=== Interior Design Lead Manager ===
Contributors: shashi-protecto
Tags: lead-manager, interior-design, crm, lead-tracking, duplicate-detection
Requires at least: 6.6
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 1.102.90
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A premium WordPress CRM for interior-design lead capture, routing, qualification, analytics, integrations, and versioned quotations.

== Description ==

Interior Design Lead Manager replaces spreadsheet-based lead operations with a WordPress-native workspace.

Features:

* Webhooks for Meta Ads, Google Ads, LinkedIn Ads, website forms, and walk-ins.
* Normalized duplicate detection and attribution touchpoints.
* Assignment routing, pre-sales qualification, follow-up tasks, and sales handoff.
* Analytics dashboard, pipeline board, lead workspace, and audit logs.
* Meta/Google/LinkedIn conversion and audience integrations.
* Interakt WhatsApp event and reply handling.
* Lead-linked, versioned quotations with catalogue items, semantic Preview / Print, and secure exact-revision PDF/CSV/XLSX downloads.
* Unified `[idlm_workspace]` frontend CRM with role-aware navigation.

== Installation ==

1. Upload the `interior-design-lead-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin from the WordPress Plugins screen.
3. Open Lead Manager and configure roles, routing, integrations, and quotation defaults.

== Frequently Asked Questions ==

= Can this plugin detect duplicate leads with different phone formats? =

Yes. Phone values are normalized and suffix-matched to reconcile common local and international formats.

= Does the plugin require Action Scheduler? =

No. Action Scheduler is used when available; WordPress Cron is the fallback.

== External services ==

Administrators configure credentials, destinations, enabled events and audience rules in Settings. Eligible lead changes and scheduled/manual work can then transfer data. Configuration does not collect customer consent; review your site's privacy/consent policy and the provider's terms before enabling a flow. Hashes are matchable identifiers, not anonymous data. Disabling work cannot recall an in-flight request or remove provider-held data.

* Meta: conversions send hashed contact/name fields, click/lead identifiers, request-context IP/user agent, event/stage/project details and configured value/currency to graph.facebook.com. Custom audiences send hashed email/phone/names and optional deal value. Review [Meta privacy](https://www.facebook.com/privacy/policy/), [Business Tools Terms](https://www.facebook.com/legal/terms/businesstools) and [Customer List Custom Audiences Terms](https://www.facebook.com/legal/terms/customaudience).
* Google: conversion reporting uses GA4 Measurement Protocol at www.google-analytics.com, sending client/click IDs, CRM lead ID, event/stage/project details and configured value/currency. Its URL includes the required API secret; protect request tracing. The configured Data Manager HTTPS audience pull receives plaintext normalized email/phone, country IN, empty postcode and hashed first/last names from this site's credentialed enabled feed. Review [Google privacy](https://policies.google.com/privacy), [API Terms](https://developers.google.com/terms) and [Customer Match policies](https://support.google.com/adspolicy/answer/6299717).
* LinkedIn: conversion reporting sends hashed email, available click ID, event/time and value/currency; contact audiences send hashed email with plaintext first/last names to api.linkedin.com. Review [LinkedIn privacy](https://www.linkedin.com/legal/privacy-policy) and [API Terms](https://www.linkedin.com/legal/l/api-terms-of-use).
* Interakt / WhatsApp: enabled ordinary/batch automation sends plaintext phone, name, email, CRM ID, event/time and project/location/budget/source traits to api.interakt.ai. Signed Meta/Interakt webhooks receive message IDs, phone and text, storing matched replies in CRM activity. Open in Interakt puts the phone in an app.interakt.ai URL. Review [Interakt privacy](https://www.interakt.shop/privacy-policy/) and [Terms of Service](https://www.interakt.shop/terms-of-service/), plus Meta's policies for WhatsApp.

Connection tests contact the selected provider using credentials/destination IDs. Optional staff notifications use the site's WordPress mail service. Configured MCP clients can receive permitted CRM results. Saved remote quotation artwork can cause server/browser requests to its host. Inter fonts are bundled locally. Full data/transfer details are in the distributed PRIVACY.txt.

== Privacy and data lifecycle ==

The site stores customer/contact/project data, attribution, staff assignments, tasks, calls, notes, WhatsApp text, identity reviews, quotations and diagnostics. Exports/backups contain personal/commercial data. Telemetry is local plugin diagnostics, not automatic developer reporting; error text and hosting/provider logs can still contain sensitive values.

Protected provider/webhook/MCP credentials use encrypted server-side storage or supported environment overrides and write-only settings fields. Retain the original encryption key or WordPress salts; changing them does not automatically re-encrypt credentials. CRM contact records are not encrypted by the credential store, and historical external traces/backups need separate cleanup or credential rotation.

The frontend attribution script automatically writes first-party idlm_ UTM/click-ID/landing-page cookies with a 30-day expiry. There is no built-in consent gate; disabling conversion/audience rules does not stop these cookies. Arrange the site's consent policy before using the script where consent is required. This disclosure does not claim consent enforcement.

Scheduled defaults: touchpoints/identity reviews 90 days, CAPI/audience diagnostics 180 days, telemetry 14 days; selected integration-summary activity has a separate seven-day cleanup. Server-side policy can override the first three. Bounded cleanup needs a working scheduler. Leads, normal activity, tasks, quotations and managed files have no general automatic expiry. CAPI receipts survive diagnostic pruning until lead deletion/reset. Minimal hashed/keyed WhatsApp replay receipts survive deletion/reset/JSON restore with no scheduled expiry or user-facing erasure control.

Authorized lead deletion removes linked CRM data and managed private-file bytes; legacy disk cleanup can require retry/reconciliation. CRM reset clears runtime CRM data while preserving catalogue, settings/credentials, WordPress users, branding media and WhatsApp replay receipts. Neither action erases provider copies, downloads, caches, external backups or all aggregate diagnostics. WordPress personal-data exporter/eraser tools are not integrated with CRM contact erasure. Direct data requests to the site administrator.

New managed confidential uploads use protected database storage and authenticated live-access downloads. Previously public files need verified migration/cleanup; remote and cached copies cannot be recalled. CRM JSON backups exclude private-file bytes, WordPress users/media, credentials, receipts and queued runtime state; they are not full-site recovery backups. Restore requires a compatible complete same-site aggregate/existing users and refuses active managed private-file records.

Deactivation clears covered schedules and preserves data. Reactivation can resume eligible work. Removing plugin files has no uninstall purge routine: stored tables/options/capabilities and private/legacy media remain. Review distributed PRIVACY.txt for exclusions and erasure/recovery limits.

== Support and security ==

Contact Shashi at [mlshashikiran@gmail.com](mailto:mlshashikiran@gmail.com) for plugin support, reviewed update requests and private security reports. Include versions and synthetic reproduction steps; send redacted diagnostics only. Do not send customer exports, passwords, tokens, keys or full backups by email. Report vulnerabilities privately, without public exploitable details. No response-time guarantee is published. Customer data/access requests belong to the site administrator. Distributed SUPPORT.txt explains the reporting process.

== Updates and recovery ==

Updates are manual reviewed ZIPs requested through the support contact; no custom automatic updater or guaranteed WordPress.org listing is provided. This audit candidate remains local and unapproved for production. Request the approved artifact, version, checksum and migration notes. Verify full database/filesystem/media backups and matching encryption keys/salts, rehearse the exact ZIP on staging, pause outbound work for maintenance and verify access/data/downloads/schedulers before reopening it. Installing an older ZIP alone cannot undo schema/data changes. Full restores can resurrect erased records or replay work; reconcile provider state. Support-floor and runtime qualification are tracked separately, so metadata is not proof of compatibility.

== Host requirements and compatibility ==

WordPress 6.6+ and PHP 7.4+ are the selected minimums. Earlier WordPress versions are outside the support scope. Metadata states the target range; this local audit candidate remains unapproved, and the exact committed release must pass the complete PHP 7.4/8.1/8.4/8.5 x WordPress 6.6/latest matrix before distribution. Local named runs are recorded separately; they do not establish every version combination. Use maintained WordPress/PHP releases for deployment.

WordPress needs its database driver (mysqli for the tested configuration). All CRM and quotation transaction dependencies must use InnoDB, including the relevant WordPress user/options tables; required metadata, schema and lock operations must be permitted. Unsupported engines or failed migrations block writes. The tested database family is MySQL; MariaDB, multisite/network activation and distributed database/cache topologies remain unqualified.

Protected stored credentials require OpenSSL with AES-256-GCM and matching encryption key/WordPress salts. Without crypto support, saved credentials cannot be created, migrated or decrypted; existing bytes are preserved and unavailable credentials must be repaired before enabling integrations. Environment-managed credentials have a separate read path; do not interpret it as qualification of a host without crypto. Never rotate salts/keys without a verified recovery procedure.

Downloaded PDFs require the bundled Dompdf tree, DOM, mbstring, iconv and GD (including image support), plus writable temporary/font-cache storage. Missing renderer prerequisites return to quotation Preview / Print before any PDF bytes are sent. PNG/image rendering requires the relevant GD codecs. PDF resource exhaustion or renderer errors use the same fallback. XLSX requires ZipArchive and writable temporary storage; failure returns a generic download error. CSV and browser print do not require the PDF/ZIP extensions. Install missing modules for the web PHP runtime as well as CLI, and verify a synthetic document with images in each required format.

Action Scheduler is optional; WP-Cron is the fallback. Scheduled work requires working cron dispatch or a controlled external runner; disabled cron/low traffic delays delivery and retention. An external object cache is optional. Keep WordPress HTTP/HTTPS transport, media uploads and temporary storage functional. No general memory/time budget or production volume is certified by these prerequisites. See bundled SUPPORT.txt for qualification and update/recovery limits.

== Third-party font notice ==

Inter 4.001 Latin fonts are served locally. Copyright belongs to The Inter Project Authors. The complete SIL Open Font License 1.1 is bundled in assets/fonts/OFL.txt; assets/fonts/NOTICE.txt and assets/fonts/inter-provenance.json identify the exact upstream revision, matching Google Fonts distribution and file hashes. Font bytes and existing CSS mappings are preserved.

== Changelog ==

= 1.102.90 =
* Remove the document-content review section from the quotation editor. Document defaults remain in Quotation Setup. Builder: Shashi.

= 1.102.89 =
* Centralize notification controls and require separate opt-in for outgoing WhatsApp automation. Preserve incoming replies and hosting fallbacks. Builder: Shashi.

= 1.102.88 =
* Complete focused M6 ownership boundaries and validate administrator role-permission inputs at the authenticated handler. Builder: Shashi.

= 1.102.87 =
* Separate lead timeline and comment handlers behind the existing CRM AJAX callbacks, retaining permissions, response messages and rendered markup. Builder: Shashi.

= 1.102.86 =
* Separate client-error logging, administrator log clearing and diagnostic downloads behind the existing AJAX callbacks. Builder: Shashi.

= 1.102.85 =
* Separate administrator connection diagnostics behind the existing authenticated AJAX callback. Builder: Shashi.

= 1.102.84 =
* Separate opt-in handoff email delivery while retaining the existing callback and durable retries. Builder: Shashi.

= 1.102.83 =
* Resolve reviewed package static diagnostics, prefix template variables and reject malformed request identifiers and credentials. Builder: Shashi.

= 1.102.82 =
* Validate bootstrap request metadata while preserving migration and recovery behavior. Builder: Shashi.

= 1.102.81 =
* Reject malformed quotation catalogue flags and milestone names before writes. Builder: Shashi.

Full release history is distributed in CHANGELOG.md. The previous complete readme is retained in the engineering evidence and docs/legacy/README-FULL.txt.

Reference workload qualification (1.102.88): current local WordPress 7.1.3 / PHP 8.5.12 runs exercise the selected 25–50-user / 50k-lead workload on the documented reference host. These results do not approve deployment or replace exact-revision CI. Network activation is rejected before schema writes; single-site activation remains the supported topology. See the supplied release notes for measured limits and operational acceptance.

Notification controls: General → Notifications lets an administrator enable handoff emails, routing alert emails and outgoing WhatsApp automations independently. Defaults are off. Outgoing WhatsApp now requires explicit opt-in, including upgrades with Interakt already configured; incoming replies remain independent. Live email/WhatsApp delivery testing is optional. WordPress cron and database transients remain supported; OPcache, dedicated workers and persistent object caching are optional hosting optimizations.
