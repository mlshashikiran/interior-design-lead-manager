# Pre-sales Leads view review

**Owner / builder:** Shashi
**Branch:** `presales/leads-view` (on `streamline/plugin`)
**Date:** 2026-11-07
**Scope:** the CRM workspace Leads view (`?idlm_view=leads`) as a user with the Pre-Sales Representative role (`idlm_pre_sales`).

Pre-sales qualifies leads: call, log the outcome, then qualify or close as unreachable. They stop at the Qualified stage. They cannot reassign leads, and they have no pipeline or quotations. This review checks whether the screen helps with that loop and points out where it gets in the way.

Method: read the template, row renderer, drawer, AJAX handlers, script and stylesheet, then checked a disposable fixture as a seeded pre-sales user at 390, 1024 and 1440px with synthetic leads only (fresh, overdue callback, ready to qualify, details missing, wrong number, qualified, closed).

## What works

- The default engagement filter is **Action required**, which holds only undecided, active, non-duplicate leads. The queue sorts overdue callbacks first, then not called, then oldest.
- Each row has a written status (Overdue callback, Due today, Decision ready, …) plus one next step ("Log call 1 of 2", "Ready to qualify"). The status is text first, as STD-CRM-4 requires.
- Log call and Log WA work straight from the row, and Decide appears only when a decision is ready.
- The server enforces the important limits: stage moves stop at Qualified, reassignment needs `idlm_assign_leads`, the drawer stage header and sales fields are rejected for pre-sales, and early close checks reasons and evidence.
- The phone layout turns rows into cards with the next step and actions, and the drawer's phone number is a `tel:` link.

## Findings

Severity: **High** is a dead end or wrong result in the daily loop. **Medium** is friction or a misleading control. **Low** is polish.

| # | Area | Finding | Severity | Status |
|---|------|---------|----------|--------|
| F1 | Filters | Clear all, removing a filter chip, and switching off the Pending calls, Unread WhatsApp or Need update pill all set Engagement to "" (All leads), not back to Action required. All leads also counted as "no filter", so it showed no chip and no count. Pre-sales left their queue without any sign and began seeing decided leads. | High | Fixed |
| F2 | Summary | The **Qualified leads** card is computed over the current filter. Action required excludes qualified leads, so the card read 0 and clicking it showed an empty table (STD-CRM-5). | Medium | Fixed (hidden for pre-sales) |
| F3 | Drawer | On a decided lead (Qualified or Unqualified), Log outbound call, Log WhatsApp and Log final closure stayed enabled, but the server rejects them ("This pre-sales decision is complete"). The guidance still said "Qualify now, use structured Early Close…". | High | Fixed |
| F4 | Drawer | Pre-sales could open Early close on a lead they had already closed as Unqualified. | Medium | Hidden in UI; server question Q4 |
| F5 | Drawer | Log final closure and Close as unreachable were disabled with no reason given. | Medium | Fixed |
| F6 | Drawer | Qualify is enabled only from saved details. The guidance listed what was missing, but the fields themselves did not say so, and it never said to save first. | Medium | Fixed |
| F7 | Drawer | An empty Project type shows the first choice (Apartment) as if it were set. It counts as missing until the form is saved, and saving writes Apartment. | Medium | Fixed (Q5): "Choose a project type" placeholder in the drawer and in Add lead |
| F8 | Drawer | The Deal value, Discount, Quote shared, Quote value and quote upload fields were shown disabled to pre-sales. They are sales-only noise, and a disabled field never submits. The group titles said "Qualification and deal" and "Quote and studio". | Low | Fixed (not rendered; titles "Qualification", "Studio") |
| F9 | Drawer, phone | Six cycle cards came before the Log buttons, so on a phone the call → log step needed a scroll every time. | Medium | Fixed (actions and decision first, history after) |
| F10 | Rows | Decided rows (seen under My qualified history) showed two disabled buttons and no way to open the lead from Actions. | Medium | Fixed ("View") |
| F11 | Rows | Every row's buttons were announced as just "Log call", "Log WA" or "Decide", with no lead name. | Low | Fixed (accessible names name the lead) |
| F12 | Rows, 1440px | Response text ("Connected - Interested") ran into the Budget column because of a legacy `width: max-content`. | Low | Fixed |
| F13 | Rows, phone | The next step was cut off on cards ("Qualified — …"). | Low | Fixed (wraps) |
| F14 | Bulk | The bulk Change stage list offered every stage except Won. The server rejects anything after Qualified for pre-sales. | Medium | Fixed (New, Contacted, Qualified only) |
| F15 | Rows | Outreach targets were read once per row. | Low | Fixed (read once per page) |
| F16 | Bulk | Pre-sales can add leads to or remove them from the audience sync queue, and can mark leads Unqualified in bulk. Bulk Unqualified skips the decision log entry that the drawer writes. | Medium | Fixed (Q2, Q3): audience queue actions are administrator-only; pre-sales has no bulk Unqualified and no bulk Qualified |
| F17 | Security | `ajax_presales_decision` checks the nonce and the pre-sales or admin role, but it does not call `IDLM_Admin::require_ajax_access('rep')` the way the other CRM handlers do. Pre-sales users pass it today. | Low | Question Q1 |
| F18 | Wording | The "Today" column counts the active outreach cycle, which can be older than today. The Follow-up card counts all Call back requested leads, not only those due. | Low | Proposal |
| F20 | All popups | Every scroll lock (dialogs, the lead drawer, Filters, and the catalogue and bulk-item editors) set `overflow: hidden` on `body`. That removed a classic scrollbar (about 15px on Windows, or on a Mac set to always show scrollbars), so the page behind widened and shifted. Measured: Add lead moved 1398→1404px and the table widened 1114→1120px at 1440. | Medium | Fixed: `dialog.js` pads `body` by the scrollbar width, measured on the opening click or key, while locked |
| F19 | Filters | Date basis offers "Won date" and "Sales handoff date", and Stage lists every stage. Both are sales-side, but they can matter for My qualified history. | Low | Left as is |

## Implemented (presentation and workflow only)

None of these change a permission, an AJAX action, an option, a stored value or how lead data is written. Every rule the server enforces stays as it was.

1. **Filters keep the queue** (F1). Each filter's resting value now comes from the option the markup marks `selected`. That is Action required for pre-sales, Assigned date for date basis, and the first option everywhere else. Clear all, chip removal and switching off a pill all go back to the resting value. Choosing All leads shows a chip. Files: `assets/js/admin-app.js` (`leadFilterDefault`, `resetLeadFilter`).
2. **Summary reconciles** (F2). Pre-sales no longer sees the Qualified leads card. Their history stays in Engagement → My qualified history. File: `templates/admin/leads.php`.
3. **Decided leads stop offering outreach** (F3, F4, F10). The drawer hides the Log buttons for decided leads. It also hides Early close from pre-sales (administrators keep it, because they may still early-close a qualified lead) and states "Decision recorded: …". Decided rows show View. Files: `includes/class-admin-lead-reads.php`, `includes/class-admin-collection-reads.php`.
4. **Locked controls say why** (F5). "Final closure unlocks after all six cycles are complete." "Close as unreachable unlocks after six cycles and the final closure."
5. **Fields say what blocks Qualify** (F6, F7). "Needed to qualify" sits under each missing saved detail, "Needs ₹4L or above to qualify" under a low budget, and an empty project type now shows "Choose a project type" with "Needed to qualify". The guidance adds "Save the details, then qualify."
6. **Call → log → decide comes first** (F9). In the drawer the order is Log actions, then Lead decision, then cycle history, then Early close.
7. **Sales-only fields not rendered** (F8). The drawer no longer renders deal value, discount, quote shared, quote value or the quote upload for pre-sales. The server still rejects them, and the existing native contract checks that.
8. **Controls match server rules** (F14). The bulk stage list for pre-sales holds New, Contacted and Qualified, the same list as `ajax_bulk_action`.
9. **Rows** (F11, F12, F13, F15). Buttons name their lead, Response text wraps inside its cell, the next step wraps on phone cards, and outreach targets are read once per page.

10. **No shift behind popups** (F20). Applies to every CRM screen that loads `dialog.js`. `scrollbar-gutter` on the root does not hold the space once `body` overflow is hidden in Chrome, so the fix pads `body` instead.

Tests: `tests/presales-leads-contracts.php` (fast source contracts) and `tests/integration/presales-leads-contracts.php` (rendered queue and drawer as a real pre-sales user). `tests/integration/high-priority-contracts.php` and `tests/admin-refactor-contracts.php` were updated, because the old "renders disabled" and "blank on toggle-off" behaviour was deliberately removed.

## Owner decisions (2026-11-07)

- **Q2, audience sync queue:** administrators only. The bulk menu shows Add to and Remove from audience sync queue only to administrators, and `ajax_bulk_action` refuses `bulk_audience_queue` and `bulk_audience_cancel` from anyone else. This also removes them from sales executives and sales managers. The INT-009 HTTP cases now queue as an administrator and check that a sales executive is refused and sees no option.
- **Q3, bulk decisions:** pre-sales has no bulk qualify or unqualify. The bulk stage list for pre-sales holds New and Contacted only, Mark unqualified is hidden, and the server refuses `bulk_unqualified` and a bulk move to any other stage from pre-sales. Other roles are unchanged.
- **Q5, project type:** both selects start with an empty "Choose a project type" choice. An untouched save keeps the field empty, so Qualify stays blocked until a type is chosen.
- **Q1 and Q4:** answered in the session and still open.

## Needs the owner's decision (original questions)

These change access, writes or stored values, so they are not done.

- **Q1.** Should `ajax_presales_decision` also call `IDLM_Admin::require_ajax_access('rep')` after its nonce check, like the other CRM handlers? Pre-sales and administrators would still pass. This only aligns it with the D6 pattern.
- **Q2.** Should pre-sales keep the bulk **Add to / Remove from audience sync queue** actions? The server allows them for any rep. If not, hide them and deny them on the server for `idlm_pre_sales`.
- **Q3.** Bulk **Mark unqualified** and bulk **Change stage → Qualified** skip the drawer's decision flow: no `qualification_decision` activity, and no handoff remark when the floor plan is missing. Should pre-sales lose these two bulk actions, or should bulk Unqualified write the same decision log entry?
- **Q4.** The server accepts early close from pre-sales on a lead that is already Unqualified, which rewrites the reason and the Lost reason. Should it reject that? The UI no longer offers it.
- **Q5.** Saving the drawer with an empty Project type stores the first choice (Apartment). Should the select get an empty "Choose a type" option? That changes what an untouched save writes.

## Proposal (next, not started)

- **"Qualified by me" metric.** If the history count matters day to day, add a card with its own read: `lead_quality = 'Qualified'` and qualified by or intake owner = me. It would switch Engagement to My qualified history and turn off active-only, so the count and the table match.
- **Callbacks due.** Change the Follow-up card for pre-sales to count only callbacks that are due or overdue, matching the queue's first sort key. Rename the "Today" column to "This cycle".
- **Log and next.** After a row Log call or Log WA succeeds, move focus to the next row's Log call. This makes working down the queue keyboard-first.
- **Phone call shortcut.** On cards, a Call button (`tel:`) next to Log call, so the call and its log sit together.
