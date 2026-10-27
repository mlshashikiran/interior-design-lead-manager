<?php
/** Ordinary Interakt delivery regressions. Builder: Shashi. */
function idlm_dispatch_fast_fixture() {
    $GLOBALS['idlm_test_http_calls']=array(); $GLOBALS['idlm_test_http_code']=200; $GLOBALS['idlm_test_queue_failure']=false;
    $GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('call_status'=>'No Answer','phone'=>'9999900011'))));
    update_option('idlm_interakt_enabled','1');update_option('idlm_whatsapp_notifications_enabled','1'); update_option('idlm_interakt_api_key','synthetic-original');
    return IDLM_Interakt::trigger_generic_event(1,'lead_no_answer');
}
idlm_test('Interakt sends the documented encoded dashboard key once and retains legacy raw-key compatibility',static function():void {
    $encoded=base64_encode('synthetic-user:synthetic-password');
    foreach(array($encoded=>'Basic '.$encoded,'synthetic-legacy'=>'Basic '.base64_encode('synthetic-legacy:'))as$key=>$expected){
        idlm_test_reset_environment();$job=idlm_dispatch_fast_fixture();update_option('idlm_interakt_api_key',$key);
        IDLM_Interakt::process_async_track_event($job);
        assert_same(1,count($GLOBALS['idlm_test_http_calls']));
        assert_same($expected,$GLOBALS['idlm_test_http_calls'][0][1]['headers']['Authorization']);
        assert_false(strpos(json_encode(get_option($job)),$key)!==false);
    }
});
idlm_test('INT004 opaque ordinary jobs use WP-Cron and fresh credentials and traits',static function():void {
    $key=idlm_dispatch_fast_fixture(); assert_true(is_string($key));
    assert_same(array($key),$GLOBALS['idlm_test_schedules'][0]['args']); assert_same(0,count($GLOBALS['idlm_test_http_calls']));
    assert_false(strpos(json_encode(get_option($key)),'synthetic-original')!==false);
    update_option('idlm_interakt_api_key','synthetic-rotated');
    $GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('call_status'=>'No Answer','phone'=>'9999900022','first_name'=>'Fresh'))));
    assert_true(IDLM_Interakt::process_async_track_event($key)); assert_true(IDLM_Interakt::process_async_track_event($key));
    assert_same(1,count($GLOBALS['idlm_test_http_calls']));
    $args=$GLOBALS['idlm_test_http_calls'][0][1]; assert_same('Basic '.base64_encode('synthetic-rotated:'),$args['headers']['Authorization']);
    $payload=json_decode($args['body'],true); assert_same('9999900022',$payload['phoneNumber']); assert_true(strpos($payload['traits']['name'],'Fresh')===0);
    assert_same('sent',get_option($key)['status']);
});
idlm_test('INT004 disable duplicate reversal ownership missing and invalid phone suppress ordinary delivery',static function():void {
    foreach(array('disabled','duplicate','reversed','owner','deleted','phone') as $case){
        idlm_test_reset_environment(); $key=idlm_dispatch_fast_fixture();
        $row=lead_fixture(array('call_status'=>'No Answer','phone'=>'9999900011'));
        if($case==='disabled'){update_option('idlm_interakt_enabled','0');}
        if($case==='duplicate'){$row['is_duplicate']=1;}
        if($case==='reversed'){$row['call_status']='Connected';}
        if($case==='owner'){$row['assigned_rep_id']=999;}
        if($case==='phone'){$row['phone']='1';}
        $GLOBALS['wpdb']->seed('idlm_leads',$case==='deleted'?array():array($row));
        IDLM_Interakt::process_async_track_event($key); assert_same(0,count($GLOBALS['idlm_test_http_calls'])); assert_same('cancelled',get_option($key)['status']);
    }
});
idlm_test('INT004 legacy secret payload callbacks are refused without HTTP or rescheduling',static function():void {
    idlm_dispatch_fast_fixture(); $schedules=count($GLOBALS['idlm_test_schedules']);
    assert_false(IDLM_Interakt::process_async_track_event(array('event'=>'lead_no_answer'),'revoked-synthetic-key',1,0));
    assert_same(0,count($GLOBALS['idlm_test_http_calls'])); assert_same($schedules,count($GLOBALS['idlm_test_schedules']));
});
idlm_test('INT004 provider failure and interrupted claims remain uncertain without automatic resend',static function():void {
    $key=idlm_dispatch_fast_fixture(); $GLOBALS['idlm_test_http_code']=503;
    IDLM_Interakt::process_async_track_event($key); IDLM_Interakt::process_async_track_event($key);
    assert_same('uncertain',get_option($key)['status']); assert_same(1,count($GLOBALS['idlm_test_http_calls']));
    idlm_test_reset_environment(); $key=idlm_dispatch_fast_fixture(); $job=get_option($key);$job['status']='processing';update_option($key,$job);
    IDLM_Interakt::process_async_track_event($key);assert_same('uncertain',get_option($key)['status']);assert_same(0,count($GLOBALS['idlm_test_http_calls']));
});
idlm_test('INT004 failed durable claim prevents HTTP and can retry before any attempted send',static function():void {
    $key=idlm_dispatch_fast_fixture();$GLOBALS['idlm_test_option_write_failures']=array($key);
    assert_false(IDLM_Interakt::process_async_track_event($key));assert_same(0,count($GLOBALS['idlm_test_http_calls']));assert_same('queued',get_option($key)['status']);
    $GLOBALS['idlm_test_option_write_failures']=array();assert_true(IDLM_Interakt::process_async_track_event($key));assert_same(1,count($GLOBALS['idlm_test_http_calls']));
});
idlm_test('INT004 enable epoch and recovery generation cancel earlier ordinary jobs',static function():void {
    $key=idlm_dispatch_fast_fixture();IDLM_Interakt_Dispatch::enable_epoch('1','0');IDLM_Interakt::process_async_track_event($key);
    assert_same('cancelled',get_option($key)['status']);assert_same(0,count($GLOBALS['idlm_test_http_calls']));
    idlm_test_reset_environment();$key=idlm_dispatch_fast_fixture();update_option(IDLM_Recovery_Guard::GENERATION,'1');
    assert_false(IDLM_Interakt::process_async_track_event($key));assert_same(0,count($GLOBALS['idlm_test_http_calls']));
});
