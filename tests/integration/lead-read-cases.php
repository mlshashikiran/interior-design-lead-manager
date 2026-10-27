<?php
/** Live lead read authority HTTP cases (CORE-001/011, QUO-002). Builder: Shashi.
 * Included only by the owned disposable HTTP runner after quotation cases.
 */
if ('cli' !== PHP_SAPI || empty($config['db_name']) || strpos($config['db_name'], 'idlm_test_') !== 0) { exit(1); }
function idlm_read_ajax(string $action, string $actor = 'owner', array $payload = array()): array {
    global $sessions;
    $nonce = (strpos($action, 'quote') !== false || strpos($action, 'quotation') !== false) ? 'search' : 'admin';
    return idlm_http_request('/wp-admin/admin-ajax.php', array_merge($payload, array('action'=>$action, 'nonce'=>$sessions[$actor][$nonce])), $actor, $nonce === 'admin');
}
function idlm_read_data(array $response): array {
    $json=json_decode($response['body'],true);
    idlm_http_check($response['status']===200 && is_array($json) && !empty($json['success']), 'Expected authorized JSON: '.$response['body']);
    return $json['data'];
}
function idlm_read_quote_ids(string $actor, array $filters = array()): array { $data=idlm_read_data(idlm_read_ajax('idlm_filter_quotation_list',$actor,$filters)); return array_map('intval',array_column($data['rows'],'id')); }
function idlm_read_task_ids(string $actor, string $view = 'attention'): array { $data=idlm_read_data(idlm_read_ajax('idlm_get_rep_tasks',$actor,array('view'=>$view))); return array_map('intval',array_column($data['tasks'],'lead_id')); }
function idlm_read_deny(string $action, string $actor, array $payload = array()): void {
    idlm_http_denied(static function() use($action,$actor,$payload): array { $r=idlm_read_ajax($action,$actor,$payload); $json=json_decode($r['body'],true); idlm_http_check(is_array($json) && empty($json['success']),'Denied read returned success'); return $r; },array(200,403,404));
}
$read_lead=$leads[4]; $read_quote=$quotes[4]; $read_rev=$revisions[4];
$scopes=IDLM_Admin_Users_Controller::role_scopes(); $scopes['sales_manager']='all'; update_option('idlm_role_lead_scopes',$scopes);
wp_set_current_user($admin);
foreach (array('pending','completed') as $status) {
    $wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$read_lead,'assigned_rep_id'=>$users['owner'],'title'=>'READ-PII-SYNTHETIC-task','task_type'=>'General','priority'=>'High','status'=>$status,'due_date'=>current_time('mysql'),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')));
}
idlm_http_case('T05-H-quote-owner-list-count-before-reassignment',static function() use($read_quote,$read_lead): void {
    $data=idlm_read_data(idlm_read_ajax('idlm_filter_quotation_list','owner',array('lead_id'=>$read_lead)));
    idlm_http_check((int)$data['total']===1 && (int)$data['rows'][0]['id']===$read_quote,'Owner list/count mismatch');
});
foreach (array('attention','completed') as $view) {
    idlm_http_case('T04-H-task-owner-'.$view.'-before-reassignment',static function() use($view,$read_lead): void { idlm_http_check(in_array($read_lead,idlm_read_task_ids('owner',$view),true),'Missing own task'); });
}
$warm=idlm_read_data(idlm_read_ajax('idlm_get_dashboard_stats','owner',array('period'=>'today')));
$generation=IDLM_Read_Model_Cache::generation();
idlm_http_check(IDLM_DB::update_lead($read_lead,array('assigned_rep_id'=>$users['other']))!==false,'Canonical reassignment failed');
idlm_http_case('T06-H-warm-dashboard-reassignment-invalidates-generation',static function() use($generation,$warm): void {
    idlm_http_check($generation!==IDLM_Read_Model_Cache::generation(),'Ownership write did not invalidate cache');
    $after=idlm_read_data(idlm_read_ajax('idlm_get_dashboard_stats','owner',array('period'=>'today')));
    idlm_http_check((int)$after['sales']['open_pipeline_total']===(int)$warm['sales']['open_pipeline_total']-1,'Warmed former-owner aggregate retained reassigned lead');
});
foreach (array('owner'=>false,'other'=>true,'readall'=>true,'manager'=>true) as $actor=>$visible) {
    idlm_http_case('T05-H-live-quote-list-count-'.$actor,static function() use($actor,$visible,$read_quote,$read_lead): void {
        $data=idlm_read_data(idlm_read_ajax('idlm_filter_quotation_list',$actor,array('quote_search'=>'PII-SYNTHETIC-4')));
        idlm_http_check((int)$data['total']===($visible?1:0) && in_array($read_quote,array_map('intval',array_column($data['rows'],'id')),true)===$visible,'Live list/count scope mismatch');
        if (!$visible) { idlm_http_check(strpos(json_encode($data),'synthetic4@example.test')===false,'List disclosed reassigned contact'); }
        $page=idlm_http_request('/workspace',array('quote_search'=>'PII-SYNTHETIC-4'),$actor,false);
        idlm_http_check($page['status']===200 && (preg_match('~<tbody data-live-list-rows="quotations">(.*?)</tbody>~s',$page['body'],$match) && strpos($match[1],'PII-SYNTHETIC-4')!==false)===$visible,'Shortcode list differs from AJAX');
        $page=idlm_http_request('/admin-workspace',array('quote_search'=>'PII-SYNTHETIC-4'),$actor,false);
        idlm_http_check($page['status']===200 && (preg_match('~<tbody data-live-list-rows="quotations">(.*?)</tbody>~s',$page['body'],$match) && strpos($match[1],'PII-SYNTHETIC-4')!==false)===$visible,'Admin renderer differs from AJAX');
    });
    foreach (array('attention','completed') as $view) {
        idlm_http_case('T04-H-live-task-'.$view.'-'.$actor,static function() use($actor,$visible,$view,$read_lead): void { idlm_http_check(in_array($read_lead,idlm_read_task_ids($actor,$view),true)===$visible,'Task attribution overrode live read scope'); });
    }
}
foreach (array('idlm_get_lead_details','idlm_get_lead_tasks') as $action) { idlm_http_case('T05-H-reassigned-deny-'.$action,static function() use($action,$read_lead): void { idlm_read_deny($action,'owner',array('lead_id'=>$read_lead)); }); }
// An assigned-only manager still has explicit mutation authority, but narrower reads.
$scopes['sales_manager']='assigned'; update_option('idlm_role_lead_scopes',$scopes);
idlm_http_case('T05-H-assigned-manager-quote-and-task-read-scope',static function() use($read_quote,$read_lead): void {
    idlm_http_check(!in_array($read_quote,idlm_read_quote_ids('manager'),true),'Manager bypassed assigned quote scope');
    idlm_http_check(!in_array($read_lead,idlm_read_task_ids('manager'),true),'Manager bypassed assigned task scope');
    idlm_read_deny('idlm_get_lead_details','manager',array('lead_id'=>$read_lead));
});
// Mixed-role precedence is Assigned before All; pre-sales stays quotation-excluded.
foreach (array('mixed','presales','mixedpresales') as $actor) {
    $roles=$actor==='mixed' ? array('sales_executive','author') : ($actor==='presales' ? array('idlm_pre_sales') : array('sales_manager','idlm_pre_sales'));
    $users[$actor]=(int)wp_insert_user(array('user_login'=>'fixture_'.$actor,'user_pass'=>'idlm-synthetic-password','user_email'=>$actor.'@example.test','role'=>$roles[0]));
    $u=new WP_User($users[$actor]); foreach(array_slice($roles,1) as $role) { $u->add_role($role); }
    $u->add_cap('idlm_access_leads'); if($actor==='mixed') { foreach($caps as $cap=>$grant){$u->add_cap($cap);} }
    if($actor==='mixedpresales') { $u->add_cap('idlm_assign_leads'); $u->add_cap('idlm_view_quotes'); }
    idlm_http_request('/login',array('user_login'=>'fixture_'.$actor,'user_password'=>'idlm-synthetic-password'),$actor);
    $sessions[$actor]=json_decode(idlm_http_request('/session',array(),$actor,false)['body'],true);
}
idlm_http_case('T05-H-mixed-assigned-role-wins-over-all',static function() use($read_quote,$read_lead): void { idlm_http_check(!in_array($read_quote,idlm_read_quote_ids('mixed'),true) && !in_array($read_lead,idlm_read_task_ids('mixed'),true),'Mixed role union bypassed assigned scope'); });
foreach(array('pending','completed') as $status) { $wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$leads[1],'assigned_rep_id'=>$users['other'],'title'=>'FOREIGN-PII-SYNTHETIC-task','task_type'=>'General','priority'=>'High','status'=>$status,'due_date'=>current_time('mysql'),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql'))); }
$wpdb->update($wpdb->prefix.'idlm_leads',array('intake_owner_id'=>$users['presales'],'qualified_by_user_id'=>$users['presales']),array('id'=>$read_lead));
idlm_http_case('T04-H-presales-historical-association-pending-completed',static function() use($read_lead,$leads): void {
    foreach(array('attention','completed') as $view) { $ids=idlm_read_task_ids('presales',$view); idlm_http_check(in_array($read_lead,$ids,true) && !in_array($leads[1],$ids,true),'Historical pre-sales scope mismatch'); }
    idlm_read_data(idlm_read_ajax('idlm_get_lead_tasks','presales',array('lead_id'=>$read_lead)));
});
foreach(array('presales','mixedpresales') as $actor) { idlm_http_case('T10-H-presales-quote-exclusion-'.$actor,static function() use($actor): void { idlm_read_deny('idlm_filter_quotation_list',$actor); }); }
// Warm a cursor and dashboard, then revoke authority with real WP user/role APIs.
for($i=0;$i<28;$i++) { $now=current_time('mysql'); $wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'CURSOR-PII-SYNTHETIC-'.$i,'phone'=>'+1555111'.str_pad((string)$i,4,'0',STR_PAD_LEFT),'assigned_rep_id'=>$users['owner'],'stage'=>'New','created_at'=>$now,'updated_at'=>$now)); }
$cursor_data=idlm_read_data(idlm_read_ajax('idlm_get_leads_table','owner')); $cursor=$cursor_data['next_cursor']; idlm_http_check($cursor!=='','Fixture did not produce real lead cursor');
idlm_http_case('T05-H-authorized-cursor-next-page',static function() use($cursor): void { $data=idlm_read_data(idlm_read_ajax('idlm_get_leads_table','owner',array('cursor'=>$cursor,'page'=>2))); idlm_http_check($data['pagination_mode']==='cursor' && strpos($data['html'],'CURSOR-PII-SYNTHETIC')!==false,'Valid cursor did not advance'); });
foreach(array('capability','role-capability','suspension') as $mode) {
    $u=new WP_User($users['owner']);
    idlm_read_data(idlm_read_ajax('idlm_get_dashboard_stats','owner')); $old_generation=IDLM_Read_Model_Cache::generation();
    if($mode==='capability') { $u->add_cap('idlm_access_leads',false); }
    elseif($mode==='role-capability') { $u->remove_cap('idlm_access_leads'); get_role('sales_executive')->remove_cap('idlm_access_leads'); }
    else { update_user_meta($users['owner'],'idlm_crm_access_disabled','1'); }
    idlm_http_case('T01-H-'.$mode.'-invalidates-warmed-cache',static function() use($old_generation): void { idlm_http_check($old_generation!==IDLM_Read_Model_Cache::generation(),'Access edit did not invalidate cache generation'); });
    foreach(array('idlm_get_leads_table','idlm_get_rep_tasks','idlm_get_dashboard_stats','idlm_get_lead_details','idlm_get_lead_tasks','idlm_filter_quotation_list','idlm_search_quote_leads') as $action) {
        idlm_http_case('T01-H-'.$mode.'-deny-'.$action,static function() use($action,$read_lead,$cursor): void { idlm_read_deny($action,'owner',array('lead_id'=>$read_lead,'cursor'=>$cursor,'page'=>2,'term'=>'PII')); });
    }
    idlm_http_case('T10-H-'.$mode.'-deny-owner-save-autosave',static function() use($leads,$quotes,$revisions): void { idlm_http_denied(static function() use($leads){return idlm_http_save(idlm_http_payload($leads[5]));}); idlm_http_denied(static function() use($leads,$quotes,$revisions){return idlm_http_autosave(idlm_http_payload($leads[4],$quotes[4],$revisions[4]));}); });
    $u->add_cap('idlm_access_leads'); get_role('sales_executive')->add_cap('idlm_access_leads'); delete_user_meta($users['owner'],'idlm_crm_access_disabled');
}
// A captured All-leads cursor must not survive a narrower role scope.
$all_data=idlm_read_data(idlm_read_ajax('idlm_get_leads_table','readall')); $all_cursor=$all_data['next_cursor']; idlm_http_check($all_cursor!=='','All-leads cursor missing');
$old_generation=IDLM_Read_Model_Cache::generation(); $scopes['author']='assigned'; update_option('idlm_role_lead_scopes',$scopes);
idlm_http_case('T05-H-role-scope-revokes-captured-cursor-and-global-cache',static function() use($all_cursor,$old_generation,$read_quote): void {
    idlm_http_check($old_generation!==IDLM_Read_Model_Cache::generation(),'Scope update did not invalidate cache');
    $r=idlm_read_ajax('idlm_get_leads_table','readall',array('cursor'=>$all_cursor,'page'=>2)); $json=json_decode($r['body'],true);
    idlm_http_check($r['status']===400 && empty($json['success']) && strpos($r['body'],'PII-SYNTHETIC')===false,'Old global cursor still usable');
    idlm_http_check(!in_array($read_quote,idlm_read_quote_ids('readall'),true),'Read-all list retained revoked scope');
});
$scopes['author']='all'; $scopes['sales_manager']='all'; update_option('idlm_role_lead_scopes',$scopes);
// Restore the browser-owned quote; completed tasks remain attribution history.
IDLM_DB::update_lead($read_lead,array('assigned_rep_id'=>$users['owner']));
// Actual independent HTTP process paused immediately before its joined read.
foreach(array('tasks','quotes','dashboard') as $kind) {
    idlm_http_case('T05-C-live-read-during-reassignment-'.$kind,static function() use($kind,$read_lead,$users,$sessions,$config): void {
        global $wpdb;
        wp_set_current_user($GLOBALS['admin']);
        IDLM_DB::update_lead($read_lead,array('assigned_rep_id'=>$users['owner'],'source'=>'READ-BARRIER-ONLY'));
        $barrier=$config['content_dir'].'/read-barrier'; file_put_contents($barrier,$kind);
        $action=$kind==='quotes'?'idlm_filter_quotation_list':($kind==='tasks'?'idlm_get_rep_tasks':'idlm_get_dashboard_stats');
        $payload=array('action'=>$action,'nonce'=>$sessions['owner'][$kind==='quotes'?'search':'admin'],'quote_search'=>'PII-SYNTHETIC-4','source'=>'READ-BARRIER-ONLY');
        $url=$config['http_url'].'/wp-admin/admin-ajax.php';
        $args=array('curl','--silent','--show-error','--max-time','15','--cookie',$config['content_dir'].'/owner.cookies','--output',$barrier.'.body','--write-out','%{http_code}');
        if($kind==='quotes'){$url.='?'.http_build_query($payload);}else{$args[]='--data';$args[]=http_build_query($payload);} $args[]=$url;
        $proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
        try {
            idlm_http_check(is_resource($proc),'Read worker unavailable'); $deadline=microtime(true)+10;
            while(!is_file($barrier.'.ready') && microtime(true)<$deadline){usleep(10000);} idlm_http_check(is_file($barrier.'.ready'),'Read worker missed barrier');
            $generation=IDLM_Read_Model_Cache::generation(); IDLM_DB::update_lead($read_lead,array('assigned_rep_id'=>$users['other']));
            $snapshot=idlm_http_snapshot(); file_put_contents($barrier.'.release','go');
            $status=(int)stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]); $body=file_get_contents($barrier.'.body');
            $data=idlm_read_data(array('status'=>$status,'body'=>$body));
            idlm_http_check(strpos($body,'PII-SYNTHETIC-4')===false && strpos($body,'synthetic4@example.test')===false,'Synchronized read disclosed former lead');
            idlm_http_check($snapshot===idlm_http_snapshot(),'Read worker changed domain/jobs state: '.$error);
            if($kind==='dashboard') {
                idlm_http_check($generation!==IDLM_Read_Model_Cache::generation(),'Canonical reassignment did not fence old cache fill');
                $next=idlm_read_data(idlm_read_ajax('idlm_get_dashboard_stats','owner',array('source'=>'READ-BARRIER-ONLY')));
                idlm_http_check((int)$next['sales']['open_pipeline_total']===0,'Late cache fill remained reachable');
            }
        } finally {
            file_put_contents($barrier.'.release','go'); if(is_resource($proc)){proc_terminate($proc);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);} foreach(glob($barrier.'*') as $file){unlink($file);}
            IDLM_DB::update_lead($read_lead,array('assigned_rep_id'=>$users['owner']));
        }
    });
}
// Default content accounts remain ordinary WordPress accounts, without CRM PII.
foreach(array('editor','author','contributor','subscriber') as $role) {
    $actor='plain_'.$role; $users[$actor]=(int)wp_insert_user(array('user_login'=>'fixture_'.$actor,'user_pass'=>'idlm-synthetic-password','user_email'=>$actor.'@example.test','role'=>$role));
    idlm_http_request('/login',array('user_login'=>'fixture_'.$actor,'user_password'=>'idlm-synthetic-password'),$actor);
    $sessions[$actor]=json_decode(idlm_http_request('/session',array(),$actor,false)['body'],true);
    foreach(array('idlm_get_leads_table','idlm_get_rep_tasks','idlm_get_dashboard_stats','idlm_filter_quotation_list') as $action) {
        idlm_http_case('T01-H-default-'.$role.'-deny-'.$action,static function() use($action,$actor): void { idlm_read_deny($action,$actor); });
    }
}
idlm_http_case('T01-H-explicit-catalogue-only-editor-keeps-non-customer-workflow',static function() use($users,$sessions): void {
    (new WP_User($users['plain_editor']))->add_cap('idlm_manage_quote_items');
    idlm_read_data(idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_filter_catalogue_list','nonce'=>$sessions['plain_editor']['search']),'plain_editor',false));
    idlm_read_deny('idlm_filter_quotation_list','plain_editor');
});
idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin');
$sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
idlm_http_case('T01-H-administrator-explicit-editorial-user-enrollment',static function() use($read_quote): void {
    global $users,$sessions;
    $r=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_create_user','_wpnonce'=>$sessions['admin']['create_user'],'user_login'=>'fixture_enrolled_author','email'=>'enrolled-author@example.test','role'=>'author','first_name'=>'Synthetic CRM Enrolled'),'admin');
    idlm_http_check($r['status']===302 && strpos($r['headers'],'created')!==false,'CRM user create failed: '.$r['body'].$r['headers']);
    $user=get_user_by('login','fixture_enrolled_author'); idlm_http_check($user && user_can($user,'idlm_access_leads') && !get_role('author')->has_cap('idlm_access_leads'),'Enrollment affected role or failed individual grant');
    wp_set_password('idlm-synthetic-password',$user->ID); $users['enrolled_author']=(int)$user->ID;
    idlm_http_request('/login',array('user_login'=>'fixture_enrolled_author','user_password'=>'idlm-synthetic-password'),'enrolled_author');
    $sessions['enrolled_author']=json_decode(idlm_http_request('/session',array(),'enrolled_author',false)['body'],true);
    idlm_http_check(in_array($read_quote,idlm_read_quote_ids('enrolled_author'),true),'Explicit enrollee lost preserved per-role All scope');
    idlm_read_deny('idlm_filter_quotation_list','plain_author');
});
(new WP_User($users['manager']))->add_cap('idlm_access_leads',false);
idlm_http_case('T01-H-manager-access-revocation-denies-despite-assignment-grant',static function() use($read_lead): void { foreach(array('idlm_get_rep_tasks','idlm_get_dashboard_stats','idlm_filter_quotation_list') as $action){idlm_read_deny($action,'manager',array('lead_id'=>$read_lead));} });
(new WP_User($users['manager']))->add_cap('idlm_access_leads');
