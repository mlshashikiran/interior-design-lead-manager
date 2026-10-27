<?php
/** REL-007 literal assignment-origin reporting regression. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

idlm_integration_test('REL007 dashboard assignment origins require the literal automatic underscore prefix', static function() use ($administrator_id): void {
    global $wpdb;
    wp_set_current_user($administrator_id);
    $rep = wp_insert_user(array('user_login' => 'rel007_rep_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'author'));
    $other = wp_insert_user(array('user_login' => 'rel007_other_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'author'));
    idlm_integration_assert_true(!is_wp_error($rep) && !is_wp_error($other));
    $source = "REL007_%'\\source";
    $time = '2026-10-13 12:00:00';
    // Reporting-only fixtures bypass lifecycle work in this owned disposable database.
    idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix . 'idlm_leads', array(
        'first_name' => 'Synthetic assignment reporting', 'assigned_rep_id' => $rep,
        'source' => $source, 'stage' => 'New', 'is_duplicate' => 0,
        'created_at' => $time, 'updated_at' => $time, 'assigned_at' => $time,
    )));
    $lead_id = (int) $wpdb->insert_id;
    foreach (array('automatic_intake', 'automatic_qualified_fallback', 'automaticXmanual', 'automatically', 'automatic', 'manual', 'creation', '') as $origin) {
        idlm_integration_assert_true(IDLM_DB::record_reporting_event($lead_id, 'assignment', '', $rep, '', $origin, $administrator_id, $time));
    }
    $history = new ReflectionProperty('IDLM_Dashboard', 'reporting_event_history');
    if (PHP_VERSION_ID < 80100) { $history->setAccessible(true); }
    $before_history = $history->getValue();
    $history->setValue(null, array());
    $before = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_lead_state_events WHERE lead_id = %d ORDER BY id", $lead_id), ARRAY_A);
    try {
        $filters = array('rep_id' => $rep, 'source' => $source);
        $data = IDLM_Dashboard::calculate_stats('custom', '2026-10-01', '2026-10-31', $filters);
        idlm_integration_assert_same('', $wpdb->last_error);
        idlm_integration_assert_same('recorded_assignments', $data['sales']['assignment_distribution_basis']);
        idlm_integration_assert_same(1, count($data['sales']['assignment_distribution']));
        $row = $data['sales']['assignment_distribution'][0];
        idlm_integration_assert_same((int) $rep, $row['rep_id']);
        idlm_integration_assert_same(8, $row['total']);
        idlm_integration_assert_same(2, $row['ooo_assigned']);
        idlm_integration_assert_same(6, $row['manager_assigned']);
        $old_context = IDLM_Dashboard_Read_Model::cache_context('custom', '2026-10-01', '2026-10-31');
        $old_context['dashboard_contract'] = 3;
        $old_context['dashboard_filters'] = array_map('strval', $filters);
        IDLM_Read_Model_Cache::remember('dashboard', $old_context, static function() { return array('obsolete_assignment_counts' => true); });
        $cached = IDLM_Dashboard::get_stats('custom', '2026-10-01', '2026-10-31', $filters);
        idlm_integration_assert_same($data['sales']['assignment_distribution'], $cached['sales']['assignment_distribution']);
        $warm = IDLM_Dashboard::get_stats('custom', '2026-10-01', '2026-10-31', $filters);
        idlm_integration_assert_same($cached['sales']['assignment_distribution'], $warm['sales']['assignment_distribution']);
        $outside = IDLM_Dashboard::calculate_stats('custom', '2026-09-01', '2026-09-30', $filters);
        idlm_integration_assert_same(array(), $outside['sales']['assignment_distribution']);
        idlm_integration_assert_same($before, $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_lead_state_events WHERE lead_id = %d ORDER BY id", $lead_id), ARRAY_A));
        idlm_integration_assert_same(1, $wpdb->update($wpdb->prefix . 'idlm_leads', array('assigned_rep_id' => $other), array('id' => $lead_id)));
        $reassigned = IDLM_Dashboard::calculate_stats('custom', '2026-10-01', '2026-10-31', $filters);
        idlm_integration_assert_same(array(), $reassigned['sales']['assignment_distribution']);
        idlm_integration_assert_same('', $wpdb->last_error);
    } finally {
        $history->setValue(null, $before_history);
        $wpdb->delete($wpdb->prefix . 'idlm_lead_state_events', array('lead_id' => $lead_id));
        $wpdb->delete($wpdb->prefix . 'idlm_leads', array('id' => $lead_id));
        wp_set_current_user($administrator_id);
    }
});
