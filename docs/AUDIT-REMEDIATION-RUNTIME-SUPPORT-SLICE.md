# REL-005 — runtime support and host prerequisites

Author / Builder: Shashi. Date: 2026-10-09. Local candidate: **1.102.75**. Schemas unchanged. **IMPLEMENTED, not CLOSED.**

## Finding, support decision and source characterization

The immutable [REL-005 finding](audit-2026-10-06/RELEASE.md) identified the untested WordPress 5.8 claim, absent public PHP minimum, missing host prerequisites and unattested runtime matrix. The user chose **“Raise WP minimum to 6.6; retain PHP 7.4.”** WordPress versions below 6.6 are now outside the selected support scope; this is a deliberate policy change, not a proven failure on 5.8. Metadata alone does not certify the target range.

The bounded Graphify query **“REL-005 runtime compatibility prerequisites quotation exporter secret store”** located the release checklist and credential-store ownership. Current source then established the PDF autoload/fallback, existing ZIP check, credential failure/preservation behavior and CI matrix. Bundled Dompdf 3.1.6 declares PHP ^7.1 or ^8.0, DOM/mbstring; its font dependency requires iconv. GD is needed for image handling and is required by this plugin's supported PDF path. Composer's bundled platform check only enforces PHP >=7.1 and is not a complete extension preflight. [Dompdf requirements](https://github.com/dompdf/dompdf/wiki/Requirements) corroborate the source inventory. The [official WordPress release archive](https://wordpress.org/download/releases/) resolved current latest to 7.1.3 during review; that version was not executed locally.

## Implemented behavior and published contract

- Align the main header and readme at **WordPress 6.6 / PHP 7.4**; public Tested up to is **7.1**, supported by named local 7.1.2 evidence, without claiming 7.1.3 execution. Release authorities agree on **1.102.75**.
- Document tested MySQL/required InnoDB/WordPress database driver, OpenSSL AES-256-GCM and key recovery, DOM/mbstring/iconv/GD and renderer/cache/temp storage, ZipArchive, cron/HTTP/uploads, and optional scheduler/cache behavior in the bundled readme and SUPPORT.md. PHP modules must be enabled for the web runtime as well as CLI. MariaDB, multisite/network activation, distributed topologies and production resource/volume guarantees remain unqualified.
- Check PDF extensions and readable bundled autoload before loading vendor code or sending binary bytes. Missing prerequisites retain the existing Preview / Print redirect and generic notice. PDF requires GD consistently even for text-only documents; CSV and browser print do not depend on renderer/ZIP modules.
- Reject nonexistent/unwritable XLSX temporary directories before tempnam, and suppress a race-time tempnam warning while returning the checked existing error. Retain existing missing-ZIP and generic HTTP error contracts.
- Check OpenSSL function availability, disabled-function configuration and AES-256-GCM support before credential operations. PHP 7.4 reports disabled functions as existing, so function_exists alone could not safely refuse configured disabling. Missing crypto/cipher support returns failure without replacing plaintext or encrypted stored bytes. Environment-managed credentials retain their separate existing path.
- Keep all six PHP 7.4/8.1/8.4 × WordPress 6.6/latest CI jobs. Explicitly install renderer/ZIP/crypto/database extensions, include prerequisite probes in ordinary CI and fast tests, record actual PHP/WP/MySQL/dependency versions, extension inventory, source revision and downloaded-core hash, and upload named logs. Bash pipelines retain pipefail. Release artifact gating still depends on the entire successful matrix.

## Executed checks and evidence limits

[Machine evidence](remediation-2026-10-09/runtime-support-slice/verification.json) records commands, scenarios, source hashes, environment and results:

- **361 fast cases PASS** on PHP **8.4.14**. Existing unrelated test-double warnings remain visible in fast-diagnostics.txt (six missing num_rooms fields and one missing phone field); affected prerequisite tests emit no diagnostics.
- **5 final focused runtime contracts PASS**, covering five PDF denial variants (DOM/mbstring/iconv/GD/renderer), missing ZIP, unavailable temporary directory, a real parsed XLSX and actual disabled OpenSSL functions with preservation of both legacy plaintext and valid encrypted bytes. Missing module/renderer/ZIP cases use isolated namespaced PHP API seams over verbatim production source: these are controlled simulations, not actual extension-free host installations. Temporary-directory failure and OpenSSL disabling are real local process conditions. No customer credential is used.
- Official downloaded **WordPress 6.6**, PHP **8.4.14**, isolated **MySQL 8.4.0**: **306 full native + 429 full actual HTTP/stdio cases PASS**, plus **11 focused PDF/print/CSV/XLSX cases PASS**. These establish named local floor behavior, not every database/PHP combination.
- Available **WordPress 7.1.2**, PHP **8.4.14**, same owned server with a fresh disposable database: **11 focused actual export cases PASS**. Multipage A4/Letter/A3/Legal PDFs are parsed for dimensions and text, CSV is parsed and XLSX signatures checked by the existing fixture. HTTP stderr is empty in these local runs. Native stderr retains three expected database diagnostics from deliberately injected review-deletion/reset and missing-receipt-table failures; the associated rollback contracts pass. Provider HTTP/mail is intercepted; configured-site data is not loaded.
- Changed PHP lint, workflow YAML/matrix/pipeline checks, version/inventory checks and a local package/source identity rehearsal pass. The package remains local_rehearsal_only; no installed final-artifact execution or production approval is inferred.

The first default-sandbox MySQL initialization crashed before fixture creation and socket connections were denied; approved isolated execution completed the tests. Early prerequisite probes exposed fixture omissions (is_admin/get_temp_dir resolution), corrected in test seams before the passing runs. These were fixture/environment failures, not suppressed application outcomes.

## Remaining qualification and next work

PHP **7.4/8.1** are unavailable locally and were **NOT RUN**. The six-cell exact-revision remote CI, resolved latest **7.1.3**, MySQL 8.0 CI behavior, actual hosts with absent extensions, image codec/resource/temp/font-cache failures beyond the named probes, all scheduler/cache/topology variants, historical full-site recovery and final installed artifact remain required qualification. The new tests do not certify every missing-prerequisite WordPress boot sequence or all image/PDF failure modes.

REL-005 is **IMPLEMENTED, not CLOSED**; clean complete candidate identity/matching CI and release qualification remain under REL-001. REL-006 deterministic root ZIP metadata and REL-007 final inventory/Plugin Check triage remain open. No commit, push, tag, configured-site mutation, provider send, email or deployment occurred. Test databases/content/server fixtures are cleaned after execution; downloaded WordPress core and evidence remain local for reproducibility. Scoped graph structure/document headings are refreshed while retaining unrelated graph material.

Backlog: **2 OPEN / 1 IN PROGRESS / 41 IMPLEMENTED**. Next executable finding: **REL-006**.
