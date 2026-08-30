<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$lead_target = isset($context['lead_target']) ? $context['lead_target'] : null;
$conv_target = isset($context['conv_target']) ? $context['conv_target'] : null;
$currency_code = isset($context['currency_code']) ? $context['currency_code'] : null;
$reps_see_all = isset($context['reps_see_all']) ? $context['reps_see_all'] : null;
$presales_targets = isset($context['presales_targets']) ? $context['presales_targets'] : null;
$stages = isset($context['stages']) ? $context['stages'] : null;
$kanban_stage_totals = isset($context['kanban_stage_totals']) ? $context['kanban_stage_totals'] : null;
$call_statuses = isset($context['call_statuses']) ? $context['call_statuses'] : null;
$lost_reasons = isset($context['lost_reasons']) ? $context['lost_reasons'] : null;
$studios = isset($context['studios']) ? $context['studios'] : null;
$num_rooms_options = isset($context['num_rooms_options']) ? $context['num_rooms_options'] : null;
?>
                    <!-- Tab 1: General Settings -->
                    <section id="settings-general" class="idlm-settings-tab-content <?php echo $active_tab === 'general' ? 'active' : ''; ?>" style="display: <?php echo $active_tab === 'general' ? 'block' : 'none'; ?>;">
                        <div class="idlm-grid-charts">
                            <article class="idlm-card">
                                <h3>Firm Metrics & Targets</h3>
                                <div class="idlm-form-grid three" style="grid-template-columns: repeat(3, 1fr);">
                                    <div class="idlm-form-group">
                                        <label>Monthly Lead Volume Target</label>
                                        <input type="number" name="lead_target" class="idlm-input" value="<?php echo esc_attr($lead_target); ?>">
                                    </div>
                                    <div class="idlm-form-group">
                                        <label>Monthly Won Deals Target</label>
                                        <input type="number" name="conversion_target" class="idlm-input" value="<?php echo esc_attr($conv_target); ?>">
                                    </div>
                                    <div class="idlm-form-group">
                                        <label>Preferred Currency Code</label>
                                        <input type="text" name="currency_code" class="idlm-input" value="<?php echo esc_attr($currency_code); ?>" placeholder="e.g. INR" maxlength="3" style="text-transform: uppercase;">
                                    </div>
                                    <div class="idlm-form-group full">
                                        <div class="idlm-checkbox-row">
                                            <input type="checkbox" name="reps_see_all" value="1" <?php checked($reps_see_all, '1'); ?> style="width: 17px; height: 17px; margin: 0; cursor: pointer;">
                                            <span>Reps see all leads (Shared Inbox Model)</span>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <article class="idlm-card">
                                <h3>Custom Pipelines Stages & Statuses</h3>
                                <div class="idlm-form-grid two">
                                    <div class="idlm-form-group full">
                                        <label>Configure Sales Stages (Comma separated)</label>
                                        <input type="text" name="custom_stages" class="idlm-input" value="<?php echo esc_attr(implode(',', $stages)); ?>">
                                        <div class="idlm-chip-grid">
                                            <?php foreach ($stages as $stage): ?>
                                                <span class="idlm-chip"><?php echo esc_html($stage); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="idlm-form-group full">
                                        <label>Configure Call Status Options (Comma separated)</label>
                                        <input type="text" name="custom_call_statuses" class="idlm-input" value="<?php echo esc_attr(implode(',', $call_statuses)); ?>">
                                        <div class="idlm-chip-grid">
                                            <?php foreach ($call_statuses as $cs): ?>
                                                <span class="idlm-chip"><?php echo esc_html($cs); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="idlm-form-group full">
                                        <label>Configure Lost Reason Options (Comma separated)</label>
                                        <input type="text" name="custom_lost_reasons" class="idlm-input" value="<?php echo esc_attr(implode(',', $lost_reasons)); ?>">
                                        <p class="description">These reasons appear in the required dropdown when a lead is moved to Lost.</p>
                                        <div class="idlm-chip-grid">
                                            <?php foreach ($lost_reasons as $lost_reason): ?>
                                                <span class="idlm-chip"><?php echo esc_html($lost_reason); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="idlm-form-group">
                                        <label>Configure Preferred Studio Locations (Comma separated)</label>
                                        <input type="text" name="custom_studios" class="idlm-input" value="<?php echo esc_attr(implode(',', $studios)); ?>">
                                        <div class="idlm-chip-grid">
                                            <?php foreach ($studios as $studio): ?>
                                                <span class="idlm-chip"><?php echo esc_html($studio); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="idlm-form-group">
                                        <label>Configure Number of Rooms Options (Comma separated)</label>
                                        <input type="text" name="custom_num_rooms" class="idlm-input" value="<?php echo esc_attr(implode(',', $num_rooms_options)); ?>">
                                        <div class="idlm-chip-grid">
                                            <?php foreach ($num_rooms_options as $opt): ?>
                                                <span class="idlm-chip"><?php echo esc_html($opt); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </article>

                            <article class="idlm-card">
                                <h3>Kanban Column Value Consolidation</h3>
                                <p class="idlm-card-desc">Choose which monetary field is totaled for each stage. Select Hidden to show only the lead count.</p>
                                <div class="idlm-table-wrapper" style="margin-top: 14px;">
                                    <table class="idlm-table">
                                        <thead>
                                            <tr>
                                                <th>Pipeline Stage</th>
                                                <th>Header Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stages as $stage): ?>
                                                <?php $total_source = isset($kanban_stage_totals[$stage]) ? $kanban_stage_totals[$stage] : 'none'; ?>
                                                <tr>
                                                    <td><strong><?php echo esc_html($stage); ?></strong></td>
                                                    <td>
                                                        <select name="kanban_stage_totals[<?php echo esc_attr(rawurlencode($stage)); ?>]" class="idlm-select" style="width: 100%;">
                                                            <option value="none" <?php selected($total_source, 'none'); ?>>Hidden</option>
                                                            <option value="quote_value" <?php selected($total_source, 'quote_value'); ?>>Quote Value</option>
                                                            <option value="deal_value" <?php selected($total_source, 'deal_value'); ?>>Deal Value</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </article>
                        </div>

                        <article class="idlm-card idlm-card-muted" style="margin-top: 22px;">
                            <h3>Frontend Shortcode Integrations</h3>
                            <p class="idlm-card-desc">Display the Leads Database Workspace and Analytics Dashboard on any frontend page or post. Access is strictly restricted to logged-in users with authorized roles (e.g. Sales Reps, Editors, Administrators) to preserve CRM security.</p>
                            <div class="idlm-shortcode-grid">
                                <div class="idlm-shortcode-card">
                                    <div class="idlm-shortcode-head">
                                        <h4>Complete CRM Workspace</h4>
                                        <button class="idlm-btn idlm-btn-secondary" type="button" onclick="idlmCopyToClipboard(this, '[idlm_workspace]');">Copy</button>
                                    </div>
                                    <code class="idlm-code">[idlm_workspace]</code>
                                    <p>Recommended. Opens dashboard, leads, pipeline, quotations and authorized administration tools in one frontend workspace.</p>
                                </div>
                                <div class="idlm-shortcode-card">
                                    <div class="idlm-shortcode-head">
                                        <h4>Leads Database Workspace</h4>
                                        <button class="idlm-btn idlm-btn-secondary" type="button" onclick="idlmCopyToClipboard(this, '[idlm_leads_list]');">Copy</button>
                                    </div>
                                    <code class="idlm-code">[idlm_leads_list]</code>
                                    <p>Embeds the main CRM workspace table with quick-edit, search, filters, and detailed drawers.</p>
                                </div>
                                <div class="idlm-shortcode-card">
                                    <div class="idlm-shortcode-head">
                                        <h4>Analytics Dashboard Widgets</h4>
                                        <button class="idlm-btn idlm-btn-secondary" type="button" onclick="idlmCopyToClipboard(this, '[idlm_dashboard]');">Copy</button>
                                    </div>
                                    <code class="idlm-code">[idlm_dashboard]</code>
                                    <p>Embeds marketing performance charts, pipeline stage funnel, and sales metrics.</p>
                                </div>
                                <div class="idlm-shortcode-card">
                                    <div class="idlm-shortcode-head">
                                        <h4>Kanban Board Workspace</h4>
                                        <button class="idlm-btn idlm-btn-secondary" type="button" onclick="idlmCopyToClipboard(this, '[idlm_kanban]');">Copy</button>
                                    </div>
                                    <code class="idlm-code">[idlm_kanban]</code>
                                    <p>Embeds the visual Kanban board with interactive drag-and-drop, stages routing, search, and filters.</p>
                                </div>
                            </div>
                        </article>
                    </section> <!-- End Tab 1: General Settings -->


