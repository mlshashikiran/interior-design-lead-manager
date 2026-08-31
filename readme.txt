=== Interior Design Lead Manager ===
Contributors: shashi-protecto
Tags: lead-manager, interior-design, crm, lead-tracking, duplicate-detection
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.101.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A premium WordPress CRM for interior-design lead capture, routing, qualification, analytics, integrations, and versioned quotations.

== Description ==

Interior Design Lead Manager replaces spreadsheet-based lead operations with a WordPress-native workspace.

Features:

* Webhooks for Meta Ads, Google Ads, LinkedIn Ads, website forms, and walk-ins.
* Normalized duplicate detection and attribution touchpoints.
* Assignment routing, pre-sales qualification, follow-up tasks, and sales handoff.
* Analytics dashboard, pipeline board, lead workspace, and audit logs.
* Meta/Google/LinkedIn conversion and audience integrations.
* Interakt WhatsApp event and reply handling.
* Lead-linked, versioned quotations with catalogue items and semantic printable output.
* Unified `[idlm_workspace]` frontend CRM with role-aware navigation.

== Installation ==

1. Upload the `interior-design-lead-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress Plugins screen.
3. Open Lead Manager and configure roles, routing, integrations, and quotation defaults.

== Frequently Asked Questions ==

= Can this plugin detect duplicate leads with different phone formats? =

Yes. Phone values are normalized and suffix-matched to reconcile common local and international formats.

= Does the plugin require Action Scheduler? =

No. Action Scheduler is used when available; WordPress Cron is the fallback.

== Changelog ==

= 1.101.3 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Removed New and Contacted from the Kanban sales board and added card sorting by lead age or name.

= 1.101.2 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Restricted the lead drawer header Stage editor to sales representatives and managers; pre-sales users retain a read-only Stage card.

= 1.101.1 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Made the lead drawer header Stage card editable with the existing secured stage-transition workflow.

= 1.101.0 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Added multi-file floor-plan uploads with individual View, Download, and Remove controls while retaining existing floor-plan files.

= 1.100.13 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Consolidated the Kanban header into an overall filtered lead count and made current-month won count/revenue use the Won conversion date regardless of the board's selected lead-date basis.

= 1.100.12 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Automatically generates unique ITEM-prefixed codes when a new catalogue item or imported CSV row has an empty item_code value.

= 1.100.11 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Added email-only CRM sign-in plus an in-workspace password request and completion flow backed by WordPress core authentication, reset keys, validation hooks, and session handling.

= 1.100.10 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Redesigned the CRM login as a responsive split workspace entry with clearer hierarchy, accessible focus treatment, password recovery, and the established Q Oak design tokens.

= 1.100.9 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Added a nonce-protected WordPress Sign Out option to My Account that returns users to the CRM page after logout.

= 1.100.8 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Added the native WordPress login form to protected CRM shortcode views for signed-out visitors while retaining the restricted-access message for signed-in users without CRM permission.

= 1.100.7 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Refined Sol/Terra/Luna delegation guidance, added model-delegation fallback and review verification requirements, and standardized effort selection to Low, Medium, and High.

= 1.100.6 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Added project-specific Sol, Terra, and Luna delegation boundaries with Light, Medium, and High effort-selection rules.

= 1.100.5 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Kept the Users & Access directory filter controls on one line, including on narrow screens.

= 1.100.1 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Added an administrator-only Users & Access workspace to create CRM users, change managed CRM roles, suspend or restore CRM access, and send password-reset links.
* CRM suspensions now deny representative and quotation access without deleting accounts or historical CRM ownership.
* Refined the Users & Access interface and made it available in the administrator frontend workspace navigation.

= 1.99.9 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Advanced production/scalability Phase 9 with independent promotion verification for the CI candidate ZIP, checksum, manifest, version, clean exact revision, completed matrix, GitHub run, file count, required runtime inventory, and single plugin directory.
* Added an aggregate staging-gate report with every backup, restore, deployment, state, monitoring, rollback, and controlled-smoke item pending; the verifier does not extract, deploy, mutate CRM state, or approve production.
* Added promotion, checksum, archive, pending-gate, and CI contracts; verified full PHP syntax and 98 fast contracts.

= 1.99.8 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Started production/scalability Phase 9 with a guarded release-artifact builder driven by `.distignore`.
* Added version, inventory, secret-signature, single-directory ZIP, checksum, revision, clean-tree, and CI-attestation gates plus CI artifact upload after the full test matrix.
* Dirty local builds require an explicit rehearsal override and cannot be marked production-approved; verified full PHP syntax, 93 fast contracts, and artifact ZIP/checksum inspection.

= 1.99.7 =

* Date: 2026-08-31
* Author / Builder: Shashi
* Started production/scalability Phase 8 with a dry-run-by-default, production-refusing authenticated load runner for the existing dashboard, lead-list, and Kanban read actions.
* Added bounded load controls, generated WordPress cookie/nonce authentication, anonymous temporary response storage, aggregate evidence, and the required not-executed resilience matrix.
* Preserved all production contracts and stored behavior; verified full PHP syntax, 89 fast contracts, 14 disposable native WordPress/MySQL tests, and a successful three-request local dashboard runner smoke check.

= 1.99.6 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Started production/scalability Phase 7 with an administrator-only aggregate operational-health report covering schemas, database, schedules, enabled credentials, storage, cache, Action Scheduler backlog, and recent faults.
* Added initial 5/15-minute queue and 1/10-failure warning/critical thresholds plus schema, queue, credential/third-party, and critical-health runbooks.
* Preserved public contracts and sensitive-data boundaries; verified full PHP syntax, 86 fast contracts, and 14 disposable native WordPress/MySQL tests.

= 1.99.5 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Started production/scalability Phase 6 with an administrator-only, aggregate read-only multi-node readiness preflight in System Health.
* Added shared database/media/configuration declarations plus persistent cache, Action Scheduler, controlled cron, and streamed-export checks without exposing infrastructure values.
* Documented the runtime-write inventory and deployment qualification sequence; verified full PHP syntax, 84 fast contracts, and 14 disposable native WordPress/MySQL tests.

= 1.99.4 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Started production/scalability Phase 5 by extracting the authenticated lead-detail read handler into a focused class behind the existing admin and AJAX delegates.
* Preserved the AJAX action, nonce, capability and object-level visibility checks, response payload, lead drawer markup, selectors, database reads, and stored behavior.
* Verified full PHP syntax, 82 fast contracts, 14 disposable native WordPress/MySQL tests, and the live frontend lead drawer without browser warnings/errors.

= 1.99.3 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Started production/scalability Phase 4 with signed, user/filter-bound keyset cursors for the default manager lead queue.
* Preserved page-number requests and offset fallback for custom sorts and pre-sales ordering while the shared client reuses cursors for sequential next/previous navigation.
* Verified cursor tamper/actor/scope contracts, 81 fast tests, 14 disposable native tests, and live wp-admin/frontend navigation.

= 1.99.2 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Started production/scalability Phase 3 with an authorization-scoped dashboard read model and a short-lived object-cache/transient cache.
* Added generation invalidation for lead lifecycle, assignment, deletion/reset, quotation values, relevant settings, and retention work, plus stampede protection and regression contracts.

= 1.99.1 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Started production/scalability Phase 2 with a read-only, production-refusing query-plan and index-inventory capture for ten critical global and role-restricted SQL shapes.
* Kept schema and runtime queries unchanged until target-profile endpoint measurements and optimizer evidence justify an index migration.
* Verified 75 fast contracts, 14 disposable native WordPress/MySQL tests, command help, local optimizer capture, and full PHP syntax checks.

= 1.99.0 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Started production/scalability Phase 1 with production-default MCP shutdown, emergency disable, least-privilege tool grants, bounded execution inputs, key revocation metadata, and aggregate audit attribution.
* Added trusted-proxy-aware rate limiting and field-specific floor-plan/quote upload validation with attachment ownership metadata.
* Verified 73 fast contracts, 13 disposable native WordPress/MySQL tests, and full PHP syntax checks.

= 1.98.4 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Added deterministic, PII-free small/expected/maximum performance dataset profiles for leads, activities, tasks, touchpoints, retained logs, quotations, revisions, sections, and items.
* Added dry-run manifests, exact mutation confirmations, non-production enforcement, batch controls, tagged verification, partial-run recovery, and run-scoped reset without deleting existing CRM data.
* Verified 70 fast contracts and 13 disposable native WordPress/MySQL tests, including real small-profile seed, cardinality validation, and scoped reset.

= 1.98.3 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Started Phase 0 of the production-scalability plan with an aggregate-only environment/capacity capture command, a 12-scenario critical benchmark manifest, and the performance evidence template.
* Captures runtime/schema versions, plugin table/index size estimates, autoload size, and WP-Cron/Action Scheduler state without exporting CRM rows, contacts, credentials, payloads, URLs, paths, or database names.

= 1.98.2 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Added the staged production-readiness and scalability plan covering measurable service targets, security/configuration gates, database/query optimization, caching, bounded jobs, code hotspots, multi-node infrastructure, observability, resilience tests, rollout, rollback, and stop criteria.
* Reopened the evidence-backed production and scalability debt register without changing runtime behavior, schema, options, public contracts, or stored values.

= 1.98.1 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Completed Phase 5 of the admin facade refactor with full PHP lint, 67 fast contracts, 12 disposable native WordPress/MySQL tests, and recorded live wp-admin/frontend verification.
* Verified the 432-line compatibility facade, all admin/workspace routes, desktop/tablet/mobile layouts, critical interactions, access restriction, asset loading, and zero console or failed-network errors without changing schema, options, or public contracts.

= 1.98.0 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Completed Phase 4 of the admin facade refactor by extracting asset loading, shortcode/workspace orchestration, presentation helpers, and lead notification/reminder helpers into focused classes.
* Preserved all 75 public methods, hooks, shortcode callbacks, asset handles/contexts, quotation compatibility, access behavior, selectors, and output while reducing `IDLM_Admin` from 949 to 432 lines; verified 67 fast and 12 native tests plus full PHP syntax checks.

= 1.97.2 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Completed Phase 3 of the admin facade refactor with `IDLM_Admin_Settings_Renderer` and ten guarded settings templates using explicit presentation context.
* Preserved settings fields, tabs, selectors, save behavior, capabilities, nonces, protected credential reads, and admin/frontend output while reducing `IDLM_Admin` from 2,950 to 949 lines; verified 66 fast and 12 native tests.

= 1.97.1 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Started Phase 3 of the admin facade refactor by extracting settings POST validation and persistence into `IDLM_Admin_Settings_Controller`.
* Preserved the settings callback, fields, notices, capability/nonce order, sanitization, routing mirrors, and secret-store behavior while reducing `IDLM_Admin` from 3,234 to 2,950 lines.

= 1.97.0 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Completed Phase 2 of the admin facade refactor by extracting leads context preparation and leads/common-overlay markup into a focused renderer and guarded templates.
* Preserved lead-list, routing, import/export, drawer, modal, task, capability, shortcode, and JavaScript selector contracts while reducing `IDLM_Admin` from 4,537 to 3,234 lines; verified 66 fast and 12 native tests.

= 1.96.0 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Completed Phase 1 of the admin facade refactor by extracting dashboard, Kanban, CAPI log, and audience log renderers into guarded templates while preserving callbacks, selectors, filters, nonces, and access behavior.
* Reduced `IDLM_Admin` by 809 lines and verified 65 fast contracts plus 12 disposable native WordPress/MySQL tests.

= 1.95.2 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Completed the admin-refactor characterization baseline with facade, hook, shortcode, menu, asset, selector, role/access, page-rendering, and settings-save contracts.

= 1.95.1 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Added the detailed compatibility-first implementation plan for reducing the oversized admin facade and reopened the corresponding low-priority debt item until verified completion.

= 1.95.0 =

* Date: 2026-08-30
* Author / Builder: Shashi
* Extracted guarded workspace, account, and access templates and moved workspace boot behavior into its module asset.
* Grouped non-secret routing settings with mirrored legacy compatibility keys and documented schemas.
* Removed release artifacts and Local-specific MCP fallbacks, added environment-driven paths and a distribution manifest.
* Archived full historical release notes and kept current public documentation concise.

= 1.94.1 =

* Date: 2026-08-29
* Author / Builder: Shashi
* Restored frontend quotation date filtering and removed empty-pagination PHP 8.x warnings.
* Added behavioral and native WordPress route coverage.

= 1.94.0 =

* Date: 2026-08-29
* Author / Builder: Shashi
* Added encrypted scoped credentials, stable access/state policies, transactional integrity controls, retention, semantic quotation printing, and matrix CI.

= 1.93.0 =

* Date: 2026-08-29
* Author / Builder: Shashi
* Split admin AJAX, quotation request/rendering, and lead lifecycle responsibilities behind compatibility facades.

The complete historical readme is preserved in `docs/legacy/README-FULL.txt`; release history is also available in `docs/legacy/CHANGELOG-FULL.md`.
