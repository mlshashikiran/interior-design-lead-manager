<?php
/** INT-007 actual credentialed CSV pulls; synthetic contacts only. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }
function idlm_google_http_case($name, callable $callback): void {
    // Each child owns a fresh rate window; retain a separate rate-limit assertion.
    foreach (array((int) floor(time()/60), (int) floor(time()/60)-1) as $window) {
        delete_transient('idlm_rl_'.md5('google-feed|127.0.0.1|'.$window));
    }
    idlm_http_case($name, $callback);
}
function idlm_google_http_rule($enabled='1',$stage='Won'): array {return array('platform'=>'google','audience_id'=>'customers/123/userLists/456','enabled'=>$enabled,'stage'=>$stage,'call_status'=>'Connected','lead_quality'=>'Qualified');}
function idlm_google_http_pull($path='/google-ads-audience/456.csv',$auth='header',$secret='synthetic-http-feed'): array {
    global $config;$error_log=$config['content_dir'].'/php-errors.log';$before_errors=is_file($error_log)?file_get_contents($error_log):'';$out=tempnam($config['content_dir'],'audience-');$headers=$out.'.headers';
    $args=array('curl','--silent','--show-error','--max-time','15','--dump-header',$headers,'--output',$out,'--write-out','%{http_code}');
    if($auth==='header'){$args[]= '--header';$args[]='X-IDLM-API-Key: '.$secret;}elseif($auth==='basic'){$args[]='--user';$args[]='google-source:'.$secret;}
    $args[]=$config['http_url'].'/wp-json/interior-design-lead-manager/v1'.$path;
    $proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$status=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);
    $r=array('status'=>(int)$status,'body'=>file_get_contents($out),'headers'=>file_get_contents($headers));unlink($out);unlink($headers);idlm_http_check($exit===0,'Feed transport failed: '.$err);
    // WP_DEBUG deliberately records rejected credentials. Keep those expected
    // application messages separate; preserve every unexpected PHP diagnostic.
    if ($r['status']===401 && is_file($error_log)) {
        $all=file_get_contents($error_log);$new=substr($all,strlen($before_errors));
        $pattern='/^\[[^\r\n]+\] IDLM GADM Auth Failed: Header present: (?:yes|no), PHP_AUTH_USER present: (?:yes|no), Username matched: (?:yes|no)\r?\n/m';
        preg_match_all($pattern,$new,$expected);
        if ($expected[0]) { file_put_contents($config['content_dir'].'/expected-gadm-auth-denials.log',implode('',$expected[0]),FILE_APPEND);file_put_contents($error_log,$before_errors.preg_replace($pattern,'',$new)); }
    }
    return $r;
}
function idlm_google_http_deny($r,$status):void {idlm_http_check($r['status']===$status,'Expected '.$status.', got '.$r['status']);idlm_http_check(strpos($r['headers'],'text/csv')===false,'Denied feed has CSV headers');idlm_http_check(strpos($r['body'],'int007-')===false,'Denied feed disclosed contacts');}
function idlm_google_http_csv($r):array {
    idlm_http_check($r['status']===200,'CSV request failed');idlm_http_check(stripos($r['headers'],'Content-Type: text/csv')!==false,'CSV content type missing');idlm_http_check(stripos($r['headers'],'no-store')!==false,'Confidential feed allows cache storage');
    idlm_http_check(preg_match('/Content-Length: (\d+)/i',$r['headers'],$m)===1 && (int)$m[1]===strlen($r['body']),'CSV content length wrong');
    $f=fopen('php://temp','w+');fwrite($f,$r['body']);rewind($f);$rows=array();while(($row=fgetcsv($f,0,',','"','\\'))!==false){$rows[]=$row;}fclose($f);idlm_http_check($rows[0]===array('Email','Phone Number','Country Code','Zip','Hashed first name','Hashed last name'),'CSV schema changed');return $rows;
}
update_option('idlm_google_gadm_username','google-source');update_option('idlm_google_gadm_password','synthetic-http-feed');update_option('idlm_audience_sync_rules',array(idlm_google_http_rule()));
$now=current_time('mysql');
foreach(array(array('match','Won','Connected','Qualified',0),array('stage','New','Connected','Qualified',0),array('call','Won','No Answer','Qualified',0),array('quality','Won','Connected','Unqualified',0),array('duplicate','Won','Connected','Qualified',1)) as $v){$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic','last_name'=>'Audience','email'=>'int007-'.$v[0].'@example.test','phone'=>'919999900007','stage'=>$v[1],'call_status'=>$v[2],'lead_quality'=>$v[3],'is_duplicate'=>$v[4],'created_at'=>$now,'updated_at'=>$now));}
idlm_google_http_case('T07-H-INT007-invalid-missing-query-and-rotated-credentials-deny',static function(){
    foreach(array(array('none','synthetic-http-feed'),array('header','wrong'),array('basic','wrong')) as $a){idlm_google_http_deny(idlm_google_http_pull('/google-ads-audience/456.csv',$a[0],$a[1]),401);}
    idlm_google_http_deny(idlm_google_http_pull('/google-ads-audience?list_id=456&api_key=synthetic-http-feed','none'),401);
    update_option('idlm_google_gadm_password','synthetic-rotated-feed');idlm_google_http_deny(idlm_google_http_pull(),401);idlm_google_http_csv(idlm_google_http_pull('/google-ads-audience/456.csv','header','synthetic-rotated-feed'));update_option('idlm_google_gadm_password','synthetic-http-feed');
});
idlm_google_http_case('T08-H-INT007-enabled-header-basic-ID-aliases-preserve-filtered-CSV',static function(){
    foreach(array(array('/google-ads-audience/456.csv','header'),array('/google-ads-audience?list_id=customers%2F123%2FuserLists%2F456','basic')) as $a){$rows=idlm_google_http_csv(idlm_google_http_pull($a[0],$a[1]));idlm_http_check(count($rows)===2 && $rows[1][0]==='int007-match@example.test','Filter/nonduplicate selection changed');idlm_http_check($rows[1][1]==='+919999900007' && $rows[1][2]==='IN' && $rows[1][4]===hash('sha256','synthetic') && $rows[1][5]===hash('sha256','audience'),'Contact mapping changed');}
});
idlm_google_http_case('T07-H-INT007-disable-reenable-and-removal-are-live-on-both-routes',static function(){
    update_option('idlm_audience_sync_rules',array(idlm_google_http_rule('0')));foreach(array('/google-ads-audience/456.csv','/google-ads-audience?list_id=456') as $p){$r=idlm_google_http_pull($p);idlm_google_http_deny($r,404);idlm_http_check(stripos($r['headers'],'no-store')!==false,'Disabled denial can be cached');}
    update_option('idlm_audience_sync_rules',array(idlm_google_http_rule()));idlm_http_check(count(idlm_google_http_csv(idlm_google_http_pull()))===2,'Re-enable failed');update_option('idlm_audience_sync_rules',array());idlm_google_http_deny(idlm_google_http_pull(),404);
});
idlm_google_http_case('T08-H-INT007-disabled-duplicate-cannot-shadow-first-enabled-filters',static function(){
    $first=idlm_google_http_rule();$other=idlm_google_http_rule('1','New');update_option('idlm_audience_sync_rules',array(idlm_google_http_rule('0','New'),$first,$other));$rows=idlm_google_http_csv(idlm_google_http_pull());idlm_http_check(count($rows)===2 && $rows[1][0]==='int007-match@example.test','Disabled or later duplicate selected');update_option('idlm_audience_sync_rules',array(idlm_google_http_rule('0'),$other));$rows=idlm_google_http_csv(idlm_google_http_pull());idlm_http_check(strpos(implode(',',array_column($rows,0)),'int007-stage@example.test')!==false,'Remaining active duplicate not selected');
});
idlm_google_http_case('T07-H-INT007-legacy-only-when-absent-explicit-empty-malformed-and-disabled-deny',static function(){
    update_option('idlm_sync_google_audience','1');update_option('idlm_google_user_list_resource','customers/123/userLists/456');delete_option('idlm_audience_sync_rules');idlm_google_http_csv(idlm_google_http_pull());
    foreach(array(array(),'corrupt',array(array('platform'=>'google','audience_id'=>'456')),array(idlm_google_http_rule('0'))) as $v){update_option('idlm_audience_sync_rules',$v);idlm_google_http_deny(idlm_google_http_pull(),404);}delete_option('idlm_audience_sync_rules');update_option('idlm_sync_google_audience','0');idlm_google_http_deny(idlm_google_http_pull(),404);
});
idlm_google_http_case('T07-H-INT007-malformed-missing-unknown-and-other-platform-list-deny',static function(){
    update_option('idlm_audience_sync_rules',array(idlm_google_http_rule()));foreach(array('/google-ads-audience','/google-ads-audience?list_id%5B%5D=456') as $p){idlm_google_http_deny(idlm_google_http_pull($p),400);}idlm_google_http_deny(idlm_google_http_pull('/google-ads-audience/unknown.csv'),404);$rule=idlm_google_http_rule();$rule['platform']='meta';update_option('idlm_audience_sync_rules',array($rule));idlm_google_http_deny(idlm_google_http_pull(),404);
});

idlm_google_http_case('T07-H-INT007-existing-rate-limit-still-denies-before-CSV',static function(){
    $key='idlm_rl_'.md5('google-feed|127.0.0.1|'.(int)floor(time()/60));set_transient($key,30,120);
    try{idlm_google_http_deny(idlm_google_http_pull(),429);}finally{delete_transient($key);}
});
