<?php
/** Native backup aggregates, fault injection and recovery fencing. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_') !== 0) { exit(1); }
function idlm_native_backup_call($method,$payload=null){$r=new ReflectionMethod('IDLM_Backup',$method);if (PHP_VERSION_ID < 80100) { $r->setAccessible(true); }return $method==='snapshot'?$r->invoke(null):$r->invoke(null,$payload);}
function idlm_native_backup_fixture(){
    global $wpdb,$administrator_id;
    foreach(array_reverse(array_keys(IDLM_Backup_Validator::columns())) as $suffix){$wpdb->query('DELETE FROM '.$wpdb->prefix.$suffix);}
    $now=current_time('mysql');$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'SYNTHETIC RECOVERY','email'=>'recovery@example.test','assigned_rep_id'=>$administrator_id,'created_at'=>$now,'updated_at'=>$now));$lead=$wpdb->insert_id;
    $wpdb->insert($wpdb->prefix.'idlm_quotes',array('lead_id'=>$lead,'quote_number'=>'SYN-2026-0001','status'=>'Draft','created_at'=>$now,'updated_at'=>$now));$quote=$wpdb->insert_id;
    $wpdb->insert($wpdb->prefix.'idlm_quote_revisions',array('quote_id'=>$quote,'revision_number'=>1,'revision_name'=>'R1','status'=>'Draft','created_at'=>$now,'updated_at'=>$now));$revision=$wpdb->insert_id;
    $wpdb->update($wpdb->prefix.'idlm_quotes',array('current_revision_id'=>$revision),array('id'=>$quote));
    $wpdb->insert($wpdb->prefix.'idlm_quote_revision_sections',array('revision_id'=>$revision,'section_name'=>'General'));$section=$wpdb->insert_id;
    $wpdb->insert($wpdb->prefix.'idlm_quote_revision_items',array('revision_id'=>$revision,'section_id'=>$section,'description'=>'Synthetic immutable line','quantity'=>'1.00','unit_rate'=>'100.00'));
    $wpdb->insert($wpdb->prefix.'idlm_number_sequences',array('sequence_type'=>'quote','sequence_year'=>2026,'last_number'=>1,'updated_at'=>$now));
    $wpdb->insert($wpdb->prefix.'idlm_telemetry_logs',array('log_type'=>'synthetic','severity'=>'info','message'=>'Old diagnostic preserved','created_at'=>'2020-01-01 00:00:00'));
    update_option('idlm_synthetic_recovery_setting','original',false);update_option('idlm_interakt_api_key','synthetic-protected-key',false);
    $payload=idlm_native_backup_call('snapshot');
    return $payload;
}
function idlm_native_backup_digest(){global $wpdb;$out=[];foreach(IDLM_Backup_Validator::columns() as $suffix=>$columns){$out[$suffix]=$wpdb->get_results('SELECT * FROM '.$wpdb->prefix.$suffix,ARRAY_A);} $out['options']=$wpdb->get_results("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name LIKE 'idlm_%' ORDER BY option_name",ARRAY_A);return hash('sha256',wp_json_encode($out));}
idlm_integration_test('complete same-site backup roundtrips rows, old diagnostics, modern autoload and protected credentials',static function():void{
    global $wpdb;$p=idlm_native_backup_fixture();idlm_integration_assert_true(!is_wp_error($p),'Snapshot failed');update_option('idlm_synthetic_recovery_setting','changed',false);get_option('idlm_synthetic_recovery_setting');$epoch=IDLM_Recovery_Guard::generation();
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$p));idlm_integration_assert_same('original',get_option('idlm_synthetic_recovery_setting'));idlm_integration_assert_same('synthetic-protected-key',get_option('idlm_interakt_api_key'));idlm_integration_assert_same($epoch+1,IDLM_Recovery_Guard::generation());
    $after=idlm_native_backup_call('snapshot');idlm_integration_assert_same($p['tables'],$after['tables']);idlm_integration_assert_same($p['options'],$after['options']);
});
idlm_integration_test('restore rejects malformed aggregates, cross-object references, users and serialized objects without writes',static function():void{
    $p=idlm_native_backup_fixture();$cases=[];$x=$p;unset($x['tables']['idlm_tasks']);$cases[]=$x;$x=$p;unset($x['tables']['idlm_leads'][0]['email']);$cases[]=$x;$x=$p;$x['tables']['idlm_quotes'][0]['lead_id']='999999';$cases[]=$x;$x=$p;$x['tables']['idlm_quote_revision_items'][0]['section_id']='999999';$cases[]=$x;$x=$p;$x['tables']['idlm_leads'][0]['created_at']='2026-02-30 00:00:00';$cases[]=$x;$x=$p;$x['tables']['idlm_quote_revision_items'][0]['unit_rate']='100000000000000000.00';$cases[]=$x;$x=$p;$x['user_identities'][array_key_first($x['user_identities'])]='foreign_login';$cases[]=$x;$x=$p;$x['options'][]=array('option_name'=>'idlm_test_object','option_value'=>'O:8:"stdClass":0:{}','autoload'=>'off');$cases[]=$x;
    foreach($cases as $bad){$before=idlm_native_backup_digest();idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload',$bad)));idlm_integration_assert_same($before,idlm_native_backup_digest());}
});
idlm_integration_test('native collation collisions are staged before persistent deletion',static function():void{
    global $wpdb;$p=idlm_native_backup_fixture();$quote=$p['tables']['idlm_quotes'][0];$quote['id']=(string)((int)$quote['id']+1);$quote['quote_number']=strtolower($quote['quote_number']);$quote['current_revision_id']='0';$quote['accepted_revision_id']='0';$quote['save_request_key']=null;$p['tables']['idlm_quotes'][]=$quote;$before=idlm_native_backup_digest();$old=$wpdb->suppress_errors(true);
    try{idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload',$p)));}finally{$wpdb->suppress_errors($old);}idlm_integration_assert_same($before,idlm_native_backup_digest());
});
idlm_integration_test('late table, settings and commit failures roll back rows, settings, generation and queues',static function():void{
    global $wpdb;$p=idlm_native_backup_fixture();wp_schedule_single_event(time()+600,'idlm_async_capi_upload_event',array(999,'New',IDLM_Recovery_Guard::generation()));
    foreach(array('INSERT INTO `'.$wpdb->prefix.'idlm_quotes` SELECT','REPLACE INTO `'.$wpdb->options.'`','COMMIT') as $prefix){$before=idlm_native_backup_digest();$cron=get_option('cron');$fail=static function($sql)use($prefix){return strpos($sql,$prefix)===0?'INSERT INTO idlm_test_missing_failure VALUES (1)':$sql;};$old=$wpdb->suppress_errors(true);add_filter('query',$fail);
        try{idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload',$p)),'Injected failure was accepted');}finally{remove_filter('query',$fail);$wpdb->suppress_errors($old);}idlm_integration_assert_same($before,idlm_native_backup_digest());idlm_integration_assert_same($cron,get_option('cron'));}
});
idlm_integration_test('restore requires complete physical schema, uniques and transactional options',static function():void{
    global $wpdb;$p=idlm_native_backup_fixture();$before=idlm_native_backup_digest();$wpdb->query("ALTER TABLE {$wpdb->options} ENGINE=MyISAM");try{idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload',$p)));}finally{$wpdb->query("ALTER TABLE {$wpdb->options} ENGINE=InnoDB");}idlm_integration_assert_same($before,idlm_native_backup_digest());
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_quotes DROP INDEX quote_number");try{idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('snapshot')));idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload',$p)));}finally{$wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_quotes ADD UNIQUE KEY quote_number (quote_number)");}
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ADD COLUMN synthetic_unversioned int NULL");try{idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('snapshot')));}finally{$wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks DROP COLUMN synthetic_unversioned");}
});
idlm_integration_test('old restore retains numbering highwater and discards runtime jobs and autosaves',static function():void{
    global $wpdb;$p=idlm_native_backup_fixture();$wpdb->query("UPDATE {$wpdb->prefix}idlm_number_sequences SET last_number=50");$key='idlm_interakt_batch_'.str_repeat('a',64);update_option($key,array('status'=>'queued'),false);set_transient('idlm_quote_autosave_1_1',array('items'=>array('old')),3600);
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$p));idlm_integration_assert_same('50',$wpdb->get_var("SELECT last_number FROM {$wpdb->prefix}idlm_number_sequences"));idlm_integration_assert_same(false,get_option($key,false));idlm_integration_assert_same(false,get_transient('idlm_quote_autosave_1_1'));
    unset($p['schema'],$p['user_identities']);$p['options'][]=array('option_name'=>'idlm_interakt_api_key','option_value'=>'foreign-key','autoload'=>'yes');idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$p));idlm_integration_assert_same('synthetic-protected-key',get_option('idlm_interakt_api_key'));
});
idlm_integration_test('active native connection fences restore/reset and stale epochs never execute or retry',static function():void{
    global $wpdb;$p=idlm_native_backup_fixture();$socket=substr(DB_HOST,strpos(DB_HOST,':')+1);$other=new mysqli('localhost',DB_USER,DB_PASSWORD,DB_NAME,0,$socket);$name=IDLM_Recovery_Guard::name('worker_1');$other->query("SELECT GET_LOCK('$name',0)");$before=idlm_native_backup_digest();
    try{idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload',$p)));idlm_integration_assert_true(is_wp_error(IDLM_Lead_Deletion_Service::reset_all()));idlm_integration_assert_same($before,idlm_native_backup_digest());}finally{$other->close();}
    $epoch=IDLM_Recovery_Guard::generation();idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$p));$called=0;$before=idlm_native_backup_digest();idlm_integration_assert_same(false,IDLM_Recovery_Guard::run(static function()use(&$called){$called++;},$epoch,true,'idlm_async_capi_upload_event',array(1,'New',$epoch)));idlm_integration_assert_same(0,$called);idlm_integration_assert_same($before,idlm_native_backup_digest());
    foreach(array(array(),true,-1,'bad') as $bad){idlm_integration_assert_same(false,IDLM_Recovery_Guard::enter_worker($bad,true));}idlm_integration_assert_true(IDLM_Recovery_Guard::enter_worker(IDLM_Recovery_Guard::generation(),true));IDLM_Recovery_Guard::exit_worker();
});
idlm_integration_test('snapshot stays consistent while independent connection commits coupled lead and settings writes',static function():void{
    global $wpdb;$p=idlm_native_backup_fixture();$lead=$p['tables']['idlm_leads'][0]['id'];$socket=substr(DB_HOST,strpos(DB_HOST,':')+1);$other=new mysqli('localhost',DB_USER,DB_PASSWORD,DB_NAME,0,$socket);$changed=false;
    $barrier=static function($sql)use($other,$lead,&$changed,$wpdb){if(!$changed && $sql==='SELECT * FROM `'.$wpdb->prefix.'idlm_quotes`'){$changed=true;$other->begin_transaction();$other->query("UPDATE {$wpdb->prefix}idlm_leads SET first_name='NEW SNAPSHOT' WHERE id=".(int)$lead);$other->query("UPDATE {$wpdb->options} SET option_value='concurrent' WHERE option_name='idlm_synthetic_recovery_setting'");$other->commit();}return $sql;};add_filter('query',$barrier);
    try{$snapshot=idlm_native_backup_call('snapshot');}finally{remove_filter('query',$barrier);$other->close();}idlm_integration_assert_true($changed);idlm_integration_assert_same($p['tables'],$snapshot['tables']);idlm_integration_assert_same($p['options'],$snapshot['options']);idlm_integration_assert_same('NEW SNAPSHOT',$wpdb->get_var("SELECT first_name FROM {$wpdb->prefix}idlm_leads WHERE id=".(int)$lead));
});
idlm_integration_test('failed export read returns an error and never substitutes an empty table',static function():void{
    global $wpdb;idlm_native_backup_fixture();$fail=static function($sql)use($wpdb){return $sql==='SELECT * FROM `'.$wpdb->prefix.'idlm_tasks`'?'SELECT * FROM idlm_test_missing_read':$sql;};$old=$wpdb->suppress_errors(true);add_filter('query',$fail);try{idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('snapshot')));}finally{remove_filter('query',$fail);$wpdb->suppress_errors($old);}
});
idlm_integration_test('provider callbacks reject stale and legacy generations before logging, retries or HTTP',static function():void{
    global $config;$p=idlm_native_backup_fixture();$epoch=IDLM_Recovery_Guard::generation();idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$p));$lead=(int)$p['tables']['idlm_leads'][0]['id'];$before=idlm_native_backup_digest();$path=$config['content_dir'].'/outbound-attempts.log';$out=is_file($path)?file_get_contents($path):'';
    IDLM_CAPI::process_capi_event($lead,'New',$epoch);IDLM_Interakt::process_async_track_event(array('traits'=>array('email'=>'stale@example.test')),'synthetic-key',$lead,$epoch);IDLM_Interakt::process_async_track_event(array(),'synthetic-key',$lead);idlm_integration_assert_same($before,idlm_native_backup_digest());idlm_integration_assert_same($out,is_file($path)?file_get_contents($path):'');
});
idlm_integration_test('lost session lease fails closed before a provider callback can execute',static function():void{
    global $wpdb;$p=idlm_native_backup_fixture();$lead=(object)$p['tables']['idlm_leads'][0];idlm_integration_assert_true(IDLM_Recovery_Guard::enter_worker());idlm_integration_assert_true(IDLM_Recovery_Guard::lease_valid());$name=IDLM_Recovery_Guard::name('worker_1');$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$name));
    try{idlm_integration_assert_true(!IDLM_Recovery_Guard::lease_valid());$before=idlm_native_backup_digest();idlm_integration_assert_same(false,IDLM_CAPI::fire_meta_capi($lead,'New'));idlm_integration_assert_same($before,idlm_native_backup_digest());}finally{IDLM_Recovery_Guard::exit_worker();}
});
idlm_integration_test('recovery worker waits for brief coordinator contention but rejects active restore',static function():void{
    global $wpdb;
    list($host, $port, $socket) = $wpdb->parse_db_host(DB_HOST);
    $other = new mysqli($host, DB_USER, DB_PASSWORD, DB_NAME, $port ?: 0, $socket ?: null);
    $coordinator = IDLM_Recovery_Guard::name('coordinator');
    $restore = IDLM_Recovery_Guard::name('restore');
    try {
        idlm_integration_assert_same('1', (string) $other->query("SELECT GET_LOCK('" . $other->real_escape_string($coordinator) . "', 0)")->fetch_row()[0]);
        idlm_integration_assert_true($other->query("SELECT SLEEP(0.3), RELEASE_LOCK('" . $other->real_escape_string($coordinator) . "')", MYSQLI_ASYNC));
        $start = microtime(true);
        $entered = IDLM_Recovery_Guard::enter_worker();
        $elapsed = microtime(true) - $start;
        if ($entered) { IDLM_Recovery_Guard::exit_worker(); }
        idlm_integration_assert_true($entered && $elapsed >= 0.1 && $elapsed < 2, 'Worker did not wait for the coordinator');
        $other->reap_async_query();
        idlm_integration_assert_same('1', (string) $other->query("SELECT GET_LOCK('" . $other->real_escape_string($restore) . "', 0)")->fetch_row()[0]);
        idlm_integration_assert_same(false, IDLM_Recovery_Guard::enter_worker(), 'Worker entered during restore');
        $other->query("SELECT RELEASE_LOCK('" . $other->real_escape_string($restore) . "')");
        idlm_integration_assert_same('1', (string) $other->query("SELECT GET_LOCK('" . $other->real_escape_string($coordinator) . "', 0)")->fetch_row()[0]);
        $start = microtime(true);
        idlm_integration_assert_same(false, IDLM_Recovery_Guard::enter_worker(), 'Worker entered with coordinator held');
        idlm_integration_assert_true(microtime(true) - $start >= 1.8, 'Coordinator wait was not bounded as expected');
    } finally { $other->close(); }
});
idlm_integration_test('lease lost during intercepted provider transport prevents result logs and retry writes',static function():void{
    global $wpdb;$p=idlm_native_backup_fixture();$lead=(int)$p['tables']['idlm_leads'][0]['id'];$before=idlm_native_backup_digest();$drop=static function($pre){global $wpdb;$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',IDLM_Recovery_Guard::name('worker_1')));return $pre;};add_filter('pre_http_request',$drop,1);
    try{idlm_integration_assert_same(false,IDLM_Interakt::send_track_event_to_interakt(array('event'=>'synthetic'),'synthetic-key',$lead));}finally{remove_filter('pre_http_request',$drop,1);}idlm_integration_assert_same($before,idlm_native_backup_digest());
});
idlm_integration_test('native option-name collisions and protected case aliases reject before deletion',static function():void{
    global $wpdb;$p=idlm_native_backup_fixture();$p['options'][]=array('option_name'=>'idlm_SYNTHETIC_recovery_setting','option_value'=>'collision','autoload'=>'off');$before=idlm_native_backup_digest();$old=$wpdb->suppress_errors(true);try{idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload',$p)));}finally{$wpdb->suppress_errors($old);}idlm_integration_assert_same($before,idlm_native_backup_digest());
    $p=idlm_native_backup_fixture();$p['options'][]=array('option_name'=>'idlm_ROLES_version','option_value'=>'foreign-marker','autoload'=>'off');$before=idlm_native_backup_digest();idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload',$p)));idlm_integration_assert_same($before,idlm_native_backup_digest());
});
