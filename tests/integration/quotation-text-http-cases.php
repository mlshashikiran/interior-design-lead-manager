<?php
/** REL-007 once-only request unslashing and stored text. Builder: Shashi. */
if ('cli' !== PHP_SAPI || strpos($config['db_name'] ?? '', 'idlm_test_') !== 0) { exit(1); }

function idlm_text_http_payload(int $lead, int $quote = 0, int $revision = 0, string $tag = 'Original'): array {
    $payload = idlm_http_payload($lead, $quote, $revision);
    $text = $tag . " C:\\plans\\oak; O'Brien's panel";
    $payload['sections'] = array(array('key' => 'living', 'section_name' => $tag . " O'Brien's C:\\plans\\living"));
    $payload['items'] = array(
        array('section_key' => 'living', 'row_type' => 'header', 'description' => $text),
        array('section_key' => 'living', 'description' => $text, 'item_name' => $text,
              'specification' => $text . "\nSecond line", 'quantity' => 1, 'unit_rate' => 100, 'unit' => 'Nos'),
    );
    return $payload;
}

function idlm_text_http_assert_items($items, string $tag): void {
    $items = json_decode(wp_json_encode($items), true);
    $text = $tag . " C:\\plans\\oak; O'Brien's panel";
    idlm_http_check(count($items) === 2, 'Text item count changed');
    idlm_http_check($items[0]['description'] === $text && $items[1]['description'] === $text, 'Description lost literal characters');
    idlm_http_check($items[1]['item_name'] === $text && $items[1]['specification'] === $text . "\nSecond line", 'Other item text drifted');
    idlm_http_check((float) $items[1]['line_subtotal'] === 100.0, 'Text repair changed money');
}

$text_quote = null;
idlm_http_case('T10-H-REL007-literal-item-and-section-text-survives-authorized-save', static function() use (&$text_quote): void {
    global $wpdb, $users;
    $now = current_time('mysql');
    idlm_http_check(1 === $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Synthetic literal text', 'assigned_rep_id' => $users['owner'], 'created_at' => $now, 'updated_at' => $now)), 'Text lead fixture failed');
    $lead = (int) $wpdb->insert_id;
    $result = idlm_http_save(idlm_text_http_payload($lead));
    idlm_http_check($result['status'] === 302 && strpos(urldecode($result['headers']), 'Quotation saved') !== false, 'Text save failed');
    $text_quote = IDLM_Quotation_Repository::get_quote_for_lead($lead);
    $bundle = IDLM_Quotation_Repository::get_quote_bundle($text_quote->id);
    idlm_text_http_assert_items($bundle->items, 'Original');
    idlm_http_check($bundle->sections[0]->section_name === "Original O'Brien's C:\\plans\\living", 'Section name changed');
    idlm_http_check((float) $bundle->revision->grand_total === 118.0, 'Save totals changed');
});

idlm_http_case('T10-H-REL007-autosave-editor-recovery-and-new-revision-preserve-literal-text', static function() use (&$text_quote): void {
    global $users;
    idlm_http_check((bool) $text_quote, 'Text save prerequisite missing');
    $old = IDLM_Quotation_Repository::get_quote_bundle($text_quote->id);
    $payload = idlm_text_http_payload((int) $text_quote->lead_id, (int) $text_quote->id, (int) $text_quote->current_revision_id, 'Working');
    idlm_http_check(idlm_http_autosave($payload)['status'] === 200, 'Text autosave failed');
    wp_cache_flush();
    $copy = get_transient('idlm_quote_autosave_' . $users['owner'] . '_' . $text_quote->current_revision_id);
    idlm_text_http_assert_items($copy['items'], 'Working');
    idlm_http_check($copy['sections']['living']['section_name'] === "Working O'Brien's C:\\plans\\living", 'Working section name changed');
    idlm_http_check(wp_json_encode($old->items) === wp_json_encode(IDLM_Quotation_Repository::get_quote_bundle($text_quote->id)->items), 'Autosave rewrote saved items');
    $editor = idlm_http_request('/workspace', array('action' => 'edit', 'quote_id' => $text_quote->id), 'owner', false);
    $decoded = html_entity_decode($editor['body'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    idlm_http_check($editor['status'] === 200 && strpos($decoded, "Working C:\\plans\\oak; O'Brien's panel") !== false, 'Editor recovery changed item text');
    idlm_http_check(strpos($decoded, "Working O'Brien's C:\\plans\\living") !== false, 'Editor recovery changed section text');
    idlm_http_check(idlm_http_save($payload)['status'] === 302, 'Working revision save failed');
    $text_quote = IDLM_Quotation_Repository::get_quote($text_quote->id);
    idlm_text_http_assert_items(IDLM_Quotation_Repository::get_quote_bundle($text_quote->id)->items, 'Working');
    idlm_http_check(wp_json_encode($old->items) === wp_json_encode(IDLM_Quotation_Repository::get_quote_bundle($text_quote->id, $old->revision->id)->items), 'Revision save rewrote historical item text');
});

idlm_http_case('T12-H-REL007-print-CSV-XLSX-retain-exact-saved-literal-text', static function() use (&$text_quote): void {
    global $config, $sessions;
    idlm_http_check((bool) $text_quote, 'Text save prerequisite missing');
    $text = "Working C:\\plans\\oak; O'Brien's panel";
    $section = "Working O'Brien's C:\\plans\\living";
    $before = idlm_http_snapshot();
    foreach (array('owner', 'admin') as $actor) {
        $parameters = array('quote_id' => $text_quote->id, 'revision_id' => $text_quote->current_revision_id);
        $printed = idlm_http_request('/wp-admin/admin-post.php', $parameters + array('action' => 'idlm_print_quote', '_wpnonce' => $sessions[$actor]['print']), $actor, false);
        $decoded = html_entity_decode($printed['body'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        idlm_http_check($printed['status'] === 200 && strpos($decoded, $text) !== false && strpos($decoded, $section) !== false, 'Print literal text drift');
        $csv = idlm_http_request('/wp-admin/admin-post.php', $parameters + array('action' => 'idlm_download_quote_csv', '_wpnonce' => $sessions[$actor]['csv']), $actor, false);
        $stream = fopen('php://temp', 'r+'); fwrite($stream, $csv['body']); rewind($stream); $matched = false;
        while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            if (count($row) === 9 && $row[2] === $text) { $matched = $row[0] === $section; }
        }
        fclose($stream);
        idlm_http_check($csv['status'] === 200 && $matched, 'Parsed CSV literal text drift');
        $xlsx = idlm_http_request('/wp-admin/admin-post.php', $parameters + array('action' => 'idlm_download_quote_xlsx', '_wpnonce' => $sessions[$actor]['xlsx']), $actor, false);
        $file = $config['content_dir'] . '/literal-text.xlsx'; file_put_contents($file, $xlsx['body']); $zip = new ZipArchive();
        idlm_http_check($xlsx['status'] === 200 && $zip->open($file) === true, 'Text XLSX failed');
        $sheet = $zip->getFromName('xl/worksheets/sheet2.xml'); $zip->close(); unlink($file);
        $xml = simplexml_load_string($sheet);
        $values = array_map('strval', $xml->xpath('//*[local-name()="t"]'));
        idlm_http_check(in_array($text, $values, true) && in_array($section, $values, true), 'Parsed XLSX literal text drift');
    }
    idlm_http_check($before === idlm_http_snapshot(), 'Text exports changed durable state');
});
