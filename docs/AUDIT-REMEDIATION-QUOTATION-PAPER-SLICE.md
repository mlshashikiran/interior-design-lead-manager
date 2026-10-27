# QUO-009 — PDF paper size and fresh defaults

Author / Builder: **Shashi**. Date: **2026-10-08**. Local candidate **1.102.63**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Schemas unchanged: core **1.3.9**, routing **1.0.1**, quotation **2.5.4**, lifecycle receipt **1.1.0**.

## Finding and implementation

The immutable [quotation review](audit-2026-10-06/QUOTATION-REVIEW.md#quo-009--p2-letter-setting-falls-back-to-a4-for-downloaded-pdf) records a `Letter`/`letter` mismatch and an injected A4 CSS override. The related [UI-004 finding](audit-2026-10-06/UI-REVIEW.md#ui-004--p2-medium-fresh-install-quotation-downloads-emit-php-84-diagnostics) records missing-default and CSV escape diagnostics. The [baseline probe](remediation-2026-10-08/quotation-paper-slice/baseline-regression.log) reproduces Letter resolving to A4 and the absent `paper_size` warning.

The bounded Graphify query **“QUO-009 paper settings exporter render_print_document”** located the audit finding, `render_print_document()`, `dompdf_document_html()` and `paper_size()`. Current source confirmed both format mismatches. `IDLM_Quotations::normalize_paper_size()` now owns the canonical presentation enum:

| Input | Resolved setting / print / PDF paper |
| --- | --- |
| A4, case variants | A4 |
| Letter, case variants | Letter |
| A3, case variants | A3 |
| Legal, case variants | Legal |
| Absent, null, non-string, empty or unsupported | A4 |

Surrounding whitespace is removed. The existing A4/Letter option values remain compatible; setup also exposes the exporter's already-supported A3/Legal choices so they survive a settings save. Settings reads normalize without rewriting stored preferences; an authorized save stores the canonical value. Paper remains live presentation configuration, consistently applied when an earlier revision is printed or downloaded. No historical contractual, currency, monetary or classification snapshot is rewritten.

Dompdf retains its 10mm margins but no longer overrides the print renderer's selected `@page` size. `setPaper()` receives the same normalized enum; browser print retains its existing 15mm margins. Different browser/PDF layout engines and margins can still paginate differently. PDF remote fetching/chroot/font behavior and generic failure responses are unchanged. Every quotation CSV `fputcsv()` call supplies an explicit empty escape argument for RFC 4180-compatible output without PHP 8.4 deprecations; the UTF-8 BOM and formula protection remain.

## Executed verification

- **312 fast / 0 failed**, including two new named QUO009 alias/type/default/no-option-write contracts with warnings promoted to exceptions. The earlier hardcoded-A4 assertion now checks the margin-only rule. Fast stderr retains the existing unrelated synthetic missing-phone warning.
- **11 focused actual HTTP / 0 failed**, through the reusable `php tests/integration/quotation-http.php --paper-only` runner on a newly owned disposable WordPress/MySQL database. Four settings-save cases assert stored selection, both workspace renderings and every PDF page dimension. Six missing/empty/null/malformed/lowercase/unsupported option cases assert defaults, clean downloads and no implicit option migration. One case rejects unauthorized actor and invalid nonce without changed settings. Each document case checks unchanged aggregate/history/job state, MIME/filename/PDF signature, parsed CSV header and XLSX archive signature, saved contractual terms, all 60 long item labels and an empty affected-request PHP diagnostic log. Final CLI stderr is empty.
- The accumulated full HTTP/stdio run passed **377 pre-existing cases** plus the new settings denial. Ten new document cases initially stopped at a test's incorrect unquoted CSV-header expectation; PDF dimensions/text had already passed. The test now parses the header correctly. All eleven new cases passed on focused reruns, including the final fresh fixture. **388 distinct cases passed across runs**; this is not a claim that a final all-pass full run was performed. Initial results are retained in `http.log`.
- Actual browser save/reload selects **Letter**. Desktop **1280 × 900** administrator utility and mobile **390 × 844** shortcode settings retain the value; mobile page width is **384**, without overflow. Console warning/error observations are empty. Screenshots and observations are retained with the evidence. Temporary tab and viewport override were cleaned up.
- Real A4 and Letter downloads contain **nine pages** each. Every A4 page measures **595.28 × 841.89 points**; every Letter page measures **612 × 792 points**. A3 and Legal dimensions are also asserted. All eighteen A4/Letter rendered pages were visually checked, including long-label continuation, totals and saved closing payments; no clipping or overlap was observed in this fixture. Representative evidence: [A4](remediation-2026-10-08/quotation-paper-slice/paper-a4.pdf), [Letter](remediation-2026-10-08/quotation-paper-slice/paper-letter.pdf).
- Changed PHP lint and `git diff --check` pass. [Named results, commands, source hashes and cleanup](remediation-2026-10-08/quotation-paper-slice/verification.json).

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, dompdf **3.1.6**, Poppler, ZIP, WP-Cron, no persistent cache. Only newly owned `idlm_test_*` schemas and isolated content were used; provider HTTP/mail were intercepted. The configured site database was untouched. A sandboxed MySQL initialization crashed before creating a schema; a fresh isolated initialization and socket-only server succeeded with approved execution. The initial focused follow-up script's administrator lookup warning was corrected; final reusable-runner stderr and normal affected-request logs are empty. Owned schemas/content were removed and the temporary database server stopped. No commit, ZIP or deployment.

## Qualification and next work

**QUO-009 and the directly coupled UI-004 diagnostic correction are IMPLEMENTED, not CLOSED.** Backlog: **17 OPEN / 27 IMPLEMENTED**, none CLOSED. Full T12/T19/T20, exact artifact/CI, older/minimum runtime matrix, missing extensions/unwritable storage, arbitrary templates/fonts/images, exhaustive resource limits and every browser print engine remain unqualified. The nine-page synthetic fixture is representative multipage evidence, not a production capacity guarantee. Graph refresh is scoped structural extraction and explicit documentation-heading refresh, retaining unrelated graph content; no full semantic/report regeneration claim.

Next: **UI-001 — ordinary quotation browser quantity validation**, characterizing the current numeric slice before deciding whether further source changes are needed.
