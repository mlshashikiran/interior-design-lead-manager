<?php
/** INT-008 native retention, receipts, failure and concurrency. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_') !== 0) { exit(1); }
function idlm_capi_ret_fixture($platform='google'): array {
    global $wpdb;[$lead,$log]=idlm_capi_native_fixture($platform);$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$lead));return array($lead,$row,$log);
}
function idlm_capi_ret_fire($row,$platform='google',$force=false):array {
    $method='fire_'.$platform.'_capi';return idlm_capi_native_capture(static fn()=>IDLM_CAPI::$method($row,'Qualified',$force));
}
function idlm_capi_ret_stage($days=180):void {
    update_option('idlm_retention_policy',array('touchpoints_days'=>90,'integration_logs_days'=>$days,'telemetry_days'=>14));
    wp_cache_delete(IDLM_Data_Retention::STATUS_OPTION,'options');wp_cache_delete('notoptions','options');
    update_option(IDLM_Data_Retention::STATUS_OPTION,array('generation'=>IDLM_Recovery_Guard::generation(),'stage'=>2,'processed'=>0,'cutoff'=>current_time('mysql'),'complete'=>false,'remaining'=>array(false,false,true,false,false,false,false)),false);
}
idlm_integration_test('T08-N-INT008 attribution and force cannot resend accepted conversions on any platform',static function():void {
    global $wpdb;
    foreach(array('google','meta','linkedin') as $platform){
        [$lead,$row,$log]=idlm_capi_ret_fixture($platform);[$r,$calls]=idlm_capi_ret_fire($row,$platform);idlm_integration_assert_true($r['success']);idlm_integration_assert_same(1,count($calls));
        $wpdb->delete($wpdb->prefix.'idlm_capi_logs',array('lead_id'=>$lead));
        [$r,$calls]=idlm_capi_native_capture(static function()use($lead,$row,$platform){IDLM_CAPI::handle_attribution_merged($lead,array('gclid'=>'changed','fb_lead_id'=>'changed','li_fat_id'=>'changed'));$method='fire_'.$platform.'_capi';return IDLM_CAPI::$method($row,'Qualified',true);});
        idlm_integration_assert_same(false,$r['attempted']);idlm_integration_assert_same(0,count($calls));
    }
});
idlm_integration_test('T08-N-INT008 ambiguous HTTP outcomes prevent later ordinary and manual replay',static function():void {
    foreach(array('google','meta','linkedin') as $platform){
        foreach(array(408,503) as $code){
            [$lead,$row,$log]=idlm_capi_ret_fixture($platform);$method='fire_'.$platform.'_capi';
            [$r,$calls]=idlm_capi_native_capture(static fn()=>IDLM_CAPI::$method($row,'Qualified'),$code);idlm_integration_assert_same('uncertain',$r['outcome']);idlm_integration_assert_same(1,count($calls));
            [$again,$calls]=idlm_capi_native_capture(static function()use($row,$method,$log){IDLM_CAPI::$method($row,'Qualified',true);return IDLM_CAPI::retry_capi_log($log);});idlm_integration_assert_same(0,count($calls));
        }
    }
});
idlm_integration_test('T08-T16-N-INT008 all platforms retain sent evidence after configured payload expiry and stage reversal',static function():void {
    global $wpdb;
    foreach(array('google','meta','linkedin')as$platform){[$lead,$row,$log]=idlm_capi_ret_fixture($platform);[$r,$calls]=idlm_capi_ret_fire($row,$platform);idlm_integration_assert_true($r['success']);idlm_integration_assert_same(1,count($calls));
        $wpdb->update($wpdb->prefix.'idlm_capi_logs',array('created_at'=>'2000-01-01 00:00:00'),array('lead_id'=>$lead));idlm_capi_ret_stage();$r=IDLM_Data_Retention::prune();idlm_integration_assert_true(is_array($r));idlm_integration_assert_same('0',$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_capi_logs WHERE lead_id=%d",$lead)));
        $wpdb->update($wpdb->prefix.'idlm_leads',array('stage'=>'New','lead_quality'=>''),array('id'=>$lead));[$r,$calls]=idlm_capi_ret_fire($row,$platform);idlm_integration_assert_same(0,count($calls));$wpdb->update($wpdb->prefix.'idlm_leads',array('stage'=>'Qualified','lead_quality'=>'Qualified'),array('id'=>$lead));[$r,$calls]=idlm_capi_ret_fire($row,$platform);idlm_integration_assert_same(0,count($calls));
        idlm_integration_assert_true(IDLM_CAPI_Delivery::read($lead,$platform,$platform==='meta'?'Contact':'CRM Qualified Lead')['sent']);
    }
});
idlm_integration_test('T16-N-INT008 configured 180-day policy preserves thirty-day success and failed logs',static function():void {
    global $wpdb;[$lead,$row,$log]=idlm_capi_ret_fixture();$created=wp_date('Y-m-d H:i:s',time()-30*DAY_IN_SECONDS);$wpdb->update($wpdb->prefix.'idlm_capi_logs',array('created_at'=>$created),array('id'=>$log));$success=IDLM_DB::log_capi_event($lead,'google','CRM Qualified Lead',array(),'Synthetic success','success');$wpdb->update($wpdb->prefix.'idlm_capi_logs',array('created_at'=>$created),array('id'=>$success));
    idlm_capi_ret_stage();idlm_integration_assert_true(is_array(IDLM_Data_Retention::prune()));idlm_integration_assert_same('2',$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_capi_logs WHERE lead_id=%d",$lead)));
});
idlm_integration_test('T16-T08-N-INT008 bounded legacy adoption preserves duplicate-family dedup without loading payloads',static function():void {
    global $wpdb;[$lead,$row,$log]=idlm_capi_ret_fixture();$duplicate=idlm_lifecycle_fixture(array('is_duplicate'=>1,'duplicate_of_id'=>$lead));
    for($i=0;$i<101;$i++){$wpdb->insert($wpdb->prefix.'idlm_capi_logs',array('lead_id'=>$duplicate,'platform'=>'google','event_name'=>'CRM Qualified Lead','payload'=>str_repeat('x',1000),'response'=>'{}','status'=>'success','created_at'=>'2000-01-01 00:00:00'));}
    idlm_capi_ret_stage();$r=IDLM_Data_Retention::prune();idlm_integration_assert_same(100,$r['processed']);idlm_integration_assert_same('1',$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_capi_logs WHERE lead_id=%d",$duplicate)));idlm_integration_assert_true(IDLM_CAPI_Delivery::read($duplicate,'google','CRM Qualified Lead')['sent']);
    [$r,$calls]=idlm_capi_ret_fire($row);idlm_integration_assert_same(0,count($calls));
    idlm_capi_ret_stage();$r=IDLM_Data_Retention::prune();idlm_integration_assert_same(1,$r['processed']);[$r,$calls]=idlm_capi_ret_fire($row);idlm_integration_assert_same(0,count($calls));
});
foreach(array('receipt','delete','commit')as$fault){idlm_integration_test('T16-N-INT008 legacy '.$fault.' failure retains log and retries transactionally',static function()use($fault):void {
    global $wpdb;[$lead,$row,$log]=idlm_capi_ret_fixture();$success=IDLM_DB::log_capi_event($lead,'google','CRM Qualified Lead',array(),'Synthetic success','success');$wpdb->update($wpdb->prefix.'idlm_capi_logs',array('created_at'=>'2000-01-01 00:00:00'),array('id'=>$success));idlm_capi_ret_stage();
    $f=static function($sql)use($fault,$wpdb){$match=$fault==='receipt'?strpos($sql,'INSERT INTO `'.$wpdb->options.'`')===0&&strpos($sql,IDLM_CAPI_Delivery::PREFIX)!==false:($fault==='delete'?strpos($sql,'DELETE FROM '.$wpdb->prefix.'idlm_capi_logs')===0:$sql==='COMMIT');return $match?'SELECT * FROM idlm_int008_missing_table':$sql;};add_filter('query',$f);try{$r=IDLM_Data_Retention::prune();}finally{remove_filter('query',$f);}idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_same((string)$success,$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_capi_logs WHERE id=%d",$success)));idlm_integration_assert_same(null,IDLM_CAPI_Delivery::read($lead,'google','CRM Qualified Lead'));
    $r=IDLM_Data_Retention::prune();idlm_integration_assert_true(is_array($r));idlm_integration_assert_true(IDLM_CAPI_Delivery::read($lead,'google','CRM Qualified Lead')['sent']);
});}
idlm_integration_test('T08-N-INT008 claim and outcome failure withhold send or success and fence future attempts',static function():void {
    global $wpdb;[$lead,$row,$log]=idlm_capi_ret_fixture();$f=static function($sql)use($wpdb){return strpos($sql,'INSERT INTO `'.$wpdb->options.'`')===0&&strpos($sql,IDLM_CAPI_Delivery::PREFIX)!==false?'SELECT * FROM idlm_int008_missing_table':$sql;};add_filter('query',$f);try{[$r,$calls]=idlm_capi_ret_fire($row);}finally{remove_filter('query',$f);}idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same(false,$r['success']);
    $f=static function($sql)use($wpdb){return strpos($sql,'UPDATE `'.$wpdb->options.'`')===0&&strpos($sql,IDLM_CAPI_Delivery::PREFIX)!==false?'SELECT * FROM idlm_int008_missing_table':$sql;};add_filter('query',$f);try{[$r,$calls]=idlm_capi_ret_fire($row);}finally{remove_filter('query',$f);}idlm_integration_assert_same(1,count($calls));idlm_integration_assert_same(false,$r['success']);idlm_integration_assert_same('uncertain',$r['outcome']);$wpdb->delete($wpdb->prefix.'idlm_capi_logs',array('lead_id'=>$lead));[$r,$calls]=idlm_capi_ret_fire($row,'google',true);idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('uncertain',IDLM_CAPI_Delivery::read($lead,'google','CRM Qualified Lead')['status']);
    [$canonical,$canonical_row,$unused]=idlm_capi_ret_fixture();$wpdb->update($wpdb->prefix.'idlm_leads',array('is_duplicate'=>1,'duplicate_of_id'=>$canonical),array('id'=>$lead));[$r,$calls]=idlm_capi_ret_fire($canonical_row,'google',true);idlm_integration_assert_same(0,count($calls));
});
idlm_integration_test('T08-T14-C-INT008 independent ordinary-worker acceptance interruption cannot resend after log expiry',static function():void {
    global $wpdb,$config;[$lead,$row,$log]=idlm_capi_ret_fixture();$r=idlm_integration_worker(array('capi-normal-crash',(string)$lead));idlm_integration_assert_same(77,$r['status'],$r['stderr']);$wpdb->delete($wpdb->prefix.'idlm_capi_logs',array('lead_id'=>$lead));[$r,$calls]=idlm_capi_ret_fire($row);idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('uncertain',IDLM_CAPI_Delivery::read($lead,'google','CRM Qualified Lead')['status']);
});
idlm_integration_test('T08-T16-N-INT008 attribution merge does not replay pruned successes and receipts survive same-site restore',static function():void {
    global $wpdb;idlm_native_backup_fixture();[$lead,$row,$log]=idlm_capi_ret_fixture();[$r,$calls]=idlm_capi_ret_fire($row);idlm_integration_assert_true($r['success']);$wpdb->delete($wpdb->prefix.'idlm_capi_logs',array('lead_id'=>$lead));
    [$r,$calls]=idlm_capi_native_capture(static function()use($lead){return IDLM_CAPI::handle_attribution_merged($lead,array('gclid'=>'synthetic-new-click'));});idlm_integration_assert_same(0,count($calls));
    $snapshot=idlm_native_backup_call('snapshot');idlm_integration_assert_true(!is_wp_error($snapshot));foreach($snapshot['options']as$option){idlm_integration_assert_true(strpos($option['option_name'],IDLM_CAPI_Delivery::PREFIX)!==0);}
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$snapshot));idlm_integration_assert_true(IDLM_CAPI_Delivery::read($lead,'google','CRM Qualified Lead')['sent']);[$r,$calls]=idlm_capi_ret_fire($row);idlm_integration_assert_same(0,count($calls));
});
idlm_integration_test('T16-N-INT008 explicit lead erasure and CRM reset erase receipts and faults roll back',static function():void {
    global $wpdb;[$lead,$row,$log]=idlm_capi_ret_fixture();[$r,$calls]=idlm_capi_ret_fire($row);idlm_integration_assert_true($r['success']);
    $f=static function($sql){return strpos($sql,'DELETE FROM wp_options')===0&&strpos($sql,'capi')!==false&&strpos($sql,'delivery')!==false?'SELECT * FROM idlm_int008_missing_table':$sql;};add_filter('query',$f);try{$r=IDLM_Lead_Deletion_Service::delete($lead);}finally{remove_filter('query',$f);}idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_true(IDLM_CAPI_Delivery::read($lead,'google','CRM Qualified Lead')['sent']);idlm_integration_assert_same(1,IDLM_Lead_Deletion_Service::delete($lead));idlm_integration_assert_same(null,IDLM_CAPI_Delivery::read($lead,'google','CRM Qualified Lead'));
    [$lead,$row,$log]=idlm_capi_ret_fixture();idlm_capi_ret_fire($row);$count=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_leads");idlm_integration_assert_true(!is_wp_error(IDLM_Lead_Deletion_Service::reset_all((string)$count)));idlm_integration_assert_same(null,IDLM_CAPI_Delivery::read($lead,'google','CRM Qualified Lead'));
});
idlm_integration_test('T08-N-INT008 malformed receipts and unsupported options engine fail closed before transport',static function():void {
    global $wpdb;[$lead,$row,$log]=idlm_capi_ret_fixture();$key=IDLM_CAPI_Delivery::key($lead,'google','CRM Qualified Lead');update_option($key,'invalid-receipt',false);[$r,$calls]=idlm_capi_ret_fire($row);idlm_integration_assert_same(0,count($calls));delete_option($key);
    $wpdb->query("ALTER TABLE {$wpdb->options} ENGINE=MyISAM");try{[$r,$calls]=idlm_capi_ret_fire($row);idlm_integration_assert_same(0,count($calls));}finally{$wpdb->query("ALTER TABLE {$wpdb->options} ENGINE=InnoDB");}
});
idlm_integration_test('T08-T16-C-INT008 independent pruning and dispatch never reopen a legacy accepted event',static function():void {
    global $wpdb;[$lead,$row,$log]=idlm_capi_ret_fixture();$success=IDLM_DB::log_capi_event($lead,'google','CRM Qualified Lead',array(),'Synthetic legacy acceptance','success');$wpdb->update($wpdb->prefix.'idlm_capi_logs',array('created_at'=>'2000-01-01 00:00:00'),array('id'=>$success));idlm_capi_ret_stage();@unlink(WP_CONTENT_DIR.'/capi-provider.log');@unlink(WP_CONTENT_DIR.'/capi-barrier');$jobs=array();
    foreach(array('capi-prune-race','capi-normal-race')as$mode){$pipes=array();$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/worker.php').' '.$mode.' '.(int)$lead;$proc=proc_open($cmd,array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$jobs[]=array($proc,$pipes);}
    foreach($jobs as[$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);idlm_integration_assert_same(0,proc_close($proc),$out.$err);}
    idlm_integration_assert_true(!is_file(WP_CONTENT_DIR.'/capi-provider.log'));idlm_integration_assert_same(null,$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_capi_logs WHERE id=%d",$success)));idlm_integration_assert_true(IDLM_CAPI_Delivery::read($lead,'google','CRM Qualified Lead')['sent']);
});
idlm_integration_test('T16-N-INT008 orphaned legacy diagnostics expire without recreating erased-lead receipts',static function():void {
    global $wpdb;$id=987654321;$log=IDLM_DB::log_capi_event($id,'google','CRM Qualified Lead',array(),'Synthetic historical orphan','success');$wpdb->update($wpdb->prefix.'idlm_capi_logs',array('created_at'=>'2000-01-01 00:00:00'),array('id'=>$log));idlm_capi_ret_stage();idlm_integration_assert_true(is_array(IDLM_Data_Retention::prune()));idlm_integration_assert_same(null,IDLM_CAPI_Delivery::read($id,'google','CRM Qualified Lead'));idlm_integration_assert_same(null,$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_capi_logs WHERE id=%d",$log)));
});
