# QUO-006 — Historical quotation currency and contractual document terms

Author / Builder: **Shashi**. Date: **2026-10-07**. Local candidate **1.102.62**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Quotation schema **2.5.4** adds one nullable revision column. Core **1.3.9**, routing **1.0.1** and lifecycle receipt **1.1.0** remain unchanged.

## Finding and policy

The [October 6 quotation review](audit-2026-10-06/QUOTATION-REVIEW.md) identified historical print/PDF dependence on current currency, payment settings and templates. The bounded Graphify query **“QUO-006 historical currency payment terms template revision snapshot renderer exporter”** located the renderer, schema and review. Current source confirmed that money formatting discarded explicit currency and document rendering consumed live contractual settings. An [offline baseline probe](remediation-2026-10-07/quotation-document-slice/baseline-regression.log) reproduces INR 118 rendered as $118 after global USD selection.

A new quotation uses the current currency. Every subsequent revision and working copy retains the quotation's original revision currency, regardless of posted currency or changed settings. No exchange conversion occurs. List, dynamic list, detail, historical preview, editor calculation and print/PDF/CSV/XLSX use saved currency. Catalogue prices continue to use the current catalogue currency.

**Each new revision captures current contractual settings**. Since 1.102.90, the editor omits the document-content review panel at Shashi’s request; manage the defaults in Quotation Setup. Earlier revisions retain their own snapshots. Save is authoritative: if settings change after the editor loads, capture uses settings current at save time. It does not implicitly adopt a different currency. A working copy captures current terms, and its final save captures them again.

| Snapshot | Contents |
| --- | --- |
| Cover and closing | Enabled flags and sanitized HTML, including contractual text and payment shortcode placement |
| Footer | Sanitized footer terms |
| Payments | Ordered milestone names, percentages and allocated monetary amounts |
| Currency | Existing `currency_code`, retained across revisions |

Company identity/logo/accent and paper layout remain live. HTML image references are captured, but external asset bytes are not archived; changing remote assets can still alter presentation. The existing milestone table is unchanged and is not used to invent missing historical terms.

## Persistence, validation and recovery

`document_terms LONGTEXT DEFAULT NULL` stores a versioned JSON document beside revision monetary snapshots. Each cover/closing/footer field is capped at **32,000 UTF-8 bytes**; the complete JSON is capped at **262,144 bytes**. There are at most **50** payment milestones, names at most **255 bytes**, each percentage within 0–100.00 and included percentages totaling at most 100.00. Malformed or excessive configured content refuses save/autosave before partial writes. Existing authorization, transaction, numbering, idempotency, engine and commercial-history guards remain.

Milestone amounts use integer cents with largest-remainder allocation: round the agreed total payment percentage, allocate floor amounts, then distribute remaining cents by descending remainder with original order breaking ties. Thus 100% milestones exactly sum to the saved total. For 1.00 with 33.33/33.33/33.34%, amounts are .33/.33/.34; for .01 with two 50% milestones they are .01/.00. No historical totals are recalculated.

The additive installer uses existing checked DDL/physical verification, recovery fencing and publish-last schema markers. Failed DDL does not publish 2.5.4; retry adds the field and preserves old revision values. Missing old terms remain **NULL/unknown**. Print/PDF show a clear historical-terms notice and omit unverified cover/closing/footer/payments. Corrupt nonempty snapshots show an invalid-snapshot notice and suppress contractual substitution. CSV/XLSX include snapshot status and known contractual fields; they never fill unknown terms from today's settings. Saved monetary/classification fields are unchanged; ordinary revision supersession still updates documented lifecycle fields.

Backups retain current snapshots exactly and reject malformed nonempty documents during preflight. Only complete matching pre-2.5.4 inventories normalize the new field to NULL. Supported quotation versions 2.5.0–2.5.3 compose with existing classification and older core normalizers; incomplete or mismatched inventories remain rejected. No restore guesses earlier contractual content.

## Executed verification

- **310 fast / 0 failed**: five new document contracts cover capture/read invariance, malformed/oversized terms, explicit currency, exact older inventories and payment allocation vectors.
- **231 native / 0 failed**: four new cases cover saved print/export after settings changes, exact additive 2.5.3 upgrade, DDL failure/retry and current/older backup roundtrips with corruption refusal. Focused document run **5/0** and true 2.5.0 backup run **2/0** include the fresh-schema prerequisite.
- **377 actual HTTP/stdio / 0 failed**: six new cases cover owner/admin historical print/PDF/CSV/XLSX, repeated reads without aggregate writes, detail grand-total currency, new revisions/autosave retaining currency, newly created current-currency quotations, malformed-term denials, aggregate rollback/retry/idempotency and legacy/corrupt notices. Focused HTTP run **12/0** includes prerequisites and warning checks.
- Actual browser save R02 retains INR while live settings use USD; R01 remains available with original contractual cover/closing/footer, two ₹59 payments and ₹118 total. Desktop admin rendering and shortcode mobile **390 × 844** show reviewed new terms and INR calculations; mobile document width **384**, no page overflow. Console error/warning observations are empty. [Observations](remediation-2026-10-07/quotation-document-slice/browser-checks.json), [saved revision](remediation-2026-10-07/quotation-document-slice/browser-saved.png), [mobile](remediation-2026-10-07/quotation-document-slice/browser-mobile.png).
- Real [PDF](remediation-2026-10-07/quotation-document-slice/historical-document.pdf), [CSV](remediation-2026-10-07/quotation-document-slice/historical-document.csv) and [XLSX](remediation-2026-10-07/quotation-document-slice/historical-document.xlsx) downloaded through production handlers. PDF text extracted with Poppler; all four A4 pages visually inspected for original terms, saved INR, payment totals and clipping. XLSX inspected as OOXML, including summary text, numeric payments and currency styles; CSV parsed into summary fields. Existing formula protection remains.
- **21 changed PHP files** pass `php -l`; quotation JavaScript passes `node --check`; `git diff --check` passes. [Named checks and hashes](remediation-2026-10-07/quotation-document-slice/verification.json).

Browser verification caught the detail grand-total call still omitting its currency argument; it was corrected and added to actual HTTP assertions. The fixture now localizes the actual current currency, verifying that saved editor currency takes precedence. Earlier test corrections fixed incomplete historical customer/section fixtures, mislabeled old backup inventories, the XLSX summary worksheet selection, and comparisons of expected supersession timestamps. No production backfill was loosened. Expected fault diagnostics are strictly matched separately. Fast stderr retains the existing missing-phone test-double warning; native stderr retains two existing synthetic review-erasure diagnostics. Final HTTP stderr and normal affected-request diagnostics are empty.

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, no persistent cache; existing dompdf **3.1.6**, Poppler and ZIP support. Fresh owned `idlm_test_*` schemas and isolated fixtures only; HTTP/mail intercepted. Configured-site database untouched. Owned schemas/content removed and socket-only server shut down after checks. No commit, package or deployment. [Fast](remediation-2026-10-07/quotation-document-slice/fast.log), [native](remediation-2026-10-07/quotation-document-slice/native-full.log), [final HTTP](remediation-2026-10-07/quotation-document-slice/http-final.log), [cleanup](remediation-2026-10-07/quotation-document-slice/cleanup.json).

## Qualification and next work

QUO-006 is **IMPLEMENTED, not CLOSED**. **19 OPEN / 25 IMPLEMENTED**, none CLOSED. Scoped incremental Graphify source extraction and explicit document-heading refresh retain unrelated graph content; no semantic/full report regeneration claim. Exact CI/artifact, minimum/older PHP/MySQL/MariaDB matrix, persistent cache/drop-ins, configured-site historical data, full production transport, desktop Excel/locale and exhaustive PDF font/layout/resource coverage remain unqualified. Global M0 and full T10/T12/T16/T17 groups remain incomplete.

The PDF checks deliberately use a configured **A4** setting. A fresh missing `paper_size` warning and Letter/default/layout/download-diagnostic behavior remain **QUO-009**; this slice does not fix or qualify them. Historical external image bytes and live branding/layout are not immutable. Invalid or unavailable legacy contractual terms require independent evidence; they cannot be reconstructed from today's templates.

Next: **QUO-009 — PDF paper-size/defaults, layout and download diagnostics**.
