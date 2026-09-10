<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$webhook_key = IDLM_Admin_Settings_Renderer::credential_value('webhook_api_key');
$field_mappings = isset($context['field_mappings']) ? $context['field_mappings'] : array();
?>
                <!-- Tab 3: Incoming Webhooks -->
                <section id="settings-webhooks" class="idlm-settings-tab-content <?php echo $active_tab === 'webhooks' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-webhooks" <?php echo $active_tab === 'webhooks' ? '' : 'hidden'; ?>>
                    <article class="idlm-card">
                        <h3>Incoming lead webhook setup</h3>
                        <div class="idlm-form-grid two">
                            <div class="idlm-form-group">
                                <label for="idlm-webhook-endpoint">Target webhook endpoint URL</label>
                                <input type="text" id="idlm-webhook-endpoint" class="idlm-input" value="<?php echo esc_attr(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>" readonly>
                            </div>
                            <div class="idlm-form-group">
                                <label for="idlm-webhook-api-key">Webhook API key (send in the <code>X-IDLM-API-Key</code> header)</label>
                                <input type="password" id="idlm-webhook-api-key" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($webhook_key); ?>" readonly>
                                <button type="submit" form="idlm-rotate-webhook-form" class="idlm-action-link idlm-action-link-danger" data-idlm-confirm-submit data-idlm-confirm-title="Rotate webhook key?" data-idlm-confirm-message="Rotate the webhook key now? Existing webhook clients must be updated immediately." data-idlm-confirm-label="Rotate key" data-idlm-confirm-destructive="true">Rotate webhook key</button>
                            </div>
                        </div>

                        <div class="idlm-card-desc idlm-settings-callout">
                            Need help setting up lead capture webhooks for ad platforms, website forms, or reception walk-ins, or resolving Cloudflare connection challenges?
                            <button type="button" class="idlm-action-link" onclick="switchSettingsTab(document.querySelector('.idlm-tab-link[onclick*=\'settings-help\']'), 'settings-help');">Visit the Help & FAQs documentation</button>
                        </div>
                    </article>

                    <!-- Field mappings tables -->
                    <h4 class="idlm-settings-section-title">Lead capture field mappings</h4>
                    <p class="idlm-card-desc">Map the field names sent by each source. A phone number or email address is required to accept a lead; every other field is optional.</p>
                    <div class="idlm-tabs idlm-nested-tabs idlm-mapping-tabs" role="tablist" aria-label="Lead capture source mappings">
                        <button type="button" id="idlm-mapping-tab-meta" class="idlm-tab-link active" role="tab" aria-selected="true" aria-controls="map-meta" tabindex="0" onclick="switchFieldMappingTab(this, 'map-meta')">Meta Ads Forms</button>
                        <button type="button" id="idlm-mapping-tab-google" class="idlm-tab-link" role="tab" aria-selected="false" aria-controls="map-google" tabindex="-1" onclick="switchFieldMappingTab(this, 'map-google')">Google Ads Forms</button>
                        <button type="button" id="idlm-mapping-tab-linkedin" class="idlm-tab-link" role="tab" aria-selected="false" aria-controls="map-linkedin" tabindex="-1" onclick="switchFieldMappingTab(this, 'map-linkedin')">LinkedIn Ads Forms</button>
                        <button type="button" id="idlm-mapping-tab-website" class="idlm-tab-link" role="tab" aria-selected="false" aria-controls="map-website" tabindex="-1" onclick="switchFieldMappingTab(this, 'map-website')">Website Custom Webhook</button>
                        <button type="button" id="idlm-mapping-tab-walkin" class="idlm-tab-link" role="tab" aria-selected="false" aria-controls="map-walkin" tabindex="-1" onclick="switchFieldMappingTab(this, 'map-walkin')">Reception Walk-ins</button>
                    </div>

                    <?php
                    $platforms = array('meta', 'google', 'linkedin', 'website', 'walkin');
                    $field_groups = array(
                        'identity' => array('title' => 'Lead identity', 'help' => 'Required: map at least one contact route (email or phone). Names are optional.', 'keys' => array('first_name', 'last_name', 'email', 'phone')),
                        'project' => array('title' => 'Project details', 'help' => 'Optional details used to qualify the lead and route it to the right team.', 'keys' => array('budget', 'project_timeline', 'property_location', 'property_type', 'num_rooms', 'preferred_studio')),
                        'attribution' => array('title' => 'Attribution', 'help' => 'Optional campaign and click identifiers for reporting and conversion matching.', 'keys' => array('utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'fb_lead_id', 'li_fat_id')),
                        'advanced' => array('title' => 'Advanced metadata', 'help' => 'Optional source context retained with the lead record.', 'keys' => array('landing_page', 'conversion_time', 'referrer_details')),
                    );
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
                        <?php
                        $mapped_count = count(array_filter($mappings, static function($value) { return '' !== trim((string) $value); }));
                        $contact_ready = !empty($mappings['email']) || !empty($mappings['phone']);
                        ?>
                        <div id="map-<?php echo esc_attr($plat); ?>" class="idlm-mapping-tab-content <?php echo esc_attr($active_class); ?>" role="tabpanel" aria-labelledby="idlm-mapping-tab-<?php echo esc_attr($plat); ?>"<?php echo $idx === 0 ? '' : ' hidden'; ?> tabindex="0">
                            <div class="idlm-mapping-header">
                                <span class="idlm-badge <?php echo esc_attr($plat_badge_class); ?>"><?php echo esc_html($plat_badge_label); ?> Webhook URL</span>
                                <button type="button" class="idlm-btn idlm-btn-secondary" onclick="idlmCopyToClipboard(this, '<?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=<?php echo esc_attr($plat); ?>');">Copy URL</button>
                            </div>
                            <code class="idlm-code"><?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=<?php echo esc_attr($plat); ?></code>
                            <p class="description">Send the configured webhook API key in the <code>X-IDLM-API-Key</code> header. For security, manage or copy the key from the protected control above.</p>
                            <p class="idlm-mapping-status <?php echo $contact_ready ? 'is-configured' : 'needs-attention'; ?>"><strong><?php echo $contact_ready ? 'Configured' : 'Needs attention'; ?></strong> · <?php echo $contact_ready ? 'Contact route ready' : 'Map an email or phone field'; ?> · <?php echo intval($mapped_count); ?> field<?php echo 1 === $mapped_count ? '' : 's'; ?> mapped</p>

                            <div class="idlm-mapping-groups">
                                <?php foreach ($field_groups as $group_key => $group):
                                    $group_fields = array_intersect_key($platform_fields, array_flip($group['keys']));
                                    if (empty($group_fields)) { continue; }
                                ?>
                                    <div class="idlm-mapping-group">
                                        <div class="idlm-mapping-group__title"><?php echo esc_html($group['title']); ?><?php if ('identity' === $group_key): ?> <span class="idlm-required-marker">Required contact route</span><?php else: ?> <span class="idlm-settings-disclosure__count">Optional</span><?php endif; ?></div>
                                        <p class="idlm-settings-help"><?php echo esc_html($group['help']); ?></p>
                                        <div class="idlm-form-grid compact">
                                            <?php foreach ($group_fields as $key => $lbl):
                                                $val = isset($mappings[$key]) ? $mappings[$key] : '';
                                            ?>
                                                <div class="idlm-form-group">
                                                    <label for="idlm-map-<?php echo esc_attr($plat); ?>-<?php echo esc_attr($key); ?>"><?php echo esc_html($lbl); ?><?php if (in_array($key, array('email', 'phone'), true)): ?> <span class="idlm-required-marker">Required*</span><?php endif; ?></label>
                                                    <input type="text" id="idlm-map-<?php echo esc_attr($plat); ?>-<?php echo esc_attr($key); ?>" name="map_<?php echo esc_attr($plat); ?>_<?php echo esc_attr($key); ?>" class="idlm-input" placeholder="e.g. <?php echo esc_attr($key); ?>" value="<?php echo esc_attr($val); ?>">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section> <!-- End Tab 3: Incoming Webhooks -->
