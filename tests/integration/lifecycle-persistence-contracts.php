<?php
/** Native aggregate rollback, durable handoff and concurrency evidence. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_lifecycle_fixture(array $extra = array()): int {
    global $wpdb;
    $now = current_time('mysql');
    $row = array_merge(array('first_name'=>'Synthetic atomic lead','email'=>wp_generate_uuid4().'@example.test','phone'=>'','stage'=>'New','call_status'=>'Not Called','lead_quality'=>'','source'=>'Website','original_source'=>'Website','latest_source'=>'Website','floor_plan_received'=>1,'project_name'=>'Synthetic residence','property_location'=>'Synthetic location','budget'=>'8 lakh','possession_date'=>'2027-01-01','property_type'=>'Apartment','project_timeline'=>'3 months','created_at'=>$now,'updated_at'=>$now), $extra);
    idlm_integration_assert_same(1, $wpdb->insert($wpdb->prefix.'idlm_leads', $row));
    return (int) $wpdb->insert_id;
}
function idlm_lifecycle_snapshot(int $lead): array {
    global $wpdb;
    $rows = array();
    foreach (array('idlm_leads','idlm_lead_activity_log','idlm_lead_state_events','idlm_tasks','idlm_lead_touchpoints','idlm_lead_outbox') as $suffix) {
        $key = $suffix === 'idlm_leads' ? 'id' : 'lead_id';
        $rows[$suffix] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$suffix} WHERE {$key} = {$lead} ORDER BY id", ARRAY_A);
        idlm_integration_assert_same('', $wpdb->last_error);
    }
    return $rows;
}
function idlm_lifecycle_failure(string $table, string $operation, callable $callback) {
    global $wpdb;
    $trigger = $wpdb->prefix.'idlm_atomic_fail';
    idlm_integration_assert_true(false !== $wpdb->query("CREATE TRIGGER {$trigger} BEFORE {$operation} ON {$wpdb->prefix}{$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Synthetic required write failure'"));
    try { return $callback(); } finally { $wpdb->query("DROP TRIGGER {$trigger}"); }
}

foreach (array('idlm_leads'=>'UPDATE','idlm_lead_activity_log'=>'INSERT','idlm_lead_state_events'=>'INSERT','idlm_tasks'=>'UPDATE','idlm_lead_outbox'=>'INSERT') as $table=>$operation) {
    idlm_integration_test('T04-N-required '.$table.' failure rolls back qualification and retry records one transition', static function() use ($table,$operation): void {
        global $wpdb;
        $lead=idlm_lifecycle_fixture();
        $wpdb->insert($wpdb->prefix.'idlm_tasks', array('lead_id'=>$lead,'task_type'=>'Qualification','status'=>'pending','title'=>'Synthetic required task'));
        $before=idlm_lifecycle_snapshot($lead); $hooks=0;
        $observer=static function() use (&$hooks) { $hooks++; };
        add_action('idlm_lead_stage_converted',$observer,1,3);
        try {
            idlm_integration_assert_same(false, idlm_lifecycle_failure($table,$operation,static function() use($lead) { return IDLM_DB::update_lead($lead,array('stage'=>'Qualified')); }));
            idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead)); idlm_integration_assert_same(0,$hooks);
            idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('stage'=>'Qualified')));
            idlm_integration_assert_same(1,$hooks);
            idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_state_events WHERE lead_id={$lead}"));
            idlm_integration_assert_same('completed',$wpdb->get_var("SELECT status FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead}"));
        } finally { remove_action('idlm_lead_stage_converted',$observer,1); }
    });
}

foreach (array('new','matched') as $kind) {
    foreach (array('idlm_lead_activity_log','idlm_lead_touchpoints','idlm_lead_outbox') as $table) {
        idlm_integration_test('T02-N-'.$kind.' intake '.$table.' failure has no partial aggregate or hook',static function() use($kind,$table):void {
            global $wpdb;
            $email=wp_generate_uuid4().'@example.test';$lead=$kind==='matched'?idlm_lifecycle_fixture(array('email'=>$email)):0;
            $before=$lead?idlm_lifecycle_snapshot($lead):null;$total=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads");$hooks=0;
            $observe=static function() use (&$hooks) { $hooks++; };add_action('idlm_lead_attribution_merged',$observe,1,2);add_action('idlm_lead_stage_converted',$observe,1,3);
            $data=array('email'=>$email,'gclid'=>'synthetic-click');
            try {
                idlm_integration_assert_same(false,idlm_lifecycle_failure($table,'INSERT',static function() use($data) {return IDLM_DB::upsert_inbound_lead($data,'website');}));
                idlm_integration_assert_same($total,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads"));if($lead){idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));}idlm_integration_assert_same(0,$hooks);
                $retry=IDLM_DB::upsert_inbound_lead($data,'website');idlm_integration_assert_true(is_array($retry));$id=(int)$retry['lead_id'];
                idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id={$id}"));idlm_integration_assert_same(1,$hooks);
            } finally {remove_action('idlm_lead_attribution_merged',$observe,1);remove_action('idlm_lead_stage_converted',$observe,1);}
        });
    }
}

idlm_integration_test('T02-N-matched UPDATE and identity SELECT errors cannot acknowledge or create another lead',static function():void {
    global $wpdb;
    $lead=idlm_lifecycle_fixture();$email=$wpdb->get_var("SELECT email FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}");$before=idlm_lifecycle_snapshot($lead);
    idlm_integration_assert_same(false,idlm_lifecycle_failure('idlm_leads','UPDATE',static function() use($email){return IDLM_DB::upsert_inbound_lead(array('email'=>$email,'gclid'=>'synthetic'),'website');}));
    idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
    $filter=static function($sql){return strpos($sql,'SELECT id, email, phone FROM wp_idlm_leads WHERE is_duplicate')===0?'SELECT * FROM idlm_test_missing_identity':$sql;};
    idlm_integration_assert_same(false,idlm_schema_filter($filter,static function() use($email){return IDLM_DB::upsert_inbound_lead(array('email'=>$email),'website');}));
    idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
});

idlm_integration_test('T02-N-external replay verifies the canonical tuple and does not append a touchpoint',static function():void {
    global $wpdb;
    $data=array('email'=>wp_generate_uuid4().'@example.test','fb_lead_id'=>wp_generate_uuid4());
    $first=IDLM_DB::upsert_inbound_lead($data,'meta');idlm_integration_assert_true($first['created']);
    // An external identity remains authoritative even when the retry contains changed contact fields.
    $second=IDLM_DB::upsert_inbound_lead(array('email'=>'replayed-'.wp_generate_uuid4().'@example.test','fb_lead_id'=>$data['fb_lead_id']),'meta');
    idlm_integration_assert_same((int)$first['lead_id'],(int)$second['lead_id']);idlm_integration_assert_same(false,$second['created']);
    idlm_integration_assert_same(1,(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE platform='meta' AND external_id=%s",$data['fb_lead_id'])));
});

idlm_integration_test('T02-N-zero inserted touchpoint rows roll back rather than masquerade as replay',static function():void {
    global $wpdb;$email=wp_generate_uuid4().'@example.test';
    $filter=static function($sql){return strpos($sql,'INSERT INTO `wp_idlm_lead_touchpoints`')===0?'SELECT 1 WHERE 0':$sql;};
    idlm_integration_assert_same(false,idlm_schema_filter($filter,static function() use($email){return IDLM_DB::upsert_inbound_lead(array('email'=>$email,'fb_lead_id'=>'zero-row'),'meta');}));
    idlm_integration_assert_same(0,(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE email=%s",$email)));
});

idlm_integration_test('T02-N-review activity failure rolls back the retained conflict submission',static function():void {
    global $wpdb;$email=wp_generate_uuid4().'@example.test';idlm_lifecycle_fixture(array('email'=>$email));idlm_lifecycle_fixture(array('email'=>$email));
    $before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_inbound_submission_reviews");
    idlm_integration_assert_same(false,idlm_lifecycle_failure('idlm_lead_activity_log','INSERT',static function() use($email){return IDLM_DB::upsert_inbound_lead(array('email'=>$email),'website');}));
    idlm_integration_assert_same($before,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_inbound_submission_reviews"));
    $r=IDLM_DB::upsert_inbound_lead(array('email'=>$email),'website');idlm_integration_assert_true($r['requires_duplicate_review']);
});

foreach(array('idlm_lead_state_events','idlm_tasks') as $table){
    idlm_integration_test('T04-N-routing '.$table.' failure rolls back owner, events and all six tasks',static function() use($table):void {
        global $wpdb;
        $lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);
        $method=new ReflectionMethod('IDLM_Integrations','assign_lead_atomically');if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); }
        $target=wp_insert_user(array('user_login'=>'routing-'.wp_generate_uuid4(),'user_pass'=>wp_generate_password(),'role'=>'idlm_pre_sales'));
        $args=array($lead,0,$target,'Synthetic intake assignment',false,'intake');
        idlm_integration_assert_same(false,idlm_lifecycle_failure($table,'INSERT',static function() use($method,$args){return $method->invokeArgs(null,$args);}));
        idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));idlm_integration_assert_same(true,$method->invokeArgs(null,$args));
        idlm_integration_assert_same(6,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead}"));
        idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_state_events WHERE lead_id={$lead}"));
    });
}

idlm_integration_test('T04-N-qualified reassignment task INSERT failure rolls back owner, history and transferred tasks',static function():void {
    global $wpdb,$administrator_id;
    $lead=idlm_lifecycle_fixture(array('stage'=>'Qualified','lead_quality'=>'Qualified','assigned_rep_id'=>999));
    $wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$lead,'assigned_rep_id'=>999,'task_type'=>'General','status'=>'pending','title'=>'Synthetic transfer'));$before=idlm_lifecycle_snapshot($lead);
    idlm_integration_assert_same(false,idlm_lifecycle_failure('idlm_tasks','INSERT',static function() use($lead,$administrator_id){return IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$administrator_id));}));
    idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
    idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('assigned_rep_id'=>$administrator_id)));
    idlm_integration_assert_same(2,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead} AND assigned_rep_id={$administrator_id}"));
});

idlm_integration_test('T04-N-foreign transaction owner remains uncommitted and rollbackable after refused lifecycle write',static function():void {
    global $wpdb;$wpdb->query('START TRANSACTION');$lead=idlm_lifecycle_fixture();
    try {idlm_integration_assert_same(false,IDLM_DB::update_lead($lead,array('stage'=>'Contacted')));idlm_integration_assert_same('New',$wpdb->get_var("SELECT stage FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));}
    finally {$wpdb->query('ROLLBACK');}
    idlm_integration_assert_same(null,$wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
});

idlm_integration_test('T04-N-commit failure preserves prior aggregate and emits no pre-commit hook',static function():void {
    $lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);$hooks=0;$observer=static function()use(&$hooks){$hooks++;};add_action('idlm_lead_stage_converted',$observer,1,3);
    $filter=static function($sql){return $sql==='COMMIT'?'COMMIT idlm_synthetic_failure':$sql;};
    try {idlm_integration_assert_same(false,idlm_schema_filter($filter,static function()use($lead){return IDLM_DB::update_lead($lead,array('stage'=>'Qualified'));}));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));idlm_integration_assert_same(0,$hooks);}
    finally {remove_action('idlm_lead_stage_converted',$observer,1);}
});

idlm_integration_test('T04-N-nontransactional core dependency blocks writes even with a current marker',static function():void {
    global $wpdb;$lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);$wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_lead_outbox ENGINE=MyISAM");
    try {idlm_integration_assert_same(false,IDLM_DB::update_lead($lead,array('stage'=>'Qualified')));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));}
    finally {$wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_lead_outbox ENGINE=InnoDB");}
});

idlm_integration_test('T14-N-after-commit callback independently sees the committed lead and history with original arguments',static function():void {
    global $wpdb,$config;$lead=idlm_lifecycle_fixture();$observed=array();
    $observer=static function($id,$stage,$old)use(&$observed,$config){
        $other=new wpdb($config['db_user'],$config['db_password'],$config['db_name'],$config['db_host']);
        $observed=array($other->get_var("SELECT stage FROM wp_idlm_leads WHERE id=".(int)$id),(int)$other->get_var("SELECT COUNT(*) FROM wp_idlm_lead_state_events WHERE lead_id=".(int)$id),$stage,is_object($old)?$old->stage:null,IDLM_Lead_Transaction::active());$other->close();
    };add_action('idlm_lead_stage_converted',$observer,1,3);
    try {idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('stage'=>'Qualified')));idlm_integration_assert_same(array('Qualified',1,'Qualified','New',false),$observed);}
    finally {remove_action('idlm_lead_stage_converted',$observer,1);}
});

idlm_integration_test('T14-N-hook exception and acknowledgement failure retain replayable intents without undoing committed lead',static function():void {
    global $wpdb;$lead=idlm_lifecycle_fixture();$throws=static function(){throw new RuntimeException('Synthetic handoff failed');};add_action('idlm_lead_stage_converted',$throws,1,3);
    try {idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('stage'=>'Qualified')));}
    finally {remove_action('idlm_lead_stage_converted',$throws,1);}
    $id=(int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_lead_outbox WHERE lead_id={$lead}");idlm_integration_assert_true($id>0);
    $wpdb->update($wpdb->prefix.'idlm_lead_outbox',array('available_at'=>gmdate('Y-m-d H:i:s',time()-1)),array('id'=>$id));
    idlm_integration_assert_same(false,idlm_lifecycle_failure('idlm_lead_outbox','DELETE',static function()use($id){return IDLM_Lead_Outbox::dispatch($id);}));
    idlm_integration_assert_same('Qualified',$wpdb->get_var("SELECT stage FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
    $wpdb->update($wpdb->prefix.'idlm_lead_outbox',array('available_at'=>gmdate('Y-m-d H:i:s',time()-1)),array('id'=>$id));idlm_integration_assert_same(true,IDLM_Lead_Outbox::dispatch($id));
    idlm_integration_assert_same(null,$wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_lead_outbox WHERE id={$id}"));
});

idlm_integration_test('T14-N-CAPI cron enqueue failure leaves intent for retry and successful retry queues once',static function():void {
    global $wpdb; $lead=idlm_lifecycle_fixture();
    add_action('idlm_lead_stage_converted',array('IDLM_CAPI','queue_capi_event'),10,3);
    $deny=static function(){return false;};add_filter('pre_schedule_event',$deny,10,3);
    try {idlm_integration_assert_same(true,IDLM_DB::update_lead($lead,array('stage'=>'Qualified')));}
    finally {remove_filter('pre_schedule_event',$deny,10);remove_action('idlm_lead_stage_converted',array('IDLM_CAPI','queue_capi_event'),10);}
    $id=(int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_lead_outbox WHERE lead_id={$lead}");idlm_integration_assert_true($id>0);$wpdb->update($wpdb->prefix.'idlm_lead_outbox',array('available_at'=>gmdate('Y-m-d H:i:s',time()-1)),array('id'=>$id));
    add_action('idlm_lead_stage_converted',array('IDLM_CAPI','queue_capi_event'),10,3);
    try {idlm_integration_assert_same(true,IDLM_Lead_Outbox::dispatch($id));}
    finally {remove_action('idlm_lead_stage_converted',array('IDLM_CAPI','queue_capi_event'),10);}
    idlm_integration_assert_true((bool)wp_next_scheduled('idlm_async_capi_upload_event',array('lead_id'=>$lead,'stage'=>'Qualified','generation'=>IDLM_Recovery_Guard::generation())));
});

idlm_integration_test('T14-N-pending intents are bounded and deleted leads or old recovery epochs never dispatch',static function():void {
    global $wpdb;$lead=idlm_lifecycle_fixture();$epoch=IDLM_Recovery_Guard::generation();$hook='idlm_lead_call_status_no_answer';$args=wp_json_encode(array(array('object'=>false,'value'=>$lead),array('object'=>true,'value'=>array('stage'=>'New'))));
    for($i=0;$i<30;$i++){$wpdb->insert($wpdb->prefix.'idlm_lead_outbox',array('lead_id'=>$lead,'generation'=>$epoch,'hook'=>$hook,'arguments'=>$args,'available_at'=>gmdate('Y-m-d H:i:s',time()-1),'created_at'=>gmdate('Y-m-d H:i:s')));}
    $count=0;$observe=static function()use(&$count){$count++;};add_action($hook,$observe,1,2);
    try {IDLM_Lead_Outbox::drain();idlm_integration_assert_same(25,$count);idlm_integration_assert_same(5,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_outbox WHERE lead_id={$lead}"));
        $wpdb->update($wpdb->prefix.'idlm_lead_outbox',array('generation'=>$epoch+1),array('lead_id'=>$lead));IDLM_Lead_Outbox::drain();idlm_integration_assert_same(25,$count);
        $wpdb->insert($wpdb->prefix.'idlm_lead_outbox',array('lead_id'=>$lead,'generation'=>$epoch,'hook'=>$hook,'arguments'=>$args,'available_at'=>gmdate('Y-m-d H:i:s',time()-1),'created_at'=>gmdate('Y-m-d H:i:s')));$wpdb->delete($wpdb->prefix.'idlm_leads',array('id'=>$lead));IDLM_Lead_Outbox::drain();idlm_integration_assert_same(25,$count);
    } finally {remove_action($hook,$observe,1);}
});

foreach(array('before','after') as $when){
    idlm_integration_test('T14-C-process death '.$when.' commit preserves the correct aggregate and retryable intent',static function()use($when):void {
        global $wpdb;$lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);
        $r=idlm_integration_worker(array('lead-interrupt',$lead,$when));idlm_integration_assert_same(77,$r['status'],$r['stderr']);
        if($when==='before'){idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));}
        else {$id=(int)$wpdb->get_var("SELECT id FROM {$wpdb->prefix}idlm_lead_outbox WHERE lead_id={$lead}");idlm_integration_assert_true($id>0);idlm_integration_assert_same('Qualified',$wpdb->get_var("SELECT stage FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));idlm_integration_assert_same(true,IDLM_Lead_Outbox::dispatch($id));foreach($wpdb->get_col("SELECT id FROM {$wpdb->prefix}idlm_lead_outbox WHERE lead_id={$lead}") as $pending){IDLM_Lead_Outbox::dispatch($pending);}}
    });
}

function idlm_lifecycle_pair(array $a,array $b): array {
    $barrier=WP_CONTENT_DIR.'/lead-barrier';file_put_contents($barrier,'');$processes=array();
    foreach(array($a,$b) as $args){$command=array_merge(array(PHP_BINARY,__DIR__.'/worker.php'),$args);$pipes=array();$proc=proc_open(implode(' ',array_map('escapeshellarg',$command)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$processes[]=array($proc,$pipes);}
    $results=array();foreach($processes as $process){$out=stream_get_contents($process[1][1]);$err=stream_get_contents($process[1][2]);fclose($process[1][1]);fclose($process[1][2]);$results[]=array('status'=>proc_close($process[0]),'stdout'=>trim($out),'stderr'=>$err);}unlink($barrier);return $results;
}
idlm_integration_test('T02-C-synchronized inbound repeats produce one canonical lead and one external touchpoint',static function():void {
    global $wpdb;$email=wp_generate_uuid4().'@example.test';$external=wp_generate_uuid4();$results=idlm_lifecycle_pair(array('lead-intake-race',$email,$external),array('lead-intake-race',$email,$external));
    foreach($results as $r){idlm_integration_assert_same(0,$r['status'],$r['stderr']);idlm_integration_assert_true(is_array(json_decode($r['stdout'],true)));}
    idlm_integration_assert_same(1,(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads WHERE email=%s",$email)));idlm_integration_assert_same(1,(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE external_id=%s",$external)));
});
idlm_integration_test('T04-C-synchronized updates preserve a serial immutable event chain',static function():void {
    global $wpdb;$lead=idlm_lifecycle_fixture();$results=idlm_lifecycle_pair(array('lead-update-race',$lead,'Contacted'),array('lead-update-race',$lead,'Lost'));foreach($results as $r){idlm_integration_assert_same(0,$r['status'],$r['stderr']);}
    $rows=$wpdb->get_results("SELECT from_value,to_value FROM {$wpdb->prefix}idlm_lead_state_events WHERE lead_id={$lead} ORDER BY id",ARRAY_A);idlm_integration_assert_same(2,count($rows));idlm_integration_assert_same('New',$rows[0]['from_value']);idlm_integration_assert_same($rows[0]['to_value'],$rows[1]['from_value']);idlm_integration_assert_same($rows[1]['to_value'],$wpdb->get_var("SELECT stage FROM {$wpdb->prefix}idlm_leads WHERE id={$lead}"));
});
idlm_integration_test('T14-C-two simultaneous outbox workers claim one hook handoff',static function():void {
    global $wpdb;$lead=idlm_lifecycle_fixture();$args=wp_json_encode(array(array('object'=>false,'value'=>$lead),array('object'=>false,'value'=>'Qualified'),array('object'=>true,'value'=>array('stage'=>'New'))));$wpdb->insert($wpdb->prefix.'idlm_lead_outbox',array('lead_id'=>$lead,'generation'=>IDLM_Recovery_Guard::generation(),'hook'=>'idlm_lead_stage_converted','arguments'=>$args,'available_at'=>gmdate('Y-m-d H:i:s',time()-1),'created_at'=>gmdate('Y-m-d H:i:s')));$id=(int)$wpdb->insert_id;
    $results=idlm_lifecycle_pair(array('lead-outbox-race',$id),array('lead-outbox-race',$id));foreach($results as $r){idlm_integration_assert_same(0,$r['status'],$r['stderr']);}
    idlm_integration_assert_same(1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id={$lead} AND activity_type='outbox_probe'"));
});

idlm_integration_test('T04-N-each of six fresh task insert failures rolls back the complete plan before retry',static function():void {
    global $wpdb,$administrator_id;
    for($position=1;$position<=6;$position++){
        $lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);$count=0;
        $filter=static function($sql)use(&$count,$position){if(strpos($sql,'INSERT INTO `wp_idlm_tasks`')===0 && ++$count===$position){return 'INSERT INTO idlm_test_missing_task VALUES (1)';}return$sql;};
        idlm_integration_assert_same(false,idlm_schema_filter($filter,static function()use($lead,$administrator_id){return IDLM_DB::create_presales_followup_tasks($lead,$administrator_id);}));
        idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));idlm_integration_assert_same(true,IDLM_DB::create_presales_followup_tasks($lead,$administrator_id));
        idlm_integration_assert_same(6,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_tasks WHERE lead_id={$lead}"));
    }
});
idlm_integration_test('T04-N-required Lost remark failure rolls back close state, task archival and reporting',static function():void {
    global $wpdb;$lead=idlm_lifecycle_fixture();$wpdb->insert($wpdb->prefix.'idlm_tasks',array('lead_id'=>$lead,'status'=>'pending','task_type'=>'General','title'=>'Synthetic task'));$before=idlm_lifecycle_snapshot($lead);
    $filter=static function($sql){return strpos($sql,'INSERT INTO `wp_idlm_lead_activity_log`')===0 && strpos($sql,"'lost_remark'")!==false?'INSERT INTO idlm_test_missing_remark VALUES (1)':$sql;};
    idlm_integration_assert_same(false,idlm_schema_filter($filter,static function()use($lead){return IDLM_DB::update_lead($lead,array('stage'=>'Lost','lost_reason'=>'Other','lost_remark'=>'Synthetic explanation'));}));idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
});
idlm_integration_test('T04-C-disconnected transaction is never retried as an autocommit dependent write',static function():void {
    $lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);$r=idlm_integration_worker(array('lead-disconnect',$lead));idlm_integration_assert_same(0,$r['status'],$r['stderr']);idlm_integration_assert_same('refused',$r['stdout']);idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
});
idlm_integration_test('T04-N-lost recovery session lock refuses commit and rolls back complete aggregate',static function():void {
    global $wpdb;$lead=idlm_lifecycle_fixture();$before=idlm_lifecycle_snapshot($lead);$released=false;
    $filter=static function($sql)use(&$released,$wpdb){if(!$released && preg_match("/SELECT IS_USED_LOCK\('([^']+_worker_[0-9]+)'\)/",$sql,$lock)){$released=true;$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock[1]));}return$sql;};
    idlm_integration_assert_same(false,idlm_schema_filter($filter,static function()use($lead){return IDLM_DB::update_lead($lead,array('stage'=>'Qualified'));}));idlm_integration_assert_same(true,$released);idlm_integration_assert_same($before,idlm_lifecycle_snapshot($lead));
});
idlm_integration_test('T14-N-lead deletion, complete restore and reset erase pending intent without replay',static function():void {
    global $wpdb;
    $lead=idlm_lifecycle_fixture();$args=wp_json_encode(array(array('object'=>false,'value'=>$lead),array('object'=>false,'value'=>'Qualified'),array('object'=>true,'value'=>array('stage'=>'New'))));
    $row=array('lead_id'=>$lead,'generation'=>IDLM_Recovery_Guard::generation(),'hook'=>'idlm_lead_stage_converted','arguments'=>$args,'available_at'=>gmdate('Y-m-d H:i:s',time()+3600),'created_at'=>gmdate('Y-m-d H:i:s'));
    $wpdb->insert($wpdb->prefix.'idlm_lead_outbox',$row);idlm_integration_assert_true(!is_wp_error(IDLM_Lead_Deletion_Service::delete($lead)));idlm_integration_assert_same(0,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_outbox WHERE lead_id={$lead}"));
    $payload=idlm_native_backup_fixture();idlm_integration_assert_true(is_array($payload));idlm_integration_assert_true(!isset($payload['tables']['idlm_lead_outbox']));
    $row['lead_id']=(int)$payload['tables']['idlm_leads'][0]['id'];$wpdb->insert($wpdb->prefix.'idlm_lead_outbox',$row);idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$payload));idlm_integration_assert_same(0,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_outbox"));
    $row['generation']=IDLM_Recovery_Guard::generation();$wpdb->insert($wpdb->prefix.'idlm_lead_outbox',$row);idlm_integration_assert_true(!is_wp_error(IDLM_Lead_Deletion_Service::reset_all('1')));idlm_integration_assert_same(0,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_outbox"));
});
