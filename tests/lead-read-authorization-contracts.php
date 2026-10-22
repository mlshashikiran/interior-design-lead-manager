<?php
/** Effective CRM/read scope contracts. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
idlm_test('role permission choices exclude WordPress authority and preserve pre-sales restrictions',static function(): void {
    $sales = IDLM_Admin_Users_Controller::selectable_permissions('sales_executive');
    assert_true(isset($sales['idlm_access_leads'], $sales['idlm_assign_leads'], $sales['idlm_view_quotes']));
    assert_false(isset($sales['manage_options'], $sales['read']));
    assert_same(array('idlm_access_leads', 'idlm_qualify_leads'), array_keys(IDLM_Admin_Users_Controller::selectable_permissions('idlm_pre_sales')));
    foreach (array('administrator', 'editor', 'author', 'contributor') as $role) { assert_false(isset(IDLM_Admin_Users_Controller::permission_roles()[$role])); }
});
idlm_test('CRM role membership cannot substitute for a revoked access capability',static function(): void {
    foreach(array('sales_executive','sales_manager','editor','author','contributor','idlm_pre_sales') as $role) {
        unset($GLOBALS['idlm_test_users'][7]); idlm_test_login(7,array($role),array('read','idlm_assign_leads','idlm_view_quotes'));
        assert_false(IDLM_Lead_Access_Policy::has_rep_access()); assert_false(IDLM_Lead_Access_Policy::can_assign());
        assert_false(IDLM_Quotation_Policy::can('idlm_view_quotes'));
        $params=array(); assert_same(' AND 1=0',IDLM_Lead_Access_Policy::scope_sql('l',$params,true)); assert_same(array(),$params);
    }
});
idlm_test('pending legacy migration denies inherited editorial grants but keeps explicit user enrollment',static function(): void {
    idlm_test_login(7,array('author'),array('idlm_access_leads','idlm_view_quotes'));
    unset($GLOBALS['idlm_test_users'][7]->caps['idlm_access_leads']);
    assert_false(IDLM_Lead_Access_Policy::has_rep_access());
    $GLOBALS['idlm_test_users'][7]->caps['idlm_access_leads']=true;
    assert_true(IDLM_Lead_Access_Policy::has_rep_access());
});
idlm_test('fresh CRM visibility is assigned-only and explicit mixed scopes remain restrictive',static function(): void {
    idlm_test_login(7,array('sales_executive'),array('idlm_access_leads'));
    assert_same('0',IDLM_Settings::routing_defaults()['idlm_reps_see_all_leads']); assert_same(7,IDLM_Lead_Access_Policy::restriction_user_id());
    update_option('idlm_role_lead_scopes',array('sales_executive'=>'assigned','author'=>'all'));
    assert_same('assigned',IDLM_Admin_Users_Controller::role_scope(array('author','sales_executive')));
});
idlm_test('denied direct dashboard cache read performs no database or cache work',static function(): void {
    idlm_test_login(7,array('sales_executive'),array('read'));
    $before=$GLOBALS['idlm_test_transients']; $queries=$GLOBALS['wpdb']->queries;
    $data=IDLM_Dashboard_Read_Model::get_stats('today');
    assert_same(array('access'),array_keys($data)); assert_false($data['access']['sales']); assert_same($before,$GLOBALS['idlm_test_transients']); assert_same($queries,$GLOBALS['wpdb']->queries);
});
idlm_test('role scope role grants and user access edits fence warmed read cache',static function(): void {
    $generation=IDLM_Read_Model_Cache::generation(); IDLM_Read_Model_Cache::invalidate_on_option_change('idlm_role_lead_scopes'); assert_false($generation===IDLM_Read_Model_Cache::generation());
    $generation=IDLM_Read_Model_Cache::generation(); IDLM_Read_Model_Cache::invalidate_on_option_change('wp_user_roles'); assert_false($generation===IDLM_Read_Model_Cache::generation());
    foreach(array('wp_capabilities','idlm_crm_access_disabled') as $key) { $generation=IDLM_Read_Model_Cache::generation(); IDLM_Read_Model_Cache::invalidate_on_user_access_change(1,7,$key); assert_false($generation===IDLM_Read_Model_Cache::generation()); }
});
