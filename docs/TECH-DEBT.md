# Technical Debt

This is an observed-debt register, not authorization to refactor.

## Critical

No currently open critical issue was proven exploitable from static inspection and the behavioral baseline.

## High

No open high-priority code defect is currently proven. Version 1.99.8 adds the guarded release-artifact/checksum/CI-attestation gate, but production deployment remains blocked until a clean committed remote CI run produces the candidate and the production-like upgrade/restore/rollback rehearsal, hardened environment/secrets/cron configuration, monitoring, backup, staging, and rollout gates in [PRODUCTION-SCALABILITY-PLAN.md](PRODUCTION-SCALABILITY-PLAN.md) are completed.

## Medium

### Production security and operational gates

- **Problem:** Version 1.99.0 closed the initial MCP least-privilege, lead-upload, and trusted-proxy code gaps. Production debug/log verification, remaining platform-secret migration and rotation, bounded retention, backup-confirmed uninstall policy, and deployment/rollback/cron/email/webhook rehearsals are still incomplete.
- **Why it matters:** these are acceptable local-development defaults but require explicit policy, least privilege, and tested recovery before real CRM data and internet traffic.
- **Approved remediation:** Phase 1 plus the release/observability/recovery phases in [PRODUCTION-SCALABILITY-PLAN.md](PRODUCTION-SCALABILITY-PLAN.md).

### Scalability baseline and critical read paths

- **Problem:** no large-dataset/load benchmark exists; dashboards execute many aggregate reads, lead metrics include additional scans/PHP-side timing calculations, deep pages use offset pagination, and bulk operations are not uniformly resumable jobs.
- **Why it matters:** correctness is proven at the current test scale, but latency, memory, queue lag, and database cost are not bounded at production growth targets.
- **Approved remediation:** Phases 0, 2, 3, 4, 7, and 8 in [PRODUCTION-SCALABILITY-PLAN.md](PRODUCTION-SCALABILITY-PLAN.md), with optimizations accepted only after baseline and query-plan evidence.
- **Progress:** version 1.99.1 added production-refusing aggregate EXPLAIN/index capture for ten critical SQL shapes. Version 1.99.7 added a guarded authenticated HTTP load runner for three read-only CRM surfaces plus the explicit Phase 8 resilience matrix. No target-profile run is recorded yet; expected/maximum endpoint timing, server-side database/resource evidence, concurrency/failure/soak coverage, and query plans remain required before qualification or an index/query migration.

### Observability and recovery operations

- **Problem:** version 1.99.6 adds administrator-visible aggregate health gates, queue/fault thresholds, and initial schema/queue/credential runbooks, but correlation IDs, route/database latency, webhook outcomes, backup/capacity signals, external alert delivery, remaining recovery runbooks, and rehearsals are incomplete.
- **Why it matters:** the local health surface can identify several unsafe states, but production operators still need proactive alerts, end-to-end attribution, and proven recovery procedures before launch.
- **Approved remediation:** complete Phases 7 and 8 in [PRODUCTION-SCALABILITY-PLAN.md](PRODUCTION-SCALABILITY-PLAN.md) using [OPERATIONS-RUNBOOKS.md](OPERATIONS-RUNBOOKS.md) as the initial operational baseline.

### Horizontal infrastructure qualification

- **Problem:** version 1.99.5 can report multi-node readiness, but the shared database, media/object storage, persistent cache, Action Scheduler runners, controlled cron, identical protected configuration, and two-node failure/recovery behavior have not been provisioned or qualified.
- **Why it matters:** declarations prove operator intent, not that accepted requests, uploads, cache entries, or queued effects survive node termination without duplication or loss.
- **Approved remediation:** complete Phases 6 and 8 in [PRODUCTION-SCALABILITY-PLAN.md](PRODUCTION-SCALABILITY-PLAN.md) using [HORIZONTAL-DEPLOYMENT.md](HORIZONTAL-DEPLOYMENT.md); do not route multi-node production traffic while any preflight or resilience gate is blocked.

## Low

### Remaining request and client hotspots

- **Problem:** `class-admin-ajax.php`, `class-integrations.php`, `class-db.php`, `class-capi.php`, `class-dashboard.php`, `admin-app.js`, and the Leads template remain large compatibility hotspots.
- **Why it matters:** behavior is covered, but unrelated changes can still share large files and slow review/parallel development.
- **Approved remediation:** extract only the hotspot touched by measured production/scalability work, in the order and with the compatibility rules in Phase 5 of [PRODUCTION-SCALABILITY-PLAN.md](PRODUCTION-SCALABILITY-PLAN.md).
- **Progress:** version 1.99.4 moved the 727-line authenticated lead-detail read/drawer handler into `IDLM_Admin_Lead_Reads` behind both existing delegate layers, reducing `class-admin-ajax.php` from 3,807 to 3,097 lines. The remaining Phase 5 boundaries are still open.

Version 1.98.1 completed the prior admin-facade item with evidence recorded in [ADMIN-REFACTOR-VERIFICATION.md](ADMIN-REFACTOR-VERIFICATION.md).

Version 1.98.0 completed Phase 4 by extracting assets, shortcode/workspace orchestration, account handling, display formatting, and WhatsApp/reminder support into focused classes behind all 75 public methods; credential rotation and representative authorization now delegate to their established controller/policy boundaries. The facade decreased from 949 to 432 lines with 67 fast contracts, 12 native tests, and full PHP syntax checks green.

Version 1.97.2 completed Phase 3 by extracting settings context/environment reads and ten guarded section templates into `IDLM_Admin_Settings_Renderer`, reducing the facade from 2,950 to 949 lines with 66 fast and 12 native tests green.

Version 1.97.0 completed Phase 2 by extracting the leads workspace and shared overlays, reducing the facade by another 1,303 lines with 66 fast and 12 native tests green.

Version 1.96.0 completed Phase 1 by extracting dashboard, Kanban, CAPI log, and audience log renderers/templates, reducing the facade by 809 lines with 65 fast and 12 native tests green.

Version 1.95.0 completed the other low-priority items: guarded reusable templates, grouped routing settings, release artifact cleanup, environment-driven MCP paths, distribution exclusions, and concise public history with full archives under `docs/legacy/`.
