<?php
/** REL-007 bootstrap request shapes and existing recovery fences. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

function idlm_bootstrap_http_errors(): string {
    global $config;
    $log = $config['content_dir'] . '/php-errors.log';
    return is_file($log) ? (string) file_get_contents($log) : '';
}

idlm_http_case('T20-H-REL007-healthy-bootstrap-ignores-array-request-metadata-without-diagnostics', static function(): void {
    $errors = idlm_bootstrap_http_errors(); $before = idlm_http_snapshot();
    $response = idlm_http_request('/migration-notices', array('rest_route' => array('/idlm/v1/probe'), 'action' => array('idlm_probe')), 'owner', false);
    idlm_http_check($response['status'] === 200, 'Malformed metadata broke ordinary bootstrap');
    idlm_http_check($before === idlm_http_snapshot(), 'Malformed metadata changed CRM/jobs/outbound');
    idlm_http_check($errors === idlm_bootstrap_http_errors(), 'Malformed metadata produced PHP diagnostics');
});

idlm_http_case('T20-H-REL007-pending-migration-blocks-CRM-action-with-malformed-route-without-diagnostics', static function(): void {
    global $wpdb, $sessions;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ENGINE=MyISAM");
    wp_cache_flush(); update_option('idlm_schema_version', '1.3.5', false);
    try {
        $errors = idlm_bootstrap_http_errors(); $before = idlm_http_snapshot();
        $response = idlm_http_request('/wp-admin/admin-ajax.php?rest_route%5B%5D=%2Fidlm%2Fv1%2Fprobe', array('action' => 'idlm_get_rep_tasks', 'nonce' => $sessions['owner']['admin']));
        idlm_http_check($response['status'] === 503 && strpos($response['body'], 'Retry shortly') !== false, 'Pending CRM action was not paused');
        idlm_http_check(strpos($response['body'], 'wp_idlm_tasks') === false && $before === idlm_http_snapshot(), 'Paused request disclosed internals or changed state');
        idlm_http_check($errors === idlm_bootstrap_http_errors(), 'Pending migration cast malformed route metadata');
    } finally {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ENGINE=InnoDB");
        idlm_http_request('/migration-notices', array(), 'owner', false);
    }
});

idlm_http_case('T20-H-REL007-pending-migration-keeps-admin-notice-available-with-array-metadata', static function(): void {
    global $wpdb;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ENGINE=MyISAM");
    wp_cache_flush(); update_option('idlm_schema_version', '1.3.5', false);
    try {
        $errors = idlm_bootstrap_http_errors(); $before = idlm_http_snapshot();
        $response = idlm_http_request('/migration-notices', array('rest_route' => array('/idlm/v1/probe'), 'action' => array('idlm_probe')), 'admin', false);
        idlm_http_check($response['status'] === 200 && strpos($response['body'], 'InnoDB') !== false, 'Administrator repair notice was lost');
        idlm_http_check($before === idlm_http_snapshot(), 'Notice request changed CRM/jobs/outbound');
        idlm_http_check($errors === idlm_bootstrap_http_errors(), 'Notice request cast malformed metadata');
    } finally {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ENGINE=InnoDB");
        idlm_http_request('/migration-notices', array(), 'owner', false);
    }
});

idlm_http_case('T20-H-REL007-pending-migration-pauses-both-REST-query-namespaces', static function(): void {
    global $wpdb;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ENGINE=MyISAM");
    wp_cache_flush(); update_option('idlm_schema_version', '1.3.5', false);
    try {
        $errors = idlm_bootstrap_http_errors(); $before = idlm_http_snapshot();
        foreach (array('/idlm/v1/meta-webhook', '/interior-design-lead-manager/v1/webhook') as $route) {
            $response = idlm_http_request('/index.php', array('rest_route' => $route), 'anonymous', false);
            idlm_http_check($response['status'] === 503 && strpos($response['body'], 'Retry shortly') !== false, 'Pending REST query was not paused');
            idlm_http_check(strpos($response['body'], 'wp_idlm_tasks') === false && $before === idlm_http_snapshot(), 'Paused REST query disclosed internals or changed state');
        }
        idlm_http_check($errors === idlm_bootstrap_http_errors(), 'Valid REST query produced diagnostics');
    } finally {
        $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks ENGINE=InnoDB");
        idlm_http_request('/migration-notices', array(), 'owner', false);
    }
});

idlm_http_case('T20-H-REL007-CRM-bootstrap-holds-worker-fence-through-response', static function(): void {
    global $config;
    $hold = $config['content_dir'] . '/bootstrap-worker-hold';
    foreach (array(array('action' => 'idlm_probe'), array('rest_route' => '/idlm/v1/probe')) as $query) {
        file_put_contents($hold, 'owned synthetic barrier');
        $process = proc_open(array('curl', '--silent', '--show-error', '--max-time', '12', $config['http_url'] . '/bootstrap-worker-hold?' . http_build_query($query)), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        idlm_http_check(is_resource($process), 'Cannot launch held request'); fclose($pipes[0]);
        try {
            $deadline = microtime(true) + 8;
            while (!is_file($hold . '.ready') && microtime(true) < $deadline) { usleep(10000); }
            idlm_http_check(is_file($hold . '.ready'), 'Bootstrap barrier was not reached');
            $acquired = IDLM_Recovery_Guard::begin_restore();
            if ($acquired) { IDLM_Recovery_Guard::end_restore(); }
            idlm_http_check(!$acquired, 'Restore entered during an active CRM request');
        } finally {
            file_put_contents($hold . '.release', 'release');
            $body = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($process);
            foreach (array($hold, $hold . '.ready', $hold . '.release') as $file) { if (is_file($file)) { unlink($file); } }
        }
        idlm_http_check($exit === 0 && $body === 'released', 'Held request did not finish: ' . $errors);
        $acquired = IDLM_Recovery_Guard::begin_restore();
        if ($acquired) { IDLM_Recovery_Guard::end_restore(); }
        idlm_http_check($acquired, 'Request shutdown failed to release recovery fence');
    }
});
