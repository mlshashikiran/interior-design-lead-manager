<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Interakt {
    public static function init() {
        // Hook when a lead call status changes to No Answer (Legacy/Wrapper)
        add_action('idlm_lead_call_status_no_answer', array(__CLASS__, 'trigger_no_answer_automation'), 10, 2);

        // Generalized hook for mapped Interakt triggers
        add_action('idlm_trigger_interakt_event', array(__CLASS__, 'trigger_generic_event'), 10, 3);
        
        // Hook for Action Scheduler execution
        add_action('idlm_async_interakt_track_event', array(__CLASS__, 'process_async_track_event'), 10, 3);

        // Hook for incoming WhatsApp Webhook listener
        add_action('rest_api_init', array(__CLASS__, 'register_webhook_route'));
    }

    /**
     * Legacy wrapper for "No Answer" status triggers
     */
    public static function trigger_no_answer_automation($lead_id, $old_lead) {
        $event_name = get_option('idlm_interakt_event_name', 'lead_no_answer');
        self::trigger_generic_event($lead_id, $event_name, $old_lead);
    }

    /**
     * Trigger WhatsApp automation on Interakt for a lead with a dynamic event name
     */
    public static function trigger_generic_event($lead_id, $event_name, $old_lead = null) {
        $enabled = get_option('idlm_interakt_enabled', '0');
        $api_key = get_option('idlm_interakt_api_key', '');

        if ($enabled !== '1') {
            IDLM_Telemetry::log('interakt_debug', 'info', "Skipped Interakt event trigger for Lead ID: {$lead_id} because the integration is disabled in settings.", __FILE__, __LINE__);
            return;
        }

        if (empty($api_key)) {
            IDLM_Telemetry::log('interakt_debug', 'warning', "Skipped Interakt event trigger for Lead ID: {$lead_id} because the API key is not configured.", __FILE__, __LINE__);
            return;
        }

        if (empty($event_name)) {
            IDLM_Telemetry::log('interakt_debug', 'warning', "Skipped Interakt event trigger for Lead ID: {$lead_id} because the event name is empty.", __FILE__, __LINE__);
            return;
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        
        // Fetch current details of the lead to get latest properties
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_leads} WHERE id = %d", $lead_id));
        if (!$lead) {
            IDLM_Telemetry::log('interakt_debug', 'warning', "Skipped Interakt event trigger because Lead ID: {$lead_id} was not found in the database.", __FILE__, __LINE__);
            return;
        }

        if (empty($lead->phone)) {
            IDLM_Telemetry::log('interakt_debug', 'warning', "Skipped Interakt event trigger for Lead ID: {$lead_id} because the phone number field is empty.", __FILE__, __LINE__);
            return;
        }

        // Format phone number into countryCode and phoneNumber
        $phone_parsed = self::format_phone_for_interakt($lead->phone);
        if (empty($phone_parsed['phoneNumber'])) {
            IDLM_Telemetry::log('interakt_debug', 'warning', "Skipped Interakt event trigger for Lead ID: {$lead_id} because the phone number '{$lead->phone}' could not be parsed.", __FILE__, __LINE__);
            return;
        }

        IDLM_Telemetry::log('interakt_debug', 'info', "Preparing to dispatch Interakt event '{$event_name}' for Lead ID: {$lead_id}.", __FILE__, __LINE__);

        // Prepare Interakt payload matching official Event Track API specs
        $payload = array(
            'userId'      => (string) $lead->id,
            'countryCode' => $phone_parsed['countryCode'],
            'phoneNumber' => $phone_parsed['phoneNumber'],
            'event'       => trim($event_name),
            'traits'      => array(
                'name'             => self::sanitize_trait_value($lead->first_name . ' ' . $lead->last_name),
                'email'            => self::sanitize_trait_value($lead->email),
                'project_location' => self::sanitize_trait_value($lead->property_location),
                'budget'           => self::sanitize_trait_value($lead->budget),
                'property_type'    => self::sanitize_trait_value($lead->property_type),
                'lead_id'          => intval($lead->id),
                'source'           => self::sanitize_trait_value($lead->source),
                'triggered_at'     => current_time('mysql')
            ),
            'createdAt'   => gmdate('c')
        );

        // Queue in Action Scheduler for background processing to avoid slowing down user requests
        if (function_exists('as_enqueue_async_action')) {
            as_enqueue_async_action('idlm_async_interakt_track_event', array(
                'payload' => $payload,
                'api_key' => $api_key,
                'lead_id' => $lead_id
            ), 'idlm-interakt');
            IDLM_DB::log_activity($lead_id, 'interakt_queued', "Queued Interakt WhatsApp automation event '{$event_name}' via Action Scheduler.", 0);
            IDLM_Telemetry::log('interakt_debug', 'info', "Queued event '{$event_name}' for Lead ID: {$lead_id} in Action Scheduler.", __FILE__, __LINE__);
        } else {
            IDLM_Telemetry::log('interakt_debug', 'info', "Action Scheduler not active. Falling back to synchronous dispatch for event '{$event_name}', Lead ID: {$lead_id}.", __FILE__, __LINE__);
            // Fallback to synchronous HTTP POST
            self::send_track_event_to_interakt($payload, $api_key, $lead_id);
        }
    }

    /**
     * Action Scheduler callback for async delivery
     */
    public static function process_async_track_event($payload, $api_key, $lead_id = 0) {
        self::send_track_event_to_interakt($payload, $api_key, $lead_id);
    }

    /**
     * Format a phone number for Interakt (splitting country code from the rest of the digits).
     */
    public static function format_phone_for_interakt($phone) {
        // Remove all non-numeric characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);
        
        // Normalize leading zero for 11-digit numbers
        if (strlen($cleaned) === 11 && strpos($cleaned, '0') === 0) {
            $cleaned = substr($cleaned, 1);
        }
        
        // Default country code is +91 (India)
        $country_code = '+91';
        $number = $cleaned;
        
        if (strlen($cleaned) === 12 && strpos($cleaned, '91') === 0) {
            $country_code = '+91';
            $number = substr($cleaned, 2);
        } elseif (strlen($cleaned) === 10) {
            $country_code = '+91';
            $number = $cleaned;
        } elseif (strlen($cleaned) > 10) {
            // Take the last 10 digits as the phone number, and the prefix as country code
            $number = substr($cleaned, -10);
            $country_code = '+' . substr($cleaned, 0, strlen($cleaned) - 10);
        }
        
        return array(
            'countryCode' => $country_code,
            'phoneNumber' => $number
        );
    }

    /**
     * Sanitize a trait value according to Interakt requirements.
     * Interakt returns 400 Bad Request if a trait contains a newline, tab, or more than 2 consecutive spaces.
     */
    public static function sanitize_trait_value($val) {
        if (!is_string($val)) {
            return $val;
        }
        // Replace tabs and newlines with space
        $val = str_replace(array("\r", "\n", "\t"), ' ', $val);
        // Replace 2 or more consecutive spaces with a single space
        $val = preg_replace('/\s+/', ' ', $val);
        return trim($val);
    }

    /**
     * Send HTTP POST to Interakt APIs
     */
    public static function send_track_event_to_interakt($payload, $api_key, $lead_id = 0) {
        $endpoint = 'https://api.interakt.ai/v1/public/track/events/';
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($api_key . ':'),
                'Content-Type'  => 'application/json'
            ),
            'body'    => json_encode($payload),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            $err_msg = $response->get_error_message();
            if ($lead_id) {
                IDLM_DB::log_activity($lead_id, 'interakt_failed', "Interakt track event failed: " . $err_msg, 0);
            }
            IDLM_Telemetry::log('interakt_debug', 'error', "Interakt API transport error: " . $err_msg, __FILE__, __LINE__);
            return false;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($code === 200 || $code === 202 || $code === 201) {
            if ($lead_id) {
                IDLM_DB::log_activity($lead_id, 'interakt_success', "Interakt WhatsApp automation event '{$payload['event']}' sent successfully.", 0);
            }
            IDLM_Telemetry::log('interakt_debug', 'info', "Interakt API success response (HTTP {$code}) for Lead ID: {$lead_id}.", __FILE__, __LINE__);
            return true;
        } elseif ($code === 429) {
            if ($lead_id) {
                IDLM_DB::log_activity($lead_id, 'interakt_failed', "Interakt rate limit exceeded (HTTP 429). Please contact Interakt support or wait before trying again.", 0);
            }
            IDLM_Telemetry::log('interakt_debug', 'error', "Interakt API rate limit exceeded (HTTP 429) for Lead ID: {$lead_id}.", __FILE__, __LINE__);
            return false;
        } else {
            if ($lead_id) {
                IDLM_DB::log_activity($lead_id, 'interakt_failed', "Interakt API returned code {$code}. Body: " . substr($body, 0, 300), 0);
            }
            IDLM_Telemetry::log('interakt_debug', 'warning', "Interakt API failed response (HTTP {$code}) for Lead ID: {$lead_id}. Response: " . substr($body, 0, 300), __FILE__, __LINE__);
            return false;
        }
    }

    /**
     * Register WordPress REST API routes for Interakt and Meta incoming webhooks
     */
    public static function register_webhook_route() {
        register_rest_route('idlm/v1', '/interakt-webhook', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_webhook_request'),
            'permission_callback' => '__return_true', // Validated via HMAC signature
        ));

        register_rest_route('idlm/v1', '/meta-webhook', array(
            array(
                'methods'             => 'GET',
                'callback'            => array(__CLASS__, 'handle_meta_webhook_verification'),
                'permission_callback' => '__return_true',
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array(__CLASS__, 'handle_meta_webhook_request'),
                'permission_callback' => '__return_true', // Validated via HMAC signature
            )
        ));
    }

    /**
     * Handle incoming Interakt Webhook requests
     */
    public static function handle_webhook_request(WP_REST_Request $request) {
        $enabled = get_option('idlm_interakt_enabled', '0');
        $secret = get_option('idlm_interakt_webhook_secret', '');

        if ($enabled !== '1' || empty($secret)) {
            return new WP_REST_Response(array('message' => 'Webhooks disabled or not configured'), 403);
        }

        $signature = $request->get_header('Interakt-Signature');
        $payload = $request->get_body();

        if (empty($signature) || !self::verify_webhook_signature($payload, $signature, $secret)) {
            return new WP_REST_Response(array('message' => 'Invalid signature'), 401);
        }

        $data = json_decode($payload, true);
        if (!$data || !isset($data['type'])) {
            return new WP_REST_Response(array('message' => 'Invalid payload format'), 400);
        }

        if ($data['type'] === 'message_received') {
            $customer = isset($data['data']['customer']) ? $data['data']['customer'] : array();
            $message = isset($data['data']['message']) ? $data['data']['message'] : array();

            $phone = isset($customer['channel_phone_number']) ? $customer['channel_phone_number'] : '';
            $text = isset($message['message']) ? $message['message'] : '';
            $media_type = isset($message['message_content_type']) ? $message['message_content_type'] : '';
            $reply = self::format_incoming_message($text, $media_type);

            if (!empty($phone) && !empty($reply)) {
                self::log_customer_reply_to_lead($phone, $reply);
            }
        }

        return new WP_REST_Response(array('success' => true), 200);
    }

    /**
     * Format an Interakt incoming message for the CRM activity timeline.
     *
     * Media messages must still create an activity when no caption/text is
     * present. Interakt media URLs are intentionally not stored because they
     * are not accessible outside the Interakt session.
     */
    private static function format_incoming_message($text, $media_type) {
        $text = sanitize_textarea_field((string) $text);
        $media_type = sanitize_text_field((string) $media_type);

        $is_text_message = empty($media_type) || strtolower($media_type) === 'text';
        if ($is_text_message) {
            return $text;
        }

        $type_label = !empty($media_type) ? $media_type : 'Media';
        $parts = array('[' . $type_label . ' received]');

        if (!empty($text)) {
            $parts[] = 'Text: ' . $text;
        }

        return implode("\n", $parts);
    }

    /**
     * Verify Interakt SHA256 HMAC Signature
     */
    public static function verify_webhook_signature($payload, $signature, $secret) {
        if (strpos($signature, 'sha256=') === 0) {
            $signature = substr($signature, 7);
        }
        
        $computed = hash_hmac('sha256', $payload, $secret);
        return hash_equals($signature, $computed);
    }

    /**
     * Match incoming customer phone suffix to CRM Lead database and record comment activity
     */
    public static function log_customer_reply_to_lead($phone, $text) {
        $cleaned_search = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleaned_search) > 10) {
            $cleaned_search = substr($cleaned_search, -10);
        }

        if (empty($cleaned_search)) {
            return;
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $lead_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_leads} WHERE phone LIKE %s ORDER BY id DESC LIMIT 1",
            '%' . $wpdb->esc_like($cleaned_search)
        ));

        if ($lead_id) {
            $msg = "[WhatsApp Reply]: " . sanitize_textarea_field($text);
            IDLM_DB::log_activity($lead_id, 'whatsapp_reply', $msg, 0);
        }
    }

    /**
     * Handle incoming Meta Webhook validation (GET)
     */
    public static function handle_meta_webhook_verification(WP_REST_Request $request) {
        $enabled = get_option('idlm_meta_webhook_enabled', '0');
        $verify_token = get_option('idlm_meta_webhook_verify_token', '');

        if ($enabled !== '1' || empty($verify_token)) {
            return new WP_REST_Response('Verification disabled or Verify Token empty', 403);
        }

        $mode = $request->get_param('hub_mode');
        $token = $request->get_param('hub_verify_token');
        $challenge = $request->get_param('hub_challenge');

        // Check fallback query variables directly
        if (empty($mode)) {
            $mode = isset($_GET['hub_mode']) ? sanitize_text_field($_GET['hub_mode']) : '';
        }
        if (empty($token)) {
            $token = isset($_GET['hub_verify_token']) ? sanitize_text_field($_GET['hub_verify_token']) : '';
        }
        if (empty($challenge)) {
            $challenge = isset($_GET['hub_challenge']) ? sanitize_text_field($_GET['hub_challenge']) : '';
        }

        if ($mode === 'subscribe' && $token === $verify_token) {
            header('Content-Type: text/plain');
            echo esc_html($challenge);
            exit;
        }

        return new WP_REST_Response('Forbidden', 403);
    }

    /**
     * Handle incoming Meta Webhook event requests (POST)
     */
    public static function handle_meta_webhook_request(WP_REST_Request $request) {
        $enabled = get_option('idlm_meta_webhook_enabled', '0');
        $app_secret = get_option('idlm_meta_webhook_app_secret', '');

        if ($enabled !== '1' || empty($app_secret)) {
            return new WP_REST_Response(array('message' => 'Webhooks disabled or not configured'), 403);
        }

        $signature = $request->get_header('X-Hub-Signature-256');
        $payload = $request->get_body();

        if (empty($signature) || !self::verify_meta_webhook_signature($payload, $signature, $app_secret)) {
            return new WP_REST_Response(array('message' => 'Invalid signature'), 401);
        }

        $data = json_decode($payload, true);
        if (!$data || !isset($data['object']) || $data['object'] !== 'whatsapp_business_account') {
            return new WP_REST_Response(array('message' => 'Invalid payload format'), 400);
        }

        if (isset($data['entry'][0]['changes'][0]['value']['messages'][0])) {
            $msg_obj = $data['entry'][0]['changes'][0]['value']['messages'][0];
            $phone = isset($msg_obj['from']) ? $msg_obj['from'] : '';
            $text = '';

            if (isset($msg_obj['text']['body'])) {
                $text = $msg_obj['text']['body'];
            } elseif (isset($msg_obj['type'])) {
                $type = $msg_obj['type'];
                if ($type === 'button' && isset($msg_obj['button']['text'])) {
                    $text = $msg_obj['button']['text'];
                } elseif ($type === 'interactive' && isset($msg_obj['interactive']['button_reply']['title'])) {
                    $text = $msg_obj['interactive']['button_reply']['title'];
                } else {
                    $text = '[' . ucfirst($type) . ' message received]';
                }
            }

            if (!empty($phone) && !empty($text)) {
                self::log_meta_customer_reply_to_lead($phone, $text);
            }
        }

        return new WP_REST_Response(array('success' => true), 200);
    }

    /**
     * Verify Meta SHA256 HMAC Signature
     */
    public static function verify_meta_webhook_signature($payload, $signature, $secret) {
        if (strpos($signature, 'sha256=') === 0) {
            $signature = substr($signature, 7);
        }
        
        $computed = hash_hmac('sha256', $payload, $secret);
        return hash_equals($signature, $computed);
    }

    /**
     * Match incoming Meta customer phone to lead and record direct comment activity
     */
    public static function log_meta_customer_reply_to_lead($phone, $text) {
        $cleaned_search = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($cleaned_search) > 10) {
            $cleaned_search = substr($cleaned_search, -10);
        }

        if (empty($cleaned_search)) {
            return;
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $lead_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_leads} WHERE phone LIKE %s ORDER BY id DESC LIMIT 1",
            '%' . $wpdb->esc_like($cleaned_search)
        ));

        if ($lead_id) {
            $msg = "[Direct WA Reply]: " . sanitize_textarea_field($text);
            IDLM_DB::log_activity($lead_id, 'whatsapp_reply', $msg, 0);
        }
    }
}
