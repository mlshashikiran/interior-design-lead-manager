<?php
/** Active workload admission behavior. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }
function idlm_capacity_fast_fixture(array $rows): void {
    idlm_eligible_user(10,array('sales_executive'));update_option('idlm_max_active_leads_per_rep',1);
    $GLOBALS['wpdb']->seed('idlm_leads',array_map(static fn($r)=>lead_fixture($r),$rows));
}
idlm_test('capacity counts active qualified work but excludes closed unqualified and duplicate history',static function():void {
    idlm_capacity_fast_fixture(array(array('id'=>1,'assigned_rep_id'=>10,'stage'=>'Qualified','lead_quality'=>'Qualified'),array('id'=>2)));
    assert_false(IDLM_DB::update_lead(2,array('assigned_rep_id'=>10)));
    foreach(array(array('stage'=>'Won'),array('stage'=>'Lost'),array('lead_quality'=>'Unqualified'),array('is_duplicate'=>1)) as $closed) {
        idlm_test_reset_environment();idlm_capacity_fast_fixture(array(array_merge(array('id'=>1,'assigned_rep_id'=>10),$closed),array('id'=>2)));
        assert_true(IDLM_DB::update_lead(2,array('assigned_rep_id'=>10)));
    }
});
idlm_test('full capacity rejects insertion and changed ownership without partial aggregate state',static function():void {
    idlm_capacity_fast_fixture(array(array('id'=>1,'assigned_rep_id'=>10),array('id'=>2)));$before=$GLOBALS['wpdb']->tables;
    assert_false(IDLM_DB::update_lead(2,array('assigned_rep_id'=>10)));assert_contains('capacity',IDLM_DB::get_last_validation_error());assert_same($before,$GLOBALS['wpdb']->tables);
    assert_false(IDLM_DB::insert_lead(array('first_name'=>'Synthetic full','assigned_rep_id'=>10)));assert_same($before,$GLOBALS['wpdb']->tables);
});
idlm_test('unchanged owner and closing remain allowed while reopening requires a slot',static function():void {
    idlm_capacity_fast_fixture(array(array('id'=>1,'assigned_rep_id'=>10),array('id'=>2,'assigned_rep_id'=>10,'stage'=>'Lost')));
    assert_true(IDLM_DB::update_lead(1,array('assigned_rep_id'=>10,'budget'=>'9 lakh')));$before=$GLOBALS['wpdb']->tables;
    assert_false(IDLM_DB::update_lead(2,array('stage'=>'Contacted')));assert_same($before,$GLOBALS['wpdb']->tables);
    assert_true(IDLM_DB::update_lead(1,array('stage'=>'Lost','lost_reason'=>'Synthetic close')));
    assert_true(IDLM_DB::update_lead(2,array('stage'=>'Contacted')));
});
idlm_test('zero capacity remains unlimited and closed owner changes consume no slot',static function():void {
    idlm_capacity_fast_fixture(array(array('id'=>1,'assigned_rep_id'=>10),array('id'=>2,'stage'=>'Lost','lost_reason'=>'Other')));
    assert_true(IDLM_DB::update_lead(2,array('assigned_rep_id'=>10)),IDLM_DB::get_last_validation_error());
    update_option('idlm_max_active_leads_per_rep',0);assert_true(IDLM_DB::insert_lead(array('first_name'=>'Synthetic unlimited','assigned_rep_id'=>10))>0,IDLM_DB::get_last_validation_error());
});
