<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_stages = isset($context['stages']) && is_array($context['stages']) ? $context['stages'] : array();
$idlm_kanban_stage_totals = isset($context['kanban_stage_totals']) && is_array($context['kanban_stage_totals']) ? $context['kanban_stage_totals'] : array();
$idlm_call_statuses = isset($context['call_statuses']) && is_array($context['call_statuses']) ? $context['call_statuses'] : array();
$idlm_lost_reasons = isset($context['lost_reasons']) && is_array($context['lost_reasons']) ? $context['lost_reasons'] : array();
$idlm_reps = isset($context['reps']) && is_array($context['reps']) ? $context['reps'] : array();
$idlm_currency_symbol = isset($context['currency_symbol']) ? (string) $context['currency_symbol'] : '';
$idlm_can_assign_leads = !empty($context['can_assign_leads']);
$idlm_stale_lead_count = isset($context['stale_lead_count']) ? absint($context['stale_lead_count']) : 0;
$idlm_ajax_url = isset($context['ajax_url']) ? (string) $context['ajax_url'] : '';
$idlm_nonce = isset($context['nonce']) ? (string) $context['nonce'] : '';
?>
        <script type="text/javascript">
            if (typeof idlm_vars === 'undefined') {
                var idlm_vars = {
                    ajax_url: '<?php echo esc_url($idlm_ajax_url); ?>',
                    nonce: '<?php echo esc_attr($idlm_nonce); ?>'
                };
            }
        </script>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div>
                    <h1>Kanban Deal Board</h1>
                </div>
                <div class="idlm-header-actions">
                    <label class="idlm-header-search" for="idlm-kanban-filter-search">
                        <span class="screen-reader-text">Search leads</span>
                        <input type="search" id="idlm-kanban-filter-search" class="idlm-input" autocomplete="off" placeholder="Search leads">
                    </label>
                    <button type="button" id="idlm-kanban-open-filters" class="idlm-btn idlm-btn-secondary idlm-header-filter-button" aria-controls="idlm-kanban-filters" aria-expanded="false">
                        <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                        <span>Filters</span>
                        <span class="idlm-filter-count idlm-kanban-filter-count" aria-label="0 active filters" hidden>0</span>
                    </button>
                    <button type="button" class="idlm-pending-calls-pill" id="idlm-kanban-stale-leads-pill" title="Show pipeline leads that need a fresh update" aria-label="Show <?php echo esc_attr($idlm_stale_lead_count); ?> pipeline leads that need a fresh update" <?php echo $idlm_stale_lead_count > 0 ? '' : 'style="display:none;"'; ?>>
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <span class="idlm-pending-text"><strong><?php echo esc_html($idlm_stale_lead_count); ?></strong> Needs update</span>
                    </button>
                </div>
            </header>

            <section class="idlm-metric-grid idlm-kanban-stats" aria-label="Kanban pipeline summary">
                <div class="idlm-metric idlm-metric-static idlm-kanban-stat">
                    <span class="idlm-metric-label">Overall Leads — Filtered</span>
                    <strong class="idlm-metric-value" id="idlm-stat-overall-leads">—</strong>
                </div>
                <div class="idlm-metric idlm-metric-static idlm-kanban-stat idlm-kanban-stat-attention">
                    <span class="idlm-metric-label">Follow-ups Due</span>
                    <strong class="idlm-metric-value" id="idlm-stat-followups-due">—</strong>
                </div>
                <div class="idlm-metric idlm-metric-static idlm-kanban-stat idlm-kanban-stat-value-card">
                    <span class="idlm-metric-label">Open Pipeline Value</span>
                    <strong class="idlm-metric-value" id="idlm-stat-pipeline-value">—</strong>
                </div>
                <div class="idlm-metric idlm-metric-static idlm-kanban-stat idlm-kanban-stat-success">
                    <span class="idlm-metric-label">Won This Month</span>
                    <strong class="idlm-metric-value" id="idlm-stat-won-month">—</strong>
                </div>
                <div class="idlm-metric idlm-metric-static idlm-kanban-stat idlm-kanban-stat-success">
                    <span class="idlm-metric-label">Won Revenue This Month</span>
                    <strong class="idlm-metric-value" id="idlm-stat-won-revenue">—</strong>
                </div>
            </section>

            <div class="idlm-filters-backdrop" id="idlm-kanban-filters-backdrop" hidden></div>
            <div class="idlm-filters-panel idlm-kanban-filters" id="idlm-kanban-filters" role="dialog" aria-modal="true" aria-labelledby="idlm-kanban-filters-title" aria-hidden="true" tabindex="-1">
                <div class="idlm-filters-panel-header">
                    <div>
                        <h2 id="idlm-kanban-filters-title">Filter pipeline</h2>
                        <p>Narrow the board by assignment, activity, and qualification.</p>
                    </div>
                    <button type="button" id="idlm-kanban-close-filters" class="idlm-filters-close" aria-label="Close filters">
                        <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                    </button>
                </div>
                <div class="idlm-filters-panel-body">
                <div class="idlm-filter-group">
                    <label for="idlm-kanban-filter-date-basis">Date basis</label>
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
                    <label for="idlm-kanban-filter-date-range">Date range</label>
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
                    <label for="idlm-kanban-filter-rep">Representative</label>
                    <select id="idlm-kanban-filter-rep" class="idlm-input">
                        <option value="">All Reps</option>
                        <option value="0">Unassigned</option>
                        <?php foreach ($idlm_reps as $idlm_rep): ?>
                            <option value="<?php echo esc_attr($idlm_rep->ID); ?>"><?php echo esc_html($idlm_rep->display_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="idlm-filter-group">
                    <label for="idlm-kanban-filter-source">Source channel</label>
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
                    <label for="idlm-kanban-filter-call-status">Call status</label>
                    <select id="idlm-kanban-filter-call-status" class="idlm-input">
                        <option value="">All Statuses</option>
                        <?php foreach ($idlm_call_statuses as $idlm_cs): ?>
                            <option value="<?php echo esc_attr($idlm_cs); ?>"><?php echo esc_html($idlm_cs); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="idlm-filter-group">
                    <label for="idlm-kanban-filter-quality">Lead quality</label>
                    <select id="idlm-kanban-filter-quality" class="idlm-input">
                        <option value="">All Qualities</option>
                        <?php if ($idlm_can_assign_leads): ?>
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
                <div class="idlm-filter-group">
                    <label for="idlm-kanban-filter-attention">Update status</label>
                    <select id="idlm-kanban-filter-attention" class="idlm-input">
                        <option value="">All leads</option>
                        <option value="stale_updates">Needs fresh update (7+ days)</option>
                    </select>
                </div>
                <div class="idlm-filter-group">
                    <label for="idlm-kanban-sort">Sort cards</label>
                    <select id="idlm-kanban-sort" class="idlm-input">
                        <option value="newest" selected>Newest Leads</option>
                        <option value="oldest">Oldest Leads</option>
                        <option value="name_asc">Name A–Z</option>
                        <option value="name_desc">Name Z–A</option>
                    </select>
                </div>
                <div class="idlm-filter-group">
                    <button type="button" id="idlm-kanban-clear-filters" class="idlm-btn idlm-btn-secondary">Clear filters</button>
                </div>
                </div>
            </div>

            <p id="idlm-kanban-live-status" class="screen-reader-text" role="status" aria-live="polite" aria-atomic="true"></p>

            <div class="idlm-kanban-stage-tabs" id="idlm-kanban-stage-tabs" role="tablist" aria-label="Pipeline stages">
                <?php foreach ($idlm_stages as $idlm_stage_index => $idlm_stage): ?>
                    <?php
                    $idlm_stage_slug = sanitize_html_class(strtolower(str_replace(' ', '-', $idlm_stage)));
                    $idlm_stage_panel_id = 'idlm-kanban-panel-' . $idlm_stage_slug;
                    ?>
                    <button type="button" class="idlm-kanban-stage-tab<?php echo 0 === $idlm_stage_index ? ' is-active' : ''; ?>" role="tab" data-stage="<?php echo esc_attr($idlm_stage); ?>" aria-selected="<?php echo 0 === $idlm_stage_index ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr($idlm_stage_panel_id); ?>" tabindex="<?php echo 0 === $idlm_stage_index ? '0' : '-1'; ?>">
                        <span><?php echo esc_html($idlm_stage); ?></span>
                        <span class="idlm-kanban-stage-tab-count" aria-label="0 leads">0</span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="idlm-kanban-pagination" aria-label="Pipeline pages">
                <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-kanban-prev-page" disabled>Previous page</button>
                <span id="idlm-kanban-page-status" role="status" aria-live="polite">Loading leads…</span>
                <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-kanban-next-page" disabled>Next page</button>
            </div>

            <!-- Kanban Board -->
            <div class="idlm-kanban-board" id="idlm-kanban-board" data-currency-symbol="<?php echo esc_attr($idlm_currency_symbol); ?>" role="region" aria-label="Sales pipeline board" tabindex="0">
                <?php foreach ($idlm_stages as $idlm_stage): ?>
                    <?php
                    $idlm_total_source = isset($idlm_kanban_stage_totals[$idlm_stage]) ? $idlm_kanban_stage_totals[$idlm_stage] : 'none';
                    $idlm_total_label = 'quote_value' === $idlm_total_source ? 'Quote Total' : ('deal_value' === $idlm_total_source ? 'Deal Total' : '');
                    ?>
                    <?php
                    $idlm_stage_slug = sanitize_html_class(strtolower(str_replace(' ', '-', $idlm_stage)));
                    $idlm_stage_heading_id = 'idlm-kanban-stage-' . $idlm_stage_slug;
                    $idlm_stage_panel_id = 'idlm-kanban-panel-' . $idlm_stage_slug;
                    ?>
                    <section class="idlm-kanban-column" id="<?php echo esc_attr($idlm_stage_panel_id); ?>" data-stage="<?php echo esc_attr($idlm_stage); ?>" data-total-source="<?php echo esc_attr($idlm_total_source); ?>" aria-labelledby="<?php echo esc_attr($idlm_stage_heading_id); ?>">
                        <div class="idlm-kanban-column-header">
                            <h3 class="idlm-kanban-column-title" id="<?php echo esc_attr($idlm_stage_heading_id); ?>"><?php echo esc_html($idlm_stage); ?></h3>
                            <div class="idlm-kanban-column-metrics">
                                <span class="idlm-kanban-column-count">0</span>
                                <span class="idlm-kanban-column-pipeline-value"<?php echo 'none' === $idlm_total_source ? ' style="display: none;"' : ''; ?>><?php echo esc_html($idlm_total_label); ?>: <?php echo esc_html($idlm_currency_symbol); ?>0</span>
                            </div>
                        </div>
                        <div class="idlm-kanban-column-body">
                            <!-- Cards populated dynamically via JS -->
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Kanban Won Dialog -->
        <div class="idlm-modal idlm-dialog" id="idlm-kanban-deal-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-kanban-deal-title" aria-describedby="idlm-kanban-deal-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-kanban-deal-input">
            <div class="idlm-modal-content idlm-dialog__panel">
                <header class="idlm-modal-header idlm-dialog__header"><div><h2 class="idlm-dialog__title" id="idlm-kanban-deal-title">Move lead to Won</h2><p class="idlm-dialog__description" id="idlm-kanban-deal-description">Record the final commercial value before moving this card.</p></div><button type="button" class="idlm-dialog__close" id="idlm-kanban-deal-close" data-idlm-dialog-close aria-label="Close Won dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header>
                <div class="idlm-dialog__body">
                    <div class="idlm-form-group"><label for="idlm-kanban-deal-input">Deal value (<?php echo esc_html($idlm_currency_symbol); ?>) <span class="idlm-required-indicator" aria-hidden="true">*</span></label><input type="number" step="0.01" min="0.01" inputmode="decimal" id="idlm-kanban-deal-input" class="idlm-input" required aria-errormessage="idlm-kanban-deal-error" placeholder="e.g. 150000"><p class="idlm-field-error" id="idlm-kanban-deal-error" role="alert" hidden></p></div>
                    <div class="idlm-form-group"><label for="idlm-kanban-discount-input">Discount percentage <span class="idlm-required-indicator" aria-hidden="true">*</span></label><input type="number" step="0.01" min="0" max="100" inputmode="decimal" id="idlm-kanban-discount-input" class="idlm-input" required aria-errormessage="idlm-kanban-discount-error" placeholder="e.g. 10"><p class="idlm-field-error" id="idlm-kanban-discount-error" role="alert" hidden></p></div>
                    <div class="idlm-form-group"><label for="idlm-kanban-property-location-input">Property location or pincode</label><input type="text" id="idlm-kanban-property-location-input" class="idlm-input" maxlength="255" autocomplete="street-address" placeholder="e.g. North Bangalore / 560001"></div>
                </div>
                <footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-kanban-deal-cancel" data-idlm-dialog-close>Cancel</button><button type="button" class="idlm-btn idlm-btn-primary" id="idlm-kanban-deal-save">Save and mark Won</button></footer>
            </div>
        </div>

        <!-- Kanban Quote Shared Dialog -->
        <div class="idlm-modal idlm-dialog" id="idlm-kanban-quote-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-kanban-quote-title" aria-describedby="idlm-kanban-quote-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-kanban-quote-input">
            <div class="idlm-modal-content idlm-dialog__panel">
                <header class="idlm-modal-header idlm-dialog__header"><div><h2 class="idlm-dialog__title" id="idlm-kanban-quote-title">Move lead to Quote Shared</h2><p class="idlm-dialog__description" id="idlm-kanban-quote-description">Record the shared quote value and optional quote file before moving this card.</p></div><button type="button" class="idlm-dialog__close" id="idlm-kanban-quote-close" data-idlm-dialog-close aria-label="Close Quote Shared dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header>
                <div class="idlm-dialog__body">
                    <div class="idlm-form-group"><label for="idlm-kanban-quote-input">Quote value (<?php echo esc_html($idlm_currency_symbol); ?>) <span class="idlm-required-indicator" aria-hidden="true">*</span></label><input type="number" step="0.01" min="0.01" inputmode="decimal" id="idlm-kanban-quote-input" class="idlm-input" required aria-errormessage="idlm-kanban-quote-error" placeholder="e.g. 150000"><p class="idlm-field-error" id="idlm-kanban-quote-error" role="alert" hidden></p></div>
                    <div class="idlm-form-group"><span class="idlm-form-label">Quote file</span><div class="idlm-drawer-file-row"><input type="hidden" id="idlm-kanban-quote-file" value=""><label class="screen-reader-text" for="idlm-kanban-quote-file-input">Choose quote file</label><input type="file" id="idlm-kanban-quote-file-input" class="idlm-file-input" data-input-id="idlm-kanban-quote-file" data-preview-id="idlm-kanban-quote-preview" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" hidden><button type="button" class="idlm-drawer-btn-file idlm-direct-upload-trigger">Upload quote</button><span id="idlm-kanban-quote-preview" class="idlm-drawer-file-note" role="status">No file uploaded</span></div></div>
                </div>
                <footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-kanban-quote-cancel" data-idlm-dialog-close>Cancel</button><button type="button" class="idlm-btn idlm-btn-primary" id="idlm-kanban-quote-save">Save and share quote</button></footer>
            </div>
        </div>

        <!-- Kanban Lost Dialog -->
        <div class="idlm-modal idlm-dialog" id="idlm-kanban-lost-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-kanban-lost-title" aria-describedby="idlm-kanban-lost-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-kanban-lost-reason">
            <div class="idlm-modal-content idlm-dialog__panel">
                <header class="idlm-modal-header idlm-dialog__header"><div><h2 class="idlm-dialog__title" id="idlm-kanban-lost-title">Move lead to Lost</h2><p class="idlm-dialog__description" id="idlm-kanban-lost-description">Document why this opportunity was closed without a sale.</p></div><button type="button" class="idlm-dialog__close" id="idlm-kanban-lost-close" data-idlm-dialog-close aria-label="Close Lost dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header>
                <div class="idlm-dialog__body">
                    <div class="idlm-form-group"><label for="idlm-kanban-lost-reason">Lost reason <span class="idlm-required-indicator" aria-hidden="true">*</span></label><select id="idlm-kanban-lost-reason" class="idlm-input" required aria-errormessage="idlm-kanban-lost-reason-error"><option value="">Select a reason</option><?php foreach ($idlm_lost_reasons as $idlm_lost_reason): ?><option value="<?php echo esc_attr($idlm_lost_reason); ?>"><?php echo esc_html($idlm_lost_reason); ?></option><?php endforeach; ?></select><p class="idlm-field-error" id="idlm-kanban-lost-reason-error" role="alert" hidden></p></div>
                    <div class="idlm-form-group"><label for="idlm-kanban-lost-input">Lost remark <span class="idlm-required-indicator" aria-hidden="true">*</span></label><textarea id="idlm-kanban-lost-input" class="idlm-input" rows="4" maxlength="1000" required aria-errormessage="idlm-kanban-lost-remark-error" placeholder="Add the reason the lead was lost..."></textarea><p class="idlm-field-error" id="idlm-kanban-lost-remark-error" role="alert" hidden></p></div>
                </div>
                <footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-kanban-lost-cancel" data-idlm-dialog-close>Cancel</button><button type="button" class="idlm-btn idlm-btn-primary" id="idlm-kanban-lost-save">Save and mark Lost</button></footer>
            </div>
        </div>

        <!-- Keyboard-accessible stage movement dialog -->
        <div class="idlm-modal idlm-dialog" id="idlm-kanban-move-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-kanban-move-title" aria-describedby="idlm-kanban-move-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-kanban-move-stage">
            <div class="idlm-modal-content idlm-dialog__panel">
                <header class="idlm-modal-header idlm-dialog__header">
                    <div>
                        <h2 class="idlm-dialog__title" id="idlm-kanban-move-title">Move lead to another stage</h2>
                        <p class="idlm-dialog__description" id="idlm-kanban-move-description">Choose a destination. Any required commercial or loss details will be requested next.</p>
                    </div>
                    <button type="button" class="idlm-dialog__close" id="idlm-kanban-move-close" data-idlm-dialog-close aria-label="Close Move to stage dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </header>
                <div class="idlm-dialog__body">
                    <div class="idlm-form-group">
                        <label for="idlm-kanban-move-stage">Destination stage <span class="idlm-required-indicator" aria-hidden="true">*</span></label>
                        <select id="idlm-kanban-move-stage" class="idlm-input" required aria-errormessage="idlm-kanban-move-error">
                            <option value="">Choose a stage</option>
                            <?php foreach ($idlm_stages as $idlm_stage): ?>
                                <option value="<?php echo esc_attr($idlm_stage); ?>"><?php echo esc_html($idlm_stage); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="idlm-field-error" id="idlm-kanban-move-error" role="alert" hidden></p>
                    </div>
                </div>
                <footer class="idlm-dialog__footer">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-kanban-move-cancel" data-idlm-dialog-close>Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-kanban-move-confirm">Continue</button>
                </footer>
            </div>
        </div>
