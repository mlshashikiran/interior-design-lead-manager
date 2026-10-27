# Architecture

## Overview

Interior Design Lead Manager is a WordPress-native, custom-table CRM. It uses a procedural bootstrap and static service classes, direct `$wpdb` persistence, WordPress options for configuration, authenticated admin-AJAX for the interactive UI, and REST endpoints for external systems. There is no container, autoloader, ORM, or build system; a minimal guarded template layer handles extracted reusable views.

```mermaid
flowchart TD
    WP[WordPress] --> B[Plugin bootstrap]
    B --> DB[IDLM_DB facade]
    DB --> LS[IDLM_Lead_Lifecycle_Service]
    B --> UI[IDLM_Admin facade + focused renderers/AJAX + IDLM_Dashboard]
    B --> INT[IDLM_Integrations]
    B --> Q[IDLM_Quotations]
    Q --> QP[IDLM_Quotation_Policy]
    Q --> QL[IDLM_Quotation_Lead_Read_Model]
    Q --> QC[IDLM_Quotation_Calculator]
    Q --> QR[IDLM_Quotation_Repository]
    Q --> QS[IDLM_Quotation_Schema]
    Q --> QH[IDLM_Quotation_Controller + Renderer]
    B --> JOB[IDLM_Scheduler]
    DB --> H[Domain hooks]
    H --> CAPI[IDLM_CAPI]
    H --> WA[IDLM_Interakt]
    JOB --> AUD[IDLM_Audiences]
    JOB --> INT
    UI --> DB
    INT --> DB
    QR --> DBT[(Quotation tables)]
    QS --> DBT
    DB --> CT[(CRM tables)]
    INT --> EXT[Ad platforms / MCP / email]
    CAPI --> EXT
    AUD --> EXT
    WA --> EXT
```

## Bootstrap and initialization

`interior-design-lead-manager.php` defines release/schema constants, requires every class in a fixed order, and registers activation/deactivation. Activation creates the core and quotation tables, stores schema state, schedules jobs, and installs CRM roles/capabilities. On `plugins_loaded`, initial role setup/access migration is checked without restoring revoked grants and—except on `wp-login.php`—telemetry, schema checks, integrations, CAPI, Interakt, scheduler, admin UI, and quotations initialize.

The fixed include order is a hidden dependency: classes call each other statically without constructor injection. Most calls are safe after boot, but activation explicitly depends on the required files already being loaded.

## Major boundaries

- **Domain/data:** `IDLM_DB` is the compatibility facade for schema, task, and audit APIs; `IDLM_Lead_Lifecycle_Service` owns lead insert/upsert/update transitions. `IDLM_Lead_Deletion_Service` owns transactional cleanup and orphan audit/repair. The database has no declared foreign keys; integrity is application-managed.
- **Presentation/controllers:** `IDLM_Admin` is the thin compatibility facade for registrations, menus, and public callbacks. `IDLM_Admin_Assets` owns shared asset registration/context enqueueing; `IDLM_Admin_Shortcodes` owns frontend shortcode/workspace orchestration; `IDLM_Admin_Account_Controller` owns account presentation/update coordination; `IDLM_Admin_Users_Controller` owns administrator-only CRM user creation, managed-role changes, access suspension, password-reset initiation, and administrator safeguards. `IDLM_Admin_Presenter` and `IDLM_Admin_Lead_Notifications` own display formatting and WhatsApp/reminder presentation support. Focused page renderers own context/query coordination. `IDLM_Admin_Ajax` remains the authenticated AJAX compatibility coordinator; `IDLM_Admin_Collection_Reads` owns list/Kanban/count transport and `IDLM_Admin_Task_Controller` owns task transport; `IDLM_Admin_Activity_Controller` owns timeline reads and comment mutations using the same inherited facade helpers as `IDLM_Admin_Lead_Reads`; `IDLM_Admin_Telemetry_Controller` owns client-error capture, administrator log clearing and diagnostic text downloads; `IDLM_Admin_Connection_Controller` owns administrator-only platform connection diagnostics, while `IDLM_Admin_Lead_Reads` owns the extracted lead-detail read and drawer response behind both established delegate layers. `IDLM_Cursor_Pagination` signs user/filter-bound keyset positions used by the default manager lead queue while legacy page/offset requests remain compatible. `IDLM_Dashboard_Read_Model` scopes and caches the aggregate queries built by `IDLM_Dashboard`; `IDLM_Read_Model_Cache` provides generation invalidation, object-cache/transient storage, and rebuild locking. `IDLM_Lead_Access_Policy` centralizes effective representative capabilities plus ownership/visibility decisions.
- **Settings/views:** `IDLM_Admin_Settings_Controller` owns authenticated CRM/integration settings POST persistence, and `IDLM_Admin_Settings_Renderer` owns settings context/environment reads plus ten guarded section templates behind the established `IDLM_Admin` callback. `IDLM_Settings` provides grouped non-secret routing reads with mirrored legacy options. `IDLM_View` includes guarded root or nested plugin templates with a single context array; templates remain responsible for context-specific escaping.
- **Quotations:** `IDLM_Quotations` remains the compatibility facade. `IDLM_Quotation_Controller` owns request/transaction handlers; `IDLM_Quotation_Renderer` owns escaped views and semantic server-rendered print output; policy, lead read model, calculator, schema, and repository classes own their established boundaries.
- **Integration:** `IDLM_Facebook_Direct` handles site-owned Meta consent and encrypted Page tokens behind `IDLM_Facebook_Connect`, whose hosted transport remains for existing connections. `IDLM_Lead_Connector` retains signed lead webhook intake/import. `IDLM_Integrations` retains stable callbacks, browser attribution, MCP and assignment routing. `IDLM_Integration_Auth` owns scoped credentials/permissions; `IDLM_Webhook_Intake` owns parsing/mapping and canonical upsert coordination; `IDLM_Google_Feed_Controller` owns feed HTTP responses. `IDLM_Handoff_Notifications` owns opt-in recipient/message construction and email delivery after durable handoff dispatch. `IDLM_CAPI` retains dispatch/current-eligibility/retry orchestration; `IDLM_CAPI_Transport` performs its provider calls and `IDLM_Google_Conversion_Feed` owns conversion export data. `IDLM_Audiences` and `IDLM_Interakt` retain their platform adapters. `IDLM_Audience_Dispatch` commits per-lead/rule audience claims before transport, owns bounded manual/scheduled pages and acknowledges outcomes with audit logs; `IDLM_Audiences` supplies current predicates and adapters. `IDLM_CAPI_Delivery` commits minimal delivery claims before CAPI transport and acknowledgements with logs afterwards; retention preserves expired legacy success evidence in those receipts.
- **Scheduling/diagnostics:** `IDLM_Scheduler` selects Action Scheduler when available and otherwise WP-Cron. `IDLM_Telemetry` installs PHP error/exception handlers and persists diagnostic events. `IDLM_Operational_Health` provides aggregate schema/database/scheduler/retention/credential/storage/cache/queue/fault checks with initial warning/critical thresholds. `IDLM_Horizontal_Readiness` provides an aggregate-only administrator preflight for shared database/media/configuration declarations, persistent cache, Action Scheduler, controlled cron, and node-local export assumptions.

## Lead data flow

```mermaid
sequenceDiagram
    participant Source as External source / CRM user
    participant Handler as REST or AJAX handler
    participant I as IDLM_Integrations
    participant D as IDLM_DB
    participant T as CRM tables
    participant Hooks as WP hooks
    participant Side as CAPI / Interakt / tasks / email
    Source->>Handler: authenticated request
    Handler->>Handler: nonce/key, capability, sanitize, validate
    Handler->>I: map/normalize/route (webhooks)
    Handler->>D: upsert/insert/update
    D->>D: lifecycle and duplicate rules
    D->>T: prepared insert/update + activity/touchpoint
    D->>Hooks: stage, attribution, call-status events
    Hooks->>Side: queued conversion, message event, task/handoff effects
    Handler-->>Source: JSON, REST response, redirect, or CSV
```

`IDLM_State_Registry` owns built-in labels and contextual manual-task/early-close/qualification subsets; it does not define a permitted-edge finite-state machine. [STATE-POLICY.md](STATE-POLICY.md) distinguishes current transitions, reversals and historical repair. `IDLM_DB::update_lead()` is the central transition boundary. It reconciles quality/stage, qualification/disqualification metadata, handoff state, timing metrics, tasks, activity entries, and downstream hooks. Any direct update to `wp_idlm_leads` must be reviewed for bypassed behavior.

## Admin and frontend architecture

The frontend `[idlm_workspace]` is the single CRM interface. The same compatibility callbacks power `[idlm_dashboard]`, `[idlm_leads_list]`, `[idlm_kanban]`, and the composite workspace; `IDLM_Admin_Shortcodes` coordinates these callbacks, and page methods delegate to focused renderers and guarded `templates/admin/` views. `[idlm_quotations]` delegates to the quotation module. WordPress Admin retains only an administrator utility page with plugin/version and shortcode setup information; legacy CRM admin URLs redirect to the capability-matched workspace section when a published workspace page exists. Shortcodes require logged-in CRM/quote capabilities; admin-only sections remain gated by `manage_options`. `IDLM_Admin_Assets` preserves the shared handles and context-specific module sets used by both shortcodes and quotations.

`assets/js/admin-app.js` is the shared AJAX client and modal/task implementation; it keeps page-entry cursors for sequential lead-list navigation and retains offset-compatible page requests where the server does not issue a cursor. Dashboard, Kanban, quotation, and workspace scripts add module behavior. `wp_localize_script()` exposes only the AJAX URL, shared admin nonce, and call-status vocabulary. CSS follows the same shared-plus-module model.

## REST, AJAX, and integration architecture

All AJAX actions are authenticated (`wp_ajax_` only) and use WordPress login cookies plus a nonce. Handlers then apply broad CRM, manager, administrator, quotation, and object-level checks. See [INTERFACES.md](INTERFACES.md).

The lead webhook, Google feed, and MCP routes use route-specific permission callbacks with encrypted/environment-backed scoped credentials, header-only constant-time checks, and per-IP rate limits; handlers retain defense-in-depth authentication checks. A high-priority `rest_authentication_errors` filter still bypasses site-wide REST authentication errors for those integration paths so external credentials can be evaluated independently of cookie authentication. Interakt and Meta callbacks use verification-token or HMAC checks appropriate to their external contracts.

Outbound integrations use `wp_remote_post()` for Meta, Google Measurement Protocol, LinkedIn, and Interakt. Google audience sync itself is feed-based/stubbed rather than an upload API; LinkedIn and Meta audience sync post hashed lead identifiers. Notifications use `wp_mail()`.

## Test architecture

`tests/run.php` is the fast dependency-free behavioral suite and uses the WordPress/$wpdb doubles in `tests/bootstrap.php`. `tests/integration/run.php` boots real WordPress core against a newly created `idlm_test_*` MySQL database, while isolated workers exercise redirecting quotation saves and concurrent number reservations. GitHub Actions runs both suites across PHP 7.4/8.1/8.4 and WordPress 6.6/latest.

`tests/performance/` contains deterministic PII-free dataset generation, aggregate environment/query-plan capture, and the Phase 8 authenticated read-only HTTP runner. Load execution is an opt-in non-production operation; it reuses existing WordPress cookies/nonces and action contracts and emits aggregate evidence only. The required resilience matrix and remaining evidence are tracked in [LOAD-RESILIENCE-QUALIFICATION.md](LOAD-RESILIENCE-QUALIFICATION.md).

`tests/release/` contains the Phase 9 release-artifact boundary. It inventories files through `.distignore`, aligns version authorities, rejects development/private material and high-confidence credential signatures, verifies a single plugin-directory ZIP, and emits exact-revision, CI-attestation, and SHA-256 evidence. Dirty local output is rehearsal-only; the CI candidate job waits for the complete test matrix. See [PRODUCTION-RELEASE.md](PRODUCTION-RELEASE.md).

The admin facade refactor reached its verified release boundary in version 1.98.1: all 75 compatibility methods remain callable, `includes/class-admin.php` is 432 lines, and the automated plus live route/viewport/interaction evidence is recorded in [ADMIN-REFACTOR-VERIFICATION.md](ADMIN-REFACTOR-VERIFICATION.md).

## Scheduling

Daily audience sync and telemetry pruning run around 02:00; sensitive-data retention runs daily. Hourly assignment fallback also rolls forward pre-sales tasks. Action Scheduler hooks (`idlm_daily_audience_sync`, `idlm_hourly_assignment_fallback`) are used when its API exists; otherwise `_cron` WP-Cron hooks are scheduled. CAPI and Interakt events use asynchronous single actions/events. Assignment fallback uses a database advisory lock, and round-robin counters use atomic `idlm_routing_state` rows.

Horizontal deployment requirements, declarations, current runtime-write inventory, and qualification order are recorded in [HORIZONTAL-DEPLOYMENT.md](HORIZONTAL-DEPLOYMENT.md). The readiness report does not provision infrastructure and must be followed by the Phase 8 two-node resilience suite.

## Recommended future structure

Production hardening, measured query/cache work, bounded background processing, hotspot extraction, and multi-node requirements are sequenced in [PRODUCTION-SCALABILITY-PLAN.md](PRODUCTION-SCALABILITY-PLAN.md). That plan is the implementation authority for scalability work; the structure below remains a gradual direction rather than permission for a wholesale rewrite.

The present structure is WordPress-compatible but class boundaries no longer match responsibilities. A gradual, compatibility-preserving target is:

```text
src/
  Admin/          menu and page controllers
  Frontend/       shortcode controllers
  Domain/         lead, qualification, routing, quotation services
  Database/       schema, repositories, migrations
  Rest/           route registration and request controllers
  Integrations/   Meta, Google, LinkedIn, Interakt, MCP
  Support/        capabilities, formatting, telemetry
templates/        escaped PHP views
assets/           source/static client assets
docs/
tests/
```

Introduce this one seam at a time behind existing class methods, hooks, route names, and shortcodes. Do not perform a wholesale namespace/autoload migration without a compatibility plan.

Private-file boundary (Builder: Shashi, 1.102.47): static `IDLM_Private_Files` encapsulates additive database metadata/chunks and authenticated retrieval behind existing upload/lead/deletion facades. Lead locks bind writes; per-file cleanup locks serialize retry journals. Verified database bytes precede irreversible legacy filesystem unlink. File identity spans custom tables and WordPress posts/postmeta, so partial plugin JSON restore is blocked while files exist. [Details and unqualified dimensions](AUDIT-REMEDIATION-PRIVATE-FILE-SLICE.md).

### Cooperative recovery guard (1.102.48)

Shashi: `IDLM_Backup_Validator` performs strict aggregate preflight; `IDLM_Backup` owns snapshot/staging/transaction recovery. `IDLM_Recovery_Guard` coordinates same-database worker sessions and committed epochs; existing static public facades remain intact. These are bounded recovery helpers, not a general transaction/outbox framework. [Scope and limits](AUDIT-REMEDIATION-RESTORE-SLICE.md).

## Core migration coordination

Shashi: `IDLM_DB` owns additive core declarations and backfill orchestration. `IDLM_Schema_Migration` is the shared core/routing/quotation session-lock, physical-verification and checked-marker helper; `IDLM_Routing_State` retains routing declaration/counter ownership. Bootstrap and activation pause the CRM on failed/pending migration. Ready markers avoid repeated DDL/lock work. DDL and idempotent repairs can persist independently across interruption; this shared helper does not itself provide lifecycle/outbox atomicity. Quotation migration coordination is now implemented by `IDLM_Quotation_Schema` as described below. [Migration slice](AUDIT-REMEDIATION-SCHEMA-SLICE.md).

## Checked lead transactions and after-commit intent

Shashi, candidate 1.102.50: `IDLM_Lead_Transaction` owns required core lead aggregate transactions behind the existing lifecycle/DB facades; automatic assignment preserves its expected-owner check under the same owner. `IDLM_Lead_Outbox` records compatible hook arguments and cache intent with that aggregate, then dispatches after commit with bounded replay/session claims. Unrelated transactions are refused; private-file migration shares the owner. Queues/providers retain their separate M4 behavior, and replay is at least once. [Contracts and qualification limits](AUDIT-REMEDIATION-LIFECYCLE-SLICE.md).

## Qualification task-plan owner — 2026-10-07

Shashi: `IDLM_Presales_Task_Plan` owns checked stable-cycle adoption, fill, evidence synchronization and bounded scheduled repair. `IDLM_DB::create_presales_followup_tasks`, `sync_presales_task_status`, `reschedule_pending_presales_tasks` and `roll_forward_presales_tasks` remain compatibility facades. Lead update and automatic intake assignment invoke it inside `IDLM_Lead_Transaction`; it reuses the live lead lock/recovery fencing and durable cache intent. [Boundary and evidence](AUDIT-REMEDIATION-TASK-PLAN-SLICE.md).

## Current capacity admission boundary — CORE-010

Shashi: `IDLM_Assignment_Capacity` admits positive active-workload deltas after normalization and before canonical insert/update or automatic writes. The target account row is its transaction-scoped mutex; locking settings/count reads avoid cached decisions and earlier repeatable-read snapshots. Committed lead rows remain the capacity source of truth, with no new reservation storage or schema. Existing task/history/outbox requirements share commit/rollback. [Rationale and qualification](AUDIT-REMEDIATION-CAPACITY-SLICE.md).

## Identity review boundary (1.102.54)

Shashi: the focused identity-review owner handles native admin presentation/POST transport, sanitized holding data, replay lookup and erasure/retention. Resolution stays in the lifecycle owner to reuse private identity locks, touchpoint/attribution rules, canonical insertion and checked transaction/outbox timing. Deletion/reset/backup owners explicitly include the holding table. [Contract and evidence](AUDIT-REMEDIATION-IDENTITY-REVIEW-SLICE.md).

## Bounded retention and reads (`1.102.55`)

Shashi: retention runs one checked 100-row batch with transactional `idlm_retention_progress`, recovery/session fencing, fair target rotation, continuation hook `idlm_continue_sensitive_retention`, and operational failure status. Task responses use 50-card signed keysets and scoped totals (`counts_only` skips cards); queue qualification history reads 100 dates at a time. WhatsApp filters/counts stay in SQL; compatibility ID helpers page 100 by `after`. Lead CSV stages 100-row reads, capped at 10,000 rows/32 MiB/20 seconds of application work, before headers. Core/routing/quotation schemas unchanged. [Policy, verification, API details and remaining production gates](AUDIT-REMEDIATION-BOUNDED-WORK-SLICE.md).


## Checked quotation migration completion (1.102.57)

Shashi: `IDLM_Quotation_Schema::schema_statements()` owns the ten-table physical manifest; install/delegates return `true` or `WP_Error`. An autocommitted `idlm_quotation_migration_status=pending` precedes DDL. Checked phase transactions commit successful substep markers; final schema 2.5.1 and `ready` receipt commit together. Boot/health use durable reads for all required markers/status. Recovery fencing and schema session locking protect migration ownership; foreign transactions and unsupported engines are refused. DDL may persist independently. Historical classification/state preservation remains QUO-005/010. [Policy and evidence](AUDIT-REMEDIATION-QUOTATION-MIGRATION-SLICE.md).


## Inbound WhatsApp transaction boundary (INT-006, Shashi)

The signed parsers in `IDLM_Interakt` delegate messages to `IDLM_WhatsApp_Inbox`. It serializes provider IDs, refuses active foreign transaction contexts, locks an unambiguous canonical phone match and commits reply plus receipt with `IDLM_Lead_Transaction`. Partial batches use independently committed messages so a provider replay resumes only missing work. No remote HTTP occurs inside this transaction. [Evidence and qualifications](AUDIT-REMEDIATION-WHATSAPP-INBOUND-SLICE.md).

## Hosted Facebook connection boundary (local unreleased)

Builder: Shashi. Each customer keeps an independent WordPress CRM. One separate publisher service handles Facebook consent and Page subscriptions, using a distinct signed credential for each installation. The service queues only selected Page/form/lead identifiers; the site's existing scheduled connector retrieves answers through a constrained proxy and commits through `IDLM_DB::upsert_inbound_lead()`. Existing manual direct sync and Interakt remain compatible. Service PHP 8.1+/SQLite/Sodium requirements apply only to the separate deployment; the plugin runtime requirement is unchanged.
