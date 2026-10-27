<?php
/** M6 telemetry transport contracts against disposable WordPress. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

function idlm_telemetry_http(string $action, array $fields = array(), string $actor = 'admin', ?string $nonce = null): array {
    global $sessions;
    return idlm_http_request('/wp-admin/admin-ajax.php', array('action'=>'idlm_'.$action, 'nonce'=>$nonce ?? ($sessions[$actor]['admin'] ?? 'invalid')) + $fields, $actor, $action !== 'download_diagnostic_report');
}

idlm_http_case('M6-H-telemetry-denials-preserve-rows-jobs-and-outbound-state', static function(): void {
    idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php', array('action'=>'idlm_fixture_unregistered'), 'admin')['status'] === 400, 'Cannot prime admin_init');
    $before = idlm_http_snapshot();
    foreach (array('log_js_error', 'clear_telemetry_logs', 'download_diagnostic_report') as $action) {
        $r = idlm_telemetry_http($action, array('message'=>'synthetic-denied'), 'admin', 'invalid');
        idlm_http_check($r['status'] === 403, 'Invalid nonce accepted: '.$action);
        idlm_http_check($before === idlm_http_snapshot(), 'Nonce denial changed state: '.$action);
        idlm_http_check(idlm_telemetry_http($action, array(), 'anonymous')['status'] === 400, 'Anonymous callback exposed: '.$action);
        idlm_http_check($before === idlm_http_snapshot(), 'Anonymous request changed state');
    }
    $r = idlm_telemetry_http('clear_telemetry_logs', array(), 'owner');
    idlm_http_check($r['status'] === 200 && json_decode($r['body'], true) === array('success'=>false, 'data'=>array('message'=>'Access denied')), 'Representative clear denial changed');
    idlm_http_check($before === idlm_http_snapshot(), 'Denied clear changed state');
    $r = idlm_telemetry_http('download_diagnostic_report', array(), 'owner');
    idlm_http_check($r['status'] === 403 && strpos($r['body'], 'Access denied.') !== false, 'Representative download denial changed');
    idlm_http_check($before === idlm_http_snapshot(), 'Denied report changed state');
});

idlm_http_case('M6-H-telemetry-client-error-keeps-sanitized-storage-and-empty-success', static function(): void {
    global $wpdb;
    $table = $wpdb->prefix.'idlm_telemetry_logs';
    $before = idlm_http_snapshot();
    $r = idlm_telemetry_http('log_js_error', array('message'=>''), 'owner');
    idlm_http_check($r['status'] === 200 && json_decode($r['body'], true) === array('success'=>true), 'Empty client response changed');
    idlm_http_check($before === idlm_http_snapshot(), 'Empty message wrote state');
    $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    $r = idlm_telemetry_http('log_js_error', array('message'=>"<b>Synthetic O'Brien</b>", 'file'=>'assets/synthetic.js', 'line'=>'17', 'col'=>'4', 'stack'=>"<b>frame one</b>\nframe two"), 'owner');
    idlm_http_check($r['status'] === 200 && json_decode($r['body'], true) === array('success'=>true), 'Client response changed');
    idlm_http_check((int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") === $count + 1, 'Expected one client log');
    $row = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A);
    foreach (array('log_type'=>'js_error', 'severity'=>'error', 'message'=>"Synthetic O'Brien", 'file'=>'assets/synthetic.js', 'line'=>'17', 'stack_trace'=>"Col: 4\n\nStack:\nframe one\nframe two") as $key=>$expected) {
        idlm_http_check((string) $row[$key] === $expected, 'Client storage changed: '.$key);
    }
});

idlm_http_case('M6-H-telemetry-download-keeps-headers-counts-order-and-fifty-row-bound', static function(): void {
    global $wpdb;
    $table = $wpdb->prefix.'idlm_telemetry_logs';
    for ($i = 1; $i <= 55; $i++) {
        IDLM_Telemetry::log('js_error', 'error', sprintf('M6-synthetic-report-%03d', $i), 'synthetic.js', $i, $i === 55 ? 'Synthetic final stack' : '');
    }
    $before = idlm_http_snapshot();
    $r = idlm_telemetry_http('download_diagnostic_report');
    idlm_http_check($r['status'] === 200 && stripos($r['headers'], 'Content-Type: text/plain; charset=utf-8') !== false, 'Report type changed');
    idlm_http_check((bool) preg_match('/Content-Disposition: attachment; filename=idlm-diagnostic-report-\d{4}-\d{2}-\d{2}\.txt/i', $r['headers']), 'Report filename changed');
    foreach (array('=== INTERIOR DESIGN LEAD MANAGER DIAGNOSTICS ===', 'Plugin Version: '.IDLM_VERSION, '=== ENVIRONMENT ===', '=== PLUGIN SETTINGS ===', '=== DATABASE STATE ===', '=== RECENT TELEMETRY LOGS (Last 50 Entries) ===', '- '.$table.': '.$wpdb->get_var("SELECT COUNT(*) FROM {$table}").' rows', 'M6-synthetic-report-055', 'M6-synthetic-report-006', 'Stack Trace:\nSynthetic final stack') as $text) {
        idlm_http_check(strpos($r['body'], str_replace('\\n', "\n", $text)) !== false, 'Missing report field: '.$text);
    }
    idlm_http_check(substr_count($r['body'], 'M6-synthetic-report-') === 50 && strpos($r['body'], 'M6-synthetic-report-005') === false, 'Recent-log bound changed');
    idlm_http_check(strpos($r['body'], 'M6-synthetic-report-055') < strpos($r['body'], 'M6-synthetic-report-054'), 'Report order changed');
    idlm_http_check(strpos($r['body'], 'PHP Warning') === false && strpos($r['body'], 'Deprecated:') === false, 'Diagnostics corrupted report');
    idlm_http_check($before === idlm_http_snapshot(), 'Report changed rows/jobs/outbound state');
});

idlm_http_case('M6-H-telemetry-admin-clear-keeps-success-and-empty-report', static function(): void {
    global $wpdb;
    $table = $wpdb->prefix.'idlm_telemetry_logs';
    idlm_http_check((int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") > 0, 'Clear fixture has no rows');
    $r = idlm_telemetry_http('clear_telemetry_logs');
    idlm_http_check($r['status'] === 200 && json_decode($r['body'], true) === array('success'=>true, 'data'=>array('message'=>'Telemetry logs cleared successfully.')), 'Clear response changed');
    idlm_http_check((int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") === 0, 'Clear did not remove telemetry');
    $before = idlm_http_snapshot();
    $r = idlm_telemetry_http('download_diagnostic_report');
    idlm_http_check($r['status'] === 200 && strpos($r['body'], 'No error logs recorded.') !== false, 'Empty report changed');
    idlm_http_check($before === idlm_http_snapshot(), 'Empty report changed state');
});
