<?php
/** Current-count admission, backlog and concurrent writer evidence. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
function idlm_capacity_settings(array $extra=array()): void {
    foreach(array_merge(array('idlm_max_active_leads_per_rep'=>1,'idlm_assignment_mode'=>'round_robin','idlm_fallback_assignment_enabled'=>'1','idlm_fallback_assignment_hours'=>1,'idlm_qualified_fallback_enabled'=>'0','idlm_working_days'=>range(1,7),'idlm_working_hours_start'=>'00:00','idlm_working_hours_end'=>'23:59'),$extra)as$key=>$value){update_option($key,$value);}IDLM_Settings::sync_routing_from_legacy();
}
function idlm_capacity_count(int $owner): int { global $wpdb;return (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE assigned_rep_id={$owner} AND is_duplicate=0 AND COALESCE(stage,'') NOT IN ('Won','Lost') AND COALESCE(lead_quality,'')!='Unqualified'"); }
function idlm_capacity_retire(array $ids): void { global $wpdb;if($ids){$wpdb->query("UPDATE {$wpdb->prefix}idlm_leads SET stage='Lost' WHERE id IN (".implode(',',array_map('absint',$ids)).')');} }
idlm_integration_test('T03-N-CORE010-201-lead-recovery-cap1-admits-one-and-retains-the-backlog',static function():void {
    global $wpdb;$target=idlm_assignment_user();idlm_capacity_settings(array('idlm_active_assignment_reps'=>array($target)));$ids=array();
    try {
        for($i=0;$i<201;$i++){$ids[]=idlm_lifecycle_fixture(array('created_at'=>'2001-01-01 00:00:00'));}
        idlm_assignment_private('recover_unassigned_leads');$sql=implode(',',$ids);idlm_integration_assert_same(1,idlm_capacity_count($target));idlm_integration_assert_same(200,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE id IN ({$sql}) AND assigned_rep_id=0"));
        idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_state_events WHERE lead_id IN ({$sql}) AND event_type='assignment'"));
        idlm_assignment_private('recover_unassigned_leads');idlm_integration_assert_same(1,idlm_capacity_count($target));
    } finally {idlm_capacity_retire($ids);}
});
idlm_integration_test('T03-N-CORE010-zero-cap-drains-more-than-one-page-without-a-limit',static function():void {
    global $wpdb;$target=idlm_assignment_user();idlm_capacity_settings(array('idlm_active_assignment_reps'=>array($target),'idlm_max_active_leads_per_rep'=>0));$ids=array();
    try {for($i=0;$i<201;$i++){$ids[]=idlm_lifecycle_fixture(array('created_at'=>'2001-01-01 00:00:00'));}idlm_assignment_private('recover_unassigned_leads');$sql=implode(',',$ids);idlm_integration_assert_same(201,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE id IN ({$sql}) AND assigned_rep_id={$target}"));}
    finally {idlm_capacity_retire($ids);}
});
idlm_integration_test('T03-N-CORE010-near-cap-studio-general-and-presales-recovery-refresh-pools-per-lead',static function():void {
    global $wpdb;$a=idlm_assignment_user();$b=idlm_assignment_user();$ids=array();
    try {
        idlm_capacity_settings(array('idlm_active_assignment_reps'=>array($a,$b),'idlm_max_active_leads_per_rep'=>2,'idlm_rep_studio_assignments'=>array($a=>'Synthetic studio')));
        $ids[]=idlm_lifecycle_fixture(array('assigned_rep_id'=>$a));$ids[]=idlm_lifecycle_fixture(array('assigned_rep_id'=>$b));
        for($i=0;$i<4;$i++){$ids[]=idlm_lifecycle_fixture(array('created_at'=>'2000-01-01 00:00:00','preferred_studio'=>'Synthetic studio'));}
        idlm_assignment_private('recover_unassigned_leads');idlm_integration_assert_same(2,idlm_capacity_count($a));idlm_integration_assert_same(2,idlm_capacity_count($b));
        idlm_integration_assert_same((string)$a,$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$ids[2]}"));idlm_integration_assert_same((string)$b,$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$ids[3]}"));
    }finally{idlm_capacity_retire($ids);}
    $pre=idlm_assignment_user('idlm_pre_sales');idlm_capacity_settings(array('idlm_assignment_mode'=>'pre_sales','idlm_active_pre_sales_reps'=>array($pre)));$ids=array();
    try {for($i=0;$i<3;$i++){$ids[]=idlm_lifecycle_fixture(array('created_at'=>'2000-01-01 00:00:00'));}idlm_assignment_private('recover_unassigned_leads');idlm_integration_assert_same(1,idlm_capacity_count($pre));$id=$ids[0];idlm_integration_assert_same(6,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$id}"));}
    finally{idlm_capacity_retire($ids);}
});
idlm_integration_test('T03-N-CORE010-all-initial-routing-modes-stop-at-cap-and-qualified-fallback-respects-sales-cap',static function():void {
    global $wpdb;
    foreach(array('pre_sales','round_robin','source_based','studio_based')as$mode){$target=idlm_assignment_user('idlm_pre_sales');idlm_capacity_settings(array('idlm_assignment_mode'=>$mode,'idlm_active_assignment_reps'=>array($target),'idlm_active_pre_sales_reps'=>array($target),'idlm_source_assignment_mappings'=>array('Website'=>$target),'idlm_rep_studio_assignments'=>array($target=>'Synthetic')));$a=idlm_lifecycle_fixture(array('preferred_studio'=>'Synthetic'));$b=idlm_lifecycle_fixture(array('preferred_studio'=>'Synthetic'));
        idlm_integration_assert_same(true,IDLM_Integrations::route_lead_assignment($a,'Website'));idlm_integration_assert_same(true,IDLM_Integrations::route_lead_assignment($b,'Website'));idlm_integration_assert_same(1,idlm_capacity_count($target));idlm_integration_assert_same('0',$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$b}"));idlm_capacity_retire(array($a,$b));}
    $sales=idlm_assignment_user();$old=idlm_assignment_user('idlm_pre_sales');idlm_capacity_settings(array('idlm_active_assignment_reps'=>array($sales),'idlm_qualified_fallback_enabled'=>'1','idlm_qualified_fallback_hours'=>1));$ids=array();
    try{for($i=0;$i<3;$i++){$ids[]=idlm_lifecycle_fixture(array('assigned_rep_id'=>$old,'stage'=>'Qualified','lead_quality'=>'Qualified','qualified_at'=>'2000-01-01 00:00:00','handoff_status'=>'awaiting_manager'));}idlm_assignment_private('handoff_qualified_leads');idlm_integration_assert_same(1,idlm_capacity_count($sales));idlm_integration_assert_same(2,idlm_capacity_count($old));}finally{idlm_capacity_retire($ids);}
});
idlm_integration_test('T03-N-CORE010-existing-work-closing-history-and-reactivation-use-positive-workload-deltas',static function():void {
    $target=idlm_assignment_user();idlm_capacity_settings();$active=idlm_lifecycle_fixture(array('assigned_rep_id'=>$target));$closed=idlm_lifecycle_fixture(array('assigned_rep_id'=>$target,'stage'=>'Lost','lost_reason'=>'Other'));$duplicate=idlm_lifecycle_fixture(array('assigned_rep_id'=>$target,'is_duplicate'=>1));
    idlm_integration_assert_same(true,IDLM_DB::update_lead($active,array('assigned_rep_id'=>$target,'budget'=>'9 lakh')));$before=idlm_lifecycle_snapshot($closed);idlm_integration_assert_same(false,IDLM_DB::update_lead($closed,array('stage'=>'Contacted')));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($closed));
    idlm_integration_assert_same(false,IDLM_DB::update_lead($duplicate,array('is_duplicate'=>0)));idlm_integration_assert_same(true,IDLM_DB::update_lead($active,array('stage'=>'Lost','lost_reason'=>'Other')));idlm_integration_assert_same(true,IDLM_DB::update_lead($closed,array('stage'=>'Contacted')));idlm_integration_assert_same(1,idlm_capacity_count($target));
    $history=idlm_lifecycle_fixture(array('stage'=>'Lost','lost_reason'=>'Other'));idlm_integration_assert_same(true,IDLM_DB::update_lead($history,array('assigned_rep_id'=>$target)));idlm_integration_assert_same(1,idlm_capacity_count($target));
});
idlm_integration_test('T03-N-CORE010-current-settings-and-locking-count-ignore-an-earlier-repeatable-read-snapshot',static function():void {
    global $wpdb,$config;$target=idlm_assignment_user();idlm_capacity_settings(array('idlm_max_active_leads_per_rep'=>0));get_option('idlm_max_active_leads_per_rep');$lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);$changed=false;
    $other=new wpdb($config['db_user'],$config['db_password'],$config['db_name'],$config['db_host']);
    $filter=static function($sql)use($target,&$changed,$other,$wpdb){if(!$changed && strpos($sql,'SELECT option_name, option_value FROM '.$wpdb->options.' WHERE option_name IN')===0){$changed=true;$other->update($wpdb->options,array('option_value'=>'1'),array('option_name'=>'idlm_max_active_leads_per_rep'));$other->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic independent admission','assigned_rep_id'=>$target,'stage'=>'New'));idlm_integration_assert_same('',$other->last_error);}return$sql;};
    try {$r=idlm_schema_filter($filter,static function()use($lead,$target,$wpdb){return IDLM_Lead_Transaction::run(static function()use($lead,$target,$wpdb){$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads");return IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$target));});});idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_same(true,$changed);idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));idlm_integration_assert_same(1,idlm_capacity_count($target));}
    finally{$other->close();wp_cache_flush();}
});
idlm_integration_test('T03-N-CORE010-capacity-read-errors-fail-closed-and-required-write-rollback-releases-the-slot',static function():void {
    global $wpdb;$target=idlm_assignment_user();idlm_capacity_settings();$lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);
    foreach(array('SELECT option_name, option_value FROM '.$wpdb->options,'SELECT COUNT(*) FROM '.$wpdb->prefix.'idlm_leads WHERE assigned_rep_id = '.$target)as$prefix){$filter=static function($sql)use($prefix){return strpos($sql,$prefix)===0 && strpos($sql,'FOR UPDATE')!==false?'SELECT * FROM idlm_test_missing_capacity':$sql;};idlm_integration_assert_same(false,idlm_schema_filter($filter,static function()use($lead,$target){return IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$target));}));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));}
    idlm_integration_assert_same(false,idlm_lifecycle_failure('idlm_lead_state_events','INSERT',static function()use($lead,$target){return IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$target));}));idlm_integration_assert_same(0,idlm_capacity_count($target));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$target)));
});
foreach(array(array('manual','manual'),array('insert','initial'),array('reopen','intake'),array('qualified','manual'))as[$a,$b]){
    idlm_integration_test('T03-C-CORE010-two-workers-'.$a.'-versus-'.$b.'-admit-at-most-one-final-slot',static function()use($a,$b):void {
        global $wpdb;$target=idlm_assignment_user(in_array('intake',array($a,$b),true)?'idlm_pre_sales':'sales_executive');idlm_capacity_settings();$ids=array();
        foreach(array($a,$b)as$op){$ids[]=idlm_lifecycle_fixture($op==='reopen'?array('assigned_rep_id'=>$target,'stage'=>'Lost','lost_reason'=>'Other'):($op==='qualified'?array('stage'=>'Qualified','lead_quality'=>'Qualified'):array()));}
        $results=idlm_lifecycle_pair(array('capacity-race',$a,$ids[0],$target),array('capacity-race',$b,$ids[1],$target));$admitted=0;foreach($results as$r){idlm_integration_assert_same(0,$r['status'],$r['stderr']);idlm_integration_assert_true(in_array($r['stdout'],array('admitted','refused'),true));$admitted+=($r['stdout']==='admitted');}idlm_integration_assert_same(1,$admitted);idlm_integration_assert_same(1,idlm_capacity_count($target));
    });
}
idlm_integration_test('T03-C-CORE010-process-death-before-commit-releases-admission-and-owner-lock',static function():void {
    $target=idlm_assignment_user();idlm_capacity_settings();$lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);$r=idlm_integration_worker(array('capacity-interrupt','manual',$lead,$target));idlm_integration_assert_same(77,$r['status'],$r['stderr']);idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));idlm_integration_assert_same(0,idlm_capacity_count($target));idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$target)));
});
