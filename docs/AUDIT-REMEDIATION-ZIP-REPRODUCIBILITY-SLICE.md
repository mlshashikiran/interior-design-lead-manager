# REL-006 — reproducible release ZIP metadata

Author / Builder: Shashi. Date: 2026-10-11. Local candidate: **1.102.76**. Runtime behavior and schemas unchanged. **IMPLEMENTED, not CLOSED.**

## Finding and current-source verification

The immutable [REL-006 finding](audit-2026-10-06/RELEASE.md) reported byte differences from the root-directory timestamp. Current `IDLM_Release_Artifact::build()` added that empty directory without checking its creation or normalizing metadata. Current source also did not normalize file times, UNIX modes or compression settings. The existing collection path sorted filenames, but the public builder accepted inventory order as supplied.

Before changing the builder, twelve existing release contracts passed. A new regression failed with different SHA256 values for identical content after a delay and source time/mode/order changes. Two **455-file** full pre-fix packages built three seconds apart had identical paths and file contents but different ZIP bytes; independent Python inspection identified the **root directory timestamp** as the only timestamp difference. [Pre-fix comparison](remediation-2026-10-11/zip-reproducibility-slice/pre-fix-reproducibility.json) preserves that reproduction. Baseline file copies/hashes preserve the scoped pre-edit source; unrelated dirty/untracked changes were retained.

## Implementation and build contract

The existing builder now checks root-directory creation, sorts its local inventory copy, and checks normalization of **every ZIP entry** before finalizing the archive:

- Timestamp **946684800**, January 1, 2000 at 00:00 UTC under the pinned build timezone.
- UNIX directory mode **040755** for the root and regular-file mode **0100644** for packaged files.
- Explicit DEFLATE compression at level **9** for files.

Normalization failures throw a release build error; they do not produce a reported successful package. Paths, root directory, file bytes, required inventory, clean-tree/CI attestation and promotion safeguards remain intact. The checksum binds the resulting ZIP; the manifest still records actual build time and attestation, so separate manifests are intentionally not claimed byte-identical.

ZIP DOS time encoding uses the host timezone. CI packaging now explicitly sets **TZ=UTC**; use the same setting for manual comparisons. Reproducibility requires the same source inventory/bytes and packaging toolchain (PHP zip/libzip/zlib and compression behavior). This slice verifies local PHP 8.4.14/libzip 1.11.4 and does not certify byte equality across unrelated dependency versions or host timezones. No new runtime dependency is introduced; the builder already requires ZipArchive.

## Executed verification

[Machine evidence](remediation-2026-10-11/zip-reproducibility-slice/verification.json) records commands, source hashes, runtime, scenario IDs and artifact checks:

- **13 focused release contracts PASS**. The new T22-A-REL006 case builds real ZIPs across a greater-than-two-second delay, changes source timestamps/permissions and input order, compares every archive byte and hash, asserts normalized root/file attributes, verifies stored contents and cleans the temporary fixture. Existing promotion, inventory, version, private-file, credential-signature, CI and font-notice contracts remain passing.
- Two independently launched, guarded **455-file** full candidate builds separated in time produce the **same ZIP bytes and SHA256**. A third build from an isolated copy with changed source times/permissions and reversed input order also matches. All three have the same paths and source bytes; independent inspection verifies every timestamp/mode, normalized root, archive contents and per-file identity.
- All changed PHP inputs pass syntax checks, workflow YAML parses with CI TZ=UTC, version authorities agree on **1.102.76**, and whitespace validation passes.
- The actual promotion verifier rejects the rehearsal for dirty Git and absent matching CI. This is expected safeguard behavior, not release approval. Packages remain `local_rehearsal_only` and `production_release_approved=false`.

No CRM/native/HTTP/browser suite was rerun for this isolated release-tooling change. No configured-site data, provider, email, commit, push, tag or deployment was touched. Runtime/cross-PHP/installed-artifact gates remain under REL-001 and REL-005. Temporary test/source-copy fixtures are removed; local rehearsal ZIPs and evidence remain for comparison. Scoped graph structure and explicit documentation headings are refreshed; unrelated graph content is retained.

## Remaining gates and next work

REL-006 is **IMPLEMENTED, not CLOSED** pending the clean exact candidate, successful matching CI and final release qualification. Cross-toolchain byte reproducibility is not established. **REL-007** packaging inventory/Plugin Check triage is next; REL-001 remains in progress behind M6/M7 and final release prerequisites.

Backlog: **1 OPEN / 1 IN PROGRESS / 42 IMPLEMENTED**.
