<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Admin {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_admin_menus'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        add_action('admin_post_idlm_update_my_account', array(__CLASS__, 'handle_update_my_account'));
        add_action('admin_post_idlm_rotate_api_credential', array(__CLASS__, 'handle_rotate_api_credential'));

        // Shortcodes for frontend lead manager & dashboard
        add_shortcode('idlm_leads_list', array(__CLASS__, 'render_leads_list_shortcode'));
        add_shortcode('idlm_dashboard', array(__CLASS__, 'render_dashboard_shortcode'));
        add_shortcode('idlm_kanban', array(__CLASS__, 'render_kanban_shortcode'));
        add_shortcode('idlm_workspace', array(__CLASS__, 'render_workspace_shortcode'));

        // AJAX endpoints
        add_action('wp_ajax_idlm_get_lead_details', array(__CLASS__, 'ajax_get_lead_details'));
        add_action('wp_ajax_idlm_get_lead_activity', array(__CLASS__, 'ajax_get_lead_activity'));
        add_action('wp_ajax_idlm_quick_edit_lead', array(__CLASS__, 'ajax_quick_edit_lead'));
        add_action('wp_ajax_idlm_save_activity_comment', array(__CLASS__, 'ajax_save_activity_comment'));
        add_action('wp_ajax_idlm_edit_activity_comment', array(__CLASS__, 'ajax_edit_activity_comment'));
        add_action('wp_ajax_idlm_delete_activity_comment', array(__CLASS__, 'ajax_delete_activity_comment'));
        add_action('wp_ajax_idlm_export_leads', array(__CLASS__, 'ajax_export_leads'));
        add_action('wp_ajax_idlm_export_google_conversions', array(__CLASS__, 'ajax_export_google_conversions'));
        add_action('wp_ajax_idlm_add_lead_manual', array(__CLASS__, 'ajax_add_lead_manual'));
        add_action('wp_ajax_idlm_trigger_manual_audience_sync', array(__CLASS__, 'ajax_trigger_manual_audience_sync'));
        add_action('wp_ajax_idlm_bulk_action', array(__CLASS__, 'ajax_bulk_action'));
        add_action('wp_ajax_idlm_get_leads_table', array(__CLASS__, 'ajax_get_leads_table'));
        add_action('wp_ajax_idlm_get_kanban_leads', array(__CLASS__, 'ajax_get_kanban_leads'));
        add_action('wp_ajax_idlm_get_dashboard_stats', array(__CLASS__, 'ajax_get_dashboard_stats'));
        add_action('wp_ajax_idlm_save_lead_details', array(__CLASS__, 'ajax_save_lead_details'));
        add_action('wp_ajax_idlm_delete_lead', array(__CLASS__, 'ajax_delete_lead'));
        add_action('wp_ajax_idlm_import_leads_batch', array(__CLASS__, 'ajax_import_leads_batch'));
        add_action('wp_ajax_idlm_upload_lead_file', array(__CLASS__, 'ajax_upload_lead_file'));
        add_action('wp_ajax_idlm_get_total_leads_count', array(__CLASS__, 'ajax_get_total_leads_count'));
        add_action('wp_ajax_idlm_reset_crm_data', array(__CLASS__, 'ajax_reset_crm_data'));
        add_action('wp_ajax_idlm_test_api_connection', array(__CLASS__, 'ajax_test_api_connection'));
        add_action('wp_ajax_idlm_retry_capi_events', array(__CLASS__, 'ajax_retry_capi_events'));
        add_action('wp_ajax_idlm_log_js_error', array(__CLASS__, 'ajax_log_js_error'));
        add_action('wp_ajax_idlm_clear_telemetry_logs', array(__CLASS__, 'ajax_clear_telemetry_logs'));
        add_action('wp_ajax_idlm_download_diagnostic_report', array(__CLASS__, 'ajax_download_diagnostic_report'));
        add_action('wp_ajax_idlm_save_routing_settings', array(__CLASS__, 'ajax_save_routing_settings'));
        add_action('wp_ajax_idlm_run_fallback_now', array(__CLASS__, 'ajax_run_fallback_now'));
        add_action('wp_ajax_idlm_log_presales_touch', array(__CLASS__, 'ajax_log_presales_touch'));
        add_action('wp_ajax_idlm_presales_decision', array(__CLASS__, 'ajax_presales_decision'));
        add_action('wp_ajax_idlm_get_mapped_past_leads', array(__CLASS__, 'ajax_get_mapped_past_leads'));
        add_action('wp_ajax_idlm_sync_mapped_past_batch', array(__CLASS__, 'ajax_sync_mapped_past_batch'));

        // Task Management Endpoints
        add_action('wp_ajax_idlm_get_lead_tasks', array(__CLASS__, 'ajax_get_lead_tasks'));
        add_action('wp_ajax_idlm_add_lead_task', array(__CLASS__, 'ajax_add_lead_task'));
        add_action('wp_ajax_idlm_toggle_task_status', array(__CLASS__, 'ajax_toggle_task_status'));
        add_action('wp_ajax_idlm_delete_lead_task', array(__CLASS__, 'ajax_delete_lead_task'));
        add_action('wp_ajax_idlm_get_rep_tasks', array(__CLASS__, 'ajax_get_rep_tasks'));
    }

    /**
     * Get list of allowed roles for representative users
     */
    public static function get_allowed_rep_roles() {
        return IDLM_Lead_Access_Policy::allowed_rep_roles();
    }

    /**
     * Whether the current user may change lead ownership.
     */
    public static function current_user_can_assign_leads() {
        return IDLM_Lead_Access_Policy::can_assign();
    }

    /**
     * Whether the current user belongs to the dedicated pre-sales intake role.
     */
    public static function current_user_is_pre_sales() {
        return IDLM_Lead_Access_Policy::is_pre_sales();
    }

    /**
     * Return the user-facing qualification status without changing stored CRM values.
     */
    public static function get_qualification_status_label($lead) {
        return IDLM_Admin_Presenter::get_qualification_status_label($lead);
    }

    /**
     * Validate that an ownership target is a CRM representative.
     */
    public static function is_assignable_rep($user_id) {
        return IDLM_Lead_Access_Policy::is_assignable_rep($user_id);
    }

    /**
     * Return user IDs currently serving in the pre-sales role.
     */
    public static function get_pre_sales_user_ids() {
        return IDLM_Lead_Access_Policy::pre_sales_user_ids();
    }

    /**
     * Enforce private ownership for pre-sales users even when global rep visibility is enabled.
     */
    public static function current_user_can_access_lead($lead_id) {
        return IDLM_Lead_Access_Policy::can_access($lead_id);
    }

    /**
     * Allow pre-sales users to read their completed qualification history while
     * keeping all mutation checks restricted to current ownership.
     */
    public static function current_user_can_view_lead($lead_id) {
        return IDLM_Lead_Access_Policy::can_access($lead_id, true);
    }

    /**
     * Format a database datetime string using the WordPress timezone settings
     */
    public static function format_datetime($mysql_date, $format = 'M d, Y h:i A') {
        return IDLM_Admin_Presenter::format_datetime($mysql_date, $format);
    }

    /** Count configured business dates inclusively for pre-sales queue ageing. */
    protected static function get_business_day_age($created_at) {
        return IDLM_Admin_Presenter::get_business_day_age($created_at);
    }

    /**
     * Render activity details while suppressing legacy Interakt media URL lines,
     * which are not accessible outside the Interakt session.
     */
    public static function render_activity_details($details) {
        return IDLM_Admin_Presenter::render_activity_details($details);
    }

    /**
     * Check if the current user has representative access (administrator or allowed rep role)
     */
    public static function current_user_has_rep_access() {
        return IDLM_Lead_Access_Policy::has_rep_access();
    }

    /**
     * Return the latest WhatsApp activity IDs viewed by the current representative.
     */
    public static function get_whatsapp_read_activity_ids() {
        return IDLM_Admin_Lead_Notifications::get_whatsapp_read_activity_ids();
    }

    /**
     * Mark a lead's latest WhatsApp reply as viewed by the current representative.
     */
    public static function mark_whatsapp_reply_read($lead_id, $activity_id) {
        return IDLM_Admin_Lead_Notifications::mark_whatsapp_reply_read($lead_id, $activity_id);
    }

    /**
     * Return lead IDs with WhatsApp replies not yet viewed by the current representative.
     */
    public static function get_unread_whatsapp_lead_ids() {
        return IDLM_Admin_Lead_Notifications::get_unread_whatsapp_lead_ids();
    }

    /**
     * Return the number of unread WhatsApp message activities visible to the
     * current user. The filter itself remains lead-based, but the notification
     * badge reflects every unread inbound message.
     */
    public static function get_unread_whatsapp_count() {
        return IDLM_Admin_Lead_Notifications::get_unread_whatsapp_count();
    }

    /**
     * Return lead IDs that have received at least one WhatsApp reply.
     */
    public static function get_whatsapp_received_lead_ids() {
        return IDLM_Admin_Lead_Notifications::get_whatsapp_received_lead_ids();
    }

    /**
     * Get the configured monetary field consolidated in each Kanban stage header.
     */
    public static function get_kanban_stage_total_mappings($stages) {
        return IDLM_Admin_Presenter::get_kanban_stage_total_mappings($stages);
    }


    /**
     * Get all active follow-up reminder rules (with fallback to singular option settings)
     */
    public static function get_reminder_rules() {
        return IDLM_Admin_Lead_Notifications::get_reminder_rules();
    }

    /**
     * Check if a lead's follow-up is overdue based on rules
     */
    public static function check_lead_overdue_status($lead, $rules = null) {
        return IDLM_Admin_Lead_Notifications::check_lead_overdue_status($lead, $rules);
    }

    public static function get_pending_calls_count() {
        return IDLM_Admin_Lead_Notifications::get_pending_calls_count();
    }

    /**
     * Register shared core style and script assets
     */
    public static function register_core_assets() {
        return IDLM_Admin_Assets::register_core_assets();
    }

    /**
     * Enqueue Google Fonts, Chart.js, styles and admin client JS inside backend
     */
    public static function enqueue_assets($hook) {
        return IDLM_Admin_Assets::enqueue_assets($hook);
    }

    /**
     * Conditionally enqueue style and script assets on frontend pages
     */
    public static function enqueue_frontend_assets() {
        return IDLM_Admin_Assets::enqueue_frontend_assets();
    }

    /**
     * Frontend Shortcode handler for Leads List Workspace [idlm_leads_list]
     */
    public static function render_leads_list_shortcode() {
        return IDLM_Admin_Shortcodes::render_leads_list_shortcode();
    }

    /**
     * Frontend Shortcode handler for Analytics Dashboard [idlm_dashboard]
     */
    public static function render_dashboard_shortcode() {
        return IDLM_Admin_Shortcodes::render_dashboard_shortcode();
    }

    /**
     * Frontend Shortcode handler for Kanban Board [idlm_kanban]
     */
    public static function render_kanban_shortcode() {
        return IDLM_Admin_Shortcodes::render_kanban_shortcode();
    }

    /**
     * Render the complete CRM in one frontend workspace.
     *
     * The legacy module shortcodes remain available for existing pages. This
     * shell provides one stable page and a collapsible, capability-aware menu.
     */
    public static function render_workspace_shortcode() {
        return IDLM_Admin_Shortcodes::render_workspace_shortcode();
    }

    /**
     * Render the current user's frontend account workspace.
     */
    public static function render_my_account_page() {
        return IDLM_Admin_Account_Controller::render();
    }

    /**
     * Save the current user's frontend account details.
     */
    public static function handle_update_my_account() {
        return IDLM_Admin_Account_Controller::handle_update();
    }

    /** Rotate a scoped REST credential after explicit administrator confirmation. */
    public static function handle_rotate_api_credential() {
        return IDLM_Admin_Settings_Controller::handle_rotate_api_credential();
    }

    /**
     * Set up admin menus
     */
    public static function register_admin_menus() {
        add_menu_page(
            'Lead Manager',
            'Lead Manager',
            'read', // Reps can see leads page, access logic restricted per lead
            'interior-design-lead-manager',
            array(__CLASS__, 'render_dashboard_page'),
            'dashicons-groups',
            26
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'Dashboard',
            'Dashboard',
            'read',
            'interior-design-lead-manager',
            array(__CLASS__, 'render_dashboard_page')
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'Leads List',
            'Leads List',
            'read',
            'idlm_leads_list',
            array(__CLASS__, 'render_leads_list_page')
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'Kanban Board',
            'Kanban Board',
            'read',
            'idlm_kanban',
            array(__CLASS__, 'render_kanban_page')
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'CAPI Audit Logs',
            'CAPI Audit Logs',
            'manage_options',
            'idlm_capi_logs',
            array(__CLASS__, 'render_capi_logs_page')
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'Audience Sync Logs',
            'Audience Sync Logs',
            'manage_options',
            'idlm_audience_sync_logs',
            array(__CLASS__, 'render_audience_sync_logs_page')
        );

        add_submenu_page(
            'interior-design-lead-manager',
            'Settings',
            'Settings',
            'manage_options',
            'idlm_settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    /**
     * Dashboard Render Page
     */
    public static function render_dashboard_page() {
        return IDLM_Admin_Dashboard_Renderer::render();
    }

    /**
     * Render Leads List Page
     */
    public static function render_leads_list_page() {
        return IDLM_Admin_Leads_Renderer::render();
    }

    /**
     * Render common overlays (lead drawer, scheduler, deal value modal, unsaved changes modal, global tasks sidebar)
     */
    public static function render_common_overlays() {
        return IDLM_Admin_Leads_Renderer::render_common_overlays();
    }

    /**
     * Render Kanban Board Page
     */
    public static function render_kanban_page() {
        return IDLM_Admin_Kanban_Renderer::render();
    }

    /**
     * CAPI Log View
     */
    public static function render_capi_logs_page() {
        return IDLM_Admin_Logs_Renderer::render_capi();
    }

    /**
     * Audience Sync Logs View
     */
    public static function render_audience_sync_logs_page() {
        return IDLM_Admin_Logs_Renderer::render_audience();
    }

    /**
     * Settings Page Render
     */
    public static function render_settings_page() {
        return IDLM_Admin_Settings_Renderer::render();
    }
    public static function ajax_get_lead_details() { return IDLM_Admin_Ajax::ajax_get_lead_details(); }
    public static function ajax_get_lead_activity() { return IDLM_Admin_Ajax::ajax_get_lead_activity(); }
    public static function ajax_quick_edit_lead() { return IDLM_Admin_Ajax::ajax_quick_edit_lead(); }
    public static function ajax_log_presales_touch() { return IDLM_Admin_Ajax::ajax_log_presales_touch(); }
    public static function ajax_presales_decision() { return IDLM_Admin_Ajax::ajax_presales_decision(); }
    public static function ajax_save_activity_comment() { return IDLM_Admin_Ajax::ajax_save_activity_comment(); }
    public static function ajax_edit_activity_comment() { return IDLM_Admin_Ajax::ajax_edit_activity_comment(); }
    public static function ajax_delete_activity_comment() { return IDLM_Admin_Ajax::ajax_delete_activity_comment(); }
    public static function ajax_export_google_conversions() { return IDLM_Admin_Ajax::ajax_export_google_conversions(); }
    public static function ajax_export_leads() { return IDLM_Admin_Ajax::ajax_export_leads(); }
    public static function ajax_add_lead_manual() { return IDLM_Admin_Ajax::ajax_add_lead_manual(); }
    public static function ajax_trigger_manual_audience_sync() { return IDLM_Admin_Ajax::ajax_trigger_manual_audience_sync(); }
    public static function ajax_test_api_connection() { return IDLM_Admin_Ajax::ajax_test_api_connection(); }
    public static function ajax_retry_capi_events() { return IDLM_Admin_Ajax::ajax_retry_capi_events(); }
    public static function ajax_log_js_error() { return IDLM_Admin_Ajax::ajax_log_js_error(); }
    public static function ajax_clear_telemetry_logs() { return IDLM_Admin_Ajax::ajax_clear_telemetry_logs(); }
    public static function ajax_download_diagnostic_report() { return IDLM_Admin_Ajax::ajax_download_diagnostic_report(); }
    public static function ajax_save_routing_settings() { return IDLM_Admin_Ajax::ajax_save_routing_settings(); }
    public static function ajax_run_fallback_now() { return IDLM_Admin_Ajax::ajax_run_fallback_now(); }
    public static function ajax_get_mapped_past_leads() { return IDLM_Admin_Ajax::ajax_get_mapped_past_leads(); }
    public static function ajax_sync_mapped_past_batch() { return IDLM_Admin_Ajax::ajax_sync_mapped_past_batch(); }
    public static function ajax_bulk_action() { return IDLM_Admin_Ajax::ajax_bulk_action(); }
    public static function ajax_get_leads_table() { return IDLM_Admin_Ajax::ajax_get_leads_table(); }
    public static function ajax_get_kanban_leads() { return IDLM_Admin_Ajax::ajax_get_kanban_leads(); }
    public static function ajax_get_dashboard_stats() { return IDLM_Admin_Ajax::ajax_get_dashboard_stats(); }
    public static function ajax_save_lead_details() { return IDLM_Admin_Ajax::ajax_save_lead_details(); }
    public static function ajax_upload_lead_file() { return IDLM_Admin_Ajax::ajax_upload_lead_file(); }
    public static function ajax_get_total_leads_count() { return IDLM_Admin_Ajax::ajax_get_total_leads_count(); }
    public static function ajax_reset_crm_data() { return IDLM_Admin_Ajax::ajax_reset_crm_data(); }
    public static function ajax_delete_lead() { return IDLM_Admin_Ajax::ajax_delete_lead(); }
    public static function ajax_import_leads_batch() { return IDLM_Admin_Ajax::ajax_import_leads_batch(); }
    public static function ajax_get_lead_tasks() { return IDLM_Admin_Ajax::ajax_get_lead_tasks(); }
    public static function ajax_add_lead_task() { return IDLM_Admin_Ajax::ajax_add_lead_task(); }
    public static function ajax_toggle_task_status() { return IDLM_Admin_Ajax::ajax_toggle_task_status(); }
    public static function ajax_delete_lead_task() { return IDLM_Admin_Ajax::ajax_delete_lead_task(); }
    public static function ajax_get_rep_tasks() { return IDLM_Admin_Ajax::ajax_get_rep_tasks(); }
}
