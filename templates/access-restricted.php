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
?>
<div class="<?php echo esc_attr($wrapper_class); ?>">
    <?php if (!$is_logged_in) : ?>
        <main class="idlm-login-shell idlm-access-restricted" aria-labelledby="idlm-login-title">
            <section class="idlm-login-context" aria-label="Lead Manager overview">
                <div class="idlm-login-brand"><span class="dashicons dashicons-admin-home" aria-hidden="true"></span><span>Q Oak Interiors</span></div>
                <div class="idlm-login-context-copy">
                    <p class="idlm-login-product">Lead Manager</p>
                    <h1>One workspace for every customer conversation.</h1>
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
                <?php if ('reset-sent' === $auth_notice) : ?><div class="idlm-auth-message" role="status">If an account matches that email, a reset link has been sent.</div><?php endif; ?>
                <?php if ('password-reset' === $auth_notice) : ?><div class="idlm-auth-message" role="status">Your password has been reset. You can sign in now.</div><?php endif; ?>
                <?php if ('invalid-login' === $auth_error) : ?><div class="idlm-auth-message is-error" role="alert">The email or password is incorrect. Please try again.</div><?php endif; ?>
                <?php if ('reset' === $auth_view && is_wp_error($reset_user)) : ?>
                    <div class="idlm-auth-message is-error" role="alert">This reset link is invalid or has expired. Request a new link to continue.</div>
                    <a class="idlm-login-reset" href="<?php echo esc_url(add_query_arg('idlm_auth', 'forgot', $return_url)); ?>">Request a new reset link</a>
                <?php elseif ('reset' === $auth_view) : ?>
                    <?php if (in_array($auth_error, array('password-invalid', 'password-mismatch'), true)) : ?><div class="idlm-auth-message is-error" role="alert"><?php echo esc_html('password-mismatch' === $auth_error ? 'The passwords do not match.' : 'Enter a valid password without leading or trailing spaces.'); ?></div><?php endif; ?>
                    <form class="idlm-login-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="idlm_reset_password">
                        <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($return_url); ?>">
                        <input type="hidden" name="idlm_reset_key" value="<?php echo esc_attr($reset_key); ?>">
                        <input type="hidden" name="idlm_reset_login" value="<?php echo esc_attr($reset_login); ?>">
                        <?php wp_nonce_field('idlm_reset_password'); ?>
                        <p><label for="idlm-new-password">New password</label><input class="input" type="password" name="idlm_new_password" id="idlm-new-password" autocomplete="new-password" required></p>
                        <p><label for="idlm-confirm-password">Confirm new password</label><input class="input" type="password" name="idlm_confirm_password" id="idlm-confirm-password" autocomplete="new-password" required></p>
                        <p class="login-submit"><button class="button-primary" type="submit">Save New Password</button></p>
                    </form>
                <?php elseif ('forgot' === $auth_view) : ?>
                    <form class="idlm-login-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="idlm_request_password_reset">
                        <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($return_url); ?>">
                        <?php wp_nonce_field('idlm_request_password_reset'); ?>
                        <p><label for="idlm-reset-email">Email address</label><input class="input" type="email" name="idlm_email" id="idlm-reset-email" autocomplete="email" required></p>
                        <p class="login-submit"><button class="button-primary" type="submit">Send Reset Link</button></p>
                    </form>
                    <a class="idlm-login-reset" href="<?php echo esc_url($return_url); ?>">Back to sign in</a>
                <?php else : ?>
                    <form class="idlm-login-form" id="idlm-loginform" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="idlm_login">
                        <input type="hidden" name="idlm_return_url" value="<?php echo esc_url($return_url); ?>">
                        <?php wp_nonce_field('idlm_login'); ?>
                        <p><label for="idlm-user-email">Email address</label><input class="input" type="email" name="idlm_email" id="idlm-user-email" autocomplete="username" required></p>
                        <p><label for="idlm-user-pass">Password</label><input class="input" type="password" name="idlm_password" id="idlm-user-pass" autocomplete="current-password" spellcheck="false" required></p>
                        <p class="login-remember"><label><input name="idlm_remember" type="checkbox" value="1"> Remember me</label></p>
                        <p class="login-submit"><button class="button-primary" type="submit">Sign In</button></p>
                    </form>
                    <a class="idlm-login-reset" href="<?php echo esc_url(add_query_arg('idlm_auth', 'forgot', $return_url)); ?>">Forgot your password?</a>
                <?php endif; ?>
                <p class="idlm-login-help">Need access? Contact your CRM administrator.</p>
            </section>
        </main>
    <?php else : ?>
        <div class="idlm-card idlm-access-restricted">
            <h3>Access Restricted</h3>
            <p>Your account does not have permission to access this resource. Contact an administrator if you need CRM access.</p>
        </div>
    <?php endif; ?>
</div>
