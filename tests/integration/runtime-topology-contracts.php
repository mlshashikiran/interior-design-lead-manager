<?php
/** Unsupported network activation must not start a migration. Builder: Shashi. */
if (!defined('ABSPATH') || strpos(DB_NAME, 'idlm_test_') !== 0) { exit(1); }
idlm_integration_test('T20-N-network-activation-denies-before-any-schema-or-data-write', static function(): void {
    $writes = array();
    $query_filter = static function($sql) use (&$writes) {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $sql)) { $writes[] = $sql; }
        return $sql;
    };
    $die_filter = static function() { return static function($message) { throw new IDLM_Integration_WP_Die((string) $message); }; };
    add_filter('query', $query_filter); add_filter('wp_die_handler', $die_filter); add_filter('wp_die_ajax_handler', $die_filter);
    try {
        try { idlm_activate_plugin(true); throw new RuntimeException('Network activation succeeded'); }
        catch (IDLM_Integration_WP_Die $error) { idlm_integration_assert_true(strpos($error->getMessage(), 'single-site') !== false); }
    } finally { remove_filter('query', $query_filter); remove_filter('wp_die_handler', $die_filter); remove_filter('wp_die_ajax_handler', $die_filter); }
    idlm_integration_assert_same(array(), $writes, 'Rejected network activation wrote data or schema');
});
