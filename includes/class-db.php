<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_DB {
    protected static $last_validation_error = '';

    public static function get_last_validation_error() {
        return self::$last_validation_error;
    }
    public static function init() {
        $steps = array(
            'idlm_handoff_state_version' => 'backfill_handoff_state',
            'idlm_duplicate_quality_version' => 'backfill_duplicate_early_close_quality',
            'idlm_workflow_integrity_version' => 'repair_workflow_integrity',
            'idlm_assignment_timestamp_version' => 'backfill_assignment_timestamps',
            'idlm_attribution_touchpoint_backfill_version' => 'backfill_duplicate_touchpoints',
            'idlm_conversion_time_integrity_version' => 'clear_non_won_conversion_times',
            'idlm_task_queue_cleanup_version' => 'cleanup_stale_pending_tasks',
        );
        $complete = IDLM_DB_VERSION === get_option('idlm_schema_version', '');
        foreach ($steps as $option => $method) { $complete = $complete && '1.0.1' === get_option($option, ''); }
        if ($complete) { return true; }
        return IDLM_Schema_Migration::run(static function () use ($steps) {
            $version = IDLM_Schema_Migration::version('idlm_schema_version');
            if (null === $version) { return IDLM_Schema_Migration::error('version_read'); }
            if ($version && version_compare($version, IDLM_DB_VERSION, '>')) { return IDLM_Schema_Migration::error('future_version', 'CRM schema is newer than this plugin. Install a matching plugin version or recover the matching database backup.'); }
            if (!$version || version_compare($version, IDLM_DB_VERSION, '<')) {
                $result = self::create_tables();
                if (is_wp_error($result)) { return $result; }
            }
            // v1.0.1 rechecks legacy markers whose writers did not check SQL.
            foreach ($steps as $option => $method) {
                $saved = IDLM_Schema_Migration::version($option);
                if (null === $saved) { return IDLM_Schema_Migration::error('marker_read'); }
                if ('1.0.1' === $saved) { continue; }
                $result = self::$method();
                if (false === $result || is_wp_error($result)) { return is_wp_error($result) ? $result : IDLM_Schema_Migration::error($method); }
                $result = IDLM_Schema_Migration::publish($option, '1.0.1');
                if (is_wp_error($result)) { return $result; }
            }
            return true;
        });
    }

    /** Archive pending work which cannot be actioned safely. */
    public static function cleanup_stale_pending_tasks() {
        global $wpdb;
        $tasks = $wpdb->prefix . 'idlm_tasks';
        $leads = $wpdb->prefix . 'idlm_leads';
        $users = $wpdb->users;
        $now = current_time('mysql');

        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0228: cleanup_stale_pending_tasks(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $lost = $wpdb->query($wpdb->prepare(
            "UPDATE {$tasks} t INNER JOIN {$leads} l ON l.id = t.lead_id
             SET t.status = 'cancelled', t.completed_at = NULL, t.updated_at = %s
             WHERE t.status = 'pending' AND l.stage = 'Lost'",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $now
        ));
        // A task can retain an obsolete task owner even when its lead is still
        // active. Archive it for a manager to recreate/reassign deliberately.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0229: cleanup_stale_pending_tasks(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $orphaned = $wpdb->query($wpdb->prepare(
            "UPDATE {$tasks} t LEFT JOIN {$users} u ON u.ID = t.assigned_rep_id
             SET t.status = 'cancelled', t.completed_at = NULL, t.updated_at = %s
             WHERE t.status = 'pending' AND t.assigned_rep_id > 0 AND u.ID IS NULL",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $now
        ));

        if (false === $lost || false === $orphaned) { return false; }
        return array('lost' => max(0, (int) $lost), 'orphaned' => max(0, (int) $orphaned));
    }

    /** Archive all pending work for a lead when it enters the Lost stage. */
    public static function archive_pending_tasks_for_lost_lead($lead_id) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->update(
            $wpdb->prefix . 'idlm_tasks',
            array('status' => 'cancelled', 'completed_at' => null, 'updated_at' => current_time('mysql')),
            array('lead_id' => absint($lead_id), 'status' => 'pending')
        );
    }

    /** Clear legacy close timestamps from records that are not currently Won. */
    protected static function clear_non_won_conversion_times() {
        global $wpdb;
        $leads = $wpdb->prefix . 'idlm_leads';

        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0230: clear_non_won_conversion_times(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query(
            "UPDATE {$leads}
             SET conversion_time = NULL
             WHERE COALESCE(stage, '') <> 'Won'
               AND conversion_time IS NOT NULL"
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        return false !== $result;
    }

    /**
     * Recover the timestamp of the current ownership assignment from durable history.
     */
    protected static function backfill_assignment_timestamps() {
        global $wpdb;
        $leads = $wpdb->prefix . 'idlm_leads';
        $activity = $wpdb->prefix . 'idlm_lead_activity_log';

        // Prefer the latest explicit assignment event, then the qualified handoff.
        // updated_at is only a last-resort legacy estimate because unrelated edits also change it.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0231: backfill_assignment_timestamps(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query(
            "UPDATE {$leads} l
             LEFT JOIN (
                 SELECT lead_id, MAX(created_at) AS latest_assignment_at
                 FROM {$activity}
                 WHERE activity_type = 'assignment'
                 GROUP BY lead_id
             ) a ON a.lead_id = l.id
             SET l.assigned_at = CASE
                 WHEN l.assigned_rep_id = 0 THEN NULL
                 ELSE COALESCE(a.latest_assignment_at, l.handoff_at, l.updated_at, l.created_at)
             END
             WHERE l.assigned_at IS NULL"
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        return false !== $result;
    }

    /**
     * Repair duplicate early closures created before version 1.40.11.
     */
    protected static function backfill_duplicate_early_close_quality() {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0232: backfill_duplicate_early_close_quality(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (false === $wpdb->query(
            "UPDATE {$table_leads}
             SET lead_quality = 'Unqualified'
             WHERE is_duplicate = 1
               AND disqualification_method = 'early_close'
               AND disqualification_reason = 'duplicate'
               AND (lead_quality = '' OR lead_quality IS NULL)"
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        )) { return false; }
        return true;
    }

    /**
     * Repair workflow combinations that older mutation paths allowed.
     */
    protected static function repair_workflow_integrity() {
        global $wpdb;
        $leads = $wpdb->prefix . 'idlm_leads';
        $tasks = $wpdb->prefix . 'idlm_tasks';
        $now = current_time('mysql');

        // Every duplicate is a closed, unqualified record linked to its canonical lead.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0233: repair_workflow_integrity(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (false === $wpdb->query($wpdb->prepare(
            "UPDATE {$leads}
             SET lead_quality = 'Unqualified', stage = 'Lost', lost_reason = 'Duplicate or Invalid Lead',
                 disqualification_reason = 'duplicate', disqualification_method = 'duplicate_detection',
                 disqualified_at = COALESCE(disqualified_at, updated_at, created_at, %s),
                 handoff_status = 'not_ready', handoff_at = NULL, handoff_method = '', handoff_exception_reason = ''
             WHERE is_duplicate = 1 AND duplicate_of_id > 0",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $now
        ))) { return false; }

        // Every Unqualified decision is terminal, not an active-pipeline record.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0234: repair_workflow_integrity(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (false === $wpdb->query(
            "UPDATE {$leads}
             SET stage = 'Lost', lost_reason = CASE
                 WHEN disqualification_reason = 'duplicate' THEN 'Duplicate or Invalid Lead'
                 WHEN disqualification_reason = 'budget_below_minimum' THEN 'Budget Too High'
                 WHEN disqualification_reason = 'outside_service_area' THEN 'Location or Service Unavailable'
                 WHEN disqualification_reason = 'timeline_mismatch' THEN 'Timeline Mismatch'
                 WHEN disqualification_reason = 'not_interested' THEN 'Client Not Interested'
                 WHEN disqualification_reason = 'unable_to_contact' THEN 'Unable to Contact'
                 ELSE 'Other' END
             WHERE lead_quality = 'Unqualified' AND stage <> 'Lost'"
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        )) { return false; }

        // A Qualified quality decision cannot remain in a pre-qualification stage.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0235: repair_workflow_integrity(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (false === $wpdb->query("UPDATE {$leads} SET stage = 'Qualified' WHERE lead_quality = 'Qualified' AND stage IN ('', 'New', 'Contacted')")) { return false; }
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Advanced sales stages require a qualified lead unless the record was explicitly closed.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0236: repair_workflow_integrity(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (false === $wpdb->query($wpdb->prepare(
            "UPDATE {$leads}
             SET lead_quality = 'Qualified', qualified_at = COALESCE(qualified_at, updated_at, created_at, %s),
                 qualified_by_user_id = COALESCE(NULLIF(qualified_by_user_id, 0), 0),
                 disqualification_reason = '', disqualification_method = '', disqualified_at = NULL, disqualified_by_user_id = 0
             WHERE is_duplicate = 0 AND stage IN ('Qualified', 'Quote Shared', 'Negotiation', 'Won')
               AND COALESCE(lead_quality, '') <> 'Qualified' AND COALESCE(disqualification_method, '') <> 'early_close'",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $now
        ))) { return false; }

        // Legacy terminal stages missing their required commercial values cannot stay terminal.
        // Downgrade them to the nearest valid state without inventing revenue or quote amounts.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0237: repair_workflow_integrity(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (false === $wpdb->query("UPDATE {$leads} SET stage = 'Negotiation', conversion_time = NULL WHERE stage = 'Won' AND COALESCE(deal_value, 0) <= 0")) { return false; }
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (false === $wpdb->query("UPDATE {$leads} SET stage = 'Qualified' WHERE stage = 'Quote Shared' AND (COALESCE(quote_shared, 0) = 0 OR COALESCE(quote_value, 0) <= 0)")) { return false; }
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

        // Preserve historical Unqualified decisions with explicit legacy audit metadata.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0238: repair_workflow_integrity(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (false === $wpdb->query($wpdb->prepare(
            "UPDATE {$leads}
             SET disqualification_reason = COALESCE(NULLIF(disqualification_reason, ''), 'legacy_unqualified'),
                 disqualification_method = COALESCE(NULLIF(disqualification_method, ''), 'legacy_import'),
                 disqualified_at = COALESCE(disqualified_at, updated_at, created_at, %s)
             WHERE lead_quality = 'Unqualified'",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $now
        ))) { return false; }

        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0239: repair_workflow_integrity(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if (false === $wpdb->query("DELETE t FROM {$tasks} t LEFT JOIN {$leads} l ON l.id = t.lead_id WHERE l.id IS NULL")) { return false; }
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        return true;
    }

    /**
     * Return configured workflow values accepted by every mutation path.
     */
    protected static function get_allowed_stages() {
        return array_values(array_unique(array_merge(
            array_values(IDLM_State_Registry::stages()),
            (array) get_option('idlm_custom_stages', array())
        )));
    }

    /**
     * Apply cross-field workflow invariants before validation and persistence.
     */
    protected static function normalize_workflow_transition($old_lead, $data) {
        $allowed_qualities = array('', IDLM_State_Registry::QUALITY_QUALIFIED, IDLM_State_Registry::QUALITY_UNQUALIFIED);
        if (isset($data['lead_quality']) && !in_array($data['lead_quality'], $allowed_qualities, true)) {
            self::$last_validation_error = 'Invalid lead quality.';
            return false;
        }
        if (isset($data['stage']) && !in_array($data['stage'], self::get_allowed_stages(), true)) {
            self::$last_validation_error = 'Invalid lead stage.';
            return false;
        }

        $candidate_stage = isset($data['stage']) ? $data['stage'] : $old_lead->stage;
        $candidate_quality = isset($data['lead_quality']) ? $data['lead_quality'] : $old_lead->lead_quality;
        // Qualification is an explicit pre-sales decision. Downstream sales
        // stages must not implicitly qualify a lead or trigger handoff checks.
        $qualified_stages = IDLM_State_Registry::quality_coupled_stages();

        if (in_array($candidate_stage, $qualified_stages, true)) {
            $data['lead_quality'] = 'Qualified';
            $candidate_quality = 'Qualified';
        }
        if ('Qualified' === $candidate_quality && in_array($candidate_stage, array('', 'New', 'Contacted'), true)) {
            $data['stage'] = 'Qualified';
            $candidate_stage = 'Qualified';
        }
        if ('Unqualified' === $candidate_quality) {
            $data['stage'] = 'Lost';
            $data['lost_reason'] = $data['lost_reason'] ?? 'Unable to Contact';
            $candidate_stage = 'Lost';
        }

        if ('Lost' === $candidate_stage && empty(trim((string) ($data['lost_reason'] ?? $old_lead->lost_reason)))) {
            self::$last_validation_error = 'A lost reason is required before moving the lead to Lost.';
            return false;
        }
        if (isset($data['stage']) && 'Lost' !== $data['stage'] && 'Lost' === $old_lead->stage) {
            $data['lost_reason'] = '';
        }
        if (isset($data['call_status']) && 'Call Back Requested' !== $data['call_status']) {
            $data['callback_datetime'] = null;
        }

        $candidate = array_merge((array) $old_lead, $data);
        if ('Won' === $candidate_stage) {
            if (empty($candidate['deal_value']) || floatval($candidate['deal_value']) <= 0) {
                self::$last_validation_error = 'A valid deal value greater than zero is required before moving the lead to Won.';
                return false;
            }
            if (!isset($candidate['discount_percentage']) || !is_numeric($candidate['discount_percentage']) || floatval($candidate['discount_percentage']) < 0 || floatval($candidate['discount_percentage']) > 100) {
                self::$last_validation_error = 'A discount percentage between 0 and 100 is required before moving the lead to Won.';
                return false;
            }
        }
        if ('Quote Shared' === $candidate_stage && (empty($candidate['quote_shared']) || empty($candidate['quote_value']) || floatval($candidate['quote_value']) <= 0)) {
            self::$last_validation_error = 'A positive quote value and Quote Shared confirmation are required before moving the lead to Quote Shared.';
            return false;
        }

        return $data;
    }

    /**
     * Mark existing qualified leads still owned by pre-sales as awaiting handoff.
     */
    protected static function backfill_handoff_state() {
        global $wpdb;
        $pre_sales_ids = array_values(array_filter(array_map('absint', get_users(array(
            'role' => 'idlm_pre_sales',
            'fields' => 'ID',
        )))));
        if ($wpdb->last_error) { return false; }
        if (empty($pre_sales_ids)) {
            return true;
        }

        $owner_sql = implode(',', $pre_sales_ids);
        $table_leads = $wpdb->prefix . 'idlm_leads';
        // The owner list contains only absint-normalized IDs.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0240: backfill_handoff_state(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (false === $wpdb->query(
            "UPDATE {$table_leads}
             SET handoff_status = 'awaiting_manager', intake_owner_id = assigned_rep_id
             WHERE handoff_status = 'not_ready' AND lead_quality = 'Qualified'
               AND assigned_rep_id IN ({$owner_sql})
               AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        )) { return false; }
        return true;
    }

    /**
     * Create/Update custom DB tables on plugin activation
     */
    public static function create_tables() {
        return IDLM_Schema_Migration::run(static function () {
            global $wpdb;
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            $statements = self::schema_statements();
            foreach ($statements as $sql) { dbDelta($sql); }
            $result = IDLM_Schema_Migration::verify($statements);
            if (is_wp_error($result)) { return $result; }
            if (!IDLM_Private_Files::install()) { return IDLM_Schema_Migration::error('private_files'); }
            $table_leads = $wpdb->prefix . 'idlm_leads';
            $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';
            // Backfill the qualification clock for leads created before schema 1.0.2.
            // updated_at is the safest available fallback because older records did
            // not store a dedicated qualification transition timestamp.
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0241: create_tables(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if (false === $wpdb->query(
                "UPDATE {$table_leads}
                 SET qualified_at = COALESCE(updated_at, conversion_time, created_at)
                 WHERE lead_quality = 'Qualified' AND qualified_at IS NULL"
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            )) { return IDLM_Schema_Migration::error('legacy_clocks'); }
            // Repair legacy Won records that predate canonical close timestamps.
            // The latest transition to Won is authoritative when a lead was reopened;
            // updated_at is the safest fallback when no historical activity survives.
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0242: create_tables(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            if (false === $wpdb->query(
                "UPDATE {$table_leads} l
                 LEFT JOIN (
                    SELECT lead_id, MAX(created_at) AS won_at
                    FROM {$table_activity}
                    WHERE activity_type = 'stage_change'
                      AND details LIKE CONCAT('%to ', CHAR(39), 'Won', CHAR(39), '%')
                    GROUP BY lead_id
                 ) won_history ON won_history.lead_id = l.id
                 SET l.conversion_time = COALESCE(won_history.won_at, l.updated_at)
                 WHERE l.stage = 'Won'
                   AND (won_history.won_at IS NOT NULL
                        OR l.conversion_time IS NULL
                        OR l.conversion_time < '1000-01-01 00:00:00')"
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            )) { return IDLM_Schema_Migration::error('legacy_clocks'); }
            $reporting_started = IDLM_Schema_Migration::version('idlm_reporting_events_started_at');
            if (null === $reporting_started) { return IDLM_Schema_Migration::error('reporting_marker_read'); }
            if (!$reporting_started) {
                $result = IDLM_Schema_Migration::publish('idlm_reporting_events_started_at', current_time('mysql'));
                if (is_wp_error($result)) { return $result; }
            }
            return IDLM_Schema_Migration::publish('idlm_schema_version', IDLM_DB_VERSION);
        });
    }

    public static function schema_statements() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $statements = array();

        // 1. Leads Table
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $sql_leads = "CREATE TABLE $table_leads (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(100) DEFAULT '',
            last_name varchar(100) DEFAULT '',
            email varchar(100) DEFAULT '',
            phone varchar(50) DEFAULT '',
            project_name varchar(255) DEFAULT '',
            budget varchar(100) DEFAULT '',
            deal_value decimal(12,2) DEFAULT 0.00,
            discount_percentage decimal(5,2) DEFAULT 0.00,
            project_timeline varchar(100) DEFAULT '',
            possession_date date DEFAULT NULL,
            property_location varchar(255) DEFAULT '',
            source varchar(50) DEFAULT '',
            original_source varchar(50) DEFAULT '',
            latest_source varchar(50) DEFAULT '',
            referrer_details text DEFAULT NULL,
            property_type varchar(50) DEFAULT '',
            num_rooms varchar(50) DEFAULT '',
            floor_plan_received tinyint(1) DEFAULT 0,
            floor_plan_file varchar(255) DEFAULT '',
            floor_plan_files longtext DEFAULT NULL,
            quote_shared tinyint(1) DEFAULT 0,
            quote_value decimal(12,2) DEFAULT 0.00,
            quote_file varchar(255) DEFAULT '',
            preferred_studio varchar(100) DEFAULT '',
            stage varchar(50) DEFAULT '',
            lost_reason varchar(100) DEFAULT '',
            call_status varchar(50) DEFAULT '',
            callback_datetime datetime DEFAULT NULL,
            lead_quality varchar(50) DEFAULT '',
            disqualification_reason varchar(50) DEFAULT '',
            disqualification_method varchar(50) DEFAULT '',
            disqualified_at datetime DEFAULT NULL,
            disqualified_by_user_id bigint(20) unsigned DEFAULT 0,
            assigned_rep_id bigint(20) unsigned DEFAULT 0,
            assigned_at datetime DEFAULT NULL,
            utm_source varchar(100) DEFAULT '',
            utm_medium varchar(100) DEFAULT '',
            utm_campaign varchar(100) DEFAULT '',
            utm_adgroup varchar(100) DEFAULT '',
            utm_term varchar(100) DEFAULT '',
            utm_content varchar(100) DEFAULT '',
            gclid varchar(255) DEFAULT '',
            gbraid varchar(255) DEFAULT '',
            wbraid varchar(255) DEFAULT '',
            fbclid varchar(255) DEFAULT '',
            li_fat_id varchar(255) DEFAULT '',
            fb_lead_id varchar(100) DEFAULT NULL,
            landing_page text DEFAULT NULL,
            conversion_time datetime DEFAULT NULL,
            qualified_at datetime DEFAULT NULL,
            qualified_by_user_id bigint(20) unsigned DEFAULT 0,
            intake_owner_id bigint(20) unsigned DEFAULT 0,
            handoff_status varchar(30) DEFAULT 'not_ready',
            handoff_at datetime DEFAULT NULL,
            handoff_method varchar(50) DEFAULT '',
            handoff_exception_reason varchar(255) DEFAULT '',
            is_duplicate tinyint(1) DEFAULT 0,
            duplicate_of_id bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            time_to_connected bigint(20) DEFAULT NULL,
            time_to_won bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY email (email),
            KEY phone (phone),
            KEY source (source),
            KEY original_source (original_source),
            KEY latest_source (latest_source),
            KEY stage (stage),
            KEY lost_reason (lost_reason),
            KEY call_status (call_status),
            KEY assigned_rep_id (assigned_rep_id),
            KEY assigned_at (assigned_at),
            KEY created_at (created_at),
            KEY conversion_time (conversion_time),
            KEY lead_quality (lead_quality),
            KEY disqualification_reason (disqualification_reason),
            KEY disqualified_at (disqualified_at),
            KEY qualified_at (qualified_at),
            KEY qualified_by_user_id (qualified_by_user_id),
            KEY intake_owner_id (intake_owner_id),
            KEY handoff_status (handoff_status),
            KEY duplicate_of_id (duplicate_of_id)
        ) ENGINE=InnoDB $charset_collate;";

        $statements[] = $sql_leads;

        // 2. Activity Log Table
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';
        $sql_activity = "CREATE TABLE $table_activity (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            activity_type varchar(50) NOT NULL,
            details text NOT NULL,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY lead_id (lead_id),
            KEY activity_lead_id (activity_type, lead_id, id),
            KEY lead_activity_id (lead_id, activity_type, id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB $charset_collate;";

        $statements[] = $sql_activity;

        // Immutable reporting events make period-based dashboard metrics
        // truthful even when a lead's current stage or owner changes later.
        $table_state_events = $wpdb->prefix . 'idlm_lead_state_events';
        $sql_state_events = "CREATE TABLE $table_state_events (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            event_type varchar(50) NOT NULL,
            event_origin varchar(50) DEFAULT '',
            from_value varchar(191) DEFAULT '',
            to_value varchar(191) DEFAULT '',
            reason varchar(191) DEFAULT '',
            amount decimal(14,2) DEFAULT NULL,
            actor_user_id bigint(20) unsigned DEFAULT 0,
            occurred_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY lead_event_time (lead_id, event_type, occurred_at),
            KEY event_time (event_type, occurred_at),
            KEY event_target_time (event_type, to_value, occurred_at)
        ) ENGINE=InnoDB $charset_collate;";
        $statements[] = $sql_state_events;

        // 3. Conversion API Log Table
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';
        $sql_capi = "CREATE TABLE $table_capi (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            platform varchar(50) NOT NULL,
            event_name varchar(100) NOT NULL,
            payload text NOT NULL,
            response text NOT NULL,
            status varchar(50) NOT NULL,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY lead_id (lead_id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB $charset_collate;";

        $statements[] = $sql_capi;

        // 4. Audience Sync Log Table
        $table_audience_logs = $wpdb->prefix . 'idlm_audience_sync_logs';
        $sql_audience = "CREATE TABLE $table_audience_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform varchar(50) NOT NULL,
            audience_id varchar(255) NOT NULL,
            lead_count int(11) NOT NULL DEFAULT 0,
            payload longtext NOT NULL,
            response longtext NOT NULL,
            status varchar(50) NOT NULL,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB $charset_collate;";

        $statements[] = $sql_audience;

        // 6. Telemetry & Diagnostics Log Table
        $table_telemetry = $wpdb->prefix . 'idlm_telemetry_logs';
        $sql_telemetry = "CREATE TABLE $table_telemetry (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            log_type varchar(50) NOT NULL,
            severity varchar(20) NOT NULL,
            message text NOT NULL,
            file varchar(255) DEFAULT '',
            line int(11) DEFAULT NULL,
            stack_trace text,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) ENGINE=InnoDB $charset_collate;";

        $statements[] = $sql_telemetry;

        // 7. Tasks & To-Dos Table
        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        $sql_tasks = "CREATE TABLE $table_tasks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            assigned_rep_id bigint(20) unsigned DEFAULT 0,
            title varchar(255) NOT NULL,
            task_type varchar(50) DEFAULT 'General',
            qualification_cycle tinyint unsigned DEFAULT NULL,
            priority varchar(20) DEFAULT 'Medium',
            status varchar(50) DEFAULT 'pending',
            due_date datetime DEFAULT NULL,
            created_by bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY lead_id (lead_id),
            KEY lead_status_due (lead_id, status, due_date),
            UNIQUE KEY lead_qualification_cycle (lead_id, qualification_cycle),
            KEY assigned_rep_id (assigned_rep_id),
            KEY status (status),
            KEY due_date (due_date)
        ) ENGINE=InnoDB $charset_collate;";

        $statements[] = $sql_tasks;

        // 8. Provider-neutral call records. Manual entries and future cloud
        // telephony webhooks share this append-only audit structure.
        $table_calls = $wpdb->prefix . 'idlm_call_logs';
        $sql_calls = "CREATE TABLE $table_calls (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            provider varchar(50) DEFAULT 'manual',
            provider_event_id varchar(191) DEFAULT NULL,
            direction varchar(20) DEFAULT 'outbound',
            outcome varchar(50) DEFAULT '',
            started_at datetime DEFAULT NULL,
            duration_seconds int(11) unsigned DEFAULT 0,
            recording_url text DEFAULT NULL,
            recording_hash varchar(128) DEFAULT '',
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY provider_event (provider, provider_event_id),
            KEY lead_id (lead_id),
            KEY user_id (user_id),
            KEY started_at (started_at)
        ) ENGINE=InnoDB $charset_collate;";

        $statements[] = $sql_calls;

        // 9. Append-only acquisition and form-submission history. Representatives
        // work only on the canonical lead while every channel touch remains auditable.
        $table_touchpoints = $wpdb->prefix . 'idlm_lead_touchpoints';
        $sql_touchpoints = "CREATE TABLE $table_touchpoints (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            platform varchar(30) DEFAULT '',
            source varchar(50) DEFAULT '',
            external_id varchar(191) DEFAULT NULL,
            utm_source varchar(100) DEFAULT '',
            utm_medium varchar(100) DEFAULT '',
            utm_campaign varchar(100) DEFAULT '',
            utm_adgroup varchar(100) DEFAULT '',
            utm_term varchar(100) DEFAULT '',
            utm_content varchar(100) DEFAULT '',
            gclid varchar(255) DEFAULT '',
            gbraid varchar(255) DEFAULT '',
            wbraid varchar(255) DEFAULT '',
            fbclid varchar(255) DEFAULT '',
            li_fat_id varchar(255) DEFAULT '',
            landing_page text DEFAULT NULL,
            payload longtext DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            received_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY platform_external (platform, external_id),
            KEY lead_id (lead_id),
            KEY source (source),
            KEY received_at (received_at)
        ) ENGINE=InnoDB $charset_collate;";

        $statements[] = $sql_touchpoints;

        // Conflicting email/phone matches are retained for administrator review
        // without creating a second lead or adding work to the pre-sales queue.
        $table_inbound_reviews = $wpdb->prefix . 'idlm_inbound_submission_reviews';
        $sql_inbound_reviews = "CREATE TABLE $table_inbound_reviews (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform varchar(30) DEFAULT '',
            source varchar(50) DEFAULT '',
            email varchar(100) DEFAULT '',
            phone varchar(50) DEFAULT '',
            candidate_lead_ids longtext DEFAULT NULL,
            submission_data longtext DEFAULT NULL,
            external_id varchar(191) DEFAULT NULL,
            resolved_lead_id bigint(20) unsigned DEFAULT NULL,
            resolved_by bigint(20) unsigned DEFAULT NULL,
            resolved_at datetime DEFAULT NULL,
            resolution_note text DEFAULT NULL,
            payload longtext DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            received_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            PRIMARY KEY  (id),
            UNIQUE KEY platform_external (platform, external_id),
            KEY resolved_lead (resolved_lead_id),
            KEY status_received (status, received_at),
            KEY received_at (received_at)
        ) ENGINE=InnoDB $charset_collate;";

        $statements[] = $sql_inbound_reviews;
        $statements[] = IDLM_Lead_Outbox::schema();
        return $statements;
    }

    /** Convert common Indian budget notation to rupees for the qualification gate. */
    public static function budget_to_rupees($budget) {
        $value = strtolower(str_replace(array(',', '₹', 'rs.', 'rs', 'inr', ' '), '', (string) $budget));
        if (!preg_match('/([0-9]+(?:\.[0-9]+)?)/', $value, $matches)) {
            return 0;
        }
        $amount = (float) $matches[1];
        if (false !== strpos($value, 'crore') || preg_match('/cr\b/', $value)) {
            return (int) round($amount * 10000000);
        }
        if (false !== strpos($value, 'lakh') || false !== strpos($value, 'lac') || preg_match('/l\b/', $value)) {
            return (int) round($amount * 100000);
        }
        return (int) round($amount);
    }

    public static function get_normalized_working_days() {
        $days = array_map('intval', (array) IDLM_Settings::get('idlm_working_days', array(1, 2, 3, 4, 5, 6)));
        $days = array_map(static function ($day) { return 0 === $day ? 7 : $day; }, $days);
        $days = array_values(array_unique(array_filter($days, static function ($day) { return $day >= 1 && $day <= 7; })));
        return $days ?: array(1, 2, 3, 4, 5, 6);
    }

    /** Return the administrator-configured outreach target for one qualification cycle. */
    public static function get_presales_outreach_targets() {
        return array(
            'calls' => min(20, max(1, intval(IDLM_Settings::get('idlm_presales_calls_per_day', 2)))),
            'whatsapp' => min(20, max(0, intval(IDLM_Settings::get('idlm_presales_whatsapp_per_day', 1)))),
        );
    }

    /**
     * Calculate carried outreach progress from rows grouped by activity date.
     * A calendar date can complete at most one cycle; unfinished progress carries forward.
     */
    public static function calculate_presales_outreach_progress($daily_rows, $targets = null) {
        $targets = is_array($targets) ? $targets : self::get_presales_outreach_targets();
        $active_cycle = array('date' => '', 'calls' => 0, 'whatsapp' => 0, 'complete' => false);
        $completed_cycles = array();
        $closure_count = 0;

        foreach ((array) $daily_rows as $row) {
            $activity_date = is_object($row) ? (string) $row->activity_date : (string) ($row['activity_date'] ?? '');
            $calls = is_object($row) ? (int) $row->calls : (int) ($row['calls'] ?? 0);
            $whatsapp = is_object($row) ? (int) $row->whatsapp : (int) ($row['whatsapp'] ?? 0);
            if (0 === $targets['whatsapp']) {
                $whatsapp = 0;
            }
            $closure_count += is_object($row) ? (int) $row->closure : (int) ($row['closure'] ?? 0);

            if ($calls <= 0 && $whatsapp <= 0) {
                continue;
            }
            if (!$active_cycle['date']) {
                $active_cycle['date'] = $activity_date;
            }
            $active_cycle['calls'] += max(0, $calls);
            $active_cycle['whatsapp'] += max(0, $whatsapp);

            if ($active_cycle['calls'] >= $targets['calls'] && $active_cycle['whatsapp'] >= $targets['whatsapp']) {
                $completed_cycles[] = array(
                    'date' => $activity_date,
                    'calls' => $targets['calls'],
                    'whatsapp' => $targets['whatsapp'],
                    'complete' => true,
                );
                // Surplus attempts belong to the cycle worked on that date. The
                // next cycle begins on a later activity date, never on the same day.
                $active_cycle = array('date' => '', 'calls' => 0, 'whatsapp' => 0, 'complete' => false);
            }
        }

        return array(
            'completed_cycles' => $completed_cycles,
            'active_cycle' => $active_cycle,
            'closure_count' => $closure_count,
        );
    }

    /** Keep existing qualification task labels aligned with the current targets. */
    public static function refresh_presales_task_titles() {
        global $wpdb;
        $targets = self::get_presales_outreach_targets();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0243: refresh_presales_task_titles(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        $tasks = $wpdb->get_results(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT t.id, t.lead_id, t.title, l.lead_quality, l.stage
             FROM {$wpdb->prefix}idlm_tasks t
             INNER JOIN {$wpdb->prefix}idlm_leads l ON l.id = t.lead_id
             WHERE t.task_type = 'Qualification'
             ORDER BY t.lead_id ASC, t.due_date ASC, t.id ASC"
        );
        $lead_ids = array();
        foreach ($tasks as $task) {
            if (!in_array($task->lead_quality, array('Qualified', 'Unqualified'), true) && !in_array($task->stage, array('Won', 'Lost'), true)) {
                $lead_ids[] = absint($task->lead_id);
            }
            if (!preg_match('/Qualification (?:day|cycle) ([0-9]+):/i', (string) $task->title, $matches)) {
                continue;
            }
            $title = sprintf(
                'Qualification cycle %d: log %d call%s and %d WhatsApp',
                absint($matches[1]),
                $targets['calls'],
                1 === $targets['calls'] ? '' : 's',
                $targets['whatsapp']
            );
            if ($title !== $task->title) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0244: refresh_presales_task_titles(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
                $wpdb->update(
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                    $wpdb->prefix . 'idlm_tasks',
                    array('title' => $title, 'updated_at' => current_time('mysql')),
                    array('id' => absint($task->id))
                );
            }
        }
        foreach (array_unique($lead_ids) as $lead_id) {
            self::sync_presales_task_status($lead_id);
        }
    }

    /** Queue-only bounded history reader; carry the same calendar-day policy
     * across 100-date pages without retaining the complete activity history. */
    private static function bounded_followup_progress($lead_id, $request_deadline = null) {
        global $wpdb;
        $last = ''; $deadline = min(microtime(true) + 5, $request_deadline ?? PHP_FLOAT_MAX);
        $targets = self::get_presales_outreach_targets();
        $result = array('completed_cycles'=>array(), 'active_cycle'=>array('date'=>'','calls'=>0,'whatsapp'=>0,'complete'=>false), 'closure_count'=>0);
        do {
            if (microtime(true) > $deadline) { return new WP_Error('idlm_task_history_time', 'Task history took too long. Retry shortly.'); }
            $after = $last !== '' ? $wpdb->prepare(" AND COALESCE(DATE(created_at), '0000-00-00') > %s", $last) : '';
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0245: bounded_followup_progress(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
            $rows = $wpdb->get_results($wpdb->prepare("SELECT DATE(created_at) activity_date,
                SUM(activity_type = 'outbound_call') calls, SUM(activity_type = 'whatsapp_outbound') whatsapp, SUM(activity_type = 'closure_message') closure
                FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id = %d AND activity_type IN ('outbound_call','whatsapp_outbound','closure_message'){$after}
                GROUP BY DATE(created_at) ORDER BY DATE(created_at) ASC LIMIT 100", absint($lead_id)));
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error) { return new WP_Error('idlm_task_history_read', 'Unable to load task history. Retry shortly.'); }
            $count = count($rows);
            if ($count) { $last = end($rows)->activity_date ?: '0000-00-00'; }
            $carry = $result['active_cycle']; $carry['activity_date'] = $carry['date']; $carry['closure'] = 0;
            array_unshift($rows, (object) $carry);
            $page = self::calculate_presales_outreach_progress($rows, $targets);
            $result['completed_cycles'] = array_slice(array_merge($result['completed_cycles'], $page['completed_cycles']), 0, 6);
            $result['active_cycle'] = $page['active_cycle']; $result['closure_count'] += $page['closure_count'];
        } while ($count === 100);
        if (microtime(true) > $deadline) { return new WP_Error('idlm_task_history_time', 'Task history took too long. Retry shortly.'); }
        return $result;
    }

    /** Return the six-day outreach checklist used by the UI and status gates. */
    public static function get_followup_compliance($lead_id, $bounded = false, $request_deadline = null) {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0246: get_followup_compliance(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", absint($lead_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($bounded && $wpdb->last_error) { return new WP_Error('idlm_task_lead_read', 'Unable to load task lead. Retry shortly.'); }
        if (IDLM_Lead_Transaction::active()) { IDLM_Lead_Transaction::read($lead); }
        if (!$lead) {
            return array(
                'eligible' => false,
                'message' => 'Lead not found.',
                'days' => array(),
                'complete_days' => 0,
                'required_days' => 6,
                'targets' => self::get_presales_outreach_targets(),
                'active_cycle' => array('date' => '', 'calls' => 0, 'whatsapp' => 0, 'complete' => false),
                'closure_sent' => false,
            );
        }

        $today = new DateTime('today', wp_timezone());

        if ($bounded) {
            $calculated = self::bounded_followup_progress($lead_id, $request_deadline);
            if (is_wp_error($calculated)) { return $calculated; }
            $targets = self::get_presales_outreach_targets();
        } else {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0247: get_followup_compliance(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
            $rows = $wpdb->get_results($wpdb->prepare(
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT DATE(created_at) activity_date,
                        SUM(activity_type = 'outbound_call') calls,
                        SUM(activity_type = 'whatsapp_outbound') whatsapp,
                        SUM(activity_type = 'closure_message') closure
                 FROM {$wpdb->prefix}idlm_lead_activity_log
                 WHERE lead_id = %d AND activity_type IN ('outbound_call','whatsapp_outbound','closure_message')
                 GROUP BY DATE(created_at)
                 ORDER BY DATE(created_at) ASC",
                absint($lead_id)
            ));

            $targets = self::get_presales_outreach_targets();
            if (IDLM_Lead_Transaction::active()) { IDLM_Lead_Transaction::read($rows); }
            $calculated = self::calculate_presales_outreach_progress($rows, $targets);
        }
        $completed_cycles = $calculated['completed_cycles'];
        $active_cycle = $calculated['active_cycle'];
        $closure_count = (int) $calculated['closure_count'];
        $complete_days = min(6, count($completed_cycles));
        $days = array_slice($completed_cycles, 0, 6);
        if (count($days) < 6) {
            $active_cycle['date'] = $active_cycle['date'] ?: $today->format('Y-m-d');
            $days[] = $active_cycle;
        }
        while (count($days) < 6) {
            $days[] = array('date' => '', 'calls' => 0, 'whatsapp' => 0, 'complete' => false);
        }
        $eligible = 6 === $complete_days && $closure_count > 0;

        return array(
            'eligible' => $eligible,
            'days' => $days,
            'complete_days' => $complete_days,
            'required_days' => 6,
            'targets' => $targets,
            'active_cycle' => $active_cycle,
            'closure_sent' => $closure_count > 0,
            'message' => $eligible ? 'Six outreach cycles and closure are complete.' : sprintf('%d of 6 outreach cycles complete; final closure message: %s.', $complete_days, $closure_count > 0 ? 'logged' : 'not logged'),
        );
    }

    /** Fill missing stable qualification cycles; keep the public facade. */
    public static function create_presales_followup_tasks($lead_id, $assigned_rep_id) {
        return IDLM_Presales_Task_Plan::ensure($lead_id, $assigned_rep_id);
    }

    /** Ensure every completed qualified handoff creates visible sales work. */
    public static function ensure_qualified_handoff_task($lead_id, $assigned_rep_id) {
        global $wpdb;
        $lead_id = absint($lead_id);
        $assigned_rep_id = absint($assigned_rep_id);
        if (!$lead_id || !$assigned_rep_id) {
            return false;
        }

        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0248: ensure_qualified_handoff_task(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_tasks}
             WHERE lead_id = %d AND assigned_rep_id = %d AND status = 'pending'
               AND task_type = 'Sales Follow-up' LIMIT 1",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $lead_id,
            $assigned_rep_id
        ));
        if ($wpdb->last_error) { return false; }
        if ($existing) {
            return true;
        }

        $now = current_time('mysql');
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0249: ensure_qualified_handoff_task(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        return 1 === $wpdb->insert($table_tasks, array(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            'lead_id' => $lead_id,
            'assigned_rep_id' => $assigned_rep_id,
            'title' => 'Contact newly assigned qualified lead',
            'task_type' => 'Sales Follow-up',
            'priority' => 'High',
            'status' => 'pending',
            'due_date' => $now,
            'created_by' => get_current_user_id(),
            'created_at' => $now,
            'updated_at' => $now,
        ));
    }

    /** Reconcile qualification cycles from outreach evidence, then roll pending dates. */
    public static function sync_presales_task_status($lead_id) {
        return IDLM_Presales_Task_Plan::sync($lead_id);
    }

    public static function reschedule_pending_presales_tasks($lead_id) {
        return IDLM_Presales_Task_Plan::sync($lead_id);
    }

    /** Bounded, checkpointed repair also discovers assigned leads with no task rows. */
    public static function roll_forward_presales_tasks() {
        return IDLM_Presales_Task_Plan::sweep();
    }

    protected static function validate_quality_transition($lead_id, $old_lead, $data, $explicit_quality_update = true) {
        $qualified_stages = IDLM_State_Registry::qualification_validation_stages();
        $requires_qualification_validation = isset($data['stage'])
            && in_array($data['stage'], $qualified_stages, true)
            && !in_array($old_lead->stage, $qualified_stages, true);
        if (empty($data['lead_quality']) || ($data['lead_quality'] === $old_lead->lead_quality && !$requires_qualification_validation)) {
            return true;
        }
        $is_structured_early_close = 'early_close' === ($data['disqualification_method'] ?? '');
        if ('Unqualified' === $data['lead_quality'] && !$is_structured_early_close) {
            $compliance = self::get_followup_compliance($lead_id);
            if (!$compliance['eligible']) {
                self::$last_validation_error = 'Cannot mark Unqualified: ' . $compliance['message'] . ' Use the manager-only junk override for a genuinely invalid lead.';
                return false;
            }
        }
        if ('Qualified' === $data['lead_quality']) {
            $candidate = array_merge((array) $old_lead, $data);
            $required = array(
                'project_name' => 'project name', 'property_location' => 'location', 'budget' => 'budget',
                'possession_date' => 'possession date', 'property_type' => 'project type', 'project_timeline' => 'timeline',
            );
            $missing = array();
            foreach ($required as $key => $label) {
                if (empty($candidate[$key])) {
                    $missing[] = $label;
                }
            }
            if ($explicit_quality_update && empty($candidate['floor_plan_received']) && empty(trim((string) ($candidate['qualification_handoff_remark'] ?? '')))) {
                self::$last_validation_error = 'Add a handoff remark before qualifying a lead without a floor plan.';
                return false;
            }
            if ($missing) {
                self::$last_validation_error = 'Cannot qualify this lead. Complete: ' . implode(', ', $missing) . '.';
                return false;
            }
            if (self::budget_to_rupees($candidate['budget']) < 400000) {
                self::$last_validation_error = 'Cannot qualify this lead: the minimum project budget is ₹4,00,000.';
                return false;
            }
        }
        return true;
    }

    /**
     * Create or enrich the one canonical lead used by inbound integrations.
     *
     * @return array{lead_id:int,created:bool,matched:bool}|false
     */

    /**
     * Route inbound lead persistence through the lifecycle service.
     */
    public static function upsert_inbound_lead($data, $platform, $payload = array(), $options = array()) {
        return IDLM_Lead_Lifecycle_Service::upsert_inbound_lead($data, $platform, $payload, $options);
    }

    /**
     * Route lead creation through the lifecycle service.
     */
    public static function insert_lead($data) {
        $binding = IDLM_Private_Files::validate_fields(0, $data);
        if (is_wp_error($binding)) { self::$last_validation_error = $binding->get_error_message(); return 0; }
        return IDLM_Lead_Lifecycle_Service::insert_lead($data);
    }

    /**
     * Route lead updates through the lifecycle service.
     */
    public static function update_lead($lead_id, $data) {
        $binding = IDLM_Private_Files::validate_fields($lead_id, $data);
        if (is_wp_error($binding)) { self::$last_validation_error = $binding->get_error_message(); return false; }
        $updated = IDLM_Lead_Lifecycle_Service::update_lead($lead_id, $data);
        if (false !== $updated && array_intersect(array_keys($data), array('floor_plan_file','floor_plan_files','quote_file'))) { IDLM_Private_Files::reconcile_lead($lead_id); }
        return $updated;
    }

    /**
     * Preserve the upgrade hook while delegating lifecycle-specific backfills.
     */
    protected static function backfill_duplicate_touchpoints() {
        return IDLM_Lead_Lifecycle_Service::backfill_duplicate_touchpoints();
    }

    /**
     * Find the canonical non-duplicate lead matching an existing record.
     */
    public static function find_canonical_duplicate_id($lead_id) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0250: find_canonical_duplicate_id(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        $lead = $wpdb->get_row($wpdb->prepare("SELECT id, email, phone, duplicate_of_id FROM {$table_leads} WHERE id = %d", $lead_id));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!$lead) {
            return 0;
        }
        if (!empty($lead->duplicate_of_id)) {
            return absint($lead->duplicate_of_id);
        }

        $email = strtolower(trim((string) $lead->email));
        $phone = preg_replace('/[^0-9]/', '', (string) $lead->phone);
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0251: find_canonical_duplicate_id(): canonical prefixed schema/tables; fixed migration/task predicates and absint ID lists; numeric/string/date values bound or wpdb CRUD escaped; lifecycle/task/schema checks cannot use stale per-query data.
        $candidates = $wpdb->get_results($wpdb->prepare(
            "SELECT id, email, phone FROM {$table_leads} WHERE id <> %d AND is_duplicate = 0 ORDER BY id ASC",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $lead_id
        ));
        foreach ($candidates as $candidate) {
            if ($email && $email === strtolower(trim((string) $candidate->email))) {
                return absint($candidate->id);
            }
            $candidate_phone = preg_replace('/[^0-9]/', '', (string) $candidate->phone);
            if ($phone && $candidate_phone && ($phone === $candidate_phone || (strlen($phone) >= 10 && strlen($candidate_phone) >= 10 && substr($phone, -10) === substr($candidate_phone, -10)))) {
                return absint($candidate->id);
            }
        }
        return 0;
    }

    /**
     * Log a lead activity
     */
    public static function log_activity($lead_id, $activity_type, $details, $user_id = 0) {
        global $wpdb;
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return 1 === $wpdb->insert($table_activity, array(
            'lead_id' => $lead_id,
            'user_id' => $user_id,
            'activity_type' => $activity_type,
            'details' => $details,
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * Record a compact immutable lifecycle event for truthful event reporting.
     * This deliberately stores no contact PII; a transition may retain the
     * numeric value recorded at that moment for revenue reporting.
     */
    public static function record_reporting_event($lead_id, $event_type, $from_value, $to_value, $reason = '', $event_origin = '', $actor_user_id = 0, $occurred_at = null, $amount = null) {
        global $wpdb;

        $lead_id = absint($lead_id);
        $event_type = sanitize_key($event_type);
        if (!$lead_id || !in_array($event_type, array('stage_change', 'assignment'), true)) {
            return false;
        }
        if (!$actor_user_id) {
            $actor_user_id = get_current_user_id();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return 1 === $wpdb->insert($wpdb->prefix . 'idlm_lead_state_events', array(
            'lead_id' => $lead_id,
            'event_type' => $event_type,
            'event_origin' => sanitize_key($event_origin),
            'from_value' => sanitize_text_field((string) $from_value),
            'to_value' => sanitize_text_field((string) $to_value),
            'reason' => sanitize_text_field((string) $reason),
            'amount' => null === $amount ? null : round((float) $amount, 2),
            'actor_user_id' => absint($actor_user_id),
            'occurred_at' => $occurred_at ? sanitize_text_field((string) $occurred_at) : current_time('mysql'),
        ));
    }

    /**
     * Log a Conversion API event
     */
    public static function log_capi_event($lead_id, $platform, $event_name, $payload, $response, $status) {
        global $wpdb;
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $saved = $wpdb->insert($table_capi, array(
            'lead_id' => $lead_id,
            'platform' => $platform,
            'event_name' => $event_name,
            'payload' => maybe_serialize(IDLM_Data_Retention::redact($payload)),
            'response' => maybe_serialize(IDLM_Data_Retention::redact($response)),
            'status' => $status,
            'created_at' => current_time('mysql')
        ));
        return 1 === $saved ? (int) $wpdb->insert_id : 0;
    }

    /**
     * Log an Audience Sync event
     */
    public static function log_audience_sync($platform, $audience_id, $lead_count, $payload, $response, $status) {
        global $wpdb;
        $table_audience_logs = $wpdb->prefix . 'idlm_audience_sync_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $saved = $wpdb->insert($table_audience_logs, array(
            'platform' => $platform,
            'audience_id' => $audience_id,
            'lead_count' => $lead_count,
            'payload' => maybe_serialize(IDLM_Data_Retention::summarize_audience_payload($payload)),
            'response' => maybe_serialize(IDLM_Data_Retention::redact($response)),
            'status' => $status,
            'created_at' => current_time('mysql')
        ));
        return 1 === $saved ? (int) $wpdb->insert_id : 0;
    }
}
