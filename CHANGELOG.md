# Changelog

All notable changes to this project will be documented in this file.

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
