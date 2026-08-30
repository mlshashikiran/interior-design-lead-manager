# Production Security Configuration

Author / Builder: Shashi  
Release: 1.99.0  
Status: Phase 1 started; deployment approval remains pending

## MCP production policy

MCP is disabled by default when `wp_get_environment_type()` returns `production`. Enable it only after approving the exact tool grants:

```php
define('IDLM_MCP_ENABLED', true);
define('IDLM_MCP_ALLOWED_TOOLS', 'get_leads_summary,get_reps_performance');
```

PII-returning `query_leads` and `get_lead_details`, plus `run_custom_query`, are not in the production default grant set. The current key fingerprint, rotation time, and immediately revoked previous-key fingerprint are stored as non-secret metadata. Request audit events contain only outcome, tool, key fingerprint, resolved client IP, and elapsed milliseconds.

Emergency shutdown:

```php
define('IDLM_MCP_DISABLED', true);
```

Optional bounded-budget constants are `IDLM_MCP_MAX_PAYLOAD_BYTES`, `IDLM_MCP_MAX_BATCH_CALLS`, `IDLM_MCP_MAX_QUERY_ROWS`, `IDLM_MCP_MAX_QUERY_LENGTH`, and `IDLM_MCP_MAX_REQUEST_SECONDS`. Built-in production defaults are 256 KiB, 5 calls, 100 custom-query rows, 4,000 query characters, and 3 seconds between batch calls.

## Trusted proxies

Forwarded client addresses are ignored unless the direct `REMOTE_ADDR` matches an explicitly configured exact IP or CIDR. Configure only load balancers or reverse proxies controlled by the operator:

```php
define('IDLM_TRUSTED_PROXIES', '10.20.0.0/16,2001:db8:1234::/48');
```

The resolver walks `X-Forwarded-For` from the trusted edge toward the client and selects the first untrusted valid address. With no matching proxy configuration, rate limiting uses `REMOTE_ADDR` exactly.

## Lead attachments

- Floor plans: PDF, JPEG, PNG, or WebP; maximum 10 MB.
- Quote attachments: PDF, DOC, DOCX, JPEG, or PNG; maximum 10 MB.
- The handler validates field purpose, extension, detected MIME, and size before `media_handle_upload()` performs WordPress validation.
- Each attachment receives purpose, creating-user ID, and creation-time metadata. A failed URL lookup immediately deletes the newly created orphan attachment.

## Deployment checks still required

- Set `WP_ENVIRONMENT_TYPE` to `production`, keep `WP_DEBUG_DISPLAY` off, restrict log access, and confirm redaction using production-like failures.
- Move remaining Meta, Google, LinkedIn, and Interakt secrets to protected environment-backed storage and rotate all scoped credentials.
- Approve legal/business retention values and make pruning bounded, observable, and resumable.
- Add explicit backup-confirmed uninstall policy; deactivation must continue to preserve CRM data.
- Rehearse upload, MCP disable, proxy, credential rotation, webhook, backup/restore, and rollback behavior on staging.
