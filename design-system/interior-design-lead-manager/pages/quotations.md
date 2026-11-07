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

## CRM design layer rules (Shashi)

Markup lives in the `crm_design()` branches of `IDLM_Quotation_Renderer`, styles in `assets/css/crm-design-quotations.css`, and behaviour behind `crmDesign` in `assets/js/quotations.js`. The JS row builders must match the PHP rows, because the live list replaces them.

**Documents.** Print, PDF, CSV and XLSX never read the design layer. `render_readonly_items()` only changes screen formats when `$include_summary` is false, which only the detail page passes.

**Formats.** Screen dates use the Leads format (`Oct 17, 2026`, `6:33 PM`). Screen money uses `display_money()`, with Indian grouping for INR, matching the live editor totals. Revisions show as `R03`, never the full revision name.

**List.** Status and revision share a cell, with status as written text. A creator is never "Unknown": `created_by = 0` is `Migrated` and a missing account is `Former user`. Filters apply live and are kept in the URL, so opening a record and coming back keeps filters and page. Empty, no-results and failed-update states are distinct, and a failed update keeps the rows on screen with a Retry toast.

**Detail.** One highlights strip (customer, property, revision, creator, grand total). Saved totals are shown so the grand total reconciles with the line items, and are never recalculated. An earlier revision says so and links to the current one. Migrated quotations with no items say why instead of showing an empty table.

**Editor.** Stays free of the document-content review panel. The sticky bar carries autosave status and a running grand total; it steps aside on phones while a field has focus. Removing a row or section offers Undo, because autosave would otherwise make the loss permanent. Enter in a search box never submits the quotation. A new quotation with no lead is stopped in the browser, because the server refusal would lose what was typed. The catalogue dropdown is positioned by script so the scrolling table cannot clip it. Locked catalogue values read as text, not disabled inputs. The section drag handle is hidden because no dragging exists.

**Catalogue.** The table is the surface, with no panel around it. Secondary actions sit in a Manage menu. Bulk actions appear only once something is selected. Archive and delete name the count and the consequence, and cancelling a row action clears its selection. Archived replaces "Inactive" in the interface; the stored value does not change.

**Setup.** Save is sticky and steps aside on phones while typing. Leaving with unsaved changes warns. Milestones over 100% are stopped before the request, so long HTML fields are not lost to a server refusal. The numbering preview updates as you type.

**Known limits.** The list cannot filter by status without a repository change. The read-only detail table keeps its legacy `960px` `!important` minimum, so on phones it scrolls inside its own labelled region.
