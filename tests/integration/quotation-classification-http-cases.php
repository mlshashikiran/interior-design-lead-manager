<?php
/** QUO-005 actual save, boot upgrade and multipart recovery. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

$classification_quote = 0;
idlm_http_case('T10-T11-H-QUO005-authorized-save-captures-submitted-classifications', static function() use (&$classification_quote): void {
    global $wpdb, $users;
    $now = current_time('mysql');
    idlm_http_check(1 === $wpdb->insert($wpdb->prefix.'idlm_leads', array('first_name'=>'Synthetic classification', 'assigned_rep_id'=>$users['owner'], 'created_at'=>$now, 'updated_at'=>$now)), 'Fixture lead failed');
    $lead = (int) $wpdb->insert_id;
    $payload = idlm_http_payload($lead);
    $payload['items'] = array();
    foreach (array(1, 0) as $wood) {
        idlm_http_check(1 === $wpdb->insert($wpdb->prefix.'idlm_quote_catalogue_items', array('item_code'=>wp_generate_uuid4(), 'item_name'=>'Synthetic catalogue', 'is_wood'=>$wood, 'created_at'=>$now, 'updated_at'=>$now)), 'Fixture catalogue failed');
        $payload['items'][] = array('section_key'=>'general','description'=>'Synthetic '.$wood,'is_wood'=>$wood,'classification_source'=>'forged','catalogue_item_id'=>$wpdb->insert_id,'quantity'=>2,'unit_rate'=>100);
    }
    $payload['wooden_discount_percent'] = 10;
    $r = idlm_http_save($payload);
    idlm_http_check($r['status'] === 302, 'Authorized quotation save failed');
    $quote = IDLM_Quotation_Repository::get_quote_for_lead($lead);
    idlm_http_check((bool) $quote, 'Saved quotation missing');
    $classification_quote = (int) $quote->id;
    $bundle = IDLM_Quotation_Repository::get_quote_bundle($classification_quote);
    idlm_http_check(array('submitted','submitted') === array_column($bundle->items,'classification_source'), 'Save did not derive provenance');
    idlm_http_check(array('1','0') === array_column($bundle->items,'is_wood'), 'Save changed classification');
    idlm_http_check('200.00' === $bundle->revision->wooden_subtotal && '20.00' === $bundle->revision->wooden_discount_amount, 'Wood adjustment changed');
});

idlm_http_case('T11-T17-H-QUO005-boot-upgrade-after-catalogue-edits-retains-items-and-totals', static function() use (&$classification_quote): void {
    global $wpdb;
    idlm_http_check($classification_quote > 0, 'Missing saved fixture');
    $before = IDLM_Quotation_Repository::get_quote_bundle($classification_quote);
    foreach ($before->items as $item) {
        idlm_http_check(1 === $wpdb->update($wpdb->prefix.'idlm_quote_catalogue_items', array('is_wood'=>1-(int)$item->is_wood, 'status'=>'inactive','default_rate'=>'999.00'), array('id'=>$item->catalogue_item_id)), 'Catalogue edit failed');
    }
    update_option('idlm_quotation_schema_version','2.4.0',false);
    $r = idlm_http_request('/migration-notices',array(),'admin',false);
    idlm_http_check($r['status'] === 200, 'Boot upgrade failed');
    wp_cache_flush();
    idlm_http_check(IDLM_Quotation_Schema::is_current(), 'Boot did not finish upgrade');
    idlm_http_check($before == IDLM_Quotation_Repository::get_quote_bundle($classification_quote), 'Boot changed stored revision/items');
    $r = idlm_http_request('/migration-notices',array(),'admin',false);
    idlm_http_check($r['status'] === 200 && $before == IDLM_Quotation_Repository::get_quote_bundle($classification_quote), 'Repeated boot drifted');
});

idlm_http_case('T16-H-QUO005-old-multipart-backup-restores-classifications-with-unverified-provenance', static function(): void {
    $current = json_decode(idlm_backup_export()['body'],true);
    idlm_http_check(is_array($current), 'Backup export failed');
    $old = $current;
    $old['schema']['quotation'] = '2.5.1';
    unset($old['schema']['columns']['idlm_quote_revision_items']['classification_source']);
    foreach ($old['tables']['idlm_quote_revision_items'] as &$row) { unset($row['classification_source']); }
    unset($row);
    $r = idlm_backup_upload($old);
    idlm_http_check($r['status'] === 302 && strpos($r['headers'],'idlm_backup_notice=restored') !== false, 'Old backup upload rejected: '.$r['headers']);
    $after = json_decode(idlm_backup_export()['body'],true);
    $expected = $current['tables'];
    foreach ($expected['idlm_quote_revision_items'] as &$row) { $row['classification_source']='legacy_unverified'; }
    unset($row);
    idlm_http_check($expected === $after['tables'], 'Old restore changed bits, totals or aggregate relations');
    $bad = $current; unset($bad['tables']['idlm_quote_revision_items'][0]['classification_source']);
    idlm_backup_http_reject($bad);
    $r = idlm_backup_upload($current);
    idlm_http_check($r['status'] === 302 && strpos($r['headers'],'idlm_backup_notice=restored') !== false, 'Current backup restore failed');
    idlm_http_check($current['tables'] === json_decode(idlm_backup_export()['body'],true)['tables'], 'New backup lost provenance');
});
