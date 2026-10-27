<?php
/** CORE-003 actual lead CSV downloads, T11/T12. Synthetic fixtures. Builder: Shashi. */
if (!defined('ABSPATH') || !defined('DB_NAME') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_csv_download(array $filters = array(), string $actor = 'admin', ?string $nonce = null): array {
    global $sessions;
    return idlm_http_request('/wp-admin/admin-ajax.php', array_merge($filters, array(
        'action' => 'idlm_export_leads', 'nonce' => $nonce ?? $sessions[$actor]['admin'],
    )), $actor, false);
}
function idlm_csv_denied(callable $request, array $statuses = array(200,400,403)): void {
    idlm_http_denied(static function() use ($request) {
        $response = $request();
        idlm_http_check(strpos($response['headers'], 'text/csv') === false && strpos($response['headers'], 'interior-design-leads-') === false, 'Denial returned a CSV attachment');
        idlm_http_check(strpos($response['body'], 'ID,') !== 0 && strpos($response['body'], 'CSV-PHONE-SYNTHETIC') === false, 'Denied download disclosed lead cells');
        return $response;
    }, $statuses);
}
function idlm_csv_parse(array $response): array {
    idlm_http_check($response['status'] === 200 && strpos($response['headers'], 'text/csv; charset=utf-8') !== false, 'Not a successful CSV download');
    idlm_http_check((bool) preg_match('/filename=interior-design-leads-\d{4}-\d{2}-\d{2}\.csv/', $response['headers']), 'Download filename changed');
    $stream = fopen('php://temp', 'w+'); fwrite($stream, $response['body']); rewind($stream);
    $rows = array(); while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) { $rows[] = $row; } fclose($stream);
    idlm_http_check(count($rows[0] ?? array()) === 40 && $rows[0][0] === 'ID' && $rows[0][39] === 'Updated At', 'CSV header contract changed');
    foreach ($rows as $row) { idlm_http_check(count($row) === 40, 'Field separator/quote injection changed column count'); }
    return $rows;
}

$csv_text_fields = array('first_name','last_name','email','phone','budget','project_timeline','property_location','source','referrer_details','property_type','num_rooms','floor_plan_file','quote_file','stage','lost_reason','call_status','lead_quality','utm_source','utm_medium','utm_campaign','utm_adgroup','utm_term','utm_content','gclid','gbraid','wbraid','fbclid','fb_lead_id','li_fat_id','landing_page');
$csv_prefixes = array('=1+1','+1+1','-1+1','@SUM(A1)',"\tPlain", "\rPlain", "\nPlain", " \t=1+1", "\0=1+1", "\x1f@SUM(A1)", "\x7f-1+1", "\u{00a0}=1+1", "\u{2003}+1+1", "\u{feff}@SUM(A1)", "\u{200b}=1+1", '＝1+1','＋1+1','－1+1','＠SUM(A1)', '="a,b"', '=1;"two"');
$csv_ordinary = array('शशि — غرف', 'A "quoted", value; two', "C:\\synthetic\\", "text\r\n=second line", "apostrophe'", '8 lakh', '560001', '', "'already text");
$csv_fixture_ids = array(); $csv_expected = array();
foreach (array_merge($csv_prefixes, $csv_ordinary) as $i => $text) {
    $now = current_time('mysql'); $data = array_fill_keys($csv_text_fields, $text);
    $data += array('quote_value' => '-42.50', 'floor_plan_received' => 1, 'quote_shared' => 1, 'assigned_rep_id' => $users[$i % 2 ? 'other' : 'owner'], 'created_at' => $now, 'updated_at' => $now);
    idlm_http_check($wpdb->insert($wpdb->prefix . 'idlm_leads', $data) !== false, 'Synthetic CSV row insert failed: ' . $wpdb->last_error);
    $csv_fixture_ids[] = (int) $wpdb->insert_id;
    $csv_expected[(int) $wpdb->insert_id] = $i < count($csv_prefixes) ? "'" . $text : $text;
}
$csv_ids_filter = array('ids' => implode(',', $csv_fixture_ids));
idlm_http_case('T12-H-lead-CSV-formula-control-Unicode-quote-and-numeric-cells', static function() use ($csv_ids_filter, $csv_expected, $csv_fixture_ids): void {
    $before = idlm_http_snapshot(); $rows = idlm_csv_parse(idlm_csv_download($csv_ids_filter));
    idlm_http_check(count($rows) === count($csv_expected) + 1, 'CSV dropped or added rows');
    idlm_http_check((int) $rows[1][0] === max($csv_fixture_ids), 'Descending ID order changed');
    foreach (array_slice($rows, 1) as $row) {
        $id = (int) $row[0]; idlm_http_check(isset($csv_expected[$id]) && $row[0] === (string) $id, 'ID no longer numeric');
        idlm_http_check($row[15] === '-42.50' && $row[12] === 'Yes' && $row[14] === 'Yes', 'Numeric/boolean export values changed');
        foreach (array(1,2,3,4,5,6,7,8,9,10,11,13,16,17,18,19,21,22,23,24,25,26,27,28,29,30,31,32,33,34) as $column) {
            idlm_http_check($row[$column] === $csv_expected[$id], 'Unexpected text cell normalization for ID ' . $id . ', column ' . $column);
        }
    }
    idlm_http_check($before === idlm_http_snapshot(), 'Export mutated business rows, jobs, copies or outbound attempts');
});
idlm_http_case('T12-H-lead-CSV-filter-search-empty-and-repeat', static function() use ($csv_fixture_ids, $csv_ids_filter): void {
    $r = idlm_csv_download(array('search' => 'शशि')); $rows = idlm_csv_parse($r); idlm_http_check(count($rows) === 2 && $rows[1][1] === 'शशि — غرف', 'Search filter changed');
    $rows = idlm_csv_parse(idlm_csv_download(array('source' => 'nonexistent-synthetic-source'))); idlm_http_check(count($rows) === 1, 'Empty filter exported unrelated rows');
    $rows = idlm_csv_parse(idlm_csv_download(array('ids' => (string) $csv_fixture_ids[0]))); idlm_http_check(count($rows) === 2 && (int)$rows[1][0] === $csv_fixture_ids[0], 'Selected IDs filter changed');
    $before = idlm_http_snapshot(); $a = idlm_csv_download($csv_ids_filter); $b = idlm_csv_download($csv_ids_filter); idlm_http_check($a['body'] === $b['body'] && $before === idlm_http_snapshot(), 'Repeated export changed data or output');
});
foreach (array('owner','other','readall','manager','anonymous') as $actor) {
    idlm_http_case('T12-H-lead-CSV-deny-' . $actor, static function() use ($actor,$csv_ids_filter,$sessions): void {
        idlm_csv_denied(static function() use ($actor,$csv_ids_filter,$sessions) { return idlm_csv_download($csv_ids_filter,$actor,$sessions[$actor]['admin'] ?? $sessions['admin']['admin']); });
    });
}
foreach (array('missing' => '', 'invalid' => 'synthetic-invalid', 'cross-user' => $sessions['owner']['admin']) as $kind => $nonce) {
    idlm_http_case('T12-H-lead-CSV-deny-' . $kind . '-nonce', static function() use ($nonce,$csv_ids_filter): void { idlm_csv_denied(static function() use ($nonce,$csv_ids_filter) { return idlm_csv_download($csv_ids_filter,'admin',$nonce); }); });
}
idlm_http_case('T12-H-lead-CSV-live-export-capability-revocation', static function() use ($csv_ids_filter,$users): void {
    $actor = new WP_User($users['manager']); $actor->add_cap('manage_options');
    try {
        $rows = idlm_csv_parse(idlm_csv_download($csv_ids_filter,'manager')); idlm_http_check(count($rows) === count(explode(',', $csv_ids_filter['ids'])) + 1, 'Explicit export grant failed');
        $actor->add_cap('manage_options',false);
        idlm_csv_denied(static function() use ($csv_ids_filter) { return idlm_csv_download($csv_ids_filter,'manager'); });
    } finally { $actor->remove_cap('manage_options'); }
});
idlm_http_case('T12-H-lead-CSV-phone-preservation-and-reassignment', static function() use ($users): void {
    global $wpdb;
    $now=current_time('mysql'); $wpdb->insert($wpdb->prefix.'idlm_leads',array('first_name'=>'CSV-PHONE-SYNTHETIC','phone'=>'+91 98765 43210','budget'=>'8 lakh','num_rooms'=>'3','quote_value'=>'123456.78','assigned_rep_id'=>$users['owner'],'created_at'=>$now,'updated_at'=>$now)); $id=(int)$wpdb->insert_id;
    $a=idlm_csv_parse(idlm_csv_download(array('ids'=>(string)$id)))[1];
    idlm_http_check($a[4]==="'+91 98765 43210" && $a[5]==='8 lakh' && $a[11]==='3' && $a[15]==='123456.78', 'Ordinary phone/budget/count/value changed');
    $wpdb->update($wpdb->prefix.'idlm_leads',array('assigned_rep_id'=>$users['other']),array('id'=>$id));
    $b=idlm_csv_parse(idlm_csv_download(array('ids'=>(string)$id)))[1]; idlm_http_check($a===$b, 'Administrator export changed with reassignment');
});
