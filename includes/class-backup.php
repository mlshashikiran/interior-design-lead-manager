<?php
/** Administrator-only IDLM data backup and restore. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Backup {
    const FORMAT = 'idlm-backup';
    const VERSION = 1;
    const MAX_FILE_SIZE = 104857600; // 100 MB.

    public static function init() {
        add_action('admin_post_idlm_export_backup', array(__CLASS__, 'export'));
        add_action('admin_post_idlm_restore_backup', array(__CLASS__, 'restore'));
    }

    /** Tables are static plugin-owned names, ordered for a safe restore. */
    private static function table_suffixes() {
        return array(
            'idlm_leads', 'idlm_lead_activity_log', 'idlm_lead_state_events', 'idlm_tasks', 'idlm_lead_touchpoints', 'idlm_inbound_submission_reviews', 'idlm_call_logs',
            'idlm_capi_logs', 'idlm_audience_sync_logs', 'idlm_telemetry_logs', 'idlm_routing_state',
            'idlm_quote_catalogue_items', 'idlm_quote_item_categories', 'idlm_quote_item_units', 'idlm_quotes',
            'idlm_quote_revisions', 'idlm_quote_revision_sections', 'idlm_quote_revision_items', 'idlm_quote_payment_milestones',
            'idlm_quote_status_history', 'idlm_number_sequences',
        );
    }

    private static function authorize($action) {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.', '', array('response' => 403));
        }
        check_admin_referer($action);
    }

    private static function table_name($suffix) {
        global $wpdb;
        return $wpdb->prefix . $suffix;
    }

    private static function core_migration_option($name) {
        return in_array(strtolower($name), array('idlm_handoff_state_version', 'idlm_duplicate_quality_version', 'idlm_workflow_integrity_version', 'idlm_assignment_timestamp_version', 'idlm_attribution_touchpoint_backfill_version', 'idlm_conversion_time_integrity_version', 'idlm_task_queue_cleanup_version', 'idlm_reporting_events_started_at'), true);
    }

    private static function backup_option($option_name) {
        $option_name = strtolower($option_name); // Match protected names under native case-insensitive option collation.
        if (0 !== strpos($option_name, 'idlm_') || self::core_migration_option($option_name) || 'idlm_duplicate_touchpoint_backfill_cursor_v2' === $option_name) { return false; }
        if (in_array($option_name, array('idlm_retention_progress', 'idlm_quotation_migration_status', 'idlm_crm_access_policy_version', 'idlm_read_cache_generation', 'idlm_private_file_schema_version', 'idlm_private_file_migration_cursor', 'idlm_private_file_migration_status', 'idlm_schema_version', 'idlm_quotation_schema_version', 'idlm_plugin_activated_at', 'idlm_roles_version', 'idlm_legacy_quote_migration_version', 'idlm_quotation_snapshot_migration_version'), true)) { return false; }
        if (preg_match('/^idlm_(?:facebook_pending_|connector_|recovery_|rc_|fallback_last_|interakt_batch_|interakt_dispatch_|capi_retry_|capi_delivery_|audience_work_|audience_scan$|whatsapp_receipt_|audience_sync_queue$|.*(?:migration|schema|retention|payload).*version$|.*_lock_|.*rate_limit)/', $option_name)) { return false; }
        return !preg_match('/(?:_api_key|_access_token|_password|_secret|_verify_token|credential)/i', $option_name);
    }

    private static function failure() {
        return new WP_Error('idlm_backup_invalid', 'Backup or recovery failed. No CRM data was changed.');
    }

    /** Fail closed on missing/extra columns, nontransactional storage or lost uniques. */
    private static function physical_schema() {
        global $wpdb;
        if (is_wp_error(IDLM_Schema_Migration::verify(array(IDLM_Lead_Outbox::schema())))) { return self::failure(); }
        $schema = array();
        $uniques = array('idlm_quotes'=>array('quote_number','save_request_key'), 'idlm_quote_revisions'=>array('quote_id,revision_number','save_request_key'), 'idlm_number_sequences'=>array('sequence_type,sequence_year'), 'idlm_quote_catalogue_items'=>array('item_code'), 'idlm_quote_item_categories'=>array('category_name'), 'idlm_quote_item_units'=>array('unit_name'), 'idlm_call_logs'=>array('provider,provider_event_id'), 'idlm_lead_touchpoints'=>array('platform,external_id'));
        foreach (array_merge(self::table_suffixes(), array('options','users')) as $suffix) {
            $table = in_array($suffix, array('options','users'), true) ? $wpdb->$suffix : self::table_name($suffix);
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0278: physical_schema(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error || strtoupper((string) $engine) !== 'INNODB') { return self::failure(); }
            if (!isset(IDLM_Backup_Validator::columns()[$suffix])) {
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0279: physical_schema(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
                $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A); $primary = array(); $names = array();
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ($wpdb->last_error) { return self::failure(); }
                foreach ($indexes as $index) {
                    if ($index['Key_name'] === 'PRIMARY') { $primary[] = $index['Column_name']; }
                    if (!(int) $index['Non_unique'] && null === $index['Sub_part']) { $names[$index['Key_name']][] = $index['Column_name']; }
                }
                if ($primary !== array($suffix === 'options' ? 'option_id' : 'ID') || ($suffix === 'options' && !in_array(array('option_name'), $names, true))) { return self::failure(); }
                continue;
            }
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0280: physical_schema(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            $columns = $wpdb->get_results("SHOW FULL COLUMNS FROM `{$table}`", ARRAY_A);
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error || array_diff(array_column((array) $columns, 'Field'), IDLM_Backup_Validator::columns()[$suffix]) || array_diff(IDLM_Backup_Validator::columns()[$suffix], array_column((array) $columns, 'Field'))) { return self::failure(); }
            $by_name = array_column($columns, null, 'Field');
            $columns = array(); foreach (IDLM_Backup_Validator::columns()[$suffix] as $field) { $columns[] = $by_name[$field]; }
            foreach ($columns as $column) { $schema[$suffix][$column['Field']] = array('Type'=>preg_replace('/^(tinyint|smallint|mediumint|int|bigint)\(\d+\)/', '$1', strtolower($column['Type'])), 'Null'=>$column['Null']); }
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0281: physical_schema(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            $indexes = $wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A); $unique = array();
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            if ($wpdb->last_error) { return self::failure(); }
            foreach ($indexes as $index) { if (!(int) $index['Non_unique'] && null === $index['Sub_part']) { $unique[$index['Key_name']][(int) $index['Seq_in_index']] = $index['Column_name']; } }
            $keys = array(); foreach ($unique as $key=>$fields) { ksort($fields); $keys[$key] = implode(',', $fields); }
            if (($keys['PRIMARY'] ?? '') !== ($suffix === 'idlm_routing_state' ? 'state_key' : 'id') || array_diff($uniques[$suffix] ?? array(), array_values($keys))) { return self::failure(); }
        }
        return $schema;
    }

    private static function identities() {
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0282: identities(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
        $rows = $wpdb->get_results("SELECT ID,user_login FROM {$wpdb->users}", ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->last_error) { return self::failure(); }
        $ids = array(); foreach ($rows as $row) { $ids[$row['ID']] = $row['user_login']; } return $ids;
    }

    /** One repeatable-read snapshot; a failed read never becomes an empty table. */
    private static function snapshot() {
        global $wpdb;
        $schema = self::physical_schema(); if (is_wp_error($schema)) { return $schema; }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0283: snapshot(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
        if (false === $wpdb->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ') || false === $wpdb->query('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY')) { return self::failure(); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        try {
            $tables = array();
            foreach (self::table_suffixes() as $suffix) {
                $table = self::table_name($suffix);
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0284: snapshot(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
                $tables[$suffix] = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ($wpdb->last_error || !is_array($tables[$suffix])) { return self::failure(); }
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0285: snapshot(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            $rows = $wpdb->get_results($wpdb->prepare("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name", $wpdb->esc_like('idlm_') . '%'), ARRAY_A);
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($wpdb->last_error || !is_array($rows)) { return self::failure(); }
            $options = array(); foreach ($rows as $row) { if (self::backup_option($row['option_name'])) { $options[] = $row; } }
            $identities = self::identities(); if (is_wp_error($identities)) { return $identities; }
            $users = array(); foreach ($tables['idlm_leads'] as $lead) { if (!empty($lead['assigned_rep_id'])) { $id = $lead['assigned_rep_id']; if (!isset($identities[$id])) { return self::failure(); } $users[$id] = $identities[$id]; } }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0286: snapshot(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            if (false === $wpdb->query('COMMIT')) { return self::failure(); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            return array('format'=>self::FORMAT, 'version'=>self::VERSION, 'created_at'=>current_time('mysql', true), 'site_url'=>home_url(), 'file_policy'=>'Private file bytes and WordPress attachments are excluded. Use a full WordPress database backup to recover them.', 'schema'=>array('core'=>IDLM_DB_VERSION, 'quotation'=>IDLM_Quotation_Schema::VERSION, 'columns'=>$schema), 'user_identities'=>$users, 'tables'=>$tables, 'options'=>$options);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0287: snapshot(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
        } finally { $wpdb->query('ROLLBACK'); }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    public static function export() {
        self::authorize('idlm_export_backup');
        $payload = self::snapshot();
        $json = is_wp_error($payload) ? false : wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!$json || strlen($json) > self::MAX_FILE_SIZE) { wp_die('Unable to create a complete compatible backup.', '', array('response'=>500)); }
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=idlm-backup-' . gmdate('Y-m-d-His') . '.json');
        header('X-Content-Type-Options: nosniff');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- authorized JSON download.
        exit;
    }

    public static function restore() {
        self::authorize('idlm_restore_backup');
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0072: restore(): restore calls private authorize with manage_options and dedicated action nonce before confirmation/file reads; server upload extension/size/is_uploaded_file and complete JSON/schema/relationship validators precede writes; internal redirect uses wp_validate_redirect.
        if (empty($_POST['idlm_restore_confirmation']) || 'RESTORE' !== strtoupper(sanitize_text_field(wp_unslash($_POST['idlm_restore_confirmation'])))) {
        // phpcs:enable WordPress.Security.NonceVerification.Missing
            self::redirect('restore-confirmation');
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0073: restore(): restore calls private authorize with manage_options and dedicated action nonce before confirmation/file reads; server upload extension/size/is_uploaded_file and complete JSON/schema/relationship validators precede writes; internal redirect uses wp_validate_redirect.
        if (empty($_FILES['idlm_backup_file']) || !is_array($_FILES['idlm_backup_file'])) {
        // phpcs:enable WordPress.Security.NonceVerification.Missing
            self::redirect('restore-file');
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0074: restore(): restore calls private authorize with manage_options and dedicated action nonce before confirmation/file reads; server upload extension/size/is_uploaded_file and complete JSON/schema/relationship validators precede writes; internal redirect uses wp_validate_redirect.
        $file = $_FILES['idlm_backup_file'];
        // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $filename = isset($file['name']) ? sanitize_file_name(wp_unslash($file['name'])) : '';
        $size = isset($file['size']) ? intval($file['size']) : 0;
        if (UPLOAD_ERR_OK !== intval($file['error'] ?? UPLOAD_ERR_NO_FILE) || !preg_match('/\.json$/i', $filename) || $size < 2 || $size > self::MAX_FILE_SIZE || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            self::redirect('restore-file');
        }
        $raw = file_get_contents($file['tmp_name']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $payload = is_string($raw) ? json_decode($raw, true, 512, JSON_BIGINT_AS_STRING) : null;
        if (!is_array($payload) || self::FORMAT !== ($payload['format'] ?? '') || self::VERSION !== intval($payload['version'] ?? 0) || !is_array($payload['tables'] ?? null) || !is_array($payload['options'] ?? null)) {
            self::redirect('restore-invalid');
        }

        $restored = self::restore_payload($payload);
        if (is_wp_error($restored)) {
            self::redirect('restore-failed');
        }
        self::redirect('restored');
    }

    /** Temporary staging enforces native collation/unique constraints before deletion. */
    private static function restore_payload($payload) {
        global $wpdb;
        if (!IDLM_Private_Files::restore_safe($payload)) { return self::failure(); }
        $schema = self::physical_schema(); $identities = self::identities();
        if (is_wp_error($schema) || is_wp_error($identities)) { return self::failure(); }
        $payload = IDLM_Backup_Validator::normalize_document_terms($payload, $schema);
        $payload = IDLM_Backup_Validator::normalize_item_classifications($payload, $schema);
        $payload = IDLM_Backup_Validator::normalize_reviews($payload, $schema);
        $payload = IDLM_Backup_Validator::normalize_task_cycles($payload, $schema);
        $valid = IDLM_Backup_Validator::validate($payload, $schema, home_url(), $identities, static function($name) use ($payload) {
            // Earlier complete 1.3.5 manifests included these migration markers.
            // Validate their ordinary value shape, but never import them below.
            return self::backup_option($name) || ('1.3.5' === ($payload['schema']['core'] ?? '') && self::core_migration_option($name));
        });
        if (is_wp_error($valid)) { return $valid; }
        if (!IDLM_Recovery_Guard::begin_restore()) { return new WP_Error('idlm_backup_busy', 'CRM recovery is busy. Retry after active work finishes.'); }
        $staged = array(); $transaction = false;
        $previous_errors = $wpdb->suppress_errors(true); // Never log uploaded rows in native constraint errors.
        try {
            // Uploads and workers cannot enter between this check and commit.
            if (!IDLM_Private_Files::restore_safe($payload) || self::physical_schema() !== $schema || self::identities() !== $identities) { return self::failure(); }
            foreach (self::table_suffixes() as $suffix) {
                $table = self::table_name($suffix); $temp = 'idlm_restore_' . bin2hex(random_bytes(8));
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0288: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
                if (false === $wpdb->query("CREATE TEMPORARY TABLE `{$temp}` LIKE `{$table}`")) { return self::failure(); }
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $staged[$suffix] = $temp;
                foreach ($payload['tables'][$suffix] as $row) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0289: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
                    if (1 !== $wpdb->insert($temp, $row) || $wpdb->get_results('SHOW WARNINGS', ARRAY_A) || $wpdb->last_error) { return self::failure(); }
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                }
            }
            $temp = 'idlm_restore_' . bin2hex(random_bytes(8));
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0290: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            if (false === $wpdb->query("CREATE TEMPORARY TABLE `{$temp}` LIKE `{$wpdb->options}`")) { return self::failure(); }
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $staged['options'] = $temp;
            foreach ($payload['options'] as $option) {
                if (!self::backup_option($option['option_name'])) { continue; }
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0291: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
                if (1 !== $wpdb->insert($temp, $option) || $wpdb->get_results('SHOW WARNINGS', ARRAY_A) || $wpdb->last_error) { return self::failure(); }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0292: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            if (false === $wpdb->query('START TRANSACTION')) { return self::failure(); } $transaction = true;
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            // Full range locks also fence non-cooperating SQL writers until commit.
            foreach (self::table_suffixes() as $suffix) {
                $table = self::table_name($suffix); $key = $suffix === 'idlm_routing_state' ? 'state_key' : 'id';
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0293: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
                $wpdb->get_results("SELECT `{$key}` FROM `{$table}` FOR UPDATE"); if ($wpdb->last_error) { return self::failure(); }
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0294: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            $wpdb->get_results("SELECT option_id FROM {$wpdb->options} FOR UPDATE"); if ($wpdb->last_error) { return self::failure(); }
            $wpdb->get_results("SELECT ID FROM {$wpdb->users} LOCK IN SHARE MODE"); if ($wpdb->last_error || self::identities() !== $identities) { return self::failure(); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            // Issued quotation numbers must never be recycled by an older backup.
            $sequence = self::table_name('idlm_number_sequences'); $temp = $staged['idlm_number_sequences'];
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0295: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            $current = $wpdb->get_results("SELECT * FROM `{$sequence}`", ARRAY_A); if ($wpdb->last_error) { return self::failure(); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            foreach ($current as $row) {
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0296: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$temp}` WHERE sequence_type=%s AND sequence_year=%d", $row['sequence_type'], $row['sequence_year']), ARRAY_A);
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                if ($wpdb->last_error) { return self::failure(); }
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0297: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
                if ($existing) { if (false === $wpdb->query($wpdb->prepare("UPDATE `{$temp}` SET last_number=GREATEST(last_number,%d) WHERE id=%d", $row['last_number'], $existing['id']))) { return self::failure(); } }
                else { unset($row['id']); if (1 !== $wpdb->insert($temp, $row)) { return self::failure(); } }
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            }
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0298: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            foreach (array_reverse(self::table_suffixes()) as $suffix) { $table = self::table_name($suffix); if (false === $wpdb->query("DELETE FROM `{$table}`")) { return self::failure(); } }
            foreach (self::table_suffixes() as $suffix) { $table = self::table_name($suffix); $temp = $staged[$suffix]; if (false === $wpdb->query("INSERT INTO `{$table}` SELECT * FROM `{$temp}`")) { return self::failure(); } }
            $names = $wpdb->get_col("SELECT option_name FROM {$wpdb->options}"); if ($wpdb->last_error) { return self::failure(); }
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $clear = array();
            foreach ($names as $name) {
                if (self::backup_option($name) || strpos($name, 'idlm_interakt_batch_') === 0 || strpos($name, 'idlm_interakt_dispatch_') === 0 || strpos($name, 'idlm_capi_retry_') === 0 || $name === 'idlm_audience_sync_queue' || $name === IDLM_Audience_Dispatch::SCAN || strpos($name, IDLM_Audience_Dispatch::PREFIX) === 0 || preg_match('/^_transient_(?:timeout_)?idlm_quote_autosave_/', $name)) {
                    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0299: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
                    $clear[] = $name; if (false === $wpdb->delete($wpdb->options, array('option_name'=>$name))) { return self::failure(); }
                    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                }
            }
            foreach ($payload['options'] as $option) {
                if (!self::backup_option($option['option_name'])) { continue; } // Complete legacy v1: protected/runtime values are ignored.
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0300: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
                $clear[] = $option['option_name']; if (false === $wpdb->replace($wpdb->options, $option)) { return self::failure(); }
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            }
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0301: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            if (false === $wpdb->query("DELETE FROM {$wpdb->prefix}idlm_lead_outbox")) { return self::failure(); }
            if (!IDLM_Recovery_Guard::advance_generation() || false === $wpdb->query('COMMIT')) { return self::failure(); } $transaction = false;
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
            foreach (array_unique(array_merge($clear, array(IDLM_Recovery_Guard::GENERATION))) as $name) { wp_cache_delete($name, 'options'); }
            wp_cache_delete('alloptions', 'options'); wp_cache_delete('notoptions', 'options');
            // External object caches may retain autosaves after their DB options vanish.
            foreach ($names as $name) { if (strpos($name, '_transient_idlm_quote_autosave_') === 0) { wp_cache_delete(substr($name, 11), 'transient'); } }
            self::cancel_queued_jobs(); IDLM_Read_Model_Cache::invalidate('backup_restore');
            return true;
        } finally {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0302: restore_payload(): table_suffixes()/validator manifest fixes identifiers; staging names are random hex, key columns are fixed; values use wpdb insert/prepare; consistent snapshots and range locks cannot use cached rows.
            if ($transaction) { $wpdb->query('ROLLBACK'); }
            foreach ($staged as $temp) { $wpdb->query("DROP TEMPORARY TABLE IF EXISTS `{$temp}`"); }
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            IDLM_Recovery_Guard::end_restore();
            $wpdb->suppress_errors($previous_errors);
        }
    }

    private static function cancel_queued_jobs() {
        $hooks = array('idlm_async_capi_upload_event', 'idlm_async_interakt_track_event', 'idlm_async_interakt_batch_event', IDLM_Audience_Dispatch::HOOK);
        foreach ($hooks as $hook) {
            if (function_exists('as_unschedule_all_actions')) { as_unschedule_all_actions($hook); }
            wp_unschedule_hook($hook);
        }
    }

    private static function redirect($notice) {
        $fallback = IDLM_Admin::admin_utility_url();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0075: redirect(): restore calls private authorize with manage_options and dedicated action nonce before confirmation/file reads; server upload extension/size/is_uploaded_file and complete JSON/schema/relationship validators precede writes; internal redirect uses wp_validate_redirect.
        $posted_return_url = isset($_POST['idlm_return_url']) ? esc_url_raw(wp_unslash($_POST['idlm_return_url'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $return_url = $posted_return_url ? wp_validate_redirect($posted_return_url, $fallback) : $fallback;
        wp_safe_redirect(add_query_arg('idlm_backup_notice', sanitize_key($notice), $return_url) . '#tab-backup');
        exit;
    }
}
