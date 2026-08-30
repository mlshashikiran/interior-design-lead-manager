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
        if (!IDLM_Lead_Access_Policy::has_rep_access()) {
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
        $sections = array(
            'dashboard' => array('Dashboard', 'dashicons-chart-area', array('IDLM_Admin', 'render_dashboard_page')),
            'leads' => array('Leads', 'dashicons-groups', array('IDLM_Admin', 'render_leads_list_page')),
            'pipeline' => array('Pipeline', 'dashicons-screenoptions', array('IDLM_Admin', 'render_kanban_page')),
        );
        if (current_user_can('idlm_view_quotes')) {
            $sections['quotations'] = array('Quotations', 'dashicons-media-spreadsheet', array('IDLM_Quotations', 'render_shortcode'));
        }
        if (current_user_can('manage_options')) {
            $sections['capi-logs'] = array('CAPI Audit', 'dashicons-shield-alt', array('IDLM_Admin', 'render_capi_logs_page'));
            $sections['audience-logs'] = array('Audience Sync', 'dashicons-update', array('IDLM_Admin', 'render_audience_sync_logs_page'));
            $sections['settings'] = array('Settings', 'dashicons-admin-generic', array('IDLM_Admin', 'render_settings_page'));
        }
        $sections['my-account'] = array('My Account', 'dashicons-admin-users', array('IDLM_Admin', 'render_my_account_page'));

        $requested = isset($_GET['idlm_view']) ? sanitize_key(wp_unslash($_GET['idlm_view'])) : '';
        if (!$requested && (isset($_GET['workspace']) || isset($_GET['quote_id']) || isset($_GET['lead_id']) || isset($_GET['quote_search']))) {
            $requested = 'quotations';
        }
        $active = isset($sections[$requested]) ? $requested : 'dashboard';
        $base_url = remove_query_arg(array('idlm_view', 'workspace', 'action', 'quote_id', 'lead_id', 'revision_id', 'quote_search', 'catalogue_search', 'status', 'created_by', 'date_from', 'date_to', 'idlm_notice', 'idlm_error'));

        return IDLM_View::render('workspace.php', array(
            'sections' => $sections,
            'active' => $active,
            'base_url' => $base_url,
        ));
    }
}
