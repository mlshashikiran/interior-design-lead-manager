<?php
/** QUO-008 native decimal persistence and historic preservation. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }

idlm_integration_test('T10-T12-N-QUO008-half-cent-tiny-area-and-limit-values-persist-exactly-without-altering-history',static function(): void {
    global $wpdb;
    $fixture=idlm_quo_migration_fixture();$before=idlm_quo_snapshot($fixture);$now=current_time('mysql');
    foreach(array(array('length'=>'.01','height'=>'.01','quantity'=>'7','unit_rate'=>'100'),array('quantity'=>'.15','unit_rate'=>'.10'),array('quantity'=>'1.335','unit_rate'=>'999.995'),array('quantity'=>'1','unit_rate'=>'8474576270')) as $case) {
        $rows=IDLM_Quotation_Calculator::parse_items(array(array_merge(array('description'=>'Synthetic decimal snapshot'),$case)));
        idlm_integration_assert_true(!is_wp_error($rows));$totals=IDLM_Quotation_Calculator::calculate_totals($rows,array());idlm_integration_assert_true(!is_wp_error($totals));
        $name='Numeric-'.wp_generate_uuid4();
        $id=IDLM_Quotation_Repository::insert_revision(array_merge($totals,array('quote_id'=>$fixture[1],'revision_name'=>$name,'revision_number'=>100+wp_rand(1,100000),'created_by'=>0,'created_at'=>$now,'updated_by'=>0,'updated_at'=>$now)));
        idlm_integration_assert_true($id>0);idlm_integration_assert_same(true,IDLM_Quotation_Repository::replace_revision_contents($id,array('general'=>array('section_name'=>'General','sort_order'=>0)),$rows));
        $saved=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_revision_items WHERE revision_id=%d",$id),ARRAY_A);
        foreach(array('length','height','quantity') as $key){idlm_integration_assert_same(number_format($rows[0][$key],3,'.',''),$saved[$key]);}
        foreach(array('unit_rate','line_subtotal','line_total') as $key){idlm_integration_assert_same(number_format($rows[0][$key],2,'.',''),$saved[$key]);}
        $sql=$wpdb->get_var($wpdb->prepare('SELECT ROUND(quantity * unit_rate,2) FROM '.$wpdb->prefix.'idlm_quote_revision_items WHERE revision_id=%d',$id));
        idlm_integration_assert_same($saved['line_subtotal'],$sql,'Persisted quantity/rate do not reproduce price');
        idlm_integration_assert_true(false!==IDLM_Quotation_Repository::update_lead_quote_value($fixture[0],$totals['grand_total'],$now));
        idlm_integration_assert_same(number_format($totals['grand_total'],2,'.',''),$wpdb->get_var($wpdb->prepare("SELECT quote_value FROM {$wpdb->prefix}idlm_leads WHERE id=%d",$fixture[0])));
        idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
    }
});
idlm_integration_test('T10-N-QUO008-invalid-numbers-are-rejected-under-strict-and-permissive-SQL-modes',static function():void {
    global $wpdb;$mode=$wpdb->get_var('SELECT @@SESSION.sql_mode');$fixture=idlm_quo_migration_fixture();$before=idlm_quo_snapshot($fixture);
    try {
        foreach(array('','STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION') as $sql_mode) {
            $wpdb->query($wpdb->prepare('SET SESSION sql_mode=%s',$sql_mode));
            foreach(array('1e309','-1','INF','9999999999.995',array('100')) as $bad) {
                $r=IDLM_Quotation_Calculator::parse_items(array(array('description'=>'Synthetic invalid','unit_rate'=>$bad)));
                idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
            }
            $r=IDLM_Quotation_Calculator::calculate_totals(IDLM_Quotation_Calculator::parse_items(array(array('description'=>'Rounding overflow','unit_rate'=>'8474576271'))),array());
            idlm_integration_assert_true(is_wp_error($r));idlm_integration_assert_same($before,idlm_quo_snapshot($fixture));
        }
    } finally {$wpdb->query($wpdb->prepare('SET SESSION sql_mode=%s',$mode));}
});
idlm_integration_test('T10-T12-N-QUO008-rounded-lines-optional-rows-and-GST-split-match-independent-decimal-SQL',static function():void {
    global $wpdb;
    $rows=IDLM_Quotation_Calculator::parse_items(array(array('description'=>'Wood A','unit_rate'=>'1.005','is_wood'=>'1'),array('description'=>'Wood B','unit_rate'=>'1.005','is_wood'=>'1'),array('description'=>'Other','unit_rate'=>'1.005'),array('description'=>'Optional','unit_rate'=>'9999999999.99','is_optional'=>'1')));
    $t=IDLM_Quotation_Calculator::calculate_totals($rows,array('wooden_discount_percent'=>'12.5','soft_close_enabled'=>'1','additional_discount_amount'=>'.025'),array('soft_close_percent'=>'10'));
    $expected=$wpdb->get_row('SELECT ROUND(1.005,2)*3 AS subtotal, ROUND(2.02*0.125,2) AS wooden_discount, ROUND(1.77*0.10,2) AS soft_close, ROUND(2.93*0.18,2) AS tax',ARRAY_A);
    foreach(array('subtotal'=>'subtotal','wooden_discount_amount'=>'wooden_discount','soft_close_amount'=>'soft_close') as $key=>$column){idlm_integration_assert_same((float)$expected[$column],$t[$key]);}
    idlm_integration_assert_same((float)$expected['tax'],$t['cgst_total']+$t['sgst_total']);idlm_integration_assert_same(3.0,$t['grand_total']);
});
