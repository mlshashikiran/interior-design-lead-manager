<?php
if (!defined('ABSPATH')) {
    exit;
}

$stages = isset($context['stages']) && is_array($context['stages']) ? $context['stages'] : array();
$kanban_stage_totals = isset($context['kanban_stage_totals']) && is_array($context['kanban_stage_totals']) ? $context['kanban_stage_totals'] : array();
$call_statuses = isset($context['call_statuses']) && is_array($context['call_statuses']) ? $context['call_statuses'] : array();
$lost_reasons = isset($context['lost_reasons']) && is_array($context['lost_reasons']) ? $context['lost_reasons'] : array();
$reps = isset($context['reps']) && is_array($context['reps']) ? $context['reps'] : array();
$currency_symbol = isset($context['currency_symbol']) ? (string) $context['currency_symbol'] : '';
$can_assign_leads = !empty($context['can_assign_leads']);
$ajax_url = isset($context['ajax_url']) ? (string) $context['ajax_url'] : '';
$nonce = isset($context['nonce']) ? (string) $context['nonce'] : '';
?>
        <script type="text/javascript">
            if (typeof idlm_vars === 'undefined') {
                var idlm_vars = {
                    ajax_url: '<?php echo esc_url($ajax_url); ?>',
                    nonce: '<?php echo esc_attr($nonce); ?>'
                };
            }
        </script>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div>
                    <h1>Kanban Deal Board</h1>
                    <p class="idlm-header-subtitle">Drag and drop leads across pipeline stages to manage sales progress</p>
                </div>
            </header>

            <section class="idlm-kanban-stats" aria-label="Kanban pipeline summary">
                <div class="idlm-kanban-stat">
                    <span class="idlm-kanban-stat-label">Assigned Leads — Filtered</span>
                    <strong class="idlm-kanban-stat-value" id="idlm-stat-assigned-active">—</strong>
                </div>
                <div class="idlm-kanban-stat idlm-kanban-stat-attention">
                    <span class="idlm-kanban-stat-label">Follow-ups Due</span>
                    <strong class="idlm-kanban-stat-value" id="idlm-stat-followups-due">—</strong>
                </div>
                <div class="idlm-kanban-stat">
                    <span class="idlm-kanban-stat-label">Qualified Leads — Filtered</span>
                    <strong class="idlm-kanban-stat-value" id="idlm-stat-qualified-pipeline">—</strong>
                </div>
                <div class="idlm-kanban-stat idlm-kanban-stat-value-card">
                    <span class="idlm-kanban-stat-label">Open Pipeline Value</span>
                    <strong class="idlm-kanban-stat-value" id="idlm-stat-pipeline-value">—</strong>
                </div>
                <div class="idlm-kanban-stat idlm-kanban-stat-success">
                    <span class="idlm-kanban-stat-label">Currently Won — Filtered</span>
                    <strong class="idlm-kanban-stat-value" id="idlm-stat-won-month">—</strong>
                </div>
                <div class="idlm-kanban-stat idlm-kanban-stat-success">
                    <span class="idlm-kanban-stat-label">Current Won Revenue — Filtered</span>
                    <strong class="idlm-kanban-stat-value" id="idlm-stat-won-revenue">—</strong>
                </div>
            </section>

            <!-- Filters Bar -->
            <div class="idlm-kanban-filters">
                <div class="idlm-filter-group">
                    <label>Search Leads</label>
                    <input type="text" id="idlm-kanban-filter-search" class="idlm-input" placeholder="Name, Email, or Phone...">
                </div>
                <div class="idlm-filter-group">
                    <label>Date Basis</label>
                    <select id="idlm-kanban-filter-date-basis" class="idlm-input">
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
                    <select id="idlm-kanban-filter-date-range" class="idlm-input">
                        <option value="">All Time</option>
                        <option value="today">Today</option>
                        <option value="yesterday">Yesterday</option>
                        <option value="week">This Week</option>
                        <option value="last_7_days">Last 7 Days</option>
                        <option value="month">This Month</option>
                        <option value="last_30_days">Last 30 Days</option>
                        <option value="last_month">Last Month</option>
                        <option value="year">This Year</option>
                    </select>
                </div>
                <div class="idlm-filter-group">
                    <label>Representative</label>
                    <select id="idlm-kanban-filter-rep" class="idlm-input">
                        <option value="">All Reps</option>
                        <option value="0">Unassigned</option>
                        <?php foreach ($reps as $rep): ?>
                            <option value="<?php echo esc_attr($rep->ID); ?>"><?php echo esc_html($rep->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="idlm-filter-group">
                    <label>Source Channel</label>
                    <select id="idlm-kanban-filter-source" class="idlm-input">
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
                    <label>Call Status</label>
                    <select id="idlm-kanban-filter-call-status" class="idlm-input">
                        <option value="">All Statuses</option>
                        <?php foreach ($call_statuses as $cs): ?>
                            <option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="idlm-filter-group">
                    <label>Lead Quality</label>
                    <select id="idlm-kanban-filter-quality" class="idlm-input">
                        <option value="">All Qualities</option>
                        <?php if ($can_assign_leads): ?>
                            <option value="Qualified Awaiting Assignment">Qualified – Awaiting Assignment</option>
                            <option value="Routing Exception">Routing Exceptions</option>
                        <?php endif; ?>
                        <option value="Qualified" selected>Qualified</option>
                        <option value="Qualified Plan Received">Qualified · Plan Received</option>
                        <option value="Qualified Plan Pending">Qualified · Plan Pending</option>
                        <option value="Unqualified">Unqualified</option>
                        <option value="Pending">Pending Qualification</option>
                    </select>
                </div>
            </div>

            <!-- Kanban Board -->
            <div class="idlm-kanban-board" id="idlm-kanban-board" data-currency-symbol="<?php echo esc_attr($currency_symbol); ?>">
                <?php foreach ($stages as $stage): ?>
                    <?php
                    $total_source = isset($kanban_stage_totals[$stage]) ? $kanban_stage_totals[$stage] : 'none';
                    $total_label = 'quote_value' === $total_source ? 'Quote Total' : ('deal_value' === $total_source ? 'Deal Total' : '');
                    ?>
                    <div class="idlm-kanban-column" data-stage="<?php echo esc_attr($stage); ?>" data-total-source="<?php echo esc_attr($total_source); ?>">
                        <div class="idlm-kanban-column-header">
                            <h3 class="idlm-kanban-column-title"><?php echo esc_html($stage); ?></h3>
                            <div class="idlm-kanban-column-metrics">
                                <span class="idlm-kanban-column-count">0</span>
                                <span class="idlm-kanban-column-pipeline-value"<?php echo 'none' === $total_source ? ' style="display: none;"' : ''; ?>><?php echo esc_html($total_label); ?>: <?php echo esc_html($currency_symbol); ?>0</span>
                            </div>
                        </div>
                        <div class="idlm-kanban-column-body">
                            <!-- Cards populated dynamically via JS -->
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Custom Kanban Deal Value Modal -->
        <div class="idlm-modal" id="idlm-kanban-deal-modal">
            <div class="idlm-modal-content" style="max-width: 400px; padding: 20px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 16px; margin:0;">Lead Won</h2>
                    <span class="idlm-close" id="idlm-kanban-deal-close">&times;</span>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label style="display:block; font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Enter Deal Value (<?php echo esc_html($currency_symbol); ?>) *</label>
                    <input type="number" step="0.01" min="0" id="idlm-kanban-deal-input" class="idlm-input" style="width: 100%;" placeholder="e.g. 150000">
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label for="idlm-kanban-discount-input" style="display:block; font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Discount % *</label>
                    <input type="number" step="0.01" min="0" max="100" id="idlm-kanban-discount-input" class="idlm-input" required style="width: 100%;" placeholder="e.g. 10">
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label for="idlm-kanban-property-location-input" style="display:block; font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Property Location / Pincode</label>
                    <input type="text" id="idlm-kanban-property-location-input" class="idlm-input" maxlength="255" style="width: 100%;" placeholder="e.g. North Bangalore / 560001">
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-kanban-deal-cancel" style="padding: 6px 12px;">Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-kanban-deal-save" style="padding: 6px 12px;">Save & Won</button>
                </div>
            </div>
        </div>

        <!-- Custom Kanban Quote Value Modal -->
        <div class="idlm-modal" id="idlm-kanban-quote-modal">
            <div class="idlm-modal-content" style="max-width: 400px; padding: 20px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 16px; margin:0;">Quote Shared</h2>
                    <span class="idlm-close" id="idlm-kanban-quote-close">&times;</span>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label for="idlm-kanban-quote-input" style="display:block; font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Quote Value (<?php echo esc_html($currency_symbol); ?>) *</label>
                    <input type="number" step="0.01" min="0.01" id="idlm-kanban-quote-input" class="idlm-input" required style="width: 100%;" placeholder="e.g. 150000">
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label style="display:block; font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Upload Quote</label>
                    <div class="idlm-drawer-file-row">
                        <input type="hidden" id="idlm-kanban-quote-file" value="">
                        <input type="file" class="idlm-file-input" data-input-id="idlm-kanban-quote-file" data-preview-id="idlm-kanban-quote-preview" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                        <button type="button" class="idlm-drawer-btn-file idlm-direct-upload-trigger">Upload Quote</button>
                        <span id="idlm-kanban-quote-preview" class="idlm-drawer-file-note">No file uploaded</span>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-kanban-quote-cancel" style="padding: 6px 12px;">Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-kanban-quote-save" style="padding: 6px 12px;">Save &amp; Share Quote</button>
                </div>
            </div>
        </div>

        <!-- Custom Kanban Lost Remark Modal -->
        <div class="idlm-modal" id="idlm-kanban-lost-modal">
            <div class="idlm-modal-content" style="max-width: 450px; padding: 20px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 16px; margin:0;">Why was this lead lost?</h2>
                    <span class="idlm-close" id="idlm-kanban-lost-close">&times;</span>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label for="idlm-kanban-lost-reason" style="display:block; font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Lost Reason *</label>
                    <select id="idlm-kanban-lost-reason" class="idlm-input" required style="width: 100%;">
                        <option value="">Select a reason</option>
                        <?php foreach ($lost_reasons as $lost_reason): ?>
                            <option value="<?php echo esc_attr($lost_reason); ?>"><?php echo esc_html($lost_reason); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label for="idlm-kanban-lost-input" style="display:block; font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Lost Remark *</label>
                    <textarea id="idlm-kanban-lost-input" class="idlm-input" rows="4" maxlength="1000" required style="width: 100%; resize: vertical;" placeholder="Add the reason the lead was lost..."></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-kanban-lost-cancel" style="padding: 6px 12px;">Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-kanban-lost-save" style="padding: 6px 12px;">Save &amp; Mark Lost</button>
                </div>
            </div>
        </div>
