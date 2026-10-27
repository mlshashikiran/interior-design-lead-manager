# QUO-004 — Checked quotation migration completion

Author / Builder: **Shashi**. Date: **2026-10-07**. Local candidate **1.102.57**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Quotation schema **2.5.1**; core **1.3.9** and routing **1.0.1** unchanged. No column inventory change.

## Finding and implementation

The October 6 QUO-004 finding showed schema/snapshot/lifecycle markers advancing after failed SQL. `IDLM_Quotation_Schema` now owns a canonical ten-table declaration manifest and reuses `IDLM_Schema_Migration` for session locking, physical column/type/null/default/index/engine verification and checked marker publication. New declarations explicitly request InnoDB. Existing nontransactional quotation tables are refused without automatic conversion. Lead and WordPress option dependencies must also be InnoDB before transactional backfills.

Installation refuses an existing transaction owner before implicit-commit DDL and registers with the recovery fence. After future-marker checks, it durably publishes `idlm_quotation_migration_status=pending` before DDL. `dbDelta()` may leave completed DDL in place; physical metadata, not its notices, determines success. Seeds/settings cleanup, legacy aggregate migration, snapshot/section backfill and lifecycle normalization use checked phase transactions. Required reads/writes, section IDs, numbering, history, pointer writes, completion predicates, marker writes and commit results are checked. A failed phase rolls back its data and marker; prior successful phases can remain committed and be skipped after revalidation.

Legacy and revision scans use ID keysets in pages of 100. Each data phase remains one transaction, so paging bounds PHP materialization, not total transaction duration. Legacy numbering participates in that phase transaction and does not start/commit a nested transaction. Existing canonical pointers and section bindings make retry idempotent. Orphan or cross-revision bindings that the existing repair cannot safely resolve fail completion rather than being silently accepted.

Substep marker versions remain compatible. Even a preexisting marker is rechecked against data completion predicates during installation; a false old snapshot/legacy/lifecycle receipt cannot bypass required work. The overall schema version and `ready` status are published together only after successful physical and data checks. Explicit reinstallation failure leaves status pending even if its version was already current. Boot/health checks read committed marker/status values directly, avoiding premature object-cache acknowledgements after interruption. Transaction rollback invalidates affected option caches and reconnect retries are disabled while a data transaction owns the connection.

`IDLM_Quotations` preserves its callbacks and delegates while returning `true`/`WP_Error` from installation and migration delegates. Activation checks installation before scheduling. Normal boot checks quotation completion before registering CRM handlers; failure produces retryable 503 on CRM AJAX/REST/cron requests and a private actionable administrator notice. Pending phases retry on a later request. The runtime status is excluded from backup payloads. Complete same-site quotation **2.5.0** backups remain accepted when the full physical column manifest matches.

## Preserved semantics and remaining findings

A 2.5.0-to-2.5.1 verification upgrade or repeated 2.5.1 install skips the old unconditional catalogue-to-item classification rewrite. Older upgrades still have the original classification migration; deciding missing historical snapshot provenance remains **QUO-005**. This slice does not close that finding.

Lifecycle normalization still implements the existing Draft/Superseded mapping. Its failure is now atomic and retryable, but preserving legacy Shared/Accepted/Rejected/cancelled state, pointers and dates remains **QUO-010**. The failure test proves that original Shared state survives a failed phase; successful retry intentionally exercises the existing normalization semantics and is not evidence of commercial-history preservation. Historical upgrades must remain behind that open release gate.

Physical engine checks apply to installation/migration. Detecting engine drift before every normal quotation write remains **QUO-007**. Supported historical/runtime/volume/drop-in qualification is still pending. This implementation does not claim that a DDL transaction can roll back table alterations or that the full installer is one atomic transaction.

## Verification

Final source passes:

- **291 fast checks / 0 failed**, including seven new QUO-004 contracts for lock/future-marker refusal, seed/engine/marker/commit faults, pending substeps at a current version, callback registration refusal, foreign transaction ownership and explicit reinstall status.
- **202 native checks / 0 failed**, including **27 new QUO-004 cases**. Native evidence covers the ten-table physical manifest, denied CREATE/ALTER/index repair, MyISAM preservation, snapshot read/update/lookup/insert/binding/marker/commit failures, falsely completed old markers, lifecycle partial rollback, legacy read/quote/revision/pointer/history faults with numbering rollback, foreign transaction and competing-session refusal, two synchronized installers, process death before backfill and final-receipt commits, stale cache receipts, full prior 2.5.0 backup round trip and failed current-version reinstall retry.
- **2 final focused native checks / 0 failed**: repeated fresh-schema prerequisite plus one additional 2.5.0 verification-upgrade/reinstall case preserving an entire captured aggregate after catalogue classification changes. Across the final full and targeted runs: **203 distinct native cases**. Production source was unchanged between these runs.
- **312 HTTP/stdio checks / 0 failed**, including four new actual boot cases for failed quotation DDL, AJAX/REST 503, private notices, pending backfills at current schema version and next-request recovery. Responses do not expose synthetic PII or SQL, and rejected requests preserve aggregates/jobs/outbound attempts.
- **15 changed PHP files** pass syntax checks; `git diff --check` passes. No JS/CSS or visual layout changes; no new browser/viewport claim.

Final native environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, no persistent cache. The owned server is socket-only; harnesses use fresh `idlm_test_*` databases. The configured site database was not used. Fast stderr retains the previously recorded contact-match test-double missing-phone warning. Full native stderr contains two expected synthetic review-erasure fault messages from existing tests. Final HTTP stderr is empty.

Earlier focused native logs contain 24 passing checks before the final cache/status/backup additions; the full 202-check native run is the final-source evidence. [Verification JSON](remediation-2026-10-07/quotation-migration-slice/verification.json), [fast log](remediation-2026-10-07/quotation-migration-slice/fast.log), [full native log](remediation-2026-10-07/quotation-migration-slice/native-full.log), [targeted preservation log](remediation-2026-10-07/quotation-migration-slice/native-preservation.log), [HTTP log](remediation-2026-10-07/quotation-migration-slice/http.log), [cleanup](remediation-2026-10-07/quotation-migration-slice/cleanup.json), [graph refresh](remediation-2026-10-07/quotation-migration-slice/graph-refresh.json).

## Graph and qualification

The bounded Graphify query **“QUO-004 quotation schema install migration verification marker backfill”** connected the finding to existing core-schema verification and the quotation/history findings. Source inspection established the installer, facade, boot, health and backup seams. The scoped refresh extracts changed PHP and explicit focused-document headings; unrelated graph content is retained, without inferred semantic relationships or HTML report regeneration.

QUO-004 is **IMPLEMENTED, not CLOSED**. **24 OPEN / 20 IMPLEMENTED**, none CLOSED. Supported runtime floors/matrix, real Action Scheduler, persistent-cache integration, older MySQL/MariaDB, custom database drop-ins, production-sized migration duration, commit-acknowledgement ambiguity, exact artifact/CI and configured-site upgrade/rollback rehearsal remain unqualified. No commit, release package or deployment was created.

Next: **QUO-005 — preserve historical item classifications during schema install**; then the outstanding commercial-state preservation prerequisite QUO-010 before historical quotation upgrade qualification.
