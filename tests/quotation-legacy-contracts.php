<?php
/** A legacy quote may be followed by a new or revised quotation. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

function idlm_legacy_pair(string $status = 'Shared'): array {
    return array(
        (object) array('id' => 1, 'lead_id' => 9, 'quote_number' => 'Q-2024-000001', 'current_revision_id' => 2, 'status' => $status),
        (object) array('id' => 2, 'quote_id' => 1, 'status' => $status, 'revision_number' => 1, 'revision_name' => 'Q-2024-000001-R01', 'created_by' => 0, 'source_revision_id' => 0, 'revision_reason' => IDLM_Quotation_Repository::LEGACY_REVISION_REASON),
    );
}

function idlm_legacy_seed(string $status = 'Shared', int $owner = 7): void {
    list($quote, $revision) = idlm_legacy_pair($status);
    $GLOBALS['wpdb']->seed('idlm_leads', array(lead_fixture(array('id' => 9, 'assigned_rep_id' => $owner, 'quote_value' => 5000, 'quote_shared' => 'Shared' === $status ? 'yes' : ''))));
    $GLOBALS['wpdb']->seed('idlm_quotes', array((array) $quote));
    $GLOBALS['wpdb']->seed('idlm_quote_revisions', array((array) $revision + array('grand_total' => 5000, 'currency_code' => 'INR', 'internal_notes' => 'Legacy quote file: https://example.test/q.pdf')));
    $GLOBALS['wpdb']->seed('idlm_quote_status_history', array(array('id' => 1, 'quote_id' => 1, 'revision_id' => 2, 'from_status' => '', 'to_status' => $status, 'note' => IDLM_Quotation_Repository::LEGACY_HISTORY_NOTE)));
}

function idlm_legacy_rep(array $without = array()): void {
    $caps = array_diff(array('idlm_access_leads', 'idlm_view_quotes', 'idlm_create_quotes', 'idlm_edit_quote_drafts', 'idlm_create_quote_revisions'), $without);
    idlm_test_login(7, array('sales_executive'), array_values($caps));
    $GLOBALS['idlm_test_options']['idlm_role_lead_scopes'] = array('sales_executive' => 'assigned');
    $GLOBALS['idlm_test_redirect_throws'] = true;
}

function idlm_legacy_post(array $extra = array()): void {
    $GLOBALS['idlm_legacy_request'] = (int) ($GLOBALS['idlm_legacy_request'] ?? 0);
    $_POST = $_REQUEST = $extra + array(
        'lead_id' => 9, 'quote_id' => 0, 'revision_id' => 0,
        'save_request_key' => sprintf('12345678-1234-4123-8123-%012d', ++$GLOBALS['idlm_legacy_request']),
        'sections' => array('s1' => array('name' => 'General', 'description' => '', 'sort_order' => 0)),
        'items' => array(array('section_key' => 's1', 'row_type' => 'item', 'description' => 'Wardrobe', 'quantity' => 1, 'unit' => 'Nos', 'unit_rate' => 1000)),
    );
}

function idlm_legacy_save(): IDLM_Test_Response {
    try { IDLM_Quotation_Controller::handle_save(); } catch (IDLM_Test_Response $response) { return $response; }
    throw new RuntimeException('Expected the save handler to redirect or refuse.');
}

function idlm_legacy_rows(string $table): array { return $GLOBALS['wpdb']->rows($table); }

idlm_test('LEGACYQUOTE the policy constants match what the migration writes', static function(): void {
    $schema = file_get_contents(dirname(__DIR__) . '/includes/class-quotation-schema.php');
    assert_contains("'revision_reason' => '" . IDLM_Quotation_Repository::LEGACY_REVISION_REASON . "'", $schema);
    assert_contains("'" . IDLM_Quotation_Repository::LEGACY_HISTORY_NOTE . "'", $schema);
});

idlm_test('LEGACYQUOTE only an untouched import counts as legacy', static function(): void {
    foreach (array('Draft', 'Shared') as $status) {
        list($quote, $revision) = idlm_legacy_pair($status);
        assert_true(IDLM_Quotation_Policy::is_legacy_quote($quote, $revision), $status . ' import should be legacy.');
    }
    list($quote, $revision) = idlm_legacy_pair();
    foreach (array('revision_reason' => 'Initial proposal', 'created_by' => 7, 'revision_number' => 2, 'source_revision_id' => 1, 'finalized_at' => '2025-01-01', 'status' => 'Draft', 'quote_id' => 5, 'id' => 3) as $field => $value) {
        $changed = clone $revision; $changed->$field = $value;
        assert_false(IDLM_Quotation_Policy::is_legacy_quote($quote, $changed), 'Revision ' . $field . ' must end legacy treatment.');
    }
    foreach (array('accepted_revision_id' => 2, 'shared_at' => '2025-01-01', 'accepted_at' => '2025-01-01', 'rejected_at' => '2025-01-01', 'status' => 'Draft') as $field => $value) {
        $changed = clone $quote; $changed->$field = $value;
        assert_false(IDLM_Quotation_Policy::is_legacy_quote($changed, $revision), 'Quote ' . $field . ' must end legacy treatment.');
    }
    foreach (array('Accepted', 'Rejected', 'Cancelled', 'Unknown') as $status) {
        list($quote, $revision) = idlm_legacy_pair($status);
        assert_false(IDLM_Quotation_Policy::is_legacy_quote($quote, $revision), $status . ' is not a legacy import state.');
    }
    unset($revision);
    list($quote, $revision) = idlm_legacy_pair();
    unset($revision->created_by);
    assert_false(IDLM_Quotation_Policy::is_legacy_quote($quote, $revision), 'A missing creator is not proof of an import.');
    assert_false(IDLM_Quotation_Policy::is_legacy_quote(null, null));
});

idlm_test('LEGACYQUOTE a Shared import can be revised but real commercial evidence still refuses', static function(): void {
    idlm_legacy_seed();
    list($quote, $revision) = idlm_legacy_pair();
    assert_true(IDLM_Quotation_Policy::is_editable_draft($quote, $revision));

    // An ordinary Shared quotation stays read-only: the reason is what makes it legacy.
    $ordinary = clone $revision; $ordinary->revision_reason = 'Initial proposal';
    assert_false(IDLM_Quotation_Policy::is_editable_draft($quote, $ordinary));

    foreach (array(array('from_status' => 'Shared', 'to_status' => 'Accepted', 'note' => 'Customer accepted'), array('from_status' => '', 'to_status' => 'Shared', 'note' => 'Shared by hand')) as $row) {
        idlm_legacy_seed();
        $GLOBALS['wpdb']->seed('idlm_quote_status_history', array($row + array('id' => 2, 'quote_id' => 1)));
        assert_false(IDLM_Quotation_Policy::is_editable_draft($quote, $revision), 'Other history is still evidence.');
        assert_false(IDLM_Quotation_Policy::allows_new_quote($quote), 'Other history also blocks a new quotation.');
    }
    idlm_legacy_seed();
    $GLOBALS['wpdb']->seed('idlm_quote_revisions', array((array) $revision + array('grand_total' => 5000), array('id' => 3, 'quote_id' => 1, 'status' => 'Accepted', 'revision_number' => 2)));
    assert_false(IDLM_Quotation_Policy::is_editable_draft($quote, $revision), 'Another accepted revision is still evidence.');
});

idlm_test('LEGACYQUOTE a new quotation is allowed once and retires the legacy quote', static function(): void {
    idlm_legacy_seed();
    list($quote) = idlm_legacy_pair();
    assert_true(IDLM_Quotation_Policy::allows_new_quote($quote));
    $GLOBALS['wpdb']->seed('idlm_quotes', array((array) $quote, array('id' => 4, 'lead_id' => 9, 'quote_number' => 'Q-2', 'current_revision_id' => 5, 'status' => 'Draft')));
    list($quote, $revision) = idlm_legacy_pair();
    assert_false(IDLM_Quotation_Policy::allows_new_quote($quote), 'The legacy quote is no longer the newest.');
    assert_false(IDLM_Quotation_Policy::is_editable_draft($quote, $revision), 'A replaced legacy quote stays read-only.');
    assert_same(4, (int) IDLM_Quotation_Repository::get_quote_for_lead(9)->id, 'The newest quotation is the working one.');
});

idlm_test('LEGACYQUOTE a design hold still refuses new and revised quotations', static function(): void {
    idlm_legacy_seed();
    list($quote, $revision) = idlm_legacy_pair();
    $GLOBALS['wpdb']->seed('idlm_design_handoffs', array(array('id' => 1, 'lead_id' => 9, 'status' => IDLM_Design_Handoffs::STATUS_ACCEPTED)));
    assert_false(IDLM_Quotation_Policy::is_editable_draft($quote, $revision));
    assert_false(IDLM_Quotation_Policy::allows_new_quote($quote));
});

idlm_test('LEGACYQUOTE a rep creates a new quotation with a new number and the legacy quote is untouched', static function(): void {
    idlm_legacy_rep();
    idlm_legacy_seed();
    $before_revision = idlm_legacy_rows('idlm_quote_revisions')[0];
    $before_quote = idlm_legacy_rows('idlm_quotes')[0];
    idlm_legacy_post();
    $response = idlm_legacy_save();
    assert_contains('idlm_notice=', $response->data['redirect'] ?? '');
    $quotes = idlm_legacy_rows('idlm_quotes');
    assert_same(2, count($quotes), 'A second quotation row is created.');
    assert_same($before_quote, $quotes[0], 'The legacy quote row is unchanged.');
    assert_same($before_revision, idlm_legacy_rows('idlm_quote_revisions')[0], 'The legacy revision is unchanged.');
    assert_true($quotes[1]['quote_number'] !== $quotes[0]['quote_number'], 'A new quotation takes the next number.');
    assert_same('Draft', $quotes[1]['status']);
});

idlm_test('LEGACYQUOTE a rep revises a Shared legacy quote on the same number and keeps revising', static function(): void {
    idlm_legacy_rep();
    idlm_legacy_seed();
    $before = idlm_legacy_rows('idlm_quote_revisions')[0];
    idlm_legacy_post(array('quote_id' => 1, 'revision_id' => 2, 'revision_reason' => 'Customer changed scope'));
    idlm_legacy_save();
    $quotes = idlm_legacy_rows('idlm_quotes');
    $revisions = idlm_legacy_rows('idlm_quote_revisions');
    assert_same(1, count($quotes), 'A revision adds no quotation row.');
    assert_same('Q-2024-000001', $quotes[0]['quote_number'], 'A revision keeps the quote number.');
    assert_same($before, $revisions[0], 'The legacy revision keeps its status, total and notes.');
    assert_same('Q-2024-000001-R02', $revisions[1]['revision_name']);
    assert_same(2, (int) $revisions[1]['source_revision_id']);
    assert_same((int) $revisions[1]['id'], (int) $quotes[0]['current_revision_id']);
    assert_same('Draft', $quotes[0]['status']);
    assert_same('INR', $revisions[1]['currency_code'], 'The saved legacy currency carries over.');
    assert_false(empty($revisions[1]['document_terms']), 'A revision takes current terms; the legacy revision stays without them.');

    idlm_legacy_post(array('quote_id' => 1, 'revision_id' => (int) $revisions[1]['id'], 'revision_reason' => 'Second change'));
    idlm_legacy_save();
    $revisions = idlm_legacy_rows('idlm_quote_revisions');
    assert_same(3, count($revisions), 'The revision after a legacy quote is an ordinary editable draft.');
    assert_same('Superseded', $revisions[1]['status']);
    assert_same($before, $revisions[0], 'Later revisions leave the legacy revision alone.');
});

idlm_test('LEGACYQUOTE a Draft legacy quote still supersedes as before', static function(): void {
    idlm_legacy_rep();
    idlm_legacy_seed('Draft');
    idlm_legacy_post(array('quote_id' => 1, 'revision_id' => 2, 'revision_reason' => 'Customer changed scope'));
    idlm_legacy_save();
    $revisions = idlm_legacy_rows('idlm_quote_revisions');
    assert_same('Superseded', $revisions[0]['status']);
    assert_same('5000', (string) $revisions[0]['grand_total']);
});

idlm_test('LEGACYQUOTE an ordinary quotation still refuses a second quotation', static function(): void {
    idlm_legacy_rep();
    idlm_legacy_seed();
    $revision = idlm_legacy_rows('idlm_quote_revisions')[0];
    $revision['revision_reason'] = 'Initial proposal'; $revision['created_by'] = 7; $revision['status'] = 'Draft';
    $GLOBALS['wpdb']->seed('idlm_quote_revisions', array($revision));
    $GLOBALS['wpdb']->seed('idlm_quotes', array(array_merge(idlm_legacy_rows('idlm_quotes')[0], array('status' => 'Draft'))));
    idlm_legacy_post();
    $response = idlm_legacy_save();
    assert_contains('quotation+already+exists', str_replace('%20', '+', $response->data['redirect'] ?? ''));
    assert_same(1, count(idlm_legacy_rows('idlm_quotes')));
    assert_same(1, count(idlm_legacy_rows('idlm_quote_revisions')));
    assert_same(array(), idlm_legacy_rows('idlm_number_sequences'), 'A refused request does not advance numbering.');
});

idlm_test('LEGACYQUOTE an accepted or ordinary Shared quotation still cannot be revised', static function(): void {
    foreach (array(array('status' => 'Accepted'), array('revision_reason' => 'Initial proposal')) as $change) {
        idlm_legacy_rep();
        idlm_legacy_seed();
        $GLOBALS['wpdb']->seed('idlm_quote_revisions', array(array_merge(idlm_legacy_rows('idlm_quote_revisions')[0], $change)));
        idlm_legacy_post(array('quote_id' => 1, 'revision_id' => 2, 'revision_reason' => 'Attempted change'));
        $response = idlm_legacy_save();
        assert_contains('idlm_error=', $response->data['redirect'] ?? '');
        assert_same(1, count(idlm_legacy_rows('idlm_quote_revisions')));
    }
});

idlm_test('LEGACYQUOTE ownership and capability checks still refuse the legacy case', static function(): void {
    idlm_legacy_rep();
    idlm_legacy_seed('Shared', 8);
    idlm_legacy_post();
    assert_same(403, idlm_legacy_save()->status, 'Another rep cannot start a new quotation.');
    idlm_legacy_post(array('quote_id' => 1, 'revision_id' => 2, 'revision_reason' => 'Attempted change'));
    assert_same(403, idlm_legacy_save()->status, 'Another rep cannot revise.');

    idlm_legacy_rep(array('idlm_create_quotes'));
    idlm_legacy_seed();
    idlm_legacy_post();
    assert_same(403, idlm_legacy_save()->status, 'A new quotation needs the create grant.');

    idlm_legacy_rep(array('idlm_create_quote_revisions'));
    idlm_legacy_seed();
    idlm_legacy_post(array('quote_id' => 1, 'revision_id' => 2, 'revision_reason' => 'Attempted change'));
    assert_same(403, idlm_legacy_save()->status, 'A revision needs the revision grant.');
    assert_same(1, count(idlm_legacy_rows('idlm_quote_revisions')));
    assert_same(1, count(idlm_legacy_rows('idlm_quotes')));
});

idlm_test('LEGACYQUOTE the save path keeps nonce, locking and design rules', static function(): void {
    $controller = file_get_contents(dirname(__DIR__) . '/includes/class-quotation-controller.php');
    assert_contains("check_admin_referer('idlm_save_quote')", $controller);
    assert_contains('!IDLM_Quotation_Policy::allows_new_quote($existing_quote)', $controller);
    assert_contains("'Draft' === \$revision->status) {\n            \$superseded", $controller);
    $design = file_get_contents(dirname(__DIR__) . '/includes/class-design-revisions.php');
    foreach (array('set_current_revision', 'supersede_revision', 'update_lead_quote_value', 'publish_schedule') as $forbidden) {
        assert_false(false !== strpos($design, $forbidden), 'Design revisions must not call ' . $forbidden);
    }
});
