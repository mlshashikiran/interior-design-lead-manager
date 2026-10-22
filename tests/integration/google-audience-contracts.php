<?php
/** INT-007 native current rules, denied reads and scheduled/manual parity. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }
function idlm_google_native_rule($enabled='1', $stage='Won'): array {
    return array('platform'=>'google','audience_id'=>'customers/123/userLists/456','enabled'=>$enabled,'stage'=>$stage,'call_status'=>'all','lead_quality'=>'all');
}
idlm_integration_test('T07-N-INT007 disabled feed returns 404 before contact query', static function(): void {
    update_option('idlm_google_gadm_password','synthetic-feed-native');update_option('idlm_audience_sync_rules',array(idlm_google_native_rule('0')));
    $reads=0;$f=static function($sql)use(&$reads){if(preg_match('/SELECT.*idlm_leads/i',$sql)){$reads++;}return $sql;};add_filter('query',$f);
    try{$r=new WP_REST_Request('GET');$r->set_header('X-IDLM-API-Key','synthetic-feed-native');$r->set_param('list_id','456');$result=IDLM_Integrations::handle_google_ads_audience_feed($r);}finally{remove_filter('query',$f);}
    idlm_integration_assert_same(404,$result->get_status());idlm_integration_assert_same(0,$reads);
});
idlm_integration_test('T08-N-INT007 manual scheduled sync skips disabled rules and retains pending queue',static function():void {
    global $wpdb;$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_audience_sync_logs");
    update_option('idlm_audience_sync_rules',array(idlm_google_native_rule('0')));update_option('idlm_audience_sync_queue',array(999999));
    IDLM_Audiences::run_batch_sync();idlm_integration_assert_same(array(999999),get_option('idlm_audience_sync_queue'));idlm_integration_assert_same($before,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_audience_sync_logs"));delete_option('idlm_audience_sync_queue');
    $now=current_time('mysql');$wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'Synthetic audience','phone'=>'919999900007','stage'=>'Won','created_at'=>$now,'updated_at'=>$now));
    update_option('idlm_audience_sync_rules',array(idlm_google_native_rule('0'),idlm_google_native_rule()));
    IDLM_Audiences::run_batch_sync();idlm_integration_assert_same($before+1,(int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_audience_sync_logs"));
    idlm_integration_assert_same('Won',IDLM_Audiences::resolve_google_feed_rule('456')['stage']);
});
idlm_integration_test('T07-N-INT007 explicit rules take precedence over legacy and current toggles',static function():void {
    update_option('idlm_sync_google_audience','1');update_option('idlm_google_user_list_resource','customers/123/userLists/456');delete_option('idlm_audience_sync_rules');
    idlm_integration_assert_same('all',IDLM_Audiences::resolve_google_feed_rule('456')['stage']);
    foreach(array(array(),'corrupt',array(idlm_google_native_rule('0'))) as $rules){update_option('idlm_audience_sync_rules',$rules);idlm_integration_assert_same(null,IDLM_Audiences::resolve_google_feed_rule('456'));}
    update_option('idlm_audience_sync_rules',array(idlm_google_native_rule()));idlm_integration_assert_same('Won',IDLM_Audiences::resolve_google_feed_rule('456')['stage']);
});
