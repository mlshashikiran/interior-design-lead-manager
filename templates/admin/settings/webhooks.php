<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$idlm_webhook_key = IDLM_Admin_Settings_Renderer::credential_value('webhook_api_key');
$idlm_field_mappings = isset($context['field_mappings']) ? $context['field_mappings'] : array();
// phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0136: (guarded template): capture_tab is a sanitized fixed UI-tab choice in the authorized settings view; settings writes have already passed the separate process_request capability/nonce guard; choosing a presentation tab has no side effect.
$idlm_capture_tab = isset($_POST['idlm_capture_tab']) && is_string($_POST['idlm_capture_tab']) && 'webhook' === sanitize_key(wp_unslash($_POST['idlm_capture_tab'])) ? 'webhook' : 'meta';
// phpcs:enable WordPress.Security.NonceVerification.Missing
if (!empty($context['lead_connector']['error'])) { $idlm_capture_tab = 'meta'; }
?>
                <!-- Tab 3: Incoming Webhooks -->
                <section id="settings-webhooks" class="idlm-settings-tab-content <?php echo $idlm_active_tab === 'webhooks' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-webhooks" <?php echo $idlm_active_tab === 'webhooks' ? '' : 'hidden'; ?>>
                    <input type="hidden" name="idlm_capture_tab" id="idlm-capture-tab-input" value="<?php echo esc_attr($idlm_capture_tab); ?>">
                    <nav class="idlm-tabs idlm-nested-tabs idlm-capture-tabs" role="tablist" aria-label="Lead capture methods">
                        <button type="button" id="idlm-capture-tab-meta" class="idlm-tab-link <?php echo 'meta' === $idlm_capture_tab ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo 'meta' === $idlm_capture_tab ? 'true' : 'false'; ?>" aria-controls="idlm-capture-meta" tabindex="<?php echo 'meta' === $idlm_capture_tab ? '0' : '-1'; ?>">Meta Direct Sync</button>
                        <button type="button" id="idlm-capture-tab-webhook" class="idlm-tab-link <?php echo 'webhook' === $idlm_capture_tab ? 'active' : ''; ?>" role="tab" aria-selected="<?php echo 'webhook' === $idlm_capture_tab ? 'true' : 'false'; ?>" aria-controls="idlm-capture-webhook" tabindex="<?php echo 'webhook' === $idlm_capture_tab ? '0' : '-1'; ?>">Webhook Sync</button>
                    </nav>
                    <div id="idlm-capture-meta" role="tabpanel" aria-labelledby="idlm-capture-tab-meta" <?php echo 'meta' === $idlm_capture_tab ? '' : 'hidden'; ?>>
<?php IDLM_View::display('admin/settings/lead-connector.php', $context); ?>
                    </div>
                    <div id="idlm-capture-webhook" role="tabpanel" aria-labelledby="idlm-capture-tab-webhook" <?php echo 'webhook' === $idlm_capture_tab ? '' : 'hidden'; ?>>
                    <article class="idlm-card">
                        <h3>Webhook credentials</h3>
                        <p class="idlm-card-desc">Add the endpoint and API key to the service sending your leads. Rotating the key requires updating every connected service.</p>
                        <div class="idlm-form-grid two">
                            <div class="idlm-form-group">
                                <label for="idlm-webhook-endpoint">Target webhook endpoint URL</label>
                                <input type="text" id="idlm-webhook-endpoint" class="idlm-input" value="<?php echo esc_attr(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>" readonly>
                            </div>
                            <div class="idlm-form-group">
                                <label for="idlm-webhook-api-key">Webhook API key (send in the <code>X-IDLM-API-Key</code> header)</label>
                                <input type="password" id="idlm-webhook-api-key" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($idlm_webhook_key); ?>" readonly>
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
                    $idlm_platforms = array('meta', 'google', 'linkedin', 'website', 'walkin');
                    $idlm_field_groups = array(
                        'identity' => array('title' => 'Lead identity', 'help' => 'Required: map at least one contact route (email or phone). Names are optional.', 'keys' => array('first_name', 'last_name', 'email', 'phone')),
                        'project' => array('title' => 'Project details', 'help' => 'Optional details used to qualify the lead and route it to the right team.', 'keys' => array('budget', 'project_timeline', 'property_location', 'property_type', 'num_rooms', 'preferred_studio')),
                        'attribution' => array('title' => 'Attribution', 'help' => 'Optional campaign and click identifiers for reporting and conversion matching.', 'keys' => array('utm_source', 'utm_medium', 'utm_campaign', 'utm_adgroup', 'utm_term', 'utm_content', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'fb_lead_id', 'li_fat_id')),
                        'advanced' => array('title' => 'Advanced metadata', 'help' => 'Optional source context retained with the lead record.', 'keys' => array('landing_page', 'conversion_time', 'referrer_details')),
                    );
                    $idlm_fields = array(
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

                    foreach ($idlm_platforms as $idlm_idx => $idlm_plat):
                        $idlm_mappings = isset($idlm_field_mappings[$idlm_plat]) ? $idlm_field_mappings[$idlm_plat] : array();
                        $idlm_active_class = ($idlm_idx === 0) ? 'active' : '';
                        $idlm_platform_fields = $idlm_fields;

                        if ($idlm_plat === 'walkin') {
                            $idlm_walkin_field_keys = array('first_name', 'last_name', 'email', 'phone', 'budget', 'project_timeline', 'property_location', 'property_type', 'num_rooms', 'preferred_studio', 'referrer_details');
                            $idlm_platform_fields = array_intersect_key($idlm_fields, array_flip($idlm_walkin_field_keys));
                        }

                        $idlm_plat_badge_label = '';
                        $idlm_plat_badge_class = 'idlm-badge-' . $idlm_plat;
                        if ($idlm_plat === 'meta') {
                            $idlm_plat_badge_label = 'Meta Ads / Facebook Lead Forms';
                        } elseif ($idlm_plat === 'google') {
                            $idlm_plat_badge_label = 'Google Ads Lead Forms';
                        } elseif ($idlm_plat === 'linkedin') {
                            $idlm_plat_badge_label = 'LinkedIn Lead Gen Forms';
                        } elseif ($idlm_plat === 'website') {
                            $idlm_plat_badge_label = 'Website Custom Forms (e.g. Elementor)';
                        } elseif ($idlm_plat === 'walkin') {
                            $idlm_plat_badge_label = 'Reception Walk-in Forms';
                        }
                    ?>
                        <?php
                        $idlm_mapped_count = count(array_filter($idlm_mappings, static function($idlm_value) { return '' !== trim((string) $idlm_value); }));
                        $idlm_contact_ready = !empty($idlm_mappings['email']) || !empty($idlm_mappings['phone']);
                        ?>
                        <div id="map-<?php echo esc_attr($idlm_plat); ?>" class="idlm-mapping-tab-content <?php echo esc_attr($idlm_active_class); ?>" role="tabpanel" aria-labelledby="idlm-mapping-tab-<?php echo esc_attr($idlm_plat); ?>"<?php echo $idlm_idx === 0 ? '' : ' hidden'; ?> tabindex="0">
                            <div class="idlm-mapping-header">
                                <span class="idlm-badge <?php echo esc_attr($idlm_plat_badge_class); ?>"><?php echo esc_html($idlm_plat_badge_label); ?> Webhook URL</span>
                                <button type="button" class="idlm-btn idlm-btn-secondary" onclick="idlmCopyToClipboard(this, '<?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=<?php echo esc_attr($idlm_plat); ?>');">Copy URL</button>
                            </div>
                            <code class="idlm-code"><?php echo esc_url(get_rest_url(null, 'interior-design-lead-manager/v1/webhook')); ?>?platform=<?php echo esc_attr($idlm_plat); ?></code>
                            <p class="description">Send the configured webhook API key in the <code>X-IDLM-API-Key</code> header. For security, manage or copy the key from the protected control above.</p>
                            <p class="idlm-mapping-status <?php echo $idlm_contact_ready ? 'is-configured' : 'needs-attention'; ?>"><strong><?php echo $idlm_contact_ready ? 'Contact mapping present' : 'Contact mapping needed'; ?></strong> · <?php echo $idlm_contact_ready ? 'Send a test lead to verify delivery' : 'Map an email or phone field'; ?> · <?php echo intval($idlm_mapped_count); ?> field<?php echo 1 === $idlm_mapped_count ? '' : 's'; ?> mapped</p>

                            <div class="idlm-mapping-groups">
                                <?php foreach ($idlm_field_groups as $idlm_group_key => $idlm_group):
                                    $idlm_group_fields = array_intersect_key($idlm_platform_fields, array_flip($idlm_group['keys']));
                                    if (empty($idlm_group_fields)) { continue; }
                                ?>
                                    <?php $idlm_collapsed_group = in_array($idlm_group_key, array('attribution', 'advanced'), true); ?>
                                    <?php if ($idlm_collapsed_group): ?><details class="idlm-mapping-group" open><summary class="idlm-mapping-group__title"><?php echo esc_html($idlm_group['title']); ?> <span class="idlm-settings-disclosure__count">Optional</span></summary><?php else: ?><div class="idlm-mapping-group">
                                        <div class="idlm-mapping-group__title"><?php echo esc_html($idlm_group['title']); ?><?php if ('identity' === $idlm_group_key): ?> <span class="idlm-required-marker">Email or phone required</span><?php else: ?> <span class="idlm-settings-disclosure__count">Optional</span><?php endif; ?></div><?php endif; ?>
                                        <p class="idlm-settings-help"><?php echo esc_html($idlm_group['help']); ?></p>
                                        <div class="idlm-form-grid compact">
                                            <?php foreach ($idlm_group_fields as $idlm_key => $idlm_lbl):
                                                $idlm_val = isset($idlm_mappings[$idlm_key]) ? $idlm_mappings[$idlm_key] : '';
                                            ?>
                                                <div class="idlm-form-group">
                                                    <label for="idlm-map-<?php echo esc_attr($idlm_plat); ?>-<?php echo esc_attr($idlm_key); ?>"><?php echo esc_html($idlm_lbl); ?></label>
                                                    <input type="text" id="idlm-map-<?php echo esc_attr($idlm_plat); ?>-<?php echo esc_attr($idlm_key); ?>" name="map_<?php echo esc_attr($idlm_plat); ?>_<?php echo esc_attr($idlm_key); ?>" class="idlm-input" placeholder="e.g. <?php echo esc_attr($idlm_key); ?>" value="<?php echo esc_attr($idlm_val); ?>">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php if ($idlm_collapsed_group): ?></details><?php else: ?></div><?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </section> <!-- End Tab 3: Incoming Webhooks -->
