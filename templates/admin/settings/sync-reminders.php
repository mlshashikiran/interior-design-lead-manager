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
                <section id="settings-audience-reminders" class="idlm-settings-tab-content <?php echo $active_tab === 'audience-reminders' ? 'active' : ''; ?>" style="display: <?php echo $active_tab === 'audience-reminders' ? 'block' : 'none'; ?>;">
                    <div class="idlm-grid-full">
                        <!-- Custom Audience Sync card -->
                        <article class="idlm-card">
                            <h3>Custom Audience Batch Sync Rules</h3>
                            <p class="idlm-card-desc">Configure multiple Custom Audience batch sync jobs to run daily at 2:00 AM. You can target specific segments by choosing custom pipeline stages, call statuses, or lead qualities for each platform's destination list.</p>
                            
                            <table class="idlm-table" id="idlm-audience-rules-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Platform</th>
                                        <th>Audience / Segment ID</th>
                                        <th>Value-Based?</th>
                                        <th>Stage Filter</th>
                                        <th>Call Status Filter</th>
                                        <th>Lead Quality Filter</th>
                                        <th style="width: 120px;">Status</th>
                                        <th style="width: 140px; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="idlm-audience-rules-container">
                                    <?php foreach ($audience_rules as $rule_idx => $rule): ?>
                                        <tr class="idlm-audience-rule-row">
                                            <td>
                                                <select name="audience_rules[platform][]" class="idlm-select" style="width: 100%;">
                                                    <option value="meta" <?php selected($rule['platform'], 'meta'); ?>>Meta Ads</option>
                                                    <option value="google" <?php selected($rule['platform'], 'google'); ?>>Google Ads</option>
                                                    <option value="linkedin" <?php selected($rule['platform'], 'linkedin'); ?>>LinkedIn Ads</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="audience_rules[audience_id][]" class="idlm-input" value="<?php echo esc_attr($rule['audience_id']); ?>" placeholder="e.g. User List ID (or URN)" required style="width: 100%; height: 34px;">
                                                <?php if ($rule['platform'] === 'google' && !empty($rule['audience_id'])): ?>
                                                    <div style="margin-top: 4px; font-size: 11px;">
                                                        <a href="<?php echo esc_url(site_url('/wp-json/interior-design-lead-manager/v1/google-ads-audience/' . urlencode($rule['audience_id']) . '.csv')); ?>" target="_blank" style="color: var(--idlm-gold-dark); text-decoration: underline; font-weight: 500;">Feed URL</a>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <select name="audience_rules[is_value][]" class="idlm-select" style="width: 100%;">
                                                    <option value="0" <?php selected(isset($rule['is_value']) ? $rule['is_value'] : '0', '0'); ?>>No</option>
                                                    <option value="1" <?php selected(isset($rule['is_value']) ? $rule['is_value'] : '0', '1'); ?>>Yes (Meta Value-Based)</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="audience_rules[stage][]" class="idlm-select" style="width: 100%;">
                                                    <option value="all">All Stages</option>
                                                    <?php foreach ($stages as $stage): ?>
                                                        <option value="<?php echo esc_attr($stage); ?>" <?php selected($rule['stage'], $stage); ?>><?php echo esc_html($stage); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="audience_rules[call_status][]" class="idlm-select" style="width: 100%;">
                                                    <option value="all">All Statuses</option>
                                                    <?php foreach ($call_statuses as $cs): ?>
                                                        <option value="<?php echo esc_attr($cs); ?>" <?php selected($rule['call_status'], $cs); ?>><?php echo esc_html($cs); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="audience_rules[lead_quality][]" class="idlm-select" style="width: 100%;">
                                                    <option value="all" <?php selected($rule['lead_quality'], 'all'); ?>>All Qualities</option>
                                                    <option value="Qualified" <?php selected($rule['lead_quality'], 'Qualified'); ?>>Qualified</option>
                                                    <option value="Unqualified" <?php selected($rule['lead_quality'], 'Unqualified'); ?>>Unqualified</option>
                                                </select>
                                            </td>
                                            <td>
                                                <select name="audience_rules[enabled][]" class="idlm-select" style="width: 100%;">
                                                    <option value="1" <?php selected($rule['enabled'], '1'); ?>>Enabled</option>
                                                    <option value="0" <?php selected($rule['enabled'], '0'); ?>>Disabled</option>
                                                </select>
                                            </td>
                                            <td style="text-align: center;">
                                                <button type="button" class="idlm-btn idlm-btn-secondary idlm-test-audience-rule" style="border-color: var(--idlm-gold); color: var(--idlm-gold-dark); padding: 4px 10px; margin-right: 5px;">Test</button>
                                                <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-audience-rule" style="border-color: #ef4444; color: #ef4444; padding: 4px 10px;">Delete</button>
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
                        <article class="idlm-card">
                            <h3>Google Ads Data Manager (HTTPS Pull Feeds)</h3>
                            <p class="idlm-card-desc">Instead of complex API integrations, Google Ads Data Manager pulls conversion data and customer match segments from secure HTTPS feeds on your site. Configure the Basic Auth credentials below, then map these URLs in your Google Ads Data Manager. <a onclick="switchSettingsTab(document.querySelector('.idlm-tab-link[onclick*=\'settings-help\']'), 'settings-help'); setTimeout(function(){ document.getElementById('idlm-help-google-ads-creds').scrollIntoView({behavior: 'smooth'}); }, 100);" style="color: var(--idlm-gold-dark); font-weight: 600; text-decoration: underline; cursor: pointer;">Read step-by-step instructions to link sources</a>.</p>
                            
                            <div class="idlm-form-grid two" style="margin-top: 15px;">
                                <div class="idlm-form-group">
                                    <label>HTTPS Source Username</label>
                                    <input type="text" name="google_gadm_username" class="idlm-input" value="<?php echo esc_attr($google_gadm_username); ?>" required>
                                </div>
                                <div class="idlm-form-group">
                                    <label>HTTPS Source Password / Token</label>
                                    <input type="text" name="google_gadm_password" class="idlm-input" value="<?php echo esc_attr($google_gadm_password); ?>" required>
                                </div>
                            </div>

                            <div class="idlm-form-group" style="margin-top: 15px;">
                                <label>Google Ads Offline Conversions Feed URL</label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="text" class="idlm-input" style="flex: 1; background: #f3f4f6;" readonly value="<?php echo esc_url(site_url('/wp-json/interior-design-lead-manager/v1/google-ads-conversions.csv')); ?>" id="idlm-conversions-feed-url">
                                    <button type="button" class="idlm-btn idlm-btn-secondary" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-conversions-feed-url').value)">Copy URL</button>
                                </div>
                                <p style="font-size: 11px; color: #6b7280; margin-top: 4px;">Use this URL for importing Offline Conversions in Google Ads Data Manager.</p>
                            </div>
                        </article>

                        <!-- Follow-up Reminders card -->
                        <article class="idlm-card">
                            <h3>Followup Reminder Settings</h3>
                            <p class="idlm-card-desc">Configure different reminder intervals (in hours) for various Call Statuses. When a lead's call status remains in one of these statuses longer than the specified hours, it will be highlighted as "Overdue" in the leads list.</p>
                            
                            <table class="idlm-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th>Call Status</th>
                                        <th>Time Interval (Hours)</th>
                                        <th style="width: 120px; text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="idlm-reminders-container">
                                    <?php foreach ($reminder_rules as $rule): ?>
                                        <tr class="idlm-reminder-row">
                                            <td>
                                                <select name="reminder_rules_status[]" class="idlm-select" style="width: 100%;">
                                                    <?php foreach ($call_statuses as $cs): ?>
                                                        <option value="<?php echo esc_attr($cs); ?>" <?php selected($rule['status'], $cs); ?>><?php echo esc_html($cs); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="reminder_rules_hours[]" class="idlm-input" value="<?php echo esc_attr($rule['hours']); ?>" min="1" required style="height: 34px;">
                                            </td>
                                            <td style="text-align: center;">
                                                <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-reminder-rule" style="border-color: #ef4444; color: #ef4444; padding: 4px 10px;">Delete</button>
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
                        <article class="idlm-card">
                            <h3>WhatsApp Automation (Interakt) Integration</h3>
                            <p class="idlm-card-desc">Configure WhatsApp campaign event triggers via the Interakt Event Track API when specific lead call statuses, stages, or quality fields are changed.</p>
                            
                            <div class="idlm-form-group" style="margin-bottom: 20px;">
                                <label style="display: flex; align-items: center; font-weight: bold; cursor: pointer; gap: 8px;">
                                    <input type="checkbox" name="idlm_interakt_enabled" value="1" <?php checked($interakt_enabled, '1'); ?>>
                                    Enable Interakt Automation
                                </label>
                            </div>
                            
                            <div class="idlm-form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">Interakt Write API Key</label>
                                <input type="password" name="idlm_interakt_api_key" class="idlm-input" value="<?php echo esc_attr($interakt_api_key); ?>" style="width: 100%;" placeholder="e.g. key_xxxxxxxxxxxxxxxxxxxxxxxx">
                                <span class="description" style="font-size: 11px; color: #666;">Provide the Write API key from your Interakt Developer dashboard.</span>
                            </div>
                            
                            <div class="idlm-form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">Interakt Webhook Secret Key</label>
                                <input type="password" name="idlm_interakt_webhook_secret" class="idlm-input" value="<?php echo esc_attr($interakt_webhook_secret); ?>" style="width: 100%;" placeholder="e.g. sec_xxxxxxxxxxxxxxxxxxxxxxxx">
                                <span class="description" style="font-size: 11px; color: #666;">Enter the secret key used by Interakt to sign webhook events.</span>
                            </div>

                            <div class="idlm-form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">Webhook Callback URL (Copy to Interakt)</label>
                                <input type="text" readonly class="idlm-input" value="<?php echo esc_url(get_rest_url(null, 'idlm/v1/interakt-webhook')); ?>" style="width: 100%; background-color: #f3f4f6;" onclick="this.select();">
                                <span class="description" style="font-size: 11px; color: #666;">Copy this URL and paste it as the Webhook URL in your Interakt Developer settings.</span>
                            </div>

                            <div class="idlm-form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">Event Mappings</label>
                                <p class="idlm-card-desc" style="margin-top: 0; margin-bottom: 12px; font-size: 12px;">Map status, stage, or quality values to custom event campaign names inside Interakt.</p>
                                
                                <table class="idlm-table" style="width: 100%;">
                                    <thead>
                                        <tr>
                                            <th>Trigger Type</th>
                                            <th>Trigger Value</th>
                                            <th>Interakt Event Name</th>
                                            <th style="width: 120px; text-align: center;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="idlm-interakt-mappings-container">
                                        <?php foreach ($interakt_mappings as $mapping): ?>
                                            <tr class="idlm-interakt-mapping-row" data-saved-val="<?php echo esc_attr($mapping['value']); ?>">
                                                <td>
                                                    <select name="interakt_mapping_type[]" class="idlm-select interakt-mapping-type-select" style="width: 100%;">
                                                        <option value="call_status" <?php selected($mapping['type'], 'call_status'); ?>>Call Status</option>
                                                        <option value="stage" <?php selected($mapping['type'], 'stage'); ?>>Stage</option>
                                                        <option value="lead_quality" <?php selected($mapping['type'], 'lead_quality'); ?>>Lead Quality</option>
                                                    </select>
                                                </td>
                                                <td>
                                                    <select name="interakt_mapping_value[]" class="idlm-select interakt-mapping-value-select" style="width: 100%;">
                                                        <!-- Populated dynamically via JS -->
                                                    </select>
                                                </td>
                                                <td>
                                                    <input type="text" name="interakt_mapping_event[]" class="idlm-input" value="<?php echo esc_attr($mapping['event']); ?>" required style="width: 100%; height: 34px;">
                                                </td>
                                                <td style="text-align: center; white-space: nowrap;">
                                                    <button type="button" class="idlm-btn idlm-btn-secondary idlm-sync-past-interakt-mapping" style="border-color: var(--idlm-gold); color: var(--idlm-gold-dark); padding: 4px 10px; margin-right: 5px;" data-type="<?php echo esc_attr($mapping['type']); ?>" data-value="<?php echo esc_attr($mapping['value']); ?>" data-event="<?php echo esc_attr($mapping['event']); ?>">Sync Past</button>
                                                    <button type="button" class="idlm-btn idlm-btn-secondary idlm-remove-interakt-mapping" style="border-color: #ef4444; color: #ef4444; padding: 4px 10px;">Delete</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                  </table>
                                
                                <div style="margin-top: 12px;">
                                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-add-interakt-mapping">+ Add Event Mapping</button>
                                </div>

                                <div id="idlm-bulk-sync-progress-container" style="display: none; margin-top: 15px; padding: 15px; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; width: 100%;">
                                    <h4 style="margin: 0 0 10px 0;">Bulk Sync Progress</h4>
                                    <div style="width: 100%; background: #e5e7eb; height: 10px; border-radius: 5px; overflow: hidden;">
                                        <div id="idlm-bulk-sync-progress-bar" style="width: 0%; background: var(--idlm-gold-dark); height: 100%; transition: width 0.3s ease;"></div>
                                    </div>
                                    <p id="idlm-bulk-sync-status-text" style="font-size: 12px; margin: 8px 0 0 0; color: #4b5563;">Preparing sync...</p>
                                </div>
                            </div>
                        </article>

                        <!-- Meta WhatsApp Webhook Configuration Card -->
                        <article class="idlm-card" style="margin-top: 20px;">
                            <h3>Meta Direct WhatsApp Webhook Configuration</h3>
                            <p class="idlm-card-desc">Bypass third-party integration charges by subscribing directly to Meta's free incoming message webhooks.</p>
                            
                            <div class="idlm-form-group" style="margin-bottom: 20px;">
                                <label style="display: flex; align-items: center; font-weight: bold; cursor: pointer; gap: 8px;">
                                    <input type="checkbox" name="idlm_meta_webhook_enabled" value="1" <?php checked($meta_webhook_enabled, '1'); ?>>
                                    Enable Direct Meta Webhook
                                </label>
                            </div>
                            
                            <div class="idlm-form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">Verify Token</label>
                                <input type="password" name="idlm_meta_webhook_verify_token" class="idlm-input" value="<?php echo esc_attr($meta_webhook_verify_token); ?>" style="width: 100%;" placeholder="Enter a random verify token of your choice">
                                <span class="description" style="font-size: 11px; color: #666;">Define a unique secret token string here, then enter the exact same string as the 'Verify Token' in your Meta App Webhook Developer settings.</span>
                            </div>

                            <div class="idlm-form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">Meta App Secret</label>
                                <input type="password" name="idlm_meta_webhook_app_secret" class="idlm-input" value="<?php echo esc_attr($meta_webhook_app_secret); ?>" style="width: 100%;" placeholder="e.g. 5f83xxxxxxxxxxxxxxxxxxxxxxxx">
                                <span class="description" style="font-size: 11px; color: #666;">Provide the Meta App Secret (found in your Meta App Dashboard > Settings > Basic) to verify X-Hub-Signature-256 signatures.</span>
                            </div>

                            <div class="idlm-form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; display: block; margin-bottom: 8px;">Webhook Callback URL (Copy to Meta App Dashboard)</label>
                                <input type="text" readonly class="idlm-input" value="<?php echo esc_url(get_rest_url(null, 'idlm/v1/meta-webhook')); ?>" style="width: 100%; background-color: #f3f4f6;" onclick="this.select();">
                                <span class="description" style="font-size: 11px; color: #666;">Configure this Callback URL inside WhatsApp > Configuration Webhook settings on the Meta Developer Portal and subscribe to the <code>messages</code> field.</span>
                            </div>
                        </article>
                    </div>
                </section> <!-- End Tab 5: Sync & Reminders -->
