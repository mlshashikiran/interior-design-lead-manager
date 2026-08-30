# Load and Resilience Qualification

Author / Builder: Shashi  
Status: Phase 8 started; read-only load tooling implemented, qualification not yet complete  
Release: 1.99.7  
Started: 2026-08-31

## Scope

The first Phase 8 increment adds a guarded authenticated HTTP runner for three established read-only CRM surfaces:

- Dashboard period refresh (`idlm_get_dashboard_stats`).
- Lead-list filter/sort page (`idlm_get_leads_table`).
- Kanban stage load (`idlm_get_kanban_leads`).

It also records the required dataset, traffic, authorization, concurrency, failure, recovery, and integrity matrix. The matrix remains `not_executed`; no target-scale, resilience, restore, rollback, or production qualification is claimed by this release.

## Safety contract

- Default invocation prints a plan and makes no request.
- Execution requires `--execute` and the exact `--confirm=run-idlm-load-qualification` value.
- Execution is allowed only in WordPress `local`, `development`, or `staging` environments.
- Only the three allowlisted read-only authenticated AJAX actions can run.
- The runner generates a one-hour WordPress login cookie and the established `idlm_admin_nonce` for the selected benchmark user, then destroys that benchmark session token on shutdown.
- Redirects are disabled, TLS verification remains enabled, concurrency is capped at 50, and requests are capped at 5,000 per scenario.
- Response bodies are streamed to anonymous temporary files, inspected only for the standard success envelope, and deleted after each batch.
- Output excludes URLs, user identity, cookies, nonces, request values, response bodies, contacts, and credentials.

## Commands

Preview the complete plan without WordPress or network access:

```bash
php tests/performance/run-load-qualification.php
```

Run one bounded scenario against a non-production benchmark clone:

```bash
php tests/performance/run-load-qualification.php \
  --wp-root=/path/to/wordpress \
  --execute \
  --confirm=run-idlm-load-qualification \
  --user-id=benchmark-user-id \
  --scenario=dashboard-period-refresh \
  --requests=100 \
  --concurrency=5 \
  --warmup=5 \
  --dataset=expected-baseline-01 \
  --revision=commit-or-build-id \
  > dashboard-load-result.json
```

Valid scenario values are `dashboard-period-refresh`, `lead-list-filter-sort`, `kanban-stage-load`, and `all`. Use dedicated synthetic benchmark users for each authorization scope. Do not reuse production sessions or production CRM data.

## Evidence format

The `idlm-load-qualification-result/v1` record contains build/dataset labels, non-identifying role scope, bounded run configuration, throughput, response bytes, outcome counts, error rate, and p50/p95/p99 latency. Threshold checks are marked unsigned and `evidence_only` until Phase 0 owners approve service targets.

Database time/query count, PHP CPU/memory, browser timing, queue lag, and resource saturation must be captured with server-side monitoring and added to `docs/PERFORMANCE-BASELINE.md`; the HTTP runner does not infer those signals.

## Remaining qualification

- Run small, expected, and maximum datasets across normal, peak, burst, and soak profiles.
- Cover administrator, assignment manager, representative, pre-sales, and unauthorized scopes.
- Add guarded mutation/concurrency drivers for webhook deduplication, assignment, tasks, quotation saves, and number reservation.
- Add controlled third-party, cache, storage, database, worker, queue, and credential failure injection.
- Rehearse production-like upgrade, interrupted migration, rollback, and database/media restore.
- Prove all integrity invariants and record signed recovery time/recovery point evidence.
