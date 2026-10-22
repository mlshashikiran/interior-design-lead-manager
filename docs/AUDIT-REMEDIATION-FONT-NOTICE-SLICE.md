# REL-002 — verified Inter font notice and package inventory

Author / Builder: Shashi  
Candidate: **1.102.72**, schemas unchanged  
Status: **IMPLEMENTED locally, not CLOSED**

## Provenance and matching license

The immutable [REL-002 finding](audit-2026-10-06/RELEASE.md) identified five bundled Inter fonts with copyright and an OFL URL but no complete distribution license. The focused Graphify “REL-002 Inter font license” lookup located that finding; existing font CSS, binary metadata and release inventory source were then verified.

All five local `inter-latin-{300,400,500,600,700}.woff2` files are identical: **48,432 bytes**, SHA-256 `c940764593d0fe5d596be327ca7558855e018039fb78509aa21921fd3644c3e4`. FontTools reports Inter Regular, version **4.001**, embedded revision `git-66647c0bb`, a weight axis **100–900** and 518 glyphs. Their names do not identify five distinct static fonts; existing CSS selects the five weights from identical variable-font copies. This slice preserves that layout and all original font/CSS bytes.

The embedded revision resolves through the official Inter repository to [commit 66647c0bbbe41a850d79d9c76fb13add3378940f](https://github.com/rsms/inter/commit/66647c0bbbe41a850d79d9c76fb13add3378940f). The official [Google Fonts CSS response](https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap) identifies a Latin subset whose [gstatic WOFF2](https://fonts.gstatic.com/s/inter/v20/UcC73FwrK3iLTeHuS_nVMrMxCp50SjIa1ZL7W0Q5nw.woff2) matches every local file byte for byte. The historical download procedure was not recorded; the present provider distribution match and upstream embedded identity are verified without asserting a reconstructed acquisition history.

`assets/fonts/OFL.txt` is the complete unmodified [license from that exact upstream revision](https://github.com/rsms/inter/blob/66647c0bbbe41a850d79d9c76fb13add3378940f/LICENSE.txt), including its copyright notice. It is **4,380 bytes**, SHA-256 `262481e844521b326f5ecd053e59b98c8b2da78c8ee1bdbb6e8174305e54935a`. Authorship remains with The Inter Project Authors; Shashi is attributed only for the plugin's distribution notice and verification work.

## Implementation

- Add the complete license, a human-readable `assets/fonts/NOTICE.txt` and a machine-readable `assets/fonts/inter-provenance.json` mapping each file to its verified hash, source, revision and license.
- Require the three notice/provenance files, existing stylesheet and all five fonts in both builder inventory and independent candidate inspection through the shared release required-file list. An exclusion or missing resource fails packaging rather than silently dropping the notice.
- Add two T22 regression children: verified original font and complete license bytes, and refusal of missing font resources/notices. Existing release contracts remain in the ordinary fast CI suite.
- Publish the bundled notice locations in readme, align all five version authorities at 1.102.72 and record Shashi attribution in changelogs.

No UI, font binary, font mapping, runtime dependency, database, service request or schema behavior changed. FontTools and downloaded upstream evidence are verification tools only; plugin runtime does not acquire fonts or require FontTools.

## Executed verification

Local PHP **8.4.14**, ZipArchive and Python/FontTools. Official resources were fetched with certificate verification enabled. The initial Python HTTPS fetch failed due to its local certificate store; curl's verified TLS succeeded without disabling verification.

| Check | Result / evidence |
|---|---|
| New regressions before implementation | **10 PASS / 2 expected FAIL**: missing notice and unguarded omission reproduced |
| Focused existing release suite after fix | **12 PASS / 0 FAIL**; two REL002 children plus ten existing release contracts |
| Actual dirty rehearsal build | **PASS**, 453 files, aligned 1.102.72; complete license/notice/provenance and all fonts included |
| Fresh isolated extraction | **453 / 453 source-byte matches**; zero missing, modified or extra files; complete license hash and all font/CSS hashes independently match |
| Actual candidate promotion | **Expected rejection, exit 1**; dirty source and missing authentic matching CI remain REL-001 blockers |
| Changed PHP lint and whitespace | **PASS**; main bootstrap, two release PHP files and the owned focused runner |
| Original font/CSS preservation | **PASS**, identical to before-change hashes |

The focused runner reuses the existing assertion definitions and `tests/release-contracts.php`; it performs no provider application calls, sends no mail and uses no database or browser. A fresh filesystem extraction verifies actual packaged bytes, not installed WordPress behavior. The temporary extraction was removed. [Commands, sources, hashes and limitations](remediation-2026-10-09/font-notice-slice/verification.json).

Rehearsal SHA-256: `890b5aa4744d19e1cdcf5ee5216d4a52ee215880ba05a59442841cbef7756f7f`. It is `local_rehearsal_only`, with production approval false. No source commit, push, tag or deployment occurred.

## Remaining release gates

This resolves the local missing Inter license/provenance/package requirement. It does not certify other dependency notices, advisories, Plugin Check diagnostics, supported runtimes or the full final artifact. REL-001 still requires a clean complete reviewed commit, authentic matching CI and final-artifact qualification; REL-006 byte reproducibility remains open. If font or upstream-license bytes change, repeat the source match and update the notices/hashes deliberately.

REL-002 is **IMPLEMENTED, not CLOSED**. Backlog: **5 OPEN / 1 IN PROGRESS / 38 IMPLEMENTED**. Next executable finding: **REL-003 — public plugin credit defaults on**.
