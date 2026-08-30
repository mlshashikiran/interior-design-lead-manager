<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$webhook_key = IDLM_Admin_Settings_Renderer::credential_value('webhook_api_key');
$field_mappings = isset($context['field_mappings']) ? $context['field_mappings'] : array();
?>
                <!-- Tab 3: Incoming Webhooks -->
                <section id="settings-webhooks" class="idlm-settings-tab-content <?php echo $active_tab === 'webhooks' ? 'active' : ''; ?>" style="display: <?php echo $active_tab === 'webhooks' ? 'block' : 'none'; ?>;">
                    <article class="idlm-card">
                        <h3>Incoming lead webhook setup</h3>
                        <div class="idlm-form-grid two">
                            <div class="idlm-form-group">
                                <label>Target Webhook Endpoint URL</label>
                                <input type="text" class="idlm-input" value="<?php echo esc_attr(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>" readonly>
                            </div>
                            <div class="idlm-form-group">
                                <label>Webhook API Key (send in the <code>X-IDLM-API-Key</code> header)</label>
                                <input type="text" class="idlm-input" value="<?php echo esc_attr($webhook_key); ?>" readonly>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Rotate the webhook key now? Existing webhook clients must be updated immediately.');">
                                    <input type="hidden" name="action" value="idlm_rotate_api_credential">
                                    <input type="hidden" name="scope" value="webhook">
                                    <?php wp_nonce_field('idlm_rotate_api_credential_webhook'); ?>
                                    <button type="submit" class="button-link">Rotate webhook key</button>
                                </form>
                            </div>
                        </div>

                        <div class="idlm-card-desc" style="margin-top: 15px; background: var(--idlm-surface-tint); border: 1px solid var(--idlm-border); border-left: 4px solid var(--idlm-blue); border-radius: 8px; padding: 12px; font-size: 13px; color: var(--idlm-text);">
                            Need help setting up lead capture webhooks for ad platforms, website forms, or reception walk-ins, or resolving Cloudflare connection challenges?
                            <a onclick="switchSettingsTab(document.querySelector('.idlm-tab-link[onclick*=\'settings-help\']'), 'settings-help');" style="color: var(--idlm-blue); font-weight: 600; text-decoration: underline; cursor: pointer;">Visit the Help & FAQs documentation</a>
                        </div>
                    </article>

                    <!-- Field mappings tables -->
                    <h4 style="margin-top: 25px; margin-bottom: 15px; font-size: 16px; font-weight: 700;">Webhooks Payload Field Mappings</h4>
                    <div class="idlm-tabs idlm-nested-tabs">
                        <button type="button" class="idlm-tab-link active" onclick="switchFieldMappingTab(this, 'map-meta')">Meta Ads Forms</button>
                        <button type="button" class="idlm-tab-link" onclick="switchFieldMappingTab(this, 'map-google')">Google Ads Forms</button>
                        <button type="button" class="idlm-tab-link" onclick="switchFieldMappingTab(this, 'map-linkedin')">LinkedIn Ads Forms</button>
                        <button type="button" class="idlm-tab-link" onclick="switchFieldMappingTab(this, 'map-website')">Website Custom Webhook</button>
                        <button type="button" class="idlm-tab-link" onclick="switchFieldMappingTab(this, 'map-walkin')">Reception Walk-ins</button>
                    </div>

                    <?php
                    $platforms = array('meta', 'google', 'linkedin', 'website', 'walkin');
                    $fields = array(
                        'first_name' => 'First Name Payload Variable',
                        'last_name' => 'Last Name Payload Variable',
                        'email' => 'Email Address Payload Variable',
                        'phone' => 'Phone Number Payload Variable',
                        'budget' => 'Lead Budget Value',
                        'project_timeline' => 'Project Timeline',
                        'property_location' => 'Property Location/Pincode',
                        'property_type' => 'Property Type (Apartment/Villa)',
                        'num_rooms' => 'Number of Rooms (BHK)',
                        'preferred_studio' => 'Studio Location Variable',
                        'utm_source' => 'UTM Source',
                        'utm_medium' => 'UTM Medium',
                        'utm_campaign' => 'UTM Campaign',
                        'utm_adgroup' => 'UTM Adgroup',
                        'utm_term' => 'UTM Term',
                        'utm_content' => 'UTM Content',
                        'gclid' => 'GCLID',
                        'gbraid' => 'GBRAID',
                        'wbraid' => 'WBRAID',
                        'fbclid' => 'FBCLID',
                        'fb_lead_id' => 'Meta Lead ID',
                        'li_fat_id' => 'LI FAT ID',
                        'landing_page' => 'Landing Page URL',
                        'conversion_time' => 'Conversion Timestamp',
                        'referrer_details' => 'Reception Notes / Referrer Details'
                    );

                    foreach ($platforms as $idx => $plat):
                        $mappings = isset($field_mappings[$plat]) ? $field_mappings[$plat] : array();
                        $active_class = ($idx === 0) ? 'active' : '';
                        $platform_fields = $fields;

                        if ($plat === 'walkin') {
                            $walkin_field_keys = array('first_name', 'last_name', 'email', 'phone', 'budget', 'project_timeline', 'property_location', 'property_type', 'num_rooms', 'preferred_studio', 'referrer_details');
                            $platform_fields = array_intersect_key($fields, array_flip($walkin_field_keys));
                        }

                        $plat_badge_label = '';
                        $plat_badge_class = 'idlm-badge-' . $plat;
                        if ($plat === 'meta') {
                            $plat_badge_label = 'Meta Ads / Facebook Lead Forms';
                        } elseif ($plat === 'google') {
                            $plat_badge_label = 'Google Ads Lead Forms';
                        } elseif ($plat === 'linkedin') {
                            $plat_badge_label = 'LinkedIn Lead Gen Forms';
                        } elseif ($plat === 'website') {
                            $plat_badge_label = 'Website Custom Forms (e.g. Elementor)';
                        } elseif ($plat === 'walkin') {
                            $plat_badge_label = 'Reception Walk-in Forms';
                        }
                    ?>
                        <div id="map-<?php echo esc_attr($plat); ?>" class="idlm-mapping-tab-content <?php echo esc_attr($active_class); ?>">
                            <div class="idlm-mapping-header">
                                <span class="idlm-badge <?php echo esc_attr($plat_badge_class); ?>"><?php echo esc_html($plat_badge_label); ?> Webhook URL</span>
                                <button type="button" class="idlm-btn idlm-btn-secondary" onclick="idlmCopyToClipboard(this, '<?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=<?php echo esc_attr($plat); ?>');">Copy URL</button>
                            </div>
                            <code class="idlm-code"><?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=<?php echo esc_attr($plat); ?></code>
                            <p class="description">Header: <code>X-IDLM-API-Key: <?php echo esc_html($webhook_key); ?></code></p>
                            
                            <div class="idlm-form-grid compact" style="margin-top: 20px;">
                                <?php foreach ($platform_fields as $key => $lbl):
                                    $val = isset($mappings[$key]) ? $mappings[$key] : '';
                                ?>
                                    <div class="idlm-form-group">
                                        <label><?php echo esc_html($lbl); ?></label>
                                        <input type="text" name="map_<?php echo esc_attr($plat); ?>_<?php echo esc_attr($key); ?>" class="idlm-input" placeholder="e.g. <?php echo esc_attr($key); ?>" value="<?php echo esc_attr($val); ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section> <!-- End Tab 3: Incoming Webhooks -->
