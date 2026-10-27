<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Builds the settings presentation context and delegates markup to guarded views.
 *
 * Builder: Shashi
 */
class IDLM_Admin_Settings_Renderer {
    public static function render() {
        if (!current_user_can('manage_options')) {
            wp_die('Access Denied');
        }

        if (function_exists('wp_enqueue_media')) {
            wp_enqueue_media();
        }
        $settings_saved = IDLM_Admin_Settings_Controller::process_request();
        $context = self::build_context();
        $context['settings_saved'] = $settings_saved;
        IDLM_View::display('admin/settings/shell.php', $context);
    }

    /**
     * Return an established credential value only inside the authorized settings view.
     *
     * Credential values deliberately remain outside the shared template context.
     */
    public static function credential_value($credential) {
        if (!current_user_can('manage_options')) {
            return '';
        }

        if (IDLM_Secret_Store::is_platform_secret('idlm_' . $credential)) { return ''; }

        switch ($credential) {
            case 'google_gadm_password':
                $password = IDLM_Secret_Store::get('idlm_google_gadm_password');
                if (empty($password)) {
                    $password = wp_generate_password(24, false);
                    IDLM_Secret_Store::set('idlm_google_gadm_password', $password);
                }
                return $password;
            case 'webhook_api_key':
                return IDLM_Secret_Store::get('idlm_webhook_api_key', '');
            case 'mcp_api_key':
                return IDLM_Secret_Store::get('idlm_mcp_api_key') ?: 'YOUR_API_KEY';
            default:
                return '';
        }
    }

    private static function build_context() {
        global $wpdb;

        // Fetch values
        $lead_target = get_option('idlm_monthly_lead_target', 100);
        $conv_target = get_option('idlm_monthly_conversion_target', 10);
        $currency_code = get_option('idlm_currency_code', 'INR');
        $reps_see_all = IDLM_Settings::get('idlm_reps_see_all_leads', '0');
        $assignment_mode = get_option('idlm_assignment_mode', 'manual');
        $active_reps = get_option('idlm_active_assignment_reps', array());
        $source_maps = get_option('idlm_source_assignment_mappings', array());
        $rep_studios = get_option('idlm_rep_studio_assignments', array());
        $fallback_enabled = get_option('idlm_fallback_assignment_enabled', '0');
        $fallback_hours = get_option('idlm_fallback_assignment_hours', '24');
        $qualified_fallback_enabled = get_option('idlm_qualified_fallback_enabled', '0');
        $qualified_fallback_hours = get_option('idlm_qualified_fallback_hours', '4');
        $qualified_fallback_end_at = get_option('idlm_qualified_fallback_end_at', '');
        $qualified_fallback_include_existing = get_option('idlm_qualified_fallback_include_existing', '0');
        $studio_mismatch_behavior = get_option('idlm_studio_mismatch_behavior', 'general_pool');
        $handoff_task_policy = get_option('idlm_handoff_task_policy', 'transfer');
        $handoff_notifications_enabled = get_option('idlm_handoff_notifications_enabled', '0');
        $active_pre_sales_reps = get_option('idlm_active_pre_sales_reps', null);
        $fallback_last_completed = get_option('idlm_fallback_last_completed_at', 'Never');
        $fallback_last_status = get_option('idlm_fallback_last_status', 'not_run');
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0443: build_context(): manage_options renderer, fixed prefixed lead/telemetry tables and constant count predicates; current operational context is intentionally live.
        $routing_exception_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE handoff_status = 'exception' AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"));
        $unassigned_intake_count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE assigned_rep_id = 0 AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $working_hours_start = get_option('idlm_working_hours_start', '09:00');
        $working_hours_end = get_option('idlm_working_hours_end', '18:00');
        $working_days = get_option('idlm_working_days', array(1, 2, 3, 4, 5, 6));
        $presales_targets = IDLM_DB::get_presales_outreach_targets();

        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $kanban_stage_totals = IDLM_Admin::get_kanban_stage_total_mappings($stages);
        $call_statuses = get_option('idlm_custom_call_statuses', array('Not Called', 'No Answer', 'Call Back Requested', 'Connected - Interested', 'Connected - Not Interested', 'Wrong Number'));
        $lost_reasons = get_option('idlm_custom_lost_reasons', array('Budget Too High', 'Lost to Competitor', 'Project Postponed', 'Unable to Contact', 'Location or Service Unavailable', 'Timeline Mismatch', 'Client Not Interested', 'Duplicate or Invalid Lead', 'Other'));
        $studios = get_option('idlm_custom_studios', array('Downtown Studio', 'North Studio', 'South Studio'));
        $num_rooms_options = get_option('idlm_custom_num_rooms', array('1 BHK', '2 BHK', '3 BHK', '4 BHK', '4+ BHK'));
        $ui_labels = wp_parse_args((array) get_option('idlm_ui_labels', array()), array('call_status' => 'Call Status', 'lost_reason' => 'Lost Reason', 'preferred_studio' => 'Preferred Studio', 'num_rooms' => 'Rooms'));
        $value_labels = (array) get_option('idlm_value_labels', array());
        $white_label_errors = IDLM_Admin_Settings_Controller::white_label_errors();
        $submitted_white_label = IDLM_Admin_Settings_Controller::white_label_values();
        $white_label = !empty($white_label_errors) && !empty($submitted_white_label) ? $submitted_white_label : IDLM_Settings::get_white_label();
        $white_label_assets = array(
            'logo_id' => IDLM_Settings::brand_asset($white_label['logo_id']),
            'compact_logo_id' => IDLM_Settings::brand_asset($white_label['compact_logo_id']),
            'favicon_id' => IDLM_Settings::brand_asset($white_label['favicon_id']),
        );

        // CAPI
        $meta_capi_enabled = get_option('idlm_meta_capi_enabled', '0');
        $meta_pixel_id = get_option('idlm_meta_pixel_id', '');
        $meta_audience_id = get_option('idlm_meta_audience_id', '');

        $google_capi_enabled = get_option('idlm_google_capi_enabled', '0');
        $google_measurement_id = get_option('idlm_google_measurement_id', '');
        $google_user_list_resource = get_option('idlm_google_user_list_resource', '');
        $google_gadm_username = get_option('idlm_google_gadm_username', 'google-ads');

        $linkedin_capi_enabled = get_option('idlm_linkedin_capi_enabled', '0');
        $linkedin_conversion_id = get_option('idlm_linkedin_conversion_id', '');
        $linkedin_segment_urn = get_option('idlm_linkedin_segment_urn', '');

        // Fetch dynamic stage conversion mappings
        $meta_event_mappings = get_option('idlm_meta_event_mappings');
        if ($meta_event_mappings === false) {
            $meta_event_mappings = array(
                'New' => 'Lead',
                'Qualified' => 'Contact',
                'Quote Shared' => 'Subscribe',
                'Won' => 'Purchase'
            );
        }
        $google_event_mappings = get_option('idlm_google_event_mappings');
        if ($google_event_mappings === false) {
            $google_event_mappings = array(
                'Qualified' => 'CRM Qualified Lead',
                'Won' => 'Closed Won Interior Project'
            );
        }
        $linkedin_event_mappings = get_option('idlm_linkedin_event_mappings');
        if ($linkedin_event_mappings === false) {
            $linkedin_event_mappings = array(
                'Qualified' => 'CRM Qualified Lead',
                'Won' => 'Closed Won'
            );
        }

        // Sync Toggles
        $sync_meta_audience = get_option('idlm_sync_meta_audience', '0');
        $sync_google_audience = get_option('idlm_sync_google_audience', '0');
        $sync_linkedin_audience = get_option('idlm_sync_linkedin_audience', '0');

        // Reminders
        $reminder_hours = get_option('idlm_reminder_hours', 24);
        $reminder_status = get_option('idlm_reminder_status', 'Call Back Requested');
        $reminder_rules = IDLM_Admin::get_reminder_rules();

        // Interakt Settings
        $interakt_enabled = get_option('idlm_interakt_enabled', '0');

        // Meta Webhook Settings
        $meta_webhook_enabled = get_option('idlm_meta_webhook_enabled', '0');

        // Custom Audience rules
        $audience_rules = get_option('idlm_audience_sync_rules');
        if ($audience_rules === false) {
            $audience_rules = array();
            // Migrate old settings if present
            $sync_meta = get_option('idlm_sync_meta_audience', '0');
            $meta_aud = get_option('idlm_meta_audience_id');
            if ($sync_meta === '1' && !empty($meta_aud)) {
                $audience_rules[] = array(
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
                $audience_rules[] = array(
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
                $audience_rules[] = array(
                    'platform' => 'linkedin',
                    'audience_id' => $linkedin_seg,
                    'stage' => 'all',
                    'call_status' => 'all',
                    'lead_quality' => 'all',
                    'enabled' => '1'
                );
            }
        }

        $reps = get_users(array(
            'role__in' => IDLM_Admin::get_allowed_rep_roles(),
            'fields'   => 'all_with_meta',
        ));
        $pre_sales_reps = get_users(array(
            'role'   => 'idlm_pre_sales',
            'fields' => array('ID', 'display_name'),
        ));
        if (null === $active_pre_sales_reps) {
            $active_pre_sales_reps = wp_list_pluck($pre_sales_reps, 'ID');
        }

        IDLM_Integrations::maybe_migrate_api_credentials();

        // Determine active tab
        $active_tab = 'general';
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0059: build_context(): manage_options render context reads active_tab only to restore the selected presentation tab after settings processing; sanitization/unslashing does not persist settings or grant authority.
        if (isset($_POST['active_tab'])) {
        // phpcs:enable WordPress.Security.NonceVerification.Missing
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0060: build_context(): manage_options render context reads active_tab only to restore the selected presentation tab after settings processing; sanitization/unslashing does not persist settings or grant authority.
            $active_tab = sanitize_key(wp_unslash($_POST['active_tab']));
            // phpcs:enable WordPress.Security.NonceVerification.Missing
        }

        $max_active_leads_per_rep = get_option('idlm_max_active_leads_per_rep', 30);
        $routing_exception_notifications_enabled = get_option('idlm_routing_exception_notifications_enabled', '0');
        $field_mappings = array();
        foreach (array('meta', 'google', 'linkedin', 'website', 'walkin') as $platform) {
            $field_mappings[$platform] = get_option('idlm_field_mappings_' . $platform, array());
        }

        $interakt_mappings = get_option('idlm_interakt_mappings');
        if (!is_array($interakt_mappings)) {
            $interakt_mappings = array();
            $legacy_event = get_option('idlm_interakt_event_name');
            if (!empty($legacy_event)) {
                $interakt_mappings[] = array(
                    'type'  => 'call_status',
                    'value' => 'No Answer',
                    'event' => $legacy_event,
                );
            }
        }

        $telemetry_level = get_option('idlm_telemetry_level', 'errors');
        $table_telemetry = $wpdb ? $wpdb->prefix . 'idlm_telemetry_logs' : '';
        $telemetry_logs = array();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0444: build_context(): manage_options renderer, fixed prefixed lead/telemetry tables and constant count predicates; current operational context is intentionally live.
        if ($wpdb && $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_telemetry)) === $table_telemetry) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $telemetry_logs = $wpdb->get_results("SELECT * FROM {$table_telemetry} ORDER BY id DESC LIMIT 50");
        }

        $action_scheduler_version = '';
        if (class_exists('ActionScheduler')) {
            $action_scheduler_version = 'Active';
            if (class_exists('ActionScheduler_Versions') && method_exists('ActionScheduler_Versions', 'instance')) {
                $action_scheduler_version = ActionScheduler_Versions::instance()->latest_version();
            }
        }

        $mcp_script_path = realpath(IDLM_PATH . 'mcp-server.php') ?: IDLM_PATH . 'mcp-server.php';
        $mcp_rest_url = get_rest_url(null, '/interior-design-lead-manager/v1/mcp');
        $mcp_local_json = wp_json_encode(array(
            'mcpServers' => array(
                'interior-design-crm' => array(
                    'command' => 'php',
                    'args'    => array($mcp_script_path),
                ),
            ),
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return array(
            'lead_target' => $lead_target,
            'conv_target' => $conv_target,
            'currency_code' => $currency_code,
            'reps_see_all' => $reps_see_all,
            'assignment_mode' => $assignment_mode,
            'active_reps' => $active_reps,
            'source_maps' => $source_maps,
            'rep_studios' => $rep_studios,
            'fallback_enabled' => $fallback_enabled,
            'fallback_hours' => $fallback_hours,
            'qualified_fallback_enabled' => $qualified_fallback_enabled,
            'qualified_fallback_hours' => $qualified_fallback_hours,
            'qualified_fallback_end_at' => $qualified_fallback_end_at,
            'qualified_fallback_include_existing' => $qualified_fallback_include_existing,
            'studio_mismatch_behavior' => $studio_mismatch_behavior,
            'handoff_task_policy' => $handoff_task_policy,
            'handoff_notifications_enabled' => $handoff_notifications_enabled,
            'active_pre_sales_reps' => $active_pre_sales_reps,
            'fallback_last_completed' => $fallback_last_completed,
            'fallback_last_status' => $fallback_last_status,
            'routing_exception_count' => $routing_exception_count,
            'unassigned_intake_count' => $unassigned_intake_count,
            'working_hours_start' => $working_hours_start,
            'working_hours_end' => $working_hours_end,
            'working_days' => $working_days,
            'presales_targets' => $presales_targets,
            'stages' => $stages,
            'kanban_stage_totals' => $kanban_stage_totals,
            'call_statuses' => $call_statuses,
            'lost_reasons' => $lost_reasons,
            'studios' => $studios,
            'num_rooms_options' => $num_rooms_options,
            'ui_labels' => $ui_labels,
            'value_labels' => $value_labels,
            'white_label' => $white_label,
            'white_label_assets' => $white_label_assets,
            'white_label_errors' => $white_label_errors,
            'meta_capi_enabled' => $meta_capi_enabled,
            'meta_pixel_id' => $meta_pixel_id,
            'meta_audience_id' => $meta_audience_id,
            'google_capi_enabled' => $google_capi_enabled,
            'google_measurement_id' => $google_measurement_id,
            'google_user_list_resource' => $google_user_list_resource,
            'google_gadm_username' => $google_gadm_username,
            'linkedin_capi_enabled' => $linkedin_capi_enabled,
            'linkedin_conversion_id' => $linkedin_conversion_id,
            'linkedin_segment_urn' => $linkedin_segment_urn,
            'meta_event_mappings' => $meta_event_mappings,
            'google_event_mappings' => $google_event_mappings,
            'linkedin_event_mappings' => $linkedin_event_mappings,
            'sync_meta_audience' => $sync_meta_audience,
            'sync_google_audience' => $sync_google_audience,
            'sync_linkedin_audience' => $sync_linkedin_audience,
            'reminder_hours' => $reminder_hours,
            'reminder_status' => $reminder_status,
            'reminder_rules' => $reminder_rules,
            'lead_connector' => IDLM_Lead_Connector::settings_context(),
            'interakt_enabled' => $interakt_enabled,
            'whatsapp_notifications_enabled' => get_option('idlm_whatsapp_notifications_enabled', '0'),
            'meta_webhook_enabled' => $meta_webhook_enabled,
            'audience_rules' => $audience_rules,
            'reps' => $reps,
            'pre_sales_reps' => $pre_sales_reps,
            'active_tab' => $active_tab,
            'max_active_leads_per_rep' => $max_active_leads_per_rep,
            'routing_exception_notifications_enabled' => $routing_exception_notifications_enabled,
            'field_mappings' => $field_mappings,
            'interakt_mappings' => $interakt_mappings,
            'telemetry_level' => $telemetry_level,
            'telemetry_logs' => $telemetry_logs,
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'memory_limit' => ini_get('memory_limit'),
            'action_scheduler_version' => $action_scheduler_version,
            'db_prefix' => $wpdb ? $wpdb->prefix : '',
            'operational_health' => IDLM_Operational_Health::report(),
            'horizontal_readiness' => IDLM_Horizontal_Readiness::report(),
            'diagnostic_url' => admin_url('admin-ajax.php?action=idlm_download_diagnostic_report&nonce=' . wp_create_nonce('idlm_admin_nonce')),
            'mcp_script_path' => $mcp_script_path,
            'mcp_rest_url' => $mcp_rest_url,
            'mcp_local_json' => $mcp_local_json,
            'can_manage_options' => true,
        );
    }
}
