<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$assignment_mode = isset($context['assignment_mode']) ? $context['assignment_mode'] : null;
$active_reps = isset($context['active_reps']) ? $context['active_reps'] : null;
$active_pre_sales_reps = isset($context['active_pre_sales_reps']) ? $context['active_pre_sales_reps'] : null;
$reps = isset($context['reps']) ? $context['reps'] : null;
$pre_sales_reps = isset($context['pre_sales_reps']) ? $context['pre_sales_reps'] : null;
$source_maps = isset($context['source_maps']) ? $context['source_maps'] : null;
$rep_studios = isset($context['rep_studios']) ? $context['rep_studios'] : null;
$studios = isset($context['studios']) ? $context['studios'] : null;
$fallback_enabled = isset($context['fallback_enabled']) ? $context['fallback_enabled'] : null;
$fallback_hours = isset($context['fallback_hours']) ? $context['fallback_hours'] : null;
$qualified_fallback_enabled = isset($context['qualified_fallback_enabled']) ? $context['qualified_fallback_enabled'] : null;
$qualified_fallback_hours = isset($context['qualified_fallback_hours']) ? $context['qualified_fallback_hours'] : null;
$qualified_fallback_end_at = isset($context['qualified_fallback_end_at']) ? $context['qualified_fallback_end_at'] : null;
$qualified_fallback_include_existing = isset($context['qualified_fallback_include_existing']) ? $context['qualified_fallback_include_existing'] : null;
$studio_mismatch_behavior = isset($context['studio_mismatch_behavior']) ? $context['studio_mismatch_behavior'] : null;
$handoff_task_policy = isset($context['handoff_task_policy']) ? $context['handoff_task_policy'] : null;
$handoff_notifications_enabled = isset($context['handoff_notifications_enabled']) ? $context['handoff_notifications_enabled'] : null;
$fallback_last_completed = isset($context['fallback_last_completed']) ? $context['fallback_last_completed'] : null;
$fallback_last_status = isset($context['fallback_last_status']) ? $context['fallback_last_status'] : null;
$routing_exception_count = isset($context['routing_exception_count']) ? $context['routing_exception_count'] : null;
$unassigned_intake_count = isset($context['unassigned_intake_count']) ? $context['unassigned_intake_count'] : null;
$working_hours_start = isset($context['working_hours_start']) ? $context['working_hours_start'] : null;
$working_hours_end = isset($context['working_hours_end']) ? $context['working_hours_end'] : null;
$working_days = isset($context['working_days']) ? $context['working_days'] : null;
$presales_targets = isset($context['presales_targets']) ? $context['presales_targets'] : array('calls' => 2, 'whatsapp' => 1);
$max_active_leads_per_rep = isset($context['max_active_leads_per_rep']) ? $context['max_active_leads_per_rep'] : 30;
$routing_exception_notifications_enabled = isset($context['routing_exception_notifications_enabled']) ? $context['routing_exception_notifications_enabled'] : '0';
?>
                <!-- Tab 2: Lead Routing -->
                <section id="settings-routing" class="idlm-settings-tab-content <?php echo $active_tab === 'routing' ? 'active' : ''; ?>" style="display: <?php echo $active_tab === 'routing' ? 'block' : 'none'; ?>;">
                    <article class="idlm-card">
                        <h3>Lead Routing & Assignment Rules</h3>
                        <div class="idlm-form-grid two">
                            <div class="idlm-form-group">
                                <label>Assignment Strategy</label>
                                <select name="assignment_mode" id="idlm-assignment-mode-select" class="idlm-select">
                                    <option value="manual" <?php selected($assignment_mode, 'manual'); ?>>Manual Only (Admin delegates from tables)</option>
                                    <option value="pre_sales" <?php selected($assignment_mode, 'pre_sales'); ?>>Pre-Sales Intake (Round robin across Pre-Sales users)</option>
                                    <option value="round_robin" <?php selected($assignment_mode, 'round_robin'); ?>>Round Robin (Autosync rotation across active reps)</option>
                                    <option value="source_based" <?php selected($assignment_mode, 'source_based'); ?>>Source-Based (Route channels to specific reps)</option>
                                    <option value="studio_based" <?php selected($assignment_mode, 'studio_based'); ?>>Studio-Based (Route preferred studios to assigned reps)</option>
                                </select>
                            </div>

                            <div class="idlm-form-group">
                                <label>Active Representative Pool:</label>
                                <p class="description">Enable a representative, then assign the studio whose leads they can receive.</p>
                                <div class="idlm-checkbox-list">
                                    <?php foreach ($reps as $rep): ?>
                                        <label style="display:grid; grid-template-columns:minmax(150px, 1fr) minmax(180px, 1fr); align-items:center; gap:10px;">
                                            <span><input type="checkbox" name="active_reps[]" value="<?php echo esc_attr($rep->ID); ?>" <?php checked(in_array($rep->ID, $active_reps)); ?>>
                                            <?php echo esc_html($rep->display_name); ?></span>
                                            <select name="rep_studios[<?php echo esc_attr($rep->ID); ?>]" class="idlm-select" aria-label="Studio location for <?php echo esc_attr($rep->display_name); ?>">
                                                <option value="">No studio assigned</option>
                                                <?php foreach ($studios as $studio): ?>
                                                    <option value="<?php echo esc_attr($studio); ?>" <?php selected(isset($rep_studios[$rep->ID]) ? $rep_studios[$rep->ID] : '', $studio); ?>><?php echo esc_html($studio); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Source maps sub panel -->
                        <div id="idlm-source-mapping-subpanel" style="display:<?php echo ($assignment_mode === 'source_based') ? 'grid' : 'none'; ?>; border-top:1px solid var(--idlm-border); padding-top:15px; margin-top:20px;" class="idlm-form-grid compact">
                            <h4 class="full" style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700;">Configure Source Routing Maps</h4>
                            <?php foreach (array('Website', 'Meta Ads', 'Google Ads', 'LinkedIn Ads', 'Referral', 'Offline/Walk-in') as $src):
                                $field_name = 'source_map_' . str_replace(array(' ', '/', '-'), '_', strtolower($src));
                                $val = isset($source_maps[$src]) ? $source_maps[$src] : 0;
                            ?>
                                <div class="idlm-form-group">
                                    <label>Route <?php echo esc_html($src); ?> to</label>
                                    <select name="<?php echo esc_attr($field_name); ?>" class="idlm-select">
                                        <option value="0">Unassigned</option>
                                        <?php foreach ($reps as $rep): 
                                            if (!in_array($rep->ID, $active_reps) && $val != $rep->ID) {
                                                continue;
                                            }
                                        ?>
                                            <option value="<?php echo esc_attr($rep->ID); ?>" <?php selected($val, $rep->ID); ?>><?php echo esc_html($rep->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Fallback Routing sub panel -->
                        <div id="idlm-fallback-routing-subpanel" class="idlm-form-grid two idlm-settings-routing-safeguards">
                            <?php
                            $settings_active_pre_sales_count = count(array_intersect(array_map('absint', (array) $active_pre_sales_reps), array_map('absint', wp_list_pluck($pre_sales_reps, 'ID'))));
                            $settings_assignment_labels = array('manual' => 'Manual only', 'pre_sales' => 'Pre-Sales intake', 'round_robin' => 'Round robin', 'source_based' => 'Source based', 'studio_based' => 'Studio based');
                            ?>
                            <div class="full idlm-routing-settings-title">
                                <h4>Routing Team & Safeguards</h4>
                                <p>Manage new-lead availability, backlog recovery, and qualified handoff as separate controls.</p>
                            </div>
                            <div class="full idlm-settings-routing-overview" aria-label="Current routing status">
                                <div><span>New lead routing</span><strong><?php echo esc_html(isset($settings_assignment_labels[$assignment_mode]) ? $settings_assignment_labels[$assignment_mode] : 'Not configured'); ?></strong></div>
                                <div><span>Active pre-sales</span><strong><?php echo esc_html($settings_active_pre_sales_count); ?> available</strong></div>
                                <div class="<?php echo $unassigned_intake_count > 0 ? 'is-warning' : ''; ?>"><span>Unassigned backlog</span><strong><?php echo esc_html($unassigned_intake_count); ?> leads</strong></div>
                                <div><span>Backlog recovery</span><strong><?php echo '1' === $fallback_enabled ? 'Enabled' : 'Disabled'; ?></strong></div>
                            </div>
                            <div class="idlm-form-group full idlm-routing-settings-group">
                                <div class="idlm-routing-settings-group-heading"><span>1</span><div><strong>Active Pre-Sales Team</strong><small>Checked users receive new Pre-Sales Intake leads immediately.</small></div></div>
                                <label style="font-weight: 500;">Available now</label>
                                <div style="display:flex; flex-wrap:wrap; gap:12px; margin-top:6px;">
                                    <?php if (empty($pre_sales_reps)): ?>
                                        <span class="description">No users currently have the Pre-Sales Representative role.</span>
                                    <?php else: ?>
                                        <?php foreach ($pre_sales_reps as $pre_sales_rep): ?>
                                            <label style="font-weight:normal;"><input type="checkbox" name="active_pre_sales_reps[]" value="<?php echo esc_attr($pre_sales_rep->ID); ?>" <?php checked(in_array($pre_sales_rep->ID, $active_pre_sales_reps)); ?>> <?php echo esc_html($pre_sales_rep->display_name); ?></label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <p class="description">Uncheck a user while they are unavailable or on leave. This does not recover older unassigned leads.</p>
                            </div>
                            <div class="idlm-form-group idlm-routing-settings-group">
                                <div class="idlm-routing-settings-group-heading"><span>2</span><div><strong>Unassigned Lead Recovery</strong><small>Optional backlog automation.</small></div></div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                                    <input type="checkbox" name="fallback_assignment_enabled" value="1" <?php checked($fallback_enabled, '1'); ?>>
                                    Automatically assign the unassigned backlog
                                </label>
                                <p class="description">When enabled, eligible unassigned leads return to the active pre-sales pool after the fallback threshold.</p>
                            </div>
                            <div class="idlm-form-group idlm-routing-settings-group">
                                <div class="idlm-routing-settings-group-heading"><span>3</span><div><strong>Qualified Lead Handoff</strong><small>Optional manager OOO coverage.</small></div></div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 500;">
                                    <input type="checkbox" name="qualified_fallback_enabled" value="1" <?php checked($qualified_fallback_enabled, '1'); ?>>
                                    Manager OOO: Auto-Assign Qualified Leads
                                </label>
                                <p class="description">Hands qualified leads still owned by pre-sales to active studio-matched sales representatives, then the general sales pool.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-weight: 500;">Maximum Active Leads per Representative</label>
                                <input type="number" name="max_active_leads_per_rep" class="idlm-input" min="0" max="10000" value="<?php echo esc_attr($max_active_leads_per_rep); ?>" style="width: 100%; max-width: 150px;">
                                <p class="description">Shared cap for every automatic pre-sales and sales routing pool. Active excludes duplicate, Won, Lost, and Unqualified leads. Enter 0 for unlimited.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-weight: 500;">Fallback Threshold (Working Hours)</label>
                                <input type="number" name="fallback_assignment_hours" class="idlm-input" min="1" max="720" value="<?php echo esc_attr($fallback_hours); ?>" style="width: 100%; max-width: 150px;">
                                <p class="description">Elapsed business/working hours to wait before automatically routing.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-weight: 500;">Qualified Handoff Threshold (Working Hours)</label>
                                <input type="number" name="qualified_fallback_hours" class="idlm-input" min="1" max="720" value="<?php echo esc_attr($qualified_fallback_hours); ?>" style="width: 100%; max-width: 150px;">
                                <p class="description">Wait time measured from the lead's qualification timestamp while Manager OOO is enabled.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-weight: 500;">Manager OOO Ends</label>
                                <input type="datetime-local" name="qualified_fallback_end_at" class="idlm-input" value="<?php echo esc_attr($qualified_fallback_end_at ? str_replace(' ', 'T', substr($qualified_fallback_end_at, 0, 16)) : ''); ?>">
                                <p class="description">Optional automatic expiry for Manager OOO routing.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-weight: 500;">Missing Studio Behavior</label>
                                <select name="studio_mismatch_behavior" class="idlm-select">
                                    <option value="general_pool" <?php selected($studio_mismatch_behavior, 'general_pool'); ?>>Use general active sales pool</option>
                                    <option value="hold_for_manager" <?php selected($studio_mismatch_behavior, 'hold_for_manager'); ?>>Hold for manager review</option>
                                </select>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-weight: 500;">Pending Task Policy</label>
                                <select name="handoff_task_policy" class="idlm-select">
                                    <option value="transfer" <?php selected($handoff_task_policy, 'transfer'); ?>>Transfer pending tasks to sales owner</option>
                                    <option value="retain" <?php selected($handoff_task_policy, 'retain'); ?>>Keep tasks with pre-sales</option>
                                    <option value="complete_qualification" <?php selected($handoff_task_policy, 'complete_qualification'); ?>>Complete qualification tasks only</option>
                                </select>
                            </div>
                            <div class="idlm-form-group">
                                <label><input type="checkbox" name="qualified_fallback_include_existing" value="1" <?php checked($qualified_fallback_include_existing, '1'); ?>> Include historical qualified leads</label>
                                <p class="description">Disabled by default to avoid moving older qualified leads immediately.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label><input type="checkbox" name="handoff_notifications_enabled" value="1" <?php checked($handoff_notifications_enabled, '1'); ?>> Email previous and new owners after handoff</label>
                            </div>
                            <div class="idlm-form-group">
                                <label><input type="checkbox" name="routing_exception_notifications_enabled" value="1" <?php checked($routing_exception_notifications_enabled, '1'); ?>> Email the administrator about routing exceptions</label>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-weight: 500;">Daily Working Hours Range</label>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <input type="time" name="working_hours_start" class="idlm-input" value="<?php echo esc_attr($working_hours_start); ?>" style="width: 100%; max-width: 120px;">
                                    <span>to</span>
                                    <input type="time" name="working_hours_end" class="idlm-input" value="<?php echo esc_attr($working_hours_end); ?>" style="width: 100%; max-width: 120px;">
                                </div>
                                <p class="description">Active hours when business is open and reps are available (e.g. 09:00 to 18:00).</p>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-weight: 500;">Calls Per Follow-up Cycle</label>
                                <input type="number" name="presales_calls_per_day" class="idlm-input" min="1" max="20" value="<?php echo esc_attr($presales_targets['calls']); ?>" style="width: 100%; max-width: 150px;">
                                <p class="description">Incomplete calls carry into the next working day instead of resetting.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-weight: 500;">WhatsApp Messages Per Follow-up Cycle</label>
                                <input type="number" name="presales_whatsapp_per_day" class="idlm-input" min="0" max="20" value="<?php echo esc_attr($presales_targets['whatsapp']); ?>" style="width: 100%; max-width: 150px;">
                                <p class="description">Enter 0 to make WhatsApp optional.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label style="font-weight: 500;">Business Working Days</label>
                                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 5px;">
                                    <?php 
                                    $days_map = array(
                                        1 => 'Mon',
                                        2 => 'Tue',
                                        3 => 'Wed',
                                        4 => 'Thu',
                                        5 => 'Fri',
                                        6 => 'Sat',
                                        0 => 'Sun'
                                    );
                                    foreach ($days_map as $val => $label):
                                    ?>
                                        <label style="display: flex; align-items: center; gap: 4px; font-weight: normal; cursor: pointer;">
                                            <input type="checkbox" name="working_days[]" value="<?php echo esc_attr($val); ?>" <?php checked(in_array($val, $working_days)); ?>>
                                            <?php echo esc_html($label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description">Select days when leads are actively assigned.</p>
                            </div>
                            <div class="idlm-form-group full">
                                <label style="font-weight: 500;">Fallback Health</label>
                                <p class="description">Last completed: <?php echo esc_html($fallback_last_completed); ?> · Status: <?php echo esc_html($fallback_last_status); ?> · Open routing exceptions: <?php echo esc_html($routing_exception_count); ?></p>
                                <button type="button" class="idlm-btn idlm-btn-secondary idlm-run-fallback-now">Run Fallback Now</button>
                            </div>
                        </div>
                    </article>
                </section> <!-- End Tab 2: Lead Routing -->
