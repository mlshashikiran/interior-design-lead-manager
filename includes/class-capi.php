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
        // Hook for handling duplicate lead attribution merges
        add_action('idlm_lead_attribution_merged', array(__CLASS__, 'handle_attribution_merged'), 10, 2);
    }

    /**
     * Re-fire CAPI events with updated attribution data when a duplicate lead merges its click IDs/UTMs.
     */
    public static function handle_attribution_merged($lead_id, $merged_fields) {
        global $wpdb;
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
        if (!$lead) {
            return;
        }

        // We only need to re-fire CAPI if critical attribution fields were added (e.g. fb_lead_id, fbclid, gclid, etc.)
        $critical_fields = array('fb_lead_id', 'fbclid', 'gclid', 'gbraid', 'wbraid', 'li_fat_id');
        $has_critical_update = false;
        foreach ($critical_fields as $field) {
            if (array_key_exists($field, $merged_fields)) {
                $has_critical_update = true;
                break;
            }
        }

        if (!$has_critical_update) {
            return;
        }

        // Query successful CAPI events sent for this lead
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $sent_events = $wpdb->get_results($wpdb->prepare(
            "SELECT platform, event_name FROM {$table_capi} WHERE lead_id = %d AND status = 'success'",
            $lead_id
        ));

        if (empty($sent_events)) {
            return;
        }

        foreach ($sent_events as $sent) {
            $platform = $sent->platform;
            $event_name = $sent->event_name;

            if ($platform === 'meta') {
                $mappings = get_option('idlm_meta_event_mappings', array(
                    'New' => 'Lead',
                    'Qualified' => 'Contact',
                    'Quote Shared' => 'Subscribe',
                    'Won' => 'Purchase'
                ));
                $stage = array_search($event_name, $mappings);
                if ($stage !== false) {
                    self::fire_meta_capi($lead, $stage, true); // $force = true
                }
            } elseif ($platform === 'google') {
                $mappings = get_option('idlm_google_event_mappings', array(
                    'Qualified' => 'CRM Qualified Lead',
                    'Won' => 'Closed Won Interior Project'
                ));
                $stage = array_search($event_name, $mappings);
                if ($stage !== false) {
                    self::fire_google_capi($lead, $stage, true); // $force = true
                }
            } elseif ($platform === 'linkedin') {
                $mappings = get_option('idlm_linkedin_event_mappings', array(
                    'Qualified' => 'CRM Qualified Lead',
                    'Won' => 'Closed Won'
                ));
                $stage = array_search($event_name, $mappings);
                if ($stage !== false) {
                    self::fire_linkedin_capi($lead, $stage, true); // $force = true
                }
            }
        }
    }

    /**
     * Check if a successful CAPI event has already been sent for this lead, platform, and event name.
     */
    private static function is_event_already_sent($lead_id, $platform, $event_name) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';
        
        // Resolve canonical lead ID
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lead_info = $wpdb->get_row($wpdb->prepare(
            "SELECT id, is_duplicate, duplicate_of_id FROM {$table_leads} WHERE id = %d",
            $lead_id
        ));
        
        if (!$lead_info) {
            return false;
        }
        
        $canonical_id = $lead_info->is_duplicate ? $lead_info->duplicate_of_id : $lead_info->id;
        
        // Fetch all related lead IDs (canonical + duplicates)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $lead_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table_leads} WHERE id = %d OR duplicate_of_id = %d",
            $canonical_id,
            $canonical_id
        ));
        
        if (empty($lead_ids)) {
            $lead_ids = array($lead_id);
        }
        
        $placeholders = implode(',', array_fill(0, count($lead_ids), '%d'));
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table_capi} WHERE lead_id IN ($placeholders) AND platform = %s AND event_name = %s AND status = 'success' LIMIT 1",
            array_merge($lead_ids, array($platform, $event_name))
        ));
        
        return !empty($exists);
    }

    /**
     * Check if a lead is eligible for Google Ads Offline Conversions / CAPI.
     */
    public static function is_google_conversion_eligible($lead) {
        $source = !empty($lead->source) ? trim(strtolower($lead->source)) : '';
        $utm_source = !empty($lead->utm_source) ? trim(strtolower($lead->utm_source)) : '';
        $utm_medium = !empty($lead->utm_medium) ? trim(strtolower($lead->utm_medium)) : '';
        $gclid = !empty($lead->gclid) ? trim($lead->gclid) : '';
        $gbraid = !empty($lead->gbraid) ? trim($lead->gbraid) : '';
        $wbraid = !empty($lead->wbraid) ? trim($lead->wbraid) : '';
        $fbclid = !empty($lead->fbclid) ? trim($lead->fbclid) : '';
        $fb_lead_id = !empty($lead->fb_lead_id) ? trim($lead->fb_lead_id) : '';
        $lead_quality = !empty($lead->lead_quality) ? trim(strtolower($lead->lead_quality)) : '';
        $stage = !empty($lead->stage) ? trim(strtolower($lead->stage)) : '';

        // Exclusions:
        // 1. Rep marks invalid/no requirement (lead quality is unqualified, or stage is lost / invalid / no requirement)
        if ($lead_quality === 'unqualified' || $stage === 'lost' || $stage === 'invalid' || $stage === 'no requirement') {
            return false;
        }

        // Check if there is google/paid ads evidence
        $has_google_evidence = (
            !empty($gclid) || 
            !empty($gbraid) || 
            !empty($wbraid) || 
            strpos($source, 'google') !== false || 
            strpos($source, 'paid search') !== false || 
            strpos($source, 'paid-search') !== false ||
            strpos($utm_source, 'google') !== false || 
            $utm_medium === 'cpc'
        );

        // 2. Exclude if source is Meta Ads and no Google evidence exists
        if ((strpos($source, 'meta') !== false || strpos($source, 'facebook') !== false || strpos($source, 'instagram') !== false) && !$has_google_evidence) {
            return false;
        }

        // 3. Exclude if fbclid/fb_lead_id is present and no Google evidence exists
        if ((!empty($fbclid) || !empty($fb_lead_id)) && !$has_google_evidence) {
            return false;
        }

        // 4. Exclude if utm_source is facebook/instagram/meta and no Google evidence exists
        if ((strpos($utm_source, 'facebook') !== false || strpos($utm_source, 'instagram') !== false || strpos($utm_source, 'meta') !== false) && !$has_google_evidence) {
            return false;
        }

        // Inclusions:
        // Upload to Google only if:
        // - source/channel is Google Ads or Paid Search
        // - OR gclid is present
        // - OR gbraid is present
        // - OR wbraid is present
        // - OR landing session has google/cpc UTMs
        // - OR original lead attribution is unknown but utm_source/utm_medium suggests Google
        $is_google_ads = (
            strpos($source, 'google') !== false || 
            strpos($source, 'paid search') !== false || 
            strpos($source, 'paid-search') !== false
        );

        $has_google_utms = (
            strpos($utm_source, 'google') !== false && 
            $utm_medium === 'cpc'
        );

        $is_unknown_but_google = (
            ($source === '' || $source === 'unknown' || $source === 'organic' || $source === 'direct') && 
            (strpos($utm_source, 'google') !== false || $utm_medium === 'cpc')
        );

        $eligible = (
            $is_google_ads || 
            !empty($gclid) || 
            !empty($gbraid) || 
            !empty($wbraid) || 
            $has_google_utms || 
            $is_unknown_but_google
        );

        return $eligible;
    }

    /**
     * Queue Conversion API event using Action Scheduler
     */
    public static function queue_capi_event($lead_id, $stage, $old_lead) {
        $delay = ($stage === 'New') ? 0 : 300;

        if ($delay === 0) {
            // Fire immediately and synchronously for lead capture ('New' stage) to ensure CAPI is sent instantly
            IDLM_DB::log_activity($lead_id, 'capi_sync', "Executing Conversions API synchronously for stage: {$stage}.", 0);
            self::process_capi_event($lead_id, $stage);
            return;
        }

        $schedule_time = time() + $delay;
        $delay_msg = " (delayed by 5 mins)";

        // Cancel any pending CAPI uploads for this lead to prevent duplicate or conflicting events from firing on rapid stage changes.
        $all_stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $all_stages = array_unique(array_merge(array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'), $all_stages));

        foreach ($all_stages as $stg) {
            if (function_exists('as_unschedule_all_actions')) {
                as_unschedule_all_actions('idlm_async_capi_upload_event', array(
                    'lead_id' => $lead_id,
                    'stage' => $stg
                ));
            }
            wp_clear_scheduled_hook('idlm_async_capi_upload_event', array(
                'lead_id' => $lead_id,
                'stage' => $stg
            ));
        }

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

        // A representative may identify a duplicate after an event was queued.
        // Suppress every future conversion for the excluded record at send time.
        if (!empty($lead->is_duplicate)) {
            IDLM_DB::log_activity($lead_id, 'capi_abort', 'Conversions API upload aborted: Lead is marked as a duplicate contact.', 0);
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
        $event_mappings = get_option('idlm_meta_event_mappings');
        if ($event_mappings === false) {
            $event_mappings = array(
                'New' => 'Lead',
                'Qualified' => 'Contact',
                'Quote Shared' => 'Subscribe',
                'Won' => 'Purchase'
            );
        }

        if (array_key_exists($stage, $event_mappings)) {
            $event_name = trim($event_mappings[$stage]);
        } else {
            // Key is missing entirely (legacy database)
            if ($stage === 'New') {
                $event_name = 'Lead';
            } else {
                return;
            }
        }

        if (empty($event_name)) {
            return;
        }

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
            $creation_time = !empty($lead->created_at) ? strtotime($lead->created_at) : time();
            $user_data['fbc'] = 'fb.1.' . $creation_time . '.' . $lead->fbclid;
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
            $custom_data['currency'] = get_option('idlm_currency_code', 'INR');
        }

        $payload = array(
            'data' => array(
                array(
                    'event_name' => $event_name,
                    'event_time' => time(),
                    'event_source_url' => home_url('/'),
                    'action_source' => 'system_generated',
                    'event_id' => 'idlm_lead_' . $lead->id . '_' . $event_name,
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

        if (!self::is_google_conversion_eligible($lead)) {
            return;
        }

        $measurement_id = get_option('idlm_google_measurement_id');
        $api_secret = get_option('idlm_google_api_secret');
        if (empty($measurement_id) || empty($api_secret)) {
            IDLM_DB::log_capi_event($lead->id, 'google', $stage, array(), 'Missing GA4 Measurement ID or API Secret in settings', 'failed');
            return;
        }

        // Map stages
        $event_mappings = get_option('idlm_google_event_mappings');
        if ($event_mappings === false) {
            $event_mappings = array(
                'Qualified' => 'CRM Qualified Lead',
                'Won' => 'Closed Won Interior Project'
            );
        }

        if (array_key_exists($stage, $event_mappings)) {
            $event_name = trim($event_mappings[$stage]);
        } else {
            return;
        }

        if (empty($event_name)) {
            return;
        }

        if (!$force && self::is_event_already_sent($lead->id, 'google', $event_name)) {
            return;
        }

        // Generate client id or use existing
        $client_id = !empty($lead->gclid) ? $lead->gclid : (!empty($lead->gbraid) ? $lead->gbraid : (!empty($lead->wbraid) ? $lead->wbraid : 'idlm.' . $lead->id));

        $event_params = array(
            'engagement_time_msec' => 100,
            'lead_id' => $lead->id,
            'source' => $lead->source,
            'gclid' => $lead->gclid,
            'gbraid' => isset($lead->gbraid) ? $lead->gbraid : '',
            'wbraid' => isset($lead->wbraid) ? $lead->wbraid : '',
            'stage' => $stage,
            'property_type' => $lead->property_type,
            'num_rooms' => $lead->num_rooms
        );

        if (!empty($lead->deal_value) && floatval($lead->deal_value) > 0) {
            $event_params['value'] = floatval($lead->deal_value);
            $event_params['currency'] = get_option('idlm_currency_code', 'INR');
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

        // Guard: LinkedIn CAPI requires the LI FAT ID (li_fat_id) to attribute conversions correctly
        if (empty($lead->li_fat_id)) {
            return;
        }

        $conversion_id = get_option('idlm_linkedin_conversion_id');
        $access_token = get_option('idlm_linkedin_access_token');
        if (empty($conversion_id) || empty($access_token)) {
            IDLM_DB::log_capi_event($lead->id, 'linkedin', $stage, array(), 'Missing LinkedIn Conversion ID or Access Token in settings', 'failed');
            return;
        }

        // Map stages
        $event_mappings = get_option('idlm_linkedin_event_mappings');
        if ($event_mappings === false) {
            $event_mappings = array(
                'Qualified' => 'CRM Qualified Lead',
                'Won' => 'Closed Won'
            );
        }

        if (array_key_exists($stage, $event_mappings)) {
            $event_name = trim($event_mappings[$stage]);
        } else {
            return;
        }

        if (empty($event_name)) {
            return;
        }

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
                'currencyCode' => get_option('idlm_currency_code', 'INR')
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
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_capi} WHERE id = %d", $log_id));
        if (!$log) {
            return array('success' => false, 'message' => 'Log not found');
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
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

    /**
     * Get Google Ads Conversion events for leads with click IDs that transitioned to qualified/closed stages
     */
    public static function get_google_ads_conversion_events() {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';

        // Get Google Event Mappings and retrieve all stages with non-empty mapped names
        $event_mappings = get_option('idlm_google_event_mappings', array(
            'Qualified' => 'CRM Qualified Lead',
            'Won' => 'Closed Won Interior Project'
        ));

        $mapped_stages = array();
        foreach ($event_mappings as $stg => $conv_name) {
            if (!empty(trim($conv_name))) {
                $mapped_stages[] = $stg;
            }
        }

        // If no stages are mapped, return early
        if (empty($mapped_stages)) {
            return array();
        }

        // 1. Fetch stage changes from activity logs for leads matching candidate filters
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $stage_logs = $wpdb->get_results(
            "SELECT a.lead_id, a.created_at, a.details, a.activity_type, l.gclid, l.gbraid, l.wbraid, l.deal_value, l.email, l.phone, l.source, l.utm_source, l.utm_medium, l.fbclid, l.fb_lead_id, l.lead_quality, l.stage
              FROM {$table_activity} a
              JOIN {$table_leads} l ON a.lead_id = l.id
              WHERE a.activity_type IN ('stage_change', 'lead_quality')
                AND l.is_duplicate = 0
                AND (
                    l.gclid != '' OR l.gbraid != '' OR l.wbraid != '' 
                    OR l.source LIKE '%google%' 
                    OR l.source LIKE '%paid%search%' 
                    OR l.utm_source LIKE '%google%' 
                    OR l.utm_medium = 'cpc'
                )
              ORDER BY a.created_at ASC"
        );

        $conversion_events = array();

        foreach ($stage_logs as $log) {
            if (!self::is_google_conversion_eligible($log)) {
                continue;
            }
            $stage = '';
            if ($log->activity_type === 'stage_change') {
                if (preg_match("/changed from '.*' to '(.*)'/i", $log->details, $matches)) {
                    $matched_stage = $matches[1];
                    foreach ($mapped_stages as $ms) {
                        if (strtolower($ms) === strtolower($matched_stage)) {
                            $stage = $ms;
                            break;
                        }
                    }
                }
            } elseif ($log->activity_type === 'lead_quality') {
                if (preg_match("/changed from '.*' to 'Qualified'/i", $log->details)) {
                    foreach ($mapped_stages as $ms) {
                        if (strtolower($ms) === 'qualified') {
                            $stage = $ms;
                            break;
                        }
                    }
                }
            }

            if ($stage) {
                $conversion_events[$log->lead_id . '_' . $stage] = array(
                    'lead_id' => $log->lead_id,
                    'gclid' => $log->gclid,
                    'gbraid' => $log->gbraid,
                    'wbraid' => $log->wbraid,
                    'stage' => $stage,
                    'time' => $log->created_at,
                    'value' => (strtolower($stage) === 'won' || strpos(strtolower($stage), 'won') !== false) ? floatval($log->deal_value) : 0,
                    'email' => $log->email,
                    'phone' => $log->phone
                );
            }
        }

        // 2. Scan leads table for current stage if not already captured in logs
        // This handles cases where leads were imported or logs were cleared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $current_leads = $wpdb->get_results(
            "SELECT id, stage, lead_quality, updated_at, created_at, conversion_time, qualified_at, gclid, gbraid, wbraid, deal_value, email, phone, source, utm_source, utm_medium, fbclid, fb_lead_id 
              FROM {$table_leads} 
              WHERE is_duplicate = 0 
                AND (
                    gclid != '' OR gbraid != '' OR wbraid != '' 
                    OR source LIKE '%google%' 
                    OR source LIKE '%paid%search%' 
                    OR utm_source LIKE '%google%' 
                    OR utm_medium = 'cpc'
                )"
        );

        foreach ($current_leads as $lead) {
            if (!self::is_google_conversion_eligible($lead)) {
                continue;
            }
            $stages_to_check = array();
            
            // Check if lead_quality is Qualified, and if 'Qualified' is mapped
            if (strtolower($lead->lead_quality) === 'qualified') {
                foreach ($mapped_stages as $ms) {
                    if (strtolower($ms) === 'qualified') {
                        $stages_to_check[] = $ms;
                        break;
                    }
                }
            }
            
            // Check if current stage is mapped
            if (!empty($lead->stage)) {
                foreach ($mapped_stages as $ms) {
                    if (strtolower($ms) === strtolower($lead->stage)) {
                        $stages_to_check[] = $ms;
                        break;
                    }
                }
            }

            // Remove duplicates
            $stages_to_check = array_unique($stages_to_check);

            foreach ($stages_to_check as $stg) {
                $key = $lead->id . '_' . $stg;
                if (!isset($conversion_events[$key])) {
                    $time = (strtolower($stg) === 'won' && !empty($lead->conversion_time) && $lead->conversion_time !== '0000-00-00 00:00:00')
                        ? $lead->conversion_time
                        : (($stg === $lead->stage) ? $lead->updated_at : (!empty($lead->qualified_at) ? $lead->qualified_at : $lead->created_at));
                    $conversion_events[$key] = array(
                        'lead_id' => $lead->id,
                        'gclid' => $lead->gclid,
                        'gbraid' => $lead->gbraid,
                        'wbraid' => $lead->wbraid,
                        'stage' => $stg,
                        'time' => $time,
                        'value' => (strtolower($stg) === 'won' || strpos(strtolower($stg), 'won') !== false) ? floatval($lead->deal_value) : 0,
                        'email' => $lead->email,
                        'phone' => $lead->phone
                    );
                }
            }
        }

        return $conversion_events;
    }

    /**
     * Generate Google Ads Offline Conversion CSV response
     */
    public static function generate_google_ads_conversions_csv_response($terminate = true) {
        $currency = get_option('idlm_currency_code', 'INR');

        if ($terminate) {
            self::clear_csv_output_buffers();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=google-ads-offline-conversions-' . gmdate('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ($output) {
            fputcsv($output, array('Conversion action', 'GCLID', 'Email Address', 'Phone number', 'Conversion date and time', 'Conversion value', 'Conversion currency', 'GBRAID', 'WBRAID'), ',', '"', '\\');

            $event_mappings = get_option('idlm_google_event_mappings', array(
                'Qualified' => 'CRM Qualified Lead',
                'Won' => 'Closed Won Interior Project'
            ));

            $events = self::get_google_ads_conversion_events();
            foreach ($events as $event) {
                $stage_key = $event['stage'];
                $conversion_name = '';

                // Match exactly or case-insensitively
                if (isset($event_mappings[$stage_key])) {
                    $conversion_name = trim($event_mappings[$stage_key]);
                } else {
                    foreach ($event_mappings as $k => $v) {
                        if (strtolower($k) === strtolower($stage_key)) {
                            $conversion_name = trim($v);
                            break;
                        }
                    }
                }

                if (empty($conversion_name)) {
                    continue;
                }

                $email = !empty($event['email']) ? strtolower(trim($event['email'])) : '';
                $phone = !empty($event['phone']) ? self::format_phone_e164($event['phone']) : '';
                $time_str = self::format_google_ads_conversion_time($event['time']);

                if (empty($time_str)) {
                    continue;
                }

                fputcsv($output, array(
                    $conversion_name,
                    $event['gclid'],
                    $email,
                    $phone,
                    $time_str,
                    $event['value'] > 0 ? number_format($event['value'], 2, '.', '') : '',
                    $currency,
                    $event['gbraid'],
                    $event['wbraid']
                ), ',', '"', '\\');
            }
            fclose($output); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        }
        if ($terminate && !defined('IDLM_TESTING')) {
            exit;
        }
    }

    /**
     * Remove buffered notices or whitespace before a direct CSV download.
     */
    private static function clear_csv_output_buffers() {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Format a WordPress-local database timestamp for Google Ads.
     *
     * Google requires a numeric UTC offset in yyyy-mm-dd HH:mm:ss+|-HH:mm form.
     */
    private static function format_google_ads_conversion_time($value) {
        $value = is_string($value) ? trim($value) : '';
        if (empty($value) || '0000-00-00 00:00:00' === $value) {
            return '';
        }

        try {
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $datetime = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
            $errors = DateTimeImmutable::getLastErrors();

            if (!$datetime || (is_array($errors) && (!empty($errors['warning_count']) || !empty($errors['error_count'])))) {
                return '';
            }

            return $datetime->format('Y-m-d H:i:sP');
        } catch (Exception $exception) {
            return '';
        }
    }

    /**
     * Format a phone number to E.164 standard.
     */
    public static function format_phone_e164($phone) {
        if (empty($phone)) {
            return '';
        }
        // Remove all characters except digits and '+'
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        if (empty($cleaned)) {
            return '';
        }

        // If it starts with '00', replace it with '+'
        if (strpos($cleaned, '00') === 0) {
            $cleaned = '+' . substr($cleaned, 2);
        }

        // If it is 10 digits (no country code), prepend India prefix '+91'
        if (strlen($cleaned) === 10 && $cleaned[0] !== '+') {
            $cleaned = '+91' . $cleaned;
        }

        // If it starts with '91' and is 12 digits, prepend '+'
        if (strlen($cleaned) === 12 && strpos($cleaned, '91') === 0) {
            $cleaned = '+' . $cleaned;
        }

        // If it doesn't start with '+', prepend it
        if ($cleaned[0] !== '+') {
            $cleaned = '+' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Normalize and hash first/last name for Google Customer Match.
     */
    public static function normalize_name_for_hash($name) {
        if (empty($name)) {
            return '';
        }
        // Remove common prefixes (case-insensitive)
        $prefixes = array(
            '/^(mr\.|mrs\.|ms\.|dr\.|prof\.|shri\.|smt\.)\s+/i',
            '/^(mr|mrs|ms|dr|prof|shri|smt)\s+/i'
        );
        $cleaned = preg_replace($prefixes, '', trim($name));

        // Convert to lowercase
        $cleaned = mb_strtolower($cleaned, 'UTF-8');

        // Keep lowercase alphabetical characters only (a-z)
        $cleaned = preg_replace('/[^a-z]/', '', $cleaned);

        if (empty($cleaned)) {
            return '';
        }

        return hash('sha256', $cleaned);
    }
}
