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
    $allowed = "array('New', 'Contacted', 'Qualified')";
    assert_contains("if (\$idlm_is_pre_sales && !in_array(\$idlm_stage, {$allowed}, true)) { continue; }", $template);
    assert_contains("self::current_user_is_pre_sales() && !in_array(\$stage, {$allowed}, true)", $ajax, 'Bulk stage list and server rule must stay in step.');
    assert_true(1 === preg_match("/if \\(!\\\$idlm_is_pre_sales\\) \\{\\s*\\\$idlm_metric_cards\\[\\] = array\\('Qualified'/", $template), 'The Qualified card always reads 0 in the pre-sales queue and stays hidden there.');
});

idlm_test('PRESALES queue cells fit their columns', static function(): void {
    $css = (string) file_get_contents(dirname(__DIR__) . '/assets/css/crm-design-leads.css');
    assert_true(1 === preg_match('/\.idlm-presales-queue-row \.idlm-response-chip \{[^}]*display: block;[^}]*width: auto;[^}]*max-width: 100%;[^}]*white-space: normal;/', $css), 'Response text must wrap inside its cell, not run into Budget.');
    foreach (array('.idlm-drawer-field-needed {', '.idlm-presales-actions-hint {') as $selector) {
        assert_contains('body.idlm-crm-design #idlm-lead-drawer ' . $selector, $css);
    }
});
