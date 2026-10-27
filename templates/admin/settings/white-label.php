<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_white_label = isset($context['white_label']) && is_array($context['white_label']) ? $context['white_label'] : IDLM_Settings::white_label_defaults();
$idlm_active_tab = isset($context['active_tab']) ? sanitize_key($context['active_tab']) : 'general';
$idlm_assets = isset($context['white_label_assets']) && is_array($context['white_label_assets']) ? $context['white_label_assets'] : array();
$idlm_errors = isset($context['white_label_errors']) && is_array($context['white_label_errors']) ? $context['white_label_errors'] : array();
$idlm_asset_fields = array(
    'logo_id' => array('Main logo', 'Used on sign-in and account surfaces.', 'Recommended wide logo. PNG, JPEG, or WebP up to 2 MB.'),
    'compact_logo_id' => array('Compact mark', 'Used in the workspace sidebar.', 'Recommended square image at least 256 x 256px.'),
    'favicon_id' => array('Browser icon', 'Used only on plugin-owned frontend CRM pages.', 'Recommended square PNG at least 512 x 512px.'),
);
?>
<section id="settings-white-label" class="idlm-settings-tab-content <?php echo $idlm_active_tab === 'white-label' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-white-label" <?php echo $idlm_active_tab === 'white-label' ? '' : 'hidden'; ?>>
    <div class="idlm-settings-card-head idlm-white-label-heading">
        <div>
            <h2>White label</h2>
            <p class="idlm-settings-subtitle">Apply your company identity to CRM entry and workspace areas without inheriting styles from the active site theme.</p>
        </div>
    </div>

    <?php if ($idlm_errors): ?>
        <div class="idlm-alert idlm-alert-warning idlm-white-label-errors" role="alert" tabindex="-1" data-idlm-white-label-errors>
            <h3>Review the highlighted branding fields</h3>
            <ul>
                <?php foreach ($idlm_errors as $idlm_field => $idlm_message): ?>
                    <li><a href="#idlm-white-label-<?php echo esc_attr(str_replace('_', '-', $idlm_field)); ?>"><?php echo esc_html($idlm_message); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="idlm-white-label-layout" data-idlm-white-label data-defaults="<?php echo esc_attr(wp_json_encode(IDLM_Settings::white_label_defaults())); ?>">
        <div class="idlm-white-label-editor">
            <article class="idlm-card">
                <div class="idlm-settings-card-head">
                    <div><h3>Brand identity</h3><p class="idlm-card-desc">Set the name and approved image assets shown on plugin-owned screens.</p></div>
                </div>
                <div class="idlm-form-group">
                    <label for="idlm-white-label-brand-name">Brand name <span class="idlm-required">(required)</span></label>
                    <input class="idlm-input" type="text" id="idlm-white-label-brand-name" name="white_label[brand_name]" maxlength="80" required value="<?php echo esc_attr($idlm_white_label['brand_name']); ?>" data-idlm-brand-field="brand_name" <?php echo isset($idlm_errors['brand_name']) ? 'aria-invalid="true" aria-describedby="idlm-white-label-brand-name-error"' : ''; ?>>
                    <p class="idlm-settings-help">Shown on sign-in and account surfaces.</p>
                    <?php if (isset($idlm_errors['brand_name'])): ?><p class="idlm-field-error" id="idlm-white-label-brand-name-error"><?php echo esc_html($idlm_errors['brand_name']); ?></p><?php endif; ?>
                </div>

                <div class="idlm-brand-assets">
                    <?php foreach ($idlm_asset_fields as $idlm_field => $idlm_copy): ?>
                        <?php $idlm_asset = isset($idlm_assets[$idlm_field]) ? $idlm_assets[$idlm_field] : array('id' => 0, 'url' => '', 'filename' => ''); ?>
                        <?php $idlm_field_slug = str_replace('_', '-', $idlm_field); ?>
                        <div class="idlm-brand-asset-control <?php echo isset($idlm_errors[$idlm_field]) ? 'has-error' : ''; ?>" data-idlm-brand-asset>
                            <div class="idlm-brand-asset-copy">
                                <label id="idlm-white-label-<?php echo esc_attr($idlm_field_slug); ?>-label" for="idlm-white-label-<?php echo esc_attr($idlm_field_slug); ?>"><?php echo esc_html($idlm_copy[0]); ?></label>
                                <p><?php echo esc_html($idlm_copy[1]); ?></p>
                            </div>
                            <div class="idlm-brand-asset-preview" data-idlm-brand-asset-preview>
                                <?php if (!empty($idlm_asset['url'])): ?>
                                    <img src="<?php echo esc_url($idlm_asset['url']); ?>" alt="">
                                <?php else: ?>
                                    <span class="dashicons dashicons-format-image" aria-hidden="true"></span><span>No image selected</span>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" id="idlm-white-label-<?php echo esc_attr($idlm_field_slug); ?>" name="white_label[<?php echo esc_attr($idlm_field); ?>]" value="<?php echo esc_attr($idlm_asset['id']); ?>" data-idlm-brand-asset-input data-idlm-brand-field="<?php echo esc_attr($idlm_field); ?>" <?php echo isset($idlm_errors[$idlm_field]) ? 'aria-invalid="true" aria-describedby="idlm-white-label-' . esc_attr($idlm_field_slug) . '-error"' : ''; ?>>
                            <div class="idlm-brand-asset-actions">
                                <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-brand-asset-select data-title="Choose <?php echo esc_attr(strtolower($idlm_copy[0])); ?>"><?php echo $idlm_asset['id'] ? 'Replace' : 'Choose image'; ?></button>
                                <button type="button" class="idlm-btn idlm-btn-quiet" data-idlm-brand-asset-remove <?php echo $idlm_asset['id'] ? '' : 'hidden'; ?>>Remove</button>
                            </div>
                            <p class="idlm-settings-help"><?php echo esc_html($idlm_copy[2]); ?></p>
                            <?php if (isset($idlm_errors[$idlm_field])): ?><p class="idlm-field-error" id="idlm-white-label-<?php echo esc_attr($idlm_field_slug); ?>-error"><?php echo esc_html($idlm_errors[$idlm_field]); ?></p><?php endif; ?>
                            <span class="screen-reader-text" aria-live="polite" data-idlm-brand-asset-status></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="idlm-card">
                <div class="idlm-settings-card-head">
                    <div><h3>Brand colours</h3><p class="idlm-card-desc">Choose colours for plugin-owned actions, accents, and navigation.</p></div>
                </div>
                <?php
                $idlm_colour_fields = array(
                    'primary_color' => array('Primary action colour', 'Used for primary buttons and selected navigation.', true),
                    'accent_color' => array('Accent colour', 'Used for decorative emphasis and supporting details.', false),
                    'sidebar_color' => array('Sidebar colour', 'Used as the workspace navigation background.', true),
                );
                ?>
                <div class="idlm-brand-colours">
                    <?php foreach ($idlm_colour_fields as $idlm_field => $idlm_copy): ?>
                        <?php $idlm_field_slug = str_replace('_', '-', $idlm_field); ?>
                        <div class="idlm-form-group idlm-brand-colour-control <?php echo isset($idlm_errors[$idlm_field]) ? 'has-error' : ''; ?>" data-idlm-brand-colour data-contrast-required="<?php echo $idlm_copy[2] ? 'true' : 'false'; ?>">
                            <label for="idlm-white-label-<?php echo esc_attr($idlm_field_slug); ?>"><?php echo esc_html($idlm_copy[0]); ?> <span class="idlm-required">(required)</span></label>
                            <div class="idlm-brand-colour-inputs">
                                <input type="color" value="<?php echo esc_attr($idlm_white_label[$idlm_field]); ?>" aria-label="Choose <?php echo esc_attr(strtolower($idlm_copy[0])); ?>" data-idlm-brand-colour-picker>
                                <input class="idlm-input" type="text" id="idlm-white-label-<?php echo esc_attr($idlm_field_slug); ?>" name="white_label[<?php echo esc_attr($idlm_field); ?>]" value="<?php echo esc_attr($idlm_white_label[$idlm_field]); ?>" maxlength="7" pattern="#[0-9A-Fa-f]{6}" required spellcheck="false" data-idlm-brand-colour-value data-idlm-brand-field="<?php echo esc_attr($idlm_field); ?>" <?php echo isset($idlm_errors[$idlm_field]) ? 'aria-invalid="true" aria-describedby="idlm-white-label-' . esc_attr($idlm_field_slug) . '-error"' : ''; ?>>
                            </div>
                            <p class="idlm-settings-help"><?php echo esc_html($idlm_copy[1]); ?></p>
                            <?php if ($idlm_copy[2]): ?><p class="idlm-brand-contrast" role="status" data-idlm-brand-contrast>Checking contrast with white text.</p><?php endif; ?>
                            <?php if (isset($idlm_errors[$idlm_field])): ?><p class="idlm-field-error" id="idlm-white-label-<?php echo esc_attr($idlm_field_slug); ?>-error"><?php echo esc_html($idlm_errors[$idlm_field]); ?></p><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="idlm-card">
                <div class="idlm-settings-card-head">
                    <div><h3>Workspace identity</h3><p class="idlm-card-desc">Set the concise labels your team sees while entering and using the CRM.</p></div>
                </div>
                <div class="idlm-form-grid two">
                    <div class="idlm-form-group">
                        <label for="idlm-white-label-workspace-title">Workspace title <span class="idlm-required">(required)</span></label>
                        <input class="idlm-input" type="text" id="idlm-white-label-workspace-title" name="white_label[workspace_title]" maxlength="60" required value="<?php echo esc_attr($idlm_white_label['workspace_title']); ?>" data-idlm-brand-field="workspace_title" <?php echo isset($idlm_errors['workspace_title']) ? 'aria-invalid="true" aria-describedby="idlm-white-label-workspace-title-error"' : ''; ?>>
                        <?php if (isset($idlm_errors['workspace_title'])): ?><p class="idlm-field-error" id="idlm-white-label-workspace-title-error"><?php echo esc_html($idlm_errors['workspace_title']); ?></p><?php endif; ?>
                    </div>
                    <div class="idlm-form-group">
                        <label for="idlm-white-label-support-email">Support email</label>
                        <input class="idlm-input" type="email" id="idlm-white-label-support-email" name="white_label[support_email]" maxlength="100" autocomplete="email" value="<?php echo esc_attr($idlm_white_label['support_email']); ?>" data-idlm-brand-field="support_email" <?php echo isset($idlm_errors['support_email']) ? 'aria-invalid="true" aria-describedby="idlm-white-label-support-email-error"' : ''; ?>>
                        <?php if (isset($idlm_errors['support_email'])): ?><p class="idlm-field-error" id="idlm-white-label-support-email-error"><?php echo esc_html($idlm_errors['support_email']); ?></p><?php endif; ?>
                    </div>
                    <div class="idlm-form-group full">
                        <label for="idlm-white-label-login-heading">Sign-in heading <span class="idlm-required">(required)</span></label>
                        <input class="idlm-input" type="text" id="idlm-white-label-login-heading" name="white_label[login_heading]" maxlength="120" required value="<?php echo esc_attr($idlm_white_label['login_heading']); ?>" data-idlm-brand-field="login_heading" <?php echo isset($idlm_errors['login_heading']) ? 'aria-invalid="true" aria-describedby="idlm-white-label-login-heading-error"' : ''; ?>>
                        <?php if (isset($idlm_errors['login_heading'])): ?><p class="idlm-field-error" id="idlm-white-label-login-heading-error"><?php echo esc_html($idlm_errors['login_heading']); ?></p><?php endif; ?>
                    </div>
                    <div class="idlm-form-group full">
                        <label for="idlm-white-label-footer-text">Footer text</label>
                        <textarea class="idlm-textarea" id="idlm-white-label-footer-text" name="white_label[footer_text]" rows="3" maxlength="180" data-idlm-brand-field="footer_text"><?php echo esc_textarea($idlm_white_label['footer_text']); ?></textarea>
                        <p class="idlm-settings-help">Plain text only. Leave blank to omit.</p>
                    </div>
                </div>
            </article>

            <article class="idlm-card idlm-card-muted">
                <div class="idlm-settings-card-head"><div><h3>Brand visibility</h3><p class="idlm-card-desc">Choose whether to display a plugin credit.</p></div></div>
                <label class="idlm-checkbox-row"><input id="idlm-show-plugin-attribution" type="checkbox" name="white_label[show_plugin_attribution]" value="1" <?php checked($idlm_white_label['show_plugin_attribution'], '1'); ?> aria-describedby="idlm-attribution-help" data-idlm-brand-field="show_plugin_attribution"><span>Show "Powered by Interior Design Lead Manager"</span></label>
                <p id="idlm-attribution-help" class="idlm-settings-help">Optional public credit, off by default. Appears on the sign-in page and workspace.</p>
                <div class="idlm-settings-actions">
                    <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-brand-reset>Restore default branding</button>
                </div>
            </article>
        </div>

        <aside class="idlm-white-label-preview-panel" aria-label="Brand preview">
            <div class="idlm-white-label-preview" data-idlm-brand-preview>
                <div class="idlm-white-label-preview__sidebar">
                    <div class="idlm-white-label-preview__mark" data-idlm-preview-image="compact_logo_id">
                        <?php if (!empty($idlm_assets['compact_logo_id']['url'])): ?><img src="<?php echo esc_url($idlm_assets['compact_logo_id']['url']); ?>" alt=""><?php else: ?><span class="dashicons dashicons-groups" aria-hidden="true"></span><?php endif; ?>
                    </div>
                    <strong data-idlm-preview-text="workspace_title"><?php echo esc_html($idlm_white_label['workspace_title']); ?></strong>
                    <span class="is-active">Dashboard</span><span>Leads</span><span>Pipeline</span>
                </div>
                <div class="idlm-white-label-preview__content">
                    <div class="idlm-white-label-preview__brand" data-idlm-preview-image="logo_id">
                        <?php if (!empty($idlm_assets['logo_id']['url'])): ?><img src="<?php echo esc_url($idlm_assets['logo_id']['url']); ?>" alt=""><?php else: ?><span data-idlm-preview-text="brand_name"><?php echo esc_html($idlm_white_label['brand_name']); ?></span><?php endif; ?>
                    </div>
                    <p>Workspace overview</p>
                    <h3 data-idlm-preview-text="login_heading"><?php echo esc_html($idlm_white_label['login_heading']); ?></h3>
                    <button type="button" tabindex="-1">Primary action</button>
                    <small data-idlm-preview-text="footer_text"><?php echo esc_html($idlm_white_label['footer_text']); ?></small>
                    <small data-idlm-preview-attribution <?php echo '1' === $idlm_white_label['show_plugin_attribution'] ? '' : 'hidden'; ?>>Powered by Interior Design Lead Manager</small>
                </div>
            </div>
            <p class="idlm-settings-callout">Preview only. Save settings to publish these changes.</p>
        </aside>
    </div>
</section>
