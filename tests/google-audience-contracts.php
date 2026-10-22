<?php
/** INT-007 active audience rule and denied-feed contracts. Builder: Shashi. */
function idlm_google_rule(array $overrides = array()): array {
    return array_merge(array('platform'=>'google','audience_id'=>'customers/123/userLists/456','enabled'=>'1','stage'=>'all','call_status'=>'all','lead_quality'=>'all'), $overrides);
}
idlm_test('INT007 disabled pull rule denies before lead query or CSV output', static function(): void {
    update_option('idlm_google_gadm_password', 'synthetic-feed-secret');
    foreach (array('0', null, '', 'false') as $enabled) {
        update_option('idlm_audience_sync_rules', array(idlm_google_rule(array('enabled'=>$enabled))));
        $GLOBALS['wpdb']->queries = array();
        $r = IDLM_Integrations::handle_google_ads_audience_feed(new WP_REST_Request(array('X-IDLM-API-Key'=>'synthetic-feed-secret'), array('list_id'=>'456')));
        assert_same(404, $r->get_status());
        assert_false((bool) array_filter($GLOBALS['wpdb']->queries, static function($q) { return strpos($q, 'idlm_leads') !== false; }));
    }
});
idlm_test('INT007 disabled duplicates cannot shadow enabled first match and re-enable is live', static function(): void {
    $off=idlm_google_rule(array('enabled'=>'0','stage'=>'Won')); $on=idlm_google_rule(array('stage'=>'Qualified'));
    update_option('idlm_audience_sync_rules', array($off, $on, idlm_google_rule(array('stage'=>'New'))));
    assert_same($on, IDLM_Audiences::resolve_google_feed_rule('456'));
    assert_same($on, IDLM_Audiences::resolve_google_feed_rule('customers/123/userLists/456'));
    update_option('idlm_audience_sync_rules', array($off)); assert_same(null, IDLM_Audiences::resolve_google_feed_rule('456'));
    $off['enabled']='1'; update_option('idlm_audience_sync_rules', array($off)); assert_same($off, IDLM_Audiences::resolve_google_feed_rule('456'));
});
idlm_test('INT007 missing option legacy fallback is shared and explicit empty or corrupt rules deny', static function(): void {
    update_option('idlm_sync_google_audience','1');update_option('idlm_google_user_list_resource','customers/123/userLists/456');
    assert_true(idlm_google_rule() == IDLM_Audiences::resolve_google_feed_rule('456'));
    foreach (array(array(), 'corrupt', array(idlm_google_rule(array('enabled'=>'0')))) as $rules) {
        update_option('idlm_audience_sync_rules',$rules); assert_same(null,IDLM_Audiences::resolve_google_feed_rule('456')); assert_same(array(),IDLM_Audiences::get_active_rules());
    }
});
idlm_test('INT007 malformed rules and wrong platform are skipped without widening filters', static function(): void {
    foreach (array(null, 'bad', array(), idlm_google_rule(array('audience_id'=>array('456'))),idlm_google_rule(array('stage'=>array('Won'))),idlm_google_rule(array('platform'=>'unknown'))) as $bad) {
        update_option('idlm_audience_sync_rules',array($bad));assert_same(array(),IDLM_Audiences::get_active_rules());
    }
    update_option('idlm_audience_sync_rules',array(idlm_google_rule(array('platform'=>'meta'))));assert_same(null,IDLM_Audiences::resolve_google_feed_rule('456'));
    update_option('idlm_audience_sync_rules',array(idlm_google_rule(array('audience_id'=>' /customers/123/userlists/456 '))));assert_true(is_array(IDLM_Audiences::resolve_google_feed_rule('456')));
});
idlm_test('INT007 malformed list ID returns 400 before rule contact reads', static function(): void {
    update_option('idlm_google_gadm_password','synthetic-feed-secret');update_option('idlm_audience_sync_rules',array(idlm_google_rule()));
    foreach(array(null, '', '  ', array('456')) as $id){$r=IDLM_Integrations::handle_google_ads_audience_feed(new WP_REST_Request(array('X-IDLM-API-Key'=>'synthetic-feed-secret'),array('list_id'=>$id)));assert_same(400,$r->get_status());}
});
