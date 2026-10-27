<?php
/** INT-005 manual retry regression contracts. Builder: Shashi. */
require_once dirname(__DIR__).'/includes/class-capi.php';
function idlm_capi_fast_fixture($platform='google',$stage='Qualified',$event='CRM Qualified Lead') {
    $GLOBALS['idlm_test_http_calls']=array();$GLOBALS['idlm_test_http_code']=200;
    $GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('stage'=>$stage,'lead_quality'=>'Qualified','source'=>'Google Ads','gclid'=>'synthetic-click','li_fat_id'=>'synthetic-linkedin','num_rooms'=>2))));
    update_option('idlm_'.$platform.'_capi_enabled','1');
    foreach(array('idlm_meta_pixel_id'=>'synthetic-pixel','idlm_meta_access_token'=>'synthetic-meta','idlm_google_measurement_id'=>'G-SYNTHETIC','idlm_google_api_secret'=>'synthetic-google','idlm_linkedin_conversion_id'=>'synthetic-conversion','idlm_linkedin_access_token'=>'synthetic-linkedin') as $key=>$value){update_option($key,$value);}
    return IDLM_DB::log_capi_event(1,$platform,$event,array(),'synthetic rejected request','failed');
}
idlm_test('INT005 current Google defaults retry the original stage and report a new exact attempt',static function():void {
    foreach(array('Qualified'=>'CRM Qualified Lead','Won'=>'Closed Won Interior Project') as $stage=>$event){
        idlm_test_reset_environment();$id=idlm_capi_fast_fixture('google',$stage,$event);$result=IDLM_CAPI::retry_capi_log($id);
        assert_true($result['success']);assert_true($result['log_id']>$id);assert_true($result['attempt_id']!=='');assert_same(1,count($GLOBALS['idlm_test_http_calls']));
        $payload=json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'],true);assert_same($stage,$payload['events'][0]['params']['stage']);assert_same($stage==='Won'?'Closed_Won_Interior_Project':'CRM_Qualified_Lead',$payload['events'][0]['name']);assert_false(isset($payload['_idlm_delivery']));
    }
});
idlm_test('INT005 unknown ambiguous success and changed mappings cannot be guessed into a retry',static function():void {
    $id=idlm_capi_fast_fixture();update_option('idlm_google_event_mappings',array('Qualified'=>'same','Won'=>'same'));
    assert_false(IDLM_CAPI::retry_capi_log($id)['success']);
    $ambiguous=IDLM_DB::log_capi_event(1,'google','same',array(),'synthetic failed','failed');assert_false(IDLM_CAPI::retry_capi_log($ambiguous)['success']);
    $success=IDLM_DB::log_capi_event(1,'google','same',array(),'synthetic success','success');assert_false(IDLM_CAPI::retry_capi_log($success)['success']);assert_same(0,count($GLOBALS['idlm_test_http_calls']));
});
idlm_test('INT005 ordinary and manual attempts share duplicate reversal and disabled gates',static function():void {
    foreach(array('duplicate','reversed','disabled','source') as $case){
        idlm_test_reset_environment();$id=idlm_capi_fast_fixture();$row=lead_fixture(array('stage'=>'Qualified','lead_quality'=>'Qualified','source'=>'Google Ads','gclid'=>'synthetic-click'));
        if($case==='duplicate'){$row['is_duplicate']=1;}
        if($case==='reversed'){$row['stage']='New';$row['lead_quality']='';}
        if($case==='disabled'){update_option('idlm_google_capi_enabled','0');}
        if($case==='source'){$row['source']='Meta Ads';$row['gclid']='';}
        $GLOBALS['wpdb']->seed('idlm_leads',array($row));assert_false(IDLM_CAPI::retry_capi_log($id)['success']);IDLM_CAPI::fire_google_capi((object)$row,'Qualified',true);assert_same(0,count($GLOBALS['idlm_test_http_calls']),$case);
    }
});
idlm_test('INT005 safe failed attempts are bounded across their result-log chain',static function():void {
    $id=idlm_capi_fast_fixture('meta','Qualified','Contact');$GLOBALS['idlm_test_http_code']=429;
    for($i=0;$i<3;$i++){$result=IDLM_CAPI::retry_capi_log($id);assert_false($result['success']);assert_true($result['log_id']>0);$id=$result['log_id'];}
    assert_false(IDLM_CAPI::retry_capi_log($id)['success']);assert_same(3,count($GLOBALS['idlm_test_http_calls']));
});
idlm_test('INT005 failed outcome writes are uncertain and cannot use an older success',static function():void {
    $id=idlm_capi_fast_fixture();$GLOBALS['wpdb']->fail_next_write('insert','idlm_capi_logs');$result=IDLM_CAPI::retry_capi_log($id);
    assert_false($result['success']);assert_same('uncertain',$result['outcome']);assert_same(0,$result['log_id']);assert_false(IDLM_CAPI::retry_capi_log($id)['success']);assert_same(1,count($GLOBALS['idlm_test_http_calls']));
});
idlm_test('INT005 bulk retry validates scalar bounded IDs before delivery',static function():void {
    idlm_test_login(1,array('administrator'),array('manage_options'));idlm_capi_fast_fixture();
    foreach(array(array_fill(0,51,1),array(array(1)),array('0'),array('-1'),array('1e2')) as $ids){$_POST=array('log_ids'=>$ids);expect_json_response(array('IDLM_Admin_Ajax','ajax_retry_capi_events'),false);}
    assert_same(0,count($GLOBALS['idlm_test_http_calls']));
});

function idlm_capi_quote_fast_seed($total=275000.50,$currency='INR'):void {
    $GLOBALS['wpdb']->seed('idlm_quotes',array(array('id'=>20,'lead_id'=>1,'current_revision_id'=>22)));
    $GLOBALS['wpdb']->seed('idlm_quote_revisions',array(array('id'=>21,'quote_id'=>20,'grand_total'=>100000,'currency_code'=>'USD'),array('id'=>22,'quote_id'=>20,'grand_total'=>$total,'currency_code'=>$currency)));
}
idlm_test('CAPIQUOTE Purchase uses current saved quotation total and its currency without changing CRM amounts',static function():void {
    idlm_test_reset_environment();idlm_capi_fast_fixture('meta','Won','Purchase');
    $row=lead_fixture(array('stage'=>'Won','lead_quality'=>'Qualified','num_rooms'=>2,'source'=>'Meta Ads','deal_value'=>125000,'quote_value'=>100000));$GLOBALS['wpdb']->seed('idlm_leads',array($row));
    idlm_capi_quote_fast_seed();update_option('idlm_currency_code','USD');
    $result=IDLM_CAPI::fire_meta_capi((object)$row,'Won');assert_true($result['attempted'],$result['message']);
    $data=json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'],true)['data'][0]['custom_data'];
    assert_same(275000.5,$data['value']);assert_same('INR',$data['currency']);assert_same(125000,$GLOBALS['wpdb']->tables['wp_idlm_leads'][0]['deal_value']);
});
idlm_test('CAPIQUOTE missing or zero-total quotation keeps entered deal value',static function():void {
    foreach(array(false,true) as $zero_quote){
        idlm_test_reset_environment();idlm_capi_fast_fixture('meta','Won','Purchase');$row=lead_fixture(array('stage'=>'Won','lead_quality'=>'Qualified','num_rooms'=>2,'source'=>'Meta Ads','deal_value'=>125000));$GLOBALS['wpdb']->seed('idlm_leads',array($row));
        if($zero_quote){idlm_capi_quote_fast_seed(0);}
        $r=IDLM_CAPI::fire_meta_capi((object)$row,'Won');assert_true($r['attempted'],$r['message']);$data=json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'],true)['data'][0]['custom_data'];assert_same(125000,$data['value']);
    }
});
idlm_test('CAPIQUOTE non-Purchase events retain existing commercial value',static function():void {
    idlm_test_reset_environment();idlm_capi_fast_fixture('meta','Qualified','Contact');$row=lead_fixture(array('stage'=>'Qualified','lead_quality'=>'Qualified','num_rooms'=>2,'source'=>'Meta Ads','deal_value'=>125000));$GLOBALS['wpdb']->seed('idlm_leads',array($row));idlm_capi_quote_fast_seed();
    $r=IDLM_CAPI::fire_meta_capi((object)$row,'Qualified');assert_true($r['attempted'],$r['message']);assert_same(125000,json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'],true)['data'][0]['custom_data']['value']);
});
idlm_test('CAPIQUOTE invalid quotation links or unknown quotation currency fail before provider delivery',static function():void {
    foreach(array('broken','currency') as $case){
        idlm_test_reset_environment();idlm_capi_fast_fixture('meta','Won','Purchase');$row=lead_fixture(array('stage'=>'Won','lead_quality'=>'Qualified','num_rooms'=>2,'source'=>'Meta Ads','deal_value'=>125000));$GLOBALS['wpdb']->seed('idlm_leads',array($row));idlm_capi_quote_fast_seed(275000,$case==='currency'?'':'INR');
        if($case==='broken'){$GLOBALS['wpdb']->tables['wp_idlm_quote_revisions'][1]['quote_id']=999;}
        $r=IDLM_CAPI::fire_meta_capi((object)$row,'Won');assert_false($r['success']);assert_same(0,count($GLOBALS['idlm_test_http_calls']));
    }
});
