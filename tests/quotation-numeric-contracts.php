<?php
/** QUO-008 finite/range/rounding contracts. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

function idlm_numeric_row(array $extra=array()): array { return array_merge(array('description'=>'Synthetic numeric row','quantity'=>'1','unit_rate'=>'100'),$extra); }

idlm_test('REL007 calculator preserves literal backslashes in already-unslashed item text', static function(): void {
    $text = "Cabinet C:\\plans\\oak; O'Brien's panel";
    $rows = IDLM_Quotation_Calculator::parse_items(array(
        array('row_type' => 'header', 'description' => $text),
        idlm_numeric_row(array('description' => $text, 'item_name' => $text, 'specification' => $text)),
    ));
    assert_false(is_wp_error($rows));
    assert_same($text, $rows[0]['description']);
    assert_same($text, $rows[1]['description']);
    assert_same($text, $rows[1]['item_name']);
    assert_same($text, $rows[1]['specification']);
    assert_same(100.0, $rows[1]['line_subtotal']);
});

idlm_test('REL007 calculator accepts unslashed section names without removing literal text', static function(): void {
    $text = "O'Brien's C:\\plans\\living";
    $sections = IDLM_Quotation_Calculator::parse_sections(array(array('key' => 'living', 'section_name' => $text)));
    assert_false(is_wp_error($sections));
    assert_same($text, $sections['living']['section_name']);
});

idlm_test('QUO008 rejects nonfinite exponent malformed negative and over-range monetary inputs', static function(): void {
    foreach(array('1e309','1e2','INF','NaN','-1','1x',array('1'),true,INF,NAN) as $bad) {
        foreach(array('length','height','quantity','unit_rate') as $field) { assert_true(is_wp_error(IDLM_Quotation_Calculator::parse_items(array(idlm_numeric_row(array($field=>$bad))))),'Accepted '.$field); }
        foreach(array('wooden_discount_percent','additional_discount_amount') as $field) { assert_true(is_wp_error(IDLM_Quotation_Calculator::calculate_totals(array(),array($field=>$bad)))); }
        assert_true(is_finite(IDLM_Quotation_Calculator::clean_decimal($bad)));
    }
    foreach(array('quantity'=>'1000000000','length'=>'1000000000','unit_rate'=>'10000000000') as $field=>$bad) { assert_true(is_wp_error(IDLM_Quotation_Calculator::parse_items(array(idlm_numeric_row(array($field=>$bad)))))); }
    assert_true(is_wp_error(IDLM_Quotation_Calculator::parse_items(array(idlm_numeric_row(array('unit_rate'=>'9999999999.995'))))));
    assert_true(is_wp_error(IDLM_Quotation_Calculator::calculate_totals(array(),array('wooden_discount_percent'=>'100.01'))));
    assert_true(is_wp_error(IDLM_Quotation_Calculator::calculate_totals(array(),array(),array('soft_close_percent'=>'101'))));
});
idlm_test('QUO008 rounds decimal text half up without binary-float or exponent interpretation', static function(): void {
    foreach(array('0.005'=>1,'1.005'=>101,'2.675'=>268,'1.335'=>134,'9999999999.99'=>999999999999,'000.01'=>1,' .015 '=>2,'+1.00'=>100) as $value=>$expected) { assert_same($expected,IDLM_Quotation_Calculator::decimal_units($value,0,IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS)); }
    assert_true(is_wp_error(IDLM_Quotation_Calculator::decimal_units(str_repeat('0',129),0,10000)));
});
idlm_test('QUO008 tiny billable areas price the exact normalized minimum quantity', static function(): void {
    $items=IDLM_Quotation_Calculator::parse_items(array(idlm_numeric_row(array('length'=>'.01','height'=>'.01')),idlm_numeric_row(array('quantity'=>'.15','unit_rate'=>'.10')),idlm_numeric_row(array('quantity'=>'1.335','unit_rate'=>'999.995'))));
    assert_same(0.01,$items[0]['quantity']);assert_same(1.0,$items[0]['line_subtotal']);
    assert_same(0.02,$items[1]['line_subtotal']);assert_same(1.34,$items[2]['quantity']);assert_same(1000.0,$items[2]['unit_rate']);assert_same(1340.0,$items[2]['line_subtotal']);
    assert_true(is_wp_error(IDLM_Quotation_Calculator::parse_items(array(idlm_numeric_row(array('length'=>'999999999.99','height'=>'2'))))));
    assert_true(is_wp_error(IDLM_Quotation_Calculator::parse_items(array(idlm_numeric_row(array('quantity'=>'2','unit_rate'=>'9999999999.99'))))));
});
idlm_test('QUO008 adds rounded line cents and preserves optional wood discount GST and round-off semantics', static function(): void {
    $items=IDLM_Quotation_Calculator::parse_items(array(idlm_numeric_row(array('unit_rate'=>'1.005','is_wood'=>'1')),idlm_numeric_row(array('unit_rate'=>'1.005','is_wood'=>'1')),idlm_numeric_row(array('unit_rate'=>'1.005')),idlm_numeric_row(array('unit_rate'=>'9999999999.99','is_optional'=>'1'))));
    $t=IDLM_Quotation_Calculator::calculate_totals($items,array('wooden_discount_percent'=>'12.5','soft_close_enabled'=>'1','additional_discount_amount'=>'.025'),array('soft_close_percent'=>'10'));
    assert_same(3.03,$t['subtotal']);assert_same(2.02,$t['wooden_subtotal']);assert_same(.25,$t['wooden_discount_amount']);assert_same(.18,$t['soft_close_amount']);assert_same(.03,$t['additional_discount_amount']);assert_same(2.93,$t['taxable_total']);assert_same(.27,$t['cgst_total']);assert_same(.26,$t['sgst_total']);assert_same(3.0,$t['grand_total']);assert_same(-.46,$t['round_off_amount']);
    $all=IDLM_Quotation_Calculator::calculate_totals($items,array('additional_discount_amount'=>'9999999999.99'));
    assert_same(3.03,$all['additional_discount_amount']);assert_same(0.0,$all['grand_total']);
});
idlm_test('QUO008 subtotal soft-close GST and final rounding must fit the lead projection', static function(): void {
    $valid=IDLM_Quotation_Calculator::parse_items(array(idlm_numeric_row(array('unit_rate'=>'8474576270'))));
    assert_same(9999999999.0,IDLM_Quotation_Calculator::calculate_totals($valid,array())['grand_total']);
    foreach(array(array(idlm_numeric_row(array('unit_rate'=>'8474576271'))),array(idlm_numeric_row(array('unit_rate'=>'9999999999.99')),idlm_numeric_row(array('unit_rate'=>'.01')))) as $rows) { assert_true(is_wp_error(IDLM_Quotation_Calculator::calculate_totals(IDLM_Quotation_Calculator::parse_items($rows),array()))); }
    $wood=IDLM_Quotation_Calculator::parse_items(array(idlm_numeric_row(array('unit_rate'=>'6000000000','is_wood'=>'1'))));
    assert_true(is_wp_error(IDLM_Quotation_Calculator::calculate_totals($wood,array('soft_close_enabled'=>'1'),array('soft_close_percent'=>'100'))));
});
idlm_test('QUO008 limits all rows including optional headers and blank rows and bounds section shape', static function(): void {
    foreach(array(idlm_numeric_row(),array('row_type'=>'header','description'=>'Header'),array('description'=>'')) as $row) {
        assert_false(is_wp_error(IDLM_Quotation_Calculator::parse_items(array_fill(0,200,$row))));
        assert_true(is_wp_error(IDLM_Quotation_Calculator::parse_items(array_fill(0,201,$row))));
    }
    assert_true(is_wp_error(IDLM_Quotation_Calculator::parse_items(array('malformed'))));
    $sections=array();for($i=0;$i<50;$i++){$sections[]=array('key'=>'section-'.$i,'section_name'=>'Section '.$i);}
    assert_count_value(50,IDLM_Quotation_Calculator::parse_sections($sections));$sections[]=$sections[0];assert_true(is_wp_error(IDLM_Quotation_Calculator::parse_sections($sections)));
    assert_true(is_wp_error(IDLM_Quotation_Calculator::parse_sections(array('malformed'))));
});
