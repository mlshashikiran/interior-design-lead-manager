# CORE-006 — restartable six-cycle task plans

Builder: **Shashi**. Date: **2026-10-07** (Asia/Kolkata).
Candidate **1.102.51**, core schema **1.3.8**; routing **1.0.1** and quotation **2.5.0** unchanged. Local, uncommitted: **IMPLEMENTED, not CLOSED**. No configured-site database, deployment, external delivery or release artifact was used.

## Repair boundary and stable identity

`IDLM_DB` keeps its existing creation, synchronization, rescheduling and scheduled recovery methods as facades for `IDLM_Presales_Task_Plan`. The helper shares `IDLM_Lead_Transaction`: it locks the live lead, reads task rows under lock, checks every adoption/insert/update/activity/cache-intent write and commit, and rolls the aggregate back on failure. Concurrent or interrupted callers can retry; recovery admission can return busy without implying success.

The additive nullable `idlm_tasks.qualification_cycle` identifies cycles **1–6**, protected by unique `(lead_id, qualification_cycle)`. Manual and sales tasks keep NULL; multiple unrelated tasks remain valid. Cycle identity survives changes to display labels and due dates. Installation verifies the column/index/engine before advancing the independent schema marker.

Legacy adoption happens lazily under the lead transaction. Exact historical `Qualification day N:` and `Qualification cycle N:` prefixes, for distinct N in 1–6, map to the same slots. A known four-day plan gains cycles 5 and 6. Adoption changes only identity; filling preserves existing IDs, titles, due dates, completion timestamps, creation timestamps and owner history. Only missing slots are inserted, with one checked creation/repair activity when rows are added. Repeating a completed repair leaves rows and activity unchanged.

Unknown labels, duplicate legacy cycle labels or invalid stored cycles produce failure with no partial adoption or speculative deletion/renumbering. Such history needs administrator investigation; this slice does not provide a new history-resolution UI. Null legacy identities are intentional pending adoption, not an assertion that every historical row has been migrated.

## Task policy

| State / operation | Behavior |
|---|---|
| Active, assigned pre-sales plan | Fill missing cycles and reconcile from the existing outreach evidence/targets |
| Existing completed task still supported by evidence | Keep completion timestamp and historical owner |
| Evidence decreases on an active plan | Reopen affected cycles, clear completion timestamp and assign pending work to the live owner |
| Pending overdue cycles | Schedule in cycle order on distinct configured working dates, starting today; skip today after a completed cycle when no new active outreach exists |
| Active reassignment | Within the lead aggregate, transfer pending cycles to the live owner; keep supported completed history attributed to its original owner |
| Automatic assignment of old unassigned partial work | Fill and synchronize inside the checked assignment aggregate; a failed missing insert rolls back owner/history/task changes |
| Qualified, Unqualified, commercial stages, Won, Lost, duplicate | Repair/synchronization does not add or reopen qualification work; existing qualification/unqualification completion and Lost cancellation rules remain authoritative |
| Explicit permitted reversal to active state | Existing plans resume through canonical lead update and reconcile against retained outreach evidence |
| Unassigned lead | Scheduled synchronization does not create work without an owner; trusted creation retains its explicit owner fallback |
| Manual qualification CRUD | Existing nonce/capability/object guards and reserved-type restrictions remain unchanged |

The scheduled fallback facade now discovers eligible assigned leads even when they have zero task rows. Each overlap-safe sweep examines at most **100** leads in ID order under a scoped session lock and recovery lease. A persisted cursor advances to later pages and wraps at the end; failed repair is reported without contact data and retried on the next full pass. A failed cursor write cannot acknowledge success. A crash before checkpoint can repeat a page safely. Large-backlog delay, live Action Scheduler behavior and multi-node qualification remain release gates.

The existing outreach AJAX command records evidence before task reconciliation. If reconciliation fails, it returns explicit repair guidance and retains that recorded evidence for scheduled recovery; it does not falsely report a successfully repaired plan. This is not a new whole-command idempotency guarantee, and users should not re-log already recorded outreach merely to repair tasks.

## Backup and upgrade compatibility

Current exports retain the cycle column and round-trip stable identities. Complete prior core **1.3.5/1.3.6/1.3.7** manifests with the old task inventory normalize to nullable cycle identities before strict preflight/staging. Rows are adopted on later active repair. Current manifests missing the new column, invalid cycles, duplicate cycles and cycles attached to another task type remain invalid. Schema changes are additive; installer repetition and upgrade of copied historical rows are exercised natively. No configured site was upgraded.

## Executed verification

Environment: **PHP 8.4.14 / WordPress 7.1.2 / MySQL 8.4.0**, native WP-Cron and cache off. Outbound HTTP/mail were intercepted before installation. Every database used a newly created `idlm_test_*` schema on a task-owned no-network MySQL socket.

- **263 fast / 0 failed**: five new behavior cases cover all historical missing slots, repeat/preservation, checked repair faults, ambiguity, closed/duplicate states and identity independent of labels/dates.
- **117 native / 0 failed**: thirteen new named cases cover every missing slot, old four-day plans, adoption/insert/activity/intent/commit faults, ambiguous history, evidence-driven completion/reopening, working-day progression, manual and automatic assignment, closed-state/reversal policy, two synchronized repairs, repair versus Lost, real additive upgrade/unique constraints, a 101-lead checkpoint run and current/three prior backup manifests with malformed preflight denials.
- **281 actual HTTP/stdio / 0 failed**: three new named cases exercise authorized outreach repair, reserved manual task denials, failure acknowledgement with retained evidence and recovery, and denied representative/foreign requests.
- **17 changed PHP files lint PASS**; `git diff --check` PASS. Earlier fast/native/HTTP contracts remain passing. Historical audit failures are preserved, not rewritten or claimed universally repaired.

Synchronized workers retry recovery-admission contention within a bounded limit and assert one cycle per slot and one repair activity. Native faults compare the entire affected aggregate before/after failure. The added automatic-assignment fault compares lead owner, immutable history, tasks, activity and pending intents. The backup preflight cases independently assert unchanged rows/options after denial.

Machine evidence and full final logs: [task-plan evidence](remediation-2026-10-07/task-plan-slice/verification.json), [cleanup](remediation-2026-10-07/task-plan-slice/cleanup.json), [scoped graph refresh](remediation-2026-10-07/task-plan-slice/graph-refresh.json). The graph query “CORE-006 partial presales six task plan repair reopening reassignment”, bounded to 1,200 tokens, connected the finding to checked lifecycle persistence; source and docs then established the implementation. Updated graph scope covers changed PHP and focused current documentation while preserving the immutable audit and prior evidence.

## Remaining qualification

CORE-006 is IMPLEMENTED, not CLOSED. Supported PHP/WordPress floors/matrix, older MySQL/MariaDB, real Action Scheduler, persistent cache, custom database drop-ins, commit ambiguity, package/CI/artifact and configured-site rehearsal remain unqualified. Legacy ambiguous history requires a separately reviewed decision; bulk semantic renumbering/deletion is not authorized by ordinary repair. Large backlog timing, retention effects on outreach evidence, broader task/owner authorization races and provider delivery remain separate gates/findings. No release artifact, commit or deployment was created. Next bounded work: CORE-009 assignment eligibility, followed by CORE-010 concurrent capacity.
