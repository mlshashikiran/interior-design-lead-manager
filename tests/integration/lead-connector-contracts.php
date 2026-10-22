<?php
/** Native connector delivery and lifecycle regression cases. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
function idlm_connector_native_setup() {
    static $initialized=false; if (!$initialized) { IDLM_Lead_Connector::init(); IDLM_CAPI::init(); rest_get_server(); do_action('rest_api_init'); $initialized=true; }
    update_option('idlm_meta_lead_connector',array('enabled'=>'1','page_id'=>'111','app_id'=>'444','forms'=>array('222'),'whatsapp_page_id'=>'111','started_at'=>time()-3600));
    foreach(array('meta','google','linkedin') as $platform){update_option('idlm_'.$platform.'_capi_enabled','0');}
    foreach(array('idlm_meta_lead_access_token'=>'synthetic-lead-token','idlm_meta_lead_app_secret'=>'synthetic-app-secret','idlm_meta_lead_verify_token'=>'synthetic-verify','idlm_interakt_webhook_secret'=>'synthetic-inbox-secret') as $key=>$value){idlm_integration_assert_true(IDLM_Secret_Store::set($key,$value));}
    update_option('idlm_interakt_enabled','1');update_option('idlm_interakt_lead_capture','1');update_option('idlm_whatsapp_capi_enabled','0');
}
function idlm_connector_native_key($provider,$id) {return IDLM_Lead_Connector::JOB.hash('sha256',$provider.'|'.$id);}
function idlm_connector_native_record($id,$phone) {return array('id'=>$id,'form_id'=>'222','created_time'=>'2026-10-17T12:00:00+0000','ad_id'=>'555','field_data'=>array(array('name'=>'full_name','values'=>array('Synthetic Connector')),array('name'=>'phone_number','values'=>array($phone))));}
function idlm_connector_native_http($records,callable $callback,$failure=false) {
    $calls=array();$filter=static function($pre,$args,$url)use($records,$failure,&$calls){
        if(strpos($url,'https://graph.facebook.com/')!==0){return $pre;}
        $calls[]=array('url'=>$url,'args'=>$args);
        if(($args['method'] ?? '')==='POST'){return array('response'=>array('code'=>200),'headers'=>array(),'body'=>'{"events_received":1}');}
        if($failure){return new WP_Error('synthetic_failure','Synthetic provider unavailable');}
        foreach($records as $id=>$record){if(strpos($url,'/'.(string)$id.'?')!==false){return array('response'=>array('code'=>200),'headers'=>array(),'body'=>wp_json_encode($record));}}
        return array('response'=>array('code'=>400),'headers'=>array(),'body'=>'{"error":{"message":"unexpected fixture request"}}');
    };add_filter('pre_http_request',$filter,PHP_INT_MAX,3);try{$result=$callback();}finally{remove_filter('pre_http_request',$filter,PHP_INT_MAX);}return array($result,$calls);
}
function idlm_connector_native_enqueue_meta($id) {return IDLM_Lead_Connector::enqueue('meta',$id,array('id'=>$id,'page_id'=>'111','form_id'=>'222'));}
function idlm_connector_native_wa($id,$phone,$click='') {return IDLM_Lead_Connector::interakt_payload(array('channel_phone_number'=>$phone,'traits'=>array('name'=>'Synthetic WhatsApp')),array('id'=>$id,'referral'=>array('ctwa_clid'=>$click,'source_id'=>'555')),'Synthetic incoming message');}
function idlm_connector_native_find($phone) {global $wpdb;return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_leads WHERE phone=%s AND is_duplicate=0 ORDER BY id DESC LIMIT 1",$phone));}
function idlm_connector_native_webhook($id,$phone,$overrides=array()) {
    IDLM_Secret_Store::set('idlm_webhook_api_key','synthetic-inbound-key');
    update_option('idlm_field_mappings_meta',array('phone'=>'phone','utm_source'=>'utm_source','utm_medium'=>'utm_medium','utm_campaign'=>'utm_campaign','utm_adgroup'=>'utm_adgroup','utm_content'=>'utm_content'));
    IDLM_Integrations::register_webhook_routes();
    return idlm_integration_request('POST','/interior-design-lead-manager/v1/webhook',array('X-IDLM-API-Key'=>'synthetic-inbound-key'),array('platform'=>'meta'),array_merge(array('leadgen_id'=>$id,'phone'=>$phone,'utm_source'=>'facebook','utm_medium'=>'paid','utm_campaign'=>'webhook-campaign','utm_adgroup'=>'webhook-adgroup','utm_content'=>'webhook-content'),$overrides));
}

idlm_integration_test('CONNECTOR-N direct and webhook arrival orders preserve attribution and send Meta CAPI',static function():void {
    global $wpdb;idlm_connector_native_setup();
    foreach(array('direct-first','webhook-first') as $index=>$order){
        $id='9000000000000000020'.($index+1);$phone='91981111002'.($index+1);$record=idlm_connector_native_record($id,$phone);$record['campaign_name']='Direct campaign';
        $direct=static function()use($id,$record){idlm_integration_assert_same(true,idlm_connector_native_enqueue_meta($id));[$result]=idlm_connector_native_http(array($id=>$record),static function()use($id){return IDLM_Lead_Connector::process(idlm_connector_native_key('meta',$id));});idlm_integration_assert_same(true,$result);};
        $webhook=static function()use($id,$phone){$response=idlm_connector_native_webhook($id,$phone);idlm_integration_assert_same(200,$response->get_status());};
        if($order==='direct-first'){$direct();$webhook();}else{$webhook();$direct();}
        $lead=idlm_connector_native_find($phone);idlm_integration_assert_true((bool)$lead);idlm_integration_assert_same($id,$lead->fb_lead_id);idlm_integration_assert_same('webhook-campaign',$lead->utm_campaign);idlm_integration_assert_same('webhook-adgroup',$lead->utm_adgroup);idlm_integration_assert_same('Direct campaign',IDLM_Lead_Connector::attribution($lead->id)['meta_lead']['campaign_name']);
        $touchpoint=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE platform='meta' AND external_id=%s",$id));idlm_integration_assert_same('webhook-campaign',$touchpoint->utm_campaign);idlm_integration_assert_same('webhook-content',$touchpoint->utm_content);
        idlm_integration_assert_same(200,idlm_connector_native_webhook($id,$phone,array('utm_campaign'=>'different-campaign'))->get_status());$touchpoint=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE platform='meta' AND external_id=%s",$id));idlm_integration_assert_same('webhook-campaign',$touchpoint->utm_campaign);
        update_option('idlm_meta_capi_enabled','1');update_option('idlm_meta_pixel_id','123456');IDLM_Secret_Store::set('idlm_meta_access_token','synthetic-capi-token');
        [$result,$calls]=idlm_connector_native_http(array(),static function()use($lead){return IDLM_CAPI::fire_meta_capi($lead,'New');});idlm_integration_assert_same(true,$result['success'],$result['message']);idlm_integration_assert_same(1,count($calls));$event=json_decode($calls[0]['args']['body'],true)['data'][0];idlm_integration_assert_same($id,$event['user_data']['lead_id']);idlm_integration_assert_same('system_generated',$event['action_source']);
        update_option('idlm_meta_capi_enabled','0');
    }
});
idlm_integration_test('CONNECTOR-N webhook Meta lead stays eligible for CAPI after WhatsApp attribution',static function():void {
    idlm_connector_native_setup();$phone='919811110023';$event=idlm_connector_native_wa('wa-before-meta',$phone,'click-before-meta');
    idlm_integration_assert_same(true,IDLM_Lead_Connector::enqueue('interakt',$event['id'],$event));idlm_integration_assert_same(true,IDLM_Lead_Connector::process(idlm_connector_native_key('interakt',$event['id'])));
    $response=idlm_connector_native_webhook('9000000000000000023',$phone);idlm_integration_assert_same(200,$response->get_status());$lead=idlm_connector_native_find($phone);
    update_option('idlm_meta_capi_enabled','1');update_option('idlm_meta_pixel_id','123456');IDLM_Secret_Store::set('idlm_meta_access_token','synthetic-capi-token');
    [$result,$calls]=idlm_connector_native_http(array(),static function()use($lead){return IDLM_CAPI::fire_meta_capi($lead,'New');});idlm_integration_assert_same(true,$result['success'],$result['message']);$payload=json_decode($calls[0]['args']['body'],true)['data'][0];idlm_integration_assert_same('9000000000000000023',$payload['user_data']['lead_id']);idlm_integration_assert_same('system_generated',$payload['action_source']);
});

idlm_integration_test('CONNECTOR-N webhook works with direct sync disabled and ignores invalid CAPI click reconstruction',static function():void {
    global $wpdb;idlm_connector_native_setup();$config=IDLM_Lead_Connector::config();$config['enabled']='0';update_option('idlm_meta_lead_connector',$config,false);
    $id='9000000000000000024';$phone='919811110024';$response=idlm_connector_native_webhook($id,$phone);idlm_integration_assert_same(200,$response->get_status());$lead=idlm_connector_native_find($phone);idlm_integration_assert_true((bool)$lead);
    $wpdb->update($wpdb->prefix.'idlm_leads',array('fbclid'=>'synthetic-click-with-unknown-click-time'),array('id'=>$lead->id));
    update_option('idlm_meta_capi_enabled','1');update_option('idlm_meta_pixel_id','123456');IDLM_Secret_Store::set('idlm_meta_access_token','synthetic-capi-token');
    [$result,$calls]=idlm_connector_native_http(array(),static function()use($lead){return IDLM_CAPI::fire_meta_capi($lead,'New');});idlm_integration_assert_same(true,$result['success'],$result['message']);$payload=json_decode($calls[0]['args']['body'],true)['data'][0];idlm_integration_assert_same($id,$payload['user_data']['lead_id']);idlm_integration_assert_true(!isset($payload['user_data']['fbc']));idlm_integration_assert_same(array(hash('sha256',$phone)),$payload['user_data']['ph']);
});
idlm_integration_test('CONNECTOR-N direct and webhook duplicate delivery sends one New CAPI event',static function():void {
    idlm_connector_native_setup();update_option('idlm_meta_capi_enabled','1');update_option('idlm_meta_pixel_id','123456');IDLM_Secret_Store::set('idlm_meta_access_token','synthetic-capi-token');
    foreach(array('direct-first','webhook-first') as $index=>$order){
        $id='900000000000000002'.(6+$index);$phone='91981111002'.(6+$index);$record=idlm_connector_native_record($id,$phone);
        [$result,$calls]=idlm_connector_native_http(array($id=>$record),static function()use($order,$id,$phone){
            $direct=static function()use($id){idlm_integration_assert_same(true,idlm_connector_native_enqueue_meta($id));idlm_integration_assert_same(true,IDLM_Lead_Connector::process(idlm_connector_native_key('meta',$id)));};
            $webhook=static function()use($id,$phone){idlm_integration_assert_same(200,idlm_connector_native_webhook($id,$phone)->get_status());};
            if($order==='direct-first'){$direct();$webhook();}else{$webhook();$direct();}return true;
        });
        $posts=array_values(array_filter($calls,static function($call){return ($call['args']['method'] ?? '')==='POST';}));idlm_integration_assert_same(1,count($posts));$payload=json_decode($posts[0]['args']['body'],true)['data'][0];idlm_integration_assert_same($id,$payload['user_data']['lead_id']);
    }
});
idlm_integration_test('CONNECTOR-N unreadable connector snapshot cannot block webhook Meta CAPI',static function():void {
    idlm_connector_native_setup();$id='9000000000000000028';$phone='919811110028';idlm_integration_assert_same(200,idlm_connector_native_webhook($id,$phone)->get_status());$lead=idlm_connector_native_find($phone);
    update_option(IDLM_Lead_Connector::ATTRIBUTION.$lead->id,'idlm:v1:invalid',false);update_option('idlm_meta_capi_enabled','1');update_option('idlm_meta_pixel_id','123456');IDLM_Secret_Store::set('idlm_meta_access_token','synthetic-capi-token');
    [$result,$calls]=idlm_connector_native_http(array(),static function()use($lead){return IDLM_CAPI::fire_meta_capi($lead,'New');});idlm_integration_assert_same(true,$result['success'],$result['message']);idlm_integration_assert_same($id,json_decode($calls[0]['args']['body'],true)['data'][0]['user_data']['lead_id']);
});
idlm_integration_test('CONNECTOR-N touchpoint backfill failure rolls back webhook changes and allows retry',static function():void {
    global $wpdb;idlm_connector_native_setup();$id='9000000000000000025';$phone='919811110025';idlm_connector_native_enqueue_meta($id);[$result]=idlm_connector_native_http(array($id=>idlm_connector_native_record($id,$phone)),static function()use($id){return IDLM_Lead_Connector::process(idlm_connector_native_key('meta',$id));});idlm_integration_assert_same(true,$result);
    foreach(array('SELECT * FROM idlm_connector_missing_table','SELECT 1 WHERE 1=0') as $replacement){$filter=static function($sql)use($wpdb,$replacement){return strpos($sql,'UPDATE `'.$wpdb->prefix.'idlm_lead_touchpoints`')===0 ? $replacement : $sql;};
        add_filter('query',$filter);try{$response=idlm_connector_native_webhook($id,$phone);}finally{remove_filter('query',$filter);}idlm_integration_assert_same(500,$response->get_status());$lead=idlm_connector_native_find($phone);idlm_integration_assert_same('',$lead->utm_campaign);
    }
    $response=idlm_connector_native_webhook($id,$phone);idlm_integration_assert_same(200,$response->get_status());$lead=idlm_connector_native_find($phone);idlm_integration_assert_same('webhook-campaign',$lead->utm_campaign);
});
idlm_integration_test('CONNECTOR-N webhook submission time is stored on touchpoint not as a Won close',static function():void {
    global $wpdb;
    $previous_timezone = get_option('timezone_string');
    update_option('timezone_string', 'UTC');
    try {
    idlm_connector_native_setup();IDLM_Secret_Store::set('idlm_webhook_api_key','synthetic-inbound-key');update_option('idlm_field_mappings_meta',array('phone'=>'phone','conversion_time'=>'submitted_at'));IDLM_Integrations::register_webhook_routes();
    $id='9000000000000000029';$phone='919811110029';$response=idlm_integration_request('POST','/interior-design-lead-manager/v1/webhook',array('X-IDLM-API-Key'=>'synthetic-inbound-key'),array('platform'=>'meta'),array('leadgen_id'=>$id,'phone'=>$phone,'submitted_at'=>'2026-10-10 10:00:00'));
    idlm_integration_assert_same(200,$response->get_status());$lead=idlm_connector_native_find($phone);idlm_integration_assert_same(null,$lead->conversion_time);$submitted=$wpdb->get_var($wpdb->prepare("SELECT submitted_at FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE platform='meta' AND external_id=%s",$id));idlm_integration_assert_same('2026-10-10 10:00:00',$submitted);
    foreach(array('9000000000000000030'=>array('919811110030','2026-10-10T10:00:00Z','2026-10-10 10:00:00'),'9000000000000000031'=>array('919811110031','not-a-date',null)) as $next_id=>$fixture){$response=idlm_integration_request('POST','/interior-design-lead-manager/v1/webhook',array('X-IDLM-API-Key'=>'synthetic-inbound-key'),array('platform'=>'meta'),array('leadgen_id'=>(string)$next_id,'phone'=>$fixture[0],'submitted_at'=>$fixture[1]));idlm_integration_assert_same(200,$response->get_status());$submitted=$wpdb->get_var($wpdb->prepare("SELECT submitted_at FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE platform='meta' AND external_id=%s",(string)$next_id));idlm_integration_assert_same($fixture[2],$submitted);}
    } finally { update_option('timezone_string', $previous_timezone); }
});
idlm_integration_test('CONNECTOR-N Meta webhook authenticates and queues without provider HTTP',static function():void {
    idlm_connector_native_setup();$body=array('object'=>'page','entry'=>array(array('id'=>'111','changes'=>array(array('field'=>'leadgen','value'=>array('page_id'=>'111','form_id'=>'222','leadgen_id'=>'90001'))))));
    $request=new WP_REST_Request('POST','/idlm/v1/meta-leads');$raw=wp_json_encode($body);$request->set_body($raw);$request->set_header('X-Hub-Signature-256','sha256='.hash_hmac('sha256',$raw,'synthetic-app-secret'));
    [$response,$calls]=idlm_connector_native_http(array(),static function()use($request){return rest_do_request($request);});
    idlm_integration_assert_same(200,$response->get_status());idlm_integration_assert_same(0,count($calls));idlm_integration_assert_true(strpos(get_option(idlm_connector_native_key('meta','90001')),'idlm:v1:')===0);
    $request->set_header('X-Hub-Signature-256','sha256='.str_repeat('0',64));idlm_integration_assert_same(401,IDLM_Lead_Connector::receive($request)->get_status());
    $verify=new WP_REST_Request('GET','/idlm/v1/meta-leads');$verify->set_query_params(array('hub_mode'=>'subscribe','hub_verify_token'=>'synthetic-verify','hub_challenge'=>'0'));$result=rest_do_request($verify);idlm_integration_assert_same(200,$result->get_status());idlm_integration_assert_same('0',$result->get_data());
});
idlm_integration_test('CONNECTOR-N Meta retrieval creates one canonical lead and preserves lead ID across replay',static function():void {
    global $wpdb;idlm_connector_native_setup();$id='99999999999999999999999999';$phone='919811110001';idlm_integration_assert_same(true,idlm_connector_native_enqueue_meta($id));
    $record=idlm_connector_native_record($id,$phone);$record['campaign_id']='99999999999999999999';$record['campaign_name']='Synthetic campaign';$record['adset_name']='Synthetic ad set';$record['platform']='ig';$record['field_data'][]=array('name'=>'custom_private_answer','values'=>array('Synthetic private answer 90210'));
    [$result,$calls]=idlm_connector_native_http(array($id=>$record),static function()use($id){return IDLM_Lead_Connector::process(idlm_connector_native_key('meta',$id));});
    idlm_integration_assert_same(true,$result);idlm_integration_assert_same(1,count($calls));$lead=idlm_connector_native_find($phone);idlm_integration_assert_true((bool)$lead);idlm_integration_assert_same($id,$lead->fb_lead_id);idlm_integration_assert_same('New',$lead->stage);idlm_integration_assert_same('instant_form',IDLM_Lead_Connector::attribution($lead->id)['channel']);
    $attribution=IDLM_Lead_Connector::attribution($lead->id);idlm_integration_assert_same('99999999999999999999',$attribution['meta_lead']['campaign_id']);idlm_integration_assert_same('Synthetic ad set',$attribution['meta_lead']['adset_name']);idlm_integration_assert_same($phone,$attribution['meta_lead']['field_data'][1]['values'][0]);idlm_integration_assert_same('Synthetic private answer 90210',$attribution['meta_lead']['field_data'][2]['values'][0]);
    $stored=IDLM_Secret_Store::stored(IDLM_Lead_Connector::ATTRIBUTION.$lead->id);idlm_integration_assert_true(strpos($stored,$phone)===false);idlm_integration_assert_true(strpos($stored,'Synthetic campaign')===false);
    idlm_integration_assert_same(true,idlm_connector_native_enqueue_meta($id));idlm_integration_assert_same(false,get_option(idlm_connector_native_key('meta',$id)));
    idlm_integration_assert_same(1,(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE platform='meta' AND external_id=%s",$id)));
    $touchpoint=$wpdb->get_var($wpdb->prepare("SELECT payload FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE platform='meta' AND external_id=%s",$id));idlm_integration_assert_true(strpos($touchpoint,'Synthetic private answer 90210')===false);
    idlm_integration_assert_true(strpos($calls[0]['url'],'synthetic-lead-token')===false);idlm_integration_assert_same('Bearer synthetic-lead-token',$calls[0]['args']['headers']['Authorization']);
});
idlm_integration_test('CONNECTOR-N unsupported optional Meta fields fall back to baseline retrieval',static function():void {
    idlm_connector_native_setup();$id='90000000000000000013';$phone='919811110013';$record=idlm_connector_native_record($id,$phone);$calls=array();
    idlm_integration_assert_same(true,idlm_connector_native_enqueue_meta($id));
    $filter=static function($pre,$args,$url)use(&$calls,$record,$id){
        if(strpos($url,'https://graph.facebook.com/')!==0 || strpos($url,'/'.$id.'?')===false){return $pre;}
        $calls[]=$url;parse_str((string)wp_parse_url($url,PHP_URL_QUERY),$query);
        if(($query['fields'] ?? '')!==IDLM_Lead_Connector::META_BASE_FIELDS){return array('response'=>array('code'=>400),'headers'=>array(),'body'=>'{"error":{"message":"Unsupported field"}}');}
        return array('response'=>array('code'=>200),'headers'=>array(),'body'=>wp_json_encode($record));
    };
    add_filter('pre_http_request',$filter,PHP_INT_MAX,3);try{$result=IDLM_Lead_Connector::process(idlm_connector_native_key('meta',$id));}finally{remove_filter('pre_http_request',$filter,PHP_INT_MAX);}
    idlm_integration_assert_same(true,$result);idlm_integration_assert_same(3,count($calls));$lead=idlm_connector_native_find($phone);idlm_integration_assert_true((bool)$lead);idlm_integration_assert_same($record,IDLM_Lead_Connector::attribution($lead->id)['meta_lead']);
});
idlm_integration_test('CONNECTOR-N failed retrieval retries exact event and current credentials without a lead',static function():void {
    idlm_connector_native_setup();$id='90003';$key=idlm_connector_native_key('meta',$id);idlm_connector_native_enqueue_meta($id);
    [$result,$calls]=idlm_connector_native_http(array(),static function()use($key){return IDLM_Lead_Connector::process($key);},true);idlm_integration_assert_same(false,$result);$job=json_decode(IDLM_Secret_Store::get($key,'',true),true);idlm_integration_assert_same(1,$job['attempts']);
    $job['next_at']=time()-1;IDLM_Secret_Store::set($key,wp_json_encode($job));IDLM_Secret_Store::set('idlm_meta_lead_access_token','rotated-lead-token');
    [$result,$calls]=idlm_connector_native_http(array($id=>idlm_connector_native_record($id,'919811110003')),static function()use($key){return IDLM_Lead_Connector::process($key);});idlm_integration_assert_same(true,$result);idlm_integration_assert_same('Bearer rotated-lead-token',$calls[0]['args']['headers']['Authorization']);
});
idlm_integration_test('CONNECTOR-N Interakt creates lead activity and attribution atomically and replays once',static function():void {
    global $wpdb;idlm_connector_native_setup();$event=idlm_connector_native_wa('wa-new','919811110004','opaque-whatsapp-click');$key=idlm_connector_native_key('interakt',$event['id']);idlm_integration_assert_same(true,IDLM_Lead_Connector::enqueue('interakt',$event['id'],$event));
    idlm_integration_assert_same(true,IDLM_Lead_Connector::process($key));$lead=idlm_connector_native_find($event['phone']);idlm_integration_assert_true((bool)$lead);idlm_integration_assert_same('WhatsApp Ads',$lead->source);idlm_integration_assert_same('opaque-whatsapp-click',IDLM_Lead_Connector::attribution($lead->id)['ctwa_clid']);
    idlm_integration_assert_same(1,idlm_wa_native_count($lead->id));idlm_integration_assert_same(true,IDLM_Lead_Connector::enqueue('interakt',$event['id'],$event));idlm_integration_assert_same(1,idlm_wa_native_count($lead->id));
    $stored=IDLM_Secret_Store::stored(IDLM_Lead_Connector::ATTRIBUTION.$lead->id);idlm_integration_assert_true(strpos($stored,'opaque-whatsapp-click')===false);
});
idlm_integration_test('CONNECTOR-N required WhatsApp activity failure rolls back new lead attribution and receipt',static function():void {
    global $wpdb;idlm_connector_native_setup();$event=idlm_connector_native_wa('wa-rollback','919811110005');$key=idlm_connector_native_key('interakt',$event['id']);IDLM_Lead_Connector::enqueue('interakt',$event['id'],$event);
    $filter=static function($sql)use($wpdb){return strpos($sql,'INSERT INTO `'.$wpdb->prefix.'idlm_lead_activity_log`')===0 && strpos($sql,'whatsapp_reply')!==false ? 'SELECT * FROM idlm_connector_missing_table' : $sql;};add_filter('query',$filter);try{$result=IDLM_Lead_Connector::process($key);}finally{remove_filter('query',$filter);}
    idlm_integration_assert_same(false,$result);idlm_integration_assert_same(null,idlm_connector_native_find($event['phone']));idlm_integration_assert_same(false,get_option(idlm_wa_native_receipt($event['id'],'interakt')));
    $job=json_decode(IDLM_Secret_Store::get($key,'',true),true);$job['next_at']=time()-1;IDLM_Secret_Store::set($key,wp_json_encode($job));idlm_integration_assert_same(true,IDLM_Lead_Connector::process($key));
});
idlm_integration_test('CONNECTOR-N ordinary WhatsApp followup preserves existing Instant Form acquisition and owner',static function():void {
    global $wpdb;idlm_connector_native_setup();$lead=idlm_lifecycle_fixture(array('phone'=>'919811110006','source'=>'Meta Ads','stage'=>'Qualified','lead_quality'=>'Qualified'));
    $a=array('channel'=>'instant_form','page_id'=>'111','lead_id'=>'90006');idlm_integration_assert_same(true,IDLM_Lead_Transaction::run(static function()use($lead,$a){return IDLM_Lead_Connector::remember_attribution($lead,$a,true);}));
    $event=idlm_connector_native_wa('wa-followup','919811110006');IDLM_Lead_Connector::enqueue('interakt',$event['id'],$event);idlm_integration_assert_same(true,IDLM_Lead_Connector::process(idlm_connector_native_key('interakt',$event['id'])));
    idlm_integration_assert_same('instant_form',IDLM_Lead_Connector::attribution($lead)['channel']);$row=idlm_connector_native_find($event['phone']);idlm_integration_assert_same($lead,(int)$row->id);idlm_integration_assert_same('Qualified',$row->stage);
});
idlm_integration_test('CONNECTOR-N WhatsApp CAPI requires optin and uses messaging identity after attribution commit',static function():void {
    global $wpdb;idlm_connector_native_setup();update_option('idlm_meta_capi_enabled','1');update_option('idlm_meta_pixel_id','123456');IDLM_Secret_Store::set('idlm_meta_access_token','synthetic-capi-token');
    $event=idlm_connector_native_wa('wa-capi','919811110007','ctwa-synthetic-click');IDLM_Lead_Connector::enqueue('interakt',$event['id'],$event);
    [$result,$calls]=idlm_connector_native_http(array(),static function()use($event){return IDLM_Lead_Connector::process(idlm_connector_native_key('interakt',$event['id']));});idlm_integration_assert_same(true,$result);idlm_integration_assert_same(0,count($calls));
    $lead=idlm_connector_native_find($event['phone']);$wpdb->update($wpdb->prefix.'idlm_leads',array('stage'=>'Won','deal_value'=>100000),array('id'=>$lead->id));update_option('idlm_whatsapp_capi_enabled','1');
    [$result,$calls]=idlm_connector_native_http(array(),static function()use($lead){return IDLM_CAPI::fire_meta_capi($lead,'Won');});idlm_integration_assert_same(true,$result['success'],$result['message']);idlm_integration_assert_same(1,count($calls));$payload=json_decode($calls[0]['args']['body'],true)['data'][0];
    idlm_integration_assert_same('business_messaging',$payload['action_source']);idlm_integration_assert_same('whatsapp',$payload['messaging_channel']);idlm_integration_assert_same(array('ctwa_clid'=>'ctwa-synthetic-click','page_id'=>'111'),$payload['user_data']);idlm_integration_assert_true(!isset($payload['event_source_url']));
});
idlm_integration_test('CONNECTOR-N queued previous-generation input is cancelled without recreating contacts',static function():void {
    idlm_connector_native_setup();$event=idlm_connector_native_wa('wa-old-generation','919811110008');$key=idlm_connector_native_key('interakt',$event['id']);IDLM_Lead_Connector::enqueue('interakt',$event['id'],$event);
    $generation=IDLM_Recovery_Guard::generation();update_option(IDLM_Recovery_Guard::GENERATION,(string)($generation+1),false);
    idlm_integration_assert_same(true,IDLM_Lead_Connector::process($key));idlm_integration_assert_same(null,idlm_connector_native_find($event['phone']));idlm_integration_assert_same(false,get_option($key));
});
idlm_integration_test('CONNECTOR-N deleting an existing contact prevents earlier queued messages from recreating it',static function():void {
    idlm_connector_native_setup();$phone='919811110009';$lead=idlm_lifecycle_fixture(array('phone'=>$phone));$event=idlm_connector_native_wa('wa-pending-erasure',$phone);$key=idlm_connector_native_key('interakt',$event['id']);IDLM_Lead_Connector::enqueue('interakt',$event['id'],$event);
    $deleted=IDLM_Lead_Deletion_Service::delete($lead);idlm_integration_assert_true(!is_wp_error($deleted));idlm_integration_assert_same(true,IDLM_Lead_Connector::process($key));idlm_integration_assert_same(null,idlm_connector_native_find($phone));
});
idlm_integration_test('CONNECTOR-N catchup paginates without following provider next URLs and deduplicates overlap',static function():void {
    idlm_connector_native_setup();delete_option('idlm_connector_scan_222');$config=IDLM_Lead_Connector::config();$config['started_at']=time()-7200;update_option('idlm_meta_lead_connector',$config,false);
    $calls=array();$filter=static function($pre,$args,$url)use(&$calls){if(strpos($url,'graph.facebook.com/v25.0/222/leads')===false){return $pre;}$calls[]=$url;$page=strpos($url,'after=cursor-two')!==false;return array('response'=>array('code'=>200),'headers'=>array(),'body'=>wp_json_encode($page ? array('data'=>array(array('id'=>'90011'))) : array('data'=>array(array('id'=>'90010')),'paging'=>array('next'=>'https://attacker.invalid/token','cursors'=>array('after'=>'cursor-two')))));};
    add_filter('pre_http_request',$filter,PHP_INT_MAX,3);try{$r=new ReflectionMethod('IDLM_Lead_Connector','catch_up');if (PHP_VERSION_ID < 80100) { $r->setAccessible(true); }$r->invoke(null);$r->invoke(null);}finally{remove_filter('pre_http_request',$filter,PHP_INT_MAX);}
    idlm_integration_assert_same(2,count($calls));idlm_integration_assert_true(strpos($calls[1],'after=cursor-two')!==false);idlm_integration_assert_true(strpos($calls[1],'attacker.invalid')===false);idlm_integration_assert_true((bool)get_option(idlm_connector_native_key('meta','90010')));idlm_integration_assert_true((bool)get_option(idlm_connector_native_key('meta','90011')));
});
idlm_integration_test('CONNECTOR-N shared settings render configuration state without stored credentials or queued contacts',static function():void {
    idlm_connector_native_setup();$html=idlm_integration_render(static function(){return IDLM_View::render('admin/settings/lead-connector.php',array('lead_connector'=>IDLM_Lead_Connector::settings_context()));});
    idlm_integration_assert_true(strpos($html,'Direct Meta lead sync')!==false);idlm_integration_assert_true(strpos($html,'synthetic-lead-token')===false);idlm_integration_assert_true(strpos($html,'synthetic-app-secret')===false);idlm_integration_assert_true(strpos($html,'91981111')===false);
});
idlm_integration_test('CONNECTOR-N CRM CAPI preserves large Meta IDs and omits sender browser identity',static function():void {
    idlm_connector_native_setup();update_option('idlm_meta_capi_enabled','1');update_option('idlm_meta_pixel_id','123456');IDLM_Secret_Store::set('idlm_meta_access_token','synthetic-capi-token');
    $id='99999999999999999999999998';$lead=idlm_lifecycle_fixture(array('phone'=>'919811110012','fb_lead_id'=>$id,'stage'=>'Qualified','lead_quality'=>'Qualified'));
    [$result,$calls]=idlm_connector_native_http(array(),static function()use($lead){return IDLM_CAPI::fire_meta_capi((object)array('id'=>$lead),'Qualified');});
    idlm_integration_assert_same(true,$result['success'],$result['message']);$event=json_decode($calls[0]['args']['body'],true)['data'][0];idlm_integration_assert_same($id,$event['user_data']['lead_id']);idlm_integration_assert_same('system_generated',$event['action_source']);idlm_integration_assert_true(!isset($event['user_data']['client_ip_address']));idlm_integration_assert_true(!isset($event['user_data']['client_user_agent']));
});
idlm_integration_test('CONNECTOR-N hosted source survives general settings saves without local Meta credentials',static function():void {
    $previous=IDLM_Lead_Connector::config();
    try {
        $config=array('mode'=>'hosted','enabled'=>'1','page_id'=>'111','app_id'=>'444','forms'=>array('222'),'connection'=>str_repeat('a',32),'page_name'=>'Synthetic business','form_names'=>array('222'=>'Synthetic enquiries'),'started_at'=>time());
        update_option('idlm_meta_lead_connector',$config,false);
        $result=IDLM_Lead_Connector::save_settings(array('idlm_connector_settings'=>'1','idlm_connector_page_id'=>'999','idlm_connector_app_id'=>'999','idlm_connector_form_ids'=>'999'));
        idlm_integration_assert_true(!is_wp_error($result));$saved=IDLM_Lead_Connector::config();
        idlm_integration_assert_same('111',$saved['page_id']);idlm_integration_assert_same(array('222'),$saved['forms']);idlm_integration_assert_same($config['connection'],$saved['connection']);idlm_integration_assert_same('hosted',$saved['mode']);
        $context=IDLM_Lead_Connector::settings_context();$html=IDLM_View::render('admin/settings/lead-connector.php',array('lead_connector'=>$context));
        idlm_integration_assert_true(strpos($html,'Change Page or forms')!==false);idlm_integration_assert_true(strpos($html,'Manual setup')!==false);
        $user=get_current_user_id();wp_set_current_user(0);try{idlm_integration_assert_true(is_wp_error(IDLM_Facebook_Connect::operation('start',array('return_url'=>'https://example.test/'))));}finally{wp_set_current_user($user);}
    } finally { update_option('idlm_meta_lead_connector',$previous,false);IDLM_Lead_Connector::save_settings(array()); }
});
idlm_integration_test('CONNECTOR-N captured leads keep a valid portable backup snapshot',static function():void {
    $backup=idlm_native_backup_call('snapshot');idlm_integration_assert_true(is_array($backup));
    $value_valid=new ReflectionMethod('IDLM_Backup_Validator','value_valid');if (PHP_VERSION_ID < 80100) { $value_valid->setAccessible(true); }
    foreach($backup['schema']['columns'] as $table=>$columns){foreach($backup['tables'][$table] as $row){foreach($columns as $field=>$column){if(!$value_valid->invoke(null,$row[$field],$column)){throw new RuntimeException('Invalid backup value in '.$table.'.'.$field);}}}}
    $valid=IDLM_Backup_Validator::validate($backup,$backup['schema']['columns'],home_url(),$backup['user_identities'],static function($name){return true;});
    idlm_integration_assert_same(true,$valid);
});
