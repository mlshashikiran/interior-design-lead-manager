<?php
if (!defined('ABSPATH')) {
    exit;
}
$idlm_active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$idlm_stages = isset($context['stages']) ? $context['stages'] : array();
$idlm_call_statuses = isset($context['call_statuses']) ? $context['call_statuses'] : array();
$idlm_settings_saved = !empty($context['settings_saved']);
$idlm_return_url = remove_query_arg('idlm_credential_rotated');
?>
        <div class="wrap idlm-wrap idlm-settings-page">
            <script>
            function switchFieldMappingTab(element, tabId) {
                var $element = jQuery(element); jQuery('.idlm-mapping-tab-content').prop('hidden', true).removeClass('active'); jQuery('#' + tabId).prop('hidden', false).addClass('active');
                $element.siblings('[role="tab"]').removeClass('active').attr({'aria-selected': 'false', 'tabindex': '-1'}); $element.addClass('active').attr({'aria-selected': 'true', 'tabindex': '0'});
            }
            function switchMcpClientTab(element, tabId) {
                var $element = jQuery(element);
                jQuery('.idlm-mcp-client-tab-content').prop('hidden', true).removeClass('active');
                jQuery('#' + tabId).prop('hidden', false).addClass('active');
                $element.siblings('.idlm-tab-link').removeClass('active').attr({'aria-selected': 'false', 'tabindex': '-1'});
                $element.addClass('active').attr({'aria-selected': 'true', 'tabindex': '0'});
            }
            function switchSettingsTab(element, tabId) {
                var $element = jQuery(element);
                jQuery('.idlm-settings-tab-content').prop('hidden', true).removeClass('active');
                jQuery('#' + tabId).prop('hidden', false).addClass('active');
                $element.siblings('.idlm-tab-link').removeClass('active').attr({'aria-selected': 'false', 'tabindex': '-1'});
                $element.addClass('active').attr({'aria-selected': 'true', 'tabindex': '0'});

                // Update the hidden input field value so the tab state is kept when saving
                var tabName = tabId.replace('settings-', '');
                var tabInput = document.getElementById('idlm-active-tab-input');
                if (tabInput) {
                    tabInput.value = tabName;
                }
                // Hide save actions only on tabs without editable settings.
                var saveBtns = document.querySelectorAll('.idlm-save-settings-btn');
                saveBtns.forEach(function(btn) {
                    btn.hidden = tabName === 'backup' || tabName === 'danger-zone' || tabName === 'help' || tabName === 'mcp';
                });
                // Also hide the footer actions container on those tabs
                var footerActions = document.querySelector('.idlm-footer-actions');
                if (footerActions) {
                    footerActions.hidden = tabName === 'backup' || tabName === 'danger-zone' || tabName === 'help' || tabName === 'mcp';
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
                <?php if ($idlm_settings_saved): ?>
                    <div class="notice notice-success is-dismissible idlm-settings-save-notice" role="status" tabindex="-1" data-idlm-settings-saved data-idlm-settings-toast data-idlm-toast-autoclose="4000">
                        <p>Settings saved successfully!</p>
                    </div>
                <?php endif; ?>
                <header class="idlm-header">
                    <div class="idlm-header-meta">
                        <h1>System settings and mappings</h1>
                    </div>
                </header>
                <nav class="idlm-tabs idlm-settings-tabs" role="tablist" aria-label="System settings tabs">
                    <button type="button" id="idlm-settings-tab-general" class="idlm-tab-link <?php echo $idlm_active_tab === 'general' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'general' ? 'true' : 'false'; ?>" aria-controls="settings-general" tabindex="<?php echo $idlm_active_tab === 'general' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-general')">General</button>
                    <button type="button" id="idlm-settings-tab-white-label" class="idlm-tab-link <?php echo $idlm_active_tab === 'white-label' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'white-label' ? 'true' : 'false'; ?>" aria-controls="settings-white-label" tabindex="<?php echo $idlm_active_tab === 'white-label' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-white-label')">White label</button>
                    <button type="button" id="idlm-settings-tab-routing" class="idlm-tab-link <?php echo $idlm_active_tab === 'routing' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'routing' ? 'true' : 'false'; ?>" aria-controls="settings-routing" tabindex="<?php echo $idlm_active_tab === 'routing' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-routing')">Routing</button>
                    <button type="button" id="idlm-settings-tab-webhooks" class="idlm-tab-link <?php echo $idlm_active_tab === 'webhooks' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'webhooks' ? 'true' : 'false'; ?>" aria-controls="settings-webhooks" tabindex="<?php echo $idlm_active_tab === 'webhooks' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-webhooks')">Lead capture</button>
                    <button type="button" id="idlm-settings-tab-capi" class="idlm-tab-link <?php echo $idlm_active_tab === 'capi' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'capi' ? 'true' : 'false'; ?>" aria-controls="settings-capi" tabindex="<?php echo $idlm_active_tab === 'capi' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-capi')">Conversion tracking</button>
                    <button type="button" id="idlm-settings-tab-audience-reminders" class="idlm-tab-link <?php echo $idlm_active_tab === 'audience-reminders' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'audience-reminders' ? 'true' : 'false'; ?>" aria-controls="settings-audience-reminders" tabindex="<?php echo $idlm_active_tab === 'audience-reminders' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-audience-reminders')">Automation</button>
                    <button type="button" id="idlm-settings-tab-health" class="idlm-tab-link <?php echo $idlm_active_tab === 'health' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'health' ? 'true' : 'false'; ?>" aria-controls="settings-health" tabindex="<?php echo $idlm_active_tab === 'health' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-health')">Health</button>
                    <button type="button" id="idlm-settings-tab-help" class="idlm-tab-link <?php echo $idlm_active_tab === 'help' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'help' ? 'true' : 'false'; ?>" aria-controls="settings-help" tabindex="<?php echo $idlm_active_tab === 'help' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-help')">Help</button>
                    <button type="button" id="idlm-settings-tab-mcp" class="idlm-tab-link <?php echo $idlm_active_tab === 'mcp' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'mcp' ? 'true' : 'false'; ?>" aria-controls="settings-mcp" tabindex="<?php echo $idlm_active_tab === 'mcp' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-mcp')">AI access</button>
                    <button type="button" id="idlm-settings-tab-backup" class="idlm-tab-link <?php echo $idlm_active_tab === 'backup' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'backup' ? 'true' : 'false'; ?>" aria-controls="settings-backup" tabindex="<?php echo $idlm_active_tab === 'backup' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-backup')">Backup &amp; restore</button>
                    <button type="button" id="idlm-settings-tab-danger-zone" class="idlm-tab-link idlm-tab-link-danger <?php echo $idlm_active_tab === 'danger-zone' ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo $idlm_active_tab === 'danger-zone' ? 'true' : 'false'; ?>" aria-controls="settings-danger-zone" tabindex="<?php echo $idlm_active_tab === 'danger-zone' ? '0' : '-1'; ?>" onclick="switchSettingsTab(this, 'settings-danger-zone')">Danger Zone</button>
                </nav>
                <form method="POST" action="" id="idlm-settings-form" class="idlm-settings-form">
                    <?php wp_nonce_field('idlm_settings_save_action'); ?>
                    <input type="hidden" name="idlm_save_settings" value="1">
                    <input type="hidden" name="active_tab" id="idlm-active-tab-input" value="<?php echo esc_attr($idlm_active_tab); ?>">
<?php IDLM_View::display('admin/settings/general.php', $context); ?>
<?php IDLM_View::display('admin/settings/white-label.php', $context); ?>
<?php IDLM_View::display('admin/settings/routing.php', $context); ?>
<?php IDLM_View::display('admin/settings/webhooks.php', $context); ?>
<?php IDLM_View::display('admin/settings/capi.php', $context); ?>
<?php IDLM_View::display('admin/settings/sync-reminders.php', $context); ?>
<?php IDLM_View::display('admin/settings/health.php', $context); ?>
<?php IDLM_View::display('admin/settings/help.php', $context); ?>
<?php IDLM_View::display('admin/settings/mcp.php', $context); ?>
<?php IDLM_View::display('admin/settings/backup.php', $context); ?>
<?php IDLM_View::display('admin/settings/danger-zone.php', $context); ?>
                    <footer class="idlm-footer-actions idlm-settings-savebar" aria-label="Settings save actions">
                        <p class="idlm-settings-savebar__status" role="status" aria-live="polite" data-idlm-save-status><?php echo $idlm_settings_saved ? 'Settings saved' : 'No unsaved changes'; ?></p>
                        <button type="submit" form="idlm-settings-form" class="idlm-btn idlm-btn-primary idlm-save-settings-btn" aria-busy="false"><span data-idlm-save-label>Save settings</span></button>
                    </footer>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="idlm-rotate-webhook-form">
                    <input type="hidden" name="action" value="idlm_rotate_api_credential">
                    <input type="hidden" name="scope" value="webhook">
                    <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>">
                    <?php wp_nonce_field('idlm_rotate_api_credential_webhook'); ?>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="idlm-rotate-mcp-form">
                    <input type="hidden" name="action" value="idlm_rotate_api_credential">
                    <input type="hidden" name="scope" value="mcp">
                    <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>">
                    <?php wp_nonce_field('idlm_rotate_api_credential_mcp'); ?>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="idlm-export-backup-form">
                    <input type="hidden" name="action" value="idlm_export_backup">
                    <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>">
                    <?php wp_nonce_field('idlm_export_backup'); ?>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="idlm-restore-backup-form" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="idlm_restore_backup">
                    <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>">
                    <?php wp_nonce_field('idlm_restore_backup'); ?>
                </form>
        </div>
    </div>
        <script>
        jQuery(document).ready(function($) {
            $('.idlm-settings-tabs').on('keydown', '[role="tab"]', function(event) {
                var $tabs = $('.idlm-settings-tabs [role="tab"]');
                var index = $tabs.index(this);
                if (event.key === 'ArrowRight') index = (index + 1) % $tabs.length;
                else if (event.key === 'ArrowLeft') index = (index - 1 + $tabs.length) % $tabs.length;
                else if (event.key === 'Home') index = 0;
                else if (event.key === 'End') index = $tabs.length - 1;
                else return;
                event.preventDefault();
                $tabs.eq(index).trigger('click').trigger('focus');
            });
            $('.idlm-mcp-tabs').on('keydown', '[role="tab"]', function(event) {
                var $tabs = $('.idlm-mcp-tabs [role="tab"]');
                var index = $tabs.index(this);
                if (event.key === 'ArrowRight') index = (index + 1) % $tabs.length;
                else if (event.key === 'ArrowLeft') index = (index - 1 + $tabs.length) % $tabs.length;
                else if (event.key === 'Home') index = 0;
                else if (event.key === 'End') index = $tabs.length - 1;
                else return;
                event.preventDefault();
                $tabs.eq(index).trigger('click').trigger('focus');
            });
            $('.idlm-mapping-tabs').on('keydown', '[role="tab"]', function(event) {
                var $tabs = $('.idlm-mapping-tabs [role="tab"]'), index = $tabs.index(this);
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') index = (index + 1) % $tabs.length;
                else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') index = (index - 1 + $tabs.length) % $tabs.length;
                else if (event.key === 'Home') index = 0; else if (event.key === 'End') index = $tabs.length - 1; else return;
                event.preventDefault(); $tabs.eq(index).trigger('click').trigger('focus');
            });
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
                if (!window.IDLMDialog || !window.IDLMDialog.confirm('Are you sure you want to permanently delete all error telemetry logs?')) {
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
                        window.IDLMDialog.notify(response.data.message, 'success');
                        location.reload();
                    } else {
                        window.IDLMDialog.notify(response.data.message || 'Error occurred', 'error');
                    }
                }).fail(function() {
                    btn.prop('disabled', false).text('Clear Telemetry Logs');
                    window.IDLMDialog.notify('Network error occurred.', 'error');
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
                if (window.IDLMDialog) {
                    window.IDLMDialog.open(document.getElementById('idlm-trace-modal'), {trigger: btn.get(0)});
                }
            });

            // Close Trace Modal
            $('#idlm-trace-close-btn, #idlm-trace-dismiss-btn').on('click', function(e) {
                e.preventDefault();
                if (window.IDLMDialog) {
                    window.IDLMDialog.close(document.getElementById('idlm-trace-modal'));
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
                <?php foreach ($idlm_call_statuses as $idlm_cs): ?>
                    statusesHtml += '<option value="<?php echo esc_attr($idlm_cs); ?>"><?php echo esc_html($idlm_cs); ?></option>';
                <?php endforeach; ?>
                
                var rowHtml = '\
                <tr class="idlm-reminder-row">\
                    <td>\
                        <select name="reminder_rules_status[]" class="idlm-select">\
                            ' + statusesHtml + '\
                        </select>\
                    </td>\
                    <td>\
                        <input type="number" name="reminder_rules_hours[]" class="idlm-input" placeholder="Hours" min="1" required>\
                    </td>\
                    <td>\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-reminder-rule">Delete</button>\
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
                <?php foreach ($idlm_stages as $idlm_stage): ?>
                    stagesHtml += '<option value="<?php echo esc_attr($idlm_stage); ?>"><?php echo esc_html($idlm_stage); ?></option>';
                <?php endforeach; ?>

                var callStatusesHtml = '<option value="all">All Statuses</option>';
                <?php foreach ($idlm_call_statuses as $idlm_cs): ?>
                    callStatusesHtml += '<option value="<?php echo esc_attr($idlm_cs); ?>"><?php echo esc_html($idlm_cs); ?></option>';
                <?php endforeach; ?>

                var rowHtml = '\
                <tr class="idlm-audience-rule-row">\
                    <td>\
                        <select name="audience_rules[platform][]" class="idlm-select">\
                            <option value="meta">Meta Ads</option>\
                            <option value="google">Google Ads</option>\
                            <option value="linkedin">LinkedIn Ads</option>\
                        </select>\
                    </td>\
                    <td>\
                        <input type="text" name="audience_rules[audience_id][]" class="idlm-input" placeholder="e.g. Audience ID or URN" required>\
                    </td>\
                    <td>\
                        <select name="audience_rules[is_value][]" class="idlm-select">\
                            <option value="0">No</option>\
                            <option value="1">Yes (Meta Value-Based)</option>\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[stage][]" class="idlm-select">\
                            ' + stagesHtml + '\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[call_status][]" class="idlm-select">\
                            ' + callStatusesHtml + '\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[lead_quality][]" class="idlm-select">\
                            <option value="all">All Qualities</option>\
                            <option value="Qualified">Qualified</option>\
                            <option value="Unqualified">Unqualified</option>\
                        </select>\
                    </td>\
                    <td>\
                        <select name="audience_rules[enabled][]" class="idlm-select">\
                            <option value="1">Enabled</option>\
                            <option value="0">Disabled</option>\
                        </select>\
                    </td>\
                    <td>\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-test-audience-rule">Test</button>\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-audience-rule">Delete</button>\
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
                    <?php foreach ($idlm_call_statuses as $idlm_cs): ?>
                        '<?php echo esc_js($idlm_cs); ?>',
                    <?php endforeach; ?>
                ],
                stage: [
                    <?php foreach ($idlm_stages as $idlm_stage): ?>
                        '<?php echo esc_js($idlm_stage); ?>',
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
                        <select name="interakt_mapping_type[]" class="idlm-select interakt-mapping-type-select">\
                            <option value="call_status">Call Status</option>\
                            <option value="stage">Stage</option>\
                            <option value="lead_quality">Lead Quality</option>\
                        </select>\
                    </td>\
                    <td>\
                        <select name="interakt_mapping_value[]" class="idlm-select interakt-mapping-value-select">\
                        </select>\
                    </td>\
                    <td>\
                        <input type="text" name="interakt_mapping_event[]" class="idlm-input" placeholder="e.g. lead_no_answer" required>\
                    </td>\
                    <td>\
                        <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-interakt-mapping">Delete</button>\
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
                if (!window.IDLMDialog || !window.IDLMDialog.confirm(confirmMsg)) {
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
                        window.IDLMDialog.notify(res.data || 'Failed to fetch leads', 'error');
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
            });
        });
        </script>
