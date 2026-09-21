=== Interior Design Lead Manager ===
Contributors: shashi-protecto
Tags: lead-manager, interior-design, crm, lead-tracking, duplicate-detection
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.102.38
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

= 1.102.29 =
* Added server-side sorting for catalogue items by code, category, item, unit, wood, rate, or status. Builder: Shashi.

= 1.102.28 =
* Retains recurring audience/CAPI diagnostic records and CAPI audit logs for seven days, with one-time cleanup of older records after an update or backup restore. Builder: Shashi.

= 1.102.27 =
* Raised the backup restore limit to 100 MB, added floating restore notifications, and reconciles the state-event amount field before a live backup restore. Builder: Shashi.

= 1.102.26 =
* Aligned Won dashboard breakdowns to close dates and repaired stale conversion timestamps on non-Won leads. Builder: Shashi.

= 1.102.25 =
* Fixed Sales Rep Performance Won, revenue, and sales-cycle reporting to use the Won close date. Builder: Shashi.

= 1.102.24 =
* Added protected backup and restore, plus dashboard and settings interface refinements. Builder: Shashi.

= 1.102.23 =
* Positioned the collapsed workspace account menu as a fixed overlay so it cannot be clipped or hidden behind dashboard content. Builder: Shashi.

= 1.102.22 =
* Fixed the collapsed workspace sidebar account menu so Profile and Logout remain visible and clickable. Builder: Shashi.

= 1.102.21 =
* Removed Pipeline/Kanban and Quotations from the Pre-sales workspace and denied direct access to both areas for Pre-sales users. Builder: Shashi.

= 1.102.20 =
* Made the Avg. Deal Value trend a continuous cumulative average across the selected period instead of showing isolated daily deal sizes. Builder: Shashi.

= 1.102.19 =
* Added a manager-only Pre-sales Activity dashboard widget for direct calls, WhatsApp outreach and replies, call-status updates, follow-up tasks, qualification, and sales handoffs. Builder: Shashi.

= 1.102.18 =
* Corrected the Avg. Deal Value trend so dates with no Won deal appear as gaps rather than misleading ₹0 values. Builder: Shashi.

= 1.102.17 =
* Replaced Sales dashboard average connection time with average lead-creation-to-close time for the Won summary. Builder: Shashi.

= 1.102.16 =
* Restored Won Deals and Revenue Trend for legacy closes using canonical conversion timestamps while keeping newer immutable transition events authoritative. Builder: Shashi.

= 1.102.15 =
* Restored dashboard selector buttons after data refresh so Sales, Marketing, disqualification, and Business Mix chips remain interactive. Builder: Shashi.

= 1.102.14 =
* Merged recorded dashboard events with non-duplicating legacy fallback data per lead, preventing partial event history from hiding Lost-reason and assignment records. Builder: Shashi.

= 1.102.13 =
* Restored legacy dashboard Lost-reason and assignment totals with clearly-labelled current-state fallbacks when immutable event reporting was not yet collecting data. Builder: Shashi.

= 1.102.12 =
* Removed the daily-count disclosure and Landing Page Performance dashboard sections as requested. Builder: Shashi.

= 1.102.11 =
* Matched the live Sales and Marketing dashboard body to the supplied reference, including its charts, widgets, tables, selectors, and data dimensions. Builder: Shashi.

= 1.102.10 =
* Reconciled lead CSV mapping, Qualified-row validation, duplicate preflight counts, studio defaults, and total-row accounting. Builder: Shashi.

= 1.102.9 =
* Made the Sales Dashboard truthful about reporting periods: current open pipeline inventory is separate from period events, and lost/won/assignment reporting now uses immutable transition records going forward. Builder: Shashi.

= 1.102.8 =
* Restored adding Pipeline stages from General Settings. Builder: Shashi.

= 1.102.7 =
* Restored Kanban card rendering after removal of the synthetic handoff column. Builder: Shashi.

= 1.102.6 =
* Removed the synthetic Kanban handoff column and its supporting logic. Builder: Shashi.

= 1.102.5 =
* Fixed repeated Kanban stage labels after a UI label rename and board refresh. Builder: Shashi.

= 1.102.4 =
* Aligned shared confirmation dialog typography with the CRM interface. Builder: Shashi.

= 1.102.3 =
* Added a confirmation warning before removing a Pipeline stage. Builder: Shashi.

= 1.102.2 =
* Improved spacing and grouping in General Settings’ Lead Fields & Options card. Builder: Shashi.

= 1.102.1 =
* Removed legacy client branding from default settings, quotation output, and documentation. Builder: Shashi.

= 1.102.0 =
* Added UI-only aliases for lead-field labels, pipeline stages, and configurable options, plus drag-and-drop pipeline ordering without changing stored CRM values. Builder: Shashi.

= 1.101.99 =
* Sorted lead drawer tasks with overdue pending tasks first, followed by other pending tasks and completed work. Builder: Shashi.

= 1.101.98 =
* Added a live overdue-task notification count to the Tasks tab in the lead details drawer. Builder: Shashi.

= 1.101.95 =
* Added a Needs update indicator to stale Kanban cards and compacted the task/status pills so multiple indicators fit in one footer row. Builder: Shashi.

= 1.101.93 =
* Made every clickable Leads and Kanban filter pill clear its applied filter when clicked again. Builder: Shashi.

= 1.101.77 =
* Added current-scope dashboard data-quality guardrails for lead freshness, UTM attribution coverage, intake-owner coverage, and first-contact timing coverage. Builder: Shashi.

= 1.101.76 =
* Corrected dashboard metric grain: current open-pipeline snapshots are separate from selected-period Won events and Won revenue. Monthly targets show only on the current-month view; duplicate growth now uses an adverse trend cue. Builder: Shashi.

= 1.101.75 =
* Centered the collapsed-sidebar My Account button and positioned its menu beside the compact sidebar. Builder: Shashi.

= 1.101.74 =
* Centered desktop Kanban action glyphs within their compact icon buttons. Builder: Shashi.

= 1.101.73 =
* Further reduced desktop Kanban drag and move icons to a minimal 24px secondary-control scale. Builder: Shashi.

= 1.101.72 =
* Reduced desktop Kanban drag and move-stage controls to compact labelled icon actions, while retaining the larger labelled mobile move control. Builder: Shashi.

= 1.101.71 =
* Reduced Kanban source, budget, and status badges to a quieter reference-label scale. Builder: Shashi.

= 1.101.70 =
* Consolidated the Kanban email and phone details into one compact inline row, preserving the phone number and truncating only long email addresses. Builder: Shashi.

= 1.101.69 =
* Removed the Kanban title’s link-style decoration because selecting any part of the card already opens the lead. Builder: Shashi.

= 1.101.68 =
* Tightened Kanban card typography, chips, and desktop stage controls for a denser CRM pipeline view; mobile controls retain their larger touch target. Builder: Shashi.

= 1.101.67 =
* Prevented dense Kanban columns from shrinking lead cards, so each card keeps its complete details and action footer. Builder: Shashi.

= 1.101.66 =
* Refined Kanban lead cards with clearer identity, readable contact details, naturally sized source/budget/status chips, and a compact action footer. Builder: Shashi.

= 1.101.65 =
* Consolidated the duplicate wp-admin CRM into the frontend workspace. WordPress Admin now provides a compact Lead Manager utility page with workspace shortcode reference and version information; legacy CRM admin links safely redirect to their matching frontend workspace section when configured.

= 1.101.64 =
* Standardized the WordPress admin CRM routes around a compact, collapsible sidebar and fixed operational header strip, while preserving current routes, navigation labels, capabilities, and lead search behavior.

= 1.101.63 =
* Hardened lead/comment/file ownership, pre-sales drawer fields, task validation and generated-task controls.
* Retained uncertain inbound identities for review; queued bounded Interakt backfills with retries and request deduplication.
* Made CRM reset transactional with complete runtime cleanup and monotonic lead IDs. Builder: Shashi.

= 1.101.62 =

* Date: 2026-09-03
* Author / Builder: Shashi
* Replaced the settings-toast dismiss glyph with an inline SVG close icon, made every close action reliable, and refined compact content-width toast sizing and typography.

= 1.101.61 =

* Settings toast typography now follows the compact design-system scale and uses a load-order-safe CSS-drawn accessible close icon.

= 1.101.60 =

* Settings notices now use high-contrast top-centre toast surfaces with visible accessible dismiss buttons.

= 1.101.59 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Settings notices now appear as dismissible floating toasts. Success and informational notices close after four seconds, pausing while hovered or focused; errors and action-required notices remain visible.

= 1.101.58 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Consolidated pipeline ordering and Kanban header-total settings into one accessible workflow editor, grouped reusable lead options, and made lead-capture mappings status-led, keyboard-accessible, and safe from API-key exposure.

= 1.101.57 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Added accessible Move up and Move down controls to reorder custom stage and option lists before saving. The saved stage order now persists throughout the CRM.

= 1.101.56 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Hardened CRM-owned task, drawer, action, payload-log, and routing-overlay presentation against theme and page-builder style leakage, including narrow-screen containment.

= 1.101.55 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Released the completed CRM usability and accessibility refinements: shared focus-managed dialogs, responsive dashboard/Kanban/leads/quotation workflows, white-label design tokens, lazy log-payload loading, bounded lead-history reads, and matching native settings-save coverage.

= 1.101.54 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Completed Lead Routing popup Escape handling with a cache-safe asset version so inline fallback confirmation closes without closing the parent dialog.

= 1.101.53 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Made the Lead Routing popup reliably single-column on narrow screens and made fallback confirmation Escape-safe with focus restoration.

= 1.101.52 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Simplified Lead Routing rules with plain-language outcomes, an Advanced safeguards disclosure, and conditional recovery and handoff controls that retain saved settings while inactive.

= 1.101.51 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Applied saved white-label colours to CRM dialogs and made outbound-call outcome validation accessible and focus-managed.

= 1.101.50 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Made the Lead Routing popup focus-managed, accessible, unsaved-change safe, and denser on desktop while preserving routing settings and fallback operations.

= 1.101.49 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Added the Lead Routing popup audit, CRM design-system reference map, and implementation delegation brief without changing runtime behavior.

= 1.101.48 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Centered the My Account navigation item and inset active navigation markers so they remain fully visible.

= 1.101.47 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Tokenized primary-button and active-tab effects and made the Active Tasks close control keyboard accessible.

= 1.101.46 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Derived soft, hover, glow, link, chart, quotation, dashboard, and Kanban colours from the configured white-label and semantic tokens.

= 1.101.45 =
* Limited input and button highlight borders to keyboard-visible focus and aligned the focus colour with white-label action tokens.

= 1.101.44 =
* Stabilized early-close validation placement so reason and evidence errors no longer shift the form controls inconsistently.

= 1.101.43 =
* Consolidated decision guidance into one copy block and moved actions to the right.

= 1.101.42 =
* Kept desktop decision guidance adjacent instead of distributing it across stretched columns.

= 1.101.41 =
* Placed decision guidance beside the explanatory copy on desktop and retained stacked mobile behavior.

= 1.101.40 =
* Compacted the lead decision box with tighter spacing, guidance text, and action controls.

= 1.101.39 =
* Fixed early-close validation errors so the message spans the form row without shifting controls.

= 1.101.38 =
* Reduced pre-sales cycle cards and action spacing for a more compact lead drawer.

= 1.101.37 =
* Refined the lead details drawer hierarchy, header density, panel radius, and workspace-aligned spacing.

= 1.101.36 =
* Aligned the Settings page canvas, shell spacing, and divider treatment with the other CRM views.

= 1.101.35 =
* Reduced empty dashboard chart footprint and improved responsive state presentation.

= 1.101.34 =
* Tightened dashboard, table, filter, navigation, quotation, and Kanban layout density for the SaaS CRM reference direction.

= 1.101.33 =
* Replaced the chart data button with a compact, keyboard-accessible text link.

= 1.101.32 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Standardized CRM button radius at 8px, reserved pill/circle shapes for status and floating actions, and enforced explicit light/dark button contrast pairs.

= 1.101.31 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Reduced secondary labels, metadata, dashboard stat labels, trends, and metric values for a denser CRM scan without changing primary headings or mobile input sizing.

= 1.101.30 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Introduced a compact, scalable CRM typography system for denser headings, cards, metrics, tables, boards, dialogs, and account views while retaining readable mobile text-entry controls.

= 1.101.29 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Added secure grouped white-label settings, image selection, accessible colour validation, live preview, theme-isolated styling, and branding across CRM sign-in, workspace, account, and frontend browser icon surfaces.

= 1.101.28 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Made active-theme independence a required part of the White label plan, including plugin-root tokens, scoped normalization, selector isolation, and cross-theme regression checks.

= 1.101.27 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Added the implementation-ready White label Settings plan, including layout, responsive behavior, validation, security, rollout surfaces, tests, and delivery phases.

= 1.101.26 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Corrected My Account password-row alignment by preventing the confirmation field from stretching when its validation message is hidden.

= 1.101.25 =
* Date: 2026-09-03
* Author / Builder: Shashi
* Replaced oversized password and credential Show/Hide buttons with compact accessible eye icons across access, account, and Settings forms.

= 1.101.24 =
* Date: 2026-09-02
* Author / Builder: Shashi
* Fixed Pipeline and lead option add/remove controls in the frontend CRM workspace by loading their Settings client script.

= 1.101.23 =
* Date: 2026-09-02
* Author / Builder: Shashi
* Completed final UI acceptance cleanup by replacing native browser prompts with shared accessible dialogs and non-blocking feedback, and removing the remaining inline transition styling.

= 1.101.22 =
* Fixed nested outreach dialogs so Log Call, Log WhatsApp, and Log Closure appear above the lead-details overlay. Builder: Shashi.

= 1.101.21 =
* Fixed call and WhatsApp activity logging so the dialog stays responsive during slow or failed requests and remains open with a retryable error. Builder: Shashi.

= 1.101.20 =
* Date: 2026-09-02
* Author / Builder: Shashi
* Implemented Phase 7 Users & Access and operational-log UX with labelled responsive filters, complete account metadata and feedback, semantic mobile log cards, inline retry status, and lazy copyable payload inspection in the shared accessible dialog.

= 1.101.19 =
* Date: 2026-09-02
* Author / Builder: Shashi
* Simplified the lead drawer header into a four-field operational summary with conditional reasons and created date on one quieter context line, removing the bordered-card clutter and status scrollbar.

= 1.101.18 =
* Date: 2026-09-02
* Author / Builder: Shashi
* Kept the lead drawer status summary to one scan-friendly row, with contained keyboard-accessible overflow for conditional fields instead of an expanding header.

= 1.101.17 =
* Date: 2026-09-02
* Author / Builder: Shashi
* Scoped frontend metric and lead-row button styles beneath the CRM workspace so Elementor Kit button defaults cannot override their intended backgrounds, text, radii, or spacing.

= 1.101.16 =
* Date: 2026-09-02
* Author / Builder: Shashi
* Implemented Phase 6 settings usability with structured add/remove option lists, goal-oriented tabs, sticky save-state feedback, consistent credential reveal/copy/configuration state, nearby connection-test results, and isolated credential-rotation forms.

= 1.101.15 =
* Date: 2026-09-02
* Author / Builder: Shashi
* Implemented Phase 5 access and account UX with password reveal controls and guidance, inline match feedback, post-redirect status focus, pending submission states, complete profile autocomplete metadata, and a focusable workspace skip target.

= 1.101.14 =
* Date: 2026-09-01
* Author / Builder: Shashi
* Implemented Phase 4 design-debt reduction with canonical presentation tokens/classes, accessible Settings tabs and shared dialogs, bounded legacy-style budgets, workspace role/state hooks, and a responsive visual-regression matrix.

= 1.101.13 =
* Date: 2026-09-01
* Author / Builder: Shashi
* Implemented Phase 3 responsive Leads, phone Kanban stage tabs/list, structured mobile quotation item cards, and affected breakpoint, motion, layer, and component-size normalization.

= 1.101.12 =
* Date: 2026-09-01
* Author / Builder: Shashi
* Implemented Phase 2 core UI workflows: keyboard Kanban movement, semantic cards, standardized lead selection/bulk/import/export flows, accessible Dashboard tabs, and equivalent chart data tables.

= 1.101.11 =
* Date: 2026-09-01
* Author / Builder: Shashi
* Implemented the Phase 1 accessibility and design-system foundation, including accessible action colours, consolidated tokens, focus/reduced-motion behavior, shared focus-managed dialogs, associated form labels, inline errors, and consistent icons.

= 1.101.10 =
* Date: 2026-09-01
* Author / Builder: Shashi
* Audited all CRM frontend surfaces and added a normative accessible design system with page-specific rules and a prioritized usability roadmap.

= 1.101.9 =
* Date: 2026-08-31
* Author / Builder: Shashi
* Repaired missing Won close timestamps from stage history and made Won-date reporting, filtering, exports, and conversion feeds use the actual close date.

= 1.101.8 =
* Date: 2026-08-31
* Author / Builder: Shashi
* Added previous and next lead controls to the lead drawer, including keyboard arrow navigation and unsaved-change protection.

= 1.101.7 =
* Date: 2026-08-31
* Author / Builder: Shashi
* Replaced the inline Kanban filter bar with the same floating filter button and accessible side drawer used by the Leads list.

= 1.101.6 =
* Date: 2026-08-31
* Author / Builder: Shashi
* Reduced CRM log-view load by bounding activity histories and loading large audience request/response payloads only when inspected.

= 1.101.5 =
* Split displayed shortcode delimiters across markup boundaries so nested frontend content filters cannot execute the examples (implemented by Shashi).

= 1.101.4 =
* Prevented shortcode examples on the General Settings tab from executing and recursively embedding CRM views (implemented by Shashi).

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
* Redesigned the CRM login as a responsive split workspace entry with clearer hierarchy, accessible focus treatment, password recovery, and the established design tokens.

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
