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
);
$idlm_clear_url = remove_query_arg(array('s', 'role', 'access', 'paged'));
$idlm_has_filters = !empty($context['search']) || !empty($context['selected_role']) || !empty($context['selected_access']);
$idlm_return_url = remove_query_arg(array('idlm_users_notice', 'idlm_users_error', 'paged'));
?>
<div class="wrap idlm-wrap idlm-users-page">
    <header class="idlm-header idlm-users-header">
        <div class="idlm-header-meta">
            <h1>Users &amp; Access</h1>
        </div>
        <div class="idlm-header-actions">
            <button type="button" class="idlm-btn idlm-btn-secondary idlm-header-filter-button" data-idlm-filter-open aria-controls="idlm-users-filters" aria-expanded="false">
                <span class="dashicons dashicons-filter" aria-hidden="true"></span>
                <span>Filters</span>
                <span class="idlm-filter-count" data-idlm-filter-count aria-label="0 active filters" hidden>0</span>
            </button>
        </div>
    </header>

    <?php if ($idlm_notice): ?>
        <div class="idlm-alert idlm-account-success" role="status" tabindex="-1" data-idlm-users-notice><?php echo esc_html($idlm_notice_messages[$idlm_notice] ?? 'User access has been updated.'); ?></div>
    <?php endif; ?>
    <?php if ($idlm_error): ?>
        <div class="idlm-alert idlm-alert-warning" role="alert" tabindex="-1" data-idlm-users-notice><?php echo esc_html($idlm_error_messages[$idlm_error] ?? 'Unable to save this user. Check the account details and administrator safeguards.'); ?></div>
    <?php endif; ?>

    <section class="idlm-users-create idlm-role-scopes" aria-labelledby="idlm-role-scopes-title">
        <div>
            <h2 id="idlm-role-scopes-title">Role lead visibility</h2>
            <p>Choose which leads members of each CRM role can see. Existing capability permissions still control what they can do.</p>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idlm-role-scopes-form" data-idlm-pending-form>
            <input type="hidden" name="action" value="idlm_update_role_scopes">
            <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>">
            <?php wp_nonce_field('idlm_update_role_scopes'); ?>
            <div class="idlm-role-scopes-grid">
                <?php foreach ($idlm_roles as $idlm_slug => $idlm_data):
                    $idlm_is_administrator = 'administrator' === $idlm_slug;
                    $idlm_selected_scope = $idlm_is_administrator ? 'all' : ($idlm_role_scopes[$idlm_slug] ?? 'default');
                ?>
                    <div class="idlm-form-group">
                        <label for="idlm-role-scope-<?php echo esc_attr($idlm_slug); ?>"><?php echo esc_html(translate_user_role($idlm_data['name'])); ?></label>
                        <select class="idlm-input" id="idlm-role-scope-<?php echo esc_attr($idlm_slug); ?>" name="role_scopes[<?php echo esc_attr($idlm_slug); ?>]" <?php disabled($idlm_is_administrator); ?>>
                            <?php if (!$idlm_is_administrator): ?><option value="default" <?php selected($idlm_selected_scope, 'default'); ?>>Use CRM default</option><?php endif; ?>
                            <option value="all" <?php selected($idlm_selected_scope, 'all'); ?>>All leads</option>
                            <?php if (!$idlm_is_administrator): ?><option value="assigned" <?php selected($idlm_selected_scope, 'assigned'); ?>>Assigned leads only</option><?php endif; ?>
                        </select>
                    </div>
                <?php endforeach; ?>
            </div>
            <button class="idlm-btn idlm-btn-primary" type="submit" data-idlm-submit-label="Saving role scopes…">Save role scopes</button>
        </form>
    </section>

    <section class="idlm-users-create idlm-permissions" aria-labelledby="idlm-role-permissions-title">
        <div class="idlm-permissions-intro">
            <h2 id="idlm-role-permissions-title">Role permissions</h2>
            <p>Choose what each CRM role can do. Changes apply to everyone with that role.</p>
            <p class="idlm-permissions-note">Access can also depend on individual grants, other roles, lead ownership and pre-sales restrictions. Administrator and shared WordPress editorial roles are managed separately.</p>
        </div>
        <div class="idlm-permissions-list">
        <?php if (!$idlm_permission_roles): ?>
            <p class="idlm-permissions-empty">No dedicated CRM roles are available to edit.</p>
        <?php endif; ?>
        <?php foreach ($idlm_permission_roles as $idlm_slug => $idlm_data):
            $idlm_role_object = get_role($idlm_slug);
            $idlm_permissions = IDLM_Admin_Users_Controller::selectable_permissions($idlm_slug);
            $idlm_enabled = 0;
            foreach ($idlm_permissions as $idlm_capability => $idlm_label) { if ($idlm_role_object && $idlm_role_object->has_cap($idlm_capability)) { ++$idlm_enabled; } }
            $idlm_groups = array(
                'Leads' => array('idlm_access_leads', 'idlm_qualify_leads', 'idlm_assign_leads'),
                'Quotations' => array('idlm_view_quotes', 'idlm_create_quotes', 'idlm_edit_quote_drafts', 'idlm_submit_quotes', 'idlm_mark_quotes_shared', 'idlm_accept_reject_quotes', 'idlm_create_quote_revisions', 'idlm_download_quote_pdf'),
                'Quotation administration' => array('idlm_approve_quotes', 'idlm_cancel_quotes', 'idlm_manage_quote_settings', 'idlm_manage_quote_items'),
            );
            $idlm_grouped_caps = array_merge(...array_values($idlm_groups));
            $idlm_groups['Other permissions'] = array_values(array_diff(array_keys($idlm_permissions), $idlm_grouped_caps));
        ?>
            <details class="idlm-permission-role">
            <summary class="idlm-permission-summary">
                <span class="idlm-permission-heading"><strong><?php echo esc_html(translate_user_role($idlm_data['name'])); ?></strong><span><?php echo esc_html(sprintf('%1$d of %2$d permissions enabled (saved)', $idlm_enabled, count($idlm_permissions))); ?></span></span>
                <span class="idlm-permission-edit">Edit permissions <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></span>
            </summary>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idlm-permission-form" data-idlm-pending-form>
                <input type="hidden" name="action" value="idlm_update_role_permissions">
                <input type="hidden" name="permission_role" value="<?php echo esc_attr($idlm_slug); ?>">
                <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>">
                <?php wp_nonce_field('idlm_update_role_permissions'); ?>
                <?php foreach ($idlm_groups as $idlm_group => $idlm_group_caps):
                    $idlm_group_permissions = array_intersect_key($idlm_permissions, array_flip($idlm_group_caps));
                    if (!$idlm_group_permissions) { continue; }
                ?>
                <fieldset class="idlm-permission-group">
                    <legend><?php echo esc_html($idlm_group); ?></legend>
                    <div class="idlm-permission-options">
                    <?php foreach ($idlm_group_permissions as $idlm_capability => $idlm_label): ?>
                        <label class="idlm-permission-option"><input type="checkbox" name="permissions[<?php echo esc_attr($idlm_capability); ?>]" value="1" <?php checked($idlm_role_object && $idlm_role_object->has_cap($idlm_capability)); ?>><span><?php echo esc_html($idlm_label); ?></span></label>
                    <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php endforeach; ?>
                <div class="idlm-permission-actions"><span>Changes apply to this role only.</span><button class="idlm-btn idlm-btn-primary" type="submit" data-idlm-submit-label="Saving permissions…">Save permissions</button></div>
            </form>
            </details>
        <?php endforeach; ?>
        </div>
    </section>

    <section class="idlm-users-create" aria-labelledby="idlm-users-create-title">
        <div>
            <h2 id="idlm-users-create-title">Invite a team member</h2>
            <p>An account is created with a secure password-reset link.</p>
        </div>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idlm-users-create-form" data-idlm-pending-form>
            <input type="hidden" name="action" value="idlm_create_user">
            <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>">
            <?php wp_nonce_field('idlm_create_user'); ?>
            <div class="idlm-form-group"><label for="idlm-user-first-name">First name</label><input class="idlm-input" required id="idlm-user-first-name" name="first_name" autocomplete="given-name"></div>
            <div class="idlm-form-group"><label for="idlm-user-last-name">Last name</label><input class="idlm-input" required id="idlm-user-last-name" name="last_name" autocomplete="family-name"></div>
            <div class="idlm-form-group"><label for="idlm-user-login">Username</label><input class="idlm-input" required id="idlm-user-login" name="user_login" autocomplete="username" autocapitalize="none" spellcheck="false"></div>
            <div class="idlm-form-group"><label for="idlm-user-email">Email</label><input class="idlm-input" required type="email" id="idlm-user-email" name="email" autocomplete="email" inputmode="email"></div>
            <div class="idlm-form-group"><label for="idlm-user-phone">Phone <span>Optional</span></label><input class="idlm-input" type="tel" id="idlm-user-phone" name="phone" autocomplete="tel" inputmode="tel"></div>
            <div class="idlm-form-group"><label for="idlm-user-role">CRM role</label><select class="idlm-input" id="idlm-user-role" name="role"><?php foreach ($idlm_roles as $idlm_slug => $idlm_data): ?><option value="<?php echo esc_attr($idlm_slug); ?>"><?php echo esc_html(translate_user_role($idlm_data['name'])); ?></option><?php endforeach; ?></select></div>
            <button class="idlm-btn idlm-btn-primary" type="submit" data-idlm-submit-label="Creating user…">Create user</button>
        </form>
    </section>

    <section class="idlm-users-directory" aria-labelledby="idlm-users-directory-title">
        <div class="idlm-users-directory-top">
            <div>
                <h2 id="idlm-users-directory-title">Team directory</h2>
                <p>Roles set CRM permissions. Suspending access keeps the account and lead history intact.</p>
            </div>
            <div class="idlm-filters-backdrop" data-idlm-filter-backdrop hidden></div>
            <form method="get" class="idlm-filters-panel idlm-users-filter" id="idlm-users-filters" data-idlm-filter-drawer aria-label="Filter team directory" role="dialog" aria-modal="true" aria-labelledby="idlm-users-filters-title" aria-hidden="true" tabindex="-1">
                <div class="idlm-filters-panel-header">
                    <div>
                        <h2 id="idlm-users-filters-title">Filter team directory</h2>
                        <p>Find team members by role or CRM access.</p>
                    </div>
                    <button type="button" class="idlm-filters-close" data-idlm-filter-close aria-label="Close filters"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                </div>
                <div class="idlm-filters-panel-body">
                <?php if (is_admin()): ?><input type="hidden" name="page" value="idlm_users_access"><?php else: ?><input type="hidden" name="idlm_view" value="users"><?php endif; ?>
                <div class="idlm-form-group idlm-users-search-field"><label for="idlm-users-search">Search users</label><input class="idlm-input" id="idlm-users-search" name="s" type="search" data-idlm-filter-field value="<?php echo esc_attr($context['search']); ?>" placeholder="Name, username, or email"></div>
                <div class="idlm-form-group"><label for="idlm-users-role-filter">CRM role</label><select class="idlm-input" id="idlm-users-role-filter" name="role" data-idlm-filter-field><option value="">All CRM roles</option><?php foreach ($idlm_roles as $idlm_slug => $idlm_data): ?><option value="<?php echo esc_attr($idlm_slug); ?>" <?php selected($context['selected_role'], $idlm_slug); ?>><?php echo esc_html(translate_user_role($idlm_data['name'])); ?></option><?php endforeach; ?></select></div>
                <div class="idlm-form-group"><label for="idlm-users-access-filter">CRM access</label><select class="idlm-input" id="idlm-users-access-filter" name="access" data-idlm-filter-field><option value="">All access</option><option value="enabled" <?php selected($context['selected_access'], 'enabled'); ?>>Access enabled</option><option value="disabled" <?php selected($context['selected_access'], 'disabled'); ?>>Access suspended</option></select></div>
                <div class="idlm-users-filter-actions"><?php if ($idlm_has_filters): ?><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url($idlm_clear_url); ?>">Clear</a><?php endif; ?><button class="idlm-btn idlm-btn-primary" type="submit">Apply filters</button></div>
                </div>
            </form>
        </div>

        <div class="idlm-user-list">
            <?php foreach ($idlm_users as $idlm_user):
                $idlm_disabled = IDLM_Admin_Users_Controller::is_crm_access_disabled($idlm_user->ID);
                $idlm_role_names = array_map(static function($idlm_role) {
                    $idlm_all_roles = wp_roles()->roles;
                    return translate_user_role($idlm_all_roles[$idlm_role]['name'] ?? $idlm_role);
                }, (array) $idlm_user->roles);
                $idlm_safeguard_id = 'idlm-user-safeguard-' . $idlm_user->ID;
            ?>
                <details class="idlm-user-row">
                    <summary>
                        <span class="idlm-user-avatar" aria-hidden="true"><?php echo esc_html(strtoupper(substr($idlm_user->display_name ?: $idlm_user->user_login, 0, 1))); ?></span>
                        <span class="idlm-user-identity"><strong><?php echo esc_html($idlm_user->display_name); ?></strong><small><?php echo esc_html($idlm_user->user_email); ?></small></span>
                        <span class="idlm-user-role"><?php echo esc_html(implode(', ', $idlm_role_names)); ?></span>
                        <span class="idlm-user-status <?php echo $idlm_disabled ? 'is-suspended' : ''; ?>"><?php echo $idlm_disabled ? 'Suspended' : 'Active'; ?></span>
                        <span class="idlm-user-edit">Edit <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></span>
                    </summary>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idlm-user-edit-form" data-idlm-pending-form>
                        <input type="hidden" name="action" value="idlm_update_user"><input type="hidden" name="user_id" value="<?php echo esc_attr($idlm_user->ID); ?>"><input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>"><?php wp_nonce_field('idlm_update_user'); ?>
                        <div class="idlm-form-group"><label for="idlm-edit-first-<?php echo esc_attr($idlm_user->ID); ?>">First name</label><input class="idlm-input" required id="idlm-edit-first-<?php echo esc_attr($idlm_user->ID); ?>" name="first_name" value="<?php echo esc_attr($idlm_user->first_name); ?>" autocomplete="given-name"></div>
                        <div class="idlm-form-group"><label for="idlm-edit-last-<?php echo esc_attr($idlm_user->ID); ?>">Last name</label><input class="idlm-input" required id="idlm-edit-last-<?php echo esc_attr($idlm_user->ID); ?>" name="last_name" value="<?php echo esc_attr($idlm_user->last_name); ?>" autocomplete="family-name"></div>
                        <div class="idlm-form-group"><label for="idlm-edit-display-<?php echo esc_attr($idlm_user->ID); ?>">Display name</label><input class="idlm-input" required id="idlm-edit-display-<?php echo esc_attr($idlm_user->ID); ?>" name="display_name" value="<?php echo esc_attr($idlm_user->display_name); ?>" autocomplete="name"></div>
                        <div class="idlm-form-group"><label for="idlm-edit-email-<?php echo esc_attr($idlm_user->ID); ?>">Email</label><input class="idlm-input" required type="email" id="idlm-edit-email-<?php echo esc_attr($idlm_user->ID); ?>" name="email" value="<?php echo esc_attr($idlm_user->user_email); ?>" autocomplete="email" inputmode="email"></div>
                        <div class="idlm-form-group"><label for="idlm-edit-phone-<?php echo esc_attr($idlm_user->ID); ?>">Phone</label><input class="idlm-input" type="tel" id="idlm-edit-phone-<?php echo esc_attr($idlm_user->ID); ?>" name="phone" value="<?php echo esc_attr(get_user_meta($idlm_user->ID, 'idlm_phone', true)); ?>" autocomplete="tel" inputmode="tel"></div>
                        <div class="idlm-form-group"><label for="idlm-edit-role-<?php echo esc_attr($idlm_user->ID); ?>">CRM role</label><select class="idlm-input" id="idlm-edit-role-<?php echo esc_attr($idlm_user->ID); ?>" name="role" aria-describedby="<?php echo esc_attr($idlm_safeguard_id); ?>"><?php foreach ($idlm_roles as $idlm_slug => $idlm_data): ?><option value="<?php echo esc_attr($idlm_slug); ?>" <?php selected(in_array($idlm_slug, (array) $idlm_user->roles, true)); ?>><?php echo esc_html(translate_user_role($idlm_data['name'])); ?></option><?php endforeach; ?></select></div>
                        <p class="idlm-user-safeguard" id="<?php echo esc_attr($idlm_safeguard_id); ?>">You cannot suspend your own access or remove the final administrator.</p>
                        <div class="idlm-user-edit-actions">
                            <label class="idlm-checkbox-row"><input class="idlm-checkbox-control" type="checkbox" name="crm_access_disabled" value="1" <?php checked($idlm_disabled); ?>><span>Suspend CRM access</span></label>
                            <label class="idlm-checkbox-row"><input class="idlm-checkbox-control" type="checkbox" name="send_reset_link" value="1"><span>Send password-reset link</span></label>
                            <button class="idlm-btn idlm-btn-primary" type="submit" data-idlm-submit-label="Saving changes…">Save changes</button>
                        </div>
                    </form>
                </details>
            <?php endforeach; ?>
            <?php if (!$idlm_users): ?><div class="idlm-users-empty"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span><strong>No users match these filters.</strong><p>Try clearing a filter or invite a team member.</p></div><?php endif; ?>
        </div>
    </section>
</div>
