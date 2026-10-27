# UI-003 — Associated task labels and keyboard recovery

Author / Builder: **Shashi**. Date: **2026-10-09**. Local candidate **1.102.64**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Core **1.3.9**, routing **1.0.1**, quotation **2.5.4** and lifecycle receipt **1.1.0** are unchanged.

## Finding and correction

The immutable [UI-003 audit](audit-2026-10-06/UI-REVIEW.md#ui-003--p2-medium-task-controls-lack-associated-accessible-labels) records missing task-control names. The bounded graph query **UI-003 / addLeadTask** located the audit and shared JavaScript handler; source inspection confirmed missing label associations in the shared lead-detail renderer.

Associate the four visible labels with the existing title, date/time, priority and type IDs. Use form submission and a submit button so Enter follows the existing task AJAX path. Whitespace-only titles expose a linked inline alert, mark the title invalid and focus it; editing clears that state while preserving the other values. Successful submission resets the form and focuses the title. A disabled-button guard prevents another valid submission while the request is pending.

Actual browser role/name checks also exposed the open drawer retaining `aria-hidden="true"`. Opening now exposes and focuses the existing focusable drawer; closing hides it. Ordinary dismissal restores focus to the initiating control. This uses the existing shared renderer/assets for administrator and representative workspace access, with no new request handler or schema change. Candidate metadata and the asset version advance to **1.102.64**.

## Executed verification

**312 fast / 0 failed** and **1 focused native HTTP / 0 failed** on the final candidate. The new `T04-T19-H-UI003-task-labels-submit-and-error-associations` requests actual owner-authorized drawer HTML, verifies each unique ID/label, submit semantics and hidden associated error, and confirms unchanged plugin domain rows. It runs in both the standard HTTP runner and `--task-ui-only`; the full native/HTTP suite was not rerun for this isolated UI change.

An initial broader aggregate snapshot failed. The final regression deliberately compares all `wp_idlm_%` domain-table rows, the relevant rendering contract; it does not assert that every WordPress job/option is unchanged. The earlier failure is retained in `http.log`; its non-domain cause was not conclusively identified. Final passing runs are `http-final.log` and `http-candidate.log`. Fast stderr retains the pre-existing synthetic missing-phone warning.

**13 actual browser observations** cover desktop **1280 × 900** administrator and mobile **390 × 844** current-owner representative flows:

- All four controls expose their associated visible names; clicking the title label focuses its input.
- Desktop Tab order follows title → native segmented date/time → priority → type → Add Task.
- Native required validation refuses an empty title. Whitespace validation focuses the title, exposes its error and retains date/priority/type; correction clears the error.
- Enter on the desktop submit button and from the mobile title input creates exactly one task each. Success resets the controls and focuses the title.
- Escape closes the ordinary drawer and returns focus to the lead button; reopening presents clean state and named controls.

Independent SQL verifies exactly two tasks: desktop **High / Site Visit / 2026-10-12 10:30:00 / creator 1** and mobile **Low / Call / 2026-10-13 09:15:00 / creator 2**, both pending on synthetic lead 5 assigned to representative 2. Invalid submissions created no tasks. [Browser observations](remediation-2026-10-09/task-accessibility-slice/browser-checks.json), [SQL assertions](remediation-2026-10-09/task-accessibility-slice/browser-persistence.json), [desktop names](remediation-2026-10-09/task-accessibility-slice/desktop-accessible-names.txt), [mobile names](remediation-2026-10-09/task-accessibility-slice/mobile-accessible-names.txt).

Desktop error, mobile error and saved-task screenshots were visually inspected. Browser warning/error observations, affected-request PHP diagnostics and final HTTP stderr are empty. Changed PHP syntax, JavaScript syntax and whitespace checks pass. [Verification, commands and hashes](remediation-2026-10-09/task-accessibility-slice/verification.json), [mobile saved tasks](remediation-2026-10-09/task-accessibility-slice/mobile-saved.jpg).

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, no persistent cache. Only newly owned `idlm_test_*` databases and synthetic accounts/content were used; provider HTTP/mail were intercepted. Configured-site data was untouched. Owned HTTP fixtures/databases/content were removed, socket-only MySQL stopped and temporary data removed; the browser tab was closed and viewport reset. No commit, ZIP or deployment.

## Qualification and next work

**UI-003 is IMPLEMENTED, not CLOSED**. Backlog: **14 OPEN / 30 IMPLEMENTED**, none CLOSED. Browser accessible-name and keyboard evidence does not qualify spoken screen-reader output, a complete drawer focus trap, nested unsaved-change dialogs, every OS date picker/browser engine, network failure recovery, or the whole T04/T19/M0 groups. Exact artifact/CI and broader runtime qualification remain open. Graph refresh covers changed source structure and explicit documentation headings, retaining unrelated graph content without full semantic/report regeneration.

Next: **INT-004 — queued Interakt dispatch eligibility and revoked keys**.
