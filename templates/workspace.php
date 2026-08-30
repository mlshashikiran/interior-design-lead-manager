<?php
if (!defined('ABSPATH')) {
    exit;
}

$sections = isset($context['sections']) && is_array($context['sections']) ? $context['sections'] : array();
$active = isset($context['active']) ? sanitize_key($context['active']) : '';
$base_url = isset($context['base_url']) ? (string) $context['base_url'] : '';
?>
<div class="idlm-workspace" data-idlm-workspace>
    <aside class="idlm-workspace-sidebar" aria-label="Lead Manager navigation">
        <div class="idlm-workspace-brand">
            <span class="dashicons dashicons-groups" aria-hidden="true"></span>
            <span class="idlm-workspace-brand-text">Lead Manager</span>
            <button type="button" class="idlm-workspace-toggle" aria-label="Collapse navigation" aria-expanded="true">
                <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
            </button>
        </div>
        <nav class="idlm-workspace-nav">
            <?php foreach ($sections as $key => $section): ?>
                <a href="<?php echo esc_url(add_query_arg('idlm_view', $key, $base_url)); ?>" class="idlm-workspace-nav-item <?php echo 'my-account' === $key ? 'idlm-workspace-account-link ' : ''; ?><?php echo $active === $key ? 'is-active' : ''; ?>" <?php echo $active === $key ? 'aria-current="page"' : ''; ?>>
                    <span class="dashicons <?php echo esc_attr($section[1]); ?>" aria-hidden="true"></span>
                    <span class="idlm-workspace-nav-label"><?php echo esc_html($section[0]); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </aside>
    <main class="idlm-workspace-content" id="idlm-workspace-content">
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
