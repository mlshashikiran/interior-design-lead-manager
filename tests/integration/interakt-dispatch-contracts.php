<?php
/** INT-004 actual options, WP-Cron, current data, claims and process recovery. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
IDLM_Interakt::init();
function idlm_interakt_native_fixture(): array {
    update_option('idlm_interakt_enabled','1'); update_option('idlm_interakt_api_key','synthetic-original');
    update_option('idlm_interakt_mappings',array(array('type'=>'call_status','value'=>'No Answer','event'=>'lead_no_answer')));
    $lead=idlm_lifecycle_fixture(array('phone'=>'9999900011','call_status'=>'No Answer'));
    $key=IDLM_Interakt::trigger_generic_event($lead,'lead_no_answer');
    idlm_integration_assert_true(is_string($key),'Expected durable opaque job');
    return array($lead,$key);
}
function idlm_interakt_native_capture(callable $callback, int $code=200): array {
    $calls=array();
    $filter=static function($pre,$args,$url) use (&$calls,$code) {
        if(strpos($url,'api.interakt.ai/')===false){return $pre;}
        $calls[]=array('url'=>$url,'args'=>$args);
        return array('response'=>array('code'=>$code),'body'=>'{}','headers'=>array());
    };
    add_filter('pre_http_request',$filter,PHP_INT_MAX,3);
    try{$callback();}finally{remove_filter('pre_http_request',$filter,PHP_INT_MAX);}
    return $calls;
}
idlm_integration_test('T09-N-INT004-opaque WP-Cron jobs use current primary credentials and contact traits',static function():void {
    global $wpdb,$config;[$lead,$key]=idlm_interakt_native_fixture();
    idlm_integration_assert_true((bool)wp_next_scheduled(IDLM_Interakt_Dispatch::HOOK,array($key)));
    idlm_integration_assert_same(false,strpos(wp_json_encode(_get_cron_array()),'synthetic-original'));
    idlm_integration_assert_same(false,strpos(wp_json_encode(IDLM_Interakt_Dispatch::inspect($key)),'9999900011'));
    get_option('idlm_interakt_api_key'); // Deliberately warm stale option caches.
    $other=new wpdb($config['db_user'],$config['db_password'],$config['db_name'],$config['db_host']);
    $other->update($wpdb->options,array('option_value'=>'synthetic-rotated'),array('option_name'=>'idlm_interakt_api_key'));
    $other->update($wpdb->prefix.'idlm_leads',array('phone'=>'9999900022','first_name'=>'Fresh primary'),array('id'=>$lead));$other->close();
    $calls=idlm_interakt_native_capture(static function() use($key){do_action(IDLM_Interakt_Dispatch::HOOK,$key);do_action(IDLM_Interakt_Dispatch::HOOK,$key);});
    idlm_integration_assert_same(1,count($calls));idlm_integration_assert_same('Basic '.base64_encode('synthetic-rotated:'),$calls[0]['args']['headers']['Authorization']);
    $payload=json_decode($calls[0]['args']['body'],true);idlm_integration_assert_same('9999900022',$payload['phoneNumber']);idlm_integration_assert_same('Fresh primary',$payload['traits']['name']);
    idlm_integration_assert_same('sent',IDLM_Interakt_Dispatch::inspect($key)['status']);
});
idlm_integration_test('T09-N-INT004-disabled deleted duplicate reversed owner and mapping changes cancel',static function():void {
    global $wpdb;
    foreach(array('disabled','deleted','duplicate','reversed','owner','mapping','phone') as $case){
        [$lead,$key]=idlm_interakt_native_fixture();
        if($case==='disabled'){$wpdb->update($wpdb->options,array('option_value'=>'0'),array('option_name'=>'idlm_interakt_enabled'));}
        if($case==='deleted'){$wpdb->delete($wpdb->prefix.'idlm_leads',array('id'=>$lead));}
        if($case==='duplicate'){$wpdb->update($wpdb->prefix.'idlm_leads',array('is_duplicate'=>1),array('id'=>$lead));}
        if($case==='reversed'){$wpdb->update($wpdb->prefix.'idlm_leads',array('call_status'=>'Connected'),array('id'=>$lead));}
        if($case==='owner'){$wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>999),array('id'=>$lead));}
        if($case==='mapping'){$wpdb->update($wpdb->options,array('option_value'=>serialize(array(array('type'=>'stage','value'=>'Won','event'=>'lead_no_answer')))),array('option_name'=>'idlm_interakt_mappings'));update_option('idlm_interakt_event_name','other_event');}
        if($case==='phone'){$wpdb->update($wpdb->prefix.'idlm_leads',array('phone'=>'1'),array('id'=>$lead));}
        $calls=idlm_interakt_native_capture(static function()use($key){IDLM_Interakt::process_async_track_event($key);});
        idlm_integration_assert_same(0,count($calls),$case);idlm_integration_assert_same('cancelled',IDLM_Interakt_Dispatch::inspect($key)['status'],$case);
        update_option('idlm_interakt_event_name','lead_no_answer');wp_cache_delete('alloptions','options');wp_cache_delete('idlm_interakt_enabled','options');wp_cache_delete('idlm_interakt_mappings','options');
    }
});
idlm_integration_test('T09-N-INT004-reenable revokes old jobs and legacy payload callbacks never send',static function():void {
    [$lead,$key]=idlm_interakt_native_fixture();$epoch=IDLM_Interakt_Dispatch::inspect($key)['epoch'];
    update_option('idlm_interakt_enabled','0');update_option('idlm_interakt_enabled','1');
    idlm_integration_assert_true($epoch!==get_option(IDLM_Interakt_Dispatch::EPOCH));
    $calls=idlm_interakt_native_capture(static function()use($key,$lead){IDLM_Interakt::process_async_track_event($key);IDLM_Interakt::process_async_track_event(array('event'=>'lead_no_answer'),'revoked-key',$lead,0);});
    idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('cancelled',IDLM_Interakt_Dispatch::inspect($key)['status']);
});
idlm_integration_test('T09-N-INT004-required claim and result writes fail without duplicate delivery',static function():void {
    [$lead,$key]=idlm_interakt_native_fixture();$deny=static fn($new,$old)=>$old;
    add_filter('pre_update_option_'.$key,$deny,10,2);
    try{$calls=idlm_interakt_native_capture(static function()use($key){IDLM_Interakt::process_async_track_event($key);});}finally{remove_filter('pre_update_option_'.$key,$deny,10);}
    idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('queued',IDLM_Interakt_Dispatch::inspect($key)['status']);
    $deny_sent=static fn($new,$old)=>$new['status']==='sent'?$old:$new;add_filter('pre_update_option_'.$key,$deny_sent,10,2);
    try{$calls=idlm_interakt_native_capture(static function()use($key){IDLM_Interakt::process_async_track_event($key);});}finally{remove_filter('pre_update_option_'.$key,$deny_sent,10);}
    idlm_integration_assert_same(1,count($calls));idlm_integration_assert_same('processing',IDLM_Interakt_Dispatch::inspect($key)['status']);
    $calls=idlm_interakt_native_capture(static function()use($key){IDLM_Interakt::process_async_track_event($key);});idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('uncertain',IDLM_Interakt_Dispatch::inspect($key)['status']);
});
idlm_integration_test('T09-N-INT004-schedule failure and repeated outbox handoff retain one durable identity',static function():void {
    global $wpdb;
    update_option('idlm_interakt_enabled','1');update_option('idlm_interakt_api_key','synthetic-original');update_option('idlm_interakt_mappings',array(array('type'=>'call_status','value'=>'No Answer','event'=>'lead_no_answer')));
    $lead=idlm_lifecycle_fixture(array('phone'=>'9999900011'));$deny=static fn($pre,$event)=>$event->hook===IDLM_Interakt_Dispatch::HOOK?false:$pre;
    add_filter('pre_schedule_event',$deny,10,2);
    try{idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('call_status'=>'No Answer')));}finally{remove_filter('pre_schedule_event',$deny,10);}
    $id=(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_lead_outbox WHERE lead_id=%d AND hook='idlm_trigger_interakt_event'",$lead));idlm_integration_assert_true($id>0,'Failed enqueue lost outbox intent');
    $before=$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like(IDLM_Interakt_Dispatch::PREFIX).'%'));
    $wpdb->update($wpdb->prefix.'idlm_lead_outbox',array('available_at'=>'2020-01-01 00:00:00'),array('id'=>$id));idlm_integration_assert_same(true,IDLM_Lead_Outbox::dispatch($id));
    idlm_integration_assert_same($before,$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",$wpdb->esc_like(IDLM_Interakt_Dispatch::PREFIX).'%')));
});
idlm_integration_test('T09-C-INT004-accepted send crash becomes uncertain without resend',static function():void {
    [$lead,$key]=idlm_interakt_native_fixture();$result=idlm_integration_worker(array('interakt-dispatch-crash',$key));idlm_integration_assert_same(77,$result['status'],$result['stderr']);
    idlm_integration_assert_same('processing',IDLM_Interakt_Dispatch::inspect($key)['status']);
    $calls=idlm_interakt_native_capture(static function()use($key){IDLM_Interakt::process_async_track_event($key);});idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('uncertain',IDLM_Interakt_Dispatch::inspect($key)['status']);
    idlm_integration_assert_same(1,count(file(WP_CONTENT_DIR.'/interakt-provider.log',FILE_IGNORE_NEW_LINES)));
});
idlm_integration_test('T09-C-INT004-overlapping independent workers send once and defer contention',static function():void {
    [$lead,$key]=idlm_interakt_native_fixture();@unlink(WP_CONTENT_DIR.'/interakt-provider.log');@unlink(WP_CONTENT_DIR.'/interakt-barrier');
    $processes=array();
    for($i=0;$i<2;$i++){$pipes=array();$process=proc_open(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/worker.php').' interakt-dispatch-race '.escapeshellarg($key),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$processes[]=array($process,$pipes);}
    foreach($processes as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);idlm_integration_assert_same(0,proc_close($process),$out.$err);}
    idlm_integration_assert_same(1,count(file(WP_CONTENT_DIR.'/interakt-provider.log',FILE_IGNORE_NEW_LINES)));idlm_integration_assert_same('sent',IDLM_Interakt_Dispatch::inspect($key)['status']);
});
idlm_integration_test('T09-T16-T17-N-INT004-runtime claims excluded from backups and erased by recovery and reset',static function():void {
    global $wpdb;idlm_native_backup_fixture();[$lead,$key]=idlm_interakt_native_fixture();$snapshot=idlm_native_backup_call('snapshot');idlm_integration_assert_true(!is_wp_error($snapshot));
    foreach($snapshot['options'] as $option){idlm_integration_assert_true(strpos($option['option_name'],IDLM_Interakt_Dispatch::PREFIX)!==0);}
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$snapshot));idlm_integration_assert_same(false,IDLM_Interakt_Dispatch::inspect($key));
    [$lead,$key]=idlm_interakt_native_fixture();$count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads");$result=IDLM_Lead_Deletion_Service::reset_all((string)$count);idlm_integration_assert_true(!is_wp_error($result),'Reset failed');idlm_integration_assert_same(false,IDLM_Interakt_Dispatch::inspect($key));
});

idlm_integration_test('T09-N-INT004-failed enable epoch and malformed job fail closed',static function():void {
    [$lead,$key]=idlm_interakt_native_fixture();update_option('idlm_interakt_enabled','0');
    $deny=static fn($new,$old)=>$old;add_filter('pre_update_option_'.IDLM_Interakt_Dispatch::EPOCH,$deny,10,2);
    try{update_option('idlm_interakt_enabled','1');}finally{remove_filter('pre_update_option_'.IDLM_Interakt_Dispatch::EPOCH,$deny,10);}
    idlm_integration_assert_same('0',get_option('idlm_interakt_enabled'));
    update_option($key,array('status'=>'queued'));
    $calls=idlm_interakt_native_capture(static function()use($key){idlm_integration_assert_same(false,IDLM_Interakt::process_async_track_event($key));});idlm_integration_assert_same(0,count($calls));
});

idlm_integration_test('T09-N-INT004-delayed fallback binds original event and workers refuse caller transactions',static function():void {
    global $wpdb;[$lead,$key]=idlm_interakt_native_fixture();
    $rule=array('field'=>'call_status','value'=>'No Answer','owner'=>0,'event'=>'lead_no_answer');update_option('idlm_interakt_event_name','changed_fallback');update_option('idlm_interakt_mappings',array());
    idlm_integration_assert_same(null,IDLM_Interakt::trigger_no_answer_automation($lead,(object)array('call_status'=>'Not Called'),$rule));
    update_option('idlm_interakt_event_name','lead_no_answer');update_option('idlm_interakt_mappings',array(array('type'=>'call_status','value'=>'No Answer','event'=>'lead_no_answer')));
    $wpdb->query('START TRANSACTION');
    try{$calls=idlm_interakt_native_capture(static function()use($key){idlm_integration_assert_same(false,IDLM_Interakt::process_async_track_event($key));});idlm_integration_assert_same(0,count($calls));}finally{$wpdb->query('ROLLBACK');}
    $calls=idlm_interakt_native_capture(static function()use($key){IDLM_Interakt::process_async_track_event($key);});idlm_integration_assert_same(1,count($calls));
});
