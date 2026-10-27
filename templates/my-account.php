<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_user = isset($context['user']) ? $context['user'] : wp_get_current_user();
$idlm_phone = isset($context['phone']) ? (string) $context['phone'] : '';
$idlm_notice = isset($context['notice']) ? sanitize_key($context['notice']) : '';
$idlm_error = isset($context['error']) ? sanitize_key($context['error']) : '';
$idlm_logout_url = isset($context['logout_url']) ? (string) $context['logout_url'] : wp_logout_url();
$idlm_white_label = isset($context['white_label']) && is_array($context['white_label']) ? $context['white_label'] : IDLM_Settings::get_white_label();
$idlm_brand_logo = IDLM_Settings::brand_asset($idlm_white_label['logo_id']);
?>
<div class="wrap idlm-wrap idlm-account-wrap">
    <header class="idlm-header">
        <div class="idlm-header-meta"><h1>My Account</h1></div>
    </header>
    <?php if ($idlm_notice): ?><div class="idlm-alert idlm-account-success" role="status" tabindex="-1" data-idlm-notice><?php echo esc_html('reset-sent' === $idlm_notice ? 'Password reset instructions were sent to your email address.' : 'Your account details were updated.'); ?></div><?php endif; ?>
    <?php if ($idlm_error): ?><div class="idlm-alert idlm-alert-warning" role="alert" tabindex="-1" data-idlm-notice><?php echo esc_html('email-exists' === $idlm_error ? 'That email address is already used by another account.' : ('password-mismatch' === $idlm_error ? 'The new passwords do not match.' : ('password-invalid' === $idlm_error ? 'Use a password with at least eight characters.' : 'Your account could not be updated. Please try again.'))); ?></div><?php endif; ?>
    <div class="idlm-account-layout">
        <aside class="idlm-card idlm-account-summary">
            <div class="idlm-account-brand"><?php if ($idlm_brand_logo['url']): ?><img src="<?php echo esc_url($idlm_brand_logo['url']); ?>" alt="<?php echo esc_attr($idlm_white_label['brand_name']); ?>"><?php else: ?><strong><?php echo esc_html($idlm_white_label['brand_name']); ?></strong><?php endif; ?></div>
            <?php echo get_avatar($idlm_user->ID, 96, '', $idlm_user->display_name, array('class' => 'idlm-account-avatar')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress-owned markup. ?>
            <h2><?php echo esc_html($idlm_user->display_name); ?></h2>
            <p><?php echo esc_html($idlm_user->user_email); ?></p>
            <div class="idlm-account-roles"><?php foreach ((array) $idlm_user->roles as $idlm_role): ?><span class="idlm-chip"><?php echo esc_html(translate_user_role(wp_roles()->roles[$idlm_role]['name'] ?? $idlm_role)); ?></span><?php endforeach; ?></div>
            <a class="idlm-btn idlm-btn-secondary idlm-account-signout" href="<?php echo esc_url($idlm_logout_url); ?>">Sign Out</a>
        </aside>
        <form class="idlm-card idlm-account-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-idlm-password-form data-idlm-pending-label="Saving account…">
            <input type="hidden" name="action" value="idlm_update_my_account">
            <input type="hidden" name="idlm_return_url" value="<?php echo esc_url(add_query_arg('idlm_view', 'my-account', remove_query_arg(array('idlm_account_notice', 'idlm_account_error')))); ?>">
            <?php wp_nonce_field('idlm_update_my_account'); ?>
            <section><h2>Profile information</h2><div class="idlm-form-grid two">
                <div class="idlm-form-group"><label for="idlm-account-first-name">First name</label><input class="idlm-input" id="idlm-account-first-name" name="first_name" autocomplete="given-name" value="<?php echo esc_attr($idlm_user->first_name); ?>"></div>
                <div class="idlm-form-group"><label for="idlm-account-last-name">Last name</label><input class="idlm-input" id="idlm-account-last-name" name="last_name" autocomplete="family-name" value="<?php echo esc_attr($idlm_user->last_name); ?>"></div>
                <div class="idlm-form-group"><label for="idlm-account-email">Email <span class="idlm-required">(required)</span></label><input class="idlm-input" type="email" required id="idlm-account-email" name="email" autocomplete="email" value="<?php echo esc_attr($idlm_user->user_email); ?>"></div>
                <div class="idlm-form-group"><label for="idlm-account-phone">Phone</label><input class="idlm-input" type="tel" inputmode="tel" id="idlm-account-phone" name="phone" autocomplete="tel" value="<?php echo esc_attr($idlm_phone); ?>"></div>
            </div></section>
            <section class="idlm-account-security"><h2>Password and security</h2><p class="idlm-card-desc">Leave both password fields blank to keep your current password.</p><div class="idlm-form-grid two">
                <div class="idlm-form-group"><label for="idlm-account-password">New password</label><div class="idlm-password-field"><input class="idlm-input" type="password" minlength="8" autocomplete="new-password" aria-describedby="idlm-account-password-help" id="idlm-account-password" name="new_password" data-idlm-password-input data-idlm-password-primary><button type="button" class="idlm-password-toggle" aria-controls="idlm-account-password" aria-pressed="false" aria-label="Show new password" data-idlm-password-toggle><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button></div><p class="idlm-field-help" id="idlm-account-password-help">Use at least eight characters and a password you do not use elsewhere.</p></div>
                <div class="idlm-form-group"><label for="idlm-account-password-confirm">Confirm new password</label><div class="idlm-password-field"><input class="idlm-input" type="password" minlength="8" autocomplete="new-password" aria-describedby="idlm-account-password-help idlm-account-password-match-error" id="idlm-account-password-confirm" name="confirm_password" data-idlm-password-input data-idlm-password-confirm><button type="button" class="idlm-password-toggle" aria-controls="idlm-account-password-confirm" aria-pressed="false" aria-label="Show password confirmation" data-idlm-password-toggle><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button></div><p class="idlm-field-error" id="idlm-account-password-match-error" role="alert" hidden data-idlm-password-error>The passwords do not match.</p></div>
            </div><label class="idlm-checkbox-row"><input type="checkbox" name="send_reset_link" value="1"><span>Email me a secure password-reset link instead</span></label></section>
            <div class="idlm-account-actions"><button class="idlm-btn idlm-btn-primary" type="submit">Save Account</button></div>
        </form>
    </div>
</div>
