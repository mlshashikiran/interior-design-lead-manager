<?php
/** CORE-013 contextual vocabularies and lifecycle behavior. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

idlm_test('CORE013 manual task types persist while lifecycle types remain reserved', static function(): void {
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 7))));
    foreach (array('General', 'Call', 'Email', 'Site Visit', 'Design Mockup', 'Quote Revision') as $type) {
        $_POST = array('lead_id' => 1, 'title' => 'Synthetic manual work', 'task_type' => $type);
        expect_json_response(array('IDLM_Admin_Ajax', 'ajax_add_lead_task'), true);
        assert_same($type, end($GLOBALS['wpdb']->tables['wp_idlm_tasks'])['task_type']);
        assert_true(in_array($type, IDLM_State_Registry::task_types(), true));
    }
    foreach (array('Qualification', 'Sales Follow-up', 'Invented') as $type) {
        $_POST['task_type'] = $type;
        expect_json_response(array('IDLM_Admin_Ajax', 'ajax_add_lead_task'), false);
    }
    assert_count_value(6, $GLOBALS['wpdb']->rows('idlm_tasks'));
});

idlm_test('CORE013 early-close contextual reasons retain evidence and administrator boundaries', static function(): void {
    foreach (array('spam_test', 'administrator_exception', 'not_interested', 'invalid_contact') as $reason) {
        idlm_test_reset_environment();
        idlm_test_login(7, array('idlm_pre_sales'), array('idlm_access_leads'));
        $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('assigned_rep_id' => 7))));
        $_POST = array('lead_id' => 1, 'kind' => 'early_close', 'reason' => $reason, 'note' => 'Synthetic documented evidence');
        expect_json_response(array('IDLM_Admin_Ajax', 'ajax_log_presales_touch'), 'spam_test' === $reason);
        if ('spam_test' !== $reason) {
            assert_same('New', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage']);
            idlm_test_login(9, array('administrator'), array('manage_options'));
            expect_json_response(array('IDLM_Admin_Ajax', 'ajax_log_presales_touch'), true);
        }
        $saved = $GLOBALS['wpdb']->rows('idlm_leads')[0];
        assert_same('Unqualified', $saved['lead_quality']);
        assert_same('Lost', $saved['stage']);
        assert_same($reason, $saved['disqualification_reason']);
        assert_true(isset(IDLM_State_Registry::disqualification_reasons()[$reason]));
    }
    foreach (array('unable_to_contact', 'legacy_unqualified', 'invented') as $reason) {
        idlm_test_reset_environment();
        idlm_test_login(9, array('administrator'), array('manage_options'));
        $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture()));
        $_POST = array('lead_id' => 1, 'kind' => 'early_close', 'reason' => $reason, 'note' => 'Synthetic documented evidence');
        expect_json_response(array('IDLM_Admin_Ajax', 'ajax_log_presales_touch'), false);
        assert_same('New', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage']);
    }
    foreach (array_keys(IDLM_State_Registry::early_close_reasons()) as $reason) {
        assert_true(isset(IDLM_State_Registry::disqualification_reasons()[$reason]));
    }
});

idlm_test('CORE013 sales stages preserve undecided quality and Qualified alone promotes it', static function(): void {
    foreach (array('Visited Studio', 'Quote Shared', 'Negotiation', 'Won', 'Qualified') as $stage) {
        idlm_test_reset_environment();
        $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('project_name' => '', 'budget' => '1 lakh'))));
        $data = array('stage' => $stage, 'quote_shared' => 1, 'quote_value' => 125000, 'deal_value' => 100000);
        assert_same('Qualified' !== $stage, IDLM_DB::update_lead(1, $data));
        assert_same('', $GLOBALS['wpdb']->rows('idlm_leads')[0]['lead_quality']);
        if ('Qualified' === $stage) { assert_same('New', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage']); }
        if ('Visited Studio' === $stage) {
            assert_true(IDLM_DB::create_presales_followup_tasks(1, 7));
            assert_count_value(6, $GLOBALS['wpdb']->rows('idlm_tasks'));
        }
    }
    idlm_test_reset_environment();
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('stage' => 'Visited'))));
    assert_true(IDLM_DB::create_presales_followup_tasks(1, 7));
    assert_count_value(0, $GLOBALS['wpdb']->rows('idlm_tasks'));
});

idlm_test('CORE013 downstream entry revalidates supplied quality but stage-only moves retain prior decisions', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('stage' => 'Contacted', 'lead_quality' => 'Qualified', 'budget' => '1 lakh'))));
    assert_false(IDLM_DB::update_lead(1, array('stage' => 'Negotiation', 'lead_quality' => 'Qualified')));
    assert_contains('minimum project budget', IDLM_DB::get_last_validation_error());
    assert_same('Contacted', $GLOBALS['wpdb']->rows('idlm_leads')[0]['stage']);
    assert_true(IDLM_DB::update_lead(1, array('stage' => 'Negotiation')));
    assert_same('Qualified', $GLOBALS['wpdb']->rows('idlm_leads')[0]['lead_quality']);
});

idlm_test('CORE013 custom stages and Won Lost reversals keep cleanup and immutable events', static function(): void {
    update_option('idlm_custom_stages', array('Design Review'));
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture()));
    assert_true(IDLM_DB::update_lead(1, array('stage' => 'Design Review')));
    assert_false(IDLM_DB::update_lead(1, array('stage' => 'Unconfigured')));
    assert_true(IDLM_DB::update_lead(1, array('stage' => 'Won', 'deal_value' => 100000)));
    assert_true(IDLM_DB::update_lead(1, array('stage' => 'Negotiation')));
    assert_same(null, $GLOBALS['wpdb']->rows('idlm_leads')[0]['conversion_time']);
    assert_true(IDLM_DB::update_lead(1, array('stage' => 'Lost', 'lost_reason' => 'Other')));
    assert_true(IDLM_DB::update_lead(1, array('stage' => 'Design Review')));
    assert_same('', $GLOBALS['wpdb']->rows('idlm_leads')[0]['lost_reason']);
    assert_count_value(5, $GLOBALS['wpdb']->rows('idlm_lead_state_events'));
});
