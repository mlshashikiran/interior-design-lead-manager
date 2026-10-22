<?php
/** QUO-007 engine gates and transaction ownership. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

idlm_test('QUO007 write gates reject each unsupported or missing dependency before mutations', static function(): void {
    foreach (array('save'=>array('quote_item_categories','quote_item_units','quote_catalogue_items','quotes','quote_revisions','quote_revision_sections','quote_revision_items','quote_payment_milestones','quote_status_history','number_sequences','leads','lead_activity_log'), 'working_copy'=>array('quotes','quote_revisions','quote_status_history','leads'), 'catalogue'=>array('quote_item_categories','quote_item_units','quote_catalogue_items'), 'number'=>array('number_sequences')) as $scope=>$suffixes) {
        $tables=array_map(static function($s){return 'wp_idlm_'.$s;},$suffixes);
        if (in_array($scope,array('save','working_copy'),true)) { $tables[]='wp_options'; }
        foreach($tables as $table) {
            foreach(array('MyISAM',null) as $engine) {
                $GLOBALS['wpdb']->schema_engines=array($table=>$engine);
                $result=IDLM_Quotation_Repository::begin_checked_transaction($scope);
                assert_same('idlm_quote_transaction_engine',$result->get_error_code());
                assert_same('ROLLBACK',end($GLOBALS['wpdb']->queries));
            }
        }
    }
    $GLOBALS['wpdb']->schema_engines=array();
    assert_same(true,IDLM_Quotation_Repository::begin_checked_transaction());
    assert_true(in_array('SET TRANSACTION ISOLATION LEVEL READ COMMITTED',$GLOBALS['wpdb']->queries,true));
    $GLOBALS['wpdb']->query('ROLLBACK');
});
idlm_test('QUO007 refuses foreign ownership without committing or rolling back its work', static function(): void {
    $db=$GLOBALS['wpdb']; $db->query('START TRANSACTION');
    $db->insert('wp_idlm_leads',array('first_name'=>'Owned pending work'));
    $before=count($db->queries);
    assert_same('idlm_quote_transaction_owner',IDLM_Quotation_Repository::begin_checked_transaction()->get_error_code());
    assert_false(in_array('START TRANSACTION',array_slice($db->queries,$before),true));
    assert_false(in_array('ROLLBACK',array_slice($db->queries,$before),true));
    assert_count_value(1,$db->rows('idlm_leads'));$db->query('ROLLBACK');assert_count_value(0,$db->rows('idlm_leads'));
});
idlm_test('QUO007 number reservation and failed metadata pins leave numbering untouched', static function(): void {
    $db=$GLOBALS['wpdb'];$db->schema_engines['wp_idlm_number_sequences']='MyISAM';
    assert_true(is_wp_error(IDLM_Quotation_Repository::reserve_quote_number(array())));
    assert_count_value(0,$db->rows('idlm_number_sequences'));
    $db->schema_engines=array();$db->query_fail_contains='idlm_quotation_engine_pin';
    assert_true(is_wp_error(IDLM_Quotation_Repository::begin_checked_transaction()));
    assert_same('ROLLBACK',end($db->queries));$db->query_fail_contains='';
    assert_same(true,IDLM_Quotation_Repository::begin_checked_transaction());
    assert_true(is_string(IDLM_Quotation_Repository::reserve_quote_number(array(),false)));
    $db->query('ROLLBACK');assert_count_value(0,$db->rows('idlm_number_sequences'));
});
