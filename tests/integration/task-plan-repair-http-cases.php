<?php
/** Actual task-plan authority, repair and failure acknowledgement. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_') !== 0) { exit(1); }

function idlm_plan_http_fixture(): int {
    global $wpdb,$users;
    $lead=idlm_atomic_http_lead();
    $wpdb->update($wpdb->prefix.'idlm_leads',array('stage'=>'Contacted'),array('id'=>$lead));
    foreach(array(1,2,4,5,6) as $cycle) {
        idlm_http_check(1===$wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$lead,'assigned_rep_id'=>$users['owner'],'title'=>'Qualification cycle '.$cycle.': historical target','task_type'=>'Qualification','status'=>'pending','due_date'=>'2026-08-21 18:00:00')),'Task fixture failed');
    }
    return $lead;
}
function idlm_plan_http_touch(int $lead, string $actor='admin'): array {
    global $sessions;
    return idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_log_presales_touch','nonce'=>$sessions[$actor]['admin'],'lead_id'=>$lead,'kind'=>'whatsapp','note'=>'Synthetic task repair evidence'),$actor);
}
idlm_http_case('T04-H-CORE006-authorized outreach repairs missing cycle and reserved task mutations remain denied',static function():void {
    global $wpdb,$sessions;
    $lead=idlm_plan_http_fixture();$r=idlm_plan_http_touch($lead);$j=json_decode($r['body'],true);idlm_http_check(!empty($j['success']),'Authorized outreach failed: '.$r['body']);
    $cycles=array_map('intval',$wpdb->get_col("SELECT qualification_cycle FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} ORDER BY qualification_cycle"));idlm_http_check($cycles===range(1,6),'Repair did not converge');
    $task=(int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND qualification_cycle=3");
    foreach(array('idlm_toggle_task_status','idlm_delete_lead_task') as $action) {
        $before=idlm_http_snapshot();$r=idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>$action,'nonce'=>$sessions['admin']['admin'],'task_id'=>$task,'status'=>'completed'),'admin');$j=json_decode($r['body'],true);
        idlm_http_check(isset($j['success']) && $j['success']===false,'Reserved task action succeeded');idlm_http_check($before===idlm_http_snapshot(),'Denied task action mutated data');
    }
});
idlm_http_case('T04-H-CORE006-repair failure returns error while retaining recorded outreach for scheduled recovery',static function():void {
    global $wpdb;
    $lead=idlm_plan_http_fixture();$before=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} ORDER BY id",ARRAY_A);
    $r=idlm_atomic_http_trigger('idlm_tasks','INSERT',static function()use($lead){return idlm_plan_http_touch($lead);});$j=json_decode($r['body'],true);
    idlm_http_check(isset($j['success']) && $j['success']===false && strpos($j['data']['message'],'Outreach was recorded')!==false,'Repair failure falsely acknowledged: '.$r['body']);
    idlm_http_check($before===$wpdb->get_results("SELECT * FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} ORDER BY id",ARRAY_A),'Failed repair changed partial tasks');
    idlm_http_check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id={$lead} AND activity_type='whatsapp_outbound'")===1,'Already logged evidence disappeared or duplicated');
    idlm_http_check(IDLM_DB::sync_presales_task_status($lead),'Scheduled-equivalent recovery failed');
    idlm_http_check((int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead}")===6,'Recovery omitted cycle');
});
idlm_http_case('T03-H-CORE006-non-presales representative and foreign owner cannot trigger plan repair',static function():void {
    $lead=idlm_plan_http_fixture();foreach(array('owner','other') as $actor) {
        $before=idlm_http_snapshot();$r=idlm_plan_http_touch($lead,$actor);$j=json_decode($r['body'],true);idlm_http_check(isset($j['success']) && $j['success']===false,'Unauthorized outreach succeeded');idlm_http_check($before===idlm_http_snapshot(),'Denied outreach repaired or mutated plan');
    }
});
