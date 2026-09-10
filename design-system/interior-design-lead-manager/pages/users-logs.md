# Users and Logs — Page Rules

Phase 7 is implemented. Users & Access and both operational log views now follow the responsive, semantic, feedback, and shared-dialog rules below; administrator capabilities, action-specific user nonces, log-read nonce checks, and retry behavior remain unchanged.

- Users directory keeps invite and directory tasks distinct. Search/filter controls wrap cleanly on phones rather than relying on page-level horizontal scrolling.
- A user row exposes name, email, role, access state, and Edit disclosure. Suspended state includes text and is never colour-only.
- Prevent self-lockout and last-administrator changes in both UI copy and server policy.
- Log pages default to event, object/lead, status, time, and concise result. Large payloads load on demand in a labelled, focus-managed detail dialog.
- Logs provide date/status/search filters, copyable request identifiers, wrap long tokens safely, and distinguish empty results from loading or permission errors.
