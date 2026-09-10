<?php
/** Administrator user and CRM access management. Builder: Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Admin_Users_Controller {
    const DISABLED_META_KEY = 'idlm_crm_access_disabled';
    const ROLE_SCOPES_OPTION = 'idlm_role_lead_scopes';

    public static function render() {
        if (!current_user_can('manage_options')) { wp_die('You do not have permission to manage CRM users.', 403); }
        $roles = self::managed_roles();
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $role = isset($_GET['role']) ? sanitize_key(wp_unslash($_GET['role'])) : '';
        $status = isset($_GET['access']) ? sanitize_key(wp_unslash($_GET['access'])) : '';
        $paged = max(1, absint($_GET['paged'] ?? 1));
        $args = array('number' => 25, 'paged' => $paged, 'orderby' => 'display_name', 'order' => 'ASC');
        if ($search) { $args['search'] = '*' . $search . '*'; $args['search_columns'] = array('user_login', 'user_email', 'display_name'); }
        if ($role && isset($roles[$role])) { $args['role'] = $role; }
        $query = new WP_User_Query($args);
        $users = $query->get_results();
        if ('enabled' === $status || 'disabled' === $status) {
            $users = array_values(array_filter($users, static function($user) use ($status) {
                return ('disabled' === $status) === self::is_crm_access_disabled($user->ID);
            }));
        }
        IDLM_View::display('admin/users.php', array(
            'users' => $users, 'roles' => $roles, 'search' => $search, 'selected_role' => $role,
            'role_scopes' => self::role_scopes(),
            'selected_access' => $status, 'paged' => $paged, 'total' => (int) $query->get_total(),
            'per_page' => 25, 'notice' => sanitize_key(wp_unslash($_GET['idlm_users_notice'] ?? '')),
            'error' => sanitize_key(wp_unslash($_GET['idlm_users_error'] ?? '')),
        ));
    }

    public static function handle_create() {
        self::authorize('idlm_create_user');
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $login = sanitize_user(wp_unslash($_POST['user_login'] ?? ''), true);
        $role = sanitize_key(wp_unslash($_POST['role'] ?? ''));
        if (!$email || !$login || !self::is_managed_role($role) || username_exists($login) || email_exists($email)) {
            self::redirect('error', 'invalid-user');
        }
        $user_id = wp_insert_user(array(
            'user_login' => $login, 'user_email' => $email, 'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')), 'role' => $role,
            'user_pass' => wp_generate_password(24, true, true),
        ));
        if (is_wp_error($user_id)) { self::redirect('error', 'create-failed'); }
        self::save_phone_and_access((int) $user_id);
        self::send_reset((int) $user_id);
        self::redirect('notice', 'created');
    }

    public static function handle_update() {
        self::authorize('idlm_update_user');
        $user_id = absint($_POST['user_id'] ?? 0);
        $user = get_userdata($user_id);
        $role = sanitize_key(wp_unslash($_POST['role'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        if (!$user || !$email || !self::is_managed_role($role) || (email_exists($email) && (int) email_exists($email) !== $user_id)) {
            self::redirect('error', 'invalid-user');
        }
        self::guard_administrator_change($user_id, $role, !empty($_POST['crm_access_disabled']));
        $updated = wp_update_user(array(
            'ID' => $user_id, 'user_email' => $email,
            'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
            'display_name' => sanitize_text_field(wp_unslash($_POST['display_name'] ?? '')),
        ));
        if (is_wp_error($updated)) { self::redirect('error', 'update-failed'); }
        $user->set_role($role);
        self::save_phone_and_access($user_id);
        if (!empty($_POST['send_reset_link'])) { self::send_reset($user_id); }
        self::redirect('notice', 'updated');
    }

    public static function handle_update_role_scopes() {
        self::authorize('idlm_update_role_scopes');
        $submitted = isset($_POST['role_scopes']) && is_array($_POST['role_scopes'])
            ? wp_unslash($_POST['role_scopes']) : array();
        $scopes = array();
        foreach (self::managed_roles() as $role => $data) {
            if ('administrator' === $role) { continue; }
            $scope = isset($submitted[$role]) ? sanitize_key($submitted[$role]) : 'default';
            if (in_array($scope, array('all', 'assigned'), true)) {
                $scopes[$role] = $scope;
            }
        }
        update_option(self::ROLE_SCOPES_OPTION, $scopes, false);
        self::redirect('notice', 'role-scopes-updated');
    }

    public static function is_crm_access_disabled($user_id) { return '1' === (string) get_user_meta(absint($user_id), self::DISABLED_META_KEY, true); }

    public static function role_scopes() {
        $stored = get_option(self::ROLE_SCOPES_OPTION, array());
        return is_array($stored) ? $stored : array();
    }

    public static function role_scope($roles) {
        $scopes = self::role_scopes();
        $resolved = array();
        foreach ((array) $roles as $role) {
            if ('administrator' === $role) { return 'all'; }
            if (isset($scopes[$role]) && in_array($scopes[$role], array('all', 'assigned'), true)) {
                $resolved[] = $scopes[$role];
            }
        }
        if (in_array('assigned', $resolved, true)) { return 'assigned'; }
        return in_array('all', $resolved, true) ? 'all' : 'default';
    }

    public static function managed_roles() {
        // `get_editable_roles()` is only loaded by wp-admin. The same page is
        // available in the frontend workspace, so use the always-available
        // WordPress role registry and retain the CRM allowlist.
        $role_registry = wp_roles();
        $all = $role_registry && is_array($role_registry->roles) ? $role_registry->roles : array();
        $allowed = array_flip(array_merge(array('administrator', 'idlm_pre_sales'), (array) idlm_get_crm_role_slugs()));
        return array_intersect_key($all, $allowed);
    }

    private static function is_managed_role($role) { return isset(self::managed_roles()[$role]); }
    private static function authorize($action) { if (!current_user_can('manage_options')) { wp_die('You do not have permission to manage CRM users.', 403); } check_admin_referer($action); }
    private static function save_phone_and_access($user_id) {
        update_user_meta($user_id, 'idlm_phone', sanitize_text_field(wp_unslash($_POST['phone'] ?? '')));
        if (!empty($_POST['crm_access_disabled'])) { update_user_meta($user_id, self::DISABLED_META_KEY, '1'); } else { delete_user_meta($user_id, self::DISABLED_META_KEY); }
    }
    private static function send_reset($user_id) { $user = get_userdata($user_id); if ($user) { retrieve_password($user->user_login); } }
    private static function guard_administrator_change($user_id, $new_role, $disabled) {
        $is_admin = user_can($user_id, 'manage_options');
        if ($user_id === get_current_user_id() && ('administrator' !== $new_role || $disabled)) { self::redirect('error', 'self-lockout'); }
        if ($is_admin && ('administrator' !== $new_role || $disabled)) {
            $admins = get_users(array('role' => 'administrator', 'fields' => 'ID'));
            if (count($admins) <= 1) { self::redirect('error', 'last-admin'); }
        }
    }
    private static function redirect($kind, $code) {
        $fallback = IDLM_Admin::admin_utility_url();
        $return_url = isset($_POST['idlm_return_url']) ? esc_url_raw(wp_unslash($_POST['idlm_return_url'])) : '';
        $base = $return_url ? wp_validate_redirect($return_url, $fallback) : $fallback;
        wp_safe_redirect(add_query_arg('idlm_users_' . $kind, $code, $base));
        exit;
    }
}
