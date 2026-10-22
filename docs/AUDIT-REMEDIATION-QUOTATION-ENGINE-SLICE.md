# QUO-007 — Verify transactional engines before quotation writes

Author / Builder: **Shashi**. Date: **2026-10-07**. Local candidate **1.102.60**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Core **1.3.9**, routing **1.0.1**, quotation **2.5.3** and lifecycle receipt **1.1.0** unchanged. No physical schema or backup-manifest change.

## Finding and current contract

The immutable October 6 quotation review identified transactions relying on unchecked default/legacy engines. QUO-004 already added explicit InnoDB declarations and checked installation; QUO-007 closes the remaining runtime drift boundary. A new native baseline against the pre-QUO-007 repository acknowledged a number reservation on an actual MyISAM sequence table. This observation is confined to an owned disposable database, not the configured site.

`IDLM_Quotation_Repository::begin_checked_transaction()` refuses foreign transaction ownership before START, sets READ COMMITTED for the next owned transaction, and checks the physical engines before any aggregate or working-copy write. It returns `true` or a checked `WP_Error`; it rolls back its own failed preflight without committing or rolling back a foreign owner's work. Save and catalogue import redirect with actionable repair guidance; autosave/discard return JSON error/503. Number reservation returns an error without advancing the sequence. Existing capabilities, nonces, object binding, live ownership and historical read-only rules remain enforced.

Every check is fresh, including when schema/backfill receipts already say ready. It opens each table with a zero-row locking read in sorted order, checks its InnoDB engine through physical metadata, and retains the metadata locks until COMMIT/ROLLBACK. Missing tables, denied/failed metadata reads, failed pinning and unsupported engines refuse writes. Concurrent ALTER cannot change a verified engine while the transaction remains open; once ALTER succeeds after completion, the next request detects it. SQL details are suppressed during this preflight; users receive repair guidance rather than a SQL dump.

Locking reads avoid creating a data snapshot themselves, but engine metadata queries can establish a repeatable-read view before a lead-lock wait. Transaction-local READ COMMITTED ensures later live user/role reads see committed revocations after the wait. The connection's session default remains unchanged. Native independent-connection visibility and existing actual HTTP user/role/reassignment barriers verify this behavior; ordinary repeated revisions and UUID retry still pass.

## Checked dependencies and scope

| Transaction | Required InnoDB tables |
| --- | --- |
| Create/revise save | All ten canonical quotation/catalogue tables, leads, lead activity, WordPress options (13 tables) |
| Autosave/discard | Quotes, revisions, status history, leads, WordPress options (5 tables) |
| Catalogue CSV import | Catalogue items, categories, units (3 tables) |
| Standalone number reservation | Number sequences (1 table) |
| Caller-owned number reservation | Recheck and pin sequence metadata inside the existing save/migration transaction; never start/commit a nested transaction |

No automatic engine conversion is attempted. Operators must back up and repair missing tables or migrate unsupported engines, then retry. Historical rows and numbering remain unchanged on refusal; authorized persisted detail reads still work when installed receipts are current. Existing pending-schema boot behavior remains QUO-004. Explicit installer verification continues to reject legacy MyISAM without silently converting it; fresh canonical declarations create ten InnoDB tables even under a session MyISAM default.

This guard covers the transaction-owning quotation/controller paths and number reservation. It does not claim that every single-row catalogue/settings write is a new aggregate transaction, that activity/working-copy cleanup after a committed save is part of that save's transaction, or that external object-cache writes are rollbackable. Current recovery fencing, connection-loss behavior and those pre-existing boundaries are unchanged. Supported drop-ins/persistent caches and production metadata-lock duration still require qualification.

## Executed checks

- **299 fast / 0 failed**, including three new QUO-007 cases. Children check each dependency/scope, MyISAM/missing metadata, metadata pin failures, foreign ownership preservation, numbering refusal and supported rollback.
- **224 native / 0 failed**, including five new QUO-007 cases. Every one of the thirteen save dependencies is converted individually to MyISAM after successful install, refused with ready receipts/rows retained, and repaired/rechecked. Other children cover missing tables, failed pins/engine reads, foreign transaction ownership, independent metadata-lock timeout followed by successful ALTER/refusal/recovery, later committed data visibility with unchanged session default, standalone sequence refusal, and fresh declarations under a MyISAM default.
- **349 actual HTTP/stdio / 0 failed**, including twenty-three new QUO-007 cases. Owner/administrator create/revise refusals cover all thirteen dependencies; applicable autosave/discard refusals retain working copies. Authorized historical reads continue. Three actual multipart catalogue imports reject unsupported engines without partial terms/items. Metadata faults and item/history/lead-projection/exact-COMMIT failures preserve complete aggregates; retry creates one next revision. Repaired create/autosave/discard/import work. Existing independent permission/user-role/reassignment/finalization barriers pass.
- **6 focused native / 0 failed** (five QUO-007 cases plus the mandatory fresh-schema prerequisite) before shared verification. **11 changed PHP files** pass `php -l`; `git diff --check` passes. No CSS, layout, markup or public route registration changes; no new browser/viewport qualification claimed.

The disposable catalogue's wide `status_name` index exceeds MyISAM's key limit. Fixtures temporarily shorten that index for conversion, preserve every row, then restore the original InnoDB index. Fresh-default verification uses untouched canonical declarations. This fixture adjustment is not an application migration or a production repair recipe.

Baseline and initial failing runs are retained. The revocation tests detected stale snapshots during implementation; transaction-local isolation fixed that regression. No existing assertions were removed. Expected item/history/COMMIT fault diagnostics are routed to a separate owned log and strictly checked against the three known synthetic messages; unexpected fault-request diagnostics fail the test. The normal affected-request diagnostic check still requires an empty PHP error log. Full native stderr retains the two pre-existing synthetic review-erasure diagnostics; fast stderr retains the pre-existing contact-match double's missing-phone warning. Final HTTP stderr is empty.

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, no persistent cache. Only fresh owned `idlm_test_*` databases and isolated HTTP/content fixtures; outbound HTTP/mail intercepted. Runners removed their databases/content fixtures; no owned test schemas remain, and the socket-only test server was shut down. No configured-site database mutation, commit, package or deployment. [Named verification](remediation-2026-10-07/quotation-engine-slice/verification.json), [baseline](remediation-2026-10-07/quotation-engine-slice/baseline-regression.log), [fast](remediation-2026-10-07/quotation-engine-slice/fast.log), [native](remediation-2026-10-07/quotation-engine-slice/native-full.log), [HTTP](remediation-2026-10-07/quotation-engine-slice/http.log), [cleanup](remediation-2026-10-07/quotation-engine-slice/cleanup.json).

The bounded Graphify query **“QUO-007 quotation transactional engines save autosave discard”** located the audit's engine finding. Current source confirmed installer verification existed and runtime write validation was absent. Scoped incremental refresh re-extracts changed PHP and explicit document headings, retaining unrelated graph content without invented semantic relationships or full report regeneration.

QUO-007 is **IMPLEMENTED, not CLOSED**. **21 OPEN / 23 IMPLEMENTED**, none CLOSED. Exact CI/artifact, supported minimum/runtime matrix, older MySQL/MariaDB, custom database drop-ins, persistent cache/real Action Scheduler, production-sized writes/metadata-lock waits and configured-site engine repair/recovery remain unqualified. Whole T10/T11/T17/T20 groups are not declared complete. QUO-008 numeric domain limits and QUO-006 historical document settings remain separate findings.

Next: **QUO-008 — validate finite, representable quotation numbers and consistent quantity calculations**.
