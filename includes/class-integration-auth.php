<?php
/** Focused M6 responsibility owner. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Integration_Auth {
    const API_CREDENTIALS_VERSION = '2.0.0';

    public static function maybe_migrate_api_credentials() {
        $legacy_key = trim((string) get_option('idlm_api_webhook_key', ''));
        $migration_pending = self::API_CREDENTIALS_VERSION !== get_option('idlm_api_credentials_version', '');

        $webhook_key = trim((string) IDLM_Secret_Store::get('idlm_webhook_api_key', ''));
        if ('' === $webhook_key && '' === IDLM_Secret_Store::stored('idlm_webhook_api_key')) {
            $webhook_key = $legacy_key ?: self::generate_api_credential();
            IDLM_Secret_Store::set('idlm_webhook_api_key', $webhook_key);
        }

        $mcp_key = trim((string) IDLM_Secret_Store::get('idlm_mcp_api_key', ''));
        if ('' === $mcp_key && '' === IDLM_Secret_Store::stored('idlm_mcp_api_key')) {
            $mcp_key = self::generate_api_credential($webhook_key);
            IDLM_Secret_Store::set('idlm_mcp_api_key', $mcp_key);
        }

        $gadm_password = trim((string) IDLM_Secret_Store::get('idlm_google_gadm_password', ''));
        if ('' === $gadm_password && '' === IDLM_Secret_Store::stored('idlm_google_gadm_password')) {
            $gadm_password = self::generate_api_credential($webhook_key . $mcp_key);
            IDLM_Secret_Store::set('idlm_google_gadm_password', $gadm_password);
        }

        foreach (array('idlm_webhook_api_key','idlm_mcp_api_key','idlm_google_gadm_password') as $required) {
            if ('' === IDLM_Secret_Store::get($required, '', true)) { return false; }
        }

        if (!get_option(IDLM_Production_Security::MCP_METADATA_OPTION, array())) {
            IDLM_Production_Security::record_mcp_credential(IDLM_Secret_Store::get('idlm_mcp_api_key', '', true));
        }

        foreach (array('idlm_api_webhook_key', 'idlm_webhook_legacy_query_until', 'idlm_mcp_legacy_key_until', 'idlm_gadm_legacy_key_until') as $legacy_option) {
            if (null !== get_option($legacy_option, null)) {
                delete_option($legacy_option);
            }
        }
        if ($migration_pending) {
            update_option('idlm_api_credentials_version', self::API_CREDENTIALS_VERSION);
        }
    }

    public static function rotate_api_credential($scope) {
        self::maybe_migrate_api_credentials();
        $scopes = array(
            'webhook' => 'idlm_webhook_api_key',
            'mcp' => 'idlm_mcp_api_key',
        );
        $scope = sanitize_key($scope);
        if (!isset($scopes[$scope])) {
            return new WP_Error('idlm_invalid_credential_scope', 'Invalid API credential scope.');
        }

        $current = (string) IDLM_Secret_Store::get($scopes[$scope], '');
        $credential = self::generate_api_credential($current);
        if (!IDLM_Secret_Store::set($scopes[$scope], $credential)) {
            return new WP_Error('idlm_environment_managed_credential', 'This credential is managed by the environment and cannot be rotated here.');
        }
        if ('mcp' === $scope) {
            IDLM_Production_Security::record_mcp_credential($credential, $current);
        }
        return $credential;
    }

    public static function generate_api_credential($different_from = '') {
        do {
            $credential = wp_generate_password(48, false, false);
        } while ('' !== $different_from && false !== strpos((string) $different_from, $credential));
        return $credential;
    }

    public static function credential_matches($provided, $expected) {
        $provided = (string) $provided;
        $expected = (string) $expected;
        return '' !== $provided && '' !== $expected && hash_equals($expected, $provided);
    }

    public static function validate_scoped_api_key(WP_REST_Request $request, $option_name) {
        self::maybe_migrate_api_credentials();
        $scoped_key = (string) IDLM_Secret_Store::get($option_name, '');
        $header_key = trim((string) $request->get_header('X-IDLM-API-Key'));
        return self::credential_matches($header_key, $scoped_key);
    }

    public static function check_rest_rate_limit($scope, $limit) {
        $remote_address = IDLM_Production_Security::client_ip();
        $window = (int) floor(time() / 60);
        $transient_key = 'idlm_rl_' . md5($scope . '|' . $remote_address . '|' . $window);
        $count = (int) get_transient($transient_key);
        if ($count >= absint($limit)) {
            return new WP_Error(
                'idlm_rest_rate_limited',
                'Too many requests. Please retry shortly.',
                array('status' => 429)
            );
        }
        set_transient($transient_key, $count + 1, 120);
        return true;
    }

    /** WordPress rechecks permissions for its Allow header. Charge the request once,
     * while each permission callback still validates the current credential. */
    private static function check_request_rate_limit(WP_REST_Request $request, $scope, $limit) {
        $attributes = $request->get_attributes();
        $results = isset($attributes['idlm_rate_limit_results']) && is_array($attributes['idlm_rate_limit_results'])
            ? $attributes['idlm_rate_limit_results'] : array();
        if (!array_key_exists($scope, $results)) {
            $results[$scope] = self::check_rest_rate_limit($scope, $limit);
            $attributes['idlm_rate_limit_results'] = $results;
            $request->set_attributes($attributes);
        }
        return $results[$scope];
    }

    public static function rest_permission_error($message = 'Unauthorized: Invalid credentials') {
        return new WP_Error('idlm_rest_unauthorized', $message, array('status' => 401));
    }

    public static function permission_webhook(WP_REST_Request $request) {
        $rate_limit = self::check_request_rate_limit($request, 'webhook', 120);
        if (is_wp_error($rate_limit)) {
            return $rate_limit;
        }
        return self::validate_scoped_api_key($request, 'idlm_webhook_api_key')
            ? true
            : self::rest_permission_error('Unauthorized: Invalid webhook API key');
    }

    public static function permission_google_feed(WP_REST_Request $request) {
        $rate_limit = self::check_request_rate_limit($request, 'google-feed', 30);
        if (is_wp_error($rate_limit)) {
            return $rate_limit;
        }
        return self::validate_gadm_request($request)
            ? true
            : self::rest_permission_error('Unauthorized: Invalid Google feed credentials');
    }

    public static function permission_mcp(WP_REST_Request $request) {
        if (!IDLM_Production_Security::is_mcp_enabled()) {
            return new WP_Error(
                'idlm_mcp_disabled',
                'MCP access is disabled for this environment.',
                array('status' => 503)
            );
        }
        $rate_limit = self::check_request_rate_limit($request, 'mcp', 60);
        if (is_wp_error($rate_limit)) {
            return $rate_limit;
        }
        return self::validate_scoped_api_key($request, 'idlm_mcp_api_key')
            ? true
            : self::rest_permission_error('Unauthorized: Invalid MCP API key');
    }

    public static function validate_gadm_request(WP_REST_Request $request) {
        self::maybe_migrate_api_credentials();
        $gadm_username = get_option('idlm_google_gadm_username', 'google-ads');
        $gadm_password = IDLM_Secret_Store::get('idlm_google_gadm_password', '');

        // 1. Try HTTP Basic Authentication
        $auth_header = $request->get_header('Authorization');
        if (empty($auth_header)) {
            if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $auth_header = wp_unslash($_SERVER['HTTP_AUTHORIZATION']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            } elseif (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $auth_header = wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            }
        }

        if (!empty($auth_header) && preg_match('/Basic\s+(.+)$/i', $auth_header, $matches)) {
            $credentials = base64_decode($matches[1]);
            if ($credentials !== false && strpos($credentials, ':') !== false) {
                list($user, $pass) = explode(':', $credentials, 2);
                if (self::credential_matches(trim($user), trim($gadm_username)) && self::credential_matches(trim($pass), trim($gadm_password))) {
                    return true;
                }
            }
        }

        // CGI/FastCGI environment variables fallback
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0056: validate_gadm_request(): Basic-auth fallback retains exact string credentials after WP unslashing and compares them via credential_matches; provider feeds use scoped credential authority rather than WordPress nonces; debug logging records presence/match booleans only, never credentials.
        $server_user = isset($_SERVER['PHP_AUTH_USER']) && is_string($_SERVER['PHP_AUTH_USER']) ? wp_unslash($_SERVER['PHP_AUTH_USER']) : '';
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0057: validate_gadm_request(): Basic-auth fallback retains exact string credentials after WP unslashing and compares them via credential_matches; provider feeds use scoped credential authority rather than WordPress nonces; debug logging records presence/match booleans only, never credentials.
        $server_pass = isset($_SERVER['PHP_AUTH_PW']) && is_string($_SERVER['PHP_AUTH_PW']) ? wp_unslash($_SERVER['PHP_AUTH_PW']) : '';
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (!empty($server_user) && !empty($server_pass)) {
            if (self::credential_matches(trim($server_user), trim($gadm_username)) && self::credential_matches(trim($server_pass), trim($gadm_password))) {
                return true;
            }
        }

        // 2. Accept the dedicated feed token in a header.
        $api_key = trim((string) $request->get_header('X-IDLM-API-Key'));
        if (self::credential_matches($api_key, $gadm_password)) {
            return true;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- REL007-BOUNDARY-0058: validate_gadm_request(): Basic-auth fallback retains exact string credentials after WP unslashing and compares them via credential_matches; provider feeds use scoped credential authority rather than WordPress nonces; debug logging records presence/match booleans only, never credentials.
            error_log(sprintf(
            // phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log
                'IDLM GADM Auth Failed: Header present: %s, PHP_AUTH_USER present: %s, Username matched: %s',
                !empty($auth_header) ? 'yes' : 'no',
                !empty($server_user) ? 'yes' : 'no',
                (isset($user) && trim($user) === trim($gadm_username)) ? 'yes' : 'no'
            ));
        }

        return false;
    }

    public static function validate_mcp_api_key(WP_REST_Request $request) {
        if (!IDLM_Production_Security::is_mcp_enabled()) {
            return new WP_REST_Response(array(
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => array(
                    'code' => -32003,
                    'message' => 'MCP access is disabled for this environment.'
                )
            ), 503);
        }
        if (!self::validate_scoped_api_key($request, 'idlm_mcp_api_key')) {
            return new WP_REST_Response(array(
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => array(
                    'code' => -32001,
                    'message' => 'Unauthorized: Invalid API Key'
                )
            ), 401);
        }

        return true;
    }

}
