<?php
/** Actual bootstrap failure/retry transport regressions. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

idlm_http_case('T20-H-pending-schema-pauses-CRM-AJAX-with-retryable-503', static function (): void {
    global $wpdb, $config, $sessions;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_leads DROP COLUMN assigned_at");
    update_option('idlm_schema_version', '1.3.5', false);
    file_put_contents($config['content_dir'] . '/fail-schema-ddl', 'synthetic failure');
    try {
        $before = idlm_http_snapshot();
        $r = idlm_http_request('/wp-admin/admin-ajax.php', array('action' => 'idlm_get_rep_tasks', 'nonce' => $sessions['owner']['admin']));
        idlm_http_check($r['status'] === 503, 'Pending migration did not return 503: ' . $r['body']);
        idlm_http_check(strpos($r['body'], 'Retry shortly') !== false, 'Missing retry guidance');
        idlm_http_check(strpos($r['body'], 'PII-SYNTHETIC') === false, 'PII disclosed');
        idlm_http_check($before === idlm_http_snapshot(), 'Paused request changed aggregate/jobs/outbound');
        idlm_http_check(IDLM_Schema_Migration::version('idlm_schema_version') === '1.3.5', 'Failed request advanced the marker');
    } finally { unlink($config['content_dir'] . '/fail-schema-ddl'); }
});

idlm_http_case('T20-H-next-request-repairs-schema-and-restores-authorized-CRM-reads', static function (): void {
    global $wpdb;
    $r = idlm_read_ajax('idlm_get_rep_tasks', 'owner');
    idlm_read_data($r);
    idlm_http_check(IDLM_Schema_Migration::version('idlm_schema_version') === IDLM_DB_VERSION, 'Retry did not complete migration');
    idlm_http_check(null !== idlm_integration_http_column($wpdb->prefix . 'idlm_leads', 'assigned_at'), 'Retry failed to repair physical column');
});

function idlm_integration_http_column(string $table, string $column) {
    global $wpdb;
    return $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", $column));
}

idlm_http_case('T20-H-unsupported-engine-pauses-REST-with-no-mutation-or-disclosure', static function (): void {
    global $wpdb;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ENGINE=MyISAM");
    wp_cache_flush(); update_option('idlm_schema_version', '1.3.5', false);
    try {
        $before = idlm_http_snapshot();
        $r = idlm_http_request('/wp-json/interior-design-lead-manager/v1/webhook', array('platform' => 'meta'), 'anonymous');
        idlm_http_check($r['status'] === 503, 'Pending migration did not block REST');
        idlm_http_check(strpos($r['body'], 'PII-SYNTHETIC') === false && strpos($r['body'], 'wp_idlm_tasks') === false, 'Public response disclosed internal data');
        idlm_http_check($before === idlm_http_snapshot(), 'Paused REST request changed aggregate/jobs/outbound');
    } finally { $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ENGINE=InnoDB"); }
});

idlm_http_case('T20-H-schema-notice-is-actionable-for-administrators-and-private-to-other-users', static function (): void {
    global $wpdb;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ENGINE=MyISAM");
    wp_cache_flush(); update_option('idlm_schema_version', '1.3.5', false);
    try {
        $admin_notice = idlm_http_request('/migration-notices', array(), 'admin', false);
        idlm_http_check(strpos($admin_notice['body'], 'InnoDB') !== false && strpos($admin_notice['body'], 'Back up the database') !== false, 'Administrator lacks repair guidance');
        $owner_notice = idlm_http_request('/migration-notices', array(), 'owner', false);
        idlm_http_check(strpos($owner_notice['body'], 'wp_idlm_tasks') === false, 'Non-admin sees internal schema notice');
    } finally { $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ENGINE=InnoDB"); }
    idlm_read_data(idlm_read_ajax('idlm_get_rep_tasks', 'owner'));
});
