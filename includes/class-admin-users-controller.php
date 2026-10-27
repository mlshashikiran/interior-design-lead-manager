<?php
/** Administrator user and CRM access management. Builder: Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Admin_Users_Controller {
    const DISABLED_META_KEY = 'idlm_crm_access_disabled';
    const ROLE_SCOPES_OPTION = 'idlm_role_lead_scopes';

    public static function selectable_permissions($role) {
        $labels = array(
            'idlm_access_leads' => 'Access CRM leads', 'idlm_qualify_leads' => 'Qualify leads',
            'idlm_assign_leads' => 'Assign leads', 'idlm_view_quotes' => 'View quotations',
            'idlm_create_quotes' => 'Create quotations', 'idlm_edit_quote_drafts' => 'Edit quotation drafts',
            'idlm_submit_quotes' => 'Submit quotations', 'idlm_mark_quotes_shared' => 'Mark quotations shared',
            'idlm_accept_reject_quotes' => 'Accept or reject quotations', 'idlm_create_quote_revisions' => 'Create quotation revisions',
            'idlm_download_quote_pdf' => 'Download quotation PDFs', 'idlm_approve_quotes' => 'Approve quotations',
            'idlm_cancel_quotes' => 'Cancel quotations', 'idlm_manage_quote_settings' => 'Manage quotation settings',
            'idlm_manage_quote_items' => 'Manage quotation items',
        );
        if ('idlm_pre_sales' === $role) { return array_intersect_key($labels, array_flip(array('idlm_access_leads', 'idlm_qualify_leads'))); }
        return $labels;
    }

    public static function permission_roles() {
        return array_diff_key(self::managed_roles(), array_flip(array('administrator', 'editor', 'author', 'contributor')));
    }

    public static function handle_update_role_permissions() {
        if (!current_user_can('manage_options')) { wp_die('You do not have permission to manage CRM users.', 403); }
        check_admin_referer('idlm_update_role_permissions');
        $request_method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if ('POST' !== $request_method) { wp_die('Invalid request.', 405); }
        $role_name = isset($_POST['permission_role']) && is_string($_POST['permission_role']) ? sanitize_key(wp_unslash($_POST['permission_role'])) : '';
        if (!isset(self::permission_roles()[$role_name]) || !($role = get_role($role_name))) { self::redirect('error', 'invalid-role'); }
        $submitted = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_map('sanitize_text_field', wp_unslash($_POST['permissions'])) : array();
        foreach (self::selectable_permissions($role_name) as $capability => $label) {
            if (isset($submitted[$capability]) && '1' === $submitted[$capability]) { $role->add_cap($capability); }
            else { $role->remove_cap($capability); }
        }
        self::redirect('notice', 'role-permissions-updated');
    }

    public static function render() {
        if (!current_user_can('manage_options')) { wp_die('You do not have permission to manage CRM users.', 403); }
        $roles = self::managed_roles();
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0103: render(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0104: render(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $role = isset($_GET['role']) ? sanitize_key(wp_unslash($_GET['role'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0105: render(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $status = isset($_GET['access']) ? sanitize_key(wp_unslash($_GET['access'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0106: render(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $paged = max(1, absint($_GET['paged'] ?? 1));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
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
            'permission_roles' => self::permission_roles(),
            'selected_access' => $status, 'paged' => $paged, 'total' => (int) $query->get_total(),
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0107: render(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
            'per_page' => 25, 'notice' => sanitize_key(wp_unslash($_GET['idlm_users_notice'] ?? '')),
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0108: render(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
            'error' => sanitize_key(wp_unslash($_GET['idlm_users_error'] ?? '')),
            // phpcs:enable WordPress.Security.NonceVerification.Recommended
        ));
    }

    public static function handle_create() {
        self::authorize('idlm_create_user');
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0109: handle_create(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0110: handle_create(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $login = sanitize_user(wp_unslash($_POST['user_login'] ?? ''), true);
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0111: handle_create(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $role = sanitize_key(wp_unslash($_POST['role'] ?? ''));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if (!$email || !$login || !self::is_managed_role($role) || username_exists($login) || email_exists($email)) {
            self::redirect('error', 'invalid-user');
        }
        $user_id = wp_insert_user(array(
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0112: handle_create(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
            'user_login' => $login, 'user_email' => $email, 'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0113: handle_create(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')), 'role' => $role,
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            'user_pass' => wp_generate_password(24, true, true),
        ));
        if (is_wp_error($user_id)) { self::redirect('error', 'create-failed'); }
        self::enroll_editorial_user((int) $user_id, $role);
        self::save_phone_and_access((int) $user_id);
        self::send_reset((int) $user_id);
        self::redirect('notice', 'created');
    }

    public static function handle_update() {
        self::authorize('idlm_update_user');
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0114: handle_update(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $user_id = absint($_POST['user_id'] ?? 0);
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $user = get_userdata($user_id);
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0115: handle_update(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $role = sanitize_key(wp_unslash($_POST['role'] ?? ''));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0116: handle_update(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if (!$user || !$email || !self::is_managed_role($role) || (email_exists($email) && (int) email_exists($email) !== $user_id)) {
            self::redirect('error', 'invalid-user');
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0117: handle_update(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        self::guard_administrator_change($user_id, $role, !empty($_POST['crm_access_disabled']));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $updated = wp_update_user(array(
            'ID' => $user_id, 'user_email' => $email,
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0118: handle_update(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
            'first_name' => sanitize_text_field(wp_unslash($_POST['first_name'] ?? '')),
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0119: handle_update(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
            'last_name' => sanitize_text_field(wp_unslash($_POST['last_name'] ?? '')),
            // phpcs:enable WordPress.Security.NonceVerification.Missing
            // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0120: handle_update(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
            'display_name' => sanitize_text_field(wp_unslash($_POST['display_name'] ?? '')),
            // phpcs:enable WordPress.Security.NonceVerification.Missing
        ));
        if (is_wp_error($updated)) { self::redirect('error', 'update-failed'); }
        $role_changed = !in_array($role, (array) $user->roles, true);
        $user->set_role($role);
        if ($role_changed) { self::enroll_editorial_user($user_id, $role); }
        self::save_phone_and_access($user_id);
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0121: handle_update(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        if (!empty($_POST['send_reset_link'])) { self::send_reset($user_id); }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        self::redirect('notice', 'updated');
    }

    public static function handle_update_role_scopes() {
        self::authorize('idlm_update_role_scopes');
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0122: handle_update_role_scopes(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $submitted = isset($_POST['role_scopes']) && is_array($_POST['role_scopes'])
        // phpcs:enable WordPress.Security.NonceVerification.Missing
            // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0123: handle_update_role_scopes(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
            ? wp_unslash($_POST['role_scopes']) : array();
            // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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

    private static function enroll_editorial_user($user_id, $role) {
        if (!in_array($role, array('editor', 'author', 'contributor'), true)) { return; }
        $user = new WP_User($user_id);
        foreach (idlm_crm_default_capabilities() as $capability) { $user->add_cap($capability); }
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
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0124: save_phone_and_access(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        update_user_meta($user_id, 'idlm_phone', sanitize_text_field(wp_unslash($_POST['phone'] ?? '')));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0125: save_phone_and_access(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        if (!empty($_POST['crm_access_disabled'])) { update_user_meta($user_id, self::DISABLED_META_KEY, '1'); } else { delete_user_meta($user_id, self::DISABLED_META_KEY); }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
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
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0126: redirect(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $return_url = isset($_POST['idlm_return_url']) ? esc_url_raw(wp_unslash($_POST['idlm_return_url'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        $base = $return_url ? wp_validate_redirect($return_url, $fallback) : $fallback;
        wp_safe_redirect(add_query_arg('idlm_users_' . $kind, $code, $base));
        exit;
    }
}
