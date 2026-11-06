<?php
/**
 * Won lead to design team handoff: one row per lead, three states.
 * Never changes the lead's stage, quality, owner or quotation. Builder: Shashi.
 */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Design_Handoffs {
    const CAPABILITY = 'idlm_receive_design_handoffs';
    const REP_CAPABILITY = 'idlm_work_design_projects';
    const ROLE = 'idlm_design';
    const REP_ROLE = 'idlm_design_rep';
    const STATUS_NEW = 'new';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_SENT_BACK = 'sent_back';
    const NOTIFICATION_HOOK = 'idlm_design_handoff_notification';
    const MAX_TEXT_BYTES = 8000;
    const OUTCOME_PENDING = 'pending';
    const OUTCOME_RETURNED = 'returned';
    const OUTCOME_ACCEPTED = 'accepted';

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'idlm_design_handoffs';
    }

    private static function versions_table() { global $wpdb; return $wpdb->prefix . 'idlm_design_handoff_versions'; }
    private static function events_table() { global $wpdb; return $wpdb->prefix . 'idlm_design_handoff_events'; }

    /** Every lead-owned table, for deletion, reset and fixtures. */
    public static function tables() { return array('idlm_design_document_versions', 'idlm_design_revisions', 'idlm_design_handoff_events', 'idlm_design_handoff_versions', 'idlm_design_handoffs'); }

    private static function error($code, $message) { return new WP_Error('idlm_handoff_' . $code, $message); }

    /** Additive table, declared through IDLM_DB::create_tables(). */
    public static function schema() {
        global $wpdb;
        $collate = $wpdb->get_charset_collate(); $table = self::table();
        return "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            lead_id bigint(20) unsigned NOT NULL,
            status varchar(20) NOT NULL,
            version_no int unsigned NOT NULL DEFAULT 0,
            design_manager_id bigint(20) unsigned NOT NULL DEFAULT 0,
            notes text NOT NULL,
            scope_summary text NOT NULL,
            pending_items text NOT NULL,
            baseline_quote_id bigint(20) unsigned NOT NULL DEFAULT 0,
            baseline_revision_id bigint(20) unsigned NOT NULL DEFAULT 0,
            baseline_total decimal(14,2) NOT NULL DEFAULT 0.00,
            design_revision_id bigint(20) unsigned NOT NULL DEFAULT 0,
            agreed_revision_id bigint(20) unsigned NOT NULL DEFAULT 0,
            agreed_total decimal(14,2) NOT NULL DEFAULT 0.00,
            agreed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            agreed_at datetime DEFAULT NULL,
            stage_key varchar(30) NOT NULL DEFAULT '',
            stage_entered_at datetime DEFAULT NULL,
            stage_receipt_mark bigint(20) unsigned NOT NULL DEFAULT 0,
            hold_at datetime DEFAULT NULL,
            hold_by bigint(20) unsigned NOT NULL DEFAULT 0,
            hold_reason text DEFAULT NULL,
            hold_resume_date date DEFAULT NULL,
            flags_cleared text DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            completed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            target_date date DEFAULT NULL,
            request_key varchar(64) NOT NULL DEFAULT '',
            sales_owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
            sent_by bigint(20) unsigned NOT NULL DEFAULT 0,
            sent_at datetime NOT NULL,
            advance_amount decimal(14,2) NOT NULL DEFAULT 0.00,
            advance_currency varchar(3) NOT NULL DEFAULT '',
            advance_date date DEFAULT NULL,
            advance_method varchar(80) NOT NULL DEFAULT '',
            advance_reference varchar(255) NOT NULL DEFAULT '',
            advance_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            receipt_confirmation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            designer_id bigint(20) unsigned NOT NULL DEFAULT 0,
            decided_by bigint(20) unsigned NOT NULL DEFAULT 0,
            decided_at datetime DEFAULT NULL,
            send_back_reason text NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY lead_id (lead_id),
            KEY status_sent (status,sent_at),
            KEY designer_status (designer_id,status),
            KEY manager_status (design_manager_id,status),
            KEY advance_attachment_id (advance_attachment_id)
        ) ENGINE=InnoDB $collate;";
    }

    /** One immutable row per submission. Only its outcome fields are ever filled in. */
    public static function versions_schema() {
        global $wpdb;
        $collate = $wpdb->get_charset_collate(); $table = self::versions_table();
        return "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            handoff_id bigint(20) unsigned NOT NULL,
            lead_id bigint(20) unsigned NOT NULL,
            version_no int unsigned NOT NULL,
            design_manager_id bigint(20) unsigned NOT NULL,
            notes text NOT NULL,
            scope_summary text NOT NULL,
            pending_items text NOT NULL,
            baseline_quote_id bigint(20) unsigned NOT NULL DEFAULT 0,
            baseline_revision_id bigint(20) unsigned NOT NULL DEFAULT 0,
            baseline_total decimal(14,2) NOT NULL DEFAULT 0.00,
            advance_amount decimal(14,2) NOT NULL DEFAULT 0.00,
            advance_currency varchar(3) NOT NULL DEFAULT '',
            advance_date date DEFAULT NULL,
            advance_method varchar(80) NOT NULL DEFAULT '',
            advance_reference varchar(255) NOT NULL DEFAULT '',
            advance_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
            receipt_confirmation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            sales_owner_id bigint(20) unsigned NOT NULL DEFAULT 0,
            sent_by bigint(20) unsigned NOT NULL,
            sent_at datetime NOT NULL,
            outcome varchar(20) NOT NULL DEFAULT 'pending',
            outcome_by bigint(20) unsigned NOT NULL DEFAULT 0,
            outcome_at datetime DEFAULT NULL,
            return_reason text NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY handoff_version (handoff_id,version_no),
            KEY lead_id (lead_id),
            KEY advance_attachment_id (advance_attachment_id)
        ) ENGINE=InnoDB $collate;";
    }

    /** Append-only facts for later reporting: who decided, and assignment intervals. */
    public static function events_schema() {
        global $wpdb;
        $collate = $wpdb->get_charset_collate(); $table = self::events_table();
        return "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            handoff_id bigint(20) unsigned NOT NULL,
            lead_id bigint(20) unsigned NOT NULL,
            version_no int unsigned NOT NULL,
            event_type varchar(30) NOT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            design_manager_id bigint(20) unsigned NOT NULL DEFAULT 0,
            designer_before bigint(20) unsigned NOT NULL DEFAULT 0,
            designer_after bigint(20) unsigned NOT NULL DEFAULT 0,
            target_before date DEFAULT NULL,
            target_after date DEFAULT NULL,
            reason text NOT NULL,
            details longtext DEFAULT NULL,
            occurred_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY handoff_id (handoff_id,id),
            KEY lead_id (lead_id),
            KEY type_time (event_type,occurred_at)
        ) ENGINE=InnoDB $collate;";
    }

    /** Pure state rule: every server operation goes through this. */
    public static function can_transition($from, $action) {
        $allowed = array(
            'send' => array('', self::STATUS_SENT_BACK),
            'accept' => array(self::STATUS_NEW),
            'assign' => array(self::STATUS_ACCEPTED),
            'send_back' => array(self::STATUS_NEW),
        );
        return isset($allowed[$action]) && in_array((string) $from, $allowed[$action], true);
    }

    public static function status_label($status) {
        $labels = array(self::STATUS_NEW => 'New', self::STATUS_ACCEPTED => 'Accepted', self::STATUS_SENT_BACK => 'Returned');
        return $labels[$status] ?? 'Unknown';
    }

    /** Required free text. Returns the cleaned value or WP_Error. */
    public static function required_text($value, $label) {
        $clean = is_string($value) ? trim(sanitize_textarea_field($value)) : '';
        if ('' === $clean) { return self::error('text', $label . ' is required.'); }
        if (strlen($clean) > self::MAX_TEXT_BYTES) { return self::error('text', $label . ' is too long.'); }
        return $clean;
    }

    private static function active_design_person() {
        if (!is_user_logged_in() || IDLM_Lead_Access_Policy::is_pre_sales()) { return false; }
        return !(class_exists('IDLM_Admin_Users_Controller') && IDLM_Admin_Users_Controller::is_crm_access_disabled(get_current_user_id()));
    }

    /** Design Manager authority. It never widens the Leads list or lead editing. */
    public static function can_manage() {
        return self::active_design_person() && (current_user_can('manage_options') || current_user_can(self::CAPABILITY));
    }

    /** May open the Design team tab at all: manager or rep. */
    public static function can_receive() {
        return self::can_manage() || (self::active_design_person() && current_user_can(self::REP_CAPABILITY));
    }

    /** Administrators see all; a manager what was addressed to them; anyone what they are assigned. */
    public static function can_see($row) {
        if (!$row || !self::can_receive()) { return false; }
        $me = get_current_user_id();
        if (current_user_can('manage_options') || (int) $row->designer_id === $me) { return true; }
        return self::can_manage() && (int) $row->design_manager_id === $me;
    }

    /** Design Manager or administrator, once the handoff is accepted. Corrections keep their own grant. */
    public static function can_record_payment($lead_id) {
        $row = self::for_lead($lead_id);
        return $row && self::STATUS_ACCEPTED === $row->status && self::can_decide($row);
    }

    /** After acceptance, only Customer approved may replace the payment schedule. */
    public static function locks_schedule($lead_id) {
        $row = self::for_lead($lead_id);
        return $row && self::STATUS_ACCEPTED === $row->status;
    }

    public static function can_decide($row) {
        if (!$row || !self::can_manage()) { return false; }
        return current_user_can('manage_options') || (int) $row->design_manager_id === get_current_user_id();
    }

    /** SQL filter matching can_see(), for lists and counts. */
    public static function scope_sql($alias = 'h') {
        global $wpdb;
        if (current_user_can('manage_options')) { return '1=1'; }
        $me = get_current_user_id();
        return self::can_manage() ? $wpdb->prepare("($alias.design_manager_id = %d OR $alias.designer_id = %d)", $me, $me) : $wpdb->prepare("$alias.designer_id = %d", $me);
    }

    /** Sending reuses the payment grant: lead owner or assignment manager, never pre-sales. */
    public static function can_send($lead_id) {
        return IDLM_Lead_Payments::permitted((int) $lead_id);
    }

    public static function for_lead($lead_id) {
        global $wpdb; $table = self::table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE lead_id = %d", (int) $lead_id));
    }

    public static function find($handoff_id) {
        global $wpdb; $table = self::table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", (int) $handoff_id));
    }

    private static function people($capabilities) {
        $users = get_users(array('capability__in' => $capabilities, 'number' => 200, 'orderby' => 'display_name', 'fields' => array('ID', 'display_name', 'user_email')));
        return array_values(array_filter($users, static function($user) { return self::eligible_person($user->ID, array()); }));
    }

    private static function eligible_person($user_id, $capabilities) {
        $user = get_userdata((int) $user_id);
        if (!$user || '1' === (string) get_user_meta($user->ID, 'idlm_crm_access_disabled', true) || in_array('idlm_pre_sales', (array) $user->roles, true)) { return false; }
        if (!$capabilities) { return true; }
        foreach ($capabilities as $capability) { if (user_can($user, $capability)) { return true; } }
        return false;
    }

    /** Administrators stay selectable, so handoffs work before any role is ticked. */
    public static function managers() { return self::people(array('manage_options', self::CAPABILITY)); }
    public static function designers() { return self::people(array('manage_options', self::CAPABILITY, self::REP_CAPABILITY)); }
    public static function is_manager($user_id) { return self::eligible_person($user_id, array('manage_options', self::CAPABILITY)); }
    public static function is_designer($user_id) { return self::eligible_person($user_id, array('manage_options', self::CAPABILITY, self::REP_CAPABILITY)); }

    /** The agreed quotation behind the lead, and its first payable milestone as the advance. */
    public static function basis($lead_id) {
        global $wpdb;
        $quote = $wpdb->get_row($wpdb->prepare("SELECT id, current_revision_id, accepted_revision_id FROM {$wpdb->prefix}idlm_quotes WHERE lead_id = %d ORDER BY id DESC LIMIT 1", (int) $lead_id));
        if (!$quote) { return null; }
        $revision_id = (int) ($quote->accepted_revision_id ?: $quote->current_revision_id);
        $revision = $revision_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_quote_revisions WHERE id = %d AND quote_id = %d", $revision_id, $quote->id)) : null;
        if (!$revision) { return null; }
        $terms = IDLM_Quotation_Document::read($revision);
        if (is_wp_error($terms) || !$terms || empty($terms['milestones'])) { return null; }
        $position = self::first_payable_position($terms['milestones']);
        if (null === $position) { return null; }
        $snapshot = IDLM_Lead_Payment_Ledger::schedule($revision, array($position));
        if (is_wp_error($snapshot)) { return null; }
        return array('revision' => $revision, 'position' => $position, 'snapshot' => $snapshot, 'milestone' => $terms['milestones'][$position]);
    }

    public static function first_payable_position($milestones) {
        foreach ((array) $milestones as $position => $milestone) {
            $amount = IDLM_Lead_Payment_Ledger::money($milestone['amount'] ?? '');
            if (!is_wp_error($amount) && $amount > 0) { return $position; }
        }
        return null;
    }

    /** Latest balance from the ledger, readable by receivers without any lead lock. */
    public static function balance($lead_id) {
        global $wpdb;
        $json = $wpdb->get_var($wpdb->prepare("SELECT snapshot FROM {$wpdb->prefix}idlm_lead_payment_allocation_sets WHERE lead_id = %d ORDER BY id DESC LIMIT 1", (int) $lead_id));
        $balance = $json ? json_decode($json, true) : null;
        return is_array($balance) ? $balance : null;
    }

    /** Current receipts already on the ledger, newest last. */
    public static function current_receipts($lead_id) {
        global $wpdb; $p = $wpdb->prefix;
        return (array) $wpdb->get_results($wpdb->prepare("SELECT v.* FROM {$p}idlm_lead_payment_confirmations c INNER JOIN {$p}idlm_lead_payment_versions v ON v.id = c.current_version_id AND v.lead_id = c.lead_id WHERE c.lead_id = %d AND v.status <> 'revoked' ORDER BY c.id", (int) $lead_id));
    }

    /** Everything typed apart from the advance. Returns cleaned values or WP_Error. */
    public static function validate_submission($input) {
        $values = array();
        foreach (array('notes' => 'Remarks for design', 'scope_summary' => 'Scope', 'pending_items' => 'Pending items') as $field => $label) {
            $values[$field] = self::required_text($input[$field] ?? '', $label);
            if (is_wp_error($values[$field])) { return $values[$field]; }
        }
        $manager = (int) ($input['design_manager_id'] ?? 0);
        if (!$manager || !self::is_manager($manager)) { return self::error('manager', 'Choose a Design Manager.'); }
        $values['design_manager_id'] = $manager;
        return $values;
    }

    /**
     * Send, or resend after a return. Payments keep their own transactions, so
     * the receipt is saved first; a retry finds it and does not record it twice.
     */
    public static function send($lead_id, $input, $request_key) {
        $lead_id = (int) $lead_id;
        $submission = self::validate_submission($input);
        if (is_wp_error($submission)) { return $submission; }
        if (!is_string($request_key) || !preg_match('/^[A-Za-z0-9_-]{16,64}$/D', $request_key)) { return self::error('request', 'Reload the lead and try again.'); }
        if ($lead_id <= 0 || !self::can_send($lead_id)) { return self::error('denied', 'You do not have permission to send this lead to design.'); }
        $row = self::for_lead($lead_id);
        if ($row && self::STATUS_NEW === $row->status && hash_equals((string) $row->request_key, $request_key)) { return array('replayed' => true, 'handoff' => $row); }
        if (!self::can_transition($row ? $row->status : '', 'send')) { return self::error('open', 'This lead is already with the design team.'); }
        $checked = self::check_lead($lead_id);
        if (is_wp_error($checked)) { return $checked; }
        $missing = self::quotation_missing($lead_id);
        if ('' !== $missing) { return self::error('quotation', $missing); }
        $advance = self::settle_advance($lead_id, $row, $input, $request_key);
        if (is_wp_error($advance)) { return $advance; }
        return self::write_send($lead_id, $submission, $advance, self::baseline($lead_id), $request_key);
    }

    /**
     * Design needs a real quotation with payment terms. A Won amount, or an old quote
     * imported from the deal value, has neither lines to revise nor milestones.
     */
    public static function quotation_missing($lead_id) {
        return self::basis($lead_id) ? '' : 'Attach a quotation with payment terms before sending to design. The Won amount or an old imported quote is not enough.';
    }

    /** The sales price: the quotation's accepted revision, otherwise its current one. */
    public static function baseline($lead_id) {
        global $wpdb;
        $quote = $wpdb->get_row($wpdb->prepare("SELECT id, current_revision_id, accepted_revision_id FROM {$wpdb->prefix}idlm_quotes WHERE lead_id = %d ORDER BY id DESC LIMIT 1", (int) $lead_id));
        $revision_id = $quote ? (int) ($quote->accepted_revision_id ?: $quote->current_revision_id) : 0;
        $total = $revision_id ? $wpdb->get_var($wpdb->prepare("SELECT grand_total FROM {$wpdb->prefix}idlm_quote_revisions WHERE id = %d AND quote_id = %d", $revision_id, $quote->id)) : null;
        return null === $total ? array('quote' => 0, 'revision' => 0, 'total' => '0.00') : array('quote' => (int) $quote->id, 'revision' => $revision_id, 'total' => $total);
    }

    /** Sales cannot change its quotation while design holds it. Returned handoffs reopen it. */
    public static function locks_sales_quote($lead_id) {
        $row = self::for_lead($lead_id);
        return $row && in_array($row->status, array(self::STATUS_NEW, self::STATUS_ACCEPTED), true);
    }

    private static function check_lead($lead_id) {
        global $wpdb;
        $lead = $wpdb->get_row($wpdb->prepare("SELECT stage, is_duplicate FROM {$wpdb->prefix}idlm_leads WHERE id = %d", $lead_id));
        return $lead && 'Won' === $lead->stage && empty($lead->is_duplicate) ? true : self::error('won', 'Only a Won lead can be sent to design.');
    }

    private static function settle_advance($lead_id, $row, $input, $request_key) {
        $ledger = IDLM_Lead_Payments::read($lead_id);
        if (is_wp_error($ledger)) { return $ledger; }
        $receipts = array_values(array_filter((array) $ledger['receipts'], static function($receipt) { return 'revoked' !== $receipt['status']; }));
        if ($receipts) {
            $last = end($receipts);
            return array('amount' => $ledger['balance']['total_received'] ?? $last['amount'], 'currency' => $last['currency'], 'received_date' => $last['received_date'], 'method' => $last['method'], 'reference' => $last['reference'], 'attachment_id' => (int) $last['attachment_id'], 'receipt_id' => (int) $last['confirmation_id']);
        }
        $data = array();
        foreach (array('amount', 'received_date', 'method', 'reference') as $field) { $data[$field] = is_string($input[$field] ?? null) ? trim($input[$field]) : ''; }
        $data['attachment_id'] = (int) ($input['attachment_id'] ?? 0);
        if (!$data['attachment_id'] && $row && (int) $row->advance_attachment_id) { $data['attachment_id'] = (int) $row->advance_attachment_id; }
        $schedule = $ledger['schedule']; $version = (int) $ledger['version']; $basis = $schedule ? null : self::basis($lead_id);
        $data['currency'] = $schedule ? $schedule['currency'] : ($basis ? $basis['snapshot']['currency'] : self::quotation_currency($lead_id));
        // Check everything the sender typed before writing a schedule.
        $receipt = IDLM_Lead_Payment_Ledger::receipt($data);
        if (is_wp_error($receipt)) { return self::error('advance', 'Enter the advance amount, date and method, plus a transaction reference or screenshot.'); }
        if ($basis) {
            $published = IDLM_Lead_Payments::publish_schedule($lead_id, (int) $basis['revision']->id, array($basis['position']), 'Set automatically by Send to design', $version, self::derived_key($request_key, 'schedule'));
            if (is_wp_error($published)) { return $published; }
            $schedule = $basis['snapshot']; $version = (int) $published['event']['id'];
        }
        if (!$schedule) { return $receipt + array('receipt_id' => 0); }
        $recorded = IDLM_Lead_Payments::record($lead_id, $data, $version, self::derived_key($request_key, 'record'));
        if (is_wp_error($recorded)) { return $recorded; }
        return $receipt + array('receipt_id' => (int) $recorded['event']['confirmation_id']);
    }

    /** A quotation without terms still has a currency; the site default is the last resort. */
    private static function quotation_currency($lead_id) {
        global $wpdb; $baseline = self::baseline($lead_id);
        $currency = $baseline['revision'] ? $wpdb->get_var($wpdb->prepare("SELECT currency_code FROM {$wpdb->prefix}idlm_quote_revisions WHERE id = %d", $baseline['revision'])) : '';
        return $currency ?: (string) get_option('idlm_currency_code', 'INR');
    }

    private static function derived_key($request_key, $purpose) { return substr(hash('sha256', $request_key . '|' . $purpose), 0, 40); }

    private static function record_event($handoff_id, $lead_id, $version_no, $type, $fields = array()) {
        global $wpdb;
        IDLM_Lead_Transaction::required(1 === $wpdb->insert(self::events_table(), array_merge(array('handoff_id' => $handoff_id, 'lead_id' => $lead_id, 'version_no' => $version_no, 'event_type' => $type, 'actor_id' => get_current_user_id(), 'reason' => '', 'occurred_at' => current_time('mysql', true)), $fields)));
        self::publish_event((int) $wpdb->insert_id, $handoff_id, $lead_id, $type);
    }

    /**
     * Versioned after-commit event for future teams and integrations. Delivery is at
     * least once, so consumers deduplicate by event_id. It carries no customer data.
     */
    public static function publish_event($event_id, $handoff_id, $lead_id, $type) {
        IDLM_Lead_Transaction::defer('idlm_design_project_event', (int) $lead_id, array('schema_version' => 1, 'event_id' => (int) $event_id, 'event_type' => (string) $type, 'handoff_id' => (int) $handoff_id, 'actor_id' => get_current_user_id(), 'occurred_at' => current_time('mysql', true)));
    }

    private static function write_send($lead_id, $submission, $advance, $baseline, $request_key) {
        $result = null;
        $transaction = IDLM_Lead_Transaction::run(static function() use ($lead_id, $submission, $advance, $baseline, $request_key, &$result) {
            global $wpdb; $table = self::table(); $now = current_time('mysql', true);
            $lead = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT id, stage, is_duplicate, assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", $lead_id)));
            if (!$lead || !IDLM_Lead_Payments::permitted($lead_id, false, true)) { $result = self::error('denied', 'You do not have permission to send this lead to design.'); return true; }
            if ('Won' !== $lead->stage || !empty($lead->is_duplicate)) { $result = self::error('won', 'Only a Won lead can be sent to design.'); return true; }
            if (!self::is_manager($submission['design_manager_id'])) { $result = self::error('manager', 'Choose a Design Manager.'); return true; }
            $row = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE lead_id = %d FOR UPDATE", $lead_id)));
            if ($row && self::STATUS_NEW === $row->status && hash_equals((string) $row->request_key, $request_key)) { $result = array('replayed' => true, 'handoff' => $row); return true; }
            if (!self::can_transition($row ? $row->status : '', 'send')) { $result = self::error('open', 'This lead is already with the design team.'); return true; }
            // An advance held only on the handoff keeps its private screenshot alive.
            if (!$advance['receipt_id'] && $advance['attachment_id']) {
                $files = $wpdb->prefix . 'idlm_private_files';
                $file = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT id FROM $files WHERE attachment_id = %d AND lead_id = %d AND purpose = 'payment' AND state IN ('uploaded','active') FOR UPDATE", $advance['attachment_id'], $lead_id)));
                if (!$file) { $result = self::error('proof', 'Upload a current payment screenshot for this lead.'); return true; }
                IDLM_Lead_Transaction::required($wpdb->update($files, array('state' => 'active'), array('id' => $file->id)));
            }
            $actor = get_current_user_id(); $version_no = $row ? (int) $row->version_no + 1 : 1;
            $shared = array('design_manager_id' => $submission['design_manager_id'], 'notes' => $submission['notes'], 'scope_summary' => $submission['scope_summary'], 'pending_items' => $submission['pending_items'], 'baseline_quote_id' => $baseline['quote'], 'baseline_revision_id' => $baseline['revision'], 'baseline_total' => $baseline['total'],
                'sales_owner_id' => (int) $lead->assigned_rep_id, 'sent_by' => $actor, 'sent_at' => $now, 'advance_amount' => $advance['amount'], 'advance_currency' => $advance['currency'], 'advance_date' => $advance['received_date'], 'advance_method' => $advance['method'], 'advance_reference' => $advance['reference'], 'advance_attachment_id' => $advance['attachment_id'], 'receipt_confirmation_id' => $advance['receipt_id']);
            $values = array_merge($shared, array('status' => self::STATUS_NEW, 'version_no' => $version_no, 'request_key' => $request_key, 'designer_id' => 0, 'target_date' => null, 'decided_by' => 0, 'decided_at' => null, 'send_back_reason' => '', 'updated_at' => $now));
            if ($row) { IDLM_Lead_Transaction::required($wpdb->update($table, $values, array('id' => $row->id))); $handoff_id = (int) $row->id; }
            else { IDLM_Lead_Transaction::required(1 === $wpdb->insert($table, array_merge(array('lead_id' => $lead_id), $values))); $handoff_id = (int) $wpdb->insert_id; }
            IDLM_Lead_Transaction::required(1 === $wpdb->insert(self::versions_table(), array_merge($shared, array('handoff_id' => $handoff_id, 'lead_id' => $lead_id, 'version_no' => $version_no, 'outcome' => self::OUTCOME_PENDING, 'return_reason' => ''))));
            self::record_event($handoff_id, $lead_id, $version_no, 'sent', array('design_manager_id' => $submission['design_manager_id']));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($lead_id, 'design_handoff', ($version_no > 1 ? 'Resent' : 'Sent') . ' to Design Manager ' . self::name_of($submission['design_manager_id']) . '.', $actor));
            IDLM_Lead_Transaction::defer(self::NOTIFICATION_HOOK, $lead_id, 'sent');
            $result = array('replayed' => false, 'handoff' => IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE lead_id = %d", $lead_id))));
            return true;
        });
        return is_wp_error($transaction) ? $transaction : $result;
    }

    private static function name_of($user_id) {
        $user = get_userdata((int) $user_id);
        return $user ? $user->display_name : 'Team member';
    }

    /** A target date is required with a designer. Returns Y-m-d or WP_Error. */
    public static function clean_target($target) {
        $parsed = is_string($target) ? DateTimeImmutable::createFromFormat('!Y-m-d', $target) : false;
        return $parsed && $parsed->format('Y-m-d') === $target ? $target : self::error('target', 'Choose a target completion date.');
    }

    private static function close_version($row, $outcome, $reason = '') {
        global $wpdb;
        IDLM_Lead_Transaction::required($wpdb->update(self::versions_table(), array('outcome' => $outcome, 'outcome_by' => get_current_user_id(), 'outcome_at' => current_time('mysql', true), 'return_reason' => $reason), array('handoff_id' => $row->id, 'version_no' => $row->version_no, 'outcome' => self::OUTCOME_PENDING)));
    }

    /** Accept, and assign in the same step when a designer is chosen. */
    public static function accept($handoff_id, $designer_id = 0, $target = '') {
        $designer_id = (int) $designer_id; $target_date = null;
        if ($designer_id) {
            $target_date = self::clean_target($target);
            if (is_wp_error($target_date)) { return $target_date; }
        }
        return self::decide($handoff_id, 'accept', static function($row, $lead, $table) use ($designer_id, $target_date) {
            global $wpdb;
            if ('Won' !== $lead->stage) { return self::error('won', 'This lead is no longer Won. Return it or ask sales to review it.'); }
            if ($designer_id && !self::is_designer($designer_id)) { return self::error('designer', 'Choose a designer who works on design projects.'); }
            // Sent before a quotation was required: return it so sales can attach one.
            if ('' !== self::quotation_missing($row->lead_id)) { return self::error('quotation', 'This lead has no quotation with payment terms. Send it back so sales can attach one.'); }
            $now = current_time('mysql', true); $baseline = self::baseline($row->lead_id);
            IDLM_Lead_Transaction::required($wpdb->update($table, array('status' => self::STATUS_ACCEPTED, 'designer_id' => $designer_id, 'target_date' => $target_date, 'baseline_quote_id' => $baseline['quote'], 'baseline_revision_id' => $baseline['revision'], 'baseline_total' => $baseline['total'], 'stage_key' => IDLM_Design_Stages::first(), 'stage_entered_at' => $now, 'stage_receipt_mark' => IDLM_Design_Stages::receipt_mark($row->lead_id), 'decided_by' => get_current_user_id(), 'decided_at' => $now, 'send_back_reason' => '', 'updated_at' => $now), array('id' => $row->id)));
            self::close_version($row, self::OUTCOME_ACCEPTED);
            self::record_event((int) $row->id, (int) $row->lead_id, (int) $row->version_no, 'accepted', array('design_manager_id' => (int) $row->design_manager_id, 'designer_after' => $designer_id, 'target_after' => $target_date));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Accepted by the design team.' . ($designer_id ? ' Designer: ' . self::name_of($designer_id) . '.' : ' Designer to be assigned.'), get_current_user_id()));
            IDLM_Lead_Transaction::defer(self::NOTIFICATION_HOOK, (int) $row->lead_id, 'accepted');
            return true;
        });
    }

    /** Assign an accepted handoff, or reassign it with a reason. */
    public static function assign($handoff_id, $designer_id, $target, $reason = '') {
        $designer_id = (int) $designer_id;
        $target_date = self::clean_target($target);
        if (is_wp_error($target_date)) { return $target_date; }
        return self::decide($handoff_id, 'assign', static function($row, $lead, $table) use ($designer_id, $target_date, $reason) {
            global $wpdb;
            if (!self::is_designer($designer_id)) { return self::error('designer', 'Choose a designer who works on design projects.'); }
            $note = '';
            if ((int) $row->designer_id && (int) $row->designer_id !== $designer_id) {
                $note = self::required_text($reason, 'A reason for reassigning');
                if (is_wp_error($note)) { return self::error('reason', $note->get_error_message()); }
            }
            IDLM_Lead_Transaction::required($wpdb->update($table, array('designer_id' => $designer_id, 'target_date' => $target_date, 'updated_at' => current_time('mysql', true)), array('id' => $row->id)));
            self::record_event((int) $row->id, (int) $row->lead_id, (int) $row->version_no, 'assigned', array('design_manager_id' => (int) $row->design_manager_id, 'designer_before' => (int) $row->designer_id, 'designer_after' => $designer_id, 'target_before' => $row->target_date, 'target_after' => $target_date, 'reason' => $note));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Designer: ' . self::name_of($designer_id) . '.' . ($note ? ' Reason: ' . $note : ''), get_current_user_id()));
            IDLM_Lead_Transaction::defer(self::NOTIFICATION_HOOK, (int) $row->lead_id, 'assigned');
            return true;
        });
    }

    public static function send_back($handoff_id, $reason) {
        $reason = self::required_text($reason, 'A reason');
        if (is_wp_error($reason)) { return $reason; }
        return self::decide($handoff_id, 'send_back', static function($row, $lead, $table) use ($reason) {
            global $wpdb;
            $now = current_time('mysql', true);
            IDLM_Lead_Transaction::required($wpdb->update($table, array('status' => self::STATUS_SENT_BACK, 'designer_id' => 0, 'target_date' => null, 'decided_by' => get_current_user_id(), 'decided_at' => $now, 'send_back_reason' => $reason, 'updated_at' => $now), array('id' => $row->id)));
            self::close_version($row, self::OUTCOME_RETURNED, $reason);
            self::record_event((int) $row->id, (int) $row->lead_id, (int) $row->version_no, 'returned', array('design_manager_id' => (int) $row->design_manager_id, 'reason' => $reason));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Returned by the design team: ' . $reason, get_current_user_id()));
            IDLM_Lead_Transaction::defer(self::NOTIFICATION_HOOK, (int) $row->lead_id, 'sent_back');
            return true;
        });
    }

    /** Earlier submissions, newest first, for people who can already see this handoff. */
    public static function versions($handoff_id) {
        global $wpdb; $table = self::versions_table();
        return (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE handoff_id = %d ORDER BY version_no DESC LIMIT 50", (int) $handoff_id));
    }

    private static function decide($handoff_id, $action, $apply) {
        $handoff_id = (int) $handoff_id;
        if (!self::can_manage()) { return self::error('denied', 'You do not have permission to act on design handoffs.'); }
        $known = self::find($handoff_id);
        if (!$known) { return self::error('missing', 'This handoff is no longer available.'); }
        $result = null;
        $transaction = IDLM_Lead_Transaction::run(static function() use ($handoff_id, $known, $action, $apply, &$result) {
            global $wpdb; $table = self::table();
            $lead = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT id, stage FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", $known->lead_id)));
            // The result is ignored on purpose: this refreshes live grants for any role.
            $actor = get_current_user_id();
            IDLM_Lead_Access_Policy::is_assignable_rep($actor, 'any', true);
            wp_set_current_user(0); wp_set_current_user($actor);
            $row = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d FOR UPDATE", $handoff_id)));
            if (!$lead || !$row) { $result = self::error('missing', 'This handoff is no longer available.'); return true; }
            if (!self::can_decide($row)) { $result = self::error('denied', 'You do not have permission to act on design handoffs.'); return true; }
            if (!self::can_transition($row->status, $action)) { $result = self::error('state', 'This handoff has already changed. Check its current status.'); return true; }
            $result = call_user_func($apply, $row, $lead, $table);
            return true;
        });
        return is_wp_error($transaction) ? $transaction : $result;
    }

    /** Receivers read a handed-off lead's files without gaining lead access. */
    public static function may_read_file($lead_id) {
        return self::can_see(self::for_lead($lead_id));
    }

    /** Keeps a screenshot referenced only by a handoff from expiring. */
    public static function references_file($lead_id, $attachment_id) {
        global $wpdb; $table = self::versions_table();
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE lead_id = %d AND advance_attachment_id = %d LIMIT 1", (int) $lead_id, (int) $attachment_id));
    }

    public static function partial_recovery_safe() {
        global $wpdb; $table = self::table();
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        return !$wpdb->last_error && '0' === (string) $count;
    }

    /** Internal staff email only, behind the existing handoff email switch. */
    public static function notify($lead_id, $event) {
        if (!IDLM_Settings::notification_enabled('idlm_handoff_notifications_enabled')) { return; }
        global $wpdb;
        $row = self::for_lead($lead_id);
        $lead = $wpdb->get_row($wpdb->prepare("SELECT first_name, last_name FROM {$wpdb->prefix}idlm_leads WHERE id = %d", (int) $lead_id));
        if (!$row || !$lead) { return; }
        $name = trim($lead->first_name . ' ' . $lead->last_name); $emails = array();
        $email_of = static function($user_id) { $user = get_userdata((int) $user_id); return $user ? $user->user_email : ''; };
        if ('sent' === $event) {
            $emails = array($email_of($row->design_manager_id));
            $subject = sprintf('[Lead Manager] New design handoff: %s', $name); $body = 'A Won lead has been sent to you for design: ' . $name . '.';
        } elseif ('accepted' === $event) {
            $emails = array($email_of($row->designer_id), $email_of($row->sent_by));
            $subject = sprintf('[Lead Manager] Design handoff accepted: %s', $name); $body = 'The design team accepted ' . $name . '.';
        } elseif ('assigned' === $event) {
            $emails = array($email_of($row->designer_id));
            $subject = sprintf('[Lead Manager] Design project assigned: %s', $name); $body = $name . ' has been assigned to you.';
        } elseif ('sent_back' === $event) {
            $emails = array($email_of($row->sent_by));
            $subject = sprintf('[Lead Manager] Design handoff returned: %s', $name); $body = 'The design team returned ' . $name . ": \n\n" . $row->send_back_reason;
        } else { return; }
        $emails = array_values(array_unique(array_filter($emails, 'is_email')));
        if (!$emails) { return; }
        $sent = wp_mail($emails, $subject, $body . "\n\nOpen the CRM to see the details.");
        if (!$sent && IDLM_Lead_Outbox::dispatching()) { throw new RuntimeException('Design handoff notification was not accepted.'); }
    }
}
