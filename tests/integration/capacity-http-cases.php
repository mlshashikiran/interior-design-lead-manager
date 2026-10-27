<?php
/** Actual capacity errors, delta admission and rollback acknowledgements. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
function idlm_capacity_http_target(string $role='sales_executive'): int {
    $id=wp_insert_user(array('user_login'=>'http-capacity-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(),'role'=>$role));idlm_http_check(!is_wp_error($id),'Target creation failed');return (int)$id;
}
function idlm_capacity_http_lead(array $extra=array()): int {
    global $wpdb;$lead=idlm_atomic_http_lead();$wpdb->update($wpdb->prefix.'idlm_leads',array_merge(array('assigned_rep_id'=>0),$extra),array('id'=>$lead));return $lead;
}
function idlm_capacity_http_stage(int $lead,string $stage): array {
    global $sessions;return idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_quick_edit_lead','nonce'=>$sessions['admin']['admin'],'lead_id'=>$lead,'field'=>'stage','value'=>$stage,'lost_reason'=>'Other','lost_remark'=>'Synthetic capacity close'),'admin');
}
idlm_http_case('T03-H-CORE010-quick-assignment-stops-at-cap-with-no-false-success-and-same-owner-remains-editable',static function():void {
    global $wpdb;$target=idlm_capacity_http_target();update_option('idlm_max_active_leads_per_rep',1);IDLM_Settings::sync_routing_from_legacy();$a=idlm_capacity_http_lead();$b=idlm_capacity_http_lead();
    $r=idlm_assignment_http($a,$target);$j=json_decode($r['body'],true);idlm_http_check(!empty($j['success']),'First free slot refused: '.$r['body']);$before=idlm_http_snapshot();$r=idlm_assignment_http($b,$target);$j=json_decode($r['body'],true);idlm_http_check(isset($j['success']) && !$j['success'] && strpos($r['body'],'capacity')!==false,'Full target falsely acknowledged');idlm_http_check($before===idlm_http_snapshot(),'Full assignment changed aggregate/jobs/outbound');
    $r=idlm_assignment_http($a,$target);$j=json_decode($r['body'],true);idlm_http_check(!empty($j['success']),'Same owner refused at cap');idlm_http_check((int)$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$b}")===0,'Refused lead gained an owner');
});
idlm_http_case('T03-H-CORE010-bulk-uses-active-deltas-and-reports-partial-per-lead-failure',static function():void {
    global $wpdb,$sessions;$target=idlm_capacity_http_target();update_option('idlm_max_active_leads_per_rep',1);$a=idlm_capacity_http_lead(array('assigned_rep_id'=>$target));$closed=idlm_capacity_http_lead(array('stage'=>'Lost','lost_reason'=>'Other'));$open=idlm_capacity_http_lead();
    $r=idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_bulk_action','nonce'=>$sessions['admin']['admin'],'bulk_action'=>'bulk_assign','ids'=>array($a,$closed,$open),'rep_id'=>$target),'admin');$j=json_decode($r['body'],true);idlm_http_check(isset($j['success']) && !$j['success'] && strpos($r['body'],'capacity')!==false,'Bulk did not report capacity refusal');
    idlm_http_check((int)$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$closed}")===$target,'Closed history consumed capacity or was incorrectly rejected');idlm_http_check((int)$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$open}")===0,'Bulk exceeded active capacity');idlm_http_check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_state_events WHERE lead_id={$open}")===0,'Refused row emitted assignment history');
});
idlm_http_case('T03-H-CORE010-reopening-needs-capacity-and-closing-releases-it',static function():void {
    $target=idlm_capacity_http_target();update_option('idlm_max_active_leads_per_rep',1);$a=idlm_capacity_http_lead(array('assigned_rep_id'=>$target));$b=idlm_capacity_http_lead(array('assigned_rep_id'=>$target,'stage'=>'Lost','lost_reason'=>'Other'));
    $before=idlm_http_snapshot();$r=idlm_capacity_http_stage($b,'Contacted');$j=json_decode($r['body'],true);idlm_http_check(isset($j['success']) && !$j['success'] && strpos($r['body'],'capacity')!==false,'Reopening bypassed cap');idlm_http_check($before===idlm_http_snapshot(),'Refused reopening changed state');
    $r=idlm_capacity_http_stage($a,'Lost');$j=json_decode($r['body'],true);idlm_http_check(!empty($j['success']),'Closing failed');$r=idlm_capacity_http_stage($b,'Contacted');$j=json_decode($r['body'],true);idlm_http_check(!empty($j['success']),'Released slot was unavailable');
});
idlm_http_case('T03-H-CORE010-required-write-failure-does-not-consume-slot-and-manual-intake-at-cap-is-denied',static function():void {
    global $sessions;$target=idlm_capacity_http_target();update_option('idlm_max_active_leads_per_rep',1);$lead=idlm_capacity_http_lead();$before=idlm_http_snapshot();
    $r=idlm_atomic_http_trigger('idlm_lead_state_events','INSERT',static function()use($lead,$target){return idlm_assignment_http($lead,$target);});$j=json_decode($r['body'],true);idlm_http_check(isset($j['success']) && !$j['success'],'Required failure acknowledged');idlm_http_check($before===idlm_http_snapshot(),'Rollback left partial reservation or owner');$r=idlm_assignment_http($lead,$target);$j=json_decode($r['body'],true);idlm_http_check(!empty($j['success']),'Rollback consumed slot');
    $before=idlm_http_snapshot();$r=idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_add_lead_manual','nonce'=>$sessions['owner']['admin'],'form_data'=>http_build_query(array('first_name'=>'Synthetic full manual intake','email'=>wp_generate_uuid4().'@example.test','phone'=>'+1555'.wp_rand(1000000,9999999)))));$j=json_decode($r['body'],true);idlm_http_check(isset($j['success']) && !$j['success'] && strpos($r['body'],'capacity')!==false,'Full creator intake response: '.$r['body']);idlm_http_check($before===idlm_http_snapshot(),'Full creator intake left aggregate');
});
idlm_http_case('T03-H-CORE010-authenticated-webhook-retains-unassigned-intake-when-presales-pool-is-full',static function():void {
    global $wpdb;$target=idlm_capacity_http_target('idlm_pre_sales');update_option('idlm_max_active_leads_per_rep',1);update_option('idlm_assignment_mode','pre_sales');update_option('idlm_active_pre_sales_reps',array($target));IDLM_Settings::sync_routing_from_legacy();$ids=array();
    foreach(array(0,1)as$i){$r=idlm_atomic_webhook(array('first_name'=>'Synthetic capacity webhook','email'=>wp_generate_uuid4().'@example.test','fb_lead_id'=>wp_generate_uuid4()));$j=json_decode($r['body'],true);idlm_http_check($r['status']===200 && !empty($j['success']),'Webhook failed: '.$r['body']);$ids[]=(int)$j['lead_id'];}
    idlm_http_check((int)$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$ids[0]}")===$target,'First webhook not assigned');idlm_http_check((int)$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$ids[1]}")===0,'Full webhook overflowed owner');idlm_http_check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$ids[0]}")===6,'First webhook lost plan');idlm_http_check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$ids[1]}")===0,'Unassigned webhook emitted tasks');
    update_option('idlm_assignment_mode','manual');IDLM_Settings::sync_routing_from_legacy();
});
