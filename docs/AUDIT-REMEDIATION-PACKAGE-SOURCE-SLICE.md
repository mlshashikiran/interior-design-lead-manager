# REL-007 package source review checkpoint

Builder: Shashi. Date: 2026-10-11. Local candidate: **1.102.78**. **IN PROGRESS, not IMPLEMENTED or CLOSED.** Schemas unchanged; public Meta GET validation changes. This continues the [1.102.77 package checkpoint](AUDIT-REMEDIATION-PACKAGE-CHECK-SLICE.md) and the immutable [release audit](audit-2026-10-06/RELEASE.md).

## Confirmed source fixes

Meta verification previously sanitized raw slashed `$_GET` fallback values without unslashing and accepted non-string REST parameters. A valid verify token plus an array challenge could reach output. The callback now requires all three fields to be nonempty strings before matching `subscribe` and the protected verify token. Only raw WordPress fallbacks use `wp_unslash` before sanitization; REST values keep their already-unslashed bytes. A string challenge `0` is valid. Success still emits escaped plain text and exits; denial is 403 without token disclosure or CRM writes. Provider token authentication governs this read-only GET; a WordPress session nonce is not part of the protocol.

Nine actual MCP CLI globals now use `idlm_` names. The WordPress-owned `$wpdb` stays intact. The CLI guard, trusted host loader/log configuration, JSON stdout, parser, tool/PII policy and live grant refresh are unchanged.

## Statement-specific SQL review

The [source review](remediation-2026-10-11/package-source-review-slice/source-review.json) binds **37 exact SQL statement locations** to archived file hashes and source lines. It traces quotation lead queries through the current lead-access scope, daily task queue view/due/from/where/cursor constructors, unread notification predicates and CASE values, private-file ownership locking, and recovery generation/session-lock queries.

Current-user scope values, search/date/cursor values, IDs and limits use matching placeholders. Table names come from WordPress prefixes; projection/order/alias/branch fragments are fixed internal choices. Notification activity constants and read IDs are individually prepared. Recovery `query($sql)` executes a string prepared immediately before it. Reads that determine current assignment, authority, unread state, generation or connection locks deliberately remain live. This review records local reasons for particular diagnostics; it does not accept every query in these modules, prove broader cache invalidation, or suppress the checker.

The new ledger records **97 additional source-specific justifications** (93 SQL diagnostics at those locations and four provider GET nonce heuristics). Existing accepted records are carried forward only when path, line, column, code and source line match; changed MCP transport statements were reviewed against the current source. IDs identify this candidate and link previous records where matched.

## Exact ZIP and unfiltered checker

The 450-file local rehearsal ZIP SHA256 is `5fb3f6242692460d3a7ef2c530354c2823409710b751fde1fa0652cdad721ad3`. Inventory matches 1.102.77. Only main version metadata, readme stable tag, changelog, Meta handler and MCP entry bytes changed; all other runtime, dependency, font and notice bytes remain identical. Every archived file matches current source. The builder still reports dirty source, absent matching CI and `local_rehearsal_only`; no release approval is claimed.

Official Plugin Check **2.1.0**, pinned official ZIP, runs all default static checks in disposable WordPress **6.6**, PHP **8.4.14**, WP-CLI **2.12.0**, MySQL **8.4.0**. [Raw report](remediation-2026-10-11/package-source-review-slice/final/plugin-check.json) and [summary](remediation-2026-10-11/package-source-review-slice/final/plugin-check-summary.json) retain **138 errors / 2,018 warnings**, **2,156 total**. Fourteen warnings disappeared: nine unprefixed CLI globals, three missing-unslash alerts and two fewer nonce heuristics after consolidating the fallback path. No diagnostic filter, broad suppression or vendor pruning was added. The checker returns zero with strict JSON; the wrapper correctly returns **1**, so the package gate still fails.

The complete [artifact-bound ledger](remediation-2026-10-11/package-source-review-slice/diagnostic-triage.json) contains **671 JUSTIFICATION_RECORDED / 1,484 REVIEW_REQUIRED / 1 RELEASE_CHANNEL_LIMITATION**. The [review queue](remediation-2026-10-11/package-source-review-slice/diagnostic-review-queue.csv) retains every non-justified diagnostic. Error counts are scanner results, not counts of confirmed vulnerabilities. Local justifications do not override the unfiltered CI gate.

## Executed verification and limits

- **365 fast contracts PASS**, including missing/array/object/integer/bool REST shapes, raw array fallback denial, and isolated actual callback success for raw and REST values with literal backslash/apostrophe tokens and zero challenge. Existing unrelated fixture warnings remain (six missing `num_rooms`, one missing `phone`); this suite is not claimed warning-free.
- **7 focused actual Meta HTTP contracts PASS**, including exact plain-text/content-type/zero responses, malformed and empty fields, wrong token, disabled integration and no CRM/job changes.
- **430 full actual HTTP/stdio contracts PASS**, including production MCP entry parsing, tool/PII grants and grant revocation between calls. Synthetic users/rows only; loopback transport with provider/mail guards. The suite's affected-request warning check passes.
- **14 release contracts PASS**; six changed PHP inputs lint PASS; independent 450-file archive/source comparison PASS. Exact dirty/CI-negative promotion behavior is retained from the prior checkpoint.

[Machine evidence](remediation-2026-10-11/package-source-review-slice/verification.json) records source/artifact hashes, counts and scope. Tests execute source in disposable WordPress, not the installed candidate ZIP. Static checking the extracted ZIP is not full artifact behavioral qualification. PHP 7.4/8.1, latest WordPress, absent-extension hosts, native full suite, browser, upgrade/staging, runtime Plugin Check, full dependency advisories and remote exact-revision CI were not rerun in this slice. No configured site, provider recipient, mail recipient, commit, push, tag or deployment was touched. Owned DB/content/config are removed and the temporary MySQL server/data are cleaned. Scoped graph structure/document headings are refreshed; full semantic graph regeneration is not claimed.

## Remaining work

Continue REL-007 with the remaining query constructors and request-validation/unslashing sites in the current review queue. Preserve statements requiring live authority; fix confirmed defects and justify only traced locations. The diagnostic gate remains blocked. REL-001 stays prerequisite-blocked behind REL-007, M6/M7 and clean exact CI/artifact qualification.

Backlog: **0 OPEN / 2 IN PROGRESS / 42 IMPLEMENTED**.
