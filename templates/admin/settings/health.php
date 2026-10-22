<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$idlm_telemetry_level = isset($context['telemetry_level']) ? $context['telemetry_level'] : 'errors';
$idlm_logs = isset($context['telemetry_logs']) ? $context['telemetry_logs'] : array();
$idlm_php_version = isset($context['php_version']) ? $context['php_version'] : '';
$idlm_wordpress_version = isset($context['wordpress_version']) ? $context['wordpress_version'] : '';
$idlm_memory_limit = isset($context['memory_limit']) ? $context['memory_limit'] : '';
$idlm_action_scheduler_version = isset($context['action_scheduler_version']) ? $context['action_scheduler_version'] : '';
$idlm_db_prefix = isset($context['db_prefix']) ? $context['db_prefix'] : '';
$idlm_operational_health = isset($context['operational_health']) && is_array($context['operational_health']) ? $context['operational_health'] : array();
$idlm_horizontal_readiness = isset($context['horizontal_readiness']) && is_array($context['horizontal_readiness']) ? $context['horizontal_readiness'] : array();
$idlm_diagnostic_url = isset($context['diagnostic_url']) ? $context['diagnostic_url'] : '';
?>
                <!-- Tab: System Health -->
                <section id="settings-health" class="idlm-settings-tab-content <?php echo $idlm_active_tab === 'health' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-health" <?php echo $idlm_active_tab === 'health' ? '' : 'hidden'; ?>>
                    <div class="idlm-grid-full">
                        <!-- Telemetry Settings Card -->
                        <article class="idlm-card">
                            <h3>Telemetry & Logging Configuration</h3>
                            <p class="idlm-card-desc">Configure the error tracking log level. Higher levels record more details but may write more rows if errors occur.</p>
                            
                            <div class="idlm-form-grid two">
                                <div class="idlm-form-group">
                                    <label for="idlm-telemetry-level">Error telemetry log level</label>
                                    <select id="idlm-telemetry-level" name="telemetry_level" class="idlm-select">
                                        <option value="off" <?php selected($idlm_telemetry_level, 'off'); ?>>Disabled (No Logs)</option>
                                        <option value="errors" <?php selected($idlm_telemetry_level, 'errors'); ?>>(Recommended) Errors Only (Exceptions & JS Errors)</option>
                                        <option value="warnings" <?php selected($idlm_telemetry_level, 'warnings'); ?>>Errors & PHP Warnings</option>
                                        <option value="verbose" <?php selected($idlm_telemetry_level, 'verbose'); ?>>Verbose Debug (All Logs & Warnings)</option>
                                    </select>
                                </div>
                                <div class="idlm-form-group">
                                    <span class="idlm-form-label">Diagnostic export</span>
                                    <div>
                                        <a href="<?php echo esc_url($idlm_diagnostic_url); ?>" class="idlm-btn idlm-btn-secondary idlm-control-full idlm-link-plain">
                                            Download Diagnostic Report (.txt)
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <!-- System Diagnostics Overview Card -->
                        <article class="idlm-card">
                            <h3>System Diagnostics Overview</h3>
                            <p class="idlm-card-desc">Key environment metrics for debugging. Share these details when reporting a bug.</p>
                            
                            <div class="idlm-health-grid">
                                <div class="idlm-health-item">
                                    <span>PHP Version</span>
                                    <strong><?php echo esc_html($idlm_php_version); ?></strong>
                                </div>
                                <div class="idlm-health-item">
                                    <span>WordPress Version</span>
                                    <strong><?php echo esc_html($idlm_wordpress_version); ?></strong>
                                </div>
                                <div class="idlm-health-item">
                                    <span>Memory Limit</span>
                                    <strong><?php echo esc_html($idlm_memory_limit); ?></strong>
                                </div>
                                <div class="idlm-health-item">
                                    <span>Action Scheduler</span>
                                    <strong>
                                        <?php if (!empty($idlm_action_scheduler_version)): ?>
                                            <span class="idlm-status-text is-success">Active (v<?php echo esc_html($idlm_action_scheduler_version); ?>)</span>
                                        <?php else: ?>
                                            <span class="idlm-status-text is-danger">Missing (WP Cron Fallback)</span>
                                        <?php endif; ?>
                                    </strong>
                                </div>
                                <div class="idlm-health-item">
                                    <span>DB Table Prefix</span>
                                    <strong>
                                        <?php echo esc_html($idlm_db_prefix); ?>
                                    </strong>
                                </div>
                            </div>
                        </article>

                        <!-- Operational Health Card -->
                        <article class="idlm-card idlm-operational-health">
                            <h3>Operational Health</h3>
                            <?php
                            $idlm_health_status = isset($idlm_operational_health['status']) ? $idlm_operational_health['status'] : 'critical';
                            $idlm_health_label = 'healthy' === $idlm_health_status ? 'Healthy' : ('warning' === $idlm_health_status ? 'Warning' : 'Critical');
                            $idlm_health_class = 'healthy' === $idlm_health_status ? 'is-success' : ('warning' === $idlm_health_status ? 'is-warning' : 'is-danger');
                            ?>
                            <p class="idlm-card-desc">
                                Current aggregate status:
                                <strong class="idlm-status-text <?php echo esc_attr($idlm_health_class); ?>"><?php echo esc_html($idlm_health_label); ?></strong>.
                                Warning thresholds begin at a 5-minute queue age or one recent fault; critical thresholds begin at 15 minutes or ten recent faults.
                            </p>

                            <div class="idlm-health-grid">
                                <?php foreach (($idlm_operational_health['checks'] ?? array()) as $idlm_check): ?>
                                    <?php
                                    $idlm_check_status = isset($idlm_check['status']) ? $idlm_check['status'] : 'critical';
                                    $idlm_status_class = 'healthy' === $idlm_check_status ? 'is-success' : ('warning' === $idlm_check_status ? 'is-warning' : ('info' === $idlm_check_status ? 'is-muted' : 'is-danger'));
                                    ?>
                                    <div class="idlm-health-item idlm-operational-check" title="<?php echo esc_attr($idlm_check['guidance'] ?? ''); ?>">
                                        <span><?php echo esc_html($idlm_check['label'] ?? 'Operational check'); ?></span>
                                        <strong class="idlm-status-text <?php echo esc_attr($idlm_status_class); ?>"><?php echo esc_html($idlm_check['value'] ?? 'Unknown'); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="idlm-card-desc idlm-mt-3">This report is read-only and exposes status, counts, and age only. It never returns credentials, contacts, payloads, hosts, database names, or storage paths.</p>
                        </article>

                        <!-- Horizontal Deployment Readiness Card -->
                        <article class="idlm-card idlm-horizontal-readiness">
                            <h3>Horizontal Deployment Readiness</h3>
                            <?php if ('multi_node' === ($idlm_horizontal_readiness['mode'] ?? 'single_node')): ?>
                                <p class="idlm-card-desc">
                                    <?php if ('ready' === ($idlm_horizontal_readiness['status'] ?? 'blocked')): ?>
                                        All declared multi-node infrastructure gates are ready. Complete the two-node resilience suite before rollout.
                                    <?php else: ?>
                                        Multi-node mode is blocked by <?php echo esc_html((string) absint($idlm_horizontal_readiness['blocking_count'] ?? 0)); ?> unresolved infrastructure gate(s).
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p class="idlm-card-desc">Single-node mode is active. Review and satisfy these gates before enabling <code>IDLM_MULTI_NODE_MODE</code>.</p>
                            <?php endif; ?>

                            <div class="idlm-health-grid">
                                <?php foreach (($idlm_horizontal_readiness['checks'] ?? array()) as $idlm_check): ?>
                                    <?php
                                    $idlm_check_status = isset($idlm_check['status']) ? $idlm_check['status'] : 'not_enabled';
                                    $idlm_status_label = 'not_enabled' === $idlm_check_status ? 'Before multi-node' : ('ready' === $idlm_check_status ? 'Ready' : 'Blocked');
                                    $idlm_status_class = 'blocked' === $idlm_check_status ? 'is-danger' : ('ready' === $idlm_check_status ? 'is-success' : 'is-muted');
                                    ?>
                                    <div class="idlm-health-item idlm-horizontal-check" title="<?php echo esc_attr($idlm_check['guidance'] ?? ''); ?>">
                                        <span><?php echo esc_html($idlm_check['label'] ?? 'Infrastructure check'); ?></span>
                                        <strong class="idlm-status-text <?php echo esc_attr($idlm_status_class); ?>"><?php echo esc_html($idlm_status_label); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="idlm-card-desc idlm-mt-3">This preflight is read-only and reports boolean readiness only; it never exposes database hosts, storage paths, or secret values.</p>
                        </article>

                        <!-- Telemetry Logs Table Card -->
                        <article class="idlm-card">
                            <div class="idlm-shortcode-head idlm-mb-4">
                                <h3 class="idlm-heading-flush">Recent Telemetry & Bug Logs</h3>
                                <button type="button" id="idlm-clear-telemetry-btn" class="idlm-btn idlm-btn-secondary idlm-btn-danger-outline">
                                    Clear Telemetry Logs
                                </button>
                            </div>
                            <p class="idlm-card-desc">This log captures errors that occurred specifically in the Interior Design Lead Manager plugin.</p>
                            
                            <table class="idlm-table">
                                <thead>
                                    <tr>
                                        <th>Date (Local)</th>
                                        <th>Log Type</th>
                                        <th>Severity</th>
                                        <th>Message</th>
                                        <th>File & Line</th>
                                        <th class="idlm-table-action-cell">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($idlm_logs)): ?>
                                        <tr>
                                            <td colspan="6" class="idlm-table-empty">No telemetry logs recorded. Your plugin is healthy!</td>
                                        </tr>
                                    <?php else:
                                        foreach ($idlm_logs as $idlm_log):
                                            ?>
                                            <tr>
                                                <td class="idlm-table-cell-nowrap idlm-status-text is-muted"><?php echo esc_html($idlm_log->created_at); ?></td>
                                                <td class="idlm-table-cell-code"><?php echo esc_html($idlm_log->log_type); ?></td>
                                                <td class="idlm-table-cell-nowrap">
                                                    <span class="idlm-pill <?php echo ($idlm_log->severity === 'error') ? 'danger' : (($idlm_log->severity === 'warning') ? 'warn' : ''); ?>">
                                                        <?php echo esc_html(strtoupper((string) $idlm_log->severity)); ?>
                                                    </span>
                                                </td>
                                                <td class="idlm-table-cell-truncate" title="<?php echo esc_attr($idlm_log->message); ?>">
                                                    <?php echo esc_html($idlm_log->message); ?>
                                                </td>
                                                <td class="idlm-table-cell-code" title="<?php echo esc_attr($idlm_log->file . ' (Line ' . $idlm_log->line . ')'); ?>">
                                                    <?php echo esc_html($idlm_log->file ? basename($idlm_log->file) : '-'); ?><?php echo $idlm_log->line ? ' (Line ' . intval($idlm_log->line) . ')' : ''; ?>
                                                </td>
                                                <td class="idlm-table-action-cell">
                                                    <?php if (!empty($idlm_log->stack_trace)): ?>
                                                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-view-trace-btn idlm-btn-compact" data-trace="<?php echo esc_attr($idlm_log->stack_trace); ?>" data-message="<?php echo esc_attr($idlm_log->message); ?>">
                                                            View Trace
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="idlm-status-text is-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach;
                                    endif; ?>
                                </tbody>
                            </table>
                        </article>

                        <!-- Telemetry Stack Trace Modal -->
                        <div id="idlm-trace-modal" class="idlm-modal idlm-dialog" role="dialog" aria-modal="true" aria-labelledby="idlm-trace-modal-title" aria-describedby="idlm-trace-modal-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="#idlm-trace-dismiss-btn">
                            <div class="idlm-modal-content idlm-dialog__panel idlm-trace-dialog">
                                <header class="idlm-modal-header idlm-dialog__header">
                                    <div><h2 class="idlm-dialog__title" id="idlm-trace-modal-title">Stack trace details</h2><p class="idlm-dialog__description" id="idlm-trace-modal-description">Review the captured diagnostic stack for this telemetry event.</p></div>
                                    <button type="button" class="idlm-dialog__close" id="idlm-trace-close-btn" data-idlm-dialog-close aria-label="Close stack trace dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                                </header>
                                <div class="idlm-dialog__body"><div class="idlm-stack-trace"><pre id="idlm-trace-content"></pre></div></div>
                                <footer class="idlm-dialog__footer">
                                    <button type="button" id="idlm-trace-dismiss-btn" class="idlm-btn idlm-btn-secondary" data-idlm-dialog-close>Close</button>
                                </footer>
                            </div>
                        </div>
                    </div>
                </section> <!-- End Tab: System Health -->
