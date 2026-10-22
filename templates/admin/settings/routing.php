<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$idlm_assignment_mode = isset($context['assignment_mode']) ? $context['assignment_mode'] : null;
$idlm_active_reps = isset($context['active_reps']) ? $context['active_reps'] : null;
$idlm_active_pre_sales_reps = isset($context['active_pre_sales_reps']) ? $context['active_pre_sales_reps'] : null;
$idlm_reps = isset($context['reps']) ? $context['reps'] : null;
$idlm_pre_sales_reps = isset($context['pre_sales_reps']) ? $context['pre_sales_reps'] : null;
$idlm_source_maps = isset($context['source_maps']) ? $context['source_maps'] : null;
$idlm_rep_studios = isset($context['rep_studios']) ? $context['rep_studios'] : null;
$idlm_studios = isset($context['studios']) ? $context['studios'] : null;
$idlm_fallback_enabled = isset($context['fallback_enabled']) ? $context['fallback_enabled'] : null;
$idlm_fallback_hours = isset($context['fallback_hours']) ? $context['fallback_hours'] : null;
$idlm_qualified_fallback_enabled = isset($context['qualified_fallback_enabled']) ? $context['qualified_fallback_enabled'] : null;
$idlm_qualified_fallback_hours = isset($context['qualified_fallback_hours']) ? $context['qualified_fallback_hours'] : null;
$idlm_qualified_fallback_end_at = isset($context['qualified_fallback_end_at']) ? $context['qualified_fallback_end_at'] : null;
$idlm_qualified_fallback_include_existing = isset($context['qualified_fallback_include_existing']) ? $context['qualified_fallback_include_existing'] : null;
$idlm_studio_mismatch_behavior = isset($context['studio_mismatch_behavior']) ? $context['studio_mismatch_behavior'] : null;
$idlm_handoff_task_policy = isset($context['handoff_task_policy']) ? $context['handoff_task_policy'] : null;
$idlm_handoff_notifications_enabled = isset($context['handoff_notifications_enabled']) ? $context['handoff_notifications_enabled'] : null;
$idlm_fallback_last_completed = isset($context['fallback_last_completed']) ? $context['fallback_last_completed'] : null;
$idlm_fallback_last_status = isset($context['fallback_last_status']) ? $context['fallback_last_status'] : null;
$idlm_routing_exception_count = isset($context['routing_exception_count']) ? $context['routing_exception_count'] : null;
$idlm_unassigned_intake_count = isset($context['unassigned_intake_count']) ? $context['unassigned_intake_count'] : null;
$idlm_working_hours_start = isset($context['working_hours_start']) ? $context['working_hours_start'] : null;
$idlm_working_hours_end = isset($context['working_hours_end']) ? $context['working_hours_end'] : null;
$idlm_working_days = isset($context['working_days']) ? $context['working_days'] : null;
$idlm_presales_targets = isset($context['presales_targets']) ? $context['presales_targets'] : array('calls' => 2, 'whatsapp' => 1);
$idlm_max_active_leads_per_rep = isset($context['max_active_leads_per_rep']) ? $context['max_active_leads_per_rep'] : 30;
$idlm_routing_exception_notifications_enabled = isset($context['routing_exception_notifications_enabled']) ? $context['routing_exception_notifications_enabled'] : '0';
$idlm_role_labels = wp_roles()->get_names();
?>
                <!-- Tab 2: Lead Routing -->
                <section id="settings-routing" class="idlm-settings-tab-content <?php echo $idlm_active_tab === 'routing' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-routing" <?php echo $idlm_active_tab === 'routing' ? '' : 'hidden'; ?>>
                    <article class="idlm-card idlm-routing-settings-body">
                        <h3>Routing rules</h3>
                        <div class="idlm-form-grid two idlm-routing-primary-grid">
                            <section class="idlm-form-group idlm-routing-section idlm-routing-strategy-section">
                                <div class="idlm-routing-section-heading">
                                    <div><h3>New leads</h3><p>Choose how new leads are assigned.</p></div>
                                </div>
                                <label for="idlm-assignment-mode-select">Routing method</label>
                                <select name="assignment_mode" id="idlm-assignment-mode-select" class="idlm-select">
                                    <option value="manual" <?php selected($idlm_assignment_mode, 'manual'); ?>>Manual Only (Admin delegates from tables)</option>
                                    <option value="pre_sales" <?php selected($idlm_assignment_mode, 'pre_sales'); ?>>Pre-Sales Intake (Round robin across Pre-Sales users)</option>
                                    <option value="round_robin" <?php selected($idlm_assignment_mode, 'round_robin'); ?>>Round Robin (Autosync rotation across active reps)</option>
                                    <option value="source_based" <?php selected($idlm_assignment_mode, 'source_based'); ?>>Source-Based (Route channels to specific reps)</option>
                                    <option value="studio_based" <?php selected($idlm_assignment_mode, 'studio_based'); ?>>Studio-Based (Route preferred studios to assigned reps)</option>
                                </select>
                            </section>

                            <section class="idlm-form-group idlm-routing-section idlm-routing-reps-section">
                                <div class="idlm-routing-section-heading">
                                    <div><h3>Representative pool</h3><p>Choose available reps and their studios.</p></div>
                                </div>
                                <div class="idlm-checkbox-list idlm-routing-rep-list">
                                    <table class="idlm-routing-rep-table">
                                        <thead><tr><th>Representative</th><th>Role</th><th>Studio location</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($idlm_reps as $idlm_rep): ?>
                                                <?php
                                                $idlm_rep_role_names = array_map(static function($idlm_role) use ($idlm_role_labels) {
                                                    return isset($idlm_role_labels[$idlm_role]) ? $idlm_role_labels[$idlm_role] : ucwords(str_replace('_', ' ', $idlm_role));
                                                }, (array) $idlm_rep->roles);
                                                ?>
                                                <tr class="idlm-routing-rep-row">
                                                    <td><label class="idlm-routing-rep-name"><input type="checkbox" name="active_reps[]" value="<?php echo esc_attr($idlm_rep->ID); ?>" <?php checked(in_array($idlm_rep->ID, $idlm_active_reps)); ?>> <?php echo esc_html($idlm_rep->display_name); ?></label></td>
                                                    <td><span class="idlm-routing-rep-role"><?php echo esc_html(implode(', ', $idlm_rep_role_names)); ?></span></td>
                                                    <td><select name="rep_studios[<?php echo esc_attr($idlm_rep->ID); ?>]" class="idlm-select" aria-label="Studio location for <?php echo esc_attr($idlm_rep->display_name); ?>">
                                                        <option value="">No studio assigned</option>
                                                        <?php foreach ($idlm_studios as $idlm_studio): ?>
                                                            <option value="<?php echo esc_attr($idlm_studio); ?>" <?php selected(isset($idlm_rep_studios[$idlm_rep->ID]) ? $idlm_rep_studios[$idlm_rep->ID] : '', $idlm_studio); ?>><?php echo esc_html($idlm_studio); ?></option>
                                                        <?php endforeach; ?>
                                                    </select></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>

                        <!-- Source maps sub panel -->
                        <div id="idlm-source-mapping-subpanel" class="idlm-form-grid compact idlm-routing-section idlm-routing-source-section" <?php echo ($idlm_assignment_mode === 'source_based') ? '' : 'hidden'; ?>>
                            <div class="full idlm-routing-section-heading"><div><h3>Source assignments</h3><p>Assign each source to a rep.</p></div></div>
                            <?php foreach (array('Website', 'Meta Ads', 'Google Ads', 'LinkedIn Ads', 'Referral', 'Offline/Walk-in') as $idlm_src):
                                $idlm_field_name = 'source_map_' . str_replace(array(' ', '/', '-'), '_', strtolower($idlm_src));
                                $idlm_val = isset($idlm_source_maps[$idlm_src]) ? $idlm_source_maps[$idlm_src] : 0;
                            ?>
                                <div class="idlm-form-group">
                                    <label for="idlm-source-map-<?php echo esc_attr(sanitize_key($idlm_src)); ?>"><?php echo esc_html($idlm_src); ?> rep</label>
                                    <select id="idlm-source-map-<?php echo esc_attr(sanitize_key($idlm_src)); ?>" name="<?php echo esc_attr($idlm_field_name); ?>" class="idlm-select">
                                        <option value="0">Unassigned</option>
                                        <?php foreach ($idlm_reps as $idlm_rep):
                                            if (!in_array($idlm_rep->ID, $idlm_active_reps) && $idlm_val != $idlm_rep->ID) {
                                                continue;
                                            }
                                        ?>
                                            <option value="<?php echo esc_attr($idlm_rep->ID); ?>" <?php selected($idlm_val, $idlm_rep->ID); ?>><?php echo esc_html($idlm_rep->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Fallback Routing sub panel -->
                        <div id="idlm-fallback-routing-subpanel" class="idlm-form-grid two idlm-settings-routing-safeguards idlm-routing-advanced">
                            <?php
                            $idlm_settings_active_pre_sales_count = count(array_intersect(array_map('absint', (array) $idlm_active_pre_sales_reps), array_map('absint', wp_list_pluck($idlm_pre_sales_reps, 'ID'))));
                            $idlm_settings_assignment_labels = array('manual' => 'Manual only', 'pre_sales' => 'Pre-Sales intake', 'round_robin' => 'Round robin', 'source_based' => 'Source based', 'studio_based' => 'Studio based');
                            ?>
                            <div class="full idlm-routing-settings-title">
                                <h4>Team & safeguards</h4>
                                <p>Manage availability, recovery, and handoff.</p>
                            </div>
                            <div class="full idlm-settings-routing-overview" aria-label="Current routing status">
                                <div><span>New lead routing</span><strong><?php echo esc_html(isset($idlm_settings_assignment_labels[$idlm_assignment_mode]) ? $idlm_settings_assignment_labels[$idlm_assignment_mode] : 'Not configured'); ?></strong></div>
                                <div><span>Active pre-sales</span><strong><?php echo esc_html($idlm_settings_active_pre_sales_count); ?> available</strong></div>
                                <div class="<?php echo $idlm_unassigned_intake_count > 0 ? 'is-warning' : ''; ?>"><span>Unassigned backlog</span><strong><?php echo esc_html($idlm_unassigned_intake_count); ?> leads</strong></div>
                                <div><span>Backlog recovery</span><strong><?php echo '1' === $idlm_fallback_enabled ? 'Enabled' : 'Disabled'; ?></strong></div>
                            </div>
                            <div class="idlm-form-group full idlm-routing-settings-group">
                                <div class="idlm-routing-settings-group-heading"><span>1</span><div><strong>Pre-sales availability</strong><small>Checked users receive new leads.</small></div></div>
                                <span class="idlm-form-label">Available reps</span>
                                <div class="idlm-settings-wrap">
                                    <?php if (empty($idlm_pre_sales_reps)): ?>
                                        <span class="description">No users currently have the Pre-Sales Representative role.</span>
                                    <?php else: ?>
                                        <?php foreach ($idlm_pre_sales_reps as $idlm_pre_sales_rep): ?>
                                            <label data-idlm-ui="normal"><input type="checkbox" name="active_pre_sales_reps[]" value="<?php echo esc_attr($idlm_pre_sales_rep->ID); ?>" <?php checked(in_array($idlm_pre_sales_rep->ID, $idlm_active_pre_sales_reps)); ?>> <?php echo esc_html($idlm_pre_sales_rep->display_name); ?></label>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <p class="description">Uncheck unavailable reps. Existing unassigned leads stay unchanged.</p>
                            </div>
                            <div class="idlm-form-group idlm-routing-settings-group">
                                <div class="idlm-routing-settings-group-heading"><span>2</span><div><strong>Unassigned lead recovery</strong><small>Optional backlog routing.</small></div></div>
                                <label data-idlm-ui="check-label">
                                    <input type="checkbox" name="fallback_assignment_enabled" value="1" <?php checked($idlm_fallback_enabled, '1'); ?>>
                                    Automatically assign the unassigned backlog
                                </label>
                                <p class="description">Return eligible unassigned leads after the threshold.</p>
                            </div>
                            <div class="idlm-form-group idlm-routing-settings-group">
                                <div class="idlm-routing-settings-group-heading"><span>3</span><div><strong>Qualified lead handoff</strong><small>Optional manager coverage.</small></div></div>
                                <label data-idlm-ui="check-label">
                                    <input type="checkbox" name="qualified_fallback_enabled" value="1" <?php checked($idlm_qualified_fallback_enabled, '1'); ?>>
                                    Manager OOO: Auto-Assign Qualified Leads
                                </label>
                                <p class="description">Assign qualified leads to a studio-matched sales rep, then the general pool.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label for="idlm-max-active-leads" data-idlm-ui="medium">Active lead limit per rep</label>
                                <input type="number" id="idlm-max-active-leads" name="max_active_leads_per_rep" class="idlm-input" min="0" max="10000" inputmode="numeric" value="<?php echo esc_attr($idlm_max_active_leads_per_rep); ?>" data-idlm-ui="max-150">
                                <p class="description">Applies to automatic pools. Use 0 for no limit.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label for="idlm-fallback-hours" data-idlm-ui="medium">Recovery delay (hours)</label>
                                <input type="number" id="idlm-fallback-hours" name="fallback_assignment_hours" class="idlm-input" min="1" max="720" inputmode="numeric" value="<?php echo esc_attr($idlm_fallback_hours); ?>" data-idlm-ui="max-150">
                                <p class="description">Wait time before automatic recovery.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label for="idlm-qualified-fallback-hours" data-idlm-ui="medium">Handoff delay (hours)</label>
                                <input type="number" id="idlm-qualified-fallback-hours" name="qualified_fallback_hours" class="idlm-input" min="1" max="720" inputmode="numeric" value="<?php echo esc_attr($idlm_qualified_fallback_hours); ?>" data-idlm-ui="max-150">
                                <p class="description">Wait time from qualification while manager coverage is on.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label for="idlm-qualified-fallback-end" data-idlm-ui="medium">Manager OOO ends</label>
                                <input type="datetime-local" id="idlm-qualified-fallback-end" name="qualified_fallback_end_at" class="idlm-input" value="<?php echo esc_attr($idlm_qualified_fallback_end_at ? str_replace(' ', 'T', substr($idlm_qualified_fallback_end_at, 0, 16)) : ''); ?>">
                                <p class="description">Optional end time for manager coverage.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label for="idlm-studio-mismatch-behavior" data-idlm-ui="medium">No studio match</label>
                                <select id="idlm-studio-mismatch-behavior" name="studio_mismatch_behavior" class="idlm-select">
                                    <option value="general_pool" <?php selected($idlm_studio_mismatch_behavior, 'general_pool'); ?>>Use general active sales pool</option>
                                    <option value="hold_for_manager" <?php selected($idlm_studio_mismatch_behavior, 'hold_for_manager'); ?>>Hold for manager review</option>
                                </select>
                            </div>
                            <div class="idlm-form-group">
                                <label for="idlm-handoff-task-policy" data-idlm-ui="medium">Pending tasks</label>
                                <select id="idlm-handoff-task-policy" name="handoff_task_policy" class="idlm-select">
                                    <option value="transfer" <?php selected($idlm_handoff_task_policy, 'transfer'); ?>>Transfer pending tasks to sales owner</option>
                                    <option value="retain" <?php selected($idlm_handoff_task_policy, 'retain'); ?>>Keep tasks with pre-sales</option>
                                    <option value="complete_qualification" <?php selected($idlm_handoff_task_policy, 'complete_qualification'); ?>>Complete qualification tasks only</option>
                                </select>
                            </div>
                            <div class="idlm-form-group">
                                <label><input type="checkbox" name="qualified_fallback_include_existing" value="1" <?php checked($idlm_qualified_fallback_include_existing, '1'); ?>> Include historical qualified leads</label>
                                <p class="description">Off by default to avoid an immediate bulk handoff.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label><input type="checkbox" name="handoff_notifications_enabled" value="1" <?php checked($idlm_handoff_notifications_enabled, '1'); ?>> Email previous and new owners after handoff</label>
                            </div>
                            <div class="idlm-form-group">
                                <label><input type="checkbox" name="routing_exception_notifications_enabled" value="1" <?php checked($idlm_routing_exception_notifications_enabled, '1'); ?>> Email the administrator about routing exceptions</label>
                            </div>
                            <div class="idlm-form-group">
                                <span class="idlm-form-label">Working hours</span>
                                <div class="idlm-settings-working-hours">
                                    <input type="time" name="working_hours_start" class="idlm-input" aria-label="Working hours start" value="<?php echo esc_attr($idlm_working_hours_start); ?>" data-idlm-ui="max-120">
                                    <span>to</span>
                                    <input type="time" name="working_hours_end" class="idlm-input" aria-label="Working hours end" value="<?php echo esc_attr($idlm_working_hours_end); ?>" data-idlm-ui="max-120">
                                </div>
                                <p class="description">Hours when reps can receive leads.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label for="idlm-presales-calls-per-day" data-idlm-ui="medium">Calls per follow-up cycle</label>
                                <input type="number" id="idlm-presales-calls-per-day" name="presales_calls_per_day" class="idlm-input" min="1" max="20" inputmode="numeric" value="<?php echo esc_attr($idlm_presales_targets['calls']); ?>" data-idlm-ui="max-150">
                                <p class="description">Incomplete calls carry forward.</p>
                            </div>
                            <div class="idlm-form-group">
                                <label for="idlm-presales-whatsapp-per-day" data-idlm-ui="medium">WhatsApp messages per follow-up cycle</label>
                                <input type="number" id="idlm-presales-whatsapp-per-day" name="presales_whatsapp_per_day" class="idlm-input" min="0" max="20" inputmode="numeric" value="<?php echo esc_attr($idlm_presales_targets['whatsapp']); ?>" data-idlm-ui="max-150">
                                <p class="description">Enter 0 to make WhatsApp optional.</p>
                            </div>
                            <div class="idlm-form-group">
                                <span class="idlm-form-label">Business working days</span>
                                <div data-idlm-ui="grid-four mt-1">
                                    <?php 
                                    $idlm_days_map = array(
                                        1 => 'Mon',
                                        2 => 'Tue',
                                        3 => 'Wed',
                                        4 => 'Thu',
                                        5 => 'Fri',
                                        6 => 'Sat',
                                        0 => 'Sun'
                                    );
                                    foreach ($idlm_days_map as $idlm_val => $idlm_label):
                                    ?>
                                        <label data-idlm-ui="check-label normal">
                                            <input type="checkbox" name="working_days[]" value="<?php echo esc_attr($idlm_val); ?>" <?php checked(in_array($idlm_val, $idlm_working_days)); ?>>
                                            <?php echo esc_html($idlm_label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <p class="description">Days when leads can be assigned.</p>
                            </div>
                            <div class="idlm-form-group full">
                                <span class="idlm-form-label">Fallback health</span>
                                <p class="description">Last completed: <?php echo esc_html($idlm_fallback_last_completed); ?> · Status: <?php echo esc_html($idlm_fallback_last_status); ?> · Open routing exceptions: <?php echo esc_html($idlm_routing_exception_count); ?></p>
                                <button type="button" class="idlm-btn idlm-btn-secondary idlm-run-fallback-now">Run fallback now</button>
                            </div>
                        </div>
                    </article>
                </section> <!-- End Tab 2: Lead Routing -->
