# Development Guide

## Environment and initialization

The selected minimums are PHP 7.4+ and WordPress 6.6+. There is no project Composer/npm build pipeline; Dompdf and its dependencies are bundled under vendor/. See the public readme and SUPPORT.txt for database, crypto, renderer, ZIP and storage prerequisites. The exact candidate matrix remains a release qualification gate. Chart.js and Inter fonts are committed assets. Action Scheduler is optional; WP-Cron is the fallback. The current checkout is commonly run under Local, but production code should not assume that environment.

WordPress loads `interior-design-lead-manager.php`, which requires static classes and boots them on `plugins_loaded`. Activation performs schema installation, schedules jobs, and adds roles. Normal requests also run versioned, idempotent schema/backfill checks; login requests intentionally skip the runtime.

## Automated tests

Run the fast, dependency-free behavioral suite for every change:

```bash
php tests/run.php
```

For schema, REST, lead-persistence, MCP, or quotation-persistence changes, also run the opt-in native suite against a disposable MySQL server:

```bash
IDLM_TEST_DB_USER=test_database_user \
IDLM_TEST_DB_PASSWORD=test_database_password \
IDLM_TEST_DB_HOST='localhost:/path/to/mysqld.sock' \
php tests/integration/run.php
```

CI runs the fast, native, HTTP and runtime prerequisite suites across the target PHP 7.4/8.1/8.4 and WordPress 6.6/latest matrix. Keep scheduler, authorization, schema, secret migration, integrity, and semantic print contracts in the fast suite when changing those boundaries.

Phase 8 read-only HTTP load measurements use `tests/performance/run-load-qualification.php`. It is dry-run by default and requires a non-production WordPress environment, explicit execution confirmation, and a dedicated benchmark user. Follow [LOAD-RESILIENCE-QUALIFICATION.md](LOAD-RESILIENCE-QUALIFICATION.md); do not run it against production or treat unsigned provisional thresholds as release approval.

Phase 9 release packaging uses `tests/release/build-release.php`. Run it without flags for a read-only preflight. A local dirty artifact requires the exact build confirmation plus `--allow-dirty`, remains rehearsal-only, and must not be deployed. CI waits for the complete PHP/WordPress matrix before producing the clean-revision ZIP, checksum, and manifest, then `tests/release/verify-candidate.php` independently binds that candidate to the reviewed revision and emits pending staging gates. See [PRODUCTION-RELEASE.md](PRODUCTION-RELEASE.md).

The native runner derives the WordPress root from the plugin location unless `IDLM_TEST_WP_ROOT` is supplied. It accepts only newly created `idlm_test_*` databases and drops them after the run. Full safety and coverage details are in [`tests/README.md`](../tests/README.md).

## Coding patterns

- Quotation item/section parsers accept unslashed domain rows. HTTP controllers call `wp_unslash()` once before the protected quotation facade; the calculator sanitizes retained text and validates shape/numbers without another unslash. Direct domain callers must preserve literal backslashes. [REL-007 regression evidence](AUDIT-REMEDIATION-PACKAGE-QUOTATION-TEXT-SLICE.md).

- Prefix with `IDLM_`/`idlm_`, guard PHP files with `ABSPATH`, and use WordPress APIs/coding conventions already present.
- Static service methods and direct `$wpdb` are the existing style. New code should isolate responsibilities where possible without breaking public names.
- Keep shared CRM asset changes in `IDLM_Admin_Assets`, frontend shortcode/workspace routing in `IDLM_Admin_Shortcodes`, and public compatibility entry points in `IDLM_Admin`.
- Extracted page templates live under `templates/admin/`, receive one explicit context array through `IDLM_View`, and must not perform request parsing or mutation.
- Sanitize → validate domain value/authorization → persist with prepared SQL → escape at render.
- Use `current_time('mysql')`/WordPress timezone for stored CRM times; use `wp_timezone()` for date calculations.
- Route lead writes through `IDLM_DB`; log material lifecycle changes with `IDLM_DB::log_activity()`.

## Adding functionality

**Feature:** identify its domain owner first. Put lifecycle invariants/data mutation in a focused service or `IDLM_DB`, thin transport/UI code in its handler, and invoke through an existing compatibility method. Review [FLOWS.md](FLOWS.md) for side effects.

**Shortcode:** register through the established facade callback, coordinate frontend rendering in `IDLM_Admin_Shortcodes`, enforce login/capability before rendering, return buffered/escaped markup, enqueue only its `IDLM_Admin_Assets` context, and keep handler names and HTML selectors stable.

**REST endpoint:** register on `rest_api_init`; use a real `permission_callback` where possible. Define methods/arguments, authenticate before reading the body, sanitize/validate, return `WP_REST_Response`/`WP_Error`, and document the route in [INTERFACES.md](INTERFACES.md). Never add it to the authentication-bypass filter casually.

**AJAX endpoint:** register only `wp_ajax_idlm_*` unless anonymous access is explicitly designed; verify a dedicated/shared nonce first, check capability, then object access. Return `wp_send_json_*`. Add the action to the client only after localizing the nonce/URL.

**Database column/table:** change `IDLM_DB::create_tables()` or `IDLM_Quotation_Schema::install()` behind `IDLM_Quotations::install()`, increment only the corresponding schema version, use `dbDelta()`, index real query patterns, and add an idempotent bounded backfill. Test activation and upgrade with existing rows. Add explicit cleanup to repository reset/delete logic because there are no foreign-key cascades.

**Setting:** use an `idlm_` name, capability + nonce protected save, type-specific sanitization, a safe default, and server-side retrieval. Do not send secrets to the browser or diagnostics. Group it with an existing settings array when that reduces option sprawl.

**JavaScript/CSS:** register through the relevant module with `IDLM_VERSION`, scope selectors under `.idlm-*`, preserve `idlm_vars`/`idlmQuoteData` contracts, and test both wp-admin and shortcode contexts. Do not edit `assets/js/chart.js` manually.

## Backward compatibility

Public contracts include class/function names, hooks, capabilities, shortcodes, AJAX action names and payloads, REST namespaces/routes/authentication, option keys, table/column names, stored state literals, JS globals/selectors, and scheduled hook names. Preserve them or add adapters/migrations. Never delete legacy database columns merely because current UI no longer uses them.

## Verification checklist

1. Inspect `git diff` first and preserve unrelated/user changes.
2. Run `php -l` for every PHP file changed and ideally all plugin PHP files.
3. Search registration and call sites for affected hooks/actions/methods/options/tables.
4. Verify nonce, capability, object access, allowed values, prepared SQL, escaping, and error responses.
5. Exercise the affected wp-admin page and front-end shortcode; check browser console/network and responsive/print presentation where relevant.
6. For lifecycle work, test new, repeat/duplicate, qualified, unqualified, owner change, Won/Lost, and rollback/error paths; inspect activities/tasks and queued hooks.
7. For integrations, use test credentials/payloads and confirm audit logging without exposing secrets.
8. For migrations, test fresh activation and upgrade on a database copy; confirm idempotence.
9. Keep plugin header, `IDLM_VERSION`, `readme.txt` stable tag, `CHANGELOG.md`, and `docs/CHANGELOG-DEV.md` aligned. Attribute author/builder entries to Shashi.

The current debt status and boundaries are recorded in [TECH-DEBT.md](TECH-DEBT.md).
The completed admin facade release matrix is recorded in [ADMIN-REFACTOR-VERIFICATION.md](ADMIN-REFACTOR-VERIFICATION.md) and should be repeated when shared callbacks, selectors, or asset contexts change.
Production and scalability work must follow the baselines, phase order, compatibility contracts, and stop conditions in [PRODUCTION-SCALABILITY-PLAN.md](PRODUCTION-SCALABILITY-PLAN.md).

## Physical core migration contract

Builder: Shashi. `IDLM_DB::init/create_tables`, `IDLM_Routing_State::install` and `IDLM_Quotation_Schema::install` return `true` or `WP_Error`; bootstrap/activation must check the result before registering CRM jobs/handlers. `IDLM_Schema_Migration` verifies canonical declarations, serializes migration steps and publishes markers only after durable success. Do not wrap DDL in an assumed rollback transaction or auto-convert existing engines. Bounded duplicate-attribution progress resumes on subsequent requests. Native `integration/schema-migration-contracts.php`, fast `schema-migration-contracts.php` and actual `integration/schema-http-cases.php` run through the existing suite commands/CI. See [CORE-007 policy/evidence](AUDIT-REMEDIATION-SCHEMA-SLICE.md).

## Lead aggregate extension contract (1.102.50)

Shashi: use the existing `IDLM_DB` facades. Required lifecycle writes must return checked results under `IDLM_Lead_Transaction`; lock current state before validation. Do not call canonical mutations from a foreign quotation/third-party transaction: they refuse that ownership. Nested work is permitted only beneath the checked owner, as used by private-file migration. Persist whitelisted `IDLM_Lead_Transaction::defer()` hook intent before commit and make consumers safe for at-least-once replay. Preserve old argument types; never put credentials in scheduler arguments. The outbox sweeps 25 due rows with checked session claims and bounded backoff; recurring scheduling requires normal cron execution. [Native fault/worker evidence and remaining qualifications](AUDIT-REMEDIATION-LIFECYCLE-SLICE.md).

## CORE-006 task repair verification — 2026-10-07

Shashi: `php tests/run.php`, native `tests/integration/run.php` and actual HTTP `tests/integration/quotation-http.php` include task-plan repair children. Use only a new owned `idlm_test_*` database/socket. Native cases alter the disposable task table to rehearse additive 1.3.7→1.3.8 upgrade, inject required faults, synchronize workers with bounded busy retries and check 101-lead cursor progression and old backup normalization. [Executed environment, results and limits](AUDIT-REMEDIATION-TASK-PLAN-SLICE.md).

## Identity-review extension contract

Shashi: preserve the review allowlist and terminal decision rules; use `IDLM_Lead_Lifecycle_Service::resolve_inbound_review()` for manager decisions. Extend core schema/backup normalization/validation and whole-row erasure together when adding held data. Keep `IDLM_Identity_Review::nonce_action()` recovery-bound. Tests run in the standard fast/native/HTTP suites. [CORE-008 report](AUDIT-REMEDIATION-IDENTITY-REVIEW-SLICE.md).

## Bounded retention and reads (`1.102.55`)

Shashi: retention runs one checked 100-row batch with transactional `idlm_retention_progress`, recovery/session fencing, fair target rotation, continuation hook `idlm_continue_sensitive_retention`, and operational failure status. Task responses use 50-card signed keysets and scoped totals (`counts_only` skips cards); queue qualification history reads 100 dates at a time. WhatsApp filters/counts stay in SQL; compatibility ID helpers page 100 by `after`. Lead CSV stages 100-row reads, capped at 10,000 rows/32 MiB/20 seconds of application work, before headers. Core/routing/quotation schemas unchanged. [Policy, verification, API details and remaining production gates](AUDIT-REMEDIATION-BOUNDED-WORK-SLICE.md).


## Quotation migration contract (1.102.57)

Shashi: edit `IDLM_Quotation_Schema::schema_statements()` for physical declarations. Preserve its `true`/`WP_Error` facade return contract and pending/ready status; check the result at activation/boot. Verify every required read/write and data-phase postcondition before publishing its marker. Never start another transaction inside a phase; legacy numbering reserves inside the existing transaction. Physical DDL is restartable, not rollbackable. Native `quotation-migration-contracts.php` includes interruption, two-session contention, rollback and old backup cases; HTTP cases exercise actual boot 503/private notice/retry. [Scope and qualification](AUDIT-REMEDIATION-QUOTATION-MIGRATION-SLICE.md).


## Historical item classification contract (1.102.58)

Shashi: quotation schema `2.5.2` adds `classification_source`. Never infer historical `is_wood` from mutable catalogue rows; retain unknown legacy/default values with conservative provenance. The calculator derives new provenance, copies preserve it, and backup inventory/normalization/validation must change together. `quotation-classification-contracts.php` runs in fast/native suites; `quotation-classification-http-cases.php` runs through the existing actual HTTP runner. These fixture checks are placed alongside backup cases, before later intentional integrity damage. [Policy and evidence](AUDIT-REMEDIATION-QUOTATION-CLASSIFICATION-SLICE.md).


## Quotation commercial history (1.102.59)

Shashi: `normalize_revision_lifecycle()` now verifies relationships and preserves commercial data; lifecycle receipt is 1.1.0 and schema 2.5.3 with unchanged columns. Reuse `IDLM_Quotation_Policy::is_editable_draft()` at mutation/UI boundaries and keep live authorization plus quote locking. Preserve commercial evidence, never reconstruct erased pointers/dates by guessing. Existing standard fast/native/HTTP suites include the history modules; 296 / 219 / 326 cases pass locally. Complete 2.5.2 backups remain supported. [Policy, named evidence and limitations](AUDIT-REMEDIATION-QUOTATION-HISTORY-SLICE.md).


## Quotation write transaction contract (1.102.60)

Shashi: Use IDLM_Quotation_Repository::begin_checked_transaction() for save, working_copy, catalogue or number scope before writes. It checks ownership, next-transaction isolation, metadata pins and current engines. Consume true/WP_Error; never nest a standalone reservation inside a save/migration. reserve_quote_number(..., false) rechecks sequence metadata under its existing owner. Keep new aggregate dependencies in the scope manifest; do not cache engine checks or auto-convert engines. [Scope, executed checks and limits](AUDIT-REMEDIATION-QUOTATION-ENGINE-SLICE.md).

## Quotation numeric contract (1.102.61)

Use checked calculator array-or-WP_Error results at every request boundary. Normalize ordinary decimal text half up to integer hundredths before area/price/percentage calculations. Rates and all totals including GST/round off fit 9,999,999,999.99; dimensions/quantity fit 999,999,999.99, minimum billable quantity .01. Reject negative/exponent/nonfinite/over-range input; 200 submitted rows and 50 sections. Autosave refuses with 422 and preserves existing work. Host max_input_vars/post limits must accommodate the full editor form; minimal 200-row test requests do not qualify host transport. [Numeric policy and qualification](AUDIT-REMEDIATION-QUOTATION-NUMERIC-SLICE.md).

## Quotation document contract (1.102.62)

Shashi: `IDLM_Quotation_Document::capture()` validates and sanitizes current contractual settings and allocates payment cents; `read()` validates saved versioned JSON and distinguishes unknown from invalid. Save/autosave retain source revision currency, never posted/global currency. Save captures current terms for the new revision; editor previews this policy. Render/export never use live contractual settings for older rows. Branding and paper remain live. Register new classes in production and test bootstraps. [Tests, upgrade/backup policy and remaining PDF qualification](AUDIT-REMEDIATION-QUOTATION-DOCUMENT-SLICE.md).


## Quotation paper contract (1.102.63)

Shashi: use `IDLM_Quotations::normalize_paper_size()` for setup, print and PDF. Keep presentation paper live and historical contractual snapshots unchanged. Do not introduce a conflicting Dompdf `@page` size. `php tests/integration/quotation-http.php --paper-only` uses the same disposable environment variables/cleanup as the standard HTTP suite; Poppler `pdfinfo` and `pdftotext` must be available (or set `IDLM_TEST_PDFINFO` / `IDLM_TEST_PDFTOTEXT`). Optional `IDLM_TEST_PAPER_EVIDENCE_DIR` retains synthetic downloads. [Scope and evidence](AUDIT-REMEDIATION-QUOTATION-PAPER-SLICE.md).

## Feature extension checklist — M6

Builder: Shashi. Before extending a feature:

- Identify the transport, permission/object policy, domain and persistence owners. Preserve existing public facade methods, registrations, priorities, arguments, selectors and errors when extracting a responsibility.
- Validate typed domain inputs after authentication and once-only request unslashing; keep prepared persistence and lifecycle/transaction invariants at the established owner.
- Review additive migration/restart, deletion/retention, private-file cleanup and backup/restore implications together; record explicitly when a feature adds no stored state.
- Keep external effects behind the existing after-commit intent/queue boundary. Characterize failure, retry, acknowledgement, credential access and erasure; do not send inside a lead transaction.
- Reuse shared admin/shortcode assets and accessible controls; test current actor/object scope and output semantics. Document when an internal extraction has no UI changes.
- Verify boot and activation load order, old facade callability, ordinary and failure behavior, and affected native/HTTP/browser contracts. Lint changed PHP and bind package checks to actual artifact bytes. Record remaining qualification honestly.
- Update the feature/project maps and scoped graph with the concrete ownership change, rather than treating reduced file size as completion.

Qualified-handoff mail uses `IDLM_Handoff_Notifications` behind the existing integration callback. New after-commit behavior must use the transaction/outbox owner, retaining its failure signal so rejected mail remains retryable. [M6 slice](AUDIT-REMEDIATION-HANDOFF-NOTIFICATIONS-SLICE.md).

Administrator platform connection diagnostics belong in `IDLM_Admin_Connection_Controller`, behind the existing two facade methods. Keep nonce/admin checks in the controller before credential access, use the protected request wrapper for provider calls, and preserve response messages/client fields during extraction. The Google connection test checks configured credential format locally; it does not prove external Google acceptance. Builder: Shashi. [M6 evidence](AUDIT-REMEDIATION-CONNECTION-CONTROLLER-SLICE.md).

## Telemetry request ownership — M6

Builder: Shashi. Extend telemetry transport through `IDLM_Admin_Telemetry_Controller`; preserve both public facade layers and shared request fields/guards. Keep normalization/persistence/pruning in `IDLM_Telemetry`. Production and fast-test bootstraps load the controller before `IDLM_Admin_Ajax`. Run `php tests/integration/quotation-http.php --telemetry-controller-only` with the documented disposable `IDLM_TEST_*` environment for four actual HTTP groups covering denials, stored client errors, bounded downloads and clearing. No view/client selector changes are part of this extraction.

## Activity request ownership — M6

Builder: Shashi. Extend timeline/comment transport in `IDLM_Admin_Activity_Controller`, following the existing `IDLM_Admin_Lead_Reads` inheritance pattern. Keep public `IDLM_Admin` and `IDLM_Admin_Ajax` callbacks callable. Reuse the inherited access/presenter helpers and `IDLM_DB::log_activity()`; do not duplicate permission or escaping rules. Production and fast-test bootstraps load this controller after `IDLM_Admin` and before `IDLM_Admin_Ajax`. Run `php tests/integration/quotation-http.php --activity-controller-only` with disposable `IDLM_TEST_*` variables for eight actual HTTP groups; the accumulated suite also includes them. Preserve the timeline's 100-row and recent-comments five-row bounds, reserved activity types and existing duplicate/error behavior. [Evidence](AUDIT-REMEDIATION-ACTIVITY-CONTROLLER-SLICE.md).
