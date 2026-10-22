<?php
/** Failure propagation and marker contracts; native tests prove physical DDL. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

idlm_test('schema migration lock contention rejects work before DDL or markers', static function (): void {
    $GLOBALS['wpdb']->deny_schema_lock = true;
    $result = IDLM_DB::init();
    assert_true(is_wp_error($result));
    assert_same('idlm_schema_busy', $result->get_error_code());
    assert_count_value(0, $GLOBALS['wpdb']->dbdelta);
    assert_same(false, get_option('idlm_schema_version'));
});

idlm_test('unsupported core engines preserve pending marker until operator repair', static function (): void {
    $GLOBALS['idlm_test_options']['idlm_schema_version'] = '1.3.5';
    $GLOBALS['wpdb']->schema_engines['wp_idlm_tasks'] = 'MyISAM';
    assert_true(is_wp_error(IDLM_DB::init()));
    assert_same('1.3.5', get_option('idlm_schema_version'));
    $GLOBALS['wpdb']->schema_engines = array();
    assert_same(true, IDLM_DB::init());
    assert_same(IDLM_DB_VERSION, get_option('idlm_schema_version'));
});

idlm_test('failed durable option write cannot acknowledge schema completion', static function (): void {
    $GLOBALS['idlm_test_options']['idlm_schema_version'] = '1.3.5';
    $GLOBALS['idlm_test_option_write_failures'] = array('idlm_schema_version');
    assert_true(is_wp_error(IDLM_DB::init()));
    assert_same('1.3.5', get_option('idlm_schema_version'));
    $GLOBALS['idlm_test_option_write_failures'] = array();
    assert_same(true, IDLM_DB::init());
});

idlm_test('late workflow backfill failure retains completion marker and retry converges', static function (): void {
    $GLOBALS['idlm_test_options']['idlm_schema_version'] = IDLM_DB_VERSION;
    $GLOBALS['wpdb']->query_fail_contains = 'DELETE t FROM wp_idlm_tasks';
    assert_true(is_wp_error(IDLM_DB::init()));
    assert_same(false, get_option('idlm_workflow_integrity_version'));
    assert_same('1.0.1', get_option('idlm_duplicate_quality_version'));
    $GLOBALS['wpdb']->query_fail_contains = '';
    assert_same(true, IDLM_DB::init());
    assert_same('1.0.1', get_option('idlm_workflow_integrity_version'));
    $before = count($GLOBALS['wpdb']->queries);
    assert_same(true, IDLM_DB::init());
    assert_same($before, count($GLOBALS['wpdb']->queries));
});

idlm_test('routing installation requires verified transactional schema before publishing its version', static function (): void {
    $GLOBALS['wpdb']->schema_engines['wp_idlm_routing_state'] = 'MyISAM';
    assert_true(is_wp_error(IDLM_Routing_State::install()));
    assert_same(false, get_option('idlm_routing_schema_version'));
    $GLOBALS['wpdb']->schema_engines = array();
    assert_same(true, IDLM_Routing_State::install());
    assert_same(IDLM_Routing_State::VERSION, get_option('idlm_routing_schema_version'));
});

idlm_test('manual task cleanup reports persistence failure without a false success response', static function (): void {
    idlm_test_login(1, array('administrator'), array('manage_options'));
    $GLOBALS['wpdb']->query_fail_contains = 'LEFT JOIN wp_users';
    try { IDLM_Task_Queue_Service::ajax_cleanup_stale(); }
    catch (IDLM_Test_Response $response) { assert_false($response->success); assert_contains('Unable to clean up', $response->data['message']); return; }
    throw new RuntimeException('Cleanup did not return a response.');
});

idlm_test('newer core and routing schema markers refuse incompatible downgrade work', static function (): void {
    $GLOBALS['idlm_test_options']['idlm_schema_version'] = '99.0.0';
    $GLOBALS['idlm_test_options']['idlm_routing_schema_version'] = '99.0.0';
    assert_same('idlm_schema_future_version', IDLM_DB::init()->get_error_code());
    assert_same('idlm_schema_future_routing_version', IDLM_Routing_State::install()->get_error_code());
    assert_count_value(0, $GLOBALS['wpdb']->dbdelta);
    assert_same('99.0.0', get_option('idlm_schema_version'));
    assert_same('99.0.0', get_option('idlm_routing_schema_version'));
});
