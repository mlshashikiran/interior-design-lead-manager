<?php
/**
 * Frontend account presentation and authenticated profile updates.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Admin_Account_Controller {
    /** Render the current user's frontend account workspace. */
    public static function render() {
        $user = wp_get_current_user();
        $phone = (string) get_user_meta($user->ID, 'idlm_phone', true);
        if ('' === $phone) {
            $phone = (string) get_user_meta($user->ID, 'billing_phone', true);
        }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0078: render(): render requires a logged-in actor and GET enums only select static notices; handle_update verifies the account nonce and always targets get_current_user_id; password strings retain exact bytes after shape checks.
        $notice = isset($_GET['idlm_account_notice']) ? sanitize_key(wp_unslash($_GET['idlm_account_notice'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0079: render(): render requires a logged-in actor and GET enums only select static notices; handle_update verifies the account nonce and always targets get_current_user_id; password strings retain exact bytes after shape checks.
        $error = isset($_GET['idlm_account_error']) ? sanitize_key(wp_unslash($_GET['idlm_account_error'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $account_url = add_query_arg('idlm_view', 'my-account', remove_query_arg(array('idlm_account_notice', 'idlm_account_error')));
        IDLM_View::display('my-account.php', array(
            'user' => $user,
            'phone' => $phone,
            'notice' => $notice,
            'error' => $error,
            'logout_url' => wp_logout_url($account_url),
            'white_label' => IDLM_Settings::get_white_label(),
        ));
    }

    /** Save the current user's frontend account details. */
    public static function handle_update() {
        if (!is_user_logged_in()) {
            wp_die('Authentication required.', 403);
        }
        check_admin_referer('idlm_update_my_account');
        $user_id = get_current_user_id();
        $return_url = isset($_POST['idlm_return_url']) ? esc_url_raw(wp_unslash($_POST['idlm_return_url'])) : home_url('/');
        $return_url = wp_validate_redirect($return_url, home_url('/'));
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $existing_user_id = $email ? email_exists($email) : false;
        if (!$email || ($existing_user_id && absint($existing_user_id) !== $user_id)) {
            wp_safe_redirect(add_query_arg('idlm_account_error', $existing_user_id ? 'email-exists' : 'update-failed', $return_url));
            exit;
        }
        if ((isset($_POST['new_password']) && !is_string($_POST['new_password'])) || (isset($_POST['confirm_password']) && !is_string($_POST['confirm_password']))) {
            wp_die('Invalid password value.', '', array('response' => 400));
        }
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0080: handle_update(): render requires a logged-in actor and GET enums only select static notices; handle_update verifies the account nonce and always targets get_current_user_id; password strings retain exact bytes after shape checks.
        $password = isset($_POST['new_password']) ? wp_unslash($_POST['new_password']) : '';
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0081: handle_update(): render requires a logged-in actor and GET enums only select static notices; handle_update verifies the account nonce and always targets get_current_user_id; password strings retain exact bytes after shape checks.
        $password_confirm = isset($_POST['confirm_password']) ? wp_unslash($_POST['confirm_password']) : '';
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ($password !== $password_confirm) {
            wp_safe_redirect(add_query_arg('idlm_account_error', 'password-mismatch', $return_url));
            exit;
        }
        if ('' !== $password && strlen($password) < 8) {
            wp_safe_redirect(add_query_arg('idlm_account_error', 'password-invalid', $return_url));
            exit;
        }
        $first_name = isset($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
        $last_name = isset($_POST['last_name']) ? sanitize_text_field(wp_unslash($_POST['last_name'])) : '';
        $display_name = trim($first_name . ' ' . $last_name);
        $updated = wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => $display_name ?: wp_get_current_user()->display_name,
            'user_email' => $email,
        ));
        if (is_wp_error($updated)) {
            wp_safe_redirect(add_query_arg('idlm_account_error', 'update-failed', $return_url));
            exit;
        }
        update_user_meta($user_id, 'idlm_phone', isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '');
        if (!empty($_POST['send_reset_link'])) {
            retrieve_password(wp_get_current_user()->user_login);
            wp_safe_redirect(add_query_arg('idlm_account_notice', 'reset-sent', $return_url));
            exit;
        }
        if ('' !== $password) {
            wp_set_password($password, $user_id);
            wp_set_auth_cookie($user_id, true);
        }
        wp_safe_redirect(add_query_arg('idlm_account_notice', 'updated', $return_url));
        exit;
    }
}
