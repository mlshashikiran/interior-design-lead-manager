<?php
/**
 * Lead reminder counts and WhatsApp read-state presentation support.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Admin_Lead_Notifications {
    const STALE_LEAD_DAYS = 7;

    /** Activity types that demonstrate an assigned representative has acted. */
    const REP_ACTION_ACTIVITY_TYPES = array(
        'update', 'outbound_call', 'whatsapp_outbound', 'comment', 'stage_change',
        'call_status_change', 'task', 'qualification_decision', 'lead_quality',
        'early_disqualification', 'qualification_handoff_remark', 'closure_message',
        'lost_remark', 'duplicate_exclusion', 'quotation',
    );

    /** Request-local copy of the current user's unread WhatsApp snapshot. */
    protected static $whatsapp_unread_snapshot = null;

    /** Return the latest WhatsApp activity IDs viewed by the current representative. */
    public static function get_whatsapp_read_activity_ids() {
        $read_ids = get_user_meta(get_current_user_id(), 'idlm_whatsapp_read_activity_ids', true);
        if (!is_array($read_ids)) {
            return array();
        }

        $sanitized_read_ids = array();
        foreach ($read_ids as $lead_id => $activity_id) {
            $sanitized_read_ids[absint($lead_id)] = absint($activity_id);
        }
        return $sanitized_read_ids;
    }

    /** Mark a lead's latest WhatsApp reply as viewed by the current representative. */
    public static function mark_whatsapp_reply_read($lead_id, $activity_id) {
        $lead_id = absint($lead_id);
        $activity_id = absint($activity_id);
        if (!$lead_id || !$activity_id) {
            return;
        }

        $read_ids = self::get_whatsapp_read_activity_ids();
        $read_ids[$lead_id] = $activity_id;
        if (count($read_ids) > 1000) {
            $read_ids = array_slice($read_ids, -1000, null, true);
        }
        update_user_meta(get_current_user_id(), 'idlm_whatsapp_read_activity_ids', $read_ids);
        self::$whatsapp_unread_snapshot = null;
    }

    /** Resolve unread WhatsApp leads and message count with one indexed query. */
    protected static function get_unread_whatsapp_snapshot() {
        global $wpdb;

        if (null !== self::$whatsapp_unread_snapshot) {
            return self::$whatsapp_unread_snapshot;
        }

        $read_ids = self::get_whatsapp_read_activity_ids();
        $visibility_restricted = (bool) IDLM_Lead_Access_Policy::restriction_user_id();
        $current_user_id = get_current_user_id();
        $latest_activity_id = absint($wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}idlm_lead_activity_log"));
        $cache_key = 'unread_' . md5(wp_json_encode(array(
            'user_id' => $current_user_id,
            'restricted' => $visibility_restricted,
            'latest_activity_id' => $latest_activity_id,
            'read_ids' => $read_ids,
        )));
        $cached = wp_cache_get($cache_key, 'idlm_whatsapp');
        if (is_array($cached) && isset($cached['lead_ids'], $cached['count'])) {
            self::$whatsapp_unread_snapshot = $cached;
            return self::$whatsapp_unread_snapshot;
        }

        $whatsapp_reply_pattern = $wpdb->esc_like('[WhatsApp Reply]:') . '%';
        $direct_reply_pattern = $wpdb->esc_like('[Direct WA Reply]:') . '%';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.lead_id
             FROM {$wpdb->prefix}idlm_lead_activity_log a
             INNER JOIN {$wpdb->prefix}idlm_leads l ON l.id = a.lead_id
             WHERE l.is_duplicate = 0
               AND (
                   a.activity_type = 'whatsapp_reply'
                   OR (a.activity_type = 'comment' AND (a.details LIKE %s OR a.details LIKE %s))
               )
               AND (%d = 0 OR l.assigned_rep_id = %d)",
            $whatsapp_reply_pattern,
            $direct_reply_pattern,
            $visibility_restricted ? 1 : 0,
            $current_user_id
        ));

        $unread_lead_ids = array();
        $unread_count = 0;
        foreach ($activities as $activity) {
            $lead_id = absint($activity->lead_id);
            $last_read_id = isset($read_ids[$lead_id]) ? absint($read_ids[$lead_id]) : 0;
            if (absint($activity->id) > $last_read_id) {
                $unread_lead_ids[$lead_id] = $lead_id;
                $unread_count++;
            }
        }

        self::$whatsapp_unread_snapshot = array(
            'lead_ids' => array_values($unread_lead_ids),
            'count' => $unread_count,
        );
        wp_cache_set($cache_key, self::$whatsapp_unread_snapshot, 'idlm_whatsapp', 15);

        return self::$whatsapp_unread_snapshot;
    }

    /** Return lead IDs with WhatsApp replies not yet viewed by the current representative. */
    public static function get_unread_whatsapp_lead_ids() {
        $snapshot = self::get_unread_whatsapp_snapshot();
        return $snapshot['lead_ids'];
    }

    /** Return the number of unread WhatsApp message activities visible to the current user. */
    public static function get_unread_whatsapp_count() {
        $snapshot = self::get_unread_whatsapp_snapshot();
        return $snapshot['count'];
    }

    /** Return lead IDs that have received at least one WhatsApp reply. */
    public static function get_whatsapp_received_lead_ids() {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return array_map('absint', $wpdb->get_col(
            "SELECT DISTINCT lead_id
             FROM {$wpdb->prefix}idlm_lead_activity_log
             WHERE activity_type = 'whatsapp_reply'
                OR (activity_type = 'comment' AND (details LIKE '[WhatsApp Reply]:%' OR details LIKE '[Direct WA Reply]:%'))"
        ));
    }

    /** Get all active follow-up reminder rules with legacy singular-option fallback. */
    public static function get_reminder_rules() {
        $rules = get_option('idlm_reminders_config');
        if (empty($rules)) {
            $rules = array(
                array(
                    'status' => get_option('idlm_reminder_status', 'Call Back Requested'),
                    'hours'  => get_option('idlm_reminder_hours', 24),
                ),
            );
        }
        return $rules;
    }

    /** Check if a lead's follow-up is overdue based on configured rules. */
    public static function check_lead_overdue_status($lead, $rules = null) {
        if (!$rules) {
            $rules = self::get_reminder_rules();
        }

        $current_status = $lead->call_status;
        $matched_rule = null;
        foreach ($rules as $rule) {
            if ($rule['status'] === $current_status) {
                $matched_rule = $rule;
                break;
            }
        }

        if (!$matched_rule) {
            return false;
        }

        $interval_hours = intval($matched_rule['hours']);
        $now_time = current_time('timestamp');

        if ('Call Back Requested' === $current_status && !empty($lead->callback_datetime)) {
            $callback_time = strtotime($lead->callback_datetime);
            if ($now_time > ($callback_time + $interval_hours * 3600)) {
                return array(
                    'overdue' => true,
                    'hours_overdue' => max(0, round(($now_time - $callback_time) / 3600)),
                    'reason' => 'Callback scheduled for ' . wp_date('M d, h:i A', $callback_time),
                );
            }
            return false;
        }

        $ref_time = !empty($lead->updated_at) ? strtotime($lead->updated_at) : strtotime($lead->created_at);
        $elapsed_hours = ($now_time - $ref_time) / 3600;
        if ($elapsed_hours > $interval_hours) {
            return array(
                'overdue' => true,
                'hours_overdue' => max(0, round($elapsed_hours - $interval_hours)),
                'reason' => "Status '$current_status' for " . round($elapsed_hours) . ' hrs',
            );
        }

        return false;
    }

    /** Return the visible count of leads in active call reminder queues. */
    public static function get_pending_calls_count() {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $current_user_id = get_current_user_id();
        $statuses = array('Not Called');
        $rules = self::get_reminder_rules();
        if (is_array($rules)) {
            foreach ($rules as $rule) {
                if (!empty($rule['status'])) {
                    $statuses[] = $rule['status'];
                }
            }
        }
        $statuses = array_unique($statuses);
        $placeholders = implode(',', array_fill(0, count($statuses), '%s'));

        if (IDLM_Lead_Access_Policy::restriction_user_id()) {
            $query = "SELECT COUNT(id) FROM {$table_leads} WHERE call_status IN ($placeholders) AND is_duplicate = 0 AND assigned_rep_id = %d";
            $params = array_merge($statuses, array($current_user_id));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            return intval($wpdb->get_var($wpdb->prepare($query, $params)));
        }

        $query = "SELECT COUNT(id) FROM {$table_leads} WHERE call_status IN ($placeholders) AND is_duplicate = 0";
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return intval($wpdb->get_var($wpdb->prepare($query, $statuses)));
    }

    /**
     * Count active assigned leads that have not received an update in a week.
     *
     * Reps receive only their own count; managers retain their usual all-leads
     * view. Won, lost, duplicate, and unassigned leads are intentionally not
     * reminders because no representative needs to provide an update for them.
     */
    public static function get_stale_lead_count($sales_board_only = false) {
        global $wpdb;

        $table_leads = $wpdb->prefix . 'idlm_leads';
        $cutoff = self::get_stale_lead_cutoff();
        $restricted_user_id = IDLM_Lead_Access_Policy::restriction_user_id();
        $rep_clause = $restricted_user_id
            ? $wpdb->prepare(' AND assigned_rep_id = %d', $restricted_user_id)
            : '';
        $sales_board_clause = $sales_board_only ? " AND stage NOT IN ('New', 'Contacted')" : '';
        $no_recent_action = self::get_no_recent_assigned_rep_action_sql($cutoff);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return intval($wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_leads}
             WHERE assigned_rep_id > 0
               AND is_duplicate = 0
               AND stage NOT IN ('Won', 'Lost')
               AND COALESCE(assigned_at, created_at) < %s
               AND {$no_recent_action}{$sales_board_clause}{$rep_clause}",
            $cutoff
        )));
    }

    /** Build the SQL predicate for no qualifying owner action since a timestamp. */
    public static function get_no_recent_assigned_rep_action_sql($since, $lead_alias = '') {
        global $wpdb;

        $lead_prefix = 'l' === $lead_alias ? 'l.' : $wpdb->prefix . 'idlm_leads.';
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';
        $activity_types = implode(', ', array_map(static function($activity_type) use ($wpdb) {
            return $wpdb->prepare('%s', $activity_type);
        }, self::REP_ACTION_ACTIVITY_TYPES));

        return $wpdb->prepare(
            "NOT EXISTS (
                SELECT 1 FROM {$table_activity} a
                WHERE a.lead_id = {$lead_prefix}id
                  AND a.user_id = {$lead_prefix}assigned_rep_id
                  AND a.created_at >= %s
                  AND a.created_at > COALESCE({$lead_prefix}assigned_at, {$lead_prefix}created_at)
                  AND a.activity_type IN ({$activity_types})
            )",
            $since
        );
    }

    /** Return the local CRM datetime before which a lead needs a fresh update. */
    public static function get_stale_lead_cutoff() {
        $cutoff = new DateTime(current_time('mysql'), wp_timezone());
        $cutoff->modify('-' . self::STALE_LEAD_DAYS . ' days');

        return $cutoff->format('Y-m-d H:i:s');
    }
}
