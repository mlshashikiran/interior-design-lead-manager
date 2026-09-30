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
        'handle_update_my_account', 'handle_rotate_api_credential', 'handle_create_user', 'handle_update_user', 'handle_update_role_scopes', 'register_admin_menus',
        'redirect_legacy_admin_page', 'admin_utility_url', 'render_admin_utility_page',
        'render_dashboard_page', 'render_users_access_page', 'render_leads_list_page', 'render_common_overlays', 'render_kanban_page',
        'render_capi_logs_page', 'render_audience_sync_logs_page', 'render_settings_page',
        'ajax_get_lead_details', 'ajax_mark_whatsapp_messages_read', 'ajax_get_lead_activity', 'ajax_get_capi_log_payload', 'ajax_get_audience_log_payload', 'ajax_quick_edit_lead',
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
    assert_same(85, count($actual));
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

    $dashboard = IDLM_View::render('admin/dashboard.php', array('access' => array('sales' => true, 'marketing' => true, 'manager' => true, 'pre_sales' => false, 'scope' => 'All permitted leads')));
    assert_contains('id="idlm-period-selector"', $dashboard);
    assert_contains('id="idlm-rep-outcomes"', $dashboard);
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
    assert_false(false !== strpos($leads_template, 'btn-toggle-more-filters'), 'Leads filters must not use a secondary disclosure button.');
    foreach (array('filter-date-basis', 'filter-date-range', 'filter-stage', 'filter-call-status', 'filter-rep', 'filter-source', 'filter-quality', 'filter-followup') as $filter_id) {
        assert_contains('id="' . $filter_id . '"', $leads_template, 'Missing visible lead filter: ' . $filter_id);
    }
    assert_contains('class="idlm-filters-secondary-row" id="idlm-secondary-filters-container">', $leads_template, 'Additional lead filters must be rendered without a collapsed inline style.');
    assert_contains('idlm-leads-table', $leads_template);
    assert_contains('idlm-export-modal', $leads_template);
    assert_contains('idlm-lead-drawer', (string) file_get_contents($root . '/templates/admin/common-overlays.php'));
});

idlm_test('Kanban header reports overall leads and filtered current-month wins by conversion date', static function(): void {
    $root = dirname(__DIR__);
    $ajax_source = (string) file_get_contents($root . '/includes/class-admin-ajax.php');
    $template = (string) file_get_contents($root . '/templates/admin/kanban.php');
    $script = (string) file_get_contents($root . '/assets/js/kanban.js');

    assert_contains('COUNT(*) AS total_filtered', $ajax_source);
    assert_contains("l.conversion_time >= %s", $ajax_source);
    assert_contains("l.conversion_time < %s", $ajax_source);
    assert_contains('$monthly_won_conditions = $where;', $ajax_source);
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

idlm_test('Kanban no longer exposes the superseded newly assigned queue', static function(): void {
    $root = dirname(__DIR__);
    $notifications = (string) file_get_contents($root . '/includes/class-admin-lead-notifications.php');
    $renderer = (string) file_get_contents($root . '/includes/class-admin-kanban-renderer.php');
    $ajax_source = (string) file_get_contents($root . '/includes/class-admin-ajax.php');
    $template = (string) file_get_contents($root . '/templates/admin/kanban.php');
    $script = (string) file_get_contents($root . '/assets/js/kanban.js');

    assert_false(false !== strpos($notifications, 'get_newly_assigned_lead_count'));
    assert_false(false !== strpos($renderer, 'newly_assigned_lead_count'));
    assert_false(false !== strpos($ajax_source, "'newly_assigned' === \$post_attention"));
    assert_false(false !== strpos($template, 'idlm-kanban-newly-assigned-pill'));
    assert_false(false !== strpos($template, 'value="newly_assigned"'));
    assert_false(false !== strpos($script, 'newly_assigned'));
});

idlm_test('Kanban cards expose compact needs-update and task pills together', static function(): void {
    $root = dirname(__DIR__);
    $ajax_source = (string) file_get_contents($root . '/includes/class-admin-ajax.php');
    $script = (string) file_get_contents($root . '/assets/js/kanban.js');
    $styles = (string) file_get_contents($root . '/assets/css/kanban.css');

    assert_contains('as needs_update', $ajax_source);
    assert_contains('get_no_recent_assigned_rep_action_sql($stale_cutoff', $ajax_source);
    assert_contains('idlm-kanban-task-pill needs-update', $script);
    assert_contains('Needs update', $script);
    assert_contains('.idlm-kanban-task-pill.needs-update', $styles);
    assert_contains('font-size: 9px;', $styles);
});

idlm_test('clickable lead and Kanban filter pills toggle their filters off', static function(): void {
    $root = dirname(__DIR__);
    $admin_script = (string) file_get_contents($root . '/assets/js/admin-app.js');
    $kanban_script = (string) file_get_contents($root . '/assets/js/kanban.js');

    foreach (array('pending_calls', 'unread_whatsapp', 'stale_updates') as $filter) {
        assert_contains("=== '" . $filter . "'", $admin_script);
        assert_contains("$('#filter-followup').val('').change()", $admin_script);
    }
    assert_contains('var filterTargets = {', $admin_script);
    assert_contains("$(target[0]).val(isActive ? '' : target[1])", $admin_script);
    foreach (array('stale_updates') as $filter) {
        assert_contains("=== '" . $filter . "'", $kanban_script);
        assert_contains("$('#idlm-kanban-filter-attention').val('')", $kanban_script);
    }
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

idlm_test('lead drawer Tasks tab shows a live overdue-task notification count', static function(): void {
    $root = dirname(__DIR__);
    $drawer_source = (string) file_get_contents($root . '/includes/class-admin-lead-reads.php');
    $script_source = (string) file_get_contents($root . '/assets/js/admin-app.js');
    $styles = (string) file_get_contents($root . '/assets/css/admin-style.css');

    assert_contains('idlm-drawer-overdue-task-count', $drawer_source);
    assert_contains('idlm-drawer-tab-notification', $drawer_source);
    assert_contains('tasks.filter(function(task) { return Boolean(task.is_overdue); }).length', $script_source);
    assert_contains(".prop('hidden', overdueCount === 0)", $script_source);
    assert_contains('// Load once on drawer open so the Tasks tab can surface overdue work immediately.', $script_source);
    assert_contains('.idlm-drawer-tab-notification', $styles);
});

idlm_test('lead drawer WhatsApp tab lists recorded messages and marks them read only when opened', static function(): void {
    $root = dirname(__DIR__);
    $drawer_source = (string) file_get_contents($root . '/includes/class-admin-lead-reads.php');
    $script_source = (string) file_get_contents($root . '/assets/js/admin-app.js');

    assert_contains("'drawer-tab-whatsapp'", $drawer_source);
    assert_contains('idlm-drawer-whatsapp-list', $drawer_source);
    assert_contains('idlm-drawer-whatsapp-count', $drawer_source);
    assert_contains("get_option('idlm_interakt_enabled', '0')", $drawer_source);
    assert_contains('idlm-drawer-open-interakt', $drawer_source);
    assert_contains('IDLM_Interakt::inbox_url($lead->phone)', $drawer_source);
    assert_contains('https://app.interakt.ai/inbox?channelPhoneNumber=', (string) file_get_contents($root . '/includes/class-interakt.php'));
    assert_contains('channelPhoneNumber=', (string) file_get_contents($root . '/includes/class-interakt.php'));
    assert_contains('&chatType=All', (string) file_get_contents($root . '/includes/class-interakt.php'));
    assert_false(false !== strpos($drawer_source, 'idlm-whatsapp-reply-alert'));
    assert_contains("action: 'idlm_mark_whatsapp_messages_read'", $script_source);
    assert_contains("tabId === 'drawer-tab-whatsapp'", $script_source);
});

idlm_test('lead drawer task list orders overdue and pending work before completed tasks', static function(): void {
    $ajax_source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-ajax.php');

    assert_contains("WHEN status = 'pending' AND due_date IS NOT NULL", $ajax_source);
    assert_contains("WHEN status = 'pending' THEN 1", $ajax_source);
    assert_contains("ELSE 2", $ajax_source);
    assert_contains("CASE WHEN due_date IS NULL OR due_date = '0000-00-00 00:00:00' THEN 1 ELSE 0 END ASC", $ajax_source);
});

idlm_test('settings support UI-only labels and draggable pipeline ordering', static function(): void {
    $root = dirname(__DIR__);
    $template = (string) file_get_contents($root . '/templates/admin/settings/token-editor.php');
    $general = (string) file_get_contents($root . '/templates/admin/settings/general.php');
    $settings_script = (string) file_get_contents($root . '/assets/js/settings.js');
    $app_script = (string) file_get_contents($root . '/assets/js/admin-app.js');
    $assets = (string) file_get_contents($root . '/includes/class-admin-assets.php');

    assert_contains('name="ui_labels[', $general);
    assert_contains('name="value_labels[', $template);
    assert_contains('draggable="true"', $template);
    assert_contains("on('dragstart'", $settings_script);
    assert_contains('applyDisplayAliases', $app_script);
    assert_contains("'ui_labels' =>", $assets);
    assert_contains("'value_labels' =>", $assets);
});

idlm_test('General settings prioritizes metrics above the two configuration cards', static function(): void {
    $root = dirname(__DIR__);
    $template = (string) file_get_contents($root . '/templates/admin/settings/general.php');
    $styles = (string) file_get_contents($root . '/assets/css/admin-style.css');

    assert_contains('idlm-settings-general-metrics', $template);
    assert_contains('.idlm-wrap .idlm-settings-general-metrics', $styles);
    assert_contains('grid-column: 1 / -1;', $styles);
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
        'admin_menu', 'admin_init', 'wp_enqueue_scripts',
        'admin_post_idlm_update_my_account', 'admin_post_idlm_rotate_api_credential', 'admin_post_idlm_create_user', 'admin_post_idlm_update_user', 'admin_post_idlm_update_role_scopes',
        'wp_ajax_idlm_get_lead_details', 'wp_ajax_idlm_mark_whatsapp_messages_read', 'wp_ajax_idlm_get_lead_activity', 'wp_ajax_idlm_get_capi_log_payload', 'wp_ajax_idlm_get_audience_log_payload',
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
    assert_same(array('idlm_utilities'), $slugs);
    assert_same(array('manage_options'), $caps);
});

idlm_test('admin core asset handles and localized JavaScript contract remain stable', static function(): void {
    IDLM_Admin::register_core_assets();
    assert_same(array(
        'idlm-google-fonts', 'idlm-admin-styles', 'idlm-dashboard-styles',
        'idlm-kanban-styles', 'idlm-workspace-styles',
    ), idlm_admin_asset_handles('register_style'));
    assert_same(array(
        'idlm-chartjs', 'idlm-dialog', 'idlm-admin-app', 'idlm-settings', 'idlm-users-logs', 'idlm-dashboard-app',
        'idlm-kanban-app', 'idlm-workspace-app', 'idlm-access-account',
    ), idlm_admin_asset_handles('register_script'));
    $localized = array_values(array_filter($GLOBALS['idlm_test_asset_calls'], static fn(array $call): bool => 'localize_script' === $call['type']));
    assert_count_value(1, $localized);
    assert_same('idlm-admin-app', $localized[0]['args'][0]);
    assert_same('idlm_vars', $localized[0]['args'][1]);
    assert_same(array('ajax_url', 'nonce', 'call_statuses', 'ui_labels', 'value_labels'), array_keys($localized[0]['args'][2]));
    assert_count_value(1, array_values(array_filter($GLOBALS['idlm_test_asset_calls'], static fn(array $call): bool => 'inline_script' === $call['type'])));
});

idlm_test('admin utility stays asset-free while shortcode contexts enqueue their required assets', static function(): void {
    IDLM_Admin::enqueue_assets('unrelated_page');
    assert_count_value(0, $GLOBALS['idlm_test_asset_calls']);

    $GLOBALS['idlm_test_asset_calls'] = array();
    IDLM_Admin::enqueue_assets('toplevel_page_idlm_utilities');
    assert_count_value(0, $GLOBALS['idlm_test_asset_calls']);

    $contexts = array(
        '[idlm_leads_list]' => array(
            array('idlm-google-fonts', 'idlm-admin-styles'),
            array('idlm-dialog', 'idlm-admin-app', 'idlm-access-account'),
        ),
        '[idlm_dashboard]' => array(
            array('idlm-google-fonts', 'idlm-admin-styles', 'idlm-dashboard-styles'),
            array('idlm-dialog', 'idlm-admin-app', 'idlm-access-account', 'idlm-chartjs', 'idlm-dashboard-app'),
        ),
        '[idlm_kanban]' => array(
            array('idlm-google-fonts', 'idlm-admin-styles', 'idlm-kanban-styles'),
            array('idlm-dialog', 'idlm-admin-app', 'idlm-access-account', 'idlm-kanban-app'),
        ),
        '[idlm_workspace]' => array(
            array('idlm-google-fonts', 'idlm-admin-styles', 'idlm-workspace-styles', 'idlm-dashboard-styles', 'idlm-kanban-styles'),
            array('idlm-dialog', 'idlm-admin-app', 'idlm-access-account', 'idlm-workspace-app', 'idlm-users-logs', 'idlm-settings', 'idlm-chartjs', 'idlm-dashboard-app', 'idlm-kanban-app'),
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

idlm_test('Phase 1 accessible dialog and design-token contracts remain present', static function(): void {
    $root = dirname(__DIR__);
    $dialog = (string) file_get_contents($root . '/assets/js/dialog.js');
    $styles = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $overlays = (string) file_get_contents($root . '/templates/admin/common-overlays.php');
    $kanban = (string) file_get_contents($root . '/templates/admin/kanban.php');
    $quotations = (string) file_get_contents($root . '/includes/class-quotation-renderer.php');

    foreach (array('focusableSelector', "event.key === 'Escape'", "event.key !== 'Tab'", 'returnFocus', 'makeBackgroundInert', 'idlm-modal-open') as $contract) {
        assert_contains($contract, $dialog, 'Missing shared dialog-controller behavior.');
    }
    foreach (array('--idlm-action: #7a5017', '--idlm-control-height: 34px', '--idlm-target-size: 44px', '--idlm-focus-ring:', '--idlm-layer-modal:', '@media (prefers-reduced-motion: reduce)') as $contract) {
        assert_contains($contract, $styles, 'Missing canonical Phase 1 design token or behavior.');
    }
    foreach (array('idlm-callback-modal', 'idlm-deal-value-modal', 'idlm-quote-value-modal', 'idlm-lost-remark-modal', 'idlm-unsaved-changes-modal') as $id) {
        assert_contains('id="' . $id . '" role="dialog" aria-modal="true"', $overlays, 'Shared Leads dialog is not accessible: ' . $id);
    }
    foreach (array('idlm-kanban-deal-modal', 'idlm-kanban-quote-modal', 'idlm-kanban-lost-modal') as $id) {
        assert_contains('id="' . $id . '" role="dialog" aria-modal="true"', $kanban, 'Kanban dialog is not accessible: ' . $id);
    }
    assert_contains('data-autosave-cancel-modal data-idlm-dialog', $quotations);
    assert_contains('aria-errormessage=', $overlays);
    assert_contains('aria-errormessage=', $kanban);
});

idlm_test('admin page, overlay, workspace, and account selector contracts remain present', static function(): void {
    $source = idlm_admin_presentation_source();
    $contracts = array(
        'dashboard' => array('idlm-period-selector', 'idlm-marketing-tab', 'chart-leads-over-time', 'idlm-sales-tab', 'sales-funnel-container'),
        'leads' => array('idlm-lead-filters', 'idlm-open-filters', 'idlm-header-filter-button', 'filter-search', 'idlm-bulk-action-select', 'idlm-leads-table', 'idlm-import-btn', 'idlm-routing-settings-btn'),
        'overlays' => array('idlm-lead-drawer', 'idlm-drawer-prev-lead', 'idlm-drawer-next-lead', 'idlm-callback-modal', 'idlm-deal-value-modal', 'idlm-quote-value-modal', 'idlm-lost-remark-modal', 'idlm-global-tasks-sidebar'),
        'kanban' => array('idlm-kanban-open-filters', 'idlm-kanban-filters', 'idlm-kanban-filter-search', 'idlm-kanban-clear-filters', 'idlm-kanban-board', 'idlm-kanban-deal-modal', 'idlm-kanban-quote-modal', 'idlm-kanban-lost-modal'),
        'logs' => array('idlm-select-all-capi', 'idlm-retry-capi-btn', 'idlm-audience-payload-dialog'),
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

idlm_test('role lead scopes override legacy visibility without granting mutation authority', static function(): void {
    $GLOBALS['wpdb']->seed('idlm_leads', array(
        lead_fixture(array('id' => 1, 'assigned_rep_id' => 7)),
        lead_fixture(array('id' => 2, 'assigned_rep_id' => 9)),
    ));
    update_option('idlm_role_lead_scopes', array('sales_manager' => 'assigned', 'sales_executive' => 'all'));
    idlm_test_login(7, array('sales_manager'), array('idlm_access_leads', 'idlm_assign_leads'));
    assert_same(7, IDLM_Lead_Access_Policy::restriction_user_id());
    assert_true(IDLM_Lead_Access_Policy::can_access(1));
    assert_false(IDLM_Lead_Access_Policy::can_access(2));
    $GLOBALS['idlm_test_options']['idlm_reps_see_all_leads'] = '0';
    idlm_test_login(8, array('sales_executive'), array('idlm_access_leads'));
    assert_same(0, IDLM_Lead_Access_Policy::restriction_user_id());
    assert_same(0, IDLM_Lead_Access_Policy::restriction_user_id(false));
    assert_true(IDLM_Lead_Access_Policy::can_access(2, true));
    assert_false(IDLM_Lead_Access_Policy::can_access(2));
    assert_same('assigned', IDLM_Admin_Users_Controller::role_scope(array('sales_executive', 'sales_manager')));
    assert_same('all', IDLM_Admin_Users_Controller::role_scope(array('administrator')));
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
    $first_write = strpos($source, 'update_option(IDLM_Settings::WHITE_LABEL_OPTION', $nonce);
    assert_true(false !== $request && false !== $capability && false !== $nonce && false !== $first_write && $request < $capability && $capability < $nonce && $nonce < $first_write);
    assert_contains("sanitize_key(wp_unslash(\$_POST['assignment_mode']))", $source);
    assert_contains('IDLM_Settings::sync_routing_from_legacy()', $source);
    assert_contains("IDLM_Secret_Store::set('idlm_google_gadm_password'", $source);
    assert_true(count(file($root . '/includes/class-admin.php')) <= 960, 'The Phase 3 settings-renderer extraction exceeded its 960-line facade size contract.');

    $settings_templates = glob($root . '/templates/admin/settings/*.php');
    assert_true(is_array($settings_templates) && 13 === count($settings_templates));
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
        assert_contains('<code class="idlm-code"><span aria-hidden="true">[</span>' . $shortcode . '<span aria-hidden="true">]</span></code>', $template);
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
    assert_true(count(file($root . '/includes/class-admin-ajax.php')) < 3350, 'The remaining AJAX coordinator exceeded its focused-class size boundary.');

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

idlm_test('frontend UI UX Phase 2 preserves keyboard, selection, and data-alternative contracts', static function(): void {
    $root = dirname(__DIR__);
    $dashboard_template = (string) file_get_contents($root . '/templates/admin/dashboard.php');
    $dashboard_script = (string) file_get_contents($root . '/assets/js/dashboard.js');
    $kanban_template = (string) file_get_contents($root . '/templates/admin/kanban.php');
    $kanban_script = (string) file_get_contents($root . '/assets/js/kanban.js');
    $leads_template = (string) file_get_contents($root . '/templates/admin/leads.php');
    $leads_script = (string) file_get_contents($root . '/assets/js/admin-app.js');
    $ajax = (string) file_get_contents($root . '/includes/class-admin-ajax.php');

    foreach (array('role="tablist"', 'role="tab"', 'aria-selected=', 'role="tabpanel"') as $contract) {
        assert_contains($contract, $dashboard_template);
    }
    assert_false(false !== strpos($dashboard_script, 'idlm-trend-table'));
    assert_false(false !== strpos($dashboard_template, 'View daily counts'));
    assert_contains('ArrowRight', $dashboard_script);

    assert_contains('idlm-kanban-live-status', $kanban_template);
    assert_contains('idlm-kanban-move-modal', $kanban_template);
    assert_contains('idlm-kanban-card-open', $kanban_script);
    assert_contains('idlm-kanban-card-move', $kanban_script);
    assert_contains('<footer class="idlm-kanban-card-actions">', $kanban_script);
    assert_contains('idlm-kanban-card-footer-meta', $kanban_script);
    assert_contains('requestStageMove', $kanban_script);
    assert_false(false !== strpos($kanban_script, '<article class="idlm-kanban-card" draggable="true"'), 'The whole Kanban card must not be the drag handle.');

    foreach (array('idlm-clear-selection', 'idlm-bulk-delete-modal', 'role="progressbar"', 'idlm-import-download-failures', 'idlm-export-count-preview') as $contract) {
        assert_contains($contract, $leads_template);
    }
    assert_contains('updateBulkSelectionState', $leads_script);
    assert_contains('lead-import-failures.csv', $leads_script);
    assert_contains("{ key: 'project_name'", $leads_script);
    assert_contains("{ key: 'possession_date'", $leads_script);
    assert_contains('claimedColumns', $leads_script);
    assert_contains('validateImportRows', $leads_script);
    assert_contains('Default Preferred Studio', $leads_template);
    assert_contains('Imported + Duplicates + Failed = ', $leads_script);
    assert_contains("'project_name' => isset(\$lead['project_name'])", $ajax);
    assert_contains("'possession_date' => !empty(\$lead['possession_date'])", $ajax);
    assert_contains("'count_only'", $ajax);
    assert_contains("'errors' => \$errors", $ajax);
    assert_contains('aria-label="Select lead', $ajax);
});

idlm_test('frontend UI UX Phase 3 preserves responsive workflow and interaction contracts', static function(): void {
    $root = dirname(__DIR__);
    $leads_template = (string) file_get_contents($root . '/templates/admin/leads.php');
    $leads_script = (string) file_get_contents($root . '/assets/js/admin-app.js');
    $leads_styles = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $ajax = (string) file_get_contents($root . '/includes/class-admin-ajax.php');
    $kanban_template = (string) file_get_contents($root . '/templates/admin/kanban.php');
    $kanban_script = (string) file_get_contents($root . '/assets/js/kanban.js');
    $kanban_styles = (string) file_get_contents($root . '/assets/css/kanban.css');
    $quotation_renderer = (string) file_get_contents($root . '/includes/class-quotation-renderer.php');
    $quotation_script = (string) file_get_contents($root . '/assets/js/quotations.js');
    $quotation_styles = (string) file_get_contents($root . '/assets/css/quotations.css');

    foreach (array('idlm-col-priority-primary', 'idlm-col-priority-secondary', 'idlm-col-priority-low') as $contract) {
        assert_contains($contract, $leads_template);
    }
    assert_contains('idlm-leads-mobile-sort', $leads_template);
    foreach (array('idlm-responsive-lead-row', 'idlm-lead-row-expand', 'aria-expanded="false"', 'data-label="Stage"', 'data-label="Follow-up status"', 'data-lead-drawer-id', 'idlm-lead-row-open') as $contract) {
        assert_contains($contract, $ajax);
    }
    assert_contains("$(document).on('click', '.idlm-lead-row-expand'", $leads_script);
    assert_contains("$(document).on('click', '.idlm-lead-identity-cell[data-lead-drawer-id]'", $leads_script);
    assert_contains("$('#idlm-leads-mobile-sort').on('change'", $leads_script);
    assert_contains('function debounce(callback, wait)', $leads_script);
    assert_contains('@media (max-width: 1024px)', $leads_styles);
    assert_contains('@media (max-width: 782px)', $leads_styles);
    assert_contains('@media (max-width: 480px)', $leads_styles);

    foreach (array('idlm-kanban-stage-tabs', 'role="tablist"', 'role="tab"', 'idlm-kanban-panel-') as $contract) {
        assert_contains($contract, $kanban_template);
    }
    assert_contains("matchMedia('(max-width: 782px)')", $kanban_script);
    assert_contains('activateMobileStage', $kanban_script);
    assert_contains("activeMobileStage = String(newStage)", $kanban_script);
    assert_contains('.idlm-kanban-column.is-mobile-active', $kanban_styles);
    assert_contains('.idlm-kanban-card-drag-handle', $kanban_styles);
    assert_contains('.idlm-kanban-card-footer-meta', $kanban_styles);
    assert_contains('.idlm-kanban-card-badges {', $kanban_styles);
    assert_contains('flex-wrap: wrap;', $kanban_styles);

    foreach (array('idlm-quote-item-card', 'data-label="Item"', 'data-label="Quantity"', 'data-label="Rate"', 'data-label="Item actions"') as $contract) {
        assert_contains($contract, $quotation_renderer);
    }
    assert_contains('data-label="Header actions"', $quotation_script);
    assert_contains('Phase 3 structured item-card editor', $quotation_styles);
    assert_contains('@media(max-width:782px)', $quotation_styles);
    assert_contains('@media(prefers-reduced-motion:reduce)', $quotation_styles);
    assert_contains('class="idlm-btn idlm-btn-primary"', $quotation_renderer);
    assert_contains('class="idlm-btn idlm-btn-secondary"', $quotation_renderer);
    assert_contains('class="idlm-btn idlm-btn-secondary"', $quotation_script);
    assert_contains('.button:not(.idlm-btn)', $quotation_styles);

    foreach (array("action: 'idlm_get_leads_table'", "action: 'idlm_get_kanban_leads'", "action: 'idlm_quick_edit_lead'") as $contract) {
        assert_contains($contract, $leads_script . $kanban_script);
    }
    assert_contains('name="action" value="idlm_save_quote"', $quotation_renderer);
    assert_contains("name=\"<?php echo esc_attr(\$name); ?>[section_key]\"", $quotation_renderer);
});
