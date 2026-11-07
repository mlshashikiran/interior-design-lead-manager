<?php
/** Pre-sales Leads view source contracts (see docs/PRESALES-LEADS-REVIEW.md). Builder: Shashi. */

idlm_test('PRESALES leads filters return to the resting Action required queue, never silently to All leads', static function(): void {
    $script = (string) file_get_contents(dirname(__DIR__) . '/assets/js/admin-app.js');
    assert_contains('function leadFilterDefault(field)', $script);
    assert_contains('field.options[i].defaultSelected', $script);
    assert_contains("resetLeadFilter('#filter-followup');", $script, 'Clear all must restore the engagement default.');
    assert_contains("resetLeadFilter('#' + id).trigger('change');", $script, 'Removing a chip must restore that filter default.');
    assert_false(false !== strpos($script, "$('#filter-followup').val('')"), 'No path may blank the engagement filter for pre-sales.');
    assert_false(false !== strpos($script, "value === 'action_required');"), 'Filter defaults come from markup, not a hard-coded list.');
    $template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/leads.php');
    assert_contains('<option value="action_required" selected>Action required</option>', $template);
});

idlm_test('PRESALES leads controls match what the server lets pre-sales do', static function(): void {
    $template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/leads.php');
    $ajax = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-ajax.php');
    $allowed = "array('New', 'Contacted')";
    assert_contains("if (\$idlm_is_pre_sales && !in_array(\$idlm_stage, {$allowed}, true)) { continue; }", $template);
    assert_contains("self::current_user_is_pre_sales() && !in_array(\$stage, {$allowed}, true)", $ajax, 'Bulk stage list and server rule must stay in step.');
    // Pre-sales never qualifies or closes in bulk: each decision goes through Lead decision and its log.
    assert_true(1 === preg_match('/<\?php if \(!\$idlm_is_pre_sales\):[^\n]*\?>\s*<option value="bulk_unqualified">/', $template), 'Bulk Mark unqualified must be hidden from pre-sales.');
    assert_true(1 === preg_match("/case 'bulk_unqualified':\s*if \(self::current_user_is_pre_sales\(\)\) \{\s*wp_send_json_error/", $ajax), 'The server must refuse bulk Unqualified from pre-sales.');
    // The audience sync queue is administrator-only, in the menu and on the server.
    assert_true(1 === preg_match('/<\?php if \(\$idlm_can_manage_options\): \?>\s*<option value="bulk_audience_queue">[^<]*<\/option>\s*<option value="bulk_audience_cancel">/', $template), 'Audience queue actions must be shown to administrators only.');
    foreach (array('bulk_audience_cancel', 'bulk_audience_queue') as $op) {
        assert_true(1 === preg_match("/case '{$op}':\s*if \(!current_user_can\('manage_options'\)\)/", $ajax), 'The server must refuse ' . $op . ' from non-administrators.');
    }
    assert_true(1 === preg_match("/if \\(!\\\$idlm_is_pre_sales\\) \\{\\s*\\\$idlm_metric_cards\\[\\] = array\\('Qualified'/", $template), 'The Qualified card always reads 0 in the pre-sales queue and stays hidden there.');
});

idlm_test('PRESALES queue cells fit their columns', static function(): void {
    $css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/crm-design-leads.css');
    assert_true(1 === preg_match('/\.idlm-presales-queue-row \.idlm-response-chip \{[^}]*display: block;[^}]*width: auto;[^}]*max-width: 100%;[^}]*white-space: normal;/', $css), 'Response text must wrap inside its cell, not run into Budget.');
    foreach (array('.idlm-drawer-field-needed {', '.idlm-presales-actions-hint {') as $selector) {
        assert_contains('body.idlm-crm-design #idlm-lead-drawer ' . $selector, $css);
    }
});

idlm_test('Popups keep the page behind them from shifting when they lock scroll', static function(): void {
    $dialog = (string) file_get_contents(dirname(__DIR__) . '/assets/js/dialog.js');
    foreach (array("'idlm-modal-open', 'idlm-filters-open', 'idlm-catalogue-editor-open', 'idlm-bulk-items-open'", "window.innerWidth - document.documentElement.clientWidth", "document.body.style.setProperty('padding-right', scrollbarWidth + 'px')", "document.body.style.removeProperty('padding-right')", "attributeFilter: ['class']") as $contract) {
        assert_contains($contract, $dialog);
    }
    // Every class that locks scroll in CSS must be one the compensation watches.
    $css = '';
    foreach (glob(dirname(__DIR__) . '/assets/css/*.css') as $file) { $css .= (string) file_get_contents($file); }
    preg_match_all('/body\.(idlm-[a-z-]+-open)\s*\{\s*overflow\s*:\s*hidden/', $css, $locks);
    foreach (array_unique($locks[1]) as $lock) {
        assert_contains("'" . $lock . "'", $dialog, 'Scroll lock without shift compensation: ' . $lock);
    }
});

idlm_test('Project type starts empty instead of pretending Apartment was chosen', static function(): void {
    $drawer = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-lead-reads.php');
    assert_contains('<option value="" <?php selected((string) $lead->property_type, \'\'); ?>>Choose a project type</option>', $drawer);
    assert_false(false !== strpos($drawer, 'Not saved yet'), 'The placeholder replaces the "Not saved yet" workaround.');
    $template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/leads.php');
    assert_contains('<option value="">Choose a project type</option>', $template, 'Add lead must not default to Apartment either.');
});

idlm_test('Reopened leads count outreach only from the reopen, everywhere cycles are counted', static function(): void {
    $db = (string) file_get_contents(dirname(__DIR__) . '/includes/class-db.php');
    assert_same(2, substr_count($db, "AND created_at >= %s"), 'Both compliance readers must start the round at the latest reopen.');
    $rows = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-collection-reads.php');
    assert_contains("r.activity_type = 'lead_reopened'", $rows, 'Queue rows must count the same round as the drawer.');
    $ajax = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-ajax.php');
    assert_contains("'Unqualified' === \$lead->lead_quality) {\n                wp_send_json_error(array('message' => 'This lead is already closed.", $ajax, 'Early close must not rewrite an earlier closure.');
});
