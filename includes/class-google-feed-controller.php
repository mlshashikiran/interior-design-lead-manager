<?php
/** Focused M6 responsibility owner. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Google_Feed_Controller {

    public static function add_google_feed_auth_challenge($response, $server, $request) {
        if (
            $response instanceof WP_REST_Response &&
            method_exists($request, 'get_route') &&
            401 === $response->get_status() &&
            0 === strpos((string) $request->get_route(), '/interior-design-lead-manager/v1/google-ads-')
        ) {
            $response->header('WWW-Authenticate', 'Basic realm="Google Ads Data Manager Feed"');
        }
        return $response;
    }

    public static function send_gadm_401() {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="Google Ads Data Manager Feed"');
        header('Content-Type: application/json; charset=utf-8');
        // Remove WordPress REST API headers that confuse GADM
        header_remove('X-Robots-Tag');
        header_remove('Link');
        header_remove('X-Content-Type-Options');
        header_remove('Access-Control-Expose-Headers');
        header_remove('Access-Control-Allow-Headers');
        echo wp_json_encode(array('success' => false, 'message' => 'Unauthorized: Invalid Credentials'));
        if (!defined('IDLM_TESTING')) {
            exit;
        }
    }

    public static function clean_rest_headers_for_gadm() {
        header_remove('X-Robots-Tag');
        header_remove('Link');
        header_remove('X-Content-Type-Options');
        header_remove('Access-Control-Expose-Headers');
        header_remove('Access-Control-Allow-Headers');
    }

    public static function handle_google_ads_conversions_feed(WP_REST_Request $request) {
        if (!self::validate_gadm_request($request)) {
            self::send_gadm_401();
            return;
        }

        self::clean_rest_headers_for_gadm();

        // Buffer the CSV so we can set Content-Length (makes it look like a static file to GADM)
        ob_start();
        if (class_exists('IDLM_CAPI')) {
            IDLM_CAPI::generate_google_ads_conversions_csv_response(false);
        }
        $csv_content = ob_get_clean();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=google-ads-offline-conversions-' . gmdate('Y-m-d') . '.csv');
        header('Content-Length: ' . strlen($csv_content));
        header('Cache-Control: no-cache, must-revalidate');
        echo $csv_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if (!defined('IDLM_TESTING')) {
            exit;
        }
    }

    public static function handle_google_ads_audience_feed(WP_REST_Request $request) {
        if (!headers_sent()) {
            header('Cache-Control: private, no-store, max-age=0');
        }
        if (!self::validate_gadm_request($request)) {
            self::send_gadm_401();
            return;
        }

        $list_id = $request->get_param('list_id');
        if (!is_string($list_id) || trim($list_id) === '') {
            return new WP_REST_Response(array('success' => false, 'message' => 'Missing list_id parameter'), 400);
        }

        $list_id = trim($list_id);

        if (!class_exists('IDLM_Audiences')) {
            require_once dirname(__FILE__) . '/class-audiences.php';
        }

        $matched_rule = IDLM_Audiences::resolve_google_feed_rule($list_id);

        if (!$matched_rule) {
            return new WP_REST_Response(array('success' => false, 'message' => 'No active Google Ads audience rule found matching target ID: ' . esc_html($list_id)), 404);
        }

        self::clean_rest_headers_for_gadm();

        // Buffer the CSV so we can set Content-Length
        ob_start();
        $leads = IDLM_Audiences::get_leads_matching_rule($matched_rule);
        IDLM_Audiences::generate_google_ads_audience_csv_response($leads, $list_id, false);
        $csv_content = ob_get_clean();

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . sanitize_file_name($list_id) . '-' . gmdate('Y-m-d') . '.csv');
        header('Content-Length: ' . strlen($csv_content));
        echo $csv_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        if (!defined('IDLM_TESTING')) {
            exit;
        }
    }
    private static function validate_gadm_request(WP_REST_Request $request) { return IDLM_Integration_Auth::validate_gadm_request($request); }

}
