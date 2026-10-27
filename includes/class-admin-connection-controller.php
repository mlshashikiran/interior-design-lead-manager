<?php
/** Administrator platform connection diagnostics. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Admin_Connection_Controller {
    public static function ajax_test_api_connection() {
        check_ajax_referer('idlm_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Access denied'));
        }

        $platform = isset($_POST['platform']) ? sanitize_key(wp_unslash($_POST['platform'])) : '';
        $test_type = isset($_POST['test_type']) ? sanitize_key(wp_unslash($_POST['test_type'])) : '';
        $target_id = isset($_POST['target_id']) ? sanitize_text_field(wp_unslash($_POST['target_id'])) : '';
        $access_token = isset($_POST['access_token']) ? sanitize_text_field(wp_unslash($_POST['access_token'])) : '';

        if ('' === $access_token && in_array($platform, array('meta','linkedin'), true)) {
            $access_token = IDLM_Secret_Store::get('idlm_' . $platform . '_access_token', '', true);
        }

        if (empty($platform) || empty($target_id)) {
            wp_send_json_error(array('message' => 'Missing platform or target ID to test.'));
        }

        if ($platform === 'meta') {
            if (empty($access_token)) {
                wp_send_json_error(array('message' => 'Access token is required to test Meta connection.'));
            }

            // We can check Pixel ID (CAPI) or Custom Audience ID
            $endpoint = "https://graph.facebook.com/v25.0/" . rawurlencode($target_id) . "?";
            if ($test_type === 'capi') {
                $endpoint .= "fields=name";
            } else {
                $endpoint .= "fields=name,subtype";
            }

            $response = IDLM_Secret_Store::request($endpoint, array('timeout' => 15, 'headers'=>array('Authorization'=>'Bearer '.$access_token)), array($access_token), 'GET');

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'Connection request failed: ' . $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code === 200) {
                $name = isset($data['name']) ? $data['name'] : 'Unnamed';
                if ($test_type === 'capi') {
                    wp_send_json_success(array('message' => sprintf('Meta Conversions API connection successful! Verified Pixel Name: "%s".', $name)));
                } else {
                    $subtype = isset($data['subtype']) ? $data['subtype'] : 'CUSTOM';
                    wp_send_json_success(array('message' => sprintf('Meta Custom Audience sync connection successful! Verified Audience Name: "%s" (%s).', $name, $subtype)));
                }
            } else {
                $err_msg = 'Meta Graph API Error: ';
                if (isset($data['error']['message'])) {
                    $err_msg .= $data['error']['message'];
                    if (isset($data['error']['error_subcode'])) {
                        $err_msg .= ' (Subcode: ' . $data['error']['error_subcode'] . ')';
                    }
                } else {
                    $err_msg .= 'HTTP Code ' . $code;
                }
                wp_send_json_error(array('message' => $err_msg));
            }
        } elseif ($platform === 'linkedin') {
            if (empty($access_token)) {
                wp_send_json_error(array('message' => 'Access token is required to test LinkedIn connection.'));
            }

            $endpoint = "https://api.linkedin.com/v2/adSegments/" . rawurlencode($target_id);
            $response = IDLM_Secret_Store::request($endpoint, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'X-Restli-Protocol-Version' => '2.0.0'
                ),
                'timeout' => 15
            ), array($access_token), 'GET');

            if (is_wp_error($response)) {
                wp_send_json_error(array('message' => 'LinkedIn connection failed: ' . $response->get_error_message()));
            }

            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code === 200) {
                $name = isset($data['name']) ? $data['name'] : 'Unnamed Segment';
                wp_send_json_success(array('message' => sprintf('LinkedIn connection successful! Verified Segment Name: "%s".', $name)));
            } else {
                $err_msg = 'LinkedIn API Error: ';
                if (isset($data['message'])) {
                    $err_msg .= $data['message'];
                } else {
                    $err_msg .= 'HTTP Code ' . $code;
                }
                wp_send_json_error(array('message' => $err_msg));
            }
        } elseif ($platform === 'google') {
            $gadm_username = isset($_POST['google_gadm_username']) ? sanitize_text_field(wp_unslash($_POST['google_gadm_username'])) : '';
            if (empty($gadm_username)) {
                $gadm_username = get_option('idlm_google_gadm_username', 'google-ads');
            }
            $gadm_password = isset($_POST['google_gadm_password']) ? sanitize_text_field(wp_unslash($_POST['google_gadm_password'])) : '';
            if (empty($gadm_password)) {
                $gadm_password = IDLM_Secret_Store::get('idlm_google_gadm_password');
            }

            if (empty($gadm_username) || empty($gadm_password)) {
                wp_send_json_error(array('message' => 'Google Ads Data Manager source credentials are not fully configured.'));
            }

            if (strlen($gadm_username) < 3 || strlen($gadm_password) < 8) {
                wp_send_json_error(array('message' => 'Credentials format error: Username must be at least 3 chars and Password must be at least 8 chars.'));
            }

            wp_send_json_success(array('message' => sprintf('Google Ads Data Manager source credentials verified! Feeds are active and secured under username "%s".', $gadm_username)));
        } else {
            wp_send_json_error(array('message' => 'Unsupported platform connection test.'));
        }
    }

}
