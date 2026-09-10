<?php
/**
 * Isolated worker for integration paths that redirect/exit or require concurrency.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $config = idlm_integration_config(true);
    idlm_integration_boot_wordpress($config, false);
    idlm_integration_load_plugin($config);

    $mode = $argv[1] ?? '';
    if ('quote-number' === $mode) {
        $method = new ReflectionMethod('IDLM_Quotations', 'next_quote_number');
        $method->setAccessible(true);
        $number = $method->invoke(null);
        if (is_wp_error($number)) {
            throw new RuntimeException($number->get_error_message());
        }
        echo wp_json_encode(array('quote_number' => $number));
        exit(0);
    }

    if ('quote-save' === $mode) {
        $lead_id = absint($argv[2] ?? 0);
        $user_id = absint($argv[3] ?? 0);
        if (!$lead_id || !$user_id) {
            throw new RuntimeException('quote-save requires a lead ID and user ID.');
        }
        wp_set_current_user($user_id);
        if (!current_user_can('idlm_create_quotes')) {
            throw new RuntimeException('Quotation worker user lacks idlm_create_quotes.');
        }
        add_filter('wp_die_handler', static function() {
            return static function($message): void {
                throw new RuntimeException('wp_die: ' . wp_strip_all_tags((string) $message));
            };
        });
        add_filter('wp_redirect', static function($location) {
            fwrite(STDERR, 'REDIRECT ' . (string) $location . "\n");
            return $location;
        });
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array(
            '_wpnonce' => wp_create_nonce('idlm_save_quote'),
            'quote_id' => 0,
            'revision_id' => 0,
            'lead_id' => $lead_id,
            'title' => 'Integration Proposal',
            'save_request_key' => '12345678-1234-4123-8123-123456789abc',
            'currency_code' => 'INR',
            'wooden_discount_percent' => '0',
            'additional_discount_amount' => '0',
            'sections' => array(
                array('key' => 'general', 'section_name' => 'General'),
            ),
            'items' => array(
                array(
                    'section_key' => 'general',
                    'description' => 'Interior design services',
                    'quantity' => '2',
                    'unit_rate' => '1000',
                ),
            ),
        );
        $_REQUEST = $_POST;
        IDLM_Quotations::handle_save();
        throw new RuntimeException('Quotation save returned without redirecting.');
    }

    throw new RuntimeException('Unknown integration worker mode.');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
