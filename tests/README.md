# Automated Test Baseline

REL-007 completion (Shashi): `php tests/integration/quotation-http.php --release-static-only` runs two new cases using the existing disposable `IDLM_TEST_*` environment: structured/nondecimal export identifiers fail without writes and literal login passwords survive once-only unslashing while array passwords fail quietly. Both run in the full suite. `php tests/run.php` includes two fail-closed package-result decoder cases, for 385 fast cases including 16 release cases. Final local native/HTTP suites pass 331/445 cases. The wrapper retains raw stdout and accepts only strict JSON diagnostic lists or Plugin Check 2.1.0’s exact exit-zero clean CLI sentence; any diagnostic still fails. [Current passing gate and source-exception ledger](../docs/AUDIT-REMEDIATION-PACKAGE-STATIC-COMPLETION-SLICE.md).

REL-007 bootstrap metadata (Shashi): `php tests/integration/quotation-http.php --bootstrap-input-only`, with the documented disposable `IDLM_TEST_*` environment, runs five HTTP cases for malformed query arrays, pending-migration 503/privacy, administrator repair notices, both REST query namespaces and live request-lifetime recovery fencing. All five also run in the accumulated HTTP/stdio suite. `IDLM_TEST_FILTER="active native connection" php tests/integration/run.php` runs fresh schema plus the existing independent-connection restore/reset/stale-generation fence. [Checkpoint](../docs/AUDIT-REMEDIATION-PACKAGE-BOOTSTRAP-REQUEST-SLICE.md).

REL-007 request validation (Shashi): `php tests/integration/quotation-http.php --request-input-only` uses the documented disposable `IDLM_TEST_*` environment and runs six real HTTP checks for malformed wood/name rejection without writes, valid catalogue/settings values, literal text, decimal bounds and clean affected-request diagnostics. Five behavioral cases also run in the accumulated HTTP/stdio suite. Fast connector checks retain exact zero/leading-zero numeric challenge responses after escaping. [Current checkpoint](../docs/AUDIT-REMEDIATION-PACKAGE-REQUEST-INPUT-SLICE.md).

REL-007 quotation text (Shashi): the fast suite includes two parser cases in `quotation-numeric-contracts.php`. With the documented disposable `IDLM_TEST_*` environment, `php tests/integration/quotation-http.php --text-only` runs four actual HTTP checks for literal item/section text, autosave/editor recovery, new/historical revisions, owner/admin print, parsed CSV/XLSX and clean diagnostics. Three behavioral cases also run in the accumulated HTTP/stdio suite. [Checkpoint](../docs/AUDIT-REMEDIATION-PACKAGE-QUOTATION-TEXT-SLICE.md).

REL-007 dashboard SQL (Shashi): `IDLM_TEST_FILTER=dashboard php tests/integration/run.php` runs the fresh-schema prerequisite, existing scoped-dashboard case and `integration/package-dashboard-contracts.php`. Supply only the documented disposable `IDLM_TEST_*` environment. The new case independently checks eight stored origins, literal prefix matching, source/date/current-owner filters, unchanged history and old/warm cache results. [Checkpoint](../docs/AUDIT-REMEDIATION-PACKAGE-DASHBOARD-SLICE.md).

REL-007 (Shashi): `tests/release/check-package.php --artifact=<built.zip> --checker-zip=<official-checker.zip> --wp-cli=<PHP WP-CLI file> --output-dir=<evidence>` uses the existing IDLM_TEST_WP_ROOT/DB_USER/DB_PASSWORD/DB_HOST fixture variables. Supply a fresh temporary WordPress core without wp-config.php; the runner refuses configured sites/pre-existing databases, intercepts HTTP/mail and removes its owned DB/content/configuration. Static default checks have no additional exclusions. Any diagnostic returns 1; infrastructure failure returns nonzero. Final reports/justifications do not qualify unresolved alerts. [Historical failing checkpoint](../docs/AUDIT-REMEDIATION-PACKAGE-CHECK-SLICE.md).

REL-006 (Shashi): the ordinary fast runner includes a real ZIP reproducibility contract in `release-contracts.php`. It crosses a two-second DOS timestamp boundary, perturbs source times/modes and input order, compares complete bytes/SHA256 and checks root/file metadata and source contents. Temporary synthetic archives are cleaned in finally. CI packaging uses TZ=UTC; reproduce packages with the same PHP zip/libzip/zlib versions. [Executed package evidence and limits](../docs/AUDIT-REMEDIATION-ZIP-REPRODUCIBILITY-SLICE.md).

REL-005 (Shashi): selected minimum **WordPress 6.6 / PHP 7.4**. `php tests/runtime-prerequisites.php` runs five focused support/availability contracts, also registered by `tests/run.php`. Separate processes evaluate verbatim exporter source with namespaced PHP API seams for missing DOM/mbstring/iconv/GD/renderer/ZIP; these are simulations, not absent-module host installations. Actual disabled OpenSSL functions preserve both legacy and valid encrypted bytes; nonexistent temporary storage fails without warnings, and available ZIP output is parsed. CI installs the explicit prerequisites and records resolved PHP/WP/MySQL/vendor versions, extension inventory, core hash and named logs for every cell. [Executed floor and export evidence, scope and limits](../docs/AUDIT-REMEDIATION-RUNTIME-SUPPORT-SLICE.md).

Builder: Shashi

## Fast behavioral suite

REL-003 (Shashi): `attribution-opt-in-contracts.php` is included in the fast suite. The accumulated HTTP runner includes `integration/attribution-opt-in-http-cases.php`; use `php tests/integration/quotation-http.php --attribution-only` with the disposable `IDLM_TEST_*` environment for the focused four-case slice. Add `--hold` for the bounded synthetic browser fixture, `/crm-workspace` for the actual sign-in/workspace shortcode, and `/settings-workspace?tab=white-label` for the shared settings renderer. Create its printed STOP file when finished. Tests assert persisted SQL across independent requests and refuse configured-site databases. [Policy and executed evidence](../docs/AUDIT-REMEDIATION-ATTRIBUTION-OPT-IN-SLICE.md).

Run the dependency-free suite from the plugin root:

```bash
php tests/run.php
```

The suite loads the production classes with small WordPress and `$wpdb` test doubles. It does not connect to or modify the site database. At candidate 1.102.57, its 291 contracts cover lead lifecycle and integrity, representative AJAX authorization, encrypted scoped REST/MCP authentication and limits, authorization/query scoping, signed cursor isolation/keyset compatibility, quotation calculations/persistence/autosave, schema migrations, scheduler fallback, retention redaction, stable states, guarded templates, grouped settings, environment-driven MCP paths, semantic print output, deterministic performance profiles/safety gates, read-only query-plan coverage/privacy gates, Phase 8 load-manifest/result/privacy safeguards, Phase 9 release inventory/version/private-file/CI-attestation, candidate-promotion, and disposable WordPress-root handoff gates, authorization-scoped read-cache reuse/invalidation, horizontal-readiness and operational-health evaluation/rendering, the admin facade's public/registration/asset/selector/capability contracts, the completed renderer/settings/shell boundaries, the UI/UX Phase 1–7 contracts, frontend workspace style-isolation specificity, single-row drawer-status overflow, and the Phase 5 lead-detail AJAX read extraction.

## Native WordPress/MySQL integration suite

The high-priority integrity regression additions also have focused runners:

```bash
php tests/inbound-task-integrity-contracts.php
php tests/interakt-action-scheduler-contracts.php
```

The default fast suite includes ownership/read-all isolation, drawer field permissions, Interakt enqueue/replay/retry/cancellation and conflicting inbound identity checks. Native tests additionally exercise pre-sales partial saves and reset rollback with an injected database failure, then verify successful runtime/queue cleanup without lead-ID reuse. Native data remains confined to the disposable test database.

The opt-in integration suite boots the WordPress installation containing this plugin against a newly created disposable MySQL database:

```bash
IDLM_TEST_DB_USER=test_database_user \
IDLM_TEST_DB_PASSWORD=test_database_password \
IDLM_TEST_DB_HOST='localhost:/path/to/mysqld.sock' \
php tests/integration/run.php
```

Use `host:port` instead of a socket path when appropriate. `IDLM_TEST_WP_ROOT` may override the WordPress root; otherwise the suite derives it from the plugin location. `IDLM_TEST_DB_NAME` is optional and normally generated uniquely.

Safety guarantees:

- Database names must start with `idlm_test_`.
- The runner refuses any pre-existing database rather than reusing or dropping it.
- The database user must have `CREATE DATABASE` and `DROP DATABASE` privileges on the disposable server.
- A shutdown cleanup attempts to drop the generated database and remove its temporary `wp-content` directory even after a failed test.

The 14 native tests cover fresh schema installation, real `dbDelta()` idempotence and upgrade recovery, REST and authenticated AJAX dispatch, admin/frontend renderer selectors, settings-save security and persistence, lead lifecycle persistence and hooks, scoped MCP PII access, catalogue persistence/read models, the frontend quotation list route, concurrent quotation numbering, quotation persistence/totals/list reads, generated semantic print markup, database uniqueness constraints, real small-profile performance seed/verify/scoped-reset behavior, and read-only optimizer/index capture. They never load the configured site database. `.github/workflows/tests.yml` runs both suites across PHP 7.4/8.1/8.4 and WordPress 6.6/latest.

## Performance baseline tooling

Phase 0 inventory, deterministic synthetic datasets, benchmark definitions, the Phase 2 query-plan manifest, and Phase 8 read-only load tooling live under `tests/performance/`. `capture-baseline.php` emits aggregate-only runtime/capacity JSON; `capture-query-plans.php` emits aggregate-only optimizer/index JSON and refuses production. `seed-dataset.php` is dry-run by default and requires explicit confirmation in a non-production environment before adding or resetting one tagged PII-free dataset run. `run-load-qualification.php` is also dry-run by default and requires an exact confirmation plus a benchmark user before it can call the allowlisted authenticated read endpoints. See `tests/performance/README.md`, `docs/PERFORMANCE-BASELINE.md`, and `docs/LOAD-RESILIENCE-QUALIFICATION.md`.

Phase 9 release tooling lives under `tests/release/`. `build-release.php` performs a read-only preflight by default; an explicitly confirmed build validates the `.distignore` inventory, version alignment, private-file/secret-signature policy, single-directory ZIP, exact revision, checksum, and CI attestation. `verify-candidate.php` accepts only the clean matrix-attested candidate for the exact reviewed revision, independently reopens its archive, and emits aggregate pending staging gates without deploying or approving production. See `docs/PRODUCTION-RELEASE.md`.

## Quotation HTTP authority regressions (QUO-001 / QUO-003)

Run `php tests/integration/quotation-http.php` with the same disposable `IDLM_TEST_*` environment as the native runner. It requires the `curl` CLI, `proc_open`, loopback listening and a fresh `idlm_test_*` database. It copies WordPress core without site configuration/content, intercepts outbound HTTP/mail before install, boots the complete plugin runtime, obtains real cookie sessions/nonces and calls native admin-post/AJAX dispatch. Its independent HTTP workers use bounded barriers to change ownership/user grants/role grants between initial checks and locking reads. Denial snapshots include every runtime IDLM table, working copies, cron and intercepted outbound attempts; SQL snapshot errors fail tests.

The runner drops its owned database and removes its content/server/cookie fixtures on shutdown. `--hold` keeps the same disposable fixture for at most 15 minutes for browser checks; create the printed `STOP` file to finish sooner. Use `/login` with the synthetic accounts defined in the runner and `/workspace` or `/admin-workspace` for the shared renderers. Never supply site credentials or target the configured database. See [the focused evidence report](../docs/AUDIT-REMEDIATION-QUOTATION-SLICE.md) for executed results and qualification limits. CI includes the HTTP runner in each existing runtime job.

The same disposable HTTP command now also includes `lead-read-cases.php` for CORE-001/011 and QUO-002: ordinary/default and explicitly enrolled roles, live list/count/task scope, pending/completed pre-sales history, user/role/suspension revocation, warmed dashboards/captured cursors, and independent query barriers. Native `run.php` includes `lead-access-contracts.php` for approved migration, persistence failure and retry. See [scope and evidence](../docs/AUDIT-REMEDIATION-READ-SLICE.md).

MCP remediation cases: `mcp-query-contracts.php` / `mcp-query-cases.php` exercise restricted grammar and independent field/tool grants; the native runner includes `integration/mcp-query-contracts.php` (real projection, SQL modes, paging and statement timeout). The existing disposable HTTP runner includes `integration/mcp-http-cases.php`, which uses actual REST HTTP and the production stdio entry against the same isolated synthetic fixture. No configured-site database is loaded. See [slice evidence](../docs/AUDIT-REMEDIATION-MCP-SLICE.md).

CORE-003 regressions: `lead-csv-contracts.php` runs in the fast suite; `integration/lead-csv-cases.php` runs through the existing disposable `integration/quotation-http.php` runner. Twelve actual download cases parse 40 columns, compare synthetic adversarial/ordinary text and numeric precision, exercise grants/nonces/filters/revocation, and assert unchanged denied/successful read state. The fixture elevates affected CSV warnings/deprecations. See [actual execution evidence and limits](../docs/AUDIT-REMEDIATION-CSV-SLICE.md).

Private-file regressions (Builder: Shashi): `integration/quotation-http.php` includes `private-file-cases.php` for actual multipart/download/legacy-static URL and synchronized live-authority races. `integration/run.php` includes `private-file-contracts.php` for native schema/copy/unlink/rollback/reset recovery. Use only a new disposable `idlm_test_*` schema and owned MySQL socket; never configured site credentials. `--hold` retains an isolated synthetic browser fixture for at most fifteen minutes; create its printed STOP file when finished. Outbound HTTP/mail is intercepted before WordPress installation. See [executed evidence](../docs/AUDIT-REMEDIATION-PRIVATE-FILE-SLICE.md).

Backup recovery children (Shashi): `tests/integration/backup-contracts.php` is included by the native runner; `backup-http-cases.php` by the accumulated HTTP runner. Both use newly created synthetic databases with outbound HTTP/mail interception. See [the sixth report](../docs/AUDIT-REMEDIATION-RESTORE-SLICE.md) for named tests and qualification limits.

CORE-007 children (Shashi): fast `schema-migration-contracts.php`, native `integration/schema-migration-contracts.php` and HTTP `integration/schema-http-cases.php` are included by the existing CI runners. Use only fresh `idlm_test_*` databases. Cases exercise physical metadata, actual restricted DDL accounts, checked markers/backfills, bounded cursors, interruption/session-lock loss, synchronized independent installers and 503/retry bootstrap behavior. [Evidence and remaining support/history limits](../docs/AUDIT-REMEDIATION-SCHEMA-SLICE.md).

## CORE-004/005 lifecycle persistence regressions

Shashi: `lifecycle-persistence-contracts.php` is included in the fast runner; `integration/lifecycle-persistence-contracts.php` adds real SQL trigger faults, every fresh task-insert position, required read/commit faults, a killed connection, lost worker lock, independent committed-state observation and synchronized worker/crash tests. `integration/lifecycle-http-cases.php` runs actual AJAX/webhook failure/retry/canonical replay via the existing disposable HTTP/stdio fixture. All operate on owned `idlm_test_*` databases/content and intercept outbound calls. [Candidate evidence and unqualified dimensions](../docs/AUDIT-REMEDIATION-LIFECYCLE-SLICE.md).

## CORE-006 qualification task-plan regressions

Shashi: fast `task-plan-repair-contracts.php`, native `integration/task-plan-repair-contracts.php` and actual HTTP `integration/task-plan-repair-http-cases.php` run in the existing commands above. They check legacy slot preservation/fill, faults, closed/reopened/evidence/owner/date policy, competing workers with bounded busy retries, additive upgrade/unique constraints, 101-lead checkpoints and prior backup normalization. Use only newly created disposable schemas with outbound interception. [Candidate results and unqualified dimensions](../docs/AUDIT-REMEDIATION-TASK-PLAN-SLICE.md).

## CORE-009 assignment eligibility regressions

Builder: Shashi. `assignment-eligibility-contracts.php`, its native counterpart and `integration/assignment-eligibility-http-cases.php` run through the existing fast/native/HTTP entry points. Real account/meta/role revocations occur on an independent database connection after pool selection and before locking persistence. Cases compare aggregate rows after denials and exercise all initial routing modes, both fallbacks, invalid target/read/engine states, legitimate transfers/unassignment and actual lead admin/shortcode mutation choices. Candidate 1.102.52: 266 fast / 127 native / 287 HTTP-stdio PASS. CORE-010 concurrent capacity is separate; runtime/artifact qualification remains pending. [Report](../docs/AUDIT-REMEDIATION-ASSIGNMENT-ELIGIBILITY-SLICE.md).

## CORE-010 concurrent capacity regressions

Builder: Shashi. `capacity-contracts.php`, its native counterpart and `integration/capacity-http-cases.php` run through the existing three runners. Native tests include a 201-lead backlog, unlimited two-page recovery, all routing contexts, stale repeatable-read/settings decisions, four synchronized pairs that both see the last slot, rollback and process death. Actual HTTP covers partial per-lead bulk errors, reopen/close, manual creation and full pre-sales webhook capture. Candidate 1.102.53: 270 fast / 139 native / 292 HTTP-stdio PASS. Earlier fixtures use explicit unlimited capacity when testing unrelated domain behavior. [Policy and qualification limits](../docs/AUDIT-REMEDIATION-CAPACITY-SLICE.md).

## CORE-008 review regressions

Builder: Shashi. `identity-review-contracts.php`, native `integration/identity-review-contracts.php` and actual `integration/identity-review-http-cases.php` run through the existing three runners. Native fault/concurrent/upgrade/erasure tests use fresh owned databases only. `IDLM_TEST_FILTER=CORE008` runs native review children plus the mandatory fresh-schema prerequisite. Browser evidence exercises the native admin shell on the disposable fixture. [Policy/evidence](../docs/AUDIT-REMEDIATION-IDENTITY-REVIEW-SLICE.md).

## Bounded retention and reads (`1.102.55`)

Shashi: retention runs one checked 100-row batch with transactional `idlm_retention_progress`, recovery/session fencing, fair target rotation, continuation hook `idlm_continue_sensitive_retention`, and operational failure status. Task responses use 50-card signed keysets and scoped totals (`counts_only` skips cards); queue qualification history reads 100 dates at a time. WhatsApp filters/counts stay in SQL; compatibility ID helpers page 100 by `after`. Lead CSV stages 100-row reads, capped at 10,000 rows/32 MiB/20 seconds of application work, before headers. Core/routing/quotation schemas unchanged. [Policy, verification, API details and remaining production gates](../docs/AUDIT-REMEDIATION-BOUNDED-WORK-SLICE.md).


## CORE-013 state policy contracts

Shashi: `state-policy-contracts.php` exercises manual type persistence/reserved types, early-close evidence and administrator limits, sales stage/quality distinctions and custom/terminal reversals. The native counterpart tests the canonical facade, historical repair idempotence and actual drawer DOM options across pre-sales, mixed-role and administrator actors. Run with the disposable harness using `IDLM_TEST_FILTER=CORE013`; `IDLM_TEST_FILTER=CORE006` checks existing task-plan repair/reopening/migration contracts. Neither command uses the configured site database. [Current policy](../docs/STATE-POLICY.md), [execution evidence and limits](../docs/AUDIT-REMEDIATION-STATE-POLICY-SLICE.md).


## QUO-004 quotation migration contracts

Shashi: fast `quotation-migration-contracts.php` checks failure propagation and marker gating. Native `integration/quotation-migration-contracts.php` exercises real physical schema, data rollback, old false receipts, competing installers, process interruption, cached receipt rejection and complete 2.5.0 backup compatibility; filter with `IDLM_TEST_FILTER=QUO004`. `integration/quotation-migration-http-cases.php` runs with the disposable HTTP suite and checks boot 503/private notices/retry. Final shared-boundary verification used full suites: 291 fast, 202 native and 312 HTTP/stdio checks pass. [Evidence and remaining gates](../docs/AUDIT-REMEDIATION-QUOTATION-MIGRATION-SLICE.md).

One final targeted preservation case plus its repeated fresh-schema prerequisite also passes, giving 203 distinct native cases across final runs; production code was unchanged after the 202-case full native run.


QUO-005 (Shashi): standard fast/native/HTTP commands include classification preservation contracts for known/missing old snapshots, additive DDL failure/retry, process death, synchronized installers, provenance copies and complete prior backup normalization. Candidate 1.102.58 has 294 fast / 212 native / 315 HTTP-stdio passing checks locally. Only use fresh owned `idlm_test_*` databases; no configured-site data. [Evidence and qualification limits](../docs/AUDIT-REMEDIATION-QUOTATION-CLASSIFICATION-SLICE.md).


## Quotation commercial history (1.102.59)

Shashi: `normalize_revision_lifecycle()` now verifies relationships and preserves commercial data; lifecycle receipt is 1.1.0 and schema 2.5.3 with unchanged columns. Reuse `IDLM_Quotation_Policy::is_editable_draft()` at mutation/UI boundaries and keep live authorization plus quote locking. Preserve commercial evidence, never reconstruct erased pointers/dates by guessing. Existing standard fast/native/HTTP suites include the history modules; 296 / 219 / 326 cases pass locally. Complete 2.5.2 backups remain supported. [Policy, named evidence and limitations](AUDIT-REMEDIATION-QUOTATION-HISTORY-SLICE.md).


## Runtime quotation engines — QUO-007

Shashi: Fast quotation-engine-contracts.php, native integration/quotation-engine-contracts.php and actual integration/quotation-engine-http-cases.php run through the three standard suites. IDLM_TEST_FILTER=QUO007 runs five native cases plus the fresh-schema prerequisite. MyISAM/default/missing/read-fault fixtures, independent metadata-lock and live-grant races, multipart catalogue imports and actual COMMIT rollback use owned disposable databases only. Synthetic fault diagnostics are checked separately; normal affected-request diagnostics must stay empty. [Scope, executed checks and limits](../docs/AUDIT-REMEDIATION-QUOTATION-ENGINE-SLICE.md).

## QUO-008 numeric regressions

Builder: Shashi. Standard suites include six fast, three native and twenty-two HTTP numeric cases. IDLM_TEST_FILTER=QUO008 runs native cases with the fresh-schema prerequisite. Run node tests/quotation-numeric-js-contracts.js for 28 production-helper golden vectors; no npm dependencies. SQL independently checks decimal rounding; actual HTTP checks rejection snapshots, exact revision exports and supported boundaries. Browser checks use the disposable --hold fixture only. See [policy and evidence](../docs/AUDIT-REMEDIATION-QUOTATION-NUMERIC-SLICE.md).

## QUO-006 document regressions

Shashi: fast `quotation-document-contracts.php`, native `integration/quotation-document-contracts.php`, and actual HTTP `integration/quotation-document-http-cases.php` cover saved currency/terms/payments, additive upgrade and DDL retry, complete old/current backups, malformed input, aggregate rollback, and selected historical print/PDF/CSV/XLSX. Full results: 310 fast / 231 native / 377 HTTP-stdio PASS. Set `IDLM_TEST_DOCUMENT_EVIDENCE_DIR` to a writable directory to retain synthetic downloaded artifacts; HTTP PDF text checks require existing `pdftotext` (override `IDLM_TEST_PDFTOTEXT`), dompdf and ZipArchive. Browser fixtures use `--hold` and the generated synthetic IDs; stop the owned fixture afterward. Configured A4 checks do not qualify QUO-009 defaults/Letter. [Evidence and limitations](../docs/AUDIT-REMEDIATION-QUOTATION-DOCUMENT-SLICE.md).


### QUO-009 / UI-004 document checks

Builder: Shashi. The standard fast/HTTP suites include paper/default regressions. Run `php tests/integration/quotation-http.php --paper-only` with the existing disposable MySQL environment for eleven focused native HTTP cases; require Poppler `pdfinfo`/`pdftotext`, overridable with `IDLM_TEST_PDFINFO`/`IDLM_TEST_PDFTOTEXT`. Set `IDLM_TEST_PAPER_EVIDENCE_DIR` to an existing directory to retain test downloads. Checks cover every multipage PDF dimension, all long item labels, saved terms, setup/print parity, no read writes and diagnostic-free PDF/CSV/XLSX. No configured-site database is used.


### UI-001 quantity checks

Builder: Shashi. `integration/quotation-quantity-http-cases.php` runs in the standard HTTP suite. Run `php tests/integration/quotation-http.php --quantity-only` with disposable MySQL environment variables for three focused cases covering ordinary quantity working copies/revisions, tiny dimension pricing and new/edit admin/shortcode controls. `--hold` retains this focused fixture for at most 30 minutes for actual browser checks; create the printed STOP file to finish and clean up. Set `IDLM_TEST_QUANTITY_EVIDENCE_DIR` to an existing writable directory for its synthetic browser-fixture identifiers. No configured-site database or credentials. [Executed evidence](../docs/AUDIT-REMEDIATION-QUOTATION-QUANTITY-SLICE.md).


### UI-002 task layout browser fixture

Builder: Shashi. Run `php tests/integration/quotation-http.php --task-ui-only --hold` with disposable MySQL environment variables. This mode prepares synthetic accounts/leads and actual `/tasks-workspace` assets; it runs no unrelated quotation cases. Set `IDLM_TEST_TASK_UI_EVIDENCE_DIR` to an existing writable directory for fixture identifiers. Use only the owned schema and synthetic login accounts, create the printed STOP file when finished, and reset temporary browser viewport overrides. The optional `/task-layout-stress` route serves only an explicitly created `task-layout-stress.html` inside the owned fixture content, for captured-markup layout stress. [Executed browser/SQL evidence](../docs/AUDIT-REMEDIATION-TASK-LAYOUT-SLICE.md).


### UI-003 task semantics and keyboard checks

Builder: Shashi. `integration/task-accessibility-http-cases.php` runs in the standard HTTP suite and `php tests/integration/quotation-http.php --task-ui-only` with disposable MySQL environment variables. It checks actual owner-authorized label/ID associations, submit/error semantics and unchanged plugin domain rows. Add `--hold` for the existing synthetic browser fixture; create its printed STOP file to clean up. [Desktop/mobile accessible names, keyboard and exact SQL evidence](../docs/AUDIT-REMEDIATION-TASK-ACCESSIBILITY-SLICE.md).


### INT-004 ordinary Interakt delivery

Builder: Shashi. Fast cases run with `php tests/run.php`; optional AS adapter checks run with `php tests/interakt-action-scheduler-contracts.php`. With the existing disposable MySQL variables, `IDLM_TEST_FILTER=INT004 php tests/integration/run.php` runs fresh schema plus ten focused native cases, including independent-worker overlap/crash and actual restore/reset cleanup. All provider HTTP/mail is intercepted. [Policy, compatibility and evidence](../docs/AUDIT-REMEDIATION-INTERAKT-DISPATCH-SLICE.md).


## CAPI manual retry (INT-005)

Builder: Shashi. Standard fast/native/HTTP runners include `capi-retry-contracts.php`, its native counterpart and `integration/capi-retry-http-cases.php`. With the disposable MySQL environment, `IDLM_TEST_FILTER=INT005 php tests/integration/run.php` runs fresh schema plus ten focused native cases. `php tests/integration/quotation-http.php --capi-retry-only --hold` retains an owned fixture for up to thirty minutes; set `IDLM_TEST_CAPI_RETRY_EVIDENCE_DIR` to retain synthetic fixture identifiers and create its STOP file when finished. `/crm-workspace?idlm_view=capi-logs` calls the actual composite workspace shortcode with shared assets. All outbound provider HTTP/mail is intercepted. [Policy and evidence](../docs/AUDIT-REMEDIATION-CAPI-RETRY-SLICE.md).


## Inbound WhatsApp batches and replay (INT-006)

Builder: Shashi. Standard fast/native/HTTP commands include `whatsapp-inbound-contracts.php`, its native counterpart and `integration/whatsapp-inbound-http-cases.php`. Use `IDLM_TEST_FILTER=INT006 php tests/integration/run.php` for nine focused native cases plus fresh schema. `php tests/integration/quotation-http.php --whatsapp-inbound-only` runs six actual signed anonymous REST cases. Workers simulate interruption before/after commit and competing deliveries. Use only newly owned disposable databases; all outbound HTTP/mail is intercepted. [Evidence and qualifications](../docs/AUDIT-REMEDIATION-WHATSAPP-INBOUND-SLICE.md).

## INT-007 Google audience pull revocation

Builder: Shashi. The standard runners include `google-audience-contracts.php` and native/HTTP counterparts. With the existing disposable MySQL variables, `IDLM_TEST_FILTER=INT007 php tests/integration/run.php` runs fresh schema plus three focused native cases. `php tests/integration/quotation-http.php --google-audience-only` runs seven actual credentialed feed cases, including a separate rate-limit assertion. Every HTTP child owns a fresh synthetic rate window. Use only newly owned disposable databases; outbound HTTP/mail is intercepted. [Evidence and remaining qualifications](../docs/AUDIT-REMEDIATION-GOOGLE-AUDIENCE-SLICE.md).

## INT-008 CAPI retention and replay evidence

Builder: Shashi. Standard fast/native/HTTP runners include the CAPI retention contracts. `IDLM_TEST_FILTER=INT008 php tests/integration/run.php` selects fresh schema plus thirteen focused native children; workers use synchronized pruning/dispatch and acceptance-interruption fixtures. `php tests/integration/quotation-http.php --capi-retention-only` selects three real AJAX children. Use the existing explicit disposable MySQL variables, newly owned databases and intercepted HTTP/mail. Expected Google-feed credential-denial debug lines are isolated by that HTTP helper; unexpected diagnostics still fail the full runner. [Evidence and qualifications](../docs/AUDIT-REMEDIATION-CAPI-RETENTION-SLICE.md).

## INT-009 audience manual work and exclusions

Builder: Shashi. Fast contracts run in `php tests/run.php`. Native: `IDLM_TEST_FILTER=INT009 php tests/integration/run.php` (fresh schema plus nineteen children). HTTP: `php tests/integration/quotation-http.php --audience-retry-only` (six actual cookie-authenticated AJAX/control children); also included in the full HTTP suite. Supply only disposable `IDLM_TEST_DB_*` credentials; fixtures refuse existing databases and intercept providers/mail. Cases cover current filters, selected-only pages, partial destination/page outcomes, bounded retries, durable claim/audit failures, independent process overlap/crash, unknown destination fences, queue changes/cancellation, restore/reset/erasure, and shared admin/shortcode controls. [Slice evidence](../docs/AUDIT-REMEDIATION-AUDIENCE-RETRY-SLICE.md).

## INT-010 platform-secret contracts

Builder: Shashi. `php tests/run.php` includes migration, authenticated clear, corruption, echo redaction and isolated runtime subprocesses (server-owned constants, disabled OpenSSL, salt rotation and absent key). `IDLM_TEST_FILTER=INT010 php tests/integration/run.php` runs primary SQL, independent credential rotation/revocation, write failures, provider/log/backup boundaries, settings and bounded Cron/physical AS cleanup. `php tests/integration/quotation-http.php --secret-store-only` checks actual cookie/nonce settings and connection-test AJAX. Use the documented disposable-database environment; provider/mail requests are intercepted. The AS table fixture and optional adapter contracts do not qualify installed AS versions. [Policy, full evidence and remaining runtime/browser gates](../docs/AUDIT-REMEDIATION-SECRET-STORE-SLICE.md).

## Hosted Facebook connector checks

Builder: Shashi. Run `php tests/facebook-service.php` and `php tests/facebook-connect-runtime.php` with PHP 8.1+, PDO SQLite and Sodium. These disposable mocked-provider tests cover the separate service and WordPress client, including consent ownership, signed transport, token isolation, selected-source delivery, reconnect/disconnect and revocation. They do not certify live Meta permissions, app approval or a deployed OAuth flow. The separate service is excluded from the plugin ZIP.

Native bounded-read samples write inside disposable `WP_CONTENT_DIR` and are removed with the fixture. Set `IDLM_TEST_READ_SAMPLE_PATH` explicitly to retain a new sample; routine runs never target the historical bounded-work slice. Builder: Shashi.

M6 handoff mail (Shashi): with disposable `IDLM_TEST_*` variables, `IDLM_TEST_FILTER=M6-handoff php tests/integration/run.php` runs fresh schema plus three native cases. They initialize the real callback, intercept mail, assert opt-in/recipient/message and missing-data compatibility, then reject a committed outbox delivery and accept its durable retry. They also run in the full native suite. No external email is sent. [Checkpoint](../docs/AUDIT-REMEDIATION-HANDOFF-NOTIFICATIONS-SLICE.md).

M6 connection controller (Shashi): `php tests/integration/quotation-http.php --connection-controller-only`, with the existing disposable `IDLM_TEST_*` environment, exercises four actual HTTP groups: nonce/admin/anonymous denial, local validation and Google posted/stored credentials, Meta/LinkedIn success messages/header authentication, and API/transport credential-echo redaction. All four also run in the accumulated HTTP suite. The provider seam returns synthetic responses without contacting services. [Evidence](../docs/AUDIT-REMEDIATION-CONNECTION-CONTROLLER-SLICE.md).

## M6 telemetry controller

Builder: Shashi. `php tests/integration/quotation-http.php --telemetry-controller-only` runs four disposable HTTP groups using the existing `IDLM_TEST_*` environment: nonce/administrator/anonymous denials without side effects, sanitized client-error storage and empty responses, report attachment/count/order/50-row semantics, and administrator clearing/empty downloads. These cases also run in the accumulated HTTP suite. Only owned temporary databases are changed; outbound calls/mail remain intercepted. [Evidence](../docs/AUDIT-REMEDIATION-TELEMETRY-CONTROLLER-SLICE.md).

## M6 activity controller

Builder: Shashi. `php tests/integration/quotation-http.php --activity-controller-only` uses the documented disposable `IDLM_TEST_*` environment for eight actual HTTP groups: nonce/capability/anonymous denials; read-all versus mutation access; validation/protected activity types; literal text/author/15-second duplicates; author/admin edits and provenance; current-owner deletion; failed update/delete responses; and empty/bounded/escaped timeline HTML with action visibility. Dedicated synthetic actors/leads avoid earlier-suite state. SQL faults reuse the existing fixture query hook, despite its historical `fail-quotation-write` name; normal request diagnostics must remain empty. The cases ran unchanged before and after extraction and also run in the accumulated HTTP suite. [Evidence](../docs/AUDIT-REMEDIATION-ACTIVITY-CONTROLLER-SLICE.md).

## 1.102.88 production qualification

Builder: Shashi. `quotation-http.php --kanban-page-only` checks multi-page coverage/full totals and actor/filter/sort/tamper rejection; `--role-permissions-only` checks administrator role-permission boundaries. Both are in the full runner. Native CORE012 cases cover task grouping/counts/cursors and five site timezones. Reflection compatibility preserves PHP 7.4 without deprecated calls on PHP 8.5.

`performance/qualify-team-load.py` exercises six scoped read actions or a selected action (including quotation list), 1–50 synthetic sessions, bounded request counts and optional 0–600 second pacing. `performance/qualify-intake.py` creates 90 synthetic submissions and replays ten duplicates only with a private loopback staging config and exact execution confirmation. Both emit aggregate evidence; private session/credential configs must never enter Git/artifacts. The `team-stress` seeder profile adds 100k leads while retaining prior profile sizes. [Results and release limits](../docs/M6-M7-RELEASE-CANDIDATE.md).

`performance/qualify-legacy-admin.py` uses the real WordPress `/wp-admin/admin.php` entry point in the owned loopback fixture. Eight legacy administrator routes plus representative/pre-sales cases verify early redirection, capability denials and query allowlisting; synthetic admin cookies remain in the private fixture config. It is dry-run by default and requires exact confirmation for execution.

## Real Action Scheduler retention acceptance (1.102.88)

Builder: Shashi. `php tests/integration/retention-action-scheduler.php` uses the existing disposable `IDLM_TEST_*` variables plus `IDLM_TEST_ACTION_SCHEDULER`, pointing to the official plugin's `action-scheduler.php`. CI verifies Action Scheduler 4.2.0 ZIP SHA-256 `a360712a5adb066a0d60db76cbe2768234731cbcc363ba7dc2d51bf4559e4551` and runs this step only with latest WordPress (AS requires WordPress 6.9+). It exercises real running/pending/complete transitions, bounded continuation through all stages, unexpired-row protection, stalled health, cross-session enqueue fencing/deduplication and WP-Cron fallback on rejection. Direct `process_action()` avoids sleeping while still entering the library's real in-progress state. No live database or outbound provider/mail traffic is used. On macOS use a canonical `TMPDIR=/private/tmp` so the crash-worker evidence path has no `/var` versus `/private/var` alias. A nonempty disposable database password is required for reliable worker environment propagation.
