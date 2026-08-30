# Horizontal Deployment Readiness

Author / Builder: Shashi  
Implementation release: 1.99.5  
Status: Phase 6 preflight implemented; infrastructure qualification pending

## Purpose

This document defines the first production/scalability Phase 6 gate for running the CRM on more than one WordPress application node. The plugin reports readiness; it does not provision databases, caches, object storage, cron runners, or secrets.

The read-only preflight is visible to administrators under **Lead Manager → Settings → System Health → Horizontal Deployment Readiness**. It returns boolean state only and never exposes database hosts, storage paths, credentials, salts, or secret values.

## Current runtime-write inventory

A static audit of plugin-owned PHP runtime paths found no persistent writes to the plugin directory:

- Lead, catalogue, audience, conversion, and diagnostic exports stream directly to the requesting client.
- Quotation PDF output is browser print/save output; the plugin does not persist a binary PDF.
- Catalogue import reads the PHP-managed upload temporary file and does not retain it.
- Lead attachments use the WordPress media APIs and store attachment URLs/ownership metadata. Their cross-node availability therefore depends on the site's shared media/object-storage configuration.

Any future asynchronous export must use protected shared/object storage with authorization, expiry, and cleanup before it can be considered multi-node safe.

## Deployment declarations

Define these booleans in protected environment configuration only after the corresponding infrastructure has been provisioned and tested:

```php
define('IDLM_MULTI_NODE_MODE', true);
define('IDLM_SHARED_DATABASE', true);
define('IDLM_SHARED_MEDIA_STORAGE', true);
define('IDLM_SHARED_CONFIGURATION', true);
define('IDLM_SHARED_OBJECT_CACHE', true);
define('IDLM_MANAGED_CRON_RUNNER', true);
define('DISABLE_WP_CRON', true);
```

These constants are operator attestations, not feature toggles that create shared infrastructure. `IDLM_SHARED_CONFIGURATION` means every node receives identical WordPress salts, IDLM environment constants, and protected credential material. `IDLM_SHARED_OBJECT_CACHE` is valid only when WordPress also detects an external object cache and every node uses the same cache service/namespace. `IDLM_MANAGED_CRON_RUNNER` means a monitored system runner is already invoking WordPress cron/Action Scheduler processing; it is valid only together with `DISABLE_WP_CRON`.

The remaining gates are detected directly:

- A persistent WordPress object cache must be active and shared across nodes.
- Action Scheduler must be initialized for coordinated background claims and retries.
- Existing database advisory locks/idempotency remain the coordination boundary for assignments, number reservations, and deliveries.

## Qualification sequence

1. Provision and restore-test the shared transactional database.
2. Configure shared WordPress media/object storage and verify existing/new lead attachments from both nodes.
3. Configure a shared persistent object cache; monitor connection failures and evictions.
4. Install Action Scheduler and start dedicated, monitored runners.
5. Start the managed system cron runner, then disable traffic-driven WP-Cron.
6. Distribute identical protected configuration/secrets to both nodes and rotate production credentials.
7. Add the declarations above and confirm that the System Health preflight reports **Ready**.
8. Run Phase 8 two-node termination, duplicate-webhook, quotation-numbering, queue-claim, cache-failure, media, and rollback tests before routing production traffic.

## Stop conditions

Do not enable multi-node traffic if any readiness check is blocked, if uploads or protected output depend on one node, if scheduled work can be claimed twice, if salts/secrets differ, or if terminating one node loses an accepted request or queued effect.
