<?php
if (!defined('ABSPATH')) {
    exit;
}

$wrapper_class = !empty($context['admin']) ? 'wrap idlm-wrap' : 'idlm-wrap';
$is_logged_in = is_user_logged_in();
$auth_view = isset($_GET['idlm_auth']) ? sanitize_key(wp_unslash($_GET['idlm_auth'])) : 'login';
$auth_error = isset($_GET['idlm_auth_error']) ? sanitize_key(wp_unslash($_GET['idlm_auth_error'])) : '';
$auth_notice = isset($_GET['idlm_auth_notice']) ? sanitize_key(wp_unslash($_GET['idlm_auth_notice'])) : '';
$request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
$return_url = home_url(remove_query_arg(array('idlm_auth', 'idlm_auth_error', 'idlm_auth_notice', 'key', 'login'), $request_uri));
$reset_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
$reset_login = isset($_GET['login']) ? sanitize_user(wp_unslash($_GET['login'])) : '';
$reset_user = 'reset' === $auth_view ? IDLM_Auth::reset_user($reset_key, $reset_login) : false;
$white_label = IDLM_Settings::get_white_label();
$brand_logo = IDLM_Settings::brand_asset($white_label['logo_id']);
?>
<div class="<?php echo esc_attr($wrapper_class); ?>" style="<?php echo esc_attr(IDLM_Settings::white_label_style($white_label)); ?>">
    <?php if (!$is_logged_in) : ?>
        <main class="idlm-login-shell idlm-access-restricted" aria-labelledby="idlm-login-title">
            <section class="idlm-login-context" aria-label="Lead Manager overview">
                <div class="idlm-login-brand"><?php if ($brand_logo['url']): ?><img src="<?php echo esc_url($brand_logo['url']); ?>" alt=""><?php else: ?><span class="dashicons dashicons-admin-home" aria-hidden="true"></span><?php endif; ?><span><?php echo esc_html($white_label['brand_name']); ?></span></div>
                <div class="idlm-login-context-copy">
                    <p class="idlm-login-product">Lead Manager</p>
                    <h1><?php echo esc_html($white_label['login_heading']); ?></h1>
                    <p>Review new enquiries, coordinate follow-ups, and move interior projects forward with your team.</p>
                </div>
                <p class="idlm-login-access-note">Authorized team access only</p>
            </section>
            <section class="idlm-login-panel">
                <div class="idlm-login-panel-heading">
                    <?php if ('reset' === $auth_view) : ?>
                        <h2 id="idlm-login-title">Choose a new password</h2>
                        <p>Use a strong password that you do not use on another account.</p>
                    <?php elseif ('forgot' === $auth_view) : ?>
                        <h2 id="idlm-login-title">Reset your password</h2>
                        <p>Enter your account email and we will send a secure reset link.</p>
                    <?php else : ?>
                        <h2 id="idlm-login-title">Welcome back</h2>
                        <p>Sign in with your account email to continue.</p>
                    <?php endif; ?>
                </div>
                <?php if ('reset-sent' === $auth_notice) : ?><div class="idlm-auth-message" role="status" tabindex="-1" data-idlm-notice>If an account matches that email, a reset link has been sent.</div><?php endif; ?>
                <?php if ('password-reset' === $auth_notice) : ?><div class="idlm-auth-message" role="status" tabindex="-1" data-idlm-notice>Your password has been reset. You can sign in now.</div><?php endif; ?>
                <?php if ('invalid-login' === $auth_error) : ?><div class="idlm-auth-message is-error" role="alert" tabindex="-1" data-idlm-notice>The email or password is incorrect. Please try again.</div><?php endif; ?>
                <?php if ('reset' === $auth_view && is_wp_error($reset_user)) : ?>
                    <div class="idlm-auth-message is-error" role="alert" tabindex="-1" data-idlm-notice>This reset link is invalid or has expired. Request a new link to continue.</div>
                    <a class="idlm-login-reset" href="<?php echo esc_url(add_query_arg('idlm_auth', 'forgot', $return_url)); ?>">Request a new reset link</a>
                <?php elseif ('reset' === $auth_view) : ?>
                    <?php if (in_array($auth_error, array('password-invalid', 'password-mismatch'), true)) : ?><div class="idlm-auth-message is-error" role="alert" tabindex="-1" data-idlm-notice><?php echo esc_html('password-mismatch' === $auth_error ? 'The passwords do not match.' : 'Use at least eight characters without leading or trailing spaces.'); ?></div><?php endif; ?>
                    <form class="idlm-login-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-idlm-password-form data-idlm-pending-label="Saving password…">
                        <input type="hidden" name="action" value="idlm_reset_password">
                        <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($return_url); ?>">
                        <input type="hidden" name="idlm_reset_key" value="<?php echo esc_attr($reset_key); ?>">
                        <input type="hidden" name="idlm_reset_login" value="<?php echo esc_attr($reset_login); ?>">
                        <?php wp_nonce_field('idlm_reset_password'); ?>
                        <div class="idlm-form-group">
                            <label for="idlm-new-password">New password <span class="idlm-required">(required)</span></label>
                            <div class="idlm-password-field">
                                <input class="input" type="password" name="idlm_new_password" id="idlm-new-password" minlength="8" autocomplete="new-password" aria-describedby="idlm-reset-password-help" required data-idlm-password-input data-idlm-password-primary>
                                <button type="button" class="idlm-password-toggle" aria-controls="idlm-new-password" aria-pressed="false" aria-label="Show new password" data-idlm-password-toggle><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button>
                            </div>
                            <p class="idlm-field-help" id="idlm-reset-password-help">Use at least eight characters and a password you do not use elsewhere.</p>
                        </div>
                        <div class="idlm-form-group">
                            <label for="idlm-confirm-password">Confirm new password <span class="idlm-required">(required)</span></label>
                            <div class="idlm-password-field">
                                <input class="input" type="password" name="idlm_confirm_password" id="idlm-confirm-password" minlength="8" autocomplete="new-password" aria-describedby="idlm-reset-password-help idlm-reset-password-match-error" required data-idlm-password-input data-idlm-password-confirm>
                                <button type="button" class="idlm-password-toggle" aria-controls="idlm-confirm-password" aria-pressed="false" aria-label="Show password confirmation" data-idlm-password-toggle><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button>
                            </div>
                            <p class="idlm-field-error" id="idlm-reset-password-match-error" role="alert" hidden data-idlm-password-error>The passwords do not match.</p>
                        </div>
                        <p class="login-submit"><button class="button-primary" type="submit">Save New Password</button></p>
                    </form>
                <?php elseif ('forgot' === $auth_view) : ?>
                    <form class="idlm-login-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-idlm-pending-label="Sending reset link…">
                        <input type="hidden" name="action" value="idlm_request_password_reset">
                        <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($return_url); ?>">
                        <?php wp_nonce_field('idlm_request_password_reset'); ?>
                        <p><label for="idlm-reset-email">Email address <span class="idlm-required">(required)</span></label><input class="input" type="email" name="idlm_email" id="idlm-reset-email" autocomplete="email" required></p>
                        <p class="login-submit"><button class="button-primary" type="submit">Send Reset Link</button></p>
                    </form>
                    <a class="idlm-login-reset" href="<?php echo esc_url($return_url); ?>">Back to sign in</a>
                <?php else : ?>
                    <form class="idlm-login-form" id="idlm-loginform" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-idlm-pending-label="Signing in…">
                        <input type="hidden" name="action" value="idlm_login">
                        <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($return_url); ?>">
                        <?php wp_nonce_field('idlm_login'); ?>
                        <p><label for="idlm-user-email">Email address <span class="idlm-required">(required)</span></label><input class="input" type="email" name="idlm_email" id="idlm-user-email" autocomplete="username" required></p>
                        <div class="idlm-form-group">
                            <label for="idlm-user-pass">Password <span class="idlm-required">(required)</span></label>
                            <div class="idlm-password-field">
                                <input class="input" type="password" name="idlm_password" id="idlm-user-pass" autocomplete="current-password" spellcheck="false" required data-idlm-password-input>
                                <button type="button" class="idlm-password-toggle" aria-controls="idlm-user-pass" aria-pressed="false" aria-label="Show password" data-idlm-password-toggle><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button>
                            </div>
                        </div>
                        <p class="login-remember"><label><input name="idlm_remember" type="checkbox" value="1"> Remember me</label></p>
                        <p class="login-submit"><button class="button-primary" type="submit">Sign In</button></p>
                    </form>
                    <a class="idlm-login-reset" href="<?php echo esc_url(add_query_arg('idlm_auth', 'forgot', $return_url)); ?>">Forgot your password?</a>
                <?php endif; ?>
                <p class="idlm-login-help">Need access? <?php if ($white_label['support_email']): ?><a href="mailto:<?php echo esc_attr($white_label['support_email']); ?>">Contact support</a><?php else: ?>Contact your CRM administrator.<?php endif; ?></p>
                <?php if ($white_label['footer_text']): ?><p class="idlm-login-footer-text"><?php echo esc_html($white_label['footer_text']); ?></p><?php endif; ?>
                <?php if ('1' === $white_label['show_plugin_attribution']): ?><p class="idlm-login-attribution">Powered by Interior Design Lead Manager</p><?php endif; ?>
            </section>
        </main>
    <?php else : ?>
        <div class="idlm-card idlm-access-restricted">
            <h3>Access Restricted</h3>
            <p>Your account does not have permission to access this resource. Contact an administrator if you need CRM access.</p>
        </div>
    <?php endif; ?>
</div>
