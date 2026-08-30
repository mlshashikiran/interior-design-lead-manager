<?php
/**
 * Encrypted storage for scoped integration credentials.
 *
 * @package InteriorDesignLeadManager
 * @author Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Secret_Store {
    const PREFIX = 'idlm:v1:';

    private static $environment_constants = array(
        'idlm_webhook_api_key'       => 'IDLM_WEBHOOK_API_KEY',
        'idlm_mcp_api_key'           => 'IDLM_MCP_API_KEY',
        'idlm_google_gadm_password'  => 'IDLM_GOOGLE_GADM_PASSWORD',
    );

    /** Read a secret, transparently encrypting an existing plaintext option. */
    public static function get($option_name, $default = '') {
        $option_name = sanitize_key($option_name);
        $constant = self::$environment_constants[$option_name] ?? '';
        if ('' !== $constant && defined($constant)) {
            return trim((string) constant($constant));
        }

        $stored = (string) get_option($option_name, '');
        if ('' === $stored) {
            return $default;
        }
        if (0 !== strpos($stored, self::PREFIX)) {
            self::set($option_name, $stored);
            return $stored;
        }

        $plaintext = self::decrypt(substr($stored, strlen(self::PREFIX)));
        return false === $plaintext ? $default : $plaintext;
    }

    /** Encrypt and persist a secret; environment-owned secrets are read-only. */
    public static function set($option_name, $plaintext) {
        $option_name = sanitize_key($option_name);
        $constant = self::$environment_constants[$option_name] ?? '';
        if ('' !== $constant && defined($constant)) {
            return false;
        }
        $encrypted = self::encrypt((string) $plaintext);
        return false !== $encrypted && update_option($option_name, self::PREFIX . $encrypted, false);
    }

    public static function is_environment_managed($option_name) {
        $constant = self::$environment_constants[sanitize_key($option_name)] ?? '';
        return '' !== $constant && defined($constant);
    }

    private static function key() {
        if (defined('IDLM_SECRET_KEY') && '' !== (string) IDLM_SECRET_KEY) {
            return hash('sha256', (string) IDLM_SECRET_KEY, true);
        }
        $material = '';
        foreach (array('AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY') as $constant) {
            if (defined($constant)) {
                $material .= (string) constant($constant);
            }
        }
        if (function_exists('wp_salt')) {
            $material .= (string) wp_salt('auth');
        }
        if ('' === $material) {
            $material = defined('IDLM_PATH') ? IDLM_PATH : __FILE__;
        }
        return hash('sha256', 'idlm-secret-store|' . $material, true);
    }

    private static function encrypt($plaintext) {
        if (!function_exists('openssl_encrypt')) {
            return false;
        }
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if (false === $ciphertext) {
            return false;
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    private static function decrypt($payload) {
        if (!function_exists('openssl_decrypt')) {
            return false;
        }
        $decoded = base64_decode($payload, true);
        if (false === $decoded || strlen($decoded) < 29) {
            return false;
        }
        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, 16);
        return openssl_decrypt(substr($decoded, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
    }
}
