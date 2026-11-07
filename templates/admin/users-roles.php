<?php
/** Design layer: one role list and one role editor with a single Save. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

$idlm_roles = isset($context['roles']) && is_array($context['roles']) ? $context['roles'] : array();
$idlm_return_url = (string) ($context['return_url'] ?? '');
$idlm_roles_url = (string) ($context['roles_url'] ?? '');
$idlm_team_url = (string) ($context['team_url'] ?? '');
$idlm_view_base_url = (string) ($context['view_base_url'] ?? '');
$idlm_permission_roles = IDLM_Admin_Users_Controller::permission_roles();
$idlm_all_scopes = IDLM_Admin_Users_Controller::role_scopes();
$idlm_user_counts = function_exists('count_users') ? count_users() : array();
$idlm_role_counts = isset($idlm_user_counts['avail_roles']) && is_array($idlm_user_counts['avail_roles']) ? $idlm_user_counts['avail_roles'] : array();
$idlm_people = static function($idlm_count) { return 1 === (int) $idlm_count ? '1 person' : sprintf('%d people', (int) $idlm_count); };
$idlm_ordered = IDLM_Admin_Users_Controller::pick_list_roles($idlm_roles);
$idlm_ordered = $idlm_ordered['main'] + $idlm_ordered['website'];
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only choice of which role to show.
$idlm_edit_slug = isset($_GET['role_edit']) ? sanitize_key(wp_unslash($_GET['role_edit'])) : '';
if (!isset($idlm_roles[$idlm_edit_slug])) { $idlm_edit_slug = ''; }
?>
<?php if ('' === $idlm_edit_slug): ?>
<section class="idlm-role-list-section" aria-labelledby="idlm-role-list-title">
    <div class="idlm-users-section-head">
        <h2 id="idlm-role-list-title">Roles</h2>
        <p>Each role sets which leads people see and what they can do. Open a role to change it.</p>
    </div>
    <ul class="idlm-role-list">
        <?php foreach ($idlm_ordered as $idlm_slug => $idlm_label):
            if (isset($idlm_permission_roles[$idlm_slug])) {
                $idlm_role_object = get_role($idlm_slug);
                $idlm_permissions = IDLM_Admin_Users_Controller::selectable_permissions($idlm_slug);
                $idlm_on = 0;
                foreach ($idlm_permissions as $idlm_capability => $idlm_permission_label) { if ($idlm_role_object && $idlm_role_object->has_cap($idlm_capability)) { ++$idlm_on; } }
                $idlm_permission_text = sprintf('%1$d of %2$d permissions on', $idlm_on, count($idlm_permissions));
            } else {
                $idlm_permission_text = 'administrator' === $idlm_slug ? 'All permissions' : 'Set per person';
            }
        ?>
        <li class="idlm-role-row">
            <a href="<?php echo esc_url(add_query_arg('role_edit', $idlm_slug, $idlm_roles_url)); ?>">
                <strong class="idlm-role-row-name"><?php echo esc_html($idlm_label); ?></strong>
                <span class="idlm-role-row-people"><?php echo esc_html($idlm_people($idlm_role_counts[$idlm_slug] ?? 0)); ?></span>
                <span class="idlm-role-row-visibility"><?php echo esc_html(IDLM_Admin_Users_Controller::role_visibility_label($idlm_slug)); ?></span>
                <span class="idlm-role-row-permissions"><?php echo esc_html($idlm_permission_text); ?></span>
                <span class="idlm-role-row-open"><?php echo 'administrator' === $idlm_slug ? 'View' : 'Edit'; ?></span>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php else:
    $idlm_label = IDLM_Admin_Users_Controller::role_label($idlm_edit_slug);
    $idlm_count = (int) ($idlm_role_counts[$idlm_edit_slug] ?? 0);
    $idlm_is_admin_role = 'administrator' === $idlm_edit_slug;
    $idlm_has_permissions = isset($idlm_permission_roles[$idlm_edit_slug]);
    $idlm_scope = isset($idlm_all_scopes[$idlm_edit_slug]) && in_array($idlm_all_scopes[$idlm_edit_slug], array('all', 'assigned'), true) ? $idlm_all_scopes[$idlm_edit_slug] : 'default';
?>
<section class="idlm-role-editor" aria-labelledby="idlm-role-editor-title">
    <p class="idlm-role-editor-back"><a href="<?php echo esc_url($idlm_roles_url); ?>"><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span> All roles</a></p>
    <div class="idlm-users-section-head">
        <h2 id="idlm-role-editor-title"><?php echo esc_html($idlm_label); ?></h2>
        <p><?php echo esc_html($idlm_people($idlm_count)); ?> with this role. <a href="<?php echo esc_url(add_query_arg('role', $idlm_edit_slug, $idlm_team_url)); ?>">View people</a></p>
    </div>
<?php if ($idlm_is_admin_role): ?>
    <p class="idlm-role-editor-note">Administrators always see all leads and manage settings and users. This role cannot be changed here. To change what one person can do, give them a different role on the Team view.</p>
<?php else:
    $idlm_role_object = get_role($idlm_edit_slug);
    $idlm_base = IDLM_Admin_Users_Controller::default_scope_outcome($idlm_edit_slug, true, false);
    $idlm_assign = IDLM_Admin_Users_Controller::default_scope_outcome($idlm_edit_slug, true, true);
    $idlm_none = IDLM_Admin_Users_Controller::default_scope_outcome($idlm_edit_slug, false, false);
    $idlm_now = IDLM_Admin_Users_Controller::default_scope_outcome($idlm_edit_slug);
?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idlm-permission-form idlm-role-editor-form" data-idlm-pending-form data-idlm-role-name="<?php echo esc_attr($idlm_label); ?>" data-idlm-role-people="<?php echo esc_attr($idlm_count); ?>">
        <input type="hidden" name="action" value="idlm_update_role_access">
        <input type="hidden" name="access_role" value="<?php echo esc_attr($idlm_edit_slug); ?>">
        <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($idlm_return_url); ?>">
        <?php wp_nonce_field('idlm_update_role_access'); ?>
        <fieldset class="idlm-role-scope-group">
            <legend>Which leads they see</legend>
            <label class="idlm-role-scope-option"><input type="radio" name="role_scope" value="default" data-idlm-role-scope <?php checked($idlm_scope, 'default'); ?>><span><strong>Default for this role</strong> <span data-idlm-default-outcome data-idlm-text-base="<?php echo esc_attr($idlm_base['text']); ?>" data-idlm-text-assign="<?php echo esc_attr($idlm_assign['text']); ?>" data-idlm-text-none="<?php echo esc_attr($idlm_none['text']); ?>" data-idlm-base-depends="<?php echo $idlm_base['depends_on_setting'] ? '1' : '0'; ?>"><?php echo esc_html($idlm_now['text']); ?></span> <a data-idlm-default-link href="<?php echo esc_url(add_query_arg('idlm_view', 'settings', $idlm_view_base_url) . '#tab-general'); ?>"<?php echo $idlm_now['depends_on_setting'] ? '' : ' hidden'; ?>>Open the setting</a></span></label>
            <label class="idlm-role-scope-option"><input type="radio" name="role_scope" value="assigned" data-idlm-role-scope <?php checked($idlm_scope, 'assigned'); ?>><span><strong>Only assigned leads</strong> People see only leads assigned to them.</span></label>
            <label class="idlm-role-scope-option"><input type="radio" name="role_scope" value="all" data-idlm-role-scope <?php checked($idlm_scope, 'all'); ?>><span><strong>All leads</strong> People see every lead, whoever it is assigned to.</span></label>
        </fieldset>
<?php if ($idlm_has_permissions):
    $idlm_permissions = IDLM_Admin_Users_Controller::selectable_permissions($idlm_edit_slug);
    $idlm_needs = IDLM_Admin_Users_Controller::permission_dependencies();
    foreach (IDLM_Admin_Users_Controller::permission_groups() as $idlm_group => $idlm_group_caps):
        $idlm_group_permissions = array_intersect_key($idlm_permissions, array_flip($idlm_group_caps));
        if (!$idlm_group_permissions) { continue; }
?>
        <fieldset class="idlm-permission-group">
            <legend><?php echo esc_html($idlm_group); ?></legend>
            <div class="idlm-permission-group-tools"><button type="button" class="idlm-action-link" data-idlm-permission-all="1">Select all</button><button type="button" class="idlm-action-link" data-idlm-permission-all="0">Clear</button></div>
            <div class="idlm-permission-options">
            <?php foreach ($idlm_group_permissions as $idlm_capability => $idlm_permission_label):
                $idlm_needed = isset($idlm_needs[$idlm_capability], $idlm_permissions[$idlm_needs[$idlm_capability]]) ? $idlm_needs[$idlm_capability] : '';
            ?>
                <label class="idlm-permission-option"><input type="checkbox" name="permissions[<?php echo esc_attr($idlm_capability); ?>]" value="1" data-idlm-capability="<?php echo esc_attr($idlm_capability); ?>" data-idlm-capability-label="<?php echo esc_attr($idlm_permission_label); ?>"<?php if ($idlm_needed): ?> data-idlm-needs="<?php echo esc_attr($idlm_needed); ?>"<?php endif; ?> <?php checked($idlm_role_object && $idlm_role_object->has_cap($idlm_capability)); ?>><span><?php echo esc_html($idlm_permission_label); ?></span></label>
            <?php endforeach; ?>
            </div>
        </fieldset>
<?php
    endforeach;
?>
        <div class="idlm-role-warnings" data-idlm-permission-warnings role="status"></div>
<?php else: ?>
        <p class="idlm-role-editor-note">Website roles have no CRM permissions of their own, so only lead visibility is set here. People invited into the CRM with this role get their permissions on their own account.</p>
<?php endif; ?>
        <div class="idlm-permission-actions"><span data-idlm-permission-status data-idlm-saved-text="<?php echo esc_attr(sprintf('Saving applies to %s with this role.', $idlm_people($idlm_count))); ?>" data-idlm-dirty-text="<?php echo esc_attr(sprintf('Unsaved changes. Saving applies to %s with this role.', $idlm_people($idlm_count))); ?>" role="status"><?php echo esc_html(sprintf('Saving applies to %s with this role.', $idlm_people($idlm_count))); ?></span><button class="idlm-btn idlm-btn-primary" type="submit" data-idlm-submit-label="Saving role…">Save role</button></div>
    </form>
<?php endif; ?>
</section>
<?php endif; ?>
