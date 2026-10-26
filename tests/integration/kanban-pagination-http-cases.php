<?php
/** Bounded pipeline pages with full filtered totals and live scope. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }
function idlm_board_page(array $fields = array(), string $actor = 'owner'): array {
    global $sessions;
    return idlm_http_request('/wp-admin/admin-ajax.php', array('action' => 'idlm_get_kanban_leads', 'nonce' => $sessions[$actor]['admin']) + $fields, $actor);
}
function idlm_board_data(array $fields = array(), string $actor = 'owner'): array {
    $response = idlm_board_page($fields, $actor); $body = json_decode($response['body'], true);
    idlm_http_check($response['status'] === 200 && !empty($body['success']), 'Board page failed');
    return $body['data'];
}
$board_source = 'M7-BOARD-' . wp_generate_uuid4(); $board_ids = array();
for ($i = 0; $i < 205; $i++) {
    $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Synthetic board ' . $i, 'source' => $board_source, 'assigned_rep_id' => $users['owner'], 'stage' => $i % 2 ? 'Quote Shared' : 'Won', 'quote_value' => 100, 'deal_value' => 200, 'created_at' => current_time('mysql')));
    $board_ids[] = (int) $wpdb->insert_id;
}
$wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Other owner board', 'source' => $board_source, 'assigned_rep_id' => $users['other'], 'stage' => 'Quote Shared', 'created_at' => current_time('mysql')));
$board_other = (int) $wpdb->insert_id;
idlm_http_case('M7-H-board-pages-bound-cards-and-preserve-full-filtered-stage-counts', static function() use ($board_source, $board_ids): void {
    $fields = array('source' => $board_source); $seen = array(); $sizes = array();
    do {
        $data = idlm_board_data($fields); $ids = array(); foreach ($data['leads'] as $rows) { $ids = array_merge($ids, array_column($rows, 'id')); }
        $sizes[] = count($ids); $seen = array_merge($seen, array_map('intval', $ids));
        idlm_http_check($data['stats']['total_filtered'] === 205 && $data['stage_summaries']['Quote Shared']['count'] === 102 && $data['stage_summaries']['Won']['count'] === 103, 'Board totals describe only current page');
        idlm_http_check((float) $data['stats']['open_pipeline_value'] === 10200.0, 'Open pipeline includes closed stages or drops later pages');
        $fields['cursor'] = $data['page']['next_cursor'];
    } while ($data['page']['has_more']);
    sort($seen); sort($board_ids);
    idlm_http_check($sizes === array(100, 100, 5) && $seen === $board_ids, 'Board pages skip, duplicate or leak cards');
});
idlm_http_case('M7-H-board-cursors-reject-other-actor-tampering-and-filter-sort-changes', static function() use ($board_source): void {
    $fields = array('source' => $board_source); $data = idlm_board_data($fields); $cursor = $data['page']['next_cursor'];
    foreach (array(array($fields + array('cursor' => $cursor), 'other'), array($fields + array('cursor' => $cursor . 'x'), 'owner'), array(array('source' => 'changed', 'cursor' => $cursor), 'owner'), array($fields + array('cursor' => $cursor, 'sort' => 'oldest'), 'owner'), array($fields + array('cursor' => array('invalid')), 'owner')) as $case) {
        $response = idlm_board_page($case[0], $case[1]); $body = json_decode($response['body'], true);
        idlm_http_check($response['status'] === 400 && empty($body['success']) && strpos($response['body'], 'Synthetic board') === false, 'Invalid cursor returned cards');
    }
});
idlm_http_case('M7-H-board-filter-cannot-widen-representative-scope', static function() use ($board_source, $users, $board_other): void {
    $data = idlm_board_data(array('source' => $board_source, 'rep_id' => $users['other']));
    idlm_http_check($data['stats']['total_filtered'] === 0 && !$data['page']['has_more'], 'Filter widened representative scope');
    $data = idlm_board_data(array('source' => $board_source), 'other'); $ids = array(); foreach ($data['leads'] as $rows) { $ids = array_merge($ids, array_map('intval', array_column($rows, 'id'))); }
    idlm_http_check($ids === array($board_other), 'Other representative scope leaked');
});
idlm_http_case('M7-H-board-columns-start-with-twenty-each-and-load-independently', static function() use ($board_source, $board_ids): void {
    $fields = array('source' => $board_source, 'per_column' => '1');
    $initial = idlm_board_data($fields);
    idlm_http_check(count($initial['leads']['Won']) === 20 && count($initial['leads']['Quote Shared']) === 20, 'Initial cards are not bounded per column');
    idlm_http_check($initial['stats']['total_filtered'] === 205 && $initial['stage_summaries']['Won']['count'] === 103, 'Initial column totals are truncated');
    $seen = array();
    foreach (array('Won' => array(20,20,20,20,20,3), 'Quote Shared' => array(20,20,20,20,20,2)) as $stage => $expected_sizes) {
        $data = $initial; $sizes = array();
        do {
            $rows = $data['leads'][$stage];
            $sizes[] = count($rows);
            $seen = array_merge($seen, array_map('intval', array_column($rows, 'id')));
            foreach ($rows as $row) { idlm_http_check($row['stage'] === $stage, 'Continuation mixed columns'); }
            $page = $data['stage_pages'][$stage];
            if (!$page['has_more']) { break; }
            $data = idlm_board_data($fields + array('column_stage' => $stage, 'cursor' => $page['next_cursor']));
            foreach ($data['leads'] as $other_stage => $other_rows) { if ($other_stage !== $stage) { idlm_http_check(!$other_rows, 'Continuation loaded another column'); } }
            idlm_http_check($data['stats']['total_filtered'] === 205 && $data['stage_summaries']['Won']['count'] === 103, 'Continuation changed full totals');
        } while (true);
        idlm_http_check($sizes === $expected_sizes, 'Column batches skip or overrun twenty cards');
    }
    sort($seen); sort($board_ids);
    idlm_http_check($seen === $board_ids, 'Independent columns skip, duplicate or leak leads');
});
idlm_http_case('M7-H-board-column-cursors-bind-column-actor-filters-and-sort', static function() use ($board_source): void {
    $fields = array('source' => $board_source, 'per_column' => '1');
    $initial = idlm_board_data($fields);
    $cursor = $initial['stage_pages']['Won']['next_cursor'];
    $valid = $fields + array('column_stage' => 'Won', 'cursor' => $cursor);
    foreach (array(
        array(array_replace($valid, array('column_stage' => 'Quote Shared')), 'owner'),
        array($valid, 'other'),
        array(array_replace($valid, array('source' => 'changed')), 'owner'),
        array($valid + array('sort' => 'oldest'), 'owner'),
        array(array_replace($valid, array('cursor' => $cursor . 'x')), 'owner'),
        array(array_replace($valid, array('column_stage' => array('Won'))), 'owner'),
        array(array_replace($valid, array('per_column' => array('1'))), 'owner'),
        array(array_replace($valid, array('column_stage' => 'New')), 'owner'),
        array(array_replace($valid, array('column_stage' => '')), 'owner'),
    ) as $case) {
        $response = idlm_board_page($case[0], $case[1]);
        idlm_http_check($response['status'] === 400 && strpos($response['body'], 'Synthetic board') === false, 'Invalid column cursor returned cards');
    }
    $data = idlm_board_data($fields + array('column_stage' => 'Lost'));
    idlm_http_check(!$data['leads']['Lost'] && !$data['stage_pages']['Lost']['has_more'], 'Empty column has a continuation');
});
idlm_http_case('M7-H-board-column-sort-and-live-revocation-apply-to-every-batch', static function() use ($board_source, $board_ids, $users): void {
    $fields = array('source' => $board_source, 'per_column' => '1', 'column_stage' => 'Won', 'sort' => 'oldest');
    $data = idlm_board_data($fields);
    $expected = array_values(array_filter($board_ids, static function($id) use ($board_ids) { return array_search($id, $board_ids, true) % 2 === 0; }));
    idlm_http_check(array_map('intval', array_column($data['leads']['Won'], 'id')) === array_slice($expected, 0, 20), 'Column sort was not applied');
    $data = idlm_board_data($fields + array('cursor' => $data['stage_pages']['Won']['next_cursor']));
    idlm_http_check(array_map('intval', array_column($data['leads']['Won'], 'id')) === array_slice($expected, 20, 20), 'Column continuation lost sort');
    update_user_meta($users['owner'], 'idlm_crm_access_disabled', '1');
    $response = idlm_board_page($fields + array('cursor' => $data['stage_pages']['Won']['next_cursor']));
    delete_user_meta($users['owner'], 'idlm_crm_access_disabled');
    idlm_http_check(empty(json_decode($response['body'], true)['success']) && strpos($response['body'], 'Synthetic board') === false, 'Column continuation ignored access revocation');
});
if (!in_array('--hold', $argv, true) || !in_array('--kanban-page-only', $argv, true)) {
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}idlm_leads WHERE source = %s", $board_source));
}
