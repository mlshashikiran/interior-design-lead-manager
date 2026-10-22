# REL-003 — optional public plugin credit

Builder: Shashi. Date: 2026-10-09. Local candidate: **1.102.73**. Schemas unchanged. **IMPLEMENTED, not CLOSED.**

## Finding and policy

The immutable [REL-003 finding](audit-2026-10-06/RELEASE.md) identified a default-on public credit in the shortcode sign-in page and workspace. [WordPress directory guideline 10](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#10-plugins-may-not-embed-external-links-or-credits-on-the-public-site-without-explicitly-asking-the-users-permission) requires optional public credits to start disabled and clearly ask for opt-in. This slice addresses the credit behavior; it does not certify directory compliance.

`IDLM_Settings::white_label_defaults()` now returns `show_plugin_attribution='0'`. An absent option or a partial saved branding array also reads as off. Existing saved scalar on/off choices retain their prior interpretation, and rendering/upgrade does not rewrite the branding option. Non-scalar stored values safely read as off. An authorized settings save enables credit only for the submitted checkbox string `1`; omitted, empty, other or array values disable it.

There is no historical consent record in this option. A saved `1` is preserved for compatibility and **is not evidence that a user explicitly opted in**. Where historical intent is unknown, the site administrator should review the checkbox and save the intended choice before public release. No automatic consent claim, database migration or preference rewrite is made.

## Shared implementation

The shared white-label template identifies the checkbox, links its explanation with `aria-describedby`, and says the credit is optional, off by default, and shown on the sign-in page/workspace. The existing settings renderer/controller serves admin and frontend contexts. Existing capability and nonce gates remain in force.

Both actual public templates already require the exact normalized value `1` before rendering the credit, so the shared default fixes both. Custom branding and footer text survive reads and upgrade. The existing shared settings JavaScript previews checkbox changes and uses the same defaults when restoring branding; the reset remains unsaved until **Save settings** is chosen. No production JavaScript or CSS change was needed.

The main header, runtime constant, readme stable tag, release contract and scalability candidate authority agree on 1.102.73. Public/development changelogs retain Shashi attribution.

## Executed verification

Evidence: [verification.json](remediation-2026-10-09/attribution-opt-in-slice/verification.json), [baseline](remediation-2026-10-09/attribution-opt-in-slice/baseline-fast.log), [fast suite](remediation-2026-10-09/attribution-opt-in-slice/fast-final.log), [actual HTTP](remediation-2026-10-09/attribution-opt-in-slice/http-final-clean.log), and [mobile screenshot](remediation-2026-10-09/attribution-opt-in-slice/mobile-default-off.jpg).

- Before the repair, three new regressions reproduced default-on public rendering/normalization: **353 PASS / 3 expected FAIL**.
- Final complete fast suite: **356 PASS / 0 FAIL**, including the four new attribution contracts and existing release/version contracts. Its seven displayed warnings match the baseline fixture warnings; no new warning is attributed to this slice.
- Final disposable WordPress HTTP slice: **4 PASS / 0 FAIL** on PHP 8.4.14, WordPress 7.1.3 and MySQL 8.4.0, WP-Cron, persistent cache off. It uses real cookie sessions, settings controller/nonces, independent public requests and primary SQL assertions. Cases cover absent/partial defaults, existing on/off choices, real opt-in/opt-out and malformed submissions, denied representative/invalid nonce, and an empty affected-request PHP diagnostic log.
- Browser verification of the actual shared settings template/JavaScript at **1280×900** and **390×844**: default unchecked/preview hidden; Space enables the checkbox and preview; restoring default branding returns it to off; mobile Space also toggles both; help fits and there is no horizontal overflow. The anonymous desktop shortcode sign-in page also omits credit. Browser changes were returned to the default unsaved state without saving; the viewport override and owned tab were removed.
- Every changed PHP file passes syntax checks; `git diff --check` passes. Scoped Graphify refresh records current PHP structure and document headings, retaining unrelated graph content.

The first HTTP fixture run read a cached parent-process option after an independent request saved it. Assertions now read primary SQL; the final fixture passes. The initial browser fixture also failed to load the shared settings JavaScript: its asset URLs pointed to the synthetic hostname and its queue lacked the settings handle. The owned router now maps those assets to its loopback route and enqueues the real shared script. Neither issue required production behavior changes. Earlier failed logs remain as diagnostic provenance.

## Qualification and next item

This is local source/runtime evidence, not installed final-ZIP qualification. The accumulated native/full HTTP suites, supported runtime/cache/scheduler matrix, clean reviewed commit, matching remote CI and exact release artifact gates were not rerun or satisfied by this slice. Browser evidence covers the shared frontend settings context, not a separate exhaustive wp-admin or assistive-technology qualification. Remaining release prerequisites retain their existing status.

Only newly owned `idlm_test_*` databases/content, loopback HTTP fixtures and the dedicated socket-only MySQL server were used and cleaned up; outbound provider HTTP/mail is intercepted by the existing harness. Configured-site data was not loaded or changed. No commit, push, tag or deployment was performed.

Backlog: **4 OPEN / 1 IN PROGRESS / 39 IMPLEMENTED**. Next executable finding: **REL-004**; revisit REL-001 after its prerequisites and final release qualification.
