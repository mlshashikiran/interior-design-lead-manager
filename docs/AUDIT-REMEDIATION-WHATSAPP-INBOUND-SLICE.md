# INT-006 — WhatsApp inbound batches and replay

Author / Builder: **Shashi**. Date: **2026-10-09**. Local candidate **1.102.67**, base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. Core **1.3.9**, routing **1.0.1**, quotation **2.5.4** and lifecycle receipt **1.1.0** are unchanged.

## Finding and source confirmation

The immutable [INT-006 finding](audit-2026-10-06/INTEGRATION-SECURITY-REVIEW.md#int-006--medium--meta-webhook-acknowledges-dropped-batch-messages-and-appends-replays) records first-entry/change/message handling, unconditional success after ignored messages, no provider replay claim and newest-lead phone-suffix association. The bounded Graphify query **INT-006 / handle_meta_webhook_request / process_incoming_whatsapp_message** located the audit and callback. Source confirmed the dropped batch and suffix/replay paths. M4/T07/T09 require complete relevant-message handling, consistent identity, concurrent replay protection and durable acknowledgement.

The [official Interakt inbound example](https://www.interakt.shop/resource-center/interakts-webhooks/) identifies received messages through `data.message.id` and the customer through `channel_phone_number`. That provider documentation also describes a three-second acknowledgement expectation. Local synthetic checks do not qualify provider retry timing or that production latency requirement.

## Parsing, identity and acknowledgement

Existing REST routes/HMAC headers remain. POST callbacks read current enablement and secret directly from primary SQL, validate configured scalar settings and authenticate the raw body before JSON/persistence. Bad signatures are 401; disabled/unconfigured callbacks remain 403, unavailable settings 503.

Meta iterates every `entry[].changes[].value.messages[]`; status-only/non-message changes create no activity. Text, button, interactive button/list replies and generic media/unknown-type placeholders are supported. Interakt received messages retain existing media formatting without storing the separate media URL field, and use their provider message ID. Missing/malformed IDs, phone/text/type/structure fail 400. Requests over **1 MiB or 100 messages** fail 413. The complete parsed message list is validated before writes; malformed later messages cannot leave an earlier activity behind.

`IDLM_WhatsApp_Inbox` shares the canonical intake phone normalizer. Explicit country digits remain intact; the existing India-local ten-digit and trunk-zero convention is retained. SQL removes common phone punctuation/whitespace and compares full numbers or those explicit India-local aliases. It never selects the newest suffix match. Duplicate records resolve to a nonduplicate canonical lead only when that canonical phone matches. More than one canonical match, a missing match, or a conflicting canonical phone is an explicit 409; no activity or receipt is written for that message. Unsupported historic phone formatting may require repair. No automatic lead creation or guessed association occurs.

Messages commit independently. Responses report `processed`, `replayed`, `failed`; **200 only follows all relevant messages committed or already acknowledged**. Unmatched/ambiguous/changed-ID content returns 409; busy or failed required persistence returns 503. Partial counts are explicit, and replaying a partial batch resumes missing work without appending its earlier committed messages. Responses disclose no contact/message body or lead identity. Unknown/ambiguous messages are not durably quarantined: the provider must retain/retry them while an operator repairs identity. Retry-window expiry is not qualified by this slice.

## Atomic replay receipts and recovery

A nonautoload `idlm_whatsapp_receipt_<sha256(provider|message-id)>` option stores version, a site-keyed fingerprint of normalized phone/content, activity ID and received timestamp. There is no raw message ID/phone/text or credential in that receipt. Provider namespaces are separate; a reused ID with changed normalized content requires review instead of returning false success or appending another activity. Site authentication-salt rotation likewise causes fingerprint review.

A provider-ID MySQL advisory lock and `wp_options` unique option name serialize cooperating writers. The existing `IDLM_Lead_Transaction` owner verifies InnoDB options, reads primary receipt state, locks the current canonical match, and checks activity insert, receipt insert/readback, recovery/session lease and COMMIT. Activity and receipt succeed or roll back together. Active foreign/nested message transaction contexts are refused. There is no remote call inside the transaction. A process exit before commit leaves neither activity nor receipt; exit after commit but before acknowledgement leaves both, so replay only acknowledges it. A conflicting simultaneous request may get a retryable 503.

Receipts remain independent of activity pruning and as tombstones after reset/deletion. Backup export/import excludes them; same-site restore preserves existing receipt state, preventing a local backup from rewinding external acknowledgement. Receipts are not reconstructable after complete database loss or a fresh/foreign restore. Historical activities with no provider ID cannot be backfilled reliably; the first legacy replay after installation may append another activity. Receipt volume/retention and complete privacy-erasure policy remain explicit qualification work. No physical columns/schema versions change.

The established public reply helpers now accept an optional third provider message ID and refuse ID-less calls. No other repository callers were found. Inbound HMAC routes supply the ID; there is no trusted legacy bypass that can silently append unclaimed provider activity.

## Executed verification

- **330 fast / 0 failed**, including six new INT-006 cases.
- **10 focused native / 0 failed** (fresh schema plus nine INT-006 cases); **260 full native / 0 failed** on final runtime source.
- **6 focused actual signed HTTP / 0 failed**, then **404 full HTTP / 0 failed**, with no HTTP diagnostics.
- All **15 changed PHP files** pass syntax checks; whitespace validation is recorded in final evidence.

Native and HTTP checks cover multiple entries/changes/messages, status-only changes, text/buttons/lists/media, missing IDs/malformed shapes/batch bounds, wrong HMAC/disabled state, complete no-write rejection, explicit partial failure and retry, international shared suffixes, multiple canonical matches, duplicate-to-canonical association, provider namespaces, changed content, warmed old secret independently rotated in primary SQL, activity/receipt/COMMIT faults and nontransactional options, competing independent workers, process exit 77 before/after commit, active caller transaction and invalid receipt, pruning, backup exclusion and same-site restore/reset replay protection. Fast tests cover the same principal contracts with doubles; actual native workers establish database concurrency and transaction behavior.

The initial native run passed seven cases and failed three subsequent fixtures because a previous independent secret-rotation case left the option cache stale; fixture `update_option()` saw the old cached value and skipped resetting primary SQL. Clearing the fixture cache before reset corrected those fixtures. [Initial log](remediation-2026-10-09/whatsapp-inbound-slice/native-initial.log) is retained. Full native stderr contains only existing deliberate identity-review write-failure diagnostics; focused native/HTTP stderr is empty. Fast stderr retains its existing synthetic missing-phone warning. [Final fast log](remediation-2026-10-09/whatsapp-inbound-slice/fast-final.log), [full native log](remediation-2026-10-09/whatsapp-inbound-slice/native-full.log), [full HTTP log](remediation-2026-10-09/whatsapp-inbound-slice/http-full.log), [commands/hashes/cleanup](remediation-2026-10-09/whatsapp-inbound-slice/verification.json).

Environment: PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, no persistent cache. Only newly owned disposable `idlm_test_*` schemas/content and synthetic provider signatures/identities were used. Outbound HTTP/mail was intercepted before WordPress install. Approved sandbox escalation enabled the owned socket-only server. Configured-site data was untouched. Owned schemas/content were removed by fixture shutdown, the socket-only server stopped and its temporary data erased. No real provider send, commit, package or deployment.

## Qualification and next work

**INT-006 is IMPLEMENTED, not CLOSED**. Backlog: **11 OPEN / 33 IMPLEMENTED**, none CLOSED. Live Meta/Interakt delivery/retry timing, production latency/volume, persistent-cache/distributed database behavior, complete provider event/media variants, legacy message history, cross-provider identity reconciliation, receipt retention/erasure and exact artifact/runtime/CI qualification remain pending. Full-phone matching uses a normalized expression, so the result cap does not prove indexed lookup or production latency. Whole T07/T09/M4 groups remain open. The graph refresh is scoped source structure plus explicit document headings; no full semantic/report regeneration.

Next: **INT-007 — disabled Google audience pull feed**.
