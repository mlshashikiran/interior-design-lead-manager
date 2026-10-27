<?php
/** Checked active-workload admission. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Assignment_Capacity {
    public static function is_active($lead) {
        $lead = (array) $lead;
        return 0 === (int) ($lead['is_duplicate'] ?? 0) && !in_array(strtolower(rtrim((string) ($lead['stage'] ?? ''))), array('won', 'lost'), true)
            && 'unqualified' !== strtolower(rtrim((string) ($lead['lead_quality'] ?? '')));
    }

    private static function active_owner($lead) {
        $lead = (array) $lead;
        return self::is_active($lead) ? absint($lead['assigned_rep_id'] ?? 0) : 0;
    }

    /** Only positive workload deltas need admission; locks live through commit/rollback. */
    public static function validate_transition($before, $after) {
        global $wpdb;
        $owner = self::active_owner($after);
        if (!$owner || $owner === self::active_owner($before)) { return true; }
        if (!IDLM_Lead_Transaction::active()) { return new WP_Error('idlm_capacity_transaction', 'Capacity admission requires a lead transaction.'); }
        foreach (array($wpdb->users, $wpdb->options) as $table) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0535: validate_transition(): fixed prefixed leads table and active predicates; rep/lead IDs bound; capacity must be counted under current assignment locks.
            $engine = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare(
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table
            )));
            IDLM_Lead_Transaction::required('INNODB' === strtoupper((string) $engine));
        }
        // All admissions for this representative serialize on the existing account row.
        // A locking count below sees current committed rows even under repeatable read.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0536: validate_transition(): fixed prefixed leads table and active predicates; rep/lead IDs bound; capacity must be counted under current assignment locks.
        $user = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->users} WHERE ID = %d FOR UPDATE", $owner)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        IDLM_Lead_Transaction::required((bool) $user);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0537: validate_transition(): fixed prefixed leads table and active predicates; rep/lead IDs bound; capacity must be counted under current assignment locks.
        $options = IDLM_Lead_Transaction::read($wpdb->get_results($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name IN (%s, %s) ORDER BY option_name FOR UPDATE",
            'idlm_max_active_leads_per_rep', IDLM_Settings::ROUTING_OPTION
        )));
        $settings = array();
        foreach ($options as $option) { $settings[$option->option_name] = maybe_unserialize($option->option_value); }
        $group = isset($settings[IDLM_Settings::ROUTING_OPTION]) && is_array($settings[IDLM_Settings::ROUTING_OPTION]) ? $settings[IDLM_Settings::ROUTING_OPTION] : array();
        $maximum = max(0, absint($settings['idlm_max_active_leads_per_rep'] ?? ($group['idlm_max_active_leads_per_rep'] ?? 30)));
        if (0 === $maximum) { return true; }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0538: validate_transition(): fixed prefixed leads table and active predicates; rep/lead IDs bound; capacity must be counted under current assignment locks.
        $count = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE assigned_rep_id = %d AND is_duplicate = 0
             AND COALESCE(stage, '') NOT IN ('Won', 'Lost') AND COALESCE(lead_quality, '') != 'Unqualified' FOR UPDATE", $owner
        )));
        IDLM_Lead_Transaction::required(is_numeric($count) && (int) $count >= 0);
        return (int) $count < $maximum ? true : new WP_Error('idlm_capacity_full', 'This representative has reached the active-lead capacity. Close or reassign an active lead first.');
    }
}
