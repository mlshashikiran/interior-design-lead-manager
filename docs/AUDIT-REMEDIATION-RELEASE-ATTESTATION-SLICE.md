# REL-001 — exact candidate and release attestation checkpoint

Author / Builder: Shashi  
Candidate: **1.102.71**, runtime source and schemas unchanged  
Status: **IN PROGRESS; prerequisite-blocked, not IMPLEMENTED or CLOSED**

## Finding and current boundary

The immutable [REL-001 finding](audit-2026-10-06/RELEASE.md) requires a complete reviewed commit, matching successful CI, exact package identity and final-artifact qualification. It explicitly states that dirty work is not itself a product defect. Graphify's bounded “release candidate attestation” query located the finding and `IDLM_Release_Candidate` verification methods; current builder, verifier, contracts and workflow source were then inspected.

The existing gates correctly reject the current candidate. The five version authorities align at 1.102.71, and distribution inventory validation passes, but Git still identifies base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385` with modified and untracked source/dependencies. A clean commit of the complete reviewed tree and matching matrix evidence do not exist for these bytes. Prior local fast/native/HTTP passes do not become remote CI or installed-artifact evidence.

No runtime, release-tool or workflow behavior was changed. No version bump, source commit, push, tag, public release, deployment or configured-site mutation was performed. Preserving the unrelated existing changes is required while the remaining release findings and qualification work are resolved. The current rehearsal is explicitly `local_rehearsal_only`, with production approval false.

## Concrete local evidence

Evidence: [verification and prerequisite report](remediation-2026-10-09/release-attestation-slice/verification.json), [preflight](remediation-2026-10-09/release-attestation-slice/preflight.json), [runtime file hashes](remediation-2026-10-09/release-attestation-slice/runtime-source-manifest.json).

| Check | Result | Scope |
|---|---|---|
| Existing release contracts | **10 PASS / 0 FAIL** | Inventory/version, synthetic clean-attestation positive and negative cases, safe checksums, actual ZIP inspection, pending staging gates and workflow wiring |
| Current builder preflight | **PASS inventory; BLOCKED promotion** | 450 files, 14,008,683 uncompressed bytes; aligned version; dirty tree and absent matching CI |
| Explicitly confirmed dirty rehearsal build | **PASS inspection** | ZIP/checksum/manifest retained under the evidence directory; not distributable approval |
| Actual CLI promotion verifier | **Expected rejection, exit 1** | `git_not_clean`, `candidate_not_ci_attested`, `candidate_has_blockers`, `ci_evidence_invalid`, `ci_run_invalid` |
| Fresh isolated filesystem extraction | **450 / 450 byte matches** | Zero missing, modified or extra files; temporary extraction removed; no WordPress activation or application qualification |
| Independent separated rebuild | **FAIL byte reproducibility** | Same file names/contents, different ZIP hashes and root timestamps; confirms OPEN REL-006 |
| Whitespace/source preservation | **PASS** | Release/runtime inputs unchanged; only focused internal documentation/evidence/backlog updated |

Rehearsal A SHA-256: `d8cd2cc715d235e7edc8b09b167a288c43289fbea2e2b5409cad384fb834d94a`. The build manifests and comparison record the second hash and actual root metadata. Each source file is bound to its package bytes in the runtime manifest. This identity is a snapshot of the current working tree, not a new commit or proof of the declared supported runtime matrix.

Environment: local PHP 8.4.14 with ZipArchive. A bounded focused runner reuses the existing fast-suite assertion definitions and runs the existing `tests/release-contracts.php`; it adds no runtime dependency or new product behavior. No databases, provider calls, emails or browser sessions were needed. The synthetic acceptance case tests the verifier's format/logic; it does not represent an authentic completed GitHub run. CI manifest fields are workflow metadata, not a cryptographic signature. The release owner must independently verify the authenticated workflow/artifact origin and matching revision/run.

## Prerequisites and execution order

| Prerequisite | Current status / required evidence |
|---|---|
| REL-002 | OPEN: exact bundled Inter provenance and complete corresponding notice |
| REL-003 | OPEN: fresh public credit opt-in and reviewed preservation of existing preferences |
| REL-004 | OPEN: actual provider/privacy/retention/lifecycle/support/update disclosures |
| REL-005 | OPEN: honest support floor, prerequisites and completed supported matrix |
| REL-006 | OPEN: deterministic ZIP metadata; repeated current builds still differ |
| REL-007 | OPEN: intentional package inventory and source-specific Plugin Check triage; this slice does not reclassify a valid inventory as a clean submission check |
| M1–M5 findings | 37 IMPLEMENTED locally, zero CLOSED; their broader qualification limits remain recorded in each slice |
| M6 | Ownership/extraction exit review remains outstanding; no unrelated refactor is performed as a release shortcut |
| M7 | Workload sign-off, supported runtime/cache/scheduler/topology, lifecycle/recovery and provider acceptance qualifications remain outstanding |
| Clean candidate and remote CI | BLOCKED until the complete reviewed source/dependency tree can be reconciled and committed, then the configured six-job matrix and release job succeed for that exact revision |
| Final artifact | NOT RUN: final native/HTTP/browser/upgrade/restore/output/Plugin Check and promotion/rollback rehearsal against installed bytes |

Resolve the remaining release findings and M6/M7 qualifications first. Then review the complete diff and dependency inventory, commit the intended complete candidate, run the supported matrix, build from that revision, retain authentic CI manifests/checksums, verify reproducibility and qualify the exact installed artifact. Use the existing promotion verifier after these inputs exist. Production deployment remains a separately authorized final action.

The builder's automated blocker list covers inventory, Git cleanliness and CI metadata. It does not automatically certify all audit findings, notices, provider acceptance, Plugin Check triage or M6/M7 exits. Those reviewed gates must remain explicit; setting environment flags or changing a manifest cannot substitute for them.

REL-001 remains **IN PROGRESS, prerequisite-blocked**. Backlog: **6 OPEN / 1 IN PROGRESS / 37 IMPLEMENTED**; no VERIFIED/CLOSED items are inferred from this checkpoint. Next executable finding: **REL-002**. Revisit REL-001 at the final release boundary.
