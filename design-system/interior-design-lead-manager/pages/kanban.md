# Kanban — Page Rules

Phase 1 foundation is implemented for the Won, Quote Shared, and Lost decision dialogs, including initial focus, trapping, Escape, trigger restoration, native close controls, and inline errors.

Phase 2 core workflow is implemented: cards open through a native action, pointer drag starts anywhere on the card (the move icon stays as a visual cue; changed 2026-11-06 at the owner's request), and “Move to stage” provides keyboard movement with shared validation, focus restoration, and live status.

Phase 3 responsive workflow is implemented: at `782px`, a keyboard-operable stage tablist exposes one vertical stage list and disables pointer dragging. Desktop columns and card dragging resume above the breakpoint; “Move to stage,” lifecycle validation, focus restoration, and live announcements remain shared.

- The board begins with filtered lead count, current-month Won count/value, filter launcher, and sort control.
- Each column header shows stage, visible count, and relevant monetary total. Totals must match the filtered cards.
- Cards expose identity, owner, next action/overdue state, source, and commercial value only when relevant.
- Opening a card is a native button/link action. Do not attach click behavior only to a heading.
- Drag and drop is an enhancement, not the sole stage-change method. Every card includes a keyboard-accessible “Move to stage” action using the same validation/modals.
- Announce successful movement and updated column counts in a polite live region. Restore focus to the moved card or its new position.
- Columns remain approximately `300px` wide inside a labelled horizontal scroll region. On phones, offer stage tabs/list view instead of requiring long two-axis scrolling.

## CRM redesign decisions (Shashi, 2026-11-05)

- Title is "Pipeline", matching the navigation. The summary is one bordered strip (leads on board, follow-ups due, open pipeline value, won this month, won revenue), and on phones it scrolls sideways inside itself. "Leads on board" equals the sum of the column counts.
- The board fills the viewport below the strip, so the page itself does not scroll. Columns are `18rem`; each scrolls vertically inside the one horizontal region. Edge shadows show when more stages sit off screen.
- Column header: a stage dot (neutral; green for Won, red for Lost), stage, count, and the money line only when the stage has one. No top stripe.
- Card, about 90px on desktop: name and value (quote or deal value when present, otherwise budget), one next-step line (overdue task, pending tasks, Needs update, then call status), then owner and quiet icon controls. Email, phone and source are in the card's hover title and the lead drawer. Unassigned shows a dashed avatar with the words "Unassigned".
- Drag handle and "Move to stage" stay, borderless until hover or focus. Pressing Enter or Space on the handle opens the Move to stage dialog. On phones the handle is hidden and "Move to stage" shows its label at 44px.
- Drag states use `is-dragging`, `is-drag-source` and `is-drop-target` (not the legacy `drag-over`, which carries `!important`). The target column names its destination.
- Dialogs name the lead and its current stage in their description. Cancelling Won, Quote Shared or Lost returns focus to that lead's "Move to stage" button after the board reloads.
- States: skeleton cards while a column loads, one board-wide error (retry) and one empty result (reset filters), and no "All N loaded" text. Load more only appears when there is more.
- Phones and tablets: underline stage tabs, sticky under the top bar, one list that scrolls with the page (two columns of cards at tablet width) and loads more as it nears the end.
- Needs update is amber everywhere (header filter, card chip) and the header filter exposes `aria-pressed`.
