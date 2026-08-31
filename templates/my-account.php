<?php
if (!defined('ABSPATH')) {
    exit;
}

$user = isset($context['user']) ? $context['user'] : wp_get_current_user();
$phone = isset($context['phone']) ? (string) $context['phone'] : '';
$notice = isset($context['notice']) ? sanitize_key($context['notice']) : '';
$error = isset($context['error']) ? sanitize_key($context['error']) : '';
$logout_url = isset($context['logout_url']) ? (string) $context['logout_url'] : wp_logout_url();
?>
<div class="wrap idlm-wrap idlm-account-wrap">
    <header class="idlm-header">
        <div class="idlm-header-meta"><h1>My Account</h1><p class="idlm-header-subtitle">Manage your profile details and account security</p></div>
    </header>
    <?php if ($notice): ?><div class="idlm-alert idlm-account-success" role="status"><?php echo esc_html('reset-sent' === $notice ? 'Password reset instructions were sent to your email address.' : 'Your account details were updated.'); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="idlm-alert idlm-alert-warning" role="alert"><?php echo esc_html('email-exists' === $error ? 'That email address is already used by another account.' : ('password-mismatch' === $error ? 'The new passwords do not match.' : ('password-invalid' === $error ? 'Use a password with at least eight characters.' : 'Your account could not be updated. Please try again.'))); ?></div><?php endif; ?>
    <div class="idlm-account-layout">
        <aside class="idlm-card idlm-account-summary">
            <?php echo get_avatar($user->ID, 96, '', $user->display_name, array('class' => 'idlm-account-avatar')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress-owned markup. ?>
            <h2><?php echo esc_html($user->display_name); ?></h2>
            <p><?php echo esc_html($user->user_email); ?></p>
            <div class="idlm-account-roles"><?php foreach ((array) $user->roles as $role): ?><span class="idlm-chip"><?php echo esc_html(translate_user_role(wp_roles()->roles[$role]['name'] ?? $role)); ?></span><?php endforeach; ?></div>
            <a class="idlm-btn idlm-btn-secondary idlm-account-signout" href="<?php echo esc_url($logout_url); ?>">Sign Out</a>
        </aside>
        <form class="idlm-card idlm-account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="idlm_update_my_account">
            <input type="hidden" name="idlm_return_url" value="<?php echo esc_url(add_query_arg('idlm_view', 'my-account', remove_query_arg(array('idlm_account_notice', 'idlm_account_error')))); ?>">
            <?php wp_nonce_field('idlm_update_my_account'); ?>
            <section><h2>Profile information</h2><div class="idlm-form-grid two">
                <div class="idlm-form-group"><label for="idlm-account-first-name">First name</label><input class="idlm-input" id="idlm-account-first-name" name="first_name" value="<?php echo esc_attr($user->first_name); ?>"></div>
                <div class="idlm-form-group"><label for="idlm-account-last-name">Last name</label><input class="idlm-input" id="idlm-account-last-name" name="last_name" value="<?php echo esc_attr($user->last_name); ?>"></div>
                <div class="idlm-form-group"><label for="idlm-account-email">Email</label><input class="idlm-input" type="email" required id="idlm-account-email" name="email" value="<?php echo esc_attr($user->user_email); ?>"></div>
                <div class="idlm-form-group"><label for="idlm-account-phone">Phone</label><input class="idlm-input" type="tel" id="idlm-account-phone" name="phone" value="<?php echo esc_attr($phone); ?>"></div>
            </div></section>
            <section class="idlm-account-security"><h2>Password and security</h2><p class="idlm-card-desc">Leave both password fields blank to keep your current password.</p><div class="idlm-form-grid two">
                <div class="idlm-form-group"><label for="idlm-account-password">New password</label><input class="idlm-input" type="password" minlength="8" autocomplete="new-password" id="idlm-account-password" name="new_password"></div>
                <div class="idlm-form-group"><label for="idlm-account-password-confirm">Confirm new password</label><input class="idlm-input" type="password" minlength="8" autocomplete="new-password" id="idlm-account-password-confirm" name="confirm_password"></div>
            </div><label class="idlm-checkbox-row"><input type="checkbox" name="send_reset_link" value="1"><span>Email me a secure password-reset link instead</span></label></section>
            <div class="idlm-account-actions"><button class="idlm-btn idlm-btn-primary" type="submit">Save Account</button></div>
        </form>
    </div>
</div>
