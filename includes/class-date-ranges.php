<?php
/**
 * One list of reporting periods for every date filter in the CRM, and one way of
 * turning a period into a window. Windows are in the site timezone, start at
 * midnight and end now (or at 23:59:59 for closed days). The previous window is
 * the same stretch of time immediately before, so comparisons are like for like.
 * Builder: Shashi.
 */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Date_Ranges {
    /** The periods every date filter offers, in this order. */
    const OPTIONS = array(
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last_7_days' => 'Last 7 days',
        'last_14_days' => 'Last 14 days',
        'last_30_days' => 'Last 30 days',
        'this_week' => 'This week',
        'this_month' => 'This month',
        'this_quarter' => 'This quarter',
        'this_year' => 'This year',
        'custom' => 'Custom range',
    );

    /** Older keys still accepted from saved links, cursors and exports. */
    const ALIASES = array('week' => 'this_week', 'month' => 'this_month', 'year' => 'this_year');
    const LEGACY = array('last_month');

    /** A known period key, an alias resolved, or '' for anything else. */
    public static function normalize($key) {
        $key = sanitize_key((string) $key);
        $key = self::ALIASES[$key] ?? $key;
        return isset(self::OPTIONS[$key]) || in_array($key, self::LEGACY, true) ? $key : '';
    }

    /** Y-m-d or ''. */
    public static function clean_date($value) {
        $value = is_string($value) ? trim($value) : '';
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    /**
     * Current and previous windows as site-time 'Y-m-d H:i:s' strings, or null when
     * the period is unknown or a custom range has no valid dates.
     */
    public static function resolve($key, $custom_start = '', $custom_end = '') {
        $key = self::normalize($key);
        if ('' === $key) { return null; }
        $timezone = wp_timezone();
        $now = new DateTime('now', $timezone);
        $fmt = 'Y-m-d H:i:s';
        // Rolling windows end now; their comparison ends at the same time of day.
        $rolling = static function($days) use ($now, $fmt) {
            $start = (clone $now)->setTime(0, 0, 0)->modify('-' . ($days - 1) . ' days');
            return array(
                'curr_start' => $start->format($fmt), 'curr_end' => $now->format($fmt),
                'prev_start' => (clone $start)->modify("-{$days} days")->format($fmt),
                'prev_end' => (clone $now)->modify("-{$days} days")->format($fmt),
            );
        };
        // Calendar windows: the previous one is the same elapsed stretch of the period before.
        $calendar = static function(DateTime $start, DateTime $prev_start) use ($now, $fmt) {
            $elapsed_days = (int) $start->diff($now)->format('%a');
            $prev_end = (clone $prev_start)->modify("+{$elapsed_days} days")->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
            $limit = (clone $start)->modify('-1 second');
            if ($prev_end > $limit) { $prev_end = $limit; }
            return array('curr_start' => $start->format($fmt), 'curr_end' => $now->format($fmt), 'prev_start' => $prev_start->format($fmt), 'prev_end' => $prev_end->format($fmt));
        };
        $midnight = (clone $now)->setTime(0, 0, 0);
        switch ($key) {
            case 'today':
                return array('curr_start' => $midnight->format($fmt), 'curr_end' => $now->format($fmt), 'prev_start' => (clone $midnight)->modify('-1 day')->format($fmt), 'prev_end' => (clone $now)->modify('-1 day')->format($fmt));
            case 'yesterday':
                $day = (clone $midnight)->modify('-1 day');
                return array('curr_start' => $day->format($fmt), 'curr_end' => $day->format('Y-m-d 23:59:59'), 'prev_start' => (clone $day)->modify('-1 day')->format($fmt), 'prev_end' => (clone $day)->modify('-1 day')->format('Y-m-d 23:59:59'));
            case 'last_7_days':
                return $rolling(7);
            case 'last_14_days':
                return $rolling(14);
            case 'last_30_days':
                return $rolling(30);
            case 'this_week':
                $start = (clone $midnight)->modify('monday this week');
                return $calendar($start, (clone $start)->modify('-7 days'));
            case 'this_month':
                $start = (clone $midnight)->modify('first day of this month');
                return $calendar($start, (clone $start)->modify('first day of last month'));
            case 'this_quarter':
                $first_month = 3 * intdiv((int) $now->format('n') - 1, 3) + 1;
                $start = (clone $midnight)->setDate((int) $now->format('Y'), $first_month, 1);
                return $calendar($start, (clone $start)->modify('-3 months'));
            case 'this_year':
                $start = (clone $midnight)->setDate((int) $now->format('Y'), 1, 1);
                return $calendar($start, (clone $start)->modify('-1 year'));
            case 'last_month':
                $start = (clone $midnight)->modify('first day of last month');
                $end = (clone $midnight)->modify('first day of this month')->modify('-1 second');
                $prev_start = (clone $start)->modify('first day of last month');
                return array('curr_start' => $start->format($fmt), 'curr_end' => $end->format($fmt), 'prev_start' => $prev_start->format($fmt), 'prev_end' => (clone $start)->modify('-1 second')->format($fmt));
            case 'custom':
                $from = self::clean_date($custom_start); $to = self::clean_date($custom_end);
                if ('' === $from || '' === $to) { return null; }
                if ($from > $to) { list($from, $to) = array($to, $from); }
                $start = new DateTime($from . ' 00:00:00', $timezone);
                $days = (int) $start->diff(new DateTime($to . ' 00:00:00', $timezone))->format('%a') + 1;
                return array('curr_start' => $start->format($fmt), 'curr_end' => $to . ' 23:59:59', 'prev_start' => (clone $start)->modify("-{$days} days")->format($fmt), 'prev_end' => (clone $start)->modify('-1 second')->format($fmt));
        }
        return null;
    }

    /**
     * For filters that work in whole days: array(period, from Y-m-d, to Y-m-d).
     * A period fills its own days; old links with only dates read as a custom range.
     */
    public static function date_bounds($range, $from, $to) {
        $range = self::normalize($range); $from = self::clean_date($from); $to = self::clean_date($to);
        if ('' === $range && ('' !== $from || '' !== $to)) { $range = 'custom'; }
        $window = '' !== $range && 'custom' !== $range ? self::resolve($range) : null;
        if ($window) { $from = substr($window['curr_start'], 0, 10); $to = substr($window['curr_end'], 0, 10); }
        return array($range, $from, $to);
    }

    /** A prepared "column between start and end" condition, or '' when no window applies. */
    public static function where($column, $key, $custom_start = '', $custom_end = '') {
        global $wpdb;
        $range = self::resolve($key, $custom_start, $custom_end);
        if (!$range) { return ''; }
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The column comes from each caller's fixed whitelist; dates are bound.
        return $wpdb->prepare("{$column} >= %s AND {$column} <= %s", $range['curr_start'], $range['curr_end']);
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /** Prints the <option> list in the standard order, escaping each value and label. */
    public static function print_options($selected = '', $all_label = '') {
        $selected = self::normalize($selected);
        if ('' !== $all_label) { echo '<option value=""' . ('' === $selected ? ' selected' : '') . '>' . esc_html($all_label) . '</option>'; }
        foreach (self::OPTIONS as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . ($value === $selected ? ' selected' : '') . '>' . esc_html($label) . '</option>';
        }
    }

    /** Escaped <option> list in the standard order, with an optional "All time" first. */
    public static function options_html($selected = '', $all_label = '') {
        $selected = self::normalize($selected);
        $html = '' !== $all_label ? '<option value=""' . selected('', $selected, false) . '>' . esc_html($all_label) . '</option>' : '';
        foreach (self::OPTIONS as $value => $label) {
            $html .= '<option value="' . esc_attr($value) . '"' . selected($value, $selected, false) . '>' . esc_html($label) . '</option>';
        }
        return $html;
    }
}
