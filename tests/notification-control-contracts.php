<?php
/** Explicit notification consent. Builder: Shashi. */
idlm_test('notification switches default off and accept only the committed enabled value', static function(): void {
    foreach (array('idlm_handoff_notifications_enabled','idlm_routing_exception_notifications_enabled','idlm_whatsapp_notifications_enabled') as $option) {
        assert_false(IDLM_Settings::notification_enabled($option));
        foreach (array('0', array('1'), 'yes', 'true') as $value) { update_option($option,$value); assert_false(IDLM_Settings::notification_enabled($option)); }
        update_option($option,'1'); assert_true(IDLM_Settings::notification_enabled($option));
    }
    assert_false(IDLM_Settings::notification_enabled('arbitrary_option'));
});
idlm_test('WhatsApp notification consent is separate from configured Interakt and blocks direct sends', static function(): void {
    $job=idlm_dispatch_fast_fixture(); delete_option('idlm_whatsapp_notifications_enabled');
    assert_same(null,IDLM_Interakt::trigger_generic_event(1,'lead_no_answer'));
    assert_false(IDLM_Interakt::send_track_event_to_interakt(array('synthetic'=>true),'synthetic-key',1));
    IDLM_Interakt::process_async_track_event($job);
    assert_same('cancelled',get_option($job)['status']);assert_same(0,count($GLOBALS['idlm_test_http_calls']));
    assert_same('1',get_option('idlm_interakt_enabled'));
});
idlm_test('re-enabling WhatsApp notifications does not revive old queued events', static function(): void {
    IDLM_Interakt_Dispatch::init();$job=idlm_dispatch_fast_fixture();
    $epoch=get_option(IDLM_Interakt_Dispatch::EPOCH);update_option('idlm_whatsapp_notifications_enabled','0');update_option('idlm_whatsapp_notifications_enabled',IDLM_Interakt_Dispatch::enable_epoch('1','0'));
    assert_false($epoch===get_option(IDLM_Interakt_Dispatch::EPOCH));IDLM_Interakt::process_async_track_event($job);
    assert_same('cancelled',get_option($job)['status']);assert_same(0,count($GLOBALS['idlm_test_http_calls']));
});
