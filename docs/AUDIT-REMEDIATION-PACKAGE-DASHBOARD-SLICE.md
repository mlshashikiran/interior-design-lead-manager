# REL-007 dashboard SQL checkpoint

Builder: Shashi. Date: 2026-10-13. Local candidate: **1.102.79**. **IN PROGRESS, not IMPLEMENTED or CLOSED.** Schemas unchanged. Continues the [source checkpoint](AUDIT-REMEDIATION-PACKAGE-SOURCE-SLICE.md) and immutable [release audit](audit-2026-10-06/RELEASE.md).

The assignment-origin query used `LIKE 'automatic_%'`, where `_` matches any character. Stored origins such as `automaticxmanual` and `automatically` therefore counted as automatic assignments. A native regression reproduced **four automatic events instead of the independently expected two**. The query now uses `$wpdb->esc_like('automatic_') . '%'` for both prepared pattern parameters. Existing field names, date/current-owner filters and legacy fallback are retained. Dashboard cache contract **4** prevents contract-3 aggregates from carrying the old classification into the new code.

The new native case runs in the standard integration runner. It inserts eight synthetic assignment events, compares totals and both origin buckets, exercises a source containing apostrophe/backslash/percent/underscore, excludes an outside date range, checks current-owner filtering after reassignment, compares unchanged stored event rows, and tests old-cache isolation and warm results. Fixtures use an owned disposable database with provider/mail interception.

## Source review and package diagnostics

The [source review](remediation-2026-10-13/package-dashboard-slice/source-review.json) binds **three assignment SQL statements / nine diagnostic locations** to source lines and SHA256. It traces current CRM admission, manager-only rep filters, prepared actor/rep/studio/source/campaign fragments, date values, fixed joins/orders and legacy `NOT EXISTS` conditions. The legacy fallback is source-reviewed; this slice does not claim new complete legacy-history qualification. The other placeholder-count warnings inspected already had specific parameter-array justifications in the previous ledger; no new defect or closure is inferred from those alerts.

The **450-file** rehearsal ZIP SHA256 is `2b8ca1b6fac59837b0c3e4ecb4b0621436f094c3b6199c19b01880da37705179`. Its inventory matches 1.102.78 and every archived byte matches current source. Only dashboard calculation/read-model bytes and version/changelog metadata changed. The builder reports dirty Git, absent matching CI and `local_rehearsal_only`.

Official Plugin Check **2.1.0**, all default static checks with no exclusions, retains **136 errors / 2,018 warnings**. The two LIKE-pattern errors disappeared. The wrapper still returns **1**. [Raw diagnostic evidence](remediation-2026-10-13/package-dashboard-slice/final/plugin-check.json) remains complete. The [artifact-bound ledger](remediation-2026-10-13/package-dashboard-slice/diagnostic-triage.json) records **680 justifications / 1,473 review-required / one release-channel limitation**. Nine new justifications cover the traced statements; earlier dispositions carry only when path, line, column, code and source line match. No additional checker suppression or cache removal was introduced.

## Executed verification and limits

- **365 fast contracts PASS**, with the previously recorded six missing `num_rooms` and one missing `phone` fixture warnings retained.
- **3 focused native contracts PASS**: fresh schema, existing dashboard role/scoped SQL behavior and the new REL-007 case. The first native run reproduced the defect. A temporary strict whole-payload cache assertion compared deserialized object identity; it was corrected to compare assignment values, then the affected cases passed.
- **14 release contracts PASS**, archive/source identity PASS and changed PHP syntax checks PASS.
- Local environment: WordPress **6.6**, PHP **8.4.14**, MySQL **8.4.0**, WP-CLI **2.12.0**. Static checking the extracted ZIP does not qualify installed artifact behavior.

[Machine evidence](remediation-2026-10-13/package-dashboard-slice/verification.json) records artifact/source hashes, named checks, diagnostics and limits. Full native, actual HTTP/browser, provider, PHP 7.4/8.1/latest WordPress, remote exact CI and release recovery qualification were not run in this slice. No configured site, commit, push, tag, provider send or deployment was touched. Disposable databases/content are cleaned; the owned MySQL server is stopped after checks. Rehearsal artifacts remain local.

Graphify query **“Dashboard assignment reporting event origin”** located the dashboard and lifecycle documentation; a focused `.calculate_stats()` query exposed structural dashboard methods, not the origin semantics. Implementation decisions were verified against current source. The local graph receives a scoped structural/document-heading refresh; no full semantic regeneration is claimed.

## Remaining work

Continue REL-007 SQL constructors and request-validation/unslashing review using the [current queue](remediation-2026-10-13/package-dashboard-slice/diagnostic-review-queue.csv). REL-001 remains dependent on REL-007, M6/M7 and clean exact CI/artifact qualification. **42 IMPLEMENTED / 2 IN PROGRESS / zero CLOSED**.
