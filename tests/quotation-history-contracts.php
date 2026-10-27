<?php
/** QUO-010 immutable commercial evidence and draft compatibility. Builder: Shashi. */
if (!defined('IDLM_TESTING')) { exit; }

function idlm_history_draft_pair(): array {
    return array((object) array('id'=>1,'current_revision_id'=>2,'status'=>'Draft'), (object) array('id'=>2,'quote_id'=>1,'status'=>'Draft'));
}
idlm_test('QUO010 draft edit policy refuses each commercial flag, finalized date and foreign revision', static function(): void {
    list($quote,$revision) = idlm_history_draft_pair();
    assert_true(IDLM_Quotation_Policy::is_editable_draft($quote,$revision));
    foreach (array('Shared','Accepted','Rejected','Cancelled','Canceled','Finalized','Unknown') as $status) {
        $q=clone $quote; $q->status=$status; assert_false(IDLM_Quotation_Policy::is_editable_draft($q,$revision));
        $r=clone $revision; $r->status=$status; assert_false(IDLM_Quotation_Policy::is_editable_draft($quote,$r));
    }
    foreach (array('accepted_revision_id'=>3,'shared_at'=>'2025-01-01','accepted_at'=>'2025-01-01','rejected_at'=>'2025-01-01') as $field=>$value) {
        $q=clone $quote; $q->$field=$value; assert_false(IDLM_Quotation_Policy::is_editable_draft($q,$revision));
    }
    $r=clone $revision; $r->finalized_at='2025-01-01'; assert_false(IDLM_Quotation_Policy::is_editable_draft($quote,$r));
    $r=clone $revision; $r->quote_id=9; assert_false(IDLM_Quotation_Policy::is_editable_draft($quote,$r));
    $r=clone $revision; $r->id=3; assert_false(IDLM_Quotation_Policy::is_editable_draft($quote,$r));
});

idlm_test('QUO010 remaining commercial history protects quotes previously normalized to Draft', static function(): void {
    list($quote,$revision) = idlm_history_draft_pair();
    foreach (array('Shared','Accepted','Rejected','Cancelled','Sent',' accepted ') as $status) {
        $GLOBALS['wpdb']->seed('idlm_quote_status_history',array(array('quote_id'=>1,'from_status'=>'Draft','to_status'=>$status)));
        assert_false(IDLM_Quotation_Policy::is_editable_draft($quote,$revision));
    }
    $GLOBALS['wpdb']->seed('idlm_quote_status_history',array(array('quote_id'=>9,'to_status'=>'Accepted'),array('quote_id'=>1,'from_status'=>'Draft','to_status'=>'Draft')));
    assert_true(IDLM_Quotation_Policy::is_editable_draft($quote,$revision));
    $GLOBALS['wpdb']->seed('idlm_quote_revisions',array(array('id'=>3,'quote_id'=>1,'status'=>'Accepted')));
    assert_false(IDLM_Quotation_Policy::is_editable_draft($quote,$revision));
    $GLOBALS['wpdb']->seed('idlm_quote_revisions',array(array('id'=>3,'quote_id'=>1,'status'=>'Superseded','finalized_at'=>'2025-01-01')));
    assert_true(IDLM_Quotation_Policy::is_editable_draft($quote,$revision));
});
