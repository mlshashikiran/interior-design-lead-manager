<?php
/** Disposable database reset and drawer integration coverage. Builder: Shashi. */

idlm_integration_test('pre-sales drawer rejects forged fields and partial saves preserve commercial data', static function(): void {
    global $wpdb;
    $original_user = get_current_user_id();
    $user = wp_insert_user(array('user_login' => 'idlm_guard_presales', 'user_pass' => wp_generate_password(), 'role' => 'idlm_pre_sales'));
    idlm_integration_assert_true(!is_wp_error($user));
    $lead_id = IDLM_DB::insert_lead(array('first_name' => 'Original', 'phone' => '9999900055', 'assigned_rep_id' => $user, 'deal_value' => 750000, 'quote_value' => 500000));
    $die_filter = static function() { return static function() { throw new IDLM_Integration_Ajax_Die('complete'); }; };
    add_filter('wp_die_ajax_handler', $die_filter);
    wp_set_current_user($user);
    $call = static function($fields) {
        $_POST = array('nonce' => wp_create_nonce('idlm_admin_nonce'), 'form_data' => http_build_query($fields));
        $_REQUEST = $_POST;
        ob_start();
        try { IDLM_Admin_Ajax::ajax_save_lead_details(); }
        catch (IDLM_Integration_Ajax_Die $expected) {}
        return json_decode((string) ob_get_clean(), true);
    };
    try {
        $denied = $call(array('lead_id' => $lead_id, 'lead_quality' => 'Qualified'));
        idlm_integration_assert_same(false, $denied['success']);
        $denied = $call(array('lead_id' => $lead_id, 'deal_value' => 1));
        idlm_integration_assert_same(false, $denied['success']);
        $saved = $call(array('lead_id' => $lead_id, 'first_name' => 'Updated'));
        idlm_integration_assert_true(!empty($saved['success']), wp_json_encode($saved));
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
        idlm_integration_assert_same('Updated', $lead->first_name);
        idlm_integration_assert_same(750000.0, (float) $lead->deal_value);
        idlm_integration_assert_same(500000.0, (float) $lead->quote_value);
        idlm_integration_assert_same('', $lead->lead_quality);
        foreach (array('', home_url('/workspace/')) as $workspace_url) {
            $_POST = array('nonce' => wp_create_nonce('idlm_admin_nonce'), 'lead_id' => $lead_id, 'workspace_url' => $workspace_url);
            $_REQUEST = $_POST;
            ob_start();
            try { IDLM_Admin_Lead_Reads::ajax_get_lead_details(); }
            catch (IDLM_Integration_Ajax_Die $expected) {}
            $drawer = json_decode((string) ob_get_clean(), true);
            idlm_integration_assert_true(!empty($drawer['success']));
            $html = $drawer['data']['html'];
            idlm_integration_assert_true(1 === preg_match('/<input[^>]*disabled[^>]*name="deal_value"/', $html), 'Pre-sales deal values must render disabled.');
            idlm_integration_assert_true(1 === preg_match('/<button(?![^>]*disabled)[^>]*>\s*Upload Floor Plans\s*<\/button>/', $html), 'Pre-sales floor-plan uploads must remain available.');
            idlm_integration_assert_true(1 === preg_match('/<button[^>]*disabled[^>]*>\s*Upload Quote\s*<\/button>/', $html), 'Pre-sales quote uploads must render disabled.');
        }
    } finally {
        remove_filter('wp_die_ajax_handler', $die_filter);
        wp_set_current_user($original_user);
    }
});

idlm_integration_test('CRM reset rolls back failed deletes and clears runtime state without recycling lead IDs', static function(): void {
    global $wpdb;
    $lead_table = $wpdb->prefix . 'idlm_leads';
    $activity_table = $wpdb->prefix . 'idlm_lead_activity_log';
    $lead_id = IDLM_DB::insert_lead(array('first_name' => 'Reset test', 'phone' => '9999900099', 'source' => 'Reset Test'));
    idlm_integration_assert_true((bool) $lead_id);
    $count = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$lead_table}");
    $activities = (int) $wpdb->get_var("SELECT COUNT(id) FROM {$activity_table}");
    $max_id = (int) $wpdb->get_var("SELECT MAX(id) FROM {$lead_table}");
    $batch_key = 'idlm_interakt_batch_' . str_repeat('a', 64);
    update_option('idlm_audience_sync_queue', array($lead_id));
    update_option($batch_key, array('lead_id' => $lead_id, 'event' => 'test', 'status' => 'queued'), false);
    wp_schedule_single_event(time() + 600, 'idlm_async_interakt_batch_event', array($batch_key));
    wp_schedule_single_event(time() + 600, 'idlm_async_capi_upload_event', array('lead_id' => $lead_id, 'stage' => 'New'));
    $settings_before = get_option('idlm_interakt_mappings');
    $trigger = $wpdb->prefix . 'idlm_test_reset_failure';
    idlm_integration_assert_true(false !== $wpdb->query("CREATE TRIGGER {$trigger} BEFORE DELETE ON {$activity_table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Expected reset rollback test'"));
    $previous = $wpdb->suppress_errors(true);
    try {
        $result = IDLM_Lead_Deletion_Service::reset_all((string) $count);
        idlm_integration_assert_true(is_wp_error($result), 'Injected failure must abort reset.');
        idlm_integration_assert_same($count, (int) $wpdb->get_var("SELECT COUNT(id) FROM {$lead_table}"));
        idlm_integration_assert_same($activities, (int) $wpdb->get_var("SELECT COUNT(id) FROM {$activity_table}"));
        idlm_integration_assert_same(array($lead_id), get_option('idlm_audience_sync_queue'));
    } finally {
        $wpdb->query("DROP TRIGGER IF EXISTS {$trigger}");
        $wpdb->suppress_errors($previous);
    }
    $result = IDLM_Lead_Deletion_Service::reset_all((string) $count);
    idlm_integration_assert_true(!is_wp_error($result), is_wp_error($result) ? $result->get_error_message() : 'Reset failed.');
    foreach (IDLM_Lead_Deletion_Service::reset_manifest() as $suffixes) {
        foreach ($suffixes as $suffix) {
            idlm_integration_assert_same(0, (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $wpdb->prefix . $suffix), $suffix);
        }
    }
    idlm_integration_assert_same(false, get_option($batch_key));
    idlm_integration_assert_same(false, get_option('idlm_audience_sync_queue'));
    idlm_integration_assert_same($settings_before, get_option('idlm_interakt_mappings'));
    idlm_integration_assert_same(false, wp_next_scheduled('idlm_async_interakt_batch_event', array($batch_key)));
    idlm_integration_assert_same(false, wp_next_scheduled('idlm_async_capi_upload_event', array('lead_id' => $lead_id, 'stage' => 'New')));
    $next_id = IDLM_DB::insert_lead(array('first_name' => 'After reset', 'phone' => '9999900098', 'source' => 'Reset Test'));
    idlm_integration_assert_true($next_id > $max_id, 'Reset must not recycle deleted lead identities.');
});
