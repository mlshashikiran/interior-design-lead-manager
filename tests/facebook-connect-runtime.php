<?php
/** Site-owned Facebook consent and selection with mocked Meta HTTP. Builder: Shashi. */
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require_once dirname(__DIR__).'/includes/class-backup.php';
$GLOBALS['facebook_session']='session-a';$GLOBALS['facebook_requests']=array();$GLOBALS['facebook_fail']='';
function wp_get_session_token():string { return $GLOBALS['facebook_session']; }
function wp_remote_retrieve_body($r):string { return $r['body']; }
function wp_remote_retrieve_response_code($r):int { return $r['response']['code']; }
function wp_remote_get($url,$args) { return facebook_http($url,$args,'GET'); }
function wp_remote_post($url,$args) { return facebook_http($url,$args,'POST'); }
function facebook_http($url,$args,$method) {
    $GLOBALS['facebook_requests'][]=array('url'=>$url,'args'=>$args,'method'=>$method);
    check(strpos($url,'https://graph.facebook.com/v25.0/')===0);check(($args['redirection']??null)===0);
    check(strpos($url,'access_token=')===false && strpos($url,'client_secret=')===false);
    $path=substr(parse_url($url,PHP_URL_PATH),strlen('/v25.0/'));
    if ($GLOBALS['facebook_fail']===$path) { return array('body'=>json_encode(array('error'=>'private-token-secret')), 'response'=>array('code'=>400)); }
    if($path==='oauth/access_token'){$data=array('access_token'=>isset($args['body']['fb_exchange_token'])?'private-long-user-token':'private-user-token');}
    elseif($path==='me/accounts'){$data=array('data'=>array(array('id'=>'111','name'=>'Business Alpha','access_token'=>'private-page-token')));}
    elseif($path==='111/leadgen_forms'){$data=array('data'=>array(array('id'=>'222','name'=>'Kitchen leads','status'=>'ACTIVE'),array('id'=>'223','name'=>'Old form','status'=>'ARCHIVED')));}
    elseif(in_array($path,array('222','224'),true)){$data=array('id'=>$path,'name'=>'Synthetic form '.$path,'page'=>array('id'=>$GLOBALS['facebook_form_page']??'111'),'questions'=>array(array('key'=>'person_name','label'=>'Your name','type'=>'CUSTOM'),array('key'=>'phone_number','label'=>'Phone','type'=>'PHONE'),array('key'=>'email','label'=>'Email','type'=>'EMAIL'),array('key'=>$path==='222'?'your_budget':'project_budget','label'=>'Budget','type'=>'CUSTOM'),array('key'=>'stage','label'=>'Stage answer','type'=>'CUSTOM')));}
    elseif($path==='222/leads'){$data=array('data'=>array());}
    elseif($path==='111/subscribed_apps'){$data=$method==='GET'?array('data'=>array(array('id'=>'444','subscribed_fields'=>array('feed')))):array('success'=>true);if($method==='POST'){check($args['body']['subscribed_fields']==='leadgen,feed');}}
    else { throw new RuntimeException('Unexpected Meta resource: '.$path); }
    return array('body'=>json_encode($data),'response'=>array('code'=>200));
}
$passed=0;$failed=0;
function check($v,string $message='Assertion failed'):void { if(!$v){throw new RuntimeException($message);} }
function test(string $name,callable $fn):void { global $passed,$failed;try{$fn();$passed++;echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: {$e->getMessage()}\n";} }
function ok($v) { check(!is_wp_error($v),is_wp_error($v)?$v->get_error_message():'');return $v; }
function begin_connection():array {
    $result=ok(IDLM_Facebook_Connect::operation('start',array('return_url'=>'https://example.test/workspace/')));
    parse_str(parse_url($result['authorization_url'],PHP_URL_QUERY),$query);return $query;
}
function consent():void { $q=begin_connection();ok(IDLM_Facebook_Direct::exchange(array('state'=>$q['state'],'code'=>'test-code'))); }
function choose():void { ok(IDLM_Facebook_Connect::operation('pages',array()));ok(IDLM_Facebook_Connect::operation('forms',array('page_id'=>'111'))); }
idlm_test_reset_environment();idlm_test_login(1,array('administrator'),array('manage_options'));
update_option('idlm_meta_lead_connector',array('app_id'=>'444','login_config_id'=>'445','enabled'=>'0'));
IDLM_Secret_Store::set('idlm_meta_lead_app_secret','private-app-secret');IDLM_Secret_Store::set('idlm_meta_lead_verify_token','private-verify');
test('direct readiness requires only site-owned app configuration',function(){check(IDLM_Facebook_Connect::ready());check(!defined('IDLM_FACEBOOK_SERVICE_URL'));});
test('operations require administrator permission',function(){idlm_test_login(2,array('subscriber'));check(is_wp_error(IDLM_Facebook_Connect::operation('start',array())));check(is_wp_error(IDLM_Facebook_Direct::exchange(array())));idlm_test_login(1,array('administrator'),array('manage_options'));});
test('consent URL points directly to Meta with local callback and encrypted pending state',function(){$q=begin_connection();check($q['client_id']==='444'&&$q['config_id']==='445');check($q['redirect_uri']===IDLM_Facebook_Direct::callback_url());check(strpos(IDLM_Secret_Store::stored(IDLM_Facebook_Direct::PENDING.'1'),'idlm:v1:')===0);check(IDLM_Facebook_Connect::valid_authorization_url('https://www.facebook.com/v25.0/dialog/oauth?'.http_build_query($q)));});
test('non HTTPS and malformed return URLs fail closed',function(){check(is_wp_error(IDLM_Facebook_Connect::operation('start',array('return_url'=>array()))));check(is_wp_error(IDLM_Facebook_Connect::operation('start',array('return_url'=>'http://example.test/workspace/'))));});
test('state mismatch and login session changes prevent exchange',function(){$q=begin_connection();check(is_wp_error(IDLM_Facebook_Direct::exchange(array('state'=>'wrong','code'=>'x'))));$GLOBALS['facebook_session']='session-b';check(is_wp_error(IDLM_Facebook_Direct::exchange(array('state'=>$q['state'],'code'=>'x'))));$GLOBALS['facebook_session']='session-a';});
test('callback state is single use and tokens stay out of callback response',function(){$q=begin_connection();$r=ok(IDLM_Facebook_Direct::exchange(array('state'=>$q['state'],'code'=>'x')));check($r===array('return_url'=>'https://example.test/workspace/'));check(IDLM_Facebook_Connect::context()['authorized']);check(!IDLM_Facebook_Connect::context()['connected']);$html=IDLM_View::render('admin/settings/lead-connector.php',array('lead_connector'=>IDLM_Lead_Connector::settings_context()));check(preg_match('/data-idlm-facebook-connect-button[^>]*disabled[^>]*>Connected<\/button>/',$html)===1);check(strpos($html,'Reconnect Facebook')!==false);check(is_wp_error(IDLM_Facebook_Direct::exchange(array('state'=>$q['state'],'code'=>'x'))));});
test('denied consent consumes state without contacting Meta',function(){$q=begin_connection();$count=count($GLOBALS['facebook_requests']);check(is_wp_error(IDLM_Facebook_Direct::exchange(array('state'=>$q['state'],'error'=>'access_denied'))));check(count($GLOBALS['facebook_requests'])===$count);check(is_wp_error(IDLM_Facebook_Direct::exchange(array('state'=>$q['state'],'code'=>'x'))));});
test('pages and active forms expose names and IDs only',function(){consent();$p=ok(IDLM_Facebook_Connect::operation('pages',array()));check($p['pages']===array(array('id'=>'111','name'=>'Business Alpha')));$f=ok(IDLM_Facebook_Connect::operation('forms',array('page_id'=>'111')));check($f['forms']===array(array('id'=>'222','name'=>'Kitchen leads')));check(strpos(json_encode(array($p,$f)),'private-')===false);});
test('arbitrary pages and unreturned forms cannot be selected',function(){check(is_wp_error(IDLM_Facebook_Connect::operation('forms',array('page_id'=>'999'))));foreach(array(array('999'),array('222','222'),array(array('222'))) as $forms){check(is_wp_error(IDLM_Facebook_Connect::operation('select',array('page_id'=>'111','forms'=>$forms))));}});
test('failed lead access keeps sync disabled and hides provider details',function(){$GLOBALS['facebook_fail']='222/leads';$r=IDLM_Facebook_Connect::operation('select',array('page_id'=>'111','forms'=>array('222')));check(is_wp_error($r));check(strpos($r->get_error_message(),'private-')===false);check(IDLM_Lead_Connector::config()['enabled']==='0');$GLOBALS['facebook_fail']='';});
test('selection checks lead access, subscribes Page and enables direct importer',function(){$r=ok(IDLM_Facebook_Connect::operation('select',array('page_id'=>'111','forms'=>array('222'))));$c=IDLM_Lead_Connector::config();check($c['mode']==='direct'&&$c['enabled']==='1'&&$c['forms']===array('222'));check(IDLM_Secret_Store::get('idlm_meta_lead_access_token','',true)==='private-page-token');check(strpos(json_encode($r),'private-')===false);check(!IDLM_Facebook_Connect::hosted());check(is_wp_error(IDLM_Facebook_Connect::operation('pages',array())));});
test('general settings save retains direct selection metadata and supports pausing',function(){ok(IDLM_Lead_Connector::save_settings(array('idlm_connector_settings'=>'1','idlm_connector_app_id'=>'444','idlm_connector_login_config_id'=>'445','idlm_connector_page_id'=>'111','idlm_connector_form_ids'=>'222')));check(IDLM_Lead_Connector::config()['mode']==='direct');check(IDLM_Lead_Connector::config()['enabled']==='0');check(IDLM_Facebook_Connect::context()['connected']);});
test('settings render local app setup without credential values',function(){$html=IDLM_View::render('admin/settings/lead-connector.php',array('lead_connector'=>IDLM_Lead_Connector::settings_context()));check(strpos($html,'Facebook Login configuration ID')!==false);check(strpos($html,'shared connection service')===false);check(strpos($html,'private-')===false);});
test('disconnect stops imports and invalidates pending consent',function(){consent();choose();ok(IDLM_Facebook_Connect::operation('disconnect',array()));check(IDLM_Lead_Connector::config()['enabled']==='0');check(!IDLM_Facebook_Connect::context()['connected']);check(!IDLM_Facebook_Connect::context()['authorized']);check(is_wp_error(IDLM_Facebook_Connect::operation('pages',array())));});
test('changing app credentials invalidates an outstanding session',function(){$q=begin_connection();IDLM_Secret_Store::set('idlm_meta_lead_app_secret','replacement');check(is_wp_error(IDLM_Facebook_Direct::exchange(array('state'=>$q['state'],'code'=>'x'))));});
test('expired consent and recovery changes reject callback before provider HTTP',function(){
    foreach(array('expires','generation') as $field){
        $q=begin_connection();$key=IDLM_Facebook_Direct::PENDING.'1';$p=json_decode(IDLM_Secret_Store::get($key,'',true),true);
        $p[$field]=$field==='expires'?time()-1:'obsolete-generation';IDLM_Secret_Store::set($key,json_encode($p));
        $count=count($GLOBALS['facebook_requests']);check(is_wp_error(IDLM_Facebook_Direct::exchange(array('state'=>$q['state'],'code'=>'x'))));check(count($GLOBALS['facebook_requests'])===$count);
    }
});
test('new administrator consent supersedes previous administrator picker',function(){
    consent();choose();idlm_test_login(3,array('administrator'),array('manage_options'));begin_connection();idlm_test_login(1,array('administrator'),array('manage_options'));
    check(is_wp_error(IDLM_Facebook_Connect::operation('select',array('page_id'=>'111','forms'=>array('222')))));
});
test('missing verify token and subscription failure do not activate sync',function(){
    consent();choose();delete_option('idlm_meta_lead_verify_token');$input=array('page_id'=>'111','forms'=>array('222'));
    check(is_wp_error(IDLM_Facebook_Connect::operation('select',$input)));IDLM_Secret_Store::set('idlm_meta_lead_verify_token','verify');
    $GLOBALS['facebook_fail']='111/subscribed_apps';check(is_wp_error(IDLM_Facebook_Connect::operation('select',$input)));$GLOBALS['facebook_fail']='';check(IDLM_Lead_Connector::config()['enabled']==='0');
});
test('token storage failure pauses existing intake before switching credentials',function(){
    $c=IDLM_Lead_Connector::config();$c['enabled']='1';update_option('idlm_meta_lead_connector',$c);
    $GLOBALS['idlm_test_option_write_failures']=array('idlm_meta_lead_access_token');
    try {check(is_wp_error(IDLM_Facebook_Connect::operation('select',array('page_id'=>'111','forms'=>array('222')))));check(IDLM_Lead_Connector::config()['enabled']==='0');}
    finally {$GLOBALS['idlm_test_option_write_failures']=array();}
});
test('pending consent never enters portable backup options',function(){$method=new ReflectionMethod(IDLM_Backup::class,'backup_option');$method->setAccessible(true);check(!$method->invoke(null,IDLM_Facebook_Direct::PENDING.'1'));check(!$method->invoke(null,IDLM_Facebook_Direct::PENDING.'epoch'));});
// Legacy service clients can still stop an existing hosted connection during migration.
define('IDLM_FACEBOOK_SERVICE_URL','https://connect.example.test');define('IDLM_FACEBOOK_SITE_ID',str_repeat('a',32));define('IDLM_FACEBOOK_SITE_KEY',str_repeat('b',64));
function wp_safe_remote_post($url,$args){
    if (!empty($GLOBALS['facebook_service_down'])) { return new WP_Error('offline','Service unavailable'); }
    $raw=json_encode(array('disconnected'=>true));$proof=hash_hmac('sha256',$args['headers']['X-IDLM-Nonce']."\n200\n".$raw,IDLM_FACEBOOK_SITE_KEY);
    return array('body'=>$raw,'response'=>array('code'=>200),'headers'=>array('x-idlm-signature'=>$proof));
}
test('hosted disconnect retains signed transport and unlocks manual app migration',function(){
    update_option('idlm_meta_lead_connector',array('mode'=>'hosted','enabled'=>'1','connection'=>str_repeat('c',32),'app_id'=>'444','page_id'=>'111','forms'=>array('222')));
    ok(IDLM_Facebook_Connect::operation('disconnect',array()));check(IDLM_Lead_Connector::config()['enabled']==='0');check(!IDLM_Facebook_Connect::hosted());
    ok(IDLM_Lead_Connector::save_settings(array('idlm_connector_settings'=>'1','idlm_connector_app_id'=>'888','idlm_connector_login_config_id'=>'889')));check(IDLM_Lead_Connector::config()['app_id']==='888');
});
test('unreachable legacy service does not block local direct migration',function(){
    update_option('idlm_meta_lead_connector',array('mode'=>'hosted','enabled'=>'1','connection'=>str_repeat('c',32),'app_id'=>'444','page_id'=>'111','forms'=>array('222')));
    $GLOBALS['facebook_service_down']=true;
    $r=ok(IDLM_Facebook_Connect::operation('disconnect',array()));check($r['service_disconnected']===false);check(!IDLM_Facebook_Connect::hosted());check(IDLM_Lead_Connector::config()['enabled']==='0');
});

function mapping_setup():void {
    idlm_test_login(1,array('administrator'),array('manage_options'));
    update_option('idlm_meta_lead_connector',array('enabled'=>'0','app_id'=>'444','page_id'=>'111','forms'=>array('222','224')));
}
function mapping_rows($budget='your_budget'):string { return json_encode(array(array('source'=>'person_name','target'=>'full_name'),array('source'=>'phone_number','target'=>'phone'),array('source'=>$budget,'target'=>'budget'))); }
test('form metadata requires administrator and saved form scope before any HTTP',function(){
    mapping_setup();$count=count($GLOBALS['facebook_requests']);
    check(is_wp_error(IDLM_Lead_Connector::form_mapping_operation('load',array('form_id'=>'999'))));
    idlm_test_login(2,array('subscriber'));check(is_wp_error(IDLM_Lead_Connector::form_mapping_operation('load',array('form_id'=>'222'))));
    check(count($GLOBALS['facebook_requests'])===$count);mapping_setup();
});
test('form field lookup returns questions, safe destinations and no contact answers or token',function(){
    $r=ok(IDLM_Lead_Connector::form_mapping_operation('load',array('form_id'=>'222')));
    check($r['fields'][0]===array('key'=>'person_name','label'=>'Your name','type'=>'CUSTOM'));check(isset($r['targets']['phone'])&&!isset($r['targets']['stage']));
    check(strpos(json_encode($r),'private-')===false);check(strpos(json_encode($r),'field_data')===false);
});
test('form ownership mismatch and provider errors preserve mappings',function(){
    $GLOBALS['facebook_form_page']='999';check(is_wp_error(IDLM_Lead_Connector::form_mapping_operation('load',array('form_id'=>'222'))));unset($GLOBALS['facebook_form_page']);
    $GLOBALS['facebook_fail']='222';check(is_wp_error(IDLM_Lead_Connector::form_mapping_operation('load',array('form_id'=>'222'))));$GLOBALS['facebook_fail']='';
});
test('form mapping rejects state destinations, unknown sources, duplicate targets and missing contact',function(){
    $cases=array(array(array('source'=>'stage','target'=>'stage')),array(array('source'=>'unknown','target'=>'phone')),array(array('source'=>'phone_number','target'=>'phone'),array('source'=>'email','target'=>'phone')),array(array('source'=>'person_name','target'=>'full_name')));
    foreach($cases as $rows){check(is_wp_error(IDLM_Lead_Connector::form_mapping_operation('save',array('form_id'=>'222','mappings'=>json_encode($rows)))));}
    check(is_wp_error(IDLM_Lead_Connector::form_mapping_operation('save',array('form_id'=>'222','mappings'=>array()))));
});
test('each form retains its own mapping and custom full-name split without touching lifecycle state',function(){
    ok(IDLM_Lead_Connector::form_mapping_operation('save',array('form_id'=>'222','mappings'=>mapping_rows())));
    ok(IDLM_Lead_Connector::form_mapping_operation('save',array('form_id'=>'224','mappings'=>mapping_rows('project_budget'))));
    $record=array('id'=>'9000','field_data'=>array(array('name'=>'person_name','values'=>array('Synthetic Person')),array('name'=>'phone_number','values'=>array('919800000001')),array('name'=>'your_budget','values'=>array('first budget')),array('name'=>'project_budget','values'=>array('second budget')),array('name'=>'stage','values'=>array('Won'))));
    $a=ok(IDLM_Lead_Connector::map_meta($record,IDLM_Lead_Connector::form_mapping('222')));$b=ok(IDLM_Lead_Connector::map_meta($record,IDLM_Lead_Connector::form_mapping('224')));
    check($a['first_name']==='Synthetic'&&$a['last_name']==='Person');check($a['budget']==='first budget'&&$b['budget']==='second budget');check($a['stage']==='New');check($a['email']==='');
    $r=ok(IDLM_Lead_Connector::form_mapping_operation('load',array('form_id'=>'222')));check($r['mappings']['full_name']==='person_name');
});
test('form mapping survives general settings saves and cannot bleed into another Page',function(){
    ok(IDLM_Lead_Connector::save_settings(array('idlm_connector_settings'=>'1','idlm_connector_app_id'=>'444','idlm_connector_page_id'=>'111','idlm_connector_form_ids'=>'222,224')));
    check(IDLM_Lead_Connector::form_mapping('222')['budget']==='your_budget');
    $c=IDLM_Lead_Connector::config();$c['page_id']='999';update_option('idlm_meta_lead_connector',$c);check(IDLM_Lead_Connector::form_mapping('222')['budget']==='budget');mapping_setup();
});
test('form mapping AJAX refuses invalid nonce without provider access',function(){
    $count=count($GLOBALS['facebook_requests']);$GLOBALS['idlm_test_nonce_valid']=false;$_POST=array('operation'=>'load','form_id'=>'222');
    try {IDLM_Lead_Connector::ajax_form_mapping();throw new RuntimeException('Expected nonce rejection');}catch(IDLM_Test_Response $r){check($r->status===403&&!$r->success);}finally{$GLOBALS['idlm_test_nonce_valid']=true;$_POST=array();}
    check(count($GLOBALS['facebook_requests'])===$count);
});
test('mapping storage failure keeps the previous mapping and reports failure',function(){
    mapping_setup();$option='idlm_meta_form_mapping_'.hash('sha256','444|111|222');$before=IDLM_Lead_Connector::form_mapping('222');
    $GLOBALS['idlm_test_option_write_failures']=array($option);
    $rows=json_encode(array(array('source'=>'email','target'=>'email')));
    try {check(is_wp_error(IDLM_Lead_Connector::form_mapping_operation('save',array('form_id'=>'222','mappings'=>$rows))));check(IDLM_Lead_Connector::form_mapping('222')===$before);}
    finally {$GLOBALS['idlm_test_option_write_failures']=array();}
});
echo "$passed passed, $failed failed\n";exit($failed?1:0);
