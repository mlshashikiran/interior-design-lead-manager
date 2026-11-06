<?php
/** Immutable payment snapshot and minor-unit allocation rules. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Lead_Payment_Ledger {
    public static function money($value) {
        return IDLM_Quotation_Calculator::decimal_units($value, 0, IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS);
    }
    public static function decimal($units) {
        return intdiv($units, 100) . '.' . str_pad((string) ($units % 100), 2, '0', STR_PAD_LEFT);
    }
    private static function error($message) { return new WP_Error('idlm_payment_invalid', $message); }

    /** Source positions are explicit identities for legacy terms without milestone IDs. */
    public static function schedule($revision, $advance_positions) {
        $terms = IDLM_Quotation_Document::read($revision);
        if (is_wp_error($terms) || !$terms || empty($terms['milestones'])) {
            return self::error('The selected revision has no usable saved payment timeline. Historic terms require an authorized resolution.');
        }
        $total = self::money($revision->grand_total ?? null);
        $currency = $revision->currency_code ?? '';
        if (is_wp_error($total) || !$total || !is_string($currency) || !preg_match('/^[A-Z]{3}$/D', $currency) || !is_array($advance_positions) || !$advance_positions) {
            return self::error('Select a positive quotation total, currency and explicit advance milestones.');
        }
        $positions = array();
        foreach ($advance_positions as $position) {
            if ((!is_int($position) && (!is_string($position) || !ctype_digit($position))) || !array_key_exists((int) $position, $terms['milestones'])) {
                return self::error('Select advance milestones from the saved timeline.');
            }
            $positions[(int) $position] = true;
        }
        $rows = array(); $scheduled = 0; $advance = 0;
        foreach ($terms['milestones'] as $position => $row) {
            $amount = self::money($row['amount']);
            if (is_wp_error($amount) || $amount > $total - $scheduled) { return self::error('Saved milestone amounts exceed the saved quotation total.'); }
            $scheduled += $amount;
            $is_advance = isset($positions[$position]);
            if ($is_advance) { $advance += $amount; }
            $rows[] = array('key' => 'position_' . $position, 'source_position' => $position, 'name' => $row['name'], 'percentage' => $row['percentage'], 'amount' => $row['amount'], 'advance' => $is_advance);
        }
        if (!$advance) { return self::error('The mapped advance must have a positive expected amount.'); }
        return array('currency' => $currency, 'basis_total' => self::decimal($total), 'milestones' => $rows, 'source_terms' => $terms);
    }

    /** Explicitly recover missing historic terms; never read current setup or rewrite the quote. */
    public static function legacy_schedule($revision, $resolution) {
        $known = IDLM_Quotation_Document::read($revision);
        if (!is_wp_error($known) && $known && !empty($known['milestones'])) { return self::error('This revision already has a saved payment timeline. Use that timeline.'); }
        if (!is_array($resolution) || true !== ($resolution['confirmed'] ?? false) || !is_string($resolution['source_reference'] ?? null) || !is_array($resolution['milestones'] ?? null) || count($resolution['milestones']) > 50) {
            return self::error('Confirm the agreed historic source and enter its payment milestones.');
        }
        $source = sanitize_textarea_field($resolution['source_reference']);
        if (strlen(trim($source)) < 5 || strlen($source) > 1000) { return self::error('Provide a meaningful historic document or agreement reference (up to 1,000 bytes).'); }
        $rows = array(); $advance = array();
        foreach ($resolution['milestones'] as $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null) || !is_string($row['percentage'] ?? null) || !in_array($row['advance'] ?? '', array('', '1'), true)) { return self::error('Enter valid milestone names, percentages and advance selections.'); }
            $name = sanitize_text_field($row['name']); $percentage = trim($row['percentage']);
            if ($name === '' && $percentage === '' && empty($row['advance'])) { continue; }
            $units = IDLM_Quotation_Calculator::decimal_units($percentage, 0, 10000);
            if (!$name || strlen($name) > 255 || is_wp_error($units) || !$units) { return self::error('Each historic milestone needs a name and a positive percentage.'); }
            if (($row['advance'] ?? '') === '1') { $advance[] = count($rows); }
            $rows[] = array('name' => $name, 'percentage' => self::decimal($units));
        }
        if (!$rows || !$advance) { return self::error('Enter at least one milestone and explicitly select the advance.'); }
        // Reuse existing rounding using only the entered historic percentages and saved total.
        $terms = IDLM_Quotation_Document::capture(array('payment_structure' => wp_json_encode($rows)), $revision->grand_total ?? null);
        if (is_wp_error($terms)) { return self::error('Historic percentages must total no more than 100% and use a valid saved quotation total.'); }
        $basis = clone $revision; $basis->document_terms = $terms;
        $snapshot = self::schedule($basis, $advance);
        if (is_wp_error($snapshot)) { return $snapshot; }
        $snapshot['source_terms'] = null;
        $snapshot['resolution'] = array('version' => 1, 'type' => 'explicit_legacy_recovery', 'source_reference' => $source,
            'original_terms_sha256' => hash('sha256', is_string($revision->document_terms ?? null) ? $revision->document_terms : ''),
            'amount_rule' => 'Entered agreed percentages against the exact saved revision total; quotation rounding.');
        return $snapshot;
    }

    /** Legacy deal with no quotation: entered percentages against the lead's saved Won amount. No quotation is read or written. */
    public static function won_schedule($won_amount, $currency, $milestones) {
        $total = self::money(is_string($won_amount) ? $won_amount : (string) $won_amount);
        if (is_wp_error($total) || !$total || !is_string($currency) || !preg_match('/^[A-Z]{3}$/D', $currency)) { return self::error('This lead needs a positive Won amount before a plan can be set from it.'); }
        $basis = (object) array('grand_total' => self::decimal($total), 'currency_code' => $currency, 'document_terms' => null);
        $snapshot = self::legacy_schedule($basis, array('confirmed' => true, 'source_reference' => 'Won amount on lead', 'milestones' => $milestones));
        if (is_wp_error($snapshot)) { return $snapshot; }
        $snapshot['resolution'] = array('version' => 1, 'type' => 'won_amount', 'source_reference' => 'Won amount on lead', 'amount_rule' => 'Entered percentages against the saved Won amount; quotation rounding.');
        return $snapshot;
    }

    public static function receipt($data) {
        if (!is_array($data)) { return self::error('Enter receipt details.'); }
        foreach (array('amount', 'received_date', 'method', 'reference', 'note', 'currency', 'duplicate_review_reason') as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) { return self::error('Receipt fields must be text values.'); }
        }
        $amount = self::money($data['amount'] ?? '');
        $date = $data['received_date'] ?? '';
        $parsed = is_string($date) ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;
        $method = sanitize_text_field($data['method'] ?? '');
        $reference = sanitize_text_field($data['reference'] ?? '');
        $note = sanitize_textarea_field($data['note'] ?? '');
        $review = sanitize_textarea_field($data['duplicate_review_reason'] ?? '');
        if (strlen($review) > 8000) { return self::error('Duplicate reference review is too long.'); }
        $attachment = $data['attachment_id'] ?? 0;
        if ((!is_int($attachment) && (!is_string($attachment) || !ctype_digit($attachment))) || (int) $attachment < 0) { return self::error('Invalid screenshot identity.'); }
        if (is_wp_error($amount) || !$amount || !$parsed || $parsed->format('Y-m-d') !== $date || !$method || strlen($method) > 80 || strlen($reference) > 255 || strlen($note) > 8000) {
            return self::error('A positive amount, valid received date and payment method are required.');
        }
        if (!$reference && !(int) $attachment) { return self::error('Every receipt requires a screenshot or URN/transaction reference. Both are allowed.'); }
        $currency = $data['currency'] ?? '';
        if (!preg_match('/^[A-Z]{3}$/D', $currency)) { return self::error('Select the schedule currency.'); }
        return array('amount' => self::decimal($amount), 'currency' => $currency, 'received_date' => $date, 'method' => $method, 'reference' => $reference,
            'normalized_reference' => strtolower(preg_replace('/\s+/u', '', $reference)), 'attachment_id' => (int) $attachment, 'note' => $note, 'duplicate_review_reason' => $review);
    }

    /** Distinct receipt identities; oldest pending milestone first, excess stays unallocated. */
    public static function balance($schedule, $receipts) {
        $total = self::money($schedule['basis_total']);
        if (is_wp_error($total)) { return self::error('Invalid schedule total.'); }
        $rows = $schedule['milestones']; $pending = array(); $scheduled = 0; $advance = 0;
        foreach ($rows as $i => $row) {
            $amount = self::money($row['amount']);
            if (is_wp_error($amount) || $amount > $total - $scheduled) { return self::error('Invalid milestone amount.'); }
            $pending[$i] = $amount; $scheduled += $amount;
            if ($row['advance']) { $advance += $amount; }
        }
        $received = 0; $seen = array(); $allocations = array(); $unallocated = 0;
        foreach ($receipts as $receipt) {
            $identity = (int) $receipt['confirmation_id'];
            if ($identity <= 0 || isset($seen[$identity])) { return self::error('A receipt identity may be counted only once.'); }
            $seen[$identity] = true;
            if ($receipt['currency'] !== $schedule['currency']) { return self::error('Receipt and schedule currencies must match.'); }
            if ('revoked' === $receipt['status']) { continue; }
            $amount = self::money($receipt['amount']);
            if (is_wp_error($amount) || $amount > IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS - $received) { return self::error('Received total exceeds supported limits.'); }
            $received += $amount; $left = $amount;
            foreach ($rows as $i => $row) {
                $allocated = min($pending[$i], $left);
                if ($allocated) { $allocations[] = array('receipt_version_id' => (int) $receipt['id'], 'milestone_key' => $row['key'], 'amount' => self::decimal($allocated)); }
                $pending[$i] -= $allocated; $left -= $allocated;
            }
            $unallocated += $left;
        }
        $advance_pending = 0;
        foreach ($rows as $i => &$row) {
            $row['pending'] = self::decimal($pending[$i]);
            $row['received'] = self::decimal(self::money($row['amount']) - $pending[$i]);
            if ($row['advance']) { $advance_pending += $pending[$i]; }
        }
        unset($row);
        return array('currency' => $schedule['currency'], 'basis_total' => self::decimal($total), 'total_received' => self::decimal($received),
            'total_outstanding' => self::decimal(max(0, $total - $received)), 'excess_credit' => self::decimal(max(0, $received - $total)),
            'unallocated_credit' => self::decimal($unallocated), 'unscheduled_balance' => self::decimal($total - $scheduled),
            'expected_advance' => self::decimal($advance), 'advance_received' => self::decimal($advance - $advance_pending), 'advance_pending' => self::decimal($advance_pending),
            'advance_status' => $advance_pending ? ($advance_pending === $advance ? 'Unpaid' : 'Partial') : 'Met',
            'milestones' => $rows, 'allocations' => $allocations);
    }
}
