# Host and provider acceptance repairs — 1.102.88

Author / Builder: Shashi. Date: 2026-10-23.

**Production acceptance remains open. No deployment was performed.** Authenticated host observations and business data are retained only in the ignored local `build/host-provider-acceptance-1.102.88/` handoff. They are excluded from the public commit and release package. This document records reproducible code repairs, synthetic tests and general operator gates.

## Candidate repairs

1. **Retention continuation:** Action Scheduler treats both pending and running actions as scheduled. A callback checking its own unique hook suppresses its successor. Real Action Scheduler 4.2.0 reproduces the failure before the patch. Check only pending successors, serialize check/enqueue with a database advisory lock and disable AS's pending-or-running uniqueness for this fenced enqueue. Retain WP-Cron fallback on failure/contention. Flag stale incomplete checkpoints with no runnable continuation after five minutes.
2. **GA4 client identity:** Google debug validation rejects the old `idlm.*` format; click IDs are also not GA4 client IDs. Emit a stable numeric CRM identity and retain authentic click IDs as parameters. This is a CRM identity, not browser-session association. Browser/session stitching still requires authentic GA identifiers if required.
3. **GA4 event labels:** Debug validation rejects the built-in space-separated event labels. Send spaces as underscores on the GA4 wire (`CRM_Qualified_Lead`, `Closed_Won_Interior_Project`). Reject unsupported syntax before claiming/sending. Preserve Google Ads CSV labels and original receipt/log identities, so accepted/uncertain conversions do not become eligible for replay. Both corrected default payloads pass strict non-ingesting debug validation; arbitrary future provider settings still require validation.
4. **Interakt encoded keys:** The documented dashboard Secret Key already encodes username:password. Send it once, retaining the historical raw-username form for existing configurations. Synthetic tests cover both forms and existing rotation/replay behavior.

## Verification

[Committed synthetic evidence](remediation-2026-10-23/host-provider-acceptance/): 392 fast tests, five runtime prerequisites, 15 focused native retention/recovery tests, 11 focused native CAPI tests and four real Action Scheduler acceptance groups pass. The before-patch scheduler failure is preserved separately. Full remote CI and package verification must match the repaired artifact's exact revision.

The real-AS test covers running/pending/complete transitions, a 100-row boundary, unexpired synthetic data, terminal completion, pending/running/fallback/fresh/missing-timestamp health, pending deduplication, an independent-session scheduling fence and rejected enqueue. CI pins the official 4.2.0 archive hash and runs this step with latest WordPress, since AS requires WordPress 6.9+. The matrix also includes PHP 8.3, alongside 7.4/8.1/8.4/8.5.

## Operator acceptance gates

- Verify tenant CPU/RAM/storage quotas, PHP runtime/extensions, dedicated worker and OPcache capacity under the selected workload. Host-visible shared totals do not prove tenant capacity. Do not reset a shared OPcache pool.
- Prove a system runner executes due WordPress/Action Scheduler and WP-Cron fallback work every minute independently of web traffic. Verify retention reaches completion, queue age stays bounded and alerts have an owner.
- Rehearse an actual-site database/private-file-chunks/media/config/key backup in an isolated restore/upgrade target. Record RPO/RTO and rollback ownership; synthetic recovery does not establish actual-site recoverability.
- Use designated noncustomer email/WhatsApp recipients and provider test destinations. Obtain inbox, ingestion, audience/feed-job and delivery-webhook receipts. Do not replay accepted or uncertain historical work.
- Perform a long staging soak. A production observation window follows deployment and cannot be completed beforehand. Deployment remains unauthorized.

Provider contracts: [Google debug validation](https://developers.google.com/analytics/devguides/collection/protocol/ga4/validating-events?client_type=gtag) never ingests events or verifies API secrets; [Brevo account authentication](https://developers.brevo.com/docs/quickstart) does not establish inbox delivery; [Interakt authentication](https://www.interakt.shop/resource-center/user-and-event-track-api/) specifies the encoded dashboard key. A Meta Pixel read does not prove CAPI write scopes or event ingestion.

The prior [CI qualification](CI-QUALIFIED-BUILD-1.102.88.md) and [reference load/restore evidence](M6-M7-RELEASE-CANDIDATE.md) retain their original artifact identities and limits. They do not attest the changed artifact's installed identity. Public branch push and CI were authorized; authenticated production diagnostics were not approved for publication and remain local.
