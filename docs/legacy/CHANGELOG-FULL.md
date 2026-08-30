# Archived Full Changelog

All notable changes to this project will be documented in this file.

## [1.94.1] - 2026-08-29
### Fixed
- Restored the frontend `idlm_view=quotations` workspace by adding the renderer-side date-filter helper omitted during the quotation class split and normalized empty pagination output to avoid PHP 8.x deprecations (implemented by Shashi).

### Tests
- Added behavioral date-normalization coverage and a native WordPress regression test that renders the authenticated quotation list route with date filters (implemented by Shashi).

### Areas Changed
- Quotation renderer, automated tests, and release metadata

## [1.94.0] - 2026-08-29
### Security
- Encrypted webhook, MCP, and Google feed credentials at rest with optional environment constants, migrated existing plaintext values, removed the legacy shared key, and rejected all URL/query credentials (implemented by Shashi).
- Redacted credential/contact fields from new raw integration payload logs and scheduled configurable touchpoint, integration-log, and telemetry pruning (implemented by Shashi).

### Changed
- Added a stable workflow state registry and a shared lead authorization/query-scope policy used by admin, dashboard, and quotation reads (implemented by Shashi).
- Moved round-robin counters to atomic database state and assignment overlap coordination to database locks (implemented by Shashi).
- Added transactional single/bulk lead deletion, dependent cleanup, orphan audit/repair services, and the `wp idlm integrity audit [--repair]` command (implemented by Shashi).
- Replaced quotation print DOM rewriting with final semantic server-rendered headers, party cards, summary rows, notes, and breakdown markup (implemented by Shashi).
- Added a GitHub Actions PHP 7.4/8.1/8.4 and WordPress 6.6/latest matrix running both behavioral and native MySQL suites (implemented by Shashi).

### Tests
- Expanded the fast suite to 53 passing contracts and verified all 9 disposable native WordPress/MySQL integration tests, including real authenticated AJAX dispatch (implemented by Shashi).

### Areas Changed
- Credentials, access/state policies, routing, integrity, retention, print rendering, CI, schema/version metadata, tests, and engineering documentation

## [1.93.0] - 2026-08-29
### Changed
- Split the 36 authenticated AJAX handlers into `IDLM_Admin_Ajax` behind the existing `IDLM_Admin` facade and actions (implemented by Shashi).
- Split quotation request/transaction handlers and HTML/print rendering into controller and renderer units behind the existing quotation facade (implemented by Shashi).
- Added `IDLM_Lead_Lifecycle_Service` behind `IDLM_DB` compatibility delegates and routed the structured early-close write through lifecycle validation, persistence, task completion, audit, and hook handling (implemented by Shashi).

### Tests
- Retained the complete 47-test behavioral baseline across lifecycle, AJAX authorization, REST/MCP, quotation, and schema contracts (implemented by Shashi).

### Areas Changed
- Admin, quotation, and lead lifecycle boundaries (`includes/class-admin*.php`, `includes/class-quotation*.php`, `includes/class-db.php`, `includes/class-lead-lifecycle-service.php`)
- Plugin bootstrap, engineering documentation, automated tests, and release metadata

## [1.92.5] - 2026-08-29
### Changed
- Added `IDLM_Quotation_Policy` as the quotation capability and object-level lead-access boundary, with existing facade checks retained as delegates (implemented by Shashi).
- Moved lead search, initial editor lead loading, selected-lead loading, and lead existence checks into `IDLM_Quotation_Lead_Read_Model` with administrator/manager unrestricted scope and representative ownership scope (implemented by Shashi).

### Tests
- Added administrator, manager, owner, restricted-representative, and unauthorized-user authorization/read-scope coverage (implemented by Shashi).

### Areas Changed
- Quotation authorization and scoped lead reads (`includes/class-quotation-policy.php`, `includes/class-quotation-lead-read-model.php`, `includes/class-quotations.php`)
- Plugin bootstrap, automated tests, engineering documentation, and release metadata

## [1.92.4] - 2026-08-29
### Changed
- Extracted catalogue item/category/unit CRUD, bulk/category actions, CSV import persistence helpers, export chunks, catalogue search/pagination, and scoped quotation-list read queries into `IDLM_Quotation_Repository` (implemented by Shashi).
- Retained capability and nonce enforcement, request/file/CSV validation, import transaction coordination, streamed export output, rendering, public actions, redirect messages, and response formats in `IDLM_Quotations` (implemented by Shashi).

### Tests
- Added catalogue CRUD, bulk-action, read-model, prepared-filter, and write-failure coverage plus native catalogue persistence/search/export/category-protection and quotation-list verification (implemented by Shashi).

### Areas Changed
- Quotation catalogue/query repository and compatibility facade (`includes/class-quotation-repository.php`, `includes/class-quotations.php`)
- Automated tests, engineering documentation, and release metadata

## [1.92.3] - 2026-08-29
### Changed
- Extracted quotation and revision aggregate persistence into `IDLM_Quotation_Repository`, including idempotency lookups, quote/revision writes, locked revision sequencing, section/item replacement and copying, and the lead quote-value projection (implemented by Shashi).
- Retained nonce/capability/object-access checks, input normalization, redirects, transaction coordination, public actions, request fields, totals, and stored behavior in `IDLM_Quotations` (implemented by Shashi).

### Tests
- Added repository aggregate-write, revision-copy, and simulated write-failure coverage; retained the native exact quotation-persistence and concurrency verification (implemented by Shashi).

### Areas Changed
- Quotation persistence repository and compatibility facade (`includes/class-quotation-repository.php`, `includes/class-quotations.php`)
- Automated tests, engineering documentation, and release metadata

## [1.92.2] - 2026-08-29
### Changed
- Extracted quotation table installation, legacy conversion, and snapshot/section backfills into `IDLM_Quotation_Schema` behind the existing `IDLM_Quotations::install()` and migration methods (implemented by Shashi).
- Added `IDLM_Quotation_Repository` for prefixed table resolution, atomic quote numbering, quotation/revision aggregate reads, status-history writes, lead-scoped cleanup, and reset operations (implemented by Shashi).
- Retained `IDLM_Quotations` public methods, schema version, hooks, actions, request fields, transactions, totals, table names, migration markers, and stored behavior as compatibility contracts (implemented by Shashi).

### Tests
- Added repository-delegate, migration-marker, and schema-version coverage; retained native fresh-install, idempotent-upgrade, concurrent-numbering, and exact quotation-persistence verification (implemented by Shashi).

### Areas Changed
- Quotation repository and schema boundaries (`includes/class-quotation-repository.php`, `includes/class-quotation-schema.php`)
- Quotation compatibility facade and bootstrap (`includes/class-quotations.php`, `interior-design-lead-manager.php`)
- Automated tests, engineering documentation, and release metadata

## [1.92.1] - 2026-08-29
### Changed
- Extracted quotation input normalization and totals calculation into a deterministic `IDLM_Quotation_Calculator` while retaining the existing `IDLM_Quotations` calculation methods as compatibility delegates (implemented by Shashi).
- Added regression coverage proving repeated calculations and the compatibility delegates produce identical item, section, and totals data (implemented by Shashi).

### Areas Changed
- Quotation calculator and compatibility API (`includes/class-quotation-calculator.php`, `includes/class-quotations.php`)
- Plugin bootstrap, automated tests, engineering documentation, and release metadata

## [1.92.0] - 2026-08-28
### Security
- Split webhook, MCP, and Google feed credentials into independently rotatable scopes while retaining a 30-day legacy compatibility window (implemented by Shashi).
- Enforced route-specific REST permission callbacks, constant-time credential comparisons, header-first authentication, and per-IP request limits (implemented by Shashi).
- Restricted custom MCP SQL to bounded SELECT statements over allowlisted CRM tables and capped MCP payload, batch, query-result, and activity-history sizes (implemented by Shashi).

### Tests
- Added regression coverage for credential migration/rotation, REST authorization, Google Basic authentication, rate limits, MCP resource bounds, and malicious SQL rejection (implemented by Shashi).

### Areas Changed
- REST/MCP authentication and transport limits (`includes/class-integrations.php`)
- MCP query policy and result bounds (`includes/class-mcp-core.php`)
- Credential settings and rotation controls (`includes/class-admin.php`)
- Automated tests and security/interface documentation
- Plugin, readme, and release metadata

## [1.91.3] - 2026-08-28
### Security
- Enforced object-level lead access across every bulk lifecycle target, preventing restricted CRM representatives from mutating leads outside their configured visibility (implemented by Shashi).

### Tests
- Added a dependency-free behavioral test baseline for lead lifecycle transitions and side effects, AJAX authorization, REST/MCP authentication, quotation calculations/numbering/autosave, and schema migration contracts (implemented by Shashi).

### Areas Changed
- Bulk lead authorization (`includes/class-admin.php`)
- Automated test harness and fixtures (`tests/`)
- Plugin, readme, and release metadata

## [1.91.2] - 2026-08-28
### Documentation
- Added a business-oriented feature map connecting leads, follow-ups, quotations, WhatsApp, users and roles, PDFs/exports, settings, reporting, advertising, and telephony to their actual PHP, storage, interface, hook, and client-side implementation surfaces (implemented by Shashi).
- Linked feature-specific Codex reviews to the new blast-radius map (implemented by Shashi).

### Areas Changed
- Feature and Codex documentation (`docs/FEATURE-MAP.md`, `AGENTS.md`, `docs/CHANGELOG-DEV.md`)
- Plugin, readme, and release metadata

## [1.91.1] - 2026-08-28
### Documentation
- Added a source-verified Codex guide and concise engineering documentation for architecture, project structure, data model, workflows, callable interfaces, security, development conventions, and technical debt (implemented by Shashi).
- Recorded compatibility-preserving structural recommendations and high-risk modification boundaries without changing production behavior (implemented by Shashi).

### Areas Changed
- Repository and engineering documentation (`AGENTS.md`, `docs/*.md`)
- Plugin, readme, and release metadata

## [1.91.0] - 2026-08-28
### Changed
- Distributed all Quote numbering controls equally across one responsive inline row (implemented by Shashi).
- Removed Company name and Footer text controls from PDF appearance and distributed Logo URL, Accent colour, and Paper size equally inline (implemented by Shashi).
- Preserved previously stored company and footer values when saving the streamlined setup form (implemented by Shashi).

### Areas Changed
- Quotation setup rendering and settings persistence (`includes/class-quotations.php`)
- Responsive quotation setup presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.90.0] - 2026-08-28
### Fixed
- Made the PDF Additional Notes section conditional on a non-empty Customer introduction and rendered its escaped text when present; whitespace-only values no longer create an empty section (implemented by Shashi).
- Continued excluding internal CRM notes from customer PDF output (implemented by Shashi).

### Areas Changed
- Quotation PDF data and conditional presentation (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.89.0] - 2026-08-28
### Changed
- Left-aligned PDF section titles, including Quotation Summary, Section & Item Breakdown, and Additional Notes, together with their gold accent rules (implemented by Shashi).

### Areas Changed
- Quotation PDF heading presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.88.0] - 2026-08-28
### Fixed
- Corrected PDF quotation-summary header alignment so `Sl No` and `Section` align with their left-aligned values while `Subtotal before discount` remains right-aligned (implemented by Shashi).

### Areas Changed
- Quotation PDF summary presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.87.0] - 2026-08-28
### Changed
- Added `Sl No`, `Section`, and `Subtotal before discount` columns to the PDF quotation summary's section-wise table without changing calculation values (implemented by Shashi).

### Areas Changed
- Quotation PDF summary presentation (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.86.0] - 2026-08-28
### Changed
- Removed revision-level payment milestone editing, autosave, persistence, copying, detail presentation, and direct PDF output (implemented by Shashi).
- Retained the global payment structure and `[idlm_payment_structure]` shortcode as the sole payment-milestone source for after-quote HTML (implemented by Shashi).
- Preserved legacy milestone tables and rows for backward-compatible cleanup without displaying or modifying them (implemented by Shashi).

### Areas Changed
- Quotation editor, save flow, autosave, revision loading, detail view, and PDF output (`includes/class-quotations.php`)
- Quotation milestone controls and presentation (`assets/js/quotations.js`, `assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.85.0] - 2026-08-28
### Changed
- Removed revision-scoped commercial and contractual term fields from quotation creation, editing, autosave, CRM details, schema creation, and PDF output; terms are now supplied exclusively through the configured after-quote HTML (implemented by Shashi).
- Preserved any legacy database columns and values without displaying, copying, or modifying them (implemented by Shashi).

### Areas Changed
- Quotation persistence, editor, details, schema, and PDF output (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.84.0] - 2026-08-28
### Changed
- Removed quotation workflow actions and all quotation-state labels, filters, badges, and revision annotations from the module interface (implemented by Shashi).
- Made every current quotation revision editable and printable for users with the existing quotation capabilities, while retaining stored legacy state data for backward compatibility (implemented by Shashi).

### Areas Changed
- Quotation routing, editing, listing, details, PDF access, and presentation (`includes/class-quotations.php`, `assets/js/quotations.js`, `assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.83.0] - 2026-08-28
### Added
- Wired revision-scoped contractual terms and payment milestones through quotation editing, autosave, persistence, revision copying, CRM detail, and customer PDF output (implemented by Shashi).
- Added database-backed quotation and revision save idempotency keys plus capability-filtered server-side lead search (implemented by Shashi).

### Security
- Neutralized spreadsheet formula prefixes in catalogue CSV exports and added CSV upload error, extension, MIME, size, header, column, field, and row validation (implemented by Shashi).

### Fixed
- Added final-submit locking, dirty-navigation warnings, and keepalive autosave flushing for refresh and tab-close reliability (implemented by Shashi).
- Added cancellable, sequenced per-row catalogue search and bounded server-side bulk catalogue lookup with visible failure states (implemented by Shashi).
- Added quotation and catalogue pagination, removed main-list creator N+1 lookups, capped initial lead loading, and primed revision and representative user caches (implemented by Shashi).
- Made lead deletion and all related quotation cleanup one checked transaction with rollback on any failed write (implemented by Shashi).
- Streamed catalogue CSV exports in bounded chunks and made catalogue imports transactional with a 2,000-row request limit (implemented by Shashi).

### Areas Changed
- Quotation schema, terms, milestones, idempotency, pagination, search, CSV, deletion, and query behavior (`includes/class-quotations.php`)
- Quotation autosave, submit, lead search, catalogue search, and milestone controls (`assets/js/quotations.js`)
- Quotation milestone and pagination presentation (`assets/css/quotations.css`)
- Atomic lead deletion integration (`includes/class-admin.php`)
- Plugin, readme, schema, and release metadata

## [1.82.0] - 2026-08-28
### Security
- Enforced current Draft quote and revision state on every quotation edit request, preventing finalized-state changes through crafted form submissions (implemented by Shashi).
- Removed internal CRM notes from customer print and PDF output while preserving the existing internal-note history (implemented by Shashi).

### Fixed
- Added checked rollback handling for every quotation, revision, section, item, history, lead-sync, and transaction-commit write in save, revision, and transition flows (implemented by Shashi).
- Made legacy quotation migration resumable and repair incomplete current-revision pointers before marking migration complete (implemented by Shashi).
- Preserved customer introductions through editor autosave, reload, and revision saves (implemented by Shashi).
- Prevented header-only quotations, protected the final real line item, and repaired historical header-only editor sections with a blank item row (implemented by Shashi).
- Enabled the capability-controlled quotation approval, sharing, negotiation, acceptance, rejection, expiry, cancellation, and revision lifecycle, including state locking and Draft PDF restrictions (implemented by Shashi).

### Areas Changed
- Quotation authorization, transactions, migration, workflow, PDF output, and editor rendering (`includes/class-quotations.php`)
- Quotation editor safeguards and customer-introduction preservation (`assets/js/quotations.js`)
- Quotation workflow control presentation (`assets/css/quotations.css`)
- Plugin, readme, schema, and release metadata

## [1.81.0] - 2026-08-28
### Changed
- Updated the related lead's Quote Value (INR) with the generated quotation grand total whenever a new quote or revision is successfully saved (implemented by Shashi).
- Kept the lead value update inside the quotation transaction so failed lead updates do not leave partially saved quotation data (implemented by Shashi).

### Areas Changed
- Quotation save and lead synchronization (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.80.6] - 2026-08-28
### Changed
- Added the customer name to the default quotation PDF filename after the quote and revision numbers (implemented by Shashi).

### Areas Changed
- Quotation print/PDF document naming (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.80.5] - 2026-08-28
### Changed
- Set the print document title to the quote number plus revision number so browser PDF saves default to that filename (implemented by Shashi).
- Replaced filename-invalid characters with hyphens for consistent PDF naming (implemented by Shashi).

### Areas Changed
- Quotation print/PDF document naming (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.80.4] - 2026-08-28
### Changed
- Set read-only and PDF quotation header rows to 14px left and right padding (implemented by Shashi).

### Areas Changed
- Breakdown header-row spacing (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.80.3] - 2026-08-28
### Changed
- Rebalanced all nine Section & Item Breakdown columns to prioritize item and description content while retaining usable measurement and amount columns (implemented by Shashi).
- Increased main, specification, numeric, and table-header print typography, while keeping category and item-code metadata at 10px (implemented by Shashi).
- Enabled safe wrapping and hyphenation for long breakdown-table content (implemented by Shashi).

### Areas Changed
- Breakdown PDF typography, column distribution, and text wrapping (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.80.2] - 2026-08-28
### Fixed
- Forced saved/PDF quotation header rows to use a dedicated full-width single-column grid instead of inheriting the serial-number column width (implemented by Shashi).

### Areas Changed
- Read-only and PDF header-row layout (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.80.1] - 2026-08-28
### Changed
- Increased Section & Item Breakdown print typography and rebalanced its nine columns for improved PDF readability while retaining page-width wrapping (implemented by Shashi).
- Moved Add Header from individual section headers into the shared `.idlm-add-section-row`; new headers are added to the final section (implemented by Shashi).

### Areas Changed
- Quotation editor header action (`assets/js/quotations.js`)
- Breakdown print typography and layout (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.80.0] - 2026-08-28
### Added
- Added non-priced header rows that can be inserted, titled, reordered, and removed inside individual quotation sections (implemented by Shashi).
- Persisted section headers through draft saves, working-copy autosaves, revisions, saved views, and PDF output without affecting quotation totals (implemented by Shashi).

### Areas Changed
- Quotation item schema, parsing, editor, and read-only rendering (`includes/class-quotations.php`)
- Header-row editor and print presentation (`assets/css/quotations.css`)
- Header-row interactions and calculations (`assets/js/quotations.js`)
- Plugin, readme, and release metadata

## [1.79.1] - 2026-08-28
### Changed
- Removed the border and padding from the quotation print logo box (implemented by Shashi).

### Areas Changed
- Quotation PDF logo presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.79.0] - 2026-08-28
### Removed
- Removed Due trigger from the Standard payment structure setup and saved configuration (implemented by Shashi).
- Removed the Due column and values from the `[idlm_payment_structure]` shortcode output (implemented by Shashi).

### Areas Changed
- Quotation setup and payment shortcode rendering (`includes/class-quotations.php`)
- Payment structure layout (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.78.3] - 2026-08-28
### Changed
- Formatted quotation PDF created and updated timestamps as `DD-MM-YYYY HH:MM AM/PM` using the WordPress site timezone (implemented by Shashi).

### Areas Changed
- Quotation PDF metadata (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.78.2] - 2026-08-28
### Fixed
- Removed the quotation breakdown table's inherited 960px minimum width and introduced page-fitting print columns with safe text wrapping (implemented by Shashi).
- Constrained payment-structure shortcode content, tables, media, and inline-width elements to the printable page so content no longer bleeds or clips (implemented by Shashi).

### Areas Changed
- Quotation and payment shortcode print presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.78.1] - 2026-08-28
### Changed
- Aligned quotation preview/PDF typography, colours, spacing, borders, radii, headings, totals, and line-item tables with the supplied Q Oak cover-page design (implemented by Shashi).
- Replaced the mismatched olive print-table headers with the cover's soft neutral surface and gold-accent visual language (implemented by Shashi).

### Areas Changed
- Quotation PDF presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.78.0] - 2026-08-28
### Changed
- Rebuilt the quotation preview/PDF middle content to match the supplied quote format, with document metadata, customer and Q Oak company cards, a section-wise summary, right-aligned editor totals, notes, and the section/item breakdown (implemented by Shashi).
- Preserved the configured cover page and after-quote content around the redesigned quotation body (implemented by Shashi).

### Areas Changed
- Quotation preview and print rendering (`includes/class-quotations.php`)
- Quotation PDF presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.77.2] - 2026-08-28
### Removed
- Removed the Commercial Terms section from quotation view pages (implemented by Shashi).

### Changed
- Kept Revision Previews as a full-width quotation panel after removing the adjacent Commercial Terms block (implemented by Shashi).

### Areas Changed
- Quotation detail view (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.77.1] - 2026-08-28
### Added
- Added an Additional Notes panel below the quotation sections and totals area (implemented by Shashi).
- Included Additional Notes in revision saves, working-copy autosaves, saved quotation details, and PDFs (implemented by Shashi).

### Areas Changed
- Quotation editor, persistence, autosave, details, and print output (`includes/class-quotations.php`)
- Additional Notes presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.77.0] - 2026-08-28
### Changed
- Replaced quotation totals with the specified wooden-discount, design/consultation fee, optional additional-discount, and GST calculation sequence (implemented by Shashi).
- Fixed GST at 18% on the total after any additional discount and removed the obsolete configurable global-tax setup (implemented by Shashi).
- Updated quotation detail and PDF summaries to use the same calculation presentation (implemented by Shashi).

### Areas Changed
- Revision schema, server calculations, editor totals, settings, and document summaries (`includes/class-quotations.php`)
- Live totals and conditional additional-discount interaction (`assets/js/quotations.js`)
- Compact editable totals presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.76.1] - 2026-08-28
### Changed
- Moved the quotation Totals card into the summary layout alongside Section-wise and Wood/Others summaries (implemented by Shashi).
- Changed the desktop summary layout to three columns while retaining responsive single-column stacking (implemented by Shashi).

### Areas Changed
- Quotation editor summary markup (`includes/class-quotations.php`)
- Summary grid and nested Totals presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.76.0] - 2026-08-28
### Removed
- Removed GST/tax fields from catalogue items and quotation line items, including catalogue forms, lists, search payloads, and CSV files (implemented by Shashi).
- Removed per-item tax settings and per-line tax calculations from the quotation editor and server totals (implemented by Shashi).

### Changed
- Global tax, when enabled, is calculated once on the quotation taxable total after the overall discount (implemented by Shashi).

### Areas Changed
- Catalogue and quotation schemas, forms, CSV handling, settings, and total calculations (`includes/class-quotations.php`)
- Quotation editor line and global-tax calculations (`assets/js/quotations.js`)
- Plugin, readme, and release metadata

## [1.75.0] - 2026-08-28
### Changed
- Replaced the quotation editor's category-wise summary with Wood and Others totals (implemented by Shashi).
- Persisted catalogue Wood classification in quotation revision items and backfilled existing catalogue-linked lines (implemented by Shashi).

### Areas Changed
- Quotation item schema, editor markup, and revision persistence (`includes/class-quotations.php`)
- Wood/Others live summary calculations (`assets/js/quotations.js`)
- Summary presentation selectors (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.74.0] - 2026-08-28
### Added
- Added a Wood Yes/No classification to quotation catalogue items (implemented by Shashi).
- Added Wood values to catalogue lists, item selection results, and CSV import/export (implemented by Shashi).

### Areas Changed
- Catalogue schema, item management, search payloads, and CSV handling (`includes/class-quotations.php`)
- Catalogue search and bulk-selection labels (`assets/js/quotations.js`)
- Plugin, readme, and release metadata

## [1.73.0] - 2026-08-28
### Removed
- Removed category-wise discount defaults and all category-discount controls, calculations, stored configuration, and quotation/PDF output (implemented by Shashi).

### Changed
- Category summaries now show subtotals without category-level discounts; overall quotation discounts remain available (implemented by Shashi).

### Areas Changed
- Quotation schema, settings, calculations, editor, summaries, and PDF output (`includes/class-quotations.php`)
- Live quotation calculations (`assets/js/quotations.js`)
- Obsolete category-discount styles (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.72.0] - 2026-08-27
### Added
- Added user-specific, seven-day quotation working copies that restore automatically when the draft editor is reopened (implemented by Shashi).
- Added a Cancel decision dialog with Save as Revision, Discard Changes, and Continue Editing actions (implemented by Shashi).

### Changed
- Autosave now stores temporary working-copy data instead of mutating the saved draft revision (implemented by Shashi).
- Saving a new revision or explicitly discarding changes clears the temporary working copy (implemented by Shashi).

### Areas Changed
- Working-copy persistence, restoration, discard endpoint, and editor dialog (`includes/class-quotations.php`)
- Autosave and Cancel decision lifecycle (`assets/js/quotations.js`)
- Working-copy status and Cancel dialog presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.71.0] - 2026-08-27
### Added
- Added debounced autosaving for existing quotation drafts without creating additional revisions (implemented by Shashi).
- Added visible unsaved, saving, saved-time, and error states to the quotation editor (implemented by Shashi).

### Changed
- Protected draft autosaves with a dedicated nonce, draft-edit capability checks, current-revision validation, sanitized input, and transactional section/item replacement (implemented by Shashi).
- Coordinated manual saves with in-progress autosaves to avoid overlapping submissions (implemented by Shashi).

### Areas Changed
- Draft autosave endpoint and editor status markup (`includes/class-quotations.php`)
- Debounced autosave lifecycle (`assets/js/quotations.js`)
- Autosave status presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.70.8] - 2026-08-27
### Fixed
- Restored the section-subtotal label across its intended eight-column grid span without affecting the compact S.No column (implemented by Shashi).

### Areas Changed
- Read-only quotation subtotal footer layout (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.70.7] - 2026-08-27
### Fixed
- Replaced browser-dependent table width negotiation with an explicit nine-column grid for read-only quotation rows (implemented by Shashi).
- Isolated the spanning section-subtotal footer from the S.No column width calculation (implemented by Shashi).

### Areas Changed
- Read-only quotation table layout (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.70.6] - 2026-08-27
### Fixed
- Moved the read-only table overrides after the shared draft-table rules and switched that table to automatic layout so the S.No column can remain compact (implemented by Shashi).
- Prevented shared currency-label rules from adding rupee indicators to Area / Qty and Unit in the read-only table (implemented by Shashi).

### Areas Changed
- Read-only quotation table cascade and sizing (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.70.5] - 2026-08-27
### Fixed
- Prevented broader quotation table rules from expanding the S.No column by enforcing a compact fixed width and padding (implemented by Shashi).

### Areas Changed
- Read-only quotation table presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.70.4] - 2026-08-27
### Improved
- Reduced the read-only quotation serial-number column to a compact fixed width and tightened its horizontal padding (implemented by Shashi).

### Areas Changed
- Read-only quotation table presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.70.3] - 2026-08-27
### Added
- Added a total row to `[idlm_payment_structure]` showing the summed milestone percentage and calculated payment amount (implemented by Shashi).

### Areas Changed
- Payment-structure shortcode calculation and table output (`includes/class-quotations.php`)
- Payment-structure total-row presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.70.2] - 2026-08-27
### Improved
- Balanced the read-only quotation table columns so item and description content receive useful space without pushing the subtotal off-screen (implemented by Shashi).
- Corrected currency indicators, numeric alignment, secondary item metadata, and section-subtotal emphasis (implemented by Shashi).

### Areas Changed
- Read-only quotation table presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.70.1] - 2026-08-27
### Fixed
- Replaced the odd generic read-only line-item presentation with the draft builder's section-card layout (implemented by Shashi).
- Kept the dedicated quotation summary in PDF output instead of duplicating it inside the on-screen detail panel (implemented by Shashi).

### Areas Changed
- Read-only quotation detail and PDF section rendering (`includes/class-quotations.php`)
- Read-only section header presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.70.0] - 2026-08-27
### Added
- Added a dedicated quotation-summary page with section subtotals, category subtotals and discounts, round-off, pre-tax total, tax, and total including tax (implemented by Shashi).
- Added a standard payment-structure builder in Quotation Setup and an `[idlm_payment_structure]` shortcode for after-quote HTML (implemented by Shashi).

### Changed
- Reordered quotation preview and print output into cover, summary, section/item breakdown, and configured after-quote content (implemented by Shashi).

### Areas Changed
- Quotation setup, summary calculations, payment shortcode, and print rendering (`includes/class-quotations.php`)
- Payment-structure editor behavior and presentation (`assets/js/quotations.js`, `assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.69.6] - 2026-08-27
### Fixed
- Restored the elevated white background on Item Categories and Units dialogs after their shared `.idlm-wrap` styling scope made the modal container transparent (implemented by Shashi).
- Explicitly preserved white headers and list panes with a soft-surface creation pane (implemented by Shashi).

### Areas Changed
- Category and unit modal surfaces (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.69.5] - 2026-08-27
### Fixed
- Applied the persisted collapsed-sidebar preference synchronously before the workspace first paints, preventing refresh-time expansion and layout shift (implemented by Shashi).
- Removed the temporary pre-paint marker after the regular sidebar controller initializes (implemented by Shashi).

### Areas Changed
- Workspace pre-paint state bootstrap (`includes/class-admin.php`)
- Persisted sidebar-state initialization (`assets/js/workspace.js`)
- Pre-paint collapsed presentation (`assets/css/workspace.css`)
- Plugin, readme, and release metadata

## [1.69.4] - 2026-08-27
### Fixed
- Moved open Item Categories and Units dialogs to the document body so workspace overflow and stacking contexts cannot clip them (implemented by Shashi).
- Hardened the manager modal layer above plugin drawers and WordPress admin chrome while preserving the shared backdrop (implemented by Shashi).
- Prevented global `.idlm-wrap` padding and margins from offsetting the fixed dialogs (implemented by Shashi).

### Areas Changed
- Manager-dialog portal behavior (`assets/js/quotations.js`)
- Fixed modal stacking and positioning (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.69.3] - 2026-08-27
### Fixed
- Brought Item Categories and Units popups into the shared quotation styling scope so their inputs and buttons use consistent typography, borders, radii, focus states, and control heights (implemented by Shashi).
- Standardized creation-form spacing and right-aligned primary actions across both management popups (implemented by Shashi).

### Areas Changed
- Category and unit popup styling scope (`includes/class-quotations.php`)
- Shared popup form and action presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.69.2] - 2026-08-27
### Fixed
- Prevented catalogue bulk actions from wrapping onto a second desktop line by using a compact, non-wrapping two-column filter toolbar (implemented by Shashi).
- Preserved a single-column toolbar fallback for narrow mobile screens (implemented by Shashi).

### Areas Changed
- Catalogue filter and bulk-action toolbar presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.69.1] - 2026-08-27
### Changed
- Moved catalogue bulk-action controls into the shared search-filter toolbar (implemented by Shashi).
- Refined Item Categories and Units popup sizing, scrolling, row alignment, form spacing, notices, and mobile behavior (implemented by Shashi).

### Areas Changed
- Catalogue toolbar and popup markup (`includes/class-quotations.php`)
- Linked bulk controls and selection state (`assets/js/quotations.js`)
- Catalogue toolbar and management-popup presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.69.0] - 2026-08-27
### Added
- Added a reusable Catalogue Units registry and unit manager (implemented by Shashi).
- Added secure, capability- and nonce-protected unit creation with duplicate prevention (implemented by Shashi).
- Automatically migrated existing catalogue unit values and registered units found during CSV imports (implemented by Shashi).

### Changed
- Replaced free-text catalogue unit entry with a required dropdown of registered units (implemented by Shashi).
- Validated catalogue item units against the active unit registry before saving (implemented by Shashi).

### Areas Changed
- Unit schema, migration, validation, management, catalogue editor, and CSV imports (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.68.0] - 2026-08-27
### Added
- Added item selection, select-all, and bulk archive or permanent-delete actions to the Item Catalogue (implemented by Shashi).
- Added individual and bulk archive or permanent-delete controls to Item Categories (implemented by Shashi).

### Changed
- Protected all new catalogue and category mutations with capability checks, nonces, sanitized IDs, and prepared database operations (implemented by Shashi).
- Prevented permanent deletion of categories still assigned to catalogue items while allowing those categories to be archived (implemented by Shashi).

### Areas Changed
- Catalogue and category action handlers and management interfaces (`includes/class-quotations.php`)
- Bulk selection and confirmation behavior (`assets/js/quotations.js`)
- Bulk action and category-row presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.67.0] - 2026-08-27
### Added
- Added a dedicated Item Categories manager using the shared wide-popup layout (implemented by Shashi).
- Added secure, capability- and nonce-protected item-category creation with duplicate prevention (implemented by Shashi).
- Added an item-category registry and automatically migrated existing catalogue category names into it (implemented by Shashi).

### Changed
- Replaced free-text category entry in catalogue items with a required dropdown of registered categories (implemented by Shashi).
- Made CSV imports register valid category names automatically before creating catalogue items (implemented by Shashi).
- Sourced quotation discount defaults from the registered category list (implemented by Shashi).

### Areas Changed
- Category schema, migration, validation, management, catalogue editor, imports, and settings (`includes/class-quotations.php`)
- Category-manager interaction (`assets/js/quotations.js`)
- Category-manager modal and responsive presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.66.4] - 2026-08-27
### Added
- Added an Enable per-item tax switch to Quotation Setup (implemented by Shashi).

### Changed
- Defined tax precedence as global tax first, catalogue/item tax second, and tax-free when both features are disabled (implemented by Shashi).
- Applied the selected tax mode consistently in live calculations and server-validated quotation saves (implemented by Shashi).

### Areas Changed
- Tax settings, defaults, and server-side item parsing (`includes/class-quotations.php`)
- Live per-item/global tax selection (`assets/js/quotations.js`)
- Plugin, readme, and release metadata

## [1.66.3] - 2026-08-27
### Changed
- Standardized the catalogue editor popup to the shared 920px-wide and 760px/82vh modal dimensions (implemented by Shashi).
- Reorganized catalogue fields into a balanced two-column form with full-width item name and description fields, a fixed header, and independently scrollable content (implemented by Shashi).
- Added a full-height, single-column mobile layout with a prominent full-width save action (implemented by Shashi).
- Documented the shared popup sizing and structure standard for future modal work (implemented by Shashi).

### Areas Changed
- Catalogue editor modal and responsive presentation (`assets/css/quotations.css`)
- Frontend modal design standards (`design.md`)
- Plugin, readme, and release metadata

## [1.66.2] - 2026-08-27
### Fixed
- Stopped repeating the complete quote number inside the revision identifier (implemented by Shashi).
- Standardized builder, detail, and PDF displays to show the quote number once followed by the short revision label, such as `Q-2026-000125 · R01` (implemented by Shashi).

### Areas Changed
- Quotation and revision-number presentation (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.66.1] - 2026-08-27
### Removed
- Removed the Additional quote settings disclosure from the quotation builder while retaining required values internally for revision compatibility (implemented by Shashi).
- Removed the quotation title, validity line, and legacy customer-introduction content from PDF preview and print output (implemented by Shashi).

### Changed
- Preserved the required edit remark as a dedicated quotation-edit control (implemented by Shashi).

### Areas Changed
- Quotation builder presentation (`assets/css/quotations.css`)
- PDF preview and print cleanup (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.66.0] - 2026-08-27
### Added
- Added category-wise discount defaults to Quotation Setup, populated automatically from active catalogue-item categories (implemented by Shashi).
- Added optional global tax type and rate configuration for CGST + SGST, IGST, or no tax (implemented by Shashi).

### Changed
- New quotations inherit configured category discounts while retaining editable per-quotation controls (implemented by Shashi).
- When global taxes are enabled, catalogue and custom line items use the configured tax type and rate in both client and server calculations (implemented by Shashi).

### Areas Changed
- Quotation settings, item parsing, setup interface, and editor defaults (`includes/class-quotations.php`)
- Global tax application in live quotation calculations (`assets/js/quotations.js`)
- Setup discount and tax presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.65.1] - 2026-08-27
### Fixed
- Prevented the general quotation click handler from rebuilding category-discount controls when their dropdowns or values are clicked (implemented by Shashi).
- Kept category totals and quotation totals recalculating after the discount selection or value changes (implemented by Shashi).

### Areas Changed
- Category-discount interaction behavior (`assets/js/quotations.js`)
- Plugin, readme, and release metadata

## [1.65.0] - 2026-08-27
### Added
- Added fixed-value and percentage discount controls to every category in the live Category subtotals panel (implemented by Shashi).
- Added immutable category-discount configuration to quotation revisions (implemented by Shashi).

### Changed
- Applied category discounts before the overall discount and proportionally recalculated GST/IGST on both the client and server (implemented by Shashi).
- Included category discounts in total-discount figures and adjusted category totals in quotation details and PDFs (implemented by Shashi).

### Areas Changed
- Quotation schema, calculations, revision persistence, builder, details, and PDF totals (`includes/class-quotations.php`)
- Live category discount controls and calculations (`assets/js/quotations.js`)
- Category discount presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.64.1] - 2026-08-27
### Changed
- Placed Section-wise summary and Category subtotals inline in a balanced two-column layout, with a single-column mobile fallback (implemented by Shashi).

### Areas Changed
- Quotation summary layout (`includes/class-quotations.php`, `assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.64.0] - 2026-08-27
### Added
- Added a live Section-wise summary matching the existing Category subtotals presentation (implemented by Shashi).

### Removed
- Removed section-description inputs and section-description output from quotations (implemented by Shashi).

### Changed
- Changed each editor footer label from the generic Section subtotal to `[section name] Subtotal` and kept it synchronized with section-name edits (implemented by Shashi).
- Increased section subtotal label and value typography for improved visibility (implemented by Shashi).

### Areas Changed
- Quotation section parsing, builder, and read-only presentation (`includes/class-quotations.php`)
- Live section summaries and subtotal labels (`assets/js/quotations.js`)
- Section header, summary, subtotal, and responsive styling (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.63.1] - 2026-08-27
### Removed
- Removed payment milestone entry and validation from the quotation builder (implemented by Shashi).
- Removed payment milestones from newly saved or copied revisions, quotation details, and PDF output (implemented by Shashi).

### Areas Changed
- Quotation persistence, builder, detail, and PDF presentation (`includes/class-quotations.php`)
- Quotation builder behavior (`assets/js/quotations.js`)
- Plugin, readme, and release metadata

## [1.63.0] - 2026-08-27
### Removed
- Removed the Commercial terms panel from the quotation builder and the commercial-term defaults from Quotation Setup (implemented by Shashi).

### Added
- Added independently enabled HTML builders for a custom PDF cover page and content after the quotation (implemented by Shashi).
- Added automatic print page breaks around enabled custom cover and after-quote content (implemented by Shashi).

### Changed
- Preserved historical commercial-term data when older quotations are edited, despite removing those editing controls (implemented by Shashi).

### Areas Changed
- Quotation setup, revision persistence, builder layout, and PDF rendering (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.62.1] - 2026-08-27
### Changed
- Enabled Preview / Save PDF and printing for quotation revisions while the workflow feature is disabled (implemented by Shashi).
- Preserved approved-only PDF enforcement automatically if the workflow feature is re-enabled later (implemented by Shashi).

### Areas Changed
- Quotation PDF authorization (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.62.0] - 2026-08-27
### Changed
- Disabled quotation workflow transitions and manual Create New Revision actions behind a future-ready feature flag (implemented by Shashi).
- Changed every edit save to create a new sequential Draft revision while preserving and superseding the previous revision (implemented by Shashi).
- Required a visible edit remark explaining what changed and why before an edited quotation can be saved (implemented by Shashi).
- Updated the edit save action and success notice to clearly communicate new Draft revision creation (implemented by Shashi).

### Areas Changed
- Quotation workflow registration, revision persistence, history, and editing permissions (`includes/class-quotations.php`)
- Edit-remark presentation and save messaging (`assets/js/quotations.js`, `assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.61.5] - 2026-08-27
### Fixed
- Replaced unreliable native select-option filtering with a cross-browser lead search-results dropdown (implemented by Shashi).
- Restored reliable lead selection and customer, property, studio, and sales-representative prefilling from search results (implemented by Shashi).

### Areas Changed
- Lead search and selection behavior (`assets/js/quotations.js`)
- Lead search-results presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.61.4] - 2026-08-27
### Removed
- Removed the Customer introduction input from the quotation editor (implemented by Shashi).

### Changed
- Preserved existing stored customer-introduction content when older quotations are subsequently edited (implemented by Shashi).

### Areas Changed
- Quotation revision persistence (`includes/class-quotations.php`)
- Quotation editor field removal (`assets/js/quotations.js`)
- Plugin, readme, and release metadata

## [1.61.3] - 2026-08-27
### Added
- Added Created by directly beneath Sales rep name in the quotation metadata column, using the saved quote creator or current user for new quotations (implemented by Shashi).

### Areas Changed
- Quotation creator resolution and editor metadata (`includes/class-quotations.php`)
- Creator-row placement (`assets/js/quotations.js`)
- Plugin, readme, and release metadata

## [1.61.2] - 2026-08-27
### Changed
- Hid Preview / Save PDF until a quotation revision has been approved (implemented by Shashi).
- Added server-side enforcement preventing direct PDF preview or download for Draft and Pending Approval revisions (implemented by Shashi).

### Areas Changed
- Quotation detail actions and print authorization (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.61.1] - 2026-08-27
### Changed
- Expanded the Item Catalogue panel to the full available workspace width after moving its editor and CSV import into modals (implemented by Shashi).

### Areas Changed
- Catalogue workspace layout (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.61.0] - 2026-08-27
### Changed
- Converted catalogue CSV import from the sidebar into a centered modal with backdrop, close control, Escape dismissal, and page-scroll locking (implemented by Shashi).

### Added
- Added an Import CSV button beside Export CSV and Add New Item in the catalogue header (implemented by Shashi).

### Areas Changed
- Catalogue header actions (`includes/class-quotations.php`)
- CSV import modal behavior (`assets/js/quotations.js`)
- CSV import modal presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.60.0] - 2026-08-27
### Changed
- Converted the catalogue item add/edit panel from a sidebar into a centered modal with backdrop, close control, Escape dismissal, and page-scroll locking (implemented by Shashi).

### Added
- Added an Add New Item button beside Export CSV in the Item Catalogue header (implemented by Shashi).

### Areas Changed
- Catalogue route state and header actions (`includes/class-quotations.php`)
- Catalogue modal behavior (`assets/js/quotations.js`)
- Catalogue modal presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.59.5] - 2026-08-27
### Changed
- Added the ₹ symbol to rate inputs and monetary column headings (implemented by Shashi).
- Added the ₹ symbol to line totals, section/category subtotals, discounts, taxes, and grand totals in the live quotation editor (implemented by Shashi).

### Areas Changed
- Quotation rate input markup (`includes/class-quotations.php`)
- Live monetary formatting (`assets/js/quotations.js`)
- Currency marker presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.59.4] - 2026-08-27
### Changed
- Removed browser increment/decrement spinner controls from quotation Rate inputs (implemented by Shashi).

### Areas Changed
- Quotation rate input markup (`includes/class-quotations.php`)
- Cross-browser spinner suppression (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.59.3] - 2026-08-27
### Changed
- Rebalanced all quotation editor columns according to their content requirements (implemented by Shashi).
- Reduced the line-action column, icon size, and toolbar gaps while preserving a single inline row (implemented by Shashi).

### Areas Changed
- Quotation table proportions and action-toolbar sizing (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.59.2] - 2026-08-27
### Changed
- Removed browser increment/decrement spinners from Length, Height, and Area/Qty inputs (implemented by Shashi).
- Standardized dimensional entry, automatic area calculation, and saved values to a maximum of two decimal places (implemented by Shashi).

### Areas Changed
- Quotation measurement parsing and input markup (`includes/class-quotations.php`)
- Automatic Area/Qty precision (`assets/js/quotations.js`)
- Cross-browser number-spinner suppression (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.59.1] - 2026-08-27
### Changed
- Converted Duplicate and Remove line-item controls into accessible icon-only buttons and kept all row actions inline (implemented by Shashi).

### Areas Changed
- Quotation line-action markup (`includes/class-quotations.php`)
- Inline icon-action presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.59.0] - 2026-08-27
### Changed
- Split Length, Height, Area/Qty, and Unit into dedicated quotation columns across the editor, detail view, and print output (implemented by Shashi).
- Rebalanced quotation-table column widths and mobile overflow for the expanded eight-column editor (implemented by Shashi).

### Areas Changed
- Quotation line-item and read-only table markup (`includes/class-quotations.php`)
- Responsive quotation-table sizing (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.58.0] - 2026-08-27
### Changed
- Replaced the oversized quotation-details section with a compact two-column customer and quotation identity panel (implemented by Shashi).
- Added client-side lead search and automatic customer, property, studio, and sales-representative prefilling (implemented by Shashi).
- Moved title, currency, validity, revision reason, and customer introduction into a collapsed Additional quote settings control (implemented by Shashi).

### Areas Changed
- Quotation editor data retrieval and identity-panel markup (`includes/class-quotations.php`)
- Lead filtering and lead-to-quotation prefilling (`assets/js/quotations.js`)
- Compact responsive identity-panel presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.57.0] - 2026-08-27
### Added
- Added Length and Height fields to quotation lines with automatic Area/Qty calculation when both dimensions are supplied (implemented by Shashi).
- Added competitor-inspired room schedules with serial number, dimensions, area/quantity, rate, costing, and room totals in quotation detail and print output (implemented by Shashi).

### Changed
- Refined print-table styling for compact A4 room-wise schedules while retaining the firm's own branding and editable commercial terms (implemented by Shashi).

### Areas Changed
- Quotation schema, calculations, editor, detail, and print presentation (`includes/class-quotations.php`)
- Live dimensional calculations (`assets/js/quotations.js`)
- Editor and A4 print styling (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.56.1] - 2026-08-26
### Fixed
- Restored the quotation edit screen on WordPress installations where the `readonly()` attribute helper is unavailable (implemented by Shashi).

### Areas Changed
- Quotation catalogue-item field rendering (`includes/class-quotations.php`)
- Plugin, readme, and release metadata

## [1.56.0] - 2026-08-26
### Changed
- Placed quantity and unit inline in each quotation row and made unit read-only (implemented by Shashi).
- Made description, item code, and category read-only after an item is populated from the catalogue while preserving editable fields for custom items (implemented by Shashi).
- Removed item-level discount inputs, client calculations, server calculations, stored values for new saves, and the read-only item discount column; retained the quotation-level overall discount (implemented by Shashi).

### Areas Changed
- Quotation item parsing, totals, editor markup, and read-only presentation (`includes/class-quotations.php`)
- Catalogue locking and discount-free live calculations (`assets/js/quotations.js`)
- Inline quantity/unit and locked-field presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.55.1] - 2026-08-26
### Changed
- Reduced the bulk catalogue picker's Selected state label to a smaller, medium-weight treatment (implemented by Shashi).

### Areas Changed
- Bulk-item selected-state typography (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.55.0] - 2026-08-26
### Added
- Added a Bulk Items control beside Add Line and Add Section that opens a responsive catalogue picker with searchable available items on the left and selected items on the right (implemented by Shashi).
- Added selection removal, selected-count feedback, backdrop and Escape dismissal, Cancel, and Add Selected footer actions (implemented by Shashi).
- Added multi-item insertion into the final quotation section using immutable catalogue snapshots and the existing live total calculations (implemented by Shashi).

### Areas Changed
- Bulk catalogue query and quotation-editor modal markup (`includes/class-quotations.php`)
- Bulk selection, search, modal, and line insertion behavior (`assets/js/quotations.js`)
- Bulk-item modal and responsive presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.54.1] - 2026-08-26
### Changed
- Moved the Add Line control from individual quotation section headers into `.idlm-add-section-row` beside Add Section, targeting the final section in the editor (implemented by Shashi).

### Areas Changed
- Quotation section and shared action-row markup (`includes/class-quotations.php`)
- Add-line section targeting (`assets/js/quotations.js`)
- Section header and action-row presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.54.0] - 2026-08-26
### Added
- Added an Edit action to every catalogue item and populated the catalogue editor with the selected item's code, category, name, description, unit, rate, and tax settings (implemented by Shashi).

### Changed
- Reused the nonce- and capability-protected catalogue save handler for updates while preserving archived item status and immutable values already copied into quotation revisions (implemented by Shashi).

### Areas Changed
- Catalogue retrieval, editing form, update validation, and action controls (`includes/class-quotations.php`)
- Catalogue editing presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.53.1] - 2026-08-26
### Changed
- Reduced the unified workspace sidebar width, navigation typography, icon sizing, and spacing while retaining its collapsed and mobile states (implemented by Shashi).
- Pinned My Account to the bottom of the desktop sidebar navigation (implemented by Shashi).
- Converted the lead filter trigger to an icon-only floating control stacked above Active Tasks, retaining its accessible label and active-filter badge (implemented by Shashi).

### Fixed
- Disabled root and nested workspace overscroll chaining to remove the bounce effect when scrolling beyond available content (implemented by Shashi).

### Areas Changed
- Unified workspace sizing, navigation, bottom account placement, and overscroll behavior (`assets/css/workspace.css`, `assets/js/workspace.js`)
- Floating filter control markup and presentation (`includes/class-admin.php`, `assets/css/admin-style.css`)
- Plugin, readme, and release metadata

## [1.53.0] - 2026-08-26
### Changed
- Collapsed the lead database filters into a button-triggered floating side panel with backdrop dismissal, Escape-key support, focus return, and active-filter count (implemented by Shashi).
- Presented the filter panel as a full-height desktop drawer and a responsive mobile sheet while retaining all existing filter and advanced-filter behavior (implemented by Shashi).

### Areas Changed
- Lead-list filter markup and accessibility (`includes/class-admin.php`)
- Filter-panel presentation and responsive behavior (`assets/css/admin-style.css`)
- Filter-panel interactions and active state (`assets/js/admin-app.js`)
- Plugin, readme, and release metadata

## [1.52.1] - 2026-08-26
### Fixed
- Extended the desktop unified-workspace sidebar to the complete viewport height using dynamic viewport units with a compatible fallback (implemented by Shashi).
- Preserved automatic height and horizontal navigation behavior on mobile screens (implemented by Shashi).

### Areas Changed
- Workspace height and responsive presentation (`assets/css/workspace.css`)
- Frontend design system (`design.md`)
- Plugin, readme, and release metadata

## [1.52.0] - 2026-08-25
### Added
- Added My Account as the final unified-workspace navigation item with the current user's avatar, roles, name, email, phone, direct password update, and password-reset email option (implemented by Shashi).
- Added nonce-protected, same-user profile handling with email uniqueness checks, sanitization, safe redirects, and session preservation after direct password changes (implemented by Shashi).

### Changed
- Expanded quotation line items to the complete content width and moved payment milestones below the editable line-item area (implemented by Shashi).
- Expanded read-only quotation line items to full width and placed payment milestones and revision previews beneath them beside commercial terms (implemented by Shashi).
- Updated the frontend design reference for My Account and the full-width quotation hierarchy (implemented by Shashi).

### Areas Changed
- Unified navigation, account UI, and account update handler (`includes/class-admin.php`)
- Account and navigation presentation (`assets/css/workspace.css`)
- Quotation editor and detail layout (`includes/class-quotations.php`)
- Frontend design system (`design.md`)
- Plugin, readme, and release metadata

## [1.51.0] - 2026-08-25
### Changed
- Redesigned the unified frontend shell with refined brand treatment, navigation states, focus visibility, mobile top navigation, and reduced-motion support (implemented by Shashi).
- Rebuilt the quotation presentation around the shared CRM design tokens, including page navigation, filters, cards, tables, editor sections, catalogue results, totals, workflow actions, sticky save controls, and responsive layouts (implemented by Shashi).
- Replaced the quotation module's separate blue action styling with the established gold primary-action system (implemented by Shashi).

### Areas Changed
- Unified frontend workspace presentation (`assets/css/workspace.css`)
- Quotation frontend and admin presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.50.1] - 2026-08-25
### Added
- Added a comprehensive frontend design system reference derived from the implemented Leads List, Kanban, Dashboard, Settings, Quotations, and unified workspace (implemented by Shashi).
- Documented design tokens, typography, spacing, page anatomy, reusable components, interaction conventions, responsive rules, accessibility requirements, file ownership, and a frontend change checklist (implemented by Shashi).

### Areas Changed
- Frontend design documentation (`design.md`)
- Plugin, readme, and release metadata

## [1.50.0] - 2026-08-25
### Changed
- Simplified editable quotation lines to five columns: Items, Qty/Unit, Rate, Total, and Buttons (implemented by Shashi).
- Combined inline catalogue search, item description, item code, and category in the Items column (implemented by Shashi).
- Moved Add Section below the line-item sections for easier access (implemented by Shashi).

### Removed
- Removed the quotation CSV import control and its client-side parser (implemented by Shashi).

### Areas Changed
- Quotation editor markup and preserved commercial values (`includes/class-quotations.php`)
- Catalogue selection, optional-line calculation, and CSV removal (`assets/js/quotations.js`)
- Five-column line-item layout and responsive presentation (`assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.49.1] - 2026-08-25
### Fixed
- Kept lead-drawer Open, Create, and View Quote actions inside `[idlm_workspace]` instead of sending frontend users to WordPress Admin (implemented by Shashi).
- Preserved the existing backend quotation destination when the lead drawer is opened from WordPress Admin (implemented by Shashi).

### Areas Changed
- Frontend context forwarding and quote routing (`assets/js/admin-app.js`, `includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.49.0] - 2026-08-25
### Added
- Added the `[idlm_workspace]` single-page frontend CRM shell with a responsive collapsible sidebar and remembered desktop state (implemented by Shashi).
- Added capability-aware navigation for dashboard, leads, Kanban pipeline, quotations, CAPI audit logs, audience sync logs, and settings (implemented by Shashi).

### Changed
- Kept the existing module shortcodes compatible while making the unified workspace the recommended frontend entry point (implemented by Shashi).
- Limited settings and audit navigation to administrators while preserving representative and quotation permissions (implemented by Shashi).

### Areas Changed
- Unified shortcode, permissions, navigation, asset loading, and settings help (`includes/class-admin.php`)
- Responsive workspace shell and collapse behavior (`assets/css/workspace.css`, `assets/js/workspace.js`)
- Plugin, readme, and release metadata

## [1.48.0] - 2026-08-25
### Added
- Added revision-level sections with ordered lines, section subtotals, and category subtotal summaries (implemented by Shashi).
- Added searchable catalogue items, catalogue CSV import/export, item archiving, and quotation-builder autocomplete (implemented by Shashi).
- Added configurable quote prefixes, separators, and sequence padding (implemented by Shashi).
- Added immutable customer and project snapshots to every quotation revision (implemented by Shashi).
- Added quotation search by quote/customer/phone/project, creator, status, and date (implemented by Shashi).

### Changed
- Consolidated quotation lists, catalogue management, commercial terms, numbering, and PDF setup into one capability-aware workspace shared by WordPress Admin and `[idlm_quotations]` (implemented by Shashi).
- Grouped revision previews and printable documents by section and added category summaries without reading current catalogue prices (implemented by Shashi).

### Migration
- Added quotation schema 1.1.0 with catalogue, revision-section, catalogue reference, item-name, and customer snapshot fields plus an idempotent historical backfill (implemented by Shashi).

### Areas Changed
- Quotation schema, catalogue, snapshots, unified workspace, builder, revisions, search, and PDF (`includes/class-quotations.php`)
- Quotation builder behavior and subtotal calculations (`assets/js/quotations.js`)
- Unified workspace, section, catalogue, and responsive presentation (`assets/css/quotations.css`)
- Role capabilities, plugin, readme, and release metadata

## [1.47.0] - 2026-08-25
### Added
- Added the role-gated `[idlm_quotations]` frontend workspace (implemented by Shashi).
- Added Create/View Quote actions to each lead drawer footer (implemented by Shashi).
- Added CSV bulk line-item import while retaining manual item entry (implemented by Shashi).
- Added quotation setup for reusable commercial terms and branded PDF formatting (implemented by Shashi).

### Changed
- Changed revision history entries into full selectable quotation previews with revision-specific PDF output (implemented by Shashi).

### Areas Changed
- Quotation routing, defaults, settings, revision previews, and PDF presentation (`includes/class-quotations.php`)
- Lead drawer quotation entry point (`includes/class-admin.php`)
- CSV import and quotation presentation (`assets/js/quotations.js`, `assets/css/quotations.css`)
- Plugin, readme, and release metadata

## [1.46.0] - 2026-08-25
### Added
- Added a separate lead-linked quotation module supporting multiple quotations per lead, atomic `Q-YYYY-NNNNNN` numbering, and named `RNN` revisions (implemented by Shashi).
- Added immutable revision cloning with author, timestamp, source revision, and mandatory revision reason audit fields (implemented by Shashi).
- Added itemized commercial proposals with categories, rooms, specifications, quantity, units, optional items, discounts, CGST/SGST or IGST, other charges, round-off, and server-calculated totals (implemented by Shashi).
- Added payment milestones that reconcile to the grand total, commercial terms, customer introduction, and internal notes (implemented by Shashi).
- Added role-controlled Draft, Pending Approval, Approved, Shared, Under Negotiation, Accepted, Rejected, Expired, Cancelled, and Superseded transitions (implemented by Shashi).
- Added quotation list, editor, detail workspace, revision history, lead-level quotation access, and print/save-PDF document view (implemented by Shashi).

### Changed
- Made structured quotations the source of truth for lead quote summaries while retaining the existing `quote_shared`, `quote_value`, `deal_value`, and pipeline dashboard compatibility fields (implemented by Shashi).
- Synchronized Shared and Under Negotiation actions with lead stages and Accepted values with the lead deal value (implemented by Shashi).
- Added quotation cleanup to lead deletion and complete CRM reset workflows (implemented by Shashi).

### Migration
- Added an idempotent migration that converts existing lead quote values and files into legacy R01 quotation records without losing historical commercial data (implemented by Shashi).

### Areas Changed
- Quotation schema, calculations, permissions, lifecycle, revisions, migration, and UI (`includes/class-quotations.php`)
- Quotation editor and responsive module presentation (`assets/js/quotations.js`, `assets/css/quotations.css`)
- Lead drawer linking, commercial-field compatibility, deletion, and reset handling (`includes/class-admin.php`)
- Plugin bootstrap, roles, readme, and release metadata

## [1.45.0] - 2026-08-17
### Added
- Added append-only lead attribution touchpoints containing channel, external IDs, click IDs, UTMs, landing page, payload, and timestamps (implemented by Shashi).
- Added a one-time backfill that preserves existing linked duplicate submissions in the canonical lead's attribution history (implemented by Shashi).

### Changed
- Changed inbound webhook ingestion to return and enrich one canonical lead matched by Meta lead ID, normalized phone, or normalized email (implemented by Shashi).
- Preserved delayed Meta acquisition as the canonical primary/original source while retaining estimator submissions as separate website touchpoints (implemented by Shashi).
- Routed only newly created canonical leads and excluded legacy duplicate rows from representative lead lists (implemented by Shashi).

### Fixed
- Included attribution touchpoints and tasks in full CRM resets and attribution touchpoints in permanent lead cleanup (implemented by Shashi).
- Removed an invalid undefined-lead check that could interrupt full CRM resets (implemented by Shashi).

### Areas Changed
- Canonical matching, attribution schema, touchpoint history, and migration (`includes/class-db.php`)
- Webhook ingestion and routing (`includes/class-integrations.php`)
- Representative lead visibility (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.44.0] - 2026-08-17
### Added
- Added a durable `assigned_at` timestamp for current lead ownership and populated it on initial routing, manual assignment, reassignment, and direct lead creation (implemented by Shashi).
- Added a one-time historical backfill that prefers the latest explicit assignment activity, then sales handoff, updated, and created timestamps while leaving unassigned leads without an assignment date (implemented by Shashi).
- Added explicit Assigned, Created, Sales Handoff, Last Updated, Qualified, and Won date bases to lead-list, Kanban, and filtered-export date ranges (implemented by Shashi).

### Fixed
- Made Assigned Date the operational default for date-filtered rep workloads and sorted current queues by assignment timing so newly assigned older leads surface correctly (implemented by Shashi).
- Changed Qualified Sales Assignments by Representative to use the actual handoff/current-assignment event period instead of lead creation cohorts (implemented by Shashi).
- Changed Yesterday Delivery metrics to count handoffs that occurred yesterday rather than leads qualified yesterday that happened to have a handoff (implemented by Shashi).
- Exported assignment, sales-handoff, and update timestamps for reconciliation (implemented by Shashi).

### Areas Changed
- Lead schema, assignment mutation paths, and historical migration (`includes/class-db.php`, `includes/class-integrations.php`)
- Lead-list, Kanban, export filters, queue ordering, and reconciliation metrics (`includes/class-admin.php`, `assets/js/admin-app.js`, `assets/js/kanban.js`)
- Sales assignment reporting (`includes/class-dashboard.php`)
- Plugin, readme, and release metadata

## [1.43.7] - 2026-08-16
### Fixed
- Recalculated every lead-list metric-grid value from the same permissions, date, source, representative, stage, call-status, quality, follow-up, search, and active-only filters used by the AJAX table (implemented by Shashi).
- Updated qualified-handoff overdue state dynamically with the filtered grid while preserving configured working-hour calculations (implemented by Shashi).
- Preserved existing filters when a metric card is selected so its drill-down result remains equal to the displayed filtered count (implemented by Shashi).

### Areas Changed
- Filter-aware lead-list metric aggregation (`includes/class-admin.php`)
- AJAX metric-grid rendering (`assets/js/admin-app.js`)
- Plugin, readme, and release metadata

## [1.43.6] - 2026-08-16
### Fixed
- Changed Kanban Assigned and Qualified cards from active-pipeline counts to complete filtered-board counts, including visible Won and Lost records, so the cards reconcile with representative totals and column cards (implemented by Shashi).
- Renamed both cards to explicitly identify them as filtered totals while retaining active-only scope for Follow-ups Due and Open Pipeline Value (implemented by Shashi).

### Areas Changed
- Kanban filtered summary aggregation and labels (`includes/class-admin.php`, `assets/js/kanban.js`)
- Plugin, readme, and release metadata

## [1.43.5] - 2026-08-16
### Fixed
- Excluded Won and Lost records from actionable lead-list cards for Not Called, Follow-up, Unassigned, Qualified Active, and Pending Quality, and made each card click apply the identical active scope (implemented by Shashi).
- Prevented the non-clickable Yesterday Delivery reconciliation card from accidentally clearing and reloading lead-list filters (implemented by Shashi).
- Changed Kanban Won and Won Revenue cards from a mixed calendar-month event calculation to the current Won population represented by all active board filters (implemented by Shashi).
- Calculated overdue qualified handoffs with configured working hours and working days instead of elapsed calendar hours (implemented by Shashi).

### Areas Changed
- Lead-list metrics and server-side filters (`includes/class-admin.php`, `assets/js/admin-app.js`)
- Kanban filtered summary metrics (`includes/class-admin.php`, `assets/js/kanban.js`)
- Plugin, readme, and release metadata

## [1.43.4] - 2026-08-16
### Fixed
- Made Qualified Sales Assignments count every current qualified sales-owned lead in the selected acquisition cohort, while separating OOO fallback from manager or direct ownership (implemented by Shashi).
- Normalized newly qualified sales-owned leads as completed handoffs and unassigned qualified leads as awaiting manager action across creation and update paths (implemented by Shashi).
- Corrected the cohort conversion-rate denominator/numerator pairing and matched year-over-year comparison windows to the same time of day (implemented by Shashi).
- Clarified acquisition-cohort, current-state, Won-event, and calendar-month metric scopes throughout the dashboard (implemented by Shashi).

### Areas Changed
- Dashboard aggregation and date comparisons (`includes/class-dashboard.php`)
- Qualification and handoff state normalization (`includes/class-db.php`)
- Dashboard labels and chart rendering (`includes/class-admin.php`, `assets/js/dashboard.js`)
- Plugin, readme, and release metadata

## [1.43.3] - 2026-08-16
### Fixed
- Aligned the Qualified Sales Assignments by Representative chart with the Kanban Qualified view by using the same lead-created period and current representative ownership instead of handoff-event dates (implemented by Shashi).
- Clarified the chart title, description, and empty state so its population and date basis are explicit (implemented by Shashi).

### Areas Changed
- Sales assignment aggregation (`includes/class-dashboard.php`)
- Sales dashboard chart copy (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.43.2] - 2026-08-16
### Fixed
- Added a one-time qualified-handoff reconciliation that preserves existing sales owners, records their historical ownership as a completed manager handoff, and keeps pre-sales-owned or unassigned records awaiting manager action (implemented by Shashi).
- Excluded the uninitialized `not_ready` schema default from pending-handoff metrics and filters (implemented by Shashi).
- Captured the legacy reconciliation update result so follow-up task creation runs after a successful repair (implemented by Shashi).

### Areas Changed
- Qualified handoff migration and fallback reconciliation (`includes/class-integrations.php`)
- Manager handoff metrics and lead filters (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.43.1] - 2026-08-16
### Added
- Added a manager/admin Sales Assignments by Representative horizontal stacked bar chart that follows the selected dashboard period and counts by `handoff_at`, not lead creation date (implemented by Shashi).
- Split assignment bars into manager-completed and OOO fallback handoffs, with exact totals in the chart tooltip (implemented by Shashi).

### Areas Changed
- Assignment-period aggregation (`includes/class-dashboard.php`)
- Sales dashboard chart markup and rendering (`includes/class-admin.php`, `assets/js/dashboard.js`)
- Plugin, readme, and release metadata

## [1.43.0] - 2026-08-16
### Fixed
- Preserved the required pre-sales → manager → sales workflow while making the OOO fallback reconcile all unresolved qualified backlog states after the configured working-hour threshold (implemented by Shashi).
- Recalculated live representative capacity before every OOO assignment so a large backlog cannot overfill a rep from a stale batch-level pool (implemented by Shashi).
- Repaired legacy qualified records already owned by a configured sales rep as completed manager handoffs without changing their owner (implemented by Shashi).
- Created one idempotent high-priority sales follow-up task after every manual, automatic, or reconciled qualified handoff (implemented by Shashi).
- Added read-only pre-sales qualified history and kept mutation permission tied to current ownership (implemented by Shashi).
- Restricted lead-task reads and mutations to authorized lead visibility/ownership and limited task status values to pending or completed (implemented by Shashi).
- Hardened MCP custom-query table enforcement against case-sensitive prefix, schema-qualified table, comment, and multi-statement bypasses (implemented by Shashi).

### Added
- Added manager reconciliation cards for yesterday's qualified-versus-delivered totals and current routing exceptions (implemented by Shashi).
- Added active assigned, qualified assigned, and received-yesterday fields to MCP representative performance results (implemented by Shashi).

### Areas Changed
- Qualified backlog reconciliation, OOO routing, capacity enforcement, and handoff tasks (`includes/class-integrations.php`, `includes/class-db.php`)
- Manager metrics, pre-sales history, task authorization, and filters (`includes/class-admin.php`, `assets/js/admin-app.js`)
- MCP reporting and read-only SQL enforcement (`includes/class-mcp-core.php`)
- Plugin, readme, and release metadata

## [1.42.1] - 2026-08-13
### Fixed
- Unified list, drawer, task-summary, and backend compliance calculations through one shared carried-progress implementation so configured targets produce identical results everywhere (implemented by Shashi).
- Limited compliance to one completed outreach cycle per activity date while carrying incomplete progress into later dates, preserving the intended daily cadence (implemented by Shashi).
- Prevented optional WhatsApp activity from displaying impossible progress such as `1/0` when the WhatsApp requirement is configured as zero (implemented by Shashi).
- Clamped negative administrative values correctly, ordered batched activity dates deterministically, and returned a complete safe response shape for missing leads (implemented by Shashi).
- Avoided expensive task-title and status reconciliation when unrelated settings are saved; reconciliation now runs only when an outreach target changes (implemented by Shashi).

### Areas Changed
- Configurable pre-sales progress calculation and task reconciliation (`includes/class-db.php`)
- Administrative setting saves and pre-sales queue projection (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.42.0] - 2026-08-13
### Added
- Added administrator-configurable call and WhatsApp targets for each pre-sales follow-up cycle, with safe defaults of two calls and one WhatsApp message (implemented by Shashi).

### Changed
- Carried incomplete outreach progress across working days so a partially completed cycle no longer resets or appears as a new Day 1 on the following day (implemented by Shashi).
- Updated pre-sales queue guidance, drawer progress cards, qualification task creation, and automatic task completion to use cumulative cycle progress and the configured targets (implemented by Shashi).

### Areas Changed
- Pre-sales compliance, task creation, synchronization, and scheduling (`includes/class-db.php`)
- Routing and general settings, pre-sales queue, and lead drawer (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.41.0] - 2026-08-12
### Fixed
- Centralized workflow validation so quick edits, full-form saves, imports, bulk actions, and API-driven updates enforce the same Lead Quality, stage, Won, Quote Shared, Lost, and callback invariants (implemented by Shashi).
- Synchronized Qualified, Visited Studio, Quote Shared, Negotiation, and Won stages with `Qualified` Lead Quality while moving Unqualified decisions into the Lost pipeline state (implemented by Shashi).
- Required manually closed duplicates to resolve to a canonical original lead and normalized automatically detected duplicates as Lost, Unqualified, and excluded (implemented by Shashi).
- Cleared stale qualification or disqualification metadata during reverse transitions and completed pending Qualification tasks for every Unqualified transition (implemented by Shashi).
- Stopped populating `conversion_time` during lead creation and unrelated stage changes; new Won transitions now own that timestamp (implemented by Shashi).
- Reported bulk-action validation failures instead of returning unconditional success, skipped routing/CAPI work for imported duplicates, and removed dependent tasks when deleting leads (implemented by Shashi).
- Prevented deletion of canonical leads while linked duplicate records still exist (implemented by Shashi).

### Migration
- Added a one-time integrity repair for legacy duplicate states, Unqualified records left in active stages, Qualified records left in pre-qualification stages, advanced stages without Qualified quality, missing audit metadata, invalid Won/Quote states, and orphaned tasks (implemented by Shashi).
- Downgraded legacy Won or Quote Shared records missing required commercial values to Negotiation or Qualified rather than inventing revenue or quote values (implemented by Shashi).

### Areas Changed
- Workflow normalization, validation, duplicate insertion, conversion timing, task completion, and data repair (`includes/class-db.php`)
- Early-close, bulk action, import routing, and deletion workflows (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.40.11] - 2026-08-12
### Fixed
- Changed the structured Duplicate early-close outcome to save Lead Quality as `Unqualified` instead of leaving it blank, while preserving duplicate exclusion from qualification reporting and future conversion events (implemented by Shashi).
- Added an explicit lead-quality activity entry so the duplicate disqualification remains auditable in the lead timeline (implemented by Shashi).
- Added a one-time data repair that marks previously early-closed duplicate leads as `Unqualified` when their quality is blank (implemented by Shashi).

### Areas Changed
- Structured pre-sales duplicate closure (`includes/class-admin.php`)
- Duplicate early-close data repair (`includes/class-db.php`)
- Plugin, readme, and release metadata

## [1.40.10] - 2026-08-12
### Fixed
- Removed buffered whitespace and incidental output before Google Ads Data Manager HTTPS CSV responses so the schema header is always the first row (implemented by Shashi).
- Formatted offline-conversion timestamps with explicit numeric UTC offsets and excluded rows whose timestamps cannot be validated (implemented by Shashi).
- Applied the clean-response behavior consistently to offline conversion and Customer Match audience feeds (implemented by Shashi).

### Areas Changed
- Google Ads offline conversion CSV generation (`includes/class-capi.php`)
- Google Ads Customer Match CSV generation (`includes/class-audiences.php`)
- HTTPS pull-feed response handling (`includes/class-integrations.php`)
- Plugin, readme, and release metadata

## [1.40.9] - 2026-08-10
### Fixed
- Replaced the dynamically assembled unread WhatsApp activity query with a single fully placeholder-prepared query (implemented by Shashi).
- Resolved the reported `PluginCheck.Security.DirectDB.UnescapedDBParameter` and `WordPress.DB.PreparedSQL.NotPrepared` errors without changing representative visibility behavior (implemented by Shashi).

### Areas Changed
- Unread WhatsApp activity discovery (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.40.8] - 2026-08-10
### Fixed
- Removed nested button elements from sortable lead-table headers so global site-theme button rules no longer render each column as a styled pill (implemented by Shashi).
- Preserved click, Enter, and Space keyboard sorting directly on accessible table headers (implemented by Shashi).

### Areas Changed
- Sortable lead-table markup (`includes/class-admin.php`)
- Header sorting interactions (`assets/js/admin-app.js`)
- Sortable-header presentation (`assets/css/admin-style.css`)
- Plugin, readme, and release metadata

## [1.40.7] - 2026-08-10
### Added
- Added accessible ascending and descending sorting controls to the lead-table columns for both pre-sales and standard CRM views (implemented by Shashi).
- Applied sorting on the server across the complete filtered result set so ordering remains correct between pagination pages (implemented by Shashi).

### Areas Changed
- Lead-table headers and secure server-side ordering allowlist (`includes/class-admin.php`)
- Lead-table sorting state and AJAX requests (`assets/js/admin-app.js`)
- Sort controls and direction indicators (`assets/css/admin-style.css`)
- Plugin, readme, and release metadata

## [1.40.6] - 2026-08-10
### Added
- Added a dedicated Created Date column to the pre-sales lead-list table while retaining the existing business-day Age column (implemented by Shashi).

### Areas Changed
- Pre-sales lead-list table header, empty state, and row rendering (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.40.5] - 2026-08-10
### Performance
- Consolidated unread WhatsApp lead discovery and message counting into one indexed, request-shared query with a short persistent object-cache window (implemented by Shashi).
- Added activity and task composite indexes for lead timelines, WhatsApp status, pending tasks, and overdue-task lookups (implemented by Shashi).
- Cancelled superseded lead-table and Kanban filter requests and stopped background lead refreshes while the browser tab is hidden (implemented by Shashi).

### Areas Changed
- Lead-list WhatsApp status queries and caching (`includes/class-admin.php`)
- Database schema and query indexes (`includes/class-db.php`)
- Lead-list and Kanban request coordination (`assets/js/admin-app.js`, `assets/js/kanban.js`)
- Plugin, readme, and release metadata

## [1.40.4] - 2026-08-10
### Changed
- Separated active pre-sales availability, unassigned-lead recovery, and qualified-lead handoff into clearly numbered controls so administrators can understand which setting affects new leads versus the existing backlog (implemented by Shashi).
- Added responsive status summaries showing the assignment strategy, available pre-sales count, open unassigned-lead count, and backlog-recovery state in both routing settings surfaces (implemented by Shashi).
- Clarified control labels and supporting descriptions, including that selecting an available pre-sales user does not itself recover older unassigned leads (implemented by Shashi).

### Areas Changed
- Lead routing settings and workspace modal (`includes/class-admin.php`)
- Responsive routing interface styles (`assets/css/admin-style.css`)
- Plugin, readme, and release metadata

## [1.40.3] - 2026-08-10
### Fixed
- Excluded dedicated Pre-Sales Representatives from Current Sales Ownership Cohort Performance even when historical or manually assigned leads still reference them as the current owner (implemented by Shashi).
- Preserved the underlying lead assignments and the separate Pre-Sales Intake Cohort Performance attribution instead of rewriting CRM history (implemented by Shashi).

### Areas Changed
- Sales ownership cohort role filtering (`includes/class-dashboard.php`)
- Plugin, readme, and release metadata

## [1.40.2] - 2026-08-10
### Fixed
- Clarified that the Won goal always measures the current calendar month while Total Won follows the selected dashboard reporting period (implemented by Shashi).
- Added the current month to the Won goal ratio and the active reporting-period label to the Total Won card so unlike date scopes are no longer presented as directly comparable (implemented by Shashi).

### Areas Changed
- Dashboard metric labels and period context (`includes/class-admin.php`, `assets/js/dashboard.js`)
- Monthly target response context (`includes/class-dashboard.php`)
- Plugin, readme, and release metadata

## [1.40.1] - 2026-08-09
### Fixed
- Applied representative visibility restrictions to Sales Representatives Performance and Pre-Sales Intake Cohort Performance (implemented by Shashi).
- Counted a sales lead as successfully connected only when `time_to_connected` is present instead of treating every non-default call outcome as contact (implemented by Shashi).
- Counted pre-sales outreach only when the persistent intake owner personally logged an outbound call or WhatsApp activity, preventing later sales status changes from inflating pre-sales contact performance (implemented by Shashi).
- Aligned Ever Qualified, Ever-Qualified Rate, and Average Calendar Time to Qualify around the same `qualified_at` population (implemented by Shashi).
- Required both a completed handoff status and `handoff_at` timestamp for Completed Handoffs (implemented by Shashi).
- Preserved valid zero-second speed values rather than displaying them as unavailable (implemented by Shashi).
- Limited average connected-to-Won time to the same currently Won cohort used by the table's Won count (implemented by Shashi).
- Normalized blank and whitespace-only studio values into Unassigned and escaped dynamic representative and studio labels before table rendering (implemented by Shashi).

### Changed
- Renamed every performance table and ambiguous column to state its acquisition cohort, current ownership/current state, successful-connection, working-time, or calendar-time semantics (implemented by Shashi).
- Added deterministic performance-row ordering by Won/qualified results and cohort size (implemented by Shashi).

### Areas Changed
- Performance metric queries and populations (`includes/class-dashboard.php`)
- Performance table definitions and column semantics (`includes/class-admin.php`)
- Safe performance table rendering (`assets/js/dashboard.js`)
- Plugin, readme, and release metadata

## [1.40.0] - 2026-08-09
### Fixed
- Corrected Last 7 Days and Last 30 Days to contain exactly 7 and 30 calendar dates, including today (implemented by Shashi).
- Made month-to-date growth compare against the equivalent elapsed portion of the previous month and show New or N/A when no percentage baseline exists (implemented by Shashi).
- Kept monthly lead and Won goals scoped to the current calendar month regardless of the selected dashboard period (implemented by Shashi).
- Counted selected-period Won conversions and revenue by `conversion_time` while retaining acquisition metrics by `created_at` (implemented by Shashi).
- Validated dashboard period values and custom date ranges server-side, including invalid formats and reversed ranges (implemented by Shashi).
- Applied active Kanban search, date, representative, source, call-status, and quality filters to the summary cards (implemented by Shashi).
- Scoped unread WhatsApp lead discovery to non-duplicate leads visible to the current user (implemented by Shashi).

### Changed
- Replaced the inferred cumulative sales funnel with a configured current-stage distribution that does not claim unavailable historical progression (implemented by Shashi).
- Clarified open-pipeline acquisition-cohort values versus Won revenue occurring during the selected period (implemented by Shashi).

### Removed
- Removed unused cost-per-lead calculations, spend-saving AJAX behavior, browser rendering, styles, schema creation, diagnostics, reset handling, and MCP table exposure without destructively dropping legacy data (implemented by Shashi).

### Areas Changed
- Dashboard calculations and period comparison logic (`includes/class-dashboard.php`)
- Dashboard and Kanban labels, filters, summaries, unread chat logic, validation, and legacy CPL removal (`includes/class-admin.php`)
- Dashboard presentation (`assets/js/dashboard.js`, `assets/css/dashboard.css`)
- Database and MCP table registration (`includes/class-db.php`, `includes/class-mcp-core.php`)
- Plugin, readme, and release metadata

## [1.39.1] - 2026-08-09
### Fixed
- Prevented manually identified duplicate contacts from being counted as Unqualified leads or producing false qualification signals (implemented by Shashi).
- Added a send-time CAPI duplicate guard so events queued before the duplicate decision are safely aborted (implemented by Shashi).

### Changed
- The Duplicate structured early-close outcome now sets the existing duplicate exclusion flag, leaves Lead Quality unset, completes pending qualification tasks, and writes a dedicated audit entry (implemented by Shashi).

### Areas Changed
- Structured pre-sales duplicate closure (`includes/class-admin.php`)
- Asynchronous conversion-event suppression (`includes/class-capi.php`)
- Plugin, readme, and release metadata

## [1.39.0] - 2026-08-09
### Added
- Added a Plan Collection Rate KPI showing qualified leads with plans as a percentage and count of all qualified leads (implemented by Shashi).

### Changed
- Enhanced the existing Overall Lead Quality Distribution doughnut into Qualification Readiness with separate Plan Received and Plan Pending qualified segments, plus Unqualified and Pending Qualification (implemented by Shashi).
- Preserved the combined Qualified value used by existing attribution, CAPI, and conversion reporting (implemented by Shashi).

### Areas Changed
- Dashboard qualification aggregation (`includes/class-dashboard.php`)
- Sales KPI and chart presentation (`includes/class-admin.php`, `assets/js/dashboard.js`)
- Plugin, readme, and release metadata

## [1.38.0] - 2026-08-09
### Added
- Added user-facing `Qualified · Plan Received`, `Qualified · Plan Pending`, `Unqualified`, and `Pending Qualification` statuses without changing the stored qualification model or aggregate reporting (implemented by Shashi).
- Added filters for the two qualified plan states in the lead list and Kanban board (implemented by Shashi).

### Changed
- Separated optional floor-plan availability from the six required project-detail checks in the pre-sales queue (implemented by Shashi).

### Areas Changed
- Qualification status presentation, filters, drawer, and queue detail progress (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.37.1] - 2026-08-09
### Changed
- Restricted the Pending Calls header pill to users in the dedicated pre-sales role and avoided calculating its count for other roles (implemented by Shashi).

### Areas Changed
- Role-aware lead-list header rendering (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.37.0] - 2026-08-09
### Changed
- Made floor-plan collection optional when qualifying a lead while preserving all other project-detail and minimum-budget requirements (implemented by Shashi).
- Required pre-sales representatives to add a handoff remark when qualifying without a floor plan and recorded that remark in the lead activity timeline (implemented by Shashi).

### Areas Changed
- Qualification transition validation and handoff activity logging (`includes/class-db.php`)
- Pre-sales qualification controls and secure AJAX handling (`includes/class-admin.php`, `assets/js/admin-app.js`)
- Plugin, readme, and release metadata

## [1.36.2] - 2026-08-09
### Fixed
- Included accepted outreach activity dates outside configured working days in the shared compliance calculation so the pre-sales queue, drawer, automatic task completion, and consolidated task progress remain consistent (implemented by Shashi).
- Continued to count at most one completed outreach cycle per calendar date (implemented by Shashi).

### Areas Changed
- Shared pre-sales compliance date normalization (`includes/class-db.php`)
- Plugin, readme, and release metadata

## [1.36.1] - 2026-08-09
### Fixed
- Replaced the pre-sales task aggregation ID list with typed placeholders and a prepared query (implemented by Shashi).
- Kept representative capacity query preparation at the database call site so static analysis can verify it correctly (implemented by Shashi).
- Documented and scoped the lead-list dynamic filter exception at the exact prepared database call after verifying every fragment is fixed or individually prepared (implemented by Shashi).

### Areas Changed
- Lead-list and qualification-summary database queries (`includes/class-admin.php`)
- Capacity-pool database query (`includes/class-integrations.php`)
- Plugin, readme, and release metadata

## [1.36.0] - 2026-08-09
### Added
- Added a manager-only Qualified Handoffs header alert and summary metric with one-click access to the existing awaiting-assignment queue (implemented by Shashi).
- Added waiting-time, urgency, and property-location context directly to awaiting-handoff lead rows while retaining the existing secure representative assignment control (implemented by Shashi).

### Changed
- Highlight handoffs that exceed the configured qualified-fallback threshold and automatically remove them from the alert count after assignment to sales (implemented by Shashi).

### Areas Changed
- Manager handoff counts, queue context, and assignment visibility (`includes/class-admin.php`)
- Handoff filtering behavior and alert presentation (`assets/js/admin-app.js`, `assets/css/admin-style.css`)
- Plugin, readme, and release metadata

## [1.35.0] - 2026-08-09
### Changed
- Consolidated the six internal pending Qualification tasks into one pre-sales My Active Tasks entry per lead showing completed cycles, today’s calls, today’s WhatsApp activity, and the next due date (implemented by Shashi).
- Kept manual and non-qualification tasks unchanged, retained individual completed-task history, and prevented representatives from manually completing or deleting the consolidated automated plan (implemented by Shashi).
- Updated the floating task badge to count the consolidated lead plan once instead of counting all six scheduled tasks (implemented by Shashi).

### Areas Changed
- Pre-sales task aggregation and progress data (`includes/class-admin.php`)
- Global task sidebar rendering and summary presentation (`assets/js/admin-app.js`, `assets/css/admin-style.css`)
- Plugin, readme, and release metadata

## [1.34.0] - 2026-08-09
### Changed
- Automatically promote a lead from New to Contacted after the first logged pre-sales call or WhatsApp without changing its Pending Lead Quality (implemented by Shashi).
- Preserve the current New or Contacted stage when pre-sales marks a lead Unqualified, keeping Lost exclusively for qualified opportunities handled by sales (implemented by Shashi).
- Retain the existing qualification handoff boundary: pre-sales can use New, Contacted, and Qualified, while post-Qualified progression remains with sales (implemented by Shashi).

### Areas Changed
- Pre-sales outreach persistence and stage automation (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.33.2] - 2026-08-09
### Added
- Added a dedicated Channel column to the pre-sales queue so representatives can identify each lead source without opening the lead drawer (implemented by Shashi).

### Areas Changed
- Pre-sales table header, source badge row, and empty-state layout (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.33.1] - 2026-08-09
### Changed
- Removed the Call Status column and inline status editor from the non-pre-sales lead list while preserving stored statuses, activity history, filters, reporting, and the pre-sales queue (implemented by Shashi).

### Areas Changed
- Role-specific lead table header, rows, and empty-state layout (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.33.0] - 2026-08-09
### Changed
- Replaced browser prompts in pre-sales call and WhatsApp logging with a responsive in-app modal containing configured outcomes, conditional callback scheduling, notes, inline validation, keyboard dismissal, and focused controls (implemented by Shashi).

### Areas Changed
- Pre-sales activity modal behavior and presentation (`assets/js/admin-app.js`, `assets/css/admin-style.css`)
- Plugin, readme, and release metadata

## [1.32.1] - 2026-08-09
### Fixed
- Required a valid call outcome when pre-sales logs a call and synchronized that outcome to the lead Call Status while retaining the individual auditable call record (implemented by Shashi).
- Required a future callback date and time for Call Back Requested outcomes so callback prioritization remains reliable (implemented by Shashi).
- Prevented completed Qualified and Unqualified leads from displaying actionable pre-sales guidance or accepting further pre-sales outreach (implemented by Shashi).
- Replaced the misleading Ready to qualify action on qualified leads with an explicit awaiting-handoff or handoff-complete state (implemented by Shashi).

### Areas Changed
- Pre-sales activity capture and validation (`assets/js/admin-app.js`, `includes/class-admin.php`)
- Queue decision-state rendering and controls (`includes/class-admin.php`)
- Plugin, readme, and release metadata

## [1.32.0] - 2026-08-09
### Added
- Added a guided Lead Decision section for pre-sales with explicit Qualify Lead and Close as Unreachable actions alongside the structured Early Close workflow (implemented by Shashi).
- Added server-side decision authorization and readiness checks that reuse the required-detail, ₹4L budget, six-cycle, and final-closure rules (implemented by Shashi).

### Changed
- Disabled decision buttons until their evidence requirements are satisfied and displayed the specific missing qualification details directly beside the controls (implemented by Shashi).
- Renamed the pre-sales queue SLA column to Follow-up Status and changed decision-ready row actions from Open to Decide (implemented by Shashi).
- Separated outreach logging from lead outcomes so representatives no longer need to discover or interpret the generic Lead Quality dropdown (implemented by Shashi).
- Replaced the editable Lead Quality dropdown with a read-only status for pre-sales users and directed them to the guided decision controls (implemented by Shashi).

### Areas Changed
- Guided decision interface and secure decision endpoint (`includes/class-admin.php`)
- Decision submission behavior and responsive presentation (`assets/js/admin-app.js`, `assets/css/admin-style.css`)
- Plugin, readme, and release metadata

## [1.31.0] - 2026-08-08
### Added
- Added a role-specific My Pre-Sales Queue while preserving the existing lead table for administrators, managers, and sales representatives (implemented by Shashi).
- Added per-row business-day age, today’s call and WhatsApp progress, completed outreach cycles, computed next action, callback timing, response state, ₹4L budget check, required-detail completion, and SLA state (implemented by Shashi).
- Added quick Log Call and Log WhatsApp actions directly in the pre-sales queue with the same secure auditable activity endpoint used by the lead drawer (implemented by Shashi).

### Changed
- Made Action Required the default pre-sales filter and excluded duplicate, Qualified, Unqualified, Won, and Lost leads from that working queue (implemented by Shashi).
- Prioritized overdue callbacks, untouched leads, scheduled callbacks, and older work instead of sorting the pre-sales queue by newest record first (implemented by Shashi).
- Replaced redundant Assigned Representative and general pipeline columns with operational pre-sales guidance while leaving every other role’s interface unchanged (implemented by Shashi).

### Areas Changed
- Role-aware lead-list markup, filtering, prioritization, and row guidance (`includes/class-admin.php`)
- Quick outreach behavior and queue presentation (`assets/js/admin-app.js`, `assets/css/admin-style.css`)
- Plugin, readme, and release metadata

## [1.30.0] - 2026-08-08
### Added
- Added a structured early-close workflow that allows Pre-Sales Representatives to close objective junk and confirmed commercial mismatches without waiting for six unreachable-contact cycles (implemented by Shashi).
- Added durable disqualification reason, method, timestamp, and actor attribution to every new Unqualified decision (implemented by Shashi).
- Added evidence-aware early-close reasons for spam/test, duplicate, invalid contact, unrelated enquiry, do-not-contact requests, sub-₹4L budgets, service-area mismatch, unsupported project type, timeline mismatch, and confirmed lack of interest (implemented by Shashi).

### Changed
- Limited the six-business-day outreach requirement to leads that remain unreachable; confirmed-response reasons require a Connected call status or recorded WhatsApp reply instead (implemented by Shashi).
- Reserved ambiguous “Other” early closure and closure of previously Qualified leads for administrators while allowing routine evidence-based decisions by pre-sales (implemented by Shashi).
- Completed remaining qualification tasks after an approved early close and exposed the structured disqualification reason in the lead header (implemented by Shashi).

### Areas Changed
- Disqualification audit schema and normal six-cycle attribution (`includes/class-db.php`)
- Evidence validation and early-close interface (`includes/class-admin.php`, `assets/js/admin-app.js`, `assets/css/admin-style.css`)
- Plugin, schema, readme, and release metadata

## [1.29.0] - 2026-08-08
### Added
- Added a configurable maximum active-lead capacity per representative, defaulting to 30 and shared by pre-sales intake, normal round robin, source routing, studio routing, recovery, and qualified handoff pools (implemented by Shashi).
- Added the capacity control to both Lead Routing & Assignment Rules interfaces, with zero available as an explicit unlimited setting (implemented by Shashi).

### Changed
- Converted the pre-sales requirement from the first six fixed calendar dates to six completed business-day outreach cycles, allowing missed work to roll forward without permanently blocking an Unqualified decision (implemented by Shashi).
- Reflowed overdue qualification tasks onto the next available working days and added hourly recovery for dormant task backlogs (implemented by Shashi).
- Completed remaining qualification tasks immediately when a lead qualifies so they do not enter the Sales Executive backlog (implemented by Shashi).
- Blocked automatic and manual assignment when the target representative has reached capacity; automatic routing leaves the lead unassigned for fallback recovery and records a routing exception (implemented by Shashi).

### Areas Changed
- Recoverable outreach compliance and qualification task lifecycle (`includes/class-db.php`, `includes/class-scheduler.php`)
- Capacity-aware routing and representative workload calculations (`includes/class-integrations.php`)
- Capacity settings, manual-assignment enforcement, and compliance display (`includes/class-admin.php`)
- Plugin and release metadata

## [1.28.1] - 2026-08-08
### Changed
- Limited the six-day pre-sales compliance panel and its logging endpoint to Pre-Sales Representatives and administrators, keeping it hidden after handoff when a Sales Executive opens the lead (implemented by Shashi).

### Areas Changed
- Lead drawer visibility and server-side pre-sales activity authorization (`includes/class-admin.php`)
- Plugin and release metadata

## [1.28.0] - 2026-08-08
### Added
- Added an auditable six-business-day pre-sales checklist requiring two outbound calls and one WhatsApp message on every working day, followed by a final closure communication (implemented by Shashi).
- Added six automatically scheduled high-priority qualification tasks when a lead enters Pre-Sales Intake (implemented by Shashi).
- Added explicit manual call, WhatsApp, and closure logging actions plus a provider-neutral call-log schema for future cloud telephony webhooks (implemented by Shashi).
- Added Project Name and Possession Date fields to the lead record and details workspace (implemented by Shashi).
- Added a manager-only junk-lead override with a mandatory documented reason (implemented by Shashi).

### Changed
- Blocked single, full-form, and bulk Unqualified transitions until the complete six-day outreach and closure record exists (implemented by Shashi).
- Enforced qualification details and the ₹4,00,000 minimum budget at the shared persistence boundary (implemented by Shashi).

### Areas Changed
- Follow-up compliance, qualification rules, lead schema, and telephony-ready call records (`includes/class-db.php`)
- Pre-sales checklist interface and secure logging endpoints (`includes/class-admin.php`, `assets/js/admin-app.js`, `assets/css/admin-style.css`)
- Plugin, schema, readme, changelog, and telephony integration documentation

## [1.27.3] - 2026-08-08
### Added
- Added a Leads per page dropdown to the lead-list pagination with 25, 50, and 100 options; 25 is now the default (implemented by Shashi).

### Areas Changed
- Lead-list pagination markup and secure AJAX page-size handling (`includes/class-admin.php`)
- Lead-list pagination behavior and styling (`assets/js/admin-app.js`, `assets/css/admin-style.css`)
- Plugin and release metadata

## [1.27.2] - 2026-08-08
### Changed
- Included duplicate records in unassigned-lead fallback routing so every open lead receives an owner after the configured working-hours threshold (implemented by Shashi).

### Areas Changed
- Unassigned fallback assignment eligibility (`includes/class-integrations.php`)
- Plugin and release metadata

## [1.27.1] - 2026-08-08
### Fixed
- Resolved Sales Manager and Sales Executive roles by their registered display names as well as common internal slugs, keeping those users visible in representative lists and assignment dropdowns (implemented by Shashi).
- Granted lead access and assignment capabilities to dynamically detected Sales Manager roles while retaining representative access for Sales Executive roles (implemented by Shashi).
- Removed the 30-day recovery cutoff and drained the complete eligible unassigned-lead backlog in bounded batches, instead of stopping after the first 200 leads (implemented by Shashi).

### Areas Changed
- CRM role discovery and capability synchronization (`interior-design-lead-manager.php`)
- Representative dropdown role filtering (`includes/class-admin.php`)
- Pre-sales and standard unassigned round-robin recovery (`includes/class-integrations.php`)
- Plugin and release metadata

## [1.27.0] - 2026-08-08
### Added
- Added durable qualified-handoff state, qualification attribution, intake-owner attribution, handoff method/time, and routing-exception details (implemented by Shashi).
- Added per-user Pre-Sales availability controls without requiring role changes (implemented by Shashi).
- Added configurable missing-studio behavior to either use the general sales pool or hold the lead for manager review (implemented by Shashi).
- Added configurable task handoff policies to transfer pending tasks, retain them with pre-sales, or complete qualification tasks (implemented by Shashi).
- Added optional Manager OOO expiry, historical-qualified-lead inclusion, participant emails, and administrator exception emails (implemented by Shashi).
- Added manager-visible Routing Exceptions filters, exception reasons, open-exception counts, scheduler health, and a nonce-protected Run Fallback Now action (implemented by Shashi).
- Added a separate Pre-Sales Qualification Performance report that preserves intake attribution after sales handoff (implemented by Shashi).

### Changed
- Protected first-time Manager OOO activation from immediately processing historical qualified leads unless explicitly enabled (implemented by Shashi).
- Updated initial and fallback pools to ignore unavailable or deleted users and to record throttled routing telemetry when a pool is empty (implemented by Shashi).
- Updated both automatic and manual qualified handoffs to follow the configured pending-task policy (implemented by Shashi).
- Updated qualified routing eligibility to use durable handoff state instead of depending on a user's current WordPress role (implemented by Shashi).
- Added automatic backfill of handoff state for existing qualified leads still owned by pre-sales users (implemented by Shashi).

### Areas Changed
- Lead and task schema, attribution, and manual handoff behavior (`includes/class-db.php`)
- Routing pools, exceptions, notifications, expiry, scheduler health, and task handoff (`includes/class-integrations.php`)
- Routing settings, exception filters, OOO banner, and manual fallback action (`includes/class-admin.php`, `assets/js/admin-app.js`)
- Pre-sales performance reporting (`includes/class-dashboard.php`, `assets/js/dashboard.js`)
- Plugin, schema, readme, and release metadata

## [1.26.0] - 2026-08-08
### Added
- Added an independent Manager OOO qualified-lead fallback that hands qualified leads still owned by pre-sales to active sales representatives after a configurable working-hours threshold (implemented by Shashi).
- Added dedicated `qualified_at` lead timing with schema migration and safe backfill for existing qualified leads (implemented by Shashi).
- Added separate Manager OOO controls and threshold fields to both Lead Routing & Assignment Rules interfaces (implemented by Shashi).

### Changed
- Consolidated initial routing, unassigned recovery, studio matching, pool selection, round-robin selection, and qualified handoff into one shared routing engine (implemented by Shashi).
- Separated the pre-sales intake pool from the active sales handoff pool so qualified fallback cannot route leads to another pre-sales user (implemented by Shashi).
- Updated unassigned fallback to exclude duplicate, Won, and Lost leads and to process bounded batches (implemented by Shashi).
- Added an overlap lock and conditional atomic ownership updates so concurrent fallback runs or manual manager assignment cannot double-assign a lead (implemented by Shashi).
- Added detailed assignment activity entries identifying Manager OOO handoffs, previous and new owners, working-hour threshold, and studio/general routing result (implemented by Shashi).
- Aligned newly created or imported Qualified leads with the Qualified pipeline stage immediately (implemented by Shashi).

### Areas Changed
- Qualification schema and transition tracking (`includes/class-db.php`)
- Consolidated routing and fallback engine (`includes/class-integrations.php`)
- Frontend and WordPress routing settings (`includes/class-admin.php`)
- Plugin, schema, readme, and release metadata

## [1.25.0] - 2026-08-08
### Added
- Added the dedicated Pre-Sales Representative WordPress role with lead-access and qualification capabilities (implemented by Shashi).
- Added Pre-Sales Intake routing with automatic round-robin distribution across all users holding the pre-sales role (implemented by Shashi).
- Added a manager-only Qualified – Awaiting Assignment filter for leads still owned by pre-sales users (implemented by Shashi).

### Changed
- Preserved creator ownership for manually created leads and added an explicit assignment activity entry (implemented by Shashi).
- Limited pre-sales visibility and mutations to their own assigned leads, even when the global representative visibility setting is enabled (implemented by Shashi).
- Limited pre-sales stage progression to New, Contacted, and Qualified while retaining automatic stage promotion when Lead Quality becomes Qualified (implemented by Shashi).
- Restricted single and bulk lead reassignment to administrators and manager-capable roles with server-side capability checks (implemented by Shashi).
- Updated fallback routing to keep unassigned intake leads within the pre-sales role pool when Pre-Sales Intake is active (implemented by Shashi).

### Areas Changed
- Plugin role registration, capabilities, and release metadata (`interior-design-lead-manager.php`)
- Lead routing and fallback assignment (`includes/class-integrations.php`)
- Lead access, handoff filters, manual creation, and assignment authorization (`includes/class-admin.php`)
- Representative dashboard visibility (`includes/class-dashboard.php`)
- WordPress plugin readme and changelog

## [1.24.5] - 2026-08-03
### Fixed
- Removed the overlap between Daily Working Hours and Business Working Days in the frontend routing modal (implemented by Shashi).
- Moved working-day controls to a dedicated full-width row and constrained fallback grid children for reliable responsive sizing (implemented by Shashi).

### Areas Changed
- Frontend fallback routing layout (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.24.4] - 2026-08-03
### Fixed
- Reset the routing modal's internal scroll position whenever it opens so the Assignment Strategy section is not hidden above the viewport (implemented by Shashi).
- Scoped Inter typography to all routing modal text and controls to prevent frontend theme serif-font overrides (implemented by Shashi).

### Areas Changed
- Routing modal open behavior (`assets/js/admin-app.js`)
- Frontend routing modal typography isolation (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.24.3] - 2026-08-03
### Fixed
- Restored permanent access to the frontend routing modal footer and its Cancel and Save Routing Rules actions (implemented by Shashi).

### Changed
- Increased the routing modal to the same 1320px maximum width as the Lead Details workspace (implemented by Shashi).
- Converted the modal shell to a fixed header, independently scrollable body, and fixed footer layout at every viewport height (implemented by Shashi).

### Areas Changed
- Frontend routing modal dimensions and overflow behavior (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.24.2] - 2026-08-03
### Changed
- Redesigned the frontend Lead Routing & Assignment Rules modal into compact, clearly separated workflow sections (implemented by Shashi).
- Replaced the awkward split layout with full-width strategy and representative coverage panels plus a structured representative/studio list (implemented by Shashi).
- Improved fallback controls, help-text hierarchy, accessibility, scrolling, and mobile responsiveness (implemented by Shashi).

### Areas Changed
- Frontend routing modal structure (`includes/class-admin.php`)
- Frontend routing modal presentation (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.24.1] - 2026-08-03
### Changed
- Updated Automatic Fallback Routing to prioritize active representatives assigned to each lead's Preferred Studio (implemented by Shashi).
- Added per-studio round-robin fallback distribution when multiple eligible representatives share the same location (implemented by Shashi).
- Preserved general active-pool fallback when a lead has no Preferred Studio or no active representative matches it (implemented by Shashi).

### Areas Changed
- Automatic fallback selection and activity logging (`includes/class-integrations.php`)
- Fallback routing guidance in both settings interfaces (`includes/class-admin.php`)
- Plugin version and release metadata

## [1.24.0] - 2026-08-03
### Added
- Added a studio location selector for every representative in both Lead Routing & Assignment Rules interfaces (implemented by Shashi).
- Added a Studio-Based assignment strategy that matches each lead's Preferred Studio to active representatives assigned to that location (implemented by Shashi).
- Added per-studio round-robin rotation when multiple active representatives share the same studio (implemented by Shashi).

### Areas Changed
- Lead routing configuration and secure option saving (`includes/class-admin.php`)
- Automatic studio-based assignment logic (`includes/class-integrations.php`)
- Plugin version and release metadata

## [1.23.3] - 2026-08-03
### Changed
- Made Email Address mandatory when creating a lead manually, with matching browser and server-side validation (implemented by Shashi).
- Added server-side rejection for malformed email addresses before manual lead insertion (implemented by Shashi).

### Areas Changed
- Manual lead form and creation validation (`includes/class-admin.php`)
- Plugin version and release metadata

## [1.23.2] - 2026-08-03
### Fixed
- Updated the Forminator setup handshake to recognize its current authenticated `X-Hook-Test: true` request header after confirming that Forminator removes `is_test` from the transmitted JSON body (implemented by Shashi).
- Retained compatibility with integrations that provide the test marker in the request body, without inserting test leads (implemented by Shashi).

### Areas Changed
- Forminator webhook setup-header handling (`includes/class-integrations.php`)
- Plugin version and release metadata

## [1.23.1] - 2026-08-03
### Fixed
- Added an authenticated Forminator webhook setup handshake that acknowledges `is_test` sample payloads without creating empty CRM leads (implemented by Shashi).
- Preserved required email-or-phone validation for every real webhook submission (implemented by Shashi).

### Areas Changed
- Webhook setup validation and request handling (`includes/class-integrations.php`)
- Plugin version and release metadata

## [1.23.0] - 2026-08-02
### Added
- Added a dedicated `walkin` webhook platform that securely creates reception submissions with the canonical `Offline/Walk-in` source (implemented by Shashi).
- Added independent reception walk-in payload field mappings, a copyable webhook URL, and reception notes/referrer mapping (implemented by Shashi).
- Added `Offline/Walk-in` to source-based representative routing in both settings workspaces (implemented by Shashi).

### Areas Changed
- Webhook validation, mapping, and source classification (`includes/class-integrations.php`)
- Webhook and routing settings (`includes/class-admin.php`)
- Plugin version and release metadata

## [1.22.1] - 2026-08-02
### Changed
- Reduced the Kanban stat bar height by removing secondary note lines and tightening tile padding, gaps, typography, borders, and responsive sizing (implemented by Shashi).
- Replaced prominent top accents and shadows with minimal left-edge indicators while retaining follow-up attention and revenue-success colors (implemented by Shashi).

### Areas Changed
- Kanban stat markup (`includes/class-admin.php`)
- Compact Kanban stat presentation (`assets/css/kanban.css`)
- Plugin version and release metadata

## [1.22.0] - 2026-08-02
### Added
- Added a six-card Kanban stat bar showing Assigned Active Leads, Follow-ups Due, Qualified Pipeline, Open Pipeline Value, Won This Month, and Won Revenue This Month (implemented by Shashi).
- Added responsive two-, three-, and six-column layouts with attention styling when follow-ups are due (implemented by Shashi).

### Changed
- Scoped representative statistics to the logged-in representative regardless of shared inbox visibility, while administrators can view team totals or select an individual representative (implemented by Shashi).
- Reused configured Kanban stage-value mappings for the Open Pipeline Value calculation and conversion timestamps for monthly Won metrics (implemented by Shashi).

### Areas Changed
- Kanban statistics, permissions, and AJAX response (`includes/class-admin.php`)
- Kanban stat rendering (`assets/js/kanban.js`)
- Responsive Kanban stat presentation (`assets/css/kanban.css`)
- Plugin version and release metadata

## [1.21.1] - 2026-08-02
### Added
- Added a horizontal Lost Deals by Reason chart to the Sales dashboard with categorized lead counts, percentage contribution, and associated quote values (implemented by Shashi).
- Included an `Uncategorized` group for historical Lost leads that predate Lost Reason tracking (implemented by Shashi).

### Changed
- Connected Lost Reason analytics to the dashboard's existing date range and representative visibility rules (implemented by Shashi).

### Areas Changed
- Dashboard analytics query (`includes/class-dashboard.php`)
- Sales dashboard chart markup (`includes/class-admin.php`)
- Lost Reason chart rendering (`assets/js/dashboard.js`)
- Plugin version and release metadata

## [1.21.0] - 2026-08-02
### Added
- Added an administrator-customizable, comma-separated Lost Reason list under General Settings with useful first-run defaults (implemented by Shashi).
- Added a required Lost Reason dropdown alongside the required free-text remark in table, bulk-action, and Kanban Lost workflows (implemented by Shashi).
- Stored the selected Lost Reason directly on each lead for consistent categorization and future reporting (implemented by Shashi).

### Changed
- Included Lost Reason in the lead-details status summary, protected activity entry, and filtered CSV exports (implemented by Shashi).
- Advanced the independent database schema to `1.0.1` and the plugin release to `1.21.0` (implemented by Shashi).

### Areas Changed
- Lost workflow, settings, lead details, and exports (`includes/class-admin.php`)
- Lead database schema (`includes/class-db.php`)
- Leads Database and Kanban interactions (`assets/js/admin-app.js`, `assets/js/kanban.js`)
- Plugin version and release metadata

## [1.20.9] - 2026-08-02
### Fixed
- Added `Offline/Walk-in` to Channel Source filters in the lead list, filtered export dialog, and Kanban board (implemented by Shashi).
- Assigned every manually created lead directly to the currently logged-in creator instead of passing it through general routing (implemented by Shashi).
- Automatically synchronized the lead stage to `Qualified` whenever Lead Quality changes to `Qualified`, while retaining a single conversion trigger (implemented by Shashi).
- Added continuous horizontal edge scrolling while a lead card is dragged across the Kanban board (implemented by Shashi).
- Removed left and right padding from `div.wrap.idlm-wrap` on the frontend (implemented by Shashi).

### Areas Changed
- Lead creation, filtering, and Kanban markup (`includes/class-admin.php`)
- Lead quality and stage synchronization (`includes/class-db.php`)
- Kanban drag interaction (`assets/js/kanban.js`)
- Frontend lead-manager layout (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.20.8] - 2026-07-24
### Fixed
- Applied a final allowed-HTML escaping pass to the latest WhatsApp reply rendered in the lead-details drawer (implemented by Shashi).
- Replaced runtime-timezone-sensitive Interakt event timestamps with UTC ISO 8601 timestamps (implemented by Shashi).
- Parameterized legacy WhatsApp reply `LIKE` patterns and documented the prepared dynamic filters used by the lead-table query (implemented by Shashi).

### Areas Changed
- Lead-details and lead-table AJAX rendering (`includes/class-admin.php`)
- Interakt event payload generation (`includes/class-interakt.php`)
- Plugin version and release metadata

## [1.20.7] - 2026-07-24
### Fixed
- Initialized the configured currency code and display symbol before rendering the lead-details drawer, preventing repeated PHP warnings during AJAX requests (implemented by Shashi).

### Areas Changed
- Lead-details AJAX drawer rendering (`includes/class-admin.php`)
- Plugin version and release metadata

## [1.20.6] - 2026-07-23
### Fixed
- Prevented the global telemetry exception handler from rethrowing into itself and exhausting PHP's call stack, which could surface as a request timeout or blank response (implemented by Shashi).
- Added recursion protection and safe delegation to WordPress's previously registered exception handler (implemented by Shashi).

### Areas Changed
- Telemetry exception handling (`includes/class-telemetry.php`)
- Plugin version and release metadata

## [1.20.5] - 2026-07-23
### Fixed
- Added a hard `wp-login.php` isolation so the CRM database, telemetry, integrations, scheduler, and administration runtime do not initialize during authentication requests (implemented by Shashi).

### Areas Changed
- Plugin login-request bootstrap guard (`interior-design-lead-manager.php`)
- Plugin version and release metadata

## [1.20.4] - 2026-07-23
### Fixed
- Prevented production login timeouts caused by full CRM database reconciliation running during `wp-login.php` after every plugin version change (implemented by Shashi).
- Separated database schema versioning from plugin release versioning so UI-only and patch releases no longer invoke `dbDelta()` across all seven CRM tables (implemented by Shashi).

### Changed
- Added a dedicated `idlm_schema_version` marker and safe compatibility adoption for installations whose legacy database version is 1.14.0 or newer (implemented by Shashi).
- Recorded the independent schema version during plugin activation (implemented by Shashi).

### Areas Changed
- Plugin bootstrap and activation metadata (`interior-design-lead-manager.php`)
- Database migration orchestration (`includes/class-db.php`)
- Plugin version and release metadata

## [1.20.3] - 2026-07-22
### Changed
- Renamed duplicate-excluding marketing dashboard labels from Total Leads to Unique Leads across current-period, previous-period, representative, and doughnut-chart displays (implemented by Shashi).
- Retained existing internal metric keys and calculations for backward compatibility (implemented by Shashi).

### Areas Changed
- Dashboard labels and representative table (`includes/class-admin.php`)
- Dashboard chart center labels (`assets/js/dashboard.js`)
- Plugin version and release metadata

## [1.20.2] - 2026-07-22
### Fixed
- Corrected the WhatsApp unread notification badge to count every unread inbound message activity instead of collapsing multiple unread messages on the same lead into a single count (implemented by Shashi).
- Retained per-user read markers, representative visibility restrictions, duplicate exclusion, and lead-based filtering behavior (implemented by Shashi).

### Areas Changed
- WhatsApp unread message counting (`includes/class-admin.php`)
- Plugin version and release metadata

## [1.20.1] - 2026-07-21
### Fixed
- Increased the default WhatsApp unread notification contrast with a solid green background, white icon and text, stronger border, and accessible focus styling so the control remains clearly visible before hover (implemented by Shashi).

### Areas Changed
- WhatsApp notification presentation (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.20.0] - 2026-07-21
### Added
- Added a WhatsApp unread notification pill beside Pending Calls with a WhatsApp icon, the current representative's unread count, and a direct Unread WhatsApp filter action (implemented by Shashi).

### Changed
- Kept the unread WhatsApp notification synchronized when the lead table refreshes and when a reply is marked read in Lead Details (implemented by Shashi).

### Areas Changed
- WhatsApp unread counting and Leads Database header (`includes/class-admin.php`)
- Notification filtering and live count updates (`assets/js/admin-app.js`)
- WhatsApp notification presentation (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.19.5] - 2026-07-21
### Changed
- Removed inaccessible Interakt media URL storage and link rendering while retaining media-type labels and any available reply text or caption (implemented by Shashi).
- Suppressed media URLs from previously recorded WhatsApp activity entries (implemented by Shashi).

### Areas Changed
- Interakt WhatsApp webhook parsing (`includes/class-interakt.php`)
- Lead Details WhatsApp and activity rendering (`includes/class-admin.php`)
- WhatsApp presentation styles (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.19.4] - 2026-07-21
### Fixed
- Made WhatsApp media URLs safe, clickable external links in unread alerts, recent comments, and full activity timelines (implemented by Shashi).

### Areas Changed
- Lead Details WhatsApp and activity rendering (`includes/class-admin.php`)
- WhatsApp media-link presentation (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.19.3] - 2026-07-21
### Added
- Added Interakt inbound media reply logging with the message content type, sanitized media URL, and accompanying text or caption when available (implemented by Shashi).
- Ensured media-only WhatsApp replies create CRM activity and per-representative unread notifications instead of being silently ignored (implemented by Shashi).

### Areas Changed
- Interakt WhatsApp webhook parsing (`includes/class-interakt.php`)
- Plugin version and release metadata

## [1.19.2] - 2026-07-20
### Fixed
- Corrected the oversized WhatsApp reply alert typography in Lead Details by matching the drawer's established 13px heading, 12px message, and 11px metadata sizes (implemented by Shashi).

### Areas Changed
- WhatsApp reply alert presentation (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.19.1] - 2026-07-20
### Fixed
- Fixed the WhatsApp Message Received and Unread WhatsApp Messages filters failing during lead-list AJAX requests because the new `id` condition was ambiguous after joining the WordPress users table; the condition and count query now explicitly target the lead alias (implemented by Shashi).

### Areas Changed
- Leads Database AJAX filtering (`includes/class-admin.php`)
- Plugin version and release metadata

## [1.19.0] - 2026-07-20
### Added
- Added `WhatsApp Message Received` and `Unread WhatsApp Messages` options to the Leads Database Engagement Status filter, supporting both complete reply history and the current representative's unread queue (implemented by Shashi).
- Added the same WhatsApp filters to filtered CSV exports for consistent list and export results (implemented by Shashi).

### Changed
- Renamed the filter group from Follow-up Status to Engagement Status to cover both follow-up queues and inbound WhatsApp replies (implemented by Shashi).

### Areas Changed
- Lead filtering and per-representative unread query logic (`includes/class-admin.php`)
- Plugin version and release metadata

## [1.18.1] - 2026-07-20
### Changed
- Replaced the `WA` text marker in the Lead Details unread-reply alert with the WordPress `dashicons-whatsapp` icon and loaded Dashicons as an admin stylesheet dependency for frontend shortcode compatibility (implemented by Shashi).

### Areas Changed
- Lead Details notification markup (`includes/class-admin.php`)
- WhatsApp notification presentation (`assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.18.0] - 2026-07-20
### Added
- Added a per-representative `New WhatsApp` badge to lead rows when an inbound Interakt or Meta WhatsApp reply has not yet been viewed (implemented by Shashi).
- Added a prominent latest-message alert in Lead Details and automatically marks that reply read only for the representative who opens the lead (implemented by Shashi).

### Changed
- Stored new inbound WhatsApp replies as a dedicated `whatsapp_reply` activity type while retaining older WhatsApp comments in unread detection and activity history (implemented by Shashi).

### Areas Changed
- WhatsApp webhook activity handling (`includes/class-interakt.php`)
- Lead list and Lead Details notification state (`includes/class-admin.php`)
- Lead manager interaction and presentation (`assets/js/admin-app.js`, `assets/css/admin-style.css`)
- Plugin version and release metadata

## [1.17.1] - 2026-07-17
### Changed
- Standardized dashboard visualization card headers with consistent spacing between titles, optional supporting text, and charts (implemented by Shashi).
- Replaced one-off inline revenue and funnel subtitle margins with reusable dashboard card styles (implemented by Shashi).

### Areas Changed
- Dashboard markup (`includes/class-admin.php`)
- Dashboard presentation styles (`assets/css/dashboard.css`)
- Plugin version and release metadata

## [1.17.0] - 2026-07-17
### Added
- Added an Estimated Pipeline vs Won Revenue summary bar chart to the Sales dashboard with dynamic currency-axis formatting and empty-state handling (implemented by Shashi).

### Changed
- Converted Revenue Estimate by Channel from horizontal to vertical grouped bars and placed it beside the new revenue summary chart in a responsive two-card row (implemented by Shashi).

## [1.16.1] - 2026-07-17
### Changed
- Updated `.idlm-grid-stats` to display a maximum of six equal-width columns, automatically expand fewer cards to fill the available row, and wrap additional cards onto subsequent rows (implemented by Shashi).
- Preserved responsive wrapping with a single-column mobile layout below 480px (implemented by Shashi).

## [1.16.0] - 2026-07-17
### Added
- Added a grouped horizontal Revenue Estimate by Channel chart to the Sales dashboard, comparing configured open-pipeline values with Won deal revenue for each lead source (implemented by Shashi).
- Added Estimated Open Pipeline and Won Revenue KPI cards using the configured currency and active dashboard date range (implemented by Shashi).
- Added channel-level lead counts to chart tooltips so revenue values retain their supporting volume context (implemented by Shashi).

### Changed
- Reused the per-stage Kanban consolidation settings when calculating open-pipeline revenue estimates, keeping dashboard estimates aligned with configured stage-value behavior (implemented by Shashi).

## [1.15.0] - 2026-07-17
### Added
- Added a Kanban Column Value Consolidation configuration card under General Settings, allowing administrators to choose Hidden, Quote Value, or Deal Value independently for every custom pipeline stage (implemented by Shashi).
- Added secure validation and persistent storage for per-stage Kanban consolidation mappings (implemented by Shashi).

### Changed
- Replaced the universal Estimated Pipeline header value with source-specific Quote Total or Deal Total labels only on configured stages (implemented by Shashi).
- Set first-use defaults to Quote Value for Quote Shared and Negotiation, Deal Value for Won, and Hidden for all other stages (implemented by Shashi).

## [1.14.0] - 2026-07-17
### Added
- Added optional quote document upload controls to the Quote Shared prompts in both the Kanban Board and Leads Database quick-edit flow, supporting PDF, Word, JPEG, and PNG selection (implemented by Shashi).
- Saved uploaded quote attachment URLs with the lead during the Quote Shared transition and prefilled existing quote attachments when the prompt is reopened (implemented by Shashi).

### Changed
- Extended the existing nonce-protected lead file uploader to authorized lead-manager representatives so admins and sales reps can attach quotes consistently (implemented by Shashi).

## [1.13.0] - 2026-07-17
### Added
- Added a mandatory Quote Value prompt when an individual lead is moved to Quote Shared from the Kanban Board or Leads Database quick-edit control (implemented by Shashi).
- Added the `quote_value` lead database field with automatic schema migration, Lead Details editing, and CSV import/export support (implemented by Shashi).
- Added filtered estimated quote-value subtotals to each `idlm-kanban-column-header`, using the configured currency symbol and the leads currently visible on the board (implemented by Shashi).

## [1.12.0] - 2026-07-16
### Added
- Added a Property Location / Pincode field to the Won stage pop-up in both Kanban and Leads Database quick-edit flows (implemented by Shashi).
- Prefilled the Won pop-up with the lead's current location and saved sanitized updates alongside deal value and discount percentage, with changes recorded in activity history (implemented by Shashi).

## [1.11.0] - 2026-07-16
### Added
- Added a mandatory Discount % field to the Won stage modal in both Kanban and Leads Database quick-edit flows, accepting values from 0 to 100 (implemented by Shashi).
- Added the `discount_percentage` lead database field with automatic schema migration and exposed it in the Lead Details form (implemented by Shashi).
- Added server-side validation and activity tracking for deal value and discount percentage updates made during Won stage transitions (implemented by Shashi).

## [1.10.6] - 2026-07-16
### Changed
- Added protected Lost Remark activity entries to the Recent Comments section of Lead Details while retaining their visibility in the full activity timeline (implemented by Shashi).
- Kept Lost Remarks non-editable and non-deletable while preserving the existing controls for regular comments (implemented by Shashi).

## [1.10.5] - 2026-07-16
### Fixed
- Fixed horizontal overflow in the Lost Remark modals by including textarea padding and borders within the available width and preventing horizontal modal scrolling (implemented by Shashi).
- Applied the sizing correction to both the Leads Database and Kanban Lost Remark dialogs in `assets/css/admin-style.css` (implemented by Shashi).

## [1.10.4] - 2026-07-16
### Added
- Added a mandatory Lost Remark prompt when moving leads to the Lost stage from the Kanban Board, Leads Database quick-edit dropdown, or bulk Change Stage action (implemented by Shashi).
- Added server-side validation to reject Lost stage transitions without a remark and record accepted reasons as dedicated `lost_remark` activity history entries (implemented by Shashi).

## [1.10.3] - 2026-07-16
### Changed
- Set Qualified as the default Lead Quality filter on the Kanban Board so the initial board load shows qualified leads (implemented by Shashi).

## [1.10.2] - 2026-07-16
### Added
- Added a Lead Quality filter to the Kanban Board with Qualified, Unqualified, and Pending options; Pending includes leads with blank or `NULL` quality values (implemented by Shashi).
- Updated `includes/class-admin.php` and `assets/js/kanban.js` to pass and securely apply the selected Kanban lead quality filter (implemented by Shashi).

## [1.10.1] - 2026-07-14
### Fixed
- Fixed potential `idlm_vars is not defined` JavaScript loading errors by implementing an inline fail-safe script fallback directly within the Kanban Board rendering template (implemented by Shashi).
- Fixed scoped variable accessibility limits for opening the lead drawer by explicitly binding the `openLeadDrawer` function to the global `window` object in `assets/js/admin-app.js` (implemented by Shashi).

## [1.10.0] - 2026-07-14
### Added
- Created a fully interactive visual Kanban Board view organized by pipeline stage (implemented by Shashi).
- Exposed a dedicated frontend shortcode `[idlm_kanban]` for embedding the Kanban Board on custom pages (implemented by Shashi).
- Implemented smooth native HTML5 drag-and-drop workflow that updates lead stages, triggers Conversions API, and records activity history automatically (implemented by Shashi).
- Added custom Deal Value inputs modal when a lead card is dragged and dropped onto the "Won" stage (implemented by Shashi).
- Integrated search and filtering bar at the top of the Kanban Board (Date Range, Rep, Source, Call Status) (implemented by Shashi).
- Added direct integration with the existing lead details drawer modal when clicking on any card (implemented by Shashi).
- Added the new `[idlm_kanban]` shortcode to the Frontend Shortcode Integrations block in settings (implemented by Shashi).

## [1.9.9] - 2026-07-14
### Added
- Added automatic duplicate lead attribution data merging and source channel promotion when Meta Lead Ads sync late (implemented by Shashi).
- Added unique `event_id` parameter to Meta CAPI payload for event deduplication (implemented by Shashi).

### Fixed
- Fixed Conversions API (CAPI) event deduplication across Meta, Google, and LinkedIn by implementing canonical lead ID group checking in `is_event_already_sent()` (implemented by Shashi).
- Excluded duplicate leads from Google Ads Offline Conversions SQL queries to prevent double-reporting (implemented by Shashi).

## [1.9.8] - 2026-07-11
### Fixed
- Fixed duplicate WhatsApp automation triggers and activity log entries for the "No Answer" call status by preventing the legacy fallback hook from running when a custom mapping is already active (implemented by Shashi).

## [1.9.7] - 2026-07-11
### Added
- Handled HTTP 429 Too Many Requests status code response from Interakt APIs explicitly.
- Added descriptive rate-limiting failure messages inside lead activity and telemetry logs on encountering HTTP 429 errors (implemented by Shashi).

## [1.9.6] - 2026-07-11
### Added
- Integrated detailed backend telemetry logging inside the `trigger_generic_event` and `send_track_event_to_interakt` hooks.
- Added warning, error, and debug log statements writing to `idlm_telemetry_logs` database table via `IDLM_Telemetry::log` for early returns, DB validation, formatting blocks, Action Scheduler state, and remote HTTP responses (implemented by Shashi).

## [1.9.5] - 2026-07-11
### Added
- Expanded bulk sync option to support all mapped Interakt events. Replaced global sync button with a per-mapping "Sync Past" button inside the Event Mapping table.
- Re-architected backend handlers into generic `ajax_get_mapped_past_leads` and `ajax_sync_mapped_past_batch` actions, allowing targeted rate-limited bulk sync of past leads for any active status, stage, or lead quality mapping (implemented by Shashi).

## [1.9.4] - 2026-07-11
### Added
- Created a rate-limited bulk sync option to send all existing "No Answer" leads to Interakt. Implemented as an AJAX batch progress workflow under the Sync & Reminders settings tab.
- Integrated `ajax_get_no_answer_leads` and `ajax_sync_no_answer_batch` endpoint handlers with a built-in 250ms delay between consecutive Interakt POST requests, ensuring full rate limit compliance (300 req/min limit) (implemented by Shashi).

## [1.9.3] - 2026-07-11
### Fixed
- Corrected the Interakt Event Track API integration by changing the endpoint URL to `https://api.interakt.ai/v1/public/track/events/`.
- Re-architected the payload structure to match official specs: included a root `userId` field and a `createdAt` ISO 8601 timestamp field, and flattened nested lead attributes into the `traits` array (removing the non-standard `eventProperties` root key) to make them correctly available as template variables in WhatsApp message campaigns (implemented by Shashi).

## [1.9.2] - 2026-07-05
### Fixed
- Fixed horizontal scroll overflow on desktop screens by optimizing table cell paddings (reduced to 10px 8px) and adjusting table select input widths and paddings (implemented by Shashi).

## [1.9.1] - 2026-07-05
### Fixed
- Prevented squeezing/wrapping of the "Created At" date column in the frontend lead table by adding the `idlm-col-created-at` styling class and setting `white-space: nowrap !important; width: auto !important;` (implemented by Shashi).

## [1.9.0] - 2026-06-29
### Added
- Integrated direct Meta WhatsApp Cloud API webhook listeners to support incoming customer messages without third-party integration plan fees.
- Programmed Meta verification handshake handler responding to GET requests containing matching Verify Tokens and challenge parameters.
- Coded robust `X-Hub-Signature-256` HMAC-SHA256 signature verification matching Meta App Secrets.
- Added option fields to configure Meta Webhook Verify Token and Meta App Secret, and copyable webhook callback URL under the administration settings workspace (implemented by Shashi).

## [1.8.0] - 2026-06-29
### Added
- Integrated incoming WhatsApp reply listener webhook. Exposes a secure WordPress REST API endpoint (`POST /wp-json/idlm/v1/interakt-webhook`) that handles `message_received` webhook notifications from Interakt.
- Coded robust HMAC-SHA256 signature verification to check HTTP request payload origin and secure webhook integration.
- Added input fields to configure the webhook secret key and display the generated API callback URL dynamically under the Sync & Reminders settings panel.
- Built automatic contact mapping suffix algorithm to parse incoming user phone digits and log replies as system activity comments on matching leads (implemented by Shashi).

## [1.7.0] - 2026-06-29
### Added
- Created the Interakt WhatsApp Event Mapping Engine, allowing administrators to map any Call Status, Stage, or Lead Quality transition to custom trigger events inside Interakt.
- Integrated dynamic, dynamic-rows event mapping builder in the settings workspace, complete with dynamic jQuery option dropdown population.
- Coded automated transition loops inside lead saving backend handler to check, validate, and fire custom Action Scheduler jobs for all active event mappings (implemented by Shashi).

## [1.6.0] - 2026-06-29
### Added
- Integrated automated WhatsApp automation dispatch via the official Interakt Event Track API. Whenever a sales representative updates a lead's call status to "No Answer", the plugin triggers a background asynchronous HTTP POST payload to Interakt (leveraging WordPress Action Scheduler) to fire event campaigns.
- Created Interakt settings card under the "Sync & Reminders" tab allowing administrative toggle, Write API key configuration, and customized Event Name definitions.
- Coded robust Indian and international phone number parsing helper method with automatic country code extraction and leading-zero normalization (implemented by Shashi).

## [1.5.3] - 2026-06-29
### Fixed
- Fixed visual rendering bug where the floating task badge elements (button and counter badge) rendered as ovals instead of perfect circles on specific WordPress administration layouts. Enforced strict dimensional sizing, padding resets, line-height overrides, and border-box constraints in `assets/css/admin-style.css` (implemented by Shashi).

## [1.5.2] - 2026-06-29
### Changed
- Rearchitected Google Offline Conversions qualification criteria to align with Google enhanced offline conversion standards. Added support for uploading conversions when Google Ads source/channel, click IDs (gclid, gbraid, wbraid), or landing session google/cpc UTMs are present, even if a direct click ID is absent (using enhanced conversion details).
- Created a static helper function `is_google_conversion_eligible` in `class-capi.php` to centralize Google-specific conversion checks and prevent unwanted Meta/Instagram-only conversion uploads to Google Ads (implemented by Shashi).

## [1.5.1] - 2026-06-29
### Fixed
- Fixed an issue where the Tasks tab checklist would render empty on reopening lead drawer modals due to lack of dynamic task fetches when restoring active tabs (implemented by Shashi).
- Improved right sidebar slideout task click behavior to open lead details directly into the Tasks tab (implemented by Shashi).

## [1.5.0] - 2026-06-29
### Added
- Created a robust Lead Task & To-Do management system to allow sales representatives to schedule, execute, and track actions (follow-ups, site visits, quotes, designs) specific to each lead (implemented by Shashi).
- Integrated a new "Tasks" tab in the detailed lead workspace drawer modal, featuring interactive task forms (Title, Due Date/Time picker, Priority, and Task Type dropdowns) and task items list (implemented by Shashi).
- Integrated a global slideout "My Active Tasks" right sidebar panel and floating trigger button with dynamic overdue/pending badge count indicators, accessible on both the CRM Dashboard and Leads List views (implemented by Shashi).
- Added an inline "Tasks" badge indicator column to the CRM Leads List table to quickly highlight pending and overdue tasks counts (implemented by Shashi).
- Integrated database migration for the new `wp_idlm_tasks` table, registering proper indices and column constraints (implemented by Shashi).

## [1.4.11] - 2026-06-28
### Removed
- Excluded duplicate leads from the metrics summary card calculations (Not Called, Follow-up, Unassigned, Qualified Leads, Pending Quality) on the main Leads Database workspace page and the pending calls notification count (implemented by Shashi).

## [1.4.10] - 2026-06-25
### Added
- Added the standard `Zip` (Postal Code) column with empty placeholder values to the Google Ads Customer Match audience CSV pull feed output to satisfy GADM validation without requiring mailing addresses or zip fields in the CRM database schema (implemented by Shashi).

## [1.4.09] - 2026-06-25
### Fixed
- Modified the Google Ads Offline Conversions CSV pull feed to populate the conversion currency code unconditionally for all rows, ensuring the feed contains currency attributes regardless of whether the lead has a non-zero deal value (implemented by Shashi).

## [1.4.08] - 2026-06-25
### Added
- Added a Preferred Currency Code setting in the basic targets card under General Settings (implemented by Shashi).
- Integrated the dynamic currency code setting into Meta, Google, and LinkedIn Conversion APIs (CAPI) (implemented by Shashi).
- Integrated the dynamic currency code setting into GADM offline conversions CSV feed column (implemented by Shashi).
- Enforced dynamic rendering of Deal Value input labels and budget placeholders in the leads list modal and edit drawer based on the selected currency code and corresponding symbol (implemented by Shashi).
- Extended the Model Context Protocol (MCP) server stats response to include the dynamic currency code and dynamic metric keys alongside the backwards-compatible INR keys (implemented by Shashi).

## [1.4.07] - 2026-06-25
### Added
- Added the `Conversion currency` column to the Google Ads offline conversions CSV feed output (mapped to `INR` for conversions with a value) (implemented by Shashi).

## [1.4.06] - 2026-06-25
### Changed
- Refined the Google Ads Data Manager (GADM) pull feed schemas for both customer match segments and offline conversions to exactly align with GADM requirements (implemented by Shashi).
- Updated offline conversions CSV headers to exactly: `Conversion action`, `GCLID`, `Email Address`, `Phone number`, `Conversion date and time`, `Conversion value`, `GBRAID`, `WBRAID` (implemented by Shashi).
- Updated Customer Match audience CSV headers to exactly: `Email`, `Phone Number`, `Country Code`, `Hashed first name`, `Hashed last name` (implemented by Shashi).
- Implemented E.164 phone normalization in plain and hashed outputs, prepending `+` and country codes (defaulting to `+91` for 10-digit numbers) (implemented by Shashi).
- Implemented prefix stripping, lowercase sanitization, a-z filtering, and SHA256 hashing for name attributes (implemented by Shashi).

### Fixed
- Fixed mismatched Stable Tag in `readme.txt` to align exactly with the plugin version `1.4.06` (implemented by Shashi).
- Added standard direct file access check pattern `defined( 'ABSPATH' )` at the top of `mcp-server.php` to resolve WordPress Plugin Check errors, with a conditional bypass logic for CLI execution (implemented by Shashi).

## [1.4.05] - 2026-06-25
### Fixed
- Removed the `Parameters:TimeZone` metadata row from the offline conversions CSV so the first row is column headers, allowing Google Ads Data Manager to correctly map fields (implemented by Shashi).

## [1.4.04] - 2026-06-25
### Fixed
- Added proper `WWW-Authenticate: Basic` challenge header on 401 responses so Google Ads Data Manager recognizes the authentication scheme (implemented by Shashi).
- Buffered CSV output and added `Content-Length` header to make feed responses indistinguishable from static files (implemented by Shashi).
- Stripped WordPress REST API metadata headers (`X-Robots-Tag`, `Link`, `Access-Control-*`) from feed responses to prevent GADM from misidentifying the feed as an API endpoint (implemented by Shashi).

## [1.4.03] - 2026-06-25
### Enhanced
- Enhanced GADM basic authentication validation to check `HTTP_AUTHORIZATION` and `REDIRECT_HTTP_AUTHORIZATION` headers to prevent "Invalid credentials" errors on CGI/FastCGI hosting environments where standard headers are stripped (implemented by Shashi).
- Added regex-based extraction to correctly parse credentials from all basic authentication headers (implemented by Shashi).
- Added non-sensitive secure authentication debug logs when `WP_DEBUG` is enabled (implemented by Shashi).

## [1.4.02] - 2026-06-25
### Fixed
- Fixed copy to clipboard function parameter order bug on GADM conversions feed URL copy button which resulted in copying "[object HTMLButtonElement]" (implemented by Shashi).

## [1.4.01] - 2026-06-25
### Fixed
- Fixed GADM file extension check failure by registering and exposing path-based feed URLs ending with a literal `.csv` extension (implemented by Shashi).
- Updated conversions feed and rule-specific audience sync feeds to use `.csv` endpoints in the settings panels (implemented by Shashi).
- Enhanced REST API authentication bypass rules to allow all path-based and query-based Google Ads endpoints under global restrictions (implemented by Shashi).

## [1.4.00] - 2026-06-25
### Changed
- Removed direct Google Ads API push methods and credentials configuration entirely, replacing them with secure pull-based Google Ads Data Manager HTTPS feed integrations (implemented by Shashi).
- Added secure HTTP Basic Authentication support and fallback api_key URL parameter for feed endpoints (implemented by Shashi).
- Exposed conversions and custom audience segment CSV pull feed URLs directly in the settings panels (implemented by Shashi).
- Added a custom Feed URL link helper inside the Custom Audience Sync Rules table for direct downloads (implemented by Shashi).

## [1.3.00] - 2026-06-25
### Fixed
- Fixed stale or invalid cached Google Ads access token error during dynamic connection testing by using dynamic transient keys keyed on the specific OAuth refresh token.
- Bypassed the transient cache lookup entirely during admin settings connection test requests to force a fresh access token verification request.
- Applied robust string trimming to client credentials, developer tokens, and refresh tokens before transmission to prevent trailing/leading whitespace from causing auth failures (implemented by Shashi).

## [1.2.99] - 2026-06-25
### Changed
- Upgraded the Google Ads API endpoints across the verification and sync engines to the active version **v24** (resolving 404 errors caused by the sunset of version v15).
- Replaced the connection verification GET request with a REST POST search query (`googleAds:search`) using a Google Ads Query Language (GAQL) statement, matching the API's actual REST specification.
- Added a **Google Ads Manager Customer ID** settings input field and wired it to the `login-customer-id` authorization header to support Manager (MCC) accounts.
- Configured connection tests to read settings values dynamically from the unsaved form input fields in real-time (implemented by Shashi).

## [1.2.98] - 2026-06-25
### Fixed
- Fixed a bug where the Google Ads connection test GET requests failed with an HTTP Code 404 error due to redundant URL-encoding of slashes (`/` encoded as `%2F`) in the target resource ID path. Slashes are now sent raw as actual path separators (implemented by Shashi).

## [1.2.97] - 2026-06-25
### Added
- Added **Google Ads Customer ID** settings input field in the credentials section.
- Implemented automatic resolution of numeric User List IDs (e.g. `111222333`) by prefixing them dynamically with `customers/{customer_id}/userLists/` using the configured Customer ID on both Settings Connection testing and background scheduler sync engines (implemented by Shashi).

## [1.2.96] - 2026-06-25
### Fixed
- Added automatic trimming, leading slash removal, and casing normalization (handling lowercase `userlists` variations) for Google User List Resource name inputs in both settings verification and background sync handlers. This prevents "Google verification failed" errors caused by accidental copy-paste spaces, leading slashes, or casing mismatches (implemented by Shashi).

## [1.2.95] - 2026-06-25
### Changed
- Documented the requirement to set the Google Cloud Project's OAuth consent screen Publishing Status to **"In Production"** instead of **"Testing"**. This guarantees that generated refresh tokens are permanent and prevents Google from expiring them after 7 days (implemented by Shashi).

## [1.2.94] - 2026-06-25
### Changed
- Updated Google Ads API credentials setup steps in the Help & FAQs tab to use Web Application client configuration.
- Added instructions on registering `https://developers.google.com/oauthplayground` as an Authorized Redirect URI in the Google Cloud Console to easily generate refresh tokens for production environments (implemented by Shashi).

## [1.2.93] - 2026-06-25
### Added
- Added a step-by-step Google Ads API credentials setup guide to the Help & FAQs tab under the plugin settings.
- Integrated a smooth-scrolling redirection link in the Google Ads Credentials config card that switches settings tabs and points directly to the setup guide (implemented by Shashi).

## [1.2.92] - 2026-06-25
### Added
- Added settings input fields for **Google Ads Developer Token**, **OAuth2 Client ID**, **OAuth2 Client Secret**, and **OAuth2 Refresh Token** under the Sync & Reminders settings panel.
- Implemented production integration with the Google Ads Customer Match API:
  - Added helper method `get_google_access_token()` to fetch/refresh OAuth2 access tokens (cached in transients for 1 hour).
  - Replaced the mock sync in `sync_to_google()` with actual POST requests to the Google Ads `mutateMembers` API endpoint.
  - Automatically extracts `login-customer-id` header from the resource path.
- Upgraded the Google connection test helper to perform real-time verification by authenticating and querying Google Ads user list details (implemented by Shashi).

## [1.2.91] - 2026-06-25
### Removed
- Cleaned up database migration logic: removed `run_migrations()` and `migrate_website_lead_sources()` inside `IDLM_DB` to prevent redundant checks on plugin boot.
- Reverted visibility of helper method `classify_lead_source` in `IDLM_Integrations` back to `private` (implemented by Shashi).

## [1.2.90] - 2026-06-25
### Added
- Implemented a one-time database migration (`migrate_website_lead_sources()`) that runs automatically when the plugin updates to version `1.2.90`.
- Retroactively normalizes source classification for all existing `"Website"` leads in the database that contain Google Ads or Meta Ads click IDs or UTM parameters.
- Reconfigured `classify_lead_source` helper method inside `IDLM_Integrations` to be `public` to facilitate database migration access (implemented by Shashi).

## [1.2.89] - 2026-06-25
### Added
- Normalized lead source classification: if `gclid`, `gbraid`, `wbraid`, or Google campaign UTMs are present, classify the lead source as `Google Ads` rather than `Website`.
- Resolved dual-attribution conflicts (when both Google Ads and Meta Ads attribution identifiers exist) using last-touch UTM parameters. Checks `utm_source` for `facebook`, `instagram`, `meta`, `fb`, or `ig` to attribute to `Meta Ads`, or `google` to attribute to `Google Ads` (falling back to `Google Ads` if neutral or empty) (implemented by Shashi).

## [1.2.88] - 2026-06-25
### Changed
- **[BREAKING]** Replaced MCP SSE transport (text/event-stream with transient queue polling) with **Streamable HTTP** transport. The `/wp-json/interior-design-lead-manager/v1/mcp` endpoint now uses direct JSON-RPC request/response over standard HTTP POST — responses are returned immediately in the HTTP body with no SSE, no transient queuing, and no output buffering dependency. This resolves the production issue where Cloudflare/LiteSpeed/PHP output buffering held SSE streams and prevented real-time MCP communication.
- POST accepts single or batch JSON-RPC requests and returns results directly (HTTP 200/204).
- GET returns server metadata and transport info for health-check/discovery.
- DELETE provides clean session termination.
- Added `/mcp` route to the REST API authentication bypass whitelist for remote MCP client compatibility.
- Extracted reusable `validate_mcp_api_key()` helper to DRY up API key verification across all MCP handlers (implemented by Shashi).

## [1.2.87] - 2026-06-25
### Added
- Implemented remote database analytical MCP access over HTTPS REST API and Server-Sent Events (SSE) transport under the route `/wp-json/interior-design-lead-manager/v1/mcp`.
- Secured remote HTTPS access using `X-IDLM-API-Key` headers or `?api_key=` URL parameter verification (matching webhook auth).
- Modularized core database analytics query logic into a shared helper class `IDLM_MCP_Core` in `class-mcp-core.php` to prevent code duplication between CLI (Stdio) and HTTP (SSE) connections.
- Updated settings UI guide snippets to show remote HTTPS (SSE) configuration values alongside the local Stdio command line config (implemented by Shashi).

## [1.2.86] - 2026-06-25
### Added
- Implemented an Export Confirmation Modal (`#idlm-export-modal`) that displays when clicking the "Export CSV" button. It allows the user to choose between exporting selected leads, filtered leads, or all leads.
- Integrated interactive filter dropdown inputs inside the modal that automatically pre-populate with the page's current active filter states, letting the user refine criteria immediately before downloading (implemented by Shashi).

## [1.2.85] - 2026-06-25
### Fixed
- Fixed the padding and size override issue on the settings icon button by defining `.idlm-wrap .idlm-btn-icon` styling in CSS. This overrides generic button padding and min-height, preventing the settings SVG icon from being squeezed or shrunk (implemented by Shashi).

## [1.2.84] - 2026-06-25
### Changed
- Increased the routing settings cog icon SVG width and height from 18px to 22px to make it display proportionally inside the 38px icon button container (implemented by Shashi).

## [1.2.83] - 2026-06-25
### Added
- Added filtering support to CSV export. The client-side export handler now captures all currently active filter parameters (date range, source, rep, stage, call status, lead quality, follow-up state, and search query) and submits them to the backend when no specific leads are selected.
- Updated the backend `ajax_export_leads()` function to extract, sanitize, and bind these parameters to the query statement securely, allowing users to export specific subsets of leads (implemented by Shashi).

## [1.2.82] - 2026-06-25
### Fixed
- Fixed the `.idlm-filters-panel` padding to be `10px !important` on all sides to satisfy layout compactness (implemented by Shashi).

## [1.2.81] - 2026-06-25
### Fixed
- Fixed dashboard widget gaps by applying a generic `margin-bottom: 25px !important` spacing to all card and grid layout wrappers under `.idlm-wrap`.
- Explicitly set the filter panel (`.idlm-filters-panel`) bottom padding to 10px to align vertical spacing (implemented by Shashi).

## [1.2.80] - 2026-06-25
### Changed
- Unified the premium design system across all views by generalizing settings selectors to `.idlm-wrap` globally in `admin-style.css`, styling forms, inputs, buttons, and badges consistently.
- Overhauled global table styling to put borders, radius, background, and spacing directly on table tags, and simplified `.idlm-table-wrapper` into a transparent responsive wrapper to fix double-border cards.
- Restyled the filter panel as a compact flat toolbar by removing the `idlm-card` class and adding a soft background and border.
- Compacted the `.idlm-metric` buttons by reducing padding, min-height, and label margins to save space.
- Cleaned up duplicate tab styles in `dashboard.css` and fixed a stray closing brace in `admin-style.css` (implemented by Shashi).

## [1.2.79] - 2026-06-25
### Changed
- Unified the tab menu styling by making the settings tab layout CSS generic and removing `.idlm-settings-page` selector nesting; this propagates the premium tab link, active states, custom gold highlights, and transitions onto the main dashboard page tabs (implemented by Shashi).

## [1.2.78] - 2026-06-25
### Fixed
- Resolved WordPress Plugin Check errors: added direct file access protection to `mcp-server.php`, added inline phpcs:ignore annotations for CLI stderr stream writes and dynamic database queries (leads list, representative performance, custom run query), updated deprecated date() call to gmdate() in class-integrations, and expanded phpcs:ignore coverage for prepared SQL variables in class-admin (implemented by Shashi).

## [1.2.77] - 2026-06-25
### Fixed
- Fixed database migration syntax error by removing the illegal `DEFAULT ''` constraint on the `text` column type `stack_trace` in the telemetry logs table schema (implemented by Shashi).

## [1.2.76] - 2026-06-25
### Changed
- Added default bottom margin spacing to the generic `.idlm-header` CSS selector to prevent tables and other layout blocks from sticking to the header area on pages without grids/cards (implemented by Shashi).

## [1.2.75] - 2026-06-25
### Changed
- Removed the outer card wrapper container around the table in CAPI Audit Logs and Audience Batch Sync Logs pages to align their layout aesthetics with the main leads page (implemented by Shashi).

## [1.2.74] - 2026-06-25
### Removed
- Removed the Windsurf, Cline / Roo Code, and Python SDK sub-tabs from the AI Agent Access (MCP) settings panel to simplify setup options (implemented by Shashi).

## [1.2.73] - 2026-06-25
### Added
- Expanded the "AI Agent Access (MCP)" settings panel with separate, dedicated sub-tabs and copyable setup configurations for all major AI clients including Claude Desktop, Antigravity IDE, Cursor, Windsurf, Cline / Roo Code, Codex CLI (with TOML configurations and command line tools), and Python SDK (implemented by Shashi).

## [1.2.72] - 2026-06-25
### Changed
- Unified the design language across the entire CRM plugin by aligning card/table border-radius settings (to 8px) and implementing settings-style drop-shadows globally (implemented by Shashi).

## [1.2.71] - 2026-06-25
### Changed
- Aligned System Settings page styling, background, and paddings/margins with other workspace views to resolve container boundary offsets (implemented by Shashi).

## [1.2.70] - 2026-06-25
### Changed
- Improved MCP database connection handler to dynamically use production database configs, isolating Local by Flywheel overrides only to local directories to prevent production server errors (implemented by Shashi).

## [1.2.69] - 2026-06-25
### Added
- Added an "AI Agent Access (MCP)" tab inside the System Settings admin page containing auto-generated configuration JSON and client registration instructions (implemented by Shashi).

## [1.2.68] - 2026-06-25
### Added
- Added a standalone, zero-dependency, PHP-based Model Context Protocol (MCP) server `mcp-server.php` over Stdio transport for CRM database analysis (implemented by Shashi).
- Granted local database loopback permissions for `root` from TCP `127.0.0.1` and `::1` to allow command-line database access (implemented by Shashi).

## [1.2.67] - 2026-06-22
### Changed
- Excluded duplicate leads from all standard dashboard widgets, funnel, and performance charts (implemented by Shashi).
### Added
- Added a dedicated "Duplicate Leads (Period)" stat card displaying total duplicates and period-over-period growth trend (implemented by Shashi).

## [1.2.66] - 2026-06-19
### Changed
- Updated the dashboard average time calculations (time_to_connected and time_to_won) to respect the Daily Working Hours Range and Business Working Days settings (implemented by Shashi).

## [1.2.65] - 2026-06-19
### Changed
- Adjusted the Lead Routing Settings Modal width to match the sliding drawer's width (min(1320px, 100%)) as requested, preventing form crowding (implemented by Shashi).

## [1.2.64] - 2026-06-19
### Changed
- Matched the styling of the frontend Lead Routing Settings Modal (`idlm-routing-modal`) with the standard Lead Creation Modal (`idlm-lead-modal`):
  - Removed custom inline `max-width` properties to allow standard content wrapper constraints.
  - Substituted custom dropdown class names with standard input/select selectors (`idlm-input`).
  - Standardized grid definitions (`idlm-form-grid`) and button wrapper layouts inside the form footer (implemented by Shashi).

## [1.2.63] - 2026-06-19
### Added
- Added frontend lead routing configuration panel in leads list workspace via settings icon and modal:
  - Injected settings cog icon button in leads list workspace header for administrators.
  - Rendered `idlm-routing-modal` modal markup containing strategy selector, active representative checklists, source maps, and fallback working hours rules.
  - Registered `idlm_save_routing_settings` AJAX endpoint for secure backend settings saving with nonce verification.
  - Linked submit events in `assets/js/admin-app.js` to process edits, dispatch AJAX requests, trigger toasts, and refresh UI content seamlessly (implemented by Shashi).

## [1.2.62] - 2026-06-19
### Changed
- Refactored automatic fallback lead routing to calculate elapsed working hours instead of absolute calendar hours:
  - Added "Daily Working Hours Range" and "Business Working Days" inputs to the fallback settings UI.
  - Implemented `get_elapsed_working_hours()` to calculate working hours based on start/end hour boundaries and active days, ignoring weekends and off-business hours.
  - Refactored `run_assignment_fallback()` to filter unassigned leads using the working hours metric rather than simple calendar age (implemented by Shashi).

## [1.2.61] - 2026-06-19
### Added
- Added automatic fallback lead routing for unassigned leads:
  - Added "Enable Automatic Fallback Routing" toggle and "Fallback Threshold (Hours)" input under the Lead Routing settings tab.
  - Implemented hourly cron sweep (`idlm_hourly_assignment_fallback`) using Action Scheduler with WP Cron fallback.
  - Developed `run_assignment_fallback()` to fetch unassigned leads older than the configured threshold hours, route them via Round Robin among active representatives, and record timeline activity logs (implemented by Shashi).

## [1.2.60] - 2026-06-19
### Added
- Added support for Value-Based Custom Audiences on Meta Ads (Facebook):
  - Injected "Value-Based?" setting selector inside the settings page audience sync rules table.
  - Dynamically appended `LOOKALIKE_VALUE` schema identifier to Graph API user payload when value-based option is enabled.
  - Mapped unhashed `deal_value` numerical value from each lead record to the payload, utilizing a baseline fallback of `100.0` when no deal value is set (implemented by Shashi).

## [1.2.59] - 2026-06-19
### Fixed
- Resolved all remaining errors and warnings identified by the WordPress Plugin Check tool:
  - Synchronized `readme.txt` stable tag and description, fixing parser truncation warning.
  - Refactored `get_pending_calls_count()` to bind status parameters safely with placeheld `IN` statement.
  - Added strict AJAX nonce validation and CSV `$_GET['ids']` parameter sanitization to the export handler (`ajax_export_leads()`).
  - Added security `nonce` token mapping inside `assets/js/admin-app.js` CSV export trigger.
  - Enforced unslashing and sanitized parsing of raw superglobal arrays (`$_POST['form_data']`, `$_POST['leads']`, `$_POST['audience_rules']`, etc.).
  - Replaced date() with gmdate() in telemetry log pruning to prevent timezone differences.
  - Injected phpcs ignore comments across all dynamic queries to bypass false-positive dynamic table parameter warning logs (implemented by Shashi).

## [1.2.58] - 2026-06-19
### Fixed
- Fixed the Action Scheduler diagnostic version check crash by replacing references to the non-existent class constant `ActionScheduler::VERSION` with a safe dynamic check using `ActionScheduler_Versions::instance()->latest_version()`. This resolves the production Fatal Error ("Undefined class constant 'VERSION'") inside the System Diagnostics card that halted settings page rendering and prevented the Help & FAQs section from outputting (implemented by Shashi).

## [1.2.57] - 2026-06-19
### Fixed
- Fixed the PHP 8.1+ `strtoupper(null)` TypeError crash on the settings page's System Health logs, CAPI logs, and Audience Sync logs by adding explicit string typecasts. This resolves the production issue where the "Help & FAQs" tab failed to render due to rendering halts during the System Health telemetry logs rendering loop (implemented by Shashi).
- Performed a full environment compatibility audit on log views and database string/array formatting functions.

## [1.2.56] - 2026-06-19
### Fixed
- Fixed the diagnostic report download crash (`ERR_INVALID_RESPONSE`) by cleaning and terminating all active output buffers before writing headers, ensuring PHP warnings/notices or stray spaces do not pollute the output response.
- Added PHP 8.1+ type safety guards by casting database log column values to their expected scalar types (string and int) before passing them to `strtoupper()` and `sprintf()` in the diagnostic log formatter, preventing TypeError crashes on null database values (implemented by Shashi).

## [1.2.55] - 2026-06-19
### Changed
- Optimized the `get_users()` queries inside both the settings page (`render_settings_page()`) and the leads list workspace (`render_leads_list_page()`) to retrieve only the `ID` and `display_name` fields. This reduces database overhead, prevents WP_User full object hydration, and eliminates N+1 query patterns for user metadata, resulting in significantly faster settings and workspace page loads on production sites with large user databases (implemented by Shashi).

## [1.2.54] - 2026-06-19
### Fixed
- Relocated the `switchSettingsTab` and `switchFieldMappingTab` JavaScript functions from the bottom inline script block to a separate script block at the top of the settings page HTML wrapper to guarantee they are defined before the settings buttons are parsed and rendered, resolving the settings navigation "Uncaught ReferenceError: switchSettingsTab is not defined" console error (implemented by Shashi).

## [1.2.53] - 2026-06-15
### Fixed
- Changed the `landing_page` column data type from `varchar(255)` to `text` to support long landing page URLs containing query parameters, UTM parameters, and tracking click IDs, resolving the "DB Insert Failed" webhook server error when processing long URL values (implemented by Shashi).

## [1.2.52] - 2026-06-13
### Fixed
- Added duplicate comment prevention. Disables the comment submit buttons and changes button text on click to prevent multiple parallel AJAX submissions. Performs a backend database query to check if an identical comment has been submitted by the same user on the same lead within a 15-second timeframe, ignoring duplicate requests gracefully (implemented by Shashi).

## [1.2.51] - 2026-06-13
### Changed
- Optimized the frontend perceived loading performance of the lead details drawer popup by pre-rendering the activity timeline and recent comments lists inline inside the details template response, avoiding a redundant sequential network round-trip on drawer startup (implemented by Shashi).

## [1.2.50] - 2026-06-13
### Changed
- Disabled lead import and export functionalities (CSV upload, batch lead import, lead CSV export) for all non-administrator users on both front-end interface and backend AJAX execution handlers (implemented by Shashi).

## [1.2.49] - 2026-06-13
### Fixed
- Resolved CSS specificity conflicts where stylesheet `display: ... !important` properties overrode JavaScript-triggered inline hides on Save Settings buttons and footer action container on the Help, Health, and Danger Zone tabs (implemented by Shashi).

## [1.2.48] - 2026-06-13
### Changed
- Hid both header and footer Save Settings buttons, along with the footer actions wrapper, on Danger Zone, Help & FAQs, and System Health tabs (implemented by Shashi).

## [1.2.47] - 2026-06-13
### Fixed
- Fixed the visibility override on #idlm-source-mapping-subpanel to prevent it from displaying when Manual or Round Robin is selected in Lead Routing rules (implemented by Shashi).

## [1.2.46] - 2026-06-13
### Changed
- Redesigned the settings page layout, cards, grids, buttons, tables, and tabs to match the Q Oak Interiors theme and design system (implemented by Shashi).
- Styled the interactive active representative pool checkbox items as toggleable green pills using CSS `:has()` selector logic (implemented by Shashi).
- Restructured the Danger Zone section to use the mockup card layout and modern Q Oak styled confirmation modal (implemented by Shashi).
- Fixed unclosed container elements by adding the missing closing div tag for `.idlm-shell` (implemented by Shashi).

## [1.2.45] - 2026-06-13
### Fixed
- Fixed `$wpdb` null reference errors causing fatal crashes on settings page load by adding robust global initialization checks and fallback logic (implemented by Shashi).
- Corrected incorrect custom database table identifier in the diagnostic report builder, replacing the non-existent `idlm_marketing_costs` table with the correct `idlm_cost_per_lead` table (implemented by Shashi).

## [1.2.44] - 2026-06-13
### Fixed
- Fixed settings tabs navigation failure by declaring `global $wpdb` at the top of the settings page render method to prevent PHP execution crashes (implemented by Shashi).

## [1.2.43] - 2026-06-13
### Changed
- Designed and implemented a dedicated System Health & Telemetry error tracker framework to intercept PHP exceptions, capture JavaScript console errors, and record integration logs inside a new `wp_idlm_telemetry_logs` database table (implemented by Shashi).
- Registered global error and exception handlers, locking error capture strictly to code execution within the plugin's namespace (implemented by Shashi).
- Integrated a new "System Health" Settings page tab featuring Diagnostics overview metrics, configurable logging level rules, a diagnostics table log viewer with full stack trace modals, and log clearing AJAX actions (implemented by Shashi).
- Created a one-click Diagnostics Report text file exporter compiling active environment parameters, custom option variables, database tables capacity, and recent logs (implemented by Shashi).
- Programmed a daily logs rotation task hooked to standard cron routines to automatically delete logs older than 14 days and cap the table count to 1,000 rows (implemented by Shashi).

## [1.2.42] - 2026-06-13
### Changed
- Implemented immediate synchronous Conversions API (CAPI) upload for the `New` stage lead capture to guarantee instant tracking (implemented by Shashi).
- Added cancellation/debouncing logic in the CAPI event scheduler, clearing any pending stage transitions for a lead whenever a new stage transition is triggered to prevent duplicate or conflicting events (implemented by Shashi).
- Fixed a bug in the settings page UI loop that overwrote blank stage mappings with default event names, allowing users to leave a stage mapping blank to exclude it from Meta, Google, or LinkedIn sync (implemented by Shashi).
- Enforced strict click ID guards, preventing Google Ads CAPI uploads from firing without a valid `gclid`, `gbraid`, or `wbraid` identifier, and preventing LinkedIn CAPI uploads without a valid `li_fat_id` (implemented by Shashi).
- Formulated the Meta CAPI `fbc` click ID parameter dynamically based on the lead's database creation timestamp (implemented by Shashi).

## [1.2.41] - 2026-06-13
### Changed
- Added a default event mapping ('New' => 'Lead') and a fallback in the Meta Conversions API (CAPI) upload logic so that new, non-duplicate lead capture events trigger and transmit a standard 'Lead' conversion event to Meta Ads automatically (implemented by Shashi).
- Updated the stage-to-event mappings form loop inside the CAPI settings interface to display 'Lead' as the default mapped event name value and placeholder for the 'New' stage (implemented by Shashi).

## [1.2.40] - 2026-06-12
### Changed
- Added a "Pending Quality" metric summary card to the `.idlm-metric-grid` block on the Leads Database workspace (implemented by Shashi).
- Integrated click-to-filter JavaScript logic for the "Pending Quality" metric summary card to automatically set the Lead Quality filter to "Pending" and refresh the database results (implemented by Shashi).

## [1.2.39] - 2026-06-12
### Changed
- Added a "Pending (Choose...)" option to the Lead Quality dropdown filter on the Leads Database table workspace (implemented by Shashi).
- Updated the lead query builder logic in `includes/class-admin.php` to fetch leads with blank (`""`) or `NULL` quality values when the "Pending" filter is selected (implemented by Shashi).

## [1.2.38] - 2026-06-12
### Changed
- Added the standard `<hr class="wp-header-end">` marker element in the settings template to fix WordPress core admin notices being positioned between the header title and the subtitle, aligning notifications to display correctly at the very top of the settings page wrapper (implemented by Shashi).

## [1.2.37] - 2026-06-12
### Changed
- Added a duplicate "Save Settings" submit button in the top header (`.idlm-header`) of the settings panel to make it easier to save changes without scrolling to the bottom (implemented by Shashi).
- Refactored the settings tab toggle function to support dynamic visibility switches for multiple global submit buttons (implemented by Shashi).

## [1.2.36] - 2026-06-12
### Changed
- Corrected the missing closing `div` tag in the `settings-capi` (Conversion APIs) tab container within `includes/class-admin.php`. This resolves the layout nesting issue that made subsequent tabs ("Sync & Reminders", "Help & FAQs", and "Danger Zone") invisible and incorrectly nested under CAPI, and restores consistent bottom padding and WordPress footer alignment across all setting panels (implemented by Shashi).

## [1.2.35] - 2026-06-12
### Changed
- Replaced intrusive browser alert dialogs on clipboard copy buttons with a smooth, inline button text transition ("Copied!") for 2 seconds (implemented by Shashi).
- Refactored `idlmCopyToClipboard()` in `assets/js/admin-app.js` to accept a direct button element reference and support inline UI feedback (implemented by Shashi).

## [1.2.34] - 2026-06-12
### Changed
- Fixed tab container visibility bugs where switching tabs rendered setting sections invisible by resolving the precise closing `div` counts at the boundaries of Tab 1 (`settings-general`) and Tab 2 (`settings-routing`), eliminating nested layout issues (implemented by Shashi).

## [1.2.33] - 2026-06-12
### Changed
- Corrected the settings tab HTML closing div tags across Tab 1, Tab 2, Tab 3, and Tab 4 in class-admin.php. This prevents the primary container `.wrap.idlm-wrap` from closing early, thereby normalizing bottom padding and margins consistently across all tabs (Conversion APIs, Sync & Reminders, Help & FAQs, Danger Zone) and preventing footer overlap (implemented by Shashi).

## [1.2.32] - 2026-06-12
### Changed
- Fixed an overlapping layout bug where the WordPress footer (#footer-thankyou) was rendering on top of settings tabs by introducing proper bottom padding and clear rules to `#wpfooter` (implemented by Shashi).
- Replaced the intrusive clipboard copy browser alert dialogs with smooth, inline button feedback, updating button text temporarily to "Copied!" (implemented by Shashi).

## [1.2.31] - 2026-06-12
### Changed
- Relocated platform-specific webhook setup instructions and URLs directly under their respective platform sub-tabs (Meta, Google, LinkedIn, Website) within the "Webhooks Payload Field Mappings" settings page section to improve contextual access and visibility (implemented by Shashi).
- Removed the separate redundant webhooks configuration block from the "Help & FAQs" tab, keeping only general Cloudflare WAF bypass guidelines and FAQ sections (implemented by Shashi).

## [1.2.30] - 2026-06-12
### Changed
- Added a new dedicated "Help & FAQs" tab (`settings-help` panel) in the settings interface, decluttering the main settings views (implemented by Shashi).
- Relocated the Cloudflare Security Guidance Notice and Webhook Platform URLs configuration guides from the Incoming Webhooks tab to the Help & FAQs tab (implemented by Shashi).
- Added direct contextual help reference links under the Incoming Webhooks settings to auto-switch focus to the Help & FAQs panel (implemented by Shashi).
- Added an FAQ section covering smart duplicate detection, platform stage mapping exclusions, and Google Click ID validation rules (implemented by Shashi).

## [1.2.29] - 2026-06-12
### Changed
- Refactored settings page tab switching logic to pass DOM element references (`this`) instead of browser events, resolving tab initialization issues and ensuring "General Settings" is always active by default on fresh loads (implemented by Shashi).

## [1.2.28] - 2026-06-12
### Fixed
- Fixed an issue where the PHP active tab detection code was printed as plain text due to an incorrectly placed PHP closing tag (implemented by Shashi).

## [1.2.27] - 2026-06-12
### Changed
- Reorganized the cluttered admin settings page into 6 distinct, well-structured tabs using client-side JavaScript to prevent data loss while editing, and saved active tab persistence on save (implemented by Shashi).
- Integrated hash-based URL routing (e.g. `#tab-capi`) to support direct linking/bookmarking of settings panels (implemented by Shashi).
- Hidden the global "Save Settings" button on the "Danger Zone" tab to prevent user confusion and focus the reset actions (implemented by Shashi).
- Incremented the plugin version across constants, headers, and readme files to 1.2.27 (implemented by Shashi).

## [1.2.26] - 2026-06-12
### Changed
- Added customizable stage-to-event mappings for Meta Ads CAPI, Google Ads OCI/CAPI, and LinkedIn Ads CAPI (implemented by Shashi).
- Introduced dynamic mapping tables in the admin settings screen rendering all custom sales stages with inputs for platform-specific event/conversion names (implemented by Shashi).
- Enforced platform exclusion rules: conversions for stages with empty/blank mapping names are skipped, and Google OCI feed/CSV excludes unmapped stages entirely (implemented by Shashi).

## [1.2.25] - 2026-06-12
### Changed
- Added leads database tracking columns for landing page URL (`landing_page`) and stage-conversion timestamp (`conversion_time`) (implemented by Shashi).
- Mapped CRM stage conversion lifecycles to Google Ads standard conversion actions: Qualified -> 'CRM Qualified Lead', Consultation Booked -> 'Consultation Booked', Site Visit -> 'Site Visit Booked', and Won -> 'Closed Won Interior Project' (implemented by Shashi).
- Updated cookie retrieval and custom webhook logic to merge cookie-captured parameters for frontend website form submissions (implemented by Shashi).
- Extended CSV templates, lead drawer views, dynamic mapping forms, standard CSV exports, and CSV import parser logic to support landing page and conversion timestamp attributes (implemented by Shashi).
- Incremented the plugin version across constants, headers, and readme files to 1.2.25 (implemented by Shashi).

## [1.2.24] - 2026-06-12
### Changed
- Added full support for Google Ads Offline Conversion Imports (OCI) by storing Google Click ID (gclid), Google App Click ID (gbraid), and Google Web Click ID (wbraid) dynamically (implemented by Shashi).
- Updated front-end cookie script, API lead webhook parser, CSV import templates, lead drawer views, and standard CSV exports to include gbraid and wbraid click ID attributes (implemented by Shashi).
- Integrated dynamic, secure REST API conversions feed CSV output under the path `/wp-json/interior-design-lead-manager/v1/google-ads-conversions?api_key=...` for direct import scheduling inside Google Ads, and added a manual download button in settings (implemented by Shashi).
- Implemented strict platform-specific conversion guards that skip uploading to Google if there are no Google click IDs (gclid, gbraid, or wbraid) or if the lead is not a Google-related source, and prevent cross-platform conversion leakage to LinkedIn (implemented by Shashi).
- Incremented the plugin version across constants, headers, and readme files to 1.2.24 (implemented by Shashi).

## [1.2.23] - 2026-06-11
### Changed
- Cleaned up remaining inline styling references to the 'Outfit' font family in HTML markup structures, changing them to 'Inter' to maintain consistent typography (implemented by Shashi).
- Incremented the plugin version across constants, headers, and readme files to 1.2.23 (implemented by Shashi).

## [1.2.22] - 2026-06-11
### Changed
- Stored font assets locally and registered them via a local stylesheet to optimize loading speeds and eliminate external enqueues (implemented by Shashi).
- Normalised `callback_datetime` changes using parsed Unix timestamps in the lead update logic to avoid false-positive duplicate activity log updates on save (implemented by Shashi).
- Map empty/cleared datetime picker inputs to `null` to ensure consistent data representations (implemented by Shashi).
- Incremented the plugin version across constants, headers, and readme files to 1.2.22 (implemented by Shashi).

## [1.2.21] - 2026-06-11
### Changed
- Standardized the entire user interface typography on the Inter font stack, removing all enqueues and style declarations associated with the Outfit font family (implemented by Shashi).
- Incremented the plugin version across constants, headers, and readme files to 1.2.21 (implemented by Shashi).

## [1.2.20] - 2026-06-11
### Changed
- Configured a document keydown listener to capture the Escape (Esc) key, closing active pop-ups, modals, and the details drawer in layering sequence (implemented by Shashi).
- Integrated change and input event listeners on `#idlm-edit-lead-form` to track unsaved edits and keep a log of which fields were modified (implemented by Shashi).
- Built a custom "Unsaved Changes" confirmation modal listing the modified fields and providing options to "Save & Exit", "Discard & Exit", or "Keep Editing" (implemented by Shashi).
- Incremented the plugin version across constants, headers, and readme files to 1.2.20 (implemented by Shashi).

## [1.2.19] - 2026-06-11
### Changed
- Converted all date/time output values from 24-hour format to 12-hour format with AM/PM indicators globally (implemented by Shashi).
- Modified the default parameters of `format_datetime()` to output `'M d, Y h:i A'` (implemented by Shashi).
- Updated callback scheduled overdue notification logs to use `'M d, h:i A'` formatting (implemented by Shashi).
- Incremented the plugin version across constants, headers, and readme files to 1.2.19 (implemented by Shashi).

## [1.2.18] - 2026-06-11
### Changed
- Removed the padded `.idlm-card` wrapping element around the lead table in `includes/class-admin.php`, allowing the table content to layout directly in the workspace grid (implemented by Shashi).
- Transferred border-radius, background, shadow, and margin-bottom presentation rules directly onto the `.idlm-table-wrapper` container in `assets/css/admin-style.css` for a premium edge-to-edge table alignment (implemented by Shashi).
- Integrated CSS variables for table cell borders and text colors, configured custom hover states for row backgrounds, and eliminated the final row's bottom border to prevent double lines (implemented by Shashi).
- Incremented the plugin version across constants, headers, and readme files to 1.2.18 (implemented by Shashi).

## [1.2.17] - 2026-06-11
### Changed
- Configured default relative positioning on drawer and modal headers to support absolute positioning for nested close buttons on mobile devices without layout issues (implemented by Shashi).
- Adjusted media query styling for modal headers under 700px viewport width, establishing responsive padding constraints (implemented by Shashi).
- Removed inline font size styling overrides on the Callback Scheduler and Deal Value Input modals' close buttons to standardize them globally (implemented by Shashi).
- Incremented the plugin version across constants, headers, and readme files to 1.2.17 (implemented by Shashi).

## [1.2.16] - 2026-06-11
### Changed
- Adapted close buttons (`.idlm-close`) to render with a general layout and hover transition, applying absolute top-right positioning on mobile query dimensions across both drawers and modals (implemented by Shashi).

## [1.2.15] - 2026-06-11
### Changed
- Styled the leads list table pagination toolbar (`.idlm-pagination`, `.idlm-pagination-btn`, and `.idlm-pagination-info`) in `assets/css/admin-style.css` to match Q Oak Interiors CRM mockup controls (implemented by Shashi).
- Programmed premium rounded borders (`border-radius: 999px`), shadows, and hover transition states for pagination buttons, including disabled state visual overrides (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.15` (implemented by Shashi).

## [1.2.14] - 2026-06-11
### Changed
- Aligned global CSS variables (`:root` tokens) in `assets/css/admin-style.css` to match the mockup's colors, background hues, font stack, borders, shadows, and margins (implemented by Shashi).
- Updated `.idlm-card` rules to use mapped variables instead of hardcoded hex values, automatically propagating the mockup theme design (implemented by Shashi).
- Enabled the updated color theme and typography tokens globally across the entire CRM plugin workspace including dashboard layouts, table list views, and settings panels (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.14` (implemented by Shashi).

## [1.2.13] - 2026-06-11
### Changed
- Configured custom SVG dropdown chevron arrows for all select elements under the plugin namespace (`.idlm-wrap select`, `.idlm-drawer select`, `select.idlm-table-select`) in `assets/css/admin-style.css` using URL-encoded SVG background images (implemented by Shashi).
- Disabled standard browser select styles (`appearance: none`, `-webkit-appearance: none`, `-moz-appearance: none`) to ensure uniform dropdown arrows across all operating systems and web browsers (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.13` (implemented by Shashi).

## [1.2.12] - 2026-06-11
### Changed
- Removed default `overflow: hidden` from `.idlm-drawer-tab-content` and set `overflow: visible !important` on active tabs in `assets/css/admin-style.css`. This resolves a layout limitation where hidden intermediate overflow ancestors blocked CSS `position: sticky` on nested panels (implemented by Shashi).
- Verified that sticky positioning now applies correctly for both `.idlm-drawer-activity` (Details tab sidebar comments panel) and `.idlm-drawer-activity-full > section:first-child` (Activity Log tab Add Comment panel) during viewport scroll (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.12` (implemented by Shashi).

## [1.2.11] - 2026-06-11
### Changed
- Moved the lead form footer element (`.idlm-drawer-form-footer`) outside of the scrollable `.idlm-drawer-body` container in `includes/class-admin.php`, allowing the flex container layout to naturally hold it static at the bottom of the modal popup (implemented by Shashi).
- Configured the save details submit button in the footer to target the forms ID via the HTML5 `form` attribute (`form="idlm-edit-lead-form"`) to maintain standard browser validation and submission from outside the form element (implemented by Shashi).
- Cleaned up the temporary CSS sticky properties and coordinate offsets from `.idlm-drawer-form-footer` in `assets/css/admin-style.css` (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.11` (implemented by Shashi).

## [1.2.10] - 2026-06-11
### Changed
- Implemented a sticky drawer form footer at the bottom of the details tab scroll viewport with edge-to-edge layout offsets (implemented by Shashi).
- Configured the first panel (Add Note / Comment) of the Activity Log tab to be sticky on desktop, keeping it visible as the user scrolls down the timeline history (implemented by Shashi).
- Disabled sticky layouts for both panels in mobile viewport media queries to preserve screen space (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.10` (implemented by Shashi).

## [1.2.9] - 2026-06-11
### Changed
- Filtered details tab sidebar 'Recent Comments' section to display comments only, excluding general system pipeline activity logs (implemented by Shashi).
- Limited comments in the Details tab sidebar to show only the 5 most recent comments (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.9` (implemented by Shashi).

## [1.2.8] - 2026-06-11
### Changed
- Added desktop/mobile layout overrides, footer styling, and mobile responsive media queries from the mockup file to fix the mobile layout views for lead profile drawers (implemented by Shashi).
- Mapped activity log layout grid using namespaced `.idlm-drawer-activity-full` selector to fix the mismatch between PHP container template class and style classes (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.8` (implemented by Shashi).

## [1.2.7] - 2026-06-11
### Changed
- Cleaned up old drawer layout media query overrides in `assets/css/admin-style.css` to prevent mobile display squishing and ensure the new Q Oak Interiors full-screen responsive layout renders correctly on mobile devices (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.7` (implemented by Shashi).

## [1.2.6] - 2026-06-11
### Changed
- Updated the layout and style of the lead details pop-up modal to match the premium Q Oak Interiors design mockup layout, status grids, and tabs (implemented by Shashi).
- Refactored `#idlm-lead-drawer` container wrapper in `includes/class-admin.php` to load its header, tabs, and body dynamically via AJAX to support dynamic status parameters (implemented by Shashi).
- Refactored `ajax_get_lead_details()` PHP AJAX endpoint in `includes/class-admin.php` to output the new namespaced mockup layout, featuring a 5-item status grid, side-by-side details, campaign attribution parameters, and recent comments sidebar (implemented by Shashi).
- Refactored JavaScript drawer loading, tab toggling, and comment saving controllers in `assets/js/admin-app.js` to support loading the entire modal dynamically, updating both comments timeline views, and saving new comments from the Details tab sidebar (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.6` (implemented by Shashi).

## [1.2.5] - 2026-06-11
### Added
- Added strict response body JSON parsing validations for Meta (`fire_meta_capi`) and LinkedIn (`fire_linkedin_capi`) Conversions API endpoints in `includes/class-capi.php` to identify token and validation errors and log them correctly as `failed` (implemented by Shashi).
- Implemented CAPI manual resend helper `retry_capi_log()` inside `includes/class-capi.php` supporting force-resending (bypassing duplicate checks) of logged events (implemented by Shashi).
- Registered the `idlm_retry_capi_events` AJAX endpoint and implemented `ajax_retry_capi_events()` in `includes/class-admin.php` supporting bulk CAPI resends (implemented by Shashi).
- Updated the CAPI logs rendering page in `includes/class-admin.php` with checkbox rows, a select-all toggle, a "Retry/Send Selected Events" bulk toolbar, and jQuery AJAX coordinators to handle resending events (implemented by Shashi).
### Changed
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.5` (implemented by Shashi).

## [1.2.4] - 2026-06-11
### Added
- Added a Cloudflare "Under Attack" Mode & Security Blocks Guidance box in the incoming ad webhook setup panel inside `includes/class-admin.php` to provide administrators with step-by-step instructions on bypassing Cloudflare edge challenge pages for lead sync webhooks (implemented by Shashi).
### Changed
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.4` (implemented by Shashi).

## [1.2.3] - 2026-06-11
### Added
- Added new "Test Connection" functionality to the Admin Settings page to validate platform API credentials (implemented by Shashi).
- Registered the `idlm_test_api_connection` AJAX action and implemented `ajax_test_api_connection()` inside `includes/class-admin.php` supporting live validation checks for Meta Graph API, LinkedIn ad segments, and Google Ads customer match lists (implemented by Shashi).
- Added a "Test Meta CAPI Connection" button under the Meta CAPI settings panel and a "Test" button to each custom audience batch sync rule row (implemented by Shashi).
- Programmed click handlers in `assets/js/admin-app.js` to trigger real-time connection test AJAX requests and display verification statuses via toasts (implemented by Shashi).
### Changed
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.3` (implemented by Shashi).

## [1.2.2] - 2026-06-11
### Changed
- Fixed incorrect `wpdb::prepare` query warning by converting the raw static Call Status string check inside the overall follow-up count query to use the standard `%s` query argument placeholder (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.2` (implemented by Shashi).

## [1.2.1] - 2026-06-11
### Changed
- Removed today's date-range filter from the "New Leads" and "Follow-up Today" metrics summary cards in `includes/class-admin.php` to calculate and display overall totals for these cards instead of today-only counts (implemented by Shashi).
- Updated "New Leads" hint to "overall" and renamed "Follow-up Today" card to "Follow-up" with the hint "overall" (implemented by Shashi).
- Modified the click filter handler in `assets/js/admin-app.js` for the "Follow-up" card to filter directly by "Call Back Requested" Call Status in the primary row instead of filtering by overdue Follow-up Status (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.1` (implemented by Shashi).

## [1.2.0] - 2026-06-11
### Changed
- Converted primary filters row container layout from display grid to display flex to allow the search input to span more width (using flex-grow factor of 2) and floated the buttons group ("More Filters" and "Clear") to the far right using `margin-left: auto` (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.2.0` (implemented by Shashi).

## [1.1.9] - 2026-06-11
### Changed
- Reduced the width of the "More Filters" and "Clear" buttons inside the primary filters row by changing their inline width properties to `auto` and adding CSS overrides to set `width: auto !important; max-width: max-content !important;` (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.1.9` (implemented by Shashi).

## [1.1.8] - 2026-06-11
### Changed
- Explicitly configured `.idlm-file-link` instances to open in a new tab via a click event listener in `assets/js/admin-app.js` (implemented by Shashi).
- Hardcoded any remaining inline style CSS variables inside `includes/class-admin.php` and `assets/js/admin-app.js` to ensure the tool styling is fully hardcoded and independent of the theme settings (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.1.8` (implemented by Shashi).

## [1.1.7] - 2026-06-11
### Changed
- Bulk-hardcoded all colors, borders, shadows, backgrounds, text-colors, transitions, and layout properties across the entire `assets/css/admin-style.css` stylesheet using hex/rgba values and `!important` declarations, completely isolating the CRM tool styling from any default/custom active WordPress admin themes (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.1.7` (implemented by Shashi).

## [1.1.6] - 2026-06-11
### Changed
- Removed the custom padding from the main `.idlm-wrap` wrapper in `assets/css/admin-style.css` by setting it to `padding: 0 !important;` (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.1.6` (implemented by Shashi).

## [1.1.5] - 2026-06-11
### Changed
- Configured the main `.idlm-wrap` wrapper in `assets/css/admin-style.css` to span the full container or screen width dynamically by setting it to `width: 100%; max-width: 100%; box-sizing: border-box;` (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.1.5` (implemented by Shashi).

## [1.1.4] - 2026-06-11
### Changed
- Removed the custom background color property from the main `.idlm-wrap` plugin wrapper in `assets/css/admin-style.css`, letting it blend seamlessly with the default WordPress dashboard background color (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.1.4` (implemented by Shashi).

## [1.1.3] - 2026-06-11
### Changed
- Renamed the header's primary action button from "Just Add Lead" to "+ Add Lead" (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.1.3` (implemented by Shashi).

## [1.1.2] - 2026-06-11
### Changed
- Hardcoded the signature gold branding theme colors for primary/secondary buttons and metrics summary cards in `assets/css/admin-style.css` using `!important` declarations, ensuring style consistency across all WordPress color configuration themes (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.1.2` (implemented by Shashi).

## [1.1.1] - 2026-06-11
### Changed
- Configured the pending calls pill click handler to reset other filters and automatically expand the secondary filters drawer to show the active "Pending Calls" Follow-up Status filter state, preventing agent confusion (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.1.1` (implemented by Shashi).

## [1.1.0] - 2026-06-11
### Changed
- Renamed the header's primary action button from "+ Add Lead" to "Just Add Lead" (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.1.0` (implemented by Shashi).

## [1.0.99] - 2026-06-11
### Changed
- Redesigned the design variables of the CRM theme in `assets/css/admin-style.css` using the Q Oak Interiors style reference, switching primary accents to gold (`#c99644`) and gold-dark (`#9f6f25`), updating shadows, and soft neutral background shades (implemented by Shashi).
- Integrated a dynamic Metrics Summary Grid (Lead Summary) above the filters toolbar displaying counts for New, Not Called, Follow-up, Unassigned, and Qualified leads, with interactive jQuery filtration triggers (implemented by Shashi).
- Restructured the CRM filter toolbar into a primary row (Search, Date, Stage, Call Status, Assigned Rep, More Filters, and Clear) and a secondary collapsible panel (Channel Source, Quality, Follow-up) to optimize vertical scanning and usability (implemented by Shashi).
- Improved the look, feel, and usability of the lead list database table: styled headers as uppercase bold, styled rows with custom hover transitions and cell alignments, bolded budget values, and polished cell hierarchy (implemented by Shashi).
- Redesigned inline table selectors and focus outlines on input form fields and textareas to use gold accent rings and gold box-shadow offsets on focus (implemented by Shashi).
- Redesigned source and status badges to use pill layouts (rounded 999px borders) and soft, harmonic color configurations (implemented by Shashi).
- Polished the detailed profile modal drawer with soft gold background indicators on active tab triggers (implemented by Shashi).
- Renamed the header's primary action button from "+ Add Manual Lead" to "+ Add Lead", positioning it on the far right of the actions list, and removed the bottom margin from the header class to optimize spacing (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.0.99` (implemented by Shashi).

## [1.0.98] - 2026-06-10
### Changed
- Configured the `.idlm-filters-compact-grid` container to take up `width: 100%` of the parent card space (implemented by Shashi).
- Styled the "Clear" action button as a full-width flexbox item within its grid cell to stretch and align symmetrically with other inputs and select dropdowns (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.0.98` (implemented by Shashi).

## [1.0.97] - 2026-06-10
### Changed
- Redesigned the CRM filters panel to use a single compact horizontal bar instead of collapsible rows, grouping all 8 filters in a tight, space-efficient grid with reduced input heights, padding, and labels (implemented by Shashi).
- Positioned the "Clear" action button inline at the end of the compact filters row for instant filter reset (implemented by Shashi).
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.0.97` (implemented by Shashi).

## [1.0.96] - 2026-06-10
### Added
- Simplified the CRM filter layout by introducing a collapsible "Advanced Filters" panel, keeping Search, Date, and Stage always visible while grouping the remaining five filters (Channel, Rep, Call Status, Quality, and Follow-up) under a smooth accordion toggle (implemented by Shashi).
- Added a "Clear Filters" action button inside the primary filters panel to quickly reset all filter elements back to default states and trigger table refresh (implemented by Shashi).
### Changed
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.0.96` (implemented by Shashi).

## [1.0.95] - 2026-06-10
### Added
- Enabled a Search Lead option in the Filters Panel on the main CRM admin screen to dynamically search leads by phone number, email, or name via AJAX (implemented by Shashi).
- Updated the front-end AJAX loader script `assets/js/admin-app.js` with a 300ms keypress/input debounce to prevent excessive database calls while typing (implemented by Shashi).
### Changed
- Incremented the plugin version and stable tag references across `interior-design-lead-manager.php` and `readme.txt` to `1.0.95` (implemented by Shashi).

## [1.0.94] - 2026-06-10
### Changed
- Deleted hidden `.DS_Store` files in the plugin directories to resolve the `hidden_files` block error in the Plugin Check validator (implemented by Shashi).
- Updated metadata in `readme.txt` to sync the Stable Tag to `1.0.94` (implemented by Shashi).

## [1.0.93] - 2026-06-10
### Changed
- Added phpcs annotations to suppress false positive warnings regarding `$_COOKIE` sanitization and REST routing nonces inside `includes/class-integrations.php` (implemented by Shashi).
- Updated the plugin `Tested up to` compatibility target to `7.0` and bumped the Stable Tag metadata in `readme.txt` to version `1.0.93` to satisfy Plugin Check header validation constraints (implemented by Shashi).

## [1.0.92] - 2026-06-10
### Changed
- Replaced discouraged `parse_url()` usage with WordPress standard `wp_parse_url()` in `includes/class-integrations.php` to resolve PHP version incompatibility warnings (implemented by Shashi).
- Properly unslashed and sanitized `$_COOKIE` and `$_SERVER` environment variables (`REQUEST_URI`, `REMOTE_ADDR`, `HTTP_USER_AGENT`) prior to utilization in `includes/class-integrations.php` and `includes/class-capi.php` (implemented by Shashi).
- Updated the plugin metadata in `readme.txt` to sync the Stable Tag to `1.0.92` and increase the "Tested up to" WordPress version target to `6.6` (implemented by Shashi).

## [1.0.91] - 2026-06-10
### Added
- Integrated a 5-minute delay buffer for post-ingestion Conversions API (CAPI) events (Qualified, Quote Shared, Won) via Action Scheduler to act as an "undo" window for sales representatives (implemented by Shashi).
- Programmed a real-time verification check in `process_capi_event()` inside `includes/class-capi.php` that verifies the lead's current database state matches the expected criteria before transmitting events, preventing premature CAPI uploads for leads that are corrected/disqualified by representatives within the 5-minute buffer (implemented by Shashi).

## [1.0.90] - 2026-06-10
### Added
- Integrated Conversions API (CAPI) event deduplication across all supported platforms: Meta, Google, and LinkedIn (implemented by Shashi).
- Programmed a log-checking mechanism `is_event_already_sent()` in `includes/class-capi.php` to prevent redundant transmissions of the same lead event stage (such as Qualified/Quote Shared/Won) when representatives change lead properties multiple times (implemented by Shashi).

## [1.0.89] - 2026-06-10
### Added
- Integrated initial `Lead` event uploads for the server-side Conversions API (CAPI) on real-time lead ingestion to improve lead coverage and data accuracy (implemented by Shashi).
- Programmed `insert_lead()` inside `includes/class-db.php` to trigger `do_action('idlm_lead_stage_converted', ...)` for new, non-duplicate leads with the stage set to `New` (which resolves to event name `Lead` in CAPI payload) (implemented by Shashi).

## [1.0.88] - 2026-06-10
### Added
- Added Yesterday, Last 7 Days, Last 30 Days, and This Year options to the analytics dashboard period selection dropdown (implemented by Shashi).
- Added calculations for the new period options (yesterday, last_7_days, last_30_days, year/this_year) in the dashboard date range generator `get_date_ranges()` to correctly filter metrics and compute period-over-period growth (implemented by Shashi).

## [1.0.87] - 2026-06-10
### Added
- Added Yesterday, Last 7 Days, Last 30 Days, Last Month, and This Year options to the "Date Added" filter dropdown on the Leads list table panel (implemented by Shashi).
- Integrated local WordPress timezone-aware database query boundaries for the new date filters in the Leads table AJAX hydrator (implemented by Shashi).

## [1.0.86] - 2026-06-10
### Changed
- Fixed viewport overflow bug for CRM modal contents on mobile devices (implemented by Shashi).
- Constrained `.idlm-modal-content` globally to a `max-height: 90vh` and added `overflow-y: auto` to enable internal scrolling for long forms (such as manual lead creations or CSV imports).
- Configured `.idlm-modal-content` on mobile screen sizes <= 600px to expand up to `max-height: 95vh` and `max-width: 95%` to leverage full display height.

## [1.0.85] - 2026-06-10
### Changed
- Fixed cramped layout of `.idlm-header-actions` buttons and filters on mobile devices (implemented by Shashi).
- Programmed header actions container and children (buttons, select dropdown, date pickers, pending calls pill) to wrap cleanly and scale flexibly (`flex: 1 1 auto; min-width: 120px;`) on mobile screen widths (max-width: 768px).
- Enabled vertical stacking and wrapping for the custom date range picker selectors on mobile.

## [1.0.84] - 2026-06-10
### Changed
- Optimised all frontend views for mobile devices (implemented by Shashi).
- Improved Leads drawer/modal view on mobile screen widths (width: 95vw, height: 90vh, reduced padding, 1-column layouts for fields and forms).
- Stacked the bulk operations action bar vertically on screen widths <= 768px for clean rendering.
- Added a fallback padding-left on mobile for the main content wrapper `.idlm-wrap` to prevent content touching screen edges in frontend shortcode renders.
- Refactored the dashboard tabs header to scroll horizontally when tabs overflow on mobile screens.
- Re-engineered the conversion pipeline funnel widget to render as a vertical stack with downward-pointing connectors on mobile screen sizes (max-width: 600px).

## [1.0.83] - 2026-06-10
### Changed
- Integrated overall lead counts directly into the labels of all dashboard charts (implemented by Shashi).
- Appended overall total lead counts to the X/Y axes labels of all bar charts (Channel stacked chart, UTM campaign stacked chart, Pipeline Stages chart, Property Types chart, and Room Configuration chart).
- Appended counts directly to the legend labels of all doughnut charts (Call Status, Preferred Studio, and Lead Quality).
- Implemented a custom Chart.js center text plugin to display "Total Leads" and the bold total count dynamically inside the center of all doughnut charts.
- Optimized doughnut chart layouts using a premium cutout ratio of 70% and cleaned up tooltip label generation callbacks to prevent redundant count suffixes.

## [1.0.82] - 2026-06-10
### Added
- Integrated a new Custom Audience Batch Sync logs table (`wp_idlm_audience_sync_logs`) and a dedicated "Audience Sync Logs" subpanel in the administration panel to record and inspect daily sync jobs (implemented by Shashi).
- Included the new audience logs table inside the secure CRM leads database reset/truncation mechanism (implemented by Shashi).

### Changed
- Refactored all datetime outputs in the administration interface (Leads table "Created At" column, Duplicate leads lists, Activity Log comments timeline, and Conversion API logs) to consistently format dates in the WordPress timezone settings using `wp_date()` and timezone offset (implemented by Shashi).

## [1.0.81] - 2026-06-09
### Removed
- Removed the redundant 'Qualified Lead Rate by Channel (%)' chart and card from the Marketing dashboard (implemented by Shashi).
- Cleaned up backend SQL database queries and API response fields for `$channel_rates` to optimize dashboard data fetching and improve page load performance (implemented by Shashi).

### Changed
- Rendered the 'Lead Quality by Channel' stacked bar chart inside a full-width `.idlm-card` wrapper, giving it a spacious and premium layout (implemented by Shashi).

## [1.0.80] - 2026-06-08
### Fixed
- Fixed stacked chart tooltip calculations (Lead Quality by Channel and UTM campaigns) to display percentages relative to the individual channel/campaign's overall volume rather than the entire account's leads volume (implemented by Shashi).

## [1.0.79] - 2026-06-08
### Fixed
- Fixed Chart.js tooltip configuration by wrapping the label callback inside the callbacks nested object, enabling dynamic percentage breakdowns to render successfully on dashboard hovers (implemented by Shashi).

## [1.0.78] - 2026-06-08
### Added
- Added dynamic percentage calculations relative to overall totals inside the hover tooltips for all dashboard charts, handling rate-based data gracefully (implemented by Shashi).

## [1.0.77] - 2026-06-08
### Fixed
- Fixed Overall Lead Quality Distribution doughnut chart rendering size bug inside hidden tab containers by triggering window resize events on tab switcher transitions (implemented by Shashi).
- Fixed Lead Quality by Channel bar chart split matching bug by forcing integer casting in PHP and case-insensitivity on database columns (implemented by Shashi).

## [1.0.76] - 2026-06-08
### Added
- Integrated premium Lead Quality Distribution Analytics (Overall Doughnut, stacked Channel Quality, and stacked UTM Campaign Quality charts) to the dashboard (implemented by Shashi).
- Added Qualified and Unqualified lead counts as performance columns to the Sales Representatives Performance table (implemented by Shashi).

### Changed
- Converted the channel leads bar chart to a stacked bar chart displaying volume breakdown of Qualified, Unqualified, and Pending leads (implemented by Shashi).
- Converted UTM campaign/source/medium attribution bar charts to horizontal stacked bar charts displaying quality dimensions (implemented by Shashi).
- Adjusted Studio Branch Conversion Performance table to display as a clean, full-width dashboard widget below studio distribution doughnut (implemented by Shashi).

## [1.0.75] - 2026-06-08
### Added
- Added new AJAX endpoint `idlm_get_lead_activity` to retrieve timeline activity logs dynamically on-demand (implemented by Shashi).

### Changed
- Optimized detailed lead popup loading performance by lazy-loading the timeline and activity log when switching to the Activity tab, reducing initial server response overhead and page rendering latency (implemented by Shashi).
- Optimized comment creation, inline updates, and deletion actions to only refresh the activity log timeline view instead of reloading the entire lead drawer details form (implemented by Shashi).

### Fixed
- Added a database B-Tree index on the `duplicate_of_id` column inside the CRM Leads table (`wp_idlm_leads`) to prevent full table scans when checking for duplicates of a lead (implemented by Shashi).

## [1.0.74] - 2026-06-08
### Fixed
- Prevented text wrapping inside channel source badges by setting `white-space: nowrap` inside the `.idlm-badge` class styles. This ensures badges like 'Meta Ads' render in a single line, fixing column vertical stretch issues (implemented by Shashi).

## [1.0.73] - 2026-06-08
### Fixed
- Fixed blank rendering/access restricted block error on the CRM Leads list and Dashboard for representative users with 'agent' or 'agentadmin' roles by adding them to the allowed rep access roles array list (implemented by Shashi).

## [1.0.72] - 2026-06-08
### Fixed
- Fixed a Javascript syntax parsing error in `admin-app.js` caused by a missing closing bracket in the comment delete click handler, which was preventing the table loading scripts from executing and rendering leads (implemented by Shashi).

## [1.0.71] - 2026-06-08
### Added
- Added interactive filter triggering when clicking on the header "Pending Calls" pill badge, updating the leads list table to dynamically show only the leads that require calling/following up based on active rules (implemented by Shashi).

## [1.0.70] - 2026-06-08
### Fixed
- Fixed bug where call statuses (like 'No Answer') removed from Followup Reminder Settings were still counted inside the pending calls notification. Modified `get_pending_calls_count` to dynamically fetch statuses from active reminder rules alongside the default 'Not Called' baseline status (implemented by Shashi).

## [1.0.69] - 2026-06-08
### Changed
- Standardized the font family and font size across the lead details drawer table td fields (including text, input, dropdown select, textarea, and code blocks) to a clean, readable monospace code styling (Monaco 12px) for improved visual consistency and alignment (implemented by Shashi).

## [1.0.68] - 2026-06-08
### Fixed
- Fixed a bug where comment deletion failed due to jQuery `.data('id')` lookup caching issues on dynamically rendered/reloaded elements. Replaced with direct DOM `.attr('data-id')` retrieval to always fetch the latest ID.
- Hardened AJAX response parsing to prevent client-side JS TypeError crashes if the server returns non-object strings (e.g. `-1` on session timeout) (implemented by Shashi).

## [1.0.67] - 2026-06-08
### Added
- Added distinct background and text color coding styling for individual lead source badges (Meta Ads, Google Ads, LinkedIn Ads, Website, Referral, Offline, CSV) to improve visual scanning in the CRM leads list and details drawer (implemented by Shashi).

## [1.0.66] - 2026-06-08
### Added
- Added edit and delete actions to manual comments/notes within the lead details activity timeline, providing inline editing with a custom styled textarea and cancel/save controls, along with delete confirmation prompting (implemented by Shashi).

## [1.0.65] - 2026-06-08
### Changed
- Increased the height of the activity log comment textarea input (`#idlm-comment-text`) to `200px` for improved legibility and entry space (implemented by Shashi).

## [1.0.64] - 2026-06-08
### Changed
- Removed the large `.idlm-drawer-profile` section from the lead details drawer layout to save vertical viewing space (implemented by Shashi).
- Relocated the lead avatar, full name, and channel source badge directly into the sticky `.idlm-drawer-header` wrapper, populated dynamically via AJAX.
- Relocated duplicate lead warning alerts to the top of the Details tab form body inside the drawer.

## [1.0.63] - 2026-06-08
### Added
- Integrated Value-Based Conversions API (CAPI) events for Meta, Google GA4 Measurement Protocol, and LinkedIn CAPI (implemented by Shashi).
- Added a custom HTML popup/modal (`#idlm-deal-value-modal`) to prompt sales representatives for the project deal value when a lead is marked as "Won".
- Added a `Deal Value (INR)` numeric input field to the Leads profile drawer Details tab.
- Added database schema migration supporting a new `deal_value` decimal column in the CRM leads table (`wp_idlm_leads`), defaulting to INR currency.
- Extended the CSV Importer schema fuzzy matcher to parse and map `deal_value` columns from CSV files.

## [1.0.62] - 2026-06-08
### Changed
- Refactored the Activity Log & Comments tab content in the lead details popup modal into a modern 2-column layout (implemented by Shashi). The left column holds the comment textarea form and submission button at a fixed width of `350px`, while the right column displays the scrollable, fully aligned activity log history.
- Added responsive styling and stacking capabilities for smaller/tablet screens (max-width: 991px).
- Programmed client-side state preservation in JavaScript to retain the active tab context when reloading details for the same lead (e.g. after submitting a comment or saving details).

## [1.0.61] - 2026-06-08
### Fixed
- Fixed background page scroll leak and scroll chaining (scroll leakage) when modal popups or drawers are active. Added CSS body-locking class toggling on open/close triggers and set `overscroll-behavior: contain` on all scrollable modal/drawer inner elements (implemented by Shashi).

## [1.0.60] - 2026-06-08
### Changed
- Relocated the Leads Over Time line/area chart widget to display right below the Marketing KPIs statistics grid for improved visual structure (implemented by Shashi).

## [1.0.59] - 2026-06-08
### Added
- Integrated Leads Over Time line/area chart widget to visualize daily lead generation trends over the selected period.
- Integrated Preferred Studio Location distribution doughnut chart and Studio Branch Conversion Performance table to track branch-level project traction and won rates.
- Integrated Property Type and Room Configuration (BHK) horizontal and vertical bar charts to analyze product mix and target preferences.
- Added detailed Avg Speed to Contact and Avg Speed to Close metric columns to the Sales Representatives Performance table (implemented by Shashi).

## [1.0.58] - 2026-06-08
### Fixed
- Fixed REST webhook auth bypass vulnerability in `includes/class-integrations.php` by using strict path matching instead of loose segment substring matching.
- Added representative user existence validation checks before accessing user objects in `includes/class-integrations.php` and `includes/class-db.php` to prevent PHP warnings and crashes when representative accounts are deleted.
- Upgraded the Meta Conversions Graph API version endpoint to `v25.0` in `includes/class-audiences.php` for consistency.
- Corrected custom date range calculations in `includes/class-dashboard.php` by incorporating the local WordPress timezone offset instead of using UTC gmdate bounds.
- Implemented a new role check helper `current_user_has_rep_access()` and integrated capability verification checks across admin rendering methods, frontend shortcodes, and all CRM-related AJAX endpoints in `includes/class-admin.php` to block unauthorized subscriber/customer access (implemented by Shashi).

## [1.0.57] - 2026-06-08
### Fixed
- Fixed the layout display bugs in the 75% screen pop-up. Converted the single-column fields list inside the Details tab into a responsive, multi-column grid layout (preventing large blank space on the right). Styled the Comment form in the Activity Log tab to stretch full-width, aligning the input textarea and submission button properly (implemented by Shashi).

## [1.0.56] - 2026-06-08
### Changed
- Converted the sliding side details drawer into a centered modal popup covering 75% of the screen. Added a sticky header and static footer for quick actions, while keeping the fields section and activity timeline individually scrollable (implemented by Shashi).

## [1.0.55] - 2026-06-07
### Changed
- Increased padding-bottom to 8px in `.idlm-tabs` to add a cleaner spacing gap between the dashboard tab links and the bottom border line (implemented by Shashi).

## [1.0.54] - 2026-06-07
### Fixed
- Fixed a bug where newly inserted webhook leads had an empty stage in the database rather than defaulting to 'New' by explicitly setting the default value on insert.
- Mapped empty or null lead stages to 'New' inside the dashboard queries, resolving the volume mismatch in Pipeline Stage Funnel chart data and Sales Funnel counts (implemented by Shashi).

## [1.0.53] - 2026-06-07
### Changed
- Refactored the Sales Conversion Funnel dashboard widget from a vertical layout to a space-saving horizontal layout, including right-pointing connector transitions with step conversion percentages and vertical liquid height indicator fills (implemented by Shashi).

## [1.0.52] - 2026-06-07
### Added
- Integrated a visual, cumulative Sales Conversion Funnel dashboard widget in the Sales tab to track lead progression and conversion rates across 'New', 'Contacted', 'Qualified', 'Quote Shared', 'Negotiation', and 'Won' stages (implemented by Shashi).

## [1.0.51] - 2026-06-07
### Changed
- Trigger the server-side Conversion API (CAPI) events when Lead Quality changes to Qualified, sending the event corresponding to the 'Qualified' stage (implemented by Shashi).

## [1.0.50] - 2026-06-05
### Added
- Added support for custom representative user roles (`marketing`, `sales_manager`, `lead_owner`, `marketing_manager`, `sales_executive`) alongside standard roles inside rep dropdowns and pools (implemented by Shashi).

## [1.0.49] - 2026-06-05
### Fixed
- Added the missing "Save Settings" form submit button at the bottom of the System Settings page (implemented by Shashi).

## [1.0.48] - 2026-06-05
### Changed
- Filtered all Assigned Rep dropdown select elements (Search Filter, Bulk Actions, Source Mapping, and Table Quick Edit) to only show active representatives configured in System Settings (implemented by Shashi).
- Retained any currently assigned representative in the quick edit dropdown list for that specific lead, preventing data visualization mismatches if a representative becomes inactive.

## [1.0.47] - 2026-06-05
### Added
- Integrated a secure administrative "Danger Zone" Leads & Analytics database reset feature (implemented by Shashi).
- Registered two administrative-only AJAX actions `idlm_get_total_leads_count` and `idlm_reset_crm_data` with strict `manage_options` privilege checks.
- Implemented a 2-step verification mechanism: fetches the real-time total lead count from the server and requires typing the exact count to confirm deletion.
- Wiped custom database tables (`idlm_leads`, `idlm_lead_activity_log`, `idlm_capi_logs`, `idlm_cost_per_lead`) via direct `TRUNCATE TABLE` operations while preserving WordPress configuration options.

## [1.0.46] - 2026-06-05
### Changed
- Optimized leads database query performance inside the administrative dashboard workspace (implemented by Shashi).
- Replaced the $O(N)$ query loops inside the table rendering loop with a single unified `LEFT JOIN` on the WordPress users table (`$wpdb->users`), obtaining all assigned rep display names in one go.
- Optimized user queries by specifying limited `fields` constraints (selecting only `ID` and `display_name`) inside `get_users()` to reduce object hydration overhead.
- Added a new custom database B-tree index on the `lead_quality` column inside the `idlm_leads` custom table to accelerate quality status filters.

## [1.0.45] - 2026-06-05
### Changed
- Replaced the WordPress Media Library pop-up framework with direct HTML5 file inputs (`<input type="file">`) for uploading Floor Plan and Quote documents (implemented by Shashi).
- Registered a secure backend AJAX endpoint `idlm_upload_lead_file` that utilizes the native WordPress `media_handle_upload()` engine to handle file storage securely.
- Upgraded the client-side uploader script in `admin-app.js` to process direct file selection, upload attachments asynchronously via AJAX, and dynamically render progress indicators and links.
- Cleared the heavy WordPress Media script dependency by removing the `wp_enqueue_media()` calls from administrative and frontend enqueues.

## [1.0.44] - 2026-06-05
### Added
- Integrated media upload options for Floor Plan Received and Quote Shared fields inside the leads detailed profile drawer workspace (implemented by Shashi).
- Added new database columns `floor_plan_file` and `quote_file` in the custom CRM Leads Table (`wp_idlm_leads`) to hold the media attachment URLs, running schema updates dynamically.
- Enqueued WordPress Media Library scripts/styles on both administrative backend panels and conditional frontend pages where the leads list shortcode is executed.
- Implemented client-side Media Library triggers in `admin-app.js` supporting direct uploads, replacing/editing uploaded files, removing attachments, and linking to view them in new browser tabs.
- Configured smart UI synchronization that auto-selects "Yes" (1) in the Floor Plan/Quote dropdown selectors when files are uploaded, or "No" (0) when removed.
- Refactored lead data CRUD and timeline comments to write clean activity logs on uploader status transitions, and extended the CSV exporter/importer matrices to support parsing, mapping, and exporting of the file attachment URLs.

## [1.0.43] - 2026-06-05
### Changed
- Aligned CSV upload event name from `QualifiedLead` to `Contact` (implemented by Shashi). This matches the plugin CAPI mappings perfectly to ensure seamless conversion tracking consistency and exact deduplication when files are uploaded.

## [1.0.42] - 2026-06-05
### Fixed
- Patched the conversion uploader to resolve Meta's 62-day age limit rejection error (implemented by Shashi).
- Reconfigured the utility script `generate_meta_upload.py` to output two distinct files:
  - `meta_qualified_leads_upload.csv`: contains 104 genuine leads filtered within the last 62 days (since April 4, 2026), designed specifically for error-free upload to Meta Events Manager.
  - `meta_qualified_leads_upload_all.csv`: contains all 118 genuine leads without age filtering, designed for Meta Custom Audience matching.

## [1.0.41] - 2026-06-05
### Added
- Created the standalone utility script `generate_meta_upload.py` to extract, normalize, and format genuine leads for Meta Offline Conversions/CAPI upload (implemented by Shashi).
- Processed 926 leads from `Q Oak Interiors Leads-New - 2026-2027 (1).csv` to export exactly 118 genuine leads.
- Implemented robust digits-only phone normalization (prepending `91` Indian dialing code to 10-digit numbers), name splitting, budget-to-numeric mapping, and privacy-safe SHA-256 pre-hashing for customer identifiers (email, phone, first/last name, city, country) mapping to Meta standard fields.
- Outputted the final uploadable CSV file `meta_qualified_leads_upload.csv` containing both plain text and hashed columns.

## [1.0.40] - 2026-05-26
### Changed
- Aligned Meta Conversions API (CAPI) events payload with Meta's official CRM integration instruction guide (implemented by Shashi).
- Upgraded Meta CAPI Graph API version endpoint from `v19.0` to the latest `v25.0` standard.
- Encapsulated hashed email (`em`) and phone number (`ph`) identifiers in array wrappers inside `user_data`.
- Cast Meta Lead IDs (`lead_id` in `user_data` payload derived from `fb_lead_id` records) to dynamic numeric values to prevent precision loss.
- Appended mandatory Custom Event metadata (`event_source` set to `crm`, and `lead_event_source` set to `Interior Design Lead Manager`) inside `custom_data` payloads.

## [1.0.39] - 2026-05-26
### Added
- Integrated an interactive, premium-designed **UTM Type Selector Dropdown** inside the analytics dashboard (designed and built by Shashi).
- Re-engineered the backend query loop in `class-dashboard.php` to fetch top 5 allocations for all six UTM dimensions (`utm_campaign`, `utm_source`, `utm_medium`, `utm_adgroup`, `utm_term`, `utm_content`) simultaneously.
- Inserted a sleek select dropdown element in `class-admin.php` inside the card header, styled seamlessly with the custom CRM input system.
- Implemented high-performance client-side caching of UTM datasets inside `dashboard.js` to enable fully instant, zero-latency chart rendering transitions when toggling attributes in the browser.

## [1.0.38] - 2026-05-25
### Fixed
- Resolved the dynamic query runtime notice `Function wpdb::prepare was called incorrectly. The query argument of wpdb::prepare() must have a placeholder` by removing the `$wpdb->prepare` wrapper on queries without placeholders, while maintaining robust static analysis checks via `// phpcs:ignore` tags (implemented by Shashi).

## [1.0.37] - 2026-05-25
### Fixed
- Completed comprehensive security, compliance, and coding standards auditing for the WordPress Plugin Check tool.
- Bundled the `chart.js` library locally in `assets/js/chart.js` and updated the script registry to comply with the WordPress repository guidelines disallowing remote resource offloading.
- Wrapped all direct database queries inside `class-admin.php`, `class-audiences.php`, `class-db.php`, and `class-dashboard.php` using `$wpdb->prepare()`. Added explicit inline PHPCS ignore directives for necessary custom table direct operations and caching bypasses.
- Corrected a typo in the database query PHPCS ignore directives from `InterpolatedNotPrepared` to `NotPrepared` across `class-admin.php` and `class-audiences.php` to satisfy the Plugin Check tool.
- Created a standard and complete `readme.txt` file in the plugin root to comply with the WordPress repository guidelines.
- Rewrote `LIKE` wildcard search patterns to pass the wildcards through a replacement parameter using `$wpdb->esc_like()`.
- Replaced all timezone-affected `date()` function calls with timezone-safe `gmdate()` or `wp_date()` across all modules.
- Patched a critical PHP syntax typo in `class-admin.php` by changing the misplaced `end8: endforeach;` label to standard `endforeach;` (implemented by Shashi).

## [1.0.36] - 2026-05-25
### Fixed
- Fixed the `WordPress.WP.EnqueuedResourceParameters.MissingVersion` warning by passing the `IDLM_VERSION` constant into the `wp_register_style` call for Google Fonts, preventing browser caching issues for assets (implemented by Shashi).

## [1.0.35] - 2026-05-25
### Fixed
- Resolved the invalid plugin URI domain warning by replacing the discouraged "example.com" domain in the plugin header with a unique github repository domain `https://github.com/shashi-protecto/interior-design-lead-manager` (implemented by Shashi).

## [1.0.34] - 2026-05-25
### Added
- Integrated high-performance **Smart Duplicate Leads Matching** algorithm (designed and implemented by Shashi).
- Replaced the exact phone matching criteria with a digits-only (ignoring hyphens, spaces, brackets, formatting symbols) and suffix-based last-10-digits comparison inside `class-db.php::insert_lead()`. This accurately captures duplicate records even when the incoming phone number has a missing international dialing code (e.g., matching local `9876543210` with international format `+919876543210` or vice versa) while maintaining robust database indexing speed.

## [1.0.33] - 2026-05-25
### Added
- Integrated full **Meta Lead ID** parameters tracking (designed and implemented by Shashi).
- Added `fb_lead_id` column to custom CRM Leads Table definition in `class-db.php`.
- Enabled dynamic field mapping configurations for Meta Lead ID under administrative Settings panel in `class-admin.php` and `class-integrations.php`.
- Integrated automatic payload extraction fallbacks mapping `lead_id` and `fb_lead_id` variables instantly on REST API incoming hooks.
- Configured dynamic Leads sliding profile drawer view and CSV export matrices to capture and render Meta Lead IDs.
- Extended the CSV Importer parameters template generator in `admin-app.js` and bulk sanitizers in `class-admin.php` to parse and populate Meta Lead ID values.
- Integrated the captured Meta Lead ID parameters as `'lead_id'` in the Meta Conversions API (CAPI) `user_data` payload inside `class-capi.php` for downstream qualification/won conversions optimization.

## [1.0.32] - 2026-05-25
### Added
- Integrated a premium **CSV Lead Import** workspace (designed and implemented by Shashi).
- Added an HTML-based 4-step wizard modal (`#idlm-import-modal`) supporting file drag-and-drop, dynamic client-side CSV parsing using an RFC 4180-compliant tokenizer, fuzzy auto-mapping of CSV headers to CRM database fields, and a 3-row interactive data preview.
- Enabled administrative configuration options to specify batch default sources, default pipeline stages, default call statuses, and toggle conditional lead assignment routing and platform conversion API (CAPI) events.
- Appended responsive drag-hover highlights, progress fill gradients, and monochromatic status lists to `admin-style.css`.
- Registered the secure `idlm_import_leads_batch` AJAX endpoint in `class-admin.php` that implements nonced authentication, processes recursive chunks of 50 leads per request to prevent server timeout, and sanitizes/normalizes tracking click-IDs, budget ranges, and room counts.

## [1.0.31] - 2026-05-24
### Changed
- Configured the detailed lead workspace drawer header (`.idlm-drawer-header`) to use sticky positioning (`position: sticky; top: 0; z-index: 100;`), keeping it fixed at the top of the side panel during active workspace scrolling operations for a more seamless and professional UX (implemented by Shashi).

## [1.0.30] - 2026-05-24
### Added
- Integrated a dynamic **Dynamic Room Option Matcher & Extractor** system-wide (designed and implemented by Shashi). It dynamically retrieves active rooms configuration options defined in plugin settings and automatically parses, extracts, and matches incoming room counts from both automated webhooks and manual lead insertions or sliding drawer workspace updates, with a robust fallback pattern matching sequence.

## [1.0.29] - 2026-05-24
### Fixed
- Extended the **Intelligent Budget Extractor** to cover manual lead entries (designed and implemented by Shashi). The high-precision extraction is now applied to budget values inputted via the manual lead creation popup form and the detailed lead workspace drawer update form, ensuring system-wide budget normalization consistency.

## [1.0.28] - 2026-05-24
### Added
- Integrated an intelligent **Webhook Budget Extractor** employing a multi-stage regular expression sequence inside `class-integrations.php`. It successfully parses and isolates standard budget ranges or values from verbose, highly descriptive webhook payload fields (e.g. `"Villa - Interiors starting @ ₹15-20 lakhs"` normalized to `"₹15-20 lakhs"`, `"Apartment starting @ $15k - $20k"` to `"$15k - $20k"`, and `"Budget starting from 50 lakhs"` to `"50 lakhs"`) while preserving raw, manually edited CRM entry compatibility (designed and implemented by Shashi).

## [1.0.27] - 2026-05-24
### Changed
- Configured **Lead Quality** parameters default state to be blank (`""`) across all modules.
- Integrated a premium `Choose Quality...` placeholder option as the default selected choice inside the Manual Lead creation form, sliding details drawer edit form, and inline Quick Edit leads list datatable dropdown selector cells (designed and implemented by Shashi).

## [1.0.26] - 2026-05-24
### Added
- Integrated an automatic background **Leads List refresh interval** to automatically reload datatable records every 15 minutes, preserving the sales representative's active pagination state and filter preferences seamlessly (designed and implemented by Shashi).

## [1.0.25] - 2026-05-24
### Added
- Integrated two new dynamic CRM custom fields: **Project Timeline** and **Property Location/Pincode**.
- Added automated database migrations inside `class-db.php` schema definitions to add both columns.
- Enabled administrative field mapping mappings configuration for incoming ad webhooks from Meta, Google, LinkedIn, and custom Website forms.
- Integrated Project Timeline and Property Location/Pincode parameters inside the Manual Lead creation modal, AJAX lead insertions, sliding profile drawer workspace editor form, AJAX profile updates, and CSV exports (designed and implemented by Shashi).

## [1.0.24] - 2026-05-24
### Fixed
- Resolved aggressive browser caching of CSS stylesheets and client-side JavaScript assets by replacing static version parameters with the dynamic `IDLM_VERSION` constant within `register_core_assets()`, forcing immediate asset refreshes upon plugin updates (implemented by Shashi).

## [1.0.23] - 2026-05-24
### Fixed
- Resolved the drawer lead deletion bug by exposing the internal `showToast` UI helper function to the global scope (`window.idlmShowToast`) and updating the `deleteSingleLead` handler to safely call it with fallback protection (implemented by Shashi).

## [1.0.22] - 2026-05-24
### Added
- Integrated robust administrator-only **Leads Deletion options** supporting both individual and bulk operations.
- Added a premium red-accented "Delete Lead" button inside the detailed workspace drawer details view, visible only to administrators (`manage_options`).
- Registered a secure `bulk_delete` action option within the CRM bulk operations dropdown, exclusive to administrators.
- Added the secure `idlm_delete_lead` AJAX endpoint and corresponding handlers in `class-admin.php` that verify admin privileges, perform full leads database removals, and automatically clean up associated activity logs and CAPI logs for database integrity (designed and implemented by Shashi).

## [1.0.21] - 2026-05-24
### Added
- Integrated a premium **Frontend Shortcode Integrations** card directly inside the WordPress Admin Settings Dashboard. Features styled instructions, specific descriptions, and single-click copy buttons linked to clipboard utility to copy `[idlm_leads_list]` and `[idlm_dashboard]` shortcodes effortlessly (designed and implemented by Shashi).

## [1.0.20] - 2026-05-24
### Added
- Integrated two new WordPress shortcodes: `[idlm_leads_list]` for rendering the full Leads Database Workspace on any frontend page, and `[idlm_dashboard]` for rendering the Performance Analytics Dashboard on the frontend (designed and implemented by Shashi).
- Refactored script, stylesheet, fonts, and localization enqueues into a shared `register_core_assets()` method, enabling clean, lightweight conditional frontend enqueuing that loads CRM dependencies only when pages contain active shortcodes.
- Added strict authorization verification checks (`current_user_can('read')`) within both shortcode handlers to guarantee maximum data protection and block unauthenticated users with a styled rest-notice.

## [1.0.19] - 2026-05-24
### Added
- Integrated a customized **Lead Budget** field across the entire CRM, settings page, and webhook systems.
- Updated the custom database schema `wp_idlm_leads` to include the new `budget` column with an automated db migration runner executing on plugin update.
- Enabled budget parameters configuration inside incoming webhook settings mapping panel, manual lead creation form, CRM leads list datatable, detailed profile workspace drawer, and leads CSV exports (designed and implemented by Shashi).

## [1.0.18] - 2026-05-24
### Fixed
- Resolved the Webhook URL Copy button malfunction by implementing a secure global clipboard copying helper function `idlmCopyToClipboard` with a robust off-screen textarea fallback to copy data successfully on insecure contexts (HTTP / localhost) where `navigator.clipboard` is restricted (implemented by Shashi).

## [1.0.17] - 2026-05-24
### Added
- Upgraded the Call Back Requested action flow: replaced the basic browser `prompt()` window with a premium, custom styled **Schedule Callback Modal** that integrates a native HTML5 datetime-local calendar and time selector.
- Converted the Callback Scheduled text field inside the detailed lead drawer profile panel to a native HTML5 datetime-local picker, and automatically formatted database conversions with ISO separators for reliable synchronization (implemented by Shashi).

## [1.0.16] - 2026-05-24
### Added
- Integrated an animated, premium styled **Pending Calls Pill** next to the "Add Manual Lead" button in the Leads Database header. The pill features a pulsing glow micro-animation and updates dynamically in real-time as reps edit lead statuses, create new leads, or apply filters (designed and implemented by Shashi).

## [1.0.15] - 2026-05-24
### Fixed
- Resolved REST API webhook authentication bypass error ("You do not have permission to access this resource") by increasing the priority of the authentication bypass hook to `99999` to successfully override security plugin/custom limits, and added support for url-encoded/parameter-based REST route requests (implemented by Shashi).

## [1.0.13] - 2026-05-24
### Fixed
- Fixed PHP Undefined array key warnings in settings form processor by guarding `meta_audience_id`, `google_user_list_resource`, and `linkedin_segment_urn` configurations with `isset()` checks (implemented by Shashi).

## [1.0.12] - 2026-05-24
### Added
- Separated Platform Conversion APIs (CAPI) and Custom Audience sync settings into two isolated, premium panels, designed and built by Shashi.
- Integrated a new dynamic **Custom Audience Batch Sync Rules** builder that supports configuring multiple custom segments target lists.
- Enabled granular segmentation filtering for each Custom Audience sync job using separate Pipeline Stage, Call Status, and Lead Quality parameters.
- Re-engineered the daily 2:00 AM Cron/Scheduler Batch Sync executor inside `class-audiences.php` to run dynamic, filter-based queries for each active sync rule sequentially.

## [1.0.11] - 2026-05-24
### Added
- Integrated dynamic, dynamic-rows multiple follow-up reminder rules inside the settings workspace panel, designed and built by Shashi.
- Enabled administrative configuration of specific reminder time intervals (in hours) for distinct call statuses (e.g. "Not Called", "No Answer", "Call Back Requested"), with backwards-compatible option mapping.
- Implemented high-performance MySQL-level date calculation query filters inside `ajax_get_leads_table` to isolate overdue follow-up leads inside the CRM datatable workspace.
- Added visual overdue accent badges under Call Status dropdowns with a premium red pulsing micro-animation.

## [1.0.10] - 2026-05-24
### Added
- Implemented robust, automatic webhook values normalization for Property Type and Number of Rooms, designed and built by Shashi.
- Integrated string lookup matching arrays and standard regular expression filters in `class-integrations.php` to map different platform responses (Meta, Google, LinkedIn, Website custom forms) to uniform system database options (`Apartment`, `Villa`, `1 BHK`, `2 BHK`, etc.).

## [1.0.9] - 2026-05-24
### Added
- Integrated a premium Webhook Setup Guide and dynamic platform copy-paste URL selectors inside the WordPress admin settings dashboard, designed and implemented by Shashi.
- Integrated quick copy clipboard triggers for Meta Ads, Google Ads, LinkedIn Ads, and custom Website forms endpoints.

## [1.0.8] - 2026-05-24
### Removed
- Removed the manual Cost Per Lead & Channels Budget inputs widget and table from the Marketing dashboard tab, ensuring a cleaner layout.
- Guarded Javascript event click listeners and CPL table populating code in `dashboard.js` to ensure stability without the CPL container elements.

## [1.0.7] - 2026-05-24
### Changed
- Streamlined Lead Quality definitions across the system. Replaced the previous `Hot`, `Warm`, `Cold`, and `Unqualified` options with exactly 2 simplified options: `Qualified` and `Unqualified`.
- Refactored Lead Quality options in dashboard filter menus, the manual lead creation form, the profile drawer view, and inline quick-edit datatable selectors.

## [1.0.6] - 2026-05-24
### Added
- Integrated dynamic and static tracking support for `utm_adgroup`, `utm_term`, and `utm_content` parameters, alongside custom click identifiers.
- Added client-side cookie capture fields inside front-end UTM tracker snippet.
- Enabled administrative mapping dropdown configurations in settings for UTM Campaign Attribution fields, supporting incoming platform webhooks mappings.
- Rendered captured UTM variables and tracking click IDs as static (read-only) variables in the Campaign Attribution details block inside the lead profile drawer.
- Appended captured UTM Adgroup, UTM Term, UTM Content, FBCLID, and LI FAT ID values to the CSV Export formatting logic.

## [1.0.5] - 2026-05-24
### Changed
- Reverted the Campaign Attribution Details inside the lead editing drawer form back to strictly static (read-only) text parameters. This locks the captured UTM variables and click-IDs (`gclid`, `fbclid`) to prevent manual editing and preserve attribution tracking data integrity.
- Stripped all Campaign Attribution parameters from the `ajax_save_lead_details` database update query parameters.

## [1.0.4] - 2026-05-24
### Added
- Integrated full **Lead Editing capabilities** inside the sliding side details drawer panel. Converted the static details table into an interactive, premium styled HTML form.
- Enabled administrators and sales representatives to modify lead names, email, phone numbers, dynamic rooms counts, preferred studio branches, property categories, quote shared checklists, and raw UTM/click ID parameters.
- Added a new `ajax_save_lead_details` AJAX endpoint to process drawer edits, sanitize parameters, update database indices, and trigger instant datatable refreshes.

## [1.0.3] - 2026-05-24
### Added
- Integrated a new **Number of Rooms Options** custom configuration in the Settings panel. Allows administrators to define a comma-separated list of active room options (e.g. `1 BHK, 2 BHK, 3 BHK, 4 BHK, 4+ BHK`).
- Updated the manual lead creator modal form to dynamically query this custom rooms list and render it inside the dropdown selector instead of using hardcoded values.

## [1.0.2] - 2026-05-24
### Added
- Integrated a new **Preferred Studio Locations** custom configuration in the Settings panel (under custom pipelines stages & statuses). Allows administrators to define a comma-separated list of active studio branches.
- Updated the manual lead creator modal form to automatically parse the custom studios options list and render them inside a premium styled `<select>` dropdown instead of a generic text input.

## [1.0.1] - 2026-05-24
### Fixed
- Added CSS visibility selectors (`display: none;` and `display: block;`) for `.idlm-mapping-tab-content` to solve the issue where all platform payload maps rendered simultaneously instead of toggling properly when clicked.

## [1.0.0] - 2026-05-24
### Added
- Initial project architecture and implementation plan approved.
- Designed custom database tables (`wp_idlm_leads`, `wp_idlm_lead_activity_log`, `wp_idlm_capi_logs`, `wp_idlm_cost_per_lead`) inside `includes/class-db.php` with robust CRUD helpers, speed connected metrics, and email/phone duplicate detectors.
- Built incoming REST API webhook handler (`wp-json/interior-design-lead-manager/v1/webhook`) and front-end UTM/Click ID cookie capturing script inside `includes/class-integrations.php`.
- Implemented automatic routing rules (Manual Only, Round Robin rotation, and Source-Based mapping) inside `includes/class-integrations.php`.
- Created Server-Side Conversion API connectors (Meta CAPI, Google Analytics 4 Measurement Protocol, and LinkedIn CAPI) inside `includes/class-capi.php` integrated with Action Scheduler background queueing.
- Engineered 2:00 AM daily recurring custom audience batch sync schedulers inside `includes/class-scheduler.php` and `includes/class-audiences.php`.
- Developed Marketing and Sales analytics calculations inside `includes/class-dashboard.php`.
- Implemented core administrative sidebar menus (Dashboard, Leads List, CAPI Audits, Settings) and AJAX quick-save row drop-downs, drawer profile details, timeline comments, manual lead addition, and CSV exports inside `includes/class-admin.php`.
- Designed premium adaptive HSL stylesheet systems (`assets/css/admin-style.css`, `assets/css/dashboard.css`) featuring custom scrollbars, target goal meters, status badges, and sliding drawers.
- Programmed table search pagination events, inline edit checkers, bulk selectors, and Chart.js reporting widgets (`assets/js/admin-app.js`, `assets/js/dashboard.js`).
- Created base bootstrap file `interior-design-lead-manager.php`.
- Validated all PHP files successfully using `php -l` syntactic lint.
