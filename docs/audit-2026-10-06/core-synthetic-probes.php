<?php
/** Audit-only synthetic probes. Author: Shashi. No WordPress site is loaded. */
require dirname(__DIR__, 2) . '/tests/bootstrap.php';
function core_fixture($extra = array()) {
    return array_merge(array('id'=>1,'email'=>'audit@example.test','phone'=>'+919876543210','first_name'=>'Synthetic','last_name'=>'Audit','stage'=>'New','lead_quality'=>'','deal_value'=>0,'discount_percentage'=>0,'quote_shared'=>0,'quote_value'=>0,'lost_reason'=>'','call_status'=>'Not Called','assigned_rep_id'=>7,'assigned_at'=>null,'qualified_at'=>null,'qualified_by_user_id'=>0,'intake_owner_id'=>0,'handoff_status'=>'not_ready','handoff_at'=>null,'handoff_method'=>'','handoff_exception_reason'=>'','floor_plan_received'=>1,'project_name'=>'Synthetic project','property_location'=>'Synthetic city','budget'=>'8 lakh','possession_date'=>'2026-12-01','property_type'=>'Apartment','project_timeline'=>'3 months','is_duplicate'=>0,'duplicate_of_id'=>0,'source'=>'Website','original_source'=>'Website','latest_source'=>'Website','created_at'=>'2026-10-01 09:00:00','updated_at'=>'2026-10-01 09:00:00','time_to_connected'=>null,'time_to_won'=>null), $extra);
}
if (in_array('--csv', $argv, true)) {
    idlm_test_reset_environment();
    idlm_test_login(1,array('administrator'),array('manage_options'));
    $fields=array_fill_keys(array('num_rooms','floor_plan_file','quote_file','callback_datetime','utm_source','utm_medium','utm_campaign','utm_adgroup','utm_term','utm_content','gclid','gbraid','wbraid','fbclid','fb_lead_id','li_fat_id','landing_page','conversion_time','referrer_details'), '');
    $GLOBALS['wpdb']->seed('idlm_leads',array(core_fixture(array_merge($fields,array('first_name'=>'=1+1')))));
    $GLOBALS['wpdb']->custom_query_results=$GLOBALS['wpdb']->rows('idlm_leads');
    IDLM_Admin_Ajax::ajax_export_leads();
}
$results = array();
function core_result($id, $expected, $actual) {
    global $results;
    $results[] = array('id'=>$id,'status'=>$expected === $actual ? 'PASS' : 'FAIL','expected'=>$expected,'actual'=>$actual);
}
idlm_test_reset_environment();
idlm_test_login(8, array('contributor'), array('read'));
$GLOBALS['wpdb']->seed('idlm_leads', array(core_fixture()));
core_result('CORE-S01 ordinary contributor without CRM capability denied', false, IDLM_Lead_Access_Policy::has_rep_access());
core_result('CORE-S02 fresh default contributor assigned-only scope', 8, IDLM_Lead_Access_Policy::restriction_user_id());
core_result('CORE-S03 contributor cannot mutate other owner', false, IDLM_Lead_Access_Policy::can_access(1));
core_result('CORE-S04 subscriber denied', false, (function(){ idlm_test_login(9,array('subscriber'),array('read')); return IDLM_Lead_Access_Policy::has_rep_access(); })());
idlm_test_login(10,array('sales_executive'),array('idlm_access_leads'));
update_user_meta(10,'idlm_crm_access_disabled','1');
core_result('CORE-S15 disabled representative denied login access',false,IDLM_Lead_Access_Policy::has_rep_access());
core_result('CORE-S16 disabled representative excluded assignment',false,IDLM_Lead_Access_Policy::is_assignable_rep(10));
idlm_test_reset_environment();
idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
$GLOBALS['wpdb']->seed('idlm_leads', array(core_fixture()));
$GLOBALS['wpdb']->fail_next_write('update', 'idlm_leads');
$r = IDLM_DB::upsert_inbound_lead(array('email'=>'audit@example.test','source'=>'Meta Ads','project_name'=>'Replacement'),'meta');
core_result('CORE-S05 failed canonical write returns failure', false, false !== $r);
core_result('CORE-S06 failed canonical write emits no attribution hook', false, in_array('idlm_lead_attribution_merged',array_column($GLOBALS['idlm_test_actions'],'hook'),true));
idlm_test_reset_environment();
$GLOBALS['wpdb']->seed('idlm_leads', array(core_fixture()));
$GLOBALS['wpdb']->fail_next_write('insert', 'idlm_lead_touchpoints');
$r = IDLM_DB::upsert_inbound_lead(array('email'=>'audit@example.test'),'website');
core_result('CORE-S07 failed touchpoint write returns failure', false, false !== $r);
idlm_test_reset_environment();
$GLOBALS['wpdb']->seed('idlm_leads', array(core_fixture()));
$GLOBALS['wpdb']->fail_next_write('insert', 'idlm_lead_state_events');
$r = IDLM_DB::update_lead(1,array('stage'=>'Contacted'));
core_result('CORE-S08 failed required state event rejects or rolls back update', false, $r);
core_result('CORE-S09 failed state event does not leave changed stage', 'New', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage']);
idlm_test_reset_environment();
$GLOBALS['wpdb']->seed('idlm_leads', array(core_fixture()));
$GLOBALS['wpdb']->fail_next_write('insert', 'idlm_tasks');
IDLM_DB::create_presales_followup_tasks(1,7);
IDLM_DB::create_presales_followup_tasks(1,7);
core_result('CORE-S10 partial qualification plan retry fills six tasks', 6, count($GLOBALS['wpdb']->rows('idlm_tasks')));
idlm_test_reset_environment();
$GLOBALS['wpdb']->seed('idlm_leads', array(core_fixture()));
$r = IDLM_DB::upsert_inbound_lead(array('email'=>'audit@example.test','phone'=>'+449876543210'),'website');
core_result('CORE-S11 single-email match keeps canonical ownership', 7, $GLOBALS['wpdb']->rows('idlm_leads')[0]['assigned_rep_id']);
core_result('CORE-S12 single email conflicting country phone actual documented test policy matches', true, $r['matched']);
idlm_test_reset_environment();
idlm_test_login(7,array('sales_executive'),array('idlm_access_leads'));
$scope=array('owner'=>7,'per_page'=>25);
$cursor=IDLM_Cursor_Pagination::encode('audit',array('id'=>1),$scope);
core_result('CORE-S13 valid cursor accepted', array('id'=>1), IDLM_Cursor_Pagination::decode($cursor,'audit',$scope));
idlm_test_login(8,array('sales_executive'),array('idlm_access_leads'));
core_result('CORE-S14 cross-user cursor rejected', true, is_wp_error(IDLM_Cursor_Pagination::decode($cursor,'audit',$scope)));
echo json_encode(array('environment'=>'Dependency-free PHP doubles; no native DB, HTTP, uploads, external callbacks, or browser','results'=>$results), JSON_PRETTY_PRINT) . PHP_EOL;
