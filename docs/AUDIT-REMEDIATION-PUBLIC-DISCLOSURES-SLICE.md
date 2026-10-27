# REL-004 — public service, privacy and support disclosures

Author / Builder: Shashi. Date: 2026-10-09. Local candidate: **1.102.74**. Schemas and runtime behavior unchanged. **IMPLEMENTED, not CLOSED.**

## Finding, source review and owner decision

The immutable [REL-004 finding](audit-2026-10-06/RELEASE.md) identified missing provider/data/purpose/privacy, retention/erasure, support/security and update/recovery information. Before changes, the public readme lacked all four new disclosure sections and there were no bundled public privacy/support guides. Twelve existing release contracts already passed; those tests did not establish the adequacy of disclosures.

The bounded Graphify query **“REL-004 service privacy retention erasure uninstall support”** connected public-release findings with identity-review erasure, CAPI retention and backup recovery. Current provider payloads, cookie code, telemetry, credentials, private files, deletion/reset, backup exclusions, deactivation and registrations were then reviewed directly. [Claim-to-source mapping and hashes](remediation-2026-10-09/public-disclosures-slice/disclosure-source-review.json) record the resulting characterization.

The user supplied **mlshashikiran@gmail.com** in response to the support/security/update contact question. It is used for support, private vulnerability reports and reviewed update requests. The current manual ZIP process is documented without inventing an automatic updater, published listing, response SLA or production approval. No email was sent.

## Published behavior

The public [readme](../readme.txt), [PRIVACY.md](../PRIVACY.md) and [SUPPORT.md](../SUPPORT.md) now explain:

- Meta conversion/custom-audience fields; Google GA4 Measurement Protocol versus the **plaintext email/phone** audience pull; LinkedIn hashed email with **plaintext names** for contact audiences; Interakt/WhatsApp plaintext contact/project traits and incoming message storage. Official provider privacy/terms links are included.
- Administrator configuration and enabled-rule triggers, connection-test requests, matchable hashes, provider-controlled retention/deletion, WordPress staff mail, MCP clients, remote saved quotation artwork and phone-bearing Interakt browser URLs.
- Local CRM/diagnostic data, protected credential encryption/environment overrides and key recovery, the **automatic 30-day attribution cookies with no built-in consent gate**, and the absence of automatic developer telemetry collection.
- Retention defaults of 90/180/14 days, separate seven-day integration-summary activity cleanup, scheduler/batch timing, ordinary CRM data without general automatic expiry, CAPI receipts and surviving WhatsApp replay receipts.
- Authorized local deletion/reset, private versus legacy/media handling, exclusions for provider/download/cache/backup copies, lack of WordPress personal-data exporter/eraser integration, partial same-site JSON restore, deactivation preservation and absence of an uninstall purge.
- Private reporting, minimal redacted support evidence, manually reviewed updates, staging/checksum/full-site backup and matching-key recovery requirements, and why installing an older ZIP alone does not reverse migrations.

Provider policies/terms are linked, not reproduced or certified. Google/LinkedIn/Interakt pages and the WordPress directory guideline were retrieved from primary sources. Meta policy/terms URLs redirected the verification fetch to Facebook login; their full content was not available for review. The Google Customer Match policy link was also checked. The incorrect guessed Interakt `/terms-and-conditions/` URL was replaced with the official linked `/terms-of-service/` page before publication.

## Release inventory and verification

The shared required-file inventory now includes `PRIVACY.md` and `SUPPORT.md`. Existing ordinary CI release inventory contracts therefore require them alongside the public readme; an explicit omission probe confirms that either missing guide is rejected. Internal `docs/` and tests remain excluded from the package.

Main header, runtime constant, readme stable tag, latest changelog and scalability candidate authority agree on **1.102.74**, with Shashi attribution. Changes to PHP are limited to version metadata, the required-file list and the corresponding version expectation; no behavior, schema, request handler, template or JavaScript change was made.

Executed evidence in [verification.json](remediation-2026-10-09/public-disclosures-slice/verification.json):

- **12 focused release contracts PASS** before and after the change. No full fast/native/HTTP/browser suite is claimed for this documentation/inventory slice.
- Four changed/owned PHP inputs pass lint; readme sections, owner contact and local guide links pass lightweight checks; either omitted public guide fails inventory validation; whitespace check passes.
- An actual **455-file** local rehearsal ZIP matches every current source file byte for byte, including the readme/privacy/support documents. [Archive identity](remediation-2026-10-09/public-disclosures-slice/installed-identity.json) records the checksum and per-document hashes. This is independent archive inspection, not a WordPress installation or recovery rehearsal.
- Scoped graph refresh retains unrelated graph material and records changed PHP structure and explicit public/internal document sections.

## Remaining qualification

The disclosures explicitly retain known limits rather than describing desired future behavior as implemented. Attribution consent enforcement, WhatsApp receipt retention/erasure policy, production legacy-file/cache migration, provider-side data removal/API acceptance, full-site recovery, unsupported topology and support-floor/runtime matrix work still require decisions or qualification. Documentation does not repair those runtime/deployment limits or establish legal/directory compliance.

REL-001 clean complete revision/matching CI/final installed-artifact gates, REL-005 support metadata/prerequisites, REL-006 ZIP reproducibility and REL-007 inventory/Plugin Check triage remain outstanding. The rehearsal is `local_rehearsal_only` and `production_release_approved=false`; no commit, push, tag, site mutation, provider call, email or deployment occurred. No database/server/browser fixtures were needed.

Backlog: **3 OPEN / 1 IN PROGRESS / 40 IMPLEMENTED**. Next executable finding: **REL-005**; revisit REL-001 after prerequisite and final release qualification.
