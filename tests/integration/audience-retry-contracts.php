<?php
/** INT-009 audience outcome and exclusion contracts. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_aud_rule($platform = 'meta', $target = 'synthetic-audience', $stage = 'Qualified'): array {
    return array('platform'=>$platform, 'audience_id'=>$target, 'stage'=>$stage, 'call_status'=>'all', 'lead_quality'=>'all', 'enabled'=>'1');
}
function idlm_aud_lead(array $fields = array()): int {
    global $wpdb;
    $wpdb->insert($wpdb->prefix.'idlm_leads', array_merge(array('first_name'=>'Synthetic audience', 'last_name'=>'Fixture', 'email'=>'audience@example.test', 'phone'=>'919999900009', 'stage'=>'Qualified', 'created_at'=>current_time('mysql'), 'updated_at'=>current_time('mysql')), $fields));
    return (int) $wpdb->insert_id;
}
function idlm_aud_provider($response, callable $callback): array {
    $calls = array();
    $filter = static function($pre, $args, $url) use (&$calls, $response) {
        if (strpos($url, '/users') === false && strpos($url, '/contacts') === false) { return $pre; }
        $calls[] = array('url'=>$url, 'args'=>$args, 'body'=>json_decode($args['body'], true));
        return is_callable($response) ? $response($url, $args) : $response;
    };
    add_filter('pre_http_request', $filter, PHP_INT_MAX, 3);
    try { $result = $callback(); } finally { remove_filter('pre_http_request', $filter, PHP_INT_MAX); }
    return array($result, $calls);
}
function idlm_aud_response($code = 200, $body = '{}'): array {
    return array('response'=>array('code'=>$code), 'body'=>$body, 'headers'=>array());
}
idlm_integration_test('T08-N-INT009 failed manual upload retains selected work', static function(): void {
    idlm_aud_clean(); $lead = idlm_aud_lead();
    update_option('idlm_audience_sync_rules', array(idlm_aud_rule())); update_option('idlm_meta_access_token', 'synthetic-audience-token');
    update_option('idlm_audience_sync_queue', array($lead));
    [$result, $calls] = idlm_aud_provider(idlm_aud_response(429), static function() { return IDLM_Audiences::run_batch_sync(true); });
    idlm_integration_assert_same(1, count($calls)); idlm_integration_assert_same(array($lead), get_option('idlm_audience_sync_queue'));
});

function idlm_aud_clean(): void {
    global $wpdb;
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name IN (%s,%s)", $wpdb->esc_like(IDLM_Audience_Dispatch::PREFIX).'%', IDLM_Audience_Dispatch::QUEUE, IDLM_Audience_Dispatch::SCAN));
    wp_cache_flush(); wp_clear_scheduled_hook(IDLM_Audience_Dispatch::HOOK);
    update_option('idlm_meta_access_token','synthetic-audience-token'); update_option('idlm_linkedin_access_token','synthetic-linkedin-token');
}
function idlm_aud_state($id, $rule): array {
    wp_cache_delete(IDLM_Audience_Dispatch::PREFIX.$id,'options'); wp_cache_delete('alloptions','options');
    return get_option(IDLM_Audience_Dispatch::PREFIX.$id)['states'][IDLM_Audience_Dispatch::rule_key($rule)];
}
function idlm_aud_due($id, $rule): void {
    $name=IDLM_Audience_Dispatch::PREFIX.$id; wp_cache_delete($name,'options'); $work=get_option($name); $work['states'][IDLM_Audience_Dispatch::rule_key($rule)]['next_at']=0; update_option($name,$work,false);
}
idlm_integration_test('T08-N-INT009 queued duplicate and changed filters cannot override current exclusions',static function():void {
    global $wpdb; idlm_aud_clean(); $rule=idlm_aud_rule(); $rule['call_status']='Connected'; $rule['lead_quality']='Qualified'; update_option('idlm_audience_sync_rules',array($rule));
    $ids=array(); foreach(array(array('is_duplicate'=>1),array('stage'=>'New'),array('call_status'=>'Not Connected'),array('lead_quality'=>'Unqualified'),array()) as $change) { $ids[]=idlm_aud_lead(array_merge(array('call_status'=>'Connected','lead_quality'=>'Qualified'),$change)); }
    idlm_integration_assert_true(is_array(IDLM_Audience_Dispatch::enqueue($ids))); $unselected=idlm_aud_lead(array('call_status'=>'Connected','lead_quality'=>'Qualified'));
    [$result,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});
    idlm_integration_assert_same(1,count($calls)); idlm_integration_assert_same(1,count($calls[0]['body']['payload']['data'])); idlm_integration_assert_same(4,$result['excluded']); idlm_integration_assert_same(array(),get_option(IDLM_Audience_Dispatch::QUEUE)); idlm_integration_assert_same(false,get_option(IDLM_Audience_Dispatch::PREFIX.$unselected));
});
idlm_integration_test('T08-N-INT009 partial destination success is retained and only failed destination retries',static function():void {
    idlm_aud_clean(); $id=idlm_aud_lead(); $meta=idlm_aud_rule(); $linkedin=idlm_aud_rule('linkedin','urn:li:adSegment:synthetic'); update_option('idlm_audience_sync_rules',array($meta,$linkedin)); IDLM_Audience_Dispatch::enqueue(array($id));
    [$r,$calls]=idlm_aud_provider(static function($url){return idlm_aud_response(strpos($url,'linkedin')!==false?503:200);},static function(){return IDLM_Audiences::run_batch_sync(true);});
    idlm_integration_assert_same(2,count($calls)); idlm_integration_assert_same('sent',idlm_aud_state($id,$meta)['status']); idlm_integration_assert_same('failed',idlm_aud_state($id,$linkedin)['status']); idlm_integration_assert_same(array($id),get_option(IDLM_Audience_Dispatch::QUEUE));
    [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(0,count($calls));
    idlm_aud_due($id,$linkedin); [$r,$calls]=idlm_aud_provider(idlm_aud_response(201),static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(1,count($calls)); idlm_integration_assert_true(strpos($calls[0]['url'],'linkedin')!==false); idlm_integration_assert_same(array(),get_option(IDLM_Audience_Dispatch::QUEUE));
});
idlm_integration_test('T08-T14-N-INT009 transient retry limit Retry After and explicit reselection preserve success',static function():void {
    idlm_aud_clean(); $id=idlm_aud_lead(); $rule=idlm_aud_rule(); update_option('idlm_audience_sync_rules',array($rule)); IDLM_Audience_Dispatch::enqueue(array($id));
    $response=idlm_aud_response(429); $response['headers']['retry-after']='300';
    for($i=1;$i<=3;$i++) { idlm_aud_due($id,$rule); [$r,$calls]=idlm_aud_provider($response,static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(1,count($calls)); $state=idlm_aud_state($id,$rule); idlm_integration_assert_same($i,$state['attempts']); if($i<3){idlm_integration_assert_true($state['next_at']>=time()+290);} }
    idlm_integration_assert_same('blocked',idlm_aud_state($id,$rule)['status']); [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(0,count($calls));
    IDLM_Audience_Dispatch::enqueue(array($id)); [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(1,count($calls)); idlm_integration_assert_same(1,idlm_aud_state($id,$rule)['attempts']);
});
idlm_integration_test('T08-N-INT009 permanent rejection timeout and malformed acceptance remain queued with truthful outcomes',static function():void {
    foreach(array(idlm_aud_response(400),new WP_Error('timeout','Synthetic timeout'),idlm_aud_response(200,'invalid-json'),idlm_aud_response(200,'{"error":{"message":"Synthetic rejection"}}')) as $index=>$response) {
        idlm_aud_clean(); $id=idlm_aud_lead(); $rule=idlm_aud_rule(); update_option('idlm_audience_sync_rules',array($rule)); IDLM_Audience_Dispatch::enqueue(array($id)); [$r,$calls]=idlm_aud_provider($response,static function(){return IDLM_Audiences::run_batch_sync(true);});
        idlm_integration_assert_same(1,count($calls)); idlm_integration_assert_same(0,$r['accepted']); idlm_integration_assert_same(array($id),get_option(IDLM_Audience_Dispatch::QUEUE)); idlm_integration_assert_same(in_array($index,array(1,2),true)?'uncertain':'blocked',idlm_aud_state($id,$rule)['status']);
        [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(0,count($calls));
        if(in_array($index,array(1,2),true)) { IDLM_Audience_Dispatch::enqueue(array($id)); [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(0,count($calls)); }
    }
});
idlm_integration_test('T08-N-INT009 disabled changed and missing credential rules retain work and current credentials are used',static function():void {
    idlm_aud_clean(); $id=idlm_aud_lead(); $rule=idlm_aud_rule(); update_option('idlm_audience_sync_rules',array($rule)); IDLM_Audience_Dispatch::enqueue(array($id));
    foreach(array(array_merge($rule,array('enabled'=>'0')),array_merge($rule,array('stage'=>'Won')),$rule)as$index=>$current) { update_option('idlm_audience_sync_rules',array($current)); if($index===2){delete_option('idlm_meta_access_token');} [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(0,count($calls)); idlm_integration_assert_same(array($id),get_option(IDLM_Audience_Dispatch::QUEUE)); }
    update_option('idlm_meta_access_token','synthetic-rotated-token'); [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(1,count($calls)); idlm_integration_assert_same('Bearer synthetic-rotated-token',$calls[0]['args']['headers']['Authorization']);idlm_integration_assert_true(strpos($calls[0]['url'],'synthetic-rotated-token')===false);
});
idlm_integration_test('T08-T14-N-INT009 queue and claim write failures roll back before HTTP',static function():void {
    global $wpdb; idlm_aud_clean(); $id=idlm_aud_lead(); $rule=idlm_aud_rule(); update_option('idlm_audience_sync_rules',array($rule));
    $f=static function($sql){return strpos($sql,'INSERT INTO `wp_options`')===0 && strpos($sql,'idlm_audience_sync_queue')!==false?'INVALID SYNTHETIC AUDIENCE QUEUE SQL':$sql;}; add_filter('query',$f); try{$r=IDLM_Audience_Dispatch::enqueue(array($id));}finally{remove_filter('query',$f);} idlm_integration_assert_true(is_wp_error($r)); idlm_integration_assert_same(false,get_option(IDLM_Audience_Dispatch::QUEUE)); idlm_integration_assert_same(false,get_option(IDLM_Audience_Dispatch::PREFIX.$id));
    IDLM_Audience_Dispatch::enqueue(array($id)); $f=static function($sql){return strpos($sql,'UPDATE `wp_options`')===0 && strpos($sql,'idlm_audience_work_')!==false?'INVALID SYNTHETIC AUDIENCE CLAIM SQL':$sql;}; add_filter('query',$f);try{[$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});}finally{remove_filter('query',$f);} idlm_integration_assert_same(0,count($calls)); idlm_integration_assert_true(is_wp_error($r)); idlm_integration_assert_same('pending',idlm_aud_state($id,$rule)['status']);
});
idlm_integration_test('T08-T14-N-INT009 log outcome and acknowledgement commit failure retain unknown claim',static function():void {
    global $wpdb;
    foreach(array('log','ack','commit')as$mode) {
        idlm_aud_clean(); $id=idlm_aud_lead(); $rule=idlm_aud_rule(); update_option('idlm_audience_sync_rules',array($rule)); IDLM_Audience_Dispatch::enqueue(array($id)); $after=false;
        $f=static function($sql)use(&$after,$mode){if($after && (($mode==='log' && strpos($sql,'INSERT INTO `wp_idlm_audience_sync_logs`')===0)||($mode==='ack' && strpos($sql,'UPDATE `wp_options`')===0 && strpos($sql,'idlm_audience_work_')!==false)||($mode==='commit' && $sql==='COMMIT'))){$after=false;return 'INVALID SYNTHETIC AUDIENCE OUTCOME SQL';}return $sql;}; add_filter('query',$f);
        try{[$r,$calls]=idlm_aud_provider(static function()use(&$after){$after=true;return idlm_aud_response();},static function(){return IDLM_Audiences::run_batch_sync(true);});}finally{remove_filter('query',$f);}
        idlm_integration_assert_same(1,count($calls)); idlm_integration_assert_same('processing',idlm_aud_state($id,$rule)['status'],$mode); [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(0,count($calls)); idlm_integration_assert_same('uncertain',idlm_aud_state($id,$rule)['status']);
    }
});
idlm_integration_test('T08-N-INT009 manual pages are bounded and continuation never adds unrelated matching contacts',static function():void {
    idlm_aud_clean(); $rule=idlm_aud_rule(); update_option('idlm_audience_sync_rules',array($rule)); $ids=array();for($i=0;$i<101;$i++){$ids[]=idlm_aud_lead();} IDLM_Audience_Dispatch::enqueue(array_slice($ids,0,100));IDLM_Audience_Dispatch::enqueue(array_slice($ids,100));idlm_aud_lead();
    [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);}); idlm_integration_assert_same(1,count($calls)); idlm_integration_assert_same(100,count($calls[0]['body']['payload']['data'])); idlm_integration_assert_same(1,$r['pending']); idlm_integration_assert_true((bool)wp_next_scheduled(IDLM_Audience_Dispatch::HOOK,array(IDLM_Recovery_Guard::generation(),true,gmdate('Y-m-d'))));
    [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_same(1,count($calls[0]['body']['payload']['data']));idlm_integration_assert_same(0,$r['pending']);
});
idlm_integration_test('T08-T14-N-INT009 scheduled pages resume a primary cursor and do not repeat same day acceptance',static function():void {
    idlm_aud_clean();$rule=idlm_aud_rule('meta','scheduled-synthetic','Synthetic Audience Scheduled');update_option('idlm_audience_sync_rules',array($rule)); for($i=0;$i<101;$i++){idlm_aud_lead(array('stage'=>$rule['stage']));}
    [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync();}); idlm_integration_assert_same(100,count($calls[0]['body']['payload']['data']));
    [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync();});idlm_integration_assert_same(1,count($calls[0]['body']['payload']['data']));
    [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync();});idlm_integration_assert_same(0,count($calls));
});
idlm_integration_test('T08-T14-N-INT009 corrupt receipts unsupported engines and stale callbacks refuse transport',static function():void {
    global $wpdb;idlm_aud_clean();$rule=idlm_aud_rule();$id=idlm_aud_lead();update_option('idlm_audience_sync_rules',array($rule));IDLM_Audience_Dispatch::enqueue(array($id));$name=IDLM_Audience_Dispatch::PREFIX.$id;$original=get_option($name);update_option($name,'malformed',false);[$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_same(0,count($calls));update_option($name,$original,false);
    $wpdb->query("ALTER TABLE {$wpdb->options} ENGINE=MyISAM");try{[$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_same(0,count($calls));idlm_integration_assert_true(is_wp_error($r));}finally{$wpdb->query("ALTER TABLE {$wpdb->options} ENGINE=InnoDB");}
    [$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audience_Dispatch::resume(IDLM_Recovery_Guard::generation()+1,true,gmdate('Y-m-d'));});idlm_integration_assert_same(0,count($calls));idlm_integration_assert_true(is_wp_error($r));
});
idlm_integration_test('T08-T16-N-INT009 cancellation erasure reset and restore remove queued runtime state',static function():void {
    global $wpdb;idlm_aud_clean();$rule=idlm_aud_rule();$id=idlm_aud_lead();update_option('idlm_audience_sync_rules',array($rule));IDLM_Audience_Dispatch::enqueue(array($id));[$r,$calls]=idlm_aud_provider(new WP_Error('timeout','Synthetic unknown acceptance'),static function(){return IDLM_Audiences::run_batch_sync(true);});
    idlm_integration_assert_same(1,IDLM_Audience_Dispatch::cancel(array($id))['removed']);idlm_integration_assert_same('uncertain',idlm_aud_state($id,$rule)['status']);IDLM_Audience_Dispatch::enqueue(array($id));[$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_same(0,count($calls));
    $backup=idlm_native_backup_call('snapshot');idlm_integration_assert_true(is_array($backup));foreach($backup['options']as$option){idlm_integration_assert_true(strpos($option['option_name'],IDLM_Audience_Dispatch::PREFIX)!==0);}
    idlm_integration_assert_same(1,IDLM_Lead_Deletion_Service::delete_many(array($id)));idlm_integration_assert_same(false,get_option(IDLM_Audience_Dispatch::PREFIX.$id));idlm_integration_assert_same(array(),get_option(IDLM_Audience_Dispatch::QUEUE));
    idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$backup));idlm_integration_assert_same(false,get_option(IDLM_Audience_Dispatch::PREFIX.$id));idlm_integration_assert_same(false,get_option(IDLM_Audience_Dispatch::QUEUE));
    IDLM_Audience_Dispatch::enqueue(array($id));idlm_integration_assert_true(is_array(IDLM_Lead_Deletion_Service::reset_all()));idlm_integration_assert_same(false,get_option(IDLM_Audience_Dispatch::PREFIX.$id));
});

idlm_integration_test('T08-T14-N-INT009 a changed filter cannot reopen an unknown destination and reselection uses current definitions',static function():void {
    idlm_aud_clean();$id=idlm_aud_lead();$rule=idlm_aud_rule();update_option('idlm_audience_sync_rules',array($rule));IDLM_Audience_Dispatch::enqueue(array($id));idlm_aud_provider(new WP_Error('timeout','Synthetic unknown'),static function(){return IDLM_Audiences::run_batch_sync(true);});
    $changed=array_merge($rule,array('stage'=>'all'));update_option('idlm_audience_sync_rules',array($changed));IDLM_Audience_Dispatch::enqueue(array($id));[$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('uncertain',idlm_aud_state($id,$changed)['status']);
    IDLM_Audience_Dispatch::cancel(array($id));update_option('idlm_audience_sync_rules',array(idlm_aud_rule('meta','different-synthetic-target','all')));IDLM_Audience_Dispatch::enqueue(array($id));[$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_same(1,count($calls));idlm_integration_assert_same(0,$r['pending']);
});
idlm_integration_test('T08-T14-N-INT009 erased legacy queue IDs disappear without retained orphan work',static function():void {
    idlm_aud_clean();$rule=idlm_aud_rule();update_option('idlm_audience_sync_rules',array($rule));update_option(IDLM_Audience_Dispatch::QUEUE,array(999999999),false);[$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same(0,$r['pending']);idlm_integration_assert_same(false,get_option(IDLM_Audience_Dispatch::PREFIX.'999999999'));
});
idlm_integration_test('T08-T14-C-INT009 independent overlapping workers send a manual page once',static function():void {
    idlm_aud_clean();$id=idlm_aud_lead();$rule=idlm_aud_rule();update_option('idlm_audience_sync_rules',array($rule));IDLM_Audience_Dispatch::enqueue(array($id));@unlink(WP_CONTENT_DIR.'/audience-barrier');@unlink(WP_CONTENT_DIR.'/audience-provider.log');$jobs=array();
    for($i=0;$i<2;$i++){ $cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/worker.php').' audience-race';$proc=proc_open($cmd,array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$jobs[]=array($proc,$pipes); }
    foreach($jobs as[$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);idlm_integration_assert_same(0,proc_close($proc),$out.$err);}idlm_integration_assert_same(1,count(file(WP_CONTENT_DIR.'/audience-provider.log',FILE_IGNORE_NEW_LINES)));idlm_integration_assert_same('sent',idlm_aud_state($id,$rule)['status']);
});
idlm_integration_test('T08-T14-C-INT009 interrupted provider acceptance blocks retry even after explicit reselection',static function():void {
    idlm_aud_clean();$id=idlm_aud_lead();$rule=idlm_aud_rule();update_option('idlm_audience_sync_rules',array($rule));IDLM_Audience_Dispatch::enqueue(array($id));@unlink(WP_CONTENT_DIR.'/audience-provider.log');$cmd=escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/worker.php').' audience-crash';$proc=proc_open($cmd,array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);idlm_integration_assert_same(77,proc_close($proc),$out.$err);
    idlm_integration_assert_same('processing',idlm_aud_state($id,$rule)['status']);[$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_same(0,count($calls));idlm_integration_assert_same('uncertain',idlm_aud_state($id,$rule)['status']);IDLM_Audience_Dispatch::enqueue(array($id));[$r,$calls]=idlm_aud_provider(idlm_aud_response(),static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_same(0,count($calls));
});
idlm_integration_test('T08-T14-N-INT009 queue acknowledgement preserves an enqueue during provider dispatch',static function():void {
    idlm_aud_clean();$id=idlm_aud_lead();$later=idlm_aud_lead();$rule=idlm_aud_rule();update_option('idlm_audience_sync_rules',array($rule));IDLM_Audience_Dispatch::enqueue(array($id));
    [$r,$calls]=idlm_aud_provider(static function()use($later){idlm_integration_assert_true(is_array(IDLM_Audience_Dispatch::enqueue(array($later))));return idlm_aud_response();},static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_same(1,count($calls));wp_cache_delete(IDLM_Audience_Dispatch::QUEUE,'options');idlm_integration_assert_same(array($later),get_option(IDLM_Audience_Dispatch::QUEUE));
});

idlm_integration_test('T08-T14-N-INT009 partial page response retains work and recovery clears continuation jobs',static function():void {
    idlm_aud_clean();$id=idlm_aud_lead();$rule=idlm_aud_rule();update_option('idlm_audience_sync_rules',array($rule));IDLM_Audience_Dispatch::enqueue(array($id));[$r,$calls]=idlm_aud_provider(idlm_aud_response(200,'{"num_invalid_entries":1}'),static function(){return IDLM_Audiences::run_batch_sync(true);});idlm_integration_assert_same(1,count($calls));idlm_integration_assert_same('uncertain',idlm_aud_state($id,$rule)['status']);idlm_integration_assert_same(array($id),get_option(IDLM_Audience_Dispatch::QUEUE));
    wp_schedule_single_event(time()+60,IDLM_Audience_Dispatch::HOOK,array(IDLM_Recovery_Guard::generation(),true,gmdate('Y-m-d')));$snapshot=idlm_native_backup_call('snapshot');idlm_integration_assert_same(true,idlm_native_backup_call('restore_payload',$snapshot));idlm_integration_assert_same(array(),_get_cron_array() ? array_filter(_get_cron_array(),static function($hooks){return isset($hooks[IDLM_Audience_Dispatch::HOOK]);}) : array());
    IDLM_Audience_Dispatch::enqueue(array($id));wp_schedule_single_event(time()+60,IDLM_Audience_Dispatch::HOOK,array(IDLM_Recovery_Guard::generation(),true,gmdate('Y-m-d')));idlm_integration_assert_true(is_array(IDLM_Lead_Deletion_Service::reset_all()));idlm_integration_assert_same(array(),array_filter(_get_cron_array(),static function($hooks){return isset($hooks[IDLM_Audience_Dispatch::HOOK]);}));
});

idlm_integration_test('T08-T16-N-INT009 erasure after transport claim cannot recreate work or an orphan acknowledgement',static function():void {
    global $wpdb;idlm_aud_clean();$rule=idlm_aud_rule();$id=idlm_aud_lead();update_option('idlm_audience_sync_rules',array($rule));IDLM_Audience_Dispatch::enqueue(array($id));$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_audience_sync_logs");
    $diagnostics=array();set_error_handler(static function($level,$message)use(&$diagnostics){$diagnostics[]=$message;return true;});try{[$r,$calls]=idlm_aud_provider(static function()use($id){idlm_integration_assert_same(1,IDLM_Lead_Deletion_Service::delete_many(array($id)));return idlm_aud_response();},static function(){return IDLM_Audiences::run_batch_sync(true);});}finally{restore_error_handler();}
    idlm_integration_assert_same(array(),$diagnostics);idlm_integration_assert_same(1,count($calls));idlm_integration_assert_same(0,$r['accepted']);idlm_integration_assert_same(false,get_option(IDLM_Audience_Dispatch::PREFIX.$id));idlm_integration_assert_same($before,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_audience_sync_logs"));
});
