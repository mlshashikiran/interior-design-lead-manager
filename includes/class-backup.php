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
            'idlm_leads', 'idlm_lead_activity_log', 'idlm_lead_state_events', 'idlm_tasks', 'idlm_lead_touchpoints', 'idlm_call_logs',
            'idlm_capi_logs', 'idlm_audience_sync_logs', 'idlm_telemetry_logs', 'idlm_routing_state',
            'idlm_quote_catalogue_items', 'idlm_quote_item_categories', 'idlm_quote_item_units', 'idlm_quotes',
            'idlm_quote_revisions', 'idlm_quote_revision_sections', 'idlm_quote_revision_items', 'idlm_quote_payment_milestones',
            'idlm_quote_status_history', 'idlm_number_sequences',
        );
    }

    private static function authorize($action) {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.', 403);
        }
        check_admin_referer($action);
    }

    private static function table_name($suffix) {
        global $wpdb;
        return $wpdb->prefix . $suffix;
    }

    private static function backup_option($option_name) {
        if (0 !== strpos($option_name, 'idlm_')) { return false; }
        if (in_array($option_name, array('idlm_schema_version', 'idlm_quotation_schema_version', 'idlm_plugin_activated_at', 'idlm_roles_version', 'idlm_legacy_quote_migration_version', 'idlm_quotation_snapshot_migration_version'), true)) { return false; }
        return !preg_match('/(?:_api_key|_access_token|_password|_secret|_verify_token|credential)/i', $option_name);
    }

    /** Stream a JSON backup; secrets and WordPress-owned data are never included. */
    public static function export() {
        self::authorize('idlm_export_backup');
        global $wpdb;

        $tables = array();
        foreach (self::table_suffixes() as $suffix) {
            $table = self::table_name($suffix);
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) { continue; }
            // Static plugin table names are selected only from table_suffixes().
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $rows = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
            if (null === $rows && $wpdb->last_error) {
                wp_die('Unable to read plugin data for backup.', 500);
            }
            $tables[$suffix] = is_array($rows) ? $rows : array();
        }

        $option_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name ASC",
            $wpdb->esc_like('idlm_') . '%'
        ), ARRAY_A);
        $options = array();
        foreach ((array) $option_rows as $row) {
            if (self::backup_option($row['option_name'])) { $options[] = $row; }
        }
        $payload = array(
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'created_at' => current_time('mysql', true),
            'site_url' => home_url(),
            'tables' => $tables,
            'options' => $options,
        );
        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!$json) { wp_die('Unable to create the backup file.', 500); }

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=idlm-backup-' . gmdate('Y-m-d-His') . '.json');
        header('X-Content-Type-Options: nosniff');
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON backup download.
        exit;
    }

    public static function restore() {
        self::authorize('idlm_restore_backup');
        if (empty($_POST['idlm_restore_confirmation']) || 'RESTORE' !== strtoupper(sanitize_text_field(wp_unslash($_POST['idlm_restore_confirmation'])))) {
            self::redirect('restore-confirmation');
        }
        if (empty($_FILES['idlm_backup_file']) || !is_array($_FILES['idlm_backup_file'])) {
            self::redirect('restore-file');
        }
        $file = $_FILES['idlm_backup_file'];
        $filename = isset($file['name']) ? sanitize_file_name(wp_unslash($file['name'])) : '';
        $size = isset($file['size']) ? intval($file['size']) : 0;
        if (UPLOAD_ERR_OK !== intval($file['error'] ?? UPLOAD_ERR_NO_FILE) || !preg_match('/\.json$/i', $filename) || $size < 2 || $size > self::MAX_FILE_SIZE || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            self::redirect('restore-file');
        }
        $raw = file_get_contents($file['tmp_name']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        $payload = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($payload) || self::FORMAT !== ($payload['format'] ?? '') || self::VERSION !== intval($payload['version'] ?? 0) || !is_array($payload['tables'] ?? null) || !is_array($payload['options'] ?? null)) {
            self::redirect('restore-invalid');
        }

        $restored = self::restore_payload($payload);
        if (is_wp_error($restored)) {
            self::redirect('restore-failed');
        }
        self::cancel_queued_jobs();
        IDLM_Data_Retention::prune();
        IDLM_Read_Model_Cache::invalidate('backup_restore');
        self::redirect('restored');
    }

    private static function restore_payload($payload) {
        global $wpdb;
        $suffixes = self::table_suffixes();
        foreach ($suffixes as $suffix) {
            $table = self::table_name($suffix);
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
                return new WP_Error('idlm_backup_table_missing', 'The backup cannot be restored because a required plugin table is missing.');
            }
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table));
            if ('INNODB' !== strtoupper((string) $engine)) {
                return new WP_Error('idlm_backup_engine', 'Restore requires all plugin tables to use InnoDB.');
            }
        }
        if (false === $wpdb->query('START TRANSACTION')) {
            return new WP_Error('idlm_backup_transaction', 'Could not start the restore transaction.');
        }
        foreach (array_reverse($suffixes) as $suffix) {
            $table = self::table_name($suffix);
            // Static plugin table names are selected only from table_suffixes().
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (false === $wpdb->query("DELETE FROM {$table}")) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_backup_delete', 'Restore failed while clearing existing plugin data.'); }
        }
        foreach ($suffixes as $suffix) {
            $table = self::table_name($suffix);
            $rows = isset($payload['tables'][$suffix]) && is_array($payload['tables'][$suffix]) ? $payload['tables'][$suffix] : array();
            foreach ($rows as $row) {
                if (!is_array($row) || false === $wpdb->insert($table, $row)) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_backup_insert', 'Restore failed while writing plugin data.'); }
            }
        }
        $current_options = $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('idlm_') . '%'));
        foreach ((array) $current_options as $option_name) {
            if (self::backup_option($option_name) && false === $wpdb->delete($wpdb->options, array('option_name' => $option_name))) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_backup_options', 'Restore failed while replacing plugin settings.'); }
        }
        foreach ($payload['options'] as $option) {
            if (!is_array($option) || empty($option['option_name']) || !self::backup_option($option['option_name'])) { continue; }
            $data = array('option_name' => $option['option_name'], 'option_value' => isset($option['option_value']) ? (string) $option['option_value'] : '', 'autoload' => ('yes' === ($option['autoload'] ?? 'no')) ? 'yes' : 'no');
            if (false === $wpdb->replace($wpdb->options, $data)) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_backup_options', 'Restore failed while writing plugin settings.'); }
        }
        if (false === $wpdb->query('COMMIT')) { $wpdb->query('ROLLBACK'); return new WP_Error('idlm_backup_commit', 'Restore could not be committed.'); }
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('notoptions', 'options');
        return true;
    }

    private static function cancel_queued_jobs() {
        $hooks = array('idlm_async_capi_upload_event', 'idlm_async_interakt_track_event', 'idlm_async_interakt_batch_event');
        foreach ($hooks as $hook) {
            if (function_exists('as_unschedule_all_actions')) { as_unschedule_all_actions($hook); }
            wp_unschedule_hook($hook);
        }
    }

    private static function redirect($notice) {
        $fallback = IDLM_Admin::admin_utility_url();
        $posted_return_url = isset($_POST['idlm_return_url']) ? esc_url_raw(wp_unslash($_POST['idlm_return_url'])) : '';
        $return_url = $posted_return_url ? wp_validate_redirect($posted_return_url, $fallback) : $fallback;
        wp_safe_redirect(add_query_arg('idlm_backup_notice', sanitize_key($notice), $return_url) . '#tab-backup');
        exit;
    }
}
