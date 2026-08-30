=== Interior Design Lead Manager — Archived Full Readme ===
Contributors: shashi-protecto
Tags: lead-manager, interior-design, crm, lead-tracking, duplicate-detection
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.94.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A premium lead management system to replace Google Sheets, capture webhooks, manage routing, and trigger server-side Conversion API events.

== Description ==

Interior Design Lead Manager is a custom WordPress plugin designed specifically for high-end interior design studios and firms to streamline their lead generation, duplicate matching, assignment routing, and analytics.

Features:
* Webhook integrations for Meta Ads, Google Ads, LinkedIn Ads, custom Website forms, and reception walk-ins.
* Smart duplicate detection with digits-only, suffix-based matching (normalizing local & international formats).
* Drag-and-drop CSV lead importer with fuzzy header auto-mapping.
* Server-side conversion API triggers utilizing Action Scheduler.
* High-precision performance analytics dashboard with local Chart.js enqueuing.
* Sticky-header profile workspace drawer with responsive timeline logging.
* Role-gated frontend quotation workspace with lead-linked proposals, immutable revision previews, catalogue/manual line items, custom HTML cover and closing content, approvals, and branded printable PDFs.
* Unified `[idlm_workspace]` frontend CRM with a collapsible, role-aware sidebar for dashboard, leads, pipeline, quotations, audit logs, and settings.

== Installation ==

1. Upload the `interior-design-lead-manager` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Access the Lead Manager setting panel from your admin dashboard sidebar.

== Frequently Asked Questions ==

= Can this plugin detect duplicate leads with different phone formats? =
Yes, the smart matcher normalizes phone entries to digits only and performs suffix matches on the last 10 digits to match local/international formats seamlessly.

== Changelog ==

= 1.94.1 =
* Date: 2026-08-29
* Author / Builder: Shashi
* Restored the frontend quotations workspace by adding the missing renderer-side date-filter normalization helper and eliminating empty-pagination PHP 8.x warnings.
* Added behavioral and native WordPress coverage for quotation list date handling and the `idlm_view=quotations` route.

= 1.94.0 =
* Date: 2026-08-29
* Author / Builder: Shashi
* Encrypted scoped REST credentials, removed legacy shared/query authentication, and added environment-managed secret overrides.
* Added shared workflow/access policies, atomic routing state, transactional deletion with integrity audit/repair, and scheduled sensitive-data retention.
* Replaced quotation print DOM rewriting with final semantic server-rendered output.
* Added PHP/WordPress matrix CI plus scheduler, integrity, retention, state, and print regression coverage.

= 1.93.0 =
* Date: 2026-08-29
* Author / Builder: Shashi
* Split authenticated admin AJAX handlers from the admin facade while retaining all registered actions and response contracts.
* Split quotation transport/transaction handlers and renderers from the quotation facade.
* Introduced a lead lifecycle service behind the existing database API and routed structured early-close mutations through it.
* Preserved hooks, nonces, capabilities, schema, request fields, and public compatibility delegates.

= 1.92.5 =
* Date: 2026-08-29
* Author / Builder: Shashi
* Added a quotation policy for capability and object-level lead authorization.
* Moved quotation lead search, editor loading, selected-lead lookup, and access existence queries into a scoped read model.
* Added administrator, manager, owner, restricted-representative, and unauthorized-user regression coverage.

= 1.92.4 =
* Date: 2026-08-29
* Author / Builder: Shashi
* Moved catalogue CRUD, bulk/category actions, import helpers, export chunks, search/pagination, and quotation-list read queries into the quotation repository.
* Retained capabilities, nonces, CSV/file validation, import transactions, streaming output, rendering, actions, and response formats in the quotation facade.
* Added catalogue/query/failure regression coverage and native catalogue plus quotation-list verification.

= 1.92.3 =
* Date: 2026-08-29
* Author / Builder: Shashi
* Moved idempotency lookups, quote/revision writes, locked revision sequencing, section/item replacement and copying, and lead quote-value projection into the quotation repository.
* Retained request authorization, validation, redirects, transaction coordination, public actions, stored values, and user-facing save behavior in the quotation facade.
* Added repository aggregate-write, revision-copy, simulated-failure, and native quotation-persistence regression coverage.

= 1.92.2 =
* Date: 2026-08-29
* Author / Builder: Shashi
* Extracted quotation table installation and versioned backfills into a dedicated schema boundary behind `IDLM_Quotations::install()`.
* Added a quotation repository for table mapping, atomic numbering, aggregate reads, history writes, lead cleanup, and reset operations while preserving the existing quotation facade.
* Added repository, migration-delegate, schema-version, and native persistence regression coverage.

= 1.92.1 =
* Date: 2026-08-29
* Author / Builder: Shashi
* Extracted deterministic quotation normalization and totals logic behind the existing `IDLM_Quotations` API.
* Added regression coverage for repeatability and compatibility-delegate parity.

= 1.92.0 =
* Date: 2026-08-28
* Author / Builder: Shashi
* Split webhook, MCP, and Google feed credentials with rotation controls and a 30-day legacy migration window.
* Moved webhook, feed, and MCP authentication into route-specific permission callbacks with constant-time comparisons and per-IP rate limits.
* Restricted custom MCP SQL to bounded SELECT queries over allowlisted CRM tables; capped request bodies, batches, custom-query results, and activity history.
* Added regression coverage for credential migration, authentication, limits, and SQL rejection behavior.

= 1.91.3 =
* Date: 2026-08-28
* Author / Builder: Shashi
* Enforced object-level lead access across all bulk lifecycle actions so restricted representatives cannot mutate leads outside their configured visibility.
* Added a dependency-free production-readiness test baseline for lifecycle, authorization, REST/MCP, quotation, and schema behavior.

= 1.77.2 =
* Date: 2026-08-28
* Author / Builder: Shashi
* Removed Commercial Terms from quotation view pages and retained Revision Previews as a full-width panel.
* Changed areas: quotation detail view and release metadata.

= 1.77.1 =
* Date: 2026-08-28
* Author / Builder: Shashi
* Added an Additional Notes panel below quotation sections with revision, autosave, detail-view, and PDF support.
* Changed areas: quotation editor, revision persistence, autosave, saved quotation/PDF output, CSS, and release metadata.

= 1.77.0 =
* Date: 2026-08-28
* Author / Builder: Shashi
* Replaced quotation totals with wooden discount, design and consultation fees, optional additional discount, fixed 18% GST, and grand-total calculations.
* Changed areas: quotation revision schema, server and live calculations, editor totals UI, read-only/PDF summaries, CSS, and release metadata.

= 1.76.1 =
* Date: 2026-08-28
* Author / Builder: Shashi
* Moved quotation Totals into the summary area and changed the desktop summary layout to three columns.
* Changed areas: quotation editor markup, responsive summary layout, and release metadata.

= 1.76.0 =
* Date: 2026-08-28
* Author / Builder: Shashi
* Removed catalogue and quotation line-item GST/tax fields and calculations; global tax now applies once to the discounted quotation taxable total.
* Changed areas: catalogue schema/forms/CSV, quotation item schema, server totals, editor calculations, tax settings, and release metadata.

= 1.75.0 =
* Date: 2026-08-28
* Author / Builder: Shashi
* Replaced the quotation editor's category-wise summary with Wood and Others totals and preserved catalogue Wood classification in quotation revisions.
* Changed areas: quotation item schema, editor summary, catalogue selection, JavaScript, CSS, and release metadata.

= 1.74.0 =
* Date: 2026-08-28
* Author / Builder: Shashi
* Added a Wood Yes/No option to catalogue items, including catalogue lists, selection results, and CSV import/export.
* Changed areas: catalogue schema, item management, catalogue search, JavaScript, CSV handling, and release metadata.

= 1.73.0 =
* Date: 2026-08-28
* Author / Builder: Shashi
* Removed category-wise discount defaults, per-quotation category discount controls, calculations, stored configuration, and discount output.
* Changed areas: quotation setup, editor, totals, summaries, PDFs, JavaScript, CSS, and schema metadata.

= 1.72.1 =
* Date: 2026-08-27
* Author / Builder: Shashi
* Standardized Area / Qty to two-decimal display in read-only quotation views and PDF output.
* Changed area: `includes/class-quotations.php`.

= 1.72.0 =
* Changed quotation autosave to a temporary working copy with Save as Revision, Discard Changes, and Continue Editing choices on Cancel (implemented by Shashi).

= 1.71.0 =
* Added secure debounced autosaving for existing quotation drafts with visible unsaved, saving, saved, and error states (implemented by Shashi).

= 1.70.8 =
* Preserved the full-width section-subtotal label while retaining the compact S.No grid column (implemented by Shashi).

= 1.70.7 =
* Rebuilt the read-only quotation rows on an explicit nine-column grid so the S.No width cannot be expanded by the subtotal footer (implemented by Shashi).

= 1.70.6 =
* Corrected the read-only table cascade so the S.No column remains compact and currency labels appear only on Rate and Subtotal (implemented by Shashi).

= 1.70.5 =
* Enforced a compact 44px S.No column on the read-only quotation view (implemented by Shashi).

= 1.70.4 =
* Reduced the read-only quotation serial-number column to a compact fixed width (implemented by Shashi).

= 1.70.3 =
* Added a total percentage and calculated amount row to the `[idlm_payment_structure]` output (implemented by Shashi).

= 1.70.2 =
* Improved the read-only quotation table with balanced columns, correct currency headings, numeric alignment, readable item details, and a visible section subtotal (implemented by Shashi).

= 1.70.1 =
* Matched read-only quotation sections to the draft builder's section-card layout and kept the document summary limited to PDF output (implemented by Shashi).

= 1.70.0 =
* Added the cover, quotation summary, section/item breakdown, and after-quote PDF sequence, plus a standard payment-structure shortcode (implemented by Shashi).

= 1.69.6 =
* Restored the white modal surface and soft secondary pane backgrounds in the Item Categories and Units popups (implemented by Shashi).

= 1.69.5 =
* Prevented the collapsed workspace sidebar from flashing open during page refreshes by applying its saved state before first paint (implemented by Shashi).

= 1.69.4 =
* Fixed Item Categories and Units popup stacking by moving open dialogs outside clipped workspace containers and hardening their modal layer (implemented by Shashi).

= 1.69.3 =
* Standardized Item Categories and Units popup inputs, labels, buttons, spacing, and action alignment against the shared design system (implemented by Shashi).

= 1.69.2 =
* Kept catalogue search and bulk-action controls on one compact toolbar row, with stacking limited to narrow screens (implemented by Shashi).

= 1.69.1 =
* Moved catalogue bulk actions into the search filter toolbar and refined Item Categories and Units popup styling (implemented by Shashi).

= 1.69.0 =
* Added reusable catalogue units with automatic migration, unit creation, CSV registration, and a validated unit dropdown (implemented by Shashi).

= 1.68.0 =
* Added select-all and bulk archive/delete actions for Item Catalogue records, plus individual and bulk archive/delete controls for item categories (implemented by Shashi).

= 1.67.0 =
* Added reusable item-category creation and replaced free-text catalogue categories with a validated category dropdown (implemented by Shashi).

= 1.66.4 =
* Added a Quotation Setup switch to enable or disable catalogue-level per-item taxes with clear global-tax precedence (implemented by Shashi).

= 1.66.3 =
* Improved the catalogue editor with the shared wide-popup dimensions, a structured two-column form, and responsive mobile layout (implemented by Shashi).

= 1.66.2 =
* Fixed full quote number displays so the quote number appears once followed by the short revision number (implemented by Shashi).

= 1.66.1 =
* Removed Additional quote settings from the builder and suppressed its title, validity, and introduction content in PDF preview/print (implemented by Shashi).

= 1.66.0 =
* Added catalogue-derived category discount defaults and optional global tax configuration to Quotation Setup (implemented by Shashi).

= 1.65.1 =
* Fixed category-discount dropdowns closing immediately when clicked (implemented by Shashi).

= 1.65.0 =
* Added fixed-value and percentage discounts for each quotation category with server-validated tax and total calculations (implemented by Shashi).

= 1.64.1 =
* Placed Section-wise summary and Category subtotals inline in a responsive two-column layout (implemented by Shashi).

= 1.64.0 =
* Added a live section-wise quotation summary, removed section descriptions, and improved named section subtotal labels and typography (implemented by Shashi).

= 1.63.1 =
* Removed payment milestones from quotation editing, revisions, detail views, and PDF output (implemented by Shashi).

= 1.63.0 =
* Removed commercial terms from the quotation builder and setup, and added optional HTML builders for PDF cover pages and after-quote content (implemented by Shashi).

= 1.62.1 =
* Enabled quotation PDF preview and printing while workflow approval is disabled, with approval enforcement retained for future workflow reactivation (implemented by Shashi).

= 1.62.0 =
* Disabled quotation workflow actions and changed every quotation edit into a new numbered Draft revision requiring an edit remark (implemented by Shashi).

= 1.61.5 =
* Fixed lead search with a cross-browser searchable results dropdown and reliable lead selection/prefilling (implemented by Shashi).

= 1.61.4 =
* Removed the Customer introduction input from the quotation editor while preserving existing stored introduction content (implemented by Shashi).

= 1.61.3 =
* Added Created by beneath Sales rep name in the quotation metadata column (implemented by Shashi).

= 1.61.2 =
* Restricted quotation PDF preview and download to approved or post-approval revisions, including server-side direct-link enforcement (implemented by Shashi).

= 1.61.1 =
* Expanded the Item Catalogue panel to the full available workspace width after moving its editor and CSV import into modals (implemented by Shashi).

= 1.61.0 =
* Converted catalogue CSV import from the sidebar into an accessible modal opened from a new Import CSV header button (implemented by Shashi).

= 1.60.0 =
* Converted the catalogue item editor from a sidebar into an accessible modal and added an Add New Item button beside Export CSV (implemented by Shashi).

= 1.59.5 =
* Added rupee symbols to quotation rate fields, monetary headings, line totals, section/category subtotals, taxes, discounts, and grand totals (implemented by Shashi).

= 1.59.4 =
* Removed browser increment/decrement spinner controls from quotation Rate inputs (implemented by Shashi).

= 1.59.3 =
* Rebalanced quotation columns by content needs and reduced the line-action toolbar to its compact intrinsic width (implemented by Shashi).

= 1.59.2 =
* Removed number spinners from dimensional inputs and standardized Length, Height, and Area/Qty to two-decimal precision (implemented by Shashi).

= 1.59.1 =
* Converted Duplicate and Remove line-item actions to accessible inline icons alongside the movement arrows (implemented by Shashi).

= 1.59.0 =
* Split Length, Height, Area/Qty, and Unit into dedicated quotation table columns in the editor, detail view, and print output (implemented by Shashi).

= 1.58.0 =
* Replaced the oversized quotation-details form with a compact two-column customer and quotation identity panel, including lead search and prefilled studio and sales-representative metadata (implemented by Shashi).

= 1.57.0 =
* Added competitor-inspired room schedules with Length, Height, calculated Area/Qty, rate, and room-total presentation while retaining original branding and terms (implemented by Shashi).

= 1.56.1 =
* Fixed the quotation edit screen fatal error on WordPress installations where the readonly attribute helper is unavailable (implemented by Shashi).

= 1.56.0 =
* Placed quantity and fixed unit inline, locked catalogue-derived description, code, and category fields, and removed line-item discounts from quotation calculations and presentation (implemented by Shashi).

= 1.55.1 =
* Reduced the size and weight of the Selected state label in the bulk catalogue picker (implemented by Shashi).

= 1.55.0 =
* Added a Bulk Items catalogue picker with search, selected-item review, and multi-item insertion into the final quotation section (implemented by Shashi).

= 1.54.1 =
* Moved Add Line from each quotation section header into the shared action row beside Add Section (implemented by Shashi).

= 1.54.0 =
* Made catalogue items editable through the quotation workspace while preserving archived status and immutable values in existing quotation revisions (implemented by Shashi).

= 1.53.1 =
* Reduced the unified workspace sidebar width and typography, pinned My Account to its bottom edge, removed workspace overscroll bounce, and stacked the icon-only filter trigger above Active Tasks (implemented by Shashi).

= 1.53.0 =
* Collapsed the lead database filters into an accessible button-triggered floating side panel with responsive mobile presentation and an active-filter count (implemented by Shashi).

= 1.52.1 =
* Extended the desktop unified-workspace sidebar to the full viewport height while retaining automatic mobile navigation height (implemented by Shashi).

= 1.52.0 =
* Added a secure My Account frontend workspace for profile, email, phone, direct password changes, and password-reset emails (implemented by Shashi).
* Expanded quotation line items to full width and moved payment milestones and revision previews below them (implemented by Shashi).

= 1.51.0 =
* Redesigned the unified frontend workspace navigation and quotation experience to follow the project design system with consistent gold/blue tokens, hierarchy, focus states, cards, tables, forms, totals, workflow actions, and responsive behavior (implemented by Shashi).

= 1.50.1 =
* Added a comprehensive frontend design system reference covering the shared workspace, Leads List, Kanban, Dashboard, Settings, Quotations, components, responsive rules, accessibility, and implementation standards (implemented by Shashi).

= 1.50.0 =
* Simplified quotation line items into Items, Qty/Unit, Rate, Total, and Buttons columns with inline catalogue search and grouped item metadata (implemented by Shashi).
* Removed quote CSV import and moved Add Section below the line-item sections for easier access (implemented by Shashi).

= 1.49.1 =
* Kept lead-drawer Open, Create, and View Quote actions inside the unified frontend workspace while retaining their WordPress Admin destination in backend views (implemented by Shashi).

= 1.49.0 =
* Added the `[idlm_workspace]` single-page frontend CRM shell with a responsive collapsible sidebar (implemented by Shashi).
* Consolidated dashboard, lead list, Kanban pipeline, quotations, and administrator-only logs and settings behind capability-aware navigation while retaining existing shortcodes (implemented by Shashi).

= 1.48.0 =
* Added sectioned quotation line items with section subtotals, category subtotals, duplication, and ordering controls (implemented by Shashi).
* Added a searchable item catalogue with CSV import/export and immutable catalogue-value snapshots in revisions (implemented by Shashi).
* Consolidated quotations, catalogue management, commercial defaults, numbering, and PDF settings into the shared quotation workspace used by the admin and `[idlm_quotations]` shortcode (implemented by Shashi).
* Added configurable quotation numbering and immutable customer/project snapshots for historical revision previews and PDFs (implemented by Shashi).
* Expanded quotation search by number, customer, phone, project, creator, status, and date (implemented by Shashi).

= 1.47.0 =
* Added the `[idlm_quotations]` frontend quotation workspace with existing role and lead-ownership controls (implemented by Shashi).
* Added Create/View Quote actions to lead drawer footers and CSV bulk line-item import alongside manual entry (implemented by Shashi).
* Added quotation setup for commercial-term defaults and PDF company name, logo, accent colour, paper size, and footer (implemented by Shashi).
* Changed revision history into selectable full quotation previews with revision-specific PDF output (implemented by Shashi).

= 1.46.0 =
* Added a separate lead-linked quotation and commercial workflow module with multiple quotations per lead (implemented by Shashi).
* Added atomic quotation numbering, named immutable revisions, revision authors and reasons, line items, optional items, discounts, GST/IGST, payment milestones, commercial terms, and server-validated totals (implemented by Shashi).
* Added Draft, Pending Approval, Approved, Shared, Under Negotiation, Accepted, Rejected, Expired, Cancelled, and Superseded workflow states with role-based permissions (implemented by Shashi).
* Added revision history, internal print/PDF views, lead activity entries, legacy quote migration, and lead stage/value synchronization (implemented by Shashi).

= 1.45.0 =
* Matched inbound Meta, website, Google, LinkedIn, and walk-in submissions to one canonical lead using normalized phone/email identity and Meta lead IDs (implemented by Shashi).
* Added append-only attribution touchpoints so every submission, click ID, UTM, landing page, and original payload remains auditable without duplicate rep records (implemented by Shashi).
* Routed only newly created canonical leads, promoted delayed Meta acquisition onto earlier website records, and hid legacy duplicate rows from representative lead lists (implemented by Shashi).
* Backfilled existing linked duplicate records into canonical attribution history (implemented by Shashi).
* Included attribution history in lead deletion/reset cleanup and fixed the full CRM reset's invalid undefined-lead check (implemented by Shashi).

= 1.44.0 =
* Added durable current-assignment timestamps with historical recovery from assignment activity, sales handoff, and conservative legacy fallbacks (implemented by Shashi).
* Added Assigned, Created, Sales Handoff, Last Updated, Qualified, and Won date-basis filters across lead lists, Kanban, and filtered exports (implemented by Shashi).
* Ordered representative queues by current assignment timing, corrected assignment-period reporting, and fixed yesterday delivery to count handoff events (implemented by Shashi).

= 1.43.7 =
* Made the lead-list metric grid recalculate through AJAX from the same permissions, filters, search, and active-only scope as the lead table (implemented by Shashi).

= 1.43.6 =
* Made Kanban Assigned and Qualified stat cards count the complete filtered board population, including visible Won and Lost records, so representative totals reconcile exactly (implemented by Shashi).

= 1.43.5 =
* Aligned lead-list action cards with active non-terminal records and ensured card clicks return the same scoped population (implemented by Shashi).
* Made Kanban Won and revenue cards summarize the currently filtered board population and corrected qualified-handoff overdue counts to use configured working hours (implemented by Shashi).

= 1.43.4 =
* Audited dashboard metric scopes, aligned qualified sales ownership with Kanban, corrected cohort conversion and year-over-year comparison calculations, and clarified acquisition-cohort versus Won-event labels (implemented by Shashi).
* Normalized newly qualified sales-owned and unassigned leads into completed or awaiting-manager handoff states at creation and qualification time (implemented by Shashi).

= 1.43.3 =
* Aligned Qualified Sales Assignments by Representative with the Kanban Qualified population by using lead creation date, current sales ownership, and the selected reporting period (implemented by Shashi).

= 1.43.2 =
* Reconciled historical qualified leads with existing sales owners as completed handoffs while retaining genuinely unresolved leads for manager action (implemented by Shashi).
* Removed the uninitialized `not_ready` schema default from pending-handoff reporting and fixed reconciliation result tracking (implemented by Shashi).

= 1.43.1 =
* Added a manager-only Sales Assignments by Representative stacked bar chart using the actual handoff date, split between manager assignments and OOO fallback assignments (implemented by Shashi).

= 1.43.0 =
* Preserved manager-controlled qualified handoffs while reconciling every unresolved historical, unassigned, pre-sales-owned, and exception backlog record during OOO fallback (implemented by Shashi).
* Recalculated representative capacity before each fallback assignment and retained clear exceptions when no sales capacity is available (implemented by Shashi).
* Added yesterday qualification-to-delivery reconciliation, per-rep received-yesterday metrics, pre-sales qualified history, and durable sales follow-up tasks after handoff (implemented by Shashi).
* Restricted task reads and mutations to visible/currently owned leads and hardened MCP custom-query table enforcement (implemented by Shashi).

= 1.42.1 =
* Unified the pre-sales list, lead drawer, task summary, and backend compliance gate on one carried-progress calculator (implemented by Shashi).
* Enforced a maximum of one completed outreach cycle per activity date while retaining partial progress across dates (implemented by Shashi).
* Fixed zero-WhatsApp targets, negative-value clamping, deterministic date ordering, and unnecessary task reconciliation on unrelated settings saves (implemented by Shashi).

= 1.42.0 =
* Changed pre-sales follow-up tracking to carry partial call and WhatsApp progress across working days until the active cycle is complete (implemented by Shashi).
* Added administrator settings for configurable calls and WhatsApp messages required per follow-up cycle (implemented by Shashi).
* Updated the pre-sales queue, drawer, qualification tasks, and task synchronization to use the configured outreach targets (implemented by Shashi).

= 1.41.0 =
* Centralized lead quality, pipeline stage, Won, Quote Shared, Lost, callback, handoff, and reverse-transition invariants so every edit path follows the same rules (implemented by Shashi).
* Closed Unqualified and duplicate records into the Lost pipeline state, required canonical links for manual duplicates, and normalized automatically detected duplicates (implemented by Shashi).
* Stopped assigning generic conversion timestamps at lead creation or unrelated stage changes; Won now records the conversion timestamp (implemented by Shashi).
* Added accurate bulk-action failure reporting, skipped routing and CAPI queueing for imported duplicates, and cleaned tasks during lead deletion (implemented by Shashi).
* Added a one-time workflow integrity repair for contradictory legacy states, invalid Won/Quote records, and orphaned tasks without fabricating commercial values (implemented by Shashi).

= 1.40.11 =
* Changed the structured Duplicate early-close outcome to set Lead Quality to Unqualified while retaining duplicate exclusion from reporting and conversion events (implemented by Shashi).
* Added an explicit lead-quality activity entry for duplicate early closures (implemented by Shashi).
* Added a one-time repair for previously closed duplicate leads whose Lead Quality was left blank (implemented by Shashi).

= 1.40.10 =
* Removed buffered whitespace before Google Ads Data Manager HTTPS CSV responses so the schema header is always the first row (implemented by Shashi).
* Changed offline-conversion timestamps from named timezones to Google-compatible numeric UTC offsets and skipped rows without a valid timestamp (implemented by Shashi).
* Applied the clean-response handling to both offline conversion and Customer Match audience pull feeds (implemented by Shashi).

= 1.40.9 =
* Reworked unread WhatsApp discovery into a fully placeholder-prepared query, resolving Plugin Check unescaped-parameter and unprepared-SQL errors (implemented by Shashi).

= 1.40.8 =
* Removed nested sort buttons from lead-table headers to prevent site-theme button styles from changing the table header design while retaining mouse and keyboard sorting (implemented by Shashi).

= 1.40.7 =
* Added accessible ascending and descending sorting controls to the lead-table columns, with sorting applied across the full filtered, paginated result set (implemented by Shashi).

= 1.40.6 =
* Added the lead creation date as a dedicated column in the pre-sales lead-list table (implemented by Shashi).

= 1.40.5 =
* Consolidated unread WhatsApp status into one indexed, request-shared query with short object caching (implemented by Shashi).
* Added composite activity and task indexes for the lead-list hot paths (implemented by Shashi).
* Cancelled superseded lead-table and Kanban requests and paused automatic refreshes in hidden tabs (implemented by Shashi).

= 1.40.4 =
* Separated active pre-sales availability, unassigned-lead recovery, and qualified-lead handoff into clearly labeled routing controls (implemented by Shashi).
* Added responsive routing status summaries for assignment mode, active pre-sales users, unassigned backlog, and recovery state (implemented by Shashi).

= 1.40.1 =
* Corrected Sales Representative performance visibility and changed successful-connection counts to require a real connected timestamp (implemented by Shashi).
* Based pre-sales outreach on the intake owner's own logged activity, aligned ever-qualified rates and averages, and tightened completed-handoff counting (implemented by Shashi).
* Clarified current-owner, intake, and studio cohort semantics across every performance-table column and safely rendered representative and studio labels (implemented by Shashi).

= 1.40.0 =
* Corrected rolling date windows, comparable month-to-date growth, monthly target scope, custom-range validation, and zero-baseline growth presentation (implemented by Shashi).
* Changed Won cards and revenue to use the actual conversion timestamp, aligned Kanban summaries with board filters, and replaced the inferred funnel with a current-stage distribution (implemented by Shashi).
* Removed the unused cost-per-lead calculation, spend endpoint, client rendering, styles, schema creation, diagnostics, reset handling, and MCP exposure (implemented by Shashi).
* Scoped unread WhatsApp discovery to visible, non-duplicate leads (implemented by Shashi).

= 1.39.1 =
* Changed the Duplicate early-close outcome into an exclusion: it marks the record as duplicate, keeps it out of Unqualified reporting, completes qualification tasks, and blocks queued or future CAPI events (implemented by Shashi).

= 1.39.0 =
* Enhanced the existing lead-quality doughnut into a Qualification Readiness chart that separates qualified leads by floor-plan availability and added a Plan Collection Rate KPI (implemented by Shashi).

= 1.38.0 =
* Added Qualified · Plan Received, Qualified · Plan Pending, Unqualified, and Pending Qualification status labels while preserving the underlying qualification values and reporting (implemented by Shashi).
* Added plan-aware lead filters and separated optional floor-plan availability from the six required qualification details (implemented by Shashi).

= 1.37.1 =
* Restricted the Pending Calls header pill to the pre-sales team and skipped its count query for all other roles (implemented by Shashi).

= 1.37.0 =
* Made floor-plan collection optional for qualification and required an auditable handoff remark when pre-sales qualifies a lead without one (implemented by Shashi).

= 1.36.2 =
* Fixed pre-sales activity logged on non-working days appearing in the queue but showing zero in the lead drawer compliance panel (implemented by Shashi).

= 1.36.1 =
* Corrected Plugin Check database-preparation findings in lead filtering, pre-sales task aggregation, and representative capacity queries (implemented by Shashi).

= 1.36.0 =
* Added visible manager handoff alerts, an awaiting-assignment metric, urgency indicators, and one-click access to qualified handoffs (implemented by Shashi).

= 1.35.0 =
* Consolidated six pending pre-sales qualification tasks into one actionable My Active Tasks row per lead with cycle and daily outreach progress (implemented by Shashi).

= 1.34.0 =
* Automatically moved New leads to Contacted after their first logged pre-sales call or WhatsApp while keeping Lead Quality Pending until an explicit decision (implemented by Shashi).
* Preserved New or Contacted stage for pre-sales disqualification and reserved post-Qualified stages for the sales team (implemented by Shashi).

= 1.33.2 =
* Added a Channel column to the pre-sales lead queue using the existing source badge presentation (implemented by Shashi).

= 1.33.1 =
* Hid the Call Status column from the lead list for managers, administrators, and sales roles while preserving the pre-sales workflow and stored call history (implemented by Shashi).

= 1.33.0 =
* Replaced browser prompts in pre-sales call and WhatsApp logging with an accessible in-app activity modal (implemented by Shashi).

= 1.32.1 =
* Synchronized logged pre-sales call outcomes with Call Status and required callback scheduling where applicable (implemented by Shashi).
* Corrected completed qualification states and blocked further pre-sales outreach after a final decision (implemented by Shashi).

= 1.27.3 =
* Added a Leads per page selector with 25, 50, and 100 options; 25 is now the default (implemented by Shashi).

= 1.27.2 =
* Included duplicate records in unassigned-lead fallback routing so every open lead receives an owner after the configured working-hours threshold (implemented by Shashi).

= 1.27.1 =
* Fixed Sales Manager and Sales Executive role discovery across custom production role slugs (implemented by Shashi).
* Updated unassigned round-robin recovery to process the complete eligible backlog in safe batches (implemented by Shashi).

= 1.27.0 =
* Added durable handoff state, routing exceptions, qualification attribution, and pre-sales performance reporting (implemented by Shashi).
* Added pre-sales availability, OOO expiry, historical-scope, studio-mismatch, task-transfer, notification, and scheduler-health controls (implemented by Shashi).
* Added manager exception filters and a protected Run Fallback Now action (implemented by Shashi).

= 1.26.0 =
* Added Manager OOO automatic handoff for qualified pre-sales leads using studio-matched sales routing and general-pool fallback (implemented by Shashi).
* Added a dedicated qualification timestamp and safe migration for existing qualified leads (implemented by Shashi).
* Consolidated initial and fallback routing with atomic assignment protection and separate pre-sales/sales pools (implemented by Shashi).

= 1.25.0 =
* Added a dedicated Pre-Sales Representative role and role-based round-robin intake routing (implemented by Shashi).
* Preserved creator ownership for manually created leads (implemented by Shashi).
* Added a Qualified – Awaiting Assignment manager queue and manager-only reassignment controls (implemented by Shashi).
* Restricted pre-sales users to their own leads and to pipeline stages through Qualified (implemented by Shashi).

= 1.24.5 =
* Moved Business Working Days to a dedicated full-width row to prevent overlap with Daily Working Hours (implemented by Shashi).

= 1.24.4 =
* Reset the frontend routing modal to the top whenever it opens so Assignment Strategy is always visible first (implemented by Shashi).
* Prevented frontend themes from replacing routing modal typography with inconsistent serif styles (implemented by Shashi).

= 1.24.3 =
* Matched the frontend routing modal width to the 1320px Lead Details workspace (implemented by Shashi).
* Kept the routing action footer permanently accessible with a fixed header, scrollable body, and fixed footer layout (implemented by Shashi).

= 1.24.2 =
* Redesigned the frontend Lead Routing modal into compact, clearly grouped strategy, representative coverage, and fallback sections (implemented by Shashi).
* Improved representative rows, help-text hierarchy, responsive behavior, and modal scrolling (implemented by Shashi).

= 1.24.1 =
* Updated automatic fallback routing to prioritize active representatives assigned to the lead's preferred studio (implemented by Shashi).
* Retained general active-pool round robin when no active representative matches the lead's studio (implemented by Shashi).

= 1.24.0 =
* Added per-representative studio location assignments in both Lead Routing workspaces (implemented by Shashi).
* Added Studio-Based routing with round-robin distribution between active representatives assigned to the lead's preferred studio (implemented by Shashi).

= 1.23.3 =
* Made Email Address mandatory and validated for manual lead creation (implemented by Shashi).

= 1.23.2 =
* Fixed Forminator webhook setup validation by recognizing its `X-Hook-Test` request header (implemented by Shashi).

= 1.23.1 =
* Added Forminator webhook setup-handshake compatibility while retaining required lead contact validation (implemented by Shashi).

= 1.23.0 =
* Added a dedicated reception walk-in webhook with separate payload mappings and forced Offline/Walk-in classification (implemented by Shashi).
* Added Offline/Walk-in to source-based representative routing (implemented by Shashi).

= 1.22.1 =
* Reduced the Kanban stat bar to compact, minimal summary tiles with smaller type, spacing, and responsive dimensions (implemented by Shashi).

= 1.22.0 =
* Added a representative-focused Kanban stat bar for active assignments, follow-ups due, qualified pipeline, open pipeline value, monthly wins, and monthly won revenue (implemented by Shashi).
* Made administrators' Kanban statistics respond to the selected representative while representatives always see their own performance totals (implemented by Shashi).

= 1.21.1 =
* Added a date-filtered Lost Deals by Reason chart to the Sales dashboard, showing lead counts, loss percentages, and associated quote values (implemented by Shashi).
* Included uncategorized legacy Lost leads in the chart so historical totals remain complete (implemented by Shashi).

= 1.21.0 =
* Added administrator-customizable Lost Reason dropdown options under General Settings (implemented by Shashi).
* Required a categorized Lost Reason alongside the existing remark for individual, bulk, and Kanban Lost transitions (implemented by Shashi).
* Stored Lost Reason on each lead and included it in Lead Details, activity history, and CSV exports (implemented by Shashi).

= 1.20.9 =
* Added Offline/Walk-in to Channel Source filters across the lead list, export dialog, and Kanban view (implemented by Shashi).
* Assigned manually created leads directly to the user who creates them (implemented by Shashi).
* Automatically moved leads to the Qualified stage when Lead Quality changes to Qualified (implemented by Shashi).
* Added horizontal edge scrolling while dragging leads across the Kanban board (implemented by Shashi).
* Removed left and right padding from the frontend lead-manager wrapper (implemented by Shashi).

= 1.20.8 =
* Fixed Plugin Check findings for WhatsApp reply output escaping, UTC Interakt timestamps, and prepared lead-table query patterns.

= 1.20.7 =
* Fixed undefined currency variables in the lead-details drawer AJAX response.

= 1.20.6 =
* Fixed recursive telemetry exception handling that could exhaust the PHP call stack and surface as a request timeout.
* Safely delegates uncaught exceptions to WordPress's previously registered handler.

= 1.20.5 =
* Added a hard wp-login.php isolation so the CRM database, telemetry, integrations, scheduler, and administration runtime do not initialize during authentication requests (implemented by Shashi).

= 1.20.4 =
* Prevented production login timeouts caused by full CRM database reconciliation running during wp-login.php after every plugin version change (implemented by Shashi).
* Separated database schema versioning from plugin release versioning and added safe compatibility adoption for installations already on schema-complete version 1.14.0 or newer (implemented by Shashi).

= 1.20.3 =
* Renamed duplicate-excluding marketing dashboard labels from Total Leads to Unique Leads across current-period, previous-period, representative, and doughnut-chart displays (implemented by Shashi).

= 1.20.2 =
* Corrected the WhatsApp unread notification badge to count every unread inbound message activity instead of collapsing multiple unread messages on the same lead into a single count (implemented by Shashi).

= 1.20.1 =
* Increased the default WhatsApp unread notification contrast with a solid green background, white icon and text, stronger border, and accessible focus styling (implemented by Shashi).

= 1.20.0 =
* Added a WhatsApp unread notification pill beside Pending Calls with the current representative's unread count and a direct Unread WhatsApp filter action (implemented by Shashi).
* Kept the notification count synchronized when the lead table refreshes and when replies are marked read in Lead Details (implemented by Shashi).

= 1.19.5 =
* Removed inaccessible Interakt media URL storage and link rendering while retaining media-type labels and any available reply text or caption (implemented by Shashi).
* Suppressed media URLs from previously recorded WhatsApp activity entries (implemented by Shashi).

= 1.19.4 =
* Made WhatsApp media URLs safe, clickable external links in unread alerts, recent comments, and full activity timelines (implemented by Shashi).

= 1.19.3 =
* Added Interakt inbound media reply logging with the message content type, sanitized media URL, and accompanying text or caption when available (implemented by Shashi).
* Ensured media-only WhatsApp replies create CRM activity and unread-reply notifications instead of being silently ignored (implemented by Shashi).

= 1.19.2 =
* Matched the WhatsApp reply preview typography to the Lead Details drawer using the existing 13px title, 12px content, and 11px metadata scale (implemented by Shashi).

= 1.19.1 =
* Fixed WhatsApp engagement filters failing to populate the lead list by qualifying the lead ID in joined AJAX queries (implemented by Shashi).

= 1.19.0 =
* Added WhatsApp Message Received and Unread WhatsApp Messages engagement filters to the Leads Database and filtered CSV export workflow (implemented by Shashi).

= 1.18.1 =
* Replaced the WA text marker in the Lead Details reply alert with the WordPress WhatsApp Dashicon and ensured Dashicons load in shortcode views (implemented by Shashi).

= 1.18.0 =
* Added per-representative New WhatsApp badges to lead rows and a prominent unread reply alert in Lead Details (implemented by Shashi).
* Added dedicated WhatsApp reply activity tracking with compatibility for previously stored reply comments (implemented by Shashi).

= 1.17.1 =
* Standardized the spacing between dashboard chart titles, supporting text, and visualizations.

= 1.17.0 =
* Displayed Revenue Estimate by Channel and Estimated Pipeline vs Won Revenue as side-by-side vertical bar charts in the Sales dashboard (implemented by Shashi).

= 1.16.1 =
* Updated dashboard statistics grids to display up to six equal-width columns, distribute fewer cards evenly, and wrap additional cards onto subsequent rows (implemented by Shashi).

= 1.16.0 =
* Added Revenue Estimate by Channel to the Sales dashboard, comparing configured open-pipeline values with Won deal revenue (implemented by Shashi).
* Added Estimated Open Pipeline and Won Revenue KPI cards with dynamic currency formatting and dashboard period filtering (implemented by Shashi).

= 1.15.0 =
* Added per-stage Kanban value consolidation settings with Hidden, Quote Value, and Deal Value options (implemented by Shashi).
* Replaced the universal Estimated Pipeline label with source-specific Quote Total or Deal Total labels only on configured stages (implemented by Shashi).

= 1.14.0 =
* Added optional quote document upload controls to the Quote Shared stage prompts in both Kanban and Leads Database views, saving the attachment URL with the lead (implemented by Shashi).
* Allowed authorized lead-manager representatives to use the existing nonce-protected lead file uploader (implemented by Shashi).

= 1.13.0 =
* Added mandatory quote value capture when an individual lead is moved to Quote Shared from the Kanban Board or Leads Database quick-edit stage control (implemented by Shashi).
* Added filtered estimated quote-value subtotals to every Kanban column header and exposed quote values in Lead Details and CSV import/export flows (implemented by Shashi).

= 1.12.0 =
* Added a prefilled Property Location / Pincode field to both Won stage pop-ups (implemented by Shashi).

= 1.11.0 =
* Added a mandatory Discount % field to Won stage modals, stored per lead and displayed in Lead Details (implemented by Shashi).

= 1.10.6 =
* Displayed protected Lost Remarks in Lead Details under Recent Comments and the full activity timeline (implemented by Shashi).

= 1.10.5 =
* Fixed textarea and horizontal scrollbar overflow in both Lost Remark modals (implemented by Shashi).

= 1.10.4 =
* Added a mandatory Lost Remark prompt and activity-history entry for Kanban, quick-edit, and bulk moves to the Lost stage (implemented by Shashi).

= 1.10.3 =
* Set Qualified as the default Lead Quality filter on the Kanban Board (implemented by Shashi).

= 1.10.2 =
* Added a Lead Quality filter to the Kanban Board with Qualified, Unqualified, and Pending options (implemented by Shashi).

= 1.4.11 =
* Removed duplicate leads from Leads Database summary card metrics (implemented by Shashi).

= 1.4.10 =
* Added the standard 'Zip' (Postal Code) column with empty placeholder values to the Google Ads Customer Match audience CSV pull feed output (implemented by Shashi).

= 1.4.09 =
* Modified Google Ads Offline Conversions CSV pull feed to populate the conversion currency code unconditionally for all rows (implemented by Shashi).

= 1.4.08 =
* Added dynamic currency configuration setting in the admin settings panel (implemented by Shashi).
* Integrated dynamic currency code into Meta, Google Ads, and LinkedIn Conversion APIs (implemented by Shashi).
* Integrated dynamic currency code into Google Ads Data Manager Conversions CSV pull feed output (implemented by Shashi).
* Made frontend deal value labels and budget placeholders react dynamically to the selected currency code and symbol (implemented by Shashi).

= 1.4.07 =
* Added Conversion currency (INR) column to the Google Ads offline conversions CSV feed output (implemented by Shashi).

= 1.4.06 =
* Refined the Google Ads Data Manager (GADM) pull feed schemas for both customer match segments and offline conversions to exactly align with GADM requirements (implemented by Shashi).
* Updated offline conversions CSV headers to exactly: Conversion action, GCLID, Email Address, Phone number, Conversion date and time, Conversion value, GBRAID, WBRAID (implemented by Shashi).
* Updated Customer Match audience CSV headers to exactly: Email, Phone Number, Country Code, Hashed first name, Hashed last name (implemented by Shashi).
* Implemented E.164 phone normalization in plain and hashed outputs, prepending + and country codes (defaulting to +91 for 10-digit numbers) (implemented by Shashi).
* Implemented prefix stripping, lowercase sanitization, a-z filtering, and SHA256 hashing for name attributes (implemented by Shashi).

= 1.4.05 =
* Removed the `Parameters:TimeZone` metadata row from the offline conversions CSV so the first row is column headers, allowing Google Ads Data Manager to correctly map fields (implemented by Shashi).

= 1.4.04 =
* Added proper `WWW-Authenticate: Basic` challenge header on 401 responses so Google Ads Data Manager recognizes the authentication scheme (implemented by Shashi).
* Buffered CSV output and added `Content-Length` header to make feed responses indistinguishable from static files (implemented by Shashi).
* Stripped WordPress REST API metadata headers (`X-Robots-Tag`, `Link`, `Access-Control-*`) from feed responses to prevent GADM from misidentifying the feed as an API endpoint (implemented by Shashi).

= 1.4.03 =
* Enhanced GADM basic authentication validation to check HTTP_AUTHORIZATION and REDIRECT_HTTP_AUTHORIZATION headers to prevent "Invalid credentials" errors on CGI/FastCGI hosting environments where standard headers are stripped (implemented by Shashi).
* Added regex-based extraction to correctly parse credentials from all basic authentication headers (implemented by Shashi).
* Added non-sensitive secure authentication debug logs when WP_DEBUG is enabled (implemented by Shashi).

= 1.4.02 =
* Fixed copy to clipboard function parameter order bug on GADM conversions feed URL copy button which resulted in copying "[object HTMLButtonElement]" (implemented by Shashi).

= 1.4.01 =
* Fixed GADM file extension check failure by registering and exposing path-based feed URLs ending with a literal `.csv` extension (implemented by Shashi).
* Updated conversions feed and rule-specific audience sync feeds to use `.csv` endpoints in the settings panels (implemented by Shashi).
* Enhanced REST API authentication bypass rules to allow all path-based and query-based Google Ads endpoints under global restrictions (implemented by Shashi).

= 1.4.00 =
* Removed direct Google Ads API push methods and credentials configuration entirely, replacing them with secure pull-based Google Ads Data Manager HTTPS feed integrations (implemented by Shashi).
* Added secure HTTP Basic Authentication support and fallback api_key URL parameter for feed endpoints (implemented by Shashi).
* Exposed conversions and custom audience segment CSV pull feed URLs directly in the settings panels (implemented by Shashi).
* Added a custom Feed URL link helper inside the Custom Audience Sync Rules table for direct downloads (implemented by Shashi).

= 1.3.00 =
* Fixed stale or invalid cached Google Ads access token error during dynamic connection testing by implementing dynamic transient keys keyed to the specific OAuth refresh token (implemented by Shashi).
* Added support to bypass the transient cache lookup entirely during admin settings connection tests to force a fresh access token verification request (implemented by Shashi).
* Applied robust string trimming to client credentials, developer tokens, and refresh tokens before transmission to prevent trailing/leading whitespace from causing auth failures (implemented by Shashi).

= 1.2.99 =
* Upgraded Google Ads API integration endpoints to version v24 (to replace the sunsetted v15 version) (implemented by Shashi).
* Overhauled connection testing to query user lists via REST POST search queries (googleAds:search) rather than direct GET requests, which are not supported by the Google Ads REST API (implemented by Shashi).
* Added Google Ads Manager Customer ID configuration field to properly authorize requests targeting linked client accounts using manager-level developer tokens (implemented by Shashi).
* Configured settings connection tests to fetch credentials dynamically from the input fields in real-time without requiring a prior settings save (implemented by Shashi).

= 1.2.98 =
* Fixed Google Ads connection test GET requests throwing 404 errors by removing the redundant urlencoding on slashes in the resource target ID path (implemented by Shashi).

= 1.2.97 =
* Added Google Ads Customer ID settings field to allow configuring it once and automatically prepending it to numeric User List IDs (implemented by Shashi).

= 1.2.96 =
* Added automatic whitespace trimming, leading slash removal, and case-normalization for User List Resource IDs in target API verification and batch sync handlers to make copy-pasting resilient (implemented by Shashi).

= 1.2.95 =
* Documented setting OAuth consent screen publishing status to "In Production" in the credentials guide to prevent Google from expiring OAuth2 refresh tokens after 7 days (implemented by Shashi).

= 1.2.94 =
* Updated Google Ads credentials help section to focus on Web application credentials, detailing authorized redirect URIs configuration using Google OAuth2 Playground (implemented by Shashi).

= 1.2.93 =
* Added a detailed step-by-step Google Ads API credentials guide to the Help & FAQs tab (implemented by Shashi).
* Linked the Google Ads Credentials card directly to the new guide in the Help tab (implemented by Shashi).

= 1.2.92 =
* Added Google Ads API credentials settings fields (Developer Token, Client ID, Client Secret, Refresh Token) to the Sync & Reminders settings panel (implemented by Shashi).
* Upgraded Google Customer Match sync from Mock/Test Mode to full production API requests, implementing dynamic OAuth2 token updates and mutateMembers API requests (implemented by Shashi).
* Upgraded the Google Connection Test helper to authenticate and retrieve actual user list details from the Google Ads API to verify credentials in real-time (implemented by Shashi).

= 1.2.91 =
* Removed retroactive database migration functions to clean up codebase post-production update (implemented by Shashi).

= 1.2.90 =
* Implemented a one-time database migration triggered on plugin update to retroactively normalize the source classification of existing historical "Website" leads containing Google Ads or Meta Ads click IDs or UTMs (implemented by Shashi).

= 1.2.89 =
* Normalized lead source classification logic: dynamically classifies leads as Google Ads instead of Website when Google click IDs (gclid, gbraid, wbraid) or campaign UTMs are present (implemented by Shashi).
* Implemented dynamic last-touch UTM logic to resolve dual-attribution conflicts (when both Google and Meta Ads attributions coexist in payload/cookies) (implemented by Shashi).

= 1.2.67 =
* Excluded duplicate leads from all standard dashboard widgets, funnel, and performance charts, and added a dedicated "Duplicate Leads (Period)" stat card (implemented by Shashi).

= 1.2.66 =
* Updated the dashboard average time calculations (time_to_connected and time_to_won) to respect the Daily Working Hours Range and Business Working Days settings (implemented by Shashi).

= 1.2.65 =
* Adjusted the Lead Routing Settings Modal width to match the sliding drawer's width (min(1320px, 100%)) as requested, preventing form crowding (implemented by Shashi).

= 1.2.64 =
* Aligned the Lead Routing Settings Modal layout, width, form input fields, and footer button spacing to match the main Lead Creation Modal styles (implemented by Shashi).

= 1.2.63 =
* Added frontend lead routing configuration panel in leads list workspace via modal and AJAX settings saving (implemented by Shashi).

= 1.2.62 =
* Refactored automatic fallback lead routing to calculate elapsed working hours instead of absolute calendar hours (implemented by Shashi).

= 1.2.61 =
* Added automatic fallback lead routing for unassigned leads when admins are on leave or away (implemented by Shashi).

= 1.2.60 =
* Added support for Value-Based Custom Audiences on Meta Ads (Facebook), mapping the LOOKALIKE_VALUE schema parameter using each lead's deal value (implemented by Shashi).

= 1.2.59 =
* Resolved WordPress Plugin Check errors and warnings, including database query sanitization, input validation, and stable tag synchronization (implemented by Shashi).

= 1.2.58 =
* Fixed the Action Scheduler diagnostic version check crash by replacing references to the non-existent class constant ActionScheduler::VERSION with a safe dynamic check using ActionScheduler_Versions::instance()->latest_version() (implemented by Shashi).

= 1.2.57 =
* Fixed the PHP 8.1+ strtoupper(null) TypeError crash on the settings page's System Health logs, CAPI logs, and Audience Sync logs by adding explicit string typecasts (implemented by Shashi).

= 1.2.56 =
* Fixed the diagnostic report download crash (ERR_INVALID_RESPONSE) by cleaning and terminating all active output buffers before writing headers, and added PHP 8.1+ type safety guards (implemented by Shashi).

= 1.2.55 =
* Optimized the get_users() queries inside the settings and workspace pages to retrieve only ID and display_name, preventing N+1 query patterns (implemented by Shashi).

= 1.2.54 =
* Relocated settings tab-switching JS functions to the top header to prevent settings navigation ReferenceErrors (implemented by Shashi).

= 1.2.53 =
* Changed the landing_page column data type from varchar(255) to text to support long landing page URLs containing query parameters (implemented by Shashi).

= 1.2.52 =
* Added frontend and backend protection to prevent duplicate comments by disabling comment submit buttons on click, updating button text, and executing a database-level query to ignore duplicate comments made within a 15-second window (implemented by Shashi).

= 1.2.51 =
* Optimized lead details drawer load performance by pre-rendering activity timeline and recent comments inline in the details PHP view, eliminating a redundant sequential AJAX HTTP request on startup (implemented by Shashi).

= 1.2.50 =
* Disabled lead import and export functionalities (both front-end UI buttons/modals and backend AJAX endpoints) for non-administrator accounts (implemented by Shashi).

= 1.2.49 =
* Fixed CSS display specificity issue on Save Settings buttons and footer action container using inline style overrides to ensure they are hidden on the Help, Health, and Danger Zone tabs (implemented by Shashi).

= 1.2.48 =
* Hid header and footer Save Settings buttons and footer container on Danger Zone, Help & FAQs, and System Health tabs (implemented by Shashi).

= 1.2.47 =
* Fixed visibility of lead routing source mapping subpanel (#idlm-source-mapping-subpanel) so it remains hidden for Manual and Round Robin strategies (implemented by Shashi).

= 1.2.46 =
* Redesigned settings page HTML/CSS to match Q Oak Interiors mockup theme and design system (implemented by Shashi).

= 1.2.45 =
* Fixed `$wpdb` null reference errors causing fatal crashes on settings page load and corrected missing `idlm_cost_per_lead` table identification in diagnostics reporting (implemented by Shashi).

= 1.2.44 =
* Fixed settings tabs navigation failure by declaring `global $wpdb` at the top of the settings page render method to prevent PHP execution crashes (implemented by Shashi).

= 1.2.43 =
* Designed and implemented a dedicated System Health & Telemetry error tracker framework to intercept PHP exceptions, capture JavaScript console errors, and record integration logs inside a new `wp_idlm_telemetry_logs` database table (implemented by Shashi).
* Registered global error and exception handlers, locking error capture strictly to code execution within the plugin's namespace (implemented by Shashi).
* Integrated a new "System Health" Settings page tab featuring Diagnostics overview metrics, configurable logging level rules, a diagnostics table log viewer with full stack trace modals, and log clearing AJAX actions (implemented by Shashi).
* Created a one-click Diagnostics Report text file exporter compiling active environment parameters, custom option variables, database tables capacity, and recent logs (implemented by Shashi).
* Programmed a daily logs rotation task hooked to standard cron routines to automatically delete logs older than 14 days and cap the table count to 1,000 rows (implemented by Shashi).

= 1.2.42 =
* Implemented immediate synchronous Conversions API (CAPI) upload for the `New` stage lead capture to guarantee instant tracking (implemented by Shashi).
* Added cancellation/debouncing logic in the CAPI event scheduler, clearing any pending stage transitions for a lead whenever a new stage transition is triggered to prevent duplicate or conflicting events (implemented by Shashi).
* Fixed a bug in the settings page UI loop that overwrote blank stage mappings with default event names, allowing users to leave a stage mapping blank to exclude it from Meta, Google, or LinkedIn sync (implemented by Shashi).
* Enforced strict click ID guards, preventing Google Ads CAPI uploads from firing without a valid `gclid`, `gbraid`, or `wbraid` identifier, and preventing LinkedIn CAPI uploads without a valid `li_fat_id` (implemented by Shashi).
* Formulated the Meta CAPI `fbc` click ID parameter dynamically based on the lead's database creation timestamp (implemented by Shashi).

= 1.2.41 =
* Added a default event mapping ('New' => 'Lead') and a fallback in the Meta Conversions API (CAPI) upload logic so that new, non-duplicate lead capture events trigger and transmit a standard 'Lead' conversion event to Meta Ads automatically (implemented by Shashi).
* Updated the stage-to-event mappings form loop inside the CAPI settings interface to display 'Lead' as the default mapped event name value and placeholder for the 'New' stage (implemented by Shashi).

= 1.2.40 =
* Added a "Pending Quality" metric summary card to the `.idlm-metric-grid` block on the Leads Database workspace (implemented by Shashi).
* Integrated click-to-filter JavaScript logic for the "Pending Quality" metric summary card to automatically set the Lead Quality filter to "Pending" and refresh the database results (implemented by Shashi).

= 1.2.39 =
* Added a "Pending (Choose...)" option to the Lead Quality dropdown filter on the Leads Database table workspace (implemented by Shashi).
* Updated the lead query builder logic in `includes/class-admin.php` to fetch leads with blank (`""`) or `NULL` quality values when the "Pending" filter is selected (implemented by Shashi).

= 1.2.38 =
* Added the standard `<hr class="wp-header-end">` marker element in the settings template to fix WordPress core admin notices being positioned between the header title and the subtitle, aligning notifications to display correctly at the very top of the settings page wrapper (implemented by Shashi).

= 1.2.37 =
* Added a duplicate "Save Settings" submit button in the top header (`.idlm-header`) of the settings panel to make it easier to save changes without scrolling to the bottom (implemented by Shashi).
* Refactored the settings tab toggle function to support dynamic visibility switches for multiple global submit buttons (implemented by Shashi).

= 1.2.36 =
* Corrected the missing closing div tag in the settings-capi (Conversion APIs) tab container within includes/class-admin.php. This resolves the layout nesting issue that made subsequent tabs ("Sync & Reminders", "Help & FAQs", and "Danger Zone") invisible and incorrectly nested under CAPI, and restores consistent bottom padding and WordPress footer alignment across all setting panels (implemented by Shashi).

= 1.2.35 =
* Replaced intrusive browser alert dialogs on clipboard copy buttons with a smooth, inline button text transition ("Copied!") for 2 seconds (implemented by Shashi).
* Refactored idlmCopyToClipboard() in assets/js/admin-app.js to accept a direct button element reference and support inline UI feedback (implemented by Shashi).

= 1.2.34 =
* Fixed tab container visibility bugs where switching tabs rendered setting sections invisible by resolving the precise closing div counts at the boundaries of Tab 1 (settings-general) and Tab 2 (settings-routing), eliminating nested layout issues (implemented by Shashi).

= 1.2.33 =
* Corrected settings tab HTML closing div tags across Tab 1, Tab 2, Tab 3, and Tab 4 in class-admin.php. This prevents the primary container `.wrap.idlm-wrap` from closing early, thereby normalizing bottom padding and margins consistently across all tabs (Conversion APIs, Sync & Reminders, Help & FAQs, Danger Zone) and preventing footer overlap (implemented by Shashi).

= 1.2.32 =
* Fixed an overlapping layout bug where the WordPress footer (#footer-thankyou) was rendering on top of settings tabs by introducing proper bottom padding and clear rules to `#wpfooter` (implemented by Shashi).
* Replaced the intrusive clipboard copy browser alert dialogs with smooth, inline button feedback, updating button text temporarily to "Copied!" (implemented by Shashi).

= 1.2.31 =
* Relocated platform-specific webhook setup instructions and URLs directly under their respective platform sub-tabs (Meta, Google, LinkedIn, Website) within the "Webhooks Payload Field Mappings" settings page section to improve contextual access and visibility (implemented by Shashi).
* Removed the separate redundant webhooks configuration block from the "Help & FAQs" tab, keeping only general Cloudflare WAF bypass guidelines and FAQ sections (implemented by Shashi).

= 1.2.30 =
* Added a new dedicated "Help & FAQs" tab in the settings interface, decluttering the main settings views (implemented by Shashi).
* Relocated the Cloudflare Security Guidance Notice and Webhook Platform URLs configuration guides from the Incoming Webhooks tab to the Help & FAQs tab (implemented by Shashi).
* Added direct contextual help reference links under the Incoming Webhooks settings to auto-switch focus to the Help & FAQs panel (implemented by Shashi).
* Added an FAQ section covering smart duplicate detection, platform stage mapping exclusions, and Google Click ID validation rules (implemented by Shashi).

= 1.2.29 =
* Refactored settings page tab switching logic to pass DOM element references (this) instead of browser events, resolving tab initialization issues and ensuring "General Settings" is always active by default on fresh loads (implemented by Shashi).

= 1.2.28 =
* Fixed an issue where the PHP active tab detection code was printed as plain text due to an incorrectly placed PHP closing tag (implemented by Shashi).

= 1.2.27 =
* Reorganized the cluttered admin settings page into 6 distinct, well-structured tabs using client-side JavaScript to prevent data loss while editing, and saved active tab persistence on save (implemented by Shashi).
* Integrated hash-based URL routing (e.g. `#tab-capi`) to support direct linking/bookmarking of settings panels (implemented by Shashi).
* Hidden the global "Save Settings" button on the "Danger Zone" tab to prevent user confusion and focus the reset actions (implemented by Shashi).

= 1.2.26 =
* Added customizable stage-to-event mappings for Meta Ads CAPI, Google Ads OCI/CAPI, and LinkedIn Ads CAPI (implemented by Shashi).
* Introduced dynamic mapping tables in the admin settings screen rendering all custom sales stages with inputs for platform-specific event/conversion names (implemented by Shashi).
* Enforced platform exclusion rules: conversions for stages with empty/blank mapping names are skipped, and Google OCI feed/CSV excludes unmapped stages entirely (implemented by Shashi).

= 1.2.25 =
* Added leads database tracking columns for landing page URL (landing_page) and stage-conversion timestamp (conversion_time) (implemented by Shashi).
* Mapped CRM stage conversion lifecycles to Google Ads standard conversion actions: Qualified -> 'CRM Qualified Lead', Consultation Booked -> 'Consultation Booked', Site Visit -> 'Site Visit Booked', and Won -> 'Closed Won Interior Project' (implemented by Shashi).
* Updated cookie retrieval and custom webhook logic to merge cookie-captured parameters for frontend website form submissions (implemented by Shashi).
* Extended CSV templates, lead drawer views, dynamic mapping forms, standard CSV exports, and CSV import parser logic to support landing page and conversion timestamp attributes (implemented by Shashi).
* Incremented the plugin version across constants, headers, and readme files to 1.2.25 (implemented by Shashi).

= 1.2.24 =
* Added full support for Google Ads Offline Conversion Imports (OCI) by storing Google Click ID (gclid), Google App Click ID (gbraid), and Google Web Click ID (wbraid) dynamically (implemented by Shashi).
* Updated front-end cookie script, API lead webhook parser, CSV import templates, lead drawer views, and standard CSV exports to include gbraid and wbraid click ID attributes (implemented by Shashi).
* Integrated dynamic, secure REST API conversions feed CSV output under the path /wp-json/interior-design-lead-manager/v1/google-ads-conversions?api_key=... for direct import scheduling inside Google Ads, and added a manual download button in settings (implemented by Shashi).
* Implemented strict platform-specific conversion guards that skip uploading to Google if there are no Google click IDs (gclid, gbraid, or wbraid) or if the lead is not a Google-related source, and prevent cross-platform conversion leakage to LinkedIn (implemented by Shashi).
* Incremented the plugin version across constants, headers, and readme files to 1.2.24 (implemented by Shashi).

= 1.2.23 =
* Cleaned up remaining inline styling references to 'Outfit' font family in HTML markup pages to ensure consistent 'Inter' typography (implemented by Shashi).

= 1.2.22 =
* Stored font assets locally and enqueued them via a local stylesheet to improve performance (implemented by Shashi).
* Fixed duplicate callback datetime update events on save/exit by normalizing comparisons using parsed Unix timestamps (implemented by Shashi).

= 1.2.21 =
* Removed all references and usages of the 'Outfit' font family across stylesheets and Google Font enqueues, standardizing the entire plugin UI strictly on 'Inter' (implemented by Shashi).

= 1.2.20 =
* Enabled the Escape key (Esc) to close all active plugin pop-ups, drawers, and modals in order of layering priority (implemented by Shashi).
* Integrated custom input and change event listeners to track unsaved edits in the lead details form (implemented by Shashi).
* Added a premium 'Unsaved Changes' confirmation modal that details which fields were edited and allows sales representatives to either save and close, discard and close, or keep editing (implemented by Shashi).

= 1.2.19 =
* Converted all date/time output values from 24-hour format to 12-hour format with AM/PM indicators globally (implemented by Shashi).
* Modified the format_datetime helper function default parameters to output 'M d, Y h:i A' (implemented by Shashi).
* Updated callback schedule log reason outputs to use 'M d, h:i A' format (implemented by Shashi).

= 1.2.18 =
* Removed the padded `.idlm-card` wrapping element from the main leads table, moving the table and pagination directly into the layout grid (implemented by Shashi).
* Transferred card presentation rules (borders, background color, shadow, and margin-bottom) directly onto the `.idlm-table-wrapper` container to support edge-to-edge content display (implemented by Shashi).
* Integrated custom table row hover states, aligned border styling variables, and eliminated the bottom border on the final table row to prevent double border lines (implemented by Shashi).

= 1.2.17 =
* Configured relative positioning on drawer and modal headers by default to prevent absolute-positioned close buttons from rendering outside parent containers (implemented by Shashi).
* Refined media query layouts for modal headers under 700px to include responsive padding and dimensions matching standard close button placement (implemented by Shashi).
* Standardized Callback Scheduler and Deal Value Input close buttons by removing their inline font size styling overrides (implemented by Shashi).

= 1.2.16 =
* Adapted close buttons (`.idlm-close`) to render with a general layout and hover transition, applying absolute top-right positioning on mobile query dimensions across both drawers and modals (implemented by Shashi).

= 1.2.15 =
* Implemented premium custom styles for table pagination controls, buttons, hover states, disabled states, and text indicators matching the Q Oak design aesthetic (implemented by Shashi).

= 1.2.14 =
* Refactored global CSS variables and base wrap styling rules in the plugin stylesheet to incorporate mockup colors, typography, borders, shadows, and spacing tokens across the entire CRM plugin workspace (implemented by Shashi).

= 1.2.13 =
* Added custom SVG dropdown chevron arrows for all select elements in the plugin to ensure consistent styling and cross-browser support (implemented by Shashi).

= 1.2.12 =
* Removed blocking overflow settings from the tab wrappers to enable sticky positioning on child panels, allowing sidebars and comment panels to remain sticky during scroll (implemented by Shashi).

= 1.2.11 =
* Refactored the form footer structure to be positioned outside of the scrollable drawer body, rendering it completely static at the bottom of the modal as in the original mockup design (implemented by Shashi).

= 1.2.10 =
* Fixed form footer layout to remain sticky/static at the bottom of the details workspace, and made the "Add Note / Comment" panel sticky inside the Activity Log tab (implemented by Shashi).

= 1.2.9 =
* Filtered Details tab sidebar 'Recent Comments' section to only display comments (excluding system pipeline activity logs) and limit display to the 5 most recent comments (implemented by Shashi).

= 1.2.8 =
* Added desktop/mobile layout overrides, footer styling, and mobile responsive media queries from the mockup file to fix the mobile layout views for lead profile drawers (implemented by Shashi).

= 1.2.7 =
* Cleaned up old drawer layout media query overrides to prevent mobile display squishing and ensure the new Q Oak Interiors full-screen responsive layout renders correctly on mobile devices (implemented by Shashi).

= 1.2.6 =
* Updated the layout and style of the lead details pop-up modal to match the premium Q Oak Interiors design mockup layout, status grids, and tabs (implemented by Shashi).
* Integrated dynamic status values and Assigned Rep display names in the modal header via AJAX (implemented by Shashi).
* Added a new comments & activity log sidebar to the main Details tab view (implemented by Shashi).

= 1.2.5 =
* Implemented strict response body JSON parsing validations for Meta and LinkedIn CAPI to ensure invalid tokens and API error results are correctly logged as FAILED rather than SUCCESS (implemented by Shashi).
* Added checkable rows and a "Retry/Send Selected Events" bulk actions bar to the CAPI logs table supporting force resends of failed events (implemented by Shashi).

= 1.2.4 =
* Added Cloudflare WAF bypass configuration guidance for the lead sync webhook endpoint in the admin panel to prevent Cloudflare Under Attack Mode challenges from blocking incoming leads (implemented by Shashi).

= 1.0.81 =
* Removed the redundant 'Qualified Lead Rate by Channel (%)' chart from the dashboard, as it was duplicate information to 'Lead Quality by Channel', and optimized performance by removing its backend SQL database queries (implemented by Shashi).
* Rendered the 'Lead Quality by Channel' stacked bar chart as a full-width card for a spacious and premium layout (implemented by Shashi).

= 1.0.80 =
* Fixed tooltip percentage calculation for stacked charts (Lead Quality by Channel and UTM parameters) to be relative to the specific category/column total rather than the entire chart total (implemented by Shashi).

= 1.0.79 =
* Wrapped Chart.js tooltip label callback inside a callbacks nested configuration block so custom tooltip formatting registers properly on all charts (implemented by Shashi).

= 1.0.78 =
* Added dynamic percentage calculations relative to overall totals inside the hover tooltips for all dashboard charts, handling rate-based data gracefully (implemented by Shashi).

= 1.0.77 =
* Fixed Overall Lead Quality Distribution doughnut chart rendering size bug inside hidden tab containers (implemented by Shashi).
* Fixed Lead Quality by Channel bar chart split matching bug by forcing integer casting and case-insensitivity on database columns (implemented by Shashi).

= 1.0.76 =
* Planned and integrated premium lead quality distribution analytics (Overall Doughnut, stacked Channel and stacked UTM Campaign/Source/Medium distribution charts) and updated Sales Representatives table columns to show lead quality data (implemented by Shashi).

= 1.0.75 =
* Added new AJAX endpoint `idlm_get_lead_activity` to retrieve timeline activity logs dynamically on-demand and optimized comment/activity UI update mechanisms (implemented by Shashi).
* Added a database B-Tree index on the `duplicate_of_id` column inside the CRM Leads table to prevent full table scans when checking for duplicates of a lead (implemented by Shashi).


= 1.9.9 =
* Fixed Conversions API (CAPI) event deduplication across Meta, Google, and LinkedIn by checking logs across the canonical lead and all its duplicates (implemented by Shashi).
* Added automatic duplicate lead attribution data merging and source channel promotion when Meta Lead Ads sync late (implemented by Shashi).
* Included event_id in Meta CAPI payload and filtered out duplicate leads from Google Ads Offline Conversions exports (implemented by Shashi).

= 1.0.74 =
* Prevented text wrapping inside channel source badges by setting white-space nowrap, ensuring badges like 'Meta Ads' fit correctly on a single line (implemented by Shashi).

= 1.0.73 =
* Fixed blank rendering/access restricted error on the Leads list and dashboard for representative users with 'agent' or 'agentadmin' roles by adding them to the allowed rep access roles list (implemented by Shashi).

= 1.0.72 =
* Fixed Javascript syntax parsing error inside admin-app.js caused by a missing closing bracket in the comment delete click handler, which was preventing the table loading scripts from executing and rendering leads (implemented by Shashi).

= 1.0.71 =
* Enabled interactive filtering by clicking on the header "Pending Calls" pill/badge to immediately show only the leads that require calling/following up (implemented by Shashi).

= 1.0.70 =
* Fixed bug where removing a status from Followup Reminder Settings still counted leads with that status in the pending calls notification by dynamically querying the active reminder rule statuses (implemented by Shashi).

= 1.0.69 =
* Standardized drawer details table input and read-only text fields to use monospace code fonts (Monaco 12px) for structural legibility and consistency (implemented by Shashi).

= 1.0.68 =
* Fixed bug where comment deletion failed by replacing cached jQuery data('id') lookups with direct DOM attr('data-id') attributes and adding robust AJAX error parsing to prevent JS crashes (implemented by Shashi).

= 1.0.67 =
* Added color coding styles for individual lead source badges (Meta Ads, Google Ads, LinkedIn Ads, Website, Referral, Offline, CSV) in the leads list table and details drawer (implemented by Shashi).

= 1.0.66 =
* Added ability to edit and delete manual timeline comments/notes inline in the lead details drawer (implemented by Shashi).

= 1.0.65 =
* Increased activity log comment input textarea height to 200px (implemented by Shashi).

= 1.0.64 =
* Relocated the lead avatar, name, and channel source badge to the sticky drawer header, removing the profile section to save vertical viewing space (implemented by Shashi).

= 1.0.63 =
* Integrated Value-Based Conversions API (CAPI) events for Meta, Google, and LinkedIn with a custom pop-up/modal prompting for project deal values on "Won" stage conversions (implemented by Shashi).

= 1.0.62 =
* Refactored Activity Log & Comments to a 2-column layout and added tab context preservation on reload (implemented by Shashi).

= 1.0.61 =
* Fixed modal popup background scrolling leak and chaining (implemented by Shashi).

= 1.0.60 =
* Relocated Leads Over Time line/area chart right below stats grid (implemented by Shashi).

= 1.0.59 =
* Integrated Leads Over Time, Preferred Studio locations, BHK distribution widgets and representative average contact/close speed metrics (implemented by Shashi).

= 1.0.58 =
* Security audits, webhook path checks, and rep-access helper validations (implemented by Shashi).

= 1.0.57 =
* Converted Details tab into responsive multi-column grid layout (implemented by Shashi).

= 1.0.56 =
* Converted details side drawer into centered popup covering 75% of screen (implemented by Shashi).

= 1.0.55 =
* Increased spacing between the tab link triggers and the bottom container border in dashboard tabs (implemented by Shashi).

= 1.0.54 =
* Fixed stage defaulting to New on lead insertion and resolved empty stage counting mismatch in dashboard calculations (implemented by Shashi).

= 1.0.53 =
* Refactored Sales Conversion Funnel widget to a horizontal layout with vertical liquid progress fills for space-saving layout (implemented by Shashi).

= 1.0.52 =
* Added a new Sales Conversion Funnel dashboard widget in the Sales tab, tracking lead progression dynamically from New to Won (implemented by Shashi).

= 1.0.51 =
* Enabled Conversion API triggers when Lead Quality changes to Qualified, sending the corresponding conversion event (implemented by Shashi).

= 1.0.38 =
* Resolved wpdb::prepare() called incorrectly PHP notice by removing the prepare wrapper on SQL count queries that do not contain query placeholders.

= 1.0.37 =
* Completed comprehensive security, compliance, and coding standards auditing for the WordPress Plugin Check tool.
* Bundled the chart.js library locally in assets/js/chart.js.
* Wrapped all database queries using `$wpdb->prepare()`.
* Timezone-safe date parsing and standard PHPCS compliance auditing.
