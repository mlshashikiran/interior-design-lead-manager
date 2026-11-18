<?php
/** An open CRM tab learns the plugin was updated and offers a reload. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

idlm_test('UPDATE-NOTICE the version endpoint answers any signed-in CRM page and refuses a stale token', static function(): void {
    idlm_test_login(30, array('idlm_design'), array());
    $response = expect_json_response(array('IDLM_Admin_Ajax', 'ajax_app_version'), true);
    assert_same(IDLM_VERSION, $response->data['version'], 'Design users read it too.');
    $GLOBALS['idlm_test_nonce_valid'] = false;
    expect_json_response(array('IDLM_Admin_Ajax', 'ajax_app_version'), false, 403);
    $GLOBALS['idlm_test_nonce_valid'] = true;
});

idlm_test('UPDATE-NOTICE pages carry the running version and check it while visible', static function(): void {
    $root = dirname(__DIR__);
    assert_contains("'plugin_version' => IDLM_VERSION", (string) file_get_contents($root . '/includes/class-admin-assets.php'));
    $js = (string) file_get_contents($root . '/assets/js/admin-app.js');
    assert_contains("action: 'idlm_app_version'", $js);
    assert_contains('response.data.version !== idlm_vars.plugin_version', $js);
    assert_contains("{action: {label: 'Reload', onClick: reloadPage}}", $js);
    assert_contains('window.idlmHasUnsavedChanges', $js, 'Reloading asks first when edits are unsaved.');
    assert_contains('document.hidden', $js, 'Hidden tabs do not poll.');
});
