<?php
/** Focused M6 responsibility owner. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Webhook_Intake {

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
        $result = IDLM_DB::upsert_inbound_lead($mapped_data, $platform, $payload, array('route'=>true));
        if ($result) {
            if (!empty($result['requires_duplicate_review'])) {
                return new WP_REST_Response(array(
                    'success' => true,
                    'created' => false,
                    'matched_existing' => false,
                    'requires_identity_review' => 'rejected' !== ($result['review_status'] ?? 'pending'),
                    'review_status' => $result['review_status'] ?? 'pending',
                    'review_id' => absint($result['review_id'] ?? 0),
                    'message' => 'rejected' === ($result['review_status'] ?? '') ? 'Submission was rejected during identity review; no lead was created or routed.' : 'Submission retained for identity review; no lead was created or routed.',
                ), 202);
            }
            $lead_id = absint($result['lead_id']);

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

    public static function apply_field_mappings($payload, $platform) {
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
            } elseif ('meta' === $platform && isset($flat_payload['leadgen_id'])) {
                $lead_data['fb_lead_id'] = sanitize_text_field($flat_payload['leadgen_id']);
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

    public static function classify_lead_source($lead_data, $platform) {
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

    public static function normalize_property_type($val) {
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

    public static function flatten_array($array, $prefix = '') {
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

    public static function is_truthy_webhook_value($value) {
        if (true === $value || 1 === $value) {
            return true;
        }

        if (!is_scalar($value)) {
            return false;
        }

        return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
    }
    private static function validate_scoped_api_key(WP_REST_Request $request, $option_name) { return IDLM_Integration_Auth::validate_scoped_api_key($request, $option_name); }
    private static function get_captured_attribution() { return IDLM_Integrations::get_captured_attribution(); }

}
