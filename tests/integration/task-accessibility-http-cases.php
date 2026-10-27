<?php
/** UI-003 rendered task semantics. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }
idlm_http_case('T04-T19-H-UI003-task-labels-submit-and-error-associations', static function() use ($leads, $sessions): void {
    global $wpdb;
    $snapshot = static function() use ($wpdb): string {
        $rows=array();
        foreach ($wpdb->get_col("SHOW TABLES LIKE 'wp_idlm_%'") as $table) { $rows[$table]=$wpdb->get_results("SELECT * FROM `$table` ORDER BY 1", ARRAY_A); }
        return wp_json_encode($rows);
    };
    $before = $snapshot();
    $response = idlm_http_request('/wp-admin/admin-ajax.php', array('action'=>'idlm_get_lead_details','lead_id'=>$leads[4],'nonce'=>$sessions['owner']['admin']));
    $body = json_decode($response['body'], true);
    idlm_http_check($response['status']===200 && !empty($body['success']), 'Task drawer response failed');
    $dom = new DOMDocument(); libxml_use_internal_errors(true); $dom->loadHTML($body['data']['html']); libxml_clear_errors(); libxml_use_internal_errors(false);
    $xpath = new DOMXPath($dom);
    foreach (array('idlm-new-task-title'=>'Task Title / Description *','idlm-new-task-due'=>'Due Date & Time','idlm-new-task-priority'=>'Priority','idlm-new-task-type'=>'Task Type') as $id=>$name) {
        $labels=$xpath->query('//label[@for="'.$id.'"]');
        idlm_http_check($labels->length===1 && trim($labels->item(0)->textContent)===$name, 'Missing associated name: '.$id);
        idlm_http_check($xpath->query('//*[@id="'.$id.'"]')->length===1, 'Missing or duplicate task control ID');
    }
    idlm_http_check($xpath->query('//form[@id="idlm-add-task-form"]//button[@id="idlm-btn-add-task" and @type="submit"]')->length===1, 'Task submit semantics missing');
    $title=$xpath->query('//*[@id="idlm-new-task-title"]')->item(0);
    $error=$xpath->query('//*[@id="'.$title->getAttribute('aria-describedby').'"]');
    idlm_http_check($error->length===1 && $error->item(0)->getAttribute('role')==='alert' && $error->item(0)->hasAttribute('hidden'), 'Task error association missing');
    idlm_http_check($before===$snapshot(), 'Rendering task form changed domain rows');
});
