<?php
/**
 * Telemetry request handlers behind the stable administrator AJAX facades.
 *
 * Builder: Shashi
 */
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Admin_Telemetry_Controller {

    public static function ajax_log_js_error() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        
        $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
        $file    = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
        $line    = isset($_POST['line']) ? intval($_POST['line']) : 0;
        $col     = isset($_POST['col']) ? intval($_POST['col']) : 0;
        $stack   = isset($_POST['stack']) ? sanitize_textarea_field(wp_unslash($_POST['stack'])) : '';

        if (!empty($message)) {
            $trace = "Col: {$col}\n\nStack:\n{$stack}";
            IDLM_Telemetry::log('js_error', 'error', $message, $file, $line, $trace);
        }

        wp_send_json_success();
    }

    public static function ajax_clear_telemetry_logs() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        global $wpdb;
        $table_telemetry = $wpdb->prefix . 'idlm_telemetry_logs';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query("TRUNCATE TABLE {$table_telemetry}");

        wp_send_json_success(array('message' => 'Telemetry logs cleared successfully.'));
    }

    public static function ajax_download_diagnostic_report() {
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_key($_GET['nonce']), 'idlm_admin_nonce')) {
            wp_die('Invalid security token.', 'Access Denied', array('response' => 403));
        }
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.', 'Access Denied', array('response' => 403));
        }

        // Clear any previous output buffers to avoid corrupting headers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        global $wpdb;
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename=idlm-diagnostic-report-' . gmdate('Y-m-d') . '.txt');

        // Compile diagnostics text
        $out = array();
        $out[] = "=== INTERIOR DESIGN LEAD MANAGER DIAGNOSTICS ===";
        $out[] = "Generated on: " . gmdate('Y-m-d H:i:s') . " UTC";
        $out[] = "Plugin Version: " . IDLM_VERSION;
        $out[] = "";
        
        $out[] = "=== ENVIRONMENT ===";
        $out[] = "WordPress Version: " . get_bloginfo('version');
        $out[] = "PHP Version: " . PHP_VERSION;
        $out[] = "Server Software: " . (isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown');
        $out[] = "MySQL Version: " . $wpdb->db_version();
        $out[] = "PHP Memory Limit: " . ini_get('memory_limit');
        $out[] = "PHP Max Execution Time: " . ini_get('max_execution_time') . "s";
        $out[] = "WP Debug Mode: " . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled');
        
        $as_version = 'Not installed/active';
        if (class_exists('ActionScheduler')) {
            $as_version = 'Active';
            if (class_exists('ActionScheduler_Versions') && method_exists('ActionScheduler_Versions', 'instance')) {
                $as_version = ActionScheduler_Versions::instance()->latest_version();
            }
        }
        $out[] = "Action Scheduler Version: " . $as_version;
        $out[] = "";

        $out[] = "=== PLUGIN SETTINGS ===";
        $out[] = "Meta CAPI Enabled: " . get_option('idlm_meta_capi_enabled', '0');
        $out[] = "Google CAPI Enabled: " . get_option('idlm_google_capi_enabled', '0');
        $out[] = "LinkedIn CAPI Enabled: " . get_option('idlm_linkedin_capi_enabled', '0');
        $out[] = "Telemetry Log Level: " . get_option('idlm_telemetry_level', 'errors');
        $out[] = "Custom Stages: " . implode(', ', get_option('idlm_custom_stages', array()));
        $out[] = "";

        $out[] = "=== DATABASE STATE ===";
        foreach (array('idlm_leads', 'idlm_lead_activity_log', 'idlm_lead_touchpoints', 'idlm_inbound_submission_reviews', 'idlm_capi_logs', 'idlm_audience_sync_logs', 'idlm_telemetry_logs') as $tbl) {
            $full_tbl = $wpdb->prefix . $tbl;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_tbl)) === $full_tbl;
            if ($exists) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$full_tbl}");
                $out[] = "- {$full_tbl}: {$count} rows";
            } else {
                $out[] = "- {$full_tbl}: MISSING";
            }
        }
        $out[] = "";

        $out[] = "=== RECENT TELEMETRY LOGS (Last 50 Entries) ===";
        $table_telemetry = $wpdb->prefix . 'idlm_telemetry_logs';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0191: ajax_download_diagnostic_report(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_telemetry)) === $table_telemetry) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $logs = $wpdb->get_results("SELECT * FROM {$table_telemetry} ORDER BY id DESC LIMIT 50");
            if (empty($logs)) {
                $out[] = "No error logs recorded.";
            } else {
                foreach ($logs as $log) {
                    $out[] = sprintf(
                        "[%s] [%s] [%s] %s in %s on line %d",
                        (string) $log->created_at,
                        strtoupper((string) $log->severity),
                        (string) $log->log_type,
                        (string) $log->message,
                        $log->file ? (string) $log->file : '[unknown file]',
                        (int) $log->line
                    );
                    if (!empty($log->stack_trace)) {
                        $out[] = "Stack Trace:\n" . (string) $log->stack_trace;
                    }
                    $out[] = str_repeat('-', 40);
                }
            }
        } else {
            $out[] = "Telemetry log table is missing.";
        }

        echo implode("\n", $out); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
}
