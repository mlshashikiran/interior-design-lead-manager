<?php
/** Central lead authorization and query scoping. @author Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Lead_Access_Policy {
    public static function allowed_rep_roles() {
        return function_exists('idlm_get_crm_role_slugs') ? idlm_get_crm_role_slugs() : array(
            'administrator', 'editor', 'author', 'contributor', 'sales_manager',
            'sales_executive', 'idlm_pre_sales',
        );
    }

    public static function has_rep_access() {
        if (!is_user_logged_in()) { return false; }
        if (current_user_can('manage_options')) { return true; }
        return (bool) array_intersect((array) wp_get_current_user()->roles, self::allowed_rep_roles());
    }

    public static function can_assign() {
        return current_user_can('manage_options') || current_user_can('idlm_assign_leads');
    }

    public static function is_pre_sales() {
        return in_array('idlm_pre_sales', (array) wp_get_current_user()->roles, true);
    }

    public static function is_assignable_rep($user_id) {
        $user_id = absint($user_id);
        if (0 === $user_id) { return true; }
        $user = get_userdata($user_id);
        return $user && (bool) array_intersect((array) $user->roles, self::allowed_rep_roles());
    }

    public static function pre_sales_user_ids() {
        return array_values(array_filter(array_map('absint', get_users(array(
            'role' => 'idlm_pre_sales',
            'fields' => 'ID',
        )))));
    }

    public static function restriction_user_id() {
        if (self::can_assign()) { return 0; }
        return self::is_pre_sales() || '1' !== IDLM_Settings::get('idlm_reps_see_all_leads', '1')
            ? get_current_user_id() : 0;
    }

    public static function scope_sql($alias, &$params, $view_history = false, $respect_global_visibility = true) {
        $user_id = $respect_global_visibility ? self::restriction_user_id() : (self::can_assign() ? 0 : get_current_user_id());
        if (!$user_id) { return ''; }
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
        if ($view_history && self::is_pre_sales()) {
            array_push($params, $user_id, $user_id, $user_id);
            return " AND ({$alias}.assigned_rep_id = %d OR {$alias}.intake_owner_id = %d OR {$alias}.qualified_by_user_id = %d)";
        }
        $params[] = $user_id;
        return " AND {$alias}.assigned_rep_id = %d";
    }

    public static function can_access($lead_id, $view_history = false) {
        global $wpdb;
        if (!self::restriction_user_id()) { return true; }
        if (!$view_history) {
            $assigned = $wpdb->get_var($wpdb->prepare(
                "SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id = %d",
                absint($lead_id)
            ));
            return null !== $assigned && absint($assigned) === get_current_user_id();
        }
        $params = array(absint($lead_id));
        $scope = self::scope_sql('l', $params, $view_history);
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->prefix}idlm_leads l WHERE l.id = %d{$scope} LIMIT 1", $params));
    }
}
