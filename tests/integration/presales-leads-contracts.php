<?php
/** Pre-sales Leads view: rendered queue rows and drawer cues for the call, log and decide loop. Synthetic data only. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_presales_view_call(callable $handler, array $post): array {
    $_POST = $post + array('nonce' => wp_create_nonce('idlm_admin_nonce'));
    $_REQUEST = $_POST;
    ob_start();
    try { $handler(); }
    catch (IDLM_Integration_Ajax_Die $expected) {}
    $response = json_decode((string) ob_get_clean(), true);
    idlm_integration_assert_true(!empty($response['success']), 'Pre-sales read failed: ' . wp_json_encode($response));
    return $response['data'];
}

idlm_integration_test('pre-sales queue rows and drawer show the next step and stop offering outreach after a decision', static function(): void {
    global $wpdb;
    $original_user = get_current_user_id();
    $user = wp_insert_user(array('user_login' => 'idlm_view_presales', 'user_pass' => wp_generate_password(), 'role' => 'idlm_pre_sales'));
    idlm_integration_assert_true(!is_wp_error($user));
    $now = current_time('mysql');
    $base = array('phone' => '', 'source' => 'Website', 'assigned_rep_id' => $user, 'assigned_at' => $now, 'created_at' => $now, 'updated_at' => $now);
    $wpdb->insert($wpdb->prefix . 'idlm_leads', $base + array('first_name' => 'Synthetic open', 'email' => wp_generate_uuid4() . '@example.test', 'stage' => 'Contacted', 'call_status' => 'No Answer', 'budget' => '2 lakh'));
    $open = (int) $wpdb->insert_id;
    $wpdb->insert($wpdb->prefix . 'idlm_leads', $base + array('first_name' => 'Synthetic decided', 'email' => wp_generate_uuid4() . '@example.test', 'stage' => 'Qualified', 'call_status' => 'Connected - Interested', 'lead_quality' => 'Qualified', 'qualified_by_user_id' => $user, 'intake_owner_id' => $user, 'handoff_status' => 'awaiting_manager', 'project_name' => 'Synthetic residence', 'property_location' => 'Synthetic location', 'budget' => '8 lakh', 'possession_date' => '2027-01-01', 'property_type' => 'Apartment', 'project_timeline' => '3 months'));
    $decided = (int) $wpdb->insert_id;
    $die_filter = static function() { return static function() { throw new IDLM_Integration_Ajax_Die('complete'); }; };
    add_filter('wp_die_ajax_handler', $die_filter);
    wp_set_current_user($user);
    try {
        $queue = idlm_presales_view_call(array('IDLM_Admin_Collection_Reads', 'ajax_get_leads_table'), array('followup_filter' => 'action_required'))['html'];
        idlm_integration_assert_true(false !== strpos($queue, 'aria-label="Log call for Synthetic open'), 'Row outreach buttons must name their lead.');
        idlm_integration_assert_true(false === strpos($queue, 'Synthetic decided'), 'The Action required queue must not hold decided leads.');

        $history = idlm_presales_view_call(array('IDLM_Admin_Collection_Reads', 'ajax_get_leads_table'), array('followup_filter' => 'my_qualified_history'))['html'];
        idlm_integration_assert_true(false !== strpos($history, 'aria-label="View Synthetic decided'), 'Decided rows open the lead instead of offering outreach.');
        idlm_integration_assert_true(false === strpos($history, 'idlm-presales-row-touch'), 'Decided rows must not offer outreach the server rejects.');

        $drawer = idlm_presales_view_call(array('IDLM_Admin_Lead_Reads', 'ajax_get_lead_details'), array('lead_id' => $open))['html'];
        idlm_integration_assert_true(false !== strpos($drawer, 'data-kind="call"'), 'Open leads keep the outreach actions.');
        idlm_integration_assert_true(false !== strpos($drawer, 'Final closure unlocks after all six cycles are complete.'), 'A locked final closure must say why.');
        idlm_integration_assert_true(false !== strpos($drawer, 'Close as unreachable unlocks after six cycles and the final closure.'), 'A locked unreachable decision must say why.');
        idlm_integration_assert_true(false !== strpos($drawer, 'Needed to qualify'), 'Missing qualification details are marked beside their fields.');
        idlm_integration_assert_true(false !== strpos($drawer, 'Needs ₹4L or above to qualify'), 'A low recorded budget is marked beside the budget.');
        idlm_integration_assert_true(strpos($drawer, 'idlm-presales-actions') < strpos($drawer, 'idlm-presales-day-grid'), 'Outreach actions come before the cycle history.');
        idlm_integration_assert_true(false === strpos($drawer, 'name="deal_value"'), 'Pre-sales does not see deal values.');

        $drawer = idlm_presales_view_call(array('IDLM_Admin_Lead_Reads', 'ajax_get_lead_details'), array('lead_id' => $decided))['html'];
        idlm_integration_assert_true(false === strpos($drawer, 'idlm-log-presales-touch'), 'Decided leads must not offer outreach or early close.');
        idlm_integration_assert_true(false !== strpos($drawer, 'Decision recorded:'), 'Decided leads state the recorded decision.');
        idlm_integration_assert_true(false === strpos($drawer, 'Needed to qualify'), 'Decided leads carry no qualification prompts.');
    } finally {
        remove_filter('wp_die_ajax_handler', $die_filter);
        wp_set_current_user($original_user);
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}idlm_leads WHERE id IN (%d, %d)", $open, $decided));
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($user);
    }
});
