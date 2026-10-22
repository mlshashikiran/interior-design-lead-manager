# Developer guide grounded in the reviewed candidate

Author: **Shashi**. Candidate `1.102.42`, reviewed 2026-10-06 from base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385` plus existing dirty changes/untracked runtime dependencies. This guide explains the current architecture and safe change boundaries. It is not a release approval or a statement that existing APIs enforce every required invariant.

Start with [data/state/flow map](DATA-AND-FLOWS.md), [request contracts](CONTRACTS.md), [core signature inventory](CORE-SOURCE-CONTRACTS.md), and the [core](CORE-REVIEW.md), [integration/security](INTEGRATION-SECURITY-REVIEW.md) and [quotation](QUOTATION-REVIEW.md) defect registers. Those reports distinguish native execution, mocked execution and source-only review; the parent index/verdict consolidates coverage and approval evidence.

## Runtime architecture

The bootstrap is procedural; implementations are prefixed static `IDLM_*` classes, without namespaces or a runtime autoloader for first-party classes. `interior-design-lead-manager.php:25–76` explicitly includes every component. Activation installs schemas, schedules background work and grants roles; plugins_loaded boots authentication and then runtime, while wp-login exits before CRM DB/integration initialization (`:79–105,224–256`). Schema versions are independent of plugin release version. Optional Action Scheduler is detected at runtime; fallback differs between job families rather than being universally WP-Cron.

There is no first-party npm/Composer build pipeline. The current dirty candidate **does** bundle a new Dompdf dependency tree and runtime exporter; older `docs/DEVELOPMENT.md` statements that there are no Composer dependencies should not be read as no third-party runtime code. Packaging must include the reviewed untracked dependency files and required licenses; do not infer a clean candidate from tracked Git files alone.

| Responsibility | Canonical owner | Compatibility/transport boundary |
|---|---|---|
| Lead identity/state/creation/update | `IDLM_Lead_Lifecycle_Service`, reached via `IDLM_DB` | REST mapped inbound, admin AJAX/manual/import, background routing exceptions |
| Schema/tasks/compliance/report events | `IDLM_DB` | Lifecycle caller, scheduler, admin task operations |
| Read/mutation lead policy | `IDLM_Lead_Access_Policy` | Admin facade delegates; concrete handler must call correct predicate |
| Assignment pools/CAS/fallback | `IDLM_Integrations` assignment methods, `IDLM_Routing_State` | Inbound handler, manager quick/bulk edit, scheduler/admin fallback |
| Lead deletion/reset/orphan audit | `IDLM_Lead_Deletion_Service` | Admin AJAX/CLI trusted callers; service does not authorize itself |
| Reporting/cache/pagination | `IDLM_Dashboard`, `IDLM_Dashboard_Read_Model`, read cache/cursors | Authenticated dashboard/list handlers and renderers |
| Quote policy/transactions/totals | Policy, controller, repository, calculator, schema | `IDLM_Quotations` compatibility facade, admin-post/AJAX/shortcode |
| Customer documents | Quotation renderer/exporter | Nonce/cap/exact revision download and print handlers |
| Provider authenticity/send queues | Integrations, CAPI, audiences, Interakt, batch | REST permission/callback and scheduled hook consumers |
| Profiles/roles/credentials/settings | Dedicated account/users/settings/auth/secret/security classes | Facade admin-post, guarded settings/account UI |
| Presentation | Admin presenter/renderers/shortcodes/assets, guarded templates | Stable facade callbacks, selectors and localized JS contracts |
| Recovery/privacy/operations | Backup, retention, integrity CLI, telemetry/health | Administrator utilities/operational tooling |

`class-admin.php` and `class-quotations.php` are compatibility facades. Preserve public callback names, action/shortcode registrations, capability names, stored literals, options, table fields, scheduled hooks, JS globals/selectors and payload keys. Put new implementation with the domain owner; don't grow facade methods merely because a legacy callback lives there. Source modules often have trusted public static seams with no independent permissions; public PHP visibility is not an HTTP authorization guarantee.

## Follow a request before changing a rule

For any feature, map registration→authentication→capability/role→object scope→input/domain validation→canonical operation→checked persistence→side effects→response. The [contract inventory](CONTRACTS.md) includes dynamically assembled registrations; the core task queue additionally dispatches `operation=reschedule/cleanup_stale` beneath `idlm_get_rep_tasks` (`class-admin-ajax.php:3218–3230`). Changing a callback alone may leave its facade, nested service or alternate transport inconsistent.

Lead drawer example (`class-admin-ajax.php:2531–2635`): shared nonce, recognized CRM access, scalar parsed fields, positive lead/current mutation access, tagged file binding check, pre-sales/commercial value validation, omitted-field preservation, removal of structured quote summary fields, canonical lifecycle update, JSON result. `is_admin()` means context, not permission. Nonces are reusable CSRF tokens; UUID/save keys supply a different idempotency contract.

Inbound example (`class-integrations.php:578–658`, lifecycle `:14–85`): explicit permission callback validates scoped header credential/rate limit before mapping; handler repeats checks. Mapped contact/source/tracking inputs enter canonical upsert; one canonical result enriches, multiple candidates retain review, created result routes. Don't route repeats, create tasks for pending review, accept query credentials, or add a new path to REST cookie-auth bypass without a designed scoped policy. Webhook replay/body/rate/provider semantics still have defects noted by the integration reviewer.

Quotation example: dedicated nonce/caps/live-lead scope, source/current Draft verification under row lock, calculator normalization, repository aggregate transaction, lead commercial projection, after-commit activity/cache/autosave cleanup. New quote creation must reject a foreign revision ID; current candidate fails that boundary (QUO-001). Export authorization already binds requested revision to quote and live lead. Never use autosaves as customer documents.

## Canonical lead APIs and their limits

Use `IDLM_DB::insert_lead()`, `upsert_inbound_lead()` and `update_lead()` for lead lifecycle writes (`class-db.php:1095–1114`). They preserve coupled stage/quality/handoff/timestamps/activity/tasks/conversion/cache behavior that a raw lead UPDATE bypasses. `get_last_validation_error()` describes domain rejection; inspect boolean/result fields and propagate errors. Caller must sanitize permitted columns and authorize the acting user.

These APIs currently have unchecked dependent writes/no aggregate transaction (CORE-004/005). Using the facade is necessary for compatibility, but does not make a failed task/event/touchpoint write atomic. A repair should establish transaction boundaries for primary state/history/tasks and defer external hooks until durable completion, with an outbox/recovery path for delivery. Do not declare success solely because the primary row updated. Audit probes demonstrate these failures; retain them as regressions when fixing behavior.

Explicit direct-write exceptions exist: routing compares expected owner atomically; DB installers/backfills reconcile historical state; quotations project quote_value/updated_at inside their aggregate transaction without stage/conversion hooks. Review every exception's timestamps/events/tasks/cache semantics before adding another. A direct quotation summary write is not permission to use raw SQL for arbitrary CRM fields.

Identity locks sort normalized phone/email/external keys (`lifecycle:88–128`). Exact external association precedes contact match; single identity match currently accepts changed other contact, multiple candidates enter review. There is no review resolver. Choosing stricter identity behavior requires explicit business policy, historical compatibility and retry/ambiguity tests, rather than interpreting stale docs as already enforced behavior.

State enumeration does not enforce linear pipeline transitions. Qualified has detail/budget/plan-remark gates; Won/Lost/Quote Shared have independent commercial gates. Reverse Won clears current close timestamp but retains historical events. Lost cancels tasks; reopen doesn't restore them. Owner changes currently transfer tasks only for formerly Qualified leads, creating the confirmed stale task PII defect. Adding a transition requires examining all these coupled effects and integration consumers.

## Authorization changes must cover reads, writes and revocation

Use `has_rep_access`/required capability for entry and `can_access(id,false)` for current-owner lead mutations; `can_access(id,true)` is a view predicate including configured read-all and historical pre-sales access (`access-policy:13–103`). Keep those meanings distinct. Current recognized-role access bypasses removed access capability and defaults many built-in roles to global read scope (CORE-001); a fix needs opt-in defaults and deliberate migration.

Quotation policy is not presently a separate mutation-ownership policy: read-all lead scope can authorize save/autosave, while list scope uses original quote owner (QUO-002/003). A new quote write must establish mutation authority separately; reusing a view predicate is insufficient. Fix all save/autosave/list/detail/export/picker surfaces together and test after ownership, roles, disabled metadata and caps change.

Visibility tests must include direct endpoint calls, task history, cached results, cross-lead/revision substitution, and saved public URLs. Hiding a UI button or refreshing a drawer doesn't revoke server authority. The queue's stale task-owner OR filter exposes phone/email after drawer denial (CORE-011); every serialized lead field needs a live-lead visibility decision. Signed cursors bind user/filter/scope/sort/expiry and still require current nonce/policy on every page.

Disabled-user enforcement belongs in assignment as well as login. Current manual assignability/pools ignore disabled meta (CORE-009). Capacity must reserve/re-evaluate per successful assignment and handle concurrent intake/manual/fallback operations; refreshing one qualified path doesn't fix unassigned batch overflow (native CORE-010).

## Persistence/schema and destructive work

Every custom table/column/index is declared in [schema evidence](evidence/custom-schema.json), sourced from core/routing/quotation installers. Add schema only there, increment matching independent schema version, preserve existing data/columns, use idempotent bounded backfills and verify actual table/column/index/engine results before advancing marker. Current marker advancement is not fail-safe. A successful repeated dbDelta test does not prove permission-denied or partial-upgrade recovery.

No FK cascades protect integrity. When adding a table/reference, update full backup, lead deletion, reset manifest, orphan auditing, retention/erasure, restore relationship validation, release health diagnostics and tests. Build independent inventory assertions rather than only iterating a manifest: existing reset tests missed a table because both code and assertion used the same incomplete list. Native CORE-008 proves pending-review PII survives reset.

Use the coordinated core deletion/reset service for CRM operations. Its internal methods are trusted and require caller admin permission/nonce/confirmation; they do not supply authorization themselves. Preserve nonrecycled lead IDs for stale job safety. Quote sequence deletion permits reused numbers and needs a decided business policy. Normal deletion does not clear every async payload; reset/restore cancellation happens after commit and cannot recall running provider sends.

Uploads currently use standard public WordPress media URLs despite field-specific intake checks. Private retrieval needs storage plus authenticated current-object download, legacy migration and file deletion/recovery policy; attachment lead metadata alone doesn't enforce HTTP privacy. Backup excludes binary media/users; data JSON is not a complete recoverable site backup. CRM delete/reset and log retention are not a complete WP personal-data erasure implementation.

Retention must be bounded/resumable, inspect errors and avoid overlapping heavy purge jobs. Fixed7-day CAPI cleanup overrides configurable integration retention and can erase prior-success evidence. Preserve enough idempotency/audit information independently of payload retention; don't solve PII retention solely by deleting the only replay guard.

## Integration and secret extension

Use protected settings/environment-backed scoped `IDLM_Secret_Store` for supported secret scopes and explicit authentication in REST permission or documented callback HMAC. Provider callbacks should authenticate raw body before parsing and reject malformed/oversized/deep inputs. Redact key-value contact/credential fields and separately control arbitrary error/trace strings; redaction does not cover secrets hidden under unrelated keys or serialized text.

A scheduled hook must define payload shape, current enabled/credential/owner/state revalidation, idempotency key/send claim, overlap lock, failure result, retry delay/ceiling, cancellation and terminal-state cleanup. Current Interakt ordinary and batch behavior differs; CAPI lacks automatic delivery retry; audiences lack chunk/claim/retry/removal semantics. Do not copy a current fallback and advertise guarantees it does not provide. Batch dedup enqueue is not provider exactly-once delivery after crash.

Do not broaden MCP custom SQL validation by adding another regex exception. INT-001/002 demonstrate table/column boundary failures. Public custom query needs parsed/table+column scoped policy, PII grants, bounded execution, host authority and audit. Database output row limits do not prevent expensive SQL execution; stdio has a different host boundary from HTTP. Keep production fail-closed/emergency disable.

## Presentation and document changes

Templates under `templates/` receive explicit context through `IDLM_View`; handlers/services own parsing and mutations. Shared asset registrations/enqueues belong to admin assets or quotation module. Preserve `.idlm-*` style isolation, design variables, facade callbacks, `idlm_vars`/`idlmQuoteData`, selectors and admin/shortcode parity. Current dirty changes lazily load activity/WhatsApp drawer tabs; UI regression needs latency/error/stale drawer sequencing tests, not only source selector checks.

Sanitize input for stored purpose, validate allowed domain values, prepare SQL variables and escape at output by HTML/attribute/URL context. For CSV/XLSX normalize untrusted cell formulas separately; fputcsv quoting is not formula protection. Catalogue exports handle this, lead CSV does not (CORE-003). Specify fputcsv escape argument for PHP8.4 compatibility; debug display can corrupt binary/tabular downloads.

Customer print/PDF/CSV/XLSX must use exact persisted revision and snapshots. Revisions currently still depend on mutable company/payment/currency settings and installer classification backfill (QUO-005/006). Template edit can change historical document output; decide immutable snapshot versus live branding policy before promising reproducible customer documents. Verify A4/Letter, pagination, optional-row differences, font licensing, remote resource restrictions, MIME/filename/no-cache headers and error fallback on the packaged runtime.

## Verification and evidence workflow

Inspect existing dirty changes before edits; preserve them. Read relevant AGENTS and targeted maps. Graphify is navigation for unfamiliar cross-module impact, followed by source verification; the existing snapshot can be stale. Use one model for small work and bounded independent reviews only where permitted. Attribute engineering documentation to Shashi.

Use the narrowest meaningful check first. Fast doubles do not boot WordPress or reproduce real isolation/unique/FK/server behavior; some tests only inspect strings. Native suite boots a new disposable `idlm_test_*` database, refuses pre-existing names and uses isolated content/uploads. Always mock/intercept email/HTTP and disable cron spawning when adding safe audit cases. Define AJAX mode/capture die handlers so exiting request functions do not bypass fixture cleanup. Never use configured site credentials/database for test convenience.

```bash
php -l path/to/changed.php
php tests/run.php
php tests/inbound-task-integrity-contracts.php
php tests/interakt-action-scheduler-contracts.php
```

Use actual changed-file paths in syntax command. For core/schema/API/persistence/concurrency, run relevant native cases on an explicitly disposable server:

```bash
IDLM_TEST_WP_ROOT=/path/to/disposable-wordpress \
IDLM_TEST_DB_USER=disposable_test_user \
IDLM_TEST_DB_PASSWORD=disposable_test_password \
IDLM_TEST_DB_HOST='localhost:/path/to/disposable-mysql.sock' \
php tests/integration/run.php
```

The audit's new core fixtures and machine results are linked in CORE-REVIEW; use the native failure probes only with the same isolation rules. PHP process exit0 indicates fixture execution completed, not all safety expectations passed: results JSON contains PASS/FAIL observations. The parent owns combined counts. CI defines PHP7.4/8.1/8.4 with WordPress6.6/latest; one PHP8.4/native run is not that complete matrix.

Meaningful lifecycle regression cases include required-write failures, duplicate/replay/race, two stale editors, every ownership state, changed roles/caps/disabled state, token substitution, negative dates/numbers, Won/Lost reversal, retention expiry, restart/overlap/deactivation/cancellation and representative larger dataset. For UI exercise actual admin+shortcode, desktop/mobile, keyboard/focus/labels/contrast, visible failure recovery, network/console/PHP warnings and document outputs. Mark PASS only from asserted execution; BLOCKED/NOT RUN remains explicit.

## Troubleshooting by boundary

| Symptom | Inspect first | Do not assume |
|---|---|---|
| Save succeeds but dashboard/history differs | Primary lead row versus state-events/activity; dependency write errors and report basis; CORE-005 probe | Cache flush restores missing immutable history |
| Lead absent/stuck in handoff | Canonical/review result, lead owner/handoff status, disabled-user pool, working threshold/studio/cap; task/event rows | All pending reviews have a resolvable UI or every routing success created tasks |
| Reassigned user still sees contact | Queue task owner versus live owner, quote original-owner list, read scope/cache and public media URL | Drawer denial proves global access revocation |
| Provider event not delivered | Enabled/map/credentials/current state, actual backend/job args, audit log, batch status/attempts and scheduling return | Accepted queue equals sent; HTTP success implies exactly-once; all failures auto-retry |
| Old quote changed/print differs | Persisted revision/item versus live company/payment/currency and installer wood mutation, revision binding | A revision row means complete immutable rendered document |
| Restore/reset loses or retains unexpected data | Independent21table inventory, JSON relationships/users/media exclusions, reset omissions, retention after restore, job options | Transaction protects external files/messages or manifest is complete |
| Schema marker current but runtime SQL fails | Physical SHOW CREATE/COLUMNS/INDEX/engine and installer errors | Version option proves successful installation |

Diagnostics themselves are sensitive; use synthetic data and aggregate-safe evidence, avoid exporting customer rows/credentials in reports or logs. MCP/telemetry failures have their own authorization/audit boundaries; an empty diagnostic result does not prove no failure occurred.

## Packaging, release and remediation dependencies

Keep header/IDLM_VERSION/readme stable tag aligned. For release-ready changes update CHANGELOG.md and relevant developer changelog with Shashi attribution; local audit documentation does not silently bump release behavior/version. Review `.distignore`, runtime dependencies/licensing, installation/disclosures/support/privacy/attribution/update requirements and compatibility metadata against current distribution policies.

Release tooling preflight is read-only by default:

```bash
php tests/release/build-release.php
```

An explicitly confirmed dirty build is rehearsal-only; use the repository's documented build/verification workflow, exact source manifest/ZIP/checksum and required CI attestation for a releasable clean candidate. Reopen/rebuild for reproducibility, install the exact ZIP in disposable WordPress and run Plugin Check/native/UI/upgrade/rollback against that artifact. Do not deploy from an unreviewed working checkout or equate clean packaging with functional/security approval.

Remediation order: close arbitrary quotation revision/MCP and ownership/file/role privacy boundaries; establish checked lifecycle/migration durability; fix capacity/disabled routing/review/reset/recovery consistency; secure CSV and provider retry/cancellation/idempotency; then bound heavy work/align document snapshots/disclosures/docs. Retest by dependency, rebuild artifact after source changes and reassess all approval gates. A public READY verdict requires resolved material security/data-integrity failures and completed required checks; exceptions belong explicitly in parent verdict, not silently inferred by this guide.

## Source references and documentation reconciliation

Core source contracts provide exact line links/hashes for all reviewed core public methods. Complete schema declarations in DATA-AND-FLOWS provide types/indexes and source links. Integration and quotation reports give module-specific handler/persistence/retry/authorization evidence. Existing project maps remain useful indices but have stale task count, CLI, ordinary Interakt fallback, PDF/dependency, conflict-policy and snapshot/permission descriptions called out in those reports. Change focused docs after deciding intended behavior; don't copy a stale contract into tests merely to make documentation and implementation agree.
