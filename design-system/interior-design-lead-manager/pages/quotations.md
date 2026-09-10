# Quotations — Page Rules

Phase 1 foundation is implemented for the autosaved unsaved-change decision dialog and shared action, focus, control-size, and reduced-motion tokens.

Phase 3 responsive workflow is implemented: editable item rows become labelled two-column cards at `782px`, with the item search and action group spanning the card. Calculations, catalogue lookup, autosave, revisions, section/item ordering, and print rendering keep their established contracts.

- List view prioritizes quote number/title, customer, revision/status, value, owner, updated time, and next action.
- Editor order: customer and quote identity → sections/items → summaries/totals → milestones/notes → workflow actions.
- Line-item tables may scroll within their region; sticky Item and Total columns preserve context. Mobile supports a structured item-card editor.
- Every icon/symbol row action has a visible tooltip and accessible name; keyboard reordering is available in addition to drag/move controls.
- Autosave status is persistent but quiet. Cancel explains whether it will keep, discard, or convert the working copy.
- Final revisions are visibly read-only. Workflow status and permitted next action appear together.
- Print/PDF uses print-specific typography and excludes application controls without shrinking critical text below legibility.
