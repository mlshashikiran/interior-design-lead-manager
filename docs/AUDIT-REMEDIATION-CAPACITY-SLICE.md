# CORE-010 active workload capacity — local implementation

Author: **Shashi**. Candidate **1.102.53**, local and uncommitted. Core **1.3.8**, routing **1.0.1**, quotation **2.5.0** remain unchanged. CORE-010 is **IMPLEMENTED, not CLOSED** pending runtime and exact release-artifact qualification. No configured site database, release artifact, commit or deployment was used.

## Policy and transaction boundary

An active lead is non-duplicate, outside Won/Lost, and not Unqualified. Qualified leads still consume capacity. Zero retains the established meaning of unlimited capacity. The configured limit does not evict historical owners or force existing over-capacity data into another state.

| Operation | Admission behavior |
|---|---|
| Insert an active assigned lead; move active work to another owner | Require a free slot for the target |
| Reopen closed/unqualified work; clear a duplicate flag into active work | Require a slot even when the owner is unchanged |
| Edit already-active work with unchanged owner | No added workload; permit at or above the cap |
| Close, unqualify, mark duplicate, or unassign | No added workload; release capacity on commit |
| Assign closed/duplicate history | Preserve ordinary ownership rules; consume no active slot |
| Duplicate intake | Existing duplicate detection closes the new historical row; it consumes no active slot |

`IDLM_Assignment_Capacity::validate_transition()` is the shared admission boundary used by canonical insert/update and automatic assignment. It runs after workflow/duplicate normalization and before the required writes. Only positive active-workload deltas require admission. CORE-009's current target eligibility and routing-context checks remain in place for new ownership.

Admission locks the target's existing WordPress account row until the lead transaction commits or rolls back. All cooperating admissions for that representative therefore serialize. It reads the standalone capacity option and grouped fallback through locking SQL, preserving their existing precedence and default 30, then uses a locking count of current active leads. An earlier request cache or repeatable-read snapshot cannot authorize an extra slot. The options lock also prevents a conflicting settings update from changing the value during that admission. Missing accounts, nontransactional users/options dependencies, failed reads or failed required writes cannot acknowledge admission.

Capacity remains derived from committed lead rows; there is no new reservation table, schema migration or durable counter to repair after a crash. Rollback and connection death undo the workload change and release database locks. Lock contention or a deadlock can refuse the command with existing retry guidance; the implementation does not treat a refusal as success or promise automatic command retry.

Unassigned fallback refreshes its context/eligibility/capacity pool for each candidate, including studio-to-general fallback, and retains unassigned backlog when no eligible slot remains. Its candidate query excludes inactive/duplicate work. Every final automatic write repeats admission, protecting simultaneous intake, manual assignment and qualified/unassigned fallback. Advisory pool/status reads fail closed on SQL errors. A full initial routing pool retains the existing successful unassigned-capture behavior; if a selected slot disappears before persistence, the checked command fails and can be retried.

Manual quick-edit and bulk use canonical admission instead of their former early count checks. Bulk retains its existing per-lead commit/failure contract: unchanged owners and closed history can succeed while another active assignment is refused, and the response lists failures. Bulk assignment is not a whole-batch transaction. Actor, nonce and object-access guards, endpoint names and payloads remain unchanged.

## Executed verification

Environment: **PHP 8.4.14 / WordPress 7.1.3 / MySQL 8.4.0**, native WP-Cron, cache off. Native and actual HTTP tests used fresh `idlm_test_*` databases on a task-owned socket-only MySQL instance with outbound HTTP/mail intercepted.

- **270 fast / 0 failed**: four new named cases cover active/Qualified versus closed/unqualified/duplicate workload, denied insert/reassignment without partial aggregates, unchanged-owner edits, closing/reopening, closed history and unlimited zero cap.
- **139 native / 0 failed**: twelve new named cases cover cap 1 with a 201-lead backlog, unlimited two-page recovery, near-cap studio/general and pre-sales recovery, all four initial routing modes, qualified fallback, reactivation/duplicate deltas, current settings and count after an older repeatable-read snapshot, injected capacity read/required-write failures, four synchronized writer pairs and process death before commit. Both workers observe the final free slot before their barrier; exactly one commits active workload. Pairs cover manual/manual, insertion/initial automatic assignment, reopening/intake, and qualified/manual assignment.
- **292 actual HTTP/stdio / 0 failed**: five new cases cover capacity refusal and unchanged-owner success, per-lead bulk results, reopening after a slot is released, rollback without a consumed slot, new manual intake at a full creator, and authenticated webhook capture retained unassigned when the pre-sales pool is full. Affected HTTP requests are warning-free.
- Changed PHP syntax and `git diff --check` pass. Earlier required-write, task-plan, schema/upgrade, eligibility and transport contracts remain passing; older non-capacity fixtures explicitly use unlimited capacity where they seed many leads for one actor.

[Verification, named cases, final logs and source hashes](remediation-2026-10-07/capacity-slice/verification.json), [cleanup](remediation-2026-10-07/capacity-slice/cleanup.json), and [scoped graph refresh](remediation-2026-10-07/capacity-slice/graph-refresh.json) contain machine evidence. The bounded Graphify query “CORE-010 capacity concurrent intake manual assignment unassigned fallback checked transaction” connected capacity to the shared assignment/lifecycle boundary; source verified the audit's stale recovery pool and non-atomic manual checks. Immutable audit findings and earlier evidence are preserved.

## Remaining qualification

CORE-010 is IMPLEMENTED, not CLOSED. Supported PHP/WordPress floors, older MySQL/MariaDB, persistent caches, multisite, database drop-ins and alternative isolation/configuration remain unqualified. The guarantee covers cooperating canonical runtime writers, not arbitrary SQL or another plugin bypassing these boundaries. Large-backlog timing, lock contention/deadlocks across representative transfers, live Action Scheduler, exact release packaging/CI and configured-site rehearsal remain gates. The existing recovery loop's broader paging/time budget and failed-row fairness belong to CORE-012; this slice does not claim bounded total sweep duration. Historical restore or a reduced configured cap may leave existing workload above the new limit; future positive admission is refused until room exists. Next bounded M3 work: **CORE-008 — identity-review lifecycle and erasure**.
