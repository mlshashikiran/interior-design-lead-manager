<?php
/** Complete customer client/service flow with mocked Meta and HTTP. Builder: Shashi. */
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require dirname(__DIR__).'/services/facebook-connect/src/Service.php';
use IDLM\Facebook\Service;
$database=sys_get_temp_dir().'/idlm-facebook-client-'.bin2hex(random_bytes(8)).'.sqlite';
$serviceConfig=['app_id'=>'444','app_secret'=>'shared-app-secret-never-on-client','config_id'=>'445','verify_token'=>'verify','base_url'=>'https://connect.example.test','database'=>$database,'encryption_key'=>random_bytes(32)];
$meta=static function($path,$query,$token,$method){
    if($path==='oauth/access_token'){return ['access_token'=>'synthetic-user-token'];}
    if($path==='me'){return ['id'=>'700'];}
    if($path==='me/accounts'){return ['data'=>[['id'=>'111','name'=>'Business Alpha','access_token'=>'synthetic-private-page-token']]];}
    if($path==='111/leadgen_forms'){return ['data'=>[['id'=>'222','name'=>'Kitchen leads','status'=>'ACTIVE']]];}
    if($path==='222/leads'){return ['data'=>[['id'=>'90001']]];}
    if($path==='111/subscribed_apps'){return $method==='POST'?['success'=>true]:['data'=>[['id'=>'444','subscribed_fields'=>['leadgen']]]];}
    if($path==='90001'){return ['id'=>'90001','form_id'=>'222','created_time'=>'2026-10-17T12:00:00Z','field_data'=>[['name'=>'phone_number','values'=>['919811110001']]]];}
    throw new RuntimeException('Unexpected mocked Meta path');
};
$GLOBALS['facebook_service']=new Service($serviceConfig,$meta);
$site=$GLOBALS['facebook_service']->provision('https://example.test');
define('IDLM_FACEBOOK_SERVICE_URL',$site['service_url']);define('IDLM_FACEBOOK_SITE_ID',$site['site_id']);define('IDLM_FACEBOOK_SITE_KEY',$site['key']);
$GLOBALS['facebook_mock_session']='session-a';$GLOBALS['facebook_mock_corrupt']=false;$GLOBALS['facebook_requests']=[];
function wp_get_session_token():string{return $GLOBALS['facebook_mock_session'];}
function wp_safe_remote_post($url,$args){
    $GLOBALS['facebook_requests'][]=['url'=>$url,'args'=>$args];$r=$GLOBALS['facebook_service']->handle('POST','/api',$args['headers'],[],$args['body']);
    if($GLOBALS['facebook_mock_corrupt']){$r['body'].=' ';}
    return ['response'=>['code'=>$r['status']],'body'=>$r['body'],'headers'=>array_change_key_case($r['headers'],CASE_LOWER)];
}
function wp_remote_retrieve_body($response):string{return $response['body'];}
function wp_remote_retrieve_response_code($response):int{return $response['response']['code'];}
$passed=0;$failed=0;
function check($value,string $message='Assertion failed'):void{if(!$value){throw new RuntimeException($message);}}
function test(string $name,callable $test):void{global $passed,$failed;try{$test();$passed++;echo "PASS $name\n";}catch(Throwable $e){$failed++;echo "FAIL $name: {$e->getMessage()}\n";}}
function ok($value){check(!is_wp_error($value),is_wp_error($value)?$value->get_error_message():'');return $value;}
idlm_test_reset_environment();idlm_test_login(1,['administrator'],['manage_options']);
$authorization=[];$pages=[];$selected=[];
test('customer client requires administrator permission',function(){idlm_test_login(2,['subscriber']);check(is_wp_error(IDLM_Facebook_Connect::operation('start',['return_url'=>'https://example.test/workspace/'])));idlm_test_login(1,['administrator'],['manage_options']);});
test('start retains an encrypted session-bound secret and returns only Facebook authorization URL',function()use(&$authorization){$authorization=ok(IDLM_Facebook_Connect::operation('start',['return_url'=>'https://example.test/workspace/?idlm_view=settings']));check(IDLM_Facebook_Connect::valid_authorization_url($authorization['authorization_url']));$stored=IDLM_Secret_Store::stored(IDLM_Facebook_Connect::PENDING.'1');check(str_starts_with($stored,'idlm:v1:'));check(!str_contains(json_encode($authorization),'claim'));check(!str_contains(json_encode($GLOBALS['facebook_requests']),'shared-app-secret'));});
test('mismatched response proof and changed WordPress session fail closed',function(){ $GLOBALS['facebook_mock_corrupt']=true;check(is_wp_error(IDLM_Facebook_Connect::request('status')));$GLOBALS['facebook_mock_corrupt']=false;$GLOBALS['facebook_mock_session']='session-b';check(is_wp_error(IDLM_Facebook_Connect::operation('pages',[])));$GLOBALS['facebook_mock_session']='session-a';});
test('Facebook callback returns to customer settings without sending any token through the browser',function()use(&$authorization){parse_str(parse_url($authorization['authorization_url'],PHP_URL_QUERY),$query);$r=$GLOBALS['facebook_service']->handle('GET','/oauth/callback',[],['state'=>$query['state'],'code'=>'synthetic-code'],'');check($r['status']===303);check(str_starts_with($r['headers']['Location'],'https://example.test/workspace/'));check(!str_contains(json_encode($r),'synthetic-user-token'));});
test('customer selects Page and individual named forms without seeing tokens or claims',function(){ $pages=ok(IDLM_Facebook_Connect::operation('pages',[]));check($pages['pages']===[['id'=>'111','name'=>'Business Alpha']]);$forms=ok(IDLM_Facebook_Connect::operation('forms',['page_id'=>'111']));check($forms['forms']===[['id'=>'222','name'=>'Kitchen leads']]);foreach([$pages,$forms]as$value){check(!str_contains(json_encode($value),'token'));check(!str_contains(json_encode($value),'claim'));}});
test('selected forms automatically enable hosted sync without a shared app secret on the CRM',function()use(&$selected){$selected=ok(IDLM_Facebook_Connect::operation('select',['page_id'=>'111','forms'=>['222']]));$config=IDLM_Lead_Connector::config();check($config['enabled']==='1'&&$config['mode']==='hosted');check($config['forms']===['222']);check($selected['page_name']==='Business Alpha');check(IDLM_Secret_Store::get('idlm_meta_lead_app_secret','',true)==='');check(IDLM_Secret_Store::get('idlm_meta_lead_access_token','',true)==='');check(!isset($selected['connection']));});
test('general settings save preserves the OAuth source and does not require manual Meta credentials',function(){ $config=IDLM_Lead_Connector::config();$r=IDLM_Lead_Connector::save_settings(['idlm_connector_settings'=>'1','idlm_connector_page_id'=>'999','idlm_connector_app_id'=>'999','idlm_connector_form_ids'=>'999']);ok($r);$saved=IDLM_Lead_Connector::config();check($saved['page_id']===$config['page_id']);check($saved['forms']===$config['forms']);check($saved['connection']===$config['connection']);check($saved['mode']==='hosted'&&$saved['enabled']==='1');});
test('signed notifications are durably queued locally before broker acknowledgment',function()use($serviceConfig){$body=json_encode(['object'=>'page','entry'=>[['id'=>'111','changes'=>[['field'=>'leadgen','value'=>['page_id'=>'111','form_id'=>'222','leadgen_id'=>'90001']]]]]]);$r=$GLOBALS['facebook_service']->handle('POST','/meta/webhook',['X-Hub-Signature-256'=>'sha256='.hash_hmac('sha256',$body,$serviceConfig['app_secret'])],[],$body);check($r['status']===200);IDLM_Facebook_Connect::pull();check(IDLM_Secret_Store::stored(IDLM_Lead_Connector::JOB.hash('sha256','meta|90001'))!=='');$config=IDLM_Lead_Connector::config();$events=ok(IDLM_Facebook_Connect::request('events',['connection'=>$config['connection']]));check($events['events']===[]);});
test('existing lead importer reads only the selected forms through the authenticated service',function(){$record=ok(IDLM_Lead_Connector::graph('90001',['fields'=>'id,created_time,form_id,ad_id,field_data']));check($record['id']==='90001');check(is_wp_error(IDLM_Lead_Connector::graph('me/accounts',[])));});
test('settings view presents Facebook connection and form selection before manual configuration',function(){ $context=IDLM_Lead_Connector::settings_context();$html=IDLM_View::render('admin/settings/lead-connector.php',['lead_connector'=>$context]);$connect=strpos($html,'Connect with Facebook');$manual=strpos($html,'Manual setup');check($connect!==false&&$manual!==false&&$connect<$manual);check(str_contains($html,'Select individual lead forms'));check(!str_contains($html,IDLM_FACEBOOK_SITE_KEY));check(!str_contains($html,'synthetic-private-page-token'));});
test('disconnect stops local sync and removes the service connection',function(){ok(IDLM_Facebook_Connect::operation('disconnect',[]));check(IDLM_Lead_Connector::config()['enabled']==='0');check(!IDLM_Facebook_Connect::context()['connected']);check(is_wp_error(IDLM_Facebook_Connect::request('status')));});
foreach(['https://evil.test/v25.0/dialog/oauth','http://www.facebook.com/v25.0/dialog/oauth','https://www.facebook.com@evil.test/v25.0/dialog/oauth','https://connect.example.test?access_token=secret']as$url){test('URL validation rejects '.$url,function()use($url){check(!IDLM_Facebook_Connect::valid_authorization_url($url));});}
unset($GLOBALS['facebook_service']);foreach([$database,$database.'-wal',$database.'-shm']as$file){if(is_file($file)){unlink($file);}}
echo "$passed passed, $failed failed\n";exit($failed?1:0);
