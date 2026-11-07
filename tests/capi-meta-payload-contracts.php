<?php
/** CAPI review S3: the Meta CRM request shape sent today. Uses idlm_capi_meta_send() from capi-meta-log-contracts.php. Synthetic data only. Builder: Shashi. */

idlm_test('CAPI review S3: Meta CRM event keeps its Conversion Leads shape and credentials stay in the header', static function(): void {
    $sent = idlm_capi_meta_send();
    assert_true($sent['result']['success'], (string) $sent['result']['message']);
    assert_same(1, count($sent['calls']));
    [$url, $args] = $sent['calls'][0];
    assert_same('https://graph.facebook.com/' . IDLM_CAPI_Transport::GRAPH_VERSION . '/synthetic-pixel/events', $url);
    assert_same('Bearer synthetic-meta-token', $args['headers']['Authorization']);
    assert_same(0, $args['redirection']);
    assert_false(strpos($url . $args['body'], 'synthetic-meta-token') !== false, 'Token leaked into the URL or body.');
    $body = json_decode($args['body'], true);
    assert_same(array('data'), array_keys($body), 'No test_event_code or other top-level field is sent in production.');
    $event = $body['data'][0];
    assert_same('Lead', $event['event_name']);
    assert_same('system_generated', $event['action_source']);
    assert_same('idlm_lead_1_Lead', $event['event_id']);
    assert_true(is_int($event['event_time']) && abs(time() - $event['event_time']) < 60);
    assert_false(isset($event['event_source_url']), 'A CRM event has no browser URL.');
    assert_same('crm', $event['custom_data']['event_source']);
    assert_same('Interior Design Lead Manager', $event['custom_data']['lead_event_source']);
    assert_same('123456789012345', $event['user_data']['lead_id']);
    foreach (array('em', 'ph') as $key) {
        assert_true(is_array($event['user_data'][$key]) && 1 === count($event['user_data'][$key]), "$key must be an array of one hash.");
        assert_same(1, preg_match('/^[0-9a-f]{64}$/D', $event['user_data'][$key][0]), "$key must be a SHA-256 hash.");
    }
    assert_same(hash('sha256', 'asha@example.test'), $event['user_data']['em'][0]);
    foreach (array('fn', 'ln') as $key) { assert_same(1, preg_match('/^[0-9a-f]{64}$/D', $event['user_data'][$key])); }
    foreach (array('client_ip_address', 'client_user_agent', 'fbc', 'fbp') as $key) { assert_false(isset($event['user_data'][$key]), "$key is not available to a CRM worker."); }
});
