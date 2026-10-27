<?php
/** Catalogue CSV validation and rollback regressions. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }
function idlm_catalogue_csv_fixture(array $rows, $callback) {
    $path = tempnam(sys_get_temp_dir(), 'idlm_catalogue_');
    $file = fopen($path, 'w');
    fputcsv($file, array('item_code','category','item_name','description','unit','is_wood','default_rate','status'), ',', '"', '');
    foreach ($rows as $row) { fputcsv($file, $row, ',', '"', ''); }
    fclose($file);
    try { return $callback($path); } finally { unlink($path); }
}
idlm_test('Catalogue CSV accepts grouped rates without changing quote decimal policy', static function(): void {
    foreach (array('1,790.00'=>179000, '1,00,000.00'=>10000000, '12,34,567.89'=>123456789, '1790.00'=>179000) as $text=>$expected) {
        assert_same($expected, IDLM_Quotation_Catalogue_Import::rate_units($text));
    }
    foreach (array('1,79.00', '17,90', '1,2,3', '-1,790', '1e3') as $text) { assert_true(is_wp_error(IDLM_Quotation_Catalogue_Import::rate_units($text))); }
    assert_true(is_wp_error(IDLM_Quotation_Calculator::decimal_units('1,790.00', 0, 999999999999)));
});
idlm_test('Catalogue CSV preview reports every row and writes nothing', static function(): void {
    $rows = array(
        array('CODE','Kitchen','Cabinet','Text, with commas','Sq Ft','Yes','1,790.00','active'),
        array('CODE','Kitchen','Duplicate','','Sq Ft','No','990','active'),
        array('','Kitchen','Bad rate','','Sq Ft','Yes','1,79.00','active'),
        array('','Kitchen','New item','','pcs','No','750','inactive'),
        array('','', 'No category','','pcs','No','0','active'),
    );
    idlm_catalogue_csv_fixture($rows, static function($path): void {
        $report = IDLM_Quotation_Catalogue_Import::inspect($path);
        assert_same(5,$report['total']); assert_same(2,$report['valid']); assert_same(2,$report['invalid']); assert_same(1,$report['duplicates']);
        assert_same(4,$report['rows'][2]['row']); assert_contains('thousands grouping',implode(' ',$report['rows'][2]['messages']));
        assert_same('1790.00',$report['rows'][0]['data']['default_rate']);
        assert_count_value(0,$GLOBALS['wpdb']->rows('idlm_quote_catalogue_items'));
        assert_count_value(0,$GLOBALS['wpdb']->rows('idlm_quote_item_categories'));
        $result = IDLM_Quotation_Catalogue_Import::import_file($path);
        assert_same(2,$result['imported']); assert_same('imported',$result['rows'][0]['state']);
        $saved=$GLOBALS['wpdb']->rows('idlm_quote_catalogue_items'); assert_count_value(2,$saved);
        assert_same('inactive',$saved[1]['status']); assert_contains('ITEM-',$saved[1]['item_code']);
        assert_same(1,IDLM_Quotation_Catalogue_Import::inspect($path)['valid']); // Blank code remains a new item on reimport.
    });
});
idlm_test('Catalogue CSV failed item and term writes roll back items and terms', static function(): void {
    foreach (array('item','term') as $fault) {
        $GLOBALS['wpdb']->reset();
        if ($fault === 'item') { $GLOBALS['wpdb']->failed_writes[] = 'insert:wp_idlm_quote_catalogue_items'; }
        else { $GLOBALS['wpdb']->query_fail_contains = 'INSERT IGNORE INTO wp_idlm_quote_item_units'; }
        idlm_catalogue_csv_fixture(array(array('','Kitchen','Cabinet','','Sq Ft','Yes','1,790.00','active')), static function($path): void {
            $result=IDLM_Quotation_Catalogue_Import::import_file($path);
            assert_true(is_wp_error($result)); assert_contains('no items were imported',$result->get_error_message());
            assert_count_value(0,$GLOBALS['wpdb']->rows('idlm_quote_catalogue_items'));
            assert_count_value(0,$GLOBALS['wpdb']->rows('idlm_quote_item_categories'));
        });
    }
});
idlm_test('Catalogue CSV rejects missing headers and oversized files before import writes', static function(): void {
    $path=tempnam(sys_get_temp_dir(),'idlm_catalogue_');
    try {
        file_put_contents($path,"item_code,item_name\nA,Name\n");
        assert_contains('category',IDLM_Quotation_Catalogue_Import::inspect($path)->get_error_message());
        file_put_contents($path,"\xEF\xBB\xBFitem_code,category,item_name\nA,Kitchen,Name\n");
        assert_same(1,IDLM_Quotation_Catalogue_Import::inspect($path)['valid']);
        file_put_contents($path,"item_code,category,item_name\n" . str_repeat("A,Kitchen,Name\n",2001));
        assert_true(is_wp_error(IDLM_Quotation_Catalogue_Import::import_file($path)));
        assert_count_value(0,$GLOBALS['wpdb']->rows('idlm_quote_catalogue_items'));
    } finally { unlink($path); }
});
