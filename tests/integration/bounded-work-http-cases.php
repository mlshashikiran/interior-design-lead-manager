<?php
/** CORE-012 actual paged requests and truthful CSV failures. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
$wpdb->query("DELETE FROM {$wpdb->prefix}idlm_tasks");
$bounded_lead=idlm_atomic_http_lead();
$wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['owner'],'stage'=>'Contacted'),array('id'=>$bounded_lead));
for($i=0;$i<123;$i++){$wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$bounded_lead,'title'=>'Synthetic paged work '.($i+1),'task_type'=>'General','priority'=>'Medium','status'=>'pending','due_date'=>current_time('mysql')));}
idlm_http_case('T05-H-CORE012-task-page-navigation-counts-and-cursor-denials',static function()use($bounded_lead):void {
    $a=idlm_read_data(idlm_read_ajax('idlm_get_rep_tasks','owner'));idlm_http_check(count($a['tasks'])===50&&$a['attention']['due_today']===123&&$a['has_more'],'First task page/count not bounded');
    $b=idlm_read_data(idlm_read_ajax('idlm_get_rep_tasks','owner',array('cursor'=>$a['next_cursor'])));$c=idlm_read_data(idlm_read_ajax('idlm_get_rep_tasks','owner',array('cursor'=>$b['next_cursor'])));
    $ids=array_column(array_merge($a['tasks'],$b['tasks'],$c['tasks']),'id');idlm_http_check(count($c['tasks'])===23&&!$c['has_more']&&count(array_unique($ids))===123,'Task pages omit/duplicate cards');
    foreach(array(array('other',$a['next_cursor'],'attention'),array('owner',$a['next_cursor'].'x','attention'),array('owner',$a['next_cursor'],'completed'))as[$actor,$cursor,$view]){$r=idlm_read_ajax('idlm_get_rep_tasks',$actor,array('cursor'=>$cursor,'view'=>$view));$j=json_decode($r['body'],true);idlm_http_check($r['status']===400&&empty($j['success']),'Invalid task cursor succeeded');}
    $badge=idlm_read_data(idlm_read_ajax('idlm_get_rep_tasks','owner',array('counts_only'=>1)));idlm_http_check($badge['tasks']===array()&&$badge['attention']['due_today']===123,'Badge allocated cards or lost total');
});
idlm_http_case('T05-H-CORE012-task-next-page-rechecks-reassigned-lead',static function()use($bounded_lead,$users):void {
    global $wpdb;$a=idlm_read_data(idlm_read_ajax('idlm_get_rep_tasks','owner'));$wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['other']),array('id'=>$bounded_lead));
    try{$b=idlm_read_data(idlm_read_ajax('idlm_get_rep_tasks','owner',array('cursor'=>$a['next_cursor'])));idlm_http_check($b['tasks']===array()&&$b['attention']['due_today']===0,'Former owner retained task PII');}finally{$wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['owner']),array('id'=>$bounded_lead));}
});
$bounded_csv_source='CORE012-CSV-'.wp_generate_uuid4();$bounded_csv_ids=array();
for($i=0;$i<230;$i++){$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'=CORE012 synthetic '.$i,'source'=>$bounded_csv_source,'stage'=>'New','created_at'=>current_time('mysql')));$bounded_csv_ids[]=(int)$wpdb->insert_id;}
idlm_http_case('T12-H-CORE012-CSV-230-rows-cross-three-keyset-reads-with-formula-safety',static function()use($bounded_csv_source,$bounded_csv_ids):void {
    $rows=idlm_csv_parse(idlm_csv_download(array('source'=>$bounded_csv_source)));idlm_http_check(count($rows)===231,'Multipage CSV dropped rows');$ids=array_map('intval',array_column(array_slice($rows,1),0));idlm_http_check($ids===array_reverse($bounded_csv_ids),'CSV keyset order changed');foreach(array_slice($rows,1)as$row){idlm_http_check(strpos($row[1],"'=CORE012")===0,'Formula sanitizer lost across pages');}
});
idlm_http_case('T12-H-CORE012-CSV-second-read-failure-sends-no-attachment-or-partial-body',static function()use($bounded_csv_source):void {
    global $config;$fault=$config['content_dir'].'/fail-export-next-page';file_put_contents($fault,'owned');
    try{$r=idlm_csv_download(array('source'=>$bounded_csv_source));idlm_http_check($r['status']===503&&strpos($r['headers'],'text/csv')===false&&strpos($r['headers'],'attachment')===false&&strpos($r['body'],'CORE012 synthetic')===false,'Failed export sent partial CSV');}finally{unlink($fault);}
    idlm_http_check(count(idlm_csv_parse(idlm_csv_download(array('source'=>$bounded_csv_source))))===231,'Export failed to retry');
});
idlm_http_case('T05-H-CORE012-WhatsApp-filters-use-current-read-state-on-paged-list-and-CSV',static function()use($bounded_lead):void {
    global $wpdb,$admin;$wpdb->insert($wpdb->prefix.'idlm_lead_activity_log',array('lead_id'=>$bounded_lead,'activity_type'=>'comment','details'=>'[WhatsApp Reply]: Synthetic bounded reply','created_at'=>current_time('mysql')));$activity=(int)$wpdb->insert_id;
    $r=idlm_csv_parse(idlm_csv_download(array('followup_filter'=>'unread_whatsapp')));idlm_http_check(in_array($bounded_lead,array_map('intval',array_column(array_slice($r,1),0)),true),'Unread CSV omitted reply');
    $prior=get_user_meta($admin,'idlm_whatsapp_read_activity_ids',true);update_user_meta($admin,'idlm_whatsapp_read_activity_ids',array($bounded_lead=>$activity));
    try{$r=idlm_csv_parse(idlm_csv_download(array('followup_filter'=>'unread_whatsapp')));idlm_http_check(!in_array($bounded_lead,array_map('intval',array_column(array_slice($r,1),0)),true),'Read CSV retained reply');}finally{update_user_meta($admin,'idlm_whatsapp_read_activity_ids',$prior);}
});
