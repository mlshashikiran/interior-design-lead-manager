<?php
/** INT-009 fast rule parity and explicit provider outcome contracts. Builder: Shashi. */
idlm_test('INT009 queued selection must satisfy all current rule exclusions',static function():void {
    $rule=idlm_google_rule(array('platform'=>'meta','stage'=>'Qualified','call_status'=>'Connected','lead_quality'=>'Qualified'));
    $lead=(object)lead_fixture(array('stage'=>'Qualified','call_status'=>'Connected','lead_quality'=>'Qualified','is_duplicate'=>0));assert_true(IDLM_Audience_Dispatch::eligible($lead,$rule));
    foreach(array('is_duplicate'=>1,'stage'=>'New','call_status'=>'No Answer','lead_quality'=>'Unqualified')as$key=>$value){$changed=clone $lead;$changed->$key=$value;assert_false(IDLM_Audience_Dispatch::eligible($changed,$rule));}assert_false(IDLM_Audience_Dispatch::eligible(null,$rule));
});
idlm_test('INT009 normalized rule identity ignores enabled toggle but binds target and filters',static function():void {
    $rule=idlm_google_rule();$key=IDLM_Audience_Dispatch::rule_key($rule);assert_same($key,IDLM_Audience_Dispatch::rule_key(array_merge($rule,array('enabled'=>'0'))));
    foreach(array('platform'=>'meta','audience_id'=>'other','stage'=>'Won','call_status'=>'Connected','lead_quality'=>'Qualified','is_value'=>'1')as$field=>$value){assert_false($key===IDLM_Audience_Dispatch::rule_key(array_merge($rule,array($field=>$value))));}
});
idlm_test('INT009 provider rejection retry cooldown and ambiguous response classification',static function():void {
    foreach(array(array(200,'{}','sent'),array(201,'{}','sent'),array(429,'{}','failed'),array(503,'{}','failed'),array(400,'{}','blocked'),array(200,'invalid','uncertain'),array(200,'{"num_invalid_entries":1}','uncertain'),array(200,'{"elements":[{"status":400}]}','uncertain'),array(200,'{"error":{"message":"Synthetic rejection"}}','blocked'))as[$code,$body,$status]){
        $r=invoke_private('IDLM_Audiences','outcome',array(array('response'=>array('code'=>$code),'body'=>$body,'headers'=>array('retry-after'=>'300')),array('synthetic'=>true),array(200,201)));assert_same($status,$r['status']);assert_same(300,$r['retry_after']);
    }
    $r=invoke_private('IDLM_Audiences','outcome',array(new WP_Error('timeout','Synthetic timeout'),array(),array(200)));assert_same('uncertain',$r['status']);
});
idlm_test('INT009 no queued selection is permitted to invent a manual all-lead scan',static function():void {
    update_option('idlm_audience_sync_rules',array(idlm_google_rule()));$GLOBALS['wpdb']->queries=array();$r=IDLM_Audiences::run_batch_sync(true);assert_true(is_array($r));assert_same(0,$r['pages']);assert_false((bool)array_filter($GLOBALS['wpdb']->queries,static function($sql){return strpos($sql,'SELECT')===0&&strpos($sql,'idlm_leads')!==false;}));
});
