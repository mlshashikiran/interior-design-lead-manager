<?php
/** M6 handoff email facade, payload and durable retry contracts. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

idlm_integration_test('M6-handoff-opt-in-and-hook-preserve-recipient-and-message-contract', static function(): void {
    global $wpdb, $administrator_id;
    // Focused native runs skip the earlier REST-registration case that calls init.
    IDLM_Integrations::init();
    $previous = get_option('idlm_handoff_notifications_enabled', '0');
    $messages = array(); $reads = 0;
    $capture = static function($result, $attributes) use (&$messages) { $messages[] = $attributes; return true; };
    $observe = static function($sql) use (&$reads) { if (strpos($sql, 'SELECT first_name, last_name, preferred_studio FROM ') === 0) { $reads++; } return $sql; };
    add_filter('pre_wp_mail', $capture, PHP_INT_MAX, 2); add_filter('query', $observe);
    try {
        idlm_integration_assert_same(10, has_action('idlm_qualified_handoff_notification', array('IDLM_Integrations', 'send_handoff_notifications')));
        idlm_integration_assert_same(4, $GLOBALS['wp_filter']['idlm_qualified_handoff_notification']->callbacks[10]['IDLM_Integrations::send_handoff_notifications']['accepted_args']);
        $lead = idlm_lifecycle_fixture(array('first_name'=>'Synthetic', 'last_name'=>'Handoff', 'preferred_studio'=>'North Studio'));
        update_option('idlm_handoff_notifications_enabled', '0');
        IDLM_Integrations::send_handoff_notifications($lead, $administrator_id, $administrator_id, 'Synthetic handoff');
        idlm_integration_assert_same(0, $reads); idlm_integration_assert_same(array(), $messages);
        update_option('idlm_handoff_notifications_enabled', '1');
        do_action('idlm_qualified_handoff_notification', $lead, $administrator_id, $administrator_id, 'Synthetic handoff');
        idlm_integration_assert_same(1, count($messages));
        idlm_integration_assert_same(array(get_userdata($administrator_id)->user_email), $messages[0]['to']);
        idlm_integration_assert_same('[Lead Manager] Qualified lead assigned: Synthetic Handoff', $messages[0]['subject']);
        idlm_integration_assert_same("Synthetic handoff\n\nLead: Synthetic Handoff\nPreferred Studio: North Studio", $messages[0]['message']);
    } finally { remove_filter('pre_wp_mail', $capture, PHP_INT_MAX); remove_filter('query', $observe); update_option('idlm_handoff_notifications_enabled', $previous); }
});

idlm_integration_test('M6-handoff-missing-lead-and-recipient-fallbacks-remain-compatible', static function(): void {
    global $wpdb, $administrator_id;
    $previous = get_option('idlm_handoff_notifications_enabled', '0'); $messages = array();
    $capture = static function($result, $attributes) use (&$messages) { $messages[] = $attributes; return false; };
    add_filter('pre_wp_mail', $capture, PHP_INT_MAX, 2);
    try {
        update_option('idlm_handoff_notifications_enabled', '1');
        $missing = 1 + (int) $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}idlm_leads");
        IDLM_Integrations::send_handoff_notifications($missing, 0, 0, 'Synthetic fallback');
        idlm_integration_assert_same(array(), $messages);
        // Outside durable dispatch, the existing facade does not throw on mail rejection.
        idlm_integration_assert_same(null, IDLM_Integrations::send_handoff_notifications($missing, 0, $administrator_id, 'Synthetic fallback'));
        idlm_integration_assert_same(1, count($messages));
        idlm_integration_assert_same(array(get_userdata($administrator_id)->user_email), $messages[0]['to']);
        idlm_integration_assert_same('[Lead Manager] Qualified lead assigned: Lead #' . $missing, $messages[0]['subject']);
        idlm_integration_assert_same("Synthetic fallback\n\nLead: Lead #" . $missing, $messages[0]['message']);
    } finally { remove_filter('pre_wp_mail', $capture, PHP_INT_MAX); update_option('idlm_handoff_notifications_enabled', $previous); }
});

idlm_integration_test('M6-handoff-committed-intent-survives-mail-rejection-and-acknowledges-retry', static function(): void {
    global $wpdb, $administrator_id;
    $previous = get_option('idlm_handoff_notifications_enabled', '0'); $accept = false; $messages = array();
    $capture = static function($result, $attributes) use (&$messages, &$accept) { $messages[] = $attributes; return $accept; };
    add_filter('pre_wp_mail', $capture, PHP_INT_MAX, 2);
    try {
        update_option('idlm_handoff_notifications_enabled', '1');
        $lead = idlm_lifecycle_fixture(array('first_name'=>'Synthetic retry'));
        idlm_integration_assert_same(true, IDLM_Lead_Transaction::run(static function() use ($lead, $administrator_id) {
            IDLM_Lead_Transaction::defer('idlm_qualified_handoff_notification', $lead, 0, $administrator_id, 'Synthetic durable handoff'); return true;
        }));
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_lead_outbox WHERE lead_id=%d AND hook=%s", $lead, 'idlm_qualified_handoff_notification'));
        idlm_integration_assert_true((bool) $row); idlm_integration_assert_same(1, (int) $row->attempts);
        idlm_integration_assert_same(1, count($messages)); idlm_integration_assert_same(false, IDLM_Lead_Outbox::dispatching());
        idlm_integration_assert_same(1, $wpdb->update($wpdb->prefix . 'idlm_lead_outbox', array('available_at'=>'2000-01-01 00:00:00'), array('id'=>$row->id)));
        $accept = true; idlm_integration_assert_same(true, IDLM_Lead_Outbox::dispatch($row->id));
        idlm_integration_assert_same(2, count($messages)); idlm_integration_assert_same($messages[0], $messages[1]);
        idlm_integration_assert_same(0, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_outbox WHERE id=%d", $row->id)));
    } finally { remove_filter('pre_wp_mail', $capture, PHP_INT_MAX); update_option('idlm_handoff_notifications_enabled', $previous); }
});
