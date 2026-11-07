<?php
/** CAPI review S2: one Meta Graph API version for every plugin request. Builder: Shashi. */
idlm_test('CAPI review S2: plugin Meta requests use the single Graph API version constant', static function(): void {
    assert_same(1, preg_match('/^v[0-9]{2}\.0$/D', IDLM_CAPI_Transport::GRAPH_VERSION));
    $root = dirname(__DIR__);
    $files = array_merge(glob($root . '/includes/*.php'), glob($root . '/templates/*/*.php'), glob($root . '/templates/*/*/*.php'), array($root . '/interior-design-lead-manager.php', $root . '/mcp-server.php'));
    $pinned = array();
    foreach ($files as $file) {
        if (preg_match('~facebook\.com/v[0-9]+\.[0-9]+~', (string) file_get_contents($file))) { $pinned[] = basename($file); }
    }
    assert_same(array(), $pinned, 'Hard-coded Graph API version in: ' . implode(', ', $pinned) . '.');
    $query = '/dialog/oauth?client_id=1&config_id=2&redirect_uri=' . rawurlencode('https://example.test/') . '&response_type=code&state=' . str_repeat('a', 64);
    assert_true(IDLM_Facebook_Connect::valid_authorization_url('https://www.facebook.com/' . IDLM_CAPI_Transport::GRAPH_VERSION . $query));
    assert_false(IDLM_Facebook_Connect::valid_authorization_url('https://www.facebook.com/v1.0' . $query), 'Login must stay on the plugin Graph version.');
});
