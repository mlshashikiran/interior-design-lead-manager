# Cloud Telephony Integration Plan

Author / Builder: Shashi  
Status: planned adapter integration; provider-neutral storage is available in version 1.28.0.

## Recommended flow

1. The cloud telephone provider sends signed HTTPS webhook events to a provider-specific WordPress REST endpoint.
2. The adapter verifies the signature against a secret stored outside browser-visible settings, rejects stale timestamps, and rate-limits failures.
3. A provider event ID is inserted into `wp_idlm_call_logs`. The `(provider, provider_event_id)` unique key makes webhook retries idempotent.
4. The adapter maps the caller/callee to a lead using normalized phone numbers and maps the provider agent identity to a WordPress user.
5. Only verified outbound call events create an `outbound_call` activity used by the six-day compliance gate. Browser-submitted metadata must never be treated as provider verification.
6. Recordings remain private at the provider. Store a short-lived or protected URL plus an optional content hash; proxy playback only after a capability and nonce check.

## Security controls

- Use HMAC/signature verification with constant-time comparison, timestamp tolerance, replay protection, and HTTPS only.
- Keep webhook secrets encrypted or in environment configuration; never expose them through localized JavaScript.
- Store the minimum payload required for audit, redact phone numbers and credentials from diagnostics, and define retention periods for metadata and recordings.
- Log actor, provider, direction, outcome, start time, duration, event ID, and verification result. Do not allow representatives to edit provider-verified records.
- Add an administrator reconciliation report for unmatched calls, duplicate events, signature failures, and provider/API outages.

## Adapter contract

Each provider adapter should normalize events into: `provider`, `provider_event_id`, `lead_id`, `user_id`, `direction`, `outcome`, `started_at`, `duration_seconds`, `recording_url`, `recording_hash`, and redacted `metadata`. This keeps Exotel, Knowlarity, MyOperator, Twilio, or another service interchangeable without changing the compliance engine.
