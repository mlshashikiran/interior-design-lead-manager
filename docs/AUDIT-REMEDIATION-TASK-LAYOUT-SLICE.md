# UI-002 — Mobile task form layout

Author / Builder: **Shashi**. Date: **2026-10-08**. Unreleased local style iteration on candidate **1.102.63**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Core **1.3.9**, routing **1.0.1**, quotation **2.5.4** and lifecycle receipt **1.1.0** remain unchanged. No version bump or release packaging for this bounded style correction.

## Finding and correction

The immutable [UI-002 audit](audit-2026-10-06/UI-REVIEW.md#ui-002--p2-medium-mobile-task-form-remains-a-compressed-two-column-layout) records an inline `1fr 1.5fr` task wrapper and inline two-column priority/type grid. The bounded graph lookup **UI-002 / tasks / ajax_get_lead_details** located the finding and shared lead-detail response owner. Current source confirmed both inline layouts still bypassed the existing responsive rules.

A fresh actual browser reproduces the defect at **390 px**: panel columns **134.4 / 201.6 px**, with a creation form only **102.4 px** wide. [Baseline screenshot](remediation-2026-10-08/task-layout-slice/baseline390.jpg).

Remove the two inline grid declarations from `includes/class-admin-lead-reads.php` and give the nested task fields a scoped class. Shared `assets/css/admin-style.css` owns the desktop `1 : 1.5` panel columns and two-column priority/type controls. At the existing **820 px** drawer-field breakpoint, both grids stack; task inputs/selects/Add Task use **44 px** minimum height. Zero-minimum tracks, bounded control sizing and wrapping labels prevent content from forcing horizontal overflow. Remove nested grid padding at these widths so all controls align with the title/date fields. Existing panel tokens, IDs, handlers, request payloads and drawer/list scroll containers are retained.

The focused HTTP runner adds **`--task-ui-only --hold`**, which prepares an owned synthetic fixture without running unrelated quotation cases. The fixture router can serve an optional captured-markup long-label stress page. These are test-only changes.

## Executed verification

| Actual browser width | Creation control width | Panel / field layout | Result |
| --- | --- | --- | --- |
| 320 px | 248 px | one column / one column | full-width 44 px controls; date/select entry and administrator task save |
| 390 px | 318 px | one column / one column | full-width 44 px controls; administrator and representative entry; task-list scroll |
| 768 px | 612 px | one column / one column | full-width 44 px controls; no horizontal drawer/form overflow |
| 1280 px | about 425 px | two columns / two columns | original desktop proportions; administrator task save |

**14 recorded browser observations** cover the baseline, final viewport/role geometry, three actual task creations, scrolling and captured long-label stress. At mobile/tablet widths, form/body scroll widths equal their client widths and every creation control spans the full form width. Actual drawer scrolling reaches all three saved tasks; the long task title wraps inside its list card. Administrator and current-owner representative use the supported frontend leads workspace and shared renderer/assets. The duplicate wp-admin CRM asset enqueue is intentionally retired in current source; no new admin CRM page is introduced.

Three tasks are independently checked in the disposable SQL database: exact titles, dates **October 9 10:30 / October 10 15:45 / October 11 09:15**, types **Site Visit / Design Mockup / Quote Revision**, priorities **High / Medium / Low**, pending status, current owner and creators. [Browser observations](remediation-2026-10-08/task-layout-slice/browser-checks.json), [database assertions](remediation-2026-10-08/task-layout-slice/browser-persistence.json), [finished form](remediation-2026-10-08/task-layout-slice/final-form390.jpg), [scrolled task list](remediation-2026-10-08/task-layout-slice/task-list390.jpg).

Long German-style labels in a **static copy of actual captured drawer markup** wrap without horizontal overflow at 320/390/768 px. This stresses layout rather than claiming a production translation system; the stress page disables only the entrance animation for stable measurements. [Captured markup](remediation-2026-10-08/task-layout-slice/long-label-markup.html), [320 px screenshot](remediation-2026-10-08/task-layout-slice/long-label320.jpg). Desktop, mobile and long-label screenshots were visually inspected.

**312 fast / 0 failed**; all three changed PHP files pass `php -l`, and `git diff --check` passes. Existing fast stderr retains the unrelated synthetic missing-phone warning. Browser warning/error observations, affected-request PHP diagnostics and fixture stderr are empty. No additional implementation-mirroring CSS test or full native/HTTP suite run is needed for this isolated presentation change. [Commands, hashes, results and cleanup](remediation-2026-10-08/task-layout-slice/verification.json).

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, no persistent cache. Only a newly owned `idlm_test_*` database and synthetic accounts/content were used; provider HTTP/mail were intercepted. Configured-site data was untouched. The HTTP fixture/database/content were cleaned up, socket-only MySQL stopped and temporary data removed, browser tab closed and viewport reset. No commit, ZIP or deployment.

## Qualification and next work

**UI-002 is IMPLEMENTED, not CLOSED**. Backlog: **15 OPEN / 29 IMPLEMENTED**, none CLOSED. Exact artifact/CI and broader runtime, browser engine, locale/native date-picker, touch-device and full T04/T19/M0 qualification remain open. This slice verifies the rendered date control and saved date value; it does not qualify every operating-system date-picker popup. Graph refresh is scoped to changed source structure and explicit documentation headings, retaining unrelated entries without full semantic/report regeneration.

Next: **UI-003 — associated task-control labels and accessibility**. Label associations and complete keyboard/screen-reader/focus/error qualification remain for that slice.
