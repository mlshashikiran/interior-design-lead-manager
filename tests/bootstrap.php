<?php
/**
 * Dependency-free WordPress test doubles for the IDLM behavioral baseline.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/support/wp/');
define('IDLM_TESTING', true);
define('DB_NAME', 'idlm_test_unit');
define('IDLM_VERSION', '1.101.75');
define('IDLM_DB_VERSION', '1.3.9');
define('IDLM_PATH', dirname(__DIR__) . '/');
define('IDLM_URL', 'https://example.test/wp-content/plugins/interior-design-lead-manager/');
define('ARRAY_A', 'ARRAY_A');
define('OBJECT', 'OBJECT');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);
define('MINUTE_IN_SECONDS', 60);
define('MB_IN_BYTES', 1048576);

final class IDLM_Test_Response extends RuntimeException {
    public bool $success;
    public $data;
    public int $status;

    public function __construct(bool $success, $data = null, int $status = 200) {
        parent::__construct('Test response');
        $this->success = $success;
        $this->data = $data;
        $this->status = $status;
    }
}

class WP_Error {
    private string $code;
    private string $message;
    private $data;

    public function __construct(string $code = '', string $message = '', $data = null) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code(): string {
        return $this->code;
    }

    public function get_error_message(): string {
        return $this->message;
    }

    public function get_error_data() {
        return $this->data;
    }

    public function has_errors(): bool {
        return '' !== $this->code;
    }
}

class WP_REST_Response {
    private $data;
    private int $status;
    private array $headers;

    public function __construct($data = null, int $status = 200, array $headers = array()) {
        $this->data = $data;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function get_data() {
        return $this->data;
    }

    public function get_status(): int {
        return $this->status;
    }

    public function get_headers(): array {
        return $this->headers;
    }

    public function header(string $key, string $value, bool $replace = true): void {
        if ($replace || !isset($this->headers[$key])) {
            $this->headers[$key] = $value;
        }
    }
}

class WP_Post {
    public string $post_content;

    public function __construct(string $post_content = '') {
        $this->post_content = $post_content;
    }
}

function selected($selected, $current = true, $display = true): string {
    $result = (string) $selected === (string) $current ? ' selected="selected"' : '';
    if ($display) {
        echo $result;
    }
    return $result;
}

class WP_REST_Request {
    private array $attributes = array();
    private array $headers;
    private array $params;
    private array $json;
    private array $body_params;
    private string $body;
    private string $route;

    public function __construct(array $headers = array(), array $params = array(), array $json = array(), array $body_params = array(), string $body = '', string $route = '') {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->params = $params;
        $this->json = $json;
        $this->body_params = $body_params;
        $this->body = $body;
        $this->route = $route;
    }

    public function get_attributes(): array { return $this->attributes; }
    public function set_attributes(array $attributes): void { $this->attributes = $attributes; }

    public function get_header(string $name): string {
        return (string) ($this->headers[strtolower($name)] ?? '');
    }

    public function get_param(string $name) {
        return $this->params[$name] ?? null;
    }

    public function get_json_params(): array {
        return $this->json;
    }

    public function get_body_params(): array {
        return $this->body_params;
    }

    public function get_body(): string {
        return $this->body;
    }

    public function get_query_params(): array { return $this->params; }

    public function get_method(): string { return 'GET'; }

    public function get_route(): string {
        return $this->route;
    }
}

final class IDLM_Test_WPDB {
    public string $prefix = 'wp_';
    public string $options = 'wp_options';
    public string $users = 'wp_users';
    public string $usermeta = 'wp_usermeta';
    public string $posts = 'wp_posts';
    public string $postmeta = 'wp_postmeta';
    public int $insert_id = 0;
    public string $last_error = '';
    public int $reconnect_retries = 5;
    private $transaction_snapshot = null;
    public bool $suppress_errors = false;
    public function suppress_errors($value = true) { $previous = $this->suppress_errors; $this->suppress_errors = (bool) $value; return $previous; }
    public array $tables = array();
    public array $queries = array();
    public array $dbdelta = array();
    public array $custom_query_results = array();
    public $custom_get_var_result = null;
    public string $last_get_results_query = '';
    public array $failed_writes = array();
    public string $query_fail_contains = '';
    public bool $deny_schema_lock = false;
    public array $schema_engines = array();
    private array $next_ids = array();

    public function __construct() {
        $this->reset();
    }

    public function reset(): void {
        $this->transaction_snapshot = null;
        $this->insert_id = 0;
        $this->last_error = '';
        $this->queries = array();
        $this->dbdelta = array();
        $this->custom_query_results = array();
        $this->custom_get_var_result = null;
        $this->last_get_results_query = '';
        $this->failed_writes = array();
        $this->query_fail_contains = '';
        $this->deny_schema_lock = false;
        $this->schema_engines = array();
        $this->next_ids = array();
        $this->tables = array();
        foreach (array(
            'idlm_leads', 'idlm_lead_activity_log', 'idlm_tasks', 'idlm_call_logs',
            'idlm_lead_outbox', 'idlm_lead_state_events', 'idlm_lead_touchpoints', 'idlm_inbound_submission_reviews', 'idlm_capi_logs', 'idlm_audience_sync_logs',
            'idlm_telemetry_logs', 'idlm_quotes', 'idlm_quote_revisions',
            'idlm_quote_revision_sections', 'idlm_quote_revision_items',
            'idlm_quote_payment_milestones', 'idlm_quote_status_history',
            'idlm_number_sequences', 'idlm_quote_catalogue_items', 'idlm_private_files', 'idlm_private_file_chunks',
            'idlm_quote_item_categories', 'idlm_quote_item_units',
        ) as $name) {
            $this->tables[$this->prefix . $name] = array();
            $this->next_ids[$this->prefix . $name] = 1;
        }
    }

    public function seed(string $table, array $rows): void {
        $table = $this->resolve_table($table);
        $this->tables[$table] = array();
        $highest = 0;
        foreach ($rows as $row) {
            $row = (array) $row;
            if (!isset($row['id'])) {
                $row['id'] = ++$highest;
            }
            $highest = max($highest, (int) $row['id']);
            $this->tables[$table][] = $row;
        }
        $this->next_ids[$table] = $highest + 1;
    }

    public function rows(string $table): array {
        return $this->tables[$this->resolve_table($table)] ?? array();
    }

    public function fail_next_write(string $operation, string $table): void {
        $this->failed_writes[] = strtolower($operation) . ':' . $this->resolve_table($table);
    }

    private function resolve_table(string $table): string {
        return 0 === strpos($table, $this->prefix) ? $table : $this->prefix . $table;
    }

    public function get_charset_collate(): string {
        return 'DEFAULT CHARACTER SET utf8mb4';
    }

    public function db_version(): string {
        return '8.0-test';
    }

    public function esc_like(string $value): string {
        return addcslashes($value, '_%\\');
    }

    public function prepare(string $query, ...$args): string {
        if (1 === count($args) && is_array($args[0])) {
            $args = $args[0];
        }
        $index = 0;
        return (string) preg_replace_callback('/(?<!%)%([dfs])/', static function(array $match) use (&$index, $args): string {
            $value = $args[$index++] ?? null;
            if ('d' === $match[1]) {
                return (string) (int) $value;
            }
            if ('f' === $match[1]) {
                return (string) (float) $value;
            }
            return "'" . addslashes((string) $value) . "'";
        }, $query);
    }

    public function insert(string $table, array $data, $format = null) {
        $this->last_error = '';

        $table = $this->resolve_table($table);
        if ($this->should_fail_write('insert', $table)) {
            $this->insert_id = 0;
            return false;
        }
        if (!isset($this->tables[$table])) {
            $this->tables[$table] = array();
            $this->next_ids[$table] = 1;
        }
        if (!isset($data['id'])) {
            $data['id'] = $this->next_ids[$table]++;
        }
        $this->insert_id = (int) $data['id'];
        $this->tables[$table][] = $data;
        return 1;
    }

    public function update(string $table, array $data, array $where, $format = null, $where_format = null) {
        $this->last_error = '';

        $table = $this->resolve_table($table);
        if ($this->should_fail_write('update', $table)) {
            return false;
        }
        if ('wp_options' === $table && isset($where['option_name'], $where['option_value']) && isset($GLOBALS['idlm_test_options'][$where['option_name']])) {
            $name = $where['option_name'];
            if (in_array($name, $GLOBALS['idlm_test_option_write_failures'] ?? array(), true)) { return false; }
            if ($GLOBALS['idlm_test_options'][$name] !== $where['option_value']) { return 0; }
            $GLOBALS['idlm_test_options'][$name] = $data['option_value'];
            return 1;
        }
        $updated = 0;
        foreach ($this->tables[$table] ?? array() as $index => $row) {
            if (!$this->row_matches($row, $where)) {
                continue;
            }
            $this->tables[$table][$index] = array_merge($row, $data);
            $updated++;
        }
        return $updated;
    }

    public function delete(string $table, array $where, $where_format = null) {
        $this->last_error = '';

        $table = $this->resolve_table($table);
        if ($this->should_fail_write('delete', $table)) {
            return false;
        }
        $kept = array();
        $deleted = 0;
        foreach ($this->tables[$table] ?? array() as $row) {
            if ($this->row_matches($row, $where)) {
                $deleted++;
            } else {
                $kept[] = $row;
            }
        }
        $this->tables[$table] = $kept;
        return $deleted;
    }

    private function should_fail_write(string $operation, string $table): bool {
        $key = strtolower($operation) . ':' . $table;
        $index = array_search($key, $this->failed_writes, true);
        if (false === $index) {
            return false;
        }
        unset($this->failed_writes[$index]);
        $this->failed_writes = array_values($this->failed_writes);
        $this->last_error = 'Simulated ' . $operation . ' failure for ' . $table;
        return true;
    }

    private function row_matches(array $row, array $where): bool {
        foreach ($where as $key => $value) {
            if (!array_key_exists($key, $row) || (string) $row[$key] !== (string) $value) {
                return false;
            }
        }
        return true;
    }

    public function query(string $query) {
        if (preg_match("/INSERT INTO wp_options .*VALUES \('(?P<key>idlm_connector_erased_[a-f0-9]+)','(?P<value>[0-9.]+)'/", $query, $marker)) { $GLOBALS['idlm_test_options'][$marker['key']] = $marker['value']; return 1; }
        if (preg_match("/INSERT INTO wp_options .*VALUES \('idlm_recovery_generation','([0-9]+)'/", $query, $epoch)) { $GLOBALS['idlm_test_options']['idlm_recovery_generation'] = $epoch[1]; return 1; }

        $this->queries[] = $query;
        $this->last_error = '';
        if ($this->query_fail_contains && strpos($query, $this->query_fail_contains) !== false) { $this->last_error = 'Synthetic query failure'; return false; }
        if ($query === 'SET TRANSACTION READ WRITE' && null !== $this->transaction_snapshot) { $this->last_error = 'Existing transaction'; return false; }
        if ($query === 'START TRANSACTION') { $this->transaction_snapshot = array($this->tables, $GLOBALS['idlm_test_options']); return 1; }
        if ($query === 'ROLLBACK') { if (null !== $this->transaction_snapshot) { [$this->tables, $GLOBALS['idlm_test_options']] = $this->transaction_snapshot; } $this->transaction_snapshot = null; return 1; }
        if ($query === 'COMMIT') { $this->transaction_snapshot = null; return 1; }
        $normalized = preg_replace('/\s+/', ' ', trim($query));
        if (preg_match("/UPDATE (wp_idlm_quote_catalogue_items) SET status = 'inactive'.*WHERE id IN \(([^)]+)\)/i", $normalized, $matches)) {
            $ids = array_map('intval', explode(',', $matches[2]));
            $updated = 0;
            foreach ($this->tables[$matches[1]] as $index => $row) {
                if (in_array((int) ($row['id'] ?? 0), $ids, true)) {
                    $this->tables[$matches[1]][$index]['status'] = 'inactive';
                    $updated++;
                }
            }
            return $updated;
        }
        if (preg_match('/DELETE FROM (wp_idlm_quote_catalogue_items) WHERE id IN \(([^)]+)\)/i', $normalized, $matches)) {
            $ids = array_map('intval', explode(',', $matches[2]));
            $before = count($this->tables[$matches[1]]);
            $this->tables[$matches[1]] = array_values(array_filter($this->tables[$matches[1]], static fn(array $row): bool => !in_array((int) ($row['id'] ?? 0), $ids, true)));
            return $before - count($this->tables[$matches[1]]);
        }
        if (preg_match("/UPDATE (wp_idlm_tasks).*WHERE lead_id = ([0-9]+).*task_type = 'Qualification'/i", $normalized, $matches)) {
            $lead_id = (int) $matches[2];
            foreach ($this->tables[$matches[1]] as $index => $row) {
                if ((int) ($row['lead_id'] ?? 0) === $lead_id && 'pending' === ($row['status'] ?? '') && 'Qualification' === ($row['task_type'] ?? '')) {
                    $this->tables[$matches[1]][$index]['status'] = 'completed';
                    $this->tables[$matches[1]][$index]['completed_at'] = '2026-08-28 12:00:00';
                    $this->tables[$matches[1]][$index]['updated_at'] = '2026-08-28 12:00:00';
                }
            }
        }
        return 1;
    }

    public function get_row(string $query, $output = OBJECT) {
        $this->last_error = '';

        $query = preg_replace('/\s+/', ' ', trim($query));

        if (preg_match("/FROM (wp_idlm_number_sequences) WHERE sequence_type = '([^']+)' AND sequence_year = ([0-9]+)/i", $query, $matches)) {
            foreach ($this->tables[$matches[1]] as $row) {
                if ($row['sequence_type'] === $matches[2] && (int) $row['sequence_year'] === (int) $matches[3]) {
                    return $this->format_row($row, $output);
                }
            }
            return null;
        }

        if (preg_match('/SELECT \* FROM wp_users WHERE ID = ([0-9]+) FOR UPDATE/i', $query, $m)) { return get_userdata((int) $m[1]); }
        if (preg_match("/FROM (wp_idlm_quote_revisions) WHERE save_request_key = '([^']+)'/i", $query, $matches)) {
            foreach ($this->tables[$matches[1]] as $row) {
                if ((string) ($row['save_request_key'] ?? '') === stripslashes($matches[2])) {
                    return $this->format_row($row, $output);
                }
            }
            return null;
        }

        if (preg_match('/FROM (wp_idlm_quotes) WHERE lead_id = ([0-9]+) ORDER BY id ASC LIMIT 1/i', $query, $matches)) {
            foreach ($this->tables[$matches[1]] as $row) {
                if ((int) ($row['lead_id'] ?? 0) === (int) $matches[2]) {
                    return $this->format_row($row, $output);
                }
            }
            return null;
        }

        if (preg_match('/FROM (wp_idlm_[a-z_]+)(?: [a-z]+)? WHERE (?:[a-z]+\.)?id = ([0-9]+)/i', $query, $matches)) {
            foreach ($this->tables[$matches[1]] ?? array() as $row) {
                if ((int) ($row['id'] ?? 0) === (int) $matches[2]) {
                    return $this->format_row($row, $output);
                }
            }
            return null;
        }

        $results = $this->get_results($query, $output);
        return $results[0] ?? null;
    }

    private function format_row(array $row, $output) {
        return ARRAY_A === $output ? $row : (object) $row;
    }

    public function get_var(string $query) {
        $this->last_error = '';
        if (preg_match('/SELECT COUNT\(\*\) FROM wp_idlm_quote_revisions WHERE quote_id = ([0-9]+) AND LOWER/', $query, $match)) {
            return count(array_filter($this->tables['wp_idlm_quote_revisions'] ?? array(), static function($row) use ($match) {
                return (int) ($row['quote_id'] ?? 0) === (int) $match[1] && !in_array(strtolower(trim($row['status'] ?? 'Draft')), array('draft', 'superseded'), true);
            }));
        }
        if (preg_match('/SELECT COUNT\(\*\) FROM wp_idlm_quote_status_history WHERE quote_id = ([0-9]+) AND/', $query, $match)) {
            return count(array_filter($this->tables['wp_idlm_quote_status_history'] ?? array(), static function($row) use ($match) {
                return (int) ($row['quote_id'] ?? 0) === (int) $match[1] &&
                    (!in_array(strtolower(trim($row['from_status'] ?? '')), array('', 'draft', 'superseded'), true) || !in_array(strtolower(trim($row['to_status'] ?? '')), array('', 'draft', 'superseded'), true));
            }));
        }
        if (strpos($query, 'SELECT ENGINE FROM information_schema.TABLES') === 0) { foreach ($this->schema_engines as $table => $engine) { if (strpos($query, $table) !== false) { return $engine; } } return 'InnoDB'; }
        $query = preg_replace('/\s+/', ' ', trim($query));
        if (strpos($query, "SELECT option_value FROM wp_options WHERE option_name = 'wp_user_roles' FOR UPDATE") === 0) {
            $roles = array(); foreach (idlm_get_crm_role_slugs() as $name) { $roles[$name] = array('name' => $name, 'capabilities' => idlm_test_role_caps($name)); }
            return serialize($roles);
        }
        if (preg_match("/SELECT option_value FROM wp_options WHERE option_name = '([^']+)'/", $query, $option)) { foreach ($this->tables['wp_options'] ?? array() as $row) { if ($row['option_name']===$option[1]) { return $row['option_value']; } } }
        if (preg_match("/SELECT option_value FROM wp_options WHERE option_name = '([^']+)'/", $query, $option)) { return $GLOBALS['idlm_test_options'][$option[1]] ?? null; }
        if (preg_match('/SELECT COUNT\(\*\) FROM wp_idlm_leads WHERE assigned_rep_id = ([0-9]+)/', $query, $m)) {
            return count(array_filter($this->tables['wp_idlm_leads'], static fn($r) => (int)($r['assigned_rep_id'] ?? 0) === (int)$m[1] && IDLM_Assignment_Capacity::is_active($r)));
        }
        if ($query === 'SELECT CONNECTION_ID()' || $query === 'SELECT @@autocommit') { return 1; }
        if (preg_match("/SELECT lead_id FROM (wp_idlm_lead_touchpoints) WHERE platform = '([^']+)' AND external_id = '([^']+)'/", $query, $touch)) {
            foreach ($this->tables[$touch[1]] as $row) { if (($row['platform'] ?? '') === $touch[2] && ($row['external_id'] ?? '') === $touch[3]) { return $row['lead_id']; } } return null;
        }
        if (preg_match("/SELECT (GET_LOCK|RELEASE_LOCK|IS_USED_LOCK)\('([^']+)'/", $query, $lock)) {
            $held = $GLOBALS['idlm_test_named_locks'][$lock[2]] ?? 0;
            if ($lock[1] === 'GET_LOCK' && $this->deny_schema_lock && strpos($lock[2], 'idlm_schema_') === 0) { return 0; }
            if ($lock[1] === 'GET_LOCK') { $GLOBALS['idlm_test_named_locks'][$lock[2]] = $held + 1; return 1; }
            if ($lock[1] === 'RELEASE_LOCK') { $GLOBALS['idlm_test_named_locks'][$lock[2]] = max(0, $held - 1); return $held ? 1 : null; }
            return $held ? 1 : null;
        }
        if (strpos($query, "SELECT option_value FROM wp_options WHERE option_name = 'idlm_recovery_generation'") === 0) { return $GLOBALS['idlm_test_options']['idlm_recovery_generation'] ?? null; }


        if (preg_match('/SELECT assigned_rep_id FROM (wp_idlm_leads) WHERE id = ([0-9]+)/i', $query, $matches)) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);
            return $row ? ($row['assigned_rep_id'] ?? 0) : null;
        }

        if (preg_match('/SELECT quote_value FROM (wp_idlm_leads) WHERE id = ([0-9]+)/i', $query, $matches)) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);
            return $row ? ($row['quote_value'] ?? 0) : null;
        }

        if (preg_match('/SELECT (?:id|1) FROM (wp_idlm_leads) WHERE id = ([0-9]+)(.*)$/i', $query, $matches)) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);
            if (!$row) {
                return null;
            }
            if (preg_match('/assigned_rep_id = ([0-9]+)/i', $matches[3], $owner) && (int) ($row['assigned_rep_id'] ?? 0) !== (int) $owner[1]) {
                return null;
            }
            return $row['id'];
        }

        if (preg_match('/SELECT l\.id FROM (wp_idlm_leads) l WHERE l\.id = ([0-9]+)(.*)$/i', $query, $matches)) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);
            if (!$row) {
                return null;
            }
            if (preg_match('/l\.assigned_rep_id = ([0-9]+)/i', $matches[3], $owner) && (int) ($row['assigned_rep_id'] ?? 0) !== (int) $owner[1]) {
                return null;
            }
            return $row['id'];
        }

        if (preg_match('/SELECT created_at FROM (wp_idlm_leads) WHERE id = ([0-9]+)/i', $query, $matches)) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);
            return $row['created_at'] ?? null;
        }

        if (preg_match("/SELECT id FROM (wp_idlm_tasks) WHERE lead_id = ([0-9]+).*task_type = '([^']+)'/i", $query, $matches)) {
            foreach ($this->tables[$matches[1]] as $row) {
                if ((int) $row['lead_id'] === (int) $matches[2] && ($row['task_type'] ?? '') === $matches[3]) {
                    if (preg_match('/assigned_rep_id = ([0-9]+)/i', $query, $owner) && (int) ($row['assigned_rep_id'] ?? 0) !== (int) $owner[1]) {
                        continue;
                    }
                    if (false !== stripos($query, "status = 'pending'") && 'pending' !== ($row['status'] ?? '')) {
                        continue;
                    }
                    return $row['id'];
                }
            }
            return null;
        }

        if (preg_match("/SELECT COUNT\(\*\) FROM (wp_idlm_tasks) WHERE lead_id = ([0-9]+).*task_type = 'Qualification'.*status = 'completed'/i", $query, $matches)) {
            return count(array_filter($this->tables[$matches[1]], static function(array $row) use ($matches): bool {
                return (int) $row['lead_id'] === (int) $matches[2] && 'Qualification' === ($row['task_type'] ?? '') && 'completed' === ($row['status'] ?? '');
            }));
        }

        if (preg_match('/SELECT MAX\(revision_number\) FROM (wp_idlm_quote_revisions) WHERE quote_id = ([0-9]+)/i', $query, $matches)) {
            $numbers = array_column(array_filter($this->tables[$matches[1]], static fn(array $row): bool => (int) $row['quote_id'] === (int) $matches[2]), 'revision_number');
            return $numbers ? max($numbers) : null;
        }

        if (preg_match('/SELECT id FROM (wp_idlm_quotes) WHERE lead_id = ([0-9]+) LIMIT 1/i', $query, $matches)) {
            foreach ($this->tables[$matches[1]] as $row) {
                if ((int) ($row['lead_id'] ?? 0) === (int) $matches[2]) {
                    return $row['id'];
                }
            }
            return null;
        }

        if (preg_match("/SELECT id FROM (wp_idlm_quote_item_categories) WHERE category_name = '([^']*)' AND status = 'active'/i", $query, $matches)) {
            foreach ($this->tables[$matches[1]] as $row) {
                if ((string) ($row['category_name'] ?? '') === stripslashes($matches[2]) && 'active' === ($row['status'] ?? '')) {
                    return $row['id'];
                }
            }
            return null;
        }

        if (preg_match("/SELECT id FROM (wp_idlm_quote_item_units) WHERE unit_name = '([^']*)' AND status = 'active'/i", $query, $matches)) {
            foreach ($this->tables[$matches[1]] as $row) {
                if ((string) ($row['unit_name'] ?? '') === stripslashes($matches[2]) && 'active' === ($row['status'] ?? '')) {
                    return $row['id'];
                }
            }
            return null;
        }

        if (preg_match('/SELECT status FROM (wp_idlm_quote_catalogue_items) WHERE id = ([0-9]+)/i', $query, $matches)) {
            $row = $this->find_by_id($matches[1], (int) $matches[2]);
            return $row['status'] ?? null;
        }

        if (preg_match("/SELECT id FROM (wp_idlm_quote_catalogue_items) WHERE item_code = '([^']*)'/i", $query, $matches)) {
            foreach ($this->tables[$matches[1]] as $row) {
                if ((string) ($row['item_code'] ?? '') === stripslashes($matches[2])) {
                    return $row['id'];
                }
            }
            return null;
        }

        if (preg_match("/SELECT COUNT\(\*\) FROM (wp_idlm_quote_catalogue_items) WHERE category = '([^']*)'/i", $query, $matches)) {
            return count(array_filter($this->tables[$matches[1]], static fn(array $row): bool => (string) ($row['category'] ?? '') === stripslashes($matches[2])));
        }

        if (preg_match('/SELECT COUNT\(\*\) FROM (wp_idlm_quote_catalogue_items)(?: WHERE .*)?$/i', $query, $matches)) {
            return count($this->tables[$matches[1]]);
        }

        if (preg_match('/SELECT COUNT\(\*\) FROM (wp_idlm_lead_activity_log).*lead_id = ([0-9]+).*activity_type = \'outbound_call\'/i', $query, $matches)) {
            return count(array_filter($this->tables[$matches[1]], static function(array $row) use ($matches): bool {
                return (int) $row['lead_id'] === (int) $matches[2] && 'outbound_call' === ($row['activity_type'] ?? '');
            }));
        }

        return $this->custom_get_var_result;
    }

    private function find_by_id(string $table, int $id): ?array {
        foreach ($this->tables[$table] ?? array() as $row) {
            if ((int) ($row['id'] ?? 0) === $id) {
                return $row;
            }
        }
        return null;
    }

    public function get_results(string $query, $output = OBJECT): array {
        $this->last_error = '';
        $this->last_get_results_query = preg_replace('/\s+/', ' ', trim($query));
        $query = $this->last_get_results_query;
        if (strpos($query, 'SELECT option_name, option_value FROM wp_options WHERE option_name IN') === 0) {
            $rows=array();foreach(array('idlm_max_active_leads_per_rep',IDLM_Settings::ROUTING_OPTION) as $key) {
                if (array_key_exists($key,$GLOBALS['idlm_test_options'])) { $value=$GLOBALS['idlm_test_options'][$key];$rows[]=(object)array('option_name'=>$key,'option_value'=>is_array($value)?serialize($value):$value); }
            }return $rows;
        }
        if (preg_match("/SELECT \* FROM (wp_idlm_tasks) WHERE lead_id = ([0-9]+) AND task_type = 'Qualification'/i", $query, $m)) {
            $rows = array_values(array_filter($this->tables[$m[1]], static fn(array $row): bool => (int) ($row['lead_id'] ?? 0) === (int) $m[2] && 'Qualification' === ($row['task_type'] ?? '')));
            usort($rows, static fn(array $a, array $b): int => array($a['due_date'] ?? '', $a['id']) <=> array($b['due_date'] ?? '', $b['id']));
            return $this->format_rows($rows, $output);
        }
        if (preg_match('/^SHOW (COLUMNS|INDEX) FROM `([a-z_]+)`$/', $query, $metadata)) {
            foreach (array_reverse($this->dbdelta) as $sql) {
                if (strpos($sql, 'CREATE TABLE ' . $metadata[2] . ' (') !== 0) { continue; }
                $rows = array();
                foreach (explode("\n", $sql) as $line) {
                    $line = rtrim(trim($line), ',');
                    if ($metadata[1] === 'COLUMNS' && preg_match('/^([a-z_]+) ([a-z]+(?:\([0-9,]+\))?(?: unsigned)?)(.*)$/i', $line, $m)) {
                        preg_match("/DEFAULT (NULL|'[^']*'|[0-9.]+)/", $m[3], $d);
                        $rows[] = array('Field' => $m[1], 'Type' => $m[2], 'Null' => strpos($m[3], 'NOT NULL') === false ? 'YES' : 'NO', 'Extra' => strpos($m[3], 'AUTO_INCREMENT') !== false ? 'auto_increment' : '', 'Default' => empty($d) || $d[1] === 'NULL' ? null : trim($d[1], "'"));
                    }
                    if ($metadata[1] === 'INDEX' && preg_match('/^(PRIMARY KEY|UNIQUE KEY ([a-z_]+)|KEY ([a-z_]+))\s*\(([^)]+)\)$/i', $line, $m)) {
                        foreach (array_map('trim', explode(',', $m[4])) as $i => $column) { $rows[] = array('Key_name' => $m[1] === 'PRIMARY KEY' ? 'PRIMARY' : ($m[2] ?: $m[3]), 'Seq_in_index' => $i + 1, 'Column_name' => $column, 'Non_unique' => strpos($m[1], 'KEY ') === 0 ? 1 : 0, 'Sub_part' => null); }
                    }
                }
                return ARRAY_A === $output ? $rows : array_map(static function ($row) { return (object) $row; }, $rows);
            }
            return array();
        }
        if (preg_match('/^SHOW INDEX FROM wp_idlm_private_file(s|_chunks)$/', $query, $match)) {
            $definitions = $match[1] === 's' ? array('PRIMARY' => array('id'), 'attachment_id' => array('attachment_id')) : array('PRIMARY' => array('file_id','variant','part'));
            $rows = array(); foreach ($definitions as $name => $columns) { foreach ($columns as $i => $column) { $rows[] = (object) array('Non_unique' => 0, 'Key_name' => $name, 'Seq_in_index' => $i + 1, 'Column_name' => $column); } } return $rows;
        }

        if (preg_match('/SELECT l\.id, l\.first_name, l\.last_name, l\.phone, l\.email, l\.project_name, l\.property_location, l\.preferred_studio, l\.assigned_rep_id, u\.display_name AS assigned_rep_name FROM (wp_idlm_leads) l LEFT JOIN wp_users u ON u\.ID = l\.assigned_rep_id WHERE (.+?)(?: ORDER BY|$)/i', $query, $matches)) {
            $where = $matches[2];
            $rows = array_values(array_filter($this->tables[$matches[1]], static function(array $row) use ($where): bool {
                if (false !== stripos($where, 'l.is_duplicate = 0') && !empty($row['is_duplicate'])) {
                    return false;
                }
                if (preg_match('/l\.id = ([0-9]+)/i', $where, $lead) && (int) ($row['id'] ?? 0) !== (int) $lead[1]) {
                    return false;
                }
                if (preg_match('/l\.assigned_rep_id = ([0-9]+)/i', $where, $owner) && (int) ($row['assigned_rep_id'] ?? 0) !== (int) $owner[1]) {
                    return false;
                }
                if (preg_match_all("/LIKE '([^']*)'/i", $where, $likes) && !empty($likes[1])) {
                    $needle = strtolower(str_replace(array('%', '\\\\_', '\\\\%'), '', stripslashes($likes[1][0])));
                    $searchable = strtolower(implode(' ', array(
                        $row['first_name'] ?? '', $row['last_name'] ?? '', $row['phone'] ?? '',
                        $row['email'] ?? '', $row['project_name'] ?? '', $row['property_location'] ?? '',
                    )));
                    if ('' !== $needle && false === strpos($searchable, $needle)) {
                        return false;
                    }
                }
                return true;
            }));
            usort($rows, static fn(array $a, array $b): int => array($a['first_name'] ?? '', $a['last_name'] ?? '') <=> array($b['first_name'] ?? '', $b['last_name'] ?? ''));
            if (preg_match('/LIMIT ([0-9]+)/i', $query, $limit)) {
                $rows = array_slice($rows, 0, (int) $limit[1]);
            }
            foreach ($rows as $index => $row) {
                $user = $GLOBALS['idlm_test_users'][(int) ($row['assigned_rep_id'] ?? 0)] ?? null;
                $rows[$index]['assigned_rep_name'] = $user ? $user->display_name : null;
            }
            return $this->format_rows($rows, $output);
        }

        if (preg_match('/SELECT DATE\(created_at\) activity_date.*FROM (wp_idlm_lead_activity_log).*lead_id = ([0-9]+)/i', $query, $matches)) {
            $days = array();
            foreach ($this->tables[$matches[1]] as $row) {
                if ((int) ($row['lead_id'] ?? 0) !== (int) $matches[2]) {
                    continue;
                }
                $type = $row['activity_type'] ?? '';
                if (!in_array($type, array('outbound_call', 'whatsapp_outbound', 'closure_message'), true)) {
                    continue;
                }
                $date = substr((string) $row['created_at'], 0, 10);
                $days[$date] ??= array('activity_date' => $date, 'calls' => 0, 'whatsapp' => 0, 'closure' => 0);
                if ('outbound_call' === $type) {
                    $days[$date]['calls']++;
                } elseif ('whatsapp_outbound' === $type) {
                    $days[$date]['whatsapp']++;
                } else {
                    $days[$date]['closure']++;
                }
            }
            ksort($days);
            return $this->format_rows(array_values($days), $output);
        }

        if (strpos($query, 'SELECT DISTINCT c.id, c.phone FROM') === 0) {
            preg_match('/ IN \(([^)]+)\)/', $query, $m); $phones=array_map(static fn($v)=>trim($v," '"),explode(',',$m[1]??''));$found=array();
            foreach($this->tables['wp_idlm_leads'] as $row) {
                $digits=preg_replace('/[^0-9]/','',$row['phone']??''); if(!in_array($digits,$phones,true)){continue;}
                $id=!empty($row['is_duplicate'])?($row['duplicate_of_id']??0):$row['id'];
                foreach($this->tables['wp_idlm_leads'] as $c){if($c['id']==$id && empty($c['is_duplicate'])){$found[$id]=(object)array('id'=>$id,'phone'=>$c['phone']);}}
            } return array_slice(array_values($found),0,3);
        }
        if (preg_match('/SELECT id, email, phone FROM (wp_idlm_leads).*is_duplicate = 0/i', $query, $matches)) {
            $rows = array_values(array_filter($this->tables[$matches[1]], static fn(array $row): bool => empty($row['is_duplicate'])));
            $rows = array_map(static fn(array $row): array => array('id'=>$row['id'], 'email'=>$row['email'] ?? '', 'phone'=>$row['phone'] ?? ''), $rows);
            return $this->format_rows($rows, $output);
        }

        if (preg_match('/SELECT \* FROM (wp_idlm_leads) WHERE is_duplicate = 1/i', $query, $matches)) {
            $rows = array_values(array_filter($this->tables[$matches[1]], static fn(array $row): bool => !empty($row['is_duplicate']) && !empty($row['duplicate_of_id'])));
            return $this->format_rows($rows, $output);
        }

        if (preg_match('/SELECT \* FROM (wp_idlm_quote_revision_sections) WHERE revision_id = ([0-9]+)/i', $query, $matches)) {
            $rows = array_values(array_filter($this->tables[$matches[1]], static fn(array $row): bool => (int) $row['revision_id'] === (int) $matches[2]));
            return $this->format_rows($rows, $output);
        }

        if (preg_match('/SELECT \* FROM (wp_idlm_quote_revision_items) WHERE revision_id = ([0-9]+)/i', $query, $matches)) {
            $rows = array_values(array_filter($this->tables[$matches[1]], static fn(array $row): bool => (int) $row['revision_id'] === (int) $matches[2]));
            return $this->format_rows($rows, $output);
        }

        if (preg_match("/SELECT id, item_code, category, item_name, description, unit, is_wood, default_rate FROM (wp_idlm_quote_catalogue_items) WHERE status = 'active'/i", $query, $matches)) {
            $rows = array_values(array_filter($this->tables[$matches[1]], static fn(array $row): bool => 'active' === ($row['status'] ?? '')));
            usort($rows, static fn(array $a, array $b): int => array($a['category'] ?? '', $a['item_name'] ?? '') <=> array($b['category'] ?? '', $b['item_name'] ?? ''));
            return $this->format_rows($rows, $output);
        }

        if (preg_match('/SELECT \* FROM (wp_idlm_quote_catalogue_items).*ORDER BY (item_code|category|item_name|unit|is_wood|default_rate|status) (ASC|DESC)/i', $query, $matches)) {
            $rows = $this->tables[$matches[1]];
            $column = strtolower($matches[2]);
            $direction = 'DESC' === strtoupper($matches[3]) ? -1 : 1;
            usort($rows, static function(array $a, array $b) use ($column, $direction): int {
                $left = $a[$column] ?? '';
                $right = $b[$column] ?? '';
                $comparison = in_array($column, array('is_wood', 'default_rate'), true)
                    ? (float) $left <=> (float) $right
                    : (string) $left <=> (string) $right;
                return $comparison * $direction;
            });
            return $this->format_rows($rows, $output);
        }

        if (preg_match('/SELECT id, category_name FROM (wp_idlm_quote_item_categories) WHERE id IN \(([^)]+)\)/i', $query, $matches)) {
            $ids = array_map('intval', explode(',', $matches[2]));
            $rows = array_values(array_filter($this->tables[$matches[1]], static fn(array $row): bool => in_array((int) ($row['id'] ?? 0), $ids, true)));
            return $this->format_rows($rows, $output);
        }

        if (preg_match('/SELECT id, category_name, status FROM (wp_idlm_quote_item_categories) ORDER BY status, category_name/i', $query, $matches)) {
            return $this->format_rows($this->tables[$matches[1]], $output);
        }

        if (preg_match('/SELECT id, item_code, category, item_name, description, unit, IF\(is_wood = 1, \'Yes\', \'No\'\) AS is_wood, default_rate, status FROM (wp_idlm_quote_catalogue_items) WHERE id > ([0-9]+).*LIMIT ([0-9]+)/i', $query, $matches)) {
            $rows = array_values(array_filter($this->tables[$matches[1]], static fn(array $row): bool => (int) ($row['id'] ?? 0) > (int) $matches[2]));
            usort($rows, static fn(array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);
            $rows = array_slice($rows, 0, (int) $matches[3]);
            foreach ($rows as $index => $row) {
                $rows[$index]['is_wood'] = !empty($row['is_wood']) ? 'Yes' : 'No';
            }
            return $this->format_rows($rows, $output);
        }

        if (0 === stripos($query, 'SELECT')) {
            return $this->format_rows($this->custom_query_results, $output);
        }
        return array();
    }

    private function format_rows(array $rows, $output): array {
        if (ARRAY_A === $output) {
            return array_map(static fn(array $row): array => $row, $rows);
        }
        return array_map(static fn(array $row): object => (object) $row, $rows);
    }

    public function get_col(string $query): array {
        if ($query === 'SHOW COLUMNS FROM wp_idlm_private_files') { return array('id','lead_id','attachment_id','purpose','filename','mime','byte_size','sha256','state','archive','created_at'); }
        if ($query === 'SHOW COLUMNS FROM wp_idlm_private_file_chunks') { return array('file_id','variant','part','data'); }
        $query = preg_replace('/\s+/', ' ', trim($query));
        if (preg_match('/SELECT id FROM (wp_idlm_leads) WHERE id = ([0-9]+) OR duplicate_of_id = ([0-9]+)/i', $query, $matches)) {
            return array_values(array_map('intval', array_column(array_filter($this->tables[$matches[1]] ?? array(), static function(array $row) use ($matches): bool {
                return (int) $row['id'] === (int) $matches[2] || (int) ($row['duplicate_of_id'] ?? 0) === (int) $matches[3];
            }), 'id')));
        }
        if (preg_match("/SELECT DISTINCT t\.lead_id FROM (wp_idlm_lead_touchpoints) t INNER JOIN (wp_idlm_leads) l ON l\.id = t\.lead_id WHERE t\.platform = '([^']+)' AND t\.external_id = '([^']+)' AND l\.is_duplicate = 0/i", $query, $matches)) {
            return array_values(array_unique(array_map('intval', array_column(array_filter($this->tables[$matches[1]] ?? array(), static function(array $row) use ($matches): bool {
                return ($row['platform'] ?? '') === stripslashes($matches[3]) && ($row['external_id'] ?? '') === stripslashes($matches[4]);
            }), 'lead_id'))));
        }
        if (preg_match("/SELECT id FROM (wp_idlm_leads) WHERE is_duplicate = 0 AND fb_lead_id = '([^']+)'/i", $query, $matches)) {
            return array_values(array_map('intval', array_column(array_filter($this->tables[$matches[1]] ?? array(), static function(array $row) use ($matches): bool {
                return empty($row['is_duplicate']) && ($row['fb_lead_id'] ?? '') === stripslashes($matches[2]);
            }), 'id')));
        }
        if (preg_match("/SELECT unit_name FROM (wp_idlm_quote_item_units) WHERE status = 'active' ORDER BY unit_name/i", $query, $matches)) {
            $units = array_values(array_map(static fn(array $row): string => (string) $row['unit_name'], array_filter($this->tables[$matches[1]], static fn(array $row): bool => 'active' === ($row['status'] ?? ''))));
            sort($units);
            return $units;
        }
        return array();
    }
}

$GLOBALS['wpdb'] = new IDLM_Test_WPDB();
$GLOBALS['idlm_test_options'] = array();
$GLOBALS['idlm_test_actions'] = array();
$GLOBALS['idlm_test_routes'] = array();
$GLOBALS['idlm_test_hooks'] = array();
$GLOBALS['idlm_test_users'] = array();
$GLOBALS['idlm_test_user_meta'] = array();
$GLOBALS['idlm_test_current_user_id'] = 0;
$GLOBALS['idlm_test_current_roles'] = array();
$GLOBALS['idlm_test_caps'] = array();
$GLOBALS['idlm_test_logged_in'] = false;
$GLOBALS['idlm_test_nonce_valid'] = true;
$GLOBALS['idlm_test_transients'] = array();
$GLOBALS['idlm_test_object_cache'] = array();
$GLOBALS['idlm_test_use_object_cache'] = false;
$GLOBALS['idlm_test_password_counter'] = 0;
$GLOBALS['idlm_test_asset_calls'] = array();
$GLOBALS['idlm_test_menu_calls'] = array();
$GLOBALS['idlm_test_remote_response'] = null;

function idlm_test_reset_environment(): void {
    $GLOBALS['idlm_test_option_write_failures'] = array();
    $GLOBALS['wpdb']->reset();
    $GLOBALS['idlm_test_options'] = array();
    $GLOBALS['idlm_test_actions'] = array();
    $GLOBALS['idlm_test_routes'] = array();
    $GLOBALS['idlm_test_hooks'] = array();
    $GLOBALS['idlm_test_users'] = array();
    $GLOBALS['idlm_test_role_cap_overrides'] = array();
    $GLOBALS['idlm_test_user_meta'] = array();
    $GLOBALS['idlm_test_current_user_id'] = 0;
    $GLOBALS['idlm_test_current_roles'] = array();
    $GLOBALS['idlm_test_caps'] = array();
    $GLOBALS['idlm_test_logged_in'] = false;
    $GLOBALS['idlm_test_nonce_valid'] = true;
    $GLOBALS['idlm_test_transients'] = array();
    $GLOBALS['idlm_test_object_cache'] = array();
    $GLOBALS['idlm_test_use_object_cache'] = false;
    $GLOBALS['idlm_test_password_counter'] = 0;
    $GLOBALS['idlm_test_schedules'] = array();
    $GLOBALS['idlm_test_asset_calls'] = array();
    $GLOBALS['idlm_test_menu_calls'] = array();
    $GLOBALS['post'] = null;
    $_GET = array();
    $_POST = array();
    $_FILES = array();
    $_SERVER = array();
}

function idlm_test_login(int $user_id, array $roles, array $caps = array()): void {
    $GLOBALS['idlm_test_current_user_id'] = $user_id;
    $GLOBALS['idlm_test_current_roles'] = $roles;
    $GLOBALS['idlm_test_caps'] = array_fill_keys($caps, true);
    $GLOBALS['idlm_test_logged_in'] = true;
    if (!isset($GLOBALS['idlm_test_users'][$user_id])) {
        $GLOBALS['idlm_test_users'][$user_id] = (object) array(
            'ID' => $user_id,
            'roles' => $roles,
            'display_name' => 'User ' . $user_id,
            'user_email' => 'user' . $user_id . '@example.test',
        );
    }
    $GLOBALS['idlm_test_users'][$user_id]->caps = array_fill_keys($caps, true);
}

function dbDelta(string $sql): array {
    $GLOBALS['wpdb']->dbdelta[] = $sql;
    return array($sql);
}

function get_option(string $name, $default = false) {
    return array_key_exists($name, $GLOBALS['idlm_test_options']) ? $GLOBALS['idlm_test_options'][$name] : $default;
}

function update_option(string $name, $value, $autoload = null): bool {
    if (in_array($name, $GLOBALS['idlm_test_option_write_failures'] ?? array(), true)) { return false; }
    $GLOBALS['idlm_test_options'][$name] = $value;
    return true;
}

function add_option(string $name, $value = '', string $deprecated = '', $autoload = 'yes'): bool {
    if (array_key_exists($name, $GLOBALS['idlm_test_options'])) {
        return false;
    }
    $GLOBALS['idlm_test_options'][$name] = $value;
    return true;
}

function delete_option(string $name): bool {
    unset($GLOBALS['idlm_test_options'][$name]);
    return true;
}

function current_time(string $type, bool $gmt = false) {
    if ('mysql' === $type) {
        return '2026-08-28 12:00:00';
    }
    if ('timestamp' === $type || 'U' === $type) {
        return strtotime('2026-08-28 12:00:00 UTC');
    }
    return wp_date($type, strtotime('2026-08-28 12:00:00 UTC'));
}

function wp_timezone(): DateTimeZone {
    return new DateTimeZone('UTC');
}

function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string {
    $date = new DateTime('@' . ($timestamp ?? strtotime('2026-08-28 12:00:00 UTC')));
    $date->setTimezone($timezone ?? wp_timezone());
    return $date->format($format);
}

function mysql2date(string $format, string $date, bool $translate = true): string {
    return wp_date($format, strtotime($date));
}

function absint($value): int {
    return abs((int) $value);
}

function sanitize_text_field($value): string {
    if (is_array($value) || is_object($value)) {
        return '';
    }
    return trim(strip_tags((string) $value));
}

function sanitize_textarea_field($value): string {
    return trim(strip_tags((string) $value));
}

function sanitize_email($value): string {
    return strtolower((string) filter_var(trim((string) $value), FILTER_SANITIZE_EMAIL));
}

function sanitize_key($value): string {
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value));
}

function sanitize_file_name($value): string {
    return preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $value);
}

function wp_unslash($value) {
    if (is_array($value)) {
        return array_map('wp_unslash', $value);
    }
    return is_string($value) ? stripslashes($value) : $value;
}

function esc_url_raw($value): string {
    return filter_var((string) $value, FILTER_SANITIZE_URL) ?: '';
}

function esc_url($value): string {
    return esc_url_raw($value);
}

function disabled($disabled, $current = true, bool $display = true): string {
    $result = (string) $disabled === (string) $current ? ' disabled="disabled"' : '';
    if ($display) { echo $result; }
    return $result;
}

function checked($checked, $current = true, bool $display = true): string {
    $result = (string) $checked === (string) $current ? ' checked="checked"' : '';
    if ($display) { echo $result; }
    return $result;
}

function get_rest_url($blog_id = null, string $path = ''): string {
    return 'https://example.test/wp-json/' . ltrim($path, '/');
}

function esc_html($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function esc_attr($value): string {
    return esc_html($value);
}

function esc_sql($value): string {
    return addslashes((string) $value);
}

function wp_kses_post($value): string {
    return (string) $value;
}

function wp_strip_all_tags($value): string {
    return strip_tags((string) $value);
}

function translate_user_role($value): string {
    return (string) $value;
}

function wp_json_encode($value, int $flags = 0): string {
    return (string) json_encode($value, $flags);
}

function wp_salt(string $scheme = 'auth'): string {
    return ($GLOBALS['idlm_test_salt'] ?? 'idlm-test-salt-') . $scheme;
}

function maybe_serialize($value) {
    return is_scalar($value) ? $value : serialize($value);
}

function wp_parse_args($args, array $defaults = array()): array {
    return array_merge($defaults, (array) $args);
}

function wp_generate_uuid4(): string {
    return '12345678-1234-4123-8123-123456789abc';
}

function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string {
    $GLOBALS['idlm_test_password_counter']++;
    $seed = hash('sha256', 'idlm-test-password-' . $GLOBALS['idlm_test_password_counter']);
    return substr(str_repeat($seed, (int) ceil($length / strlen($seed))), 0, $length);
}

function get_bloginfo(string $show = ''): string {
    return 'Test Site';
}

function bloginfo(string $show = ''): void {
    echo get_bloginfo($show);
}

function number_format_i18n($number, int $decimals = 0): string {
    return number_format((float) $number, $decimals, '.', ',');
}

function get_current_user_id(): int {
    return (int) $GLOBALS['idlm_test_current_user_id'];
}

function get_userdata(int $user_id) {
    return $GLOBALS['idlm_test_users'][$user_id] ?? false;
}

function get_user_meta(int $user_id, string $key = '', bool $single = false) {
    $value = $GLOBALS['idlm_test_user_meta'][$user_id][$key] ?? ($single ? '' : array());
    return $single ? $value : (array) $value;
}

function update_user_meta(int $user_id, string $key, $value): bool {
    $GLOBALS['idlm_test_user_meta'][$user_id][$key] = $value;
    return true;
}

function delete_user_meta(int $user_id, string $key): bool {
    unset($GLOBALS['idlm_test_user_meta'][$user_id][$key]);
    return true;
}

function wp_get_current_user(): object {
    return $GLOBALS['idlm_test_users'][get_current_user_id()] ?? (object) array(
        'ID' => 0,
        'roles' => $GLOBALS['idlm_test_current_roles'],
        'display_name' => 'Guest',
        'user_email' => '',
    );
}

function is_user_logged_in(): bool {
    return (bool) $GLOBALS['idlm_test_logged_in'];
}

function wp_login_form(array $args = array()) {
    $args = wp_parse_args($args, array('echo' => true, 'form_id' => 'loginform', 'label_log_in' => 'Log In'));
    $form = '<form id="' . esc_attr($args['form_id']) . '" action="wp-login.php" method="post"><input name="log"><input name="pwd" type="password"><button type="submit">' . esc_html($args['label_log_in']) . '</button></form>';
    if ($args['echo']) {
        echo $form;
        return;
    }
    return $form;
}

function wp_logout_url(string $redirect = ''): string {
    return 'https://example.test/wp-login.php?action=logout&_wpnonce=test&redirect_to=' . rawurlencode($redirect);
}

function wp_lostpassword_url(string $redirect = ''): string {
    return 'https://example.test/wp-login.php?action=lostpassword';
}

function home_url(string $path = ''): string {
    return 'https://example.test' . ('/' === substr($path, 0, 1) ? $path : '/' . $path);
}

function sanitize_user($value): string {
    return preg_replace('/[^a-zA-Z0-9_.@-]/', '', (string) $value);
}

function idlm_test_role_caps(string $name): array {
    if (isset($GLOBALS['idlm_test_role_cap_overrides'][$name])) { return $GLOBALS['idlm_test_role_cap_overrides'][$name]; }
    if ('administrator' === $name) { return array('manage_options' => true, 'idlm_access_leads' => true); }
    return in_array($name, array('sales_manager','sales_executive','idlm_pre_sales','marketing','marketing_manager','campaign_team'), true) ? array('idlm_access_leads' => true) : array();
}
class WP_Role {
    public $name; public $capabilities;
    public function __construct($name, $caps) { $this->name=$name; $this->capabilities=$caps; }
    public function has_cap($cap) { return !empty($this->capabilities[$cap]); }
}
function get_role($name) { return new WP_Role($name, idlm_test_role_caps($name)); }
function user_can($user, string $cap): bool {
    if (!is_object($user)) { $user=get_userdata((int) $user); } if (!$user) { return false; }
    $caps=array();foreach((array)$user->roles as $name){$caps=array_merge($caps,idlm_test_role_caps($name));}
    $caps=array_merge($caps,(array)($user->caps ?? array()));return !empty($caps[$cap]);
}
function maybe_unserialize($value) { $decoded=is_string($value)?@unserialize($value):false;return false === $decoded ? $value : $decoded; }

function current_user_can(string $capability, ...$args): bool {
    return !empty($GLOBALS['idlm_test_caps'][$capability]);
}

function get_users(array $args = array()): array {
    $users = array_filter($GLOBALS['idlm_test_users'], static function(object $user) use ($args): bool {
        return empty($args['role']) || in_array($args['role'], (array) $user->roles, true);
    });
    return 'ID' === ($args['fields'] ?? '') ? array_map(static fn(object $user): int => (int) $user->ID, $users) : array_values($users);
}

function idlm_get_crm_role_slugs(string $scope = 'all'): array {
    if ('manager' === $scope) {
        return array('administrator', 'sales_manager');
    }
    return array('administrator', 'editor', 'author', 'contributor', 'sales_manager', 'sales_executive', 'idlm_pre_sales', 'marketing', 'marketing_manager', 'campaign_team');
}

function is_email(string $email): bool {
    return false !== filter_var($email, FILTER_VALIDATE_EMAIL);
}

function do_action(string $hook, ...$args): void {
    $GLOBALS['idlm_test_actions'][] = array('hook' => $hook, 'args' => $args);
}

function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
    $GLOBALS['idlm_test_hooks'][] = array('type' => 'action', 'hook' => $hook, 'callback' => $callback, 'priority' => $priority);
}

function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {
    $GLOBALS['idlm_test_hooks'][] = array('type' => 'filter', 'hook' => $hook, 'callback' => $callback);
}

function add_shortcode(string $tag, $callback): void {
    $GLOBALS['idlm_test_hooks'][] = array('type' => 'shortcode', 'hook' => $tag, 'callback' => $callback);
}

function add_menu_page(...$args): string {
    $GLOBALS['idlm_test_menu_calls'][] = array('type' => 'menu', 'args' => $args);
    return 'toplevel_page_' . (string) ($args[3] ?? '');
}

function add_submenu_page(...$args): string {
    $GLOBALS['idlm_test_menu_calls'][] = array('type' => 'submenu', 'args' => $args);
    return (string) ($args[3] ?? '') . '_page_' . (string) ($args[4] ?? '');
}

function wp_next_scheduled(string $hook) {
    foreach ($GLOBALS['idlm_test_schedules'] ?? array() as $schedule) {
        if ($schedule['hook'] === $hook) { return $schedule['timestamp']; }
    }
    return false;
}

function wp_schedule_event(int $timestamp, string $recurrence, string $hook): bool {
    $GLOBALS['idlm_test_schedules'][] = compact('timestamp', 'recurrence', 'hook');
    return true;
}

function wp_clear_scheduled_hook(string $hook): int { return 1; }

function register_rest_route(string $namespace, string $route, array $args): bool {
    $GLOBALS['idlm_test_routes'][$namespace . $route] = $args;
    return true;
}

function __return_true(): bool {
    return true;
}

function check_ajax_referer(string $action, $query_arg = false, bool $die = true): int {
    if (!$GLOBALS['idlm_test_nonce_valid']) {
        throw new IDLM_Test_Response(false, array('message' => 'Invalid nonce.'), 403);
    }
    return 1;
}

function check_admin_referer(string $action, string $query_arg = '_wpnonce'): int {
    return check_ajax_referer($action, $query_arg);
}

function wp_verify_nonce($nonce, string $action): bool {
    return (bool) $GLOBALS['idlm_test_nonce_valid'];
}

function wp_send_json_success($data = null, ?int $status_code = null, int $flags = 0): void {
    throw new IDLM_Test_Response(true, $data, $status_code ?? 200);
}

function wp_send_json_error($data = null, ?int $status_code = null, int $flags = 0): void {
    throw new IDLM_Test_Response(false, $data, $status_code ?? 200);
}

function wp_die($message = '', $title = '', $args = array()): void {
    $status = is_int($title) ? $title : (int) ($args['response'] ?? 500);
    throw new IDLM_Test_Response(false, array('message' => (string) $message), $status);
}

function set_transient(string $key, $value, int $expiration = 0): bool {
    $GLOBALS['idlm_test_transients'][$key] = $value;
    return true;
}

function get_transient(string $key) {
    return $GLOBALS['idlm_test_transients'][$key] ?? false;
}

function delete_transient(string $key): bool {
    unset($GLOBALS['idlm_test_transients'][$key]);
    return true;
}

function wp_using_ext_object_cache($using = null): bool {
    if (null !== $using) {
        $GLOBALS['idlm_test_use_object_cache'] = (bool) $using;
    }
    return (bool) $GLOBALS['idlm_test_use_object_cache'];
}

function wp_cache_get(string $key, string $group = '', bool $force = false, &$found = null) {
    $cache_key = $group . ':' . $key;
    $found = array_key_exists($cache_key, $GLOBALS['idlm_test_object_cache']);
    return $found ? $GLOBALS['idlm_test_object_cache'][$cache_key] : false;
}

function wp_cache_set(string $key, $value, string $group = '', int $expire = 0): bool {
    $GLOBALS['idlm_test_object_cache'][$group . ':' . $key] = $value;
    return true;
}

function wp_cache_add(string $key, $value, string $group = '', int $expire = 0): bool {
    $cache_key = $group . ':' . $key;
    if (array_key_exists($cache_key, $GLOBALS['idlm_test_object_cache'])) {
        return false;
    }
    $GLOBALS['idlm_test_object_cache'][$cache_key] = $value;
    return true;
}

function wp_cache_delete(string $key, string $group = ''): bool {
    unset($GLOBALS['idlm_test_object_cache'][$group . ':' . $key]);
    return true;
}

function untrailingslashit(string $value): string {
    return rtrim($value, '/\\');
}

function wp_parse_url(string $url, int $component = -1) {
    return parse_url($url, $component);
}

function wp_safe_remote_get(string $url, array $args = array()) {
    return $GLOBALS['idlm_test_remote_response'] ?? new WP_Error('remote_unavailable', 'No test response configured.');
}

function wp_remote_retrieve_header($response, string $header): string {
    return (string) ($response['headers'][strtolower($header)] ?? '');
}

function is_wp_error($value): bool {
    return $value instanceof WP_Error;
}

function admin_url(string $path = ''): string {
    return 'https://example.test/wp-admin/' . ltrim($path, '/');
}

function remove_query_arg($keys, $url = ''): string {
    return $url ?: 'https://example.test/current';
}

function add_query_arg($args, $url = ''): string {
    return ($url ?: 'https://example.test/current') . '?' . http_build_query((array) $args);
}

function wp_validate_redirect(string $location, string $fallback = ''): string {
    return 0 === strpos($location, 'https://example.test/') ? $location : $fallback;
}

function wp_safe_redirect(string $location, int $status = 302): bool {
    return true;
}

function plugin_dir_url(string $file): string {
    return IDLM_URL;
}

function wp_create_nonce(string $action): string {
    return 'test-nonce';
}

function wp_nonce_url(string $actionurl, string $action = '-1', string $name = '_wpnonce'): string {
    return add_query_arg(array($name => wp_create_nonce($action)), $actionurl);
}

function wp_nonce_field(string $action, string $name = '_wpnonce'): void {
    echo '<input type="hidden" name="' . esc_attr($name) . '" value="test-nonce">';
}

function has_shortcode(string $content, string $tag): bool {
    return false !== strpos($content, '[' . $tag);
}

function wp_enqueue_style(...$args): void {
    $GLOBALS['idlm_test_asset_calls'][] = array('type' => 'enqueue_style', 'args' => $args);
}

function wp_enqueue_script(...$args): void {
    $GLOBALS['idlm_test_asset_calls'][] = array('type' => 'enqueue_script', 'args' => $args);
}

function wp_register_style(...$args): void {
    $GLOBALS['idlm_test_asset_calls'][] = array('type' => 'register_style', 'args' => $args);
}

function wp_register_script(...$args): void {
    $GLOBALS['idlm_test_asset_calls'][] = array('type' => 'register_script', 'args' => $args);
}

function wp_localize_script(...$args): void {
    $GLOBALS['idlm_test_asset_calls'][] = array('type' => 'localize_script', 'args' => $args);
}

function wp_add_inline_script(...$args): void {
    $GLOBALS['idlm_test_asset_calls'][] = array('type' => 'inline_script', 'args' => $args);
}

require_once dirname(__DIR__) . '/includes/class-mcp-core.php';
require_once dirname(__DIR__) . '/includes/class-view.php';
require_once dirname(__DIR__) . '/includes/class-auth.php';
require_once dirname(__DIR__) . '/includes/class-settings.php';
require_once dirname(__DIR__) . '/includes/class-secret-store.php';
require_once dirname(__DIR__) . '/includes/class-production-security.php';
require_once dirname(__DIR__) . '/includes/class-horizontal-readiness.php';
require_once dirname(__DIR__) . '/includes/class-operational-health.php';
require_once dirname(__DIR__) . '/includes/class-state-registry.php';
require_once dirname(__DIR__) . '/includes/class-lead-access-policy.php';
require_once dirname(__DIR__) . '/includes/class-read-model-cache.php';
require_once dirname(__DIR__) . '/includes/class-cursor-pagination.php';
require_once dirname(__DIR__) . '/includes/class-schema-migration.php';
require_once dirname(__DIR__) . '/includes/class-routing-state.php';
require_once dirname(__DIR__) . '/includes/class-capi-delivery.php';
require_once dirname(__DIR__) . '/includes/class-capi-transport.php';
require_once dirname(__DIR__) . '/includes/class-google-conversion-feed.php';
require_once dirname(__DIR__) . '/includes/class-data-retention.php';
require_once dirname(__DIR__) . '/includes/class-db.php';
require_once dirname(__DIR__) . '/includes/class-private-files.php';
require_once dirname(__DIR__) . '/includes/class-recovery-guard.php';
require_once dirname(__DIR__) . '/includes/class-lead-transaction.php';
require_once dirname(__DIR__) . '/includes/class-lead-outbox.php';
require_once dirname(__DIR__) . '/includes/class-presales-task-plan.php';
require_once dirname(__DIR__) . '/includes/class-assignment-capacity.php';
require_once dirname(__DIR__) . '/includes/class-backup-validator.php';
require_once dirname(__DIR__) . '/includes/class-lead-lifecycle-service.php';
require_once dirname(__DIR__) . '/includes/class-identity-review.php';
require_once dirname(__DIR__) . '/includes/class-task-queue-service.php';
require_once dirname(__DIR__) . '/includes/class-lead-deletion-service.php';
require_once dirname(__DIR__) . '/includes/class-integrity-command.php';
require_once dirname(__DIR__) . '/includes/class-handoff-notifications.php';
require_once dirname(__DIR__) . '/includes/class-integration-auth.php';
require_once dirname(__DIR__) . '/includes/class-webhook-intake.php';
require_once dirname(__DIR__) . '/includes/class-google-feed-controller.php';
require_once dirname(__DIR__) . '/includes/class-integrations.php';
require_once dirname(__DIR__) . '/includes/class-audiences.php';
require_once dirname(__DIR__) . '/includes/class-audience-dispatch.php';
require_once dirname(__DIR__) . '/includes/class-scheduler.php';
require_once dirname(__DIR__) . '/includes/class-whatsapp-inbox.php';
require_once dirname(__DIR__) . '/includes/class-interakt.php';
require_once dirname(__DIR__) . '/includes/class-lead-connector.php';
require_once dirname(__DIR__) . '/includes/class-facebook-connect.php';
require_once dirname(__DIR__) . '/includes/class-interakt-batch.php';
require_once dirname(__DIR__) . '/includes/class-interakt-dispatch.php';
require_once dirname(__DIR__) . '/includes/class-dashboard-read-model.php';
require_once dirname(__DIR__) . '/includes/class-dashboard.php';
require_once dirname(__DIR__) . '/includes/class-admin-dashboard-renderer.php';
require_once dirname(__DIR__) . '/includes/class-admin-kanban-renderer.php';
require_once dirname(__DIR__) . '/includes/class-admin-logs-renderer.php';
require_once dirname(__DIR__) . '/includes/class-admin-leads-renderer.php';
require_once dirname(__DIR__) . '/includes/class-admin-settings-controller.php';
require_once dirname(__DIR__) . '/includes/class-admin-settings-renderer.php';
require_once dirname(__DIR__) . '/includes/class-admin-presenter.php';
require_once dirname(__DIR__) . '/includes/class-admin-lead-notifications.php';
require_once dirname(__DIR__) . '/includes/class-admin-assets.php';
require_once dirname(__DIR__) . '/includes/class-admin-shortcodes.php';
require_once dirname(__DIR__) . '/includes/class-admin-account-controller.php';
require_once dirname(__DIR__) . '/includes/class-admin-users-controller.php';
require_once dirname(__DIR__) . '/includes/class-admin.php';
require_once dirname(__DIR__) . '/includes/class-admin-lead-reads.php';
require_once dirname(__DIR__) . '/includes/class-admin-connection-controller.php';
require_once dirname(__DIR__) . '/includes/class-admin-telemetry-controller.php';
require_once dirname(__DIR__) . '/includes/class-admin-activity-controller.php';
require_once dirname(__DIR__) . '/includes/class-admin-task-controller.php';
require_once dirname(__DIR__) . '/includes/class-admin-collection-reads.php';
require_once dirname(__DIR__) . '/includes/class-admin-ajax.php';
require_once dirname(__DIR__) . '/includes/class-quotation-lead-read-model.php';
require_once dirname(__DIR__) . '/includes/class-quotation-policy.php';
require_once dirname(__DIR__) . '/includes/class-quotation-repository.php';
require_once dirname(__DIR__) . '/includes/class-quotation-schema.php';
require_once dirname(__DIR__) . '/includes/class-quotation-calculator.php';
require_once dirname(__DIR__) . '/includes/class-quotation-document.php';
require_once dirname(__DIR__) . '/includes/class-quotation-exporter.php';
require_once dirname(__DIR__) . '/includes/class-quotations.php';
require_once dirname(__DIR__) . '/includes/class-quotation-controller.php';
require_once dirname(__DIR__) . '/includes/class-quotation-renderer.php';

// Dashboard role registry mirrors the production recognition helper.
function wp_roles(): object {
    return (object) array('role_key' => 'wp_user_roles', 'roles' => array(
        'marketing' => array('name' => 'Marketing'),
        'marketing_manager' => array('name' => 'Marketing Manager'),
        'campaign_team' => array('name' => 'Marketing Manager'),
    ));
}

function doing_action($hook = null) { return false; }
