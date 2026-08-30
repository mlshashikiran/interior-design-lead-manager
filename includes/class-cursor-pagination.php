<?php
/**
 * Signed cursor tokens for bounded keyset pagination.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

class IDLM_Cursor_Pagination {
    const VERSION = 1;
    const DEFAULT_TTL = 7200;
    const MAX_TOKEN_LENGTH = 2048;

    /**
     * Create a signed cursor bound to the current user and request scope.
     *
     * @param string $context Cursor family identifier.
     * @param array  $position Stable sort tuple.
     * @param array  $scope Filter, sort, and authorization scope.
     * @param int    $ttl Token lifetime in seconds.
     * @return string
     */
    public static function encode($context, array $position, array $scope, $ttl = self::DEFAULT_TTL) {
        $payload = array(
            'v' => self::VERSION,
            'c' => sanitize_key($context),
            'u' => get_current_user_id(),
            's' => self::scope_hash($scope),
            'p' => $position,
            'e' => time() + max(1, intval($ttl)),
        );
        $encoded = self::base64url_encode(wp_json_encode($payload));
        $signature = self::base64url_encode(hash_hmac('sha256', $encoded, wp_salt('nonce'), true));

        return $encoded . '.' . $signature;
    }

    /**
     * Validate and decode a cursor for the current request.
     *
     * @param string $token Signed cursor token.
     * @param string $context Expected cursor family.
     * @param array  $scope Expected filter, sort, and authorization scope.
     * @return array|WP_Error
     */
    public static function decode($token, $context, array $scope) {
        $token = (string) $token;
        if ('' === $token || strlen($token) > self::MAX_TOKEN_LENGTH || 1 !== substr_count($token, '.')) {
            return new WP_Error('idlm_invalid_cursor', 'The pagination cursor is invalid. Refresh the lead list and try again.');
        }

        list($encoded, $provided_signature) = explode('.', $token, 2);
        $expected_signature = self::base64url_encode(hash_hmac('sha256', $encoded, wp_salt('nonce'), true));
        if ('' === $provided_signature || !hash_equals($expected_signature, $provided_signature)) {
            return new WP_Error('idlm_invalid_cursor', 'The pagination cursor is invalid. Refresh the lead list and try again.');
        }

        $json = self::base64url_decode($encoded);
        $payload = false === $json ? null : json_decode($json, true);
        if (!is_array($payload)
            || self::VERSION !== intval($payload['v'] ?? 0)
            || sanitize_key($context) !== ($payload['c'] ?? '')
            || get_current_user_id() !== intval($payload['u'] ?? 0)
            || empty($payload['e'])
            || time() > intval($payload['e'])
            || empty($payload['s'])
            || !hash_equals(self::scope_hash($scope), (string) $payload['s'])
            || !isset($payload['p'])
            || !is_array($payload['p'])
        ) {
            return new WP_Error('idlm_invalid_cursor', 'The pagination cursor has expired or no longer matches this lead list. Refresh and try again.');
        }

        return $payload['p'];
    }

    private static function scope_hash(array $scope) {
        return hash('sha256', wp_json_encode(self::normalize($scope)));
    }

    private static function normalize($value) {
        if (!is_array($value)) {
            return $value;
        }

        $normalized = array();
        foreach ($value as $key => $item) {
            $normalized[$key] = self::normalize($item);
        }
        if (!empty($normalized) && array_keys($normalized) !== range(0, count($normalized) - 1)) {
            ksort($normalized);
        }

        return $normalized;
    }

    private static function base64url_encode($value) {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }

    private static function base64url_decode($value) {
        $value = (string) $value;
        if ('' === $value || preg_match('/[^A-Za-z0-9\-_]/', $value)) {
            return false;
        }
        $padding = strlen($value) % 4;
        if ($padding) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
