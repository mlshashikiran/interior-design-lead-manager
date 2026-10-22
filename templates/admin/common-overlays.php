<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_currency_code = isset($context['currency_code']) ? (string) $context['currency_code'] : 'INR';
$idlm_stages = isset($context['stages']) && is_array($context['stages']) ? $context['stages'] : array();
$idlm_lost_reasons = isset($context['lost_reasons']) && is_array($context['lost_reasons']) ? $context['lost_reasons'] : array();
$idlm_reps = isset($context['reps']) && is_array($context['reps']) ? $context['reps'] : array();
$idlm_can_assign_leads = !empty($context['can_assign_leads']);
$idlm_can_manage_options = !empty($context['can_manage_options']);
?>
        <!-- Sliding Drawer Panel (Right side slideout) -->
        <div class="idlm-drawer" id="idlm-lead-drawer" role="dialog" aria-modal="true" aria-labelledby="lead-title" aria-hidden="true" tabindex="-1">
            <button type="button" class="idlm-drawer-lead-nav idlm-drawer-lead-nav-prev" id="idlm-drawer-prev-lead" aria-label="Open previous lead" title="Previous lead (Left arrow)" disabled>
                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
            </button>
            <div class="idlm-drawer-content-wrapper" id="idlm-drawer-content-wrapper-inner">
                <!-- Header, Tabs, and Body loaded dynamically via AJAX to match mockup -->
                <div class="idlm-drawer-load-state" role="status"><span class="spinner is-active idlm-spinner-inline"></span> Loading Lead details...</div>
            </div>
            <button type="button" class="idlm-drawer-lead-nav idlm-drawer-lead-nav-next" id="idlm-drawer-next-lead" aria-label="Open next lead" title="Next lead (Right arrow)" disabled>
                <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
            </button>
            <div class="idlm-drawer-lead-position" id="idlm-drawer-lead-position" aria-live="polite"></div>
        </div>

        <!-- Callback Scheduler Dialog -->
        <div class="idlm-modal idlm-dialog" id="idlm-callback-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-callback-modal-title" aria-describedby="idlm-callback-modal-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-callback-datetime-input">
            <div class="idlm-modal-content idlm-dialog__panel">
                <header class="idlm-modal-header idlm-dialog__header">
                    <div><h2 class="idlm-dialog__title" id="idlm-callback-modal-title">Schedule callback</h2><p class="idlm-dialog__description" id="idlm-callback-modal-description">Choose when the customer should be contacted again.</p></div>
                    <button type="button" class="idlm-dialog__close" id="idlm-callback-modal-close" data-idlm-dialog-close aria-label="Close callback dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </header>
                <div class="idlm-dialog__body">
                    <div class="idlm-form-group">
                        <label for="idlm-callback-datetime-input">Callback date and time <span class="idlm-required-indicator" aria-hidden="true">*</span></label>
                        <input type="datetime-local" id="idlm-callback-datetime-input" class="idlm-input" required aria-errormessage="idlm-callback-datetime-error">
                        <p class="idlm-field-error" id="idlm-callback-datetime-error" role="alert" hidden></p>
                    </div>
                </div>
                <footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-callback-modal-cancel" data-idlm-dialog-close>Cancel</button><button type="button" class="idlm-btn idlm-btn-primary" id="idlm-callback-modal-save">Schedule</button></footer>
            </div>
        </div>

        <!-- Won Deal Value Dialog -->
        <div class="idlm-modal idlm-dialog" id="idlm-deal-value-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-deal-value-modal-title" aria-describedby="idlm-deal-value-modal-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-deal-value-input">
            <div class="idlm-modal-content idlm-dialog__panel">
                <header class="idlm-modal-header idlm-dialog__header">
                    <div><h2 class="idlm-dialog__title" id="idlm-deal-value-modal-title">Move lead to Won</h2><p class="idlm-dialog__description" id="idlm-deal-value-modal-description">Record the final commercial value before closing this lead.</p></div>
                    <button type="button" class="idlm-dialog__close" id="idlm-deal-value-modal-close" data-idlm-dialog-close aria-label="Close Won dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </header>
                <div class="idlm-dialog__body">
                    <div class="idlm-form-group"><label for="idlm-deal-value-input">Deal value (<?php echo esc_html($idlm_currency_code); ?>) <span class="idlm-required-indicator" aria-hidden="true">*</span></label><input type="number" step="0.01" min="0.01" inputmode="decimal" id="idlm-deal-value-input" class="idlm-input" required aria-errormessage="idlm-deal-value-error" placeholder="e.g. 150000"><p class="idlm-field-error" id="idlm-deal-value-error" role="alert" hidden></p></div>
                    <div class="idlm-form-group"><label for="idlm-discount-percentage-input">Discount percentage <span class="idlm-required-indicator" aria-hidden="true">*</span></label><input type="number" step="0.01" min="0" max="100" inputmode="decimal" id="idlm-discount-percentage-input" class="idlm-input" required aria-errormessage="idlm-discount-percentage-error" placeholder="e.g. 10"><p class="idlm-field-error" id="idlm-discount-percentage-error" role="alert" hidden></p></div>
                    <div class="idlm-form-group"><label for="idlm-won-property-location-input">Property location or pincode</label><input type="text" id="idlm-won-property-location-input" class="idlm-input" maxlength="255" autocomplete="street-address" placeholder="e.g. North Bangalore / 560001"></div>
                </div>
                <footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-deal-value-modal-cancel" data-idlm-dialog-close>Cancel</button><button type="button" class="idlm-btn idlm-btn-primary" id="idlm-deal-value-modal-save">Save value</button></footer>
            </div>
        </div>

        <!-- Quote Shared Dialog -->
        <div class="idlm-modal idlm-dialog" id="idlm-quote-value-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-quote-value-modal-title" aria-describedby="idlm-quote-value-modal-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-quote-value-input">
            <div class="idlm-modal-content idlm-dialog__panel">
                <header class="idlm-modal-header idlm-dialog__header">
                    <div><h2 class="idlm-dialog__title" id="idlm-quote-value-modal-title">Move lead to Quote Shared</h2><p class="idlm-dialog__description" id="idlm-quote-value-modal-description">Record the shared quote value and optional quote file.</p></div>
                    <button type="button" class="idlm-dialog__close" id="idlm-quote-value-modal-close" data-idlm-dialog-close aria-label="Close Quote Shared dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </header>
                <div class="idlm-dialog__body">
                    <div class="idlm-form-group"><label for="idlm-quote-value-input">Quote value (<?php echo esc_html($idlm_currency_code); ?>) <span class="idlm-required-indicator" aria-hidden="true">*</span></label><input type="number" step="0.01" min="0.01" inputmode="decimal" id="idlm-quote-value-input" class="idlm-input" required aria-errormessage="idlm-quote-value-error" placeholder="e.g. 150000"><p class="idlm-field-error" id="idlm-quote-value-error" role="alert" hidden></p></div>
                    <div class="idlm-form-group"><span class="idlm-form-label">Quote file</span><div class="idlm-drawer-file-row"><input type="hidden" id="idlm-stage-quote-file" value=""><label class="screen-reader-text" for="idlm-stage-quote-file-input">Choose quote file</label><input type="file" id="idlm-stage-quote-file-input" class="idlm-file-input" data-input-id="idlm-stage-quote-file" data-preview-id="idlm-stage-quote-preview" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" hidden><button type="button" class="idlm-drawer-btn-file idlm-direct-upload-trigger">Upload quote</button><span id="idlm-stage-quote-preview" class="idlm-drawer-file-note" role="status">No file uploaded</span></div></div>
                </div>
                <footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-quote-value-modal-cancel" data-idlm-dialog-close>Cancel</button><button type="button" class="idlm-btn idlm-btn-primary" id="idlm-quote-value-modal-save">Save and share quote</button></footer>
            </div>
        </div>

        <!-- Lost Lead Dialog -->
        <div class="idlm-modal idlm-dialog" id="idlm-lost-remark-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-lost-remark-modal-title" aria-describedby="idlm-lost-remark-modal-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-lost-reason-select">
            <div class="idlm-modal-content idlm-dialog__panel">
                <header class="idlm-modal-header idlm-dialog__header">
                    <div><h2 class="idlm-dialog__title" id="idlm-lost-remark-modal-title">Move lead to Lost</h2><p class="idlm-dialog__description" id="idlm-lost-remark-modal-description">Document why this opportunity was closed without a sale.</p></div>
                    <button type="button" class="idlm-dialog__close" id="idlm-lost-remark-modal-close" data-idlm-dialog-close aria-label="Close Lost dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </header>
                <div class="idlm-dialog__body">
                    <div class="idlm-form-group"><label for="idlm-lost-reason-select">Lost reason <span class="idlm-required-indicator" aria-hidden="true">*</span></label><select id="idlm-lost-reason-select" class="idlm-input" required aria-errormessage="idlm-lost-reason-error"><option value="">Select a reason</option><?php foreach ($idlm_lost_reasons as $idlm_lost_reason): ?><option value="<?php echo esc_attr($idlm_lost_reason); ?>"><?php echo esc_html($idlm_lost_reason); ?></option><?php endforeach; ?></select><p class="idlm-field-error" id="idlm-lost-reason-error" role="alert" hidden></p></div>
                    <div class="idlm-form-group"><label for="idlm-lost-remark-input">Lost remark <span class="idlm-required-indicator" aria-hidden="true">*</span></label><textarea id="idlm-lost-remark-input" class="idlm-input" rows="4" maxlength="1000" required aria-errormessage="idlm-lost-remark-error" placeholder="Add the reason the lead was lost..."></textarea><p class="idlm-field-error" id="idlm-lost-remark-error" role="alert" hidden></p></div>
                </div>
                <footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-lost-remark-modal-cancel" data-idlm-dialog-close>Cancel</button><button type="button" class="idlm-btn idlm-btn-primary" id="idlm-lost-remark-modal-save">Save and mark Lost</button></footer>
            </div>
        </div>

        <!-- Unsaved Changes Dialog -->
        <div class="idlm-modal idlm-dialog idlm-dialog--stacked-actions" id="idlm-unsaved-changes-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-unsaved-modal-title" aria-describedby="idlm-unsaved-modal-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-unsaved-save-exit">
            <div class="idlm-modal-content idlm-dialog__panel">
                <header class="idlm-modal-header idlm-dialog__header">
                    <div><h2 class="idlm-dialog__title" id="idlm-unsaved-modal-title">Unsaved changes</h2><p class="idlm-dialog__description" id="idlm-unsaved-modal-description">Save your lead edits before closing, discard them, or return to editing.</p></div>
                    <button type="button" class="idlm-dialog__close" id="idlm-unsaved-modal-close" data-idlm-dialog-close aria-label="Close unsaved changes dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </header>
                <div class="idlm-dialog__body"><div id="idlm-unsaved-fields-list" role="status" aria-live="polite"></div><p class="idlm-field-error" id="idlm-unsaved-save-error" role="alert" hidden></p></div>
                <footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-unsaved-cancel" data-idlm-dialog-close>Keep editing</button><button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-unsaved-discard-exit">Discard and exit</button><button type="button" class="idlm-btn idlm-btn-primary" id="idlm-unsaved-save-exit">Save and exit</button></footer>
            </div>
        </div>

        <!-- Global Task Sidebar Panel -->
        <div class="idlm-global-sidebar" id="idlm-global-tasks-sidebar">
            <div class="idlm-global-sidebar-header">
                <h2 class="idlm-global-sidebar-title">Today’s work</h2>
                <div class="idlm-global-sidebar-actions">
                    <button type="button" class="idlm-global-sidebar-filter-btn active" id="idlm-global-tasks-filter-attention" onclick="filterGlobalTasks('attention')">Needs attention</button>
                    <button type="button" class="idlm-global-sidebar-filter-btn" id="idlm-global-tasks-filter-completed" onclick="filterGlobalTasks('completed')">Completed</button>
                    <?php if (current_user_can('manage_options')): ?>
                        <button type="button" class="idlm-global-sidebar-filter-btn" onclick="cleanupStaleTasks()">Clean up</button>
                    <?php endif; ?>
                    <button type="button" class="idlm-close" id="idlm-global-tasks-sidebar-close" aria-label="Close active tasks">&times;</button>
                </div>
            </div>
            <div class="idlm-global-sidebar-body" id="idlm-global-tasks-sidebar-body">
                <!-- Loaded dynamically via AJAX -->
                <div class="idlm-global-sidebar-load-state" role="status">
                    <span class="spinner is-active idlm-spinner-inline"></span> Loading tasks...
                </div>
            </div>
        </div>

        <!-- Floating Action Button for Tasks -->
        <button type="button" class="idlm-floating-tasks-btn" id="idlm-floating-tasks-btn" title="View today’s work" onclick="toggleGlobalTasksSidebar()">
            <span class="dashicons dashicons-editor-ul idlm-floating-tasks-icon" aria-hidden="true"></span>
            <span class="idlm-floating-tasks-label">Today’s work</span>
            <span class="idlm-floating-tasks-badge" id="idlm-floating-tasks-badge">0</span>
        </button>
