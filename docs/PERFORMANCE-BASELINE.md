# Performance Baseline

Author / Builder: Shashi  
Status: Phase 0 started; targets await business/operations sign-off  
Release: 1.99.7  
Started: 2026-08-30

## Purpose

This document is the evidence record for the production and scalability plan. Measurements must use a sanitized production-like clone, identify the exact build and dataset, and exclude lead PII, credentials, raw request payloads, and integration responses.

## Target sign-off

The values below remain the plan's conservative validation envelope until the named business and operations owners approve or replace them.

| Dimension | Provisional target | Approved target | Owner | Status |
|---|---:|---:|---|---|
| Canonical leads plus duplicates | 250,000 | Pending | Pending | Not signed |
| Activities | 2,000,000 | Pending | Pending | Not signed |
| Tasks | 500,000 | Pending | Pending | Not signed |
| Touchpoints | 500,000 | Pending | Pending | Not signed |
| Retained integration/telemetry logs | 1,000,000 | Pending | Pending | Not signed |
| Quotations | 100,000 | Pending | Pending | Not signed |
| Concurrent authenticated users | 50 | Pending | Pending | Not signed |
| Sustained/burst webhook rate | 10/50 requests/sec | Pending | Pending | Not signed |
| Availability objective | Pending | Pending | Pending | Not signed |
| Recovery time/recovery point | Pending | Pending | Pending | Not signed |

## Service thresholds

| Surface | Provisional threshold | Signed threshold | Status |
|---|---:|---:|---|
| Lead-list API | p95 < 1.5s; p99 < 3s | Pending | Not signed |
| Lead mutation API | p95 < 750ms excluding third-party delivery | Pending | Not signed |
| Dashboard | p95 < 2.5s cold; < 1s warm | Pending | Not signed |
| Background queue lag | < 5 minutes normally | Pending | Not signed |
| Error rate | < 1% excluding validated client errors | Pending | Not signed |
| Integrity | No duplicate quote numbers, lost lifecycle events, or cross-user leaks | Pending | Not signed |

## Environment capture

Generate the aggregate inventory with:

```bash
php tests/performance/capture-baseline.php \
  --wp-root=/path/to/wordpress \
  --revision=commit-or-build-id \
  > performance-baseline.json
```

| Field | Value |
|---|---|
| Build revision | Pending committed revision |
| Plugin/core/quotation schema versions | Pending capture |
| WordPress/PHP/MySQL versions | Pending capture |
| Topology | Pending |
| CPU/memory limits | Pending |
| Database/cache/queue configuration | Pending |
| Dataset profile and generator seed | `small`, `expected`, or `maximum`; record the normalized `--seed` and emitted run ID |
| Capture artifact | Pending |

## Critical scenario results

The authoritative scenario IDs and transports live in `tests/performance/scenarios.php`. Each execution must record server and browser latency separately where applicable.

| Scenario | Dataset | Concurrency/rate | p50 | p95 | p99 | DB time / queries | Peak memory | Response bytes | Error rate | Evidence |
|---|---|---:|---:|---:|---:|---|---:|---:|---:|---|
| dashboard-period-refresh | Pending | Pending | — | — | — | — | — | — | — | Pending |
| lead-list-filter-sort | Pending | Pending | — | — | — | — | — | — | — | Pending |
| lead-detail-activity | Pending | Pending | — | — | — | — | — | — | — | Pending |
| kanban-stage-load | Pending | Pending | — | — | — | — | — | — | — | Pending |
| quotation-list | Pending | Pending | — | — | — | — | — | — | — | Pending |
| quotation-save | Pending | Pending | — | — | — | — | — | — | — | Pending |
| webhook-ingestion | Pending | Pending | — | — | — | — | — | — | — | Pending |
| lead-import-batch | Pending | Pending | — | — | — | — | — | — | — | Pending |
| lead-export | Pending | Pending | — | — | — | — | — | — | — | Pending |
| capi-retry | Pending | Pending | — | — | — | — | — | — | — | Pending |
| audience-sync | Pending | Pending | — | — | — | — | — | — | — | Pending |
| mcp-summary-query | Pending | Pending | — | — | — | — | — | — | — | Pending |

Phase 8 release 1.99.7 adds an authenticated aggregate-only HTTP runner for the dashboard, lead-list, and Kanban read scenarios. It is dry-run by default, refuses production, requires an exact execution confirmation, and does not expose URLs, identities, cookies, nonces, request values, response bodies, contacts, or credentials. No row in the table above becomes measured until an emitted result is reviewed together with server-side database/resource evidence.

A 2026-08-31 local tooling smoke used the current small local dataset and an administrator scope for one dashboard warm-up plus two measured requests at concurrency one. Both requests succeeded with 0% errors, 25.652 ms mean, 26.449 ms p95/p99, 3,511 response bytes, and 38.427 requests/second over the measured window. This tiny working-tree run validates authentication, transport, aggregation, and cleanup only; it is not a baseline or target result.

## Query-plan evidence

Capture `EXPLAIN` and, when supported and safe, `EXPLAIN ANALYZE` for the slowest and most frequent SQL shapes. Record the exact filters, sort tuple, dataset profile, rows examined, chosen indexes, database time, and anonymized plan output.

Run the aggregate-only Phase 2 optimizer capture on a non-production benchmark clone:

```bash
php tests/performance/capture-query-plans.php \
  --wp-root=/path/to/wordpress \
  --revision=commit-or-build-id \
  --dataset=small-baseline-01 \
  --start=2026-01-01 \
  --end=2026-12-31 \
  > query-plans.json
```

| Rank | Scenario / SQL fingerprint | Total time impact | Plan evidence | Decision |
|---:|---|---:|---|---|
| 1 | Pending | — | Pending | Measure before optimizing |

The 1.99.1 local tooling verification captured ten plans against approximately 117 lead rows. It found four full-scan steps and six filesort steps, but this dataset is too small to rank a bottleneck or approve an index. Repeat against the deterministic expected/maximum benchmark profiles and pair plans with endpoint database time before deciding.

## Capacity and operations inventory

| Signal | Baseline | Threshold | Evidence |
|---|---:|---:|---|
| Plugin table/index sizes | Pending capture | Pending | Aggregate JSON inventory |
| Autoloaded option bytes | Pending capture | Pending | Aggregate JSON inventory |
| WP-Cron IDLM event count | Pending capture | Expected registered schedule | Aggregate JSON inventory |
| Action Scheduler pending/failed/oldest | Pending capture | Queue lag < signed target | Aggregate JSON inventory |
| Backup age and restore duration | Pending rehearsal | Pending | Restore record |

## Ranked bottlenecks

No bottleneck is ranked until total-time and user-impact evidence exists.

| Rank | Bottleneck | Evidence | User impact | Proposed phase |
|---:|---|---|---|---|
| — | Pending first measurement | Pending | Pending | Phase 2–5 based on evidence |

## Release ownership checklist

| Responsibility | Owner | Backup | Status |
|---|---|---|---|
| Production release | Pending | Pending | Not assigned |
| Rollback decision/execution | Pending | Pending | Not assigned |
| Monitoring/incident response | Pending | Pending | Not assigned |
| Database/media backup and restore | Pending | Pending | Not assigned |
| Queue/integration operations | Pending | Pending | Not assigned |

## Phase 0 progress

- Completed in 1.99.7: guarded authenticated load runner and deterministic resilience matrix for three existing read-only CRM AJAX surfaces, with bounded concurrency, anonymous temporary response storage, aggregate p50/p95/p99/throughput/size/outcomes, and unsigned provisional threshold checks.
- Pending after 1.99.7: first reviewed small/expected/maximum results, per-role runs, server-side database/resource signals, write/concurrency drivers, failure injection, soak, rollback, and restore evidence.
- Completed in 1.99.1: aggregate-only, production-refusing EXPLAIN and index-inventory capture for ten critical global and role-restricted query shapes; the local tooling check is not treated as target-scale evidence.

- Completed in 1.98.4: deterministic PII-free small, expected, and provisional-maximum dataset profiles with guarded batch seeding, tagged verification, partial-run recovery, and run-scoped reset.
- Verified in 1.98.4: dry-run manifests for small/maximum profiles, 70 fast contracts, and 13 disposable native WordPress/MySQL tests including real small-profile seed, cardinality verification, scoped reset, and preservation of unmarked rows.
- Completed in 1.98.3: aggregate-only environment/capacity capture command and critical-scenario manifest.
- Completed in 1.98.3: signed-target, scenario-result, query-plan, capacity, bottleneck, and ownership evidence templates.
- Verified in 1.98.3: valid aggregate JSON from the Local WordPress/MySQL environment, all PHP syntax, 67 fast contracts, and 12 disposable native WordPress/MySQL tests.
- Pending: committed remote CI revision.
- Pending: business forecast and owner sign-off.
- Pending: first small/target dataset measurements and server-side database/resource capture.
- Pending: `EXPLAIN` evidence and ranked bottleneck list.
