<?php
/** INT-006 real signed anonymous REST delivery and replay. Builder: Shashi. */
if ('cli'!==PHP_SAPI || strpos($config['db_name']??'','idlm_test_')!==0) {exit(1);}
function idlm_wa_http_call($body,$provider='meta',$secret='synthetic-http-inbox') {
    global $config;$raw=wp_json_encode($body);$sig='sha256='.hash_hmac('sha256',$raw,$secret);$in=tempnam($config['content_dir'],'wa-body-');$out=$in.'.response';file_put_contents($in,$raw);
    $args=array('curl','--silent','--show-error','--max-time','15','--header','Content-Type: application/json','--header',($provider==='meta'?'X-Hub-Signature-256: ':'Interakt-Signature: ').$sig,'--data-binary','@'.$in,'--output',$out,'--write-out','%{http_code}',$config['http_url'].'/wp-json/idlm/v1/'.$provider.'-webhook');$proc=proc_open(implode(' ',array_map('escapeshellarg',$args)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);$status=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);$r=array('status'=>(int)$status,'body'=>json_decode(file_get_contents($out),true));unlink($in);unlink($out);idlm_http_check($exit===0,'Webhook HTTP failed: '.$err);return $r;
}
function idlm_wa_http_msg($id,$phone='919999900010') {return array('id'=>$id,'from'=>$phone,'type'=>'text','text'=>array('body'=>'Synthetic '.$id));}
function idlm_wa_http_body($messages) {return array('object'=>'whatsapp_business_account','entry'=>array(array('changes'=>array(array('value'=>array('messages'=>$messages))))));}
function idlm_wa_http_fixture($phone='919999900010') {global $wpdb;$now=current_time('mysql');$wpdb->insert($wpdb->prefix.'idlm_leads',array('phone'=>$phone,'first_name'=>'Synthetic inbox','created_at'=>$now,'updated_at'=>$now));return (int)$wpdb->insert_id;}
function idlm_wa_http_count($lead) {global $wpdb;return (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_activity_log WHERE lead_id=%d AND activity_type='whatsapp_reply'",$lead));}
update_option('idlm_meta_webhook_enabled','1');update_option('idlm_meta_webhook_app_secret','synthetic-http-inbox');update_option('idlm_interakt_enabled','1');update_option('idlm_interakt_webhook_secret','synthetic-http-inbox');$wa_lead=idlm_wa_http_fixture();
idlm_http_case('T07-H-REL007-Meta-verification-is-plain-text-and-denies-malformed-query-shapes', static function() {
    // A literal backslash detects accidental second unslashing of REST values.
    $token = "synthetic\\verify'token";
    update_option('idlm_meta_webhook_verify_token', $token);
    $valid = array('hub_mode' => 'subscribe', 'hub_verify_token' => $token, 'hub_challenge' => '12345');
    $before = idlm_http_snapshot();
    $r = idlm_http_request('/wp-json/idlm/v1/meta-webhook', $valid, 'anonymous', false);
    idlm_http_check($r['status'] === 200 && $r['body'] === '12345', 'Verification did not return the exact plain-text challenge');
    idlm_http_check(stripos($r['headers'], 'Content-Type: text/plain') !== false, 'Verification content type changed');
    $zero = $valid; $zero['hub_challenge'] = '0';
    $r = idlm_http_request('/wp-json/idlm/v1/meta-webhook', $zero, 'anonymous', false);
    idlm_http_check($r['status'] === 200 && $r['body'] === '0', 'Zero challenge was treated as missing');
    foreach (array_keys($valid) as $key) {
        foreach (array(array('12345'), '') as $invalid) {
            $params = $valid; $params[$key] = $invalid;
            $r = idlm_http_request('/wp-json/idlm/v1/meta-webhook', $params, 'anonymous', false);
            idlm_http_check($r['status'] === 403, 'Malformed verification parameter accepted: ' . $key);
            idlm_http_check(strpos($r['body'], $token) === false, 'Verification token disclosed');
        }
    }
    $wrong = $valid; $wrong['hub_verify_token'] = 'wrong';
    idlm_http_check(idlm_http_request('/wp-json/idlm/v1/meta-webhook', $wrong, 'anonymous', false)['status'] === 403, 'Wrong verification token accepted');
    update_option('idlm_meta_webhook_enabled', '0');
    idlm_http_check(idlm_http_request('/wp-json/idlm/v1/meta-webhook', $valid, 'anonymous', false)['status'] === 403, 'Disabled verification accepted');
    update_option('idlm_meta_webhook_enabled', '1');
    idlm_http_check($before === idlm_http_snapshot(), 'Verification changed CRM rows or jobs');
});
idlm_http_case('T07-H-INT006-signature-disabled-and-malformed-reject-without-writes',static function()use($wa_lead){$b=idlm_wa_http_body(array(idlm_wa_http_msg('auth')));idlm_http_check(idlm_wa_http_call($b,'meta','wrong')['status']===401,'Wrong signature accepted');update_option('idlm_meta_webhook_enabled','0');idlm_http_check(idlm_wa_http_call($b)['status']===403,'Disabled accepted');update_option('idlm_meta_webhook_enabled','1');$bad=idlm_wa_http_msg('missing');unset($bad['id']);idlm_http_check(idlm_wa_http_call(idlm_wa_http_body(array(idlm_wa_http_msg('good'),$bad)))['status']===400,'Malformed batch accepted');idlm_http_check(idlm_wa_http_count($wa_lead)===0,'Denied request persisted activity');});
idlm_http_case('T09-H-INT006-multiple-entries-changes-media-status-and-replay-append-once',static function()use($wa_lead){$b=idlm_wa_http_body(array(idlm_wa_http_msg('http-one'),idlm_wa_http_msg('http-two')));$b['entry'][]=array('changes'=>array(array('value'=>array('statuses'=>array(array('status'=>'read')))),array('value'=>array('messages'=>array(array('id'=>'http-image','from'=>'919999900010','type'=>'image'))))));$r=idlm_wa_http_call($b);idlm_http_check($r['status']===200 && $r['body']['processed']===3,'Incomplete batch acceptance');$r=idlm_wa_http_call($b);idlm_http_check($r['status']===200 && $r['body']['replayed']===3,'Replay appended');idlm_http_check(idlm_wa_http_count($wa_lead)===3,'Wrong activity count');});
idlm_http_case('T09-H-INT006-partial-unmatched-delivery-resumes-after-identity-repair',static function(){ $b=idlm_wa_http_body(array(idlm_wa_http_msg('http-partial-good'),idlm_wa_http_msg('http-partial-late','449999900010')));$r=idlm_wa_http_call($b);idlm_http_check($r['status']===409 && $r['body']['processed']===1 && $r['body']['failed']===1,'Partial failure acknowledged as success');idlm_wa_http_fixture('449999900010');$r=idlm_wa_http_call($b);idlm_http_check($r['status']===200 && $r['body']['processed']===1 && $r['body']['replayed']===1,'Partial replay not resumed'); });
idlm_http_case('T07-H-INT006-Interakt-ID-media-replay-and-collision-use-own-receipt',static function()use($wa_lead){$b=array('type'=>'message_received','data'=>array('customer'=>array('channel_phone_number'=>'919999900010'),'message'=>array('id'=>'http-one','message_content_type'=>'Image','message'=>'')));$r=idlm_wa_http_call($b,'interakt');idlm_http_check($r['status']===200 && $r['body']['processed']===1,'Interakt namespace collided');$r=idlm_wa_http_call($b,'interakt');idlm_http_check($r['body']['replayed']===1,'Interakt replay appended');$b['data']['message']['message']='Changed';idlm_http_check(idlm_wa_http_call($b,'interakt')['status']===409,'ID collision accepted');});
idlm_http_case('T09-H-INT006-required-receipt-failure-rolls-back-and-can-retry',static function()use($wa_lead){global $config;$b=idlm_wa_http_body(array(idlm_wa_http_msg('http-write-fault')));$before=idlm_wa_http_count($wa_lead);touch($config['content_dir'].'/fail-wa-receipt');try{$r=idlm_wa_http_call($b);}finally{unlink($config['content_dir'].'/fail-wa-receipt');}idlm_http_check($r['status']===503,'Failed durable receipt accepted');idlm_http_check(idlm_wa_http_count($wa_lead)===$before,'Receipt failure left activity');idlm_http_check(idlm_wa_http_call($b)['status']===200,'Failed receipt not retried');});
idlm_http_case('T07-H-INT006-batch-size-bound-and-full-phone-ambiguity',static function()use($wa_lead){$before=idlm_wa_http_count($wa_lead);idlm_http_check(idlm_wa_http_call(idlm_wa_http_body(array_fill(0,101,idlm_wa_http_msg('oversized'))))['status']===413,'Oversized batch accepted');idlm_wa_http_fixture('9999900010');idlm_http_check(idlm_wa_http_call(idlm_wa_http_body(array(idlm_wa_http_msg('ambiguous'))))['status']===409,'Ambiguous match accepted');idlm_http_check(idlm_wa_http_count($wa_lead)===$before,'Ambiguous activity persisted');});
