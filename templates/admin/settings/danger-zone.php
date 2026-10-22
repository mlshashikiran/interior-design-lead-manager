<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$idlm_can_manage_options = !empty($context['can_manage_options']);
?>
            <?php if ($idlm_can_manage_options): ?>
                <!-- Tab 8: Danger Zone -->
                <div id="settings-danger-zone" class="idlm-settings-tab-content <?php echo $idlm_active_tab === 'danger-zone' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-danger-zone" <?php echo $idlm_active_tab === 'danger-zone' ? '' : 'hidden'; ?>>
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
                    <div id="idlm-reset-modal" class="idlm-modal idlm-dialog" role="dialog" aria-modal="true" aria-labelledby="idlm-reset-dialog-title" aria-describedby="idlm-reset-dialog-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-reset-confirm-input">
                        <div class="idlm-modal-content idlm-dialog__panel idlm-dialog--compact">
                            <header class="idlm-modal-header idlm-dialog__header">
                                <div><h2 class="idlm-dialog__title idlm-dialog__title--danger" id="idlm-reset-dialog-title"><span class="dashicons dashicons-warning" aria-hidden="true"></span> Confirm permanent reset</h2><p class="idlm-dialog__description" id="idlm-reset-dialog-description">This action is irreversible and permanently deletes all leads and database analytics.</p></div>
                                <button type="button" class="idlm-dialog__close" id="idlm-reset-close-btn" data-idlm-dialog-close aria-label="Close permanent reset dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                            </header>
                            <div class="idlm-dialog__body">
                                <div class="idlm-form-group">
                                    <label id="idlm-reset-instruction-text" class="idlm-settings-label" for="idlm-reset-confirm-input">To confirm, enter the total number of leads currently in the database:</label>
                                    <input type="text" inputmode="numeric" id="idlm-reset-confirm-input" class="idlm-input idlm-control-full" placeholder="Enter total lead count" aria-describedby="idlm-reset-dialog-description">
                                </div>
                            </div>
                            <footer class="idlm-dialog__footer">
                                <button type="button" id="idlm-reset-cancel-btn" class="idlm-btn idlm-btn-secondary" data-idlm-dialog-close>Cancel</button>
                                <button type="button" id="idlm-reset-confirm-btn" class="idlm-btn idlm-btn-danger" disabled>Confirm reset</button>
                            </footer>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
