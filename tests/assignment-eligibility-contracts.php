<?php
/** Assignment target policy and pool contracts. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }
function idlm_eligible_user(int $id, array $roles, array $caps=array()): void {
    $GLOBALS['idlm_test_users'][$id]=(object)array('ID'=>$id,'roles'=>$roles,'caps'=>$caps,'display_name'=>'Synthetic target');
}
idlm_test('assignment eligibility requires enabled current CRM access and context role',static function():void {
    idlm_eligible_user(10,array('sales_executive'));idlm_eligible_user(11,array('idlm_pre_sales','sales_executive'));
    idlm_eligible_user(12,array('subscriber'),array('idlm_access_leads'=>true));idlm_eligible_user(13,array('editor'));
    idlm_eligible_user(14,array('editor'),array('idlm_access_leads'=>true));idlm_eligible_user(15,array('administrator'));
    assert_true(IDLM_Lead_Access_Policy::is_assignable_rep(0));assert_false(IDLM_Lead_Access_Policy::is_assignable_rep(0,'intake'));
    assert_true(IDLM_Lead_Access_Policy::is_assignable_rep(10,'sales'));assert_false(IDLM_Lead_Access_Policy::is_assignable_rep(10,'intake'));
    assert_true(IDLM_Lead_Access_Policy::is_assignable_rep(11,'intake'));assert_false(IDLM_Lead_Access_Policy::is_assignable_rep(11,'sales'));
    assert_false(IDLM_Lead_Access_Policy::is_assignable_rep(12));assert_false(IDLM_Lead_Access_Policy::is_assignable_rep(13));
    assert_true(IDLM_Lead_Access_Policy::is_assignable_rep(14));assert_true(IDLM_Lead_Access_Policy::is_assignable_rep(15));assert_false(IDLM_Lead_Access_Policy::is_assignable_rep(999));
    update_user_meta(15,'idlm_crm_access_disabled','1');assert_false(IDLM_Lead_Access_Policy::is_assignable_rep(15));
    $GLOBALS['idlm_test_users'][10]->caps=array('idlm_access_leads'=>false);assert_false(IDLM_Lead_Access_Policy::is_assignable_rep(10));
});
idlm_test('all assignment pools exclude disabled revoked and deleted targets and sales excludes mixed presales',static function():void {
    idlm_eligible_user(10,array('sales_executive'));idlm_eligible_user(11,array('idlm_pre_sales','sales_executive'));
    idlm_eligible_user(12,array('sales_executive'));idlm_eligible_user(13,array('idlm_pre_sales'));idlm_eligible_user(14,array('sales_executive'),array('idlm_access_leads'=>false));
    update_user_meta(12,'idlm_crm_access_disabled','1');update_user_meta(13,'idlm_crm_access_disabled','1');
    update_option('idlm_active_assignment_reps',array(10,11,12,13,14,999));update_option('idlm_active_pre_sales_reps',array(11,13));update_option('idlm_max_active_leads_per_rep',0);
    assert_same(array(10,11),invoke_private('IDLM_Integrations','get_active_rep_pool'));
    assert_same(array(10),invoke_private('IDLM_Integrations','get_active_rep_pool',array(true)));
    assert_same(array(10),invoke_private('IDLM_Integrations','get_configured_sales_pool'));
    assert_same(array(11),invoke_private('IDLM_Integrations','get_pre_sales_pool'));
    assert_same(array(11,13),invoke_private('IDLM_Integrations','get_all_pre_sales_pool'),'Historical pool must retain disabled users.');
});
idlm_test('canonical ownership rejects disabled targets without changing the aggregate and permits unassignment',static function():void {
    idlm_eligible_user(10,array('sales_executive'));update_user_meta(10,'idlm_crm_access_disabled','1');
    $GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('assigned_rep_id'=>7))));$before=$GLOBALS['wpdb']->tables;
    assert_false(IDLM_DB::update_lead(1,array('assigned_rep_id'=>10)));assert_same($before,$GLOBALS['wpdb']->tables);
    assert_false(IDLM_DB::insert_lead(array('first_name'=>'Synthetic','assigned_rep_id'=>10)));assert_same($before,$GLOBALS['wpdb']->tables);
    assert_true(IDLM_DB::update_lead(1,array('assigned_rep_id'=>0)));assert_same(0,(int)$GLOBALS['wpdb']->rows('idlm_leads')[0]['assigned_rep_id']);
});
