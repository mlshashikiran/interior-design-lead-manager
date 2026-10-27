<?php
/** CORE-012 bounded reads, retention checkpoints and recovery. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }
function idlm_bound_reset(): void {
    global $wpdb,$administrator_id;
    wp_set_current_user($administrator_id);
    foreach (array('idlm_lead_touchpoints','idlm_inbound_submission_reviews','idlm_capi_logs','idlm_audience_sync_logs','idlm_telemetry_logs','idlm_lead_activity_log','idlm_tasks') as $suffix) { $wpdb->query("DELETE FROM {$wpdb->prefix}{$suffix}"); }
    delete_option(IDLM_Data_Retention::STATUS_OPTION); update_option('idlm_retention_policy',array('touchpoints_days'=>90,'integration_logs_days'=>180,'telemetry_days'=>14));
}
function idlm_bound_expired(int $n=101): void {
    global $wpdb;
    for($i=0;$i<$n;$i++) { idlm_integration_assert_same(1,$wpdb->insert($wpdb->prefix.'idlm_lead_touchpoints',array('lead_id'=>1,'platform'=>'website','received_at'=>'2000-01-01 00:00:00'))); }
}
idlm_integration_test('T16-N-CORE012-retention-100-row-commit-checkpoint-resume-and-configured-CAPI-policy',static function():void {
    global $wpdb;idlm_bound_reset();idlm_bound_expired();
    $wpdb->insert($wpdb->prefix.'idlm_capi_logs',array('lead_id'=>1,'platform'=>'google','event_name'=>'Test','payload'=>'{}','response'=>'{}','status'=>'success','created_at'=>wp_date('Y-m-d H:i:s',time()-30*DAY_IN_SECONDS)));$keep=$wpdb->insert_id;
    $r=IDLM_Data_Retention::prune();idlm_integration_assert_same(100,$r['processed']);idlm_integration_assert_same(1,$r['stage']);idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints"));
    $r=IDLM_Data_Retention::prune();idlm_integration_assert_same(100,$r['processed']);idlm_integration_assert_same(2,$r['stage']);
    for($i=0;$i<8&&!$r['complete'];$i++){$r=IDLM_Data_Retention::prune();idlm_integration_assert_true(is_array($r));}
    idlm_integration_assert_true($r['complete']);idlm_integration_assert_same(101,$r['processed']);idlm_integration_assert_same((string)$keep,$wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_capi_logs WHERE id={$keep}"));
});
idlm_integration_test('T16-N-CORE012-delete-failure-retains-checkpoint-exposes-generic-fault-and-retries',static function():void {
    global $wpdb;idlm_bound_reset();idlm_bound_expired();$r=idlm_lifecycle_failure('idlm_lead_touchpoints','DELETE',static function(){return IDLM_Data_Retention::prune();});idlm_integration_assert_true(is_wp_error($r));
    $state=get_option(IDLM_Data_Retention::STATUS_OPTION);idlm_integration_assert_same(0,$state['stage']);idlm_integration_assert_same(0,$state['processed']);idlm_integration_assert_same('batch_failed',$state['last_error']);idlm_integration_assert_true((bool)wp_next_scheduled(IDLM_Data_Retention::CONTINUE_HOOK));idlm_integration_assert_same(101,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints"));
    $r=IDLM_Data_Retention::prune();idlm_integration_assert_same('',$r['last_error']);idlm_integration_assert_same(100,$r['processed']);
});
foreach(array('checkpoint','commit')as$kind){idlm_integration_test('T16-N-CORE012-'.$kind.'-failure-rolls-back-deletes-and-advancement',static function()use($kind):void {
    global $wpdb;idlm_bound_reset();idlm_bound_expired(3);
    $r=idlm_schema_filter(static function($sql)use($kind){return (($kind==='commit'&&$sql==='COMMIT')||($kind==='checkpoint'&&strpos($sql,"INSERT INTO {$GLOBALS['wpdb']->options} (option_name")===0&&strpos($sql,IDLM_Data_Retention::STATUS_OPTION)!==false))?'SELECT * FROM idlm_missing_retention_fault':$sql;},static function(){return IDLM_Data_Retention::prune();});
    idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_same(3,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints"));$r=IDLM_Data_Retention::prune();idlm_integration_assert_same(3,$r['processed']);idlm_integration_assert_same(1,$r['stage']);
});}
idlm_integration_test('T16-C-CORE012-independent-session-lock-refuses-overlap-and-releases-for-retry',static function():void {
    global $wpdb,$config;idlm_bound_reset();idlm_bound_expired(3);$other=new wpdb($config['db_user'],$config['db_password'],$config['db_name'],$config['db_host']);$name=IDLM_Recovery_Guard::name('retention');
    try { idlm_integration_assert_same('1',$other->get_var($other->prepare('SELECT GET_LOCK(%s,0)',$name)));idlm_integration_assert_same(false,IDLM_Data_Retention::prune());idlm_integration_assert_same(false,get_option(IDLM_Data_Retention::STATUS_OPTION)); }
    finally{$other->get_var($other->prepare('SELECT RELEASE_LOCK(%s)',$name));$other->close();}
    idlm_integration_assert_same(3,IDLM_Data_Retention::prune()['processed']);
});
idlm_integration_test('T16-C-CORE012-process-death-before-commit-replays-no-skip',static function():void {
    global $wpdb;
    idlm_bound_reset(); idlm_bound_expired(3);
    $worker = idlm_integration_worker(array('retention-interrupt'));
    idlm_integration_assert_same(77, $worker['status'], $worker['stderr']);
    idlm_integration_assert_same(3, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints"));
    $result = IDLM_Data_Retention::prune();
    idlm_integration_assert_true(is_array($result), 'Dead worker session did not release retention/recovery locks within five seconds.');
    idlm_integration_assert_same(3, $result['processed']);
    idlm_integration_assert_same(1, $result['stage']);
    idlm_integration_assert_same(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints"));
});
idlm_integration_test('T16-N-CORE012-compaction-does-not-load-large-payload-and-is-bounded-checked',static function():void {
    global $wpdb;idlm_bound_reset();$wpdb->query("INSERT INTO {$wpdb->prefix}idlm_audience_sync_logs (platform,audience_id,lead_count,payload,response,status,created_at) VALUES ('google','test',1,REPEAT('x',2097152),'{}','success',NOW())");
    $state=array('generation'=>IDLM_Recovery_Guard::generation(),'stage'=>6,'processed'=>0,'cutoff'=>current_time('mysql'),'complete'=>false,'remaining'=>array(false,false,false,false,false,false,true));update_option(IDLM_Data_Retention::STATUS_OPTION,$state,false);
    $r=idlm_lifecycle_failure('idlm_audience_sync_logs','UPDATE',static function(){return IDLM_Data_Retention::prune();});idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_same('2097152',$wpdb->get_var("SELECT OCTET_LENGTH(payload) FROM {$wpdb->prefix}idlm_audience_sync_logs"));
    $r=IDLM_Data_Retention::prune();idlm_integration_assert_true($r['complete']);$body=maybe_unserialize($wpdb->get_var("SELECT payload FROM {$wpdb->prefix}idlm_audience_sync_logs"));idlm_integration_assert_same(2097152,$body['original_bytes']);idlm_integration_assert_same(hash('sha256',str_repeat('x',2097152)),$body['sha256']);
});
function idlm_bound_task(int $lead,string $type='General',?string $due=null,string $status='pending'):int {
    global $wpdb;idlm_integration_assert_same(1,$wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$lead,'title'=>'Synthetic bounded task','task_type'=>$type,'due_date'=>$due,'status'=>$status,'priority'=>'Medium')));return(int)$wpdb->insert_id;
}
idlm_integration_test('T05-N-CORE012-task-cards-keysets-collapse-six-slots-count-entire-scope-and-hide-undated',static function():void {
    global $wpdb;idlm_bound_reset();$lead=idlm_lifecycle_fixture();$due=current_time('mysql');$expected=array();for($i=0;$i<123;$i++){$expected[]=idlm_bound_task($lead,'General',$due);}
    for($i=0;$i<6;$i++){idlm_bound_task($lead,'Qualification',wp_date('Y-m-d H:i:s',time()+($i+1)*DAY_IN_SECONDS));}idlm_bound_task($lead);idlm_bound_task($lead,'General',wp_date('Y-m-d H:i:s',time()+30*DAY_IN_SECONDS));
    $r=IDLM_Task_Queue_Service::page();idlm_integration_assert_same(50,count($r['tasks']));idlm_integration_assert_same(123,$r['attention']['due_today'], wp_json_encode(array('timezone'=>wp_timezone()->getName(),'now'=>current_time('mysql'),'day'=>wp_date('Y-m-d',current_time('timestamp'),wp_timezone()),'due'=>$wpdb->get_var("SELECT MIN(due_date) FROM {$wpdb->prefix}idlm_tasks"),'totals'=>$r['attention'])));idlm_integration_assert_same(1,$r['attention']['follow_up_plans']);$ids=array();$pages=0;
    do{foreach($r['tasks']as$t){$ids[]=(int)$t->id;}$pages++;$r=$r['has_more']?IDLM_Task_Queue_Service::page('attention',$r['next_cursor']):null;}while($r);
    idlm_integration_assert_same(124,count($ids));idlm_integration_assert_same(124,count(array_unique($ids)));idlm_integration_assert_same(3,$pages);idlm_integration_assert_same(array_reverse($expected),array_slice($ids,0,123));
});
idlm_integration_test('T05-N-CORE012-task-cursors-bind-actor-view-scope-recovery-and-errors-are-truthful',static function():void {
    global $wpdb,$administrator_id;idlm_bound_reset();$lead=idlm_lifecycle_fixture();for($i=0;$i<51;$i++){idlm_bound_task($lead,'General',current_time('mysql'),'completed');}$r=IDLM_Task_Queue_Service::page('completed');$token=$r['next_cursor'];idlm_integration_assert_true($r['has_more']);idlm_integration_assert_true(is_wp_error(IDLM_Task_Queue_Service::page('attention',$token)));idlm_integration_assert_true(is_wp_error(IDLM_Task_Queue_Service::page('completed',$token.'x')));
    $epoch=IDLM_Recovery_Guard::generation();update_option(IDLM_Recovery_Guard::GENERATION,$epoch+1);idlm_integration_assert_true(is_wp_error(IDLM_Task_Queue_Service::page('completed',$token)));update_option(IDLM_Recovery_Guard::GENERATION,$epoch);
    $actor=wp_create_user('bounded-reader-'.wp_generate_uuid4(),wp_generate_password());$u=new WP_User($actor);$u->add_cap('idlm_access_leads');wp_set_current_user($actor);idlm_integration_assert_true(is_wp_error(IDLM_Task_Queue_Service::page('completed',$token)));wp_set_current_user($administrator_id);
    $r=idlm_schema_filter(static function($sql){return strpos($sql,'SELECT t.*,')===0?'SELECT * FROM idlm_missing_task_read':$sql;},static function(){return IDLM_Task_Queue_Service::page('completed');});idlm_integration_assert_true(is_wp_error($r));
    $counts=IDLM_Task_Queue_Service::page('attention','',true);idlm_integration_assert_same(array(),$counts['tasks']);
});
idlm_integration_test('T05-N-CORE012-WhatsApp-SQL-count-read-state-legacy-replies-and-live-scope',static function():void {
    global $wpdb,$administrator_id;idlm_bound_reset();$actor=wp_create_user('wa-reader-'.wp_generate_uuid4(),wp_generate_password());$u=new WP_User($actor);$u->add_cap('idlm_access_leads');$ids=array();
    for($i=0;$i<111;$i++){$lead=idlm_lifecycle_fixture(array('assigned_rep_id'=>$actor));$ids[]=$lead;for($j=0;$j<10;$j++){$wpdb->insert($wpdb->prefix.'idlm_lead_activity_log',array('lead_id'=>$lead,'activity_type'=>$j%2?'whatsapp_reply':'comment','details'=>$j%2?'Synthetic reply':'[Direct WA Reply]: Synthetic','created_at'=>current_time('mysql')));}}
    wp_set_current_user($actor);idlm_integration_assert_same(1110,IDLM_Admin_Lead_Notifications::get_unread_whatsapp_count());$first=IDLM_Admin_Lead_Notifications::get_unread_whatsapp_lead_ids();idlm_integration_assert_same(100,count($first));$next=IDLM_Admin_Lead_Notifications::get_unread_whatsapp_lead_ids(end($first));idlm_integration_assert_same(11,count($next));
    $last=(int)$wpdb->get_var("SELECT MAX(id) FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id={$ids[0]}");IDLM_Admin_Lead_Notifications::mark_whatsapp_reply_read($ids[0],$last);idlm_integration_assert_same(1100,IDLM_Admin_Lead_Notifications::get_unread_whatsapp_count());$wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$administrator_id),array('id'=>$ids[1]));idlm_integration_assert_same(1090,IDLM_Admin_Lead_Notifications::get_unread_whatsapp_count());$u->remove_cap('idlm_access_leads');clean_user_cache($actor);wp_set_current_user(0);wp_set_current_user($actor);idlm_integration_assert_same(0,IDLM_Admin_Lead_Notifications::get_unread_whatsapp_count());wp_set_current_user($administrator_id);
});
idlm_integration_test('T12-N-CORE012-export-keyset-multipage-late-read-fault-row-cap-and-deadline',static function():void {
    global $wpdb;idlm_bound_reset();$source='bounded-'.wp_generate_uuid4();$values=array();for($i=0;$i<10001;$i++){$values[]=$wpdb->prepare('(%s,%s)','Synthetic CSV',$source);}idlm_integration_assert_same(10001,$wpdb->query("INSERT INTO {$wpdb->prefix}idlm_leads (first_name,source) VALUES ".implode(',',$values)));
    $method=new ReflectionMethod('IDLM_Admin_Ajax','export_lead_rows');if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); }$query=$wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE source = %s",$source);$seen=0;
    try{foreach($method->invoke(null,$query,microtime(true)+20)as$row){$seen++;}throw new RuntimeException('Oversized export succeeded');}catch(RuntimeException$e){idlm_integration_assert_same(10000,$seen);idlm_integration_assert_true(strpos($e->getMessage(),'10,000')!==false);}
    $seen=0;$thrown=false;try{idlm_schema_filter(static function($sql){return strpos($sql,'SELECT * FROM '.$GLOBALS['wpdb']->prefix.'idlm_leads WHERE source')===0&&strpos($sql,'id <')!==false?'SELECT * FROM idlm_missing_export_read':$sql;},static function()use($method,$query,&$seen){foreach($method->invoke(null,$query,microtime(true)+20)as$row){$seen++;}});}catch(RuntimeException$e){$thrown=true;}idlm_integration_assert_true($thrown);idlm_integration_assert_same(100,$seen);
    $thrown=false;try{foreach($method->invoke(null,$query,microtime(true)-1)as$row){}}catch(RuntimeException$e){$thrown=true;}idlm_integration_assert_true($thrown);
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}idlm_leads WHERE source=%s",$source));
});
idlm_integration_test('T05-N-CORE012-qualification-progress-carries-across-100-date-pages-with-identical-results',static function():void {
    global $wpdb;idlm_bound_reset();$lead=idlm_lifecycle_fixture();for($i=0;$i<230;$i++){$wpdb->insert($wpdb->prefix.'idlm_lead_activity_log',array('lead_id'=>$lead,'activity_type'=>$i%3?'outbound_call':'whatsapp_outbound','details'=>'Synthetic outreach','created_at'=>wp_date('Y-m-d H:i:s',time()-($i+1)*DAY_IN_SECONDS)));}
    idlm_integration_assert_same(IDLM_DB::get_followup_compliance($lead),IDLM_DB::get_followup_compliance($lead,true));idlm_integration_assert_true(is_wp_error(IDLM_DB::get_followup_compliance($lead,true,microtime(true)-1)));
});
idlm_integration_test('T21-N-CORE012-local-5000-lead-20000-task-25000-message-read-plan-time-memory-sample',static function():void {
    global $wpdb;idlm_bound_reset();$first=(int)$wpdb->get_var("SELECT COALESCE(MAX(id),0)+1 FROM {$wpdb->prefix}idlm_leads");
    for($batch=0;$batch<5;$batch++){$values=array();for($i=0;$i<1000;$i++){$values[]="('Synthetic profile','New')";}$wpdb->query("INSERT INTO {$wpdb->prefix}idlm_leads (first_name,stage) VALUES ".implode(',',$values));}
    // Task due dates use the WordPress site clock, which can be a different day from MySQL NOW(). Builder: Shashi.
    $due_date_sql=$wpdb->prepare('%s',current_time('mysql'));
    for($batch=0;$batch<20;$batch++){$values=array();for($i=0;$i<1000;$i++){$id=$first+(($batch*1000+$i)%5000);$values[]="({$id},'Synthetic profile','General','pending',{$due_date_sql})";}$wpdb->query("INSERT INTO {$wpdb->prefix}idlm_tasks (lead_id,title,task_type,status,due_date) VALUES ".implode(',',$values));}
    for($batch=0;$batch<25;$batch++){$values=array();for($i=0;$i<1000;$i++){$id=$first+(($batch*1000+$i)%5000);$values[]="({$id},'whatsapp_reply','Synthetic profile',NOW())";}$wpdb->query("INSERT INTO {$wpdb->prefix}idlm_lead_activity_log (lead_id,activity_type,details,created_at) VALUES ".implode(',',$values));}
    idlm_integration_assert_same('',$wpdb->last_error);$queries=array();$filter=static function($sql)use(&$queries){if(strpos($sql,'SELECT t.*,')===0||strpos($sql,'SELECT COUNT(*) FROM '.$GLOBALS['wpdb']->prefix.'idlm_leads l INNER JOIN')===0||strpos($sql,'SELECT\n')===0){$queries[]=$sql;}return$sql;};add_filter('query',$filter);
    $before=memory_get_usage(true);$start=microtime(true);$page=IDLM_Task_Queue_Service::page();$task_ms=(microtime(true)-$start)*1000;$start=microtime(true);$count=IDLM_Admin_Lead_Notifications::get_unread_whatsapp_count();$wa_ms=(microtime(true)-$start)*1000;$delta=memory_get_usage(true)-$before;remove_filter('query',$filter);
    idlm_integration_assert_same(50,count($page['tasks']));idlm_integration_assert_same(20000,$page['attention']['due_today']);idlm_integration_assert_same(25000,$count);idlm_integration_assert_true($delta<16*1024*1024,'Read allocations exceeded 16 MiB');
    $plans=array();foreach($queries as$sql){$rows=$wpdb->get_results('EXPLAIN '.$sql,ARRAY_A);idlm_integration_assert_same('',$wpdb->last_error);$plan=array();foreach($rows as$row){$plan[]=array_intersect_key($row,array_flip(array('select_type','type','possible_keys','key','rows','filtered','Extra')));}$plans[]=$plan;}
    $evidence=array('author'=>'Shashi','profile'=>array('leads_added'=>5000,'tasks'=>20000,'messages'=>25000),'measurements'=>array('task_page_ms'=>$task_ms,'whatsapp_count_ms'=>$wa_ms,'php_allocated_delta_bytes'=>$delta,'task_cards'=>count($page['tasks'])),'plans'=>$plans,'limits'=>'Single local sample; not p95/p99, shared-traffic or expected/maximum production qualification.');
    // Keep routine reruns out of historical evidence. Builder: Shashi.
    $sample_path = getenv('IDLM_TEST_READ_SAMPLE_PATH') ?: WP_CONTENT_DIR . '/read-sample.json';
    idlm_integration_assert_true(false !== file_put_contents($sample_path,wp_json_encode($evidence,JSON_PRETTY_PRINT)."\n"), 'Cannot retain read sample');
    $wpdb->query("DELETE FROM {$wpdb->prefix}idlm_leads WHERE id >= {$first}");
});

idlm_integration_test('T17-N-CORE012-task-day-counts-use-one-site-timezone-conversion', static function(): void {
    global $wpdb;
    $previous = get_option('timezone_string');
    try {
        foreach (array('UTC', 'Asia/Kolkata', 'Pacific/Auckland', 'America/New_York', 'Etc/GMT+12') as $zone) {
            update_option('timezone_string', $zone);
            idlm_bound_reset();
            $lead = idlm_lifecycle_fixture();
            idlm_bound_task($lead, 'General', current_time('mysql'));
            $page = IDLM_Task_Queue_Service::page('attention', '', true);
            idlm_integration_assert_true(!is_wp_error($page));
            idlm_integration_assert_same(1, $page['attention']['due_today'], 'Incorrect local day in '.$zone);
            idlm_integration_assert_same(0, $page['attention']['overdue'], 'Current-day task became yesterday in '.$zone);
        }
    } finally { update_option('timezone_string', $previous); }
});
