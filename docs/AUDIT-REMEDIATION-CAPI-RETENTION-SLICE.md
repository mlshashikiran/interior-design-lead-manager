# INT-008 — CAPI retention and delivery evidence

Author / Builder: **Shashi**. Date: **2026-10-09**. Candidate: **1.102.69**, local/uncommitted; schemas unchanged. Status: **IMPLEMENTED, not CLOSED**.

## Current-source finding and reproduction

The October 6 audit is immutable. CORE-012 had already removed the unconditional seven-day CAPI deletion: current bounded retention honors configured `integration_logs_days` (default 180), while diagnostic activity remains seven days. INT-008's remaining defect was ordinary CAPI's exclusive reliance on successful log rows for replay protection. A fast regression accepted an event, removed its logs, then sent again: expected one provider call, actual two. [Baseline](remediation-2026-10-09/capi-retention-slice/baseline-fast.log).

Graphify query “CAPI delivery retention success idempotency retry”, budget 1,100 tokens, connected `IDLM_CAPI`, retention and manual retry evidence. Source verified the configured-retention correction and the missing ordinary durable delivery state. `IDLM_CAPI_Delivery` now owns that state behind the existing platform/lead session lock and transaction/recovery owners. Platform methods, queued hooks, retry AJAX/nonces/capabilities, provider payload shape and settings remain stable.

## Policy and implementation

- Replay identity remains lead/platform/exact event name, matching the established ordinary dedup contract. Related duplicate-lead receipts also block canonical replay; uncertainty blocks even a forced canonical refresh. A changed destination alone does not reset an already accepted event. Intended forced attribution refresh remains permitted for confirmed outcomes; it finds pruned success evidence and sends each platform/event once per callback.
- Minimal nonautoload receipts contain lead/platform/event, status, historical-success boolean, attempt ID and update time. They contain no contact/provider request/response, credential or commercial payload. Receipt reads use primary locking reads and reject malformed state.
- Before HTTP, a checked independent InnoDB transaction commits processing state. HTTP occurs outside the transaction. Afterwards the log and receipt outcome commit together. Receipt/log/commit failure withholds success and leaves the prior processing claim as an uncertainty fence. Interrupted processing becomes uncertain on the next attempt and blocks ordinary/manual/forced resend. Confirmed rejection can use existing retry rules; manual per-root attempt limits remain unchanged. Prior acceptance survives a later failed attribution refresh.
- Retention reads at most 100 expired CAPI metadata rows with a five-second application deadline. It never materializes request/response bodies for migration. Surviving successful legacy identities are copied to receipts in the same transaction as exact-ID log deletion and the checkpoint. Receipt/delete/commit failures roll back the batch and retain logs; the existing generic failure/continuation path remains active. Already erased leads do not acquire new receipts from orphan diagnostics. No migration completion marker can falsely advance.
- Receipt lifetime follows the lead's CRM state, independently of configured diagnostic expiry. Explicit lead deletion and CRM reset erase receipts transactionally. Same-site restore preserves local receipts across recovery generation changes; backup export/import excludes them. Historical successes present in restored logs remain useful until adopted. A database loss, explicit erasure followed by restore, or earlier pruning cannot reconstruct evidence absent from both local receipts and surviving logs.
- No configured-site data/settings, real provider sends, schema changes, commit, package or deployment were performed. Provider-specific success interpretation remains the existing adapter contract.

## Executed verification

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, persistent cache off. Tests used new owned `idlm_test_*` schemas on a dedicated temporary socket-only MySQL server, isolated files and HTTP/mail interception.

| Command / evidence | Result | Scope |
|---|---|---|
| `php tests/run.php` | **340 PASS / 0 FAIL** | Five INT008 children: post-log replay denial, failed outcome uncertainty, claim-write refusal/retry, failed forced refresh preserves success, minimal legacy adoption preserves interrupted state |
| `php tests/integration/run.php` | **275 PASS / 0 FAIL** | Full native suite including retention, dispatch, independent workers, restore and erasure; `native-full-final.log` |
| `IDLM_TEST_FILTER=INT008 php tests/integration/run.php` | **14 PASS / 0 FAIL** | Final focused run after adding erased-lead migration guard: fresh schema plus thirteen INT008 children; `native-final-focused.log` |
| `php tests/integration/quotation-http.php` | **414 PASS / 0 FAIL** | Full actual HTTP/stdio suite including three new INT008 AJAX children; `http-full-final.log` |
| Changed PHP lint and `git diff --check` | **PASS** | Runtime and registered test files; inventory in evidence |

Native children cover all three adapters after aged logs pass configured expiry, stage reversal/return, 30-day success/failed logs under 180-day policy, 101-row bounded adoption and duplicate-family replay, receipt/delete/commit rollback, pre-send claim and post-send acknowledgement faults, interrupted ordinary acceptance, malformed receipts/MyISAM options refusal, forced attribution after pruning, same-site restore, explicit erasure/reset and orphan diagnostics. Separate synchronized processes race pruning against ordinary dispatch. In the race fixture, recovery-busy outcomes use bounded retries to exercise the existing scheduled-continuation behavior; no sequential loop is represented as concurrency.

Actual administrator AJAX children reject a new failed-log retry root after earlier acceptance is pruned, preserve legacy accepted identity and refuse uncertain resend after log deletion. Existing auth/nonce, manual retry and recovery children passed in the full suite. The native suite has **276 distinct cases across these runs**, not a claim that one full run contained 276.

Initial fixture failures were incorrect lead/assertion helpers and an escaped-prefix fault selector; these were corrected. Final fixture adjustments added canonical-family SQL support in the test double and cleared its cached checkpoint after independent worker writes. The final fast/focused native runs verify the added erased-lead and canonical-uncertainty guards; the broader native/HTTP logs precede those last guards. The first full HTTP run counted deliberate Google credential-denial debug logging as PHP diagnostics. Its existing Google audience helper now isolates only the exact expected denial messages; unexpected diagnostics remain in the failure log. Final HTTP stderr is empty. The fast runner retains the same seven pre-existing `num_rooms`/`phone` fixture warnings, with shifted source lines; new fixtures supply their required fields. [Logs, source hashes and cleanup](remediation-2026-10-09/capi-retention-slice/verification.json).

## Remaining qualification

Previously lost success history cannot be recovered and is not invented. Receipt storage is local and excluded from plugin JSON backups; operators need full database recovery to retain pruned delivery evidence after database loss. Explicit erasure intentionally removes this replay history. Provider acceptance/time windows, complete timeout/malformed/error variants, exactly-once external effects, a reconciliation interface, persistent-cache/distributed topology, retention volume, all timezone/policy changes during a run, supported runtime matrix and exact release artifact are not qualified. An in-flight external request cannot be recalled by deletion or settings change. Ordinary queue enqueue/crash handoff guarantees remain bounded by the existing outbox/scheduler contract.

INT-008 is **IMPLEMENTED, not CLOSED**; T08/T14/T16 remain broader qualification groups. Backlog: **9 OPEN / 35 IMPLEMENTED**. Next: **INT-009 — audience failed manual work and exclusions**.
