<?php
if (!defined('ABSPATH')) {
    exit;
}

$logs = isset($context['logs']) && is_array($context['logs']) ? $context['logs'] : array();
?>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div class="idlm-header-meta">
                    <h1>Audience Batch Sync Logs</h1>
                    <p class="idlm-header-subtitle">Debugging logs, audience segments and sync success statuses</p>
                </div>
            </header>

            <div class="idlm-table-wrapper">
                <table class="idlm-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Platform</th>
                            <th>Audience ID</th>
                            <th>Leads Synced</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" style="text-align:center;">No audience sync logs recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td><?php echo esc_html(IDLM_Admin::format_datetime($log->created_at)); ?></td>
                                    <td><span class="idlm-badge idlm-badge-<?php echo esc_attr($log->platform); ?>"><?php echo esc_html(ucfirst($log->platform)); ?></span></td>
                                    <td><code><?php echo esc_html($log->audience_id); ?></code></td>
                                    <td><?php echo esc_html($log->lead_count); ?> leads</td>
                                    <td>
                                        <span class="idlm-badge idlm-badge-<?php echo ($log->status === 'success') ? 'success' : 'failed'; ?>">
                                            <?php echo esc_html(strtoupper((string) $log->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="idlm-btn idlm-btn-secondary" onclick="viewAudienceLogPayload(<?php echo esc_attr($log->id); ?>)">Inspect JSON</button>
                                        <div id="audience-log-details-<?php echo esc_attr($log->id); ?>" style="display:none; margin-top: 10px;">
                                            <h5>Request Payload:</h5>
                                            <pre class="idlm-code-block"><?php echo esc_html(wp_json_encode(maybe_unserialize($log->payload), JSON_PRETTY_PRINT)); ?></pre>
                                            <h5>Platform Response:</h5>
                                            <pre class="idlm-code-block"><?php echo esc_html(maybe_unserialize($log->response)); ?></pre>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <script>
        function viewAudienceLogPayload(logId) {
            var el = document.getElementById('audience-log-details-' + logId);
            if (el.style.display === 'none') {
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
            }
        }
        </script>

