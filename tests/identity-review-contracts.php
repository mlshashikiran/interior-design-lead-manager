<?php
/** Focused identity-review contracts. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }
idlm_test('identity review submission retains contact and project fields without credentials or workflow instructions', static function(): void {
    $data = IDLM_Identity_Review::submission(array('first_name'=>'Synthetic','email'=>'one@example.test','budget'=>'8 lakh','stage'=>'Won','assigned_rep_id'=>9,'access_token'=>'secret','quote_file'=>'private','nested'=>array('token'=>'secret')));
    assert_same(array('first_name'=>'Synthetic','email'=>'one@example.test','budget'=>'8 lakh'),$data);
});
idlm_test('identity review list and resolver deny ordinary read-all and suspended managers', static function(): void {
    foreach (array(array('idlm_access_leads'=>true),array('manage_options'=>true)) as $caps) {
        $GLOBALS['idlm_test_logged_in']=true;$GLOBALS['idlm_test_current_user_id']=12;$GLOBALS['idlm_test_caps']=$caps;
        if (isset($caps['manage_options'])) { $GLOBALS['idlm_test_user_meta'][12]['idlm_crm_access_disabled']='1'; }
        assert_true(is_wp_error(IDLM_Identity_Review::list_pending()));
        assert_true(is_wp_error(IDLM_Lead_Lifecycle_Service::resolve_inbound_review(1,'separate',0,'Synthetic reason')));
        assert_count_value(0,$GLOBALS['wpdb']->rows('idlm_leads'));
    }
});
idlm_test('identity conflict captures only supported fields and retains a pending review without actionable work', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('id'=>1,'email'=>'one@example.test','phone'=>'919111111111')),lead_fixture(array('id'=>2,'email'=>'two@example.test','phone'=>'919222222222'))));
    $result=IDLM_DB::upsert_inbound_lead(array('first_name'=>'Synthetic','email'=>'one@example.test','phone'=>'9222222222','budget'=>'8 lakh','access_token'=>'never-retain','stage'=>'Won'),'website');
    assert_same(0,$result['lead_id']);$row=$GLOBALS['wpdb']->rows('idlm_inbound_submission_reviews')[0];
    assert_same('Synthetic',json_decode($row['submission_data'],true)['first_name']);assert_false(strpos($row['submission_data'],'never-retain')!==false);
    assert_same('pending',$row['status']);assert_count_value(0,$GLOBALS['wpdb']->rows('idlm_tasks'));assert_count_value(0,$GLOBALS['wpdb']->rows('idlm_lead_outbox'));
});
idlm_test('identity review reset inventory independently includes the PII holding table', static function(): void {
    assert_true(in_array('idlm_inbound_submission_reviews',array_merge(...array_values(IDLM_Lead_Deletion_Service::reset_manifest())),true));
});
idlm_test('legacy identity reviews preserve known contact data without reconstructing redacted names', static function(): void {
    $r=(object)array('email'=>'old@example.test','phone'=>'919111111111','source'=>'Website','candidate_lead_ids'=>'[1,2]','payload'=>'{"first_name":"[REDACTED]"}');
    assert_same(array('email'=>'old@example.test','phone'=>'919111111111','source'=>'Website'),IDLM_Identity_Review::data($r));assert_same(array(1,2),IDLM_Identity_Review::candidate_ids($r));
});
