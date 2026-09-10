<?php
if (!defined('ABSPATH')) {
    exit;
}

$sections = isset($context['sections']) && is_array($context['sections']) ? $context['sections'] : array();
$active = isset($context['active']) ? sanitize_key($context['active']) : '';
$base_url = isset($context['base_url']) ? (string) $context['base_url'] : '';
$role_variant = isset($context['role_variant']) ? sanitize_key($context['role_variant']) : 'representative';
$white_label = isset($context['white_label']) && is_array($context['white_label']) ? $context['white_label'] : IDLM_Settings::get_white_label();
$compact_logo = IDLM_Settings::brand_asset($white_label['compact_logo_id']);
$brand_name = isset($white_label['brand_name']) ? (string) $white_label['brand_name'] : '';
$workspace_title = isset($white_label['workspace_title']) ? (string) $white_label['workspace_title'] : '';
$brand_mark = strtoupper(substr($brand_name ?: $workspace_title ?: 'L', 0, 1));
?>
<div class="idlm-workspace" data-idlm-workspace data-idlm-role="<?php echo esc_attr($role_variant); ?>" style="<?php echo esc_attr(IDLM_Settings::white_label_style($white_label)); ?>">
    <a class="idlm-skip-link" href="#idlm-workspace-content">Skip to CRM content</a>
    <button type="button" class="idlm-workspace-mobile-toggle" aria-controls="idlm-workspace-sidebar" aria-expanded="false" aria-label="Open navigation">
        <span class="dashicons dashicons-menu" aria-hidden="true"></span>
    </button>
    <div class="idlm-workspace-backdrop" data-idlm-workspace-backdrop hidden></div>
    <aside class="idlm-workspace-sidebar" id="idlm-workspace-sidebar" aria-label="<?php echo esc_attr(sprintf('%s navigation', $workspace_title)); ?>">
        <div class="idlm-workspace-brand">
            <?php if ($compact_logo['url']): ?><img class="idlm-workspace-brand-image" src="<?php echo esc_url($compact_logo['url']); ?>" alt="<?php echo esc_attr($brand_name); ?>"><?php else: ?><span class="idlm-workspace-brand-mark" aria-hidden="true"><?php echo esc_html($brand_mark); ?></span><?php endif; ?>
            <span class="idlm-workspace-brand-text"><?php echo esc_html($workspace_title); ?></span>
            <button type="button" class="idlm-workspace-toggle" aria-label="Collapse navigation" aria-expanded="true">
                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <nav class="idlm-workspace-nav">
            <?php foreach ($sections as $key => $section): ?>
                <?php if ('my-account' === $key): ?><div class="idlm-workspace-account-menu-wrap"><button type="button" class="idlm-workspace-nav-item idlm-workspace-account-trigger" aria-controls="idlm-workspace-account-menu" aria-expanded="false" title="My Account"><span class="dashicons <?php echo esc_attr($section[1]); ?>" aria-hidden="true"></span><span class="idlm-workspace-nav-label"><?php echo esc_html($section[0]); ?></span><span class="dashicons dashicons-arrow-up-alt2 idlm-workspace-account-chevron" aria-hidden="true"></span></button><div class="idlm-workspace-account-menu" id="idlm-workspace-account-menu" role="menu" aria-label="My Account" hidden><a href="<?php echo esc_url(add_query_arg('idlm_view', 'my-account', $base_url)); ?>" role="menuitem"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><span>Profile</span></a><a href="<?php echo esc_url(wp_logout_url($base_url)); ?>" role="menuitem"><span class="dashicons dashicons-exit" aria-hidden="true"></span><span>Logout</span></a></div></div><?php else: ?><a href="<?php echo esc_url(add_query_arg('idlm_view', $key, $base_url)); ?>" class="idlm-workspace-nav-item <?php echo $active === $key ? 'is-active' : ''; ?>" <?php echo $active === $key ? 'aria-current="page"' : ''; ?>>
                    <span class="dashicons <?php echo esc_attr($section[1]); ?>" aria-hidden="true"></span>
                    <span class="idlm-workspace-nav-label"><?php echo esc_html($section[0]); ?></span>
                </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>
        <?php if ($white_label['footer_text'] || '1' === $white_label['show_plugin_attribution']): ?>
            <footer class="idlm-workspace-footer">
                <?php if ($white_label['footer_text']): ?><span><?php echo esc_html($white_label['footer_text']); ?></span><?php endif; ?>
                <?php if ('1' === $white_label['show_plugin_attribution']): ?><small>Powered by Interior Design Lead Manager</small><?php endif; ?>
            </footer>
        <?php endif; ?>
    </aside>
    <main class="idlm-workspace-content" id="idlm-workspace-content" tabindex="-1">
        <?php
        if (isset($sections[$active][2]) && is_callable($sections[$active][2])) {
            $result = call_user_func($sections[$active][2]);
            if (is_string($result)) {
                echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted plugin renderer output.
            }
        }
        ?>
    </main>
</div>
