<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$stages = isset($context['stages']) ? $context['stages'] : null;
$meta_capi_enabled = isset($context['meta_capi_enabled']) ? $context['meta_capi_enabled'] : null;
$meta_pixel_id = isset($context['meta_pixel_id']) ? $context['meta_pixel_id'] : null;
$meta_access_token = IDLM_Admin_Settings_Renderer::credential_value('meta_access_token');
$meta_event_mappings = isset($context['meta_event_mappings']) ? $context['meta_event_mappings'] : null;
$google_capi_enabled = isset($context['google_capi_enabled']) ? $context['google_capi_enabled'] : null;
$google_measurement_id = isset($context['google_measurement_id']) ? $context['google_measurement_id'] : null;
$google_api_secret = IDLM_Admin_Settings_Renderer::credential_value('google_api_secret');
$google_event_mappings = isset($context['google_event_mappings']) ? $context['google_event_mappings'] : null;
$linkedin_capi_enabled = isset($context['linkedin_capi_enabled']) ? $context['linkedin_capi_enabled'] : null;
$linkedin_conversion_id = isset($context['linkedin_conversion_id']) ? $context['linkedin_conversion_id'] : null;
$linkedin_access_token = IDLM_Admin_Settings_Renderer::credential_value('linkedin_access_token');
$linkedin_event_mappings = isset($context['linkedin_event_mappings']) ? $context['linkedin_event_mappings'] : null;
?>
                <!-- Tab 4: Conversion APIs (CAPI) -->
                <section id="settings-capi" class="idlm-settings-tab-content <?php echo $active_tab === 'capi' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-capi" <?php echo $active_tab === 'capi' ? '' : 'hidden'; ?>>
                    <div class="idlm-grid-full">
                        <article class="idlm-card">
                            <h3>Platform Conversion APIs (CAPI) Settings</h3>
                            <p class="idlm-card-desc">Configure the real-time Conversions API settings to transmit server-side conversion signals to Meta, Google Ads, and LinkedIn upon pipeline stage changes.</p>
                        </article>

                        <div class="idlm-detail-grid">
                            <!-- Meta CAPI Card -->
                            <article class="idlm-card">
                                <div class="idlm-settings-card-head">
                                    <input type="checkbox" id="idlm-meta-capi-enabled" name="meta_capi_enabled" value="1" <?php checked($meta_capi_enabled, '1'); ?> class="idlm-capi-toggle" data-idlm-ui="checkbox">
                                    <h3 class="idlm-heading-flush"><label for="idlm-meta-capi-enabled">Meta Conversion API (CAPI)</label></h3>
                                </div>
                                <div class="idlm-form-grid two">
                                    <div class="idlm-form-group">
                                        <label for="idlm-meta-pixel-id">Meta Pixel ID</label>
                                        <input type="text" id="idlm-meta-pixel-id" name="meta_pixel_id" class="idlm-input" value="<?php echo esc_attr($meta_pixel_id); ?>">
                                    </div>
                                    <div class="idlm-form-group">
                                        <label for="idlm-meta-access-token">Meta access token</label>
                                        <input type="password" id="idlm-meta-access-token" name="meta_access_token" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($meta_access_token); ?>">
                                    </div>
                                </div>

                                <h4 class="idlm-settings-subtitle">Stage to Meta CAPI event mapping</h4>
                                <table class="idlm-table">
                                    <thead>
                                        <tr>
                                            <th data-idlm-ui="col-40">Sales Stage</th>
                                            <th data-idlm-ui="col-60">Meta Event Name</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stages as $stage) : 
                                            $mapped_val = isset($meta_event_mappings[$stage]) ? $meta_event_mappings[$stage] : '';
                                        ?>
                                            <tr>
                                                <td data-idlm-ui="strong"><?php echo esc_html($stage); ?></td>
                                                <td>
                                                    <input type="text" name="meta_event_mappings[<?php echo esc_attr($stage); ?>]" class="idlm-input" aria-label="Meta event for <?php echo esc_attr($stage); ?>" value="<?php echo esc_attr($mapped_val); ?>" placeholder="e.g. Lead or Contact">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <div class="idlm-table-actions">
                                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-test-meta-capi" data-idlm-ui="action-outline">Test Meta CAPI Connection</button>
                                </div>
                            </article>

                            <!-- Google Ads Offline Conversions Card -->
                            <article class="idlm-card">
                                <div class="idlm-settings-card-head">
                                    <input type="checkbox" id="idlm-google-capi-enabled" name="google_capi_enabled" value="1" <?php checked($google_capi_enabled, '1'); ?> class="idlm-capi-toggle" data-idlm-ui="checkbox">
                                    <h3 class="idlm-heading-flush"><label for="idlm-google-capi-enabled">Google Ads Offline Conversions</label></h3>
                                </div>
                                <div class="idlm-form-grid two">
                                    <div class="idlm-form-group">
                                        <label for="idlm-google-measurement-id">GA4 Measurement ID</label>
                                        <input type="text" id="idlm-google-measurement-id" name="google_measurement_id" class="idlm-input" value="<?php echo esc_attr($google_measurement_id); ?>">
                                    </div>
                                    <div class="idlm-form-group">
                                        <label for="idlm-google-api-secret">GA4 API secret</label>
                                        <input type="password" id="idlm-google-api-secret" name="google_api_secret" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($google_api_secret); ?>">
                                    </div>
                                </div>

                                <h4 class="idlm-settings-subtitle">Stage to Google Ads conversion mapping</h4>
                                <table class="idlm-table">
                                    <thead>
                                        <tr>
                                            <th data-idlm-ui="col-40">Sales Stage</th>
                                            <th data-idlm-ui="col-60">Google Ads Conversion Name</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stages as $stage) : 
                                            $mapped_val = isset($google_event_mappings[$stage]) ? $google_event_mappings[$stage] : '';
                                        ?>
                                            <tr>
                                                <td data-idlm-ui="strong"><?php echo esc_html($stage); ?></td>
                                                <td>
                                                    <input type="text" name="google_event_mappings[<?php echo esc_attr($stage); ?>]" class="idlm-input" aria-label="Google conversion for <?php echo esc_attr($stage); ?>" value="<?php echo esc_attr($mapped_val); ?>" placeholder="e.g. CLOSED_WON">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <?php 
                                $feed_url = site_url('/wp-json/interior-design-lead-manager/v1/google-ads-conversions.csv');
                                ?>
                                <h4 class="idlm-settings-subtitle">Google Ads offline conversion import (OCI) CSV feed</h4>
                                <p class="idlm-card-desc">Use this URL to schedule automatic conversion uploads in Google Ads (fetch schedule: e.g. daily), or download the CSV manually.</p>
                                <div class="idlm-form-group" data-idlm-ui="mt-2">
                                    <input type="text" class="idlm-input" readonly value="<?php echo esc_url($feed_url); ?>" id="idlm-google-oci-feed-url">
                                </div>
                                <div class="idlm-table-actions">
                                    <button type="button" class="idlm-btn idlm-btn-secondary" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-google-oci-feed-url').value);">Copy URL</button>
                                    <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=idlm_export_google_conversions')); ?>" class="idlm-btn idlm-btn-secondary idlm-link-plain">Download OCI CSV</a>
                                </div>
                            </article>
                        </div>

                        <!-- LinkedIn Conversion API Card -->
                        <article class="idlm-card">
                            <div class="idlm-settings-card-head">
                                <input type="checkbox" id="idlm-linkedin-capi-enabled" name="linkedin_capi_enabled" value="1" <?php checked($linkedin_capi_enabled, '1'); ?> class="idlm-capi-toggle" data-idlm-ui="checkbox">
                                <h3 class="idlm-heading-flush"><label for="idlm-linkedin-capi-enabled">LinkedIn Conversion API</label></h3>
                            </div>
                            <div class="idlm-form-grid two">
                                <div class="idlm-form-group">
                                    <label for="idlm-linkedin-conversion-id">LinkedIn conversion action partner ID</label>
                                    <input type="text" id="idlm-linkedin-conversion-id" name="linkedin_conversion_id" class="idlm-input" value="<?php echo esc_attr($linkedin_conversion_id); ?>">
                                </div>
                                <div class="idlm-form-group">
                                    <label for="idlm-linkedin-access-token">LinkedIn access token</label>
                                    <input type="password" id="idlm-linkedin-access-token" name="linkedin_access_token" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($linkedin_access_token); ?>">
                                </div>
                            </div>

                            <h4 class="idlm-settings-subtitle">Stage to LinkedIn conversion mapping</h4>
                            <table class="idlm-table">
                                <thead>
                                    <tr>
                                        <th data-idlm-ui="col-40">Sales Stage</th>
                                        <th data-idlm-ui="col-60">LinkedIn Conversion Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stages as $stage) : 
                                        $mapped_val = isset($linkedin_event_mappings[$stage]) ? $linkedin_event_mappings[$stage] : '';
                                    ?>
                                        <tr>
                                            <td data-idlm-ui="strong"><?php echo esc_html($stage); ?></td>
                                            <td>
                                                <input type="text" name="linkedin_event_mappings[<?php echo esc_attr($stage); ?>]" class="idlm-input" aria-label="LinkedIn conversion for <?php echo esc_attr($stage); ?>" value="<?php echo esc_attr($mapped_val); ?>" placeholder="e.g. Lead">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </article>
                    </div>
                </section> <!-- End Tab 4: Conversion APIs (CAPI) -->
