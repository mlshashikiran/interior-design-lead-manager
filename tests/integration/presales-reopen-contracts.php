<?php
/** Reopen for qualification: who may reopen an Unqualified lead, and how the outreach round restarts. Synthetic data only. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_reopen_call(callable $handler, array $post): array {
    $_POST = $post + array('nonce' => wp_create_nonce('idlm_admin_nonce'));
    $_REQUEST = $_POST;
    ob_start();
    try { $handler(); }
    catch (IDLM_Integration_Ajax_Die $expected) {}
    return (array) json_decode((string) ob_get_clean(), true);
}

idlm_integration_test('pre-sales reopens its own closed lead with a reason, and outreach restarts at cycle 1', static function(): void {
    global $wpdb;
    $original_user = get_current_user_id();
    $users = array();
    foreach (array('presales' => 'idlm_pre_sales', 'rep' => 'editor', 'manager' => 'editor', 'admin' => 'administrator') as $key => $role) {
        $users[$key] = wp_insert_user(array('user_login' => 'idlm_reopen_' . $key, 'user_pass' => wp_generate_password(), 'role' => $role));
        idlm_integration_assert_true(!is_wp_error($users[$key]), 'Fixture user failed: ' . $key);
    }
    (new WP_User($users['rep']))->add_cap('idlm_access_leads');
    (new WP_User($users['manager']))->add_cap('idlm_access_leads');
    (new WP_User($users['manager']))->add_cap('idlm_assign_leads');
    $now = current_time('mysql');
    $closed = array('stage' => 'Lost', 'lead_quality' => 'Unqualified', 'lost_reason' => 'Client Not Interested', 'call_status' => 'Connected - Not Interested',
        'disqualification_reason' => 'not_interested', 'disqualification_method' => 'early_close', 'disqualified_at' => $now, 'disqualified_by_user_id' => $users['presales']);
    $insert = static function(array $values) use ($wpdb, $now, $users): int {
        $wpdb->insert($wpdb->prefix . 'idlm_leads', $values + array('first_name' => 'Synthetic reopen', 'phone' => '', 'email' => wp_generate_uuid4() . '@example.test', 'source' => 'Website',
            'assigned_rep_id' => $users['presales'], 'assigned_at' => $now, 'created_at' => $now, 'updated_at' => $now));
        return (int) $wpdb->insert_id;
    };
    $own = $insert($closed);
    $was_qualified = $insert(array('qualified_at' => $now, 'disqualification_reason' => 'administrator_exception', 'disqualified_by_user_id' => $users['admin']) + $closed);
    $duplicate = $insert(array('is_duplicate' => 1, 'disqualification_reason' => 'duplicate') + $closed);
    $rep_lead = $insert(array('assigned_rep_id' => $users['rep']) + $closed);
    $ids = array($own, $was_qualified, $duplicate, $rep_lead);
    // Six completed cycles and a final closure from before the reopen.
    $targets = IDLM_DB::get_presales_outreach_targets();
    $per_day = array_merge(array_fill(0, $targets['calls'], 'outbound_call'), array_fill(0, max(0, $targets['whatsapp']), 'whatsapp_outbound'));
    for ($day = 8; $day >= 2; $day--) {
        $when = wp_date('Y-m-d 10:00:00', time() - $day * DAY_IN_SECONDS, wp_timezone());
        foreach ($per_day as $type) {
            $wpdb->insert($wpdb->prefix . 'idlm_lead_activity_log', array('lead_id' => $own, 'user_id' => $users['presales'], 'activity_type' => $type, 'details' => 'Synthetic', 'created_at' => $when));
        }
    }
    $wpdb->insert($wpdb->prefix . 'idlm_lead_activity_log', array('lead_id' => $own, 'user_id' => $users['presales'], 'activity_type' => 'closure_message', 'details' => 'Synthetic', 'created_at' => wp_date('Y-m-d 11:00:00', time() - DAY_IN_SECONDS, wp_timezone())));
    $reopen = static function(int $lead, string $reason = 'customer_called_back', string $note = 'Customer called back, budget now 6 lakh.'): array {
        return idlm_reopen_call(array('IDLM_Admin_Ajax', 'ajax_presales_decision'), array('lead_id' => $lead, 'decision' => 'reopen', 'reason' => $reason, 'note' => $note));
    };
    $die_filter = static function() { return static function() { throw new IDLM_Integration_Ajax_Die('complete'); }; };
    add_filter('wp_die_ajax_handler', $die_filter);
    try {
        wp_set_current_user($users['presales']);
        $start = IDLM_DB::get_followup_compliance($own);
        idlm_integration_assert_true(6 === $start['complete_days'] && $start['closure_sent'], 'Fixture must start with six completed cycles: ' . wp_json_encode($start));

        $drawer = idlm_reopen_call(array('IDLM_Admin_Lead_Reads', 'ajax_get_lead_details'), array('lead_id' => $own))['data']['html'];
        idlm_integration_assert_true(false !== strpos($drawer, 'idlm-reopen-panel'), 'Pre-sales sees Reopen on a lead it closed.');
        idlm_integration_assert_true(false === strpos($drawer, 'data-kind="early_close"'), 'A closed lead offers no second early close.');

        $closing_again = idlm_reopen_call(array('IDLM_Admin_Ajax', 'ajax_log_presales_touch'), array('lead_id' => $own, 'kind' => 'early_close', 'reason' => 'not_interested', 'note' => 'Trying to rewrite the reasons.'));
        idlm_integration_assert_true(empty($closing_again['success']) && false !== strpos($closing_again['data']['message'], 'already closed'), 'Early close on an Unqualified lead must be refused.');

        idlm_integration_assert_true(empty($reopen($own, '')['success']), 'Reopen needs a reason.');
        idlm_integration_assert_true(empty($reopen($own, 'customer_called_back', 'short')['success']), 'Reopen needs a note of at least 10 characters.');
        foreach (array($was_qualified => 'qualified before', $duplicate => 'duplicate', $rep_lead => 'assigned to you') as $lead => $expected) {
            $refused = $reopen($lead);
            idlm_integration_assert_true(empty($refused['success']) && false !== strpos($refused['data']['message'], $expected), 'Pre-sales reopen must be refused (' . $expected . '): ' . wp_json_encode($refused));
        }
        $drawer = idlm_reopen_call(array('IDLM_Admin_Lead_Reads', 'ajax_get_lead_details'), array('lead_id' => $was_qualified))['data']['html'];
        idlm_integration_assert_true(false === strpos($drawer, 'idlm-reopen-panel'), 'Pre-sales sees no Reopen on a lead it may not reopen.');

        $done = $reopen($own);
        idlm_integration_assert_true(!empty($done['success']), 'Pre-sales reopen failed: ' . wp_json_encode($done));
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $own));
        idlm_integration_assert_true('' === (string) $lead->lead_quality && 'Contacted' === $lead->stage && '' === (string) $lead->lost_reason && '' === (string) $lead->disqualification_method, 'Reopen must return the lead to Contacted with quality pending: ' . wp_json_encode($lead));
        $log = (string) $wpdb->get_var($wpdb->prepare("SELECT details FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id = %d AND activity_type = 'lead_reopened'", $own));
        idlm_integration_assert_true(false !== strpos($log, 'Customer called back') && false !== strpos($log, 'Previous closure: Client Not Interested'), 'The reopen must be logged with its reason and the earlier closure.');
        idlm_integration_assert_true(0 < (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id = %d AND activity_type = 'closure_message'", $own)), 'Earlier outreach history must be kept.');
        $round = IDLM_DB::get_followup_compliance($own);
        idlm_integration_assert_true(0 === $round['complete_days'] && !$round['closure_sent'] && !$round['eligible'], 'A reopened lead starts a fresh outreach round.');
        $pending = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id = %d AND task_type = 'Qualification' AND status = 'pending'", $own));
        idlm_integration_assert_true(6 === $pending, 'All six qualification cycles are pending again.');
        $queue = idlm_reopen_call(array('IDLM_Admin_Collection_Reads', 'ajax_get_leads_table'), array('followup_filter' => 'action_required'))['data']['html'];
        idlm_integration_assert_true(false !== strpos($queue, 'Synthetic reopen') && false === strpos($queue, 'Send final closure'), 'The reopened lead is back in the queue without its old cycles.');
        idlm_integration_assert_true(empty($reopen($own)['success']), 'An open lead cannot be reopened again.');

        wp_set_current_user($users['rep']);
        $refused = $reopen($rep_lead);
        idlm_integration_assert_true(empty($refused['success']) && false !== strpos($refused['data']['message'], 'Only pre-sales, sales managers'), 'A sales executive cannot reopen: ' . wp_json_encode($refused));

        wp_set_current_user($users['manager']);
        $managed = $reopen($was_qualified, 'closed_by_mistake', 'Closed by mistake during review.');
        idlm_integration_assert_true(!empty($managed['success']), 'A manager reopens a lead pre-sales may not: ' . wp_json_encode($managed));

        wp_set_current_user($users['admin']);
        idlm_integration_assert_true(empty($reopen($duplicate)['success']), 'Nobody reopens a duplicate.');
    } finally {
        remove_filter('wp_die_ajax_handler', $die_filter);
        wp_set_current_user($original_user);
        $id_sql = implode(',', array_map('absint', $ids));
        foreach (array('idlm_lead_activity_log', 'idlm_tasks') as $table) { $wpdb->query("DELETE FROM {$wpdb->prefix}{$table} WHERE lead_id IN ({$id_sql})"); }
        $wpdb->query("DELETE FROM {$wpdb->prefix}idlm_leads WHERE id IN ({$id_sql})");
        require_once ABSPATH . 'wp-admin/includes/user.php';
        foreach ($users as $user) { wp_delete_user($user); }
    }
});
