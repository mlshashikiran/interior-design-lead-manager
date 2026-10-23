<?php
/**
 * Authenticated CRM AJAX request handlers.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Admin_Ajax extends IDLM_Admin {
    public static function ajax_get_lead_details() {
        return IDLM_Admin_Lead_Reads::ajax_get_lead_details();
    }

    /**
     * AJAX Endpoint: Get lead activity log history for lazy-loading
     */
    public static function ajax_get_lead_activity() {
        return IDLM_Admin_Activity_Controller::ajax_get_lead_activity();
    }

    /**
     * AJAX Endpoint: Inline Quick-Edit row changes
     */
    public static function ajax_quick_edit_lead() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        $field = isset($_POST['field']) ? sanitize_key(wp_unslash($_POST['field'])) : '';
        $value = isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : '';
        $lost_remark = isset($_POST['lost_remark']) ? sanitize_textarea_field(wp_unslash($_POST['lost_remark'])) : '';
        $lost_reason = isset($_POST['lost_reason']) ? sanitize_text_field(wp_unslash($_POST['lost_reason'])) : '';
        $edit_context = isset($_POST['edit_context']) ? sanitize_key(wp_unslash($_POST['edit_context'])) : '';

        if (!$lead_id || !in_array($field, array('call_status', 'stage', 'assigned_rep_id', 'lead_quality'), true)) {
            wp_send_json_error(array('message' => 'Invalid edit request parameters'));
        }
        if (!self::current_user_can_access_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You can update only leads assigned to you.'));
        }
        if ('stage' === $field && 'drawer_header' === $edit_context && self::current_user_is_pre_sales()) {
            wp_send_json_error(array('message' => 'Only sales representatives and managers can change the stage from the lead drawer header.'));
        }

        if ('assigned_rep_id' === $field) {
            $value = absint($value);
            if (!self::current_user_can_assign_leads()) {
                wp_send_json_error(array('message' => 'Only a sales manager or administrator can reassign leads.'));
            }
            if (!self::is_assignable_rep($value)) {
                wp_send_json_error(array('message' => 'Please select a valid CRM representative.'));
            }
        }

        if ('stage' === $field && self::current_user_is_pre_sales() && !in_array($value, array('New', 'Contacted', 'Qualified'), true)) {
            wp_send_json_error(array('message' => 'Pre-sales users can progress leads only through the Qualified stage.'));
        }

        if ('stage' === $field && 'Lost' === $value) {
            $allowed_lost_reasons = get_option('idlm_custom_lost_reasons', array('Budget Too High', 'Lost to Competitor', 'Project Postponed', 'Unable to Contact', 'Location or Service Unavailable', 'Timeline Mismatch', 'Client Not Interested', 'Duplicate or Invalid Lead', 'Other'));
            if (!in_array($lost_reason, $allowed_lost_reasons, true)) {
                wp_send_json_error(array('message' => 'Please select a valid lost reason.'));
            }
            if ('' === trim($lost_remark)) {
                wp_send_json_error(array('message' => 'A lost remark is required before moving the lead to Lost.'));
            }
        }

        $data = array($field => $value);
        if ('stage' === $field && 'Lost' === $value) {
            $data['lost_reason'] = $lost_reason;
            $data['lost_remark'] = $lost_remark;
        }
        
        // Custom logic for Call Back Requested addition callback fields
        if ($field === 'call_status' && $value === 'Call Back Requested' && isset($_POST['callback_time'])) {
            $cb_time = sanitize_text_field(wp_unslash($_POST['callback_time']));
            $data['callback_datetime'] = !empty($cb_time) ? str_replace('T', ' ', $cb_time) : null;
        }

        // Deal value and discount percentage are mandatory for Won stage transitions.
        if ('stage' === $field && 'Won' === $value) {
            $deal_value_raw = isset($_POST['deal_value']) ? sanitize_text_field(wp_unslash($_POST['deal_value'])) : '';
            $discount_raw = isset($_POST['discount_percentage']) ? sanitize_text_field(wp_unslash($_POST['discount_percentage'])) : '';
            $property_location = isset($_POST['property_location']) ? sanitize_text_field(wp_unslash($_POST['property_location'])) : '';

            if (!is_numeric($deal_value_raw) || floatval($deal_value_raw) <= 0) {
                wp_send_json_error(array('message' => 'A valid deal value greater than zero is required before moving the lead to Won.'));
            }

            if ('' === $discount_raw || !is_numeric($discount_raw) || floatval($discount_raw) < 0 || floatval($discount_raw) > 100) {
                wp_send_json_error(array('message' => 'A discount percentage between 0 and 100 is required before moving the lead to Won.'));
            }

            $data['deal_value'] = round(floatval($deal_value_raw), 2);
            $data['discount_percentage'] = round(floatval($discount_raw), 2);
            $data['property_location'] = $property_location;
        }

        if ('stage' === $field && 'Quote Shared' === $value) {
            $has_structured_quotes = class_exists('IDLM_Quotations') && IDLM_Quotations::lead_has_quotes($lead_id);
            if ($has_structured_quotes && IDLM_Quotation_Repository::get_lead_quote_value($lead_id) <= 0) { wp_send_json_error(array('message' => 'The structured quotation total must be greater than zero before moving the lead to Quote Shared.')); }
            if (!$has_structured_quotes) {
                $quote_value_raw = isset($_POST['quote_value']) ? sanitize_text_field(wp_unslash($_POST['quote_value'])) : '';
                $quote_file = isset($_POST['quote_file']) ? esc_url_raw(wp_unslash($_POST['quote_file'])) : '';
                if (!is_numeric($quote_value_raw) || floatval($quote_value_raw) <= 0) {
                    wp_send_json_error(array('message' => 'A valid quote value greater than zero is required before moving the lead to Quote Shared.'));
                }
                $data['quote_value'] = round(floatval($quote_value_raw), 2);
                $data['quote_file'] = $quote_file;
            }
            $data['quote_shared'] = 1;
        }

        $updated = IDLM_DB::update_lead($lead_id, $data);
        if ($updated) {
            wp_send_json_success(array(
                'message' => 'Lead updated successfully',
                'pending_calls_count' => self::get_pending_calls_count()
            ));
        }

        $validation_error = IDLM_DB::get_last_validation_error();
        wp_send_json_error(array('message' => $validation_error ?: 'Failed to save changes. Value unchanged or database lock.'));
    }

    /** Map structured early-close reasons to the sales-pipeline Lost taxonomy. */
    private static function map_disqualification_to_lost_reason($reason) {
        $map = array(
            'duplicate' => 'Duplicate or Invalid Lead',
            'invalid_contact' => 'Duplicate or Invalid Lead',
            'budget_below_minimum' => 'Budget Too High',
            'outside_service_area' => 'Location or Service Unavailable',
            'timeline_mismatch' => 'Timeline Mismatch',
            'not_interested' => 'Client Not Interested',
        );
        return isset($map[$reason]) ? $map[$reason] : 'Other';
    }

    /** Build a concise error for bulk actions that only partially succeeded. */
    private static function format_bulk_failures($label, $failed) {
        $details = array();
        foreach (array_slice($failed, 0, 5, true) as $lead_id => $message) {
            $details[] = sprintf('#%d: %s', $lead_id, $message);
        }
        return sprintf('%s failed for %d lead(s). %s', $label, count($failed), implode(' ', $details));
    }

    /** Log auditable pre-sales outreach or a policy-controlled early close. */
    public static function ajax_log_presales_touch() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        if (!self::current_user_is_pre_sales() && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Only pre-sales representatives and administrators can log pre-sales follow-up.'));
        }
        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $kind = isset($_POST['kind']) ? sanitize_key(wp_unslash($_POST['kind'])) : '';
        $note = isset($_POST['note']) ? sanitize_textarea_field(wp_unslash($_POST['note'])) : '';
        $call_status = isset($_POST['call_status']) ? sanitize_text_field(wp_unslash($_POST['call_status'])) : '';
        $callback_time = isset($_POST['callback_time']) ? sanitize_text_field(wp_unslash($_POST['callback_time'])) : '';
        if (!$lead_id || !self::current_user_can_access_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You can update only leads assigned to you.'));
        }

        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0183: ajax_log_presales_touch(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        $lead = $wpdb->get_row($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d",
            $lead_id
        ));
        if (!$lead) {
            wp_send_json_error(array('message' => 'Lead not found.'));
        }
        if ('early_close' !== $kind && in_array($lead->lead_quality, array('Qualified', 'Unqualified'), true)) {
            wp_send_json_error(array('message' => 'This pre-sales decision is complete. Further activity belongs in the post-decision workflow.'));
        }

        $types = array('call' => 'outbound_call', 'whatsapp' => 'whatsapp_outbound', 'closure' => 'closure_message');
        if ('early_close' === $kind) {
            $reason = isset($_POST['reason']) ? sanitize_key(wp_unslash($_POST['reason'])) : '';
            $reason_labels = IDLM_State_Registry::early_close_reasons();
            if (!isset($reason_labels[$reason])) {
                wp_send_json_error(array('message' => 'Select a valid early-close reason.'));
            }
            if (strlen(trim($note)) < 10) {
                wp_send_json_error(array('message' => 'Document the evidence in at least 10 characters.'));
            }
            if ('administrator_exception' === $reason && !current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Only an administrator can use the exceptional override.'));
            }
            if ('Qualified' === $lead->lead_quality && !current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Only an administrator can early-close a previously Qualified lead.'));
            }
            if ('budget_below_minimum' === $reason && IDLM_DB::budget_to_rupees($lead->budget) >= 400000) {
                wp_send_json_error(array('message' => 'The recorded budget is not below ₹4,00,000. Correct the budget or choose another reason.'));
            }

            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0184: ajax_log_presales_touch(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            $outbound_calls = (int) $wpdb->get_var($wpdb->prepare(
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id = %d AND activity_type = 'outbound_call'",
                $lead_id
            ));
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0185: ajax_log_presales_touch(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            $response_evidence = in_array($lead->call_status, array('Connected - Interested', 'Connected - Not Interested'), true) || (bool) $wpdb->get_var($wpdb->prepare(
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT id FROM {$wpdb->prefix}idlm_lead_activity_log
                 WHERE lead_id = %d AND (activity_type = 'whatsapp_reply' OR (activity_type = 'comment' AND (details LIKE %s OR details LIKE %s))) LIMIT 1",
                $lead_id,
                $wpdb->esc_like('[WhatsApp Reply]:') . '%',
                $wpdb->esc_like('[Direct WA Reply]:') . '%'
            ));
            $confirmed_reasons = array_keys(IDLM_State_Registry::early_close_reason_groups()['confirmed']);
            if (in_array($reason, $confirmed_reasons, true) && !$response_evidence && !current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'This reason requires a Connected call status or a recorded WhatsApp reply.'));
            }
            if ('invalid_contact' === $reason && 'Wrong Number' !== $lead->call_status && $outbound_calls < 1 && !current_user_can('manage_options')) {
                wp_send_json_error(array('message' => 'Log at least one call attempt or set Call Status to Wrong Number before using Invalid Contact.'));
            }

            $is_duplicate_close = 'duplicate' === $reason;
            $duplicate_of_id = 0;
            if ($is_duplicate_close) {
                $duplicate_of_id = IDLM_DB::find_canonical_duplicate_id($lead_id);
                if (!$duplicate_of_id) {
                    wp_send_json_error(array('message' => 'No matching original lead was found. Confirm the email or phone matches the canonical lead before closing as Duplicate.'));
                }
            }
            $close_data = array(
                'lead_quality' => 'Unqualified',
                'stage' => 'Lost',
                'lost_reason' => $is_duplicate_close ? 'Duplicate or Invalid Lead' : self::map_disqualification_to_lost_reason($reason),
                'disqualification_reason' => $reason,
                'disqualification_method' => 'early_close',
                'disqualified_at' => current_time('mysql'),
                'disqualified_by_user_id' => get_current_user_id(),
                'handoff_status' => 'not_ready',
                'handoff_at' => null,
                'handoff_method' => '',
                'handoff_exception_reason' => '',
                'updated_at' => current_time('mysql'),
            );
            if ($is_duplicate_close) {
                $close_data['is_duplicate'] = 1;
                $close_data['duplicate_of_id'] = $duplicate_of_id;
            }
            $closed = IDLM_DB::update_lead($lead_id, $close_data);
            if (false === $closed) {
                wp_send_json_error(array('message' => IDLM_DB::get_last_validation_error() ?: 'The early-close decision could not be saved. No tasks were changed.'));
            }
            if ($is_duplicate_close) {
                IDLM_DB::log_activity($lead_id, 'duplicate_exclusion', sprintf('Lead excluded as a duplicate contact. Evidence: %s', $note));
                IDLM_DB::log_activity($lead_id, 'lead_quality', "Lead quality changed to 'Unqualified' through the structured duplicate early-close policy.");
                wp_send_json_success(array('message' => 'Duplicate contact marked Unqualified and excluded from qualification reporting and future conversion events.'));
            }
            IDLM_DB::log_activity($lead_id, 'early_disqualification', sprintf('Lead closed early. Reason: %s. Evidence: %s', $reason_labels[$reason], $note));
            IDLM_DB::log_activity($lead_id, 'lead_quality', "Lead quality changed to 'Unqualified' through the structured early-close policy.");
            wp_send_json_success(array('message' => 'Lead closed early with an auditable reason.'));
        }
        if (!isset($types[$kind])) {
            wp_send_json_error(array('message' => 'Invalid follow-up activity.'));
        }
        if ('call' === $kind) {
            $allowed_call_statuses = get_option('idlm_custom_call_statuses', array('Not Called', 'No Answer', 'Call Back Requested', 'Connected - Interested', 'Connected - Not Interested', 'Wrong Number'));
            $allowed_call_statuses = array_values(array_diff((array) $allowed_call_statuses, array('Not Called')));
            if (!$call_status || !in_array($call_status, $allowed_call_statuses, true)) {
                wp_send_json_error(array('message' => 'Select a valid call outcome before logging the call.'));
            }
            $call_update = array('call_status' => $call_status);
            if ('Call Back Requested' === $call_status) {
                $callback_timestamp = strtotime($callback_time);
                if (!$callback_timestamp || $callback_timestamp <= current_time('timestamp')) {
                    wp_send_json_error(array('message' => 'Enter a valid future callback date and time.'));
                }
                $call_update['callback_datetime'] = wp_date('Y-m-d H:i:s', $callback_timestamp, wp_timezone());
            } else {
                $call_update['callback_datetime'] = null;
            }
            if (!IDLM_DB::update_lead($lead_id, $call_update)) {
                wp_send_json_error(array('message' => IDLM_DB::get_last_validation_error() ?: 'The call outcome could not be saved.'));
            }
        }
        if ('closure' === $kind) {
            $progress = IDLM_DB::get_followup_compliance($lead_id);
            if (count($progress['days']) < 6 || $progress['complete_days'] < 6) {
                wp_send_json_error(array('message' => 'The final closure can be logged only after all six outreach cycles are complete.'));
            }
        }
        IDLM_DB::log_activity($lead_id, $types[$kind], ucfirst($kind) . ' logged manually.' . ($note ? ' Note: ' . $note : ''));
        if (in_array($kind, array('call', 'whatsapp'), true) && 'New' === $lead->stage) {
            if (!IDLM_DB::update_lead($lead_id, array('stage' => 'Contacted'))) {
                wp_send_json_error(array('message' => IDLM_DB::get_last_validation_error() ?: 'The outreach was logged, but the lead could not be moved to Contacted.'));
            }
        }
        if (!IDLM_DB::sync_presales_task_status($lead_id)) {
            wp_send_json_error(array('message' => 'Outreach was recorded, but the qualification task plan needs repair. The scheduled worker will retry; contact an administrator if this persists.'));
        }
        if ('call' === $kind) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- REL007-SQL-0186: ajax_log_presales_touch(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            $wpdb->insert($wpdb->prefix . 'idlm_call_logs', array(
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
                'lead_id' => $lead_id, 'user_id' => get_current_user_id(), 'provider' => 'manual',
                'direction' => 'outbound', 'outcome' => $call_status, 'started_at' => current_time('mysql'),
                'metadata' => wp_json_encode(array('source' => 'crm_manual', 'note' => $note)), 'created_at' => current_time('mysql'),
            ));
        }
        wp_send_json_success(array('message' => ucfirst($kind) . ' logged successfully.'));
    }

    /** Apply an explicit pre-sales qualification or unreachable decision. */
    public static function ajax_presales_decision() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_is_pre_sales() && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Only pre-sales representatives and administrators can make this decision.'));
        }
        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        $decision = isset($_POST['decision']) ? sanitize_key(wp_unslash($_POST['decision'])) : '';
        $handoff_remark = isset($_POST['handoff_remark']) ? sanitize_textarea_field(wp_unslash($_POST['handoff_remark'])) : '';
        if (!$lead_id || !self::current_user_can_access_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You can decide only leads assigned to you.'));
        }
        if ('qualify' === $decision) {
            $updated = IDLM_DB::update_lead($lead_id, array(
                'lead_quality' => 'Qualified',
                'qualification_handoff_remark' => $handoff_remark,
            ));
            if (!$updated) {
                wp_send_json_error(array('message' => IDLM_DB::get_last_validation_error() ?: 'The lead could not be qualified.'));
            }
            IDLM_DB::log_activity($lead_id, 'qualification_decision', current_user_can('manage_options') && !self::current_user_is_pre_sales() ? 'Administrator decision: Qualified.' : 'Pre-sales decision: Qualified.');
            wp_send_json_success(array('message' => 'Lead qualified and moved to the qualified handoff workflow.'));
        }
        if ('unreachable' === $decision) {
            $compliance = IDLM_DB::get_followup_compliance($lead_id);
            if (empty($compliance['eligible'])) {
                wp_send_json_error(array('message' => 'Complete six outreach cycles and log the final closure before closing as unreachable.'));
            }
            $updated = IDLM_DB::update_lead($lead_id, array('lead_quality' => 'Unqualified'));
            if (!$updated) {
                wp_send_json_error(array('message' => IDLM_DB::get_last_validation_error() ?: 'The lead could not be closed.'));
            }
            IDLM_DB::log_activity($lead_id, 'qualification_decision', 'Pre-sales decision: Unqualified — Unable to Contact.');
            wp_send_json_success(array('message' => 'Lead closed as Unqualified — Unable to Contact.'));
        }
        wp_send_json_error(array('message' => 'Select a valid lead decision.'));
    }

    /**
     * AJAX Endpoint: Add comment notes in drawer
     */
    public static function ajax_save_activity_comment() {
        return IDLM_Admin_Activity_Controller::ajax_save_activity_comment();
    }

    /**
     * AJAX Endpoint: Edit activity comment
     */
    public static function ajax_edit_activity_comment() {
        return IDLM_Admin_Activity_Controller::ajax_edit_activity_comment();
    }

    /**
     * AJAX Endpoint: Delete activity comment
     */
    public static function ajax_delete_activity_comment() {
        return IDLM_Admin_Activity_Controller::ajax_delete_activity_comment();
    }

    /**
     * AJAX Endpoint: Export Google Ads Offline Conversions CSV
     */
    public static function ajax_export_google_conversions() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access');
        }

        if (class_exists('IDLM_CAPI')) {
            IDLM_CAPI::generate_google_ads_conversions_csv_response();
        }
        exit;
    }

    /**
     * AJAX Endpoint: Export leads database to CSV format
     */
    public static function ajax_export_leads() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized access. Only administrators can export leads.');
        }

        // Clear any previous output buffers to avoid corrupting headers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        // Check rep restriction
        $rep_id = IDLM_Lead_Access_Policy::restriction_user_id();

        $query = "SELECT id, first_name, last_name, email, phone, budget, project_timeline, property_location, source, referrer_details, property_type, num_rooms, floor_plan_received, floor_plan_file, quote_shared, quote_value, quote_file, stage, lost_reason, call_status, callback_datetime, lead_quality, utm_source, utm_medium, utm_campaign, utm_adgroup, utm_term, utm_content, gclid, gbraid, wbraid, fbclid, fb_lead_id, li_fat_id, landing_page, conversion_time, created_at, assigned_at, handoff_at, updated_at FROM {$table_leads}";
        $where = array();
        $params = array();

        if ($rep_id) {
            $where[] = "assigned_rep_id = %d";
            $params[] = $rep_id;
        }

        // If bulk list is provided
        if (isset($_GET['ids'])) {
            if (!is_string($_GET['ids'])) { wp_die('Export is limited to 10,000 selected leads. Narrow your selection.', '', array('response'=>400)); }
            $raw_ids = sanitize_text_field(wp_unslash($_GET['ids']));
            if (strlen($raw_ids) > 210000 || substr_count($raw_ids, ',') >= 10000) { wp_die('Export is limited to 10,000 selected leads. Narrow your selection.', '', array('response'=>400)); }
            $ids = array_map('intval', explode(',', $raw_ids));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $where[] = "id IN ($placeholders)";
                $params = array_merge($params, $ids);
            }
        } else {
            // Apply other GET filters
            $date_columns = array(
                'assigned_at' => 'assigned_at', 'created_at' => 'created_at', 'handoff_at' => 'handoff_at',
                'updated_at' => 'updated_at', 'qualified_at' => 'qualified_at', 'conversion_time' => 'conversion_time',
            );
            $get_date_basis = isset($_GET['date_basis']) ? sanitize_key(wp_unslash($_GET['date_basis'])) : 'assigned_at';
            $date_column = $date_columns[$get_date_basis] ?? 'assigned_at';
            $get_date_range = isset($_GET['date_range']) ? sanitize_key(wp_unslash($_GET['date_range'])) : '';
            if (!empty($get_date_range)) {
                $timezone = wp_timezone();
                $now = new DateTime('now', $timezone);
                if ($get_date_range === 'today') {
                    $where[] = "{$date_column} >= %s";
                    $params[] = $now->format('Y-m-d 00:00:00');
                } elseif ($get_date_range === 'yesterday') {
                    $yesterday = clone $now;
                    $yesterday->modify('-1 day');
                    $where[] = "{$date_column} >= %s AND {$date_column} <= %s";
                    $params[] = $yesterday->format('Y-m-d 00:00:00');
                    $params[] = $yesterday->format('Y-m-d 23:59:59');
                } elseif ($get_date_range === 'week') {
                    $start = clone $now;
                    $start->modify('monday this week');
                    $where[] = "{$date_column} >= %s";
                    $params[] = $start->format('Y-m-d 00:00:00');
                } elseif ($get_date_range === 'month') {
                    $where[] = "{$date_column} >= %s";
                    $params[] = $now->format('Y-m-01 00:00:00');
                } elseif ($get_date_range === 'last_7_days') {
                    $start = clone $now;
                    $start->modify('-6 days');
                    $where[] = "{$date_column} >= %s";
                    $params[] = $start->format('Y-m-d 00:00:00');
                } elseif ($get_date_range === 'last_30_days') {
                    $start = clone $now;
                    $start->modify('-29 days');
                    $where[] = "{$date_column} >= %s";
                    $params[] = $start->format('Y-m-d 00:00:00');
                } elseif ($get_date_range === 'last_month') {
                    $start = clone $now;
                    $start->modify('first day of last month');
                    $end = clone $now;
                    $end->modify('last day of last month');
                    $where[] = "{$date_column} >= %s AND {$date_column} <= %s";
                    $params[] = $start->format('Y-m-d 00:00:00');
                    $params[] = $end->format('Y-m-d 23:59:59');
                } elseif ($get_date_range === 'year' || $get_date_range === 'this_year') {
                    $where[] = "{$date_column} >= %s";
                    $params[] = $now->format('Y-01-01 00:00:00');
                }
            }

            $get_source          = isset($_GET['source']) ? sanitize_text_field(wp_unslash($_GET['source'])) : '';
            $get_rep_id          = isset($_GET['rep_id']) ? sanitize_text_field(wp_unslash($_GET['rep_id'])) : '';
            $get_stage           = isset($_GET['stage']) ? sanitize_text_field(wp_unslash($_GET['stage'])) : '';
            $get_call_status     = isset($_GET['call_status']) ? sanitize_text_field(wp_unslash($_GET['call_status'])) : '';
            $get_quality         = isset($_GET['quality']) ? sanitize_text_field(wp_unslash($_GET['quality'])) : '';
            $get_followup_filter = isset($_GET['followup_filter']) ? sanitize_text_field(wp_unslash($_GET['followup_filter'])) : '';
            $get_search          = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';

            if (!empty($get_source)) {
                $where[] = "source = %s";
                $params[] = $get_source;
            }

            if ($get_rep_id !== '') {
                $where[] = "assigned_rep_id = %d";
                $params[] = intval($get_rep_id);
            }

            if (!empty($get_stage)) {
                $where[] = "stage = %s";
                $params[] = $get_stage;
            }

            if (!empty($get_call_status)) {
                $where[] = "call_status = %s";
                $params[] = $get_call_status;
            }

            if (!empty($get_quality)) {
                if ('Pending' === $get_quality) {
                    $where[] = "(lead_quality = '' OR lead_quality IS NULL)";
                } else {
                    $where[] = "lead_quality = %s";
                    $params[] = $get_quality;
                }
            }

            if (!empty($get_followup_filter) && $get_followup_filter === 'pending_calls') {
                $statuses = array('Not Called');
                $rules = self::get_reminder_rules();
                if (is_array($rules)) {
                    foreach ($rules as $rule) {
                        if (!empty($rule['status'])) {
                            $statuses[] = $rule['status'];
                        }
                    }
                }
                $statuses = array_unique($statuses);
                $in_clause = implode("','", array_map('esc_sql', $statuses));
                $where[] = "call_status IN ('{$in_clause}')";
            } elseif (!empty($get_followup_filter) && $get_followup_filter === 'whatsapp_received') {
                $where[] = IDLM_Admin_Lead_Notifications::whatsapp_exists_sql('', false);
            } elseif (!empty($get_followup_filter) && $get_followup_filter === 'unread_whatsapp') {
                $where[] = IDLM_Admin_Lead_Notifications::whatsapp_exists_sql('', true);
            } elseif (!empty($get_followup_filter) && $get_followup_filter === 'overdue') {
                $rules = self::get_reminder_rules();
                $or_clauses = array();
                $now_mysql = current_time('mysql');
                
                foreach ($rules as $rule) {
                    $status = $rule['status'];
                    $hours = intval($rule['hours']);
                    
                    if ($status === 'Call Back Requested') {
                        $or_clauses[] = $wpdb->prepare(
                            "(call_status = %s AND (
                                (callback_datetime IS NOT NULL AND callback_datetime < DATE_SUB(%s, INTERVAL %d HOUR)) OR
                                (callback_datetime IS NULL AND COALESCE(updated_at, created_at) < DATE_SUB(%s, INTERVAL %d HOUR))
                            ))",
                            $status, $now_mysql, $hours, $now_mysql, $hours
                        );
                    } else {
                        $or_clauses[] = $wpdb->prepare(
                            "(call_status = %s AND COALESCE(updated_at, created_at) < DATE_SUB(%s, INTERVAL %d HOUR))",
                            $status, $now_mysql, $hours
                        );
                    }
                }
                
                if (!empty($or_clauses)) {
                    $where[] = "(" . implode(" OR ", $or_clauses) . ")";
                } else {
                    $where[] = "1=0";
                }
            } elseif (!empty($get_followup_filter) && $get_followup_filter === 'stale_updates') {
                $cutoff = IDLM_Admin_Lead_Notifications::get_stale_lead_cutoff();
                $where[] = $wpdb->prepare(
                    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0188: ajax_export_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
                    "assigned_rep_id > 0 AND is_duplicate = 0 AND stage NOT IN ('Won', 'Lost') AND COALESCE(assigned_at, created_at) < %s AND " . IDLM_Admin_Lead_Notifications::get_no_recent_assigned_rep_action_sql($cutoff),
                    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
                    $cutoff
                );
            }

            if (!empty($get_search)) {
                $search = '%' . $wpdb->esc_like($get_search) . '%';
                $where[] = "(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)";
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
                $params[] = $search;
            }
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        if (!empty($params)) {
            // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0189: ajax_export_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            $prepared_query = $wpdb->prepare($query, $params);
            // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
        } else { $prepared_query = $query; }

        // Stage the entire bounded export before sending attachment headers.
        // A later read/write failure must not look like a successful partial CSV.
        $output = tmpfile();
        if (!$output) { wp_die('Unable to prepare the export. Retry shortly.', '', array('response'=>503)); }
        $deadline = microtime(true) + 20;
        try {
            $header_written = fputcsv($output, array('ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Budget', 'Project Timeline', 'Property Location / Pincode', 'Source', 'Referrer Details', 'Property Type', 'Rooms', 'Floor Plan', 'Floor Plan File URL', 'Quote Shared', 'Quote Value', 'Quote File URL', 'Stage', 'Lost Reason', 'Call Status', 'Callback Scheduled', 'Quality', 'UTM Source', 'UTM Medium', 'UTM Campaign', 'UTM Adgroup', 'UTM Term', 'UTM Content', 'GCLID', 'GBRAID', 'WBRAID', 'FBCLID', 'Meta Lead ID', 'LI FAT ID', 'Landing Page', 'Conversion Time', 'Created At', 'Assigned At', 'Sales Handoff At', 'Updated At'), ',', '"', '');
            if (false === $header_written) { throw new RuntimeException('Unable to write the export. Retry shortly.'); }

            foreach (self::export_lead_rows($prepared_query, $deadline) as $l) {
                $row = array(
                    $l->id,
                    $l->first_name,
                    $l->last_name,
                    $l->email,
                    $l->phone,
                    $l->budget,
                    $l->project_timeline,
                    $l->property_location,
                    $l->source,
                    $l->referrer_details,
                    $l->property_type,
                    $l->num_rooms,
                    $l->floor_plan_received ? 'Yes' : 'No',
                    isset($l->floor_plan_file) ? $l->floor_plan_file : '',
                    $l->quote_shared ? 'Yes' : 'No',
                    isset($l->quote_value) ? $l->quote_value : 0,
                    isset($l->quote_file) ? $l->quote_file : '',
                    $l->stage,
                    isset($l->lost_reason) ? $l->lost_reason : '',
                    $l->call_status,
                    $l->callback_datetime,
                    $l->lead_quality,
                    $l->utm_source,
                    $l->utm_medium,
                    $l->utm_campaign,
                    $l->utm_adgroup,
                    $l->utm_term,
                    $l->utm_content,
                    $l->gclid,
                    isset($l->gbraid) ? $l->gbraid : '',
                    isset($l->wbraid) ? $l->wbraid : '',
                    $l->fbclid,
                    $l->fb_lead_id,
                    $l->li_fat_id,
                    isset($l->landing_page) ? $l->landing_page : '',
                    isset($l->conversion_time) ? $l->conversion_time : '',
                    $l->created_at,
                    isset($l->assigned_at) ? $l->assigned_at : '',
                    isset($l->handoff_at) ? $l->handoff_at : '',
                    $l->updated_at
                );
                // Only ID/decimal quote value are numbers; phone/budget/rooms are text.
                foreach ($row as $column => $value) {
                    if (0 !== $column && 15 !== $column) {
                        $row[$column] = IDLM_Quotation_Exporter::csv_text($value);
                    }
                }
                // Explicit escape preserves quotes/backslashes without PHP 8.4 warnings.
                if (false === fputcsv($output, $row, ',', '"', '') || ftell($output) > 32 * 1024 * 1024) { throw new RuntimeException('Export exceeds the file limit. Narrow your filters and retry.'); }
            }

        } catch (Throwable $error) {
            // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0041: ajax_export_leads(): the registered authenticated handler checks the shared admin nonce/capabilities and live object policy; IDs are normalized/bounded, rep-studio maps filtered to eligible IDs, signed cursor decoded by Task_Queue_Service and uploads validated by Private_Files; CSV streams are bounded and staged before headers.
            fclose($output);
            // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            wp_die(esc_html($error->getMessage()), '', array('response'=>503));
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=interior-design-leads-' . gmdate('Y-m-d') . '.csv');
        nocache_headers();
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0042: ajax_export_leads(): the registered authenticated handler checks the shared admin nonce/capabilities and live object policy; IDs are normalized/bounded, rep-studio maps filtered to eligible IDs, signed cursor decoded by Task_Queue_Service and uploads validated by Private_Files; CSV streams are bounded and staged before headers.
        rewind($output); fpassthru($output); fclose($output);
        // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        exit;
    }

    /** Read 100 rows per batch, at most 10,000 rows/file and 20 seconds of app work.
     * ID keysets exclude new higher IDs and avoid deep OFFSET scans. */
    private static function export_lead_rows($query, $deadline) {
        global $wpdb;
        $last = null; $count = 0;
        do {
            if (microtime(true) > $deadline) { throw new RuntimeException('Export took too long. Narrow your filters and retry.'); }
            $page_query = $query;
            if (null !== $last) { $page_query .= (strpos($query, ' WHERE ') !== false ? ' AND ' : ' WHERE ') . $wpdb->prepare('id < %d', $last); }
            $suppressed = $wpdb->suppress_errors(true);
            // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- REL007-SQL-0190: export_lead_rows(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
            try { $rows = $wpdb->get_results($page_query . ' ORDER BY id DESC LIMIT 100'); }
            // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
            finally { $wpdb->suppress_errors($suppressed); }
            if ($wpdb->last_error) { throw new RuntimeException('Unable to read the export. Retry shortly.'); }
            foreach ($rows as $row) {
                if (++$count > 10000) { throw new RuntimeException('Export is limited to 10,000 leads. Narrow your filters and retry.'); }
                $last = (int) $row->id;
                yield $row;
            }
        } while (count($rows) === 100);
        if (microtime(true) > $deadline) { throw new RuntimeException('Export took too long. Narrow your filters and retry.'); }
    }

    /**
     * AJAX Endpoint: Create manual lead in backend popup
     */
    public static function ajax_add_lead_manual() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $form_data_raw = isset($_POST['form_data']) ? wp_unslash($_POST['form_data']) : '';
        parse_str($form_data_raw, $form_fields);
        $first_name = isset($form_fields['first_name']) ? sanitize_text_field($form_fields['first_name']) : '';
        $email = isset($form_fields['email']) ? sanitize_email($form_fields['email']) : '';
        $phone = isset($form_fields['phone']) ? sanitize_text_field($form_fields['phone']) : '';
        if (empty($first_name) || empty($email) || empty($phone)) {
            wp_send_json_error(array('message' => 'First Name, Email Address, and Phone are required.'));
        }
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }
        $lead_data = array(
            'first_name' => $first_name,
            'last_name' => sanitize_text_field($form_fields['last_name'] ?? ''),
            'email' => $email,
            'phone' => $phone,
            'budget' => isset($form_fields['budget']) ? IDLM_Integrations::normalize_budget(sanitize_text_field($form_fields['budget'])) : '',
            'project_timeline' => isset($form_fields['project_timeline']) ? sanitize_text_field($form_fields['project_timeline']) : '',
            'property_location' => isset($form_fields['property_location']) ? sanitize_text_field($form_fields['property_location']) : '',
            'source' => sanitize_text_field($form_fields['source'] ?? ''),
            'property_type' => sanitize_text_field($form_fields['property_type'] ?? ''),
            'num_rooms' => IDLM_Integrations::normalize_num_rooms(sanitize_text_field($form_fields['num_rooms'] ?? '')),
            'preferred_studio' => sanitize_text_field($form_fields['preferred_studio'] ?? ''),
            'lead_quality' => sanitize_text_field($form_fields['lead_quality'] ?? ''),
            'stage' => 'New',
            'call_status' => 'Not Called',
            'assigned_rep_id' => get_current_user_id()
        );
        if ($lead_data['source'] === 'Referral') {
            $lead_data['referrer_details'] = sanitize_textarea_field($form_fields['referrer_details'] ?? '');
        }
        // Capture local UTMs if present in admin browser session
        $captured = IDLM_Integrations::get_captured_attribution();
        foreach ($captured as $k => $v) {
            if (!empty($v)) {
                $lead_data[$k] = $v;
            }
        }
        $result = IDLM_DB::upsert_inbound_lead($lead_data, 'manual', $lead_data);
        if ($result) {
            if (!empty($result['requires_duplicate_review'])) {
                wp_send_json_success(array(
                    'message' => 'Submission retained for identity review; no new lead or task was created.',
                    'pending_calls_count' => self::get_pending_calls_count(),
                ));
            }
            $lead_id = absint($result['lead_id']);
            $created = !empty($result['created']);
            wp_send_json_success(array(
                'message' => $created ? 'Manual Lead added successfully!' : 'Existing lead updated with the new submission data.',
                'pending_calls_count' => self::get_pending_calls_count()
            ));
        }
        $validation_error = IDLM_DB::get_last_validation_error();
        wp_send_json_error(array('message' => $validation_error ?: 'Failed to create lead. Database write error.'));
    }

    /**
     * AJAX Endpoint: Manual sync triggers
     */
    public static function ajax_trigger_manual_audience_sync() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        // Trigger the batch sync immediately
        $result = IDLM_Audiences::run_batch_sync(true);
        if (is_wp_error($result)) { wp_send_json_error(array('message'=>$result->get_error_message()), 409); }
        wp_send_json_success(array('message'=>'Selected audience work processed. Failed or uncertain work remains queued; Google mapping does not confirm a provider pull.', 'outcomes'=>$result));
    }

    /**
     * AJAX Endpoint: Test platform connection and credentials
     */
    public static function ajax_test_api_connection() {
        return IDLM_Admin_Connection_Controller::ajax_test_api_connection();
    }

    /**
     * AJAX Endpoint: Bulk retry Conversions API events
     */
    public static function ajax_retry_capi_events() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0043: ajax_retry_capi_events(): the registered authenticated handler checks the shared admin nonce/capabilities and live object policy; IDs are normalized/bounded, rep-studio maps filtered to eligible IDs, signed cursor decoded by Task_Queue_Service and uploads validated by Private_Files; CSV streams are bounded and staged before headers.
        $raw_ids = isset($_POST['log_ids']) && is_array($_POST['log_ids']) ? wp_unslash($_POST['log_ids']) : array();
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (!$raw_ids || count($raw_ids) > 50) { wp_send_json_error(array('message'=>'Select between 1 and 50 conversion logs.')); }
        $log_ids = array();
        foreach ($raw_ids as $id) {
            if (!is_scalar($id) || !preg_match('/^[1-9][0-9]{0,18}$/D', (string)$id) || (string)(int)$id !== (string)$id) { wp_send_json_error(array('message'=>'Invalid conversion log selection.')); }
            $log_ids[] = (int)$id;
        }
        $log_ids = array_values(array_unique($log_ids));

        $success_count = 0;
        $failed_count = 0;
        $errors = array();
        $attempts = array();

        foreach ($log_ids as $log_id) {
            $result = IDLM_CAPI::retry_capi_log($log_id);
            $attempts[] = array('source_log_id'=>$log_id, 'log_id'=>$result['log_id'], 'attempt_id'=>$result['attempt_id'], 'success'=>$result['success']);
            if ($result['success']) {
                $success_count++;
            } else {
                $failed_count++;
                $errors[] = "Log #{$log_id}: " . $result['message'];
            }
        }

        $msg = sprintf('Completed bulk CAPI resend: %d succeeded, %d failed.', $success_count, $failed_count);
        if (!empty($errors)) {
            $msg .= "\n\nErrors:\n" . implode("\n", $errors);
        }

        wp_send_json_success(array('message' => $msg, 'attempts'=>$attempts));
    }

    /**
     * AJAX Endpoint: Log Javascript errors to telemetry
     */
    public static function ajax_log_js_error() {
        return IDLM_Admin_Telemetry_Controller::ajax_log_js_error();
    }

    /**
     * AJAX Endpoint: Clear telemetry logs table
     */
    public static function ajax_clear_telemetry_logs() {
        return IDLM_Admin_Telemetry_Controller::ajax_clear_telemetry_logs();
    }

    /**
     * AJAX/GET Endpoint: Download diagnostic report
     */
    public static function ajax_download_diagnostic_report() {
        return IDLM_Admin_Telemetry_Controller::ajax_download_diagnostic_report();
    }

    /**
     * AJAX POST Handler: Save Lead Routing settings from the frontend workspace modal
     */
    public static function ajax_save_routing_settings() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access Denied');
        }
        $previous_presales_targets = IDLM_DB::get_presales_outreach_targets();

        // Save strategy
        $assignment_mode = isset($_POST['assignment_mode']) ? sanitize_key(wp_unslash($_POST['assignment_mode'])) : 'manual';
        if (!in_array($assignment_mode, array('manual', 'pre_sales', 'round_robin', 'source_based', 'studio_based'), true)) {
            $assignment_mode = 'manual';
        }
        update_option('idlm_assignment_mode', $assignment_mode);

        // Save active reps
        $active_reps = (!empty($_POST['active_reps']) && is_array($_POST['active_reps'])) ? array_map('intval', wp_unslash($_POST['active_reps'])) : array();
        update_option('idlm_active_assignment_reps', $active_reps);
        $posted_pre_sales = (!empty($_POST['active_pre_sales_reps']) && is_array($_POST['active_pre_sales_reps'])) ? array_map('absint', wp_unslash($_POST['active_pre_sales_reps'])) : array();
        $pre_sales_role_ids = array_map('absint', get_users(array('role' => 'idlm_pre_sales', 'fields' => 'ID')));
        update_option('idlm_active_pre_sales_reps', array_values(array_intersect($posted_pre_sales, $pre_sales_role_ids)));

        // Save the studio location assigned to each representative.
        $allowed_studios = get_option('idlm_custom_studios', array('Downtown Studio', 'North Studio', 'South Studio'));
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0044: ajax_save_routing_settings(): the registered authenticated handler checks the shared admin nonce/capabilities and live object policy; IDs are normalized/bounded, rep-studio maps filtered to eligible IDs, signed cursor decoded by Task_Queue_Service and uploads validated by Private_Files; CSV streams are bounded and staged before headers.
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
        // Hidden source-map controls are disabled in the accessible modal. Retain the
        // saved mappings unless the source-based strategy is actively being edited.
        if ('source_based' === $assignment_mode) {
            $source_maps = array();
            foreach ($sources as $src) {
                $field_name = 'source_map_' . str_replace(array(' ', '/', '-'), '_', strtolower($src));
                if (isset($_POST[$field_name])) {
                    $source_maps[$src] = intval(wp_unslash($_POST[$field_name]));
                }
            }
            update_option('idlm_source_assignment_mappings', $source_maps);
        }

        // Save fallback
        update_option('idlm_fallback_assignment_enabled', isset($_POST['fallback_assignment_enabled']) ? '1' : '0');
        // Conditional controls are intentionally disabled while their safeguard is off.
        // Preserve the previously configured values until that safeguard is enabled again.
        if (isset($_POST['fallback_assignment_controls_active'])) {
            update_option('idlm_fallback_assignment_hours', isset($_POST['fallback_assignment_hours']) ? max(1, intval(wp_unslash($_POST['fallback_assignment_hours']))) : 24);
        }
        $qualified_fallback_was_enabled = get_option('idlm_qualified_fallback_enabled', '0');
        $qualified_fallback_is_enabled = isset($_POST['qualified_fallback_enabled']) ? '1' : '0';
        update_option('idlm_qualified_fallback_enabled', $qualified_fallback_is_enabled);
        if ('1' === $qualified_fallback_is_enabled && '1' !== $qualified_fallback_was_enabled) {
            update_option('idlm_qualified_fallback_enabled_at', current_time('mysql'));
        }
        if (isset($_POST['qualified_fallback_controls_active'])) {
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
        }
        update_option('idlm_routing_exception_notifications_enabled', isset($_POST['routing_exception_notifications_enabled']) ? '1' : '0');
        update_option('idlm_max_active_leads_per_rep', isset($_POST['max_active_leads_per_rep']) ? min(10000, max(0, absint(wp_unslash($_POST['max_active_leads_per_rep'])))) : 30);
        update_option('idlm_working_hours_start', isset($_POST['working_hours_start']) ? sanitize_text_field(wp_unslash($_POST['working_hours_start'])) : '09:00');
        update_option('idlm_working_hours_end', isset($_POST['working_hours_end']) ? sanitize_text_field(wp_unslash($_POST['working_hours_end'])) : '18:00');
        update_option('idlm_presales_calls_per_day', isset($_POST['presales_calls_per_day']) ? min(20, max(1, intval(wp_unslash($_POST['presales_calls_per_day'])))) : 2);
        update_option('idlm_presales_whatsapp_per_day', isset($_POST['presales_whatsapp_per_day']) ? min(20, max(0, intval(wp_unslash($_POST['presales_whatsapp_per_day'])))) : 1);
        $working_days = (!empty($_POST['working_days']) && is_array($_POST['working_days'])) ? array_map('intval', wp_unslash($_POST['working_days'])) : array();
        update_option('idlm_working_days', $working_days);
        IDLM_Settings::sync_routing_from_legacy();
        if ($previous_presales_targets !== IDLM_DB::get_presales_outreach_targets()) {
            IDLM_DB::refresh_presales_task_titles();
        }

        wp_send_json_success('Routing settings saved successfully!');
    }

    /**
     * Run both fallback flows immediately for an administrator.
     */
    public static function ajax_run_fallback_now() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied.'));
        }

        IDLM_Integrations::run_assignment_fallback();
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0192: ajax_run_fallback_now(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        $exception_count = intval($wpdb->get_var(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads
             WHERE handoff_status = 'exception' AND stage NOT IN ('Won', 'Lost') AND is_duplicate = 0"
        ));
        wp_send_json_success(array(
            'message' => 'Fallback routing completed.',
            'completed_at' => get_option('idlm_fallback_last_completed_at', current_time('mysql')),
            'exception_count' => $exception_count,
        ));
    }

    /**
     * AJAX Endpoint: Fetch all lead IDs with "No Answer" call status
     */
    /**
     * AJAX Endpoint: Fetch all lead IDs matching a specific mapping trigger criteria
     */
    public static function ajax_get_mapped_past_leads() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access Denied');
        }

        $allowed_types = array('call_status', 'stage', 'lead_quality');
        $trigger_type = isset($_POST['trigger_type']) ? sanitize_key($_POST['trigger_type']) : '';
        if (!in_array($trigger_type, $allowed_types)) {
            wp_send_json_error('Invalid trigger type');
        }

        $trigger_value = isset($_POST['trigger_value']) ? sanitize_text_field(wp_unslash($_POST['trigger_value'])) : '';

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0193: ajax_get_mapped_past_leads(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $lead_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table_leads} WHERE {$trigger_type} = %s ORDER BY id ASC",
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $trigger_value
        ));
        
        wp_send_json_success(array('lead_ids' => array_map('intval', $lead_ids)));
    }

    /**
     * AJAX Endpoint: Sync a batch of leads to Interakt for a specific event with throttling
     */
    public static function ajax_sync_mapped_past_batch() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Access Denied');
        }
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0045: ajax_sync_mapped_past_batch(): the registered authenticated handler checks the shared admin nonce/capabilities and live object policy; IDs are normalized/bounded, rep-studio maps filtered to eligible IDs, signed cursor decoded by Task_Queue_Service and uploads validated by Private_Files; CSV streams are bounded and staged before headers.
        $raw_ids = isset($_POST['lead_ids']) ? wp_unslash($_POST['lead_ids']) : array();
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (!is_array($raw_ids) || !$raw_ids || count($raw_ids) > 50 || count(array_filter($raw_ids, 'is_scalar')) !== count($raw_ids)) {
            wp_send_json_error('Select between 1 and 50 leads per batch.');
        }
        $event = isset($_POST['event_name']) && is_scalar($_POST['event_name']) ? sanitize_text_field(wp_unslash($_POST['event_name'])) : '';
        $request_id = isset($_POST['request_id']) && is_scalar($_POST['request_id']) ? sanitize_text_field(wp_unslash($_POST['request_id'])) : '';
        if (!preg_match('/^[a-zA-Z0-9_-]{8,80}$/D', $request_id)) {
            wp_send_json_error('A valid sync request ID is required. Refresh the page and retry.');
        }
        if (!IDLM_Interakt_Batch::allowed_event($event)) {
            wp_send_json_error('Save a valid Interakt event mapping before syncing.');
        }
        if (!IDLM_Settings::notification_enabled('idlm_whatsapp_notifications_enabled') || '1' !== get_option('idlm_interakt_enabled', '0') || !IDLM_Secret_Store::get('idlm_interakt_api_key', '', true)) {
            wp_send_json_error('Interakt automation is disabled or API key is missing.');
        }
        $queued = 0;
        $existing = 0;
        $failed = 0;
        foreach (array_values(array_unique(array_map('absint', $raw_ids))) as $index => $lead_id) {
            $result = IDLM_Interakt_Batch::enqueue($lead_id, $event, $request_id, $index * 2);
            if ('queued' === $result) { $queued++; }
            elseif ('existing' === $result) { $existing++; }
            else { $failed++; }
        }
        wp_send_json_success(array('queued_count' => $queued, 'existing_count' => $existing, 'failure_count' => $failed));
    }

    /**
     * AJAX Endpoint: Bulk operations from Leads Table
     */
    public static function ajax_bulk_action() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $ids = (isset($_POST['ids']) && is_array($_POST['ids'])) ? array_map('intval', wp_unslash($_POST['ids'])) : array();
        $action = isset($_POST['bulk_action']) ? sanitize_key(wp_unslash($_POST['bulk_action'])) : '';
        $lost_remark = isset($_POST['lost_remark']) ? sanitize_textarea_field(wp_unslash($_POST['lost_remark'])) : '';
        $lost_reason = isset($_POST['lost_reason']) ? sanitize_text_field(wp_unslash($_POST['lost_reason'])) : '';

        if (empty($ids) || empty($action)) {
            wp_send_json_error(array('message' => 'No items or action selected.'));
        }

        foreach ($ids as $lead_id) {
            if (!self::current_user_can_access_lead($lead_id)) {
                wp_send_json_error(array('message' => 'Bulk actions can include only leads assigned to you.'));
            }
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        switch ($action) {
            case 'bulk_assign':
                if (!self::current_user_can_assign_leads()) {
                    wp_send_json_error(array('message' => 'Only a sales manager or administrator can reassign leads.'));
                }
                $rep_id = isset($_POST['rep_id']) ? intval(wp_unslash($_POST['rep_id'])) : 0;
                if ($rep_id >= 0 && self::is_assignable_rep($rep_id)) {
                    // Each canonical write admits only its positive active-workload delta.
                    $failed = array();
                    foreach ($ids as $id) {
                        if (false === IDLM_DB::update_lead($id, array('assigned_rep_id' => $rep_id))) {
                            $failed[$id] = IDLM_DB::get_last_validation_error() ?: 'Update failed.';
                        }
                    }
                    if ($failed) {
                        wp_send_json_error(array('message' => self::format_bulk_failures('Assignment', $failed)));
                    }
                    wp_send_json_success(array('message' => 'Successfully reassigned selected leads.'));
                }
                break;

            case 'bulk_stage':
                $stage = isset($_POST['stage']) ? sanitize_text_field(wp_unslash($_POST['stage'])) : '';
                if (!empty($stage)) {
                    if (self::current_user_is_pre_sales() && !in_array($stage, array('New', 'Contacted', 'Qualified'), true)) {
                        wp_send_json_error(array('message' => 'Pre-sales users can progress leads only through the Qualified stage.'));
                    }
                    if ('Lost' === $stage) {
                        $allowed_lost_reasons = get_option('idlm_custom_lost_reasons', array('Budget Too High', 'Lost to Competitor', 'Project Postponed', 'Unable to Contact', 'Location or Service Unavailable', 'Timeline Mismatch', 'Client Not Interested', 'Duplicate or Invalid Lead', 'Other'));
                        if (!in_array($lost_reason, $allowed_lost_reasons, true)) {
                            wp_send_json_error(array('message' => 'Please select a valid lost reason.'));
                        }
                        if ('' === trim($lost_remark)) {
                            wp_send_json_error(array('message' => 'A lost remark is required before moving leads to Lost.'));
                        }
                    }
                    $failed = array();
                    foreach ($ids as $id) {
                        $stage_data = array('stage' => $stage);
                        if ('Lost' === $stage) {
                            $stage_data['lost_reason'] = $lost_reason;
                            $stage_data['lost_remark'] = $lost_remark;
                        }
                        if (false === IDLM_DB::update_lead($id, $stage_data)) {
                            $failed[$id] = IDLM_DB::get_last_validation_error() ?: 'Update failed.';
                        }
                    }
                    if ($failed) {
                        wp_send_json_error(array('message' => self::format_bulk_failures('Stage update', $failed)));
                    }
                    wp_send_json_success(array('message' => 'Successfully updated stages for selected leads.'));
                }
                break;

            case 'bulk_unqualified':
                foreach ($ids as $id) {
                    $compliance = IDLM_DB::get_followup_compliance($id);
                    if (!$compliance['eligible']) {
                        wp_send_json_error(array('message' => sprintf('Lead #%d cannot be marked Unqualified: %s', $id, $compliance['message'])));
                    }
                }
                $failed = array();
                foreach ($ids as $id) {
                    if (false === IDLM_DB::update_lead($id, array('lead_quality' => 'Unqualified'))) {
                        $failed[$id] = IDLM_DB::get_last_validation_error() ?: 'Update failed.';
                    }
                }
                if ($failed) {
                    wp_send_json_error(array('message' => self::format_bulk_failures('Unqualified update', $failed)));
                }
                wp_send_json_success(array('message' => 'Successfully marked selected leads as Unqualified.'));
                break;

            case 'bulk_audience_cancel':
                $result = IDLM_Audience_Dispatch::cancel($ids);
                if (is_wp_error($result) || false === $result) { wp_send_json_error(array('message'=>is_wp_error($result) ? $result->get_error_message() : 'Audience work is busy. Retry shortly.'), 409); }
                wp_send_json_success(array('message'=>'Selected manual audience work removed. Unknown delivery outcomes remain blocked.', 'removed_count'=>$result['removed']));
                break;

            case 'bulk_audience_queue':
                $result = IDLM_Audience_Dispatch::enqueue($ids);
                if (is_wp_error($result) || false === $result) { wp_send_json_error(array('message'=>is_wp_error($result) ? $result->get_error_message() : 'Audience queue is busy. Retry shortly.'), 409); }
                wp_send_json_success(array('message'=>'Selected leads queued. Current audience exclusions apply at dispatch.', 'queued_count'=>$result['queued']));
                break;

            case 'bulk_delete':
                if (!current_user_can('manage_options')) {
                    wp_send_json_error(array('message' => 'Permission denied. Only administrators can delete leads.'));
                }
                $deleted = IDLM_Lead_Deletion_Service::delete_many($ids);
                if (is_wp_error($deleted)) {
                    wp_send_json_error(array('message' => $deleted->get_error_message()));
                }
                wp_send_json_success(array('message' => 'Successfully permanently deleted selected leads.'));
                break;
        }

        wp_send_json_error(array('message' => 'Failed to execute bulk action parameters.'));
    }

    /**
     * AJAX Endpoint: Server-side search, paginate and filter leads table
     */
    public static function ajax_get_leads_table() {
        return IDLM_Admin_Collection_Reads::ajax_get_leads_table();
    }

    /**
     * AJAX Endpoint: Fetch leads grouped by stage for Kanban board view
     */
    public static function ajax_get_kanban_leads() {
        return IDLM_Admin_Collection_Reads::ajax_get_kanban_leads();
    }

    /**
     * AJAX Endpoint: Fetch JSON dataset for dashboard widgets & ChartJS
     */
    public static function ajax_get_dashboard_stats() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $period = isset($_POST['period']) ? sanitize_key(wp_unslash($_POST['period'])) : 'this_month';
        $custom_start = isset($_POST['start_date']) ? sanitize_text_field(wp_unslash($_POST['start_date'])) : '';
        $custom_end = isset($_POST['end_date']) ? sanitize_text_field(wp_unslash($_POST['end_date'])) : '';

        $allowed_periods = array('today', 'yesterday', 'this_week', 'last_7_days', 'this_month', 'last_30_days', 'last_month', 'year', 'this_year', 'custom');
        if (!in_array($period, $allowed_periods, true)) {
            wp_send_json_error(array('message' => 'Invalid dashboard period.'));
        }
        if ('custom' === $period) {
            $start_date = DateTime::createFromFormat('!Y-m-d', $custom_start, wp_timezone());
            $start_errors = DateTime::getLastErrors();
            $start_is_valid = false !== $start_date && (false === $start_errors || (0 === $start_errors['warning_count'] && 0 === $start_errors['error_count']));
            $end_date = DateTime::createFromFormat('!Y-m-d', $custom_end, wp_timezone());
            $end_errors = DateTime::getLastErrors();
            $end_is_valid = false !== $end_date && (false === $end_errors || (0 === $end_errors['warning_count'] && 0 === $end_errors['error_count']));
            if (!$start_is_valid || !$end_is_valid || $start_date > $end_date) {
                wp_send_json_error(array('message' => 'Select a valid custom date range with the start date on or before the end date.'));
            }
        }

        if (!empty($_POST['investigate'])) {
            $records = IDLM_Dashboard::get_records(
                sanitize_key(wp_unslash($_POST['investigate'])),
                isset($_POST['value']) ? sanitize_text_field(wp_unslash($_POST['value'])) : '',
                $period, $custom_start, $custom_end,
                isset($_POST['records_page']) ? absint($_POST['records_page']) : 1
            );
            if (is_wp_error($records)) { wp_send_json_error(array('message' => $records->get_error_message())); }
            wp_send_json_success($records);
        }
        $filters = array('rep_id' => isset($_POST['rep_id']) ? absint($_POST['rep_id']) : 0, 'studio' => isset($_POST['studio']) ? sanitize_text_field(wp_unslash($_POST['studio'])) : '', 'source' => isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '', 'campaign' => isset($_POST['campaign']) ? sanitize_text_field(wp_unslash($_POST['campaign'])) : '');
        $stats = IDLM_Dashboard::get_stats($period, $custom_start, $custom_end, $filters);

        wp_send_json_success($stats);
    }

    /**
     * AJAX Endpoint: Save complete Lead details form changes
     */
    public static function ajax_save_lead_details() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $form_raw = isset($_POST['form_data']) && is_string($_POST['form_data']) ? wp_unslash($_POST['form_data']) : '';
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        parse_str($form_raw, $form_fields);

        foreach ($form_fields as $value) {
            if (!is_scalar($value)) {
                wp_send_json_error(array('message' => 'Invalid lead field value.'));
            }
        }

        $lead_id = isset($form_fields['lead_id']) ? intval($form_fields['lead_id']) : 0;
        if (!$lead_id) {
            wp_send_json_error(array('message' => 'Invalid Lead ID'));
        }
        if (!self::current_user_can_access_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You can update only leads assigned to you.'));
        }

        $floor_plan_files = IDLM_Admin_Presenter::normalize_floor_plan_files(
            isset($form_fields['floor_plan_files']) ? $form_fields['floor_plan_files'] : '',
            isset($form_fields['floor_plan_file']) ? $form_fields['floor_plan_file'] : ''
        );
        foreach (array('floor_plan' => $floor_plan_files, 'quote' => !empty($form_fields['quote_file']) ? array(esc_url_raw($form_fields['quote_file'])) : array()) as $purpose => $urls) {
            $binding = IDLM_Private_Files::validate_urls($lead_id, $urls, $purpose);
            if (is_wp_error($binding)) { wp_send_json_error(array('message' => $binding->get_error_message())); }
        }
        global $wpdb;
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0222: ajax_save_lead_details(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if (!$existing) {
            wp_send_json_error(array('message' => 'Lead not found.'));
        }
        $protected_fields = IDLM_Lead_Access_Policy::validate_detail_fields($form_fields, $existing);
        if (is_wp_error($protected_fields)) {
            wp_send_json_error(array('message' => $protected_fields->get_error_message()));
        }
        // Omitted controls must preserve stored values, including disabled role-specific fields.
        $submitted_fields = array_keys($form_fields);
        $form_fields = array_merge((array) $existing, $form_fields);
        $data = array(
            'first_name' => sanitize_text_field($form_fields['first_name']),
            'last_name'  => sanitize_text_field($form_fields['last_name']),
            'email'      => sanitize_email($form_fields['email']),
            'phone'      => sanitize_text_field($form_fields['phone']),
            'project_name' => isset($form_fields['project_name']) ? sanitize_text_field($form_fields['project_name']) : '',
            'budget'     => isset($form_fields['budget']) ? IDLM_Integrations::normalize_budget(sanitize_text_field($form_fields['budget'])) : '',
            'deal_value' => isset($form_fields['deal_value']) ? floatval($form_fields['deal_value']) : 0.00,
            'discount_percentage' => isset($form_fields['discount_percentage']) ? min(100, max(0, floatval($form_fields['discount_percentage']))) : 0.00,
            'project_timeline' => isset($form_fields['project_timeline']) ? sanitize_text_field($form_fields['project_timeline']) : '',
            'possession_date' => !empty($form_fields['possession_date']) ? sanitize_text_field($form_fields['possession_date']) : null,
            'property_location' => isset($form_fields['property_location']) ? sanitize_text_field($form_fields['property_location']) : '',
            'lead_quality' => sanitize_text_field($form_fields['lead_quality']),
            'property_type' => sanitize_text_field($form_fields['property_type']),
            'num_rooms' => IDLM_Integrations::normalize_num_rooms(sanitize_text_field($form_fields['num_rooms'])),
            'floor_plan_received' => intval($form_fields['floor_plan_received']),
            'floor_plan_file' => isset($floor_plan_files[0]) ? $floor_plan_files[0] : '',
            'floor_plan_files' => $floor_plan_files ? wp_json_encode($floor_plan_files) : null,
            'quote_shared' => intval($form_fields['quote_shared']),
            'quote_value' => isset($form_fields['quote_value']) ? max(0, round(floatval($form_fields['quote_value']), 2)) : 0.00,
            'quote_file' => isset($form_fields['quote_file']) ? esc_url_raw($form_fields['quote_file']) : '',
            'preferred_studio' => sanitize_text_field($form_fields['preferred_studio'])
        );

        foreach ($data as $field => $value) {
            if (in_array($field, $protected_fields, true) ||
                (!in_array($field, $submitted_fields, true) && !('floor_plan_file' === $field && in_array('floor_plan_files', $submitted_fields, true)) && !('floor_plan_files' === $field && in_array('floor_plan_file', $submitted_fields, true)))) {
                unset($data[$field]);
            }
        }

        // Once a lead has structured quotations, commercial summary fields are
        // maintained only by the quotation workflow and cannot be overwritten
        // by the legacy lead drawer.
        if (class_exists('IDLM_Quotations') && IDLM_Quotations::lead_has_quotes($lead_id)) {
            unset($data['quote_shared'], $data['quote_value'], $data['quote_file']);
        }

        if (isset($form_fields['referrer_details'])) {
            $data['referrer_details'] = sanitize_textarea_field($form_fields['referrer_details']);
        }
        if (isset($form_fields['callback_datetime'])) {
            $callback_dt = sanitize_text_field($form_fields['callback_datetime']);
            $data['callback_datetime'] = !empty($callback_dt) ? str_replace('T', ' ', $callback_dt) : null;
        }

        $updated = IDLM_DB::update_lead($lead_id, $data);
        if ($updated !== false) {
            wp_send_json_success(array(
                'message' => 'Lead details saved successfully!',
                'pending_calls_count' => self::get_pending_calls_count()
            ));
        }

        $validation_error = IDLM_DB::get_last_validation_error();
        wp_send_json_error(array('message' => $validation_error ?: 'Failed to save lead updates. Database error.'));
    }

    /**
     * AJAX Endpoint: Upload lead file directly via standard HTML5 file uploader
     */
    public static function ajax_upload_lead_file() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $lead_id = isset($_POST['lead_id']) ? absint($_POST['lead_id']) : 0;
        if (!$lead_id || !IDLM_Private_Files::allowed($lead_id, true)) {
            wp_send_json_error(array('message' => 'You can upload files only for leads currently assigned to you.'), 403);
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'), 400);
        }

        $purpose = isset($_POST['purpose']) ? sanitize_key(wp_unslash($_POST['purpose'])) : '';
        if ('quote' === $purpose && self::current_user_is_pre_sales()) {
            wp_send_json_error(array('message' => 'Pre-sales users cannot upload commercial quotations.'), 403);
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by the field-specific policy before WordPress handles it.
        $upload_validation = IDLM_Production_Security::validate_upload($_FILES['file'], $purpose);
        if (is_wp_error($upload_validation)) {
            wp_send_json_error(array('message' => $upload_validation->get_error_message()), 400);
        }

        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0046: ajax_upload_lead_file(): the registered authenticated handler checks the shared admin nonce/capabilities and live object policy; IDs are normalized/bounded, rep-studio maps filtered to eligible IDs, signed cursor decoded by Task_Queue_Service and uploads validated by Private_Files; CSV streams are bounded and staged before headers.
        $stored = IDLM_Private_Files::upload($_FILES['file'], $lead_id, $purpose);
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (is_wp_error($stored)) { wp_send_json_error(array('message' => $stored->get_error_message()), 'idlm_file_denied' === $stored->get_error_code() ? 403 : 500); }
        wp_send_json_success($stored);
    }

    /**
     * AJAX Endpoint: Get the total number of leads in the database for reset verification
     */
    public static function ajax_get_total_leads_count() {
        return IDLM_Admin_Collection_Reads::ajax_get_total_leads_count();
    }

    /**
     * AJAX Endpoint: Reset all CRM lead tables & analytics
     */
    public static function ajax_reset_crm_data() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied. Only administrators can reset data.'));
        }

        $input_count = isset($_POST['confirm_count']) ? sanitize_text_field(wp_unslash($_POST['confirm_count'])) : '';
        if ('' === $input_count || !ctype_digit($input_count)) {
            wp_send_json_error(array('message' => 'Verification failed. Enter the current lead count as a whole number.'));
        }

        $reset = IDLM_Lead_Deletion_Service::reset_all($input_count);
        if (is_wp_error($reset)) {
            wp_send_json_error(array('message' => $reset->get_error_message()), 500);
        }

        wp_send_json_success(array(
            'message' => 'CRM leads database and analytics have been successfully reset to starting state!',
            'manifest' => $reset,
        ));
    }

    /**
     * AJAX Endpoint: Delete a single lead and associated activity/CAPI logs (Admin only)
     */
    public static function ajax_delete_lead() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied. Only administrators can delete leads.'));
        }

        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        if ($lead_id <= 0) {
            wp_send_json_error(array('message' => 'Invalid lead ID.'));
        }

        $deleted = IDLM_Lead_Deletion_Service::delete($lead_id);
        if (is_wp_error($deleted)) {
            wp_send_json_error(array('message' => $deleted->get_error_message()));
        }
        wp_send_json_success(array('message' => 'Lead successfully permanently deleted.'));
    }

    /**
     * AJAX Endpoint: Import a batch of leads from CSV
     */
    public static function ajax_import_leads_batch() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied. Only administrators can import leads.'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $leads = isset($_POST['leads']) ? (array) wp_unslash($_POST['leads']) : array();
        if (!$leads && isset($_POST['leads_json'])) {
            // JSON avoids PHP max_input_vars truncating large non-mutating preflight requests.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $decoded_leads = json_decode(wp_unslash($_POST['leads_json']), true);
            $leads = is_array($decoded_leads) ? $decoded_leads : array();
        }
        if (empty($leads)) {
            wp_send_json_error(array('message' => 'No lead records received in this batch.'));
        }

        $default_source = isset($_POST['default_source']) ? sanitize_text_field(wp_unslash($_POST['default_source'])) : 'CSV Import';
        $default_stage = isset($_POST['default_stage']) ? sanitize_text_field(wp_unslash($_POST['default_stage'])) : 'New';
        $default_call_status = isset($_POST['default_call_status']) ? sanitize_text_field(wp_unslash($_POST['default_call_status'])) : 'Not Called';
        $default_studio = isset($_POST['default_studio']) ? sanitize_text_field(wp_unslash($_POST['default_studio'])) : '';
        $validate_only = !empty($_POST['validate_only']);
        $run_routing = !empty($_POST['run_routing']) ? true : false;
        $run_capi = !empty($_POST['run_capi']) ? true : false;

        $imported = 0;
        $duplicates = 0;
        $failed = 0;
        $errors = array();
        $projected = array('valid' => 0, 'duplicate' => 0, 'rejected' => 0);
        $seen_import_identities = array();

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        foreach ($leads as $lead_index => $lead) {
            $first_name = isset($lead['first_name']) ? sanitize_text_field($lead['first_name']) : '';
            $phone = isset($lead['phone']) ? sanitize_text_field($lead['phone']) : '';

            // First Name and Phone are required for database schema integrity
            if (empty($first_name) || empty($phone)) {
                $failed++;
                $projected['rejected']++;
                $errors[] = array(
                    'index' => absint($lead_index),
                    'message' => 'First name and phone number are required.',
                );
                continue;
            }

            $lead_data = array(
                'first_name' => $first_name,
                'last_name' => isset($lead['last_name']) ? sanitize_text_field($lead['last_name']) : '',
                'email' => isset($lead['email']) ? sanitize_email($lead['email']) : '',
                'phone' => $phone,
                'project_name' => isset($lead['project_name']) ? sanitize_text_field($lead['project_name']) : '',
                'budget' => isset($lead['budget']) ? IDLM_Integrations::normalize_budget(sanitize_text_field($lead['budget'])) : '',
                'deal_value' => isset($lead['deal_value']) ? max(0, round(floatval($lead['deal_value']), 2)) : 0,
                'project_timeline' => isset($lead['project_timeline']) ? sanitize_text_field($lead['project_timeline']) : '',
                'possession_date' => !empty($lead['possession_date']) ? sanitize_text_field($lead['possession_date']) : null,
                'property_location' => isset($lead['property_location']) ? sanitize_text_field($lead['property_location']) : '',
                'source' => !empty($lead['source']) ? sanitize_text_field($lead['source']) : $default_source,
                'property_type' => isset($lead['property_type']) ? sanitize_text_field($lead['property_type']) : '',
                'num_rooms' => isset($lead['num_rooms']) ? IDLM_Integrations::normalize_num_rooms(sanitize_text_field($lead['num_rooms'])) : '',
                'preferred_studio' => !empty($lead['preferred_studio']) ? sanitize_text_field($lead['preferred_studio']) : $default_studio,
                'lead_quality' => isset($lead['lead_quality']) ? sanitize_text_field($lead['lead_quality']) : '',
                'stage' => !empty($lead['stage']) ? sanitize_text_field($lead['stage']) : $default_stage,
                'call_status' => !empty($lead['call_status']) ? sanitize_text_field($lead['call_status']) : $default_call_status,
            );

            if ($validate_only) {
                $qualified = 'Qualified' === $lead_data['lead_quality'] || 'Qualified' === $lead_data['stage'];
                $required_project_fields = array('project_name', 'property_location', 'budget', 'possession_date', 'property_type', 'project_timeline');
                $missing_project_fields = array_filter($required_project_fields, static function($field) use ($lead_data) {
                    return empty($lead_data[$field]);
                });
                if ($qualified && ($missing_project_fields || IDLM_DB::budget_to_rupees($lead_data['budget']) < 400000)) {
                    $projected['rejected']++;
                    continue;
                }
                $email_identity = strtolower(trim($lead_data['email']));
                $phone_digits = preg_replace('/[^0-9]/', '', $lead_data['phone']);
                $phone_identity = strlen($phone_digits) >= 10 ? substr($phone_digits, -10) : $phone_digits;
                $email_key = $email_identity ? 'email:' . $email_identity : '';
                $phone_key = $phone_identity ? 'phone:' . $phone_identity : '';
                $is_duplicate = ($email_key && isset($seen_import_identities[$email_key]))
                    || ($phone_key && isset($seen_import_identities[$phone_key]))
                    || self::import_row_is_duplicate($lead_data, $table_leads);
                if ($email_key) $seen_import_identities[$email_key] = true;
                if ($phone_key) $seen_import_identities[$phone_key] = true;
                $projected[$is_duplicate ? 'duplicate' : 'valid']++;
                continue;
            }

            if (isset($lead['floor_plan_received'])) {
                $lead_data['floor_plan_received'] = intval($lead['floor_plan_received']);
            }
            if (isset($lead['floor_plan_file'])) {
                $lead_data['floor_plan_file'] = esc_url_raw($lead['floor_plan_file']);
                if (!empty($lead_data['floor_plan_file']) && empty($lead_data['floor_plan_received'])) {
                    $lead_data['floor_plan_received'] = 1;
                }
            }

            if (isset($lead['quote_shared'])) {
                $lead_data['quote_shared'] = intval($lead['quote_shared']);
            }
            if (isset($lead['quote_value'])) {
                $lead_data['quote_value'] = max(0, round(floatval($lead['quote_value']), 2));
            }
            if (isset($lead['quote_file'])) {
                $lead_data['quote_file'] = esc_url_raw($lead['quote_file']);
                if (!empty($lead_data['quote_file']) && empty($lead_data['quote_shared'])) {
                    $lead_data['quote_shared'] = 1;
                }
            }

            // Sanitize tracking click-IDs and UTM attribution strings
            $utm_keys = array('utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'fb_lead_id', 'li_fat_id', 'landing_page', 'conversion_time');
            foreach ($utm_keys as $ukey) {
                if (isset($lead[$ukey])) {
                    $lead_data[$ukey] = sanitize_text_field($lead[$ukey]);
                }
            }

            $result = IDLM_DB::upsert_inbound_lead($lead_data, 'csv', $lead_data, array('route'=>$run_routing, 'capi'=>$run_capi));

            if ($result) {
                if (!empty($result['requires_duplicate_review']) || !empty($result['matched'])) {
                    $duplicates++;
                } else {
                    $imported++;
                }

                $lead_id = absint($result['lead_id']);

            } else {
                $failed++;
                $errors[] = array(
                    'index' => absint($lead_index),
                    'message' => IDLM_DB::get_last_validation_error() ?: 'The lead could not be saved.',
                );
            }
        }

        wp_send_json_success(array(
            'imported' => $imported,
            'duplicates' => $duplicates,
            'failed' => $failed,
            'errors' => $errors,
            'projected' => $projected,
            'message' => 'Batch completed successfully'
        ));
    }

    private static function import_row_is_duplicate($lead, $table_leads) {
        global $wpdb;
        $email = sanitize_email($lead['email'] ?? '');
        $digits = preg_replace('/[^0-9]/', '', (string) ($lead['phone'] ?? ''));
        $conditions = array();
        $params = array();
        if ($email) {
            $conditions[] = 'email = %s';
            $params[] = $email;
        }
        if ($digits) {
            $conditions[] = 'phone LIKE %s';
            $params[] = '%' . $wpdb->esc_like(substr($digits, -10));
        }
        if (!$conditions) {
            return false;
        }
        $sql = "SELECT id, email, phone FROM {$table_leads} WHERE is_duplicate = 0 AND (" . implode(' OR ', $conditions) . ')';
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- REL007-SQL-0223: import_row_is_duplicate(): authenticated nonce/capability/object handlers; fixed date/sort/trigger maps; filter fragments prepared at construction, dynamic IN lists use integers/placeholders; export/task cursors are bounded and scoped.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $candidates = $wpdb->get_results($wpdb->prepare($sql, $params));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter
        $matching_ids = array();
        foreach ($candidates as $candidate) {
            if ($email && strtolower(trim((string) $candidate->email)) === strtolower($email)) {
                $matching_ids[] = absint($candidate->id);
                continue;
            }
            $candidate_digits = preg_replace('/[^0-9]/', '', (string) $candidate->phone);
            if (strlen($digits) >= 10 && strlen($candidate_digits) >= 10 && substr($digits, -10) === substr($candidate_digits, -10)) {
                $matching_ids[] = absint($candidate->id);
            }
        }
        return 1 === count(array_unique(array_filter($matching_ids)));
    }

    /**
     * AJAX Endpoint: Get tasks for a specific lead
     */
    public static function ajax_get_lead_tasks() {
        return IDLM_Admin_Task_Controller::ajax_get_lead_tasks();
    }

    /**
     * AJAX Endpoint: Add task for a lead
     */
    public static function ajax_add_lead_task() {
        return IDLM_Admin_Task_Controller::ajax_add_lead_task();
    }

    /**
     * AJAX Endpoint: Toggle task completion status
     */
    public static function ajax_toggle_task_status() {
        return IDLM_Admin_Task_Controller::ajax_toggle_task_status();
    }

    /**
     * AJAX Endpoint: Delete task
     */
    public static function ajax_delete_lead_task() {
        return IDLM_Admin_Task_Controller::ajax_delete_lead_task();
    }
    private static function manual_task_types() {
        return IDLM_Admin_Task_Controller::manual_task_types();
    }

    /**
     * A blank due date is valid; otherwise accept only the datetime-local
     * contract emitted by the task form and store a normalized site-local value.
     */
    private static function normalize_manual_task_due_date($due_date) {
        return IDLM_Admin_Task_Controller::normalize_manual_task_due_date($due_date);
    }

    private static function is_system_qualification_task($task) {
        return IDLM_Admin_Task_Controller::is_system_qualification_task($task);
    }

    public static function ajax_get_rep_tasks() {
        return IDLM_Admin_Task_Controller::ajax_get_rep_tasks();
    }
}
