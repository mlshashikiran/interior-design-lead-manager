<?php
/** Administrator user and CRM access management. Builder: Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Admin_Users_Controller {
    const DISABLED_META_KEY = 'idlm_crm_access_disabled';
    const ROLE_SCOPES_OPTION = 'idlm_role_lead_scopes';
    const EDITORIAL_ROLES = array('editor', 'author', 'contributor');

    public static function selectable_permissions($role) {
        $labels = array(
            'idlm_access_leads' => 'Access CRM leads', 'idlm_qualify_leads' => 'Qualify leads',
            'idlm_assign_leads' => 'Assign leads', 'idlm_view_quotes' => 'View quotations',
            'idlm_create_quotes' => 'Create quotations', 'idlm_edit_quote_drafts' => 'Edit quotation drafts',
            'idlm_submit_quotes' => 'Submit quotations', 'idlm_mark_quotes_shared' => 'Mark quotations shared',
            'idlm_record_lead_payments' => 'Record lead payments', 'idlm_correct_lead_payments' => 'Correct payments and amend payment schedules',
            'idlm_accept_reject_quotes' => 'Accept or reject quotations', 'idlm_create_quote_revisions' => 'Create quotation revisions',
            'idlm_download_quote_pdf' => 'Download quotation PDFs', 'idlm_approve_quotes' => 'Approve quotations',
            'idlm_cancel_quotes' => 'Cancel quotations', 'idlm_manage_quote_settings' => 'Manage quotation settings',
            'idlm_manage_quote_items' => 'Manage quotation items',
            'idlm_receive_design_handoffs' => 'Accept and assign design handoffs',
            'idlm_work_design_projects' => 'Work on assigned design projects',
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
        $changes = self::apply_role_permissions($role, $role_name, $submitted);
        self::log_role_permissions($role_name, $changes);
        self::redirect('notice', 'role-permissions-updated');
    }

    /**
     * Save one role's lead visibility and permissions from the single role editor.
     * Other roles' scopes are never rewritten. Administrator stays read-only.
     */
    public static function handle_update_role_access() {
        if (!current_user_can('manage_options')) { wp_die('You do not have permission to manage CRM users.', 403); }
        check_admin_referer('idlm_update_role_access');
        $request_method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if ('POST' !== $request_method) { wp_die('Invalid request.', 405); }
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce and manage_options are checked above.
        $role_name = isset($_POST['access_role']) && is_string($_POST['access_role']) ? sanitize_key(wp_unslash($_POST['access_role'])) : '';
        $scope = isset($_POST['role_scope']) && is_string($_POST['role_scope']) ? sanitize_key(wp_unslash($_POST['role_scope'])) : '';
        $submitted = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_map('sanitize_text_field', wp_unslash($_POST['permissions'])) : array();
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        if ('administrator' === $role_name || !self::is_managed_role($role_name) || !in_array($scope, array('default', 'all', 'assigned'), true)) {
            self::redirect('error', 'invalid-role');
        }
        self::apply_role_access($role_name, $scope, $submitted);
        self::redirect('notice', 'role-access-updated');
    }

    /** Apply and log one role's scope and capabilities. Callers authorize and validate first. */
    public static function apply_role_access($role_name, $scope, $submitted) {
        $scopes = self::role_scopes();
        $before = isset($scopes[$role_name]) && in_array($scopes[$role_name], array('all', 'assigned'), true) ? $scopes[$role_name] : 'default';
        if ($before !== $scope) {
            if ('default' === $scope) { unset($scopes[$role_name]); } else { $scopes[$role_name] = $scope; }
            update_option(self::ROLE_SCOPES_OPTION, $scopes, false);
            self::log_role_scope($role_name, $before, $scope);
        }
        if (isset(self::permission_roles()[$role_name]) && ($role = get_role($role_name))) {
            self::log_role_permissions($role_name, self::apply_role_permissions($role, $role_name, $submitted));
        }
    }

    /** Tick means granted, no tick means removed, for this role's selectable permissions only. */
    private static function apply_role_permissions($role, $role_name, $submitted) {
        $changes = array('added' => array(), 'removed' => array());
        foreach (self::selectable_permissions($role_name) as $capability => $label) {
            $had = $role->has_cap($capability);
            if (isset($submitted[$capability]) && '1' === $submitted[$capability]) {
                $role->add_cap($capability);
                if (!$had) { $changes['added'][] = $capability; }
            } else {
                $role->remove_cap($capability);
                if ($had) { $changes['removed'][] = $capability; }
            }
        }
        return $changes;
    }

    private static function log_role_scope($role_name, $before, $after) {
        if (class_exists('IDLM_Access_Change_Log')) {
            IDLM_Access_Change_Log::record('role_scope_changed', 'role', $role_name, $role_name, array('scope' => $before), array('scope' => $after));
        }
    }

    private static function log_role_permissions($role_name, $changes) {
        if (($changes['added'] || $changes['removed']) && class_exists('IDLM_Access_Change_Log')) {
            IDLM_Access_Change_Log::record('role_permissions_changed', 'role', $role_name, $role_name, array('removed' => $changes['removed']), array('added' => $changes['added']));
        }
    }

    /** Job-role name for screens. Stored slugs never change, and the old design keeps stored names. */
    public static function role_label($slug) {
        $slug = (string) $slug;
        $registry = wp_roles();
        $all = $registry && is_array($registry->roles) ? $registry->roles : array();
        $name = translate_user_role($all[$slug]['name'] ?? $slug);
        if (!class_exists('IDLM_Admin_Assets') || !IDLM_Admin_Assets::design_layer_enabled()) { return $name; }
        $labels = array('agent' => 'Sales representative', 'agentadmin' => 'Sales manager', 'idlm_pre_sales' => 'Pre-sales', 'administrator' => 'Administrator');
        if (isset($labels[$slug])) { return $labels[$slug]; }
        return in_array($slug, self::EDITORIAL_ROLES, true) ? $name . ' (website role)' : $name;
    }

    /** Roles for a pick list: Administrator last; website roles apart unless the person holds one. */
    public static function pick_list_roles($roles, $held = array()) {
        $main = array();
        $website = array();
        foreach (array_keys((array) $roles) as $slug) {
            if ('administrator' === $slug) { continue; }
            if (in_array($slug, self::EDITORIAL_ROLES, true) && !in_array($slug, (array) $held, true)) {
                $website[$slug] = self::role_label($slug);
            } else {
                $main[$slug] = self::role_label($slug);
            }
        }
        if (isset($roles['administrator'])) { $main['administrator'] = self::role_label('administrator'); }
        return array('main' => $main, 'website' => $website);
    }

    /** The one role a person's form preselects. A held Administrator role always wins. */
    public static function preselected_role($held, $roles) {
        $managed = array_values(array_intersect((array) $held, array_keys((array) $roles)));
        if (!$managed) { return ''; }
        return in_array('administrator', $managed, true) ? 'administrator' : $managed[0];
    }

    public static function permission_groups() {
        return array(
            'Leads' => array('idlm_access_leads', 'idlm_qualify_leads', 'idlm_assign_leads'),
            'Quotations' => array('idlm_view_quotes', 'idlm_create_quotes', 'idlm_edit_quote_drafts', 'idlm_submit_quotes', 'idlm_mark_quotes_shared', 'idlm_accept_reject_quotes', 'idlm_create_quote_revisions', 'idlm_download_quote_pdf'),
            'Quotation administration' => array('idlm_approve_quotes', 'idlm_cancel_quotes', 'idlm_manage_quote_settings', 'idlm_manage_quote_items'),
            'Payments' => array('idlm_record_lead_payments', 'idlm_correct_lead_payments'),
            'Design team' => array('idlm_receive_design_handoffs', 'idlm_work_design_projects'),
        );
    }

    /** Permission => the permission it has no effect without. Used only for non-blocking warnings. */
    public static function permission_dependencies() {
        $needs = array();
        foreach (array('idlm_qualify_leads', 'idlm_assign_leads', 'idlm_view_quotes', 'idlm_record_lead_payments', 'idlm_correct_lead_payments') as $capability) { $needs[$capability] = 'idlm_access_leads'; }
        foreach (array('idlm_create_quotes', 'idlm_edit_quote_drafts', 'idlm_submit_quotes', 'idlm_mark_quotes_shared', 'idlm_accept_reject_quotes', 'idlm_create_quote_revisions', 'idlm_download_quote_pdf', 'idlm_approve_quotes', 'idlm_cancel_quotes') as $capability) { $needs[$capability] = 'idlm_view_quotes'; }
        return $needs;
    }

    /**
     * What "Default" means for a role, in words. Pass the access flags to describe unsaved ticks.
     * Must stay aligned with IDLM_Lead_Access_Policy::restriction_user_id().
     */
    public static function default_scope_outcome($slug, $has_access = null, $can_assign = null) {
        $role = get_role($slug);
        if (null === $has_access) { $has_access = $role && $role->has_cap('idlm_access_leads'); }
        if (null === $can_assign) { $can_assign = $role && $role->has_cap('idlm_assign_leads'); }
        $reps_see_all = '1' === IDLM_Settings::get('idlm_reps_see_all_leads', '0');
        $routing_phrase = $reps_see_all ? 'all leads, because "Reps see all leads" is on in Settings' : 'only leads assigned to them, because "Reps see all leads" is off in Settings';
        if ('administrator' === $slug) {
            return array('outcome' => 'all', 'text' => 'Administrators always see all leads.', 'depends_on_setting' => false);
        }
        if (in_array($slug, self::EDITORIAL_ROLES, true)) {
            return array('outcome' => 'none', 'text' => 'This website role gives no CRM access by itself. People invited into the CRM with it see ' . $routing_phrase . '.', 'depends_on_setting' => true);
        }
        if (!$has_access) {
            return array('outcome' => 'none', 'text' => 'People with only this role have no CRM access, so they see no leads.', 'depends_on_setting' => false);
        }
        if ($can_assign) {
            return array('outcome' => 'all', 'text' => 'People with this role see all leads, because it can assign leads.', 'depends_on_setting' => false);
        }
        if ('1' !== get_option('idlm_crm_access_policy_version', '')) {
            return array('outcome' => 'assigned', 'text' => 'People with this role see only leads assigned to them until CRM access setup finishes.', 'depends_on_setting' => false);
        }
        if ('idlm_pre_sales' === $slug) {
            return array('outcome' => 'assigned', 'text' => 'People with this role see only leads assigned to them, because pre-sales always work from their own queue.', 'depends_on_setting' => false);
        }
        return array('outcome' => $reps_see_all ? 'all' : 'assigned', 'text' => 'People with this role see ' . $routing_phrase . '.', 'depends_on_setting' => true);
    }

    /** One plain phrase for a role's lead visibility, for the roles list. */
    public static function role_visibility_label($slug) {
        if ('administrator' === $slug) { return 'All leads'; }
        $scopes = self::role_scopes();
        $scope = isset($scopes[$slug]) && in_array($scopes[$slug], array('all', 'assigned'), true) ? $scopes[$slug] : 'default';
        $default = self::default_scope_outcome($slug);
        $words = array('all' => 'All leads', 'assigned' => 'Only assigned leads', 'none' => 'No CRM access');
        if (in_array($slug, self::EDITORIAL_ROLES, true)) {
            return 'default' === $scope ? 'No CRM access by itself' : $words[$scope] . ' if invited into the CRM';
        }
        $role = get_role($slug);
        if (!$role || !$role->has_cap('idlm_access_leads')) { return $words['none']; }
        return 'default' === $scope ? $words[$default['outcome']] . ' (default)' : $words[$scope];
    }

    /** Read-only description of one person's real access, for their edit disclosure. */
    public static function access_summary($user) {
        $access = IDLM_Lead_Access_Policy::describe_user_access($user);
        $words = array('all' => 'All leads', 'assigned' => 'Only assigned leads', 'none' => 'No CRM access');
        $labels = self::selectable_permissions('');
        $is_admin = user_can($user, 'manage_options');
        $pre_sales = in_array('idlm_pre_sales', (array) $user->roles, true);
        $standalone = array('idlm_manage_quote_items', 'idlm_manage_quote_settings', 'idlm_receive_design_handoffs', 'idlm_work_design_projects');
        $admin_implied = array('idlm_access_leads', 'idlm_assign_leads', 'idlm_record_lead_payments', 'idlm_correct_lead_payments', 'idlm_receive_design_handoffs', 'idlm_work_design_projects');
        $needs = self::permission_dependencies();
        $effective = static function($capability) use ($user, $is_admin, $admin_implied) {
            return user_can($user, $capability) || ($is_admin && in_array($capability, $admin_implied, true));
        };
        $groups = array();
        foreach (self::permission_groups() as $group => $capabilities) {
            foreach ($capabilities as $capability) {
                if ($access['suspended'] || !isset($labels[$capability])) { continue; }
                $is_lead_permission = 'Leads' === $group;
                if ($pre_sales && !$is_lead_permission) { continue; }
                if (!$access['has_access'] && !in_array($capability, $standalone, true)) { continue; }
                // A permission whose prerequisite is missing does nothing, so it is not listed as something they can do.
                if (isset($needs[$capability]) && !$effective($needs[$capability])) { continue; }
                if ($effective($capability)) { $groups[$group][] = $labels[$capability]; }
            }
        }
        // scope_sql() also lets restricted pre-sales read leads they took in or qualified.
        $sees = 'assigned' === $access['sees'] && $pre_sales ? 'Assigned leads, plus leads they took in or qualified' : $words[$access['sees']];
        return array('sees' => $sees, 'groups' => $groups, 'extra' => self::extra_capabilities($user), 'suspended' => $access['suspended'], 'is_admin' => $is_admin);
    }

    /** Capabilities stored on the account that no current role provides: stale or direct grants, shown and never changed. */
    public static function extra_capabilities($user) {
        $stored = isset($user->caps) && is_array($user->caps) ? $user->caps : array();
        $roles = (array) $user->roles;
        $registry = wp_roles();
        $all = $registry && is_array($registry->roles) ? $registry->roles : array();
        $labels = self::selectable_permissions('');
        $extra = array();
        foreach ($stored as $capability => $granted) {
            if (!$granted || in_array($capability, $roles, true) || isset($all[$capability])) { continue; }
            foreach ($roles as $role_name) {
                $role = get_role($role_name);
                if ($role && $role->has_cap($capability)) { continue 2; }
            }
            $extra[] = $labels[$capability] ?? (string) $capability;
        }
        return $extra;
    }

    // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom IDLM tables named from $wpdb->prefix; values are bound with prepare(), cast to int, or come from fixed clause builders and generated placeholder lists; request-scoped reads and transactional writes are deliberately uncached. Builder: Shashi.
    /** Open lead count per person in one prepared query: not Won or Lost, not duplicates. */
    public static function open_lead_counts($user_ids) {
        global $wpdb;
        $ids = array_values(array_unique(array_filter(array_map('absint', (array) $user_ids))));
        if (!$ids) { return array(); }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Fixed table; IDs are bound placeholders; administrators see live counts.
        $rows = $wpdb->get_results($wpdb->prepare("SELECT assigned_rep_id, COUNT(*) AS open_leads FROM {$wpdb->prefix}idlm_leads WHERE assigned_rep_id IN ({$placeholders}) AND is_duplicate = 0 AND COALESCE(stage, '') NOT IN ('Won', 'Lost') GROUP BY assigned_rep_id", $ids));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $counts = array();
        foreach ((array) $rows as $row) { $counts[(int) $row->assigned_rep_id] = (int) $row->open_leads; }
        return $counts;
    }
    // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

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
        $design_layer = class_exists('IDLM_Admin_Assets') && IDLM_Admin_Assets::design_layer_enabled();
        IDLM_View::display('admin/users.php', array(
            'users' => $users, 'roles' => $roles, 'search' => $search, 'selected_role' => $role,
            'open_leads' => $design_layer ? self::open_lead_counts(wp_list_pluck($users, 'ID')) : array(),
            'access_log' => $design_layer && class_exists('IDLM_Access_Change_Log') ? IDLM_Access_Change_Log::entries() : array(),
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

    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    public static function handle_create() {
        self::authorize('idlm_create_user');
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0109: handle_create(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- REL007-BOUNDARY-0110: handle_create(): render requires manage_options and uses only bounded read filters/notices; create/update/scopes call authorize(action) before private helpers, which enforces manage_options, POST and action-specific nonce; managed roles/scopes and self/final-admin protections remain.
        $submitted_login = wp_unslash($_POST['user_login'] ?? '');
        // phpcs:enable WordPress.Security.NonceVerification.Missing
        // An empty username is generated from the email. A supplied one behaves as it always did.
        $login = (is_string($submitted_login) && '' === trim($submitted_login) && $email) ? self::generate_login($email) : sanitize_user($submitted_login, true);
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
        self::log_user_created((int) $user_id, $role);
        self::send_reset((int) $user_id);
        self::redirect('notice', 'created');
    }
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

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
        $holds_managed_role = $user && array_intersect((array) $user->roles, array_keys(self::managed_roles()));
        // Only someone with no managed role can keep what they have. An empty role is refused for everyone else.
        $keep_role = '' === $role && $user && !$holds_managed_role;
        if (!$user || !$email || (!$keep_role && !self::is_managed_role($role)) || (email_exists($email) && (int) email_exists($email) !== $user_id)) {
            self::redirect('error', 'invalid-user');
        }
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- authorize() ran above.
        // A form without the keep option preselected its first role, Administrator. A page open across an update or a script must not promote this person.
        if (!$keep_role && !$holds_managed_role && empty($_POST['idlm_role_choice_offered'])) { self::redirect('error', 'role-not-confirmed'); }
        // phpcs:enable WordPress.Security.NonceVerification.Missing
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
        $roles_before = array_values((array) $user->roles);
        $was_disabled = self::is_crm_access_disabled($user_id);
        self::apply_role_change($user, $role);
        self::save_phone_and_access($user_id);
        self::log_user_update($user, $roles_before, $was_disabled);
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
        $scopes_before = self::role_scopes();
        update_option(self::ROLE_SCOPES_OPTION, $scopes, false);
        foreach (array_keys($scopes_before + $scopes) as $scoped_role) {
            $was = $scopes_before[$scoped_role] ?? 'default';
            $now = $scopes[$scoped_role] ?? 'default';
            if ($was !== $now) { self::log_role_scope($scoped_role, $was, $now); }
        }
        self::redirect('notice', 'role-scopes-updated');
    }

    private static function enroll_editorial_user($user_id, $role) {
        if (!in_array($role, self::EDITORIAL_ROLES, true)) { return; }
        $user = new WP_User($user_id);
        foreach (idlm_crm_default_capabilities() as $capability) { $user->add_cap($capability); }
    }

    /** Take back what enroll_editorial_user() added. Only ever runs on an explicit role change. */
    private static function remove_editorial_grants($user) {
        foreach (idlm_crm_default_capabilities() as $capability) {
            if ('read' !== $capability) { $user->remove_cap($capability); }
        }
    }

    /**
     * Apply a saved role. An empty role, or one the person already holds, changes nothing, so other roles survive.
     * A new role replaces only managed roles; unmanaged roles from other plugins stay.
     *
     * @return bool Whether roles changed.
     */
    public static function apply_role_change($user, $role) {
        $held = array_values((array) $user->roles);
        if ('' === (string) $role || in_array($role, $held, true)) { return false; }
        foreach (array_keys(self::managed_roles()) as $managed) {
            if (in_array($managed, $held, true)) { $user->remove_role($managed); }
        }
        $user->add_role($role);
        if (array_intersect($held, self::EDITORIAL_ROLES) && !in_array($role, self::EDITORIAL_ROLES, true)) {
            self::remove_editorial_grants($user);
        }
        self::enroll_editorial_user((int) $user->ID, $role);
        return true;
    }

    /** A unique username from the email's local part: sanitized, with a numeric suffix on collision. */
    public static function generate_login($email) {
        $local = strtolower(sanitize_user((string) strstr((string) $email, '@', true), true));
        $local = substr(trim($local, '.-_@ '), 0, 50);
        if ('' === $local) { $local = 'user'; }
        $login = $local;
        for ($suffix = 2; username_exists($login) && $suffix < 10000; ++$suffix) { $login = $local . $suffix; }
        return $login;
    }

    private static function log_user_created($user_id, $role) {
        if (!class_exists('IDLM_Access_Change_Log')) { return; }
        $created = get_userdata($user_id);
        IDLM_Access_Change_Log::record('user_created', 'user', $user_id, $created ? $created->display_name : '', array(), array('roles' => array($role)));
    }

    private static function log_user_update($user, $roles_before, $was_disabled) {
        if (!class_exists('IDLM_Access_Change_Log')) { return; }
        $user_id = (int) $user->ID;
        $current = get_userdata($user_id);
        $name = $current ? $current->display_name : $user->display_name;
        $roles_after = array_values((array) $user->roles);
        if (array_diff($roles_before, $roles_after) || array_diff($roles_after, $roles_before)) {
            IDLM_Access_Change_Log::record('role_changed', 'user', $user_id, $name, array('roles' => $roles_before), array('roles' => $roles_after));
        }
        $is_disabled = self::is_crm_access_disabled($user_id);
        if ($is_disabled !== $was_disabled) {
            IDLM_Access_Change_Log::record($is_disabled ? 'access_suspended' : 'access_restored', 'user', $user_id, $name);
        }
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
        $allowed = array_flip(array_merge(array('administrator', 'idlm_pre_sales', 'idlm_design', 'idlm_design_rep'), (array) idlm_get_crm_role_slugs()));
        return array_intersect_key($all, $allowed);
    }

    private static function is_managed_role($role) { return isset(self::managed_roles()[$role]); }
    private static function authorize($action) {
        if (!current_user_can('manage_options')) { wp_die('You do not have permission to manage CRM users.', 403); }
        check_admin_referer($action);
        $request_method = isset($_SERVER['REQUEST_METHOD']) && is_string($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        if ('POST' !== $request_method) { wp_die('Invalid request.', 405); }
    }
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
        // An empty role keeps a person's unmanaged roles, and so whatever manage_options they hold through them.
        if ($user_id === get_current_user_id() && (('' !== $new_role && 'administrator' !== $new_role) || $disabled)) { self::redirect('error', 'self-lockout'); }
        if (user_can($user_id, 'manage_options') && self::would_leave_no_active_administrator($user_id, $new_role, $disabled)) { self::redirect('error', 'last-admin'); }
    }

    /** Suspended administrators do not count. True only when the change would leave zero active ones. */
    public static function would_leave_no_active_administrator($user_id, $new_role, $disabled) {
        $user_id = absint($user_id);
        $active_others = 0;
        foreach (get_users(array('role' => 'administrator', 'fields' => 'ID')) as $admin_id) {
            if ((int) $admin_id !== $user_id && !self::is_crm_access_disabled((int) $admin_id)) { ++$active_others; }
        }
        if ($active_others > 0) { return false; }
        $user = get_userdata($user_id);
        $held = $user ? (array) $user->roles : array();
        // A kept role, or one the person already holds, leaves every role in place, so only suspension can still matter.
        $role_unchanged = '' === $new_role || in_array($new_role, $held, true);
        return !('administrator' === $new_role || $role_unchanged) || (bool) $disabled;
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

// Registered here because tests pin the IDLM_Admin hook list; the handler still needs manage_options, a nonce and POST.
add_action('admin_post_idlm_update_role_access', array('IDLM_Admin_Users_Controller', 'handle_update_role_access'));
