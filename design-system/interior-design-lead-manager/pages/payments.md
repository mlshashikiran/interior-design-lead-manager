# Payments — existing CRM page rules

Builder: Shashi  
Date: 2026-11-05

Payments extends the current Quotations workspace. It is not a separate visual system. Reuse `idlm-quotes-wrap`, `idlm-header`, `idlm-card`, `idlm-quote-summary`, `idlm-table`, `idlm-quote-layout`, `idlm-input` and `idlm-btn`, including their existing density, colours, typography, radius and mobile navigation. Do not introduce a larger Payments title, a custom hero, a different metric style, a decorative form panel or a separate project-card picker. The previous standalone payment treatment has been replaced at Shashi's request.

Design read: an operational CRM extension for the existing sales team, preserving the current product language. DESIGN_VARIANCE 3 / MOTION_INTENSITY 1 / VISUAL_DENSITY 7. UI UX Pro Max guides forms, labels, disclosure and responsive behavior. Design Taste Frontend's preservation/audit principles apply; marketing layouts and alternative design systems do not apply to this product screen.

`assets/css/payments.css` contains only payment-specific structure and disclosure/receipt details; shared appearance comes from the existing admin, workspace and quotation stylesheets. Desktop header height, title font size, card padding and table-cell padding were compared in the actual browser and match Quotations exactly.

- The lead picker uses the same table and Open action as the quotation list. Existing and new Won leads share the list and scope rules.
- Summary values use the existing four-cell quotation summary. Payment schedule rows use the shared semantic table, with scrolling contained within the standard table wrapper on narrow screens.
- The receipt panel uses shared controls and sizing. Screenshot upload, notes, versions and correction actions use native disclosure. DOM/focus order matches the mobile visual order.
- Phone text-entry fields use 16 px text to avoid focus zoom; the page uses the current workspace's responsive header/navigation and control system.
- The legacy-recovery form uses the same controls: milestone name/percentage/advance, historic source reference, reason and explicit confirmation. Three rows appear first; additional rows expand by keyboard. Recovery does not imply any actual receipt.
- Backend authority is never inferred from a visible button. Recovery uses the separate payment-correction grant plus live lead scope. Saved historical terms cannot be overridden through legacy recovery.

Local browser checks cover 375, 768, 1024 and 1440 px. No page-level horizontal overflow; wide payment tables scroll within their own region. Recovery source/actor/time/reason and a real synthetic 2024 receipt remain visible through the appropriate disclosures.

## CRM redesign pass (Shashi, 2026-11-05)
- List: Lead, Customer, Project and an Open action. The customer name is a stretched link, so the whole row opens the project; the Open button keeps an accessible name with the customer and lead number. Phones show each row as name, project and ID with Open on the right. Empty state uses `.idlm-state.is-empty`.
- Search and outstanding figures are not on the list because the renderer holds no balance data without per-lead queries. Follow-up: add a balance read for the page of leads, then a search field.
- Summary cards drop the quotation stripe and uppercase 10px labels; values scale down on phones instead of truncating.
- Revision choices in setup read as one bordered list. Revoked receipts use the neutral badge. The receipt panel is sticky on desktop only.
- All markup changes are gated on `IDLM_Admin_Assets::design_layer_enabled()`; legacy output is unchanged.

## Design handoff

Styles sit at the end of `crm-design-payments.css`. The lead drawer shows one `Design handoff` panel: status badge, one line, one button. The send dialog puts notes first, then the advance. The Design team tab is one table with text tabs (five for managers, one for reps); a handoff opens to details with actions beside them, and below them at 782px and below. Phones hide the Project and Sent columns.

The handoff's Stage panel shows the six stages, then the checklist for leaving the current one, with uploads beside each document. One primary move button; hold, move back and reopen stay inside native disclosures.

The Design team tab opens on the Projects table for managers and on My board for designers. Queue counts are quiet text links above the table. Designer and target date are edited in the row: they look like values until hover or focus, and keep a visible border on phones. Phones hide Stage, Next step and Pending; the table scrolls inside its own labelled region. A row or card opens the project quick panel. It copies the lead popup: avatar, name and contact, a status strip (status, stage, designer, target date), then three titled panels (two at 1024px and below, one on phones, where it fills the screen). Open full project is its primary action. On the project page, secondary actions (hold, move back, reopen, add a file, record a payment, return to sales) are secondary buttons that open dialogs, and history lists (design revisions, payments received, earlier versions and submissions) open in place behind a quiet chevron row.

The Overview tab puts administrator queues first, then current cards that open their matching list, what needs attention, stages, workload, period figures and recent activity.

Design stages (administrators) is one card per stage, in order. Each card has the name, up and down arrows, a short note (start stage and unfinished projects), what is needed before leaving the stage (checkboxes, a payment select, document checkboxes) and Remove this stage. Removed stages stay listed on a soft background with their requirements hidden, and an unticked Remove brings them back. Added documents sit below with a name, file type and Remove. Save stays visible at the bottom.

## Simple payment page (2026-11-06)
Order: header (title, lead line with advance badge, All leads, one primary **Record payment**) → summary (Total, Received, Outstanding) → Milestones table → Payments table. Every form is a dialog using the shared `idlm-dialog` component and `design-detail.js`. Secondary actions are `idlm-btn-quiet`. Optional figures (credit, not matched, not in any milestone) appear only when not zero. Rules live in the "Simple payment page" section of `assets/css/crm-design-payments.css`.
