# REL-007 — distribution inventory and static diagnostic triage

Author / Builder: Shashi. Date: 2026-10-11. Local candidate **1.102.77**. **IN PROGRESS; not IMPLEMENTED or CLOSED.** Runtime semantics and schemas unchanged.

## Finding and verified scope

The immutable [release audit](audit-2026-10-06/RELEASE.md) reported 107 errors / 1,451 warnings and local package artifacts. Current source contains substantial later remediation work, so those old locations/counts cannot qualify the current ZIP. The bounded knowledge-graph query returned remediation context, not proof of the static alerts; source and packaged bytes were checked directly.

Official [Plugin Check](https://wordpress.org/plugins/plugin-check/) **2.1.0**, downloaded from the official versioned archive, was run in a freshly extracted WordPress **6.6**, PHP **8.4.14**, WP-CLI **2.12.0**, MySQL **8.4.0** fixture. The original current-source baseline ZIP reported **143 errors / 2,033 warnings**. The corrected ZIP reports **138 errors / 2,032 warnings**. Counts are scanner results, not counts of proven vulnerabilities. All default static checks run without additional file/directory/code/category exclusions, warning/error filtering or AI classification. Plugin Check's own default vendor exclusions remain; this is not a vendored dependency security audit or runtime Plugin Check run.

## Completed changes

`.distignore` excludes `.graphifyignore`, `design.md`, the local `quote-builder-redesign.html` prototype and graph caches under `includes/graphify-out/`. Existing root graph exclusion remains. An independent archive inventory policy refuses these known development artifacts and graph directories if they reappear. It does not reject public privacy/support guides or dependency authorship notices. Required inventory now protects bundled autoload/package metadata, renderer/font/SVG/HTML/CSS dependency licenses/authorship and the ICC resource plus its license.

The package shrank from **455 to 450 files**: three local artifacts and two nested graph cache files were removed. All remaining vendor/font bytes match the baseline, and every final extracted file matches current source. No broad vendor pruning, license removal or runtime source deletion was used to lower diagnostics.

Four narrowly documented source-line exceptions cover the policy parser exception (returned as JSON, not HTML), authorized binary PDF output, complete escaped/allowlisted standalone print HTML, and its direct stylesheet link (admin-post page does not run `wp_head`). Original escaping/sanitization remains at the actual data sinks. Other diagnostics were not suppressed to manufacture a clean report.

`tests/release/check-package.php` accepts an artifact/checker ZIP and WP-CLI file. It refuses configured/non-temporary core directories, existing configurations, unsafe ZIP paths/symlinks and existing/non-test databases; extracts the package into an isolated content directory, intercepts outbound HTTP/mail, activates only the checker and uses WP-CLI static mode. It retains strict JSON with file/line/code/type, stderr, versions/checksums and diagnostic counts. WordPress configuration and owned DB/content are cleaned in `finally`. Infrastructure failures fail the runner. **Plugin Check strict-json returned exit 0 despite findings**; the wrapper deliberately returns **1** whenever diagnostics remain.

CI now checks the actual built ZIP with this runner and the pinned, checksum-verified checker. Reports upload on failure; candidate upload requires the checker step to pass. The existing full runtime matrix, revision/checksum/promotion validation and UTC packaging remain. This CI change has been parsed/inspected locally, **not executed remotely**. Current residual findings will fail this gate; no acceptance baseline or ignore-all switch is configured.

## Retained diagnostic review

The [full raw final report](remediation-2026-10-11/package-check-slice/final/plugin-check.json) is retained unchanged. The [triage ledger](remediation-2026-10-11/package-check-slice/diagnostic-triage.json) preserves **all 2,170 diagnostics**, with archive-relative path, exact archived file SHA256, source line/context, disposition and rationale. Checker temporary-path formatting is normalized only by matching a unique actual archive member; raw paths remain in each row.

- **574 JUSTIFICATION_RECORDED**: examined contracts include method-scoped template variables, helper-delegated nonce/capability checks, read-only private download authority, generated typed SQL placeholder counts, prepared SQL stored in a variable, native binary/upload streams, CLI stdio configuration, a no-argument `error_reporting()` mask read, fixed/allowlisted hook calls, temporary restore tables and deliberately retained public guides. These are recorded reasons, not directory approval or complete independent security review.
- **1,595 REVIEW_REQUIRED**: no acceptance or suppression is claimed. The largest outstanding groups are custom SQL construction/direct reads/cache policy, remaining request validation/unslashing and naming diagnostics. Some repeated rules share one query site; SQL interpolation/not-prepared alerts span **354 distinct file/line sites**. Review each constructor, variable identifier/fragment and live caller before deciding whether to fix or justify it.
- **1 RELEASE_CHANNEL_LIMITATION**: directory readme parser trims the changelog section. The full distributed CHANGELOG.md remains, but published directory display needs review.

The [review queue](remediation-2026-10-11/package-check-slice/diagnostic-review-queue.csv) retains every non-justified finding. It is not a source change checklist automatically generated from scanner severity. In particular, generic `prepare`/nonce heuristics cannot prove safe arbitrary SQL or authorization; existing passing tests do not excuse unreviewed source sites. Remaining `MissingUnslash` findings and actual unprefixed CLI globals require concrete follow-through. Privacy/support markdown warnings require release-channel acceptance, not removal of required disclosures.

## Executed verification and limits

[Machine evidence](remediation-2026-10-11/package-check-slice/verification.json) binds the versions, local Git base, artifact SHA and checks.

- **14 release contracts PASS**, including a new development-artifact rejection contract and dependency/notice inventory; existing real ZIP reproducibility and promotion checks pass.
- **363 fast contracts PASS**. Existing fixture warnings (missing `num_rooms`/`phone` properties) occurred in unrelated tests; no claim of warning-free suite output.
- Independent ZIP/source inventory comparison PASS; renderer/vendor/font bytes retained; five local files excluded; guarded builder reports inventory valid and `local_rehearsal_only` / `production_release_approved=false`.
- Seven changed PHP inputs pass `php -l`; workflow YAML parses and diagnostic-failing check precedes success-only candidate upload. Official final checker completes with valid JSON and **fails the diagnostic gate as expected**.

No CRM/native/HTTP/browser/upgrade suite was rerun for comment-only runtime edits and release tooling. The disposable WordPress install for the checker is not an installed-artifact behavioral qualification. Runtime Plugin Check, full vendor scanning/advisories, remote CI/PHP 7.4/8.1/latest WordPress, production/staging, release-channel review and final-artifact tests remain pending. No configured-site DB, provider, mail recipient, commit, push, tag or deployment was touched. Synthetic DB/config/content are removed; downloaded fixture cores/checker and rehearsal ZIPs remain only in temporary storage. Scoped graph structure/document headings are refreshed; no full semantic graph regeneration is claimed.

## Remaining work

Continue **REL-007**, starting with the ledger's SQL `REVIEW_REQUIRED` sites and request-field constructors. Eliminate confirmed defects, retain only source-specific supported justifications and rerun the unfiltered checker after each bounded source batch. Do not advance this row to IMPLEMENTED/CLOSED or release the ZIP while required source review remains. REL-001 stays prerequisite-blocked behind REL-007, M6/M7 and exact clean CI/artifact qualification.

Backlog: **0 OPEN / 2 IN PROGRESS / 42 IMPLEMENTED**.
