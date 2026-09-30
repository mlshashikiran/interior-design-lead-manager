# Changelog

## [1.102.38] - 2026-09-21

### Fixed

- Inbound repeat submissions now enrich the one lead matched by email or normalized phone and retain their touchpoint history without creating duplicate lead records, assignments, or pre-sales tasks. Conflicting identifiers are retained for review without entering the lead queue. Builder: Shashi.

## [1.102.37] - 2026-09-11

### Changed

- Open in Interakt now opens the lead's WhatsApp Inbox conversation by normalized phone number. Builder: Shashi.

## [1.102.36] - 2026-09-11

### Changed

- Changed Open in Interakt to open the WhatsApp Inbox with the configured filter and latest-activity sort. Builder: Shashi.

## [1.102.35] - 2026-09-11

### Added

- Added an Open in Interakt action to the lead-drawer WhatsApp tab when Interakt is enabled and inbound messages exist. Builder: Shashi.

## [1.102.34] - 2026-09-11

### Changed

- Changed Snooze Task to use a native date-and-time picker. Builder: Shashi.

## [1.102.33] - 2026-09-11

### Changed

- Removed the standalone WhatsApp workspace inbox and replaced the lead-drawer reply alert with a WhatsApp tab that lists inbound messages and timestamps, with a per-lead unread count. Builder: Shashi.

## [1.102.32] - 2026-09-11

### Added

- Added a scoped WhatsApp workspace inbox for Interakt customer replies, with lead context and an approved-template sender. Template names and variables are administrator-configured and validated on the server; live replies remain in Interakt. Builder: Shashi.

## [1.102.31] - 2026-09-10

### Changed

- Refined the Today’s work trigger into a docked bottom-right tab and compacted the task drawer’s groups, task metadata, controls, and standardized close buttons. Builder: Shashi.

### Fixed

- Kept the task drawer open while opening lead details and surfaced the lead phone number in queue rows. Builder: Shashi.

## [1.102.30] - 2026-09-10

### Changed

- Replaced the raw active-task backlog with a bounded Today’s work queue: overdue, due-today, next-seven-days, and one follow-up plan per lead. Completed tasks now live in separate history, and the floating indicator reports only due-today and overdue work. Builder: Shashi.

### Fixed

- Archived pending tasks on Lost leads and orphaned task owners during the one-time upgrade and through an administrator cleanup control. Lost-stage transitions now archive pending work, and manual task creation/rescheduling rejects past-due dates. Builder: Shashi.

## [1.102.29] - 2026-09-10

### Added

- Added catalogue-table sorting by code, category, item, unit, wood, rate, or status in ascending or descending order. Sorting is allowlisted on the server and retained across pagination. Builder: Shashi.

## [1.102.28] - 2026-09-10

### Changed

- Retains recurring per-lead audience/CAPI diagnostic activity and CAPI audit-table records for seven days. A versioned cleanup removes older diagnostic records on update, and the same policy runs immediately after a backup restore. Builder: Shashi.

## [1.102.27] - 2026-09-10

### Fixed

- Raised the JSON backup restore limit to 100 MB and repaired the core schema migration so live backups containing recorded Won/Lost event amounts restore correctly on local installations. Builder: Shashi.
- Rendered backup restore results as page-level floating notices with an accessible close icon and a three-second auto-dismiss. Builder: Shashi.

## [1.102.26] - 2026-09-10

### Fixed

- Made Channel Performance, Business Mix, and the Marketing Won trend report Won outcomes on the selected `conversion_time` close-date basis, while retaining clearly-labelled acquisition measures for lead quality and volume. Builder: Shashi.
- Added an idempotent cleanup for legacy `conversion_time` values on non-Won leads and clear that timestamp whenever a Won lead is reopened, preventing false future close reporting. Builder: Shashi.

## [1.102.25] - 2026-09-10

### Fixed

- Made Sales Rep Performance Won counts, Won revenue, and average/median sales-cycle calculations use the lead `conversion_time` reporting period, so deals created earlier and closed in the selected period are included. Builder: Shashi.

## [1.102.24] - 2026-09-10

### Added

- Added protected JSON backup and restore for CRM data and non-secret settings. Builder: Shashi.

### Changed

- Refined dashboard reporting, Conversion tracking, Automation, AI access, webhook, and Routing settings interfaces for clearer controls, consistent toggles, and responsive alignment. Builder: Shashi.

### Fixed

- Improved routing representative coverage with aligned role and studio columns. Builder: Shashi.

## [1.102.23] - 2026-09-09

### Fixed

- Positioned the collapsed workspace account menu as a fixed high-layer overlay, preventing it from being clipped or obscured by workspace content. Builder: Shashi.

## [1.102.22] - 2026-09-09

### Fixed

- Fixed the collapsed workspace sidebar account menu being clipped outside the sidebar, restoring Profile and Logout access. Builder: Shashi.

## [1.102.21] - 2026-09-09

### Changed

- Removed Pipeline/Kanban and Quotations from the Pre-sales workspace. Direct shortcode, legacy admin-link, and quotation-policy access now enforce the same restriction. Builder: Shashi.

## [1.102.20] - 2026-09-09

### Fixed

- Changed Avg. Deal Value from a per-close-day value to a continuous cumulative average over the selected reporting period. Builder: Shashi.

## [1.102.19] - 2026-09-09

### Added

- Added a manager-only Pre-sales Activity dashboard widget covering direct calls, WhatsApp outreach and replies, call-status updates, follow-up tasks, qualifications, and sales handoffs. Activity is attributed only to users with the Pre-sales CRM role. Builder: Shashi.

## [1.102.18] - 2026-09-09

### Fixed

- Corrected the Avg. Deal Value trend so dates with no Won deal render as gaps rather than ₹0 values. Builder: Shashi.

## [1.102.17] - 2026-09-09

### Changed

- Replaced the Sales Won-summary average connection time with average elapsed time from lead creation to recorded close. Builder: Shashi.

## [1.102.16] - 2026-09-09

### Fixed

- Restored Won Deals and Revenue Trend for legacy closes using canonical conversion timestamps without double-counting immutable transition events. Builder: Shashi.

## [1.102.15] - 2026-09-09

### Fixed

- Restored dashboard selector buttons after refresh; Sales, Marketing, disqualification, and Business Mix chips no longer remain disabled after the loading state. Builder: Shashi.

## [1.102.14] - 2026-09-09

### Fixed

- Merged immutable dashboard events with non-duplicating legacy per-lead fallback data, so partial event history no longer suppresses Lost-reason and assignment reporting. Builder: Shashi.

## [1.102.13] - 2026-09-09

### Fixed

- Restored dashboard Lost-reason and assignment totals on legacy CRM data using clearly-labelled current-state fallbacks only when immutable reporting events are absent. Won history remains event-only and is never reconstructed. Builder: Shashi.

## [1.102.12] - 2026-09-09

### Removed

- Removed the daily-count disclosure and Landing Page Performance dashboard sections. Builder: Shashi.

## [1.102.11] - 2026-09-09

### Changed

- Matched the live Sales and Marketing dashboard body to the supplied HTML reference, including its trend charts, panel composition, selector chips, campaign/ad-group explorer, performance tables, Business Mix dimensions, landing-page outcomes, disqualification reasons, and attribution-health data points. Builder: Shashi.

## [1.102.10] - 2026-09-09

### Fixed

- Reconciled lead CSV mapping and import validation, including Qualified project fields, deterministic one-to-one header matching, Preferred Studio defaults, full-row preflight counts, and explicit imported/duplicate/failed accounting. Builder: Shashi.

## [1.102.9] - 2026-09-09

### Fixed

- Made Sales Dashboard time semantics explicit: current open inventory and its operational risks are no longer presented as a period conversion funnel.
- Added immutable lifecycle/assignment reporting events, with Won/Lost amount snapshots, so new selected-period event metrics cannot be rewritten by later lead edits. Historic current-state rows are intentionally not inferred as historical events. Builder: Shashi.

## [1.102.8] - 2026-09-09

### Fixed

- Restored adding Pipeline stages from General Settings by correcting the dynamic pipeline-editor item construction. New stages include the expected drag handle and header-total control. Builder: Shashi.

## [1.102.7] - 2026-09-09

### Fixed

- Restored Kanban card rendering by removing a stale feed-only client condition left after the synthetic handoff column was removed. Builder: Shashi.

## [1.102.6] - 2026-09-09

### Removed

- Removed the synthetic Kanban handoff column and all of its supporting UI, board response, action-refresh, and feed logic. The underlying pre-sales handoff workflow is unchanged. Builder: Shashi.

## [1.102.5] - 2026-09-09

### Fixed

- Fixed renamed Kanban stage titles repeating after an AJAX refresh by applying UI aliases only to complete labels, never partial text fragments. Builder: Shashi.

## [1.102.4] - 2026-09-09

### Fixed

- Aligned shared action-dialog typography with the CRM interface, including dynamically created confirmation dialogs. Builder: Shashi.

## [1.102.3] - 2026-09-09

### Added

- Added a confirmation warning before a Pipeline stage is removed, explaining how existing leads are affected and requiring explicit confirmation before the pending settings save. Builder: Shashi.

## [1.102.2] - 2026-09-09

### Changed

- Improved the Lead Fields & Options card’s spacing, display-label grouping, and disclosure rhythm in General Settings. Builder: Shashi.

## [1.102.1] - 2026-09-09

### Changed

- Removed legacy client branding from defaults, quotation output, user-facing reset copy, code comments, and project documentation. Quotation identity now uses configured company settings. Builder: Shashi.

## [1.102.0] - 2026-09-09

### Added

- Added presentation-only aliases for Call Status, Lost Reason, Preferred Studio, Rooms, pipeline stages, and their configurable options. Stored values, integrations, filters, reports, and API contracts remain unchanged. Builder: Shashi.
- Replaced pipeline-stage arrow controls with drag-and-drop ordering while retaining the established ordered stage values. Builder: Shashi.

## [1.101.99] - 2026-09-09

### Changed

- Reordered the lead drawer task list so overdue pending tasks appear first, followed by other pending tasks and then completed tasks. Dated tasks precede undated tasks within their status group. Builder: Shashi.

## [1.101.98] - 2026-09-09

### Added

- Added a compact notification badge to the lead drawer’s Tasks tab showing the number of overdue pending tasks. It loads when the drawer opens and updates after task changes, remaining hidden when no tasks are overdue. Builder: Shashi.

## [1.101.95] - 2026-09-09

### Added

- Added a “Needs update” indicator to Kanban cards with no qualifying representative activity in the last seven days, using the same rule as the existing stale-lead filter. Builder: Shashi.

### Changed

- Reduced Kanban task and attention pill typography, icon size, padding, and spacing so Overdue and Needs update indicators fit together in one card-footer row. Builder: Shashi.

## [1.101.93] - 2026-09-09

### Changed

- Made every clickable Leads and Kanban filter pill toggle its filter: the first click applies the pill’s queue and the second click clears it. Builder: Shashi.

## [1.101.91] - 2026-09-07

### Fixed

- Corrected the Users, CAPI Audit, and Audience Sync filter badges so they count only visible filter criteria and no longer treat workspace routing fields as active filters. Builder: Shashi.

## [1.101.90] - 2026-09-07

### Fixed

- Corrected the live stale-reminder activity subquery to reference the outer lead row explicitly. Recent actions by the assigned representative now reliably remove leads from “Needs update.” Builder: Shashi.

## [1.101.89] - 2026-09-07

### Fixed

- Corrected the “Needs update” reminder to use recent qualifying activity by the assigned representative instead of the lead row timestamp. Automated audience and integration activity is excluded, while genuine rep actions clear the reminder for seven days. Builder: Shashi.

## [1.101.88] - 2026-09-07

### Fixed

- Corrected the Kanban new-assignment notification so a comment, call, task action, or other activity logged by the assigned sales representative clears the lead from their queue. Automated system activity does not clear it. Builder: Shashi.

## [1.101.87] - 2026-09-07

### Changed

- Renamed the Kanban new-assignment notification to “New leads assigned” and its filter to “awaiting first action,” so it clearly prompts sales reps to act rather than suggesting that the lead itself needs an update. Builder: Shashi.

## [1.101.86] - 2026-09-07

### Added

- Added a Kanban pill that highlights active newly assigned sales leads awaiting their first action. It filters directly to that queue and is scoped to the signed-in representative where lead access is restricted. Builder: Shashi.

## [1.101.85] - 2026-09-07

### Changed

- Updated quotation totals with discounted wooden and other subtotals, optional globally configured Soft Close Addition, total discount, GST, and nearest-rupee round-off. Builder: Shashi.
- Improved the Preview / Save PDF quotation summary and item-section borders. Builder: Shashi.

## [1.101.84] - 2026-09-06

### Changed

- Removed Next Action and Callback columns from the Pre-Sales leads table to make room for remaining operational fields. Builder: Shashi.

## [1.101.83] - 2026-09-06

### Changed

- Removed the Created Date column from the Pre-Sales leads table so remaining columns can use the available width. Builder: Shashi.

## [1.101.82] - 2026-09-06

### Fixed

- Kept the Pre-Sales Log Call and Log WA controls inline; the conditional Decide control uses its own row. Builder: Shashi.

## [1.101.81] - 2026-09-06

### Fixed

- Tightened the Pre-Sales table action column after removal of the generic Open action; the conditional Decide control still uses the full mobile row. Builder: Shashi.

## [1.101.80] - 2026-09-06

### Changed

- Removed the generic Open action from the Pre-Sales leads-table action column while retaining the Decide action when a lead requires a qualification decision. Builder: Shashi.

## [1.101.79] - 2026-09-06

### Changed

- Reduced Kanban card titles to 12px. Builder: Shashi.

## [1.101.78] - 2026-09-06

### Changed

- Removed the Data-quality guardrails metric section from the dashboard frontend. The underlying aggregate data remains available to authenticated dashboard reads. Builder: Shashi.

## [1.101.77] - 2026-09-06

### Added

- Added current-scope dashboard data-quality guardrails for lead freshness, UTM attribution coverage, intake-owner coverage, and first-contact timing coverage. Builder: Shashi.

## [1.101.76] - 2026-09-06

### Fixed

- Separated current open-pipeline snapshots from selected-period Won events and Won revenue, removing the invalid open-pipeline-versus-Won-revenue comparison. Builder: Shashi.
- Restricted monthly targets to the current-month view, added straight-line pace context, and reversed duplicate-growth visual semantics so increases are adverse. Builder: Shashi.

## [1.101.75] - 2026-09-06

### Collapsed account navigation

- Centered the My Account icon in the collapsed sidebar and positioned its account menu beside the compact navigation. Builder: Shashi.

## [1.101.74] - 2026-09-06

### Centered Kanban action glyphs

- Centered the desktop drag and move glyphs within their compact icon controls. Builder: Shashi.

## [1.101.73] - 2026-09-06

### Minimal desktop Kanban actions

- Further reduced desktop Kanban drag and move controls to a 24px icon scale without changing the mobile action layout. Builder: Shashi.

## [1.101.72] - 2026-09-06

### Compact desktop Kanban actions

- Reduced desktop drag and move-stage controls to unobtrusive icon-only secondary actions; the mobile move control remains labelled and touch-sized. Builder: Shashi.

## [1.101.71] - 2026-09-06

### Quiet Kanban reference labels

- Reduced source, budget, and call-status badge size and spacing so they read as supporting references rather than primary controls. Builder: Shashi.

## [1.101.70] - 2026-09-06

### Compact Kanban contact row

- Reduced Kanban contact metadata and placed email and phone on one line; long email values truncate before the phone number. Builder: Shashi.

## [1.101.69] - 2026-09-06

### Kanban card identity treatment

- Removed the misleading link-style title treatment; the complete card remains the single click target for opening lead details. Builder: Shashi.

## [1.101.68] - 2026-09-06

### Compact Kanban card controls

- Reduced desktop Kanban card title, metadata, badge, and action-control sizing for faster pipeline scanning. Mobile retains the established 44px move control. Builder: Shashi.

## [1.101.67] - 2026-09-06

### Kanban dense-column rendering

- Prevented the Kanban flex column from shrinking lead cards when a stage contains many leads, retaining every card's contact details, chips, and action footer within the column's native scroll area. Builder: Shashi.

## [1.101.66] - 2026-09-06

### Kanban card hierarchy

- Refined lead cards with a blue identity treatment, readable contact metadata, naturally sized source/budget/status chips, and a compact action footer. Existing drawer, move-stage, ownership, and drag interactions are unchanged. Builder: Shashi.

## [1.101.65] - 2026-09-06

### Frontend workspace consolidation

- Removed the duplicate wp-admin CRM menu, navigation shell, and associated shell styles. The remaining administrator-only Lead Manager page provides the plugin version, shortcode reference, and a workspace link. Builder: Shashi.
- Redirected legacy CRM admin URLs to their capability-matched frontend workspace section when a published `[idlm_workspace]` page is available. Frontend Users & Access and credential-rotation submissions now retain their workspace return URL. Builder: Shashi.

## [1.101.64] - 2026-09-06

### Standardized CRM admin shell

- Added a scoped CRM sidebar and a 64px fixed header strip across all plugin admin routes. The shell preserves existing URLs, navigation labels, capabilities, page controls, and lead-search behavior. Builder: Shashi.
- Added desktop collapse persistence, a mobile off-canvas navigation treatment, WordPress admin-bar offsets, Escape/backdrop dismissal, and visible keyboard focus states. Builder: Shashi.

## [1.101.63] - 2026-09-06

### High-priority CRM integrity fixes

- Enforced current lead ownership for mutations even when all leads are visible; comment handlers now check the linked lead, and uploads require and record lead ownership. Builder: Shashi.
- Enforced pre-sales drawer field restrictions, preserved omitted fields, validated commercial values, and prevented cross-lead reuse of newly bound attachments. Builder: Shashi.
- Validated manual task types, priorities, titles and dates; qualification tasks remain controlled by their workflow in both server and client. Builder: Shashi.
- Retained ambiguous or conflicting inbound identities as actionable inquiries with duplicate-review activity; exact external touchpoints remain replay-safe. Builder: Shashi.
- Replaced synchronous administrative Interakt backfills with bounded, request-idempotent Action Scheduler/WP-Cron jobs, capped retries, current-state checks and truthful queue progress. Builder: Shashi.
- Replaced unchecked reset truncation with checked InnoDB deletes, rollback, runtime queue cleanup and scheduled-job cancellation without recycling lead IDs. Builder: Shashi.

## [1.101.62] - 2026-09-03

### Settings toast interaction and sizing

- Replaced the broken dismiss glyph with a real inline SVG X, explicitly intercepting each dismiss action before WordPress notice handling so it cancels the timer and uses the existing reduced-motion-aware removal transition. Builder: Shashi.
- Made settings toasts content-width by default with safe viewport bounds, and reduced their text to a compact 13px regular-weight treatment. Builder: Shashi.

## [1.101.61] - 2026-09-03

### Settings toast refinement

- Matched toast text to the compact design-system type scale, explicitly applied the plugin font stack to the detached toast stack, and replaced the broken icon-font close glyph with a load-order-safe CSS-drawn X. Builder: Shashi.

## [1.101.60] - 2026-09-03

### Settings toast presentation

- Repositioned settings notices to a visible top-centre stack with opaque status colours, responsive sizing, and 44px dismiss controls.
- Added a fallback accessible dismiss button when WordPress common.js has not injected one (implemented by Shashi).

Current release notes are kept concise. The complete pre-maintenance history is preserved in [`docs/legacy/CHANGELOG-FULL.md`](docs/legacy/CHANGELOG-FULL.md).

## [1.101.59] - 2026-09-03

### Settings notification toasts

- Promoted settings-screen WordPress notices into a fixed, mobile-safe toast stack without changing their server-side generation or dismiss controls (implemented by Shashi).
- Success and informational notices automatically close after four seconds, pausing while hovered or keyboard-focused; warnings, errors, and notices needing action remain visible.
- Preserved per-notice live-region semantics, reduced-motion support, and non-shifting page layout while supporting multiple concurrent notices.

## [1.101.58] - 2026-09-03

### CRM settings workflow and lead-capture clarity

- Consolidated ordered pipeline stages and each stage’s Kanban header total into one responsive, accessible editor; existing option names, submitted fields, and persistence remain unchanged (implemented by Shashi).
- Grouped lead-option dictionaries behind count-labelled native disclosures and reworked source mappings into semantic keyboard-operable tabs with contact-route status and progressive field groups.
- Removed plaintext webhook API-key output from mapping details; administrators use the protected credential control above instead (implemented by Shashi).

## [1.101.57] - 2026-09-03

### Accessible custom stage ordering

- Added Move up and Move down controls to the shared settings option editor. They maintain the list and hidden submitted values in the selected order, so custom pipeline stages persist in that order across the CRM (implemented by Shashi).
- Disabled unavailable move controls, preserved focus on the action after a move, and announced reorder changes to assistive technology.

## [1.101.56] - 2026-09-03

### Theme-isolated CRM overlay hardening

- Hardened task sidebar states, action-dialog controls, lead-drawer load/failure states, payload-log overflow, and routing-modal scrolling against theme and page-builder styling leakage (implemented by Shashi).
- Stacked overlay fields and actions cleanly on narrow screens without changing CRM behavior, AJAX contracts, permissions, or saved data.

## [1.101.55] - 2026-09-03

### CRM usability, accessibility, and bounded operations release

- Released the completed shared focus-managed dialog, responsive dashboard/Kanban/leads/quotation, and white-label design-token refinements across the CRM workspace.
- Added lazy privileged log-payload retrieval and bounded lead-history reads to keep operational screens responsive while preserving their existing authorization boundaries (implemented by Shashi).
- Corrected native settings-save coverage to submit the required white-label form payload, matching the rendered settings form (implemented by Shashi).

## [1.101.54] - 2026-09-03

### Cache-safe routing popup Escape handling

- Advanced the asset version so the completed capture-phase Escape handling reaches browsers that cached the earlier routing-popup script.
- Escape now reliably dismisses only the inline fallback confirmation and restores focus to its trigger (implemented by Shashi).

## [1.101.53] - 2026-09-03

### Lead routing popup polish

- Collapsed the primary routing grid to one column at the compact breakpoint and normalized the manual fallback action label.
- Escape now dismisses the inline fallback confirmation and returns focus to its trigger without closing the routing dialog (implemented by Shashi).

## [1.101.52] - 2026-09-03

### Distilled lead routing popup

- Replaced numbered routing scaffolding and competing status cards with named sections, concise strategy choices, one live routing outcome, and a compact current-setup summary.
- Moved team availability and operational safeguards into a native Advanced safeguards disclosure. Backlog and qualified-handoff details now appear only when enabled and retain their existing saved values while inactive (implemented by Shashi).

## [1.101.51] - 2026-09-03

### Tokenized accessible outbound-call popup

- Applied the saved white-label tokens to admin CRM roots and body-level dialogs, so the outbound-call popup's primary action and focus states follow configured branding.
- Made Call outcome conditionally required, linked its inline error accessibly, and returned focus to the invalid control (implemented by Shashi).

## [1.101.50] - 2026-09-03

### Accessible lead routing popup

- Moved the routing popup onto the shared focus-managed dialog contract, added labelled controls and live feedback, protected unsaved edits, and made source-map controls semantically hidden without clearing saved mappings.
- Added an in-context fallback-run confirmation/progress state and a denser two-column desktop routing layout while retaining the 760px and 480px responsive rules (implemented by Shashi).

## [1.101.49] - 2026-09-03

### Lead routing popup audit documentation

- Added the routing-popup audit, CRM design-system reference map, and prioritized implementation brief without changing runtime behavior (implemented by Shashi).

## [1.101.48] - 2026-09-03

### Workspace account navigation alignment

- Removed the My Account navigation padding offset and inset active navigation markers so labels remain centered and indicators are not clipped (implemented by Shashi).

## [1.101.47] - 2026-09-03

### Accessible tokenized controls

- Derived the primary-button glow and nested active-tab border from white-label tokens and converted the Active Tasks close control into a labelled, keyboard-accessible button (implemented by Shashi).

## [1.101.46] - 2026-09-03

### Complete white-label colour propagation

- Derived soft surfaces, interaction glows, dynamic links, revenue charts, quotation defaults, Dashboard accents, Kanban accents, and password-recovery hover states from the configured white-label and semantic tokens (implemented by Shashi).

## [1.101.45] - 2026-09-03

### Focus highlight consistency

- Limited persistent-looking input and button border highlights to keyboard-visible focus and tied the focus colour to the white-label action token (implemented by Shashi).

## [1.101.44] - 2026-09-03

### Stable early-close validation

- Moved early-close validation feedback into a stable full-width grid slot so missing-reason and missing-evidence errors preserve the same control alignment (implemented by Shashi).

## [1.101.43] - 2026-09-03

### Unified decision copy

- Consolidated decision guidance into one compact copy block and kept the two decision actions aligned on the right side (implemented by Shashi).

## [1.101.42] - 2026-09-03

### Adjacent decision guidance

- Kept the desktop decision messages in content-sized adjacent columns so the guidance no longer floats far from the explanatory copy (implemented by Shashi).

## [1.101.41] - 2026-09-03

### Desktop decision guidance alignment

- Positioned the qualification guidance beside the decision explanation on desktop and preserved a stacked responsive layout below 900px (implemented by Shashi).

## [1.101.40] - 2026-09-03

### Compact lead decision box

- Reduced the pre-sales decision panel height with tighter spacing, smaller guidance text, and compact action controls inspired by the CRM reference (implemented by Shashi).

## [1.101.39] - 2026-09-03

### Early-close validation layout

- Fixed early-close validation feedback so the error message occupies a full grid row without breaking the reason, notes, and action controls (implemented by Shashi).

## [1.101.38] - 2026-09-03

### Compact pre-sales cycle cards

- Reduced pre-sales cycle card padding, typography, gaps, and action spacing to reclaim drawer space while retaining status readability (implemented by Shashi).

## [1.101.37] - 2026-09-03

### Lead details drawer refinement

- Refined the lead drawer hierarchy with a tighter header, smaller identity title, consistent panel radius, and more compact status rhythm inspired by the CRM reference (implemented by Shashi).

## [1.101.36] - 2026-09-03

### Consistent Settings shell

- Aligned the Settings page canvas and shell spacing with the other CRM views and removed the redundant WordPress divider line (implemented by Shashi).

## [1.101.35] - 2026-09-03

### Responsive state polish

- Reduced empty dashboard chart footprint and tightened responsive state presentation without changing chart data or disclosure behavior (implemented by Shashi).

## [1.101.34] - 2026-09-03

### SaaS CRM layout polish

- Tightened dashboard cards, metric grids, charts, table density, filters, navigation, quotation panels, and Kanban spacing for a more efficient CRM workspace (implemented by Shashi).

## [1.101.33] - 2026-09-03

### Compact chart data link

- Replaced the oversized chart data button with a small underlined disclosure link while preserving keyboard access, focus visibility, and the accessible data-table toggle behavior (implemented by Shashi).

## [1.101.32] - 2026-09-03

### Consistent CRM buttons

- Standardized standard button radius at 8px across shared CRM, workspace, Dashboard, filters, pagination, quotation, and task-overlay controls (implemented by Shashi).
- Added explicit semantic foreground/background pairs so primary buttons retain white text on the dark action colour and neutral buttons remain dark text on white (implemented by Shashi).

## [1.101.31] - 2026-09-03

### Tighter secondary CRM typography

- Reduced labels to 10px, metadata to 9px, and summary metrics to 18px, with Dashboard stat labels and trends aligned to the tighter scale (implemented by Shashi).

## [1.101.30] - 2026-09-03

### Compact CRM typography

- Added a consistent, rem-based operational type scale and reduced oversized page, section, card, metric, table, board, dialog, and account typography to improve information density (implemented by Shashi).
- Preserved 14px mobile body text, 16px mobile text-entry controls, 200% browser zoom compatibility, and reflow-friendly unitless line heights (implemented by Shashi).

## [1.101.29] - 2026-09-03

### White-label branding

- Added an accessible White label Settings tab with grouped identity, media, colour, workspace, attribution, live preview, contrast validation, reset, save, and error states (implemented by Shashi).
- Applied saved branding to plugin-owned sign-in, workspace, account, and frontend browser icon surfaces through scoped semantic tokens and explicit theme/page-builder isolation (implemented by Shashi).

## [1.101.28] - 2026-09-03

### White-label theme isolation

- Required white-label and CRM presentation to remain independent from active WordPress theme and page-builder styles through plugin-owned tokens, scoped normalization, selector isolation, and cross-theme regression coverage (planned by Shashi).

## [1.101.27] - 2026-09-03

### White-label settings plan

- Added an implementation-ready White label Settings plan covering information architecture, layout, responsive behavior, preview and upload states, accessibility, storage, security, rollout surfaces, tests, and phased delivery (planned by Shashi).

## [1.101.26] - 2026-09-03

### My Account password alignment

- Aligned the New password and Confirm new password controls by preventing empty validation space from stretching the confirmation field (implemented by Shashi).

## [1.101.25] - 2026-09-03

### Compact password visibility controls

- Replaced oversized Show/Hide text controls with compact accessible eye icons across sign-in, password reset, My Account, and Settings credential fields (implemented by Shashi).

## [1.101.24] - 2026-09-02

### Frontend pipeline option controls

- Restored Pipeline and lead option add/remove controls in the frontend CRM workspace by enqueueing the existing Settings client there (implemented by Shashi).

## [1.101.23] - 2026-09-02

### Final UI acceptance cleanup

- Replaced remaining native browser alerts, confirms, and prompts with shared accessible dialogs and non-blocking feedback across CRM, quotation, and Settings workflows (implemented by Shashi).
- Removed the remaining inline `transition: all` control styling and added contracts that prevent native browser feedback from returning (implemented by Shashi).

## [1.101.22] - 2026-09-02

### Nested outreach dialog stacking

- Raised shared dialogs above the lead-details overlay so Log Call, Log WhatsApp, and Log Closure remain visible and interactive when launched from a lead drawer (implemented by Shashi).

## [1.101.21] - 2026-09-02

### Pre-sales activity logging recovery

- Kept call and WhatsApp activity dialogs open while requests are pending, added bounded request timeouts and retryable inline failures, and closed the dialog only after a confirmed save so representatives are not left in a blocked interface (implemented by Shashi).

## [1.101.20] - 2026-09-02

### Phase 7 users and operational logs

- Added labelled responsive Users & Access filters, complete account input metadata, focused result feedback, pending form states, specific errors, and visible administrator safeguards (implemented by Shashi).
- Added filtered semantic CAPI/audience log views, mobile record cards, accessible selection and inline retry feedback, plus lazy copyable redacted payload inspection through the shared focus-managed dialog (implemented by Shashi).

## [1.101.19] - 2026-09-02

### Lead drawer header hierarchy

- Replaced equal bordered status cards with a four-field operational summary and one quieter contextual line for conditional reasons and created date, removing the status scrollbar while preserving every value and the stage quick edit (implemented by Shashi).

## [1.101.18] - 2026-09-02

### Lead drawer status summary

- Kept status metadata in one scan-friendly row and added contained, labelled, keyboard-focusable horizontal overflow for conditional fields so the drawer header no longer grows beyond five items (implemented by Shashi).

## [1.101.17] - 2026-09-02

### Frontend style isolation

- Scoped metric and lead-row button component rules beneath the CRM workspace so Elementor Kit global button styles cannot override their intended presentation, without adding broad `!important` declarations or changing wp-admin rendering (implemented by Shashi).

## [1.101.16] - 2026-09-02

### Phase 6 settings usability

- Replaced comma-delimited pipeline, call-status, Lost-reason, studio, and room configuration with labelled add/remove option lists that preserve the established saved-option format (implemented by Shashi).
- Added goal-oriented Settings tabs, a sticky saved/unsaved/submitting status area, consistent credential reveal/copy/configuration controls, nearby timestamped connection-test results, and isolated credential-rotation forms that preserve the complete Settings save boundary (implemented by Shashi).

## [1.101.15] - 2026-09-02

### Phase 5 access and account experience

- Added labelled password reveal controls, visible eight-character guidance, nearby password-match validation, and clear pending states across sign-in, password reset, and My Account (implemented by Shashi).
- Added focused post-redirect success/error feedback, complete profile autocomplete/input metadata, and a focusable CRM skip-link target while preserving WordPress authentication, nonce, safe-redirect, and account-enumeration protections (implemented by Shashi).

## [1.101.14] - 2026-09-01

### Phase 4 design-debt reduction

- Migrated Settings and shared UI presentation from inline values and legacy aliases to canonical tokens/classes, added keyboard-operable Settings/MCP tabs, and moved health/reset dialogs to the shared accessible controller (implemented by Shashi).
- Reduced runtime inline styles, hard-coded CSS colours, and `!important` overrides; added decreasing automated budgets plus responsive, role, motion, and empty/loading/error/success visual-state contracts (implemented by Shashi).

## [1.101.13] - 2026-09-01

### Phase 3 responsive workflows

- Added priority-based Leads columns, accessible expandable mobile details, and a narrow-screen sorting control while preserving selection, filtering, pagination, drawer navigation, and role-aware actions (implemented by Shashi).
- Added phone Kanban stage tabs with a single vertical card list while retaining desktop columns, pointer drag-and-drop, keyboard movement, lifecycle dialogs, focus restoration, and announcements (implemented by Shashi).
- Added structured mobile quotation item cards and normalized affected responsive dimensions, motion, and layers without changing calculations, catalogue, autosave, revision, ordering, print, or stored-data contracts (implemented by Shashi).

## [1.101.12] - 2026-09-01

### Phase 2 core frontend workflows

- Added semantic Kanban card opening, explicit drag handles, keyboard “Move to stage” actions, shared lifecycle validation, live movement announcements, and focus restoration (implemented by Shashi).
- Standardized Leads selection/bulk state, accessible destructive confirmation, labelled CSV import progress with downloadable row failures, and export scope count previews (implemented by Shashi).
- Added persistent keyboard-operable Dashboard tabs, labelled/custom reporting ranges, update announcements, accessible canvas names, and equivalent chart data tables (implemented by Shashi).

## [1.101.11] - 2026-09-01

### Phase 1 accessibility foundation

- Replaced inaccessible white-on-gold primary actions with the approved action and hover tokens, and consolidated shared colour, spacing, control, focus, motion, breakpoint, layer, and semantic-status tokens (implemented by Shashi).
- Added shared focus-visible and reduced-motion behavior plus one accessible dialog controller with initial focus, trapping, Escape close, trigger restoration, inert background, and scroll locking (implemented by Shashi).
- Migrated callback, Won, Quote Shared, Lost, and unsaved-change decisions across Leads, Kanban, and quotations; associated affected Leads/Settings labels, added inline validation, and replaced migrated emoji controls with Dashicons (implemented by Shashi).

## [1.101.10] - 2026-09-01

### UI/UX design system and audit

- Audited every user-facing CRM module across workspace, authentication, account, Leads, drawers/modals/tasks, import/export/routing, Kanban, Dashboard, Quotations, Settings, Users, and logs (implemented by Shashi).
- Added a normative design system with accessible colour, typography, spacing, motion, layer, responsive, component, chart, and interaction contracts plus page-specific overrides (implemented by Shashi).
- Added a prioritized improvement roadmap covering primary-action contrast, dialog behavior, keyboard Kanban operation, chart accessibility, form semantics, mobile workflows, and design-token debt (implemented by Shashi).

## [1.101.9] - 2026-08-31

### Won close dates

- Backfilled missing Won timestamps from the latest recorded transition to Won, with last-update fallback for legacy records without activity history (implemented by Shashi).
- Made Won-stage dashboard periods, MCP deal summaries, drawer/export values, offline conversion timing, and Won-date filters use the canonical close timestamp instead of lead creation (implemented by Shashi).
- Added a conversion-time index, guaranteed timestamps for newly inserted Won leads, and prevented generic import `time` columns from auto-mapping as Won dates (implemented by Shashi).

## [1.101.8] - 2026-08-31

### Lead drawer navigation

- Added previous/next controls, visible-position feedback, and left/right keyboard navigation for leads in the current filtered list or Kanban view, with form-focus and unsaved-change safeguards (implemented by Shashi).

## [1.101.7] - 2026-08-31

### Kanban filters

- Replaced the inline Kanban filter bar with the Leads-list floating filter button, active-filter badge, backdrop, accessible side drawer, and clear action while preserving the existing live filters (implemented by Shashi).

## [1.101.6] - 2026-08-31

### Performance

- Limited lead drawer and activity refresh reads to the newest 100 indexed events (implemented by Shashi).
- Removed large audience request/response bodies from the initial log-list query and added an administrator-only, nonce-protected lazy payload read (implemented by Shashi).

## [1.101.5] - 2026-08-31

### Settings rendering

- Split displayed shortcode delimiters across markup boundaries so nested frontend content filters cannot reconstruct and execute the examples (implemented by Shashi).

## [1.101.4] - 2026-08-31

### Settings rendering

- Encoded the General Settings shortcode examples so WordPress displays them literally instead of recursively rendering complete CRM views (implemented by Shashi).

## [1.101.3] - 2026-08-31

### Kanban board

- Removed the New and Contacted columns from the sales Kanban without changing the underlying lifecycle stages (implemented by Shashi).
- Added server-side card sorting by newest, oldest, and lead name (implemented by Shashi).

## [1.101.2] - 2026-08-31

### Lead drawer access

- Restricted the header Stage editor to sales representatives and managers, leaving the Stage card read-only for pre-sales users (implemented by Shashi).
- Added server-side enforcement for drawer-header stage requests so the UI restriction cannot be bypassed (implemented by Shashi).

## [1.101.1] - 2026-08-31

### Lead drawer

- Made the header Stage card editable and routed transitions through the existing nonce, access-policy, lifecycle-validation, and activity-log boundary (implemented by Shashi).
- Preserved the required Won, Quote Shared, and Lost transition dialogs and refreshes the drawer after a successful change (implemented by Shashi).

## [1.101.0] - 2026-08-31

### Floor plans

- Added secure multi-file floor-plan uploads with a 20-file limit and the existing per-file type/size validation (implemented by Shashi).
- Added individual View, Download, and Remove controls while preserving legacy single-file records (implemented by Shashi).

## [1.100.13] - 2026-08-31

### Kanban dashboard

- Replaced separate assigned and qualified header counts with one overall filtered lead count (implemented by Shashi).
- Made current-month Won count and revenue follow each lead's Won conversion timestamp, independent of its created/assigned date and the board date-basis filter (implemented by Shashi).

## [1.100.12] - 2026-08-31

### Item catalogue

- Added automatic unique `ITEM-000001`-style codes for newly created catalogue items and CSV rows with an empty `item_code`, while preserving supplied codes (implemented by Shashi).

## [1.100.11] - 2026-08-31

### Authentication

- Added plugin-scoped email-only CRM sign-in and a complete branded password recovery flow while retaining WordPress core authentication, reset keys, validation hooks, cookies, and session invalidation (implemented by Shashi).
- Added generic reset responses and hashed short-lived request throttling without changing normal WordPress login behavior (implemented by Shashi).

## [1.100.10] - 2026-08-31

### Improved

- Redesigned the CRM login as a responsive split workspace entry using the established tokens, clearer form hierarchy, accessible focus states, and native password recovery (implemented by Shashi).

## [1.100.9] - 2026-08-31

### Authentication

- Added a native, nonce-protected WordPress Sign Out option to My Account with a return to the CRM page after logout (implemented by Shashi).

## [1.100.8] - 2026-08-31

### Authentication

- Added the WordPress core login form to protected frontend CRM views for signed-out visitors and preserved capability-based restricted access for signed-in unauthorized users (implemented by Shashi).

## [1.100.7] - 2026-08-31

### Engineering workflow

- Refined Sol/Terra/Luna delegation guidance, added model-delegation fallback and review verification requirements, and standardized effort selection to Low, Medium, and High (implemented by Shashi).
- Recorded the saved MCP for reviewed LIVE deployments (implemented by Shashi).

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
