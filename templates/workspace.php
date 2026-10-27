<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_sections = isset($context['sections']) && is_array($context['sections']) ? $context['sections'] : array();
$idlm_active = isset($context['active']) ? sanitize_key($context['active']) : '';
$idlm_base_url = isset($context['base_url']) ? (string) $context['base_url'] : '';
$idlm_role_variant = isset($context['role_variant']) ? sanitize_key($context['role_variant']) : 'representative';
$idlm_white_label = isset($context['white_label']) && is_array($context['white_label']) ? $context['white_label'] : IDLM_Settings::get_white_label();
$idlm_compact_logo = IDLM_Settings::brand_asset($idlm_white_label['compact_logo_id']);
$idlm_brand_name = isset($idlm_white_label['brand_name']) ? (string) $idlm_white_label['brand_name'] : '';
$idlm_workspace_title = isset($idlm_white_label['workspace_title']) ? (string) $idlm_white_label['workspace_title'] : '';
$idlm_brand_mark = strtoupper(substr($idlm_brand_name ?: $idlm_workspace_title ?: 'L', 0, 1));
?>
<div class="idlm-workspace alignfull" data-idlm-workspace data-idlm-role="<?php echo esc_attr($idlm_role_variant); ?>" style="<?php echo esc_attr(IDLM_Settings::white_label_style($idlm_white_label)); ?>">
    <a class="idlm-skip-link" href="#idlm-workspace-content">Skip to CRM content</a>
    <button type="button" class="idlm-workspace-mobile-toggle" aria-controls="idlm-workspace-sidebar" aria-expanded="false" aria-label="Open navigation">
        <span class="dashicons dashicons-menu" aria-hidden="true"></span>
    </button>
    <div class="idlm-workspace-backdrop" data-idlm-workspace-backdrop hidden></div>
    <aside class="idlm-workspace-sidebar" id="idlm-workspace-sidebar" aria-label="<?php echo esc_attr(sprintf('%s navigation', $idlm_workspace_title)); ?>">
        <div class="idlm-workspace-brand">
            <?php if ($idlm_compact_logo['url']): ?><img class="idlm-workspace-brand-image" src="<?php echo esc_url($idlm_compact_logo['url']); ?>" alt="<?php echo esc_attr($idlm_brand_name); ?>"><?php else: ?><span class="idlm-workspace-brand-mark" aria-hidden="true"><?php echo esc_html($idlm_brand_mark); ?></span><?php endif; ?>
            <span class="idlm-workspace-brand-text"><?php echo esc_html($idlm_workspace_title); ?></span>
            <button type="button" class="idlm-workspace-toggle" aria-label="Collapse navigation" aria-expanded="true">
                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <nav class="idlm-workspace-nav">
            <?php foreach ($idlm_sections as $idlm_key => $idlm_section): ?>
                <?php if ('my-account' === $idlm_key): ?><div class="idlm-workspace-account-menu-wrap"><button type="button" class="idlm-workspace-nav-item idlm-workspace-account-trigger" aria-controls="idlm-workspace-account-menu" aria-expanded="false" title="My Account"><span class="dashicons <?php echo esc_attr($idlm_section[1]); ?>" aria-hidden="true"></span><span class="idlm-workspace-nav-label"><?php echo esc_html($idlm_section[0]); ?></span><span class="dashicons dashicons-arrow-up-alt2 idlm-workspace-account-chevron" aria-hidden="true"></span></button><div class="idlm-workspace-account-menu" id="idlm-workspace-account-menu" role="menu" aria-label="My Account" hidden><a href="<?php echo esc_url(add_query_arg('idlm_view', 'my-account', $idlm_base_url)); ?>" role="menuitem"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span>Profile</span></a><a href="<?php echo esc_url(wp_logout_url($idlm_base_url)); ?>" role="menuitem"><span class="dashicons dashicons-exit" aria-hidden="true"></span><span>Logout</span></a></div></div><?php else: ?><a href="<?php echo esc_url(add_query_arg('idlm_view', $idlm_key, $idlm_base_url)); ?>" class="idlm-workspace-nav-item <?php echo $idlm_active === $idlm_key ? 'is-active' : ''; ?>" <?php echo $idlm_active === $idlm_key ? 'aria-current="page"' : ''; ?>>
                    <span class="dashicons <?php echo esc_attr($idlm_section[1]); ?>" aria-hidden="true"></span>
                    <span class="idlm-workspace-nav-label"><?php echo esc_html($idlm_section[0]); ?></span>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php if ($idlm_white_label['footer_text'] || '1' === $idlm_white_label['show_plugin_attribution']): ?>
            <footer class="idlm-workspace-footer">
                <?php if ($idlm_white_label['footer_text']): ?><span><?php echo esc_html($idlm_white_label['footer_text']); ?></span><?php endif; ?>
                <?php if ('1' === $idlm_white_label['show_plugin_attribution']): ?><small>Powered by Interior Design Lead Manager</small><?php endif; ?>
            </footer>
        <?php endif; ?>
    </aside>
    <main class="idlm-workspace-content" id="idlm-workspace-content" tabindex="-1">
        <?php
        if (isset($idlm_sections[$idlm_active][2]) && is_callable($idlm_sections[$idlm_active][2])) {
            $idlm_result = call_user_func($idlm_sections[$idlm_active][2]);
            if (is_string($idlm_result)) {
                echo $idlm_result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted plugin renderer output.
            }
        }
        ?>
    </main>
</div>
