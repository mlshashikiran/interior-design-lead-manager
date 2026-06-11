<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_CAPI {
    public static function init() {
        // Hook lead conversion stage changes
        add_action('idlm_lead_stage_converted', array(__CLASS__, 'queue_capi_event'), 10, 3);
        // Hook for Action Scheduler execution
        add_action('idlm_async_capi_upload_event', array(__CLASS__, 'process_capi_event'), 10, 2);
    }

    /**
     * Check if a successful CAPI event has already been sent for this lead, platform, and event name.
     */
    private static function is_event_already_sent($lead_id, $platform, $event_name) {
        global $wpdb;
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_capi} WHERE lead_id = %d AND platform = %s AND event_name = %s AND status = 'success' LIMIT 1",
            $lead_id,
            $platform,
            $event_name
        ));
        
        return !empty($exists);
    }

    /**
     * Queue Conversion API event using Action Scheduler
     */
    public static function queue_capi_event($lead_id, $stage, $old_lead) {
        // Add a 5-minute (300 seconds) delay buffer for post-ingestion stages (Qualified, Quote Shared, Won)
        // to allow representatives an "undo" window in case of accidental changes.
        // Initial lead creation ('New') runs immediately.
        $delay = ($stage === 'New') ? 0 : 300;
        $schedule_time = time() + $delay;
        $delay_msg = $delay > 0 ? " (delayed by 5 mins)" : "";

        if (function_exists('as_schedule_single_action')) {
            as_schedule_single_action($schedule_time, 'idlm_async_capi_upload_event', array(
                'lead_id' => $lead_id,
                'stage' => $stage
            ));
            IDLM_DB::log_activity($lead_id, 'capi_queue', "Queued Conversions API background sync via Action Scheduler for stage: {$stage}{$delay_msg}.", 0);
        } else {
            // Fallback to WP Cron if Action Scheduler is disabled or not booted yet
            wp_schedule_single_event($schedule_time, 'idlm_async_capi_upload_event', array(
                'lead_id' => $lead_id,
                'stage' => $stage
            ));
            IDLM_DB::log_activity($lead_id, 'capi_queue', "Queued Conversions API background sync via WP Cron for stage: {$stage}{$delay_msg}.", 0);
        }
    }

    /**
     * Background worker handling CAPI processing
     */
    public static function process_capi_event($lead_id, $stage) {
        global $wpdb;
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
        if (!$lead) {
            return;
        }

        // Verify that the lead's current state in the database still matches the event criteria.
        // This acts as a safety gate if the representative changed their mind within the 5-minute queue window.
        if ($stage === 'Qualified') {
            $is_current_qualified = ($lead->lead_quality === 'Qualified' || in_array($lead->stage, array('Qualified', 'Quote Shared', 'Won')));
            if (!$is_current_qualified) {
                IDLM_DB::log_activity($lead_id, 'capi_abort', "Conversions API upload aborted: Lead is no longer marked as Qualified.", 0);
                return;
            }
        } elseif ($stage === 'Quote Shared') {
            if ($lead->stage !== 'Quote Shared' && $lead->stage !== 'Won') {
                IDLM_DB::log_activity($lead_id, 'capi_abort', "Conversions API upload aborted: Lead stage is no longer Quote Shared.", 0);
                return;
            }
        } elseif ($stage === 'Won') {
            if ($lead->stage !== 'Won') {
                IDLM_DB::log_activity($lead_id, 'capi_abort', "Conversions API upload aborted: Lead stage is no longer Won.", 0);
                return;
            }
        }

        // Fire for each enabled platform
        self::fire_meta_capi($lead, $stage);
        self::fire_google_capi($lead, $stage);
        self::fire_linkedin_capi($lead, $stage);
    }

    /**
     * Meta Conversion API (CAPI) Upload
     */
    public static function fire_meta_capi($lead, $stage, $force = false) {
        $enabled = get_option('idlm_meta_capi_enabled', '0');
        if (!$enabled) {
            return;
        }

        $pixel_id = get_option('idlm_meta_pixel_id');
        $access_token = get_option('idlm_meta_access_token');
        if (empty($pixel_id) || empty($access_token)) {
            IDLM_DB::log_capi_event($lead->id, 'meta', $stage, array(), 'Missing Pixel ID or Access Token in settings', 'failed');
            return;
        }

        // Map internal stages to Meta Event Names
        $event_mappings = get_option('idlm_meta_event_mappings', array(
            'Qualified' => 'Contact',
            'Quote Shared' => 'Subscribe',
            'Won' => 'Purchase'
        ));

        $event_name = isset($event_mappings[$stage]) ? $event_mappings[$stage] : 'Lead';

        if (!$force && self::is_event_already_sent($lead->id, 'meta', $event_name)) {
            return;
        }

        $hashed_email = !empty($lead->email) ? hash('sha256', strtolower(trim($lead->email))) : '';
        $hashed_phone = !empty($lead->phone) ? hash('sha256', preg_replace('/[^0-9]/', '', $lead->phone)) : '';

        // Capture IP and User Agent if lead came from web form, fallback to server params
        $client_ip = !empty($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1';
        $client_ua = !empty($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'WordPressServer';

        $user_data = array();
        
        // Hashed email and phone must be arrays of hashed strings according to Meta specification (implemented by Shashi)
        if (!empty($hashed_email)) {
            $user_data['em'] = array($hashed_email);
        }
        if (!empty($hashed_phone)) {
            $user_data['ph'] = array($hashed_phone);
        }

        $user_data['client_ip_address'] = $client_ip;
        $user_data['client_user_agent'] = $client_ua;

        if (!empty($lead->first_name)) {
            $user_data['fn'] = hash('sha256', strtolower(trim($lead->first_name)));
        }
        if (!empty($lead->last_name)) {
            $user_data['ln'] = hash('sha256', strtolower(trim($lead->last_name)));
        }

        // Add fbclid if captured
        if (!empty($lead->fbclid)) {
            $user_data['fbc'] = 'fb.1.' . time() . '.' . $lead->fbclid;
        }

        // Add Meta Lead ID as numeric float/integer type to retain precision (implemented by Shashi)
        if (!empty($lead->fb_lead_id)) {
            $user_data['lead_id'] = is_numeric($lead->fb_lead_id) ? 0 + $lead->fb_lead_id : $lead->fb_lead_id;
        }

        $custom_data = array(
            'event_source' => 'crm',
            'lead_event_source' => 'Interior Design Lead Manager',
            'lead_id' => $lead->id,
            'stage' => $stage,
            'property_type' => $lead->property_type,
            'num_rooms' => $lead->num_rooms,
            'source' => $lead->source
        );

        if (!empty($lead->deal_value) && floatval($lead->deal_value) > 0) {
            $custom_data['value'] = floatval($lead->deal_value);
            $custom_data['currency'] = 'INR';
        }

        $payload = array(
            'data' => array(
                array(
                    'event_name' => $event_name,
                    'event_time' => time(),
                    'event_source_url' => home_url('/'),
                    'action_source' => 'system_generated',
                    'user_data' => array_filter($user_data),
                    'custom_data' => $custom_data
                )
            )
        );

        // Updated API version to v25.0 to align with Meta latest API version standard (implemented by Shashi)
        $endpoint = "https://graph.facebook.com/v25.0/{$pixel_id}/events?access_token={$access_token}";
        
        $response = wp_remote_post($endpoint, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => json_encode($payload),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            $err_msg = $response->get_error_message();
            IDLM_DB::log_capi_event($lead->id, 'meta', $event_name, $payload, $err_msg, 'failed');
        } else {
            $body = wp_remote_retrieve_body($response);
            $code = wp_remote_retrieve_response_code($response);
            $status = 'failed';
            if ($code == 200 && !empty($body)) {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && isset($decoded['events_received']) && intval($decoded['events_received']) > 0 && !isset($decoded['error'])) {
                    $status = 'success';
                }
            }
            IDLM_DB::log_capi_event($lead->id, 'meta', $event_name, $payload, $body, $status);
        }
    }

    /**
     * Google Ads Offline Conversion (via GA4 Measurement Protocol)
     */
    public static function fire_google_capi($lead, $stage, $force = false) {
        $enabled = get_option('idlm_google_capi_enabled', '0');
        if (!$enabled) {
            return;
        }

        $measurement_id = get_option('idlm_google_measurement_id');
        $api_secret = get_option('idlm_google_api_secret');
        if (empty($measurement_id) || empty($api_secret)) {
            IDLM_DB::log_capi_event($lead->id, 'google', $stage, array(), 'Missing GA4 Measurement ID or API Secret in settings', 'failed');
            return;
        }

        // Map stages
        $event_mappings = get_option('idlm_google_event_mappings', array(
            'Qualified' => 'qualified_lead',
            'Quote Shared' => 'quote_shared',
            'Won' => 'won_conversion'
        ));

        $event_name = isset($event_mappings[$stage]) ? $event_mappings[$stage] : 'lead_conversion';

        if (!$force && self::is_event_already_sent($lead->id, 'google', $event_name)) {
            return;
        }

        // Generate client id or use existing
        $client_id = !empty($lead->gclid) ? $lead->gclid : 'idlm.' . $lead->id;

        $event_params = array(
            'engagement_time_msec' => 100,
            'lead_id' => $lead->id,
            'source' => $lead->source,
            'gclid' => $lead->gclid,
            'stage' => $stage,
            'property_type' => $lead->property_type,
            'num_rooms' => $lead->num_rooms
        );

        if (!empty($lead->deal_value) && floatval($lead->deal_value) > 0) {
            $event_params['value'] = floatval($lead->deal_value);
            $event_params['currency'] = 'INR';
        }

        $payload = array(
            'client_id' => $client_id,
            'events' => array(
                array(
                    'name' => $event_name,
                    'params' => $event_params
                )
            )
        );

        $endpoint = "https://www.google-analytics.com/mp/collect?measurement_id={$measurement_id}&api_secret={$api_secret}";

        $response = wp_remote_post($endpoint, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => json_encode($payload),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            $err_msg = $response->get_error_message();
            IDLM_DB::log_capi_event($lead->id, 'google', $event_name, $payload, $err_msg, 'failed');
        } else {
            $body = wp_remote_retrieve_body($response);
            $code = wp_remote_retrieve_response_code($response);
            // GA4 Measurement Protocol returns 204 No Content on success
            $status = ($code == 204 || $code == 200) ? 'success' : 'failed';
            
            $log_resp = !empty($body) ? $body : "Success Response (Code {$code})";
            IDLM_DB::log_capi_event($lead->id, 'google', $event_name, $payload, $log_resp, $status);
        }
    }

    /**
     * LinkedIn Conversion API Upload
     */
    public static function fire_linkedin_capi($lead, $stage, $force = false) {
        $enabled = get_option('idlm_linkedin_capi_enabled', '0');
        if (!$enabled) {
            return;
        }

        $conversion_id = get_option('idlm_linkedin_conversion_id');
        $access_token = get_option('idlm_linkedin_access_token');
        if (empty($conversion_id) || empty($access_token)) {
            IDLM_DB::log_capi_event($lead->id, 'linkedin', $stage, array(), 'Missing LinkedIn Conversion ID or Access Token in settings', 'failed');
            return;
        }

        // LinkedIn CAPI requires the user email or phone, or li_fat_id
        if (empty($lead->li_fat_id) && empty($lead->email)) {
            IDLM_DB::log_capi_event($lead->id, 'linkedin', $stage, array(), 'Failed: LinkedIn CAPI requires either li_fat_id or email', 'failed');
            return;
        }

        $event_name = "ConversionEvent: {$stage}";
        if (!$force && self::is_event_already_sent($lead->id, 'linkedin', $event_name)) {
            return;
        }

        $hashed_email = !empty($lead->email) ? hash('sha256', strtolower(trim($lead->email))) : '';

        $user_ids = array();
        if (!empty($lead->li_fat_id)) {
            $user_ids[] = array(
                'idType' => 'LI_FAT_ID',
                'idValue' => $lead->li_fat_id
            );
        }

        $payload = array(
            'conversion' => "urn:lla:llaPartnerConversion:{$conversion_id}",
            'conversionHappenedAt' => time() * 1000, // Miliseconds
            'user' => array(
                'userIds' => $user_ids,
                'emailHashes' => array_filter(array($hashed_email))
            )
        );

        if (!empty($lead->deal_value) && floatval($lead->deal_value) > 0) {
            $payload['conversionValue'] = array(
                'amount' => number_format(floatval($lead->deal_value), 2, '.', ''),
                'currencyCode' => 'INR'
            );
        }

        $endpoint = "https://api.linkedin.com/v2/conversionEvents";

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
                'X-Restli-Protocol-Version' => '2.0.0'
            ),
            'body'    => json_encode($payload),
            'timeout' => 15
        ));

        if (is_wp_error($response)) {
            $err_msg = $response->get_error_message();
            IDLM_DB::log_capi_event($lead->id, 'linkedin', $event_name, $payload, $err_msg, 'failed');
        } else {
            $body = wp_remote_retrieve_body($response);
            $code = wp_remote_retrieve_response_code($response);
            $status = 'failed';
            if (($code == 201 || $code == 200) && !empty($body)) {
                $decoded = json_decode($body, true);
                if (is_array($decoded) && !isset($decoded['error']) && !isset($decoded['serviceErrorCode'])) {
                    $status = 'success';
                }
            }
            IDLM_DB::log_capi_event($lead->id, 'linkedin', $event_name, $payload, $body, $status);
        }
    }

    /**
     * Retry CAPI log manually
     */
    public static function retry_capi_log($log_id) {
        global $wpdb;
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_capi} WHERE id = %d", $log_id));
        if (!$log) {
            return array('success' => false, 'message' => 'Log not found');
        }

        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $log->lead_id));
        if (!$lead) {
            return array('success' => false, 'message' => "Lead ID {$log->lead_id} not found");
        }

        // Determine the stage from event_name/payload or reverse map
        $stage = 'New';
        if ($log->platform === 'meta') {
            if ($log->event_name === 'Contact') $stage = 'Qualified';
            elseif ($log->event_name === 'Subscribe') $stage = 'Quote Shared';
            elseif ($log->event_name === 'Purchase') $stage = 'Won';
        } elseif ($log->platform === 'google') {
            if ($log->event_name === 'qualified_lead') $stage = 'Qualified';
            elseif ($log->event_name === 'quote_shared') $stage = 'Quote Shared';
            elseif ($log->event_name === 'won_conversion') $stage = 'Won';
        } elseif ($log->platform === 'linkedin') {
            if (strpos($log->event_name, 'Qualified') !== false) $stage = 'Qualified';
            elseif (strpos($log->event_name, 'Quote Shared') !== false) $stage = 'Quote Shared';
            elseif (strpos($log->event_name, 'Won') !== false) $stage = 'Won';
        }

        // Fire CAPI event with force = true
        if ($log->platform === 'meta') {
            self::fire_meta_capi($lead, $stage, true);
        } elseif ($log->platform === 'google') {
            self::fire_google_capi($lead, $stage, true);
        } elseif ($log->platform === 'linkedin') {
            self::fire_linkedin_capi($lead, $stage, true);
        }

        // Fetch latest created log entry
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $new_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_capi} WHERE lead_id = %d AND platform = %s AND event_name = %s ORDER BY id DESC LIMIT 1",
            $lead->id,
            $log->platform,
            $log->event_name
        ));

        if ($new_log && $new_log->status === 'success') {
            return array('success' => true, 'message' => 'Resent successfully.');
        } else {
            $err = ($new_log) ? $new_log->response : 'Unknown API error';
            // Suppress serialized or JSON details slightly for readable message
            $err_decoded = json_decode($err, true);
            if (is_array($err_decoded) && isset($err_decoded['error']['message'])) {
                $err = $err_decoded['error']['message'];
            }
            return array('success' => false, 'message' => $err);
        }
    }
}
