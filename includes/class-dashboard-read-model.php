<?php
/**
 * Authorization-scoped dashboard read model.
 *
 * @package InteriorDesignLeadManager
 * @author Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Dashboard_Read_Model {
    /**
     * Return cached dashboard aggregates for the current visibility scope.
     */
    public static function get_stats($period, $custom_start = '', $custom_end = '', $filters = array()) {
        $context = self::cache_context($period, $custom_start, $custom_end);
        $context['dashboard_filters'] = array_map('strval', (array) $filters);

        return IDLM_Read_Model_Cache::remember(
            'dashboard',
            $context,
            static function() use ($period, $custom_start, $custom_end, $filters) {
                return IDLM_Dashboard::calculate_stats($period, $custom_start, $custom_end, $filters);
            }
        );
    }

    /**
     * Build a PII-free cache context that cannot cross representative scopes.
     */
    public static function cache_context($period, $custom_start = '', $custom_end = '') {
        $restriction_user_id = IDLM_Lead_Access_Policy::restriction_user_id();
        $roles = array_map('sanitize_key', (array) wp_get_current_user()->roles);
        sort($roles);

        $ranges = IDLM_Dashboard::get_date_ranges($period, $custom_start, $custom_end);
        foreach ($ranges as $key => $value) {
            // Dashboard live ranges end at "now". Minute precision aligns the key with the cache TTL.
            $ranges[$key] = substr((string) $value, 0, 16);
        }

        $settings = array(
            'custom_stages' => get_option('idlm_custom_stages', array()),
            'kanban_stage_totals' => get_option('idlm_kanban_stage_totals', array()),
            'currency_code' => get_option('idlm_currency_code', 'INR'),
            'monthly_lead_target' => get_option('idlm_monthly_lead_target', 100),
            'monthly_conversion_target' => get_option('idlm_monthly_conversion_target', 10),
            'reps_see_all_leads' => IDLM_Settings::get('idlm_reps_see_all_leads', '1'),
        );

        return array(
            'dashboard_contract' => 3,
            'access' => IDLM_Dashboard::access_context(),
            'period' => sanitize_key($period),
            'ranges' => $ranges,
            'scope' => $restriction_user_id ? 'owner:' . absint($restriction_user_id) : 'global',
            'roles' => hash('sha256', wp_json_encode($roles)),
            'settings_version' => hash('sha256', wp_json_encode($settings)),
            'schema_version' => defined('IDLM_DB_VERSION') ? IDLM_DB_VERSION : '',
            'quotation_schema_version' => class_exists('IDLM_Quotation_Schema') ? IDLM_Quotation_Schema::VERSION : '',
        );
    }
}
