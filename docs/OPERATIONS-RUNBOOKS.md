# Operational Health and Initial Runbooks

Author / Builder: Shashi  
Implementation release: 1.99.6  
Status: Phase 7 health baseline implemented; external alerting and full runbook rehearsal pending

## Purpose

The administrator-only **Lead Manager → Settings → System Health → Operational Health** report provides a read-only, aggregate view of conditions that can stop or delay CRM work. It never returns credentials, contacts, request/response payloads, hosts, database names, or storage paths.

The report checks:

- database reachability and independent core/quotation schema versions;
- recurring audience/fallback jobs and the sensitive-data retention schedule;
- presence of required credentials for enabled integrations;
- WordPress upload-storage availability and the active read-model cache backend;
- IDLM Action Scheduler pending/failed counts and oldest pending age when Action Scheduler is active;
- error telemetry and failed CAPI/audience deliveries recorded during the previous 24 hours.

WP-Cron remains a supported fallback, but it does not expose a reliable queue backlog. Its queue check is therefore informational rather than a false zero.

## Thresholds

| Signal | Healthy | Warning | Critical |
|---|---:|---:|---:|
| Oldest Action Scheduler pending item | under 5 minutes | 5–14 minutes | 15 minutes or more |
| Failed Action Scheduler items in 24 hours | 0 | 1–9 | 10 or more |
| Telemetry + integration faults in 24 hours | 0 | 1–9 | 10 or more |
| Database, schemas, recurring jobs, retention, enabled credentials, upload storage | available/current | — | unavailable/incomplete |

These are initial conservative thresholds derived from the Phase 0 queue-lag target. Replace them only after target-profile load evidence and operator sign-off.

## Critical-health triage

Owner: production operations; application escalation: Shashi.

1. Record the build version, report timestamp, failed check labels, aggregate counts, and oldest queue age. Do not copy secrets or raw CRM/integration payloads.
2. Stop deployment and non-essential bulk work. If database, schema, or storage is critical, pause CRM writes at the traffic/application layer.
3. Confirm WordPress, database, media storage, cron/queue runner, and protected configuration from the infrastructure monitoring system.
4. Follow the specific runbook below. Preserve accepted webhooks and queued actions; do not delete queue rows or improvise destructive SQL.
5. After recovery, confirm two consecutive healthy checks, run one controlled test lead lifecycle, and record the incident and safe-retry decision.

## Schema mismatch

1. Pause writes and take a verified database backup.
2. Confirm the deployed plugin build, `IDLM_DB_VERSION`, quotation schema version, and stored schema markers without editing them manually.
3. Rehearse the same upgrade against the production-like clone. Run the idempotent installer and verify interrupted-run recovery there first.
4. Deploy the tested forward repair. Do not roll application code back across an incompatible schema.
5. Verify schema health, lead reads/writes, quotation save/numbering, queue processing, and audit records before reopening traffic.

## Queue backlog or failures

1. Confirm the Action Scheduler runner is active and check its worker/claim logs. A 5-minute oldest age is warning; 15 minutes is critical.
2. Group failed IDLM actions by hook and error class. Inspect only redacted summaries; keep raw third-party payloads in access-controlled systems.
3. Resolve database/cache/network/credential pressure before increasing worker concurrency.
4. Retry only actions whose handler is confirmed idempotent. Never retry quotation numbering or external delivery by directly duplicating a request.
5. Verify oldest age is falling, failed count is stable or decreasing, and third-party rate limits remain within policy.

## Credential or third-party outage

1. Distinguish an incomplete enabled-integration credential check from a remote-service failure recorded in CAPI/audience logs.
2. For an incomplete credential, restore it from the protected environment/secret store or rotate it using the established settings control. Never paste it into logs, tickets, URLs, or JavaScript.
3. For a remote outage, keep bounded queued work, respect provider retry/rate-limit guidance, and disable only the affected optional integration when business approval exists.
4. After recovery, send one controlled test event, verify its success log, and retry only confirmed idempotent failed work.

## Remaining Phase 7 work

- Add correlation IDs and structured redacted events across webhooks, lifecycle writes, queued deliveries, and callbacks.
- Add route/action latency, database timing/fingerprint, webhook outcome, cache, capacity, backup-age, and storage-usage signals.
- Connect warning/critical checks to an external monitoring system with tested owners and notifications.
- Complete and rehearse deployment, webhook, database restore, integrity repair, and rollback runbooks.
