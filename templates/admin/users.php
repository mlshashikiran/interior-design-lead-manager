<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_users = isset($context['users']) && is_array($context['users']) ? $context['users'] : array();
$idlm_roles = isset($context['roles']) && is_array($context['roles']) ? $context['roles'] : array();
$idlm_role_scopes = isset($context['role_scopes']) && is_array($context['role_scopes']) ? $context['role_scopes'] : array();
$idlm_permission_roles = isset($context['permission_roles']) && is_array($context['permission_roles']) ? $context['permission_roles'] : array();
$idlm_notice = isset($context['notice']) ? (string) $context['notice'] : '';
$idlm_error = isset($context['error']) ? (string) $context['error'] : '';
$idlm_notice_messages = array(
    'created' => 'The user was created and a password-reset link was sent.',
    'updated' => 'The user account and CRM access were updated.',
    'role-scopes-updated' => 'Role lead scopes were updated.',
    'role-permissions-updated' => 'Role permissions were updated.',
);
$idlm_error_messages = array(
    'invalid-user'  => 'Check the account details, email address, and selected CRM role.',
    'create-failed' => 'WordPress could not create this account. Review the details and try again.',
    'update-failed' => 'WordPress could not update this account. Review the details and try again.',
    'self-lockout'  => 'You cannot remove your own administrator role or suspend your own CRM access.',
    'last-admin'    => 'Keep at least one active administrator before changing this account.',
    'role-not-confirmed' => 'Nothing was saved. This page was out of date, so reload it and choose a role or keep their current role.',
);
$idlm_notice_messages['role-access-updated'] = 'Role access was updated. It applies from their next page load.';
$idlm_error_messages['invalid-role'] = 'This role cannot be changed here. Nothing was saved.';
$idlm_error_messages['invalid-user'] = 'Check the email address and role. That email or username may already be in use.';
$idlm_clear_url = remove_query_arg(array('s', 'role', 'access', 'paged'));
$idlm_has_filters = !empty($context['search']) || !empty($context['selected_role']) || !empty($context['selected_access']);
$idlm_return_url = remove_query_arg(array('idlm_users_notice', 'idlm_users_error', 'paged'));
$idlm_total = isset($context['total']) ? (int) $context['total'] : count($idlm_users);
$idlm_per_page = isset($context['per_page']) ? max(1, (int) $context['per_page']) : 25;
$idlm_paged = isset($context['paged']) ? max(1, (int) $context['paged']) : 1;
$idlm_shown = count($idlm_users);
$idlm_suspended_shown = 0;
foreach ($idlm_users as $idlm_counted) { if (IDLM_Admin_Users_Controller::is_crm_access_disabled($idlm_counted->ID)) { ++$idlm_suspended_shown; } }
$idlm_current_user_id = get_current_user_id();
$idlm_page_url = remove_query_arg(array('idlm_users_notice', 'idlm_users_error'));
$idlm_invite_open = in_array($idlm_error, array('invalid-user', 'create-failed'), true);
// Presentation only: which view is showing. Saved roles and options are never read from this value.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view selection.
$idlm_requested_tab = isset($_GET['users_tab']) ? sanitize_key(wp_unslash($_GET['users_tab'])) : '';
$idlm_users_tab = in_array($idlm_requested_tab, array('roles', 'activity'), true) ? $idlm_requested_tab : 'team';
if (in_array($idlm_notice, array('role-scopes-updated', 'role-permissions-updated', 'role-access-updated'), true)) { $idlm_users_tab = 'roles'; }
$idlm_team_url = remove_query_arg(array('users_tab', 'role_edit', 'idlm_users_notice', 'idlm_users_error'));
$idlm_roles_url = add_query_arg('users_tab', 'roles', remove_query_arg(array('s', 'role', 'access', 'paged', 'role_edit', 'idlm_users_notice', 'idlm_users_error')));
$idlm_activity_url = add_query_arg('users_tab', 'activity', remove_query_arg(array('s', 'role', 'access', 'paged', 'role_edit', 'idlm_users_notice', 'idlm_users_error')));
$idlm_view_base_url = remove_query_arg(array('users_tab', 'role_edit', 's', 'role', 'access', 'paged', 'idlm_users_notice', 'idlm_users_error'));
// Website roles stay reachable: the options wait in a template until "Show website roles" is pressed.
$idlm_website_roles_control = static function($idlm_select_id, $idlm_website_roles) {
    if (!$idlm_website_roles) { return; }
    ?><div class="idlm-website-roles" data-idlm-website-roles>
        <template data-idlm-website-role-options><optgroup label="Website roles"><?php foreach ($idlm_website_roles as $idlm_website_slug => $idlm_website_label): ?><option value="<?php echo esc_attr($idlm_website_slug); ?>"><?php echo esc_html($idlm_website_label); ?></option><?php endforeach; ?></optgroup></template>
        <button type="button" class="idlm-action-link" data-idlm-show-website-roles aria-controls="<?php echo esc_attr($idlm_select_id); ?>">Show website roles</button>
        <small>They also grant website publishing rights.</small>
    </div><?php
};
?>
<div class="wrap idlm-wrap idlm-users-page">
    <header class="idlm-header idlm-users-header">
        <div class="idlm-header-meta">
            <h1>Users &amp; access</h1>
        </div>
        <div class="idlm-header-actions">
<?php if ('team' === $idlm_users_tab): ?>
            <a class="idlm-btn idlm-btn-primary" href="#idlm-users-invite" data-idlm-open-fold="idlm-users-invite"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span><span>Invite team member</span></a>
<?php endif; ?>
        </div>
    </header>

    <?php if ($idlm_notice): ?>
        <div class="idlm-alert idlm-account-success idlm-alert-success" role="status" tabindex="-1" data-idlm-users-notice><?php echo esc_html($idlm_notice_messages[$idlm_notice] ?? 'User access has been updated.'); ?></div>
    <?php endif; ?>
    <?php if ($idlm_error): ?>
        <div class="idlm-alert idlm-alert-warning" role="alert" tabindex="-1" data-idlm-users-notice><?php echo esc_html($idlm_error_messages[$idlm_error] ?? 'Unable to save this user. Check the account details and administrator safeguards.'); ?></div>
    <?php endif; ?>

    <nav class="idlm-users-tabs" aria-label="Users and access views">
        <a href="<?php echo esc_url($idlm_team_url); ?>"<?php echo 'team' === $idlm_users_tab ? ' class="is-active" aria-current="page"' : ''; ?>>Team</a>
        <a href="<?php echo esc_url($idlm_roles_url); ?>"<?php echo 'roles' === $idlm_users_tab ? ' class="is-active" aria-current="page"' : ''; ?>>Roles &amp; permissions</a>
        <a href="<?php echo esc_url($idlm_activity_url); ?>"<?php echo 'activity' === $idlm_users_tab ? ' class="is-active" aria-current="page"' : ''; ?>>Activity</a>
    </nav>


    <?php ob_start(); ?>
    <section class="idlm-users-create" id="idlm-users-invite" aria-labelledby="idlm-users-create-title">
        <details class="idlm-users-fold" <?php echo $idlm_invite_open ? 'open' : ''; ?>>
        <summary class="idlm-users-fold-summary"><span class="idlm-users-fold-title"><strong id="idlm-users-create-title">Invite a team member</strong><span>They get an account and a secure password-reset link by email.</span></span><span class="idlm-permission-edit">Invite <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></span></summary>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idlm-users-create-form" data-idlm-pending-form<?php echo ' data-idlm-invite-form' . ($idlm_invite_open ? ' data-idlm-invite-restore' : ''); ?>>
            <input type="hidden" name="action" value="idlm_create_user">
            <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>">
            <?php wp_nonce_field('idlm_create_user'); ?>
            <div class="idlm-form-group"><label for="idlm-user-first-name">First name</label><input class="idlm-input" required id="idlm-user-first-name" name="first_name" autocomplete="given-name"></div>
            <div class="idlm-form-group"><label for="idlm-user-last-name">Last name</label><input class="idlm-input" required id="idlm-user-last-name" name="last_name" autocomplete="family-name"></div>
            <div class="idlm-form-group"><label for="idlm-user-email">Email</label><input class="idlm-input" required type="email" id="idlm-user-email" name="email" autocomplete="email" inputmode="email"></div>
            <div class="idlm-form-group"><label for="idlm-user-phone">Phone <span>Optional</span></label><input class="idlm-input" type="tel" id="idlm-user-phone" name="phone" autocomplete="tel" inputmode="tel"></div>
<?php $idlm_invite_roles = IDLM_Admin_Users_Controller::pick_list_roles($idlm_roles); ?>
            <div class="idlm-form-group"><label for="idlm-user-role">CRM role</label><select class="idlm-input" id="idlm-user-role" name="role" required><option value="">Choose a role</option><?php foreach ($idlm_invite_roles['main'] as $idlm_slug => $idlm_label): ?><option value="<?php echo esc_attr($idlm_slug); ?>"><?php echo esc_html($idlm_label); ?></option><?php endforeach; ?></select><?php $idlm_website_roles_control('idlm-user-role', $idlm_invite_roles['website']); ?></div>
            <details class="idlm-users-login-fold" data-idlm-login-fold>
                <summary>Set a username</summary>
                <div class="idlm-form-group"><label for="idlm-user-login">Username</label><input class="idlm-input" id="idlm-user-login" name="user_login" autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="idlm-user-login-hint"><small id="idlm-user-login-hint">Leave empty to create one from the email address.</small></div>
            </details>
            <button class="idlm-btn idlm-btn-primary" type="submit" data-idlm-submit-label="Creating user…">Create user</button>
        </form>
</details>
    </section>
    <?php $idlm_section_invite = ob_get_clean(); ?>

    <?php  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Section is escaped where it is built. ?>
<?php if ('roles' === $idlm_users_tab) { IDLM_View::display('admin/users-roles.php', array('roles' => $idlm_roles, 'return_url' => $idlm_return_url, 'roles_url' => $idlm_roles_url, 'team_url' => $idlm_team_url, 'view_base_url' => $idlm_view_base_url)); } ?>
<?php if ('activity' === $idlm_users_tab) { IDLM_View::display('admin/users-activity.php', array('entries' => isset($context['access_log']) && is_array($context['access_log']) ? $context['access_log'] : array())); } ?>
<?php if ('team' === $idlm_users_tab) { echo $idlm_section_invite; } // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Section is escaped where it is built. ?>
<?php if ('team' === $idlm_users_tab): ?>
    <p class="idlm-users-summary" role="status">
        <?php if ('' !== (string) $context['selected_access']): ?>
            <?php echo esc_html(sprintf(1 === $idlm_shown ? '%d person matches' : '%d people match', $idlm_shown)); ?>
        <?php else: ?>
            <?php echo esc_html(sprintf('Showing %1$d of %2$d people', $idlm_shown, max($idlm_total, $idlm_shown))); ?>
        <?php endif; ?>
        <?php if ($idlm_suspended_shown): ?><span class="idlm-users-summary-flag"><?php echo esc_html(sprintf('%d suspended in this list', $idlm_suspended_shown)); ?></span><?php endif; ?>
    </p>
<?php endif; ?>

<?php if ('team' === $idlm_users_tab): ?>
    <section class="idlm-users-directory" aria-labelledby="idlm-users-directory-title">
        <div class="idlm-users-directory-top">
            <div>
                <h2 id="idlm-users-directory-title">Team directory</h2>
            </div>
            <form method="get" class="idlm-users-toolbar" id="idlm-users-filters" role="search" aria-label="Find a team member">
                <?php if (is_admin()): ?><input type="hidden" name="page" value="idlm_users_access"><?php else: ?><input type="hidden" name="idlm_view" value="users"><?php endif; ?>
                <div class="idlm-form-group idlm-users-search-field"><label class="screen-reader-text" for="idlm-users-search">Search users</label><input class="idlm-input" id="idlm-users-search" name="s" type="search" data-idlm-filter-field value="<?php echo esc_attr($context['search']); ?>" placeholder="Search name, username or email"></div>
                <div class="idlm-form-group"><label class="screen-reader-text" for="idlm-users-role-filter">CRM role</label><select class="idlm-input" id="idlm-users-role-filter" name="role" data-idlm-filter-field><option value="">All CRM roles</option><?php foreach ($idlm_roles as $idlm_slug => $idlm_data): ?><option value="<?php echo esc_attr($idlm_slug); ?>" <?php selected($context['selected_role'], $idlm_slug); ?>><?php echo esc_html(IDLM_Admin_Users_Controller::role_label($idlm_slug)); ?></option><?php endforeach; ?></select></div>
                <div class="idlm-form-group"><label class="screen-reader-text" for="idlm-users-access-filter">CRM access</label><select class="idlm-input" id="idlm-users-access-filter" name="access" data-idlm-filter-field><option value="">All access</option><option value="enabled" <?php selected($context['selected_access'], 'enabled'); ?>>Access enabled</option><option value="disabled" <?php selected($context['selected_access'], 'disabled'); ?>>Access suspended</option></select></div>
                <div class="idlm-users-filter-actions"><button class="idlm-btn idlm-btn-secondary" type="submit">Search</button><?php if ($idlm_has_filters): ?><a class="idlm-btn idlm-btn-quiet" href="<?php echo esc_url($idlm_clear_url); ?>">Clear filters</a><?php endif; ?></div>
            </form>
        </div>

        <div class="idlm-user-list">
            <?php foreach ($idlm_users as $idlm_user):
                $idlm_disabled = IDLM_Admin_Users_Controller::is_crm_access_disabled($idlm_user->ID);
                $idlm_role_names = array_map(array('IDLM_Admin_Users_Controller', 'role_label'), (array) $idlm_user->roles);
                $idlm_preselect = IDLM_Admin_Users_Controller::preselected_role($idlm_user->roles, $idlm_roles);
                $idlm_open_leads = (int) ($context['open_leads'][(int) $idlm_user->ID] ?? 0);
                $idlm_open_label = 1 === $idlm_open_leads ? '1 open lead' : sprintf('%d open leads', $idlm_open_leads);
                $idlm_safeguard_id = 'idlm-user-safeguard-' . $idlm_user->ID;
                $idlm_is_self = (int) $idlm_user->ID === (int) $idlm_current_user_id;
                $idlm_row_class = 'idlm-user-row' . ($idlm_disabled ? ' is-suspended' : '') . ($idlm_is_self ? ' is-self' : '');
            ?>
                <details class="<?php echo esc_attr($idlm_row_class); ?>">
                    <summary>
                        <span class="idlm-user-avatar" aria-hidden="true"><?php echo esc_html(strtoupper(substr($idlm_user->display_name ?: $idlm_user->user_login, 0, 1))); ?></span>
                        <span class="idlm-user-identity"><strong><?php echo esc_html($idlm_user->display_name); ?><?php if ($idlm_is_self): ?> <span class="idlm-user-you">You</span><?php endif; ?></strong><small><?php echo esc_html($idlm_user->user_email); ?></small></span>
                        <span class="idlm-user-role"><?php echo esc_html(implode(', ', $idlm_role_names)); ?><?php if ($idlm_open_leads > 0): ?> <small class="idlm-user-open-leads"><?php echo esc_html($idlm_open_label); ?></small><?php endif; ?></span>
                        <span class="idlm-user-status <?php echo $idlm_disabled ? 'is-suspended' : ''; ?><?php echo ($idlm_disabled ? ' idlm-badge is-danger' : ' idlm-badge is-success'); ?>"><?php echo $idlm_disabled ? 'Suspended' : 'Active'; ?></span>
                        <span class="idlm-user-edit">Edit <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></span>
                    </summary>
<?php $idlm_summary = IDLM_Admin_Users_Controller::access_summary($idlm_user); ?>
                    <dl class="idlm-user-access-summary">
                        <div><dt>Username</dt><dd><?php echo esc_html($idlm_user->user_login); ?></dd></div>
                        <div><dt>Sees</dt><dd><?php echo esc_html($idlm_summary['sees']); ?></dd></div>
                        <div><dt>Can</dt><dd>
                            <?php if ($idlm_summary['suspended'] && $idlm_summary['is_admin']): ?>Leads and quotations are off while suspended. Settings, users and backups stay available, because this account can manage the site.
                            <?php elseif ($idlm_summary['suspended']): ?>Nothing in the CRM while suspended
                            <?php elseif (!$idlm_summary['groups'] && !$idlm_summary['is_admin']): ?>Nothing in the CRM
                            <?php else: ?>
                                <?php if ($idlm_summary['is_admin']): ?><span class="idlm-user-access-group"><strong>Administration:</strong> Manage settings and users</span><?php endif; ?>
                                <?php foreach ($idlm_summary['groups'] as $idlm_group => $idlm_group_labels): ?><span class="idlm-user-access-group"><strong><?php echo esc_html($idlm_group); ?>:</strong> <?php echo esc_html(implode(', ', $idlm_group_labels)); ?></span><?php endforeach; ?>
                            <?php endif; ?>
                        </dd></div>
                        <?php if ($idlm_summary['extra']): ?><div><dt>Extra on this account</dt><dd><?php echo esc_html(implode(', ', $idlm_summary['extra'])); ?></dd></div><?php endif; ?>
                    </dl>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idlm-user-edit-form" data-idlm-pending-form data-idlm-user-name="<?php echo esc_attr($idlm_user->display_name); ?>"<?php echo $idlm_summary['is_admin'] ? ' data-idlm-user-manages-site="1"' : ''; ?><?php echo $idlm_open_leads > 0 ? ' data-idlm-open-leads="' . esc_attr($idlm_open_leads) . '"' : ''; ?>>
                        <input type="hidden" name="action" value="idlm_update_user"><input type="hidden" name="user_id" value="<?php echo esc_attr($idlm_user->ID); ?>"><input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>"><?php wp_nonce_field('idlm_update_user'); ?><?php if ('' === $idlm_preselect): ?><input type="hidden" name="idlm_role_choice_offered" value="1"><?php endif; ?>
                        <div class="idlm-form-group"><label for="idlm-edit-first-<?php echo esc_attr($idlm_user->ID); ?>">First name</label><input class="idlm-input" id="idlm-edit-first-<?php echo esc_attr($idlm_user->ID); ?>" name="first_name" value="<?php echo esc_attr($idlm_user->first_name); ?>" autocomplete="given-name"></div>
                        <div class="idlm-form-group"><label for="idlm-edit-last-<?php echo esc_attr($idlm_user->ID); ?>">Last name</label><input class="idlm-input" id="idlm-edit-last-<?php echo esc_attr($idlm_user->ID); ?>" name="last_name" value="<?php echo esc_attr($idlm_user->last_name); ?>" autocomplete="family-name"></div>
                        <div class="idlm-form-group"><label for="idlm-edit-display-<?php echo esc_attr($idlm_user->ID); ?>">Display name</label><input class="idlm-input" required id="idlm-edit-display-<?php echo esc_attr($idlm_user->ID); ?>" name="display_name" value="<?php echo esc_attr($idlm_user->display_name); ?>" autocomplete="name"></div>
                        <div class="idlm-form-group"><label for="idlm-edit-email-<?php echo esc_attr($idlm_user->ID); ?>">Email</label><input class="idlm-input" required type="email" id="idlm-edit-email-<?php echo esc_attr($idlm_user->ID); ?>" name="email" value="<?php echo esc_attr($idlm_user->user_email); ?>" autocomplete="email" inputmode="email"></div>
                        <div class="idlm-form-group"><label for="idlm-edit-phone-<?php echo esc_attr($idlm_user->ID); ?>">Phone</label><input class="idlm-input" type="tel" id="idlm-edit-phone-<?php echo esc_attr($idlm_user->ID); ?>" name="phone" value="<?php echo esc_attr(get_user_meta($idlm_user->ID, 'idlm_phone', true)); ?>" autocomplete="tel" inputmode="tel"></div>
<?php $idlm_edit_roles = IDLM_Admin_Users_Controller::pick_list_roles($idlm_roles, $idlm_user->roles); ?>
                        <div class="idlm-form-group"><label for="idlm-edit-role-<?php echo esc_attr($idlm_user->ID); ?>">CRM role</label><select class="idlm-input" id="idlm-edit-role-<?php echo esc_attr($idlm_user->ID); ?>" name="role"<?php echo '' === $idlm_preselect ? '' : ' required'; ?> aria-describedby="<?php echo esc_attr($idlm_safeguard_id); ?>"><?php if ('' === $idlm_preselect): ?><option value="" selected>Keep current role</option><?php endif; ?><?php foreach ($idlm_edit_roles['main'] as $idlm_slug => $idlm_label): ?><option value="<?php echo esc_attr($idlm_slug); ?>" <?php selected($idlm_preselect === $idlm_slug); ?>><?php echo esc_html($idlm_label); ?></option><?php endforeach; ?></select><?php $idlm_website_roles_control('idlm-edit-role-' . $idlm_user->ID, $idlm_edit_roles['website']); ?></div>
                        <p class="idlm-user-safeguard" id="<?php echo esc_attr($idlm_safeguard_id); ?>"><?php echo esc_html($idlm_is_self ? 'This is your account. You cannot suspend it or remove your administrator role.' : 'You cannot suspend your own access or remove the final administrator.'); ?></p>
                        <div class="idlm-user-edit-actions">
                            <label class="idlm-checkbox-row"><input class="idlm-checkbox-control" type="checkbox" name="crm_access_disabled" value="1" <?php checked($idlm_disabled); ?>><span>Suspend CRM access (keeps the account and lead history)</span></label>
<?php if ($idlm_open_leads > 0): ?>
                            <p class="idlm-user-suspend-note" data-idlm-suspend-note<?php echo $idlm_disabled ? '' : ' hidden'; ?>><?php echo esc_html(sprintf('%1$s has %2$s. They stay assigned until you reassign them.', $idlm_user->display_name, $idlm_open_label)); ?> <a href="<?php echo esc_url(add_query_arg(array('idlm_view' => 'leads', 'rep' => (int) $idlm_user->ID), $idlm_view_base_url)); ?>">View on Leads</a></p>
<?php endif; ?>
                            <label class="idlm-checkbox-row"><input class="idlm-checkbox-control" type="checkbox" name="send_reset_link" value="1"><span>Send password-reset link</span></label>
                            <button class="idlm-btn idlm-btn-primary" type="submit" data-idlm-submit-label="Saving changes…">Save changes</button>
                        </div>
                    </form>
                </details>
            <?php endforeach; ?>
            <?php if (!$idlm_users): ?><div class="idlm-users-empty idlm-state is-empty"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><strong>No users match these filters.</strong><p>Try clearing a filter or invite a team member.</p><?php if ($idlm_has_filters): ?><div class="idlm-state__actions"><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url($idlm_clear_url); ?>">Clear filters</a></div><?php endif; ?></div><?php endif; ?>
        </div>
<?php if ('' === (string) $context['selected_access'] && $idlm_total > $idlm_per_page): $idlm_last_page = (int) ceil($idlm_total / $idlm_per_page); ?>
        <nav class="idlm-users-pager" aria-label="Team directory pages">
            <?php if ($idlm_paged > 1): ?><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url(add_query_arg('paged', $idlm_paged - 1, $idlm_page_url)); ?>">Previous</a><?php endif; ?>
            <span><?php echo esc_html(sprintf('Page %1$d of %2$d', $idlm_paged, $idlm_last_page)); ?></span>
            <?php if ($idlm_paged < $idlm_last_page): ?><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url(add_query_arg('paged', $idlm_paged + 1, $idlm_page_url)); ?>">Next</a><?php endif; ?>
        </nav>
<?php endif; ?>
    </section>
<?php endif; ?>
</div>
