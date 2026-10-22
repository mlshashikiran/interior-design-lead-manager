<?php
/** M6 actual administrator connection-diagnostic contracts. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }
function idlm_connection_http(array $fields, string $actor = 'admin', ?string $nonce = null): array {
    global $sessions;
    return idlm_http_request('/wp-admin/admin-ajax.php', array('action'=>'idlm_test_api_connection','nonce'=>$nonce ?? ($sessions[$actor]['admin'] ?? 'invalid')) + $fields, $actor);
}
idlm_http_case('M6-H-connection-nonce-and-administrator-denials-have-no-side-effects', static function(): void {
    // Prime WordPress admin_init scheduling without invoking any plugin handler.
    idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php', array('action'=>'idlm_fixture_unregistered'), 'admin')['status'] === 400, 'Cannot prime administrative bootstrap');
    $before = idlm_http_snapshot();
    $fields = array('platform'=>'meta','target_id'=>'synthetic-target','access_token'=>'synthetic-denied');
    idlm_http_check(idlm_connection_http($fields, 'admin', 'bad-nonce')['status'] === 403, 'Invalid nonce was accepted');
    idlm_http_check($before === idlm_http_snapshot(), 'Invalid-nonce request changed snapshot');
    $response = idlm_connection_http($fields, 'owner'); $json = json_decode($response['body'], true);
    idlm_http_check($response['status'] === 200 && $json === array('success'=>false,'data'=>array('message'=>'Access denied')), 'Representative denial changed');
    idlm_http_check($before === idlm_http_snapshot(), 'Representative request changed snapshot');
    idlm_http_check(idlm_connection_http($fields, 'anonymous')['status'] === 400, 'Anonymous action became callable');
    idlm_http_check($before === idlm_http_snapshot(), 'Denied connection test wrote CRM/jobs or contacted a provider');
});
idlm_http_case('M6-H-connection-local-validation-and-Google-credential-fallback-stay-read-only', static function(): void {
    $previous_user = get_option('idlm_google_gadm_username', false); $previous_secret = get_option('idlm_google_gadm_password', false);
    try {
        update_option('idlm_google_gadm_username', 'synthetic-user'); idlm_http_check(IDLM_Secret_Store::set('idlm_google_gadm_password','synthetic-local-google-secret'), 'Cannot set synthetic credential');
        $before = idlm_http_snapshot(); $stored = IDLM_Secret_Store::stored('idlm_google_gadm_password');
        foreach (array(
            array(array('platform'=>'','target_id'=>'synthetic'), false, 'Missing platform or target ID to test.'),
            array(array('platform'=>'unsupported','target_id'=>'synthetic'), false, 'Unsupported platform connection test.'),
            array(array('platform'=>'google','target_id'=>'synthetic','google_gadm_username'=>'ab','google_gadm_password'=>'tiny'), false, 'Credentials format error: Username must be at least 3 chars and Password must be at least 8 chars.'),
            array(array('platform'=>'google','target_id'=>'synthetic','google_gadm_username'=>'','google_gadm_password'=>''), true, 'Google Ads Data Manager source credentials verified! Feeds are active and secured under username "synthetic-user".'),
            array(array('platform'=>'google','target_id'=>'synthetic','google_gadm_username'=>'posted-user','google_gadm_password'=>'synthetic-posted-secret'), true, 'Google Ads Data Manager source credentials verified! Feeds are active and secured under username "posted-user".'),
        ) as $case) {
            $r = idlm_connection_http($case[0]); $json = json_decode($r['body'], true);
            idlm_http_check($r['status'] === 200 && $json === array('success'=>$case[1],'data'=>array('message'=>$case[2])), 'Local connection response changed: '.$r['body']);
            idlm_http_check(strpos($r['body'],'synthetic-local-google-secret') === false && strpos($r['body'],'synthetic-posted-secret') === false, 'Credential appeared in local response');
        }
        idlm_http_check($before === idlm_http_snapshot() && $stored === IDLM_Secret_Store::stored('idlm_google_gadm_password'), 'Local test persisted credentials or changed CRM/jobs');
    } finally {
        $previous_user === false ? delete_option('idlm_google_gadm_username') : update_option('idlm_google_gadm_username', $previous_user);
        $previous_secret === false ? delete_option('idlm_google_gadm_password') : update_option('idlm_google_gadm_password', $previous_secret);
    }
});
idlm_http_case('M6-H-connection-provider-success-retains-stored-header-auth-and-messages', static function(): void {
    global $config;
    $previous = array();
    try {
        foreach (array('meta','linkedin') as $p) { $previous[$p] = get_option('idlm_'.$p.'_access_token', false); idlm_http_check(IDLM_Secret_Store::set('idlm_'.$p.'_access_token','synthetic-M6-CONNECTION-'.$p), 'Cannot set provider fixture'); }
        file_put_contents($config['content_dir'].'/secret-provider.json', '{"result":"success"}');
        foreach (array(array('meta','capi','Meta Conversions API connection successful! Verified Pixel Name: "Synthetic verified target".'),array('meta','audience','Meta Custom Audience sync connection successful! Verified Audience Name: "Synthetic verified target" (CUSTOM).'),array('linkedin','audience','LinkedIn connection successful! Verified Segment Name: "Synthetic verified target".')) as $case) {
            $r = idlm_connection_http(array('platform'=>$case[0],'test_type'=>$case[1],'target_id'=>'synthetic/target','access_token'=>'')); $json = json_decode($r['body'], true);
            idlm_http_check($r['status'] === 200 && $json === array('success'=>true,'data'=>array('message'=>$case[2])), 'Provider success response changed');
            idlm_http_check(json_decode(file_get_contents($config['content_dir'].'/secret-provider-check.json'),true) === array('url_secret'=>false,'header_correct'=>true,'redirects'=>0), 'Stored credential transport changed');
        }
    } finally {
        foreach ($previous as $p=>$old) { $old === false ? delete_option('idlm_'.$p.'_access_token') : update_option('idlm_'.$p.'_access_token',$old); }
        @unlink($config['content_dir'].'/secret-provider.json'); @unlink($config['content_dir'].'/secret-provider-check.json');
    }
});
idlm_http_case('M6-H-connection-provider-errors-retain-shape-and-redact-credential-echoes', static function(): void {
    global $config;
    $previous = array();
    try {
        foreach (array('meta','linkedin') as $p) { $previous[$p] = get_option('idlm_'.$p.'_access_token', false); idlm_http_check(IDLM_Secret_Store::set('idlm_'.$p.'_access_token','synthetic-M6-CONNECTION-'.$p), 'Cannot set provider fixture'); }
        foreach (array('api_error','transport_error') as $result) {
            file_put_contents($config['content_dir'].'/secret-provider.json', wp_json_encode(array('result'=>$result)));
            foreach (array('meta','linkedin') as $platform) {
                $r = idlm_connection_http(array('platform'=>$platform,'test_type'=>'capi','target_id'=>'synthetic-target','access_token'=>'')); $json = json_decode($r['body'],true);
                idlm_http_check($r['status'] === 200 && $json['success'] === false && !empty($json['data']['message']), 'Provider failure response changed');
                idlm_http_check(strpos($r['body'],'synthetic-M6-CONNECTION-') === false, 'Provider error exposed a credential');
                if ($platform === 'meta' && $result === 'api_error') { idlm_http_check(strpos($json['data']['message'],'(Subcode: 42)') !== false, 'Meta error subcode was lost'); }
            }
        }
    } finally {
        foreach ($previous as $p=>$old) { $old === false ? delete_option('idlm_'.$p.'_access_token') : update_option('idlm_'.$p.'_access_token',$old); }
        @unlink($config['content_dir'].'/secret-provider.json'); @unlink($config['content_dir'].'/secret-provider-check.json');
    }
});
