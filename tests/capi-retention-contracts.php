<?php
/** INT-008 retention-independent CAPI delivery contracts. Builder: Shashi. */
function idlm_capi_ret_fast_fixture(): void {
    idlm_capi_fast_fixture();$rows=$GLOBALS['wpdb']->rows('idlm_leads');$rows[0]['num_rooms']=2;$GLOBALS['wpdb']->seed('idlm_leads',$rows);
}
idlm_test('Google transport uses a stable numeric CRM client ID while preserving authentic click attribution',static function():void {
    idlm_capi_ret_fast_fixture();
    $lead=(object)$GLOBALS['wpdb']->rows('idlm_leads')[0];
    IDLM_CAPI::fire_google_capi($lead,'Qualified');
    $payload=json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'],true);
    assert_same(1,preg_match('/^[0-9]+\.[0-9]+$/D',$payload['client_id']));
    assert_same('CRM_Qualified_Lead',$payload['events'][0]['name']);
    assert_same($lead->gclid,$payload['events'][0]['params']['gclid']);
    $client=$payload['client_id'];
    idlm_test_reset_environment();idlm_capi_ret_fast_fixture();
    $rows=$GLOBALS['wpdb']->rows('idlm_leads');$rows[0]['gclid']='different-authentic-click';$GLOBALS['wpdb']->seed('idlm_leads',$rows);
    IDLM_CAPI::fire_google_capi((object)$rows[0],'Qualified');
    $payload=json_decode($GLOBALS['idlm_test_http_calls'][0][1]['body'],true);
    assert_same($client,$payload['client_id']);
    assert_same('different-authentic-click',$payload['events'][0]['params']['gclid']);
});
idlm_test('Google invalid event labels are rejected before claims or provider requests',static function():void {
    foreach(array('9invalid','bad-event',str_repeat('a',41))as$label){
        idlm_test_reset_environment();idlm_capi_ret_fast_fixture();update_option('idlm_google_event_mappings',array('Qualified'=>$label));
        $result=IDLM_CAPI::fire_google_capi((object)$GLOBALS['wpdb']->rows('idlm_leads')[0],'Qualified');
        assert_false($result['attempted']);assert_same(0,count($GLOBALS['idlm_test_http_calls']));
        assert_same(null,IDLM_CAPI_Delivery::read(1,'google',$label));
    }
});
idlm_test('INT008 ordinary success remains deduplicated after diagnostic logs disappear',static function():void {
    idlm_capi_ret_fast_fixture();$lead=(object)$GLOBALS['wpdb']->rows('idlm_leads')[0];
    assert_true(IDLM_CAPI::fire_google_capi($lead,'Qualified')['success']);$GLOBALS['wpdb']->seed('idlm_capi_logs',array());
    IDLM_CAPI::fire_google_capi($lead,'Qualified');assert_same(1,count($GLOBALS['idlm_test_http_calls']));
});
idlm_test('INT008 failed outcome persistence leaves a durable uncertain fence after logs are removed',static function():void {
    idlm_capi_ret_fast_fixture();$lead=(object)$GLOBALS['wpdb']->rows('idlm_leads')[0];$GLOBALS['wpdb']->fail_next_write('insert','idlm_capi_logs');
    $r=IDLM_CAPI::fire_google_capi($lead,'Qualified');assert_false($r['success']);assert_same('uncertain',$r['outcome']);assert_same(1,count($GLOBALS['idlm_test_http_calls']));
    $GLOBALS['wpdb']->seed('idlm_capi_logs',array());$r=IDLM_CAPI::fire_google_capi($lead,'Qualified',true);assert_false($r['attempted']);assert_same(1,count($GLOBALS['idlm_test_http_calls']));assert_same('uncertain',IDLM_CAPI_Delivery::read(1,'google','CRM Qualified Lead')['status']);
    $duplicate=(array)$lead;$duplicate['is_duplicate']=1;$duplicate['duplicate_of_id']=2;$canonical=lead_fixture(array('id'=>2,'stage'=>'Qualified','lead_quality'=>'Qualified','source'=>'Google Ads','gclid'=>'synthetic','num_rooms'=>2));$GLOBALS['wpdb']->seed('idlm_leads',array($duplicate,$canonical));IDLM_CAPI::fire_google_capi((object)$canonical,'Qualified',true);assert_same(1,count($GLOBALS['idlm_test_http_calls']));
});
idlm_test('INT008 claim failure sends nothing and later safe retry can succeed',static function():void {
    idlm_capi_ret_fast_fixture();IDLM_Recovery_Guard::generation();$lead=(object)$GLOBALS['wpdb']->rows('idlm_leads')[0];$GLOBALS['wpdb']->fail_next_write('insert','options');
    $r=IDLM_CAPI::fire_google_capi($lead,'Qualified');assert_false($r['attempted']);assert_same(0,count($GLOBALS['idlm_test_http_calls']));
    assert_true(IDLM_CAPI::fire_google_capi($lead,'Qualified')['success']);assert_same(1,count($GLOBALS['idlm_test_http_calls']));
});
idlm_test('INT008 force and attribution changes cannot replay a prior successful delivery',static function():void {
    idlm_capi_ret_fast_fixture();$lead=(object)$GLOBALS['wpdb']->rows('idlm_leads')[0];assert_true(IDLM_CAPI::fire_google_capi($lead,'Qualified')['success']);
    $r=IDLM_CAPI::fire_google_capi($lead,'Qualified',true);assert_false($r['attempted']);$receipt=IDLM_CAPI_Delivery::read(1,'google','CRM Qualified Lead');assert_true($receipt['sent']);assert_same('sent',$receipt['status']);
    $GLOBALS['wpdb']->seed('idlm_capi_logs',array());IDLM_CAPI::handle_attribution_merged(1,array('gclid'=>'new'));IDLM_CAPI::fire_google_capi($lead,'Qualified');assert_same(1,count($GLOBALS['idlm_test_http_calls']));
});
idlm_test('CAPI ambiguous provider responses fence normal forced and manual replay',static function():void {
    foreach(array('meta','google','linkedin') as $platform){
        foreach(array(408,503) as $code){
            idlm_test_reset_environment();$event=$platform==='meta'?'Contact':'CRM Qualified Lead';$id=idlm_capi_fast_fixture($platform,'Qualified',$event);$GLOBALS['idlm_test_http_code']=$code;
            $lead=(object)$GLOBALS['wpdb']->rows('idlm_leads')[0];$method='fire_'.$platform.'_capi';$result=IDLM_CAPI::$method($lead,'Qualified');
            assert_same('uncertain',$result['outcome']);assert_same(1,count($GLOBALS['idlm_test_http_calls']));
            IDLM_CAPI::$method($lead,'Qualified',true);IDLM_CAPI::retry_capi_log($id);IDLM_CAPI::retry_capi_log($result['log_id']);assert_same(1,count($GLOBALS['idlm_test_http_calls']));
        }
    }
});
idlm_test('CAPI unrecognized success responses and Google non-2xx fence replay',static function():void {
    foreach(array(array('meta',200),array('google',400),array('linkedin',204)) as $case){
        idlm_test_reset_environment();[$platform,$code]=$case;$event=$platform==='meta'?'Contact':'CRM Qualified Lead';idlm_capi_fast_fixture($platform,'Qualified',$event);$GLOBALS['idlm_test_http_code']=$code;
        $lead=(object)$GLOBALS['wpdb']->rows('idlm_leads')[0];$method='fire_'.$platform.'_capi';$result=IDLM_CAPI::$method($lead,'Qualified');assert_same('uncertain',$result['outcome']);IDLM_CAPI::$method($lead,'Qualified');assert_same(1,count($GLOBALS['idlm_test_http_calls']));
    }
});
idlm_test('CAPI old failed receipt with unknown provider outcome fences replay',static function():void {
    idlm_capi_ret_fast_fixture();$lead=(object)$GLOBALS['wpdb']->rows('idlm_leads')[0];$receipt=array('version'=>1,'lead_id'=>1,'platform'=>'google','event'=>'CRM Qualified Lead','sent'=>false,'status'=>'failed','attempt_id'=>'legacy','updated_at'=>'2000-01-01 00:00:00');
    $GLOBALS['wpdb']->insert($GLOBALS['wpdb']->options,array('option_name'=>IDLM_CAPI_Delivery::key(1,'google','CRM Qualified Lead'),'option_value'=>serialize($receipt),'autoload'=>'no'));
    $result=IDLM_CAPI::fire_google_capi($lead,'Qualified');assert_false($result['attempted']);assert_same(0,count($GLOBALS['idlm_test_http_calls']));assert_same('uncertain',IDLM_CAPI_Delivery::read(1,'google','CRM Qualified Lead')['status']);
});
idlm_test('INT008 legacy success migration preserves interrupted claim and stores no request or credential',static function():void {
    idlm_capi_ret_fast_fixture();$r=IDLM_Lead_Transaction::run(static function(){return IDLM_CAPI_Delivery::remember_success(1,'google','CRM Qualified Lead','2000-01-01 00:00:00');});assert_true($r);
    $receipt=IDLM_CAPI_Delivery::read(1,'google','CRM Qualified Lead');assert_same('sent',$receipt['status']);$body=serialize($receipt);
    $receipt['sent']=false;$receipt['status']='processing';$receipt['attempt_id']='synthetic-interrupted';$GLOBALS['wpdb']->update($GLOBALS['wpdb']->options,array('option_value'=>serialize($receipt)),array('option_name'=>IDLM_CAPI_Delivery::key(1,'google','CRM Qualified Lead')));
    assert_true(IDLM_Lead_Transaction::run(static function(){return IDLM_CAPI_Delivery::remember_success(1,'google','CRM Qualified Lead','2000-01-01 00:00:00');}));$merged=IDLM_CAPI_Delivery::read(1,'google','CRM Qualified Lead');assert_true($merged['sent']);assert_same('processing',$merged['status']);
    foreach(array('synthetic-google','synthetic-click','user_data','payload','response','currency')as$secret){assert_false(strpos($body,$secret)!==false);}
});
