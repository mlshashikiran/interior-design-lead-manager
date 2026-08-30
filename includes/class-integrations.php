<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Integrations {
    const API_CREDENTIALS_VERSION = '2.0.0';

    public static function init() {
        self::maybe_migrate_api_credentials();
        add_action('wp_head', array(__CLASS__, 'inject_utm_tracker_script'));
        add_action('rest_api_init', array(__CLASS__, 'register_webhook_routes'));
        add_filter('rest_authentication_errors', array(__CLASS__, 'bypass_rest_auth_for_webhook'), 99999);
        add_filter('rest_post_dispatch', array(__CLASS__, 'add_google_feed_auth_challenge'), 10, 3);

        if ('1.0.1' !== get_option('idlm_handoff_reconciliation_version', '')) {
            self::reconcile_legacy_qualified_handoffs();
            update_option('idlm_handoff_reconciliation_version', '1.0.1');
        }
    }

    /** Preserve the Basic Auth challenge on permission-callback failures. */
    public static function add_google_feed_auth_challenge($response, $server, $request) {
        if (
            $response instanceof WP_REST_Response &&
            method_exists($request, 'get_route') &&
            401 === $response->get_status() &&
            0 === strpos((string) $request->get_route(), '/interior-design-lead-manager/v1/google-ads-')
        ) {
            $response->header('WWW-Authenticate', 'Basic realm="Google Ads Data Manager Feed"');
        }
        return $response;
    }

    /** Migrate scoped credentials into encrypted storage and remove legacy paths. */
    public static function maybe_migrate_api_credentials() {
        $legacy_key = trim((string) get_option('idlm_api_webhook_key', ''));
        $migration_pending = self::API_CREDENTIALS_VERSION !== get_option('idlm_api_credentials_version', '');

        $webhook_key = trim((string) IDLM_Secret_Store::get('idlm_webhook_api_key', ''));
        if ('' === $webhook_key) {
            $webhook_key = $legacy_key ?: self::generate_api_credential();
            IDLM_Secret_Store::set('idlm_webhook_api_key', $webhook_key);
        }

        $mcp_key = trim((string) IDLM_Secret_Store::get('idlm_mcp_api_key', ''));
        if ('' === $mcp_key) {
            $mcp_key = self::generate_api_credential($webhook_key);
            IDLM_Secret_Store::set('idlm_mcp_api_key', $mcp_key);
        }
        if (!get_option(IDLM_Production_Security::MCP_METADATA_OPTION, array())) {
            IDLM_Production_Security::record_mcp_credential($mcp_key);
        }

        $gadm_password = trim((string) IDLM_Secret_Store::get('idlm_google_gadm_password', ''));
        if ('' === $gadm_password) {
            $gadm_password = self::generate_api_credential($webhook_key . $mcp_key);
            IDLM_Secret_Store::set('idlm_google_gadm_password', $gadm_password);
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

    /** Rotate one scoped API credential and end its legacy compatibility path. */
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

    /** Generate a URL/header-safe credential distinct from the supplied value. */
    private static function generate_api_credential($different_from = '') {
        do {
            $credential = wp_generate_password(48, false, false);
        } while ('' !== $different_from && false !== strpos((string) $different_from, $credential));
        return $credential;
    }

    /** Compare non-empty credentials without exposing timing differences. */
    private static function credential_matches($provided, $expected) {
        $provided = (string) $provided;
        $expected = (string) $expected;
        return '' !== $provided && '' !== $expected && hash_equals($expected, $provided);
    }

    /** Validate a scoped header key. Credentials are never accepted in URLs. */
    private static function validate_scoped_api_key(WP_REST_Request $request, $option_name) {
        self::maybe_migrate_api_credentials();
        $scoped_key = (string) IDLM_Secret_Store::get($option_name, '');
        $header_key = trim((string) $request->get_header('X-IDLM-API-Key'));
        return self::credential_matches($header_key, $scoped_key);
    }

    /** Apply a fixed-window per-IP request limit before route callbacks run. */
    private static function check_rest_rate_limit($scope, $limit) {
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

    private static function rest_permission_error($message = 'Unauthorized: Invalid credentials') {
        return new WP_Error('idlm_rest_unauthorized', $message, array('status' => 401));
    }

    /** REST permission boundary for inbound lead webhooks. */
    public static function permission_webhook(WP_REST_Request $request) {
        $rate_limit = self::check_rest_rate_limit('webhook', 120);
        if (is_wp_error($rate_limit)) {
            return $rate_limit;
        }
        return self::validate_scoped_api_key($request, 'idlm_webhook_api_key')
            ? true
            : self::rest_permission_error('Unauthorized: Invalid webhook API key');
    }

    /** REST permission boundary for Google Data Manager feeds. */
    public static function permission_google_feed(WP_REST_Request $request) {
        $rate_limit = self::check_rest_rate_limit('google-feed', 30);
        if (is_wp_error($rate_limit)) {
            return $rate_limit;
        }
        return self::validate_gadm_request($request)
            ? true
            : self::rest_permission_error('Unauthorized: Invalid Google feed credentials');
    }

    /** REST permission boundary for MCP, including PII-returning tools. */
    public static function permission_mcp(WP_REST_Request $request) {
        if (!IDLM_Production_Security::is_mcp_enabled()) {
            return new WP_Error(
                'idlm_mcp_disabled',
                'MCP access is disabled for this environment.',
                array('status' => 503)
            );
        }
        $rate_limit = self::check_rest_rate_limit('mcp', 60);
        if (is_wp_error($rate_limit)) {
            return $rate_limit;
        }
        return self::validate_scoped_api_key($request, 'idlm_mcp_api_key')
            ? true
            : self::rest_permission_error('Unauthorized: Invalid MCP API key');
    }

    /**
     * Reconcile handoff fields added after historical qualified ownership existed.
     *
     * Existing non-pre-sales ownership is a completed handoff. Pre-sales-owned,
     * unassigned, or orphaned ownership remains manager action instead of being
     * mislabeled by the schema default as a new pending handoff state.
     */
    private static function reconcile_legacy_qualified_handoffs() {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $leads = $wpdb->get_results(
            "SELECT id, assigned_rep_id, qualified_at, updated_at, created_at
             FROM {$table_leads}
             WHERE handoff_status = 'not_ready' AND lead_quality = 'Qualified'
               AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
        );

        foreach ($leads as $lead) {
            $owner_id = absint($lead->assigned_rep_id);
            $owner = $owner_id ? get_userdata($owner_id) : false;
            $is_pre_sales = $owner && in_array('idlm_pre_sales', (array) $owner->roles, true);

            if ($owner && !$is_pre_sales) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update(
                    $table_leads,
                    array(
                        'handoff_status'           => 'manually_assigned',
                        'handoff_at'               => $lead->updated_at ?: ($lead->qualified_at ?: $lead->created_at),
                        'handoff_method'           => 'legacy_manager_reconciled',
                        'handoff_exception_reason' => '',
                    ),
                    array('id' => absint($lead->id), 'handoff_status' => 'not_ready'),
                    array('%s', '%s', '%s', '%s'),
                    array('%d', '%s')
                );
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $table_leads,
                array(
                    'handoff_status' => 'awaiting_manager',
                    'intake_owner_id' => $is_pre_sales ? $owner_id : 0,
                ),
                array('id' => absint($lead->id), 'handoff_status' => 'not_ready'),
                array('%s', '%d'),
                array('%d', '%s')
            );
        }
    }

    /**
     * Bypass global REST API auth restrictions for our public webhook endpoint
     */
    public static function bypass_rest_auth_for_webhook($errors) {
        $is_public_route = false;

        // Check query parameter rest_route
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $rest_route = isset($_GET['rest_route']) ? sanitize_text_field(wp_unslash($_GET['rest_route'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (empty($rest_route) && isset($_POST['rest_route'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $rest_route = sanitize_text_field(wp_unslash($_POST['rest_route']));
        }
        if (empty($rest_route) && isset($GLOBALS['wp']->query_vars['rest_route'])) {
            $rest_route = $GLOBALS['wp']->query_vars['rest_route'];
        }

        if (!empty($rest_route)) {
            $clean_route = untrailingslashit($rest_route);
            if ($clean_route === '/interior-design-lead-manager/v1/webhook' || 
                $clean_route === '/interior-design-lead-manager/v1/mcp' || 
                strpos($clean_route, '/interior-design-lead-manager/v1/google-ads-') === 0) {
                $is_public_route = true;
            }
        }

        // Check REQUEST_URI path strictly
        if (!$is_public_route && isset($_SERVER['REQUEST_URI'])) {
            $path = wp_parse_url(urldecode(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))), PHP_URL_PATH);
            if ($path && (
                preg_match('#/wp-json/interior-design-lead-manager/v1/webhook/?$#', $path) || 
                preg_match('#/wp-json/interior-design-lead-manager/v1/mcp/?$#', $path) || 
                preg_match('#/wp-json/interior-design-lead-manager/v1/google-ads-#', $path)
            )) {
                $is_public_route = true;
            }
        }

        if ($is_public_route) {
            return true; // Bypass global errors or login requirements
        }

        return $errors;
    }

    /**
     * Inject front-end JavaScript to capture UTMs and Ad Click IDs in cookies
     */
    public static function inject_utm_tracker_script() {
        ?>
        <script type="text/javascript">
        (function() {
            var params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'li_fat_id'];
            var urlParams = new URLSearchParams(window.location.search);
            var domain = window.location.hostname;
            
            params.forEach(function(param) {
                if (urlParams.has(param)) {
                    var val = urlParams.get(param);
                    // Store in cookie for 30 days
                    var date = new Date();
                    date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
                    document.cookie = "idlm_" + param + "=" + encodeURIComponent(val) + "; expires=" + date.toUTCString() + "; path=/; domain=" + domain + "; SameSite=Lax";
                }
            });

            // Store landing page URL in cookie if not already set
            var landingCookieName = "idlm_landing_page";
            if (!document.cookie.match(new RegExp('(^|; )' + landingCookieName + '='))) {
                var currentUrl = window.location.href.split('?')[0];
                var date = new Date();
                date.setTime(date.getTime() + (30 * 24 * 60 * 60 * 1000));
                document.cookie = landingCookieName + "=" + encodeURIComponent(currentUrl) + "; expires=" + date.toUTCString() + "; path=/; domain=" + domain + "; SameSite=Lax";
            }
        })();
        </script>
        <?php
    }

    /**
     * Retrieve captured UTMs/Click IDs from cookies
     */
    public static function get_captured_attribution() {
        $attribution = array();
        $params = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'li_fat_id');

        foreach ($params as $param) {
            $cookie_name = 'idlm_' . $param;
            if (!empty($_COOKIE[$cookie_name])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $attribution[$param] = sanitize_text_field(urldecode(wp_unslash($_COOKIE[$cookie_name])));
            } else {
                $attribution[$param] = '';
            }
        }

        // Read landing page from cookie
        if (!empty($_COOKIE['idlm_landing_page'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $attribution['landing_page'] = esc_url_raw(urldecode(wp_unslash($_COOKIE['idlm_landing_page'])));
        } else {
            $attribution['landing_page'] = '';
        }

        return $attribution;
    }

    /**
     * Register REST API endpoint for third-party webhooks
     */
    public static function register_webhook_routes() {
        register_rest_route('interior-design-lead-manager/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_webhook_request'),
            'permission_callback' => array(__CLASS__, 'permission_webhook')
        ));

        register_rest_route('interior-design-lead-manager/v1', '/google-ads-conversions', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'handle_google_ads_conversions_feed'),
            'permission_callback' => array(__CLASS__, 'permission_google_feed')
        ));

        register_rest_route('interior-design-lead-manager/v1', '/google-ads-conversions\.csv', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'handle_google_ads_conversions_feed'),
            'permission_callback' => array(__CLASS__, 'permission_google_feed')
        ));

        register_rest_route('interior-design-lead-manager/v1', '/google-ads-audience', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'handle_google_ads_audience_feed'),
            'permission_callback' => array(__CLASS__, 'permission_google_feed')
        ));

        register_rest_route('interior-design-lead-manager/v1', '/google-ads-audience/(?P<list_id>[a-zA-Z0-9_-]+)\.csv', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'handle_google_ads_audience_feed'),
            'permission_callback' => array(__CLASS__, 'permission_google_feed')
        ));

        register_rest_route('interior-design-lead-manager/v1', '/mcp', array(
            array(
                'methods' => 'GET',
                'callback' => array(__CLASS__, 'handle_mcp_get_info'),
                'permission_callback' => array(__CLASS__, 'permission_mcp')
            ),
            array(
                'methods' => 'POST',
                'callback' => array(__CLASS__, 'handle_mcp_post'),
                'permission_callback' => array(__CLASS__, 'permission_mcp')
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array(__CLASS__, 'handle_mcp_delete_session'),
                'permission_callback' => array(__CLASS__, 'permission_mcp')
            )
        ));
    }

    /**
     * Validate Google Ads Data Manager credentials using Basic Auth or a
     * dedicated header token.
     */
    private static function validate_gadm_request(WP_REST_Request $request) {
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
        $server_user = isset($_SERVER['PHP_AUTH_USER']) ? $_SERVER['PHP_AUTH_USER'] : '';
        $server_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
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
            error_log(sprintf(
                'IDLM GADM Auth Failed: Header present: %s, PHP_AUTH_USER present: %s, Username matched: %s',
                !empty($auth_header) ? 'yes' : 'no',
                !empty($server_user) ? 'yes' : 'no',
                (isset($user) && trim($user) === trim($gadm_username)) ? 'yes' : 'no'
            ));
        }

        return false;
    }

    /**
     * Send a proper 401 response with WWW-Authenticate header for GADM feeds.
     * Google Ads Data Manager requires the standard HTTP auth challenge header.
     */
    private static function send_gadm_401() {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="Google Ads Data Manager Feed"');
        header('Content-Type: application/json; charset=utf-8');
        // Remove WordPress REST API headers that confuse GADM
        header_remove('X-Robots-Tag');
        header_remove('Link');
        header_remove('X-Content-Type-Options');
        header_remove('Access-Control-Expose-Headers');
        header_remove('Access-Control-Allow-Headers');
        echo wp_json_encode(array('success' => false, 'message' => 'Unauthorized: Invalid Credentials'));
        if (!defined('IDLM_TESTING')) {
            exit;
        }
    }

    /**
     * Clean WordPress REST API headers so GADM sees a clean static-file response
     */
    private static function clean_rest_headers_for_gadm() {
        header_remove('X-Robots-Tag');
        header_remove('Link');
        header_remove('X-Content-Type-Options');
        header_remove('Access-Control-Expose-Headers');
        header_remove('Access-Control-Allow-Headers');
    }

    /**
     * Expose a secure CSV conversion feed for Google Ads OCI
     */
    public static function handle_google_ads_conversions_feed(WP_REST_Request $request) {
        if (!self::validate_gadm_request($request)) {
            self::send_gadm_401();
            return;
        }

        self::clean_rest_headers_for_gadm();

        // Buffer the CSV so we can set Content-Length (makes it look like a static file to GADM)
        ob_start();
        if (class_exists('IDLM_CAPI')) {
            IDLM_CAPI::generate_google_ads_conversions_csv_response(false);
        }
        $csv_content = ob_get_clean();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=google-ads-offline-conversions-' . gmdate('Y-m-d') . '.csv');
        header('Content-Length: ' . strlen($csv_content));
        header('Cache-Control: no-cache, must-revalidate');
        echo $csv_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if (!defined('IDLM_TESTING')) {
            exit;
        }
    }

    /**
     * Expose a secure CSV audience feed for Google Ads Data Manager Customer Match
     */
    public static function handle_google_ads_audience_feed(WP_REST_Request $request) {
        if (!self::validate_gadm_request($request)) {
            self::send_gadm_401();
            return;
        }

        $list_id = $request->get_param('list_id');
        if (empty($list_id)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Missing list_id parameter'), 400);
        }

        $list_id = trim($list_id);

        if (!class_exists('IDLM_Audiences')) {
            require_once dirname(__FILE__) . '/class-audiences.php';
        }

        // Find the rule matching the list_id
        $rules = get_option('idlm_audience_sync_rules', array());
        $matched_rule = null;

        foreach ($rules as $rule) {
            if (isset($rule['platform']) && $rule['platform'] === 'google') {
                $target_id = ltrim(trim($rule['audience_id']), '/');
                $target_id = preg_replace('/\/userlists\//i', '/userLists/', $target_id);

                if ($target_id === $list_id || basename($target_id) === $list_id || $rule['audience_id'] === $list_id) {
                    $matched_rule = $rule;
                    break;
                }
            }
        }

        if (!$matched_rule) {
            return new WP_REST_Response(array('success' => false, 'message' => 'No active Google Ads audience rule found matching target ID: ' . esc_html($list_id)), 404);
        }

        self::clean_rest_headers_for_gadm();

        // Buffer the CSV so we can set Content-Length
        ob_start();
        $leads = IDLM_Audiences::get_leads_matching_rule($matched_rule);
        IDLM_Audiences::generate_google_ads_audience_csv_response($leads, $list_id, false);
        $csv_content = ob_get_clean();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($list_id) . '-' . gmdate('Y-m-d') . '.csv');
        header('Content-Length: ' . strlen($csv_content));
        header('Cache-Control: no-cache, must-revalidate');
        echo $csv_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if (!defined('IDLM_TESTING')) {
            exit;
        }
    }

    /**
     * Process incoming webhook payloads from Ad Platforms
     */
    public static function handle_webhook_request(WP_REST_Request $request) {
        if (!self::validate_scoped_api_key($request, 'idlm_webhook_api_key')) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Unauthorized: Invalid API Key'), 401);
        }

        $payload = $request->get_json_params();
        if (empty($payload)) {
            $payload = $request->get_body_params();
        }

        if (empty($payload)) {
            // Read raw body if still empty
            $raw_body = $request->get_body();
            $payload = json_decode($raw_body, true);
        }

        if (empty($payload)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Bad Request: Empty Payload'), 400);
        }

        $platform = sanitize_key($request->get_param('platform')); // meta, google, linkedin, website, walkin
        if (!in_array($platform, array('meta', 'google', 'linkedin', 'website', 'walkin'), true)) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid Platform Specified'), 400);
        }

        // Forminator removes is_test from its sample body and sends X-Hook-Test: true
        // while saving a webhook. Retain body support for compatible integrations.
        $flat_payload = self::flatten_array($payload);
        $hook_test_header = $request->get_header('X-Hook-Test');
        $is_test_payload = isset($flat_payload['is_test']) && self::is_truthy_webhook_value($flat_payload['is_test']);
        if (self::is_truthy_webhook_value($hook_test_header) || $is_test_payload) {
            return new WP_REST_Response(
                array(
                    'success' => true,
                    'message' => 'Webhook connection verified successfully',
                ),
                200
            );
        }

        // Apply field mappings defined in settings
        $mapped_data = self::apply_field_mappings($payload, $platform);
        if (empty($mapped_data['email']) && empty($mapped_data['phone'])) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Failed to parse required fields (Email or Phone) based on mapping'), 422);
        }

        // Create one canonical lead or enrich the existing canonical record.
        // Every submission is retained as an attribution touchpoint.
        $result = IDLM_DB::upsert_inbound_lead($mapped_data, $platform, $payload);
        if ($result) {
            $lead_id = absint($result['lead_id']);
            if (!empty($result['created'])) {
                self::route_lead_assignment($lead_id, $mapped_data['source']);
            }

            return new WP_REST_Response(array(
                'success' => true,
                'lead_id' => $lead_id,
                'created' => !empty($result['created']),
                'matched_existing' => !empty($result['matched']),
                'message' => !empty($result['created']) ? 'Lead created successfully' : 'Submission matched to existing lead',
            ), 200);
        }

        return new WP_REST_Response(array('success' => false, 'message' => 'Server Error: DB Insert Failed'), 500);
    }

    /**
     * Map webhook payload parameters to DB columns
     */
    private static function apply_field_mappings($payload, $platform) {
        $mappings = get_option('idlm_field_mappings_' . $platform, array());
        $lead_data = array(
            'first_name'         => '',
            'last_name'          => '',
            'email'              => '',
            'phone'              => '',
            'budget'             => '',
            'project_timeline'   => '',
            'property_location'  => '',
            'source'             => 'walkin' === $platform ? 'Offline/Walk-in' : ucfirst($platform) . ' Ads',
            'property_type'      => '',
            'num_rooms'          => '',
            'preferred_studio'   => '',
            'utm_source'         => '',
            'utm_medium'         => '',
            'utm_campaign'       => '',
            'utm_adgroup'        => '',
            'utm_term'           => '',
            'utm_content'        => '',
            'gclid'              => '',
            'gbraid'             => '',
            'wbraid'             => '',
            'fbclid'             => '',
            'li_fat_id'          => '',
            'fb_lead_id'         => '',
            'landing_page'       => '',
            'conversion_time'    => '',
            'referrer_details'   => ''
        );

        if ($platform === 'website') {
            $lead_data['source'] = 'Website';
        }

        // Flatten payload for easy mapping (dot notation if needed, but simple key lookup first)
        $flat_payload = self::flatten_array($payload);

        foreach ($mappings as $db_field => $payload_key) {
            if (empty($payload_key)) {
                continue;
            }
            if (isset($flat_payload[$payload_key])) {
                $lead_data[$db_field] = sanitize_text_field($flat_payload[$payload_key]);
            }
        }

        // Fallback checks for click IDs in webhook payload itself if map was skipped
        if (empty($lead_data['gclid']) && isset($flat_payload['gclid'])) {
            $lead_data['gclid'] = sanitize_text_field($flat_payload['gclid']);
        }
        if (empty($lead_data['gbraid']) && isset($flat_payload['gbraid'])) {
            $lead_data['gbraid'] = sanitize_text_field($flat_payload['gbraid']);
        }
        if (empty($lead_data['wbraid']) && isset($flat_payload['wbraid'])) {
            $lead_data['wbraid'] = sanitize_text_field($flat_payload['wbraid']);
        }
        if (empty($lead_data['fbclid']) && isset($flat_payload['fbclid'])) {
            $lead_data['fbclid'] = sanitize_text_field($flat_payload['fbclid']);
        }
        if (empty($lead_data['li_fat_id']) && isset($flat_payload['li_fat_id'])) {
            $lead_data['li_fat_id'] = sanitize_text_field($flat_payload['li_fat_id']);
        }
        if (empty($lead_data['fb_lead_id'])) {
            if (isset($flat_payload['lead_id'])) {
                $lead_data['fb_lead_id'] = sanitize_text_field($flat_payload['lead_id']);
            } elseif (isset($flat_payload['fb_lead_id'])) {
                $lead_data['fb_lead_id'] = sanitize_text_field($flat_payload['fb_lead_id']);
            }
        }

        // Merge cookie attribution for website/frontend submissions
        if ($platform === 'website') {
            $cookies = self::get_captured_attribution();
            foreach ($cookies as $ckey => $cval) {
                if (empty($lead_data[$ckey]) && !empty($cval)) {
                    $lead_data[$ckey] = $cval;
                }
            }
        }

        // Normalize and dynamically classify lead source based on click IDs and UTMs
        $lead_data['source'] = self::classify_lead_source($lead_data, $platform);

        // Normalize Property Type & Number of Rooms values dynamically across channels
        $lead_data['property_type'] = self::normalize_property_type($lead_data['property_type']);
        $lead_data['num_rooms']     = self::normalize_num_rooms($lead_data['num_rooms']);
        $lead_data['budget']        = self::normalize_budget($lead_data['budget']);

        return $lead_data;
    }

    /**
     * Dynamically classify lead source based on click IDs and UTM parameter patterns
     */
    private static function classify_lead_source($lead_data, $platform) {
        // Reception submissions are deliberately isolated from ad and website attribution.
        if ('walkin' === $platform) {
            return 'Offline/Walk-in';
        }

        // If the platform is explicitly passed as 'google', 'meta', or 'linkedin' (e.g. from dedicated webhook), keep that classification.
        if (in_array($platform, array('google', 'meta', 'linkedin'))) {
            return ucfirst($platform) . ' Ads';
        }

        // Initialize / keep existing source if not Website/empty
        $source = !empty($lead_data['source']) ? $lead_data['source'] : 'Website';
        if ($source !== 'Website') {
            return $source;
        }

        $utm_source = !empty($lead_data['utm_source']) ? strtolower(trim($lead_data['utm_source'])) : '';
        $utm_medium = !empty($lead_data['utm_medium']) ? strtolower(trim($lead_data['utm_medium'])) : '';

        // 1. Google Ads Attribution Check
        $has_google_click_id = !empty($lead_data['gclid']) || !empty($lead_data['gbraid']) || !empty($lead_data['wbraid']);
        $has_google_utms = (strpos($utm_source, 'google') !== false);
        $is_google = $has_google_click_id || $has_google_utms;

        // 2. Meta Ads Attribution Check
        $has_meta_click_id = !empty($lead_data['fbclid']) || !empty($lead_data['fb_lead_id']);
        $has_meta_utms = (
            strpos($utm_source, 'facebook') !== false || 
            strpos($utm_source, 'instagram') !== false || 
            strpos($utm_source, 'meta') !== false || 
            $utm_source === 'fb' || 
            strpos($utm_source, 'fb_') !== false || 
            strpos($utm_source, 'fb-') !== false || 
            $utm_source === 'ig' || 
            strpos($utm_source, 'ig_') !== false || 
            strpos($utm_source, 'ig-') !== false
        );
        $is_meta = $has_meta_click_id || $has_meta_utms;

        // 3. Resolve Dual Attribution conflicts using last-touch UTM logic
        if ($is_google && $is_meta) {
            if ($has_google_utms && !$has_meta_utms) {
                return 'Google Ads';
            } elseif ($has_meta_utms && !$has_google_utms) {
                return 'Meta Ads';
            } else {
                // If both UTMs match or neither has UTMs matching, fall back to Google Ads
                return 'Google Ads';
            }
        }

        if ($is_google) {
            return 'Google Ads';
        }

        if ($is_meta) {
            return 'Meta Ads';
        }

        return 'Website';
    }

    /**
     * Standardize Property Types to system options: Apartment, Villa, Independent House, Commercial
     */
    private static function normalize_property_type($val) {
        $val = strtolower(trim($val));
        if (empty($val)) {
            return '';
        }

        if (in_array($val, array('apt', 'apartment', 'apartments', 'flat', 'flats', 'unit', 'condo', 'condominium'))) {
            return 'Apartment';
        }
        if (in_array($val, array('villa', 'villas', 'row house', 'rowhouse', 'bungalow', 'bungalows'))) {
            return 'Villa';
        }
        if (in_array($val, array('independent house', 'house', 'plot', 'residential', 'builder floor', 'individual house'))) {
            return 'Independent House';
        }
        if (in_array($val, array('commercial', 'office', 'shop', 'retail', 'showroom', 'commercial space'))) {
            return 'Commercial';
        }

        return ucfirst($val); // Fallback to capitalized original if no match
    }

    /**
     * Standardize Room count parameters to standard system keys dynamically based on active settings options
     */
    public static function normalize_num_rooms($val) {
        $val = trim($val);
        if (empty($val)) {
            return '';
        }

        $options = get_option('idlm_custom_num_rooms', array('1 BHK', '2 BHK', '3 BHK', '4 BHK', '4+ BHK'));
        $val_clean = strtolower($val);

        // Replace word-numbers with digits
        $word_map = array('one' => '1', 'two' => '2', 'three' => '3', 'four' => '4', 'five' => '5', 'six' => '6');
        foreach ($word_map as $word => $digit) {
            if ($val_clean === $word) {
                $val_clean = $digit;
                break;
            }
        }

        // Cleaned input (lowercase, spaces and hyphens removed)
        $val_stripped = str_replace(array(' ', '-', '_'), '', $val_clean);

        // 1. First Pass: Exact or stripped exact match
        foreach ($options as $opt) {
            $opt_clean = strtolower($opt);
            if ($val_clean === $opt_clean) {
                return $opt;
            }
            $opt_stripped = str_replace(array(' ', '-', '_'), '', $opt_clean);
            if ($val_stripped === $opt_stripped) {
                return $opt;
            }
        }

        // 2. Second Pass: Substring match (either option inside input, or input inside option)
        foreach ($options as $opt) {
            $opt_clean = strtolower($opt);
            $opt_stripped = str_replace(array(' ', '-', '_'), '', $opt_clean);

            if (!empty($opt_stripped) && !empty($val_stripped)) {
                if (stripos($val_stripped, $opt_stripped) !== false || stripos($opt_stripped, $val_stripped) !== false) {
                    return $opt;
                }
            }
        }

        // 3. Third Pass: Standard patterns fallback (checking largest first)
        if (preg_match('/(?:5|6|7|8|9|10|4\+)/i', $val_clean) || stripos($val_clean, 'five') !== false || stripos($val_clean, 'more') !== false) {
            return in_array('4+ BHK', $options) ? '4+ BHK' : (in_array('4 BHK', $options) ? '4 BHK' : $options[0]);
        }
        if (preg_match('/4/i', $val_clean) || stripos($val_clean, 'four') !== false) {
            return in_array('4 BHK', $options) ? '4 BHK' : $options[0];
        }
        if (preg_match('/3/i', $val_clean) || stripos($val_clean, 'three') !== false) {
            return in_array('3 BHK', $options) ? '3 BHK' : $options[0];
        }
        if (preg_match('/2/i', $val_clean) || stripos($val_clean, 'two') !== false) {
            return in_array('2 BHK', $options) ? '2 BHK' : $options[0];
        }
        if (preg_match('/1/i', $val_clean) || stripos($val_clean, 'one') !== false) {
            return in_array('1 BHK', $options) ? '1 BHK' : $options[0];
        }

        return $val; // Fallback
    }

    /**
     * Parse verbose budget strings (e.g. "Villa - Interiors starting @ ₹15-20 lakhs") 
     * and isolate standard budget/currency ranges/values.
     */
    public static function normalize_budget($val) {
        $val = trim($val);
        if (empty($val)) {
            return '';
        }

        $text = trim(preg_replace('/\s+/', ' ', $val));

        // Pattern 1: Sign + digits + optional range + optional suffix (with proper suffix ordering)
        $regex = '/(?:(?:₹|Rs\.?|Rs|INR|USD|\$|€|£)\s*)[\d,.]+(?:\s*(?:lakhs?|crores?|cr|m|k|l)?\s*(?:-|to|and)\s*(?:(?:₹|Rs\.?|Rs|INR|USD|\$|€|£)\s*)?[\d,.]+)?(?:\s*(?:lakhs?|crores?|cr|m|k|l))?/i';
        if (preg_match($regex, $text, $matches)) {
            return trim($matches[0]);
        }

        // Pattern 2: Digits + optional range + suffix (no leading sign)
        $regex_suffix = '/[\d,.]+(?:\s*(?:lakhs?|crores?|cr|m|k|l)?\s*(?:-|to|and)\s*[\d,.]+)?\s*(?:lakhs?|crores?|cr|m|k|l)\b/i';
        if (preg_match($regex_suffix, $text, $matches)) {
            return trim($matches[0]);
        }

        // Pattern 3: Standalone sign + digits
        $regex_fallback = '/(?:(?:₹|Rs\.?|Rs|INR|USD|\$|€|£)\s*)[\d,.]+/i';
        if (preg_match($regex_fallback, $text, $matches)) {
            return trim($matches[0]);
        }

        // Pattern 4: Standalone large numeric group fallback (length >= 4)
        if (preg_match_all('/\b[\d,.-]+\b/', $text, $num_matches)) {
            foreach ($num_matches[0] as $num) {
                $cleaned_num = str_replace(array(',', '-'), '', $num);
                if (strlen($cleaned_num) >= 4) {
                    return trim($num);
                }
            }
        }

        return $val;
    }

    /**
     * Flatten a multi-dimensional array with dot notation
     */
    private static function flatten_array($array, $prefix = '') {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, self::flatten_array($value, $prefix . $key . '.'));
            } else {
                $result[$prefix . $key] = $value;
                // Also store raw key for simpler mapping lookups
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Determine whether a webhook control value explicitly represents true.
     */
    private static function is_truthy_webhook_value($value) {
        if (true === $value || 1 === $value) {
            return true;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
    }

    /**
     * Return a stable, sanitized set of WordPress user IDs.
     */
    private static function normalize_rep_pool($rep_ids) {
        $rep_ids = array_values(array_unique(array_filter(array_map('absint', (array) $rep_ids))));
        sort($rep_ids, SORT_NUMERIC);
        return $rep_ids;
    }

    /**
     * Users who receive new leads during Pre-Sales Intake.
     */
    private static function get_all_pre_sales_pool() {
        return self::normalize_rep_pool(get_users(array(
            'role'    => 'idlm_pre_sales',
            'fields'  => 'ID',
            'orderby' => 'ID',
            'order'   => 'ASC',
        )));
    }

    /**
     * Available users who receive new Pre-Sales Intake leads.
     */
    private static function get_pre_sales_pool() {
        $role_pool = self::get_all_pre_sales_pool();
        $available_pool = IDLM_Settings::get('idlm_active_pre_sales_reps', null);
        if (null === $available_pool) {
            return self::filter_pool_by_capacity($role_pool);
        }

        return self::filter_pool_by_capacity(array_values(array_intersect($role_pool, self::normalize_rep_pool($available_pool))));
    }

    /**
     * Configured active representatives, optionally excluding pre-sales users.
     */
    private static function get_active_rep_pool($exclude_pre_sales = false) {
        $rep_ids = array_values(array_filter(
            self::normalize_rep_pool(IDLM_Settings::get('idlm_active_assignment_reps', array())),
            static function($rep_id) {
                return (bool) get_userdata($rep_id);
            }
        ));
        if (!$exclude_pre_sales) {
            return self::filter_pool_by_capacity($rep_ids);
        }

        $rep_ids = array_values(array_filter($rep_ids, static function($rep_id) {
            $user = get_userdata($rep_id);
            return $user && !in_array('idlm_pre_sales', (array) $user->roles, true);
        }));
        return self::filter_pool_by_capacity($rep_ids);
    }

    /**
     * Configured sales owners without applying the live capacity filter.
     *
     * This is used to distinguish a valid manager-completed handoff from a
     * backlog record that still needs routing. Capacity is applied separately
     * immediately before each automatic assignment.
     */
    private static function get_configured_sales_pool() {
        $rep_ids = array_values(array_filter(
            self::normalize_rep_pool(IDLM_Settings::get('idlm_active_assignment_reps', array())),
            static function($rep_id) {
                $user = get_userdata($rep_id);
                return $user && !in_array('idlm_pre_sales', (array) $user->roles, true);
            }
        ));

        return $rep_ids;
    }

    /**
     * Remove representatives who have reached the configured active-lead cap.
     * Zero disables the cap. The same definition is used across all routing modes.
     */
    private static function filter_pool_by_capacity($rep_ids) {
        global $wpdb;
        $rep_ids = self::normalize_rep_pool($rep_ids);
        $maximum = max(0, absint(IDLM_Settings::get('idlm_max_active_leads_per_rep', 30)));
        if (0 === $maximum || !$rep_ids) {
            return $rep_ids;
        }
        $placeholders = implode(',', array_fill(0, count($rep_ids), '%d'));
        $counts = array();
        foreach ($wpdb->get_results($wpdb->prepare(
            "SELECT assigned_rep_id, COUNT(*) active_count
             FROM {$wpdb->prefix}idlm_leads
             WHERE assigned_rep_id IN ({$placeholders}) AND is_duplicate = 0
               AND COALESCE(stage, '') NOT IN ('Won', 'Lost')
               AND COALESCE(lead_quality, '') != 'Unqualified'
             GROUP BY assigned_rep_id",
            $rep_ids
        )) as $row) {
            $counts[absint($row->assigned_rep_id)] = (int) $row->active_count;
        }
        return array_values(array_filter($rep_ids, static function($rep_id) use ($counts, $maximum) {
            return ($counts[$rep_id] ?? 0) < $maximum;
        }));
    }

    /** Public capacity check used to protect manual reassignment as well. */
    public static function rep_has_capacity($rep_id) {
        $rep_id = absint($rep_id);
        return 0 === $rep_id || in_array($rep_id, self::filter_pool_by_capacity(array($rep_id)), true);
    }

    public static function get_rep_capacity_status($rep_id) {
        global $wpdb;
        $rep_id = absint($rep_id);
        $maximum = max(0, absint(IDLM_Settings::get('idlm_max_active_leads_per_rep', 30)));
        $active = $rep_id ? (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads
             WHERE assigned_rep_id = %d AND is_duplicate = 0
               AND COALESCE(stage, '') NOT IN ('Won', 'Lost')
               AND COALESCE(lead_quality, '') != 'Unqualified'",
            $rep_id
        )) : 0;
        return array(
            'maximum' => $maximum,
            'active' => $active,
            'remaining' => 0 === $maximum ? PHP_INT_MAX : max(0, $maximum - $active),
        );
    }

    /**
     * Match a representative pool to a preferred studio.
     */
    private static function get_studio_rep_pool($rep_ids, $preferred_studio) {
        $preferred_studio = trim((string) $preferred_studio);
        if ('' === $preferred_studio) {
            return array();
        }

        $rep_studios = IDLM_Settings::get('idlm_rep_studio_assignments', array());
        if (!is_array($rep_studios)) {
            return array();
        }

        $matched = array();
        foreach (self::normalize_rep_pool($rep_ids) as $rep_id) {
            $rep_studio = isset($rep_studios[$rep_id]) ? trim((string) $rep_studios[$rep_id]) : '';
            if ('' !== $rep_studio && 0 === strcasecmp($rep_studio, $preferred_studio)) {
                $matched[] = $rep_id;
            }
        }

        return $matched;
    }

    /**
     * Pick the next representative using an isolated rotation counter.
     */
    private static function select_round_robin_rep($rep_ids, $rotation_key) {
        $rep_ids = self::normalize_rep_pool($rep_ids);
        if (empty($rep_ids)) {
            return 0;
        }

        $next_index = IDLM_Routing_State::next_index($rotation_key, count($rep_ids));
        return $rep_ids[$next_index];
    }

    /**
     * Assign only when ownership still matches what the router inspected.
     */
    private static function assign_lead_atomically($lead_id, $expected_owner_id, $assigned_rep_id, $activity_message, $qualified_only = false, $assignment_context = '') {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        $qualified_sql = $qualified_only
            ? " AND lead_quality = 'Qualified' AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
            : '';
        $set_sql = 'assigned_rep_id = %d, assigned_at = %s, updated_at = %s';
        if ('intake' === $assignment_context) {
            $set_sql .= ', intake_owner_id = %d';
        } elseif (0 === strpos($assignment_context, 'qualified_')) {
            $set_sql .= ", handoff_status = 'auto_assigned', handoff_at = %s, handoff_method = %s, handoff_exception_reason = ''";
        }

        $assignment_time = current_time('mysql');
        $params = array(absint($assigned_rep_id), $assignment_time, $assignment_time);
        if ('intake' === $assignment_context) {
            $params[] = absint($assigned_rep_id);
        } elseif (0 === strpos($assignment_context, 'qualified_')) {
            $params[] = current_time('mysql');
            $params[] = sanitize_key($assignment_context);
        }
        $params[] = absint($lead_id);
        $params[] = absint($expected_owner_id);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_leads}
             SET {$set_sql}
             WHERE id = %d AND assigned_rep_id = %d{$qualified_sql}",
            $params
        ));

        if (1 !== $updated) {
            return false;
        }

        IDLM_DB::log_activity($lead_id, 'assignment', $activity_message, 0);
        IDLM_Read_Model_Cache::invalidate('lead_assignment');

        if ('intake' === $assignment_context) {
            IDLM_DB::create_presales_followup_tasks($lead_id, $assigned_rep_id);
        }

        if (0 === strpos($assignment_context, 'qualified_')) {
            $task_policy = IDLM_Settings::get('idlm_handoff_task_policy', 'transfer');
            if ('transfer' === $task_policy) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update(
                    $table_tasks,
                    array('assigned_rep_id' => absint($assigned_rep_id), 'updated_at' => current_time('mysql')),
                    array('lead_id' => absint($lead_id), 'assigned_rep_id' => absint($expected_owner_id), 'status' => 'pending')
                );
            } elseif ('complete_qualification' === $task_policy) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $reconciled = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_tasks}
                     SET status = 'completed', completed_at = %s, updated_at = %s
                     WHERE lead_id = %d AND assigned_rep_id = %d AND status = 'pending'
                       AND LOWER(task_type) = 'qualification'",
                    current_time('mysql'),
                    current_time('mysql'),
                    absint($lead_id),
                    absint($expected_owner_id)
                ));
            }

            IDLM_DB::ensure_qualified_handoff_task($lead_id, $assigned_rep_id);

            self::send_handoff_notifications($lead_id, $expected_owner_id, $assigned_rep_id, $activity_message);
        }
        return true;
    }

    /**
     * Notify handoff participants when explicitly enabled by an administrator.
     */
    private static function send_handoff_notifications($lead_id, $old_owner_id, $new_owner_id, $activity_message) {
        if ('1' !== IDLM_Settings::get('idlm_handoff_notifications_enabled', '0')) {
            return;
        }

        global $wpdb;
        $lead = $wpdb->get_row($wpdb->prepare(
            "SELECT first_name, last_name, preferred_studio FROM {$wpdb->prefix}idlm_leads WHERE id = %d",
            absint($lead_id)
        ));
        $old_owner = get_userdata($old_owner_id);
        $new_owner = get_userdata($new_owner_id);
        $recipients = array_filter(array(
            $old_owner ? $old_owner->user_email : '',
            $new_owner ? $new_owner->user_email : '',
        ), 'is_email');
        if (empty($recipients)) {
            return;
        }

        $lead_name = $lead ? trim($lead->first_name . ' ' . $lead->last_name) : 'Lead #' . absint($lead_id);
        $subject = sprintf('[Lead Manager] Qualified lead assigned: %s', $lead_name);
        $body = $activity_message . "\n\nLead: " . $lead_name;
        if ($lead && !empty($lead->preferred_studio)) {
            $body .= "\nPreferred Studio: " . $lead->preferred_studio;
        }
        wp_mail(array_values(array_unique($recipients)), $subject, $body);
    }

    /**
     * Record a routing exception without adding duplicate activity entries hourly.
     */
    private static function mark_handoff_exception($lead_id, $reason) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $reason = sanitize_text_field($reason);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_leads}
             SET handoff_status = 'exception', handoff_exception_reason = %s, updated_at = %s
             WHERE id = %d AND lead_quality = 'Qualified'
               AND handoff_status IN ('awaiting_manager', 'exception')
               AND handoff_exception_reason <> %s",
            $reason,
            current_time('mysql'),
            absint($lead_id),
            $reason
        ));
        if ($updated > 0) {
            IDLM_Read_Model_Cache::invalidate('handoff_exception');
        }
    }

    /**
     * Surface pool/configuration failures without flooding telemetry every hour.
     */
    private static function record_routing_system_exception($message) {
        $message = sanitize_text_field($message);
        $throttle_key = 'idlm_route_error_' . md5($message);
        update_option('idlm_routing_last_exception', $message, false);
        if (get_transient($throttle_key)) {
            return;
        }
        set_transient($throttle_key, '1', HOUR_IN_SECONDS);
        if (class_exists('IDLM_Telemetry')) {
            IDLM_Telemetry::log('routing_exception', 'warning', $message);
        }
        if ('1' === IDLM_Settings::get('idlm_routing_exception_notifications_enabled', '0')) {
            $admin_email = get_option('admin_email');
            if (is_email($admin_email)) {
                wp_mail($admin_email, '[Lead Manager] Routing exception requires attention', $message);
            }
        }
    }

    /**
     * Route a newly captured lead using the configured intake strategy.
     * Manual lead creation does not call this method and remains creator-owned.
     */
    public static function route_lead_assignment($lead_id, $source) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $mode = IDLM_Settings::get('idlm_assignment_mode', 'manual');
        if ('manual' === $mode) {
            return;
        }

        $active_reps = self::get_active_rep_pool(false);
        $assigned_rep_id = 0;
        $message = '';

        if ('pre_sales' === $mode) {
            $assigned_rep_id = self::select_round_robin_rep(self::get_pre_sales_pool(), 'idlm_last_pre_sales_rep_index');
            $rep = $assigned_rep_id ? get_userdata($assigned_rep_id) : false;
            $message = sprintf('Lead automatically assigned to %s via Pre-Sales Intake round robin.', $rep ? $rep->display_name : 'Pre-Sales Representative');
        } elseif ('round_robin' === $mode) {
            $assigned_rep_id = self::select_round_robin_rep($active_reps, 'idlm_last_initial_rep_index');
            $rep = $assigned_rep_id ? get_userdata($assigned_rep_id) : false;
            $message = sprintf('Lead automatically assigned to %s via initial round-robin routing.', $rep ? $rep->display_name : 'Representative');
        } elseif ('source_based' === $mode) {
            foreach ((array) IDLM_Settings::get('idlm_source_assignment_mappings', array()) as $map_source => $rep_id) {
                $rep_id = absint($rep_id);
                if (false !== stripos((string) $source, (string) $map_source) && in_array($rep_id, $active_reps, true)) {
                    $assigned_rep_id = $rep_id;
                    break;
                }
            }
            $rep = $assigned_rep_id ? get_userdata($assigned_rep_id) : false;
            $message = sprintf('Lead automatically assigned to %s via source routing (%s).', $rep ? $rep->display_name : 'Representative', $source);
        } elseif ('studio_based' === $mode) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $preferred_studio = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT preferred_studio FROM {$table_leads} WHERE id = %d",
                absint($lead_id)
            ));
            $studio_reps = self::get_studio_rep_pool($active_reps, $preferred_studio);
            $rotation_key = 'idlm_last_initial_studio_' . md5(strtolower(trim($preferred_studio)));
            $assigned_rep_id = self::select_round_robin_rep($studio_reps, $rotation_key);
            $rep = $assigned_rep_id ? get_userdata($assigned_rep_id) : false;
            $message = sprintf('Lead automatically assigned to %s via initial studio routing (%s).', $rep ? $rep->display_name : 'Representative', $preferred_studio);
        }

        if ($assigned_rep_id) {
            self::assign_lead_atomically($lead_id, 0, $assigned_rep_id, $message, false, 'pre_sales' === $mode ? 'intake' : 'initial');
        } else {
            self::record_routing_system_exception('A newly captured lead could not be routed because the selected strategy has no eligible representative.');
        }
    }

    /**
     * Recover open leads that remain completely unassigned.
     */
    private static function recover_unassigned_leads() {
        if ('1' !== IDLM_Settings::get('idlm_fallback_assignment_enabled', '0')) {
            return;
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $mode = IDLM_Settings::get('idlm_assignment_mode', 'manual');
        $rep_pool = 'pre_sales' === $mode ? self::get_pre_sales_pool() : self::get_active_rep_pool(false);
        if (empty($rep_pool)) {
            self::record_routing_system_exception('Unassigned recovery could not run because its representative pool is empty.');
            return;
        }

        $threshold = max(1, intval(IDLM_Settings::get('idlm_fallback_assignment_hours', '24')));
        $work_start = IDLM_Settings::get('idlm_working_hours_start', '09:00');
        $work_end = IDLM_Settings::get('idlm_working_hours_end', '18:00');
        $work_days = IDLM_Settings::get('idlm_working_days', array(1, 2, 3, 4, 5, 6));
        $now = current_time('mysql');

        do {
            // Re-query each batch because successfully assigned rows leave the unassigned set.
            // This drains the complete eligible backlog without loading every lead into memory.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $leads = $wpdb->get_results(
                "SELECT id, created_at, preferred_studio
                 FROM {$table_leads}
                 WHERE assigned_rep_id = 0
                   AND stage NOT IN ('Won', 'Lost')
                 ORDER BY created_at ASC LIMIT 200"
            );
            $assigned_in_batch = 0;

            foreach ($leads as $lead) {
                if (self::get_elapsed_working_hours($lead->created_at, $now, $work_start, $work_end, $work_days) < $threshold) {
                    break;
                }

                $studio_reps = 'pre_sales' === $mode ? array() : self::get_studio_rep_pool($rep_pool, $lead->preferred_studio);
                $used_studio = !empty($studio_reps);
                $eligible_reps = $used_studio ? $studio_reps : $rep_pool;
                $rotation_key = $used_studio
                    ? 'idlm_last_unassigned_studio_' . md5(strtolower(trim((string) $lead->preferred_studio)))
                    : ('pre_sales' === $mode ? 'idlm_last_pre_sales_fallback_index' : 'idlm_last_unassigned_rep_index');
                $assigned_rep_id = self::select_round_robin_rep($eligible_reps, $rotation_key);
                if (!$assigned_rep_id) {
                    continue;
                }

                $rep = get_userdata($assigned_rep_id);
                $route = $used_studio ? 'studio-matched' : ('pre_sales' === $mode ? 'pre-sales' : 'general');
                $message = sprintf(
                    'Unassigned lead recovered to %s after %d working hours via %s fallback routing%s.',
                    $rep ? $rep->display_name : 'Representative',
                    $threshold,
                    $route,
                    $used_studio ? ' (' . trim((string) $lead->preferred_studio) . ')' : ''
                );
                if (self::assign_lead_atomically($lead->id, 0, $assigned_rep_id, $message, false, 'pre_sales' === $mode ? 'intake' : 'initial')) {
                    $assigned_in_batch++;
                }
            }
        } while (200 === count($leads) && $assigned_in_batch > 0);
    }

    /**
     * Automatically hand qualified pre-sales leads to the studio-matched sales pool.
     */
    private static function handoff_qualified_leads() {
        if ('1' !== IDLM_Settings::get('idlm_qualified_fallback_enabled', '0')) {
            return;
        }

        $ooo_end_at = IDLM_Settings::get('idlm_qualified_fallback_end_at', '');
        if ($ooo_end_at && strtotime($ooo_end_at) <= current_time('timestamp')) {
            update_option('idlm_qualified_fallback_enabled', '0');
            update_option('idlm_qualified_fallback_last_status', 'expired');
            return;
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $configured_sales_pool = self::get_configured_sales_pool();
        $legacy_pre_sales_pool = self::get_all_pre_sales_pool();
        if (!empty($legacy_pre_sales_pool)) {
            $legacy_owner_sql = implode(',', array_map('absint', $legacy_pre_sales_pool));
            // Backfill durable handoff state only for qualified leads still owned by a current pre-sales user.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $backfilled = $wpdb->query(
                "UPDATE {$table_leads}
                 SET handoff_status = 'awaiting_manager', intake_owner_id = assigned_rep_id
                 WHERE handoff_status = 'not_ready' AND lead_quality = 'Qualified'
                   AND assigned_rep_id IN ({$legacy_owner_sql})
                   AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
            );
            if ($backfilled > 0) {
                IDLM_Read_Model_Cache::invalidate('handoff_backfill');
            }
        }
        // Reconcile the full unresolved backlog. Normal qualified leads remain
        // manager-controlled; this queue is acted on only after the configured
        // OOO working-hour threshold has elapsed.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $leads = $wpdb->get_results(
            "SELECT id, assigned_rep_id, preferred_studio, qualified_at, handoff_status
             FROM {$table_leads}
             WHERE COALESCE(handoff_status, 'not_ready') NOT IN ('auto_assigned', 'manually_assigned')
               AND lead_quality = 'Qualified' AND qualified_at IS NOT NULL
               AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0
             ORDER BY qualified_at ASC LIMIT 200"
        );

        if (empty($leads)) {
            return;
        }

        $threshold = max(1, intval(IDLM_Settings::get('idlm_qualified_fallback_hours', '4')));
        $work_start = IDLM_Settings::get('idlm_working_hours_start', '09:00');
        $work_end = IDLM_Settings::get('idlm_working_hours_end', '18:00');
        $work_days = IDLM_Settings::get('idlm_working_days', array(1, 2, 3, 4, 5, 6));
        $now = current_time('mysql');

        foreach ($leads as $lead) {
            // A manager may have assigned a qualified legacy record before the
            // durable handoff fields existed. Preserve that ownership and repair
            // only the missing state instead of reassigning the lead.
            if (in_array(absint($lead->assigned_rep_id), $configured_sales_pool, true)) {
                $reconciled = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_leads}
                     SET handoff_status = 'manually_assigned',
                         handoff_at = COALESCE(handoff_at, updated_at, qualified_at),
                         handoff_method = CASE WHEN handoff_method = '' THEN 'legacy_manager_reconciled' ELSE handoff_method END,
                         handoff_exception_reason = ''
                     WHERE id = %d AND handoff_status NOT IN ('auto_assigned', 'manually_assigned')",
                    absint($lead->id)
                ));
                if (1 === $reconciled) {
                    IDLM_DB::log_activity($lead->id, 'assignment', 'Existing sales ownership was reconciled as a completed manager handoff.', 0);
                    IDLM_DB::ensure_qualified_handoff_task($lead->id, $lead->assigned_rep_id);
                    IDLM_Read_Model_Cache::invalidate('handoff_reconcile');
                }
                continue;
            }

            if (self::get_elapsed_working_hours($lead->qualified_at, $now, $work_start, $work_end, $work_days) < $threshold) {
                continue;
            }

            // Recalculate capacity for every lead so a large backlog cannot
            // overfill representatives using a stale batch-level pool.
            $sales_pool = self::get_active_rep_pool(true);
            if (empty($sales_pool)) {
                self::record_routing_system_exception('Manager OOO handoff could not run because every configured sales representative is unavailable or at capacity.');
                self::mark_handoff_exception($lead->id, 'No sales representative currently has assignment capacity.');
                continue;
            }

            $studio_reps = self::get_studio_rep_pool($sales_pool, $lead->preferred_studio);
            $used_studio = !empty($studio_reps);
            $studio_mismatch_behavior = IDLM_Settings::get('idlm_studio_mismatch_behavior', 'general_pool');
            if (!$used_studio && 'hold_for_manager' === $studio_mismatch_behavior) {
                $reason = empty(trim((string) $lead->preferred_studio))
                    ? 'Preferred Studio is missing; qualified lead is held for manager review.'
                    : sprintf('No active sales representative matches Preferred Studio: %s.', trim((string) $lead->preferred_studio));
                self::mark_handoff_exception($lead->id, $reason);
                continue;
            }
            $eligible_reps = $used_studio ? $studio_reps : $sales_pool;
            $rotation_key = $used_studio
                ? 'idlm_last_qualified_studio_' . md5(strtolower(trim((string) $lead->preferred_studio)))
                : 'idlm_last_qualified_general_index';
            $assigned_rep_id = self::select_round_robin_rep($eligible_reps, $rotation_key);
            if (!$assigned_rep_id) {
                continue;
            }

            $old_rep = get_userdata($lead->assigned_rep_id);
            $new_rep = get_userdata($assigned_rep_id);
            $route = $used_studio ? 'studio-matched' : 'general sales-pool';
            $message = sprintf(
                'Manager OOO automatic handoff moved this qualified lead from %s to %s after %d working hours via %s routing%s.',
                $old_rep ? $old_rep->display_name : 'Pre-Sales Representative',
                $new_rep ? $new_rep->display_name : 'Sales Representative',
                $threshold,
                $route,
                $used_studio ? ' (' . trim((string) $lead->preferred_studio) . ')' : ' (no active studio match)'
            );
            if (!self::assign_lead_atomically(
                $lead->id,
                $lead->assigned_rep_id,
                $assigned_rep_id,
                $message,
                true,
                $used_studio ? 'qualified_studio' : 'qualified_general'
            )) {
                self::mark_handoff_exception($lead->id, 'Ownership changed while OOO fallback was assigning this lead; manager review is required.');
            }
        }
    }

    /**
     * Run both fallback flows under one overlap lock.
     */
    public static function run_assignment_fallback() {
        if (!IDLM_Routing_State::acquire_lock('assignment_fallback')) {
            return;
        }
        update_option('idlm_fallback_last_started_at', current_time('mysql'), false);
        try {
            self::recover_unassigned_leads();
            self::handoff_qualified_leads();
            update_option('idlm_fallback_last_completed_at', current_time('mysql'), false);
            update_option('idlm_fallback_last_status', 'success', false);
        } catch (Throwable $error) {
            update_option('idlm_fallback_last_status', 'failed', false);
            update_option('idlm_fallback_last_error', sanitize_text_field($error->getMessage()), false);
            throw $error;
        } finally {
            IDLM_Routing_State::release_lock('assignment_fallback');
        }
    }

    /**
     * Calculate elapsed working hours between two datetimes based on working range and active days.
     *
     * @param string $start_time_str MySQL datetime string for start time
     * @param string $end_time_str MySQL datetime string for end time
     * @param string $work_start_str Daily working hours start (e.g. '09:00')
     * @param string $work_end_str Daily working hours end (e.g. '18:00')
     * @param array $work_days Active working days of the week (e.g. array(1, 2, 3, 4, 5, 6))
     * @return float Elapsed working hours
     */
    public static function get_elapsed_working_hours($start_time_str, $end_time_str, $work_start_str, $work_end_str, $work_days) {
        try {
            $start_dt = new DateTime($start_time_str);
            $end_dt = new DateTime($end_time_str);
        } catch (Exception $e) {
            return 0.0;
        }

        if ($start_dt >= $end_dt) {
            return 0.0;
        }

        if (empty($work_days) || !is_array($work_days)) {
            $work_days = array(0, 1, 2, 3, 4, 5, 6);
        }

        $total_seconds = 0;

        $current = clone $start_dt;
        $current->setTime(0, 0, 0);

        $end_day = clone $end_dt;
        $end_day->setTime(23, 59, 59);

        // Parse working bounds
        list($sh, $sm) = array_pad(array_map('intval', explode(':', $work_start_str)), 2, 0);
        list($eh, $em) = array_pad(array_map('intval', explode(':', $work_end_str)), 2, 0);

        while ($current <= $end_day) {
            $wday = intval($current->format('w')); // 0 (Sun) to 6 (Sat)
            if (in_array($wday, $work_days, true)) {
                $work_start_dt = clone $current;
                $work_start_dt->setTime($sh, $sm, 0);

                $work_end_dt = clone $current;
                $work_end_dt->setTime($eh, $em, 0);

                $eff_start = max($start_dt->getTimestamp(), $work_start_dt->getTimestamp());
                $eff_end = min($end_dt->getTimestamp(), $work_end_dt->getTimestamp());

                if ($eff_start < $eff_end) {
                    $total_seconds += ($eff_end - $eff_start);
                }
            }
            $current->modify('+1 day');
        }

        return round($total_seconds / 3600, 2);
    }

    /** Validate the scoped MCP header. */
    private static function validate_mcp_api_key(WP_REST_Request $request) {
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

    /**
     * Handle MCP GET requests — Streamable HTTP server info / health check
     *
     * Per the MCP Streamable HTTP spec, GET on the MCP endpoint returns
     * server metadata. Clients can use this to verify connectivity.
     */
    public static function handle_mcp_get_info(WP_REST_Request $request) {
        $auth = self::validate_mcp_api_key($request);
        if ($auth instanceof WP_REST_Response) {
            return $auth;
        }

        return new WP_REST_Response(array(
            'jsonrpc' => '2.0',
            'result' => array(
                'protocolVersion' => '2024-11-05',
                'capabilities' => array(
                    'tools' => (object) array()
                ),
                'serverInfo' => array(
                    'name' => 'interior-design-crm-mcp',
                    'version' => '1.0.0',
                    'transport' => 'streamable-http'
                )
            )
        ), 200, array(
            'Content-Type' => 'application/json',
            'Cache-Control' => 'no-cache, no-store, must-revalidate'
        ));
    }

    /**
     * Handle MCP POST requests — Streamable HTTP Direct JSON-RPC
     *
     * Accepts a standard JSON-RPC request body, executes it immediately via
     * IDLM_MCP_Core::handle_json_rpc(), and returns the result directly in
     * the HTTP response body. No SSE, no transient queuing, no buffering issues.
     *
     * Supports both single requests and JSON-RPC batch arrays.
     */
    public static function handle_mcp_post(WP_REST_Request $request) {
        $request_started = microtime(true);
        $auth = self::validate_mcp_api_key($request);
        if ($auth instanceof WP_REST_Response) {
            return $auth;
        }

        $body = $request->get_body();
        $payload_limit = IDLM_Production_Security::mcp_payload_limit();
        if (strlen($body) > $payload_limit) {
            return new WP_REST_Response(array(
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => array(
                    'code' => -32600,
                    'message' => 1048576 === $payload_limit
                        ? 'Request rejected: MCP payload exceeds the 1 MB limit.'
                        : 'Request rejected: MCP payload exceeds the configured ' . $payload_limit . '-byte limit.'
                )
            ), 413);
        }
        $json_request = json_decode($body, true);

        if ($json_request === null && json_last_error() !== JSON_ERROR_NONE) {
            return new WP_REST_Response(array(
                'jsonrpc' => '2.0',
                'id' => null,
                'error' => array(
                    'code' => -32700,
                    'message' => 'Parse error: Invalid JSON — ' . json_last_error_msg()
                )
            ), 400);
        }

        if (!class_exists('IDLM_MCP_Core')) {
            require_once dirname(__FILE__) . '/class-mcp-core.php';
        }

        // Support JSON-RPC batch requests (array of requests)
        if (isset($json_request[0]) && is_array($json_request[0])) {
            $batch_limit = IDLM_Production_Security::mcp_batch_limit();
            if (count($json_request) > $batch_limit) {
                return new WP_REST_Response(array(
                    'jsonrpc' => '2.0',
                    'id' => null,
                    'error' => array(
                        'code' => -32600,
                        'message' => 'Request rejected: MCP batches are limited to ' . $batch_limit . ' calls.'
                    )
                ), 413);
            }
            $responses = array();
            foreach ($json_request as $single_request) {
                if ((microtime(true) - $request_started) > IDLM_Production_Security::mcp_request_time_limit()) {
                    $responses[] = array(
                        'jsonrpc' => '2.0',
                        'id' => $single_request['id'] ?? null,
                        'error' => array(
                            'code' => -32004,
                            'message' => 'Request budget exhausted before this call could run.'
                        )
                    );
                    IDLM_Production_Security::audit_mcp(
                        $request,
                        $single_request,
                        'budget_exhausted',
                        (microtime(true) - $request_started) * 1000
                    );
                    continue;
                }
                $call_started = microtime(true);
                $response = IDLM_MCP_Core::handle_json_rpc($single_request);
                $outcome = is_array($response) && isset($response['error']) ? 'error' : 'success';
                if (is_array($response) && !empty($response['result']['isError'])) {
                    $outcome = 'error';
                }
                IDLM_Production_Security::audit_mcp(
                    $request,
                    $single_request,
                    $outcome,
                    (microtime(true) - $call_started) * 1000
                );
                if ($response !== null) {
                    $responses[] = $response;
                }
            }
            // Return batch response (or empty 204 if all were notifications)
            if (empty($responses)) {
                return new WP_REST_Response(null, 204);
            }
            return new WP_REST_Response($responses, 200, array(
                'Content-Type' => 'application/json'
            ));
        }

        // Single request
        $response = IDLM_MCP_Core::handle_json_rpc($json_request);
        $outcome = is_array($response) && isset($response['error']) ? 'error' : 'success';
        if (is_array($response) && !empty($response['result']['isError'])) {
            $outcome = 'error';
        }
        IDLM_Production_Security::audit_mcp(
            $request,
            $json_request,
            $outcome,
            (microtime(true) - $request_started) * 1000
        );

        // Notifications (no id) return no body
        if ($response === null) {
            return new WP_REST_Response(null, 204);
        }

        // Determine HTTP status code from response
        $http_status = 200;
        if (isset($response['error'])) {
            $error_code = $response['error']['code'];
            if ($error_code === -32700 || $error_code === -32600) {
                $http_status = 400;
            } elseif ($error_code === -32601) {
                $http_status = 404;
            } else {
                $http_status = 500;
            }
        }

        return new WP_REST_Response($response, $http_status, array(
            'Content-Type' => 'application/json'
        ));
    }

    /**
     * Handle MCP DELETE requests — Session termination (Streamable HTTP)
     *
     * Optional endpoint for MCP clients to cleanly terminate a session.
     * Returns a simple acknowledgment.
     */
    public static function handle_mcp_delete_session(WP_REST_Request $request) {
        $auth = self::validate_mcp_api_key($request);
        if ($auth instanceof WP_REST_Response) {
            return $auth;
        }

        return new WP_REST_Response(array(
            'jsonrpc' => '2.0',
            'result' => array(
                'message' => 'Session terminated successfully'
            )
        ), 200, array(
            'Content-Type' => 'application/json'
        ));
    }
}
