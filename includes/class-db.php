<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_DB {
    public static function init() {
        // Run DB update check to automate database schema migrations
        $saved_db_version = get_option('idlm_db_version', '');
        if ($saved_db_version !== IDLM_VERSION) {
            self::create_tables();
            update_option('idlm_db_version', IDLM_VERSION);
        }
    }

    /**
     * Create/Update custom DB tables on plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // 1. Leads Table
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $sql_leads = "CREATE TABLE $table_leads (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            first_name varchar(100) DEFAULT '',
            last_name varchar(100) DEFAULT '',
            email varchar(100) DEFAULT '',
            phone varchar(50) DEFAULT '',
            budget varchar(100) DEFAULT '',
            deal_value decimal(12,2) DEFAULT 0.00,
            project_timeline varchar(100) DEFAULT '',
            property_location varchar(255) DEFAULT '',
            source varchar(50) DEFAULT '',
            referrer_details text DEFAULT NULL,
            property_type varchar(50) DEFAULT '',
            num_rooms varchar(50) DEFAULT '',
            floor_plan_received tinyint(1) DEFAULT 0,
            floor_plan_file varchar(255) DEFAULT '',
            quote_shared tinyint(1) DEFAULT 0,
            quote_file varchar(255) DEFAULT '',
            preferred_studio varchar(100) DEFAULT '',
            stage varchar(50) DEFAULT '',
            call_status varchar(50) DEFAULT '',
            callback_datetime datetime DEFAULT NULL,
            lead_quality varchar(50) DEFAULT '',
            assigned_rep_id bigint(20) unsigned DEFAULT 0,
            utm_source varchar(100) DEFAULT '',
            utm_medium varchar(100) DEFAULT '',
            utm_campaign varchar(100) DEFAULT '',
            utm_adgroup varchar(100) DEFAULT '',
            utm_term varchar(100) DEFAULT '',
            utm_content varchar(100) DEFAULT '',
            gclid varchar(255) DEFAULT '',
            fbclid varchar(255) DEFAULT '',
            li_fat_id varchar(255) DEFAULT '',
            fb_lead_id varchar(100) DEFAULT NULL,
            is_duplicate tinyint(1) DEFAULT 0,
            duplicate_of_id bigint(20) unsigned DEFAULT 0,
            created_at datetime DEFAULT NULL,
            updated_at datetime DEFAULT NULL,
            time_to_connected bigint(20) DEFAULT NULL,
            time_to_won bigint(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY email (email),
            KEY phone (phone),
            KEY source (source),
            KEY stage (stage),
            KEY call_status (call_status),
            KEY assigned_rep_id (assigned_rep_id),
            KEY created_at (created_at),
            KEY lead_quality (lead_quality),
            KEY duplicate_of_id (duplicate_of_id)
        ) $charset_collate;";

        dbDelta($sql_leads);

        // 2. Activity Log Table
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';
        $sql_activity = "CREATE TABLE $table_activity (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            activity_type varchar(50) NOT NULL,
            details text NOT NULL,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY lead_id (lead_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_activity);

        // 3. Conversion API Log Table
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';
        $sql_capi = "CREATE TABLE $table_capi (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            platform varchar(50) NOT NULL,
            event_name varchar(100) NOT NULL,
            payload text NOT NULL,
            response text NOT NULL,
            status varchar(50) NOT NULL,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY lead_id (lead_id),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_capi);

        // 4. Cost Per Lead Table
        $table_cost = $wpdb->prefix . 'idlm_cost_per_lead';
        $sql_cost = "CREATE TABLE $table_cost (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            channel varchar(50) NOT NULL,
            month_year varchar(7) NOT NULL,
            cost decimal(10,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY  (id),
            UNIQUE KEY channel_month (channel, month_year)
        ) $charset_collate;";

        dbDelta($sql_cost);

        // 5. Audience Sync Log Table
        $table_audience_logs = $wpdb->prefix . 'idlm_audience_sync_logs';
        $sql_audience = "CREATE TABLE $table_audience_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            platform varchar(50) NOT NULL,
            audience_id varchar(255) NOT NULL,
            lead_count int(11) NOT NULL DEFAULT 0,
            payload longtext NOT NULL,
            response longtext NOT NULL,
            status varchar(50) NOT NULL,
            created_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at)
        ) $charset_collate;";

        dbDelta($sql_audience);
    }

    /**
     * Add lead and run duplicate detection
     */
    public static function insert_lead($data) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        // Set default call status if missing
        if (empty($data['call_status'])) {
            $data['call_status'] = 'Not Called';
        }

        // Set default stage if missing (defaults to New)
        if (empty($data['stage'])) {
            $data['stage'] = 'New';
        }

        // Duplicate lead detection using email or phone number
        $is_duplicate = 0;
        $duplicate_of_id = 0;

        if (!empty($data['email']) || !empty($data['phone'])) {
            $conditions = array();
            $params = array();

            if (!empty($data['email'])) {
                $conditions[] = "email = %s";
                $params[] = sanitize_email($data['email']);
            }

            if (!empty($data['phone'])) {
                $conditions[] = "phone = %s";
                $params[] = sanitize_text_field($data['phone']);

                $incoming_digits = preg_replace('/[^0-9]/', '', $data['phone']);
                if (strlen($incoming_digits) >= 10) {
                    $last_10 = substr($incoming_digits, -10);
                    // Match any phone ending with these 10 digits
                    $conditions[] = "phone LIKE %s";
                    $params[] = '%' . $wpdb->esc_like($last_10);
                }
            }

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $duplicate_query = "SELECT id, email, phone FROM $table_leads WHERE is_duplicate = 0 AND (" . implode(" OR ", $conditions) . ")";
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $candidates = $wpdb->get_results($wpdb->prepare($duplicate_query, $params));

            if (!empty($candidates)) {
                foreach ($candidates as $cand) {
                    // 1. If email matches exactly
                    if (!empty($data['email']) && !empty($cand->email) && strtolower(trim($data['email'])) === strtolower(trim($cand->email))) {
                        $is_duplicate = 1;
                        $duplicate_of_id = $cand->id;
                        break;
                    }

                    // 2. If phone matches with smart phone digits matching (ignoring format and international code)
                    if (!empty($data['phone']) && !empty($cand->phone)) {
                        $incoming_clean = preg_replace('/[^0-9]/', '', $data['phone']);
                        $cand_clean = preg_replace('/[^0-9]/', '', $cand->phone);

                        if ($incoming_clean === $cand_clean) {
                            $is_duplicate = 1;
                            $duplicate_of_id = $cand->id;
                            break;
                        }

                        if (strlen($incoming_clean) >= 10 && strlen($cand_clean) >= 10) {
                            if (substr($incoming_clean, -10) === substr($cand_clean, -10)) {
                                $is_duplicate = 1;
                                $duplicate_of_id = $cand->id;
                                break;
                            }
                        }
                    }
                }
            }
        }

        $data['is_duplicate'] = $is_duplicate;
        $data['duplicate_of_id'] = $duplicate_of_id;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $inserted = $wpdb->insert($table_leads, $data);
        if ($inserted) {
            $lead_id = $wpdb->insert_id;

            // Log activity
            $log_msg = "Lead created.";
            if ($is_duplicate) {
                $log_msg .= " Marked as duplicate of Lead ID: " . $duplicate_of_id;
            }
            self::log_activity($lead_id, 'creation', $log_msg);

            // Trigger CAPI for initial lead event only if it's NOT a duplicate (implemented by Shashi)
            if (!$is_duplicate) {
                do_action('idlm_lead_stage_converted', $lead_id, 'New', (object) array('stage' => ''));
            }

            return $lead_id;
        }

        return false;
    }

    /**
     * Update an existing lead, tracking specific status changes
     */
    public static function update_lead($lead_id, $data) {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // Fetch old data to detect changes
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $old_lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_leads WHERE id = %d", $lead_id));
        if (!$old_lead) {
            return false;
        }

        $data['updated_at'] = current_time('mysql');

        // Check if Call Status is transition to Connected
        if (isset($data['call_status']) && $data['call_status'] !== $old_lead->call_status) {
            $connected_statuses = array('Connected - Interested', 'Connected - Not Interested');
            if (in_array($data['call_status'], $connected_statuses) && empty($old_lead->time_to_connected)) {
                $created_time = strtotime($old_lead->created_at);
                $now_time = current_time('timestamp');
                $data['time_to_connected'] = $now_time - $created_time;
            }
        }

        // Check if Stage transitions to Won
        if (isset($data['stage']) && $data['stage'] !== $old_lead->stage) {
            if ($data['stage'] === 'Won' && empty($old_lead->time_to_won) && !empty($old_lead->time_to_connected)) {
                // Find when call status was set to Connected
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $activity_connected = $wpdb->get_var($wpdb->prepare(
                    "SELECT created_at FROM {$wpdb->prefix}idlm_lead_activity_log 
                     WHERE lead_id = %d AND activity_type = 'call_status_change' 
                     AND details LIKE %s ORDER BY id ASC LIMIT 1", 
                    $lead_id,
                    '%' . $wpdb->esc_like('Connected') . '%'
                ));
                $connected_time = $activity_connected ? strtotime($activity_connected) : strtotime($old_lead->created_at);
                $now_time = current_time('timestamp');
                $data['time_to_won'] = $now_time - $connected_time;
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update($table_leads, $data, array('id' => $lead_id));

        if ($updated !== false) {
            $user_id = get_current_user_id();

            // Log details of changes
            foreach ($data as $key => $value) {
                if ($key === 'updated_at' || $key === 'time_to_connected' || $key === 'time_to_won') {
                    continue;
                }

                $old_val = isset($old_lead->$key) ? $old_lead->$key : '';
                
                $is_changed = false;
                if ($key === 'callback_datetime') {
                    $old_ts = (!empty($old_val) && $old_val !== '0000-00-00 00:00:00') ? strtotime($old_val) : false;
                    $new_ts = (!empty($value) && $value !== '0000-00-00 00:00:00') ? strtotime($value) : false;
                    $is_changed = ($old_ts !== $new_ts);
                } else {
                    $is_changed = ($old_val != $value);
                }

                if ($is_changed) {
                    $type = 'update';
                    $msg = "Field '$key' changed from '$old_val' to '$value'.";

                    if ($key === 'assigned_rep_id') {
                        $type = 'assignment';
                        $old_rep_obj = $old_val ? get_userdata($old_val) : false;
                        $old_rep = $old_rep_obj ? $old_rep_obj->display_name : 'Unassigned';
                        $new_rep_obj = $value ? get_userdata($value) : false;
                        $new_rep = $new_rep_obj ? $new_rep_obj->display_name : 'Unassigned';
                        $msg = "Assigned representative changed from $old_rep to $new_rep.";
                    } elseif ($key === 'call_status') {
                        $type = 'call_status_change';
                    } elseif ($key === 'stage') {
                        $type = 'stage_change';
                    } elseif ($key === 'floor_plan_file') {
                        $msg = "Floor Plan file updated.";
                    } elseif ($key === 'quote_file') {
                        $msg = "Quote file updated.";
                    } elseif ($key === 'callback_datetime') {
                        $old_formatted = $old_ts ? wp_date('Y-m-d h:i A', $old_ts) : 'None';
                        $new_formatted = $new_ts ? wp_date('Y-m-d h:i A', $new_ts) : 'None';
                        $msg = "Callback Date/Time changed from $old_formatted to $new_formatted.";
                    }

                    self::log_activity($lead_id, $type, $msg, $user_id);

                    // Trigger server-side Conversion API events if stage, status, or lead quality matches
                    if ($key === 'stage' && in_array($value, array('Qualified', 'Quote Shared', 'Won'))) {
                        do_action('idlm_lead_stage_converted', $lead_id, $value, $old_lead);
                    } elseif ($key === 'lead_quality' && $value === 'Qualified') {
                        do_action('idlm_lead_stage_converted', $lead_id, 'Qualified', $old_lead);
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * Log a lead activity
     */
    public static function log_activity($lead_id, $activity_type, $details, $user_id = 0) {
        global $wpdb;
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';

        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert($table_activity, array(
            'lead_id' => $lead_id,
            'user_id' => $user_id,
            'activity_type' => $activity_type,
            'details' => $details,
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * Log a Conversion API event
     */
    public static function log_capi_event($lead_id, $platform, $event_name, $payload, $response, $status) {
        global $wpdb;
        $table_capi = $wpdb->prefix . 'idlm_capi_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert($table_capi, array(
            'lead_id' => $lead_id,
            'platform' => $platform,
            'event_name' => $event_name,
            'payload' => maybe_serialize($payload),
            'response' => maybe_serialize($response),
            'status' => $status,
            'created_at' => current_time('mysql')
        ));
    }

    /**
     * Log an Audience Sync event
     */
    public static function log_audience_sync($platform, $audience_id, $lead_count, $payload, $response, $status) {
        global $wpdb;
        $table_audience_logs = $wpdb->prefix . 'idlm_audience_sync_logs';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->insert($table_audience_logs, array(
            'platform' => $platform,
            'audience_id' => $audience_id,
            'lead_count' => $lead_count,
            'payload' => maybe_serialize($payload),
            'response' => maybe_serialize($response),
            'status' => $status,
            'created_at' => current_time('mysql')
        ));
    }
}
