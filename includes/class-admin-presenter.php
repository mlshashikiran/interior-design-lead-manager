<?php
/**
 * Display-only helpers shared by admin and frontend CRM views.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Admin_Presenter {
    /** Return the user-facing qualification status without changing stored values. */
    public static function get_qualification_status_label($lead) {
        $quality = is_object($lead) ? ($lead->lead_quality ?? '') : ($lead['lead_quality'] ?? '');
        $has_floor_plan = is_object($lead) ? !empty($lead->floor_plan_received) : !empty($lead['floor_plan_received']);

        if ('Qualified' === $quality) {
            return $has_floor_plan ? 'Qualified · Plan Received' : 'Qualified · Plan Pending';
        }
        if ('Unqualified' === $quality) {
            return 'Unqualified';
        }
        return 'Pending Qualification';
    }

    /** Format a database datetime string using the WordPress timezone settings. */
    public static function format_datetime($mysql_date, $format = 'M d, Y h:i A') {
        if (empty($mysql_date)) {
            return '';
        }
        try {
            $dt = new DateTime($mysql_date, wp_timezone());
            return wp_date($format, $dt->getTimestamp());
        } catch (Exception $e) {
            return $mysql_date;
        }
    }

    /** Count configured business dates inclusively for pre-sales queue ageing. */
    public static function get_business_day_age($created_at) {
        if (!$created_at) {
            return 0;
        }
        $working_days = array_map('intval', (array) get_option('idlm_working_days', array(1, 2, 3, 4, 5, 6)));
        $working_days = array_map(static function ($day) { return 0 === $day ? 7 : $day; }, $working_days);
        $working_days = $working_days ?: array(1, 2, 3, 4, 5, 6);
        $cursor = new DateTime($created_at, wp_timezone());
        $cursor->setTime(0, 0, 0);
        $today = new DateTime('today', wp_timezone());
        $age = 0;
        while ($cursor <= $today) {
            if (in_array((int) $cursor->format('N'), $working_days, true)) {
                $age++;
            }
            $cursor->modify('+1 day');
        }
        return $age;
    }

    /**
     * Render activity details while suppressing inaccessible legacy Interakt media URLs.
     */
    public static function render_activity_details($details) {
        $lines = preg_split('/\R/', (string) $details);
        $rendered_lines = array();

        foreach ($lines as $line) {
            if (preg_match('/^Media URL:\s*/i', trim($line))) {
                continue;
            }

            $rendered_lines[] = esc_html($line);
        }

        return implode('<br>', $rendered_lines);
    }

    /** Get the configured monetary field consolidated in each Kanban stage header. */
    public static function get_kanban_stage_total_mappings($stages) {
        $allowed_sources = array('none', 'quote_value', 'deal_value');
        $saved_mappings = get_option('idlm_kanban_stage_totals', false);
        $default_mappings = array(
            'Quote Shared' => 'quote_value',
            'Negotiation' => 'quote_value',
            'Won' => 'deal_value',
        );
        $mappings = array();

        foreach ($stages as $stage) {
            if (is_array($saved_mappings) && isset($saved_mappings[$stage]) && in_array($saved_mappings[$stage], $allowed_sources, true)) {
                $mappings[$stage] = $saved_mappings[$stage];
            } elseif (false === $saved_mappings && isset($default_mappings[$stage])) {
                $mappings[$stage] = $default_mappings[$stage];
            } else {
                $mappings[$stage] = 'none';
            }
        }

        return $mappings;
    }
}
