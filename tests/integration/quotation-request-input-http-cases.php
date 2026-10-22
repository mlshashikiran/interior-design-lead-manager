<?php
/** REL-007 quotation settings/catalogue request validation. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

idlm_http_case('T11-H-REL007-malformed-payment-names-reject-without-settings-writes', static function(): void {
    global $wpdb, $sessions;
    $before = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'idlm_quote_settings'");
    $result = idlm_http_request('/wp-admin/admin-post.php', array('action' => 'idlm_save_quote_settings', '_wpnonce' => $sessions['admin']['quote_settings'], 'payment_structure' => array(array('name' => array('Nested'), 'percentage' => '50'))), 'admin');
    idlm_http_check($result['status'] === 302 && strpos($result['headers'], 'idlm_error=') !== false, 'Malformed milestone was accepted');
    idlm_http_check($before === $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'idlm_quote_settings'"), 'Rejected milestone changed settings');
});

idlm_http_case('T11-H-REL007-array-wood-flag-rejects-before-catalogue-writes', static function(): void {
    global $sessions;
    IDLM_Quotation_Repository::ensure_catalogue_term('category', 'REL007 request category', get_current_user_id(), current_time('mysql'));
    IDLM_Quotation_Repository::ensure_catalogue_term('unit', 'REL007 request unit', get_current_user_id(), current_time('mysql'));
    $before = idlm_http_snapshot();
    $result = idlm_http_request('/wp-admin/admin-post.php', array('action' => 'idlm_save_catalogue_item', '_wpnonce' => $sessions['admin']['catalogue_save'], 'item_name' => 'Malformed wood flag', 'category' => 'REL007 request category', 'unit' => 'REL007 request unit', 'default_rate' => '10.005', 'is_wood' => array('1')), 'admin');
    idlm_http_check($result['status'] === 302 && strpos($result['headers'], 'idlm_error=') !== false, 'Array wood flag was accepted');
    idlm_http_check($before === idlm_http_snapshot(), 'Rejected flag changed catalogue state');
});

idlm_http_case('T11-H-REL007-settings-retain-literal-milestone-text-and-validated-values', static function(): void {
    global $wpdb, $sessions;
    $before = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'idlm_quote_settings'");
    try {
        $name = "O'Brien's C:\\plans\\oak <b>deposit</b>";
        $result = idlm_http_request('/wp-admin/admin-post.php', array('action' => 'idlm_save_quote_settings', '_wpnonce' => $sessions['admin']['quote_settings'], 'payment_structure' => array(array('name' => $name, 'percentage' => '33.335')), 'soft_close_percent' => '1.005', 'currency_code' => 'usd', 'number_separator' => '/', 'paper_size' => 'letter'), 'admin');
        $settings = maybe_unserialize($wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'idlm_quote_settings'"));
        $milestones = json_decode($settings['payment_structure'], true);
        idlm_http_check($result['status'] === 302 && strpos($result['headers'], 'idlm_notice=') !== false, 'Valid settings save failed');
        idlm_http_check(count($milestones) === 1 && $milestones[0]['name'] === "O'Brien's C:\\plans\\oak deposit" && $milestones[0]['percentage'] === 33.34, 'Milestone text/rounding changed');
        idlm_http_check($settings['currency_code'] === 'USD' && $settings['number_separator'] === '/' && $settings['paper_size'] === 'Letter' && $settings['soft_close_percent'] === 1.01, 'Validated settings drifted');
    } finally {
        wp_cache_flush();
        if ($before === null) { delete_option('idlm_quote_settings'); } else { update_option('idlm_quote_settings', maybe_unserialize($before), false); }
    }
});

idlm_http_case('T11-H-REL007-valid-catalogue-classification-text-and-rate-persist', static function(): void {
    global $wpdb, $sessions;
    foreach (array('0', '1') as $wood) {
        $name = 'REL007 valid wood ' . $wood;
        $text = "O'Brien's C:\\plans\\oak";
        $result = idlm_http_request('/wp-admin/admin-post.php', array('action' => 'idlm_save_catalogue_item', '_wpnonce' => $sessions['admin']['catalogue_save'], 'item_name' => $name, 'description' => $text, 'category' => 'REL007 request category', 'unit' => 'REL007 request unit', 'default_rate' => '10.005', 'is_wood' => $wood), 'admin');
        $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_catalogue_items WHERE item_name = %s", $name));
        idlm_http_check($result['status'] === 302 && strpos($result['headers'], 'idlm_notice=') !== false && $item && (string) $item->is_wood === $wood && $item->default_rate === '10.01' && $item->description === $text, 'Valid catalogue item drifted');
    }
});

idlm_http_case('T11-H-REL007-structured-and-literal-backslash-decimals-remain-invalid', static function(): void {
    global $wpdb, $sessions;
    $before = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'idlm_quote_settings'");
    foreach (array(array('50'), '1\\0', '1e2', '100.01') as $bad) {
        $result = idlm_http_request('/wp-admin/admin-post.php', array('action' => 'idlm_save_quote_settings', '_wpnonce' => $sessions['admin']['quote_settings'], 'payment_structure' => array(array('name' => 'Deposit', 'percentage' => $bad))), 'admin');
        idlm_http_check($result['status'] === 302 && strpos($result['headers'], 'idlm_error=') !== false && $before === $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'idlm_quote_settings'"), 'Invalid decimal changed settings');
    }
});
