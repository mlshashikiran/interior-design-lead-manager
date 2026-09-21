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
        // Keep schema migrations independent from UI/plugin release versions.
        // Older releases stored IDLM_VERSION in idlm_db_version, which caused
        // dbDelta() to run during every release, including wp-login.php requests.
        $saved_schema_version = get_option('idlm_schema_version', '');

        if (empty($saved_schema_version)) {
            $legacy_db_version = get_option('idlm_db_version', '');

            // Plugin version 1.14.0 matched schema 1.0.0. Adopt that baseline
            // first so newer schema migrations still run normally.
            if (!empty($legacy_db_version) && version_compare($legacy_db_version, '1.14.0', '>=')) {
                $saved_schema_version = '1.0.0';
                update_option('idlm_schema_version', $saved_schema_version);
            }
        }

        if (empty($saved_schema_version) || version_compare($saved_schema_version, IDLM_DB_VERSION, '<')) {
            self::create_tables();
            update_option('idlm_schema_version', IDLM_DB_VERSION);
        }

        if ('1.0.0' !== get_option('idlm_handoff_state_version', '')) {
            if (self::backfill_handoff_state()) {
                update_option('idlm_handoff_state_version', '1.0.0');
            }
        }

        if ('1.0.0' !== get_option('idlm_duplicate_quality_version', '')) {
            self::backfill_duplicate_early_close_quality();
            update_option('idlm_duplicate_quality_version', '1.0.0');
        }

        if ('1.0.0' !== get_option('idlm_workflow_integrity_version', '')) {
            self::repair_workflow_integrity();
            update_option('idlm_workflow_integrity_version', '1.0.0');
        }

        if ('1.0.0' !== get_option('idlm_assignment_timestamp_version', '')) {
            if (self::backfill_assignment_timestamps()) {
                update_option('idlm_assignment_timestamp_version', '1.0.0');
            }
        }

        if ('1.0.0' !== get_option('idlm_attribution_touchpoint_backfill_version', '')) {
            self::backfill_duplicate_touchpoints();
            update_option('idlm_attribution_touchpoint_backfill_version', '1.0.0');
        }

        // conversion_time is the canonical timestamp of a current Won lead.
        // Older imports populated it for other stages, risking false closes in
        // any report that filters by this timestamp.
        if ('1.0.0' !== get_option('idlm_conversion_time_integrity_version', '')) {
            if (self::clear_non_won_conversion_times()) {
                update_option('idlm_conversion_time_integrity_version', '1.0.0');
            }
        }

        // The daily queue must never inherit work from terminal leads or
        // accounts that no longer exist. This is intentionally a one-time,
        // idempotent archive rather than a destructive delete.
        if ('1.0.0' !== get_option('idlm_task_queue_cleanup_version', '')) {
            self::cleanup_stale_pending_tasks();
            update_option('idlm_task_queue_cleanup_version', '1.0.0');
        }
    }

    /** Archive pending work which cannot be actioned safely. */
    public static function cleanup_stale_pending_tasks() {
        global $wpdb;
        $tasks = $wpdb->prefix . 'idlm_tasks';
        $leads = $wpdb->prefix . 'idlm_leads';
        $users = $wpdb->users;
        $now = current_time('mysql');

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $lost = $wpdb->query($wpdb->prepare(
            "UPDATE {$tasks} t INNER JOIN {$leads} l ON l.id = t.lead_id
             SET t.status = 'cancelled', t.completed_at = NULL, t.updated_at = %s
             WHERE t.status = 'pending' AND l.stage = 'Lost'",
            $now
        ));
        // A task can retain an obsolete task owner even when its lead is still
        // active. Archive it for a manager to recreate/reassign deliberately.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $orphaned = $wpdb->query($wpdb->prepare(
            "UPDATE {$tasks} t LEFT JOIN {$users} u ON u.ID = t.assigned_rep_id
             SET t.status = 'cancelled', t.completed_at = NULL, t.updated_at = %s
             WHERE t.status = 'pending' AND t.assigned_rep_id > 0 AND u.ID IS NULL",
            $now
        ));

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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->query(
            "UPDATE {$leads}
             SET conversion_time = NULL
             WHERE COALESCE(stage, '') <> 'Won'
               AND conversion_time IS NOT NULL"
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
        );

        return false !== $result;
    }

    /**
     * Repair duplicate early closures created before version 1.40.11.
     */
    protected static function backfill_duplicate_early_close_quality() {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            "UPDATE {$table_leads}
             SET lead_quality = 'Unqualified'
             WHERE is_duplicate = 1
               AND disqualification_method = 'early_close'
               AND disqualification_reason = 'duplicate'
               AND (lead_quality = '' OR lead_quality IS NULL)"
        );
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare(
            "UPDATE {$leads}
             SET lead_quality = 'Unqualified', stage = 'Lost', lost_reason = 'Duplicate or Invalid Lead',
                 disqualification_reason = 'duplicate', disqualification_method = 'duplicate_detection',
                 disqualified_at = COALESCE(disqualified_at, updated_at, created_at, %s),
                 handoff_status = 'not_ready', handoff_at = NULL, handoff_method = '', handoff_exception_reason = ''
             WHERE is_duplicate = 1 AND duplicate_of_id > 0",
            $now
        ));

        // Every Unqualified decision is terminal, not an active-pipeline record.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
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
        );

        // A Qualified quality decision cannot remain in a pre-qualification stage.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("UPDATE {$leads} SET stage = 'Qualified' WHERE lead_quality = 'Qualified' AND stage IN ('', 'New', 'Contacted')");

        // Advanced sales stages require a qualified lead unless the record was explicitly closed.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare(
            "UPDATE {$leads}
             SET lead_quality = 'Qualified', qualified_at = COALESCE(qualified_at, updated_at, created_at, %s),
                 qualified_by_user_id = COALESCE(NULLIF(qualified_by_user_id, 0), 0),
                 disqualification_reason = '', disqualification_method = '', disqualified_at = NULL, disqualified_by_user_id = 0
             WHERE is_duplicate = 0 AND stage IN ('Qualified', 'Quote Shared', 'Negotiation', 'Won')
               AND COALESCE(lead_quality, '') <> 'Qualified' AND COALESCE(disqualification_method, '') <> 'early_close'",
            $now
        ));

        // Legacy terminal stages missing their required commercial values cannot stay terminal.
        // Downgrade them to the nearest valid state without inventing revenue or quote amounts.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("UPDATE {$leads} SET stage = 'Negotiation', conversion_time = NULL WHERE stage = 'Won' AND COALESCE(deal_value, 0) <= 0");
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("UPDATE {$leads} SET stage = 'Qualified' WHERE stage = 'Quote Shared' AND (COALESCE(quote_shared, 0) = 0 OR COALESCE(quote_value, 0) <= 0)");

        // Preserve historical Unqualified decisions with explicit legacy audit metadata.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query($wpdb->prepare(
            "UPDATE {$leads}
             SET disqualification_reason = COALESCE(NULLIF(disqualification_reason, ''), 'legacy_unqualified'),
                 disqualification_method = COALESCE(NULLIF(disqualification_method, ''), 'legacy_import'),
                 disqualified_at = COALESCE(disqualified_at, updated_at, created_at, %s)
             WHERE lead_quality = 'Unqualified'",
            $now
        ));

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query("DELETE t FROM {$tasks} t LEFT JOIN {$leads} l ON l.id = t.lead_id WHERE l.id IS NULL");
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
        $qualified_stages = array(
            IDLM_State_Registry::STAGE_QUALIFIED, IDLM_State_Registry::STAGE_VISITED,
            IDLM_State_Registry::STAGE_QUOTE_SHARED, IDLM_State_Registry::STAGE_NEGOTIATION,
            IDLM_State_Registry::STAGE_WON,
        );

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
        if (empty($pre_sales_ids)) {
            return false;
        }

        $owner_sql = implode(',', $pre_sales_ids);
        $table_leads = $wpdb->prefix . 'idlm_leads';
        // The owner list contains only absint-normalized IDs.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $wpdb->query(
            "UPDATE {$table_leads}
             SET handoff_status = 'awaiting_manager', intake_owner_id = assigned_rep_id
             WHERE handoff_status = 'not_ready' AND lead_quality = 'Qualified'
               AND assigned_rep_id IN ({$owner_sql})
               AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
        );
        return true;
    }

    /**
     * Create/Update custom DB tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

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
        ) $charset_collate;";

        dbDelta($sql_leads);

        // Backfill the qualification clock for leads created before schema 1.0.2.
        // updated_at is the safest available fallback because older records did
        // not store a dedicated qualification transition timestamp.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            "UPDATE {$table_leads}
             SET qualified_at = COALESCE(updated_at, conversion_time, created_at)
             WHERE lead_quality = 'Qualified' AND qualified_at IS NULL"
        );

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
        ) $charset_collate;";

        dbDelta($sql_activity);

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
        ) $charset_collate;";
        dbDelta($sql_state_events);
        if (!get_option('idlm_reporting_events_started_at', '')) {
            update_option('idlm_reporting_events_started_at', current_time('mysql'), false);
        }

        // Repair legacy Won records that predate canonical close timestamps.
        // The latest transition to Won is authoritative when a lead was reopened;
        // updated_at is the safest fallback when no historical activity survives.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
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
        );

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
        ) $charset_collate;";

        dbDelta($sql_capi);

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
        ) $charset_collate;";

        dbDelta($sql_audience);

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
        ) $charset_collate;";

        dbDelta($sql_telemetry);

        // 7. Tasks & To-Dos Table
        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        $sql_tasks = "CREATE TABLE $table_tasks (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            assigned_rep_id bigint(20) unsigned DEFAULT 0,
            title varchar(255) NOT NULL,
            task_type varchar(50) DEFAULT 'General',
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
            KEY assigned_rep_id (assigned_rep_id),
            KEY status (status),
            KEY due_date (due_date)
        ) $charset_collate;";

        dbDelta($sql_tasks);

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
        ) $charset_collate;";

        dbDelta($sql_calls);

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
        ) $charset_collate;";

        dbDelta($sql_touchpoints);

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
            payload longtext DEFAULT NULL,
            submitted_at datetime DEFAULT NULL,
            received_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            PRIMARY KEY  (id),
            KEY status_received (status, received_at),
            KEY received_at (received_at)
        ) $charset_collate;";

        dbDelta($sql_inbound_reviews);
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

    protected static function get_normalized_working_days() {
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
        $tasks = $wpdb->get_results(
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
                $wpdb->update(
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

    /** Return the six-day outreach checklist used by the UI and status gates. */
    public static function get_followup_compliance($lead_id) {
        global $wpdb;
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", absint($lead_id)));
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

        $rows = $wpdb->get_results($wpdb->prepare(
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
        $calculated = self::calculate_presales_outreach_progress($rows, $targets);
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

    /** Create one qualification checklist task for each of the six outreach cycles. */
    public static function create_presales_followup_tasks($lead_id, $assigned_rep_id) {
        global $wpdb;
        $lead_id = absint($lead_id);
        $assigned_rep_id = absint($assigned_rep_id);
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}idlm_tasks WHERE lead_id = %d AND task_type = 'Qualification' LIMIT 1",
            $lead_id
        ));
        if ($exists) {
            return;
        }
        $lead_created = $wpdb->get_var($wpdb->prepare("SELECT created_at FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
        $working_days = self::get_normalized_working_days();
        $work_end = substr((string) IDLM_Settings::get('idlm_working_hours_end', '18:00'), 0, 5);
        $cursor = new DateTime($lead_created ?: current_time('mysql'), wp_timezone());
        $cursor->setTime(0, 0, 0);
        $created = 0;
        while ($created < 6) {
            if (in_array((int) $cursor->format('N'), $working_days, true)) {
                $created++;
                $due = $cursor->format('Y-m-d') . ' ' . $work_end . ':00';
                $wpdb->insert($wpdb->prefix . 'idlm_tasks', array(
                    'lead_id' => $lead_id,
                    'assigned_rep_id' => $assigned_rep_id,
                    'title' => sprintf(
                        'Qualification cycle %d: log %d call%s and %d WhatsApp',
                        $created,
                        self::get_presales_outreach_targets()['calls'],
                        1 === self::get_presales_outreach_targets()['calls'] ? '' : 's',
                        self::get_presales_outreach_targets()['whatsapp']
                    ),
                    'task_type' => 'Qualification',
                    'priority' => 'High',
                    'status' => 'pending',
                    'due_date' => $due,
                    'created_by' => 0,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ));
            }
            $cursor->modify('+1 day');
        }
        self::log_activity($lead_id, 'task', 'Created the six-cycle pre-sales qualification task plan.', 0);
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
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_tasks}
             WHERE lead_id = %d AND assigned_rep_id = %d AND status = 'pending'
               AND task_type = 'Sales Follow-up' LIMIT 1",
            $lead_id,
            $assigned_rep_id
        ));
        if ($existing) {
            return true;
        }

        $now = current_time('mysql');
        return false !== $wpdb->insert($table_tasks, array(
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

    /** Complete today's qualification task when its required outreach is logged. */
    public static function sync_presales_task_status($lead_id) {
        global $wpdb;
        $progress = self::get_followup_compliance($lead_id);
        $completed_task_count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id = %d AND task_type = 'Qualification' AND status = 'completed'",
            absint($lead_id)
        ));
        while ($completed_task_count > (int) $progress['complete_days']) {
            $task_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}idlm_tasks
                 WHERE lead_id = %d AND task_type = 'Qualification' AND status = 'completed'
                 ORDER BY due_date DESC, id DESC LIMIT 1",
                absint($lead_id)
            ));
            if (!$task_id) {
                break;
            }
            $wpdb->update($wpdb->prefix . 'idlm_tasks', array(
                'status' => 'pending',
                'completed_at' => null,
                'updated_at' => current_time('mysql'),
            ), array('id' => absint($task_id)));
            $completed_task_count--;
        }
        while ($completed_task_count < (int) $progress['complete_days']) {
            $task_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}idlm_tasks
                 WHERE lead_id = %d AND task_type = 'Qualification' AND status = 'pending'
                 ORDER BY due_date ASC, id ASC LIMIT 1",
                absint($lead_id)
            ));
            if ($task_id) {
                $wpdb->update($wpdb->prefix . 'idlm_tasks', array(
                    'status' => 'completed',
                    'completed_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ), array('id' => absint($task_id)));
                $completed_task_count++;
            } else {
                break;
            }
        }
        self::reschedule_pending_presales_tasks($lead_id);
    }

    /** Roll unfinished outreach forward without allowing multiple cycles on one day. */
    public static function reschedule_pending_presales_tasks($lead_id) {
        global $wpdb;
        $task_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}idlm_tasks
             WHERE lead_id = %d AND task_type = 'Qualification' AND status = 'pending'
             ORDER BY due_date ASC, id ASC",
            absint($lead_id)
        ));
        if (!$task_ids) {
            return;
        }
        $working_days = self::get_normalized_working_days();
        $work_end = substr((string) IDLM_Settings::get('idlm_working_hours_end', '18:00'), 0, 5);
        $cursor = new DateTime('today', wp_timezone());
        $progress = self::get_followup_compliance($lead_id);
        $last_completed_cycle = !empty($progress['complete_days']) ? $progress['days'][$progress['complete_days'] - 1] : null;
        if ($last_completed_cycle && current_time('Y-m-d') === $last_completed_cycle['date'] && empty($progress['active_cycle']['calls']) && empty($progress['active_cycle']['whatsapp'])) {
            $cursor->modify('+1 day');
        }
        foreach ($task_ids as $task_id) {
            while (!in_array((int) $cursor->format('N'), $working_days, true)) {
                $cursor->modify('+1 day');
            }
            $wpdb->update($wpdb->prefix . 'idlm_tasks', array(
                'due_date' => $cursor->format('Y-m-d') . ' ' . $work_end . ':00',
                'updated_at' => current_time('mysql'),
            ), array('id' => absint($task_id)));
            $cursor->modify('+1 day');
        }
    }

    /** Periodic recovery keeps dormant lead task dates aligned with the next working days. */
    public static function roll_forward_presales_tasks() {
        global $wpdb;
        $lead_ids = $wpdb->get_col(
            "SELECT DISTINCT t.lead_id FROM {$wpdb->prefix}idlm_tasks t
             INNER JOIN {$wpdb->prefix}idlm_leads l ON l.id = t.lead_id
             WHERE t.task_type = 'Qualification' AND t.status = 'pending'
               AND l.lead_quality NOT IN ('Qualified', 'Unqualified') AND l.stage NOT IN ('Won', 'Lost')
             ORDER BY t.lead_id ASC LIMIT 500"
        );
        foreach ($lead_ids as $lead_id) {
            self::reschedule_pending_presales_tasks($lead_id);
        }
    }

    protected static function validate_quality_transition($lead_id, $old_lead, $data) {
        $qualified_stages = array(
            IDLM_State_Registry::STAGE_QUALIFIED, IDLM_State_Registry::STAGE_VISITED,
            IDLM_State_Registry::STAGE_QUOTE_SHARED, IDLM_State_Registry::STAGE_NEGOTIATION,
            IDLM_State_Registry::STAGE_WON,
        );
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
            if (empty($candidate['floor_plan_received']) && empty(trim((string) ($candidate['qualification_handoff_remark'] ?? '')))) {
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
    public static function upsert_inbound_lead($data, $platform, $payload = array()) {
        return IDLM_Lead_Lifecycle_Service::upsert_inbound_lead($data, $platform, $payload);
    }

    /**
     * Route lead creation through the lifecycle service.
     */
    public static function insert_lead($data) {
        return IDLM_Lead_Lifecycle_Service::insert_lead($data);
    }

    /**
     * Route lead updates through the lifecycle service.
     */
    public static function update_lead($lead_id, $data) {
        return IDLM_Lead_Lifecycle_Service::update_lead($lead_id, $data);
    }

    /**
     * Preserve the upgrade hook while delegating lifecycle-specific backfills.
     */
    protected static function backfill_duplicate_touchpoints() {
        IDLM_Lead_Lifecycle_Service::backfill_duplicate_touchpoints();
    }

    /**
     * Find the canonical non-duplicate lead matching an existing record.
     */
    public static function find_canonical_duplicate_id($lead_id) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $lead = $wpdb->get_row($wpdb->prepare("SELECT id, email, phone, duplicate_of_id FROM {$table_leads} WHERE id = %d", $lead_id));
        if (!$lead) {
            return 0;
        }
        if (!empty($lead->duplicate_of_id)) {
            return absint($lead->duplicate_of_id);
        }

        $email = strtolower(trim((string) $lead->email));
        $phone = preg_replace('/[^0-9]/', '', (string) $lead->phone);
        $candidates = $wpdb->get_results($wpdb->prepare(
            "SELECT id, email, phone FROM {$table_leads} WHERE id <> %d AND is_duplicate = 0 ORDER BY id ASC",
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
        $wpdb->insert($table_activity, array(
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
        return false !== $wpdb->insert($wpdb->prefix . 'idlm_lead_state_events', array(
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
        $wpdb->insert($table_capi, array(
            'lead_id' => $lead_id,
            'platform' => $platform,
            'event_name' => $event_name,
            'payload' => maybe_serialize(IDLM_Data_Retention::redact($payload)),
            'response' => maybe_serialize(IDLM_Data_Retention::redact($response)),
            'status' => $status,
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * Log an Audience Sync event
     */
    public static function log_audience_sync($platform, $audience_id, $lead_count, $payload, $response, $status) {
        global $wpdb;
        $table_audience_logs = $wpdb->prefix . 'idlm_audience_sync_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert($table_audience_logs, array(
            'platform' => $platform,
            'audience_id' => $audience_id,
            'lead_count' => $lead_count,
            'payload' => maybe_serialize(IDLM_Data_Retention::summarize_audience_payload($payload)),
            'response' => maybe_serialize(IDLM_Data_Retention::redact($response)),
            'status' => $status,
            'created_at' => current_time('mysql')
        ));
    }
}
