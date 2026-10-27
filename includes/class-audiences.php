<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Audiences {
    /**
     * Resolve enabled rules in saved order for scheduled, manual and pull consumers.
     * Explicit empty/invalid saved rules never revive legacy settings.
     */
    public static function get_active_rules() {
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

        $active_rules = array();
        foreach (is_array($rules) ? $rules : array() as $rule) {
            if (!is_array($rule) || !isset($rule['enabled']) || !is_scalar($rule['enabled']) || (string) $rule['enabled'] !== '1'
                || !isset($rule['platform'], $rule['audience_id']) || !is_string($rule['platform'])
                || !in_array($rule['platform'], array('meta', 'google', 'linkedin'), true)
                || !is_scalar($rule['audience_id']) || trim((string) $rule['audience_id']) === '') {
                continue;
            }
            foreach (array('stage', 'call_status', 'lead_quality') as $field) {
                if (isset($rule[$field]) && !is_scalar($rule[$field])) {
                    continue 2;
                }
                $rule[$field] = empty($rule[$field]) ? 'all' : (string) $rule[$field];
            }
            $active_rules[] = $rule;
        }
        return $active_rules;
    }

    /** First enabled Google match wins; disabled duplicates cannot shadow it. */
    public static function resolve_google_feed_rule($list_id) {
        foreach (self::get_active_rules() as $rule) {
            if ($rule['platform'] !== 'google') {
                continue;
            }
            $target_id = ltrim(trim((string) $rule['audience_id']), '/');
            $target_id = preg_replace('/\/userlists\//i', '/userLists/', $target_id);
            if ($target_id === $list_id || basename($target_id) === $list_id || $rule['audience_id'] === $list_id) {
                return $rule;
            }
        }
        return null;
    }

    /**
     * Daily 2:00 AM Cron/Scheduler Batch Sync executor
     */
    public static function run_batch_sync($manual_only = false) {
        return IDLM_Audience_Dispatch::run((bool) $manual_only);
    }

    public static function has_credentials($rule) {
        if ($rule['platform'] === 'google') { return true; } // Pull mapping, not an upload.
        return (bool) IDLM_Secret_Store::get('idlm_' . $rule['platform'] . '_access_token', '', true);
    }

    /** The same nonduplicate and current rule predicate serves scheduled and pull reads. */
    public static function rule_conditions($rule) {
        global $wpdb;
        $conditions = array('is_duplicate = 0');
        foreach (array('stage', 'call_status', 'lead_quality') as $field) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0384: rule_conditions(): rule_conditions iterates only stage/call_status/lead_quality identifiers and prepares each value; no arbitrary field or SQL fragment is accepted.
            if (!empty($rule[$field]) && $rule[$field] !== 'all') { $conditions[] = $wpdb->prepare("{$field} = %s", $rule[$field]); }
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }
        return implode(' AND ', $conditions);
    }

    /** Return an explicit outcome; the dispatcher owns the log/state transaction. */
    public static function send_page($leads, $rule) {
        if ($rule['platform'] === 'meta') { return self::sync_to_meta($leads, $rule['audience_id'], !empty($rule['is_value']) && (string) $rule['is_value'] === '1'); }
        if ($rule['platform'] === 'linkedin') { return self::sync_to_linkedin($leads, $rule['audience_id']); }
        return array('status'=>'sent', 'payload'=>array('mode'=>'pull'), 'response'=>'Pull feed mapped; provider consumption is not confirmed.', 'retry_after'=>0);
    }

    private static function outcome($response, $payload, $success_codes) {
        if (is_wp_error($response)) { return array('status'=>'uncertain','payload'=>$payload,'response'=>$response->get_error_message(),'retry_after'=>0); }
        $code=(int) wp_remote_retrieve_response_code($response); $body=wp_remote_retrieve_body($response);
        $decoded=json_decode($body,true); $valid=is_array($decoded) || trim($body)==='';
        $partial=!empty($decoded['num_invalid_entries']);
        foreach (is_array($decoded['elements'] ?? null) ? $decoded['elements'] : array() as $element) {
            if (is_array($element) && (!empty($element['error']) || isset($element['status']) && (int)$element['status']>=400)) { $partial=true; }
        }
        if (in_array($code,$success_codes,true) && $partial) { $status='uncertain'; }
        elseif (in_array($code,$success_codes,true) && $valid && empty($decoded['error'])) { $status='sent'; }
        elseif ($code===429 || $code>=500 && $code<=599 || !empty($decoded['error']['is_transient'])) { $status='failed'; }
        elseif ($code>=400 && $code<=499 || !empty($decoded['error'])) { $status='blocked'; }
        else { $status='uncertain'; }
        $retry=wp_remote_retrieve_header($response,'retry-after');
        $delay=is_scalar($retry) && ctype_digit((string)$retry) ? (int)$retry : (is_string($retry) ? max(0,(int)strtotime($retry)-time()) : 0);
        return array('status'=>$status,'payload'=>$payload,'response'=>$body,'retry_after'=>min(3600,max(0,$delay)));
    }

    /**
     * Sync to Meta Custom Audience
     */
    private static function sync_to_meta($leads, $audience_id = '', $is_value_based = false) {
        $pixel_id = get_option('idlm_meta_pixel_id');
        if (empty($audience_id)) {
            $audience_id = get_option('idlm_meta_audience_id');
        }
        $access_token = IDLM_Secret_Store::get('idlm_meta_access_token', '', true);

        if (empty($audience_id) || empty($access_token)) {
            return array('status'=>'blocked','payload'=>array(),'response'=>'Credentials missing.','retry_after'=>0);
        }

        $schema = array('EMAIL', 'PHONE', 'FN', 'LN');
        if ($is_value_based) {
            $schema[] = 'LOOKALIKE_VALUE';
        }
        $data = array();

        foreach ($leads as $lead) {
            $email_hash = !empty($lead->email) ? hash('sha256', strtolower(trim($lead->email))) : '';
            $phone_hash = !empty($lead->phone) ? hash('sha256', preg_replace('/[^0-9]/', '', $lead->phone)) : '';
            $first_hash = !empty($lead->first_name) ? hash('sha256', strtolower(trim($lead->first_name))) : '';
            $last_hash = !empty($lead->last_name) ? hash('sha256', strtolower(trim($lead->last_name))) : '';

            $row_data = array($email_hash, $phone_hash, $first_hash, $last_hash);
            if ($is_value_based) {
                // Determine transaction/deal value from lead record, fallback to 100.0 if not set or zero
                $val = (!empty($lead->deal_value) && floatval($lead->deal_value) > 0) ? floatval($lead->deal_value) : 100.0;
                $row_data[] = $val;
            }
            $data[] = $row_data;
        }

        $payload = array(
            'payload' => array(
                'schema' => $schema,
                'data' => $data
            )
        );

        $endpoint = "https://graph.facebook.com/v25.0/" . rawurlencode($audience_id) . "/users";

        if (!IDLM_Recovery_Guard::lease_valid()) { return array('status'=>'uncertain','payload'=>array(),'response'=>'Worker fence lost.','retry_after'=>0); }
        $response = IDLM_Secret_Store::request($endpoint, array(
            'headers' => array('Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $access_token),
            'body'    => json_encode($payload),
            'timeout' => 30
        ), array($access_token));

        return self::outcome($response, $payload, array(200));
    }

    /**
     * Sync to Google Ads Customer Match
     */
    /**
     * Get active leads matching specific audience rule filters
     */
    public static function get_leads_matching_rule($rule) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $where_clause = self::rule_conditions($rule);
        $query = "SELECT * FROM {$table_leads} WHERE {$where_clause}";

        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- REL007-SQL-0385: get_leads_matching_rule(): rule_conditions iterates only stage/call_status/lead_quality identifiers and prepares each value; no arbitrary field or SQL fragment is accepted.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results($query);
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
    }

    /**
     * Generate a CSV output containing the mapped leads for Google Ads Data Manager HTTPS Audience Pull source
     */
    public static function generate_google_ads_audience_csv_response($leads, $filename = 'google-ads-audience', $terminate = true) {
        if ($terminate) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($filename) . '-' . gmdate('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ($output) {
            fputcsv($output, array('Email', 'Phone Number', 'Country Code', 'Zip', 'Hashed first name', 'Hashed last name'), ',', '"', '\\');

            foreach ($leads as $lead) {
                $email = !empty($lead->email) ? strtolower(trim($lead->email)) : '';
                $phone = !empty($lead->phone) ? IDLM_CAPI::format_phone_e164($lead->phone) : '';
                $first_hash = !empty($lead->first_name) ? IDLM_CAPI::normalize_name_for_hash($lead->first_name) : '';
                $last_hash = !empty($lead->last_name) ? IDLM_CAPI::normalize_name_for_hash($lead->last_name) : '';

                fputcsv($output, array(
                    $email,
                    $phone,
                    'IN',
                    '',
                    $first_hash,
                    $last_hash
                ), ',', '"', '\\');
            }
            fclose($output); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        }
        if ($terminate && !defined('IDLM_TESTING')) {
            exit;
        }
    }

    /**
     * Sync to LinkedIn Contact Segment
     */
    private static function sync_to_linkedin($leads, $segment_urn = '') {
        if (empty($segment_urn)) {
            $segment_urn = get_option('idlm_linkedin_segment_urn');
        }
        $access_token = IDLM_Secret_Store::get('idlm_linkedin_access_token', '', true);

        if (empty($segment_urn) || empty($access_token)) {
            return array('status'=>'blocked','payload'=>array(),'response'=>'Credentials missing.','retry_after'=>0);
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

        if (!IDLM_Recovery_Guard::lease_valid()) { return array('status'=>'uncertain','payload'=>array(),'response'=>'Worker fence lost.','retry_after'=>0); }
        $response = IDLM_Secret_Store::request($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($payload),
            'timeout' => 30
        ), array($access_token));

        return self::outcome($response, $payload, array(200, 201));
    }
}
