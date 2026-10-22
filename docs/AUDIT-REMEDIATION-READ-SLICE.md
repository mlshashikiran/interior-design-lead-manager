# Second remediation slice: effective CRM access and live reads

Author / Builder: **Shashi**. Local candidate **1.102.44**, **2026-10-06**, dirty working tree on base `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. No commit, deployment or exact-artifact qualification claim.

CORE-001, QUO-002 and CORE-011 are **IMPLEMENTED**, not CLOSED. This follows the [first quotation slice](AUDIT-REMEDIATION-QUOTATION-SLICE.md); its evidence and the original October 6 audit remain historical records.

## Baseline and approved migration

Preserved 619 tracked/untracked candidate files, SHA-256 hashes, status and the complete tracked binary diff before this slice. Reconstructed the starting runtime under `/private/tmp/idlm-read-slice-original`; copied only the newer HTTP fixture/test cases into it for same-harness characterization. No reset, checkout, stash or unrelated cleanup was used. [Preservation evidence](remediation-2026-10-06/read-slice/preservation.json) identifies every changed starting file and confirms all original audit and first-slice evidence bytes remain unchanged.

The focused Graphify query **“Effective CRM capabilities default lead visibility quotation lists task queue ownership cache revocation”**, budget 1,400, connected CORE-001/011, QUO-002 and their lifecycle/authorization documentation. Source confirmed role-based CRM admission, stored quote-owner list filters and stale task-owner OR predicates. The graph is navigation evidence; source and disposable tests establish the behavior.

The user approved: **“Use the safer migration: remove automatic editorial-role grants and move legacy global visibility to assigned-only.”** Explicit per-role All/Assigned preferences and dedicated CRM/manager grants are preserved. This resolves the plan's existing-access prerequisite; it does not authorize deployment or changes to the configured site database.

## Resulting policy and role impact

| Actor / state | CRM admission and reads | Mutation authority |
|---|---|---|
| Ordinary editor/author/contributor/subscriber | No automatic CRM or customer quotation access | None |
| Dedicated CRM representative or individually enrolled account | Effective `idlm_access_leads`, no suspension; default assigned-only | Live owned lead with required operation capabilities |
| Explicit per-role All scope | Broad reads only after effective enrollment | Owner-only unless explicit assignment-manager grant |
| Assigned-only manager | Reads honor the assigned preference | Explicit manager quotation operations remain authorized; existing CRM object checks remain |
| Multiple roles | Assigned wins over All; administrator remains unrestricted | Existing operation/object checks remain |
| Pre-sales / mixed pre-sales | Current assignment plus existing intake/qualification association for historical lead/task reads; no customer quotation operations | Existing protected-field and qualification controls |
| Capability removed or CRM suspended | Denied, regardless of role name, stale task owner, captured URL/cursor or warmed dashboard | Denied |
| Catalogue-only explicit grant | Catalogue operations remain available without customer access | No lead/customer quotation operations |

Admission uses effective WordPress capabilities, including explicit user-level false overrides. Assignment authority also requires CRM admission. Customer quotation capabilities depend on enrollment; independently granted catalogue/settings capabilities keep their standalone behavior. Scope SQL fails closed without CRM access.

Initial role provisioning uses `idlm_initialized_crm_roles`; a version reconciliation does not add removed grants back to already provisioned roles. Dedicated sales/marketing role slug/name recognition remains for compatibility. A legacy installation's already recognized roles are treated as provisioned, preserving revoked grants. New recognized roles without existing IDLM grants are provisioned once when setup next runs; existing partial or negative IDLM grants are treated as deliberate enrollment and are not expanded. Explicitly selecting an editorial role while creating a CRM user, or changing an existing CRM user's role into one, enrolls only that individual through direct grants. Saving the same role does not restore a revoked individual capability.

One-time `idlm_crm_access_policy_version=1` migration removes the former automatic IDLM role grants on editor/author/contributor, resets standalone and grouped legacy global visibility to `0`, and preserves `idlm_role_lead_scopes`, direct user capabilities, other routing settings and lead/task/quote history. A non-secret `idlm_crm_access_migration_v1` record retains previous visibility and removed grant metadata. Persisted roles and both visibility stores are verified before the marker advances. Failed role persistence leaves the marker behind; pending cleanup denies inherited editorial admission and defaults ordinary representatives to assigned scope. Explicit individual/dedicated enrollment and administrator repair remain available. Retry converges.

Upgrade impact is intentional: automatic editorial CRM access and the legacy global read default are revoked. Before any deployment, identify editorial CRM users and routing-pool entries that need explicit enrollment or a dedicated CRM role. Existing Users & Access role changes can supply a dedicated role; newly created editorial CRM users are individually enrolled. Explicit direct grants from capability administration remain supported. Per-role All is visibility, not enrollment. Manually granted role-level editorial IDLM capabilities are indistinguishable from the legacy automatic grants and are also removed once; re-enroll intended users explicitly. Later explicit global visibility changes are preserved after the marker is complete. An older ZIP alone does not undo migrated WordPress role/option state; the migration record is diagnostic, not a recovery backup.

## Live list, task and cache contracts

Quotation list and count queries share canonical live-lead scope through the joined lead. `owner_user_id` stays stored attribution and no longer supplies authorization or automatic list filtering. Search, creator/date/lead filters, paging, JSON shape, shortcode/admin markup and callback names stay stable. A former owner loses discovery; a new owner gains it; read-all users and assigned-only managers match direct access. Inaccessible lead filters retain their existing zero-filter behavior within authorized scope.

Pending/completed task queues apply the same lead-history scope as drawers. Stale `t.assigned_rep_id` never grants customer visibility. Current owners can see associated work even if task attribution still names a former owner. Historical pre-sales association remains deliberate. This slice does not rewrite completed task attribution or broaden lifecycle task transfer/archive policy; dependent task-plan durability/repair remains M2/M3 work. Lost-lead attention exclusions and six-task presentation remain unchanged.

Role-scope, role-capability, individual-capability and suspension changes invalidate the read generation. Direct dashboard read-model entry denies without database/cache work. Existing lead lifecycle invalidation fences a late dashboard fill from before reassignment. Existing signed cursors remain user/filter/access-bound: a captured global cursor fails after narrowing scope; capability/suspension denial precedes decoding; current SQL scope applies to every page. No cursor wire-format or UI asset changes were made.

## Executed local verification

[Machine evidence and named cases](remediation-2026-10-06/read-slice/verification.json) records candidate hashes, runtime, commands, scenario IDs, outcomes and unexecuted dimensions. Environment: PHP **8.4.14**, WordPress **7.1.2** core, MySQL **8.4.0** / InnoDB, WP-Cron, no persistent object cache.

| Check | Actual outcome |
|---|---|
| Starting candidate fast suite | 177 PASS / 0 FAIL (first-slice baseline) |
| Starting candidate same final HTTP harness | 102 PASS / 40 FAIL; failures expose targeted access defects, not 40 independent findings |
| Final fast suite | 182 PASS / 0 FAIL |
| Final native suite | 21 PASS / 0 FAIL, including fresh defaults, upgrade/idempotence and failed-write/retry migration |
| Final actual HTTP suite | 142 PASS / 0 FAIL: first slice's 68 cases plus 74 live-read/access cases |
| Independent workers | Nine existing mutation authority barriers plus three read reassignment barriers (tasks, quotes, late dashboard cache fill) |
| Browser | Owner list → canonical reassignment → former owner empty list → new owner discovers quotation; live search; administrator utility parity; sampled 390px list; ordinary author denial; no affected console warnings/errors |
| Focused audit probes | CORE-S01/02 and QUO-S01/02/S09 pass; unrelated failures remain open |
| PHP lint / whitespace / preservation | PASS, recorded in machine evidence |

The runner uses real core admin-ajax/admin-post dispatch, cookie login and WordPress nonces. It covers ordinary content roles, explicit editorial enrollment through actual administrator user creation, owner/read-all/manager/mixed/pre-sales matrices, pending/completed history, reassignment, user/role revocation, suspension, warmed dashboards and captured cursors. Denied operations check all actual IDLM tables, working copies, cron/jobs, intercepted outbound attempts and synthetic PII; direct denied cache tests also assert no cache/database work. Customer records, users, credentials and mail recipients are synthetic. HTTP/mail are intercepted before install; fixtures create only new `idlm_test_*` databases and isolated copied core/content. The configured site's configuration/database is never loaded. Existing CI jobs automatically run these expanded native/HTTP cases; CI itself is NOT RUN here.

The sampled mobile view retains the existing horizontal table scroll. Full task UI, keyboard/accessibility/layout qualification, persistent-cache/multi-node behavior, in-flight permission revocation after a response starts, Action Scheduler native HTTP mode, supported PHP/WordPress matrix (including WP 5.8), non-InnoDB handling and exact-artifact qualification are NOT RUN. They are not inferred from local counts. Global M0 and the complete T01/T03/T04/T05/T06/T10 gap groups remain incomplete.

CORE-009/010 routing eligibility/capacity, M2 aggregate/migration durability, private files, MCP SQL grants and other audit findings remain OPEN. In particular, existing routing pools need review after enrollment revocation; this slice does not qualify background assignment/notification eligibility. No public-release or production-readiness claim is made.

## Next slice

Continue M1 with **INT-001 and INT-002**: inventory current MCP custom-query usage, retain supported transport contracts, enforce a tested restricted SQL grammar and safe field projections, deny authentication columns and unrelated tables before execution. CORE-002 private-file storage/retrieval remains another high-priority M1 boundary; its deployment/storage decision must precede legacy file migration.
