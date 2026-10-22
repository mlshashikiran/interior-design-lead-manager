<?php
/** M6 activity transport characterization in owned disposable WordPress. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

function idlm_activity_http(string $operation, array $fields = array(), string $actor = 'activity_owner', ?string $nonce = null): array {
    global $sessions;
    return idlm_http_request('/wp-admin/admin-ajax.php', array('action'=>'idlm_'.$operation, 'nonce'=>$nonce ?? ($sessions[$actor]['admin'] ?? 'invalid')) + $fields, $actor);
}
function idlm_activity_message(array $response, bool $success, string $message): void {
    idlm_http_check($response['status'] === 200 && json_decode($response['body'], true) === array('success'=>$success, 'data'=>array('message'=>$message)), 'Activity response changed: '.$response['body']);
}
function idlm_activity_seed(string $type = 'comment', string $actor = 'activity_owner', string $details = 'Synthetic note', int $lead = 0): int {
    global $wpdb, $idlm_activity_users, $idlm_activity_leads;
    idlm_http_check($wpdb->insert($wpdb->prefix.'idlm_lead_activity_log', array('lead_id'=>$lead ?: $idlm_activity_leads[0], 'user_id'=>$idlm_activity_users[$actor], 'activity_type'=>$type, 'details'=>$details, 'created_at'=>current_time('mysql'))) === 1, 'Cannot seed activity');
    return (int) $wpdb->insert_id;
}

// Dedicated actors and leads keep characterization independent of earlier suites.
$idlm_activity_saved_scopes = get_option('idlm_role_lead_scopes', array());
update_option('idlm_role_lead_scopes', array_merge($idlm_activity_saved_scopes, array('sales_executive'=>'assigned', 'author'=>'all', 'sales_manager'=>'all')));
$idlm_activity_users = array();
foreach (array('activity_owner'=>'sales_executive', 'activity_other'=>'sales_executive', 'activity_reader'=>'author', 'activity_manager'=>'sales_manager', 'activity_subscriber'=>'subscriber') as $idlm_activity_actor=>$idlm_activity_role) {
    $idlm_activity_id = wp_insert_user(array('user_login'=>'m6_'.$idlm_activity_actor, 'user_pass'=>'idlm-synthetic-activity-password', 'user_email'=>$idlm_activity_actor.'@example.test', 'role'=>$idlm_activity_role));
    idlm_http_check(!is_wp_error($idlm_activity_id), 'Cannot create activity actor');
    $idlm_activity_users[$idlm_activity_actor] = (int) $idlm_activity_id;
    $idlm_activity_user = new WP_User($idlm_activity_id);
    if ($idlm_activity_role !== 'subscriber') { $idlm_activity_user->add_cap('idlm_access_leads'); }
    if ($idlm_activity_role === 'sales_manager') { $idlm_activity_user->add_cap('idlm_assign_leads'); }
    idlm_http_check(idlm_http_request('/login', array('user_login'=>'m6_'.$idlm_activity_actor, 'user_password'=>'idlm-synthetic-activity-password'), $idlm_activity_actor)['status'] === 302, 'Activity login failed');
    $sessions[$idlm_activity_actor] = json_decode(idlm_http_request('/session', array(), $idlm_activity_actor, false)['body'], true);
}
$idlm_activity_leads = array();
foreach (array('activity_owner', 'activity_other') as $idlm_activity_actor) {
    idlm_http_check($wpdb->insert($wpdb->prefix.'idlm_leads', array('first_name'=>'M6 synthetic activity', 'phone'=>$idlm_activity_actor, 'assigned_rep_id'=>$idlm_activity_users[$idlm_activity_actor], 'created_at'=>current_time('mysql'), 'updated_at'=>current_time('mysql'), 'stage'=>'New')) === 1, 'Cannot seed activity lead');
    $idlm_activity_leads[] = (int) $wpdb->insert_id;
}

idlm_http_case('M6-H-activity-nonce-capability-and-anonymous-denials-have-no-effects', static function(): void {
    global $idlm_activity_leads;
    idlm_http_check(idlm_http_request('/wp-admin/admin-ajax.php', array('action'=>'idlm_fixture_unregistered'), 'admin')['status'] === 400, 'Cannot prime admin_init');
    $log = idlm_activity_seed(); $before = idlm_http_snapshot();
    foreach (array('get_lead_activity', 'save_activity_comment', 'edit_activity_comment', 'delete_activity_comment') as $action) {
        $fields = array('lead_id'=>$idlm_activity_leads[0], 'log_id'=>$log, 'comment'=>'Denied note');
        idlm_http_check(idlm_activity_http($action, $fields, 'activity_owner', 'invalid')['status'] === 403, 'Invalid nonce accepted');
        idlm_http_check($before === idlm_http_snapshot(), 'Nonce denial changed state');
        idlm_activity_message(idlm_activity_http($action, $fields, 'activity_subscriber'), false, 'Permission denied.');
        idlm_http_check($before === idlm_http_snapshot(), 'Capability denial changed state');
        idlm_http_check(idlm_activity_http($action, $fields, 'anonymous')['status'] === 400, 'Anonymous action exposed');
        idlm_http_check($before === idlm_http_snapshot(), 'Anonymous request changed state');
    }
});

idlm_http_case('M6-H-activity-readall-remains-read-only-and-current-owner-controls-mutations', static function(): void {
    global $idlm_activity_leads;
    $log = idlm_activity_seed(); $before = idlm_http_snapshot();
    idlm_activity_message(idlm_activity_http('get_lead_activity', array('lead_id'=>$idlm_activity_leads[0]), 'activity_other'), false, 'You can access only leads assigned to you.');
    $r = idlm_activity_http('get_lead_activity', array('lead_id'=>$idlm_activity_leads[0]), 'activity_reader');
    idlm_http_check($r['status'] === 200 && json_decode($r['body'], true)['success'] === true, 'Read-all activity read denied');
    foreach (array('activity_other', 'activity_reader') as $actor) {
        idlm_activity_message(idlm_activity_http('save_activity_comment', array('lead_id'=>$idlm_activity_leads[0], 'comment'=>'Denied'), $actor), false, 'You can comment only on leads currently assigned to you.');
        foreach (array('edit_activity_comment', 'delete_activity_comment') as $action) {
            idlm_activity_message(idlm_activity_http($action, array('log_id'=>$log, 'comment'=>'Denied'), $actor), false, 'You cannot modify comments on this lead.');
        }
    }
    idlm_http_check($before === idlm_http_snapshot(), 'Scoped activity read/denial changed rows/jobs/outbound');
});

idlm_http_case('M6-H-activity-validation-and-protected-records-preserve-error-shapes', static function(): void {
    global $idlm_activity_leads;
    $protected = idlm_activity_seed('lost_remark'); $system = idlm_activity_seed('stage_change');
    $before = idlm_http_snapshot();
    foreach (array(0, 999999999) as $id) {
        idlm_activity_message(idlm_activity_http('get_lead_activity', array('lead_id'=>$id)), false, $id ? 'You can access only leads assigned to you.' : 'Invalid Lead ID');
        idlm_activity_message(idlm_activity_http('save_activity_comment', array('lead_id'=>$id, 'comment'=>'Note')), false, $id ? 'You can comment only on leads currently assigned to you.' : 'Empty comment or invalid Lead ID');
    }
    idlm_activity_message(idlm_activity_http('save_activity_comment', array('lead_id'=>$idlm_activity_leads[0], 'comment'=>'')), false, 'Empty comment or invalid Lead ID');
    foreach (array('edit_activity_comment', 'delete_activity_comment') as $action) {
        idlm_activity_message(idlm_activity_http($action), false, 'Invalid parameters.');
        foreach (array($protected, $system, 999999999) as $log) {
            idlm_activity_message(idlm_activity_http($action, array('log_id'=>$log, 'comment'=>'Tampered'), 'admin'), false, 'Comment not found.');
        }
    }
    idlm_http_check($before === idlm_http_snapshot(), 'Invalid/protected request changed state');
});

idlm_http_case('M6-H-activity-save-preserves-literal-text-author-and-fifteen-second-deduplication', static function(): void {
    global $wpdb, $idlm_activity_leads, $idlm_activity_users;
    $table = $wpdb->prefix.'idlm_lead_activity_log';
    $fields = array('lead_id'=>$idlm_activity_leads[0], 'comment'=>"<b>M6 O'Brien</b> C:\\Plans\\North\nSecond line");
    idlm_activity_message(idlm_activity_http('save_activity_comment', $fields), true, 'Comment added successfully');
    $row = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1", ARRAY_A);
    idlm_http_check($row['details'] === "M6 O'Brien C:\\Plans\\North\nSecond line" && (int) $row['user_id'] === $idlm_activity_users['activity_owner'] && $row['activity_type'] === 'comment', 'Stored comment changed');
    $before = idlm_http_snapshot();
    idlm_activity_message(idlm_activity_http('save_activity_comment', $fields), true, 'Comment added successfully');
    idlm_http_check($before === idlm_http_snapshot(), 'Duplicate created a second row or jobs');
    $wpdb->update($table, array('created_at'=>gmdate('Y-m-d H:i:s', strtotime(current_time('mysql')) - 60)), array('id'=>$row['id']));
    idlm_activity_message(idlm_activity_http('save_activity_comment', $fields), true, 'Comment added successfully');
    idlm_http_check((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE lead_id=%d AND details=%s", $idlm_activity_leads[0], $row['details'])) === 2, 'Deduplication window changed');
});

idlm_http_case('M6-H-activity-edit-keeps-author-admin-and-provenance-rules', static function(): void {
    global $wpdb;
    $table = $wpdb->prefix.'idlm_lead_activity_log'; $log = idlm_activity_seed();
    $before = idlm_http_snapshot();
    idlm_activity_message(idlm_activity_http('edit_activity_comment', array('log_id'=>$log, 'comment'=>'Manager edit'), 'activity_manager'), false, 'Unauthorized operation.');
    idlm_http_check($before === idlm_http_snapshot(), 'Manager author denial changed state');
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $log), ARRAY_A);
    foreach (array('activity_owner', 'admin') as $actor) {
        idlm_activity_message(idlm_activity_http('edit_activity_comment', array('log_id'=>$log, 'comment'=>"<b>Edited</b> O'Brien"), $actor), true, 'Comment updated successfully.');
        $updated = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id=%d", $log), ARRAY_A);
        $expected = $row; $expected['details'] = "Edited O'Brien";
        idlm_http_check($updated === $expected, 'Edit changed comment provenance or sanitization');
    }
});

idlm_http_case('M6-H-activity-delete-keeps-author-admin-and-current-lead-authority', static function(): void {
    global $wpdb, $idlm_activity_leads, $idlm_activity_users;
    $table = $wpdb->prefix.'idlm_lead_activity_log'; $log = idlm_activity_seed(); $before = idlm_http_snapshot();
    idlm_activity_message(idlm_activity_http('delete_activity_comment', array('log_id'=>$log), 'activity_manager'), false, 'Unauthorized operation.');
    idlm_http_check($before === idlm_http_snapshot(), 'Manager delete denial changed state');
    $wpdb->update($wpdb->prefix.'idlm_leads', array('assigned_rep_id'=>$idlm_activity_users['activity_other']), array('id'=>$idlm_activity_leads[0]));
    try {
        $before = idlm_http_snapshot();
        foreach (array('edit_activity_comment', 'delete_activity_comment') as $action) {
            idlm_activity_message(idlm_activity_http($action, array('log_id'=>$log, 'comment'=>'Former owner')), false, 'You cannot modify comments on this lead.');
        }
        idlm_http_check($before === idlm_http_snapshot(), 'Former owner changed comment');
    } finally {
        $wpdb->update($wpdb->prefix.'idlm_leads', array('assigned_rep_id'=>$idlm_activity_users['activity_owner']), array('id'=>$idlm_activity_leads[0]));
    }
    foreach (array('activity_owner', 'admin') as $actor) {
        $id = $actor === 'activity_owner' ? $log : idlm_activity_seed();
        idlm_activity_message(idlm_activity_http('delete_activity_comment', array('log_id'=>$id), $actor), true, 'Comment deleted successfully.');
        idlm_http_check(!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id=%d", $id)), 'Comment survived deletion');
    }
});

idlm_http_case('M6-H-activity-write-failures-retain-json-and-unchanged-state', static function(): void {
    global $config, $wpdb;
    $log = idlm_activity_seed();
    // Reuse the fixture's existing arbitrary-query fault hook; no production seam.
    $fault = $config['content_dir'].'/fail-quotation-write';
    try {
        foreach (array('edit_activity_comment'=>array('UPDATE', 'Failed to update comment.'), 'delete_activity_comment'=>array('DELETE FROM', 'Failed to delete comment.')) as $action=>$test) {
            file_put_contents($fault, $test[0].' `'.$wpdb->prefix.'idlm_lead_activity_log`');
            $before = idlm_http_snapshot();
            idlm_activity_message(idlm_activity_http($action, array('log_id'=>$log, 'comment'=>'Faulted')), false, $test[1]);
            idlm_http_check($before === idlm_http_snapshot(), 'Failed write changed state');
        }
    } finally { @unlink($fault); }
});

idlm_http_case('M6-H-activity-timeline-keeps-hundred-row-five-comment-html-contract', static function(): void {
    global $wpdb, $idlm_activity_leads;
    $table = $wpdb->prefix.'idlm_lead_activity_log';
    $wpdb->delete($table, array('lead_id'=>$idlm_activity_leads[0]));
    $empty = json_decode(idlm_activity_http('get_lead_activity', array('lead_id'=>$idlm_activity_leads[0]))['body'], true);
    idlm_http_check($empty['success'] && strpos($empty['data']['html'], 'No activity logs found for this lead.') !== false && strpos($empty['data']['comments_html'], 'No comments found for this lead.') !== false, 'Empty timeline changed');
    $ids = array();
    for ($i = 1; $i <= 105; $i++) {
        $ids[$i] = idlm_activity_seed($i === 105 ? 'lost_remark' : ($i === 104 ? 'whatsapp_reply' : 'comment'), $i === 103 ? 'activity_other' : 'activity_owner', sprintf('M6 timeline %03d', $i).($i === 105 ? " <script>alert(1)</script>\nMedia URL: https://example.test/private" : ''));
    }
    $before = idlm_http_snapshot();
    foreach (array('activity_owner', 'admin') as $actor) {
        $r = idlm_activity_http('get_lead_activity', array('lead_id'=>$idlm_activity_leads[0]), $actor); $json = json_decode($r['body'], true);
        idlm_http_check($r['status'] === 200 && $json['success'] && array_keys($json['data']) === array('html', 'comments_html'), 'Timeline envelope changed');
        $html = $json['data']['html']; $recent = $json['data']['comments_html'];
        idlm_http_check(substr_count($html, 'class="idlm-timeline-item"') === 100 && substr_count($recent, 'class="idlm-timeline-item"') === 5, 'Timeline limits changed');
        idlm_http_check(strpos($html, 'M6 timeline 006') !== false && strpos($html, 'M6 timeline 005') === false && strpos($recent, 'M6 timeline 101') !== false && strpos($recent, 'M6 timeline 100') === false, 'Timeline boundaries changed');
        idlm_http_check(strpos($html, 'M6 timeline 105') < strpos($html, 'M6 timeline 104'), 'Timeline order changed');
        idlm_http_check(strpos($html, '&lt;script&gt;alert(1)&lt;/script&gt;') !== false && strpos($html, '<script>') === false && strpos($html, 'https://example.test/private') === false, 'Presenter escaping/media filtering changed');
        foreach (array($html, $recent) as $markup) {
            foreach (array(104, 105) as $protected) { idlm_http_check(strpos($markup, 'data-id="'.$ids[$protected].'"') === false, 'Protected activity gained actions'); }
            idlm_http_check((strpos($markup, 'class="idlm-edit-comment-link" data-id="'.$ids[103].'"') !== false) === ($actor === 'admin'), 'Comment author action visibility changed');
            idlm_http_check(strpos($markup, 'class="idlm-delete-comment-link" data-id="'.$ids[102].'"') !== false, 'Own comment actions missing');
        }
    }
    idlm_http_check($before === idlm_http_snapshot(), 'Timeline read changed rows/jobs/outbound');
});
update_option('idlm_role_lead_scopes', $idlm_activity_saved_scopes);
