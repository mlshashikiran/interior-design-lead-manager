# Integration, authentication, operations and recovery audit

Author / Builder: Shashi. Reviewed 6 October 2026. Candidate: `1.102.42`, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`, including the existing dirty tree. No production behavior changed. This review added only its documentation and `tests/audit-integration-security.php`. The parent audit records the complete tree/artifact identity and release verdict.

## Evidence and scope

Reviewed current implementations of settings/branding, scoped secrets, frontend authentication/account/user administration, production security, inbound/outbound integrations, CAPI, audiences, Interakt and its separate administrative batch worker, scheduling, deployment/operational health, telemetry/diagnostics, MCP HTTP/stdio/SQL, backup/restore, retention, and the integrity CLI entry point. Assignment ownership and task/lead deletion internals are covered by the core review; integration callers and effects were traced here.

Graphify navigation query: `graphify query 'Which modules own scoped credential authentication, asynchronous integration retries and backup restore cancellation?' --budget 1400`. Material result: the bounded result emphasized `IDLM_Admin`, the settings controller, and fast/native test runners. It did not establish queue/restore causal ownership; all conclusions below were verified against current PHP. Graph output is an aid, not evidence that the current dirty tree is indexed completely.

Read targeted `PROJECT-MAP`, `SECURITY`, `INTERFACES`, `FLOWS`, `DEVELOPMENT`, and `PRODUCTION-SECURITY-CONFIG` documentation. The scoped-secret and fail-closed production MCP descriptions are supported. Several older descriptions conflict with implementation: `INTERFACES.md` says no CLI commands although `class-integrity-command.php:19` registers `idlm integrity`; it also describes the removed Local-specific MCP fallback. `FLOWS.md` describes ordinary Interakt delivery as Action Scheduler/WP-Cron, but ordinary events use synchronous fallback; only administrative backfills use WP-Cron. Its assertion that no binary PDF is generated is stale in this candidate (quotation reviewer covers the new renderer).

No configured-site database, real integration credentials or customer data were read. External requests in the added audit script are intercepted PHP doubles. The audit script never loads WordPress. The parent subsequently brought up a separate MySQL process/database and reports the native suite as 17 passing, zero failing. Native restore/concurrency/provider-delivery checks below remain distinct from that aggregate result.

## Module contracts and ownership

`CRM` below means the shared access policy; managers have additional assignment authority. Administrator utilities use `manage_options`. Static methods exposed by the classes are PHP compatibility seams, not independently authorized public APIs; caller authorization still matters.

| Runtime module | Purpose, entry points and public contracts | Inputs, validation and authorization | Persistence, hooks, dependencies and outputs | Recovery and existing verification limits |
|---|---|---|---|---|
| `class-settings.php` | `init`, routing `get`/`sync_routing_from_legacy`, branding defaults/read/validate/style/asset/favicon helpers. Bootstrap initializes it; admin/frontend shells and reporting consume it. | Routing values read legacy first, grouped snapshot second, default last. Branding validates attachment images, six-digit colors, 4.5:1 primary/sidebar contrast, email and text. Favicon limited to CRM shortcodes. | `idlm_settings_version`, `idlm_routing_settings`, `idlm_white_label_settings`; `wp_head` favicon action. CSS tokens and normalized contexts; WordPress media dependency. | Group migration is simple option mirroring. Fast tests exercise routing, branding/contrast and safe renderer output. Migration write failure and branding asset browser behavior not tested here. Read-all default remains `1` at line 226; see the core access finding. Attribution display defaults `1` at line 36; parent assesses distribution requirement. |
| `class-secret-store.php` | `get`, `set`, `is_environment_managed`; called by REST auth, rotation, feed settings and health. | Sanitized option keys; fixed environment mapping for webhook/MCP/feed password. Nonempty environment constant overrides option; environment-backed writes rejected. | AES-256-GCM `idlm:v1:` options, nonautoload; key derived from dedicated key or WordPress salts. Plaintext reads transparently attempt encryption. | Returns default on decryption failure; encrypt requires OpenSSL. Migration caller does not verify all writes before advancing credential version/removing legacy key. Fast/audit secret round-trip passes; missing OpenSSL, salt rotation and write-failure migration NOT RUN. Remaining platform secrets are outside this module. |
| `class-auth.php` | Anonymous admin-post sign-in, password request/reset; reset screen assets and header filters. `reset_user` validates displayed reset state. | Action nonce; email resolution then `wp_signon`; local validated return URL. Request uses normalized hashed-email transient and generic response. Reset uses WordPress user-bound key, matching minimum-eight-character password and core validation hook. | Core users/password keys/cookies/sessions/mail; `idlm_reset_*` 60-second throttle, temporary `retrieve_password_message` filter. Redirect response. | WordPress owns authentication and reset sessions. No custom login throttle; password-request throttle is non-atomic. Fast coverage is chiefly source/markup assertions, not a real successful login, expired key, mail failure or session revocation. Those browser/core scenarios NOT RUN by this reviewer. |
| `class-admin-account-controller.php` | Frontend own-account render and `handle_update`, through admin facade. | Logged-in self only, dedicated nonce; email duplicate check; profile text/phone sanitization, matching optional password length; validated local return. No submitted target user ID. | `wp_update_user`, `idlm_phone` user meta; optional `retrieve_password`; password replace/auth cookie; core logout URL. HTML or redirect. | Sequential nontransactional profile/meta/reset writes. Does not ask current password; a valid logged-in session is authority. Reset-mail result not propagated. End-to-end profile/password/session checks NOT RUN. |
| `class-admin-users-controller.php` | Administrator user list/create/update, scope update, role/suspension helpers. Admin facade registers its three admin-post handlers. | Dedicated nonces and `manage_options`; managed CRM role allowlist; duplicate login/email; self and final-administrator safeguard. Role scope allowlist `all`/`assigned`; assigned wins among mixed roles, administrator all. | WordPress user/role changes; `idlm_phone`, `idlm_crm_access_disabled`; `idlm_role_lead_scopes`; core reset email. Existing leads retain historical owners; disabling blocks policy access rather than deleting account. | No transaction across profile/role/meta/mail. Final-admin count is not concurrency locked. Status filter is applied after paginated query, so count/pagination may show empty/sparse pages. Existing access-policy tests cover scope/disabled users; full create/update/race/mail workflow NOT RUN here. |
| `class-admin-settings-controller.php` | Settings POST processing and scope-specific credential rotation. Called by settings renderer/facade. | `manage_options`, action nonce, rotation POST/scope allowlist; typed sanitation/clamps and branding validation. Mapping dictionaries and audience rules use text sanitation, not a provider schema validator. | Many `idlm_*` options; mirrored routing snapshot, task target updates and cache invalidation. Three scoped secrets encrypted; platform secrets saved as plaintext options. Return boolean or redirect. | Sequential saves; UI/value labels saved before branding validation can return false (`84–115`), leaving partial settings. Native tests cover capability/nonce/sanitized mirror/scoped encryption. Atomic save, failed option writes and retained secrets after partial POST lack coverage. |
| `class-admin-settings-renderer.php` | Admin/frontend guarded settings shell and context builder. `credential_value` returns values only for `manage_options`. | Administrator gate before media enqueue, request processing or output. Guarded escaped templates. | Reads integrations, routing, branding, health; creates feed password if absent; authorized credential HTML; reusable template context. | Secrets are not localized into JS, but are intentionally present in authorized settings HTML. Missing encrypted secret can lead to attempted regeneration. Browser cache/secret reveal handling NOT RUN. |
| `class-production-security.php` | Shared environment/MCP policy, grants/budgets, proxy resolution, upload validation, credential fingerprint metadata and aggregate MCP audit. Called by REST, upload handlers, MCP and health. | Production MCP disabled unless configured, emergency constant wins. Tool allowlist; bounded payload/batch/query values; direct-IP trust, right-to-left trusted forwarding chain; purpose/extension/MIME/10 MB uploads. | `idlm_mcp_enabled`, tool grants, proxy options, MCP metadata; aggregate telemetry outcome/tool/key fingerprint/client/time only. No PII payload in MCP audit. | Bounds cap output size, not database execution duration. Single expensive query is not terminated; time budget checked between batch calls only. Tests cover grants, disabling, proxy/network/upload and policy context; real multihost proxy/cache/filesystem NOT RUN. |
| `class-integrations.php` | Credential migration/rotation; tracking cookies; REST registrations/permission/handlers; payload mappings/classification; routing/handoff/fallback delegates; HTTP MCP transport. Bootstrap initializes it. | Header-only scoped webhook/MCP keys or dedicated Basic/header Google feed credential; constant-time equality. Fixed-window per-client rate counts 120/30/60 per minute. Valid supported platform/contact identity. Route and handler repeat credential checks. | Lead lifecycle upsert/touchpoints/review queue, then route only new lead; routing activities/tasks/mail. REST JSON/CSV/JSON-RPC. `rest_authentication_errors` bypass only recognized integration paths; feed Basic challenge filter. 30-day UTM/click-ID/landing cookies injected on all frontend pages. | Rate counters use non-atomic transient get/set; simultaneous nodes can exceed limit. No lead-webhook payload/depth/size budget. Test hooks return 200 without persistence. Native REST test covers denial/lead persistence/MCP key but not provider delivery or rate races. |
| `class-capi.php` | Stage and attribution listeners; `queue_capi_event`, `process_capi_event`, platform fire methods, `retry_capi_log`; offline conversion events/CSV and normalization helpers. | Platform enable/mapping/credentials; duplicate and current-stage checks in worker; prior-success lookup across canonical/duplicate IDs. Google requires paid-source/click-ID eligibility; LinkedIn FAT ID. | `idlm_capi_logs`, activity diagnostics; async `idlm_async_capi_upload_event` for non-New events. New synchronous; other stages delayed 300s, prior lead/stage jobs canceled. Meta/GA4/LinkedIn HTTP (15s), CSV uses state history/current state as fallback. | No automatic provider failure retry or atomic send claim. HTTP failure logs require manual retry; scheduling failures not checked. Manual retry differs from worker validation and configured names (INT-005). Existing tests exercise lifecycle hooks/native lead persistence; no direct CAPI delivery/eligibility behavior test was found in the fast/native runners. Failure/reversal/overlap/provider acceptance gaps remain. |
| `class-audiences.php` | Daily/manual `run_batch_sync`, rules selection, Google CSV; private Meta/LinkedIn sends, Google pull audit. | Enabled rules, stage/call/quality filters and nonduplicate base selection; manual queue IDs override rule predicate. Google feed authenticates at integration boundary. | Leads plus `idlm_audience_sync_rules`/`idlm_audience_sync_queue`; sync audit summaries. Meta 30s POST to audience users; LinkedIn 30s contacts; Google logs pull activation. CSV hashes contact identifiers. | All matching leads fetched at once; no provider-size chunking, overlap lock or failed-row retry. Queue cleared after rule loop regardless delivery failure; disabled pull rule served (INT-007). Logs redact/summarize payload. Provider limits, real delivery, consent and removal semantics NOT RUN. |
| `class-interakt.php` | Lifecycle/no-answer hooks, generic track event, async callback, phone/trait helpers/inbox link; Interakt/Meta inbound webhooks. | Outbound generic checks enabled/key/phone initially. Inbound `__return_true` route permission with callback enabled/HMAC check before JSON/persistence; Meta GET verify token. | Outbound PII trait snapshot plus API key in Action Scheduler args; synchronous fallback otherwise; 15s Basic-auth track POST. Activity and telemetry outcomes. Incoming reply suffix matching, newest lead, `whatsapp_reply` activity. | Ordinary worker only checks lead exists, ignores current disable/rotation/duplicate/state (INT-004); no automatic retry/claim. Inbound does not deduplicate message IDs, and Meta only processes first batched message (INT-006). Signature tests pass; mock audit confirms omissions. |
| `class-interakt-batch.php` | Administrator historic backfill jobs; `allowed_event`, `enqueue`, `process`; shared hook registered from Interakt init. | HTTP caller caps 1–50 scalar IDs, mapped event, request ID regex 8–80. Worker key regex; refreshes enabled/current key/mapping/nonduplicate lead/phone. | Nonautoload `idlm_interakt_batch_<sha256(request,lead,event)>` option; atomic option insert deduplicates enqueue, MySQL advisory lock serializes worker. AS or WP-Cron hook stores only opaque key. Fresh traits, attempt count/status; activity and HTTP through Interakt. | At most 3 tries, 120 then 240s backoff; send may repeat after accepted response/crash before `sent` write, no provider idempotency token. Busy advisory-lock callback returns without rescheduling. Terminal job options never pruned. Existing AS contract tests verify retry scheduling; true competing workers/crash gaps NOT RUN. |
| `class-scheduler.php` | Frontend `wp` setup plus bootstrap activation calls; recurring audience and assignment/task rollover jobs; `clear_schedules`. | No user entry; backend choice is availability of AS helper. Site timezone computes tomorrow 02:00 initial daily target; intervals are fixed seconds. | AS daily/hourly jobs or `_cron` WP-Cron variants; consumers audiences/telemetry, integrations/DB. | Sequential setup presence checks, no cross-backend cleanup if AS becomes available later: prior WP-Cron and new AS can both remain active. Deactivation clears recurring and retention jobs but does not clear asynchronous CAPI/Interakt hooks. Backend transition/DST/deactivation with pending work NOT RUN. |
| `class-horizontal-readiness.php` | Aggregate deployment `report` and boolean detection; settings/diagnostics context. | Infrastructure constant declarations, persistent cache helper, AS and managed cron detection. No secrets/hosts returned. | Read-only readiness statuses/checks for shared DB/media/config/cache, AS/managed cron and streamed exports. | Shared infrastructure readiness is operator attestation, not proof of failover/atomicity. Fast test context checks pass. Physical multi-node/load/recovery checks NOT RUN. |
| `class-operational-health.php` | Aggregate `report`, schema/database/schedules/secret/storage/cache/queue/recent-fault detection. Administrator shell exposes it. | UI uses administrator gate; class itself is internal read utility. Enabled-service credential presence only; no remote authentication. | Read-only custom tables/options, AS tables; aggregates overdue/failed actions, faults and storage writeability. | Presence/version markers cannot prove columns/engines/schema success or current credential validity. WP-Cron backlog intentionally unobservable. Google feed credentials and enabled audience-only service credentials omitted from checks. Fast context/report tests pass; native failure-detection/large queues NOT RUN. |
| `class-telemetry.php` + diagnostic AJAX | Error/exception handlers; `log`, `prune_logs`; JS log/clear/report through admin facade and AJAX class. | PHP origin/level filters, sanitization and 1000-row cap. Clear/download require `manage_options` + nonce. JS handler checks nonce only, unlike documented CRM authorization. | `idlm_telemetry_logs`; relative plugin paths, message/trace; prior exception handler delegated; external fallback error_log. Administrator diagnostic text includes env, table counts, last 50 raw messages/traces. | No redaction of freeform error text or length cap; PHP/HTTP messages can contain PII/secrets. 1000-row cap suppresses later MCP audit. Timestamp pruning mixes UTC threshold with site-time insert. Source/fast tests exercise error delegation/health; malicious JS flood and production-like redaction NOT RUN. |
| `class-mcp-core.php` + `mcp-server.php` | Five granted tools, JSON-RPC protocol/SQL engine; HTTP callers authenticate via integrations; CLI stdio bootstrap uses configured WP-load/error-log paths. | Engine checks enable/grants but no WordPress per-user ownership. SQL deny regex + FROM/JOIN table allowlist + numeric limit. Stdio authority is host process access, no API key; stdin line has no HTTP payload/batch/time guard. | Global CRM aggregates or PII rows/activity; users for rep context. Raw SQL additionally allows `wp_users`; query errors returned to credential caller. HTTP aggregate telemetry; stdio has startup/error log only. | INT-001 and INT-002 compromise custom-query boundary. Default production tools aggregate only; development default includes all five. Fast/native key/SQL checks miss mixed JOIN/comma and auth columns. Added audit fails these checks. No SQL query timeout; invalid shape/type errors can escape Exception-only catch as PHP Error. |
| `class-backup.php` | Admin-post export/restore; versioned JSON, 21 fixed custom-table suffixes, filtered options; restore/cancel helpers private. | `manage_options`, dedicated nonce, confirmation, uploaded `.json`, 2–100 MB, envelope format/version/array checks. Requires target custom tables exist and InnoDB. | Export all rows/options into memory; credentials/core users/media/jobs omitted. Restore transaction deletes/reinserts tables and selected options directly, commits, clears aggregate option caches, unschedules 3 async hooks, prunes retention and invalidates read cache. | No payload completeness/relationship/column/schema-version validation (INT-003). Options table engine not verified; individual nonautoload option-cache keys not invalidated. No consistent export transaction, restore maintenance lock or active-worker fencing; historical data immediately pruned. Native round-trip/rollback/large-file/old-schema tests NOT RUN. |
| `class-data-retention.php` | `init`, `prune`, `clear`, nested keyed redaction, audience-summary/legacy compaction. Bootstrap and daily WP-Cron; restore calls prune directly. | Static table/column targets and clamped numeric policy intervals. Sensitive key redaction, not arbitrary string redaction. | Deletes touchpoints/reviews, integration/telemetry rows; fixed 7-day CAPI/activity cleanup, policy defaults 90/180/14 days. Legacy audience payload summary batch 100, marker/invalidation. | Configured CAPI 180 days overridden by fixed 7 days (INT-008). Bulk DELETE is unbounded and failures ignored; upgrade marker can advance despite failed cleanup. Retention is not customer erasure. Tests check source constants/redaction/summary; large-table chunk/failure recovery NOT RUN. |
| `class-integrity-command.php` | `WP_CLI::add_command('idlm integrity', ...)`; `audit($args,$assoc_args)` invokes lead-deletion `audit_orphans`. | Host CLI authority; `--repair` enables deletion. No web route or nonce. | Table orphan counts, optional dependent deletion, CLI log/success. Rule owner is lead-deletion service, not CLI wrapper. | Real CLI installation/repair/failure reporting NOT RUN here. Existing docs incorrectly say no custom command. Core reviewer owns orphan SQL correctness. |

## Registered entry-point chains

All rows include the authentication → authorization → validation → operation → persistence → effects → response chain; scheduled/CLI/internal entries deliberately use host authority rather than a web nonce.

| Registration / caller | Handler chain and observable contract |
|---|---|
| `admin_post_nopriv_idlm_login` | Auth handler → action nonce → email/core authentication → core cookies → validated local success/generic failure redirect. Login does not itself grant CRM access. |
| `admin_post_nopriv_idlm_request_password_reset` | Action nonce → normalized email → hashed throttle → core user lookup/reset-key/mail with temporary CRM URL filter → generic reset-sent redirect, regardless existence/mail outcome. |
| `admin_post_nopriv_idlm_reset_password` | Nonce → core user-bound key → password confirmation/length/hooks → core reset/password/session effects → local redirect. |
| `admin_post_idlm_update_my_account` | Admin facade → account controller → authenticated own ID + dedicated nonce → email/password/profile validation → core users/meta/password/mail → local redirect. |
| `admin_post_idlm_create_user`, `idlm_update_user`, `idlm_update_role_scopes` | Facade → users controller → administrator + respective nonce → role/scope/identity/admin safeguards → core user/role/meta or option → reset mail if requested → notice/error local redirect. |
| Admin menu/frontend settings page POST | Settings renderer administrator gate → controller POST marker + save nonce → sanitized inputs/branding check → options/secret/routing/task writes → cache invalidation → guarded settings HTML/boolean notice. Not an AJAX-only endpoint. |
| `admin_post_idlm_rotate_api_credential` | Facade/controller → administrator/POST/scope-specific nonce → generate/encrypt scoped secret → MCP fingerprint revocation metadata if applicable → settings tab redirect. No secret in URL. |
| `POST /interior-design-lead-manager/v1/webhook` | Explicit permission callback rate/key → repeated callback key → JSON/form/raw/platform/mappings/test/contact validation → canonical lifecycle upsert/identity conflict review → touchpoint/new lead/merge → routing only new lead; hooks/tasks/integration side effects → 200, 202 review, 400/401/422/500 errors. |
| `GET .../google-ads-conversions[.csv]` | Feed permission rate + dedicated Basic/header secret → repeated handler auth → conversion event read model/eligibility/mapping → CSV → buffered length/content/cache headers and termination. This is global feed scope, not CRM-user scope. |
| `GET .../google-ads-audience`, `.../google-ads-audience/{list_id}.csv` | Same feed credential/rate → list-ID rule selection → query matching nonduplicates → hashed CSV/headers. Missing list 400, unmatched 404; disabled flag currently ignored (INT-007). |
| `GET/POST/DELETE .../mcp` | Enabled production policy → rate/scoped header → repeated handler auth → GET info or DELETE acknowledgment; POST payload/JSON/batch/time-bound-between-calls → engine grants/tool validation/reads → aggregate audit → JSON-RPC result/error or 204 notification. DELETE is acknowledgment, no stored session revocation. |
| `POST /idlm/v1/interakt-webhook` | Public permission → callback enabled/secret/HMAC before parse → message shape/contact/text/media sanitization → suffix lead lookup → activity → 200 acknowledgment; malformed 400, disabled 403, invalid signature 401. No replay ID storage. |
| `GET /idlm/v1/meta-webhook` | Public permission → callback enabled/verify-token equality → challenge plaintext via REST response. |
| `POST /idlm/v1/meta-webhook` | Public permission → enabled/app-secret/HMAC → WhatsApp object → first entry/change/message only → lead suffix lookup/activity → 200; no deduplication. |
| `wp_ajax_idlm_trigger_manual_audience_sync` | Logged-in AJAX registration → administrator nonce/capability → synchronous audience loop → audit/queue clear → success reports trigger acceptance, not per-provider success. |
| `wp_ajax_idlm_test_api_connection` | Nonce/administrator → platform/target/token sanitation → fixed provider host GET; Google only checks username/password format → JSON success/error. Google message says verified though no provider/feed request occurred. |
| `wp_ajax_idlm_retry_capi_events` | Nonce/administrator → log-ID array → repeated manual retry per log → platform HTTP/new audit → aggregate counts/errors JSON. Input count unbounded. |
| `wp_ajax_idlm_get_mapped_past_leads`, `idlm_sync_mapped_past_batch` | Nonce/administrator → selected mapped event/rule reads; batch 1–50 IDs/request regex/enable-key check → opaque option claim/jobs/activity → queue-existing-failed JSON. Delivery happens later. |
| `wp_ajax_idlm_log_js_error` | Logged-in route + admin nonce only → freeform sanitized error/file/line/stack → telemetry insert → JSON success. No CRM/capability guard. |
| `wp_ajax_idlm_clear_telemetry_logs`, `idlm_download_diagnostic_report` | Nonce/administrator → TRUNCATE telemetry or aggregate/env/recent-log read → success JSON or text download. Report downloads retain raw messages/traces. |
| `admin_post_idlm_export_backup`, `idlm_restore_backup` | Administrator + dedicated nonce → fixed table read/filtered options export; or confirmation/upload/envelope/engine checks → transaction replace → async cancellation/retention/cache → JSON download or local notice redirect. |
| `idlm_lead_stage_converted` | Canonical lifecycle consumer CAPI queue → New immediate, otherwise cancels pending stage jobs and schedules 300s → worker current-state gate → enabled provider HTTP and logs. |
| `idlm_lead_attribution_merged` | CAPI rereads lead/success logs → critical added click fields → reverse current maps → force synchronous platform resend per successful row. No deduplicated platform/event grouping, transaction/queue or worker state check. |
| `idlm_lead_call_status_no_answer`, `idlm_trigger_interakt_event` | Interakt generic handler → current enable/key/phone → traits snapshot → AS args or synchronous HTTP → activity/telemetry. |
| `idlm_async_capi_upload_event`, `idlm_async_interakt_track_event`, `idlm_async_interakt_batch_event` | Scheduler authority → CAPI rechecks current duplicate/stage then HTTP; ordinary Interakt existence-only then stale payload/key HTTP; administrative Interakt regex/advisory-lock/current settings/lead, retry state then HTTP. |
| `idlm_daily_audience_sync[ _cron]`, `idlm_hourly_assignment_fallback[ _cron]` | Registered paired consumers: audience sync then telemetry prune; assignment fallback then pre-sales task rollover. `idlm_prune_sensitive_data` separate daily WP-Cron → deletion/compaction. |
| `wp_head`, `wp`, REST auth/dispatch filters | Settings favicon at priority 2; integrations cookie JS; scheduler presence/setup on frontend `wp`; scoped-route auth bypass and Basic challenge. No business write authorization should rely on these filters. |
| `mcp-server.php`, `idlm integrity [--repair]` | Trusted local CLI process → WordPress configured bootstrap → shared MCP policy/engine reads with line JSON output; CLI integrity → deletion-service audit/optional repair → counts/success. |

No missing callbacks were found among these source registrations. Duplicated auth checks are deliberate defense in depth. Rule duplication is material in worker versus manual CAPI retry, daily audiences versus pull feeds, ordinary versus administrative Interakt queues, and policy retention versus fixed diagnostic pruning.

## Cross-module flow 10: queues, failure, retry, cancellation, duplicates

```mermaid
sequenceDiagram
    participant Lead as Lead lifecycle
    participant CAPI
    participant Queue as AS / WP-Cron
    participant Provider
    participant Logs as Logs / job options
    Lead->>CAPI: stage converted
    CAPI->>Queue: cancel pending old stages; schedule +300s
    Queue->>CAPI: process(lead_id, stage)
    CAPI->>CAPI: reread lead; duplicate/current-state gate
    CAPI->>Logs: check prior successful platform/event
    CAPI->>Provider: mapped payload (15s)
    Provider-->>CAPI: success / error
    CAPI->>Logs: request, response, status
    Note over CAPI,Logs: failed delivery has no automatic retry; admin retry uses different mapping/state path
    Note over Queue,Provider: no atomic CAPI claim; parallel check-then-send may duplicate
```

New lead CAPI is synchronous and may delay capture by the combined provider timeouts. Non-New jobs cancel known stages before scheduling, but a running job cannot be recalled. Worker applies gates only for Qualified, Quote Shared and Won; custom mapped stages have no equivalent current-stage check. Prior-success idempotency depends on logs later deleted after seven days. Meta sends stable lead/event `event_id`; Google and LinkedIn do not implement the same persistent provider idempotency contract.

Administrative Interakt uses atomic option enqueue claim plus MySQL advisory lock and fresh settings/lead data. It retries all false delivery results up to three times (including permanent validation errors) and records terminal state. Ordinary Interakt does not share that mechanism: it queues snapshot PII and key in AS, or sends synchronously if unavailable, does not retry failure, and does not respect later disable/key change. A crash after provider acceptance and before local `sent` status can duplicate any backfill; provider acceptance/crash recovery remains untested. Deactivation only clears recurring jobs, so pending asynchronous jobs can run after reactivation with stale arguments.

Audience sync is a synchronous whole-rule pass. It always clears the manual queue after the pass, even if the provider failed. No overlap guard prevents the manual action and scheduled action from overlapping. Switching from WP-Cron fallback to AS can leave both backend recurring schedules, increasing duplicate passes.

## Cross-module flow 11: backup/restore and recovery

```mermaid
sequenceDiagram
    participant Admin
    participant Backup
    participant DB
    participant Queue
    participant Cache
    Admin->>Backup: nonce + uploaded JSON + RESTORE
    Backup->>Backup: envelope/upload validation
    Backup->>DB: required target tables and InnoDB checks
    Backup->>DB: START TRANSACTION
    Backup->>DB: DELETE 21 tables; INSERT payload rows; replace filtered options
    alt failed write
        Backup->>DB: ROLLBACK
        Backup-->>Admin: restore-failed redirect
    else accepted
        Backup->>DB: COMMIT
        Backup->>Cache: delete alloptions/notoptions
        Backup->>Queue: unschedule 3 outbound hooks
        Backup->>DB: retention prune
        Backup->>Cache: invalidate read generation
        Backup-->>Admin: restored redirect
    end
```

Export includes 21 custom tables and allowed `idlm_*` options, but does not take a consistent read snapshot. Schema versions and credentials are excluded; file envelope has no plugin/schema version or column/relationship manifest. Restore validates target table existence/engine but not the presence of every payload table: an omitted suffix silently becomes empty. Uploaded relational inconsistencies are accepted where no database constraint rejects them. User IDs and attachment IDs/URLs cannot be remapped because WordPress users/media are excluded; a cross-site restore needs matching users/media and explicit compatibility checks.

Queue cancellation happens after commit, cannot fence already executing workers, and does not delete/reconcile batch option records restored by the broad option selector. Active workers reading the same numeric lead IDs may overlap restoration. Direct option SQL bypasses normal option hooks and per-key nonautoload cache invalidation; AS cancellation/retention later in the same request may observe cached pre-restore settings. Custom tables are checked InnoDB, while `wp_options` transaction participation is assumed. Restore immediately prunes imported data, so it cannot recover historical logs beyond current policy. No maintenance fence or backup/restore native round-trip evidence establishes this entire recovery contract as safe yet.

## Findings register

All findings concern the current reviewed candidate. Severity reflects impact under the specified trigger; MCP production defaults reduce exposure but do not repair tool security when granted. An executed double proves control flow, not real provider acceptance or database concurrency.

### INT-001 — HIGH — MCP SQL allowlist bypass after an explicit JOIN

- **Module/source:** `class-mcp-core.php:265–291`, execution `:502–521`.
- **Trigger/users:** enabled `run_custom_query` tool; credential holder or authorized stdio caller supplies a comma table after JOIN. CRM ownership scope is not consulted by MCP.
- **Expected/actual:** every referenced table must be allowlisted. `SELECT o.option_value FROM wp_idlm_leads l JOIN wp_idlm_leads x ON x.id=l.id, wp_options o` is accepted and gets `LIMIT 200`; scanner counts only FROM/JOIN references and the comma guard only handles the initial FROM table.
- **Impact:** arbitrary WordPress/plugin table reads, including options/secrets, user metadata, other plugins' customer data. Output limits do not prevent disclosure across repeated pages.
- **Safe evidence/coverage:** audit `INT-S02` FAIL returns the executable query from the real validator with only `wp_idlm_leads` allowed. No database called. Existing `tests/run.php:638–647` tests simple forbidden FROM and immediate comma joins but misses mixed grammar.
- **Remediation/verification:** replace freeform regex SQL with a parser restricted to a deliberately small grammar or parameterized predefined read tools. Fail closed on all unsupported/comma table expressions. Verify adversarial mixed joins, nested expressions, quoted strings and qualified identifiers against a disposable DB, including prevention before any query executes.
- **Confidence:** high; executable-query acceptance confirmed. Actual forbidden data extraction not attempted.

### INT-002 — HIGH — MCP explicitly exposes WordPress authentication columns

- **Module/source:** `class-mcp-core.php:503–509`, `:517–521`; policy default grants `class-production-security.php:39–59`.
- **Trigger/users:** enabled custom-query credential/stdio caller. Development defaults grant this tool; production must explicitly grant it.
- **Expected/actual:** analytical representative lookup should expose approved profile fields. Entire `$wpdb->users` table is allowlisted with no column restrictions; `SELECT user_pass, user_activation_key FROM wp_users` is passed unchanged to DB.
- **Impact:** password hashes and reset-key material for administrators and unrelated WordPress users, enabling offline guessing and privacy exposure beyond CRM scope.
- **Safe evidence/coverage:** audit `INT-S03` FAIL captured the SQL using the repository DB double; it did not retrieve any actual hash. Existing tests consider table restriction, not safe projections of allowed user table.
- **Remediation/verification:** remove raw users from custom queries; expose explicit representative display-name/ID aggregates or safe DB views. Deny sensitive columns/SELECT star by design and exercise native synthetic auth-column tests. Also address INT-001, since column filtering alone cannot protect table bypasses.
- **Confidence:** high; unrestricted query acceptance confirmed. Password reset exploitation was not attempted.

### INT-003 — HIGH — Incomplete backup payload commits an unintended CRM reset

- **Module/source:** `class-backup.php:103–106`, `:119–160` especially `:139–145`.
- **Trigger/users:** administrator imports a structurally valid partial/historical/damaged backup after confirmation. Payload `{format:'idlm-backup',version:1,tables:[],options:[]}` passes envelope checks.
- **Expected/actual:** missing data manifest rejected before deletion. Restore checks destination tables only, deletes every table, treats each missing payload suffix as empty, and commits success.
- **Impact:** all 21 CRM/quotation/routing tables and eligible settings lost despite a restore-success response; historical backup missing newly added tables silently empties them. Transaction cannot help when invalid input is regarded as successful.
- **Safe evidence/coverage:** audit `INT-S05` FAIL reports `result=true; DELETE count=21` with an explicit SQL recorder double. `INT-S06` PASS proves insert failure requests rollback, not actual engine atomicity. Existing backup test `tests/run.php:115` only asserts source size constant/template/prune text.
- **Remediation/verification:** validate complete table/column/schema manifest, key types and referential relationships before transaction; distinguish an explicitly empty table from an absent table. Add versioned upgrade adapters/reject incompatible schemas; native round-trip, missing-table/no-write and partial-failure rollback tests. Add consistent export snapshot and active-worker fence before release recovery approval.
- **Confidence:** high control-flow proof; actual DB wipe deliberately not performed.

### INT-004 — MEDIUM — Ordinary queued Interakt ignores disabling and retains revoked key

- **Module/source:** `class-interakt.php:111–123`, `:130–135`.
- **Trigger/users:** lead event queued, then integration disabled/key rotated/lead excluded before worker execution; affected customer receives old automation.
- **Expected/actual:** workers enforce current enable/credential/lead eligibility. Ordinary worker checks only existence and sends snapshot payload with the queued plaintext key. Administrative batch worker does perform fresh validation, so behavior differs by entry point.
- **Impact:** unwanted messages/data transfers after shutdown, key copies retained in AS arguments/backups and stale traits/phone use. Deleted lead is suppressed, but disabled/duplicate/state-reversed lead is not.
- **Safe evidence/coverage:** `INT-S07` FAIL produces one intercepted HTTP request while enabled=`0`; synthetic revoked key supplied. `INT-S08` PASS confirms deleted lead suppression. Existing AS test covers administrative batch retry, not ordinary worker revocation.
- **Remediation/verification:** queue only opaque IDs/event claims; reread current lead/settings/key at dispatch; support cancellation/revocation epoch and provider idempotency. Verify disabling, duplicate, changed phone/ownership/state and rotation with both backends/crash recovery.
- **Confidence:** high; mocked dispatch confirmed, no real message sent.

### INT-005 — MEDIUM — Manual CAPI retry cannot replay current Google defaults and bypasses worker gates

- **Module/source:** `class-capi.php:661–704`; defaults `:479–482`; worker gates `:269–307`.
- **Trigger/users:** administrator retries Google `CRM Qualified Lead` / `Closed Won Interior Project` log or customized mapping; or retries a prior Meta event for a now-reversed/duplicate lead.
- **Expected/actual:** retry same persisted event under current eligibility and report this attempt. Hardcoded reverse names recognize only legacy Google lowercase names; otherwise stage becomes New, for which Google default map has no entry, so no request. Retry force-calls provider methods without worker duplicate/current-stage validation and looks up any latest same-name old log, potentially reporting old success as resend success.
- **Impact:** failed conversion recovery silently makes no delivery; misleading outcome; inappropriate/duplicate conversions after lifecycle reversal, degraded campaign optimization.
- **Safe evidence/coverage:** audit `INT-S11` FAIL seeds current default failed Google event and eligible lead, intercepts zero HTTP requests. No actual advertising event. No direct CAPI retry test was found in the fast/native runners; mapped retry equivalence was previously unverified.
- **Remediation/verification:** persist stable stage/event/config snapshot in durable job; unify retry with worker eligibility; identify outcome by attempt ID and reject non-retryable/unknown mapping states. Test defaults, custom names, mapping changes, stale success, duplicate/reversal and provider failure.
- **Confidence:** high Google control-flow proof; other force-retry consequences source verified, provider effects untested.

### INT-006 — MEDIUM — Meta webhook acknowledges dropped batch messages and appends replays

- **Module/source:** `class-interakt.php:423–446`; matching/write helpers `:340–364`, `:464–486`.
- **Trigger/users:** valid provider payload contains multiple messages/entries/changes, or provider retries a successfully handled signed message. Sales users see an incomplete/duplicated conversation.
- **Expected/actual:** process every relevant message exactly once (or explicitly report partial failure). Callback only inspects `[0][0][0]`, returns 200 for the rest and persists no message ID/replay marker. Interakt callback also lacks replay marker.
- **Impact:** permanent lost reply evidence, duplicate unread activities/notifications; phone suffix chooses newest lead including duplicates and can misassociate international/shared suffix identities.
- **Safe evidence/coverage:** audit `INT-S12` FAIL sends synthetic signed two-message body, gets 200 and one activity; `INT-S13` FAIL replays it and count grows from one to two. HMAC wrong-secret test passes. Existing tests exercise signature/media rendering, not multi-message/replay handling.
- **Remediation/verification:** loop all entries/changes/messages, store provider+message-ID unique claims, acknowledge after durable processing, use canonical normalized full-phone identity/conflict handling. Native concurrent replay and mixed media/status/batched fixtures required.
- **Confidence:** high for drop/replay; suffix association risk source-only and collision fixture NOT RUN.

### INT-007 — MEDIUM — Disabling Google audience rule does not stop its pull feed

- **Module/source:** `class-integrations.php:537–558`; daily enabled filtering `class-audiences.php:59–64`.
- **Trigger/users:** administrator disables saved Google rule but feed holder/Data Manager continues existing list-ID pulls.
- **Expected/actual:** disabled rule unavailable. Pull selection tests platform and target ID only, ignores enabled flag, then queries/export matching leads. Daily sync correctly skips disabled rules.
- **Impact:** continued contact-identifier transfer after operator disables that audience; UI/cron state gives misleading stop assurance. Valid feed credentials still required; this is not anonymous access.
- **Evidence/coverage:** source-confirmed branch comparison; real feed/download NOT RUN by reviewer. No disabled-rule regression found.
- **Remediation/verification:** common active-rule resolver for scheduled/manual/pull consumers; tests for disable/re-enable, duplicate list IDs and credentials before CSV output.
- **Confidence:** high source evidence; provider stop behavior not exercised.

### INT-008 — MEDIUM — Seven-day CAPI pruning removes configured retention and idempotency evidence

- **Module/source:** `class-data-retention.php:67–84`, `:116–132`; success-based dedup `class-capi.php:92–130`.
- **Trigger/users:** logs older than seven days, irrespective configured `integration_logs_days` default 180; later repeated stage/attribution events.
- **Expected/actual:** requested retention honored and durable delivery identity survives diagnostic cleanup. Policy prune is immediately followed by unconditional 7-day CAPI DELETE, removing successful and failed logs alike; idempotency lookup relies exclusively on those logs.
- **Impact:** shortened retry/audit window, vanished conversion evidence and later eligible duplicate sends; Meta provider event ID may reduce duplication but does not restore local history, Google/LinkedIn lack equivalent durable claim here.
- **Evidence/coverage:** existing test explicitly asserts source seven-day constant, so passing test preserves the mismatch; no time-advanced delivery-dedup integration test. Native/time-advanced provider consequences NOT RUN.
- **Remediation/verification:** separate durable delivery claims from retention-controlled request/response detail; document/align diagnostic retention policy; prune bounded batches with observable errors. Advance synthetic clock beyond retention then repeat/reverse stage and verify one durable event.
- **Confidence:** high retention/control dependency source evidence; exact provider duplicate treatment unknown.

### INT-009 — MEDIUM — Audience failures discard manual work; manual queue can bypass exclusion

- **Module/source:** `class-audiences.php:91–99`, `:107–121`, platform methods `:131–198`, `:274–323`.
- **Trigger/users:** provider timeout/non-2xx during administrator bulk sync; or queued lead subsequently marked duplicate/otherwise no longer matches rule before daily pass.
- **Expected/actual:** retain failed work and revalidate exclusions. Provider methods only log and return; enclosing loop clears queue regardless outcome. SQL `(id IN(queue) OR (is_duplicate=0 AND filters))` allows queued duplicate and rule-ineligible IDs.
- **Impact:** failed manually selected work lost; excluded customer can enter all active audiences; manual success response only indicates loop completed. Full rule selection runs alongside selected IDs, so a selected retry may resend all matching leads.
- **Evidence/coverage:** source-confirmed branches, no real audience upload attempted. No provider failure/queue-revalidation tests found.
- **Remediation/verification:** explicit per-rule/page durable outcomes, retained failure queue, shared nonduplicate/consent eligibility outside OR, bounded retry/remove semantics. Test duplicate-after-queue, permanent/transient errors and overlapping runs with mocked services/native claims.
- **Confidence:** high source evidence; provider acceptance/account size limits unverified.

### INT-010 — MEDIUM — Remaining platform tokens are plaintext and some requests put secrets in URLs

- **Module/source:** settings `class-admin-settings-controller.php:248–270`, `:375–388`; CAPI `class-capi.php:423`, `:521`; audiences `class-audiences.php:170`; ordinary Interakt queue `:115`.
- **Trigger/users:** enabled Meta/Google/LinkedIn/Interakt integration, database/AS backup access or HTTP URL tracing/proxy logs.
- **Expected/actual:** project security requirement stores protected server-side secrets and avoids URLs/logs. Only webhook/MCP/feed secrets use encrypted/environment storage; platform keys/HMAC secrets are direct options; Meta token/Google API secret interpolated into URL. Authorized settings HTML can read platform secrets.
- **Impact:** secret proliferation into database exports outside the plugin backup, AS arguments and URL diagnostics. Plugin backup selector filters credential-like option names correctly, so that particular export is not the claimed leak. No evidence a publicly reachable log contains a token was found.
- **Evidence/coverage:** source-confirmed; scoped encryption round-trip PASS does not cover remaining secrets. Existing SECURITY/PRODUCTION-SECURITY-CONFIG already call migration a pending production gate.
- **Remediation/verification:** finish environment/encrypted secret migration, validate migration writes before advancing/deleting legacy keys, queue IDs only; use auth headers where supported, provider-required URL secrets must be redacted at logging boundaries. Verify diagnostics/error strings/logs/backups with synthetic token markers and missing-extension/salt-rotation/write-failure tests.
- **Confidence:** high storage/URL evidence; public exploitability not asserted.

## Executed scenarios and honest limits

`php tests/audit-integration-security.php` runs 13 synthetic scenarios through actual current PHP implementations with test DB/HTTP doubles. Result: **6 PASS, 7 FAIL, exit 1**, documenting defects, not changed production behavior. `php -l tests/audit-integration-security.php` passes. An initial fixture execution missed the recorder's `esc_like` method; fixture was corrected before the reported completed run. No production code was altered to satisfy tests.

| Scenario | Status | Evidence and limit |
|---|---|---|
| INT-S01 direct forbidden table denial | PASS | Actual SQL validator, no DB execution. |
| INT-S02 mixed explicit/comma JOIN table denial | FAIL | Returns query containing forbidden options table; INT-001. |
| INT-S03 authentication-column denial | FAIL | DB double receives users password/reset columns; INT-002. |
| INT-S04 MCP emergency-disabled query | PASS | Shared engine returns denial. |
| INT-S05 incomplete restore rejection before writes | FAIL | Recorder sees 21 DELETEs and committed success; INT-003. |
| INT-S06 insert-failure rollback request | PASS | Recorder sees ROLLBACK; actual storage atomicity not proved. |
| INT-S07 disabled ordinary queued Interakt | FAIL | One intercepted HTTP call; INT-004. |
| INT-S08 deleted lead Interakt suppression | PASS | No intercepted HTTP call. |
| INT-S09 wrong HMAC | PASS | Exact crypto helper rejects synthetic wrong secret. |
| INT-S10 encryption round-trip | PASS | Scoped synthetic token absent from stored ciphertext; real salts/migration not exercised. |
| INT-S11 default mapped Google retry | FAIL | Zero HTTP calls from eligible failed log; INT-005. |
| INT-S12 multiple signed Meta messages | FAIL | Only first activity persists, 200; INT-006. |
| INT-S13 replay of signed Meta message | FAIL | Duplicate activity appends; INT-006. |
| Existing full fast suite | PASS (parent) | Parent reports 174 checks. Many described above are source string assertions/doubles; not full UI/provider behavior. |
| Existing native suite | PASS (parent) | Parent reports 17 checks on isolated MySQL; individual supported scenarios are in parent evidence. Does not imply missing restore/concurrency scenarios ran. |
| Disabled audience pull, audience failed-queue retention, CAPI retention/dedup | NOT RUN | Source findings, no provider requests. |
| Restore round-trip/schema adaptation/options cache/media/user integrity/job fencing | NOT RUN | Required disposable-native and synthetic WordPress coverage still needed. |
| Real competing workers/rate counters/advisory-lock loss/crash-after-acceptance | NOT RUN | Doubles return lock success; cannot establish concurrency safety. |
| Browser login/reset/account/users/settings, disabled/mixed roles, expired/replayed nonces | NOT RUN by reviewer | Parent UI evidence may establish a bounded subset; source descriptions alone are not PASS. |
| External-service real delivery/ad acceptance/large audiences | NOT RUN | User prohibited real messages/audiences; use provider mocks/approved sandbox before distribution. |
| Horizontal failover/shared cache/proxy/multisite and supported PHP/WordPress matrix | NOT RUN by reviewer | Parent release/CI evidence governs; declaration flags are not deployment tests. |

## Extension and recovery guidance

Use the canonical lifecycle service/DB facade for lead writes so assignment/tasks/state events/cache/hooks remain coupled. Reuse access/quotation policy before reading or mutating a target lead; visibility does not grant ownership mutation authority. Add authenticated web mutations with a dedicated nonce/capability/object check; tool credentials are separate global scopes and must not inherit CRM-user assumptions. Reuse scoped secret store rather than copying secrets into settings arrays, jobs or logs.

Treat external delivery as a durable event with an identity, current eligibility, outcome and retry policy. Queue acceptance is different from delivery success. Never infer retry success from an older successful log. Keep essential delivery claims longer than diagnostic request/response detail and fence restore/delete operations against workers. Keep media/users and schema compatibility explicit in restore documentation because this JSON backup does not include them.

Operational-health `healthy` is aggregate observability, not release approval; it cannot prove provider credentials work or table columns exist. Diagnose with aggregate metrics first, then tightly scoped sanitized logs. Existing diagnostics contain raw strings and should not be assumed secret-free merely because tables/options themselves are protected.

The reviewed integration/security/recovery scope is **NOT READY**. Priorities: fix MCP parser/table and authentication-column boundaries together; reject invalid backups and establish native restore/worker recovery; unify Interakt/CAPI delivery eligibility, IDs and retries; repair webhook batch/replay and disabled audience behavior; separate idempotency from log retention, then complete remaining secret migration and bounded observable jobs. Required approval evidence includes passing adversarial/native synthetic regressions for these findings, a current-source installed artifact, replay/concurrency/restore rehearsal, and the parent release checklist. No source-only or passing baseline claim resolves the outstanding material findings.
