<?php
/** INT-010 real settings HTML and writes. Builder: Shashi. */
if ('cli'!==PHP_SAPI || strpos($config['db_name']??'','idlm_test_')!==0) { exit(1); }
function idlm_secret_http_nonce(): string {
    $r=idlm_http_request('/settings-workspace',array(),'admin',false);idlm_http_check($r['status']===200,'Settings page unavailable');
    preg_match('/name="_wpnonce" value="([^"]+)"/',$r['body'],$m);idlm_http_check(!empty($m[1]),'Settings nonce missing');return $m[1];
}
idlm_http_case('T07-T20-H-INT010 encrypted platform tokens absent from rendered settings',static function():void {
    foreach(IDLM_Secret_Store::platform_options()as$name){idlm_http_check(IDLM_Secret_Store::set($name,'synthetic-HTML-INT010-'.$name),'Secret fixture save failed');}
    $r=idlm_http_request('/settings-workspace',array(),'admin',false);idlm_http_check($r['status']===200,'Settings page failed');idlm_http_check(strpos($r['body'],'synthetic-HTML-INT010')===false,'Platform credential in HTML');
    $dom=new DOMDocument();@$dom->loadHTML($r['body']);$xpath=new DOMXPath($dom);
    foreach(IDLM_Secret_Store::platform_options()as$name){$field=in_array($name,array('idlm_meta_access_token','idlm_google_api_secret','idlm_linkedin_access_token'),true)?substr($name,5):$name;$nodes=$xpath->query('//input[@name="'.$field.'"]');idlm_http_check($nodes->length===1 && $nodes->item(0)->getAttribute('value')==='','Credential is prefilled');idlm_http_check($xpath->query('//input[@name="'.$field.'_clear"]')->length===1,'Explicit clear control missing');}
});
idlm_http_case('T07-H-INT010 settings credential mutation rejects actor and nonce',static function():void {
    $before=IDLM_Secret_Store::stored('idlm_meta_access_token');
    foreach(array(array('owner',idlm_secret_http_nonce()),array('admin','bad-nonce'))as[$actor,$nonce]){$r=idlm_http_request('/settings-workspace',array('idlm_save_settings'=>'1','_wpnonce'=>$nonce,'meta_access_token'=>'synthetic-denied-secret'),$actor);idlm_http_check($r['status']!==200,'Denied credential save succeeded');idlm_http_check($before===IDLM_Secret_Store::stored('idlm_meta_access_token'),'Denial changed token');idlm_http_check(strpos($r['body'],'synthetic-denied-secret')===false,'Denied token echoed');}
});
idlm_http_case('T20-H-INT010 blank retains rotation replaces clear revokes without echo',static function():void {
    $old=IDLM_Secret_Store::get('idlm_meta_access_token','',true);$base=array('idlm_save_settings'=>'1','_wpnonce'=>idlm_secret_http_nonce());
    $r=idlm_http_request('/settings-workspace',$base+array('meta_access_token'=>''),'admin');idlm_http_check($r['status']===200,'Blank settings failed');idlm_http_check($old===IDLM_Secret_Store::get('idlm_meta_access_token','',true),'Blank erased credential');
    $r=idlm_http_request('/settings-workspace',$base+array('meta_access_token'=>'synthetic-ROTATED-INT010'),'admin');idlm_http_check($r['status']===200 && strpos($r['body'],'synthetic-ROTATED-INT010')===false,'Rotation failed or echoed');idlm_http_check(IDLM_Secret_Store::get('idlm_meta_access_token','',true)==='synthetic-ROTATED-INT010','Rotation not durable');
    $r=idlm_http_request('/settings-workspace',$base+array('meta_access_token_clear'=>'1'),'admin');idlm_http_check($r['status']===200,'Clear failed');idlm_http_check(IDLM_Secret_Store::get('idlm_meta_access_token','default',true)==='','Clear not durable');
});
idlm_http_case('T20-H-INT010 contradictory replace clear and array values refuse persistence',static function():void {
    $before=IDLM_Secret_Store::stored('idlm_meta_access_token');$base=array('idlm_save_settings'=>'1','_wpnonce'=>idlm_secret_http_nonce());
    foreach(array(array('meta_access_token'=>'synthetic-conflict','meta_access_token_clear'=>'1'),array('meta_access_token'=>array('synthetic-array')))as$fields){$r=idlm_http_request('/settings-workspace',$base+$fields,'admin');idlm_http_check($r['status']>=400,'Ambiguous credential accepted');idlm_http_check($before===IDLM_Secret_Store::stored('idlm_meta_access_token'),'Invalid credential changed token');idlm_http_check(strpos($r['body'],'synthetic-conflict')===false&&strpos($r['body'],'synthetic-array')===false,'Credential echoed in errors');}
});

idlm_http_case('T07-T08-H-INT010 blank connection tests use stored header token and return scrubbed errors',static function():void {
    global $config,$sessions;
    file_put_contents($config['content_dir'].'/secret-provider.json','{}');
    try{foreach(array('meta','linkedin')as$platform){IDLM_Secret_Store::set('idlm_'.$platform.'_access_token','synthetic-CONNECTION-INT010');$r=idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_test_api_connection','nonce'=>$sessions['admin']['admin'],'platform'=>$platform,'test_type'=>'capi','target_id'=>'synthetic-target','access_token'=>''),'admin');$body=json_decode($r['body'],true);idlm_http_check($r['status']===200 && !$body['success'],'Connection test response failed: '.(string)$r['status'].' '.$r['body']);idlm_http_check(strpos($r['body'],'synthetic-CONNECTION-INT010')===false,'Provider error exposed stored token');$check=json_decode(file_get_contents($config['content_dir'].'/secret-provider-check.json'),true);idlm_http_check($check===array('url_secret'=>false,'header_correct'=>true,'redirects'=>0),'Connection auth contract failed');}}finally{@unlink($config['content_dir'].'/secret-provider.json');@unlink($config['content_dir'].'/secret-provider-check.json');}
});
