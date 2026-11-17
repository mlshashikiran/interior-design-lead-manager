<?php
/** An unqualified lead with pre-sales stays in pre-sales until it is qualified. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

function idlm_presales_hold_users(): void {
    foreach (array(8 => array('idlm_pre_sales'), 9 => array('idlm_pre_sales'), 3 => array('sales_executive'), 4 => array('administrator')) as $id => $roles) {
        $GLOBALS['idlm_test_users'][$id] = (object) array('ID' => $id, 'roles' => $roles, 'caps' => array(), 'display_name' => 'User ' . $id);
    }
}

idlm_test('PRESALES-HOLD pure rule allows only pre-sales moves until qualified', static function(): void {
    $pre = array(8, 9);
    $error = IDLM_Lead_Lifecycle_Service::PRESALES_HOLD_ERROR;
    assert_same($error, IDLM_Lead_Lifecycle_Service::presales_hold_error(8, 3, false, $pre), 'Pre-sales to sales before qualification is blocked.');
    assert_same($error, IDLM_Lead_Lifecycle_Service::presales_hold_error(8, 0, false, $pre), 'Unassigning a pre-sales lead is blocked.');
    assert_same('', IDLM_Lead_Lifecycle_Service::presales_hold_error(8, 9, false, $pre), 'Another pre-sales person may take it.');
    assert_same('', IDLM_Lead_Lifecycle_Service::presales_hold_error(8, 3, true, $pre), 'A qualified lead may go to sales.');
    assert_same('', IDLM_Lead_Lifecycle_Service::presales_hold_error(3, 4, false, $pre), 'Leads outside pre-sales are not held.');
    assert_same('', IDLM_Lead_Lifecycle_Service::presales_hold_error(0, 3, false, $pre), 'Unassigned leads are not held.');
    assert_same('', IDLM_Lead_Lifecycle_Service::presales_hold_error(8, 8, false, $pre), 'No owner change is not held.');
});

idlm_test('PRESALES-HOLD manual reassignment of an unqualified pre-sales lead fails without writing', static function(): void {
    idlm_presales_hold_users();
    idlm_test_login(4, array('administrator'), array('manage_options'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 8, 'lead_quality' => '', 'stage' => 'Contacted'))));
    $before = $GLOBALS['wpdb']->tables;
    assert_false(IDLM_DB::update_lead(1, array('assigned_rep_id' => 3)), 'An administrator cannot move it to sales.');
    assert_same(IDLM_Lead_Lifecycle_Service::PRESALES_HOLD_ERROR, IDLM_DB::get_last_validation_error());
    assert_same($before, $GLOBALS['wpdb']->tables, 'Nothing is written.');
    assert_false(IDLM_DB::update_lead(1, array('assigned_rep_id' => 0)), 'It cannot be unassigned either.');
    assert_true(IDLM_DB::update_lead(1, array('assigned_rep_id' => 9)), 'Another pre-sales person can take it.');
    assert_same(9, (int) $GLOBALS['wpdb']->rows('idlm_leads')[0]['assigned_rep_id']);
});

idlm_test('PRESALES-HOLD a qualified pre-sales lead can be handed to sales', static function(): void {
    idlm_presales_hold_users();
    idlm_test_login(4, array('administrator'), array('manage_options'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 8, 'lead_quality' => 'Qualified', 'stage' => 'Qualified'))));
    assert_true(IDLM_DB::update_lead(1, array('assigned_rep_id' => 3)), IDLM_DB::get_last_validation_error());
    assert_same(3, (int) $GLOBALS['wpdb']->rows('idlm_leads')[0]['assigned_rep_id']);
});
