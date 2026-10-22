# Public-release checklist and verdict

Author: Shashi. Reviewed 2026-10-06. **NOT READY.** This applies to the exact dirty working-tree 1.102.42 candidate, not merely its base commit or any previous published release. No production behavior was changed, released or promoted.

## Candidate identity and package proof

Base revision: `ff148ba62ec710a622d9b1af7e8f511b7e0b7385` (base release 1.102.38). Current main header, version constant, stable tag and current changelog align at 1.102.42. Existing dirty/untracked changes include the quotation exporter and 11 MB PDF vendor tree. [Initial status](evidence/initial-working-tree.txt), [original patch](evidence/reviewed-working-tree.patch), [513-file source manifest](evidence/source-manifest.json) preserve the review identity. [Preservation check](evidence/source-preservation.json) reports zero changed original files. Added audit docs/tests are excluded from the runtime ZIP.

Reviewed ZIP: [interior-design-lead-manager-1.102.42.zip](evidence/candidate/interior-design-lead-manager-1.102.42.zip), 5,102,173 bytes, 431 files, 13,650,928 uncompressed bytes. SHA256: `eb866c3d16fd34aaf568212b01655c3661810b82646bb62b2df3490f0e700760`. This is a **local rehearsal only**, not an approved distributable. The installed artifact has 431 matching files, no modifications/missing files and no extras, independently checked after temporary test harness removal. [Manifest/build evidence](evidence/release-build.json), [installed identity](evidence/installed-artifact-identity.json).

An independent rebuild has identical file contents/names but different ZIP bytes: root empty-directory timestamp varies. [Reproducibility result](evidence/reproducibility.json), REL-006. The candidate promotion verifier correctly rejects this candidate for dirty Git, absent matching CI attestation and release blockers. That expected rejection is proof the negative gate works, not proof the candidate is approvable. [Verifier output](evidence/candidate-verification.txt).

## Checklist

|Gate|Status|Evidence / remaining requirement|
|---|---|---|
|Exact version/source/ZIP identity|PASS within dirty reviewed tree|Aligned 1.102.42; hashed candidate and preserved original source. Commit identity still required.|
|First-party module/surface/persistence inventory|PASS as source review|53 includes, 24 templates, 22 assets, 21 custom tables; contracts/reviewer maps. Registration callability is not permission proof.|
|Fast suite|PASS|174/174, PHP 8.4.14; mixed doubles/source assertions.|
|Native suite / installed artifact|PASS for named cases|17/17 source + 17/17 artifact on WP 7.1.2/MySQL 8.4 InnoDB/PHP 8.4.14.|
|Runtime/vendor PHP syntax|PASS|301 files on PHP 8.4.14, [lint](evidence/php-lint.json).|
|Material security/data-integrity findings resolved|FAIL|Authorization/PII, SQL scope, destructive restore, migration/history and lifecycle atomicity findings remain.|
|Audit negative/failure invariants|FAIL|48 targeted assertions: 19 PASS, 29 FAIL; see [matrix](scenario-matrix.csv).|
|Complete actual UI / accessibility / clean exports|FAIL / NOT RUN|Confirmed quantity validation, mobile task layout, labels, PHP warnings; bounded browser happy path works, remaining matrix open.|
|Byte-reproducible clean packaging|FAIL|Directory timestamp changes ZIP; source dirty.|
|Package exclusions and unintended executables|FAIL pending triage|.graphifyignore and design.md included; many third-party dev/doc files included. No tests/docs/.hosting/build/customer DB/config included by manifest. MCP stdio entry is intentional but requires published host-access/deployment contract.|
|Final-artifact Plugin Check|FAIL|107 ERROR + 1451 WARNING across 63 files. Hidden file and unexpected markdown confirmed. Remaining diagnostics must be fixed or justified with source-specific review, then rerun.|
|Dependency advisories|PARTIAL PASS, scoped|Known July 2026 Dompdf issues patched by installed 3.1.6; no universal advisory-clean/provenance guarantee.|
|Licenses/notices|FAIL|Dompdf/dependency license files present; Inter bundle lacks full OFL notice. Minified asset source provenance/license mapping still needs final distribution review.|
|Fresh-install role/object privacy defaults|FAIL|Broad contributor/editor/sales read access; public uploads/revocation and task/quote ownership inconsistencies.|
|Service/privacy/consent/retention disclosures|FAIL|Readme mentions providers but does not explain data transfers, service/privacy links, secret lifecycle or erasure/uninstall behavior.|
|Default attribution directory requirement|FAIL for WordPress.org|Fresh anonymous login and workspace display credit without explicit opt-in.|
|Declared runtime compatibility|NOT RUN / BLOCKED|WP 5.8 claim not tested; header PHP 7.4 but readme missing Requires PHP; CI does not cover WP 5.8. Missing-extension behavior unverified.|
|Full configured CI matrix matching candidate|BLOCKED|No completed attestable PHP 7.4/8.1/8.4 × WP 6.6/latest workflow on exact committed candidate. Local runs cannot provide that evidence.|
|Historical upgrade/restore/migration interruption|NOT RUN, with known FAIL contracts|Legacy quote state changes/history rewrite and completion markers unsafe; native preservation/rollback fixtures required.|
|Deactivate/reactivate/uninstall/multisite|NOT RUN|Source preserves data on deactivation; no uninstall routine; queued work and network activation guarantees unestablished.|
|Large-data/concurrency/provider acceptance|NOT RUN|Resource budgets, locks, replay/worker fencing and real provider sandbox acceptance unresolved.|
|Support/security-reporting/update channel|FAIL documentation gate|Choose managed ZIP vs WordPress.org, document authenticated distribution/update/rollback and support/security contact. No updater implementation attested.|

Plugin Check raw output is a FILE heading plus JSON array per file, not one JSON document despite its CLI format flag. [Normalized diagnostics](evidence/plugin-check-normalized.json) and [counts](evidence/plugin-check-summary.json) preserve all 1,558 diagnostics. For example, SQL table-name interpolation may involve trusted static identifiers and is not automatically SQL injection; nonce warnings may overlook delegated guard helpers; PDF-controlled HTML may be deliberately emitted. These require specific triage. Hidden .graphifyignore and design.md are concrete packaging diagnostics. Do not mass-suppress or claim 1,558 vulnerabilities.

## Dependencies, upstream review and licenses

|Installed dependency|License evidence|Review result|
|---|---|---|
|dompdf/dompdf 3.1.6|vendor/dompdf/LICENSE.LGPL plus bundled upstream license|Known 3.1.5-and-earlier chroot/file-oracle/bitmap advisories fixed in 3.1.6; upstream archive provenance/hash not independently attested.|
|dompdf/php-font-lib 1.0.2|bundled LGPL 2.1-or-later notice|Security page inspected; no displayed published advisory. Absence of advisories is not a code-security proof.|
|dompdf/php-svg-lib 1.0.2|bundled LGPL 3-or-later notice|Published older-version advisories require version-range tracking; current dependency identity recorded. Final composer/advisory provenance check remains necessary.|
|Masterminds/html5 2.10.1|bundled MIT|Security page inspected; no displayed advisories.|
|sabberworm/php-css-parser 8.9.0|bundled MIT|Security page inspected; no displayed advisories.|
|Chart.js 4.5.1, embedded @kurkle/color 0.3.2|MIT header notices|Actual 4.5.1 header differs from 4.4.1 enqueue/cache metadata and old development docs; no displayed Chart security advisories. Source/build provenance not attested.|
|Inter Latin 300/400/500/600/700|font metadata copyright + OFL URL; no full license|All five name tables inspected with FontTools; no license-text name record 13 and no standalone full license. REL-002.|

Official July 20, 2026 Dompdf advisories mark <=3.1.5 affected and 3.1.6 patched: [chroot boundary](https://github.com/dompdf/dompdf/security/advisories/GHSA-wvh6-f5jh-8gw4), [font file oracle](https://github.com/dompdf/dompdf/security/advisories/GHSA-7x2p-4jvh-6384), [oversized bitmap](https://github.com/dompdf/dompdf/security/advisories/GHSA-f5gf-2cj8-52g2). This reassesses the bundle positively for those advisories; it does not excuse missing application resource limits. Other official pages checked: [font library](https://github.com/dompdf/php-font-lib/security), [SVG library](https://github.com/dompdf/php-svg-lib/security), [HTML parser](https://github.com/Masterminds/html5-php/security), [CSS parser](https://github.com/MyIntervals/PHP-CSS-Parser/security), [Chart.js](https://github.com/chartjs/Chart.js/security). Preserve all applicable LGPL/MIT/OFL notices and offer corresponding source as required; the GPLv2-or-later declaration does not waive dependency obligations.

The [Inter upstream license](https://github.com/rsms/inter/blob/master/LICENSE.txt) permits bundling subject to copyright/license inclusion. Current [font metadata evidence](evidence/font-license-metadata.json) contains copyright and a URL, not the full license. Add a verified notice corresponding to the actual subset origin and license/source inventory before distribution.

For WordPress.org, [official detailed guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/) address compatible licensing, meaningful external-service documentation/consent and opt-in public credits. This audit’s directory compliance findings are source-based interpretations; final directory review remains with its reviewers. A private ZIP channel still requires honest privacy/licensing/support and tested compatibility.

## Root release findings

### REL-001 — P1 High: exact candidate cannot satisfy release attestation

**Source:** `tests/release/class-release-artifact.php`, `.github/workflows/tests.yml`, evidence build/preflight/verifier. **Trigger:** promote current dirty/untracked tree. **Expected/actual:** clean committed candidate, matched successful CI and no blockers; actual local_rehearsal_only, no valid matrix attestation and rejected promotion. **Impact:** unrepeatable approval identity and unverified deployment support. **Evidence:** exact SHA/base recorded above. **Tests:** local suites pass; none establish remote completed CI on this uncommitted state. **Remediation:** after reviewed fixes, commit complete source/dependencies, run matrix and build from that revision, retain CI manifests/checksums. **Verification:** promotion verifier and fresh-artifact rehearsal with exact revision/bytes. **Confidence:** high; no release was attempted. Dirty work itself is not a product bug.

### REL-002 — P2 Medium: bundled Inter fonts lack the complete distribution license notice

**Source:** `assets/fonts/inter-latin-*.woff2` (all five), release manifest. **Trigger:** distributing candidate. **Expected/actual:** required copyright/full OFL notice; actual metadata copyright/URL only, no name 13 license or standalone notice. **Impact:** incomplete third-party distribution compliance/provenance. **Evidence:** font-license-metadata.json and upstream license linked above. **Tests:** no license-content/provenance assertion. **Remediation:** verify exact font origin and include its notice/license/source manifest. **Verification:** inspect installed ZIP notices and font metadata; licensing review if subset modified. **Confidence:** high missing notice evidence; exact subset origin remains unknown.

### REL-003 — P2 Medium: public plugin credit defaults on

**Source:** `includes/class-settings.php:35`, `templates/workspace.php:42`, auth login footer; observed anonymous login. **Trigger/users:** fresh frontend shortcode use. **Expected/actual:** directory public credit explicit opt-in; actual show_plugin_attribution=1. **Impact:** directory submission gate and unexpected public branding. **Evidence:** source/default/actual browser; setting can be off but default remains. **Tests:** normalization tests do not establish opt-in. **Remediation/verification:** default off, clear opt-in, preserve existing authorized preferences, fresh/upgrade/login/workspace tests. **Confidence:** high behavior; directory rule application per official guidelines above.

### REL-004 — P2 Medium: public service/privacy/support and lifecycle disclosures are incomplete

**Source:** `readme.txt:14–43`; integration contracts and deletion/retention matrix. **Trigger/users:** install/configure integrations or erase/uninstall. **Expected/actual:** clear provider/data/purpose/retention/consent/credentials/support/update/security-reporting contract; actual feature list and short installation FAQ only. **Impact:** users cannot assess PII transfers or cleanup; directory release gate. **Evidence:** provider HTTP and storage catalogue; no privacy/service URL/support/security section. **Tests:** none establish published disclosures. **Remediation:** document actual Meta/Google/LinkedIn/Interakt flows, hashed vs plaintext data, opt-in configuration, private-media deficiency, erasure/retention/reset/restore limits and support contact; resolve defects first. **Verification:** installed readme/UI instructions against tested behavior and provider terms. **Confidence:** high documentation gap; no legal compliance certification implied.

### REL-005 — P2 Medium: compatibility promises exceed verification and omit prerequisites

**Source:** `readme.txt:4–8`, main plugin header PHP 7.4, workflow matrix, vendor platform checks/exporter. **Trigger:** WP 5.8/PHP 7.4 or missing extensions hosts. **Expected/actual:** declared tested minimum/prerequisites supported; actual local WP 7.1.2/PHP 8.4 only, CI WP 6.6/latest unattested, missing readme Requires PHP and extension/onboarding detail. **Impact:** avoidable install/download failure and unsupported compatibility claims. **Evidence:** environment/lint/CI records. **Tests:** current matrix does not cover WP 5.8; missing extension scenarios NOT RUN. **Remediation/verification:** choose actual support floor, align metadata, exercise floor and advertised matrix plus renderer/ZIP/crypto/image extension failures. **Confidence:** high evidence gap, not a confirmed WP 5.8 runtime failure.

### REL-006 — P2 Medium: ZIP builder does not normalize the root-directory timestamp

**Source:** `tests/release/class-release-artifact.php:268` addEmptyDir; file metadata normalization differs. **Trigger:** identical source independent rebuild. **Expected/actual:** same checksum; actual identical 431 contents but changed root directory timestamp and SHA. **Impact:** byte reproducibility requirement fails and cannot compare independent build checksums. **Evidence:** reproducibility.json. **Tests:** inventory inspection passes without byte comparison. **Remediation/verification:** deterministic root entry metadata or omission, fixed build inputs, two separated rebuilds with identical hash. **Confidence:** high observed comparison.

### REL-007 — P2 Medium: final package fails static submission diagnostics and includes local artifacts

**Source:** `.distignore`, packaged `.graphifyignore`, `design.md`, Plugin Check normalized source/file/line diagnostics. **Trigger:** directory submission/final ZIP review. **Expected/actual:** intentional runtime files and resolved/justified diagnostics; actual hidden file, unexpected markdown, 107 errors/1451 warnings. **Impact:** submission/maintenance gate and unreviewed diagnostic backlog. **Evidence:** exact runtime checked after harness removal. **Tests:** existing release inventory permits these files and does not run Plugin Check. **Remediation/verification:** exclude unneeded local files, triage SQL/escaping/filesystem/resource/nonce warnings by actual contract, rerun official check on fresh final ZIP, retain narrow valid justifications. **Confidence:** high diagnostics, individual alerts are not automatically vulnerabilities.

## Ranked blockers, remediation dependencies and final approval evidence

1. Close unauthorized access/mutation first: MCP mixed-table/auth-column exposure (INT-001/002), new-quote revision binding (QUO-001), broad default access/public attachments/stale task and quote ownership (CORE-001/002/011, QUO-002/003). Required evidence: actual disposable HTTP roles/revocation/ownership/nonces/object-ID tests and private-document revocation/storage checks.
2. Protect recovery/history before migrations: incomplete backup reset (INT-003), schema completion (CORE-007/QUO-004), catalogue snapshot rewrite/legacy state loss (QUO-005/010), lifecycle/upsert partial writes (CORE-004/005). Required evidence: native failed write rollback/retry and historical upgrade/restore aggregate comparisons, locks and worker fencing.
3. Repair core business/queue behavior: partial task plans/capacity/disabled assignment/review PII, disabled/replayed external work/manual retry/retention/idempotency. Required evidence: failure/concurrency/cancellation fixtures and provider sandbox acceptance where required; no real customers.
4. Repair quotation domain/output/UI: numeric/engine/historical currency/terms/paper contracts, browser quantity validation, mobile/labels/PHP diagnostics. Required evidence: supported runtime browser matrix, exact revision PDF/print/tabular parity and error recovery.
5. Complete release contract: notices/privacy/attribution/support/compatibility, Plugin Check triage, deterministic packaging, clean commit and matched CI. Required evidence: final 431-or-revised inventory SBOM/notices, dependency range/provenance check, clean Git revision, full supported matrix, same-byte independent ZIP rebuild, final-artifact native/browser/upgrade/restore rehearsal, SHA + manifest + attestation and passing promotion verifier.

Current aggregate scenario counts are linked above; unresolved required checks, 44 findings and known material defects prevent approval. No responsible **READY WITH EXPLICIT LIMITATIONS** verdict can waive these security/data-integrity blockers. **NOT READY** remains the final verdict.
