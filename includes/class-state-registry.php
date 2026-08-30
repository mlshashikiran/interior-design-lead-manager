<?php
/** Stable workflow keys and their persisted labels. @author Shashi */
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
        return array('qualification' => self::TASK_QUALIFICATION, 'sales_follow_up' => 'Sales Follow-up', 'general' => 'General');
    }

    public static function disqualification_reasons() {
        return array(
            'duplicate' => 'Duplicate', 'invalid_contact' => 'Invalid Contact',
            'unrelated_enquiry' => 'Unrelated Enquiry', 'do_not_contact' => 'Do Not Contact',
            'budget_below_minimum' => 'Budget Below Minimum', 'outside_service_area' => 'Outside Service Area',
            'unsupported_project_type' => 'Unsupported Project Type', 'timeline_mismatch' => 'Timeline Mismatch',
            'not_interested' => 'Not Interested', 'unable_to_contact' => 'Unable to Contact',
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
