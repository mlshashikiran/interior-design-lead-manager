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
    assert_contains("stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN 1 ELSE 0 END) as converted", $dashboard);
    assert_contains("stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN COALESCE(deal_value, 0) ELSE 0 END) as revenue", $dashboard);
    assert_contains("stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN time_to_won END) as avg_won", $dashboard);
    assert_contains("time_to_won IS NOT NULL AND conversion_time BETWEEN %s AND %s", $dashboard);
});

idlm_test('dashboard breakdowns separate acquisition measures from Won-close outcomes', static function(): void {
    $dashboard = (string) file_get_contents(dirname(__DIR__) . '/includes/class-dashboard.php');
    $template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/dashboard.php');
    $script = (string) file_get_contents(dirname(__DIR__) . '/assets/js/dashboard.js');
    assert_contains("stage = 'Won' AND conversion_time BETWEEN %s AND %s THEN 1 ELSE 0 END) as won", $dashboard);
    assert_contains("SELECT DATE(conversion_time) AS close_date, COUNT(id) AS won", $dashboard);
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
    assert_contains('Open &amp; Closed Pipeline by Channel', $html);
    assert_contains('Revenue progress', $html);
    assert_contains('Business Mix', $html);
    assert_contains('id="idlm-marketing-tab"', $html);
    assert_contains('Lead Volume &amp; Quality Trend', $html);
    assert_contains('Lead Quality by Source', $html);
    assert_contains('Disqualification Reasons', $html);
    assert_false(strpos($html, 'Channel Performance') !== false);
    assert_false(strpos($html, 'Value still in the pipeline') !== false);
});
