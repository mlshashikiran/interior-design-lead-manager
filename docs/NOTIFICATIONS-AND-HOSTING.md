# Notification controls and portable hosting — 1.102.89

Author / Builder: Shashi. Date: 2026-10-23.

## Notification choices

An administrator controls outgoing notifications under **Settings → General → Notifications**:

- **Email previous and new owners after handoff** retains the existing email preference.
- **Email the administrator about routing exceptions** retains the existing routing-alert preference.
- **Enable outgoing WhatsApp automations** is a separate, explicit opt-in. It defaults off even on upgrades where Interakt is already configured. Interakt credentials and event mappings remain under Automation.

All missing choices default off. Existing email preferences are preserved. Saving credentials or enabling incoming Interakt capture does not enable outgoing WhatsApp notifications. Incoming authenticated WhatsApp replies, CRM writes, assignment, audit history and on-screen reminders remain available with outgoing notifications off.

Workers and direct send helpers check committed consent rather than a cached option. Disabling WhatsApp stops new enqueues and cancels queued ordinary/backfill work at dispatch. Re-enabling creates a new consent epoch; earlier WhatsApp jobs do not revive. Missed events are not backfilled automatically. Explicit administrative backfills require the outgoing switch. Delivery already in flight cannot be recalled.

Email and WhatsApp receiver testing is **optional and waived as a release prerequisite by Shashi**. Regression tests intercept mail/HTTP and verify no delivery when disabled, explicit opt-in, permission/nonce boundaries, independent choices, stale-cache cancellation, re-enable behavior and inbound independence. No live email or WhatsApp message is sent during qualification. Provider deliverability remains a service/account setting to verify when the owner enables delivery.

## Portable WordPress hosting contract

The plugin supports single-site WordPress 6.6+ and PHP 7.4+, with the declared database/extension capabilities. The normal baseline uses **WP-Cron and database transients**. Action Scheduler, persistent object caching, OPcache, dedicated workers, root access and a particular CPU/RAM allocation are not required to activate or operate the CRM. No hosting-panel access or hosting-specific resource change is a plugin-build acceptance gate.

- Use WordPress's configured database with InnoDB and transaction/named-lock support for checked writes and overlap protection. Missing or incompatible schema/database capabilities must produce a checked error; the plugin does not silently replace the database's guarantees.
- Use writable WordPress media/temporary storage. PDF, XLSX and protected credential features check their required PHP extensions and return actionable errors when unavailable.
- Keep reads and queues bounded. Lead/task pages, exports, retention and background dispatch use the existing row/time/byte limits; a slow host may need more requests to finish work.
- WP-Cron follows normal WordPress scheduling. On low-traffic sites, background jobs can be delayed until cron runs. An external one-minute runner is an optional reliability improvement, not a dependency on a particular hosting provider.
- OPcache tuning, persistent cache and worker/resource recommendations concern throughput and workload capacity. The reference 25–50-user/50k-lead measurements do not define universal minimum server hardware, and do not promise the same speed on every host.

No plugin can guarantee an arbitrary workload with zero available memory, exhausted storage or a stopped database. Portability means supported WordPress capabilities, fallbacks, bounded work and checked failures—not unlimited capacity. The supported fallback suite is also exercised with a 128 MiB initial PHP memory limit, no persistent cache, no Action Scheduler and CLI OPcache disabled. This is regression evidence, not a universal memory promise for every document size or third-party plugin combination.

## Release and deployment

The 1.102.89 build supersedes 1.102.88 for notification behavior. Runtime bytes change; previous artifact identities and workload/restore evidence retain their original scope. Use the new exact-revision CI manifest and local `build/production-notifications-1.102.89/REVIEW.md` for the current build. Actual-site backups, monitoring and provider credentials remain operator responsibilities. Deployment still requires separate authorization and has not been performed.
