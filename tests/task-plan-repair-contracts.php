<?php
/** Six-cycle plan preservation and retry contracts. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

function idlm_task_plan_fixture($missing = 0) {
    $db = $GLOBALS['wpdb'];
    $db->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 7))));
    $rows = array();
    for ($cycle = 1; $cycle <= 6; $cycle++) {
        if ($cycle === $missing) { continue; }
        $rows[] = array('id' => $cycle, 'lead_id' => 1, 'assigned_rep_id' => 7, 'title' => 'Qualification cycle ' . $cycle . ': historical target',
            'task_type' => 'Qualification', 'status' => $cycle === 1 ? 'completed' : 'pending', 'due_date' => '2026-08-2' . $cycle . ' 18:00:00',
            'created_at' => '2026-08-20 09:00:00', 'updated_at' => '2026-08-20 09:00:00', 'completed_at' => $cycle === 1 ? '2026-08-21 18:00:00' : null);
    }
    $db->seed('idlm_tasks', $rows);
    return $rows;
}

idlm_test('every historical missing qualification cycle is filled once without changing task history', static function(): void {
    for ($missing = 1; $missing <= 6; $missing++) {
        idlm_test_reset_environment();
        $before = idlm_task_plan_fixture($missing);
        assert_true(IDLM_DB::create_presales_followup_tasks(1, 99));
        $rows = $GLOBALS['wpdb']->rows('idlm_tasks'); assert_count_value(6, $rows);
        foreach ($before as $old) {
            $saved = array_values(array_filter($rows, static fn($r) => $r['id'] === $old['id']))[0];
            unset($saved['qualification_cycle']); assert_same($old, $saved);
        }
        $slots = array_map(static fn($r) => (int) $r['qualification_cycle'], $rows); sort($slots); assert_same(range(1, 6), $slots);
        $again = $GLOBALS['wpdb']->tables; assert_true(IDLM_DB::create_presales_followup_tasks(1, 99)); assert_same($again, $GLOBALS['wpdb']->tables);
        assert_same(7, (int) $rows[5]['assigned_rep_id']);
    }
});

idlm_test('repair insert and activity failures roll back legacy identities and remain retryable', static function(): void {
    foreach (array('idlm_tasks', 'idlm_lead_activity_log', 'idlm_lead_outbox') as $table) {
        idlm_test_reset_environment(); idlm_task_plan_fixture(3); $before = $GLOBALS['wpdb']->tables;
        $GLOBALS['wpdb']->fail_next_write('insert', $table);
        assert_false(IDLM_DB::create_presales_followup_tasks(1, 7)); assert_same($before, $GLOBALS['wpdb']->tables);
        assert_true(IDLM_DB::create_presales_followup_tasks(1, 7)); assert_count_value(6, $GLOBALS['wpdb']->rows('idlm_tasks'));
    }
});

idlm_test('ambiguous legacy qualification history is preserved and refuses speculative repair', static function(): void {
    idlm_task_plan_fixture(3);
    $GLOBALS['wpdb']->tables['wp_idlm_tasks'][0]['title'] = 'Unknown historical qualification task';
    $before = $GLOBALS['wpdb']->tables;
    assert_false(IDLM_DB::create_presales_followup_tasks(1, 7)); assert_same($before, $GLOBALS['wpdb']->tables);
});

idlm_test('closed and duplicate leads never acquire missing or reopened qualification work', static function(): void {
    foreach (array(array('stage'=>'Lost'),array('stage'=>'Won'),array('lead_quality'=>'Qualified'),array('lead_quality'=>'Unqualified'),array('is_duplicate'=>1)) as $state) {
        idlm_test_reset_environment(); idlm_task_plan_fixture(3);
        $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array_merge(array('assigned_rep_id'=>7),$state))));
        $before = $GLOBALS['wpdb']->tables;
        assert_true(IDLM_DB::create_presales_followup_tasks(1, 7)); assert_true(IDLM_DB::sync_presales_task_status(1)); assert_same($before, $GLOBALS['wpdb']->tables);
    }
});

idlm_test('stable cycle identity is independent of mutable task labels and dates', static function(): void {
    idlm_task_plan_fixture(3); assert_true(IDLM_DB::create_presales_followup_tasks(1,7));
    foreach ($GLOBALS['wpdb']->tables['wp_idlm_tasks'] as &$row) { $row['title'] = 'Custom display label'; $row['due_date'] = '2027-01-01 18:00:00'; } unset($row);
    $before = $GLOBALS['wpdb']->tables; assert_true(IDLM_DB::create_presales_followup_tasks(1,7)); assert_same($before, $GLOBALS['wpdb']->tables);
});
