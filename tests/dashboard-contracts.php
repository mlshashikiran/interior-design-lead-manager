<?php
/** Dashboard permissions, bounded reads and semantic presentation. Builder: Shashi. */
idlm_test('dashboard context respects role groups, disabled users and mixed roles', static function(): void {
    foreach (array(
        array(array('administrator'), array('manage_options'), true, true, false),
        array(array('sales_manager'), array('idlm_access_leads', 'idlm_assign_leads'), true, true, false),
        array(array('marketing'), array('idlm_access_leads'), false, true, false),
        array(array('campaign_team'), array('idlm_access_leads'), false, true, false),
        array(array('sales_executive'), array('idlm_access_leads'), true, false, false),
        array(array('idlm_pre_sales'), array('idlm_access_leads'), true, true, true),
        array(array('marketing', 'sales_executive'), array('idlm_access_leads'), true, true, false),
        array(array('subscriber'), array(), false, false, false),
    ) as $case) {
        unset($GLOBALS['idlm_test_users'][7]);
        idlm_test_login(7, $case[0], $case[1]);
        $access = IDLM_Dashboard::access_context();
        assert_same($case[2], $access['sales']);
        assert_same($case[3], $access['marketing']);
        assert_same($case[4], $access['pre_sales']);
    }
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('administrator'), array('manage_options'));
    update_user_meta(7, 'idlm_crm_access_disabled', '1');
    assert_false(IDLM_Dashboard::access_context()['marketing']);
    assert_false(IDLM_Dashboard::access_context()['sales']);
});

idlm_test('dashboard payload removes unauthorized contexts and individual performance', static function(): void {
    $raw = array('marketing' => array('total_leads_period' => 40), 'targets' => array('lead_target' => 100), 'data_quality' => array('total_leads' => 100), 'sales' => array(
        'open_pipeline_total' => 20, 'won_revenue_total' => 5000, 'leads_by_rep' => array('private'),
        'assignment_distribution' => array('private'), 'pre_sales_performance' => array('private'),
        'studio_performance' => array('private'), 'pre_sales_portfolio_activity' => array('calls' => 2),
    ));
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
    $sales = IDLM_Dashboard::filter_stats($raw);
    assert_false(isset($sales['marketing'], $sales['data_quality']));
    assert_false(isset($sales['targets']));
    assert_false(isset($sales['sales']['leads_by_rep']));
    assert_false(isset($sales['sales']['pre_sales_performance']));
    assert_same(5000, $sales['sales']['won_revenue_total']);
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('marketing'), array('idlm_access_leads'));
    assert_false(isset(IDLM_Dashboard::filter_stats($raw)['sales']));
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('idlm_pre_sales'), array('idlm_access_leads'));
    $presales = IDLM_Dashboard::filter_stats($raw);
    assert_same(5000, $presales['sales']['won_revenue_total']);
    assert_same(array('calls' => 2), $presales['sales']['pre_sales_portfolio_activity']);
    assert_same(20, $presales['sales']['open_pipeline_total']);
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('subscriber'), array());
    assert_same(array('access'), array_keys(IDLM_Dashboard::filter_stats($raw)));
});

idlm_test('pre-sales reporting retains outcomes after a Sales handoff', static function(): void {
    $dashboard = (string) file_get_contents(dirname(__DIR__) . '/includes/class-dashboard.php');
    assert_contains('assigned_rep_id = %d OR intake_owner_id = %d OR qualified_by_user_id = %d', $dashboard);
    assert_contains('l.assigned_rep_id = %d OR l.intake_owner_id = %d OR l.qualified_by_user_id = %d', $dashboard);
    assert_contains('$reporting_rep_clause', $dashboard);
    assert_contains('$reporting_event_rep_clause', $dashboard);
    assert_contains('a.user_id IN ({$pre_sales_placeholders}){$reporting_event_rep_clause}', $dashboard);
    assert_contains('l.qualified_by_user_id IN ({$pre_sales_placeholders}){$reporting_rep_clause}', $dashboard);
});

idlm_test('dashboard investigations enforce context before querying and bind current ownership', static function(): void {
    global $wpdb;
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
    $GLOBALS['idlm_test_options']['idlm_reps_see_all_leads'] = '0';
    assert_true(is_wp_error(IDLM_Dashboard::get_records('channel', 'Meta', 'this_month')));
    assert_true(is_wp_error(IDLM_Dashboard::get_records('handoff', '', 'this_month')));
    assert_true(is_wp_error(IDLM_Dashboard::get_records('arbitrary_sql', '', 'this_month')));
    assert_same('', $wpdb->last_get_results_query);
    IDLM_Dashboard::get_records('overdue', '', 'this_month', '', '', 2);
    assert_contains('l.assigned_rep_id = 7', $wpdb->last_get_results_query);
    assert_contains("t.status = 'pending' AND t.due_date <", $wpdb->last_get_results_query);
    assert_contains('LIMIT 26 OFFSET 25', $wpdb->last_get_results_query);
    assert_false(strpos($wpdb->last_get_results_query, 'created_at BETWEEN') !== false);
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('marketing'), array('idlm_access_leads'));
    assert_true(is_wp_error(IDLM_Dashboard::get_records('stage', 'New', 'this_month')));
    IDLM_Dashboard::get_records('channel', '', 'custom', '2026-09-01', '2026-09-09');
    assert_contains("COALESCE(l.source, '') = ''", $wpdb->last_get_results_query);
    assert_contains("l.created_at BETWEEN '2026-09-01 00:00:00' AND '2026-09-09 23:59:59'", $wpdb->last_get_results_query);
    assert_contains('l.assigned_rep_id = 7', $wpdb->last_get_results_query);
});

idlm_test('dashboard cache varies when capabilities change without a role change', static function(): void {
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
    $before = IDLM_Dashboard_Read_Model::cache_context('today');
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads', 'idlm_assign_leads'));
    $after = IDLM_Dashboard_Read_Model::cache_context('today');
    assert_same($before['roles'], $after['roles']);
    assert_false($before['access']['marketing']);
    assert_true($after['access']['marketing']);
    assert_true($before !== $after);
});

idlm_test('sales rep Won outcomes use the close-date reporting basis', static function(): void {
    $dashboard = (string) file_get_contents(dirname(__DIR__) . '/includes/class-dashboard.php');
    // Reps, channels, business mix, cards, trend and targets all read the same won-in-period set.
    assert_contains('private static function won_set_sql(', $dashboard);
    assert_contains("COUNT(w.lead_id) as converted", $dashboard);
    assert_contains("SUM(COALESCE(w.won_amount, 0)) as revenue", $dashboard);
    assert_contains("GREATEST(0, TIMESTAMPDIFF(SECOND, created_at, w.won_at)) END) as avg_won", $dashboard);
    assert_contains("INNER JOIN {\$won_set_curr} w ON w.lead_id = {\$table_leads}.id WHERE is_duplicate = 0 AND assigned_rep_id > 0", $dashboard);
});

idlm_test('dashboard breakdowns separate acquisition measures from Won-close outcomes', static function(): void {
    $dashboard = (string) file_get_contents(dirname(__DIR__) . '/includes/class-dashboard.php');
    $template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/dashboard.php');
    $script = (string) file_get_contents(dirname(__DIR__) . '/assets/js/dashboard.js');
    assert_contains("COUNT(w.lead_id) as won,", $dashboard);
    // The trend's Won line is close-dated and shares the Won KPI's basis: recorded transitions, then legacy closes.
    assert_contains("\$timeline_counts[\$won_date]['won'] = \$won_point['won'];", $dashboard);
    assert_false(false !== strpos($dashboard, 'SELECT DATE(conversion_time) AS close_date'), 'The trend must not count Won on a different basis from the Won KPI.');
    assert_contains('Won outcomes closed in the selected period', $template);
    assert_contains('Won Closed', $template);
    assert_contains("label: 'Won Closed'", $script);
});

idlm_test('dashboard KPI comparisons retain metric grain and omit date ranges', static function(): void {
    $dashboard = (string) file_get_contents(dirname(__DIR__) . '/includes/class-dashboard.php');
    $script = (string) file_get_contents(dirname(__DIR__) . '/assets/js/dashboard.js');
    assert_contains("'qualified_total_previous'", $dashboard);
    assert_contains("'total_won_previous'", $dashboard);
    assert_contains("'won_revenue_previous'", $dashboard);
    assert_contains('function comparedKpi(', $script);
    assert_contains("'% vs previous'", $script);
    assert_false(false !== strpos($script, "ranges.prev_start"), 'KPI comparisons must not append a previous period range.');
});

idlm_test('conversion timestamps remain exclusive to current Won leads', static function(): void {
    $database = (string) file_get_contents(dirname(__DIR__) . '/includes/class-db.php');
    $lifecycle = (string) file_get_contents(dirname(__DIR__) . '/includes/class-lead-lifecycle-service.php');
    assert_contains("idlm_conversion_time_integrity_version", $database);
    assert_contains("SET conversion_time = NULL", $database);
    assert_contains("COALESCE(stage, '') <> 'Won'", $database);
    assert_contains("elseif ('Won' === \$old_lead->stage)", $lifecycle);
});

idlm_test('dashboard renders standard header and only authorized body tabs', static function(): void {
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('marketing'), array('idlm_access_leads'));
    $html = IDLM_View::render('admin/dashboard.php', array('access' => IDLM_Dashboard::access_context()));
    assert_contains('<header class="idlm-header">', $html);
    assert_true(strpos($html, '</header>') < strpos($html, 'role="tablist"'));
    assert_contains('aria-selected="true"', $html);
    assert_contains('id="idlm-marketing-tab"', $html);
    assert_false(strpos($html, 'id="idlm-sales-tab"') !== false);
    assert_false(strpos($html, 'idlm-rep-outcomes') !== false);
    unset($GLOBALS['idlm_test_users'][7]);
    idlm_test_login(7, array('idlm_pre_sales'), array('idlm_access_leads'));
    $html = IDLM_View::render('admin/dashboard.php', array('access' => IDLM_Dashboard::access_context()));
    assert_contains('Pre-sales workspace', $html);
    assert_contains('Qualification readiness', $html);
    assert_contains('Qualification queue', $html);
    assert_contains('id="idlm-presales-quality"', $html);
    assert_contains('id="idlm-presales-call-status"', $html);
    assert_contains('id="idlm-presales-activity"', $html);
    assert_contains('Open &amp; closed pipeline by channel', $html);
    assert_contains('Revenue progress', $html);
    assert_contains('Business mix', $html);
    assert_contains('id="idlm-marketing-tab"', $html);
    assert_contains('Lead volume &amp; quality trend', $html);
    assert_contains('Lead quality by source', $html);
    assert_contains('Disqualification reasons', $html);
    assert_false(strpos($html, 'Channel performance') !== false);
    assert_false(strpos($html, 'Value still in the pipeline') !== false);
});

idlm_test('dashboard metrics reconcile: like-for-like comparisons, one Won basis and bounded rates', static function(): void {
    $dashboard = (string) file_get_contents(dirname(__DIR__) . '/includes/class-dashboard.php');
    $script = (string) file_get_contents(dirname(__DIR__) . '/assets/js/dashboard.js');
    // Previous windows are defined once, in the shared period class (see the overlap tests).
    assert_contains('IDLM_Date_Ranges::resolve($period, $custom_start, $custom_end)', $dashboard);
    // Numerators and denominators share one population.
    assert_contains("stage = 'Won' AND created_at BETWEEN %s AND %s \$reporting_rep_clause", $dashboard);
    assert_contains("created_at BETWEEN %s AND %s \$reporting_rep_clause\",\n            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared\n            \$curr_start, \$curr_end\n        ));\n\n        // Convert seconds", $dashboard);
    // Target pacing counts legacy Won like the Won KPI.
    assert_contains('$won_set_month = self::won_set_sql(', $dashboard);
    // A blank stage is New, not an opportunity.
    assert_contains("COALESCE(NULLIF(TRIM(stage), ''), 'New') NOT IN ('New', 'Lost') THEN 1 ELSE 0 END) AS opportunities", $dashboard);
    // Win rate is Won over closed outcomes; rep conversion cannot exceed 100%; campaign coverage counts every campaign.
    assert_contains("'total_lost' => array_sum(array_column(\$lost_reasons, 'count'))", $dashboard);
    assert_contains("rate(s.total_won, num(s.total_won) + num(s.total_lost))", $script);
    assert_false(false !== strpos($script, 'num(s.total_won) + num(s.open_pipeline_total)'), 'Win rate must not divide by the open pipeline.');
    assert_contains("'cohort_won' => intval(\$row->cohort_won)", $dashboard);
    assert_contains('round(($row->cohort_won / $row->total_leads) * 100, 1)', $dashboard);
    assert_contains("'campaign_attributed' => intval(", $dashboard);
    assert_contains('m.campaign_attributed', $script);
});

idlm_test('Won is counted once per deal on one basis across every dashboard figure', static function(): void {
    $dashboard = (string) file_get_contents(dirname(__DIR__) . '/includes/class-dashboard.php');
    // The latest Won transition per lead in the window, else the legacy close for leads with no Won event.
    assert_contains('SELECT MAX(id) AS id FROM {$table_state_events} WHERE event_type = \'stage_change\' AND to_value = \'Won\' AND occurred_at BETWEEN %s AND %s GROUP BY lead_id', $dashboard);
    assert_contains("'legacy'", $dashboard);
    assert_false(false !== strpos($dashboard, "SELECT COUNT(e.id) FROM {\$table_state_events} e"), 'Won Deals must not count every transition.');
    assert_false(false !== strpos($dashboard, "stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN"), 'Tables must not use a second Won basis.');
    foreach (array('$total_won = intval($won_current->won', '$total_won_previous = intval($won_previous->won', '$monthly_conversions = intval($wpdb->get_var("SELECT COUNT(w.lead_id) FROM {$won_set_month}') as $contract) {
        assert_contains($contract, $dashboard);
    }
    assert_contains("'Removed user #' . absint(\$row->assigned_rep_id)", $dashboard);
});

idlm_test('dashboard periods never overlap their comparison window', static function(): void {
    foreach (array('this_week', 'last_7_days', 'this_month', 'last_30_days', 'last_month', 'this_year', 'today', 'yesterday') as $period) {
        $r = IDLM_Dashboard::get_date_ranges($period);
        assert_true($r['curr_start'] <= $r['curr_end'], $period . ' current window is reversed.');
        assert_true($r['prev_start'] < $r['prev_end'], $period . ' previous window is reversed.');
        assert_true($r['prev_end'] < $r['curr_start'], $period . ' previous window overlaps the current one.');
    }
    $month = IDLM_Dashboard::get_date_ranges('last_month');
    $day_before = (new DateTime($month['curr_start'], wp_timezone()))->modify('-1 second')->format('Y-m-d H:i:s');
    assert_same($day_before, $month['prev_end'], 'Last month compares with the whole month before it.');
    assert_contains('00:00:00', $month['prev_start']);
    $custom = IDLM_Dashboard::get_date_ranges('custom', '2026-03-10', '2026-03-16');
    assert_same(array('2026-03-10 00:00:00', '2026-03-16 23:59:59', '2026-03-03 00:00:00', '2026-03-09 23:59:59'), array_values(array($custom['curr_start'], $custom['curr_end'], $custom['prev_start'], $custom['prev_end'])));
});

idlm_test('every date filter offers the same periods from one definition', static function(): void {
    $root = dirname(__DIR__);
    assert_same(array('today', 'yesterday', 'last_7_days', 'last_14_days', 'last_30_days', 'this_week', 'this_month', 'this_quarter', 'this_year', 'custom'), array_keys(IDLM_Date_Ranges::OPTIONS));
    foreach (array('templates/admin/dashboard.php', 'templates/admin/leads.php', 'templates/admin/kanban.php', 'templates/admin/logs/capi.php', 'templates/admin/logs/audience.php', 'includes/class-quotation-renderer.php', 'includes/class-design-dashboard.php') as $file) {
        $source = (string) file_get_contents($root . '/' . $file);
        assert_true(false !== strpos($source, 'IDLM_Date_Ranges::print_options(') || false !== strpos($source, 'IDLM_Date_Ranges::options_html('), $file . ' must use the shared period list.');
        assert_false((bool) preg_match('/<option value="(?:week|month|year|last_month|last_7_days)"/', $source), $file . ' still hard-codes its own periods.');
    }
    foreach (array('includes/class-admin-collection-reads.php', 'includes/class-admin-ajax.php', 'includes/class-dashboard.php', 'includes/class-admin-logs-renderer.php', 'includes/class-quotation-controller.php') as $file) {
        assert_contains('IDLM_Date_Ranges::', (string) file_get_contents($root . '/' . $file), $file);
    }
    // Old keys from saved links still work.
    assert_same('this_week', IDLM_Date_Ranges::normalize('week'));
    assert_same('this_year', IDLM_Date_Ranges::normalize('year'));
    assert_same('last_month', IDLM_Date_Ranges::normalize('last_month'));
    assert_same('', IDLM_Date_Ranges::normalize('fortnight'));
    // Every period is a real, non-overlapping window ending no later than now.
    $now = (new DateTime('now', wp_timezone()))->format('Y-m-d H:i:s');
    foreach (array_keys(IDLM_Date_Ranges::OPTIONS) as $key) {
        if ('custom' === $key) { continue; }
        $r = IDLM_Date_Ranges::resolve($key);
        assert_true($r['curr_start'] <= $r['curr_end'] && $r['curr_end'] <= $now, $key);
        assert_true($r['prev_start'] < $r['prev_end'] && $r['prev_end'] < $r['curr_start'], $key . ' comparison overlaps.');
    }
    $quarter = IDLM_Date_Ranges::resolve('this_quarter');
    assert_true(in_array(substr($quarter['curr_start'], 5), array('01-01 00:00:00', '04-01 00:00:00', '07-01 00:00:00', '10-01 00:00:00'), true), 'Quarter starts on a quarter boundary.');
    $fortnight = IDLM_Date_Ranges::resolve('last_14_days');
    assert_same(13, (int) (new DateTime(substr($fortnight['curr_start'], 0, 10)))->diff(new DateTime(substr($fortnight['curr_end'], 0, 10)))->format('%a'));
    assert_true(null === IDLM_Date_Ranges::resolve('custom', '2026-02-30', '2026-03-01'), 'Invalid custom dates give no window.');
    assert_same(array('custom', '2026-03-01', '2026-03-05'), IDLM_Date_Ranges::date_bounds('', '2026-03-01', '2026-03-05'));
});
