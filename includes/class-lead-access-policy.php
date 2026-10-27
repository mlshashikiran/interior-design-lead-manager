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
        if (class_exists('IDLM_Admin_Users_Controller') && IDLM_Admin_Users_Controller::is_crm_access_disabled(get_current_user_id())) { return false; }
        if (current_user_can('manage_options')) { return true; }
        if (!current_user_can('idlm_access_leads')) { return false; }
        // A failed legacy cleanup must not keep automatic editorial access.
        // Direct grants and an enrolled second CRM role remain explicit access.
        $user = wp_get_current_user();
        $editorial = array('editor', 'author', 'contributor');
        if ('1' !== get_option('idlm_crm_access_policy_version', '') && array_intersect((array) $user->roles, $editorial)) {
            if (!empty($user->caps['idlm_access_leads'])) { return true; }
            foreach (array_diff((array) $user->roles, $editorial) as $role_name) {
                $role = get_role($role_name);
                if ($role && $role->has_cap('idlm_access_leads')) { return true; }
            }
            return false;
        }
        return true;
    }

    public static function can_assign() {
        if (class_exists('IDLM_Admin_Users_Controller') && IDLM_Admin_Users_Controller::is_crm_access_disabled(get_current_user_id())) { return false; }
        return self::has_rep_access() && (current_user_can('manage_options') || current_user_can('idlm_assign_leads'));
    }

    public static function is_pre_sales() {
        return in_array('idlm_pre_sales', (array) wp_get_current_user()->roles, true);
    }

    /** Validate drawer fields before persistence; return the role-protected fields. */
    public static function validate_detail_fields($fields, $existing) {
        $protected = self::is_pre_sales()
            ? array('lead_quality', 'deal_value', 'discount_percentage', 'quote_shared', 'quote_value', 'quote_file') : array();
        foreach ($protected as $field) {
            if (isset($fields[$field]) && (string) $fields[$field] !== (string) ($existing->$field ?? '')) {
                $numeric = in_array($field, array('deal_value', 'discount_percentage', 'quote_shared', 'quote_value'), true);
                if (!$numeric || !is_numeric($fields[$field]) || (float) $fields[$field] !== (float) ($existing->$field ?? 0)) {
                    return new WP_Error('idlm_detail_permission', 'Use Lead Decision controls for qualification; commercial fields are managed by sales.');
                }
            }
        }
        foreach (array('deal_value', 'quote_value', 'discount_percentage') as $field) {
            if (isset($fields[$field]) && '' !== $fields[$field] &&
                (!is_numeric($fields[$field]) || !is_finite((float) $fields[$field]) || (float) $fields[$field] < 0 || ('discount_percentage' === $field && (float) $fields[$field] > 100))) {
                return new WP_Error('idlm_detail_value', 'Enter valid non-negative commercial values and a discount between 0 and 100.');
            }
        }
        return $protected;
    }

    /** Future ownership requires an enabled, effectively enrolled CRM user.
     * Context: any (manual/general), intake (pre-sales), sales (no pre-sales role).
     * Locking checks are reserved for the canonical lead transaction owner.
     */
    public static function is_assignable_rep($user_id, $context = 'any', $lock = false) {
        $user_id = absint($user_id);
        if (0 === $user_id) { return 'any' === $context; }
        if (!in_array($context, array('any', 'intake', 'sales'), true)) { return false; }
        if ($lock && !self::refresh_assignment_target($user_id)) { return false; }
        $user = get_userdata($user_id);
        if (!$user || !array_intersect((array) $user->roles, self::allowed_rep_roles())) { return false; }
        if ('1' === (string) get_user_meta($user_id, 'idlm_crm_access_disabled', true)) { return false; }
        $pre_sales = in_array('idlm_pre_sales', (array) $user->roles, true);
        if (('intake' === $context && !$pre_sales) || ('sales' === $context && $pre_sales)) { return false; }
        if (user_can($user, 'manage_options')) { return true; }
        if (!user_can($user, 'idlm_access_leads')) { return false; }
        // Match the access policy during an incomplete editorial-grant cleanup.
        $editorial = array('editor', 'author', 'contributor');
        if ('1' !== get_option('idlm_crm_access_policy_version', '') && array_intersect((array) $user->roles, $editorial)) {
            if (!empty($user->caps['idlm_access_leads'])) { return true; }
            foreach (array_diff((array) $user->roles, $editorial) as $role_name) {
                $role = get_role($role_name);
                if ($role && $role->has_cap('idlm_access_leads')) { return true; }
            }
            return false;
        }
        return true;
    }

    /** Serialize with live account/meta/role changes and discard request-local grants. */
    private static function refresh_assignment_target($user_id) {
        global $wpdb;
        if (!IDLM_Lead_Transaction::active()) { return false; }
        foreach (array($wpdb->users, $wpdb->usermeta, $wpdb->options) as $table) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0311: refresh_assignment_target(): fixed lead/user/meta tables and alias choices; user/lead/role values prepared; current grants and ownership are the authority and cannot use stale cached rows.
            $engine = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare(
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table
            )));
            if ('INNODB' !== strtoupper((string) $engine)) { return false; }
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0312: refresh_assignment_target(): fixed lead/user/meta tables and alias choices; user/lead/role values prepared; current grants and ownership are the authority and cannot use stale cached rows.
        $row = IDLM_Lead_Transaction::read($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->users} WHERE ID = %d FOR UPDATE", $user_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        if (!$row) { return false; }
        // Use the locking values, not a repeatable-read snapshot or earlier WP caches.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0313: refresh_assignment_target(): fixed lead/user/meta tables and alias choices; user/lead/role values prepared; current grants and ownership are the authority and cannot use stale cached rows.
        $meta_rows = IDLM_Lead_Transaction::read($wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d ORDER BY umeta_id FOR UPDATE", $user_id)));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $roles = wp_roles();
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0314: refresh_assignment_target(): fixed lead/user/meta tables and alias choices; user/lead/role values prepared; current grants and ownership are the authority and cannot use stale cached rows.
        $raw_roles = IDLM_Lead_Transaction::read($wpdb->get_var($wpdb->prepare(
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s FOR UPDATE", $roles->role_key
        )));
        $definitions = maybe_unserialize($raw_roles);
        if (!is_array($definitions)) { return false; }
        $roles->roles = $definitions; $roles->role_objects = array(); $roles->role_names = array();
        foreach ($definitions as $name => $definition) {
            if (!is_array($definition) || !is_array($definition['capabilities'] ?? null)) { return false; }
            $roles->role_objects[$name] = new WP_Role($name, $definition['capabilities']);
            $roles->role_names[$name] = $definition['name'] ?? $name;
        }
        $meta = array();
        foreach ($meta_rows as $item) { $meta[$item->meta_key][] = $item->meta_value; }
        wp_cache_delete($user_id, 'users');
        wp_cache_delete($user_id, 'user_meta');
        wp_cache_set($user_id, $row, 'users');
        wp_cache_set($user_id, $meta, 'user_meta');
        return true;
    }

    /** Revalidate the acting manager after waiting for a review lock. */
    public static function refresh_review_actor() {
        $id = get_current_user_id();
        if (!$id || !self::refresh_assignment_target($id)) { return false; }
        wp_set_current_user(0);
        wp_set_current_user($id);
        return self::can_assign();
    }

    public static function pre_sales_user_ids() {
        return array_values(array_filter(array_map('absint', get_users(array(
            'role' => 'idlm_pre_sales',
            'fields' => 'ID',
        )))));
    }

    public static function restriction_user_id($respect_global_visibility = true) {
        $scope = class_exists('IDLM_Admin_Users_Controller')
            ? IDLM_Admin_Users_Controller::role_scope((array) wp_get_current_user()->roles) : 'default';
        if ('assigned' === $scope) { return get_current_user_id(); }
        if ('all' === $scope) { return 0; }
        if (self::can_assign()) { return 0; }
        if (!$respect_global_visibility) { return get_current_user_id(); }
        if ('1' !== get_option('idlm_crm_access_policy_version', '')) { return get_current_user_id(); }
        return self::is_pre_sales() || '1' !== IDLM_Settings::get('idlm_reps_see_all_leads', '0') ? get_current_user_id() : 0;
    }

    public static function scope_sql($alias, &$params, $view_history = false, $respect_global_visibility = true) {
        if (!self::has_rep_access()) { return ' AND 1=0'; }
        $user_id = self::restriction_user_id($respect_global_visibility);
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
        if (!self::has_rep_access() || absint($lead_id) < 1) { return false; }
        if ((self::can_assign() && !self::restriction_user_id()) || ($view_history && !self::restriction_user_id())) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0315: can_access(): fixed lead/user/meta tables and alias choices; user/lead/role values prepared; current grants and ownership are the authority and cannot use stale cached rows.
            return (bool) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}idlm_leads WHERE id = %d", absint($lead_id)));
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        }
        if (!$view_history) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0316: can_access(): fixed lead/user/meta tables and alias choices; user/lead/role values prepared; current grants and ownership are the authority and cannot use stale cached rows.
            $assigned = $wpdb->get_var($wpdb->prepare(
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                "SELECT assigned_rep_id FROM {$wpdb->prefix}idlm_leads WHERE id = %d",
                absint($lead_id)
            ));
            return null !== $assigned && absint($assigned) === get_current_user_id();
        }
        $params = array(absint($lead_id));
        $scope = self::scope_sql('l', $params, $view_history);
        // phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- REL007-SQL-0317: can_access(): fixed lead/user/meta tables and alias choices; user/lead/role values prepared; current grants and ownership are the authority and cannot use stale cached rows.
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->prefix}idlm_leads l WHERE l.id = %d{$scope} LIMIT 1", $params));
        // phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }
}
