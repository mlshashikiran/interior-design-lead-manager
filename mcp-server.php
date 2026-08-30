<?php
/**
 * Model Context Protocol (MCP) Server for Interior Design Lead Manager CRM
 * Author: Shashi
 * Description: Provides read-only analytical tools and data access for the CRM leads database via Stdio transport.
 */

// Prevent direct file access
if ( php_sapi_name() === 'cli' ) {
    if ( ! defined( 'ABSPATH' ) ) {
        define( 'ABSPATH', dirname( __DIR__, 3 ) . '/' );
    }
}

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Prevent execution in web server context (CLI only)
if ( php_sapi_name() !== 'cli' ) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden: This script can only be run from the command line.';
    exit(1);
}

// Prevent script timeout
set_time_limit(0);

// Disable direct PHP error output to prevent corrupting stdout JSON stream
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
$configured_error_log = getenv('IDLM_MCP_ERROR_LOG');
$error_log_path = $configured_error_log ? $configured_error_log : sys_get_temp_dir() . '/idlm-mcp-error.log';
ini_set('error_log', $error_log_path);

// Suppress WordPress initialization output using output buffering
ob_start();

// Find and load WordPress core
$configured_wp_load = getenv('IDLM_MCP_WP_LOAD');
$wp_load_path = $configured_wp_load ? $configured_wp_load : dirname(__DIR__, 3) . '/wp-load.php';

if (!file_exists($wp_load_path)) {
    ob_end_clean();
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
    fwrite(STDERR, "Error: Could not locate wp-load.php at: " . $wp_load_path . "\n");
    exit(1);
}

require_once $wp_load_path;

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
error_log("Interior Design CRM MCP Server started successfully (Stdio mode).");

/**
 * Handle MCP Server Stdio Loop
 */
$stdin = fopen('php://stdin', 'r');
while ($line = fgets($stdin)) {
    $request = json_decode($line, true);
    if (!$request) {
        continue;
    }

    $response = IDLM_MCP_Core::handle_json_rpc($request);
    if ($response !== null) {
        echo json_encode($response) . "\n";
        flush();
    }
}
