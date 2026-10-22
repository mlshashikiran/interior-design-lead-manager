# Executed matrix and coverage gaps

Author: Shashi. Date2026-10-06. [Machine-readable scenario matrix](scenario-matrix.csv), [calculated counts](evidence/scenario-counts.json).

## How to interpret results

PASS means the named assertion/observation succeeded in the recorded environment. FAIL means an expected safety/business/release invariant was violated; the tests deliberately expose existing behavior without fixing it. BLOCKED means a required execution gate cannot be established in this session. NOT RUN identifies remaining verification, including broad operation-specific dimensions for which only individual samples may have run. NOT APPLICABLE marks user-prohibited real-customer/production actions. No percentage code coverage or universal scenario guarantee is claimed.

Existing suites:174 fast assertions pass;17 native WordPress/MySQL tests pass; the same17 native tests pass against the exact installed artifact. Thus208 passing invocations represent191 distinct existing named cases, with17 environment repetitions. The fast suite contains both behavioral doubles and source-string assertions. Native tests cover actual DB/WordPress paths but not all handlers or provider deliveries. Audit probes add48 assertions:19 PASS,29 FAIL (core16 doubles +7 native, integrations13mock, quotations12double/helper cases; four formula-prefix examples counted separately). They are separate audit scripts and are not silently added to the existing runner.

The CSV includes additional bounded UI/release observations and explicit gap rows. Aggregate counts therefore combine different granularities and must not be called unique workflows. Syntax301PHP files and callback callability are separate static checks; syntax passing onPHP8.4 does not provePHP7.4 compatibility. See each row’s limit and reviewer report.

## Environments and safety

- Fast/double scripts bootstrap only isolated WordPress stand-ins; mock HTTP/SQL recorders and in-memory rows establish their limited invariants.
- Native suites use newly created `idlm_test_*` schemas on a dedicated MySQL 8.4 server with networking disabled, PHP 8.4.14, WordPress 7.1.2 and isolated uploads. They drop their owned schemas. No configured-site database or production credentials are used.
- Browser fixture installs the exact ZIP, synthetic users/leads and blocks mail/outbound HTTP. UI and export evidence is detailed in [UI-REVIEW.md](UI-REVIEW.md).
- Plugin Check runs on the extracted runtime after removal of the temporarily copied test harness; byte equality verified for all431 files with zero extras.
- Missing native credentials initially blocked the suite; disposable server setup resolved that local block. CI attestation and additional runtime versions remain blocked, not inferred from eventual local success.

## Reproduction entry points

Run from plugin root: `php tests/run.php`; `php tests/audit-integration-security.php`; `php tests/audit-quotation-probes.php`; `php docs/audit-2026-10-06/core-synthetic-probes.php`. Audit scripts intentionally return failure when invariants remain broken. Native: set explicit disposable DB credentials/host and `IDLM_TEST_WP_ROOT` where required, then `php tests/integration/run.php`. Read runner guardrails and tests/README first. `core-native-probes.php` additionally requires the guarded dedicated synthetic environment; do not point it at an existing site DB. Read-only evidence aggregation: `python3 tests/audit-evidence-report.py` while retained fixture/download files are available; it is a one-session audit utility, not general release automation.

## Required gaps and completion criteria

The matrix names16 operation families and seven meaningful dimensions for each: malformed/oversized/Unicode/date input; full roles/revocation/ownership; nonce/credential boundaries; actual HTTP object substitution; retries/replays/stale state; partial persistence/downstream recovery; concurrent/large-data behavior. Those complete dimensions remain NOT RUN even when a sampled assertion above passes. This makes the missing verification explicit without inventing a Cartesian product of irrelevant cases.

Before approval, replace each relevant gap with a bounded named test and actual evidence: supported historical upgrades preserving events/accepted quote data; native backup round trip and rejected omissions; migration interruption/retry; deactivation/reactivation with queued work; multisite scope decision/testing; missing-extension paths; WP-Cron/Action Scheduler delivery fallback and stalled jobs; representative large datasets and renderer resources; administrator/frontend scope/filter parity; accessible keyboard/screen-reader/mobile flows; headers/nonces/download ownership; privacy/erasure/review-queue resolution; historical document/settings/catalogue fidelity; provider sandbox acceptance using synthetic data if advertised functionality requires it.

Required CI PHP 7.4/8.1/8.4×WP 6.6/latest is BLOCKED because this dirty uncommitted candidate has no matching completed workflow attestation. ClaimedWP5.8 minimum is separately NOT RUN and is outside that CI matrix. No network release/promotion, customer-message, production restore or destructive configured-site test is authorized. This audit completes all review phases while leaving unresolved checks explicitly open and the release NOT READY.
