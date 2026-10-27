# M6 administrator connection controller — 1.102.85

Author / Builder: **Shashi**. Date: **2026-10-18**.

This bounded M6 extraction is implemented locally. **The complete M6 exit, M7 and REL-001 remain IN PROGRESS.** The finding backlog remains **43 IMPLEMENTED / one IN PROGRESS / zero CLOSED**. The private managed ZIP is a dirty-tree rehearsal, not an approved release.

## Ownership and preserved contracts

The focused graph query “connection-test handler ownership/callers” located the two existing facade methods in `class-admin.php` and `class-admin-ajax.php`. Source verification identified one self-contained platform-diagnostic handler, its authenticated action registration and both existing client request sites.

The complete **119-line method moves byte-for-byte** into `IDLM_Admin_Connection_Controller`. `IDLM_Admin::ajax_test_api_connection()` still delegates through `IDLM_Admin_Ajax::ajax_test_api_connection()`; the latter now delegates to the controller. `wp_ajax_idlm_test_api_connection`, registration order, request fields and JavaScript remain unchanged. Production and fast-test bootstraps load the new controller before the AJAX coordinator.

The controller retains the shared nonce and `manage_options` guard before credentials or transport. It owns existing posted/stored credential selection, Meta and LinkedIn connection requests through `IDLM_Secret_Store::request()`, local Google credential-format checks and JSON success/error messages. No anonymous action is added. The Google success message continues to describe the existing local credential check; it is not new proof of external Google acceptance.

[Byte/token comparison](remediation-2026-10-18/connection-controller-slice/extraction-parity.json) binds the exact original and moved method. No authentication, credential policy, provider API/version, error/status behavior, persistence, schema or UI change is bundled into this extraction. No new static exception, checker exclusion or diagnostic filter is added. The feature/project/architecture/interface maps and development guidance identify the controller and stable facade chain.

## Verification

[Machine evidence](remediation-2026-10-18/connection-controller-slice/verification.json) records local WordPress **6.6**, PHP **8.4.14**, MySQL **8.4.0**, WP-Cron and no persistent cache. The fast suite passes **385 cases**, including 16 release cases. The focused HTTP run passes **four groups**; the full HTTP/stdio suite passes **449 cases**, including the four new groups. All **eight changed PHP files** lint clean; whitespace checks pass.

Four new actual HTTP groups exercise:

- Invalid nonce, representative and anonymous denial, with unchanged CRM/jobs/outbound snapshots after administrative bootstrap.
- Missing/unsupported fields, short Google credentials, and posted/stored Google credential fallback, without persisting test credentials or exposing secrets.
- Meta Pixel/Custom Audience and LinkedIn success messages, stored Bearer headers, no secret in the URL and redirects disabled.
- API/transport failures, Meta subcodes and credential-echo redaction.

The groups run both with `--connection-controller-only` and in the accumulated HTTP/stdio suite. The existing protected-credential HTTP cases also remain in that suite. Providers and mail are intercepted; synthetic success/error responses test transport handling, not live service acceptance.

The initial focused denial snapshot correctly detected first-request WordPress administrative scheduling: `wp_update_user_counts` and `wp_update_comment_type_batch`. The fixture now primes `admin_init` through an unregistered action before the baseline; every denied connection request must still preserve the subsequent snapshot. No production code was changed to accommodate that fixture setup. The initial failure and isolated cron evidence are retained separately.

The source-matching **455-file ZIP** is SHA-256 `0b661ad141ae319894035f5fcae9abd9ead5478a1fcc0408b97617d350ff44cc`. The [default Plugin Check 2.1.0 gate](remediation-2026-10-18/connection-controller-slice/plugin-check/plugin-check-summary.json) passes on its extracted bytes with **zero errors, zero warnings and wrapper exit zero**. Raw stdout/stderr and normalized diagnostics are retained. Runtime tests exercise source; static checks exercise the extracted ZIP.

Full native tests are not repeated for this byte-identical AJAX handler move; the current source's actual HTTP dispatch exercises boot/load, registration, authorization and responses. The 334-case native result for 1.102.84 remains historical. Browser/UI, supported remote CI matrix, exact installed-artifact qualification and real provider acceptance are not newly claimed.

## Preservation and remaining gates

[Preservation](remediation-2026-10-18/connection-controller-slice/preservation.json) records all pre-existing source/evidence hashes. Historical generated diagnostics appended by the full HTTP harness are retained in this new slice and restored exactly at their old path. Unrelated working-tree edits and bundled resources remain unchanged. Owned temporary databases, configuration and server are removed. A scoped structural/document graph refresh retains unrelated relationships; no full semantic/HTML rebuild is claimed.

This completes one more M6 responsibility extraction, not the entire M6 ownership review. Integration auth/intake/feed, remaining AJAX/schema/reporting and client responsibilities still need bounded review where coupling is demonstrated. M7 workload/resource/topology sign-off and its runtime/cache/scheduler/lifecycle/recovery/provider qualifications remain outstanding. REL-001 still requires the complete reviewed clean revision, matching successful CI and qualification of the exact installed artifact. No commit, push, tag, publication or deployment occurred.
