<?php
/** CORE-003 shared text normalization contracts. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

idlm_test('lead CSV shared sanitizer protects formula, control and Unicode prefixes without dropping bytes', static function(): void {
    $prefixes = array('=1+1','+1+1','-1+1','@SUM(A1)',"\tPlain","\rPlain","\nPlain"," \t=1+1","\0=1+1","\x1f@SUM(A1)","\x7f-1+1","\u{00a0}=1+1","\u{2003}+1+1","\u{feff}@SUM(A1)","\u{200b}=1+1",'＝1+1','＋1+1','－1+1','＠SUM(A1)',"=1\xff",'="a,b"','=1;"two"');
    foreach ($prefixes as $value) { assert_same("'" . $value, IDLM_Quotation_Exporter::csv_text($value), 'Unsafe cell: ' . bin2hex($value)); }
});
idlm_test('lead CSV normalizer preserves ordinary Unicode, embedded controls, numbers and apostrophes', static function(): void {
    foreach (array('शशि — غرف','A "quoted", value; two',"C:\\synthetic\\","text\r\n=second line","apostrophe'",'8 lakh','560001','3','',"'already text","plain\xff") as $value) {
        assert_same($value, IDLM_Quotation_Exporter::csv_text($value));
        assert_same($value, IDLM_Quotation_Exporter::csv_text(IDLM_Quotation_Exporter::csv_text($value)), 'Normalization not idempotent');
    }
    assert_same("'+91 98765 43210", IDLM_Quotation_Exporter::csv_text('+91 98765 43210'));
});
idlm_test('shared CSV sanitizer keeps separators and backslashes in a single parsed text cell', static function(): void {
    $values = array('="a,b"', '=1;"two"', "C:\\synthetic\\", "text\r\n=second line",'शशि — غرف');
    $safe = array_map(array('IDLM_Quotation_Exporter','csv_text'), $values);
    $out = fopen('php://temp','w+'); fputcsv($out,$safe,',','"',''); rewind($out);
    assert_same($safe, fgetcsv($out,0,',','"','')); assert_same(false, fgetcsv($out,0,',','"','')); fclose($out);
});
