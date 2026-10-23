<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Interakt {
    public static function init() {
        IDLM_Interakt_Batch::init();
        IDLM_Interakt_Dispatch::init();
        // Hook when a lead call status changes to No Answer (Legacy/Wrapper)
        add_action('idlm_lead_call_status_no_answer', array(__CLASS__, 'trigger_no_answer_automation'), 10, 3);

        // Generalized hook for mapped Interakt triggers
        add_action('idlm_trigger_interakt_event', array(__CLASS__, 'trigger_generic_event'), 10, 4);
        
        // Hook for Action Scheduler execution
        add_action('idlm_async_interakt_track_event', array(__CLASS__, 'process_async_track_event'), 10, 4);

        // Hook for incoming WhatsApp Webhook listener
        add_action('rest_api_init', array(__CLASS__, 'register_webhook_route'));
    }

    /** Return the filtered Interakt Inbox URL for a customer phone number. */
    public static function inbox_url($phone) {
        $formatted_phone = self::format_phone_for_interakt($phone);
        $channel_phone_number = preg_replace('/\D+/', '', $formatted_phone['countryCode'] . $formatted_phone['phoneNumber']);
        if (!$channel_phone_number) {
            return '';
        }
        return 'https://app.interakt.ai/inbox?channelPhoneNumber=' . rawurlencode($channel_phone_number) . '&filter=' . rawurlencode(wp_json_encode(array(
            'chatStatus' => null, 'channel' => array('Whatsapp'), 'label' => array(), 'assignee' => array(),
            'chatReplyStatus' => array(), 'aiHandover' => null, 'tags' => array(), 'customerServiceWindow' => null,
            'unreadStatus' => null, 'lastMessageTime' => null, 'showSpamChats' => null,
            'sortData' => array('field' => 'last_activity_at_utc', 'order' => 'desc'),
        ))) . '&chatType=All';
    }

    /**
     * Legacy wrapper for "No Answer" status triggers
     */
    public static function trigger_no_answer_automation($lead_id, $old_lead, $rule = null) {
        $event_name = is_array($rule) && isset($rule['event']) ? $rule['event'] : get_option('idlm_interakt_event_name', 'lead_no_answer');
        if (is_array($rule)) { unset($rule['event']); }
        return self::trigger_generic_event($lead_id, $event_name, $old_lead, $rule);
    }

    /**
     * Trigger WhatsApp automation on Interakt for a lead with a dynamic event name
     */
    public static function trigger_generic_event($lead_id, $event_name, $old_lead = null, $rule = null) {
        $key = IDLM_Interakt_Dispatch::enqueue($lead_id, $event_name, $old_lead, $rule);
        if (false === $key && IDLM_Lead_Outbox::dispatching()) {
            // Ineligible intent is terminal; failed durable handoff remains retryable.
            throw new RuntimeException('Interakt handoff was not accepted.');
        }
        return $key;
    }

    /** Legacy payload/key callbacks are deliberately refused; new callbacks carry one opaque ID. */
    public static function process_async_track_event($payload, $api_key = null, $lead_id = 0, $generation = null) {
        if (!is_string($payload) || null !== $api_key) { return false; }
        return IDLM_Interakt_Dispatch::process($payload);
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
        return IDLM_Recovery_Guard::run(static function() use ($payload, $api_key, $lead_id) { return self::send_track_event_to_interakt_work($payload, $api_key, $lead_id); }, null, false, '', array());
    }

    private static function send_track_event_to_interakt_work($payload, $api_key, $lead_id = 0) {
        $endpoint = 'https://api.interakt.ai/v1/public/track/events/';
        // Dashboard Secret Keys already encode username:password. Retain the
        // historical raw-username form for existing installations.
        $decoded = base64_decode($api_key, true);
        $basic_key = is_string($decoded) && false !== strpos($decoded, ':') ? $api_key : base64_encode($api_key . ':');
        
        if (!IDLM_Recovery_Guard::lease_valid()) { return false; }
        $response = IDLM_Secret_Store::request($endpoint, array(
            'headers' => array(
                'Authorization' => 'Basic ' . $basic_key,
                'Content-Type'  => 'application/json'
            ),
            'body'    => json_encode($payload),
            'timeout' => 15
        ), array($api_key));

        if (!IDLM_Recovery_Guard::lease_valid()) { return false; }
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
        $enabled = IDLM_WhatsApp_Inbox::setting('idlm_interakt_enabled', '0');
        $secret = IDLM_WhatsApp_Inbox::setting('idlm_interakt_webhook_secret', '');

        if (is_wp_error($enabled) || is_wp_error($secret)) { return new WP_REST_Response(array('message'=>'Webhook settings unavailable'),503); }
        if ($enabled !== '1' || !is_string($secret) || '' === $secret) {
            return new WP_REST_Response(array('message' => 'Webhooks disabled or not configured'), 403);
        }

        $signature = $request->get_header('Interakt-Signature');
        $payload = $request->get_body();

        if (empty($signature) || !self::verify_webhook_signature($payload, $signature, $secret)) {
            return new WP_REST_Response(array('message' => 'Invalid signature'), 401);
        }

        if (strlen($payload) > IDLM_WhatsApp_Inbox::MAX_BYTES) { return new WP_REST_Response(array('message'=>'Payload too large.'),413); }
        $data = json_decode($payload, true);
        if (!is_array($data) || !is_string($data['type'] ?? null)) { return new WP_REST_Response(array('message'=>'Invalid payload format'),400); }
        if ('message_received' !== $data['type']) { return IDLM_WhatsApp_Inbox::response(array()); }
        $customer=$data['data']['customer'] ?? array();$message=$data['data']['message'] ?? array();
        if (!is_array($customer) || !is_array($message) || !is_string($message['message'] ?? '') || !is_string($message['message_content_type'] ?? '')) { return new WP_REST_Response(array('message'=>'Invalid message format'),400); }
        $reply=self::format_incoming_message($message['message'] ?? '',$message['message_content_type'] ?? '');
        if (IDLM_Lead_Connector::option('idlm_interakt_lead_capture','0') === '1') {
            $event=IDLM_Lead_Connector::interakt_payload($customer,$message,$reply);
            if (!$event) { return new WP_REST_Response(array('message'=>'Invalid incoming contact or referral'),400); }
            $saved=IDLM_Lead_Connector::enqueue('interakt',$event['id'],$event);
            return new WP_REST_Response(array('success'=>$saved === true,'message'=>$saved === true ? 'Incoming message retained for CRM processing.' : 'Intake could not be retained; retry required.'),$saved === true ? 200 : 503);
        }
        return IDLM_WhatsApp_Inbox::response(array(IDLM_WhatsApp_Inbox::message('interakt',$message['id'] ?? null,$customer['channel_phone_number'] ?? null,$reply)));
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
     * Match incoming customer full phone to CRM Lead database and record comment activity
     */
    public static function log_customer_reply_to_lead($phone, $text, $message_id = '') {
        $message=IDLM_WhatsApp_Inbox::message('interakt',$message_id,$phone,$text);
        return $message ? IDLM_WhatsApp_Inbox::record($message) : new WP_Error('idlm_whatsapp_identity','A valid provider message ID is required.');
    }

    /**
     * Handle incoming Meta Webhook validation (GET)
     */
    public static function handle_meta_webhook_verification(WP_REST_Request $request) {
        $enabled = get_option('idlm_meta_webhook_enabled', '0');
        $verify_token = IDLM_Secret_Store::get('idlm_meta_webhook_verify_token', '', true);

        if ($enabled !== '1' || empty($verify_token)) {
            return new WP_REST_Response('Verification disabled or Verify Token empty', 403);
        }

        $params = array();
        foreach (array('hub_mode', 'hub_verify_token', 'hub_challenge') as $key) {
            // REST parameters are already unslashed. Only the raw WP fallback
            // needs wp_unslash; reject non-string shapes before sanitization.
            $value = $request->get_param($key);
            if ($value === null || $value === '') {
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0070: handle_meta_webhook_verification(): provider verification validates required string mode/token/challenge and protected token before escaped plain-text output; this public provider GET protocol is authenticated by the verify token, not a WordPress session nonce.
                if (isset($_GET[$key]) && !is_string($_GET[$key])) {
                // phpcs:enable WordPress.Security.NonceVerification.Recommended
                    return new WP_REST_Response('Forbidden', 403);
                }
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0071: handle_meta_webhook_verification(): provider verification validates required string mode/token/challenge and protected token before escaped plain-text output; this public provider GET protocol is authenticated by the verify token, not a WordPress session nonce.
                $value = isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
                // phpcs:enable WordPress.Security.NonceVerification.Recommended
            }
            if (!is_string($value) || $value === '') {
                return new WP_REST_Response('Forbidden', 403);
            }
            $params[$key] = $value;
        }

        if ($params['hub_mode'] === 'subscribe' && $params['hub_verify_token'] === $verify_token) {
            header('Content-Type: text/plain');
            echo esc_html($params['hub_challenge']);
            exit;
        }

        return new WP_REST_Response('Forbidden', 403);
    }

    /**
     * Handle incoming Meta Webhook event requests (POST)
     */
    public static function handle_meta_webhook_request(WP_REST_Request $request) {
        $enabled = IDLM_WhatsApp_Inbox::setting('idlm_meta_webhook_enabled', '0');
        $app_secret = IDLM_WhatsApp_Inbox::setting('idlm_meta_webhook_app_secret', '');

        if (is_wp_error($enabled) || is_wp_error($app_secret)) { return new WP_REST_Response(array('message'=>'Webhook settings unavailable'),503); }
        if ($enabled !== '1' || !is_string($app_secret) || '' === $app_secret) {
            return new WP_REST_Response(array('message' => 'Webhooks disabled or not configured'), 403);
        }

        $signature = $request->get_header('X-Hub-Signature-256');
        $payload = $request->get_body();

        if (empty($signature) || !self::verify_meta_webhook_signature($payload, $signature, $app_secret)) {
            return new WP_REST_Response(array('message' => 'Invalid signature'), 401);
        }

        if (strlen($payload) > IDLM_WhatsApp_Inbox::MAX_BYTES) { return new WP_REST_Response(array('message'=>'Payload too large.'),413); }
        $data=json_decode($payload,true);
        if (!is_array($data) || ($data['object'] ?? '')!=='whatsapp_business_account' || !is_array($data['entry'] ?? null)) { return new WP_REST_Response(array('message'=>'Invalid payload format'),400); }
        $messages=array();
        foreach ($data['entry'] as $entry) {
            if (!is_array($entry) || !is_array($entry['changes'] ?? null)) { return new WP_REST_Response(array('message'=>'Invalid entry format'),400); }
            foreach ($entry['changes'] as $change) {
                if (!is_array($change) || !is_array($change['value'] ?? null)) { return new WP_REST_Response(array('message'=>'Invalid change format'),400); }
                $value=$change['value'];
                if (!array_key_exists('messages',$value)) { continue; } // Delivery statuses are not inbound replies.
                if (!is_array($value['messages'])) { return new WP_REST_Response(array('message'=>'Invalid messages format'),400); }
                foreach ($value['messages'] as $message) {
                    $messages[]=self::meta_incoming_message($message);
                    if (count($messages)>IDLM_WhatsApp_Inbox::MAX_MESSAGES) { return new WP_REST_Response(array('message'=>'Message batch too large.'),413); }
                }
            }
        }
        return IDLM_WhatsApp_Inbox::response($messages);
    }

    private static function meta_incoming_message($message) {
        if (!is_array($message)) { return false; }
        $type=$message['type'] ?? (isset($message['text']) ? 'text' : 'unknown');
        if (!is_string($type) || !preg_match('/^[a-z_]{1,40}$/D',$type)) { return false; }
        $text=$message['text']['body'] ?? ($message['button']['text'] ?? ($message['interactive']['button_reply']['title'] ?? ($message['interactive']['list_reply']['title'] ?? null)));
        if (null !== $text && !is_string($text)) { return false; }
        if (null === $text && 'text' === $type) { return false; }
        if (null === $text) { $text='['.ucfirst($type).' message received]'; }
        return IDLM_WhatsApp_Inbox::message('meta',$message['id'] ?? null,$message['from'] ?? null,$text);
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
     * Match incoming Meta customer full phone to lead and record direct comment activity
     */
    public static function log_meta_customer_reply_to_lead($phone, $text, $message_id = '') {
        $message=IDLM_WhatsApp_Inbox::message('meta',$message_id,$phone,$text);
        return $message ? IDLM_WhatsApp_Inbox::record($message) : new WP_Error('idlm_whatsapp_identity','A valid provider message ID is required.');
    }
}
