# M6 activity controller — 1.102.87

Author / Builder: **Shashi**. Date: **2026-10-21**.

This bounded M6 slice is implemented and locally verified. **The complete M6 exit, M7 and REL-001 remain IN PROGRESS.** Finding statuses remain **43 IMPLEMENTED / one IN PROGRESS / zero CLOSED**. The private managed ZIP remains a local rehearsal artifact.

## Ownership and preserved contracts

The bounded Graphify query “lead activity/comment transport ownership and callers” found four handlers in `IDLM_Admin_Ajax`, with matching `IDLM_Admin` methods. Its structural class/method edges identify ownership; source review verifies the registrations, clients and helper dependencies. [Caller inventory](remediation-2026-10-21/activity-controller-slice/caller-inventory.json) records each authenticated action, both facade layers and existing JavaScript call sites.

Move `ajax_get_lead_activity()`, `ajax_save_activity_comment()`, `ajax_edit_activity_comment()` and `ajax_delete_activity_comment()` into `IDLM_Admin_Activity_Controller`: **four methods / 232 lines, byte-identical** to the captured pre-slice source. [Extraction hashes](remediation-2026-10-21/activity-controller-slice/extraction-parity.json). The old methods remain stable delegates; the `IDLM_Admin` facade and action registrations are untouched. Production and fast-test bootstraps load the new class after `IDLM_Admin` and before `IDLM_Admin_Ajax`.

The controller follows the existing `IDLM_Admin_Lead_Reads extends IDLM_Admin` pattern. Reflection verifies that all five inherited helpers still resolve to `IDLM_Admin`: representative admission, lead visibility, lead mutation access, timestamp formatting and detail rendering. Those facades retain the established `IDLM_Lead_Access_Policy` and `IDLM_Admin_Presenter` owners. Comment creation still uses `IDLM_DB::log_activity()`; existing prepared reads and direct comment update/delete statements move unchanged.

Preserve shared nonce checks, read-all versus mutation scope, author/administrator edit/delete rules, protected activity types, input unslashing/sanitization, the 15-second duplicate window, exact JSON messages, and timeline response keys. Keep the latest 100 activities, five recent comment/Lost remark/WhatsApp entries, presenter escaping/media filtering and existing action selectors. No policy, schema, lifecycle, queue, JavaScript or stylesheet change is bundled here. Existing source-specific SQL annotations move with the code; no checker exception or exclusion is added.

[Scoped Graphify refresh](remediation-2026-10-21/activity-controller-slice/graph-refresh.json) updates eight PHP files, eleven documents and four verified delegate edges; outside-scope relationships are retained. No full semantic or HTML rebuild is claimed.

## Verification

[Machine evidence](remediation-2026-10-21/activity-controller-slice/verification.json):

- **Eight actual HTTP characterization groups PASS before extraction** on 1.102.86 / WordPress **6.6**. The same eight groups PASS after extraction on 1.102.87 / WordPress **6.6**. Normal affected requests produce no PHP diagnostics.
- **387 fast cases PASS**. The bounded-log source assertion now reads the activity controller; its original 100-row requirement remains intact. The initial failure from the old file location is retained separately.
- **461 accumulated HTTP/stdio cases PASS** on WordPress **7.1.3**, including the eight new groups and warning-free affected requests. Both HTTP versions use PHP **8.4.14**, MySQL **8.4.0**, WP-Cron and no persistent cache.
- **Eight changed PHP files lint clean**. Dedicated synthetic users/leads and newly owned disposable schemas/content isolate tests from the configured site. Provider HTTP/mail is intercepted. Update/delete fault cases reuse the existing fixture-only arbitrary-query fault mechanism; expected database errors are isolated from normal diagnostics.
- The **457-file ZIP** independently matches every packaged source file. [Package identity](remediation-2026-10-21/activity-controller-slice/package-identity.json). Header, constant, stable tag, changelog and current implementation release agree at **1.102.87**.

The new HTTP groups cover nonce/capability/anonymous denials with unchanged aggregate state; read-all and foreign-lead mutation denials; invalid identifiers/empty input/protected activity types; literal text, authorship and duplicate timing; author/admin edit provenance; former-owner denial and valid deletion; update/delete SQL failures; and empty/bounded/ordered/escaped HTML with correct action visibility. Client/facade and Users & Access source bytes match 1.102.86. [Unchanged surfaces](remediation-2026-10-21/activity-controller-slice/unchanged-surface.json).

## Static package gate

Official Plugin Check **2.1.0**, with default checks and no extra exclusions, reports **nine warnings / zero errors**. The complete package gate **fails**. The nine diagnostic records exactly match the 1.102.86 baseline, all in unchanged `class-admin-users-controller.php` lines 30–33. This slice adds **zero diagnostics**. [Current summary](remediation-2026-10-21/activity-controller-slice/plugin-check-summary.json), [baseline summary](remediation-2026-10-21/activity-controller-slice/plugin-check-summary-baseline.json).

The existing Users & Access warnings remain outside this extraction; no unrelated edits or suppressions were made. Historical clean checkpoint results do not establish a clean current package.

## Remaining work

M6 still needs bounded ownership review of remaining integration auth/intake/feed, AJAX, schema/reporting and client responsibilities where coupling is demonstrated. This slice does not establish the complete M6 exit. M7 workload/resource/topology acceptance and the supported runtime/cache/scheduler/lifecycle/recovery matrix remain open. REL-001 needs the complete reviewed clean revision, matching successful CI, a clean static gate and qualification of the exact installed artifact.

Full native and browser UI suites were not repeated: this extraction changes no schema/domain lifecycle or client assets, and the moved markup is byte-identical and exercised by actual HTTP assertions. Those local results do not replace the remaining support matrix or installed-artifact acceptance. No commit, push, tag, publication or deployment occurred.
