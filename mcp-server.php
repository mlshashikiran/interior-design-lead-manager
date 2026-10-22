<?php
/**
 * Model Context Protocol (MCP) Server for Interior Design Lead Manager CRM
 * Author: Shashi
 * Description: Provides read-only analytical tools and data access for the CRM leads database via Stdio transport.
 */

// Prevent execution in web server context (CLI only)
if ( php_sapi_name() !== 'cli' ) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden: This script can only be run from the command line.';
    exit(1);
}

// Prevent script timeout
// phpcs:disable Squiz.PHP.DiscouragedFunctions.Discouraged -- REL007-BOUNDARY-0005: (guarded template): CLI-only dedicated stdio process; server-owned loader/log environment and PHP error settings preserve JSON stdout; native STDERR is not a filesystem document; no HTTP request can select these settings.
set_time_limit(0);
// phpcs:enable Squiz.PHP.DiscouragedFunctions.Discouraged

// Disable direct PHP error output to prevent corrupting stdout JSON stream
// phpcs:disable PluginCheck.CodeAnalysis.PHPErrorReporting.IniDirectiveDisplay_errors,Squiz.PHP.DiscouragedFunctions.Discouraged -- REL007-BOUNDARY-0006: (guarded template): CLI-only dedicated stdio process; server-owned loader/log environment and PHP error settings preserve JSON stdout; native STDERR is not a filesystem document; no HTTP request can select these settings.
ini_set('display_errors', 0);
// phpcs:enable PluginCheck.CodeAnalysis.PHPErrorReporting.IniDirectiveDisplay_errors,Squiz.PHP.DiscouragedFunctions.Discouraged
// phpcs:disable PluginCheck.CodeAnalysis.PHPErrorReporting.DirectErrorReportingCall,WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting -- REL007-BOUNDARY-0007: (guarded template): CLI-only dedicated stdio process; server-owned loader/log environment and PHP error settings preserve JSON stdout; native STDERR is not a filesystem document; no HTTP request can select these settings.
error_reporting(E_ALL);
// phpcs:enable PluginCheck.CodeAnalysis.PHPErrorReporting.DirectErrorReportingCall,WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting
// phpcs:disable Squiz.PHP.DiscouragedFunctions.Discouraged -- REL007-BOUNDARY-0008: (guarded template): CLI-only dedicated stdio process; server-owned loader/log environment and PHP error settings preserve JSON stdout; native STDERR is not a filesystem document; no HTTP request can select these settings.
ini_set('log_errors', 1);
// phpcs:enable Squiz.PHP.DiscouragedFunctions.Discouraged
$idlm_configured_error_log = getenv('IDLM_MCP_ERROR_LOG');
$idlm_error_log_path = $idlm_configured_error_log ? $idlm_configured_error_log : sys_get_temp_dir() . '/idlm-mcp-error.log';
// phpcs:disable Squiz.PHP.DiscouragedFunctions.Discouraged -- REL007-BOUNDARY-0009: (guarded template): CLI-only dedicated stdio process; server-owned loader/log environment and PHP error settings preserve JSON stdout; native STDERR is not a filesystem document; no HTTP request can select these settings.
ini_set('error_log', $idlm_error_log_path);
// phpcs:enable Squiz.PHP.DiscouragedFunctions.Discouraged

// Suppress WordPress initialization output using output buffering
ob_start();

// Find and load WordPress core
$idlm_configured_wp_load = getenv('IDLM_MCP_WP_LOAD');
$idlm_wp_load_path = $idlm_configured_wp_load ? $idlm_configured_wp_load : dirname(__DIR__, 3) . '/wp-load.php';

if (!file_exists($idlm_wp_load_path)) {
    ob_end_clean();
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
    fwrite(STDERR, "Error: Could not locate wp-load.php at: " . $idlm_wp_load_path . "\n");
    exit(1);
}

require_once $idlm_wp_load_path;

// The selected loader owns ABSPATH, including isolated/explicit host loaders.
if (!defined('ABSPATH')) {
    ob_end_clean();
    // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- REL007-BOUNDARY-0010: (guarded template): CLI-only dedicated stdio process; server-owned loader/log environment and PHP error settings preserve JSON stdout; native STDERR is not a filesystem document; no HTTP request can select these settings.
    fwrite(STDERR, "Error: The selected WordPress loader did not initialize WordPress.\n");
    // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
    exit(1);
}

// Clean and end output buffering
ob_end_clean();

// Ensure global database object is loaded
global $wpdb;
if (!isset($wpdb)) {
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
    fwrite(STDERR, "Error: WordPress database connection is not available.\n");
    exit(1);
}

// Make sure the production policy and core class are loaded
if (!class_exists('IDLM_Production_Security')) {
    require_once __DIR__ . '/includes/class-production-security.php';
}
if (!class_exists('IDLM_MCP_Core')) {
    require_once __DIR__ . '/includes/class-mcp-core.php';
}

// Log startup details to error log
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_error_log -- REL007-BOUNDARY-0011: (guarded template): CLI-only dedicated stdio process; server-owned loader/log environment and PHP error settings preserve JSON stdout; native STDERR is not a filesystem document; no HTTP request can select these settings.
error_log("Interior Design CRM MCP Server started successfully (Stdio mode).");
// phpcs:enable WordPress.PHP.DevelopmentFunctions.error_log_error_log

/**
 * Handle MCP Server Stdio Loop
 */
$idlm_stdin = fopen('php://stdin', 'r');
while ($idlm_line = fgets($idlm_stdin)) {
    $idlm_request = json_decode($idlm_line, true);
    if (!$idlm_request) {
        continue;
    }

    // A long-running stdio process must observe externally changed grants.
    foreach (array('alloptions', 'notoptions', 'idlm_mcp_enabled', 'idlm_mcp_allow_pii', IDLM_Production_Security::MCP_GRANTS_OPTION) as $idlm_policy_cache_key) {
        wp_cache_delete($idlm_policy_cache_key, 'options');
    }

    $idlm_response = IDLM_MCP_Core::handle_json_rpc($idlm_request);
    if ($idlm_response !== null) {
        echo json_encode($idlm_response) . "\n";
        flush();
    }
}
