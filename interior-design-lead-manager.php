<?php
/**
 * Plugin Name:       Interior Design Lead Manager
 * Plugin URI:        https://github.com/shashi-protecto/interior-design-lead-manager
 * Description:       A premium lead management system to replace Google Sheets and Pabbly for interior design firms. Captures leads from ad webhooks, manages routing, schedules custom audience batch uploads, triggers conversions server-side using Action Scheduler, and includes a full analytics workspace.
 * Version:           1.102.31
 * Requires PHP:      7.4
 * Author:            Shashi
 * License:           GPL-2.0+
 * Text Domain:       interior-design-lead-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Plugin Constants
define('IDLM_VERSION', '1.102.31');
define('IDLM_DB_VERSION', '1.3.3');
define('IDLM_PATH', plugin_dir_path(__FILE__));
define('IDLM_URL', plugin_dir_url(__FILE__));

// Load core component classes
require_once IDLM_PATH . 'includes/class-mcp-core.php';
require_once IDLM_PATH . 'includes/class-telemetry.php';
require_once IDLM_PATH . 'includes/class-view.php';
require_once IDLM_PATH . 'includes/class-auth.php';
require_once IDLM_PATH . 'includes/class-settings.php';
require_once IDLM_PATH . 'includes/class-secret-store.php';
require_once IDLM_PATH . 'includes/class-production-security.php';
require_once IDLM_PATH . 'includes/class-horizontal-readiness.php';
require_once IDLM_PATH . 'includes/class-operational-health.php';
require_once IDLM_PATH . 'includes/class-state-registry.php';
require_once IDLM_PATH . 'includes/class-lead-access-policy.php';
require_once IDLM_PATH . 'includes/class-read-model-cache.php';
require_once IDLM_PATH . 'includes/class-cursor-pagination.php';
require_once IDLM_PATH . 'includes/class-routing-state.php';
require_once IDLM_PATH . 'includes/class-data-retention.php';
require_once IDLM_PATH . 'includes/class-db.php';
require_once IDLM_PATH . 'includes/class-lead-lifecycle-service.php';
require_once IDLM_PATH . 'includes/class-task-queue-service.php';
require_once IDLM_PATH . 'includes/class-lead-deletion-service.php';
require_once IDLM_PATH . 'includes/class-backup.php';
require_once IDLM_PATH . 'includes/class-integrity-command.php';
require_once IDLM_PATH . 'includes/class-integrations.php';
require_once IDLM_PATH . 'includes/class-capi.php';
require_once IDLM_PATH . 'includes/class-scheduler.php';
require_once IDLM_PATH . 'includes/class-interakt-batch.php';
require_once IDLM_PATH . 'includes/class-audiences.php';
require_once IDLM_PATH . 'includes/class-dashboard-read-model.php';
require_once IDLM_PATH . 'includes/class-dashboard.php';
require_once IDLM_PATH . 'includes/class-interakt.php';
require_once IDLM_PATH . 'includes/class-admin-dashboard-renderer.php';
require_once IDLM_PATH . 'includes/class-admin-kanban-renderer.php';
require_once IDLM_PATH . 'includes/class-admin-logs-renderer.php';
require_once IDLM_PATH . 'includes/class-admin-leads-renderer.php';
require_once IDLM_PATH . 'includes/class-admin-settings-controller.php';
require_once IDLM_PATH . 'includes/class-admin-settings-renderer.php';
require_once IDLM_PATH . 'includes/class-admin-presenter.php';
require_once IDLM_PATH . 'includes/class-admin-lead-notifications.php';
require_once IDLM_PATH . 'includes/class-admin-assets.php';
require_once IDLM_PATH . 'includes/class-admin-shortcodes.php';
require_once IDLM_PATH . 'includes/class-admin-account-controller.php';
require_once IDLM_PATH . 'includes/class-admin-users-controller.php';
require_once IDLM_PATH . 'includes/class-admin.php';
require_once IDLM_PATH . 'includes/class-admin-lead-reads.php';
require_once IDLM_PATH . 'includes/class-admin-ajax.php';
require_once IDLM_PATH . 'includes/class-quotation-lead-read-model.php';
require_once IDLM_PATH . 'includes/class-quotation-policy.php';
require_once IDLM_PATH . 'includes/class-quotation-repository.php';
require_once IDLM_PATH . 'includes/class-quotation-schema.php';
require_once IDLM_PATH . 'includes/class-quotation-calculator.php';
require_once IDLM_PATH . 'includes/class-quotations.php';
require_once IDLM_PATH . 'includes/class-quotation-controller.php';
require_once IDLM_PATH . 'includes/class-quotation-renderer.php';

// Plugin Activation Hook
register_activation_hook(__FILE__, 'idlm_activate_plugin');
function idlm_activate_plugin() {
    // 1. Run custom database migrations & table creations
    IDLM_DB::create_tables();
    if ('1.0.0' !== get_option('idlm_routing_schema_version', '')) {
        IDLM_Routing_State::install();
    }
    IDLM_Quotations::install();
    update_option('idlm_schema_version', IDLM_DB_VERSION);

    // 2. Set up background Action Scheduler or WP Cron schedules
    IDLM_Scheduler::setup_schedules();

    // 3. Register CRM roles and assignment capabilities.
    idlm_register_crm_roles_and_capabilities();

    // Log activation in global WordPress options
    update_option('idlm_plugin_activated_at', current_time('mysql'));
}

/**
 * Register the pre-sales role and keep assignment authority limited to managers.
 *
 * This routine is intentionally idempotent so existing installations receive the
 * role and capabilities after a normal plugin update without reactivation.
 */
function idlm_register_crm_roles_and_capabilities() {
    $pre_sales_role = add_role(
        'idlm_pre_sales',
        'Pre-Sales Representative',
        array(
            'read'                    => true,
            'idlm_access_leads'       => true,
            'idlm_qualify_leads'      => true,
        )
    );

    if (!$pre_sales_role) {
        $pre_sales_role = get_role('idlm_pre_sales');
    }

    if ($pre_sales_role) {
        $pre_sales_role->add_cap('read');
        $pre_sales_role->add_cap('idlm_access_leads');
        $pre_sales_role->add_cap('idlm_qualify_leads');
        $pre_sales_role->remove_cap('idlm_assign_leads');
    }

    foreach (idlm_get_crm_role_slugs() as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->add_cap('idlm_access_leads');
            $role->add_cap('idlm_qualify_leads');
            $role->add_cap('idlm_view_quotes');
            $role->add_cap('idlm_create_quotes');
            $role->add_cap('idlm_edit_quote_drafts');
            $role->add_cap('idlm_submit_quotes');
            $role->add_cap('idlm_mark_quotes_shared');
            $role->add_cap('idlm_accept_reject_quotes');
            $role->add_cap('idlm_create_quote_revisions');
            $role->add_cap('idlm_download_quote_pdf');
        }
    }

    // Pre-sales may see the commercial summary after handoff, but cannot
    // author or progress quotations.
    if ($pre_sales_role) {
        foreach (array(
            'idlm_create_quotes', 'idlm_edit_quote_drafts', 'idlm_submit_quotes',
            'idlm_mark_quotes_shared', 'idlm_accept_reject_quotes',
            'idlm_create_quote_revisions', 'idlm_approve_quotes',
            'idlm_cancel_quotes', 'idlm_manage_quote_settings',
            'idlm_manage_quote_items', 'idlm_download_quote_pdf',
        ) as $capability) {
            $pre_sales_role->remove_cap($capability);
        }
    }

    foreach (idlm_get_crm_role_slugs('manager') as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->add_cap('idlm_assign_leads');
            $role->add_cap('idlm_approve_quotes');
            $role->add_cap('idlm_cancel_quotes');
            $role->add_cap('idlm_manage_quote_settings');
            $role->add_cap('idlm_manage_quote_items');
        }
    }

    update_option('idlm_roles_version', IDLM_VERSION);
}

/**
 * Resolve CRM roles by both stored slug and administrator-facing role name.
 *
 * Production sites often receive Sales Manager and Sales Executive roles from
 * another plugin, so their internal slugs are not guaranteed to match ours.
 *
 * @param string $scope Optional role group: all or manager.
 * @return array
 */
function idlm_get_crm_role_slugs($scope = 'all') {
    $known_roles = array(
        'administrator', 'editor', 'author', 'contributor', 'marketing',
        'sales_manager', 'sales-manager', 'lead_owner', 'lead-owner',
        'marketing_manager', 'marketing-manager', 'sales_executive',
        'sales-executive', 'agent', 'agentadmin', 'idlm_pre_sales',
    );
    $manager_roles = array('administrator', 'sales_manager', 'sales-manager', 'agentadmin');
    $recognized_names = array(
        'marketing', 'salesmanager', 'leadowner', 'marketingmanager',
        'salesexecutive', 'agent', 'agentadmin', 'presalesrepresentative',
    );
    $manager_names = array('salesmanager', 'agentadmin');

    foreach (wp_roles()->roles as $role_slug => $role_data) {
        $normalized_slug = preg_replace('/[^a-z0-9]/', '', strtolower((string) $role_slug));
        $normalized_name = preg_replace('/[^a-z0-9]/', '', strtolower(wp_strip_all_tags(translate_user_role($role_data['name']))));
        if (in_array($normalized_slug, $recognized_names, true) || in_array($normalized_name, $recognized_names, true)) {
            $known_roles[] = $role_slug;
        }
        if (in_array($normalized_slug, $manager_names, true) || in_array($normalized_name, $manager_names, true)) {
            $manager_roles[] = $role_slug;
        }
    }

    return array_values(array_unique('manager' === $scope ? $manager_roles : $known_roles));
}

// Plugin Deactivation Hook
register_deactivation_hook(__FILE__, 'idlm_deactivate_plugin');
function idlm_deactivate_plugin() {
    // Clear scheduled tasks to keep the database tidy
    IDLM_Scheduler::clear_schedules();
    IDLM_Data_Retention::clear();
}

// Boot up classes on WordPress init hooks
add_action('plugins_loaded', 'idlm_boot_plugin');
function idlm_boot_plugin() {
    IDLM_Auth::init();

    if (get_option('idlm_roles_version') !== IDLM_VERSION) {
        idlm_register_crm_roles_and_capabilities();
    }

    $request_script = isset($_SERVER['SCRIPT_NAME']) ? basename(sanitize_text_field(wp_unslash($_SERVER['SCRIPT_NAME']))) : '';
    $current_page = isset($GLOBALS['pagenow']) ? (string) $GLOBALS['pagenow'] : '';

    // Keep login requests limited to the scoped authentication presentation hooks.
    // Avoid loading the CRM database, integrations, scheduler, and admin runtime.
    if ('wp-login.php' === $request_script || 'wp-login.php' === $current_page) {
        return;
    }

    IDLM_Read_Model_Cache::init();
    IDLM_Telemetry::init_handlers();
    IDLM_Settings::init();
    IDLM_DB::init();
    if ('1.0.0' !== get_option('idlm_routing_schema_version', '')) {
        IDLM_Routing_State::install();
    }
    IDLM_Data_Retention::init();
    IDLM_Integrations::init();
    IDLM_CAPI::init();
    IDLM_Interakt::init();
    IDLM_Scheduler::init();
    IDLM_Admin::init();
    IDLM_Backup::init();
    IDLM_Quotations::init();
}
