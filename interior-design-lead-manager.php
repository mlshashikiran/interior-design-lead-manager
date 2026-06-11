<?php
/**
 * Plugin Name:       Interior Design Lead Manager
 * Plugin URI:        https://github.com/shashi-protecto/interior-design-lead-manager
 * Description:       A premium lead management system to replace Google Sheets and Pabbly for interior design firms. Captures leads from ad webhooks, manages routing, schedules custom audience batch uploads, triggers conversions server-side using Action Scheduler, and includes a full analytics workspace.
 * Version:           1.2.23
 * Author:            Shashi
 * License:           GPL-2.0+
 * Text Domain:       interior-design-lead-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define Plugin Constants
define('IDLM_VERSION', '1.2.23');
define('IDLM_PATH', plugin_dir_path(__FILE__));
define('IDLM_URL', plugin_dir_url(__FILE__));

// Load core component classes
require_once IDLM_PATH . 'includes/class-db.php';
require_once IDLM_PATH . 'includes/class-integrations.php';
require_once IDLM_PATH . 'includes/class-capi.php';
require_once IDLM_PATH . 'includes/class-scheduler.php';
require_once IDLM_PATH . 'includes/class-audiences.php';
require_once IDLM_PATH . 'includes/class-dashboard.php';
require_once IDLM_PATH . 'includes/class-admin.php';

// Plugin Activation Hook
register_activation_hook(__FILE__, 'idlm_activate_plugin');
function idlm_activate_plugin() {
    // 1. Run custom database migrations & table creations
    IDLM_DB::create_tables();

    // 2. Set up background Action Scheduler or WP Cron schedules
    IDLM_Scheduler::setup_schedules();

    // Log activation in global WordPress options
    update_option('idlm_plugin_activated_at', current_time('mysql'));
}

// Plugin Deactivation Hook
register_deactivation_hook(__FILE__, 'idlm_deactivate_plugin');
function idlm_deactivate_plugin() {
    // Clear scheduled tasks to keep the database tidy
    IDLM_Scheduler::clear_schedules();
}

// Boot up classes on WordPress init hooks
add_action('plugins_loaded', 'idlm_boot_plugin');
function idlm_boot_plugin() {
    IDLM_DB::init();
    IDLM_Integrations::init();
    IDLM_CAPI::init();
    IDLM_Scheduler::init();
    IDLM_Admin::init();
}
