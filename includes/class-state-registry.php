<?php
/** Built-in vocabularies and contextual subsets; transition rules live in IDLM_DB/lifecycle. @author Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_State_Registry {
    const STAGE_NEW = 'New';
    const STAGE_CONTACTED = 'Contacted';
    const STAGE_QUALIFIED = 'Qualified';
    const STAGE_VISITED = 'Visited Studio';
    const STAGE_QUOTE_SHARED = 'Quote Shared';
    const STAGE_NEGOTIATION = 'Negotiation';
    const STAGE_WON = 'Won';
    const STAGE_LOST = 'Lost';
    const QUALITY_QUALIFIED = 'Qualified';
    const QUALITY_UNQUALIFIED = 'Unqualified';
    const TASK_QUALIFICATION = 'Qualification';
    const TASK_PENDING = 'pending';
    const TASK_COMPLETED = 'completed';
    const CALL_NOT_CALLED = 'Not Called';
    const CALL_NO_ANSWER = 'No Answer';
    const CALL_BACK_REQUESTED = 'Call Back Requested';
    const CALL_CONNECTED_INTERESTED = 'Connected - Interested';
    const CALL_CONNECTED_NOT_INTERESTED = 'Connected - Not Interested';
    const CALL_WRONG_NUMBER = 'Wrong Number';

    public static function call_statuses() {
        return array(
            'not_called' => self::CALL_NOT_CALLED, 'no_answer' => self::CALL_NO_ANSWER,
            'call_back_requested' => self::CALL_BACK_REQUESTED,
            'connected_interested' => self::CALL_CONNECTED_INTERESTED,
            'connected_not_interested' => self::CALL_CONNECTED_NOT_INTERESTED,
            'wrong_number' => self::CALL_WRONG_NUMBER,
        );
    }

    public static function handoff_statuses() {
        return array(
            'not_ready' => 'not_ready', 'awaiting_manager' => 'awaiting_manager',
            'manually_assigned' => 'manually_assigned', 'auto_assigned' => 'auto_assigned',
            'exception' => 'exception',
        );
    }

    public static function task_types() {
        return array_merge(array('qualification' => self::TASK_QUALIFICATION, 'sales_follow_up' => 'Sales Follow-up'), self::manual_task_types());
    }

    /** Lifecycle-owned task types are deliberately absent from the manual form. */
    public static function manual_task_types() {
        return array('general' => 'General', 'call' => 'Call', 'email' => 'Email',
            'site_visit' => 'Site Visit', 'design_mockup' => 'Design Mockup', 'quote_revision' => 'Quote Revision');
    }

    /** Only this stage implicitly makes a qualification decision. */
    public static function quality_coupled_stages() {
        return array(self::STAGE_QUALIFIED);
    }

    /** Entry checks when an update supplies Qualified quality; these stages do not imply it. */
    public static function qualification_validation_stages() {
        return array(self::STAGE_QUALIFIED, self::STAGE_VISITED, self::STAGE_QUOTE_SHARED, self::STAGE_NEGOTIATION, self::STAGE_WON);
    }

    /** Form groups also identify reasons requiring connected-call or reply evidence. */
    public static function early_close_reason_groups() {
        return array(
            'objective' => array('spam_test' => 'Spam or test submission', 'duplicate' => 'Duplicate lead', 'invalid_contact' => 'Invalid or nonexistent contact'),
            'confirmed' => array('unrelated_enquiry' => 'Vendor, job seeker, or unrelated enquiry', 'do_not_contact' => 'Requested no further contact',
                'budget_below_minimum' => 'Budget below ₹4L', 'outside_service_area' => 'Outside supported service area',
                'unsupported_project_type' => 'Unsupported project type', 'timeline_mismatch' => 'Project timeline mismatch',
                'not_interested' => 'Customer confirmed not interested'),
            'administrator' => array('administrator_exception' => 'Administrator exception'),
        );
    }

    public static function early_close_reasons() {
        return array_merge(...array_values(self::early_close_reason_groups()));
    }

    public static function disqualification_reasons() {
        return array(
            'spam_test' => 'Spam or Test', 'administrator_exception' => 'Administrator Exception',
            'duplicate' => 'Duplicate', 'invalid_contact' => 'Invalid Contact',
            'unrelated_enquiry' => 'Unrelated Enquiry', 'do_not_contact' => 'Do Not Contact',
            'budget_below_minimum' => 'Budget Below Minimum', 'outside_service_area' => 'Outside Service Area',
            'unsupported_project_type' => 'Unsupported Project Type', 'timeline_mismatch' => 'Timeline Mismatch',
            'not_interested' => 'Not Interested', 'unable_to_contact' => 'Unable to Contact',
            'legacy_unqualified' => 'Legacy Unqualified',
        );
    }

    public static function values($domain) {
        $registries = array(
            'stage' => self::stages(), 'call_status' => self::call_statuses(),
            'handoff_status' => self::handoff_statuses(), 'task_type' => self::task_types(),
            'disqualification_reason' => self::disqualification_reasons(),
        );
        return $registries[sanitize_key($domain)] ?? array();
    }

    public static function stages() {
        return array(
            'new' => self::STAGE_NEW, 'contacted' => self::STAGE_CONTACTED,
            'qualified' => self::STAGE_QUALIFIED, 'visited_studio' => self::STAGE_VISITED,
            'quote_shared' => self::STAGE_QUOTE_SHARED, 'negotiation' => self::STAGE_NEGOTIATION,
            'won' => self::STAGE_WON, 'lost' => self::STAGE_LOST,
        );
    }

    public static function stage_label($key_or_label) {
        return self::label('stage', $key_or_label);
    }

    public static function label($domain, $key_or_label) {
        $stages = self::values($domain);
        $key = sanitize_key(str_replace(' ', '_', (string) $key_or_label));
        return $stages[$key] ?? (in_array($key_or_label, $stages, true) ? $key_or_label : '');
    }

    public static function stage_key($label) {
        $key = array_search(self::stage_label($label), self::stages(), true);
        return false === $key ? '' : $key;
    }
}
