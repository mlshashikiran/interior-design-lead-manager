<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$audience_rules = isset($context['audience_rules']) ? $context['audience_rules'] : null;
$stages = isset($context['stages']) ? $context['stages'] : null;
$call_statuses = isset($context['call_statuses']) ? $context['call_statuses'] : null;
$google_gadm_username = isset($context['google_gadm_username']) ? $context['google_gadm_username'] : null;
$google_gadm_password = IDLM_Admin_Settings_Renderer::credential_value('google_gadm_password');
$reminder_rules = isset($context['reminder_rules']) ? $context['reminder_rules'] : null;
$interakt_enabled = isset($context['interakt_enabled']) ? $context['interakt_enabled'] : null;
$interakt_api_key = IDLM_Admin_Settings_Renderer::credential_value('interakt_api_key');
$interakt_webhook_secret = IDLM_Admin_Settings_Renderer::credential_value('interakt_webhook_secret');
$meta_webhook_enabled = isset($context['meta_webhook_enabled']) ? $context['meta_webhook_enabled'] : null;
$meta_webhook_verify_token = IDLM_Admin_Settings_Renderer::credential_value('meta_webhook_verify_token');
$meta_webhook_app_secret = IDLM_Admin_Settings_Renderer::credential_value('meta_webhook_app_secret');
$interakt_mappings = isset($context['interakt_mappings']) ? $context['interakt_mappings'] : array();
?>
                <!-- Tab 5: Sync & Reminders -->
                <section id="settings-audience-reminders" class="idlm-settings-tab-content <?php echo $active_tab === 'audience-reminders' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-audience-reminders" <?php echo $active_tab === 'audience-reminders' ? '' : 'hidden'; ?>>
                    <div class="idlm-grid-full">
                        <!-- Custom Audience Sync card -->
                        <article class="idlm-card">
                            <h3>Custom Audience Batch Sync Rules</h3>
                            <p class="idlm-card-desc">Configure multiple Custom Audience batch sync jobs to run daily at 2:00 AM. You can target specific segments by choosing custom pipeline stages, call statuses, or lead qualities for each platform's destination list.</p>
                            
                            <table class="idlm-table" id="idlm-audience-rules-table" data-idlm-ui="w-full">
                                <thead>
                                    <tr>
                                        <th>Platform</th>
                                        <th>Audience / Segment ID</th>
                                        <th>Value-Based?</th>
                                        <th>Stage Filter</th>
                                        <th>Call Status Filter</th>
                                        <th>Lead Quality Filter</th>
                                        <th data-idlm-ui="col-120">Status</th>
                                        <th data-idlm-ui="col-140 center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="idlm-audience-rules-container">
                                    <?php foreach ($audience_rules as $rule_idx => $rule): ?>
                                        <tr class="idlm-audience-rule-row">
                                            <td>
                                                <select name="audience_rules[platform][]" class="idlm-select idlm-control-full">
                                                    <option value="meta" <?php selected($rule['platform'], 'meta'); ?>>Meta Ads</option>
                                                    <option value="google" <?php selected($rule['platform'], 'google'); ?>>Google Ads</option>
                                                    <option value="linkedin" <?php selected($rule['platform'], 'linkedin'); ?>>LinkedIn Ads</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="audience_rules[audience_id][]" class="idlm-input" value="<?php echo esc_attr($rule['audience_id']); ?>" placeholder="e.g. User List ID (or URN)" required data-idlm-ui="control-full">
                                                <?php if ($rule['platform'] === 'google' && !empty($rule['audience_id'])): ?>
                                                    <div data-idlm-ui="mt-1">
                                                        <a href="<?php echo esc_url(site_url('/wp-json/interior-design-lead-manager/v1/google-ads-audience/' . urlencode($rule['audience_id']) . '.csv')); ?>" target="_blank" class="idlm-text-link">Feed URL</a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <select name="audience_rules[is_value][]" class="idlm-select idlm-control-full">
                                                    <option value="0" <?php selected(isset($rule['is_value']) ? $rule['is_value'] : '0', '0'); ?>>No</option>
                                                    <option value="1" <?php selected(isset($rule['is_value']) ? $rule['is_value'] : '0', '1'); ?>>Yes (Meta Value-Based)</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="audience_rules[stage][]" class="idlm-select idlm-control-full">
                                                    <option value="all">All Stages</option>
                                                    <?php foreach ($stages as $stage): ?>
                                                        <option value="<?php echo esc_attr($stage); ?>" <?php selected($rule['stage'], $stage); ?>><?php echo esc_html($stage); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="audience_rules[call_status][]" class="idlm-select idlm-control-full">
                                                    <option value="all">All Statuses</option>
                                                    <?php foreach ($call_statuses as $cs): ?>
                                                        <option value="<?php echo esc_attr($cs); ?>" <?php selected($rule['call_status'], $cs); ?>><?php echo esc_html($cs); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="audience_rules[lead_quality][]" class="idlm-select idlm-control-full">
                                                    <option value="all" <?php selected($rule['lead_quality'], 'all'); ?>>All Qualities</option>
                                                    <option value="Qualified" <?php selected($rule['lead_quality'], 'Qualified'); ?>>Qualified</option>
                                                    <option value="Unqualified" <?php selected($rule['lead_quality'], 'Unqualified'); ?>>Unqualified</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="audience_rules[enabled][]" class="idlm-select idlm-control-full">
                                                    <option value="1" <?php selected($rule['enabled'], '1'); ?>>Enabled</option>
                                                    <option value="0" <?php selected($rule['enabled'], '0'); ?>>Disabled</option>
                                                </select>
                                            </td>
                                            <td data-idlm-ui="center">
                                                <button type="button" class="idlm-btn idlm-btn-secondary idlm-test-audience-rule" data-idlm-ui="action-outline compact-button">Test</button>
                                                <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-audience-rule idlm-btn-compact idlm-btn-danger-outline">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div class="idlm-table-actions">
                                <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-add-audience-rule">+ Add Sync Rule</button>
                            </div>
                        </article>

                        <!-- Google Ads Data Manager HTTPS Pull Feeds card -->
                        <article class="idlm-card idlm-gadm-feed-card">
                            <h3>Google Ads Data Manager (HTTPS Pull Feeds)</h3>
                            <p class="idlm-card-desc">Instead of complex API integrations, Google Ads Data Manager pulls conversion data and customer match segments from secure HTTPS feeds on your site. Configure the Basic Auth credentials below, then map these URLs in your Google Ads Data Manager. <button type="button" class="idlm-action-link" onclick="switchSettingsTab(document.querySelector('.idlm-tab-link[onclick*=\'settings-help\']'), 'settings-help'); setTimeout(function(){ document.getElementById('idlm-help-google-ads-creds').scrollIntoView({behavior: 'smooth'}); }, 100);">Read step-by-step instructions to link sources</button>.</p>
                            
                            <div class="idlm-form-grid two" data-idlm-ui="mt-4">
                                <div class="idlm-form-group">
                                    <label for="idlm-google-gadm-username">HTTPS source username</label>
                                    <input type="text" id="idlm-google-gadm-username" name="google_gadm_username" class="idlm-input" autocomplete="username" value="<?php echo esc_attr($google_gadm_username); ?>" required>
                                </div>
                                <div class="idlm-form-group">
                                    <label for="idlm-google-gadm-password">HTTPS source password or token</label>
                                    <input type="password" id="idlm-google-gadm-password" name="google_gadm_password" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($google_gadm_password); ?>" required>
                                </div>
                            </div>

                            <div class="idlm-form-group" data-idlm-ui="mt-4">
                                <label for="idlm-conversions-feed-url">Google Ads offline conversions feed URL</label>
                                <div data-idlm-ui="flex">
                                    <input type="text" class="idlm-input idlm-flex-grow idlm-readonly-control" readonly value="<?php echo esc_url(site_url('/wp-json/interior-design-lead-manager/v1/google-ads-conversions.csv')); ?>" id="idlm-conversions-feed-url">
                                    <button type="button" class="idlm-btn idlm-btn-secondary" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-conversions-feed-url').value)">Copy URL</button>
                                </div>
                                <p class="idlm-settings-help">Use this URL for importing Offline Conversions in Google Ads Data Manager.</p>
                            </div>
                        </article>

                        <!-- Follow-up Reminders card -->
                        <article class="idlm-card">
                            <h3>Followup Reminder Settings</h3>
                            <p class="idlm-card-desc">Configure different reminder intervals (in hours) for various Call Statuses. When a lead's call status remains in one of these statuses longer than the specified hours, it will be highlighted as "Overdue" in the leads list.</p>
                            
                            <table class="idlm-table idlm-control-full">
                                <thead>
                                    <tr>
                                        <th>Call Status</th>
                                        <th>Time Interval (Hours)</th>
                                        <th data-idlm-ui="col-120 center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="idlm-reminders-container">
                                    <?php foreach ($reminder_rules as $rule): ?>
                                        <tr class="idlm-reminder-row">
                                            <td>
                                                <select name="reminder_rules_status[]" class="idlm-select" aria-label="Call status for reminder rule" data-idlm-ui="w-full">
                                                    <?php foreach ($call_statuses as $cs): ?>
                                                        <option value="<?php echo esc_attr($cs); ?>" <?php selected($rule['status'], $cs); ?>><?php echo esc_html($cs); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="reminder_rules_hours[]" class="idlm-input" aria-label="Reminder interval in hours" inputmode="numeric" value="<?php echo esc_attr($rule['hours']); ?>" min="1" required>
                                            </td>
                                            <td data-idlm-ui="center">
                                                <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-reminder-rule idlm-btn-compact idlm-btn-danger-outline">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <div class="idlm-table-actions">
                                <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-add-reminder-rule">+ Add Reminder Rule</button>
                            </div>
                        </article>

                        <!-- WhatsApp Automation (Interakt) Integration Card -->
                        <article class="idlm-card idlm-automation-integration-card">
                            <div class="idlm-settings-card-head idlm-automation-card-head">
                                <label class="idlm-settings-toggle">
                                    <input type="checkbox" id="idlm-interakt-enabled" name="idlm_interakt_enabled" value="1" <?php checked($interakt_enabled, '1'); ?>>
                                    <span class="idlm-settings-toggle__track" aria-hidden="true"></span>
                                    <span class="screen-reader-text">Enable Interakt Automation</span>
                                </label>
                                <h3 class="idlm-heading-flush">WhatsApp Automation (Interakt) Integration</h3>
                            </div>
                            <p class="idlm-card-desc">Configure WhatsApp campaign event triggers via the Interakt Event Track API when specific lead call statuses, stages, or quality fields are changed.</p>
                            
                            <div class="idlm-form-group" data-idlm-ui="mb-5">
                                <label for="idlm-interakt-api-key" class="idlm-settings-label">Interakt write API key</label>
                                <input type="password" id="idlm-interakt-api-key" name="idlm_interakt_api_key" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($interakt_api_key); ?>" data-idlm-ui="w-full" placeholder="e.g. key_xxxxxxxxxxxxxxxxxxxxxxxx">
                                <span class="description idlm-settings-help">Provide the Write API key from your Interakt Developer dashboard.</span>
                            </div>
                            
                            <div class="idlm-form-group" data-idlm-ui="mb-5">
                                <label for="idlm-interakt-webhook-secret" class="idlm-settings-label">Interakt webhook secret key</label>
                                <input type="password" id="idlm-interakt-webhook-secret" name="idlm_interakt_webhook_secret" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($interakt_webhook_secret); ?>" data-idlm-ui="w-full" placeholder="e.g. sec_xxxxxxxxxxxxxxxxxxxxxxxx">
                                <span class="description idlm-settings-help">Enter the secret key used by Interakt to sign webhook events.</span>
                            </div>

                            <div class="idlm-form-group" data-idlm-ui="mb-5">
                                <label for="idlm-interakt-webhook-url" class="idlm-settings-label">Webhook callback URL (copy to Interakt)</label>
                                <input type="text" id="idlm-interakt-webhook-url" readonly class="idlm-input" value="<?php echo esc_url(get_rest_url(null, 'idlm/v1/interakt-webhook')); ?>" data-idlm-ui="w-full readonly" onclick="this.select();">
                                <span class="description idlm-settings-help">Copy this URL and paste it as the Webhook URL in your Interakt Developer settings.</span>
                            </div>

                            <div class="idlm-form-group" data-idlm-ui="mb-5">
                                <span class="idlm-form-label idlm-settings-label">Event Mappings</span>
                                <p class="idlm-card-desc idlm-settings-description">Map status, stage, or quality values to custom event campaign names inside Interakt.</p>
                                
                                <table class="idlm-table idlm-control-full">
                                    <thead>
                                        <tr>
                                            <th>Trigger Type</th>
                                            <th>Trigger Value</th>
                                            <th>Interakt Event Name</th>
                                            <th data-idlm-ui="col-120 center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="idlm-interakt-mappings-container">
                                        <?php foreach ($interakt_mappings as $mapping): ?>
                                            <tr class="idlm-interakt-mapping-row" data-saved-val="<?php echo esc_attr($mapping['value']); ?>">
                                                <td>
                                                    <select name="interakt_mapping_type[]" class="idlm-select interakt-mapping-type-select idlm-control-full">
                                                        <option value="call_status" <?php selected($mapping['type'], 'call_status'); ?>>Call Status</option>
                                                        <option value="stage" <?php selected($mapping['type'], 'stage'); ?>>Stage</option>
                                                        <option value="lead_quality" <?php selected($mapping['type'], 'lead_quality'); ?>>Lead Quality</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="interakt_mapping_value[]" class="idlm-select interakt-mapping-value-select idlm-control-full">
                                                        <!-- Populated dynamically via JS -->
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="interakt_mapping_event[]" class="idlm-input" value="<?php echo esc_attr($mapping['event']); ?>" required data-idlm-ui="control-full">
                                                </td>
                                                <td data-idlm-ui="center nowrap">
                                                    <button type="button" class="idlm-btn idlm-btn-secondary idlm-sync-past-interakt-mapping" data-idlm-ui="action-outline compact-button" data-type="<?php echo esc_attr($mapping['type']); ?>" data-value="<?php echo esc_attr($mapping['value']); ?>" data-event="<?php echo esc_attr($mapping['event']); ?>">Sync Past</button>
                                                    <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-interakt-mapping idlm-btn-compact idlm-btn-danger-outline">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                  </table>
                                
                                <div data-idlm-ui="mt-3">
                                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-add-interakt-mapping">+ Add Event Mapping</button>
                                </div>

                                <div id="idlm-bulk-sync-progress-container" class="idlm-settings-progress" hidden>
                                    <h4 data-idlm-ui="heading">Bulk Sync Progress</h4>
                                    <div class="idlm-settings-progress__track">
                                        <div id="idlm-bulk-sync-progress-bar" class="idlm-settings-progress__bar"></div>
                                    </div>
                                    <p id="idlm-bulk-sync-status-text" class="idlm-settings-progress__status">Preparing sync...</p>
                                </div>
                            </div>
                        </article>

                        <!-- Meta WhatsApp Webhook Configuration Card -->
                        <article class="idlm-card idlm-automation-integration-card" data-idlm-ui="mt-5">
                            <div class="idlm-settings-card-head idlm-automation-card-head">
                                <label class="idlm-settings-toggle">
                                    <input type="checkbox" id="idlm-meta-webhook-enabled" name="idlm_meta_webhook_enabled" value="1" <?php checked($meta_webhook_enabled, '1'); ?>>
                                    <span class="idlm-settings-toggle__track" aria-hidden="true"></span>
                                    <span class="screen-reader-text">Enable Direct Meta Webhook</span>
                                </label>
                                <h3 class="idlm-heading-flush">Meta Direct WhatsApp Webhook Configuration</h3>
                            </div>
                            <p class="idlm-card-desc">Bypass third-party integration charges by subscribing directly to Meta's free incoming message webhooks.</p>
                            
                            <div class="idlm-form-group" data-idlm-ui="mb-5">
                                <label for="idlm-meta-webhook-verify-token" class="idlm-settings-label">Verify token</label>
                                <input type="password" id="idlm-meta-webhook-verify-token" name="idlm_meta_webhook_verify_token" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($meta_webhook_verify_token); ?>" data-idlm-ui="w-full" placeholder="Enter a random verify token of your choice">
                                <span class="description idlm-settings-help">Define a unique secret token string here, then enter the exact same string as the 'Verify Token' in your Meta App Webhook Developer settings.</span>
                            </div>

                            <div class="idlm-form-group" data-idlm-ui="mb-5">
                                <label for="idlm-meta-webhook-app-secret" class="idlm-settings-label">Meta app secret</label>
                                <input type="password" id="idlm-meta-webhook-app-secret" name="idlm_meta_webhook_app_secret" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($meta_webhook_app_secret); ?>" data-idlm-ui="w-full" placeholder="e.g. 5f83xxxxxxxxxxxxxxxxxxxxxxxx">
                                <span class="description idlm-settings-help">Provide the Meta App Secret (found in your Meta App Dashboard > Settings > Basic) to verify X-Hub-Signature-256 signatures.</span>
                            </div>

                            <div class="idlm-form-group" data-idlm-ui="mb-5">
                                <label for="idlm-meta-webhook-url" class="idlm-settings-label">Webhook callback URL (copy to Meta App Dashboard)</label>
                                <input type="text" id="idlm-meta-webhook-url" readonly class="idlm-input" value="<?php echo esc_url(get_rest_url(null, 'idlm/v1/meta-webhook')); ?>" data-idlm-ui="w-full readonly" onclick="this.select();">
                                <span class="description idlm-settings-help">Configure this Callback URL inside WhatsApp > Configuration Webhook settings on the Meta Developer Portal and subscribe to the <code>messages</code> field.</span>
                            </div>
                        </article>
                    </div>
                </section> <!-- End Tab 5: Sync & Reminders -->
