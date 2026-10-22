# Publisher-hosted Facebook Connect service

Builder: Shashi. Local, unreleased implementation, 2026-10-17. This service is deployed once by the CRM publisher, separately from customer WordPress installations. It is excluded from the distributable WordPress plugin ZIP. No live Meta app, deployment or customer connection has been created.

## Customer experience

In CRM Settings → Lead capture, the customer clicks **Connect Facebook**, approves the Facebook permissions, chooses their Page and ticks individual active forms, then clicks **Start syncing selected forms**. The service subscribes the Page and the CRM starts its existing importer. Customer-site browser traffic contains neither Meta tokens nor the publisher app secret. **Change Page or forms** starts a fresh consent session; **Disconnect Facebook** stops local sync and deletes the hosted connection.

Each installation receives its own provisioned credential. The publisher installs it during CRM deployment; customers do not create Meta developer apps or copy app secrets, access tokens, webhook URLs or form IDs.

## Runtime and deployment

Requirements: PHP **8.1+**, PDO SQLite, Sodium, cURL, HTTPS and a persistent private filesystem directory. Deploy only this directory's `public/` as the web document root, with all routes falling back to `public/index.php`. `src/`, `config.php`, SQLite files, environment values and provisioning tools must be outside the public document root. The Apache `.htaccess` assumes a dedicated root hostname; the example Nginx configuration below does likewise.

Use one service instance/private SQLite database on a local filesystem. This initial implementation is not a horizontally distributed or multi-region service. Meta/provider quotas, SQLite writer contention, backup/restore, credential rotation and production throughput require staging qualification before admitting customers.

Supply these protected environment variables to PHP-FPM and the CLI:

| Variable | Value |
|---|---|
| `IDLM_FB_BASE_URL` | Dedicated public HTTPS origin, e.g. your chosen connection-service hostname |
| `IDLM_FB_APP_ID` | Publisher-owned Meta app ID |
| `IDLM_FB_APP_SECRET` | Publisher app secret, stored only on this server |
| `IDLM_FB_LOGIN_CONFIG_ID` | Facebook Login for Business configuration ID; configure a **User access token** flow with lead/Page permissions |
| `IDLM_FB_VERIFY_TOKEN` | A random webhook verification value |
| `IDLM_FB_DATABASE` | Absolute SQLite file path in a private directory outside `public/` |
| `IDLM_FB_ENCRYPTION_KEY` | Base64-encoded 32 random bytes; keep stable across restarts and backups |

Create the private data directory with owner-only access. Run PHP with an owner-only umask and limit filesystem access to the service account. Preserve the encryption key independently in secure key storage. Losing it makes stored credentials unreadable; do not silently regenerate it. Do not log request bodies, authorization headers, provisioning output or OAuth query strings. The public controller returns generic failures and does not print provider exceptions.

The publisher must supply a real privacy policy, support contact and terms, then complete Meta's current business verification, permission/access review and other onboarding requirements for external businesses. Confirm the exact permission set in the app's live dashboard; the code does not certify approval. The Login for Business configuration must grant access to Page discovery, lead forms/retrieval and Page webhook management. Additional dependent permissions may be required by Meta.

Register these URLs in the publisher Meta app using the chosen service origin:

- Valid OAuth redirect URI: `/oauth/callback`
- Page webhook callback: `/meta/webhook`, subscribed field: `leadgen`
- Deauthorization callback: `/meta/deauthorize`
- Data deletion callback: `/meta/delete-data`

The webhook callback is registered once. Per-Page subscription is performed by **Start syncing**, using the customer's granted Page token. The app must be usable by the customers involved; an app-role test is not proof of public production access.

### Example Nginx route configuration

Replace the document root, PHP-FPM socket and hostname with the actual deployment values; provision TLS separately. This is a route example, not a deployed configuration.

```nginx
root /srv/idlm-facebook-connect/public;
index index.php;
autoindex off;
client_max_body_size 256k;
location / { try_files $uri /index.php?$query_string; }
location = /oauth/callback {
    access_log off;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/idlm-facebook.sock;
}
location = /index.php {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root/index.php;
    fastcgi_pass unix:/run/php/idlm-facebook.sock;
}
location ~ \.php$ { return 404; }
location ~ /\. { deny all; }
```

Avoid an upstream proxy logging OAuth query strings as well. Set server-side request/worker limits, backups and monitoring appropriate to the business volume. The published service origin must remain stable because it is registered with Meta and customer installations.

## Provision an installation

On the service host, with the same protected environment as PHP-FPM:

```sh
php bin/manage.php provision https://customer-crm.example
```

This is a publisher-only CLI operation. It returns three installation-specific `wp-config.php` definitions: `IDLM_FACEBOOK_SERVICE_URL`, `IDLM_FACEBOOK_SITE_ID`, and `IDLM_FACEBOOK_SITE_KEY`. Install them in that customer's protected WordPress configuration, not in the plugin source or a shared/public artifact. Provision using the canonical CRM origin (include the WordPress path if installed under a subdirectory). Each customer uses a different site ID/key.

The customer's CRM must use HTTPS. Local plain-HTTP development can exercise mocked tests; it is deliberately refused by the real Connect flow. The publisher key and Meta app secret never belong in customer WordPress configuration.

Revoke a lost/retired installation:

```sh
php bin/manage.php disable SITE_ID
```

This removes the site's pending authorizations, hosted token and queued events. Reprovision and update the protected installation configuration to rotate its key. Customer WordPress site keys are not included in portable CRM exports. Same-site copies/restores must not be operated as separate live installations with a duplicated key.

## Delivery, limits and revocation

- HMAC-authenticated, timestamped requests and authenticated responses isolate installations. Nonces reject request replay. Publisher provisioning fixes the return origin; OAuth sessions have random, single-use state, a separate server-side claim, a 15-minute expiry, and a WordPress user/session/recovery binding.
- A new connection attempt invalidates older pending attempts for the installation. Disconnect/disable fences an in-flight selection so a delayed subscription response cannot restore a cancelled connection. Existing active sync continues while a new authorization is pending.
- The service exchanges the OAuth code and extends the user token server-side before discovering Page tokens. Tokens and pending authorization data are encrypted at rest with Sodium. Page/form names are returned to the browser; tokens are not. Provider pagination URLs, which may contain tokens, are never forwarded.
- One Page and up to 20 selected forms are active per CRM installation. An already-connected Page cannot be silently claimed by another installation. A deliberate Page migration requires the existing installation to disconnect or the publisher to disable it first.
- Signed Meta notifications store only lead/Page/form IDs and the destination installation. Customer WordPress polls up to 25 notifications through its existing minute scheduler and acknowledges only after durable local intake. Actual latency depends on cron; this is not an instantaneous push guarantee. The existing importer fetches selected lead details through the service. Contact answers pass through memory to the CRM and are not stored in the broker database.
- The read proxy permits only selected-form lead lists and individual leads verified against selected forms. It is not a general Graph API proxy. It never calls a customer-supplied network destination; it redirects only to provisioned origins and talks to Meta's fixed HTTPS Graph host.
- Tokens are read fresh from the active connection. Reconnect establishes a new connection identity. Queued events from an old selection cannot be acknowledged as a new connection. Local recovery generation checks continue to fence old CRM intake.
- Disconnect deletes the service token and stops routing immediately. It deliberately does not remove the shared app from the Page, so another workflow is not accidentally unsubscribed; without an active connection, subsequent notifications are ignored. Facebook's own permission removal invokes the signed deauthorization endpoint. Signed data-deletion requests remove that app user's connection records and provide an opaque, expiring deletion-status receipt. These callbacks remove broker data; they do not silently delete leads already imported into an independently operated customer CRM.

Run the retention CLI at least every 15 minutes:

```sh
php bin/manage.php prune
```

Pending OAuth sessions expire after 15 minutes, notification IDs after seven days, nonce receipts after ten minutes and deletion confirmation receipts after thirty days. Expired sessions fail closed even before physical pruning. Database backups may retain historical credentials/IDs; apply an explicit backup retention and erasure policy and preserve the matching encryption key securely.

## Verification and launch

Run from the parent plugin root:

```sh
php tests/facebook-service.php
php tests/facebook-connect-runtime.php
php tests/run.php
```

The first two are PHP 8.1+ service/client suites and mock all Meta/HTTP calls. They cover OAuth state, tenant isolation, named form selection, exact source restrictions, response proofs, replay, expiry, encrypted storage, Page ownership, subscription confirmation, cancellation races, notification acknowledgment, disconnect and deletion. The customer plugin remains PHP 7.4-compatible; this service's files must stay outside its distribution ZIP.

Before launch, verify with an approved staging app and two distinct customer installations: real consent and denial; multi-page/form pagination; one generated lead per selected form; unselected-form exclusion; reconnect and revoked grants; scheduler outage/catch-up; duplicate delivery; restart and encrypted-backup recovery; and source isolation. Test that enabling the hosted connection does not duplicate outcomes already sent by Pabbly/Interakt. Live Meta acceptance, production hosting, app approval and load qualification are not completed by local mocked tests.
