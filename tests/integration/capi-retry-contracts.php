<?php
/** INT-005 native manual retry identity, eligibility, persistence and recovery. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
function idlm_capi_native_fixture($platform='google',$stage='Qualified',$event=null): array {
    foreach(array('meta','google','linkedin') as $p){update_option('idlm_'.$p.'_capi_enabled',$p===$platform?'1':'0');delete_option('idlm_'.$p.'_event_mappings');}
    foreach(array('idlm_meta_pixel_id'=>'synthetic-pixel','idlm_meta_access_token'=>'synthetic-meta','idlm_google_measurement_id'=>'G-SYNTHETIC','idlm_google_api_secret'=>'synthetic-google','idlm_linkedin_conversion_id'=>'synthetic-conversion','idlm_linkedin_access_token'=>'synthetic-linkedin') as $key=>$value){update_option($key,$value);}
    $defaults=array('google'=>array('Qualified'=>'CRM Qualified Lead','Won'=>'Closed Won Interior Project'),'meta'=>array('New'=>'Lead','Qualified'=>'Contact','Quote Shared'=>'Subscribe','Won'=>'Purchase'),'linkedin'=>array('Qualified'=>'CRM Qualified Lead','Won'=>'Closed Won'));
    if($event!==null){update_option('idlm_'.$platform.'_event_mappings',array($stage=>$event));}else{$event=$defaults[$platform][$stage];}
    $lead=idlm_lifecycle_fixture(array('stage'=>$stage,'lead_quality'=>'Qualified','source'=>'Google Ads','gclid'=>'synthetic-click','li_fat_id'=>'synthetic-li','deal_value'=>125000));
    $id=IDLM_DB::log_capi_event($lead,$platform,$event,array(),'Synthetic rejected delivery','failed');
    return array($lead,$id);
}
function idlm_capi_native_capture(callable $callback,$code=200): array {
    $calls=array();$filter=static function($pre,$args,$url)use(&$calls,$code){
        if(!preg_match('~graph.facebook.com|google-analytics.com|api.linkedin.com~',$url)){return $pre;}
        $calls[]=array('url'=>$url,'args'=>$args);
        return $code==='timeout'?new WP_Error('synthetic_timeout','Synthetic timeout'):array('response'=>array('code'=>$code),'body'=>'{"events_received":1}','headers'=>array());
    };add_filter('pre_http_request',$filter,PHP_INT_MAX,3);
    try{$result=$callback();}finally{remove_filter('pre_http_request',$filter,PHP_INT_MAX);}
    return array($result,$calls);
}
function idlm_capi_receipt_key($root): string {return 'idlm_capi_retry_'.hash('sha256',IDLM_Recovery_Guard::generation().'|'.$root);}
idlm_integration_test('T08-N-INT005-current Google defaults and custom platform events retry exact attempts',static function():void {
    global $wpdb;
    foreach(array(array('google','Qualified',null),array('google','Won',null),array('meta','Qualified','custom_meta'),array('google','Qualified','custom_google'),array('linkedin','Won','custom_linkedin')) as [$platform,$stage,$event]){
        [$lead,$id]=idlm_capi_native_fixture($platform,$stage,$event);[$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));
        idlm_integration_assert_same(true,$result['success'],$result['message']);idlm_integration_assert_same(1,count($calls));idlm_integration_assert_true($result['log_id']>$id);idlm_integration_assert_true($result['attempt_id']!=='');
        $log=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_capi_logs WHERE id=%d",$result['log_id']));$payload=maybe_unserialize($log->payload);$meta=$payload['_idlm_delivery'];
        idlm_integration_assert_same($result['attempt_id'],$meta['attempt_id']);idlm_integration_assert_same($stage,$meta['stage']);idlm_integration_assert_same('success',$log->status);idlm_integration_assert_same($id,$meta['retry_root']);
        idlm_integration_assert_true(strpos(wp_json_encode($meta),'synthetic-google')===false);idlm_integration_assert_true(!isset(json_decode($calls[0]['args']['body'],true)['_idlm_delivery']));
        if ('google' === $platform) { $sent=json_decode($calls[0]['args']['body'],true);idlm_integration_assert_same(1,preg_match('/^[0-9]+\.[0-9]+$/D',$sent['client_id']));idlm_integration_assert_same('synthetic-click',$sent['events'][0]['params']['gclid']); }
    }
});
idlm_integration_test('T08-N-INT005-worker forced attribution and retry share current exclusion gates',static function():void {
    global $wpdb;
    foreach(array('duplicate','reversed','disabled','deleted','google_source','linkedin_identity') as $case){
        $platform=$case==='linkedin_identity'?'linkedin':($case==='google_source'?'google':'meta');[$lead,$id]=idlm_capi_native_fixture($platform);
        if($case==='duplicate'){$wpdb->update($wpdb->prefix.'idlm_leads',array('is_duplicate'=>1),array('id'=>$lead));}
        if($case==='reversed'){$wpdb->update($wpdb->prefix.'idlm_leads',array('stage'=>'New','lead_quality'=>''),array('id'=>$lead));}
        if($case==='disabled'){$wpdb->update($wpdb->options,array('option_value'=>'0'),array('option_name'=>'idlm_meta_capi_enabled'));}
        if($case==='deleted'){$wpdb->delete($wpdb->prefix.'idlm_leads',array('id'=>$lead));}
        if($case==='google_source'){$wpdb->update($wpdb->prefix.'idlm_leads',array('source'=>'Meta Ads','gclid'=>''),array('id'=>$lead));}
        if($case==='linkedin_identity'){$wpdb->update($wpdb->prefix.'idlm_leads',array('li_fat_id'=>''),array('id'=>$lead));}
        [$result,$calls]=idlm_capi_native_capture(static function()use($id,$lead,$platform){$r=IDLM_CAPI::retry_capi_log($id);IDLM_CAPI::process_capi_event($lead,'Qualified');$method='fire_'.$platform.'_capi';IDLM_CAPI::$method((object)array('id'=>$lead),'Qualified',true);return $r;});
        idlm_integration_assert_same(false,$result['success'],$case);idlm_integration_assert_same(0,count($calls),$case);
        wp_cache_delete('alloptions','options');
    }
});
idlm_integration_test('T08-N-INT005-legacy ambiguity mapping changes and prior success never report a resend',static function():void {
    global $wpdb;[$lead,$id]=idlm_capi_native_fixture();
    IDLM_DB::log_capi_event($lead,'google','CRM Qualified Lead',array(),'old success','success');
    [$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));idlm_integration_assert_same(false,$result['success']);idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same(0,$result['log_id']);
    [$lead,$id]=idlm_capi_native_fixture('google','Qualified','same');update_option('idlm_google_event_mappings',array('Qualified'=>'same','Won'=>'same'));
    [$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));idlm_integration_assert_same(false,$result['success']);idlm_integration_assert_same(0,count($calls));
    update_option('idlm_google_event_mappings',array('Qualified'=>'changed'));[$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));idlm_integration_assert_same(false,$result['success']);idlm_integration_assert_same(0,count($calls));
});
idlm_integration_test('T08-N-INT005-snapshots bind destination commercial data and timing while credentials refresh',static function():void {
    global $wpdb,$config;[$lead,$unused]=idlm_capi_native_fixture('meta','Won');
    [$first,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::fire_meta_capi((object)array('id'=>$lead),'Won'),429);$initial=json_decode($calls[0]['args']['body'],true);
    get_option('idlm_meta_access_token');$other=new wpdb($config['db_user'],$config['db_password'],$config['db_name'],$config['db_host']);$other->update($wpdb->options,array('option_value'=>'synthetic-rotated'),array('option_name'=>'idlm_meta_access_token'));$other->update($wpdb->prefix.'idlm_leads',array('deal_value'=>999999,'email'=>'fresh@example.test'),array('id'=>$lead));$other->close();update_option('idlm_currency_code','USD');
    [$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($first['log_id']));$retry=json_decode($calls[0]['args']['body'],true);
    idlm_integration_assert_same(true,$result['success']);idlm_integration_assert_same('Bearer synthetic-rotated',$calls[0]['args']['headers']['Authorization']);idlm_integration_assert_true(strpos($calls[0]['url'],'synthetic-rotated')===false);idlm_integration_assert_same($initial['data'][0]['custom_data']['value'],$retry['data'][0]['custom_data']['value']);idlm_integration_assert_same($initial['data'][0]['custom_data']['currency'],$retry['data'][0]['custom_data']['currency']);idlm_integration_assert_same($initial['data'][0]['event_time'],$retry['data'][0]['event_time']);idlm_integration_assert_same($initial['data'][0]['event_id'],$retry['data'][0]['event_id']);idlm_integration_assert_same(array(hash('sha256','fresh@example.test')),$retry['data'][0]['user_data']['em']);
    [$lead,$unused]=idlm_capi_native_fixture();[$first]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::fire_google_capi((object)array('id'=>$lead),'Qualified'),503);update_option('idlm_google_measurement_id','G-CHANGED');[$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($first['log_id']));idlm_integration_assert_same(false,$result['success']);idlm_integration_assert_same(0,count($calls));update_option('idlm_currency_code','INR');
});
idlm_integration_test('T08-N-INT005-failures bounded across root chain and uncertain or permanent results refuse replay',static function():void {
    [$lead,$id]=idlm_capi_native_fixture('meta');$count=0;
    for($i=0;$i<3;$i++){[$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id),429);$count+=count($calls);idlm_integration_assert_same(false,$result['success']);$id=$result['log_id'];}
    [$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id),429);idlm_integration_assert_same(3,$count);idlm_integration_assert_same(0,count($calls));
    foreach(array(400,'timeout') as $failure){[$lead,$id]=idlm_capi_native_fixture();[$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id),$failure);idlm_integration_assert_same(1,count($calls));$new=$result['log_id'];[$again,$calls]=idlm_capi_native_capture(static function()use($id,$new){IDLM_CAPI::retry_capi_log($new);return IDLM_CAPI::retry_capi_log($id);});idlm_integration_assert_same(false,$again['success']);idlm_integration_assert_same(0,count($calls));}
});
idlm_integration_test('T08-N-INT005-required claim log and acknowledgement failures preserve uncertainty',static function():void {
    global $wpdb;[$lead,$id]=idlm_capi_native_fixture();$key=idlm_capi_receipt_key($id);$deny=static fn($new,$old)=>$old;add_filter('pre_update_option_'.$key,$deny,10,2);
    try{[$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));}finally{remove_filter('pre_update_option_'.$key,$deny,10);}idlm_integration_assert_same(false,$result['success']);idlm_integration_assert_same(0,count($calls));
    $break=static function($sql)use($wpdb){return strpos($sql,'INSERT INTO `'.$wpdb->prefix.'idlm_capi_logs`')===0?'INSERT INTO idlm_int005_missing_table VALUES (1)':$sql;};$old=$wpdb->suppress_errors(true);add_filter('query',$break);
    try{[$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));}finally{remove_filter('query',$break);$wpdb->suppress_errors($old);}idlm_integration_assert_same(false,$result['success']);idlm_integration_assert_same('uncertain',$result['outcome']);idlm_integration_assert_same(1,count($calls));[$again,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));idlm_integration_assert_same(0,count($calls));
    [$lead,$id]=idlm_capi_native_fixture();$key=idlm_capi_receipt_key($id);$deny_sent=static fn($new,$old)=>$new['status']==='sent'?$old:$new;add_filter('pre_update_option_'.$key,$deny_sent,10,2);
    try{[$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));}finally{remove_filter('pre_update_option_'.$key,$deny_sent,10);}idlm_integration_assert_same(false,$result['success']);idlm_integration_assert_same(1,count($calls));[$again,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));idlm_integration_assert_same(0,count($calls));
});
idlm_integration_test('T08-C-INT005-crash after synthetic acceptance never automatically resends',static function():void {
    [$lead,$id]=idlm_capi_native_fixture();@unlink(WP_CONTENT_DIR.'/capi-provider.log');$result=idlm_integration_worker(array('capi-retry-crash',$id));idlm_integration_assert_same(77,$result['status'],$result['stderr']);
    [$again,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));idlm_integration_assert_same(false,$again['success']);idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('uncertain',get_option(idlm_capi_receipt_key($id))['status']);idlm_integration_assert_same(1,count(file(WP_CONTENT_DIR.'/capi-provider.log',FILE_IGNORE_NEW_LINES)));
});
idlm_integration_test('T08-C-INT005-independent manual workers and normal worker serialize provider delivery',static function():void {
    foreach(array(false,true) as $normal){[$lead,$id]=idlm_capi_native_fixture();@unlink(WP_CONTENT_DIR.'/capi-provider.log');@unlink(WP_CONTENT_DIR.'/capi-barrier');$jobs=array();
        foreach(array('capi-retry-race',$normal?'capi-normal-race':'capi-retry-race') as $mode){$pipes=array();$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/worker.php').' '.$mode.' '.(int)($mode==='capi-normal-race'?$lead:$id);$proc=proc_open($cmd,array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$jobs[]=array($proc,$pipes);}
        foreach($jobs as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);idlm_integration_assert_same(0,proc_close($proc),$out.$err);}
        idlm_integration_assert_same(1,count(file(WP_CONTENT_DIR.'/capi-provider.log',FILE_IGNORE_NEW_LINES)));
    }
});
idlm_integration_test('T08-T16-T17-N-INT005-receipts stay outside backups and erase on restore and reset',static function():void {
    global $wpdb;idlm_native_backup_fixture();[$lead,$id]=idlm_capi_native_fixture();idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id),503);$key=idlm_capi_receipt_key($id);$snapshot=idlm_native_backup_call('snapshot');idlm_integration_assert_true(!is_wp_error($snapshot));foreach($snapshot['options'] as $option){idlm_integration_assert_true(strpos($option['option_name'],'idlm_capi_retry_')!==0);}
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$snapshot));idlm_integration_assert_same(false,get_option($key));
    [$lead,$id]=idlm_capi_native_fixture();idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id),503);$key=idlm_capi_receipt_key($id);$count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads");idlm_integration_assert_true(!is_wp_error(IDLM_Lead_Deletion_Service::reset_all((string)$count)));idlm_integration_assert_same(false,get_option($key));
});
idlm_integration_test('T08-N-INT005-active transactions and malformed identities refuse provider calls',static function():void {
    global $wpdb;[$lead,$id]=idlm_capi_native_fixture();$wpdb->query('START TRANSACTION');try{[$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));idlm_integration_assert_same(false,$result['success']);idlm_integration_assert_same(0,count($calls));}finally{$wpdb->query('ROLLBACK');}
    $wpdb->update($wpdb->prefix.'idlm_capi_logs',array('payload'=>serialize(array('_idlm_delivery'=>array('version'=>1)))),array('id'=>$id));[$result,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($id));idlm_integration_assert_same(false,$result['success']);idlm_integration_assert_same(0,count($calls));
});

function idlm_capi_quote_native_revision($lead,$value,$currency='INR'):array {
    global $wpdb;$now=current_time('mysql');
    idlm_integration_assert_same(1,$wpdb->insert($wpdb->prefix.'idlm_quotes',array('quote_number'=>'CAPI-'.wp_generate_uuid4(),'lead_id'=>$lead,'status'=>'Draft','created_at'=>$now,'updated_at'=>$now)));
    $quote=(int)$wpdb->insert_id;
    idlm_integration_assert_same(1,$wpdb->insert($wpdb->prefix.'idlm_quote_revisions',array('quote_id'=>$quote,'revision_number'=>1,'revision_name'=>wp_generate_uuid4(),'status'=>'Superseded','grand_total'=>100000,'currency_code'=>'USD','created_at'=>$now,'updated_at'=>$now)));
    idlm_integration_assert_same(1,$wpdb->insert($wpdb->prefix.'idlm_quote_revisions',array('quote_id'=>$quote,'revision_number'=>2,'revision_name'=>wp_generate_uuid4(),'status'=>'Draft','grand_total'=>$value,'currency_code'=>$currency,'created_at'=>$now,'updated_at'=>$now)));
    $revision=(int)$wpdb->insert_id;$wpdb->update($wpdb->prefix.'idlm_quotes',array('current_revision_id'=>$revision),array('id'=>$quote));
    return array($quote,$revision);
}
idlm_integration_test('CAPIQUOTE-N latest saved quotation wins over stale projection and manual deal with original quote currency',static function():void {
    global $wpdb;[$lead]=idlm_capi_native_fixture('meta','Won');idlm_capi_quote_native_revision($lead,315500.75,'INR');
    $wpdb->update($wpdb->prefix.'idlm_leads',array('quote_value'=>100000),array('id'=>$lead));update_option('idlm_currency_code','USD');
    try {
        [$r,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::fire_meta_capi((object)array('id'=>$lead),'Won'));
        idlm_integration_assert_same(true,$r['success'],$r['message']);$data=json_decode($calls[0]['args']['body'],true)['data'][0];
        idlm_integration_assert_same('Purchase',$data['event_name']);idlm_integration_assert_same(315500.75,$data['custom_data']['value']);idlm_integration_assert_same('INR',$data['custom_data']['currency']);
        idlm_integration_assert_same('125000.00',$wpdb->get_var($wpdb->prepare("SELECT deal_value FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$lead)));
    } finally {update_option('idlm_currency_code','INR');}
});
idlm_integration_test('CAPIQUOTE-N external or zero-priced quote uses entered final Won amount',static function():void {
    foreach(array(false,true) as $zero){
        [$lead]=idlm_capi_native_fixture('meta','Won');if($zero){idlm_capi_quote_native_revision($lead,0);}
        [$r,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::fire_meta_capi((object)array('id'=>$lead),'Won'));
        idlm_integration_assert_same(true,$r['success'],$r['message']);$data=json_decode($calls[0]['args']['body'],true)['data'][0]['custom_data'];idlm_integration_assert_same(125000,$data['value']);idlm_integration_assert_same('INR',$data['currency']);
    }
});
idlm_integration_test('CAPIQUOTE-N retry preserves first quoted value and currency after quotation and deal edits',static function():void {
    global $wpdb;[$lead]=idlm_capi_native_fixture('meta','Won');[$quote,$revision]=idlm_capi_quote_native_revision($lead,315500.75,'INR');
    [$first,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::fire_meta_capi((object)array('id'=>$lead),'Won'),429);$initial=json_decode($calls[0]['args']['body'],true)['data'][0];
    $wpdb->update($wpdb->prefix.'idlm_quote_revisions',array('grand_total'=>999999,'currency_code'=>'USD'),array('id'=>$revision));$wpdb->update($wpdb->prefix.'idlm_leads',array('deal_value'=>888888),array('id'=>$lead));
    [$r,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::retry_capi_log($first['log_id']));$retry=json_decode($calls[0]['args']['body'],true)['data'][0];
    idlm_integration_assert_same(true,$r['success'],$r['message']);idlm_integration_assert_same(315500.75,$retry['custom_data']['value']);idlm_integration_assert_same('INR',$retry['custom_data']['currency']);idlm_integration_assert_same($initial['event_id'],$retry['event_id']);idlm_integration_assert_same($initial['event_time'],$retry['event_time']);
});
