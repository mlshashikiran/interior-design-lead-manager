<?php
/** Focused quotation mutation authority contracts. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
idlm_test('quotation read-all scope does not grant mutation authority', static function(): void {
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads', 'idlm_view_quotes', 'idlm_edit_quote_drafts', 'idlm_create_quote_revisions'));
    $GLOBALS['idlm_test_options']['idlm_role_lead_scopes'] = array('sales_executive' => 'all');
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('id'=>1,'assigned_rep_id'=>8)),lead_fixture(array('id'=>2,'assigned_rep_id'=>7))));
    assert_true(IDLM_Quotation_Policy::can_view_lead(1));
    assert_false(IDLM_Quotation_Policy::can_mutate_lead(1));
    assert_true(IDLM_Quotation_Policy::can_mutate_lead(2));
    assert_false(IDLM_Quotation_Policy::can_mutate_lead(99));
});
idlm_test('quotation explicit manager grant survives a narrower read scope', static function(): void {
    idlm_test_login(3,array('sales_manager'),array('idlm_access_leads', 'idlm_view_quotes','idlm_assign_leads'));
    $GLOBALS['idlm_test_options']['idlm_role_lead_scopes']=array('sales_manager'=>'assigned');
    $GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('id'=>1,'assigned_rep_id'=>8))));
    assert_false(IDLM_Quotation_Policy::can_view_lead(1));
    assert_true(IDLM_Quotation_Policy::can_mutate_lead(1));
    $GLOBALS['idlm_test_caps']['idlm_assign_leads']=false;
    assert_false(IDLM_Quotation_Policy::can_mutate_lead(1));
});
idlm_test('quotation all-read working-copy mutation denial has no side effects',static function(): void {
    idlm_test_login(7,array('sales_executive'),array('idlm_access_leads', 'idlm_view_quotes','idlm_edit_quote_drafts','idlm_create_quote_revisions'));
    $GLOBALS['idlm_test_options']['idlm_role_lead_scopes']=array('sales_executive'=>'all');
    $GLOBALS['wpdb']->seed('idlm_leads',array(lead_fixture(array('id'=>1,'assigned_rep_id'=>8))));
    $GLOBALS['wpdb']->seed('idlm_quotes',array(array('id'=>1,'lead_id'=>1,'current_revision_id'=>1,'status'=>'Draft')));
    $GLOBALS['wpdb']->seed('idlm_quote_revisions',array(array('id'=>1,'quote_id'=>1,'status'=>'Draft')));
    $_POST=array('lead_id'=>1,'quote_id'=>1,'revision_id'=>1,'items'=>array(array('description'=>'Synthetic','quantity'=>1,'unit_rate'=>100)));
    $GLOBALS['idlm_test_transients']['idlm_quote_autosave_7_1']=array('synthetic'=>'preserve');
    $before=$GLOBALS['idlm_test_transients'];
    expect_json_response(static fn()=>IDLM_Quotations::ajax_autosave_quote(),false,409);
    expect_json_response(static fn()=>IDLM_Quotations::ajax_discard_quote_autosave(),false,404);
    assert_same($before,$GLOBALS['idlm_test_transients']);
});

idlm_test('REL007 quotation exports reject structured and nondecimal identifiers before SQL', static function(): void {
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads', 'idlm_view_quotes', 'idlm_download_quote_pdf'));
    foreach (array(array(1), (object) array('id' => 1), true, 1.5, -1, '-1', '1.0', '1e0', '') as $bad) {
        foreach (array(array($bad, 1), array(1, $bad)) as $ids) {
            $before = count($GLOBALS['wpdb']->queries);
            $result = IDLM_Quotation_Exporter::authorize($ids[0], $ids[1], 'csv', false);
            assert_true(is_wp_error($result));
            assert_same($before, count($GLOBALS['wpdb']->queries), 'Malformed export ID reached SQL');
        }
    }
});
