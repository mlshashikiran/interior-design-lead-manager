<?php
/**
 * Capture aggregate-only Phase 0 environment and capacity evidence.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

if ('cli' !== PHP_SAPI) {
    fwrite(STDERR, "Performance capture must run from the command line.\n");
    exit(1);
}

$options = getopt('', array('help', 'wp-root:', 'revision:'));
if (isset($options['help'])) {
    fwrite(STDOUT, implode("\n", array(
        'Usage: php tests/performance/capture-baseline.php [--wp-root=/path/to/wordpress] [--revision=git-sha]',
        '',
        'Emits aggregate-only JSON to stdout. Redirect it to a versioned evidence file when required.',
        'IDLM_PERF_WP_ROOT and IDLM_PERF_REVISION provide equivalent environment overrides.',
        '',
    )));
    exit(0);
}

$wp_root = isset($options['wp-root']) ? (string) $options['wp-root'] : (string) getenv('IDLM_PERF_WP_ROOT');
if ('' === trim($wp_root)) {
    $wp_root = dirname(__DIR__, 5);
}
$wp_root = rtrim($wp_root, '/\\') . DIRECTORY_SEPARATOR;
if (!is_file($wp_root . 'wp-load.php')) {
    fwrite(STDERR, "WordPress root not found. Pass --wp-root or set IDLM_PERF_WP_ROOT.\n");
    exit(1);
}

if (!defined('WP_USE_THEMES')) {
    define('WP_USE_THEMES', false);
}
require_once $wp_root . 'wp-load.php';

if (!defined('IDLM_VERSION')) {
    fwrite(STDERR, "Interior Design Lead Manager must be active in the selected WordPress environment.\n");
    exit(1);
}

$revision = isset($options['revision']) ? (string) $options['revision'] : (string) getenv('IDLM_PERF_REVISION');
$revision = preg_match('/^[a-zA-Z0-9._\/-]{1,80}$/', $revision) ? $revision : '';
$scenarios = require __DIR__ . '/scenarios.php';

/**
 * Build a PII-free inventory from WordPress and MySQL aggregate metadata.
 */
final class IDLM_Performance_Baseline_Capture {
    /** @var wpdb */
    private $wpdb;

    /** @param wpdb $wpdb WordPress database adapter. */
    public function __construct($wpdb) {
        $this->wpdb = $wpdb;
    }

    /** @return array<string,mixed> */
    public function collect(string $revision, array $scenarios): array {
        return array(
            'schema' => 'idlm-performance-baseline/v1',
            'captured_at_utc' => gmdate('c'),
            'build' => array(
                'revision' => $revision,
                'plugin_version' => IDLM_VERSION,
                'core_schema_version' => defined('IDLM_DB_VERSION') ? IDLM_DB_VERSION : '',
                'quotation_schema_version' => class_exists('IDLM_Quotation_Schema') ? IDLM_Quotation_Schema::VERSION : '',
            ),
            'runtime' => $this->runtime_inventory(),
            'storage' => array(
                'plugin_tables' => $this->table_inventory(),
                'autoloaded_options' => $this->autoload_inventory(),
            ),
            'scheduling' => array(
                'wordpress_cron' => $this->cron_inventory(),
                'action_scheduler' => $this->action_scheduler_inventory(),
            ),
            'scenarios' => array_map(static function(array $scenario): array {
                $scenario['measurement_status'] = 'not_measured';
                return $scenario;
            }, $scenarios),
            'privacy' => array(
                'aggregate_only' => true,
                'excluded' => array('lead rows', 'contacts', 'request payloads', 'responses', 'credentials', 'URLs', 'filesystem paths', 'database name'),
            ),
        );
    }

    /** @return array<string,mixed> */
    private function runtime_inventory(): array {
        return array(
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'mysql_version' => $this->wpdb->db_version(),
            'environment_type' => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'unknown',
            'multisite' => is_multisite(),
            'external_object_cache' => function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache(),
            'action_scheduler_available' => function_exists('as_enqueue_async_action'),
            'wp_cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'php_memory_limit' => (string) ini_get('memory_limit'),
            'php_max_execution_time_seconds' => (int) ini_get('max_execution_time'),
        );
    }

    /** @return array<int,array<string,mixed>> */
    private function table_inventory(): array {
        $suffixes = array(
            'idlm_leads',
            'idlm_lead_activity_log',
            'idlm_capi_logs',
            'idlm_audience_sync_logs',
            'idlm_telemetry_logs',
            'idlm_tasks',
            'idlm_call_logs',
            'idlm_lead_touchpoints',
            'idlm_routing_state',
            'idlm_quotes',
            'idlm_quote_revisions',
            'idlm_quote_revision_sections',
            'idlm_quote_revision_items',
            'idlm_quote_payment_milestones',
            'idlm_quote_status_history',
            'idlm_number_sequences',
            'idlm_quote_catalogue_items',
            'idlm_quote_item_categories',
            'idlm_quote_item_units',
        );
        $inventory = array();
        foreach ($suffixes as $suffix) {
            $table = $this->wpdb->prefix . $suffix;
            $row = $this->wpdb->get_row(
                $this->wpdb->prepare(
                    'SELECT TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, DATA_FREE FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
                    DB_NAME,
                    $table
                ),
                ARRAY_A
            );
            $inventory[] = array(
                'table' => $suffix,
                'present' => is_array($row),
                'estimated_rows' => is_array($row) ? (int) $row['TABLE_ROWS'] : 0,
                'data_bytes' => is_array($row) ? (int) $row['DATA_LENGTH'] : 0,
                'index_bytes' => is_array($row) ? (int) $row['INDEX_LENGTH'] : 0,
                'free_bytes' => is_array($row) ? (int) $row['DATA_FREE'] : 0,
            );
        }
        return $inventory;
    }

    /** @return array<string,int> */
    private function autoload_inventory(): array {
        $table = $this->wpdb->options;
        $row = $this->wpdb->get_row(
            "SELECT COUNT(*) AS option_count, COALESCE(SUM(LENGTH(option_name) + LENGTH(option_value)), 0) AS total_bytes
             FROM {$table}
             WHERE autoload IN ('yes', 'on', 'auto', 'auto-on')",
            ARRAY_A
        );
        return array(
            'option_count' => is_array($row) ? (int) $row['option_count'] : 0,
            'total_bytes' => is_array($row) ? (int) $row['total_bytes'] : 0,
        );
    }

    /** @return array<string,mixed> */
    private function cron_inventory(): array {
        $cron = function_exists('_get_cron_array') ? _get_cron_array() : array();
        $event_count = 0;
        $idlm_event_count = 0;
        $next_idlm_timestamp = null;
        foreach ((array) $cron as $timestamp => $hooks) {
            foreach ((array) $hooks as $hook => $events) {
                $count = count((array) $events);
                $event_count += $count;
                if (0 === strpos((string) $hook, 'idlm_')) {
                    $idlm_event_count += $count;
                    if (null === $next_idlm_timestamp || (int) $timestamp < $next_idlm_timestamp) {
                        $next_idlm_timestamp = (int) $timestamp;
                    }
                }
            }
        }
        return array(
            'event_count' => $event_count,
            'idlm_event_count' => $idlm_event_count,
            'next_idlm_event_utc' => null === $next_idlm_timestamp ? null : gmdate('c', $next_idlm_timestamp),
        );
    }

    /** @return array<string,mixed> */
    private function action_scheduler_inventory(): array {
        $table = $this->wpdb->prefix . 'actionscheduler_actions';
        $exists = $table === $this->wpdb->get_var(
            $this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($table))
        );
        if (!$exists) {
            return array('present' => false, 'status_counts' => array(), 'oldest_open_action_utc' => null);
        }

        $status_counts = array();
        foreach ((array) $this->wpdb->get_results("SELECT status, COUNT(*) AS action_count FROM {$table} GROUP BY status", ARRAY_A) as $row) {
            $status_counts[(string) $row['status']] = (int) $row['action_count'];
        }
        $oldest = $this->wpdb->get_var(
            "SELECT MIN(scheduled_date_gmt) FROM {$table} WHERE status IN ('pending', 'in-progress')"
        );
        return array(
            'present' => true,
            'status_counts' => $status_counts,
            'oldest_open_action_utc' => $oldest ? gmdate('c', strtotime((string) $oldest)) : null,
        );
    }
}

global $wpdb;
$capture = new IDLM_Performance_Baseline_Capture($wpdb);
$result = $capture->collect($revision, $scenarios);
$json = function_exists('wp_json_encode')
    ? wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (false === $json) {
    fwrite(STDERR, "Unable to encode performance baseline.\n");
    exit(1);
}
fwrite(STDOUT, $json . "\n");
