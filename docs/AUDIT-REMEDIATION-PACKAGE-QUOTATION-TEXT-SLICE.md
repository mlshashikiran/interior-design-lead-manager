# REL-007 quotation request-text checkpoint

Builder: Shashi. Date: 2026-10-13. Local candidate **1.102.80**. **IN PROGRESS, not IMPLEMENTED or CLOSED.** Schemas unchanged. Continues the [dashboard SQL checkpoint](AUDIT-REMEDIATION-PACKAGE-DASHBOARD-SLICE.md) and immutable [release audit](audit-2026-10-06/RELEASE.md).

## Confirmed defect and boundary

Quotation save/autosave already unslashed posted item rows, but the calculator unslashed their descriptions again. Literal text such as `C:\plans\oak` became `C:plansoak`. The section parser also removed backslashes from already-unslashed domain values, while its HTTP callers passed raw WordPress-slashed section rows. Two fast regressions and actual HTTP save/autosave/print checks reproduced the loss.

The controller now removes request slashes exactly once for both item and section rows. The calculator accepts **unslashed domain values** and sanitizes retained text without removing literal backslashes. All in-repository parser callers were inventoried; the existing protected quotation facade still delegates. Section shape/count rejection remains in the parser. No schema migration, historical text rewrite or total recalculation occurs; previously lost text cannot be reconstructed by this repair.

The bounded Graphify query **“quotation controller calculator parse_items parse_sections”** located controller/calculator methods. Source inspection confirmed the inherited facade delegates and the differing unslashing boundaries; graph structure alone did not establish the text semantics.

## Executed evidence

- **367 fast contracts PASS**, including two new domain-text cases. Before the repair: 365 PASS / two FAIL. Existing six missing `num_rooms` and one missing `phone` fixture warnings remain separate.
- **Four focused actual HTTP cases PASS**: literal item/header/section text and totals on save; working-copy/editor recovery and new-revision/history preservation; owner/admin print and parsed CSV/XLSX; absence of affected-request PHP diagnostics. Before the repair, three behavioral cases failed. The initial XLSX assertion inspected the summary sheet; correcting it to the line-item sheet made the parsed export assertion meaningful.
- **433 accumulated actual HTTP/stdio cases PASS**, including existing authorization, identity, migration, invalid shape/count/numeric, transaction and export regressions plus the three new behavioral cases.
- **14 release contracts PASS**, changed PHP lint and archive/source identity PASS. Environment: WordPress **6.6**, PHP **8.4.14**, MySQL **8.4.0**, WP-CLI **2.12.0**, WP-Cron, no persistent cache. Owned synthetic databases/content only, with outbound providers/mail intercepted.

[Machine evidence](remediation-2026-10-13/package-quotation-text-slice/verification.json) binds commands, results and source hashes. This slice does not add actual-browser, focused PDF-text, full native, PHP 7.4/8.1/latest WordPress, provider acceptance, remote exact CI or installed-artifact behavioral qualification. The existing full HTTP output cases pass; that does not establish exhaustive literal-text parity for every format/device.

## Package and diagnostic review

The **450-file** local rehearsal ZIP SHA256 is `cbaa70e993dfb60c5c474396c88034830542af46ca74c4438a6457e99355146a`. Inventory matches 1.102.79; every archived byte matches source. Only calculator/controller bytes and version/changelog metadata changed. Dirty Git and absent matching CI keep the artifact `local_rehearsal_only`.

Official Plugin Check **2.1.0**, default static checks without exclusions, retains **136 errors / 2,016 warnings**. Two section-input missing-unslash warnings disappeared; the wrapper still returns **1**. The [source review](remediation-2026-10-13/package-quotation-text-slice/source-review.json) traces four controller item/section inputs through the protected facade to row/count/domain validation and field sanitization, with nonce/capability/live-object guards. Four delegated-input justifications are recorded; other controller inputs remain outside this review. The [complete ledger](remediation-2026-10-13/package-quotation-text-slice/diagnostic-triage.json) has **684 justifications / 1,467 review-required / one release-channel limitation**. Earlier dispositions carry only on matching path/line/column/code/source text. No new suppression was added.

The local graph receives a scoped structural/document-heading refresh; full semantic/HTML/report regeneration is not claimed. Test runners remove owned databases/content; the socket-only test server is stopped after checks. No configured site, customer/provider send, commit, push, tag or deployment is part of this slice.

Continue REL-007 using the [current review queue](remediation-2026-10-13/package-quotation-text-slice/diagnostic-review-queue.csv). REL-001 remains dependent on REL-007, M6/M7 and clean exact CI/artifact qualification. Backlog: **42 IMPLEMENTED / two IN PROGRESS / zero CLOSED**.
