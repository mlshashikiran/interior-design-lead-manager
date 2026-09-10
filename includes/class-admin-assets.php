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
    private static $white_label_style_added = false;

    /** Use each local asset's modification time to avoid stale frontend bundles. */
    private static function asset_version($relative_path) {
        $path = dirname(__DIR__) . '/assets/' . ltrim((string) $relative_path, '/');
        return is_file($path) ? (string) filemtime($path) : IDLM_VERSION;
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

        wp_register_script('idlm-dialog', $asset_url . 'js/dialog.js', array(), IDLM_VERSION, true);
        wp_register_script('idlm-admin-app', $asset_url . 'js/admin-app.js', array('jquery', 'idlm-dialog'), IDLM_VERSION, true);
        wp_register_script('idlm-settings', $asset_url . 'js/settings.js', array('jquery', 'idlm-admin-app'), IDLM_VERSION, true);
        wp_register_script('idlm-users-logs', $asset_url . 'js/users-logs.js', array('idlm-admin-app', 'idlm-dialog'), IDLM_VERSION, true);
        wp_register_script('idlm-dashboard-app', $asset_url . 'js/dashboard.js', array('jquery', 'idlm-chartjs'), self::asset_version('js/dashboard.js'), true);
        wp_register_script('idlm-kanban-app', $asset_url . 'js/kanban.js', array('jquery', 'idlm-admin-app'), IDLM_VERSION, true);
        // Load before the workspace can paint so its saved collapsed state prevents a sidebar flash.
        wp_register_script('idlm-workspace-app', $asset_url . 'js/workspace.js', array(), self::asset_version('js/workspace.js'), false);
        wp_register_script('idlm-access-account', $asset_url . 'js/access-account.js', array(), IDLM_VERSION, true);

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

    /** Conditionally enqueue the established frontend shortcode asset sets. */
    public static function enqueue_frontend_assets() {
        global $post;
        if (!is_a($post, 'WP_Post')) {
            return;
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
    }
}
