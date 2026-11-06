<?php
/**
 * Shared CRM asset registration and context-aware enqueueing.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Admin_Assets {
    const DESIGN_OPTION = 'idlm_design_layer';
    const DESIGN_CRM = 'crm';
    const DESIGN_LEGACY = 'legacy';
    const DESIGN_AREAS = array('leads', 'pipeline', 'dashboard', 'quotations', 'settings', 'admin', 'payments');

    private static $white_label_style_added = false;

    /** Use each local asset's modification time to avoid stale frontend bundles. */
    private static function asset_version($relative_path) {
        $path = dirname(__DIR__) . '/assets/' . ltrim((string) $relative_path, '/');
        return is_file($path) ? (string) filemtime($path) : IDLM_VERSION;
    }

    /** Cache-busting version for any plugin asset, relative to assets/: its modification time. */
    public static function version($relative_path) {
        return self::asset_version($relative_path);
    }

    /** Apply saved brand tokens only to plugin-owned CRM roots and dialogs. */
    private static function add_white_label_style() {
        if (self::$white_label_style_added || !function_exists('wp_add_inline_style')) {
            return;
        }
        $declarations = IDLM_Settings::white_label_style();
        wp_add_inline_style('idlm-admin-styles', '.idlm-wrap.idlm-wrap,.idlm-workspace.idlm-workspace,.idlm-access-restricted.idlm-access-restricted,.idlm-dialog.idlm-dialog{' . $declarations . '}');
        self::$white_label_style_added = true;
    }

    /** Register shared core style and script assets. */
    public static function register_core_assets() {
        $asset_url = plugin_dir_url(dirname(__FILE__)) . 'assets/';

        wp_register_style('idlm-google-fonts', $asset_url . 'css/fonts.css', array(), self::asset_version('css/fonts.css'));
        wp_register_script('idlm-chartjs', $asset_url . 'js/chart.js', array(), '4.4.1', true);

        wp_register_style('idlm-admin-styles', $asset_url . 'css/admin-style.css', array('dashicons'), self::asset_version('css/admin-style.css'));
        wp_register_style('idlm-dashboard-styles', $asset_url . 'css/dashboard.css', array('idlm-admin-styles'), self::asset_version('css/dashboard.css'));
        wp_register_style('idlm-kanban-styles', $asset_url . 'css/kanban.css', array('idlm-admin-styles'), self::asset_version('css/kanban.css'));
        wp_register_style('idlm-workspace-styles', $asset_url . 'css/workspace.css', array('idlm-admin-styles'), self::asset_version('css/workspace.css'));

        wp_register_script('idlm-dialog', $asset_url . 'js/dialog.js', array(), self::asset_version('js/dialog.js'), true);
        wp_register_script('idlm-admin-app', $asset_url . 'js/admin-app.js', array('jquery', 'idlm-dialog'), self::asset_version('js/admin-app.js'), true);
        wp_register_script('idlm-settings', $asset_url . 'js/settings.js', array('jquery', 'idlm-admin-app'), self::asset_version('js/settings.js'), true);
        wp_register_script('idlm-users-logs', $asset_url . 'js/users-logs.js', array('idlm-admin-app', 'idlm-dialog'), self::asset_version('js/users-logs.js'), true);
        wp_register_script('idlm-dashboard-app', $asset_url . 'js/dashboard.js', array('jquery', 'idlm-chartjs'), self::asset_version('js/dashboard.js'), true);
        wp_register_script('idlm-kanban-app', $asset_url . 'js/kanban.js', array('jquery', 'idlm-admin-app'), self::asset_version('js/kanban.js'), true);
        // Load before the workspace can paint so its saved collapsed state prevents a sidebar flash.
        wp_register_script('idlm-workspace-app', $asset_url . 'js/workspace.js', array(), self::asset_version('js/workspace.js'), false);
        wp_register_script('idlm-access-account', $asset_url . 'js/access-account.js', array(), self::asset_version('js/access-account.js'), true);

        wp_localize_script('idlm-admin-app', 'idlm_vars', array(
            'ajax_url'      => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('idlm_admin_nonce'),
            'call_statuses' => array_values(array_diff(
                (array) get_option('idlm_custom_call_statuses', array_values(IDLM_State_Registry::call_statuses())),
                array(IDLM_State_Registry::CALL_NOT_CALLED)
            )),
            'ui_labels' => (array) get_option('idlm_ui_labels', array()),
            'value_labels' => (array) get_option('idlm_value_labels', array()),
        ));

        wp_add_inline_script('idlm-admin-app', "
            window.addEventListener('error', function(e) {
                if (e.filename && e.filename.indexOf('interior-design-lead-manager') !== -1) {
                    jQuery.post(idlm_vars.ajax_url, {
                        action: 'idlm_log_js_error',
                        nonce: idlm_vars.nonce,
                        message: e.message,
                        file: e.filename,
                        line: e.lineno,
                        col: e.colno,
                        stack: e.error ? e.error.stack : ''
                    });
                }
            });
        ", 'before');
    }

    /**
     * The duplicated wp-admin CRM was removed. Keep this public compatibility
     * method intentionally empty so the lightweight utility page loads only
     * WordPress core assets.
     */
    public static function enqueue_assets($hook) {
        unset($hook);
    }

    /**
     * Whether the switchable CRM design layer applies to this request.
     *
     * Order: IDLM_DESIGN_LAYER constant (wp-config rollback), then the read-only
     * ?idlm_design=legacy|crm comparison parameter, then the saved option.
     */
    public static function design_layer_enabled() {
        // wp-admin pages load no layer stylesheet, so they keep the markup their CSS expects.
        if (function_exists('is_admin') && is_admin() && !(function_exists('wp_doing_ajax') && wp_doing_ajax())) {
            return false;
        }
        if (defined('IDLM_DESIGN_LAYER')) {
            return (bool) IDLM_DESIGN_LAYER;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only presentation preference.
        $preview = isset($_GET['idlm_design']) ? sanitize_key(wp_unslash($_GET['idlm_design'])) : '';
        if ('legacy' === $preview || 'crm' === $preview) {
            return 'crm' === $preview;
        }
        return self::DESIGN_LEGACY !== get_option(self::DESIGN_OPTION, self::DESIGN_CRM);
    }

    /** Load the design layer last and expose the body class that gates every rule. */
    private static function maybe_enqueue_design_layer() {
        if (!self::design_layer_enabled()) {
            return;
        }
        $asset_url = plugin_dir_url(dirname(__FILE__)) . 'assets/';
        wp_enqueue_style('idlm-crm-design', $asset_url . 'css/crm-design.css', array('idlm-admin-styles'), self::asset_version('css/crm-design.css'));
        foreach (self::DESIGN_AREAS as $area) {
            wp_enqueue_style('idlm-crm-design-' . $area, $asset_url . 'css/crm-design-' . $area . '.css', array('idlm-crm-design'), self::asset_version('css/crm-design-' . $area . '.css'));
        }
        add_filter('body_class', array(__CLASS__, 'add_design_body_class'));
    }

    /** Gate class for assets/css/crm-design.css. */
    public static function add_design_body_class($classes) {
        $classes[] = 'idlm-crm-design';
        return $classes;
    }

    /** Administrator rollback control, rendered on the asset-free wp-admin utility page. */
    public static function render_design_layer_control() {
        $locked = defined('IDLM_DESIGN_LAYER');
        $active = $locked ? (bool) IDLM_DESIGN_LAYER : self::DESIGN_LEGACY !== get_option(self::DESIGN_OPTION, self::DESIGN_CRM);
        ?>
        <h2>Interface design</h2>
        <p>Currently using the <strong><?php echo $active ? 'CRM design' : 'previous design'; ?></strong>. Switching changes appearance only; leads, quotations and settings are not affected.</p>
        <?php if ($locked) : ?>
            <p>This is set by the <code>IDLM_DESIGN_LAYER</code> constant in <code>wp-config.php</code>. Remove the constant to switch here.</p>
        <?php else : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="idlm_set_design_layer">
                <input type="hidden" name="design" value="<?php echo esc_attr($active ? self::DESIGN_LEGACY : self::DESIGN_CRM); ?>">
                <?php wp_nonce_field('idlm_set_design_layer'); ?>
                <button type="submit" class="button"><?php echo $active ? 'Switch to previous design' : 'Switch to CRM design'; ?></button>
            </form>
        <?php endif;
    }

    /** Save the administrator's design layer choice. */
    public static function handle_set_design_layer() {
        if (!current_user_can('manage_options')) {
            wp_die('You do not have permission to manage Lead Manager.', 403);
        }
        check_admin_referer('idlm_set_design_layer');
        $design = isset($_POST['design']) ? sanitize_key(wp_unslash($_POST['design'])) : '';
        update_option(self::DESIGN_OPTION, self::DESIGN_LEGACY === $design ? self::DESIGN_LEGACY : self::DESIGN_CRM, false);
        wp_safe_redirect(admin_url('admin.php?page=idlm_utilities'));
        exit;
    }

    /** Conditionally enqueue the established frontend shortcode asset sets. */
    public static function enqueue_frontend_assets() {
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
        }

        if (has_shortcode($post->post_content, 'idlm_quotations')) {
            self::register_core_assets();
            self::maybe_enqueue_design_layer();
        }

        $has_leads_list = has_shortcode($post->post_content, 'idlm_leads_list');
        $has_dashboard = has_shortcode($post->post_content, 'idlm_dashboard');
        $has_kanban = has_shortcode($post->post_content, 'idlm_kanban');
        $has_workspace = has_shortcode($post->post_content, 'idlm_workspace');
        if (!$has_leads_list && !$has_dashboard && !$has_kanban && !$has_workspace) {
            return;
        }

        self::register_core_assets();
        wp_enqueue_style('idlm-google-fonts');
        wp_enqueue_style('idlm-admin-styles');
        self::add_white_label_style();
        wp_enqueue_script('idlm-dialog');
        wp_enqueue_script('idlm-admin-app');
        wp_enqueue_script('idlm-access-account');

        if ($has_workspace) {
            wp_enqueue_style('idlm-workspace-styles');
            wp_enqueue_script('idlm-workspace-app');
            wp_enqueue_script('idlm-users-logs');
            wp_enqueue_script('idlm-settings');
        }
        if ($has_dashboard || $has_workspace) {
            wp_enqueue_style('idlm-dashboard-styles');
            wp_enqueue_script('idlm-chartjs');
            wp_enqueue_script('idlm-dashboard-app');
        }
        if ($has_kanban || $has_workspace) {
            wp_enqueue_style('idlm-kanban-styles');
            wp_enqueue_script('idlm-kanban-app');
        }
        self::maybe_enqueue_design_layer();
    }

    /** Force the asset set required by a direct shortcode render. */
    public static function enqueue_shortcode_assets($context) {
        self::register_core_assets();
        wp_enqueue_style('idlm-google-fonts');
        wp_enqueue_style('idlm-admin-styles');
        self::add_white_label_style();
        wp_enqueue_script('idlm-dialog');
        wp_enqueue_script('idlm-admin-app');
        wp_enqueue_script('idlm-access-account');

        if ('dashboard' === $context || 'workspace' === $context) {
            wp_enqueue_style('idlm-dashboard-styles');
            wp_enqueue_script('idlm-chartjs');
            wp_enqueue_script('idlm-dashboard-app');
        }
        if ('kanban' === $context || 'workspace' === $context) {
            wp_enqueue_style('idlm-kanban-styles');
            wp_enqueue_script('idlm-kanban-app');
        }
        if ('workspace' === $context) {
            wp_enqueue_style('dashicons');
            wp_enqueue_style('idlm-workspace-styles');
            wp_enqueue_script('idlm-workspace-app');
            wp_enqueue_script('idlm-users-logs');
        }
        // A shortcode the post content does not show (block template, page builder) reaches here without
        // enqueue_frontend_assets(). Its templates render the design markup, so the layer must load with it.
        if (!is_admin() && self::design_layer_enabled()) {
            self::maybe_enqueue_design_layer();
            // <body> may already be printed, so set the gate class before the first script reads it.
            wp_add_inline_script('idlm-dialog', "document.body.classList.add('idlm-crm-design');", 'before');
        }
    }
}

add_action('admin_post_idlm_set_design_layer', array('IDLM_Admin_Assets', 'handle_set_design_layer'));
