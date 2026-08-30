<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$can_manage_options = !empty($context['can_manage_options']);
?>
            <?php if ($can_manage_options): ?>
                <!-- Tab 8: Danger Zone -->
                <div id="settings-danger-zone" class="idlm-settings-tab-content <?php echo $active_tab === 'danger-zone' ? 'active' : ''; ?>" style="display: <?php echo $active_tab === 'danger-zone' ? 'block' : 'none'; ?>;">
                    <!-- Danger Zone: Reset CRM Data -->
                    <article class="idlm-card idlm-card-danger">
                        <h3>Danger Zone: Reset CRM Leads &amp; Analytics</h3>
                        <p class="idlm-danger-copy">
                            This operation will permanently delete all leads, activity logs, Conversions API logs, and marketing cost spent records from the database. All performance analytics and conversion pipelines will start from scratch. Your plugin configuration settings (API keys, stages, routing rules) will remain untouched.
                        </p>
                        <div class="idlm-table-actions">
                            <button type="button" id="idlm-reset-data-btn" class="idlm-btn idlm-btn-danger">
                                Delete All Leads &amp; Reset Analytics
                            </button>
                        </div>
                    </article>

                    <!-- 2-Step Verification Modal -->
                    <div id="idlm-reset-modal" class="idlm-modal">
                        <div class="idlm-modal-content" style="max-width: 450px;">
                            <div class="idlm-modal-header">
                                <h2 style="color: var(--idlm-danger) !important; display: flex; align-items: center; gap: 8px;">
                                    ⚠️ Confirm Permanent Reset
                                </h2>
                                <span class="idlm-close" id="idlm-reset-close-btn">&times;</span>
                            </div>
                            <p class="idlm-danger-copy" style="margin-top: 0 !important; margin-bottom: 15px !important;">
                                This action is <strong>irreversible</strong> and will permanently wipe out all leads and database analytics.
                            </p>
                            <div class="idlm-form-group" style="margin-bottom: 20px;">
                                <label id="idlm-reset-instruction-text" style="font-weight: 600; color: var(--idlm-text); margin-bottom: 8px; display: block;">
                                    To confirm, please enter the total number of leads currently in the database to proceed:
                                </label>
                                <input type="text" id="idlm-reset-confirm-input" class="idlm-input" placeholder="Enter total lead count..." style="width: 100%;">
                            </div>
                            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                                <button type="button" id="idlm-reset-cancel-btn" class="idlm-btn idlm-btn-secondary">Cancel</button>
                                <button type="button" id="idlm-reset-confirm-btn" class="idlm-btn idlm-btn-danger" disabled>Confirm Reset</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
