<?php
/** Permission and background delivery regressions. Builder: Shashi. */
require_once dirname(__DIR__) . '/includes/class-telemetry.php';

if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event($timestamp, $hook, $args = array(), $wp_error = false) {
        $GLOBALS['idlm_test_schedules'][] = compact('timestamp', 'hook', 'args');
        return empty($GLOBALS['idlm_test_queue_failure']);
    }
}
if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args) {
        $GLOBALS['idlm_test_http_calls'][] = array($url, $args);
        return array('response' => array('code' => $GLOBALS['idlm_test_http_code'] ?? 200), 'body' => '{}');
    }
    function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
    function wp_remote_retrieve_body($response) { return $response['body']; }
}

idlm_test('comment and upload mutations require current ownership even when all leads are visible', static function(): void {
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('id' => 1, 'assigned_rep_id' => 8))));
    $GLOBALS['wpdb']->seed('idlm_lead_activity_log', array(array('id' => 1, 'lead_id' => 1, 'user_id' => 7, 'activity_type' => 'comment', 'details' => 'Original')));
    assert_true(IDLM_Admin::current_user_can_view_lead(1));
    assert_false(IDLM_Admin::current_user_can_access_lead(1));
    $_POST = array('lead_id' => 1, 'log_id' => 1, 'comment' => 'Tampered');
    foreach (array('ajax_save_activity_comment', 'ajax_edit_activity_comment', 'ajax_delete_activity_comment', 'ajax_upload_lead_file') as $handler) {
        expect_json_response(array('IDLM_Admin_Ajax', $handler), false);
    }
    assert_same('Original', $GLOBALS['wpdb']->rows('idlm_lead_activity_log')[0]['details']);
    idlm_test_login(9, array('administrator'), array('manage_options'));
    assert_true(IDLM_Admin::current_user_can_access_lead(1));
    assert_false(IDLM_Admin::current_user_can_access_lead(999));
});

idlm_test('drawer pre-sales policy rejects qualification and commercial changes but accepts unchanged fields', static function(): void {
    idlm_test_login(7, array('idlm_pre_sales'), array('idlm_access_leads'));
    $lead = (object) lead_fixture();
    foreach (array('lead_quality' => 'Qualified', 'deal_value' => '1000', 'discount_percentage' => '15', 'quote_shared' => '1', 'quote_value' => '1000', 'quote_file' => 'https://example.test/quote.pdf') as $field => $value) {
        assert_true(is_wp_error(IDLM_Lead_Access_Policy::validate_detail_fields(array($field => $value), $lead)));
    }
    assert_true(is_array(IDLM_Lead_Access_Policy::validate_detail_fields(array('lead_quality' => '', 'deal_value' => '0.00'), $lead)));
    idlm_test_login(8, array('sales_executive'), array('idlm_access_leads'));
    assert_true(is_wp_error(IDLM_Lead_Access_Policy::validate_detail_fields(array('discount_percentage' => '101'), $lead)));
    assert_true(is_wp_error(IDLM_Lead_Access_Policy::validate_detail_fields(array('deal_value' => '-5'), $lead)));
});

idlm_test('Interakt backfill queues id-only jobs and deduplicates HTTP submissions and worker replay', static function(): void {
    $GLOBALS['idlm_test_http_calls'] = array();
    $GLOBALS['idlm_test_http_code'] = 200;
    $GLOBALS['idlm_test_queue_failure'] = false;
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 7))));
    update_option('idlm_interakt_enabled', '1');
    update_option('idlm_interakt_api_key', 'test-secret');
    update_option('idlm_interakt_mappings', array(array('event' => 'qualified')));
    assert_same('queued', IDLM_Interakt_Batch::enqueue(1, 'qualified', 'request_123'));
    assert_same('existing', IDLM_Interakt_Batch::enqueue(1, 'qualified', 'request_123'));
    assert_same(0, count($GLOBALS['idlm_test_http_calls']));
    assert_same(1, count($GLOBALS['idlm_test_schedules']));
    $key = $GLOBALS['idlm_test_schedules'][0]['args'][0];
    assert_false(false !== strpos(json_encode($GLOBALS['idlm_test_schedules']), 'test-secret'));
    IDLM_Interakt_Batch::process($key);
    IDLM_Interakt_Batch::process($key);
    assert_same(1, count($GLOBALS['idlm_test_http_calls']));
    assert_same('sent', get_option($key)['status']);
});

idlm_test('Interakt backfill retries are bounded and removed leads never send', static function(): void {
    $GLOBALS['idlm_test_http_calls'] = array();
    $GLOBALS['idlm_test_http_code'] = 500;
    $GLOBALS['idlm_test_queue_failure'] = false;
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture()));
    update_option('idlm_interakt_enabled', '1');
    update_option('idlm_interakt_api_key', 'test-secret');
    update_option('idlm_interakt_mappings', array(array('event' => 'qualified')));
    IDLM_Interakt_Batch::enqueue(1, 'qualified', 'retry_123');
    $key = $GLOBALS['idlm_test_schedules'][0]['args'][0];
    for ($i = 0; $i < 4; $i++) { IDLM_Interakt_Batch::process($key); }
    assert_same(3, count($GLOBALS['idlm_test_http_calls']));
    assert_same('failed', get_option($key)['status']);
    IDLM_Interakt_Batch::enqueue(1, 'qualified', 'deleted_123');
    $key = end($GLOBALS['idlm_test_schedules'])['args'][0];
    $GLOBALS['wpdb']->seed('idlm_leads', array());
    IDLM_Interakt_Batch::process($key);
    assert_same('cancelled', get_option($key)['status']);
    assert_same(3, count($GLOBALS['idlm_test_http_calls']));
});

idlm_test('Interakt batch endpoint rejects oversized and unconfigured requests before sending', static function(): void {
    idlm_test_login(9, array('administrator'), array('manage_options'));
    $_POST = array('lead_ids' => range(1, 51), 'event_name' => 'bad', 'request_id' => 'request_123');
    expect_json_response(array('IDLM_Admin_Ajax', 'ajax_sync_mapped_past_batch'), false);
    $_POST['lead_ids'] = array(1);
    expect_json_response(array('IDLM_Admin_Ajax', 'ajax_sync_mapped_past_batch'), false);
});

idlm_test('inbound contact conflicts and explicit country-code collisions require review', static function(): void {
    foreach (array(
        array('email' => 'different@example.test', 'phone' => '9876543210'),
        array('email' => 'same@example.test', 'phone' => '+449876543210'),
        array('email' => 'same@example.test', 'phone' => '9123456789'),
    ) as $incoming) {
        idlm_test_reset_environment();
        $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('id' => 1, 'email' => 'same@example.test', 'phone' => '+919876543210'))));
        $result = IDLM_DB::upsert_inbound_lead(array_merge(array('first_name' => 'Separate inquiry'), $incoming), 'website');
        assert_true($result['created']);
        assert_true($result['requires_duplicate_review']);
        assert_false($result['matched']);
    }
});

idlm_test('dashboard keeps pipeline and Won reporting separate without exposing implementation terminology', static function(): void {
    $dashboard = (string) file_get_contents(dirname(__DIR__) . '/includes/class-dashboard.php');
    $template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/dashboard.php');
    $script = (string) file_get_contents(dirname(__DIR__) . '/assets/js/dashboard.js');
    assert_contains("COALESCE(stage, '') NOT IN ('Won', 'Lost') \$rep_clause", $dashboard);
    assert_contains("'is_applicable' => 'this_month' === \$period", $dashboard);
    assert_contains('id="idlm-pipeline-title">Open pipeline</h3>', $template);
    assert_contains('id="idlm-attention-title">Act next</h3>', $template);
    assert_contains('id="idlm-sales-trend-chart"', $template);
    assert_contains('Won Deals &amp; Revenue Trend', $template);
    assert_contains("'open_pipeline_health' => array(", $dashboard);
    assert_contains("'estimated_pipeline_covered_leads'", $dashboard);
    assert_contains("'cohort_won_current'", $dashboard);
    assert_contains("stage = 'Won' AND created_at BETWEEN", $dashboard);
    assert_false(false !== strpos($template, 'Current Open-Stage Distribution'));
    assert_false(false !== strpos($template, 'Open Pipeline vs Won Revenue'));
    assert_contains('Duplicate records created in period', $script);
});

idlm_test('dashboard retains visible-scope data-quality coverage data without rendering removed guardrails', static function(): void {
    $dashboard = (string) file_get_contents(dirname(__DIR__) . '/includes/class-dashboard.php');
    $template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/dashboard.php');
    assert_contains("'data_quality' => array(", $dashboard);
    assert_contains('response_timed_leads', $dashboard);
    assert_false(false !== strpos($template, 'Data-quality guardrails'), 'Removed data-quality guardrails must not return to the dashboard UI.');
    assert_false(false !== strpos($template, 'First-contact timing coverage'), 'Removed data-quality guardrail copy must not return to the dashboard UI.');
});
