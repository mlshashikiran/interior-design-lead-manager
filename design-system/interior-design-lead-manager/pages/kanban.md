# Kanban — Page Rules

Phase 1 foundation is implemented for the Won, Quote Shared, and Lost decision dialogs, including initial focus, trapping, Escape, trigger restoration, native close controls, and inline errors.

Phase 2 core workflow is implemented: cards open through a native action, pointer drag is isolated to an explicit handle, and “Move to stage” provides keyboard movement with shared validation, focus restoration, and live status.

Phase 3 responsive workflow is implemented: at `782px`, a keyboard-operable stage tablist exposes one vertical stage list and disables pointer dragging. Desktop columns and drag handles resume above the breakpoint; “Move to stage,” lifecycle validation, focus restoration, and live announcements remain shared.

- The board begins with filtered lead count, current-month Won count/value, filter launcher, and sort control.
- Each column header shows stage, visible count, and relevant monetary total. Totals must match the filtered cards.
- Cards expose identity, owner, next action/overdue state, source, and commercial value only when relevant.
- Opening a card is a native button/link action. Do not attach click behavior only to a heading.
- Drag and drop is an enhancement, not the sole stage-change method. Every card includes a keyboard-accessible “Move to stage” action using the same validation/modals.
- Announce successful movement and updated column counts in a polite live region. Restore focus to the moved card or its new position.
- Columns remain approximately `300px` wide inside a labelled horizontal scroll region. On phones, offer stage tabs/list view instead of requiring long two-axis scrolling.
