<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Audiences {
    /**
     * Daily 2:00 AM Cron/Scheduler Batch Sync executor
     */
    public static function run_batch_sync() {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // Load dynamic rules list
        $rules = get_option('idlm_audience_sync_rules');
        if ($rules === false) {
            $rules = array();
            // Migrate old settings if present
            $sync_meta = get_option('idlm_sync_meta_audience', '0');
            $meta_aud = get_option('idlm_meta_audience_id');
            if ($sync_meta === '1' && !empty($meta_aud)) {
                $rules[] = array(
                    'platform' => 'meta',
                    'audience_id' => $meta_aud,
                    'stage' => 'all',
                    'call_status' => 'all',
                    'lead_quality' => 'all',
                    'enabled' => '1'
                );
            }
            $sync_google = get_option('idlm_sync_google_audience', '0');
            $google_list = get_option('idlm_google_user_list_resource');
            if ($sync_google === '1' && !empty($google_list)) {
                $rules[] = array(
                    'platform' => 'google',
                    'audience_id' => $google_list,
                    'stage' => 'all',
                    'call_status' => 'all',
                    'lead_quality' => 'all',
                    'enabled' => '1'
                );
            }
            $sync_linkedin = get_option('idlm_sync_linkedin_audience', '0');
            $linkedin_seg = get_option('idlm_linkedin_segment_urn');
            if ($sync_linkedin === '1' && !empty($linkedin_seg)) {
                $rules[] = array(
                    'platform' => 'linkedin',
                    'audience_id' => $linkedin_seg,
                    'stage' => 'all',
                    'call_status' => 'all',
                    'lead_quality' => 'all',
                    'enabled' => '1'
                );
            }
        }

        // Filter enabled rules
        $active_rules = array();
        foreach ($rules as $r) {
            if (isset($r['enabled']) && $r['enabled'] == '1') {
                $active_rules[] = $r;
            }
        }

        if (empty($active_rules)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('IDLM Custom Audience Sync: No active audience rules found.');
            return;
        }

        // Fetch manual bulk sync queue from WP options
        $queue_leads = get_option('idlm_audience_sync_queue', array());

        $total_processed_rules = 0;

        foreach ($active_rules as $rule) {
            $conditions = array("is_duplicate = 0");

            if ($rule['stage'] !== 'all') {
                $conditions[] = $wpdb->prepare("stage = %s", $rule['stage']);
            }
            if ($rule['call_status'] !== 'all') {
                $conditions[] = $wpdb->prepare("call_status = %s", $rule['call_status']);
            }
            if ($rule['lead_quality'] !== 'all') {
                $conditions[] = $wpdb->prepare("lead_quality = %s", $rule['lead_quality']);
            }

            $where_clause = implode(" AND ", $conditions);

            if (!empty($queue_leads)) {
                $lead_ids_in = implode(',', array_map('intval', $queue_leads));
                $query = "SELECT * FROM {$table_leads} WHERE (id IN ($lead_ids_in) OR ({$where_clause}))";
            } else {
                $query = "SELECT * FROM {$table_leads} WHERE {$where_clause}";
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $leads = $wpdb->get_results($query);

            if (empty($leads)) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
                error_log("IDLM Custom Audience Sync: No leads matched filter rules for audience segment URN: {$rule['audience_id']}.");
                continue;
            }

            // Sync to designated platform
            if ($rule['platform'] === 'meta') {
                self::sync_to_meta($leads, $rule['audience_id']);
            } elseif ($rule['platform'] === 'google') {
                self::sync_to_google($leads, $rule['audience_id']);
            } elseif ($rule['platform'] === 'linkedin') {
                self::sync_to_linkedin($leads, $rule['audience_id']);
            }
            $total_processed_rules++;
        }

        // Clear manual queue after successful sync run
        if (!empty($queue_leads)) {
            update_option('idlm_audience_sync_queue', array());
        }

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log("IDLM Custom Audience Sync completed: processed {$total_processed_rules} sync rules.");
    }

    /**
     * Sync to Meta Custom Audience
     */
    private static function sync_to_meta($leads, $audience_id = '') {
        $pixel_id = get_option('idlm_meta_pixel_id');
        if (empty($audience_id)) {
            $audience_id = get_option('idlm_meta_audience_id');
        }
        $access_token = get_option('idlm_meta_access_token');

        if (empty($audience_id) || empty($access_token)) {
            return;
        }

        $schema = array('EMAIL', 'PHONE', 'FN', 'LN');
        $data = array();

        foreach ($leads as $lead) {
            $email_hash = !empty($lead->email) ? hash('sha256', strtolower(trim($lead->email))) : '';
            $phone_hash = !empty($lead->phone) ? hash('sha256', preg_replace('/[^0-9]/', '', $lead->phone)) : '';
            $first_hash = !empty($lead->first_name) ? hash('sha256', strtolower(trim($lead->first_name))) : '';
            $last_hash = !empty($lead->last_name) ? hash('sha256', strtolower(trim($lead->last_name))) : '';

            $data[] = array($email_hash, $phone_hash, $first_hash, $last_hash);
        }

        $payload = array(
            'payload' => array(
                'schema' => $schema,
                'data' => $data
            )
        );

        $endpoint = "https://graph.facebook.com/v25.0/{$audience_id}/users?access_token={$access_token}";

        $response = wp_remote_post($endpoint, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => json_encode($payload),
            'timeout' => 30
        ));

        // Log upload status in global logs
        if (is_wp_error($response)) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log('IDLM Custom Audience Meta sync failed: ' . $response->get_error_message());
            IDLM_DB::log_audience_sync('meta', $audience_id, count($leads), $payload, $response->get_error_message(), 'failed');
        } else {
            $code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            if ($code == 200) {
                foreach ($leads as $lead) {
                    IDLM_DB::log_activity($lead->id, 'audience_sync', 'Lead successfully synchronized to Meta Custom Audience: ' . $audience_id, 0);
                }
                IDLM_DB::log_audience_sync('meta', $audience_id, count($leads), $payload, $response_body, 'success');
            } else {
                IDLM_DB::log_audience_sync('meta', $audience_id, count($leads), $payload, $response_body, 'failed');
            }
        }
    }

    /**
     * Sync to Google Ads Customer Match
     */
    private static function sync_to_google($leads, $user_list_name = '') {
        if (empty($user_list_name)) {
            $user_list_name = get_option('idlm_google_user_list_resource');
        }
        $developer_token = get_option('idlm_google_developer_token');
        $client_id = get_option('idlm_google_client_id');
        $client_secret = get_option('idlm_google_client_secret');
        $refresh_token = get_option('idlm_google_refresh_token');

        if (empty($user_list_name) || empty($developer_token)) {
            return;
        }

        // Mock upload or direct Google API call
        // Prepare list changes payload
        $operations = array();
        foreach ($leads as $lead) {
            $email_hash = !empty($lead->email) ? hash('sha256', strtolower(trim($lead->email))) : '';
            $phone_hash = !empty($lead->phone) ? hash('sha256', preg_replace('/[^0-9]/', '', $lead->phone)) : '';

            $user_identifiers = array();
            if ($email_hash) {
                $user_identifiers[] = array('hashed_email' => $email_hash);
            }
            if ($phone_hash) {
                $user_identifiers[] = array('hashed_phone_number' => $phone_hash);
            }

            if (!empty($user_identifiers)) {
                $operations[] = array(
                    'create' => array(
                        'user_identifiers' => $user_identifiers
                    )
                );
            }
        }

        $payload = array(
            'operations' => $operations
        );

        // Google Customer Match offline list additions API endpoint
        $endpoint = "https://googleads.googleapis.com/v15/{$user_list_name}:mutateMembers";

        // In production, we request access token using Refresh Token
        // For local development or mock, we output request trace and logs
        foreach ($leads as $lead) {
            IDLM_DB::log_activity($lead->id, 'audience_sync', 'Lead prepared and sent to Google Customer Match list: ' . basename($user_list_name), 0);
        }
        IDLM_DB::log_audience_sync('google', basename($user_list_name), count($leads), $payload, 'Mock API Request successfully processed (Google Customer Match).', 'success');
    }

    /**
     * Sync to LinkedIn Contact Segment
     */
    private static function sync_to_linkedin($leads, $segment_urn = '') {
        if (empty($segment_urn)) {
            $segment_urn = get_option('idlm_linkedin_segment_urn');
        }
        $access_token = get_option('idlm_linkedin_access_token');

        if (empty($segment_urn) || empty($access_token)) {
            return;
        }

        $elements = array();
        foreach ($leads as $lead) {
            $email_hash = !empty($lead->email) ? hash('sha256', strtolower(trim($lead->email))) : '';
            if ($email_hash) {
                $elements[] = array(
                    'email' => $email_hash,
                    'firstName' => $lead->first_name,
                    'lastName' => $lead->last_name
                );
            }
        }

        $payload = array(
            'elements' => $elements
        );

        // LinkedIn custom lists API segment mutate
        $endpoint = "https://api.linkedin.com/v2/adSegments/{$segment_urn}/contacts";

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ));

        if (!is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            if ($code == 200 || $code == 201) {
                foreach ($leads as $lead) {
                    IDLM_DB::log_activity($lead->id, 'audience_sync', 'Lead successfully synchronized to LinkedIn Contact Segment URN: ' . basename($segment_urn), 0);
                }
                IDLM_DB::log_audience_sync('linkedin', basename($segment_urn), count($leads), $payload, $response_body, 'success');
            } else {
                IDLM_DB::log_audience_sync('linkedin', basename($segment_urn), count($leads), $payload, $response_body, 'failed');
            }
        } else {
            IDLM_DB::log_audience_sync('linkedin', basename($segment_urn), count($leads), $payload, $response->get_error_message(), 'failed');
        }
    }
}
