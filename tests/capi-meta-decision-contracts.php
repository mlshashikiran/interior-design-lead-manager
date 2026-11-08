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
