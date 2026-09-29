<?php
if (!defined('ABSPATH')) {
    exit;
}

$stages = isset($context['stages']) && is_array($context['stages']) ? $context['stages'] : array();
$call_statuses = isset($context['call_statuses']) && is_array($context['call_statuses']) ? $context['call_statuses'] : array();
$studios = isset($context['studios']) && is_array($context['studios']) ? $context['studios'] : array();
$num_rooms_options = isset($context['num_rooms_options']) && is_array($context['num_rooms_options']) ? $context['num_rooms_options'] : array();
$reps = isset($context['reps']) && is_array($context['reps']) ? $context['reps'] : array();
$pre_sales_reps = isset($context['pre_sales_reps']) && is_array($context['pre_sales_reps']) ? $context['pre_sales_reps'] : array();
$active_rep_ids = isset($context['active_rep_ids']) && is_array($context['active_rep_ids']) ? $context['active_rep_ids'] : array();
$is_pre_sales = !empty($context['is_pre_sales']);
$can_assign_leads = !empty($context['can_assign_leads']);
$can_manage_options = !empty($context['can_manage_options']);
$reps_see_all = !empty($context['reps_see_all']);
$pending_calls_count = isset($context['pending_calls_count']) ? absint($context['pending_calls_count']) : 0;
$unread_whatsapp_count = isset($context['unread_whatsapp_count']) ? absint($context['unread_whatsapp_count']) : 0;
$stale_lead_count = isset($context['stale_lead_count']) ? absint($context['stale_lead_count']) : 0;
$new_leads_count = isset($context['new_leads_count']) ? absint($context['new_leads_count']) : 0;
$not_called_count = isset($context['not_called_count']) ? absint($context['not_called_count']) : 0;
$followup_count = isset($context['followup_count']) ? absint($context['followup_count']) : 0;
$unassigned_count = isset($context['unassigned_count']) ? absint($context['unassigned_count']) : 0;
$qualified_count = isset($context['qualified_count']) ? absint($context['qualified_count']) : 0;
$pending_quality_count = isset($context['pending_quality_count']) ? absint($context['pending_quality_count']) : 0;
$qualified_handoff_count = isset($context['qualified_handoff_count']) ? absint($context['qualified_handoff_count']) : 0;
$overdue_handoff_count = isset($context['overdue_handoff_count']) ? absint($context['overdue_handoff_count']) : 0;
$handoff_exception_count = isset($context['handoff_exception_count']) ? absint($context['handoff_exception_count']) : 0;
$qualified_yesterday_count = isset($context['qualified_yesterday_count']) ? absint($context['qualified_yesterday_count']) : 0;
$delivered_yesterday_count = isset($context['delivered_yesterday_count']) ? absint($context['delivered_yesterday_count']) : 0;
$qualified_fallback_enabled = isset($context['qualified_fallback_enabled']) ? (string) $context['qualified_fallback_enabled'] : '0';
$qualified_fallback_hours = isset($context['qualified_fallback_hours']) ? (string) $context['qualified_fallback_hours'] : '4';
$qualified_fallback_end_at = isset($context['qualified_fallback_end_at']) ? (string) $context['qualified_fallback_end_at'] : '';
$qualified_fallback_end_formatted = isset($context['qualified_fallback_end_formatted']) ? (string) $context['qualified_fallback_end_formatted'] : '';
$assignment_mode = isset($context['assignment_mode']) ? (string) $context['assignment_mode'] : 'manual';
$active_reps = isset($context['active_reps']) && is_array($context['active_reps']) ? $context['active_reps'] : array();
$rep_studios = isset($context['rep_studios']) && is_array($context['rep_studios']) ? $context['rep_studios'] : array();
$source_maps = isset($context['source_maps']) && is_array($context['source_maps']) ? $context['source_maps'] : array();
$fallback_enabled = isset($context['fallback_enabled']) ? (string) $context['fallback_enabled'] : '0';
$fallback_hours = isset($context['fallback_hours']) ? (string) $context['fallback_hours'] : '24';
$qualified_fallback_include_existing = isset($context['qualified_fallback_include_existing']) ? (string) $context['qualified_fallback_include_existing'] : '0';
$studio_mismatch_behavior = isset($context['studio_mismatch_behavior']) ? (string) $context['studio_mismatch_behavior'] : 'general_pool';
$handoff_task_policy = isset($context['handoff_task_policy']) ? (string) $context['handoff_task_policy'] : 'transfer';
$handoff_notifications_enabled = isset($context['handoff_notifications_enabled']) ? (string) $context['handoff_notifications_enabled'] : '0';
$active_pre_sales_reps = isset($context['active_pre_sales_reps']) && is_array($context['active_pre_sales_reps']) ? $context['active_pre_sales_reps'] : array();
$fallback_last_completed = isset($context['fallback_last_completed']) ? (string) $context['fallback_last_completed'] : 'Never';
$fallback_last_status = isset($context['fallback_last_status']) ? (string) $context['fallback_last_status'] : 'not_run';
$routing_exception_count = isset($context['routing_exception_count']) ? absint($context['routing_exception_count']) : 0;
$unassigned_intake_count = isset($context['unassigned_intake_count']) ? absint($context['unassigned_intake_count']) : 0;
$active_pre_sales_count = isset($context['active_pre_sales_count']) ? absint($context['active_pre_sales_count']) : 0;
$assignment_mode_label = isset($context['assignment_mode_label']) ? (string) $context['assignment_mode_label'] : 'Not configured';
$working_hours_start = isset($context['working_hours_start']) ? (string) $context['working_hours_start'] : '09:00';
$working_hours_end = isset($context['working_hours_end']) ? (string) $context['working_hours_end'] : '18:00';
$presales_targets = isset($context['presales_targets']) && is_array($context['presales_targets']) ? $context['presales_targets'] : array('calls' => 3, 'whatsapp' => 1);
$max_active_leads_per_rep = isset($context['max_active_leads_per_rep']) ? absint($context['max_active_leads_per_rep']) : 30;
$working_days = isset($context['working_days']) && is_array($context['working_days']) ? $context['working_days'] : array();
$routing_exception_notifications_enabled = isset($context['routing_exception_notifications_enabled']) ? (string) $context['routing_exception_notifications_enabled'] : '0';
$days_map = isset($context['days_map']) && is_array($context['days_map']) ? $context['days_map'] : array();
$common_overlays_html = isset($context['common_overlays_html']) ? (string) $context['common_overlays_html'] : '';
?>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div class="idlm-header-meta">
                    <h1><?php echo $is_pre_sales ? 'My Pre-Sales Queue' : 'Leads Database'; ?></h1>
                </div>
                <div class="idlm-header-actions" style="display: flex; align-items: center; gap: 15px;">
                    <label class="idlm-header-search" for="filter-search">
                        <span class="screen-reader-text">Search leads</span>
                        <input type="search" id="filter-search" class="idlm-input" autocomplete="off" placeholder="Search leads">
                    </label>
                    <button type="button" id="idlm-open-filters" class="idlm-btn idlm-btn-secondary idlm-header-filter-button" aria-controls="idlm-lead-filters" aria-expanded="false">
                        <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                        <span>Filters</span>
                        <span class="idlm-filter-count" aria-label="0 active filters" hidden>0</span>
                    </button>
                    <?php if ($can_assign_leads && $qualified_handoff_count > 0): ?>
                        <button type="button" class="idlm-handoff-alert-pill <?php echo $overdue_handoff_count > 0 ? 'is-overdue' : ''; ?>" id="idlm-qualified-handoffs-pill" title="Open qualified leads awaiting sales assignment">
                            <span class="dashicons dashicons-randomize" aria-hidden="true"></span>
                            <span><strong><?php echo esc_html($qualified_handoff_count); ?></strong> Qualified Handoffs</span>
                            <?php if ($overdue_handoff_count > 0): ?><em><?php echo esc_html($overdue_handoff_count); ?> overdue</em><?php endif; ?>
                        </button>
                    <?php endif; ?>
                    <?php if ($is_pre_sales && $pending_calls_count > 0): ?>
                        <div class="idlm-pending-calls-pill" id="idlm-pending-calls-pill" title="<?php echo esc_attr($pending_calls_count); ?> calls currently in pending queues">
                            <span class="idlm-pending-pulse-dot"></span>
                            <span class="idlm-pending-text"><strong><?php echo esc_html($pending_calls_count); ?></strong> Pending Calls</span>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="idlm-pending-calls-pill" id="idlm-stale-leads-pill" title="Show leads that need a fresh update" aria-label="Show <?php echo esc_attr($stale_lead_count); ?> leads that need a fresh update" <?php echo $stale_lead_count > 0 ? '' : 'style="display:none;"'; ?>>
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <span class="idlm-pending-text"><strong><?php echo esc_html($stale_lead_count); ?></strong> Needs update</span>
                    </button>
                    <button type="button" class="idlm-whatsapp-unread-pill" id="idlm-whatsapp-unread-pill" title="<?php echo esc_attr($unread_whatsapp_count); ?> unread WhatsApp messages" aria-label="Show <?php echo esc_attr($unread_whatsapp_count); ?> unread WhatsApp messages" <?php echo $unread_whatsapp_count > 0 ? '' : 'style="display:none;"'; ?>>
                        <span class="dashicons dashicons-whatsapp" aria-hidden="true"></span>
                        <span class="idlm-whatsapp-unread-text"><strong><?php echo esc_html($unread_whatsapp_count); ?></strong> Unread WhatsApp</span>
                    </button>
                    <?php if ($can_manage_options): ?>
                        <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-import-btn">Import CSV</button>
                        <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-export-btn">Export CSV</button>
                        <button type="button" class="idlm-btn idlm-btn-icon idlm-routing-settings-button" id="idlm-routing-settings-btn" onclick="openRoutingSettingsModal(this)" title="Lead Routing Settings" aria-label="Open lead routing settings">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-settings" style="display: block; margin: auto;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="idlm-btn idlm-btn-primary" onclick="openLeadModal()">+ Add Lead</button>
                </div>
            </header>

            <?php if ('1' === $qualified_fallback_enabled && $can_assign_leads): ?>
                <div class="notice notice-warning" style="margin: 12px 0; padding: 10px 14px; border-left: 4px solid #d97706; background: #fffbeb;">
                    <strong>Manager OOO qualified handoff is active.</strong>
                    Qualified pre-sales leads will be assigned automatically after <?php echo esc_html($qualified_fallback_hours); ?> working hours.
                    <?php if ($qualified_fallback_end_at): ?>Ends <?php echo esc_html($qualified_fallback_end_formatted); ?>.<?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Metrics Summary Grid -->
            <section class="idlm-metric-grid" aria-label="Lead summary">
                <button class="idlm-metric" type="button" data-filter="New">
                    <span class="idlm-metric-label">New Leads</span>
                    <span class="idlm-metric-value"><span data-metric-count="new"><?php echo esc_html($new_leads_count); ?></span> <span class="idlm-metric-hint">filtered</span></span>
                </button>
                <button class="idlm-metric" type="button" data-filter="Not Called">
                    <span class="idlm-metric-label">Not Called</span>
                    <span class="idlm-metric-value"><span data-metric-count="not_called"><?php echo esc_html($not_called_count); ?></span> <span class="idlm-metric-hint">active only</span></span>
                </button>
                <button class="idlm-metric" type="button" data-filter="Follow-up">
                    <span class="idlm-metric-label">Follow-up</span>
                    <span class="idlm-metric-value"><span data-metric-count="followup"><?php echo esc_html($followup_count); ?></span> <span class="idlm-metric-hint">active only</span></span>
                </button>
                <?php if ($can_assign_leads || (!$is_pre_sales && $reps_see_all)): ?>
                <button class="idlm-metric" type="button" data-filter="Unassigned">
                    <span class="idlm-metric-label">Unassigned</span>
                    <span class="idlm-metric-value"><span data-metric-count="unassigned"><?php echo esc_html($unassigned_count); ?></span> <span class="idlm-metric-hint">needs owner</span></span>
                </button>
                <?php endif; ?>
                <?php if ($can_assign_leads): ?>
                <button class="idlm-metric <?php echo $overdue_handoff_count > 0 ? 'is-alert' : ''; ?>" type="button" data-filter="Qualified Handoffs">
                    <span class="idlm-metric-label">Qualified Handoffs</span>
                    <span class="idlm-metric-value"><span data-metric-count="qualified_handoffs"><?php echo esc_html($qualified_handoff_count); ?></span> <span class="idlm-metric-hint" data-metric-hint="qualified_handoffs"><?php echo $overdue_handoff_count > 0 ? esc_html($overdue_handoff_count) . ' overdue' : 'awaiting assignment'; ?></span></span>
                </button>
                <div class="idlm-metric" aria-label="Yesterday qualification delivery reconciliation">
                    <span class="idlm-metric-label">Yesterday Delivery</span>
                    <span class="idlm-metric-value"><span data-metric-count="delivered_yesterday"><?php echo esc_html($delivered_yesterday_count); ?></span>/<span data-metric-count="qualified_yesterday"><?php echo esc_html($qualified_yesterday_count); ?></span> <span class="idlm-metric-hint">handoffs / qualified</span></span>
                </div>
                <?php if ($handoff_exception_count > 0): ?>
                <button class="idlm-metric is-alert" type="button" data-filter="Routing Exceptions">
                    <span class="idlm-metric-label">Routing Exceptions</span>
                    <span class="idlm-metric-value"><span data-metric-count="routing_exceptions"><?php echo esc_html($handoff_exception_count); ?></span> <span class="idlm-metric-hint">manager review</span></span>
                </button>
                <?php endif; ?>
                <?php endif; ?>
                <button class="idlm-metric" type="button" data-filter="Qualified">
                    <span class="idlm-metric-label">Qualified Leads</span>
                    <span class="idlm-metric-value"><span data-metric-count="qualified"><?php echo esc_html($qualified_count); ?></span> <span class="idlm-metric-hint">active</span></span>
                </button>
                <button class="idlm-metric" type="button" data-filter="Pending Quality">
                    <span class="idlm-metric-label">Pending Quality</span>
                    <span class="idlm-metric-value"><span data-metric-count="pending_quality"><?php echo esc_html($pending_quality_count); ?></span> <span class="idlm-metric-hint">to evaluate</span></span>
                </button>
            </section>

            <!-- Table Filters Panel -->
            <div class="idlm-filters-backdrop" id="idlm-filters-backdrop" hidden></div>
            <div class="idlm-filters-panel" id="idlm-lead-filters" role="dialog" aria-modal="true" aria-labelledby="idlm-filters-title" aria-hidden="true" tabindex="-1">
                <div class="idlm-filters-panel-header">
                    <div>
                        <h2 id="idlm-filters-title">Filter leads</h2>
                        <p>Narrow the database by assignment, activity, and qualification.</p>
                    </div>
                    <button type="button" id="idlm-close-filters" class="idlm-filters-close" aria-label="Close filters">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </div>

                <div class="idlm-filters-panel-body">
                <!-- Primary Filters Row -->
                <div class="idlm-filters-primary-row">
                    <div class="idlm-filter-group">
                        <label for="filter-date-basis">Date basis</label>
                        <select id="filter-date-basis" class="idlm-input">
                            <option value="assigned_at" selected>Assigned Date</option>
                            <option value="created_at">Created Date</option>
                            <option value="handoff_at">Sales Handoff Date</option>
                            <option value="updated_at">Last Updated Date</option>
                            <option value="qualified_at">Qualified Date</option>
                            <option value="conversion_time">Won Date</option>
                        </select>
                    </div>
                    <div class="idlm-filter-group">
                        <label for="filter-date-range">Date range</label>
                        <select id="filter-date-range" class="idlm-input">
                            <option value="">All Time</option>
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="week">This Week</option>
                            <option value="month">This Month</option>
                            <option value="last_7_days">Last 7 Days</option>
                            <option value="last_30_days">Last 30 Days</option>
                            <option value="last_month">Last Month</option>
                            <option value="year">This Year</option>
                        </select>
                    </div>
                    <div class="idlm-filter-group">
                        <label for="filter-stage">Stage</label>
                        <select id="filter-stage" class="idlm-input">
                            <option value="">All Stages</option>
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="idlm-filter-group">
                        <label for="filter-call-status">Call status</label>
                        <select id="filter-call-status" class="idlm-input">
                            <option value="">All Statuses</option>
                            <?php foreach ($call_statuses as $cs): ?>
                                <option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($can_assign_leads || (!$is_pre_sales && $reps_see_all)): ?>
                    <div class="idlm-filter-group">
                        <label for="filter-rep">Assigned representative</label>
                        <select id="filter-rep" class="idlm-input">
                            <option value="">All Reps</option>
                            <option value="0">Unassigned</option>
                            <?php foreach ($reps as $rep):
                                if (!in_array($rep->ID, $active_rep_ids)) {
                                    continue;
                                }
                            ?>
                                <option value="<?php echo esc_attr($rep->ID); ?>"><?php echo esc_html($rep->display_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="idlm-filter-group idlm-filter-buttons-group">
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="button" id="btn-clear-filters" class="idlm-btn idlm-btn-secondary" style="height: 29px; padding: 0 10px; display: flex; width: auto; box-sizing: border-box; align-items: center; justify-content: center; gap: 4px; font-size: 11px;" title="Clear Filters">
                                <span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px; margin: 0;"></span>
                                <span>Clear</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Additional lead filters -->
                <div class="idlm-filters-secondary-row" id="idlm-secondary-filters-container">
                    <div class="idlm-filter-group">
                        <label for="filter-source">Channel source</label>
                        <select id="filter-source" class="idlm-input">
                            <option value="">All Channels</option>
                            <option value="Website">Website</option>
                            <option value="Meta Ads">Meta Ads</option>
                            <option value="Google Ads">Google Ads</option>
                            <option value="LinkedIn Ads">LinkedIn Ads</option>
                            <option value="Referral">Referral</option>
                            <option value="Offline/Walk-in">Offline/Walk-in</option>
                        </select>
                    </div>
                    <div class="idlm-filter-group">
                        <label for="filter-quality">Lead quality</label>
                        <select id="filter-quality" class="idlm-input">
                            <option value="">All Qualities</option>
                            <?php if ($can_assign_leads): ?>
                                <option value="Qualified Awaiting Assignment">Qualified – Awaiting Assignment</option>
                                <option value="Routing Exception">Routing Exceptions</option>
                            <?php endif; ?>
                            <option value="Qualified">Qualified</option>
                            <option value="Qualified Plan Received">Qualified · Plan Received</option>
                            <option value="Qualified Plan Pending">Qualified · Plan Pending</option>
                            <option value="Unqualified">Unqualified</option>
                            <option value="Pending">Pending Qualification</option>
                        </select>
                    </div>
                    <div class="idlm-filter-group">
                        <label for="filter-followup">Engagement status</label>
                        <select id="filter-followup" class="idlm-input">
                            <?php if ($is_pre_sales): ?>
                                <option value="action_required" selected>Action Required</option>
                                <option value="my_qualified_history">My Qualified History (read-only)</option>
                            <?php endif; ?>
                            <option value="">All Leads</option>
                            <option value="overdue">Overdue follow-up</option>
                            <option value="stale_updates">Needs fresh update (7+ days)</option>
                            <option value="pending_calls">Pending calls</option>
                            <option value="whatsapp_received">WhatsApp Message Received</option>
                            <option value="unread_whatsapp">Unread WhatsApp Messages</option>
                        </select>
                    </div>
                </div>
                </div>
            </div>

            <!-- Bulk Actions Panel -->
            <div class="idlm-bulk-bar" hidden aria-label="Bulk lead actions">
                <span class="idlm-selected-count" role="status" aria-live="polite">0 leads selected</span>
                <div class="idlm-bulk-actions">
                    <label class="screen-reader-text" for="idlm-bulk-action-select">Bulk action</label>
                    <select id="idlm-bulk-action-select" class="idlm-input">
                        <option value="">Select Bulk Action</option>
                        <?php if ($can_assign_leads): ?>
                            <option value="bulk_assign">Assign to Rep</option>
                        <?php endif; ?>
                        <option value="bulk_stage">Change Stage</option>
                        <option value="bulk_unqualified">Mark Unqualified</option>
                        <option value="bulk_audience_queue">Add to Audience Sync Queue</option>
                        <?php if ($can_manage_options): ?>
                            <option value="bulk_delete">Delete leads</option>
                        <?php endif; ?>
                    </select>

                    <!-- Contextual dynamic parameters for bulk options -->
                    <label class="screen-reader-text" for="idlm-bulk-rep">Representative for bulk assignment</label>
                    <select id="idlm-bulk-rep" class="idlm-input" style="display:none;">
                        <option value="">Select Rep</option>
                        <?php foreach ($reps as $rep):
                            if (!in_array($rep->ID, $active_rep_ids)) {
                                continue;
                            }
                        ?>
                            <option value="<?php echo esc_attr($rep->ID); ?>"><?php echo esc_html($rep->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label class="screen-reader-text" for="idlm-bulk-stage">Stage for bulk update</label>
                    <select id="idlm-bulk-stage" class="idlm-input" style="display:none;">
                        <option value="">Select Stage</option>
                        <?php foreach ($stages as $stage): ?>
                            <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button type="button" id="idlm-clear-selection" class="idlm-btn idlm-btn-secondary">Clear selection</button>
                    <button type="button" id="idlm-apply-bulk" class="idlm-btn idlm-btn-primary">Apply action</button>
                </div>
                <p class="idlm-field-error idlm-bulk-error" id="idlm-bulk-error" role="alert" hidden></p>
            </div>

            <div class="idlm-leads-mobile-sort">
                <label for="idlm-leads-mobile-sort">Sort leads</label>
                <select id="idlm-leads-mobile-sort" class="idlm-input">
                    <option value="">Default order</option>
                    <option value="name|asc">Name: A to Z</option>
                    <option value="name|desc">Name: Z to A</option>
                    <option value="budget|desc">Budget: high to low</option>
                    <option value="lead_quality|asc"><?php echo $is_pre_sales ? 'Follow-up status' : 'Lead quality'; ?></option>
                    <option value="assigned_at|desc">Recently assigned</option>
                    <option value="created_at|desc">Recently created</option>
                    <?php if (!$is_pre_sales): ?>
                        <option value="stage|asc">Stage</option>
                        <option value="assigned_rep|asc">Assigned representative</option>
                    <?php endif; ?>
                </select>
            </div>

            <!-- Leads Data Table -->
            <div class="idlm-table-wrapper idlm-leads-table-wrapper">
                <table class="idlm-table" id="idlm-leads-table">
                    <caption class="screen-reader-text">Leads matching the current filters. On narrow screens, use each lead's Show details button to reveal secondary columns.</caption>
                    <thead>
                        <tr>
                            <th class="idlm-selection-column idlm-col-priority-primary"><input type="checkbox" id="idlm-select-all" aria-label="Select all leads on this page" aria-controls="idlm-leads-table"></th>
                            <?php if ($is_pre_sales): ?>
                            <th class="idlm-sortable-column idlm-col-priority-primary" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="name">Lead<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-sortable-column idlm-col-priority-low" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="source">Channel<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-col-priority-low">Studio</th>
                            <th class="idlm-col-created-at idlm-sortable-column idlm-col-priority-low" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="assigned_at">Assigned Date<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-sortable-column idlm-col-priority-low" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="age">Age<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-col-priority-low">Today</th>
                            <th class="idlm-sortable-column idlm-col-priority-secondary" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="response">Response<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-sortable-column idlm-col-priority-secondary" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="budget">Budget<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-sortable-column idlm-col-priority-low" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="details">Details<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-sortable-column idlm-col-priority-primary" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="lead_quality">Follow-up Status<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-col-priority-primary">Actions</th>
                            <?php else: ?>
                            <th class="idlm-sortable-column idlm-col-priority-primary" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="name">Name<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-sortable-column idlm-col-priority-low" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="source">Channel<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-col-priority-low">Studio</th>
                            <th class="idlm-sortable-column idlm-col-priority-secondary" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="budget">Budget<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-sortable-column idlm-col-priority-primary" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="stage">Stage<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-sortable-column idlm-col-priority-secondary" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="assigned_rep">Assigned Rep<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-sortable-column idlm-col-priority-primary" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="lead_quality">Lead Quality<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-sortable-column idlm-col-priority-secondary" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="tasks">Tasks<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-col-created-at idlm-sortable-column idlm-col-priority-low" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="assigned_at">Assigned At<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <th class="idlm-col-created-at idlm-sortable-column idlm-col-priority-low" aria-sort="none"><button type="button" class="idlm-table-sort-button" data-sort="created_at">Created At<span class="idlm-sort-indicator" aria-hidden="true"></span></button></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Loaded via dynamic script ajax lists -->
                    </tbody>
                </table>
            </div>
            <div class="idlm-pagination">
                <label class="idlm-per-page-label" for="idlm-leads-per-page">
                    Leads per page
                    <select id="idlm-leads-per-page" class="idlm-per-page-select">
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
                <button type="button" class="idlm-pagination-btn" id="prev-page">Previous</button>
                <span id="page-num" class="idlm-pagination-info">Page 1 of 1</span>
                <button type="button" class="idlm-pagination-btn" id="next-page">Next</button>
            </div>
        </div>

        <!-- Add Manual Lead Modal -->
        <div class="idlm-modal" id="idlm-lead-modal">
            <div class="idlm-modal-content">
                <div class="idlm-modal-header">
                    <h2>Create Manual Lead</h2>
                    <button type="button" class="idlm-close" onclick="closeLeadModal()" aria-label="Close create manual lead dialog">&times;</button>
                </div>
                <form id="idlm-add-lead-form">
                    <div class="idlm-form-grid">
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-first-name">First name <span class="idlm-required-indicator" aria-hidden="true">*</span></label>
                            <input type="text" id="idlm-add-lead-first-name" name="first_name" class="idlm-input" autocomplete="given-name" required>
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-last-name">Last name</label>
                            <input type="text" id="idlm-add-lead-last-name" name="last_name" class="idlm-input" autocomplete="family-name">
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-email">Email address <span class="idlm-required-indicator" aria-hidden="true">*</span></label>
                            <input type="email" id="idlm-add-lead-email" name="email" class="idlm-input" autocomplete="email" required>
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-phone">Phone number <span class="idlm-required-indicator" aria-hidden="true">*</span></label>
                            <input type="tel" id="idlm-add-lead-phone" name="phone" class="idlm-input" autocomplete="tel" inputmode="tel" required>
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-lead-source-select">Source channel</label>
                            <select name="source" id="idlm-lead-source-select" class="idlm-input">
                                <option value="Website">Website</option>
                                <option value="Meta Ads">Meta Ads</option>
                                <option value="Google Ads">Google Ads</option>
                                <option value="LinkedIn Ads">LinkedIn Ads</option>
                                <option value="Referral">Referral</option>
                                <option value="Offline/Walk-in">Offline/Walk-in</option>
                            </select>
                        </div>
                        <!-- Referrer Text Area (Hidden by default, shown when Source is Referral) -->
                        <div class="idlm-form-group" id="idlm-referrer-field" style="display:none; grid-column: span 2;">
                            <label for="idlm-add-lead-referrer">Referrer details</label>
                            <textarea id="idlm-add-lead-referrer" name="referrer_details" class="idlm-input" rows="2" placeholder="Mention referral details..."></textarea>
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-property-type">Property type</label>
                            <select id="idlm-add-lead-property-type" name="property_type" class="idlm-input">
                                <option value="Apartment">Apartment</option>
                                <option value="Villa">Villa</option>
                                <option value="Independent House">Independent House</option>
                                <option value="Commercial">Commercial</option>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-rooms">Number of rooms</label>
                            <select id="idlm-add-lead-rooms" name="num_rooms" class="idlm-input">
                                <?php foreach ($num_rooms_options as $opt): ?>
                                    <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-studio">Preferred studio location</label>
                            <select id="idlm-add-lead-studio" name="preferred_studio" class="idlm-input">
                                <?php foreach ($studios as $studio): ?>
                                    <option value="<?php echo esc_attr($studio); ?>"><?php echo esc_html($studio); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-quality">Lead quality</label>
                            <select id="idlm-add-lead-quality" name="lead_quality" class="idlm-input">
                                <option value="" selected>Choose Quality...</option>
                                <option value="Qualified">Qualified</option>
                                <option value="Unqualified">Unqualified</option>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-budget">Lead budget</label>
                            <input type="text" id="idlm-add-lead-budget" name="budget" class="idlm-input" inputmode="decimal" placeholder="e.g. 15000 or 25000">
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-timeline">Project timeline</label>
                            <input type="text" id="idlm-add-lead-timeline" name="project_timeline" class="idlm-input" placeholder="e.g. Immediate or 1–3 months">
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-add-lead-property-location">Property location or pincode</label>
                            <input type="text" id="idlm-add-lead-property-location" name="property_location" class="idlm-input" autocomplete="street-address" placeholder="e.g. Downtown or 560001">
                        </div>
                    </div>
                    <div style="margin-top: 20px; display:flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="idlm-btn idlm-btn-secondary" onclick="closeLeadModal()">Cancel</button>
                        <button type="submit" class="idlm-btn idlm-btn-primary">Save Lead</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lead Routing Settings Modal -->
        <div class="idlm-modal idlm-dialog" id="idlm-routing-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-routing-modal-title" aria-describedby="idlm-routing-modal-description" aria-hidden="true" data-idlm-dialog data-idlm-use-hidden hidden>
            <div class="idlm-modal-content idlm-dialog__panel">
                <div class="idlm-modal-header">
                    <div class="idlm-routing-modal-heading">
                        <h2 id="idlm-routing-modal-title">Lead Routing &amp; Assignment Rules</h2>
                        <p class="idlm-routing-modal-intro" id="idlm-routing-modal-description">Choose how leads are distributed and which studio each representative covers.</p>
                    </div>
                    <button type="button" class="idlm-close" onclick="return closeRoutingModal()" aria-label="Close routing settings">&times;</button>
                </div>
                <form id="idlm-routing-settings-form">
                    <input type="hidden" name="action" value="idlm_save_routing_settings">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('idlm_admin_nonce')); ?>">

                    <div class="idlm-routing-modal-body">
                        <div class="idlm-form-grid idlm-routing-primary-grid">
                            <section class="idlm-form-group idlm-routing-section idlm-routing-strategy-section">
                                <div class="idlm-routing-section-heading">
                                    <div>
                                        <h3>New-lead routing</h3>
                                        <p>Choose how new leads reach the right person.</p>
                                    </div>
                                </div>
                                <label for="idlm-routing-mode-select">Assignment strategy</label>
                                <select name="assignment_mode" id="idlm-routing-mode-select" class="idlm-input" aria-describedby="idlm-routing-strategy-help idlm-routing-settings-error" onchange="toggleRoutingModalSourcePanel(this.value)">

                                    <option value="manual" <?php selected($assignment_mode, 'manual'); ?>>Manual assignment</option>
                                    <option value="pre_sales" <?php selected($assignment_mode, 'pre_sales'); ?>>Pre-sales rotation</option>
                                    <option value="round_robin" <?php selected($assignment_mode, 'round_robin'); ?>>All-representative rotation</option>
                                    <option value="source_based" <?php selected($assignment_mode, 'source_based'); ?>>Route by source</option>
                                    <option value="studio_based" <?php selected($assignment_mode, 'studio_based'); ?>>Route by studio</option>
                                </select>
                                <p class="idlm-routing-outcome" id="idlm-routing-strategy-help" role="status" aria-live="polite"></p>
                                <div class="idlm-routing-current-summary" role="status" aria-live="polite" aria-label="Current routing setup" data-pre-sales-count="<?php echo esc_attr($active_pre_sales_count); ?>" data-unassigned-count="<?php echo esc_attr($unassigned_intake_count); ?>">
                                    <strong>Current setup</strong>
                                    <span id="idlm-routing-current-summary-text"><?php echo esc_html($assignment_mode_label); ?> · <?php echo esc_html($active_pre_sales_count); ?> pre-sales available · <?php echo esc_html($unassigned_intake_count); ?> unassigned · Backlog recovery <?php echo '1' === $fallback_enabled ? 'on' : 'off'; ?></span>
                                </div>
                            </section>

                            <section class="idlm-form-group idlm-routing-section idlm-routing-reps-section">
                                <div class="idlm-routing-section-heading">
                                    <div>
                                        <h3>Representative coverage</h3>
                                        <p>Choose the representatives available for rotation and their studio coverage.</p>
                                    </div>
                                </div>
                                <div class="idlm-routing-rep-head" aria-hidden="true">
                                    <span>Representative</span>
                                    <span>Studio location</span>
                                </div>
                                <div class="idlm-checkbox-list idlm-routing-rep-list">
                                    <?php


                                    foreach ($reps as $rep):
                                    ?>
                                        <label class="idlm-routing-rep-row">
                                            <span class="idlm-routing-rep-name">
                                            <input type="checkbox" name="active_reps[]" value="<?php echo esc_attr($rep->ID); ?>" <?php checked(in_array($rep->ID, $active_reps)); ?>>
                                            <?php echo esc_html($rep->display_name); ?>
                                            </span>
                                            <select name="rep_studios[<?php echo esc_attr($rep->ID); ?>]" class="idlm-input" aria-label="Studio location for <?php echo esc_attr($rep->display_name); ?>">
                                                <option value="">No studio assigned</option>
                                                <?php foreach ($studios as $studio): ?>
                                                    <option value="<?php echo esc_attr($studio); ?>" <?php selected(isset($rep_studios[$rep->ID]) ? $rep_studios[$rep->ID] : '', $studio); ?>><?php echo esc_html($studio); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        </div>

                        <!-- Source maps sub panel -->

                        <div id="idlm-routing-source-mapping-subpanel" class="idlm-form-grid idlm-routing-section idlm-routing-source-section<?php echo 'source_based' === $assignment_mode ? '' : ' is-inactive'; ?>" aria-disabled="<?php echo 'source_based' === $assignment_mode ? 'false' : 'true'; ?>">
                            <div class="idlm-routing-section-heading full">
                                <div><h3>Source assignments</h3><p>Choose who receives each source. These rules apply when “Route by source” is selected above.</p></div>
                            </div>
                            <?php foreach (array('Website', 'Meta Ads', 'Google Ads', 'LinkedIn Ads', 'Referral', 'Offline/Walk-in') as $src):
                                $field_name = 'source_map_' . str_replace(array(' ', '/', '-'), '_', strtolower($src));
                                $field_id = 'idlm-' . $field_name;
                                $val = isset($source_maps[$src]) ? $source_maps[$src] : 0;
                            ?>
                                <div class="idlm-form-group">
                                    <label for="<?php echo esc_attr($field_id); ?>">Route <?php echo esc_html($src); ?> to</label>
                                    <select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>" class="idlm-input"<?php disabled('source_based' !== $assignment_mode); ?>>
                                        <option value="0">Unassigned</option>
                                        <?php foreach ($reps as $rep): ?>
                                            <option value="<?php echo esc_attr($rep->ID); ?>" <?php selected($val, $rep->ID); ?>><?php echo esc_html($rep->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <section class="idlm-routing-advanced" id="idlm-routing-advanced-safeguards">
                            <div class="idlm-routing-section-heading">
                                <div>
                                    <h3>Safeguards and operations</h3>
                                    <p>Set team availability, recovery, handoff, and everyday routing limits.</p>
                                </div>
                            </div>
                            <div class="idlm-routing-advanced-content">
                                <section id="idlm-routing-fallback-subpanel" class="idlm-routing-fallback-section">
                                    <div class="idlm-routing-control-group idlm-routing-team-group">
                                        <div class="idlm-routing-control-heading">
                                            <div><h3>Pre-sales availability</h3><p>Choose who can receive new pre-sales leads right now.</p></div>
                                        </div>
                                <div class="idlm-form-group idlm-control-full">
                                    <span class="idlm-form-label">Available now</span>
                                    <div class="idlm-routing-days">
                                        <?php if (empty($pre_sales_reps)): ?>
                                            <span class="description">No users currently have the Pre-Sales Representative role.</span>
                                        <?php else: ?>
                                            <?php foreach ($pre_sales_reps as $pre_sales_rep): ?>
                                                <label><input type="checkbox" name="active_pre_sales_reps[]" value="<?php echo esc_attr($pre_sales_rep->ID); ?>" <?php checked(in_array($pre_sales_rep->ID, $active_pre_sales_reps)); ?>> <?php echo esc_html($pre_sales_rep->display_name); ?></label>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <p class="description">Checked users are active for new Pre-Sales Intake leads. Uncheck someone while they are unavailable or on leave.</p>
                                </div>
                                    </div>

                                    <div class="idlm-routing-control-group">
                                        <div class="idlm-routing-control-heading">
                                            <div><h3>Unassigned lead recovery</h3><p>Optionally return older unassigned leads to the active pre-sales pool.</p></div>
                                        </div>
                                <label class="idlm-routing-switch-row">
                                    <input type="checkbox" name="fallback_assignment_enabled" value="1" <?php checked($fallback_enabled, '1'); ?>>
                                    <span><strong>Automatically assign the unassigned backlog</strong><small>When enabled, eligible leads return to the active pre-sales pool after the fallback threshold.</small></span>
                                </label>
                                        <div id="idlm-routing-backlog-controls" class="idlm-routing-conditional-controls"<?php echo '1' === $fallback_enabled ? '' : ' hidden aria-hidden="true"'; ?>>
                                            <input type="hidden" name="fallback_assignment_controls_active" value="1"<?php disabled('1' !== $fallback_enabled); ?>>
                                            <div class="idlm-form-group">
                                                <label for="idlm-fallback-hours">Recovery threshold</label>
                                                <div class="idlm-routing-input-suffix"><input id="idlm-fallback-hours" type="number" name="fallback_assignment_hours" class="idlm-input" min="1" max="720" value="<?php echo esc_attr($fallback_hours); ?>"<?php disabled('1' !== $fallback_enabled); ?>><span>working hours</span></div>
                                                <p class="description">Wait this long before an eligible unassigned lead is reassigned.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="idlm-routing-control-group">
                                        <div class="idlm-routing-control-heading">
                                            <div><h3>Qualified lead handoff</h3><p>Cover qualified leads when a manager is unavailable.</p></div>
                                        </div>
                                <label class="idlm-routing-switch-row">
                                    <input type="checkbox" name="qualified_fallback_enabled" value="1" <?php checked($qualified_fallback_enabled, '1'); ?>>
                                    <span><strong>Manager OOO: auto-assign qualified leads</strong><small>After the threshold, hand qualified pre-sales leads to studio-matched active sales representatives, then the general sales pool.</small></span>
                                </label>
                                        <div id="idlm-routing-qualified-controls" class="idlm-routing-conditional-controls idlm-routing-fallback-fields"<?php echo '1' === $qualified_fallback_enabled ? '' : ' hidden aria-hidden="true"'; ?>>
                                            <input type="hidden" name="qualified_fallback_controls_active" value="1"<?php disabled('1' !== $qualified_fallback_enabled); ?>>
                                <div class="idlm-form-group">
                                    <label for="idlm-qualified-fallback-hours">Qualified handoff threshold</label>
                                    <div class="idlm-routing-input-suffix"><input id="idlm-qualified-fallback-hours" type="number" name="qualified_fallback_hours" class="idlm-input" min="1" max="720" value="<?php echo esc_attr($qualified_fallback_hours); ?>"><span>working hours</span></div>
                                    <p class="description">Manager OOO wait time measured from qualification.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label for="idlm-qualified-fallback-end">Manager OOO ends</label>
                                    <input id="idlm-qualified-fallback-end" type="datetime-local" name="qualified_fallback_end_at" class="idlm-input" value="<?php echo esc_attr($qualified_fallback_end_at ? str_replace(' ', 'T', substr($qualified_fallback_end_at, 0, 16)) : ''); ?>">
                                    <p class="description">Optional. OOO handoff disables automatically after this time.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label for="idlm-studio-mismatch-behavior">Missing studio behavior</label>
                                    <select id="idlm-studio-mismatch-behavior" name="studio_mismatch_behavior" class="idlm-input">
                                        <option value="general_pool" <?php selected($studio_mismatch_behavior, 'general_pool'); ?>>Use general active sales pool</option>
                                        <option value="hold_for_manager" <?php selected($studio_mismatch_behavior, 'hold_for_manager'); ?>>Hold for manager review</option>
                                    </select>
                                </div>
                                <div class="idlm-form-group">
                                    <label for="idlm-handoff-task-policy">Pending task policy</label>
                                    <select id="idlm-handoff-task-policy" name="handoff_task_policy" class="idlm-input">
                                        <option value="transfer" <?php selected($handoff_task_policy, 'transfer'); ?>>Transfer pending tasks to sales owner</option>
                                        <option value="retain" <?php selected($handoff_task_policy, 'retain'); ?>>Keep tasks with pre-sales</option>
                                        <option value="complete_qualification" <?php selected($handoff_task_policy, 'complete_qualification'); ?>>Complete qualification tasks only</option>
                                    </select>
                                </div>
                                <div class="idlm-form-group">
                                    <label class="idlm-routing-switch-row"><input type="checkbox" name="qualified_fallback_include_existing" value="1" <?php checked($qualified_fallback_include_existing, '1'); ?>><span><strong>Include historical qualified leads</strong><small>Off by default to prevent an immediate bulk handoff when OOO is first enabled.</small></span></label>
                                </div>
                                <div class="idlm-form-group">
                                    <label class="idlm-routing-switch-row"><input type="checkbox" name="handoff_notifications_enabled" value="1" <?php checked($handoff_notifications_enabled, '1'); ?>><span><strong>Email handoff participants</strong><small>Notify the previous pre-sales owner and the new sales owner.</small></span></label>
                                </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </section>

                        <section class="idlm-routing-advanced idlm-routing-operating-section">
                            <div class="idlm-routing-section-heading">
                                <div>
                                    <h3>Operating limits and notifications</h3>
                                    <p>Set capacity, working hours, follow-up targets, and routing alerts.</p>
                                </div>
                            </div>
                            <div class="idlm-routing-advanced-content">
                                <div class="idlm-routing-control-group idlm-routing-operating-group">
                                        <div class="idlm-routing-fallback-fields">
                                <div class="idlm-form-group">
                                    <label for="idlm-max-active-leads">Maximum active leads per rep</label>
                                    <div class="idlm-routing-input-suffix"><input id="idlm-max-active-leads" type="number" name="max_active_leads_per_rep" class="idlm-input" min="0" max="10000" value="<?php echo esc_attr($max_active_leads_per_rep); ?>"><span>leads</span></div>
                                    <p class="description">Use 0 for no limit.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label class="idlm-routing-switch-row"><input type="checkbox" name="routing_exception_notifications_enabled" value="1" <?php checked($routing_exception_notifications_enabled, '1'); ?>><span><strong>Email routing exceptions</strong><small>Notify the WordPress administrator when a routing pool is unavailable.</small></span></label>
                                </div>
                                <div class="idlm-form-group">
                                    <span class="idlm-form-label">Daily working hours</span>
                                    <div class="idlm-routing-time-range">
                                    <input type="time" name="working_hours_start" class="idlm-input" aria-label="Working day starts" value="<?php echo esc_attr($working_hours_start); ?>">
                                    <span>to</span>
                                    <input type="time" name="working_hours_end" class="idlm-input" aria-label="Working day ends" value="<?php echo esc_attr($working_hours_end); ?>">
                                    </div>
                                    <p class="description">Hours when the business is open.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label for="idlm-presales-calls">Calls per follow-up cycle</label>
                                    <input id="idlm-presales-calls" type="number" name="presales_calls_per_day" class="idlm-input" min="1" max="20" value="<?php echo esc_attr($presales_targets['calls']); ?>">
                                    <p class="description">Partial progress carries forward until this target is reached.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label for="idlm-presales-whatsapp">WhatsApp messages per follow-up cycle</label>
                                    <input id="idlm-presales-whatsapp" type="number" name="presales_whatsapp_per_day" class="idlm-input" min="0" max="20" value="<?php echo esc_attr($presales_targets['whatsapp']); ?>">
                                    <p class="description">Enter 0 when WhatsApp is not required.</p>
                                </div>
                                <div class="idlm-form-group idlm-routing-days-group">
                                <span class="idlm-form-label">Business working days</span>
                                <div class="idlm-routing-days">
                                    <?php foreach ($days_map as $val => $label): ?>
                                        <label>
                                            <input type="checkbox" name="working_days[]" value="<?php echo esc_attr($val); ?>" <?php checked(in_array($val, $working_days)); ?>>
                                            <?php echo esc_html($label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description">Days counted toward the threshold.</p>
                                </div>
                                            <div class="idlm-form-group idlm-routing-days-group">
                                                <span class="idlm-form-label">Fallback health</span>
                                                <p class="description">Last completed: <?php echo esc_html($fallback_last_completed); ?> · Status: <?php echo esc_html($fallback_last_status); ?> · Open exceptions: <?php echo esc_html($routing_exception_count); ?></p>
                                                <button type="button" class="idlm-btn idlm-btn-secondary idlm-run-fallback-now" aria-describedby="idlm-routing-fallback-progress">Run fallback now</button>
                                                <p class="idlm-routing-fallback-progress" id="idlm-routing-fallback-progress" role="status" aria-live="polite"></p>
                                                <div class="idlm-routing-fallback-confirm" hidden><p>Run fallback routing now? This can assign eligible unassigned leads immediately.</p><div><button type="button" class="idlm-btn idlm-btn-secondary idlm-cancel-fallback-run">Cancel</button><button type="button" class="idlm-btn idlm-btn-primary idlm-confirm-fallback-run">Run fallback</button></div></div>
                                            </div>
                                        </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="idlm-routing-modal-footer">
                        <button type="button" class="idlm-btn idlm-btn-secondary" onclick="return closeRoutingModal()">Cancel</button>
                        <button type="submit" class="idlm-btn idlm-btn-primary">Save Routing Rules</button>
                        <p class="idlm-routing-form-error" id="idlm-routing-settings-error" role="alert" hidden></p>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($can_manage_options): ?>
        <!-- Import Leads Modal -->
        <div class="idlm-modal idlm-dialog" id="idlm-import-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-import-title" aria-describedby="idlm-import-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-import-drag-zone">
            <div class="idlm-modal-content idlm-dialog__panel idlm-import-dialog-panel">
                <header class="idlm-modal-header idlm-dialog__header">
                    <div>
                        <h2 class="idlm-dialog__title" id="idlm-import-title">Import leads from CSV</h2>
                        <p class="idlm-dialog__description" id="idlm-import-description">Upload, map, review, and import lead records in four guided steps.</p>
                    </div>
                    <button type="button" class="idlm-dialog__close" id="idlm-import-close" data-idlm-dialog-close aria-label="Close lead import"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </header>

                <div class="idlm-dialog__body">

                <!-- Steps Navigation Indicator -->
                <ol class="idlm-import-steps" aria-label="Import progress">
                    <li class="idlm-import-step-item active" id="import-step-nav-1" aria-current="step">
                        <span class="idlm-import-step-number">1</span> Upload CSV
                    </li>
                    <li class="idlm-import-step-item" id="import-step-nav-2">
                        <span class="idlm-import-step-number">2</span> Map Fields
                    </li>
                    <li class="idlm-import-step-item" id="import-step-nav-3">
                        <span class="idlm-import-step-number">3</span> Preview & Options
                    </li>
                    <li class="idlm-import-step-item" id="import-step-nav-4">
                        <span class="idlm-import-step-number">4</span> Progress
                    </li>
                </ol>
                <p class="idlm-field-error idlm-import-error" id="idlm-import-error" role="alert" hidden></p>

                <!-- STEP 1: UPLOAD CSV -->
                <div class="idlm-import-step-content active" id="import-step-content-1">
                    <button type="button" class="idlm-import-drag-zone" id="idlm-import-drag-zone" aria-describedby="idlm-import-file-help">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span class="idlm-import-drag-text">Drag and drop your CSV file here, or click to browse</span>
                        <span class="idlm-import-drag-subtext" id="idlm-import-file-help">Supports standard .csv files up to 5MB</span>
                    </button>
                    <label class="screen-reader-text" for="idlm-import-file-input">Choose CSV file</label>
                    <input type="file" id="idlm-import-file-input" accept=".csv,text/csv" hidden>

                    <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                        <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-import-download-template" style="display: inline-flex; gap: 5px;">
                            <span class="dashicons dashicons-download" aria-hidden="true"></span> Download sample template
                        </button>
                        <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-dialog-close>Cancel</button>
                    </div>
                </div>

                <!-- STEP 2: MAP FIELDS -->
                <div class="idlm-import-step-content" id="import-step-content-2">
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 12px;">Map the headers from your CSV file to Lead Manager fields. Fields marked with * are required. Qualified leads also require all project-detail fields shown below.</p>

                    <div class="idlm-import-mapping-wrapper">
                        <table class="idlm-import-mapping-table">
                            <caption class="screen-reader-text">Map CRM fields to columns in the uploaded CSV file</caption>
                            <thead>
                                <tr>
                                    <th>CRM Field</th>
                                    <th>CSV Column</th>
                                </tr>
                            </thead>
                            <tbody id="idlm-import-mapping-tbody">
                                <!-- Dynamically populated by JS -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Default Settings Collapsible/Section -->
                    <div style="margin-top: 15px; border-top: 1px solid #dfe7f1; padding-top: 15px;">
                        <h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 600;">Import Defaults</h4>
                        <div class="idlm-form-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                            <div class="idlm-form-group">
                                <label for="import-default-source" style="font-size: 11px;">Default Source Channel</label>
                                <select id="import-default-source" class="idlm-input">
                                    <option value="CSV Import" selected>CSV Import</option>
                                    <option value="Website">Website</option>
                                    <option value="Meta Ads">Meta Ads</option>
                                    <option value="Google Ads">Google Ads</option>
                                    <option value="LinkedIn Ads">LinkedIn Ads</option>
                                    <option value="Referral">Referral</option>
                                </select>
                            </div>
                            <div class="idlm-form-group">
                                <label for="import-default-studio" style="font-size: 11px;">Default Preferred Studio</label>
                                <select id="import-default-studio" class="idlm-input">
                                    <option value="">No default studio</option>
                                    <?php foreach ($studios as $studio): ?>
                                        <option value="<?php echo esc_attr($studio); ?>"><?php echo esc_html($studio); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="idlm-form-group">
                                <label for="import-default-stage" style="font-size: 11px;">Default Pipeline Stage</label>
                                <select id="import-default-stage" class="idlm-input">
                                    <?php foreach ($stages as $stage): ?>
                                        <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="idlm-form-group">
                                <label for="import-default-call-status" style="font-size: 11px;">Default Call Status</label>
                                <select id="import-default-call-status" class="idlm-input">
                                    <?php foreach ($call_statuses as $cs): ?>
                                        <option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="idlm-btn idlm-btn-secondary" id="import-btn-back-1">Back</button>
                        <button type="button" class="idlm-btn idlm-btn-primary" id="import-btn-next-2">Next: Preview</button>
                    </div>
                </div>

                <!-- STEP 3: PREVIEW & OPTIONS -->
                <div class="idlm-import-step-content" id="import-step-content-3">
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 12px;">Confirm the parsed data preview (showing first 3 rows) and configure additional processing rules.</p>

                    <div class="idlm-import-status-list" id="idlm-import-projection" role="status" aria-live="polite">Validating every CSV row…</div>

                    <div class="idlm-import-preview-wrapper">
                        <table class="idlm-import-preview-table" id="idlm-import-preview-table">
                            <caption>Preview of the first three imported rows</caption>
                            <thead>
                                <tr id="idlm-import-preview-thead-tr">
                                    <!-- Dynamic headers -->
                                </tr>
                            </thead>
                            <tbody id="idlm-import-preview-tbody">
                                <!-- Dynamic rows -->
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 15px; border-top: 1px solid #dfe7f1; padding-top: 15px; display: flex; flex-direction: column; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" id="import-opt-route" checked>
                            Run Lead Assignment Rules (e.g. Round Robin) for imported leads
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" id="import-opt-capi" checked>
                            Trigger Conversion API (CAPI) events if imported stage triggers conversion
                        </label>
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="idlm-btn idlm-btn-secondary" id="import-btn-back-2">Back</button>
                        <button type="button" class="idlm-btn idlm-btn-primary" id="import-btn-start">Start Importing</button>
                    </div>
                </div>

                <!-- STEP 4: PROGRESS & STATUS -->
                <div class="idlm-import-step-content" id="import-step-content-4">
                    <div class="idlm-import-progress-wrapper" id="idlm-import-progress" role="progressbar" aria-labelledby="import-progress-text" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-valuetext="Initializing import">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 500;">
                            <span id="import-progress-text">Initializing import...</span>
                            <span id="import-progress-percent">0%</span>
                        </div>
                        <div class="idlm-import-progress-bar-bg">
                            <div class="idlm-import-progress-bar-fill" id="idlm-import-progress-fill"></div>
                        </div>
                    </div>

                    <div class="idlm-import-status-list" id="idlm-import-status-list" role="log" aria-live="polite" aria-relevant="additions text">
                        <!-- Code activity details populated dynamically -->
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-import-download-failures" hidden>Download failure report</button>
                        <button type="button" class="idlm-btn idlm-btn-primary" id="import-btn-close-final" hidden data-idlm-dialog-close>Close and refresh</button>
                    </div>
                </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Shared overlays (drawer, modals, and task sidebar) -->
        <?php echo $common_overlays_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guarded plugin template output. ?>

        <!-- Export Leads Modal -->
        <div class="idlm-modal idlm-dialog" id="idlm-export-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-export-title" aria-describedby="idlm-export-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="input[name='idlm-export-scope']:checked">
            <div class="idlm-modal-content idlm-dialog__panel idlm-export-dialog-panel">
                <header class="idlm-modal-header idlm-dialog__header">
                    <div>
                        <h2 class="idlm-dialog__title" id="idlm-export-title">Export leads to CSV</h2>
                        <p class="idlm-dialog__description" id="idlm-export-description">Choose a scope and confirm the matched record count before downloading.</p>
                    </div>
                    <button type="button" class="idlm-dialog__close" id="idlm-export-close" data-idlm-dialog-close aria-label="Close lead export"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </header>

                <div class="idlm-dialog__body">
                <fieldset class="idlm-form-group idlm-export-scope">
                    <legend class="idlm-form-label">1. Select export scope</legend>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 500; cursor: pointer;" id="idlm-export-scope-selected-label">
                            <input type="radio" name="idlm-export-scope" value="selected" id="idlm-export-scope-selected">
                            <span>Export selected leads only (<strong id="idlm-export-selected-count">0</strong> selected)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 500; cursor: pointer;">
                            <input type="radio" name="idlm-export-scope" value="filtered" id="idlm-export-scope-filtered" checked>
                            <span>Export all leads matching active filters below</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-weight: 500; cursor: pointer;">
                            <input type="radio" name="idlm-export-scope" value="all" id="idlm-export-scope-all">
                            <span>Export all leads (unfiltered)</span>
                        </label>
                    </div>
                </fieldset>

                <div id="idlm-export-filters-section" style="border: 1px solid var(--idlm-border); border-radius: 8px; padding: 15px; background: var(--idlm-surface-soft); margin-bottom: 20px; transition: var(--idlm-transition);">
                    <span class="idlm-form-label" style="font-weight: 600; display: block; margin-bottom: 12px; color: var(--idlm-text);">2. Filter Options to Apply</span>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label for="export-filter-search" style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-text-muted);">Search Query</label>
                            <input type="search" id="export-filter-search" class="idlm-input idlm-control-full" placeholder="Search..." autocomplete="off">
                        </div>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label for="export-filter-date-basis" style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-text-muted);">Date Basis</label>
                            <select id="export-filter-date-basis" class="idlm-input idlm-control-full">
                                <option value="assigned_at" selected>Assigned Date</option>
                                <option value="created_at">Created Date</option>
                                <option value="handoff_at">Sales Handoff Date</option>
                                <option value="updated_at">Last Updated Date</option>
                                <option value="qualified_at">Qualified Date</option>
                                <option value="conversion_time">Won Date</option>
                            </select>
                        </div>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label for="export-filter-date-range" style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-text-muted);">Date Range</label>
                            <select id="export-filter-date-range" class="idlm-input idlm-control-full">
                                <option value="">All Time</option>
                                <option value="today">Today</option>
                                <option value="yesterday">Yesterday</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="last_7_days">Last 7 Days</option>
                                <option value="last_30_days">Last 30 Days</option>
                                <option value="last_month">Last Month</option>
                                <option value="year">This Year</option>
                            </select>
                        </div>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label for="export-filter-source" style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-text-muted);">Channel Source</label>
                            <select id="export-filter-source" class="idlm-input idlm-control-full">
                                <option value="">All Channels</option>
                                <option value="Website">Website</option>
                                <option value="Meta Ads">Meta Ads</option>
                                <option value="Google Ads">Google Ads</option>
                                <option value="LinkedIn Ads">LinkedIn Ads</option>
                                <option value="Referral">Referral</option>
                                <option value="Offline/Walk-in">Offline/Walk-in</option>
                            </select>
                        </div>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label for="export-filter-stage" style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-text-muted);">Lead Stage</label>
                            <select id="export-filter-stage" class="idlm-input idlm-control-full">
                                <option value="">All Stages</option>
                                <?php foreach ($stages as $stage): ?>
                                    <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label for="export-filter-call-status" style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-text-muted);">Call Status</label>
                            <select id="export-filter-call-status" class="idlm-input idlm-control-full">
                                <option value="">All Statuses</option>
                                <?php foreach ($call_statuses as $cs): ?>
                                    <option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($can_assign_leads || (!$is_pre_sales && $reps_see_all)): ?>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label for="export-filter-rep" style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-text-muted);">Assigned Rep</label>
                            <select id="export-filter-rep" class="idlm-input idlm-control-full">
                                <option value="">All Reps</option>
                                <option value="0">Unassigned</option>
                                <?php foreach ($reps as $rep):
                                    if (!in_array($rep->ID, $active_rep_ids)) {
                                        continue;
                                    }
                                ?>
                                    <option value="<?php echo esc_attr($rep->ID); ?>"><?php echo esc_html($rep->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label for="export-filter-quality" style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-text-muted);">Lead Quality</label>
                            <select id="export-filter-quality" class="idlm-input idlm-control-full">
                                <option value="">All Qualities</option>
                                <option value="Qualified">Qualified</option>
                                <option value="Unqualified">Unqualified</option>
                                <option value="Pending">Pending (Choose...)</option>
                            </select>
                        </div>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label for="export-filter-followup" style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-text-muted);">Engagement Status</label>
                            <select id="export-filter-followup" class="idlm-input idlm-control-full">
                                <option value="">All Leads</option>
                                <option value="overdue">Overdue follow-up</option>
                                <option value="stale_updates">Needs fresh update (7+ days)</option>
                                <option value="pending_calls">Pending calls</option>
                                <option value="whatsapp_received">WhatsApp Message Received</option>
                                <option value="unread_whatsapp">Unread WhatsApp Messages</option>
                            </select>
                        </div>
                    </div>
                </div>

                <p class="idlm-export-count-preview" id="idlm-export-count-preview" role="status" aria-live="polite">Calculating matched leads…</p>
                </div>

                <footer class="idlm-dialog__footer">
                    <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-dialog-close>Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-confirm-export-btn">Download CSV</button>
                </footer>
            </div>
        </div>

        <?php if ($can_manage_options): ?>
        <div class="idlm-modal idlm-dialog" id="idlm-bulk-delete-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-bulk-delete-title" aria-describedby="idlm-bulk-delete-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-bulk-delete-cancel">
            <div class="idlm-modal-content idlm-dialog__panel">
                <header class="idlm-modal-header idlm-dialog__header">
                    <div>
                        <h2 class="idlm-dialog__title" id="idlm-bulk-delete-title">Permanently delete selected leads?</h2>
                        <p class="idlm-dialog__description" id="idlm-bulk-delete-description">This deletes <strong id="idlm-bulk-delete-count">0</strong> selected leads and their associated CRM records. This action cannot be undone.</p>
                    </div>
                    <button type="button" class="idlm-dialog__close" data-idlm-dialog-close aria-label="Close delete confirmation"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </header>
                <footer class="idlm-dialog__footer">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-bulk-delete-cancel" data-idlm-dialog-close>Keep leads</button>
                    <button type="button" class="idlm-btn idlm-btn-danger" id="idlm-confirm-bulk-delete">Permanently delete leads</button>
                </footer>
            </div>
        </div>
        <?php endif; ?>
