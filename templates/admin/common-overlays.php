<?php
if (!defined('ABSPATH')) {
    exit;
}

$currency_code = isset($context['currency_code']) ? (string) $context['currency_code'] : 'INR';
$stages = isset($context['stages']) && is_array($context['stages']) ? $context['stages'] : array();
$lost_reasons = isset($context['lost_reasons']) && is_array($context['lost_reasons']) ? $context['lost_reasons'] : array();
$reps = isset($context['reps']) && is_array($context['reps']) ? $context['reps'] : array();
$can_assign_leads = !empty($context['can_assign_leads']);
$can_manage_options = !empty($context['can_manage_options']);
?>
        <!-- Sliding Drawer Panel (Right side slideout) -->
        <div class="idlm-drawer" id="idlm-lead-drawer">
            <div class="idlm-drawer-content-wrapper" id="idlm-drawer-content-wrapper-inner">
                <!-- Header, Tabs, and Body loaded dynamically via AJAX to match mockup -->
                <div style="text-align:center; padding:50px;"><span class="spinner is-active" style="float:none;"></span> Loading Lead details...</div>
            </div>
        </div>

        <!-- Callback Scheduler Modal -->
        <div class="idlm-modal" id="idlm-callback-modal">
            <div class="idlm-modal-content" style="max-width: 400px; padding: 20px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 16px;">Schedule Callback</h2>
                    <span class="idlm-close" id="idlm-callback-modal-close">&times;</span>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Select Date & Time</label>
                    <input type="datetime-local" id="idlm-callback-datetime-input" class="idlm-input" style="width: 100%;">
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-callback-modal-cancel" style="padding: 6px 12px;">Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-callback-modal-save" style="padding: 6px 12px;">Schedule</button>
                </div>
            </div>
        </div>

        <!-- Deal Value Input Modal -->
        <div class="idlm-modal" id="idlm-deal-value-modal">
            <div class="idlm-modal-content" style="max-width: 400px; padding: 20px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 16px;">Enter Deal Value</h2>
                    <span class="idlm-close" id="idlm-deal-value-modal-close">&times;</span>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Deal Value (<?php echo esc_html($currency_code); ?>)*</label>
                    <input type="number" step="0.01" min="0" id="idlm-deal-value-input" class="idlm-input" style="width: 100%;" placeholder="e.g. 150000">
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label for="idlm-discount-percentage-input" style="font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Discount % *</label>
                    <input type="number" step="0.01" min="0" max="100" id="idlm-discount-percentage-input" class="idlm-input" required style="width: 100%;" placeholder="e.g. 10">
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label for="idlm-won-property-location-input" style="font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Property Location / Pincode</label>
                    <input type="text" id="idlm-won-property-location-input" class="idlm-input" maxlength="255" style="width: 100%;" placeholder="e.g. North Bangalore / 560001">
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-deal-value-modal-cancel" style="padding: 6px 12px;">Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-deal-value-modal-save" style="padding: 6px 12px;">Save Value</button>
                </div>
            </div>
        </div>

        <!-- Quote Value Input Modal -->
        <div class="idlm-modal" id="idlm-quote-value-modal">
            <div class="idlm-modal-content" style="max-width: 400px; padding: 20px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 16px;">Enter Quote Value</h2>
                    <span class="idlm-close" id="idlm-quote-value-modal-close">&times;</span>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label for="idlm-quote-value-input" style="font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Quote Value (<?php echo esc_html($currency_code); ?>) *</label>
                    <input type="number" step="0.01" min="0.01" id="idlm-quote-value-input" class="idlm-input" required style="width: 100%;" placeholder="e.g. 150000">
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label style="font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Upload Quote</label>
                    <div class="idlm-drawer-file-row">
                        <input type="hidden" id="idlm-stage-quote-file" value="">
                        <input type="file" class="idlm-file-input" data-input-id="idlm-stage-quote-file" data-preview-id="idlm-stage-quote-preview" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;">
                        <button type="button" class="idlm-drawer-btn-file idlm-direct-upload-trigger">Upload Quote</button>
                        <span id="idlm-stage-quote-preview" class="idlm-drawer-file-note">No file uploaded</span>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-quote-value-modal-cancel" style="padding: 6px 12px;">Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-quote-value-modal-save" style="padding: 6px 12px;">Save &amp; Share Quote</button>
                </div>
            </div>
        </div>

        <!-- Lost Lead Remark Modal -->
        <div class="idlm-modal" id="idlm-lost-remark-modal">
            <div class="idlm-modal-content" style="max-width: 450px; padding: 20px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 16px;">Why was this lead lost?</h2>
                    <span class="idlm-close" id="idlm-lost-remark-modal-close">&times;</span>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label for="idlm-lost-reason-select" style="font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Lost Reason *</label>
                    <select id="idlm-lost-reason-select" class="idlm-input" required style="width: 100%;">
                        <option value="">Select a reason</option>
                        <?php foreach ($lost_reasons as $lost_reason): ?>
                            <option value="<?php echo esc_attr($lost_reason); ?>"><?php echo esc_html($lost_reason); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="idlm-form-group" style="margin-bottom: 15px;">
                    <label for="idlm-lost-remark-input" style="font-weight: 500; font-size: 12px; margin-bottom: 6px; color: #64748b;">Lost Remark *</label>
                    <textarea id="idlm-lost-remark-input" class="idlm-input" rows="4" maxlength="1000" required style="width: 100%; resize: vertical;" placeholder="Add the reason the lead was lost..."></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-lost-remark-modal-cancel" style="padding: 6px 12px;">Cancel</button>
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-lost-remark-modal-save" style="padding: 6px 12px;">Save &amp; Mark Lost</button>
                </div>
            </div>
        </div>

        <!-- Unsaved Changes Confirmation Modal -->
        <div class="idlm-modal" id="idlm-unsaved-changes-modal" style="z-index: 110000;">
            <div class="idlm-modal-content" style="max-width: 450px; padding: 25px;">
                <div class="idlm-modal-header" style="margin-bottom: 15px; padding-bottom: 8px;">
                    <h2 style="font-size: 18px; font-weight: 700; color: #111827;">⚠️ Unsaved Changes</h2>
                    <span class="idlm-close" id="idlm-unsaved-modal-close">&times;</span>
                </div>
                <p style="font-size: 13px; line-height: 1.5; color: #63728a; margin-bottom: 10px;">
                    You have made edits to the lead details. Would you like to save your changes before closing, or discard them?
                </p>
                <div id="idlm-unsaved-fields-list" style="margin-bottom: 20px; font-weight: 600; color: #111827; font-size: 12px; line-height: 1.4;"></div>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button type="button" class="idlm-btn idlm-btn-primary" id="idlm-unsaved-save-exit" style="width: 100%; justify-content: center; min-height: 40px;">Save & Exit</button>
                    <button type="button" class="idlm-btn" id="idlm-unsaved-discard-exit" style="width: 100%; justify-content: center; min-height: 40px; background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1;">Discard & Exit</button>
                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-unsaved-cancel" style="width: 100%; justify-content: center; min-height: 40px;">Keep Editing</button>
                </div>
            </div>
        </div>

        <!-- Global Task Sidebar Panel -->
        <div class="idlm-global-sidebar" id="idlm-global-tasks-sidebar">
            <div class="idlm-global-sidebar-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--idlm-border); padding: 15px 20px; background: var(--idlm-surface-soft);">
                <h2 style="font-size: 16px; font-weight: 700; margin: 0; color: var(--idlm-text-main);">My Active Tasks</h2>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <button class="idlm-global-sidebar-filter-btn active" id="idlm-global-tasks-filter-pending" onclick="filterGlobalTasks('pending')" style="padding: 2px 8px; font-size: 11px; border: 1px solid var(--idlm-border); border-radius: 4px; background: #fff; cursor: pointer;">Pending</button>
                    <button class="idlm-global-sidebar-filter-btn" id="idlm-global-tasks-filter-completed" onclick="filterGlobalTasks('completed')" style="padding: 2px 8px; font-size: 11px; border: 1px solid var(--idlm-border); border-radius: 4px; background: #fff; cursor: pointer;">Completed</button>
                    <span class="idlm-close" id="idlm-global-tasks-sidebar-close" style="font-size: 20px; cursor: pointer; margin-left: 5px;">&times;</span>
                </div>
            </div>
            <div class="idlm-global-sidebar-body" id="idlm-global-tasks-sidebar-body" style="padding: 15px; overflow-y: auto; height: calc(100% - 60px);">
                <!-- Loaded dynamically via AJAX -->
                <div style="text-align: center; color: var(--idlm-text-muted); padding: 40px 0;">
                    <span class="spinner is-active" style="float:none;"></span> Loading tasks...
                </div>
            </div>
        </div>

        <!-- Floating Action Button for Tasks -->
        <button type="button" class="idlm-floating-tasks-btn" id="idlm-floating-tasks-btn" title="View Active Tasks" onclick="toggleGlobalTasksSidebar()">
            <span class="dashicons dashicons-editor-ul" style="font-size: 20px; width: 20px; height: 20px; line-height: 20px; display: block; margin: auto;"></span>
            <span class="idlm-floating-tasks-badge" id="idlm-floating-tasks-badge" style="display: none;">0</span>
        </button>


