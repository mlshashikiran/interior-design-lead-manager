<?php
/** Cookie-authenticated assignment targets and mutation choices. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
function idlm_assignment_http(int $lead,int $target,string $kind='quick',string $actor='admin',bool $nonce=true): array {
    global $sessions;
    $data=$kind==='quick'?array('action'=>'idlm_quick_edit_lead','lead_id'=>$lead,'field'=>'assigned_rep_id','value'=>$target):array('action'=>'idlm_bulk_action','bulk_action'=>'bulk_assign','ids'=>array($lead),'rep_id'=>$target);
    $data['nonce']=$nonce?$sessions[$actor]['admin']:'invalid';return idlm_http_request('/wp-admin/admin-ajax.php',$data,$actor);
}
foreach(array('disabled','direct_denial','role_lost','deleted') as $reason) {
    idlm_http_case('T03-H-CORE009-quick-and-bulk-reject-'.$reason.'-without-side-effects',static function()use($reason):void {
        global $wpdb;
        $target=wp_insert_user(array('user_login'=>'http-target-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(),'role'=>'sales_executive'));$lead=idlm_atomic_http_lead();
        if($reason==='disabled'){update_user_meta($target,'idlm_crm_access_disabled','1');}
        elseif($reason==='direct_denial'){(new WP_User($target))->add_cap('idlm_access_leads',false);}
        elseif($reason==='role_lost'){(new WP_User($target))->set_role('subscriber');}
        else {$wpdb->delete($wpdb->users,array('ID'=>$target));clean_user_cache($target);}
        foreach(array('quick','bulk') as $kind){$before=idlm_http_snapshot();$r=idlm_assignment_http($lead,$target,$kind);$j=json_decode($r['body'],true);idlm_http_check(is_array($j) && isset($j['success']) && $j['success']===false,'Ineligible assignment accepted: '.$r['body']);idlm_http_check($before===idlm_http_snapshot(),'Denied assignment changed aggregate, jobs or outbound attempts');}
    });
}
idlm_http_case('T03-H-CORE009-enabled-assignment-and-unassignment-work-while-actor-and-nonce-denials-remain',static function():void {
    global $wpdb,$users;$lead=idlm_atomic_http_lead();update_option('idlm_max_active_leads_per_rep',0);IDLM_Settings::sync_routing_from_legacy();
    foreach(array(array('owner',true),array('other',true),array('admin',false)) as [$actor,$nonce]) {$before=idlm_http_snapshot();$r=idlm_assignment_http($lead,$users['other'],'quick',$actor,$nonce);$j=json_decode($r['body'],true);idlm_http_check($r['status']===403 || (isset($j['success']) && $j['success']===false),'Unauthorized actor/nonce succeeded');idlm_http_check($before===idlm_http_snapshot(),'Actor/nonce denial changed data');}
    foreach(array(array('quick',$users['other']),array('bulk',$users['manager']),array('quick',0)) as [$kind,$target]) {$r=idlm_assignment_http($lead,$target,$kind);$j=json_decode($r['body'],true);idlm_http_check(!empty($j['success']),'Eligible assignment/unassignment failed: '.$r['body']);idlm_http_check((int)$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}")===$target,'Wrong owner persisted');}
});
idlm_http_case('T03-H-CORE009-admin-and-shortcode-assignment-selects-preserve-disabled-owner-history',static function():void {
    global $wpdb,$users,$sessions;
    $lead=idlm_atomic_http_lead();update_user_meta($users['owner'],'idlm_crm_access_disabled','1');update_option('idlm_active_assignment_reps',array($users['owner'],$users['other']));IDLM_Settings::sync_routing_from_legacy();
    try {
        foreach(array('/leads-workspace','/leads-admin-workspace') as $path) {$r=idlm_http_request($path,array(),'admin',false);idlm_http_check($r['status']===200,'Workspace failed');$html=$r['body'];preg_match('/<select[^>]*id="idlm-bulk-rep"[^>]*>(.*?)<\/select>/s',$html,$select);if(!$select){preg_match('/<select[^>]*id="bulk-rep"[^>]*>(.*?)<\/select>/s',$html,$select);}idlm_http_check(!empty($select),'Bulk representative select missing');idlm_http_check(strpos($select[1],'value="'.$users['owner'].'"')===false,'Disabled representative offered as bulk target');idlm_http_check(strpos($select[1],'value="'.$users['other'].'"')!==false,'Enabled target missing');}
        $r=idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_get_leads_table','nonce'=>$sessions['admin']['admin'],'search'=>'Synthetic atomic HTTP'),'admin');$j=json_decode($r['body'],true);$html=$j['data']['html']??'';
        idlm_http_check(preg_match('/<option value="'.$users['owner'].'"[^>]*disabled=[^>]*>/', $html)===1,'Current disabled owner not retained as disabled history');
    } finally {delete_user_meta($users['owner'],'idlm_crm_access_disabled');}
});
