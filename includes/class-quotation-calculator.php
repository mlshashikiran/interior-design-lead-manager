<?php
/**
 * Deterministic quotation input normalization and totals calculation.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Quotation_Calculator {
    const MAX_AMOUNT_UNITS = 999999999999; // DECIMAL(12,2) lead projection, in cents.
    const MAX_MEASURE_UNITS = 99999999999; // DECIMAL(12,3), normalized to two decimals.
    const MAX_ROWS = 200;
    const MAX_SECTIONS = 50;

    /** Decimal text to integer hundredths, with half-up rounding and checked bounds. */
    public static function decimal_units($value, $minimum, $maximum, $label = 'Value') {
        if (is_float($value)) {
            if (!is_finite($value) || $value < 0 || $value > $maximum / 100) { return self::invalid($label); }
            $units = (int) round($value * 100, 0, PHP_ROUND_HALF_UP);
        } else {
            if (!is_int($value) && !is_string($value)) { return self::invalid($label); }
            $text = trim((string) $value);
            if ('' === $text) { $text = '0'; }
            if (strlen($text) > 128 || !preg_match('/^\+?(?:([0-9]+)(?:\.([0-9]*))?|\.([0-9]+))$/D', $text, $match)) { return self::invalid($label); }
            $whole = ltrim($match[1] ?? '', '0') ?: '0';
            $fraction = str_pad(($match[2] ?? '') ?: ($match[3] ?? ''), 3, '0');
            if (strlen($whole) > strlen((string) intdiv($maximum, 100))) { return self::invalid($label); }
            $units = (int) $whole * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);
        }
        if ($units > $maximum) { return self::invalid($label); }
        return max($minimum, $units);
    }

    private static function invalid($label) {
        return new WP_Error('idlm_quote_numeric', $label . ' must be a nonnegative decimal within the supported range; exponent notation is not accepted.');
    }

    /** Compatibility float helper; request paths use checked decimal_units instead. */
    public static function clean_decimal($value, $minimum = 0) {
        $minimum = is_numeric($minimum) && is_finite((float) $minimum) ? max(0, min(self::MAX_AMOUNT_UNITS / 100, (float) $minimum)) : 0;
        $units = self::decimal_units($value, (int) round($minimum * 100), self::MAX_AMOUNT_UNITS);
        return is_wp_error($units) ? (float) $minimum : (float) $units / 100;
    }

    /** Guard the product before multiplying; accepted products are exact safe integers. */
    private static function product($left, $right, $maximum, $label) {
        if ($right && $left > intdiv($maximum * 100, $right)) { return self::invalid($label); }
        $units = intdiv($left * $right + 50, 100);
        return $units > $maximum ? self::invalid($label) : $units;
    }

    private static function percentage($amount, $percent) {
        return intdiv($amount, 10000) * $percent + intdiv(($amount % 10000) * $percent + 5000, 10000);
    }

    /**
     * Normalize submitted quotation rows and calculate their line totals.
     *
     * The settings argument is retained for compatibility with the quotation
     * module's existing parsing API and for future calculation policy inputs.
     *
     * @param array $raw_items Unslashed item rows; HTTP callers remove request slashes once.
     * @param array $quote_settings Quotation settings.
     * @return array|WP_Error
     */
    public static function parse_items($raw_items, $quote_settings = array()) {
        unset($quote_settings);
        if (!is_array($raw_items) || count($raw_items) > self::MAX_ROWS) { return new WP_Error('idlm_quote_rows', 'A quotation supports at most 200 rows.'); }
        $items = array();
        foreach ((array) $raw_items as $index => $raw) {
            if (!is_array($raw)) { return self::invalid('Item row'); }
            $row_type = 'header' === sanitize_key($raw['row_type'] ?? 'item') ? 'header' : 'item';
            $description = sanitize_textarea_field($raw['description'] ?? '');
            if ('' === trim($description)) {
                continue;
            }
            if ('header' === $row_type) {
                $items[] = array(
                    'section_key' => sanitize_key($raw['section_key'] ?? 'general'),
                    'row_type' => 'header',
                    'classification_source' => 'default_nonwood',
                    'description' => $description,
                    'quantity' => 0,
                    'unit_rate' => 0,
                    'line_subtotal' => 0,
                    'line_total' => 0,
                    'sort_order' => absint($index),
                );
                continue;
            }
            $values = array();
            foreach (array('length'=>0, 'height'=>0, 'quantity'=>1, 'unit_rate'=>0) as $field=>$default) {
                $maximum = 'unit_rate' === $field ? self::MAX_AMOUNT_UNITS : self::MAX_MEASURE_UNITS;
                $values[$field] = self::decimal_units($raw[$field] ?? $default, 'quantity' === $field ? 1 : 0, $maximum, 'Row ' . (count($items) + 1) . ' ' . $field);
                if (is_wp_error($values[$field])) { return $values[$field]; }
            }
            $quantity = $values['length'] > 0 && $values['height'] > 0
                ? self::product($values['length'], $values['height'], self::MAX_MEASURE_UNITS, 'Billable area')
                : $values['quantity'];
            if (is_wp_error($quantity)) { return $quantity; }
            $quantity = max(1, $quantity);
            $subtotal = self::product($quantity, $values['unit_rate'], self::MAX_AMOUNT_UNITS, 'Line subtotal');
            if (is_wp_error($subtotal)) { return $subtotal; }
            $length = (float) $values['length'] / 100;
            $height = (float) $values['height'] / 100;
            $unit_rate = (float) $values['unit_rate'] / 100;
            $quantity = (float) $quantity / 100;
            $subtotal = (float) $subtotal / 100;
            $items[] = array(
                'section_key' => sanitize_key($raw['section_key'] ?? 'general'),
                'row_type' => 'item',
                'catalogue_item_id' => absint($raw['catalogue_item_id'] ?? 0),
                'is_wood' => '1' === (string) ($raw['is_wood'] ?? '0') ? 1 : 0,
                'classification_source' => isset($raw['is_wood']) && in_array((string) $raw['is_wood'], array('0', '1'), true) ? 'submitted' : 'default_nonwood',
                'category' => sanitize_text_field($raw['category'] ?? ''),
                'room_area' => sanitize_text_field($raw['room_area'] ?? ''),
                'item_code' => sanitize_text_field($raw['item_code'] ?? ''),
                'item_name' => sanitize_text_field($raw['item_name'] ?? ''),
                'description' => $description,
                'specification' => sanitize_textarea_field($raw['specification'] ?? ''),
                'length' => round($length, 2),
                'height' => round($height, 2),
                'quantity' => max(0.01, round($quantity, 2)),
                'unit' => sanitize_text_field($raw['unit'] ?? 'Nos'),
                'unit_rate' => $unit_rate,
                'discount_type' => 'fixed',
                'discount_value' => 0,
                'discount_amount' => 0,
                'line_subtotal' => $subtotal,
                'line_total' => $subtotal,
                'is_optional' => empty($raw['is_optional']) ? 0 : 1,
                'sort_order' => absint($index),
            );
        }
        return $items;
    }

    /**
     * Normalize submitted quotation sections.
     *
     * @param array $raw_sections Unslashed section rows; HTTP callers remove request slashes once.
     * @return array|WP_Error
     */
    public static function parse_sections($raw_sections) {
        if (!is_array($raw_sections) || count($raw_sections) > self::MAX_SECTIONS) { return new WP_Error('idlm_quote_sections', 'A quotation supports at most 50 sections.'); }
        $sections = array();
        foreach ((array) $raw_sections as $index => $raw) {
            if (!is_array($raw)) { return self::invalid('Section row'); }
            $key = sanitize_key($raw['key'] ?? 'section-' . $index);
            $name = sanitize_text_field($raw['section_name'] ?? '');
            if (!$key || !$name) {
                continue;
            }
            $sections[$key] = array(
                'section_name' => $name,
                'section_description' => '',
                'sort_order' => absint($index),
            );
        }
        if (!$sections) {
            $sections['general'] = array('section_name' => 'General', 'section_description' => '', 'sort_order' => 0);
        }
        return $sections;
    }

    /**
     * Calculate quotation totals from normalized item rows and adjustments.
     *
     * @param array $items Normalized item rows.
     * @param array $posted Raw quotation adjustment values.
     * @param array $quote_settings Global quotation calculation settings.
     * @return array|WP_Error
     */
    public static function calculate_totals($items, $posted, $quote_settings = array()) {
        if (!is_array($items) || count($items) > self::MAX_ROWS) { return new WP_Error('idlm_quote_rows', 'A quotation supports at most 200 rows.'); }
        $subtotal = 0; $wooden_subtotal = 0;
        foreach ($items as $item) {
            if ('header' === ($item['row_type'] ?? 'item') || !empty($item['is_optional'])) { continue; }
            $line = self::decimal_units($item['line_subtotal'] ?? 0, 0, self::MAX_AMOUNT_UNITS, 'Line subtotal');
            if (is_wp_error($line)) { return $line; }
            $subtotal += $line;
            if ($subtotal > self::MAX_AMOUNT_UNITS) { return self::invalid('Quotation subtotal'); }
            if (!empty($item['is_wood'])) { $wooden_subtotal += $line; }
        }
        $wooden_discount_percent = self::decimal_units($posted['wooden_discount_percent'] ?? 0, 0, 10000, 'Wooden discount percentage');
        $soft_close_percent = self::decimal_units($quote_settings['soft_close_percent'] ?? 0, 0, 10000, 'Soft close percentage');
        $additional_discount_amount = self::decimal_units($posted['additional_discount_amount'] ?? 0, 0, self::MAX_AMOUNT_UNITS, 'Additional discount');
        foreach (array($wooden_discount_percent, $soft_close_percent, $additional_discount_amount) as $value) { if (is_wp_error($value)) { return $value; } }
        $wooden_discount_amount = self::percentage($wooden_subtotal, $wooden_discount_percent);
        $subtotal_after_wooden_discount = $wooden_subtotal - $wooden_discount_amount;
        $other_subtotal = $subtotal - $wooden_subtotal;
        $soft_close_enabled = !empty($posted['soft_close_enabled']) ? 1 : 0;
        $soft_close_amount = $soft_close_enabled ? self::percentage($subtotal_after_wooden_discount, $soft_close_percent) : 0;
        $total_before_additional_discount = $subtotal_after_wooden_discount + $other_subtotal + $soft_close_amount;
        if ($total_before_additional_discount > self::MAX_AMOUNT_UNITS) { return self::invalid('Quotation total'); }
        $additional_discount_amount = min($total_before_additional_discount, $additional_discount_amount);
        $taxable = $total_before_additional_discount - $additional_discount_amount;
        $tax_total = self::percentage($taxable, 1800);
        $cgst = intdiv($tax_total + 1, 2); $sgst = $tax_total - $cgst;
        $pre_round_total = $taxable + $tax_total;
        $grand_total = intdiv($pre_round_total + 50, 100) * 100;
        if ($pre_round_total > self::MAX_AMOUNT_UNITS || $grand_total > self::MAX_AMOUNT_UNITS) { return self::invalid('Quotation total including GST and round off'); }
        return array(
            'subtotal' => (float) $subtotal / 100,
            'wooden_subtotal' => (float) $wooden_subtotal / 100,
            'wooden_discount_percent' => (float) $wooden_discount_percent / 100,
            'wooden_discount_amount' => (float) $wooden_discount_amount / 100,
            'subtotal_after_wooden_discount' => (float) $subtotal_after_wooden_discount / 100,
            'other_subtotal' => (float) $other_subtotal / 100,
            'soft_close_enabled' => $soft_close_enabled,
            'soft_close_percent' => (float) $soft_close_percent / 100,
            'soft_close_amount' => (float) $soft_close_amount / 100,
            'design_fee_percent' => 0,
            'design_fee_amount' => 0,
            'total_before_additional_discount' => (float) $total_before_additional_discount / 100,
            'additional_discount_amount' => (float) $additional_discount_amount / 100,
            'taxable_total' => (float) $taxable / 100,
            'cgst_total' => (float) $cgst / 100,
            'sgst_total' => (float) $sgst / 100,
            'igst_total' => 0,
            'round_off_amount' => (float) ($grand_total - $pre_round_total) / 100,
            'grand_total' => (float) $grand_total / 100,
        );
    }
}
