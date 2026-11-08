<?php
/** Direct Meta lead sync review L-S1 and L-S2. Synthetic leads; Graph is intercepted, never called. Builder: Shashi. */

function idlm_leadsync_graph(string $id, callable $answer): array {
    $calls = array();
    $filter = static function($pre, $args, $url) use ($id, $answer, &$calls) {
        if (strpos($url, 'https://graph.facebook.com/') !== 0 || strpos($url, '/' . $id . '?') === false) { return $pre; }
        parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query); $calls[] = $query['fields'] ?? '';
        [$code, $body] = $answer($query['fields'] ?? '');
        return array('response'=>array('code'=>$code), 'headers'=>array(), 'body'=>$body);
    };
    add_filter('pre_http_request', $filter, PHP_INT_MAX, 3);
    try { $result = IDLM_Lead_Connector::process(idlm_connector_native_key('meta', $id)); } finally { remove_filter('pre_http_request', $filter, PHP_INT_MAX); }
    return array($result, $calls);
}

idlm_integration_test('LEADSYNC L-S1: rate-limit and token errors make one request, not three', static function(): void {
    foreach (array(4, 17, 32, 613, 190) as $index => $code) {
        idlm_connector_native_setup(); $id = '9100000000000000' . (10 + $index);
        idlm_integration_assert_same(true, idlm_connector_native_enqueue_meta($id));
        [$result, $calls] = idlm_leadsync_graph($id, static fn() => array(400, '{"error":{"code":' . $code . ',"message":"Synthetic"}}'));
        idlm_integration_assert_same(false, $result);
        idlm_integration_assert_same(array(IDLM_Lead_Connector::META_FULL_FIELDS), $calls, "Code $code must not trigger the field fallback.");
        $job = json_decode(IDLM_Secret_Store::get(idlm_connector_native_key('meta', $id), '', true), true);
        idlm_integration_assert_same(array('queued', 1), array($job['state'], $job['attempts']), 'A transient error keeps the timed retry.');
    }
});

idlm_integration_test('LEADSYNC L-S1: an unsupported field (100) or unknown error still falls back to fewer fields', static function(): void {
    foreach (array('{"error":{"code":100,"message":"Unsupported field"}}', '{"error":{"message":"no code"}}', 'not json') as $index => $error) {
        idlm_connector_native_setup(); $id = '9100000000000000' . (20 + $index); $phone = '91981112' . (2000 + $index);
        idlm_integration_assert_same(true, idlm_connector_native_enqueue_meta($id));
        $record = idlm_connector_native_record($id, $phone);
        [$result, $calls] = idlm_leadsync_graph($id, static fn($fields) => IDLM_Lead_Connector::META_BASE_FIELDS === $fields ? array(200, wp_json_encode($record)) : array(400, $error));
        idlm_integration_assert_same(true, $result);
        idlm_integration_assert_same(array(IDLM_Lead_Connector::META_FULL_FIELDS, IDLM_Lead_Connector::META_ATTRIBUTION_FIELDS, IDLM_Lead_Connector::META_BASE_FIELDS), $calls);
    }
    idlm_integration_assert_same(false, IDLM_Lead_Connector::field_fallback(array('id'=>'1')));
    idlm_integration_assert_true(IDLM_Lead_Connector::field_fallback(new WP_Error('x', 'hosted relay error')));
});

idlm_integration_test('LEADSYNC L-S2: a lead with no mapped contact waits for an admin at once and imports after the mapping is fixed', static function(): void {
    idlm_connector_native_setup(); $id = '910000000000000030'; $key = idlm_connector_native_key('meta', $id);
    idlm_integration_assert_same(true, idlm_connector_native_enqueue_meta($id));
    $record = array('id'=>$id, 'form_id'=>'222', 'created_time'=>'2026-10-17T12:00:00+0000', 'field_data'=>array(array('name'=>'full_name', 'values'=>array('Synthetic Mapping')), array('name'=>'mobile', 'values'=>array('919811113030'))));
    [$result, $calls] = idlm_connector_native_http(array($id=>$record), static fn() => IDLM_Lead_Connector::process($key));
    idlm_integration_assert_same(false, $result);
    $job = json_decode(IDLM_Secret_Store::get($key, '', true), true);
    idlm_integration_assert_same(array('failed', 1), array($job['state'], $job['attempts']), 'No timed retries for a mapping error.');
    idlm_integration_assert_same('mapping_failed', get_option('idlm_connector_last_status')['state']);
    $config = IDLM_Lead_Connector::config(); $config['mappings']['phone'] = 'mobile'; update_option('idlm_meta_lead_connector', $config, false);
    IDLM_Lead_Connector::retry_failed();
    [$result] = idlm_connector_native_http(array($id=>$record), static fn() => IDLM_Lead_Connector::process($key));
    idlm_integration_assert_same(true, $result, 'Retry failed imports recovers the lead after the mapping is fixed.');
    idlm_integration_assert_true((bool) idlm_connector_native_find('919811113030'));
});
