# Consolidated findings register

Author: Shashi. Reviewed 2026-10-06, working tree 1.102.42. **NOT READY.** Findings are separated from unexecuted verification gaps and static Plugin Check diagnostics.

Every row links to the detailed finding, which records module/source lines, trigger/users, expected versus actual behavior, impact, safe evidence, existing-test coverage, remediation, verification and confidence/uncertainty. P1/High marks material security/data integrity or a central workflow/release gate. P2/Medium covers narrower operational/output/compliance defects or conditional integrity risks. P3/Low covers documentation/maintenance issues. Severity does not imply every item was reproduced through actual HTTP.

The register has 44 findings. Confidence and evidence type remain individual: CORE native failure probes use actual disposable MySQL; integration/quotation probes mostly use doubles/recorders; UI findings use the installed artifact and browser; some findings are source-supported risks rather than observed environment failures. INT-010 plaintext legacy platform credentials is an observed storage design risk, not a demonstrated exfiltration. QUO-007 is conditional on nontransactional engines. QUO-010 requires a supported historical native upgrade fixture and product decision before approval. No automatic finding is created per Plugin Check warning.

## Ranked remediation groups

1. Unauthorized access/mutation and private-data boundaries: INT-001/002, QUO-001, CORE-001/002/011, QUO-002/003.
2. Recovery, migration and historical integrity: INT-003, CORE-004/005/007, QUO-004/005/010.
3. Routing/task/review retention and queue delivery/idempotency: remaining CORE/INT operational findings.
4. Commercial calculations/historical output and UI: QUO-006/007/008/009 and UI-001–004.
5. Public notices/disclosures/compatibility, reproducible package, static diagnostic triage, clean commit and exact CI attestation: REL-001–007.

Resolve business-policy decisions explicitly before adding regression expectations; do not treat documented intentional broad reads or draft-only normalization as approved public defaults. A proposed exception must still address impact, preservation and user-facing contract.

## Register

|ID|Severity|Finding and detailed evidence|
|---|---|---|
|CORE-001|High|[P1 High: broad default CRM visibility and ineffective capability revocation](CORE-REVIEW.md#core-001--p1-high-broad-default-crm-visibility-and-ineffective-capability-revocation)|
|CORE-002|High|[P1 High: confidential uploads use public static URLs and survive deletion](CORE-REVIEW.md#core-002--p1-high-confidential-uploads-use-public-static-urls-and-survive-deletion)|
|CORE-003|High|[P1 High: lead CSV formula injection](CORE-REVIEW.md#core-003--p1-high-lead-csv-formula-injection)|
|CORE-004|High|[P1 High: canonical upsert acknowledges failed persistence and emits false side effects](CORE-REVIEW.md#core-004--p1-high-canonical-upsert-acknowledges-failed-persistence-and-emits-false-side-effects)|
|CORE-005|High|[P1 High: lead row and immutable reporting events/tasks are not atomic](CORE-REVIEW.md#core-005--p1-high-lead-row-and-immutable-reporting-eventstasks-are-not-atomic)|
|CORE-006|Medium|[P2 Medium: partial six-task plan cannot self-repair](CORE-REVIEW.md#core-006--p2-medium-partial-six-task-plan-cannot-self-repair)|
|CORE-007|High|[P1 High: schema migration markers advance without successful schema verification](CORE-REVIEW.md#core-007--p1-high-schema-migration-markers-advance-without-successful-schema-verification)|
|CORE-008|Medium|[P2 Medium: CRM reset leaves pending identity-review PII and there is no resolution interface](CORE-REVIEW.md#core-008--p2-medium-crm-reset-leaves-pending-identity-review-pii-and-there-is-no-resolution-interface)|
|CORE-009|Medium|[P2 Medium: disabled users remain assignable](CORE-REVIEW.md#core-009--p2-medium-disabled-users-remain-assignable)|
|CORE-010|Medium|[P2 Medium: unassigned recovery exceeds configured rep capacity](CORE-REVIEW.md#core-010--p2-medium-unassigned-recovery-exceeds-configured-rep-capacity)|
|CORE-011|High|[P1 High: task queue leaks reassigned lead PII to former owner](CORE-REVIEW.md#core-011--p1-high-task-queue-leaks-reassigned-lead-pii-to-former-owner)|
|CORE-012|Medium|[P2 Medium: retention and heavy reads remain unbounded/weakly recoverable](CORE-REVIEW.md#core-012--p2-medium-retention-and-heavy-reads-remain-unboundedweakly-recoverable)|
|CORE-013|Low|[P3 Low: documentation and state registries overstate consistency](CORE-REVIEW.md#core-013--p3-low-documentation-and-state-registries-overstate-consistency)|
|INT-001|High|[HIGH — MCP SQL allowlist bypass after an explicit JOIN](INTEGRATION-SECURITY-REVIEW.md#int-001--high--mcp-sql-allowlist-bypass-after-an-explicit-join)|
|INT-002|High|[HIGH — MCP explicitly exposes WordPress authentication columns](INTEGRATION-SECURITY-REVIEW.md#int-002--high--mcp-explicitly-exposes-wordpress-authentication-columns)|
|INT-003|High|[HIGH — Incomplete backup payload commits an unintended CRM reset](INTEGRATION-SECURITY-REVIEW.md#int-003--high--incomplete-backup-payload-commits-an-unintended-crm-reset)|
|INT-004|Medium|[MEDIUM — Ordinary queued Interakt ignores disabling and retains revoked key](INTEGRATION-SECURITY-REVIEW.md#int-004--medium--ordinary-queued-interakt-ignores-disabling-and-retains-revoked-key)|
|INT-005|Medium|[MEDIUM — Manual CAPI retry cannot replay current Google defaults and bypasses worker gates](INTEGRATION-SECURITY-REVIEW.md#int-005--medium--manual-capi-retry-cannot-replay-current-google-defaults-and-bypasses-worker-gates)|
|INT-006|Medium|[MEDIUM — Meta webhook acknowledges dropped batch messages and appends replays](INTEGRATION-SECURITY-REVIEW.md#int-006--medium--meta-webhook-acknowledges-dropped-batch-messages-and-appends-replays)|
|INT-007|Medium|[MEDIUM — Disabling Google audience rule does not stop its pull feed](INTEGRATION-SECURITY-REVIEW.md#int-007--medium--disabling-google-audience-rule-does-not-stop-its-pull-feed)|
|INT-008|Medium|[MEDIUM — Seven-day CAPI pruning removes configured retention and idempotency evidence](INTEGRATION-SECURITY-REVIEW.md#int-008--medium--seven-day-capi-pruning-removes-configured-retention-and-idempotency-evidence)|
|INT-009|Medium|[MEDIUM — Audience failures discard manual work; manual queue can bypass exclusion](INTEGRATION-SECURITY-REVIEW.md#int-009--medium--audience-failures-discard-manual-work-manual-queue-can-bypass-exclusion)|
|INT-010|Medium|[MEDIUM — Remaining platform tokens are plaintext and some requests put secrets in URLs](INTEGRATION-SECURITY-REVIEW.md#int-010--medium--remaining-platform-tokens-are-plaintext-and-some-requests-put-secrets-in-urls)|
|QUO-001|High|[P1: new quotation accepts an existing revision without binding authorization](QUOTATION-REVIEW.md#quo-001--p1-new-quotation-accepts-an-existing-revision-without-binding-authorization)|
|QUO-002|High|[P1: quotation lists disclose data after lead reassignment](QUOTATION-REVIEW.md#quo-002--p1-quotation-lists-disclose-data-after-lead-reassignment)|
|QUO-003|High|[P1: all-lead read scope grants quotation mutations on another owner's lead](QUOTATION-REVIEW.md#quo-003--p1-all-lead-read-scope-grants-quotation-mutations-on-another-owners-lead)|
|QUO-004|High|[P1: failed quotation migration can be marked complete](QUOTATION-REVIEW.md#quo-004--p1-failed-quotation-migration-can-be-marked-complete)|
|QUO-005|High|[P1: schema install rewrites immutable item classifications from current catalogue](QUOTATION-REVIEW.md#quo-005--p1-schema-install-rewrites-immutable-item-classifications-from-current-catalogue)|
|QUO-006|Medium|[P2: current currency and payment/template settings change historical PDF/print](QUOTATION-REVIEW.md#quo-006--p2-current-currency-and-paymenttemplate-settings-change-historical-pdfprint)|
|QUO-007|Medium|[P2, conditional integrity risk: transactions rely on an unchecked default table engine](QUOTATION-REVIEW.md#quo-007--p2-conditional-integrity-risk-transactions-rely-on-an-unchecked-default-table-engine)|
|QUO-008|Medium|[P2: numeric normalization permits nonfinite/overrange values and inconsistent tiny areas](QUOTATION-REVIEW.md#quo-008--p2-numeric-normalization-permits-nonfiniteoverrange-values-and-inconsistent-tiny-areas)|
|QUO-009|Medium|[P2: Letter setting falls back to A4 for downloaded PDF](QUOTATION-REVIEW.md#quo-009--p2-letter-setting-falls-back-to-a4-for-downloaded-pdf)|
|QUO-010|High|[P1 upgrade data-loss risk: legacy commercial quotation states are erased](QUOTATION-REVIEW.md#quo-010--p1-upgrade-data-loss-risk-legacy-commercial-quotation-states-are-erased)|
|REL-001|High|[P1 High: exact candidate cannot satisfy release attestation](RELEASE.md#rel-001--p1-high-exact-candidate-cannot-satisfy-release-attestation)|
|REL-002|Medium|[P2 Medium: bundled Inter fonts lack the complete distribution license notice](RELEASE.md#rel-002--p2-medium-bundled-inter-fonts-lack-the-complete-distribution-license-notice)|
|REL-003|Medium|[P2 Medium: public plugin credit defaults on](RELEASE.md#rel-003--p2-medium-public-plugin-credit-defaults-on)|
|REL-004|Medium|[P2 Medium: public service/privacy/support and lifecycle disclosures are incomplete](RELEASE.md#rel-004--p2-medium-public-serviceprivacysupport-and-lifecycle-disclosures-are-incomplete)|
|REL-005|Medium|[P2 Medium: compatibility promises exceed verification and omit prerequisites](RELEASE.md#rel-005--p2-medium-compatibility-promises-exceed-verification-and-omit-prerequisites)|
|REL-006|Medium|[P2 Medium: ZIP builder does not normalize the root-directory timestamp](RELEASE.md#rel-006--p2-medium-zip-builder-does-not-normalize-the-root-directory-timestamp)|
|REL-007|Medium|[P2 Medium: final package fails static submission diagnostics and includes local artifacts](RELEASE.md#rel-007--p2-medium-final-package-fails-static-submission-diagnostics-and-includes-local-artifacts)|
|UI-001|High|[P1 High: ordinary quotation quantities cannot pass browser validation](UI-REVIEW.md#ui-001--p1-high-ordinary-quotation-quantities-cannot-pass-browser-validation)|
|UI-002|Medium|[P2 Medium: mobile task form remains a compressed two-column layout](UI-REVIEW.md#ui-002--p2-medium-mobile-task-form-remains-a-compressed-two-column-layout)|
|UI-003|Medium|[P2 Medium: task controls lack associated accessible labels](UI-REVIEW.md#ui-003--p2-medium-task-controls-lack-associated-accessible-labels)|
|UI-004|Medium|[P2 Medium: fresh-install quotation downloads emit PHP 8.4 diagnostics](UI-REVIEW.md#ui-004--p2-medium-fresh-install-quotation-downloads-emit-php-84-diagnostics)|

## Reassessment of earlier concerns

Broad default CRM access, public static upload URLs and lead CSV formula injection remain confirmed (CORE-001/002/003). Quotation CSV protection is different: four dangerous-prefix examples pass; do not generalize the lead defect to quotation exports. Attribution defaults remain on and are observed on anonymous login (REL-003). Readme service/privacy disclosures remain incomplete (REL-004). Inter copyright metadata exists, but the full license text is absent from both files and distribution notices (REL-002); this was checked rather than assumed from folder contents alone. Compatibility and release attestation remain incomplete (REL-001/005). Dompdf 3.1.6 resolves the three reviewed July 2026 older-version advisories; it is not reported as affected by those issues. Existing ownership/nonces/export guards that pass their narrow tests are retained in reviewer reports.

No runtime fixes were applied. Required verification gaps remain in SCENARIOS.md and RELEASE.md even when no defect has yet been demonstrated. Resolve material findings and complete applicable required gaps against one clean final candidate before approval.
