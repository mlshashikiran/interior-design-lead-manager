<?php
/**
 * Design stages, their checklists and private documents. Administrators add, rename,
 * reorder and remove stages and choose what each one requires. Stage keys never change,
 * and a removed stage is archived, never deleted, so history keeps its meaning. A
 * checklist gates leaving a stage, not entering it, so the work happens inside the stage.
 * Builder: Shashi.
 */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Design_Stages {
    const OPTION = 'idlm_design_stage_settings';
    /** The default start stage; the live start is first(). */
    const FIRST = 'new_project';
    const PAYMENTS = array('' => 'No payment check', 'advance' => 'Advance milestone', 'second' => 'Milestone after the advance', 'final' => 'Final milestone');
    const MAX_STAGES = 20;
    const MAX_DOCUMENTS = 30;
    const MAX_HOLD_REASONS = 20;
    /** Offered on every site. Administrators add their own below these; "Other" always comes last. */
    const HOLD_REASONS = array('Waiting for customer', 'Waiting for payment', 'Site not ready', 'Customer travelling', 'Waiting for material samples');
    const HOLD_OTHER = 'Other';
    const STATE_CURRENT = 'current';
    const STATE_SUPERSEDED = 'superseded';
    const STATE_ARCHIVED = 'archived';

    private static function table() { global $wpdb; return $wpdb->prefix . 'idlm_design_document_versions'; }
    private static function error($code, $message) { return new WP_Error('idlm_design_stage_' . $code, $message); }

    public static function schema() {
        global $wpdb;
        $collate = $wpdb->get_charset_collate(); $table = self::table();
        return "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            handoff_id bigint(20) unsigned NOT NULL,
            lead_id bigint(20) unsigned NOT NULL,
            document_key varchar(190) NOT NULL,
            slot_key varchar(40) NOT NULL,
            title varchar(190) NOT NULL,
            version_no int unsigned NOT NULL,
            attachment_id bigint(20) unsigned NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'current',
            uploaded_by bigint(20) unsigned NOT NULL,
            uploaded_at datetime NOT NULL,
            archived_by bigint(20) unsigned NOT NULL DEFAULT 0,
            archived_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY document_version (handoff_id,document_key,version_no),
            KEY handoff_state (handoff_id,state),
            KEY lead_id (lead_id),
            KEY attachment_id (attachment_id)
        ) ENGINE=InnoDB $collate;";
    }

    /** File types an administrator can choose for a document a stage requires. */
    public static function file_types() {
        $image = array('jpg', 'jpeg', 'png', 'webp');
        return array(
            'pdf' => array('label' => 'PDF', 'extensions' => array('pdf')),
            'dwg' => array('label' => 'DWG drawing', 'extensions' => array('dwg')),
            'image' => array('label' => 'Image (JPG, PNG or WebP)', 'extensions' => $image),
            'pdf_image' => array('label' => 'PDF or image', 'extensions' => array_merge(array('pdf'), $image)),
            'any' => array('label' => 'PDF, image or DWG', 'extensions' => array_merge(array('pdf', 'dwg'), $image)),
        );
    }

    /** Built-in evidence documents plus the ones an administrator added. */
    public static function slots() { return self::slots_from(get_option(self::OPTION, array())); }

    /** Pure: the documents a stage can require, from saved settings. */
    public static function slots_from($saved) {
        $slots = self::builtin_slots(); $types = self::file_types();
        foreach (self::custom_documents_from($saved) as $key => $document) { $slots[$key] = array('label' => $document['label'], 'extensions' => $types[$document['type']]['extensions'], 'custom' => true); }
        return $slots;
    }

    /** Pure: validated administrator-added documents, by key. */
    public static function custom_documents_from($saved) {
        $documents = array(); $types = self::file_types(); $builtin = self::builtin_slots();
        if (!is_array($saved) || 2 !== (int) ($saved['version'] ?? 0) || !is_array($saved['documents'] ?? null)) { return $documents; }
        foreach ($saved['documents'] as $document) {
            $key = is_array($document) && is_string($document['key'] ?? null) ? $document['key'] : '';
            $label = is_array($document) && is_string($document['label'] ?? null) ? trim($document['label']) : '';
            $type = is_array($document) && is_string($document['type'] ?? null) ? $document['type'] : '';
            if (!preg_match('/^doc_[a-z0-9_]{1,36}$/', $key) || isset($builtin[$key]) || isset($documents[$key]) || '' === $label || !isset($types[$type])) { continue; }
            $documents[$key] = array('label' => $label, 'type' => $type);
        }
        return $documents;
    }
    public static function custom_documents() { return self::custom_documents_from(get_option(self::OPTION, array())); }

    /** Fixed evidence documents shipped with the plugin. */
    private static function builtin_slots() {
        $dwg = array('dwg'); $image = array('jpg', 'jpeg', 'png', 'webp');
        return array(
            'final_render_pdf' => array('label' => 'Final render PDF', 'extensions' => array('pdf')),
            'masking_dwg' => array('label' => 'Masking DWG', 'extensions' => $dwg),
            'masking_changes_dwg' => array('label' => 'Masking changes DWG', 'extensions' => $dwg),
            'dqc_dwg' => array('label' => 'DQC DWG', 'extensions' => $dwg),
            'mom' => array('label' => 'Minutes of the meeting', 'extensions' => array_merge(array('pdf'), $image)),
            'approval_mail' => array('label' => 'Approval mail screenshot', 'extensions' => $image),
            'p2p_dwg' => array('label' => 'P2P DWG', 'extensions' => $dwg),
        );
    }

    /** Other project files. Not required by any stage. */
    public static function categories() {
        return array('measurement' => 'Measurement', 'site_photos' => 'Site photos', 'layout' => 'Layout', 'electrical_plumbing' => 'Electrical and plumbing', 'design_3d' => '3D design', 'material_spec' => 'Material specification', 'other' => 'Other');
    }

    public static function allowed_extensions($slot_key) {
        $slots = self::slots();
        if (isset($slots[$slot_key])) { return $slots[$slot_key]['extensions']; }
        return isset(self::categories()[$slot_key]) ? array('pdf', 'jpg', 'jpeg', 'png', 'webp', 'dwg') : array();
    }

    private static function requires($set = array()) {
        return array_merge(array('designer' => false, 'payment' => '', 'tentative_quote' => false, 'final_quote' => false), $set);
    }

    /** The six stages the business started with, with the checks they always had. */
    private static function defaults() {
        $stage = static function($label, $slots, $requires) { return array('label' => $label, 'slots' => $slots, 'requires' => self::requires($requires), 'archived' => false); };
        return array(
            'new_project' => $stage('New Project / Lead', array(), array('designer' => true)),
            'material_selection' => $stage('Material Selection', array(), array('payment' => 'advance')),
            'masking' => $stage('Masking', array('final_render_pdf', 'masking_dwg'), array()),
            'kick_off' => $stage('Kick Off Meeting', array('masking_changes_dwg'), array('payment' => 'second', 'tentative_quote' => true)),
            'dqc' => $stage('Design Quality Check', array('dqc_dwg', 'mom', 'approval_mail'), array('tentative_quote' => true)),
            'p2p' => $stage('P2P', array('p2p_dwg'), array('payment' => 'final', 'final_quote' => true)),
        );
    }

    /**
     * Pure: the ordered stage list from saved settings. Version 2 is the managed list. Anything
     * older (names and documents keyed by the six stages) reads exactly as it did before.
     */
    public static function merge_settings($saved) {
        $saved = is_array($saved) ? $saved : array();
        if (2 === (int) ($saved['version'] ?? 0) && is_array($saved['stages'] ?? null)) {
            $known = array_keys(self::slots_from($saved)); $stages = array();
            foreach ($saved['stages'] as $stage) {
                if (!is_array($stage)) { continue; }
                $key = is_string($stage['key'] ?? null) ? $stage['key'] : ''; $label = is_string($stage['label'] ?? null) ? trim($stage['label']) : '';
                if (!preg_match('/^[a-z0-9_]{1,30}$/', $key) || isset($stages[$key]) || '' === $label) { continue; }
                $requires = is_array($stage['requires'] ?? null) ? $stage['requires'] : array(); $payment = is_string($requires['payment'] ?? null) ? $requires['payment'] : '';
                $stages[$key] = array('label' => $label, 'slots' => array_values(array_intersect($known, is_array($stage['slots'] ?? null) ? $stage['slots'] : array())), 'requires' => self::requires(array('designer' => !empty($requires['designer']), 'payment' => isset(self::PAYMENTS[$payment]) ? $payment : '', 'tentative_quote' => !empty($requires['tentative_quote']), 'final_quote' => !empty($requires['final_quote']))), 'archived' => !empty($stage['archived']));
            }
            // A list with no active stage is unusable, so the defaults stand in rather than a broken board.
            if (array_filter($stages, static function($stage) { return !$stage['archived']; })) { return $stages; }
        }
        $stages = self::defaults(); $known = array_keys(self::builtin_slots());
        foreach ($stages as $key => $stage) {
            $custom = isset($saved[$key]) && is_array($saved[$key]) ? $saved[$key] : array();
            if (is_string($custom['label'] ?? null) && '' !== trim($custom['label'])) { $stages[$key]['label'] = trim($custom['label']); }
            if (is_array($custom['slots'] ?? null)) { $stages[$key]['slots'] = array_values(array_intersect($known, $custom['slots'])); }
        }
        return $stages;
    }

    /** Pure: the reasons an administrator added, from saved settings of any version. */
    public static function custom_hold_reasons_from($saved) {
        $reasons = array();
        foreach ((array) (is_array($saved) ? ($saved['hold_reasons'] ?? array()) : array()) as $reason) {
            if (is_string($reason) && '' !== trim($reason)) { $reasons[] = trim($reason); }
        }
        return $reasons;
    }
    public static function custom_hold_reasons() { return self::custom_hold_reasons_from(get_option(self::OPTION, array())); }
    /** The list a person picks from when putting a project on hold, without Other. */
    public static function hold_reasons() {
        $list = self::HOLD_REASONS; $seen = array_map('strtolower', $list);
        foreach (self::custom_hold_reasons() as $reason) { if (!in_array(strtolower($reason), $seen, true)) { $list[] = $reason; $seen[] = strtolower($reason); } }
        return $list;
    }

    /**
     * The saved hold reason from a picked reason and an optional note. Other needs the note,
     * because it is then the whole reason. Takes unslashed values. Returns the text or WP_Error.
     */
    public static function hold_reason_text($choice, $note) {
        $choice = is_string($choice) ? trim($choice) : ''; $note = is_string($note) ? trim(sanitize_textarea_field($note)) : '';
        if (self::HOLD_OTHER === $choice) {
            if ('' === $note) { return self::error('reason', 'Describe the reason when you choose Other.'); }
            return $note;
        }
        if (!in_array($choice, self::hold_reasons(), true)) { return self::error('reason', 'Choose a reason for the hold.'); }
        return '' === $note ? $choice : $choice . ': ' . $note;
    }

    /** Every stage in order, removed ones included, so old projects and history keep a name. */
    public static function stages() { return self::merge_settings(get_option(self::OPTION, array())); }
    /** Stages projects can move through, in order. */
    public static function active() { return array_filter(self::stages(), static function($stage) { return !$stage['archived']; }); }
    public static function first() { $keys = array_keys(self::active()); return $keys ? $keys[0] : self::FIRST; }
    public static function stage_of($row) {
        $key = (string) ($row->stage_key ?? '');
        return isset(self::stages()[$key]) ? $key : self::first();
    }
    /** The next or previous active stage. Works from a removed stage too, by its place in the order. */
    public static function neighbour($key, $step) {
        $stages = self::stages(); $keys = array_keys($stages); $index = array_search($key, $keys, true);
        if (false === $index) { return null; }
        for ($i = $index + $step; isset($keys[$i]); $i += $step) { if (!$stages[$keys[$i]]['archived']) { return $keys[$i]; } }
        return null;
    }
    /** Stages to show for a set of current stage keys: every active one, plus removed ones still in use. */
    public static function shown($in_use = array()) {
        $in_use = array_flip((array) $in_use);
        return array_filter(self::stages(), static function($stage, $key) use ($in_use) { return !$stage['archived'] || isset($in_use[$key]); }, ARRAY_FILTER_USE_BOTH);
    }
    /** The newest receipt identity when a stage starts; later ones were taken during it. */
    public static function receipt_mark($lead_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare("SELECT MAX(id) FROM {$wpdb->prefix}idlm_lead_payment_confirmations WHERE lead_id = %d", (int) $lead_id));
    }

    /** Current files by document key, newest first. */
    public static function documents($handoff_id, $include_history = false) {
        global $wpdb; $table = self::table();
        $where = $include_history ? '' : $wpdb->prepare(' AND state = %s', self::STATE_CURRENT);
        return (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE handoff_id = %d$where ORDER BY id DESC LIMIT 300", (int) $handoff_id));
    }

    public static function references_file($lead_id, $attachment_id) {
        global $wpdb; $table = self::table();
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE lead_id = %d AND attachment_id = %d LIMIT 1", (int) $lead_id, (int) $attachment_id));
    }

    public static function can_upload($lead_id) { return IDLM_Design_Revisions::can_edit(IDLM_Design_Handoffs::for_lead($lead_id)); }

    /** Which saved milestone a payment check looks at: advance, the one after it, or the final one. Pure. */
    public static function payment_milestone($choice, $milestones) {
        $milestones = array_values((array) $milestones);
        if (!$milestones || !in_array($choice, array('advance', 'second', 'final'), true)) { return null; }
        $last = count($milestones) - 1; $advance = 0;
        foreach ($milestones as $index => $milestone) { if (!empty($milestone['advance'])) { $advance = $index; } }
        if ('advance' === $choice) { return $advance; }
        return 'second' === $choice ? min($advance + 1, $last) : $last;
    }

    /** A payment with evidence is enough: on the milestone, or recorded during this stage. A shortfall only shows. */
    private static function payment_item($row, $choice, $context = null) {
        $balance = $context ? $context['balance'] : IDLM_Design_Handoffs::balance($row->lead_id);
        $base = array('key' => 'payment', 'type' => 'payment');
        if (!$balance || null === ($index = self::payment_milestone($choice, $balance['milestones'] ?? array()))) { return $base + array('label' => 'Payment', 'met' => false, 'detail' => 'This project has no payment schedule yet.', 'evidence' => null); }
        $milestone = array_values($balance['milestones'])[$index]; $receipt = null;
        // A receipt taken after entering this stage. A correction to an older one does not count.
        foreach ($context ? $context['receipts'] : IDLM_Design_Handoffs::current_receipts($row->lead_id) as $current) { if ((int) $current->confirmation_id > (int) ($row->stage_receipt_mark ?? 0)) { $receipt = $current; } }
        $met = (float) $milestone['received'] > 0 || null !== $receipt;
        $symbol = 'INR' === ($balance['currency'] ?? '') ? '₹' : (($balance['currency'] ?? '') . ' ');
        $detail = sprintf('%s%s received of %s%s expected.', $symbol, number_format((float) $milestone['received'], 2), $symbol, number_format((float) $milestone['amount'], 2)) . ((float) $milestone['pending'] > 0 && $met ? ' The rest stays pending.' : '');
        return $base + array('label' => 'Payment: ' . $milestone['name'], 'met' => $met, 'detail' => $detail, 'evidence' => array('milestone' => $milestone['key'] ?? '', 'receipt_version_id' => $receipt ? (int) $receipt->id : 0, 'received' => $milestone['received'], 'expected' => $milestone['amount']));
    }

    /** Everything needed before leaving a stage, evaluated from current records. */
    public static function checklist($row, $stage = null, $context = null) {
        $stage = $stage ?: self::stage_of($row); $items = array(); $slots = self::slots(); $current = array(); $stages = self::stages();
        if (!isset($stages[$stage])) { return $items; }
        $requires = $stages[$stage]['requires'];
        foreach ($context ? $context['documents'] : self::documents($row->id) as $document) { if (!isset($current[$document->slot_key])) { $current[$document->slot_key] = $document; } }
        if ($requires['designer']) { $items[] = array('key' => 'designer', 'type' => 'designer', 'label' => 'Designer assigned', 'met' => (int) $row->designer_id > 0, 'detail' => '', 'evidence' => array('designer_id' => (int) $row->designer_id)); }
        if ('' !== $requires['payment']) { $items[] = self::payment_item($row, $requires['payment'], $context); }
        if ($requires['tentative_quote']) { $items[] = array('key' => 'tentative_quote', 'type' => 'revision', 'label' => 'Tentative quote', 'met' => (int) $row->design_revision_id > 0, 'detail' => 'The latest saved design revision counts.', 'evidence' => array('revision_id' => (int) $row->design_revision_id)); }
        if ($requires['final_quote']) { $items[] = array('key' => 'final_quote', 'type' => 'revision', 'label' => 'Final quote', 'met' => (int) $row->agreed_revision_id > 0, 'detail' => 'The revision marked Customer approved.', 'evidence' => array('revision_id' => (int) $row->agreed_revision_id)); }
        foreach ($stages[$stage]['slots'] as $slot) {
            if (!isset($slots[$slot])) { continue; }
            $document = $current[$slot] ?? null;
            $items[] = array('key' => $slot, 'type' => 'document', 'label' => $slots[$slot]['label'], 'met' => (bool) $document, 'detail' => '', 'document' => $document, 'evidence' => $document ? array('document_version_id' => (int) $document->id) : null);
        }
        return $items;
    }

    /** Checklist data for many projects in three queries, keyed by lead. */
    public static function contexts($rows) {
        global $wpdb; $contexts = array(); $leads = array(); $handoffs = array(); $p = $wpdb->prefix;
        $revisions = array();
        foreach ($rows as $row) { $leads[] = (int) $row->lead_id; $handoffs[] = (int) $row->id; $contexts[(int) $row->lead_id] = array('documents' => array(), 'balance' => null, 'receipts' => array(), 'unreviewed' => false); if ((int) ($row->design_revision_id ?? 0)) { $revisions[(int) $row->design_revision_id] = (int) $row->lead_id; } }
        if (!$leads) { return $contexts; }
        $by_handoff = array_combine($handoffs, $leads); $in_leads = implode(',', array_fill(0, count($leads), '%d')); $in_handoffs = implode(',', array_fill(0, count($handoffs), '%d')); $table = self::table();
        foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE handoff_id IN ($in_handoffs) AND state = 'current' ORDER BY id DESC", array_merge($handoffs))) as $document) { $contexts[$by_handoff[(int) $document->handoff_id]]['documents'][] = $document; }
        foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT a.lead_id, a.snapshot FROM {$p}idlm_lead_payment_allocation_sets a INNER JOIN (SELECT lead_id, MAX(id) AS latest FROM {$p}idlm_lead_payment_allocation_sets WHERE lead_id IN ($in_leads) GROUP BY lead_id) m ON m.latest = a.id", $leads)) as $set) { $balance = json_decode($set->snapshot, true); $contexts[(int) $set->lead_id]['balance'] = is_array($balance) ? $balance : null; }
        foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT v.* FROM {$p}idlm_lead_payment_confirmations c INNER JOIN {$p}idlm_lead_payment_versions v ON v.id = c.current_version_id AND v.lead_id = c.lead_id WHERE c.lead_id IN ($in_leads) AND v.status <> 'revoked' ORDER BY c.id", $leads)) as $receipt) { $contexts[(int) $receipt->lead_id]['receipts'][] = $receipt; }
        // The latest design revision still waiting for a manager's review.
        if ($revisions) {
            $ids = array_keys($revisions);
            foreach ((array) $wpdb->get_col($wpdb->prepare("SELECT revision_id FROM {$p}idlm_design_revisions WHERE revision_id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ') AND reviewed_at IS NULL AND approved_at IS NULL', $ids)) as $id) { $contexts[$revisions[(int) $id]]['unreviewed'] = true; }
        }
        return $contexts;
    }

    public static function unmet($items) {
        return array_values(array_filter($items, static function($item) { return empty($item['met']); }));
    }

    /** Earlier stages whose evidence no longer holds, for example an archived drawing or revoked payment. */
    public static function review_flags($row, $context = null) {
        $flags = array(); $stages = self::stages(); $now = self::stage_of($row);
        foreach ($stages as $key => $stage) {
            if ($key === $now) { break; }
            if ($stage['archived']) { continue; }
            foreach (self::unmet(self::checklist($row, $key, $context)) as $item) { $flags[] = $stage['label'] . ': ' . $item['label']; }
        }
        return $flags;
    }

    /** Changes whenever a receipt or a current document changes, so a cleared flag comes back on any later change. */
    public static function review_mark($row, $context = null) {
        $receipts = array_map(static function($receipt) { return (int) $receipt->id; }, $context ? $context['receipts'] : IDLM_Design_Handoffs::current_receipts($row->lead_id));
        $documents = array_map(static function($document) { return (int) $document->id; }, $context ? $context['documents'] : self::documents($row->id));
        sort($receipts); sort($documents);
        return md5(implode(',', $receipts) . '|' . implode(',', $documents));
    }

    /** Review flags a manager has not cleared since the last payment or document change. */
    public static function open_flags($row, $context = null) {
        $flags = self::review_flags($row, $context);
        $cleared = json_decode((string) ($row->flags_cleared ?? ''), true);
        if (!$flags || !is_array($cleared) || !hash_equals((string) ($cleared['mark'] ?? ''), self::review_mark($row, $context))) { return $flags; }
        return array_values(array_diff($flags, (array) ($cleared['flags'] ?? array())));
    }

    /**
     * The Design Manager accepts the current flags with a note, for example that the customer
     * pays at the next stage. Nothing moves; the note joins the history. Any later payment or
     * document change shows the flags again.
     */
    public static function clear_flags($handoff_id, $note) {
        $note = IDLM_Design_Handoffs::required_text($note, 'A note');
        if (is_wp_error($note)) { return $note; }
        return self::locked($handoff_id, array(__CLASS__, 'can_manage'), static function($row, $lead, $table) use ($note) {
            global $wpdb; $now = current_time('mysql', true);
            $open = self::open_flags($row);
            if (!$open) { return self::error('flags', 'There is nothing to clear on this project.'); }
            $all = self::review_flags($row);
            IDLM_Lead_Transaction::required($wpdb->update($table, array('flags_cleared' => wp_json_encode(array('mark' => self::review_mark($row), 'flags' => $all, 'by' => get_current_user_id(), 'at' => $now)), 'updated_at' => $now), array('id' => $row->id)));
            self::event($row, 'review_cleared', $note, array('flags' => $open));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Design review flag cleared: ' . implode(', ', $open) . '. Note: ' . $note, get_current_user_id()));
            return true;
        });
    }

    private static function event($row, $type, $reason, $details) {
        global $wpdb;
        IDLM_Lead_Transaction::required(1 === $wpdb->insert($wpdb->prefix . 'idlm_design_handoff_events', array('handoff_id' => $row->id, 'lead_id' => $row->lead_id, 'version_no' => $row->version_no, 'event_type' => $type, 'actor_id' => get_current_user_id(), 'design_manager_id' => $row->design_manager_id, 'designer_after' => $row->designer_id, 'reason' => (string) $reason, 'details' => wp_json_encode($details), 'occurred_at' => current_time('mysql', true))));
        IDLM_Design_Handoffs::publish_event((int) $wpdb->insert_id, (int) $row->id, (int) $row->lead_id, $type);
    }

    /** Lead lock, refreshed grants, handoff lock, then the operation. */
    private static function locked($handoff_id, $can, $operation) {
        $known = IDLM_Design_Handoffs::find($handoff_id);
        if (!$known) { return self::error('missing', 'This project is no longer available.'); }
        $result = null;
        $transaction = IDLM_Lead_Transaction::run(static function() use ($handoff_id, $known, $can, $operation, &$result) {
            global $wpdb; $table = $wpdb->prefix . 'idlm_design_handoffs';
            $lead = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT id, stage FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", $known->lead_id)));
            $actor = get_current_user_id(); IDLM_Lead_Access_Policy::is_assignable_rep($actor, 'any', true); wp_set_current_user(0); wp_set_current_user($actor);
            $row = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d FOR UPDATE", $handoff_id)));
            if (!$lead || !$row || IDLM_Design_Handoffs::STATUS_ACCEPTED !== $row->status) { $result = self::error('state', 'This project has changed. Reload it.'); return true; }
            if (!call_user_func($can, $row)) { $result = self::error('denied', 'You do not have permission to do this on this project.'); return true; }
            $result = call_user_func($operation, $row, $lead, $table);
            return true;
        });
        return is_wp_error($transaction) ? $transaction : $result;
    }
    private static function can_work($row) { return IDLM_Design_Revisions::can_edit($row); }
    private static function can_manage($row) { return IDLM_Design_Handoffs::can_decide($row); }

    private static function evidence($items) {
        $evidence = array();
        foreach ($items as $item) { $evidence[$item['key']] = $item['evidence'] ?? null; }
        return $evidence;
    }

    private static function require_checklist($row, $stage) {
        $items = self::checklist($row, $stage); $missing = self::unmet($items);
        if (!$missing) { return $items; }
        return self::error('checklist', 'Still needed: ' . implode(', ', array_column($missing, 'label')) . '.');
    }

    /** $expected stops a second click or stale board from moving a project twice. */
    public static function advance($handoff_id, $expected = '') {
        return self::locked($handoff_id, array(__CLASS__, 'can_work'), static function($row, $lead, $table) use ($expected) {
            if ('' !== (string) $expected && self::stage_of($row) !== $expected) { return self::error('stale', 'This project has already moved. Reload the page.'); }
            global $wpdb;
            if ('Won' !== $lead->stage) { return self::error('won', 'This lead is no longer Won. Review it with sales.'); }
            if ($row->hold_at) { return self::error('hold', 'Resume this project before moving it.'); }
            $from = self::stage_of($row); $to = self::neighbour($from, 1);
            if (!$to) { return self::error('last', 'This is the last stage. Mark the project complete instead.'); }
            $items = self::require_checklist($row, $from);
            if (is_wp_error($items)) { return $items; }
            return self::move($row, $table, $from, $to, 'stage_changed', '', self::evidence($items));
        });
    }

    public static function move_back($handoff_id, $reason) {
        $reason = IDLM_Design_Handoffs::required_text($reason, 'A reason');
        if (is_wp_error($reason)) { return $reason; }
        return self::locked($handoff_id, array(__CLASS__, 'can_manage'), static function($row, $lead, $table) use ($reason) {
            $from = self::stage_of($row); $to = self::neighbour($from, -1);
            if (!$to) { return self::error('first', 'This is already the first stage.'); }
            return self::move($row, $table, $from, $to, 'stage_moved_back', $reason, array());
        });
    }

    private static function move($row, $table, $from, $to, $type, $reason, $evidence) {
        global $wpdb; $now = current_time('mysql', true); $stages = self::stages();
        IDLM_Lead_Transaction::required($wpdb->update($table, array('stage_key' => $to, 'stage_entered_at' => $now, 'stage_receipt_mark' => self::receipt_mark($row->lead_id), 'updated_at' => $now), array('id' => $row->id)));
        // Labels are copied so renaming a stage never rewrites history.
        self::event($row, $type, $reason, array('from' => $from, 'to' => $to, 'from_label' => $stages[$from]['label'], 'to_label' => $stages[$to]['label'], 'evidence' => $evidence));
        IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Design stage: ' . $stages[$from]['label'] . ' to ' . $stages[$to]['label'] . ($reason ? '. Reason: ' . $reason : '.'), get_current_user_id()));
        return true;
    }

    public static function hold($handoff_id, $reason, $resume_date = '') {
        $reason = IDLM_Design_Handoffs::required_text($reason, 'A reason');
        if (is_wp_error($reason)) { return $reason; }
        $resume = '' === (string) $resume_date ? null : IDLM_Design_Handoffs::clean_target($resume_date);
        if (is_wp_error($resume)) { return self::error('resume', 'Enter a valid expected resume date, or leave it empty.'); }
        return self::locked($handoff_id, array(__CLASS__, 'can_work'), static function($row, $lead, $table) use ($reason, $resume) {
            global $wpdb; $now = current_time('mysql', true);
            if ($row->hold_at) { return self::error('hold', 'This project is already on hold.'); }
            IDLM_Lead_Transaction::required($wpdb->update($table, array('hold_at' => $now, 'hold_by' => get_current_user_id(), 'hold_reason' => $reason, 'hold_resume_date' => $resume, 'updated_at' => $now), array('id' => $row->id)));
            self::event($row, 'hold_started', $reason, array('stage' => self::stage_of($row), 'expected_resume' => $resume));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Design project on hold: ' . $reason, get_current_user_id()));
            return true;
        });
    }

    public static function resume($handoff_id) {
        return self::locked($handoff_id, array(__CLASS__, 'can_work'), static function($row, $lead, $table) {
            global $wpdb; $now = current_time('mysql', true);
            if (!$row->hold_at) { return self::error('hold', 'This project is not on hold.'); }
            IDLM_Lead_Transaction::required($wpdb->update($table, array('hold_at' => null, 'hold_by' => 0, 'hold_reason' => '', 'hold_resume_date' => null, 'updated_at' => $now), array('id' => $row->id)));
            self::event($row, 'hold_ended', '', array('stage' => self::stage_of($row), 'held_since' => $row->hold_at));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Design project resumed.', get_current_user_id()));
            return true;
        });
    }

    public static function complete($handoff_id) {
        return self::locked($handoff_id, array(__CLASS__, 'can_manage'), static function($row, $lead, $table) {
            global $wpdb; $now = current_time('mysql', true);
            if ('Won' !== $lead->stage) { return self::error('won', 'This lead is no longer Won. Review it with sales.'); }
            if (null !== self::neighbour(self::stage_of($row), 1)) { return self::error('last', 'Only a project in the last stage can be completed.'); }
            if ($row->hold_at) { return self::error('hold', 'Resume this project before completing it.'); }
            if ($row->completed_at) { return self::error('state', 'This project is already complete.'); }
            $items = self::require_checklist($row, self::stage_of($row));
            if (is_wp_error($items)) { return $items; }
            IDLM_Lead_Transaction::required($wpdb->update($table, array('completed_at' => $now, 'completed_by' => get_current_user_id(), 'updated_at' => $now), array('id' => $row->id)));
            self::event($row, 'completed', '', array('evidence' => self::evidence($items)));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Design project completed.', get_current_user_id()));
            return true;
        });
    }

    public static function reopen($handoff_id, $reason) {
        $reason = IDLM_Design_Handoffs::required_text($reason, 'A reason');
        if (is_wp_error($reason)) { return $reason; }
        return self::locked($handoff_id, array(__CLASS__, 'can_manage'), static function($row, $lead, $table) use ($reason) {
            global $wpdb; $now = current_time('mysql', true);
            if (!$row->completed_at) { return self::error('state', 'This project is not complete.'); }
            IDLM_Lead_Transaction::required($wpdb->update($table, array('completed_at' => null, 'completed_by' => 0, 'updated_at' => $now), array('id' => $row->id)));
            self::event($row, 'reopened', $reason, array('completed_at' => $row->completed_at));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Design project reopened: ' . $reason, get_current_user_id()));
            return true;
        });
    }

    /** Attach an uploaded private file as a new version. Older versions stay. */
    public static function add_document($handoff_id, $slot_key, $title, $attachment_id) {
        $slots = self::slots(); $is_slot = isset($slots[$slot_key]);
        if (!self::allowed_extensions($slot_key)) { return self::error('slot', 'Choose a valid document type.'); }
        $title = $is_slot ? $slots[$slot_key]['label'] : trim(sanitize_text_field((string) $title));
        if ('' === $title || strlen($title) > 150) { return self::error('title', 'Give this file a short title.'); }
        $key = $is_slot ? $slot_key : $slot_key . '-' . sanitize_title($title);
        return self::locked($handoff_id, array(__CLASS__, 'can_work'), static function($row, $lead, $table) use ($slot_key, $title, $key, $attachment_id) {
            global $wpdb; $versions = self::table(); $files = $wpdb->prefix . 'idlm_private_files'; $now = current_time('mysql', true);
            $file = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT id, filename FROM $files WHERE attachment_id = %d AND lead_id = %d AND purpose = 'design' AND state IN ('uploaded','active') FOR UPDATE", (int) $attachment_id, $row->lead_id)));
            if (!$file) { return self::error('file', 'Upload the file again.'); }
            $extension = strtolower((string) pathinfo((string) $file->filename, PATHINFO_EXTENSION));
            if (!in_array($extension, self::allowed_extensions($slot_key), true)) { return self::error('type', 'This document must be one of: ' . strtoupper(implode(', ', self::allowed_extensions($slot_key))) . '.'); }
            $latest = (int) IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare("SELECT MAX(version_no) FROM $versions WHERE handoff_id = %d AND document_key = %s FOR UPDATE", $row->id, $key)));
            IDLM_Lead_Transaction::required(false !== $wpdb->update($versions, array('state' => self::STATE_SUPERSEDED), array('handoff_id' => $row->id, 'document_key' => $key, 'state' => self::STATE_CURRENT)));
            IDLM_Lead_Transaction::required(1 === $wpdb->insert($versions, array('handoff_id' => $row->id, 'lead_id' => $row->lead_id, 'document_key' => $key, 'slot_key' => $slot_key, 'title' => $title, 'version_no' => $latest + 1, 'attachment_id' => (int) $attachment_id, 'state' => self::STATE_CURRENT, 'uploaded_by' => get_current_user_id(), 'uploaded_at' => $now)));
            $version_id = (int) $wpdb->insert_id;
            IDLM_Lead_Transaction::required(false !== $wpdb->update($files, array('state' => 'active'), array('id' => $file->id)));
            self::event($row, 'document_added', '', array('document_version_id' => $version_id, 'slot' => $slot_key, 'version_no' => $latest + 1));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Design document added: ' . $title . ' (version ' . ($latest + 1) . ').', get_current_user_id()));
            return $version_id;
        });
    }

    /** Archive keeps the file and its history. An earlier stage that relied on it is flagged. */
    public static function archive_document($handoff_id, $version_id) {
        return self::locked($handoff_id, array(__CLASS__, 'can_manage'), static function($row, $lead, $table) use ($version_id) {
            global $wpdb; $versions = self::table(); $now = current_time('mysql', true);
            $version = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $versions WHERE id = %d AND handoff_id = %d FOR UPDATE", (int) $version_id, $row->id)));
            if (!$version || self::STATE_CURRENT !== $version->state) { return self::error('file', 'This file is no longer current.'); }
            IDLM_Lead_Transaction::required($wpdb->update($versions, array('state' => self::STATE_ARCHIVED, 'archived_by' => get_current_user_id(), 'archived_at' => $now), array('id' => $version->id)));
            self::event($row, 'document_archived', '', array('document_version_id' => (int) $version->id, 'slot' => $version->slot_key));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Design document archived: ' . $version->title . '.', get_current_user_id()));
            return true;
        });
    }

    /** Changes when anyone saves stages, so a form opened before that cannot overwrite it. */
    public static function fingerprint() { return md5((string) wp_json_encode(array(self::stages(), self::custom_documents(), self::custom_hold_reasons()))); }

    /** Pure, on unslashed text: one reason per line, cleaned. Standard reasons and repeats are dropped. Returns a list or WP_Error. */
    public static function parse_hold_reasons($text) {
        $reasons = array(); $seen = array_map('strtolower', array_merge(self::HOLD_REASONS, array(self::HOLD_OTHER)));
        foreach (preg_split('/\r\n|\r|\n/', is_string($text) ? $text : '') as $line) {
            $line = trim(sanitize_text_field($line));
            if ('' === $line || in_array(strtolower($line), $seen, true)) { continue; }
            if (strlen($line) > 80) { return self::error('hold_reason', 'Each hold reason can be up to 80 characters.'); }
            $reasons[] = $line; $seen[] = strtolower($line);
        }
        if (count($reasons) > self::MAX_HOLD_REASONS) { return self::error('hold_reason', 'Up to ' . self::MAX_HOLD_REASONS . ' hold reasons of your own.'); }
        return $reasons;
    }

    /** Unfinished projects sitting in a stage. A project with no stage yet counts in the start stage. */
    public static function projects_in($key) {
        global $wpdb; $table = $wpdb->prefix . 'idlm_design_handoffs';
        $match = self::first() === $key ? $wpdb->prepare("(h.stage_key = %s OR h.stage_key = '')", $key) : $wpdb->prepare('h.stage_key = %s', $key);
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table h WHERE h.status = %s AND h.completed_at IS NULL AND ", IDLM_Design_Handoffs::STATUS_ACCEPTED) . $match);
    }

    private static function document_in_use($key) {
        global $wpdb; $table = self::table();
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slot_key = %s LIMIT 1", $key));
    }

    /** Pure: a new stable key from a name, unique among $taken. */
    public static function new_key($prefix, $label, $max, $taken) {
        $base = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(function_exists('remove_accents') ? remove_accents((string) $label) : (string) $label)), '_');
        $base = substr('' === $base ? 'item' : $base, 0, $max - strlen($prefix) - 3);
        $key = $prefix . $base;
        for ($i = 2; in_array($key, $taken, true); $i++) { $key = $prefix . $base . '_' . $i; }
        return $key;
    }

    private static function text($value) { return is_string($value) ? trim(sanitize_text_field(wp_unslash($value))) : ''; }

    /**
     * Administrator only. Stages are added, renamed, reordered and removed; a removed stage is
     * archived, so history and finished projects keep its name. A stage with unfinished projects
     * cannot be removed. Saving never moves a project or rewrites a past event.
     */
    public static function save_settings($input, $documents_input = array(), $fingerprint = null, $hold_input = null) {
        if (!current_user_can('manage_options')) { return self::error('denied', 'Only an administrator can change design stages.'); }
        // A form without the field keeps the current reasons.
        $hold_reasons = null === $hold_input ? self::custom_hold_reasons() : self::parse_hold_reasons($hold_input);
        if (is_wp_error($hold_reasons)) { return $hold_reasons; }
        if (null !== $fingerprint && !hash_equals(self::fingerprint(), (string) $fingerprint)) { return self::error('stale', 'Design stages were changed in another window. Reload this page and make your changes again.'); }
        $stale = self::error('stale', 'This form is out of date. Reload this page and make your changes again.');
        // Documents first, so stages can require a document added in the same save.
        $current_documents = self::custom_documents(); $documents = array(); $seen = array(); $names = array();
        foreach (self::builtin_slots() as $slot) { $names[] = strtolower($slot['label']); }
        foreach (is_array($documents_input) ? $documents_input : array() as $row) {
            if (!is_array($row)) { continue; }
            $key = is_string($row['key'] ?? null) ? sanitize_key($row['key']) : ''; $label = self::text($row['label'] ?? ''); $type = is_string($row['type'] ?? null) ? sanitize_key($row['type']) : '';
            if ('' !== $key && !isset($current_documents[$key])) { return $stale; }
            if ('' !== $key) { $seen[$key] = true; }
            if (!empty($row['remove'])) {
                if ('' === $key) { continue; }
                if (self::document_in_use($key)) { return self::error('document_used', $current_documents[$key]['label'] . ' has uploaded files, so it stays. Untick it on each stage instead.'); }
                continue;
            }
            if ('' === $key && '' === $label) { continue; }
            if ('' === $label || strlen($label) > 80) { return self::error('document_label', 'Every document needs a name of up to 80 characters.'); }
            if (in_array(strtolower($label), $names, true)) { return self::error('document_label', 'Two documents cannot share the name ' . $label . '.'); }
            if (!isset(self::file_types()[$type])) { return self::error('document_type', 'Choose a file type for ' . $label . '.'); }
            $names[] = strtolower($label);
            $key = '' !== $key ? $key : self::new_key('doc_', $label, 40, array_merge(array_keys(self::builtin_slots()), array_keys($current_documents), array_keys($documents)));
            $documents[$key] = array('key' => $key, 'label' => $label, 'type' => $type);
        }
        if (array_diff_key($current_documents, $seen)) { return $stale; }
        if (count($documents) > self::MAX_DOCUMENTS) { return self::error('document_count', 'Up to ' . self::MAX_DOCUMENTS . ' added documents.'); }
        $known = array_merge(array_keys(self::builtin_slots()), array_keys($documents));
        // Stages, in the order chosen.
        $current = self::stages(); $rows = array();
        foreach (is_array($input) ? array_values($input) : array() as $index => $stage) {
            if (!is_array($stage)) { continue; }
            $key = is_string($stage['key'] ?? null) ? sanitize_key($stage['key']) : ''; $label = self::text($stage['label'] ?? '');
            if ('' !== $key && !isset($current[$key])) { return $stale; }
            // A blank new row, or a new row removed before saving, is simply not added.
            if ('' === $key && ('' === $label || !empty($stage['archived']))) { continue; }
            if ('' === $label || strlen($label) > 80) { return self::error('label', 'Every stage needs a name of up to 80 characters.'); }
            $position = is_string($stage['position'] ?? null) && is_numeric($stage['position']) ? (float) $stage['position'] : (float) ($index + 1);
            $rows[] = array('index' => $index, 'position' => $position, 'key' => $key, 'label' => $label, 'stage' => $stage);
        }
        $keys = array_filter(array_column($rows, 'key'));
        if (count($keys) !== count(array_unique($keys)) || array_diff(array_keys($current), $keys)) { return $stale; }
        usort($rows, static function($a, $b) { return $a['position'] === $b['position'] ? $a['index'] - $b['index'] : ($a['position'] < $b['position'] ? -1 : 1); });
        $list = array(); $labels = array(); $taken = array_keys($current); $active = 0;
        foreach ($rows as $row) {
            $stage = $row['stage']; $payment = is_string($stage['payment'] ?? null) ? sanitize_key($stage['payment']) : '';
            if (in_array(strtolower($row['label']), $labels, true)) { return self::error('label', 'Two stages cannot share the name ' . $row['label'] . '.'); }
            $labels[] = strtolower($row['label']);
            $key = '' !== $row['key'] ? $row['key'] : self::new_key('stage_', $row['label'], 30, $taken); $taken[] = $key;
            $archived = '' !== $row['key'] && !empty($stage['archived']);
            if ($archived && empty($current[$key]['archived']) && ($count = self::projects_in($key))) { return self::error('occupied', $row['label'] . ' has ' . $count . ($count === 1 ? ' unfinished project' : ' unfinished projects') . '. Move ' . ($count === 1 ? 'it' : 'them') . ' to another stage before removing it.'); }
            $slots = isset($stage['slots']) && is_array($stage['slots']) ? array_values(array_intersect($known, array_map('sanitize_key', wp_unslash($stage['slots'])))) : array();
            $list[] = array('key' => $key, 'label' => $row['label'], 'slots' => $slots, 'requires' => self::requires(array('designer' => !empty($stage['designer']), 'payment' => isset(self::PAYMENTS[$payment]) ? $payment : '', 'tentative_quote' => !empty($stage['tentative_quote']), 'final_quote' => !empty($stage['final_quote']))), 'archived' => $archived);
            if (!$archived) { $active++; }
        }
        if (!$active) { return self::error('empty', 'Keep at least one stage.'); }
        if (count($list) > self::MAX_STAGES) { return self::error('count', 'Up to ' . self::MAX_STAGES . ' stages, including removed ones.'); }
        update_option(self::OPTION, array('version' => 2, 'stages' => $list, 'documents' => array_values($documents), 'hold_reasons' => $hold_reasons), false);
        return true;
    }
}
