# Coupled lead persistence and after-commit delivery

Author / Builder: **Shashi**. Date: **2026-10-07**.
Findings: **CORE-004 / CORE-005**. Status: **IMPLEMENTED locally, not CLOSED**.
Candidate: **1.102.50**, uncommitted working tree; core schema **1.3.7**, routing **1.0.1**, quotation **2.5.0**. No deployment or configured-site database changes.

## Checked aggregate boundary

`IDLM_DB` retains its public insert/upsert/update facades. `IDLM_Lead_Lifecycle_Service` retains normalization, validation, attribution and state decisions. `IDLM_Lead_Transaction` owns one checked transaction and permits nesting only beneath that owner. Locked current lead reads precede transition validation. Inbound identity locks remain held through the outer commit. Required InnoDB engines are checked on mutations, including a deployment whose ready marker conceals an externally changed engine.

| Operation | Required synchronous persistence |
|---|---|
| Canonical capture | Lead, creation activity, initial reporting/assignment events, assigned pre-sales plan when applicable, touchpoint, hook/cache intent |
| Matched intake | Checked attribution merge, activity, touchpoint and any attribution/cache intent |
| Conflicting intake | Retained review and activity on each candidate; no actionable lead/routing |
| Lead update | Normalized row/timestamps/handoff state, applicable immutable events, qualification task completion, Lost archival, reassignment task reconciliation/sales task, activities including handoff/Lost remarks, hook/cache intent |
| Automatic assignment | Existing expected-owner comparison, row/timestamps/handoff state, assignment activity/event, required six-task intake or sales work, cache intent and configured handoff notification intent |
| Fresh six-task plan | Locked lead, all six tasks and creation activity; failure at any position rolls back the new plan |

Every required write and commit is checked. Inserted dependent rows must report one inserted row. Failed required reads cannot become an unmatched capture. External-ID replay succeeds only after verifying the existing `(platform, external_id)` tuple belongs to the same canonical lead; submissions without external IDs remain separate touches. A failed capture/merge rolls back attribution and required history and produces no success hook. Manual and routed CSV/webhook capture now include their required work before acknowledgement. No eligible intake pool legitimately retains an unassigned lead.

An unrelated active transaction is refused without committing or rolling it back. The next-transaction `SET TRANSACTION READ WRITE` guard uses MySQL's rejection of characteristics changes inside a transaction; see the [MySQL transaction reference](https://dev.mysql.com/doc/refman/8.0/en/set-transaction.html). Private-file migration now uses this transaction owner so its nested canonical reference update remains atomic with the verified file copy. Quotation transaction ownership and direct commercial summary projection remain separate.

Core `wpdb` reconnect retries are disabled while the aggregate transaction is owned. A dependent write must not replay on a new autocommit connection after losing the transaction. Connection/worker-lock ownership is checked before commit. Failed SQL is suppressed from raw diagnostic output; callers receive retry guidance through the existing false/error contracts. A disconnect or uncertain commit can still make client acknowledgement ambiguous; this is not a general command idempotency guarantee for arbitrary manual inserts.

## Durable after-commit handoff

Additive `idlm_lead_outbox` stores lead identity, recovery generation, whitelisted hook, typed JSON arguments, attempts, availability and creation time in the same transaction. Existing conversion, attribution, mapped/legacy Interakt hook names and payloads are preserved, including the old-lead object. Cache invalidation also has durable intent. Jobs schedule only the sweep hook, without credentials or copied contact payloads in scheduler arguments; the existing provider queues retain their separate M4 behavior.

After a successful commit, cache generations rotate and intents are dispatched. Hook/queue/mail exceptions and failed acknowledgement leave the committed lead successful and the intent durable. Known CAPI/Interakt scheduling failures are detected at this handoff. Interakt's synchronous refusal is retryable. The pending intent is retained after callback failure or process death; attempts and next availability are stored before callback execution. Backoff starts at 30 seconds and caps at one hour. A database/prefix-scoped session lock prevents two intent workers invoking the same handoff simultaneously. Each sweep reads at most 25 due intents. Action Scheduler uses a minute recurrence when available, with an `idlm_minute` WP-Cron recurrence otherwise; the normal configured cron runner must execute it.

Delivery is **at least once**, not exactly once. Process death after a callback/enqueue but before acknowledgement can replay a hook. Consumers must tolerate replay; successful provider log deduplication, configuration cancellation, provider idempotency and uncertain-send recovery remain M4 responsibilities. There is no cross-provider all-or-nothing send guarantee. Pending arguments can contain the original CRM row required by compatibility callbacks; they are protected CRM data and do not contain configured credentials. Undelivered intent is retained rather than silently expiring.

Workers require a current recovery epoch and live lead. Lead deletion removes dependent outbox rows; reset and complete restore clear pending intents transactionally. Plugin JSON backups exclude delivery state so restore cannot replay old intent. Complete previous core **1.3.5/1.3.6** manifests remain compatible with the unchanged exported aggregate columns; unknown versions remain rejected. Restore also verifies the outbox's physical schema/engine before clearing it. Deactivation clears the sweep schedule while preserving data; actual deactivate/reactivate qualification remains pending.

## Verification and preservation

[Machine evidence and logs](remediation-2026-10-07/lifecycle-slice/verification.json) record the source hashes, commands, named cases and limits. Final candidate checks:

- **258 fast PASS / 0 FAIL**; ten new focused cases.
- **104 native PASS / 0 FAIL**; 35 new focused cases, with real WordPress/MySQL and separate connections/processes.
- **278 actual HTTP/stdio PASS / 0 FAIL**; seven new actual AJAX/webhook cases, including no-state-change failure responses, successful retry and canonical external replay.
- Existing inbound/task and optional Action Scheduler focused contract runners PASS.
- All **24 changed PHP files** pass `php -l`; `git diff --check` passes.

Native evidence includes each required dependency failure, failed commit, each position in a fresh six-task plan, matched UPDATE/read errors, zero-row touchpoint insert, review activity failure, reassignment task failure, a foreign transaction, unsupported engine, independently observed committed history/old hook arguments, callback/acknowledgement/cron-enqueue failure, bounded replay, obsolete epochs/deleted leads, actual process death before/after commit, real connection kill, real lost worker lock, synchronized concurrent repeat intake, serial transition history and two simultaneous dispatch workers. Deletion/restore/reset erase pending intents. Earlier private-file and recovery tests remain passing.

Runtime: **PHP 8.4.14 / WordPress 7.1.2 / MySQL 8.4.0**, native WP-Cron, object cache off. All native/HTTP tests created fresh `idlm_test_*` databases and disposable WordPress content; outbound HTTP/mail was intercepted. The task-owned MySQL server and databases were removed after verification. Baseline hashes preserved **899 existing files**; immutable audit and previous remediation evidence were not rewritten. Graphify's focused query linked intake/lifecycle to the existing recovery fence; final graph refresh is scoped to affected code/documents and retains outside-scope graph state.

## Remaining qualification and scope

Full supported PHP/WordPress versions (including WP 5.8), older MySQL/MariaDB, a live Action Scheduler installation, persistent cache, database drop-ins/failover, exact release ZIP/revision/CI, full operator upgrade/rollback and representative volume budgets are not qualified here. Core `wpdb` behavior was exercised; custom database reconnect implementations need qualification. Physical prior-release upgrades use the existing synthetic historical marker/row fixtures, not production backups or every historical release's physical DDL.

Historical partial task plans still need **CORE-006** repair/cycle identity; eligibility/capacity/review/retention remain **CORE-008/009/010/012**. Other administrator commands that write call logs/comments/tasks outside these canonical aggregates do not acquire universal command atomicity. HTTP authorization is still owned by the existing controllers; this work preserves those checks and does not qualify every stale-editor/owner race. Ordering across retried older hooks and newer transitions, running provider work versus deletion, revoked copied provider credentials and provider uncertain-send handling remain **M4 / INT-004 through INT-010**. Undelivered-intent age/queue alerting and large backlog recovery latency need operational qualification. Arbitrary third-party hook side effects/implicit commits are outside the trusted transaction contract.

Quotation migration/snapshot/engine findings remain open. Previous historical quotation/security probe failures and limits are preserved in their respective audit/slice evidence; they were not rerun or claimed repaired by this slice. **CORE-004 and CORE-005 remain IMPLEMENTED, not CLOSED.** No commit, deployment or release artifact was produced.
