# M6 finite exit inventory

Author / Builder: Shashi. Started: 2026-10-22. Candidate baseline: 1.102.87. Completed: 1.102.88.

Scope: finish ownership boundaries required by M6, preserve public contracts, then advance to M7. A large file alone is not a failure. No further extraction is required for an accepted existing owner without a demonstrated responsibility conflict.

| Item | Disposition | Required work / acceptance |
|---|---|---|
| Integration credential/authentication policy | DONE | Extract scoped credentials, rate limits and feed/MCP checks; keep route callbacks and migration version stable. |
| Webhook intake and field normalization | DONE | Extract parsing/mapping/test handshake; preserve attribution and canonical DB upsert/transaction ownership. |
| Google feed HTTP transport | DONE | Extract challenge/headers/CSV endpoints; preserve auth and response bytes. |
| CAPI provider transport | DONE | Extract provider payload/HTTP/result handling from queue/current-eligibility orchestration; retain receipt claims/retry algorithms. |
| Google conversion feed data/export | DONE | Extract export queries/CSV formatting from CAPI orchestration; keep the CAPI facade callable. |
| Task AJAX transport | DONE | Extract task handlers and manual-task helpers; preserve access, reserved types and task policy. |
| Lead collection AJAX reads | DONE | Extract list/Kanban/count reads; preserve scopes, signed keysets, HTML/selectors and client payloads. |
| Schema and legacy backfills | ACCEPT EXISTING | IDLM_DB owns canonical statements/backfills; IDLM_Schema_Migration owns physical verification, locking and recovery. No new schema or migration is required by M6. |
| Lead lifecycle/assignment transactions | ACCEPT EXISTING | IDLM_Lead_Lifecycle_Service, Presales_Task_Plan, Assignment_Capacity, Lead_Transaction/Outbox own coupled state; integration routing retains pool/fallback orchestration. Preserve tested atomics. |
| Audit/reporting persistence | ACCEPT EXISTING | DB log/reporting methods are the canonical persistence API; activity transport has already moved. Further logger wrappers add no distinct responsibility. |
| Dashboard aggregates/presentation | ACCEPT EXISTING | IDLM_Dashboard computes metrics; Dashboard_Read_Model scopes/caches; Admin_Dashboard_Renderer/templates present results. No presentation HTML belongs in aggregate calculations. |
| Existing AJAX mutations/import/export | ACCEPT EXISTING | Remaining Admin_Ajax coordinates mutations/transfer through DB, lifecycle/access/private-file/export services. Dedicated activity, connection and telemetry owners already exist. |
| Shared client and Leads template | ACCEPT EXISTING | Existing shared asset/renderer registration and drawer selectors couple admin/shortcode contexts. No measured production change requires a new client module in this release; preserve bytes and verify desktop/mobile flows. |
| Feature-extension checklist/maps | DONE | Keep Development checklist and source-backed owner maps current; record delegate parity, tests and graph refresh. |

Exit requires the seven REQUIRED items implemented and verified, preserved boot/call/response contracts, source-backed acceptance of retained owners, and current maps. M6 completion does not close M7 or approve the release.

Completion: seven required owners and all 46 original facade signatures/delegates are verified. Maps, release evidence and scoped graph refresh are recorded in [the candidate report](M6-M7-RELEASE-CANDIDATE.md). M6 is COMPLETE. Later M7 fixes are identified separately from extraction parity; M7/M8 acceptance and REL-001 remain separate gates.
