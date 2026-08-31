<?php
/**
 * Admin facade characterization contracts for the compatibility-first refactor.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

function idlm_admin_presentation_source(): string {
    $root = dirname(__DIR__);
    $source = (string) file_get_contents($root . '/includes/class-admin.php');
    $templates = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/templates', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($templates as $template) {
        if ($template->isFile() && 'php' === strtolower($template->getExtension())) {
            $source .= "\n" . (string) file_get_contents($template->getPathname());
        }
    }
    return $source;
}

function idlm_admin_asset_handles(string $type): array {
    return array_values(array_map(static function(array $call): string {
        return (string) ($call['args'][0] ?? '');
    }, array_filter($GLOBALS['idlm_test_asset_calls'], static function(array $call) use ($type): bool {
        return $type === $call['type'];
    })));
}

idlm_test('admin facade preserves every public compatibility method and its size baseline', static function(): void {
    $expected = array(
        'init', 'get_allowed_rep_roles', 'current_user_can_assign_leads', 'current_user_is_pre_sales',
        'get_qualification_status_label', 'is_assignable_rep', 'get_pre_sales_user_ids',
        'current_user_can_access_lead', 'current_user_can_view_lead', 'format_datetime',
        'render_activity_details', 'current_user_has_rep_access', 'get_whatsapp_read_activity_ids',
        'mark_whatsapp_reply_read', 'get_unread_whatsapp_lead_ids', 'get_unread_whatsapp_count',
        'get_whatsapp_received_lead_ids', 'get_kanban_stage_total_mappings', 'get_reminder_rules',
        'check_lead_overdue_status', 'get_pending_calls_count', 'register_core_assets', 'enqueue_assets',
        'enqueue_frontend_assets', 'render_leads_list_shortcode', 'render_dashboard_shortcode',
        'render_kanban_shortcode', 'render_workspace_shortcode', 'render_my_account_page',
        'handle_update_my_account', 'handle_rotate_api_credential', 'handle_create_user', 'handle_update_user', 'register_admin_menus',
        'render_dashboard_page', 'render_users_access_page', 'render_leads_list_page', 'render_common_overlays', 'render_kanban_page',
        'render_capi_logs_page', 'render_audience_sync_logs_page', 'render_settings_page',
        'ajax_get_lead_details', 'ajax_get_lead_activity', 'ajax_quick_edit_lead',
        'ajax_log_presales_touch', 'ajax_presales_decision', 'ajax_save_activity_comment',
        'ajax_edit_activity_comment', 'ajax_delete_activity_comment', 'ajax_export_google_conversions',
        'ajax_export_leads', 'ajax_add_lead_manual', 'ajax_trigger_manual_audience_sync',
        'ajax_test_api_connection', 'ajax_retry_capi_events', 'ajax_log_js_error',
        'ajax_clear_telemetry_logs', 'ajax_download_diagnostic_report', 'ajax_save_routing_settings',
        'ajax_run_fallback_now', 'ajax_get_mapped_past_leads', 'ajax_sync_mapped_past_batch',
        'ajax_bulk_action', 'ajax_get_leads_table', 'ajax_get_kanban_leads',
        'ajax_get_dashboard_stats', 'ajax_save_lead_details', 'ajax_upload_lead_file',
        'ajax_get_total_leads_count', 'ajax_reset_crm_data', 'ajax_delete_lead',
        'ajax_import_leads_batch', 'ajax_get_lead_tasks', 'ajax_add_lead_task',
        'ajax_toggle_task_status', 'ajax_delete_lead_task', 'ajax_get_rep_tasks',
    );
    $reflection = new ReflectionClass('IDLM_Admin');
    $actual = array_values(array_map(static function(ReflectionMethod $method): string {
        return $method->getName();
    }, array_filter($reflection->getMethods(ReflectionMethod::IS_PUBLIC), static function(ReflectionMethod $method): bool {
        return 'IDLM_Admin' === $method->getDeclaringClass()->getName();
    })));

    assert_same($expected, $actual, 'The IDLM_Admin public compatibility surface changed.');
    assert_same(78, count($actual));
    assert_true(count(file(dirname(__DIR__) . '/includes/class-admin.php')) <= 3240, 'The Phase 2 admin facade exceeded its 3,240-line size contract.');
});

idlm_test('admin Phase 1 renderers retain delegates and guarded nested templates', static function(): void {
    $root = dirname(__DIR__);
    $source = (string) file_get_contents($root . '/includes/class-admin.php');

    assert_contains('return IDLM_Admin_Dashboard_Renderer::render();', $source);
    assert_contains('return IDLM_Admin_Kanban_Renderer::render();', $source);
    assert_contains('return IDLM_Admin_Logs_Renderer::render_capi();', $source);
    assert_contains('return IDLM_Admin_Logs_Renderer::render_audience();', $source);
    assert_true(class_exists('IDLM_Admin_Dashboard_Renderer'));
    assert_true(class_exists('IDLM_Admin_Kanban_Renderer'));
    assert_true(class_exists('IDLM_Admin_Logs_Renderer'));

    $dashboard = IDLM_View::render('admin/dashboard.php', array('can_assign_leads' => true));
    assert_contains('id="idlm-period-selector"', $dashboard);
    assert_contains('id="chart-sales-assignments"', $dashboard);
    assert_same('', IDLM_View::render('admin/../dashboard.php'));
});

idlm_test('admin Phase 2 leads renderer retains delegates and presentation-only guarded templates', static function(): void {
    $root = dirname(__DIR__);
    $source = (string) file_get_contents($root . '/includes/class-admin.php');
    $leads_template = (string) file_get_contents($root . '/templates/admin/leads.php');

    assert_contains('return IDLM_Admin_Leads_Renderer::render();', $source);
    assert_contains('return IDLM_Admin_Leads_Renderer::render_common_overlays();', $source);
    assert_true(class_exists('IDLM_Admin_Leads_Renderer'));
    assert_true(is_file($root . '/templates/admin/leads.php'));
    assert_true(is_file($root . '/templates/admin/common-overlays.php'));
    assert_false(false !== strpos($leads_template, 'get_option('), 'Leads template must not read options.');
    assert_false(false !== strpos($leads_template, '$wpdb'), 'Leads template must not query the database.');
    assert_false(false !== strpos($leads_template, 'current_user_can('), 'Leads template must receive capability context.');
    assert_contains('idlm-lead-filters', $leads_template);
    assert_contains('idlm-leads-table', $leads_template);
    assert_contains('idlm-export-modal', $leads_template);
    assert_contains('idlm-lead-drawer', (string) file_get_contents($root . '/templates/admin/common-overlays.php'));
});

idlm_test('Kanban header reports overall leads and current-month wins by conversion date', static function(): void {
    $root = dirname(__DIR__);
    $ajax_source = (string) file_get_contents($root . '/includes/class-admin-ajax.php');
    $template = (string) file_get_contents($root . '/templates/admin/kanban.php');
    $script = (string) file_get_contents($root . '/assets/js/kanban.js');

    assert_contains('COUNT(*) AS total_filtered', $ajax_source);
    assert_contains("l.conversion_time >= %s", $ajax_source);
    assert_contains("l.conversion_time < %s", $ajax_source);
    assert_contains("'won_revenue_current_month'", $ajax_source);
    assert_contains('Overall Leads — Filtered', $template);
    assert_contains('Won Revenue This Month', $template);
    assert_false(false !== strpos($template, 'Assigned Leads — Filtered'));
    assert_false(false !== strpos($template, 'Qualified Leads — Filtered'));
    assert_contains('stats.total_filtered', $script);
    assert_contains('stats.won_revenue_current_month', $script);
});

idlm_test('Kanban omits early stages and exposes whitelisted card sorting', static function(): void {
    $root = dirname(__DIR__);
    $renderer = (string) file_get_contents($root . '/includes/class-admin-kanban-renderer.php');
    $ajax_source = (string) file_get_contents($root . '/includes/class-admin-ajax.php');
    $template = (string) file_get_contents($root . '/templates/admin/kanban.php');
    $script = (string) file_get_contents($root . '/assets/js/kanban.js');

    assert_contains('array_diff($stages, array(\'New\', \'Contacted\'))', $renderer);
    assert_contains("l.stage NOT IN ('New', 'Contacted')", $ajax_source);
    assert_contains("'newest'   => 'l.created_at DESC, l.id DESC'", $ajax_source);
    assert_contains('idlm-kanban-sort', $template);
    assert_contains('Name A–Z', $template);
    assert_contains("sort: $('#idlm-kanban-sort').val()", $script);
});

idlm_test('lead drawer header stage card uses the secured lifecycle quick edit', static function(): void {
    $root = dirname(__DIR__);
    $drawer_source = (string) file_get_contents($root . '/includes/class-admin-lead-reads.php');
    $script_source = (string) file_get_contents($root . '/assets/js/admin-app.js');

    assert_contains('id="idlm-drawer-stage-select"', $drawer_source);
    assert_contains('class="idlm-drawer-stage-select idlm-quick-edit"', $drawer_source);
    assert_contains('data-field="stage"', $drawer_source);
    assert_contains('data-edit-context="drawer_header"', $drawer_source);
    assert_contains('if (self::current_user_is_pre_sales()):', $drawer_source);
    assert_contains('aria-label="Change lead stage"', $drawer_source);
    assert_contains("\$select.hasClass('idlm-drawer-stage-select')", $script_source);
    assert_contains('openLeadDrawer(leadId);', $script_source);
});

idlm_test('admin hooks, AJAX actions, shortcodes, and menu callbacks remain registered', static function(): void {
    IDLM_Admin::init();
    $actions = array_values(array_map(static fn(array $hook): string => $hook['hook'], array_filter(
        $GLOBALS['idlm_test_hooks'],
        static fn(array $hook): bool => 'action' === $hook['type']
    )));
    $shortcodes = array_values(array_map(static fn(array $hook): string => $hook['hook'], array_filter(
        $GLOBALS['idlm_test_hooks'],
        static fn(array $hook): bool => 'shortcode' === $hook['type']
    )));
    $expected_actions = array(
        'admin_menu', 'admin_enqueue_scripts', 'wp_enqueue_scripts',
        'admin_post_idlm_update_my_account', 'admin_post_idlm_rotate_api_credential', 'admin_post_idlm_create_user', 'admin_post_idlm_update_user',
        'wp_ajax_idlm_get_lead_details', 'wp_ajax_idlm_get_lead_activity',
        'wp_ajax_idlm_quick_edit_lead', 'wp_ajax_idlm_save_activity_comment',
        'wp_ajax_idlm_edit_activity_comment', 'wp_ajax_idlm_delete_activity_comment',
        'wp_ajax_idlm_export_leads', 'wp_ajax_idlm_export_google_conversions',
        'wp_ajax_idlm_add_lead_manual', 'wp_ajax_idlm_trigger_manual_audience_sync',
        'wp_ajax_idlm_bulk_action', 'wp_ajax_idlm_get_leads_table',
        'wp_ajax_idlm_get_kanban_leads', 'wp_ajax_idlm_get_dashboard_stats',
        'wp_ajax_idlm_save_lead_details', 'wp_ajax_idlm_delete_lead',
        'wp_ajax_idlm_import_leads_batch', 'wp_ajax_idlm_upload_lead_file',
        'wp_ajax_idlm_get_total_leads_count', 'wp_ajax_idlm_reset_crm_data',
        'wp_ajax_idlm_test_api_connection', 'wp_ajax_idlm_retry_capi_events',
        'wp_ajax_idlm_log_js_error', 'wp_ajax_idlm_clear_telemetry_logs',
        'wp_ajax_idlm_download_diagnostic_report', 'wp_ajax_idlm_save_routing_settings',
        'wp_ajax_idlm_run_fallback_now', 'wp_ajax_idlm_log_presales_touch',
        'wp_ajax_idlm_presales_decision', 'wp_ajax_idlm_get_mapped_past_leads',
        'wp_ajax_idlm_sync_mapped_past_batch', 'wp_ajax_idlm_get_lead_tasks',
        'wp_ajax_idlm_add_lead_task', 'wp_ajax_idlm_toggle_task_status',
        'wp_ajax_idlm_delete_lead_task', 'wp_ajax_idlm_get_rep_tasks',
    );
    assert_same($expected_actions, $actions);
    assert_same(array('idlm_leads_list', 'idlm_dashboard', 'idlm_kanban', 'idlm_workspace'), $shortcodes);

    foreach ($GLOBALS['idlm_test_hooks'] as $hook) {
        assert_true(is_array($hook['callback']) && 'IDLM_Admin' === $hook['callback'][0]);
        assert_true(is_callable($hook['callback']), 'Registered admin callback is not callable: ' . $hook['hook']);
    }

    IDLM_Admin::register_admin_menus();
    $slugs = array_map(static function(array $call): string {
        return (string) $call['args']['menu' === $call['type'] ? 3 : 4];
    }, $GLOBALS['idlm_test_menu_calls']);
    $caps = array_map(static function(array $call): string {
        return (string) $call['args']['menu' === $call['type'] ? 2 : 3];
    }, $GLOBALS['idlm_test_menu_calls']);
    assert_same(array(
        'interior-design-lead-manager', 'interior-design-lead-manager', 'idlm_leads_list',
        'idlm_kanban', 'idlm_capi_logs', 'idlm_audience_sync_logs', 'idlm_settings', 'idlm_users_access',
    ), $slugs);
    assert_same(array('read', 'read', 'read', 'read', 'manage_options', 'manage_options', 'manage_options', 'manage_options'), $caps);
});

idlm_test('admin core asset handles and localized JavaScript contract remain stable', static function(): void {
    IDLM_Admin::register_core_assets();
    assert_same(array(
        'idlm-google-fonts', 'idlm-admin-styles', 'idlm-dashboard-styles',
        'idlm-kanban-styles', 'idlm-workspace-styles',
    ), idlm_admin_asset_handles('register_style'));
    assert_same(array(
        'idlm-chartjs', 'idlm-admin-app', 'idlm-dashboard-app',
        'idlm-kanban-app', 'idlm-workspace-app',
    ), idlm_admin_asset_handles('register_script'));
    $localized = array_values(array_filter($GLOBALS['idlm_test_asset_calls'], static fn(array $call): bool => 'localize_script' === $call['type']));
    assert_count_value(1, $localized);
    assert_same('idlm-admin-app', $localized[0]['args'][0]);
    assert_same('idlm_vars', $localized[0]['args'][1]);
    assert_same(array('ajax_url', 'nonce', 'call_statuses'), array_keys($localized[0]['args'][2]));
    assert_count_value(1, array_values(array_filter($GLOBALS['idlm_test_asset_calls'], static fn(array $call): bool => 'inline_script' === $call['type'])));
});

idlm_test('admin and shortcode contexts enqueue only their established asset sets', static function(): void {
    IDLM_Admin::enqueue_assets('unrelated_page');
    assert_count_value(0, $GLOBALS['idlm_test_asset_calls']);

    $GLOBALS['idlm_test_asset_calls'] = array();
    IDLM_Admin::enqueue_assets('toplevel_page_interior-design-lead-manager');
    assert_same(array('idlm-google-fonts', 'idlm-admin-styles', 'idlm-dashboard-styles', 'idlm-kanban-styles'), idlm_admin_asset_handles('enqueue_style'));
    assert_same(array('idlm-chartjs', 'idlm-admin-app', 'idlm-dashboard-app', 'idlm-kanban-app'), idlm_admin_asset_handles('enqueue_script'));

    $contexts = array(
        '[idlm_leads_list]' => array(
            array('idlm-google-fonts', 'idlm-admin-styles'),
            array('idlm-admin-app'),
        ),
        '[idlm_dashboard]' => array(
            array('idlm-google-fonts', 'idlm-admin-styles', 'idlm-dashboard-styles'),
            array('idlm-admin-app', 'idlm-chartjs', 'idlm-dashboard-app'),
        ),
        '[idlm_kanban]' => array(
            array('idlm-google-fonts', 'idlm-admin-styles', 'idlm-kanban-styles'),
            array('idlm-admin-app', 'idlm-kanban-app'),
        ),
        '[idlm_workspace]' => array(
            array('idlm-google-fonts', 'idlm-admin-styles', 'idlm-workspace-styles', 'idlm-dashboard-styles', 'idlm-kanban-styles'),
            array('idlm-admin-app', 'idlm-workspace-app', 'idlm-chartjs', 'idlm-dashboard-app', 'idlm-kanban-app'),
        ),
    );
    foreach ($contexts as $content => $expected) {
        $GLOBALS['idlm_test_asset_calls'] = array();
        $GLOBALS['post'] = new WP_Post($content);
        IDLM_Admin::enqueue_frontend_assets();
        assert_same($expected[0], idlm_admin_asset_handles('enqueue_style'), 'Unexpected frontend style set for ' . $content);
        assert_same($expected[1], idlm_admin_asset_handles('enqueue_script'), 'Unexpected frontend script set for ' . $content);
    }
});

idlm_test('admin page, overlay, workspace, and account selector contracts remain present', static function(): void {
    $source = idlm_admin_presentation_source();
    $contracts = array(
        'dashboard' => array('idlm-period-selector', 'idlm-marketing-tab', 'chart-leads-over-time', 'idlm-sales-tab', 'sales-funnel-container'),
        'leads' => array('idlm-lead-filters', 'filter-search', 'idlm-bulk-action-select', 'idlm-leads-table', 'idlm-import-btn', 'idlm-routing-settings-btn'),
        'overlays' => array('idlm-lead-drawer', 'idlm-callback-modal', 'idlm-deal-value-modal', 'idlm-quote-value-modal', 'idlm-lost-remark-modal', 'idlm-global-tasks-sidebar'),
        'kanban' => array('idlm-kanban-filter-search', 'idlm-kanban-board', 'idlm-kanban-deal-modal', 'idlm-kanban-quote-modal', 'idlm-kanban-lost-modal'),
        'logs' => array('idlm-select-all-capi', 'idlm-retry-capi-btn', 'audience-log-details-'),
        'settings' => array('idlm-settings-form', 'settings-general', 'settings-routing', 'settings-webhooks', 'settings-capi', 'settings-audience-reminders', 'settings-health', 'settings-mcp', 'settings-danger-zone'),
        'workspace' => array('idlm-workspace', 'idlm-workspace-nav', 'idlm-workspace-content'),
        'account' => array('idlm-account-first-name', 'idlm-account-email', 'idlm-account-password', 'idlm-account-password-confirm', 'idlm-account-signout'),
    );
    foreach ($contracts as $area => $selectors) {
        foreach ($selectors as $selector) {
            assert_contains($selector, $source, 'Missing ' . $area . ' selector contract.');
        }
    }
    foreach (array('access-restricted.php', 'workspace.php', 'my-account.php') as $template) {
        assert_true(is_file(dirname(__DIR__) . '/templates/' . $template), 'Missing guarded template: ' . $template);
    }
});

idlm_test('lead capability matrix preserves administrator, manager, representative, pre-sales, owner, and unauthorized access', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_leads', array(
        lead_fixture(array('id' => 1, 'assigned_rep_id' => 7, 'intake_owner_id' => 8, 'qualified_by_user_id' => 8)),
        lead_fixture(array('id' => 2, 'assigned_rep_id' => 9)),
    ));

    idlm_test_login(1, array('administrator'), array('manage_options'));
    assert_true(IDLM_Lead_Access_Policy::has_rep_access());
    assert_true(IDLM_Lead_Access_Policy::can_assign());
    assert_same(0, IDLM_Lead_Access_Policy::restriction_user_id());
    assert_true(IDLM_Lead_Access_Policy::can_access(2));

    idlm_test_login(5, array('sales_manager'), array('idlm_access_leads', 'idlm_assign_leads'));
    assert_true(IDLM_Lead_Access_Policy::can_assign());
    assert_same(0, IDLM_Lead_Access_Policy::restriction_user_id());

    $GLOBALS['idlm_test_options']['idlm_reps_see_all_leads'] = '0';
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
    assert_true(IDLM_Lead_Access_Policy::has_rep_access());
    assert_same(7, IDLM_Lead_Access_Policy::restriction_user_id());
    assert_true(IDLM_Lead_Access_Policy::can_access(1));
    assert_false(IDLM_Lead_Access_Policy::can_access(2));

    $GLOBALS['idlm_test_options']['idlm_reps_see_all_leads'] = '1';
    idlm_test_login(8, array('idlm_pre_sales'), array('idlm_access_leads', 'idlm_qualify_leads'));
    assert_true(IDLM_Lead_Access_Policy::is_pre_sales());
    assert_same(8, IDLM_Lead_Access_Policy::restriction_user_id());
    $params = array();
    assert_contains('intake_owner_id', IDLM_Lead_Access_Policy::scope_sql('l', $params, true));
    assert_same(array(8, 8, 8), $params);

    idlm_test_login(12, array('subscriber'), array('read'));
    assert_false(IDLM_Lead_Access_Policy::has_rep_access());
    assert_false(IDLM_Lead_Access_Policy::can_assign());
});

idlm_test('settings controller retains capability, nonce, sanitization, grouped mirroring, and secret-store contracts', static function(): void {
    $root = dirname(__DIR__);
    $facade = (string) file_get_contents($root . '/includes/class-admin.php');
    $renderer = (string) file_get_contents($root . '/includes/class-admin-settings-renderer.php');
    $source = (string) file_get_contents($root . '/includes/class-admin-settings-controller.php');
    assert_true(class_exists('IDLM_Admin_Settings_Controller'));
    assert_true(class_exists('IDLM_Admin_Settings_Renderer'));
    assert_contains('return IDLM_Admin_Settings_Renderer::render();', $facade);
    assert_contains('IDLM_Admin_Settings_Controller::process_request()', $renderer);
    foreach (array('webhook_key', 'mcp_api_key', 'google_gadm_password', 'meta_access_token', 'google_api_secret', 'linkedin_access_token', 'interakt_api_key', 'interakt_webhook_secret', 'meta_webhook_verify_token', 'meta_webhook_app_secret') as $secret_key) {
        assert_false(false !== strpos($renderer, "'{$secret_key}' =>"), $secret_key . ' must not be added to the shared template context.');
    }
    $request = strpos($source, 'public static function process_request');
    $capability = strpos($source, "if (!current_user_can('manage_options'))", $request);
    $nonce = strpos($source, "check_admin_referer('idlm_settings_save_action')", $capability);
    $first_write = strpos($source, "update_option('idlm_monthly_lead_target'", $nonce);
    assert_true(false !== $request && false !== $capability && false !== $nonce && false !== $first_write && $request < $capability && $capability < $nonce && $nonce < $first_write);
    assert_contains("sanitize_key(wp_unslash(\$_POST['assignment_mode']))", $source);
    assert_contains('IDLM_Settings::sync_routing_from_legacy()', $source);
    assert_contains("IDLM_Secret_Store::set('idlm_google_gadm_password'", $source);
    assert_true(count(file($root . '/includes/class-admin.php')) <= 960, 'The Phase 3 settings-renderer extraction exceeded its 960-line facade size contract.');

    $settings_templates = glob($root . '/templates/admin/settings/*.php');
    assert_true(is_array($settings_templates) && 10 === count($settings_templates));
    foreach ($settings_templates as $template) {
        $template_source = (string) file_get_contents($template);
        assert_false(false !== strpos($template_source, 'get_option('), basename($template) . ' must receive option values through context.');
        assert_false(false !== strpos($template_source, '$wpdb'), basename($template) . ' must not query the database.');
        assert_false(false !== strpos($template_source, 'IDLM_Secret_Store'), basename($template) . ' must not read protected credentials.');
        assert_false(false !== strpos($template_source, 'current_user_can('), basename($template) . ' must receive capability context.');
        assert_true(count(file($template)) < 500, basename($template) . ' exceeded the settings-template size boundary.');
    }
});

idlm_test('settings shortcode examples remain literal in the frontend workspace', static function(): void {
    $template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/settings/general.php');

    foreach (array('idlm_workspace', 'idlm_leads_list', 'idlm_dashboard', 'idlm_kanban') as $shortcode) {
        assert_contains('<code class="idlm-code">&#91;' . $shortcode . '&#93;</code>', $template);
        assert_false(false !== strpos($template, '<code class="idlm-code">[' . $shortcode . ']</code>'), $shortcode . ' must not execute while the frontend workspace shortcode is expanding.');
    }
});

idlm_test('admin Phase 4 shell responsibilities retain compatibility delegates', static function(): void {
    $root = dirname(__DIR__);
    $facade = (string) file_get_contents($root . '/includes/class-admin.php');

    assert_true(class_exists('IDLM_Admin_Assets'));
    assert_true(class_exists('IDLM_Admin_Shortcodes'));
    assert_true(class_exists('IDLM_Admin_Presenter'));
    assert_true(class_exists('IDLM_Admin_Lead_Notifications'));
    assert_true(class_exists('IDLM_Admin_Account_Controller'));
    foreach (array(
        'return IDLM_Admin_Assets::register_core_assets();',
        'return IDLM_Admin_Assets::enqueue_assets($hook);',
        'return IDLM_Admin_Assets::enqueue_frontend_assets();',
        'return IDLM_Admin_Shortcodes::render_leads_list_shortcode();',
        'return IDLM_Admin_Shortcodes::render_dashboard_shortcode();',
        'return IDLM_Admin_Shortcodes::render_kanban_shortcode();',
        'return IDLM_Admin_Shortcodes::render_workspace_shortcode();',
        'return IDLM_Admin_Account_Controller::render();',
        'return IDLM_Admin_Account_Controller::handle_update();',
        'return IDLM_Admin_Settings_Controller::handle_rotate_api_credential();',
        'return IDLM_Admin_Presenter::format_datetime($mysql_date, $format);',
        'return IDLM_Admin_Lead_Notifications::get_pending_calls_count();',
    ) as $delegate) {
        assert_contains($delegate, $facade);
    }

    $facade_lines = count(file($root . '/includes/class-admin.php'));
    assert_true($facade_lines >= 400 && $facade_lines <= 800, 'The Phase 4 facade must remain within its approximately 500–800 line target.');
    foreach (array('class-admin-assets.php', 'class-admin-shortcodes.php', 'class-admin-presenter.php', 'class-admin-lead-notifications.php', 'class-admin-account-controller.php') as $support_file) {
        assert_true(count(file($root . '/includes/' . $support_file)) < 500, $support_file . ' exceeded its focused-class size boundary.');
    }
    assert_false(false !== strpos($facade, 'wp_register_style('), 'Asset registration must remain outside the facade.');
    assert_false(false !== strpos($facade, 'has_shortcode('), 'Shortcode context detection must remain outside the facade.');
    assert_false(false !== strpos($facade, 'SELECT MAX(id) FROM'), 'WhatsApp notification queries must remain outside the facade.');
});

idlm_test('production scalability Phase 5 starts with a focused lead-detail AJAX read boundary', static function(): void {
    $root = dirname(__DIR__);
    $facade = (string) file_get_contents($root . '/includes/class-admin.php');
    $ajax = (string) file_get_contents($root . '/includes/class-admin-ajax.php');
    $reads = (string) file_get_contents($root . '/includes/class-admin-lead-reads.php');

    assert_true(class_exists('IDLM_Admin_Lead_Reads'));
    assert_contains('return IDLM_Admin_Ajax::ajax_get_lead_details();', $facade);
    assert_contains('return IDLM_Admin_Lead_Reads::ajax_get_lead_details();', $ajax);
    assert_contains("check_ajax_referer('idlm_admin_nonce', 'nonce')", $reads);

    $handler = strpos($reads, 'public static function ajax_get_lead_details()');
    $broad_access = strpos($reads, 'current_user_has_rep_access()', $handler);
    $object_access = strpos($reads, 'current_user_can_view_lead($lead_id)', $broad_access);
    $lead_read = strpos($reads, '$wpdb->get_row(', $object_access);
    assert_true(false !== $handler && false !== $broad_access && false !== $object_access && false !== $lead_read);
    assert_true($handler < $broad_access && $broad_access < $object_access && $object_access < $lead_read);
    assert_contains('idlm-edit-lead-form', $reads);
    assert_contains("'unread_whatsapp_count'", $reads);
    assert_true(count(file($root . '/includes/class-admin-lead-reads.php')) < 800, 'The focused lead-detail read handler exceeded the Phase 5 class-size boundary.');
    assert_true(count(file($root . '/includes/class-admin-ajax.php')) < 3200, 'The remaining AJAX coordinator did not shrink after extraction.');

    idlm_test_login(12, array('subscriber'), array('read'));
    $response = expect_json_response(array('IDLM_Admin', 'ajax_get_lead_details'), false);
    assert_same('Permission denied.', $response->data['message']);

    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('id' => 2, 'assigned_rep_id' => 9))));
    $GLOBALS['idlm_test_options']['idlm_reps_see_all_leads'] = '0';
    idlm_test_login(7, array('sales_executive'), array('idlm_access_leads'));
    $_POST['lead_id'] = '2';
    $response = expect_json_response(array('IDLM_Admin', 'ajax_get_lead_details'), false);
    assert_same('You can access only leads assigned to you.', $response->data['message']);
});
