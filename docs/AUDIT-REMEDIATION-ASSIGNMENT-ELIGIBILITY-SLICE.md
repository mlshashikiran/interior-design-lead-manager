# CORE-009 assignment eligibility — local implementation

Author: **Shashi**. Candidate **1.102.52**, local and uncommitted. Core **1.3.8**, routing **1.0.1**, quotation **2.5.0** remain unchanged. CORE-009 is **IMPLEMENTED, not CLOSED** pending runtime and exact artifact qualification. No configured site database, release artifact or deployment was used.

## Policy and persistence

Future ownership requires an existing user with a recognized CRM role, enabled CRM access and effective `idlm_access_leads` capability. Explicit negative user grants prevail. Enabled administrators retain the existing `manage_options` access allowance; the disabled flag also excludes administrators. Editorial roles receive no automatic enrollment: individually enrolled users and eligible second CRM roles follow the existing access policy, including its incomplete-migration guard.

| Assignment context | Eligible target |
|---|---|
| Manual/general | Any user satisfying the shared role/access policy; zero retains manual unassignment |
| Pre-sales intake | Shared policy plus `idlm_pre_sales` role |
| Qualified sales fallback | Shared policy and no `idlm_pre_sales` role, including mixed-role users |

`IDLM_Lead_Access_Policy::is_assignable_rep()` owns this policy. Runtime pre-sales, active representative and configured sales pools filter through it. Round-robin, source/studio selection, unassigned recovery and qualified fallback therefore exclude disabled, deleted and revoked targets even when saved settings still contain their IDs. Historical pre-sales discovery remains separate for legacy handoff backfill.

Canonical insert and changed-owner update validate the target inside the existing lead transaction. Automatic assignment locks the lead, verifies its expected owner and performs the same live target check with its routing context before any ownership, activity, event or task write. The target check reads and locks current WordPress user, metadata and role-option rows, then rebuilds target grants from those values. It does not trust a previously cached pool, `WP_User`, metadata or role definition, or a repeatable-read snapshot taken earlier in the transaction. Missing targets, failed reads, malformed role definitions and non-InnoDB authentication tables fail closed.

The account/meta/role locks serialize ownership writes with conflicting database updates on the tested InnoDB runtime. A disable committed before the final check blocks assignment; a disable that waits behind an assignment takes effect afterward. This is not a bulk reassignment operation. An unchanged historical owner can remain on ordinary canonical edits, and historical representative filters remain available. Existing qualified unresolved-backlog fallback may move a now-ineligible owner according to configured fallback policy; completed handoffs are not newly mass-rewritten.

Bulk mutation choices omit ineligible targets. Inline assignment choices omit ineligible alternative targets while retaining a current disabled owner's selected option as disabled history. Actor capability, nonce and object-access guards remain authoritative. Existing callbacks, payloads and per-lead bulk failure behavior are retained.

## Executed verification

Environment: **PHP 8.4.14 / WordPress 7.1.3 / MySQL 8.4.0**, native WP-Cron, cache off. All native/HTTP data used fresh `idlm_test_*` schemas on a task-owned socket-only MySQL instance; outbound mail/HTTP were intercepted.

- **266 fast / 0 failed**: shared eligibility/context matrix, mixed-role and explicit denial, all pool variants and historical discovery, canonical owner denial without aggregate changes, and manual unassignment.
- **127 native / 0 failed**: ten new named cases cover real grants/enrollment, five independent-connection revocations between cached selection and locking persistence, unchanged aggregate after manual and automatic refusal, invalid insertion/history/unassignment, failed target reads, all three nontransactional authentication dependencies, all four initial routing modes and both fallback flows. Existing transaction/task/schema contracts also pass with genuinely eligible assignment fixtures.
- **287 actual HTTP/stdio / 0 failed**: six new named cases cover quick-edit and bulk target denials for disabled/deleted/role-changed/revoked users, successful eligible transfer/unassignment, actor/foreign-object/nonce denial, and actual admin/shortcode lead mutation choices with disabled owner history. Affected HTTP requests are warning-free.
- Changed PHP syntax and `git diff --check` pass; counts, named cases, source hashes and logs are recorded in [verification evidence](remediation-2026-10-07/assignment-eligibility-slice/verification.json). [Cleanup](remediation-2026-10-07/assignment-eligibility-slice/cleanup.json) and [scoped graph refresh](remediation-2026-10-07/assignment-eligibility-slice/graph-refresh.json) are separate evidence.

The focused Graphify query “CORE-009 disabled representative assignment eligibility manual automatic fallback”, bounded to 1,200 tokens, connected access policy and routing to the checked lifecycle boundary. The audit's role-only manual and existence-only pool findings were verified against source before implementation. The scoped refresh retains unrelated graph content, immutable audit findings and earlier verification evidence.

## Remaining qualification

CORE-010 capacity accounting/concurrency remains OPEN; these tests disable the cap where isolating eligibility and do not establish capacity safety. Supported PHP/WordPress floors, older MySQL/MariaDB, multisite, database/cache drop-ins, persistent cache, alternative transaction isolation, external concurrent account administration across those configurations, live Action Scheduler, volume/lock-contention targets and exact release packaging/CI remain unqualified. The target policy does not repair existing actor-authorization races, provider delivery or unrelated legacy handoff reconciliation atomicity. Next bounded slice: **CORE-010 — capacity accounting and concurrent assignment**.
