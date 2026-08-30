# Interior Design Lead Manager — Frontend Design System

**Design owner / builder:** Shashi  
**Applies to:** `[idlm_workspace]`, Leads List, Kanban, Dashboard, Settings, Quotations, drawers, modals, logs, and future frontend modules  
**Source of truth:** The implemented UI in `assets/css/admin-style.css`, `dashboard.css`, `kanban.css`, `workspace.css`, and `quotations.css`

## 1. Purpose

This document defines the visual and interaction language for the Interior Design Lead Manager. Use it before changing or adding frontend UI so every module feels like one operational CRM rather than separate WordPress pages.

The product should feel premium, calm, trustworthy, and efficient. It is a dense business application, so clarity, hierarchy, predictable controls, and fast scanning take priority over decoration.

## 2. Core Principles

1. **One workspace:** Keep users inside the unified frontend shell whenever possible. Preserve their context when moving between leads, pipeline, quotations, analytics, and settings.
2. **Operational clarity:** Put the primary task first, supporting information second, and diagnostics or destructive actions last.
3. **Progressive disclosure:** Keep list and board views compact. Use drawers, modals, tabs, and detail views for deeper information.
4. **Consistent hierarchy:** Every screen follows page header → summary → filters/navigation → primary content → supporting actions.
5. **Capability-aware UI:** Hide unavailable actions instead of showing controls that will fail. Server-side capability checks remain mandatory.
6. **Measured density:** Tables and Kanban cards are compact; forms and detail drawers use more breathing room.
7. **Meaningful colour:** Gold indicates primary actions and focus. Blue indicates information. Green, amber, and red communicate state—not decoration.
8. **Responsive, not reduced:** On small screens, reflow or horizontally scroll dense data without removing essential operations.

## 3. Design Tokens

Use the existing `--idlm-*` CSS custom properties. Do not introduce a near-duplicate colour, radius, or shadow when a token already exists.

### Brand and semantic colours

| Token | Value | Use |
|---|---:|---|
| `--idlm-primary` | `#c99644` | Primary actions, active navigation, focus, selected states |
| `--idlm-primary-hover` | `#9f6f25` | Primary hover/focus |
| `--idlm-secondary` / `--idlm-info` | `#1f7fc2` | Informational accents, links, New-stage Kanban accent |
| `--idlm-success` | `#177c51` | Success, Qualified/Won, completed states |
| `--idlm-warning` | `#9a6700` | Warnings and attention states |
| `--idlm-danger` | `#c84635` | Destructive actions, failures, Lost |
| `--idlm-dark` | `#111a24` | Sidebar and strong dark surfaces |

Soft state backgrounds are `--idlm-success-soft`, `--idlm-warning-soft`, `--idlm-danger-soft`, and `--idlm-info-soft`.

### Surfaces and text

| Token | Value | Use |
|---|---:|---|
| `--idlm-bg-main` | `#eef2f6` | Application background |
| `--idlm-surface` / `--idlm-card-bg` | `#ffffff` | Cards, tables, form surfaces |
| `--idlm-surface-soft` | `#f8fafc` | Table headers, filters, grouped content |
| `--idlm-surface-tint` | `#f5f9ff` | Subtle informational grouping |
| `--idlm-text-main` | `#17212b` | Primary text |
| `--idlm-text-muted` | `#63728a` | Supporting text and metadata |
| `--idlm-border` | `#d9e1eb` | Standard borders and separators |
| `--idlm-border-strong` | `#c7d2df` | Hover and stronger divisions |

### Shape, shadow, and motion

- Standard radius: `8px` (`--idlm-radius`).
- Large panels and Kanban columns: `10–12px`.
- Pills, counts, and badges: `999px`.
- Standard card shadow: `--idlm-shadow`.
- Elevated drawer/modal shadow: `--idlm-shadow-lg`.
- Standard transition: `--idlm-transition`, approximately `200ms` with a smooth ease.
- Avoid heavy shadows on dense operational components such as Kanban stats and filters.

## 4. Typography

- Font stack: `Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`.
- Page title: `28px / 700`.
- Card title: `18px / 600`.
- Section and Kanban titles: `14–16px / 600–700`.
- Body and inputs: `13–14px / 400–500`.
- Labels and metadata: `11–12px / 600–700`.
- Metric values: `17–32px / 700`, based on available space.
- Uppercase is reserved for compact labels, table headers, metrics, and badges. Do not use uppercase for paragraphs or actions.
- Supporting copy uses muted colour and approximately `1.5` line-height.

## 5. Spacing and Layout

Use a practical 4px-based rhythm:

- Tight inline spacing: `4–8px`.
- Control and component gaps: `10–12px`.
- Card inner spacing: `16–24px`.
- Section gaps: `20–25px`.
- Workspace desktop content padding: `22px`; mobile: `12px`.

Primary content must use `min-width: 0` inside grid/flex layouts to prevent overflow. Dense tables and Kanban boards may scroll horizontally. Avoid fixed page widths; the CRM should fill its available workspace.

## 6. Unified Workspace Shell

- Desktop sidebar width: `238px`; collapsed width: `72px`; height fills the complete viewport using `100dvh` with a `100vh` fallback.
- Sidebar uses a dark surface with gold active navigation.
- Each navigation row has one Dashicon, one short label, a `44px` minimum height, and a `10px` radius.
- Collapsed state retains icons and tool meaning; its state is remembered locally.
- At `782px` and below, the sidebar becomes a top navigation region with horizontally scrollable navigation. Collapsing hides the mobile menu.
- Module content must render inside `.idlm-workspace-content` without adding an unrelated full-page background or duplicate navigation shell.
- My Account remains the final sidebar item and provides self-service profile, phone, email, password change, and password-reset email controls.

## 7. Page Anatomy

Every primary screen should follow this structure:

1. `.idlm-header`
   - Left: title and one concise subtitle.
   - Right: primary page actions or global period selector.
2. Summary layer
   - Metrics, status cards, or compact operational totals.
3. Navigation/filter layer
   - Tabs for peer sections; filter panels for data refinement.
4. Primary content
   - Table, Kanban board, charts, form cards, or quotation workspace.
5. Secondary/detail layer
   - Drawers, modals, supporting cards, logs, and sticky save actions.

Do not place more than one visually dominant primary action in the page header.

## 8. Components

### Buttons

- `.idlm-btn-primary`: gold background, white text; use for the single preferred action.
- `.idlm-btn-secondary`: white surface, neutral border; use for navigation and secondary operations.
- Danger actions use red text/border and require clear wording. Irreversible actions require confirmation.
- Standard controls are approximately `8px 16px`, `13px`, medium weight, and `8px` radius.
- Icon-only buttons require an accessible label or title and a minimum practical hit area.
- Disabled controls must visibly reduce emphasis and must not rely only on cursor changes.

### Inputs and forms

- Standard class: `.idlm-input`; use the matching select and textarea styles within `.idlm-wrap`.
- White background, soft border, `8px` radius, `13px` text.
- Focus uses the gold border plus a translucent gold ring.
- Labels sit above controls, use `11–12px` semibold text, and may be uppercase in compact filters.
- Form grids use two columns by default and collapse to one column responsively.
- Group related checkboxes in bordered or soft-surface rows rather than scattering them through a form.
- Help text appears directly below its control in muted text. Validation appears close to the failing control.

### Cards and panels

- Standard `.idlm-card`: white background, soft border, `8px` radius, `20px` padding, restrained shadow.
- Use one card per meaningful concept, not one card per field.
- Nested content should generally use a soft surface and border instead of another elevated shadow.
- Card titles are brief and action-oriented. Optional descriptive copy sits immediately below the title.

### Metrics

- Metrics are compact, scannable, and may be clickable filters.
- Label: muted uppercase `10–12px`; value: strong `17px+`.
- Alert metrics use warm/red semantic treatment only when a real exception exists.
- A clicked metric must apply the exact population represented by its displayed value.

### Tabs

- Tabs represent peer views within the same page, not unrelated destinations.
- Active tabs use gold text/indicator; inactive tabs remain neutral.
- Tab bars scroll horizontally when needed and do not wrap into uneven multi-line rows.
- Danger Zone remains visually separated with danger colouring.
- Preserve selected tabs in the URL hash or relevant view state where implemented.

### Tables

- White surface, separate borders, `8px` radius, horizontal scrolling wrapper.
- Header: soft surface, `11px` uppercase semibold/bold muted text.
- Cells: `10px 8px` padding with primary content at `13px` and metadata at `12px`.
- Rows use a subtle soft-surface hover. Do not add strong striping.
- Sortable headers must retain `aria-sort`, keyboard support, and visible focus.
- Inline editing controls should be compact and visually quieter than page-level forms.
- Keep critical identifying information toward the left and actions toward the right.

### Badges and chips

- Badges are compact semantic labels: `11px`, bold, pill-shaped.
- Source badges retain their established channel colours.
- Chips communicate configured values or passive metadata; do not make them look clickable unless they are interactive.
- Never use colour as the only status signal—always include text.

### Drawers

- Lead details use the right-side drawer for deep work without losing list or Kanban context.
- Desktop drawer may be wide enough for two-column detail panels; under `820px`, forms collapse to one column.
- At `700px` and below, the drawer becomes a full-width mobile workspace.
- Header contains identity and current status; tabs divide Details, Activity, Tasks, and related workflows.
- Footer actions remain visible and place the primary Save action last/right on desktop and prominently on mobile.
- Lock background scrolling while a drawer or modal is open.

### Modals

- Use modals for short, blocking decisions: confirmation, routing, loss reason, or a focused activity entry.
- Avoid putting full-page configuration inside a modal.
- Use an elevated white panel, clear title, concise explanation, and explicit cancel/confirm actions.
- Standard wide work modals use `min(920px, calc(100vw - 48px))` width and `min(82vh, 760px)` maximum height; at `700px` and below they use `calc(100vw - 20px)` width and `92vh` height.
- Keep modal headers fixed, long form bodies independently scrollable, and action placement consistent across catalogue, import, and selection popups.
- Keep keyboard focus inside the modal and restore it to the trigger on close when adding new modal behavior.

### Empty, loading, success, and error states

- Loading states use an inline spinner or skeleton near the affected region; do not blank the whole workspace.
- Empty states explain what is absent and, when useful, offer the next action.
- Toasts are suitable for completed background or AJAX actions.
- Inline alerts are used when the user must read or correct something before continuing.
- Success is green, warning amber, error red, and neutral information blue.

## 9. Leads List Pattern

- Header presents the operational purpose and global actions such as Add Lead or Import.
- Summary metrics sit above filters and double as drill-down shortcuts where appropriate.
- Filters use the compact soft panel, auto-fitting columns, and consistent label/control sizing.
- The table is the primary scanning surface. Keep rows compact and show secondary data in muted text.
- Quick-edit controls must not visually overpower the customer identity.
- Clicking a row opens the lead drawer; mutations should refresh affected metrics and rows without losing filters or pagination.
- Preserve role-based differences for pre-sales, managers, and representatives without changing the overall visual grammar.

## 10. Kanban Pattern

- Summary cards are compact and arranged as six columns on wide screens, three below `1280px`, and two below `680px`.
- Filters use a white card with a `12px` radius and wrap into usable widths.
- Board scrolls horizontally; columns remain approximately `310px` wide.
- Column headers use a thin semantic stage accent:
  - New: blue
  - Contacted: gold
  - Qualified and Won: green
  - Quote Shared: purple
  - Negotiation: orange
  - Lost: red
- Cards use a white surface, soft border, compact metadata, and a restrained lift on hover.
- Drag state must visibly distinguish the moving card and target column.
- Clicking a card opens the same lead drawer used by Leads List.
- Counts and monetary totals in column headers must correspond exactly to the visible filtered population.

## 11. Settings Pattern

- Settings use a consistent page header with a single Save Settings action.
- Top tabs organize major concerns: General, Routing, Webhooks, CAPI, Sync & Reminders, Help, Health, MCP, and Danger Zone.
- Settings are grouped into cards by user goal, not underlying option names.
- Two-column card layouts collapse to one column below approximately `900px`.
- Use nested tabs only for closely related mappings or client-specific instructions.
- API keys and credentials need clear labels, masking where appropriate, connection testing, and explanatory text.
- Diagnostics are read-only unless an explicit maintenance action is required.
- Danger Zone is isolated, red-accented, and never shares the standard Save action.

## 12. Dashboard Pattern

- Marketing and Sales are peer tabs.
- Stat cards flex across the available width, targeting six per row with `180px` minimum width; they become single-column on narrow phones.
- Chart grids use two columns and collapse below `900px`.
- Each chart card uses title, optional subtitle, optional compact toolbar, then a stable-height chart region around `300px`.
- Always provide a meaningful chart empty state rather than an empty canvas.
- Trend colours must reflect genuine positive/negative meaning for that metric, not merely numerical direction.

## 13. Quotations Pattern

- Quotations use the shared header, panel, input, button, table, and workspace patterns.
- Editable line items remain intentionally simple: Items, Qty/Unit, Rate, Total, and Buttons.
- Catalogue search, description, item code, and category stay together in the Items column.
- Section controls remain in the section header; Add Section sits below the line-item collection.
- Line items occupy the full detail width. Payment milestones and revision previews follow beneath the line-item area rather than narrowing it into a sidebar column.
- Totals and workflow actions are visually distinct from editable commercial content.
- Finalized revisions are read-only. New commercial changes require a named revision with a reason.
- Printable/PDF views prioritize legibility and avoid application-only controls in print.

## 14. Responsive Rules

Use the existing breakpoints unless a component has a demonstrated need for another:

- `1280px`: Kanban summary reduces from six to three columns.
- `1120px`: Lead drawer workspace begins simplifying.
- `900px`: chart grids and multi-column settings become one column.
- `820px`: drawer form/data grids become one column.
- `782px`: unified sidebar becomes top navigation.
- `768px`: common tablet adjustments.
- `700px`: lead drawer becomes full-width.
- `680px`: Kanban summary becomes two columns.
- `640px`: settings tabs and form spacing tighten.
- `600px`: modal, progress, and compact layout adjustments.
- `480px`: stat cards become one per row.

Do not shrink text below legible sizes to force desktop layouts onto mobile. Prefer reflow, stacking, or scoped horizontal scrolling.

## 15. Accessibility Requirements

- Maintain a visible keyboard focus state using the gold focus treatment.
- Every form control requires an associated label or accessible name.
- Icon-only controls require `aria-label` or equivalent text.
- Interactive table headers must support Enter and Space and expose `aria-sort`.
- Active navigation uses `aria-current`; tabs should expose their selected state when new tab systems are added.
- Modal and drawer close controls must be keyboard reachable.
- Preserve sufficient contrast for muted text, badges, and controls.
- Status, validation, and drag feedback must not depend on colour alone.
- Minimum target size should be approximately `36px`, with `44px` preferred for primary mobile navigation and actions.
- Respect reduced-motion preferences when introducing nonessential animation.

## 16. Writing and Content Style

- Use short, direct labels: “Add Lead”, “Save Settings”, “Create Quote”.
- Page subtitles explain purpose in one sentence.
- Use sentence case for titles, actions, and labels; reserve uppercase for compact metadata.
- Error messages state what failed and what the user should do next.
- Confirmations name the affected object and consequence.
- Avoid unexplained acronyms except established product terms such as CAPI, MCP, CSV, and PDF; explain them in supporting copy when first introduced.

## 17. Implementation Rules

- Scope plugin UI selectors beneath `.idlm-wrap`, `.idlm-workspace`, or another `idlm-` component root to minimize theme conflicts.
- Prefix every new class with `idlm-`.
- Reuse existing PHP render patterns and CSS classes before creating variants.
- Enqueue assets only on plugin admin pages or pages containing the relevant shortcode.
- Do not use inline styles for reusable presentation; move repeated styles into the appropriate stylesheet.
- Keep interaction logic in the matching JavaScript asset rather than inline handlers for new work.
- Continue WordPress security practices: capabilities, nonces, sanitization, escaping, and prepared queries.
- Frontend and backend versions of the same module should share markup and components where practical.
- Avoid breaking existing shortcodes while `[idlm_workspace]` remains the recommended entry point.

## 18. File Ownership

| Area | Primary files |
|---|---|
| Shared tokens and components | `assets/css/admin-style.css` |
| Unified frontend shell | `assets/css/workspace.css`, `assets/js/workspace.js` |
| Leads List and drawer behavior | `includes/class-admin.php`, `assets/js/admin-app.js` |
| Dashboard | `assets/css/dashboard.css`, `assets/js/dashboard.js` |
| Kanban | `assets/css/kanban.css`, `assets/js/kanban.js` |
| Quotations | `assets/css/quotations.css`, `assets/js/quotations.js`, `includes/class-quotations.php` |
| Settings and shared overlays | `includes/class-admin.php`, `assets/css/admin-style.css` |
| Local Inter font | `assets/css/fonts.css`, `assets/fonts/` |

## 19. Frontend Change Checklist

Before completing any UI change:

- [ ] The screen follows the standard page anatomy.
- [ ] Existing tokens and components are reused.
- [ ] The UI works inside `[idlm_workspace]` and does not unexpectedly route to WordPress Admin.
- [ ] Role/capability differences are respected visually and server-side.
- [ ] Desktop, tablet, and mobile layouts are checked.
- [ ] Keyboard focus, labels, and status text are present.
- [ ] Loading, empty, success, and failure states are covered.
- [ ] Theme CSS conflicts are minimized through `idlm-` scoping.
- [ ] PHP and JavaScript syntax checks pass.
- [ ] Version and changelog are updated with Builder: Shashi.
