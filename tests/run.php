<?php
/**
 * IDLM production-readiness behavioral test suite.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/performance/class-dataset-generator.php';
require_once __DIR__ . '/performance/class-qualification-result.php';

$tests = array();

function idlm_test(string $name, callable $callback): void {
    global $tests;
    $tests[$name] = $callback;
}

function assert_true($condition, string $message = 'Expected condition to be true.'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assert_false($condition, string $message = 'Expected condition to be false.'): void {
    if ($condition) {
        throw new RuntimeException($message);
    }
}

function assert_same($expected, $actual, string $message = ''): void {
    if ($expected !== $actual) {
        throw new RuntimeException(($message ? $message . ' ' : '') . 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . '.');
    }
}

function assert_contains(string $needle, string $haystack, string $message = ''): void {
    if (false === strpos($haystack, $needle)) {
        throw new RuntimeException(($message ? $message . ' ' : '') . 'Did not find ' . var_export($needle, true) . '.');
    }
}

function assert_count_value(int $expected, array $actual, string $message = ''): void {
    assert_same($expected, count($actual), $message);
}

function expect_json_response(callable $callback, bool $success, ?int $status = null): IDLM_Test_Response {
    try {
        $callback();
    } catch (IDLM_Test_Response $response) {
        assert_same($success, $response->success, 'Unexpected JSON response type.');
        if (null !== $status) {
            assert_same($status, $response->status, 'Unexpected response status.');
        }
        return $response;
    }
    throw new RuntimeException('Expected the handler to terminate with a JSON response.');
}

function invoke_private(string $class, string $method, array $args = array()) {
    $reflection = new ReflectionMethod($class, $method);
    $reflection->setAccessible(true);
    return $reflection->invokeArgs(null, $args);
}

function lead_fixture(array $overrides = array()): array {
    return array_merge(array(
        'id' => 1,
        'first_name' => 'Asha',
        'last_name' => 'Rao',
        'email' => 'asha@example.test',
        'phone' => '+91 98765 43210',
        'project_name' => 'Rao Residence',
        'budget' => '8 lakh',
        'deal_value' => 0,
        'discount_percentage' => 0,
        'project_timeline' => '3 months',
        'possession_date' => '2026-12-01',
        'property_location' => 'Bengaluru',
        'property_type' => 'Apartment',
        'floor_plan_received' => 1,
        'quote_shared' => 0,
        'quote_value' => 0,
        'stage' => 'New',
        'lost_reason' => '',
        'call_status' => 'Not Called',
        'callback_datetime' => null,
        'lead_quality' => '',
        'disqualification_reason' => '',
        'disqualification_method' => '',
        'assigned_rep_id' => 0,
        'assigned_at' => null,
        'qualified_at' => null,
        'qualified_by_user_id' => 0,
        'intake_owner_id' => 0,
        'handoff_status' => 'not_ready',
        'handoff_at' => null,
        'handoff_method' => '',
        'handoff_exception_reason' => '',
        'is_duplicate' => 0,
        'duplicate_of_id' => 0,
        'created_at' => '2026-08-20 09:00:00',
        'updated_at' => '2026-08-20 09:00:00',
        'time_to_connected' => null,
        'time_to_won' => null,
    ), $overrides);
}

function action_names(): array {
    return array_column($GLOBALS['idlm_test_actions'], 'hook');
}

idlm_test('backup restore accepts generated backups smaller than 100 MB', static function(): void {
    $root = dirname(__DIR__) . '/';
    $backup = file_get_contents($root . 'includes/class-backup.php');
    $template = file_get_contents($root . 'templates/admin/settings/backup.php');

    assert_contains('const MAX_FILE_SIZE = 104857600; // 100 MB.', $backup);
    assert_contains('$size > self::MAX_FILE_SIZE', $backup);
    assert_contains('IDLM_Data_Retention::prune();', $backup);
    assert_contains('smaller than 100 MB.', $template);
});

idlm_test('heavy CRM log reads are bounded and audience payloads are lazy loaded', static function(): void {
    $root = dirname(__DIR__) . '/';
    $lead_reads = file_get_contents($root . 'includes/class-admin-lead-reads.php');
    $ajax_reads = file_get_contents($root . 'includes/class-admin-ajax.php');
    $log_renderer = file_get_contents($root . 'includes/class-admin-logs-renderer.php');
    $log_template = file_get_contents($root . 'templates/admin/logs/audience.php');
    $log_script = file_get_contents($root . 'assets/js/users-logs.js');
    $admin = file_get_contents($root . 'includes/class-admin.php');

    assert_contains('ORDER BY a.id DESC LIMIT 100', $lead_reads);
    assert_contains('ORDER BY a.id DESC LIMIT 100', $ajax_reads);
    assert_contains('SELECT id, platform, audience_id, lead_count, status, created_at', $log_renderer);
    assert_false(false !== strpos($log_renderer, 'SELECT *\n            FROM {$wpdb->prefix}idlm_audience_sync_logs'));
    assert_contains("check_ajax_referer('idlm_admin_nonce', 'nonce')", $log_renderer);
    assert_contains("current_user_can('manage_options')", $log_renderer);
    assert_contains("add_action('wp_ajax_idlm_get_audience_log_payload'", $admin);
    assert_contains("'idlm_get_audience_log_payload'", $log_script);
    assert_false(false !== strpos($log_template, 'maybe_unserialize($log->payload)'));
});

idlm_test('signed cursor pagination binds position to actor and normalized request scope', static function(): void {
    idlm_test_login(7, array('sales_manager'), array('idlm_access_leads'));
    $scope = array(
        'filters' => array('stage' => 'New', 'search' => ''),
        'limit' => 25,
        'sort' => 'assigned_or_created_desc_id_desc',
    );
    $position = array('sort_value' => '2026-08-30 12:00:00', 'id' => 91);
    $token = IDLM_Cursor_Pagination::encode('leads', $position, $scope);

    assert_same($position, IDLM_Cursor_Pagination::decode($token, 'leads', array(
        'sort' => 'assigned_or_created_desc_id_desc',
        'limit' => 25,
        'filters' => array('search' => '', 'stage' => 'New'),
    )));
    assert_true(is_wp_error(IDLM_Cursor_Pagination::decode($token . 'x', 'leads', $scope)));
    assert_true(is_wp_error(IDLM_Cursor_Pagination::decode($token, 'leads', array_merge($scope, array('limit' => 50)))));

    idlm_test_login(8, array('sales_manager'), array('idlm_access_leads'));
    assert_true(is_wp_error(IDLM_Cursor_Pagination::decode($token, 'leads', $scope)));
});

idlm_test('lead table keeps offset compatibility and adds bounded default keyset navigation', static function(): void {
    $ajax_source = file_get_contents(dirname(__DIR__) . '/includes/class-admin-ajax.php');
    $client_source = file_get_contents(dirname(__DIR__) . '/assets/js/admin-app.js');

    assert_contains("isset(\$_POST['page'])", $ajax_source);
    assert_contains("'pagination_mode' => \$use_cursor_query ? 'cursor' : 'offset'", $ajax_source);
    assert_contains('COALESCE(l.assigned_at, l.created_at) < %s', $ajax_source);
    assert_contains("\$pagination_limit_sql = \$use_cursor_query ? 'LIMIT %d' : 'LIMIT %d OFFSET %d'", $ajax_source);
    assert_contains('IDLM_Cursor_Pagination::decode', $ajax_source);
    assert_contains('leadsPageCursors[currentPage + 1] = response.data.next_cursor', $client_source);
    assert_contains('requestData.cursor = leadsPageCursors[page]', $client_source);
});

idlm_test('read-model cache reuses scoped results and generation invalidation forces a rebuild', static function(): void {
    $builds = 0;
    $builder = static function() use (&$builds): array {
        $builds++;
        return array('build' => $builds);
    };

    $first = IDLM_Read_Model_Cache::remember('test_dashboard', array('scope' => 'global'), $builder);
    $second = IDLM_Read_Model_Cache::remember('test_dashboard', array('scope' => 'global'), $builder);
    assert_same(array('build' => 1), $first);
    assert_same($first, $second);
    assert_same(1, $builds);

    IDLM_Read_Model_Cache::invalidate('test');
    $third = IDLM_Read_Model_Cache::remember('test_dashboard', array('scope' => 'global'), $builder);
    assert_same(array('build' => 2), $third);
    assert_same(2, $builds);

    wp_using_ext_object_cache(true);
    $object_first = IDLM_Read_Model_Cache::remember('test_dashboard_object', array('scope' => 'global'), $builder);
    $object_second = IDLM_Read_Model_Cache::remember('test_dashboard_object', array('scope' => 'global'), $builder);
    assert_same(array('build' => 3), $object_first);
    assert_same($object_first, $object_second);
    assert_same(3, $builds);
});

idlm_test('dashboard cache context separates restricted representatives from global managers', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_reps_see_all_leads'] = '0';
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
    $rep_context = IDLM_Dashboard_Read_Model::cache_context('this_month');
    assert_same('owner:7', $rep_context['scope']);

    idlm_test_login(99, array('sales_manager'), array('idlm_access_leads', 'idlm_assign_leads'));
    $manager_context = IDLM_Dashboard_Read_Model::cache_context('this_month');
    assert_same('global', $manager_context['scope']);
    assert_true($rep_context['scope'] !== $manager_context['scope']);
    assert_true($rep_context['roles'] !== $manager_context['roles']);
});

idlm_test('lead lifecycle and dashboard settings rotate the read-cache generation', static function(): void {
    $initial = IDLM_Read_Model_Cache::generation();
    $lead_id = IDLM_DB::insert_lead(array(
        'first_name' => 'Asha',
        'email' => 'asha@example.test',
        'phone' => '9876543210',
        'lead_quality' => '',
        'assigned_rep_id' => 0,
    ));
    assert_true((bool) $lead_id);
    $after_insert = IDLM_Read_Model_Cache::generation();
    assert_true($initial !== $after_insert);

    assert_true(IDLM_DB::update_lead($lead_id, array('project_name' => 'Updated Residence')));
    $after_update = IDLM_Read_Model_Cache::generation();
    assert_true($after_insert !== $after_update);

    IDLM_Read_Model_Cache::invalidate_on_option_change('idlm_custom_stages');
    assert_true($after_update !== IDLM_Read_Model_Cache::generation());
});

idlm_test('dashboard cache invalidation covers routing, deletion, reset, quotations, and retention', static function(): void {
    $root = dirname(__DIR__) . '/includes/';
    assert_contains("IDLM_Read_Model_Cache::invalidate('lead_assignment')", file_get_contents($root . 'class-integrations.php'));
    assert_contains("IDLM_Read_Model_Cache::invalidate('lead_delete')", file_get_contents($root . 'class-lead-deletion-service.php'));
    assert_contains("IDLM_Read_Model_Cache::invalidate('crm_reset')", file_get_contents($root . 'class-lead-deletion-service.php'));

    $quotation_source = file_get_contents($root . 'class-quotation-controller.php');
    $commit_position = strpos($quotation_source, "if (false === \$wpdb->query('COMMIT')) {");
    $invalidation_position = strpos($quotation_source, "IDLM_Read_Model_Cache::invalidate('quotation_value')");
    assert_true(false !== $commit_position && false !== $invalidation_position && $invalidation_position > $commit_position);

    $cache_source = file_get_contents($root . 'class-read-model-cache.php');
    assert_contains("add_action(IDLM_Data_Retention::HOOK", $cache_source);
    assert_contains("'idlm_reps_see_all_leads'", $cache_source);
});

idlm_test('lead insert applies defaults, logs creation, and emits the New hook', static function(): void {
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
    $lead_id = IDLM_DB::insert_lead(array(
        'first_name' => 'Asha',
        'email' => 'asha@example.test',
        'phone' => '9876543210',
    ));
    assert_same(1, $lead_id);
    $lead = $GLOBALS['wpdb']->rows('idlm_leads')[0];
    assert_same('New', $lead['stage']);
    assert_same('Not Called', $lead['call_status']);
    assert_same(0, $lead['is_duplicate']);
    assert_same('creation', $GLOBALS['wpdb']->rows('idlm_lead_activity_log')[0]['activity_type']);
    assert_same(array('idlm_lead_stage_converted'), action_names());
    assert_same('New', $GLOBALS['idlm_test_actions'][0]['args'][1]);
});

idlm_test('lead insert detects normalized duplicate phones and suppresses duplicate conversion hooks', static function(): void {
    IDLM_DB::insert_lead(array('first_name' => 'Asha', 'email' => 'asha@example.test', 'phone' => '+91 98765 43210', 'source' => 'Website'));
    $duplicate_id = IDLM_DB::insert_lead(array('first_name' => 'Asha Again', 'email' => 'other@example.test', 'phone' => '9876543210', 'source' => 'Website'));
    assert_same(2, $duplicate_id);
    $duplicate = $GLOBALS['wpdb']->rows('idlm_leads')[1];
    assert_same(1, $duplicate['is_duplicate']);
    assert_same(1, $duplicate['duplicate_of_id']);
    assert_same('Unqualified', $duplicate['lead_quality']);
    assert_same('Lost', $duplicate['stage']);
    assert_same('duplicate_detection', $duplicate['disqualification_method']);
    assert_count_value(1, $GLOBALS['idlm_test_actions'], 'Only the canonical lead should emit a creation conversion hook.');
});

idlm_test('lead insert rejects invalid initial workflow states', static function(): void {
    assert_false(IDLM_DB::insert_lead(array('stage' => 'Invented', 'email' => 'a@example.test')));
    assert_contains('Invalid lead stage', IDLM_DB::get_last_validation_error());
    assert_false(IDLM_DB::insert_lead(array('lead_quality' => 'Unqualified', 'email' => 'b@example.test')));
    assert_contains('cannot start as Unqualified', IDLM_DB::get_last_validation_error());
    assert_count_value(0, $GLOBALS['wpdb']->rows('idlm_leads'));
});

idlm_test('lead update enforces Lost, Won, and Quote Shared invariants', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture()));
    assert_false(IDLM_DB::update_lead(1, array('stage' => 'Lost')));
    assert_contains('lost reason is required', IDLM_DB::get_last_validation_error());
    assert_false(IDLM_DB::update_lead(1, array('stage' => 'Won')));
    assert_contains('deal value', IDLM_DB::get_last_validation_error());
    assert_false(IDLM_DB::update_lead(1, array('stage' => 'Quote Shared')));
    assert_contains('positive quote value', IDLM_DB::get_last_validation_error());
    assert_same('New', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage']);
});

idlm_test('Won transitions persist the actual close timestamp', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('stage' => 'Negotiation', 'lead_quality' => 'Qualified', 'deal_value' => 750000))));
    assert_true(IDLM_DB::update_lead(1, array('stage' => 'Won')));
    $lead = $GLOBALS['wpdb']->rows('idlm_leads')[0];
    assert_same('Won', $lead['stage']);
    assert_same(current_time('mysql'), $lead['conversion_time']);
});

idlm_test('qualification updates stage, handoff state, tasks, audit, and conversion hook', static function(): void {
    idlm_test_login(7, array('idlm_pre_sales'), array('idlm_access_leads', 'idlm_qualify_leads'));
    $GLOBALS['idlm_test_users'][7] = (object) array('ID' => 7, 'roles' => array('idlm_pre_sales'), 'display_name' => 'Pre Sales', 'user_email' => 'pre@example.test');
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 7))));
    $GLOBALS['wpdb']->seed('idlm_tasks', array(array('id' => 1, 'lead_id' => 1, 'assigned_rep_id' => 7, 'task_type' => 'Qualification', 'status' => 'pending')));
    assert_true(IDLM_DB::update_lead(1, array('lead_quality' => 'Qualified')));
    $lead = $GLOBALS['wpdb']->rows('idlm_leads')[0];
    assert_same('Qualified', $lead['stage']);
    assert_same('Qualified', $lead['lead_quality']);
    assert_same(7, $lead['qualified_by_user_id']);
    assert_same('awaiting_manager', $lead['handoff_status']);
    assert_same(7, $lead['intake_owner_id']);
    assert_same('completed', $GLOBALS['wpdb']->rows('idlm_tasks')[0]['status']);
    assert_same(1, count(array_filter($GLOBALS['idlm_test_actions'], static fn(array $action): bool => 'idlm_lead_stage_converted' === $action['hook'])));
});

idlm_test('qualification without a floor plan requires and records a handoff remark', static function(): void {
    idlm_test_login(7, array('idlm_pre_sales'), array('idlm_access_leads', 'idlm_qualify_leads'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('floor_plan_received' => 0))));
    assert_false(IDLM_DB::update_lead(1, array('lead_quality' => 'Qualified')));
    assert_contains('handoff remark', IDLM_DB::get_last_validation_error());
    assert_true(IDLM_DB::update_lead(1, array('lead_quality' => 'Qualified', 'qualification_handoff_remark' => 'Client will provide it after the site visit.')));
    $types = array_column($GLOBALS['wpdb']->rows('idlm_lead_activity_log'), 'activity_type');
    assert_true(in_array('qualification_handoff_remark', $types, true));
});

idlm_test('qualified owner reassignment completes handoff and transfers pending work', static function(): void {
    idlm_test_login(99, array('sales_manager'), array('idlm_access_leads', 'idlm_assign_leads'));
    $GLOBALS['idlm_test_users'][7] = (object) array('ID' => 7, 'roles' => array('idlm_pre_sales'), 'display_name' => 'Pre Sales', 'user_email' => 'pre@example.test');
    $GLOBALS['idlm_test_users'][8] = (object) array('ID' => 8, 'roles' => array('sales_executive'), 'display_name' => 'Sales Rep', 'user_email' => 'sales@example.test');
    $GLOBALS['idlm_test_options']['idlm_handoff_task_policy'] = 'transfer';
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('stage' => 'Qualified', 'lead_quality' => 'Qualified', 'assigned_rep_id' => 7, 'handoff_status' => 'awaiting_manager'))));
    $GLOBALS['wpdb']->seed('idlm_tasks', array(array('id' => 1, 'lead_id' => 1, 'assigned_rep_id' => 7, 'task_type' => 'General', 'status' => 'pending')));
    assert_true(IDLM_DB::update_lead(1, array('assigned_rep_id' => 8)));
    $lead = $GLOBALS['wpdb']->rows('idlm_leads')[0];
    assert_same(8, $lead['assigned_rep_id']);
    assert_same('manually_assigned', $lead['handoff_status']);
    assert_same('manual_manager', $lead['handoff_method']);
    $tasks = $GLOBALS['wpdb']->rows('idlm_tasks');
    assert_same(8, $tasks[0]['assigned_rep_id']);
    assert_same('Sales Follow-up', $tasks[1]['task_type']);
    assert_same(8, $tasks[1]['assigned_rep_id']);
});

idlm_test('unqualification is blocked until outreach compliance is complete', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture()));
    assert_false(IDLM_DB::update_lead(1, array('lead_quality' => 'Unqualified')));
    assert_contains('Cannot mark Unqualified', IDLM_DB::get_last_validation_error());
    assert_same('', $GLOBALS['wpdb']->rows('idlm_leads')[0]['lead_quality']);
});

idlm_test('No Answer transition emits the legacy automation hook once', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture()));
    assert_true(IDLM_DB::update_lead(1, array('call_status' => 'No Answer')));
    assert_same(array('idlm_lead_call_status_no_answer'), action_names());
    assert_same('call_status_change', $GLOBALS['wpdb']->rows('idlm_lead_activity_log')[0]['activity_type']);
});

idlm_test('invalid AJAX nonce stops a destructive handler before database access', static function(): void {
    idlm_test_login(1, array('administrator'), array('manage_options'));
    $GLOBALS['idlm_test_nonce_valid'] = false;
    $_POST = array('lead_id' => 1);
    expect_json_response(static fn() => IDLM_Admin::ajax_delete_lead(), false, 403);
    assert_count_value(0, $GLOBALS['wpdb']->queries);
});

idlm_test('destructive AJAX handler denies users without manage_options', static function(): void {
    idlm_test_login(5, array('sales_executive'), array('idlm_access_leads'));
    $_POST = array('lead_id' => 1);
    $response = expect_json_response(static fn() => IDLM_Admin::ajax_delete_lead(), false);
    assert_contains('Only administrators', (string) $response->data['message']);
    assert_count_value(0, $GLOBALS['wpdb']->queries);
});

idlm_test('quick edit enforces object ownership for restricted representatives', static function(): void {
    idlm_test_login(5, array('sales_executive'), array('idlm_access_leads'));
    $GLOBALS['idlm_test_options']['idlm_reps_see_all_leads'] = '0';
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 9))));
    $_POST = array('lead_id' => 1, 'field' => 'stage', 'value' => 'Contacted');
    $response = expect_json_response(static fn() => IDLM_Admin::ajax_quick_edit_lead(), false);
    assert_contains('assigned to you', (string) $response->data['message']);
    assert_same('New', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage']);
});

idlm_test('pre-sales cannot change stage from the lead drawer header', static function(): void {
    idlm_test_login(5, array('idlm_pre_sales'), array('idlm_access_leads', 'idlm_qualify_leads'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 5))));
    $_POST = array('lead_id' => 1, 'field' => 'stage', 'value' => 'Contacted', 'edit_context' => 'drawer_header');
    $response = expect_json_response(static fn() => IDLM_Admin::ajax_quick_edit_lead(), false);
    assert_contains('Only sales representatives and managers', (string) $response->data['message']);
    assert_same('New', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage']);
});

idlm_test('bulk lifecycle mutation enforces object ownership for every restricted representative', static function(): void {
    idlm_test_login(5, array('sales_executive'), array('idlm_access_leads'));
    $GLOBALS['idlm_test_options']['idlm_reps_see_all_leads'] = '0';
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 9))));
    $_POST = array('ids' => array(1), 'bulk_action' => 'bulk_stage', 'stage' => 'Contacted');
    $response = expect_json_response(static fn() => IDLM_Admin::ajax_bulk_action(), false);
    assert_contains('assigned to you', (string) $response->data['message']);
    assert_same('New', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage']);
});

idlm_test('bulk lifecycle mutation remains available to the current lead owner', static function(): void {
    idlm_test_login(5, array('sales_executive'), array('idlm_access_leads'));
    $GLOBALS['idlm_test_options']['idlm_reps_see_all_leads'] = '0';
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 5))));
    $_POST = array('ids' => array(1), 'bulk_action' => 'bulk_stage', 'stage' => 'Contacted');
    expect_json_response(static fn() => IDLM_Admin::ajax_bulk_action(), true);
    assert_same('Contacted', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage']);
});

idlm_test('REST routes explicitly register permission callbacks and callback authentication remains enforced', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_webhook_api_key'] = 'webhook-scope-key';
    IDLM_Integrations::register_webhook_routes();
    $route = $GLOBALS['idlm_test_routes']['interior-design-lead-manager/v1/webhook'];
    assert_true(isset($route['permission_callback']));
    $permission_denied = call_user_func($route['permission_callback'], new WP_REST_Request(array('X-IDLM-API-Key' => 'wrong')));
    assert_true(is_wp_error($permission_denied));
    assert_same(401, $permission_denied->get_error_data()['status']);
    assert_true(call_user_func($route['permission_callback'], new WP_REST_Request(array('X-IDLM-API-Key' => 'webhook-scope-key'))));
    $response = IDLM_Integrations::handle_webhook_request(new WP_REST_Request(array('X-IDLM-API-Key' => 'wrong'), array('platform' => 'meta'), array('name' => 'Test')));
    assert_same(401, $response->get_status());
});

idlm_test('lead webhook accepts a valid header key and rejects invalid payload/platform inputs', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_webhook_api_key'] = 'secret-key';
    $valid = new WP_REST_Request(array('X-IDLM-API-Key' => 'secret-key', 'X-Hook-Test' => 'true'), array('platform' => 'meta'), array('name' => 'Test'));
    assert_same(200, IDLM_Integrations::handle_webhook_request($valid)->get_status());
    $empty = new WP_REST_Request(array('X-IDLM-API-Key' => 'secret-key'), array('platform' => 'meta'));
    assert_same(400, IDLM_Integrations::handle_webhook_request($empty)->get_status());
    $bad_platform = new WP_REST_Request(array('X-IDLM-API-Key' => 'secret-key'), array('platform' => 'unknown'), array('name' => 'Test'));
    assert_same(400, IDLM_Integrations::handle_webhook_request($bad_platform)->get_status());
});

idlm_test('credential migration encrypts scoped secrets and removes legacy/query access', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_api_webhook_key'] = 'historical-shared-key';
    IDLM_Integrations::maybe_migrate_api_credentials();
    assert_same('historical-shared-key', IDLM_Secret_Store::get('idlm_webhook_api_key'));
    assert_true(0 === strpos((string) get_option('idlm_webhook_api_key'), IDLM_Secret_Store::PREFIX));
    assert_true('historical-shared-key' !== IDLM_Secret_Store::get('idlm_mcp_api_key'));
    assert_true('historical-shared-key' !== IDLM_Secret_Store::get('idlm_google_gadm_password'));
    assert_same(false, get_option('idlm_api_webhook_key'));
    assert_same(IDLM_Integrations::API_CREDENTIALS_VERSION, get_option('idlm_api_credentials_version'));

    assert_true(is_wp_error(IDLM_Integrations::permission_mcp(new WP_REST_Request(array(), array('api_key' => IDLM_Secret_Store::get('idlm_mcp_api_key'))))));
    assert_true(IDLM_Integrations::permission_mcp(new WP_REST_Request(array('X-IDLM-API-Key' => IDLM_Secret_Store::get('idlm_mcp_api_key')))));
});

idlm_test('credential rotation replaces the encrypted scoped secret', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_mcp_api_key'] = 'old-mcp-key';
    $rotated = IDLM_Integrations::rotate_api_credential('mcp');
    assert_false(is_wp_error($rotated));
    assert_true('old-mcp-key' !== $rotated);
    assert_same($rotated, IDLM_Secret_Store::get('idlm_mcp_api_key'));
    assert_true(0 === strpos((string) get_option('idlm_mcp_api_key'), IDLM_Secret_Store::PREFIX));
    $metadata = get_option(IDLM_Production_Security::MCP_METADATA_OPTION, array());
    assert_same(IDLM_Production_Security::credential_fingerprint($rotated), $metadata['fingerprint']);
    assert_same(IDLM_Production_Security::credential_fingerprint('old-mcp-key'), $metadata['previous_fingerprint']);
    assert_true(!empty($metadata['previous_revoked_at']));
    assert_true(is_wp_error(IDLM_Integrations::rotate_api_credential('unknown')));
});

idlm_test('production security applies MCP tool grants and bounded budgets', static function(): void {
    update_option(IDLM_Production_Security::MCP_GRANTS_OPTION, array('get_leads_summary', 'unknown_tool'));
    assert_same(array('get_leads_summary'), IDLM_Production_Security::mcp_allowed_tools());
    assert_count_value(1, IDLM_MCP_Core::get_tools_list());
    assert_contains('denied', IDLM_MCP_Core::execute_tool('get_lead_details', array('id' => 1))['error']);
    assert_same(20, IDLM_Production_Security::mcp_batch_limit());
    assert_same(200, IDLM_Production_Security::mcp_query_row_limit());

    update_option('idlm_mcp_enabled', '0');
    assert_false(IDLM_Production_Security::is_mcp_enabled());
    $disabled = IDLM_Integrations::permission_mcp(new WP_REST_Request(array('X-IDLM-API-Key' => 'unused')));
    assert_true(is_wp_error($disabled));
    assert_same('idlm_mcp_disabled', $disabled->get_error_code());
    assert_same(503, $disabled->get_error_data()['status']);
    $disabled_protocol = IDLM_MCP_Core::handle_json_rpc(array('jsonrpc' => '2.0', 'id' => 9, 'method' => 'tools/list'));
    assert_same(-32003, $disabled_protocol['error']['code']);
    assert_contains('IDLM_MCP_DISABLED', file_get_contents(dirname(__DIR__) . '/includes/class-production-security.php'));
});

idlm_test('trusted proxy policy ignores spoofed forwarding headers and resolves configured chains', static function(): void {
    update_option('idlm_trusted_proxies', array('10.0.0.0/8', '2001:db8::/32'));
    assert_same('198.51.100.10', IDLM_Production_Security::client_ip(array(
        'REMOTE_ADDR' => '198.51.100.10',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
    )));
    assert_same('203.0.113.50', IDLM_Production_Security::client_ip(array(
        'REMOTE_ADDR' => '10.20.30.40',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.50, 10.10.10.10',
    )));
    assert_true(IDLM_Production_Security::is_trusted_proxy('2001:db8::5'));
    assert_false(IDLM_Production_Security::is_trusted_proxy('2001:db9::5'));
});

idlm_test('lead attachment policies are field-specific and reject unknown purposes', static function(): void {
    $floor_plan = IDLM_Production_Security::upload_policy('floor_plan');
    $quote = IDLM_Production_Security::upload_policy('quote');
    assert_true(in_array('webp', $floor_plan['extensions'], true));
    assert_false(in_array('docx', $floor_plan['extensions'], true));
    assert_true(in_array('docx', $quote['extensions'], true));
    assert_same(10 * MB_IN_BYTES, $quote['max_bytes']);
    assert_true(is_wp_error(IDLM_Production_Security::validate_upload(array(), 'unknown')));

    $source = file_get_contents(dirname(__DIR__) . '/includes/class-admin-ajax.php');
    assert_contains('IDLM_Production_Security::validate_upload', $source);
    assert_contains('_idlm_attachment_owner_id', $source);
    assert_contains('wp_delete_attachment($attachment_id, true)', $source);
});

idlm_test('floor plan attachments normalize legacy and multi-file values safely', static function(): void {
    $files = IDLM_Admin_Presenter::normalize_floor_plan_files(
        wp_json_encode(array('https://example.test/plan-b.pdf', 'javascript:alert(1)', 'https://example.test/plan-a.png')),
        'https://example.test/plan-a.png'
    );
    assert_same(array('https://example.test/plan-a.png', 'https://example.test/plan-b.pdf'), $files);

    $drawer_source = file_get_contents(dirname(__DIR__) . '/includes/class-admin-lead-reads.php');
    $script_source = file_get_contents(dirname(__DIR__) . '/assets/js/admin-app.js');
    assert_contains('name="floor_plan_files"', $drawer_source);
    assert_contains('multiple style="display: none;"', $drawer_source);
    assert_contains('>View</a>', $drawer_source);
    assert_contains('>Download</a>', $drawer_source);
    assert_contains('uploadFloorPlanFiles', $script_source);
});

idlm_test('Google feed permission uses dedicated Basic Auth and preserves its challenge', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_google_gadm_username'] = 'google-source';
    $GLOBALS['idlm_test_options']['idlm_google_gadm_password'] = 'google-feed-password';
    $valid = new WP_REST_Request(array('Authorization' => 'Basic ' . base64_encode('google-source:google-feed-password')));
    assert_true(IDLM_Integrations::permission_google_feed($valid));
    $denied = IDLM_Integrations::permission_google_feed(new WP_REST_Request(array('Authorization' => 'Basic ' . base64_encode('google-source:wrong'))));
    assert_true(is_wp_error($denied));
    assert_same(401, $denied->get_error_data()['status']);

    $response = new WP_REST_Response(array('error' => 'unauthorized'), 401);
    $request = new WP_REST_Request(array(), array(), array(), array(), '', '/interior-design-lead-manager/v1/google-ads-conversions.csv');
    $response = IDLM_Integrations::add_google_feed_auth_challenge($response, null, $request);
    assert_same('Basic realm="Google Ads Data Manager Feed"', $response->get_headers()['WWW-Authenticate']);
});

idlm_test('MCP PII tool rejects unauthorized requests and returns lead data only with a valid API key', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_mcp_api_key'] = 'secret-key';
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture()));
    $body = wp_json_encode(array(
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => array('name' => 'get_lead_details', 'arguments' => array('id' => 1)),
    ));
    $denied = IDLM_Integrations::handle_mcp_post(new WP_REST_Request(array(), array(), array(), array(), $body));
    assert_same(401, $denied->get_status());
    $allowed = IDLM_Integrations::handle_mcp_post(new WP_REST_Request(array('X-IDLM-API-Key' => 'secret-key'), array(), array(), array(), $body));
    assert_same(200, $allowed->get_status());
    assert_contains('asha@example.test', $allowed->get_data()['result']['content'][0]['text']);
    assert_contains('LIMIT 200', $GLOBALS['wpdb']->last_get_results_query);
});

idlm_test('MCP rejects oversized bodies and oversized JSON-RPC batches', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_mcp_api_key'] = 'mcp-limit-key';
    $headers = array('X-IDLM-API-Key' => 'mcp-limit-key');
    $oversized_body = IDLM_Integrations::handle_mcp_post(new WP_REST_Request($headers, array(), array(), array(), str_repeat('x', 1048577)));
    assert_same(413, $oversized_body->get_status());
    assert_contains('exceeds the 1 MB limit', $oversized_body->get_data()['error']['message']);

    $call = array('jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => array());
    $oversized_batch = IDLM_Integrations::handle_mcp_post(new WP_REST_Request($headers, array(), array(), array(), wp_json_encode(array_fill(0, 21, $call))));
    assert_same(413, $oversized_batch->get_status());
    assert_contains('limited to 20 calls', $oversized_batch->get_data()['error']['message']);
});

idlm_test('MCP SQL guard rejects mutation, comments, unsafe functions, and non-CRM tables', static function(): void {
    assert_contains('Only read-only SELECT', IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'UPDATE wp_idlm_leads SET stage = "Won"'))['error']);
    assert_contains('comments', IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT * FROM wp_idlm_leads; SELECT 1'))['error']);
    assert_contains('restricted', IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT * FROM wp_options'))['error']);
    assert_contains('unsafe database functions', IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT SLEEP(1) FROM wp_idlm_leads'))['error']);
    assert_contains('unsafe database functions', IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT RAND() FROM wp_idlm_leads'))['error']);
    assert_contains('explicit allowlisted FROM', IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT 1'))['error']);
    assert_contains('Comma-separated', IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT * FROM wp_idlm_leads l, wp_users u'))['error']);
    assert_contains('Database-qualified', IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT * FROM wp_idlm_leads.wp_options'))['error']);
    assert_contains('CROSS JOIN', IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT * FROM wp_idlm_leads CROSS JOIN wp_users'))['error']);
});

idlm_test('MCP SQL guard appends and caps a numeric row limit', static function(): void {
    $GLOBALS['wpdb']->custom_query_results = array_fill(0, 250, array('id' => 1));
    $results = IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT id FROM wp_idlm_leads'));
    assert_same(200, count($results));
    assert_contains('LIMIT 200', $GLOBALS['wpdb']->last_get_results_query);
    IDLM_MCP_Core::execute_tool('run_custom_query', array('sql' => 'SELECT id FROM wp_idlm_leads LIMIT 999'));
    assert_contains('LIMIT 200', $GLOBALS['wpdb']->last_get_results_query);
});

idlm_test('REST permission callbacks rate limit repeated MCP requests', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_mcp_api_key'] = 'mcp-rate-key';
    $_SERVER['REMOTE_ADDR'] = '192.0.2.10';
    $request = new WP_REST_Request(array('X-IDLM-API-Key' => 'mcp-rate-key'));
    for ($request_number = 0; $request_number < 60; $request_number++) {
        assert_true(IDLM_Integrations::permission_mcp($request));
    }
    $limited = IDLM_Integrations::permission_mcp($request);
    assert_true(is_wp_error($limited));
    assert_same(429, $limited->get_error_data()['status']);
});

idlm_test('MCP lead query caps caller-supplied result size at 100', static function(): void {
    IDLM_MCP_Core::execute_tool('query_leads', array('limit' => 9999));
    assert_contains('LIMIT 100 OFFSET 0', $GLOBALS['wpdb']->last_get_results_query);
});

idlm_test('Interakt and Meta signatures require an exact SHA-256 HMAC', static function(): void {
    $payload = '{"event":"message_received"}';
    $signature = 'sha256=' . hash_hmac('sha256', $payload, 'secret');
    assert_true(IDLM_Interakt::verify_webhook_signature($payload, $signature, 'secret'));
    assert_false(IDLM_Interakt::verify_webhook_signature($payload, $signature, 'wrong'));
    assert_true(IDLM_Interakt::verify_meta_webhook_signature($payload, $signature, 'secret'));
});

idlm_test('quotation policy gives administrators capability-controlled unrestricted lead access', static function(): void {
    idlm_test_login(1, array('administrator'), array('manage_options', 'idlm_view_quotes', 'idlm_create_quotes'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(
        lead_fixture(array('id' => 1, 'first_name' => 'Asha', 'assigned_rep_id' => 7)),
        lead_fixture(array('id' => 2, 'first_name' => 'Ashok', 'assigned_rep_id' => 9)),
    ));

    assert_true(IDLM_Quotation_Policy::can('idlm_create_quotes'));
    assert_true(IDLM_Quotation_Policy::can_manage_all_leads());
    assert_true(IDLM_Quotation_Policy::can_view_lead(2));
    assert_true(invoke_private('IDLM_Quotations', 'can_view_lead', array(2)));
    assert_count_value(2, IDLM_Quotation_Lead_Read_Model::search('Ash'));
    assert_true(false === strpos($GLOBALS['wpdb']->last_get_results_query, 'l.assigned_rep_id = 1'));
});

idlm_test('quotation policy gives assignment managers unrestricted lead access without administrator capability', static function(): void {
    idlm_test_login(3, array('sales_manager'), array('idlm_assign_leads', 'idlm_view_quotes', 'idlm_create_quotes'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(
        lead_fixture(array('id' => 1, 'first_name' => 'Asha', 'assigned_rep_id' => 7)),
        lead_fixture(array('id' => 2, 'first_name' => 'Ashok', 'assigned_rep_id' => 9)),
    ));

    assert_true(IDLM_Quotation_Policy::can_manage_all_leads());
    assert_true(IDLM_Quotation_Policy::can_view_lead(1));
    assert_true(IDLM_Quotation_Policy::can_view_lead(2));
    assert_count_value(2, IDLM_Quotation_Lead_Read_Model::list_for_editor());
});

idlm_test('quotation policy and lead read model allow an owner only assigned leads', static function(): void {
    idlm_test_login(7, array('sales_executive'), array('idlm_view_quotes', 'idlm_create_quotes'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(
        lead_fixture(array('id' => 1, 'first_name' => 'Asha', 'assigned_rep_id' => 7)),
        lead_fixture(array('id' => 2, 'first_name' => 'Ashok', 'assigned_rep_id' => 9)),
    ));

    assert_false(IDLM_Quotation_Policy::can_manage_all_leads());
    assert_true(IDLM_Quotation_Policy::can_view_lead(1));
    assert_false(IDLM_Quotation_Policy::can_view_lead(2));
    $leads = IDLM_Quotation_Lead_Read_Model::search('Ash');
    assert_count_value(1, $leads);
    assert_same(1, (int) $leads[0]->id);
    assert_same(1, (int) IDLM_Quotation_Lead_Read_Model::get_selected_lead(1)->id);
    assert_same(null, IDLM_Quotation_Lead_Read_Model::get_selected_lead(2));
    assert_contains('l.assigned_rep_id = 7', $GLOBALS['wpdb']->last_get_results_query);
});

idlm_test('quotation policy prevents a restricted representative reading another owners leads', static function(): void {
    idlm_test_login(8, array('sales_executive'), array('idlm_view_quotes', 'idlm_create_quotes'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(
        lead_fixture(array('id' => 1, 'first_name' => 'Asha', 'assigned_rep_id' => 7)),
        lead_fixture(array('id' => 2, 'first_name' => 'Ashok', 'assigned_rep_id' => 9)),
    ));

    assert_false(IDLM_Quotation_Policy::can_view_lead(1));
    assert_false(IDLM_Quotation_Policy::can_view_lead(2));
    assert_count_value(0, IDLM_Quotation_Lead_Read_Model::list_for_editor());
    assert_count_value(0, IDLM_Quotation_Lead_Read_Model::search('Ash'));
    assert_same(null, IDLM_Quotation_Lead_Read_Model::get_selected_lead(1));
});

idlm_test('quotation policy and handler deny users without quotation capabilities', static function(): void {
    idlm_test_login(7, array('subscriber'), array('read'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 7))));

    assert_false(IDLM_Quotation_Policy::can('idlm_view_quotes'));
    assert_false(IDLM_Quotation_Policy::can_view_lead(1));
    assert_false(invoke_private('IDLM_Quotations', 'can_view_lead', array(1)));
    $_GET = array('term' => 'Asha');
    $response = expect_json_response(static fn() => IDLM_Quotations::ajax_search_leads(), false, 403);
    assert_contains('Permission denied', (string) $response->data['message']);
});

idlm_test('quotation line items calculate quantity multiplied by rate and sanitize optional/header rows', static function(): void {
    $items = invoke_private('IDLM_Quotations', 'parse_items', array(array(
        array('description' => 'Woodwork', 'quantity' => '2.50', 'unit_rate' => '1200.40', 'is_wood' => '1'),
        array('description' => 'Optional appliance', 'quantity' => '1', 'unit_rate' => '500', 'is_optional' => '1'),
        array('row_type' => 'header', 'description' => 'Living room'),
        array('description' => ''),
    ), array()));
    assert_count_value(3, $items);
    assert_same(3001.0, $items[0]['line_subtotal']);
    assert_same(3001.0, $items[0]['line_total']);
    assert_same(1, $items[1]['is_optional']);
    assert_same('header', $items[2]['row_type']);
    assert_same(0, $items[2]['line_total']);
});

idlm_test('quotation totals apply wooden discount, optional soft close addition, additional discount, and fixed GST', static function(): void {
    $items = invoke_private('IDLM_Quotations', 'parse_items', array(array(
        array('description' => 'Woodwork', 'quantity' => '2', 'unit_rate' => '1000', 'is_wood' => '1'),
        array('description' => 'Lighting', 'quantity' => '3', 'unit_rate' => '500'),
        array('description' => 'Optional', 'quantity' => '1', 'unit_rate' => '999', 'is_optional' => '1'),
    ), array()));
    $totals = invoke_private('IDLM_Quotations', 'calculate_totals', array($items, array(
        'wooden_discount_percent' => '10',
        'soft_close_enabled' => '1',
        'additional_discount_amount' => '130',
    ), array('soft_close_percent' => '10')));
    assert_same(3500.0, $totals['subtotal']);
    assert_same(2000.0, $totals['wooden_subtotal']);
    assert_same(200.0, $totals['wooden_discount_amount']);
    assert_same(1800.0, $totals['subtotal_after_wooden_discount']);
    assert_same(1500.0, $totals['other_subtotal']);
    assert_same(10.0, $totals['soft_close_percent']);
    assert_same(180.0, $totals['soft_close_amount']);
    assert_same(3350.0, $totals['taxable_total']);
    assert_same(301.5, $totals['cgst_total']);
    assert_same(301.5, $totals['sgst_total']);
    assert_same(0, $totals['igst_total']);
    assert_same(0.0, $totals['round_off_amount']);
    assert_same(3953.0, $totals['grand_total']);
});

idlm_test('quotation grand total rounds to the nearest rupee and records the adjustment', static function(): void {
    $items = IDLM_Quotation_Calculator::parse_items(array(
        array('description' => 'Rounding example', 'quantity' => '1', 'unit_rate' => '10.01'),
    ));
    $totals = IDLM_Quotation_Calculator::calculate_totals($items, array());
    assert_same(0.19, $totals['round_off_amount']);
    assert_same(12.0, $totals['grand_total']);
});

idlm_test('quotation calculator is deterministic and the legacy quotation API delegates without drift', static function(): void {
    $raw_items = array(
        array('section_key' => 'living', 'description' => 'Woodwork', 'quantity' => '1.335', 'unit_rate' => '999.995', 'is_wood' => '1'),
        array('section_key' => 'living', 'description' => 'Optional light', 'quantity' => '1', 'unit_rate' => '250', 'is_optional' => '1'),
        array('section_key' => 'living', 'row_type' => 'header', 'description' => 'Fixtures'),
    );
    $adjustments = array(
        'wooden_discount_percent' => '125',
        'additional_discount_amount' => '-50',
    );

    $items = IDLM_Quotation_Calculator::parse_items($raw_items);
    $totals = IDLM_Quotation_Calculator::calculate_totals($items, $adjustments);

    assert_same($items, IDLM_Quotation_Calculator::parse_items($raw_items));
    assert_same($totals, IDLM_Quotation_Calculator::calculate_totals($items, $adjustments));
    assert_same($items, invoke_private('IDLM_Quotations', 'parse_items', array($raw_items, array())));
    assert_same($totals, invoke_private('IDLM_Quotations', 'calculate_totals', array($items, $adjustments)));
    assert_same(100, $totals['wooden_discount_percent']);
    assert_same(0.0, $totals['additional_discount_amount']);

    $sections = IDLM_Quotation_Calculator::parse_sections(array(array('key' => 'living', 'section_name' => 'Living Room')));
    assert_same($sections, invoke_private('IDLM_Quotations', 'parse_sections', array(array(array('key' => 'living', 'section_name' => 'Living Room')))));
});

idlm_test('quotation repository owns table mapping and legacy aggregate read delegates', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_quotes', array(array(
        'id' => 11,
        'lead_id' => 7,
        'quote_number' => 'Q-2026-000011',
        'current_revision_id' => 21,
    )));
    $GLOBALS['wpdb']->seed('idlm_quote_revisions', array(array(
        'id' => 21,
        'quote_id' => 11,
        'revision_number' => 1,
    )));

    assert_same('wp_idlm_quotes', IDLM_Quotation_Repository::table('quotes'));
    assert_same('wp_idlm_quotes', invoke_private('IDLM_Quotations', 'table', array('quotes')));
    assert_same(
        (array) IDLM_Quotation_Repository::get_quote(11),
        (array) invoke_private('IDLM_Quotations', 'get_quote', array(11))
    );
    assert_same(
        (array) IDLM_Quotation_Repository::get_revision(21),
        (array) invoke_private('IDLM_Quotations', 'get_revision', array(21))
    );
});

idlm_test('quotation repository persists and copies complete revision aggregates', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('id' => 7))));
    $quote_id = IDLM_Quotation_Repository::insert_quote(array(
        'lead_id' => 7,
        'quote_number' => 'Q-2026-000011',
        'title' => 'Initial proposal',
        'status' => 'Draft',
        'save_request_key' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    ));
    assert_same(1, $quote_id);

    $source_id = IDLM_Quotation_Repository::insert_revision(array(
        'quote_id' => $quote_id,
        'revision_number' => 1,
        'revision_name' => 'Q-2026-000011-R01',
        'status' => 'Draft',
        'save_request_key' => 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
    ));
    assert_same(1, $source_id);
    assert_same(2, IDLM_Quotation_Repository::next_revision_number($quote_id));
    assert_same($source_id, (int) IDLM_Quotation_Repository::get_revision_by_save_request_key('bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb')->id);

    $saved = IDLM_Quotation_Repository::replace_revision_contents(
        $source_id,
        array('living' => array('section_name' => 'Living Room', 'sort_order' => 0)),
        array(array('section_key' => 'living', 'description' => 'Woodwork', 'quantity' => 1, 'unit_rate' => 1000, 'sort_order' => 0))
    );
    assert_true(true === $saved);

    $target_id = IDLM_Quotation_Repository::insert_revision(array(
        'quote_id' => $quote_id,
        'revision_number' => 2,
        'revision_name' => 'Q-2026-000011-R02',
        'status' => 'Draft',
        'save_request_key' => 'cccccccc-cccc-4ccc-8ccc-cccccccccccc',
    ));
    assert_same(2, $target_id);
    assert_true(true === IDLM_Quotation_Repository::copy_revision_contents($source_id, $target_id));
    assert_same(2, count($GLOBALS['wpdb']->rows('idlm_quote_revision_sections')));
    assert_same(2, count($GLOBALS['wpdb']->rows('idlm_quote_revision_items')));
    $copied_section = $GLOBALS['wpdb']->rows('idlm_quote_revision_sections')[1];
    $copied_item = $GLOBALS['wpdb']->rows('idlm_quote_revision_items')[1];
    assert_same($target_id, (int) $copied_section['revision_id']);
    assert_same((int) $copied_section['id'], (int) $copied_item['section_id']);

    assert_same(1, IDLM_Quotation_Repository::update_quote($quote_id, array('title' => 'Updated proposal')));
    assert_same(1, IDLM_Quotation_Repository::set_current_revision($quote_id, $target_id, 'Draft', '2026-08-29 12:00:00'));
    assert_same(1, IDLM_Quotation_Repository::supersede_revision($source_id, '2026-08-29 12:00:00'));
    assert_same(1, IDLM_Quotation_Repository::update_lead_quote_value(7, 1180, '2026-08-29 12:00:00'));
    assert_same('Updated proposal', $GLOBALS['wpdb']->rows('idlm_quotes')[0]['title']);
    assert_same($target_id, (int) $GLOBALS['wpdb']->rows('idlm_quotes')[0]['current_revision_id']);
    assert_same('Superseded', $GLOBALS['wpdb']->rows('idlm_quote_revisions')[0]['status']);
    assert_same(1180.0, $GLOBALS['wpdb']->rows('idlm_leads')[0]['quote_value']);
});

idlm_test('quotation repository reports aggregate write failures to the facade rollback path', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_quote_revisions', array(array('id' => 3, 'quote_id' => 1, 'revision_number' => 1)));
    $GLOBALS['wpdb']->fail_next_write('insert', 'idlm_quote_revision_sections');
    $result = IDLM_Quotation_Repository::replace_revision_contents(
        3,
        array('general' => array('section_name' => 'General', 'sort_order' => 0)),
        array(array('section_key' => 'general', 'description' => 'Design', 'quantity' => 1, 'unit_rate' => 1000, 'sort_order' => 0))
    );
    assert_true(is_wp_error($result));
    assert_same('revision_section_insert_failed', $result->get_error_code());
    assert_same('Revision section insert failed', $result->get_error_data());
    assert_count_value(0, $GLOBALS['wpdb']->rows('idlm_quote_revision_items'));
});

idlm_test('quotation repository owns catalogue CRUD, bulk actions, and paginated read models', static function(): void {
    $now = '2026-08-29 12:00:00';
    assert_same(1, IDLM_Quotation_Repository::insert_catalogue_term('category', 'Living Room', 7, $now));
    assert_same(1, IDLM_Quotation_Repository::insert_catalogue_term('unit', 'Sq Ft', 7, $now));
    assert_same(1, IDLM_Quotation_Repository::insert_catalogue_term('category', 'Unused', 7, $now));
    assert_true(IDLM_Quotation_Repository::catalogue_term_exists('category', 'Living Room'));
    assert_true(IDLM_Quotation_Repository::catalogue_term_exists('unit', 'Sq Ft'));
    assert_false(IDLM_Quotation_Repository::catalogue_term_exists('unknown', 'Living Room'));

    assert_same(1, IDLM_Quotation_Repository::insert_catalogue_item(array(
        'item_code' => 'LR-001',
        'category' => 'Living Room',
        'item_name' => 'TV Unit',
        'description' => 'Custom TV unit',
        'unit' => 'Sq Ft',
        'is_wood' => 1,
        'default_rate' => 1200,
        'status' => 'active',
        'created_by' => 7,
        'created_at' => $now,
        'updated_at' => $now,
    )));
    assert_true(IDLM_Quotation_Repository::catalogue_item_code_exists('LR-001'));
    assert_same(1, IDLM_Quotation_Repository::insert_catalogue_item(array(
        'item_code' => '',
        'category' => 'Living Room',
        'item_name' => 'Generated Code Item',
        'unit' => 'Sq Ft',
        'created_at' => $now,
        'updated_at' => $now,
    )));
    assert_same('ITEM-000002', IDLM_Quotation_Repository::get_catalogue_item(2)->item_code);
    assert_same('active', IDLM_Quotation_Repository::get_catalogue_item_status(1));
    assert_same('TV Unit', IDLM_Quotation_Repository::get_catalogue_item(1)->item_name);
    assert_same(2, IDLM_Quotation_Repository::count_catalogue_items());
    assert_count_value(1, IDLM_Quotation_Repository::search_catalogue('TV', 1, 20));
    assert_count_value(2, IDLM_Quotation_Repository::list_catalogue_items('', 1, 100));
    $catalogue_source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-quotation-repository.php');
    $catalogue_renderer = (string) file_get_contents(dirname(__DIR__) . '/includes/class-quotation-renderer.php');
    $catalogue_script = (string) file_get_contents(dirname(__DIR__) . '/assets/js/quotations.js');
    assert_contains("'rate' => 'default_rate'", $catalogue_source);
    assert_contains('ORDER BY {$order_by}', $catalogue_source);
    assert_contains('idlm-catalogue-sort-form', $catalogue_renderer);
    assert_contains('catalogueToolbar.insertBefore(catalogueSortForm', $catalogue_script);
    assert_count_value(2, IDLM_Quotation_Repository::list_catalogue_categories());
    assert_same(array('Sq Ft'), IDLM_Quotation_Repository::list_active_catalogue_units());
    $export = IDLM_Quotation_Repository::get_catalogue_export_chunk(0, 500);
    assert_count_value(2, $export);
    assert_same('Yes', $export[0]['is_wood']);

    assert_same(1, IDLM_Quotation_Repository::update_catalogue_item(1, array('default_rate' => 1350)));
    assert_same(1, IDLM_Quotation_Repository::archive_catalogue_item(1, $now));
    assert_same('inactive', IDLM_Quotation_Repository::get_catalogue_item_status(1));
    assert_same(2, IDLM_Quotation_Repository::bulk_catalogue_items(array(1, 2), 'delete', $now));
    assert_count_value(0, $GLOBALS['wpdb']->rows('idlm_quote_catalogue_items'));

    assert_same(1, IDLM_Quotation_Repository::ensure_catalogue_term('category', 'Kitchen', 7, $now));
    $category_result = IDLM_Quotation_Repository::apply_catalogue_category_action(array(1, 2), 'delete', $now);
    assert_same(array('affected' => 2, 'blocked' => 0), $category_result);
    assert_count_value(0, $GLOBALS['wpdb']->rows('idlm_quote_item_categories'));
});

idlm_test('quotation repository prepares scoped quotation list filters and propagates catalogue write failures', static function(): void {
    $GLOBALS['wpdb']->custom_get_var_result = 1;
    $GLOBALS['wpdb']->custom_query_results = array(array(
        'id' => 9,
        'quote_number' => 'Q-2026-000009',
        'first_name' => 'Asha',
        'last_name' => 'Rao',
    ));
    $filters = array(
        'owner_user_id' => 5,
        'search' => 'Asha',
        'created_by' => 7,
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-29',
        'lead_id' => 3,
    );
    assert_same(1, IDLM_Quotation_Repository::count_quotations($filters));
    $quotes = IDLM_Quotation_Repository::list_quotations($filters, 2, 50);
    assert_count_value(1, $quotes);
    assert_contains('q.owner_user_id = 5', $GLOBALS['wpdb']->last_get_results_query);
    assert_contains("q.quote_number LIKE '%Asha%'", $GLOBALS['wpdb']->last_get_results_query);
    assert_contains('q.created_by = 7', $GLOBALS['wpdb']->last_get_results_query);
    assert_contains('q.lead_id = 3', $GLOBALS['wpdb']->last_get_results_query);
    assert_contains('LIMIT 50 OFFSET 50', $GLOBALS['wpdb']->last_get_results_query);

    $GLOBALS['wpdb']->fail_next_write('insert', 'idlm_quote_catalogue_items');
    assert_false(IDLM_Quotation_Repository::insert_catalogue_item(array('item_code' => 'FAIL-001')));
});

idlm_test('quotation migration compatibility delegates preserve version markers', static function(): void {
    invoke_private('IDLM_Quotations', 'migrate_legacy_quotes');
    invoke_private('IDLM_Quotations', 'backfill_revision_snapshots_and_sections');

    assert_same(IDLM_Quotation_Schema::LEGACY_MIGRATION_VERSION, get_option('idlm_legacy_quote_migration_version'));
    assert_same(IDLM_Quotation_Schema::SNAPSHOT_MIGRATION_VERSION, get_option('idlm_quotation_snapshot_migration_version'));
});

idlm_test('quotation numbering is yearly, formatted, and monotonically increments', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_quote_settings'] = array('number_prefix' => 'Q', 'number_separator' => '-', 'number_padding' => 6);
    assert_same('Q-2026-000001', invoke_private('IDLM_Quotations', 'next_quote_number'));
    assert_same('Q-2026-000002', invoke_private('IDLM_Quotations', 'next_quote_number'));
    assert_same(2, $GLOBALS['wpdb']->rows('idlm_number_sequences')[0]['last_number']);
    assert_same(array('START TRANSACTION', 'COMMIT', 'START TRANSACTION', 'COMMIT'), array_values(array_filter($GLOBALS['wpdb']->queries, static fn(string $query): bool => in_array($query, array('START TRANSACTION', 'COMMIT'), true))));
});

idlm_test('quotation autosave rejects missing capability and object-access mismatches', static function(): void {
    idlm_test_login(5, array('sales_executive'), array('idlm_view_quotes'));
    $_POST = array('quote_id' => 1, 'revision_id' => 1, 'lead_id' => 1);
    $response = expect_json_response(static fn() => IDLM_Quotations::ajax_autosave_quote(), false, 403);
    assert_contains('permission', (string) $response->data['message']);

    idlm_test_login(5, array('sales_executive'), array('idlm_view_quotes', 'idlm_edit_quote_drafts'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 9))));
    $GLOBALS['wpdb']->seed('idlm_quotes', array(array('id' => 1, 'lead_id' => 1, 'current_revision_id' => 1)));
    $GLOBALS['wpdb']->seed('idlm_quote_revisions', array(array('id' => 1, 'quote_id' => 1, 'currency_code' => 'INR')));
    $response = expect_json_response(static fn() => IDLM_Quotations::ajax_autosave_quote(), false, 409);
    assert_contains('current quotation revision', (string) $response->data['message']);
});

idlm_test('quotation autosave stores a scoped working copy with server-calculated totals', static function(): void {
    idlm_test_login(5, array('sales_executive'), array('idlm_view_quotes', 'idlm_edit_quote_drafts'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 5))));
    $GLOBALS['wpdb']->seed('idlm_quotes', array(array('id' => 1, 'lead_id' => 1, 'current_revision_id' => 1)));
    $GLOBALS['wpdb']->seed('idlm_quote_revisions', array(array('id' => 1, 'quote_id' => 1, 'currency_code' => 'INR', 'customer_intro' => '')));
    $_POST = array(
        'quote_id' => 1,
        'revision_id' => 1,
        'lead_id' => 1,
        'title' => 'Proposal',
        'items' => array(array('description' => 'Design', 'quantity' => '2', 'unit_rate' => '1000')),
        'sections' => array(array('key' => 'general', 'section_name' => 'General')),
    );
    $response = expect_json_response(static fn() => IDLM_Quotations::ajax_autosave_quote(), true);
    assert_same(2360.0, $response->data['grandTotal']);
    $copy = $GLOBALS['idlm_test_transients']['idlm_quote_autosave_5_1'];
    assert_same(2360.0, $copy['revision']['grand_total']);
    assert_same('Proposal', $copy['title']);
});

idlm_test('core schema baseline declares required lifecycle columns, defaults, and indexes', static function(): void {
    IDLM_DB::create_tables();
    assert_count_value(10, $GLOBALS['wpdb']->dbdelta);
    $leads_sql = $GLOBALS['wpdb']->dbdelta[0];
    assert_contains("handoff_status varchar(30) DEFAULT 'not_ready'", $leads_sql);
    assert_contains('qualified_at datetime DEFAULT NULL', $leads_sql);
    assert_contains('floor_plan_files longtext DEFAULT NULL', $leads_sql);
    assert_contains('KEY assigned_at (assigned_at)', $leads_sql);
    assert_contains('KEY conversion_time (conversion_time)', $leads_sql);
    assert_contains('KEY duplicate_of_id (duplicate_of_id)', $leads_sql);
    $db_source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-db.php');
    assert_contains('MAX(created_at) AS won_at', $db_source);
    assert_contains("WHERE l.stage = 'Won'", $db_source);
    $state_events_sql = $GLOBALS['wpdb']->dbdelta[2];
    assert_contains('amount decimal(14,2) DEFAULT NULL', $state_events_sql);
    assert_contains('KEY event_target_time (event_type, to_value, occurred_at)', $state_events_sql);
    $tasks_sql = $GLOBALS['wpdb']->dbdelta[6];
    assert_contains("status varchar(50) DEFAULT 'pending'", $tasks_sql);
    assert_contains('KEY lead_status_due (lead_id, status, due_date)', $tasks_sql);
    $reviews_sql = $GLOBALS['wpdb']->dbdelta[9];
    assert_contains('idlm_inbound_submission_reviews', $reviews_sql);
    assert_contains("status varchar(20) DEFAULT 'pending'", $reviews_sql);
});

idlm_test('core migration check is idempotent at the current schema version', static function(): void {
    $GLOBALS['idlm_test_options'] = array(
        'idlm_schema_version' => IDLM_DB_VERSION,
        'idlm_handoff_state_version' => '1.0.0',
        'idlm_duplicate_quality_version' => '1.0.0',
        'idlm_workflow_integrity_version' => '1.0.0',
        'idlm_assignment_timestamp_version' => '1.0.0',
        'idlm_attribution_touchpoint_backfill_version' => '1.0.0',
    );
    IDLM_DB::init();
    assert_count_value(0, $GLOBALS['wpdb']->dbdelta);
    IDLM_DB::init();
    assert_count_value(0, $GLOBALS['wpdb']->dbdelta);
});

idlm_test('core migration upgrades an older schema and records the independent schema version', static function(): void {
    $GLOBALS['idlm_test_options'] = array(
        'idlm_schema_version' => '1.0.0',
        'idlm_handoff_state_version' => '1.0.0',
        'idlm_duplicate_quality_version' => '1.0.0',
        'idlm_workflow_integrity_version' => '1.0.0',
        'idlm_assignment_timestamp_version' => '1.0.0',
        'idlm_attribution_touchpoint_backfill_version' => '1.0.0',
    );
    IDLM_DB::init();
    assert_count_value(10, $GLOBALS['wpdb']->dbdelta);
    assert_same(IDLM_DB_VERSION, get_option('idlm_schema_version'));
});

idlm_test('core migration reconciles the state-event amount column from schema 1.3.2', static function(): void {
    $GLOBALS['idlm_test_options'] = array(
        'idlm_schema_version' => '1.3.2',
        'idlm_handoff_state_version' => '1.0.0',
        'idlm_duplicate_quality_version' => '1.0.0',
        'idlm_workflow_integrity_version' => '1.0.0',
        'idlm_assignment_timestamp_version' => '1.0.0',
        'idlm_attribution_touchpoint_backfill_version' => '1.0.0',
        'idlm_conversion_time_integrity_version' => '1.0.0',
    );

    IDLM_DB::init();

    assert_count_value(10, $GLOBALS['wpdb']->dbdelta);
    assert_contains('amount decimal(14,2) DEFAULT NULL', $GLOBALS['wpdb']->dbdelta[2]);
    assert_same('1.3.4', get_option('idlm_schema_version'));
});

idlm_test('quotation installer declares transactional persistence tables and records schema state', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_legacy_quote_migration_version'] = '1.1.0';
    $GLOBALS['idlm_test_options']['idlm_quotation_snapshot_migration_version'] = '1.1.0';
    IDLM_Quotations::install();
    assert_count_value(10, $GLOBALS['wpdb']->dbdelta);
    assert_contains('UNIQUE KEY quote_number (quote_number)', $GLOBALS['wpdb']->dbdelta[0]);
    assert_contains('UNIQUE KEY save_request_key (save_request_key)', $GLOBALS['wpdb']->dbdelta[1]);
    assert_contains('UNIQUE KEY sequence_year (sequence_type, sequence_year)', $GLOBALS['wpdb']->dbdelta[9]);
    assert_same(IDLM_Quotation_Schema::VERSION, IDLM_Quotations::SCHEMA_VERSION);
    assert_same(IDLM_Quotations::SCHEMA_VERSION, get_option('idlm_quotation_schema_version'));
});

idlm_test('scheduler registers and creates both fallback cron jobs idempotently', static function(): void {
    IDLM_Scheduler::init();
    $hooks = array_column($GLOBALS['idlm_test_hooks'], 'hook');
    assert_true(in_array('idlm_daily_audience_sync_cron', $hooks, true));
    assert_true(in_array('idlm_hourly_assignment_fallback_cron', $hooks, true));
    IDLM_Scheduler::setup_schedules();
    assert_count_value(2, $GLOBALS['idlm_test_schedules']);
    IDLM_Scheduler::setup_schedules();
    assert_count_value(2, $GLOBALS['idlm_test_schedules']);
});

idlm_test('state registry keeps stable keys separate from persisted labels', static function(): void {
    assert_same('Quote Shared', IDLM_State_Registry::stage_label('quote_shared'));
    assert_same('quote_shared', IDLM_State_Registry::stage_key('Quote Shared'));
    assert_same('No Answer', IDLM_State_Registry::label('call_status', 'no_answer'));
    assert_same('awaiting_manager', IDLM_State_Registry::label('handoff_status', 'awaiting_manager'));
    assert_same('', IDLM_State_Registry::stage_label('unknown-state'));
});

idlm_test('grouped routing settings migrate legacy values and preserve compatibility defaults', static function(): void {
    $GLOBALS['idlm_test_options']['idlm_assignment_mode'] = 'studio_based';
    $GLOBALS['idlm_test_options']['idlm_working_days'] = array(1, 3, 5);
    IDLM_Settings::init();
    $group = get_option(IDLM_Settings::ROUTING_OPTION, array());
    assert_same('studio_based', $group['idlm_assignment_mode']);
    assert_same(array(1, 3, 5), IDLM_Settings::get('idlm_working_days'));
    assert_same(30, IDLM_Settings::get('idlm_max_active_leads_per_rep'));
    assert_same(IDLM_Settings::VERSION, get_option(IDLM_Settings::VERSION_OPTION));
    update_option('idlm_assignment_mode', 'round_robin');
    assert_same('round_robin', IDLM_Settings::get('idlm_assignment_mode'), 'Legacy option writers must remain authoritative during migration.');
});

idlm_test('white-label settings normalize safe values and reject inaccessible colours', static function(): void {
    $valid = IDLM_Settings::validate_white_label(array(
        'brand_name' => 'Northline Studio',
        'workspace_title' => 'Project Desk',
        'login_heading' => 'Keep every client decision in one place.',
        'support_email' => 'help@northline.test',
        'primary_color' => '#264653',
        'accent_color' => '#E9C46A',
        'sidebar_color' => '#17212B',
        'show_plugin_attribution' => '1',
    ));
    assert_same(array(), $valid['errors']);
    assert_same('#264653', $valid['settings']['primary_color']);
    assert_same('1', $valid['settings']['show_plugin_attribution']);
    $tokens = IDLM_Settings::white_label_style($valid['settings']);
    assert_contains('--idlm-action:#264653;', $tokens);
    assert_contains('--idlm-action-hover:#1F3944;', $tokens);
    assert_contains('--idlm-action-soft:#E9EDEE;', $tokens);
    assert_contains('--idlm-brand-gold:#E9C46A;', $tokens);

    $invalid = IDLM_Settings::validate_white_label(array(
        'brand_name' => '',
        'workspace_title' => '',
        'login_heading' => '',
        'support_email' => 'not-an-email',
        'primary_color' => '#FFFFFF',
        'accent_color' => 'gold',
        'sidebar_color' => '#F8FAFC',
    ));
    foreach (array('brand_name', 'workspace_title', 'login_heading', 'support_email', 'primary_color', 'accent_color', 'sidebar_color') as $field) {
        assert_true(isset($invalid['errors'][$field]), 'Missing white-label validation error for ' . $field);
    }
});

idlm_test('white-label runtime surfaces do not retain fixed default accent colors', static function(): void {
    $root = dirname(__DIR__);
    $workspace = (string) file_get_contents($root . '/assets/css/workspace.css');
    $dashboard = (string) file_get_contents($root . '/assets/js/dashboard.js');
    $kanban = (string) file_get_contents($root . '/assets/js/kanban.js');
    $lead_markup = (string) file_get_contents($root . '/includes/class-admin-ajax.php')
        . (string) file_get_contents($root . '/includes/class-admin-lead-reads.php');

    assert_false((bool) preg_match('/(?:201\s*,\s*150\s*,\s*68|225\s*,\s*182\s*,\s*109|#e1b66d)/i', $workspace . $dashboard . $kanban . $lead_markup));
    assert_false(false !== strpos($lead_markup, 'var(--gold)'));
    assert_false(false !== strpos($lead_markup, 'var(--red)'));
    assert_contains("dashboardToken('--idlm-brand-gold'", $dashboard);
});

idlm_test('guarded view renderer rejects traversal and renders the correct authentication state', static function(): void {
    assert_same('', IDLM_View::render('../interior-design-lead-manager.php'));
    $GLOBALS['idlm_test_logged_in'] = false;
    $markup = IDLM_View::render('access-restricted.php');
    assert_contains('idlm-access-restricted', $markup);
    assert_contains('Welcome back', $markup);
    assert_contains('idlm-loginform', $markup);
    assert_contains('idlm-login-shell', $markup);
    assert_contains('Forgot your password?', $markup);
    assert_contains('data-idlm-password-toggle', $markup);
    assert_contains('autocomplete="current-password"', $markup);
    $GLOBALS['idlm_test_logged_in'] = true;
    $markup = IDLM_View::render('access-restricted.php');
    assert_contains('Access Restricted', $markup);
});

idlm_test('CRM authentication remains email-only, scoped, and core-backed', static function(): void {
    $source = file_get_contents(dirname(__DIR__) . '/includes/class-auth.php');
    assert_contains("admin_post_nopriv_idlm_login", $source);
    assert_contains("admin_post_nopriv_idlm_request_password_reset", $source);
    assert_contains("admin_post_nopriv_idlm_reset_password", $source);
    assert_contains("get_user_by('email', \$email)", $source);
    assert_contains('wp_signon($credentials, is_ssl())', $source);
    assert_contains('retrieve_password($user->user_login)', $source);
    assert_contains('check_password_reset_key($key, $login)', $source);
    assert_contains('reset_password($user, $password)', $source);
    assert_false(false !== strpos($source, "add_filter('authenticate'"), 'CRM email-only login must not change global WordPress authentication.');
    $GLOBALS['idlm_test_logged_in'] = false;
    $_GET = array();
    $_SERVER['REQUEST_URI'] = '/crm/';
    $markup = IDLM_View::render('access-restricted.php');
    assert_contains('type="email"', $markup);
    assert_contains('name="idlm_email"', $markup);
    assert_contains('action" value="idlm_login', $markup);
    assert_contains('data-idlm-pending-label="Signing in…"', $markup);
    assert_false(false !== strpos($markup, 'Username or Email'), 'CRM login must not advertise username authentication.');
});

idlm_test('MCP CLI entry uses environment-driven paths and no Local-specific fallback', static function(): void {
    $source = file_get_contents(dirname(__DIR__) . '/mcp-server.php');
    assert_contains("getenv('IDLM_MCP_WP_LOAD')", $source);
    assert_contains("getenv('IDLM_MCP_ERROR_LOG')", $source);
    assert_contains("sys_get_temp_dir() . '/idlm-mcp-error.log'", $source);
    assert_false(false !== strpos($source, '/Users/'));
    assert_false(false !== strpos($source, "define('DB_HOST'"));
});

idlm_test('routing state installer declares an atomic keyed counter table', static function(): void {
    IDLM_Routing_State::install();
    assert_count_value(1, $GLOBALS['wpdb']->dbdelta);
    assert_contains('PRIMARY KEY  (state_key)', $GLOBALS['wpdb']->dbdelta[0]);
    assert_same('1.0.0', get_option('idlm_routing_schema_version'));
});

idlm_test('retention redacts nested credentials and contact PII', static function(): void {
    $redacted = IDLM_Data_Retention::redact(array('email' => 'person@example.test', 'nested' => array('token' => 'secret'), 'safe' => 'value'));
    assert_same('[REDACTED]', $redacted['email']);
    assert_same('[REDACTED]', $redacted['nested']['token']);
    assert_same('value', $redacted['safe']);
});

idlm_test('audience audit payloads are stored as fixed-size redacted summaries', static function(): void {
    $summary = IDLM_Data_Retention::summarize_audience_payload(array(
        'payload' => array('schema' => array('EMAIL'), 'data' => array(array('person@example.test'), array('other@example.test'))),
        'token' => 'secret',
    ));
    assert_same('summary', $summary['storage']);
    assert_same(2, $summary['item_count']);
    assert_true($summary['original_bytes'] > 0);
    assert_false(false !== strpos(wp_json_encode($summary), 'person@example.test'));
    assert_false(false !== strpos(wp_json_encode($summary), 'secret'));
});

idlm_test('diagnostic audience and CAPI logs expire after seven days', static function(): void {
    $source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-data-retention.php');

    foreach (array('DIAGNOSTIC_LOG_RETENTION_VERSION', 'DIAGNOSTIC_LOG_RETENTION_DAYS = 7', "'idlm_diagnostic_log_retention_version'", "'audience_sync', 'capi_sync', 'capi_queue', 'capi_abort'", 'idlm_lead_activity_log', 'idlm_capi_logs', 'diagnostic_log_retention') as $contract) {
        assert_contains($contract, $source);
    }
});

idlm_test('transactional deletion removes a lead and every core dependent row', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture()));
    $GLOBALS['wpdb']->seed('idlm_tasks', array(array('id' => 1, 'lead_id' => 1)));
    $GLOBALS['wpdb']->seed('idlm_lead_activity_log', array(array('id' => 1, 'lead_id' => 1)));
    assert_same(1, IDLM_Lead_Deletion_Service::delete(1));
    assert_count_value(0, $GLOBALS['wpdb']->rows('idlm_leads'));
    assert_count_value(0, $GLOBALS['wpdb']->rows('idlm_tasks'));
    assert_count_value(0, $GLOBALS['wpdb']->rows('idlm_lead_activity_log'));
    assert_true(in_array('COMMIT', $GLOBALS['wpdb']->queries, true));
});

idlm_test('quotation print source is semantic and has no DOM rewrite script', static function(): void {
    $source = file_get_contents(dirname(__DIR__) . '/includes/class-quotation-renderer.php');
    assert_contains('idlm-print-document-head', $source);
    assert_contains('Section-wise subtotal before discount', $source);
    assert_contains('idlm-print-additional-notes', $source);
    assert_false(false !== strpos($source, 'document.querySelector'));
    assert_false(false !== strpos($source, '<script'));
});

idlm_test('quotation renderer accepts valid list dates and rejects malformed dates', static function(): void {
    assert_same('2026-08-29', invoke_private('IDLM_Quotation_Renderer', 'clean_date', array('2026-08-29')));
    assert_same(null, invoke_private('IDLM_Quotation_Renderer', 'clean_date', array('2026-02-30')));
    assert_same(null, invoke_private('IDLM_Quotation_Renderer', 'clean_date', array('not-a-date')));
});

idlm_test('performance dataset profiles match the approved Phase 0 scale envelope', static function(): void {
    $profiles = IDLM_Performance_Dataset_Generator::profiles();
    assert_same(1000, $profiles['small']['leads']);
    assert_same(250000, $profiles['maximum']['leads']);
    assert_same(2000000, $profiles['maximum']['activities']);
    assert_same(500000, $profiles['maximum']['tasks']);
    assert_same(500000, $profiles['maximum']['touchpoints']);
    assert_same(1000000, $profiles['maximum']['logs']);
    assert_same(100000, $profiles['maximum']['quotes']);
});

idlm_test('performance dataset rows are deterministic and use reserved synthetic identifiers', static function(): void {
    $run_id = IDLM_Performance_Dataset_Generator::run_id('small', 'Baseline_01');
    $first = IDLM_Performance_Dataset_Generator::lead($run_id, 42, 7);
    $second = IDLM_Performance_Dataset_Generator::lead($run_id, 42, 7);
    assert_same($first, $second);
    assert_same('small-baseline_01', $run_id);
    assert_same('perf+small-baseline_01-000042@performance.invalid', $first['email']);
    assert_contains('idlm-perf:small-baseline_01', $first['project_name']);
    assert_same(IDLM_Performance_Dataset_Generator::SOURCE, $first['source']);
    assert_contains('idlm-perf:small-baseline_01', IDLM_Performance_Dataset_Generator::activity($run_id, 42, 1)['details']);
    assert_contains('small-baseline_01', IDLM_Performance_Dataset_Generator::quote($run_id, 42, 1)['quote_number']);
});

idlm_test('performance dataset command retains explicit production and confirmation gates', static function(): void {
    $source = file_get_contents(__DIR__ . '/performance/seed-dataset.php');
    assert_contains("array('local', 'development', 'staging')", $source);
    assert_contains('seed-idlm-performance-data', $source);
    assert_contains('reset-idlm-performance-data', $source);
    assert_contains('The default mode prints the deterministic plan', $source);
});

idlm_test('query-plan manifest covers critical global and restricted read shapes', static function(): void {
    $scenarios = require __DIR__ . '/performance/query-plan-scenarios.php';
    assert_count_value(10, $scenarios);
    $ids = array_column($scenarios, 'id');
    assert_true(in_array('dashboard-global-period-volume', $ids, true));
    assert_true(in_array('dashboard-restricted-period-volume', $ids, true));
    assert_true(in_array('lead-list-global-default-order', $ids, true));
    assert_true(in_array('lead-list-restricted-default-order', $ids, true));
    assert_true(in_array('qualified-handoff-queue', $ids, true));
    assert_true(in_array('quotation-list-default-order', $ids, true));
    foreach ($scenarios as $scenario) {
        assert_true(!empty($scenario['scenario']));
        assert_true(!empty($scenario['tables']));
        assert_true(0 === strpos(trim($scenario['sql']), 'SELECT '));
    }
});

idlm_test('query-plan capture is read-only, production-refusing, and aggregate-only', static function(): void {
    $source = file_get_contents(__DIR__ . '/performance/capture-query-plans.php');
    assert_contains("array('local', 'development', 'staging')", $source);
    assert_contains("'EXPLAIN ' . \$sql", $source);
    assert_contains('SHOW INDEX FROM', $source);
    assert_contains('normalize_ref', $source);
    assert_contains("'raw_sql_included' => false", $source);
    assert_contains("'parameter_values_included' => false", $source);
    assert_false(false !== strpos($source, 'EXPLAIN ANALYZE'));
    assert_false(false !== strpos($source, 'INSERT INTO'));
    assert_false(false !== strpos($source, 'UPDATE {{'));
    assert_false(false !== strpos($source, 'DELETE FROM'));
});

idlm_test('Phase 8 load manifest covers bounded read-only authenticated surfaces', static function(): void {
    $scenarios = require __DIR__ . '/performance/qualification-scenarios.php';
    assert_count_value(3, $scenarios);
    assert_same(
        array('dashboard-period-refresh', 'lead-list-filter-sort', 'kanban-stage-load'),
        array_column($scenarios, 'id')
    );
    assert_same(
        array('idlm_get_dashboard_stats', 'idlm_get_leads_table', 'idlm_get_kanban_leads'),
        array_column($scenarios, 'action')
    );
    foreach ($scenarios as $scenario) {
        assert_true($scenario['read_only']);
        assert_same('admin-ajax.php', $scenario['transport']);
        assert_true(!empty($scenario['roles']));
        assert_true(isset($scenario['provisional_thresholds']['error_rate_percent']));
    }
});

idlm_test('Phase 8 result recorder emits deterministic aggregate percentiles and provisional evidence', static function(): void {
    $samples = array();
    foreach (array(10, 20, 30, 40, 50, 60, 70, 80, 90, 100) as $index => $duration) {
        $samples[] = array(
            'duration_ms' => $duration,
            'response_bytes' => 100 + $index,
            'outcome' => 9 === $index ? 'http_error' : 'success',
        );
    }
    $summary = IDLM_Performance_Qualification_Result::summarize($samples, array(
        'p95_ms' => 150,
        'p99_ms' => 150,
        'error_rate_percent' => 11,
    ));
    assert_same(10, $summary['request_count']);
    assert_same(9, $summary['success_count']);
    assert_same(10.0, $summary['error_rate_percent']);
    assert_same(50.0, $summary['latency_ms']['p50']);
    assert_same(100.0, $summary['latency_ms']['p95']);
    assert_same(100.0, $summary['latency_ms']['p99']);
    assert_true($summary['provisional_thresholds']['within']);
    assert_false($summary['provisional_thresholds']['signed']);
    assert_same('evidence_only', $summary['provisional_thresholds']['qualification_status']);
});

idlm_test('Phase 8 runner refuses production and excludes authentication and response material', static function(): void {
    $source = file_get_contents(__DIR__ . '/performance/run-load-qualification.php');
    $matrix = require __DIR__ . '/performance/qualification-matrix.php';
    assert_contains("array('local', 'development', 'staging')", $source);
    assert_contains('run-idlm-load-qualification', $source);
    assert_contains('wp_generate_auth_cookie', $source);
    assert_contains("wp_create_nonce('idlm_admin_nonce')", $source);
    assert_contains("WP_Session_Tokens::get_instance(\$user_id)->destroy(\$benchmark_session_token)", $source);
    assert_contains('curl_multi_init', $source);
    assert_contains('Qualification execution is limited to the read-only action allowlist.', $source);
    assert_contains('CURLOPT_FOLLOWLOCATION => false', $source);
    assert_contains('CURLOPT_FILE => $temporary', $source);
    assert_contains("'identity_included' => false", $source);
    assert_contains("'response bodies'", $source);
    assert_false(false !== strpos($source, 'CURLOPT_SSL_VERIFYPEER => false'));
    assert_same('not_executed', $matrix['execution_status']);
    assert_true(in_array('unauthorized', $matrix['authorization_scopes'], true));
    assert_true(in_array('database_and_media_restore', $matrix['recovery_cases'], true));
    assert_true(in_array('no_cross_user_access', $matrix['integrity_invariants'], true));
});

idlm_test('horizontal readiness blocks incomplete multi-node infrastructure without exposing values', static function(): void {
    $report = IDLM_Horizontal_Readiness::report(array(
        'multi_node' => true,
        'shared_database' => true,
        'shared_media' => false,
        'shared_configuration' => true,
        'persistent_cache' => false,
        'action_scheduler' => true,
        'managed_cron' => false,
        'streamed_exports' => true,
    ));

    assert_same('multi_node', $report['mode']);
    assert_same('blocked', $report['status']);
    assert_same(3, $report['blocking_count']);
    assert_false(false !== strpos(wp_json_encode($report), 'DB_HOST'));
    assert_false(false !== strpos(wp_json_encode($report), IDLM_PATH));
});

idlm_test('horizontal readiness passes complete infrastructure and renders aggregate admin health state', static function(): void {
    $report = IDLM_Horizontal_Readiness::report(array(
        'multi_node' => true,
        'shared_database' => true,
        'shared_media' => true,
        'shared_configuration' => true,
        'persistent_cache' => true,
        'action_scheduler' => true,
        'managed_cron' => true,
        'streamed_exports' => true,
    ));

    assert_same('ready', $report['status']);
    assert_same(0, $report['blocking_count']);
    $markup = IDLM_View::render('admin/settings/health.php', array(
        'active_tab' => 'health',
        'horizontal_readiness' => $report,
    ));
    assert_contains('Horizontal Deployment Readiness', $markup);
    assert_contains('All declared multi-node infrastructure gates are ready.', $markup);
    assert_count_value(7, array_filter($report['checks'], static function(array $check): bool {
        return 'ready' === $check['status'];
    }));
});

idlm_test('operational health applies queue and recent-fault thresholds without exposing sensitive values', static function(): void {
    $report = IDLM_Operational_Health::report(array(
        'database_ready' => true,
        'core_schema_current' => true,
        'quotation_schema_current' => true,
        'scheduler_backend' => 'action_scheduler',
        'recurring_jobs_scheduled' => true,
        'retention_scheduled' => true,
        'secrets_ready' => true,
        'uploads_ready' => true,
        'cache_backend' => 'persistent',
        'queue_observable' => true,
        'queue_pending' => 12,
        'queue_failed' => 1,
        'queue_oldest_age_seconds' => 301,
        'telemetry_errors_24h' => 0,
        'integration_failures_24h' => 1,
        'credential' => 'must-not-appear',
        'path' => IDLM_PATH,
    ));

    assert_same('warning', $report['status']);
    assert_same(12, $report['signals']['queue_pending']);
    assert_same(1, $report['signals']['integration_failures_24h']);
    $encoded = wp_json_encode($report);
    assert_false(false !== strpos($encoded, 'must-not-appear'));
    assert_false(false !== strpos($encoded, IDLM_PATH));

    $source = file_get_contents(dirname(__DIR__) . '/includes/class-operational-health.php');
    assert_contains("status = 'pending' AND scheduled_date_gmt <= UTC_TIMESTAMP()", $source);
    assert_contains("last_attempt_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)", $source);
});

idlm_test('operational health marks critical gates and renders the administrator summary', static function(): void {
    $report = IDLM_Operational_Health::report(array(
        'database_ready' => true,
        'core_schema_current' => false,
        'quotation_schema_current' => true,
        'scheduler_backend' => 'action_scheduler',
        'recurring_jobs_scheduled' => true,
        'retention_scheduled' => true,
        'secrets_ready' => false,
        'secret_missing_count' => 2,
        'uploads_ready' => true,
        'cache_backend' => 'database',
        'queue_observable' => true,
        'queue_pending' => 40,
        'queue_failed' => 10,
        'queue_oldest_age_seconds' => 901,
        'telemetry_errors_24h' => 2,
        'integration_failures_24h' => 8,
    ));

    assert_same('critical', $report['status']);
    assert_true($report['counts']['critical'] >= 3);
    $markup = IDLM_View::render('admin/settings/health.php', array(
        'active_tab' => 'health',
        'operational_health' => $report,
    ));
    assert_contains('Operational Health', $markup);
    assert_contains('40 pending / 10 failed (24h) / 15m oldest', $markup);
    assert_contains('2 incomplete', $markup);
});

require_once __DIR__ . '/admin-refactor-contracts.php';
require_once __DIR__ . '/ui-regression-contracts.php';
require_once __DIR__ . '/release-contracts.php';
require_once __DIR__ . '/high-priority-contracts.php';
require_once __DIR__ . '/dashboard-contracts.php';

$passed = 0;
$failed = 0;
$failures = array();

foreach ($tests as $name => $test) {
    idlm_test_reset_environment();
    try {
        $test();
        $passed++;
        echo "PASS {$name}\n";
    } catch (Throwable $error) {
        $failed++;
        $failures[] = array($name, $error);
        echo "FAIL {$name}\n";
        echo '  ' . $error->getMessage() . "\n";
    }
}

echo "\n{$passed} passed, {$failed} failed\n";

if ($failed) {
    exit(1);
}
