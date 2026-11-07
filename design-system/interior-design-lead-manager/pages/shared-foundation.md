# Shared Foundation — Rules

Owner: Shashi. Applies to every CRM screen. `STANDARD.md` wins any conflict. Styles live in `assets/css/crm-design.css`.

## Shell
- Desktop: `248px` sidebar, `64px` rail. Active is a calm fill plus a gold icon, with no edge stripe. Rail labels are clipped, not removed, so links keep their names; `workspace.js` adds the hover title.
- Phones (`782px` and below): a sticky `52px` top bar shows the navigation label of the page and Today's work. The page `h1` is visually hidden and stays in the outline. The page header scrolls away, so it is never sticky on a phone.
- Anything sticky adds `var(--idlm-sticky-top)` to its top offset, so it clears the top bar.
- The phone drawer is modal: the rest of the page is inert, focus moves to its close button, and Escape returns focus to the menu button.

## Today's work
- It is a navigation entry, never a floating control (STD-LAY-5): first in the sidebar, in the top bar on phones. `workspace.js` moves the existing button, which keeps its ID, inline handler and badge.
- Screens without the overlays show a link that opens the panel on Leads (`idlm_open=today`).
- The panel is non-modal, so the table stays usable beside it. Peer views use the underline tab, rows are hairline-separated and carry no priority stripes, and row actions appear on hover or focus on desktop.

## Overlays
- One scrim (`--idlm-scrim`), no blur. One close control: `32px`, `44px` on phones, never red.
- Footer order in source: destructive (quiet, far left), secondary, primary last. Phones reverse it into a column, so primary sits on top.
- Destructive confirmations use a filled danger button and focus Cancel first.
- Phones: dialogs are bottom sheets; plain modals, the filter drawer and long dialogs (import, export, logs, trace) are full screen.
- `IDLMDialog` shows one modal at a time. A confirmation opened from a dialog closes that dialog, so close and reopen the parent deliberately, or use an in-dialog step.

## Feedback
- Toast: confirms background work. `IDLMDialog.notify(message, type, {action: {label, onClick}, duration})`. Three at most, identical messages replace each other, hover or focus pauses them, errors last 10 seconds. Bottom right on desktop, under the top bar on phones.
- Inline notice: anything the user must read or correct. `.idlm-alert` plus a variant. Text stays in `--idlm-text`; colour is carried by the icon, border and fill.
- `.idlm-state`: empty, loading, error and success for a whole region.

## Sign-in
- Reviewed signed out through a cookie-free fetch. Phones show the brand bar and the form first; the marketing copy is desktop only.
- `assets/css/login.css` styles core `wp-login.php`, which never gets the design layer's body class, so it is left alone.

## Sidebar (Shashi)
- Two groups: Work (daily screens) and Admin (Ad conversions, Ad audiences, Settings, Users & access). A role without admin screens sees no headings.
- Counts are needs-action only, never totals, computed with the screen's own visibility rules (`IDLM_Admin_Shortcodes::nav_counts()`): amber waits on someone, red failed. A zero shows nothing.
- The collapsed rail uses the styled `.idlm-nav-tip` tooltip on hover and keyboard focus, never the native `title`.
- No search field or Add lead in the sidebar (owner's decision); both stay on the Leads page.
- The account row shows the signed-in person's name and role.
