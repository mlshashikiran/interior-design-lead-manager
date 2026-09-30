# Interior Design Lead Manager — Reusable CRM UI Design

**Builder:** Shashi  
**Status:** Reusable visual and interaction specification  
**Scope:** Admin CRM, `[idlm_workspace]`, leads, pipeline, dashboard, quotations, users, settings, logs, and future modules  
**Reference:** [Twenty CRM](https://github.com/twentyhq/twenty)  
**Local source of truth:** [`docs/DESIGN.md`](docs/DESIGN.md) and [`design-system/interior-design-lead-manager/MASTER.md`](design-system/interior-design-lead-manager/MASTER.md)

## 1. Intent

Use this document to design new CRM screens from a consistent vocabulary of reusable elements. The visual direction is inspired by Twenty’s open, object-and-view-oriented CRM: a persistent workspace, compact record lists, configurable views, strong record identity, progressive disclosure, and keyboard-friendly operational controls.

This is an adaptation, not a pixel copy. Existing IDLM brand tokens, WordPress contracts, permissions, data density, and accessibility requirements always win.

### Product qualities

- Calm, premium, trustworthy, and operational.
- Fast scanning for sales and pre-sales teams.
- One workspace with preserved context between list, board, record, and detail surfaces.
- Clear distinction between data, workflow state, and destructive actions.
- Dense where comparison matters; spacious where editing or decision-making matters.

## 2. Design foundations

### 2.1 Application frame

```text
┌─────────────────────────────────────────────────────────────┐
│ Persistent sidebar        │ Page header + primary action    │
│ Workspace navigation       │ Summary / filters / view tools  │
│                           │ Main object view                │
│                           │ Drawer, modal, or detail route  │
└─────────────────────────────────────────────────────────────┘
```

- Use `.idlm-workspace` as the application shell.
- Desktop sidebar: approximately `248px`; collapsed: approximately `64px`.
- Sidebar is dark; active navigation uses the gold brand accent.
- Content must use `min-width: 0` and fill the available viewport.
- At `782px` and below, navigation becomes a compact mobile/top navigation treatment.
- Do not add a second full-page shell around an existing workspace.

### 2.2 Tokens

Reuse the canonical `--idlm-*` variables. Never add a one-off colour, radius, shadow, or breakpoint when an equivalent token exists.

| Family | Canonical tokens | Guidance |
|---|---|---|
| Brand | `--idlm-brand-gold` | Selected indicators, accents, chart labels |
| Actions | `--idlm-action`, `--idlm-action-hover`, `--idlm-action-contrast` | Primary actions and focus |
| Semantics | `--idlm-info`, `--idlm-success`, `--idlm-warning`, `--idlm-danger` | Meaningful state only |
| Surfaces | `--idlm-canvas`, `--idlm-surface`, `--idlm-surface-soft`, `--idlm-surface-tint` | Canvas, cards, grouping |
| Text | `--idlm-text`, `--idlm-text-muted`, `--idlm-text-inverse` | Hierarchy and metadata |
| Structure | `--idlm-border`, `--idlm-border-strong` | Separators, control boundaries |
| Shape | `--idlm-radius`, `--idlm-radius-panel`, `--idlm-radius-pill` | 8px cards, 12px large panels, pills |
| Elevation | `--idlm-shadow`, `--idlm-shadow-lg` | Restrained cards, elevated overlays |
| Layout | `--idlm-space-*`, `--idlm-control-height`, `--idlm-target-size` | 4px rhythm; 44px touch target |
| Motion | `--idlm-transition`, `--idlm-motion-*`, `--idlm-ease` | Short, purposeful transitions |

### 2.3 Type scale

- Page title: `22px`, bold.
- Section title: `16px`, semibold/bold.
- Card and control title: `14px`, semibold.
- Body and input text: `13–14px`.
- Metadata and helper text: `11–12px`.
- Metric value: `18–32px`, tabular numerals.
- Use sentence case for user-facing copy. Reserve uppercase for compact metadata and table headers.
- Font stack: `Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`.

## 3. Primitive elements

These are the smallest reusable building blocks. Each new component should compose them rather than inventing parallel markup.

### 3.1 Icon and avatar

- Dashicons or the project’s existing icon source; never rely on colour alone.
- Icon-only controls need an accessible name and a `44px` practical hit area.
- Avatar supports initials, image, fallback colour, presence indicator, and optional team/owner label.
- Use avatars for lead owner, assignee, created-by, and activity actors.
- Do not use decorative avatars where identity is unavailable.

### 3.2 Text and labels

- Page title, section title, card title, field label, helper text, metadata, link, and eyebrow label.
- Truncate long record names visually but preserve the full value in accessible text or a title.
- Numeric and currency values use tabular numerals and consistent locale formatting.
- Empty values use an explicit muted em dash or “Not set”; never silently collapse a field label.

### 3.3 Buttons

| Variant | Use |
|---|---|
| Primary | One preferred action: Add Lead, Save Settings, Create Quote |
| Secondary | Navigation, filtering, neutral alternatives |
| Tertiary/text | Low-emphasis inline actions |
| Icon-only | Toolbar and compact row actions with accessible label |
| Danger | Delete, reset, revoke, or other irreversible actions |
| Loading | Preserve label and show progress; prevent duplicate submission |

Rules: one dominant header action, visible focus, disabled state with explanation where useful, and explicit wording for destructive consequences.

### 3.4 Inputs

- Text, email, telephone, number, currency, date, date range, time, URL, password, textarea, select, multi-select, combobox, search, checkbox, radio, switch, file upload, and hidden/readonly field.
- Labels sit above controls. Help text sits directly below. Validation stays adjacent to the failing control.
- Use a combobox for searchable record or catalogue selection; use a select only for short stable lists.
- Group related choices inside a bordered or soft-surface fieldset.
- Readonly data should look intentional, not disabled.

### 3.5 Selection and filtering

- Search field with search icon, clear action, loading state, and result count.
- Filter button opens a popover or drawer containing labelled filter groups.
- Active filters appear as removable chips and can be cleared individually or all at once.
- Saved view control contains view name, selected columns, filters, sort, and grouping.
- Preserve filters and pagination after a record mutation where possible.

### 3.6 Status primitives

- Badge: compact semantic status such as New, Qualified, Won, Failed.
- Chip: passive metadata or an explicitly interactive filter.
- Dot/presence: supplementary only; pair with text for important states.
- Progress bar: completion or allocation; include a numeric or textual equivalent.
- Never communicate status through colour alone.

## 4. Composite components

### 4.1 Page header

An `idlm-header` contains breadcrumb or module label, title, one-sentence purpose, optional scope/date selector, and page actions. Keep actions stable across view changes. Use a compact header on dense list pages and a larger identity header on record pages.

### 4.2 View switcher

Use peer tabs or segmented controls for List, Kanban, Dashboard, and other equivalent views. Each view may retain its own sort/filter state, but switching views must preserve the current object scope when possible.

Required states: active, hover, focus, disabled, loading, and no-permission/hidden. New tab systems need `tablist`, `tab`, `tabpanel`, keyboard arrow navigation, and persistent selection.

### 4.3 Metric card

```text
Label / optional trend      [optional icon]
32                          
Supporting definition or comparison
```

Metric cards may be clickable drill-down controls. The displayed number and resulting population must match exactly. Use semantic treatment only for actual attention or trend meaning.

### 4.4 Data table

- Header row, optional bulk-select column, identity column, status columns, compact metadata, and trailing actions.
- Critical identity fields stay left; actions stay right.
- Use a horizontal scrolling wrapper rather than shrinking text below legibility.
- Sortable headers expose `aria-sort` and keyboard activation.
- Row hover is subtle; avoid heavy striping.
- On mobile, use priority columns plus a mobile card or horizontal scroll for essential fields.

### 4.5 Kanban board

- Horizontally scrollable board with fixed-width columns and semantic stage accents.
- Column header: stage name, count, total value, and optional add action.
- Card: identity, owner, value, age/next action, status, and compact metadata.
- Drag state, target state, and keyboard alternative must be visible and announced.
- Clicking a card opens the same lead drawer as the list.
- On mobile, prefer stage tabs with one vertical card list when horizontal scanning becomes impractical.

### 4.6 Record row/card

Record identity is the reusable unit across list, board, search results, recent activity, and relation pickers.

```text
[avatar] Primary name       [status]
        secondary identity / source / owner
        next action or last activity       [menu]
```

The whole row/card may open the record; nested buttons must stop propagation and have distinct labels.

### 4.7 Record drawer

Use for lead details when the user should retain list or Kanban context. Header contains identity, status, owner, and close. Body uses tabs or sections for Details, Activity, Tasks, Quotations, and integrations. Footer holds save and contextual actions.

- Desktop: right-side drawer with one or two detail columns.
- Below `820px`: collapse form columns.
- At `700px` and below: full-width mobile workspace.
- Lock background scroll, restore focus, support Escape, and announce save/error results.

### 4.8 Modal and popover

- Modal: short blocking decision or focused form.
- Popover: transient filter, menu, date range, or field picker.
- Confirmation names the object and consequence.
- Long bodies scroll independently; headers and actions remain available.
- Reuse `window.IDLMDialog` and `.idlm-dialog` for blocking dialogs.

### 4.9 Activity timeline

Each event contains actor/avatar, event type, timestamp, concise title, optional body, source/integration marker, and available action. Group events by day where useful. Keep system events visually quieter than human notes, but never hide audit-relevant information.

### 4.10 Task and reminder item

Use checkbox/status, task title, due date, owner, priority, and overflow menu. Overdue is a semantic warning; completed items remain discoverable with reduced emphasis. Empty task lists offer one direct next action.

### 4.11 Relation picker

Searchable picker for linking leads, users, quotations, campaigns, or catalogue items. Show identity, type, and current relationship. Support loading, no results, create-new where authorized, selected state, and removal.

### 4.12 Form section and field group

Group fields by user goal: Contact, Qualification, Assignment, Follow-up, Attribution, Commercials, and Integrations. Each group has a short title, optional explanation, and consistent two-column-to-one-column responsive grid.

### 4.13 Quote line-item editor

Use a table on desktop and item cards on mobile. Keep item, quantity/unit, rate, total, and row actions together. Separate commercial editing from totals, payment milestones, revision history, and finalization actions. Finalized revisions are read-only.

### 4.14 Chart and data visualization

Chart card anatomy: title, definition/subtitle, compact toolbar, chart region, legend, and accessible data-table alternative. Include loading, no-data, partial-data, and error states. Charts must not imply positive/negative meaning solely through colour.

### 4.15 Settings section

Use goal-oriented cards, not option-name groupings. Each section supports labels, explanation, field grouping, inline validation, save state, connection test result, and permission-aware controls. Keep Danger Zone isolated from normal save actions.

### 4.16 Log/event table

Columns: timestamp, event, object, actor/source, status, and action. On mobile, render semantic cards with the same information. Payload inspection is lazy, copyable, permission-checked, and presented in the shared dialog.

## 5. Page templates

### List template

`Page header → metric strip → view/filter toolbar → table → pagination → record drawer`.

### Board template

`Page header → metric strip → filter toolbar → stage board → record drawer`.

### Dashboard template

`Page header → scope/date controls → insight banner → KPI grid → chart/panel grid → action queue → accessible data tables`.

### Record template

`Back/breadcrumb → identity header → status/owner actions → overview fields → related records → activity/timeline → sticky actions`.

### Editor template

`Page header → goal-based sections → inline validation → sticky save state → secondary diagnostics`.

### Settings template

`Settings navigation/tabs → section header → grouped cards → save/test feedback → help or diagnostics`.

## 6. State catalogue

Every data-backed reusable component must account for:

- Initial/loading: skeleton or inline spinner close to the affected region.
- Empty: explain what is absent and provide the next useful action.
- No results: reflect active filters and offer clear filters.
- Partial/unknown: explain freshness or unavailable fields.
- Success: concise inline or toast feedback; restore focus appropriately.
- Validation error: identify the field, problem, and correction.
- Request failure: preserve entered data and provide retry.
- Permission denied: hide unavailable mutations; explain read-only access where needed.
- Offline/stale: show freshness and avoid implying that a mutation completed.
- Destructive confirmation: state object, action, and consequence.
- Reduced motion: disable nonessential animation under `prefers-reduced-motion`.

## 7. Twenty-inspired CRM patterns to adopt

| Twenty concept | IDLM adaptation |
|---|---|
| Objects and fields | Leads, users, quotations, catalogue items, tasks, and integration events |
| Multiple views | List, Kanban, dashboard, logs, and quotation views sharing object scope |
| Configurable layouts | Saved filters, visible columns, grouping, and compact/expanded detail sections |
| Persistent workspace | Existing `.idlm-workspace` shell and context-preserving drawers |
| Record-centric navigation | Lead identity remains stable across table, board, search, and drawer |
| Activity and relations | Timeline, tasks, quotations, owner, source, and linked records |
| Command/keyboard efficiency | Search, predictable shortcuts where implemented, focus restoration, keyboard DnD alternative |
| Extensible CRM building blocks | New modules compose the primitives and templates in this document |

## 8. Responsive and accessibility contract

- Canonical breakpoints: `1280px`, `1024px`, `782px`, and `480px`.
- Reflow or scroll dense data; do not delete essential operations on mobile.
- Minimum control height: `40px`; practical touch target: `44px`.
- Every control has a label or accessible name.
- Focus is visible and uses `--idlm-focus-ring`.
- Drawers and dialogs trap focus, support Escape, restore focus, and lock background scroll.
- Status, validation, loading, and drag feedback include text or assistive announcements.
- Tables, tabs, menus, forms, and charts retain semantic equivalents.
- Preserve browser zoom and user text scaling.

## 9. Implementation rules

- Scope selectors beneath `.idlm-wrap`, `.idlm-workspace`, or a specific `idlm-` root.
- Prefix new classes, hooks, handles, and custom properties with `idlm-`/`IDLM_` as appropriate.
- Reuse existing PHP templates, CSS tokens, JS utilities, and shared overlays before adding variants.
- Keep frontend and admin implementations visually aligned while preserving their existing contracts.
- Enqueue only the assets required by the current module or shortcode.
- Keep permissions, nonces, object access, escaping, and server-side validation independent of visual state.
- Do not use inline styles for reusable components.
- Update [`docs/DESIGN.md`](docs/DESIGN.md) or the relevant page override when a reusable rule changes.

## 10. Component acceptance checklist

- [ ] Uses an existing token, primitive, template, or clearly documents a new one.
- [ ] Has default, hover, focus, active, disabled, loading, empty, error, and permission-aware states where relevant.
- [ ] Works in workspace, admin, drawer/modal, and mobile contexts where applicable.
- [ ] Has accessible names, keyboard behaviour, focus restoration, and non-colour status cues.
- [ ] Preserves filters, record context, and user-entered data during asynchronous actions.
- [ ] Does not introduce a second breakpoint or parallel visual language.
- [ ] Reuses existing IDLM classes and contracts before adding CSS or JavaScript.
- [ ] Has been checked against the relevant page override and visual regression matrix.

## 11. Reference notes

Twenty’s repository describes a CRM built from objects, fields, views, layouts, workflows, and extensible primitives. Those concepts informed the structure above. The implementation remains intentionally IDLM-specific: gold/brown branding, WordPress delivery, custom-table CRM data, capability-aware actions, quotations, advertising integrations, and the existing responsive/accessibility contract.
