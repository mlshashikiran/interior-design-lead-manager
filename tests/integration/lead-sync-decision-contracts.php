<?php
/** Direct Meta lead sync decisions L-D1 to L-D6. Synthetic leads; Graph is intercepted, never called. Builder: Shashi. */

function idlm_leadsync_import(string $id, array $record, ?callable $answer = null): array {
    idlm_integration_assert_same(true, idlm_connector_native_enqueue_meta($id));
    $key = idlm_connector_native_key('meta', $id);
    if (null === $answer) { $answer = static fn() => array(200, wp_json_encode($record)); }
    $calls = array();
    $filter = static function($pre, $args, $url) use ($id, $answer, &$calls) {
        if (strpos($url, 'https://graph.facebook.com/') !== 0) { return $pre; }
        $calls[] = $url;
        if (strpos($url, '/' . $id . '?') !== false) { [$c, $b] = $answer($url); return array('response'=>array('code'=>$c), 'headers'=>array(), 'body'=>$b); }
        return array('response'=>array('code'=>200), 'headers'=>array(), 'body'=>'{"events_received":1}');
    };
    add_filter('pre_http_request', $filter, PHP_INT_MAX, 3);
    try { $result = IDLM_Lead_Connector::process($key); } finally { remove_filter('pre_http_request', $filter, PHP_INT_MAX); }
    return array($result, $calls, $key);
}

idlm_integration_test('LEADSYNC L-D1: Meta created_time becomes the touchpoint submission time, not a Won close', static function(): void {
    global $wpdb; idlm_connector_native_setup(); delete_option(IDLM_Lead_Connector::AUTH_PAUSED);
    $id = '920000000000000001'; $phone = '919811114001';
    $record = idlm_connector_native_record($id, $phone); $record['created_time'] = gmdate('Y-m-d\TH:i:s+0000', time() - 3 * HOUR_IN_SECONDS);
    [$result] = idlm_leadsync_import($id, $record);
    idlm_integration_assert_same(true, $result);
    $lead = idlm_connector_native_find($phone);
    idlm_integration_assert_same(null, $lead->conversion_time, 'A New lead has no Won close time.');
    $submitted = $wpdb->get_var($wpdb->prepare("SELECT submitted_at FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id = %d", $lead->id));
    idlm_integration_assert_same(wp_date('Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS), $submitted);
    update_option('idlm_meta_capi_enabled', '1'); update_option('idlm_meta_pixel_id', '123456'); IDLM_Secret_Store::set('idlm_meta_access_token', 'synthetic-capi-token');
    try { [$sent, $calls] = idlm_capi_native_capture(static fn() => IDLM_CAPI::fire_meta_capi($lead, 'New')); } finally { update_option('idlm_meta_capi_enabled', '0'); }
    idlm_integration_assert_true($sent['success'], $sent['message']);
    idlm_integration_assert_same(time() - 3 * HOUR_IN_SECONDS, json_decode($calls[0]['args']['body'], true)['data'][0]['event_time'], 'CAPI New uses the submission time.');
});

idlm_integration_test('LEADSYNC L-D2: a token error pauses Meta imports and catch-up, keeps leads 30 days, and a new token resumes', static function(): void {
    idlm_connector_native_setup(); delete_option(IDLM_Lead_Connector::AUTH_PAUSED);
    $id = '920000000000000002'; $phone = '919811114002'; $record = idlm_connector_native_record($id, $phone);
    [$result, $calls, $key] = idlm_leadsync_import($id, $record, static fn() => array(400, '{"error":{"code":190,"message":"Session expired"}}'));
    idlm_integration_assert_same(false, $result); idlm_integration_assert_same(1, count($calls), 'No field fallback on a token error.');
    idlm_integration_assert_true(IDLM_Lead_Connector::paused_at() > 0);
    idlm_integration_assert_same('auth_failed', get_option('idlm_connector_last_status')['state']);
    $job = json_decode(IDLM_Secret_Store::get($key, '', true), true); $job['next_at'] = time() - 1; IDLM_Secret_Store::set($key, wp_json_encode($job));
    $http = 0; $count = static function($pre, $args, $url) use (&$http) { if (strpos($url, 'graph.facebook.com') !== false) { $http++; } return $pre; };
    add_filter('pre_http_request', $count, 1, 3);
    try { idlm_integration_assert_same(false, IDLM_Lead_Connector::process($key)); } finally { remove_filter('pre_http_request', $count, 1); }
    idlm_integration_assert_same(0, $http, 'A paused sync makes no Graph request.');
    idlm_integration_assert_same(0, json_decode(IDLM_Secret_Store::get($key, '', true), true)['attempts'], 'Paused jobs spend no attempts.');
    ob_start(); IDLM_Lead_Connector::auth_notice(); $notice = ob_get_clean();
    idlm_integration_assert_true(strpos($notice, 'Meta lead sync is paused.') !== false && strpos($notice, '30 days') !== false);
    IDLM_Secret_Store::set('idlm_meta_lead_access_token', 'synthetic-replaced-token');
    idlm_integration_assert_same(0, IDLM_Lead_Connector::paused_at(), 'Saving a new token resumes the sync.');
    [$ok] = idlm_connector_native_http(array($id=>$record), static fn() => IDLM_Lead_Connector::process($key));
    idlm_integration_assert_same(true, $ok);
    // A queued lead older than 7 but younger than 30 days still imports.
    $old_id = '920000000000000003'; idlm_connector_native_enqueue_meta($old_id); $old_key = idlm_connector_native_key('meta', $old_id);
    $old = json_decode(IDLM_Secret_Store::get($old_key, '', true), true); $old['created_at'] = time() - 20 * DAY_IN_SECONDS; IDLM_Secret_Store::set($old_key, wp_json_encode($old));
    [$ok] = idlm_connector_native_http(array($old_id=>idlm_connector_native_record($old_id, '919811114003')), static fn() => IDLM_Lead_Connector::process($old_key));
    idlm_integration_assert_same(true, $ok, 'Within the 30-day window.');
    idlm_integration_assert_same('imported', get_option(IDLM_Lead_Connector::RECEIPT . substr($old_key, strlen(IDLM_Lead_Connector::JOB)))['outcome']);
});

idlm_integration_test('LEADSYNC L-D3: organic Instant Form leads are labelled Meta (organic) and route like Meta Ads', static function(): void {
    idlm_connector_native_setup(); delete_option(IDLM_Lead_Connector::AUTH_PAUSED);
    $record = idlm_connector_native_record('920000000000000004', '919811114004'); $record['is_organic'] = true;
    [$result] = idlm_leadsync_import('920000000000000004', $record);
    idlm_integration_assert_same(true, $result);
    idlm_integration_assert_same('Meta (organic)', idlm_connector_native_find('919811114004')->source);
    $paid = idlm_connector_native_record('920000000000000005', '919811114005'); $paid['is_organic'] = false;
    idlm_leadsync_import('920000000000000005', $paid);
    idlm_integration_assert_same('Meta Ads', idlm_connector_native_find('919811114005')->source);
    $routing = file_get_contents(dirname(__DIR__, 2) . '/includes/class-integrations.php');
    idlm_integration_assert_true(strpos($routing, "IDLM_Lead_Connector::ORGANIC_SOURCE === (string) \$source ? 'Meta Ads'") !== false);
    idlm_integration_assert_true(!in_array('Meta (organic)', IDLM_Contact_Identity::AD_SOURCES, true), 'Organic leads are not ad leads.');
});

idlm_integration_test('LEADSYNC L-D4: only the fields the CRM uses are requested and stored', static function(): void {
    foreach (array('home_listing', 'vehicle', 'retailer_item_id', 'post_submission_check_result', ',post') as $field) {
        idlm_integration_assert_true(false === strpos(IDLM_Lead_Connector::META_FULL_FIELDS, $field), $field);
    }
    idlm_integration_assert_true(false !== strpos(IDLM_Lead_Connector::META_FULL_FIELDS, 'custom_disclaimer_responses'), 'Consent answers are kept.');
    $snapshot = IDLM_Lead_Connector::meta_lead_snapshot(array('id'=>'1', 'vehicle'=>array('x'), 'home_listing'=>array('y'), 'field_data'=>array()));
    idlm_integration_assert_same(array('id', 'field_data'), array_keys($snapshot));
});

idlm_integration_test('LEADSYNC L-D5: the summary counts waiting and failed imports, records the last import, and retry resets failures', static function(): void {
    global $wpdb; idlm_connector_native_setup(); delete_option(IDLM_Lead_Connector::AUTH_PAUSED);
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like(IDLM_Lead_Connector::JOB) . '%'));
    delete_option(IDLM_Lead_Connector::LAST_IMPORT);
    idlm_connector_native_enqueue_meta('920000000000000006'); idlm_connector_native_enqueue_meta('920000000000000007');
    $key = idlm_connector_native_key('meta', '920000000000000007'); $job = json_decode(IDLM_Secret_Store::get($key, '', true), true); $job['state'] = 'failed'; IDLM_Secret_Store::set($key, wp_json_encode($job));
    $summary = IDLM_Lead_Connector::summary();
    idlm_integration_assert_same(array(1, 1, 0, 0), array($summary['waiting'], $summary['failed'], $summary['last_import'], $summary['paused_at']));
    idlm_integration_assert_same(1, IDLM_Lead_Connector::retry_failed());
    idlm_integration_assert_same(array(2, 0), array(IDLM_Lead_Connector::summary()['waiting'], IDLM_Lead_Connector::summary()['failed']));
    [$ok] = idlm_connector_native_http(array('920000000000000006'=>idlm_connector_native_record('920000000000000006', '919811114006')), static fn() => IDLM_Lead_Connector::process(idlm_connector_native_key('meta', '920000000000000006')));
    idlm_integration_assert_same(true, $ok);
    idlm_integration_assert_true(abs(time() - IDLM_Lead_Connector::summary()['last_import']) < 5);
    $source = file_get_contents(dirname(__DIR__, 2) . '/includes/class-lead-connector.php');
    $handler = substr($source, strpos($source, 'function ajax_retry'), 400);
    idlm_integration_assert_true(strpos($handler, "current_user_can('manage_options')") < strpos($handler, 'check_ajax_referer') && strpos($handler, 'check_ajax_referer') < strpos($handler, 'retry_failed'));
});

idlm_integration_test('LEADSYNC L-D6: receipts are dated, kept 35 days, then pruned in daily batches', static function(): void {
    global $wpdb;
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like(IDLM_Lead_Connector::RECEIPT) . '%'));
    delete_option('idlm_connector_receipt_prune');
    $old = IDLM_Lead_Connector::RECEIPT . str_repeat('a', 64); $fresh = IDLM_Lead_Connector::RECEIPT . str_repeat('b', 64); $legacy = IDLM_Lead_Connector::RECEIPT . str_repeat('c', 64);
    update_option($old, array('fingerprint'=>'x', 'outcome'=>'imported', 'at'=>time() - 36 * DAY_IN_SECONDS), false);
    update_option($fresh, array('fingerprint'=>'y', 'outcome'=>'imported', 'at'=>time() - 34 * DAY_IN_SECONDS), false);
    update_option($legacy, array('fingerprint'=>'z', 'outcome'=>'imported'), false);
    idlm_integration_assert_same(1, IDLM_Lead_Connector::prune_receipts());
    idlm_integration_assert_same(false, get_option($old)); idlm_integration_assert_true(is_array(get_option($fresh)));
    idlm_integration_assert_true(abs(time() - get_option($legacy)['at']) < 5, 'An undated legacy receipt is dated, not deleted.');
    idlm_integration_assert_same(0, IDLM_Lead_Connector::prune_receipts(), 'Runs at most once a day.');
});
