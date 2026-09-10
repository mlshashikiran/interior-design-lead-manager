# Interior Design Lead Manager — Master Design System

> Source of truth for all CRM UI. Before changing a screen, check `pages/<screen>.md`; page rules may refine but must not weaken this file.

**Product:** Operational CRM for an interior-design sales team  
**Design owner / builder:** Shashi  
**Version:** 1.7  
**Last reviewed:** 2026-09-03  
**UI direction:** Calm, friendly, professional, data-dense, and task-led

**Phase 1 implementation:** Completed 2026-09-01 by Shashi. Canonical runtime tokens, accessible primary actions, focus-visible/reduced-motion behavior, the shared dialog controller, requested Leads/Kanban/quotation decision dialogs, and affected form labels/inline errors now follow this contract.

**Phase 2 implementation:** Completed 2026-09-01 by Shashi. Kanban keyboard movement and semantic cards, Leads selection/bulk/import/export workflows, and accessible Dashboard tabs/chart data alternatives now follow this contract.

**Phase 3 implementation:** Completed 2026-09-01 by Shashi. Leads priority/expandable rows, phone Kanban stage tabs/list, quotation mobile item cards, and affected breakpoint, control, motion, and layer rules now follow this contract.

**Phase 4 implementation:** Completed 2026-09-01 by Shashi. Settings and shared UI presentation now use canonical runtime tokens/classes, active module CSS no longer consumes legacy aliases, and automated debt/state/role/responsive contracts guard the migration.

**Phase 5 implementation:** Completed 2026-09-02 by Shashi. Authentication and account forms now expose labelled password reveal, visible guidance, nearby matching errors, pending submission feedback, focused redirect notices, complete autofill metadata, and a programmatically focusable skip destination.

**Phase 6 implementation:** Completed 2026-09-02 by Shashi. Settings now expose goal-oriented tabs, structured vocabulary editors, persistent save-state feedback, consistent credential controls, and nearby connection-test results without changing their storage or authorization contracts.

**Phase 7 implementation:** Completed 2026-09-02 by Shashi. Users and operational logs now expose responsive labelled filters, explicit access safeguards, semantic table/card views, inline retry status, and lazy redacted payload inspection through the shared accessible dialog without changing their administrator-only boundaries.

**Phase 8 implementation:** Completed 2026-09-03 by Shashi. White-label settings now provide grouped identity, media, colour, workspace, attribution, preview, validation, and reset controls; saved branding is consumed by plugin-owned sign-in, workspace, account, and favicon surfaces through scoped tokens that remain independent from active theme and page-builder settings.

## 1. Product principles

1. **Next action is obvious.** Every screen prioritizes the decision or task that moves a lead forward.
2. **Context is preserved.** Drawers and focused overlays keep filters, pagination, board position, and unsaved state intact.
3. **Dense, not cramped.** Tables and boards scan quickly; forms and decisions receive more space.
4. **Status is explicit.** Colour supports a visible text label, icon, count, or pattern and is never the only signal.
5. **Progressive disclosure.** Start with the essential record summary; reveal history, diagnostics, and advanced settings on demand.
6. **Accessible by default.** WCAG 2.2 AA contrast, keyboard access, focus order, target size, announcements, and reduced motion are component requirements.
7. **One system.** CRM modules use the same tokens and components in wp-admin and the frontend workspace.

## 2. Brand and colour tokens

The configured gold remains the brand accent. It is not an accessible white-text button background.

| Token | Value | Use |
|---|---:|---|
| `--idlm-brand-gold` | `#C99644` | Decorative accents, selected indicators, charts with adjacent labels |
| `--idlm-action` | `#7A5017` | Primary buttons and actionable text on light surfaces |
| `--idlm-action-hover` | `#603D10` | Primary hover/pressed state |
| `--idlm-action-soft` | `#FFF4DF` | Selected or highlighted background |
| `--idlm-info` | `#176A9E` | Links, information, New-state accents |
| `--idlm-success` | `#177C51` | Success, Qualified, Won |
| `--idlm-warning` | `#855A00` | Warnings, overdue, attention |
| `--idlm-danger` | `#C84635` | Error, destructive action, Lost |
| `--idlm-text` | `#17212B` | Primary text |
| `--idlm-text-muted` | `#56657A` | Secondary text; never use lighter text for body copy |
| `--idlm-surface` | `#FFFFFF` | Cards, tables, drawers, inputs |
| `--idlm-surface-soft` | `#F8FAFC` | Grouping, table headers, secondary panels |
| `--idlm-canvas` | `#EEF2F6` | Application background |
| `--idlm-border` | `#D9E1EB` | Standard dividers |
| `--idlm-border-strong` | `#B9C6D5` | Hover, active, and stronger separation |
| `--idlm-sidebar` | `#111A24` | Workspace navigation |

Semantic soft backgrounds: success `#E8F6EF`, warning `#FFF6D7`, danger `#FFF0EC`, info `#E7F4FF`. Text over these surfaces uses the corresponding dark semantic token. White text on `--idlm-brand-gold` is forbidden; use `--idlm-action` or dark text on gold.

### Contrast contract

- Body text: at least `4.5:1`.
- Large text and non-text controls: at least `3:1`.
- Focus indicator against both control and surrounding surface: at least `3:1`.
- Disabled controls remain readable and identifiable; opacity alone must not communicate their state.

## 3. Typography

Use the locally bundled Inter family. Do not add remote font imports.

| Role | Size / line height | Weight |
|---|---:|---:|
| Page title | `22px / 28px` | 700 |
| Section title | `16px / 22px` | 650–700 |
| Card title | `14px / 20px` | 600–700 |
| Body/control | `13px / 1.5` | 400–600 |
| Compact body/table | `12px / 18px` | 400–600 |
| Label/table heading | `10px / 15px` | 600–700 |
| Metadata/badge | `9px / 13px` | 600–700 |
| Metric | `18–22px / 1.1` | 700 |

Use the equivalent `rem` value in runtime CSS so browser text scaling remains effective. On screens up to `782px`, ordinary body text rises to `14px` and text-entry controls remain `16px` to support comfortable touch input. Sentence case is standard. Uppercase is limited to short table headers and badges. Operational copy uses plain terms: “Save account”, “Move to Won”, “No leads match these filters”.

## 4. Spacing, shape, and layout

- Spacing scale: `4, 8, 12, 16, 20, 24, 32, 40, 48px`.
- Compact table cells: `10px 12px`; form groups: `16px`; card padding: `20–24px`; sections: `24–32px`.
- Control minimum height: `40px`; primary mobile actions and icon-only targets: `44px`.
- Radius: `6px` compact, `8px` controls/cards, `12px` large panels, `999px` pills.
- Shadows: border-first. Use a small shadow for cards and one strong shadow only for drawers/modals.
- Content width: fill the workspace with `min-width: 0`; never solve overflow with arbitrary fixed page widths.
- Breakpoints: `480px` small phone, `782px` WordPress/mobile navigation, `1024px` compact desktop, `1280px` wide dashboard.

## 5. Motion and layers

- Durations: `150ms` hover/focus, `200ms` component state, `250ms` drawer/modal entrance.
- Animate only `color`, `background-color`, `border-color`, `box-shadow`, `opacity`, and `transform`. Never use `transition: all`.
- Hover must not change layout. Respect `prefers-reduced-motion: reduce` in every module.
- Layer tokens: base `0`, sticky `20`, dropdown `1000`, filter backdrop `99990`, filter panel `99999`, modal backdrop `100000`, modal `100100`, toast `100200`.

## 6. Interaction foundation

- Every clickable element is a native `button` or `a`; clickable headings, spans, and divs are forbidden.
- Use `:focus-visible` with a `2px` action-colour ring and `2px` offset. Do not remove `outline` without the replacement.
- Form controls have visible labels, programmatic `for`/`id` association, correct `type`, `inputmode`, `autocomplete`, required indication, help text, and nearby errors.
- Loading, saving, success, and failure states use `role="status"`/`aria-live` or `role="alert"` as appropriate.
- Preserve the trigger and restore focus when drawers, filters, or dialogs close.
- Destructive actions state the object and consequence, require confirmation, and are separated from routine actions.

## 7. Core components

### Buttons

- Primary: one preferred action per region; `--idlm-action` with white text.
- Secondary: white surface, strong border, primary text.
- Quiet: text/icon action for low-priority row operations.
- Danger: danger text/border for reversible preparation; filled danger only in the final destructive confirmation.
- Icon-only buttons have an accessible name and `44 × 44px` target (visual icon may remain `20px`).
- Standard buttons use the shared `--idlm-button-radius` (`8px`); pill radius is reserved for status badges and compact status filters, and circular radius is reserved for floating icon actions.
- Loading buttons retain their width, expose status text, and block duplicate submission.

### Inputs and validation

- All controls share `40px` height, `8px` radius, `14px` text, and visible hover/focus/error states.
- Validate on blur for ordinary fields and on change for constrained selections; summarize errors at form top only when multiple fields fail.
- Use fieldsets and legends for related radio/checkbox groups.
- Secrets have show/hide, copy, masked stored state, and connection-test patterns; never rely on a prefilled password field to indicate configuration.

### Cards and metrics

- One card represents one user goal, not one field.
- Static cards do not receive a pointer cursor or lift-on-hover.
- Interactive metrics are buttons, include their filter effect in the accessible name, and show selected state after activation.
- Metric labels include scope/time basis where ambiguity is possible.

### Tables

- Include a caption (visible or screen-reader-only), scoped headers, sortable-button names, and selection labels.
- Sticky identifying and action columns are allowed on wide tables. The wrapper may scroll horizontally and must show a subtle overflow cue.
- Mobile priority: identity → next action → stage/status → owner → time; secondary fields move into an expandable row or record drawer.
- Bulk selection exposes a sticky action bar, selected count, clear selection, and consequence-aware confirmation.

### Tabs

- Use `role="tablist"`, `role="tab"`, `aria-selected`, `aria-controls`, and associated `role="tabpanel"` when tabs switch content without navigation.
- Arrow keys move between tabs; URL/hash stores the selected view when useful.
- Tab bars scroll horizontally on narrow screens and never wrap to uneven rows.

### Drawers and dialogs

- Drawer: `role="dialog"`, `aria-modal="true"`, labelled title, focus trap, Escape close, focus restoration, background inert/scroll lock.
- Modal close control is always a button. A bare `span` containing `×` is forbidden.
- Header and footer stay visible while long content scrolls; primary action is last/right on desktop and full-width on phones.
- Only one modal layer may be active. Nested workflows use an in-dialog step or close the parent first.

### Status, empty, and feedback states

- Badges always include text. Remove emoji UI icons; use one Dashicons or SVG set with `aria-hidden="true"` when decorative.
- Empty states say what is missing, why it matters, and the next available action.
- Skeletons preserve layout for lists/charts. Do not blank the entire workspace during a regional update.
- Toasts confirm completed background actions; errors requiring correction remain inline.

## 8. CRM navigation and information architecture

Primary order follows daily work: Dashboard → Leads → Pipeline → Quotations → Tasks/Logs → Users → Settings → My Account. Role capability controls visibility without changing the order of shared items.

Every page uses: page header → operational summary → filters/local navigation → primary work surface → details/secondary actions. Keep one dominant page-header action.

## 9. Charts and data visualization

- Line chart: trends over time. Bar chart: ranked comparison. Funnel: ordered stage conversion. Avoid doughnuts when precise comparison matters.
- Limit categorical palettes; semantic colours keep their semantic meaning. Never use red/green alone to compare series.
- Every canvas has an accessible name and a nearby “View data table” alternative containing the same values.
- Tooltips repeat label, value, unit, period, and percentage where relevant.
- Empty and loading chart states are explicit. Chart updates announce the selected period without stealing focus.
- Funnel stages show count, conversion from previous stage, and overall conversion.

## 10. Responsive contract

- Verify at `375`, `768`, `1024`, and `1440px`, plus 200% browser zoom.
- No page-level horizontal scrolling. Purposefully wide tables/boards scroll only inside their labelled region.
- At `1024px`, compact dense surfaces by priority while retaining native controls and contained overflow regions.
- At `782px`, Leads become priority-based cards with an explicit details disclosure and mobile sort control; Kanban becomes stage tabs plus one vertical list; editable quotation lines become structured item cards.
- At `480px`, action groups and pagination wrap or stack without shrinking controls below the target-size contract.
- Desktop sidebar becomes a compact top/off-canvas navigation at `782px`; provide a skip link to `#idlm-workspace-content`.
- Drawers become full-screen workspaces on phones. Filters become an off-canvas dialog with Apply and Clear actions.
- Mobile forms use one column and appropriate keyboards. Sticky actions must not cover the final field.

## 11. Governance and delivery checklist

1. Use tokens; do not add a hard-coded colour, spacing, radius, shadow, duration, breakpoint, or z-index without documenting a new token.
2. Prefer shared component classes over inline styles and `!important`.
3. Test keyboard-only operation, screen-reader names/state, contrast, reduced motion, responsive widths, empty/loading/error/success, and each role/capability state.
4. Confirm no emoji icons, click-only divs/spans, inaccessible canvas-only data, or drag-only mutations.
5. Record page-specific deviations in `pages/` and update the UI audit backlog when debt is resolved.
