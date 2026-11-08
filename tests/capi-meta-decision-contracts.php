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

idlm_test('CAPI D5: event_time is when the stage changed, falling back to now outside Meta\'s window', static function(): void {
    $two_hours = time() - 2 * HOUR_IN_SECONDS;
    $new = idlm_capi_meta_event(array('created_at'=>gmdate('Y-m-d H:i:s', $two_hours)));
    assert_same($two_hours, $new['event_time'], 'New uses the lead creation time.');
    $stage_at = time() - 3 * HOUR_IN_SECONDS;
    $events = array(
        array('id'=>1, 'lead_id'=>1, 'event_type'=>'stage_change', 'to_value'=>'Won', 'occurred_at'=>gmdate('Y-m-d H:i:s', $stage_at - DAY_IN_SECONDS)),
        array('id'=>2, 'lead_id'=>1, 'event_type'=>'stage_change', 'to_value'=>'Won', 'occurred_at'=>gmdate('Y-m-d H:i:s', $stage_at)),
        array('id'=>3, 'lead_id'=>1, 'event_type'=>'stage_change', 'to_value'=>'Lost', 'occurred_at'=>gmdate('Y-m-d H:i:s', time() - 60)),
    );
    $sent = idlm_capi_meta_send(array('deal_value'=>5000), 'Won', '{"events_received":1}', $events);
    assert_same($stage_at, json_decode($sent['calls'][0][1]['body'], true)['data'][0]['event_time'], 'The latest change into the stage wins.');
    foreach (array(gmdate('Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS), gmdate('Y-m-d H:i:s', time() + HOUR_IN_SECONDS), 'not a date', null) as $created) {
        $event = idlm_capi_meta_event(array('created_at'=>$created));
        assert_true(abs(time() - $event['event_time']) < 60, 'Unknown, future or stale times fall back to now: ' . var_export($created, true));
    }
});

idlm_test('CAPI D5: a Qualified event without a stage change uses qualified_at', static function(): void {
    $at = time() - 5 * HOUR_IN_SECONDS;
    $event = idlm_capi_meta_event(array('stage'=>'Contacted', 'lead_quality'=>'Qualified', 'qualified_at'=>gmdate('Y-m-d H:i:s', $at)), 'Qualified');
    assert_same($at, $event['event_time']);
});

idlm_test('CAPI D8: Meta timeouts, 5xx and documented throttling codes are transient; other rejections are not', static function(): void {
    foreach (array(array(503, ''), array(500, '{}'), array(408, ''), array(400, '{"error":{"code":17}}'), array(400, '{"error":{"code":4}}'), array(403, '{"error":{"code":613}}'), array(400, '{"error":{"code":80004}}'), array(400, '{"error":{"code":100,"is_transient":true}}'), array(400, '{"error":{"code":2}}')) as $case) {
        assert_true(IDLM_CAPI_Transport::meta_transient($case[0], $case[1]), wp_json_encode($case));
    }
    foreach (array(array(400, '{"error":{"code":100}}'), array(400, '{"error":{"code":190}}'), array(400, 'not json'), array(404, ''), array(200, '{}')) as $case) {
        assert_false(IDLM_CAPI_Transport::meta_transient($case[0], $case[1]), wp_json_encode($case));
    }
});

function idlm_capi_d8_retries(): array {
    return array_values(array_filter($GLOBALS['idlm_test_schedules'] ?? array(), static fn($s) => IDLM_CAPI_Transport::META_RETRY_HOOK === ($s['hook'] ?? '')));
}

function idlm_capi_d8_fresh_root(int $log_id): void {
    $GLOBALS['wpdb']->update($GLOBALS['wpdb']->prefix . 'idlm_capi_logs', array('created_at'=>gmdate('Y-m-d H:i:s', time() - 60)), array('id'=>$log_id));
}

idlm_test('CAPI D8: a Meta 503 is retried automatically with the same event_id and event_time', static function(): void {
    $first = idlm_capi_meta_send(array(), 'New', '', array(), 503)['result'];
    assert_same('transient', $first['outcome']); assert_true($first['retryable']);
    assert_same('failed', IDLM_CAPI_Delivery::read(1, 'meta', 'Lead')['status']);
    $scheduled = idlm_capi_d8_retries();
    assert_same(1, count($scheduled));
    assert_same(array($first['log_id'], IDLM_Recovery_Guard::generation()), $scheduled[0]['args']);
    assert_true($scheduled[0]['timestamp'] >= time() + 290 && $scheduled[0]['timestamp'] <= time() + 310, 'First retry is 5 minutes out.');
    idlm_capi_d8_fresh_root($first['log_id']);
    $GLOBALS['idlm_test_http_code'] = 200; $GLOBALS['idlm_test_http_body'] = '{"events_received":1}';
    try { $retry = IDLM_CAPI::auto_retry_meta($first['log_id'], IDLM_Recovery_Guard::generation()); } finally { unset($GLOBALS['idlm_test_http_body']); }
    assert_true($retry['success'], (string) $retry['message']);
    assert_same(2, count($GLOBALS['idlm_test_http_calls']));
    $a = json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'], true)['data'][0];
    $b = json_decode($GLOBALS['idlm_test_http_calls'][1][1]['body'], true)['data'][0];
    assert_same($a['event_id'], $b['event_id']); assert_same($a['event_time'], $b['event_time']);
    assert_same('sent', IDLM_CAPI_Delivery::read(1, 'meta', 'Lead')['status']);
    IDLM_CAPI::auto_retry_meta($retry['log_id'], IDLM_Recovery_Guard::generation());
    IDLM_CAPI::auto_retry_meta($first['log_id'], IDLM_Recovery_Guard::generation());
    assert_same(2, count($GLOBALS['idlm_test_http_calls']), 'Accepted events are never replayed.');
});

idlm_test('CAPI D8: a 400 rate limit is retried at most 3 times, 30 minutes apart after the first', static function(): void {
    $first = idlm_capi_meta_send(array(), 'New', '{"error":{"code":17}}', array(), 400)['result'];
    assert_same('transient', $first['outcome'], 'A rate limit is not a confirmed rejection.');
    idlm_capi_d8_fresh_root($first['log_id']);
    $GLOBALS['idlm_test_http_body'] = '{"error":{"code":17}}';
    try {
        assert_false(IDLM_CAPI::auto_retry_meta($first['log_id'], IDLM_Recovery_Guard::generation() + 1), 'A stale generation is ignored.');
        assert_same(1, count($GLOBALS['idlm_test_http_calls']));
        $log = $first['log_id'];
        for ($i = 0; $i < 5; $i++) { $r = IDLM_CAPI::auto_retry_meta($log, IDLM_Recovery_Guard::generation()); if (!empty($r['log_id'])) { $log = $r['log_id']; } }
    } finally { unset($GLOBALS['idlm_test_http_body']); }
    assert_same(4, count($GLOBALS['idlm_test_http_calls']), 'One send plus at most three retries.');
    $later = idlm_capi_d8_retries();
    assert_true(count($later) >= 2 && $later[1]['timestamp'] >= time() + 1790, 'Later retries are 30 minutes apart.');
});

idlm_test('CAPI D8: no auto-retry outside 47 hours or after a real rejection', static function(): void {
    $old = idlm_capi_meta_send(array(), 'New', '', array(), 503)['result'];
    $GLOBALS['wpdb']->update($GLOBALS['wpdb']->prefix . 'idlm_capi_logs', array('created_at'=>gmdate('Y-m-d H:i:s', time() - 48 * HOUR_IN_SECONDS)), array('id'=>$old['log_id']));
    $late = IDLM_CAPI::auto_retry_meta($old['log_id'], IDLM_Recovery_Guard::generation());
    assert_false($late['attempted']); assert_same(1, count($GLOBALS['idlm_test_http_calls']));
    $rejected = idlm_capi_meta_send(array(), 'New', '{"error":{"code":100,"message":"Invalid parameter"}}', array(), 400)['result'];
    assert_same('failed', $rejected['outcome']);
    assert_same(0, count(idlm_capi_d8_retries()), 'A confirmed rejection is not retried automatically.');
    idlm_capi_d8_fresh_root($rejected['log_id']);
    assert_false(IDLM_CAPI::auto_retry_meta($rejected['log_id'], IDLM_Recovery_Guard::generation())['attempted']);
});

idlm_test('CAPI D2: only a first-ever activation seeds CRM stage names; existing mappings are never changed', static function(): void {
    idlm_test_reset_environment();
    assert_false(IDLM_CAPI::seed_fresh_install_defaults(false));
    assert_false(get_option('idlm_meta_event_mappings', false), 'An existing install keeps the legacy default.');
    assert_true(IDLM_CAPI::seed_fresh_install_defaults(true));
    assert_same(array('New'=>'Lead', 'Qualified'=>'Qualified', 'Quote Shared'=>'Quote Shared', 'Won'=>'Won'), get_option('idlm_meta_event_mappings'));
    update_option('idlm_meta_event_mappings', array('New'=>'Lead', 'Won'=>'Purchase'));
    IDLM_CAPI::seed_fresh_install_defaults(true);
    assert_same(array('New'=>'Lead', 'Won'=>'Purchase'), get_option('idlm_meta_event_mappings'), 'A saved mapping is never overwritten.');
    $source = file_get_contents(dirname(__DIR__) . '/interior-design-lead-manager.php');
    $fresh = strpos($source, '$fresh_install = false === get_option(\'idlm_plugin_activated_at\', false) && false === get_option(\'idlm_schema_version\', false);');
    assert_true(false !== $fresh && $fresh < strpos($source, '$result = IDLM_DB::init();'), 'Fresh install is decided before migrations write markers.');
});

idlm_test('CAPI D2: a Won stage sends the close value whatever its Meta event is called', static function(): void {
    idlm_test_reset_environment();
    update_option('idlm_meta_event_mappings', IDLM_CAPI::META_CRM_STAGE_EVENTS);
    $GLOBALS['idlm_test_http_calls'] = array(); $GLOBALS['idlm_test_http_code'] = 200; $GLOBALS['idlm_test_http_body'] = '{"events_received":1}';
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('stage'=>'Won', 'deal_value'=>250000, 'fb_lead_id'=>'123456789012345', 'num_rooms'=>2, 'source'=>'Meta Ads'))));
    foreach (array('idlm_meta_capi_enabled'=>'1', 'idlm_meta_pixel_id'=>'synthetic-pixel', 'idlm_meta_access_token'=>'synthetic-meta-token') as $key => $value) { update_option($key, $value); }
    try { $result = IDLM_CAPI::fire_meta_capi((object) $GLOBALS['wpdb']->rows('idlm_leads')[0], 'Won'); } finally { unset($GLOBALS['idlm_test_http_body']); }
    assert_true($result['success'], (string) $result['message']);
    $event = json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'], true)['data'][0];
    assert_same('Won', $event['event_name']);
    assert_same(250000.0, (float) $event['custom_data']['value']);
});

idlm_test('CAPI D14: value-based Meta audiences upload only real deal values', static function(): void {
    idlm_test_reset_environment();
    $GLOBALS['idlm_test_http_calls'] = array(); $GLOBALS['idlm_test_http_code'] = 200;
    update_option('idlm_meta_access_token', 'synthetic-meta-token');
    $rule = array('platform'=>'meta', 'audience_id'=>'synthetic-audience', 'is_value'=>'1');
    $leads = array((object) lead_fixture(array('id'=>1, 'deal_value'=>450000)), (object) lead_fixture(array('id'=>2, 'deal_value'=>0, 'email'=>'b@example.test')), (object) lead_fixture(array('id'=>3, 'deal_value'=>null, 'email'=>'c@example.test')));
    $out = IDLM_Recovery_Guard::run(static function() use ($leads, $rule) { return IDLM_Audiences::send_page($leads, $rule); });
    assert_same('sent', $out['status']);
    $body = json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'], true)['payload'];
    assert_same(array('EMAIL', 'PHONE', 'FN', 'LN', 'LOOKALIKE_VALUE'), $body['schema']);
    assert_same(1, count($body['data']), 'Leads without a deal value are left out, not given 100.');
    assert_same(450000, $body['data'][0][4]);
    $GLOBALS['idlm_test_http_calls'] = array();
    $none = IDLM_Recovery_Guard::run(static function() use ($leads, $rule) { return IDLM_Audiences::send_page(array($leads[1], $leads[2]), $rule); });
    assert_same('sent', $none['status']); assert_same(0, count($GLOBALS['idlm_test_http_calls']), 'An empty value page makes no request.');
    $plain = IDLM_Recovery_Guard::run(static function() use ($leads) { return IDLM_Audiences::send_page($leads, array('platform'=>'meta', 'audience_id'=>'synthetic-audience', 'is_value'=>'0')); });
    assert_same(3, count(json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'], true)['payload']['data']), 'A plain audience still uploads everyone.');
});

idlm_test('CAPI D13: the log screen tells rejected, retrying, uncertain and not-sent attempts apart', static function(): void {
    $row = static function(string $status, $delivery, string $response = '') { return (object) array('status'=>$status, 'payload'=>serialize(array('data'=>array(), '_idlm_delivery'=>$delivery)), 'response'=>$response); };
    $ok = IDLM_Admin_Logs_Renderer::capi_outcome($row('success', array('outcome'=>'success', 'attempted'=>true, 'retryable'=>false), '{"events_received":1,"fbtrace_id":"synthetic-trace"}'));
    assert_same(array('success', 'Succeeded', 'success', false), array($ok['key'], $ok['label'], $ok['badge'], $ok['retryable']));
    $rejected = IDLM_Admin_Logs_Renderer::capi_outcome($row('failed', array('outcome'=>'failed', 'attempted'=>true, 'retryable'=>false), '{"error":{"message":"Invalid parameter","error_user_msg":"The event_time is too old.","fbtrace_id":"synthetic-trace"}}'));
    assert_same(array('Rejected', 'failed', false), array($rejected['label'], $rejected['badge'], $rejected['retryable']));
    assert_same('The event_time is too old. (trace synthetic-trace)', $rejected['message']);
    $fixable = IDLM_Admin_Logs_Renderer::capi_outcome($row('failed', array('outcome'=>'failed', 'attempted'=>true, 'retryable'=>true), '{"error":{"message":"Invalid OAuth access token."}}'));
    assert_true($fixable['retryable']); assert_same('Invalid OAuth access token.', $fixable['message']);
    $transient = IDLM_Admin_Logs_Renderer::capi_outcome($row('failed', array('outcome'=>'transient', 'attempted'=>true, 'retryable'=>true), '{"error":{"code":17,"message":"User request limit reached"}}'));
    assert_same(array('Retrying', 'warning', true), array($transient['label'], $transient['badge'], $transient['retryable']));
    $uncertain = IDLM_Admin_Logs_Renderer::capi_outcome($row('failed', array('outcome'=>'uncertain', 'attempted'=>true, 'retryable'=>false), 'cURL error 28'));
    assert_same(array('Uncertain', false, 'cURL error 28'), array($uncertain['label'], $uncertain['retryable'], $uncertain['message']));
    $skipped = IDLM_Admin_Logs_Renderer::capi_outcome($row('failed', array('outcome'=>'failed', 'attempted'=>false, 'retryable'=>true), 'Missing integration destination or credentials.'));
    assert_same(array('Not sent', true), array($skipped['label'], $skipped['retryable']));
    $legacy = IDLM_Admin_Logs_Renderer::capi_outcome((object) array('status'=>'failed', 'payload'=>'{"data":[]}', 'response'=>''));
    assert_same(array('Rejected', true), array($legacy['label'], $legacy['retryable']));
    $template = file_get_contents(dirname(__DIR__) . '/templates/admin/logs/capi.php');
    assert_true(strpos($template, "<?php if (\$idlm_outcome['retryable']): ?>") !== false, 'Rows that cannot be retried have no checkbox.');
    assert_true(strpos($template, "esc_html(\$idlm_outcome['message'])") !== false);
    $renderer = file_get_contents(dirname(__DIR__) . '/includes/class-admin-logs-renderer.php');
    assert_true(strpos($renderer, 'unset($log->payload, $log->response)') !== false && strpos($renderer, "ORDER BY c.id DESC LIMIT 50") !== false, 'Payloads are read for at most 50 rows and not passed to the view.');
});
