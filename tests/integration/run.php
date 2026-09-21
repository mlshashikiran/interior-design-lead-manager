<?php
/**
 * Native WordPress/MySQL integration tests for high-risk plugin boundaries.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$tests = array();
$database = null;
$config = array();
$cleanup_complete = false;

final class IDLM_Integration_Ajax_Die extends RuntimeException {}
final class IDLM_Integration_WP_Die extends RuntimeException {}

function idlm_integration_test(string $name, callable $callback): void {
    global $tests;
    $tests[$name] = $callback;
}

function idlm_integration_assert_true($condition, string $message = 'Expected condition to be true.'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function idlm_integration_assert_same($expected, $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException(($message ? $message . ' ' : '') . 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
}

function idlm_integration_render(callable $callback): string {
    ob_start();
    try {
        $result = $callback();
        $output = (string) ob_get_clean();
        return is_string($result) ? $result : $output;
    } catch (Throwable $error) {
        ob_end_clean();
        throw $error;
    }
}

function idlm_integration_table_exists(string $table): bool {
    global $wpdb;
    return $table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
}

function idlm_integration_column(string $table, string $column) {
    global $wpdb;
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new RuntimeException('Unsafe table identifier in integration assertion.');
    }
    return $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column));
}

function idlm_integration_index_names(string $table): array {
    global $wpdb;
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new RuntimeException('Unsafe table identifier in integration assertion.');
    }
    return array_values(array_unique(array_map(static function($row): string {
        return (string) $row->Key_name;
    }, $wpdb->get_results("SHOW INDEX FROM `{$table}`"))));
}

function idlm_integration_show_create(string $table): string {
    global $wpdb;
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new RuntimeException('Unsafe table identifier in integration assertion.');
    }
    $row = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
    return isset($row[1]) ? (string) $row[1] : '';
}

function idlm_integration_request(string $method, string $route, array $headers = array(), array $params = array(), array $body = array()): WP_REST_Response {
    $request = new WP_REST_Request($method, $route);
    foreach ($headers as $name => $value) {
        $request->set_header($name, $value);
    }
    foreach ($params as $name => $value) {
        $request->set_param($name, $value);
    }
    if ($body) {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(wp_json_encode($body));
    }
    return rest_get_server()->dispatch($request);
}

function idlm_integration_worker(array $arguments): array {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/worker.php');
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg((string) $argument);
    }
    $pipes = array();
    $process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start integration worker.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    return array('status' => $status, 'stdout' => trim((string) $stdout), 'stderr' => trim((string) $stderr));
}

function idlm_integration_performance_command(array $arguments): array {
    $command = escapeshellarg(PHP_BINARY)
        . ' -d ' . escapeshellarg('auto_prepend_file=' . __DIR__ . '/performance-bootstrap.php')
        . ' ' . escapeshellarg(dirname(__DIR__) . '/performance/seed-dataset.php');
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg((string) $argument);
    }
    $pipes = array();
    $process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start performance dataset command.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return array(
        'status' => proc_close($process),
        'stdout' => trim((string) $stdout),
        'stderr' => trim((string) $stderr),
    );
}

function idlm_integration_query_plan_command(array $arguments): array {
    $command = escapeshellarg(PHP_BINARY)
        . ' -d ' . escapeshellarg('auto_prepend_file=' . __DIR__ . '/performance-bootstrap.php')
        . ' ' . escapeshellarg(dirname(__DIR__) . '/performance/capture-query-plans.php');
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg((string) $argument);
    }
    $pipes = array();
    $process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Unable to start query-plan capture command.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return array(
        'status' => proc_close($process),
        'stdout' => trim((string) $stdout),
        'stderr' => trim((string) $stderr),
    );
}

function idlm_integration_concurrent_workers(int $count, string $mode): array {
    $workers = array();
    for ($index = 0; $index < $count; $index++) {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/worker.php') . ' ' . escapeshellarg($mode);
        $pipes = array();
        $process = proc_open($command, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start concurrent integration worker.');
        }
        $workers[] = array('process' => $process, 'pipes' => $pipes);
    }

    $results = array();
    foreach ($workers as $worker) {
        $stdout = stream_get_contents($worker['pipes'][1]);
        $stderr = stream_get_contents($worker['pipes'][2]);
        fclose($worker['pipes'][1]);
        fclose($worker['pipes'][2]);
        $results[] = array(
            'status' => proc_close($worker['process']),
            'stdout' => trim((string) $stdout),
            'stderr' => trim((string) $stderr),
        );
    }
    return $results;
}

function idlm_integration_cleanup(): void {
    global $database, $config, $cleanup_complete;
    if ($cleanup_complete) {
        return;
    }
    if ($database instanceof IDLM_Integration_Database) {
        $database->drop();
    }
    if (!empty($config['content_dir'])) {
        idlm_integration_remove_directory((string) $config['content_dir']);
    }
    $cleanup_complete = true;
}

register_shutdown_function('idlm_integration_cleanup');

try {
    $config = idlm_integration_config(false);
    idlm_integration_export_config($config);
    $database = new IDLM_Integration_Database($config);
    $administrator_id = idlm_integration_boot_wordpress($config, true);
    idlm_integration_load_plugin($config);
    idlm_register_crm_roles_and_capabilities();

    idlm_integration_test('fresh schema installation creates required tables, columns, defaults, and indexes', static function(): void {
        global $wpdb;
        IDLM_DB::create_tables();
        IDLM_Routing_State::install();
        update_option('idlm_schema_version', IDLM_DB_VERSION, false);
        IDLM_Quotations::install();

        $required_tables = array(
            'idlm_leads', 'idlm_lead_activity_log', 'idlm_lead_state_events', 'idlm_capi_logs', 'idlm_audience_sync_logs',
            'idlm_telemetry_logs', 'idlm_tasks', 'idlm_call_logs', 'idlm_lead_touchpoints', 'idlm_inbound_submission_reviews',
            'idlm_quotes', 'idlm_quote_revisions', 'idlm_quote_revision_items',
            'idlm_quote_revision_sections', 'idlm_quote_catalogue_items', 'idlm_quote_item_categories',
            'idlm_quote_item_units', 'idlm_quote_payment_milestones', 'idlm_quote_status_history',
            'idlm_number_sequences', 'idlm_routing_state',
        );
        foreach ($required_tables as $suffix) {
            idlm_integration_assert_true(idlm_integration_table_exists($wpdb->prefix . $suffix), 'Missing table: ' . $suffix);
        }

        $handoff = idlm_integration_column($wpdb->prefix . 'idlm_leads', 'handoff_status');
        idlm_integration_assert_same('not_ready', $handoff->Default);
        idlm_integration_assert_true(null !== idlm_integration_column($wpdb->prefix . 'idlm_leads', 'assigned_at'));
        idlm_integration_assert_true(in_array('duplicate_of_id', idlm_integration_index_names($wpdb->prefix . 'idlm_leads'), true));
        idlm_integration_assert_true(in_array('lead_status_due', idlm_integration_index_names($wpdb->prefix . 'idlm_tasks'), true));
        idlm_integration_assert_true(in_array('quote_number', idlm_integration_index_names($wpdb->prefix . 'idlm_quotes'), true));
        idlm_integration_assert_true(in_array('sequence_year', idlm_integration_index_names($wpdb->prefix . 'idlm_number_sequences'), true));
        idlm_integration_assert_same(IDLM_Quotations::SCHEMA_VERSION, get_option('idlm_quotation_schema_version'));
    });

    idlm_integration_test('schema installers are idempotent and an old core version restores a missing column', static function(): void {
        global $wpdb;
        $tables = array(
            $wpdb->prefix . 'idlm_leads',
            $wpdb->prefix . 'idlm_quotes',
            $wpdb->prefix . 'idlm_quote_revisions',
            $wpdb->prefix . 'idlm_number_sequences',
            $wpdb->prefix . 'idlm_routing_state',
        );
        $before = array_map('idlm_integration_show_create', $tables);
        IDLM_DB::create_tables();
        IDLM_Routing_State::install();
        IDLM_Quotations::install();
        $after = array_map('idlm_integration_show_create', $tables);
        idlm_integration_assert_same($before, $after, 'Repeated installers changed the resulting schema.');

        $wpdb->query("ALTER TABLE `{$wpdb->prefix}idlm_leads` DROP COLUMN `assigned_at`");
        idlm_integration_assert_true(null === idlm_integration_column($wpdb->prefix . 'idlm_leads', 'assigned_at'));
        update_option('idlm_schema_version', '1.0.0', false);
        foreach (array(
            'idlm_handoff_state_version', 'idlm_duplicate_quality_version', 'idlm_workflow_integrity_version',
            'idlm_assignment_timestamp_version', 'idlm_attribution_touchpoint_backfill_version',
        ) as $option_name) {
            update_option($option_name, '1.0.0', false);
        }
        IDLM_DB::init();
        idlm_integration_assert_true(null !== idlm_integration_column($wpdb->prefix . 'idlm_leads', 'assigned_at'));
        idlm_integration_assert_same(IDLM_DB_VERSION, get_option('idlm_schema_version'));
    });

    idlm_integration_test('real REST dispatch enforces webhook and MCP permission callbacks', static function(): void {
        update_option('idlm_api_credentials_version', IDLM_Integrations::API_CREDENTIALS_VERSION, false);
        IDLM_Secret_Store::set('idlm_webhook_api_key', 'integration-webhook-key');
        IDLM_Secret_Store::set('idlm_mcp_api_key', 'integration-mcp-key');
        IDLM_Integrations::init();
        rest_get_server();
        do_action('rest_api_init');

        $denied = idlm_integration_request(
            'POST',
            '/interior-design-lead-manager/v1/webhook',
            array('X-IDLM-API-Key' => 'wrong'),
            array('platform' => 'meta'),
            array('is_test' => true)
        );
        idlm_integration_assert_same(401, $denied->get_status());

        $allowed = idlm_integration_request(
            'POST',
            '/interior-design-lead-manager/v1/webhook',
            array('X-IDLM-API-Key' => 'integration-webhook-key', 'X-Hook-Test' => 'true'),
            array('platform' => 'meta'),
            array('name' => 'Connection test')
        );
        idlm_integration_assert_same(200, $allowed->get_status());
        idlm_integration_assert_true(!empty($allowed->get_data()['success']));

        $mcp_denied = idlm_integration_request(
            'POST',
            '/interior-design-lead-manager/v1/mcp',
            array('X-IDLM-API-Key' => 'wrong'),
            array(),
            array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list')
        );
        idlm_integration_assert_same(401, $mcp_denied->get_status());
    });

    idlm_integration_test('authenticated WordPress AJAX dispatch enforces nonce, capability, and lazy log payload context', static function() use ($administrator_id): void {
        global $wpdb;
        if (!defined('DOING_AJAX')) { define('DOING_AJAX', true); }
        wp_set_current_user((int) $administrator_id);
        IDLM_Admin::init();
        $handler_filter = static function() {
            return static function() { throw new IDLM_Integration_Ajax_Die('ajax complete'); };
        };
        add_filter('wp_die_ajax_handler', $handler_filter);
        $_POST = array('action' => 'idlm_get_total_leads_count', 'nonce' => wp_create_nonce('idlm_admin_nonce'));
        $_REQUEST = $_POST;
        ob_start();
        try {
            do_action('wp_ajax_idlm_get_total_leads_count');
        } catch (IDLM_Integration_Ajax_Die $error) {
            // Expected termination path from wp_send_json_success().
        }
        $payload = json_decode((string) ob_get_clean(), true);
        remove_filter('wp_die_ajax_handler', $handler_filter);
        idlm_integration_assert_true(!empty($payload['success']));
        idlm_integration_assert_true(isset($payload['data']['count']));

        IDLM_DB::log_capi_event(0, 'meta', 'Lead', array('event_name' => 'Lead'), array('accepted' => true), 'success');
        $log_id = (int) $wpdb->insert_id;
        add_filter('wp_die_ajax_handler', $handler_filter);
        $_POST = array('action' => 'idlm_get_capi_log_payload', 'nonce' => wp_create_nonce('idlm_admin_nonce'), 'log_id' => $log_id);
        $_REQUEST = $_POST;
        ob_start();
        try {
            do_action('wp_ajax_idlm_get_capi_log_payload');
        } catch (IDLM_Integration_Ajax_Die $error) {
            // Expected termination path from wp_send_json_success().
        }
        $log_payload = json_decode((string) ob_get_clean(), true);
        remove_filter('wp_die_ajax_handler', $handler_filter);
        idlm_integration_assert_true(!empty($log_payload['success']));
        idlm_integration_assert_true(false !== strpos((string) $log_payload['data']['payload'], 'event_name'));
        idlm_integration_assert_true(false !== strpos((string) $log_payload['data']['response'], 'accepted'));
    });

    idlm_integration_test('admin and frontend renderers preserve critical selectors and access output', static function() use ($administrator_id): void {
        wp_set_current_user((int) $administrator_id);
        $_GET = array();
        $_POST = array();
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $renderers = array(
            'dashboard' => array(static fn() => IDLM_Admin::render_dashboard_page(), array('idlm-period-selector', 'chart-leads-over-time', 'sales-funnel-container')),
            'leads' => array(static fn() => IDLM_Admin::render_leads_list_page(), array('idlm-lead-filters', 'idlm-leads-table', 'idlm-lead-drawer')),
            'kanban' => array(static fn() => IDLM_Admin::render_kanban_page(), array('idlm-kanban-filter-search', 'idlm-kanban-board', 'idlm-kanban-deal-modal')),
            'capi logs' => array(static fn() => IDLM_Admin::render_capi_logs_page(), array('idlm-select-all-capi', 'idlm-retry-capi-btn')),
            'audience logs' => array(static fn() => IDLM_Admin::render_audience_sync_logs_page(), array('Audience sync log', 'idlm-audience-payload-dialog')),
            'settings' => array(static fn() => IDLM_Admin::render_settings_page(), array('idlm-settings-form', 'settings-routing', 'settings-danger-zone')),
            'account' => array(static fn() => IDLM_Admin::render_my_account_page(), array('idlm-account-first-name', 'idlm-account-password-confirm')),
            'dashboard shortcode' => array(static fn() => IDLM_Admin::render_dashboard_shortcode(), array('idlm-period-selector', 'chart-leads-over-time')),
            'leads shortcode' => array(static fn() => IDLM_Admin::render_leads_list_shortcode(), array('idlm-leads-table', 'idlm-lead-drawer')),
            'kanban shortcode' => array(static fn() => IDLM_Admin::render_kanban_shortcode(), array('idlm-kanban-board', 'idlm-kanban-lost-modal')),
            'workspace shortcode' => array(static fn() => IDLM_Admin::render_workspace_shortcode(), array('idlm-workspace-nav', 'idlm-workspace-content', 'idlm-period-selector')),
        );
        foreach ($renderers as $name => $contract) {
            $html = idlm_integration_render($contract[0]);
            foreach ($contract[1] as $selector) {
                idlm_integration_assert_true(false !== strpos($html, $selector), 'Missing ' . $name . ' selector: ' . $selector);
            }
        }

        wp_set_current_user(0);
        $restricted = idlm_integration_render(static fn() => IDLM_Admin::render_dashboard_page());
        idlm_integration_assert_true(false !== strpos($restricted, 'idlm-access-restricted'));
    });

    idlm_integration_test('settings save enforces capability and nonce before sanitized mirrored secret-safe writes', static function() use ($administrator_id): void {
        update_option('idlm_currency_code', 'KEEP', false);
        wp_set_current_user((int) $administrator_id);
        $valid_nonce = wp_create_nonce('idlm_settings_save_action');
        $die_filter = static function() {
            return static function($message): void {
                throw new IDLM_Integration_WP_Die(wp_strip_all_tags((string) $message));
            };
        };
        add_filter('wp_die_handler', $die_filter);
        add_filter('wp_die_ajax_handler', $die_filter);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array('idlm_save_settings' => '1', '_wpnonce' => 'invalid');
        $_REQUEST = $_POST;
        try {
            idlm_integration_render(static fn() => IDLM_Admin::render_settings_page());
            throw new RuntimeException('Invalid settings nonce did not stop processing.');
        } catch (IDLM_Integration_WP_Die $error) {
            idlm_integration_assert_same('KEEP', get_option('idlm_currency_code'));
        }

        wp_set_current_user(0);
        $_POST = array('idlm_save_settings' => '1', '_wpnonce' => $valid_nonce, 'currency_code' => 'USD');
        $_REQUEST = $_POST;
        try {
            idlm_integration_render(static fn() => IDLM_Admin::render_settings_page());
            throw new RuntimeException('Unauthorized settings save did not stop processing.');
        } catch (IDLM_Integration_WP_Die $error) {
            idlm_integration_assert_same('KEEP', get_option('idlm_currency_code'));
        }

        wp_set_current_user((int) $administrator_id);
        $valid_nonce = wp_create_nonce('idlm_settings_save_action');
        $_POST = array(
            'idlm_save_settings' => '1',
            '_wpnonce' => $valid_nonce,
            'lead_target' => '125<script>',
            'conversion_target' => '17',
            'currency_code' => '<b>usd</b>',
            'reps_see_all' => '1',
            'presales_calls_per_day' => '99',
            'presales_whatsapp_per_day' => '-5',
            'assignment_mode' => 'invented<script>',
            'fallback_assignment_hours' => '0',
            'qualified_fallback_hours' => '9999',
            'studio_mismatch_behavior' => 'invalid',
            'handoff_task_policy' => 'invalid',
            'max_active_leads_per_rep' => '-10',
            'working_hours_start' => '<b>08:30</b>',
            'working_hours_end' => '17:30',
            'working_days' => array('1', '3', '5'),
            'custom_stages' => 'New,<b>Qualified</b>,Won',
            'custom_stages_values' => array('Won', 'New', '<b>Qualified</b>', 'New'),
            'kanban_stage_totals' => array('Qualified' => 'quote_value', 'Won' => 'deal_value'),
            'custom_call_statuses' => 'Not Called,<i>Connected</i>',
            'custom_lost_reasons' => 'Budget,Other,Budget',
            'custom_studios' => 'North,South',
            'custom_num_rooms' => '2 BHK,3 BHK',
            'white_label' => array(
                'brand_name' => 'Integration Studio',
                'workspace_title' => 'Integration CRM',
                'login_heading' => 'Manage every customer conversation.',
                'primary_color' => '#264653',
                'accent_color' => '#E9C46A',
                'sidebar_color' => '#17212B',
            ),
            'google_gadm_password' => '<b>native-secret</b>',
        );
        $_REQUEST = $_POST;
        $html = idlm_integration_render(static fn() => IDLM_Admin::render_settings_page());
        remove_filter('wp_die_handler', $die_filter);
        remove_filter('wp_die_ajax_handler', $die_filter);

        idlm_integration_assert_true(false !== strpos($html, 'Settings saved successfully!'));
        idlm_integration_assert_same(125, get_option('idlm_monthly_lead_target'));
        idlm_integration_assert_same('USD', get_option('idlm_currency_code'));
        idlm_integration_assert_same(20, get_option('idlm_presales_calls_per_day'));
        idlm_integration_assert_same(0, get_option('idlm_presales_whatsapp_per_day'));
        idlm_integration_assert_same('manual', get_option('idlm_assignment_mode'));
        idlm_integration_assert_same(1, get_option('idlm_fallback_assignment_hours'));
        idlm_integration_assert_same(720, get_option('idlm_qualified_fallback_hours'));
        idlm_integration_assert_same('general_pool', get_option('idlm_studio_mismatch_behavior'));
        idlm_integration_assert_same('transfer', get_option('idlm_handoff_task_policy'));
        idlm_integration_assert_same(array(1, 3, 5), get_option('idlm_working_days'));
        idlm_integration_assert_same(array('Won', 'New', 'Qualified'), get_option('idlm_custom_stages'));
        idlm_integration_assert_same(array('Budget', 'Other'), get_option('idlm_custom_lost_reasons'));
        $routing = get_option(IDLM_Settings::ROUTING_OPTION, array());
        idlm_integration_assert_same('manual', $routing['idlm_assignment_mode']);
        idlm_integration_assert_same(array(1, 3, 5), $routing['idlm_working_days']);
        idlm_integration_assert_same('native-secret', IDLM_Secret_Store::get('idlm_google_gadm_password'));
        $stored_secret = (string) get_option('idlm_google_gadm_password');
        idlm_integration_assert_true(0 === strpos($stored_secret, IDLM_Secret_Store::PREFIX));
        idlm_integration_assert_true(false === strpos($stored_secret, 'native-secret'));

        $_GET = array();
        $_POST = array();
        $_REQUEST = array();
        $_SERVER['REQUEST_METHOD'] = 'GET';
    });

    idlm_integration_test('lead lifecycle writes persist coupled state, tasks, activity, and hooks', static function(): void {
        global $wpdb;
        $pre_sales_id = wp_insert_user(array(
            'user_login' => 'idlm_pre_sales_test',
            'user_pass' => 'idlm-test-password',
            'user_email' => 'pre-sales@example.test',
            'role' => 'idlm_pre_sales',
        ));
        if (is_wp_error($pre_sales_id)) {
            throw new RuntimeException($pre_sales_id->get_error_message());
        }
        wp_set_current_user((int) $pre_sales_id);

        $events = array();
        $listener = static function($lead_id, $stage) use (&$events): void {
            $events[] = array((int) $lead_id, (string) $stage);
        };
        add_action('idlm_lead_stage_converted', $listener, 10, 2);

        $lead_id = IDLM_DB::insert_lead(array(
            'first_name' => 'Asha',
            'last_name' => 'Rao',
            'email' => 'asha.integration@example.test',
            'phone' => '9876543210',
            'project_name' => 'Rao Residence',
            'property_location' => 'Bengaluru',
            'budget' => '8 lakh',
            'possession_date' => '2026-12-01',
            'property_type' => 'Apartment',
            'project_timeline' => '3 months',
            'floor_plan_received' => 1,
            'assigned_rep_id' => (int) $pre_sales_id,
        ));
        idlm_integration_assert_true((int) $lead_id > 0, IDLM_DB::get_last_validation_error());
        $wpdb->insert($wpdb->prefix . 'idlm_tasks', array(
            'lead_id' => $lead_id,
            'assigned_rep_id' => (int) $pre_sales_id,
            'title' => 'Qualification cycle',
            'task_type' => 'Qualification',
            'priority' => 'High',
            'status' => 'pending',
            'due_date' => current_time('mysql'),
            'created_by' => (int) $pre_sales_id,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ));

        idlm_integration_assert_true(IDLM_DB::update_lead((int) $lead_id, array('lead_quality' => 'Qualified')), IDLM_DB::get_last_validation_error());
        $lead = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
        idlm_integration_assert_same('Qualified', $lead->stage);
        idlm_integration_assert_same('Qualified', $lead->lead_quality);
        idlm_integration_assert_same('awaiting_manager', $lead->handoff_status);
        idlm_integration_assert_same((int) $pre_sales_id, (int) $lead->intake_owner_id);
        idlm_integration_assert_same('completed', $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}idlm_tasks WHERE lead_id = %d AND task_type = 'Qualification'", $lead_id)));
        $activity_types = $wpdb->get_col($wpdb->prepare("SELECT activity_type FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id = %d", $lead_id));
        idlm_integration_assert_true(in_array('creation', $activity_types, true));
        idlm_integration_assert_true(in_array('update', $activity_types, true));
        idlm_integration_assert_true(in_array('stage_change', $activity_types, true));
        $reporting_events = $wpdb->get_results($wpdb->prepare("SELECT event_type, from_value, to_value FROM {$wpdb->prefix}idlm_lead_state_events WHERE lead_id = %d ORDER BY id ASC", $lead_id));
        idlm_integration_assert_same(3, count($reporting_events));
        idlm_integration_assert_same('stage_change', $reporting_events[2]->event_type);
        idlm_integration_assert_same('New', $reporting_events[2]->from_value);
        idlm_integration_assert_same('Qualified', $reporting_events[2]->to_value);
        idlm_integration_assert_same('New', $events[0][1]);
        idlm_integration_assert_same('Qualified', $events[1][1]);
        remove_action('idlm_lead_stage_converted', $listener, 10);

        $GLOBALS['idlm_integration_lead_id'] = (int) $lead_id;
    });

    idlm_integration_test('MCP PII access succeeds only with the scoped key after real lead persistence', static function(): void {
        $lead_id = (int) ($GLOBALS['idlm_integration_lead_id'] ?? 0);
        $request_body = array(
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => array('name' => 'get_lead_details', 'arguments' => array('id' => $lead_id)),
        );
        $allowed = idlm_integration_request(
            'POST',
            '/interior-design-lead-manager/v1/mcp',
            array('X-IDLM-API-Key' => 'integration-mcp-key'),
            array(),
            $request_body
        );
        idlm_integration_assert_same(200, $allowed->get_status());
        $text = (string) $allowed->get_data()['result']['content'][0]['text'];
        idlm_integration_assert_true(false !== strpos($text, 'asha.integration@example.test'));
    });

    idlm_integration_test('quotation repository persists and queries catalogue read models', static function() use ($administrator_id): void {
        $now = current_time('mysql');
        idlm_integration_assert_same(1, IDLM_Quotation_Repository::insert_catalogue_term('category', 'Living Room', $administrator_id, $now));
        idlm_integration_assert_same(1, IDLM_Quotation_Repository::insert_catalogue_term('category', 'Unused Category', $administrator_id, $now));
        idlm_integration_assert_same(1, IDLM_Quotation_Repository::insert_catalogue_term('unit', 'Sq Ft', $administrator_id, $now));
        idlm_integration_assert_true(IDLM_Quotation_Repository::catalogue_term_exists('category', 'Living Room'));
        idlm_integration_assert_true(IDLM_Quotation_Repository::catalogue_term_exists('unit', 'Sq Ft'));

        idlm_integration_assert_same(1, IDLM_Quotation_Repository::insert_catalogue_item(array(
            'item_code' => 'INT-LR-001',
            'category' => 'Living Room',
            'item_name' => 'Integration TV Unit',
            'description' => 'Native catalogue persistence test',
            'unit' => 'Sq Ft',
            'is_wood' => 1,
            'default_rate' => 1200,
            'status' => 'active',
            'created_by' => $administrator_id,
            'created_at' => $now,
            'updated_at' => $now,
        )));
        $item = IDLM_Quotation_Repository::search_catalogue('Integration TV', 1, 20)[0] ?? null;
        idlm_integration_assert_true((bool) $item, 'Catalogue search did not return the inserted item.');
        idlm_integration_assert_same('INT-LR-001', $item->item_code);
        idlm_integration_assert_same(1, IDLM_Quotation_Repository::count_catalogue_items('Integration TV'));
        idlm_integration_assert_same(1, count(IDLM_Quotation_Repository::list_catalogue_items('Integration TV', 1, 100)));
        idlm_integration_assert_same(1, count(IDLM_Quotation_Repository::get_catalogue_export_chunk(0, 500)));

        $categories = IDLM_Quotation_Repository::list_catalogue_categories();
        $category_ids = array();
        foreach ($categories as $category) {
            $category_ids[$category->category_name] = (int) $category->id;
        }
        $category_result = IDLM_Quotation_Repository::apply_catalogue_category_action(
            array($category_ids['Living Room'], $category_ids['Unused Category']),
            'delete',
            $now
        );
        idlm_integration_assert_same(array('affected' => 1, 'blocked' => 1), $category_result);

        idlm_integration_assert_same(1, IDLM_Quotation_Repository::archive_catalogue_item((int) $item->id, $now));
        idlm_integration_assert_same('inactive', IDLM_Quotation_Repository::get_catalogue_item_status((int) $item->id));
        idlm_integration_assert_same(1, IDLM_Quotation_Repository::bulk_catalogue_items(array((int) $item->id), 'delete', $now));
        $category_result = IDLM_Quotation_Repository::apply_catalogue_category_action(array($category_ids['Living Room']), 'delete', $now);
        idlm_integration_assert_same(array('affected' => 1, 'blocked' => 0), $category_result);
    });

    idlm_integration_test('frontend quotation workspace list route renders with date filters', static function() use ($administrator_id): void {
        wp_set_current_user((int) $administrator_id);
        $_GET = array(
            'idlm_view' => 'quotations',
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-29',
        );
        $html = IDLM_Quotations::render_shortcode();
        idlm_integration_assert_true(false !== strpos($html, '<h1>Quotations</h1>'));
        idlm_integration_assert_true(false !== strpos($html, 'name="date_from" value="2026-08-01"'));
        idlm_integration_assert_true(false !== strpos($html, 'name="date_to" value="2026-08-29"'));
        $_GET = array();
    });

    idlm_integration_test('quotation number reservation is monotonic under concurrent database workers', static function(): void {
        global $wpdb;
        $method = new ReflectionMethod('IDLM_Quotations', 'next_quote_number');
        $method->setAccessible(true);
        $first = $method->invoke(null);
        idlm_integration_assert_true(!is_wp_error($first));

        $results = idlm_integration_concurrent_workers(4, 'quote-number');
        $numbers = array((string) $first);
        foreach ($results as $result) {
            idlm_integration_assert_same(0, $result['status'], $result['stderr']);
            $decoded = json_decode($result['stdout'], true);
            idlm_integration_assert_true(is_array($decoded) && !empty($decoded['quote_number']), 'Invalid quote-number worker output: ' . $result['stdout']);
            $numbers[] = (string) $decoded['quote_number'];
        }
        idlm_integration_assert_same(5, count(array_unique($numbers)));
        idlm_integration_assert_same(5, (int) $wpdb->get_var("SELECT last_number FROM {$wpdb->prefix}idlm_number_sequences WHERE sequence_type = 'quotation'"));
    });

    idlm_integration_test('quotation save persists exact totals, revision graph, and uniqueness constraints', static function() use ($administrator_id): void {
        global $wpdb;
        $lead_id = (int) ($GLOBALS['idlm_integration_lead_id'] ?? 0);
        $result = idlm_integration_worker(array('quote-save', $lead_id, $administrator_id));
        idlm_integration_assert_same(0, $result['status'], $result['stderr']);

        $quote = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}idlm_quotes ORDER BY id DESC LIMIT 1");
        idlm_integration_assert_true((bool) $quote, 'Quotation worker did not persist a quote. ' . $result['stderr']);
        $revision = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_revisions WHERE quote_id = %d", $quote->id));
        idlm_integration_assert_same('Draft', $quote->status);
        idlm_integration_assert_same((int) $revision->id, (int) $quote->current_revision_id);
        idlm_integration_assert_same('2000.00', (string) $revision->subtotal);
        idlm_integration_assert_same(360.0, (float) $revision->cgst_total + (float) $revision->sgst_total);
        idlm_integration_assert_same('2360.00', (string) $revision->grand_total);
        idlm_integration_assert_same(1, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_quote_revision_sections WHERE revision_id = %d", $revision->id)));
        idlm_integration_assert_same(1, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_quote_revision_items WHERE revision_id = %d", $revision->id)));
        idlm_integration_assert_same(1, (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_quote_status_history WHERE quote_id = %d", $quote->id)));
        idlm_integration_assert_same('2360.00', (string) $wpdb->get_var($wpdb->prepare("SELECT quote_value FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id)));
        idlm_integration_assert_same(1, IDLM_Quotation_Repository::count_quotations(array()));
        $quotation_rows = IDLM_Quotation_Repository::list_quotations(array(), 1, 50);
        idlm_integration_assert_same(1, count($quotation_rows));
        idlm_integration_assert_same((int) $quote->id, (int) $quotation_rows[0]->id);

        $print_html = IDLM_Quotation_Renderer::render_print_document(IDLM_Quotation_Repository::get_quote_bundle($quote->id));
        idlm_integration_assert_true(false !== strpos($print_html, 'idlm-print-document-head'));
        idlm_integration_assert_true(false !== strpos($print_html, 'Section-wise subtotal before discount'));
        idlm_integration_assert_true(false === strpos($print_html, '<script'));
        idlm_integration_assert_true(false === strpos($print_html, 'document.querySelector'));
        $print_dom = new DOMDocument();
        @$print_dom->loadHTML($print_html);
        idlm_integration_assert_same(1, $print_dom->getElementsByTagName('main')->length);
        idlm_integration_assert_true($print_dom->getElementsByTagName('table')->length >= 2);

        $wpdb->suppress_errors(true);
        $duplicate = $wpdb->insert($wpdb->prefix . 'idlm_quotes', array(
            'lead_id' => $lead_id,
            'quote_number' => $quote->quote_number,
            'title' => 'Duplicate number',
            'status' => 'Draft',
            'created_by' => $administrator_id,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ));
        $wpdb->suppress_errors(false);
        idlm_integration_assert_same(false, $duplicate);
        idlm_integration_assert_true('' !== $wpdb->last_error);
    });

    idlm_integration_test('performance dataset command seeds, verifies, and resets only its disposable tagged run', static function(): void {
        global $wpdb;
        $unmarked_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE source <> 'Performance Seed' OR source IS NULL");
        $seed = idlm_integration_performance_command(array(
            '--profile=small',
            '--seed=integration-01',
            '--batch-size=500',
            '--execute',
            '--confirm=seed-idlm-performance-data',
        ));
        idlm_integration_assert_same(0, $seed['status'], $seed['stderr']);
        $manifest = json_decode($seed['stdout'], true);
        idlm_integration_assert_true(is_array($manifest), 'Invalid seed manifest: ' . $seed['stdout']);
        idlm_integration_assert_same('small-integration-01', $manifest['run_id']);
        idlm_integration_assert_same(1000, $manifest['counts']['leads']);
        idlm_integration_assert_same(8000, $manifest['counts']['activities']);
        idlm_integration_assert_same(4000, $manifest['counts']['logs']);
        idlm_integration_assert_same(500, $manifest['counts']['quote_items']);
        idlm_integration_assert_same(1000, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE source = 'Performance Seed'"));

        $reset = idlm_integration_performance_command(array(
            '--reset=small-integration-01',
            '--batch-size=500',
            '--confirm=reset-idlm-performance-data',
        ));
        idlm_integration_assert_same(0, $reset['status'], $reset['stderr']);
        $reset_manifest = json_decode($reset['stdout'], true);
        idlm_integration_assert_true(is_array($reset_manifest), 'Invalid reset manifest: ' . $reset['stdout']);
        idlm_integration_assert_same(0, $reset_manifest['remaining_marked_leads']);
        idlm_integration_assert_same(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE source = 'Performance Seed'"));
        idlm_integration_assert_same(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_telemetry_logs WHERE log_type = 'idlm_performance_seed'"));
        idlm_integration_assert_same($unmarked_before, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE source <> 'Performance Seed' OR source IS NULL"));
    });

    idlm_integration_test('query-plan capture returns optimizer and index evidence without raw SQL', static function(): void {
        global $config;
        $capture = idlm_integration_query_plan_command(array(
            '--revision=integration-worktree',
            '--dataset=integration-disposable',
            '--start=2026-01-01',
            '--end=2026-12-31',
            '--rep-id=1',
            '--lead-id=1',
        ));
        idlm_integration_assert_same(0, $capture['status'], $capture['stderr']);
        $result = json_decode($capture['stdout'], true);
        idlm_integration_assert_true(is_array($result), 'Invalid query-plan JSON: ' . $capture['stdout']);
        idlm_integration_assert_same('idlm-query-plan-capture/v1', $result['schema']);
        idlm_integration_assert_same(10, $result['summary']['scenario_count']);
        idlm_integration_assert_same(false, $result['privacy']['raw_sql_included']);
        idlm_integration_assert_same(false, $result['privacy']['parameter_values_included']);
        idlm_integration_assert_true(false === strpos($capture['stdout'], (string) $config['db_name']));
        idlm_integration_assert_true(!empty($result['plans']));
        idlm_integration_assert_true(!empty($result['indexes']));
        foreach ($result['plans'] as $plan) {
            idlm_integration_assert_same('captured', $plan['status']);
            idlm_integration_assert_true(!array_key_exists('sql', $plan));
        }
    });

    idlm_integration_test('dashboard analytics and investigations share role scope and real SQL counts', static function() use ($administrator_id): void {
        global $wpdb;
        $rep = wp_insert_user(array('user_login' => 'dashboard_rep_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'author'));
        $other = wp_insert_user(array('user_login' => 'dashboard_other_' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'author'));
        update_option('timezone_string', 'Asia/Kolkata');
        $created = (new DateTime('now', wp_timezone()))->modify('-45 days')->format('Y-m-d H:i:s');
        $older = (new DateTime('now', wp_timezone()))->modify('-65 days')->format('Y-m-d H:i:s');
        $ids = array();
        foreach (array(array($rep, $created, 'New', 'Qualified'), array($rep, $older, 'Retired stage', ''), array($other, $created, 'New', 'Qualified')) as $fixture) {
            // Isolated reporting fixtures deliberately bypass workflow side effects in this disposable database.
            $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Dashboard fixture', 'assigned_rep_id' => $fixture[0], 'created_at' => $fixture[1], 'updated_at' => $fixture[1], 'stage' => $fixture[2], 'lead_quality' => $fixture[3], 'source' => 'Dashboard source', 'utm_campaign' => 'Dashboard campaign', 'is_duplicate' => 0));
            $ids[] = (int) $wpdb->insert_id;
        }
        $wpdb->insert($wpdb->prefix . 'idlm_tasks', array('lead_id' => $ids[0], 'assigned_rep_id' => $rep, 'created_by' => $rep, 'title' => 'Dashboard pending follow-up', 'status' => 'pending', 'due_date' => $created, 'created_at' => $created));
        idlm_integration_assert_same('', $wpdb->last_error);
        update_option('idlm_reps_see_all_leads', '0');
        wp_set_current_user($rep);
        $data = IDLM_Dashboard::calculate_stats('this_year');
        idlm_integration_assert_same('', $wpdb->last_error);
        idlm_integration_assert_true(!isset($data['marketing']));
        idlm_integration_assert_true(!isset($data['sales']['leads_by_rep']));
        idlm_integration_assert_same(2, $data['sales']['open_pipeline_total']);
        idlm_integration_assert_same(1, $data['sales']['open_pipeline_health']['aged_31_to_60']);
        idlm_integration_assert_same(1, $data['sales']['open_pipeline_health']['aged_61_plus']);
        idlm_integration_assert_same(1, $data['sales']['open_pipeline_health']['overdue_task']);
        idlm_integration_assert_same(1, $data['sales']['open_pipeline_health']['without_next_task']);
        $records = IDLM_Dashboard::get_records('overdue', '', 'today');
        idlm_integration_assert_same(array($ids[0]), array_map('intval', wp_list_pluck($records['records'], 'id')));
        $records = IDLM_Dashboard::get_records('stage', 'Retired stage', 'today');
        idlm_integration_assert_same(array($ids[1]), array_map('intval', wp_list_pluck($records['records'], 'id')));
        idlm_integration_assert_true(is_wp_error(IDLM_Dashboard::get_records('channel', 'Dashboard source', 'this_year')));
        add_role('marketing', 'Marketing', array('read' => true, 'idlm_access_leads' => true));
        $user = new WP_User($rep); $user->set_role('marketing'); wp_set_current_user(0); wp_set_current_user($rep);
        $marketing = IDLM_Dashboard::calculate_stats('this_year');
        idlm_integration_assert_true(!isset($marketing['sales']));
        idlm_integration_assert_same(2, $marketing['marketing']['total_leads_period']);
        idlm_integration_assert_same(1, $marketing['marketing']['leads_by_channel'][0]->qualified);
        $records = IDLM_Dashboard::get_records('channel', 'Dashboard source', 'this_year');
        idlm_integration_assert_same(2, count($records['records']));
        idlm_integration_assert_true(!in_array($ids[2], array_map('intval', wp_list_pluck($records['records'], 'id')), true));
        idlm_integration_assert_true(is_wp_error(IDLM_Dashboard::get_records('overdue', '', 'today')));
        wp_set_current_user($administrator_id);
    });

    require_once __DIR__ . '/high-priority-contracts.php';

    $passed = 0;
    $failed = 0;
    foreach ($tests as $name => $test) {
        try {
            $test();
            $passed++;
            echo "PASS {$name}\n";
        } catch (Throwable $error) {
            $failed++;
            echo "FAIL {$name}\n  {$error->getMessage()}\n";
        }
    }
    echo "\n{$passed} passed, {$failed} failed\n";
    idlm_integration_cleanup();
    exit($failed ? 1 : 0);
} catch (Throwable $error) {
    fwrite(STDERR, 'Integration bootstrap failed: ' . $error->getMessage() . "\n");
    idlm_integration_cleanup();
    exit(1);
}
