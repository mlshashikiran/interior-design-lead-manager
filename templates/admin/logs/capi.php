<?php
if (!defined('ABSPATH')) {
    exit;
}

$logs = isset($context['logs']) && is_array($context['logs']) ? $context['logs'] : array();
$retry_nonce = isset($context['retry_nonce']) ? (string) $context['retry_nonce'] : '';
?>
        <div class="wrap idlm-wrap">
            <header class="idlm-header">
                <div class="idlm-header-meta">
                    <h1>Conversion API (CAPI) Audit Logs</h1>
                    <p class="idlm-header-subtitle">Debugging dashboards, validation tracking and platform response packages</p>
                </div>
            </header>

            <!-- CAPI Bulk Retry Action Bar -->
            <div class="idlm-bulk-capi-bar" style="display:none; background:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #c99644; padding:12px; border-radius:8px; margin-bottom:15px; justify-content:space-between; align-items:center;">
                <span class="idlm-selected-capi-count" style="font-size:13px; font-weight:600; color:#c99644; font-family:'Inter', sans-serif;">0 logs selected</span>
                <button class="idlm-btn" id="idlm-retry-capi-btn" style="background:#c99644; color:#fff; border:none; padding:6px 15px; border-radius:4px; font-size:12px; cursor:pointer;">Retry/Send Selected Events</button>
            </div>

            <div class="idlm-table-wrapper">
                <table class="idlm-table">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;"><input type="checkbox" id="idlm-select-all-capi"></th>
                            <th>Timestamp</th>
                            <th>Lead Email</th>
                            <th>Platform</th>
                            <th>Event Name</th>
                            <th>Status</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="7" style="text-align:center;">No CAPI logs recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td style="text-align: center;"><input type="checkbox" class="idlm-capi-log-selector" value="<?php echo esc_attr($log->id); ?>"></td>
                                    <td><?php echo esc_html(IDLM_Admin::format_datetime($log->created_at)); ?></td>
                                    <td><?php echo esc_html($log->email ? $log->email : 'N/A'); ?></td>
                                    <td><span class="idlm-badge idlm-badge-<?php echo esc_attr($log->platform); ?>"><?php echo esc_html(ucfirst($log->platform)); ?></span></td>
                                    <td><code><?php echo esc_html($log->event_name); ?></code></td>
                                    <td>
                                        <span class="idlm-badge idlm-badge-<?php echo ($log->status === 'success') ? 'success' : 'failed'; ?>">
                                            <?php echo esc_html(strtoupper((string) $log->status)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="idlm-btn idlm-btn-secondary" onclick="viewLogPayload(<?php echo esc_attr($log->id); ?>)">Inspect JSON</button>
                                        <div id="log-details-<?php echo esc_attr($log->id); ?>" style="display:none; margin-top: 10px;">
                                            <h5>Request Payload:</h5>
                                            <pre class="idlm-code-block"><?php echo esc_html(wp_json_encode(maybe_unserialize($log->payload), JSON_PRETTY_PRINT)); ?></pre>
                                            <h5>Platform Response:</h5>
                                            <pre class="idlm-code-block"><?php echo esc_html($log->response); ?></pre>
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
        function viewLogPayload(id) {
            var el = document.getElementById('log-details-' + id);
            if (el.style.display === 'none') {
                el.style.display = 'block';
            } else {
                el.style.display = 'none';
            }
        }

        jQuery(document).ready(function($) {
            // Select all checkbox handler
            $('#idlm-select-all-capi').on('change', function() {
                var checked = $(this).prop('checked');
                $('.idlm-capi-log-selector').prop('checked', checked).trigger('change');
            });

            // Single checkbox handler
            $(document).on('change', '.idlm-capi-log-selector', function() {
                var selectedCount = $('.idlm-capi-log-selector:checked').length;
                if (selectedCount > 0) {
                    $('.idlm-selected-capi-count').text(selectedCount + ' log' + (selectedCount > 1 ? 's' : '') + ' selected');
                    $('.idlm-bulk-capi-bar').css('display', 'flex');
                } else {
                    $('.idlm-bulk-capi-bar').hide();
                    $('#idlm-select-all-capi').prop('checked', false);
                }
            });

            // Retry action click handler
            $('#idlm-retry-capi-btn').on('click', function() {
                var selectedIds = [];
                $('.idlm-capi-log-selector:checked').each(function() {
                    selectedIds.push($(this).val());
                });

                if (selectedIds.length === 0) {
                    alert('Please select at least one log to retry.');
                    return;
                }

                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('Retrying...').prop('disabled', true);

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'idlm_retry_capi_events',
                        nonce: '<?php echo esc_js($retry_nonce); ?>',
                        log_ids: selectedIds
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            location.reload();
                        } else {
                            alert(response.data.message || 'Bulk retry failed.');
                        }
                    },
                    error: function() {
                        alert('Server error occurred during retry.');
                    },
                    complete: function() {
                        $btn.text(originalText).prop('disabled', false);
                    }
                });
            });
        });
        </script>

