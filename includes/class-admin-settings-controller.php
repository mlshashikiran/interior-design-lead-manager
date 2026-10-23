<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Validates and persists the established administrator settings form.
 *
 * Builder: Shashi
 */
class IDLM_Admin_Settings_Controller {
    private static $white_label_errors = array();
    private static $white_label_values = array();

    /** Return white-label validation failures for the current settings render. */
    public static function white_label_errors() {
        return self::$white_label_errors;
    }

    /** Return submitted white-label values when validation prevented persistence. */
    public static function white_label_values() {
        return self::$white_label_values;
    }

    /** Normalize a structured vocabulary post while retaining the legacy comma field. */
    private static function posted_vocabulary($array_key, $legacy_key, $fallback) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0085: posted_vocabulary(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
        if (isset($_POST[$array_key]) && is_array($_POST[$array_key])) {
        // phpcs:enable WordPress.Security.NonceVerification.Missing
            // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0086: posted_vocabulary(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
            $raw_values = wp_unslash($_POST[$array_key]);
            // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        } else {
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0087: posted_vocabulary(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
            $legacy_value = isset($_POST[$legacy_key]) ? sanitize_text_field(wp_unslash($_POST[$legacy_key])) : '';
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            $raw_values = explode(',', $legacy_value);
        }

        $values = array_values(array_unique(array_filter(array_map('sanitize_text_field', $raw_values))));
        return empty($values) ? array_values($fallback) : $values;
    }

    /** Rotate a scoped REST credential after explicit administrator confirmation. */
    public static function handle_rotate_api_credential() {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.', 403);
        }
        if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))) {
            wp_die('Credential rotation requires a POST request.', 405);
        }
        $scope = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : '';
        if (!in_array($scope, array('webhook', 'mcp'), true)) {
            wp_die('Invalid API credential scope.', 400);
        }
        check_admin_referer('idlm_rotate_api_credential_' . $scope);
        $rotated = IDLM_Integrations::rotate_api_credential($scope);
        if (is_wp_error($rotated)) {
            wp_die(esc_html($rotated->get_error_message()), 400);
        }
        $tab = 'mcp' === $scope ? 'mcp' : 'webhooks';
        $fallback = IDLM_Admin::admin_utility_url();
        $posted_return_url = isset($_POST['idlm_return_url']) ? esc_url_raw(wp_unslash($_POST['idlm_return_url'])) : '';
        $base = $posted_return_url ? wp_validate_redirect($posted_return_url, $fallback) : $fallback;
        $return_url = add_query_arg('idlm_credential_rotated', $scope, $base) . '#tab-' . $tab;
        wp_safe_redirect($return_url);
        exit;
    }

    /**
     * Process the current settings request when it is an authenticated save.
     *
     * @return bool Whether settings were saved.
     */
    public static function process_request() {
        if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD'] || !isset($_POST['idlm_save_settings'])) {
            return false;
        }
        if (!current_user_can('manage_options')) {
            wp_die('Access Denied');
        }

        check_admin_referer('idlm_settings_save_action');
        foreach (IDLM_Secret_Store::platform_options() as $option) {
            $field = in_array($option, array('idlm_meta_access_token','idlm_google_api_secret','idlm_linkedin_access_token'), true) ? substr($option, 5) : $option;
            $clear = !empty($_POST[$field . '_clear']);
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0088: process_request(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
            $raw = wp_unslash($_POST[$field] ?? '');
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if (!is_string($raw)) { wp_die('Invalid credential value.', 400); }
            $value = sanitize_text_field($raw);
            if (!$clear && '' === $value) { continue; }
            if (($clear && '' !== $value) || !IDLM_Secret_Store::set($option, $clear ? '' : $value)) {
                wp_die('Credential could not be saved. Check environment ownership and encryption configuration; retry the settings save.', 503);
            }
        }
        $connector_result = IDLM_Lead_Connector::save_settings(wp_unslash($_POST));
        if (is_wp_error($connector_result)) {
            return false;
        }
        $label_defaults = array('call_status' => 'Call Status', 'lost_reason' => 'Lost Reason', 'preferred_studio' => 'Preferred Studio', 'num_rooms' => 'Rooms');
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0089: process_request(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
        $posted_ui_labels = (isset($_POST['ui_labels']) && is_array($_POST['ui_labels'])) ? wp_unslash($_POST['ui_labels']) : array();
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $ui_labels = array();
        foreach ($label_defaults as $key => $default) {
            $ui_labels[$key] = sanitize_text_field(isset($posted_ui_labels[$key]) ? $posted_ui_labels[$key] : $default) ?: $default;
        }
        update_option('idlm_ui_labels', $ui_labels);
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0090: process_request(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
        $posted_value_labels = (isset($_POST['value_labels']) && is_array($_POST['value_labels'])) ? wp_unslash($_POST['value_labels']) : array();
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $value_labels = array();
        foreach ($posted_value_labels as $group => $labels) {
            $group = sanitize_key($group);
            if (!is_array($labels)) continue;
            foreach ($labels as $encoded_value => $display_label) {
                $value = sanitize_text_field(rawurldecode($encoded_value));
                $display_label = sanitize_text_field($display_label);
                if ($value && $display_label && $value !== $display_label) $value_labels[$group][$value] = $display_label;
            }
        }
        update_option('idlm_value_labels', $value_labels);
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0091: process_request(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
        $white_label_raw = (isset($_POST['white_label']) && is_array($_POST['white_label'])) ? wp_unslash($_POST['white_label']) : array();
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $white_label_validation = IDLM_Settings::validate_white_label($white_label_raw);
        self::$white_label_values = $white_label_validation['settings'];
        self::$white_label_errors = $white_label_validation['errors'];
        // A settings save posts every tab. Administrators who cannot edit an
        // attachment selected by a previous administrator must be able to
        // retain that unchanged white-label asset while saving unrelated
        // settings such as pipeline stages. Changed asset IDs remain subject
        // to the normal access validation above.
        $saved_white_label = IDLM_Settings::get_white_label();
        foreach (array('logo_id', 'compact_logo_id', 'favicon_id') as $asset_field) {
            $submitted_asset_id = absint($white_label_validation['settings'][$asset_field] ?? 0);
            $saved_asset_id = absint($saved_white_label[$asset_field] ?? 0);
            if ($submitted_asset_id === $saved_asset_id) {
                unset(self::$white_label_errors[$asset_field]);
            }
        }
        if (!empty(self::$white_label_errors)) {
            return false;
        }
        update_option(IDLM_Settings::WHITE_LABEL_OPTION, self::$white_label_values, false);

        $previous_presales_targets = IDLM_DB::get_presales_outreach_targets();

        // Save basic settings
        update_option('idlm_monthly_lead_target', isset($_POST['lead_target']) ? intval(wp_unslash($_POST['lead_target'])) : 0);
        update_option('idlm_monthly_conversion_target', isset($_POST['conversion_target']) ? intval(wp_unslash($_POST['conversion_target'])) : 0);
        update_option('idlm_currency_code', isset($_POST['currency_code']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['currency_code']))) : 'INR');
        update_option('idlm_reps_see_all_leads', isset($_POST['reps_see_all']) ? '1' : '0');
        update_option('idlm_presales_calls_per_day', isset($_POST['presales_calls_per_day']) ? min(20, max(1, intval(wp_unslash($_POST['presales_calls_per_day'])))) : 2);
        update_option('idlm_presales_whatsapp_per_day', isset($_POST['presales_whatsapp_per_day']) ? min(20, max(0, intval(wp_unslash($_POST['presales_whatsapp_per_day'])))) : 1);
        $assignment_mode = isset($_POST['assignment_mode']) ? sanitize_key(wp_unslash($_POST['assignment_mode'])) : 'manual';
        if (!in_array($assignment_mode, array('manual', 'pre_sales', 'round_robin', 'source_based', 'studio_based'), true)) {
            $assignment_mode = 'manual';
        }
        update_option('idlm_assignment_mode', $assignment_mode);
        update_option('idlm_fallback_assignment_enabled', isset($_POST['fallback_assignment_enabled']) ? '1' : '0');
        update_option('idlm_fallback_assignment_hours', isset($_POST['fallback_assignment_hours']) ? max(1, intval(wp_unslash($_POST['fallback_assignment_hours']))) : 24);
        $qualified_fallback_was_enabled = get_option('idlm_qualified_fallback_enabled', '0');
        $qualified_fallback_is_enabled = isset($_POST['qualified_fallback_enabled']) ? '1' : '0';
        update_option('idlm_qualified_fallback_enabled', $qualified_fallback_is_enabled);
        if ('1' === $qualified_fallback_is_enabled && '1' !== $qualified_fallback_was_enabled) {
            update_option('idlm_qualified_fallback_enabled_at', current_time('mysql'));
        }
        update_option('idlm_qualified_fallback_hours', isset($_POST['qualified_fallback_hours']) ? min(720, max(1, intval(wp_unslash($_POST['qualified_fallback_hours'])))) : 4);
        $qualified_end_raw = isset($_POST['qualified_fallback_end_at']) ? sanitize_text_field(wp_unslash($_POST['qualified_fallback_end_at'])) : '';
        $qualified_end_at = $qualified_end_raw && strtotime($qualified_end_raw) ? str_replace('T', ' ', $qualified_end_raw) : '';
        update_option('idlm_qualified_fallback_end_at', $qualified_end_at);
        update_option('idlm_qualified_fallback_include_existing', isset($_POST['qualified_fallback_include_existing']) ? '1' : '0');
        $studio_mismatch_behavior = isset($_POST['studio_mismatch_behavior']) ? sanitize_key(wp_unslash($_POST['studio_mismatch_behavior'])) : 'general_pool';
        update_option('idlm_studio_mismatch_behavior', in_array($studio_mismatch_behavior, array('general_pool', 'hold_for_manager'), true) ? $studio_mismatch_behavior : 'general_pool');
        $handoff_task_policy = isset($_POST['handoff_task_policy']) ? sanitize_key(wp_unslash($_POST['handoff_task_policy'])) : 'transfer';
        update_option('idlm_handoff_task_policy', in_array($handoff_task_policy, array('transfer', 'retain', 'complete_qualification'), true) ? $handoff_task_policy : 'transfer');
        update_option('idlm_handoff_notifications_enabled', isset($_POST['handoff_notifications_enabled']) ? '1' : '0');
        update_option('idlm_routing_exception_notifications_enabled', isset($_POST['routing_exception_notifications_enabled']) ? '1' : '0');
        update_option('idlm_whatsapp_notifications_enabled', isset($_POST['whatsapp_notifications_enabled']) && is_string($_POST['whatsapp_notifications_enabled']) && '1' === sanitize_text_field(wp_unslash($_POST['whatsapp_notifications_enabled'])) ? '1' : '0');
        update_option('idlm_max_active_leads_per_rep', isset($_POST['max_active_leads_per_rep']) ? min(10000, max(0, absint(wp_unslash($_POST['max_active_leads_per_rep'])))) : 30);
        update_option('idlm_working_hours_start', isset($_POST['working_hours_start']) ? sanitize_text_field(wp_unslash($_POST['working_hours_start'])) : '09:00');
        update_option('idlm_working_hours_end', isset($_POST['working_hours_end']) ? sanitize_text_field(wp_unslash($_POST['working_hours_end'])) : '18:00');
        $working_days = (!empty($_POST['working_days']) && is_array($_POST['working_days'])) ? array_map('intval', wp_unslash($_POST['working_days'])) : array();
        update_option('idlm_working_days', $working_days);
        if ($previous_presales_targets !== IDLM_DB::get_presales_outreach_targets()) {
            IDLM_DB::refresh_presales_task_titles();
        }

        // Save Reps for auto assignments
        $active_reps = (!empty($_POST['active_reps']) && is_array($_POST['active_reps'])) ? array_map('intval', wp_unslash($_POST['active_reps'])) : array();
        update_option('idlm_active_assignment_reps', $active_reps);
        $posted_pre_sales = (!empty($_POST['active_pre_sales_reps']) && is_array($_POST['active_pre_sales_reps'])) ? array_map('absint', wp_unslash($_POST['active_pre_sales_reps'])) : array();
        $pre_sales_role_ids = array_map('absint', get_users(array('role' => 'idlm_pre_sales', 'fields' => 'ID')));
        update_option('idlm_active_pre_sales_reps', array_values(array_intersect($posted_pre_sales, $pre_sales_role_ids)));

        // Save the studio location assigned to each representative.
        $allowed_studios = get_option('idlm_custom_studios', array('Downtown Studio', 'North Studio', 'South Studio'));
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0092: process_request(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
        $posted_rep_studios = (!empty($_POST['rep_studios']) && is_array($_POST['rep_studios'])) ? wp_unslash($_POST['rep_studios']) : array();
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $rep_studios = array();
        foreach ($posted_rep_studios as $rep_id => $studio) {
            $rep_id = absint($rep_id);
            $studio = sanitize_text_field($studio);
            if ($rep_id && in_array($studio, $allowed_studios, true)) {
                $rep_studios[$rep_id] = $studio;
            }
        }
        update_option('idlm_rep_studio_assignments', $rep_studios);

        // Save source based mappings
        $sources = array('Website', 'Meta Ads', 'Google Ads', 'LinkedIn Ads', 'Referral', 'Offline/Walk-in');
        $source_maps = array();
        foreach ($sources as $src) {
            $field_name = 'source_map_' . str_replace(array(' ', '/', '-'), '_', strtolower($src));
            if (isset($_POST[$field_name])) {
                $source_maps[$src] = intval(wp_unslash($_POST[$field_name]));
            }
        }
        update_option('idlm_source_assignment_mappings', $source_maps);
        IDLM_Settings::sync_routing_from_legacy();

        // Save custom options lists
        $stages = self::posted_vocabulary(
            'custom_stages_values',
            'custom_stages',
            get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'))
        );
        update_option('idlm_custom_stages', $stages);

        // Save the monetary field consolidated in each Kanban stage header.
        $kanban_stage_totals = array();
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0093: process_request(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
        $posted_stage_totals = (isset($_POST['kanban_stage_totals']) && is_array($_POST['kanban_stage_totals'])) ? wp_unslash($_POST['kanban_stage_totals']) : array();
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $allowed_total_sources = array('none', 'quote_value', 'deal_value');
        foreach ($posted_stage_totals as $encoded_stage => $total_source) {
            $stage_name = sanitize_text_field(rawurldecode($encoded_stage));
            $source = sanitize_key($total_source);
            if (in_array($stage_name, $stages, true) && in_array($source, $allowed_total_sources, true)) {
                $kanban_stage_totals[$stage_name] = $source;
            }
        }
        foreach ($stages as $stage) {
            if (!isset($kanban_stage_totals[$stage])) {
                $kanban_stage_totals[$stage] = 'none';
            }
        }
        update_option('idlm_kanban_stage_totals', $kanban_stage_totals);
        
        $call_statuses = self::posted_vocabulary(
            'custom_call_statuses_values',
            'custom_call_statuses',
            get_option('idlm_custom_call_statuses', array('Not Called', 'No Answer', 'Call Back Requested', 'Connected - Interested', 'Connected - Not Interested', 'Wrong Number'))
        );
        update_option('idlm_custom_call_statuses', $call_statuses);

        $lost_reasons = self::posted_vocabulary(
            'custom_lost_reasons_values',
            'custom_lost_reasons',
            get_option('idlm_custom_lost_reasons', array('Budget Too High', 'Lost to Competitor', 'Project Postponed', 'Unable to Contact', 'Location or Service Unavailable', 'Timeline Mismatch', 'Client Not Interested', 'Duplicate or Invalid Lead', 'Other'))
        );
        update_option('idlm_custom_lost_reasons', $lost_reasons);

        $studios = self::posted_vocabulary(
            'custom_studios_values',
            'custom_studios',
            get_option('idlm_custom_studios', array('Downtown Studio', 'North Studio', 'South Studio'))
        );
        update_option('idlm_custom_studios', $studios);

        $num_rooms = self::posted_vocabulary(
            'custom_num_rooms_values',
            'custom_num_rooms',
            get_option('idlm_custom_num_rooms', array('1 BHK', '2 BHK', '3 BHK', '4 BHK', '4+ BHK'))
        );
        update_option('idlm_custom_num_rooms', $num_rooms);

        // Save CAPI configurations
        update_option('idlm_meta_capi_enabled', isset($_POST['meta_capi_enabled']) ? '1' : '0');
        update_option('idlm_meta_pixel_id', isset($_POST['meta_pixel_id']) ? sanitize_text_field(wp_unslash($_POST['meta_pixel_id'])) : '');
        if (isset($_POST['meta_audience_id'])) {
            update_option('idlm_meta_audience_id', sanitize_text_field(wp_unslash($_POST['meta_audience_id'])));
        }
        update_option('idlm_google_capi_enabled', isset($_POST['google_capi_enabled']) ? '1' : '0');
        update_option('idlm_google_measurement_id', isset($_POST['google_measurement_id']) ? sanitize_text_field(wp_unslash($_POST['google_measurement_id'])) : '');
        if (isset($_POST['google_user_list_resource'])) {
            update_option('idlm_google_user_list_resource', sanitize_text_field(wp_unslash($_POST['google_user_list_resource'])));
        }

        if (isset($_POST['google_gadm_username'])) {
            update_option('idlm_google_gadm_username', sanitize_text_field(wp_unslash($_POST['google_gadm_username'])));
        }
        if (isset($_POST['google_gadm_password'])) {
            IDLM_Secret_Store::set('idlm_google_gadm_password', sanitize_text_field(wp_unslash($_POST['google_gadm_password'])));
        }

        update_option('idlm_linkedin_capi_enabled', isset($_POST['linkedin_capi_enabled']) ? '1' : '0');
        update_option('idlm_linkedin_conversion_id', isset($_POST['linkedin_conversion_id']) ? sanitize_text_field(wp_unslash($_POST['linkedin_conversion_id'])) : '');
        if (isset($_POST['linkedin_segment_urn'])) {
            update_option('idlm_linkedin_segment_urn', sanitize_text_field(wp_unslash($_POST['linkedin_segment_urn'])));
        }

        // Save dynamic stage mappings
        if (isset($_POST['meta_event_mappings']) && is_array($_POST['meta_event_mappings'])) {
            $meta_maps = array_map('sanitize_text_field', wp_unslash($_POST['meta_event_mappings']));
            update_option('idlm_meta_event_mappings', $meta_maps);
        }
        if (isset($_POST['google_event_mappings']) && is_array($_POST['google_event_mappings'])) {
            $google_maps = array_map('sanitize_text_field', wp_unslash($_POST['google_event_mappings']));
            update_option('idlm_google_event_mappings', $google_maps);
        }
        if (isset($_POST['linkedin_event_mappings']) && is_array($_POST['linkedin_event_mappings'])) {
            $linkedin_maps = array_map('sanitize_text_field', wp_unslash($_POST['linkedin_event_mappings']));
            update_option('idlm_linkedin_event_mappings', $linkedin_maps);
        }

        // Save dynamic mappings
        $platforms = array('meta', 'google', 'linkedin', 'website', 'walkin');
        $fields = array('first_name', 'last_name', 'email', 'phone', 'budget', 'project_timeline', 'property_location', 'property_type', 'num_rooms', 'preferred_studio', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'li_fat_id', 'fb_lead_id', 'landing_page', 'conversion_time', 'referrer_details');
        foreach ($platforms as $plat) {
            $plat_maps = array();
            foreach ($fields as $f) {
                $key_name = "map_{$plat}_{$f}";
                if (isset($_POST[$key_name])) {
                    $plat_maps[$f] = sanitize_text_field(wp_unslash($_POST[$key_name]));
                }
            }
            update_option('idlm_field_mappings_' . $plat, $plat_maps);
        }

        // Save platform Sync Toggles
        update_option('idlm_sync_meta_audience', isset($_POST['sync_meta_audience']) ? '1' : '0');
        update_option('idlm_sync_google_audience', isset($_POST['sync_google_audience']) ? '1' : '0');
        update_option('idlm_sync_linkedin_audience', isset($_POST['sync_linkedin_audience']) ? '1' : '0');

        // Save Telemetry Level
        if (isset($_POST['telemetry_level'])) {
            update_option('idlm_telemetry_level', sanitize_text_field(wp_unslash($_POST['telemetry_level'])));
        }

        // Save Dynamic Custom Audience Sync Rules
        $audience_rules_saved = array();
        if (isset($_POST['audience_rules']) && is_array($_POST['audience_rules'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $raw_rules = wp_unslash($_POST['audience_rules']);
            if (isset($raw_rules['platform']) && is_array($raw_rules['platform'])) {
                $count = count($raw_rules['platform']);
                for ($i = 0; $i < $count; $i++) {
                    $plat = isset($raw_rules['platform'][$i]) ? sanitize_text_field($raw_rules['platform'][$i]) : '';
                    $aud_id = isset($raw_rules['audience_id'][$i]) ? sanitize_text_field($raw_rules['audience_id'][$i]) : '';
                    $is_value = isset($raw_rules['is_value'][$i]) ? sanitize_text_field($raw_rules['is_value'][$i]) : '0';
                    $stage = isset($raw_rules['stage'][$i]) ? sanitize_text_field($raw_rules['stage'][$i]) : '';
                    $call_status = isset($raw_rules['call_status'][$i]) ? sanitize_text_field($raw_rules['call_status'][$i]) : '';
                    $lead_quality = isset($raw_rules['lead_quality'][$i]) ? sanitize_text_field($raw_rules['lead_quality'][$i]) : '';
                    $enabled = isset($raw_rules['enabled'][$i]) ? sanitize_text_field($raw_rules['enabled'][$i]) : '';

                    if (!empty($plat) && !empty($aud_id)) {
                        $audience_rules_saved[] = array(
                            'platform' => $plat,
                            'audience_id' => $aud_id,
                            'is_value' => $is_value,
                            'stage' => $stage,
                            'call_status' => $call_status,
                            'lead_quality' => $lead_quality,
                            'enabled' => $enabled
                        );
                    }
                }
            }
        }
        update_option('idlm_audience_sync_rules', $audience_rules_saved);

        // Save Reminder Intervals
        $reminder_rules_saved = array();
        if (isset($_POST['reminder_rules_status']) && isset($_POST['reminder_rules_hours'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $rules_status = wp_unslash($_POST['reminder_rules_status']);
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $rules_hours = wp_unslash($_POST['reminder_rules_hours']);
            if (is_array($rules_status) && is_array($rules_hours)) {
                $count = count($rules_status);
                for ($i = 0; $i < $count; $i++) {
                    $status = isset($rules_status[$i]) ? sanitize_text_field($rules_status[$i]) : '';
                    $hours = isset($rules_hours[$i]) ? intval($rules_hours[$i]) : 0;
                    if (!empty($status) && $hours > 0) {
                        $reminder_rules_saved[] = array(
                            'status' => $status,
                            'hours'  => $hours
                        );
                    }
                }
            }
        }
        update_option('idlm_reminders_config', $reminder_rules_saved);

        if (!empty($reminder_rules_saved)) {
            update_option('idlm_reminder_status', $reminder_rules_saved[0]['status']);
            update_option('idlm_reminder_hours', $reminder_rules_saved[0]['hours']);
        }

        // Save Interakt Settings
        $interakt_enabled = isset($_POST['idlm_interakt_enabled']) ? '1' : '0';
        update_option('idlm_interakt_enabled', $interakt_enabled);


        // Save Meta Webhook Settings
        $meta_webhook_enabled = isset($_POST['idlm_meta_webhook_enabled']) ? '1' : '0';
        update_option('idlm_meta_webhook_enabled', $meta_webhook_enabled);

        $interakt_mappings = array();
        if (isset($_POST['interakt_mapping_type']) && isset($_POST['interakt_mapping_value']) && isset($_POST['interakt_mapping_event'])) {
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0094: process_request(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
            $types = wp_unslash($_POST['interakt_mapping_type']);
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0095: process_request(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
            $values = wp_unslash($_POST['interakt_mapping_value']);
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0096: process_request(): process_request requires manage_options and idlm_settings_save_action before helper calls; vocabulary/label/studio/role/event arrays are type checked and individually sanitized/allowlisted, White_Label validates its own structure, and secrets are string checked/sanitized after one unslash; helper reads cannot bypass that authority.
            $events = wp_unslash($_POST['interakt_mapping_event']);
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

            if (is_array($types) && is_array($values) && is_array($events)) {
                $count = count($types);
                for ($i = 0; $i < $count; $i++) {
                    $m_type = isset($types[$i]) ? sanitize_key($types[$i]) : '';
                    $m_val = isset($values[$i]) ? sanitize_text_field($values[$i]) : '';
                    $m_evt = isset($events[$i]) ? sanitize_key($events[$i]) : '';

                    if (!empty($m_type) && !empty($m_val) && !empty($m_evt)) {
                        $interakt_mappings[] = array(
                            'type'  => $m_type,
                            'value' => $m_val,
                            'event' => $m_evt
                        );
                    }
                }
            }
        }
        update_option('idlm_interakt_mappings', $interakt_mappings);

        return true;
    }

}
