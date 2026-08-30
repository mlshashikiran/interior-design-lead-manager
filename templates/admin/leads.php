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
                    <p class="idlm-header-subtitle"><?php echo $is_pre_sales ? 'Work today’s calls, WhatsApp follow-ups, callbacks and qualification decisions' : 'Manage interior design pipelines, communications and assignment distributions'; ?></p>
                </div>
                <div class="idlm-header-actions" style="display: flex; align-items: center; gap: 15px;">
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
                    <button type="button" class="idlm-whatsapp-unread-pill" id="idlm-whatsapp-unread-pill" title="<?php echo esc_attr($unread_whatsapp_count); ?> unread WhatsApp messages" aria-label="Show <?php echo esc_attr($unread_whatsapp_count); ?> unread WhatsApp messages" <?php echo $unread_whatsapp_count > 0 ? '' : 'style="display:none;"'; ?>>
                        <span class="dashicons dashicons-whatsapp" aria-hidden="true"></span>
                        <span class="idlm-whatsapp-unread-text"><strong><?php echo esc_html($unread_whatsapp_count); ?></strong> Unread WhatsApp</span>
                    </button>
                    <?php if ($can_manage_options): ?>
                        <button class="idlm-btn idlm-btn-secondary" id="idlm-import-btn" onclick="openImportModal()">Import CSV</button>
                        <button class="idlm-btn idlm-btn-secondary" id="idlm-export-btn">Export CSV</button>
                        <button class="idlm-btn idlm-btn-icon" id="idlm-routing-settings-btn" onclick="openRoutingSettingsModal()" title="Lead Routing Settings" style="display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; padding: 0; border-radius: 6px; background: var(--idlm-bg-secondary); border: 1px solid var(--idlm-border); color: var(--idlm-text); cursor: pointer; transition: all 0.2s;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-settings" style="display: block; margin: auto;"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                        </button>
                    <?php endif; ?>
                    <button class="idlm-btn idlm-btn-primary" onclick="openLeadModal()">+ Add Lead</button>
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

            <div class="idlm-filter-launcher">
                <button type="button" id="idlm-open-filters" class="idlm-btn idlm-btn-secondary idlm-open-filters" aria-controls="idlm-lead-filters" aria-expanded="false">
                    <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                    <span class="screen-reader-text">Open lead filters</span>
                    <span class="idlm-filter-count" aria-label="0 active filters" hidden>0</span>
                </button>
            </div>

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
                    <div class="idlm-filter-group idlm-filter-group-search">
                        <label>Search Leads</label>
                        <input type="text" id="filter-search" class="idlm-input" placeholder="Search...">
                    </div>
                    <div class="idlm-filter-group">
                        <label>Date Basis</label>
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
                        <label>Date Range</label>
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
                        <label>Stage</label>
                        <select id="filter-stage" class="idlm-input">
                            <option value="">All Stages</option>
                            <?php foreach ($stages as $stage): ?>
                                <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="idlm-filter-group">
                        <label>Call Status</label>
                        <select id="filter-call-status" class="idlm-input">
                            <option value="">All Statuses</option>
                            <?php foreach ($call_statuses as $cs): ?>
                                <option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($can_assign_leads || (!$is_pre_sales && $reps_see_all)): ?>
                    <div class="idlm-filter-group">
                        <label>Assigned Rep</label>
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
                        <label>&nbsp;</label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="button" id="btn-toggle-more-filters" class="idlm-btn idlm-btn-secondary" style="height: 29px; padding: 0 10px; display: flex; width: auto; box-sizing: border-box; align-items: center; justify-content: center; gap: 4px; font-size: 11px;" title="More Filters">
                                <span class="dashicons dashicons-filter" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px; margin: 0;"></span>
                                <span>More Filters</span>
                            </button>
                            <button type="button" id="btn-clear-filters" class="idlm-btn idlm-btn-secondary" style="height: 29px; padding: 0 10px; display: flex; width: auto; box-sizing: border-box; align-items: center; justify-content: center; gap: 4px; font-size: 11px;" title="Clear Filters">
                                <span class="dashicons dashicons-trash" style="font-size: 14px; width: 14px; height: 14px; line-height: 14px; margin: 0;"></span>
                                <span>Clear</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Collapsible Secondary Filters Row -->
                <div class="idlm-filters-secondary-row" id="idlm-secondary-filters-container" style="display: <?php echo $is_pre_sales ? 'grid' : 'none'; ?>;">
                    <div class="idlm-filter-group">
                        <label>Channel Source</label>
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
                        <label>Lead Quality</label>
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
                        <label>Engagement Status</label>
                        <select id="filter-followup" class="idlm-input">
                            <?php if ($is_pre_sales): ?>
                                <option value="action_required" selected>Action Required</option>
                                <option value="my_qualified_history">My Qualified History (read-only)</option>
                            <?php endif; ?>
                            <option value="">All Leads</option>
                            <option value="overdue">⏳ Overdue Follow-up</option>
                            <option value="pending_calls">📞 Pending Calls</option>
                            <option value="whatsapp_received">WhatsApp Message Received</option>
                            <option value="unread_whatsapp">Unread WhatsApp Messages</option>
                        </select>
                    </div>
                </div>
                </div>
            </div>

            <!-- Bulk Actions Panel -->
            <div class="idlm-bulk-bar" style="display:none;">
                <span class="idlm-selected-count">0 items selected</span>
                <div class="idlm-bulk-actions">
                    <select id="idlm-bulk-action-select" class="idlm-input">
                        <option value="">Select Bulk Action</option>
                        <?php if ($can_assign_leads): ?>
                            <option value="bulk_assign">Assign to Rep</option>
                        <?php endif; ?>
                        <option value="bulk_stage">Change Stage</option>
                        <option value="bulk_unqualified">Mark Unqualified</option>
                        <option value="bulk_audience_queue">Add to Audience Sync Queue</option>
                        <?php if ($can_manage_options): ?>
                            <option value="bulk_delete" style="color: #ef4444; font-weight: 600;">🗑️ Delete Leads</option>
                        <?php endif; ?>
                    </select>

                    <!-- Contextual dynamic parameters for bulk options -->
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

                    <select id="idlm-bulk-stage" class="idlm-input" style="display:none;">
                        <option value="">Select Stage</option>
                        <?php foreach ($stages as $stage): ?>
                            <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <button id="idlm-apply-bulk" class="idlm-btn idlm-btn-primary">Apply Action</button>
                </div>
            </div>

            <!-- Leads Data Table -->
            <div class="idlm-table-wrapper">
                <table class="idlm-table" id="idlm-leads-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="idlm-select-all"></th>
                            <?php if ($is_pre_sales): ?>
                            <th class="idlm-sortable-column" data-sort="name" aria-sort="none" role="button" tabindex="0">Lead<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="source" aria-sort="none" role="button" tabindex="0">Channel<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-col-created-at idlm-sortable-column" data-sort="created_at" aria-sort="none" role="button" tabindex="0">Created Date<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-col-created-at idlm-sortable-column" data-sort="assigned_at" aria-sort="none" role="button" tabindex="0">Assigned Date<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="age" aria-sort="none" role="button" tabindex="0">Age<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th>Today</th>
                            <th>Next Action</th>
                            <th class="idlm-sortable-column" data-sort="callback" aria-sort="none" role="button" tabindex="0">Callback<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="response" aria-sort="none" role="button" tabindex="0">Response<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="budget" aria-sort="none" role="button" tabindex="0">Budget<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="details" aria-sort="none" role="button" tabindex="0">Details<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="lead_quality" aria-sort="none" role="button" tabindex="0">Follow-up Status<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th>Actions</th>
                            <?php else: ?>
                            <th class="idlm-sortable-column" data-sort="name" aria-sort="none" role="button" tabindex="0">Name<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="source" aria-sort="none" role="button" tabindex="0">Channel<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="budget" aria-sort="none" role="button" tabindex="0">Budget<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="stage" aria-sort="none" role="button" tabindex="0">Stage<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="assigned_rep" aria-sort="none" role="button" tabindex="0">Assigned Rep<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="lead_quality" aria-sort="none" role="button" tabindex="0">Lead Quality<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-sortable-column" data-sort="tasks" aria-sort="none" role="button" tabindex="0">Tasks<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-col-created-at idlm-sortable-column" data-sort="assigned_at" aria-sort="none" role="button" tabindex="0">Assigned At<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
                            <th class="idlm-col-created-at idlm-sortable-column" data-sort="created_at" aria-sort="none" role="button" tabindex="0">Created At<span class="idlm-sort-indicator" aria-hidden="true"></span></th>
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
                <button class="idlm-pagination-btn" id="prev-page">Previous</button>
                <span id="page-num" class="idlm-pagination-info">Page 1 of 1</span>
                <button class="idlm-pagination-btn" id="next-page">Next</button>
            </div>
        </div>

        <!-- Add Manual Lead Modal -->
        <div class="idlm-modal" id="idlm-lead-modal">
            <div class="idlm-modal-content">
                <div class="idlm-modal-header">
                    <h2>Create Manual Lead</h2>
                    <span class="idlm-close" onclick="closeLeadModal()">&times;</span>
                </div>
                <form id="idlm-add-lead-form">
                    <div class="idlm-form-grid">
                        <div class="idlm-form-group">
                            <label>First Name*</label>
                            <input type="text" name="first_name" class="idlm-input" required>
                        </div>
                        <div class="idlm-form-group">
                            <label>Last Name</label>
                            <input type="text" name="last_name" class="idlm-input">
                        </div>
                        <div class="idlm-form-group">
                            <label>Email Address*</label>
                            <input type="email" name="email" class="idlm-input" required>
                        </div>
                        <div class="idlm-form-group">
                            <label>Phone Number*</label>
                            <input type="tel" name="phone" class="idlm-input" required>
                        </div>
                        <div class="idlm-form-group">
                            <label>Source Channel</label>
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
                            <label>Referrer Details</label>
                            <textarea name="referrer_details" class="idlm-input" rows="2" placeholder="Mention referral details..."></textarea>
                        </div>
                        <div class="idlm-form-group">
                            <label>Property Type</label>
                            <select name="property_type" class="idlm-input">
                                <option value="Apartment">Apartment</option>
                                <option value="Villa">Villa</option>
                                <option value="Independent House">Independent House</option>
                                <option value="Commercial">Commercial</option>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label>Number of Rooms</label>
                            <select name="num_rooms" class="idlm-input">
                                <?php foreach ($num_rooms_options as $opt): ?>
                                    <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label>Preferred Studio Location</label>
                            <select name="preferred_studio" class="idlm-input">
                                <?php foreach ($studios as $studio): ?>
                                    <option value="<?php echo esc_attr($studio); ?>"><?php echo esc_html($studio); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label>Lead Quality</label>
                            <select name="lead_quality" class="idlm-input">
                                <option value="" selected>Choose Quality...</option>
                                <option value="Qualified">Qualified</option>
                                <option value="Unqualified">Unqualified</option>
                            </select>
                        </div>
                        <div class="idlm-form-group">
                            <label>Lead Budget</label>
                            <input type="text" name="budget" class="idlm-input" placeholder="e.g. $15,000 or 25000">
                        </div>
                        <div class="idlm-form-group">
                            <label>Project Timeline</label>
                            <input type="text" name="project_timeline" class="idlm-input" placeholder="e.g. Immediate or 1-3 Months">
                        </div>
                        <div class="idlm-form-group">
                            <label>Property Location / Pincode</label>
                            <input type="text" name="property_location" class="idlm-input" placeholder="e.g. Downtown or 560001">
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
        <div class="idlm-modal" id="idlm-routing-modal">
            <div class="idlm-modal-content">
                <div class="idlm-modal-header">
                    <div>
                        <h2>Lead Routing & Assignment Rules</h2>
                        <p class="idlm-routing-modal-intro">Choose how leads are distributed and which studio each representative covers.</p>
                    </div>
                    <button type="button" class="idlm-close" onclick="closeRoutingModal()" aria-label="Close routing settings">&times;</button>
                </div>
                <form id="idlm-routing-settings-form">
                    <input type="hidden" name="action" value="idlm_save_routing_settings">
                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('idlm_admin_nonce')); ?>">

                    <div class="idlm-routing-modal-body">
                        <div class="idlm-form-grid idlm-routing-primary-grid">
                            <section class="idlm-form-group idlm-routing-section idlm-routing-strategy-section">
                                <div class="idlm-routing-section-heading">
                                    <span class="idlm-routing-step">1</span>
                                    <div>
                                        <h3>Assignment strategy</h3>
                                        <p>Choose when and how incoming leads should be assigned.</p>
                                    </div>
                                </div>
                                <select name="assignment_mode" id="idlm-routing-mode-select" class="idlm-input" onchange="toggleRoutingModalSourcePanel(this.value)">

                                    <option value="manual" <?php selected($assignment_mode, 'manual'); ?>>Manual Only (Admin delegates from tables)</option>
                                    <option value="pre_sales" <?php selected($assignment_mode, 'pre_sales'); ?>>Pre-Sales Intake (Round robin across Pre-Sales users)</option>
                                    <option value="round_robin" <?php selected($assignment_mode, 'round_robin'); ?>>Round Robin (Autosync rotation across active reps)</option>
                                    <option value="source_based" <?php selected($assignment_mode, 'source_based'); ?>>Source-Based (Route channels to specific reps)</option>
                                    <option value="studio_based" <?php selected($assignment_mode, 'studio_based'); ?>>Studio-Based (Route preferred studios to assigned reps)</option>
                                </select>
                            </section>

                            <section class="idlm-form-group idlm-routing-section idlm-routing-reps-section">
                                <div class="idlm-routing-section-heading">
                                    <span class="idlm-routing-step">2</span>
                                    <div>
                                        <h3>Representative coverage</h3>
                                        <p>Enable representatives and select the studio whose leads they can receive.</p>
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

                        <div id="idlm-routing-source-mapping-subpanel" style="display:<?php echo ($assignment_mode === 'source_based') ? 'grid' : 'none'; ?>;" class="idlm-form-grid idlm-routing-section idlm-routing-source-section">
                            <div class="idlm-routing-section-heading full">
                                <span class="idlm-routing-step">3</span>
                                <div><h3>Source routing maps</h3><p>Send each lead source to a specific active representative.</p></div>
                            </div>
                            <?php foreach (array('Website', 'Meta Ads', 'Google Ads', 'LinkedIn Ads', 'Referral', 'Offline/Walk-in') as $src):
                                $field_name = 'source_map_' . str_replace(array(' ', '/', '-'), '_', strtolower($src));
                                $val = isset($source_maps[$src]) ? $source_maps[$src] : 0;
                            ?>
                                <div class="idlm-form-group">
                                    <label>Route <?php echo esc_html($src); ?> to</label>
                                    <select name="<?php echo esc_attr($field_name); ?>" class="idlm-input">
                                        <option value="0">Unassigned</option>
                                        <?php foreach ($reps as $rep): ?>
                                            <option value="<?php echo esc_attr($rep->ID); ?>" <?php selected($val, $rep->ID); ?>><?php echo esc_html($rep->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Fallback Routing sub panel -->

                        <section id="idlm-routing-fallback-subpanel" class="idlm-routing-section idlm-routing-fallback-section">
                            <div class="idlm-routing-section-heading">
                                <span class="idlm-routing-step" aria-hidden="true">↻</span>
                                <div><h3>Routing team & safeguards</h3><p>See who receives new leads, recover the unassigned backlog, and configure qualified-lead handoff independently.</p></div>
                            </div>

                            <div class="idlm-routing-status-strip" aria-label="Current routing status">
                                <div class="idlm-routing-status-card">
                                    <span>New lead routing</span>
                                    <strong><?php echo esc_html($assignment_mode_label); ?></strong>
                                    <small><?php echo 'manual' === $assignment_mode ? 'New leads wait for an admin.' : 'New leads route automatically.'; ?></small>
                                </div>
                                <div class="idlm-routing-status-card <?php echo $active_pre_sales_count > 0 ? 'is-ready' : 'is-warning'; ?>">
                                    <span>Active pre-sales</span>
                                    <strong><?php echo esc_html($active_pre_sales_count); ?> available</strong>
                                    <small><?php echo $active_pre_sales_count > 0 ? 'Eligible for new intake leads.' : 'No one can receive pre-sales leads.'; ?></small>
                                </div>
                                <div class="idlm-routing-status-card <?php echo $unassigned_intake_count > 0 ? 'is-warning' : 'is-ready'; ?>">
                                    <span>Unassigned backlog</span>
                                    <strong><?php echo esc_html($unassigned_intake_count); ?> leads</strong>
                                    <small><?php echo $unassigned_intake_count > 0 ? 'Waiting for an owner.' : 'No open leads are waiting.'; ?></small>
                                </div>
                                <div class="idlm-routing-status-card <?php echo '1' === $fallback_enabled ? 'is-ready' : 'is-inactive'; ?>">
                                    <span>Backlog recovery</span>
                                    <strong><?php echo '1' === $fallback_enabled ? 'Enabled' : 'Disabled'; ?></strong>
                                    <small><?php echo '1' === $fallback_enabled ? esc_html(sprintf('Runs after %d working hours.', $fallback_hours)) : 'Existing unassigned leads stay put.'; ?></small>
                                </div>
                            </div>

                            <div class="idlm-routing-control-card idlm-routing-team-card">
                                <div class="idlm-routing-control-heading">
                                    <span class="idlm-routing-control-number">1</span>
                                    <div><strong>Active Pre-Sales Team</strong><small>Controls who can receive new leads immediately.</small></div>
                                </div>
                                <div class="idlm-form-group" style="width:100%;">
                                    <label>Available now</label>
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

                            <div class="idlm-routing-control-card">
                                <div class="idlm-routing-control-heading">
                                    <span class="idlm-routing-control-number">2</span>
                                    <div><strong>Unassigned Lead Recovery</strong><small>A separate safety net for older leads that currently have no owner.</small></div>
                                </div>
                                <label class="idlm-routing-switch-row">
                                    <input type="checkbox" name="fallback_assignment_enabled" value="1" <?php checked($fallback_enabled, '1'); ?>>
                                    <span><strong>Automatically assign the unassigned backlog</strong><small>When enabled, eligible leads return to the active pre-sales pool after the fallback threshold.</small></span>
                                </label>
                            </div>

                            <div class="idlm-routing-control-card">
                                <div class="idlm-routing-control-heading">
                                    <span class="idlm-routing-control-number">3</span>
                                    <div><strong>Qualified Lead Handoff</strong><small>Optional coverage for moving qualified pre-sales leads to the sales team.</small></div>
                                </div>
                                <label class="idlm-routing-switch-row">
                                    <input type="checkbox" name="qualified_fallback_enabled" value="1" <?php checked($qualified_fallback_enabled, '1'); ?>>
                                    <span><strong>Manager OOO: auto-assign qualified leads</strong><small>After the threshold, hand qualified pre-sales leads to studio-matched active sales representatives, then the general sales pool.</small></span>
                                </label>
                            </div>
                            <div class="idlm-routing-fallback-fields">
                                <div class="idlm-form-group">
                                    <label>Maximum active leads per rep</label>
                                    <div class="idlm-routing-input-suffix"><input type="number" name="max_active_leads_per_rep" class="idlm-input" min="0" max="10000" value="<?php echo esc_attr($max_active_leads_per_rep); ?>"><span>leads</span></div>
                                    <p class="description">Applied to every automatic pre-sales and sales routing pool. Enter 0 for unlimited.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label>Fallback threshold</label>
                                    <div class="idlm-routing-input-suffix"><input type="number" name="fallback_assignment_hours" class="idlm-input" min="1" max="720" value="<?php echo esc_attr($fallback_hours); ?>"><span>working hours</span></div>
                                    <p class="description">Wait time before fallback assignment.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label>Qualified handoff threshold</label>
                                    <div class="idlm-routing-input-suffix"><input type="number" name="qualified_fallback_hours" class="idlm-input" min="1" max="720" value="<?php echo esc_attr($qualified_fallback_hours); ?>"><span>working hours</span></div>
                                    <p class="description">Manager OOO wait time measured from qualification.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label>Manager OOO ends</label>
                                    <input type="datetime-local" name="qualified_fallback_end_at" class="idlm-input" value="<?php echo esc_attr($qualified_fallback_end_at ? str_replace(' ', 'T', substr($qualified_fallback_end_at, 0, 16)) : ''); ?>">
                                    <p class="description">Optional. OOO handoff disables automatically after this time.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label>Missing studio behavior</label>
                                    <select name="studio_mismatch_behavior" class="idlm-input">
                                        <option value="general_pool" <?php selected($studio_mismatch_behavior, 'general_pool'); ?>>Use general active sales pool</option>
                                        <option value="hold_for_manager" <?php selected($studio_mismatch_behavior, 'hold_for_manager'); ?>>Hold for manager review</option>
                                    </select>
                                </div>
                                <div class="idlm-form-group">
                                    <label>Pending task policy</label>
                                    <select name="handoff_task_policy" class="idlm-input">
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
                                <div class="idlm-form-group">
                                    <label class="idlm-routing-switch-row"><input type="checkbox" name="routing_exception_notifications_enabled" value="1" <?php checked($routing_exception_notifications_enabled, '1'); ?>><span><strong>Email routing exceptions</strong><small>Notify the WordPress administrator when a routing pool is unavailable.</small></span></label>
                                </div>
                                <div class="idlm-form-group">
                                    <label>Daily working hours</label>
                                    <div class="idlm-routing-time-range">
                                    <input type="time" name="working_hours_start" class="idlm-input" value="<?php echo esc_attr($working_hours_start); ?>">
                                    <span>to</span>
                                    <input type="time" name="working_hours_end" class="idlm-input" value="<?php echo esc_attr($working_hours_end); ?>">
                                    </div>
                                    <p class="description">Hours when the business is open.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label>Calls per follow-up cycle</label>
                                    <input type="number" name="presales_calls_per_day" class="idlm-input" min="1" max="20" value="<?php echo esc_attr($presales_targets['calls']); ?>">
                                    <p class="description">Partial progress carries forward until this target is reached.</p>
                                </div>
                                <div class="idlm-form-group">
                                    <label>WhatsApp messages per follow-up cycle</label>
                                    <input type="number" name="presales_whatsapp_per_day" class="idlm-input" min="0" max="20" value="<?php echo esc_attr($presales_targets['whatsapp']); ?>">
                                    <p class="description">Enter 0 when WhatsApp is not required.</p>
                                </div>
                                <div class="idlm-form-group idlm-routing-days-group">
                                <label>Business working days</label>
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
                                    <label>Fallback health</label>
                                    <p class="description">Last completed: <?php echo esc_html($fallback_last_completed); ?> · Status: <?php echo esc_html($fallback_last_status); ?> · Open routing exceptions: <?php echo esc_html($routing_exception_count); ?></p>
                                    <button type="button" class="idlm-btn idlm-btn-secondary idlm-run-fallback-now">Run Fallback Now</button>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="idlm-routing-modal-footer">
                        <button type="button" class="idlm-btn idlm-btn-secondary" onclick="closeRoutingModal()">Cancel</button>
                        <button type="submit" class="idlm-btn idlm-btn-primary">Save Routing Rules</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($can_manage_options): ?>
        <!-- Import Leads Modal -->
        <div class="idlm-modal" id="idlm-import-modal">
            <div class="idlm-modal-content" style="max-width: 650px;">
                <div class="idlm-modal-header">
                    <h2>Import Leads from CSV</h2>
                    <span class="idlm-close" onclick="closeImportModal()">&times;</span>
                </div>

                <!-- Steps Navigation Indicator -->
                <div class="idlm-import-steps">
                    <div class="idlm-import-step-item active" id="import-step-nav-1">
                        <span class="idlm-import-step-number">1</span> Upload CSV
                    </div>
                    <div class="idlm-import-step-item" id="import-step-nav-2">
                        <span class="idlm-import-step-number">2</span> Map Fields
                    </div>
                    <div class="idlm-import-step-item" id="import-step-nav-3">
                        <span class="idlm-import-step-number">3</span> Preview & Options
                    </div>
                    <div class="idlm-import-step-item" id="import-step-nav-4">
                        <span class="idlm-import-step-number">4</span> Progress
                    </div>
                </div>

                <!-- STEP 1: UPLOAD CSV -->
                <div class="idlm-import-step-content active" id="import-step-content-1">
                    <div class="idlm-import-drag-zone" id="idlm-import-drag-zone">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span class="idlm-import-drag-text">Drag and drop your CSV file here, or click to browse</span>
                        <span class="idlm-import-drag-subtext">Supports standard .csv files up to 5MB</span>
                        <input type="file" id="idlm-import-file-input" accept=".csv" style="display: none;">
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
                        <a href="#" class="idlm-btn idlm-btn-secondary" id="idlm-import-download-template" style="text-decoration: none; display: inline-flex; gap: 5px;">
                            📥 Download Sample Template
                        </a>
                        <button type="button" class="idlm-btn idlm-btn-secondary" onclick="closeImportModal()">Cancel</button>
                    </div>
                </div>

                <!-- STEP 2: MAP FIELDS -->
                <div class="idlm-import-step-content" id="import-step-content-2">
                    <p style="font-size: 13px; color: #64748b; margin-bottom: 12px;">Map the headers from your CSV file to Lead Manager fields. Fields marked with * are highly recommended.</p>

                    <div class="idlm-import-mapping-wrapper">
                        <table class="idlm-import-mapping-table">
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
                                <label style="font-size: 11px;">Default Source Channel</label>
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
                                <label style="font-size: 11px;">Default Pipeline Stage</label>
                                <select id="import-default-stage" class="idlm-input">
                                    <?php foreach ($stages as $stage): ?>
                                        <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-size: 11px;">Default Call Status</label>
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

                    <div class="idlm-import-preview-wrapper">
                        <table class="idlm-import-preview-table" id="idlm-import-preview-table">
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
                    <div class="idlm-import-progress-wrapper">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 13px; font-weight: 500;">
                            <span id="import-progress-text">Initializing import...</span>
                            <span id="import-progress-percent">0%</span>
                        </div>
                        <div class="idlm-import-progress-bar-bg">
                            <div class="idlm-import-progress-bar-fill" id="idlm-import-progress-fill"></div>
                        </div>
                    </div>

                    <div class="idlm-import-status-list" id="idlm-import-status-list">
                        <!-- Code activity details populated dynamically -->
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="idlm-btn idlm-btn-primary" id="import-btn-close-final" style="display: none;" onclick="closeImportModal()">Close & Refresh</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Shared overlays (drawer, modals, and task sidebar) -->
        <?php echo $common_overlays_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Guarded plugin template output. ?>

        <!-- Export Leads Modal -->
        <div class="idlm-modal" id="idlm-export-modal" style="z-index: 100000;">
            <div class="idlm-modal-content" style="max-width: 550px; padding: 25px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px; border-bottom: 1px solid var(--idlm-border);">
                    <h2 style="font-size: 18px; font-weight: 700; color: var(--idlm-text);">Export Leads to CSV</h2>
                    <span class="idlm-close" onclick="closeExportModal()">&times;</span>
                </div>

                <div class="idlm-form-group" style="margin-bottom: 20px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 10px; color: var(--idlm-text);">1. Select Export Scope</label>
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
                </div>

                <div id="idlm-export-filters-section" style="border: 1px solid var(--idlm-border); border-radius: 8px; padding: 15px; background: var(--idlm-surface-soft); margin-bottom: 20px; transition: all 0.2s;">
                    <label style="font-weight: 600; display: block; margin-bottom: 12px; color: var(--idlm-text);">2. Filter Options to Apply</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-muted);">Search Query</label>
                            <input type="text" id="export-filter-search" class="idlm-input" style="width: 100%;" placeholder="Search...">
                        </div>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-muted);">Date Basis</label>
                            <select id="export-filter-date-basis" class="idlm-input" style="width: 100%;">
                                <option value="assigned_at" selected>Assigned Date</option>
                                <option value="created_at">Created Date</option>
                                <option value="handoff_at">Sales Handoff Date</option>
                                <option value="updated_at">Last Updated Date</option>
                                <option value="qualified_at">Qualified Date</option>
                                <option value="conversion_time">Won Date</option>
                            </select>
                        </div>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-muted);">Date Range</label>
                            <select id="export-filter-date-range" class="idlm-input" style="width: 100%;">
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
                            <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-muted);">Channel Source</label>
                            <select id="export-filter-source" class="idlm-input" style="width: 100%;">
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
                            <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-muted);">Lead Stage</label>
                            <select id="export-filter-stage" class="idlm-input" style="width: 100%;">
                                <option value="">All Stages</option>
                                <?php foreach ($stages as $stage): ?>
                                    <option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-muted);">Call Status</label>
                            <select id="export-filter-call-status" class="idlm-input" style="width: 100%;">
                                <option value="">All Statuses</option>
                                <?php foreach ($call_statuses as $cs): ?>
                                    <option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($can_assign_leads || (!$is_pre_sales && $reps_see_all)): ?>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-muted);">Assigned Rep</label>
                            <select id="export-filter-rep" class="idlm-input" style="width: 100%;">
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
                            <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-muted);">Lead Quality</label>
                            <select id="export-filter-quality" class="idlm-input" style="width: 100%;">
                                <option value="">All Qualities</option>
                                <option value="Qualified">Qualified</option>
                                <option value="Unqualified">Unqualified</option>
                                <option value="Pending">Pending (Choose...)</option>
                            </select>
                        </div>
                        <div class="idlm-filter-group" style="margin-bottom: 0;">
                            <label style="font-size: 11px; font-weight: 600; text-transform: uppercase; color: var(--idlm-muted);">Engagement Status</label>
                            <select id="export-filter-followup" class="idlm-input" style="width: 100%;">
                                <option value="">All Leads</option>
                                <option value="overdue">⏳ Overdue Follow-up</option>
                                <option value="pending_calls">📞 Pending Calls</option>
                                <option value="whatsapp_received">WhatsApp Message Received</option>
                                <option value="unread_whatsapp">Unread WhatsApp Messages</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-secondary" onclick="closeExportModal()">Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-confirm-export-btn" style="min-width: 120px;">Download CSV</button>
                </div>
            </div>
        </div>

