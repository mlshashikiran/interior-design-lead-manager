<?php
/**
 * Plugin Name:       Interior Design Lead Manager
 * Plugin URI:        https://github.com/shashi-protecto/interior-design-lead-manager
 * Description:       A premium lead management system to replace Google Sheets and Pabbly for interior design firms. Captures leads from ad webhooks, manages routing, schedules custom audience batch uploads, triggers conversions server-side using Action Scheduler, and includes a full analytics workspace.
 * Version:           1.102.91
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Shashi
 * License:           GPL-2.0+
 * Text Domain:       interior-design-lead-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Plugin Constants
define('IDLM_VERSION', '1.102.91');
define('IDLM_DB_VERSION', '1.3.9');
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
require_once IDLM_PATH . 'includes/class-schema-migration.php';
require_once IDLM_PATH . 'includes/class-routing-state.php';
require_once IDLM_PATH . 'includes/class-data-retention.php';
require_once IDLM_PATH . 'includes/class-db.php';
require_once IDLM_PATH . 'includes/class-private-files.php';
require_once IDLM_PATH . 'includes/class-recovery-guard.php';
require_once IDLM_PATH . 'includes/class-lead-transaction.php';
require_once IDLM_PATH . 'includes/class-lead-outbox.php';
require_once IDLM_PATH . 'includes/class-presales-task-plan.php';
require_once IDLM_PATH . 'includes/class-assignment-capacity.php';
require_once IDLM_PATH . 'includes/class-backup-validator.php';
require_once IDLM_PATH . 'includes/class-lead-lifecycle-service.php';
require_once IDLM_PATH . 'includes/class-identity-review.php';
require_once IDLM_PATH . 'includes/class-task-queue-service.php';
require_once IDLM_PATH . 'includes/class-lead-deletion-service.php';
require_once IDLM_PATH . 'includes/class-backup.php';
require_once IDLM_PATH . 'includes/class-integrity-command.php';
require_once IDLM_PATH . 'includes/class-handoff-notifications.php';
require_once IDLM_PATH . 'includes/class-integration-auth.php';
require_once IDLM_PATH . 'includes/class-webhook-intake.php';
require_once IDLM_PATH . 'includes/class-google-feed-controller.php';
require_once IDLM_PATH . 'includes/class-integrations.php';
require_once IDLM_PATH . 'includes/class-capi-delivery.php';
require_once IDLM_PATH . 'includes/class-capi-transport.php';
require_once IDLM_PATH . 'includes/class-google-conversion-feed.php';
require_once IDLM_PATH . 'includes/class-capi.php';
require_once IDLM_PATH . 'includes/class-scheduler.php';
require_once IDLM_PATH . 'includes/class-interakt-batch.php';
require_once IDLM_PATH . 'includes/class-interakt-dispatch.php';
require_once IDLM_PATH . 'includes/class-audiences.php';
require_once IDLM_PATH . 'includes/class-audience-dispatch.php';
require_once IDLM_PATH . 'includes/class-dashboard-read-model.php';
require_once IDLM_PATH . 'includes/class-dashboard.php';
require_once IDLM_PATH . 'includes/class-whatsapp-inbox.php';
require_once IDLM_PATH . 'includes/class-interakt.php';
require_once IDLM_PATH . 'includes/class-lead-connector.php';
require_once IDLM_PATH . 'includes/class-facebook-connect.php';
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
require_once IDLM_PATH . 'includes/class-admin-connection-controller.php';
require_once IDLM_PATH . 'includes/class-admin-telemetry-controller.php';
require_once IDLM_PATH . 'includes/class-admin-activity-controller.php';
require_once IDLM_PATH . 'includes/class-admin-task-controller.php';
require_once IDLM_PATH . 'includes/class-admin-collection-reads.php';
require_once IDLM_PATH . 'includes/class-admin-ajax.php';
require_once IDLM_PATH . 'includes/class-quotation-lead-read-model.php';
require_once IDLM_PATH . 'includes/class-quotation-policy.php';
require_once IDLM_PATH . 'includes/class-quotation-repository.php';
require_once IDLM_PATH . 'includes/class-quotation-schema.php';
require_once IDLM_PATH . 'includes/class-quotation-calculator.php';
require_once IDLM_PATH . 'includes/class-quotation-document.php';
require_once IDLM_PATH . 'includes/class-quotation-exporter.php';
require_once IDLM_PATH . 'includes/class-quotations.php';
require_once IDLM_PATH . 'includes/class-quotation-controller.php';
require_once IDLM_PATH . 'includes/class-quotation-renderer.php';

// Plugin Activation Hook
register_activation_hook(__FILE__, 'idlm_activate_plugin');
function idlm_activate_plugin($network_wide = false) {
    if ($network_wide) {
        wp_die(esc_html__('Network activation is not supported. Use a single-site WordPress installation.', 'interior-design-lead-manager'), '', array('response' => 400));
    }
    // 1. Run custom database migrations & table creations
    $result = IDLM_DB::init();
    if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', array('response' => 503)); }
    $result = IDLM_Routing_State::install();
    if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', array('response' => 503)); }
    $result = IDLM_Quotations::install();
    if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), '', array('response' => 503)); }

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
 * Initial provisioning and the approved legacy access migration are idempotent.
 * Later plugin updates preserve administrator capability removals.
 */
function idlm_register_crm_roles_and_capabilities() {
    $legacy_setup = false !== get_option('idlm_roles_version', false);
    $seeded = get_option('idlm_initialized_crm_roles', null);
    if (!is_array($seeded)) {
        // Existing CRM roles have already been provisioned. Never restore a
        // capability removed by an administrator during a version update.
        $seeded = $legacy_setup ? array_fill_keys(idlm_get_crm_role_slugs(), true) : array();
    }
    $pre_sales_role = add_role('idlm_pre_sales', 'Pre-Sales Representative', array(
        'read' => true, 'idlm_access_leads' => true, 'idlm_qualify_leads' => true,
    ));
    $created_pre_sales = (bool) $pre_sales_role;
    if ($pre_sales_role) { unset($seeded['idlm_pre_sales']); }
    $editorial_roles = array('editor', 'author', 'contributor');
    $new_grants = array();
    foreach (array_diff(idlm_get_crm_role_slugs(), $editorial_roles) as $role_name) {
        $role = get_role($role_name);
        if (!$role || !empty($seeded[$role_name])) { continue; }
        // A newly encountered role may already have deliberately partial or
        // negative IDLM grants. Treat that as provisioned, not a blank default.
        $existing_grants = array_intersect(array_keys($role->capabilities), array_diff(idlm_crm_default_capabilities(true), array('read')));
        if ($existing_grants && !('idlm_pre_sales' === $role_name && $created_pre_sales)) {
            $seeded[$role_name] = true;
            continue;
        }
        $capabilities = idlm_crm_default_capabilities(in_array($role_name, idlm_get_crm_role_slugs('manager'), true));
        if ('idlm_pre_sales' === $role_name) {
            $capabilities = array('read', 'idlm_access_leads', 'idlm_qualify_leads');
        }
        foreach ($capabilities as $capability) { $role->add_cap($capability); }
        $new_grants[$role_name] = $capabilities;
        $seeded[$role_name] = true;
    }
    // Pre-sales cannot acquire manager or customer quotation authority by role.
    $pre_sales_role = get_role('idlm_pre_sales');
    if ($pre_sales_role) {
        foreach (array_diff(idlm_crm_default_capabilities(true), array('read', 'idlm_access_leads', 'idlm_qualify_leads')) as $capability) {
            $pre_sales_role->remove_cap($capability);
        }
    }
    if ('1' !== get_option('idlm_crm_access_policy_version', '')) {
        $old_grants = array();
        foreach ($editorial_roles as $role_name) {
            $role = get_role($role_name);
            if (!$role) { continue; }
            $old_grants[$role_name] = array_intersect_key($role->capabilities, array_flip(idlm_crm_default_capabilities(true)));
            foreach (idlm_crm_default_capabilities(true) as $capability) {
                if ('read' !== $capability) { $role->remove_cap($capability); }
            }
        }
        // Approved security migration: keep explicit role scopes, reset the
        // legacy global default and its grouped compatibility mirror once.
        add_option('idlm_crm_access_migration_v1', array(
            'author' => 'Shashi', 'previous_global_visibility' => IDLM_Settings::get('idlm_reps_see_all_leads', $legacy_setup ? '1' : '0'),
            'removed_role_grants' => $old_grants, 'migrated_at' => current_time('mysql'),
        ), '', false);
        update_option('idlm_reps_see_all_leads', '0', false);
        $routing = get_option(IDLM_Settings::ROUTING_OPTION, array());
        $routing = is_array($routing) ? $routing : array();
        $routing['idlm_reps_see_all_leads'] = '0';
        update_option(IDLM_Settings::ROUTING_OPTION, $routing, false);
    }
    // Verify stored role grants and visibility before advancing setup markers.
    $registry = wp_roles();
    wp_cache_delete($registry->role_key, 'options'); wp_cache_delete('alloptions', 'options');
    $registry->for_site();
    foreach ($new_grants as $role_name => $capabilities) {
        $role = get_role($role_name);
        foreach ($capabilities as $capability) { if (!$role || !$role->has_cap($capability)) { return false; } }
    }
    if ('1' !== get_option('idlm_crm_access_policy_version', '')) {
        foreach ($editorial_roles as $role_name) {
            $role = get_role($role_name);
            foreach (idlm_crm_default_capabilities(true) as $capability) {
                if ($role && 'read' !== $capability && $role->has_cap($capability)) { return false; }
            }
        }
        $stored_routing = get_option(IDLM_Settings::ROUTING_OPTION, array());
        if ('0' !== IDLM_Settings::get('idlm_reps_see_all_leads', '0') || !is_array($stored_routing) || '0' !== ($stored_routing['idlm_reps_see_all_leads'] ?? '')) { return false; }
        update_option('idlm_crm_access_policy_version', '1', false);
    }
    update_option('idlm_initialized_crm_roles', $seeded, false);
    update_option('idlm_roles_version', IDLM_VERSION);
    return true;
}

/** Initial CRM enrollment grants; existing grants are never reconciled upward. */
function idlm_crm_default_capabilities($manager = false) {
    $capabilities = array('read', 'idlm_access_leads', 'idlm_qualify_leads', 'idlm_view_quotes',
        'idlm_create_quotes', 'idlm_edit_quote_drafts', 'idlm_submit_quotes',
        'idlm_mark_quotes_shared', 'idlm_accept_reject_quotes', 'idlm_create_quote_revisions', 'idlm_download_quote_pdf');
    return $manager ? array_merge($capabilities, array('idlm_assign_leads', 'idlm_approve_quotes',
        'idlm_cancel_quotes', 'idlm_manage_quote_settings', 'idlm_manage_quote_items')) : $capabilities;
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
    wp_clear_scheduled_hook('idlm_private_file_maintenance');
}

// Boot up classes on WordPress init hooks
add_action('plugins_loaded', 'idlm_boot_plugin');
function idlm_boot_plugin() {
    IDLM_Recovery_Guard::begin_boot();
    try {
    IDLM_Auth::init();

    if (get_option('idlm_roles_version') !== IDLM_VERSION || '1' !== get_option('idlm_crm_access_policy_version', '')) {
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
    $result = IDLM_DB::init();
    if (!is_wp_error($result) && IDLM_Routing_State::VERSION !== get_option('idlm_routing_schema_version', '')) {
        $result = IDLM_Routing_State::install();
    }
    if (!is_wp_error($result) && !IDLM_Quotation_Schema::is_current()) {
        $result = IDLM_Quotations::install();
    }
    if (is_wp_error($result)) {
        add_action('admin_notices', static function () use ($result) {
            if (current_user_can('manage_options')) { echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>'; }
        });
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0127: idlm_boot_plugin(): typed sanitized metadata is read only after a schema WP_Error to choose the generic 503; it never dispatches an operation; both REST namespaces and cron handling remain, and private repair notices separately require manage_options.
        $action = isset($_REQUEST['action']) && is_string($_REQUEST['action']) ? sanitize_text_field(wp_unslash($_REQUEST['action'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $uri = isset($_SERVER['REQUEST_URI']) && is_string($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0128: idlm_boot_plugin(): typed sanitized metadata is read only after a schema WP_Error to choose the generic 503; it never dispatches an operation; both REST namespaces and cron handling remain, and private repair notices separately require manage_options.
        $rest_route = isset($_GET['rest_route']) && is_string($_GET['rest_route']) ? sanitize_text_field(wp_unslash($_GET['rest_route'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        if (strpos($action, 'idlm_') === 0 || strpos($uri, '/idlm/v1/') !== false || strpos($uri, '/interior-design-lead-manager/v1/') !== false || strpos($rest_route, '/idlm/v1/') !== false || strpos($rest_route, '/interior-design-lead-manager/v1/') !== false || (defined('DOING_CRON') && DOING_CRON)) {
            wp_die('CRM database migration is incomplete. Retry shortly or contact the administrator.', '', array('response' => 503));
        }
        return;
    }
    IDLM_Data_Retention::init();
    IDLM_Integrations::init();
    IDLM_CAPI::init();
    IDLM_Interakt::init();
    IDLM_Lead_Connector::init();
    IDLM_Facebook_Connect::init();
    IDLM_Lead_Outbox::init();
    IDLM_Scheduler::init();
    IDLM_Admin::init();
    IDLM_Identity_Review::init();
    IDLM_Backup::init();
    IDLM_Private_Files::init();
    IDLM_Quotations::init();
    } finally { IDLM_Recovery_Guard::exit_worker(); }
}
