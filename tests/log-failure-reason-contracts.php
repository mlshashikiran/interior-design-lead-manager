<?php
/** Log rows say in plain words why an attempt failed (STANDARD.md known gap 3). Builder: Shashi. */

idlm_test('Log failure reasons map known Meta, Google and LinkedIn errors to what to fix', static function(): void {
    $reason = static function(string $platform, $response): string { return IDLM_Admin_Logs_Renderer::failure_reason($platform, $response); };
    $expired = '{"error":{"message":"Error validating access token: Session has expired on Wednesday, 10-Jun-26 07:00:00 PDT.","type":"OAuthException","code":190,"error_subcode":463,"fbtrace_id":"synthetic-trace"}}';
    assert_contains('Reconnect Meta in Settings', $reason('meta', $expired));
    assert_contains('Reconnect Meta in Settings', $reason('meta', '{"error":{"message":"The session is invalid because the user logged out.","type":"OAuthException","code":190,"error_subcode":467}}'));
    assert_contains('Check it in Settings', $reason('meta', '{"error":{"message":"Unsupported post request. Object with ID \'120248073253620469\' does not exist. Please read the Graph API documentation at https:\/\/developers.facebook.com\/docs\/graph-api","type":"GraphMethodException","code":100,"error_subcode":33}}'));
    assert_same('Value-based audience needs a deal value on every lead.', $reason('meta', '{"error":{"message":"(#100) Value-Based Custom Audience requires LOOKALIKE_VALUE attribute.","type":"OAuthException","code":100}}'));
    assert_contains('permission', $reason('meta', '{"error":{"message":"(#200) Missing permissions","type":"OAuthException","code":200}}'));
    assert_contains('rate limit', $reason('meta', '{"error":{"code":17,"message":"User request limit reached"}}'));
    assert_same('Meta rejected 3 of 80 uploaded entries.', $reason('meta', '{"audience_id":"1","num_received":80,"num_invalid_entries":3}'));
    assert_same('Missing user_data parameter.', $reason('meta', '{"error":{"message":"(#100) missing user_data parameter.","type":"OAuthException","code":100}}'), 'An unknown Meta error falls back to its own message without the (#code) prefix.');
    assert_contains('Reconnect Google', $reason('google', '{"error":{"code":401,"message":"Request had invalid authentication credentials.","status":"UNAUTHENTICATED"}}'));
    assert_same('Google rejected the request (HTTP 400). Check the measurement ID and API secret.', $reason('google', 'Success Response (Code 400)'));
    assert_same('', $reason('google', 'Success Response (Code 204)'));
    assert_contains('access token', $reason('linkedin', '{"status":401,"serviceErrorCode":65600,"code":"INVALID_ACCESS_TOKEN","message":"Invalid access token"}'));
    assert_same('The integration is missing an ID or credential. Complete it in Settings.', $reason('meta', 'Missing integration destination or credentials.'));
    assert_same('The integration is missing an ID or credential. Complete it in Settings.', $reason('linkedin', 'Credentials missing.'));
    assert_contains('could not reach the provider', $reason('meta', 'cURL error 6: Could not resolve host: graph.facebook.com'));
    assert_contains('Reconnect Meta', $reason('meta', serialize(array('error' => array('code' => 190, 'message' => 'x')))), 'A serialized stored response is decoded first.');
});

idlm_test('Log failure reasons never show secrets, contact details, hashes or links, and stay short', static function(): void {
    $raw = 'Bad request for jane.doe@example.com phone +91 98765 43210 token EAABsbCS1iHgBAKZBZCsynthetic123456 hash ' . str_repeat('ab12', 16) . ' at https://graph.facebook.com/v25.0/1/events?access_token=supersecret Authorization: Bearer abc.def <script>alert(1)</script>';
    $shown = IDLM_Admin_Logs_Renderer::failure_reason('meta', $raw);
    foreach (array('jane.doe', '98765', 'EAABsbCS', 'ab12ab12', 'supersecret', 'abc.def', 'https://', '<script>') as $leak) {
        assert_false(false !== strpos($shown, $leak), 'Leaked ' . $leak . ' in: ' . $shown);
    }
    $json = IDLM_Admin_Logs_Renderer::failure_reason('meta', '{"error":{"message":"Invalid user jane.doe@example.com with access_token=EAABsbCS1iHgBAKZBZCsynthetic123456","code":9999}}');
    assert_false(false !== strpos($json, 'jane.doe') || false !== strpos($json, 'EAAB'), 'JSON messages are scrubbed too: ' . $json);
    $long = IDLM_Admin_Logs_Renderer::failure_reason('meta', str_repeat('Something went wrong. ', 40));
    assert_true(mb_strlen($long, 'UTF-8') <= IDLM_Admin_Logs_Renderer::REASON_LENGTH, 'Reasons are trimmed for the row.');
    foreach (array('{"error":{"code":190,"type":"OAuthException","message":"x"}}', '{"error":{"code":100,"error_subcode":33,"type":"GraphMethodException","message":"x"}}', '{"error":{"code":100,"type":"OAuthException","message":"requires LOOKALIKE_VALUE"}}') as $known) {
        assert_true(mb_strlen(IDLM_Admin_Logs_Renderer::failure_reason('meta', $known), 'UTF-8') <= 70, 'A mapped reason fits one line of the Status column.');
    }
    assert_contains('Pixel 1234567890123456 rejected', IDLM_Admin_Logs_Renderer::failure_reason('meta', 'Pixel 1234567890123456 rejected'), 'Long provider IDs are not mistaken for phone numbers.');
    // The template escapes the reason at output.
    foreach (array('capi.php', 'audience.php') as $file) {
        $template = (string) file_get_contents(dirname(__DIR__) . '/templates/admin/logs/' . $file);
        assert_contains("esc_html(\$idlm_outcome['message'])", $template);
        assert_contains('idlm-log-status', $template, 'Badge and reason share one wrapper so the phone card keeps them together.');
        assert_contains("esc_attr(\$idlm_outcome['tone'])", $template, 'Status badges use the STD-CMP-3 tone modifiers.');
    }
});

idlm_test('Log failure reasons leave empty and succeeded rows unchanged', static function(): void {
    assert_same('', IDLM_Admin_Logs_Renderer::failure_reason('meta', ''));
    assert_same('', IDLM_Admin_Logs_Renderer::failure_reason('meta', '   '));
    assert_same('', IDLM_Admin_Logs_Renderer::failure_reason('meta', '{"events_received":1,"fbtrace_id":"synthetic-trace"}'));
    $row = static function(string $status, string $response) { return (object) array('platform' => 'meta', 'status' => $status, 'payload' => serialize(array('_idlm_delivery' => array('outcome' => $status, 'attempted' => true))), 'response' => $response); };
    $ok = IDLM_Admin_Logs_Renderer::capi_outcome($row('success', '{"events_received":1,"fbtrace_id":"synthetic-trace"}'));
    assert_same(array('', 'is-success'), array($ok['message'], $ok['tone']));
    $failed = IDLM_Admin_Logs_Renderer::capi_outcome($row('failed', '{"error":{"code":190,"error_subcode":467,"type":"OAuthException","message":"x"}}'));
    assert_same('is-danger', $failed['tone']); assert_contains('Reconnect Meta', $failed['message']);
    $renderer = (string) file_get_contents(dirname(__DIR__) . '/includes/class-admin-logs-renderer.php');
    assert_contains("CASE WHEN status = 'success' THEN '' ELSE response END AS response", $renderer, 'Audience rows read the response only when they failed.');
    assert_contains('unset($log->response);', $renderer, 'The audience response is not passed to the view.');
});
