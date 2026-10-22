<?php
/** Required core write and transaction ownership regressions. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

foreach (array('idlm_leads'=>'update', 'idlm_lead_activity_log'=>'insert', 'idlm_lead_state_events'=>'insert', 'idlm_lead_outbox'=>'insert', 'idlm_tasks'=>'update') as $table => $operation) {
    idlm_test('lead transition rolls back a required ' . $table . ' failure without hooks', static function() use ($table, $operation): void {
        $db = $GLOBALS['wpdb']; $db->seed('idlm_leads', array(lead_fixture()));
        $before = $db->tables; $generation = IDLM_Read_Model_Cache::generation();
        if ('idlm_tasks' === $table) { $db->query_fail_contains = 'UPDATE wp_idlm_tasks'; }
        else { $db->fail_next_write($operation, $table); }
        assert_false(IDLM_DB::update_lead(1, array('stage'=>'Qualified')));
        assert_same($before, $db->tables);
        assert_count_value(0, $GLOBALS['idlm_test_actions']);
        assert_same($generation, IDLM_Read_Model_Cache::generation());
        $db->query_fail_contains = '';
        assert_true(IDLM_DB::update_lead(1, array('stage'=>'Qualified')));
        assert_count_value(1, $db->rows('idlm_lead_state_events'));
        assert_count_value(1, $GLOBALS['idlm_test_actions']);
    });
}

idlm_test('inbound touchpoint failure rolls back both new capture and attribution merge', static function(): void {
    $db = $GLOBALS['wpdb'];
    $db->fail_next_write('insert', 'idlm_lead_touchpoints');
    assert_false(IDLM_DB::upsert_inbound_lead(array('email'=>'rollback@example.test'), 'website'));
    assert_count_value(0, $db->rows('idlm_leads')); assert_count_value(0, $GLOBALS['idlm_test_actions']);
    $result = IDLM_DB::upsert_inbound_lead(array('email'=>'rollback@example.test'), 'website');
    assert_true($result['created']);
    $before = $db->tables; $actions = $GLOBALS['idlm_test_actions'];
    $db->fail_next_write('insert', 'idlm_lead_touchpoints');
    assert_false(IDLM_DB::upsert_inbound_lead(array('email'=>'rollback@example.test','gclid'=>'synthetic-click'), 'website'));
    assert_same($before, $db->tables); assert_same($actions, $GLOBALS['idlm_test_actions']);
});

idlm_test('failed core commit leaves no lead, event, activity, intent or success hook', static function(): void {
    $db = $GLOBALS['wpdb']; $db->query_fail_contains = 'COMMIT';
    assert_false(IDLM_DB::insert_lead(array('email'=>'commit@example.test')));
    foreach (array('idlm_leads','idlm_lead_activity_log','idlm_lead_state_events','idlm_lead_outbox') as $table) { assert_count_value(0, $db->rows($table)); }
    assert_count_value(0, $GLOBALS['idlm_test_actions']);
    $db->query_fail_contains = ''; assert_true(IDLM_DB::insert_lead(array('email'=>'commit@example.test')) > 0);
});

idlm_test('lead writes refuse an unrelated active transaction without committing or rolling it back', static function(): void {
    $db = $GLOBALS['wpdb']; $db->query('START TRANSACTION');
    $db->insert('wp_idlm_leads', lead_fixture());
    assert_false(IDLM_DB::update_lead(1, array('first_name'=>'Blocked')));
    assert_same('Asha', $db->rows('idlm_leads')[0]['first_name']);
    assert_false(in_array('COMMIT', $db->queries, true)); assert_false(in_array('ROLLBACK', $db->queries, true));
    $db->query('ROLLBACK'); assert_count_value(0, $db->rows('idlm_leads'));
});

idlm_test('lead writes refuse nontransactional required tables despite completed schema markers', static function(): void {
    $db = $GLOBALS['wpdb']; $db->schema_engines['wp_idlm_lead_state_events'] = 'MyISAM';
    assert_false(IDLM_DB::insert_lead(array('email'=>'engine@example.test')));
    assert_count_value(0, $db->rows('idlm_leads')); assert_count_value(0, $GLOBALS['idlm_test_actions']);
    assert_contains('InnoDB', IDLM_DB::get_last_validation_error());
});

idlm_test('outbox rejects dispatch inside an aggregate and survives failed post-commit acknowledgement', static function(): void {
    $db = $GLOBALS['wpdb']; $db->seed('idlm_leads', array(lead_fixture()));
    $id = 0; $db->fail_next_write('delete', 'idlm_lead_outbox');
    $result = IDLM_Lead_Transaction::run(static function() use (&$id): bool {
        IDLM_Lead_Transaction::defer('idlm_lead_stage_converted', 1, 'New', (object) array('stage'=>''));
        $id = (int) $GLOBALS['wpdb']->insert_id;
        assert_false(IDLM_Lead_Outbox::dispatch($id)); assert_count_value(0, $GLOBALS['idlm_test_actions']);
        return true;
    });
    assert_same(true, $result); assert_count_value(1, $db->rows('idlm_lead_outbox'));
    assert_count_value(1, $GLOBALS['idlm_test_actions']);
});
