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
                <section id="settings-capi" class="idlm-settings-tab-content <?php echo $active_tab === 'capi' ? 'active' : ''; ?>" style="display: <?php echo $active_tab === 'capi' ? 'block' : 'none'; ?>;">
                    <div class="idlm-grid-full">
                        <article class="idlm-card">
                            <h3>Platform Conversion APIs (CAPI) Settings</h3>
                            <p class="idlm-card-desc">Configure the real-time Conversions API settings to transmit server-side conversion signals to Meta, Google Ads, and LinkedIn upon pipeline stage changes.</p>
                        </article>

                        <div class="idlm-detail-grid">
                            <!-- Meta CAPI Card -->
                            <article class="idlm-card">
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom: 20px;">
                                    <input type="checkbox" name="meta_capi_enabled" value="1" <?php checked($meta_capi_enabled, '1'); ?> style="width: 17px; height: 17px; margin: 0; cursor: pointer;">
                                    <h3 style="margin: 0 !important;">Meta Conversion API (CAPI)</h3>
                                </div>
                                <div class="idlm-form-grid two">
                                    <div class="idlm-form-group">
                                        <label>Meta Pixel ID</label>
                                        <input type="text" name="meta_pixel_id" class="idlm-input" value="<?php echo esc_attr($meta_pixel_id); ?>">
                                    </div>
                                    <div class="idlm-form-group">
                                        <label>Meta Access Token</label>
                                        <input type="password" name="meta_access_token" class="idlm-input" value="<?php echo esc_attr($meta_access_token); ?>">
                                    </div>
                                </div>

                                <h4 style="margin-top: 20px; margin-bottom: 8px; font-size: 14px; font-weight: 700;">Stage to Meta CAPI Event Mapping</h4>
                                <table class="idlm-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40%;">Sales Stage</th>
                                            <th style="width: 60%;">Meta Event Name</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stages as $stage) : 
                                            $mapped_val = isset($meta_event_mappings[$stage]) ? $meta_event_mappings[$stage] : '';
                                        ?>
                                            <tr>
                                                <td style="font-weight: 600; color: var(--idlm-text);"><?php echo esc_html($stage); ?></td>
                                                <td>
                                                    <input type="text" name="meta_event_mappings[<?php echo esc_attr($stage); ?>]" class="idlm-input" style="height: 34px;" value="<?php echo esc_attr($mapped_val); ?>" placeholder="e.g. Lead, Contact, etc.">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <div class="idlm-table-actions">
                                    <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-test-meta-capi" style="border-color: var(--idlm-gold); color: var(--idlm-gold-dark);">Test Meta CAPI Connection</button>
                                </div>
                            </article>

                            <!-- Google Ads Offline Conversions Card -->
                            <article class="idlm-card">
                                <div style="display:flex; align-items:center; gap:10px; margin-bottom: 20px;">
                                    <input type="checkbox" name="google_capi_enabled" value="1" <?php checked($google_capi_enabled, '1'); ?> style="width: 17px; height: 17px; margin: 0; cursor: pointer;">
                                    <h3 style="margin: 0 !important;">Google Ads Offline Conversions</h3>
                                </div>
                                <div class="idlm-form-grid two">
                                    <div class="idlm-form-group">
                                        <label>GA4 Measurement ID</label>
                                        <input type="text" name="google_measurement_id" class="idlm-input" value="<?php echo esc_attr($google_measurement_id); ?>">
                                    </div>
                                    <div class="idlm-form-group">
                                        <label>GA4 API Secret</label>
                                        <input type="password" name="google_api_secret" class="idlm-input" value="<?php echo esc_attr($google_api_secret); ?>">
                                    </div>
                                </div>

                                <h4 style="margin-top: 20px; margin-bottom: 8px; font-size: 14px; font-weight: 700;">Stage to Google Ads Conversion Mapping</h4>
                                <table class="idlm-table">
                                    <thead>
                                        <tr>
                                            <th style="width: 40%;">Sales Stage</th>
                                            <th style="width: 60%;">Google Ads Conversion Name</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stages as $stage) : 
                                            $mapped_val = isset($google_event_mappings[$stage]) ? $google_event_mappings[$stage] : '';
                                        ?>
                                            <tr>
                                                <td style="font-weight: 600; color: var(--idlm-text);"><?php echo esc_html($stage); ?></td>
                                                <td>
                                                    <input type="text" name="google_event_mappings[<?php echo esc_attr($stage); ?>]" class="idlm-input" style="height: 34px;" value="<?php echo esc_attr($mapped_val); ?>" placeholder="e.g. CLOSED_WON, etc.">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                                <?php 
                                $feed_url = site_url('/wp-json/interior-design-lead-manager/v1/google-ads-conversions.csv');
                                ?>
                                <h4 style="margin-top: 25px; margin-bottom: 8px; font-size: 14px; font-weight: 700;">Google Ads Offline Conversion Import (OCI) CSV Feed</h4>
                                <p class="idlm-card-desc">Use this URL to schedule automatic conversion uploads in Google Ads (fetch schedule: e.g. daily), or download the CSV manually.</p>
                                <div class="idlm-form-group" style="margin-top: 10px;">
                                    <input type="text" class="idlm-input" readonly value="<?php echo esc_url($feed_url); ?>" id="idlm-google-oci-feed-url">
                                </div>
                                <div class="idlm-table-actions">
                                    <button type="button" class="idlm-btn idlm-btn-secondary" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-google-oci-feed-url').value);">Copy URL</button>
                                    <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=idlm_export_google_conversions')); ?>" class="idlm-btn idlm-btn-secondary" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center;">Download OCI CSV</a>
                                </div>
                            </article>
                        </div>

                        <!-- LinkedIn Conversion API Card -->
                        <article class="idlm-card">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom: 20px;">
                                <input type="checkbox" name="linkedin_capi_enabled" value="1" <?php checked($linkedin_capi_enabled, '1'); ?> style="width: 17px; height: 17px; margin: 0; cursor: pointer;">
                                <h3 style="margin: 0 !important;">LinkedIn Conversion API</h3>
                            </div>
                            <div class="idlm-form-grid two">
                                <div class="idlm-form-group">
                                    <label>LinkedIn Conversion Action Partner ID</label>
                                    <input type="text" name="linkedin_conversion_id" class="idlm-input" value="<?php echo esc_attr($linkedin_conversion_id); ?>">
                                </div>
                                <div class="idlm-form-group">
                                    <label>LinkedIn Access Token</label>
                                    <input type="password" name="linkedin_access_token" class="idlm-input" value="<?php echo esc_attr($linkedin_access_token); ?>">
                                </div>
                            </div>

                            <h4 style="margin-top: 20px; margin-bottom: 8px; font-size: 14px; font-weight: 700;">Stage to LinkedIn Conversion Mapping</h4>
                            <table class="idlm-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40%;">Sales Stage</th>
                                        <th style="width: 60%;">LinkedIn Conversion Name</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stages as $stage) : 
                                        $mapped_val = isset($linkedin_event_mappings[$stage]) ? $linkedin_event_mappings[$stage] : '';
                                    ?>
                                        <tr>
                                            <td style="font-weight: 600; color: var(--idlm-text);"><?php echo esc_html($stage); ?></td>
                                            <td>
                                                <input type="text" name="linkedin_event_mappings[<?php echo esc_attr($stage); ?>]" class="idlm-input" style="height: 34px;" value="<?php echo esc_attr($mapped_val); ?>" placeholder="e.g. Lead, etc.">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </article>
                    </div>
                </section> <!-- End Tab 4: Conversion APIs (CAPI) -->

