<?php
/** CAPI review D4 and D12 on native WordPress cron. Synthetic leads; no provider HTTP. Builder: Shashi. */

function idlm_capi_queue_pending(int $lead): array {
    $found = array();
    foreach ((array) _get_cron_array() as $time => $hooks) {
        foreach ((array) ($hooks['idlm_async_capi_upload_event'] ?? array()) as $event) {
            if ((int) ($event['args']['lead_id'] ?? 0) === $lead) { $found[$event['args']['stage']] = (int) $time; }
        }
    }
    ksort($found);
    return $found;
}

idlm_integration_test('CAPI D12: New is queued with no delay and never sends inside the intake request', static function(): void {
    $lead = idlm_lifecycle_fixture();
    $http = 0; $count = static function($pre) use (&$http) { $http++; return new WP_Error('blocked', 'No provider HTTP in tests.'); };
    add_filter('pre_http_request', $count, 1);
    try { IDLM_CAPI::queue_capi_event($lead, 'New', (object) array('stage'=>'')); }
    finally { remove_filter('pre_http_request', $count, 1); }
    idlm_integration_assert_same(0, $http);
    $pending = idlm_capi_queue_pending($lead);
    idlm_integration_assert_same(array('New'), array_keys($pending));
    idlm_integration_assert_true($pending['New'] <= time() + 1, 'New must be due immediately.');
    wp_clear_scheduled_hook('idlm_async_capi_upload_event', array('lead_id'=>$lead, 'stage'=>'New', 'generation'=>IDLM_Recovery_Guard::generation()));
});

idlm_integration_test('CAPI D4: a later stage keeps earlier pending stages; the same stage replaces its own job', static function(): void {
    $lead = idlm_lifecycle_fixture();
    IDLM_CAPI::queue_capi_event($lead, 'Qualified', (object) array('stage'=>'New'));
    IDLM_CAPI::queue_capi_event($lead, 'Quote Shared', (object) array('stage'=>'Qualified'));
    IDLM_CAPI::queue_capi_event($lead, 'Won', (object) array('stage'=>'Quote Shared'));
    idlm_integration_assert_same(array('Qualified', 'Quote Shared', 'Won'), array_keys(idlm_capi_queue_pending($lead)));
    IDLM_CAPI::queue_capi_event($lead, 'Won', (object) array('stage'=>'Lost'));
    $won = 0;
    foreach ((array) _get_cron_array() as $hooks) { foreach ((array) ($hooks['idlm_async_capi_upload_event'] ?? array()) as $event) { if ((int) $event['args']['lead_id'] === $lead && 'Won' === $event['args']['stage']) { $won++; } } }
    idlm_integration_assert_same(1, $won, 'Re-entering a stage keeps one pending job.');
    foreach (array('Qualified', 'Quote Shared', 'Won') as $stage) { wp_clear_scheduled_hook('idlm_async_capi_upload_event', array('lead_id'=>$lead, 'stage'=>$stage, 'generation'=>IDLM_Recovery_Guard::generation())); }
    idlm_integration_assert_same(array(), idlm_capi_queue_pending($lead));
});

idlm_integration_test('CAPI D6 phase 2 native: website webhook cookies are stored, filled on a repeat and sent to Meta', static function(): void {
    global $wpdb;
    $phone = '98' . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
    $fbc = 'fb.1.1762560000000.IwAR-native_click'; $fbp = 'fb.1.1762560000123.987654321';
    $saved_map = get_option('idlm_field_mappings_website', array());
    update_option('idlm_field_mappings_website', array('first_name'=>'first_name', 'phone'=>'phone', 'email'=>'email'));
    try {
    $first = IDLM_DB::upsert_inbound_lead(IDLM_Webhook_Intake::apply_field_mappings(array('first_name'=>'Synthetic Cookie', 'phone'=>$phone, 'email'=>'cookie-' . $phone . '@example.test', '_fbp'=>$fbp), 'website'), 'website');
    idlm_integration_assert_true(is_array($first) && !empty($first['lead_id']), IDLM_DB::get_last_validation_error());
    $lead_id = (int) $first['lead_id'];
    $row = $wpdb->get_row($wpdb->prepare("SELECT fbc, fbp FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
    idlm_integration_assert_same(array('', $fbp), array($row->fbc, $row->fbp));
    IDLM_DB::upsert_inbound_lead(IDLM_Webhook_Intake::apply_field_mappings(array('first_name'=>'Synthetic Cookie', 'phone'=>$phone, 'email'=>'cookie-' . $phone . '@example.test', 'fbc'=>$fbc, 'fbp'=>'fb.1.1762560009999.111'), 'website'), 'website');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
    idlm_integration_assert_same(array($fbc, $fbp), array($row->fbc, $row->fbp), 'A repeat fills an empty cookie and keeps the first one.');
    } finally { update_option('idlm_field_mappings_website', $saved_map); }
    update_option('idlm_meta_capi_enabled', '1'); update_option('idlm_meta_pixel_id', '123456'); IDLM_Secret_Store::set('idlm_meta_access_token', 'synthetic-capi-token');
    try {
        [$result, $calls] = idlm_capi_native_capture(static fn() => IDLM_CAPI::fire_meta_capi($row, 'New'));
    } finally { update_option('idlm_meta_capi_enabled', '0'); }
    idlm_integration_assert_true($result['success'], $result['message']);
    $user = json_decode($calls[0]['args']['body'], true)['data'][0]['user_data'];
    idlm_integration_assert_same(array($fbc, $fbp), array($user['fbc'], $user['fbp']));
});
