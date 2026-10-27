# Browser and export evidence

Author: Shashi. Reviewed 2026-10-06, candidate 1.102.42. This is a bounded actual-browser check, separate from source review and PHP doubles.

## Isolation and executed observations

The user-supplied configured site URL is localhost:10003. Its database was not used for testing. The browser exercised a separate WordPress 7.1.2 fixture at 127.0.0.1:8876, a new `idlm_test_audit_ui_20261006` database on an isolated networking-disabled MySQL 8.4 socket, a minimal synthetic theme and the exact extracted 431-file candidate. Mail and outbound WordPress HTTP were blocked by fixture-only MU filters. Twenty-four synthetic leads have reserved example.test addresses; twelve belong to audit_rep and twelve to audit_admin. No provider/customer messages or audiences were sent.

Initial fixture setup used symlinked WordPress code directories; a request resolved the original WordPress loader and failed its configured DB connection. Those links were immediately replaced with code-only directory copies and isolated configuration. No connection to or modification of the configured database occurred. Initial setup/runtime failures are recorded as fixture failures, not plugin defects.

|Actual UI scenario|Result|Evidence and limit|
|---|---|---|
|Administrator sign-in, workspace navigation and 24-row list|PASS|Authenticated synthetic account; actual list and drawer. No cross-environment equivalence claim.|
|Details/activity/WhatsApp/task tab switching|PASS|Activity contains synthetic creation/CAPI-dispatch diagnostic, WhatsApp has explicit empty state, tasks zero. No external delivery took place.|
|Blank task submission|PASS|Visible “Please enter a task title.” and focus moves to title field. No task inserted.|
|Desktop 1440×1000 task drawer|PASS|[Screenshot](evidence/ui-desktop.jpg); create form and list legible.|
|Mobile 390×844 task drawer|FAIL|[Screenshot](evidence/ui-mobile.jpg); narrow persistent side-by-side columns, tiny selects, clipped tabs. UI-002.|
|New quote, custom item, quantity 2, rate 1000|FAIL|Browser blocks Save with step mismatch; UI-001. [Screenshot](evidence/ui-quotation-validation.jpg).|
|Same quote with browser-valid quantity 2.01|PASS|Save confirms Q-2026-000001-R01; displayed subtotal 2010, GST 361.80, rounded grand total 2372. [Screenshot](evidence/ui-quotation-saved.jpg). Only happy path administrator creation.|
|CSV/PDF/XLSX authenticated download|PASS|Browser download events completed with expected Q/revision/customer filename. Files retained under evidence. Transport/cache headers and full denial matrix NOT RUN.|
|PDF visual and numeric agreement|PASS|Poppler renders both A4 pages; both inspected. One synthetic custom item 2.01×1000=2010, tax 361.80, grand 2372 match UI/CSV/XLSX. No clipping in this fixture. Large, optional, image-heavy, multilingual, historical and changed-settings output NOT RUN.|
|Wrong synthetic email sign-in|PASS|Visible invalid-login response; the entered address did not exist. This is not a password-recovery test.|
|Representative sign-in/navigation|PASS|Dashboard/Leads/Pipeline/Quotations, no Settings/Users/CAPI/Audience navigation. Server role matrix remains separate.|
|Fresh representative own-lead-only visibility|FAIL|Representative owns 12 but dashboard and list expose 24; search Synthetic24 returns administrator-owned PII. [Screenshot](evidence/ui-representative-search.jpg), CORE-001. Default all-read setting is intentional source behavior but unsafe as an unqualified fresh-install default.|
|Search asynchronous response|PASS|Search Synthetic24 eventually reduces count 24→1; input and list agree. Sort/multi-page/stale-response combinations NOT RUN.|
|Subscriber sign-in|PASS|Actual “Access Restricted” response after valid synthetic sign-in.|
|Programmatic task labels|FAIL|AX title/due/priority/type fields have ID-based or no accessible name; visible labels have no `for`. UI-003.|
|Console errors/warnings|PASS for observed actions|Captured browser developer log empty. No performance or all-browser assertion.|
|PHP warnings during quotation downloads|FAIL|CSV escape deprecation and fresh PDF missing paper_size warning/null deprecations; UI-004. [Log](evidence/php-ui-errors.log).|

## Additional source-verified findings

### UI-001 — P1 High: ordinary quotation quantities cannot pass browser validation

**Module/source:** `includes/class-quotation-renderer.php:305`; generated quantity input min 0.01, step 0.10, default 1.00. **Trigger/users:** quotation editor enters ordinary 2 (also default 1 lies off the step lattice). **Expected/actual:** positive two-decimal quantity accepted and saved; actual native browser blocks submission and reports nearest 1.91/2.01. **Impact:** standard quote workflow blocked or users change commercial quantities to make form submit. **Evidence:** actual browser value 2.00 with validationMessage; no request/save occurred until 2.01. **Existing tests:** calculator/markup tests pass without native form validation. **Remediation/verification:** choose domain-aligned min/step and browser/server precision; actual UI tests 1, 2, .01, 2.05, dimension-derived area and repeated edit/save. **Confidence:** high, actual installed artifact reproduction.

### UI-002 — P2 Medium: mobile task form remains a compressed two-column layout

**Source:** `includes/class-admin-lead-reads.php:696` inline 1fr 1.5fr grid; nested two-column priority/type at 712. **Trigger/users:** 390 px mobile drawer. **Expected/actual:** usable full-width controls; actual task form occupies roughly 134 px with cramped input and tiny selects. **Impact:** difficult task entry and hidden tab names. **Evidence:** actual screenshot; desktop comparison. **Tests:** no existing native viewport test. **Remediation/verification:** responsive stacking rules that override/remove inline layout; verify 320/390/768 px, scroll, date controls and long translated labels. **Confidence:** high for shown viewport, other devices NOT RUN.

### UI-003 — P2 Medium: task controls lack associated accessible labels

**Source:** `includes/class-admin-lead-reads.php:705–724` visible label elements do not wrap controls or carry for; inputs lack aria-label. **Trigger/users:** screen reader task entry. **Expected/actual:** title/date/priority/type names; AX names are missing or IDs. **Impact:** assistive users cannot reliably identify task controls. **Evidence:** AX snapshot and current markup. **Tests:** blank validation focus works, full screen-reader/keyboard audit NOT RUN. **Remediation/verification:** associated labels and sensible focus order; test accessible names, keyboard submit/error/recovery and mobile. **Confidence:** high source/AX agreement.

### UI-004 — P2 Medium: fresh-install quotation downloads emit PHP 8.4 diagnostics

**Source:** `includes/class-quotation-exporter.php:133` fputcsv omits escape; `:330` paper_size condition defaults to A4 but then reads absent array key. **Trigger/users:** fresh quote settings absent, CSV/PDF download on PHP 8.4. **Expected/actual:** clean output with valid A4 default; download succeeds but emits warning/deprecations, then passes null into Dompdf. **Impact:** noisy logs and potential corrupted download/path disclosure on hosts displaying diagnostics. Displayed leakage was not reproduced; fixture uses display_errors false. **Evidence:** retained PHP log and actual successful files. **Tests:** local syntax/native suites miss first-use settings and deprecation cleanliness. **Remediation/verification:** resolve default into a value before return, specify escape explicitly, fresh-settings PDF/CSV with warnings promoted to test failures; support matrix. **Confidence:** high observed diagnostics, display-on consequence conditional.

## Unknowns

Full administrator/frontend filter parity, pre-sales/manager/mixed/disabled browser navigation, expired/cross-user nonce HTTP behavior, request interruption, duplicate clicks, upload/download revocation, keyboard focus trap/Escape/reopen, contrast measurements, print-browser parity, PDF Letter/image/resource bounds and multi-page quotation layouts remain NOT RUN. Passing one download does not establish authorization or historical immutability; see QUO-001–010. Browser automation focus/locator issues and an initial long observation delay are tooling limitations, not classified as plugin defects. Temporary viewport is reset and audit tabs closed at cleanup.

Cleanup removed only `idlm_test_audit_ui_20261006` after verifying the temporary MySQL datadir; only the four built-in MySQL schemas remained. The temporary PHP server was stopped, the dedicated MySQL server shut down, and the browser viewport restored and audit tab closed. Synthetic download files, screenshots, source/ZIP identity and test evidence are retained in this audit directory. [Cleanup evidence](evidence/cleanup.json). The configured site database was not used.
