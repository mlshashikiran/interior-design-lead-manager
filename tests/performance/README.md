# Performance Baseline Tooling

Builder: Shashi

This directory contains the reproducible Phase 0 benchmark manifest, read-only capacity and Phase 2 query-plan capture commands, guarded deterministic synthetic dataset tooling, and the Phase 8 authenticated read-only load runner. Captures contain aggregate metadata only; synthetic datasets use reserved identifiers and `.invalid` email addresses rather than real contact data.

## Run authenticated read-only load qualification

Preview the Phase 8 matrix without booting WordPress or making network requests:

```bash
php tests/performance/run-load-qualification.php
```

Execute a bounded scenario only against a local, development, or staging benchmark clone:

```bash
php tests/performance/run-load-qualification.php \
  --wp-root=/path/to/wordpress \
  --execute \
  --confirm=run-idlm-load-qualification \
  --user-id=benchmark-user-id \
  --scenario=lead-list-filter-sort \
  --requests=100 \
  --concurrency=5 \
  --warmup=5 \
  --dataset=expected-baseline-01 \
  --revision=commit-or-build-id \
  > lead-list-load-result.json
```

The runner generates a short-lived WordPress login cookie and the established CRM nonce, destroys the benchmark session token on shutdown, follows no redirects, retains TLS verification, streams bodies to anonymous temporary files, and emits only latency/throughput/size/outcome aggregates. See `docs/LOAD-RESILIENCE-QUALIFICATION.md` for the full safety and evidence contract.

## Capture query plans and index inventory

Run on a local, development, or staging benchmark clone:

```bash
php tests/performance/capture-query-plans.php \
  --wp-root=/path/to/wordpress \
  --revision=commit-or-build-id \
  --dataset=small-baseline-01 \
  --start=2026-01-01 \
  --end=2026-12-31 \
  > query-plans.json
```

The command executes read-only `EXPLAIN` and `SHOW INDEX` statements for the ten shapes in `query-plan-scenarios.php`. It refuses production and omits SQL text, parameter values, CRM rows, contacts, credentials, URLs, paths, and database names. Full scans and filesorts are review signals, not automatic index approvals; compare expected/maximum-profile plans with measured endpoint database time and index storage/write cost.

## Capture the environment inventory

Run from the plugin root:

```bash
php tests/performance/capture-baseline.php \
  --wp-root=/path/to/wordpress \
  --revision=commit-or-build-id \
  > performance-baseline.json
```

`IDLM_PERF_WP_ROOT` and `IDLM_PERF_REVISION` are equivalent environment variables. The selected WordPress environment must have the plugin active. Store captures outside release artifacts and review them before sharing.

The output records:

- WordPress, PHP, MySQL, plugin, and schema versions.
- Aggregate plugin-table row estimates, data bytes, index bytes, and free bytes.
- Aggregate autoloaded-option count and byte size.
- WP-Cron and Action Scheduler counts/oldest open work.
- The critical scenario manifest with an explicit `not_measured` state.

## Safety and interpretation

- The command issues aggregate reads after a normal WordPress bootstrap; it does not seed, update, delete, or truncate CRM data.
- InnoDB `TABLE_ROWS` values are estimates. Use exact counts only when their cost is acceptable for the benchmark environment.
- Run latency/load scenarios against a sanitized production-like clone, never the production database.
- Record browser/server latency, database time/query count, memory, response bytes, CPU, queue lag, and error rate for each scenario in `docs/PERFORMANCE-BASELINE.md`.

## Plan and seed a deterministic dataset

Preview the default small profile without booting WordPress or writing data:

```bash
php tests/performance/seed-dataset.php \
  --profile=small \
  --seed=baseline-01
```

Available profiles are:

| Profile | Leads | Activities | Tasks | Touchpoints | Retained logs | Quotes/graphs |
|---|---:|---:|---:|---:|---:|---:|
| `small` | 1,000 | 8,000 | 2,000 | 2,000 | 4,000 | 500 |
| `expected` | 50,000 | 400,000 | 100,000 | 100,000 | 200,000 | 20,000 |
| `maximum` | 250,000 | 2,000,000 | 500,000 | 500,000 | 1,000,000 | 100,000 |

Each quote gets one revision, section, and item. The same profile and seed always produce the same sanitized values and timestamp distribution.

Execute only against a local, development, or staging benchmark clone:

```bash
php tests/performance/seed-dataset.php \
  --wp-root=/path/to/wordpress \
  --profile=small \
  --seed=baseline-01 \
  --execute \
  --confirm=seed-idlm-performance-data
```

The command refuses production, requires the plugin to be active, validates every required table, writes in batches, verifies all generated cardinalities, and records an aggregate run manifest in the non-autoloaded `idlm_performance_seed_runs` option. Use `--batch-size=50..2000` to tune write batches.

If a run is interrupted, rerunning the same seed refuses to append duplicates. Reset only that tagged run, then seed it again:

```bash
php tests/performance/seed-dataset.php \
  --wp-root=/path/to/wordpress \
  --reset=small-baseline-01 \
  --confirm=reset-idlm-performance-data
```

Reset discovers leads by the reserved `Performance Seed` source plus run marker, deletes their linked core/quotation rows in bounded ID batches, and removes only audience/telemetry rows carrying that run marker. Existing unmarked CRM data is preserved.

## Next performance increment

Run the authenticated read-only scenarios for each benchmark role at small/expected/maximum scale, capture server-side database/resource timing, and add guarded concurrency and failure-injection drivers before claiming Phase 8 qualification or approving further Phase 2 tuning.
