<?php
/**
 * Focused authenticated lead-detail AJAX read handler.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Admin_Lead_Reads extends IDLM_Admin {
    public static function ajax_get_lead_details() {
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $lead = $wpdb->get_row($wpdb->prepare("
            SELECT l.*, u.display_name AS assigned_rep_name
            FROM {$wpdb->prefix}idlm_leads l
            LEFT JOIN {$wpdb->users} u ON l.assigned_rep_id = u.ID
            WHERE l.id = %d
        ", $lead_id));
        if (!$lead) {
            wp_send_json_error(array('message' => 'Lead not found'));
        }

        $quote_action = IDLM_Quotations::lead_has_quotes($lead_id) ? 'list' : 'new';
        $quote_url = add_query_arg(
            array('page' => 'idlm_quotations', 'lead_id' => $lead_id, 'action' => $quote_action),
            admin_url('admin.php')
        );
        $workspace_url = isset($_POST['workspace_url']) ? esc_url_raw(wp_unslash($_POST['workspace_url'])) : '';
        if ($workspace_url) {
            $validated_workspace_url = wp_validate_redirect($workspace_url, '');
            if ($validated_workspace_url) {
                $workspace_base = remove_query_arg(
                    array('idlm_view', 'workspace', 'action', 'quote_id', 'lead_id', 'revision_id', 'quote_search', 'catalogue_search', 'status', 'created_by', 'date_from', 'date_to', 'idlm_notice', 'idlm_error'),
                    $validated_workspace_url
                );
                $quote_url = add_query_arg(
                    array('idlm_view' => 'quotations', 'lead_id' => $lead_id, 'action' => $quote_action),
                    $workspace_base
                );
            }
        }

        $currency_code = get_option('idlm_currency_code', 'INR');
        $currency_symbols = array(
            'INR' => '₹',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AED' => 'د.إ',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'SGD' => 'S$'
        );
        $currency_symbol = isset($currency_symbols[$currency_code]) ? $currency_symbols[$currency_code] : $currency_code;
        $stages = get_option('idlm_custom_stages', array('New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', 'Won', 'Lost'));
        $floor_plan_files = IDLM_Admin_Presenter::normalize_floor_plan_files(
            isset($lead->floor_plan_files) ? $lead->floor_plan_files : '',
            isset($lead->floor_plan_file) ? $lead->floor_plan_file : ''
        );

        // Fetch duplicate leads if any
        $duplicates = array();
        if ($lead->is_duplicate == 0) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $duplicates = $wpdb->get_results($wpdb->prepare(
                "SELECT id, first_name, last_name, created_at FROM {$wpdb->prefix}idlm_leads
                 WHERE is_duplicate = 1 AND duplicate_of_id = %d",
                $lead_id
            ));
        }

        // Fetch activity logs inline to speed up perceived load time
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
                if (in_array($log->activity_type, array('comment', 'lost_remark', 'whatsapp_reply', 'qualification_handoff_remark'), true)) {
                    $comments[] = $log;
                }
            }
        }
        $recent_comments = array_slice($comments, 0, 5);

        $latest_whatsapp_reply = null;
        foreach ($logs as $log) {
            $is_legacy_whatsapp_reply = 'comment' === $log->activity_type && (
                0 === strpos($log->details, '[WhatsApp Reply]:') ||
                0 === strpos($log->details, '[Direct WA Reply]:')
            );
            if ('whatsapp_reply' === $log->activity_type || $is_legacy_whatsapp_reply) {
                $latest_whatsapp_reply = $log;
                break;
            }
        }

        $whatsapp_read_ids = self::get_whatsapp_read_activity_ids();
        $last_read_whatsapp_id = isset($whatsapp_read_ids[$lead_id]) ? absint($whatsapp_read_ids[$lead_id]) : 0;
        $has_unread_whatsapp = $latest_whatsapp_reply && absint($latest_whatsapp_reply->id) > $last_read_whatsapp_id;
        $show_presales_compliance = self::current_user_is_pre_sales();
        $followup = $show_presales_compliance ? IDLM_DB::get_followup_compliance($lead_id) : null;
        $qualification_fields = array(
            'project_name' => 'Project name', 'property_location' => 'Location', 'budget' => 'Budget',
            'possession_date' => 'Possession date', 'property_type' => 'Project type', 'project_timeline' => 'Timeline',
        );
        $missing_qualification_fields = array();
        foreach ($qualification_fields as $field => $label) {
            if (empty($lead->$field)) {
                $missing_qualification_fields[] = $label;
            }
        }
        $qualification_ready = !$missing_qualification_fields && IDLM_DB::budget_to_rupees($lead->budget) >= 400000;

        // Render HTML for Drawer
        ob_start();
        ?>
        <!-- Header -->
        <header class="idlm-drawer-header">
            <div class="idlm-drawer-identity">
                <div class="idlm-drawer-avatar"><?php echo esc_html($lead->first_name ? strtoupper($lead->first_name[0]) : '?'); ?></div>
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <h2 id="lead-title"><?php echo esc_html($lead->first_name . ' ' . $lead->last_name); ?></h2>
                    <?php if ($lead->source): ?>
                        <span class="idlm-drawer-chip idlm-badge-<?php echo esc_attr(strtolower(str_replace(array(' ', '/', '-'), '', $lead->source))); ?>" style="align-self: flex-start;"><?php echo esc_html($lead->source); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="idlm-drawer-status-grid" role="group" aria-label="Lead status summary">
                <div class="idlm-drawer-status-primary">
                    <div class="idlm-drawer-status-item">
                        <span class="idlm-drawer-status-label">Call status</span>
                        <span class="idlm-drawer-status-value"><?php echo esc_html($lead->call_status ? $lead->call_status : 'Not Called'); ?></span>
                    </div>
                    <div class="idlm-drawer-status-item">
                        <?php if (self::current_user_is_pre_sales()): ?>
                            <span class="idlm-drawer-status-label">Stage</span>
                            <span class="idlm-drawer-status-value"><?php echo esc_html($lead->stage ? $lead->stage : 'New'); ?></span>
                        <?php else: ?>
                            <label class="idlm-drawer-status-label" for="idlm-drawer-stage-select">Stage</label>
                            <select id="idlm-drawer-stage-select" class="idlm-drawer-stage-select idlm-quick-edit" data-field="stage" data-edit-context="drawer_header" data-id="<?php echo esc_attr($lead->id); ?>" data-original-value="<?php echo esc_attr($lead->stage ? $lead->stage : 'New'); ?>" data-property-location="<?php echo esc_attr($lead->property_location); ?>" data-quote-value="<?php echo esc_attr(isset($lead->quote_value) ? $lead->quote_value : ''); ?>" data-quote-file="<?php echo esc_url(isset($lead->quote_file) ? $lead->quote_file : ''); ?>" aria-label="Change lead stage">
                                <?php foreach ($stages as $stage): ?>
                                    <option value="<?php echo esc_attr($stage); ?>" <?php selected($lead->stage ? $lead->stage : 'New', $stage); ?>><?php echo esc_html($stage); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="idlm-drawer-status-item">
                        <span class="idlm-drawer-status-label">Assigned rep</span>
                        <span class="idlm-drawer-status-value"><?php echo esc_html($lead->assigned_rep_name ? $lead->assigned_rep_name : 'Unassigned'); ?></span>
                    </div>
                    <div class="idlm-drawer-status-item">
                        <span class="idlm-drawer-status-label">Lead quality</span>
                        <span class="idlm-drawer-status-value"><?php echo esc_html(self::get_qualification_status_label($lead)); ?></span>
                    </div>
                </div>
                <div class="idlm-drawer-status-context" aria-label="Additional lead context">
                    <?php if ('Unqualified' === $lead->lead_quality && !empty($lead->disqualification_reason)): ?>
                    <span class="idlm-drawer-status-context-item">
                        <span class="idlm-drawer-status-context-label">Disqualification:</span>
                        <span class="idlm-drawer-status-context-value" title="<?php echo esc_attr(ucwords(str_replace('_', ' ', $lead->disqualification_reason))); ?>"><?php echo esc_html(ucwords(str_replace('_', ' ', $lead->disqualification_reason))); ?></span>
                    </span>
                    <?php endif; ?>
                    <?php if ('exception' === $lead->handoff_status): ?>
                    <span class="idlm-drawer-status-context-item">
                        <span class="idlm-drawer-status-context-label">Routing exception:</span>
                        <span class="idlm-drawer-status-context-value" title="<?php echo esc_attr($lead->handoff_exception_reason); ?>"><?php echo esc_html($lead->handoff_exception_reason); ?></span>
                    </span>
                    <?php endif; ?>
                    <?php if ('Lost' === $lead->stage && !empty($lead->lost_reason)): ?>
                    <span class="idlm-drawer-status-context-item">
                        <span class="idlm-drawer-status-context-label">Lost reason:</span>
                        <span class="idlm-drawer-status-context-value" title="<?php echo esc_attr($lead->lost_reason); ?>"><?php echo esc_html($lead->lost_reason); ?></span>
                    </span>
                    <?php endif; ?>
                    <span class="idlm-drawer-status-context-item">
                        <span class="idlm-drawer-status-context-label">Created:</span>
                        <span class="idlm-drawer-status-context-value"><?php echo esc_html(self::format_datetime($lead->created_at)); ?></span>
                    </span>
                </div>
            </div>

            <button type="button" class="idlm-close" aria-label="Close" onclick="closeLeadDrawer()">&times;</button>
        </header>

        <nav class="idlm-drawer-tabs" aria-label="Lead sections">
            <button class="idlm-drawer-tab active" type="button" onclick="switchDrawerTab(event, 'drawer-tab-info')">Details</button>
            <button class="idlm-drawer-tab" type="button" onclick="switchDrawerTab(event, 'drawer-tab-activity')">Activity Log & Comments</button>
            <button class="idlm-drawer-tab" type="button" onclick="switchDrawerTab(event, 'drawer-tab-tasks')">Tasks <span class="idlm-drawer-tab-notification" id="idlm-drawer-overdue-task-count" aria-label="No overdue tasks" hidden>0</span></button>
        </nav>

        <?php if ($has_unread_whatsapp): ?>
            <div class="idlm-whatsapp-reply-alert" role="status">
                <span class="dashicons dashicons-whatsapp idlm-whatsapp-reply-alert-icon" aria-hidden="true"></span>
                <div>
                    <strong>New WhatsApp reply</strong>
                    <span><?php echo wp_kses_post(self::render_activity_details(preg_replace('/^\[(?:WhatsApp Reply|Direct WA Reply)\]:\s*/', '', $latest_whatsapp_reply->details))); ?></span>
                    <small><?php echo esc_html(self::format_datetime($latest_whatsapp_reply->created_at)); ?></small>
                </div>
            </div>
        <?php endif; ?>

        <div class="idlm-drawer-body">
            <div id="drawer-tab-info" class="idlm-drawer-tab-content active">
                <?php if ($show_presales_compliance): ?>
                <section class="idlm-drawer-panel idlm-presales-compliance" data-lead-id="<?php echo esc_attr($lead->id); ?>" data-has-floor-plan="<?php echo empty($lead->floor_plan_received) ? '0' : '1'; ?>">
                    <div class="idlm-drawer-panel-head">
                        <h3 class="idlm-drawer-panel-title">PRE-SALES 6-CYCLE FOLLOW-UP</h3>
                        <span><?php echo esc_html($followup['message']); ?></span>
                    </div>
                    <div class="idlm-presales-day-grid">
                        <?php foreach ($followup['days'] as $index => $day): ?>
                            <div class="idlm-presales-day <?php echo $day['complete'] ? 'is-complete' : ''; ?>">
                                <strong>Cycle <?php echo esc_html($index + 1); ?></strong>
                                <span><?php echo $day['date'] ? esc_html(wp_date('D, M j', strtotime($day['date']))) : 'Pending'; ?></span>
                                <small><?php echo esc_html($day['calls']); ?>/<?php echo esc_html($followup['targets']['calls']); ?> calls · <?php echo esc_html($day['whatsapp']); ?>/<?php echo esc_html($followup['targets']['whatsapp']); ?> WhatsApp</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="idlm-presales-actions">
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-log-presales-touch" data-kind="call">Log outbound call</button>
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-log-presales-touch" data-kind="whatsapp">Log WhatsApp</button>
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-log-presales-touch" data-kind="closure" <?php disabled($followup['complete_days'] < 6); ?>>Log final closure</button>
                    </div>
                    <div class="idlm-presales-decision-box" id="idlm-presales-decision-box">
                        <div>
                            <strong>Lead Decision</strong>
                            <small>Choose an outcome after logging the required evidence. <span class="idlm-decision-guidance"><?php if (!$qualification_ready): ?>Qualification needs: <?php echo esc_html(implode(', ', $missing_qualification_fields ?: array('Budget of ₹4L or above'))); ?>.
                            <?php elseif (empty($followup['eligible'])): ?>Qualify now, use structured Early Close, or continue follow-up.
                            <?php else: ?>This lead is ready for a final decision.<?php endif; ?></span></small>
                        </div>
                        <button type="button" class="idlm-btn idlm-btn-primary idlm-presales-decision" data-decision="qualify" <?php disabled(!$qualification_ready || in_array($lead->lead_quality, array('Qualified', 'Unqualified'), true)); ?>>Qualify Lead</button>
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-presales-decision" data-decision="unreachable" <?php disabled(empty($followup['eligible']) || 'Unqualified' === $lead->lead_quality); ?>>Close as Unreachable</button>
                    </div>
                    <div class="idlm-early-close-box">
                        <strong>Early close for confirmed junk or mismatch</strong>
                        <select class="idlm-input idlm-early-close-reason" aria-label="Early close reason">
                            <option value="">Select a reason…</option>
                            <optgroup label="Objective junk">
                                <option value="spam_test">Spam or test submission</option>
                                <option value="duplicate">Duplicate lead</option>
                                <option value="invalid_contact">Invalid or nonexistent contact</option>
                            </optgroup>
                            <optgroup label="Confirmed response">
                                <option value="unrelated_enquiry">Vendor, job seeker, or unrelated enquiry</option>
                                <option value="do_not_contact">Requested no further contact</option>
                                <option value="budget_below_minimum">Budget below ₹4L</option>
                                <option value="outside_service_area">Outside supported service area</option>
                                <option value="unsupported_project_type">Unsupported project type</option>
                                <option value="timeline_mismatch">Project timeline mismatch</option>
                                <option value="not_interested">Customer confirmed not interested</option>
                            </optgroup>
                            <?php if (current_user_can('manage_options')): ?>
                                <option value="administrator_exception">Other - administrator exception</option>
                            <?php endif; ?>
                        </select>
                        <textarea class="idlm-input idlm-early-close-note" rows="2" maxlength="1000" placeholder="Document the evidence and specific reason (required)"></textarea>
                        <button type="button" class="idlm-btn idlm-btn-danger idlm-log-presales-touch" data-kind="early_close">Close lead early</button>
                    </div>
                </section>
                <?php endif; ?>
                <form id="idlm-edit-lead-form">
                    <input type="hidden" name="lead_id" value="<?php echo esc_attr($lead->id); ?>">
                    <div class="idlm-drawer-workspace">
                        <div>
                            <?php if ($lead->is_duplicate): ?>
                                <div class="idlm-alert idlm-alert-warning" style="margin-bottom:15px;">
                                    <strong>Warning!</strong> Duplicate Lead detected. Linked to original lead:
                                    <a href="#" onclick="openLeadDrawer(<?php echo esc_attr($lead->duplicate_of_id); ?>)">Lead ID #<?php echo esc_html($lead->duplicate_of_id); ?></a>
                                </div>
                            <?php endif; ?>

                            <section class="idlm-drawer-panel">
                                <div class="idlm-drawer-panel-head">
                                    <h3 class="idlm-drawer-panel-title">Details</h3>
                                </div>
                                <div class="idlm-drawer-form-grid">
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-first-name">First Name</label>
                                        <input type="text" name="first_name" class="idlm-drawer-control" id="idlm-edit-first-name" value="<?php echo esc_attr($lead->first_name); ?>" required />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-last-name">Last Name</label>
                                        <input type="text" name="last_name" class="idlm-drawer-control" id="idlm-edit-last-name" value="<?php echo esc_attr($lead->last_name); ?>" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-email">Email</label>
                                        <input type="email" name="email" class="idlm-drawer-control" id="idlm-edit-email" value="<?php echo esc_attr($lead->email); ?>" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-phone">Phone</label>
                                        <input type="tel" name="phone" class="idlm-drawer-control" id="idlm-edit-phone" value="<?php echo esc_attr($lead->phone); ?>" required />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-lead-quality">Lead Quality</label>
                                        <?php if (self::current_user_is_pre_sales()): ?>
                                            <input type="hidden" name="lead_quality" value="<?php echo esc_attr($lead->lead_quality); ?>">
                                            <div class="idlm-drawer-control idlm-readonly-quality" id="idlm-edit-lead-quality"><?php echo esc_html(self::get_qualification_status_label($lead)); ?></div>
                                            <small>Use the Lead Decision controls above to qualify or close this lead.</small>
                                        <?php else: ?>
                                        <select name="lead_quality" class="idlm-drawer-control" id="idlm-edit-lead-quality">
                                            <option value="" <?php selected($lead->lead_quality, ''); ?>>Pending Qualification</option>
                                            <option value="Qualified" <?php selected($lead->lead_quality, 'Qualified'); ?>>✅ <?php echo esc_html('Qualified' === $lead->lead_quality ? self::get_qualification_status_label($lead) : 'Qualified'); ?></option>
                                            <option value="Unqualified" <?php selected($lead->lead_quality, 'Unqualified'); ?>>❌ Unqualified</option>
                                        </select>
                                        <?php endif; ?>
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-project-name">Project Name</label>
                                        <input type="text" name="project_name" class="idlm-drawer-control" id="idlm-edit-project-name" value="<?php echo esc_attr($lead->project_name); ?>" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-lead-budget">Lead Budget</label>
                                        <input type="text" name="budget" class="idlm-drawer-control" id="idlm-edit-lead-budget" value="<?php echo esc_attr($lead->budget); ?>" placeholder="e.g. <?php echo esc_attr($currency_symbol); ?>15-25 Lakhs" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-deal-value">Deal Value (<?php echo esc_html($currency_code); ?>)</label>
                                        <input <?php disabled(self::current_user_is_pre_sales()); ?> type="number" step="0.01" min="0" name="deal_value" class="idlm-drawer-control" id="idlm-edit-deal-value" value="<?php echo esc_attr($lead->deal_value ? $lead->deal_value : ''); ?>" placeholder="e.g. 150000" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-discount-percentage">Discount %</label>
                                        <input <?php disabled(self::current_user_is_pre_sales()); ?> type="number" step="0.01" min="0" max="100" name="discount_percentage" class="idlm-drawer-control" id="idlm-edit-discount-percentage" value="<?php echo esc_attr(isset($lead->discount_percentage) ? $lead->discount_percentage : '0.00'); ?>" placeholder="e.g. 10" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-timeline">Project Timeline</label>
                                        <input type="text" name="project_timeline" class="idlm-drawer-control" id="idlm-edit-timeline" value="<?php echo esc_attr($lead->project_timeline); ?>" placeholder="Immediately" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-possession-date">Possession Date</label>
                                        <input type="date" name="possession_date" class="idlm-drawer-control" id="idlm-edit-possession-date" value="<?php echo esc_attr($lead->possession_date); ?>" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-location">Property Location / Pincode</label>
                                        <input type="text" name="property_location" class="idlm-drawer-control" id="idlm-edit-location" value="<?php echo esc_attr($lead->property_location); ?>" placeholder="North Bangalore" />
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-property-type">Property Type</label>
                                        <select name="property_type" class="idlm-drawer-control" id="idlm-edit-property-type">
                                            <option value="Apartment" <?php selected($lead->property_type, 'Apartment'); ?>>Apartment</option>
                                            <option value="Villa" <?php selected($lead->property_type, 'Villa'); ?>>Villa</option>
                                            <option value="Independent House" <?php selected($lead->property_type, 'Independent House'); ?>>Independent House</option>
                                            <option value="Commercial" <?php selected($lead->property_type, 'Commercial'); ?>>Commercial</option>
                                        </select>
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-rooms">Rooms (BHK)</label>
                                        <?php $num_rooms_options = get_option('idlm_custom_num_rooms', array('1 BHK', '2 BHK', '3 BHK', '4 BHK', '4+ BHK')); ?>
                                        <select name="num_rooms" class="idlm-drawer-control" id="idlm-edit-rooms">
                                            <?php foreach ($num_rooms_options as $opt): ?>
                                                <option value="<?php echo esc_attr($opt); ?>" <?php selected($lead->num_rooms, $opt); ?>><?php echo esc_html($opt); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-floor-plan">Floor Plan Recd</label>
                                        <select name="floor_plan_received" class="idlm-drawer-control" id="idlm-edit-floor-plan" style="margin-bottom: 8px;">
                                            <option value="1" <?php selected($lead->floor_plan_received, 1); ?>>Yes</option>
                                            <option value="0" <?php selected($lead->floor_plan_received, 0); ?>>No</option>
                                        </select>
                                        <div class="idlm-drawer-file-row">
                                            <input type="hidden" name="floor_plan_file" id="idlm_floor_plan_file" value="<?php echo esc_attr(isset($floor_plan_files[0]) ? $floor_plan_files[0] : ''); ?>">
                                            <input type="hidden" name="floor_plan_files" id="idlm_floor_plan_files" value="<?php echo esc_attr(wp_json_encode($floor_plan_files)); ?>">
                                            <input type="file" class="idlm-file-input" data-input-id="idlm_floor_plan_file" data-preview-id="idlm_floor_plan_preview" accept=".pdf,.jpg,.jpeg,.png,.webp" multiple style="display: none;">
                                            <button type="button" class="idlm-drawer-btn-file idlm-direct-upload-trigger">
                                                <?php echo $floor_plan_files ? 'Add Floor Plans' : 'Upload Floor Plans'; ?>
                                            </button>
                                            <span id="idlm_floor_plan_preview" class="idlm-drawer-file-note idlm-floor-plan-file-list">
                                                <?php if ($floor_plan_files): ?>
                                                    <?php foreach ($floor_plan_files as $index => $floor_plan_url): ?>
                                                        <span class="idlm-floor-plan-file" data-url="<?php echo esc_url($floor_plan_url); ?>">
                                                            <span class="idlm-floor-plan-file-name">Floor plan <?php echo esc_html($index + 1); ?></span>
                                                            <a href="<?php echo esc_url($floor_plan_url); ?>" target="_blank" rel="noopener" class="idlm-file-link">View</a>
                                                            <a href="<?php echo esc_url($floor_plan_url); ?>" download class="idlm-file-link">Download</a>
                                                            <button type="button" class="idlm-remove-floor-plan-btn" data-url="<?php echo esc_url($floor_plan_url); ?>">Remove</button>
                                                        </span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    No file uploaded
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="idlm-drawer-panel">
                                <div class="idlm-drawer-form-grid idlm-drawer-form-grid-two">
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-quote-shared">Quote Shared</label>
                                        <select <?php disabled(self::current_user_is_pre_sales()); ?> name="quote_shared" class="idlm-drawer-control" id="idlm-edit-quote-shared" style="margin-bottom: 8px;">
                                            <option value="1" <?php selected($lead->quote_shared, 1); ?>>Yes</option>
                                            <option value="0" <?php selected($lead->quote_shared, 0); ?>>No</option>
                                        </select>
                                        <div class="idlm-drawer-file-row">
                                            <input <?php disabled(self::current_user_is_pre_sales()); ?> type="hidden" name="quote_file" id="idlm_quote_file" value="<?php echo esc_attr(isset($lead->quote_file) ? $lead->quote_file : ''); ?>">
                                            <input <?php disabled(self::current_user_is_pre_sales()); ?> type="file" class="idlm-file-input" data-input-id="idlm_quote_file" data-preview-id="idlm_quote_preview" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                                            <button <?php disabled(self::current_user_is_pre_sales()); ?> type="button" class="idlm-drawer-btn-file idlm-direct-upload-trigger">
                                                <?php echo !empty($lead->quote_file) ? 'Change File' : 'Upload Quote'; ?>
                                            </button>
                                            <span id="idlm_quote_preview" class="idlm-drawer-file-note">
                                                <?php if (!empty($lead->quote_file)): ?>
                                                    <a href="<?php echo esc_url($lead->quote_file); ?>" target="_blank" class="idlm-file-link" style="color: var(--idlm-brand-gold); font-weight: 500; text-decoration: none;">📄 View File</a>
                                                    <button <?php disabled(self::current_user_is_pre_sales()); ?> type="button" class="idlm-remove-file-btn" data-input-id="idlm_quote_file" data-preview-id="idlm_quote_preview" style="background:none; border:none; color:var(--idlm-danger); cursor:pointer; font-size:12px; padding:0; margin-left:5px; font-weight:500;">Remove</button>
                                                <?php else: ?>
                                                    No file uploaded
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-quote-value">Quote Value (<?php echo esc_html($currency_code); ?>)</label>
                                        <input <?php disabled(self::current_user_is_pre_sales()); ?> type="number" step="0.01" min="0" name="quote_value" class="idlm-drawer-control" id="idlm-edit-quote-value" value="<?php echo esc_attr(isset($lead->quote_value) && $lead->quote_value > 0 ? $lead->quote_value : ''); ?>" placeholder="e.g. 150000" />
                                        <?php if (!self::current_user_is_pre_sales() && current_user_can('idlm_view_quotes')): ?>
                                            <a class="idlm-drawer-btn-file" style="display:inline-flex;margin-top:8px;text-decoration:none;" href="<?php echo esc_url($quote_url); ?>">Open Quotations</a>
                                        <?php endif; ?>
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label for="idlm-edit-studio-location">Studio Location</label>
                                        <?php $studios = get_option('idlm_custom_studios', array('Downtown Studio', 'North Studio', 'South Studio')); ?>
                                        <select name="preferred_studio" class="idlm-drawer-control" id="idlm-edit-studio-location">
                                            <?php foreach ($studios as $studio): ?>
                                                <option value="<?php echo esc_attr($studio); ?>" <?php selected($lead->preferred_studio, $studio); ?>><?php echo esc_html($studio); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <?php if ($lead->source === 'Referral'): ?>
                                        <div class="idlm-drawer-field" style="grid-column: span 2;">
                                            <label for="idlm-edit-referrer">Referrer Details</label>
                                            <textarea name="referrer_details" class="idlm-drawer-control" id="idlm-edit-referrer" rows="2" style="min-height: 48px;"><?php echo esc_html($lead->referrer_details); ?></textarea>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    $is_callback_requested = ($lead->call_status === 'Call Back Requested');
                                    if ($is_callback_requested || !empty($lead->callback_datetime)):
                                        $callback_val = '';
                                        if (!empty($lead->callback_datetime)) {
                                            $callback_val = wp_date('Y-m-d\TH:i', strtotime($lead->callback_datetime));
                                        }
                                    ?>
                                        <div class="idlm-drawer-field" style="grid-column: span 2;">
                                            <label for="idlm-edit-callback-datetime">Callback Scheduled</label>
                                            <input type="datetime-local" name="callback_datetime" class="idlm-drawer-control" id="idlm-edit-callback-datetime" value="<?php echo esc_attr($callback_val); ?>" style="width: 100%;">
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </section>

                            <section class="idlm-drawer-panel">
                                <div class="idlm-drawer-panel-head">
                                    <h3 class="idlm-drawer-panel-title">CAMPAIGN ATTRIBUTION DETAILS</h3>
                                </div>
                                <div class="idlm-drawer-data-grid">
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Source</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_source ? $lead->utm_source : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Medium</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_medium ? $lead->utm_medium : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Campaign</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_campaign ? $lead->utm_campaign : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Adgroup</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_adgroup ? $lead->utm_adgroup : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Term</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_term ? $lead->utm_term : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">UTM Content</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->utm_content ? $lead->utm_content : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">GCLID</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->gclid ? $lead->gclid : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">GBRAID</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html(isset($lead->gbraid) && $lead->gbraid ? $lead->gbraid : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">WBRAID</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html(isset($lead->wbraid) && $lead->wbraid ? $lead->wbraid : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">FBCLID</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->fbclid ? $lead->fbclid : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">Meta Lead ID</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->fb_lead_id ? $lead->fb_lead_id : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">LI FAT ID</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html($lead->li_fat_id ? $lead->li_fat_id : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">Landing Page</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html(isset($lead->landing_page) && $lead->landing_page ? $lead->landing_page : 'N/A'); ?></div>
                                    </div>
                                    <div>
                                        <span class="idlm-drawer-data-label">Conversion Time</span>
                                        <div class="idlm-drawer-data-value"><?php echo esc_html(isset($lead->conversion_time) && $lead->conversion_time && $lead->conversion_time !== '0000-00-00 00:00:00' ? $lead->conversion_time : 'N/A'); ?></div>
                                    </div>
                                </div>
                            </section>

                            <?php if (!empty($duplicates)): ?>
                                <div style="margin-top:20px; padding: 0 14px;">
                                    <h4 style="margin: 0 0 10px 0; font-size:13px; font-weight:700;">Linked Duplicate Leads (Merged):</h4>
                                    <ul class="idlm-duplicate-list" style="margin:0; padding-left:18px;">
                                        <?php foreach ($duplicates as $dup): ?>
                                            <li style="margin-bottom:6px; font-size:12px;">
                                                <a href="#" onclick="openLeadDrawer(<?php echo esc_attr($dup->id); ?>)" style="color:var(--idlm-brand-gold); text-decoration:none; font-weight:600;"><?php echo esc_html($dup->first_name . ' ' . $dup->last_name); ?></a>
                                                <span style="font-size:11px; color:#64748b;">(Added <?php echo esc_html(self::format_datetime($dup->created_at)); ?>)</span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Sidebar Notes & Comments -->
                        <aside class="idlm-drawer-panel idlm-drawer-activity">
                            <div class="idlm-drawer-panel-head">
                                <h3 class="idlm-drawer-panel-title">Notes &amp; Comments</h3>
                            </div>
                            <div class="idlm-drawer-activity-body">
                                <div>
                                    <h4 class="idlm-drawer-activity-title">Add Note / Comment</h4>
                                    <textarea id="idlm-comment-text-details" class="idlm-drawer-textarea idlm-drawer-textarea-compact" placeholder="Add activity notes or comment..."></textarea>
                                    <div class="idlm-drawer-comment-actions">
                                        <button type="button" class="idlm-btn" onclick="saveActivityCommentDetails(this, <?php echo esc_attr($lead->id); ?>)">Add Comment</button>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="idlm-drawer-activity-title">Recent Comments</h4>
                                    <div class="idlm-timeline-container-details loaded" id="idlm-timeline-container-details-inner">
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
                                    </div>
                                </div>
                            </div>
                        </aside>
                    </div>

                </form>
            </div>

            <div id="drawer-tab-activity" class="idlm-drawer-tab-content">
                <div class="idlm-drawer-activity-full">
                    <!-- Left column: Add comment -->
                    <section class="idlm-drawer-panel">
                        <div class="idlm-drawer-panel-head">
                            <h3 class="idlm-drawer-panel-title">Add Note / Comment</h3>
                        </div>
                        <div class="idlm-drawer-activity-body">
                            <textarea id="idlm-comment-text" class="idlm-drawer-textarea" placeholder="Add activity notes or comment..."></textarea>
                            <div class="idlm-drawer-comment-actions">
                                        <button type="button" class="idlm-btn idlm-btn-primary" onclick="saveActivityComment(this, <?php echo esc_attr($lead->id); ?>)">Add Comment</button>
                                    </div>
                        </div>
                    </section>

                    <!-- Right column: Activities list -->
                    <section class="idlm-drawer-panel">
                        <div class="idlm-drawer-panel-head">
                            <h3 class="idlm-drawer-panel-title">Activity History</h3>
                        </div>
                        <div class="idlm-drawer-activity-body">
                            <div class="idlm-timeline-container loaded" id="idlm-timeline-container-inner">
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
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div id="drawer-tab-tasks" class="idlm-drawer-tab-content">
                <div class="idlm-drawer-tasks-wrapper" style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px;">
                    <!-- Left Column: Add Task Form -->
                    <section class="idlm-drawer-panel">
                        <div class="idlm-drawer-panel-head">
                            <h3 class="idlm-drawer-panel-title">Create New Task</h3>
                        </div>
                        <div class="idlm-drawer-panel-body" style="padding: 15px;">
                            <form id="idlm-add-task-form">
                                <div class="idlm-form-group" style="margin-bottom: 12px;">
                                    <label style="font-weight: 500; font-size: 11px; color: var(--idlm-text-muted); display: block; margin-bottom: 6px;">Task Title / Description *</label>
                                    <input type="text" id="idlm-new-task-title" class="idlm-input" style="width: 100%; font-size: 12px; font-family: Monaco, monospace; box-sizing: border-box;" placeholder="e.g. Call client for site visit confirmation" required>
                                </div>
                                <div class="idlm-form-group" style="margin-bottom: 12px;">
                                    <label style="font-weight: 500; font-size: 11px; color: var(--idlm-text-muted); display: block; margin-bottom: 6px;">Due Date & Time</label>
                                    <input type="datetime-local" id="idlm-new-task-due" class="idlm-input" style="width: 100%; font-size: 12px; font-family: Monaco, monospace; box-sizing: border-box;">
                                </div>
                                <div class="idlm-drawer-form-grid" style="grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 15px; display: grid;">
                                    <div class="idlm-drawer-field">
                                        <label style="font-weight: 500; font-size: 11px; color: var(--idlm-text-muted); display: block; margin-bottom: 6px;">Priority</label>
                                        <select id="idlm-new-task-priority" class="idlm-drawer-control" style="font-size: 12px; height: 32px; width: 100%; font-family: Monaco, monospace;">
                                            <option value="Low">Low</option>
                                            <option value="Medium" selected>Medium</option>
                                            <option value="High">High</option>
                                        </select>
                                    </div>
                                    <div class="idlm-drawer-field">
                                        <label style="font-weight: 500; font-size: 11px; color: var(--idlm-text-muted); display: block; margin-bottom: 6px;">Task Type</label>
                                        <select id="idlm-new-task-type" class="idlm-drawer-control" style="font-size: 12px; height: 32px; width: 100%; font-family: Monaco, monospace;">
                                            <option value="General" selected>General</option>
                                            <option value="Call">Call</option>
                                            <option value="Email">Email</option>
                                            <option value="Site Visit">Site Visit</option>
                                            <option value="Design Mockup">Design Mockup</option>
                                            <option value="Quote Revision">Quote Revision</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-btn-add-task" onclick="addLeadTask(<?php echo esc_attr($lead->id); ?>)" style="width: 100%; font-size: 12px; padding: 8px; font-weight: 600;">Add Task</button>
                            </form>
                        </div>
                    </section>

                    <!-- Right Column: Tasks List -->
                    <section class="idlm-drawer-panel">
                        <div class="idlm-drawer-panel-head" style="display: flex; justify-content: space-between; align-items: center;">
                            <h3 class="idlm-drawer-panel-title">Task List</h3>
                            <span class="idlm-task-count-badge" id="idlm-task-count-badge" style="font-size: 11px; background: var(--idlm-action-soft); color: var(--idlm-action); padding: 2px 8px; border-radius: 12px; font-weight: 600;">0 tasks</span>
                        </div>
                        <div class="idlm-drawer-panel-body" style="padding: 15px; min-height: 250px; max-height: 400px; overflow-y: auto;" id="idlm-drawer-tasks-list-container">
                            <!-- Tasks loaded dynamically via JS -->
                            <div style="text-align: center; color: var(--idlm-text-muted); padding: 40px 0;" id="idlm-tasks-empty-state">
                                <span class="dashicons dashicons-saved" style="font-size: 32px; width: 32px; height: 32px; margin-bottom: 8px; color: var(--idlm-border-strong); display: inline-block;"></span>
                                <p style="margin: 0; font-size: 12px;">No active tasks for this lead.</p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <footer class="idlm-drawer-form-footer">
            <?php if (current_user_can('manage_options')): ?>
                <button type="button" class="idlm-btn idlm-btn-danger-outline" onclick="deleteSingleLead(<?php echo esc_attr($lead->id); ?>)">Delete Lead</button>
            <?php endif; ?>
            <?php if (current_user_can('idlm_view_quotes')): ?>
                <a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url($quote_url); ?>"><?php echo 'list' === $quote_action ? 'View / Edit Quote' : 'Create Quote'; ?></a>
            <?php endif; ?>
            <button type="submit" form="idlm-edit-lead-form" class="idlm-btn idlm-btn-primary">Save Lead Details</button>
        </footer>
        <?php
        $html = ob_get_clean();
        if ($latest_whatsapp_reply) {
            self::mark_whatsapp_reply_read($lead_id, $latest_whatsapp_reply->id);
        }
        wp_send_json_success(array(
            'html' => $html,
            'first_name' => $lead->first_name,
            'full_name' => $lead->first_name . ' ' . $lead->last_name,
            'source' => $lead->source,
            'whatsapp_marked_read' => (bool) $latest_whatsapp_reply,
            'unread_whatsapp_count' => self::get_unread_whatsapp_count(),
            'lead_id' => $lead_id
        ));
    }
}
