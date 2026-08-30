<?php
/**
 * Guarded deterministic performance dataset seeder and scoped reset command.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

if ('cli' !== PHP_SAPI) {
    fwrite(STDERR, "Performance datasets can only be managed from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/class-dataset-generator.php';

$options = getopt('', array('help', 'wp-root:', 'profile:', 'seed:', 'batch-size:', 'execute', 'reset:', 'confirm:'));
if (isset($options['help'])) {
    fwrite(STDOUT, implode("\n", array(
        'Usage:',
        '  php tests/performance/seed-dataset.php --profile=small --seed=baseline-01',
        '  php tests/performance/seed-dataset.php --profile=small --seed=baseline-01 --execute --confirm=seed-idlm-performance-data',
        '  php tests/performance/seed-dataset.php --reset=small-baseline-01 --confirm=reset-idlm-performance-data',
        '',
        'The default mode prints the deterministic plan without booting WordPress or writing data.',
        'Execution and reset refuse production, require an active plugin, and require exact confirmation text.',
        'Use --wp-root=/path/to/wordpress or IDLM_PERF_WP_ROOT to select WordPress.',
        '',
    )));
    exit(0);
}

$profile = isset($options['profile']) ? (string) $options['profile'] : 'small';
$seed = isset($options['seed']) ? (string) $options['seed'] : 'baseline-01';
try {
    $run_id = IDLM_Performance_Dataset_Generator::run_id($profile, $seed);
    $profile_counts = IDLM_Performance_Dataset_Generator::profile($profile);
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

$execute = isset($options['execute']);
$reset_run_id = isset($options['reset']) ? (string) $options['reset'] : '';
if ($execute && '' !== $reset_run_id) {
    fwrite(STDERR, "Choose either --execute or --reset, not both.\n");
    exit(1);
}

if (!$execute && '' === $reset_run_id) {
    $plan = array(
        'schema' => 'idlm-performance-dataset-plan/v1',
        'run_id' => $run_id,
        'marker' => IDLM_Performance_Dataset_Generator::marker($run_id),
        'profile' => $profile,
        'seed' => IDLM_Performance_Dataset_Generator::normalize_seed($seed),
        'counts' => $profile_counts,
        'derived_counts' => array(
            'quote_revisions' => $profile_counts['quotes'],
            'quote_sections' => $profile_counts['quotes'],
            'quote_items' => $profile_counts['quotes'],
        ),
        'writes_data' => false,
    );
    fwrite(STDOUT, json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    exit(0);
}

if ('' !== $reset_run_id) {
    try {
        IDLM_Performance_Dataset_Generator::marker($reset_run_id);
    } catch (InvalidArgumentException $error) {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
}

$confirmation = isset($options['confirm']) ? (string) $options['confirm'] : '';
$expected_confirmation = $execute ? 'seed-idlm-performance-data' : 'reset-idlm-performance-data';
if ($expected_confirmation !== $confirmation) {
    fwrite(STDERR, "Refusing data mutation without --confirm={$expected_confirmation}.\n");
    exit(1);
}

$batch_size = isset($options['batch-size']) ? (int) $options['batch-size'] : 500;
if ($batch_size < 50 || $batch_size > 2000) {
    fwrite(STDERR, "Batch size must be between 50 and 2000.\n");
    exit(1);
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
    fwrite(STDERR, "Performance dataset mutation is forbidden in the {$environment} environment.\n");
    exit(1);
}

/**
 * Bulk writer for deterministic synthetic performance records.
 */
final class IDLM_Performance_Dataset_Writer {
    private const RUNS_OPTION = 'idlm_performance_seed_runs';

    /** @var wpdb */
    private $wpdb;

    /** @var int */
    private $batch_size;

    /** @var array<string,string> */
    private $tables;

    /** @param wpdb $wpdb WordPress database adapter. */
    public function __construct($wpdb, int $batch_size) {
        $this->wpdb = $wpdb;
        $this->batch_size = $batch_size;
        $this->tables = array();
        foreach (array(
            'leads', 'lead_activity_log', 'tasks', 'call_logs', 'lead_touchpoints', 'capi_logs',
            'audience_sync_logs', 'telemetry_logs', 'quotes', 'quote_revisions',
            'quote_revision_sections', 'quote_revision_items', 'quote_payment_milestones',
            'quote_status_history',
        ) as $suffix) {
            $this->tables[$suffix] = $this->wpdb->prefix . 'idlm_' . $suffix;
        }
        $this->assert_tables_exist();
    }

    /** @param array<string,int> $counts @return array<string,mixed> */
    public function seed(string $run_id, string $profile, string $seed, array $counts): array {
        $marker = IDLM_Performance_Dataset_Generator::marker($run_id);
        if ($this->count_leads($marker) > 0) {
            throw new RuntimeException("Dataset {$run_id} already exists; reset it before seeding again.");
        }

        $started = microtime(true);
        $user_ids = array_values(array_map('intval', get_users(array('fields' => 'ID', 'number' => 100, 'orderby' => 'ID', 'order' => 'ASC'))));
        if (empty($user_ids)) {
            $user_ids = array(0);
        }

        $this->insert_rows($this->tables['leads'], $counts['leads'], static function(int $ordinal) use ($run_id, $user_ids): array {
            $owner_id = $user_ids[($ordinal - 1) % count($user_ids)];
            return IDLM_Performance_Dataset_Generator::lead($run_id, $ordinal, $owner_id);
        }, 'leads');

        $lead_ids = $this->lead_ids($marker);
        if (count($lead_ids) !== $counts['leads']) {
            throw new RuntimeException('Lead count verification failed after seeding.');
        }
        $this->mark_duplicates($lead_ids);

        $this->insert_rows($this->tables['lead_activity_log'], $counts['activities'], static function(int $ordinal) use ($run_id, $lead_ids, $user_ids): array {
            return IDLM_Performance_Dataset_Generator::activity(
                $run_id,
                $ordinal,
                $lead_ids[($ordinal - 1) % count($lead_ids)],
                $user_ids[($ordinal - 1) % count($user_ids)]
            );
        }, 'activities');

        $this->insert_rows($this->tables['tasks'], $counts['tasks'], static function(int $ordinal) use ($run_id, $lead_ids, $user_ids): array {
            return IDLM_Performance_Dataset_Generator::task(
                $run_id,
                $ordinal,
                $lead_ids[($ordinal - 1) % count($lead_ids)],
                $user_ids[($ordinal - 1) % count($user_ids)]
            );
        }, 'tasks');

        $this->insert_rows($this->tables['lead_touchpoints'], $counts['touchpoints'], static function(int $ordinal) use ($run_id, $lead_ids): array {
            return IDLM_Performance_Dataset_Generator::touchpoint($run_id, $ordinal, $lead_ids[($ordinal - 1) % count($lead_ids)]);
        }, 'touchpoints');

        $base_logs = intdiv($counts['logs'], 3);
        $capi_count = $base_logs + (0 < $counts['logs'] % 3 ? 1 : 0);
        $audience_count = $base_logs + (1 < $counts['logs'] % 3 ? 1 : 0);
        $telemetry_count = $base_logs;
        $this->insert_rows($this->tables['capi_logs'], $capi_count, static function(int $ordinal) use ($run_id, $lead_ids): array {
            return IDLM_Performance_Dataset_Generator::capi_log($run_id, $ordinal, $lead_ids[($ordinal - 1) % count($lead_ids)]);
        }, 'CAPI logs');
        $this->insert_rows($this->tables['audience_sync_logs'], $audience_count, static function(int $ordinal) use ($run_id): array {
            return IDLM_Performance_Dataset_Generator::audience_log($run_id, $ordinal);
        }, 'audience logs');
        $this->insert_rows($this->tables['telemetry_logs'], $telemetry_count, static function(int $ordinal) use ($run_id): array {
            return IDLM_Performance_Dataset_Generator::telemetry_log($run_id, $ordinal);
        }, 'telemetry logs');

        $this->insert_rows($this->tables['quotes'], $counts['quotes'], static function(int $ordinal) use ($run_id, $lead_ids, $user_ids): array {
            return IDLM_Performance_Dataset_Generator::quote(
                $run_id,
                $ordinal,
                $lead_ids[($ordinal - 1) % count($lead_ids)],
                $user_ids[($ordinal - 1) % count($user_ids)]
            );
        }, 'quotes');

        $quote_ids = $this->quote_ids($run_id);
        $this->insert_rows($this->tables['quote_revisions'], $counts['quotes'], static function(int $ordinal) use ($run_id, $quote_ids, $user_ids): array {
            return IDLM_Performance_Dataset_Generator::revision(
                $run_id,
                $ordinal,
                $quote_ids[$ordinal - 1],
                $user_ids[($ordinal - 1) % count($user_ids)]
            );
        }, 'quote revisions');

        $revision_ids = $this->revision_ids($run_id);
        $this->connect_quote_revisions($quote_ids, $revision_ids);
        $this->insert_rows($this->tables['quote_revision_sections'], $counts['quotes'], static function(int $ordinal) use ($run_id, $revision_ids): array {
            return IDLM_Performance_Dataset_Generator::section($run_id, $ordinal, $revision_ids[$ordinal - 1]);
        }, 'quote sections');

        $section_ids = $this->section_ids($run_id);
        $this->insert_rows($this->tables['quote_revision_items'], $counts['quotes'], static function(int $ordinal) use ($run_id, $revision_ids, $section_ids): array {
            return IDLM_Performance_Dataset_Generator::item($run_id, $ordinal, $revision_ids[$ordinal - 1], $section_ids[$ordinal - 1]);
        }, 'quote items');

        $actual = $this->verify($run_id, $marker);
        $expected = array_merge($counts, array(
            'quote_revisions' => $counts['quotes'],
            'quote_sections' => $counts['quotes'],
            'quote_items' => $counts['quotes'],
        ));
        foreach ($expected as $key => $value) {
            if (!isset($actual[$key]) || $actual[$key] !== $value) {
                throw new RuntimeException("Verification failed for {$key}: expected {$value}, found " . ($actual[$key] ?? 0) . '.');
            }
        }

        $manifest = array(
            'schema' => 'idlm-performance-dataset/v1',
            'run_id' => $run_id,
            'profile' => $profile,
            'seed' => $seed,
            'marker' => $marker,
            'plugin_version' => IDLM_VERSION,
            'environment_type' => wp_get_environment_type(),
            'counts' => $actual,
            'batch_size' => $this->batch_size,
            'duration_seconds' => round(microtime(true) - $started, 3),
            'generated_at_utc' => gmdate('c'),
            'contains_real_contact_data' => false,
        );
        $runs = (array) get_option(self::RUNS_OPTION, array());
        $runs[$run_id] = $manifest;
        update_option(self::RUNS_OPTION, $runs, false);
        return $manifest;
    }

    /** @return array<string,mixed> */
    public function reset(string $run_id): array {
        $marker = IDLM_Performance_Dataset_Generator::marker($run_id);
        $lead_ids = $this->lead_ids($marker);
        $deleted = array();
        foreach (array_chunk($lead_ids, $this->batch_size) as $lead_batch) {
            $quote_ids = $this->select_ids($this->tables['quotes'], 'lead_id', $lead_batch);
            $revision_ids = $this->select_ids($this->tables['quote_revisions'], 'quote_id', $quote_ids);
            $deleted['quote_items'] = ($deleted['quote_items'] ?? 0) + $this->delete_ids($this->tables['quote_revision_items'], 'revision_id', $revision_ids);
            $deleted['quote_sections'] = ($deleted['quote_sections'] ?? 0) + $this->delete_ids($this->tables['quote_revision_sections'], 'revision_id', $revision_ids);
            $deleted['quote_milestones'] = ($deleted['quote_milestones'] ?? 0) + $this->delete_ids($this->tables['quote_payment_milestones'], 'revision_id', $revision_ids);
            $deleted['quote_history'] = ($deleted['quote_history'] ?? 0) + $this->delete_ids($this->tables['quote_status_history'], 'quote_id', $quote_ids);
            $deleted['quote_revisions'] = ($deleted['quote_revisions'] ?? 0) + $this->delete_ids($this->tables['quote_revisions'], 'id', $revision_ids);
            $deleted['quotes'] = ($deleted['quotes'] ?? 0) + $this->delete_ids($this->tables['quotes'], 'id', $quote_ids);
            foreach (array('lead_activity_log', 'tasks', 'call_logs', 'lead_touchpoints', 'capi_logs') as $suffix) {
                $deleted[$suffix] = ($deleted[$suffix] ?? 0) + $this->delete_ids($this->tables[$suffix], 'lead_id', $lead_batch);
            }
            $deleted['leads'] = ($deleted['leads'] ?? 0) + $this->delete_ids($this->tables['leads'], 'id', $lead_batch);
        }
        $deleted['audience_sync_logs'] = $this->delete_marker_rows($this->tables['audience_sync_logs'], 'audience_id', $marker . ':%');
        $deleted['telemetry_logs'] = $this->delete_marker_rows($this->tables['telemetry_logs'], 'message', $marker . ' %', "log_type = 'idlm_performance_seed' AND ");

        $runs = (array) get_option(self::RUNS_OPTION, array());
        unset($runs[$run_id]);
        update_option(self::RUNS_OPTION, $runs, false);
        return array(
            'schema' => 'idlm-performance-dataset-reset/v1',
            'run_id' => $run_id,
            'deleted' => $deleted,
            'remaining_marked_leads' => $this->count_leads($marker),
            'reset_at_utc' => gmdate('c'),
        );
    }

    private function assert_tables_exist(): void {
        foreach ($this->tables as $table) {
            $present = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $this->wpdb->esc_like($table)));
            if ($table !== $present) {
                throw new RuntimeException("Required plugin table is missing: {$table}");
            }
        }
    }

    private function insert_rows(string $table, int $count, callable $factory, string $label): void {
        for ($start = 1; $start <= $count; $start += $this->batch_size) {
            $end = min($count, $start + $this->batch_size - 1);
            $rows = array();
            for ($ordinal = $start; $ordinal <= $end; $ordinal++) {
                $rows[] = $factory($ordinal);
            }
            $this->insert_batch($table, $rows);
            if (1 === $start || $end === $count || 0 === (int) ceil($end / $this->batch_size) % 20) {
                fwrite(STDERR, sprintf("Seeded %s: %d/%d\n", $label, $end, $count));
            }
        }
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function insert_batch(string $table, array $rows): void {
        if (empty($rows)) {
            return;
        }
        $columns = array_keys($rows[0]);
        foreach ($columns as $column) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
                throw new RuntimeException('Unsafe dataset column identifier.');
            }
        }
        $value_groups = array();
        $values = array();
        foreach ($rows as $row) {
            if (array_keys($row) !== $columns) {
                throw new RuntimeException('Dataset rows must have identical columns.');
            }
            $placeholders = array();
            foreach ($row as $value) {
                if (null === $value) {
                    $placeholders[] = 'NULL';
                } elseif (is_int($value)) {
                    $placeholders[] = '%d';
                    $values[] = $value;
                } elseif (is_float($value)) {
                    $placeholders[] = '%f';
                    $values[] = $value;
                } else {
                    $placeholders[] = '%s';
                    $values[] = (string) $value;
                }
            }
            $value_groups[] = '(' . implode(', ', $placeholders) . ')';
        }
        $quoted_columns = array_map(static function(string $column): string {
            return "`{$column}`";
        }, $columns);
        $sql = "INSERT INTO `{$table}` (" . implode(', ', $quoted_columns) . ') VALUES ' . implode(', ', $value_groups);
        $prepared = empty($values) ? $sql : $this->wpdb->prepare($sql, $values);
        $result = $this->wpdb->query($prepared);
        if (false === $result) {
            throw new RuntimeException("Unable to insert synthetic rows into {$table}: {$this->wpdb->last_error}");
        }
    }

    /** @param array<int,int> $lead_ids */
    private function mark_duplicates(array $lead_ids): void {
        $pairs = array();
        foreach ($lead_ids as $index => $lead_id) {
            if (0 !== ($index + 1) % 10) {
                continue;
            }
            $canonical_index = max(0, $index - 1);
            $pairs[(int) $lead_id] = (int) $lead_ids[$canonical_index];
        }
        foreach (array_chunk($pairs, $this->batch_size, true) as $batch) {
            $case = array();
            $values = array();
            $ids = array();
            foreach ($batch as $duplicate_id => $canonical_id) {
                $case[] = 'WHEN %d THEN %d';
                $values[] = $duplicate_id;
                $values[] = $canonical_id;
                $ids[] = $duplicate_id;
            }
            $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
            $values = array_merge($values, $ids);
            $sql = "UPDATE `{$this->tables['leads']}` SET is_duplicate = 1, duplicate_of_id = CASE id " . implode(' ', $case) . " END, stage = 'Lost', lead_quality = 'Unqualified', lost_reason = 'Duplicate or Invalid Lead', disqualification_reason = 'duplicate', disqualification_method = 'performance_seed' WHERE id IN ({$placeholders})";
            if (false === $this->wpdb->query($this->wpdb->prepare($sql, $values))) {
                throw new RuntimeException('Unable to mark synthetic duplicates: ' . $this->wpdb->last_error);
            }
        }
    }

    /** @param array<int,int> $quote_ids @param array<int,int> $revision_ids */
    private function connect_quote_revisions(array $quote_ids, array $revision_ids): void {
        if (count($quote_ids) !== count($revision_ids)) {
            throw new RuntimeException('Quote/revision cardinality mismatch.');
        }
        $pairs = array_combine($quote_ids, $revision_ids);
        foreach (array_chunk($pairs, $this->batch_size, true) as $batch) {
            $case = array();
            $values = array();
            $ids = array();
            foreach ($batch as $quote_id => $revision_id) {
                $case[] = 'WHEN %d THEN %d';
                $values[] = $quote_id;
                $values[] = $revision_id;
                $ids[] = $quote_id;
            }
            $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
            $values = array_merge($values, $ids);
            $sql = "UPDATE `{$this->tables['quotes']}` SET current_revision_id = CASE id " . implode(' ', $case) . " END WHERE id IN ({$placeholders})";
            if (false === $this->wpdb->query($this->wpdb->prepare($sql, $values))) {
                throw new RuntimeException('Unable to connect synthetic quote revisions: ' . $this->wpdb->last_error);
            }
        }
    }

    /** @return array<int,int> */
    private function lead_ids(string $marker): array {
        $like = $this->wpdb->esc_like($marker . ' Project ') . '%';
        return array_map('intval', (array) $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM `{$this->tables['leads']}` WHERE source = %s AND project_name LIKE %s ORDER BY id",
            IDLM_Performance_Dataset_Generator::SOURCE,
            $like
        )));
    }

    /** @return array<int,int> */
    private function quote_ids(string $run_id): array {
        $like = $this->wpdb->esc_like('PERF-' . $run_id . '-') . '%';
        return array_map('intval', (array) $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM `{$this->tables['quotes']}` WHERE quote_number LIKE %s ORDER BY id",
            $like
        )));
    }

    /** @return array<int,int> */
    private function revision_ids(string $run_id): array {
        $like = $this->wpdb->esc_like('PERF-' . $run_id . '-') . '%';
        return array_map('intval', (array) $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT r.id FROM `{$this->tables['quote_revisions']}` r JOIN `{$this->tables['quotes']}` q ON q.id = r.quote_id WHERE q.quote_number LIKE %s ORDER BY q.id",
            $like
        )));
    }

    /** @return array<int,int> */
    private function section_ids(string $run_id): array {
        $like = $this->wpdb->esc_like(IDLM_Performance_Dataset_Generator::marker($run_id) . ' Section ') . '%';
        return array_map('intval', (array) $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM `{$this->tables['quote_revision_sections']}` WHERE section_name LIKE %s ORDER BY id",
            $like
        )));
    }

    /** @return array<string,int> */
    private function verify(string $run_id, string $marker): array {
        $lead_like = $this->wpdb->esc_like($marker . ' Project ') . '%';
        $activity_like = $this->wpdb->esc_like($marker . ' synthetic activity ') . '%';
        $task_like = $this->wpdb->esc_like($marker . ' synthetic task ') . '%';
        $touchpoint_like = $this->wpdb->esc_like($marker . ':touchpoint:') . '%';
        $quote_like = $this->wpdb->esc_like('PERF-' . $run_id . '-') . '%';
        $section_like = $this->wpdb->esc_like($marker . ' Section ') . '%';
        $item_like = $this->wpdb->esc_like($marker . ' Item ') . '%';
        $audience_like = $this->wpdb->esc_like($marker . ':audience:') . '%';
        $telemetry_like = $this->wpdb->esc_like($marker . ' synthetic telemetry ') . '%';
        $capi_count = (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM `{$this->tables['capi_logs']}` c JOIN `{$this->tables['leads']}` l ON l.id = c.lead_id WHERE l.source = %s AND l.project_name LIKE %s AND c.event_name = 'SyntheticPerformanceEvent'",
            IDLM_Performance_Dataset_Generator::SOURCE,
            $lead_like
        ));
        $audience_count = (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM `{$this->tables['audience_sync_logs']}` WHERE audience_id LIKE %s", $audience_like));
        $telemetry_count = (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM `{$this->tables['telemetry_logs']}` WHERE log_type = 'idlm_performance_seed' AND message LIKE %s", $telemetry_like));
        return array(
            'leads' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM `{$this->tables['leads']}` WHERE source = %s AND project_name LIKE %s", IDLM_Performance_Dataset_Generator::SOURCE, $lead_like)),
            'activities' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM `{$this->tables['lead_activity_log']}` WHERE details LIKE %s", $activity_like)),
            'tasks' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM `{$this->tables['tasks']}` WHERE title LIKE %s", $task_like)),
            'touchpoints' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM `{$this->tables['lead_touchpoints']}` WHERE external_id LIKE %s", $touchpoint_like)),
            'logs' => $capi_count + $audience_count + $telemetry_count,
            'quotes' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM `{$this->tables['quotes']}` WHERE quote_number LIKE %s", $quote_like)),
            'quote_revisions' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM `{$this->tables['quote_revisions']}` WHERE revision_name LIKE %s", $quote_like . 'R01')),
            'quote_sections' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM `{$this->tables['quote_revision_sections']}` WHERE section_name LIKE %s", $section_like)),
            'quote_items' => (int) $this->wpdb->get_var($this->wpdb->prepare("SELECT COUNT(*) FROM `{$this->tables['quote_revision_items']}` WHERE item_name LIKE %s", $item_like)),
        );
    }

    private function count_leads(string $marker): int {
        $like = $this->wpdb->esc_like($marker . ' Project ') . '%';
        return (int) $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT COUNT(*) FROM `{$this->tables['leads']}` WHERE source = %s AND project_name LIKE %s",
            IDLM_Performance_Dataset_Generator::SOURCE,
            $like
        ));
    }

    /** @param array<int,int> $values @return array<int,int> */
    private function select_ids(string $table, string $column, array $values): array {
        if (empty($values)) {
            return array();
        }
        $placeholders = implode(', ', array_fill(0, count($values), '%d'));
        return array_map('intval', (array) $this->wpdb->get_col($this->wpdb->prepare(
            "SELECT id FROM `{$table}` WHERE `{$column}` IN ({$placeholders})",
            $values
        )));
    }

    /** @param array<int,int> $ids */
    private function delete_ids(string $table, string $column, array $ids): int {
        if (empty($ids)) {
            return 0;
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '%d'));
        $deleted = $this->wpdb->query($this->wpdb->prepare("DELETE FROM `{$table}` WHERE `{$column}` IN ({$placeholders})", $ids));
        if (false === $deleted) {
            throw new RuntimeException("Unable to reset synthetic rows from {$table}: {$this->wpdb->last_error}");
        }
        return (int) $deleted;
    }

    private function delete_marker_rows(string $table, string $column, string $pattern, string $extra_where = ''): int {
        $deleted_total = 0;
        $like = $this->wpdb->esc_like(rtrim($pattern, '%')) . '%';
        do {
            $deleted = $this->wpdb->query($this->wpdb->prepare(
                "DELETE FROM `{$table}` WHERE {$extra_where}`{$column}` LIKE %s LIMIT %d",
                $like,
                $this->batch_size
            ));
            if (false === $deleted) {
                throw new RuntimeException("Unable to reset marker rows from {$table}: {$this->wpdb->last_error}");
            }
            $deleted_total += (int) $deleted;
        } while ($deleted === $this->batch_size);
        return $deleted_total;
    }
}

try {
    global $wpdb;
    $writer = new IDLM_Performance_Dataset_Writer($wpdb, $batch_size);
    if ($execute) {
        $result = $writer->seed(
            $run_id,
            $profile,
            IDLM_Performance_Dataset_Generator::normalize_seed($seed),
            $profile_counts
        );
    } else {
        $result = $writer->reset($reset_run_id);
    }
    fwrite(STDOUT, wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
