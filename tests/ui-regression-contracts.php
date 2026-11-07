<?php
/**
 * Phase 4 UI debt plus Phase 5 access/account regression contracts.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

function idlm_ui_php_sources(string $directory): string {
    $source = '';
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($files as $file) {
        if ($file->isFile() && 'php' === strtolower($file->getExtension())) {
            $source .= "\n" . (string) file_get_contents($file->getPathname());
        }
    }
    return $source;
}

function idlm_ui_css_sources(string $directory): string {
    $source = '';
    foreach (glob($directory . '/*.css') ?: array() as $file) {
        $source .= "\n" . (string) file_get_contents($file);
    }
    return $source;
}

idlm_test('Phase 4 settings remove inline presentation and expose accessible tab and dialog contracts', static function(): void {
    $root = dirname(__DIR__);
    $settings = idlm_ui_php_sources($root . '/templates/admin/settings');
    $shell = (string) file_get_contents($root . '/templates/admin/settings/shell.php');
    $health = (string) file_get_contents($root . '/templates/admin/settings/health.php');
    $danger = (string) file_get_contents($root . '/templates/admin/settings/danger-zone.php');

    assert_false((bool) preg_match('/\sstyle\s*=\s*["\']/i', $settings), 'Settings must use shared presentation contracts instead of inline styles.');
    assert_contains('role="tablist"', $shell);
    assert_contains('role="tab"', $shell);
    assert_contains('role="tabpanel"', $settings);
    assert_contains("event.key === 'ArrowRight'", $shell);
    foreach (array('idlm-settings-tabs', 'id="idlm-settings-tab-<?php echo esc_attr($idlm_tab_key); ?>"', "'general' => 'General'", "'danger' => array('danger-zone' => 'Danger zone')", 'idlm-rotate-webhook-form', 'idlm-rotate-mcp-form') as $contract) {
        assert_contains($contract, $shell);
    }
    assert_contains('window.IDLMDialog.open', $shell);
    assert_contains('window.IDLMDialog.close', $shell);
});

idlm_test('settings keep horizontal tab contracts without a secondary sidebar', static function(): void {
    $root = dirname(__DIR__);
    $shell = (string) file_get_contents($root . '/templates/admin/settings/shell.php');

    foreach (array('idlm-settings-tabs', 'role="tablist" aria-label="System settings tabs"', 'id="idlm-settings-tab-<?php echo esc_attr($idlm_tab_key); ?>"', 'onclick="switchSettingsTab(this, \'settings-<?php echo esc_attr($idlm_tab_key); ?>\')"', "'danger' => array('danger-zone' => 'Danger zone')") as $contract) {
        assert_contains($contract, $shell);
    }
    assert_false(false !== strpos($shell, 'idlm-settings-sidebar'), 'Settings must not render a second sidebar.');
});

idlm_test('Phase 4 visual matrix keeps canonical responsive, role, motion, and state hooks', static function(): void {
    $root = dirname(__DIR__);
    $admin_css = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $workspace = (string) file_get_contents($root . '/templates/workspace.php');
    $shortcodes = (string) file_get_contents($root . '/includes/class-admin-shortcodes.php');

    foreach (array('480px', '782px', '1024px', '1280px') as $viewport) {
        assert_contains($viewport, $admin_css . idlm_ui_css_sources($root . '/assets/css'));
    }
    foreach (array('.idlm-state.is-empty', '.idlm-state.is-loading', '.idlm-state.is-error', '.idlm-state.is-success') as $state) {
        assert_contains($state, $admin_css . idlm_ui_css_sources($root . '/assets/css'));
    }
    assert_contains('@media (prefers-reduced-motion: reduce)', $admin_css);
    assert_contains('data-idlm-role=', $workspace);
    assert_contains('idlm-skip-link', $workspace);
    foreach (array("'administrator'", "'pre-sales'", "'representative'") as $role) {
        assert_contains($role, $shortcodes);
    }
});

idlm_test('Phase 4 design-debt budgets prevent reintroducing legacy presentation volume', static function(): void {
    $root = dirname(__DIR__);
    $css = idlm_ui_css_sources($root . '/assets/css');
    $runtime_php = idlm_ui_php_sources($root . '/templates') . idlm_ui_php_sources($root . '/includes');

    preg_match_all('/\sstyle\s*=\s*["\']/i', $runtime_php, $inline_styles);
    preg_match_all('/#[0-9a-f]{3,8}\b/i', $css, $hard_coded_colours);
    preg_match_all('/!important\b/i', $css, $important_overrides);

    assert_true(count($inline_styles[0]) <= 170, 'Inline presentation exceeded the Phase 4 migration budget.');
    assert_true(count($hard_coded_colours[0]) <= 190, 'Hard-coded CSS colours exceeded the Phase 4 token budget.');
    assert_true(count($important_overrides[0]) <= 220, 'CSS important overrides exceeded the Phase 4 consolidation budget.');
    assert_false((bool) preg_match('/transition\s*:\s*all\b/i', $css), 'transition: all is prohibited.');
    assert_false(false !== strpos($css, 'var(--idlm-primary)'), 'Runtime CSS must use the canonical action token.');
    assert_false(false !== strpos($css, 'var(--idlm-bg-main)'), 'Runtime CSS must use the canonical canvas token.');
});

idlm_test('Phase 5 access and account forms expose recoverable password and status behavior', static function(): void {
    $root = dirname(__DIR__);
    $access = (string) file_get_contents($root . '/templates/access-restricted.php');
    $account = (string) file_get_contents($root . '/templates/my-account.php');
    $workspace = (string) file_get_contents($root . '/templates/workspace.php');
    $script = (string) file_get_contents($root . '/assets/js/access-account.js');
    $css = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $auth = (string) file_get_contents($root . '/includes/class-auth.php');
    $assets = (string) file_get_contents($root . '/includes/class-admin-assets.php');

    foreach (array('data-idlm-password-toggle', 'data-idlm-password-primary', 'data-idlm-password-confirm', 'data-idlm-password-error', 'data-idlm-notice', 'minlength="8"') as $contract) {
        assert_contains($contract, $access . $account);
    }
    foreach (array('autocomplete="given-name"', 'autocomplete="family-name"', 'autocomplete="email"', 'autocomplete="tel"', 'inputmode="tel"') as $contract) {
        assert_contains($contract, $account);
    }
    foreach (array("input.type = showing ? 'password' : 'text'", "setAttribute('aria-pressed'", 'setCustomValidity', "setAttribute('aria-busy'", 'notice.focus') as $contract) {
        assert_contains($contract, $script);
    }
    assert_contains('strlen($password) < 8', $auth);
    assert_contains("wp_register_script('idlm-access-account'", $assets);
    assert_contains('id="idlm-workspace-content" tabindex="-1"', $workspace);
    assert_contains('width: 38px; min-width: 38px;', $css);
    assert_false(false !== strpos($access . $account, 'data-idlm-password-toggle-label'), 'Password visibility controls must remain icon-only.');
});

idlm_test('Phase 6 settings expose structured options, save state, and credential controls', static function(): void {
    $root = dirname(__DIR__);
    $general = (string) file_get_contents($root . '/templates/admin/settings/general.php');
    $editor = (string) file_get_contents($root . '/templates/admin/settings/token-editor.php');
    $shell = (string) file_get_contents($root . '/templates/admin/settings/shell.php');
    $settings_script = (string) file_get_contents($root . '/assets/js/settings.js');
    $admin_script = (string) file_get_contents($root . '/assets/js/admin-app.js');
    $assets = (string) file_get_contents($root . '/includes/class-admin-assets.php');
    $controller = (string) file_get_contents($root . '/includes/class-admin-settings-controller.php');
    $credential_panels = (string) file_get_contents($root . '/templates/admin/settings/webhooks.php')
        . (string) file_get_contents($root . '/templates/admin/settings/mcp.php');

    foreach (array('custom_stages', 'custom_call_statuses', 'custom_lost_reasons', 'custom_studios', 'custom_num_rooms') as $field) {
        assert_contains("'field_name' => '" . $field . "'", $general);
    }
    foreach (array('data-idlm-token-editor', 'data-idlm-token-entry', 'data-idlm-token-value', 'data-idlm-token-move="up"', 'data-idlm-token-move="down"', 'data-idlm-token-remove', 'aria-live="polite"') as $contract) {
        assert_contains($contract, $editor);
    }
    foreach (array('idlm-settings-savebar', 'data-idlm-save-status', 'data-idlm-settings-saved', 'Save settings') as $contract) {
        assert_contains($contract, $shell);
    }
    foreach (array('validateTokenEditors', 'updateTokenOrderControls', 'moveToken', 'Remove pipeline stage?', 'Leads already in this stage will retain their stored stage until moved.', 'Unsaved changes', 'enhanceSecretField', 'navigator.clipboard', 'IDLMSettingsActionStatus') as $contract) {
        assert_contains($contract, $settings_script);
    }
    foreach (array('Object.prototype.hasOwnProperty.call(aliases, label)', 'var label = original.trim()') as $contract) {
        assert_contains($contract, $admin_script);
    }
    assert_contains('idlm-secret-control__reveal', $settings_script);
    assert_contains("'class': 'dashicons dashicons-visibility'", $settings_script);
    assert_contains("wp_register_script('idlm-settings'", $assets);
    assert_false(false !== strpos($assets, "strpos(\$hook, 'idlm_settings')"));
    assert_contains('private static function posted_vocabulary', $controller);
    assert_contains("'custom_stages_values'", $controller);
    assert_false(false !== strpos($credential_panels, '<form method='));
    assert_contains('id="idlm-rotate-webhook-form"', $shell);
    assert_contains('id="idlm-rotate-mcp-form"', $shell);
});

idlm_test('settings notices use accessible non-duplicating floating toasts with safe auto-close behavior', static function(): void {
    $root = dirname(__DIR__);
    $shell = (string) file_get_contents($root . '/templates/admin/settings/shell.php');
    $backup = (string) file_get_contents($root . '/templates/admin/settings/backup.php');
    $script = (string) file_get_contents($root . '/assets/js/settings.js');
    $css = (string) file_get_contents($root . '/assets/css/admin-style.css') . idlm_ui_css_sources($root . '/assets/css');

    foreach (array('data-idlm-settings-toast', 'data-idlm-toast-autoclose="4000"', 'notice-success', 'is-dismissible') as $contract) {
        assert_contains($contract, $shell);
    }
    foreach (array('idlm-settings-backup-notice', 'data-idlm-toast-autoclose="3000"', 'is-dismissible') as $contract) {
        assert_contains($contract, $backup);
    }
    foreach (array('enhanceSettingsNotices', 'idlm-settings-toast-stack', "'.notice-success, .notice-info'", "'.notice-error, .notice-warning'", 'requiresAction', 'hasExplicitAutoClose', "'mouseenter focusin'", "'mouseleave focusout'", 'stack deliberately is not a live region', 'Dismiss notification', 'notice-dismiss', "createElementNS('http://www.w3.org/2000/svg', 'svg')", "createElementNS('http://www.w3.org/2000/svg', 'path')", "viewBox', '0 0 16 16'", '_idlmToastDismissHandler', "addEventListener('click', button._idlmToastDismissHandler, true)", 'event.stopImmediatePropagation()', 'window.clearTimeout(timer)') as $contract) {
        assert_contains($contract, $script);
    }
    assert_false(false !== strpos($script, 'notice.focus();'), 'Saved notices should not receive focus or duplicate their status announcement.');
    foreach (array('.idlm-settings-toast-stack', '.notice.idlm-settings-toast', 'pointer-events: none', 'left: 50%', 'width: fit-content', 'max-width: min(520px, calc(100vw - (var(--idlm-space-4) * 2)))', 'background: var(--idlm-success)', 'font-family: Inter, ui-sans-serif, system-ui', 'font-size: 13px', 'font-weight: 400', 'content: none', 'display: none', '.idlm-settings-toast__dismiss-icon', 'stroke-width: 1.75', '@media (prefers-reduced-motion: reduce)', 'max-height: calc(100dvh - 128px)') as $contract) {
        assert_contains($contract, $css);
    }
    assert_false(false !== strpos($css, 'border-top: 1.5px solid currentColor'), 'Settings toast close control must not fall back to a CSS-drawn glyph.');
});

idlm_test('CRM settings pipeline and lead-capture mappings retain accessible progressive disclosure contracts', static function(): void {
    $root = dirname(__DIR__);
    $general = (string) file_get_contents($root . '/templates/admin/settings/general.php');
    $editor = (string) file_get_contents($root . '/templates/admin/settings/token-editor.php');
    $webhooks = (string) file_get_contents($root . '/templates/admin/settings/webhooks.php');
    $shell = (string) file_get_contents($root . '/templates/admin/settings/shell.php');
    $css = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $script = (string) file_get_contents($root . '/assets/js/settings.js');

    foreach (array('Pipeline stages', "'pipeline' => true", "'stage_totals' => \$idlm_kanban_stage_totals", 'Lead fields and options', '<details class="idlm-settings-disclosure"', 'Sales activity', 'Outcome tracking', 'Assignment and routing', 'Project requirements') as $contract) {
        assert_contains($contract, $general);
    }
    foreach (array('data-idlm-pipeline-editor', 'data-idlm-stage-total', 'kanban_stage_totals[', 'idlm-pipeline-stage-editor__position') as $contract) {
        assert_contains($contract, $editor . $script);
    }
    foreach (array('role="tablist" aria-label="Lead capture source mappings"', 'role="tab"', 'aria-selected=', 'aria-controls=', 'role="tabpanel"', 'aria-labelledby=', 'Lead identity', 'Project details', 'Attribution', 'Advanced metadata', 'Email or phone required', 'Contact mapping present', 'Send a test lead to verify delivery', 'Map an email or phone field', '<details class="idlm-mapping-group" open>') as $contract) {
        assert_contains($contract, $webhooks);
    }
    assert_false(false !== strpos($webhooks, 'X-IDLM-API-Key: <?php echo'), 'Mapping details must never render the webhook API key in plaintext.');
    foreach (array("event.key === 'ArrowDown'", "event.key === 'ArrowUp'", "$('.idlm-mapping-tabs')", "prop('hidden', true)") as $contract) {
        assert_contains($contract, $shell);
    }
    foreach (array('.idlm-pipeline-stage-editor__item', '.idlm-settings-disclosure', '.idlm-mapping-group', '.idlm-mapping-status', '@media (max-width: 782px)') as $contract) {
        assert_contains($contract, $css);
    }
});

idlm_test('white-label settings remain accessible, responsive, and isolated from theme styles', static function(): void {
    $root = dirname(__DIR__);
    $template = (string) file_get_contents($root . '/templates/admin/settings/white-label.php');
    $shell = (string) file_get_contents($root . '/templates/admin/settings/shell.php');
    $settings = (string) file_get_contents($root . '/includes/class-settings.php');
    $controller = (string) file_get_contents($root . '/includes/class-admin-settings-controller.php');
    $script = (string) file_get_contents($root . '/assets/js/settings.js');
    $css = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $workspace = (string) file_get_contents($root . '/templates/workspace.php');
    $access = (string) file_get_contents($root . '/templates/access-restricted.php');

    foreach (array('settings-white-label', 'data-idlm-white-label', 'data-idlm-brand-preview', 'data-idlm-brand-asset-select', 'data-idlm-brand-colour', 'data-idlm-brand-reset', 'role="alert"') as $contract) {
        assert_contains($contract, $template . $shell);
    }
    foreach (array('WHITE_LABEL_OPTION', 'validate_white_label', 'validate_brand_asset', 'contrast_ratio', 'render_frontend_favicon') as $contract) {
        assert_contains($contract, $settings);
    }
    assert_contains('IDLM_Settings::validate_white_label', $controller);
    assert_contains('current_user_can(\'manage_options\')', $controller);
    assert_contains("check_admin_referer('idlm_settings_save_action')", $controller);
    foreach (array('window.wp.media', 'contrastRatio', 'validateWhiteLabel', 'updateBrandPreview', 'window.IDLMDialog.confirm') as $contract) {
        assert_contains($contract, $script);
    }
    foreach (array('.idlm-white-label-layout', '.idlm-white-label-preview', '.idlm-brand-asset-control', '.idlm-workspace.idlm-workspace', '@media (max-width: 1024px)', '@media (max-width: 782px)', '@media (max-width: 480px)') as $contract) {
        assert_contains($contract, $css);
    }
    assert_contains('IDLM_Settings::white_label_style', $workspace);
    assert_contains('IDLM_Settings::white_label_style', $access);
    assert_false(false !== strpos($template, 'theme.json'));
    assert_false(false !== strpos($css, '--wp--preset-'));
});

idlm_test('wp-admin stays a compact utility page while the frontend owns CRM navigation', static function(): void {
    $root = dirname(__DIR__);
    $admin = (string) file_get_contents($root . '/includes/class-admin.php');
    $assets = (string) file_get_contents($root . '/includes/class-admin-assets.php');
    $css = (string) file_get_contents($root . '/assets/css/admin-style.css');

    foreach (array('redirect_legacy_admin_page', 'IDLM_Admin_Shortcodes::workspace_url()', 'render_admin_utility_page', '[idlm_workspace]', '[idlm_quotations]') as $contract) {
        assert_contains($contract, $admin);
    }
    assert_contains('.idlm-wrap.idlm-wrap,.idlm-workspace.idlm-workspace', $assets);
    assert_false(false !== strpos($admin, 'idlm-admin-sidebar'));
    assert_false(false !== strpos($css, 'idlm-admin-sidebar'));
});

idlm_test('frontend workspace uses the shared strip, white-label mark, and independent mobile drawer', static function(): void {
    $root = dirname(__DIR__);
    $template = (string) file_get_contents($root . '/templates/workspace.php');
    $css = (string) file_get_contents($root . '/assets/css/workspace.css');
    $script = (string) file_get_contents($root . '/assets/js/workspace.js');
    $assets = (string) file_get_contents($root . '/includes/class-admin-assets.php');

    foreach (array('idlm-workspace-mobile-toggle', 'idlm-workspace-backdrop', 'idlm-workspace-brand-mark', '$idlm_brand_name', '$idlm_workspace_title', 'idlm-workspace-account-trigger', 'idlm-workspace-account-menu', '>Profile</span>', '>Sign out</span>', 'wp_logout_url($idlm_base_url)') as $contract) {
        assert_contains($contract, $template);
    }
    foreach (array('--idlm-workspace-sidebar:248px', '--idlm-workspace-sidebar:64px', 'min-height:64px', 'border-radius:0', 'position:fixed', 'transform:translateX(-100%)', 'idlm-workspace .idlm-pending-calls-pill') as $contract) {
        assert_contains($contract, $css);
    }
    foreach (array('is-mobile-open', 'compactQuery.matches', 'window.localStorage.setItem(storageKey', 'closeMobile(true)', 'addListener(onViewportChange)', 'setAccountMenu', 'accountMenu.hidden') as $contract) {
        assert_contains($contract, $script);
    }
    assert_contains('private static function asset_version', $assets);
    assert_contains("self::asset_version('css/workspace.css')", $assets);
    assert_contains("self::asset_version('js/workspace.js')", $assets);
});

idlm_test('workspace controls retain native semantics and one shared control contract', static function(): void {
    $root = dirname(__DIR__);
    $leads = (string) file_get_contents($root . '/templates/admin/leads.php');
    $script = (string) file_get_contents($root . '/assets/js/admin-app.js');
    $css = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $workspace_css = (string) file_get_contents($root . '/assets/css/workspace.css');

    assert_contains('<button type="button" class="idlm-table-sort-button"', $leads);
    assert_contains('class="idlm-close" onclick="closeLeadModal()" aria-label=', $leads);
    assert_false((bool) preg_match('/<th[^>]+role="button"/i', $leads), 'Sortable table headers must contain native buttons, not simulate buttons.');
    assert_false(false !== strpos($leads, 'href="#" class="idlm-btn'), 'Action controls must not use placeholder links.');
    assert_contains(".on('click', '.idlm-table-sort-button'", $script);
    assert_contains('.idlm-table .idlm-table-sort-button', $css);
    assert_contains('.idlm-text-link,', $css);
    assert_contains('.idlm-action-link', $css);
    assert_false(false !== strpos(idlm_ui_php_sources($root . '/templates'), 'idlm-link-button'), 'Links and in-place actions must not share one mixed component class.');
    assert_same(1, substr_count($css, '/* Shared actionable dialog and non-blocking status feedback. */'), 'Dialog/toast contract must have one authoritative declaration.');
    assert_false(false !== strpos($workspace_css, '.idlm-workspace-account-link'), 'Unused workspace account-link styling must not return.');
});

idlm_test('Phase 7 users and logs expose responsive filters, semantic tables, and lazy accessible payloads', static function(): void {
    $root = dirname(__DIR__);
    $users = (string) file_get_contents($root . '/templates/admin/users.php');
    $capi = (string) file_get_contents($root . '/templates/admin/logs/capi.php');
    $audience = (string) file_get_contents($root . '/templates/admin/logs/audience.php');
    $dialog = (string) file_get_contents($root . '/templates/admin/logs/payload-dialog.php');
    $script = (string) file_get_contents($root . '/assets/js/users-logs.js');
    $renderer = (string) file_get_contents($root . '/includes/class-admin-logs-renderer.php');
    $admin = (string) file_get_contents($root . '/includes/class-admin.php');
    $assets = (string) file_get_contents($root . '/includes/class-admin-assets.php');
    $css = (string) file_get_contents($root . '/assets/css/admin-style.css');

    foreach (array('idlm-users-role-filter', 'idlm-users-access-filter', 'data-idlm-filter-field', 'autocomplete="given-name"', 'autocomplete="email"', 'autocomplete="tel"', 'data-idlm-pending-form', 'idlm-user-safeguard') as $contract) {
        assert_contains($contract, $users);
    }
    foreach (array('data-idlm-filter-open', 'data-idlm-filter-drawer', 'data-idlm-filter-backdrop', 'aria-modal="true"', 'data-idlm-filter-close') as $contract) {
        assert_contains($contract, $users . $capi . $audience);
    }
    foreach (array('<caption', 'data-idlm-log-inspect', 'log_date_from', 'log_date_to', 'data-label=', 'Inspect payload') as $contract) {
        assert_contains($contract, $capi . $audience);
    }
    assert_false((bool) preg_match('/\sstyle\s*=|<script\b|onclick\s*=/i', $capi . $audience), 'Log templates must use shared assets without inline presentation or interaction.');
    foreach (array('role="dialog"', 'aria-modal="true"', 'data-idlm-dialog-close', 'data-idlm-log-copy', 'tabindex="0"') as $contract) {
        assert_contains($contract, $dialog);
    }
    foreach (array('idlm_get_capi_log_payload', 'idlm_get_audience_log_payload', 'navigator.clipboard', 'window.IDLMDialog.open', 'updateCapiSelection', 'openFilterDrawer', 'closeFilterDrawer', 'trapFilterDrawerFocus', "setAttribute('aria-busy'") as $contract) {
        assert_contains($contract, $script);
    }
    $admin_app = (string) file_get_contents($root . '/assets/js/admin-app.js');
    assert_contains('var $panel = $(\'#idlm-lead-filters\');', $admin_app);
    assert_contains('var $count = $(\'#idlm-open-filters .idlm-filter-count\');', $admin_app);
    assert_false(false !== strpos($script, 'alert('), 'Phase 7 log feedback must remain inline.');
    foreach (array('request_filters', 'append_common_where', 'format_payload', 'SELECT c.id, c.lead_id', 'SELECT payload, response') as $contract) {
        assert_contains($contract, $renderer);
    }
    assert_contains("wp_ajax_idlm_get_capi_log_payload", $admin);
    assert_contains("wp_register_script('idlm-users-logs'", $assets);
    foreach (array('.idlm-log-filters', '.idlm-log-table tbody td::before', '.idlm-log-dialog-panel', '.idlm-users-page .idlm-users-filter') as $contract) {
        assert_contains($contract, $css);
    }
});

idlm_test('frontend workspace button components outrank Elementor kit defaults without important overrides', static function(): void {
    $admin_css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin-style.css');

    assert_contains('.idlm-workspace .idlm-metric,', $admin_css);
    assert_contains('.idlm-workspace .idlm-lead-row-open,', $admin_css);
    assert_false((bool) preg_match('/\.idlm-workspace\s+\.idlm-(?:metric|lead-row-open)[^{]*\{[^}]*!important/is', $admin_css));
});

idlm_test('compact CRM typography uses a consistent scalable hierarchy', static function(): void {
    $root = dirname(__DIR__);
    $admin_css = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $dashboard_css = (string) file_get_contents($root . '/assets/css/dashboard.css');
    $master = (string) file_get_contents($root . '/design-system/interior-design-lead-manager/MASTER.md');

    foreach (array('--idlm-type-page: 1.375rem', '--idlm-type-section: 1rem', '--idlm-type-body: 0.8125rem', '--idlm-type-compact: 0.75rem', '--idlm-type-label: 0.625rem') as $token) {
        assert_contains($token, $admin_css);
    }
    assert_contains('.idlm-wrap .idlm-table td', $admin_css);
    assert_contains('font-size: var(--idlm-type-page)', $admin_css);
    assert_contains('| Page title | `22px / 28px` |', $master);
    assert_contains('font-size: 1rem;', $admin_css);
});

idlm_test('CRM buttons use a shared radius and readable semantic contrast', static function(): void {
    $root = dirname(__DIR__);
    $admin_css = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $workspace_css = (string) file_get_contents($root . '/assets/css/workspace.css');
    $quotes_css = (string) file_get_contents($root . '/assets/css/quotations.css');
    $overlays = (string) file_get_contents($root . '/templates/admin/common-overlays.php');

    assert_contains('--idlm-button-radius: 8px', $admin_css);
    assert_contains('border-radius: var(--idlm-button-radius)', $admin_css);
    assert_contains('color: var(--idlm-action-contrast)', $admin_css);
    assert_contains('border-radius:var(--idlm-button-radius,8px)', $workspace_css . $quotes_css);
    assert_contains('background: var(--idlm-action)', $admin_css);
    assert_contains('color-mix(in srgb, var(--idlm-action-hover) 24%, transparent)', $admin_css);
    assert_contains('color-mix(in srgb, var(--idlm-brand-gold) 35%, var(--idlm-border))', $admin_css);
    assert_contains('<button type="button" class="idlm-close" id="idlm-global-tasks-sidebar-close" aria-label="Close active tasks"', $overlays);
});

idlm_test('final UI acceptance uses shared dialogs and non-blocking feedback instead of native browser prompts', static function(): void {
    $root = dirname(__DIR__);
    $sources = (string) file_get_contents($root . '/assets/js/dialog.js')
        . (string) file_get_contents($root . '/assets/js/admin-app.js')
        . (string) file_get_contents($root . '/assets/js/quotations.js')
        . (string) file_get_contents($root . '/templates/admin/settings/shell.php')
        . (string) file_get_contents($root . '/templates/admin/settings/mcp.php')
        . (string) file_get_contents($root . '/templates/admin/settings/webhooks.php');

    assert_contains('IDLMDialog.confirm', $sources);
    assert_contains('IDLMDialog.prompt', $sources);
    assert_contains('IDLMDialog.notify', $sources);
    assert_contains("inputType: 'datetime-local'", $sources);
    assert_contains('data-idlm-confirm-submit', $sources);
    assert_false((bool) preg_match('/(?<!IDLMDialog\\.)\\b(?:window\\.)?(?:alert|confirm|prompt)\\s*\\(/', $sources), 'Runtime workflows must not use native browser feedback dialogs.');
});

idlm_test('pre-sales outreach stays recoverable while the activity request is pending', static function(): void {
    $script = (string) file_get_contents(dirname(__DIR__) . '/assets/js/admin-app.js');
    $css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin-style.css');
    $dialog = (string) file_get_contents(dirname(__DIR__) . '/assets/js/dialog.js');
    $assets = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-assets.php');

    assert_contains("timeout: 20000", $script);
    assert_contains(".text('Saving…')", $script);
    assert_contains("closePresalesActivityModal();", $script);
    assert_contains("Saving took too long. Check your connection and try again.", $script);
    assert_contains("#idlm-presales-activity-modal .idlm-presales-activity-error", $script);
    assert_false(false !== strpos($script, "        closePresalesActivityModal();\n    });\n\n    function submitPresalesTouch"), 'The outreach dialog must not close before the request settles.');
    assert_contains('--idlm-layer-dialog: 100150', $css);
    assert_contains('z-index: var(--idlm-layer-dialog)', $css);
    foreach (array('id="idlm-presales-activity-form" novalidate', 'aria-errormessage="idlm-presales-call-status-error"', "prop('required', isCall)", "attr('aria-required', isCall ? 'true' : 'false')", "showInlineFieldError('#idlm-presales-call-status', 'Select a call outcome.')") as $contract) {
        assert_contains($contract, $script);
    }
    assert_contains('field.dataset.idlmErrorDescription = errorId', $dialog);
    assert_contains("delete field.dataset.idlmErrorDescription", $dialog);
    assert_contains('.idlm-dialog.idlm-dialog', $assets);
    assert_contains('self::add_white_label_style();', $assets);
    assert_contains('background: var(--idlm-danger-soft);', $css);
    assert_contains('color: var(--idlm-danger);', $css);
});

idlm_test('lead drawer status summary separates operational state from quieter context without card clutter', static function(): void {
    $root = dirname(__DIR__);
    $admin_css = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $lead_reads = (string) file_get_contents($root . '/includes/class-admin-lead-reads.php');

    foreach (array('.idlm-drawer-status-primary', 'grid-template-columns: repeat(4, minmax(0, 1fr))', '.idlm-drawer-status-context', 'white-space: nowrap', 'text-overflow: ellipsis') as $contract) {
        assert_contains($contract, $admin_css);
    }
    assert_false((bool) preg_match('/\.idlm-drawer-status-item\s*\{[^}]*(?:background|border-radius|box-shadow)\s*:/is', $admin_css));
    assert_false((bool) preg_match('/\.idlm-drawer-status-grid\s*\{[^}]*overflow-x\s*:\s*auto/is', $admin_css));
    foreach (array('role="group" aria-label="Lead status summary"', 'idlm-drawer-status-primary', 'idlm-drawer-status-context', 'Call status', 'Assigned rep', 'Disqualification:', 'Lost reason:', 'Created:') as $contract) {
        assert_contains($contract, $lead_reads);
    }
    assert_false(false !== strpos($lead_reads, 'aria-label="Lead status summary" tabindex="0"'));
});

idlm_test('lead routing popup distills safeguards with conditional controls while retaining routing contracts', static function(): void {
    $root = dirname(__DIR__);
    $leads = (string) file_get_contents($root . '/templates/admin/leads.php');
    $routing_settings = (string) file_get_contents($root . '/templates/admin/settings/routing.php');
    $script = (string) file_get_contents($root . '/assets/js/admin-app.js');
    $dialog = (string) file_get_contents($root . '/assets/js/dialog.js');
    $css = (string) file_get_contents($root . '/assets/css/admin-style.css');
    $ajax = (string) file_get_contents($root . '/includes/class-admin-ajax.php');

    foreach (array('role="dialog"', 'aria-modal="true"', 'data-idlm-dialog', 'idlm-routing-modal-title', 'idlm-routing-modal-description', 'aria-label="Open lead routing settings"', 'for="idlm-routing-mode-select"', 'idlm-routing-settings-error', 'idlm-routing-fallback-confirm', 'role="status" aria-live="polite"', 'idlm-routing-current-summary', 'idlm-routing-advanced-safeguards', 'fallback_assignment_controls_active', 'qualified_fallback_controls_active') as $contract) {
        assert_contains($contract, $leads);
    }
    assert_false(false !== strpos($leads, 'idlm-routing-step'), 'Routing sections must use named hierarchy instead of misleading numbered badges.');
    foreach (array('window.IDLMDialog.open(modal', 'window.IDLMDialog.close(modal', 'idlm:dialogbeforeclose', 'idlm:dialogclose', "panel.prop('hidden', false)", "toggleClass('is-inactive', !enabled)", "panel.find('select').prop('disabled', !enabled)", 'toggleRoutingModalConditionalPanel', 'updateRoutingModalState', 'New leads follow the source assignments below.', 'Discard routing changes?', 'runRoutingFallback', 'hideRoutingFallbackConfirmation', 'removeRoutingFallbackEscapeHandler', 'if (!$confirm.length)', "document.addEventListener('keydown', idlmRoutingFallbackEscapeHandler, true)", "document.removeEventListener('keydown', idlmRoutingFallbackEscapeHandler, true)", "'Escape' !== event.key || \$confirm.prop('hidden')", "'escape' === closeReason", "event.originalEvent && event.originalEvent.detail", "find('.idlm-routing-fallback-confirm').filter(function()", 'event.stopImmediatePropagation()', "text('Run fallback now')", "attr('aria-busy', 'true')") as $contract) {
        assert_contains($contract, $script);
    }
    assert_contains("new CustomEvent('idlm:dialogbeforeclose'", $dialog);
    assert_contains("if ('source_based' === \$assignment_mode)", $ajax);
    assert_contains("if (isset(\$_POST['fallback_assignment_controls_active']))", $ajax);
    assert_contains("if (isset(\$_POST['qualified_fallback_controls_active']))", $ajax);
    assert_contains('grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr)', $css);
    assert_contains('.idlm-routing-advanced', $css);
    assert_contains('.idlm-routing-conditional-controls', $css);
    assert_contains('.idlm-routing-source-section[hidden]', $css);
    assert_contains('.idlm-routing-conditional-controls[hidden]', $css);
    assert_contains('@media (max-width: 760px)', $css);
    assert_contains('@media (max-width: 480px)', $css);
    assert_true((bool) preg_match('/@media\s*\(max-width:\s*760px\)\s*\{.*?#idlm-routing-modal\s+\.idlm-routing-primary-grid\s*\{\s*grid-template-columns:\s*minmax\(0,\s*1fr\)/s', $css));
    $routing_grid_index = strpos($css, '#idlm-routing-modal .idlm-routing-primary-grid');
    assert_true(false !== strpos($css, '@media (max-width: 480px)', $routing_grid_index), 'The 480px phone breakpoint must inherit the compact one-column routing grid.');
    assert_contains('Run fallback now', $leads);
    assert_contains('Run fallback now', $routing_settings);
});

idlm_test('CRM design layer stays fully gated, token-led and reversible', static function(): void {
    $root = dirname(__DIR__);
    $assets = (string) file_get_contents($root . '/includes/class-admin-assets.php');
    $admin = (string) file_get_contents($root . '/includes/class-admin.php');
    $files = glob($root . '/assets/css/crm-design*.css') ?: array();
    assert_true(8 === count($files), 'The design layer must stay in its base and seven area stylesheets.');
    $rule_count = 0;

    foreach ($files as $file) {
        $name = basename($file);
        $bare = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($file));

        assert_false(false !== stripos($bare, '!important'), 'STD-CSS-3: !important in ' . $name);
        assert_false((bool) preg_match('/transition\s*:\s*all\b/i', $bare), 'transition: all is prohibited in ' . $name);
        assert_false((bool) preg_match('/--idlm-(?:action|action-hover|action-soft|brand-gold|sidebar|focus-ring)\s*:/', $bare), 'STD-CSS-4: white-label token overridden in ' . $name);

        preg_match_all('/([^{}]+)\{/', $bare, $preludes);
        foreach ($preludes[1] as $prelude) {
            $prelude = trim($prelude);
            if ('@' === substr($prelude, 0, 1)) {
                assert_true((bool) preg_match('/^@media \((?:(?:max-width: (?:480|782|1024|1280)px)|(?:min-width: (?:481|783|1025|1281)px)|(?:prefers-reduced-motion: reduce))\)$/', $prelude), 'STD-CSS-5: non-canonical media query in ' . $name . ': ' . $prelude);
                continue;
            }
            ++$rule_count;
            foreach (preg_split('/,(?![^(]*\))/', $prelude) as $selector) {
                assert_true(0 === strpos(trim($selector), 'body.idlm-crm-design'), 'STD-CSS-1: ungated selector in ' . $name . ': ' . trim($selector));
            }
        }

        $colour_scope = 'crm-design.css' === $name ? substr($bare, (int) strpos($bare, '--idlm-muted')) : $bare;
        assert_false((bool) preg_match('/#[0-9a-f]{3,8}\b/i', (string) preg_replace('/#(?:idlm|settings|tab)[a-z0-9_-]*/i', '', $colour_scope)), 'STD-CSS-2: hard-coded colour in ' . $name);

        preg_match_all('/font-size:\s*([0-9.]+)rem/', $bare, $sizes);
        foreach ($sizes[1] as $size) {
            assert_true((float) $size >= 0.6875, 'STD-TYPE-1: text below 11px in ' . $name);
        }
    }
    assert_true($rule_count > 20, 'The design layer rules must be parseable by this contract.');

    // One design: the switch, preview parameter and rollback constant are retired.
    foreach (array("'idlm-crm-design'", 'DESIGN_AREAS') as $contract) {
        assert_contains($contract, $assets);
    }
    foreach (array('IDLM_DESIGN_LAYER', 'idlm_design_layer', 'idlm_set_design_layer', "\$_GET['idlm_design']", 'render_design_layer_control') as $retired) {
        assert_true(false === strpos($assets . $admin, $retired), 'Retired design switch is back: ' . $retired);
    }
});

idlm_test('design standard stays the entry point for agents and status options stay free of emoji', static function(): void {
    $root = dirname(__DIR__);
    $standard = (string) file_get_contents($root . '/design-system/interior-design-lead-manager/STANDARD.md');
    foreach (array('STD-CSS-1', 'STD-CRM-1', 'STD-TYPE-1', 'STD-CSS-9', 'design-baseline-worktree') as $contract) {
        assert_contains($contract, $standard);
    }
    assert_contains('design-system/interior-design-lead-manager/STANDARD.md', (string) file_get_contents($root . '/AGENTS.md'));
    foreach (array('class-admin-collection-reads.php', 'class-admin-lead-reads.php') as $file) {
        assert_false((bool) preg_match('/>\s*(?:\x{2705}|\x{274C})/u', (string) file_get_contents($root . '/includes/' . $file)), 'STD-CRM-4: status text must not use emoji.');
    }
});

idlm_test('Users and permissions views stay presentation only and keep every saved field', static function(): void {
    $users = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/users.php');
    assert_false((bool) preg_match('/\b(?:update_option|add_option|delete_option|add_cap|remove_cap|add_role|remove_role|update_user_meta)\s*\(/', $users), 'The users template must not write settings.');
    $roles = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/users-roles.php');
    // Lead visibility and permissions are saved per role from the Roles tab.
    foreach (array('name="action" value="idlm_update_role_access"', 'name="access_role"', 'name="role_scope"', 'name="permissions[', "wp_nonce_field('idlm_update_role_access')") as $contract) {
        assert_contains($contract, $roles);
    }
    foreach (array('name="action" value="idlm_create_user"', 'name="action" value="idlm_update_user"', 'name="crm_access_disabled"') as $contract) {
        assert_contains($contract, $users);
    }
});

idlm_test('design layer shows page notices as dismissible bottom-right toasts and keeps standing context inline', static function(): void {
    $root = dirname(__DIR__);
    $dialog = (string) file_get_contents($root . '/assets/js/dialog.js');
    $css = (string) file_get_contents($root . '/assets/css/crm-design.css');
    foreach (array('promoteNotices', '[data-idlm-notice-inline]', "a, button:not(.notice-dismiss), input, select, textarea", 'MutationObserver', 'idlm-toast__dismiss', 'idlm-toast-stack') as $contract) {
        assert_contains($contract, $dialog);
    }
    assert_false(false !== strpos($css, 'left: calc(248px + var(--idlm-space-4))'), 'Toasts must stay in the bottom right corner on desktop.');
    foreach (array('includes/class-quotation-renderer.php', 'includes/class-design-handoff-controller.php', 'templates/admin/settings/lead-connector.php', 'templates/admin/settings/white-label.php', 'templates/admin/settings/mcp.php', 'assets/js/admin-app.js', 'assets/js/users-logs.js') as $file) {
        assert_contains('data-idlm-notice-inline', (string) file_get_contents($root . '/' . $file), $file);
    }
    // One design: no script branches on the retired design switch.
    foreach (glob($root . '/assets/js/*.js') as $script) {
        assert_true(false === strpos((string) file_get_contents($script), "classList.contains('idlm-crm-design')"), basename($script) . ' still tests for the CRM design.');
    }
});

idlm_test('design layer sidebar groups work and admin, shows needs-action counts and the signed-in person', static function(): void {
    $root = dirname(__DIR__);
    $template = (string) file_get_contents($root . '/templates/workspace.php');
    $shortcodes = (string) file_get_contents($root . '/includes/class-admin-shortcodes.php');
    $script = (string) file_get_contents($root . '/assets/js/workspace.js');
    $css = (string) file_get_contents($root . '/assets/css/crm-design.css');
    foreach (array('idlm-workspace-nav-heading', '>Work</p>', '>Admin</p>', 'idlm-workspace-nav-count', 'data-idlm-tip', 'idlm-workspace-account-name') as $contract) {
        assert_contains($contract, $template);
    }
    // Search and Add lead stay on the Leads page, not in the sidebar.
    assert_false(false !== strpos($template, 'data-idlm-workspace-search'), 'The sidebar has no search field.');
    assert_false(false !== strpos($template, 'data-idlm-add-lead'), 'The sidebar has no Add lead entry.');
    assert_contains("array('Ad conversions', 'dashicons-shield-alt'", $shortcodes);
    assert_contains("array('Ad audiences', 'dashicons-update'", $shortcodes);
    assert_contains('private static function nav_counts($sections)', $shortcodes);
    assert_contains("handoff_status = 'awaiting_manager'", $shortcodes);
    assert_contains('IDLM_Lead_Access_Policy::scope_sql(', $shortcodes);
    foreach (array('idlm-nav-tip', 'focusin') as $contract) {
        assert_contains($contract, $script);
    }
    assert_false(false !== strpos($script, "item.setAttribute('title', label.textContent)"), 'The rail must not fall back to native title tooltips.');
    assert_contains('.idlm-nav-tip.is-visible', $css);
});

idlm_test('Qualified handoffs card never repeats its count in the overdue hint', static function(): void {
    $root = dirname(__DIR__);
    $template = (string) file_get_contents($root . '/templates/admin/leads.php');
    $script = (string) file_get_contents($root . '/assets/js/admin-app.js');
    assert_contains('$idlm_overdue_handoff_count >= $idlm_qualified_handoff_count ? \'all overdue\'', $template);
    assert_contains("overdueHandoffs >= qualifiedHandoffs ? 'all overdue'", $script);
});
