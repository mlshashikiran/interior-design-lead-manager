<?php
/** Owned disposable HTTP fixture only; never load wp-config.php. Builder: Shashi. */
if ('cli-server' !== PHP_SAPI) { exit(1); }
$config_path = getenv('IDLM_HTTP_CONFIG');
if (!$config_path || !preg_match('~^/.*idlm_test_[a-z0-9_]+-content/http-config.json$~', $config_path)) { http_response_code(503); exit; }
$config = json_decode(file_get_contents($config_path), true);
if (!$config || !preg_match('/^idlm_test_[a-z0-9_]+$/', $config['db_name']) || !is_file($config['content_dir'] . '/owned-http-fixture')) { http_response_code(503); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Optional captured task markup stress fixture; synthetic content only.
if ('/task-layout-stress' === $path && is_file($config['content_dir'].'/task-layout-stress.html')) {
    header('Content-Type: text/html; charset=UTF-8'); readfile($config['content_dir'].'/task-layout-stress.html'); exit;
}
// Serve only the owned fixture's core assets and this candidate's public assets.
if (preg_match('~^/(wp-includes|wp-admin)/(?:[^.][^/]*/)*[^/]+\.(js|css|png|svg|woff2?)$~', $path) && is_file($config['wp_root'] . ltrim($path, '/'))) {
    header('Content-Type: ' . (substr($path, -3) === '.js' ? 'application/javascript' : (substr($path, -4) === '.css' ? 'text/css' : 'application/octet-stream')));
    readfile($config['wp_root'] . ltrim($path, '/')); exit;
}
if (preg_match('~^/candidate-assets/(css|js|fonts)/[a-zA-Z0-9_.-]+\.(css|js|woff2?)$~', $path)) {
    header('Content-Type: ' . (substr($path, -3) === '.js' ? 'application/javascript' : (substr($path, -4) === '.css' ? 'text/css' : 'font/woff2')));
    readfile($config['plugin_root'] . 'assets/' . substr($path, strlen('/candidate-assets/'))); exit;
}
// Actual static uploads for the owned fixture only; demonstrates legacy bypass.
if (strpos($path, '/wp-content/uploads/') === 0) {
    $base = realpath($config['content_dir'] . '/uploads');
    $file = realpath($config['content_dir'] . substr($path, strlen('/wp-content')));
    if ($base && $file && strpos($file, $base . DIRECTORY_SEPARATOR) === 0 && is_file($file) && !preg_match('/\.(?:php|phtml|phar)$/i', $file)) {
        header('Content-Type: application/octet-stream'); readfile($file); exit;
    }
    http_response_code(404); exit;
}
define('IDLM_DISPOSABLE_HTTP_FIXTURE', true);
define('CONCATENATE_SCRIPTS', false); // Serve core assets directly in the owned admin fixture.
if ('/admin-workspace' === $path || '/leads-admin-workspace' === $path || '/wp-admin/admin.php' === $path) { define('WP_ADMIN', true); }
require __DIR__ . '/bootstrap.php';
idlm_integration_boot_wordpress($config, false);
idlm_integration_load_plugin($config);
// Fault injection is confined to this owned disposable fixture before bootstrap.
if (is_file($config['content_dir'] . '/fail-schema-ddl')) {
    add_filter('query', static function ($sql) { return stripos($sql, 'ALTER TABLE') === 0 ? 'SELECT 1' : $sql; });
}
if (is_file($config['content_dir'] . '/fail-quotation-backfill')) {
    add_filter('query', static function ($sql) { return strpos($sql, 'idlm_quotation_lifecycle_integrity') !== false ? 'SELECT idlm_synthetic_migration_failure FROM idlm_absent_table' : $sql; });
}
idlm_boot_plugin();
idlm_integration_capture_known_core_diagnostics($config);
if ('/bootstrap-worker-hold' === $path && is_file($config['content_dir'] . '/bootstrap-worker-hold')) {
    $hold = $config['content_dir'] . '/bootstrap-worker-hold';
    file_put_contents($hold . '.ready', 'ready');
    $deadline = microtime(true) + 10;
    while (!is_file($hold . '.release') && microtime(true) < $deadline) { usleep(10000); }
    echo is_file($hold . '.release') ? 'released' : 'barrier timeout'; exit;
}
if (preg_match('~^/wp-json/idlm/v1/(meta|interakt)-webhook$~',$path)) {
    error_reporting(E_ALL);set_error_handler(static function($level,$message){throw new RuntimeException('Inbound webhook diagnostic: '.$message);});
    add_filter('query',static function($sql){return is_file(WP_CONTENT_DIR.'/fail-wa-receipt') && strpos($sql,'INSERT INTO `wp_options`')===0 && strpos($sql,'idlm_whatsapp_receipt_')!==false ? 'SELECT * FROM idlm_int006_missing_table' : $sql;});
}

if ('/migration-notices' === $path) { do_action('admin_notices'); exit; }
remove_action('admin_init', '_maybe_update_core');
remove_action('admin_init', '_maybe_update_plugins');
remove_action('admin_init', '_maybe_update_themes');
// Test-only synchronization barrier. Independent HTTP workers stop before
// their first lead locking read while the parent changes live authority.
add_filter('query', static function($sql) use ($config) {
    $barrier = $config['content_dir'] . '/barrier';
    if (strpos($sql, 'idlm_leads WHERE id =') !== false && strpos($sql, 'FOR UPDATE') !== false && is_file($barrier)) {
        file_put_contents($barrier . '.ready', 'ready');
        $deadline = microtime(true) + 10;
        while (!is_file($barrier . '.release') && microtime(true) < $deadline) { usleep(10000); }
        if (!is_file($barrier . '.release')) { throw new RuntimeException('Fixture barrier timeout.'); }
    }
    return $sql;
});
// Read race barrier is owned fixture state, never a production query hook.
add_filter('query', static function($sql) use ($config) {
    global $wpdb;
    $barrier=$config['content_dir'].'/read-barrier';
    if (!is_file($barrier) || is_file($barrier.'.ready')) { return $sql; }
    $kind=file_get_contents($barrier);
    $table=$kind==='files' ? 'idlm_private_file_chunks' : ($kind==='tasks' ? 'idlm_tasks' : ($kind==='quotes' ? 'idlm_quotes' : 'idlm_leads'));
    if (strpos($sql,'FROM '.$wpdb->prefix.$table.' ') !== false) {
        file_put_contents($barrier.'.ready','ready'); $deadline=microtime(true)+10;
        while(!is_file($barrier.'.release') && microtime(true)<$deadline){usleep(10000);}
        if(!is_file($barrier.'.release')){throw new RuntimeException('Read fixture barrier timeout.');}
    }
    return $sql;
});
// Controlled SQL failure only in this owned disposable fixture.
add_filter('query', static function($sql) use ($config) {
    if (is_file($config['content_dir'] . '/fail-private-chunk') && strpos($sql, 'INSERT INTO `' . $GLOBALS['wpdb']->prefix . 'idlm_private_file_chunks`') === 0) { return 'INSERT INTO idlm_nonexistent_failure_table VALUES (1)'; }
    return $sql;
});
add_filter('query', static function($sql) use ($config) {
    if (is_file($config['content_dir'] . '/fail-export-next-page') && strpos($sql, 'SELECT id, first_name,') === 0 && strpos($sql, 'id <') !== false) { return 'SELECT * FROM idlm_missing_export_page'; }
    return $sql;
});
// QUO-007 faults happen after boot, at actual write preflight or required writes.
add_filter('query', static function($sql) use ($config) {
    $file=$config['content_dir'].'/fail-quotation-write';
    if (is_file($file)) { $GLOBALS['wpdb']->suppress_errors(true); ini_set('error_log',$config['content_dir'].'/expected-quotation-faults.log'); }
    if (is_file($file)) {
        $needle=file_get_contents($file);
        if ('COMMIT'===$needle ? trim($sql)==='COMMIT' : strpos($sql,$needle)!==false) { return 'SELECT idlm_synthetic_write_fault FROM idlm_absent_table'; }
    }
    return $sql;
});
// INT-008 receipt acknowledgement fault in the owned synthetic fixture only.
add_filter('query',static function($sql) use ($config) {
    if (is_file($config['content_dir'].'/fail-capi-delivery') && strpos($sql,'UPDATE `'.$GLOBALS['wpdb']->options.'`')===0 && strpos($sql,IDLM_CAPI_Delivery::PREFIX)!==false) { return 'SELECT * FROM idlm_int008_missing_table'; }
    return $sql;
});
// Observe actual custom-query executions in this owned fixture, never raw SQL.
add_filter('query', static function($sql) use ($config) {
    if (strpos($sql, 'SELECT /*+ MAX_EXECUTION_TIME(') === 0) {
        file_put_contents($config['content_dir'] . '/mcp-query-executions.log', "custom read executed\n", FILE_APPEND);
    }
    return $sql;
});
if (strpos($path, '/wp-json/') === 0) {
    $GLOBALS['wp']->query_vars['rest_route'] = substr($path, strlen('/wp-json'));
    rest_api_loaded(); exit;
}
if ('/index.php' === $path && isset($_GET['rest_route']) && is_string($_GET['rest_route'])) {
    $GLOBALS['wp']->query_vars['rest_route'] = wp_unslash($_GET['rest_route']);rest_api_loaded();exit;
}
if ('/login' === $path) {
    if ('POST' === $_SERVER['REQUEST_METHOD']) {
        $user = wp_signon(array('user_login' => $_POST['user_login'] ?? '', 'user_password' => $_POST['user_password'] ?? '', 'remember' => true), false);
        if (is_wp_error($user)) { wp_die('Invalid fixture credentials.', '', array('response' => 403)); }
        wp_safe_redirect('/workspace'); exit;
    }
    echo '<form method="post"><label>Username<input name="user_login"></label><label>Password<input name="user_password" type="password"></label><button>Log in</button></form>'; exit;
}
if ('/session' === $path) {
    if (!is_user_logged_in()) { wp_send_json_error(null, 403); }
    wp_send_json(array('pdf' => wp_create_nonce('idlm_download_quote_pdf'), 'catalogue_save' => wp_create_nonce('idlm_save_catalogue_item'), 'quote_settings' => wp_create_nonce('idlm_save_quote_settings'), 'catalogue_import' => wp_create_nonce('idlm_import_catalogue_csv'), 'backup' => wp_create_nonce('idlm_export_backup'), 'restore_backup' => wp_create_nonce('idlm_restore_backup'), 'create_user' => wp_create_nonce('idlm_create_user'), 'role_permissions' => wp_create_nonce('idlm_update_role_permissions'), 'admin' => wp_create_nonce('idlm_admin_nonce'), 'search' => wp_create_nonce('idlm_quote_catalogue_search'), 'save' => wp_create_nonce('idlm_save_quote'), 'autosave' => wp_create_nonce('idlm_quote_autosave'), 'csv' => wp_create_nonce('idlm_download_quote_csv'), 'xlsx' => wp_create_nonce('idlm_download_quote_xlsx'), 'print' => wp_create_nonce('idlm_print_quote')));
}
// Optional synthetic provider response is confined to this owned fixture directory.
if (is_file(WP_CONTENT_DIR.'/capi-provider-response.json')) {
    add_filter('pre_http_request',static function($pre,$args,$url){
        if(!preg_match('~graph.facebook.com|google-analytics.com|api.linkedin.com~',$url)){return $pre;}
        $fixture=json_decode(file_get_contents(WP_CONTENT_DIR.'/capi-provider-response.json'),true);
        $platform=strpos($url,'google-analytics')!==false?'google':(strpos($url,'facebook')!==false?'meta':'linkedin');
        file_put_contents(WP_CONTENT_DIR.'/capi-provider-calls.jsonl',wp_json_encode(array('platform'=>$platform,'payload'=>IDLM_Data_Retention::redact(json_decode($args['body'],true))))."\n",FILE_APPEND|LOCK_EX);
        return array('response'=>array('code'=>$fixture['code']??200),'body'=>'{"events_received":1}','headers'=>array());
    },PHP_INT_MAX,3);
    add_action('wp_ajax_idlm_retry_capi_events',static function(){error_reporting(E_ALL);set_error_handler(static function($level,$message){throw new RuntimeException('CAPI retry diagnostic: '.$message);});},-100);
}
// Synthetic audience responses and SQL faults apply only in this owned fixture.
if (is_file(WP_CONTENT_DIR.'/audience-provider-response.json')) {
    add_filter('pre_http_request',static function($pre,$args,$url){
        if(strpos($url,'/users')===false && strpos($url,'/contacts')===false){return $pre;}
        $platform=strpos($url,'facebook')!==false?'meta':'linkedin';$responses=json_decode(file_get_contents(WP_CONTENT_DIR.'/audience-provider-response.json'),true);$response=$responses[$platform]??array('code'=>200,'body'=>'{}');
        file_put_contents(WP_CONTENT_DIR.'/audience-provider-calls.jsonl',wp_json_encode(array('platform'=>$platform,'body'=>json_decode($args['body'],true)))."\n",FILE_APPEND|LOCK_EX);
        return array('response'=>array('code'=>$response['code']),'body'=>$response['body'],'headers'=>array('retry-after'=>$response['retry_after']??''));
    },PHP_INT_MAX,3);
    add_filter('query',static function($sql){return is_file(WP_CONTENT_DIR.'/fail-audience-outcome') && strpos($sql,'INSERT INTO `wp_idlm_audience_sync_logs`')===0 ? 'SELECT * FROM idlm_int009_missing_table' : $sql;});
}
// INT-010 connection-test fixture; never contact a real provider.
if (is_file(WP_CONTENT_DIR.'/secret-provider.json')) {
    add_filter('pre_http_request',static function($pre,$args,$url){
        if (($args['method'] ?? '') !== 'GET' || !preg_match('~graph.facebook.com|api.linkedin.com~',$url)) { return $pre; }
        $secret=IDLM_Secret_Store::get(strpos($url,'facebook')!==false?'idlm_meta_access_token':'idlm_linkedin_access_token','',true);
        file_put_contents(WP_CONTENT_DIR.'/secret-provider-check.json',wp_json_encode(array('url_secret'=>strpos($url,$secret)!==false,'header_correct'=>($args['headers']['Authorization']??'')==='Bearer '.$secret,'redirects'=>$args['redirection'])));
        $fixture = json_decode(file_get_contents(WP_CONTENT_DIR.'/secret-provider.json'), true);
        if (($fixture['result'] ?? '') === 'success') {
            return array('headers'=>array(), 'response'=>array('code'=>200), 'body'=>wp_json_encode(array('name'=>'Synthetic verified target','subtype'=>'CUSTOM')));
        }
        if (($fixture['result'] ?? '') === 'api_error') {
            $message = 'Synthetic provider rejection ' . $secret;
            return array('headers'=>array(), 'response'=>array('code'=>400), 'body'=>wp_json_encode(array('error'=>array('message'=>$message,'error_subcode'=>42),'message'=>$message)));
        }
        return new WP_Error('synthetic','Provider echo '.$secret.' Authorization: Bearer '.$secret);
    },PHP_INT_MAX,3);
}
// Elevate warnings/deprecations only for the affected lead CSV handler.
add_action('wp_ajax_idlm_export_leads', static function() {
    error_reporting(E_ALL);
    set_error_handler(static function($level, $message) { throw new RuntimeException('CSV PHP diagnostic: ' . $message); });
}, -100);
if ('/wp-admin/admin-post.php' === $path || '/wp-admin/admin-ajax.php' === $path) {
    require $config['wp_root'] . ltrim($path, '/'); exit;
}
if ('/wp-admin/admin.php' === $path && ($_GET['page'] ?? '') === 'idlm_identity_reviews') {
    require $config['wp_root'] . 'wp-admin/admin.php';exit;
}
if ('/identity-reviews' === $path) {
    error_reporting(E_ALL);set_error_handler(static function($level,$message){throw new RuntimeException('Review PHP diagnostic: '.$message);});
    IDLM_Identity_Review::render();exit;
}
if ('/tasks-workspace' === $path || '/crm-workspace' === $path) {
    foreach (array('script_loader_src', 'style_loader_src') as $filter) {
        add_filter($filter, static function($url) { $at = strpos($url, '/assets/'); return $at === false ? $url : '/candidate-assets/' . substr($url, $at + 8); });
    }
    $html = '/crm-workspace' === $path ? IDLM_Admin_Shortcodes::render_workspace_shortcode() : IDLM_Admin_Shortcodes::render_leads_list_shortcode();
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">';
    wp_head(); echo '</head><body>' . $html; wp_print_footer_scripts(); echo '</body></html>'; exit;
}
if ('/settings-workspace' === $path) {
    foreach (array('script_loader_src', 'style_loader_src') as $filter) {
        add_filter($filter, static function($url) { $at = strpos($url, '/assets/'); return $at === false ? $url : '/candidate-assets/' . substr($url, $at + 8); });
    }
    $_POST['active_tab'] = sanitize_key($_GET['tab'] ?? 'backup');
    IDLM_Admin_Assets::enqueue_shortcode_assets('workspace');
    wp_enqueue_script('idlm-settings');
    // Run protected settings handling before the fixture emits any shell bytes.
    ob_start(); IDLM_Admin_Settings_Renderer::render(); $settings_markup = ob_get_clean();
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">';
    wp_head(); echo '</head><body>';
    echo $settings_markup; wp_print_footer_scripts(); echo '</body></html>'; exit;
}
if ('/leads-workspace' === $path || '/leads-admin-workspace' === $path) {
    if ('/leads-admin-workspace' === $path) { IDLM_Admin::render_leads_list_page(); }
    else { echo IDLM_Admin_Shortcodes::render_leads_list_shortcode(); }
    exit;
}
if ('/workspace' === $path || '/admin-workspace' === $path || '/wp-admin/admin.php' === $path) {
    // Resolve capability/object redirects before the fixture emits its presentation shell.
    $quotation_markup = IDLM_Quotations::render_shortcode();
    echo '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<link rel="stylesheet" href="/candidate-assets/css/admin-style.css"><link rel="stylesheet" href="/candidate-assets/css/quotations.css"><script src="/wp-includes/js/jquery/jquery.min.js"></script></head><body>';
    echo '<script>var idlmQuoteData=' . wp_json_encode(array('ajaxUrl' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('idlm_quote_catalogue_search'), 'autosaveNonce' => wp_create_nonce('idlm_quote_autosave'), 'currencyCode' => (get_option('idlm_quote_settings', array())['currency_code'] ?? 'INR'), 'numericLimits' => array('maxAmount' => IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS, 'maxMeasure' => IDLM_Quotation_Calculator::MAX_MEASURE_UNITS, 'maxRows' => IDLM_Quotation_Calculator::MAX_ROWS, 'maxSections' => IDLM_Quotation_Calculator::MAX_SECTIONS))) . ';</script>';
    echo $quotation_markup;
    echo '<script src="/candidate-assets/js/dialog.js"></script><script src="/candidate-assets/js/quotations.js"></script></body></html>'; exit;
}
http_response_code(404);
