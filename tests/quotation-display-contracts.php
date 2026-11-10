<?php
/** Quotation version labels and the customer link to the lead popup. Builder: Shashi. */

idlm_test('QUO display status names the current version from the lead outcome', static function(): void {
    assert_same('Sales finalised', IDLM_Quotation_Policy::display_status('Draft', true, 'Won'));
    assert_same('Closed: lost', IDLM_Quotation_Policy::display_status('Draft', true, 'Lost'));
    assert_same('Shared', IDLM_Quotation_Policy::display_status('Draft', true, 'Quote Shared'));
    assert_same('Shared', IDLM_Quotation_Policy::display_status('Draft', true, 'Negotiation'));
    assert_same('Draft', IDLM_Quotation_Policy::display_status('Draft', true, 'Qualified'));
    assert_same('Draft', IDLM_Quotation_Policy::display_status('', true, ''));
    // A legacy stored status is kept until the lead closes.
    assert_same('Shared', IDLM_Quotation_Policy::display_status('Shared', true, 'Contacted'));
    assert_same('Sales finalised', IDLM_Quotation_Policy::display_status('Shared', true, 'Won'));
    // Any earlier version is superseded, whatever it stored.
    assert_same('Superseded', IDLM_Quotation_Policy::display_status('Draft', false, 'Won'));
    assert_same('Superseded', IDLM_Quotation_Policy::display_status('Superseded', false, 'Qualified'));
});

idlm_test('QUO list rows and the detail view use the display status; the customer opens the lead popup', static function(): void {
    $root = dirname(__DIR__);
    $repository = file_get_contents($root . '/includes/class-quotation-repository.php');
    assert_contains('l.stage AS lead_stage', $repository);
    assert_contains("\$row->display_status = IDLM_Quotation_Policy::display_status(", $repository);
    $renderer = file_get_contents($root . '/includes/class-quotation-renderer.php');
    assert_contains('self::status_badge($quote->display_status ?? ($quote->status ?? \'\'))', $renderer);
    assert_contains('self::status_badge($status_label)', $renderer);
    assert_contains('data-idlm-open-lead="<?php echo esc_attr($lead_id); ?>"', $renderer);
    // Only a lead this person may already see, and only where the popup exists.
    assert_contains('$can_open_lead = !is_admin() && $lead_id && IDLM_Lead_Access_Policy::can_access($lead_id);', $renderer);
    assert_contains('if ($can_open_lead) { IDLM_Admin::render_common_overlays(); }', $renderer);
    assert_contains("'sales finalised' => 'is-success'", $renderer);
    $quotations = file_get_contents($root . '/assets/js/quotations.js');
    assert_contains('crmStatusBadge(row.display_status || row.status)', $quotations);
    $app = file_get_contents($root . '/assets/js/admin-app.js');
    assert_contains("\$(document).on('click', '[data-idlm-open-lead]'", $app);
    assert_contains("openLeadDrawer(\$(this).data('idlm-open-lead'));", $app);
    $css = file_get_contents($root . '/assets/css/crm-design-quotations.css');
    assert_contains('.idlm-quote-customer-link:focus-visible', $css);
});
