<?php
/** REL-007 request-shape and raw authentication contracts. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

idlm_http_case('T12-H-REL007-export-identifiers-reject-arrays-and-nondecimal-text', static function(): void {
    global $wpdb, $users, $sessions;
    $now = current_time('mysql');
    idlm_http_check(1 === $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name'=>'Synthetic export ID contract','assigned_rep_id'=>$users['owner'],'created_at'=>$now,'updated_at'=>$now)), 'Cannot create export-ID fixture');
    $lead_id = (int) $wpdb->insert_id;
    idlm_http_check(idlm_http_save(idlm_http_payload($lead_id))['status'] === 302, 'Cannot save export-ID fixture');
    $quote = IDLM_Quotation_Repository::get_quote_for_lead($lead_id);
    idlm_http_check((bool) $quote, 'Missing export-ID fixture quote');
    $quote_id = $quote->id; $revision_id = $quote->current_revision_id;
    $before = idlm_http_snapshot();
    foreach (array(array($quote_id), '-' . $quote_id, $quote_id . '.0', $quote_id . 'e0') as $bad) {
        foreach (array(array($bad, $revision_id), array($quote_id, $bad)) as $ids) {
            $response = idlm_http_request('/wp-admin/admin-post.php', array('action' => 'idlm_download_quote_csv', '_wpnonce' => $sessions['owner']['csv'], 'quote_id' => $ids[0], 'revision_id' => $ids[1]), 'owner', false);
            idlm_http_check($response['status'] === 404, 'Malformed identifier was coerced to a quotation');
            idlm_http_check(strpos($response['body'], 'PRIVATE-SYNTHETIC') === false && $before === idlm_http_snapshot(), 'Rejected export disclosed data or changed state');
        }
    }
    $response = idlm_http_request('/wp-admin/admin-post.php', array('action' => 'idlm_download_quote_csv', '_wpnonce' => $sessions['owner']['csv'], 'quote_id' => '00' . $quote_id, 'revision_id' => '00' . $revision_id), 'owner', false);
    idlm_http_check($response['status'] === 200 && $before === idlm_http_snapshot(), 'Valid decimal identifiers with leading zeros stopped working');
});

idlm_http_case('T01-H-REL007-production-login-retains-literal-password-and-rejects-array-without-diagnostics', static function(): void {
    global $config;
    $password = "Synthetic C:\\secret\\O'Brien<&>";
    $id = wp_insert_user(array('user_login' => 'rel007_literal_login', 'user_email' => 'rel007-literal@example.test', 'user_pass' => $password, 'role' => 'subscriber'));
    idlm_http_check(!is_wp_error($id), 'Cannot create synthetic authentication actor');
    $errors = is_file($config['content_dir'] . '/php-errors.log') ? file_get_contents($config['content_dir'] . '/php-errors.log') : '';
    $page = idlm_http_request('/crm-workspace', array(), 'literal-login', false);
    idlm_http_check((bool) preg_match('/name="_wpnonce" value="([^"]+)"/', $page['body'], $match), 'Missing production login nonce');
    $payload = array('action' => 'idlm_login', '_wpnonce' => $match[1], 'idlm_email' => 'rel007-literal@example.test', 'idlm_password' => array($password));
    $before = idlm_http_snapshot();
    $response = idlm_http_request('/wp-admin/admin-post.php', $payload, 'literal-login');
    idlm_http_check($response['status'] === 302 && strpos($response['headers'], 'invalid-login') !== false, 'Malformed credential was not rejected');
    idlm_http_check($before === idlm_http_snapshot(), 'Rejected credential changed CRM/jobs');
    $payload['idlm_password'] = $password;
    $response = idlm_http_request('/wp-admin/admin-post.php', $payload, 'literal-login');
    idlm_http_check($response['status'] === 302 && strpos($response['headers'], 'idlm_auth_error') === false, 'Literal password bytes were altered');
    idlm_http_check(idlm_http_request('/session', array(), 'literal-login', false)['status'] === 200, 'Production login did not establish an authenticated session');
    $after = is_file($config['content_dir'] . '/php-errors.log') ? file_get_contents($config['content_dir'] . '/php-errors.log') : '';
    idlm_http_check($errors === $after, 'Malformed credential produced PHP diagnostics');
});
