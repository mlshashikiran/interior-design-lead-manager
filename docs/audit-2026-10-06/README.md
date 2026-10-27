# Engineering and public-release audit

Author: **Shashi**. Date: **2026-10-06**. Plugin: **Interior Design Lead Manager 1.102.42**. Verdict: **NOT READY**.

The review covers all first-party modules, registered interfaces and bundled runtime files in the current working tree, including the untracked quotation exporter and PDF dependencies. All eight audit phases were completed as review and verification work. Required cases that could not be established remain explicitly open and prevent approval. Production behavior and the configured site database were not changed. Only audit documentation, synthetic fixtures and tests were added.

## Deliverables

1. [Module inventory and architecture](ARCHITECTURE.md): 53 include modules, 24 templates, 22 assets, boot/lifecycle ownership, dependencies and tooling.
2. [Contract inventory](CONTRACTS.md): public signatures, actions, filters, AJAX, REST, admin-post, shortcodes, menus, jobs, CLI and MCP. Detailed request chains link to the module reviews.
3. [Data model and business states](DATA-AND-FLOWS.md): 21 custom tables, 291 columns, 89 indexes, relationships, states and persistence invariants.
4. [Cross-module flows and dependencies](DATA-AND-FLOWS.md#twelve-flow-contracts): all 12 requested chains, Mermaid diagrams and failure, recovery, ownership and privacy effects.
5. [Executed scenarios and gaps](SCENARIOS.md), [CSV matrix](scenario-matrix.csv): named outcomes, environments, evidence and limits.
6. [Findings register](FINDINGS.md): 44 stable findings with ranked remediation groups, source references, evidence and uncertainty.
7. [Developer guide](DEVELOPER-GUIDE.md): canonical extension seams, authorization, persistence, queues, testing, troubleshooting and release workflow.
8. [Release checklist and verdict](RELEASE.md): artifact identity, dependency/license/directory checks, blockers and approval requirements.

Detailed reviews: [core](CORE-REVIEW.md), [core signatures](CORE-SOURCE-CONTRACTS.md), [integrations/security](INTEGRATION-SECURITY-REVIEW.md), [quotations/catalogue/exports](QUOTATION-REVIEW.md), [actual UI and exports](UI-REVIEW.md).

## Evidence and counts

Base revision `ff148ba62ec710a622d9b1af7e8f511b7e0b7385` is version 1.102.38. The reviewed working tree is 1.102.42 with existing dirty changes. The [source manifest](evidence/source-manifest.json), [initial status](evidence/initial-working-tree.txt), [original patch](evidence/reviewed-working-tree.patch) and [preservation verification](evidence/source-preservation.json) bind the review and confirm that audit work changed none of the 513 original files. No commit or release was created.

The exact local rehearsal ZIP contains 431 files. SHA256: `eb866c3d16fd34aaf568212b01655c3661810b82646bb62b2df3490f0e700760`. All installed files match, with zero extras. The [candidate](evidence/candidate/interior-design-lead-manager-1.102.42.zip) is retained for inspection only; its manifest explicitly denies production approval.

Existing suites pass: 174 fast cases, 17 native cases and the same 17 native cases against the artifact. These are 208 invocations covering 191 distinct existing named cases. Targeted audit probes add 48 assertions: **19 PASS and 29 FAIL**. The full CSV includes additional UI/release observations and explicit gaps: **243 PASS, 36 FAIL, 2 BLOCKED, 127 NOT RUN and 1 NOT APPLICABLE**. These totals combine different granularities and repeated environments; they are neither coverage percentages nor unique workflows. The separate syntax check passed for 301 PHP files. Plugin Check reported 107 errors and 1,451 warnings, which are static diagnostics requiring triage rather than vulnerability counts.

Native/browser work used disposable MySQL 8.4, WordPress 7.1.2, PHP 8.4.14, synthetic users/leads, isolated uploads, disabled mail and blocked outbound HTTP. Actual browser checks covered administrator, representative and subscriber sign-in; navigation, drawer tabs and search; desktop/mobile task layouts; validation; quotation save; and PDF/CSV/XLSX downloads. The full supported CI matrix, historical upgrades, concurrency, provider acceptance and other required dimensions remain blocked or not run. See the scenario matrix for the exact limits.

Substantial independent module reviews were delegated under the repository routing policy. Reviewers supplied bounded source evidence and synthetic invariants. The primary agent integrated their results and verified the artifact, UI and release requirements. Reviewers' test doubles are clearly distinguished from actual WordPress/MySQL/browser evidence.

Graphify was queried first for lifecycle, quotation and authorization ownership. Its October 2 snapshot was stale relative to the dirty source. The final refresh is scoped to first-party runtime and audit documentation because the old manifest treats 434 files, including vendor code, as changed. Graph evidence is a navigation aid; current source and executable evidence govern authorization, transactions and business behavior. The refresh scope and limitations are retained in evidence.

## Final decision and approval conditions

The highest-impact blockers are MCP table/authentication-column exposure, new-quotation revision binding, broad/public/stale-owner PII access, incomplete restore deleting omitted data, migration/history and persistence failures, ordinary quotation quantity validation, and absent attestation for a clean release candidate.

Follow the dependency order in the findings and release reports. Approval requires actual disposable HTTP permission/object-binding tests; private-file revocation; native historical upgrade, restore and failed-write recovery; queue/concurrency scenarios; commercial output/UI/runtime checks; complete notices/privacy/support metadata; Plugin Check triage; and a deterministic final ZIP with a clean revision, checksum, matching CI attestation and successful promotion rehearsal.

**NOT READY.** Material security/data-integrity findings and required verification gaps remain unresolved.
