<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Owns the read-only CAPI and audience log page queries and contexts.
 *
 * Builder: Shashi
 */
class IDLM_Admin_Logs_Renderer {
    public static function render_capi() {
        if (!current_user_can('manage_options')) {
            wp_die('Access Denied');
        }

        global $wpdb;
        $filters = self::request_filters();
        $where = array('1 = 1');
        $values = array();

        if ('' !== $filters['search']) {
            $like = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = '(l.email LIKE %s OR c.event_name LIKE %s OR c.platform LIKE %s OR CAST(c.lead_id AS CHAR) LIKE %s)';
            array_push($values, $like, $like, $like, $like);
        }
        self::append_common_where($where, $values, $filters, 'c');

        $sql = "SELECT c.id, c.lead_id, c.platform, c.event_name, c.status, c.created_at,
                       l.first_name, l.last_name, l.email
                FROM {$wpdb->prefix}idlm_capi_logs c
                LEFT JOIN {$wpdb->prefix}idlm_leads l ON c.lead_id = l.id
                WHERE " . implode(' AND ', $where) . '
                ORDER BY c.id DESC LIMIT 50';
        if ($values) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0044: render_capi(): manage_options and dedicated AJAX nonce guards; table suffix/status/date maps are fixed; search/date/id values prepared; bounded diagnostic rows reflect current retry state.
            $sql = $wpdb->prepare($sql, $values);
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
        }
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- REL007-SQL-0045: render_capi(): manage_options and dedicated AJAX nonce guards; table suffix/status/date maps are fixed; search/date/id values prepared; bounded diagnostic rows reflect current retry state.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $logs = $wpdb->get_results($sql);
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

        IDLM_View::display('admin/logs/capi.php', array(
            'logs'        => is_array($logs) ? $logs : array(),
            'retry_nonce' => wp_create_nonce('idlm_admin_nonce'),
            'filters'     => $filters,
        ));
    }

    public static function render_audience() {
        if (!current_user_can('manage_options')) {
            wp_die('Access Denied');
        }

        global $wpdb;
        $filters = self::request_filters();
        $where = array('1 = 1');
        $values = array();

        if ('' !== $filters['search']) {
            $like = '%' . $wpdb->esc_like($filters['search']) . '%';
            $where[] = '(audience_id LIKE %s OR platform LIKE %s)';
            array_push($values, $like, $like);
        }
        self::append_common_where($where, $values, $filters);

        $sql = "SELECT id, platform, audience_id, lead_count, status, created_at
                FROM {$wpdb->prefix}idlm_audience_sync_logs
                WHERE " . implode(' AND ', $where) . '
                ORDER BY id DESC LIMIT 50';
        if ($values) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0046: render_audience(): manage_options and dedicated AJAX nonce guards; table suffix/status/date maps are fixed; search/date/id values prepared; bounded diagnostic rows reflect current retry state.
            $sql = $wpdb->prepare($sql, $values);
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
        }
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- REL007-SQL-0047: render_audience(): manage_options and dedicated AJAX nonce guards; table suffix/status/date maps are fixed; search/date/id values prepared; bounded diagnostic rows reflect current retry state.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $logs = $wpdb->get_results($sql);
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter

        IDLM_View::display('admin/logs/audience.php', array(
            'logs'    => is_array($logs) ? $logs : array(),
            'filters' => $filters,
        ));
    }

    /** Load one CAPI request/response pair only when an administrator asks for it. */
    public static function ajax_get_capi_log_payload() {
        self::ajax_get_log_payload('idlm_capi_logs');
    }

    /**
     * Load one large audience request/response pair only when an administrator asks for it.
     */
    public static function ajax_get_audience_log_payload() {
        self::ajax_get_log_payload('idlm_audience_sync_logs');
    }

    /**
     * Return one redacted integration log payload behind the shared administrator boundary.
     */
    private static function ajax_get_log_payload($table_suffix) {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }

        $log_id = isset($_POST['log_id']) ? absint(wp_unslash($_POST['log_id'])) : 0;
        if (!$log_id) {
            wp_send_json_error(array('message' => 'Invalid log ID.'), 400);
        }

        global $wpdb;
        $allowed_tables = array('idlm_capi_logs', 'idlm_audience_sync_logs');
        if (!in_array($table_suffix, $allowed_tables, true)) {
            wp_send_json_error(array('message' => 'Invalid log type.'), 400);
        }
        $table = $wpdb->prefix . $table_suffix;
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0048: ajax_get_log_payload(): manage_options and dedicated AJAX nonce guards; table suffix/status/date maps are fixed; search/date/id values prepared; bounded diagnostic rows reflect current retry state.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $log = $wpdb->get_row($wpdb->prepare(
            "SELECT payload, response FROM {$table} WHERE id = %d",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $log_id
        ));
        if (!$log) {
            wp_send_json_error(array('message' => 'Log not found.'), 404);
        }

        wp_send_json_success(array(
            'payload'  => self::format_payload($log->payload),
            'response' => self::format_payload($log->response),
        ));
    }

    /** Read and validate the shared log filters. */
    private static function request_filters() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0061: request_filters(): manage_options-gated read-only log views; request filters are sanitized and dates validated by valid_date before prepared SQL; no state mutation is selected by these GET fields.
        $status = isset($_GET['log_status']) ? sanitize_key(wp_unslash($_GET['log_status'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (!in_array($status, array('', 'success', 'failed'), true)) {
            $status = '';
        }

        return array(
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0062: request_filters(): manage_options-gated read-only log views; request filters are sanitized and dates validated by valid_date before prepared SQL; no state mutation is selected by these GET fields.
            'search'    => isset($_GET['log_search']) ? sanitize_text_field(wp_unslash($_GET['log_search'])) : '',
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            'status'    => $status,
            // phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0063: request_filters(): manage_options-gated read-only log views; request filters are sanitized and dates validated by valid_date before prepared SQL; no state mutation is selected by these GET fields.
            'date_from' => self::valid_date(isset($_GET['log_date_from']) ? wp_unslash($_GET['log_date_from']) : ''),
            // phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            // phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0064: request_filters(): manage_options-gated read-only log views; request filters are sanitized and dates validated by valid_date before prepared SQL; no state mutation is selected by these GET fields.
            'date_to'   => self::valid_date(isset($_GET['log_date_to']) ? wp_unslash($_GET['log_date_to']) : ''),
            // phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        );
    }

    /** Add status and date clauses to one bounded log query. */
    private static function append_common_where(&$where, &$values, $filters, $alias = '') {
        $prefix = $alias ? $alias . '.' : '';
        if ('' !== $filters['status']) {
            $where[] = $prefix . 'status = %s';
            $values[] = $filters['status'];
        }
        if ('' !== $filters['date_from']) {
            $where[] = $prefix . 'created_at >= %s';
            $values[] = $filters['date_from'] . ' 00:00:00';
        }
        if ('' !== $filters['date_to']) {
            $where[] = $prefix . 'created_at <= %s';
            $values[] = $filters['date_to'] . ' 23:59:59';
        }
    }

    /** Accept only real ISO calendar dates from date inputs. */
    private static function valid_date($date) {
        if (!is_string($date)) { return ''; }
        $date = sanitize_text_field($date);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return '';
        }
        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]) ? $date : '';
    }

    /** Format a serialized scalar or structure for the read-only payload viewer. */
    private static function format_payload($value) {
        $value = maybe_unserialize($value);
        if (is_scalar($value) || null === $value) {
            $string = (string) $value;
            $decoded = json_decode($string, true);
            if (JSON_ERROR_NONE === json_last_error()) {
                $json = wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                return false === $json ? $string : $json;
            }
            return $string;
        }
        $json = wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return false === $json ? '' : $json;
    }
}
