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

    /**
     * The workspace sections this person may open and the one this request shows.
     * Returns null when they may not use the workspace. The renderer and the asset loader share it.
     */
    public static function workspace_sections() {
        $has_lead_access = IDLM_Lead_Access_Policy::has_rep_access();
        if (!$has_lead_access && !IDLM_Design_Handoffs::can_receive()) {
            return null;
        }
        $is_pre_sales = IDLM_Lead_Access_Policy::is_pre_sales();
        // Each page title repeats its navigation label.
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
        if (!$is_pre_sales && (current_user_can('manage_options') || current_user_can('idlm_record_lead_payments') || current_user_can('idlm_correct_lead_payments'))) {
            // Off the menu when retired; one lead's page still opens from its drawer or Design project.
            $sections['payments'] = array('Payments', 'dashicons-money-alt', array('IDLM_Lead_Payment_Controller', 'render'), !IDLM_Lead_Payment_Controller::tab_shown());
        }
        if (IDLM_Design_Handoffs::can_receive()) {
            $sections[IDLM_Design_Handoff_Controller::VIEW] = array('Design team', 'dashicons-art', array('IDLM_Design_Handoff_Controller', 'render'));
        }
        if (!$has_lead_access) {
            // A design-only person sees their queue and account, and no lead screens.
            unset($sections['dashboard'], $sections['leads'], $sections['pipeline'], $sections['quotations'], $sections['payments']);
        }
        if (current_user_can('manage_options')) {
            $sections['capi-logs'] = array('Ad conversions', 'dashicons-shield-alt', array('IDLM_Admin', 'render_capi_logs_page'));
            $sections['audience-logs'] = array('Ad audiences', 'dashicons-update', array('IDLM_Admin', 'render_audience_sync_logs_page'));
            $sections['settings'] = array('Settings', 'dashicons-admin-generic', array('IDLM_Admin', 'render_settings_page'));
            $sections['users'] = array('Users & access', 'dashicons-admin-users', array('IDLM_Admin', 'render_users_access_page'));
        }
        $sections['my-account'] = array('My account', 'dashicons-admin-users', array('IDLM_Admin', 'render_my_account_page'));

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0082: render_workspace_shortcode(): current CRM access gates workspace routing; idlm_view/quotation query presence select only an allowlisted view, whose handler retains its own capability/object rules; these GETs do not mutate state.
        $requested = isset($_GET['idlm_view']) ? sanitize_key(wp_unslash($_GET['idlm_view'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0083: render_workspace_shortcode(): current CRM access gates workspace routing; idlm_view/quotation query presence select only an allowlisted view, whose handler retains its own capability/object rules; these GETs do not mutate state.
        if (!$requested && (isset($_GET['workspace']) || isset($_GET['quote_id']) || isset($_GET['lead_id']) || isset($_GET['quote_search']))) {
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
            $requested = 'quotations';
        }
        $active = isset($sections[$requested]) ? $requested : ($has_lead_access ? 'dashboard' : IDLM_Design_Handoff_Controller::VIEW);
        return array('sections' => $sections, 'active' => $active, 'has_lead_access' => $has_lead_access);
    }

    /** Render the complete capability-aware CRM workspace. */
    public static function render_workspace_shortcode() {
        $workspace = self::workspace_sections();
        if (null === $workspace) {
            return IDLM_View::render('access-restricted.php');
        }
        IDLM_Admin_Assets::enqueue_shortcode_assets('workspace');
        $sections = $workspace['sections'];
        $active = $workspace['active'];
        $has_lead_access = $workspace['has_lead_access'];
        // Navigation always opens a section's start, so no view's tab, filter or open record follows it.
        $base_url = remove_query_arg(array_merge(array('idlm_view', 'workspace', 'action', 'quote_id', 'lead_id', 'revision_id', 'quote_search', 'catalogue_search', 'status', 'created_by', 'date_from', 'date_to', 'idlm_notice', 'idlm_error'), IDLM_Design_Handoff_Controller::QUERY_KEYS));
        $current_user = wp_get_current_user();
        $role_variant = current_user_can('manage_options')
            ? 'administrator'
            : (in_array('idlm_pre_sales', (array) $current_user->roles, true) ? 'pre-sales' : 'representative');

        $roles = (array) $current_user->roles;
        $role_names = wp_roles()->role_names;
        $first_role = $roles ? reset($roles) : '';
        return IDLM_View::render('workspace.php', array(
            'sections' => $sections,
            'active' => $active,
            'base_url' => $base_url,
            'role_variant' => $role_variant,
            'white_label' => IDLM_Settings::get_white_label(),
            'nav_counts' => self::nav_counts($sections),
            'account_name' => $current_user->display_name,
            'account_role' => isset($role_names[$first_role]) ? translate_user_role($role_names[$first_role]) : '',
        ));
    }

    // phpcs:disable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /**
     * Needs-action counts for the sidebar, never totals. Each count matches what its
     * screen shows and uses that screen's own visibility rules.
     * Returns key => array(count, text, tone).
     */
    private static function nav_counts($sections) {
        global $wpdb;
        $counts = array();
        if (isset($sections['leads']) && IDLM_Lead_Access_Policy::can_assign()) {
            $params = array();
            $scope = IDLM_Lead_Access_Policy::scope_sql('l', $params);
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefix-derived table; the scope clause is built by the access policy with bound values.
            $sql = "SELECT COUNT(l.id) FROM {$wpdb->prefix}idlm_leads l WHERE l.is_duplicate = 0 AND l.handoff_status = 'awaiting_manager' AND COALESCE(l.stage, '') NOT IN ('Won', 'Lost'){$scope}";
            $counts['leads'] = array((int) $wpdb->get_var($params ? $wpdb->prepare($sql, $params) : $sql), 'awaiting assignment', 'warning');
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
        if (isset($sections[IDLM_Design_Handoff_Controller::VIEW]) && IDLM_Design_Handoffs::can_manage()) {
            $scope = IDLM_Design_Handoffs::scope_sql('h');
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Prefix-derived table; the scope clause comes from the handoff policy.
            $counts[IDLM_Design_Handoff_Controller::VIEW] = array((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_design_handoffs h WHERE $scope AND h.status = %s", IDLM_Design_Handoffs::STATUS_NEW)), 'new handoffs', 'warning');
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
        $since = (new DateTime('now', wp_timezone()))->modify('-7 days')->format('Y-m-d H:i:s');
        foreach (array('capi-logs' => 'idlm_capi_logs', 'audience-logs' => 'idlm_audience_sync_logs') as $key => $table) {
            if (!isset($sections[$key])) { continue; }
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Fixed, prefix-derived log tables.
            $counts[$key] = array((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(id) FROM {$wpdb->prefix}{$table} WHERE status <> 'success' AND created_at >= %s", $since)), 'failed in the last 7 days', 'danger');
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
        return array_filter($counts, static function($row) { return $row[0] > 0; });
    }
    // phpcs:enable WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter
}
