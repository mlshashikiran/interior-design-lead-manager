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
    /**
     * Normalize a decimal value to the quotation's two-decimal precision.
     *
     * @param mixed $value Raw decimal value.
     * @param float $minimum Minimum permitted value.
     * @return float
     */
    public static function clean_decimal($value, $minimum = 0) {
        $number = is_numeric($value) ? round((float) $value, 2) : 0.0;
        return max((float) $minimum, $number);
    }

    /**
     * Normalize submitted quotation rows and calculate their line totals.
     *
     * The settings argument is retained for compatibility with the quotation
     * module's existing parsing API and for future calculation policy inputs.
     *
     * @param array $raw_items Raw item rows.
     * @param array $quote_settings Quotation settings.
     * @return array
     */
    public static function parse_items($raw_items, $quote_settings = array()) {
        unset($quote_settings);
        $items = array();
        foreach ((array) $raw_items as $index => $raw) {
            $row_type = 'header' === sanitize_key($raw['row_type'] ?? 'item') ? 'header' : 'item';
            $description = sanitize_textarea_field(wp_unslash($raw['description'] ?? ''));
            if ('' === trim($description)) {
                continue;
            }
            if ('header' === $row_type) {
                $items[] = array(
                    'section_key' => sanitize_key($raw['section_key'] ?? 'general'),
                    'row_type' => 'header',
                    'description' => $description,
                    'quantity' => 0,
                    'unit_rate' => 0,
                    'line_subtotal' => 0,
                    'line_total' => 0,
                    'sort_order' => absint($index),
                );
                continue;
            }
            $quantity = max(0.001, (float) ($raw['quantity'] ?? 1));
            $length = self::clean_decimal($raw['length'] ?? 0);
            $height = self::clean_decimal($raw['height'] ?? 0);
            $unit_rate = self::clean_decimal($raw['unit_rate'] ?? 0);
            $subtotal = round($quantity * $unit_rate, 2);
            $items[] = array(
                'section_key' => sanitize_key($raw['section_key'] ?? 'general'),
                'row_type' => 'item',
                'catalogue_item_id' => absint($raw['catalogue_item_id'] ?? 0),
                'is_wood' => '1' === (string) ($raw['is_wood'] ?? '0') ? 1 : 0,
                'category' => sanitize_text_field($raw['category'] ?? ''),
                'room_area' => sanitize_text_field($raw['room_area'] ?? ''),
                'item_code' => sanitize_text_field($raw['item_code'] ?? ''),
                'item_name' => sanitize_text_field($raw['item_name'] ?? ''),
                'description' => $description,
                'specification' => sanitize_textarea_field($raw['specification'] ?? ''),
                'length' => round($length, 2),
                'height' => round($height, 2),
                'quantity' => round($quantity, 2),
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
     * @param array $raw_sections Raw section rows.
     * @return array
     */
    public static function parse_sections($raw_sections) {
        $sections = array();
        foreach ((array) $raw_sections as $index => $raw) {
            $key = sanitize_key($raw['key'] ?? 'section-' . $index);
            $name = sanitize_text_field(wp_unslash($raw['section_name'] ?? ''));
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
     * @return array
     */
    public static function calculate_totals($items, $posted) {
        $subtotal = 0.0;
        $wooden_subtotal = 0.0;
        foreach ((array) $items as $item) {
            if ('header' === ($item['row_type'] ?? 'item')) {
                continue;
            }
            if (!empty($item['is_optional'])) {
                continue;
            }
            $subtotal += $item['line_subtotal'];
            if (!empty($item['is_wood'])) {
                $wooden_subtotal += $item['line_subtotal'];
            }
        }
        $wooden_discount_percent = min(100, self::clean_decimal($posted['wooden_discount_percent'] ?? 0));
        $wooden_discount_amount = round($wooden_subtotal * $wooden_discount_percent / 100, 2);
        $subtotal_after_wooden_discount = round(max(0, $subtotal - $wooden_discount_amount), 2);
        $design_fee_percent = min(100, self::clean_decimal($posted['design_fee_percent'] ?? 0));
        $design_fee_amount = round($subtotal_after_wooden_discount * $design_fee_percent / 100, 2);
        $total_before_additional_discount = round($subtotal_after_wooden_discount + $design_fee_amount, 2);
        $additional_discount_amount = min($total_before_additional_discount, self::clean_decimal($posted['additional_discount_amount'] ?? 0));
        $taxable = round(max(0, $total_before_additional_discount - $additional_discount_amount), 2);
        $tax_total = round($taxable * 18 / 100, 2);
        $cgst = round($tax_total / 2, 2);
        $sgst = round($tax_total - $cgst, 2);
        return array(
            'subtotal' => round($subtotal, 2),
            'wooden_subtotal' => round($wooden_subtotal, 2),
            'wooden_discount_percent' => $wooden_discount_percent,
            'wooden_discount_amount' => $wooden_discount_amount,
            'subtotal_after_wooden_discount' => $subtotal_after_wooden_discount,
            'design_fee_percent' => $design_fee_percent,
            'design_fee_amount' => $design_fee_amount,
            'total_before_additional_discount' => $total_before_additional_discount,
            'additional_discount_amount' => $additional_discount_amount,
            'taxable_total' => $taxable,
            'cgst_total' => $cgst,
            'sgst_total' => $sgst,
            'igst_total' => 0,
            'grand_total' => round($taxable + $tax_total, 2),
        );
    }
}
