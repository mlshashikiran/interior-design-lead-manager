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
        assert_true(idlm_white_label_test_ratio($sidebar, '#FFFFFF') >= 4.5, 'Fixture sidebar must pass save validation: ' . $sidebar);
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
