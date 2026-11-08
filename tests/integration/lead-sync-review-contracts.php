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
        // A token error (190) pauses the sync without spending an attempt (L-D2); throttling keeps the timed retry.
        idlm_integration_assert_same(array('queued', 190 === $code ? 0 : 1), array($job['state'], $job['attempts']), "Code $code retry state.");
        delete_option(IDLM_Lead_Connector::AUTH_PAUSED);
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

/** Route synthetic Graph answers by path; record every call. */
function idlm_leadsync_check(array $routes): array {
    $calls = array();
    $filter = static function($pre, $args, $url) use ($routes, &$calls) {
        if (strpos($url, 'https://graph.facebook.com/') !== 0) { return $pre; }
        $path = preg_replace('#^/v[0-9.]+/#', '', (string) wp_parse_url($url, PHP_URL_PATH));
        $method = $args['method'] ?? 'GET'; $calls[] = array($method, $path, $args);
        $answer = $routes[$method . ' ' . $path] ?? array(400, '{"error":{"code":100,"message":"unexpected"}}');
        return array('response'=>array('code'=>$answer[0]), 'headers'=>array(), 'body'=>$answer[1]);
    };
    add_filter('pre_http_request', $filter, PHP_INT_MAX, 3);
    try { $report = IDLM_Lead_Connector::check_connection(); } finally { remove_filter('pre_http_request', $filter, PHP_INT_MAX); }
    return array($report, $calls);
}

function idlm_leadsync_check_setup(): void {
    idlm_connector_native_setup();
    update_option('idlm_meta_lead_connector', array('enabled'=>'0', 'mode'=>'manual', 'page_id'=>'111', 'app_id'=>'444', 'forms'=>array('222', '333')), false);
    IDLM_Secret_Store::set('idlm_meta_lead_access_token', 'synthetic-system-user-token');
}

function idlm_leadsync_routes(array $overrides = array()): array {
    $all = array_map(static fn($p) => array('permission'=>$p, 'status'=>'granted'), IDLM_Lead_Connector::TOKEN_PERMISSIONS);
    return array_merge(array(
        'GET me' => array(200, '{"id":"999","name":"Synthetic System User"}'),
        'GET me/permissions' => array(200, wp_json_encode(array('data'=>$all))),
        'GET 111/subscribed_apps' => array(200, '{"data":[{"id":"444","subscribed_fields":["feed"]}]}'),
        'GET 111' => array(200, '{"access_token":"synthetic-derived-page-token","id":"111"}'),
        'POST 111/subscribed_apps' => array(200, '{"success":true}'),
        'GET 222' => array(200, '{"id":"222","page":{"id":"111"}}'),
        'GET 333' => array(200, '{"id":"333","page":{"id":"111"}}'),
        'GET 222/leads' => array(200, '{"data":[]}'),
        'GET 333/leads' => array(200, '{"data":[]}'),
    ), $overrides);
}

idlm_integration_test('LEADSYNC check: a good system user token subscribes the Page once, keeps other fields and stores no Page token', static function(): void {
    idlm_leadsync_check_setup();
    [$report, $calls] = idlm_leadsync_check(idlm_leadsync_routes());
    idlm_integration_assert_true($report['ok'], implode(' | ', $report['lines']));
    idlm_integration_assert_same('Token accepted with all lead permissions.', $report['lines'][0]);
    idlm_integration_assert_same('Subscribed the Page’s new leads to this app.', $report['lines'][1]);
    $posts = array_values(array_filter($calls, static fn($c) => 'POST' === $c[0]));
    idlm_integration_assert_same(1, count($posts));
    idlm_integration_assert_same('Bearer synthetic-derived-page-token', $posts[0][2]['headers']['Authorization']);
    idlm_integration_assert_same(array('subscribed_fields'=>'leadgen,feed'), $posts[0][2]['body']);
    foreach ($calls as $call) { idlm_integration_assert_true(false === strpos($call[1], 'token'), 'No token in a URL.'); }
    foreach ($calls as $call) { if ('GET' === $call[0] && '111' !== $call[1]) { idlm_integration_assert_same('Bearer synthetic-system-user-token', $call[2]['headers']['Authorization']); } }
    global $wpdb;
    idlm_integration_assert_same('0', (string) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", '%synthetic-derived-page-token%')), 'The derived Page token is never stored.');
    idlm_integration_assert_same('synthetic-system-user-token', IDLM_Secret_Store::get('idlm_meta_lead_access_token', '', true));
    [$again, $calls] = idlm_leadsync_check(idlm_leadsync_routes(array('GET 111/subscribed_apps'=>array(200, '{"data":[{"id":"444","subscribed_fields":["leadgen"]}]}'))));
    idlm_integration_assert_true($again['ok']);
    idlm_integration_assert_same(array(), array_values(array_filter($calls, static fn($c) => 'POST' === $c[0])), 'An existing subscription is left alone.');
});

idlm_integration_test('LEADSYNC check: rejected tokens, missing permissions and unreadable forms are reported plainly', static function(): void {
    idlm_leadsync_check_setup();
    [$bad] = idlm_leadsync_check(idlm_leadsync_routes(array('GET me'=>array(400, '{"error":{"code":190}}'))));
    idlm_integration_assert_true(!($bad['ok'])); idlm_integration_assert_true(strpos($bad['lines'][0], 'did not accept the saved access token') !== false);
    [$partial] = idlm_leadsync_check(idlm_leadsync_routes(array('GET me/permissions'=>array(200, '{"data":[{"permission":"leads_retrieval","status":"granted"},{"permission":"pages_show_list","status":"declined"}]}'))));
    idlm_integration_assert_true(strpos($partial['lines'][0], 'missing: pages_manage_metadata, pages_read_engagement, pages_show_list, pages_manage_ads') !== false, $partial['lines'][0]);
    [$page] = idlm_leadsync_check(idlm_leadsync_routes(array('GET me/permissions'=>array(400, '{"error":{"code":100}}'))));
    idlm_integration_assert_true($page['ok']); idlm_integration_assert_true(strpos($page['lines'][0], 'Page token') !== false);
    [$forms] = idlm_leadsync_check(idlm_leadsync_routes(array('GET 333/leads'=>array(400, '{"error":{"code":200}}'))));
    idlm_integration_assert_true(!($forms['ok'])); idlm_integration_assert_true(strpos(end($forms['lines']), 'Leads of form 333 cannot be read') !== false);
    [$sub] = idlm_leadsync_check(idlm_leadsync_routes(array('POST 111/subscribed_apps'=>array(400, '{"error":{"code":200}}'))));
    idlm_integration_assert_true(!($sub['ok'])); idlm_integration_assert_true(strpos(end($sub['lines']), 'subscribing failed') !== false);
    update_option('idlm_meta_lead_connector', array('enabled'=>'0', 'page_id'=>'111'), false);
    [$unsaved, $calls] = idlm_leadsync_check(idlm_leadsync_routes());
    idlm_integration_assert_true(!($unsaved['ok'])); idlm_integration_assert_same(0, count($calls), 'Nothing is requested before the setup is saved.');
});
