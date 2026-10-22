<?php
/** Immutable contractual document settings for a saved revision. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Quotation_Document {
    const VERSION = 1;
    const MAX_BYTES = 262144;
    const MAX_TEXT_BYTES = 32000;

    private static function error() {
        return new WP_Error('idlm_quote_document_terms', 'Quotation document terms are invalid or too large. Review Quotation Setup before saving.');
    }

    /** Snapshot current contractual settings; branding and paper layout remain live. */
    public static function capture($settings, $grand_total) {
        if (!is_array($settings)) { return self::error(); }
        foreach (array('cover_page_html','after_quote_html','pdf_footer','payment_structure') as $field) { if (isset($settings[$field]) && !is_string($settings[$field])) { return self::error(); } }
        $amount = IDLM_Quotation_Calculator::decimal_units($grand_total, 0, IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS);
        if (is_wp_error($amount)) { return self::error(); }
        $rows = json_decode((string) ($settings['payment_structure'] ?? '[]'), true);
        if (!is_array($rows) || count($rows) > 50) { return self::error(); }
        $terms = array('version' => self::VERSION,
            'cover_page_enabled' => empty($settings['cover_page_enabled']) ? 0 : 1,
            'cover_page_html' => wp_kses_post($settings['cover_page_html'] ?? ''),
            'after_quote_enabled' => empty($settings['after_quote_enabled']) ? 0 : 1,
            'after_quote_html' => wp_kses_post($settings['after_quote_html'] ?? ''),
            'pdf_footer' => sanitize_textarea_field($settings['pdf_footer'] ?? ''),
            'milestones' => array());
        foreach (array('cover_page_html','after_quote_html','pdf_footer') as $field) {
            if (strlen($terms[$field]) > self::MAX_TEXT_BYTES) { return self::error(); }
        }
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
        if (!is_array($terms) || array_diff($keys,array_keys($terms)) || array_diff(array_keys($terms),$keys) || $terms['version'] !== self::VERSION || !is_array($terms['milestones']) || count($terms['milestones']) > 50) { return self::error(); }
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
}
