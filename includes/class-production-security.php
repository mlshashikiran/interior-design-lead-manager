<?php
/**
 * Production security policy for public integrations and CRM uploads.
 *
 * @package InteriorDesignLeadManager
 * @author Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Production_Security {
    const MCP_GRANTS_OPTION = 'idlm_mcp_tool_grants';
    const MCP_METADATA_OPTION = 'idlm_mcp_credential_metadata';

    /** Return the normalized WordPress environment type. */
    public static function environment_type() {
        if (function_exists('wp_get_environment_type')) {
            return sanitize_key(wp_get_environment_type());
        }
        return defined('IDLM_TESTING') && IDLM_TESTING ? 'development' : 'production';
    }

    /** MCP is fail-closed in production unless explicitly enabled. */
    public static function is_mcp_enabled() {
        if (defined('IDLM_MCP_DISABLED') && IDLM_MCP_DISABLED) {
            return false;
        }
        if (defined('IDLM_MCP_ENABLED')) {
            return (bool) IDLM_MCP_ENABLED;
        }

        $default = 'production' === self::environment_type() ? '0' : '1';
        return '1' === (string) get_option('idlm_mcp_enabled', $default);
    }

    /** Resolve least-privilege tool grants for the current MCP credential. */
    public static function mcp_allowed_tools() {
        $all_tools = array(
            'get_leads_summary',
            'query_leads',
            'get_lead_details',
            'get_reps_performance',
            'run_custom_query',
        );
        $production_defaults = array('get_leads_summary', 'get_reps_performance');

        if (defined('IDLM_MCP_ALLOWED_TOOLS')) {
            $configured = is_array(IDLM_MCP_ALLOWED_TOOLS)
                ? IDLM_MCP_ALLOWED_TOOLS
                : preg_split('/[\s,]+/', (string) IDLM_MCP_ALLOWED_TOOLS, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $default = 'production' === self::environment_type() ? $production_defaults : $all_tools;
            $configured = get_option(self::MCP_GRANTS_OPTION, $default);
        }

        $configured = array_values(array_unique(array_map('sanitize_key', (array) $configured)));
        return array_values(array_intersect($all_tools, $configured));
    }

    public static function is_mcp_tool_allowed($tool_name) {
        return in_array(sanitize_key($tool_name), self::mcp_allowed_tools(), true);
    }

    /** Independent, explicit contact/profile-data grant; absent means denied. */
    public static function mcp_pii_allowed() {
        return defined('IDLM_MCP_ALLOW_PII') ? true === IDLM_MCP_ALLOW_PII
            : '1' === (string) get_option('idlm_mcp_allow_pii', '0');
    }

    public static function mcp_payload_limit() {
        $default = 'production' === self::environment_type() ? 262144 : 1048576;
        return defined('IDLM_MCP_MAX_PAYLOAD_BYTES')
            ? max(16384, min(1048576, absint(IDLM_MCP_MAX_PAYLOAD_BYTES)))
            : $default;
    }

    public static function mcp_batch_limit() {
        $default = 'production' === self::environment_type() ? 5 : 20;
        return defined('IDLM_MCP_MAX_BATCH_CALLS')
            ? max(1, min(20, absint(IDLM_MCP_MAX_BATCH_CALLS)))
            : $default;
    }

    public static function mcp_query_row_limit() {
        $default = 'production' === self::environment_type() ? 100 : 200;
        return defined('IDLM_MCP_MAX_QUERY_ROWS')
            ? max(1, min(200, absint(IDLM_MCP_MAX_QUERY_ROWS)))
            : $default;
    }

    public static function mcp_query_length_limit() {
        $default = 'production' === self::environment_type() ? 4000 : 10000;
        return defined('IDLM_MCP_MAX_QUERY_LENGTH')
            ? max(256, min(10000, absint(IDLM_MCP_MAX_QUERY_LENGTH)))
            : $default;
    }

    public static function mcp_request_time_limit() {
        $default = 'production' === self::environment_type() ? 3.0 : 10.0;
        if (!defined('IDLM_MCP_MAX_REQUEST_SECONDS')) {
            return $default;
        }
        return max(0.25, min(15.0, (float) IDLM_MCP_MAX_REQUEST_SECONDS));
    }

    /** Resolve the caller address without trusting spoofable forwarding headers. */
    public static function client_ip($server = null) {
        $server = is_array($server) ? $server : $_SERVER;
        $remote = isset($server['REMOTE_ADDR']) ? trim((string) $server['REMOTE_ADDR']) : '';
        if (!filter_var($remote, FILTER_VALIDATE_IP)) {
            return 'unknown';
        }
        if (!self::is_trusted_proxy($remote)) {
            return $remote;
        }

        $forwarded = isset($server['HTTP_X_FORWARDED_FOR']) ? (string) $server['HTTP_X_FORWARDED_FOR'] : '';
        $addresses = array_values(array_filter(array_map('trim', explode(',', $forwarded)), static function($address) {
            return (bool) filter_var($address, FILTER_VALIDATE_IP);
        }));
        if (empty($addresses)) {
            return $remote;
        }

        $addresses[] = $remote;
        for ($index = count($addresses) - 1; $index >= 0; $index--) {
            if (!self::is_trusted_proxy($addresses[$index])) {
                return $addresses[$index];
            }
        }

        return $addresses[0];
    }

    public static function trusted_proxies() {
        if (defined('IDLM_TRUSTED_PROXIES')) {
            $configured = is_array(IDLM_TRUSTED_PROXIES)
                ? IDLM_TRUSTED_PROXIES
                : preg_split('/[\s,]+/', (string) IDLM_TRUSTED_PROXIES, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $configured = get_option('idlm_trusted_proxies', array());
        }
        return array_values(array_filter(array_map('trim', (array) $configured)));
    }

    public static function is_trusted_proxy($address) {
        foreach (self::trusted_proxies() as $network) {
            if (self::ip_matches_network($address, $network)) {
                return true;
            }
        }
        return false;
    }

    private static function ip_matches_network($address, $network) {
        if (false === strpos($network, '/')) {
            $address_binary = @inet_pton($address);
            $network_binary = @inet_pton($network);
            return false !== $address_binary && false !== $network_binary
                && strlen($address_binary) === strlen($network_binary)
                && hash_equals($network_binary, $address_binary);
        }

        list($network_address, $prefix_length) = array_pad(explode('/', $network, 2), 2, null);
        $address_binary = @inet_pton($address);
        $network_binary = @inet_pton($network_address);
        if (false === $address_binary || false === $network_binary || strlen($address_binary) !== strlen($network_binary)) {
            return false;
        }

        $maximum_bits = 8 * strlen($address_binary);
        $prefix_length = (int) $prefix_length;
        if ($prefix_length < 0 || $prefix_length > $maximum_bits) {
            return false;
        }

        $full_bytes = (int) floor($prefix_length / 8);
        $remaining_bits = $prefix_length % 8;
        if ($full_bytes && substr($address_binary, 0, $full_bytes) !== substr($network_binary, 0, $full_bytes)) {
            return false;
        }
        if (!$remaining_bits) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining_bits)) & 0xFF;
        return (ord($address_binary[$full_bytes]) & $mask) === (ord($network_binary[$full_bytes]) & $mask);
    }

    /** Field-specific extension, MIME, and size rules for CRM attachments. */
    public static function upload_policy($purpose) {
        $policies = array(
            'floor_plan' => array(
                'max_bytes' => 10 * MB_IN_BYTES,
                'extensions' => array('pdf', 'jpg', 'jpeg', 'png', 'webp'),
                'mimes' => array('application/pdf', 'image/jpeg', 'image/png', 'image/webp'),
            ),
            'quote' => array(
                'max_bytes' => 10 * MB_IN_BYTES,
                'extensions' => array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'),
                'mimes' => array(
                    'application/pdf', 'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-office', 'application/x-ole-storage',
                    'application/cdfv2', 'application/zip', 'image/jpeg', 'image/png',
                ),
            ),
        );
        $purpose = sanitize_key($purpose);
        return isset($policies[$purpose]) ? $policies[$purpose] : null;
    }

    public static function validate_upload($file, $purpose) {
        $policy = self::upload_policy($purpose);
        if (!$policy) {
            return new WP_Error('idlm_upload_invalid_purpose', 'Choose a valid CRM attachment field.');
        }
        if (!is_array($file) || UPLOAD_ERR_OK !== absint($file['error'] ?? UPLOAD_ERR_NO_FILE)) {
            return new WP_Error('idlm_upload_failed', 'The file upload did not complete successfully.');
        }

        $size = absint($file['size'] ?? 0);
        $name = sanitize_file_name(wp_unslash($file['name'] ?? ''));
        $temporary_path = (string) ($file['tmp_name'] ?? '');
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (!$size || $size > $policy['max_bytes']) {
            return new WP_Error('idlm_upload_size', 'The selected file exceeds the 10 MB limit.');
        }
        if (!in_array($extension, $policy['extensions'], true)) {
            return new WP_Error('idlm_upload_extension', 'This file extension is not allowed for the selected field.');
        }

        $detected_mime = '';
        if ($temporary_path && function_exists('finfo_open')) {
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $detected_mime = $file_info ? strtolower((string) finfo_file($file_info, $temporary_path)) : '';
            if ($file_info) {
                finfo_close($file_info);
            }
        }
        if ('' === $detected_mime) {
            $detected_mime = strtolower(sanitize_mime_type($file['type'] ?? ''));
        }
        if (!in_array($detected_mime, $policy['mimes'], true)) {
            return new WP_Error('idlm_upload_mime', 'The file contents do not match an allowed document type.');
        }

        return true;
    }

    public static function credential_fingerprint($credential) {
        return substr(hash('sha256', (string) $credential), 0, 16);
    }

    public static function record_mcp_credential($credential, $previous_credential = '') {
        $now = current_time('mysql');
        $metadata = array(
            'fingerprint' => self::credential_fingerprint($credential),
            'status' => 'active',
            'rotated_at' => $now,
        );
        if ('' !== (string) $previous_credential) {
            $metadata['previous_fingerprint'] = self::credential_fingerprint($previous_credential);
            $metadata['previous_revoked_at'] = $now;
        }
        update_option(self::MCP_METADATA_OPTION, $metadata, false);
        return $metadata;
    }

    /** Write aggregate MCP attribution without request bodies, arguments, or credentials. */
    public static function audit_mcp($request, $rpc_request, $outcome, $elapsed_ms) {
        if (!class_exists('IDLM_Telemetry') || !method_exists('IDLM_Telemetry', 'log')) {
            return;
        }
        $tool = '';
        if (is_array($rpc_request) && 'tools/call' === ($rpc_request['method'] ?? '')) {
            $tool = sanitize_key($rpc_request['params']['name'] ?? '');
        }
        $credential = is_object($request) && method_exists($request, 'get_header')
            ? (string) $request->get_header('X-IDLM-API-Key')
            : '';
        $message = sprintf(
            'MCP request outcome=%s tool=%s key=%s client=%s elapsed_ms=%d',
            sanitize_key($outcome),
            $tool ?: 'protocol',
            self::credential_fingerprint($credential),
            self::client_ip(),
            absint($elapsed_ms)
        );
        IDLM_Telemetry::log('mcp_access', 'info', $message);
    }
}
