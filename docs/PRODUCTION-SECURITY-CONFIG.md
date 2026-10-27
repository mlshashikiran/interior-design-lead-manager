# Production Security Configuration

Author / Builder: Shashi  
Release: 1.102.71
Status: Local implementation; production qualification/deployment remain pending

## MCP production policy

MCP is disabled by default when `wp_get_environment_type()` returns `production`. Enable it only after approving the exact tool grants:

```php
define('IDLM_MCP_ENABLED', true);
define('IDLM_MCP_ALLOWED_TOOLS', 'get_leads_summary,get_reps_performance');
```

PII-returning `query_leads` and `get_lead_details`, plus `run_custom_query`, are not in the production default grant set. The current key fingerprint, rotation time, and immediately revoked previous-key fingerprint are stored as non-secret metadata. Request audit events contain only outcome, tool, key fingerprint, resolved client IP, and elapsed milliseconds.

PII is separately denied by default in every environment. Grant it only to the intended trusted MCP principal (tenant-wide, independent of cookie/CRM role scope):

```php
define('IDLM_MCP_ALLOW_PII', true); // Also grant query_leads/get_lead_details as needed.
```

When the constant is absent, trusted server configuration may set `idlm_mcp_allow_pii` to `'1'`; no frontend control grants it. The flag also enables only the enumerated custom contact columns. Authentication columns, raw log text and private-file URLs stay denied. `wp_users` supplies only CRM-associated ID/display_name. `run_custom_query` retains its SQL argument with a deliberately restricted SELECT grammar; see [grammar, approved fields, migration impact and verified bounds](AUDIT-REMEDIATION-MCP-SLICE.md). Keep custom SQL disabled on database platforms whose statement-time bound is unverified.

Emergency shutdown:

```php
define('IDLM_MCP_DISABLED', true);
```

Optional bounded-budget constants are `IDLM_MCP_MAX_PAYLOAD_BYTES`, `IDLM_MCP_MAX_BATCH_CALLS`, `IDLM_MCP_MAX_QUERY_ROWS`, `IDLM_MCP_MAX_QUERY_LENGTH`, and `IDLM_MCP_MAX_REQUEST_SECONDS`. Built-in production defaults are 256 KiB, 5 calls, 100 custom-query rows, 4,000 query characters, and 3 seconds between batch calls. Custom SQL additionally receives the MySQL statement-time hint using that budget; network/queue time and individual typed-tool SQL remain separate.

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
- Qualify the platform-secret migration, rehearse key recovery and rotate credentials exposed in historic database/AS exports or traces. Protected server-owned constants take precedence over encrypted options.
- Approve legal/business retention values and make pruning bounded, observable, and resumable.
- Add explicit backup-confirmed uninstall policy; deactivation must continue to preserve CRM data.
- Rehearse upload, MCP disable, proxy, credential rotation, webhook, backup/restore, and rollback behavior on staging.

## Platform credentials — INT-010 (Shashi)

All seven platform options now migrate to nonautoload AES-256-GCM ciphertext with verified writes and primary credential reads. Settings stay blank; blank retains, a new value replaces, and the clear control explicitly revokes. Environment-managed values are read-only. [Option/constant mapping, executed failures and qualification](AUDIT-REMEDIATION-SECRET-STORE-SLICE.md).

Provide `IDLM_SECRET_KEY` through protected server configuration, or retain the original WordPress keys/salts. PHP must support OpenSSL AES-256-GCM and secure randomness. Missing encryption or key material refuses storage/migration; damaged ciphertext remains preserved and unusable. Changing key material does not re-encrypt existing ciphertext: retain the original key securely and replace credentials under the new configuration, or restore matching original material. Adding `IDLM_SECRET_KEY` to an already salt-encrypted installation also requires this transition.

Meta outbound tokens use Bearer headers. GA4 still requires its encoded secret in the provider URL, and Meta verification receives a query token. Covered plugin diagnostics scrub echoes and credential fields, but external HTTP hooks, proxy/database tracing and existing exports must be restricted/redacted independently. Bootstrap queue cleanup advances in batches of at most 100 legacy events/AS rows; inspect backlog and retry failures before claiming historical cleanup. Rehearse the installed Action Scheduler schema and restore/key pairing on staging.
