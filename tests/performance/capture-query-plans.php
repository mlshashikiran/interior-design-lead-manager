<?php
/**
 * Capture aggregate-only optimizer and index evidence for Phase 2.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

if ('cli' !== PHP_SAPI) {
    fwrite(STDERR, "Query-plan capture must run from the command line.\n");
    exit(1);
}

$options = getopt('', array('help', 'wp-root:', 'revision:', 'dataset:', 'start:', 'end:', 'rep-id:', 'lead-id:'));
if (isset($options['help'])) {
    fwrite(STDOUT, implode("\n", array(
        'Usage: php tests/performance/capture-query-plans.php [options]',
        '',
        'Options:',
        '  --wp-root=/path/to/wordpress',
        '  --revision=commit-or-build-id',
        '  --dataset=small-baseline-01',
        '  --start=YYYY-MM-DD --end=YYYY-MM-DD',
        '  --rep-id=1 --lead-id=1',
        '',
        'Runs read-only EXPLAIN and SHOW INDEX statements on a non-production benchmark environment.',
        'Output omits SQL text, CRM rows, contacts, credentials, request values, paths, and database names.',
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
if (!function_exists('wp_get_environment_type')) {
    require_once $wp_root . 'wp-load.php';
}

if (!defined('IDLM_VERSION')) {
    fwrite(STDERR, "Interior Design Lead Manager must be active in the selected WordPress environment.\n");
    exit(1);
}

$environment = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
if (!in_array($environment, array('local', 'development', 'staging'), true)) {
    fwrite(STDERR, "Query-plan capture is forbidden in the {$environment} environment.\n");
    exit(1);
}

$revision = isset($options['revision']) ? (string) $options['revision'] : (string) getenv('IDLM_PERF_REVISION');
$revision = preg_match('/^[a-zA-Z0-9._\/-]{1,80}$/', $revision) ? $revision : '';
$dataset = isset($options['dataset']) ? (string) $options['dataset'] : 'unclassified';
if (!preg_match('/^[a-zA-Z0-9._-]{1,80}$/', $dataset)) {
    fwrite(STDERR, "Dataset must contain only letters, numbers, dots, underscores, and hyphens.\n");
    exit(1);
}

$default_end = gmdate('Y-m-d');
$default_start = gmdate('Y-m-d', strtotime('-29 days'));
$start = isset($options['start']) ? (string) $options['start'] : $default_start;
$end = isset($options['end']) ? (string) $options['end'] : $default_end;
foreach (array('start' => $start, 'end' => $end) as $label => $date) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        fwrite(STDERR, ucfirst($label) . " must be a valid YYYY-MM-DD date.\n");
        exit(1);
    }
}
if ($start > $end) {
    fwrite(STDERR, "Start date must not be after end date.\n");
    exit(1);
}

$values = array(
    'start' => $start . ' 00:00:00',
    'end' => $end . ' 23:59:59',
    'rep_id' => isset($options['rep-id']) ? max(1, (int) $options['rep-id']) : 1,
    'lead_id' => isset($options['lead-id']) ? max(1, (int) $options['lead-id']) : 1,
);
$scenarios = require __DIR__ . '/query-plan-scenarios.php';

/**
 * Execute read-only EXPLAIN statements and normalize their aggregate metadata.
 */
final class IDLM_Performance_Query_Plan_Capture {
    /** @var wpdb */
    private $wpdb;

    /** @var array<string,string> */
    private $tokens;

    /** @param wpdb $wpdb WordPress database adapter. */
    public function __construct($wpdb) {
        $this->wpdb = $wpdb;
        $this->tokens = array(
            '{{leads}}' => $wpdb->prefix . 'idlm_leads',
            '{{tasks}}' => $wpdb->prefix . 'idlm_tasks',
            '{{activity}}' => $wpdb->prefix . 'idlm_lead_activity_log',
            '{{capi}}' => $wpdb->prefix . 'idlm_capi_logs',
            '{{quotes}}' => $wpdb->prefix . 'idlm_quotes',
            '{{revisions}}' => $wpdb->prefix . 'idlm_quote_revisions',
        );
    }

    /** @return array<string,mixed> */
    public function collect(array $scenarios, array $values, string $revision, string $dataset, string $environment): array {
        $plans = array();
        $table_suffixes = array();
        foreach ($scenarios as $scenario) {
            $plans[] = $this->explain($scenario, $values);
            $table_suffixes = array_merge($table_suffixes, (array) $scenario['tables']);
        }
        $table_suffixes = array_values(array_unique($table_suffixes));
        sort($table_suffixes);

        $full_scan_steps = 0;
        $filesort_steps = 0;
        $temporary_steps = 0;
        foreach ($plans as $plan) {
            foreach ($plan['steps'] as $step) {
                $full_scan_steps += 'ALL' === $step['access_type'] ? 1 : 0;
                $filesort_steps += false !== stripos($step['extra'], 'filesort') ? 1 : 0;
                $temporary_steps += false !== stripos($step['extra'], 'temporary') ? 1 : 0;
            }
        }

        return array(
            'schema' => 'idlm-query-plan-capture/v1',
            'captured_at_utc' => gmdate('c'),
            'build' => array(
                'revision' => $revision,
                'plugin_version' => IDLM_VERSION,
                'core_schema_version' => defined('IDLM_DB_VERSION') ? IDLM_DB_VERSION : '',
                'quotation_schema_version' => class_exists('IDLM_Quotation_Schema') ? IDLM_Quotation_Schema::VERSION : '',
            ),
            'benchmark' => array(
                'environment_type' => $environment,
                'dataset' => $dataset,
                'date_window' => array('start' => substr($values['start'], 0, 10), 'end' => substr($values['end'], 0, 10)),
                'mysql_version' => $this->wpdb->db_version(),
            ),
            'plans' => $plans,
            'indexes' => $this->index_inventory($table_suffixes),
            'summary' => array(
                'scenario_count' => count($plans),
                'full_scan_steps' => $full_scan_steps,
                'filesort_steps' => $filesort_steps,
                'temporary_steps' => $temporary_steps,
                'decision' => 'Review with measured endpoint cost and target-profile cardinality before changing indexes.',
            ),
            'privacy' => array(
                'aggregate_only' => true,
                'raw_sql_included' => false,
                'parameter_values_included' => false,
                'excluded' => array('CRM rows', 'contacts', 'request payloads', 'responses', 'credentials', 'URLs', 'filesystem paths', 'database name'),
            ),
        );
    }

    /** @return array<string,mixed> */
    private function explain(array $scenario, array $values): array {
        $sql = strtr((string) $scenario['sql'], $this->tokens);
        $params = array();
        foreach ((array) $scenario['params'] as $param) {
            $params[] = $values[$param];
        }
        if ($params) {
            $sql = $this->wpdb->prepare($sql, $params);
        }

        $rows = $this->wpdb->get_results('EXPLAIN ' . $sql, ARRAY_A);
        $steps = array();
        foreach ((array) $rows as $row) {
            $steps[] = array(
                'select_id' => isset($row['id']) ? (int) $row['id'] : 0,
                'select_type' => (string) ($row['select_type'] ?? ''),
                'table' => $this->normalize_table((string) ($row['table'] ?? '')),
                'access_type' => (string) ($row['type'] ?? ''),
                'possible_keys' => $this->csv_values($row['possible_keys'] ?? ''),
                'key' => isset($row['key']) ? (string) $row['key'] : '',
                'key_length' => isset($row['key_len']) ? (int) $row['key_len'] : 0,
                'ref' => $this->normalize_ref(isset($row['ref']) ? (string) $row['ref'] : ''),
                'estimated_rows' => isset($row['rows']) ? (int) $row['rows'] : 0,
                'filtered_percent' => isset($row['filtered']) ? (float) $row['filtered'] : null,
                'extra' => isset($row['Extra']) ? (string) $row['Extra'] : '',
            );
        }

        return array(
            'id' => (string) $scenario['id'],
            'scenario' => (string) $scenario['scenario'],
            'variant' => (string) $scenario['variant'],
            'status' => $steps ? 'captured' : 'optimizer_error',
            'steps' => $steps,
        );
    }

    /** @return array<int,array<string,mixed>> */
    private function index_inventory(array $suffixes): array {
        $inventory = array();
        foreach ($suffixes as $suffix) {
            if (!preg_match('/^idlm_[a-z0-9_]+$/', $suffix)) {
                continue;
            }
            $table = $this->wpdb->prefix . $suffix;
            $estimated_rows = $this->wpdb->get_var($this->wpdb->prepare(
                'SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
                DB_NAME,
                $table
            ));
            $indexes = array();
            foreach ((array) $this->wpdb->get_results("SHOW INDEX FROM `{$table}`", ARRAY_A) as $row) {
                $indexes[] = array(
                    'name' => (string) ($row['Key_name'] ?? ''),
                    'sequence' => isset($row['Seq_in_index']) ? (int) $row['Seq_in_index'] : 0,
                    'column' => (string) ($row['Column_name'] ?? ''),
                    'unique' => isset($row['Non_unique']) ? 0 === (int) $row['Non_unique'] : false,
                    'cardinality' => isset($row['Cardinality']) ? (int) $row['Cardinality'] : 0,
                    'index_type' => (string) ($row['Index_type'] ?? ''),
                );
            }
            $inventory[] = array(
                'table' => $suffix,
                'estimated_rows' => null === $estimated_rows ? 0 : (int) $estimated_rows,
                'indexes' => $indexes,
            );
        }
        return $inventory;
    }

    private function normalize_table(string $table): string {
        $prefix = $this->wpdb->prefix;
        return 0 === strpos($table, $prefix) ? substr($table, strlen($prefix)) : $table;
    }

    private function normalize_ref(string $reference): string {
        $parts = explode('.', $reference);
        if (count($parts) >= 3) {
            array_shift($parts);
        }
        return implode('.', $parts);
    }

    /** @return array<int,string> */
    private function csv_values($value): array {
        if (null === $value || '' === $value) {
            return array();
        }
        return array_values(array_filter(array_map('trim', explode(',', (string) $value))));
    }
}

global $wpdb;
$capture = new IDLM_Performance_Query_Plan_Capture($wpdb);
$result = $capture->collect($scenarios, $values, $revision, $dataset, $environment);
$json = function_exists('wp_json_encode')
    ? wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    : json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (false === $json) {
    fwrite(STDERR, "Unable to encode query-plan capture.\n");
    exit(1);
}
fwrite(STDOUT, $json . "\n");
