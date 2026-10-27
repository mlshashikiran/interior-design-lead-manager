<?php
/** QUO-004 actual boot rejection, private notices and next-request retry. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

idlm_http_case('T20-H-QUO004-failed-quotation-DDL-blocks-AJAX-and-REST-with-503',static function(): void {
    global $wpdb,$config,$sessions;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_quote_revisions DROP COLUMN revision_reason");
    update_option('idlm_quotation_schema_version','2.5.0',false);
    file_put_contents($config['content_dir'].'/fail-schema-ddl','synthetic');
    try {
        $before=idlm_http_snapshot();
        foreach(array(array('/wp-admin/admin-ajax.php',array('action'=>'idlm_get_rep_tasks','nonce'=>$sessions['owner']['admin'])),array('/wp-json/interior-design-lead-manager/v1/webhook',array('platform'=>'meta'))) as $request) {
            $r=idlm_http_request($request[0],$request[1]);
            idlm_http_check($r['status']===503,'Incomplete quotation DDL did not block boot');
            idlm_http_check(strpos($r['body'],'Retry shortly')!==false,'Missing retry guidance');
            idlm_http_check(strpos($r['body'],'PII-SYNTHETIC')===false && strpos($r['body'],'SELECT ')===false,'Private data or SQL disclosed');
        }
        idlm_http_check($before===idlm_http_snapshot(),'Failed quotation boot changed aggregates/jobs/outbound');
        idlm_http_check(IDLM_Schema_Migration::version('idlm_quotation_schema_version')==='2.5.0','Failed boot advanced completion');
    } finally {unlink($config['content_dir'].'/fail-schema-ddl');}
});

idlm_http_case('T20-H-QUO004-next-request-verifies-quotation-schema-and-restores-authorized-read',static function(): void {
    idlm_read_data(idlm_read_ajax('idlm_get_rep_tasks','owner'));
    wp_cache_flush();idlm_http_check(IDLM_Quotation_Schema::is_current(),'Quotation retry did not complete');
});

idlm_http_case('T20-H-QUO004-pending-backfill-blocks-current-schema-and-keeps-notice-private',static function(): void {
    global $config,$sessions;
    delete_option('idlm_quotation_revision_lifecycle_migration_version');
    file_put_contents($config['content_dir'].'/fail-quotation-backfill','synthetic');
    try {
        $before=idlm_http_snapshot();
        $r=idlm_http_request('/wp-admin/admin-ajax.php',array('action'=>'idlm_get_rep_tasks','nonce'=>$sessions['owner']['admin']));
        idlm_http_check($r['status']===503,'Pending quotation backfill accepted CRM request');
        idlm_http_check($before===idlm_http_snapshot(),'Failed backfill changed aggregates/jobs/outbound');
        idlm_http_check(IDLM_Schema_Migration::version('idlm_quotation_revision_lifecycle_migration_version')==='','Failed backfill advanced its marker');
        $admin=idlm_http_request('/migration-notices',array(),'admin',false);
        idlm_http_check(strpos($admin['body'],'Quotation migration did not complete')!==false,'Administrator lacks repair guidance');
        $owner=idlm_http_request('/migration-notices',array(),'owner',false);
        idlm_http_check(strpos($owner['body'],'Quotation migration did not complete')===false,'Non-manager sees private migration notice');
    } finally {unlink($config['content_dir'].'/fail-quotation-backfill');}
});

idlm_http_case('T20-H-QUO004-next-request-retries-uncompleted-backfill',static function(): void {
    idlm_read_data(idlm_read_ajax('idlm_get_rep_tasks','owner'));
    wp_cache_flush();idlm_http_check(IDLM_Quotation_Schema::is_current(),'Quotation backfill retry did not complete');
});
