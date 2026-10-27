# INT-010 — protected platform credentials

Author / Builder: Shashi  
Candidate: **1.102.71**, schemas unchanged  
Status: **IMPLEMENTED locally, not CLOSED**

The immutable [INT-010 finding](audit-2026-10-06/INTEGRATION-SECURITY-REVIEW.md) identified plaintext platform options, prefilled settings, copied Interakt queue credentials and URL authentication. This slice extends the existing secret store and checks the actual settings, dispatch, webhook, diagnostic and recovery boundaries. It preserves unrelated working-tree changes.

## Storage and migration policy

| Existing option | Optional server-owned constant |
|---|---|
| `idlm_meta_access_token` | `IDLM_META_ACCESS_TOKEN` |
| `idlm_google_api_secret` | `IDLM_GOOGLE_API_SECRET` |
| `idlm_linkedin_access_token` | `IDLM_LINKEDIN_ACCESS_TOKEN` |
| `idlm_interakt_api_key` | `IDLM_INTERAKT_API_KEY` |
| `idlm_interakt_webhook_secret` | `IDLM_INTERAKT_WEBHOOK_SECRET` |
| `idlm_meta_webhook_verify_token` | `IDLM_META_WEBHOOK_VERIFY_TOKEN` |
| `idlm_meta_webhook_app_secret` | `IDLM_META_WEBHOOK_APP_SECRET` |

`IDLM_Secret_Store` retains compatible `idlm:v1:` AES-256-GCM ciphertext. New values, including explicit empty clears, are encrypted and nonautoload. The key derives from protected `IDLM_SECRET_KEY` when configured, otherwise the existing WordPress key/salt material. There is no filesystem-path key fallback. Encryption/key unavailability refuses writes and plaintext migration; unreadable or damaged ciphertext is preserved and unusable rather than replaced with a generated credential.

Every platform read uses primary SQL, bypassing warmed option caches. The seven-option bootstrap migration is bounded and repeatable. A compare-and-swap replaces only the plaintext originally read, invalidates caches and verifies committed decryptable ciphertext before returning a usable value. Concurrent rotation wins. Failed persistence leaves the original value for a later retry and returns no credential. Environment constants override stored values, including empty revocation, and prohibit settings writes; remaining legacy plaintext is still encrypted. There is no platform completion marker to advance incorrectly. Scoped migration verifies all three stored credentials before deleting the shared legacy key or advancing its existing marker, and records MCP fingerprints only after verification.

Changing WordPress salts without a stable `IDLM_SECRET_KEY`, changing that key, or restoring ciphertext without its original key makes existing values unreadable. Before changing key material, securely retain the old material and arrange replacement credentials. Deploy replacement environment constants or explicitly re-enter each credential using the new key; alternatively restore the matching original key material. Setting a new `IDLM_SECRET_KEY` does not automatically re-encrypt existing salt-derived ciphertext. No recovery path guesses the old value or silently resets it. Encrypting options protects database-only disclosure; an attacker with both the database and server keys can decrypt them.

## Settings and queued work

All seven platform settings are blank password fields for administrators too. Blank or omitted input retains the saved credential; entering a value replaces it; a separate clear checkbox writes authenticated empty ciphertext. Array input and simultaneous replacement/clear are refused. Capability and nonce checks precede any credential write. Environment ownership, unavailable encryption and failed storage produce a generic error without echoing input. The existing form saves per field, not as one transaction: a later failure or unrelated white-label validation can leave earlier credential changes committed. Correct the failure and retry deliberately. Existing scoped webhook/MCP/feed reveal controls retain their established behavior.

Ordinary Interakt callbacks continue to accept one opaque dispatch ID and load current credentials at execution. Bootstrap removes at most 100 obsolete copied-key WP-Cron events, then scrubs at most 100 matching Action Scheduler records per invocation. The AS path checks the physical columns, removes old `args`/`extended_args`, preserves action IDs, hook, status and schedule, and verifies each update before advancing a non-secret cursor. Valid opaque jobs and unrelated hooks remain. Refused legacy AS actions retain a redacted argument rather than becoming dispatchable. Failed writes stop cursor progress; another bootstrap retries. This is progressive cleanup, not an immediate promise that every historical queue row is clean. The AS native fixture exercises compatible physical columns; installed AS versions still need qualification. The plugin JSON backup already excludes credential-like options and scheduled jobs; it is not the audit's claimed secret leak.

## Authentication and local diagnostics

Meta CAPI, audience uploads and connection tests now use Bearer headers; tokens leave their URLs. LinkedIn retains Bearer authentication and Interakt retains Basic authentication. Blank Meta/LinkedIn connection-test input uses the saved credential. All covered credentialed transports disable redirects and scrub raw, URL-encoded, JSON-escaped and Basic-encoded secret echoes from returned errors/responses before plugin activity, delivery logs and telemetry persistence. Thrown transport errors become generic uncertain outcomes. Retention's recursive diagnostic redactor additionally removes credential fields and URL/auth patterns.

Google Measurement Protocol still requires an encoded `api_secret` query parameter: [Google's sending-events contract](https://developers.google.com/analytics/devguides/collection/protocol/ga4/sending-events). Meta verification still receives the provider's query token. Plugin-owned diagnostic redaction cannot control third-party `http_api_debug` listeners, `SAVEQUERIES`, database query logs, upstream proxies, old database/AS exports or logs already produced. Compare-and-swap SQL necessarily compares the old plaintext during migration; its wpdb error display is suppressed. Operators must restrict/redact external traces and rotate credentials exposed historically. No claim of universal URL secrecy or retroactive erasure is made.

## Executed verification

PHP **8.4.14**, WordPress **7.1.3**, MySQL **8.4.0**, WP-Cron, persistent cache off. Tests use new owned `idlm_test_*` databases and isolated content, intercept HTTP/mail and make no real provider calls or configured-site data changes. [Machine evidence, hashes and commands](remediation-2026-10-09/secret-store-slice/verification.json).

| Check | Result / scope |
|---|---|
| `php tests/run.php` | **350 PASS / 0 FAIL**; six new INT010 contracts, including subprocess environment ownership, disabled OpenSSL, salt rotation and missing key |
| `php tests/integration/run.php` | **306 PASS / 0 FAIL**; actual primary SQL, independent rotation/revocation, checked migration, providers, settings, queue cleanup and thrown transport |
| `IDLM_TEST_FILTER=INT010 php tests/integration/run.php` | **12 PASS / 0 FAIL**; eleven slice children plus fresh schema |
| `php tests/integration/quotation-http.php` | **425 PASS / 0 FAIL**; cookie-authenticated settings and AJAX, plus inherited regression suite and diagnostic gate |
| `php tests/integration/quotation-http.php --secret-store-only` | **5 PASS / 0 FAIL**; no settings disclosure, actor/nonce denial, retain/replace/clear, malformed input and saved-credential connection tests |
| Optional AS and inbound/task contracts | **PASS**, intercepted adapter coverage |
| Scoped PHP lint, shared JS syntax, whitespace checks | **PASS**; file inventory in machine evidence |

Synthetic migration-write failure reproduces the original plaintext fallback (**344 PASS / 1 FAIL** baseline). New native cases verify seven ciphertext/nonautoload options; failed-write retry; damaged scoped ciphertext retaining legacy key/marker; an independent mysqli rotation during migration; warm-cache revocation; all three CAPI adapters and required Google encoding; audience/Interakt echo redaction and backup exclusion; settings clear/write failures; old Cron jobs; 101 AS legacy records with checkpoint failure/retry and preservation of valid/unrelated actions; and a thrown transport exception.

Intermediate fixture failures are retained in logs: the fast wpdb double initially lacked compare-and-swap option behavior; native denial injection needed both normal and AJAX `wp_die` handlers; the HTTP fixture's core footer produced a deprecation and was changed to print its actual scripts; an older CAPI interceptor initially overrode the new connection-test fixture and was reordered. Final reruns follow those corrections. Seven fast fixture warnings match the normalized multiset in prior INT-009 evidence; no new warnings. The full HTTP unexpected-diagnostics gate passes.

Actual in-app browser observations: Conversion Tracking displays three blank password fields and clear controls at 1280×900 and 390×844 without horizontal overflow. Automation displays four blank platform fields and clear controls; its wider audience-rule table causes horizontal overflow at 390 px and stretches that panel's fields. Full Automation mobile qualification does **not** pass. No browser settings were submitted; the fixture's synthetic POST tests cover persistence. Viewport was restored. Browser observations are recorded as transcript evidence, not an exported screenshot artifact.

## Remaining qualification

Live provider account permissions and response variants, provider-required query tracing configuration, installed Action Scheduler schemas and historical queue volume, persistent caches/topologies, the supported PHP/WordPress/database matrix, key-management/rotation rehearsal, historic credential exposure, full Automation responsive qualification and the exact clean release artifact/CI remain open. No production deployment or credential rotation was performed.

INT-010 is **IMPLEMENTED, not CLOSED**; broader T07/T08/T09/T14/T20 qualification remains. Backlog: **7 OPEN / 37 IMPLEMENTED**. Next listed finding: **REL-001**, with M6/M7 and the earlier release prerequisites still required.
