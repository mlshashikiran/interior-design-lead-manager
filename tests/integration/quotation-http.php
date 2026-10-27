<?php
/** QUO-001/003 actual HTTP acceptance fixture (T10/T12, GAP-10/12).
 * Synthetic users/rows only. Builder: Shashi.
 */
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
$config = array(); $database = null; $server = null; $server_pipes = array(); $failed = 0; $passed = 0;
function idlm_http_check(bool $value, string $message): void { if (!$value) { throw new RuntimeException($message); } }
function idlm_http_case(string $id, callable $test): void {
    global $failed, $passed;
    try { $test(); $passed++; echo "PASS $id\n"; } catch (Throwable $e) { $failed++; echo "FAIL $id: {$e->getMessage()}\n"; }
}
function idlm_http_request(string $path, array $data = array(), string $actor = 'owner', bool $post = true): array {
    global $config;
    $file = tempnam($config['content_dir'], 'response-');
    $headers = $file . '.headers';
    $args = array('curl', '--silent', '--show-error', '--max-time', '15', '--cookie', $config['content_dir'] . '/' . $actor . '.cookies', '--cookie-jar', $config['content_dir'] . '/' . $actor . '.cookies', '--dump-header', $headers, '--output', $file, '--write-out', '%{http_code}');
    if ($post) { $args[] = '--data'; $args[] = http_build_query($data); }
    elseif ($data) { $path .= (strpos($path, '?') === false ? '?' : '&') . http_build_query($data); }
    $args[] = $config['http_url'] . $path;
    $proc = proc_open(implode(' ', array_map('escapeshellarg', $args)), array(1 => array('pipe','w'), 2 => array('pipe','w')), $pipes);
    $status = stream_get_contents($pipes[1]); $errors = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]); $exit = proc_close($proc);
    $result = array('status' => (int) $status, 'body' => (string) file_get_contents($file), 'headers' => (string) file_get_contents($headers));
    unlink($file); unlink($headers);
    idlm_http_check($exit === 0, 'HTTP transport failed: ' . $errors);
    return $result;
}
function idlm_http_snapshot(): string {
    global $wpdb;
    $rows = array();
    foreach ($wpdb->get_col("SHOW TABLES LIKE 'wp_idlm_%'") as $table) {
        $rows[$table] = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);
        idlm_http_check($wpdb->last_error === '', 'Unable to snapshot fixture table ' . $table);
        usort($rows[$table], static function($a, $b): int { return strcmp(wp_json_encode($a), wp_json_encode($b)); });
    }
    // Independent aggregate coverage includes all runtime idlm tables, queue
    // records, numbering, CRM activity and user-scoped working copies.
    $rows['working_copies_and_jobs'] = $wpdb->get_results("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '%idlm_quote_autosave_%' OR option_name = 'cron' ORDER BY option_name", ARRAY_A);
    global $config;
    $rows['outbound_attempts'] = is_file($config['content_dir'].'/outbound-attempts.log') ? file_get_contents($config['content_dir'].'/outbound-attempts.log') : '';
    return hash('sha256', wp_json_encode($rows));
}
function idlm_http_payload(int $lead, int $quote = 0, int $revision = 0): array {
    return array('action' => 'idlm_save_quote', 'lead_id' => $lead, 'quote_id' => $quote, 'revision_id' => $revision, 'save_request_key' => wp_generate_uuid4(), 'revision_reason' => 'Synthetic authorized revision', 'internal_notes' => 'PRIVATE-SYNTHETIC-NOTES', 'sections' => array('general' => array('section_name' => 'General')), 'items' => array(array('section_key' => 'general', 'description' => 'SYNTHETIC-ITEM-ORIGINAL', 'quantity' => 1, 'unit_rate' => 100, 'unit' => 'Nos')));
}
function idlm_http_save(array $payload, string $actor = 'owner'): array {
    global $sessions;
    return idlm_http_request('/wp-admin/admin-post.php', array_merge($payload, array('_wpnonce' => $sessions[$actor]['save'])), $actor);
}
function idlm_http_autosave(array $payload, string $actor = 'owner', bool $discard = false): array {
    global $sessions;
    return idlm_http_request('/wp-admin/admin-ajax.php', array_merge($payload, array('action' => $discard ? 'idlm_discard_quote_autosave' : 'idlm_autosave_quote', 'nonce' => $sessions[$actor]['autosave'])), $actor);
}
function idlm_http_denied(callable $request, array $statuses = array(403,404,409)): void {
    $before = idlm_http_snapshot(); $r = $request();
    idlm_http_check(in_array($r['status'], $statuses, true), 'Expected denial, got ' . $r['status']);
    foreach (array('PII-SYNTHETIC', 'PRIVATE-SYNTHETIC-NOTES', 'SYNTHETIC-ITEM-ORIGINAL', 'example.test') as $pii) { idlm_http_check(strpos($r['body'] . $r['headers'], $pii) === false, 'PII disclosed: ' . $pii); }
    idlm_http_check($before === idlm_http_snapshot(), 'Denied request changed rows, numbering, working copies or jobs');
}
function idlm_http_copy_core(string $source, string $target): void {
    mkdir($target, 0700, true);
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($source));
        if (strpos($relative, 'wp-content/') === 0 || $relative === 'wp-content' || strpos($relative, 'wp-config') === 0 || $relative[0] === '.') { continue; }
        $dest = $target . $relative;
        if ($item->isDir()) { if (!is_dir($dest)) { mkdir($dest, 0700, true); } }
        elseif ($item->isFile()) { if (!is_dir(dirname($dest))) { mkdir(dirname($dest), 0700, true); } copy($item->getPathname(), $dest); }
    }
    // Core transport requires wp-load.php; WordPress has already been booted
    // against this fixture. There is deliberately no site configuration.
    file_put_contents($target . 'wp-config.php', "<?php\nif (!defined('DB_NAME') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit; }\n");
}
function idlm_http_cleanup(): void {
    global $config, $database, $server, $server_pipes;
    if (is_resource($server)) { proc_terminate($server); foreach ($server_pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } } proc_close($server); $server = null; }
    if ($database) { $database->drop(); $database = null; }
    if (!empty($config['content_dir'])) { idlm_integration_remove_directory($config['content_dir']); }
}
register_shutdown_function('idlm_http_cleanup');
try {
    $config = idlm_integration_config(); $database = new IDLM_Integration_Database($config);
    $listener = stream_socket_server('tcp://127.0.0.1:0', $errno, $error); idlm_http_check((bool) $listener, 'Cannot allocate fixture port');
    $address = stream_socket_get_name($listener, false); fclose($listener); $config['http_url'] = 'http://' . $address;
    mkdir($config['content_dir'], 0700, true);
    idlm_http_copy_core($config['wp_root'], $config['content_dir'] . '/wordpress/'); $config['wp_root'] = $config['content_dir'] . '/wordpress/';
    idlm_integration_export_config($config);
    $admin = idlm_integration_boot_wordpress($config, true); idlm_integration_load_plugin($config); idlm_register_crm_roles_and_capabilities();
    IDLM_DB::create_tables(); IDLM_Quotations::install();
    idlm_boot_plugin();
    // Non-capacity HTTP fixtures seed an unrestricted shared owner.
    update_option('idlm_max_active_leads_per_rep', 0);
    // No runtime version reconciliation or provider scheduling on HTTP requests.
    update_option('idlm_schema_version', IDLM_DB_VERSION);
    update_option('idlm_role_lead_scopes', array('sales_executive' => 'assigned', 'author' => 'all', 'sales_manager' => 'all'));
    $caps = array('read' => true, 'idlm_access_leads' => true, 'idlm_view_quotes' => true, 'idlm_create_quotes' => true, 'idlm_edit_quote_drafts' => true, 'idlm_create_quote_revisions' => true, 'idlm_download_quote_pdf' => true);
    add_role('sales_executive', 'Sales Executive', $caps); add_role('sales_manager', 'Sales Manager', $caps + array('idlm_assign_leads' => true));
    $users = array();
    foreach (array('owner' => 'sales_executive', 'other' => 'sales_executive', 'readall' => 'author', 'manager' => 'sales_manager') as $actor => $role) {
        $users[$actor] = (int) wp_insert_user(array('user_login' => 'fixture_' . $actor, 'user_pass' => 'idlm-synthetic-password', 'user_email' => $actor . '@example.test', 'role' => $role));
        $u = new WP_User($users[$actor]); foreach ($caps as $cap => $grant) { $u->add_cap($cap); } if ($actor === 'manager') { $u->add_cap('idlm_assign_leads'); }
    }
    $leads = array();
    foreach (array('owner','other','readall','other','owner','owner','owner','owner') as $i => $actor) {
        $now = current_time('mysql');
        $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'PII-SYNTHETIC-' . $i, 'phone' => '+1555000000' . $i, 'email' => 'synthetic' . $i . '@example.test', 'project_name' => 'PII-SYNTHETIC-PROJECT', 'assigned_rep_id' => $users[$actor], 'created_at' => $now, 'updated_at' => $now, 'stage' => 'New'));
        $leads[$i] = (int) $wpdb->insert_id;
    }
    file_put_contents($config['content_dir'] . '/owned-http-fixture', $config['db_name']);
    file_put_contents($config['content_dir'] . '/http-config.json', json_encode($config)); chmod($config['content_dir'] . '/http-config.json', 0600);
    putenv('IDLM_HTTP_CONFIG=' . $config['content_dir'] . '/http-config.json');
    $server = proc_open(escapeshellarg(PHP_BINARY) . ' -d upload_max_filesize=16M -d post_max_size=20M -d display_errors=0 -d log_errors=1 -d error_log=' . escapeshellarg($config['content_dir'] . '/php-errors.log') . ' -S ' . escapeshellarg($address) . ' ' . escapeshellarg(__DIR__ . '/http-router.php'), array(0 => array('pipe','r'), 1 => array('file',$config['content_dir'].'/server.log','a'), 2 => array('file',$config['content_dir'].'/server.log','a')), $server_pipes);
    idlm_http_check(is_resource($server), 'Unable to start owned HTTP server'); usleep(300000);
    $sessions = array();
    foreach ($users as $actor => $user) {
        $login = idlm_http_request('/login', array('user_login' => 'fixture_' . $actor, 'user_password' => 'idlm-synthetic-password'), $actor);
        idlm_http_check($login['status'] === 302, 'Login failed for ' . $actor);
        $sessions[$actor] = json_decode(idlm_http_request('/session', array(), $actor, false)['body'], true);
        idlm_http_check(!empty($sessions[$actor]['save']), 'Cookie-authenticated session missing');
    }
    echo 'ENV PHP=' . PHP_VERSION . ' WP=' . $wp_version . ' DB=' . $wpdb->db_version() . ' memory_limit=' . ini_get('memory_limit') . ' scheduler=' . (function_exists('as_schedule_single_action') ? 'ActionScheduler' : 'WP-Cron') . ' cache=' . (wp_using_ext_object_cache() ? 'on' : 'off') . ' opcache_cli=' . ini_get('opcache.enable_cli') . "\n";
    if (in_array('--notifications-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
        require __DIR__ . '/notification-control-http-cases.php';
        echo "$passed passed, $failed failed\n";
        if (in_array('--hold',$argv,true)) {
            echo 'BROWSER URL='.$config['http_url'].' STOP='.$config['content_dir']."/stop\n";
            $deadline=microtime(true)+900;while(!is_file($config['content_dir'].'/stop') && microtime(true)<$deadline){usleep(100000);}
        }
        idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--attribution-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
        $sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
        require __DIR__ . '/attribution-opt-in-http-cases.php';
        echo "$passed passed, $failed failed\n";
        if (in_array('--hold',$argv,true)) {
            delete_option(IDLM_Settings::WHITE_LABEL_OPTION);
            echo 'BROWSER URL='.$config['http_url'].' STOP='.$config['content_dir']."/stop\n";
            $deadline=microtime(true)+900;while(!is_file($config['content_dir'].'/stop') && microtime(true)<$deadline){usleep(100000);}
        }
        idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--kanban-page-only', $argv, true)) {
        require __DIR__ . '/kanban-pagination-http-cases.php';
        echo "{$passed} passed, {$failed} failed\n";
        if (in_array('--hold', $argv, true)) {
            echo 'BROWSER URL=' . $config['http_url'] . ' SOURCE=' . $board_source . ' STOP=' . $config['content_dir'] . "/stop\n";
            $deadline = microtime(true) + 900;
            while (!is_file($config['content_dir'] . '/stop') && microtime(true) < $deadline) { usleep(100000); }
        }
        exit($failed ? 1 : 0);
    }
    if (in_array('--role-permissions-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
        $sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
        require __DIR__ . '/role-permissions-http-cases.php';
        $errors = is_file($config['content_dir'].'/php-errors.log') ? file_get_contents($config['content_dir'].'/php-errors.log') : '';
        idlm_http_check(trim($errors) === '', 'PHP diagnostics: '.$errors);
        echo "$passed passed, $failed failed\n";idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--activity-controller-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
        $sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
        require __DIR__ . '/activity-controller-http-cases.php';
        $errors = is_file($config['content_dir'].'/php-errors.log') ? file_get_contents($config['content_dir'].'/php-errors.log') : '';
        idlm_http_check(trim($errors) === '', 'PHP diagnostics: '.$errors);
        echo "$passed passed, $failed failed\n";idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--telemetry-controller-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
        $sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
        require __DIR__ . '/telemetry-controller-http-cases.php';
        echo "$passed passed, $failed failed\n";idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--connection-controller-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
        $sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
        require __DIR__ . '/connection-controller-http-cases.php';
        echo "$passed passed, $failed failed\n";idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--secret-store-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
        $sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
        require __DIR__.'/secret-store-http-cases.php';
        if (in_array('--hold',$argv,true)) {
            echo 'BROWSER URL='.$config['http_url'].' STOP='.$config['content_dir']."/stop\n";
            $deadline=microtime(true)+900;while(!is_file($config['content_dir'].'/stop')&&microtime(true)<$deadline){usleep(100000);}
        }
        echo "$passed passed, $failed failed\n";idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--release-static-only', $argv, true)) {
        require __DIR__ . '/release-static-http-cases.php';
        echo "$passed passed, $failed failed\n";idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--audience-retry-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
        $sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
        require __DIR__.'/audience-retry-http-cases.php';
        echo "$passed passed, $failed failed\n";idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--capi-retention-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
        $sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
        require __DIR__.'/capi-retention-http-cases.php';
        echo "$passed passed, $failed failed\n";idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--google-audience-only', $argv, true)) {
        require __DIR__.'/google-audience-http-cases.php';
        echo "$passed passed, $failed failed\n";idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--whatsapp-inbound-only', $argv, true)) {
        require __DIR__.'/whatsapp-inbound-http-cases.php';
        echo "$passed passed, $failed failed\n";idlm_http_cleanup();exit($failed?1:0);
    }
    if (in_array('--capi-retry-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
        $sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
        require __DIR__.'/capi-retry-http-cases.php';
        echo "$passed passed, $failed failed\n";
        if (in_array('--hold',$argv,true)) { echo 'BROWSER URL='.$config['http_url'].' STOP='.$config['content_dir']."/stop\n";$deadline=microtime(true)+1800;while(!is_file($config['content_dir'].'/stop') && microtime(true)<$deadline){usleep(100000);} }
        idlm_http_cleanup();exit($failed?1:0);
    }
    // Task presentation checks use actual shared assets and owned synthetic leads.
    if (in_array('--task-ui-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login', array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'), 'admin')['status']===302, 'Administrator login failed');
        require __DIR__ . '/task-accessibility-http-cases.php';
        echo "$passed passed, $failed failed\n";
        $task_evidence = getenv('IDLM_TEST_TASK_UI_EVIDENCE_DIR');
        if ($task_evidence) { file_put_contents($task_evidence.'/browser-fixture.json', wp_json_encode(array('url'=>$config['http_url'],'lead_id'=>$leads[4],'database'=>$config['db_name'],'content_dir'=>$config['content_dir']))); }
        echo 'BROWSER URL='.$config['http_url'].' LEAD='.$leads[4].' STOP='.$config['content_dir']."/stop\n";
        if (in_array('--hold', $argv, true)) { $deadline=microtime(true)+1800; while (!is_file($config['content_dir'].'/stop') && microtime(true)<$deadline) { usleep(100000); } }
        idlm_http_cleanup(); exit($failed ? 1 : 0);
    }
    if (in_array('--bootstrap-input-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login', array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'), 'admin')['status']===302, 'Administrator login failed');
        $sessions['admin'] = json_decode(idlm_http_request('/session', array(), 'admin', false)['body'], true);
        require __DIR__ . '/bootstrap-request-http-cases.php';
        echo "$passed passed, $failed failed\n";
        idlm_http_cleanup(); exit($failed ? 1 : 0);
    }
    if (in_array('--catalogue-import-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login', array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'), 'admin')['status']===302, 'Administrator login failed');
        $sessions['admin'] = json_decode(idlm_http_request('/session', array(), 'admin', false)['body'], true);
        require __DIR__ . '/catalogue-import-http-cases.php';
        if (in_array('--hold', $argv, true)) {
            echo 'BROWSER URL='.$config['http_url'].' STOP='.$config['content_dir']."/stop\n";
            $deadline=microtime(true)+1800;
            while (!is_file($config['content_dir'].'/stop') && microtime(true)<$deadline) { usleep(100000); }
        }
        echo "$passed passed, $failed failed\n";
        idlm_http_cleanup(); exit($failed ? 1 : 0);
    }
    if (in_array('--request-input-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login', array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'), 'admin')['status']===302, 'Administrator login failed');
        $sessions['admin'] = json_decode(idlm_http_request('/session', array(), 'admin', false)['body'], true);
        require __DIR__ . '/quotation-request-input-http-cases.php';
        $errors = is_file($config['content_dir'] . '/php-errors.log') ? file_get_contents($config['content_dir'] . '/php-errors.log') : '';
        idlm_http_case('T11-H-REL007-request-inputs-without-PHP-diagnostics', static function() use ($errors): void { idlm_http_check(trim($errors) === '', 'PHP diagnostics: ' . $errors); });
        echo "$passed passed, $failed failed\n";
        idlm_http_cleanup(); exit($failed ? 1 : 0);
    }
    // Focused document/form checks use the same owned native/HTTP setup and cleanup.
    if (in_array('--paper-only', $argv, true) || in_array('--quantity-only', $argv, true) || in_array('--text-only', $argv, true)) {
        idlm_http_check(idlm_http_request('/login', array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'), 'admin')['status']===302, 'Administrator login failed');
        $sessions['admin']=json_decode(idlm_http_request('/session', array(), 'admin', false)['body'], true);
        require __DIR__ . (in_array('--text-only', $argv, true) ? '/quotation-text-http-cases.php' : (in_array('--quantity-only', $argv, true) ? '/quotation-quantity-http-cases.php' : '/quotation-paper-http-cases.php'));
        if (in_array('--text-only', $argv, true)) {
            $errors = is_file($config['content_dir'] . '/php-errors.log') ? file_get_contents($config['content_dir'] . '/php-errors.log') : '';
            idlm_http_case('T10-H-REL007-literal-text-requests-without-PHP-diagnostics', static function() use ($errors): void { idlm_http_check(trim($errors) === '', 'PHP diagnostics: ' . $errors); });
        }
        echo "$passed passed, $failed failed\n";
        if (in_array('--hold', $argv, true)) { echo 'BROWSER URL='.$config['http_url'].' STOP='.$config['content_dir']."/stop\n"; $deadline=microtime(true)+1800; while (!is_file($config['content_dir'].'/stop') && microtime(true)<$deadline) { usleep(100000); } }
        idlm_http_cleanup(); exit($failed ? 1 : 0);
    }
    $initial = array(); $quotes = array(); $revisions = array();
    foreach (array(0 => 'owner',1 => 'other',2 => 'readall',3 => 'manager',4 => 'owner') as $i => $actor) {
        idlm_http_case('T10-H-authorized-create-' . $actor . '-' . $i, static function() use ($i,$actor,&$initial,&$quotes,&$revisions,$leads): void {
            $initial[$i] = idlm_http_payload($leads[$i]); $r = idlm_http_save($initial[$i],$actor);
            idlm_http_check($r['status'] === 302 && strpos(urldecode($r['headers']), 'Quotation saved') !== false, 'Save did not succeed: ' . $r['body'] . $r['headers']);
            $q = IDLM_Quotation_Repository::get_quote_for_lead($leads[$i]); idlm_http_check((bool) $q, 'Missing persisted quote');
            $quotes[$i] = (int)$q->id; $revisions[$i] = (int)$q->current_revision_id;
            $rev = IDLM_Quotation_Repository::get_revision($revisions[$i]); idlm_http_check((int)$rev->quote_id === $quotes[$i] && (float)$rev->grand_total === 118.0, 'Wrong revision binding/totals');
        });
    }
    idlm_http_check(count($quotes) === 5, 'Required happy paths failed');
    foreach (array('owner','other','readall') as $actor) {
        $foreign = $actor === 'other' ? 0 : 1;
        foreach (array(false,true) as $discard) {
            idlm_http_case('T10-H-deny-foreign-' . ($discard?'discard':'autosave') . '-' . $actor, static function() use($leads,$quotes,$revisions,$foreign,$actor,$discard): void { idlm_http_denied(static function() use($leads,$quotes,$revisions,$foreign,$actor,$discard) { return idlm_http_autosave(idlm_http_payload($leads[$foreign],$quotes[$foreign],$revisions[$foreign]),$actor,$discard); }); });
        }
        idlm_http_case('T10-H-deny-foreign-save-' . $actor, static function() use($leads,$quotes,$revisions,$foreign,$actor): void { idlm_http_denied(static function() use($leads,$quotes,$revisions,$foreign,$actor) { return idlm_http_save(idlm_http_payload($leads[$foreign],$quotes[$foreign],$revisions[$foreign]),$actor); }); });
    }
    foreach (array('foreign-revision-new' => array(5,0,1), 'owned-revision-new' => array(5,0,0), 'foreign-quote' => array(0,1,1), 'foreign-revision' => array(0,0,1)) as $id => $v) {
        $payload = idlm_http_payload($leads[$v[0]], $v[1] ? $quotes[$v[1]] : ($v[0] === 0 ? $quotes[0] : 0), $revisions[$v[2]]);
        idlm_http_case('T10-H-' . $id, static function() use($payload): void { idlm_http_denied(static function() use($payload) { return idlm_http_save($payload); }); });
    }
    foreach (array('lead' => array('lead_id'=>$leads[1]),'quote'=>array('quote_id'=>$quotes[1]),'revision'=>array('revision_id'=>$revisions[1])) as $id=>$override) {
        $payload=array_merge(idlm_http_payload($leads[0],$quotes[0],$revisions[0]),$override);
        idlm_http_case('T10-H-autosave-substitute-' . $id, static function() use($payload): void { idlm_http_denied(static function() use($payload) { return idlm_http_autosave($payload); }); });
    }
    foreach (array('quote'=>array('quote_id'=>$quotes[1]),'revision'=>array('revision_id'=>$revisions[1])) as $id=>$override) {
        $p=array_merge(idlm_http_payload($leads[0],$quotes[0],$revisions[0]),$override);
        idlm_http_case('T10-H-discard-substitute-'.$id,static function() use($p): void { idlm_http_denied(static function() use($p) { return idlm_http_autosave($p,'owner',true); }); });
    }
    idlm_http_case('T10-H-anonymous-save', static function() use($leads): void { idlm_http_denied(static function() use($leads) { return idlm_http_request('/wp-admin/admin-post.php',idlm_http_payload($leads[5]),'anonymous'); },array(400,403)); });
    idlm_http_case('T10-H-missing-lead', static function(): void { idlm_http_denied(static function() { return idlm_http_save(idlm_http_payload(999999)); }); });
    idlm_http_case('T10-H-missing-save-nonce',static function() use($leads): void { idlm_http_denied(static function() use($leads) { return idlm_http_request('/wp-admin/admin-post.php',idlm_http_payload($leads[5])); }); });
    idlm_http_case('T10-H-cross-user-autosave-nonce',static function() use($leads,$quotes,$revisions,$sessions): void { idlm_http_denied(static function() use($leads,$quotes,$revisions,$sessions) { $p=idlm_http_payload($leads[0],$quotes[0],$revisions[0]); $p['action']='idlm_autosave_quote'; $p['nonce']=$sessions['other']['autosave']; return idlm_http_request('/wp-admin/admin-ajax.php',$p); }); });
    foreach (array('foreign-key'=>array(idlm_http_payload($leads[5]),$initial[1]['save_request_key'],'owner'), 'owned-other-lead-key'=>array(idlm_http_payload($leads[5]),$initial[0]['save_request_key'],'owner'), 'different-actor-key'=>array($initial[0],$initial[0]['save_request_key'],'manager')) as $id=>$v) {
        $v[0]['save_request_key']=$v[1]; idlm_http_case('T10-H-' . $id, static function() use($v): void { idlm_http_denied(static function() use($v) { return idlm_http_save($v[0],$v[2]); }); });
    }
    idlm_http_case('T10-H-repeat-initial-request',static function() use($initial): void { $before=idlm_http_snapshot(); $r=idlm_http_save($initial[0]); idlm_http_check($r['status']===302 && strpos(urldecode($r['headers']),'already completed')!==false,'Repeat not recognized'); idlm_http_check($before===idlm_http_snapshot(),'Repeat wrote data'); });
    foreach (array('other','readall') as $actor) {
        idlm_http_case('T10-H-foreign-new-lead-' . $actor, static function() use($leads,$actor): void { idlm_http_denied(static function() use($leads,$actor) { return idlm_http_save(idlm_http_payload($leads[5]),$actor); }); });
    }
    idlm_http_case('T10-H-readall-owner-autosave-revision', static function() use($leads,$quotes,$revisions): void {
        $own=idlm_http_payload($leads[2],$quotes[2],$revisions[2]);
        idlm_http_check(idlm_http_autosave($own,'readall')['status']===200, 'Read-all current owner autosave failed');
        $r=idlm_http_save($own,'readall'); idlm_http_check($r['status']===302 && strpos(urldecode($r['headers']),'New revision saved')!==false,'Read-all current owner revision failed');
    });
    $payload=idlm_http_payload($leads[0],$quotes[0],$revisions[0]); $payload['items'][0]['description']='SYNTHETIC-ITEM-REVISED';
    idlm_http_case('T10-H-owner-autosave-and-discard',static function() use($payload,$users,$revisions): void {
        $r=idlm_http_autosave($payload); idlm_http_check($r['status']===200 && json_decode($r['body'],true)['success'],'Autosave failed');
        idlm_http_check(is_array(get_transient('idlm_quote_autosave_'.$users['owner'].'_'.$revisions[0])),'Missing user/revision scoped working copy');
        $r=idlm_http_autosave($payload,'owner',true); idlm_http_check($r['status']===200,'Discard failed'); wp_cache_flush(); idlm_http_check(false===get_transient('idlm_quote_autosave_'.$users['owner'].'_'.$revisions[0]),'Discard left working copy');
    });
    idlm_http_case('T10-H-owner-revision-and-repeat',static function() use($payload,$quotes,$revisions): void { $r=idlm_http_save($payload); idlm_http_check($r['status']===302 && strpos(urldecode($r['headers']),'New revision saved')!==false,'Revision failed'); $q=IDLM_Quotation_Repository::get_quote($quotes[0]); $rev=IDLM_Quotation_Repository::get_revision($q->current_revision_id); idlm_http_check((int)$rev->source_revision_id===$revisions[0] && $rev->revision_number==2,'Bad revision graph'); $before=idlm_http_snapshot(); $r=idlm_http_save($payload); idlm_http_check(strpos(urldecode($r['headers']),'already completed')!==false && $before===idlm_http_snapshot(),'Revision retry changed state'); });
    $current=(int)IDLM_Quotation_Repository::get_quote($quotes[0])->current_revision_id;
    $wrong_source=idlm_http_payload($leads[0],$quotes[0],$current); $wrong_source['save_request_key']=$payload['save_request_key'];
    idlm_http_case('T10-H-request-key-source-substitution',static function() use($wrong_source): void { idlm_http_denied(static function() use($wrong_source) { return idlm_http_save($wrong_source); }); });
    idlm_http_case('T10-H-stale-revision-no-write',static function() use($leads,$quotes,$revisions): void { $before=idlm_http_snapshot(); $r=idlm_http_save(idlm_http_payload($leads[0],$quotes[0],$revisions[0])); idlm_http_check($r['status']===302 && strpos($r['headers'],'idlm_error')!==false && $before===idlm_http_snapshot(),'Stale source accepted'); });
    foreach (array('owner','readall','manager') as $actor) {
        idlm_http_case('T12-H-exact-old-revision-export-' . $actor,static function() use($quotes,$revisions,$actor,$sessions): void { $r=idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_csv','quote_id'=>$quotes[0],'revision_id'=>$revisions[0],'_wpnonce'=>$sessions[$actor]['csv']),$actor,false); $csv=fopen('php://temp','w+'); fwrite($csv,$r['body']); rewind($csv); $parsed=array(); while (($row=fgetcsv($csv,0,',','"',''))!==false) { $parsed[]=$row; } fclose($csv);
            $lines=array_values(array_filter($parsed,static function($row) { return count($row)===9 && ($row[2]??'')==='SYNTHETIC-ITEM-ORIGINAL'; }));
            idlm_http_check(count($lines)===1 && (float)$lines[0][5]===1.0 && (float)$lines[0][8]===100.0,'Parsed CSV exact revision values differ');
            idlm_http_check($r['status']===200 && strpos($r['headers'],'text/csv')!==false && strpos($r['body'],'SYNTHETIC-ITEM-ORIGINAL')!==false && strpos($r['body'],'SYNTHETIC-ITEM-REVISED')===false && strpos($r['body'],'PRIVATE-SYNTHETIC-NOTES')===false,'Export did not select persisted exact revision'); });
    }
    foreach (array('foreign-revision'=>array('owner',$revisions[1]),'non-owner'=>array('other',$revisions[0])) as $id=>$v) { idlm_http_case('T12-H-deny-' . $id,static function() use($quotes,$sessions,$v): void { idlm_http_denied(static function() use($quotes,$sessions,$v) { return idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_csv','quote_id'=>$quotes[0],'revision_id'=>$v[1],'_wpnonce'=>$sessions[$v[0]]['csv']),$v[0],false); }); }); }
    // Read-all visibility survives, edit controls and direct editor access do not.
    idlm_http_case('T10-H-readall-read-without-editor',static function() use($quotes): void { $r=idlm_http_request('/workspace',array('action'=>'view','quote_id'=>$quotes[1]),'readall',false); idlm_http_check($r['status']===200 && strpos($r['body'],'PII-SYNTHETIC')!==false && strpos($r['body'],'Edit Quotation')===false,'Read/edit UI separation failed'); $r=idlm_http_request('/workspace',array('action'=>'edit','quote_id'=>$quotes[1]),'readall',false); idlm_http_check($r['status']===302 && strpos($r['headers'],'read-only')!==false,'Editor not denied'); });
    $live_payload=idlm_http_payload($leads[0],$quotes[0],$current);
    // Reassignment after obtaining a nonce and working-copy URL.
    $wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['other']),array('id'=>$leads[0]));
    foreach (array('save','autosave','discard','export') as $op) { idlm_http_case('T10-H-reassigned-former-owner-' . $op,static function() use($op,$live_payload,$quotes,$current,$sessions): void { idlm_http_denied(static function() use($op,$live_payload,$quotes,$current,$sessions) { if ($op==='save') { return idlm_http_save($live_payload); } if ($op==='export') { return idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_csv','quote_id'=>$quotes[0],'revision_id'=>$current,'_wpnonce'=>$sessions['owner']['csv']),'owner',false); } return idlm_http_autosave($live_payload,'owner',$op==='discard'); }); }); }
    idlm_http_case('T10-H-reassigned-new-owner-autosave-and-save',static function() use($live_payload): void { idlm_http_check(idlm_http_autosave($live_payload,'other')['status']===200,'New owner cannot autosave'); $r=idlm_http_save($live_payload,'other'); idlm_http_check($r['status']===302 && strpos(urldecode($r['headers']),'New revision saved')!==false,'New owner cannot save'); });
    $live_payload=idlm_http_payload($leads[0],$quotes[0],(int)IDLM_Quotation_Repository::get_quote($quotes[0])->current_revision_id);
    update_option('idlm_role_lead_scopes', array('sales_executive'=>'assigned','author'=>'all','sales_manager'=>'assigned'));
    idlm_http_case('T10-H-manager-assigned-read-scope-save-autosave',static function() use($live_payload): void { idlm_http_check(idlm_http_autosave($live_payload,'manager')['status']===200,'Explicit manager cannot autosave'); $r=idlm_http_save($live_payload,'manager'); idlm_http_check($r['status']===302 && strpos(urldecode($r['headers']),'New revision saved')!==false,'Explicit manager cannot save'); });
    $manager_payload=idlm_http_payload($leads[0],$quotes[0],(int)IDLM_Quotation_Repository::get_quote($quotes[0])->current_revision_id);
    (new WP_User($users['manager']))->add_cap('idlm_assign_leads',false);
    idlm_http_case('T10-H-manager-grant-revoked',static function() use($manager_payload): void { idlm_http_denied(static function() use($manager_payload) { return idlm_http_autosave($manager_payload,'manager'); }); });
    (new WP_User($users['manager']))->add_cap('idlm_assign_leads');
    // Revoked capability in a cookie-authenticated session must remain revoked.
    $u=new WP_User($users['owner']); $u->add_cap('idlm_create_quotes',false); $u->add_cap('idlm_create_quote_revisions',false); $u->add_cap('idlm_download_quote_pdf',false);
    foreach (array('save','autosave','export') as $op) { idlm_http_case('T10-H-revoked-session-' . $op,static function() use($op,$leads,$quotes,$revisions,$sessions): void { idlm_http_denied(static function() use($op,$leads,$quotes,$revisions,$sessions) { if ($op==='save') { return idlm_http_save(idlm_http_payload($leads[5])); } if ($op==='export') { return idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_csv','quote_id'=>$quotes[4],'revision_id'=>$revisions[4],'_wpnonce'=>$sessions['owner']['csv']),'owner',false); } return idlm_http_autosave(idlm_http_payload($leads[4],$quotes[4],$revisions[4])); }); }); }
    $u->add_cap('idlm_create_quotes'); $u->add_cap('idlm_create_quote_revisions'); $u->add_cap('idlm_download_quote_pdf');
    update_user_meta($users['owner'],'idlm_crm_access_disabled','1');
    foreach (array('save','autosave','discard','export') as $operation) {
        idlm_http_case('T10-H-disabled-user-'.$operation,static function() use($operation,$leads,$quotes,$revisions,$sessions): void {
            idlm_http_denied(static function() use($operation,$leads,$quotes,$revisions,$sessions) {
                if ($operation==='save') { return idlm_http_save(idlm_http_payload($leads[5])); }
                if ($operation==='export') { return idlm_http_request('/wp-admin/admin-post.php',array('action'=>'idlm_download_quote_csv','quote_id'=>$quotes[4],'revision_id'=>$revisions[4],'_wpnonce'=>$sessions['owner']['csv']),'owner',false); }
                return idlm_http_autosave(idlm_http_payload($leads[4],$quotes[4],$revisions[4]),'owner',$operation==='discard');
            });
        });
    }
    delete_user_meta($users['owner'],'idlm_crm_access_disabled');
    // Independent process + file barrier: authority changes between initial
    // check and the locking read, rather than sequential requests called a race.
    foreach (array('create','autosave','discard') as $operation) {
        foreach (array('reassignment','revocation','role-revocation') as $mode) {
            idlm_http_case('T10-C-before-write-lock-'.$operation.'-'.$mode,static function() use($mode,$operation,$leads,$quotes,$revisions,$users,$config,$sessions): void {
                global $wpdb;
                $target=$operation==='create' ? $leads[6] : $leads[4];
                $barrier=$config['content_dir'].'/barrier'; file_put_contents($barrier,$mode);
                $payload=$operation==='create' ? idlm_http_payload($target) : idlm_http_payload($target,$quotes[4],$revisions[4]);
                $payload[$operation==='create'?'_wpnonce':'nonce']=$sessions['owner'][$operation==='create'?'save':'autosave'];
                if ($operation!=='create') { $payload['action']=$operation==='autosave'?'idlm_autosave_quote':'idlm_discard_quote_autosave'; }
                $url=$config['http_url'].'/wp-admin/'.($operation==='create'?'admin-post.php':'admin-ajax.php');
                $cap=$operation==='create'?'idlm_create_quotes':'idlm_create_quote_revisions';
                $actor=new WP_User($users['owner']);
                if ($mode==='role-revocation') { $actor->remove_cap($cap); }
                $command=array('curl','--silent','--show-error','--max-time','15','--cookie',$config['content_dir'].'/owner.cookies','--data',http_build_query($payload),'--output',$barrier.'.body','--write-out','%{http_code}',$url);
                $process=proc_open(implode(' ',array_map('escapeshellarg',$command)),array(1=>array('pipe','w'),2=>array('pipe','w')),$pipes);
                idlm_http_check(is_resource($process),'Cannot start independent HTTP worker');
                try {
                    $deadline=microtime(true)+10; while (!is_file($barrier.'.ready') && microtime(true)<$deadline) { usleep(10000); }
                    idlm_http_check(is_file($barrier.'.ready'),'HTTP worker did not reach locking read');
                    if ($mode==='reassignment') { $wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['other']),array('id'=>$target)); }
                    elseif ($mode==='revocation') { $actor->add_cap($cap,false); }
                    else { get_role('sales_executive')->remove_cap($cap); }
                    $before=idlm_http_snapshot(); file_put_contents($barrier.'.release','go');
                    $status=(int)stream_get_contents($pipes[1]); $error=stream_get_contents($pipes[2]);
                    idlm_http_check(in_array($status,array(403,404,409),true) && $before===idlm_http_snapshot(),'Stale authority wrote after lock: '.$status.' '.$error);
                    idlm_http_check(strpos(file_get_contents($barrier.'.body'),'PII-SYNTHETIC')===false,'Barrier denial disclosed PII');
                } finally {
                    file_put_contents($barrier.'.release','go');
                    if (is_resource($process)) { proc_terminate($process); fclose($pipes[1]); fclose($pipes[2]); proc_close($process); }
                    foreach (glob($barrier.'*') as $file) { unlink($file); }
                    $wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['owner']),array('id'=>$target));
                    get_role('sales_executive')->add_cap($cap); $actor->add_cap($cap);
                }
            });
        }
    }
    require __DIR__ . '/lead-read-cases.php';
    require __DIR__ . '/mcp-http-cases.php';
    require __DIR__ . '/lead-csv-cases.php';
    require __DIR__ . '/backup-http-cases.php';
    require __DIR__ . '/quotation-classification-http-cases.php';
    require __DIR__ . '/quotation-history-http-cases.php';
    require __DIR__ . '/quotation-engine-http-cases.php';
    require __DIR__ . '/catalogue-import-http-cases.php';
    require __DIR__ . '/quotation-numeric-http-cases.php';
    require __DIR__ . '/quotation-request-input-http-cases.php';
    require __DIR__ . '/release-static-http-cases.php';
    require __DIR__ . '/quotation-document-http-cases.php';
    require __DIR__ . '/quotation-paper-http-cases.php';
    require __DIR__ . '/quotation-quantity-http-cases.php';
    require __DIR__ . '/task-accessibility-http-cases.php';
    require __DIR__ . '/private-file-cases.php';
    require __DIR__ . '/schema-http-cases.php';
    require __DIR__ . '/bootstrap-request-http-cases.php';
    require __DIR__ . '/quotation-migration-http-cases.php';
    require __DIR__ . '/lifecycle-http-cases.php';
    require __DIR__ . '/task-plan-repair-http-cases.php';
    require __DIR__ . '/assignment-eligibility-http-cases.php';
    require __DIR__ . '/capacity-http-cases.php';
    require __DIR__ . '/identity-review-http-cases.php';
    require __DIR__ . '/bounded-work-http-cases.php';
    require __DIR__ . '/kanban-pagination-http-cases.php';
    idlm_http_check(idlm_http_request('/login',array('user_login'=>'idlm_admin','user_password'=>'idlm-integration-password'),'admin')['status']===302,'Administrator login failed');
    $sessions['admin']=json_decode(idlm_http_request('/session',array(),'admin',false)['body'],true);
    require __DIR__ . '/quotation-text-http-cases.php';
    require __DIR__ . '/capi-retry-http-cases.php';
    require __DIR__ . '/capi-retention-http-cases.php';
    require __DIR__ . '/whatsapp-inbound-http-cases.php';
    require __DIR__ . '/google-audience-http-cases.php';
    require __DIR__ . '/audience-retry-http-cases.php';
    require __DIR__ . '/secret-store-http-cases.php';
    require __DIR__ . '/connection-controller-http-cases.php';
    require __DIR__ . '/telemetry-controller-http-cases.php';
    require __DIR__ . '/activity-controller-http-cases.php';
    require __DIR__ . '/role-permissions-http-cases.php';
    require __DIR__ . '/attribution-opt-in-http-cases.php';
    require __DIR__ . '/notification-control-http-cases.php';
    { $errors=is_file($config['content_dir'].'/php-errors.log') ? file_get_contents($config['content_dir'].'/php-errors.log') : ''; idlm_http_case('T10-H-warning-free-affected-requests',static function() use($errors): void { idlm_http_check(trim($errors)==='', 'PHP diagnostics: ' . $errors); }); }
    echo "$passed passed, $failed failed\n";
    if (in_array('--hold', $argv,true)) { if (getenv('IDLM_TEST_DOCUMENT_EVIDENCE_DIR') && function_exists('idlm_document_http_settings')) { update_option('idlm_quote_settings', idlm_document_http_settings('Changed live', 'USD'), false); } echo 'BROWSER URL='.$config['http_url'].' LEAD='.$leads[4].' QUOTE='.$quotes[4].' STOP='.$config['content_dir']."/stop\n"; $deadline=microtime(true)+900; while (!is_file($config['content_dir'].'/stop') && microtime(true)<$deadline) { usleep(100000); } }
    idlm_http_cleanup(); exit($failed?1:0);
} catch (Throwable $e) { fwrite(STDERR,'HTTP fixture failed: '.$e->getMessage()."\n"); idlm_http_cleanup(); exit(1); }
