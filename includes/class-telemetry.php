<?php
if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Telemetry {
    /**
     * Exception handler that was active before IDLM telemetry initialized.
     *
     * @var callable|null
     */
    private static $previous_exception_handler = null;

    /**
     * Prevent recursive exception handling if logging or delegation fails.
     *
     * @var bool
     */
    private static $handling_exception = false;

    /**
     * Initialize PHP error and exception handlers
     */
    public static function init_handlers() {
        $level = get_option('idlm_telemetry_level', 'errors'); // 'off', 'errors', 'warnings', 'verbose'
        if ($level === 'off') {
            return;
        }

        // Register PHP error handler
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
        set_error_handler(array(__CLASS__, 'error_handler'));

        // Register PHP exception handler
        self::$previous_exception_handler = set_exception_handler(array(__CLASS__, 'exception_handler'));
    }

    /**
     * Custom PHP Error Handler
     */
    public static function error_handler($errno, $errstr, $errfile, $errline) {
        // Respect error_reporting settings (especially silencer operator @)
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting
        if (!(error_reporting() & $errno)) {
            return false;
        }

        // Filter: only capture errors that originate from our plugin folder
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        if (strpos($errfile, $plugin_dir) === false) {
            return false;
        }

        // Determine severity and log level check
        $severity = 'error';
        $log_type = 'php_error';
        
        switch ($errno) {
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                $severity = 'error';
                break;
            case E_WARNING:
            case E_USER_WARNING:
                $severity = 'warning';
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $severity = 'info';
                break;
            default:
                $severity = 'debug';
                break;
        }

        // Check if we should log based on user configuration
        $level = get_option('idlm_telemetry_level', 'errors');
        if ($level === 'errors' && $severity !== 'error') {
            return false;
        }
        if ($level === 'warnings' && !in_array($severity, array('error', 'warning'))) {
            return false;
        }

        // Capture stack trace
        $trace_lines = array();
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
        $backtrace = debug_backtrace();
        // Skip first element (error_handler itself)
        array_shift($backtrace);
        foreach ($backtrace as $i => $frame) {
            $file = isset($frame['file']) ? $frame['file'] : '[unknown]';
            $line = isset($frame['line']) ? $frame['line'] : '[unknown]';
            $func = isset($frame['function']) ? $frame['function'] : '[unknown]';
            $class = isset($frame['class']) ? $frame['class'] : '';
            $type = isset($frame['type']) ? $frame['type'] : '';
            $trace_lines[] = "#{$i} {$file}({$line}): {$class}{$type}{$func}()";
        }
        $stack_trace = implode("\n", $trace_lines);

        self::log($log_type, $severity, $errstr, $errfile, $errline, $stack_trace);

        // Don't halt PHP execution (unless it's a fatal error, which PHP handles anyway)
        return false;
    }

    /**
     * Custom PHP Exception Handler
     */
    public static function exception_handler($exception) {
        if (self::$handling_exception) {
            self::delegate_exception($exception);
            return;
        }

        self::$handling_exception = true;
        $file = $exception->getFile();
        
        // Filter: only capture exceptions originating from our plugin folder
        $plugin_dir = plugin_dir_path(dirname(__FILE__));
        if (strpos($file, $plugin_dir) !== false) {
            $message = $exception->getMessage();
            $line = $exception->getLine();
            $trace = $exception->getTraceAsString();

            self::log('php_exception', 'error', $message, $file, $line, $trace);
        }

        self::delegate_exception($exception);
        self::$handling_exception = false;
    }

    /**
     * Pass an exception to the handler WordPress registered before telemetry.
     *
     * Rethrowing from inside an exception handler invokes the active handler
     * again on some PHP runtimes, causing unbounded recursion.
     */
    private static function delegate_exception($exception) {
        if (is_callable(self::$previous_exception_handler)) {
            call_user_func(self::$previous_exception_handler, $exception);
            return;
        }

        // Keep a last-resort record without throwing from inside the handler.
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log(sprintf(
            'Uncaught %s: %s in %s:%d',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));
    }

    /**
     * Insert telemetry log into the database
     */
    public static function log($type, $severity, $message, $file = '', $line = null, $trace = '') {
        global $wpdb;
        $table_telemetry = $wpdb->prefix . 'idlm_telemetry_logs';

        // Check if telemetry table exists first
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_telemetry)) !== $table_telemetry) {
            return;
        }

        // Prevent runaway logging: if we exceed 1000 rows, do not write until pruned/cleared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_telemetry}"));
        if ($count >= 1000) {
            return;
        }

        // Normalize paths relative to plugin directory for privacy and clean output
        $plugin_path = plugin_dir_path(dirname(__FILE__));
        if (!empty($file)) {
            $file = str_replace($plugin_path, '', $file);
        }
        if (!empty($trace)) {
            $trace = str_replace($plugin_path, '', $trace);
        }

        $data = array(
            'log_type'    => sanitize_text_field($type),
            'severity'    => sanitize_text_field($severity),
            'message'     => sanitize_text_field($message),
            'file'        => sanitize_text_field($file),
            'line'        => $line !== null ? intval($line) : null,
            'stack_trace' => sanitize_textarea_field($trace),
            'created_at'  => current_time('mysql')
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert($table_telemetry, $data);
    }

    /**
     * Prune logs older than 14 days and cap database table rows at 1000
     */
    public static function prune_logs() {
        global $wpdb;
        $table_telemetry = $wpdb->prefix . 'idlm_telemetry_logs';

        // Verify table exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_telemetry)) !== $table_telemetry) {
            return;
        }

        // 1. Delete logs older than 14 days
        $threshold = gmdate('Y-m-d H:i:s', time() - 14 * DAY_IN_SECONDS);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->query($wpdb->prepare("DELETE FROM {$table_telemetry} WHERE created_at < %s", $threshold));

        // 2. Cap table at 1000 rows
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $count = intval($wpdb->get_var("SELECT COUNT(*) FROM {$table_telemetry}"));
        if ($count > 1000) {
            $offset = $count - 1000;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $threshold_id = intval($wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_telemetry} ORDER BY id ASC LIMIT 1 OFFSET %d", $offset - 1)));
            if ($threshold_id > 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
                $wpdb->query($wpdb->prepare("DELETE FROM {$table_telemetry} WHERE id <= %d", $threshold_id));
            }
        }
    }
}
