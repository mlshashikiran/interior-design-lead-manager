<?php
/** Lead query index migration: structure only, repeatable, no row changes. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME,'idlm_test_')!==0) { exit(1); }
function idlm_lead_index_names(): array {
    global $wpdb;
    return array_values(array_unique(array_map(static fn($row) => $row->Key_name, (array) $wpdb->get_results("SHOW INDEX FROM {$wpdb->prefix}idlm_leads"))));
}
idlm_integration_test('STREAMLINE-N-D4-lead-index-migration-adds-missing-indexes-and-changes-no-row', static function(): void {
    global $wpdb; $leads = $wpdb->prefix . 'idlm_leads'; $ids = array();
    foreach (array_keys(IDLM_DB::LEAD_QUERY_INDEXES) as $name) { idlm_integration_assert_true(in_array($name, idlm_lead_index_names(), true), 'Fresh install lacks '.$name); }
    try {
        for ($i = 0; $i < 5; $i++) { $ids[] = idlm_lifecycle_fixture(array('stage' => 'Won', 'created_at' => '2001-01-0'.($i+1).' 00:00:00')); }
        $before = $wpdb->get_results("SELECT * FROM {$leads} ORDER BY id", ARRAY_A);
        // An install upgraded from before this step has neither index and no marker.
        foreach (array_keys(IDLM_DB::LEAD_QUERY_INDEXES) as $name) { $wpdb->query("ALTER TABLE {$leads} DROP INDEX {$name}"); }
        // Every earlier step is already done on a live site; only this one is pending.
        $markers = array();
        foreach (array_keys(IDLM_DB::MIGRATION_STEPS) as $marker) { $markers[$marker] = get_option($marker, null); update_option($marker, '1.0.1', false); }
        delete_option('idlm_lead_index_version'); wp_cache_delete('idlm_lead_index_version', 'options');
        idlm_integration_assert_true(true === IDLM_DB::init(), 'Upgrade migration failed');
        foreach (array_keys(IDLM_DB::LEAD_QUERY_INDEXES) as $name) { idlm_integration_assert_true(in_array($name, idlm_lead_index_names(), true), 'Upgrade did not add '.$name); }
        idlm_integration_assert_same('1.0.1', get_option('idlm_lead_index_version'));
        idlm_integration_assert_same($before, $wpdb->get_results("SELECT * FROM {$leads} ORDER BY id", ARRAY_A), 'A lead row changed.');
        // Repeating it is harmless.
        idlm_integration_assert_true(IDLM_DB::add_lead_query_indexes());
        idlm_integration_assert_same($before, $wpdb->get_results("SELECT * FROM {$leads} ORDER BY id", ARRAY_A));
        $plan = $wpdb->get_row("EXPLAIN SELECT id FROM {$leads} WHERE is_duplicate = 0 AND assigned_rep_id = 7 AND stage = 'Negotiation' ORDER BY id DESC LIMIT 20", ARRAY_A);
        idlm_integration_assert_true(false !== strpos((string) $plan['possible_keys'], 'rep_duplicate_stage'), 'Board column query cannot use the index.');
    } finally {
        foreach ($markers ?? array() as $marker => $value) { if ('idlm_lead_index_version' !== $marker) { null === $value ? delete_option($marker) : update_option($marker, $value, false); } }
        if ($ids) { $wpdb->query("DELETE FROM {$leads} WHERE id IN (".implode(',', array_map('absint', $ids)).')'); }
    }
});
