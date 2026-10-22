# QUO-005 — Preserve historical item classifications

Author / Builder: **Shashi**. Date: **2026-10-07**. Local candidate **1.102.58**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Quotation schema **2.5.2**; core **1.3.9** and routing **1.0.1** unchanged.

## Finding and policy

The October 6 audit identified an installer UPDATE JOIN that replaced persisted item `is_wood` with the current catalogue flag. QUO-004 skipped this on verification-only 2.5.0 upgrades, but older upgrades and missing schema receipts still rewrote captured values. A new native regression reproduced both wood-to-nonwood and nonwood-to-wood drift on a 2.4.0 upgrade before this fix. The historical audit remains unchanged.

Remove that UPDATE entirely. All upgrade/reinstall paths preserve stored item flags, descriptions, dimensions, quantities, rates, line totals and revision monetary fields. Catalogue edits, archival, deletion or missing catalogue references cannot supply historical evidence. No migration recalculates amounts or discounts.

Add `quote_revision_items.classification_source` (`varchar(30) NOT NULL DEFAULT 'legacy_unverified'`). Provenance meanings:

| Value | Meaning |
|---|---|
| `legacy_unverified` | Preserve the existing stored classification, whose original capture cannot be independently established. Also used when adding a previously missing `is_wood` column: its compatibility default 0 is a placeholder, not proof that the historical item was nonwood. |
| `submitted` | A new save normalized an explicit submitted 0/1 classification. It records submitted state, not an assertion of historic catalogue agreement. |
| `default_nonwood` | A new save used the existing nonwood fallback for omitted/invalid classification; header rows also use this value. |

All existing rows receive the conservative provenance default through additive DDL; known 0 and 1 values are untouched. Preexisting installations have no trustworthy capture receipt, and some may already have suffered the old rewrite, so no legacy row is retrospectively labelled as verified. Old zero/default values cannot distinguish genuine nonwood from lost information. Missing original values require independent historical documents/backups and a separately reviewed repair; today's catalogue cannot reconstruct them.

The calculator derives provenance from normalization and ignores posted provenance. New saves keep the existing submitted-classification behavior and monetary rules. Draft edits create new snapshots through the current save workflow; they do not change source revisions. Repository revision copies use stored rows and preserve provenance. Direct trusted internal inserts that omit provenance remain unverified. This adds no customer-facing implementation metadata or presentation changes.

## Migration and recovery

The column uses the existing QUO-004 schema lock, physical verification, pending/ready receipt and checked final commit. DDL can persist after process death; its conservative default still records unverified legacy rows on retry. Denied ALTER leaves the old version pending, not acknowledged. Independent installers converge without rewriting classification or financial fields. A noncanonical nullable `is_wood` schema is not reconstructed from catalogue data; repair/qualification of unsupported physical schemas remains outside this slice.

Backup's exact column inventory includes provenance. Before the existing core review/task normalizers and strict preflight, complete quotation 2.5.0/2.5.1 manifests add only `legacy_unverified` to old item rows. This composes with supported complete core 1.3.5–1.3.9 manifests. Legacy schema-less version-1 backups receive the same conservative addition only when their item rows match the complete old item inventory; the ordinary full-table/relationship/type validation still runs before writes. Current manifests missing the column, incomplete prior manifests, mixed item inventories and invalid provenance values are rejected before mutation. New backup round trips preserve provenance exactly.

Physical declarations and commercial-state behavior are separate. QUO-010's existing successful lifecycle normalization remains unchanged and open; these tests use current Draft/Superseded semantics. Finalized legacy state qualification and QUO-006 historical currency/payment rendering remain open. Rolling back to an older plugin requires a matching database backup: the new schema receipt/column is not reversed by replacing the ZIP. No configured-site upgrade, database mutation, commit, release artifact or deployment was performed.

## Executed verification

- **294 fast / 0 failed**, including three QUO-005 cases for submitted/default provenance, forged provenance denial, all installer version paths and exact provenance/bit copies.
- **212 native / 0 failed**, including nine new QUO-005 cases. T11/T17 children compare every preexisting item and aggregate field for absent/2.4.0/2.5.0/2.5.1 receipts, wood/nonwood/custom/optional/header/detached rows, missing old classification columns, calculation parity and repeated installation. T10 verifies new persistence and copies. T17 fault/concurrency children cover denied additive DDL, process death before final receipt commit, retry and synchronized independent installers. T16 checks ten combined old quotation/core manifests, new round trips and malformed/forged current or prior backups with no mutation.
- **315 HTTP/stdio / 0 failed**, including three new QUO-005 children. Real authenticated save derives provenance and wood adjustments; a subsequent real boot upgrade after catalogue edits preserves persisted content/totals; multipart old backup normalization and new round trips preserve rows, and current missing-provenance input is rejected. The whole runner's affected-request diagnostics check passes.
- **12 changed PHP files** pass `php -l`; `git diff --check` passes. No JS/CSS/markup/layout change or new browser/viewport claim.

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, no persistent cache. All native/HTTP tests use fresh owned `idlm_test_*` databases on an isolated socket-only server and intercept outbound HTTP/mail. Native stderr retains two existing expected synthetic review-erasure fault messages; fast stderr retains the existing contact-match double's missing-phone warning. HTTP stderr is empty. The first sandboxed MySQL initialization crashed; an owned isolated initialization outside that sandbox succeeded. All test databases/content fixtures and the owned server were cleaned up.

[Named machine evidence](remediation-2026-10-07/quotation-classification-slice/verification.json), [fast](remediation-2026-10-07/quotation-classification-slice/fast.log), [native](remediation-2026-10-07/quotation-classification-slice/native-full.log), [HTTP](remediation-2026-10-07/quotation-classification-slice/http.log), [baseline reproduction](remediation-2026-10-07/quotation-classification-slice/baseline-regression.log), [cleanup](remediation-2026-10-07/quotation-classification-slice/cleanup.json).

The bounded Graphify query **“QUO-005 quotation item classification snapshot backup restore”** connected the finding to migration and recovery contracts. Current source verified the schema/calculator/copy/backup seams. The scoped incremental graph refresh replaces changed PHP structure and explicit focused-document headings, retaining unrelated content without invented semantic edges or full report regeneration.

QUO-005 is **IMPLEMENTED, not CLOSED**. **23 OPEN / 21 IMPLEMENTED**, none CLOSED. Supported runtime/matrix and historical commercial states, real Action Scheduler, persistent cache, custom database drop-ins, older MySQL/MariaDB, production-sized migration/lock duration, exact CI/artifact and configured-site upgrade/rollback rehearsal remain unqualified. These named children do not complete whole T10/T11/T16/T17/T20 groups.

Next: **QUO-010 — preserve historical commercial quotation states**.
