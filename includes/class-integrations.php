<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Integrations {
    const API_CREDENTIALS_VERSION = IDLM_Integration_Auth::API_CREDENTIALS_VERSION;

    public static function init() {
        add_action('idlm_qualified_handoff_notification', array(__CLASS__, 'send_handoff_notifications'), 10, 4);
        self::maybe_migrate_api_credentials();
        IDLM_Secret_Store::migrate_platforms();
        IDLM_Secret_Store::scrub_legacy_queues();
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
        return IDLM_Google_Feed_Controller::add_google_feed_auth_challenge($response, $server, $request);
    }

    /** Migrate scoped credentials into encrypted storage and remove legacy paths. */
    public static function maybe_migrate_api_credentials() {
        return IDLM_Integration_Auth::maybe_migrate_api_credentials();
    }

    /** Rotate one scoped API credential and end its legacy compatibility path. */
    public static function rotate_api_credential($scope) {
        return IDLM_Integration_Auth::rotate_api_credential($scope);
    }

    /** Generate a URL/header-safe credential distinct from the supplied value. */
    private static function generate_api_credential($different_from = '') {
        return IDLM_Integration_Auth::generate_api_credential($different_from);
    }

    /** Compare non-empty credentials without exposing timing differences. */
    private static function credential_matches($provided, $expected) {
        return IDLM_Integration_Auth::credential_matches($provided, $expected);
    }

    /** Validate a scoped header key. Credentials are never accepted in URLs. */
    private static function validate_scoped_api_key(WP_REST_Request $request, $option_name) {
        return IDLM_Integration_Auth::validate_scoped_api_key($request, $option_name);
    }

    /** Apply a fixed-window per-IP request limit before route callbacks run. */
    private static function check_rest_rate_limit($scope, $limit) {
        return IDLM_Integration_Auth::check_rest_rate_limit($scope, $limit);
    }

    private static function rest_permission_error($message = 'Unauthorized: Invalid credentials') {
        return IDLM_Integration_Auth::rest_permission_error($message);
    }

    /** REST permission boundary for inbound lead webhooks. */
    public static function permission_webhook(WP_REST_Request $request) {
        return IDLM_Integration_Auth::permission_webhook($request);
    }

    /** REST permission boundary for Google Data Manager feeds. */
    public static function permission_google_feed(WP_REST_Request $request) {
        return IDLM_Integration_Auth::permission_google_feed($request);
    }

    /** REST permission boundary for MCP, including PII-returning tools. */
    public static function permission_mcp(WP_REST_Request $request) {
        return IDLM_Integration_Auth::permission_mcp($request);
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

        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0430: reconcile_legacy_qualified_handoffs(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $leads = $wpdb->get_results(
            "SELECT id, assigned_rep_id, qualified_at, updated_at, created_at
             FROM {$table_leads}
             WHERE handoff_status = 'not_ready' AND lead_quality = 'Qualified'
               AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
            if ($clean_route === '/idlm/v1/meta-leads' || $clean_route === '/interior-design-lead-manager/v1/webhook' ||
                $clean_route === '/interior-design-lead-manager/v1/mcp' || 
                strpos($clean_route, '/interior-design-lead-manager/v1/google-ads-') === 0) {
                $is_public_route = true;
            }
        }

        // Check REQUEST_URI path strictly
        if (!$is_public_route && isset($_SERVER['REQUEST_URI'])) {
            $path = wp_parse_url(urldecode(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))), PHP_URL_PATH);
            if ($path && (
                preg_match('#/wp-json/idlm/v1/meta-leads/?$#', $path) ||
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
        return IDLM_Integration_Auth::validate_gadm_request($request);
    }

    /**
     * Send a proper 401 response with WWW-Authenticate header for GADM feeds.
     * Google Ads Data Manager requires the standard HTTP auth challenge header.
     */
    private static function send_gadm_401() {
        return IDLM_Google_Feed_Controller::send_gadm_401();
    }

    /**
     * Clean WordPress REST API headers so GADM sees a clean static-file response
     */
    private static function clean_rest_headers_for_gadm() {
        return IDLM_Google_Feed_Controller::clean_rest_headers_for_gadm();
    }

    /**
     * Expose a secure CSV conversion feed for Google Ads OCI
     */
    public static function handle_google_ads_conversions_feed(WP_REST_Request $request) {
        return IDLM_Google_Feed_Controller::handle_google_ads_conversions_feed($request);
    }

    /**
     * Expose a secure CSV audience feed for Google Ads Data Manager Customer Match
     */
    public static function handle_google_ads_audience_feed(WP_REST_Request $request) {
        return IDLM_Google_Feed_Controller::handle_google_ads_audience_feed($request);
    }

    /**
     * Process incoming webhook payloads from Ad Platforms
     */
    public static function handle_webhook_request(WP_REST_Request $request) {
        return IDLM_Webhook_Intake::handle_webhook_request($request);
    }

    /**
     * Map webhook payload parameters to DB columns
     */
    private static function apply_field_mappings($payload, $platform) {
        return IDLM_Webhook_Intake::apply_field_mappings($payload, $platform);
    }

    /**
     * Dynamically classify lead source based on click IDs and UTM parameter patterns
     */
    private static function classify_lead_source($lead_data, $platform) {
        return IDLM_Webhook_Intake::classify_lead_source($lead_data, $platform);
    }

    /**
     * Standardize Property Types to system options: Apartment, Villa, Independent House, Commercial
     */
    private static function normalize_property_type($val) {
        return IDLM_Webhook_Intake::normalize_property_type($val);
    }

    /**
     * Standardize Room count parameters to standard system keys dynamically based on active settings options
     */
    public static function normalize_num_rooms($val) {
        return IDLM_Webhook_Intake::normalize_num_rooms($val);
    }

    /**
     * Parse verbose budget strings (e.g. "Villa - Interiors starting @ ₹15-20 lakhs") 
     * and isolate standard budget/currency ranges/values.
     */
    public static function normalize_budget($val) {
        return IDLM_Webhook_Intake::normalize_budget($val);
    }

    /**
     * Flatten a multi-dimensional array with dot notation
     */
    private static function flatten_array($array, $prefix = '') {
        return IDLM_Webhook_Intake::flatten_array($array, $prefix);
    }

    /**
     * Determine whether a webhook control value explicitly represents true.
     */
    private static function is_truthy_webhook_value($value) {
        return IDLM_Webhook_Intake::is_truthy_webhook_value($value);
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
        $role_pool = array_values(array_filter(self::get_all_pre_sales_pool(), static function($id) {
            return IDLM_Lead_Access_Policy::is_assignable_rep($id, 'intake');
        }));
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
                return IDLM_Lead_Access_Policy::is_assignable_rep($rep_id);
            }
        ));
        if (!$exclude_pre_sales) {
            return self::filter_pool_by_capacity($rep_ids);
        }

        $rep_ids = array_values(array_filter($rep_ids, static function($rep_id) {
            return IDLM_Lead_Access_Policy::is_assignable_rep($rep_id, 'sales');
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
                return IDLM_Lead_Access_Policy::is_assignable_rep($rep_id, 'sales');
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
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- REL007-SQL-0431: filter_pool_by_capacity(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT assigned_rep_id, COUNT(*) active_count
             FROM {$wpdb->prefix}idlm_leads
             WHERE assigned_rep_id IN ({$placeholders}) AND is_duplicate = 0
               AND COALESCE(stage, '') NOT IN ('Won', 'Lost')
               AND COALESCE(lead_quality, '') != 'Unqualified'
             GROUP BY assigned_rep_id",
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
            $rep_ids
        ));
        if ($wpdb->last_error || !is_array($rows)) { return array(); }
        foreach ($rows as $row) {
            $counts[absint($row->assigned_rep_id)] = (int) $row->active_count;
        }
        return array_values(array_filter($rep_ids, static function($rep_id) use ($counts, $maximum) {
            return ($counts[$rep_id] ?? 0) < $maximum;
        }));
    }

    /** Advisory capacity for selection; canonical persistence checks current admission. */
    public static function rep_has_capacity($rep_id) {
        $rep_id = absint($rep_id);
        return 0 === $rep_id || in_array($rep_id, self::filter_pool_by_capacity(array($rep_id)), true);
    }

    public static function get_rep_capacity_status($rep_id) {
        global $wpdb;
        $rep_id = absint($rep_id);
        $maximum = max(0, absint(IDLM_Settings::get('idlm_max_active_leads_per_rep', 30)));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0432: get_rep_capacity_status(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
        $active = $rep_id ? (int) $wpdb->get_var($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads
             WHERE assigned_rep_id = %d AND is_duplicate = 0
               AND COALESCE(stage, '') NOT IN ('Won', 'Lost')
               AND COALESCE(lead_quality, '') != 'Unqualified'",
            $rep_id
        )) : 0;
        $failed = $rep_id && (bool) $wpdb->last_error;
        return array(
            'maximum' => $maximum,
            'active' => $active,
            'remaining' => $failed ? 0 : (0 === $maximum ? PHP_INT_MAX : max(0, $maximum - $active)),
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
        $result = IDLM_Lead_Transaction::run(static function() use ($lead_id, $expected_owner_id, $assigned_rep_id, $activity_message, $qualified_only, $assignment_context) {
            return self::assign_lead_work($lead_id, $expected_owner_id, $assigned_rep_id, $activity_message, $qualified_only, $assignment_context);
        });
        return !is_wp_error($result) && $result;
    }

    private static function assign_lead_work($lead_id, $expected_owner_id, $assigned_rep_id, $activity_message, $qualified_only, $assignment_context) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0433: assign_lead_work(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
        $lead = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_leads} WHERE id = %d FOR UPDATE", absint($lead_id))));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        if (!$lead || absint($lead->assigned_rep_id) !== absint($expected_owner_id)) { return false; }
        $target_context = 'intake' === $assignment_context ? 'intake' : (($qualified_only || 0 === strpos($assignment_context, 'qualified_')) ? 'sales' : 'any');
        if (!absint($assigned_rep_id) || !IDLM_Lead_Access_Policy::is_assignable_rep($assigned_rep_id, $target_context, true)) { return false; }
        if (!IDLM_Assignment_Capacity::is_active($lead)) { return false; }
        IDLM_Lead_Transaction::required(IDLM_Assignment_Capacity::validate_transition($lead, array_merge((array) $lead, array('assigned_rep_id' => absint($assigned_rep_id)))));
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

        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- REL007-SQL-0434: assign_lead_work(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $updated = IDLM_Lead_Transaction::required($wpdb->query($wpdb->prepare(
            "UPDATE {$table_leads}
             SET {$set_sql}
             WHERE id = %d AND assigned_rep_id = %d{$qualified_sql}",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
            $params
        )));

        if (1 !== $updated) {
            return false;
        }

        IDLM_Lead_Transaction::required(IDLM_DB::log_activity($lead_id, 'assignment', $activity_message, 0));
        IDLM_Lead_Transaction::required(IDLM_DB::record_reporting_event(
            $lead_id,
            'assignment',
            $expected_owner_id,
            $assigned_rep_id,
            '',
            'automatic_' . sanitize_key($assignment_context),
            0,
            $assignment_time
        ));
        IDLM_Lead_Transaction::invalidate('lead_assignment', $lead_id);

        if ('intake' === $assignment_context) {
            IDLM_Lead_Transaction::required(IDLM_DB::create_presales_followup_tasks($lead_id, $assigned_rep_id));
            IDLM_Lead_Transaction::required(IDLM_DB::sync_presales_task_status($lead_id));
        }

        if (0 === strpos($assignment_context, 'qualified_')) {
            $task_policy = IDLM_Settings::get('idlm_handoff_task_policy', 'transfer');
            if ('transfer' === $task_policy) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                IDLM_Lead_Transaction::required($wpdb->update(
                    $table_tasks,
                    array('assigned_rep_id' => absint($assigned_rep_id), 'updated_at' => current_time('mysql')),
                    array('lead_id' => absint($lead_id), 'assigned_rep_id' => absint($expected_owner_id), 'status' => 'pending')
                ));
            } elseif ('complete_qualification' === $task_policy) {
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0435: assign_lead_work(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $reconciled = IDLM_Lead_Transaction::required($wpdb->query($wpdb->prepare(
                    "UPDATE {$table_tasks}
                     SET status = 'completed', completed_at = %s, updated_at = %s
                     WHERE lead_id = %d AND assigned_rep_id = %d AND status = 'pending'
                       AND LOWER(task_type) = 'qualification'",
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    current_time('mysql'),
                    current_time('mysql'),
                    absint($lead_id),
                    absint($expected_owner_id)
                )));
            }

            IDLM_Lead_Transaction::required(IDLM_DB::ensure_qualified_handoff_task($lead_id, $assigned_rep_id));

            IDLM_Lead_Transaction::defer('idlm_qualified_handoff_notification', $lead_id, $expected_owner_id, $assigned_rep_id, $activity_message);
        }
        return true;
    }

    /**
     * Notify handoff participants when explicitly enabled by an administrator.
     */
    public static function send_handoff_notifications($lead_id, $old_owner_id, $new_owner_id, $activity_message) {
        return IDLM_Handoff_Notifications::send($lead_id, $old_owner_id, $new_owner_id, $activity_message);
    }

    /**
     * Record a routing exception without adding duplicate activity entries hourly.
     */
    private static function mark_handoff_exception($lead_id, $reason) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $reason = sanitize_text_field($reason);
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0437: mark_handoff_exception(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_leads}
             SET handoff_status = 'exception', handoff_exception_reason = %s, updated_at = %s
             WHERE id = %d AND lead_quality = 'Qualified'
               AND handoff_status IN ('awaiting_manager', 'exception')
               AND handoff_exception_reason <> %s",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
        if (IDLM_Settings::notification_enabled('idlm_routing_exception_notifications_enabled')) {
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
            return true;
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
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0438: route_lead_assignment(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $preferred_studio = (string) $wpdb->get_var($wpdb->prepare(
                "SELECT preferred_studio FROM {$table_leads} WHERE id = %d",
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                absint($lead_id)
            ));
            $studio_reps = self::get_studio_rep_pool($active_reps, $preferred_studio);
            $rotation_key = 'idlm_last_initial_studio_' . md5(strtolower(trim($preferred_studio)));
            $assigned_rep_id = self::select_round_robin_rep($studio_reps, $rotation_key);
            $rep = $assigned_rep_id ? get_userdata($assigned_rep_id) : false;
            $message = sprintf('Lead automatically assigned to %s via initial studio routing (%s).', $rep ? $rep->display_name : 'Representative', $preferred_studio);
        }

        if ($assigned_rep_id) {
            return self::assign_lead_atomically($lead_id, 0, $assigned_rep_id, $message, false, 'pre_sales' === $mode ? 'intake' : 'initial');
        } else {
            if (!IDLM_Lead_Transaction::active()) { self::record_routing_system_exception('A newly captured lead could not be routed because the selected strategy has no eligible representative.'); }
        }
        return true;
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
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0439: recover_unassigned_leads(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $leads = $wpdb->get_results(
                "SELECT id, created_at, preferred_studio
                 FROM {$table_leads}
                 WHERE assigned_rep_id = 0 AND is_duplicate = 0
                   AND COALESCE(stage, '') NOT IN ('Won', 'Lost') AND COALESCE(lead_quality, '') != 'Unqualified'
                 ORDER BY created_at ASC LIMIT 200"
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
            $assigned_in_batch = 0;

            foreach ($leads as $lead) {
                if (self::get_elapsed_working_hours($lead->created_at, $now, $work_start, $work_end, $work_days) < $threshold) {
                    break;
                }

                // Refresh after every successful admission; a batch is not a capacity reservation.
                $rep_pool = 'pre_sales' === $mode ? self::get_pre_sales_pool() : self::get_active_rep_pool(false);
                if (!$rep_pool) { break; }
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
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0440: handoff_qualified_leads(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $backfilled = $wpdb->query(
                "UPDATE {$table_leads}
                 SET handoff_status = 'awaiting_manager', intake_owner_id = assigned_rep_id
                 WHERE handoff_status = 'not_ready' AND lead_quality = 'Qualified'
                   AND assigned_rep_id IN ({$legacy_owner_sql})
                   AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            );
            if ($backfilled > 0) {
                IDLM_Read_Model_Cache::invalidate('handoff_backfill');
            }
        }
        // Reconcile the full unresolved backlog. Normal qualified leads remain
        // manager-controlled; this queue is acted on only after the configured
        // OOO working-hour threshold has elapsed.
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0441: handoff_qualified_leads(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $leads = $wpdb->get_results(
            "SELECT id, assigned_rep_id, preferred_studio, qualified_at, handoff_status
             FROM {$table_leads}
             WHERE COALESCE(handoff_status, 'not_ready') NOT IN ('auto_assigned', 'manually_assigned')
               AND lead_quality = 'Qualified' AND qualified_at IS NOT NULL
               AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0
             ORDER BY qualified_at ASC LIMIT 200"
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
                // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0442: handoff_qualified_leads(): prefixed lead/task tables; fixed assignment SET/qualification branches, matching parameter arrays and absint rep lists; assignment capacity/ownership reads are live under existing locks.
                $reconciled = $wpdb->query($wpdb->prepare(
                    "UPDATE {$table_leads}
                     SET handoff_status = 'manually_assigned',
                         handoff_at = COALESCE(handoff_at, updated_at, qualified_at),
                         handoff_method = CASE WHEN handoff_method = '' THEN 'legacy_manager_reconciled' ELSE handoff_method END,
                         handoff_exception_reason = ''
                     WHERE id = %d AND handoff_status NOT IN ('auto_assigned', 'manually_assigned')",
                // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
                self::mark_handoff_exception($lead->id, 'Ownership, eligibility or capacity changed while OOO fallback was assigning this lead; manager review is required.');
            }
        }
    }

    /**
     * Run both fallback flows under one overlap lock.
     */
    public static function run_assignment_fallback() {
        return IDLM_Recovery_Guard::run(static function() { return self::run_assignment_fallback_work(); }, null, false, '', array());
    }

    private static function run_assignment_fallback_work() {
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
        return IDLM_Integration_Auth::validate_mcp_api_key($request);
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
