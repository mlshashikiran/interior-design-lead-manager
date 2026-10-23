# M6 exit and production qualification — 1.102.88

Author / Builder: Shashi. Date: 2026-10-22.

**M6 is COMPLETE.** All seven items in the [finite exit inventory](M6-EXIT-INVENTORY.md) have focused owners and verified compatibility delegates. M7 has advanced to measured single-server qualification. M8 / REL-001 remain open until the complete reviewed source has matching authentic remote CI and final candidate promotion evidence. A local rehearsal ZIP is not approved for deployment. **2026-10-23 update:** [authentic CI and candidate promotion now pass](CI-QUALIFIED-BUILD-1.102.88.md); REL-001 is IMPLEMENTED, with actual-site acceptance and deployment pending. The sections below retain their local qualification checkpoint and archive identity.

## Ownership and compatibility

| Owner | Responsibility | Moved methods |
|---|---|---:|
| `IDLM_Integration_Auth` | Scoped credentials, rate limits, integration permissions | 12 |
| `IDLM_Webhook_Intake` | Payload parsing, normalization, mapping and canonical upsert coordination | 8 |
| `IDLM_Google_Feed_Controller` | HTTP authentication challenges, headers and CSV response transport | 5 |
| `IDLM_CAPI_Transport` | Provider payloads/HTTP requests and audit outcomes | 6 |
| `IDLM_Google_Conversion_Feed` | Conversion export queries, date/CSV formatting and buffer cleanup | 4 |
| `IDLM_Admin_Task_Controller` | Task request handlers and manual-task helpers | 8 |
| `IDLM_Admin_Collection_Reads` | List, pipeline and administrator count reads | 3 |

The original 46 method signatures and forwarding calls remain intact. Existing routing, transaction/receipt/retry algorithms, registration names, selectors, shortcodes and core/quotation schemas (1.3.9 / 2.5.4) are preserved. Each owner is loaded before its facade is called. The internal owners expose methods needed by stable delegates; this creates no new HTTP endpoint.

The [parity capture](remediation-2026-10-22/production-candidate/m6-extraction-parity.json) describes the instant of extraction: 46 moved methods were identical after normalizing internal visibility. It does **not** claim current bodies are all identical. Subsequent M7 fixes intentionally change task-day calculation, Kanban paging and integration permission rate accounting. [Final signature/delegate verification](remediation-2026-10-22/production-candidate/m6-delegate-verification.json) independently checks all 46 wrappers against the captured source baseline.

Canonical schema/backfill, lifecycle/assignment, database logging, aggregate dashboard calculation and shared client/view owners are explicitly accepted in the finite inventory. Further splitting by file size is not an M6 requirement. The feature-extension checklist and focused source maps remain the extension guide.

## Workload and runtime decision

Shashi specified latest stable versions, a single server and 25–50 users, delegating other sizing decisions. The selected expected envelope is **50,000 total leads**, 400,000 activities, 100,000 tasks, 100,000 touchpoints, 200,000 retained integration/telemetry logs and 20,000 quotation graphs. Every tenth lead is a synthetic duplicate. Fifty synthetic users comprise one administrator, one sales manager, 43 representatives and five pre-sales users. Five representatives own duplicate-only records; drawer/activity cases require a canonical owned lead.

Reference runtime: WordPress **7.1.3**, PHP **8.5.12**, MySQL **8.4.0**, Nginx **1.26.1**, one host with **10 logical CPUs / 16 GiB**, 16 PHP-FPM workers, OPcache and 256 MiB application memory limit. PHP and WordPress came from verified official archives; PHP 8.5 was built in an isolated temporary directory, without replacing system PHP. The FPM build lacks cURL; WordPress uses its supported alternative HTTP transport. Provider calls and mail were blocked in every synthetic fixture. The measurements do not establish capacity on smaller hardware.

Selected baseline: no persistent cache, system-driven WP-Cron at least once per minute, staging/private access for qualification, MCP disabled unless explicitly configured, single-site deployment, with unsupported network activation rejected before schema writes. Multisite and multi-node deployment are outside this release qualification. A real Action Scheduler 4.2.0 / Docket Cache 26.04.11 variant was additionally exercised; neither is bundled or required by the release.

Sources: [WordPress releases](https://wordpress.org/download/releases/), [PHP downloads](https://www.php.net/downloads.php), [Action Scheduler](https://wordpress.org/plugins/action-scheduler/), [Docket Cache](https://wordpress.org/plugins/docket-cache/). Exact versions and archive hashes are in the evidence directory.

## Measured defects and repairs

1. Task attention applied the site offset twice. The reproduced failure is fixed with `current_time('Y-m-d')`; native checks cover UTC, India, Auckland, New York and a negative fixed offset.
2. Unbounded manager Kanban responses exceeded 4 MiB. Responses now contain at most 100 cards, user/filter/sort-bound signed page navigation, full filtered stage counts/values and visible Previous/Next controls. All filters and access are reapplied on every page. Offset pages reflect live data; concurrent edits can change page membership. The fixed sort still includes the ID tie-breaker.
3. Task grouping scanned the full queue before restricting visibility. Grouping now applies the same live lead scope before aggregation; qualification collapsing, totals and signed task cursors retain their tested behavior.
4. WordPress invokes REST permission callbacks again to construct the Allow header. The original limiter charged that recheck as another request. Rate outcomes are now memoized only in server-owned request attributes, while credentials are revalidated every time. Client parameters cannot provide that memo. Independent request objects still consume the full rate budget.
5. The complete workspace inherited the block theme's article width. Standard WordPress full-width alignment and scoped automatic width now fit the existing shell. Pipeline page controls wrap their range label within the available width.
6. Actual wp-admin smoke found WordPress rejecting retired CRM menu URLs before the late `admin_init` redirect. The same guarded callback now also runs at `admin_menu` priority zero, before core menu-page access validation. Eleven real WordPress admin-entry checks exercise eight administrator routes, query allowlisting and representative/pre-sales access.
7. The role-permission handler's existing administrator/nonce boundary is explicit to the checker; request method, role and structured permission fields are sanitized and allowlisted. Malformed grants fail closed.

The first expected-profile mixed run had five over-budget Kanban responses and task p95 3.67s. After bounding/scope changes, 1,200 requests at concurrency 50 had zero errors; list p95 748ms, task p95 1.08s and Kanban p95 596ms. Dashboard p95 in that mixed cold/warm run was 1.55s; an explicitly primed 450-request dashboard run measured p95 126ms. These are endpoint/network measurements from the reference host, with aggregate server query/time/memory signals retained separately.

The **100,000-lead doubled stress profile is not qualified**: 1,200 concurrent mixed reads recorded four task 503 deadline responses; list p95 1.63s and dashboard p95 3.45s exceeded selected thresholds. The task deadline failed closed; no mutation was involved. This is a documented capacity boundary, not a passing result or a promise of 250k support. A separate 450-request quotation-list compatibility run with persistent cache recorded zero errors / p95 154ms.

The [final-archive qualification summary](remediation-2026-10-22/production-candidate/qualification-summary.json) records these results with the frozen archive hash:

| Final-archive workload | Requests | Errors | p95 |
|---|---:|---:|---:|
| Fifty synthetic sessions, six mixed reads, 10 requests/second for three minutes; inbound active during the first minute | 1,800 | 0 | 86.48ms |
| Explicitly primed dashboards, concurrency 50 | 450 | 0 | 105.40ms |
| Quotation list, concurrency 25 | 450 | 0 | 83.02ms |
| Ninety unique inbound submissions plus ten replays | 100 | 0 | 57.72ms |

The paced run recorded p99 485.96ms, maximum 2.14s, 22 MiB peak server allocation, p95 172 queries and 74.86ms database time. These query signals include full WordPress startup; they are measured, not projected. The final fixture has 50k seeded leads plus 253 synthetic intake leads from the failing characterization and passing rerun. Independent persistence checks confirm exactly 90 canonical records, 100 touchpoints and zero duplicate rows for the passing inbound run. The prior rate-accounting characterization accepted 73/90 new submissions and rejected the remaining requests/replays; that failed evidence is retained. Earlier measurements retain their own artifact hashes and are not relabeled as final-archive runs.

## Verification and operations

- Fast suite: **388 PASS**. Native database suite: **337 PASS**. Actual HTTP/stdio suite: **468 PASS**, including three new pipeline paging/scope groups, four role-permission groups and native network-activation denial before any write. Native and HTTP runs are repeated from the ZIP-installed runtime with a temporary test harness; that harness is removed before installed-inventory verification.
- PHP 8.5 syntax checks pass for all 477 PHP files, including first-party runtime/templates/tests and bundled libraries. JavaScript syntax and Python compilation checks pass. The CI matrix now includes PHP 8.5 alongside 7.4/8.1/8.4 and WordPress 6.6/latest; remote execution is still required.
- Official Plugin Check 2.1.0, unfiltered default static checks: **zero errors / zero warnings** on the final source-matching archive. This is a static package gate, not production acceptance.
- Browser checks exercise pipeline navigation at 1280px and 390px, full filtered counts, 100-card pages, and the block-theme workspace. Final screenshots and DOM/console observations are retained. No plugin console warning/error was observed. Real wp-admin legacy-link acceptance is recorded separately from the 468-case custom-router HTTP suite.
- Actual installed native/HTTP cases cover install/historical migration, interrupted transaction/backfill/restart, quote numbering and concurrent mutation, lifecycle/outbox/retry fencing, schema failure recovery, permissions, retention, private files, erasure and backup restoration. Injected failures remain visible in the relevant case evidence.
- Real Action Scheduler and WP-Cron/no-cache lifecycle rehearsals preserve record counts and scheduled plugin hooks. An actual WP-Cron entry-point probe completes. The persistent-cache variant verifies cross-process hits, actor separation, generation invalidation/rebuild and immediate denial after synthetic access revocation.
- A **402,367,904-byte** transaction-consistent database backup restores into a separate disposable schema. All **40 table checksums** match; dump took 2.70s and restore/verification 26.45s on this host. A separate three-file synthetic media restoration matches every SHA-256. SQL dumps, sessions, salts and credentials remain private outside Git and the release.
- The raw stress seeder gives some leads legacy quotation values without a corresponding versioned graph. An activation rehearsal correctly migrated 22,500 such legacy records; its initial unchanged-count assertion failed. Subsequent stable-state deactivate/reactivate rehearsals passed with 62,500 graphs. This was fixture migration behavior, not a claim that the initial raw dataset was already fully migrated.
- OSV query results for the five bundled library versions contained no reported advisories at qualification time. Notices, versions and query/results are retained; this is not a guarantee that no vulnerability exists.

## Release boundary and remaining work

The release inventory contains **464 files**. Frozen ZIP SHA-256: `e23945882160c472a7ce2c8575735084293a0a72ff6306f9cd6b0e586da4bb71`. The [installed inventory proof](remediation-2026-10-22/production-candidate/installed-identity.json) checks every source/archive/installed file and removal of the temporary harness. Two independent local builds are byte-identical; [reproducibility evidence](remediation-2026-10-22/production-candidate/reproducibility.json) and per-file installed identity are recorded separately. Engineering docs, tests, graphs, local prototypes, temporary data and optional scheduler/cache packages are excluded. The source-matching ZIP remains **local_rehearsal_only** until the existing verifier accepts authentic CI from the clean reviewed revision.

1. Push the reviewed release branch to the **public** GitHub repository only with Shashi's explicit approval, then complete the supported matrix and download/verify its exact artifact and provenance. Never synthesize CI environment flags locally.
2. Run the candidate verifier against that revision and CI artifact; bind the installed-runtime evidence to its runtime byte inventory. REL-001 and audit closure remain open until these gates are satisfied.
3. Confirm the real server matches the qualified resource/runtime envelope, HTTPS/private-file protection, debug display off, protected secrets/backups, one-minute scheduler, retention policy, health monitoring and rollback owner. Rehearse the real site's upgrade/backup restore before rollout. The synthetic snapshot does not establish the site's RPO/RTO.
4. Live provider credentials/delivery, real email/WhatsApp, long-duration soak and the production observation window remain deployment acceptance. No real customer/provider traffic, public release, tag or production deployment has occurred. The reviewed branch was subsequently pushed with explicit authorization; its CI outcome is recorded in the update above.

Use the existing controlled-release/runbook documents. Deployment requires separate authorization and the saved production connection's domain must be confirmed before any mutation.
