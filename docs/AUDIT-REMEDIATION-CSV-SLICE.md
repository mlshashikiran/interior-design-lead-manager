# Fourth remediation slice: lead CSV formula protection

Builder: Shashi. Local candidate `1.102.46`, based on the preserved dirty working tree at `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. CORE-003 is **IMPLEMENTED**, not CLOSED. Earlier slice reports/evidence and the original audit remain immutable; no deployment or schema change.

## Baseline and implementation

The bounded Graphify query for lead CSV/formula/export authorization pointed to CORE-003 and its admin AJAX contract. Source inspection confirmed that `IDLM_Admin_Ajax::ajax_export_leads()` emitted raw database text, whereas quotation exports already used `IDLM_Quotation_Exporter::csv_text()`. The pre-fix disposable HTTP run reproduced unprotected formula/control/Unicode cells and a leading-plus phone; 217 cases passed and two new protection assertions failed. Existing export authority and filters succeeded. The preceding unchanged candidate's recorded 238 fast / 24 native / 207 HTTP-and-stdio baseline is retained with its source hashes, alongside a new 660-file preservation manifest and binary dirty patch under `/private/tmp/idlm-csv-slice-baseline/`.

Lead export now reuses that existing public text normalizer. An apostrophe is placed before dangerous `=`, `+`, `-`, `@`, tab, CR or LF prefixes, including preceding ASCII controls/space, Unicode separators/format characters and full-width formula characters. A byte-pattern fallback protects ASCII formulas in invalid UTF-8 inputs. Source text bytes are retained. Ordinary Unicode, embedded newlines, apostrophes, separators, quotes and backslashes survive CSV parsing. Already marked text remains unchanged.

The two schema-controlled numeric columns, ID and decimal quote value, remain numeric strings with their existing precision. Phone, budget and room count remain text even when numeric-looking; leading-plus phone values therefore acquire the protective marker. The existing 40-column header/order, filters, descending row order, nonce/capability, action/callback, filename and MIME contracts remain. Explicit delimiter/enclosure/empty escape arguments keep quotes/backslashes within cells and avoid the affected handler's PHP 8.4 implicit-escape deprecation. Stored lead values are unchanged.

The existing quotation `csv_text()` callback and typed numeric export paths remain intact; its stronger prefix handling is shared with quotation CSV/XLSX text cells. Catalogue/provider feeds use their own paths and are outside this finding's scope. No unrelated coordinator extraction was needed; the existing class-size guard still passes.

## Authorization and executed evidence

Lead CSV retains its administrator `manage_options` requirement and `idlm_admin_nonce`. Owners, non-owners, read-all users and CRM managers without that export grant are denied; an explicitly granted manager can export and loses access after live capability revocation. Anonymous, missing/invalid/cross-user nonce requests are denied. Existing WordPress AJAX capability denials may return HTTP 200; assertions also require no CSV attachment or lead content, and unchanged business rows, working copies, jobs and outbound attempts. Administrator exports continue across ownership reassignment.

[Machine verification](remediation-2026-10-06/csv-slice/verification.json), [preservation](remediation-2026-10-06/csv-slice/preservation.json) and [fixture cleanup](remediation-2026-10-06/csv-slice/cleanup.json) record the source hashes, named cases, environment and limits.

- Fast suite: **241 PASS / 0 FAIL**, including three new shared-normalization/byte-preservation/parsed-cell cases and existing typed quotation exports.
- Native suite: **24 PASS / 0 FAIL** against disposable WordPress/MySQL fixtures.
- Actual HTTP-and-stdio runner: **219 PASS / 0 FAIL**; prior 207 cases plus 12 lead CSV cases. Downloads independently parse all 40 columns over 21 dangerous and nine ordinary synthetic payloads in 30 text fields. Numeric precision, booleans, Unicode, quote/backslash/separator containment, selected-ID/search/empty filters, repeated output, phone preservation, reassignment and live grants are checked.
- The owned HTTP fixture elevates warnings/deprecations for this handler to failures; affected downloads and prior requests remain warning-free. Changed PHP lint and whitespace checks are recorded separately.

PHP 8.4.14, WordPress 7.1.2, MySQL 8.4.0/InnoDB, WP-Cron, no persistent object cache. New `idlm_test_*` schemas and a new isolated MySQL datadir/socket only; copied WordPress core excludes configured content/configuration, and MU-plugin guards intercept outbound HTTP/mail before installation. No configured site database was used.

## Limits and next slice

CI/support matrix (including PHP 7.4/8.1 and WordPress 5.8), exact release artifact, persistent-cache/multi-node and spreadsheet application/locale/import/re-save behavior are **NOT RUN**. No UI surface changed, so browser/layout checks were not repeated. Parsed download checks prove the emitted values and field boundaries, not every spreadsheet's interpretation. The apostrophe follows the existing text-export contract; spreadsheet re-save may remove protective markers. [OWASP describes these application-dependent limits and control/full-width prefixes](https://community.owasp.org/attacks/CSV_Injection). Consumers must retain text typing when importing/re-saving these cells. Unbounded lead-export reads (CORE-012) and public file links (CORE-002) remain separate open findings. Global M0, complete T11/T12, and release qualification are not complete.

Next M1 slice: CORE-002 confidential-file storage, authorized retrieval and legacy URL revocation. The user requires file handling/storage inside WordPress. Production server type is still unconfirmed: WordPress authorization does not protect a static uploads URL, and an Apache access file alone is not portable to Nginx. The next slice must establish a fail-closed WordPress-contained storage policy and rehearse legacy preservation/URL revocation before migration.
