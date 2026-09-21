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
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }
        
        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        if (!$lead_id) {
            wp_send_json_error(array('message' => 'Invalid Lead ID'));
        }
        if (!self::current_user_can_view_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You can access only leads assigned to you.'));
        }

        global $wpdb;
        
        // Fetch activity logs
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, u.display_name 
             FROM {$wpdb->prefix}idlm_lead_activity_log a 
             LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID 
             WHERE a.lead_id = %d ORDER BY a.id DESC LIMIT 100",
            $lead_id
        ));

        // Include comments and protected Lost remarks in the Details tab sidebar.
        $comments = array();
        if (!empty($logs)) {
            foreach ($logs as $log) {
                if (in_array($log->activity_type, array('comment', 'lost_remark', 'whatsapp_reply'), true)) {
                    $comments[] = $log;
                }
            }
        }
        // Show only the recent 5 comments (3-5 comments limit)
        $recent_comments = array_slice($comments, 0, 5);

        // 1. Render all activities & comments (Full Timeline)
        ob_start();
        ?>
        <div class="idlm-timeline" style="margin-top:0;">
            <?php if (empty($logs)): ?>
                <div style="text-align: center; color: #64748b; padding: 20px 0;">No activity logs found for this lead.</div>
            <?php else: ?>
                <?php foreach ($logs as $log): 
                    $author = $log->display_name ? $log->display_name : 'System Pipeline';
                ?>
                    <div class="idlm-timeline-item">
                        <div class="idlm-timeline-meta" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo esc_html($author); ?></strong>
                                <span><?php echo esc_html(self::format_datetime($log->created_at)); ?></span>
                            </div>
                            <?php if ($log->activity_type === 'comment' && (current_user_can('manage_options') || get_current_user_id() == $log->user_id)): ?>
                                <div class="idlm-timeline-actions" style="font-size: 11px;">
                                    <a href="#" class="idlm-edit-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: var(--idlm-brand-gold); text-decoration: none; margin-right: 8px;">Edit</a>
                                    <a href="#" class="idlm-delete-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: #b5472e; text-decoration: none;">Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="idlm-timeline-details">
                            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safely escaped and constructed by render_activity_details().
                            echo self::render_activity_details($log->details); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        $full_html = ob_get_clean();

        // 2. Render only recent comments
        ob_start();
        ?>
        <div class="idlm-timeline" style="margin-top:0;">
            <?php if (empty($recent_comments)): ?>
                <div style="text-align: center; color: #64748b; padding: 20px 0;">No comments found for this lead.</div>
            <?php else: ?>
                <?php foreach ($recent_comments as $log): 
                    $author = $log->display_name ? $log->display_name : 'System Pipeline';
                ?>
                    <div class="idlm-timeline-item">
                        <div class="idlm-timeline-meta" style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <strong><?php echo esc_html($author); ?></strong>
                                <span><?php echo esc_html(self::format_datetime($log->created_at)); ?></span>
                            </div>
                            <?php if ($log->activity_type === 'comment' && (current_user_can('manage_options') || get_current_user_id() == $log->user_id)): ?>
                                <div class="idlm-timeline-actions" style="font-size: 11px;">
                                    <a href="#" class="idlm-edit-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: var(--idlm-brand-gold); text-decoration: none; margin-right: 8px;">Edit</a>
                                    <a href="#" class="idlm-delete-comment-link" data-id="<?php echo esc_attr($log->id); ?>" style="color: #b5472e; text-decoration: none;">Delete</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="idlm-timeline-details">
                            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safely escaped and constructed by render_activity_details().
                            echo self::render_activity_details($log->details); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        $comments_html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $full_html,
            'comments_html' => $comments_html
        ));
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
            if ($value && !IDLM_Integrations::rep_has_capacity($value)) {
                wp_send_json_error(array('message' => 'This representative has reached the active-lead capacity. Close or reassign an active lead first.'));
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

        // Quote value is mandatory when the quote is shared.
        if ('stage' === $field && 'Quote Shared' === $value) {
            $quote_value_raw = isset($_POST['quote_value']) ? sanitize_text_field(wp_unslash($_POST['quote_value'])) : '';
            $quote_file = isset($_POST['quote_file']) ? esc_url_raw(wp_unslash($_POST['quote_file'])) : '';

            if (!is_numeric($quote_value_raw) || floatval($quote_value_raw) <= 0) {
                wp_send_json_error(array('message' => 'A valid quote value greater than zero is required before moving the lead to Quote Shared.'));
            }

            $data['quote_value'] = round(floatval($quote_value_raw), 2);
            $data['quote_shared'] = 1;
            $data['quote_file'] = $quote_file;
        }

        $updated = IDLM_DB::update_lead($lead_id, $data);
        if ($updated) {
            if ('stage' === $field && 'Lost' === $value) {
                IDLM_DB::log_activity($lead_id, 'lost_remark', 'Lost reason: ' . $lost_reason . '. Remark: ' . $lost_remark);
            }
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
        $lead = $wpdb->get_row($wpdb->prepare(
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
            $reason_labels = array(
                'spam_test' => 'Spam or test submission',
                'duplicate' => 'Duplicate lead',
                'invalid_contact' => 'Invalid or nonexistent contact',
                'unrelated_enquiry' => 'Vendor, job seeker, or unrelated enquiry',
                'do_not_contact' => 'Requested no further contact',
                'budget_below_minimum' => 'Budget below ₹4L',
                'outside_service_area' => 'Outside supported service area',
                'unsupported_project_type' => 'Unsupported project type',
                'timeline_mismatch' => 'Project timeline mismatch',
                'not_interested' => 'Customer confirmed not interested',
                'administrator_exception' => 'Administrator exception',
            );
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

            $outbound_calls = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id = %d AND activity_type = 'outbound_call'",
                $lead_id
            ));
            $response_evidence = in_array($lead->call_status, array('Connected - Interested', 'Connected - Not Interested'), true) || (bool) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}idlm_lead_activity_log
                 WHERE lead_id = %d AND (activity_type = 'whatsapp_reply' OR (activity_type = 'comment' AND (details LIKE %s OR details LIKE %s))) LIMIT 1",
                $lead_id,
                $wpdb->esc_like('[WhatsApp Reply]:') . '%',
                $wpdb->esc_like('[Direct WA Reply]:') . '%'
            ));
            $confirmed_reasons = array('unrelated_enquiry', 'do_not_contact', 'budget_below_minimum', 'outside_service_area', 'unsupported_project_type', 'timeline_mismatch', 'not_interested');
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
        IDLM_DB::sync_presales_task_status($lead_id);
        if ('call' === $kind) {
            $wpdb->insert($wpdb->prefix . 'idlm_call_logs', array(
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
            IDLM_DB::log_activity($lead_id, 'qualification_decision', 'Pre-sales decision: Qualified.');
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
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $lead_id = isset($_POST['lead_id']) ? intval(wp_unslash($_POST['lead_id'])) : 0;
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

        if (!$lead_id || empty($comment)) {
            wp_send_json_error(array('message' => 'Empty comment or invalid Lead ID'));
        }
        if (!self::current_user_can_access_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You can comment only on leads currently assigned to you.'));
        }

        global $wpdb;
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';
        $user_id = get_current_user_id();

        // Prevent duplicate comment within 15 seconds timeframe
        $now = current_time('mysql');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $duplicate = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_activity} 
             WHERE lead_id = %d 
               AND user_id = %d 
               AND activity_type = 'comment' 
               AND details = %s 
               AND created_at >= DATE_SUB(%s, INTERVAL 15 SECOND)",
            $lead_id,
            $user_id,
            $comment,
            $now
        ));

        if ($duplicate > 0) {
            wp_send_json_success(array('message' => 'Comment added successfully'));
        } else {
            IDLM_DB::log_activity($lead_id, 'comment', $comment);
            wp_send_json_success(array('message' => 'Comment added successfully'));
        }
    }

    /**
     * AJAX Endpoint: Edit activity comment
     */
    public static function ajax_edit_activity_comment() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $log_id = isset($_POST['log_id']) ? intval(wp_unslash($_POST['log_id'])) : 0;
        $comment = isset($_POST['comment']) ? sanitize_textarea_field(wp_unslash($_POST['comment'])) : '';

        if (!$log_id || empty($comment)) {
            wp_send_json_error(array('message' => 'Invalid parameters.'));
        }

        global $wpdb;
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_activity} WHERE id = %d", $log_id));
        if (!$log || $log->activity_type !== 'comment') {
            wp_send_json_error(array('message' => 'Comment not found.'));
        }

        if (!self::current_user_can_access_lead($log->lead_id)) {
            wp_send_json_error(array('message' => 'You cannot modify comments on this lead.'));
        }

        if (!current_user_can('manage_options') && get_current_user_id() != $log->user_id) {
            wp_send_json_error(array('message' => 'Unauthorized operation.'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update($table_activity, array('details' => $comment), array('id' => $log_id));

        if ($updated !== false) {
            wp_send_json_success(array('message' => 'Comment updated successfully.'));
        }

        wp_send_json_error(array('message' => 'Failed to update comment.'));
    }

    /**
     * AJAX Endpoint: Delete activity comment
     */
    public static function ajax_delete_activity_comment() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $log_id = isset($_POST['log_id']) ? intval(wp_unslash($_POST['log_id'])) : 0;
        if (!$log_id) {
            wp_send_json_error(array('message' => 'Invalid parameters.'));
        }

        global $wpdb;
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_activity} WHERE id = %d", $log_id));
        if (!$log || $log->activity_type !== 'comment') {
            wp_send_json_error(array('message' => 'Comment not found.'));
        }

        if (!self::current_user_can_access_lead($log->lead_id)) {
            wp_send_json_error(array('message' => 'You cannot modify comments on this lead.'));
        }

        if (!current_user_can('manage_options') && get_current_user_id() != $log->user_id) {
            wp_send_json_error(array('message' => 'Unauthorized operation.'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $deleted = $wpdb->delete($table_activity, array('id' => $log_id));

        if ($deleted !== false) {
            wp_send_json_success(array('message' => 'Comment deleted successfully.'));
        }

        wp_send_json_error(array('message' => 'Failed to delete comment.'));
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

        $query = "SELECT * FROM {$table_leads}";
        $where = array();
        $params = array();

        if ($rep_id) {
            $where[] = "assigned_rep_id = %d";
            $params[] = $rep_id;
        }

        // If bulk list is provided
        if (isset($_GET['ids'])) {
            $raw_ids = sanitize_text_field(wp_unslash($_GET['ids']));
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
                $whatsapp_received_lead_ids = self::get_whatsapp_received_lead_ids();
                $where[] = empty($whatsapp_received_lead_ids)
                    ? '1=0'
                    : 'id IN (' . implode(',', $whatsapp_received_lead_ids) . ')';
            } elseif (!empty($get_followup_filter) && $get_followup_filter === 'unread_whatsapp') {
                $unread_whatsapp_lead_ids = self::get_unread_whatsapp_lead_ids();
                $where[] = empty($unread_whatsapp_lead_ids)
                    ? '1=0'
                    : 'id IN (' . implode(',', array_map('absint', $unread_whatsapp_lead_ids)) . ')';
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
                    "assigned_rep_id > 0 AND is_duplicate = 0 AND stage NOT IN ('Won', 'Lost') AND COALESCE(assigned_at, created_at) < %s AND " . IDLM_Admin_Lead_Notifications::get_no_recent_assigned_rep_action_sql($cutoff),
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
        $query .= " ORDER BY id DESC";

        if (!empty($params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $prepared_query = $wpdb->prepare($query, $params);
        } else {
            $prepared_query = $query;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads = $wpdb->get_results($prepared_query);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=interior-design-leads-' . gmdate('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ($output) {
            fputcsv($output, array('ID', 'First Name', 'Last Name', 'Email', 'Phone', 'Budget', 'Project Timeline', 'Property Location / Pincode', 'Source', 'Referrer Details', 'Property Type', 'Rooms', 'Floor Plan', 'Floor Plan File URL', 'Quote Shared', 'Quote Value', 'Quote File URL', 'Stage', 'Lost Reason', 'Call Status', 'Callback Scheduled', 'Quality', 'UTM Source', 'UTM Medium', 'UTM Campaign', 'UTM Adgroup', 'UTM Term', 'UTM Content', 'GCLID', 'GBRAID', 'WBRAID', 'FBCLID', 'Meta Lead ID', 'LI FAT ID', 'Landing Page', 'Conversion Time', 'Created At', 'Assigned At', 'Sales Handoff At', 'Updated At'));

            foreach ($leads as $l) {
                fputcsv($output, array(
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
                ));
            }

            fclose($output); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        }
        exit;
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
            'last_name' => sanitize_text_field($form_fields['last_name']),
            'email' => $email,
            'phone' => $phone,
            'budget' => isset($form_fields['budget']) ? IDLM_Integrations::normalize_budget(sanitize_text_field($form_fields['budget'])) : '',
            'project_timeline' => isset($form_fields['project_timeline']) ? sanitize_text_field($form_fields['project_timeline']) : '',
            'property_location' => isset($form_fields['property_location']) ? sanitize_text_field($form_fields['property_location']) : '',
            'source' => sanitize_text_field($form_fields['source']),
            'property_type' => sanitize_text_field($form_fields['property_type']),
            'num_rooms' => IDLM_Integrations::normalize_num_rooms(sanitize_text_field($form_fields['num_rooms'])),
            'preferred_studio' => sanitize_text_field($form_fields['preferred_studio']),
            'lead_quality' => sanitize_text_field($form_fields['lead_quality']),
            'stage' => 'New',
            'call_status' => 'Not Called',
            'assigned_rep_id' => get_current_user_id()
        );
        if ($lead_data['source'] === 'Referral') {
            $lead_data['referrer_details'] = sanitize_textarea_field($form_fields['referrer_details']);
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
            $creator = wp_get_current_user();
            if ($created) {
                IDLM_DB::log_activity(
                    $lead_id,
                    'assignment',
                    sprintf('Lead manually created and assigned to %s.', $creator->display_name),
                    get_current_user_id()
                );
                if (self::current_user_is_pre_sales()) {
                    IDLM_DB::create_presales_followup_tasks($lead_id, get_current_user_id());
                }
            }
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
        IDLM_Audiences::run_batch_sync();

        wp_send_json_success(array('message' => 'Custom Audience background batch sync triggered!'));
    }

    /**
     * AJAX Endpoint: Test platform connection and credentials
     */
    public static function ajax_test_api_connection() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        $platform = isset($_POST['platform']) ? sanitize_key(wp_unslash($_POST['platform'])) : '';
        $test_type = isset($_POST['test_type']) ? sanitize_key(wp_unslash($_POST['test_type'])) : '';
        $target_id = isset($_POST['target_id']) ? sanitize_text_field(wp_unslash($_POST['target_id'])) : '';
        $access_token = isset($_POST['access_token']) ? sanitize_text_field(wp_unslash($_POST['access_token'])) : '';

        if (empty($platform) || empty($target_id)) {
            wp_send_json_error(array('message' => 'Missing platform or target ID to test.'));
        }

        if ($platform === 'meta') {
            if (empty($access_token)) {
                wp_send_json_error(array('message' => 'Access token is required to test Meta connection.'));
            }

            // We can check Pixel ID (CAPI) or Custom Audience ID
            $endpoint = "https://graph.facebook.com/v25.0/{$target_id}?access_token=" . urlencode($access_token);
            if ($test_type === 'capi') {
                $endpoint .= "&fields=name";
            } else {
                $endpoint .= "&fields=name,subtype";
            }

            $response = wp_remote_get($endpoint, array('timeout' => 15));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'Connection request failed: ' . $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code === 200) {
                $name = isset($data['name']) ? $data['name'] : 'Unnamed';
                if ($test_type === 'capi') {
                    wp_send_json_success(array('message' => sprintf('Meta Conversions API connection successful! Verified Pixel Name: "%s".', $name)));
                } else {
                    $subtype = isset($data['subtype']) ? $data['subtype'] : 'CUSTOM';
                    wp_send_json_success(array('message' => sprintf('Meta Custom Audience sync connection successful! Verified Audience Name: "%s" (%s).', $name, $subtype)));
                }
            } else {
                $err_msg = 'Meta Graph API Error: ';
                if (isset($data['error']['message'])) {
                    $err_msg .= $data['error']['message'];
                    if (isset($data['error']['error_subcode'])) {
                        $err_msg .= ' (Subcode: ' . $data['error']['error_subcode'] . ')';
                    }
                } else {
                    $err_msg .= 'HTTP Code ' . $code;
                }
                wp_send_json_error(array('message' => $err_msg));
            }
        } elseif ($platform === 'linkedin') {
            if (empty($access_token)) {
                wp_send_json_error(array('message' => 'Access token is required to test LinkedIn connection.'));
            }

            $endpoint = "https://api.linkedin.com/v2/adSegments/" . rawurlencode($target_id);
            $response = wp_remote_get($endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'X-Restli-Protocol-Version' => '2.0.0'
                ),
                'timeout' => 15
            ));

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'LinkedIn connection failed: ' . $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code === 200) {
                $name = isset($data['name']) ? $data['name'] : 'Unnamed Segment';
                wp_send_json_success(array('message' => sprintf('LinkedIn connection successful! Verified Segment Name: "%s".', $name)));
            } else {
                $err_msg = 'LinkedIn API Error: ';
                if (isset($data['message'])) {
                    $err_msg .= $data['message'];
                } else {
                    $err_msg .= 'HTTP Code ' . $code;
                }
                wp_send_json_error(array('message' => $err_msg));
            }
        } elseif ($platform === 'google') {
            $gadm_username = isset($_POST['google_gadm_username']) ? sanitize_text_field(wp_unslash($_POST['google_gadm_username'])) : '';
            if (empty($gadm_username)) {
                $gadm_username = get_option('idlm_google_gadm_username', 'google-ads');
            }
            $gadm_password = isset($_POST['google_gadm_password']) ? sanitize_text_field(wp_unslash($_POST['google_gadm_password'])) : '';
            if (empty($gadm_password)) {
                $gadm_password = IDLM_Secret_Store::get('idlm_google_gadm_password');
            }

            if (empty($gadm_username) || empty($gadm_password)) {
                wp_send_json_error(array('message' => 'Google Ads Data Manager source credentials are not fully configured.'));
            }

            if (strlen($gadm_username) < 3 || strlen($gadm_password) < 8) {
                wp_send_json_error(array('message' => 'Credentials format error: Username must be at least 3 chars and Password must be at least 8 chars.'));
            }

            wp_send_json_success(array('message' => sprintf('Google Ads Data Manager source credentials verified! Feeds are active and secured under username "%s".', $gadm_username)));
        } else {
            wp_send_json_error(array('message' => 'Unsupported platform connection test.'));
        }
    }

    /**
     * AJAX Endpoint: Bulk retry Conversions API events
     */
    public static function ajax_retry_capi_events() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        $log_ids = isset($_POST['log_ids']) && is_array($_POST['log_ids']) ? array_map('intval', wp_unslash($_POST['log_ids'])) : array();

        if (empty($log_ids)) {
            wp_send_json_error(array('message' => 'No logs selected for retry.'));
        }

        $success_count = 0;
        $failed_count = 0;
        $errors = array();

        foreach ($log_ids as $log_id) {
            $result = IDLM_CAPI::retry_capi_log($log_id);
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

        wp_send_json_success(array('message' => $msg));
    }

    /**
     * AJAX Endpoint: Log Javascript errors to telemetry
     */
    public static function ajax_log_js_error() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        
        $message = isset($_POST['message']) ? sanitize_text_field(wp_unslash($_POST['message'])) : '';
        $file    = isset($_POST['file']) ? sanitize_text_field(wp_unslash($_POST['file'])) : '';
        $line    = isset($_POST['line']) ? intval($_POST['line']) : 0;
        $col     = isset($_POST['col']) ? intval($_POST['col']) : 0;
        $stack   = isset($_POST['stack']) ? sanitize_textarea_field(wp_unslash($_POST['stack'])) : '';

        if (!empty($message)) {
            $trace = "Col: {$col}\n\nStack:\n{$stack}";
            IDLM_Telemetry::log('js_error', 'error', $message, $file, $line, $trace);
        }

        wp_send_json_success();
    }

    /**
     * AJAX Endpoint: Clear telemetry logs table
     */
    public static function ajax_clear_telemetry_logs() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        global $wpdb;
        $table_telemetry = $wpdb->prefix . 'idlm_telemetry_logs';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query("TRUNCATE TABLE {$table_telemetry}");

        wp_send_json_success(array('message' => 'Telemetry logs cleared successfully.'));
    }

    /**
     * AJAX/GET Endpoint: Download diagnostic report
     */
    public static function ajax_download_diagnostic_report() {
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_key($_GET['nonce']), 'idlm_admin_nonce')) {
            wp_die('Invalid security token.', 'Access Denied', array('response' => 403));
        }
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.', 'Access Denied', array('response' => 403));
        }

        // Clear any previous output buffers to avoid corrupting headers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        global $wpdb;
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename=idlm-diagnostic-report-' . gmdate('Y-m-d') . '.txt');

        // Compile diagnostics text
        $out = array();
        $out[] = "=== INTERIOR DESIGN LEAD MANAGER DIAGNOSTICS ===";
        $out[] = "Generated on: " . gmdate('Y-m-d H:i:s') . " UTC";
        $out[] = "Plugin Version: " . IDLM_VERSION;
        $out[] = "";
        
        $out[] = "=== ENVIRONMENT ===";
        $out[] = "WordPress Version: " . get_bloginfo('version');
        $out[] = "PHP Version: " . PHP_VERSION;
        $out[] = "Server Software: " . (isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown');
        $out[] = "MySQL Version: " . $wpdb->db_version();
        $out[] = "PHP Memory Limit: " . ini_get('memory_limit');
        $out[] = "PHP Max Execution Time: " . ini_get('max_execution_time') . "s";
        $out[] = "WP Debug Mode: " . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled');
        
        $as_version = 'Not installed/active';
        if (class_exists('ActionScheduler')) {
            $as_version = 'Active';
            if (class_exists('ActionScheduler_Versions') && method_exists('ActionScheduler_Versions', 'instance')) {
                $as_version = ActionScheduler_Versions::instance()->latest_version();
            }
        }
        $out[] = "Action Scheduler Version: " . $as_version;
        $out[] = "";

        $out[] = "=== PLUGIN SETTINGS ===";
        $out[] = "Meta CAPI Enabled: " . get_option('idlm_meta_capi_enabled', '0');
        $out[] = "Google CAPI Enabled: " . get_option('idlm_google_capi_enabled', '0');
        $out[] = "LinkedIn CAPI Enabled: " . get_option('idlm_linkedin_capi_enabled', '0');
        $out[] = "Telemetry Log Level: " . get_option('idlm_telemetry_level', 'errors');
        $out[] = "Custom Stages: " . implode(', ', get_option('idlm_custom_stages', array()));
        $out[] = "";

        $out[] = "=== DATABASE STATE ===";
        foreach (array('idlm_leads', 'idlm_lead_activity_log', 'idlm_lead_touchpoints', 'idlm_inbound_submission_reviews', 'idlm_capi_logs', 'idlm_audience_sync_logs', 'idlm_telemetry_logs') as $tbl) {
            $full_tbl = $wpdb->prefix . $tbl;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_tbl)) === $full_tbl;
            if ($exists) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$full_tbl}");
                $out[] = "- {$full_tbl}: {$count} rows";
            } else {
                $out[] = "- {$full_tbl}: MISSING";
            }
        }
        $out[] = "";

        $out[] = "=== RECENT TELEMETRY LOGS (Last 50 Entries) ===";
        $table_telemetry = $wpdb->prefix . 'idlm_telemetry_logs';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_telemetry)) === $table_telemetry) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $logs = $wpdb->get_results("SELECT * FROM {$table_telemetry} ORDER BY id DESC LIMIT 50");
            if (empty($logs)) {
                $out[] = "No error logs recorded.";
            } else {
                foreach ($logs as $log) {
                    $out[] = sprintf(
                        "[%s] [%s] [%s] %s in %s on line %d",
                        (string) $log->created_at,
                        strtoupper((string) $log->severity),
                        (string) $log->log_type,
                        (string) $log->message,
                        $log->file ? (string) $log->file : '[unknown file]',
                        (int) $log->line
                    );
                    if (!empty($log->stack_trace)) {
                        $out[] = "Stack Trace:\n" . (string) $log->stack_trace;
                    }
                    $out[] = str_repeat('-', 40);
                }
            }
        } else {
            $out[] = "Telemetry log table is missing.";
        }

        echo implode("\n", $out); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
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
        $posted_rep_studios = (!empty($_POST['rep_studios']) && is_array($_POST['rep_studios'])) ? wp_unslash($_POST['rep_studios']) : array();
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
        $exception_count = intval($wpdb->get_var(
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
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $lead_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table_leads} WHERE {$trigger_type} = %s ORDER BY id ASC",
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
        $raw_ids = isset($_POST['lead_ids']) ? wp_unslash($_POST['lead_ids']) : array();
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
        if ('1' !== get_option('idlm_interakt_enabled', '0') || !get_option('idlm_interakt_api_key', '')) {
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
                    if ($rep_id) {
                        $capacity = IDLM_Integrations::get_rep_capacity_status($rep_id);
                        if (count($ids) > $capacity['remaining']) {
                            wp_send_json_error(array('message' => sprintf(
                                'This assignment would exceed the representative capacity. %d active, %d maximum, %d slot(s) available.',
                                $capacity['active'], $capacity['maximum'], $capacity['remaining']
                            )));
                        }
                    }
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
                        }
                        if (false === IDLM_DB::update_lead($id, $stage_data)) {
                            $failed[$id] = IDLM_DB::get_last_validation_error() ?: 'Update failed.';
                        } elseif ('Lost' === $stage) {
                            IDLM_DB::log_activity($id, 'lost_remark', 'Lost reason: ' . $lost_reason . '. Remark: ' . $lost_remark);
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

            case 'bulk_audience_queue':
                $queue = get_option('idlm_audience_sync_queue', array());
                $new_queue = array_unique(array_merge($queue, $ids));
                update_option('idlm_audience_sync_queue', $new_queue);

                foreach ($ids as $id) {
                    IDLM_DB::log_activity($id, 'audience_queue', 'Lead manually added to 2:00 AM custom audience sync queue.', 0);
                }
                wp_send_json_success(array('message' => 'Selected leads added to 2:00 AM audience batch sync queue.'));
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
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        $page = isset($_POST['page']) ? max(1, intval(wp_unslash($_POST['page']))) : 1;
        $allowed_limits = array(25, 50, 100);
        $requested_limit = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 25;
        $limit = in_array($requested_limit, $allowed_limits, true) ? $requested_limit : 25;
        $offset = ($page - 1) * $limit;
        $sort_by = isset($_POST['sort_by']) ? sanitize_key(wp_unslash($_POST['sort_by'])) : '';
        $sort_order = isset($_POST['sort_order']) ? strtoupper(sanitize_key(wp_unslash($_POST['sort_order']))) : 'ASC';
        $sort_order = in_array($sort_order, array('ASC', 'DESC'), true) ? $sort_order : 'ASC';
        $cursor_token = isset($_POST['cursor']) ? sanitize_text_field(wp_unslash($_POST['cursor'])) : '';

        $where = array();
        
        // Rep permissions check
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $restricted_user_id = IDLM_Lead_Access_Policy::restriction_user_id();

        // Duplicate rows remain available to administrators for audit, but reps
        // always work from the single canonical lead.
        if (!$is_admin) {
            $where[] = 'l.is_duplicate = 0';
        }

        $requested_followup_filter = isset($_POST['followup_filter']) ? sanitize_text_field(wp_unslash($_POST['followup_filter'])) : '';
        $active_only = isset($_POST['active_only']) && '1' === sanitize_text_field(wp_unslash($_POST['active_only']));
        if ($is_pre_sales && 'my_qualified_history' === $requested_followup_filter) {
            $where[] = $wpdb->prepare(
                "lead_quality = 'Qualified' AND (qualified_by_user_id = %d OR intake_owner_id = %d)",
                $current_user_id,
                $current_user_id
            );
        } elseif ($restricted_user_id) {
            $where[] = $wpdb->prepare("assigned_rep_id = %d", $restricted_user_id);
        }
        if ($active_only) {
            $where[] = "COALESCE(l.stage, '') NOT IN ('Won', 'Lost')";
        }

        // Apply filters
        $date_columns = array(
            'assigned_at' => 'l.assigned_at', 'created_at' => 'l.created_at', 'handoff_at' => 'l.handoff_at',
            'updated_at' => 'l.updated_at', 'qualified_at' => 'l.qualified_at', 'conversion_time' => 'l.conversion_time',
        );
        $post_date_basis = isset($_POST['date_basis']) ? sanitize_key(wp_unslash($_POST['date_basis'])) : 'assigned_at';
        $date_column = $date_columns[$post_date_basis] ?? 'l.assigned_at';
        $post_date_range = isset($_POST['date_range']) ? sanitize_key(wp_unslash($_POST['date_range'])) : '';
        $cursor_date_anchor = '';
        if (!empty($post_date_range)) {
            $timezone = wp_timezone();
            $now = new DateTime('now', $timezone);
            $cursor_date_anchor = $timezone->getName() . '|' . $now->format('Y-m-d');
            if ($post_date_range === 'today') {
                $where[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'yesterday') {
                $yesterday = clone $now;
                $yesterday->modify('-1 day');
                $where[] = $wpdb->prepare(
                    "{$date_column} >= %s AND {$date_column} <= %s",
                    $yesterday->format('Y-m-d 00:00:00'),
                    $yesterday->format('Y-m-d 23:59:59')
                );
            } elseif ($post_date_range === 'week') {
                $start = clone $now;
                $start->modify('monday this week');
                $where[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'month') {
                $where[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-m-01 00:00:00'));
            } elseif ($post_date_range === 'last_7_days') {
                $start = clone $now;
                $start->modify('-6 days');
                $where[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'last_30_days') {
                $start = clone $now;
                $start->modify('-29 days');
                $where[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'last_month') {
                $start = clone $now;
                $start->modify('first day of last month');
                $end = clone $now;
                $end->modify('last day of last month');
                $where[] = $wpdb->prepare(
                    "{$date_column} >= %s AND {$date_column} <= %s",
                    $start->format('Y-m-d 00:00:00'),
                    $end->format('Y-m-d 23:59:59')
                );
            } elseif ($post_date_range === 'year' || $post_date_range === 'this_year') {
                $where[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-01-01 00:00:00'));
            }
        }

        $post_source          = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
        $post_rep_id          = isset($_POST['rep_id']) ? sanitize_text_field(wp_unslash($_POST['rep_id'])) : '';
        $post_stage           = isset($_POST['stage']) ? sanitize_text_field(wp_unslash($_POST['stage'])) : '';
        $post_call_status     = isset($_POST['call_status']) ? sanitize_text_field(wp_unslash($_POST['call_status'])) : '';
        $post_quality         = isset($_POST['quality']) ? sanitize_text_field(wp_unslash($_POST['quality'])) : '';
        $post_followup_filter = $requested_followup_filter;
        $post_search          = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

        if (!empty($post_source)) {
            $where[] = $wpdb->prepare("source = %s", $post_source);
        }

        if ($post_rep_id !== '') {
            $where[] = $wpdb->prepare("assigned_rep_id = %d", intval($post_rep_id));
        }

        if (!empty($post_stage)) {
            $where[] = $wpdb->prepare("stage = %s", $post_stage);
        }

        if (!empty($post_call_status)) {
            $where[] = $wpdb->prepare("call_status = %s", $post_call_status);
        }

        if (!empty($post_quality)) {
            if ('Qualified Awaiting Assignment' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND handoff_status IN ('awaiting_manager', 'exception')";
            } elseif ('Routing Exception' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND handoff_status = 'exception'";
            } elseif ('Qualified Plan Received' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND floor_plan_received = 1";
            } elseif ('Qualified Plan Pending' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND COALESCE(floor_plan_received, 0) = 0";
            } elseif ('Pending' === $post_quality) {
                $where[] = "(lead_quality = '' OR lead_quality IS NULL)";
            } else {
                $where[] = $wpdb->prepare("lead_quality = %s", $post_quality);
            }
        }

        if ('my_qualified_history' === $post_followup_filter && $is_pre_sales) {
            // Visibility and qualification predicates were applied above.
        } elseif ('action_required' === $post_followup_filter && $is_pre_sales) {
            $where[] = "l.is_duplicate = 0 AND COALESCE(l.lead_quality, '') NOT IN ('Qualified', 'Unqualified') AND COALESCE(l.stage, '') NOT IN ('Won', 'Lost')";
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'pending_calls') {
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
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'whatsapp_received') {
            $whatsapp_received_lead_ids = self::get_whatsapp_received_lead_ids();
            $where[] = empty($whatsapp_received_lead_ids)
                ? '1=0'
                : 'l.id IN (' . implode(',', $whatsapp_received_lead_ids) . ')';
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'unread_whatsapp') {
            $unread_whatsapp_lead_ids = self::get_unread_whatsapp_lead_ids();
            $where[] = empty($unread_whatsapp_lead_ids)
                ? '1=0'
                : 'l.id IN (' . implode(',', array_map('absint', $unread_whatsapp_lead_ids)) . ')';
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'overdue') {
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
        } elseif (!empty($post_followup_filter) && $post_followup_filter === 'stale_updates') {
            $cutoff = IDLM_Admin_Lead_Notifications::get_stale_lead_cutoff();
            $where[] = $wpdb->prepare(
                "l.assigned_rep_id > 0 AND l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND COALESCE(l.assigned_at, l.created_at) < %s AND " . IDLM_Admin_Lead_Notifications::get_no_recent_assigned_rep_action_sql($cutoff, 'l'),
                $cutoff
            );
        }

        if (!empty($post_search)) {
            $search = '%' . $wpdb->esc_like($post_search) . '%';
            $where[] = $wpdb->prepare("(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)", $search, $search, $search, $search);
        }

        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        if (isset($_POST['count_only']) && '1' === sanitize_text_field(wp_unslash($_POST['count_only']))) {
            // Reuse the authenticated, capability-scoped table filters for export previews without rendering rows.
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $matched_count = intval($wpdb->get_var("SELECT COUNT(l.id) FROM {$table_leads} l {$where_clause}"));
            wp_send_json_success(array('total_items' => $matched_count));
        }

        // Metric-grid values use the same permissions and active filters as the
        // table, while retaining each card's documented workflow definition.
        $metric_yesterday = new DateTime('yesterday', wp_timezone());
        $metric_yesterday_start = $metric_yesterday->format('Y-m-d 00:00:00');
        $metric_yesterday_end = $metric_yesterday->format('Y-m-d 23:59:59');
        $metric_yesterday_start_sql = $wpdb->prepare('%s', $metric_yesterday_start);
        $metric_yesterday_end_sql = $wpdb->prepare('%s', $metric_yesterday_end);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $metric_row = $wpdb->get_row(
            "SELECT
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage = 'New' THEN 1 ELSE 0 END) AS new_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.call_status = 'Not Called' THEN 1 ELSE 0 END) AS not_called_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.call_status = 'Call Back Requested' THEN 1 ELSE 0 END) AS followup_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.assigned_rep_id = 0 THEN 1 ELSE 0 END) AS unassigned_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.lead_quality = 'Qualified' THEN 1 ELSE 0 END) AS qualified_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND COALESCE(l.lead_quality, '') = '' THEN 1 ELSE 0 END) AS pending_quality_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.lead_quality = 'Qualified' AND l.handoff_status IN ('awaiting_manager', 'exception') THEN 1 ELSE 0 END) AS qualified_handoff_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.lead_quality = 'Qualified' AND l.handoff_status = 'exception' THEN 1 ELSE 0 END) AS routing_exception_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.qualified_at BETWEEN {$metric_yesterday_start_sql} AND {$metric_yesterday_end_sql} THEN 1 ELSE 0 END) AS qualified_yesterday_count,
                SUM(CASE WHEN l.is_duplicate = 0 AND l.handoff_at BETWEEN {$metric_yesterday_start_sql} AND {$metric_yesterday_end_sql} AND l.handoff_status IN ('manually_assigned', 'auto_assigned') THEN 1 ELSE 0 END) AS delivered_yesterday_count
             FROM {$table_leads} l {$where_clause}"
        );
        $metric_stats = array(
            'new' => $metric_row ? intval($metric_row->new_count) : 0,
            'not_called' => $metric_row ? intval($metric_row->not_called_count) : 0,
            'followup' => $metric_row ? intval($metric_row->followup_count) : 0,
            'unassigned' => $metric_row ? intval($metric_row->unassigned_count) : 0,
            'qualified' => $metric_row ? intval($metric_row->qualified_count) : 0,
            'pending_quality' => $metric_row ? intval($metric_row->pending_quality_count) : 0,
            'qualified_handoffs' => $metric_row ? intval($metric_row->qualified_handoff_count) : 0,
            'routing_exceptions' => $metric_row ? intval($metric_row->routing_exception_count) : 0,
            'qualified_yesterday' => $metric_row ? intval($metric_row->qualified_yesterday_count) : 0,
            'delivered_yesterday' => $metric_row ? intval($metric_row->delivered_yesterday_count) : 0,
            'overdue_handoffs' => 0,
        );

        $metric_handoff_starts = $wpdb->get_col(
            "SELECT COALESCE(l.qualified_at, l.updated_at, l.created_at) FROM {$table_leads} l {$where_clause}" .
            (empty($where) ? ' WHERE ' : ' AND ') .
            "l.is_duplicate = 0 AND l.stage NOT IN ('Won', 'Lost') AND l.lead_quality = 'Qualified' AND l.handoff_status IN ('awaiting_manager', 'exception')"
        );
        $metric_now = current_time('mysql');
        foreach ($metric_handoff_starts as $metric_handoff_start) {
            if (IDLM_Integrations::get_elapsed_working_hours(
                $metric_handoff_start,
                $metric_now,
                get_option('idlm_working_hours_start', '09:00'),
                get_option('idlm_working_hours_end', '18:00'),
                get_option('idlm_working_days', array(1, 2, 3, 4, 5, 6))
            ) >= max(1, intval(get_option('idlm_qualified_fallback_hours', '4')))) {
                $metric_stats['overdue_handoffs']++;
            }
        }

        // Query leads
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_items = intval($wpdb->get_var("SELECT COUNT(l.id) FROM {$table_leads} l {$where_clause}"));
        $total_pages = ceil($total_items / $limit);

        $now_mysql = current_time('mysql');
        $whatsapp_reply_pattern = $wpdb->esc_like('[WhatsApp Reply]:') . '%';
        $direct_wa_reply_pattern = $wpdb->esc_like('[Direct WA Reply]:') . '%';
        // The dynamic WHERE clause contains only fixed SQL fragments and values escaped above with $wpdb->prepare().
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $sortable_columns = array(
            'name'          => "LOWER(CONCAT_WS(' ', l.first_name, l.last_name))",
            'source'        => "LOWER(COALESCE(l.source, ''))",
            'created_at'    => 'l.created_at',
            'assigned_at'   => 'l.assigned_at',
            'age'           => 'l.created_at',
            'budget'        => "LOWER(COALESCE(l.budget, ''))",
            'stage'         => "LOWER(COALESCE(l.stage, ''))",
            'assigned_rep'  => "LOWER(COALESCE(u.display_name, ''))",
            'lead_quality'  => "LOWER(COALESCE(l.lead_quality, ''))",
            'tasks'         => 'pending_tasks_count',
            'callback'      => 'l.callback_datetime',
            'response'      => "LOWER(COALESCE(l.call_status, ''))",
            'details'       => "((COALESCE(l.project_name, '') <> '') + (COALESCE(l.property_location, '') <> '') + (COALESCE(l.budget, '') <> '') + (COALESCE(l.possession_date, '') <> '') + (COALESCE(l.property_type, '') <> '') + (COALESCE(l.project_timeline, '') <> ''))",
        );
        $uses_default_queue_order = empty($sort_by) || !isset($sortable_columns[$sort_by]);
        $order_by = $is_pre_sales
            ? "CASE WHEN l.call_status = 'Call Back Requested' AND l.callback_datetime IS NOT NULL AND l.callback_datetime <= %s THEN 0 WHEN l.call_status = 'Not Called' THEN 1 ELSE 2 END ASC, COALESCE(l.callback_datetime, l.assigned_at, l.created_at) ASC, l.id ASC"
            : 'COALESCE(l.assigned_at, l.created_at) DESC, l.id DESC';
        if (!$uses_default_queue_order) {
            $sort_expression = $sortable_columns[$sort_by];
            if ('age' === $sort_by) {
                $sort_expression = 'l.created_at';
                $sort_order = 'ASC' === $sort_order ? 'DESC' : 'ASC';
            }
            $order_by = "{$sort_expression} {$sort_order}, l.id {$sort_order}";
        }

        $cursor_enabled = !$is_pre_sales && $uses_default_queue_order;
        $cursor_scope = array(
            'access' => array(
                'administrator' => $is_admin ? 1 : 0,
                'restricted_user_id' => intval($restricted_user_id),
            ),
            'filters' => array(
                'active_only' => $active_only ? 1 : 0,
                'call_status' => $post_call_status,
                'date_anchor' => $cursor_date_anchor,
                'date_basis' => $post_date_basis,
                'date_range' => $post_date_range,
                'followup_filter' => $post_followup_filter,
                'quality' => $post_quality,
                'rep_id' => $post_rep_id,
                'search' => $post_search,
                'source' => $post_source,
                'stage' => $post_stage,
            ),
            'limit' => $limit,
            'sort' => 'assigned_or_created_desc_id_desc',
        );
        if ('' !== $cursor_token && !$cursor_enabled) {
            wp_send_json_error(array('message' => 'This pagination cursor does not match the selected lead sort.'), 400);
        }

        $pagination_where_clause = $where_clause;
        $use_cursor_query = $cursor_enabled && (1 === $page || '' !== $cursor_token);
        if ('' !== $cursor_token) {
            $cursor_position = IDLM_Cursor_Pagination::decode($cursor_token, 'leads', $cursor_scope);
            if (is_wp_error($cursor_position)) {
                wp_send_json_error(array('message' => $cursor_position->get_error_message()), 400);
            }
            $cursor_sort_value = isset($cursor_position['sort_value']) ? (string) $cursor_position['sort_value'] : '';
            $cursor_id = isset($cursor_position['id']) ? absint($cursor_position['id']) : 0;
            if (!$cursor_id || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $cursor_sort_value)) {
                wp_send_json_error(array('message' => 'The pagination cursor position is invalid. Refresh the lead list and try again.'), 400);
            }
            $cursor_predicate = $wpdb->prepare(
                '(COALESCE(l.assigned_at, l.created_at) < %s OR (COALESCE(l.assigned_at, l.created_at) = %s AND l.id < %d))',
                $cursor_sort_value,
                $cursor_sort_value,
                $cursor_id
            );
            $pagination_where_clause .= (empty($where) ? ' WHERE ' : ' AND ') . $cursor_predicate;
        }

        $query_params = array($now_mysql, $whatsapp_reply_pattern, $direct_wa_reply_pattern);
        if ($is_pre_sales && $uses_default_queue_order) {
            $query_params[] = $now_mysql;
        }
        $query_params[] = $limit;
        if (!$use_cursor_query) {
            $query_params[] = $offset;
        }
        $pagination_limit_sql = $use_cursor_query ? 'LIMIT %d' : 'LIMIT %d OFFSET %d';
        // Every dynamic filter fragment in $where_clause is fixed SQL or was
        // prepared at construction time; remaining placeholders are prepared here.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name as rep_name,
                    (SELECT COUNT(t.id) FROM {$wpdb->prefix}idlm_tasks t WHERE t.lead_id = l.id AND t.status = 'pending') as pending_tasks_count,
                    (SELECT COUNT(t.id) FROM {$wpdb->prefix}idlm_tasks t WHERE t.lead_id = l.id AND t.status = 'pending' AND t.due_date IS NOT NULL AND t.due_date < %s) as overdue_tasks_count,
                    (SELECT MAX(a.id) FROM {$wpdb->prefix}idlm_lead_activity_log a
                     WHERE a.lead_id = l.id AND (
                        a.activity_type = 'whatsapp_reply' OR
                        (a.activity_type = 'comment' AND (a.details LIKE %s OR a.details LIKE %s))
                     )) as latest_whatsapp_reply_id
             FROM {$table_leads} l 
             LEFT JOIN {$wpdb->users} u ON l.assigned_rep_id = u.ID 
             {$pagination_where_clause} ORDER BY {$order_by} {$pagination_limit_sql}",
            $query_params
        ));

        $outreach_by_lead = array();
        if ($is_pre_sales && $leads) {
            $lead_ids = array_map('absint', wp_list_pluck($leads, 'id'));
            $id_sql = implode(',', $lead_ids);
            $outreach_rows = $wpdb->get_results(
                "SELECT lead_id, DATE(created_at) activity_date,
                        SUM(activity_type = 'outbound_call') calls,
                        SUM(activity_type = 'whatsapp_outbound') whatsapp
                 FROM {$wpdb->prefix}idlm_lead_activity_log
                 WHERE lead_id IN ({$id_sql}) AND activity_type IN ('outbound_call', 'whatsapp_outbound')
                 GROUP BY lead_id, DATE(created_at)
                 ORDER BY lead_id ASC, DATE(created_at) ASC"
            );
            foreach ($outreach_rows as $row) {
                $outreach_by_lead[absint($row->lead_id)][$row->activity_date] = array(
                    'activity_date' => $row->activity_date,
                    'calls' => (int) $row->calls,
                    'whatsapp' => (int) $row->whatsapp,
                );
            }
        }

        // Get config data for inline edit options
        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $reps = get_users(array(
            'role__in' => self::get_allowed_rep_roles(),
            'fields'   => array('ID', 'display_name')
        ));
        $active_rep_ids = get_option('idlm_active_assignment_reps', array());
        $whatsapp_read_ids = self::get_whatsapp_read_activity_ids();

        ob_start();
        if (empty($leads)) {
            echo '<tr><td colspan="' . ($is_pre_sales ? '13' : '10') . '" style="text-align:center;">No leads found matching your active filters.</td></tr>';
        } else {
            foreach ($leads as $l) {
                $name = esc_html($l->first_name . ' ' . $l->last_name);
                if (empty($name)) {
                    $name = 'Unnamed Lead';
                }
                $dup_badge = $l->is_duplicate ? '<span class="idlm-badge idlm-badge-warning" style="margin-left:5px; font-size:9px; padding:2px 6px;">DUP</span>' : '';
                $last_read_whatsapp_id = isset($whatsapp_read_ids[$l->id]) ? absint($whatsapp_read_ids[$l->id]) : 0;
                $has_unread_whatsapp = !empty($l->latest_whatsapp_reply_id) && absint($l->latest_whatsapp_reply_id) > $last_read_whatsapp_id;
                $responsive_row_id = 'idlm-lead-row-' . absint($l->id);
                if ($is_pre_sales) {
                    $outreach_targets = IDLM_DB::get_presales_outreach_targets();
                    $calculated_progress = IDLM_DB::calculate_presales_outreach_progress(
                        array_values($outreach_by_lead[absint($l->id)] ?? array()),
                        $outreach_targets
                    );
                    $complete_cycles = min(6, count($calculated_progress['completed_cycles']));
                    $cycle_outreach = $calculated_progress['active_cycle'];
                    $details_values = array($l->project_name, $l->property_location, $l->budget, $l->possession_date, $l->property_type, $l->project_timeline);
                    $details_complete = count(array_filter($details_values));
                    $has_response = in_array($l->call_status, array('Connected - Interested', 'Connected - Not Interested'), true) || !empty($l->latest_whatsapp_reply_id);
                    $callback_due = 'Call Back Requested' === $l->call_status && !empty($l->callback_datetime) && strtotime($l->callback_datetime) <= current_time('timestamp');
                    if ('Qualified' === $l->lead_quality) {
                        $next_action = 'Qualified — awaiting handoff';
                        if (!empty($l->handoff_status) && 'awaiting_manager' !== $l->handoff_status) {
                            $next_action = 'Qualified — handoff complete';
                        }
                        $sla_label = 'Decision complete';
                        $sla_class = 'is-complete';
                    } elseif ('Unqualified' === $l->lead_quality) {
                        $next_action = 'Closed as unqualified';
                        $sla_label = 'Decision complete';
                        $sla_class = 'is-complete';
                    } elseif ($callback_due) {
                        $next_action = 'Call back now';
                        $sla_label = 'Overdue callback';
                        $sla_class = 'is-overdue';
                    } elseif ('Wrong Number' === $l->call_status) {
                        $next_action = 'Review invalid contact';
                        $sla_label = 'Decision ready';
                        $sla_class = 'is-response';
                    } elseif ('Connected - Not Interested' === $l->call_status) {
                        $next_action = 'Review early close';
                        $sla_label = 'Decision ready';
                        $sla_class = 'is-response';
                    } elseif ($has_response && $details_complete < 6) {
                        $next_action = 'Complete project details';
                        $sla_label = 'Response received';
                        $sla_class = 'is-response';
                    } elseif ($has_response && $l->budget && IDLM_DB::budget_to_rupees($l->budget) < 400000) {
                        $next_action = 'Review budget mismatch';
                        $sla_label = 'Decision ready';
                        $sla_class = 'is-response';
                    } elseif ($has_response && IDLM_DB::budget_to_rupees($l->budget) >= 400000) {
                        $next_action = 'Ready to qualify';
                        $sla_label = 'Decision ready';
                        $sla_class = 'is-complete';
                    } elseif ($complete_cycles >= 6) {
                        $next_action = 'Send final closure';
                        $sla_label = 'Closure ready';
                        $sla_class = 'is-response';
                    } elseif ((int) $cycle_outreach['calls'] < (int) $outreach_targets['calls']) {
                        $next_action = 'Log call ' . ((int) $cycle_outreach['calls'] + 1) . ' of ' . (int) $outreach_targets['calls'];
                        $sla_label = (int) $cycle_outreach['calls'] > 0 || (int) $cycle_outreach['whatsapp'] > 0 ? 'Cycle in progress' : 'Due today';
                        $sla_class = (int) $cycle_outreach['calls'] > 0 || (int) $cycle_outreach['whatsapp'] > 0 ? 'is-progress' : 'is-due';
                    } elseif ((int) $cycle_outreach['whatsapp'] < (int) $outreach_targets['whatsapp']) {
                        $next_action = 'Log WhatsApp';
                        $sla_label = 'Cycle in progress';
                        $sla_class = 'is-progress';
                    } else {
                        $next_action = 'Today complete';
                        $sla_label = 'On track';
                        $sla_class = 'is-complete';
                    }
                    $response_label = !empty($l->latest_whatsapp_reply_id) && !in_array($l->call_status, array('Connected - Interested', 'Connected - Not Interested'), true)
                        ? 'WhatsApp reply'
                        : ($l->call_status ?: 'No response');
                    $budget_pass = IDLM_DB::budget_to_rupees($l->budget) >= 400000;
                    $decision_ready = in_array($next_action, array('Ready to qualify', 'Review invalid contact', 'Review early close', 'Review budget mismatch', 'Send final closure'), true);
                    $decision_complete = in_array($l->lead_quality, array('Qualified', 'Unqualified'), true);
                    ?>
                    <tr id="<?php echo esc_attr($responsive_row_id); ?>" data-id="<?php echo esc_attr($l->id); ?>" class="idlm-presales-queue-row idlm-responsive-lead-row <?php echo $l->is_duplicate ? 'idlm-duplicate-row' : ''; ?>">
                        <td class="idlm-selection-column idlm-col-priority-primary" data-label="Select"><input type="checkbox" class="idlm-row-selector" value="<?php echo esc_attr($l->id); ?>" aria-label="Select lead <?php echo esc_attr($name); ?>"></td>
                        <td class="idlm-lead-identity-cell idlm-col-priority-primary" data-label="Lead" data-lead-drawer-id="<?php echo esc_attr($l->id); ?>">
                            <button type="button" class="idlm-lead-row-open" onclick="openLeadDrawer(<?php echo esc_attr($l->id); ?>)"><strong><?php echo esc_html($name); ?></strong><span class="screen-reader-text"> — open lead details</span></button> <?php echo wp_kses_post($dup_badge); ?>
                            <?php if ($has_unread_whatsapp): ?><span class="idlm-whatsapp-row-badge">New WhatsApp</span><?php endif; ?>
                            <small><?php echo esc_html($l->phone); ?><?php echo $l->project_name ? ' · ' . esc_html($l->project_name) : ''; ?></small>
                            <button type="button" class="idlm-lead-row-expand" aria-expanded="false" aria-controls="<?php echo esc_attr($responsive_row_id); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span><span class="idlm-lead-row-expand-label">Show details</span></button>
                        </td>
                        <td class="idlm-col-priority-low" data-label="Channel"><span class="idlm-badge idlm-badge-<?php echo esc_attr(strtolower(str_replace(array(' ', '/', '-'), '', $l->source))); ?>"><?php echo esc_html($l->source ?: 'Unknown'); ?></span></td>
                        <td class="idlm-col-created-at idlm-col-priority-low" data-label="Assigned date"><?php echo wp_kses_post(IDLM_Admin_Presenter::render_lead_table_datetime($l->assigned_at)); ?></td>
                        <td class="idlm-col-priority-low" data-label="Age"><strong><?php echo esc_html(self::get_business_day_age($l->created_at)); ?></strong><small>business days</small></td>
                        <td class="idlm-col-priority-low" data-label="Today"><span class="idlm-progress-chip"><?php echo esc_html($cycle_outreach['calls']); ?>/<?php echo esc_html($outreach_targets['calls']); ?> calls</span><span class="idlm-progress-chip"><?php echo esc_html($cycle_outreach['whatsapp']); ?>/<?php echo esc_html($outreach_targets['whatsapp']); ?> WA</span><small><?php echo esc_html(min(6, $complete_cycles)); ?>/6 cycles</small></td>
                        <td class="idlm-col-priority-secondary" data-label="Response"><span class="idlm-response-chip"><?php echo esc_html($response_label); ?></span></td>
                        <td class="idlm-col-priority-secondary" data-label="Budget"><strong><?php echo esc_html($l->budget ?: 'Missing'); ?></strong><span class="idlm-budget-check <?php echo $budget_pass ? 'is-pass' : 'is-review'; ?>"><?php echo $budget_pass ? '₹4L+' : 'Review'; ?></span></td>
                        <td class="idlm-col-priority-low" data-label="Project details"><strong><?php echo esc_html($details_complete); ?>/6</strong><small><?php echo empty($l->floor_plan_received) ? 'Plan pending' : 'Plan received'; ?></small></td>
                        <td class="idlm-col-priority-primary" data-label="Follow-up status"><span class="idlm-sla-chip <?php echo esc_attr($sla_class); ?>"><?php echo esc_html($sla_label); ?></span></td>
                        <td class="idlm-col-priority-primary idlm-lead-row-actions" data-label="Actions"><div class="idlm-queue-actions"><button type="button" class="idlm-btn idlm-btn-secondary idlm-presales-row-touch" data-kind="call" data-lead-id="<?php echo esc_attr($l->id); ?>" <?php disabled($decision_complete); ?>>Log call</button><button type="button" class="idlm-btn idlm-btn-secondary idlm-presales-row-touch" data-kind="whatsapp" data-lead-id="<?php echo esc_attr($l->id); ?>" <?php disabled($decision_complete); ?>>Log WA</button><?php if ($decision_ready): ?><button type="button" class="idlm-btn idlm-btn-primary" onclick="openLeadDrawer(<?php echo esc_attr($l->id); ?>)">Decide</button><?php endif; ?></div></td>
                    </tr>
                    <?php
                    continue;
                }
                ?>
                <tr id="<?php echo esc_attr($responsive_row_id); ?>" data-id="<?php echo esc_attr($l->id); ?>" class="idlm-responsive-lead-row <?php echo $l->is_duplicate ? 'idlm-duplicate-row' : ''; ?>">
                    <td class="idlm-selection-column idlm-col-priority-primary" data-label="Select"><input type="checkbox" class="idlm-row-selector" value="<?php echo esc_attr($l->id); ?>" aria-label="Select lead <?php echo esc_attr($name); ?>"></td>
                    <td class="idlm-lead-identity-cell idlm-col-priority-primary" data-label="Lead" data-lead-drawer-id="<?php echo esc_attr($l->id); ?>">
                        <button type="button" class="idlm-lead-row-open" onclick="openLeadDrawer(<?php echo esc_attr($l->id); ?>)"><strong><?php echo esc_html($name); ?></strong><span class="screen-reader-text"> — open lead details</span></button> <?php echo wp_kses_post($dup_badge); ?>
                        <?php if ($has_unread_whatsapp): ?>
                            <span class="idlm-whatsapp-row-badge" data-lead-id="<?php echo esc_attr($l->id); ?>" title="This lead sent a new WhatsApp message">New WhatsApp</span>
                        <?php endif; ?>
                        <div class="idlm-lead-row-contact"><?php echo esc_html($l->email); ?> | <?php echo esc_html($l->phone); ?></div>
                        <?php if ('Qualified' === $l->lead_quality && 'awaiting_manager' === $l->handoff_status):
                            $handoff_started_at = $l->qualified_at ?: ($l->updated_at ?: $l->created_at);
                            $handoff_wait_hours = IDLM_Integrations::get_elapsed_working_hours(
                                $handoff_started_at,
                                current_time('mysql'),
                                get_option('idlm_working_hours_start', '09:00'),
                                get_option('idlm_working_hours_end', '18:00'),
                                get_option('idlm_working_days', array(1, 2, 3, 4, 5, 6))
                            );
                            $handoff_is_overdue = $handoff_wait_hours >= max(1, intval(get_option('idlm_qualified_fallback_hours', '4')));
                        ?>
                            <span class="idlm-row-handoff-badge <?php echo $handoff_is_overdue ? 'is-overdue' : ''; ?>">Awaiting assignment · <?php echo esc_html($handoff_wait_hours < 1 ? '<1 working hour' : floor($handoff_wait_hours) . ' working hours'); ?></span>
                            <?php if (!empty($l->property_location)): ?><small class="idlm-row-handoff-location"><?php echo esc_html($l->property_location); ?></small><?php endif; ?>
                        <?php endif; ?>
                        <button type="button" class="idlm-lead-row-expand" aria-expanded="false" aria-controls="<?php echo esc_attr($responsive_row_id); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span><span class="idlm-lead-row-expand-label">Show details</span></button>
                    </td>
                    <td class="idlm-col-priority-low" data-label="Channel"><span class="idlm-badge idlm-badge-<?php echo esc_attr(strtolower(str_replace(' ', '', $l->source))); ?>"><?php echo esc_html($l->source); ?></span></td>
                    <td class="idlm-col-priority-secondary" data-label="Budget"><strong><?php echo !empty($l->budget) ? esc_html($l->budget) : 'N/A'; ?></strong></td>
                    
                    <!-- Stage Quick Edit dropdown -->
                    <td class="idlm-col-priority-primary" data-label="Stage">
                        <select class="idlm-table-select idlm-quick-edit" data-field="stage" data-id="<?php echo esc_attr($l->id); ?>" data-property-location="<?php echo esc_attr($l->property_location); ?>" data-quote-value="<?php echo esc_attr(isset($l->quote_value) ? $l->quote_value : ''); ?>" data-quote-file="<?php echo esc_url(isset($l->quote_file) ? $l->quote_file : ''); ?>">
                            <?php foreach ($stages as $st): ?>
                                <option value="<?php echo esc_attr($st); ?>" <?php selected($l->stage, $st); ?>><?php echo esc_html($st); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
 
                    <!-- Rep Quick Edit dropdown -->
                    <td class="idlm-col-priority-secondary" data-label="Assigned representative">
                        <?php if (self::current_user_can_assign_leads()): ?>
                            <select class="idlm-table-select idlm-quick-edit" data-field="assigned_rep_id" data-id="<?php echo esc_attr($l->id); ?>">
                                <option value="0">Unassigned</option>
                                <?php foreach ($reps as $rep): 
                                    if (!in_array($rep->ID, $active_rep_ids) && $l->assigned_rep_id != $rep->ID) {
                                        continue;
                                    }
                                ?>
                                    <option value="<?php echo esc_attr($rep->ID); ?>" <?php selected($l->assigned_rep_id, $rep->ID); ?>><?php echo esc_html($rep->display_name); ?></option>
                                  <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?php 
                            $assigned = !empty($l->rep_name) ? $l->rep_name : 'Unassigned';
                            echo esc_html($assigned);
                            ?>
                        <?php endif; ?>
                    </td>
 
                    <!-- Lead Quality dropdown -->
                    <td class="idlm-col-priority-primary" data-label="Lead quality">
                        <select class="idlm-table-select idlm-quick-edit" data-field="lead_quality" data-id="<?php echo esc_attr($l->id); ?>">
                            <option value="" <?php selected($l->lead_quality, ''); ?>>Pending Qualification</option>
                            <option value="Qualified" <?php selected($l->lead_quality, 'Qualified'); ?>>✅ <?php echo esc_html('Qualified' === $l->lead_quality ? self::get_qualification_status_label($l) : 'Qualified'); ?></option>
                            <option value="Unqualified" <?php selected($l->lead_quality, 'Unqualified'); ?>>❌ Unqualified</option>
                        </select>
                    </td>
 
                    <!-- Tasks Indicator -->
                    <td class="idlm-col-priority-secondary" data-label="Tasks">
                        <?php 
                        $pending_count = intval($l->pending_tasks_count);
                        $overdue_count = intval($l->overdue_tasks_count);
                        if ($pending_count > 0): 
                            $badge_class = ($overdue_count > 0) ? 'idlm-task-badge-overdue' : 'idlm-task-badge-active';
                            $title_text = sprintf('%d pending tasks (%d overdue)', $pending_count, $overdue_count);
                        ?>
                            <div class="idlm-table-task-badge <?php echo esc_attr($badge_class); ?>" title="<?php echo esc_attr($title_text); ?>" onclick="event.stopPropagation(); openLeadDrawer(<?php echo esc_attr($l->id); ?>, 'drawer-tab-tasks');" style="cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-size: 11px; padding: 3px 8px; border-radius: 12px; font-weight: 600;">
                                <span class="dashicons dashicons-editor-ul" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px;"></span>
                                <span><?php echo esc_html($pending_count); ?></span>
                            </div>
                        <?php else: ?>
                            <span style="color: #cbd5e1; font-size: 11px;">None</span>
                        <?php endif; ?>
                    </td>

                    <td class="idlm-col-created-at idlm-col-priority-low" data-label="Assigned at"><?php echo wp_kses_post(IDLM_Admin_Presenter::render_lead_table_datetime($l->assigned_at)); ?></td>
                    <td class="idlm-col-created-at idlm-col-priority-low" data-label="Created at"><?php echo wp_kses_post(IDLM_Admin_Presenter::render_lead_table_datetime($l->created_at)); ?></td>
                </tr>
                <?php
            }
        }
        $html = ob_get_clean();

        $next_cursor = '';
        if ($cursor_enabled && $page < $total_pages && !empty($leads)) {
            $last_lead = end($leads);
            $next_sort_value = null !== $last_lead->assigned_at ? $last_lead->assigned_at : $last_lead->created_at;
            if (is_string($next_sort_value) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $next_sort_value)) {
                $next_cursor = IDLM_Cursor_Pagination::encode(
                    'leads',
                    array('sort_value' => $next_sort_value, 'id' => absint($last_lead->id)),
                    $cursor_scope
                );
            }
        }

        wp_send_json_success(array(
            'html' => $html,
            'total_items' => $total_items,
            'total_pages' => max(1, $total_pages),
            'current_page' => $page,
            'pagination_mode' => $use_cursor_query ? 'cursor' : 'offset',
            'next_cursor' => $next_cursor,
            'metric_stats' => $metric_stats,
            'pending_calls_count' => self::get_pending_calls_count(),
            'unread_whatsapp_count' => self::get_unread_whatsapp_count(),
            'stale_lead_count' => IDLM_Admin_Lead_Notifications::get_stale_lead_count()
        ));
    }

    /**
     * AJAX Endpoint: Fetch leads grouped by stage for Kanban board view
     */
    public static function ajax_get_kanban_leads() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';

        $where = array();
        $where[] = "is_duplicate = 0"; // Exclude duplicate leads

        // Rep permissions check
        $current_user_id = get_current_user_id();
        $is_admin = current_user_can('manage_options');
        $restricted_user_id = IDLM_Lead_Access_Policy::restriction_user_id();

        if ($restricted_user_id) {
            $where[] = $wpdb->prepare("assigned_rep_id = %d", $restricted_user_id);
        }

        // Apply filters
        $date_columns = array(
            'assigned_at' => 'assigned_at', 'created_at' => 'created_at', 'handoff_at' => 'handoff_at',
            'updated_at' => 'updated_at', 'qualified_at' => 'qualified_at', 'conversion_time' => 'conversion_time',
        );
        $post_date_basis = isset($_POST['date_basis']) ? sanitize_key(wp_unslash($_POST['date_basis'])) : 'assigned_at';
        $date_column = $date_columns[$post_date_basis] ?? 'assigned_at';
        $post_date_range = isset($_POST['date_range']) ? sanitize_key(wp_unslash($_POST['date_range'])) : '';
        $date_conditions = array();
        if (!empty($post_date_range)) {
            $timezone = wp_timezone();
            $now = new DateTime('now', $timezone);
            if ($post_date_range === 'today') {
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'yesterday') {
                $yesterday = clone $now;
                $yesterday->modify('-1 day');
                $date_conditions[] = $wpdb->prepare(
                    "{$date_column} >= %s AND {$date_column} <= %s",
                    $yesterday->format('Y-m-d 00:00:00'),
                    $yesterday->format('Y-m-d 23:59:59')
                );
            } elseif ($post_date_range === 'week') {
                $start = clone $now;
                $start->modify('monday this week');
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'month') {
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-m-01 00:00:00'));
            } elseif ($post_date_range === 'last_7_days') {
                $start = clone $now;
                $start->modify('-6 days');
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'last_30_days') {
                $start = clone $now;
                $start->modify('-29 days');
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $start->format('Y-m-d 00:00:00'));
            } elseif ($post_date_range === 'last_month') {
                $start = clone $now;
                $start->modify('first day of last month');
                $end = clone $now;
                $end->modify('last day of last month');
                $date_conditions[] = $wpdb->prepare(
                    "{$date_column} >= %s AND {$date_column} <= %s",
                    $start->format('Y-m-d 00:00:00'),
                    $end->format('Y-m-d 23:59:59')
                );
            } elseif ($post_date_range === 'year' || $post_date_range === 'this_year') {
                $date_conditions[] = $wpdb->prepare("{$date_column} >= %s", $now->format('Y-01-01 00:00:00'));
            }
        }

        $post_source      = isset($_POST['source']) ? sanitize_text_field(wp_unslash($_POST['source'])) : '';
        $post_rep_id      = isset($_POST['rep_id']) ? sanitize_text_field(wp_unslash($_POST['rep_id'])) : '';
        $post_call_status = isset($_POST['call_status']) ? sanitize_text_field(wp_unslash($_POST['call_status'])) : '';
        $post_quality     = isset($_POST['quality']) ? sanitize_text_field(wp_unslash($_POST['quality'])) : '';
        $post_attention   = isset($_POST['attention']) ? sanitize_key(wp_unslash($_POST['attention'])) : '';
        $post_search      = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';
        $post_sort        = isset($_POST['sort']) ? sanitize_key(wp_unslash($_POST['sort'])) : 'newest';
        $sort_options     = array(
            'newest'   => 'l.created_at DESC, l.id DESC',
            'oldest'   => 'l.created_at ASC, l.id ASC',
            'name_asc' => 'l.first_name ASC, l.last_name ASC, l.id DESC',
            'name_desc' => 'l.first_name DESC, l.last_name DESC, l.id DESC',
        );
        $order_by = isset($sort_options[$post_sort]) ? $sort_options[$post_sort] : $sort_options['newest'];

        // New and Contacted remain valid lifecycle stages but are intentionally omitted from this sales board.
        $where[] = "l.stage NOT IN ('New', 'Contacted')";

        if (!empty($post_source)) {
            $where[] = $wpdb->prepare("source = %s", $post_source);
        }
        if ($post_rep_id !== '') {
            $where[] = $wpdb->prepare("assigned_rep_id = %d", intval($post_rep_id));
        }
        if (!empty($post_call_status)) {
            $where[] = $wpdb->prepare("call_status = %s", $post_call_status);
        }
        if (!empty($post_quality)) {
            if ('Qualified Awaiting Assignment' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND handoff_status IN ('awaiting_manager', 'exception')";
            } elseif ('Routing Exception' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND handoff_status = 'exception'";
            } elseif ('Qualified Plan Received' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND floor_plan_received = 1";
            } elseif ('Qualified Plan Pending' === $post_quality) {
                $where[] = "lead_quality = 'Qualified' AND COALESCE(floor_plan_received, 0) = 0";
            } elseif ('Pending' === $post_quality) {
                $where[] = "(lead_quality = '' OR lead_quality IS NULL)";
            } else {
                $where[] = $wpdb->prepare("lead_quality = %s", $post_quality);
            }
        }
        if ('stale_updates' === $post_attention) {
            $cutoff = IDLM_Admin_Lead_Notifications::get_stale_lead_cutoff();
            $where[] = $wpdb->prepare(
                "l.assigned_rep_id > 0 AND l.stage NOT IN ('Won', 'Lost') AND COALESCE(l.assigned_at, l.created_at) < %s AND " . IDLM_Admin_Lead_Notifications::get_no_recent_assigned_rep_action_sql($cutoff, 'l'),
                $cutoff
            );
        }
        if (!empty($post_search)) {
            $search = '%' . $wpdb->esc_like($post_search) . '%';
            $where[] = $wpdb->prepare("(first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)", $search, $search, $search, $search);
        }

        $monthly_won_conditions = $where;
        $where = array_merge($where, $date_conditions);
        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        // Query leads
        $now_mysql = current_time('mysql');
        $stale_cutoff = IDLM_Admin_Lead_Notifications::get_stale_lead_cutoff();
        $needs_update_sql = $wpdb->prepare(
            "CASE WHEN l.assigned_rep_id > 0
                AND l.stage NOT IN ('Won', 'Lost')
                AND COALESCE(l.assigned_at, l.created_at) < %s
                AND " . IDLM_Admin_Lead_Notifications::get_no_recent_assigned_rep_action_sql($stale_cutoff, 'l') . "
             THEN 1 ELSE 0 END",
            $stale_cutoff
        );
        $leads = $wpdb->get_results($wpdb->prepare(
            "SELECT l.id, l.first_name, l.last_name, l.email, l.phone, l.budget, l.quote_value, l.deal_value, l.quote_file, l.property_location, l.source, l.stage, l.call_status, l.assigned_rep_id, l.is_duplicate,
                    u.display_name as rep_name,
                    {$needs_update_sql} as needs_update,
                    (SELECT COUNT(t.id) FROM {$wpdb->prefix}idlm_tasks t WHERE t.lead_id = l.id AND t.status = 'pending') as pending_tasks_count,
                    (SELECT COUNT(t.id) FROM {$wpdb->prefix}idlm_tasks t WHERE t.lead_id = l.id AND t.status = 'pending' AND t.due_date IS NOT NULL AND t.due_date < %s) as overdue_tasks_count
             FROM {$table_leads} l
             LEFT JOIN {$wpdb->users} u ON l.assigned_rep_id = u.ID
             {$where_clause} ORDER BY {$order_by}",
            $now_mysql
        ));

        // Group leads by stage
        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $stages = array_values(array_diff($stages, array('New', 'Contacted')));
        $grouped_leads = array();
        foreach ($stages as $stage) {
            $grouped_leads[$stage] = array();
        }
        if (!empty($leads)) {
            foreach ($leads as $lead) {
                $stage = !empty($lead->stage) ? $lead->stage : 'New';
                if (!isset($grouped_leads[$stage])) {
                    $grouped_leads[$stage] = array();
                }
                $grouped_leads[$stage][] = $lead;
            }
        }

        // Board summary cards describe the same filtered population as the board.
        $stats_conditions = $where;
        $stats_conditions[] = 'l.is_duplicate = 0';
        $stats_where = 'WHERE ' . implode(' AND ', $stats_conditions);
        $active_stage_sql = "l.stage NOT IN ('Won', 'Lost')";

        // Every dynamic filter fragment is fixed SQL or was individually prepared when constructed above.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $summary = $wpdb->get_row(
            "SELECT
                COUNT(*) AS total_filtered
             FROM {$table_leads} l {$stats_where}"
        );

        // Current-month wins use the actual Won transition timestamp, independently of the board's date-basis filter.
        $month_start = new DateTime('first day of this month 00:00:00', wp_timezone());
        $next_month_start = clone $month_start;
        $next_month_start->modify('first day of next month');
        $monthly_won_conditions[] = "l.stage = 'Won'";
        $monthly_won_conditions[] = $wpdb->prepare('l.conversion_time >= %s', $month_start->format('Y-m-d H:i:s'));
        $monthly_won_conditions[] = $wpdb->prepare('l.conversion_time < %s', $next_month_start->format('Y-m-d H:i:s'));
        $monthly_won_where = 'WHERE ' . implode(' AND ', $monthly_won_conditions);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $monthly_won_summary = $wpdb->get_row(
            "SELECT COUNT(*) AS won_current_month, SUM(COALESCE(l.deal_value, 0)) AS won_revenue_current_month
             FROM {$table_leads} l {$monthly_won_where}"
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $pipeline_rows = $wpdb->get_results(
            "SELECT l.stage, SUM(COALESCE(l.quote_value, 0)) AS quote_total, SUM(COALESCE(l.deal_value, 0)) AS deal_total
             FROM {$table_leads} l {$stats_where} AND {$active_stage_sql}
             GROUP BY l.stage"
        );
        $stage_total_mappings = self::get_kanban_stage_total_mappings($stages);
        $open_pipeline_value = 0.0;
        foreach ($pipeline_rows as $pipeline_row) {
            $total_source = isset($stage_total_mappings[$pipeline_row->stage]) ? $stage_total_mappings[$pipeline_row->stage] : 'none';
            if ('quote_value' === $total_source) {
                $open_pipeline_value += floatval($pipeline_row->quote_total);
            } elseif ('deal_value' === $total_source) {
                $open_pipeline_value += floatval($pipeline_row->deal_total);
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $followup_leads = $wpdb->get_results(
            "SELECT l.call_status, l.callback_datetime, l.updated_at, l.created_at
             FROM {$table_leads} l {$stats_where} AND {$active_stage_sql}"
        );
        $reminder_rules = self::get_reminder_rules();
        $today_end = strtotime(current_time('Y-m-d 23:59:59'));
        $followups_due = 0;
        foreach ($followup_leads as $followup_lead) {
            $callback_due = 'Call Back Requested' === $followup_lead->call_status && !empty($followup_lead->callback_datetime) && strtotime($followup_lead->callback_datetime) <= $today_end;
            $overdue = self::check_lead_overdue_status($followup_lead, $reminder_rules);
            if ($callback_due || ($overdue && !empty($overdue['overdue']))) {
                $followups_due++;
            }
        }

        wp_send_json_success(array(
            'leads' => $grouped_leads,
            'stats' => array(
                'total_filtered' => $summary ? intval($summary->total_filtered) : 0,
                'followups_due' => $followups_due,
                'open_pipeline_value' => round($open_pipeline_value, 2),
                'won_current_month' => $monthly_won_summary ? intval($monthly_won_summary->won_current_month) : 0,
                'won_revenue_current_month' => $monthly_won_summary ? round(floatval($monthly_won_summary->won_revenue_current_month), 2) : 0,
                'stale_lead_count' => IDLM_Admin_Lead_Notifications::get_stale_lead_count(true),
            ),
        ));
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
        $file_urls = $floor_plan_files;
        if (!empty($form_fields['quote_file'])) { $file_urls[] = esc_url_raw($form_fields['quote_file']); }
        foreach ($file_urls as $file_url) {
            $attachment_id = attachment_url_to_postid($file_url);
            $owner_lead = $attachment_id ? absint(get_post_meta($attachment_id, '_idlm_attachment_lead_id', true)) : 0;
            if ($owner_lead && $owner_lead !== $lead_id) {
                wp_send_json_error(array('message' => 'An uploaded file belongs to another lead.'));
            }
        }
        global $wpdb;
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
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
        if (!$lead_id || !self::current_user_can_access_lead($lead_id)) {
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

        // Use WordPress core functions to handle the upload
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        // Let WordPress handle the upload as an attachment
        $attachment_id = media_handle_upload('file', 0);

        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => $attachment_id->get_error_message()), 400);
        }

        update_post_meta($attachment_id, '_idlm_attachment_purpose', $purpose);
        update_post_meta($attachment_id, '_idlm_attachment_lead_id', $lead_id);
        update_post_meta($attachment_id, '_idlm_attachment_owner_id', get_current_user_id());
        update_post_meta($attachment_id, '_idlm_attachment_created_at', current_time('mysql'));

        $file_url = wp_get_attachment_url($attachment_id);

        if (!$file_url) {
            wp_delete_attachment($attachment_id, true);
            wp_send_json_error(array('message' => 'Failed to get uploaded file URL.'), 500);
        }

        wp_send_json_success(array(
            'url' => $file_url,
            'attachment_id' => $attachment_id
        ));
    }

    /**
     * AJAX Endpoint: Get the total number of leads in the database for reset verification
     */
    public static function ajax_get_total_leads_count() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied. Only administrators can perform this action.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total_leads = intval($wpdb->get_var("SELECT COUNT(id) FROM {$table_leads}"));

        wp_send_json_success(array('count' => $total_leads));
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

            $result = IDLM_DB::upsert_inbound_lead($lead_data, 'csv', $lead_data);

            if ($result) {
                if (!empty($result['requires_duplicate_review']) || !empty($result['matched'])) {
                    $duplicates++;
                } else {
                    $imported++;
                }

                $lead_id = absint($result['lead_id']);

                // Apply dynamic routing assignments if checked
                if ($run_routing && !empty($result['created'])) {
                    IDLM_Integrations::route_lead_assignment($lead_id, $lead_data['source']);
                }

                // Trigger server-side Conversion API events if stage matches conversion hooks or quality is Qualified
                if ($run_capi && !empty($result['created']) && (in_array($lead_data['stage'], array('Qualified', 'Quote Shared', 'Won'), true) || $lead_data['lead_quality'] === 'Qualified')) {
                    $capi_stage = in_array($lead_data['stage'], array('Qualified', 'Quote Shared', 'Won')) ? $lead_data['stage'] : 'Qualified';
                    // Triggers Action Scheduler background conversion CAPI enqueuing automatically
                    do_action('idlm_lead_stage_converted', $lead_id, $capi_stage, (object) array('stage' => ''));
                }
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $candidates = $wpdb->get_results($wpdb->prepare($sql, $params));
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
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        if (!$lead_id) {
            wp_send_json_error(array('message' => 'Invalid Lead ID'));
        }
        if (!self::current_user_can_view_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You cannot view tasks for this lead.'));
        }

        global $wpdb;
        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $tasks = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_tasks}
             WHERE lead_id = %d
             ORDER BY
                CASE
                    WHEN status = 'pending' AND due_date IS NOT NULL AND due_date <> '0000-00-00 00:00:00' AND due_date < %s THEN 0
                    WHEN status = 'pending' THEN 1
                    ELSE 2
                END ASC,
                CASE WHEN due_date IS NULL OR due_date = '0000-00-00 00:00:00' THEN 1 ELSE 0 END ASC,
                due_date ASC,
                id DESC",
            $lead_id,
            current_time('mysql')
        ));

        // Format dates nicely
        foreach ($tasks as &$task) {
            $task->due_date_formatted = $task->due_date && $task->due_date !== '0000-00-00 00:00:00' ? self::format_datetime($task->due_date) : '';
            $task->due_date_raw = $task->due_date && $task->due_date !== '0000-00-00 00:00:00' ? wp_date('Y-m-d\TH:i', strtotime($task->due_date)) : '';
            $task->is_overdue = ($task->status === 'pending' && $task->due_date && $task->due_date !== '0000-00-00 00:00:00' && strtotime($task->due_date) < current_time('timestamp'));
        }

        wp_send_json_success(array('tasks' => $tasks));
    }

    /**
     * AJAX Endpoint: Add task for a lead
     */
    public static function ajax_add_lead_task() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $lead_id = isset($_POST['lead_id']) ? intval($_POST['lead_id']) : 0;
        $title = isset($_POST['title']) ? trim(sanitize_text_field(wp_unslash($_POST['title']))) : '';
        $due_date = isset($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : '';
        $priority = isset($_POST['priority']) ? sanitize_text_field(wp_unslash($_POST['priority'])) : 'Medium';
        $task_type = isset($_POST['task_type']) ? sanitize_text_field(wp_unslash($_POST['task_type'])) : 'General';

        if (!$lead_id || '' === $title) {
            wp_send_json_error(array('message' => 'Missing required fields.'));
        }
        if (strlen($title) > 255) {
            wp_send_json_error(array('message' => 'Task title must be 255 characters or fewer.'));
        }
        if (!in_array($priority, array('Low', 'Medium', 'High'), true)) {
            wp_send_json_error(array('message' => 'Invalid task priority.'));
        }
        if (!in_array($task_type, self::manual_task_types(), true)) {
            wp_send_json_error(array('message' => 'Invalid task type.'));
        }
        $formatted_due_date = self::normalize_manual_task_due_date($due_date);
        if (false === $formatted_due_date) {
            wp_send_json_error(array('message' => 'Please provide a valid due date and time.'));
        }
        if ($formatted_due_date && strtotime($formatted_due_date) <= current_time('timestamp')) {
            wp_send_json_error(array('message' => 'Please choose a future due date and time.'));
        }
        if (!self::current_user_can_access_lead($lead_id)) {
            wp_send_json_error(array('message' => 'You can add tasks only to leads currently assigned to you.'));
        }

        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $lead = $wpdb->get_row($wpdb->prepare("SELECT assigned_rep_id, first_name, last_name FROM {$table_leads} WHERE id = %d", $lead_id));
        if (!$lead) {
            wp_send_json_error(array('message' => 'Lead not found.'));
        }

        $assigned_rep_id = $lead->assigned_rep_id;

        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->insert($table_tasks, array(
            'lead_id' => $lead_id,
            'assigned_rep_id' => $assigned_rep_id,
            'title' => $title,
            'task_type' => $task_type,
            'priority' => $priority,
            'status' => 'pending',
            'due_date' => $formatted_due_date,
            'created_by' => get_current_user_id(),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ));

        if ($result) {
            $task_id = $wpdb->insert_id;
            
            // Log in activity log
            $activity_details = sprintf('Added task: "%s" (Priority: %s, Due: %s)', $title, $priority, !empty($due_date) ? self::format_datetime($formatted_due_date) : 'No due date');
            IDLM_DB::log_activity($lead_id, 'task', $activity_details);

            wp_send_json_success(array('message' => 'Task added successfully', 'task_id' => $task_id));
        } else {
            wp_send_json_error(array('message' => 'Failed to insert task.'));
        }
    }

    /**
     * AJAX Endpoint: Toggle task completion status
     */
    public static function ajax_toggle_task_status() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_key($_POST['status']) : 'pending';

        if (!$task_id) {
            wp_send_json_error(array('message' => 'Invalid Task ID'));
        }
        if (!in_array($status, array('pending', 'completed'), true)) {
            wp_send_json_error(array('message' => 'Invalid task status.'));
        }

        global $wpdb;
        $table_tasks = $wpdb->prefix . 'idlm_tasks';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_tasks} WHERE id = %d", $task_id));
        if (!$task) {
            wp_send_json_error(array('message' => 'Task not found.'));
        }
        if (!self::current_user_can_access_lead($task->lead_id)) {
            wp_send_json_error(array('message' => 'You can update tasks only for leads currently assigned to you.'));
        }
        if (self::is_system_qualification_task($task)) {
            wp_send_json_error(array('message' => 'Qualification tasks are updated automatically from outreach evidence and cannot be changed manually.'));
        }

        $completed_at = ($status === 'completed') ? current_time('mysql') : null;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $table_tasks,
            array(
                'status' => $status,
                'completed_at' => $completed_at,
                'updated_at' => current_time('mysql')
            ),
            array('id' => $task_id)
        );

        if ($result !== false) {
            // Log in activity log
            $activity_details = sprintf('Marked task "%s" as %s', $task->title, $status);
            IDLM_DB::log_activity($task->lead_id, 'task', $activity_details);

            wp_send_json_success(array('message' => 'Task status updated.', 'status' => $status));
        } else {
            wp_send_json_error(array('message' => 'Failed to update task status.'));
        }
    }

    /**
     * AJAX Endpoint: Delete task
     */
    public static function ajax_delete_lead_task() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
        if (!$task_id) {
            wp_send_json_error(array('message' => 'Invalid Task ID'));
        }

        global $wpdb;
        $table_tasks = $wpdb->prefix . 'idlm_tasks';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_tasks} WHERE id = %d", $task_id));
        if (!$task) {
            wp_send_json_error(array('message' => 'Task not found.'));
        }
        if (!self::current_user_can_access_lead($task->lead_id)) {
            wp_send_json_error(array('message' => 'You can delete tasks only for leads currently assigned to you.'));
        }
        if (self::is_system_qualification_task($task)) {
            wp_send_json_error(array('message' => 'Qualification tasks are managed automatically and cannot be deleted manually.'));
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete($table_tasks, array('id' => $task_id));

        if ($result) {
            // Log in activity log
            $activity_details = sprintf('Deleted task: "%s"', $task->title);
            IDLM_DB::log_activity($task->lead_id, 'task', $activity_details);

            wp_send_json_success(array('message' => 'Task deleted successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete task.'));
        }
    }
    private static function manual_task_types() {
        return array('General', 'Call', 'Email', 'Site Visit', 'Design Mockup', 'Quote Revision');
    }

    /**
     * A blank due date is valid; otherwise accept only the datetime-local
     * contract emitted by the task form and store a normalized site-local value.
     */
    private static function normalize_manual_task_due_date($due_date) {
        if ('' === $due_date) {
            return null;
        }
        $parsed = DateTime::createFromFormat('!Y-m-d\\TH:i', $due_date, wp_timezone());
        $errors = DateTime::getLastErrors();
        if (!$parsed || (is_array($errors) && ($errors['warning_count'] || $errors['error_count'])) || $parsed->format('Y-m-d\\TH:i') !== $due_date) {
            return false;
        }
        return $parsed->format('Y-m-d H:i:s');
    }

    private static function is_system_qualification_task($task) {
        return 'Qualification' === (string) ($task->task_type ?? '');
    }

    public static function ajax_get_rep_tasks() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';
        if ('reschedule' === $operation) {
            IDLM_Task_Queue_Service::ajax_reschedule();
        }
        if ('cleanup_stale' === $operation) {
            IDLM_Task_Queue_Service::ajax_cleanup_stale();
        }
        if (!self::current_user_has_rep_access()) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $restricted_user_id = IDLM_Lead_Access_Policy::restriction_user_id();

        global $wpdb;
        $table_tasks = $wpdb->prefix . 'idlm_tasks';
        $table_leads = $wpdb->prefix . 'idlm_leads';

        $where = array();
        // If rep is not admin and can't see all leads, only query their assigned leads/tasks
        if ($restricted_user_id) {
            $where[] = $wpdb->prepare("(t.assigned_rep_id = %d OR l.assigned_rep_id = %d)", $restricted_user_id, $restricted_user_id);
        }

        // Default to bounded daily attention work; completed is a separate
        // history view rather than a peer of the work queue.
        $view = isset($_POST['view']) ? sanitize_key(wp_unslash($_POST['view'])) : 'attention';
        if ('completed' === $view) {
            $where[] = "t.status = 'completed'";
        } else {
            $where[] = "t.status = 'pending'";
            $where[] = "l.stage <> 'Lost'";
        }

        $where_sql = '';
        if (!empty($where)) {
            $where_sql = "WHERE " . implode(" AND ", $where);
        }

        // Query tasks joining leads to display lead name
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $tasks = $wpdb->get_results("
            SELECT t.*, l.first_name, l.last_name, l.phone, l.email, l.source, l.stage
            FROM {$table_tasks} t
            INNER JOIN {$table_leads} l ON t.lead_id = l.id
            {$where_sql}
            ORDER BY t.status ASC, t.due_date ASC, t.id DESC
        ");

        // Keep the six scheduled qualification tasks internally, but present one
        // actionable follow-up plan per lead for every representative.
        if ('completed' !== $view && $tasks) {
            $visible_tasks = array();
            $qualification_groups = array();
            foreach ($tasks as $task) {
                if ('Qualification' !== $task->task_type) {
                    $visible_tasks[] = $task;
                    continue;
                }
                $lead_id = absint($task->lead_id);
                if (!isset($qualification_groups[$lead_id])) {
                    $qualification_groups[$lead_id] = array(
                        'task' => $task,
                        'next_due' => $task->due_date,
                    );
                } elseif ($task->due_date && (!$qualification_groups[$lead_id]['next_due'] || strtotime($task->due_date) < strtotime($qualification_groups[$lead_id]['next_due']))) {
                    $qualification_groups[$lead_id]['next_due'] = $task->due_date;
                }
            }

            if ($qualification_groups) {
                foreach ($qualification_groups as $lead_id => $group) {
                    $summary = clone $group['task'];
                    $progress = IDLM_DB::get_followup_compliance($lead_id);
                    $summary->id = 'qualification-summary-' . $lead_id;
                    $summary->title = 'Follow-up plan';
                    $summary->due_date = $group['next_due'];
                    $summary->is_qualification_summary = true;
                    $summary->complete_days = (int) $progress['complete_days'];
                    $summary->required_days = 6;
                    $summary->today_calls = (int) $progress['active_cycle']['calls'];
                    $summary->today_whatsapp = (int) $progress['active_cycle']['whatsapp'];
                    $summary->calls_target = (int) $progress['targets']['calls'];
                    $summary->whatsapp_target = (int) $progress['targets']['whatsapp'];
                    $visible_tasks[] = $summary;
                }
            }
            $tasks = $visible_tasks;
            usort($tasks, static function ($a, $b) {
                return strtotime($a->due_date ?: '9999-12-31') <=> strtotime($b->due_date ?: '9999-12-31');
            });
        }

        $today_start = (new DateTime(wp_date('Y-m-d', current_time('timestamp'), wp_timezone()) . ' 00:00:00', wp_timezone()))->getTimestamp();
        $tomorrow_start = strtotime('+1 day', $today_start);
        $upcoming_end = strtotime('+8 days', $today_start);
        $attention = array('overdue' => 0, 'due_today' => 0, 'upcoming' => 0, 'follow_up_plans' => 0);
        $visible_tasks = array();

        // Format and group attention work. Undated backlog is deliberately not
        // shown as daily work; users can schedule it from the lead drawer.
        foreach ($tasks as &$task) {
            $task->lead_name = esc_html($task->first_name . ' ' . $task->last_name);
            $task->due_date_formatted = $task->due_date && $task->due_date !== '0000-00-00 00:00:00' ? self::format_datetime($task->due_date) : 'No due date';
            $task->is_overdue = ($task->status === 'pending' && $task->due_date && $task->due_date !== '0000-00-00 00:00:00' && strtotime($task->due_date) < current_time('timestamp'));
            $task->queue_group = '';
            if ('completed' === $view) {
                $visible_tasks[] = $task;
                continue;
            }
            if (!empty($task->is_qualification_summary)) {
                $task->queue_group = 'follow_up_plans';
            } elseif ($task->due_date) {
                $due_timestamp = strtotime($task->due_date);
                if ($due_timestamp < $today_start) {
                    $task->queue_group = 'overdue';
                } elseif ($due_timestamp < $tomorrow_start) {
                    $task->queue_group = 'due_today';
                } elseif ($due_timestamp < $upcoming_end) {
                    $task->queue_group = 'upcoming';
                }
            }
            if ($task->queue_group) {
                $attention[$task->queue_group]++;
                $visible_tasks[] = $task;
            }
        }

        wp_send_json_success(array('tasks' => $visible_tasks, 'attention' => $attention, 'view' => $view));
    }
}
