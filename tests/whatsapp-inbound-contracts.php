<?php
/** INT-006 signed batches, exact identity and durable acknowledgement. Builder: Shashi. */
function idlm_wa_fast_body($messages) { return array('object'=>'whatsapp_business_account','entry'=>array(array('changes'=>array(array('value'=>array('messages'=>$messages)))))); }
function idlm_wa_fast_call($data, $provider='meta', $signature=null) {
    $body=wp_json_encode($data);$header=$provider==='meta'?'X-Hub-Signature-256':'Interakt-Signature';
    $r=new WP_REST_Request(array($header=>$signature??'sha256='.hash_hmac('sha256',$body,'synthetic-inbox-secret')),array(),array(),array(),$body);
    return $provider==='meta'?IDLM_Interakt::handle_meta_webhook_request($r):IDLM_Interakt::handle_webhook_request($r);
}
function idlm_wa_fast_fixture() {
    update_option('idlm_meta_webhook_enabled','1');update_option('idlm_meta_webhook_app_secret','synthetic-inbox-secret');update_option('idlm_interakt_enabled','1');update_option('idlm_interakt_webhook_secret','synthetic-inbox-secret');
    $GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('phone'=>'+91 99999-00001'))));
}
function idlm_wa_fast_message($id,$phone='919999900001') {return array('id'=>$id,'from'=>$phone,'type'=>'text','text'=>array('body'=>'Synthetic '.$id));}
idlm_test('REL007 Meta verification rejects missing and non-string REST or raw query parameters', static function(): void {
    update_option('idlm_meta_webhook_enabled', '1');
    update_option('idlm_meta_webhook_verify_token', 'synthetic-verify-token');
    $valid = array('hub_mode' => 'subscribe', 'hub_verify_token' => 'synthetic-verify-token', 'hub_challenge' => '12345');
    $original_get = $_GET;
    try {
        $_GET = array();
        foreach (array_keys($valid) as $key) {
            foreach (array(array('12345'), new stdClass(), 12345, false, null, '') as $invalid) {
                $params = $valid; $params[$key] = $invalid;
                $response = IDLM_Interakt::handle_meta_webhook_verification(new WP_REST_Request(array(), $params));
                assert_same(403, $response->get_status());
            }
            $_GET = $valid; $_GET[$key] = array('12345');
            $response = IDLM_Interakt::handle_meta_webhook_verification(new WP_REST_Request());
            assert_same(403, $response->get_status());
            $_GET = array();
        }
    } finally {
        $_GET = $original_get;
    }
});
idlm_test('REL007 Meta verification unslashes raw fallbacks once and preserves REST token bytes', static function(): void {
    $token = "synthetic\\verify'token";
    $valid = array('hub_mode' => 'subscribe', 'hub_verify_token' => $token, 'hub_challenge' => '0');
    foreach (array('raw', 'rest') as $source) {
        // Successful verification deliberately exits after plain-text output;
        // use the actual callback in an isolated process for both input paths.
        $code = 'require ' . var_export(__DIR__ . '/bootstrap.php', true) . ';'
            . 'update_option("idlm_meta_webhook_enabled", "1");'
            . 'update_option("idlm_meta_webhook_verify_token", ' . var_export($token, true) . ');'
            . '$params = ' . var_export($valid, true) . ';'
            . ($source === 'raw' ? '$_GET = array_map("addslashes", $params); $params = array();' : '$_GET = array();')
            . 'IDLM_Interakt::handle_meta_webhook_verification(new WP_REST_Request(array(), $params)); exit(1);';
        $process = proc_open(array(PHP_BINARY, '-r', $code), array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        assert_true(is_resource($process), 'Verification subprocess failed to start');
        $body = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        assert_same(0, proc_close($process), 'Verification failed for ' . $source);
        assert_same('0', $body, 'Challenge changed for ' . $source);
        assert_same('', $errors, 'Verification emitted diagnostics for ' . $source);
    }
});
idlm_test('INT006 every Meta entry change and message commits once across batch replay',static function():void {
    idlm_wa_fast_fixture();$body=idlm_wa_fast_body(array(idlm_wa_fast_message('m1'),idlm_wa_fast_message('m2')));$body['entry'][0]['changes'][]=array('value'=>array('statuses'=>array(array('id'=>'status'))));$body['entry'][]=array('changes'=>array(array('value'=>array('messages'=>array(idlm_wa_fast_message('m3'))))));
    $r=idlm_wa_fast_call($body);assert_same(200,$r->get_status());assert_same(3,$r->get_data()['processed']);assert_same(3,count($GLOBALS['wpdb']->rows('idlm_lead_activity_log')));
    $r=idlm_wa_fast_call($body);assert_same(3,$r->get_data()['replayed']);assert_same(3,count($GLOBALS['wpdb']->rows('idlm_lead_activity_log')));
});
idlm_test('INT006 malformed identity and signature reject entire batch before activity writes',static function():void {
    idlm_wa_fast_fixture();$bad=idlm_wa_fast_message('bad');unset($bad['id']);assert_same(400,idlm_wa_fast_call(idlm_wa_fast_body(array(idlm_wa_fast_message('good'),$bad)))->get_status());assert_same(401,idlm_wa_fast_call(idlm_wa_fast_body(array(idlm_wa_fast_message('good'))),'meta','invalid')->get_status());assert_same(0,count($GLOBALS['wpdb']->rows('idlm_lead_activity_log')));
});
idlm_test('INT006 partial failure retries only messages not durably committed',static function():void {
    idlm_wa_fast_fixture();$body=idlm_wa_fast_body(array(idlm_wa_fast_message('m1'),idlm_wa_fast_message('m2','447777700001')));$r=idlm_wa_fast_call($body);assert_same(409,$r->get_status());assert_same(1,$r->get_data()['processed']);
    $GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('phone'=>'919999900001')),lead_fixture(array('id'=>2,'phone'=>'447777700001'))));$r=idlm_wa_fast_call($body);assert_same(200,$r->get_status());assert_same(1,$r->get_data()['processed']);assert_same(1,$r->get_data()['replayed']);assert_same(2,count($GLOBALS['wpdb']->rows('idlm_lead_activity_log')));
});
idlm_test('INT006 full international identity never selects a shared suffix or multiple canonicals',static function():void {
    idlm_wa_fast_fixture();$GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('phone'=>'919999900001')),lead_fixture(array('id'=>2,'phone'=>'449999900001'))));assert_same(200,idlm_wa_fast_call(idlm_wa_fast_body(array(idlm_wa_fast_message('exact'))))->get_status());assert_same(1,$GLOBALS['wpdb']->rows('idlm_lead_activity_log')[0]['lead_id']);
    $GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('phone'=>'9999900001')),lead_fixture(array('id'=>2,'phone'=>'919999900001'))));assert_same(409,idlm_wa_fast_call(idlm_wa_fast_body(array(idlm_wa_fast_message('ambiguous'))))->get_status());
});
idlm_test('INT006 failed receipt or activity persistence cannot acknowledge delivery',static function():void {
    foreach(array('idlm_lead_activity_log','options') as $table){idlm_test_reset_environment();idlm_wa_fast_fixture();$GLOBALS['wpdb']->fail_next_write('insert',$table);$body=idlm_wa_fast_body(array(idlm_wa_fast_message('fault')));assert_same(503,idlm_wa_fast_call($body)->get_status());assert_same(0,count($GLOBALS['wpdb']->rows('idlm_lead_activity_log')));assert_same(200,idlm_wa_fast_call($body)->get_status());assert_same(1,count($GLOBALS['wpdb']->rows('idlm_lead_activity_log')));}
});
idlm_test('INT006 Interakt media uses provider message ID and refuses changed replay content',static function():void {
    idlm_wa_fast_fixture();$body=array('type'=>'message_received','data'=>array('customer'=>array('channel_phone_number'=>'919999900001'),'message'=>array('id'=>'interakt-1','message_content_type'=>'Image','media_url'=>'https://example.test/private','message'=>'')));
    assert_same(200,idlm_wa_fast_call($body,'interakt')->get_status());assert_same(1,idlm_wa_fast_call($body,'interakt')->get_data()['replayed']);assert_same('[WhatsApp Reply]: [Image received]',$GLOBALS['wpdb']->rows('idlm_lead_activity_log')[0]['details']);$body['data']['message']['message']='Changed';assert_same(409,idlm_wa_fast_call($body,'interakt')->get_status());
});
