<?php
/** Live ownership eligibility, routing and stale-grant evidence. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_') !== 0) { exit(1); }
function idlm_assignment_user(string $role='sales_executive'): int {
    if ('sales_executive' === $role && !get_role($role)) { add_role($role,'Sales Executive',array('read'=>true,'idlm_access_leads'=>true)); }
    $id=wp_insert_user(array('user_login'=>'eligibility-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(),'role'=>$role));
    idlm_integration_assert_true(!is_wp_error($id));return (int)$id;
}
function idlm_assignment_private(string $method,array $args=array()) {
    $r=new ReflectionMethod('IDLM_Integrations',$method);if (PHP_VERSION_ID < 80100) { $r->setAccessible(true); }return $r->invokeArgs(null,$args);
}
idlm_integration_test('T01-N-CORE009-effective role capability disabled and mixed-role matrix',static function():void {
    $sales=idlm_assignment_user();$pre=idlm_assignment_user('idlm_pre_sales');$editor=idlm_assignment_user('editor');$admin=idlm_assignment_user('administrator');
    idlm_integration_assert_same(true,IDLM_Lead_Access_Policy::is_assignable_rep($sales,'sales'));
    idlm_integration_assert_same(false,IDLM_Lead_Access_Policy::is_assignable_rep($editor));(new WP_User($editor))->add_cap('idlm_access_leads');idlm_integration_assert_same(true,IDLM_Lead_Access_Policy::is_assignable_rep($editor));
    (new WP_User($pre))->add_role('sales_executive');idlm_integration_assert_same(true,IDLM_Lead_Access_Policy::is_assignable_rep($pre,'intake'));idlm_integration_assert_same(false,IDLM_Lead_Access_Policy::is_assignable_rep($pre,'sales'));
    (new WP_User($sales))->add_cap('idlm_access_leads',false);idlm_integration_assert_same(false,IDLM_Lead_Access_Policy::is_assignable_rep($sales));
    update_user_meta($admin,'idlm_crm_access_disabled','1');idlm_integration_assert_same(false,IDLM_Lead_Access_Policy::is_assignable_rep($admin));
});
foreach(array('disabled','deleted','role_lost','direct_denial','role_revoked') as $change) {
    idlm_integration_test('T03-N-CORE009-cached-pool-'.$change.'-before-live-lock-has-no-assignment-side-effects',static function()use($change):void {
        global $wpdb,$config,$administrator_id;
        $target=idlm_assignment_user();$lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));$before=idlm_lifecycle_snapshot($lead);
        update_option('idlm_max_active_leads_per_rep',0);update_option('idlm_active_assignment_reps',array($target));IDLM_Settings::sync_routing_from_legacy();
        idlm_integration_assert_same(array($target),idlm_assignment_private('get_active_rep_pool'));get_userdata($target);get_user_meta($target);$original=get_option($wpdb->prefix.'user_roles');
        $other=new wpdb($config['db_user'],$config['db_password'],$config['db_name'],$config['db_host']);$changed=false;
        $filter=static function($sql)use($change,$target,$other,&$changed,$original,$wpdb) {
            if (!$changed && strpos($sql,"SELECT * FROM {$wpdb->users} WHERE ID = {$target} FOR UPDATE")===0) {
                $changed=true;
                if ($change==='disabled') {$other->insert($wpdb->usermeta,array('user_id'=>$target,'meta_key'=>'idlm_crm_access_disabled','meta_value'=>'1'));}
                elseif ($change==='deleted') {$other->delete($wpdb->users,array('ID'=>$target));}
                elseif ($change==='role_revoked') {$roles=$original;$roles['sales_executive']['capabilities']['idlm_access_leads']=false;$other->update($wpdb->options,array('option_value'=>serialize($roles)),array('option_name'=>$wpdb->prefix.'user_roles'));}
                else {$caps=$change==='role_lost'?array('subscriber'=>true):array('sales_executive'=>true,'idlm_access_leads'=>false);$other->update($wpdb->usermeta,array('meta_value'=>serialize($caps)),array('user_id'=>$target,'meta_key'=>$wpdb->prefix.'capabilities'));}
                idlm_integration_assert_same('',$other->last_error);
            }
            return $sql;
        };
        try {
            idlm_integration_assert_same(false,idlm_schema_filter($filter,static function()use($lead,$target){return IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$target));}));
            idlm_integration_assert_same(true,$changed);idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
            idlm_integration_assert_same(false,idlm_assignment_private('assign_lead_atomically',array($lead,$administrator_id,$target,'Synthetic stale target',false,'initial')));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
        } finally {$other->update($wpdb->options,array('option_value'=>serialize($original)),array('option_name'=>$wpdb->prefix.'user_roles'));$other->close();wp_cache_flush();wp_roles()->for_site();}
    });
}
idlm_integration_test('T03-N-CORE009-insert-rejects-disabled-owner-unchanged-history-and-unassignment-remain-valid',static function():void {
    global $wpdb;$target=idlm_assignment_user();update_user_meta($target,'idlm_crm_access_disabled','1');
    $total=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads");idlm_integration_assert_same(false,IDLM_DB::insert_lead(array('first_name'=>'Synthetic denied owner','assigned_rep_id'=>$target)));idlm_integration_assert_same($total,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads"));
    $lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$target));idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$target,'budget'=>'9 lakh')));idlm_integration_assert_same((string)$target,$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
    idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('assigned_rep_id'=>0)));idlm_integration_assert_same('0',$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
});
idlm_integration_test('T03-N-CORE009-live-target-read-errors-and-nontransactional-auth-tables-fail-closed',static function():void {
    global $wpdb;$target=idlm_assignment_user();$lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);
    foreach(array('SELECT * FROM '.$wpdb->users,'SELECT meta_key, meta_value FROM '.$wpdb->usermeta,'SELECT option_value FROM '.$wpdb->options) as $prefix) {
        $filter=static function($sql)use($prefix){return strpos($sql,$prefix)===0 && strpos($sql,'FOR UPDATE')!==false?'SELECT * FROM idlm_test_missing_target':$sql;};
        idlm_integration_assert_same(false,idlm_schema_filter($filter,static function()use($lead,$target){return IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$target));}));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
    }
    foreach(array($wpdb->users,$wpdb->usermeta,$wpdb->options) as $table) {
        $wpdb->query("ALTER TABLE {$table} ENGINE=MyISAM");try {idlm_integration_assert_same(false,IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$target)));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));}finally{$wpdb->query("ALTER TABLE {$table} ENGINE=InnoDB");}
    }
});
idlm_integration_test('T03-N-CORE009-all-initial-routing-modes-filter-disabled-targets',static function():void {
    global $wpdb;$bad=idlm_assignment_user('idlm_pre_sales');$good=idlm_assignment_user('idlm_pre_sales');update_user_meta($bad,'idlm_crm_access_disabled','1');
    update_option('idlm_active_assignment_reps',array($bad,$good));update_option('idlm_active_pre_sales_reps',array($bad,$good));update_option('idlm_max_active_leads_per_rep',0);update_option('idlm_rep_studio_assignments',array($bad=>'Synthetic',$good=>'Synthetic'));
    foreach(array('pre_sales','round_robin','source_based','studio_based') as $mode) {
        update_option('idlm_assignment_mode',$mode);update_option('idlm_source_assignment_mappings',array('Website'=>$bad));IDLM_Settings::sync_routing_from_legacy();$lead=idlm_lifecycle_fixture(array('preferred_studio'=>'Synthetic'));
        idlm_integration_assert_same(true,IDLM_Integrations::route_lead_assignment($lead,'Website'));idlm_integration_assert_same($mode==='source_based'?'0':(string)$good,$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
        if($mode==='source_based'){update_option('idlm_source_assignment_mappings',array('Website'=>$good));IDLM_Settings::sync_routing_from_legacy();idlm_integration_assert_same(true,IDLM_Integrations::route_lead_assignment($lead,'Website'));idlm_integration_assert_same((string)$good,$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));}
    }
    update_option('idlm_assignment_mode','manual');IDLM_Settings::sync_routing_from_legacy();
});
idlm_integration_test('T03-N-CORE009-unassigned-and-qualified-fallback-use-enabled-context-correct-pools',static function():void {
    global $wpdb;$bad=idlm_assignment_user();$good=idlm_assignment_user();$mixed=idlm_assignment_user('idlm_pre_sales');(new WP_User($mixed))->add_role('sales_executive');update_user_meta($bad,'idlm_crm_access_disabled','1');
    foreach(array('idlm_active_assignment_reps'=>array($bad,$good),'idlm_max_active_leads_per_rep'=>0,'idlm_assignment_mode'=>'round_robin','idlm_fallback_assignment_enabled'=>'1','idlm_fallback_assignment_hours'=>1,'idlm_qualified_fallback_enabled'=>'1','idlm_qualified_fallback_hours'=>1,'idlm_working_days'=>range(1,7),'idlm_working_hours_start'=>'00:00','idlm_working_hours_end'=>'23:59')as$key=>$value){update_option($key,$value);}IDLM_Settings::sync_routing_from_legacy();
    $old=gmdate('Y-m-d H:i:s',time()-7*DAY_IN_SECONDS);$lead=idlm_lifecycle_fixture(array('created_at'=>$old));idlm_assignment_private('recover_unassigned_leads');idlm_integration_assert_same((string)$good,$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
    update_option('idlm_active_assignment_reps',array($bad,$mixed,$good));IDLM_Settings::sync_routing_from_legacy();$qualified=idlm_lifecycle_fixture(array('assigned_rep_id'=>$bad,'lead_quality'=>'Qualified','stage'=>'Qualified','qualified_at'=>$old,'handoff_status'=>'awaiting_manager'));
    idlm_assignment_private('handoff_qualified_leads');idlm_integration_assert_same((string)$good,$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$qualified}"));
    update_option('idlm_assignment_mode','manual');update_option('idlm_fallback_assignment_enabled','0');update_option('idlm_qualified_fallback_enabled','0');IDLM_Settings::sync_routing_from_legacy();
});
