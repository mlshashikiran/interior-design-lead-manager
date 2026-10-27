<?php
/** QUO-005 classification capture and conservative legacy provenance. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

idlm_test('QUO005 submitted classifications ignore forged provenance and distinguish default nonwood', static function(): void {
    $items = IDLM_Quotation_Calculator::parse_items(array(
        array('description'=>'Wood', 'is_wood'=>'1', 'classification_source'=>'legacy_unverified'),
        array('description'=>'Nonwood', 'is_wood'=>'0'),
        array('description'=>'Custom default'),
        array('description'=>'Header', 'row_type'=>'header'),
    ));
    assert_same(array('submitted','submitted','default_nonwood','default_nonwood'), array_column($items, 'classification_source'));
    assert_same(array(1,0,0), array_column($items, 'is_wood'));
});

idlm_test('QUO005 installers never resolve old classifications against the current catalogue', static function(): void {
    foreach (array(false, '2.4.0', '2.5.0', '2.5.1') as $version) {
        idlm_test_reset_environment();
        $GLOBALS['wpdb']->custom_get_var_result = 0;
        if ($version) { update_option('idlm_quotation_schema_version', $version); }
        $GLOBALS['wpdb']->query_fail_contains = 'SET revision_item.is_wood';
        assert_same(true, IDLM_Quotations::install());
        assert_same(true, IDLM_Quotations::install());
    }
});

idlm_test('QUO005 revision copy preserves captured and unverified provenance without catalogue lookup', static function(): void {
    $items = IDLM_Quotation_Calculator::parse_items(array(array('description'=>'Wood', 'is_wood'=>'1')));
    $items[] = array_merge($items[0], array('is_wood'=>0, 'classification_source'=>'legacy_unverified'));
    assert_same(true, IDLM_Quotation_Repository::replace_revision_contents(1, IDLM_Quotation_Calculator::parse_sections(array()), $items));
    assert_same(true, IDLM_Quotation_Repository::copy_revision_contents(1, 2));
    $rows = $GLOBALS['wpdb']->rows('idlm_quote_revision_items');
    assert_same(array('submitted','legacy_unverified','submitted','legacy_unverified'), array_column($rows, 'classification_source'));
    assert_same(array(1,0,1,0), array_column($rows, 'is_wood'));
});
