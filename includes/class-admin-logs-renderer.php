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

        $sql = "SELECT c.id, c.lead_id, c.platform, c.event_name, c.status, c.created_at, c.payload, c.response,
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

        $logs = is_array($logs) ? $logs : array();
        // The outcome is read from the 50 shown rows; payloads are dropped before rendering.
        foreach ($logs as $log) { $log->outcome = self::capi_outcome($log); unset($log->payload, $log->response); }

        IDLM_View::display('admin/logs/capi.php', array(
            'logs'        => $logs,
            'retry_nonce' => wp_create_nonce('idlm_admin_nonce'),
            'filters'     => $filters,
        ));
    }

    /** What happened to one delivery attempt, for the log screen (CAPI review D13). Builder: Shashi. */
    public static function capi_outcome($log) {
        $payload = is_string($log->payload ?? null) ? IDLM_Schema_Migration::decode($log->payload) : ($log->payload ?? array());
        if (is_string($payload)) { $decoded = json_decode($payload, true); $payload = is_array($decoded) ? $decoded : array(); }
        $delivery = is_array($payload) && is_array($payload['_idlm_delivery'] ?? null) ? $payload['_idlm_delivery'] : array();
        $outcome = (string) ($delivery['outcome'] ?? ('success' === ($log->status ?? '') ? 'success' : 'failed'));
        if ('success' === ($log->status ?? '')) { $outcome = 'success'; }
        elseif (array_key_exists('attempted', $delivery) && false === $delivery['attempted']) { $outcome = 'not_sent'; }
        $states = array(
            'success'   => array('Succeeded', 'success', true),
            'failed'    => array('Rejected', 'failed', !array_key_exists('retryable', $delivery) || !empty($delivery['retryable'])),
            'transient' => array('Retrying', 'warning', true),
            'uncertain' => array('Uncertain', 'warning', false),
            'not_sent'  => array('Not sent', 'warning', true),
        );
        $state = $states[$outcome] ?? $states['failed'];
        // A succeeded row has no reason; the payload dialog stays the detail view.
        $message = 'success' === $outcome ? '' : self::failure_reason((string) ($log->platform ?? ''), $log->response ?? '');
        if ('uncertain' === $outcome && '' === $message) { $message = 'The provider may have received this event; it is not replayed.'; }
        return array('key' => $outcome, 'label' => $state[0], 'badge' => $state[1], 'tone' => self::badge_tone($state[1]), 'retryable' => 'success' !== $outcome && $state[2], 'message' => $message);
    }

    /** STD-CMP-3 badge modifier for an outcome badge. */
    public static function badge_tone($badge) {
        $tones = array('success' => 'is-success', 'warning' => 'is-warning', 'failed' => 'is-danger');
        return $tones[$badge] ?? 'is-danger';
    }

    /** Longest reason shown in a log row; the payload dialog holds the rest. */
    const REASON_LENGTH = 160;

    /**
     * Why one CAPI or audience attempt failed, in plain words, read from the stored provider
     * response. Known Meta, Google and LinkedIn errors are mapped to what to fix; anything else
     * falls back to the provider's own short message with credentials, contact details, hashes
     * and links removed. The result is plain text: escape it at output. Builder: Shashi.
     */
    public static function failure_reason($platform, $response) {
        $response = IDLM_Schema_Migration::decode($response);
        if (is_object($response)) { $response = get_object_vars($response); }
        if (is_string($response)) {
            $text = trim($response);
            if ('' === $text) { return ''; }
            $decoded = json_decode($text, true);
            $response = is_array($decoded) ? $decoded : $text;
        }
        if (is_array($response)) {
            $reason = self::provider_reason((string) $platform, $response);
        } elseif (is_scalar($response)) {
            $reason = self::transport_reason((string) $response);
        } else {
            $reason = '';
        }
        return self::short_text($reason);
    }

    /** Map one decoded provider error body. */
    private static function provider_reason($platform, array $body) {
        $error = $body['error'] ?? null;
        if (is_string($error)) { $error = array('message' => $error); }
        $error = is_array($error) ? $error : array();
        $message = self::scrub((string) ($error['error_user_msg'] ?? ($error['message'] ?? ($body['message'] ?? ''))));
        $code = (int) ($error['code'] ?? 0);
        $subcode = (int) ($error['error_subcode'] ?? 0);
        $status = strtoupper((string) ($error['status'] ?? ($body['code'] ?? '')));

        // Meta Graph API (Conversions API and Custom Audiences).
        if (isset($error['fbtrace_id']) || isset($body['fbtrace_id']) || isset($error['type']) || 'meta' === $platform) {
            if (190 === $code || 102 === $code || in_array($subcode, array(458, 459, 460, 463, 464, 467), true)) {
                return 'Meta access token expired or revoked. Reconnect Meta in Settings.';
            }
            if (10 === $code || ($code >= 200 && $code <= 299)) {
                return 'The Meta token lacks permission for this pixel or audience. Reconnect Meta.';
            }
            if (100 === $code && 33 === $subcode) {
                return 'Meta cannot find this pixel or audience ID. Check it in Settings.';
            }
            if (false !== stripos($message, 'LOOKALIKE_VALUE')) {
                return 'Value-based audience needs a deal value on every lead.';
            }
            if (in_array($code, array(4, 17, 32, 613), true) || ($code >= 80000 && $code <= 80099)) {
                return 'Meta rate limit reached. The event is retried later.';
            }
            if (in_array($code, array(1, 2), true) || !empty($error['is_transient'])) {
                return 'Meta had a temporary problem. The event is retried later.';
            }
            if (368 === $code) {
                return 'Meta temporarily blocked requests from this account.';
            }
            if (!empty($body['num_invalid_entries'])) {
                return sprintf('Meta rejected %d of %d uploaded entries.', (int) $body['num_invalid_entries'], (int) ($body['num_received'] ?? 0));
            }
        }

        // Google APIs report a gRPC-style status name.
        $google = array(
            'UNAUTHENTICATED'    => 'Google rejected the saved credentials. Reconnect Google in Settings.',
            'PERMISSION_DENIED'  => 'The Google account does not have access to this property or audience.',
            'NOT_FOUND'          => 'Google cannot find this measurement ID or audience. Check the ID in Settings.',
            'RESOURCE_EXHAUSTED' => 'Google quota reached. Try again later.',
            'UNAVAILABLE'        => 'Google was unavailable. Try again later.',
        );
        if (isset($google[$status])) { return $google[$status]; }

        // LinkedIn uses an HTTP status plus a code name.
        $http = (int) ($body['status'] ?? (is_numeric($error['code'] ?? null) ? $error['code'] : 0));
        if (401 === $http || in_array($status, array('INVALID_ACCESS_TOKEN', 'EXPIRED_ACCESS_TOKEN', 'REVOKED_ACCESS_TOKEN'), true)) {
            return 'The provider rejected the saved access token. Reconnect the integration in Settings.';
        }
        if (403 === $http || 'ACCESS_DENIED' === $status) {
            return 'The connected account does not have permission for this destination.';
        }
        if (429 === $http) { return 'Provider rate limit reached. Try again later.'; }
        if ($http >= 500 && $http <= 599) { return 'The provider had a server error. Try again later.'; }
        foreach (is_array($body['elements'] ?? null) ? $body['elements'] : array() as $element) {
            if (is_array($element) && (!empty($element['error']) || (int) ($element['status'] ?? 0) >= 400)) {
                return 'The provider rejected some of the uploaded entries.';
            }
        }

        if ('' !== $message) { return ucfirst('meta' === $platform ? preg_replace('/^\(#\d+\)\s*/', '', $message) : $message); }
        return $error ? 'The provider returned an error without a message. Inspect the payload for details.' : '';
    }

    /** Map a plain-text response: a transport error or a message the plugin wrote itself. */
    private static function transport_reason($text) {
        if (preg_match('/^Success Response \(Code (\d{3})\)$/', $text, $match)) {
            $code = (int) $match[1];
            if ($code < 300) { return ''; }
            return $code >= 500 ? sprintf('Google had a server error (HTTP %d).', $code) : sprintf('Google rejected the request (HTTP %d). Check the measurement ID and API secret.', $code);
        }
        $known = array(
            '/missing integration destination or credentials|credentials missing/i' => 'The integration is missing an ID or credential. Complete it in Settings.',
            '/cURL error 28|timed out/i'                                          => 'The request timed out. The provider may or may not have received it.',
            '/cURL error (6|7)\b|could not resolve host|failed to connect/i'       => 'The server could not reach the provider (network or DNS problem).',
            '/cURL error (35|51|60)\b|SSL/i'                                       => 'A secure connection to the provider could not be made.',
            '/worker fence lost/i'                                                 => 'The background worker stopped before the result was confirmed.',
        );
        foreach ($known as $pattern => $reason) {
            if (preg_match($pattern, $text)) { return $reason; }
        }
        return self::scrub($text);
    }

    /** Remove anything secret or personal from a provider message before it is shown. */
    private static function scrub($text) {
        $text = IDLM_Secret_Store::redact(wp_strip_all_tags((string) $text));
        $text = preg_replace('/Please read the Graph API documentation[^.]*\.?/i', '', $text);
        $text = preg_replace('#\bhttps?://\S+#i', '', $text);
        $text = preg_replace('/\b(?:EAA|ya29\.)[A-Za-z0-9._\-]{16,}/', '[token]', $text);
        $text = preg_replace('/\b[a-f0-9]{32,}\b/i', '[hash]', $text);
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $text);
        $text = preg_replace('/(?<![\w.])\+?\d[\d\s\-]{8,11}\d(?![\w.])/', '[phone]', $text);
        return trim(preg_replace('/\s+/', ' ', (string) $text));
    }

    private static function short_text($text) {
        $text = trim((string) $text);
        if (function_exists('mb_strimwidth')) { return mb_strimwidth($text, 0, self::REASON_LENGTH, '…', 'UTF-8'); }
        return strlen($text) > self::REASON_LENGTH ? substr($text, 0, self::REASON_LENGTH - 3) . '...' : $text;
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

        // Only failed rows read the provider response, and only to derive the shown reason.
        $sql = "SELECT id, platform, audience_id, lead_count, status, created_at,
                       CASE WHEN status = 'success' THEN '' ELSE response END AS response
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

        $logs = is_array($logs) ? $logs : array();
        foreach ($logs as $log) {
            $failed = 'success' !== ($log->status ?? '');
            $log->outcome = array(
                'label'   => $failed ? 'Failed' : 'Succeeded',
                'tone'    => $failed ? 'is-danger' : 'is-success',
                'message' => $failed ? self::failure_reason((string) ($log->platform ?? ''), $log->response ?? '') : '',
            );
            unset($log->response);
        }

        IDLM_View::display('admin/logs/audience.php', array(
            'logs'    => $logs,
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
        IDLM_Admin::require_ajax_access('admin', 'Permission denied.', 403);

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

        // The shared period list; old links with only From/To dates read as a custom range.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only log filter; the period is normalized and dates validated.
        $range = IDLM_Date_Ranges::normalize(isset($_GET['log_date_range']) ? wp_unslash($_GET['log_date_range']) : '');
        $date_from = self::valid_date(isset($_GET['log_date_from']) ? wp_unslash($_GET['log_date_from']) : '');
        $date_to = self::valid_date(isset($_GET['log_date_to']) ? wp_unslash($_GET['log_date_to']) : '');
        // phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ('' === $range && ('' !== $date_from || '' !== $date_to)) { $range = 'custom'; }
        $window = '' !== $range && 'custom' !== $range ? IDLM_Date_Ranges::resolve($range) : null;
        if ($window) { $date_from = substr($window['curr_start'], 0, 10); $date_to = substr($window['curr_end'], 0, 10); }
        return array(
            'range'     => $range,
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0062: request_filters(): manage_options-gated read-only log views; request filters are sanitized and dates validated by valid_date before prepared SQL; no state mutation is selected by these GET fields.
            'search'    => isset($_GET['log_search']) ? sanitize_text_field(wp_unslash($_GET['log_search'])) : '',
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            'status'    => $status,
            // phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0063: request_filters(): manage_options-gated read-only log views; request filters are sanitized and dates validated by valid_date before prepared SQL; no state mutation is selected by these GET fields.
            'date_from' => $date_from,
            // phpcs:enable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            // phpcs:disable WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0064: request_filters(): manage_options-gated read-only log views; request filters are sanitized and dates validated by valid_date before prepared SQL; no state mutation is selected by these GET fields.
            'date_to'   => $date_to,
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
        $value = IDLM_Schema_Migration::decode($value);
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
