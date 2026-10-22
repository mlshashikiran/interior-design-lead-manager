<?php
/** Environment-only service configuration. Builder: Shashi. */
declare(strict_types=1);
return array(
    'app_id'=>(string)getenv('IDLM_FB_APP_ID'),
    'app_secret'=>(string)getenv('IDLM_FB_APP_SECRET'),
    'config_id'=>(string)getenv('IDLM_FB_LOGIN_CONFIG_ID'),
    'verify_token'=>(string)getenv('IDLM_FB_VERIFY_TOKEN'),
    'base_url'=>(string)getenv('IDLM_FB_BASE_URL'),
    'database'=>(string)getenv('IDLM_FB_DATABASE'),
    'encryption_key'=>base64_decode((string)getenv('IDLM_FB_ENCRYPTION_KEY'),true) ?: '',
);
