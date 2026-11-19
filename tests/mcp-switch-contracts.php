<?php
/** MCP enable/disable switch and module-review contracts. Builder: Shashi. */

if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type() { return $GLOBALS['idlm_test_environment_type'] ?? 'development'; }
}

/** Counting driver for typed-tool query budgets. */
function idlm_mcp_counting_driver(array $row_sets = array()) {
    return new class($row_sets) {
        public $prefix = 'wp_'; public $users = 'wp_users'; public $queries = array(); public $last_error = '';
        private $row_sets;
        public function __construct($row_sets) { $this->row_sets = $row_sets; }
        public function suppress_errors($value = true) { return false; }
        public function prepare($query, ...$args) { if (1 === count($args) && is_array($args[0])) { $args = $args[0]; } $i = 0; return preg_replace_callback('/%[dsf]/', static function() use (&$i, $args) { return (string) ($args[$i++] ?? ''); }, $query); }
        public $row = null;
        public function get_var($query) { $this->queries[] = $query; return 0; }
        public function get_row($query, $output = null) { $this->queries[] = $query; return $this->row_sets['row'] ?? array(); }
        public function get_results($query, $output = null) { $this->queries[] = $query; return $this->row_sets['results'] ?? array(); }
    };
}

function idlm_mcp_switch_reset(): void {
    idlm_test_reset_environment();
    unset($GLOBALS['idlm_test_environment_type']);
}

idlm_test('MCP switch default keeps production fail-closed and leaves other environments working', static function(): void {
    idlm_mcp_switch_reset();
    $GLOBALS['idlm_test_environment_type'] = 'production';
    assert_false(IDLM_Production_Security::is_mcp_enabled(), 'An untouched production site must stay closed.');
    $GLOBALS['idlm_test_environment_type'] = 'staging';
    assert_true(IDLM_Production_Security::is_mcp_enabled());
    update_option('idlm_mcp_enabled', '0');
    assert_false(IDLM_Production_Security::is_mcp_enabled(), 'An explicit saved Off wins over the environment default.');
    $GLOBALS['idlm_test_environment_type'] = 'production';
    update_option('idlm_mcp_enabled', '1');
    assert_true(IDLM_Production_Security::is_mcp_enabled(), 'An explicit saved On enables production.');
    foreach (array('yes', 'true', '', 0, false, array('1')) as $loose) {
        update_option('idlm_mcp_enabled', $loose);
        assert_false(IDLM_Production_Security::is_mcp_enabled(), 'Only the committed value 1 enables MCP.');
    }
    assert_same('setting', IDLM_Production_Security::mcp_enabled_source());
    unset($GLOBALS['idlm_test_environment_type']);
});

idlm_test('MCP switch saves from Settings only for administrators, with nonce and marker, and audits changes', static function(): void {
    idlm_mcp_switch_reset();
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $base = array('idlm_save_settings' => '1', 'white_label' => IDLM_Settings::white_label_defaults());
    try {
        idlm_test_login(1, array('administrator'), array('manage_options'));
        update_option('idlm_mcp_enabled', '0');
        // A post that never rendered the switch must not change it.
        $_POST = $base;
        IDLM_Admin_Settings_Controller::process_request();
        assert_same('0', get_option('idlm_mcp_enabled'));
        $_POST = $base + array('mcp_enabled_present' => '1', 'mcp_enabled' => '1');
        IDLM_Admin_Settings_Controller::process_request();
        assert_same('1', get_option('idlm_mcp_enabled'));
        assert_true(IDLM_Production_Security::is_mcp_enabled());
        // Rendered but unchecked means Off.
        $_POST = $base + array('mcp_enabled_present' => '1');
        IDLM_Admin_Settings_Controller::process_request();
        assert_same('0', get_option('idlm_mcp_enabled'));
        // Non-committed values are Off.
        $_POST = $base + array('mcp_enabled_present' => '1', 'mcp_enabled' => array('1'));
        IDLM_Admin_Settings_Controller::process_request();
        assert_same('0', get_option('idlm_mcp_enabled'));
        // Users without manage_options cannot change it.
        idlm_test_login(2, array('subscriber'), array('read'));
        $_POST = $base + array('mcp_enabled_present' => '1', 'mcp_enabled' => '1');
        try { IDLM_Admin_Settings_Controller::process_request(); } catch (IDLM_Test_Response $denied) { /* wp_die */ }
        assert_same('0', get_option('idlm_mcp_enabled'));
    } finally { $_POST = array(); }
    $source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-settings-controller.php');
    assert_contains("check_admin_referer('idlm_settings_save_action')", $source);
    assert_contains('IDLM_Telemetry::log(\'mcp_access\'', $source);
});

idlm_test('MCP switch is read-only while a wp-config constant owns the state', static function(): void {
    $source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-production-security.php');
    assert_contains("'constant-disabled'", $source);
    assert_contains("'constant-enabled'", $source);
    $controller = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-settings-controller.php');
    assert_contains("'setting' !== IDLM_Production_Security::mcp_enabled_source()", $controller);
    $template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/settings/mcp.php');
    assert_contains("disabled('setting' !== \$idlm_mcp_source)", $template);
    assert_contains('IDLM_MCP_ENABLED', $template);
});

idlm_test('MCP settings tab shows the state, the switch and hides the key and snippets while off', static function(): void {
    idlm_mcp_switch_reset();
    idlm_test_login(1, array('administrator'), array('manage_options'));
    IDLM_Secret_Store::set('idlm_mcp_api_key', 'SYNTHETIC-MCP-KEY-VISIBLE-ONLY-WHEN-ON');
    $context = array('active_tab' => 'mcp', 'mcp_script_path' => '/x/mcp-server.php', 'mcp_rest_url' => 'https://example.test/wp-json/x', 'mcp_local_json' => '{}');
    update_option('idlm_mcp_enabled', '0');
    $off = IDLM_View::render('admin/settings/mcp.php', array('context' => $context) + $context);
    assert_contains('MCP disabled', $off);
    assert_contains('name="mcp_enabled"', $off);
    assert_contains('name="mcp_enabled_present"', $off);
    assert_contains('Every MCP endpoint refuses requests', $off);
    assert_false(strpos($off, 'SYNTHETIC-MCP-KEY-VISIBLE-ONLY-WHEN-ON') !== false, 'The key must not be printed while MCP is off.');
    assert_false(strpos($off, 'idlm-mcp-claude-remote') !== false);
    assert_false(strpos($off, ' checked') !== false);
    update_option('idlm_mcp_enabled', '1');
    $on = IDLM_View::render('admin/settings/mcp.php', array('context' => $context) + $context);
    assert_contains('MCP enabled', $on);
    assert_contains('idlm-mcp-claude-remote', $on);
    assert_contains('checked="checked"', $on);
    assert_contains('SYNTHETIC-MCP-KEY-VISIBLE-ONLY-WHEN-ON', $on);
});

idlm_test('MCP disabled refuses every endpoint and tool before any database work', static function(): void {
    idlm_mcp_switch_reset();
    update_option('idlm_mcp_enabled', '0');
    IDLM_Secret_Store::set('idlm_mcp_api_key', 'switch-key');
    $driver = idlm_mcp_counting_driver();
    $original = $GLOBALS['wpdb'];
    $GLOBALS['wpdb'] = $driver;
    try {
        $headers = array('X-IDLM-API-Key' => 'switch-key');
        $permission = IDLM_Integrations::permission_mcp(new WP_REST_Request($headers));
        assert_true(is_wp_error($permission));
        assert_same(503, $permission->get_error_data()['status']);
        foreach (array('handle_mcp_get_info', 'handle_mcp_delete_session') as $handler) {
            $response = IDLM_Integrations::$handler(new WP_REST_Request($headers));
            assert_same(503, $response->get_status());
            assert_same(-32003, $response->get_data()['error']['code']);
        }
        $post = IDLM_Integrations::handle_mcp_post(new WP_REST_Request($headers, array(), array(), array(), wp_json_encode(array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'))));
        assert_same(503, $post->get_status());
        $batch = IDLM_Integrations::handle_mcp_post(new WP_REST_Request($headers, array(), array(), array(), wp_json_encode(array(array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list')))));
        assert_same(503, $batch->get_status());
        foreach (array('get_leads_summary', 'get_reps_performance', 'run_custom_query', 'query_leads', 'get_lead_details') as $tool) {
            assert_contains('disabled', IDLM_MCP_Core::execute_tool($tool, array('sql' => 'SELECT id FROM wp_idlm_leads', 'id' => 1))['error']);
        }
        foreach (array('initialize', 'tools/list', 'tools/call') as $method) {
            assert_same(-32003, IDLM_MCP_Core::handle_json_rpc(array('jsonrpc' => '2.0', 'id' => 5, 'method' => $method, 'params' => array('name' => 'get_leads_summary')))['error']['code']);
        }
        assert_same(array(), $driver->queries, 'A disabled MCP must not touch the database.');
    } finally { $GLOBALS['wpdb'] = $original; }
    update_option('idlm_mcp_enabled', '1');
    assert_true(true === IDLM_Integrations::permission_mcp(new WP_REST_Request(array('X-IDLM-API-Key' => 'switch-key'))));
});

idlm_test('MCP stdio server reads the switch fresh for every request', static function(): void {
    $source = (string) file_get_contents(dirname(__DIR__) . '/mcp-server.php');
    assert_contains("'idlm_mcp_enabled'", $source);
    assert_contains('IDLM_MCP_Core::handle_json_rpc', $source);
});

idlm_test('MCP internal errors never echo exception text to the caller', static function(): void {
    idlm_mcp_switch_reset();
    update_option('idlm_mcp_enabled', '1');
    $original = $GLOBALS['wpdb'];
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_'; public $users = 'wp_users';
        public function __call($name, $args) { throw new Error('SYNTHETIC-SECRET table wp_idlm_leads path /var/www'); }
    };
    try {
        $response = IDLM_MCP_Core::handle_json_rpc(array('jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => array('name' => 'get_leads_summary', 'arguments' => array())));
        assert_same(-32603, $response['error']['code']);
        assert_same('Internal error.', $response['error']['message']);
        assert_false(strpos(wp_json_encode($response), 'SYNTHETIC-SECRET') !== false);
    } finally { $GLOBALS['wpdb'] = $original; }
});

idlm_test('MCP lead details return only granted columns, never file paths or ad-click identifiers', static function(): void {
    idlm_mcp_switch_reset();
    update_option('idlm_mcp_enabled', '1');
    update_option('idlm_mcp_allow_pii', '1');
    $driver = idlm_mcp_counting_driver(array('row' => null));
    $original = $GLOBALS['wpdb'];
    $GLOBALS['wpdb'] = $driver;
    try {
        IDLM_MCP_Core::execute_tool('get_lead_details', array('id' => 4));
        $query = implode(' ', $driver->queries);
        assert_false(strpos($query, 'SELECT *') !== false, 'Typed lead details must not use SELECT *.');
        foreach (array('floor_plan_file', 'floor_plan_files', 'quote_file', 'gclid', 'fbclid', 'fbc', 'fbp', 'fb_lead_id', 'li_fat_id', 'landing_page', 'referrer_details', 'utm_source') as $private) {
            assert_false(1 === preg_match('/\b' . $private . '\b/', $query), "$private must not be selected.");
        }
        assert_contains('first_name', $query);
        assert_contains('assigned_rep_id', $query);
        foreach (array(array(), array('id' => 0), array('id' => -3), array('id' => array(1)), array('id' => 'abc')) as $bad) {
            assert_contains('positive lead id', IDLM_MCP_Core::execute_tool('get_lead_details', $bad)['error']);
        }
    } finally { $GLOBALS['wpdb'] = $original; update_option('idlm_mcp_allow_pii', '0'); }
});

idlm_test('MCP lead search pages deterministically and validates filter types', static function(): void {
    idlm_mcp_switch_reset();
    update_option('idlm_mcp_enabled', '1');
    update_option('idlm_mcp_allow_pii', '1');
    IDLM_MCP_Core::execute_tool('query_leads', array('stage' => 'Won', 'limit' => 5, 'offset' => 10));
    $query = $GLOBALS['wpdb']->last_get_results_query;
    assert_contains('ORDER BY id DESC LIMIT 5 OFFSET 10', $query);
    assert_contains("stage = 'Won'", $query);
    $result = IDLM_MCP_Core::execute_tool('query_leads', array('stage' => array('Won')));
    assert_contains('stage', $result['error']);
    update_option('idlm_mcp_allow_pii', '0');
});

idlm_test('MCP summary uses two aggregate queries instead of nine and reuses a short cache', static function(): void {
    idlm_mcp_switch_reset();
    update_option('idlm_mcp_enabled', '1');
    $rows = array(
        array('stage' => 'New', 'source' => 'Website', 'lead_quality' => 'Warm', 'lead_count' => '3'),
        array('stage' => 'Won', 'source' => 'Website', 'lead_quality' => 'Genuine', 'lead_count' => '2'),
        array('stage' => 'Won', 'source' => 'Meta Ads', 'lead_quality' => 'Genuine', 'lead_count' => '1'),
    );
    $totals = array('total_leads' => '9', 'non_duplicate_leads' => '6', 'won_value' => '300000', 'avg_won_value' => '100000', 'avg_connected' => '7200', 'avg_won' => '172800');
    $driver = idlm_mcp_counting_driver(array('row' => $totals, 'results' => $rows));
    $original = $GLOBALS['wpdb'];
    $GLOBALS['wpdb'] = $driver;
    try {
        $summary = IDLM_MCP_Core::execute_tool('get_leads_summary', array());
        assert_same(2, count($driver->queries), 'Summary must cost one scalar and one grouped query.');
        assert_same(9, $summary['total_leads_in_db']);
        assert_same(6, $summary['non_duplicate_leads']);
        assert_same(300000.0, $summary['financial_metrics']['total_won_deal_value_inr']);
        assert_same(2.0, $summary['responsiveness_metrics']['avg_time_to_connected_hours']);
        assert_same(2.0, $summary['responsiveness_metrics']['avg_time_to_won_days']);
        $by_stage = array_column($summary['leads_by_stage'], 'count', 'stage');
        assert_same(array('New' => '3', 'Won' => '3'), $by_stage);
        $by_source = array_column($summary['leads_by_source'], 'count', 'source');
        assert_same(array('Website' => '5', 'Meta Ads' => '1'), $by_source);
        IDLM_MCP_Core::execute_tool('get_leads_summary', array());
        assert_same(2, count($driver->queries), 'A repeat call inside the cache window must not query again.');
        IDLM_MCP_Core::execute_tool('get_leads_summary', array('days_ago' => 30));
        assert_same(4, count($driver->queries), 'A different window is a different cache entry.');
        assert_contains('INTERVAL 30 DAY', $driver->queries[2]);
        IDLM_MCP_Core::execute_tool('get_leads_summary', array('days_ago' => -5));
        assert_same(4, count($driver->queries), 'A non-positive window means all time and shares the all-time entry.');
        IDLM_MCP_Core::execute_tool('get_leads_summary', array('days_ago' => 999999));
        assert_contains('INTERVAL 3650 DAY', end($driver->queries));
    } finally { $GLOBALS['wpdb'] = $original; $GLOBALS['idlm_test_transients'] = array(); }
});

idlm_test('MCP rep performance resolves each rep once and is cached', static function(): void {
    idlm_mcp_switch_reset();
    update_option('idlm_mcp_enabled', '1');
    $GLOBALS['idlm_test_transients'] = array();
    $rows = array(
        array('assigned_rep_id' => '0', 'total_assigned_leads' => '2', 'active_assigned_leads' => '1', 'qualified_assigned_leads' => '0', 'received_yesterday' => '0', 'won_leads' => '0', 'total_deal_value' => '0', 'avg_time_to_connected_sec' => null, 'avg_time_to_won_sec' => null),
        array('assigned_rep_id' => '77', 'total_assigned_leads' => '4', 'active_assigned_leads' => '2', 'qualified_assigned_leads' => '1', 'received_yesterday' => '1', 'won_leads' => '1', 'total_deal_value' => '50000', 'avg_time_to_connected_sec' => '3600', 'avg_time_to_won_sec' => '86400'),
    );
    $driver = idlm_mcp_counting_driver(array('results' => $rows));
    $original = $GLOBALS['wpdb'];
    $GLOBALS['wpdb'] = $driver;
    try {
        $reps = IDLM_MCP_Core::execute_tool('get_reps_performance', array());
        assert_same('Unassigned', $reps[0]['rep_name']);
        assert_same(25.0, $reps[1]['conversion_rate_percent']);
        assert_same(1.0, $reps[1]['avg_time_to_connected_hours']);
        IDLM_MCP_Core::execute_tool('get_reps_performance', array());
        assert_same(1, count($driver->queries));
    } finally { $GLOBALS['wpdb'] = $original; $GLOBALS['idlm_test_transients'] = array(); }
});

idlm_test('MCP statement time budget uses the right syntax for MySQL and MariaDB', static function(): void {
    idlm_mcp_switch_reset();
    $original = $GLOBALS['wpdb'];
    $tables = array('wp_idlm_leads', 'wp_users');
    $mysql = IDLM_MCP_Query_Policy::prepare('SELECT id FROM wp_idlm_leads', $tables);
    assert_contains('SELECT /*+ MAX_EXECUTION_TIME(10000) */ `id` FROM', $mysql['query']);
    $GLOBALS['wpdb'] = new class {
        public $prefix = 'wp_'; public $users = 'wp_users';
        public function db_server_info() { return '10.11.6-MariaDB-log'; }
    };
    try {
        $maria = IDLM_MCP_Query_Policy::prepare('SELECT id FROM wp_idlm_leads', $tables);
        assert_same(0, strpos($maria['query'], 'SET STATEMENT max_statement_time=10 FOR SELECT `id` FROM'), 'MariaDB ignores the MySQL hint, so it needs max_statement_time.');
        assert_false(strpos($maria['query'], 'MAX_EXECUTION_TIME') !== false);
    } finally { $GLOBALS['wpdb'] = $original; }
});

idlm_test('MCP request authentication skips the credential migration once a site is migrated', static function(): void {
    idlm_mcp_switch_reset();
    IDLM_Integration_Auth::maybe_migrate_api_credentials();
    assert_same(IDLM_Integration_Auth::API_CREDENTIALS_VERSION, get_option('idlm_api_credentials_version'));
    IDLM_Secret_Store::set('idlm_mcp_api_key', 'fast-path-key');
    // Make the migration observable: it deletes these legacy options when it runs.
    update_option('idlm_mcp_legacy_key_until', '2099-01-01');
    assert_true(IDLM_Integration_Auth::validate_scoped_api_key(new WP_REST_Request(array('X-IDLM-API-Key' => 'fast-path-key')), 'idlm_mcp_api_key'));
    assert_same('2099-01-01', get_option('idlm_mcp_legacy_key_until'), 'A migrated site must not re-run the migration per request.');
    assert_false(IDLM_Integration_Auth::validate_scoped_api_key(new WP_REST_Request(array('X-IDLM-API-Key' => 'wrong')), 'idlm_mcp_api_key'));
    update_option('idlm_api_credentials_version', '');
    IDLM_Integration_Auth::validate_scoped_api_key(new WP_REST_Request(array('X-IDLM-API-Key' => 'fast-path-key')), 'idlm_mcp_api_key');
    assert_same(null, get_option('idlm_mcp_legacy_key_until', null), 'An unmigrated site still migrates on first use.');
});

idlm_test('MCP HTTP maps the disabled protocol error to 503', static function(): void {
    $source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-integrations.php');
    assert_contains('$error_code === -32003', $source);
});
