<?php
if (!defined('ABSPATH')) {
    exit;
}
// Script fragment included inside the settings shell's jQuery ready handler.
?>
            // Bulk sync existing leads matching a specific mapping
            $(document).on('click', '.idlm-sync-past-interakt-mapping', function(e) {
                e.preventDefault();
                
                var $btn = $(this);
                var triggerType = $btn.attr('data-type');
                var triggerValue = $btn.attr('data-value');
                var eventName = $btn.attr('data-event');
                
                var $progressContainer = $('#idlm-bulk-sync-progress-container');
                var $progressBar = $('#idlm-bulk-sync-progress-bar');
                var $statusText = $('#idlm-bulk-sync-status-text');
                
                var confirmMsg = 'This queues the "' + eventName + '" event to Interakt for every past lead where ' + triggerType + ' is "' + triggerValue + '". Delivery results appear in each lead\u2019s activity.';
                if (!window.IDLMDialog) {
                    return;
                }
                window.IDLMDialog.confirm({
                    title: 'Sync past leads to Interakt?',
                    message: confirmMsg,
                    confirmLabel: 'Queue sync',
                    trigger: $btn.get(0)
                }).then(function(confirmed) {
                    if (confirmed) {
                        startPastSync();
                    }
                });

                function startPastSync() {
                $btn.prop('disabled', true).text('Syncing...');
                $progressContainer.show();
                $progressBar.css('width', '0%');
                $statusText.text('Fetching matching leads...');
                
                $.post(ajaxurl, {
                    action: 'idlm_get_mapped_past_leads',
                    trigger_type: triggerType,
                    trigger_value: triggerValue,
                    nonce: '<?php echo esc_js(wp_create_nonce("idlm_admin_nonce")); ?>'
                }, function(res) {
                    if (!res.success) {
                        window.IDLMDialog.notify((res.data && res.data.message) || 'Failed to fetch leads', 'error');
                        resetSyncBtn();
                        return;
                    }
                    
                    var leadIds = res.data.lead_ids || [];
                    if (leadIds.length === 0) {
                        $statusText.text('No matching leads found to sync.');
                        resetSyncBtn();
                        return;
                    }
                    
                    $statusText.text('Found ' + leadIds.length + ' leads. Starting sync...');
                    
                    var total = leadIds.length;
                    var processed = 0;
                    var successCount = 0;
                    var existingCount = 0;
                    var requestId = 'sync_' + Date.now() + '_' + Math.random().toString(36).slice(2);
                    var failureCount = 0;
                    var batchSize = 10;
                    
                    function processNextBatch() {
                        if (leadIds.length === 0) {
                            $statusText.text('Queue complete: ' + successCount + ' queued, ' + existingCount + ' already submitted, ' + failureCount + ' failed to queue. Delivery results appear in each lead’s activity.');
                            resetSyncBtn();
                            return;
                        }
                        
                        var currentBatch = leadIds.splice(0, batchSize);
                        $statusText.text('Syncing batch of ' + currentBatch.length + ' leads (' + (processed + 1) + '-' + Math.min(processed + batchSize, total) + ' of ' + total + ')...');
                        
                        $.post(ajaxurl, {
                            action: 'idlm_sync_mapped_past_batch',
                            lead_ids: currentBatch,
                            event_name: eventName,
                            request_id: requestId,
                            nonce: '<?php echo esc_js(wp_create_nonce("idlm_admin_nonce")); ?>'
                        }, function(batchRes) {
                            if (batchRes.success) {
                                successCount += batchRes.data.queued_count || 0;
                                existingCount += batchRes.data.existing_count || 0;
                                failureCount += batchRes.data.failure_count || 0;
                            } else {
                                failureCount += currentBatch.length;
                            }
                            
                            processed += currentBatch.length;
                            var pct = Math.round((processed / total) * 100);
                            $progressBar.css('width', pct + '%');
                            
                            // HTTP requests only queue delivery; background jobs send the events.
                            processNextBatch();
                        }).fail(function() {
                            failureCount += currentBatch.length;
                            processed += currentBatch.length;
                            var pct = Math.round((processed / total) * 100);
                            $progressBar.css('width', pct + '%');
                            processNextBatch();
                        });
                    }
                    
                    processNextBatch();
                    
                }).fail(function() {
                    window.IDLMDialog.notify('An error occurred during sync initialization.', 'error');
                    resetSyncBtn();
                });
                
                function resetSyncBtn() {
                    $btn.prop('disabled', false).text('Sync Past');
                }
                }
            });
