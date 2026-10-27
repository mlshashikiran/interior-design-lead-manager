# REL-007 bootstrap request-metadata checkpoint

Builder: Shashi. Date: 2026-10-18. Local candidate **1.102.82**. **IN PROGRESS, not IMPLEMENTED or CLOSED.** Schemas unchanged. Continues the [quotation request-validation checkpoint](AUDIT-REMEDIATION-PACKAGE-REQUEST-INPUT-SLICE.md).

## Reproduced boundary and focused repair

Bootstrap read `rest_route` without validating its shape. Ordinary array-valued query parameters reached string casts in `IDLM_Recovery_Guard::begin_boot()` and the pending-migration branch of `idlm_boot_plugin()`. Actual HTTP baseline checks reproduced PHP diagnostics on healthy bootstrap, paused CRM actions and administrator repair notices: **two PASS / three FAIL**. Existing REST 503 behavior and the request-lifetime recovery fence passed before the repair.

Both locations now require string action/URI/route values, unslash once and sanitize before classification. Non-string metadata becomes empty. The migration gate checks URI and query route independently rather than concatenating them. Current CRM action checks, REST namespace checks, cron handling, administrator notice authorization, recovery exceptions and worker/generation/lock algorithms remain. These fields classify the request; they do not authorize a mutation or replace downstream handler authentication.

The bounded graph query **“recovery guard schema boot HTTP request classification”**, budget 900 tokens, located the recovery guard and its bootstrap/worker methods. Structural graph edges did not establish input semantics; current bootstrap, recovery, fixture and schema-test source supplied that evidence. This small isolated change adds no architectural edges; no full graph/semantic/HTML regeneration is claimed.

## Executed verification

- **Five focused actual HTTP cases PASS**: ordinary array metadata produces no plugin diagnostics or CRM/job changes; a pending CRM action with malformed route metadata returns generic 503 without mutation/disclosure; administrators retain actionable repair notices; both REST query namespaces still pause; a held CRM action or `idlm/v1` query-route request blocks restore until request shutdown releases its worker fence.
- **443 accumulated HTTP/stdio cases PASS**, including the five new cases plus existing authorization, migration, restore, persistence, numeric and export checks.
- **Two focused native cases PASS**: fresh schema and existing independent-connection restore/reset/stale-generation fencing.
- **382 fast contracts PASS**, including the **14 release contracts**; no duplicate standalone release rerun. The existing missing-`phone` fixture warning remains. Six changed PHP files pass syntax checks; whitespace checks pass.
- Environment: WordPress **6.6**, PHP **8.4.14**, MySQL **8.4.0**, WP-Cron, no persistent cache. Owned synthetic databases/content only, with outbound HTTP/mail intercepted.

[Machine verification](remediation-2026-10-18/package-bootstrap-request-slice/verification.json) records commands, results and limits. No full native suite, browser/PDF, PHP 7.4/8.1/latest-WordPress matrix, live provider acceptance, matching remote CI or installed-artifact behavioral qualification was run. The recovery guard retains its existing namespace scope; this slice does not claim wider legacy-namespace recovery-fence qualification. Real query arrays were exercised; server-variable array injection was not separately simulated.

## Package and static gate

The **453-file** local rehearsal ZIP SHA256 is `91fdacc6a3cb5ca91a169cf81b26b6bb6753a5b6fc4aba5b377dcb49d525bedf`. Every archived byte matches source; inventory matches 1.102.81. Only the recovery guard, bootstrap/version header, readme stable tag and changelog changed in the archive. The starting runtime matched the previously checked 1.102.81 artifact byte for byte, so its report is the valid diagnostic baseline. Dirty Git and absent matching CI retain `local_rehearsal_only` and `production_release_approved=false`.

Official Plugin Check **2.1.0**, the pinned official ZIP, runs all default static checks without exclusions. Final output is **136 errors / 2,042 warnings**, **2,178 total**; wrapper exit **1**. Six missing-unslash and six input-sanitization alerts disappeared, while explicit shape checks produce four additional nonce heuristics: net **eight fewer warnings**. [Raw diagnostics](remediation-2026-10-18/package-bootstrap-request-slice/final/plugin-check.json) remain complete.

The [source review](remediation-2026-10-18/package-bootstrap-request-slice/source-review.json) traces six exact metadata inputs and records **12 specific nonce-alert justifications at four source locations**. These reads only select the recovery lease lifetime or generic migration-failure response, including public requests that cannot carry WordPress session nonces. The [complete ledger](remediation-2026-10-18/package-bootstrap-request-slice/diagnostic-triage.json) records **656 JUSTIFICATION_RECORDED / 1,521 REVIEW_REQUIRED / one RELEASE_CHANNEL_LIMITATION**. Older dispositions carry only on exact path/line/column/code/source-line matches; shifted locations return to review. No checker suppression or diagnostic filtering was added. The unfiltered static gate is **FAIL**.

## Preservation and next work

All pre-existing files outside this slice remain hash-identical to the starting tree. The accumulated runner appended expected injected-fault messages to an older evidence log; the exact saved original was restored, and only this run's appended messages are retained in the new evidence directory. Disposable DB/content/configuration are removed and the task-owned MySQL server/data are cleaned. No configured site, commit, push, tag, live provider send, email or deployment was changed. [Preservation](remediation-2026-10-18/package-bootstrap-request-slice/preservation.json) and [cleanup](remediation-2026-10-18/package-bootstrap-request-slice/cleanup.json) are separately recorded.

Continue REL-007 using the [current review queue](remediation-2026-10-18/package-bootstrap-request-slice/diagnostic-review-queue.csv). Remaining request/nonce, SQL-constructor and distribution findings require focused source review and fixes. REL-001 stays dependent on REL-007, M6/M7 and exact release qualification. Backlog: **42 IMPLEMENTED / two IN PROGRESS / zero CLOSED**.
