# Leads — Page Rules

The Leads view is the primary operational queue. These rules refine the master system.

Phase 1 foundation is implemented: Leads filters/forms are programmatically labelled and callback, Won, Quote Shared, Lost, and unsaved-change decisions use the shared accessible dialog pattern.

Phase 2 core workflow is implemented: row selection and bulk state remain synchronized, destructive bulk changes use an accessible confirmation, import exposes labelled steps/progress and downloadable row failures, and export announces the matched count before download.

Phase 3 responsive workflow is implemented: primary/secondary/low column priorities compact the table at `1024px`; at `782px`, each row becomes a keyboard-operable card with Show/Hide details and a labelled mobile sorting control. Existing selection, filters, pagination, drawer sequence, and role-aware controls remain authoritative.

- Header: role-specific title, one-line purpose, one primary “Add lead” action, secondary import/export actions.
- Summary metrics are keyboard-operable filters and expose selected state.
- Filter drawer groups Date, Ownership, Lifecycle, and Engagement. Apply/Clear remain visible; every label is associated with its control.
- Table priority is identity, next action, stage/quality, owner, age, then attribution. Use an expandable row or lead drawer for secondary fields on narrow screens.
- Lead drawer preserves table filters, page, selection, and previous/next position. It follows the complete dialog contract in `MASTER.md`.
- Lead drawer status metadata uses two levels of emphasis: call status, stage, assigned rep, and lead quality form the primary summary; conditional reasons and created date share one quieter context line. Use spacing and sparse dividers instead of a bordered card for every field.
- Bulk delete is visually separated from routine bulk actions and names the selected count in its confirmation.
- Import is a four-step labelled progress flow: Upload → Map → Preview → Import. Use a progress indicator, live status, recoverable row errors, and no emoji status icons.
- Export uses a labelled fieldset for scope and reuses current filters by default, clearly stating the resulting record count before download.
