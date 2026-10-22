<?php
/** QUO-009 shared presentation defaults. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }
idlm_test('QUO009 normalizes supported paper aliases and rejects malformed values', static function(): void {
    foreach (array('A4'=>'A4','a4'=>'A4',' Letter '=>'Letter','letter'=>'Letter','LETTER'=>'Letter','A3'=>'A3','a3'=>'A3','legal'=>'Legal','Legal'=>'Legal') as $input=>$expected) {
        assert_same($expected, IDLM_Quotations::normalize_paper_size($input));
    }
    foreach (array(null, false, 4, array('Letter'), new stdClass(), '', 'A5', 'Letter; color:red') as $input) {
        assert_same('A4', IDLM_Quotations::normalize_paper_size($input));
    }
});
idlm_test('QUO009 fresh and malformed options share warning-free print and PDF defaults without option writes', static function(): void {
    $settings = new ReflectionMethod(IDLM_Quotations::class, 'settings');
    $paper = new ReflectionMethod(IDLM_Quotation_Exporter::class, 'paper_size');
    set_error_handler(static function($severity, $message): void { throw new RuntimeException($message); });
    try {
        foreach (array(false, array(), array('paper_size'=>null), array('paper_size'=>array('Letter')), array('paper_size'=>'letter')) as $option) {
            update_option('idlm_quote_settings', $option);
            $expected = is_array($option) && ($option['paper_size'] ?? '') === 'letter' ? 'Letter' : 'A4';
            assert_same($expected, $settings->invoke(null)['paper_size']);
            assert_same($expected, $paper->invoke(null));
            assert_same($option, get_option('idlm_quote_settings'));
        }
    } finally { restore_error_handler(); }
});
