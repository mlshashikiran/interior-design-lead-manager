<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$stages = isset($context['stages']) ? $context['stages'] : array();
$call_statuses = isset($context['call_statuses']) ? $context['call_statuses'] : array();
?>
        <div class="wrap idlm-wrap idlm-settings-page">
            <script>
            function switchFieldMappingTab(element, tabId) {
                var $element = jQuery(element);
                jQuery('.idlm-mapping-tab-content').removeClass('active');
                jQuery('#' + tabId).addClass('active');
                
                $element.siblings('.idlm-tab-link').removeClass('active');
                $element.addClass('active');
            }
            function switchMcpClientTab(element, tabId) {
                var $element = jQuery(element);
                jQuery('.idlm-mcp-client-tab-content').hide().removeClass('active');
                jQuery('#' + tabId).show().addClass('active');
                
                $element.siblings('.idlm-tab-link').removeClass('active');
                $element.addClass('active');
            }
            function switchSettingsTab(element, tabId) {
                var $element = jQuery(element);
                jQuery('.idlm-settings-tab-content').hide().removeClass('active');
                jQuery('#' + tabId).show().addClass('active');
                
                $element.siblings('.idlm-tab-link').removeClass('active');
                $element.addClass('active');

                // Update the hidden input field value so the tab state is kept when saving
                var tabName = tabId.replace('settings-', '');
                var tabInput = document.getElementById('idlm-active-tab-input');
                if (tabInput) {
                    tabInput.value = tabName;
                }

                // Hide the global Save Settings buttons if on Danger Zone, Help, System Health, or MCP tab
                var saveBtns = document.querySelectorAll('.idlm-save-settings-btn');
                saveBtns.forEach(function(btn) {
                    if (tabName === 'danger-zone' || tabName === 'help' || tabName === 'health' || tabName === 'mcp') {
                        btn.style.display = 'none';
                    } else {
                        btn.style.display = 'inline-block';
                    }
                });

                // Also hide the footer actions container on those tabs
                var footerActions = document.querySelector('.idlm-footer-actions');
                if (footerActions) {
                    if (tabName === 'danger-zone' || tabName === 'help' || tabName === 'health' || tabName === 'mcp') {
                        footerActions.style.display = 'none';
                    } else {
                        footerActions.style.display = 'flex';
                    }
                }

                // Update the window hash to allow direct linking/bookmarking
                if (history.replaceState) {
                    history.replaceState(null, null, '#tab-' + tabName);
                } else {
                    window.location.hash = 'tab-' + tabName;
                }
            }
            </script>
            <div class="idlm-shell">
                <hr class="wp-header-end">
                <header class="idlm-header">
                    <div class="idlm-header-meta">
                        <h1>System Settings & Mappings</h1>
                        <p class="idlm-header-subtitle">Route assignments, Conversion APIs and field definitions</p>
                    </div>
                    <div class="idlm-header-actions">
                        <button type="submit" form="idlm-settings-form" class="idlm-btn idlm-btn-primary idlm-save-settings-btn">Save Settings</button>
                    </div>
                </header>

                <nav class="idlm-tabs" aria-label="System settings tabs">
                    <button type="button" class="idlm-tab-link <?php echo $active_tab === 'general' ? 'active' : ''; ?>" onclick="switchSettingsTab(this, 'settings-general')">General Settings</button>
                    <button type="button" class="idlm-tab-link <?php echo $active_tab === 'routing' ? 'active' : ''; ?>" onclick="switchSettingsTab(this, 'settings-routing')">Lead Routing</button>
                    <button type="button" class="idlm-tab-link <?php echo $active_tab === 'webhooks' ? 'active' : ''; ?>" onclick="switchSettingsTab(this, 'settings-webhooks')">Incoming Webhooks</button>
                    <button type="button" class="idlm-tab-link <?php echo $active_tab === 'capi' ? 'active' : ''; ?>" onclick="switchSettingsTab(this, 'settings-capi')">Conversion APIs (CAPI)</button>
                    <button type="button" class="idlm-tab-link <?php echo $active_tab === 'audience-reminders' ? 'active' : ''; ?>" onclick="switchSettingsTab(this, 'settings-audience-reminders')">Sync & Reminders</button>
                    <button type="button" class="idlm-tab-link <?php echo $active_tab === 'help' ? 'active' : ''; ?>" onclick="switchSettingsTab(this, 'settings-help')">Help & FAQs</button>
                    <button type="button" class="idlm-tab-link <?php echo $active_tab === 'health' ? 'active' : ''; ?>" onclick="switchSettingsTab(this, 'settings-health')">System Health</button>
                    <button type="button" class="idlm-tab-link <?php echo $active_tab === 'mcp' ? 'active' : ''; ?>" onclick="switchSettingsTab(this, 'settings-mcp')">AI Agent Access (MCP)</button>
                    <button type="button" class="idlm-tab-link idlm-tab-link-danger <?php echo $active_tab === 'danger-zone' ? 'active' : ''; ?>" onclick="switchSettingsTab(this, 'settings-danger-zone')" style="color: var(--idlm-danger) !important;">Danger Zone</button>
                </nav>

                <form method="POST" action="" id="idlm-settings-form" class="idlm-settings-form">
                    <?php wp_nonce_field('idlm_settings_save_action'); ?>
                    <input type="hidden" name="idlm_save_settings" value="1">
                    <input type="hidden" name="active_tab" id="idlm-active-tab-input" value="<?php echo esc_attr($active_tab); ?>">

<?php IDLM_View::display('admin/settings/general.php', $context); ?>
<?php IDLM_View::display('admin/settings/routing.php', $context); ?>
<?php IDLM_View::display('admin/settings/webhooks.php', $context); ?>
<?php IDLM_View::display('admin/settings/capi.php', $context); ?>
<?php IDLM_View::display('admin/settings/sync-reminders.php', $context); ?>
<?php IDLM_View::display('admin/settings/health.php', $context); ?>
<?php IDLM_View::display('admin/settings/help.php', $context); ?>
<?php IDLM_View::display('admin/settings/mcp.php', $context); ?>
<?php IDLM_View::display('admin/settings/danger-zone.php', $context); ?>

        </div>
    </div>
        <script>
        jQuery(document).ready(function($) {
            // Auto-activate tab from hash or hidden input
            var hash = window.location.hash;
            var activeTabName = $('#idlm-active-tab-input').val() || 'general';
            if (hash && hash.indexOf('#tab-') === 0) {
                activeTabName = hash.replace('#tab-', '');
            }
            
            // Find tab link and click it
            var tabBtn = $('.idlm-tab-link[onclick*="settings-' + activeTabName + '"]');
            if (tabBtn.length) {
                tabBtn.trigger('click');
            } else {
                $('.idlm-tab-link[onclick*="settings-general"]').first().trigger('click');
            }

            // Clear Telemetry Logs
            $('#idlm-clear-telemetry-btn').on('click', function(e) {
                e.preventDefault();
                if (!confirm('Are you sure you want to permanently delete all error telemetry logs?')) {
                    return;
                }
                var btn = $(this);
                btn.prop('disabled', true).text('Clearing...');
                
                $.post(idlm_vars.ajax_url, {
                    action: 'idlm_clear_telemetry_logs',
                    nonce: idlm_vars.nonce
                }, function(response) {
                    btn.prop('disabled', false).text('Clear Telemetry Logs');
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message || 'Error occurred');
                    }
                }).fail(function() {
                    btn.prop('disabled', false).text('Clear Telemetry Logs');
                    alert('Network error occurred.');
                });
            });

            // View Stack Trace Modal
            $('.idlm-view-trace-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                var trace = btn.attr('data-trace');
                var msg = btn.attr('data-message');
                
                $('#idlm-trace-modal-title').text('Stack Trace: ' + msg);
                $('#idlm-trace-content').text(trace);
                $('#idlm-trace-modal').show();
            });

            // Close Trace Modal
            $('#idlm-trace-close-btn, #idlm-trace-dismiss-btn').on('click', function(e) {
                e.preventDefault();
                $('#idlm-trace-modal').hide();
            });
            $(window).on('click', function(e) {
                if ($(e.target).is('#idlm-trace-modal')) {
                    $('#idlm-trace-modal').hide();
                }
            });

            $('#idlm-assignment-mode-select').change(function() {
                if ($(this).val() === 'source_based') {
                    $('#idlm-source-mapping-subpanel').slideDown();
                } else {
                    $('#idlm-source-mapping-subpanel').slideUp();
                }
            });

            // Dynamic reminders settings rule builder
            $('#idlm-add-reminder-rule').click(function() {
                var statusesHtml = '';
                <?php foreach ($call_statuses as $cs): ?>
                    statusesHtml += '<option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>';
                <?php endforeach; ?>
                
                var rowHtml = '\
                <tr class="idlm-reminder-row">\
                    <td>\
                        <select name="reminder_rules_status[]" class="idlm-select" style="width: 100%;">\
                            ' + statusesHtml + '\
                        </select>\
                    </td>\
                    <td>\
                        <input type="number" name="reminder_rules_hours[]" class="idlm-input" placeholder="Hours" min="1" required style="height: 34px;">\
                    </td>\
                    <td style="text-align: center;">\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-reminder-rule" style="border-color: #ef4444; color: #ef4444; padding: 4px 10px;">Delete</button>\
                    </td>\
                </tr>';
                
                $('#idlm-reminders-container').append(rowHtml);
            });
            
            $(document).on('click', '.idlm-remove-reminder-rule', function() {
                $(this).closest('tr').remove();
            });

            // Dynamic custom audience sync rule builder
            $('#idlm-add-audience-rule').click(function() {
                var stagesHtml = '<option value="all">All Stages</option>';
                <?php foreach ($stages as $stage): ?>
                    stagesHtml += '<option value="<?php echo esc_attr($stage); ?>"><?php echo esc_html($stage); ?></option>';
                <?php endforeach; ?>

                var callStatusesHtml = '<option value="all">All Statuses</option>';
                <?php foreach ($call_statuses as $cs): ?>
                    callStatusesHtml += '<option value="<?php echo esc_attr($cs); ?>"><?php echo esc_html($cs); ?></option>';
                <?php endforeach; ?>

                var rowHtml = '\
                <tr class="idlm-audience-rule-row">\
                    <td>\
                        <select name="audience_rules[platform][]" class="idlm-select" style="width: 100%;">\
                            <option value="meta">Meta Ads</option>\
                            <option value="google">Google Ads</option>\
                            <option value="linkedin">LinkedIn Ads</option>\
                        </select>\
                    </td>\
                    <td>\
                        <input type="text" name="audience_rules[audience_id][]" class="idlm-input" placeholder="e.g. Audience ID or URN" required style="width: 100%; height: 34px;">\
                    </td>\
                    <td>\
                        <select name="audience_rules[is_value][]" class="idlm-select" style="width: 100%;">\
                            <option value="0">No</option>\
                            <option value="1">Yes (Meta Value-Based)</option>\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[stage][]" class="idlm-select" style="width: 100%;">\
                            ' + stagesHtml + '\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[call_status][]" class="idlm-select" style="width: 100%;">\
                            ' + callStatusesHtml + '\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[lead_quality][]" class="idlm-select" style="width: 100%;">\
                            <option value="all">All Qualities</option>\
                            <option value="Qualified">Qualified</option>\
                            <option value="Unqualified">Unqualified</option>\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[enabled][]" class="idlm-select" style="width: 100%;">\
                            <option value="1">Enabled</option>\
                            <option value="0">Disabled</option>\
                        </select>\
                    </td>\
                    <td style="text-align: center;">\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-test-audience-rule" style="border-color: var(--idlm-gold); color: var(--idlm-gold-dark); padding: 4px 10px; margin-right: 5px;">Test</button>\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-audience-rule" style="border-color: #ef4444; color: #ef4444; padding: 4px 10px;">Delete</button>\
                    </td>\
                </tr>';

                $('#idlm-audience-rules-container').append(rowHtml);
            });

            $(document).on('click', '.idlm-remove-audience-rule', function() {
                $(this).closest('tr').remove();
            });

            // Dynamic Interakt Event mapping row builder
            const interaktOptions = {
                call_status: [
                    <?php foreach ($call_statuses as $cs): ?>
                        '<?php echo esc_js($cs); ?>',
                    <?php endforeach; ?>
                ],
                stage: [
                    <?php foreach ($stages as $stage): ?>
                        '<?php echo esc_js($stage); ?>',
                    <?php endforeach; ?>
                ],
                lead_quality: [
                    'Qualified', 'Pending Quality', 'Unqualified'
                ]
            };

            function updateInteraktValueDropdown($row, selectedVal) {
                const type = $row.find('.interakt-mapping-type-select').val();
                const $valSelect = $row.find('.interakt-mapping-value-select');
                $valSelect.empty();
                
                if (interaktOptions[type]) {
                    interaktOptions[type].forEach(function(opt) {
                        const selectedAttr = (opt === selectedVal) ? 'selected' : '';
                        $valSelect.append('<option value="' + opt + '" ' + selectedAttr + '>' + opt + '</option>');
                    });
                }
            }

            $(document).on('change', '.interakt-mapping-type-select', function() {
                const $row = $(this).closest('tr');
                updateInteraktValueDropdown($row, '');
            });

            // Populate existing values
            $('.idlm-interakt-mapping-row').each(function() {
                const $row = $(this);
                const savedVal = $row.attr('data-saved-val');
                updateInteraktValueDropdown($row, savedVal);
            });

            $('#idlm-add-interakt-mapping').click(function() {
                const rowHtml = '<tr class="idlm-interakt-mapping-row">\
                    <td>\
                        <select name="interakt_mapping_type[]" class="idlm-select interakt-mapping-type-select" style="width: 100%;">\
                            <option value="call_status">Call Status</option>\
                            <option value="stage">Stage</option>\
                            <option value="lead_quality">Lead Quality</option>\
                        </select>\
                    </td>\
                    <td>\
                        <select name="interakt_mapping_value[]" class="idlm-select interakt-mapping-value-select" style="width: 100%;">\
                        </select>\
                    </td>\
                    <td>\
                        <input type="text" name="interakt_mapping_event[]" class="idlm-input" placeholder="e.g. lead_no_answer" required style="width: 100%; height: 34px;">\
                    </td>\
                    <td style="text-align: center;">\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-interakt-mapping" style="border-color: #ef4444; color: #ef4444; padding: 4px 10px;">Delete</button>\
                    </td>\
                </tr>';
                
                const $newRow = $(rowHtml);
                $('#idlm-interakt-mappings-container').append($newRow);
                updateInteraktValueDropdown($newRow, '');
            });

            $(document).on('click', '.idlm-remove-interakt-mapping', function() {
                $(this).closest('tr').remove();
            });

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
                
                var confirmMsg = 'Are you sure you want to sync all past leads where "' + triggerType + '" is "' + triggerValue + '" to Interakt for event "' + eventName + '"?';
                if (!confirm(confirmMsg)) {
                    return;
                }
                
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
                        alert(res.data || 'Failed to fetch leads');
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
                    var failureCount = 0;
                    var batchSize = 10;
                    
                    function processNextBatch() {
                        if (leadIds.length === 0) {
                            $statusText.html('<strong>Sync completed!</strong> ' + successCount + ' succeeded, ' + failureCount + ' failed.');
                            resetSyncBtn();
                            return;
                        }
                        
                        var currentBatch = leadIds.splice(0, batchSize);
                        $statusText.text('Syncing batch of ' + currentBatch.length + ' leads (' + (processed + 1) + '-' + Math.min(processed + batchSize, total) + ' of ' + total + ')...');
                        
                        $.post(ajaxurl, {
                            action: 'idlm_sync_mapped_past_batch',
                            lead_ids: currentBatch,
                            event_name: eventName,
                            nonce: '<?php echo esc_js(wp_create_nonce("idlm_admin_nonce")); ?>'
                        }, function(batchRes) {
                            if (batchRes.success) {
                                successCount += batchRes.data.success_count || 0;
                                failureCount += batchRes.data.failure_count || 0;
                            } else {
                                failureCount += currentBatch.length;
                            }
                            
                            processed += currentBatch.length;
                            var pct = Math.round((processed / total) * 100);
                            $progressBar.css('width', pct + '%');
                            
                            // Proceed to next batch (internal 250ms sleep rate limits requests)
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
                    alert('An error occurred during sync initialization.');
                    resetSyncBtn();
                });
                
                function resetSyncBtn() {
                    $btn.prop('disabled', false).text('Sync Past');
                }
            });
        });
        </script>
