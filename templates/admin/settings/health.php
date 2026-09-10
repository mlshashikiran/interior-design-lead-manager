<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$telemetry_level = isset($context['telemetry_level']) ? $context['telemetry_level'] : 'errors';
$logs = isset($context['telemetry_logs']) ? $context['telemetry_logs'] : array();
$php_version = isset($context['php_version']) ? $context['php_version'] : '';
$wordpress_version = isset($context['wordpress_version']) ? $context['wordpress_version'] : '';
$memory_limit = isset($context['memory_limit']) ? $context['memory_limit'] : '';
$action_scheduler_version = isset($context['action_scheduler_version']) ? $context['action_scheduler_version'] : '';
$db_prefix = isset($context['db_prefix']) ? $context['db_prefix'] : '';
$operational_health = isset($context['operational_health']) && is_array($context['operational_health']) ? $context['operational_health'] : array();
$horizontal_readiness = isset($context['horizontal_readiness']) && is_array($context['horizontal_readiness']) ? $context['horizontal_readiness'] : array();
$diagnostic_url = isset($context['diagnostic_url']) ? $context['diagnostic_url'] : '';
?>
                <!-- Tab: System Health -->
                <section id="settings-health" class="idlm-settings-tab-content <?php echo $active_tab === 'health' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-health" <?php echo $active_tab === 'health' ? '' : 'hidden'; ?>>
                    <div class="idlm-grid-full">
                        <!-- Telemetry Settings Card -->
                        <article class="idlm-card">
                            <h3>Telemetry & Logging Configuration</h3>
                            <p class="idlm-card-desc">Configure the error tracking log level. Higher levels record more details but may write more rows if errors occur.</p>
                            
                            <div class="idlm-form-grid two">
                                <div class="idlm-form-group">
                                    <label for="idlm-telemetry-level">Error telemetry log level</label>
                                    <select id="idlm-telemetry-level" name="telemetry_level" class="idlm-select">
                                        <option value="off" <?php selected($telemetry_level, 'off'); ?>>Disabled (No Logs)</option>
                                        <option value="errors" <?php selected($telemetry_level, 'errors'); ?>>(Recommended) Errors Only (Exceptions & JS Errors)</option>
                                        <option value="warnings" <?php selected($telemetry_level, 'warnings'); ?>>Errors & PHP Warnings</option>
                                        <option value="verbose" <?php selected($telemetry_level, 'verbose'); ?>>Verbose Debug (All Logs & Warnings)</option>
                                    </select>
                                </div>
                                <div class="idlm-form-group">
                                    <span class="idlm-form-label">Diagnostic export</span>
                                    <div>
                                        <a href="<?php echo esc_url($diagnostic_url); ?>" class="idlm-btn idlm-btn-secondary idlm-control-full idlm-link-plain">
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
                                    <strong><?php echo esc_html($php_version); ?></strong>
                                </div>
                                <div class="idlm-health-item">
                                    <span>WordPress Version</span>
                                    <strong><?php echo esc_html($wordpress_version); ?></strong>
                                </div>
                                <div class="idlm-health-item">
                                    <span>Memory Limit</span>
                                    <strong><?php echo esc_html($memory_limit); ?></strong>
                                </div>
                                <div class="idlm-health-item">
                                    <span>Action Scheduler</span>
                                    <strong>
                                        <?php if (!empty($action_scheduler_version)): ?>
                                            <span class="idlm-status-text is-success">Active (v<?php echo esc_html($action_scheduler_version); ?>)</span>
                                        <?php else: ?>
                                            <span class="idlm-status-text is-danger">Missing (WP Cron Fallback)</span>
                                        <?php endif; ?>
                                    </strong>
                                </div>
                                <div class="idlm-health-item">
                                    <span>DB Table Prefix</span>
                                    <strong>
                                        <?php echo esc_html($db_prefix); ?>
                                    </strong>
                                </div>
                            </div>
                        </article>

                        <!-- Operational Health Card -->
                        <article class="idlm-card idlm-operational-health">
                            <h3>Operational Health</h3>
                            <?php
                            $health_status = isset($operational_health['status']) ? $operational_health['status'] : 'critical';
                            $health_label = 'healthy' === $health_status ? 'Healthy' : ('warning' === $health_status ? 'Warning' : 'Critical');
                            $health_class = 'healthy' === $health_status ? 'is-success' : ('warning' === $health_status ? 'is-warning' : 'is-danger');
                            ?>
                            <p class="idlm-card-desc">
                                Current aggregate status:
                                <strong class="idlm-status-text <?php echo esc_attr($health_class); ?>"><?php echo esc_html($health_label); ?></strong>.
                                Warning thresholds begin at a 5-minute queue age or one recent fault; critical thresholds begin at 15 minutes or ten recent faults.
                            </p>

                            <div class="idlm-health-grid">
                                <?php foreach (($operational_health['checks'] ?? array()) as $check): ?>
                                    <?php
                                    $check_status = isset($check['status']) ? $check['status'] : 'critical';
                                    $status_class = 'healthy' === $check_status ? 'is-success' : ('warning' === $check_status ? 'is-warning' : ('info' === $check_status ? 'is-muted' : 'is-danger'));
                                    ?>
                                    <div class="idlm-health-item idlm-operational-check" title="<?php echo esc_attr($check['guidance'] ?? ''); ?>">
                                        <span><?php echo esc_html($check['label'] ?? 'Operational check'); ?></span>
                                        <strong class="idlm-status-text <?php echo esc_attr($status_class); ?>"><?php echo esc_html($check['value'] ?? 'Unknown'); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="idlm-card-desc idlm-mt-3">This report is read-only and exposes status, counts, and age only. It never returns credentials, contacts, payloads, hosts, database names, or storage paths.</p>
                        </article>

                        <!-- Horizontal Deployment Readiness Card -->
                        <article class="idlm-card idlm-horizontal-readiness">
                            <h3>Horizontal Deployment Readiness</h3>
                            <?php if ('multi_node' === ($horizontal_readiness['mode'] ?? 'single_node')): ?>
                                <p class="idlm-card-desc">
                                    <?php if ('ready' === ($horizontal_readiness['status'] ?? 'blocked')): ?>
                                        All declared multi-node infrastructure gates are ready. Complete the two-node resilience suite before rollout.
                                    <?php else: ?>
                                        Multi-node mode is blocked by <?php echo esc_html((string) absint($horizontal_readiness['blocking_count'] ?? 0)); ?> unresolved infrastructure gate(s).
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <p class="idlm-card-desc">Single-node mode is active. Review and satisfy these gates before enabling <code>IDLM_MULTI_NODE_MODE</code>.</p>
                            <?php endif; ?>

                            <div class="idlm-health-grid">
                                <?php foreach (($horizontal_readiness['checks'] ?? array()) as $check): ?>
                                    <?php
                                    $check_status = isset($check['status']) ? $check['status'] : 'not_enabled';
                                    $status_label = 'not_enabled' === $check_status ? 'Before multi-node' : ('ready' === $check_status ? 'Ready' : 'Blocked');
                                    $status_class = 'blocked' === $check_status ? 'is-danger' : ('ready' === $check_status ? 'is-success' : 'is-muted');
                                    ?>
                                    <div class="idlm-health-item idlm-horizontal-check" title="<?php echo esc_attr($check['guidance'] ?? ''); ?>">
                                        <span><?php echo esc_html($check['label'] ?? 'Infrastructure check'); ?></span>
                                        <strong class="idlm-status-text <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></strong>
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
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="6" class="idlm-table-empty">No telemetry logs recorded. Your plugin is healthy!</td>
                                        </tr>
                                    <?php else:
                                        foreach ($logs as $log): 
                                            ?>
                                            <tr>
                                                <td class="idlm-table-cell-nowrap idlm-status-text is-muted"><?php echo esc_html($log->created_at); ?></td>
                                                <td class="idlm-table-cell-code"><?php echo esc_html($log->log_type); ?></td>
                                                <td class="idlm-table-cell-nowrap">
                                                    <span class="idlm-pill <?php echo ($log->severity === 'error') ? 'danger' : (($log->severity === 'warning') ? 'warn' : ''); ?>">
                                                        <?php echo esc_html(strtoupper((string) $log->severity)); ?>
                                                    </span>
                                                </td>
                                                <td class="idlm-table-cell-truncate" title="<?php echo esc_attr($log->message); ?>">
                                                    <?php echo esc_html($log->message); ?>
                                                </td>
                                                <td class="idlm-table-cell-code" title="<?php echo esc_attr($log->file . ' (Line ' . $log->line . ')'); ?>">
                                                    <?php echo esc_html($log->file ? basename($log->file) : '-'); ?><?php echo $log->line ? ' (Line ' . intval($log->line) . ')' : ''; ?>
                                                </td>
                                                <td class="idlm-table-action-cell">
                                                    <?php if (!empty($log->stack_trace)): ?>
                                                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-view-trace-btn idlm-btn-compact" data-trace="<?php echo esc_attr($log->stack_trace); ?>" data-message="<?php echo esc_attr($log->message); ?>">
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
