<?php
/**
 * Design-phase quotation revisions: same quote number and revision sequence, a
 * separate save path. A design revision never becomes the quotation's current
 * revision, because sales readers and Meta Purchase read that pointer, and it
 * never changes the lead's quote value or the payment schedule. Builder: Shashi.
 */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Design_Revisions {
    const SUFFIX = '-DES';
    private static $pending_events = array();

    private static function table() { global $wpdb; return $wpdb->prefix . 'idlm_design_revisions'; }
    private static function error($code, $message) { return new WP_Error('idlm_design_revision_' . $code, $message); }

    /** Side table, so the quotation tables and their backups stay unchanged. */
    public static function schema() {
        global $wpdb;
        $collate = $wpdb->get_charset_collate(); $table = self::table();
        return "CREATE TABLE $table (
            revision_id bigint(20) unsigned NOT NULL,
            handoff_id bigint(20) unsigned NOT NULL,
            lead_id bigint(20) unsigned NOT NULL,
            source_revision_id bigint(20) unsigned NOT NULL,
            designer_id bigint(20) unsigned NOT NULL DEFAULT 0,
            designer_name varchar(255) NOT NULL DEFAULT '',
            custom_lines text DEFAULT NULL,
            approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
            approved_at datetime DEFAULT NULL,
            approval_note text DEFAULT NULL,
            reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            reviewed_at datetime DEFAULT NULL,
            rate_changes text DEFAULT NULL,
            actor_id bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (revision_id),
            KEY handoff_id (handoff_id,revision_id),
            KEY lead_id (lead_id)
        ) ENGINE=InnoDB $collate;";
    }

    public static function meta($revision_id) {
        global $wpdb; $table = self::table();
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE revision_id = %d", (int) $revision_id));
    }

    public static function is_design_revision($revision_id) { return (bool) self::meta($revision_id); }

    /** Display name kept on the revision, so later settings cannot rename history. */
    public static function revision_name($quote_number, $revision_number) {
        return $quote_number . '-R' . str_pad((string) $revision_number, 2, '0', STR_PAD_LEFT) . self::SUFFIX;
    }

    /** Reassignment or a state change takes editing away immediately. */
    public static function can_edit($row) {
        if (!$row || IDLM_Design_Handoffs::STATUS_ACCEPTED !== $row->status || !empty($row->completed_at)) { return false; }
        if (IDLM_Design_Handoffs::can_decide($row)) { return true; }
        return IDLM_Design_Handoffs::can_receive() && (int) $row->designer_id === get_current_user_id();
    }

    /** Discounts: managers and administrators only. Designers may change line rates; each change is listed for review. */
    public static function can_change_pricing($row) { return IDLM_Design_Handoffs::can_decide($row); }

    /** Reviewed by a manager, or already Customer approved before reviews existed. */
    public static function is_reviewed($meta) { return $meta && (!empty($meta->reviewed_at) || !empty($meta->approved_at)); }

    /**
     * May this revision leave the system as a PDF, print or export? Sales revisions always may.
     * A design revision waits for a manager's review; the manager reviewing it may see it first.
     */
    public static function may_share($revision_id) {
        $meta = self::meta($revision_id);
        if (!$meta || self::is_reviewed($meta)) { return true; }
        return IDLM_Design_Handoffs::can_decide(IDLM_Design_Handoffs::find((int) $meta->handoff_id));
    }

    const WAITING_FOR_REVIEW = 'This design revision is waiting for the Design Manager to review it. It can be printed or shared once it is reviewed.';

    /** The revision a new save must build on. */
    public static function working_revision_id($row) { return (int) ($row->design_revision_id ?: $row->baseline_revision_id); }

    public static function history($handoff_id) {
        global $wpdb; $table = self::table(); $revisions = IDLM_Quotation_Repository::table('quote_revisions');
        return (array) $wpdb->get_results($wpdb->prepare("SELECT r.id, r.revision_name, r.grand_total, r.currency_code, r.revision_reason, r.created_at, m.designer_name, m.actor_id, m.custom_lines, m.rate_changes, m.reviewed_at, m.reviewed_by, m.approved_at, m.approved_by FROM $table m INNER JOIN $revisions r ON r.id = m.revision_id WHERE m.handoff_id = %d ORDER BY r.revision_number DESC LIMIT 100", (int) $handoff_id));
    }

    /** Pure: which discount settings differ from the source. Compared in hundredths. */
    public static function discount_changes($source, $posted) {
        $changes = array();
        foreach (array('wooden_discount_percent' => 10000, 'additional_discount_amount' => IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS) as $field => $maximum) {
            $before = IDLM_Quotation_Calculator::decimal_units($source->$field ?? 0, 0, $maximum, 'Discount');
            $after = IDLM_Quotation_Calculator::decimal_units($posted[$field] ?? 0, 0, $maximum, 'Discount');
            if (is_wp_error($before) || is_wp_error($after)) { return is_wp_error($after) ? $after : $before; }
            if ($before !== $after) { $changes[$field] = array('before' => number_format($before / 100, 2, '.', ''), 'after' => number_format($after / 100, 2, '.', '')); }
        }
        return $changes;
    }

    private static function line_key($item) {
        $item = (array) $item;
        if (!empty($item['catalogue_item_id'])) { return 'c' . (int) $item['catalogue_item_id']; }
        return 'n' . strtolower(trim((string) ($item['item_name'] ?? '')) . '|' . trim((string) ($item['item_code'] ?? '')));
    }

    /**
     * Pure: which lines carry a rate other than the source rate (known line) or the catalogue
     * rate (new catalogue line), and which are new custom lines with a rate of their own. Both
     * are allowed and listed for the manager's review.
     * Returns array('changed' => list of array(name, before, after), 'custom' => names).
     */
    public static function rate_review($items, $source_items, $catalogue_rates) {
        $known = array();
        foreach ((array) $source_items as $item) {
            if ('header' === (((array) $item)['row_type'] ?? 'item')) { continue; }
            $known[self::line_key($item)][] = number_format((float) ((array) $item)['unit_rate'], 2, '.', '');
        }
        $custom = array(); $changed = array();
        foreach ((array) $items as $item) {
            if ('header' === ($item['row_type'] ?? 'item')) { continue; }
            $rate = number_format((float) $item['unit_rate'], 2, '.', ''); $key = self::line_key($item);
            $allowed = $known[$key] ?? array();
            if (!empty($item['catalogue_item_id']) && isset($catalogue_rates[(int) $item['catalogue_item_id']])) { $allowed[] = number_format((float) $catalogue_rates[(int) $item['catalogue_item_id']], 2, '.', ''); }
            if (!$allowed && empty($item['catalogue_item_id'])) { $custom[] = $item['item_name'] ?: $item['description']; continue; }
            if (!in_array($rate, $allowed, true)) { $changed[] = array('name' => $item['item_name'] ?: $item['description'], 'before' => $allowed ? $allowed[0] : '', 'after' => $rate); }
        }
        return array('changed' => $changed, 'custom' => $custom);
    }

    private static function catalogue_rates($items) {
        global $wpdb; $table = IDLM_Quotation_Repository::table('quote_catalogue_items');
        $ids = array_values(array_unique(array_filter(array_map(static function($item) { return (int) ($item['catalogue_item_id'] ?? 0); }, (array) $items))));
        if (!$ids) { return array(); }
        $rates = array();
        foreach ((array) $wpdb->get_results($wpdb->prepare("SELECT id, default_rate FROM $table WHERE id IN (" . implode(',', array_fill(0, count($ids), '%d')) . ')', $ids)) as $row) { $rates[(int) $row->id] = $row->default_rate; }
        return $rates;
    }

    /** New milestone amounts from the source's saved percentages, never from Quotation Setup. */
    public static function terms_for_total($source, $grand_total) {
        $terms = IDLM_Quotation_Document::read($source);
        if (!is_array($terms)) { return $source->document_terms ?? null; }
        $rows = array();
        foreach ((array) $terms['milestones'] as $milestone) { $rows[] = array('name' => $milestone['name'], 'percentage' => $milestone['percentage']); }
        return IDLM_Quotation_Document::capture(array_merge($terms, array('payment_structure' => wp_json_encode($rows))), $grand_total);
    }

    /** Parse and check one submission. Shared by save and autosave, so neither can skip a rule. */
    private static function prepare($row, $source, $source_items, $post) {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Delegated to the bounded calculator, as in the sales save.
        $items = IDLM_Quotation_Calculator::parse_items(wp_unslash((array) ($post['items'] ?? array())));
        if (is_wp_error($items)) { return $items; }
        if (!array_filter($items, static function($item) { return 'item' === ($item['row_type'] ?? 'item'); })) { return self::error('items', 'Add at least one line item.'); }
        $sections = IDLM_Quotation_Calculator::parse_sections(wp_unslash($post['sections'] ?? array()));
        if (is_wp_error($sections)) { return $sections; }
        // The saved percentage carries over; today's Quotation Setup is not read.
        $totals = IDLM_Quotation_Calculator::calculate_totals($items, $post, array('soft_close_percent' => $source->soft_close_percent));
        if (is_wp_error($totals)) { return $totals; }
        $discounts = self::discount_changes($source, $post);
        if (is_wp_error($discounts)) { return $discounts; }
        if ($discounts && !self::can_change_pricing($row)) { return self::error('discount', 'Only a Design Manager or administrator can change a discount.'); }
        $review = self::rate_review($items, $source_items, self::catalogue_rates($items));
        $terms = self::terms_for_total($source, $totals['grand_total']);
        if (is_wp_error($terms)) { return $terms; }
        return array('items' => $items, 'sections' => $sections, 'totals' => $totals, 'discounts' => $discounts, 'custom' => $review['custom'], 'rate_changes' => $review['changed'], 'terms' => $terms);
    }

    private static function refresh_actor() {
        $user_id = get_current_user_id(); clean_user_cache($user_id);
        $roles = wp_roles(); wp_cache_delete($roles->role_key, 'options'); wp_cache_delete('alloptions', 'options'); $roles->for_site();
        wp_set_current_user(0); wp_set_current_user($user_id);
    }

    public static function source_items($revision_id) {
        global $wpdb; $table = IDLM_Quotation_Repository::table('quote_revision_items');
        return (array) $wpdb->get_results($wpdb->prepare("SELECT * FROM $table WHERE revision_id = %d", (int) $revision_id));
    }

    /** Save one new design revision. Returns array('revision_id', 'replayed') or WP_Error. */
    public static function save($handoff_id, $source_revision_id, $post, $request_key) {
        global $wpdb;
        $row = IDLM_Design_Handoffs::find($handoff_id);
        if (!self::can_edit($row)) { return self::error('denied', 'You cannot edit this design quotation.'); }
        if (!is_string($request_key) || !preg_match('/^[a-f0-9-]{36}$/i', $request_key)) { return self::error('request', 'Reload the page and try again.'); }
        $done = IDLM_Quotation_Repository::get_revision_by_save_request_key($request_key);
        if ($done) {
            $meta = self::meta($done->id);
            if ($meta && (int) $meta->handoff_id === (int) $row->id && (int) $meta->actor_id === get_current_user_id()) { return array('revision_id' => (int) $done->id, 'replayed' => true); }
            return self::error('request', 'Reload the page and try again.');
        }
        $reason = sanitize_text_field(wp_unslash($post['revision_reason'] ?? ''));
        if ('' === trim($reason)) { return self::error('reason', 'Enter a revision remark explaining what changed.'); }
        $discount_remark = sanitize_textarea_field(wp_unslash($post['discount_remark'] ?? ''));
        $source = IDLM_Quotation_Repository::get_revision((int) $source_revision_id);
        if (!$source || (int) $source->quote_id !== (int) $row->baseline_quote_id) { return self::error('source', 'This quotation revision is not part of the handoff.'); }
        $prepared = self::prepare($row, $source, self::source_items($source->id), $post);
        if (is_wp_error($prepared)) { return $prepared; }
        if ($prepared['discounts'] && strlen(trim($discount_remark)) < 5) { return self::error('discount_remark', 'Explain the discount change in the discount remark.'); }

        $transaction = IDLM_Quotation_Repository::begin_checked_transaction('save');
        if (is_wp_error($transaction)) { return $transaction; }
        $table = $wpdb->prefix . 'idlm_design_handoffs'; $committed = false;
        try {
            // Lock order everywhere: lead, quote, handoff.
            $lead = IDLM_Quotation_Repository::get_lead_snapshot((int) $row->lead_id, true);
            $quote = IDLM_Quotation_Repository::lock_quote((int) $row->baseline_quote_id);
            $locked = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d FOR UPDATE", (int) $row->id));
            self::refresh_actor();
            if (!$lead || !$quote || !$locked || !self::can_edit($locked)) { throw new DomainException('denied'); }
            if ('Won' !== $wpdb->get_var($wpdb->prepare("SELECT stage FROM {$wpdb->prefix}idlm_leads WHERE id = %d", (int) $row->lead_id))) { throw new DomainException('won'); }
            if (self::working_revision_id($locked) !== (int) $source->id) { throw new DomainException('stale'); }
            // Grants may have changed while waiting for the lock.
            if (!self::can_change_pricing($locked) && $prepared['discounts']) { throw new DomainException('denied'); }
            $number = IDLM_Quotation_Repository::next_revision_number($quote->id);
            $name = self::revision_name($quote->quote_number, $number);
            $now = current_time('mysql'); $actor = get_current_user_id();
            $designer_id = (int) $locked->designer_id; $designer = $designer_id ? get_userdata($designer_id) : false;
            $revision_id = IDLM_Quotation_Repository::insert_revision(array_merge(array(
                'quote_id' => $quote->id, 'revision_number' => $number, 'revision_name' => $name, 'status' => 'Draft', 'source_revision_id' => $source->id, 'revision_reason' => $reason,
                'currency_code' => $source->currency_code, 'document_terms' => $prepared['terms'], 'valid_until' => $source->valid_until, 'customer_intro' => $source->customer_intro,
                'customer_name' => $source->customer_name, 'customer_phone' => $source->customer_phone, 'customer_email' => $source->customer_email, 'project_name' => $source->project_name, 'property_location' => $source->property_location,
                'internal_notes' => sanitize_textarea_field(wp_unslash($post['internal_notes'] ?? '')), 'created_by' => $actor, 'created_at' => $now, 'updated_by' => $actor, 'updated_at' => $now, 'finalized_at' => $now, 'save_request_key' => $request_key,
            ), $prepared['totals']));
            if (!$revision_id) { throw new RuntimeException('revision'); }
            if (is_wp_error(IDLM_Quotation_Repository::replace_revision_contents($revision_id, $prepared['sections'], $prepared['items']))) { throw new RuntimeException('contents'); }
            if (false === IDLM_Quotation_Repository::insert_history($quote->id, $revision_id, 'Draft', 'Draft', $reason)) { throw new RuntimeException('history'); }
            if (1 !== $wpdb->insert(self::table(), array('revision_id' => $revision_id, 'handoff_id' => $locked->id, 'lead_id' => $locked->lead_id, 'source_revision_id' => $source->id, 'designer_id' => $designer_id, 'designer_name' => $designer ? $designer->display_name : '', 'custom_lines' => wp_json_encode($prepared['custom']), 'rate_changes' => wp_json_encode($prepared['rate_changes']), 'actor_id' => $actor, 'created_at' => $now)
                // A manager's own revision needs no second look.
                + (IDLM_Design_Handoffs::can_decide($locked) ? array('reviewed_by' => $actor, 'reviewed_at' => current_time('mysql', true)) : array()))) { throw new RuntimeException('meta'); }
            if (false === $wpdb->update($table, array('design_revision_id' => $revision_id, 'updated_at' => current_time('mysql', true)), array('id' => $locked->id))) { throw new RuntimeException('pointer'); }
            self::event($locked, 'design_revision', $reason, array('source_revision_id' => (int) $source->id, 'revision_id' => $revision_id, 'grand_total' => $prepared['totals']['grand_total'], 'custom_lines' => $prepared['custom'], 'rate_changes' => $prepared['rate_changes']));
            if ($prepared['discounts']) { self::event($locked, 'discount_changed', $discount_remark, array('source_revision_id' => (int) $source->id, 'revision_id' => $revision_id, 'changes' => $prepared['discounts'])); }
            if (1 !== $wpdb->insert($wpdb->prefix . 'idlm_lead_activity_log', array('lead_id' => $locked->lead_id, 'user_id' => $actor, 'activity_type' => 'design_handoff', 'details' => 'Design quotation ' . $name . ' saved: ' . $reason, 'created_at' => $now))) { throw new RuntimeException('activity'); }
            if (false === $wpdb->query('COMMIT')) { throw new RuntimeException('commit'); }
            $committed = true;
        } catch (DomainException $error) {
            $messages = array('denied' => 'You cannot edit this design quotation.', 'won' => 'This lead is no longer Won, so its design quotation is paused.', 'stale' => 'Someone saved a newer revision. Reload to see it before saving.');
            return self::error($error->getMessage(), $messages[$error->getMessage()]);
        } catch (Throwable $error) {
            return self::error('failed', 'Unable to save the design revision. Your changes were not saved. Please retry.');
        } finally {
            if (!$committed) { $wpdb->query('ROLLBACK'); self::$pending_events = array(); }
        }
        delete_transient(self::autosave_key($source->id));
        self::flush_events();
        return array('revision_id' => $revision_id, 'replayed' => false);
    }

    /**
     * The new schedule: the current schedule's percentages and advance mapping on
     * this revision's saved total. Milestones and receipts are never typed in here.
     */
    public static function approved_schedule($revision, $prior) {
        $rows = array(); $advance = array();
        if ($prior) {
            $snapshot = json_decode((string) $prior['snapshot'], true);
            foreach ((array) ($snapshot['milestones'] ?? array()) as $index => $milestone) {
                $rows[] = array('name' => $milestone['name'], 'percentage' => $milestone['percentage']);
                if (!empty($milestone['advance'])) { $advance[] = $index; }
            }
        } else {
            $terms = IDLM_Quotation_Document::read($revision);
            $milestones = is_array($terms) ? (array) $terms['milestones'] : array();
            foreach ($milestones as $milestone) { $rows[] = array('name' => $milestone['name'], 'percentage' => $milestone['percentage']); }
            $first = IDLM_Design_Handoffs::first_payable_position($milestones);
            if (null !== $first) { $advance[] = $first; }
        }
        if (!$rows || !$advance) { return self::error('terms', 'This quotation has no payment terms yet. Add them in Payments first.'); }
        $terms = IDLM_Quotation_Document::capture(array('payment_structure' => wp_json_encode($rows)), $revision->grand_total);
        if (is_wp_error($terms)) { return $terms; }
        $basis = clone $revision; $basis->document_terms = $terms;
        return IDLM_Lead_Payment_Ledger::schedule($basis, $advance);
    }

    /** What Customer approved would change, without saving anything. */
    public static function preview($row, $revision_id) {
        global $wpdb;
        $meta = self::meta($revision_id); $revision = $meta && (int) $meta->handoff_id === (int) $row->id ? IDLM_Quotation_Repository::get_revision($revision_id) : null;
        if (!$revision) { return self::error('source', 'This revision is not part of the handoff.'); }
        if (!self::is_reviewed($meta)) { return self::error('review', 'Review this revision before marking it Customer approved.'); }
        $prior = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}idlm_lead_payment_schedules WHERE lead_id = %d ORDER BY id DESC LIMIT 1", (int) $row->lead_id), ARRAY_A);
        $schedule = self::approved_schedule($revision, $prior);
        if (is_wp_error($schedule)) { return $schedule; }
        $receipts = array_map(static function($receipt) { return (array) $receipt; }, IDLM_Design_Handoffs::current_receipts($row->lead_id));
        $after = IDLM_Lead_Payment_Ledger::balance($schedule, $receipts);
        if (is_wp_error($after)) { return $after; }
        return array('revision' => $revision, 'before' => IDLM_Design_Handoffs::balance($row->lead_id), 'after' => $after);
    }

    /** Checked again under the lead lock, inside the schedule transaction. */
    public static function check_approval($lead_id, $revision_id) {
        global $wpdb; $table = $wpdb->prefix . 'idlm_design_handoffs';
        $row = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE lead_id = %d FOR UPDATE", (int) $lead_id)));
        if (!$row || IDLM_Design_Handoffs::STATUS_ACCEPTED !== $row->status || !IDLM_Design_Handoffs::can_decide($row)) { return self::error('denied', 'Only the Design Manager or an administrator can mark this.'); }
        $meta = self::meta($revision_id);
        if (!$meta || (int) $meta->handoff_id !== (int) $row->id) { return self::error('source', 'This revision is not part of the handoff.'); }
        if ((int) $row->agreed_revision_id === (int) $revision_id) { return self::error('already', 'This revision is already the customer-approved one.'); }
        if (!self::is_reviewed($meta)) { return self::error('review', 'Review this revision before marking it Customer approved.'); }
        return $row;
    }

    /** Mark a revision Customer approved. Returns array('replayed', 'note') or WP_Error. */
    public static function customer_approve($handoff_id, $revision_id, $note, $expected_version, $request_key) {
        $row = IDLM_Design_Handoffs::find($handoff_id);
        if (!$row || !IDLM_Design_Handoffs::can_decide($row)) { return self::error('denied', 'Only the Design Manager or an administrator can mark this.'); }
        $note = IDLM_Design_Handoffs::required_text($note, 'An approval note');
        if (is_wp_error($note)) { return self::error('note', 'Add a note saying how the customer approved this.'); }
        $revision_id = (int) $revision_id;
        $result = IDLM_Lead_Payments::publish_customer_approved((int) $row->lead_id, $revision_id, $note, $expected_version, $request_key, static function($locked, $revision, $schedule_id, $total) use ($revision_id, $note) {
            global $wpdb; $now = current_time('mysql', true); $actor = get_current_user_id();
            IDLM_Lead_Transaction::required($wpdb->update($wpdb->prefix . 'idlm_design_handoffs', array('agreed_revision_id' => $revision_id, 'agreed_total' => $total, 'agreed_by' => $actor, 'agreed_at' => $now, 'updated_at' => $now), array('id' => $locked->id)));
            IDLM_Lead_Transaction::required($wpdb->update(self::table(), array('approved_by' => $actor, 'approved_at' => $now, 'approval_note' => $note), array('revision_id' => $revision_id)));
            $before = (int) $locked->agreed_revision_id ? $locked->agreed_total : $locked->baseline_total;
            self::event($locked, 'customer_approved', $note, array('revision_id' => $revision_id, 'schedule_id' => $schedule_id, 'total_before' => $before, 'total_after' => $total));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($locked->lead_id, 'design_handoff', 'Customer approved ' . $revision->revision_name . ': ' . $note, $actor));
        });
        if (is_wp_error($result)) { return $result; }
        return array('replayed' => !empty($result['replayed']), 'note' => $result['replayed'] ? '' : self::move_handoff_advance($row, $result, $request_key));
    }

    /** An advance kept only on the handoff joins the ledger once a schedule exists. */
    private static function move_handoff_advance($row, $published, $request_key) {
        global $wpdb;
        if ((int) $row->receipt_confirmation_id || (float) $row->advance_amount <= 0 || IDLM_Design_Handoffs::current_receipts($row->lead_id)) { return ''; }
        $data = array('amount' => (string) $row->advance_amount, 'currency' => (string) $row->advance_currency, 'received_date' => (string) $row->advance_date, 'method' => (string) $row->advance_method, 'reference' => (string) $row->advance_reference, 'attachment_id' => (int) $row->advance_attachment_id);
        $recorded = IDLM_Lead_Payments::record((int) $row->lead_id, $data, (int) $published['event']['id'], substr(hash('sha256', $request_key . '|advance'), 0, 40));
        if (is_wp_error($recorded)) { return 'The advance on this handoff could not be added to Payments: ' . $recorded->get_error_message(); }
        $wpdb->update($wpdb->prefix . 'idlm_design_handoffs', array('receipt_confirmation_id' => (int) $recorded['event']['confirmation_id']), array('id' => $row->id));
        return '';
    }

    public static function handle_approve() {
        check_admin_referer('idlm_design_quote_approve');
        $handoff_id = absint($_POST['handoff_id'] ?? 0); $revision_id = absint($_POST['approve_revision_id'] ?? 0);
        $row = IDLM_Design_Handoffs::find($handoff_id);
        if (!$row || !IDLM_Design_Handoffs::can_decide($row)) { wp_die('Only the Design Manager or an administrator can mark this.', '', array('response' => 403)); }
        $note = wp_unslash($_POST['approval_note'] ?? ''); $version = absint($_POST['payment_version'] ?? 0); $key = sanitize_text_field(wp_unslash($_POST['request_key'] ?? ''));
        $result = IDLM_Recovery_Guard::run(static function() use ($handoff_id, $revision_id, $note, $version, $key) { return self::customer_approve($handoff_id, $revision_id, $note, $version, $key); });
        if (!is_array($result)) { wp_die(esc_html(is_wp_error($result) ? $result->get_error_message() : 'The CRM is busy. Try again shortly.'), 'Not saved', array('response' => 422, 'back_link' => true)); }
        if ($result['note']) { wp_die(esc_html('Customer approval was saved. ' . $result['note']), 'Check the advance', array('response' => 200, 'back_link' => true)); }
        wp_safe_redirect(add_query_arg(array('handoff' => $handoff_id, 'design_notice' => 'approved'), self::return_base())); exit;
    }

    /** The Design Manager or an administrator confirms a designer's revision may go to the customer. */
    public static function review($handoff_id, $revision_id) {
        $known = IDLM_Design_Handoffs::find($handoff_id);
        if (!$known || !IDLM_Design_Handoffs::can_decide($known)) { return self::error('denied', 'Only the Design Manager or an administrator can review this.'); }
        $result = null;
        $transaction = IDLM_Lead_Transaction::run(static function() use ($known, $revision_id, &$result) {
            global $wpdb; $table = $wpdb->prefix . 'idlm_design_handoffs';
            IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id = %d FOR UPDATE", (int) $known->lead_id)));
            $row = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d FOR UPDATE", (int) $known->id)));
            $meta = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE revision_id = %d FOR UPDATE', (int) $revision_id)));
            if (!$row || IDLM_Design_Handoffs::STATUS_ACCEPTED !== $row->status || !IDLM_Design_Handoffs::can_decide($row)) { $result = self::error('denied', 'Only the Design Manager or an administrator can review this.'); return true; }
            if (!$meta || (int) $meta->handoff_id !== (int) $row->id) { $result = self::error('source', 'This revision is not part of the handoff.'); return true; }
            if (self::is_reviewed($meta)) { $result = true; return true; }
            $now = current_time('mysql', true); $actor = get_current_user_id();
            IDLM_Lead_Transaction::required($wpdb->update(self::table(), array('reviewed_by' => $actor, 'reviewed_at' => $now), array('revision_id' => (int) $revision_id)));
            $name = (string) $wpdb->get_var($wpdb->prepare('SELECT revision_name FROM ' . IDLM_Quotation_Repository::table('quote_revisions') . ' WHERE id = %d', (int) $revision_id));
            self::event($row, 'design_revision_reviewed', '', array('revision_id' => (int) $revision_id));
            IDLM_Lead_Transaction::required(IDLM_DB::log_activity($row->lead_id, 'design_handoff', 'Design quotation ' . $name . ' reviewed.', $actor));
            $result = true; return true;
        });
        return is_wp_error($transaction) ? $transaction : $result;
    }

    public static function handle_review() {
        check_admin_referer('idlm_design_quote_review');
        $handoff_id = absint($_POST['handoff_id'] ?? 0); $revision_id = absint($_POST['revision_id'] ?? 0);
        if (!IDLM_Design_Handoffs::can_decide(IDLM_Design_Handoffs::find($handoff_id))) { wp_die('Only the Design Manager or an administrator can review this.', '', array('response' => 403)); }
        $result = IDLM_Recovery_Guard::run(static function() use ($handoff_id, $revision_id) { return self::review($handoff_id, $revision_id); });
        if (true !== $result) { wp_die(esc_html(is_wp_error($result) ? $result->get_error_message() : 'The CRM is busy. Try again shortly.'), 'Not saved', array('response' => 422, 'back_link' => true)); }
        wp_safe_redirect(add_query_arg(array('handoff' => $handoff_id, 'design_notice' => 'reviewed'), self::return_base())); exit;
    }

    /** A later payment recorded by the Design Manager. Same evidence rules as sales. */
    public static function handle_record_payment() {
        check_admin_referer('idlm_design_payment_record');
        $handoff_id = absint($_POST['handoff_id'] ?? 0); $row = IDLM_Design_Handoffs::find($handoff_id);
        if (!$row || !IDLM_Design_Handoffs::can_record_payment($row->lead_id)) { wp_die('Only the Design Manager or an administrator can record this.', '', array('response' => 403)); }
        $data = array('attachment_id' => 0, 'currency' => (string) (IDLM_Design_Handoffs::balance($row->lead_id)['currency'] ?? ''));
        foreach (array('amount', 'received_date', 'method', 'reference') as $field) { $data[$field] = sanitize_text_field(wp_unslash($_POST[$field] ?? '')); }
        $version = absint($_POST['payment_version'] ?? 0); $key = sanitize_text_field(wp_unslash($_POST['request_key'] ?? ''));
        $file = isset($_FILES['payment_screenshot']) && UPLOAD_ERR_NO_FILE !== (int) ($_FILES['payment_screenshot']['error'] ?? UPLOAD_ERR_NO_FILE) ? $_FILES['payment_screenshot'] : null;
        $result = IDLM_Recovery_Guard::run(static function() use ($row, $data, $version, $key, $file) {
            // Check the typed fields first, so a bad form stores no file. A chosen file counts as evidence.
            $checked = IDLM_Lead_Payment_Ledger::receipt(array_merge($data, array('attachment_id' => $file ? 1 : 0)));
            if (is_wp_error($checked)) { return $checked; }
            if ($file) {
                $upload = IDLM_Private_Files::upload($file, (int) $row->lead_id, 'payment');
                if (is_wp_error($upload)) { return $upload; }
                $data['attachment_id'] = (int) $upload['attachment_id'];
            }
            return IDLM_Lead_Payments::record((int) $row->lead_id, $data, $version, $key);
        });
        if (!is_array($result)) { wp_die(esc_html(is_wp_error($result) ? $result->get_error_message() : 'The CRM is busy. Try again shortly.'), 'Payment not saved', array('response' => 422, 'back_link' => true)); }
        wp_safe_redirect(add_query_arg(array('handoff' => $handoff_id, 'design_notice' => 'payment'), self::return_base())); exit;
    }

    private static function return_base() {
        $fallback = add_query_arg('idlm_view', IDLM_Design_Handoff_Controller::VIEW, IDLM_Admin_Shortcodes::workspace_url());
        return wp_validate_redirect(sanitize_text_field(wp_unslash($_POST['return_url'] ?? '')), $fallback);
    }

    private static function event($row, $type, $reason, $details) {
        global $wpdb;
        if (1 !== $wpdb->insert($wpdb->prefix . 'idlm_design_handoff_events', array('handoff_id' => $row->id, 'lead_id' => $row->lead_id, 'version_no' => $row->version_no, 'event_type' => $type, 'actor_id' => get_current_user_id(), 'design_manager_id' => $row->design_manager_id, 'designer_after' => $row->designer_id, 'reason' => (string) $reason, 'details' => wp_json_encode($details), 'occurred_at' => current_time('mysql', true)))) { throw new RuntimeException('event'); }
        // A quotation save owns its own transaction, so its events queue just after it commits.
        if (IDLM_Lead_Transaction::active()) { IDLM_Design_Handoffs::publish_event((int) $wpdb->insert_id, (int) $row->id, (int) $row->lead_id, $type); }
        else { self::$pending_events[] = array((int) $wpdb->insert_id, (int) $row->id, (int) $row->lead_id, $type); }
    }

    private static function flush_events() {
        $events = self::$pending_events; self::$pending_events = array();
        if (!$events) { return; }
        IDLM_Lead_Transaction::run(static function() use ($events) {
            foreach ($events as $event) { IDLM_Design_Handoffs::publish_event($event[0], $event[1], $event[2], $event[3]); }
            return true;
        });
    }

    public static function autosave_key($revision_id) { return 'idlm_design_autosave_' . get_current_user_id() . '_' . (int) $revision_id; }

    /** Per-user working copy. It runs the same rules as a save and writes no quotation row. */
    public static function ajax_autosave() {
        check_ajax_referer('idlm_design_quote_autosave', 'nonce');
        $row = IDLM_Design_Handoffs::find(absint($_POST['handoff_id'] ?? 0));
        if (!self::can_edit($row)) { wp_send_json_error(array('message' => 'You cannot edit this design quotation.'), 403); }
        $revision_id = self::working_revision_id($row);
        if (absint($_POST['revision_id'] ?? 0) !== $revision_id) { wp_send_json_error(array('message' => 'Someone saved a newer revision. Reload to see it.'), 409); }
        $source = IDLM_Quotation_Repository::get_revision($revision_id);
        $prepared = $source ? self::prepare($row, $source, self::source_items($revision_id), $_POST) : self::error('source', 'Reload the page.');
        if (is_wp_error($prepared)) { wp_send_json_error(array('message' => $prepared->get_error_message()), 422); }
        $copy = array('revision' => array_merge(array('internal_notes' => sanitize_textarea_field(wp_unslash($_POST['internal_notes'] ?? ''))), $prepared['totals']), 'sections' => $prepared['sections'], 'items' => $prepared['items'], 'saved_at' => time());
        $key = self::autosave_key($revision_id);
        if (!set_transient($key, $copy, 7 * DAY_IN_SECONDS) && false === get_transient($key)) { wp_send_json_error(array('message' => 'Unable to store the working copy.'), 500); }
        wp_send_json_success(array('message' => 'Working copy autosaved.', 'savedAt' => wp_date('H:i:s', $copy['saved_at']), 'grandTotal' => $prepared['totals']['grand_total']));
    }

    public static function ajax_discard() {
        check_ajax_referer('idlm_design_quote_autosave', 'nonce');
        $row = IDLM_Design_Handoffs::find(absint($_POST['handoff_id'] ?? 0));
        if (!self::can_edit($row)) { wp_send_json_error(array('message' => 'You cannot edit this design quotation.'), 403); }
        delete_transient(self::autosave_key(absint($_POST['revision_id'] ?? 0)));
        wp_send_json_success(array('message' => 'Working copy discarded.'));
    }

    public static function init() {
        add_action('admin_post_idlm_design_quote_save', array(__CLASS__, 'handle_save'));
        add_action('admin_post_idlm_design_quote_print', array(__CLASS__, 'handle_print'));
        add_action('admin_post_idlm_design_quote_approve', array(__CLASS__, 'handle_approve'));
        add_action('admin_post_idlm_design_quote_review', array(__CLASS__, 'handle_review'));
        add_action('admin_post_idlm_design_payment_record', array(__CLASS__, 'handle_record_payment'));
        add_action('wp_ajax_idlm_design_quote_autosave', array(__CLASS__, 'ajax_autosave'));
        add_action('wp_ajax_idlm_design_quote_discard', array(__CLASS__, 'ajax_discard'));
    }

    public static function handle_save() {
        check_admin_referer('idlm_design_quote_save');
        $handoff_id = absint($_POST['handoff_id'] ?? 0); $source_id = absint($_POST['revision_id'] ?? 0);
        $key = sanitize_text_field(wp_unslash($_POST['save_request_key'] ?? ''));
        if (!self::can_edit(IDLM_Design_Handoffs::find($handoff_id))) { wp_die('You cannot edit this design quotation.', '', array('response' => 403)); }
        $post = $_POST;
        $result = IDLM_Recovery_Guard::run(static function() use ($handoff_id, $source_id, $post, $key) { return self::save($handoff_id, $source_id, $post, $key); });
        $fallback = add_query_arg('idlm_view', IDLM_Design_Handoff_Controller::VIEW, IDLM_Admin_Shortcodes::workspace_url());
        $base = wp_validate_redirect(sanitize_text_field(wp_unslash($_POST['return_url'] ?? '')), $fallback);
        if (is_array($result)) { wp_safe_redirect(add_query_arg(array('handoff' => $handoff_id, 'design_notice' => 'revision'), $base)); exit; }
        $message = is_wp_error($result) ? $result->get_error_message() : 'The CRM is busy. Try again shortly.';
        // The autosaved working copy keeps the typed work; a save failure must not lose it.
        wp_die(esc_html($message), 'Design revision not saved', array('response' => 422, 'back_link' => true));
    }

    /** Print any revision of a handoff the viewer can see, without granting quotation access. */
    public static function handle_print() {
        $handoff_id = absint($_GET['handoff_id'] ?? 0); $revision_id = absint($_GET['revision_id'] ?? 0);
        check_admin_referer('idlm_design_quote_print_' . $handoff_id);
        $row = IDLM_Design_Handoffs::find($handoff_id);
        if (!IDLM_Design_Handoffs::can_see($row)) { wp_die('Quotation not found.', '', array('response' => 404)); }
        $meta = self::meta($revision_id);
        if ($revision_id !== (int) $row->baseline_revision_id && (!$meta || (int) $meta->handoff_id !== (int) $row->id)) { wp_die('Quotation not found.', '', array('response' => 404)); }
        if (!self::may_share($revision_id)) { wp_die(esc_html(self::WAITING_FOR_REVIEW), 'Waiting for review', array('response' => 403)); }
        $bundle = IDLM_Quotation_Repository::get_quote_bundle((int) $row->baseline_quote_id, $revision_id);
        if (!$bundle || !$bundle->revision) { wp_die('Quotation not found.', '', array('response' => 404)); }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The renderer escapes fields and applies the document allowlist.
        echo IDLM_Quotation_Renderer::render_print_document($bundle);
        exit;
    }

    public static function print_url($handoff_id, $revision_id) {
        return wp_nonce_url(admin_url('admin-post.php?action=idlm_design_quote_print&handoff_id=' . (int) $handoff_id . '&revision_id=' . (int) $revision_id), 'idlm_design_quote_print_' . (int) $handoff_id);
    }
}
