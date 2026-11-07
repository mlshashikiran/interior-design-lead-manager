<?php
/**
 * White-label presentation contracts: derived brand tokens keep WCAG AA on the
 * sidebar and on accent fills, and brand values reach every plugin surface.
 * See docs/WHITE-LABEL-REVIEW.md. Builder: Shashi.
 */

declare(strict_types=1);

function idlm_white_label_test_ratio(string $a, string $b): float {
    $method = new ReflectionMethod('IDLM_Settings', 'contrast_ratio');
    $method->setAccessible(true);
    return (float) $method->invoke(null, $a, $b);
}

function idlm_white_label_test_token(string $tokens, string $name): string {
    assert_true((bool) preg_match('/' . preg_quote($name, '/') . ':(#[0-9A-F]{6});/', $tokens, $match), 'Missing token ' . $name);
    return $match[1];
}

idlm_test('white-label derived tokens keep sidebar accents at 3:1 and accent fill text at 4.5:1', static function(): void {
    $defaults = IDLM_Settings::white_label_style(IDLM_Settings::white_label_defaults());
    // The default brand keeps its gold on the sidebar; the letter on gold becomes the sidebar colour (white is 2.6:1).
    assert_same('#C99644', idlm_white_label_test_token($defaults, '--idlm-sidebar-accent'));
    assert_same('#111A24', idlm_white_label_test_token($defaults, '--idlm-on-accent'));

    $dark = IDLM_Settings::white_label_style(array('primary_color' => '#1E1B4B', 'accent_color' => '#3B2F80', 'sidebar_color' => '#0B0A1F'));
    assert_same('#FFFFFF', idlm_white_label_test_token($dark, '--idlm-sidebar-accent'));
    assert_same('#FFFFFF', idlm_white_label_test_token($dark, '--idlm-on-accent'));

    $sidebars = array('#111A24', '#0B0A1F', '#1F2937', '#264653', '#3B2F80', '#5C1A15', '#737373', '#0E5A43');
    $accents = array('#C99644', '#F4D35E', '#E9C46A', '#3B2F80', '#FFFFFF', '#000000', '#888888', '#B3261E', '#2A9D8F', '#F8FAFC');
    foreach ($sidebars as $sidebar) {
        // #737373 (4.74:1) is no longer accepted as a new value but may already be saved, so it must still render safely.
        assert_true(idlm_white_label_test_ratio($sidebar, '#FFFFFF') >= 4.5, 'Fixture sidebar must pass the saved-value rule: ' . $sidebar);
        foreach ($accents as $accent) {
            $tokens = IDLM_Settings::white_label_style(array('primary_color' => '#7A5017', 'accent_color' => $accent, 'sidebar_color' => $sidebar));
            $sidebar_accent = idlm_white_label_test_token($tokens, '--idlm-sidebar-accent');
            $on_accent = idlm_white_label_test_token($tokens, '--idlm-on-accent');
            $label = $accent . ' on ' . $sidebar;
            assert_true(idlm_white_label_test_ratio($sidebar_accent, $sidebar) >= 3, 'Sidebar accent below 3:1 for ' . $label);
            assert_true(idlm_white_label_test_ratio($on_accent, $accent) >= 4.5, 'Accent fill text below 4.5:1 for ' . $label);
        }
    }
    // Stored values are untouched: the derived tokens only exist in the emitted style.
    assert_false(array_key_exists('sidebar_accent', IDLM_Settings::white_label_defaults()));
    assert_same(array(), IDLM_Settings::validate_white_label(array_merge(IDLM_Settings::white_label_defaults(), array('accent_color' => '#3B2F80')))['errors']);
});

idlm_test('white-label colours reach the workspace shell through tokens that resolve per workspace', static function(): void {
    $root = dirname(__DIR__);
    $workspace_css = (string) file_get_contents($root . '/assets/css/workspace.css');
    $layer = (string) file_get_contents($root . '/assets/css/crm-design.css');
    // --idlm-dark is resolved once at :root, so it never sees the per-workspace --idlm-sidebar.
    assert_false(false !== strpos($workspace_css, 'var(--idlm-dark)'), 'The sidebar must paint the configured sidebar colour.');
    assert_contains('background:var(--idlm-sidebar)', $workspace_css);
    // The workspace-wide focus rule out-specifies the sidebar's own focus colour; this restores it.
    assert_contains('body.idlm-crm-design .idlm-workspace .idlm-workspace-sidebar :is(a, button, input, select, textarea, summary):focus-visible {' . "\n" . '    outline-color: var(--idlm-sidebar-accent, var(--idlm-brand-gold));', $layer);
    assert_contains("body.idlm-crm-design .idlm-workspace-brand-mark {\n    color: var(--idlm-on-accent, var(--idlm-action-contrast));", $layer);
    assert_false((bool) preg_match('/color:\s*var\(--idlm-brand-gold\)/', $layer), 'Accent-coloured marks on the sidebar use --idlm-sidebar-accent.');
    assert_false((bool) preg_match('/--idlm-(?:sidebar-accent|on-accent)\s*:/', $layer), 'Derived brand tokens come from IDLM_Settings::white_label_style only.');
});

idlm_test('white-label names replace product copy on the sign-in surfaces', static function(): void {
    $root = dirname(__DIR__);
    $login = (string) file_get_contents($root . '/templates/access-restricted.php');
    $workspace = (string) file_get_contents($root . '/templates/workspace.php');
    $auth = (string) file_get_contents($root . '/includes/class-auth.php');
    assert_false(false !== strpos($login, '>Lead Manager<'), 'The sign-in panel uses the workspace title.');
    assert_false(false !== strpos($login, '"Lead Manager overview"'));
    assert_contains("esc_html(\$idlm_white_label['workspace_title'])", $login);
    assert_contains("mb_substr(\$idlm_brand_mark_source, 0, 1, 'UTF-8')", $workspace);
    assert_contains("return esc_html(\$white_label['brand_name'] . ' ' . \$white_label['workspace_title']);", $auth);
});

idlm_test('the CRM browser icon covers every CRM shortcode, including quotations', static function(): void {
    $source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-settings.php');
    assert_contains("array('idlm_workspace', 'idlm_leads_list', 'idlm_dashboard', 'idlm_kanban', 'idlm_quotations')", $source);
});

idlm_test('new white-label colours must stay readable as link and navigation text; saved colours are kept', static function(): void {
    $base = IDLM_Settings::white_label_defaults();
    // #0E7C86 passes 4.5:1 with white (4.95) but is 4.46:1 on the canvas and 4.33:1 on its soft tint.
    $teal = IDLM_Settings::validate_white_label(array_merge($base, array('primary_color' => '#0E7C86')));
    assert_contains('link text', $teal['errors']['primary_color'] ?? '');
    // #737373 passes 4.5:1 with white (4.74) but 78% white navigation text on it is 3.6:1.
    $grey = IDLM_Settings::validate_white_label(array_merge($base, array('sidebar_color' => '#737373')));
    assert_contains('7:1', $grey['errors']['sidebar_color'] ?? '');
    // Unchanged colours saved under the old rule never block a save of another tab.
    $legacy = array_merge($base, array('primary_color' => '#0E7C86', 'sidebar_color' => '#737373'));
    assert_same(array(), IDLM_Settings::validate_white_label($legacy, $legacy)['errors']);
    // The old minimum still applies to every colour, changed or not.
    $white = array_merge($base, array('primary_color' => '#FFFFFF'));
    assert_true(isset(IDLM_Settings::validate_white_label($white, $white)['errors']['primary_color']));
    // Brands used in the browser check and the defaults pass the stricter rules.
    foreach (array(array('#7A5017', '#111A24'), array('#264653', '#17212B'), array('#1E1B4B', '#0B0A1F'), array('#B3261E', '#0E5A43')) as $pair) {
        assert_same(array(), IDLM_Settings::validate_white_label(array_merge($base, array('primary_color' => $pair[0], 'sidebar_color' => $pair[1])))['errors'], implode(' / ', $pair));
    }
    $controller = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-settings-controller.php');
    assert_contains('IDLM_Settings::validate_white_label($white_label_raw, $saved_white_label)', $controller);
    $js = (string) file_get_contents(dirname(__DIR__) . '/assets/js/settings.js');
    assert_contains("contrastRatio(hex, '#F5F3EF'), contrastRatio(hex, '#EEF2F6'), contrastRatio(hex, lightenHex(hex, 90))", $js);
    assert_contains("return {ratio: white, min: 7,", $js);
    assert_contains("normalizeHex(input.defaultValue) !== normalized", $js);
});

idlm_test('notification subjects and the wp-admin menu use the white-label workspace title', static function(): void {
    $root = dirname(__DIR__);
    $sources = '';
    foreach (array('class-integrations.php', 'class-design-handoffs.php', 'class-handoff-notifications.php', 'class-admin.php') as $file) {
        $sources .= (string) file_get_contents($root . '/includes/' . $file);
    }
    assert_false(false !== strpos($sources, '[Lead Manager]'), 'Subjects use IDLM_Settings::mail_subject_prefix().');
    assert_false(false !== strpos($sources, "'Lead Manager'"), 'The admin menu uses the workspace title.');
    assert_same(6, substr_count($sources, 'IDLM_Settings::mail_subject_prefix()'));
    // The default title keeps the established prefix, so existing mail filters keep matching.
    assert_same('[Lead Manager]', IDLM_Settings::mail_subject_prefix());
    $previous = get_option(IDLM_Settings::WHITE_LABEL_OPTION, null);
    update_option(IDLM_Settings::WHITE_LABEL_OPTION, array_merge(IDLM_Settings::white_label_defaults(), array('workspace_title' => "Desk [beta]")));
    try {
        assert_same('[Desk beta]', IDLM_Settings::mail_subject_prefix());
    } finally {
        null === $previous ? delete_option(IDLM_Settings::WHITE_LABEL_OPTION) : update_option(IDLM_Settings::WHITE_LABEL_OPTION, $previous);
    }
    $login = (string) file_get_contents($root . '/templates/access-restricted.php');
    assert_contains("esc_url('mailto:' . \$idlm_white_label['support_email'])", $login);
});

idlm_test('root token aliases that would freeze white-label colours are gone', static function(): void {
    $css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/admin-style.css');
    foreach (array('--idlm-primary:', '--idlm-primary-hover:', '--idlm-dark:', '--idlm-gold:', '--idlm-gold-dark:', '--idlm-gold-soft:') as $alias) {
        assert_false(false !== strpos($css, $alias), 'Removed alias reintroduced: ' . $alias);
    }
});
