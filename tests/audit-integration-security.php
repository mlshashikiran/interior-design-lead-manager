<?php
/** Isolated 2026-10-06 audit invariants. Builder: Shashi. No WordPress bootstrap or real HTTP/DB. */
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/includes/class-backup.php';
require_once dirname(__DIR__) . '/includes/class-capi.php';
require_once dirname(__DIR__) . '/includes/class-telemetry.php';

$GLOBALS['audit_http_requests'] = array();
function wp_remote_post($url, $args) {
    $GLOBALS['audit_http_requests'][] = array('url' => $url, 'args' => $args);
    return array('response' => array('code' => 202), 'body' => '{}');
}
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }

final class IDLM_Audit_Restore_DB {
    public string $prefix = 'wp_';
    public string $options = 'wp_options';
    public array $queries = array();
    public bool $fail_insert = false;
    public function esc_like($value) { return addcslashes($value, '_%\\'); }
    public function prepare($query, ...$args) { return (new IDLM_Test_WPDB())->prepare($query, ...$args); }
    public function get_var($query) {
        if (preg_match("/SHOW TABLES LIKE '([^']+)'/", $query, $match)) { return $match[1]; }
        if (str_contains($query, 'SELECT ENGINE')) { return 'InnoDB'; }
        return null;
    }
    public function query($query) { $this->queries[] = $query; return 1; }
    public function insert($table, $row) { return !$this->fail_insert; }
    public function get_col($query) { return array(); }
    public function replace($table, $row) { return 1; }
}

$failed = 0;
$passed = 0;
function audit_invariant($id, $description, $assertion, $evidence = '') {
    global $failed, $passed;
    if ($assertion) { $passed++; } else { $failed++; }
    echo ($assertion ? 'PASS' : 'FAIL') . " {$id}: {$description}" . ($evidence ? " | {$evidence}" : '') . PHP_EOL;
}

idlm_test_reset_environment();
$guard = new ReflectionMethod(IDLM_MCP_Core::class, 'prepare_custom_query');
$allowed = array('wp_idlm_leads');
$direct = $guard->invoke(null, 'SELECT option_value FROM wp_options', $allowed);
audit_invariant('INT-S01', 'Direct forbidden table is rejected', isset($direct['error']));
$bypass = $guard->invoke(null, 'SELECT o.option_value FROM wp_idlm_leads l JOIN wp_idlm_leads x ON x.id=l.id, wp_options o', $allowed);
audit_invariant('INT-S02', 'Forbidden comma table following JOIN is rejected', isset($bypass['error']), json_encode($bypass));
$results = IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT user_pass, user_activation_key FROM wp_users'));
audit_invariant('INT-S03', 'MCP denies WordPress authentication columns', isset($results['error']), $GLOBALS['wpdb']->last_get_results_query);
update_option('idlm_mcp_enabled', '0');
$denied = IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT id FROM wp_idlm_leads'));
audit_invariant('INT-S04', 'Emergency-disabled engine rejects queries', isset($denied['error']));

$restore = new ReflectionMethod(IDLM_Backup::class, 'restore_payload');
$GLOBALS['wpdb'] = new IDLM_Audit_Restore_DB();
$restored = $restore->invoke(null, array('tables' => array(), 'options' => array()));
$delete_count = count(array_filter($GLOBALS['wpdb']->queries, static fn($sql) => str_starts_with($sql, 'DELETE FROM')));
audit_invariant('INT-S05', 'Restore rejects missing table payload before destructive SQL', is_wp_error($restored) && 0 === $delete_count, "result=" . json_encode($restored) . "; DELETE count={$delete_count}");
$GLOBALS['wpdb'] = new IDLM_Audit_Restore_DB();
$GLOBALS['wpdb']->fail_insert = true;
$restored = $restore->invoke(null, array('tables' => array('idlm_leads' => array(array('id' => 1))), 'options' => array()));
audit_invariant('INT-S06', 'Restore insertion failure requests rollback', is_wp_error($restored) && in_array('ROLLBACK', $GLOBALS['wpdb']->queries, true));

$GLOBALS['wpdb'] = new IDLM_Test_WPDB();
idlm_test_reset_environment();
$GLOBALS['wpdb']->seed('idlm_leads', array(array('id' => 1, 'phone' => '9999900001')));
update_option('idlm_interakt_enabled', '0');
update_option('idlm_interakt_api_key', 'synthetic-current-key');
$GLOBALS['audit_http_requests'] = array();
IDLM_Interakt::process_async_track_event(array('event' => 'follow_up'), 'synthetic-revoked-key', 1);
audit_invariant('INT-S07', 'Queued Interakt worker stops after integration is disabled', 0 === count($GLOBALS['audit_http_requests']), 'Mock HTTP requests=' . count($GLOBALS['audit_http_requests']));
$GLOBALS['audit_http_requests'] = array();
IDLM_Interakt::process_async_track_event(array('event' => 'follow_up'), 'synthetic-revoked-key', 999);
audit_invariant('INT-S08', 'Queued Interakt worker stops for deleted lead', 0 === count($GLOBALS['audit_http_requests']));
$signature = 'sha256=' . hash_hmac('sha256', '{"synthetic":true}', 'synthetic-secret');
audit_invariant('INT-S09', 'WhatsApp HMAC rejects wrong secret', !IDLM_Interakt::verify_webhook_signature('{"synthetic":true}', $signature, 'wrong-secret'));
audit_invariant('INT-S10', 'Scoped secret is encrypted at rest', IDLM_Secret_Store::set('idlm_mcp_api_key', 'synthetic-only') && !str_contains(get_option('idlm_mcp_api_key'), 'synthetic-only') && 'synthetic-only' === IDLM_Secret_Store::get('idlm_mcp_api_key'));

idlm_test_reset_environment();
$GLOBALS['wpdb']->seed('idlm_leads', array(array('id' => 1, 'source' => 'Google Ads', 'stage' => 'Qualified', 'lead_quality' => 'Qualified')));
$GLOBALS['wpdb']->seed('idlm_capi_logs', array(array('id' => 1, 'lead_id' => 1, 'platform' => 'google', 'event_name' => 'CRM Qualified Lead', 'status' => 'failed', 'response' => 'synthetic failure')));
update_option('idlm_google_capi_enabled', '1');
update_option('idlm_google_measurement_id', 'G-SYNTHETIC');
update_option('idlm_google_api_secret', 'synthetic-secret');
$GLOBALS['audit_http_requests'] = array();
IDLM_CAPI::retry_capi_log(1);
audit_invariant('INT-S11', 'Default Google event retry submits an actual delivery', 1 === count($GLOBALS['audit_http_requests']), 'Mock HTTP requests=' . count($GLOBALS['audit_http_requests']));

idlm_test_reset_environment();
$GLOBALS['wpdb']->custom_get_var_result = 1;
update_option('idlm_meta_webhook_enabled', '1');
update_option('idlm_meta_webhook_app_secret', 'synthetic-secret');
$messages = array(
    array('id' => 'synthetic-message-1', 'from' => '919999900001', 'text' => array('body' => 'Synthetic first')),
    array('id' => 'synthetic-message-2', 'from' => '919999900001', 'text' => array('body' => 'Synthetic second')),
);
$body = wp_json_encode(array('object' => 'whatsapp_business_account', 'entry' => array(array('changes' => array(array('value' => array('messages' => $messages)))))));
$request = new WP_REST_Request(array('X-Hub-Signature-256' => 'sha256=' . hash_hmac('sha256', $body, 'synthetic-secret')), array(), array(), array(), $body);
$response = IDLM_Interakt::handle_meta_webhook_request($request);
$count = count($GLOBALS['wpdb']->rows('idlm_lead_activity_log'));
audit_invariant('INT-S12', 'Valid Meta batch persists every message before acknowledgment', 2 === $count && 200 === $response->get_status(), 'Activity count=' . $count);
$count_before_replay = $count;
IDLM_Interakt::handle_meta_webhook_request($request);
$count = count($GLOBALS['wpdb']->rows('idlm_lead_activity_log'));
audit_invariant('INT-S13', 'Meta replay does not append another copy of already handled message', $count_before_replay === $count, 'Activity count after replay=' . $count);

echo "Audit invariants: {$passed} passed, {$failed} failed. Failures document reviewed defects; production behavior was not changed." . PHP_EOL;
exit($failed ? 1 : 0);
