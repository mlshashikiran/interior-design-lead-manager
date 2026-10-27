<?php
/**
 * Frontend CRM shortcode and composite workspace orchestration.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Admin_Shortcodes {
    /** Locate the published page that hosts the complete CRM workspace. */
    public static function workspace_url() {
        static $workspace_url = null;
        if (null !== $workspace_url) {
            return $workspace_url;
        }
        $workspace_url = '';
        if (!function_exists('get_posts') || !function_exists('get_permalink')) {
            return $workspace_url;
        }
        $pages = get_posts(array(
            'post_type' => 'any',
            'post_status' => 'publish',
            'posts_per_page' => 100,
            's' => '[idlm_workspace]',
            'orderby' => 'ID',
            'order' => 'ASC',
        ));
        foreach ((array) $pages as $page) {
            if (!empty($page->post_content) && has_shortcode($page->post_content, 'idlm_workspace')) {
                $workspace_url = (string) get_permalink($page);
                break;
            }
        }
        return $workspace_url;
    }

    /** Render the frontend leads workspace shortcode. */
    public static function render_leads_list_shortcode() {
        if (!IDLM_Lead_Access_Policy::has_rep_access()) {
            return IDLM_View::render('access-restricted.php');
        }

        IDLM_Admin_Assets::enqueue_shortcode_assets('leads');
        ob_start();
        IDLM_Admin::render_leads_list_page();
        return ob_get_clean();
    }

    /** Render the frontend analytics dashboard shortcode. */
    public static function render_dashboard_shortcode() {
        if (!IDLM_Lead_Access_Policy::has_rep_access()) {
            return IDLM_View::render('access-restricted.php');
        }

        IDLM_Admin_Assets::enqueue_shortcode_assets('dashboard');
        ob_start();
        IDLM_Admin::render_dashboard_page();
        return ob_get_clean();
    }

    /** Render the frontend Kanban shortcode. */
    public static function render_kanban_shortcode() {
        if (!IDLM_Lead_Access_Policy::has_rep_access() || IDLM_Lead_Access_Policy::is_pre_sales()) {
            return IDLM_View::render('access-restricted.php');
        }

        IDLM_Admin_Assets::enqueue_shortcode_assets('kanban');
        ob_start();
        IDLM_Admin::render_kanban_page();
        return ob_get_clean();
    }

    /** Render the complete capability-aware CRM workspace. */
    public static function render_workspace_shortcode() {
        if (!IDLM_Lead_Access_Policy::has_rep_access()) {
            return IDLM_View::render('access-restricted.php');
        }

        IDLM_Admin_Assets::enqueue_shortcode_assets('workspace');
        $is_pre_sales = IDLM_Lead_Access_Policy::is_pre_sales();
        $sections = array(
            'dashboard' => array('Dashboard', 'dashicons-chart-area', array('IDLM_Admin', 'render_dashboard_page')),
            'leads' => array('Leads', 'dashicons-groups', array('IDLM_Admin', 'render_leads_list_page')),
        );
        if (!$is_pre_sales) {
            $sections['pipeline'] = array('Pipeline', 'dashicons-screenoptions', array('IDLM_Admin', 'render_kanban_page'));
        }
        if (!$is_pre_sales && current_user_can('idlm_view_quotes')) {
            $sections['quotations'] = array('Quotations', 'dashicons-media-spreadsheet', array('IDLM_Quotations', 'render_shortcode'));
        }
        if (current_user_can('manage_options')) {
            $sections['capi-logs'] = array('CAPI Audit', 'dashicons-shield-alt', array('IDLM_Admin', 'render_capi_logs_page'));
            $sections['audience-logs'] = array('Audience Sync', 'dashicons-update', array('IDLM_Admin', 'render_audience_sync_logs_page'));
            $sections['settings'] = array('Settings', 'dashicons-admin-generic', array('IDLM_Admin', 'render_settings_page'));
            $sections['users'] = array('Users & Access', 'dashicons-admin-users', array('IDLM_Admin', 'render_users_access_page'));
        }
        $sections['my-account'] = array('My Account', 'dashicons-admin-users', array('IDLM_Admin', 'render_my_account_page'));

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0082: render_workspace_shortcode(): current CRM access gates workspace routing; idlm_view/quotation query presence select only an allowlisted view, whose handler retains its own capability/object rules; these GETs do not mutate state.
        $requested = isset($_GET['idlm_view']) ? sanitize_key(wp_unslash($_GET['idlm_view'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0083: render_workspace_shortcode(): current CRM access gates workspace routing; idlm_view/quotation query presence select only an allowlisted view, whose handler retains its own capability/object rules; these GETs do not mutate state.
        if (!$requested && (isset($_GET['workspace']) || isset($_GET['quote_id']) || isset($_GET['lead_id']) || isset($_GET['quote_search']))) {
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
            $requested = 'quotations';
        }
        $active = isset($sections[$requested]) ? $requested : 'dashboard';
        $base_url = remove_query_arg(array('idlm_view', 'workspace', 'action', 'quote_id', 'lead_id', 'revision_id', 'quote_search', 'catalogue_search', 'status', 'created_by', 'date_from', 'date_to', 'idlm_notice', 'idlm_error'));
        $current_user = wp_get_current_user();
        $role_variant = current_user_can('manage_options')
            ? 'administrator'
            : (in_array('idlm_pre_sales', (array) $current_user->roles, true) ? 'pre-sales' : 'representative');

        return IDLM_View::render('workspace.php', array(
            'sections' => $sections,
            'active' => $active,
            'base_url' => $base_url,
            'role_variant' => $role_variant,
            'white_label' => IDLM_Settings::get_white_label(),
        ));
    }
}
