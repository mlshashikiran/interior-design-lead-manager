# Settings Registry

Builder: Shashi

## Storage policy

- Secrets use `IDLM_Secret_Store` or environment constants and are never added to grouped settings.
- Routing, ownership, work-hours, handoff, and fallback configuration is read through `IDLM_Settings`.
- `idlm_routing_settings` is the grouped non-secret routing snapshot.
- Existing individual `idlm_*` routing keys are mirrored on every supported settings save and remain compatibility contracts for older releases and extensions.
- `idlm_settings_version` controls the idempotent legacy-to-group migration.

## Grouped routing schema

The grouped array contains the full legacy option names as keys:

- Visibility and pools: `idlm_reps_see_all_leads`, `idlm_active_assignment_reps`, `idlm_active_pre_sales_reps`.
- Strategy and mappings: `idlm_assignment_mode`, `idlm_rep_studio_assignments`, `idlm_source_assignment_mappings`, `idlm_studio_mismatch_behavior`.
- Handoff policy: `idlm_handoff_task_policy`, `idlm_handoff_notifications_enabled`, `idlm_routing_exception_notifications_enabled`.
- Capacity and hours: `idlm_max_active_leads_per_rep`, `idlm_working_hours_start`, `idlm_working_hours_end`, `idlm_working_days`.
- Pre-sales targets: `idlm_presales_calls_per_day`, `idlm_presales_whatsapp_per_day`.
- Fallbacks: `idlm_fallback_assignment_enabled`, `idlm_fallback_assignment_hours`, `idlm_qualified_fallback_enabled`, `idlm_qualified_fallback_hours`, `idlm_qualified_fallback_end_at`, `idlm_qualified_fallback_include_existing`.

New non-secret routing fields should be added to `IDLM_Settings::routing_defaults()`, written by the protected settings handler, and mirrored only when an established standalone key must remain compatible.

## Other option families

- `idlm_retention_policy`: grouped touchpoint, integration-log, and telemetry retention limits. Per-lead audience-sync and CAPI diagnostic activity, plus CAPI audit-table records, are retained for seven days independently so recurring integration diagnostics do not become CRM-history bloat.
- `idlm_quote_settings`: grouped quotation identity, branding, defaults, cover, and closing content.
- `idlm_white_label_settings`: grouped non-secret CRM brand identity, WordPress attachment IDs, accessible semantic colours, workspace/sign-in copy, support contact, footer text, and attribution visibility. Read through `IDLM_Settings::get_white_label()` so defaults, normalization, and frontend token ownership remain centralized.
- `idlm_field_mappings_{platform}`: dynamic inbound field maps retained per platform.
- `idlm_custom_stages` and `idlm_kanban_stage_totals`: ordered pipeline labels and their per-stage header-total source. The General settings pipeline editor submits the established structured and legacy vocabulary fields, and total-map keys remain URL-encoded stage labels for compatibility.
- `idlm_custom_call_statuses`, `idlm_custom_lost_reasons`, `idlm_custom_studios`, `idlm_custom_num_rooms`: reusable lead-option vocabularies. Their count-labelled settings disclosures are presentation only; all controls remain successful form controls when collapsed.
- Schema and migration markers: independent, scalar, idempotent version options.
- Integration configuration: platform IDs, feature flags, and event maps; credentials remain separate protected secrets.
- Operational status: fallback timestamps/status, queues, telemetry level, and migration progress. These are runtime state, not administrator-authored configuration.
