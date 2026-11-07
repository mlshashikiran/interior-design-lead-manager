# IDLM CRM Design Standard

**Owner / builder:** Shashi
**Status:** Mandatory for every person and agent changing CRM UI
**Version:** 2.0 (2026-11-05)

Read this before changing any file in `templates/`, `assets/css/`, `assets/js/`, or any PHP that prints markup. Rule IDs are enforced where marked **[test]** by `tests/ui-regression-contracts.php`; run `php tests/run.php` before finishing.

## Order of authority

1. This file. It wins every conflict.
2. `MASTER.md`, then the matching `pages/<screen>.md`, for component and screen detail.
3. `docs/DESIGN.md` and root `design.md`, for background and pattern vocabulary only.

Known conflicts, settled here: desktop controls are `34px` and touch targets `44px` at `782px` and below (not `40px`). The sidebar is `248px`, collapsing to `64px` (not `238/72`). `12px` table text and `11px` minimum text replace the `9–10px` sizes in `MASTER.md`.

## Who uses this CRM

Interior-design sales staff work a queue all day: a lead arrives, pre-sales calls and qualifies it, sales takes the handoff, the deal moves through visits and quotations to Won or Lost, then payments are collected. Administrators watch delivery and configure the system. Design for someone returning to the same screens hundreds of times, often on a phone between site visits.

## CRM rules

- **STD-CRM-1 Next action first.** Each screen answers "who do I act on now, and what do I do?" before anything else. Overdue and needs-attention items are visually ahead of totals.
- **STD-CRM-2 Scanning beats decoration.** Comparison surfaces (tables, boards, logs) stay dense, aligned and quiet. Aim for 8 or more lead rows in a 723px desktop viewport as markup is simplified, and never add to row height without removing something.
- **STD-CRM-3 Record identity is constant.** A lead looks the same in the table, the board, search and the drawer: name first, then contact, then status. Opening a record keeps filters, page, selection and board position.
- **STD-CRM-4 Status is text first.** Every status has a written label; colour only supports it. No emoji in UI. **[test]** Semantic colour means one thing everywhere: green succeeded or won, amber needs attention, red failed, overdue or lost, blue neutral information. Never use a colour for its look.
- **STD-CRM-11 Stage and quality tones.** Every Stage and Quality value uses one tone everywhere, always beside its label. Stage: New and Contacted neutral; Pre-Qualified, Sales Connected, EC/VC Planned, QS - Visit Pending and EC Completed blue; Quote Shared and Negotiation green; Won green and bold; RNR and On Hold amber; Lost red. Quality: Pending neutral; Qualified with plan received green; Qualified with plan pending amber; Unqualified red. Tones are matched on stored values in `crm-design.css`, section 15; a custom stage stays neutral until added there.
- **STD-CRM-12 Access is stated, not implied.** Role pick lists never default to a role. Show real outcomes ("Sees only assigned leads"), not "default". Access screens state how many people a change affects. Access saves stay explicit: nothing on update, activation or page load may change roles, capabilities or lead visibility.
- **STD-CRM-13 Notices are toasts.** Feedback appears in one stack in the bottom right corner, newest lowest. Each toast has a dismiss button and closes by itself: success and information after 5s, warnings after 8s, errors after 10s, pausing on hover or focus. `dialog.js` converts `.notice` and `.idlm-alert` automatically. A notice stays inline only if it has a link or control, sits in a dialog or drawer, or carries `data-idlm-notice-inline` because it is standing context about the record or a problem with a form field. **[test]**
- **STD-CRM-14 One list of date periods.** Every date filter offers Today, Yesterday, Last 7 days, Last 14 days, Last 30 days, This week, This month, This quarter, This year and Custom range, in that order, from `IDLM_Date_Ranges` (`options_html()` for the list, `resolve()`, `where()` or `date_bounds()` for the window). Lists may add "All time" first. Windows run from midnight to now in the site timezone, and comparisons use the same stretch of the period before. Never hard-code a period list or a date window. **[test]**
- **STD-CRM-5 Numbers must reconcile.** A metric or column count equals the population shown when clicked. Say the scope ("this month", "active only") wherever it could be misread.
- **STD-CRM-6 Inline edits stay quiet but visible.** Editable dropdowns in tables and status strips keep a thin border and their arrow at rest, so people can tell they are dropdowns; hover and focus strengthen the border. On phones they keep the full control border.
- **STD-CRM-7 One format per data type.** Dates, money and phone numbers use one display format across screens. Quote numbers and IDs keep proportional figures; Inter's tabular figures widen their hyphens.
- **STD-CRM-8 Consequences are named.** Destructive and bulk actions state the object, the count and the result, and use `window.IDLMDialog`. Native `alert`, `confirm` and `prompt` are banned. **[test]**
- **STD-CRM-9 Permissions hide, servers enforce.** Hide what a role cannot do, and keep capability, nonce and object-access checks on the server regardless of what the UI shows.
- **STD-CRM-10 Nothing is lost.** A failed save keeps what the user typed and offers a retry. Autosave status stays visible and quiet.

## Layout

- **STD-LAY-1** Page order: header → summary → view or filter controls → primary surface → drawer or dialog details. One primary button per header.
- **STD-LAY-2** Headers may wrap to two rows and titles never truncate. Search and filters are never squeezed to make room for status pills.
- **STD-LAY-3** No frame inside a frame. A table is its own surface and does not sit inside a padded card.
- **STD-LAY-4** No page-level horizontal scroll. Wide tables and boards scroll inside their own labelled region.
- **STD-LAY-5** Floating or sticky UI (Today's work, save bar) never permanently covers the last record or field.

## Visual

- **STD-TYPE-1** Bundled Inter only, no remote fonts. Minimum rendered text is `11px`. **[test, design layer]** Sentence case everywhere, including table headers, labels, badges and metrics.
- **STD-VIS-1** One neutral family (warm stone, tokens in `assets/css/crm-design.css`). Do not mix cool grey back in.
- **STD-VIS-2** Border first. Static cards, buttons and metrics have no shadow; only drawers, dialogs and popovers are elevated.
- **STD-VIS-3** No accent stripes on the edge of a card, no gradients, no glass effects, no decorative illustrations.
- **STD-VIS-4** Peer views use one tab style: text with a 2px action-colour underline. Badges use `6px` corners; pills are for filter counts only.
- **STD-VIS-5** Motion is limited to colour, opacity and transform at `150–250ms`, and respects `prefers-reduced-motion`. `transition: all` is banned. **[test]**

## Access

**Focus rule.** A mouse or touch click leaves no ring around a control. `dialog.js` adds `idlm-pointer-input` to `body` on pointer use and removes it on Tab, so keyboard users always get the full ring. Text fields keep a thin darker border only while being typed in. Never remove the keyboard ring.

WCAG 2.2 AA. Text contrast `4.5:1`, controls and focus `3:1`. Every control has a visible label or accessible name. Everything works by keyboard, including stage moves (dragging is never the only way). Clickable things are native `a` or `button`. Dialogs and drawers trap focus, close on Escape and return focus. Canvas charts have a data table alternative.

## Writing CSS

The redesign lives in switchable stylesheets: `assets/css/crm-design.css` (tokens, shell, shared components) and `assets/css/crm-design-<area>.css` for `leads`, `pipeline`, `dashboard`, `quotations`, `settings`, `admin` and `payments`. Legacy stylesheets stay untouched so that switching off restores the old design.

- **STD-CSS-1** Every selector starts with `body.idlm-crm-design`. **[test]**
- **STD-CSS-2** Hex colours appear only in the token block at the top; everything else uses `var(--idlm-*)`. **[test]** The repository's colour budget has one slot left, so derive new shades with `color-mix()`.
- **STD-CSS-3** No `!important` in the layer. **[test]** Win with the gated selector's specificity.
- **STD-CSS-4** Never override white-label tokens (`--idlm-action*`, `--idlm-brand-gold`, `--idlm-sidebar`, `--idlm-focus-ring`). Customers set these in Settings. **[test]**
- **STD-CSS-5** Breakpoints are `480`, `782`, `1024`, `1280` (`min-width` uses the value plus 1). **[test]**
- **STD-CSS-6** Style through existing classes. Do not rename classes, IDs or `data-*` hooks: `assets/js/` and the contract tests depend on them. Do not change behaviour, handlers or stored values for a visual reason.
- **STD-CSS-7** New UI uses shared components and adds its rules to the layer or its own gated section. New inline `style=` attributes are not allowed.
- **STD-CSS-9** Markup changes must keep every existing class, ID, `name`, `data-*` and handler. A change that alters structure, order or wording is wrapped in `IDLM_Admin_Assets::design_layer_enabled()`, so the previous design still renders as it was.
- **STD-CSS-8** The quotation editor stays free of the document-content review panel, and printed or PDF quotations are not restyled by this layer.

## Reverting the design

Any one of these restores the previous design. Data is never affected.

| Situation | Action |
|---|---|
| Compare one page | Add `?idlm_design=legacy` to the URL (`?idlm_design=crm` shows the new one) |
| Switch for everyone | wp-admin → **Lead Manager** → Interface design → *Switch to previous design* |
| The site or wp-admin is unusable | Add `define('IDLM_DESIGN_LAYER', false);` to `wp-config.php` |
| Remove it from the code | Delete the `assets/css/crm-design*.css` files and the enqueue in `IDLM_Admin_Assets::maybe_enqueue_design_layer()`, then remove the `design_layer_enabled()` branches |
| Inspect the old source | Git tags `design-baseline-head` (last commit) and `design-baseline-worktree` (tracked files as they stood before this work) |

Removing status emoji from the lead-quality options is the one change outside the layer. It is text only and does not switch back; it is recoverable from the tags.

## Before you finish a UI change

- [ ] `php tests/run.php` passes.
- [ ] Checked at `375`, `768`, `1024` and `1440px`, with the page free of horizontal scroll.
- [ ] Checked as administrator, pre-sales and representative where the screen differs.
- [ ] Empty, loading, error and success states exist.
- [ ] Keyboard path and visible focus work.
- [ ] `?idlm_design=legacy` still renders the old design correctly.
- [ ] A new reusable rule is recorded here or in `MASTER.md`.

## Known gaps

Do not make these worse.

1. Some legacy rules on screens outside the design layer's reach still hard-code `9–10px` text, against STD-TYPE-1.
2. The wp-login form and the wp-admin Identity reviews page do not load the layer.
3. The Payments list has no search or outstanding balance, against STD-CRM-1: it needs a balance read for the page of leads.
4. Log rows cannot show a failure reason: the query has no such column.
