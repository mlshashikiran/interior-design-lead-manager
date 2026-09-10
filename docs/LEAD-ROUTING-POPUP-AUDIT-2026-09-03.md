# Lead Routing & Assignment Rules — UI Audit

Date: 2026-09-03  
Author / Builder: Shashi  
Audit scope: routing popup, surrounding Leads layout, and CRM design-system reference mapping. Runtime behavior was not changed by this audit.

## Audit score

Visual system: 8/10. Layout and responsive styling are coherent and tokenized. Interaction/accessibility: 5/10. The popup bypasses the shared accessible dialog controller and has several semantics/state-management gaps.

## Severity findings

| Severity | Finding | Evidence |
| --- | --- | --- |
| P1 | Popup does not use the shared dialog contract: no `role="dialog"`, `aria-modal`, labelled title/description, `aria-hidden`, or `data-idlm-dialog`; direct class toggles bypass focus trap, inert background, Escape handling, and focus restoration. | `templates/admin/leads.php:509-518`; `assets/js/admin-app.js:3004-3017`; shared controller `assets/js/dialog.js:94-129,353-379` |
| P1 | Settings trigger is icon-only and has no `aria-label`; title alone is unreliable for accessible naming. | `templates/admin/leads.php:87` |
| P1 | Assignment strategy has no explicit label; its relationship is inferred from the section heading. | `templates/admin/leads.php:524-539` |
| P1 | Source panel visibility is controlled by inline CSS and jQuery show/hide without `hidden`, `aria-hidden`, or disabled-state synchronization. | `templates/admin/leads.php:579`; `assets/js/admin-app.js:3064-3069` |
| P1 | Closing after edits silently discards changes; save errors are generic toast-only feedback with no field association. | `assets/js/admin-app.js:3072-3100` |
| P2 | Desktop popup is 1320px wide while the primary grid is forced to one column, producing avoidable horizontal whitespace and a long vertical scan. | `assets/css/admin-style.css:3176-3264` |
| P2 | Status cards use color bars for readiness states but lack live/semantic status announcements. | `assets/css/admin-style.css:3404-3462`; `templates/admin/leads.php:609-629` |
| P2 | “Run Fallback Now” is a consequential operational action embedded beside settings without a dedicated confirmation/progress state. | `templates/admin/leads.php:749-753` |

## Positive findings

- Dedicated scrollable modal body with fixed header/footer and overscroll containment.
- Correctly escaped dynamic output and per-representative labels on studio selects.
- Responsive rules stack sections, representative rows, control cards, and footer buttons at 760px; status cards become single-column at 480px.
- Existing token system is consistent across brand, surfaces, text, spacing, radii, focus, motion, and layer elevation.

## CRM design-system reference map

| Layer | Canonical reference |
| --- | --- |
| Brand/action | `--idlm-brand-gold`, `--idlm-action`, `--idlm-action-hover`, `--idlm-action-soft` |
| Semantic states | `--idlm-success`, `--idlm-warning`, `--idlm-danger`, corresponding `*-soft` tokens |
| Surfaces/text | canvas `#eef2f6`, surface `#fff`, surface-soft `#f8fafc`, text `#17212b`, muted `#56657a` |
| Borders/elevation | `--idlm-border`, `--idlm-border-strong`, `--idlm-shadow`, `--idlm-shadow-lg` |
| Spacing/shape | 4–48px spacing scale; 6/8/12px radii; 44px target size; 40px control height |
| Type | page 1.375rem; section 1rem; card .875rem; body .8125rem; compact .75rem; labels .625rem; meta .5625rem |
| Layers | modal backdrop 100000; modal 100100; dialog 100150; toast 100200 |
| Leads page composition | role-aware header/actions → summary metrics → filters → lead queue/table → drawers and dialogs |
| Routing popup composition | header → strategy → representative coverage → conditional source maps → status strip → safeguards/fallback controls → footer |

## Prioritized fix plan

1. Adopt `IDLMDialog.open/close`, add complete dialog ARIA/data attributes, and pass the settings trigger for focus restoration.
2. Add explicit labels and accessible trigger naming; synchronize conditional source panel semantics and disabled controls.
3. Add dirty-state confirmation, inline field errors, and announced save/progress states.
4. Recompose desktop content into a denser two-column layout while preserving the existing mobile rules.
5. Separate fallback execution from persistence with confirmation and progress/error feedback.

## Next task delegation brief

Scope: implement the P0/P1 routing-popup accessibility and state fixes, then apply the desktop layout refinement without changing AJAX contracts, permissions, saved options, or database schema.

Expected files: `templates/admin/leads.php`, `assets/js/admin-app.js`, `assets/css/admin-style.css`, plus focused UI tests/contracts if the repository test conventions support them.

Acceptance criteria: popup uses the shared dialog controller; focus is trapped/restored; Escape closes it; background is inert and scroll-locked; title/description/trigger/strategy controls have accessible names; hidden source controls are semantically hidden/disabled; unsaved edits are protected; desktop and 760px/480px layouts remain usable; existing save and routing actions still work.

Verification: run relevant PHP/JS/style checks and focused tests; manually exercise open, close, Escape, keyboard tab order, source-mode toggle, save success/error, dirty cancel, and responsive widths.
