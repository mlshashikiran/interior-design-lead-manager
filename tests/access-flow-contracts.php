<?php
/**
 * User access and permissions flow: role changes, safeguards, role editor, parity and the change log.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

if (!defined('IDLM_TESTING')) { exit; }

/** Stored account double with the WP_User mutators the handlers call. */
final class IDLM_Test_Access_Account {
    public $ID; public $roles; public $caps; public $display_name; public $user_login; public $user_email; public $first_name = ''; public $last_name = '';
    public function __construct(int $id, array $roles, array $direct_caps = array(), string $name = '') {
        $this->ID = $id; $this->roles = $roles; $this->display_name = $name ?: 'Person ' . $id; $this->user_login = 'person' . $id; $this->user_email = 'person' . $id . '@example.test';
        $this->caps = array_fill_keys($roles, true) + array_fill_keys($direct_caps, true);
    }
    public function add_role($role) { if (!in_array($role, $this->roles, true)) { $this->roles[] = $role; } $this->caps[$role] = true; }
    public function remove_role($role) { $this->roles = array_values(array_diff($this->roles, array($role))); unset($this->caps[$role]); }
    public function add_cap($cap) { $this->caps[$cap] = true; }
    public function remove_cap($cap) { unset($this->caps[$cap]); }
}
if (!class_exists('WP_User')) {
    class WP_User {
        private $account;
        public function __construct($id) { $this->account = $GLOBALS['idlm_test_users'][(int) $id]; }
        public function add_cap($cap) { $this->account->add_cap($cap); }
        public function remove_cap($cap) { $this->account->remove_cap($cap); }
    }
}
if (!function_exists('username_exists')) {
    function username_exists($login) {
        foreach ($GLOBALS['idlm_test_users'] as $user) { if (($user->user_login ?? '') === $login) { return (int) $user->ID; } }
        return false;
    }
    function email_exists($email) {
        foreach ($GLOBALS['idlm_test_users'] as $user) { if (($user->user_email ?? '') === $email) { return (int) $user->ID; } }
        return false;
    }
    function wp_insert_user($data) {
        $id = max(array_merge(array(100), array_keys($GLOBALS['idlm_test_users']))) + 1;
        $account = new IDLM_Test_Access_Account($id, array($data['role']));
        $account->user_login = $data['user_login']; $account->user_email = $data['user_email']; $account->display_name = $data['user_login'];
        $GLOBALS['idlm_test_users'][$id] = $account;
        return $id;
    }
    function wp_update_user($data) { return (int) $data['ID']; }
    function retrieve_password($login) { return true; }
}

if (!function_exists('idlm_crm_default_capabilities')) {
    // The plugin file cannot load under tests, so take the one real definition from it rather than a copy.
    preg_match('/function idlm_crm_default_capabilities\(.*?\n}\n/s', (string) file_get_contents(dirname(__DIR__) . '/interior-design-lead-manager.php'), $idlm_default_caps_source);
    eval($idlm_default_caps_source[0]); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Test-only load of a pure plugin function.
}

function idlm_access_fixture(): void {
    $GLOBALS['idlm_test_extra_roles'] = array(
        'administrator' => array('name' => 'Administrator'), 'editor' => array('name' => 'Editor'), 'author' => array('name' => 'Author'), 'contributor' => array('name' => 'Contributor'),
        'sales_manager' => array('name' => 'Sales Manager'), 'sales_executive' => array('name' => 'Sales Executive'), 'idlm_pre_sales' => array('name' => 'Pre-sales representative'),
        'shop_manager' => array('name' => 'Shop manager'),
    );
    $GLOBALS['idlm_test_redirect_throws'] = true;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    idlm_test_login(9, array('sales_manager'), array('manage_options'));
}

function idlm_access_account(int $id, array $roles, array $direct_caps = array(), string $name = ''): IDLM_Test_Access_Account {
    $GLOBALS['idlm_test_users'][$id] = new IDLM_Test_Access_Account($id, $roles, $direct_caps, $name);
    return $GLOBALS['idlm_test_users'][$id];
}

/** Run a handler to its redirect and return the location. */
function idlm_access_run(callable $handler, array $post): string {
    $_POST = $post;
    try {
        $handler();
    } catch (IDLM_Test_Response $redirect) {
        return (string) ($redirect->data['redirect'] ?? ($redirect->data['message'] ?? ''));
    }
    throw new RuntimeException('The handler did not redirect.');
}

function idlm_access_update_user(int $id, string $role, array $extra = array()): string {
    return idlm_access_run(array('IDLM_Admin_Users_Controller', 'handle_update'), array('user_id' => $id, 'role' => $role, 'email' => 'person' . $id . '@example.test', 'display_name' => 'Person ' . $id) + $extra);
}

function idlm_access_update_role(string $role, string $scope, array $permissions = array()): string {
    return idlm_access_run(array('IDLM_Admin_Users_Controller', 'handle_update_role_access'), array('access_role' => $role, 'role_scope' => $scope, 'permissions' => array_fill_keys($permissions, '1')));
}

function idlm_access_events(): array {
    return array_column(IDLM_Access_Change_Log::entries(), 'event');
}

idlm_test('saving a role the person already holds leaves every role alone', static function(): void {
    idlm_access_fixture();
    $account = idlm_access_account(5, array('sales_executive', 'sales_manager', 'shop_manager'));
    assert_contains('updated', idlm_access_update_user(5, 'sales_manager'));
    assert_same(array('sales_executive', 'sales_manager', 'shop_manager'), $account->roles);
    assert_same(array(), idlm_access_events(), 'No role change means no role entry.');
});

idlm_test('a new role replaces managed roles and keeps unmanaged ones', static function(): void {
    idlm_access_fixture();
    $account = idlm_access_account(5, array('sales_executive', 'shop_manager', 'sales_manager'));
    assert_contains('updated', idlm_access_update_user(5, 'idlm_pre_sales'));
    sort($account->roles);
    assert_same(array('idlm_pre_sales', 'shop_manager'), $account->roles);
    assert_same(array('role_changed'), idlm_access_events());
});

idlm_test('template preselects Administrator for a person with several managed roles', static function(): void {
    idlm_access_fixture();
    $roles = $GLOBALS['idlm_test_extra_roles'];
    assert_same('administrator', IDLM_Admin_Users_Controller::preselected_role(array('sales_manager', 'administrator', 'editor'), $roles));
    assert_same('sales_manager', IDLM_Admin_Users_Controller::preselected_role(array('shop_manager', 'sales_manager'), array('sales_manager' => array())));
    assert_same('', IDLM_Admin_Users_Controller::preselected_role(array('shop_manager'), array('sales_manager' => array())));
});

idlm_test('last administrator safeguard ignores suspended administrators and blocks only zero active', static function(): void {
    idlm_access_fixture();
    $active = idlm_access_account(2, array('administrator'));
    idlm_access_account(3, array('administrator'));
    update_user_meta(3, IDLM_Admin_Users_Controller::DISABLED_META_KEY, '1');

    // Two administrators on file, one suspended: the active one is the last.
    assert_contains('last-admin', idlm_access_update_user(2, 'administrator', array('crm_access_disabled' => '1')));
    assert_contains('last-admin', idlm_access_update_user(2, 'sales_manager'));
    assert_same(array('administrator'), $active->roles);
    assert_same('', get_user_meta(2, IDLM_Admin_Users_Controller::DISABLED_META_KEY, true));

    // A suspended administrator can change while another active one remains.
    assert_false(IDLM_Admin_Users_Controller::would_leave_no_active_administrator(3, 'sales_manager', true));
    assert_contains('updated', idlm_access_update_user(3, 'sales_manager', array('crm_access_disabled' => '1')));
    assert_same(array('sales_manager'), $GLOBALS['idlm_test_users'][3]->roles);

    // With another active administrator the change goes through.
    idlm_access_account(4, array('administrator'));
    assert_contains('updated', idlm_access_update_user(2, 'administrator', array('crm_access_disabled' => '1')));

    // Restoring the only administrator is always allowed; keeping them suspended is not.
    unset($GLOBALS['idlm_test_users'][4]);
    assert_false(IDLM_Admin_Users_Controller::would_leave_no_active_administrator(2, 'administrator', false));
    assert_true(IDLM_Admin_Users_Controller::would_leave_no_active_administrator(2, 'administrator', true));

    // A held second role changes nothing, so the person stays the administrator.
    idlm_access_account(2, array('administrator', 'sales_manager'));
    assert_false(IDLM_Admin_Users_Controller::would_leave_no_active_administrator(2, 'sales_manager', false));
});

idlm_test('your own administrator role and access stay protected', static function(): void {
    idlm_access_fixture();
    idlm_test_login(9, array('administrator'), array('manage_options'));
    idlm_access_account(9, array('administrator'));
    idlm_access_account(2, array('administrator'));
    assert_contains('self-lockout', idlm_access_update_user(9, 'sales_manager'));
    assert_contains('self-lockout', idlm_access_update_user(9, 'administrator', array('crm_access_disabled' => '1')));
});

idlm_test('leaving a website role removes only the enrolled capabilities on an explicit change', static function(): void {
    idlm_access_fixture();
    $enrolled = array_values(array_diff(idlm_crm_default_capabilities(), array('read')));
    $account = idlm_access_account(5, array('editor', 'shop_manager'), array_merge(array('read', 'idlm_assign_leads'), $enrolled));

    // Same role: never touched.
    idlm_access_update_user(5, 'editor');
    foreach ($enrolled as $capability) { assert_true(!empty($account->caps[$capability])); }

    // One website role to another keeps the enrolment.
    idlm_access_update_user(5, 'author');
    foreach ($enrolled as $capability) { assert_true(!empty($account->caps[$capability])); }

    // Away from website roles: enrolled capabilities go, read and other direct grants stay.
    idlm_access_update_user(5, 'sales_executive');
    foreach ($enrolled as $capability) { assert_false(isset($account->caps[$capability]), $capability); }
    assert_true(!empty($account->caps['read']));
    assert_true(!empty($account->caps['idlm_assign_leads']));

    // A change that never involved a website role removes nothing.
    $other = idlm_access_account(6, array('sales_executive'), $enrolled);
    idlm_access_update_user(6, 'sales_manager');
    foreach ($enrolled as $capability) { assert_true(!empty($other->caps[$capability])); }

    // Moving into a website role still enrols.
    $joining = idlm_access_account(7, array('sales_executive'));
    idlm_access_update_user(7, 'contributor');
    foreach ($enrolled as $capability) { assert_true(!empty($joining->caps[$capability])); }
});

idlm_test('an empty username is generated from the email and a supplied one behaves as before', static function(): void {
    idlm_access_fixture();
    assert_same('jane.doe', IDLM_Admin_Users_Controller::generate_login('Jane.Doe@example.test'));
    idlm_access_account(20, array('sales_executive'))->user_login = 'jane.doe';
    assert_same('jane.doe2', IDLM_Admin_Users_Controller::generate_login('jane.doe@other.test'));
    idlm_access_account(21, array('sales_executive'))->user_login = 'jane.doe2';
    assert_same('jane.doe3', IDLM_Admin_Users_Controller::generate_login('jane.doe@other.test'));
    assert_same('user', IDLM_Admin_Users_Controller::generate_login('+++@example.test'));

    $create = array('IDLM_Admin_Users_Controller', 'handle_create');
    $base = array('email' => 'new.person@example.test', 'role' => 'sales_executive');
    assert_contains('created', idlm_access_run($create, $base + array('user_login' => '')));
    assert_true(false !== username_exists('new.person'));

    // Supplied logins are used as given, and a taken one is still refused.
    assert_contains('created', idlm_access_run($create, array('email' => 'second@example.test', 'role' => 'sales_executive', 'user_login' => 'chosen_name')));
    assert_true(false !== username_exists('chosen_name'));
    assert_contains('invalid-user', idlm_access_run($create, array('email' => 'third@example.test', 'role' => 'sales_executive', 'user_login' => 'chosen_name')));
    assert_contains('invalid-user', idlm_access_run($create, array('email' => 'fourth@example.test', 'role' => 'sales_executive', 'user_login' => '!!!')));

    // No role and no email are still refused.
    assert_contains('invalid-user', idlm_access_run($create, array('email' => 'fifth@example.test', 'role' => '', 'user_login' => '')));
    assert_contains('invalid-user', idlm_access_run($create, array('email' => '', 'role' => 'sales_executive', 'user_login' => '')));
    assert_same(array('user_created', 'user_created'), idlm_access_events());
});

idlm_test('role access handler updates one role scope and never overwrites the others', static function(): void {
    idlm_access_fixture();
    $stored = array('sales_manager' => 'assigned', 'editor' => 'all', 'retired_role' => 'assigned');
    update_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION, $stored);

    assert_contains('role-access-updated', idlm_access_update_role('sales_executive', 'all'));
    assert_same($stored + array('sales_executive' => 'all'), get_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION));

    assert_contains('role-access-updated', idlm_access_update_role('sales_manager', 'default'));
    assert_same(array('editor' => 'all', 'retired_role' => 'assigned', 'sales_executive' => 'all'), get_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION));

    // Website roles change scope only; their capabilities are not touched.
    $GLOBALS['idlm_test_role_cap_overrides']['editor'] = array('edit_posts' => true, 'idlm_access_leads' => true);
    assert_contains('role-access-updated', idlm_access_update_role('editor', 'assigned'));
    assert_same('assigned', get_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION)['editor']);
    assert_true(get_role('editor')->has_cap('idlm_access_leads'));
});

idlm_test('role access handler refuses Administrator, bad input, weak access and non-POST', static function(): void {
    idlm_access_fixture();
    $before = array('sales_manager' => 'assigned');
    update_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION, $before);
    foreach (array(array('administrator', 'all'), array('not_a_role', 'all'), array('shop_manager', 'all'), array('sales_manager', 'everyone'), array('sales_manager', '')) as $bad) {
        assert_contains('invalid-role', idlm_access_update_role($bad[0], $bad[1]));
    }
    assert_same($before, get_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION));

    $_SERVER['REQUEST_METHOD'] = 'GET';
    assert_contains('Invalid request', idlm_access_update_role('sales_manager', 'all'));
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $GLOBALS['idlm_test_caps'] = array();
    assert_contains('permission', idlm_access_update_role('sales_manager', 'all'));
    assert_same($before, get_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION));
});

idlm_test('role access handler removes unticked capabilities and stays inside selectable permissions', static function(): void {
    idlm_access_fixture();
    $GLOBALS['idlm_test_role_cap_overrides']['sales_executive'] = array('idlm_access_leads' => true, 'idlm_assign_leads' => true, 'edit_posts' => true);
    idlm_access_update_role('sales_executive', 'default', array('idlm_access_leads', 'idlm_view_quotes'));
    $role = get_role('sales_executive');
    assert_true($role->has_cap('idlm_access_leads'));
    assert_true($role->has_cap('idlm_view_quotes'));
    assert_false($role->has_cap('idlm_assign_leads'), 'An unticked permission is removed.');
    assert_true($role->has_cap('edit_posts'), 'Capabilities outside the list stay.');

    $entry = IDLM_Access_Change_Log::entries()[0];
    assert_same(array('idlm_view_quotes'), $entry['after']['added']);
    assert_same(array('idlm_assign_leads'), $entry['before']['removed']);

    // Pre-sales can only ever change its two permissions.
    idlm_access_update_role('idlm_pre_sales', 'default', array('idlm_access_leads', 'idlm_qualify_leads', 'idlm_approve_quotes'));
    assert_false(get_role('idlm_pre_sales')->has_cap('idlm_approve_quotes'));
});

idlm_test('legacy role handlers still save, with the same semantics, and log changes', static function(): void {
    idlm_access_fixture();
    update_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION, array('sales_manager' => 'assigned'));
    idlm_access_run(array('IDLM_Admin_Users_Controller', 'handle_update_role_scopes'), array('role_scopes' => array('sales_executive' => 'all')));
    assert_same(array('sales_executive' => 'all'), get_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION));
    $events = idlm_access_events();
    assert_same(array('role_scope_changed', 'role_scope_changed'), $events);

    $GLOBALS['idlm_test_role_cap_overrides']['sales_executive'] = array('idlm_assign_leads' => true);
    assert_contains('role-permissions-updated', idlm_access_run(array('IDLM_Admin_Users_Controller', 'handle_update_role_permissions'), array('permission_role' => 'sales_executive', 'permissions' => array('idlm_access_leads' => '1'))));
    assert_true(get_role('sales_executive')->has_cap('idlm_access_leads'));
    assert_false(get_role('sales_executive')->has_cap('idlm_assign_leads'));
    assert_same('role_permissions_changed', idlm_access_events()[0]);

    // Saving nothing new logs nothing.
    $count = count(IDLM_Access_Change_Log::entries());
    idlm_access_run(array('IDLM_Admin_Users_Controller', 'handle_update_role_permissions'), array('permission_role' => 'sales_executive', 'permissions' => array('idlm_access_leads' => '1')));
    assert_same($count, count(IDLM_Access_Change_Log::entries()));
});

idlm_test('describe_user_access matches the live policy across every branch', static function(): void {
    $people = array(
        'administrator' => array(array('administrator'), array(), array('manage_options' => true, 'idlm_access_leads' => true)),
        'manage_options only' => array(array('shop_manager'), array('manage_options'), array()),
        'assigner' => array(array('sales_manager'), array(), array('idlm_access_leads' => true, 'idlm_assign_leads' => true)),
        'plain rep' => array(array('sales_executive'), array(), array('idlm_access_leads' => true)),
        'pre-sales' => array(array('idlm_pre_sales'), array(), array('idlm_access_leads' => true)),
        'pre-sales assigner' => array(array('idlm_pre_sales'), array('idlm_assign_leads'), array('idlm_access_leads' => true)),
        'no access role' => array(array('sales_executive'), array(), array()),
        'direct grant' => array(array('shop_manager'), array('idlm_access_leads'), array()),
        'assign without access' => array(array('shop_manager'), array('idlm_assign_leads'), array()),
        'editor direct grant' => array(array('editor'), array('idlm_access_leads'), array()),
        'editor role grant only' => array(array('editor'), array(), array('idlm_access_leads' => true)),
        'editor and rep' => array(array('editor', 'sales_executive'), array(), array('sales_executive_only' => true)),
        'mixed scopes' => array(array('sales_manager', 'sales_executive'), array(), array('idlm_access_leads' => true, 'idlm_assign_leads' => true)),
    );
    $scope_sets = array(array(), array('sales_executive' => 'all'), array('sales_manager' => 'assigned', 'sales_executive' => 'all', 'idlm_pre_sales' => 'all', 'editor' => 'assigned', 'shop_manager' => 'all'), array('sales_manager' => 'all', 'sales_executive' => 'assigned', 'idlm_pre_sales' => 'assigned', 'editor' => 'all', 'shop_manager' => 'assigned'));
    $checked = 0;
    foreach ($people as $name => $person) {
        foreach (array(false, true) as $suspended) {
            foreach ($scope_sets as $scopes) {
                foreach (array('0', '1') as $reps_see_all) {
                    foreach (array('1', '') as $policy) {
                        idlm_test_reset_environment();
                        unset($GLOBALS['idlm_test_users'][50]);
                        update_option('idlm_role_lead_scopes', $scopes);
                        update_option('idlm_reps_see_all_leads', $reps_see_all);
                        update_option('idlm_crm_access_policy_version', $policy);
                        $role_caps = $person[2];
                        foreach ($person[0] as $role) { $GLOBALS['idlm_test_role_cap_overrides'][$role] = $role_caps; }
                        if ('editor and rep' === $name) { $GLOBALS['idlm_test_role_cap_overrides'] = array('editor' => array(), 'sales_executive' => array('idlm_access_leads' => true)); $role_caps = array('idlm_access_leads' => true); }
                        idlm_test_login(50, $person[0], $person[1]);
                        $GLOBALS['idlm_test_caps'] = $role_caps + array_fill_keys($person[1], true);
                        if ($suspended) { update_user_meta(50, 'idlm_crm_access_disabled', '1'); }
                        $label = $name . ' suspended=' . (int) $suspended . ' scopes=' . json_encode($scopes) . ' all=' . $reps_see_all . ' policy=' . $policy;
                        $user = get_userdata(50);
                        $described = IDLM_Lead_Access_Policy::describe_user_access($user);
                        assert_same(IDLM_Lead_Access_Policy::has_rep_access(), $described['has_access'], 'has_rep_access: ' . $label);
                        assert_same(IDLM_Lead_Access_Policy::can_assign(), $described['can_assign'], 'can_assign: ' . $label);
                        assert_same(IDLM_Lead_Access_Policy::restriction_user_id(), $described['restriction_user_id'], 'restriction: ' . $label);
                        assert_same(IDLM_Lead_Access_Policy::restriction_user_id(false), IDLM_Lead_Access_Policy::describe_user_access($user, false)['restriction_user_id'], 'restriction local: ' . $label);
                        $params = array();
                        $sql = IDLM_Lead_Access_Policy::scope_sql('l', $params);
                        $expected = false !== strpos($sql, '1=0') ? 'none' : ('' === $sql ? 'all' : 'assigned');
                        assert_same($expected, $described['sees'], 'sees: ' . $label);
                        ++$checked;
                    }
                }
            }
        }
    }
    assert_true($checked > 400, 'The parity matrix must stay broad.');
});

idlm_test('access summary states real access and lists extras without changing them', static function(): void {
    idlm_access_fixture();
    $GLOBALS['idlm_test_role_cap_overrides']['sales_executive'] = array('idlm_access_leads' => true, 'idlm_view_quotes' => true);
    update_option('idlm_crm_access_policy_version', '1');
    $account = idlm_access_account(5, array('sales_executive'), array('idlm_access_leads', 'idlm_cancel_quotes', 'some_old_cap'));
    $before = $account->caps;
    $summary = IDLM_Admin_Users_Controller::access_summary($account);
    assert_same('Only assigned leads', $summary['sees']);
    assert_same(array('Access CRM leads'), $summary['groups']['Leads']);
    assert_same(array('View quotations'), $summary['groups']['Quotations']);
    assert_same(array('Cancel quotations', 'some_old_cap'), $summary['extra'], 'Grants a role already gives are not extra.');
    assert_same($before, $account->caps);

    update_user_meta(5, 'idlm_crm_access_disabled', '1');
    $suspended = IDLM_Admin_Users_Controller::access_summary($account);
    assert_same('No CRM access', $suspended['sees']);
    assert_same(array(), $suspended['groups']);
});

idlm_test('default visibility sentence states the real outcome for each kind of role', static function(): void {
    idlm_access_fixture();
    update_option('idlm_crm_access_policy_version', '1');
    update_option('idlm_reps_see_all_leads', '0');
    $rep = IDLM_Admin_Users_Controller::default_scope_outcome('sales_executive', true, false);
    assert_same('assigned', $rep['outcome']);
    assert_true($rep['depends_on_setting']);
    update_option('idlm_reps_see_all_leads', '1');
    assert_same('all', IDLM_Admin_Users_Controller::default_scope_outcome('sales_executive', true, false)['outcome']);
    assert_same('assigned', IDLM_Admin_Users_Controller::default_scope_outcome('idlm_pre_sales', true, false)['outcome']);
    assert_same('all', IDLM_Admin_Users_Controller::default_scope_outcome('sales_manager', true, true)['outcome']);
    assert_false(IDLM_Admin_Users_Controller::default_scope_outcome('sales_manager', true, true)['depends_on_setting']);
    assert_same('none', IDLM_Admin_Users_Controller::default_scope_outcome('sales_executive', false, false)['outcome']);
    assert_same('none', IDLM_Admin_Users_Controller::default_scope_outcome('editor')['outcome']);
});

idlm_test('job-role labels and pick lists use the CRM wording', static function(): void {
    idlm_access_fixture();
    $GLOBALS['idlm_test_extra_roles'] += array('agent' => array('name' => 'Agent'), 'agentadmin' => array('name' => 'Agent admin'));
    assert_same('Sales representative', IDLM_Admin_Users_Controller::role_label('agent'));
    assert_same('Sales manager', IDLM_Admin_Users_Controller::role_label('agentadmin'));
    assert_same('Pre-sales', IDLM_Admin_Users_Controller::role_label('idlm_pre_sales'));
    assert_same('Editor (website role)', IDLM_Admin_Users_Controller::role_label('editor'));
    assert_same('Sales Executive', IDLM_Admin_Users_Controller::role_label('sales_executive'));

    $list = IDLM_Admin_Users_Controller::pick_list_roles($GLOBALS['idlm_test_extra_roles']);
    assert_same(array('editor', 'author', 'contributor'), array_keys($list['website']));
    $slugs = array_keys($list['main']);
    assert_same('administrator', end($slugs), 'Administrator comes last.');
    $held = IDLM_Admin_Users_Controller::pick_list_roles($GLOBALS['idlm_test_extra_roles'], array('author'));
    assert_true(isset($held['main']['author']), 'A held website role stays in the list.');

});

idlm_test('access change log keeps the newest 200, names only, and never blocks a save', static function(): void {
    idlm_access_fixture();
    assert_same(array(), IDLM_Access_Change_Log::entries());
    assert_false(array_key_exists(IDLM_Access_Change_Log::OPTION, $GLOBALS['idlm_test_options']), 'Reading must not create the option.');

    for ($i = 1; $i <= 205; $i++) {
        IDLM_Access_Change_Log::record('access_suspended', 'user', $i, 'Person ' . $i);
    }
    $entries = IDLM_Access_Change_Log::entries();
    assert_same(200, count($entries));
    assert_same('Person 205', $entries[0]['target_name']);
    assert_same('Person 6', $entries[199]['target_name']);
    foreach (array('time', 'actor_id', 'actor_name', 'event', 'target_type', 'target_id', 'target_name', 'before', 'after') as $key) {
        assert_true(array_key_exists($key, $entries[0]), $key);
    }
    assert_same(9, $entries[0]['actor_id']);

    delete_option(IDLM_Access_Change_Log::OPTION);
    $GLOBALS['idlm_test_users'][9]->display_name = 'boss@example.test';
    IDLM_Access_Change_Log::record('role_changed', 'user', 5, 'someone@example.test', array('roles' => array('agent'), 'email' => 'x@example.test', 'phone' => '+15551234567'), array('roles' => array('agentadmin@x'), 'password' => 'hunter2', 'token' => 'abc'));
    IDLM_Access_Change_Log::record('role_changed', 'user', 6, '+91 98765 43210');
    $serialized = serialize(IDLM_Access_Change_Log::entries());
    foreach (array('@example.test', 'hunter2', 'abc', '98765', '5551234567') as $forbidden) {
        assert_false(false !== strpos($serialized, $forbidden), 'Logged forbidden value: ' . $forbidden);
    }
    assert_same('User #6', IDLM_Access_Change_Log::entries()[0]['target_name']);
    assert_same('User #5', IDLM_Access_Change_Log::entries()[1]['target_name']);
    assert_same('User #9', IDLM_Access_Change_Log::entries()[0]['actor_name']);
    assert_false(IDLM_Access_Change_Log::record('not_an_event', 'user', 1, 'x'));

    // Log writes can fail without blocking the handler.
    $GLOBALS['idlm_test_option_write_failures'] = array(IDLM_Access_Change_Log::OPTION);
    idlm_access_account(5, array('sales_executive'));
    assert_contains('updated', idlm_access_update_user(5, 'sales_manager'));
    assert_same(array('sales_manager'), $GLOBALS['idlm_test_users'][5]->roles);
});

idlm_test('suspend and restore are logged once from the user handler', static function(): void {
    idlm_access_fixture();
    idlm_access_account(5, array('sales_executive'));
    idlm_access_update_user(5, 'sales_executive', array('crm_access_disabled' => '1'));
    idlm_access_update_user(5, 'sales_executive', array('crm_access_disabled' => '1'));
    idlm_access_update_user(5, 'sales_executive');
    assert_same(array('access_restored', 'access_suspended'), idlm_access_events());
});

idlm_test('updates cannot change access: no bootstrap or activation path reaches the new writes', static function(): void {
    $root = dirname(__DIR__);
    $bootstrap = (string) file_get_contents($root . '/interior-design-lead-manager.php');
    foreach (array('IDLM_Access_Change_Log::', 'idlm_access_change_log', 'apply_role_access', 'apply_role_change', 'handle_update_role_access', 'generate_login') as $write) {
        assert_false(false !== strpos($bootstrap, $write), 'Bootstrap must not reach ' . $write);
    }
    assert_contains("require_once IDLM_PATH . 'includes/class-access-change-log.php';", $bootstrap);

    $log = (string) file_get_contents($root . '/includes/class-access-change-log.php');
    assert_false((bool) preg_match('/\badd_(?:action|filter)\s*\(/', $log), 'The log class must not hook into WordPress.');
    assert_contains("add_option(self::OPTION, \$entries, '', 'no')", $log);
    assert_contains('update_option(self::OPTION, $entries, false)', $log);

    $controller = (string) file_get_contents($root . '/includes/class-admin-users-controller.php');
    preg_match_all('/\badd_action\s*\(\s*\'([^\']+)\'/', $controller, $hooks);
    assert_same(array('admin_post_idlm_update_role_access'), $hooks[1], 'The only hook this file adds is the new handler.');

    foreach (array_merge(glob($root . '/includes/*.php') ?: array(), glob($root . '/templates/admin/*.php') ?: array()) as $file) {
        $source = (string) file_get_contents($file);
        $base = basename($file);
        if (!in_array($base, array('class-access-change-log.php', 'class-admin-users-controller.php'), true)) {
            assert_false(false !== strpos($source, 'IDLM_Access_Change_Log::record'), 'Only the users controller records: ' . $base);
            assert_false(false !== strpos($source, 'apply_role_access'), 'Only the handler applies role access: ' . $base);
        }
    }
    foreach (array('users-roles.php', 'users-activity.php', 'users.php') as $template) {
        assert_false((bool) preg_match('/\b(?:update_option|add_option|delete_option|add_cap|remove_cap|add_role|remove_role|update_user_meta|set_role)\s*\(/', (string) file_get_contents($root . '/templates/admin/' . $template)), $template . ' must not write.');
    }
});

idlm_test('the change log is kept out of backups and restores and open counts use one prepared query', static function(): void {
    require_once dirname(__DIR__) . '/includes/class-backup.php';
    $backup = new ReflectionMethod('IDLM_Backup', 'backup_option');
    $backup->setAccessible(true);
    assert_false($backup->invoke(null, 'idlm_access_change_log'), 'A restore must not rewind the access log.');
    assert_true($backup->invoke(null, 'idlm_role_lead_scopes'), 'Role scopes stay in backups.');

    $source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-users-controller.php');
    assert_contains('$wpdb->prepare("SELECT assigned_rep_id, COUNT(*) AS open_leads', $source);
    assert_contains("is_duplicate = 0 AND COALESCE(stage, '') NOT IN ('Won', 'Lost') GROUP BY assigned_rep_id", $source);
    assert_same(array(), IDLM_Admin_Users_Controller::open_lead_counts(array()));
});

idlm_test('role editor, invite and suspend markup keep their gated contracts', static function(): void {
    $root = dirname(__DIR__);
    $users = (string) file_get_contents($root . '/templates/admin/users.php');
    $roles = (string) file_get_contents($root . '/templates/admin/users-roles.php');
    $activity = (string) file_get_contents($root . '/templates/admin/users-activity.php');
    $script = (string) file_get_contents($root . '/assets/js/users-logs.js');
    foreach (array('name="action" value="idlm_update_role_access"', "wp_nonce_field('idlm_update_role_access')", 'name="access_role"', 'name="role_scope"', 'name="permissions[') as $contract) { assert_contains($contract, $roles); }
    foreach (array('Choose a role', 'Set a username', 'Show website roles', 'data-idlm-suspend-note', 'data-idlm-open-leads') as $contract) { assert_contains($contract, $users); }
    assert_contains('idlm-state is-empty', $activity);
    assert_false((bool) preg_match('/\sstyle\s*=/i', $users . $roles . $activity), 'STD-CSS-7: no inline style.');
    foreach (array('window.IDLMDialog.confirm', 'data-idlm-permission-warnings', 'openLeadsSentence', 'data-idlm-show-website-roles') as $contract) { assert_contains($contract, $script); }
    assert_false((bool) preg_match('/\b(?:alert|confirm|prompt)\s*\(/', str_replace('IDLMDialog.confirm(', '', $script)), 'STD-CRM-8: native dialogs are banned.');
    $quotations = (string) file_get_contents($root . '/assets/js/quotations.js');
    assert_contains("input:not([type=\"submit\"],[type=\"button\"],[type=\"reset\"],[type=\"image\"],[type=\"file\"])", $quotations);
    $leads = (string) file_get_contents($root . '/assets/js/admin-app.js');
    assert_contains("isCrmDesign ? new URLSearchParams(window.location.search).get('rep') : null", $leads);
});

idlm_test('a person with no managed role can keep it, and nothing promotes them without a real choice', static function(): void {
    idlm_access_fixture();
    idlm_access_account(2, array('administrator'));
    $owner = idlm_access_account(5, array('shop_manager'), array('manage_options'));

    // The Keep option changes no role and writes no role entry.
    assert_contains('updated', idlm_access_update_user(5, '', array('idlm_role_choice_offered' => '1')));
    assert_same(array('shop_manager'), $owner->roles);
    assert_contains('updated', idlm_access_update_user(5, ''));
    assert_same(array('shop_manager'), $owner->roles);
    assert_same(array(), idlm_access_events());

    // A page from the old design, or a script, posts Administrator with no marker.
    assert_contains('role-not-confirmed', idlm_access_update_user(5, 'administrator'));
    assert_contains('role-not-confirmed', idlm_access_update_user(5, 'sales_executive'));
    assert_same(array('shop_manager'), $owner->roles);

    // Choosing from a form that offered Keep still works and keeps the unmanaged role.
    assert_contains('updated', idlm_access_update_user(5, 'sales_executive', array('idlm_role_choice_offered' => '1')));
    assert_same(array('shop_manager', 'sales_executive'), $owner->roles);

    // Once they hold a managed role an empty role is refused, not treated as Keep.
    assert_contains('invalid-user', idlm_access_update_user(5, ''));
    assert_contains('invalid-user', idlm_access_update_user(2, '', array('idlm_role_choice_offered' => '1')));
    assert_same(array('administrator'), $GLOBALS['idlm_test_users'][2]->roles);

    // People who already hold a managed role keep the old behaviour with no marker.
    idlm_access_account(6, array('sales_executive'));
    assert_contains('updated', idlm_access_update_user(6, 'sales_manager'));
    assert_contains('invalid-user', idlm_access_update_user(6, 'shop_manager', array('idlm_role_choice_offered' => '1')));
    assert_same(array('sales_manager'), $GLOBALS['idlm_test_users'][6]->roles);
    assert_false(IDLM_Admin_Users_Controller::apply_role_change($GLOBALS['idlm_test_users'][6], ''), 'An empty role must never strip managed roles.');
    assert_same(array('sales_manager'), $GLOBALS['idlm_test_users'][6]->roles);

    $users = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/users.php');
    assert_same(2, substr_count($users, '<option value="" selected>Keep current role</option>'), 'Both designs offer Keep.');
    assert_same(2, substr_count($users, '<option value="">Choose a role</option>'), 'No invite form preselects a role.');
    assert_contains('name="idlm_role_choice_offered"', $users);
    assert_same(array('role_changed', 'role_changed'), idlm_access_events());
});

idlm_test('someone who manages the site through an unmanaged role can save their own account safely', static function(): void {
    idlm_access_fixture();
    idlm_test_login(9, array('shop_manager'), array('manage_options'));
    $self = idlm_access_account(9, array('shop_manager'), array('manage_options'));
    idlm_access_account(2, array('administrator'));
    assert_contains('updated', idlm_access_update_user(9, ''));
    assert_same(array('shop_manager'), $self->roles);
    assert_contains('self-lockout', idlm_access_update_user(9, '', array('crm_access_disabled' => '1')));
    assert_same('', get_user_meta(9, IDLM_Admin_Users_Controller::DISABLED_META_KEY, true));

    // With no active administrator, keeping their role is safe; suspending them is not.
    unset($GLOBALS['idlm_test_users'][2]);
    assert_false(IDLM_Admin_Users_Controller::would_leave_no_active_administrator(9, '', false));
    assert_true(IDLM_Admin_Users_Controller::would_leave_no_active_administrator(9, '', true));
    assert_true(IDLM_Admin_Users_Controller::would_leave_no_active_administrator(9, 'sales_manager', false));
});

idlm_test('create, update and scope handlers refuse anything but POST', static function(): void {
    idlm_access_fixture();
    update_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION, array('sales_manager' => 'assigned'));
    idlm_access_account(5, array('sales_executive'));
    $_SERVER['REQUEST_METHOD'] = 'GET';
    foreach (array('handle_create', 'handle_update', 'handle_update_role_scopes', 'handle_update_role_permissions', 'handle_update_role_access') as $handler) {
        assert_contains('Invalid request', idlm_access_run(array('IDLM_Admin_Users_Controller', $handler), array('user_id' => 5, 'role' => 'sales_manager', 'email' => 'person5@example.test')), $handler);
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';
    assert_same(array('sales_manager' => 'assigned'), get_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION));
    assert_same(array('sales_executive'), $GLOBALS['idlm_test_users'][5]->roles);
});

idlm_test('crafted role access posts cannot widen a role or reach another one', static function(): void {
    idlm_access_fixture();
    $run = array('IDLM_Admin_Users_Controller', 'handle_update_role_access');
    update_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION, array('sales_manager' => 'assigned'));
    $GLOBALS['idlm_test_role_cap_overrides']['sales_executive'] = array('idlm_access_leads' => true);

    // Capabilities outside the list and nested values are ignored.
    idlm_access_run($run, array('access_role' => 'sales_executive', 'role_scope' => 'default', 'permissions' => array('manage_options' => '1', 'edit_users' => '1', 'idlm_access_leads' => array('1'), 'idlm_view_quotes' => '1')));
    $role = get_role('sales_executive');
    assert_false($role->has_cap('manage_options'));
    assert_false($role->has_cap('edit_users'));
    assert_false($role->has_cap('idlm_access_leads'), 'A nested value is not a tick.');
    assert_true($role->has_cap('idlm_view_quotes'));

    // Slug tampering never reaches Administrator, an unmanaged role or a second role.
    $admin_caps = get_role('administrator')->capabilities;
    foreach (array(array('ADMINISTRATOR', 'assigned'), array('administrator ', 'assigned'), array(array('administrator'), 'assigned'), array('shop_manager', 'all'), array('sales_executive', array('all'))) as $bad) {
        assert_contains('invalid-role', idlm_access_run($run, array('access_role' => $bad[0], 'role_scope' => $bad[1], 'permissions' => array('idlm_assign_leads' => '1'))));
    }
    assert_same($admin_caps, get_role('administrator')->capabilities);
    assert_same(array('sales_manager' => 'assigned'), get_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION));
    assert_contains('invalid-role', idlm_access_run(array('IDLM_Admin_Users_Controller', 'handle_update_role_permissions'), array('permission_role' => 'administrator', 'permissions' => array('idlm_assign_leads' => '1'))));
    assert_contains('invalid-role', idlm_access_run(array('IDLM_Admin_Users_Controller', 'handle_update_role_permissions'), array('permission_role' => 'editor', 'permissions' => array('idlm_assign_leads' => '1'))));

    // Every selectable permission has a box in the role editor, or saving it would silently remove it.
    $grouped = array_merge(...array_values(IDLM_Admin_Users_Controller::permission_groups()));
    assert_same(array(), array_values(array_diff(array_keys(IDLM_Admin_Users_Controller::selectable_permissions('')), $grouped)));
});

idlm_test('access summary follows real access for site managers, prerequisites and pre-sales', static function(): void {
    idlm_access_fixture();
    update_option('idlm_crm_access_policy_version', '1');

    // manage_options admits them to the CRM even when no role carries the access capability.
    $owner = idlm_access_account(5, array('shop_manager'), array('manage_options'));
    $summary = IDLM_Admin_Users_Controller::access_summary($owner);
    assert_same('All leads', $summary['sees']);
    assert_true(in_array('Access CRM leads', $summary['groups']['Leads'], true));
    assert_true($summary['is_admin']);

    // Quote permissions without View quotations do nothing, so they are not listed.
    $GLOBALS['idlm_test_role_cap_overrides']['sales_executive'] = array('idlm_access_leads' => true, 'idlm_create_quotes' => true, 'idlm_manage_quote_items' => true);
    $rep = IDLM_Admin_Users_Controller::access_summary(idlm_access_account(6, array('sales_executive')));
    assert_false(isset($rep['groups']['Quotations']));
    assert_same(array('Manage quotation items'), $rep['groups']['Quotation administration'], 'Catalogue grants stand alone.');

    // Restricted pre-sales also read leads they took in or qualified.
    $GLOBALS['idlm_test_role_cap_overrides']['idlm_pre_sales'] = array('idlm_access_leads' => true, 'idlm_qualify_leads' => true);
    $pre_sales = idlm_access_account(7, array('idlm_pre_sales'));
    assert_contains('took in or qualified', IDLM_Admin_Users_Controller::access_summary($pre_sales)['sees']);
    idlm_test_login(7, array('idlm_pre_sales'), array('idlm_access_leads'));
    $GLOBALS['idlm_test_users'][7] = $pre_sales;
    $params = array();
    assert_contains('intake_owner_id', IDLM_Lead_Access_Policy::scope_sql('l', $params, true));
    update_option(IDLM_Admin_Users_Controller::ROLE_SCOPES_OPTION, array('idlm_pre_sales' => 'all'));
    assert_same('All leads', IDLM_Admin_Users_Controller::access_summary($pre_sales)['sees']);

    // A suspended site manager keeps manage_options, and the screen says so.
    update_user_meta(5, 'idlm_crm_access_disabled', '1');
    $suspended = IDLM_Admin_Users_Controller::access_summary($owner);
    assert_same('No CRM access', $suspended['sees']);
    assert_true($suspended['suspended'] && $suspended['is_admin']);
    assert_contains('Settings, users and backups stay available', (string) file_get_contents(dirname(__DIR__) . '/templates/admin/users.php'));
    $script = (string) file_get_contents(dirname(__DIR__) . '/assets/js/users-logs.js');
    assert_false(false !== strpos($script, 'signed out'), 'Suspension does not end a WordPress session, so the prompt must not say it does.');
});

idlm_test('the Leads rep filter only narrows and stays out of the access scope', static function(): void {
    $source = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-collection-reads.php');
    $method = substr($source, (int) strpos($source, 'function ajax_get_leads_table'));
    $scope = strpos($method, '$where[] = $wpdb->prepare("assigned_rep_id = %d", $restricted_user_id);');
    $filter = strpos($method, '$where[] = $wpdb->prepare("assigned_rep_id = %d", intval($post_rep_id));');
    assert_true(false !== $scope && false !== $filter && $scope < $filter, 'The viewer scope is applied first and the filter adds to it.');
    assert_contains('$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";', $method);
    assert_false((bool) preg_match('/\$_(?:GET|REQUEST)\[[\'"]rep[\'"]\]/', $source), 'The query reads no URL rep value.');
    assert_contains("'rep_id' => \$post_rep_id", $method);
});

idlm_test('the Activity tab escapes what it shows and long names stay valid text', static function(): void {
    idlm_access_fixture();
    $long = str_repeat('प्र', 60);
    IDLM_Access_Change_Log::record('access_suspended', 'user', 5, $long);
    $stored = IDLM_Access_Change_Log::entries()[0]['target_name'];
    assert_true(1 === preg_match('//u', $stored), 'A truncated name must stay valid UTF-8.');
    assert_same(100, mb_strlen($stored, 'UTF-8'));

    IDLM_Access_Change_Log::record('access_suspended', 'user', 6, '<script>alert(1)</script>Name');
    assert_false(false !== strpos(IDLM_Access_Change_Log::entries()[0]['target_name'], '<script'));
    $html = IDLM_View::render('admin/users-activity.php', array('entries' => array(array(
        'time' => 1, 'actor_name' => '<img src=x onerror=a>', 'event' => '<b>x</b>', 'target_type' => 'user', 'target_name' => '"><svg onload=a>',
        'before' => array('roles' => array('<i>r</i>')), 'after' => array('roles' => array('agent')),
    ))));
    foreach (array('<img', '<b>x', '<svg', '<i>r') as $forbidden) { assert_false(false !== strpos($html, $forbidden), $forbidden); }
});

idlm_test('an update grants nothing to a role first seen on an existing install', static function(): void {
    $source = (string) file_get_contents(dirname(__DIR__) . '/interior-design-lead-manager.php');
    preg_match('/function idlm_register_crm_roles_and_capabilities\(.*?\n}\n/s', $source, $function);
    if (!function_exists('idlm_test_register_roles')) {
        // The test role registry has no for_site(); the provisioning decisions under test are unchanged.
        eval(str_replace(array('function idlm_register_crm_roles_and_capabilities', '$registry->for_site();'), array('function idlm_test_register_roles', ''), $function[0])); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- Test-only load of the plugin function.
    }
    if (!function_exists('add_role')) {
        function add_role($role, $name, $caps = array()) { return null; }
    }
    idlm_access_fixture();
    $GLOBALS['idlm_test_role_cap_overrides'] = array('marketing' => array('read' => true), 'idlm_pre_sales' => array('read' => true, 'idlm_access_leads' => true, 'idlm_qualify_leads' => true), 'sales_manager' => array('idlm_access_leads' => true), 'sales_executive' => array('idlm_access_leads' => true), 'marketing_manager' => array('idlm_access_leads' => true), 'campaign_team' => array('idlm_access_leads' => true));
    update_option('idlm_crm_access_policy_version', '1');

    // Existing install: the update records the role and leaves it alone.
    update_option('idlm_roles_version', '1.0.0');
    update_option('idlm_initialized_crm_roles', array('sales_manager' => true));
    assert_true(idlm_test_register_roles());
    assert_same(array('read' => true), get_role('marketing')->capabilities);
    assert_true(!empty(get_option('idlm_initialized_crm_roles')['marketing']));
    assert_false(get_role('sales_manager')->has_cap('idlm_assign_leads'));

    // Fresh install: initial setup still provisions it, once.
    delete_option('idlm_roles_version');
    delete_option('idlm_initialized_crm_roles');
    assert_true(idlm_test_register_roles());
    assert_true(get_role('marketing')->has_cap('idlm_access_leads'));
});

idlm_test('new required files are in the release inventory', static function(): void {
    require_once dirname(__DIR__) . '/tests/release/class-release-artifact.php';
    $required = IDLM_Release_Artifact::required_files();
    foreach (array('includes/class-access-change-log.php', 'templates/admin/users-roles.php', 'templates/admin/users-activity.php') as $file) {
        assert_true(in_array($file, $required, true), $file);
        assert_true(is_file(dirname(__DIR__) . '/' . $file), $file);
        assert_false(IDLM_Release_Artifact::is_excluded($file, IDLM_Release_Artifact::distignore_patterns(dirname(__DIR__))), $file);
    }
});
