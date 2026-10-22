<?php
/** REL-003 optional public credit. Builder: Shashi. */
idlm_test('T01-T19-F-REL003 fresh and partial branding default credit off', static function(): void {
    assert_same('0', IDLM_Settings::white_label_defaults()['show_plugin_attribution']);
    assert_same('0', IDLM_Settings::get_white_label()['show_plugin_attribution']);
    update_option(IDLM_Settings::WHITE_LABEL_OPTION, array('brand_name' => 'Synthetic Studio'));
    assert_same('0', IDLM_Settings::get_white_label()['show_plugin_attribution']);
    assert_same('Synthetic Studio', IDLM_Settings::get_white_label()['brand_name']);
});

idlm_test('T20-F-REL003 upgrade preserves saved on and off without rewriting branding', static function(): void {
    foreach (array('0', '1') as $flag) {
        $saved = array('brand_name' => 'Synthetic Studio', 'footer_text' => 'Synthetic footer', 'show_plugin_attribution' => $flag);
        update_option(IDLM_Settings::WHITE_LABEL_OPTION, $saved);
        IDLM_Settings::init();
        assert_same($flag, IDLM_Settings::get_white_label()['show_plugin_attribution']);
        assert_same($saved, get_option(IDLM_Settings::WHITE_LABEL_OPTION));
    }
});

idlm_test('T19-F-REL003 actual sign-in and workspace templates hide default public credit', static function(): void {
    $GLOBALS['idlm_test_logged_in'] = false;
    assert_false(strpos(IDLM_View::render('access-restricted.php'), 'Powered by Interior Design Lead Manager') !== false);
    assert_false(strpos(IDLM_View::render('workspace.php'), 'Powered by Interior Design Lead Manager') !== false);
    update_option(IDLM_Settings::WHITE_LABEL_OPTION, array('show_plugin_attribution' => '1'));
    assert_contains('Powered by Interior Design Lead Manager', IDLM_View::render('access-restricted.php'));
    assert_contains('Powered by Interior Design Lead Manager', IDLM_View::render('workspace.php'));
});

idlm_test('T20-F-REL003 only explicit checkbox value opts in on a settings save', static function(): void {
    $defaults = IDLM_Settings::white_label_defaults();
    foreach (array(null, '', '0', 'yes', array('1')) as $value) {
        $raw = $defaults; unset($raw['show_plugin_attribution']);
        if (null !== $value) { $raw['show_plugin_attribution'] = $value; }
        assert_same('0', IDLM_Settings::validate_white_label($raw)['settings']['show_plugin_attribution']);
    }
    $defaults['show_plugin_attribution'] = '1';
    assert_same('1', IDLM_Settings::validate_white_label($defaults)['settings']['show_plugin_attribution']);
});
