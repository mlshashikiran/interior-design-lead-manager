<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Integrations {
    public static function init() {
        add_action('wp_head', array(__CLASS__, 'inject_utm_tracker_script'));
        add_action('rest_api_init', array(__CLASS__, 'register_webhook_routes'));
        add_filter('rest_authentication_errors', array(__CLASS__, 'bypass_rest_auth_for_webhook'), 99999);
    }

    /**
     * Bypass global REST API auth restrictions for our public webhook endpoint
     */
    public static function bypass_rest_auth_for_webhook($errors) {
        $is_webhook = false;

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
            if (untrailingslashit($rest_route) === '/interior-design-lead-manager/v1/webhook') {
                $is_webhook = true;
            }
        }

        // Check REQUEST_URI path strictly
        if (!$is_webhook && isset($_SERVER['REQUEST_URI'])) {
            $path = wp_parse_url(urldecode(esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))), PHP_URL_PATH);
            if ($path && preg_match('#/wp-json/interior-design-lead-manager/v1/webhook/?$#', $path)) {
                $is_webhook = true;
            }
        }

        if ($is_webhook) {
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
            var params = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'li_fat_id'];
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
        })();
        </script>
        <?php
    }

    /**
     * Retrieve captured UTMs/Click IDs from cookies
     */
    public static function get_captured_attribution() {
        $attribution = array();
        $params = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'fbclid', 'li_fat_id');

        foreach ($params as $param) {
            $cookie_name = 'idlm_' . $param;
            if (!empty($_COOKIE[$cookie_name])) {
                // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
                $attribution[$param] = sanitize_text_field(urldecode(wp_unslash($_COOKIE[$cookie_name])));
            } else {
                $attribution[$param] = '';
            }
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
            'permission_callback' => '__return_true' // Handled via API key validation
        ));
    }

    /**
     * Process incoming webhook payloads from Ad Platforms
     */
    public static function handle_webhook_request(WP_REST_Request $request) {
        $api_key = $request->get_header('X-IDLM-API-Key');
        $saved_key = get_option('idlm_api_webhook_key');

        if (empty($api_key) || $api_key !== $saved_key) {
            // Check fallback in query params
            $api_key = $request->get_param('api_key');
            if (empty($api_key) || $api_key !== $saved_key) {
                return new WP_REST_Response(array('success' => false, 'message' => 'Unauthorized: Invalid API Key'), 401);
            }
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

        $platform = sanitize_text_field($request->get_param('platform')); // meta, google, linkedin, website
        if (!in_array($platform, array('meta', 'google', 'linkedin', 'website'))) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Invalid Platform Specified'), 400);
        }

        // Apply field mappings defined in settings
        $mapped_data = self::apply_field_mappings($payload, $platform);
        if (empty($mapped_data['email']) && empty($mapped_data['phone'])) {
            return new WP_REST_Response(array('success' => false, 'message' => 'Failed to parse required fields (Email or Phone) based on mapping'), 422);
        }

        // Add lead
        $lead_id = IDLM_DB::insert_lead($mapped_data);
        if ($lead_id) {
            // Assign representative
            self::route_lead_assignment($lead_id, $mapped_data['source']);

            return new WP_REST_Response(array('success' => true, 'lead_id' => $lead_id, 'message' => 'Lead created successfully'), 200);
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
            'source'             => ucfirst($platform) . ' Ads',
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
            'fbclid'             => '',
            'li_fat_id'          => '',
            'fb_lead_id'         => '',
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

        // Normalize Property Type & Number of Rooms values dynamically across channels
        $lead_data['property_type'] = self::normalize_property_type($lead_data['property_type']);
        $lead_data['num_rooms']     = self::normalize_num_rooms($lead_data['num_rooms']);
        $lead_data['budget']        = self::normalize_budget($lead_data['budget']);

        return $lead_data;
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
     * Route and assign lead based on settings rules
     */
    public static function route_lead_assignment($lead_id, $source) {
        $mode = get_option('idlm_assignment_mode', 'manual'); // manual, round_robin, source_based
        $reps = get_option('idlm_active_assignment_reps', array()); // Array of Wordpress User IDs (Sales Reps)

        if (empty($reps) || $mode === 'manual') {
            return;
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        if ($mode === 'round_robin') {
            // Fetch last assigned index
            $last_index = intval(get_option('idlm_last_assigned_rep_index', -1));
            $next_index = ($last_index + 1) % count($reps);
            $assigned_rep_id = $reps[$next_index];

            // Update lead record
            $wpdb->update($table_leads, array('assigned_rep_id' => $assigned_rep_id), array('id' => $lead_id));
            update_option('idlm_last_assigned_rep_index', $next_index);

            // Log activity
            $rep_obj = get_userdata($assigned_rep_id);
            $rep_name = $rep_obj ? $rep_obj->display_name : 'Deleted Representative';
            IDLM_DB::log_activity($lead_id, 'assignment', "Lead automatically assigned to {$rep_name} via Round Robin.", 0);

        } elseif ($mode === 'source_based') {
            $source_mappings = get_option('idlm_source_assignment_mappings', array()); // e.g. array('Google Ads' => rep_id)
            $assigned_rep_id = 0;

            foreach ($source_mappings as $map_source => $rep_id) {
                if (stripos($source, $map_source) !== false && in_array($rep_id, $reps)) {
                    $assigned_rep_id = $rep_id;
                    break;
                }
            }

            if ($assigned_rep_id) {
                $wpdb->update($table_leads, array('assigned_rep_id' => $assigned_rep_id), array('id' => $lead_id));
                $rep_obj = get_userdata($assigned_rep_id);
                $rep_name = $rep_obj ? $rep_obj->display_name : 'Deleted Representative';
                IDLM_DB::log_activity($lead_id, 'assignment', "Lead automatically assigned to {$rep_name} based on Source routing ({$source}).", 0);
            }
        }
    }
}
