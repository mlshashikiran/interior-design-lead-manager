<?php
/** INT-005 actual administrator/nonce boundary and attempt reporting. Builder: Shashi. */
if ('cli'!==PHP_SAPI || strpos($config['db_name']??'','idlm_test_')!==0) { exit(1); }
function idlm_capi_http_snapshot(): string {
    global $wpdb,$config;$rows=array();
    foreach($wpdb->get_col("SHOW TABLES LIKE 'wp_idlm_%'") as $table){$rows[$table]=$wpdb->get_results("SELECT * FROM `$table` ORDER BY 1",ARRAY_A);}
    $rows['receipts']=$wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE 'idlm_capi_retry_%' ORDER BY option_name",ARRAY_A);
    $rows['http']=is_file($config['content_dir'].'/capi-provider-calls.jsonl')?file_get_contents($config['content_dir'].'/capi-provider-calls.jsonl'):'';
    return hash('sha256',wp_json_encode($rows));
}
function idlm_capi_http_call(array $ids,string $actor='admin',$nonce=null): array {
    global $sessions;return idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_retry_capi_events','log_ids'=>$ids,'nonce'=>$nonce??$sessions[$actor]['admin']),$actor);
}
function idlm_capi_http_fixture(string $stage='Qualified',string $event='CRM Qualified Lead'): array {
    global $wpdb,$users;$now=current_time('mysql');$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic CAPI retry','assigned_rep_id'=>$users['owner'],'stage'=>$stage,'lead_quality'=>'Qualified','source'=>'Google Ads','gclid'=>'synthetic-http-click','created_at'=>$now,'updated_at'=>$now));$lead=(int)$wpdb->insert_id;
    return array($lead,IDLM_DB::log_capi_event($lead,'google',$event,array(),'Synthetic rejected delivery','failed'));
}
update_option('idlm_google_capi_enabled','1');update_option('idlm_google_measurement_id','G-SYNTHETIC');update_option('idlm_google_api_secret','synthetic-http-secret');delete_option('idlm_google_event_mappings');
file_put_contents($config['content_dir'].'/capi-provider-response.json',wp_json_encode(array('code'=>200)));
[$lead,$log]=idlm_capi_http_fixture();
idlm_http_case('T08-H-INT005-anonymous-no-retry',static function()use($log){$before=idlm_capi_http_snapshot();$r=idlm_capi_http_call(array($log),'anonymous','invalid');idlm_http_check($r['status']>=400,'Anonymous retry accepted');idlm_http_check($before===idlm_capi_http_snapshot(),'Anonymous retry wrote state');});
idlm_http_case('T08-H-INT005-nonadministrator-no-retry',static function()use($log){foreach(array('owner','manager','readall') as $actor){$before=idlm_capi_http_snapshot();$body=json_decode(idlm_capi_http_call(array($log),$actor)['body'],true);idlm_http_check(empty($body['success']),'Nonadministrator retry accepted');idlm_http_check($before===idlm_capi_http_snapshot(),'Denied retry wrote state');}});
idlm_http_case('T08-H-INT005-bad-and-cross-user-nonce-no-retry',static function()use($log){global $sessions;foreach(array('invalid',$sessions['owner']['admin']) as $nonce){$before=idlm_capi_http_snapshot();$r=idlm_capi_http_call(array($log),'admin',$nonce);idlm_http_check($r['status']===403,'Invalid nonce accepted');idlm_http_check($before===idlm_capi_http_snapshot(),'Nonce rejection wrote state');}});
idlm_http_case('T08-H-INT005-bounded-scalar-selection-no-retry',static function()use($log){foreach(array(array_fill(0,51,$log),array(array($log)),array('0'),array('1e2')) as $ids){$before=idlm_capi_http_snapshot();$body=json_decode(idlm_capi_http_call($ids)['body'],true);idlm_http_check(empty($body['success']),'Malformed selection accepted');idlm_http_check($before===idlm_capi_http_snapshot(),'Invalid selection wrote state');}});
idlm_http_case('T08-H-INT005-default-Google-events-report-exact-new-attempts',static function()use($log){global $wpdb;[$won,$won_log]=idlm_capi_http_fixture('Won','Closed Won Interior Project');$body=json_decode(idlm_capi_http_call(array($log,$log,$won_log))['body'],true);idlm_http_check(!empty($body['success']),'Authorized retry failed');idlm_http_check(count($body['data']['attempts'])===2,'Duplicate selection was repeated');foreach($body['data']['attempts'] as $attempt){idlm_http_check($attempt['success'] && $attempt['log_id']>$attempt['source_log_id'],'No new successful attempt');$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_capi_logs WHERE id=%d",$attempt['log_id']));$payload=maybe_unserialize($row->payload);idlm_http_check($payload['_idlm_delivery']['attempt_id']===$attempt['attempt_id'],'Attempt ID mismatch');}});
idlm_http_case('T08-H-INT005-disabled-and-prior-success-report-no-send',static function()use($log){[$lead,$new]=idlm_capi_http_fixture();update_option('idlm_google_capi_enabled','0');$body=json_decode(idlm_capi_http_call(array($new))['body'],true);idlm_http_check(strpos($body['data']['message'],'0 succeeded, 1 failed')!==false,'Disabled reported success');idlm_http_check($body['data']['attempts'][0]['log_id']===0,'Disabled created a delivery');update_option('idlm_google_capi_enabled','1');$body=json_decode(idlm_capi_http_call(array($log))['body'],true);idlm_http_check(strpos($body['data']['message'],'0 succeeded, 1 failed')!==false,'Prior successful attempt reported a resend');idlm_http_check($body['data']['attempts'][0]['log_id']===0,'Prior success reused old log');});
// Two selectable pending rows for actual browser reporting checks.
update_option('idlm_google_event_mappings',array('Qualified'=>'Browser_CRM_Qualified','Won'=>'BrowserAlreadyDelivered'));
[$browser_lead,$browser_log]=idlm_capi_http_fixture('Qualified','Browser_CRM_Qualified');[$blocked_lead,$blocked_log]=idlm_capi_http_fixture('Won','BrowserAlreadyDelivered');IDLM_DB::log_capi_event($blocked_lead,'google','BrowserAlreadyDelivered',array(),'Synthetic prior acceptance','success');
$browser_fixture=array('url'=>$config['http_url'],'database'=>$config['db_name'],'content_dir'=>$config['content_dir'],'source_log_id'=>$browser_log,'blocked_log_id'=>$blocked_log,'lead_id'=>$browser_lead);
$dir=getenv('IDLM_TEST_CAPI_RETRY_EVIDENCE_DIR');if($dir){file_put_contents($dir.'/browser-fixture.json',wp_json_encode($browser_fixture));}
