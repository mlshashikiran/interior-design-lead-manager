<?php
/** Focused M6 responsibility owner. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Google_Conversion_Feed {

    public static function get_google_ads_conversion_events() {
        global $wpdb;
        $table_leads = $wpdb->prefix . 'idlm_leads';
        $table_activity = $wpdb->prefix . 'idlm_lead_activity_log';

        // Get Google Event Mappings and retrieve all stages with non-empty mapped names
        $event_mappings = get_option('idlm_google_event_mappings', array(
            'Qualified' => 'CRM Qualified Lead',
            'Won' => 'Closed Won Interior Project'
        ));

        $mapped_stages = array();
        foreach ($event_mappings as $stg => $conv_name) {
            if (!empty(trim($conv_name))) {
                $mapped_stages[] = $stg;
            }
        }

        // If no stages are mapped, return early
        if (empty($mapped_stages)) {
            return array();
        }

        // 1. Fetch stage changes from activity logs for leads matching candidate filters
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $stage_logs = $wpdb->get_results(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0458: get_google_ads_conversion_events(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
            "SELECT a.lead_id, a.created_at, a.details, a.activity_type, l.gclid, l.gbraid, l.wbraid, l.deal_value, l.email, l.phone, l.source, l.utm_source, l.utm_medium, l.fbclid, l.fb_lead_id, l.lead_quality, l.stage
              FROM {$table_activity} a
              JOIN {$table_leads} l ON a.lead_id = l.id
              WHERE a.activity_type IN ('stage_change', 'lead_quality')
                AND l.is_duplicate = 0
                AND (
                    l.gclid != '' OR l.gbraid != '' OR l.wbraid != '' 
                    OR l.source LIKE '%google%' 
                    OR l.source LIKE '%paid%search%' 
                    OR l.utm_source LIKE '%google%' 
                    OR l.utm_medium = 'cpc'
                )
              ORDER BY a.created_at ASC"
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        $conversion_events = array();

        foreach ($stage_logs as $log) {
            if (!self::is_google_conversion_eligible($log)) {
                continue;
            }
            $stage = '';
            if ($log->activity_type === 'stage_change') {
                if (preg_match("/changed from '.*' to '(.*)'/i", $log->details, $matches)) {
                    $matched_stage = $matches[1];
                    foreach ($mapped_stages as $ms) {
                        if (strtolower($ms) === strtolower($matched_stage)) {
                            $stage = $ms;
                            break;
                        }
                    }
                }
            } elseif ($log->activity_type === 'lead_quality') {
                if (preg_match("/changed from '.*' to 'Qualified'/i", $log->details)) {
                    foreach ($mapped_stages as $ms) {
                        if (strtolower($ms) === 'qualified') {
                            $stage = $ms;
                            break;
                        }
                    }
                }
            }

            if ($stage) {
                $conversion_events[$log->lead_id . '_' . $stage] = array(
                    'lead_id' => $log->lead_id,
                    'gclid' => $log->gclid,
                    'gbraid' => $log->gbraid,
                    'wbraid' => $log->wbraid,
                    'stage' => $stage,
                    'time' => $log->created_at,
                    'value' => (strtolower($stage) === 'won' || strpos(strtolower($stage), 'won') !== false) ? floatval($log->deal_value) : 0,
                    'email' => $log->email,
                    'phone' => $log->phone
                );
            }
        }

        // 2. Scan leads table for current stage if not already captured in logs
        // This handles cases where leads were imported or logs were cleared
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $current_leads = $wpdb->get_results(
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0459: get_google_ads_conversion_events(): prefixed lead/log tables, fixed success/event predicates; values bound; IN placeholders match lead IDs plus platform/event parameters; receipts/history/current eligibility must not be cached.
            "SELECT id, stage, lead_quality, updated_at, created_at, conversion_time, qualified_at, gclid, gbraid, wbraid, deal_value, email, phone, source, utm_source, utm_medium, fbclid, fb_lead_id 
              FROM {$table_leads} 
              WHERE is_duplicate = 0 
                AND (
                    gclid != '' OR gbraid != '' OR wbraid != '' 
                    OR source LIKE '%google%' 
                    OR source LIKE '%paid%search%' 
                    OR utm_source LIKE '%google%' 
                    OR utm_medium = 'cpc'
                )"
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        foreach ($current_leads as $lead) {
            if (!self::is_google_conversion_eligible($lead)) {
                continue;
            }
            $stages_to_check = array();
            
            // Check if lead_quality is Qualified, and if 'Qualified' is mapped
            if (strtolower($lead->lead_quality) === 'qualified') {
                foreach ($mapped_stages as $ms) {
                    if (strtolower($ms) === 'qualified') {
                        $stages_to_check[] = $ms;
                        break;
                    }
                }
            }
            
            // Check if current stage is mapped
            if (!empty($lead->stage)) {
                foreach ($mapped_stages as $ms) {
                    if (strtolower($ms) === strtolower($lead->stage)) {
                        $stages_to_check[] = $ms;
                        break;
                    }
                }
            }

            // Remove duplicates
            $stages_to_check = array_unique($stages_to_check);

            foreach ($stages_to_check as $stg) {
                $key = $lead->id . '_' . $stg;
                if (!isset($conversion_events[$key])) {
                    $time = strtolower($stg) === 'won'
                        ? ((!empty($lead->conversion_time) && $lead->conversion_time !== '0000-00-00 00:00:00') ? $lead->conversion_time : $lead->updated_at)
                        : (($stg === $lead->stage) ? $lead->updated_at : (!empty($lead->qualified_at) ? $lead->qualified_at : $lead->created_at));
                    $conversion_events[$key] = array(
                        'lead_id' => $lead->id,
                        'gclid' => $lead->gclid,
                        'gbraid' => $lead->gbraid,
                        'wbraid' => $lead->wbraid,
                        'stage' => $stg,
                        'time' => $time,
                        'value' => (strtolower($stg) === 'won' || strpos(strtolower($stg), 'won') !== false) ? floatval($lead->deal_value) : 0,
                        'email' => $lead->email,
                        'phone' => $lead->phone
                    );
                }
            }
        }

        return $conversion_events;
    }

    public static function generate_google_ads_conversions_csv_response($terminate = true) {
        $currency = get_option('idlm_currency_code', 'INR');

        if ($terminate) {
            self::clear_csv_output_buffers();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=google-ads-offline-conversions-' . gmdate('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if ($output) {
            fputcsv($output, array('Conversion action', 'GCLID', 'Email Address', 'Phone number', 'Conversion date and time', 'Conversion value', 'Conversion currency', 'GBRAID', 'WBRAID'), ',', '"', '\\');

            $event_mappings = get_option('idlm_google_event_mappings', array(
                'Qualified' => 'CRM Qualified Lead',
                'Won' => 'Closed Won Interior Project'
            ));

            $events = self::get_google_ads_conversion_events();
            foreach ($events as $event) {
                $stage_key = $event['stage'];
                $conversion_name = '';

                // Match exactly or case-insensitively
                if (isset($event_mappings[$stage_key])) {
                    $conversion_name = trim($event_mappings[$stage_key]);
                } else {
                    foreach ($event_mappings as $k => $v) {
                        if (strtolower($k) === strtolower($stage_key)) {
                            $conversion_name = trim($v);
                            break;
                        }
                    }
                }

                if (empty($conversion_name)) {
                    continue;
                }

                $email = !empty($event['email']) ? strtolower(trim($event['email'])) : '';
                $phone = !empty($event['phone']) ? self::format_phone_e164($event['phone']) : '';
                $time_str = self::format_google_ads_conversion_time($event['time']);

                if (empty($time_str)) {
                    continue;
                }

                fputcsv($output, array(
                    $conversion_name,
                    $event['gclid'],
                    $email,
                    $phone,
                    $time_str,
                    $event['value'] > 0 ? number_format($event['value'], 2, '.', '') : '',
                    $currency,
                    $event['gbraid'],
                    $event['wbraid']
                ), ',', '"', '\\');
            }
            fclose($output); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        }
        if ($terminate && !defined('IDLM_TESTING')) {
            exit;
        }
    }

    public static function clear_csv_output_buffers() {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    public static function format_google_ads_conversion_time($value) {
        $value = is_string($value) ? trim($value) : '';
        if (empty($value) || '0000-00-00 00:00:00' === $value) {
            return '';
        }

        try {
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
            $datetime = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
            $errors = DateTimeImmutable::getLastErrors();

            if (!$datetime || (is_array($errors) && (!empty($errors['warning_count']) || !empty($errors['error_count'])))) {
                return '';
            }

            return $datetime->format('Y-m-d H:i:sP');
        } catch (Exception $exception) {
            return '';
        }
    }
    private static function is_google_conversion_eligible($lead) { return IDLM_CAPI::is_google_conversion_eligible($lead); }
    private static function format_phone_e164($phone) { return IDLM_CAPI::format_phone_e164($phone); }

}
