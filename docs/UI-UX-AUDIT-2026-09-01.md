# Frontend UI/UX Audit — 2026-09-01

**Plugin:** Interior Design Lead Manager  
**Author / builder:** Shashi  
**Method:** UI UX Pro Max design-system, UX, chart, and web-stack guidance; source review of all frontend templates, renderers, CSS, and interaction JavaScript  
**Scope:** Workspace, authentication, account, Leads, drawers/modals/tasks, import/export/routing, Kanban, Dashboard, Quotations, Settings, Users, and logs

## Executive assessment

The CRM already has a strong foundation: consistent Inter typography, a recognizable palette, a unified workspace, role-aware views, compact operational tables, useful filters, progressive lead detail, responsive layouts, status text, and recent keyboard/focus work in the filter drawers.

The main usability risk is not the visual direction; it is inconsistent implementation of the direction. The shared stylesheet contains 431 hard-coded colour values, 1,581 `!important` declarations, 505 template inline styles, duplicate token aliases, 13 `transition: all` rules, more than a dozen breakpoint variants, and multiple independent z-index scales. This makes accessible changes difficult to apply reliably.

Four items should be corrected before further visual polish: primary-action contrast, complete dialog behavior, keyboard alternatives for Kanban, and chart data accessibility.

## Audit evidence

| Area reviewed | Evidence |
|---|---:|
| PHP UI templates | 20+ screens/partials |
| CSS | 5,044 lines across 7 modules |
| Interaction JavaScript | 5,581 lines across 6 modules |
| Tables | 18 |
| Forms | 16 |
| Template buttons | 140 |
| Chart canvases | 13 |
| Existing ARIA attributes | 107 |
| Template inline styles | 505 |
| CSS hard-coded hex uses | 431 |
| CSS `!important` uses | 1,581 |

## What is working well

- Unified role-aware workspace and persistent contextual lead drawer.
- Strong operational IA: Leads, Pipeline, Dashboard, Quotations, Settings, Users, logs, and account are recognizably one product.
- Embedded Inter fonts avoid third-party dependency and layout shift.
- Leads/Kanban filter drawers already use dialog semantics, focus trapping, Escape, and focus restoration.
- Sortable lead headers expose keyboard behavior and `aria-sort`.
- Semantic status text accompanies most colours.
- Tables and quotation editor use contained horizontal scrolling rather than breaking the page.
- Authentication includes visible labels, useful autocomplete values, status/error announcements, and responsive presentation.
- Capability-aware actions reduce dead ends for pre-sales, representatives, managers, and administrators.

## Prioritized findings

| Priority | Finding | Evidence | Improvement |
|---|---|---|---|
| P0 | Primary button contrast fails WCAG AA | White on `#C99644` is `2.65:1`; white on hover `#9F6F25` is `4.40:1` | Keep gold as brand accent; use `#7A5017`/`#603D10` for white-text actions, or dark text on gold |
| P0 | Modal behavior is inconsistent | Shared overlays use unlabelled modal containers and clickable `span` close controls; several flows do not trap/restore focus | Build one dialog controller/component with native close buttons, label/description, inert background, focus trap, Escape, and focus restoration |
| P0 | Kanban is mouse/drag dependent | Cards are draggable `div`s; only the title has click behavior and no keyboard handler; stage movement is drag-only | Make card opening keyboard-operable and add a “Move to stage” action using the same validations as drag/drop |
| P0 | Charts are canvas-only | 13 canvases have headings but no equivalent data tables or consistently exposed accessible names | Add labelled canvas fallback/description and a “View data table” toggle for every chart |
| P1 | Form labels are often visual only | Many filters/settings/modal labels have no `for`/`id`; required state is often represented by `*` in text | Associate labels, add fieldsets/legends, `aria-describedby`, correct input types/input modes, and inline errors |
| P1 | UI tokens are not governing the implementation | 431 hard-coded colours, duplicate aliases, 505 inline styles, 1,581 `!important` declarations | Migrate by component to the master tokens; prohibit new inline visual styles and near-duplicate values |
| P1 | Click/tap targets are too small | Base controls are often 29–38px; close controls and row actions can be smaller | Use 40px controls and 44px icon-only/mobile targets; preserve dense visuals with internal icon sizing |
| P1 | Tabs do not expose tab semantics | Dashboard switches content with buttons and inline handlers but lacks tablist/tab/tabpanel state | Implement full ARIA tabs, arrow-key navigation, focus-visible styling, and persistent selected state |
| P1 | Mobile density is handled mainly by horizontal scroll | Users filters, lead tables, quotation tables, and Kanban can require long horizontal navigation | Keep intentional table/board scroll regions, but add priority columns, expandable rows, mobile card/item editors, and stage list mode |
| P1 | Emoji and symbol icons vary by platform | Emoji occur in lead filters, bulk delete, import progress, task badges, unsaved warning, and Danger Zone | Replace with Dashicons or a single SVG icon set; keep text labels and hide decorative icons from assistive tech |
| P2 | Motion and layer systems are fragmented | `transition: all`; z-index values span 1–110000; numerous non-standard breakpoints | Adopt the master motion, layer, and breakpoint tokens; animate named properties only |
| P2 | Dashboard is cognitively heavy | 13 charts plus multiple tables and metrics compete for attention | Lead with six decision KPIs and three core views; move diagnostic distributions behind “More analysis” |
| P2 | Settings expose storage vocabulary | Comma-separated lists, long integration cards, repeated credentials, and mixed advanced content increase error risk | Use add/remove tokens, connection states/tests, progressive disclosure, grouped goals, and clearer save scope |
| P2 | Feedback still uses browser alerts | Kanban validation and several workflows call `alert()` | Use inline field errors and non-blocking status/toast feedback with focus moved to the first error |

## Screen-by-screen recommendations

### Workspace shell

- Add a visible-on-focus “Skip to CRM content” link before the sidebar.
- Keep the current dark sidebar/gold selection but increase label contrast and make the collapsed tooltip behavior explicit.
- Align the implemented `210/64px` shell widths with one documented standard; the earlier design document states `238/72px`.
- On mobile, prefer an off-canvas navigation trigger over a long horizontally scrolling navigation row when the menu grows.

### Login and My Account

- Preserve the calm split login layout and local Inter font.
- Add password reveal and visible password requirements.
- Move focus to returned error/status messages after form navigation.
- Add `autocomplete="name"`, `given-name`, `family-name`, `email`, and `tel` to profile fields.

### Leads, drawer, tasks, import/export, and routing

- Keep operational metrics and filter drawer; expose metric-selected state and exact filter effect.
- Associate every filter label and provide one stable Apply/Clear footer rather than tiny `29px` More/Clear actions.
- Make row selection labels announce the lead name; add a table caption and column-priority/mobile expansion pattern.
- Consolidate callback, Won, Quote Shared, Lost, unsaved, import, export, and routing overlays into one dialog contract.
- Make import progress a real labelled progressbar with batch/row errors and a downloadable failure file.
- Export should preview the matched record count and announce when current filters are copied.

### Kanban

- Keep stage columns and contained horizontal scroll on desktop.
- Add keyboard “Move to stage”, focus restoration, and live move announcements.
- Make the whole card opening action semantic and preserve an explicit drag handle for pointer users.
- Show next action/overdue state before lower-value metadata; remove emoji task indicators.
- Offer a stage-tab list on phones to avoid two-dimensional scrolling.

### Dashboard

- Add a visible label to the period selector and accessible semantics to Marketing/Sales tabs.
- Provide data tables for all canvases and announce range updates.
- Default to decision views: trend, acquisition ranking, funnel, pipeline/Won value, rep workload, lost reasons.
- Put property/BHK/studio/call-status diagnostic distributions behind disclosure.
- Use horizontal bars for long category names and reserve doughnuts for small part-to-whole sets.

### Quotations

- Preserve the identity-first editor and full-width line items.
- Replace symbol-only move/duplicate/remove controls with consistent icons, tooltips, and 44px targets.
- Add keyboard section/item reordering and a structured mobile item editor.
- Keep autosave status visible and clarify whether Cancel keeps or discards the working copy.
- Check print font sizes and table wrapping at A4/Letter before each release.

### Settings

- Shorten and regroup tabs around goals; combine integration-specific tabs beneath one Integrations parent where feasible.
- Replace comma-separated pipeline/status lists with validated chips and add/reorder/remove controls.
- For secrets, show configuration state, reveal/copy, last tested, and Test Connection without redisplaying stored secret values.
- Use a consistent sticky save/status area and isolate Danger Zone from it.
- Convert dense help and diagnostics to disclosures; retain copyable examples and endpoints.

### Users, logs, and account administration

- Preserve disclosure-based user editing and clear Active/Suspended text.
- Allow filters to wrap into a one-column mobile form rather than depending on horizontal scrolling.
- Add accessible labels to log selection checkboxes and captions to log tables.
- Show large request/response bodies only in the standard focus-managed dialog with copy and wrap controls.

## Recommended delivery roadmap

### Phase 1 — Accessibility foundation

**Implemented 2026-09-01 (Builder: Shashi):** accessible action/semantic tokens, global focus-visible and reduced-motion rules, canonical control/layer/motion/breakpoint scales, the reusable dialog controller, requested Leads/Kanban/quotation decision-dialog migrations, and affected Leads/Settings label and inline-validation fixes.

1. Introduce accessible action/semantic tokens and correct primary button contrast.
2. Add global focus-visible, target-size, reduced-motion, layer, and feedback rules.
3. Create one shared dialog controller and migrate common overlays.
4. Associate labels and correct input metadata in Leads and Settings first.

### Phase 2 — Core workflows

**Implemented 2026-09-01 (Builder: Shashi):** semantic Kanban cards with an explicit pointer drag handle and keyboard “Move to stage” flow; focus restoration and live movement status; synchronized Leads row selection, accessible bulk-delete confirmation, four-step import progress with downloadable row failures, export scope/count previews; and persistent ARIA Dashboard tabs with keyboard navigation, range announcements, and equivalent tables for canvas charts.

1. Add keyboard Kanban movement and semantic card opening.
2. Standardize Leads table selection, bulk actions, import, and export.
3. Add accessible Dashboard tabs and chart data alternatives.

### Phase 3 — Responsive workflow design

**Implemented 2026-09-01 (Builder: Shashi):** priority-based Leads columns with accessible details disclosure and mobile sorting; a phone Kanban stage-tab/list mode that retains the shared keyboard movement/lifecycle/focus/live-feedback path; structured quotation item cards; and affected breakpoint, control, motion, layer, and overflow normalization. Verified at `375`, `768`, `1024`, and `1440px`, including the `782px`/200%-zoom-equivalent reflow and reduced motion.

1. Add priority/expandable lead rows and a phone Kanban list.
2. Add a quotation item-card editor for narrow screens.
3. Normalize breakpoints, layers, motion, and component sizes.

### Phase 4 — Design debt reduction

**Implemented 2026-09-01 by Shashi.** Settings and shared presentation contracts were migrated first; canonical runtime tokens now govern active module CSS while compatibility aliases remain declaration-only. The initial debt pass reduced runtime PHP inline styles from 505 to 169, hard-coded CSS colour uses from 431 to 180, and `!important` uses from 1,581 to 207. Automated contracts and the [visual regression matrix](UI-VISUAL-REGRESSION-MATRIX.md) now cover canonical desktop/mobile widths, role variants, reduced motion, and empty/loading/error/success states.

1. Migrate inline styles and hard-coded colours component by component.
2. Consolidate duplicate token aliases and `!important` overrides.
3. Add visual regression states for desktop/mobile, role variants, and empty/loading/error/success.

### Phase 5 — Access and account experience

**Implemented 2026-09-02 (Builder: Shashi):** labelled password reveal controls across sign-in, reset, and My Account; visible password requirements and nearby matching feedback; post-redirect success/error focus; pending submission state; complete profile autocomplete/input metadata; and a focusable workspace skip destination.

1. Add password reveal and visible requirements without weakening WordPress authentication boundaries.
2. Move focus to returned status, expose pending submission state, and keep validation near the affected fields.
3. Complete profile autofill metadata and the workspace skip-link focus target.

### Phase 6 — Settings usability

**Implemented 2026-09-02 (Builder: Shashi):** goal-oriented Settings navigation; structured add/remove editors for pipeline, call-status, Lost-reason, studio, and room vocabularies; persistent saved/unsaved/submitting feedback; consistent reveal/copy/configuration affordances for credential fields; and nearby timestamped connection-test results. Existing option names, serialized values, capability checks, nonces, secret storage, and integration test endpoints remain unchanged.

1. Replace error-prone comma-delimited vocabularies with labelled add/remove option lists.
2. Keep the save action and saved/unsaved/submitting state visible within the active editable tab.
3. Standardize credential configuration state, reveal/copy controls, and nearby test feedback.

### Phase 7 — Users and operational logs

**Implemented 2026-09-02 (Builder: Shashi):** Users & Access now provides programmatic filter labels, mobile-safe one-column reflow, complete profile input metadata, focused redirect feedback, pending form states, specific errors, and visible administrator safeguards. CAPI and audience logs now provide date/status/search filters, semantic captions and selection names, responsive record cards, inline retry feedback, and copyable redacted request/response data in the shared focus-managed dialog. Large CAPI and audience payloads load only through administrator-only, nonce-protected requests.

1. Make Users filters and account forms responsive, labelled, autofill-aware, and explicit about administrator safeguards.
2. Add semantic log tables, date/status/search filters, mobile record cards, and inline feedback.
3. Load large redacted payloads on demand in one accessible copyable detail dialog.

### Final acceptance cleanup

**Implemented 2026-09-02 (Builder: Shashi):** replaced remaining native browser alerts, confirms, and prompts across lead decisions, task and activity actions, quotation bulk actions, Settings operations, and credential rotations with the shared focus-managed dialog and non-blocking status feedback. The final routing-settings control no longer carries inline motion styling. Automated contracts now reject reintroduction of native browser feedback in runtime UI sources.

## Acceptance criteria

- WCAG 2.2 AA contrast for text, controls, status, and focus.
- Every workflow is completable with keyboard only; no drag-only or click-only mutation.
- Every dialog traps/restores focus, closes with Escape, and has an accessible name.
- Every chart has an equivalent data table.
- Every form control has a programmatic label and nearby error/help relationship.
- No new inline visual style, emoji icon, hard-coded design value, `transition: all`, or undocumented z-index/breakpoint.
- Verified at 375, 768, 1024, and 1440px, 200% zoom, and reduced motion.
