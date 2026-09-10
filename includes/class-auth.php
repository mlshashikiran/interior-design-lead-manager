<?php
/**
 * Scoped frontend authentication and password-recovery entry points.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Auth {
    private static $reset_return_url = '';

    /** Register public authentication handlers and branded core reset screens. */
    public static function init() {
        add_action('admin_post_nopriv_idlm_login', array(__CLASS__, 'handle_login'));
        add_action('admin_post_nopriv_idlm_request_password_reset', array(__CLASS__, 'handle_password_reset_request'));
        add_action('admin_post_nopriv_idlm_reset_password', array(__CLASS__, 'handle_password_reset'));
        add_action('login_enqueue_scripts', array(__CLASS__, 'enqueue_reset_assets'));
        add_filter('login_headerurl', array(__CLASS__, 'login_header_url'));
        add_filter('login_headertext', array(__CLASS__, 'login_header_text'));
    }

    /** Authenticate this CRM form by email while preserving WordPress auth hooks and cookies. */
    public static function handle_login() {
        check_admin_referer('idlm_login');
        $return_url = self::return_url();
        $email = isset($_POST['idlm_email']) ? sanitize_email(wp_unslash($_POST['idlm_email'])) : '';
        $password = isset($_POST['idlm_password']) ? (string) wp_unslash($_POST['idlm_password']) : '';
        $user = $email && is_email($email) ? get_user_by('email', $email) : false;
        $credentials = array(
            'user_login' => $user ? $user->user_login : $email,
            'user_password' => $password,
            'remember' => !empty($_POST['idlm_remember']),
        );
        $authenticated = wp_signon($credentials, is_ssl());
        if (is_wp_error($authenticated)) {
            wp_safe_redirect(add_query_arg('idlm_auth_error', 'invalid-login', $return_url));
            exit;
        }
        wp_safe_redirect($return_url);
        exit;
    }

    /** Send a native WordPress reset email and always return a generic response. */
    public static function handle_password_reset_request() {
        check_admin_referer('idlm_request_password_reset');
        $return_url = self::return_url();
        $email = isset($_POST['idlm_email']) ? sanitize_email(wp_unslash($_POST['idlm_email'])) : '';
        if ($email && is_email($email)) {
            $rate_key = 'idlm_reset_' . substr(hash_hmac('sha256', strtolower($email), wp_salt('nonce')), 0, 32);
            if (false === get_transient($rate_key)) {
                $user = get_user_by('email', $email);
                if ($user) {
                    self::$reset_return_url = $return_url;
                    add_filter('retrieve_password_message', array(__CLASS__, 'password_reset_message'), 10, 4);
                    try {
                        retrieve_password($user->user_login);
                    } finally {
                        remove_filter('retrieve_password_message', array(__CLASS__, 'password_reset_message'), 10);
                        self::$reset_return_url = '';
                    }
                }
                set_transient($rate_key, 1, MINUTE_IN_SECONDS);
            }
        }
        wp_safe_redirect(add_query_arg(array('idlm_auth' => 'forgot', 'idlm_auth_notice' => 'reset-sent'), $return_url));
        exit;
    }

    /** Complete password reset after WordPress validates its user-bound reset key. */
    public static function handle_password_reset() {
        check_admin_referer('idlm_reset_password');
        $return_url = self::return_url();
        $key = isset($_POST['idlm_reset_key']) ? sanitize_text_field(wp_unslash($_POST['idlm_reset_key'])) : '';
        $login = isset($_POST['idlm_reset_login']) ? sanitize_user(wp_unslash($_POST['idlm_reset_login'])) : '';
        $password = isset($_POST['idlm_new_password']) ? (string) wp_unslash($_POST['idlm_new_password']) : '';
        $confirmation = isset($_POST['idlm_confirm_password']) ? (string) wp_unslash($_POST['idlm_confirm_password']) : '';
        $reset_url = add_query_arg(array('idlm_auth' => 'reset', 'key' => $key, 'login' => $login), $return_url);
        $user = check_password_reset_key($key, $login);
        if (is_wp_error($user)) {
            wp_safe_redirect(add_query_arg('idlm_auth_error', 'invalid-key', $reset_url));
            exit;
        }
        if ('' === $password || trim($password) !== $password || strlen($password) < 8) {
            wp_safe_redirect(add_query_arg('idlm_auth_error', 'password-invalid', $reset_url));
            exit;
        }
        if ($password !== $confirmation) {
            wp_safe_redirect(add_query_arg('idlm_auth_error', 'password-mismatch', $reset_url));
            exit;
        }
        $errors = new WP_Error();
        do_action('validate_password_reset', $errors, $user);
        if ($errors->has_errors()) {
            wp_safe_redirect(add_query_arg('idlm_auth_error', 'password-invalid', $reset_url));
            exit;
        }
        reset_password($user, $password);
        wp_safe_redirect(add_query_arg('idlm_auth_notice', 'password-reset', $return_url));
        exit;
    }

    /** Replace the core wp-login reset destination with the CRM reset state. */
    public static function password_reset_message($message, $key, $user_login, $user_data) {
        $site_name = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $reset_url = add_query_arg(array(
            'idlm_auth' => 'reset',
            'key' => $key,
            'login' => $user_login,
        ), self::$reset_return_url ?: home_url('/'));
        return sprintf(
            "A password reset was requested for your %s account.\n\nSet a new password here:\n%s\n\nIf you did not request this, you can ignore this email.",
            $site_name,
            $reset_url
        );
    }

    /** Resolve reset-key state for the guarded CRM reset form. */
    public static function reset_user($key, $login) {
        if (!$key || !$login) {
            return new WP_Error('invalid_key');
        }
        return check_password_reset_key($key, $login);
    }

    /** Load plugin branding only for WordPress core password-recovery screens. */
    public static function enqueue_reset_assets() {
        if (!self::is_password_screen()) {
            return;
        }
        wp_enqueue_style('idlm-login-fonts', IDLM_URL . 'assets/css/fonts.css', array(), IDLM_VERSION);
        wp_enqueue_style('idlm-core-password', IDLM_URL . 'assets/css/login.css', array('idlm-login-fonts'), IDLM_VERSION);
        if (function_exists('wp_add_inline_style')) {
            $white_label = IDLM_Settings::get_white_label();
            $tokens = IDLM_Settings::white_label_style($white_label);
            wp_add_inline_style('idlm-core-password', 'body.login{' . $tokens . '--idlm-login-action:var(--idlm-action);--idlm-login-action-hover:var(--idlm-action-hover);--idlm-login-brand:var(--idlm-brand-gold);--idlm-login-sidebar:var(--idlm-sidebar);}');
        }
    }

    public static function login_header_url($url) {
        return self::is_password_screen() ? home_url('/') : $url;
    }

    public static function login_header_text($text) {
        if (!self::is_password_screen()) {
            return $text;
        }
        $white_label = IDLM_Settings::get_white_label();
        return $white_label['brand_name'] . ' ' . $white_label['workspace_title'];
    }

    private static function return_url() {
        $fallback = home_url('/');
        $url = isset($_POST['idlm_return_url']) ? esc_url_raw(wp_unslash($_POST['idlm_return_url'])) : $fallback;
        return wp_validate_redirect($url, $fallback);
    }

    private static function is_password_screen() {
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : 'login';
        return in_array($action, array('lostpassword', 'retrievepassword', 'resetpass', 'rp'), true);
    }
}
