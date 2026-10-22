# Security Model

## Roles and capabilities

Initial setup creates `idlm_pre_sales` and provisions recognized dedicated CRM roles by slug or normalized display name once. Effective `idlm_access_leads` (or administrator `manage_options`) and no CRM suspension are required for customer access; role membership alone never grants admission. Version updates preserve removed capabilities. Ordinary WordPress editorial roles receive no automatic customer access. See [the approved migration, role impact and evidence](AUDIT-REMEDIATION-READ-SLICE.md).

- General CRM: `idlm_access_leads`, `idlm_qualify_leads`.
- Assignment managers: `idlm_assign_leads` (administrator and recognized manager roles).
- Quotes: `idlm_view_quotes`, `idlm_create_quotes`, `idlm_edit_quote_drafts`, `idlm_submit_quotes`, `idlm_mark_quotes_shared`, `idlm_accept_reject_quotes`, `idlm_create_quote_revisions`, `idlm_download_quote_pdf`.
- Quote managers: `idlm_approve_quotes`, `idlm_cancel_quotes`, `idlm_manage_quote_settings`, `idlm_manage_quote_items`. Explicit catalogue-only grants remain usable without customer CRM access; editorial catalogue grants are no longer automatic.
- `manage_options` gates settings, diagnostics, imports, resets/deletes, routing controls, CAPI/audience logs, and several exports.
- `manage_options` exclusively gates Users & Access. Its user and role-scope handlers use action-specific nonces, an allowlist of managed CRM roles/scopes, duplicate account validation, and protections against self-lockout and removal of the final administrator.

Administrators can configure `All leads` or `Assigned leads only` for every managed CRM role. The saved `idlm_role_lead_scopes` value overrides the legacy `idlm_reps_see_all_leads` default for that role; administrators remain unrestricted, and assigned-only wins when a user has multiple roles. Pre-sales quotation exclusion remains effective, including mixed roles; the pre-sales role has no assignment grant. Explicit additional manager grants keep their existing non-quotation authority. Mutating handlers use `current_user_can_access_lead()`, so read-all scope never grants non-managers permission to mutate another owner's lead.

In the local role-permissions UI, administrators can select the allowlisted CRM capabilities for dedicated managed roles. The action requires `manage_options` and a nonce; it never edits administrator or shared editorial roles, and pre-sales choices are limited to CRM access and qualification. These are role grants, not a complete effective-access report: direct user grants, other roles, suspension, ownership and pre-sales policy continue to apply. Role option changes invalidate cached reads. Builder: Shashi.

Users marked with the `idlm_crm_access_disabled` user meta retain their WordPress account and historical lead relationships, but cannot obtain CRM representative or quotation access.

Quotation capability checks and object-level lead access are centralized in `IDLM_Quotation_Policy`. Since 1.102.43, `can_mutate_lead()` requires live ownership or an explicit assignment-manager/administrator grant independently of read visibility; handlers additionally require the operation capabilities. Save/autosave/discard lock the live lead before the quote and refresh effective user/role grants after a lock wait. New saves reject persisted revision IDs; repeated UUIDs must match actor, lead, quote and source operation. See [slice evidence and role-impact matrix](AUDIT-REMEDIATION-QUOTATION-SLICE.md). Since 1.102.44, quotation list/count and task queue results also use live lead scope, and customer quotation operations require effective CRM enrollment. Permission/scope edits invalidate warmed read generations. Configured role scope is applied after the required quotation capability check; without an explicit role override, assignment managers retain unrestricted scope and other quotation users retain assigned-lead scope. `IDLM_Quotation_Lead_Read_Model` applies that scope to lead existence, search, editor choices, and selected-lead prefilling.

## Request protection

Bootstrap recovery/migration gates validate string action, URI and REST-route metadata before unslashing and sanitization. Malformed query arrays do not reach string casts. These fields only classify request lifetime or a pending-migration 503 response; handlers retain their own nonce, capability, object and provider-authentication checks. [REL-007 source review and request-fence evidence](AUDIT-REMEDIATION-PACKAGE-BOOTSTRAP-REQUEST-SLICE.md).

Release 1.101.63 separates read-all visibility from mutation authority. Mutations require an existing lead plus current ownership or manager capability, including comments and uploads. Pre-sales drawer saves cannot alter qualification or commercial fields; qualification decisions continue through their dedicated controls. Uploaded attachments record `_idlm_attachment_lead_id`, and drawer attachment binding rejects a different lead's newly bound files. Legacy local attachments migrate through verified, resumable copy/unlink journals; unresolved public originals remain an operator remediation item.

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

Confidential lead uploads use WordPress validation/attachment APIs and private database chunks; raw bytes are never published in uploads. Authenticated download URLs enforce live lead read scope and private/no-store headers. Catalogue CSV import adds explicit extension, MIME, size, header, field, row-count, formula/export, and transactional checks. Lead uploads now enforce field-specific purpose, extension, detected MIME, and 10 MB size policies before WordPress media validation, then record purpose/creator/time ownership metadata.

Sensitive data includes lead contact/property/budget/deal data, call recording URLs, activity messages, CAPI/audience payloads, stack traces, and advertising/API credentials. New raw integration payloads redact common credential/contact fields, and configurable daily retention prunes touchpoint, CAPI, audience, and telemetry rows. Scoped webhook/MCP/Google-feed and all seven platform credentials use authenticated encryption or server-owned constants. Platform settings are write-only; migration/write failure and unreadable ciphertext fail closed. [INT-010 migration, key recovery and tracing limits](AUDIT-REMEDIATION-SECRET-STORE-SLICE.md).

## Phase 1 production gates

MCP is fail-closed in production, has per-tool grants, bounded payload/batch/query budgets, rotation/revocation fingerprints, aggregate audit attribution, and an emergency disable constant. Production deployment must still approve the enabled tool set and rotate the credential. Exact constants and proxy/upload behavior are documented in [PRODUCTION-SECURITY-CONFIG.md](PRODUCTION-SECURITY-CONFIG.md).

Remaining gates include production debug/log verification, credential rotation and key-management rehearsal, installed scheduler/runtime qualification, historic secret exposure, backup-confirmed uninstall policy, and staging rehearsal. Local platform migration and bounded retention implementations have evidence; production qualification remains pending.

## Existing concerns

1. **Low — compatibility renderer review.** Shared access, workspace, and account views use guarded templates and the workspace boot script is asset-based. Large legacy compatibility renderers retain contextual markup; keep edits narrow and preserve escape-at-output review.
2. **Low — MCP host trust.** `mcp-server.php` derives WordPress from the plugin location, writes its default error log to the system temporary directory, and accepts explicit `IDLM_MCP_WP_LOAD` / `IDLM_MCP_ERROR_LOG` environment overrides. Operators must protect those environment values and the local stdio process boundary.

Do not fix these as incidental changes. Any further remediation needs compatibility and endpoint tests.

MCP custom queries reject unsupported syntax and every unauthorized table/field before execution. Customer contact/profile data requires the independent server-side PII grant; safe user projection excludes authentication columns and unrelated accounts, even with PII permission. Raw log/private-file fields remain excluded. [MCP remediation evidence and platform limits](AUDIT-REMEDIATION-MCP-SLICE.md).

## Lead CSV text-cell protection

Lead export requires `manage_options` and the admin nonce. Its 40-column payload neutralizes formula/control/Unicode prefixes through the existing quotation text sanitizer while preserving schema-controlled ID/quote value and original database rows. Denials contain no CSV attachment and produce no business/job/outbound changes; explicit export grants are checked live. [CORE-003 evidence and spreadsheet/support qualification limits](AUDIT-REMEDIATION-CSV-SLICE.md).

## Private lead file policy — candidate 1.102.47

Builder: Shashi. Owner or explicit manager upload authority is separate from read visibility. Main files are limited to 10 MiB; unsaved uploads expire after seven days. Pointer removal, lead deletion and reset revoke downloads and journal erasure. Partial JSON restore is blocked when private files or private pointers are present; a full WordPress database backup is required for recovery. See [implementation and verification](AUDIT-REMEDIATION-PRIVATE-FILE-SLICE.md). Production migration/cache revocation and runtime matrix remain unqualified.

### Recovery authority and fencing (1.102.48)

Shashi: backup/download/restore remain `manage_options` plus dedicated nonce; broad CRM/quotation visibility and assignment authority do not grant backup access. Preflight/staging reject incompatible aggregates before deletion. Session-held guards fence covered workers and CRM HTTP mutations; committed recovery generations reject stale queued PII and working copies. Same-primary, cooperative scope and unverified failure modes are explicit in [the sixth slice](AUDIT-REMEDIATION-RESTORE-SLICE.md).

## Assignment target eligibility — CORE-009

Shashi: future ownership requires a recognized CRM role, enabled access and effective CRM capability, with the existing enabled administrator allowance. Disabled status overrides that allowance. Intake requires pre-sales; qualified automatic sales excludes pre-sales even in mixed-role accounts. Canonical changed-owner writes use locked live account/meta/role data rather than cached selection. Historical ownership is not automatically erased and does not grant new access. Non-InnoDB authentication dependencies fail closed for new ownership. [Executed evidence and unqualified runtimes](AUDIT-REMEDIATION-ASSIGNMENT-ELIGIBILITY-SLICE.md).

## Identity-review authority and erasure (1.102.54)

Shashi: only enabled, effectively enrolled assignment managers may inspect/resolve held PII. Review-specific, user-bound nonces include the recovery generation. POST decisions refresh locking account/meta/role grants and revalidate the live candidate list; broader read grants do not grant resolution. Stored capture excludes workflow/owner/credential/file input. Whole associated reviews are erased with lead deletion/reset, and all statuses follow bounded receipt-based retention. [Detailed policy and limits](AUDIT-REMEDIATION-IDENTITY-REVIEW-SLICE.md).


## WhatsApp signed replay and acknowledgement (INT-006, Shashi)

Meta/Interakt inbound handlers read enabled state and secrets directly from primary SQL, check HMAC before JSON processing and reject malformed identities/bounds before persistence. Full normalized phone matching replaces suffix/newest-lead selection; conflicting canonical identities get an explicit failure. Minimal provider-ID receipts commit with activity, survive pruning and same-site recovery, and contain a keyed fingerprint rather than raw contact/message data. Salt rotation causes old fingerprints to require review, never silent resend. Receipt volume/retention, provider retry timing and complete erasure policy remain release qualification work. [Detailed policy](AUDIT-REMEDIATION-WHATSAPP-INBOUND-SLICE.md).

## Google audience feed revocation — INT-007

Builder: Shashi. Every credentialed audience pull uses `IDLM_Audiences::get_active_rules()` through `resolve_google_feed_rule()`, also used by scheduled/manual batch sync. A disabled, removed or unmatched rule returns 404 before querying contacts or setting CSV headers. Explicit saved empty/invalid rules override legacy sync options; only an absent rules option enables the legacy fallback. Duplicate targets use the first enabled match in saved order. Feed responses use `private, no-store, max-age=0`; downloads already obtained and requests already authorized at selection cannot be recalled. Basic/header credentials, credential rotation and rate limiting retain their existing boundaries. Provider-side deletion/consent and manual audience retry/exclusion semantics remain separate qualifications.

## CAPI delivery evidence and erasure — INT-008

Builder: Shashi. Minimal server-only receipts preserve acceptance/uncertainty independently of diagnostic retention. Direct primary reads, current eligibility, existing session locks, checked InnoDB claim/acknowledgement transactions and recovery fencing prevent a lost log from authorizing resend. Receipt bodies contain lead/platform/event identity and attempt status/time, with no credentials/contact/provider payload. Their lead-lifetime retention ends at explicit lead deletion/reset; same-site restore preserves existing local receipts and excludes imported receipt options. A full database loss, explicit erasure or earlier historical pruning cannot be repaired from excluded receipts. Uncertain outcomes require reconciliation; exactly-once provider delivery and a public reconciliation interface are not claimed. [Evidence](AUDIT-REMEDIATION-CAPI-RETENTION-SLICE.md).

## Audience current exclusions and uncertain outcomes — INT-009

Shashi: selected IDs cannot override duplicate/stage/call/quality predicates. Primary locking claims, current rule/credential reload, nonautoload minimal state, checked InnoDB transactions and one session lock protect dispatch/ack and overlap. Unknown outcomes fence the same destination even after filter changes; explicit reselection cannot override them. Bulk queue/cancel retains object access and nonce checks; execution remains administrator-only. Removal stops manual work, not provider membership. Restore/reset starts a new audience epoch and clears work; external in-flight calls cannot be recalled. Consent/provider removal remains separate qualification; INT-010 now supplies protected platform storage and covered transport redaction. [Evidence and limits](AUDIT-REMEDIATION-AUDIENCE-RETRY-SLICE.md).

## Local unreleased lead connector

Builder: Shashi. New Meta callback HMAC/verify-token checks precede parsing; Page/form allowlists constrain intake. Three dedicated credentials are encrypted and write-only. Provider retrieval uses a fixed HTTPS Graph host, Bearer headers and no redirects; pagination never follows provider next URLs. Interakt intake uses its existing signature boundary. Encrypted jobs contain no credentials and scheduler arguments contain no PII; aggregate transactions bind WhatsApp receipts and attribution. HMAC erasure markers and generation fences block stale recreation. [Security and qualification limits](META-LEAD-CONNECTOR.md).

## Hosted Facebook connection boundary (local unreleased)

Builder: Shashi. The separate publisher service keeps encrypted Page tokens and the app secret outside WordPress and outside its public document root. Per-install signing keys live in protected server constants; pending local consent claims use the existing secret store and bind to an administrator session. Signed requests enforce timestamps and replay nonces; responses bind to the request nonce. Registered HTTPS return origins, fixed Meta hosts/paths, selected Page/form ownership, installation epochs and constrained Graph projections prevent arbitrary redirects, cross-install access and token-bearing provider paging URLs reaching clients. Service code is excluded from plugin distribution. No live security or Meta approval certification is implied; qualify the deployment using its README.

## REL-007 request boundary qualification — 2026-10-18

Builder: Shashi. Export authorization rejects structured, negative, floating/exponent identifiers before coercion or SQL; positive decimal strings, including leading zeros, remain accepted. Authentication accepts only scalar strings and removes WordPress slashes once; passwords and HTTP Basic credentials retain literal bytes for credential comparison. Request arrays fail before password writes or string operations. Static source annotations identify the exact delegated nonce/capability/object guard, compiled SQL constructor or binary/CLI boundary; they add no authorization. [Reviewed sites and qualification limits](AUDIT-REMEDIATION-PACKAGE-STATIC-COMPLETION-SLICE.md).
