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
$ui_labels = isset($context['ui_labels']) ? $context['ui_labels'] : array();
$value_labels = isset($context['value_labels']) ? $context['value_labels'] : array();
?>
                    <!-- Tab 1: General Settings -->
                    <section id="settings-general" class="idlm-settings-tab-content <?php echo $active_tab === 'general' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-general" <?php echo $active_tab === 'general' ? '' : 'hidden'; ?>>
                        <div class="idlm-grid-charts">
                            <article class="idlm-card idlm-settings-general-metrics">
                                <h3>Firm Metrics & Targets</h3>
                                <div class="idlm-form-grid three" data-idlm-ui="grid-three">
                                    <div class="idlm-form-group">
                                        <label for="idlm-lead-target">Monthly lead volume target</label>
                                        <input type="number" id="idlm-lead-target" name="lead_target" class="idlm-input" min="0" inputmode="numeric" value="<?php echo esc_attr($lead_target); ?>">
                                    </div>
                                    <div class="idlm-form-group">
                                        <label for="idlm-conversion-target">Monthly Won deals target</label>
                                        <input type="number" id="idlm-conversion-target" name="conversion_target" class="idlm-input" min="0" inputmode="numeric" value="<?php echo esc_attr($conv_target); ?>">
                                    </div>
                                    <div class="idlm-form-group">
                                        <label for="idlm-currency-code">Preferred currency code</label>
                                        <input type="text" id="idlm-currency-code" name="currency_code" class="idlm-input" value="<?php echo esc_attr($currency_code); ?>" placeholder="e.g. INR" maxlength="3" autocapitalize="characters" data-idlm-ui="uppercase">
                                    </div>
                                    <div class="idlm-form-group full">
                                        <label class="idlm-checkbox-row">
                                            <input type="checkbox" name="reps_see_all" value="1" <?php checked($reps_see_all, '1'); ?> data-idlm-ui="checkbox">
                                            <span>Reps see all leads (Shared Inbox Model)</span>
                                        </label>
                                    </div>
                                </div>
                            </article>

                            <article class="idlm-card">
                                <h3>Pipeline stages</h3>
                                <p class="idlm-card-desc">Set the workflow order and each Kanban column’s header total together. The first stage appears first throughout the CRM.</p>
                                <?php IDLM_View::display('admin/settings/token-editor.php', array(
                                    'editor_id' => 'idlm-custom-stages',
                                    'field_name' => 'custom_stages',
                                    'label' => 'Sales stages',
                                    'help' => 'Move stages to set the workflow order. Choose Hidden to show only a lead count in the Kanban header.',
                                    'values' => $stages,
                                    'stage_totals' => $kanban_stage_totals,
                                    'required' => true,
                                    'pipeline' => true,
                                    'aliases' => isset($value_labels['custom_stages']) ? $value_labels['custom_stages'] : array(),
                                )); ?>
                            </article>

                            <article class="idlm-card idlm-lead-fields-card">
                                <h3>Lead fields and options</h3>
                                <p class="idlm-card-desc">Keep reusable choices organized by the part of the CRM that uses them. Open a group to add, remove, or reorder its options.</p>
                                <div class="idlm-form-grid two idlm-ui-label-editor">
                                    <?php foreach (array('call_status' => 'Call Status', 'lost_reason' => 'Lost Reason', 'preferred_studio' => 'Preferred Studio', 'num_rooms' => 'Rooms') as $key => $default): ?>
                                        <div class="idlm-form-group"><label for="idlm-ui-label-<?php echo esc_attr($key); ?>"><?php echo esc_html($default); ?> display label</label><input class="idlm-input" id="idlm-ui-label-<?php echo esc_attr($key); ?>" name="ui_labels[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr(isset($ui_labels[$key]) ? $ui_labels[$key] : $default); ?>"></div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="idlm-settings-disclosures">
                                    <details class="idlm-settings-disclosure" open>
                                        <summary>Sales activity <span class="idlm-settings-disclosure__count"><?php echo count($call_statuses); ?> options</span></summary>
                                        <div class="idlm-settings-disclosure__content">
                                            <?php IDLM_View::display('admin/settings/token-editor.php', array(
                                                'editor_id' => 'idlm-custom-call-statuses', 'field_name' => 'custom_call_statuses', 'label' => 'Call status options',
                                                'help' => 'These options appear in outreach activity and follow-up reminder rules.', 'values' => $call_statuses, 'required' => true, 'aliases' => isset($value_labels['custom_call_statuses']) ? $value_labels['custom_call_statuses'] : array(),
                                            )); ?>
                                        </div>
                                    </details>
                                    <details class="idlm-settings-disclosure">
                                        <summary>Outcome tracking <span class="idlm-settings-disclosure__count"><?php echo count($lost_reasons); ?> reasons</span></summary>
                                        <div class="idlm-settings-disclosure__content">
                                            <?php IDLM_View::display('admin/settings/token-editor.php', array(
                                                'editor_id' => 'idlm-custom-lost-reasons', 'field_name' => 'custom_lost_reasons', 'label' => 'Lost reasons',
                                                'help' => 'These reasons appear in the required dropdown when a lead is moved to Lost.', 'values' => $lost_reasons, 'required' => true, 'aliases' => isset($value_labels['custom_lost_reasons']) ? $value_labels['custom_lost_reasons'] : array(),
                                            )); ?>
                                        </div>
                                    </details>
                                    <details class="idlm-settings-disclosure">
                                        <summary>Assignment and routing <span class="idlm-settings-disclosure__count"><?php echo count($studios); ?> studios</span></summary>
                                        <div class="idlm-settings-disclosure__content">
                                            <?php IDLM_View::display('admin/settings/token-editor.php', array(
                                                'editor_id' => 'idlm-custom-studios', 'field_name' => 'custom_studios', 'label' => 'Preferred studio locations',
                                                'help' => 'Studio choices used for lead preference and studio-based routing.', 'values' => $studios, 'required' => true, 'aliases' => isset($value_labels['custom_studios']) ? $value_labels['custom_studios'] : array(),
                                            )); ?>
                                        </div>
                                    </details>
                                    <details class="idlm-settings-disclosure">
                                        <summary>Project requirements <span class="idlm-settings-disclosure__count"><?php echo count($num_rooms_options); ?> room options</span></summary>
                                        <div class="idlm-settings-disclosure__content">
                                            <?php IDLM_View::display('admin/settings/token-editor.php', array(
                                                'editor_id' => 'idlm-custom-num-rooms', 'field_name' => 'custom_num_rooms', 'label' => 'Room options',
                                                'help' => 'Room choices shown when capturing an interior-design requirement.', 'values' => $num_rooms_options, 'required' => true, 'aliases' => isset($value_labels['custom_num_rooms']) ? $value_labels['custom_num_rooms'] : array(),
                                            )); ?>
                                        </div>
                                    </details>
                                </div>
                            </article>
                        </div>

                        <article class="idlm-card idlm-card-muted" data-idlm-ui="mt-6">
                            <h3>Frontend Shortcode Integrations</h3>
                            <p class="idlm-card-desc">Display the Leads Database Workspace and Analytics Dashboard on any frontend page or post. Access is strictly restricted to logged-in users with authorized roles (e.g. Sales Reps, Editors, Administrators) to preserve CRM security.</p>
                            <div class="idlm-shortcode-grid">
                                <div class="idlm-shortcode-card">
                                    <div class="idlm-shortcode-head">
                                        <h4>Complete CRM Workspace</h4>
                                        <button class="idlm-btn idlm-btn-secondary" type="button" onclick="idlmCopyToClipboard(this, '[idlm_workspace]');">Copy</button>
                                    </div>
                                    <code class="idlm-code"><span aria-hidden="true">[</span>idlm_workspace<span aria-hidden="true">]</span></code>
                                    <p>Recommended. Opens dashboard, leads, pipeline, quotations and authorized administration tools in one frontend workspace.</p>
                                </div>
                                <div class="idlm-shortcode-card">
                                    <div class="idlm-shortcode-head">
                                        <h4>Leads Database Workspace</h4>
                                        <button class="idlm-btn idlm-btn-secondary" type="button" onclick="idlmCopyToClipboard(this, '[idlm_leads_list]');">Copy</button>
                                    </div>
                                    <code class="idlm-code"><span aria-hidden="true">[</span>idlm_leads_list<span aria-hidden="true">]</span></code>
                                    <p>Embeds the main CRM workspace table with quick-edit, search, filters, and detailed drawers.</p>
                                </div>
                                <div class="idlm-shortcode-card">
                                    <div class="idlm-shortcode-head">
                                        <h4>Analytics Dashboard Widgets</h4>
                                        <button class="idlm-btn idlm-btn-secondary" type="button" onclick="idlmCopyToClipboard(this, '[idlm_dashboard]');">Copy</button>
                                    </div>
                                    <code class="idlm-code"><span aria-hidden="true">[</span>idlm_dashboard<span aria-hidden="true">]</span></code>
                                    <p>Embeds marketing performance charts, pipeline stage funnel, and sales metrics.</p>
                                </div>
                                <div class="idlm-shortcode-card">
                                    <div class="idlm-shortcode-head">
                                        <h4>Kanban Board Workspace</h4>
                                        <button class="idlm-btn idlm-btn-secondary" type="button" onclick="idlmCopyToClipboard(this, '[idlm_kanban]');">Copy</button>
                                    </div>
                                    <code class="idlm-code"><span aria-hidden="true">[</span>idlm_kanban<span aria-hidden="true">]</span></code>
                                    <p>Embeds the visual Kanban board with interactive drag-and-drop, stages routing, search, and filters.</p>
                                </div>
                            </div>
                        </article>
                    </section> <!-- End Tab 1: General Settings -->
