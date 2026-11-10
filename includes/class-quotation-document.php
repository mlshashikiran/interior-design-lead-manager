<?php
/** Immutable contractual document settings for a saved revision. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Quotation_Document {
    const VERSION = 1;
    /** Format 2 is format 1 plus the closing terms the client accepted (1.105.6). */
    const ACCEPTED_VERSION = 2;
    const MAX_BYTES = 262144;
    const MAX_TEXT_BYTES = 32000;

    private static function error() {
        return new WP_Error('idlm_quote_document_terms', 'Quotation document terms are invalid or too large. Review Quotation Setup before saving.');
    }

    /**
     * Snapshot the footer and payment milestones; branding and paper layout remain live. The cover
     * (1.105.3) and the content after the quote (1.105.5) are read from current settings: their
     * keys stay for format v1 compatibility but are saved empty.
     */
    public static function capture($settings, $grand_total) {
        if (!is_array($settings)) { return self::error(); }
        foreach (array('cover_page_html','after_quote_html','pdf_footer','payment_structure') as $field) { if (isset($settings[$field]) && !is_string($settings[$field])) { return self::error(); } }
        $amount = IDLM_Quotation_Calculator::decimal_units($grand_total, 0, IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS);
        if (is_wp_error($amount)) { return self::error(); }
        $rows = json_decode((string) ($settings['payment_structure'] ?? '[]'), true);
        if (!is_array($rows) || count($rows) > 50) { return self::error(); }
        $terms = array('version' => self::VERSION,
            'cover_page_enabled' => 0,
            'cover_page_html' => '',
            'after_quote_enabled' => 0,
            'after_quote_html' => '',
            'pdf_footer' => sanitize_textarea_field($settings['pdf_footer'] ?? ''),
            'milestones' => array());
        foreach (array('cover_page_html','after_quote_html','pdf_footer') as $field) {
            if (strlen($terms[$field]) > self::MAX_TEXT_BYTES) { return self::error(); }
        }
        // The live content after the quote is not stored, but keep refusing oversized content.
        if (strlen(wp_kses_post($settings['after_quote_html'] ?? '')) > self::MAX_TEXT_BYTES) { return self::error(); }
        $total_percent = 0; $allocated = 0; $remainders = array();
        foreach ($rows as $row) {
            if (!is_array($row)) { return self::error(); }
            $name = sanitize_text_field($row['name'] ?? '');
            $percent = IDLM_Quotation_Calculator::decimal_units($row['percentage'] ?? 0, 0, 10000);
            if (is_wp_error($percent) || strlen($name) > 255) { return self::error(); }
            if ($name === '' || !$percent) { continue; }
            $total_percent += $percent;
            if ($total_percent > 10000) { return self::error(); }
            $cents = intdiv($amount, 10000) * $percent + intdiv(($amount % 10000) * $percent, 10000);
            $allocated += $cents;
            $remainders[count($terms['milestones'])] = ($amount % 10000) * $percent % 10000;
            $terms['milestones'][] = array('name' => $name, 'percentage' => number_format($percent / 100, 2, '.', ''), 'amount' => number_format($cents / 100, 2, '.', ''));
        }
        // Allocate rounding cents by largest remainder, with stable milestone order on ties.
        $target = intdiv($amount, 10000) * $total_percent + intdiv(($amount % 10000) * $total_percent + 5000, 10000);
        $order = array_keys($remainders);
        usort($order, static function($a, $b) use ($remainders) { return ($remainders[$b] <=> $remainders[$a]) ?: ($a <=> $b); });
        for ($index = 0; $index < $target - $allocated; $index++) {
            $row = $order[$index];
            $cents = IDLM_Quotation_Calculator::decimal_units($terms['milestones'][$row]['amount'], 0, IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS);
            $terms['milestones'][$row]['amount'] = number_format(($cents + 1) / 100, 2, '.', '');
        }
        $json = wp_json_encode($terms);
        return is_string($json) && strlen($json) <= self::MAX_BYTES ? $json : self::error();
    }

    /** Missing legacy content stays unknown; never substitute today's settings. */
    public static function read($revision) {
        $json = $revision->document_terms ?? null;
        if ($json === null || $json === '') { return null; }
        if (!is_string($json) || strlen($json) > self::MAX_BYTES) { return self::error(); }
        $terms = json_decode($json, true);
        $keys = array('version','cover_page_enabled','cover_page_html','after_quote_enabled','after_quote_html','pdf_footer','milestones');
        if (!is_array($terms) || !in_array($terms['version'] ?? null, array(self::VERSION, self::ACCEPTED_VERSION), true)) { return self::error(); }
        if (self::ACCEPTED_VERSION === $terms['version']) { $keys[] = 'accepted'; }
        if (array_diff($keys,array_keys($terms)) || array_diff(array_keys($terms),$keys) || !is_array($terms['milestones']) || count($terms['milestones']) > 50) { return self::error(); }
        if (isset($terms['accepted'])) {
            $accepted = $terms['accepted'];
            if (!is_array($accepted) || array_keys($accepted) !== array('at','source') || !is_string($accepted['at']) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $accepted['at']) || !in_array($accepted['source'], array('saved','settings'), true)) { return self::error(); }
        }
        foreach (array('cover_page_enabled','after_quote_enabled') as $field) { if (!in_array($terms[$field], array(0,1), true)) { return self::error(); } }
        foreach (array('cover_page_html','after_quote_html','pdf_footer') as $field) { if (!is_string($terms[$field]) || strlen($terms[$field]) > self::MAX_TEXT_BYTES) { return self::error(); } }
        $percentage_total = 0;
        foreach ($terms['milestones'] as $row) {
            if (!is_array($row) || array_keys($row) !== array('name','percentage','amount') || !is_string($row['name']) || strlen($row['name']) > 255 || !is_string($row['percentage']) || !is_string($row['amount'])) { return self::error(); }
            $percentage = IDLM_Quotation_Calculator::decimal_units($row['percentage'],0,10000);
            $amount = IDLM_Quotation_Calculator::decimal_units($row['amount'],0,IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS);
            if (is_wp_error($percentage) || is_wp_error($amount)) { return self::error(); }
            $percentage_total += $percentage;
            if ($percentage_total > 10000) { return self::error(); }
        }
        return $terms;
    }

    /**
     * Lock the closing terms the client accepted, once. A revision saved before 1.105.5 already
     * holds the closing it was shared with, so that copy is kept; otherwise current setup is copied.
     * Returns the new format-2 JSON, false when there is nothing to lock, or a WP_Error.
     */
    public static function accept_closing($revision, $settings) {
        $terms = self::read($revision);
        if (is_wp_error($terms)) { return $terms; }
        if (!is_array($terms) || isset($terms['accepted'])) { return false; }
        $saved = '' !== $terms['after_quote_html'];
        if (!$saved) {
            $html = wp_kses_post(is_string($settings['after_quote_html'] ?? null) ? $settings['after_quote_html'] : '');
            if (strlen($html) > self::MAX_TEXT_BYTES) { return self::error(); }
            $terms['after_quote_enabled'] = empty($settings['after_quote_enabled']) ? 0 : 1;
            $terms['after_quote_html'] = $html;
        }
        $terms['version'] = self::ACCEPTED_VERSION;
        $terms['accepted'] = array('at' => gmdate('Y-m-d H:i:s'), 'source' => $saved ? 'saved' : 'settings');
        $json = wp_json_encode($terms);
        return is_string($json) && strlen($json) <= self::MAX_BYTES ? $json : self::error();
    }

    /**
     * The closing to print from the saved terms, or null to use current setup. Accepted terms always
     * win. A finalised quote (current revision of a Won lead) not yet locked keeps the closing it
     * was saved with, when it has one.
     */
    public static function closing($terms, $finalised) {
        if (!is_array($terms)) { return null; }
        if (isset($terms['accepted']) || ($finalised && '' !== $terms['after_quote_html'])) {
            return array('enabled' => (int) $terms['after_quote_enabled'], 'html' => $terms['after_quote_html']);
        }
        return null;
    }
}
