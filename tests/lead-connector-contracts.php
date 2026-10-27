<?php
/** Connector parsing, authorization and encrypted intake. Builder: Shashi. */
idlm_test('CONNECTOR Meta answers preserve identifiers and custom mappings without injecting CRM state',static function():void {
    $id='99999999999999999999999999';
    $record=array('id'=>$id,'field_data'=>array(array('name'=>'full_name','values'=>array('Asha Rao')),array('name'=>'email','values'=>array('asha@example.test')),array('name'=>'phone_number','values'=>array('+919876543210')),array('name'=>'your_budget','values'=>array('8 lakh')),array('name'=>'stage','values'=>array('Won'))));
    $data=IDLM_Lead_Connector::map_meta($record,array('budget'=>'your_budget'));
    assert_same($id,$data['fb_lead_id']);assert_same('Asha',$data['first_name']);assert_same('Rao',$data['last_name']);assert_same('8 lakh',$data['budget']);assert_same('New',$data['stage']);
    $record['field_data'][0]['values']=array(array('invalid'));
    assert_true(is_wp_error(IDLM_Lead_Connector::map_meta($record)));
    assert_true(is_wp_error(IDLM_Lead_Connector::map_meta(array('id'=>'123','field_data'=>array()))));
});
idlm_test('CONNECTOR Meta snapshot preserves requested provider data and excludes unrequested keys',static function():void {
    $record=array('id'=>'99999999999999999999','form_id'=>'222','campaign_id'=>'12345678901234567890','campaign_name'=>'Kitchen','adset_name'=>'North','platform'=>'ig','is_organic'=>false,'field_data'=>array(array('name'=>'custom_question','values'=>array('private answer'))),'unexpected'=>'do not retain');
    $snapshot=IDLM_Lead_Connector::meta_lead_snapshot($record);
    assert_same('12345678901234567890',$snapshot['campaign_id']);assert_same('private answer',$snapshot['field_data'][0]['values'][0]);assert_same(false,$snapshot['is_organic']);assert_false(isset($snapshot['unexpected']));
    assert_true(is_wp_error(IDLM_Lead_Connector::meta_lead_snapshot(array('field_data'=>array(str_repeat('x',524289))))));
});
idlm_test('CONNECTOR Meta verification rejects malformed shapes and returns raw numeric challenge',static function():void {
    update_option('idlm_meta_lead_connector',array('enabled'=>'1'));IDLM_Secret_Store::set('idlm_meta_lead_verify_token','synthetic-token');
    $params=array('hub_mode'=>'subscribe','hub_verify_token'=>'synthetic-token','hub_challenge'=>'0');
    $r=new WP_REST_Request(array(),$params,array(),array(),'','/idlm/v1/meta-leads');
    assert_true(IDLM_Lead_Connector::permission_verify($r));assert_same('0',IDLM_Lead_Connector::verify($r)->get_data());
    ob_start();$served=IDLM_Lead_Connector::serve_challenge(false,IDLM_Lead_Connector::verify($r),$r,null);$body=ob_get_clean();assert_same(true,$served);assert_same('0',$body);
    $params['hub_challenge']='00012345678901234567890';$r=new WP_REST_Request(array(),$params,array(),array(),'','/idlm/v1/meta-leads');
    ob_start();$served=IDLM_Lead_Connector::serve_challenge(false,IDLM_Lead_Connector::verify($r),$r,null);$body=ob_get_clean();assert_same(true,$served);assert_same($params['hub_challenge'],$body);
    assert_true(IDLM_Lead_Connector::permission_verify(new WP_REST_Request(array(),array('hub.mode'=>'subscribe','hub.verify_token'=>'synthetic-token','hub.challenge'=>'123'))));
    foreach(array('hub_mode','hub_verify_token','hub_challenge') as $key){$bad=$params;$bad[$key]=array('x');assert_false(IDLM_Lead_Connector::permission_verify(new WP_REST_Request(array(),$bad)));}
});
idlm_test('CONNECTOR signed Meta notifications only retain configured Page and forms',static function():void {
    update_option('idlm_meta_lead_connector',array('enabled'=>'1','page_id'=>'111','forms'=>array('222')));IDLM_Secret_Store::set('idlm_meta_lead_app_secret','synthetic-secret');
    $payload=array('object'=>'page','entry'=>array(array('id'=>'111','changes'=>array(array('field'=>'leadgen','value'=>array('page_id'=>'111','form_id'=>'222','leadgen_id'=>'333'))))));
    $body=wp_json_encode($payload);$r=new WP_REST_Request(array('X-Hub-Signature-256'=>'sha256='.hash_hmac('sha256',$body,'synthetic-secret')),array(),array(),array(),$body);
    assert_true((bool)IDLM_Lead_Connector::permission_post($r));assert_same(200,IDLM_Lead_Connector::receive($r)->get_status());
    $key=IDLM_Lead_Connector::JOB.hash('sha256','meta|333');assert_true(strpos(get_option($key),'idlm:v1:')===0);assert_same(true,IDLM_Lead_Connector::enqueue('meta','333',array('id'=>'333','page_id'=>'111','form_id'=>'222')));
    assert_same(401,IDLM_Lead_Connector::receive(new WP_REST_Request(array(),array(),array(),array(),$body))->get_status());
    assert_true(is_wp_error(IDLM_Lead_Connector::enqueue('meta','333',array('id'=>'333','page_id'=>'different'))));
});
idlm_test('CONNECTOR Interakt extracts explicit referrals without treating callback data as ad identity',static function():void {
    $customer=array('channel_phone_number'=>'919876543210','traits'=>array('name'=>'Asha Rao','ctwa_clid'=>'untrusted-trait'));
    $message=array('id'=>'interakt-synthetic','meta_data'=>array('source_data'=>array('callback_data'=>'untrusted')));
    $data=IDLM_Lead_Connector::interakt_payload($customer,$message,'Hello');assert_same('',$data['referral']['ctwa_clid']);
    $message['meta_data']['referral']=array('ctwa_clid'=>'opaque-click','source_id'=>'555');$data=IDLM_Lead_Connector::interakt_payload($customer,$message,'Hello');assert_same('opaque-click',$data['referral']['ctwa_clid']);
    assert_same(true,IDLM_Lead_Connector::enqueue('interakt',$data['id'],$data));$raw=get_option(IDLM_Lead_Connector::JOB.hash('sha256','interakt|'.$data['id']));assert_false(strpos($raw,'919876543210')!==false);assert_false(strpos($raw,'Hello')!==false);
    $message['referral']=array('ctwa_clid'=>array('bad'));assert_false(IDLM_Lead_Connector::interakt_payload($customer,$message,'Hello'));
});
idlm_test('CONNECTOR disabled and expired jobs retain minimal replay evidence without importing contacts',static function():void {
    $data=array('id'=>'cancelled-event','phone'=>'919876543210','text'=>'Hello','name'=>'Asha','referral'=>array('ctwa_clid'=>'','source_id'=>'','source_url'=>''));
    assert_same(true,IDLM_Lead_Connector::enqueue('interakt',$data['id'],$data));$hash=hash('sha256','interakt|'.$data['id']);
    assert_same(true,IDLM_Lead_Connector::process(IDLM_Lead_Connector::JOB.$hash));$receipt=get_option(IDLM_Lead_Connector::RECEIPT.$hash);assert_same('cancelled',$receipt['outcome']);assert_false(get_option(IDLM_Lead_Connector::JOB.$hash));assert_same(true,IDLM_Lead_Connector::enqueue('interakt',$data['id'],$data));
    assert_false(strpos(wp_json_encode($receipt),'919876543210')!==false);
});
idlm_test('CONNECTOR incomplete enabled setup keeps settings form and submitted values without exposing saved credentials',static function():void {
    idlm_test_login(1,array('administrator'),array('manage_options'));
    $saved=array('enabled'=>'0','page_id'=>'111','app_id'=>'444','forms'=>array('222'));
    update_option('idlm_meta_lead_connector',$saved);
    update_option('idlm_monthly_lead_target',77);
    $_SERVER['REQUEST_METHOD']='POST';
    $_POST=array('idlm_save_settings'=>'1','active_tab'=>'webhooks','idlm_connector_settings'=>'1','idlm_meta_lead_sync'=>'1','idlm_connector_page_id'=>'555','idlm_connector_app_id'=>'666','idlm_connector_form_ids'=>'777, 888','idlm_connector_map_budget'=>'your_budget','idlm_interakt_lead_capture'=>'1','idlm_meta_lead_access_token'=>'synthetic-new-secret');
    try {
        assert_same(false,IDLM_Admin_Settings_Controller::process_request());
        assert_same($saved,IDLM_Lead_Connector::config());
        assert_same(77,get_option('idlm_monthly_lead_target'));
        assert_same('synthetic-new-secret',IDLM_Secret_Store::get('idlm_meta_lead_access_token','',true));
        $context=IDLM_Lead_Connector::settings_context();
        assert_contains('Meta app secret',$context['error']);assert_contains('webhook verify token',$context['error']);
        assert_false(strpos($context['error'],'Missing: Facebook Page ID')!==false);
        assert_same('555',$context['config']['page_id']);assert_same(array('777','888'),$context['config']['forms']);assert_same('your_budget',$context['config']['mappings']['budget']);assert_same('1',$context['interakt_capture']);
        $html=IDLM_View::render('admin/settings/lead-connector.php',array('lead_connector'=>$context));
        assert_contains('role="alert"',$html);assert_contains('Connector setup was not saved.',$html);assert_contains('value="555"',$html);assert_contains('Check saved Meta lead connection',$html);assert_contains('WhatsApp leads through Interakt',$html);assert_false(strpos($html,'synthetic-new-secret')!==false);
        // Unchecking enablement saves the same unfinished setup without requiring credentials.
        unset($_POST['idlm_meta_lead_sync']);
        $result=IDLM_Lead_Connector::save_settings($_POST);
        assert_false(is_wp_error($result));assert_same('0',IDLM_Lead_Connector::config()['enabled']);assert_same('555',IDLM_Lead_Connector::config()['page_id']);assert_same('',IDLM_Lead_Connector::settings_context()['error']);
    } finally { IDLM_Lead_Connector::save_settings(array()); }
});
idlm_test('CONNECTOR invalid identifier and mapping errors preserve settings instead of terminating the page',static function():void {
    $saved=array('enabled'=>'0','page_id'=>'111');update_option('idlm_meta_lead_connector',$saved);
    foreach(array(array('idlm_connector_page_id'=>'bad-page'),array('idlm_connector_form_ids'=>'222, invalid'),array('idlm_connector_map_phone'=>array('invalid')),array('idlm_whatsapp_event_won'=>'bad<script>')) as $invalid) {
        try {
            $result=IDLM_Lead_Connector::save_settings(array_merge(array('idlm_connector_settings'=>'1'),$invalid));
            assert_true(is_wp_error($result));assert_same($saved,IDLM_Lead_Connector::config());assert_true(IDLM_Lead_Connector::settings_context()['error']!=='');
        } finally { IDLM_Lead_Connector::save_settings(array()); }
    }
});

idlm_test('FACEBOOK customer UI refuses unsafe service and authorization URLs',static function():void {
    foreach (array('http://service.example.test','https://user@service.example.test','https://service.example.test?secret=value','https://service.example.test/#fragment') as $url) { assert_false(IDLM_Facebook_Connect::valid_service_url($url)); }
    assert_true(IDLM_Facebook_Connect::valid_service_url('https://connect.example.test'));
    assert_false(IDLM_Facebook_Connect::ready());
    assert_false(IDLM_Facebook_Connect::valid_authorization_url('https://attacker.example/v25.0/dialog/oauth'));
});

idlm_test('FACEBOOK unprovisioned settings explain setup instead of silently disabling Connect',static function():void {
    idlm_test_reset_environment();
    idlm_test_login(1,array('administrator'),array('manage_options'));
    $html=IDLM_View::render('admin/settings/lead-connector.php',array('lead_connector'=>IDLM_Lead_Connector::settings_context()));
    assert_contains('data-idlm-facebook-ready="0"',$html);
    assert_contains('Facebook sign-in is not available yet.',$html);
    assert_contains('public HTTPS',$html);
    assert_same(1,preg_match('/<button[^>]*data-idlm-facebook-start[^>]*>/',$html,$button));
    assert_false(strpos($button[0],'disabled')!==false);
});

idlm_test('CONNECTOR manual credentials persist securely across saves and render truthful saved state',static function():void {
    idlm_test_reset_environment();idlm_test_login(1,array('administrator'),array('manage_options'));
    $_SERVER['REQUEST_METHOD']='POST';
    $_POST=array('idlm_save_settings'=>'1','idlm_connector_settings'=>'1','idlm_connector_page_id'=>'111','idlm_connector_app_id'=>'444','idlm_connector_login_config_id'=>'445','idlm_connector_form_ids'=>'222','idlm_meta_lead_access_token'=>'synthetic-token','idlm_meta_lead_app_secret'=>'synthetic-secret','white_label'=>IDLM_Settings::white_label_defaults());
    try {
        assert_same(true,IDLM_Admin_Settings_Controller::process_request());
        foreach(array('access_token'=>'synthetic-token','app_secret'=>'synthetic-secret') as $key=>$value){assert_same($value,IDLM_Secret_Store::get('idlm_meta_lead_'.$key,'',true));assert_true(strpos(IDLM_Secret_Store::stored('idlm_meta_lead_'.$key),'idlm:v1:')===0);}
        $_POST['idlm_meta_lead_access_token']='';$_POST['idlm_meta_lead_app_secret']='';
        assert_same(true,IDLM_Admin_Settings_Controller::process_request());
        $context=IDLM_Lead_Connector::settings_context();assert_true($context['credentials']['access_token']);assert_true($context['credentials']['app_secret']);
        $html=IDLM_View::render('admin/settings/lead-connector.php',array('lead_connector'=>$context));
        assert_contains('data-idlm-secret-configured="1"',$html);assert_contains('Your saved value is hidden for security. No need to enter it again.',$html);assert_contains('data-idlm-secret-replace',$html);assert_contains('data-idlm-secret-summary ',$html);
        foreach(array(1,2,3,4)as$step){assert_contains('data-idlm-manual-step="'.$step.'"',$html);}
        assert_contains('data-idlm-manual-step="2" open',$html);assert_contains('Save details and continue',$html);assert_contains('data-idlm-wizard-go="4"',$html);
        assert_false(strpos($html,'synthetic-token')!==false);assert_false(strpos($html,'synthetic-secret')!==false);
        $_POST['idlm_meta_lead_access_token_clear']='1';assert_same(true,IDLM_Admin_Settings_Controller::process_request());
        assert_same('',IDLM_Secret_Store::get('idlm_meta_lead_access_token','',true));assert_same('synthetic-secret',IDLM_Secret_Store::get('idlm_meta_lead_app_secret','',true));
    } finally { $_POST=array(); IDLM_Lead_Connector::save_settings(array()); }
});
idlm_test('CONNECTOR manual webhook can be verified before enabling lead intake',static function():void {
    idlm_test_reset_environment();update_option('idlm_meta_lead_connector',array('enabled'=>'0','page_id'=>'111','forms'=>array('222')));
    IDLM_Secret_Store::set('idlm_meta_lead_verify_token','synthetic-verify');IDLM_Secret_Store::set('idlm_meta_lead_app_secret','synthetic-secret');
    $params=array('hub_mode'=>'subscribe','hub_verify_token'=>'synthetic-verify','hub_challenge'=>'123');
    assert_true(IDLM_Lead_Connector::permission_verify(new WP_REST_Request(array(),$params)));
    $params['hub_verify_token']='wrong';assert_false(IDLM_Lead_Connector::permission_verify(new WP_REST_Request(array(),$params)));
    assert_false(IDLM_Lead_Connector::permission_post(new WP_REST_Request()));
    update_option('idlm_meta_lead_connector',array('mode'=>'hosted','enabled'=>'0','page_id'=>'111'));$params['hub_verify_token']='synthetic-verify';assert_false(IDLM_Lead_Connector::permission_verify(new WP_REST_Request(array(),$params)));
});

idlm_test('CONNECTOR wizard distinguishes saved, absent and server-managed credentials without echoing secrets',static function():void {
    idlm_test_reset_environment();
    $empty=IDLM_View::render('admin/settings/connector-secret.php',array('key'=>'app_secret','label'=>'Meta app secret','saved'=>false,'managed'=>false));
    assert_contains('Not configured. Enter a value, then save details.',$empty);
    assert_contains('data-idlm-secret-summary hidden',$empty);
    assert_false(strpos($empty,'data-idlm-secret-replace')!==false);
    $managed=IDLM_View::render('admin/settings/connector-secret.php',array('key'=>'app_secret','label'=>'Meta app secret','saved'=>true,'managed'=>true));
    assert_contains('Managed in server configuration.',$managed);
    assert_contains('data-idlm-secret-editor hidden',$managed);
    assert_false(strpos($managed,'data-idlm-secret-replace')!==false);
    assert_false(strpos($managed,'data-idlm-secret-clear')!==false);
});
