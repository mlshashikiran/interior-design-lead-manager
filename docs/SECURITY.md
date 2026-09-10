# Security Model

## Roles and capabilities

Activation/update reconciliation creates `idlm_pre_sales` and adds CRM capabilities to recognized WordPress roles by slug or normalized display name. This dynamic role-name matching is compatibility behavior.

- General CRM: `idlm_access_leads`, `idlm_qualify_leads`.
- Assignment managers: `idlm_assign_leads` (administrator and recognized manager roles).
- Quotes: `idlm_view_quotes`, `idlm_create_quotes`, `idlm_edit_quote_drafts`, `idlm_submit_quotes`, `idlm_mark_quotes_shared`, `idlm_accept_reject_quotes`, `idlm_create_quote_revisions`, `idlm_download_quote_pdf`.
- Quote managers: `idlm_approve_quotes`, `idlm_cancel_quotes`, `idlm_manage_quote_settings`, `idlm_manage_quote_items`.
- `manage_options` gates settings, diagnostics, imports, resets/deletes, routing controls, CAPI/audience logs, and several exports.
- `manage_options` exclusively gates Users & Access. Its user and role-scope handlers use action-specific nonces, an allowlist of managed CRM roles/scopes, duplicate account validation, and protections against self-lockout and removal of the final administrator.

Administrators can configure `All leads` or `Assigned leads only` for every managed CRM role. The saved `idlm_role_lead_scopes` value overrides the legacy `idlm_reps_see_all_leads` default for that role; administrators remain unrestricted, and assigned-only wins when a user has multiple roles. Pre-sales users still cannot assign leads or author/progress quotations. Mutating handlers use `current_user_can_access_lead()`, so read-all scope never grants non-managers permission to mutate another owner's lead.

Users marked with the `idlm_crm_access_disabled` user meta retain their WordPress account and historical lead relationships, but cannot obtain CRM representative or quotation access.

Quotation capability checks and object-level lead access are centralized in `IDLM_Quotation_Policy`. Configured role scope is applied after the required quotation capability check; without an explicit role override, assignment managers retain unrestricted scope and other quotation users retain assigned-lead scope. `IDLM_Quotation_Lead_Read_Model` applies that scope to lead existence, search, editor choices, and selected-lead prefilling.

## Request protection

Release 1.101.63 separates read-all visibility from mutation authority. Mutations require an existing lead plus current ownership or manager capability, including comments and uploads. Pre-sales drawer saves cannot alter qualification or commercial fields; qualification decisions continue through their dedicated controls. Uploaded attachments record `_idlm_attachment_lead_id`, and drawer attachment binding rejects a different lead's newly bound files. Legacy URL-only attachments remain compatible.

- Frontend CRM authentication uses plugin-scoped anonymous admin-post handlers. Sign-in accepts an email field only, resolves it to the internal WordPress login, and delegates authentication/cookies to `wp_signon()` without modifying global WordPress username/email authentication. Password recovery keeps responses generic, throttles normalized email hashes briefly, and delegates reset-key generation, validation hooks, password replacement, and session invalidation to WordPress core.
- CRM AJAX uses the `idlm_admin_nonce`; quotation AJAX uses dedicated search/autosave nonces. Every registered AJAX action is authenticated (`wp_ajax_`, no `nopriv`).
- Admin-post forms use action-specific nonces and capabilities.
- Backup export and restore require `manage_options` and dedicated action nonces. Restore accepts only an uploaded, versioned IDLM JSON file smaller than 100 MB and requires an exact `RESTORE` confirmation. It runs as one InnoDB transaction; API credentials, WordPress users, Media Library files, and scheduled jobs are not restored, and queued outbound jobs are cleared after a successful restore.
- The lead webhook, Google feed, and MCP routes use dedicated `permission_callback` methods, scoped credentials, constant-time comparisons, and fixed-window per-client rate limits. Forwarded addresses are accepted only when `REMOTE_ADDR` belongs to an explicitly configured trusted proxy. Handlers repeat credential checks as defense in depth. Interakt/Meta webhook POSTs validate SHA-256 HMAC; Meta verification GET compares the configured token.
- Scoped webhook and MCP credentials can be rotated independently by administrators, are encrypted at rest, may be supplied through environment constants, and are accepted only in headers. The former shared/query credential is deleted during migration.
- `rest_authentication_errors` is bypassed only for the external webhook/feed/MCP paths so their route permission callbacks can authenticate independently of WordPress cookie authentication.
- Lead-list keyset cursors are HMAC-signed with the WordPress nonce salt, expire after two hours, and are bound to the current user, authorization scope, normalized filters, page size, and stable sort contract. A cursor is navigation state, not authorization; the AJAX nonce/capability/visibility checks still run on every request.

## Data handling

Input generally uses WordPress sanitizers (`sanitize_text_field`, `sanitize_textarea_field`, `sanitize_email`, `absint`, `sanitize_key`, `esc_url_raw`) followed by allowed-value/domain checks. SQL with input generally uses `$wpdb->prepare()` or typed `$wpdb` CRUD calls. Dynamic allowlisted fragments occur in reporting/filter queries and must remain allowlisted. HTML rendering uses contextual escaping; configured quotation HTML is filtered through `wp_kses_post()`.

File uploads use `media_handle_upload()` and create attachments. Catalogue CSV import adds explicit extension, MIME, size, header, field, row-count, formula/export, and transactional checks. Lead uploads now enforce field-specific purpose, extension, detected MIME, and 10 MB size policies before WordPress media validation, then record purpose/creator/time ownership metadata.

Sensitive data includes lead contact/property/budget/deal data, call recording URLs, activity messages, CAPI/audience payloads, stack traces, and advertising/API credentials. New raw integration payloads redact common credential/contact fields, and configurable daily retention prunes touchpoint, CAPI, audience, and telemetry rows. Scoped webhook/MCP/Google-feed secrets use authenticated encryption or environment constants; unrelated platform tokens retain their existing option storage.

## Phase 1 production gates

MCP is fail-closed in production, has per-tool grants, bounded payload/batch/query budgets, rotation/revocation fingerprints, aggregate audit attribution, and an emergency disable constant. Production deployment must still approve the enabled tool set and rotate the credential. Exact constants and proxy/upload behavior are documented in [PRODUCTION-SECURITY-CONFIG.md](PRODUCTION-SECURITY-CONFIG.md).

Remaining gates are production debug/log verification, migration and rotation of remaining platform secrets, bounded/resumable retention, backup-confirmed uninstall policy, and staging rehearsal.

## Existing concerns

1. **Low — compatibility renderer review.** Shared access, workspace, and account views use guarded templates and the workspace boot script is asset-based. Large legacy compatibility renderers retain contextual markup; keep edits narrow and preserve escape-at-output review.
2. **Low — MCP host trust.** `mcp-server.php` derives WordPress from the plugin location, writes its default error log to the system temporary directory, and accepts explicit `IDLM_MCP_WP_LOAD` / `IDLM_MCP_ERROR_LOG` environment overrides. Operators must protect those environment values and the local stdio process boundary.

Do not fix these as incidental changes. Any further remediation needs compatibility and endpoint tests.
