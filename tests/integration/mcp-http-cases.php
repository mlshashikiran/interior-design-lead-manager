<?php
/** Actual REST HTTP and production stdio entry against the owned fixture. Shashi. */
require_once dirname(__DIR__) . '/mcp-query-cases.php';
function idlm_mcp_call($sql, $id = 1, $tool = 'run_custom_query') {
    return array('jsonrpc'=>'2.0','id'=>$id,'method'=>'tools/call','params'=>array('name'=>$tool,'arguments'=>$tool==='run_custom_query'?array('sql'=>$sql):array('id'=>(int)$sql)));
}
function idlm_mcp_http($rpc, $key, $method = 'POST', $query = '', $actor = '', $reset_rate = true) {
    global $config;
    if ($reset_rate) {
        // Independently scoped authorization cases, not a production bypass.
        // Native REST Allow-header probes also consume this existing counter.
        $rate_key = 'idlm_rl_' . md5('mcp|127.0.0.1|' . (int) floor(time() / 60));
        foreach (array('alloptions','notoptions','_transient_'.$rate_key,'_transient_timeout_'.$rate_key) as $cache_key) { wp_cache_delete($cache_key,'options'); }
        delete_transient($rate_key);
    }
    $body=tempnam($config['content_dir'],'mcp-request-'); $response=$body.'.response'; $headers=$body.'.headers';
    file_put_contents($body,wp_json_encode($rpc));
    $args=array('curl','--silent','--show-error','--max-time','15','--request',$method,'--header','Content-Type: application/json','--dump-header',$headers,'--output',$response,'--write-out','%{http_code}');
    if($key!==null){ $args[]= '--header'; $args[]='X-IDLM-API-Key: '.$key; }
    if($method==='POST'){ $args[]='--data-binary'; $args[]='@'.$body; }
    if($actor){ $args[]='--cookie'; $args[]=$config['content_dir'].'/'.$actor.'.cookies'; }
    $args[]=$config['http_url'].'/wp-json/interior-design-lead-manager/v1/mcp'.$query;
    $process=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
    $status=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    $result=array('status'=>(int)$status,'body'=>(string)file_get_contents($response),'headers'=>(string)file_get_contents($headers));
    foreach(array($body,$response,$headers) as $file){unlink($file);} idlm_http_check($exit===0,'MCP HTTP transport failed: '.$errors);
    return $result;
}
function idlm_mcp_business_snapshot() {
    global $wpdb,$config;
    $rows=array();
    foreach($wpdb->get_col("SHOW TABLES LIKE 'wp_idlm_%'") as $table){
        $where=$table===$wpdb->prefix.'idlm_telemetry_logs'?" WHERE log_type != 'mcp_access'":'';
        $rows[$table]=$wpdb->get_results("SELECT * FROM `$table`$where",ARRAY_A);
        usort($rows[$table],static function($a,$b){return strcmp(wp_json_encode($a),wp_json_encode($b));});
        idlm_http_check($wpdb->last_error==='','Cannot snapshot MCP fixture');
    }
    $rows['jobs_copies']=$wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name='cron' OR option_name LIKE '%idlm_quote_autosave_%' ORDER BY option_name",ARRAY_A);
    $rows['outbound']=is_file($config['content_dir'].'/outbound-attempts.log')?file_get_contents($config['content_dir'].'/outbound-attempts.log'):'';
    return hash('sha256',wp_json_encode($rows));
}
function idlm_mcp_execution_count() {
    global $config;
    return is_file($config['content_dir'].'/mcp-query-executions.log')?substr_count(file_get_contents($config['content_dir'].'/mcp-query-executions.log'),"custom read executed\n"):0;
}
function idlm_mcp_stdio(array $requests, $between = null) {
    global $config;
    $stub=$config['content_dir'].'/mcp-wp-load.php';
    $bootstrap=__DIR__.'/bootstrap.php';
    $source="<?php\nrequire ".var_export($bootstrap,true).";\n\$config=json_decode(file_get_contents(".var_export($config['content_dir'].'/http-config.json',true)."),true);\nidlm_integration_boot_wordpress(\$config,false);\nidlm_integration_load_plugin(\$config);\nidlm_boot_plugin();\n";
    $source.='add_filter("query",static function($sql) { if(strpos($sql,"SELECT /*+ MAX_EXECUTION_TIME(")===0){file_put_contents(WP_CONTENT_DIR."/mcp-query-executions.log","custom read executed\\n",FILE_APPEND);} return $sql; });';
    $source.='if(rtrim(ABSPATH,"/\\\\")!==rtrim($config["wp_root"],"/\\\\")){throw new RuntimeException("Stdio used an unselected WordPress root");}';
    file_put_contents($stub,$source);
    $env=getenv();$env['IDLM_MCP_WP_LOAD']=$stub;$env['IDLM_MCP_ERROR_LOG']=$config['content_dir'].'/mcp-stdio.log';
    $process=proc_open(array(PHP_BINARY,$config['plugin_root'].'mcp-server.php'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes,null,$env);
    idlm_http_check(is_resource($process),'Cannot start actual stdio entry');
    $out='';stream_set_timeout($pipes[1],15);
    foreach($requests as $index=>$request){
        fwrite($pipes[0],wp_json_encode($request)."\n");fflush($pipes[0]);
        if($between){$line=fgets($pipes[1]);idlm_http_check($line!==false,'Interactive stdio response timed out');$out.=$line;$between($index);}
    } fclose($pipes[0]);
    $out.=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    idlm_http_check($exit===0 && trim($err)==='','Stdio failed or emitted diagnostics');
    $diagnostics=file_get_contents($config['content_dir'].'/mcp-stdio.log');
    idlm_http_check(!preg_match('/PHP (?:Warning|Deprecated|Notice|Fatal error|Parse error)/',$diagnostics),'Stdio logged PHP diagnostics');
    $results=array();foreach(array_filter(explode("\n",trim($out))) as $line){$decoded=json_decode($line,true);idlm_http_check(is_array($decoded),'Non-JSON stdout');$results[]=$decoded;}
    return $results;
}
$mcp_key='MCP-SYNTHETIC-CREDENTIAL';
IDLM_Secret_Store::set('idlm_mcp_api_key',$mcp_key);IDLM_Production_Security::record_mcp_credential($mcp_key);
IDLM_Secret_Store::set('idlm_webhook_api_key','MCP-SYNTHETIC-WEBHOOK');
update_option('idlm_mcp_enabled','1');update_option('idlm_mcp_allow_pii','0');
update_option(IDLM_Production_Security::MCP_GRANTS_OPTION,array('get_leads_summary','get_reps_performance','run_custom_query','query_leads','get_lead_details'));
update_option('idlm_mcp_synthetic_secret','SYNTHETIC-SECRET-OPTION');update_user_meta($users['owner'],'idlm_mcp_synthetic_secret','SYNTHETIC-SECRET-META');
foreach(array('missing'=>null,'wrong'=>'WRONG-SYNTHETIC','webhook'=>'MCP-SYNTHETIC-WEBHOOK') as $name=>$key){
    idlm_http_case('T15-H-credential-'.$name,static function() use($key):void{
        $before=idlm_mcp_business_snapshot();$count=idlm_mcp_execution_count();$r=idlm_mcp_http(idlm_mcp_call('SELECT id FROM wp_idlm_leads'),$key);
        idlm_http_check($r['status']===401 && $count===idlm_mcp_execution_count() && $before===idlm_mcp_business_snapshot(),'Credential denial failed');
        idlm_http_check(strpos($r['body'],'PII-SYNTHETIC')===false,'Credential response disclosed data');
    });
}
idlm_http_case('T15-H-query-string-credential-denied',static function() use($mcp_key):void{idlm_http_check(idlm_mcp_http(array(),null,'GET','?api_key='.$mcp_key)['status']===401,'Query-string credential accepted');});
idlm_http_case('T15-H-initialize-list-and-session-transports',static function() use($mcp_key):void{
    $rpc=array('jsonrpc'=>'2.0','id'=>70,'method'=>'initialize');$r=idlm_mcp_http($rpc,$mcp_key);$data=json_decode($r['body'],true);
    idlm_http_check($r['status']===200 && $data['id']===70 && $data['result']['protocolVersion']==='2024-11-05','Initialize contract changed');
    $r=idlm_mcp_http(array('jsonrpc'=>'2.0','id'=>71,'method'=>'tools/list'),$mcp_key);$data=json_decode($r['body'],true);
    $tools=array_column($data['result']['tools'],'name');idlm_http_check(in_array('run_custom_query',$tools,true) && !in_array('query_leads',$tools,true),'PII list filtering failed');
    idlm_http_check(idlm_mcp_http(array(),$mcp_key,'GET')['status']===200,'GET failed');idlm_http_check(idlm_mcp_http(array(),$mcp_key,'DELETE')['status']===200,'DELETE failed');
});
// Batch dispatch exercises every adversarial call without exhausting credential rate limits.
foreach(array_chunk(idlm_mcp_rejected_queries(),17,true) as $chunk){
    $calls=array();$index=100;foreach($chunk as $sql){$calls[]=idlm_mcp_call($sql,$index++);}
    $before=idlm_mcp_business_snapshot();$executions=idlm_mcp_execution_count();$r=idlm_mcp_http($calls,$mcp_key);$responses=json_decode($r['body'],true);$i=0;
    foreach($chunk as $id=>$sql){$response=$responses[$i++] ?? array();idlm_http_case('T15-H-reject-'.$id,static function() use($r,$response,$before,$executions):void{
        idlm_http_check($r['status']===200 && !empty($response['result']['isError']),'Adversarial query not rejected');
        idlm_http_check($executions===idlm_mcp_execution_count(),'Rejected query reached custom SQL execution');
        idlm_http_check($before===idlm_mcp_business_snapshot(),'Denied MCP request changed business state, jobs or outbound attempts');
        foreach(array('PII-SYNTHETIC','SYNTHETIC-SECRET','example.test') as $secret){idlm_http_check(strpos(wp_json_encode($response),$secret)===false,'Denied response disclosed synthetic data');}
    });}
}
idlm_http_case('T15-H-safe-report-and-key-principal-independent-of-cookie',static function() use($mcp_key):void{
    $sql="SELECT u.ID AS rep_id,u.display_name AS rep_name,COUNT(l.id) AS total FROM wp_idlm_leads l JOIN wp_users u ON u.ID=l.assigned_rep_id GROUP BY u.ID,u.display_name ORDER BY total DESC LIMIT 2";
    foreach(array('owner','other','readall','manager') as $actor){$r=idlm_mcp_http(idlm_mcp_call($sql),$mcp_key,'POST','',$actor);$data=json_decode($r['body'],true);idlm_http_check(empty($data['result']['isError']) && strpos($r['body'],'rep_name')!==false,'Safe report failed for '.$actor);}
});
idlm_http_case('T15-H-PII-grant-enable-revoke-and-tool-grant',static function() use($mcp_key,$leads):void{
    foreach(array(idlm_mcp_call('SELECT email FROM wp_idlm_leads'),idlm_mcp_call($leads[4],1,'get_lead_details'),idlm_mcp_call(0,1,'query_leads')) as $call){$before=idlm_mcp_execution_count();$response=idlm_mcp_http($call,$mcp_key);$data=json_decode($response['body'],true);idlm_http_check(!empty($data['result']['isError']) && $before===idlm_mcp_execution_count(),'Missing PII grant denial differed: '.$response['status'].' '.$response['body']);}
    update_option('idlm_mcp_allow_pii','1');
    foreach(array('SELECT user_pass FROM wp_users','SELECT user_activation_key FROM wp_users','SELECT payload FROM wp_idlm_capi_logs') as $sql){
        $before=idlm_mcp_business_snapshot();$executions=idlm_mcp_execution_count();$data=json_decode(idlm_mcp_http(idlm_mcp_call($sql),$mcp_key)['body'],true);
        idlm_http_check(!empty($data['result']['isError']) && $executions===idlm_mcp_execution_count() && $before===idlm_mcp_business_snapshot(),'PII grant widened authentication/raw log access');
    }
    $response=idlm_mcp_http(idlm_mcp_call('SELECT email FROM wp_idlm_leads WHERE id = '.$leads[4].' LIMIT 1'),$mcp_key);$data=json_decode($response['body'],true);idlm_http_check(empty($data['result']['isError']) && strpos($data['result']['content'][0]['text'],'synthetic4@example.test')!==false,'Explicit PII SQL grant response differed: '.$response['status'].' '.$response['body']);
    $data=json_decode(idlm_mcp_http(idlm_mcp_call($leads[4],1,'get_lead_details'),$mcp_key)['body'],true);idlm_http_check(empty($data['result']['isError']),'Typed PII grant failed');
    update_option(IDLM_Production_Security::MCP_GRANTS_OPTION,array('run_custom_query'));
    $data=json_decode(idlm_mcp_http(idlm_mcp_call($leads[4],1,'get_lead_details'),$mcp_key)['body'],true);idlm_http_check(!empty($data['result']['isError']),'PII grant bypassed tool restriction');
    update_option('idlm_mcp_allow_pii','0');$data=json_decode(idlm_mcp_http(idlm_mcp_call('SELECT email FROM wp_idlm_leads'),$mcp_key)['body'],true);idlm_http_check(!empty($data['result']['isError']),'PII grant revocation failed');
});
idlm_http_case('T15-A-actual-stdio-grammar-and-safe-report',static function():void{
    $requests=array(array('jsonrpc'=>'2.0','id'=>1,'method'=>'initialize'),array('jsonrpc'=>'2.0','method'=>'notifications/initialized'),array('jsonrpc'=>'2.0','id'=>2,'method'=>'tools/list'));
    $i=10;foreach(idlm_mcp_rejected_queries() as $sql){$requests[]=idlm_mcp_call($sql,$i++);}
    $requests[]=idlm_mcp_call('SELECT id FROM wp_idlm_leads ORDER BY id LIMIT 2',900);
    $before=idlm_mcp_business_snapshot();$executions=idlm_mcp_execution_count();$responses=idlm_mcp_stdio($requests);
    idlm_http_check(count($responses)===count($requests)-1,'Stdio response/notification contract changed');
    foreach(array_slice($responses,2,-1) as $response){idlm_http_check(!empty($response['result']['isError']),'Stdio accepted adversarial query');}
    idlm_http_check(empty(end($responses)['result']['isError']) && idlm_mcp_execution_count()===$executions+1,'Stdio safe query/execution count differs');
    idlm_http_check($before===idlm_mcp_business_snapshot(),'Stdio changed business state');
});
idlm_http_case('T15-A-stdio-explicit-PII-grant-and-revocation',static function() use($leads):void{
    update_option(IDLM_Production_Security::MCP_GRANTS_OPTION,array('run_custom_query','get_lead_details','query_leads'));
    $requests=array(idlm_mcp_call('SELECT email FROM wp_idlm_leads LIMIT 1'),idlm_mcp_call($leads[4],2,'get_lead_details'),idlm_mcp_call('SELECT user_pass FROM wp_users',3));
    foreach(array('0','1','0') as $grant){
        update_option('idlm_mcp_allow_pii',$grant);$responses=idlm_mcp_stdio($requests);
        idlm_http_check(count($responses)===3 && !empty($responses[2]['result']['isError']),'Stdio exposed authentication data');
        foreach(array_slice($responses,0,2) as $response){idlm_http_check((bool)$response['result']['isError']===($grant==='0'),'Stdio PII grant/revocation mismatch');}
    }
});
idlm_http_case('T15-A-live-stdio-option-grants-revoke-between-calls',static function() use($leads):void{
    update_option('idlm_mcp_allow_pii','1');update_option(IDLM_Production_Security::MCP_GRANTS_OPTION,array('run_custom_query'));
    $call=idlm_mcp_call('SELECT email FROM wp_idlm_leads WHERE id='.$leads[4]);
    try {
        $responses=idlm_mcp_stdio(array_fill(0,5,$call),static function($index):void{
            if($index===0){update_option('idlm_mcp_allow_pii','0');}
            elseif($index===1){update_option('idlm_mcp_allow_pii','1');}
            elseif($index===2){update_option(IDLM_Production_Security::MCP_GRANTS_OPTION,array());}
            elseif($index===3){update_option('idlm_mcp_enabled','0');}
        });
        idlm_http_check(count($responses)===5,'Live stdio response count differed');
        foreach(array(false,true,false,true) as $index=>$error){idlm_http_check((bool)$responses[$index]['result']['isError']===$error,'Stdio reused stale external grants at call '.$index);}
        idlm_http_check(($responses[4]['error']['code']??0)===-32003,'Live stdio ignored policy disable');
    } finally {update_option('idlm_mcp_enabled','1');update_option('idlm_mcp_allow_pii','0');update_option(IDLM_Production_Security::MCP_GRANTS_OPTION,array('run_custom_query'));}
});
idlm_http_case('T15-H-rotation-and-live-policy-disable',static function() use($mcp_key):void{
    $rotated=IDLM_Integrations::rotate_api_credential('mcp');idlm_http_check(is_string($rotated),'Rotation failed');
    $response=idlm_mcp_http(idlm_mcp_call('SELECT id FROM wp_idlm_leads'),$mcp_key);idlm_http_check($response['status']===401,'Rotation denial differed: '.$response['status'].' '.$response['body']);
    $data=json_decode(idlm_mcp_http(idlm_mcp_call('SELECT id FROM wp_idlm_leads LIMIT 1'),$rotated)['body'],true);idlm_http_check(empty($data['result']['isError']),'Rotated credential failed');
    update_option('idlm_mcp_enabled','0');$before=idlm_mcp_business_snapshot();$executions=idlm_mcp_execution_count();idlm_http_check(idlm_mcp_http(idlm_mcp_call('SELECT id FROM wp_idlm_leads'),$rotated)['status']===503,'HTTP disable failed');
    $responses=idlm_mcp_stdio(array(idlm_mcp_call('SELECT id FROM wp_idlm_leads')));idlm_http_check(($responses[0]['error']['code']??0)===-32003,'Stdio disable failed');
    idlm_http_check($before===idlm_mcp_business_snapshot() && $executions===idlm_mcp_execution_count(),'Disabled policy executed/wrote');update_option('idlm_mcp_enabled','1');
});
idlm_http_case('T15-H-aggregate-audit-does-not-record-SQL-PII-or-credentials',static function() use($mcp_key):void{
    global $wpdb;$logs=implode('\n',$wpdb->get_col("SELECT message FROM {$wpdb->prefix}idlm_telemetry_logs WHERE log_type='mcp_access'"));
    idlm_http_check(strpos($logs,'outcome=error')!==false && strpos($logs,'outcome=success')!==false,'Missing aggregate audit');
    foreach(array('SELECT','SYNTHETIC-SECRET','PII-SYNTHETIC','example.test',$mcp_key) as $value){idlm_http_check(strpos($logs,$value)===false,'Audit disclosed argument/contact/credential');}
});
idlm_http_case('T15-H-actual-HTTP-rate-limit',static function():void{
    $key=IDLM_Secret_Store::get('idlm_mcp_api_key');
    idlm_mcp_http(array(),$key,'GET'); $limited=false;
    for($i=0;$i<61;$i++){
        $response=idlm_mcp_http(array(),$key,'GET','','',false);
        if($response['status']===429){$limited=true;break;}
        idlm_http_check($response['status']===200,'Unexpected response before rate limit');
    }
    idlm_http_check($limited,'Real REST dispatch did not enforce existing rate limit');
});
