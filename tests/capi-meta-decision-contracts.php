<?php
/** CAPI review decisions D1–D14. Synthetic data only; HTTP is captured, never sent. Uses idlm_capi_meta_send() from capi-meta-log-contracts.php. Builder: Shashi. */

function idlm_capi_meta_event(array $lead_overrides = array(), string $stage = 'New'): array {
    $sent = idlm_capi_meta_send($lead_overrides, $stage);
    assert_same(1, count($sent['calls']), (string) ($sent['result']['message'] ?? 'No request.'));
    return json_decode($sent['calls'][0][1]['body'], true)['data'][0];
}

idlm_test('CAPI D1: Meta phone hashes carry the country code whatever the stored format', static function(): void {
    foreach (array('9876543210', '09876543210', '+91 98765-43210', '0091 98765 43210', '919876543210') as $stored) {
        assert_same('919876543210', IDLM_CAPI_Transport::meta_phone($stored), $stored);
    }
    assert_same('14155550100', IDLM_CAPI_Transport::meta_phone('+1 (415) 555-0100'));
    assert_same('', IDLM_CAPI_Transport::meta_phone('12-34'));
    assert_same('', IDLM_CAPI_Transport::meta_phone(null));
    $event = idlm_capi_meta_event(array('phone'=>'098765 43210'));
    assert_same(array(hash('sha256', '919876543210')), $event['user_data']['ph']);
});

idlm_test('CAPI D7: Meta names are UTF-8 lowercased without punctuation', static function(): void {
    assert_same('dsouza', IDLM_CAPI_Transport::meta_name(" D'Souza. "));
    assert_same('élodie', IDLM_CAPI_Transport::meta_name('ÉLODIE'));
    assert_same('maryann', IDLM_CAPI_Transport::meta_name('Mary-Ann'));
    assert_same('mary ann', IDLM_CAPI_Transport::meta_name('  Mary   Ann '));
    assert_same('', IDLM_CAPI_Transport::meta_name('..'));
    $event = idlm_capi_meta_event(array('first_name'=>'ÉLODIE', 'last_name'=>"D'Souza", 'phone'=>''));
    assert_same(hash('sha256', 'élodie'), $event['user_data']['fn']);
    assert_same(hash('sha256', 'dsouza'), $event['user_data']['ln']);
    assert_false(isset($event['user_data']['ph']), 'An empty phone sends no hash.');
});

idlm_test('CAPI D1/D7: Meta audience rows use the same phone and name normalisation', static function(): void {
    idlm_test_reset_environment();
    $GLOBALS['idlm_test_http_calls'] = array(); $GLOBALS['idlm_test_http_code'] = 200;
    update_option('idlm_meta_access_token', 'synthetic-meta-token');
    $lead = (object) lead_fixture(array('phone'=>'9876543210', 'first_name'=>'ÉLODIE', 'last_name'=>"D'Souza"));
    $out = IDLM_Recovery_Guard::run(static function() use ($lead) { return IDLM_Audiences::send_page(array($lead), array('platform'=>'meta', 'audience_id'=>'synthetic-audience', 'is_value'=>'0')); });
    assert_same('sent', $out['status'] ?? null, wp_json_encode($out));
    $row = json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'], true)['payload']['data'][0];
    assert_same(array(hash('sha256', 'asha@example.test'), hash('sha256', '919876543210'), hash('sha256', 'élodie'), hash('sha256', 'dsouza')), $row);
});

idlm_test('CAPI D6: Meta events carry a stable hashed external_id and a country only when the phone proves it', static function(): void {
    $event = idlm_capi_meta_event();
    assert_same(array(IDLM_CAPI_Transport::meta_external_id(1)), $event['user_data']['external_id']);
    assert_same(1, preg_match('/^[0-9a-f]{64}$/D', $event['user_data']['external_id'][0]));
    assert_same(IDLM_CAPI_Transport::meta_external_id(1), IDLM_CAPI_Transport::meta_external_id('1'), 'Same lead, same ID.');
    assert_false(IDLM_CAPI_Transport::meta_external_id(1) === IDLM_CAPI_Transport::meta_external_id(2));
    assert_same(array(hash('sha256', 'in')), $event['user_data']['country']);
    $foreign = idlm_capi_meta_event(array('phone'=>'+1 415 555 0100'));
    assert_false(isset($foreign['user_data']['country']), 'A non-Indian phone sends no country.');
    foreach (array('fbc', 'fbp', 'client_ip_address', 'client_user_agent') as $key) { assert_false(isset($event['user_data'][$key])); }
});

idlm_test('CAPI D3: only Lead Ads leads carry the Conversion Leads CRM fields', static function(): void {
    $lead_ads = idlm_capi_meta_event();
    assert_same('crm', $lead_ads['custom_data']['event_source']);
    assert_same('Interior Design Lead Manager', $lead_ads['custom_data']['lead_event_source']);
    $website = idlm_capi_meta_event(array('fb_lead_id'=>'', 'source'=>'Website'));
    assert_same('system_generated', $website['action_source']);
    foreach (array('event_source', 'lead_event_source') as $key) { assert_false(isset($website['custom_data'][$key]), "$key sent for a non-Lead-Ads lead."); }
    assert_false(isset($website['user_data']['lead_id']));
    assert_same('New', $website['custom_data']['stage'], 'The event is still sent with its CRM context.');
    assert_true(isset($website['user_data']['em'], $website['user_data']['external_id']));
});
