<?php
/** Native physical schema, restart and failure regressions. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }

function idlm_schema_expect_ok($result): void {
    idlm_integration_assert_same(true, $result, is_wp_error($result) ? $result->get_error_message() : 'Migration did not complete.');
}

function idlm_schema_filter(callable $filter, callable $operation) {
    global $wpdb;
    $old = $wpdb->suppress_errors(true);
    add_filter('query', $filter);
    try { return $operation(); }
    finally { remove_filter('query', $filter); $wpdb->suppress_errors($old); }
}

function idlm_schema_markers(): array {
    return array('idlm_handoff_state_version', 'idlm_duplicate_quality_version', 'idlm_workflow_integrity_version', 'idlm_assignment_timestamp_version', 'idlm_attribution_touchpoint_backfill_version', 'idlm_conversion_time_integrity_version', 'idlm_task_queue_cleanup_version');
}

function idlm_schema_complete_steps(): void {
    foreach (idlm_schema_markers() as $marker) { update_option($marker, '1.0.1', false); }
}

idlm_integration_test('T17-N-physical core install verifies every declared column, default, index and InnoDB engine', static function (): void {
    global $wpdb;
    idlm_schema_expect_ok(IDLM_DB::create_tables());
    idlm_schema_expect_ok(IDLM_Schema_Migration::verify(IDLM_DB::schema_statements()));
    idlm_schema_expect_ok(IDLM_Routing_State::install());
    // Independent assertions avoid deriving all expectations from production SQL.
    idlm_integration_assert_same('InnoDB', $wpdb->get_var("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->prefix}idlm_leads'"));
    $parts = $wpdb->get_col("SHOW INDEX FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE Key_name = 'platform_external'", 4);
    idlm_integration_assert_same(array('platform', 'external_id'), $parts);
    idlm_integration_assert_same('not_ready', idlm_integration_column($wpdb->prefix . 'idlm_leads', 'handoff_status')->Default);
    idlm_integration_assert_same(IDLM_DB_VERSION, IDLM_Schema_Migration::version('idlm_schema_version'));
});

idlm_integration_test('T17-N-denied ALTER retains the old core marker and retries a partial physical upgrade', static function (): void {
    global $wpdb;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_leads DROP COLUMN assigned_at");
    update_option('idlm_schema_version', '1.3.5', false);
    $reporting = get_option('idlm_reporting_events_started_at');
    $filter = static function ($sql) { return stripos($sql, 'ALTER TABLE') === 0 ? 'SELECT 1' : $sql; };
    $result = idlm_schema_filter($filter, static function () { return IDLM_DB::init(); });
    idlm_integration_assert_true(is_wp_error($result));
    idlm_integration_assert_same('1.3.5', IDLM_Schema_Migration::version('idlm_schema_version'));
    idlm_integration_assert_same($reporting, get_option('idlm_reporting_events_started_at'));
    idlm_schema_expect_ok(IDLM_DB::init());
    idlm_integration_assert_true(null !== idlm_integration_column($wpdb->prefix . 'idlm_leads', 'assigned_at'));
});

idlm_integration_test('T17-N-absent index cannot pass physical verification or advance the schema marker', static function (): void {
    global $wpdb;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_tasks DROP INDEX lead_status_due");
    update_option('idlm_schema_version', '1.3.5', false);
    $result = idlm_schema_filter(static function ($sql) { return stripos($sql, 'ALTER TABLE') === 0 && strpos($sql, 'lead_status_due') !== false ? 'SELECT 1' : $sql; }, static function () { return IDLM_DB::init(); });
    idlm_integration_assert_true(is_wp_error($result));
    idlm_integration_assert_same('1.3.5', IDLM_Schema_Migration::version('idlm_schema_version'));
    idlm_schema_expect_ok(IDLM_DB::init());
});

idlm_integration_test('T17-N-wrong index order, uniqueness and prefix lengths are rejected even with matching names', static function (): void {
    global $wpdb;
    $table = $wpdb->prefix . 'idlm_lead_touchpoints';
    foreach (array('KEY platform_external (external_id, platform)', 'KEY platform_external (platform, external_id)', 'UNIQUE KEY platform_external (platform, external_id(20))') as $definition) {
        $wpdb->query("ALTER TABLE {$table} DROP INDEX platform_external, ADD {$definition}");
        try { idlm_integration_assert_true(is_wp_error(IDLM_Schema_Migration::verify(IDLM_DB::schema_statements()))); }
        finally { $wpdb->query("ALTER TABLE {$table} DROP INDEX platform_external, ADD UNIQUE KEY platform_external (platform, external_id)"); }
    }
    idlm_schema_expect_ok(IDLM_DB::create_tables());
});

idlm_integration_test('T17-N-column width, signedness, nullability and defaults must match the declared schema', static function (): void {
    global $wpdb;
    $table = $wpdb->prefix . 'idlm_leads';
    foreach (array("handoff_status varchar(12) DEFAULT 'not_ready'", "handoff_status varchar(30) NOT NULL DEFAULT 'not_ready'", "handoff_status varchar(30) DEFAULT 'other'", 'assigned_rep_id bigint DEFAULT 0') as $definition) {
        $column = strpos($definition, 'assigned_rep_id') === 0 ? 'assigned_rep_id' : 'handoff_status';
        $wpdb->query("ALTER TABLE {$table} MODIFY {$definition}");
        try { idlm_integration_assert_true(is_wp_error(IDLM_Schema_Migration::verify(IDLM_DB::schema_statements()))); }
        finally { $wpdb->query("ALTER TABLE {$table} MODIFY " . ($column === 'assigned_rep_id' ? 'assigned_rep_id bigint unsigned DEFAULT 0' : "handoff_status varchar(30) DEFAULT 'not_ready'")); }
    }
});

idlm_integration_test('T17-N-nontransactional core engine blocks migration without converting or losing rows', static function (): void {
    global $wpdb;
    $table = $wpdb->prefix . 'idlm_tasks';
    $before = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id", ARRAY_A);
    update_option('idlm_schema_version', '1.3.5', false);
    $wpdb->query("ALTER TABLE {$table} ENGINE=MyISAM");
    try {
        $result = IDLM_DB::init();
        idlm_integration_assert_true(is_wp_error($result));
        idlm_integration_assert_true(strpos($result->get_error_message(), 'InnoDB') !== false);
        idlm_integration_assert_same('1.3.5', IDLM_Schema_Migration::version('idlm_schema_version'));
        idlm_integration_assert_same($before, $wpdb->get_results("SELECT * FROM {$table} ORDER BY id", ARRAY_A));
    } finally { $wpdb->query("ALTER TABLE {$table} ENGINE=InnoDB"); }
    idlm_schema_expect_ok(IDLM_DB::init());
});

idlm_integration_test('T17-N-real restricted DDL account cannot falsely complete core or routing installation', static function (): void {
    global $wpdb;
    $admin_db = $wpdb;
    $user = 'idlm_test_ddl_' . getmypid();
    $wpdb->query("CREATE USER '{$user}'@'localhost' IDENTIFIED BY 'synthetic-test-password'");
    $wpdb->query('GRANT SELECT, INSERT, UPDATE, DELETE ON `' . DB_NAME . "`.* TO '{$user}'@'localhost'");
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_leads DROP COLUMN assigned_at");
    $wpdb->query("DROP TABLE {$wpdb->prefix}idlm_routing_state");
    update_option('idlm_schema_version', '1.3.5', false);
    delete_option('idlm_routing_schema_version');
    try {
        $wpdb = new wpdb($user, 'synthetic-test-password', DB_NAME, DB_HOST);
        $wpdb->set_prefix('wp_');
        idlm_integration_assert_true(is_wp_error(IDLM_DB::init()));
        idlm_integration_assert_true(is_wp_error(IDLM_Routing_State::install()));
        idlm_integration_assert_same('1.3.5', IDLM_Schema_Migration::version('idlm_schema_version'));
        idlm_integration_assert_same('', IDLM_Schema_Migration::version('idlm_routing_schema_version'));
        $wpdb->close();
    } finally { $wpdb = $admin_db; $wpdb->query("DROP USER '{$user}'@'localhost'"); }
    idlm_schema_expect_ok(IDLM_DB::init());
    idlm_schema_expect_ok(IDLM_Routing_State::install());
});

idlm_integration_test('T17-N-marker write failure is detected from durable options and retry converges', static function (): void {
    global $wpdb;
    update_option('idlm_schema_version', '1.3.5', false);
    $filter = static function ($sql) { return preg_match('/^(INSERT|UPDATE).*wp_options/s', $sql) && strpos($sql, 'idlm_schema_version') !== false ? 'INSERT INTO idlm_test_missing_marker VALUES (1)' : $sql; };
    idlm_integration_assert_true(is_wp_error(idlm_schema_filter($filter, static function () { return IDLM_DB::init(); })));
    idlm_integration_assert_same('1.3.5', IDLM_Schema_Migration::version('idlm_schema_version'));
    idlm_schema_expect_ok(IDLM_DB::init());
});

idlm_integration_test('T17-N-clock backfill failure prevents core and reporting markers from advancing', static function (): void {
    foreach (array('SET qualified_at = COALESCE', 'SET l.conversion_time = COALESCE') as $match) {
        update_option('idlm_schema_version', '1.3.5', false);
        $started = get_option('idlm_reporting_events_started_at');
        $filter = static function ($sql) use ($match) { return strpos($sql, $match) !== false ? 'INSERT INTO idlm_test_missing_clock VALUES (1)' : $sql; };
        idlm_integration_assert_true(is_wp_error(idlm_schema_filter($filter, static function () { return IDLM_DB::init(); })));
        idlm_integration_assert_same('1.3.5', IDLM_Schema_Migration::version('idlm_schema_version'));
        idlm_integration_assert_same($started, get_option('idlm_reporting_events_started_at'));
        idlm_schema_expect_ok(IDLM_DB::init());
    }
});

idlm_integration_test('T17-N-historical core rows, immutable events and unrelated commercial rows survive repeated upgrades', static function (): void {
    global $wpdb;
    idlm_schema_complete_steps();
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_leads ADD COLUMN synthetic_legacy_note varchar(60) DEFAULT ''");
    $now = '2025-01-02 03:04:05';
    $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Synthetic historical Won', 'stage' => 'Won', 'lead_quality' => 'Qualified', 'deal_value' => '12345.67', 'assigned_at' => $now, 'qualified_at' => $now, 'conversion_time' => $now, 'created_at' => $now, 'updated_at' => $now, 'synthetic_legacy_note' => 'Preserve additive legacy data'));
    $lead = (int) $wpdb->insert_id;
    $wpdb->insert($wpdb->prefix . 'idlm_lead_state_events', array('lead_id' => $lead, 'event_type' => 'stage_change', 'from_value' => 'Negotiation', 'to_value' => 'Won', 'amount' => '12345.67', 'occurred_at' => $now));
    $wpdb->insert($wpdb->prefix . 'idlm_quotes', array('lead_id' => $lead, 'quote_number' => 'SYN-HIST-CORE-007', 'status' => 'Accepted', 'created_at' => $now, 'updated_at' => $now));
    $tables = array('idlm_leads', 'idlm_lead_state_events', 'idlm_quotes'); $before = array();
    foreach ($tables as $table) { $where = $table === 'idlm_leads' ? 'id' : 'lead_id'; $before[$table] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$table} WHERE {$where} = {$lead}", ARRAY_A); }
    try {
        foreach (array('1.0.0', '1.3.2', '1.3.4', '1.3.5') as $version) {
            foreach (idlm_schema_markers() as $marker) { update_option($marker, '1.0.0', false); }
            delete_option('idlm_duplicate_touchpoint_backfill_cursor_v2');
            update_option('idlm_schema_version', $version, false); idlm_schema_expect_ok(IDLM_DB::init());
            foreach ($tables as $table) { $where = $table === 'idlm_leads' ? 'id' : 'lead_id'; idlm_integration_assert_same($before[$table], $wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$table} WHERE {$where} = {$lead}", ARRAY_A)); }
        }
        idlm_schema_expect_ok(IDLM_DB::init());
    } finally { $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_leads DROP COLUMN synthetic_legacy_note"); }
});

idlm_integration_test('T17-N-identical physical manifest keeps previous core 1.3.5 backups restorable', static function (): void {
    $payload = idlm_native_backup_fixture();
    $payload['schema']['core'] = '1.3.5';
    foreach (idlm_schema_markers() as $marker) { $payload['options'][] = array('option_name' => $marker, 'option_value' => '1.0.0', 'autoload' => 'off'); }
    $before = array(); foreach (idlm_schema_markers() as $marker) { $before[$marker] = IDLM_Schema_Migration::version($marker); }
    idlm_schema_expect_ok(idlm_native_backup_call('restore_payload', $payload));
    foreach ($before as $marker => $value) { idlm_integration_assert_same($value, IDLM_Schema_Migration::version($marker)); }
    $current = $payload; $current['schema']['core'] = IDLM_DB_VERSION;
    idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload', $current)));
    $bad = $payload; $bad['schema']['core'] = '1.3.4';
    idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload', $bad)));
    $bad = $payload; unset($bad['schema']['columns']['idlm_leads']['assigned_at']);
    idlm_integration_assert_true(is_wp_error(idlm_native_backup_call('restore_payload', $bad)));
});

idlm_integration_test('T17-N-loss of the session lock prevents durable completion markers', static function (): void {
    global $wpdb;
    update_option('idlm_schema_version', '1.3.5', false);
    $released = false;
    $filter = static function ($sql) use (&$released) {
        if (!$released && strpos($sql, "SELECT option_value FROM wp_options WHERE option_name = 'idlm_reporting_events_started_at'") === 0) {
            $released = true;
            $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare('SELECT RELEASE_LOCK(%s)', IDLM_Schema_Migration::lock_name()));
        }
        return $sql;
    };
    idlm_integration_assert_true(is_wp_error(idlm_schema_filter($filter, static function () { return IDLM_DB::init(); })));
    idlm_integration_assert_true($released);
    idlm_integration_assert_same('1.3.5', IDLM_Schema_Migration::version('idlm_schema_version'));
    idlm_schema_expect_ok(IDLM_DB::init());
});

// Each failure is named separately, with the completion marker checked before retry.
$failure_steps = array(
    'handoff' => array('idlm_handoff_state_version', "SET handoff_status = 'awaiting_manager'"),
    'duplicate-quality' => array('idlm_duplicate_quality_version', "SET lead_quality = 'Unqualified'"),
    'workflow-late-write' => array('idlm_workflow_integrity_version', 'DELETE t FROM'),
    'assignment-clock' => array('idlm_assignment_timestamp_version', 'SET l.assigned_at = CASE'),
    'conversion-clock' => array('idlm_conversion_time_integrity_version', 'SET conversion_time = NULL'),
    'task-cleanup-second-write' => array('idlm_task_queue_cleanup_version', 'LEFT JOIN wp_users'),
);
foreach ($failure_steps as $name => $step) {
    idlm_integration_test('T17-N-backfill-' . $name . ' retains its marker on failure then accepts zero affected rows', static function () use ($step): void {
        global $wpdb;
        idlm_schema_complete_steps();
        // Ensure the handoff UPDATE executes even on an otherwise empty fixture.
        if ($step[0] === 'idlm_handoff_state_version' && !get_users(array('role' => 'idlm_pre_sales', 'fields' => 'ID'))) { wp_insert_user(array('user_login' => 'synthetic_schema_presales', 'user_pass' => 'synthetic-password', 'role' => 'idlm_pre_sales')); }
        update_option($step[0], '1.0.0', false);
        $hits = 0;
        $filter = static function ($sql) use ($step, &$hits) { if (strpos($sql, $step[1]) !== false) { $hits++; return 'INSERT INTO idlm_test_missing_backfill VALUES (1)'; } return $sql; };
        idlm_integration_assert_true(is_wp_error(idlm_schema_filter($filter, static function () { return IDLM_DB::init(); })));
        idlm_integration_assert_same(1, $hits);
        idlm_integration_assert_same('1.0.0', IDLM_Schema_Migration::version($step[0]));
        idlm_schema_expect_ok(IDLM_DB::init());
        idlm_integration_assert_same('1.0.1', IDLM_Schema_Migration::version($step[0]));
    });
}

idlm_integration_test('T17-N-touchpoint interrupted insert retries without duplicates and preserves existing snapshots', static function (): void {
    global $wpdb;
    idlm_schema_complete_steps(); delete_option('idlm_duplicate_touchpoint_backfill_cursor_v2');
    update_option('idlm_attribution_touchpoint_backfill_version', '1.0.0', false);
    $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Synthetic migration canonical')); $canonical = (int) $wpdb->insert_id;
    $ids = array();
    for ($i = 0; $i < 3; $i++) { $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Synthetic duplicate ' . $i, 'is_duplicate' => 1, 'duplicate_of_id' => $canonical)); $ids[] = (int) $wpdb->insert_id; }
    $hits = 0;
    $filter = static function ($sql) use (&$hits) { return strpos($sql, 'INSERT INTO `wp_idlm_lead_touchpoints`') === 0 && ++$hits === 2 ? 'INSERT INTO idlm_test_missing_touch VALUES (1)' : $sql; };
    idlm_integration_assert_true(is_wp_error(idlm_schema_filter($filter, static function () { return IDLM_DB::init(); })));
    $before = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id = {$canonical}", ARRAY_A);
    idlm_integration_assert_same('1.0.0', IDLM_Schema_Migration::version('idlm_attribution_touchpoint_backfill_version'));
    idlm_schema_expect_ok(IDLM_DB::init()); idlm_schema_expect_ok(IDLM_DB::init());
    idlm_integration_assert_same(3, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id = {$canonical}"));
    idlm_integration_assert_same($before, $wpdb->get_row("SELECT * FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE id = {$before['id']}", ARRAY_A));
});

idlm_integration_test('T17-N-touchpoint SELECT failure cannot masquerade as an empty completed backfill', static function (): void {
    idlm_schema_complete_steps(); update_option('idlm_attribution_touchpoint_backfill_version', '1.0.0', false);
    $filter = static function ($sql) { return strpos($sql, 'SELECT * FROM wp_idlm_leads WHERE is_duplicate') === 0 ? 'SELECT * FROM idlm_test_missing_read' : $sql; };
    idlm_integration_assert_true(is_wp_error(idlm_schema_filter($filter, static function () { return IDLM_DB::init(); })));
    idlm_integration_assert_same('1.0.0', IDLM_Schema_Migration::version('idlm_attribution_touchpoint_backfill_version'));
    idlm_schema_expect_ok(IDLM_DB::init());
});

idlm_integration_test('T17-N-large touchpoint backfill checkpoints bounded batches and resumes after each request', static function (): void {
    global $wpdb;
    idlm_schema_complete_steps(); delete_option('idlm_duplicate_touchpoint_backfill_cursor_v2');
    update_option('idlm_attribution_touchpoint_backfill_version', '1.0.0', false);
    $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Synthetic batch canonical')); $lead = (int) $wpdb->insert_id;
    for ($i = 0; $i < 201; $i++) { $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Synthetic batch duplicate', 'is_duplicate' => 1, 'duplicate_of_id' => $lead)); }
    $cursor = 0; $complete = false;
    for ($i = 0; $i < 4; $i++) {
        $before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id = {$lead}");
        $result = IDLM_DB::init();
        $after = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id = {$lead}");
        idlm_integration_assert_true($after - $before <= 100);
        if (true === $result) { $complete = true; break; }
        idlm_integration_assert_same('idlm_schema_touchpoint_batch', $result->get_error_code());
        idlm_integration_assert_same('1.0.0', IDLM_Schema_Migration::version('idlm_attribution_touchpoint_backfill_version'));
        $next = (int) IDLM_Schema_Migration::version('idlm_duplicate_touchpoint_backfill_cursor_v2');
        idlm_integration_assert_true($next > $cursor); $cursor = $next;
    }
    idlm_integration_assert_true($complete);
    idlm_integration_assert_same(201, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id = {$lead}"));
    idlm_schema_expect_ok(IDLM_DB::init());
});

idlm_integration_test('T17-C-killed installer leaves DDL durable, marker pending and session lock recoverable', static function (): void {
    global $wpdb;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_leads DROP COLUMN assigned_at");
    update_option('idlm_schema_version', '1.3.5', false);
    $worker = idlm_integration_worker(array('schema-interrupt'));
    idlm_integration_assert_same(77, $worker['status'], $worker['stderr']);
    idlm_integration_assert_true(null !== idlm_integration_column($wpdb->prefix . 'idlm_leads', 'assigned_at'));
    idlm_integration_assert_same('1.3.5', IDLM_Schema_Migration::version('idlm_schema_version'));
    idlm_schema_expect_ok(IDLM_DB::init());
});

idlm_integration_test('T17-C-competing synchronized processes allow one installer and safely retry the other', static function (): void {
    @unlink(WP_CONTENT_DIR . '/schema-barrier');
    update_option('idlm_schema_version', '1.3.5', false);
    $workers = idlm_integration_concurrent_workers(2, 'schema-race');
    $statuses = array();
    foreach ($workers as $worker) { idlm_integration_assert_same(0, $worker['status'], $worker['stderr']); $statuses[] = $worker['stdout']; }
    sort($statuses); idlm_integration_assert_same(array('busy', 'complete'), $statuses);
    wp_cache_flush();
    idlm_schema_expect_ok(IDLM_DB::init());
});

idlm_integration_test('T20-N-activation schema failure stops before scheduling or activation success', static function (): void {
    global $wpdb;
    $wpdb->query("ALTER TABLE {$wpdb->prefix}idlm_leads DROP COLUMN assigned_at");
    update_option('idlm_schema_version', '1.3.5', false);
    $cron = get_option('cron'); $activated = get_option('idlm_plugin_activated_at');
    $die = static function () { return static function ($message) { throw new IDLM_Integration_WP_Die((string) $message); }; };
    add_filter('wp_die_handler', $die);
    add_filter('wp_die_ajax_handler', $die);
    try {
        $failed = false;
        try { idlm_schema_filter(static function ($sql) { return stripos($sql, 'ALTER TABLE') === 0 ? 'SELECT 1' : $sql; }, 'idlm_activate_plugin'); }
        catch (IDLM_Integration_WP_Die $error) { $failed = true; }
        idlm_integration_assert_true($failed);
        idlm_integration_assert_same($cron, get_option('cron'));
        idlm_integration_assert_same($activated, get_option('idlm_plugin_activated_at'));
    } finally { remove_filter('wp_die_handler', $die); remove_filter('wp_die_ajax_handler', $die); }
    idlm_schema_expect_ok(IDLM_DB::init());
});

idlm_integration_test('T17-N-zero inserted attribution rows remain pending until a real retry persists the touchpoint', static function (): void {
    global $wpdb;
    idlm_schema_complete_steps(); update_option('idlm_attribution_touchpoint_backfill_version', '1.0.0', false);
    $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Synthetic zero-insert canonical')); $lead = (int) $wpdb->insert_id;
    $wpdb->insert($wpdb->prefix . 'idlm_leads', array('first_name' => 'Synthetic zero-insert duplicate', 'is_duplicate' => 1, 'duplicate_of_id' => $lead));
    $filter = static function ($sql) { return strpos($sql, 'INSERT INTO `wp_idlm_lead_touchpoints`') === 0 ? 'SELECT 1 WHERE 0' : $sql; };
    idlm_integration_assert_true(is_wp_error(idlm_schema_filter($filter, static function () { return IDLM_DB::init(); })));
    idlm_integration_assert_same('1.0.0', IDLM_Schema_Migration::version('idlm_attribution_touchpoint_backfill_version'));
    idlm_integration_assert_same(0, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id = {$lead}"));
    idlm_schema_expect_ok(IDLM_DB::init());
    idlm_integration_assert_same(1, (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}idlm_lead_touchpoints WHERE lead_id = {$lead}"));
});
