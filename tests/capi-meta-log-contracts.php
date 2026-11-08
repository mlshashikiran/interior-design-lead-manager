<?php
/** CAPI review S1: Meta delivery logs keep match-key names, never customer hashes. Synthetic data only; HTTP is captured, never sent. Builder: Shashi. */

/** One synthetic Meta-enabled lead; returns the provider request body and the stored log row. */
function idlm_capi_meta_send(array $lead_overrides = array(), string $stage = 'New', string $provider_body = '{"events_received":1,"fbtrace_id":"synthetic-trace"}', array $state_events = array(), int $code = 200): array {
    idlm_test_reset_environment();
    $GLOBALS['idlm_test_http_calls'] = array(); $GLOBALS['idlm_test_http_code'] = $code; $GLOBALS['idlm_test_http_body'] = $provider_body; $GLOBALS['idlm_test_schedules'] = array();
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array_merge(array('stage'=>$stage, 'source'=>'Meta Ads', 'fb_lead_id'=>'123456789012345', 'num_rooms'=>2), $lead_overrides))));
    if ($state_events) { $GLOBALS['wpdb']->seed('idlm_lead_state_events', $state_events); }
    foreach (array('idlm_meta_capi_enabled'=>'1', 'idlm_meta_pixel_id'=>'synthetic-pixel', 'idlm_meta_access_token'=>'synthetic-meta-token') as $key => $value) { update_option($key, $value); }
    try { $result = IDLM_CAPI::fire_meta_capi((object) $GLOBALS['wpdb']->rows('idlm_leads')[0], $stage); }
    finally { unset($GLOBALS['idlm_test_http_body']); }
    $calls = $GLOBALS['idlm_test_http_calls'];
    $logs = $GLOBALS['wpdb']->rows('idlm_capi_logs');
    return array('result'=>$result, 'calls'=>$calls, 'log'=>$logs ? end($logs) : null);
}

idlm_test('CAPI review S1: stored Meta logs keep match-key names but never the customer hashes', static function(): void {
    $sent = idlm_capi_meta_send();
    $event = json_decode($sent['calls'][0][1]['body'], true)['data'][0];
    $log = $sent['log'];
    assert_true(is_array($log), 'The attempt was not logged.');
    $stored = maybe_unserialize($log['payload']);
    $user = $stored['data'][0]['user_data'];
    foreach (array('em', 'ph', 'fn', 'ln') as $key) { assert_same('[HASHED]', $user[$key], "$key value was stored."); }
    assert_same('123456789012345', $user['lead_id'], 'The Lead Ads ID stays for diagnostics.');
    $raw = (string) $log['payload'] . (string) $log['response'];
    foreach (array($event['user_data']['em'][0], $event['user_data']['ph'][0], $event['user_data']['fn'], $event['user_data']['ln'], 'synthetic-meta-token') as $secret) {
        assert_false(strpos($raw, $secret) !== false, 'A customer hash or token reached the log.');
    }
    assert_same('Lead', $stored['_idlm_delivery']['event'], 'Retry identity is still stored.');
    assert_same('crm', $stored['data'][0]['custom_data']['event_source']);
});

idlm_test('CAPI review S1: masking leaves the provider request and WhatsApp identifiers untouched', static function(): void {
    $payload = array('data'=>array(array('user_data'=>array('em'=>array('a'), 'ph'=>array('b'), 'country'=>'c', 'external_id'=>array('d'), 'lead_id'=>'123456789012345', 'ctwa_clid'=>'synthetic-click', 'page_id'=>'111'))));
    $masked = IDLM_CAPI_Transport::mask_logged_identifiers($payload);
    assert_same(array('em'=>'[HASHED]', 'ph'=>'[HASHED]', 'country'=>'[HASHED]', 'external_id'=>'[HASHED]', 'lead_id'=>'123456789012345', 'ctwa_clid'=>'synthetic-click', 'page_id'=>'111'), $masked['data'][0]['user_data']);
    assert_same(array('a'), $payload['data'][0]['user_data']['em'], 'The caller copy is unchanged.');
    foreach (array(array(), array('events'=>array(array('params'=>array('em'=>'x')))), array('data'=>'x'), array('data'=>array('x')), 'text') as $other) {
        assert_same($other, IDLM_CAPI_Transport::mask_logged_identifiers($other));
    }
});

idlm_test('CAPI review S1: a failed Meta attempt is logged without customer hashes and stays retry-readable', static function(): void {
    $sent = idlm_capi_meta_send(array(), 'New', '{"error":{"message":"Invalid parameter","code":100,"fbtrace_id":"synthetic-trace"}}');
    assert_false($sent['result']['success']);
    $stored = maybe_unserialize($sent['log']['payload']);
    assert_same('[HASHED]', $stored['data'][0]['user_data']['em']);
    assert_same('New', $stored['_idlm_delivery']['stage']);
});
