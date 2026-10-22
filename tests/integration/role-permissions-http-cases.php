<?php
/** Production role-permission boundary regressions. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

function idlm_role_permissions_http(array $fields, string $actor = 'admin', ?string $nonce = null, bool $post = true): array {
    global $sessions;
    return idlm_http_request('/wp-admin/admin-post.php', array('action'=>'idlm_update_role_permissions', '_wpnonce'=>$nonce ?? ($sessions[$actor]['role_permissions'] ?? 'invalid')) + $fields, $actor, $post);
}
function idlm_role_permissions_stored(): string {
    global $wpdb;
    return (string) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $wpdb->prefix.'user_roles'));
}
function idlm_role_permissions_restore(string $serialized): void {
    global $wpdb;
    $wpdb->update($wpdb->options, array('option_value'=>$serialized), array('option_name'=>$wpdb->prefix.'user_roles'));
    wp_cache_delete($wpdb->prefix.'user_roles', 'options'); wp_cache_delete('alloptions', 'options'); wp_roles()->for_site();
}

idlm_http_case('RELEASE-H-role-permissions-deny-nonce-capability-and-get-before-writes', static function(): void {
    idlm_http_request('/wp-admin/admin-ajax.php', array('action'=>'idlm_fixture_unregistered'), 'admin');
    $before = idlm_http_snapshot(); $roles = idlm_role_permissions_stored();
    $fields = array('permission_role'=>'sales_executive', 'permissions'=>array('idlm_access_leads'=>'1'));
    idlm_http_check(idlm_role_permissions_http($fields, 'admin', 'invalid')['status'] === 403, 'Invalid role nonce accepted');
    idlm_http_check(idlm_role_permissions_http($fields, 'owner')['status'] === 403, 'Representative changed role grants');
    idlm_http_check(idlm_role_permissions_http($fields, 'admin', null, false)['status'] === 405, 'GET changed role grants');
    idlm_http_check($before === idlm_http_snapshot() && $roles === idlm_role_permissions_stored(), 'Denied role request changed state');
});

idlm_http_case('RELEASE-H-role-permissions-reject-shared-and-malformed-role-targets', static function(): void {
    $roles = idlm_role_permissions_stored();
    foreach (array('administrator', 'editor', 'author', 'contributor', 'unknown-role', array('sales_executive')) as $role) {
        $r = idlm_role_permissions_http(array('permission_role'=>$role, 'permissions'=>array('manage_options'=>'1')));
        idlm_http_check($r['status'] === 302 && strpos($r['headers'], 'idlm_users_error=invalid-role') !== false, 'Protected role target accepted');
        idlm_http_check($roles === idlm_role_permissions_stored(), 'Protected role target changed grants');
    }
});

idlm_http_case('RELEASE-H-role-permissions-apply-only-allowlisted-grants-and-clear-unselected', static function(): void {
    $before = idlm_role_permissions_stored();
    try {
        $original = maybe_unserialize($before)['sales_executive']['capabilities'];
        $r = idlm_role_permissions_http(array('permission_role'=>'sales_executive', 'permissions'=>array('idlm_access_leads'=>'1', 'idlm_assign_leads'=>'1', 'manage_options'=>'1', 'unfiltered_html'=>'1', 'unknown_permission'=>'1')));
        idlm_http_check($r['status'] === 302 && strpos($r['headers'], 'idlm_users_notice=role-permissions-updated') !== false, 'Valid grants failed');
        $actual = maybe_unserialize(idlm_role_permissions_stored())['sales_executive']['capabilities'];
        foreach (IDLM_Admin_Users_Controller::selectable_permissions('sales_executive') as $cap=>$label) {
            idlm_http_check(!empty($actual[$cap]) === in_array($cap, array('idlm_access_leads', 'idlm_assign_leads'), true), 'Grant mismatch: '.$cap);
        }
        foreach (array('manage_options', 'unfiltered_html', 'unknown_permission') as $cap) {
            idlm_http_check(($actual[$cap] ?? null) === ($original[$cap] ?? null), 'Unlisted grant changed');
        }
    } finally { idlm_role_permissions_restore($before); }
});

idlm_http_case('RELEASE-H-role-permissions-malformed-values-fail-closed-without-diagnostics', static function(): void {
    $before = idlm_role_permissions_stored();
    try {
        foreach (array('malformed', array('idlm_access_leads'=>array('1'), 'idlm_assign_leads'=>'<b>0</b>')) as $permissions) {
            $r = idlm_role_permissions_http(array('permission_role'=>'sales_executive', 'permissions'=>$permissions));
            idlm_http_check($r['status'] === 302, 'Malformed grant request crashed');
            $actual = maybe_unserialize(idlm_role_permissions_stored())['sales_executive']['capabilities'];
            idlm_http_check(empty($actual['idlm_access_leads']) && empty($actual['idlm_assign_leads']), 'Malformed value granted access');
        }
    } finally { idlm_role_permissions_restore($before); }
});
