<?php
/** Native legacy preservation, repair faults and synchronized writers. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_plan_legacy(int $lead, int $owner, int $missing = 0, string $label = 'cycle'): array {
    global $wpdb;
    for ($cycle=1;$cycle<=6;$cycle++) {
        if ($cycle===$missing) { continue; }
        idlm_integration_assert_same(1,$wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$lead,'assigned_rep_id'=>$owner,
            'title'=>'Qualification '.$label.' '.$cycle.': historical target','task_type'=>'Qualification','status'=>$cycle===1?'completed':'pending',
            'due_date'=>'2026-08-2'.$cycle.' 18:00:00','created_at'=>'2026-08-20 09:00:00','updated_at'=>'2026-08-20 09:00:00','completed_at'=>$cycle===1?'2026-08-21 18:00:00':null)));
    }
    return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} ORDER BY id",ARRAY_A);
}
function idlm_plan_slots(int $lead): array {
    global $wpdb;
    return array_map('intval',$wpdb->get_col("SELECT qualification_cycle FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND task_type='Qualification' ORDER BY qualification_cycle"));
}
function idlm_plan_evidence(int $lead, int $cycles = 1): void {
    global $wpdb;
    $targets=IDLM_DB::get_presales_outreach_targets();
    $types=array_merge(array_fill(0,$targets['calls'],'outbound_call'),array_fill(0,$targets['whatsapp'],'whatsapp_outbound'));
    for($day=1;$day<=$cycles;$day++) {
        foreach($types as $type) {
            idlm_integration_assert_same(1,$wpdb->insert($wpdb->prefix.'idlm_lead_activity_log',array('lead_id'=>$lead,'activity_type'=>$type,'details'=>'Synthetic outreach','created_at'=>'2026-08-2'.$day.' 12:00:00')));
        }
    }
}

idlm_integration_test('T04-N-CORE006-every historical missing cycle converges once and preserves existing history',static function():void {
    global $wpdb,$administrator_id;
    for($missing=1;$missing<=6;$missing++) {
        $lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));$before=idlm_plan_legacy($lead,$administrator_id,$missing);
        idlm_integration_assert_same(true,IDLM_DB::create_presales_followup_tasks($lead,999));idlm_integration_assert_same(range(1,6),idlm_plan_slots($lead));
        foreach($before as $old) {$row=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}idlm_tasks WHERE id={$old['id']}",ARRAY_A);$row['qualification_cycle']=null;idlm_integration_assert_same($old,$row);}
        $snapshot=idlm_lifecycle_snapshot($lead);idlm_integration_assert_same(true,IDLM_DB::create_presales_followup_tasks($lead,999));idlm_integration_assert_same($snapshot,idlm_lifecycle_snapshot($lead));
        idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id={$lead} AND details LIKE 'Repaired%'") );
    }
});

idlm_integration_test('T17-N-CORE006-old four-day plans become six cycles without rewriting old rows',static function():void {
    global $wpdb,$administrator_id;
    $lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));idlm_plan_legacy($lead,$administrator_id,6,'day');
    $wpdb->query("DELETE FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND title LIKE 'Qualification day 5:%'");
    idlm_integration_assert_same(true,IDLM_DB::create_presales_followup_tasks($lead,$administrator_id));idlm_integration_assert_same(range(1,6),idlm_plan_slots($lead));
    idlm_integration_assert_same(4,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND title LIKE 'Qualification day%'") );
});

idlm_integration_test('T04-N-CORE006-required adoption, missing insert, activity, intent and commit faults preserve partial plan',static function():void {
    global $wpdb,$administrator_id;
    foreach(array(array('idlm_tasks','UPDATE'),array('idlm_tasks','INSERT'),array('idlm_lead_activity_log','INSERT'),array('idlm_lead_outbox','INSERT'),array('commit','COMMIT')) as $fault) {
        $lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));idlm_plan_legacy($lead,$administrator_id,3);$before=idlm_lifecycle_snapshot($lead);
        $work=static function()use($lead,$administrator_id){return IDLM_DB::create_presales_followup_tasks($lead,$administrator_id);};
        if($fault[0]==='commit') {$filter=static function($sql){return trim($sql)==='COMMIT'?'SELECT * FROM idlm_test_missing_commit':$sql;};$result=idlm_schema_filter($filter,$work);}
        else {$result=idlm_lifecycle_failure($fault[0],$fault[1],$work);}
        idlm_integration_assert_same(false,$result);idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));idlm_integration_assert_same(true,$work());idlm_integration_assert_same(range(1,6),idlm_plan_slots($lead));
    }
});

idlm_integration_test('T04-N-CORE006-ambiguous duplicate and unknown legacy labels fail without changing history',static function():void {
    global $wpdb,$administrator_id;
    foreach(array('Qualification cycle 2: duplicate','Unknown historical task') as $title) {
        $lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));idlm_plan_legacy($lead,$administrator_id,3);
        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}idlm_tasks SET title=%s WHERE lead_id=%d AND title LIKE 'Qualification cycle 1:%'",$title,$lead));
        $before=idlm_lifecycle_snapshot($lead);idlm_integration_assert_same(false,IDLM_DB::create_presales_followup_tasks($lead,$administrator_id));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
    }
});

idlm_integration_test('T04-N-CORE006-evidence completion, reopening and overdue working-day progression use stable cycles',static function():void {
    global $wpdb,$administrator_id;
    $lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));idlm_plan_legacy($lead,$administrator_id,3);idlm_plan_evidence($lead,2);
    idlm_integration_assert_same(true,IDLM_DB::sync_presales_task_status($lead));idlm_integration_assert_same(range(1,6),idlm_plan_slots($lead));
    $tasks=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} ORDER BY qualification_cycle",ARRAY_A);
    idlm_integration_assert_same('2026-08-21 18:00:00',$tasks[0]['completed_at']);idlm_integration_assert_same('completed',$tasks[1]['status']);
    $dates=array();foreach(array_slice($tasks,2) as $task){idlm_integration_assert_same('pending',$task['status']);$day=substr($task['due_date'],0,10);idlm_integration_assert_true($day>=current_time('Y-m-d'));idlm_integration_assert_true(in_array((int)(new DateTime($day))->format('N'),IDLM_DB::get_normalized_working_days(),true));$dates[]=$day;}idlm_integration_assert_same(4,count(array_unique($dates)));
    $wpdb->query("DELETE FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id={$lead} AND activity_type IN ('outbound_call','whatsapp_outbound')");
    idlm_integration_assert_same(true,IDLM_DB::sync_presales_task_status($lead));idlm_integration_assert_same(6,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND status='pending' AND completed_at IS NULL"));
    $before=idlm_lifecycle_snapshot($lead);idlm_integration_assert_same(true,IDLM_DB::sync_presales_task_status($lead));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
});

idlm_integration_test('T03-N-CORE006-active reassignment transfers pending cycles and preserves completed owner history',static function():void {
    global $wpdb,$administrator_id;
    $next=wp_create_user('plan-owner-'.wp_generate_uuid4(),wp_generate_password(),'plan-'.wp_generate_uuid4().'@example.test');
    (new WP_User($next))->set_role('idlm_pre_sales');
    $lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id,'intake_owner_id'=>$administrator_id));idlm_plan_legacy($lead,$administrator_id,3);idlm_plan_evidence($lead);
    idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$next)));
    idlm_integration_assert_same(5,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND status='pending' AND assigned_rep_id={$next}"));
    idlm_integration_assert_same((string)$administrator_id,$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND qualification_cycle=1"));
});

idlm_integration_test('T03-N-CORE006-automatic assignment repairs partial unassigned work in the checked aggregate',static function():void {
    global $wpdb,$administrator_id;
    $lead=idlm_lifecycle_fixture();idlm_plan_legacy($lead,0,3);idlm_plan_evidence($lead);
    $method=new ReflectionMethod('IDLM_Integrations','assign_lead_atomically');if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); }
    $target=wp_insert_user(array('user_login'=>'plan-intake-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(),'role'=>'idlm_pre_sales'));
    $args=array($lead,0,$target,'Synthetic recovered intake assignment',false,'intake');
    $before=idlm_lifecycle_snapshot($lead);
    idlm_integration_assert_same(false,idlm_lifecycle_failure('idlm_tasks','INSERT',static function()use($method,$args){return $method->invokeArgs(null,$args);}));
    idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
    idlm_integration_assert_same(true,$method->invokeArgs(null,$args));idlm_integration_assert_same(range(1,6),idlm_plan_slots($lead));
    idlm_integration_assert_same(5,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND status='pending' AND assigned_rep_id={$target}"));
    idlm_integration_assert_same('0',$wpdb->get_var("SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND qualification_cycle=1"));
});

idlm_integration_test('T04-N-CORE006-closed states and duplicates do not repair or reopen; explicit active reversal resumes slots',static function():void {
    global $wpdb,$administrator_id;
    // This task-policy fixture seeds many administrator-owned leads; capacity is tested separately.
    update_option('idlm_max_active_leads_per_rep',0);
    foreach(array(array('stage'=>'Won'),array('stage'=>'Lost'),array('lead_quality'=>'Qualified'),array('lead_quality'=>'Unqualified'),array('is_duplicate'=>1)) as $state) {
        $lead=idlm_lifecycle_fixture(array_merge(array('assigned_rep_id'=>$administrator_id),$state));idlm_plan_legacy($lead,$administrator_id,3);$before=idlm_lifecycle_snapshot($lead);
        idlm_integration_assert_same(true,IDLM_DB::create_presales_followup_tasks($lead,$administrator_id));idlm_integration_assert_same(true,IDLM_DB::sync_presales_task_status($lead));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
    }
    $lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));idlm_plan_legacy($lead,$administrator_id,3);
    idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('stage'=>'Lost','lost_reason'=>'Synthetic close')));
    idlm_integration_assert_same(4,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND status='cancelled'"));
    idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('stage'=>'Contacted')));idlm_integration_assert_same(range(1,6),idlm_plan_slots($lead));
    idlm_integration_assert_same(6,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND status='pending'"));
});

idlm_integration_test('T04-C-CORE006-two synchronized repair workers create each missing slot and repair activity once',static function():void {
    global $wpdb,$administrator_id;$lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));idlm_plan_legacy($lead,$administrator_id,3);
    $results=idlm_lifecycle_pair(array('task-plan-race',$lead,$administrator_id),array('task-plan-race',$lead,$administrator_id));
    foreach($results as $r){idlm_integration_assert_same(0,$r['status'],$r['stderr']);idlm_integration_assert_same('complete',$r['stdout']);}
    idlm_integration_assert_same(range(1,6),idlm_plan_slots($lead));idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id={$lead} AND details LIKE 'Repaired%'"));
});

idlm_integration_test('T04-C-CORE006-repair racing Lost closure leaves no pending qualification work',static function():void {
    global $wpdb,$administrator_id;$lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));idlm_plan_legacy($lead,$administrator_id,3);
    $results=idlm_lifecycle_pair(array('task-plan-race',$lead,$administrator_id),array('lead-update-race',$lead,'Lost'));foreach($results as $r){idlm_integration_assert_same(0,$r['status'],$r['stderr']);}
    idlm_integration_assert_same('Lost',$wpdb->get_var("SELECT stage FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));idlm_integration_assert_same(0,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND status='pending'"));
});

idlm_integration_test('T17-N-CORE006-additive upgrade preserves old rows and verifies nullable cycle uniqueness',static function():void {
    global $wpdb,$administrator_id;
    $lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));$before=idlm_plan_legacy($lead,$administrator_id,3);
    try {
        idlm_integration_assert_true(false!==$wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks DROP INDEX lead_qualification_cycle, DROP COLUMN qualification_cycle"));
        update_option('idlm_schema_version','1.3.7',false);
        idlm_integration_assert_same(true,IDLM_DB::init());idlm_integration_assert_same(IDLM_DB_VERSION,get_option('idlm_schema_version'));
        $after=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} ORDER BY id",ARRAY_A);
        foreach($before as &$row){ksort($row);}unset($row);foreach($after as &$row){ksort($row);}unset($row);
        idlm_integration_assert_same($before,$after);
        idlm_integration_assert_same(true,IDLM_DB::init());idlm_integration_assert_same(true,IDLM_DB::create_presales_followup_tasks($lead,$administrator_id));idlm_integration_assert_same(range(1,6),idlm_plan_slots($lead));
        $wpdb->suppress_errors(true);$duplicate=$wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$lead,'title'=>'Duplicate slot','task_type'=>'Qualification','qualification_cycle'=>3));$wpdb->suppress_errors(false);idlm_integration_assert_same(false,$duplicate);
        idlm_integration_assert_same(1,$wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$lead,'title'=>'Ordinary manual task','task_type'=>'General')));
    } finally {IDLM_DB::create_tables();update_option('idlm_schema_version',IDLM_DB_VERSION,false);}
});

idlm_integration_test('T14-N-CORE006-checkpointed sweep discovers empty plans and does not starve later pages',static function():void {
    global $wpdb,$administrator_id;
    $start=(int)$wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}idlm_leads");$ids=array();
    for($i=0;$i<101;$i++) {$ids[]=idlm_lifecycle_fixture(array('assigned_rep_id'=>$administrator_id));}
    update_option('idlm_presales_plan_cursor',$start,false);idlm_integration_assert_same(true,IDLM_DB::roll_forward_presales_tasks());
    idlm_integration_assert_same(range(1,6),idlm_plan_slots($ids[99]));idlm_integration_assert_same(array(),idlm_plan_slots($ids[100]));
    idlm_integration_assert_same(true,IDLM_DB::roll_forward_presales_tasks());idlm_integration_assert_same(range(1,6),idlm_plan_slots($ids[100]));idlm_integration_assert_same(0,(int)get_option('idlm_presales_plan_cursor'));
});

idlm_integration_test('T16-N-CORE006-backup roundtrip retains cycles and complete prior manifests normalize safely',static function():void {
    global $wpdb,$administrator_id;
    idlm_native_backup_fixture();$lead=(int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_leads LIMIT 1");
    idlm_integration_assert_same(true,IDLM_DB::create_presales_followup_tasks($lead,$administrator_id));$payload=idlm_native_backup_call('snapshot');idlm_integration_assert_true(!is_wp_error($payload));
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$payload));idlm_integration_assert_same(range(1,6),idlm_plan_slots($lead));
    foreach(array('1.3.5','1.3.6','1.3.7') as $version) {
        $old=$payload;$old['schema']['core']=$version;unset($old['schema']['columns']['idlm_tasks']['qualification_cycle']);
        foreach($old['tables']['idlm_tasks'] as &$row){unset($row['qualification_cycle']);}unset($row);
        idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$old));
        idlm_integration_assert_same(6,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND qualification_cycle IS NULL"));
        idlm_integration_assert_same(true,IDLM_DB::create_presales_followup_tasks($lead,$administrator_id));idlm_integration_assert_same(range(1,6),idlm_plan_slots($lead));
    }
    foreach(array('invalid_cycle','duplicate_cycle','current_missing_column') as $kind) {
        $bad=$payload;
        if($kind==='invalid_cycle'){$bad['tables']['idlm_tasks'][0]['qualification_cycle']='7';}
        elseif($kind==='duplicate_cycle'){$bad['tables']['idlm_tasks'][1]['qualification_cycle']=$bad['tables']['idlm_tasks'][0]['qualification_cycle'];}
        else {unset($bad['schema']['columns']['idlm_tasks']['qualification_cycle']);foreach($bad['tables']['idlm_tasks'] as &$row){unset($row['qualification_cycle']);}unset($row);}
        $before=idlm_native_backup_digest();idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload',$bad)));idlm_integration_assert_same($before,idlm_native_backup_digest());
    }
});
