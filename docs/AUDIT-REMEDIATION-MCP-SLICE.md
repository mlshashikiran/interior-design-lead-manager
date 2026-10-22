# Third remediation slice: restricted MCP queries and field grants

Author / Builder: **Shashi**. Local candidate **1.102.45**, **2026-10-06**, dirty working tree on `ff148ba62ec710a622d9b1af7e8f511b7e0b7385`. **INT-001 and INT-002 are IMPLEMENTED, not CLOSED.** No deployment, exact-artifact or complete-release qualification claim.

## Baseline and compatibility decision

Preserved **640** tracked/untracked starting files with SHA-256 hashes, status, full tracked binary patch and a focused source snapshot before editing. [Preservation evidence](remediation-2026-10-06/mcp-slice/preservation.json) records changed files and verifies that the original audit and both previous slices' reports/evidence remain unchanged. The configured site database/configuration was never loaded.

The focused Graphify query **“MCP read-only SQL validation allowed tables wp_users data projection query transport authentication”**, budget 1,400, identified the mixed JOIN/comma bypass and unrestricted user-column access. Current source and audit probes confirmed both. Starting fast suite: **182 PASS / 0 FAIL**. Same unchanged audit probes: **6 PASS / 7 FAIL**, including INT-S02 and INT-S03 failure.

Repository inventory found simple SELECT/limit test queries, the existing typed tools, and adversarial audit queries; no stored client report templates. The user reported **“nothing that i know of”** when asked about existing custom-report dependencies. External clients were not enumerated. Chose a compatible restricted grammar behind the existing `run_custom_query` SQL argument rather than removing or renaming the tool. Documented syntax and field narrowing are intentional security corrections; unknown external reports need compatibility rehearsal before deployment.

## Contracts and data authority

Tool names, schemas' property names, REST routes, header credential, JSON-RPC envelopes, CLI entry and protocol version remain. Supported SQL compiles to a reconstructed query; caller text is never forwarded as an executable statement. Identifier validation and literal preparation are independent. Full token consumption prevents hidden table expressions after a join. Computed-column labels without AS and explicit output aliases remain stable after identifier quoting.

MCP uses its scoped credential or trusted local stdio process, with tenant-wide analytics authority; cookie users and CRM role scopes are not the MCP principal. Actual owner/non-owner/read-all/manager cookies with the same API grant return the same permitted report. Do not interpret this as owner-scoped MCP access.

`query_leads` and `get_lead_details` now require both their tool grant and a separate **`IDLM_MCP_ALLOW_PII` boolean true**, or trusted-server option **`idlm_mcp_allow_pii='1'`** when the constant is absent. Default is denied in every environment. Without the grant they are omitted from `tools/list` and execution fails before customer SQL. The same grant controls explicitly approved custom contact/profile columns. Stored-option revocation takes effect on the next HTTP request or stdio call; the long-running stdio entry clears its policy-option cache before each line. Changing server constants requires restarting that process. There is no new browser control; trusted server configuration manages the grant. Existing explicit tool grants remain required and production MCP remains disabled by default.

`wp_users` is compiled into an internal projection containing only **ID and display_name**, limited to accounts referenced by canonical assigned/intake/qualification lead associations. Unrelated accounts, email, login, password hashes, activation keys and user metadata are unavailable. This is approved representative-display data; historical associations deliberately remain discoverable to the trusted analytics principal.

Custom fields are enumerated in [the policy](../includes/class-mcp-query-policy.php); future schema columns are not automatically granted. Lead analytical state/timing/value/source columns and log ID/type/status/time/count fields are available. Approved lead contact/project/budget/location fields require PII permission. Private attachment URLs, advertising identifiers, activity free text, raw provider payload/response and telemetry message/file/trace remain unavailable even with the PII grant. Analytics labels and display names may themselves contain operator-entered identifying text; review granted analytics and naming conventions for the deployment's privacy requirements. The existing explicitly granted detailed-profile tool retains its established profile/activity payload; this slice does not claim content-level cleansing of historical text.

## Supported query grammar and bounds

- One SELECT, optionally DISTINCT; explicit columns with optional AS aliases. COUNT(*), COUNT/SUM/AVG/MIN/MAX and DATE/YEAR/MONTH expressions are supported; aggregate arguments may use DISTINCT.
- One approved FROM and at most four distinct table aliases. JOIN/INNER JOIN/LEFT [OUTER] JOIN require one qualified equality linking the new alias to an existing alias. All referenced columns in SELECT, ON, WHERE, GROUP and ORDER are checked.
- WHERE supports bounded parentheses, AND/OR, comparisons, LIKE/NOT LIKE, literal IN/NOT IN, BETWEEN and IS [NOT] NULL. Numeric and single-quoted literals are compiled safely; strings support doubled apostrophes. Backslash escaping and double-quoted strings are unsupported.
- GROUP BY and ORDER BY approved columns/output aliases, optional ASC/DESC; trailing numeric LIMIT with comma or OFFSET form. Configured result limits are appended/capped, offset cannot exceed 100,000, and expression/condition nesting is at most eight levels. Existing payload, batch and query-length budgets remain.
- Star projections, comma tables, caller subqueries/CTEs/unions, derived/database-qualified tables, comments, variables, arbitrary functions, locking/file-output statements and unconsumed syntax are rejected. Safe internal user projection SQL is generated by the compiler, not accepted from callers.

Examples:

```sql
SELECT id,stage,deal_value FROM wp_idlm_leads WHERE stage IN ('Won','Qualified') ORDER BY id DESC LIMIT 20
SELECT u.ID AS rep_id,u.display_name AS rep_name,COUNT(l.id) AS total FROM wp_idlm_leads l LEFT JOIN wp_users u ON u.ID=l.assigned_rep_id GROUP BY u.ID,u.display_name ORDER BY total DESC
```

The actual prefix/user-table name comes from WordPress. MySQL statements receive an internally generated MAX_EXECUTION_TIME hint based on the existing request budget; MySQL's documented semantics apply to the entire read-only statement, including internal projection queries. [MySQL reference](https://dev.mysql.com/doc/refman/8.0/en/optimizer-hints.html#optimizer-hints-execution-time). Native MySQL 8.4 tests interrupt a four-table aggregate under a 250ms test budget and verify a usable connection afterward. This is a server execution bound, not a socket/queue/network deadline. MariaDB/older-server hint behavior and other server topologies are NOT QUALIFIED here; operators must keep custom SQL disabled unless their database enforces the bound. Typed-tool individual SQL timing was not changed.

Database errors and driver exceptions return generic responses and WordPress query-error output is suppressed/restored around the custom read; SQL/literals are not returned or added to MCP audit records. HTTP audit still writes intentional aggregate `mcp_access` attribution. Denied business operations do not mutate CRM data, working copies, jobs or outbound queues; these expected audit rows are not counted as forbidden business writes.

The stdio entry now lets the selected WordPress loader initialize ABSPATH, preserving the established default path/environment override while avoiding a duplicate definition and loading the explicitly selected isolated core. Tests execute the real entry, assert the owned core root, JSON-only stdout and no PHP diagnostics.

## Executed verification and limits

[Machine evidence](remediation-2026-10-06/mcp-slice/verification.json) records commands, source hashes, named cases, preservation, cleanup and boundaries. PHP **8.4.14**, WordPress **7.1.2**, MySQL **8.4.0** / InnoDB, WP-Cron, no persistent object cache.

| Check | Actual result |
|---|---|
| Final fast suite | **238 PASS / 0 FAIL**, including 52 adversarial requests and independent PII/tool grants |
| Final native suite | **24 PASS / 0 FAIL**; 52 adversarial requests with each PII state reject before any observed SQL; safe associated-user projection, 205-row paging, literal encoding under ANSI_QUOTES/NO_BACKSLASH_ESCAPES, computed labels and real statement timeout |
| Actual HTTP/stdio runner | **207 PASS / 0 FAIL**: earlier 142 cases plus 65 MCP cases, including three real-stdio scenario groups |
| Audit probes | **8 PASS / 5 FAIL**; INT-S01/02/03/04 now PASS, unrelated backup/provider failures remain open |
| PHP lint, whitespace, preservation | PASS, recorded with file lists and hashes |

Transport coverage includes missing/wrong/webhook/query credentials, single/batch protocol dispatch, tool discovery, GET/DELETE session interfaces, safe reports, owner/non-owner/read-all/manager cookie independence, PII grant enable/revoke, tool restriction, actual stdio grammar/notifications/grants/disable and external option revocation between calls in one live process, credential rotation, HTTP disable, audit redaction and real rate limiting. All denied custom reads assert no custom execution; native tests observe no SQL at all after warmed policy configuration. Denial snapshots cover all IDLM business tables, working copies, cron/jobs and outbound attempts; expected aggregate audit attribution is checked separately. Synthetic auth markers/options/usermeta/contacts are isolated. HTTP and mail are intercepted before fixture installation.

Authorization cases reset only the owned fixture's rate counter between independent requests. Existing native REST Allow-header permission probes also consume that counter; a separate actual HTTP case verifies 429 enforcement without resets. No production limiter behavior was changed.

CI and the PHP/WordPress support matrix, exact packaged artifact, persistent cache/multi-node revocation, MariaDB/older MySQL, high-volume/load/network deadlines, production client reports and in-flight permission revocation after execution begins are **NOT RUN**. No UI surface changed, so browser/layout checks were not repeated; actual transports were exercised. Global M0, full T15 and release qualification remain incomplete; do not mark these findings CLOSED from local counts.

## Next slice

Continue M1 with **CORE-003** lead CSV formula protection and actual authorized/denied export regression. **CORE-002** private-file storage/retrieval remains high priority but requires the deployment/storage decision and legacy-file migration rehearsal before implementation. Other audit findings remain OPEN.
