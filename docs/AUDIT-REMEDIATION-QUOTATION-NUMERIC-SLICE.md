# QUO-008 — Finite quotation numbers and consistent quantity pricing

Author / Builder: **Shashi**. Date: **2026-10-07**. Local candidate **1.102.61**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Core **1.3.9**, routing **1.0.1**, quotation **2.5.3** and lifecycle receipt **1.1.0** unchanged. No migration or historical recalculation.

## Finding and numeric policy

The October 6 quotation review reproduced nonfinite exponent input and `.01 × .01` dimensions yielding stored quantity `.01` with subtotal zero. A new offline probe of the pre-change calculator reproduced both defects ([baseline](remediation-2026-10-07/quotation-numeric-slice/baseline-regression.log)). The bounded Graphify query **“QUO-008 numeric quotation calculator quantity totals browser”** located the calculator, browser editor and audit finding; current source confirmed the normalization order and missing bounds.

Requests accept nonnegative ordinary decimal text, with optional leading plus/whitespace and empty values interpreted as zero. Reject negative, exponent, malformed, array/boolean and nonfinite values. Decimal text rounds half up to two places using integer hundredths before multiplication. Token length is capped at 128 characters. Internal finite floats remain supported for normalized persisted data; `clean_decimal()` remains a finite compatibility helper, while request paths use the strict checked parser.

| Domain | Supported normalized range |
| --- | --- |
| Width, height, manual/derived quantity | 0–999,999,999.99; billable quantity minimum 0.01 |
| Rate, line amount, subtotal, adjustments, pre-round/final total | 0–9,999,999,999.99 |
| Wooden discount, soft-close, milestone percentages | 0–100.00; included milestones total at most 100.00 |
| Quotation rows / sections | 200 / 50, counting submitted blank/header/optional rows |
| Payment milestones | 50 |

The money ceiling fits the narrower lead `quote_value DECIMAL(12,2)` as well as quotation `DECIMAL(14,2)` columns; quantity fits existing `DECIMAL(12,3)`. Intermediate products are bounded before multiplication, within exact PHP 64-bit/JavaScript integer range. Full totals must fit before additional discount and after GST and whole-unit rounding; discount cannot hide an overflowing aggregate. Maximum representable rate is allowed on excluded optional rows, but each optional row still requires a valid representable product.

## Calculation and mutation contract

Normalize width/height first, derive area and round it to two decimals, then apply minimum billable quantity **before** pricing. Normalize manual quantity and rate first, round each line to cents, then sum rounded lines. For `.01 × .01` at rate `100`, quantity is `.01` and subtotal is `1.00`. For quantity `.15` at rate `.10`, subtotal is `.02`. All adjustments use integer cents; wooden discounts and soft-close additions round half up. Additional fixed discount still clamps to the available total. Header/optional exclusion, wood classifications, zero design fee/IGST and fixed 18% GST remain. CGST receives the rounded-up odd tax cent and SGST the remainder. Final grand total retains whole-unit half-up rounding, checked against the lead projection ceiling.

Calculator parsers/totals now return checked `WP_Error` on invalid numeric/count/shape input. Create/revise refuses before number allocation or aggregate writes, releases any acquired new-lead lock, and redirects with an error. Autosave rolls back and returns JSON **422**, preserving the existing working copy. Existing nonce, current authority, object binding, engine checks, historical read-only policy and transaction fencing remain.

Catalogue edits validate rates before item/term writes. CSV import skips invalid numeric rows under its existing partial-valid import policy, counting them as errors before creating terms/items. Settings reject invalid percentages/milestone structure before updating options; included milestones sum in integer hundredths. Browser inputs share the two-decimal steps and representable bounds; the calculator uses integer units and refuses autosave on invalid numeric data. Production admin/shortcode localization supplies the same server constants.

Existing revision rows, currencies, classifications and totals are never rewritten. Historical out-of-range revisions remain readable; submitting a new revision or working copy must satisfy the current bounds. Historical document settings remain QUO-006; UI-001 is not separately marked implemented (quantity already had a `.01` step before this slice).

## Executed verification

- **305 fast / 0 failed**, including six new QUO-008 numeric cases across all fields, strict tokens, decimal half-up boundaries, tiny areas, line sums, optional/wood semantics, discount/GST/rounding, projection overflow and 200/50 shape/count limits.
- **227 native / 0 failed**, including three new QUO-008 cases. Independent SQL DECIMAL rounding matches calculator lines/adjustments/GST. Real persisted quantity × rate reproduces line prices; legal large totals fit the lead projection, old revisions remain exact, and strict/permissive SQL modes cannot admit invalid normalized input. **4 focused native / 0 failed** includes the fresh-schema prerequisite.
- **371 actual HTTP/stdio / 0 failed**, including twenty-two new QUO-008 cases. Owner/admin create/revise/autosave reject all four numeric fields, adjustments, stored bad soft-close, malformed/count/sum/product/grand overflow with aggregate/number/history/working-copy snapshots unchanged. Supported tiny/half-cent/multiline/large/200-row saves and exact revision CSV/repeated reads pass. Additional settings/catalogue edit requests reject invalid values before writes; valid fractional milestones total exactly 100%.
- **28 JavaScript golden vectors PASS** against the production helper source; exponent/range/minimum/product/percentage and half-cent boundaries. `node --check` passes. **13 changed PHP files** pass `php -l`; `git diff --check` passes.
- Actual disposable browser: admin tiny-area subtotal `1.00`, valid quantity `1.05`, exponent/overflow refusals, half-cent subtotal `.02`, autosave and R03 save with R01/R02 retained. Mobile shortcode **390 × 844** reproduces `.02`, valid `.01` steps and negative-input refusal; document width 384, no page overflow. Corrected fixture console has no errors. [Browser observations](remediation-2026-10-07/quotation-numeric-slice/browser-checks.json), [saved revision](remediation-2026-10-07/quotation-numeric-slice/browser-saved.png), [mobile](remediation-2026-10-07/quotation-numeric-slice/browser-mobile.png).

The browser first detected missing localized numeric limits in the manually rendered HTTP fixture; production localization already contained them. Correcting the fixture fixed initialization, with subsequent calculation/save checks passing. The Playwright CLI registry lookup was unavailable; browser checks used the available Codex browser controls. No new dependencies were installed. Settings assertions compare independent SQL values and flush the parent worker cache before restoring options, because HTTP writes occur in another process. Two stale-cache assertions found during accumulated verification were corrected; these were fixture read/restoration errors. Existing minimum/delegation fixtures now submit valid values; invalid cases explicitly assert errors. Fast stderr retains the pre-existing missing-phone test-double warning; native stderr retains two existing synthetic review-erasure diagnostics. Normal affected HTTP diagnostics and final HTTP stderr are empty.

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, no persistent cache. Fresh owned `idlm_test_*` schemas and isolated fixtures only; outbound HTTP/mail intercepted. Runners remove owned databases/content and the socket-only server is shut down after verification. No configured-site database mutation, commit, package or deployment. [Named verification](remediation-2026-10-07/quotation-numeric-slice/verification.json), [fast](remediation-2026-10-07/quotation-numeric-slice/fast.log), [native](remediation-2026-10-07/quotation-numeric-slice/native-full.log), [HTTP](remediation-2026-10-07/quotation-numeric-slice/http-final.log), [cleanup](remediation-2026-10-07/quotation-numeric-slice/cleanup.json).

## Qualification and next work

Scoped incremental Graphify refresh re-extracts changed source and explicit document headings, retaining unrelated graph content without inferred semantic relationships or full report regeneration. QUO-008 is **IMPLEMENTED, not CLOSED**. **20 OPEN / 24 IMPLEMENTED**, none CLOSED. Exact CI/artifact, minimum/runtime and older MySQL/MariaDB matrix, cache/drop-in environments, configured-site numeric history and full production-scale editor/transport qualification remain open. The 200-row HTTP boundary fixture uses minimal row fields below PHP `max_input_vars`; a full 200-row editor request requires host limits sized for its complete form. CSV invalid-rate skip logic is source/strict-parser checked; no actual multipart invalid-rate import claim. Browser golden helpers plus representative real editor flows are not exhaustive browser locale/device coverage. Whole T10/T11/T12/T18 groups and global M0 remain incomplete.

Next: **QUO-006 — preserve historical currency and contractual payment/template terms**.
