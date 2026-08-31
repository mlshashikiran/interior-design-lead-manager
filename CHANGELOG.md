# Changelog

Current release notes are kept concise. The complete pre-maintenance history is preserved in [`docs/legacy/CHANGELOG-FULL.md`](docs/legacy/CHANGELOG-FULL.md).

## [1.100.7] - 2026-08-31

### Engineering workflow

- Refined Sol/Terra/Luna delegation guidance, added model-delegation fallback and review verification requirements, and standardized effort selection to Low, Medium, and High (implemented by Shashi).
- Recorded `novamira-crm-qoakinterior` as the saved MCP for reviewed LIVE deployments to `crm.qoakinteriors.com` (implemented by Shashi).

## [1.100.6] - 2026-08-31

### Engineering workflow

- Added project-specific Sol, Terra, and Luna delegation boundaries with Light, Medium, and High effort-selection rules (implemented by Shashi).

## [1.100.5] - 2026-08-31

### Improved

- Kept the Users & Access directory filter controls on one line, with horizontal scrolling available on narrow screens (implemented by Shashi).

## [1.100.1] - 2026-08-31

### Added

- Added an administrator-only Users & Access workspace for creating CRM users, changing managed CRM roles, suspending/restoring CRM access, and sending password-reset links (implemented by Shashi).
- Enforced CRM suspensions in representative and quotation authorization while preserving WordPress accounts and historical lead ownership (implemented by Shashi).
- Redesigned Users & Access with an accessible invite flow, searchable team directory, clear active/suspended state, and disclosure-based user editing. Added it to the administrator frontend workspace navigation (implemented by Shashi).

### Security and documentation

- Added self-lockout and final-administrator safeguards; documented the user-management interface and access boundary (implemented by Shashi).

## [1.99.9] - 2026-08-31

### Staging candidate promotion

- Advanced production/scalability Phase 9 with an independent verifier that binds the CI candidate ZIP, checksum, manifest, version, clean exact revision, completed matrix, GitHub run, file count, required runtime inventory, and single plugin directory before staging rehearsal (implemented by Shashi).
- Added an aggregate staging-gate report that leaves database/media backup, restore, deployment, release matrix, schema, queue, cron, environment, monitoring, rollback-command, and controlled-smoke evidence pending; the verifier never extracts, deploys, mutates CRM state, or approves production (implemented by Shashi).

### Tests and documentation

- Added promotion-policy, checksum-binding, archive-reinspection, pending-gate, and CI-wiring contracts; verified full PHP syntax and 98 fast contracts (implemented by Shashi).

## [1.99.8] - 2026-08-31

### Controlled production release

- Started production/scalability Phase 9 with a guarded `.distignore`-driven release-artifact builder that verifies aligned versions, required runtime inventory, excluded development/private files, high-confidence credential signatures, one plugin directory, and an exact Git revision (implemented by Shashi).
- Added rehearsal-only dirty-build labeling plus a CI-attested artifact job that waits for the full PHP/WordPress matrix and uploads the inspected ZIP, SHA-256 checksum, and release manifest together (implemented by Shashi).

### Tests and documentation

- Added release inventory, version alignment, private-file/secret-signature, and exact-revision CI-attestation contracts; verified full PHP syntax, 93 fast contracts, and local artifact/checksum inspection (implemented by Shashi).

## [1.99.7] - 2026-08-31

### Load and resilience qualification

- Started production/scalability Phase 8 with a dry-run-by-default, production-refusing authenticated HTTP load runner for the existing dashboard, lead-list, and Kanban read actions (implemented by Shashi).
- Added bounded requests/concurrency/warmups, generated WordPress cookie/nonce authentication, no redirects, retained TLS verification, anonymous temporary response storage, aggregate latency/throughput/size/outcome evidence, and an explicit not-executed resilience matrix (implemented by Shashi).

### Tests and documentation

- Added load-manifest, percentile/threshold, safety/privacy, and qualification-matrix contracts; verified full PHP syntax, 89 fast contracts, 14 disposable native WordPress/MySQL tests, and a successful three-request local dashboard runner smoke check (implemented by Shashi).

## [1.99.6] - 2026-08-30

### Operational health and runbooks

- Started production/scalability Phase 7 with an administrator-only, aggregate operational-health report for database/schema state, recurring/retention schedules, enabled credential presence, upload storage, cache backend, Action Scheduler backlog/failures/age, and recent telemetry/integration faults (implemented by Shashi).
- Added initial warning/critical thresholds plus critical-health, schema-mismatch, queue-backlog, and credential/third-party runbooks without exposing credentials, contacts, payloads, hosts, database names, or paths (implemented by Shashi).

### Tests and documentation

- Added queue/fault threshold, critical-gate, privacy, and System Health rendering contracts; verified full PHP syntax, 86 fast contracts, and 14 disposable native WordPress/MySQL tests (implemented by Shashi).

## [1.99.5] - 2026-08-30

### Horizontal deployment readiness

- Started production/scalability Phase 6 with an administrator-only, aggregate read-only preflight for shared database, media/object storage, configuration/secrets, persistent cache, Action Scheduler, controlled cron, and node-local export assumptions (implemented by Shashi).
- Documented the current runtime-write inventory, protected deployment declarations, qualification order, and multi-node stop conditions without provisioning infrastructure or exposing hosts, paths, salts, credentials, or secret values (implemented by Shashi).

### Tests and documentation

- Added blocked/ready evaluation and System Health rendering contracts; verified full PHP syntax, 84 fast contracts, and 14 disposable native WordPress/MySQL tests (implemented by Shashi).

## [1.99.4] - 2026-08-30

### Focused request handlers

- Started production/scalability Phase 5 by extracting the authenticated lead-detail read handler into `IDLM_Admin_Lead_Reads` behind the established `IDLM_Admin` and `IDLM_Admin_Ajax` delegates (implemented by Shashi).
- Preserved the `idlm_get_lead_details` action, nonce, capability and object-level visibility checks, database reads, response payload, drawer markup, selectors, and stored behavior (implemented by Shashi).

### Tests and documentation

- Added delegate, security-order, class-size, response, and representative isolation contracts; verified full PHP syntax, 82 fast contracts, 14 disposable native WordPress/MySQL tests, and the live frontend lead drawer without browser warnings/errors (implemented by Shashi).

## [1.99.3] - 2026-08-30

### Bounded pagination

- Started production/scalability Phase 4 with stable keyset pagination for the default manager lead queue using the existing `(COALESCE(assigned_at, created_at), id)` order (implemented by Shashi).
- Added short-lived HMAC-signed cursor tokens bound to the current user, authorization scope, normalized filters, page size, and sort contract; invalid or stale cursors fail closed and refresh safely (implemented by Shashi).
- Preserved the existing `page`/`per_page` inputs, page metadata, sort order, selectors, and offset fallback for custom sorts, pre-sales ordering, and non-cursor callers (implemented by Shashi).

### Tests and documentation

- Added cursor tamper, actor/scope isolation, server query-shape, and client history contracts; verified full PHP/JavaScript syntax, 81 fast contracts, 14 disposable native WordPress/MySQL tests, and live wp-admin/frontend pagination without console errors (implemented by Shashi).

## [1.99.2] - 2026-08-30

### Scalable read models

- Started production/scalability Phase 3 with an authorization-scoped dashboard read model keyed by reporting range, owner/global visibility, normalized roles, relevant settings, and schema versions (implemented by Shashi).
- Added short-lived WordPress object-cache storage with transient fallback, stale-while-rebuild stampede protection, and fail-open correct database reads when cache storage is unavailable (implemented by Shashi).
- Added generation invalidation after lead lifecycle changes, routing assignments/handoffs, deletion/reset, quotation value projection, aggregate-affecting settings, and retention work (implemented by Shashi).

### Tests and documentation

- Added cache reuse, role-scope isolation, and mutation/settings invalidation contracts; verified 79 fast contracts, 14 disposable native WordPress/MySQL tests, and PHP syntax (implemented by Shashi).

## [1.99.1] - 2026-08-30

### Performance evidence

- Started production/scalability Phase 2 with a read-only, production-refusing query-plan manifest and capture for ten critical dashboard, lead-list, handoff, Kanban, task, activity, CAPI, and quotation SQL shapes (implemented by Shashi).
- Added aggregate optimizer steps, plugin index/cardinality inventory, full-scan/filesort summaries, dataset/build metadata, and explicit privacy exclusions; no runtime query or schema changed without target-profile evidence (implemented by Shashi).

### Tests and documentation

- Added fast safety/coverage contracts and a disposable native MySQL EXPLAIN/index capture; verified 75 fast contracts and 14 native tests (implemented by Shashi).

## [1.99.0] - 2026-08-30

### Production security

- Started production/scalability Phase 1 with production-default MCP shutdown, emergency disable, per-tool grants, bounded request/query budgets, credential rotation/revocation fingerprints, and aggregate audit attribution (implemented by Shashi).
- Added trusted-proxy-aware rate-limit identity and field-specific floor-plan/quote extension, detected-MIME, 10 MB size, and attachment ownership enforcement (implemented by Shashi).

### Tests and documentation

- Added MCP policy/disable, credential metadata, trusted IPv4/IPv6 proxy, upload policy, and handler-wiring contracts plus the production security configuration guide; verified 73 fast contracts and 13 disposable native WordPress/MySQL tests (implemented by Shashi).

## [1.98.4] - 2026-08-30

### Performance tooling

- Added deterministic, PII-free small/expected/maximum dataset profiles covering leads, activities, tasks, touchpoints, retained logs, and complete quotation revision/section/item graphs (implemented by Shashi).
- Added a safe-by-default dry-run command with explicit seed/reset confirmations, non-production enforcement, bounded batches, reserved synthetic markers, cardinality verification, partial-run recovery, and run-scoped cleanup that preserves existing CRM rows (implemented by Shashi).

### Tests

- Added profile-envelope, determinism, reserved-identifier, safety-gate, and disposable seed/reset contracts; verified command help, dry-run JSON, PHP syntax, 70 fast behavioral contracts, and 13 native WordPress/MySQL tests (implemented by Shashi).

## [1.98.3] - 2026-08-30

### Performance tooling

- Started production/scalability Phase 0 with a CLI environment/capacity capture, a 12-scenario critical benchmark manifest, and a structured baseline evidence record (implemented by Shashi).
- Kept capture output aggregate-only while recording runtime/schema versions, plugin table/index size estimates, autoload size, and WP-Cron/Action Scheduler state (implemented by Shashi).

### Tests

- Verified all PHP syntax, 67 fast contracts, 12 disposable native WordPress/MySQL tests, command help, and a live aggregate capture producing valid versioned JSON for all 19 plugin tables and 12 scenarios (implemented by Shashi).

## [1.98.2] - 2026-08-30

### Documentation

- Added a detailed staged plan for production release gates and evidence-led scalability work, including targets, security/configuration, query/index analysis, cache correctness, cursor pagination, resumable jobs, hotspot extraction, horizontal infrastructure, observability, load/recovery qualification, rollout, rollback, and stop conditions (implemented by Shashi).
- Reopened the production and scalability debt register with measured remediation criteria; runtime behavior, schema, options, stored values, and public contracts remain unchanged (implemented by Shashi).

## [1.98.1] - 2026-08-30

### Changed

- Completed Phase 5 of the admin facade refactor and closed the evidence-backed facade debt with the established 75-method compatibility surface and 432-line facade unchanged (implemented by Shashi).
- Recorded the final wp-admin, frontend workspace, access, responsive, interaction, console, asset, and network verification matrix without changing schema, options, stored values, or public contracts (implemented by Shashi).

### Tests

- Verified full PHP syntax, 67 fast contracts, 12 disposable native WordPress/MySQL tests, and 14 live administrator routes with no failed requests or console warnings/errors (implemented by Shashi).

## [1.98.0] - 2026-08-30

### Changed

- Completed Phase 4 of the admin facade refactor with focused asset, shortcode/workspace, account, presentation, and lead notification/reminder boundaries behind all established `IDLM_Admin` compatibility methods (implemented by Shashi).
- Preserved hooks, shortcode callbacks, asset handles and contexts, quotation shared-asset calls, authorization, account/credential handling, selectors, output, options, and stored values while reducing `includes/class-admin.php` from 949 to 432 lines (implemented by Shashi).

### Tests

- Added Phase 4 delegate, boundary, support-class-size, and facade-size contracts; verified 67 fast contracts, 12 disposable native WordPress/MySQL tests, and full PHP syntax checks (implemented by Shashi).

## [1.97.2] - 2026-08-30

### Changed

- Completed Phase 3 of the admin facade refactor with `IDLM_Admin_Settings_Renderer`, an explicit settings presentation context, and ten guarded section templates (implemented by Shashi).
- Preserved the public callback, settings fields/tabs, selectors, success notice, admin/frontend output, capability/nonce order, option keys, and credential handling; protected credential values remain outside the shared template context while `includes/class-admin.php` decreased from 2,950 to 949 lines (implemented by Shashi).

### Tests

- Added settings-renderer, guarded-template, presentation-boundary, template-size, delegate, and facade-size contracts; verified 66 fast tests, 12 disposable native WordPress/MySQL tests, and full PHP syntax checks (implemented by Shashi).

## [1.97.1] - 2026-08-30

### Changed

- Started Phase 3 of the admin facade refactor by moving settings POST detection, capability/nonce validation, sanitization, option persistence, secret persistence, and grouped-routing synchronization into `IDLM_Admin_Settings_Controller` (implemented by Shashi).
- Preserved the public settings callback, success notice, form/request fields, option keys, validation defaults, and stored behavior while reducing `includes/class-admin.php` from 3,234 to 2,950 lines (implemented by Shashi).

### Tests

- Added controller/delegate/security-order and facade-size contracts; verified 66 fast tests, 12 disposable native WordPress/MySQL tests, and full PHP syntax checks (implemented by Shashi).

## [1.97.0] - 2026-08-30

### Changed

- Completed Phase 2 of the admin facade refactor with `IDLM_Admin_Leads_Renderer` plus guarded leads and common-overlay templates using explicit presentation contexts (implemented by Shashi).
- Preserved the public leads/overlay callbacks, access policy, routing settings, imports/exports, bulk controls, drawers, tasks, modals, selectors, and shortcode output behavior (implemented by Shashi).
- Reduced `includes/class-admin.php` from 4,537 to 3,234 lines without changing schema, options, or stored values (implemented by Shashi).

### Tests

- Added Phase 2 delegate, template-boundary, selector, and facade-size contracts; verified 66 fast tests, 12 disposable native WordPress/MySQL tests, full PHP syntax checks, and a live Leads-page interaction smoke check without console errors (implemented by Shashi).

## [1.96.0] - 2026-08-30

### Changed

- Completed Phase 1 of the admin facade refactor by adding focused dashboard, Kanban, CAPI log, and audience log renderers with guarded nested templates and explicit contexts (implemented by Shashi).
- Preserved the four public `IDLM_Admin` callbacks as delegates and retained capabilities, nonces, query limits, shortcode output, selectors, JavaScript entry points, and module assets (implemented by Shashi).
- Reduced `includes/class-admin.php` from 5,346 to 4,537 lines without changing schema or stored values (implemented by Shashi).

### Tests

- Added renderer/delegate/nested-template contracts; verified 65 fast tests, all 12 disposable native WordPress/MySQL tests, and live wp-admin smoke checks for the four extracted pages without browser console errors (implemented by Shashi).

## [1.95.2] - 2026-08-30

### Tests

- Completed Phase 0 of the admin facade refactor with characterization contracts for all 75 public methods, hook/AJAX/shortcode/menu registrations, asset contexts, critical selectors, role/object access, native page rendering, and settings-save security/persistence behavior (implemented by Shashi).
- Expanded the verified baselines from 57 to 64 fast tests and from 10 to 12 disposable native WordPress/MySQL tests (implemented by Shashi).

## [1.95.1] - 2026-08-30

### Documentation

- Added a detailed, staged, compatibility-first implementation plan for reducing `IDLM_Admin` to a thin facade and restored the oversized admin file to the low-priority debt register until the plan is verified complete (implemented by Shashi).

## [1.95.0] - 2026-08-30

### Changed

- Added a guarded `IDLM_View` template boundary and extracted shared access, workspace, and account presentation; moved the workspace pre-collapse script into its registered asset (implemented by Shashi).
- Added grouped non-secret routing settings with an idempotent legacy migration and mirrored compatibility keys (implemented by Shashi).
- Made MCP WordPress/error-log paths environment-driven, removed Local-specific paths and root runtime artifacts, and added `.distignore` (implemented by Shashi).
- Archived the complete historical changelog/readme under `docs/legacy/` and retained concise current public release notes (implemented by Shashi).

### Tests

- Added guarded template, grouped settings migration/default, and MCP path regression contracts; verified 57 fast behaviors and all 10 disposable native WordPress/MySQL integration tests (implemented by Shashi).

## [1.94.1] - 2026-08-29

### Fixed

- Restored frontend quotation date filtering and normalized empty pagination output for PHP 8.x (implemented by Shashi).

### Tests

- Added behavioral and native WordPress coverage for the quotation list route and date handling (implemented by Shashi).

## [1.94.0] - 2026-08-29

### Security and integrity

- Added encrypted/environment-backed scoped credentials, header-only authentication, redaction and retention controls (implemented by Shashi).
- Added stable workflow/access policy, atomic routing state, transactional lead deletion, and integrity audit/repair (implemented by Shashi).

### Presentation and tests

- Replaced quotation print DOM rewriting with semantic server-rendered markup (implemented by Shashi).
- Added PHP/WordPress matrix CI and expanded behavioral/native coverage (implemented by Shashi).

## [1.93.0] - 2026-08-29

### Changed

- Split authenticated admin AJAX, quotation controller/renderer, and lead lifecycle behavior into focused classes behind compatibility facades (implemented by Shashi).

## [1.92.0–1.92.5] - 2026-08-28 to 2026-08-29

### Changed

- Hardened REST/MCP trust boundaries and bulk lead authorization (implemented by Shashi).
- Extracted quotation calculator, repository, schema, catalogue/list query, and lead-access boundaries without changing public contracts (implemented by Shashi).
- Added the behavioral and native WordPress/MySQL regression baselines (implemented by Shashi).

Earlier release-by-release notes remain available in the archived full changelog.
