<?php
/** Sales sends a qualified lead back to pre-sales; it returns to the same rep once qualified again. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

function idlm_send_back_users(): void {
    foreach (array(8 => array('idlm_pre_sales'), 9 => array('idlm_pre_sales'), 3 => array('sales_executive'), 5 => array('sales_executive'), 4 => array('administrator')) as $id => $roles) {
        $GLOBALS['idlm_test_users'][$id] = (object) array('ID' => $id, 'roles' => $roles, 'caps' => array(), 'display_name' => 'User ' . $id);
    }
}

function idlm_send_back_lead(array $overrides = array()): void {
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array_merge(array(
        'assigned_rep_id' => 3, 'lead_quality' => 'Qualified', 'stage' => 'Sales Connected',
        'qualified_at' => '2026-08-20 10:00:00', 'qualified_by_user_id' => 8, 'intake_owner_id' => 8,
        'handoff_status' => 'manually_assigned', 'handoff_method' => 'manual_manager', 'handoff_at' => '2026-08-20 11:00:00',
        'floor_plan_received' => 1, 'property_type' => '3 BHK', 'property_location' => 'JP Nagar',
    ), $overrides))));
    $GLOBALS['wpdb']->seed('idlm_tasks', array(array('id' => 1, 'lead_id' => 1, 'assigned_rep_id' => 3, 'title' => 'Contact newly assigned qualified lead', 'task_type' => 'Call', 'priority' => 'High', 'status' => 'pending', 'due_date' => '2026-08-21 10:00:00')));
}

idlm_test('SENDBACK pure checks name who may send back what', static function(): void {
    $pre = array(8, 9);
    $lead = (object) array('assigned_rep_id' => 3, 'lead_quality' => 'Qualified', 'stage' => 'Sales Connected', 'is_duplicate' => 0);
    $ok = 'Customer budget is only 3 lakh';
    assert_same('', IDLM_Lead_Lifecycle_Service::send_back_error($lead, 3, false, 'budget_mismatch', $ok, $pre), 'The owner can send it back.');
    assert_same('', IDLM_Lead_Lifecycle_Service::send_back_error($lead, 4, true, 'budget_mismatch', $ok, $pre), 'A manager can send it back.');
    assert_true('' !== IDLM_Lead_Lifecycle_Service::send_back_error($lead, 5, false, 'budget_mismatch', $ok, $pre), 'Another rep cannot.');
    assert_true('' !== IDLM_Lead_Lifecycle_Service::send_back_error($lead, 3, false, 'nonsense', $ok, $pre), 'A reason from the list is required.');
    assert_true('' !== IDLM_Lead_Lifecycle_Service::send_back_error($lead, 3, false, 'other', 'short', $pre), 'A note is required.');
    foreach (array(array('stage', 'Won'), array('stage', 'Lost'), array('lead_quality', ''), array('lead_quality', 'Unqualified'), array('assigned_rep_id', 8), array('is_duplicate', 1)) as $change) {
        $copy = clone $lead; $copy->{$change[0]} = $change[1];
        assert_true('' !== IDLM_Lead_Lifecycle_Service::send_back_error($copy, 4, true, 'other', $ok, $pre), $change[0] . '=' . $change[1] . ' cannot be sent back.');
    }
});

idlm_test('SENDBACK returns the lead to its qualifier unqualified with a call task and closes the rep tasks', static function(): void {
    idlm_send_back_users();
    idlm_test_login(3, array('sales_executive'), array('idlm_access_leads'));
    idlm_send_back_lead();
    assert_true(IDLM_Lead_Lifecycle_Service::send_back_to_presales(1, 'budget_mismatch', 'Customer budget is only 3 lakh'), IDLM_DB::get_last_validation_error());
    $lead = $GLOBALS['wpdb']->rows('idlm_leads')[0];
    assert_same(8, (int) $lead['assigned_rep_id'], 'Back with the person who qualified it.');
    assert_same('', (string) $lead['lead_quality']);
    assert_same('Contacted', $lead['stage']);
    assert_same('sent_back', $lead['handoff_status']);
    assert_same(null, $lead['qualified_at']);
    assert_same(0, (int) $lead['qualified_by_user_id']);
    $tasks = $GLOBALS['wpdb']->rows('idlm_tasks');
    assert_same('completed', $tasks[0]['status'], "The rep's open task closes.");
    $new = array_values(array_filter($tasks, static fn($t) => 'pending' === $t['status']));
    assert_same(1, count($new));
    assert_same(8, (int) $new[0]['assigned_rep_id']);
    assert_same('Sent back by User 3: Budget does not match', $new[0]['title']);
    $events = array_values(array_filter($GLOBALS['wpdb']->rows('idlm_lead_state_events'), static fn($e) => 'sent_back' === $e['event_origin']));
    assert_same(2, count($events), 'Stage and owner changes are reported as sent back.');
    $activity = array_values(array_filter($GLOBALS['wpdb']->rows('idlm_lead_activity_log'), static fn($a) => 'sent_back' === $a['activity_type']));
    assert_contains('Customer budget is only 3 lakh', $activity[0]['details']);
    assert_same('', IDLM_Admin_Collection_Reads::sent_back_badge((object) $lead) === '' ? 'missing' : '', 'The row shows the Sent back badge.');
});

idlm_test('SENDBACK the lead stays in pre-sales until qualified and then returns to the same rep', static function(): void {
    idlm_send_back_users();
    idlm_test_login(4, array('administrator'), array('manage_options'));
    idlm_send_back_lead();
    assert_true(IDLM_Lead_Lifecycle_Service::send_back_to_presales(1, 'wrong_details', 'Location is Whitefield, not JP Nagar'), IDLM_DB::get_last_validation_error());
    assert_false(IDLM_DB::update_lead(1, array('assigned_rep_id' => 5)), 'The pre-sales hold applies.');
    idlm_test_login(8, array('idlm_pre_sales'), array('idlm_access_leads'));
    assert_true(IDLM_DB::update_lead(1, array('lead_quality' => 'Qualified')), IDLM_DB::get_last_validation_error());
    $lead = $GLOBALS['wpdb']->rows('idlm_leads')[0];
    assert_same(3, (int) $lead['assigned_rep_id'], 'Back to the rep who sent it back.');
    assert_same('returned_after_send_back', $lead['handoff_method']);
    assert_same('manually_assigned', $lead['handoff_status']);
    assert_same('', IDLM_Admin_Collection_Reads::sent_back_badge((object) $lead));
});

idlm_test('SENDBACK refuses without changing anything when the rules fail', static function(): void {
    idlm_send_back_users();
    idlm_test_login(5, array('sales_executive'), array('idlm_access_leads'));
    idlm_send_back_lead();
    $before = $GLOBALS['wpdb']->tables;
    assert_false(IDLM_Lead_Lifecycle_Service::send_back_to_presales(1, 'budget_mismatch', 'Customer budget is only 3 lakh'));
    assert_same('Only the lead owner or a manager can send a lead back to pre-sales.', IDLM_DB::get_last_validation_error());
    assert_same($before, $GLOBALS['wpdb']->tables);
});
