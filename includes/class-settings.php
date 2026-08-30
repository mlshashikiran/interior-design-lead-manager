<?php
if (!defined('ABSPATH')) {
    exit;
}

/** Grouped non-secret settings with mirrored legacy option compatibility. */
class IDLM_Settings {
    const VERSION = '1.0.0';
    const VERSION_OPTION = 'idlm_settings_version';
    const ROUTING_OPTION = 'idlm_routing_settings';

    public static function init() {
        if (self::VERSION !== get_option(self::VERSION_OPTION, '')) {
            self::sync_routing_from_legacy();
            update_option(self::VERSION_OPTION, self::VERSION, false);
        }
    }

    public static function routing_defaults() {
        return array(
            'idlm_reps_see_all_leads' => '1',
            'idlm_assignment_mode' => 'manual',
            'idlm_active_assignment_reps' => array(),
            'idlm_active_pre_sales_reps' => null,
            'idlm_rep_studio_assignments' => array(),
            'idlm_source_assignment_mappings' => array(),
            'idlm_studio_mismatch_behavior' => 'general_pool',
            'idlm_handoff_task_policy' => 'transfer',
            'idlm_handoff_notifications_enabled' => '0',
            'idlm_routing_exception_notifications_enabled' => '0',
            'idlm_max_active_leads_per_rep' => 30,
            'idlm_working_hours_start' => '09:00',
            'idlm_working_hours_end' => '18:00',
            'idlm_working_days' => array(1, 2, 3, 4, 5, 6),
            'idlm_presales_calls_per_day' => 2,
            'idlm_presales_whatsapp_per_day' => 1,
            'idlm_fallback_assignment_enabled' => '0',
            'idlm_fallback_assignment_hours' => 24,
            'idlm_qualified_fallback_enabled' => '0',
            'idlm_qualified_fallback_hours' => 4,
            'idlm_qualified_fallback_end_at' => '',
            'idlm_qualified_fallback_include_existing' => '0',
        );
    }

    public static function get($option_name, $default = null) {
        $defaults = self::routing_defaults();
        if (!array_key_exists($option_name, $defaults)) {
            return get_option($option_name, $default);
        }

        // Standalone keys remain authoritative while compatibility mirroring is active.
        $missing = new stdClass();
        $legacy_value = get_option($option_name, $missing);
        if ($missing !== $legacy_value) {
            return $legacy_value;
        }

        $group = get_option(self::ROUTING_OPTION, array());
        if (is_array($group) && array_key_exists($option_name, $group)) {
            return $group[$option_name];
        }

        return null === $default ? $defaults[$option_name] : $default;
    }

    public static function sync_routing_from_legacy() {
        $group = array();
        foreach (self::routing_defaults() as $option_name => $default) {
            $group[$option_name] = get_option($option_name, $default);
        }
        update_option(self::ROUTING_OPTION, $group, false);
        return $group;
    }
}
