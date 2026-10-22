<?php
/** REL-003 real public rendering and administrator opt-in. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

function idlm_attribution_http_nonce(): string {
    $r = idlm_http_request('/settings-workspace?tab=white-label', array(), 'admin', false);
    idlm_http_check($r['status'] === 200, 'Settings unavailable');
    preg_match('/name="_wpnonce" value="([^"]+)"/', $r['body'], $m);
    idlm_http_check(!empty($m[1]), 'Settings nonce missing');
    return $m[1];
}

function idlm_attribution_http_public(bool $enabled): void {
    foreach (array('anonymous', 'owner') as $actor) {
        $r = idlm_http_request('/crm-workspace', array(), $actor, false);
        idlm_http_check($r['status'] === 200, 'CRM render failed for ' . $actor);
        $present = strpos($r['body'], 'Powered by Interior Design Lead Manager') !== false;
        idlm_http_check($present === $enabled, 'Credit state wrong for ' . $actor);
    }
}

idlm_http_case('T01-T19-H-REL003 fresh sign-in workspace and checkbox default to no credit', static function(): void {
    global $wpdb;
    delete_option(IDLM_Settings::WHITE_LABEL_OPTION);
    idlm_attribution_http_public(false);
    $r = idlm_http_request('/settings-workspace?tab=white-label', array(), 'admin', false);
    $dom = new DOMDocument(); @$dom->loadHTML($r['body']); $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//input[@name="white_label[show_plugin_attribution]"]');
    idlm_http_check($nodes->length === 1 && !$nodes->item(0)->hasAttribute('checked'), 'Default checkbox checked');
    idlm_http_check($nodes->item(0)->getAttribute('aria-describedby') === 'idlm-attribution-help', 'Optional help unlinked');
    idlm_http_check(strpos($r['body'], 'Optional public credit, off by default.') !== false, 'Opt-in explanation absent');
    idlm_http_check(null === $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", IDLM_Settings::WHITE_LABEL_OPTION)), 'Rendering persisted an implicit preference');
});

idlm_http_case('T20-H-REL003 upgrade retains saved choices and partial branding stays off', static function(): void {
    global $wpdb;
    foreach (array('0', '1', null) as $flag) {
        $saved = array('brand_name' => 'Synthetic Upgrade Studio', 'footer_text' => 'Synthetic custom footer');
        if (null !== $flag) { $saved['show_plugin_attribution'] = $flag; }
        update_option(IDLM_Settings::WHITE_LABEL_OPTION, $saved, false);
        $before = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", IDLM_Settings::WHITE_LABEL_OPTION));
        idlm_attribution_http_public($flag === '1');
        $after = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", IDLM_Settings::WHITE_LABEL_OPTION));
        idlm_http_check($before === $after, 'Upgrade/render rewrote existing branding');
    }
});

idlm_http_case('T19-T20-H-REL003 authorized opt-in opt-out persists and controls both public views', static function(): void {
    global $wpdb;
    foreach (array('1', null, array('1')) as $flag) {
        $white_label = IDLM_Settings::white_label_defaults(); unset($white_label['show_plugin_attribution']);
        if (null !== $flag) { $white_label['show_plugin_attribution'] = $flag; }
        $r = idlm_http_request('/settings-workspace?tab=white-label', array('idlm_save_settings' => '1', '_wpnonce' => idlm_attribution_http_nonce(), 'white_label' => $white_label), 'admin');
        idlm_http_check($r['status'] === 200, 'Authorized branding save failed');
        $stored = maybe_unserialize($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", IDLM_Settings::WHITE_LABEL_OPTION)));
        idlm_http_check($stored['show_plugin_attribution'] === ($flag === '1' ? '1' : '0'), 'Stored opt-in wrong');
        idlm_attribution_http_public($flag === '1');
    }
});

idlm_http_case('T01-T20-H-REL003 denied actor and nonce cannot change public credit', static function(): void {
    global $wpdb, $config;
    update_option(IDLM_Settings::WHITE_LABEL_OPTION, IDLM_Settings::white_label_defaults(), false);
    $before = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", IDLM_Settings::WHITE_LABEL_OPTION));
    foreach (array(array('owner', idlm_attribution_http_nonce()), array('admin', 'invalid-nonce')) as $denial) {
        $r = idlm_http_request('/settings-workspace', array('idlm_save_settings' => '1', '_wpnonce' => $denial[1], 'white_label' => array_merge(IDLM_Settings::white_label_defaults(), array('show_plugin_attribution' => '1'))), $denial[0]);
        idlm_http_check($r['status'] !== 200, 'Denied branding save succeeded');
        idlm_http_check($before === $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", IDLM_Settings::WHITE_LABEL_OPTION)), 'Denial changed branding');
    }
    idlm_attribution_http_public(false);
    $diagnostics = is_file($config['content_dir'] . '/php-errors.log') ? file_get_contents($config['content_dir'] . '/php-errors.log') : '';
    idlm_http_check(trim($diagnostics) === '', 'Affected requests produced PHP diagnostics: ' . $diagnostics);
});
