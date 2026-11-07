# Leads — Page Rules

The Leads view is the primary operational queue. These rules refine the master system.

Phase 1 foundation is implemented: Leads filters/forms are programmatically labelled and callback, Won, Quote Shared, Lost, and unsaved-change decisions use the shared accessible dialog pattern.

Phase 2 core workflow is implemented: row selection and bulk state remain synchronized, destructive bulk changes use an accessible confirmation, import exposes labelled steps/progress and downloadable row failures, and export announces the matched count before download.

Phase 3 responsive workflow is implemented: primary/secondary/low column priorities compact the table at `1024px`; at `782px`, each row becomes a keyboard-operable card with Show/Hide details and a labelled mobile sorting control. Existing selection, filters, pagination, drawer sequence, and role-aware controls remain authoritative.

- Header: role-specific title, one-line purpose, one primary “Add lead” action, secondary import/export actions.
- Add lead: outside pre-sales, a Qualification fieldset offers three bordered choices (Qualified now, add details later, ask pre-sales). The qualification-only fields hide unless Qualified now is chosen. The whole form scrolls as one, with the footer pinned. Errors keep the typed values and focus the field named by the server.
- Summary metrics are keyboard-operable filters and expose selected state.
- Filter drawer groups Date, Ownership, Lifecycle, and Engagement. Apply/Clear remain visible; every label is associated with its control.
- Table priority is identity, next action, stage/quality, owner, age, then attribution. Use an expandable row or lead drawer for secondary fields on narrow screens.
- Lead drawer preserves table filters, page, selection, and previous/next position. It follows the complete dialog contract in `MASTER.md`.
- Lead drawer status metadata uses two levels of emphasis: call status, stage, assigned rep, and lead quality form the primary summary; conditional reasons and created date share one quieter context line. Use spacing and sparse dividers instead of a bordered card for every field.
- Bulk delete is visually separated from routine bulk actions and names the selected count in its confirmation.
- Import is a four-step labelled progress flow: Upload → Map → Preview → Import. Use a progress indicator, live status, recoverable row errors, and no emoji status icons.
- Export uses a labelled fieldset for scope and reuses current filters by default, clearly stating the resulting record count before download.

## Design layer decisions (Shashi, 2026-11-05)

The CRM design is the only design. Styles live in `assets/css/crm-design-leads.css`.

- **Header.** Title matches the navigation label ("Leads") for every role. Order: search, Filters, quiet attention counts, More, Add lead. Import, Export and Routing settings sit in the More menu for the roles that already had them. Attention counts are text with a coloured number and show pressed state; they stay on their own row at `1280px` and below.
- **Summary strip.** Needs-attention counts come first. One line above `1280px`, four columns up to `782px`, a self-scrolling strip on phones. Selected uses `aria-pressed` plus an action border; alert colours the number only. Scope stays visible in the hint.
- **Result line.** The count and removable filter chips sit above the table. Chips come from the filter panel's own selects, so they cannot drift from what is applied. Phones share this line with the sort control.
- **Filter panel.** Groups: Date, Lifecycle, Source and owner, Engagement. Filters apply as they change, so the footer holds Clear all and a Show N leads button that closes the panel.
- **Rows.** 44px, one or two lines, `table-layout: fixed` with no sideways scroll. Name and flags, then phone and email on one line. Handoff flags are short ("Awaiting 114h"); the full wording and location move to hover and the drawer, and location stays visible on phone cards. Stage, owner and quality stay editable and read as values until hover or focus.
- **Column priority.** Above `1280px`: all columns, with studio beneath channel. To `1024px`: dates drop to the drawer. Down to `783px`: channel, budget and tasks also drop beside an open sidebar and return as the sidebar collapses. Phones use cards with a corner details control. Pre-sales drops age and details at `1280px`, and response at `1024px`.
- **States.** Skeleton on first load, dimmed table on refresh, retry on error. Empty wording distinguishes "no matches" (Clear filters), "no leads yet" and a clear pre-sales queue.
- **Drawer.** Fixed height, top-aligned frame. Previous, next and position sit beside close, never over the page or footer. Identity then contact, then the four status values, then created and assigned dates. Form groups are Contact, Qualification and deal, Project, Quote and studio. Attribution collapses and lists recorded values only. Early close is a disclosure. Comments stay beside the form above `1024px`.
- **Footers.** Delete is quiet and far left, primary last. Phones keep one row so the form keeps the screen. A quiet "Unsaved changes" appears as soon as a field changes.
- **Inline presentation.** The Leads template, row, drawer and activity markup carry no `style` attributes except `display:none`, which scripts toggle, and their labels are written in sentence case in the source. Nothing rewrites markup at render time.
