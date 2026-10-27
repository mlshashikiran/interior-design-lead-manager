<?php
/** Actual AJAX/webhook atomic failure and retry contracts. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_atomic_http_trigger(string $suffix, string $operation, callable $request): array {
    global $wpdb;
    $trigger=$wpdb->prefix.'idlm_http_atomic_fail';
    idlm_http_check(false!==$wpdb->query("CREATE TRIGGER {$trigger} BEFORE {$operation} ON {$wpdb->prefix}{$suffix} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic HTTP persistence failure'"),'Unable to inject failure');
    try {return $request();} finally {$wpdb->query("DROP TRIGGER {$trigger}");}
}
function idlm_atomic_http_lead(): int {
    global $wpdb,$users;$now=current_time('mysql');
    idlm_http_check(1===$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic atomic HTTP','email'=>wp_generate_uuid4().'@example.test','assigned_rep_id'=>$users['owner'],'stage'=>'New','lead_quality'=>'','call_status'=>'Not Called','floor_plan_received'=>1,'project_name'=>'Synthetic residence','property_location'=>'Synthetic location','budget'=>'8 lakh','possession_date'=>'2027-01-01','property_type'=>'Apartment','project_timeline'=>'3 months','created_at'=>$now,'updated_at'=>$now)),'Fixture insert failed');return (int)$wpdb->insert_id;
}
foreach(array('idlm_lead_activity_log','idlm_lead_state_events','idlm_tasks','idlm_lead_outbox') as $table){
    idlm_http_case('T04-H-'.$table.'-failure-returns-error-with-no-partial-state-and-retry-succeeds',static function()use($table):void {
        global $wpdb,$sessions;$lead=idlm_atomic_http_lead();$wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$lead,'task_type'=>'Qualification','title'=>'Synthetic HTTP task','status'=>'pending'));$before=idlm_http_snapshot();
        $request=static function()use($lead,$sessions){return idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_quick_edit_lead','nonce'=>$sessions['owner']['admin'],'lead_id'=>$lead,'field'=>'stage','value'=>'Qualified'));};
        $r=idlm_atomic_http_trigger($table,$table==='idlm_tasks'?'UPDATE':'INSERT',$request);$json=json_decode($r['body'],true);
        idlm_http_check($r['status']===200 && is_array($json) && $json['success']===false,'Request falsely acknowledged or returned invalid error: '.$r['body']);idlm_http_check($before===idlm_http_snapshot(),'Failed update changed aggregate, jobs or outbound calls');
        $retry=$request();$json=json_decode($retry['body'],true);idlm_http_check($json['success']===true,'Retry failed: '.$retry['body']);
        idlm_http_check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_state_events WHERE lead_id={$lead}")===1,'Retry duplicated or omitted transition');
    });
}

function idlm_atomic_webhook(array $payload): array {
    global $config;
    $file=tempnam($config['content_dir'],'lifecycle-json-');$response=$file.'.response';$headers=$file.'.headers';file_put_contents($file,wp_json_encode($payload));
    $args=array('curl','--silent','--show-error','--max-time','15','--header','Content-Type: application/json','--header','X-IDLM-API-Key: lifecycle-synthetic-key','--data-binary','@'.$file,'--dump-header',$headers,'--output',$response,'--write-out','%{http_code}',$config['http_url'].'/wp-json/interior-design-lead-manager/v1/webhook?platform=website');
    $proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$status=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
    $r=array('status'=>(int)$status,'body'=>(string)file_get_contents($response),'headers'=>(string)file_get_contents($headers));foreach(array($file,$response,$headers)as$p){unlink($p);}idlm_http_check($exit===0,'Webhook transport failed: '.$error);return$r;
}
IDLM_Secret_Store::set('idlm_webhook_api_key','lifecycle-synthetic-key');
update_option('idlm_field_mappings_website',array('first_name'=>'first_name','email'=>'email','phone'=>'phone','gclid'=>'gclid','fb_lead_id'=>'fb_lead_id'));
update_option('idlm_assignment_mode','manual'); IDLM_Settings::sync_routing_from_legacy();
foreach(array('new','matched') as $kind){
    idlm_http_case('T02-H-'.$kind.'-webhook-touchpoint-failure-is-not-acknowledged-and-retry-is-canonical',static function()use($kind):void {
        global $wpdb;$lead=$kind==='matched'?idlm_atomic_http_lead():0;$email=$lead?$wpdb->get_var("SELECT email FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"):wp_generate_uuid4().'@example.test';$payload=array('first_name'=>'Synthetic intake','email'=>$email,'phone'=>'+1 555 000 '.wp_rand(1000,9999),'gclid'=>'synthetic-click','fb_lead_id'=>wp_generate_uuid4());$before=idlm_http_snapshot();
        $r=idlm_atomic_http_trigger('idlm_lead_touchpoints','INSERT',static function()use($payload){return idlm_atomic_webhook($payload);});$json=json_decode($r['body'],true);
        idlm_http_check($r['status']===500 && $json['success']===false,'Webhook acknowledged failed touchpoint: '.$r['body']);idlm_http_check($before===idlm_http_snapshot(),'Failed capture left rows, schedules or outbound delivery');
        $retry=idlm_atomic_webhook($payload);$json=json_decode($retry['body'],true);idlm_http_check($retry['status']===200 && $json['success']===true,'Retry failed: '.$retry['body']);$id=(int)$json['lead_id'];if($lead){idlm_http_check($id===$lead,'Matched lead identity changed');}
        $repeat=idlm_atomic_webhook($payload);$again=json_decode($repeat['body'],true);idlm_http_check($repeat['status']===200 && (int)$again['lead_id']===$id && $again['created']===false,'Repeat was not canonical');
        idlm_http_check((int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE platform='website' AND external_id=%s",$payload['fb_lead_id']))===1,'External replay appended touchpoint');
    });
}

idlm_http_case('T04-H-manual-intake-required-activity-failure-returns-error-without-created-lead',static function():void {
    global $sessions;$before=idlm_http_snapshot();$form=http_build_query(array('first_name'=>'Synthetic manual','email'=>wp_generate_uuid4().'@example.test','phone'=>'+15550123456'));
    $request=static function()use($sessions,$form){return idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_add_lead_manual','nonce'=>$sessions['owner']['admin'],'form_data'=>$form));};
    $r=idlm_atomic_http_trigger('idlm_lead_activity_log','INSERT',$request);$json=json_decode($r['body'],true);idlm_http_check($json['success']===false,'Manual intake acknowledged failed activity');idlm_http_check($before===idlm_http_snapshot(),'Failed manual intake changed aggregate');
    $r=$request();$json=json_decode($r['body'],true);idlm_http_check($json['success']===true,'Manual retry failed: '.$r['body']);
});
