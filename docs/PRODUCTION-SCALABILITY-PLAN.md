# Production Readiness and Scalability Plan

Author / Builder: Shashi  
Status: Approved staged plan; Phases 0 through 9 are in progress  
Prepared: 2026-08-30  
Planning release: 1.98.2  
Current implementation release: 1.102.88
Latest audit checkpoint: [CI-qualified production build 1.102.88](CI-QUALIFIED-BUILD-1.102.88.md). M6 COMPLETE; selected single-server M7 qualification and exact M8 build/promotion evidence verified. Eight remote matrix cells and the official package gate PASS. REL-001 IMPLEMENTED; 44 IMPLEMENTED / zero IN PROGRESS / zero CLOSED. Actual-host acceptance and deployment remain pending.
Baseline implementation: 1.98.1

## Objective

Move Interior Design Lead Manager from a verified local release candidate to an operationally safe, measurable, and horizontally deployable WordPress CRM without breaking its established integrations or user workflows.

This plan addresses two separate outcomes:

1. **Production readiness:** repeatable builds, CI, staging upgrades, backup/restore, hardened configuration, secrets, scheduling, monitoring, rollback, and controlled deployment.
2. **Scalability:** predictable response times as CRM data and concurrency grow, bounded background work, query/index efficiency, safe caching, modular request handlers, and multi-node infrastructure compatibility.

## Current evidence

Version 1.98.4 provides a strong functional baseline:

- Full PHP syntax checks pass.
- 70 fast behavioral contracts and 13 disposable native WordPress/MySQL tests pass, including real small-profile synthetic seed/verify/scoped-reset coverage.
- Six wp-admin pages and eight frontend workspace routes pass live selector, interaction, responsive, asset, console, and network checks.
- The admin facade is 432 lines and retains all 75 public compatibility methods.
- Core CRM data uses custom tables with primary and secondary indexes.
- Quotation saves use transactions, numbering is atomic, assignment uses a database advisory lock, and recurring work supports Action Scheduler with WP-Cron fallback.
- Security boundaries include nonces, capabilities, object-level access, scoped encrypted credentials, header authentication, rate limits, HMAC validation, redaction, and retention.

The remaining challenges are measurable rather than hypothetical:

- The current worktree must be committed and pass remote CI before release.
- Production upgrade, rollback, backup restore, cron, email, webhook, and credential operations have not been rehearsed against a production-like clone.
- No large-dataset, sustained-load, concurrency, or failure-recovery benchmark exists.
- Dashboard reads execute many aggregate queries per refresh without a shared cache or aggregate read table.
- Lead list metrics and overdue-handoff calculations perform additional reads, including loading qualifying timestamps into PHP.
- Custom-sort, pre-sales, and non-cursor page-number pagination still uses `LIMIT/OFFSET`, which becomes increasingly expensive on deep pages.
- Authenticated interactive traffic uses `admin-ajax.php`, which boots WordPress on each request.
- `includes/class-admin-ajax.php`, `includes/class-integrations.php`, `includes/class-db.php`, `includes/class-capi.php`, `includes/class-dashboard.php`, `assets/js/admin-app.js`, and `templates/admin/leads.php` remain large change hotspots.
- Multi-node deployment requires a shared database, shared media/object storage, coordinated job runners, and shared cache semantics.
- The security register still identifies MCP execution scope, lead-upload policy, and trusted-proxy rate limiting as production decisions.

## Non-goals

- Do not redesign the CRM UI while tuning it.
- Do not rename hooks, AJAX actions, REST routes, shortcodes, menu slugs, capabilities, option keys, JavaScript globals, request fields, selectors, database tables, columns, or stable workflow values.
- Do not perform a wholesale namespace, Composer, framework, ORM, or frontend rewrite.
- Do not replace WordPress authentication or authorization.
- Do not add speculative indexes, caches, queues, or aggregate tables without baseline evidence and `EXPLAIN` output.
- Do not combine schema migration, security behavior, transport migration, and UI redesign in one release.

## Compatibility contracts

Every phase must preserve or explicitly version:

- All existing public PHP methods and WordPress callbacks.
- Authenticated AJAX names, REST namespaces/routes, methods, status codes, and response payloads.
- Nonce names, capabilities, object-level lead access, and role visibility.
- Shortcode echo/return behavior and workspace query routing.
- Form fields, option names, grouped-routing mirrors, encrypted secret storage, and environment constant support.
- Database table/column names, state labels, number sequences, lifecycle hooks, task effects, and audit records.
- Asset handles, dependencies, localization objects, DOM IDs/classes, and responsive behavior.
- Quotation calculations, transaction boundaries, numbering, revisions, print output, and autosave behavior.

## Provisional scale and service targets

Phase 0 must replace these provisional values with a signed-off business forecast. Until then they define a conservative benchmark envelope, not a production promise.

| Dimension | Provisional validation target |
|---|---:|
| Leads | 250,000 canonical rows plus duplicates |
| Activities | 2,000,000 rows |
| Tasks and touchpoints | 500,000 rows each |
| CAPI/audience/telemetry logs | 1,000,000 combined retained rows |
| Quotations | 100,000 quotes with revision/item graphs |
| Concurrent authenticated users | 50 active sessions |
| Inbound webhook traffic | 10 requests/second sustained, 50 requests/second short burst |
| Lead-list API | p95 below 1.5 seconds, p99 below 3 seconds |
| Lead mutation API | p95 below 750 ms excluding third-party delivery |
| Dashboard | p95 below 2.5 seconds cold and 1 second warm |
| Background queue lag | below 5 minutes during normal load |
| Error rate | below 1% excluding validated client errors |
| Data integrity | zero duplicate quote numbers, lost lifecycle events, or cross-user access leaks |

All timings must be measured at the server and browser, with database query count/time recorded separately.

## Phase 0 — Establish the production and performance baseline

Purpose: define the release target and make regressions measurable before tuning.

Work:

- Commit the complete 1.98.2 planning baseline and run the GitHub PHP 7.4/8.1/8.4 × WordPress 6.6/latest matrix.
- Define expected users, lead growth, activity growth, quotation volume, webhook peaks, data retention, deployment topology, and availability objectives.
- Add a sanitized deterministic data generator for leads, activities, tasks, touchpoints, logs, quotes, revisions, sections, and items.
- Add benchmark scenarios for dashboard, lead list/filter/sort, lead detail/activity, Kanban, quotations, webhook ingestion, imports, exports, CAPI retry, audience sync, and MCP queries.
- Capture baseline endpoint latency, database time, query count, memory, response size, PHP CPU, queue lag, and error rate.
- Capture `EXPLAIN` plans for the slowest and most frequent SQL shapes at small and target datasets.
- Record table sizes, index sizes, retention volumes, autoloaded option size, and cron/Action Scheduler backlog.
- Add a performance-results template that stores environment, dataset, command, thresholds, and before/after evidence without lead PII.

Deliverables:

- `docs/PERFORMANCE-BASELINE.md` with signed targets and first measurements.
- Reproducible seed and load-test tooling under `tests/performance/`.
- A ranked bottleneck list based on total time and user impact.
- A release checklist identifying production owner, rollback owner, and monitoring owner.

Exit criteria:

- Remote CI passes from a committed revision.
- The benchmark environment and dataset can be recreated.
- Every critical route has a latency/error/query baseline.
- No optimization phase starts without its before measurement.

Progress evidence (versions 1.98.4 and 1.99.7): Phase 0 includes `docs/PERFORMANCE-BASELINE.md`, an aggregate-only environment/capacity capture, an explicit critical-scenario manifest, deterministic PII-free small/expected/provisional-maximum dataset profiles, and the first guarded authenticated read-load runner under `tests/performance/`. Dataset mutation and load execution are dry-run by default, refuse production, require exact confirmations, and keep evidence aggregate-only. Target/owner sign-off, target-profile measurements with server-side query/resource signals, ranked bottlenecks, a committed revision, and remote CI remain pending.

## Phase 1 — Close production security and configuration gates

Purpose: make deployment defaults and exposed boundaries safe for real CRM data.

Work:

- Decide whether MCP is enabled in production. If enabled, add per-tool grants, per-key revocation metadata, stricter query execution/time budgets, audit attribution, and an emergency disable switch.
- Add field-specific extension, MIME, size, and ownership rules for lead floor-plan and attachment uploads; preserve WordPress media validation as defense in depth.
- Define trusted proxy handling for client IPs. Accept forwarded addresses only from configured trusted proxies; otherwise retain `REMOTE_ADDR`.
- Verify `WP_DEBUG_DISPLAY` is off in production, logging is access-controlled, environment type is production, and logs contain no credentials or contact payloads.
- Move deploy-time secrets to protected environment constants or a managed secret store where supported; rotate webhook, MCP, feed, Meta, Google, LinkedIn, and Interakt credentials during release.
- Document intentional uninstall behavior. CRM data must not be deleted on deactivation; permanent uninstall cleanup must require an explicit administrator policy and backup confirmation.
- Validate retention values against legal/business requirements and confirm pruning is bounded, observable, and resumable.
- Add security regression tests for the new upload, MCP, proxy, disable-switch, and uninstall-policy behavior.

Exit criteria:

- No credential is stored in source, localized JavaScript, URLs, diagnostics, or release artifacts.
- Uploads reject disallowed types and sizes before persistence.
- MCP is disabled or has approved least-privilege and execution limits.
- Proxy-based rate limiting cannot be spoofed by untrusted callers.
- Production debug, log, retention, and uninstall policies are documented and verified.

Progress evidence (version 1.99.0): Phase 1 has started with production-default MCP shutdown, an emergency disable switch, least-privilege tool grants, bounded payload/batch/query budgets, key rotation/revocation fingerprints, aggregate audit attribution, trusted-proxy-aware client IP resolution, and field-specific lead attachment extension/MIME/size/ownership rules. Production debug/log verification, remaining secret migration and rotation, bounded retention, backup-confirmed uninstall behavior, staging rehearsal, and final approval remain pending.

## Phase 2 — Optimize schema and critical query paths

Purpose: keep database cost bounded as rows grow.

Work:

- Use slow-query evidence and `EXPLAIN ANALYZE` where available to review dashboard, lead list, Kanban, tasks, logs, audience selection, CAPI lookup, webhook deduplication, and quotation list queries.
- Evaluate composite indexes matching actual equality/range/order patterns. Candidate shapes include, but are not approved until measured:
  - Leads: `(is_duplicate, created_at, id)`.
  - Restricted lead reads: `(assigned_rep_id, is_duplicate, created_at, id)`.
  - Workflow queues: `(stage, is_duplicate, assigned_at, id)` and `(lead_quality, handoff_status, is_duplicate, qualified_at, id)`.
  - Call queues: `(call_status, is_duplicate, callback_datetime, assigned_rep_id, id)`.
  - Tasks: `(assigned_rep_id, status, due_date, id)` and existing lead/status paths.
  - CAPI: `(lead_id, platform, event_name, status, id)` and `(status, created_at, id)`.
  - Logs: `(status, created_at, id)` where filtered operational views justify it.
- Remove redundant single-column indexes only after confirming no remaining query depends on them.
- Replace PHP-side overdue-handoff scans with a bounded read model or persisted/queryable deadline where business-working-hour rules can remain exact.
- Avoid wrapping indexed filter columns in functions when a normalized stored value or generated/indexable key can preserve behavior.
- Keep every schema change behind the independent schema version with idempotent `dbDelta()` installation and bounded backfills.
- Test fresh install, upgrade from a large prior dataset, interrupted migration recovery, and rollback compatibility.

Exit criteria:

- Target query plans avoid full scans where selectivity permits.
- Index write/storage cost is measured and justified.
- Critical p95 database time meets the signed Phase 0 thresholds.
- Migrations are idempotent, restartable, and do not require destructive downtime.

Progress evidence (version 1.99.1): Phase 2 has started with a production-refusing, read-only manifest and CLI capture for ten real dashboard, lead-list, handoff, Kanban, task, activity, CAPI, and quotation SQL shapes. It records normalized optimizer steps, full-scan/filesort/temporary signals, plugin index/cardinality inventory, and build/dataset/date metadata without exporting SQL text, parameter values, CRM rows, contacts, credentials, URLs, paths, or database names. The first local capture contained only 117 lead rows, so it is useful for tooling verification but explicitly insufficient to approve schema/index changes; target-profile endpoint timing and query-plan evidence remain required.

## Phase 3 — Build scalable read models and caching

Purpose: stop recalculating expensive shared views on every request while preserving role visibility.

Work:

- Introduce focused dashboard and lead-list read models behind the existing public callbacks.
- Consolidate lead-summary counts into the fewest safe aggregate queries and reuse the same filter/access specification as the table query.
- Add short-lived caches only for proven expensive, read-heavy results.
- Include date range, filters, normalized role scope, owner/user scope, vocabulary/settings version, and schema version in cache keys.
- Never share restricted representative data through a manager/global cache entry.
- Invalidate or version caches after lifecycle changes, assignment, deletion, imports, quotation value changes, settings changes, and retention work.
- Use cache stampede protection for expensive dashboard rebuilds.
- Support WordPress object cache when available and a database-transient fallback with identical correctness.
- If warm-cache targets still fail, design hourly/daily aggregate tables with source timestamps, rebuild commands, and reconciliation checks.

Exit criteria:

- Cached and uncached responses are authorization- and value-equivalent.
- Mutation tests prove invalidation for every affected aggregate.
- Warm and cold dashboard/list targets pass at the signed dataset size.
- Cache failure degrades to correct database reads, not stale or cross-user data.

Progress evidence (version 1.99.2): Phase 3 has started with an authorization-scoped dashboard read model behind the existing public callback. Its short-lived cache keys include minute-aligned reporting ranges, owner/global visibility, normalized roles, aggregate-affecting settings, and schema versions. It uses a persistent WordPress object cache when configured and database transients otherwise, retains a bounded stale value during rebuild lock contention, and falls back to the canonical database reads on a first-fill/cache-store failure. Generation invalidation covers lead insert/update/inbound merge, assignment/handoff, deletion/reset, quotation value projection, relevant dashboard/visibility settings, and retention work. Lead-list read-model consolidation, full invalidation/native mutation coverage, and target-profile cold/warm benchmarks remain pending.

## Phase 4 — Replace deep offsets and bound bulk work

Purpose: make page depth and batch size independent from request timeout and memory limits.

Work:

- Add stable cursor/keyset pagination for leads, activities, logs, quotations, and catalogue lists that need deep navigation.
- Use deterministic sort tuples such as `(assigned_at, id)` or `(created_at, id)` and signed/validated cursor tokens.
- Preserve current page-number query parameters during a compatibility window; do not silently change sort order.
- Convert large lead/catalogue imports, exports, historical CAPI sync, audience sync, integrity repair, and reset operations into bounded resumable jobs.
- Store job progress, checkpoints, actor, filter snapshot, totals, timestamps, error summary, and idempotency key.
- Stream downloadable output to protected temporary storage or object storage and expire it after a documented period.
- Cap rows, bytes, execution time, and retries per worker; use exponential backoff and dead-letter/manual retry state.
- Add cancellation only where it cannot leave partially committed domain state.

Exit criteria:

- Deep navigation does not show latency proportional to skipped rows.
- Web requests do not process unbounded datasets.
- Jobs resume safely after worker termination and never duplicate lifecycle/integration effects.
- Progress and failures are visible to authorized operators.

Progress evidence (version 1.99.3): Phase 4 has started with signed, short-lived keyset cursors for the default manager lead queue. Tokens bind the stable `(COALESCE(assigned_at, created_at), id)` descending position to the current user, authorization scope, normalized filters, page size, and sort contract. The existing `page`/`per_page` inputs and response metadata remain; the shared client retains page-entry cursors for sequential navigation, while custom sorts, pre-sales ordering, direct non-cursor requests, and page-number compatibility continue to use offsets. Full syntax, 81 fast contracts, 14 disposable native tests, and live wp-admin/frontend cursor navigation pass without console errors. Other list families and resumable bulk jobs remain pending, as do target-profile deep-page measurements.

## Phase 5 — Split remaining code hotspots

Purpose: let multiple features evolve without returning to oversized request coordinators.

Work order:

1. Split `IDLM_Admin_Ajax` by lead reads, lead mutations, tasks/activity, imports/exports, routing, diagnostics, and integration operations while keeping every `IDLM_Admin` and AJAX callback as a delegate.
2. Split `IDLM_Integrations` into route registration/authentication, inbound mapping, assignment/routing, feeds/MCP transport, and notifications.
3. Split schema/task/activity compatibility from `IDLM_DB` while retaining lifecycle writes through its established facade.
4. Split platform delivery/read models from `IDLM_CAPI` and aggregate queries from `IDLM_Dashboard`.
5. Split `assets/js/admin-app.js` by Leads table, drawer/activity, tasks, routing, import/export, settings, and diagnostics behind the same registered handle or dependency-compatible module handles.
6. Split the Leads template into guarded presentation sections when selector-safe.

Rules:

- Characterize registrations, payloads, selectors, output, and side effects before each extraction.
- Keep transport handlers thin: authenticate, authorize, validate, call a service/read model, format response.
- Do not introduce a container/autoloader migration unless a separate compatibility plan proves value.
- No replacement class should exceed approximately 800 lines without a documented exception.
- No new JavaScript module should own unrelated page families.

Exit criteria:

- Existing public and browser contracts remain unchanged.
- Critical handlers have focused unit/integration coverage.
- No newly extracted boundary duplicates lifecycle, authorization, or SQL logic.
- File size is used as a warning signal, not the sole completion metric.

Progress evidence (version 1.99.4): Phase 5 has started with the 727-line authenticated lead-detail read and drawer-response handler extracted into `IDLM_Admin_Lead_Reads`. The established `IDLM_Admin` and `IDLM_Admin_Ajax` methods remain delegates, and the action, nonce, capability/object visibility order, response payload, selectors, SQL reads, and read-state side effect remain unchanged. The remaining AJAX coordinator decreased from 3,807 to 3,097 lines. Full PHP syntax, 82 fast contracts, 14 disposable native WordPress/MySQL tests, and the live frontend lead drawer pass without browser warnings/errors. Other lead-read handlers and the later mutation, task/activity, import/export, routing, diagnostic, integration, database, CAPI/dashboard, JavaScript, and Leads-template splits remain pending.

## Phase 6 — Prepare horizontal infrastructure

Purpose: allow more than one WordPress application node without duplicated work or inconsistent files/cache.

Required production topology:

- A shared transactional MySQL primary with tested backups and point-in-time recovery.
- Shared WordPress media/object storage; lead uploads cannot depend on one node's local filesystem.
- A persistent shared object cache such as Redis with namespaced keys and monitored eviction/error behavior.
- Action Scheduler as the preferred queue, with dedicated runners sized for backlog and third-party rate limits.
- One controlled system cron entry invoking WordPress cron/queue processing; disable traffic-driven WP-Cron when the managed runner is proven.
- Database-backed advisory locks/idempotency retained for assignment, numbering, imports, and integration jobs.
- Secrets and environment configuration available identically to every node.
- Load balancer health checks that do not mutate CRM state.

Work:

- Verify no runtime writes target plugin files or node-local paths except explicitly temporary files.
- Make temporary export paths shared or deliver them from the creating worker through protected object storage.
- Test node termination during webhooks, lead mutations, quote save, number reservation, imports, and background delivery.
- Verify scheduled actions are claimed once and retries remain idempotent across nodes.
- Document database connection, cache, queue, media, cron, and secret dependencies.

Exit criteria:

- Two application nodes pass the same functional and concurrency suite.
- Terminating either node does not lose accepted requests or corrupt state.
- Media and protected exports remain available regardless of serving node.
- Queue work is not duplicated and backlog recovery meets the target.

Progress evidence (version 1.99.5): Phase 6 has started with an administrator-only, read-only horizontal deployment preflight. It evaluates declared shared database, media/object storage, and configuration/secrets; requires both an active persistent object cache and a shared-cache declaration; detects Action Scheduler availability; requires traffic-driven WP-Cron to be disabled only after a declared managed runner exists; and records that current exports stream without plugin-local persistence. The report exposes boolean readiness only, not hosts, paths, salts, credentials, or secret values. The runtime-write inventory and deployment declaration/qualification sequence are documented in `docs/HORIZONTAL-DEPLOYMENT.md`. Actual shared infrastructure provisioning, two-node termination/queue/media/concurrency testing, backup recovery, and target-scale qualification remain pending.

## Phase 7 — Add observability, capacity signals, and runbooks

Purpose: detect degradation before users or integrations lose data.

Signals:

- Request rate, p50/p95/p99 latency, PHP errors, memory, and response size by route/action.
- Database total/query latency, slow query fingerprint, rows examined, lock waits, and connection saturation.
- Webhook accepted/rejected/rate-limited counts and deduplication outcomes.
- Queue pending/failed/oldest age, retry count, and third-party response status.
- Lead lifecycle failures, routing exceptions, quotation transaction rollbacks, numbering conflicts, import/export failures, and retention failures.
- Table/index growth, autoloaded option size, cache hit rate, eviction/errors, disk/object-storage usage, and backup age.

Work:

- Emit structured, redacted events with correlation IDs across webhooks, lifecycle writes, queued deliveries, and callbacks.
- Separate expected validation/authorization failures from operational faults.
- Add health checks for schema version, cron/Action Scheduler, queue age, secret availability, database, cache, and storage.
- Define warning and critical thresholds from Phase 0 targets.
- Create runbooks for failed deployment, schema migration, queue backlog, webhook outage, third-party outage, credential compromise, database restore, data-integrity repair, and rollback.
- Test alerts without exposing PII or secrets.

Exit criteria:

- Operators can answer what failed, for whom, when, and whether retry is safe without reading raw sensitive payloads.
- Critical alerts have an owner and tested runbook.
- Backup age, queue lag, webhook failure, and database saturation alerts are active before launch.

Progress evidence (version 1.99.6): Phase 7 has started with an administrator-only, aggregate operational-health report for database reachability, independent schema versions, recurring and retention schedules, enabled-integration credential presence, upload storage, cache backend, Action Scheduler pending/failed/oldest age, and recent telemetry/CAPI/audience faults. Initial thresholds are 5/15 minutes for queue age and 1/10 for warning/critical failures. The report is read-only and excludes credentials, contacts, payloads, hosts, database names, and paths. Initial schema, queue, credential/third-party, and critical-health runbooks are documented in `docs/OPERATIONS-RUNBOOKS.md`. Correlation IDs, route/database timing, webhook outcome metrics, backup/capacity signals, external alerts, remaining runbooks, and alert rehearsals remain pending.

## Phase 8 — Load, resilience, and recovery qualification

Purpose: prove the signed targets and failure behavior before production rollout.

Test matrix:

- Small, expected, and provisional-maximum datasets.
- Normal, peak, burst, and soak traffic.
- Administrator, assignment manager, representative, pre-sales, and unauthorized access.
- Concurrent webhook duplicates, lead assignments, task updates, quote saves, and number reservations.
- Slow/unavailable Meta, Google, LinkedIn, Interakt, email, cache, and object storage.
- Database latency, connection exhaustion, deadlocks, worker termination, queue backlog, and expired credentials.
- Upgrade from production-like 1.98.x data, interrupted backfill, rollback, and restore.

Required evidence:

- Latency/error/resource charts with exact dataset and build revision.
- Query plans for remaining slow fingerprints.
- Zero authorization leakage under cache and concurrency tests.
- Zero duplicate quote numbers, duplicated integration effects, lost accepted webhooks, or orphaned quotation graphs.
- Successful backup restore and documented recovery time/recovery point results.

Exit criteria:

- All signed service targets pass with headroom.
- A soak test completes without memory growth, queue drift, or table/log explosion.
- Failure injection produces bounded retries and actionable alerts.
- Restore and rollback rehearsals meet agreed objectives.

Progress evidence (version 1.99.7): Phase 8 has started with a dry-run-by-default, production-refusing authenticated HTTP load runner for the existing dashboard, lead-list, and Kanban read actions. It generates a short-lived WordPress session and established nonce for a selected benchmark user, destroys the benchmark session on shutdown, caps requests/concurrency/warmups, disables redirects, retains TLS verification, streams bodies to anonymous temporary files, and emits only aggregate latency percentiles, throughput, response size, outcome counts, and unsigned provisional threshold checks. A deterministic dataset/traffic/role/concurrency/failure/recovery/integrity matrix is recorded but remains not executed. Target-scale measurements, per-role authorization evidence, server-side database/resource timing, mutation/concurrency drivers, failure injection, soak, upgrade, rollback, and restore qualification remain pending.

## Phase 9 — Controlled production release

Pre-deployment:

- Merge a clean reviewed commit only after remote CI and artifact verification.
- Build the ZIP using `.distignore`; inspect that it contains one plugin directory, required templates/assets/classes, no secrets, no logs, no local database files, and no test/runtime artifacts.
- Generate an artifact checksum and tag the exact revision.
- Take and verify a database/media backup.
- Record current schema/plugin versions, queue state, cron state, environment configuration, and rollback command.
- Freeze unrelated schema, integration, and workflow changes during the rollout window.

Rollout:

- Deploy to staging from the exact production artifact and rerun the release matrix.
- Deploy during a monitored window with migration and queue dashboards open.
- Run read-only smoke checks, then one controlled lead lifecycle and quotation flow using test records.
- Verify inbound webhook, background delivery, email, cron, access roles, workspace, and logs.
- Expand access/traffic only after health thresholds remain stable.

Rollback:

- Roll back application code only while schema remains backward compatible.
- If a data migration is not backward compatible, use its tested forward-repair or restore procedure; never improvise destructive SQL.
- Preserve accepted webhooks and queued jobs during rollback through idempotency/checkpoints.
- Record the incident and update tests/runbooks before retrying release.

Exit criteria:

- Production metrics remain within targets through the agreed observation window.
- No new security, integrity, queue, or performance alert remains unresolved.
- Artifact, revision, schema, documentation, and changelog versions are aligned.

Progress evidence (version 1.99.8): Phase 9 has started with a dry-run preflight and explicitly confirmed artifact builder driven by `.distignore`. It aligns the plugin header, runtime constant, readme stable tag, changelog, and plan release; validates required runtime files and blocks development/private artifacts plus high-confidence credential signatures; requires one plugin directory; records an exact Git revision; and emits a SHA-256 checksum and aggregate manifest. Dirty local builds require `--allow-dirty` and remain labeled rehearsal-only. A GitHub release-artifact job waits for the complete PHP/WordPress matrix, builds from the clean checked-out revision, verifies the checksum, and uploads the ZIP and evidence together. Remote CI execution, tag/release creation, backup verification, staging deployment, production smoke, monitored rollout, and rollback rehearsal remain pending.

Progress evidence (version 1.100.5): Phase 9 now independently reopens a CI-built candidate and binds its ZIP, checksum file, manifest, clean exact revision, GitHub run, completed matrix, version, file count, required runtime inventory, and single plugin directory before promotion to staging rehearsal. The integration harness now passes the disposable WordPress fixture root to its performance subprocesses in CI. CI emits an aggregate staging-gate report with database/media backup, restore, staging deployment, release matrix, schema, queue, cron, environment, monitoring, rollback-command, and controlled-smoke evidence explicitly pending. The verifier never extracts or deploys the archive, changes CRM state, or approves production. Remote CI execution, tag/release creation, operator evidence, staging deployment, production smoke, monitored rollout, and rollback rehearsal remain pending.

## Phase sequencing and release policy

- Phase 0 is mandatory before performance implementation.
- Phase 1 production security/configuration gates can proceed in parallel with baseline tooling, but must finish before production launch.
- Phases 2–4 are ordered by measured impact; a phase may be skipped only when baseline evidence shows it is unnecessary.
- Phase 5 extractions should accompany the affected optimization, not precede it as a cosmetic rewrite.
- Phase 6 is required only before multi-node deployment, but node-local assumptions should be removed earlier when touching related code.
- Phases 7–8 are required before launch at the signed scale target.
- Each independently completed implementation release increments the version and updates `CHANGELOG.md`, `readme.txt`, `docs/CHANGELOG-DEV.md`, the relevant focused docs, and this plan's completion evidence with Shashi attribution.

## Stop conditions

Stop and diagnose before continuing if any phase causes:

- Changed capability or object-level visibility.
- Missing nonce/signature/credential validation or secret/PII exposure.
- Changed public hook, action, route, shortcode, payload, selector, option, state, or stored-value contract.
- Lost/duplicated lifecycle, task, routing, quotation, webhook, or integration events.
- Non-idempotent or blocking schema migration.
- Cache data shared across incompatible user scopes.
- Queue retries that duplicate external delivery or numbering.
- Query improvement on synthetic data that regresses the production-like profile.
- A release artifact that cannot be tied to a clean tested revision.
- A failed backup restore or rollback rehearsal.

## Definition of done

The production and scalability program is complete only when:

- A clean committed revision passes the complete remote CI and release matrix.
- Security/configuration decisions are closed and production secrets are rotated.
- Staging upgrade, backup restore, rollback, cron, email, webhook, and queue rehearsals pass.
- Signed scale targets pass under load and soak tests with documented headroom.
- Critical queries have evidence-backed plans/indexes and no unbounded web-request work remains.
- Caches are authorization-safe and correctly invalidated.
- Imports, exports, syncs, and repairs are bounded, resumable, observable, and idempotent.
- Multi-node infrastructure passes when horizontal deployment is required.
- Monitoring, alerts, ownership, and recovery runbooks are active and tested.
- Public contracts, schema state, release metadata, documentation, and artifact checksum are aligned.

## CORE-012 bounded retention and read work — 2026-10-07

Shashi: candidate **1.102.55**, schemas unchanged. Checked 100-row retention batches rotate across targets with transactional progress, recovery/session fencing, continuations and health faults. Task cards page 50 after SQL grouping; WhatsApp uses SQL count/EXISTS; CSV stages 100-row reads with explicit row/file/time limits before headers. **279 fast / 171 native / 308 HTTP-stdio PASS**, desktop/mobile pagination and a local 5,000-lead read-plan sample. IMPLEMENTED, not CLOSED; M7 production workload/runtime/artifact gates remain open. [Policy, evidence and limitations](AUDIT-REMEDIATION-BOUNDED-WORK-SLICE.md). Next: CORE-013.


## CORE-013 — State vocabulary and lifecycle policy (2026-10-07)

Shashi: candidate **1.102.56**, schemas unchanged. Shared manual-task and early-close subsets now drive forms and handlers; qualification coupling is distinguished from supplied-quality validation. [Current policy](STATE-POLICY.md) records current writers, reversal effects and historical repair differences. **284 fast / 5 focused native / 14 task-plan native checks PASS** (18 distinct native cases; fresh schema runs twice). CORE-013 is IMPLEMENTED, not CLOSED; runtime/artifact qualification remains open. **25 OPEN / 19 IMPLEMENTED**. [Evidence and limits](AUDIT-REMEDIATION-STATE-POLICY-SLICE.md). Next: QUO-004, outstanding M2 quotation migration integrity.


## QUO-004 — Checked quotation migration completion (2026-10-07)

Shashi: candidate **1.102.57**, quotation schema **2.5.1**, core/routing unchanged. Physical verification precedes checked data phases; final schema completion and durable pending/ready status prevent false acknowledgements. Boot/activation propagate failures and retry incomplete work, reading committed markers directly. Complete 2.5.0 backup compatibility retained. **291 fast / 202 native / 312 HTTP-stdio PASS**. Two final targeted native checks also pass (203 distinct native cases across final runs). IMPLEMENTED, not CLOSED; QUO-005/010 historical semantics and M7/M8 qualification remain open. **24 OPEN / 20 IMPLEMENTED**. [Policy and evidence](AUDIT-REMEDIATION-QUOTATION-MIGRATION-SLICE.md). Next: QUO-005.


## 2026-10-08 — QUO-009 paper and coupled UI-004 defaults

Shashi: candidate **1.102.63**, schemas unchanged. Shared paper normalization aligns setup/print/PDF, preserves existing A3/Legal support and safe A4 defaults; PDF CSS no longer forces A4. Explicit quotation CSV escaping removes PHP 8.4 download diagnostics. **312 fast / 11 focused actual HTTP PASS**, with all 377 pre-existing HTTP/stdio cases passing in the accumulated run, actual browser desktop/mobile selection and nine-page A4/Letter dimension/text/visual evidence. QUO-009 and coupled UI-004 are **IMPLEMENTED, not CLOSED**; runtime/artifact and exhaustive PDF resource/transport qualification remain open. **17 OPEN / 27 IMPLEMENTED**. [Policy and evidence](AUDIT-REMEDIATION-QUOTATION-PAPER-SLICE.md). Next: UI-001.

## INT-010 protected platform credentials — 2026-10-09

Shashi: candidate **1.102.71**, schemas unchanged. Seven platform secrets use verified encrypted/environment storage and fresh primary reads; write-only fields support retain/replace/explicit clear. Meta uses Bearer authentication; covered transports scrub secret echoes and refuse redirects. Legacy copied-key queues are cleaned in bounded batches. **350 fast / 306 full native / 425 full actual HTTP PASS** (12 focused native and 5 focused HTTP also pass); commands and hashes are recorded in the [slice and machine evidence](AUDIT-REMEDIATION-SECRET-STORE-SLICE.md). **INT-010 IMPLEMENTED, not CLOSED; 7 OPEN / 37 IMPLEMENTED**. Provider-required Google query secrets, historic traces, installed scheduler/runtime/key-management and exact artifact qualification remain open. Automation mobile overflow was observed. Next listed finding: REL-001, with M6/M7 prerequisites still required.

## REL-002 verified Inter license and provenance — 2026-10-09

Shashi: candidate **1.102.72**, schemas and font/CSS bytes unchanged. All five font files exactly match the official Google Latin variable-font distribution; embedded Inter revision resolves to the pinned upstream source/license. Bundle complete OFL 1.1, human notice and per-file provenance hashes; require all font resources/notices in release inventory. **12 release contracts PASS**, two pre-fix failures reproduced, actual **453-file** rehearsal/extraction identity verified. **REL-002 IMPLEMENTED, not CLOSED; 5 OPEN / 1 IN PROGRESS / 38 IMPLEMENTED**. REL-001 clean/CI/final artifact, other dependency and reproducibility gates remain. [Sources, implementation and evidence](AUDIT-REMEDIATION-FONT-NOTICE-SLICE.md). Next executable finding: REL-003.

## REL-003 optional public credit — 2026-10-09

Shashi: candidate **1.102.73**, schemas unchanged. Fresh/missing/partial attribution defaults off; saved on/off choices remain unchanged without inferring historical consent. Only the explicit checkbox string opts in on authorized save; clear linked help and shared preview/reset inherit the off default. **356 fast / 4 actual HTTP PASS**, desktop/mobile keyboard/reset checks and all changed PHP lint PASS. **REL-003 IMPLEMENTED, not CLOSED; 4 OPEN / 1 IN PROGRESS / 39 IMPLEMENTED**. Complete runtime/CI/final artifact qualification remains pending. [Policy and evidence](AUDIT-REMEDIATION-ATTRIBUTION-OPT-IN-SLICE.md). Next executable finding: REL-004.

## REL-004 public service/privacy/support disclosures — 2026-10-09

Shashi: candidate **1.102.74**, schemas/runtime behavior unchanged. Publish actual provider transfers, automatic attribution cookies, protected credentials, retention/erasure/removal/partial-backup limits, owner-supplied private support/security contact and manual ZIP update/recovery instructions. Require bundled public privacy/support guides. **12 release contracts PASS; 455-file archive/source identity PASS; 4 PHP lint PASS**; no new full runtime/installed-artifact qualification claimed. **REL-004 IMPLEMENTED, not CLOSED; 3 OPEN / 1 IN PROGRESS / 40 IMPLEMENTED**. Consent/receipt-erasure and provider/deployment/recovery limits remain explicit. [Source review and evidence](AUDIT-REMEDIATION-PUBLIC-DISCLOSURES-SLICE.md). Next executable finding: REL-005.

## REL-005 runtime support and prerequisites — 2026-10-09

Shashi: candidate **1.102.75**, schemas unchanged. User selected **WordPress 6.6 / PHP 7.4**; align header/readme, publish prerequisites, refuse unavailable PDF/XLSX storage and crypto operations, and retain all six CI cells with resolved runtime/dependency/download/log evidence. **361 fast / 5 focused prerequisite contracts / 306 WP 6.6 native / 429 WP 6.6 full HTTP-stdio / 11 WP 6.6 export HTTP / 11 WP 7.1.2 export HTTP PASS**, on PHP 8.4.14/MySQL 8.4.0. Missing module/ZIP/renderer simulations are distinguished from actual disabled crypto and temporary-directory conditions. **REL-005 IMPLEMENTED, not CLOSED; 2 OPEN / 1 IN PROGRESS / 41 IMPLEMENTED**. PHP 7.4/8.1, resolved latest 7.1.3, actual absent-extension hosts, complete exact CI and installed artifact remain unqualified. [Evidence, policy and limits](AUDIT-REMEDIATION-RUNTIME-SUPPORT-SLICE.md). Next executable finding: REL-006.

## REL-006 reproducible release ZIP metadata — 2026-10-11

Shashi: candidate **1.102.76**, runtime/schemas unchanged. Reproduce the root timestamp defect with two full packages; check root creation, sort builder inventory and normalize root/file timestamps, UNIX modes and file compression. CI packaging pins UTC. **13 release contracts PASS**; separated **455-file** full builds and a source-metadata/order-perturbed copy produce identical ZIP bytes, with independent content/metadata inspection. **REL-006 IMPLEMENTED, not CLOSED; 1 OPEN / 1 IN PROGRESS / 42 IMPLEMENTED**. Exact clean revision/matching CI/final artifact and cross-toolchain qualification remain open. [Evidence and build contract](AUDIT-REMEDIATION-ZIP-REPRODUCIBILITY-SLICE.md). Next executable finding: REL-007.

## REL-007 package inventory and diagnostic checkpoint — 2026-10-11

Shashi: local candidate **1.102.77**, runtime semantics/schemas unchanged. Exclude five local graph/design/prototype files, independently reject their reintroduction, protect dependency licenses/resources and document four narrow output/resource exceptions. Add disposable unfiltered official Plugin Check 2.1.0 with a failing diagnostic wrapper and CI candidate-upload gate. **14 release / 363 fast contracts PASS**; final 450-file ZIP matches source. Checker: **138 errors / 2,032 warnings remain**; ledger records 574 examined justifications, **1,595 source-review-required findings** and one readme limit. **REL-007 IN PROGRESS, not IMPLEMENTED or CLOSED; 0 OPEN / 2 IN PROGRESS / 42 IMPLEMENTED**. [Exact artifact, diagnostics and review queue](AUDIT-REMEDIATION-PACKAGE-CHECK-SLICE.md). Next executable work: continue REL-007 SQL/request source review; REL-001 remains prerequisite-blocked.

## REL-007 SQL/request source checkpoint — 2026-10-11

Shashi: local candidate **1.102.78**, schemas unchanged. Meta verification rejects non-string/missing parameters and unslashes only raw WP fallbacks; plain-text/zero challenges and REST token bytes remain correct. Prefix nine MCP CLI globals. Review **37 SQL locations**, record 97 additional specific justifications without suppressing diagnostics. **365 fast / 7 focused Meta HTTP / 430 full HTTP-stdio / 14 release contracts PASS**. Official final 450-file ZIP: **138 errors / 2,018 warnings**, ledger **671 justified / 1,484 review-required / one readme limit**. **REL-007 IN PROGRESS, not IMPLEMENTED or CLOSED; 0 OPEN / 2 IN PROGRESS / 42 IMPLEMENTED**. [Source fixes, exact artifact and remaining queue](AUDIT-REMEDIATION-PACKAGE-SOURCE-SLICE.md). Continue REL-007 SQL/request source review; REL-001 remains prerequisite-blocked.

## REL-007 local static completion — 2026-10-18

Shashi: [REL-007 static completion, candidate 1.102.83](AUDIT-REMEDIATION-PACKAGE-STATIC-COMPLETION-SLICE.md). Official Plugin Check 2.1.0 default static gate PASS on the 453-file source-matching ZIP: zero errors, zero warnings, zero review-required diagnostics. 385 fast (including 16 release), 331 native and 445 HTTP/stdio cases PASS; 88 changed PHP files lint clean. Source fixes resolve 438 diagnostic instances; 694 bounded, source-specific review exceptions account for 1,740 instances. Private managed ZIP selected by Shashi. REL-007 IMPLEMENTED locally; REL-001, M6/M7 and exact clean CI/installed-artifact qualification remain IN PROGRESS. Backlog: **43 IMPLEMENTED / one IN PROGRESS / zero CLOSED**. Historical checkpoints retain their original failure results.

## M6 handoff notification ownership — 2026-10-18

Shashi: [M6 handoff notification ownership, candidate 1.102.84](AUDIT-REMEDIATION-HANDOFF-NOTIFICATIONS-SLICE.md). Extract opt-in email delivery behind the unchanged integration callback and durable outbox. Method-body executable tokens match; 385 fast / four focused native / 334 full native cases and seven PHP lints PASS. Default Plugin Check gate PASS with zero diagnostics on the source-matching 454-file ZIP. M6/M7 and REL-001 remain IN PROGRESS; backlog remains 43 IMPLEMENTED / one IN PROGRESS / zero CLOSED. Next bounded M6 work: inventory one integration auth/intake/feed or AJAX handler responsibility and preserve its existing delegate/transport contracts before extracting it. Do not treat this single mail extraction as the M6 exit review.

## M6 administrator connection controller — 2026-10-18

Shashi: [M6 connection controller, candidate 1.102.85](AUDIT-REMEDIATION-CONNECTION-CONTROLLER-SLICE.md). Move the 119-line authenticated diagnostic handler byte-for-byte behind both existing facades; nonce/admin guards, credentials, provider requests and JSON messages remain unchanged. 385 fast / four focused HTTP / 449 full HTTP-stdio cases and eight PHP lints PASS. Default Plugin Check gate PASS with zero diagnostics on the source-matching 455-file ZIP. M6/M7 and REL-001 remain IN PROGRESS; backlog stays 43 IMPLEMENTED / one IN PROGRESS / zero CLOSED. Continue the remaining bounded M6 ownership review; this handler extraction does not complete M6 or qualify the release.

## M6 telemetry controller — 2026-10-20

Shashi: [M6 telemetry controller, candidate 1.102.86](AUDIT-REMEDIATION-TELEMETRY-CONTROLLER-SLICE.md). Extract three byte-identical handlers (129 lines) behind both unchanged facades. 387 fast / four focused HTTP (WP 7.1.3) / 453 full HTTP-stdio (WP 6.6) cases and seven PHP lints PASS. The 456-file ZIP matches source. Default Plugin Check reports nine warnings in existing Users & Access code; complete static gate fails, with no telemetry diagnostics. M6/M7 and REL-001 remain IN PROGRESS; backlog stays 43 IMPLEMENTED / one IN PROGRESS / zero CLOSED. Continue the remaining bounded M6 ownership review.

## M6 activity controller — 2026-10-21

Shashi: [M6 activity controller, candidate 1.102.87](AUDIT-REMEDIATION-ACTIVITY-CONTROLLER-SLICE.md). Extract four byte-identical timeline/comment handlers (232 lines) behind both stable facades. 387 fast / eight pre-extraction and eight post-extraction focused HTTP (WP 6.6) / 461 full HTTP-stdio (WP 7.1.3) cases and eight PHP lints PASS. The 457-file ZIP matches source. Default Plugin Check retains the same nine existing Users & Access warnings; no new diagnostics. M6/M7 and REL-001 remain IN PROGRESS; backlog stays 43 IMPLEMENTED / one IN PROGRESS / zero CLOSED. Continue bounded M6 review of the remaining coupled responsibilities.

## CI-qualified build checkpoint — 2026-10-23

Shashi: [CI-qualified production build 1.102.88](CI-QUALIFIED-BUILD-1.102.88.md). M6 COMPLETE; selected single-server M7 qualification and exact M8 build/promotion evidence verified. Eight remote matrix cells and the official package gate PASS. REL-001 IMPLEMENTED; 44 IMPLEMENTED / zero IN PROGRESS / zero CLOSED. Actual-host acceptance and deployment remain pending. Historical checkpoints retain their original results.
