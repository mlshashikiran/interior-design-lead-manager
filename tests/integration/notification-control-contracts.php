<?php
/** Notification opt-in, worker cancellation and inbound independence. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_') !== 0) { exit(1); }
idlm_integration_test('NOTIFY outgoing default off preserves authenticated incoming WhatsApp',static function():void {
    delete_option('idlm_whatsapp_notifications_enabled');$lead=idlm_wa_native_fixture('919999900086');
    $body=array('type'=>'message_received','data'=>array('customer'=>array('channel_phone_number'=>'919999900086'),'message'=>array('id'=>'notification-off-inbound','message_content_type'=>'Text','message'=>'Synthetic incoming reply')));
    $calls=idlm_interakt_native_capture(static function()use($body){idlm_integration_assert_same(200,idlm_wa_native_call($body,'interakt')->get_status());idlm_integration_assert_same(false,IDLM_Interakt::send_track_event_to_interakt(array('synthetic'=>true),'synthetic-key'));});
    idlm_integration_assert_same(1,idlm_wa_native_count($lead));idlm_integration_assert_same(0,count($calls));
});
idlm_integration_test('NOTIFY outgoing default off also preserves queued connector WhatsApp intake',static function():void {
    delete_option('idlm_whatsapp_notifications_enabled');
    $lead=idlm_wa_native_fixture('919999900087');
    update_option('idlm_interakt_lead_capture','1');
    $body=array('type'=>'message_received','data'=>array('customer'=>array('channel_phone_number'=>'919999900087'),'message'=>array('id'=>'notification-off-queued-inbound','message_content_type'=>'Text','message'=>'Synthetic queued incoming reply')));
    try {
        $calls=idlm_interakt_native_capture(static function()use($body,$lead){
            idlm_integration_assert_same(200,idlm_wa_native_call($body,'interakt')->get_status());
            idlm_integration_assert_same(0,idlm_wa_native_count($lead));
            $key=idlm_connector_native_key('interakt','notification-off-queued-inbound');
            idlm_integration_assert_true((bool)get_option($key),'Signed message was not durably queued');
            idlm_integration_assert_same(true,IDLM_Lead_Connector::process($key));
        });
        idlm_integration_assert_same(1,idlm_wa_native_count($lead));
        idlm_integration_assert_same(0,count($calls));
    } finally { update_option('idlm_interakt_lead_capture','0'); }
});
idlm_integration_test('NOTIFY committed disable overrides warm caches for ordinary and batch jobs',static function():void {
    global $wpdb;[$lead,$job]=idlm_interakt_native_fixture();idlm_integration_assert_same('queued',IDLM_Interakt_Batch::enqueue($lead,'lead_no_answer','notify-disable-batch'));
    $batch='idlm_interakt_batch_'.hash('sha256','notify-disable-batch|'.$lead.'|lead_no_answer');get_option('idlm_whatsapp_notifications_enabled');
    $other=idlm_integration_other_connection();$other->query("UPDATE {$wpdb->options} SET option_value='0' WHERE option_name='idlm_whatsapp_notifications_enabled'");$other->close();
    $calls=idlm_interakt_native_capture(static function()use($job,$batch){IDLM_Interakt::process_async_track_event($job);IDLM_Interakt_Batch::process($batch);});
    idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('cancelled',IDLM_Interakt_Dispatch::inspect($job)['status']);idlm_integration_assert_same('cancelled',get_option($batch)['status']);wp_cache_delete('idlm_whatsapp_notifications_enabled','options');wp_cache_delete('alloptions','options');
});
idlm_integration_test('NOTIFY re-enable cancels old ordinary and administrative backfill intent',static function():void {
    [$lead,$job]=idlm_interakt_native_fixture();idlm_integration_assert_same('queued',IDLM_Interakt_Batch::enqueue($lead,'lead_no_answer','notify-reenable-batch'));
    $batch='idlm_interakt_batch_'.hash('sha256','notify-reenable-batch|'.$lead.'|lead_no_answer');update_option('idlm_whatsapp_notifications_enabled','0');update_option('idlm_whatsapp_notifications_enabled','1');
    $calls=idlm_interakt_native_capture(static function()use($job,$batch){IDLM_Interakt::process_async_track_event($job);IDLM_Interakt_Batch::process($batch);});
    idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('cancelled',IDLM_Interakt_Dispatch::inspect($job)['status']);idlm_integration_assert_same('cancelled',get_option($batch)['status']);
});
idlm_integration_test('NOTIFY handoff email observes a committed disable despite cached options',static function():void {
    global $wpdb,$administrator_id;update_option('idlm_handoff_notifications_enabled','1');get_option('idlm_handoff_notifications_enabled');
    $lead=idlm_lifecycle_fixture();$other=idlm_integration_other_connection();$other->query("UPDATE {$wpdb->options} SET option_value='0' WHERE option_name='idlm_handoff_notifications_enabled'");$other->close();$messages=array();
    $capture=static function($pre,$args)use(&$messages){$messages[]=$args;return true;};add_filter('pre_wp_mail',$capture,PHP_INT_MAX,2);
    try{IDLM_Handoff_Notifications::send($lead,0,$administrator_id,'Synthetic disabled mail');}finally{remove_filter('pre_wp_mail',$capture,PHP_INT_MAX);wp_cache_delete('idlm_handoff_notifications_enabled','options');}
    idlm_integration_assert_same(array(),$messages);
});
