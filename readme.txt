=== Interior Design Lead Manager ===
Contributors: shashi-protecto
Tags: lead-manager, interior-design, crm, lead-tracking, duplicate-detection
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.2.23
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A premium lead management system to replace Google Sheets and Pabbly for interior design firms. Captures leads from ad webhooks, manages routing, schedules custom audience batch uploads, triggers conversions server-side using Action Scheduler, and includes a full analytics workspace.

== Description ==

Interior Design Lead Manager is a custom WordPress plugin designed specifically for high-end interior design studios and firms to streamline their lead generation, duplicate matching, assignment routing, and analytics.

Features:
* Webhook integrations for Meta Ads, Google Ads, LinkedIn Ads, and custom Website forms.
* Smart duplicate detection with digits-only, suffix-based matching (normalizing local & international formats).
* Drag-and-drop CSV lead importer with fuzzy header auto-mapping.
* Server-side conversion API triggers utilizing Action Scheduler.
* High-precision performance analytics dashboard with local Chart.js enqueuing.
* Sticky-header profile workspace drawer with responsive timeline logging.

== Installation ==

1. Upload the `interior-design-lead-manager` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Access the Lead Manager setting panel from your admin dashboard sidebar.

== Frequently Asked Questions ==

= Can this plugin detect duplicate leads with different phone formats? =
Yes, the smart matcher normalizes phone entries to digits only and performs suffix matches on the last 10 digits to match local/international formats seamlessly.

== Changelog ==

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
