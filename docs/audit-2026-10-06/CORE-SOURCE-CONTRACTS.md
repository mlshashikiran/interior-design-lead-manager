# Core public signatures and registrations

Author: **Shashi**. Generated from the reviewed working tree on 2026-10-06. Public signatures are compatibility contracts; business behavior, validation, persistence, authorization, error/retry and coverage are described by module in [CORE-REVIEW.md](CORE-REVIEW.md). All source locations are one-based and linked. This is an explicit core boundary, not the complete plugin inventory.

## interior-design-lead-manager.php

SHA-256: `5e22b5e459907c8e986256d9e31b0e9f7ff83ec24cdd2811a9fe069b7681f3ad`

| Source | Public signature |
|---|---|
| [80](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/interior-design-lead-manager.php:80>) | `function idlm_activate_plugin()` |
| [105](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/interior-design-lead-manager.php:105>) | `function idlm_register_crm_roles_and_capabilities()` |
| [187](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/interior-design-lead-manager.php:187>) | `function idlm_get_crm_role_slugs($scope = 'all')` |
| [217](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/interior-design-lead-manager.php:217>) | `function idlm_deactivate_plugin()` |
| [225](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/interior-design-lead-manager.php:225>) | `function idlm_boot_plugin()` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|
| [79](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/interior-design-lead-manager.php:79>) | `register_activation_hook(__FILE__, 'idlm_activate_plugin');` |
| [216](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/interior-design-lead-manager.php:216>) | `register_deactivation_hook(__FILE__, 'idlm_deactivate_plugin');` |
| [224](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/interior-design-lead-manager.php:224>) | `add_action('plugins_loaded', 'idlm_boot_plugin');` |

## includes/class-db.php

SHA-256: `a027a627468d662ccb7e02526ef2ec5cca9825fa89c5aaa1754d54df5105caf0`

| Source | Public signature |
|---|---|
| [9](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:9>) | `public static function get_last_validation_error()` |
| [12](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:12>) | `public static function init()` |
| [80](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:80>) | `public static function cleanup_stale_pending_tasks()` |
| [108](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:108>) | `public static function archive_pending_tasks_for_lost_lead($lead_id)` |
| [359](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:359>) | `public static function create_tables()` |
| [687](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:687>) | `public static function budget_to_rupees($budget)` |
| [710](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:710>) | `public static function get_presales_outreach_targets()` |
| [721](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:721>) | `public static function calculate_presales_outreach_progress($daily_rows, $targets = null)` |
| [766](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:766>) | `public static function refresh_presales_task_titles()` |
| [805](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:805>) | `public static function get_followup_compliance($lead_id)` |
| [864](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:864>) | `public static function create_presales_followup_tasks($lead_id, $assigned_rep_id)` |
| [910](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:910>) | `public static function ensure_qualified_handoff_task($lead_id, $assigned_rep_id)` |
| [946](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:946>) | `public static function sync_presales_task_status($lead_id)` |
| [992](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:992>) | `public static function reschedule_pending_presales_tasks($lead_id)` |
| [1024](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:1024>) | `public static function roll_forward_presales_tasks()` |
| [1095](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:1095>) | `public static function upsert_inbound_lead($data, $platform, $payload = array())` |
| [1102](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:1102>) | `public static function insert_lead($data)` |
| [1109](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:1109>) | `public static function update_lead($lead_id, $data)` |
| [1123](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:1123>) | `public static function find_canonical_duplicate_id($lead_id)` |
| [1155](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:1155>) | `public static function log_activity($lead_id, $activity_type, $details, $user_id = 0)` |
| [1178](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:1178>) | `public static function record_reporting_event($lead_id, $event_type, $from_value, $to_value, $reason = '', $event_origin = '', $actor_user_id = 0, $occurred_at = null, $amount = null)` |
| [1207](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:1207>) | `public static function log_capi_event($lead_id, $platform, $event_name, $payload, $response, $status)` |
| [1226](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-db.php:1226>) | `public static function log_audience_sync($platform, $audience_id, $lead_count, $payload, $response, $status)` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-lead-lifecycle-service.php

SHA-256: `da94acb4a6e8cf8d68506b5f7b0f3d361b652439f447152ecf924a401d9bd27a`

| Source | Public signature |
|---|---|
| [14](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-lifecycle-service.php:14>) | `public static function upsert_inbound_lead($data, $platform, $payload = array())` |
| [311](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-lifecycle-service.php:311>) | `public static function insert_lead($data, $skip_duplicate_detection = false)` |
| [541](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-lifecycle-service.php:541>) | `public static function update_lead($lead_id, $data)` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|
| [38](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-lifecycle-service.php:38>) | `do_action('idlm_lead_attribution_merged', $canonical_id, $updates);` |
| [521](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-lifecycle-service.php:521>) | `do_action('idlm_lead_attribution_merged', $duplicate_of_id, $original_updates);` |
| [528](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-lifecycle-service.php:528>) | `do_action('idlm_lead_stage_converted', $lead_id, 'New', (object) array('stage' => ''));` |
| [850](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-lifecycle-service.php:850>) | `do_action('idlm_lead_stage_converted', $lead_id, $value, $old_lead);` |
| [852](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-lifecycle-service.php:852>) | `do_action('idlm_lead_stage_converted', $lead_id, 'Qualified', $old_lead);` |
| [869](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-lifecycle-service.php:869>) | `do_action('idlm_trigger_interakt_event', $lead_id, $m_evt, $old_lead);` |
| [881](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-lifecycle-service.php:881>) | `do_action('idlm_lead_call_status_no_answer', $lead_id, $old_lead);` |

## includes/class-lead-access-policy.php

SHA-256: `ddaabcea5f1812a3337e9849d461f2189a5b245daf7fb943e209467e21472ee6`

| Source | Public signature |
|---|---|
| [6](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-access-policy.php:6>) | `public static function allowed_rep_roles()` |
| [13](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-access-policy.php:13>) | `public static function has_rep_access()` |
| [20](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-access-policy.php:20>) | `public static function can_assign()` |
| [25](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-access-policy.php:25>) | `public static function is_pre_sales()` |
| [30](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-access-policy.php:30>) | `public static function validate_detail_fields($fields, $existing)` |
| [50](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-access-policy.php:50>) | `public static function is_assignable_rep($user_id)` |
| [57](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-access-policy.php:57>) | `public static function pre_sales_user_ids()` |
| [64](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-access-policy.php:64>) | `public static function restriction_user_id($respect_global_visibility = true)` |
| [74](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-access-policy.php:74>) | `public static function scope_sql($alias, &$params, $view_history = false, $respect_global_visibility = true)` |
| [86](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-access-policy.php:86>) | `public static function can_access($lead_id, $view_history = false)` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-state-registry.php

SHA-256: `0e1e8a38dcee20995eb854173b84cae8d43499d5a6309a437975f119e3a61491`

| Source | Public signature |
|---|---|
| [26](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-state-registry.php:26>) | `public static function call_statuses()` |
| [36](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-state-registry.php:36>) | `public static function handoff_statuses()` |
| [44](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-state-registry.php:44>) | `public static function task_types()` |
| [48](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-state-registry.php:48>) | `public static function disqualification_reasons()` |
| [58](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-state-registry.php:58>) | `public static function values($domain)` |
| [67](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-state-registry.php:67>) | `public static function stages()` |
| [76](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-state-registry.php:76>) | `public static function stage_label($key_or_label)` |
| [80](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-state-registry.php:80>) | `public static function label($domain, $key_or_label)` |
| [86](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-state-registry.php:86>) | `public static function stage_key($label)` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-routing-state.php

SHA-256: `d716d833dad7d7d43cf197076eb9341c2698a65c27969da2350484d327db0ff9`

| Source | Public signature |
|---|---|
| [6](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-routing-state.php:6>) | `public static function install()` |
| [20](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-routing-state.php:20>) | `public static function next_index($state_key, $modulus)` |
| [39](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-routing-state.php:39>) | `public static function acquire_lock($name, $timeout = 0)` |
| [44](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-routing-state.php:44>) | `public static function release_lock($name)` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-task-queue-service.php

SHA-256: `d96e11077951575fbc813a70ead0d4c70ffce69db35bad9bedb6d1a4318bc6b3`

| Source | Public signature |
|---|---|
| [13](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-task-queue-service.php:13>) | `public static function ajax_reschedule()` |
| [39](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-task-queue-service.php:39>) | `public static function ajax_cleanup_stale()` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-lead-deletion-service.php

SHA-256: `920482435fcdfca13e12304679663956507f6ce420c62aca7a4ecdb0d7ddc144`

| Source | Public signature |
|---|---|
| [10](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-deletion-service.php:10>) | `public static function delete($lead_id) { return self::delete_many(array($lead_id)); }` |
| [21](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-deletion-service.php:21>) | `public static function reset_manifest()` |
| [46](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-deletion-service.php:46>) | `public static function reset_all($expected_lead_count = null)` |
| [157](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-deletion-service.php:157>) | `public static function delete_many($lead_ids)` |
| [177](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-deletion-service.php:177>) | `public static function audit_orphans($repair = false)` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|
| [148](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-lead-deletion-service.php:148>) | `wp_unschedule_hook($hook);` |

## includes/class-data-retention.php

SHA-256: `73350beae765cfbfc61ca65dd625c91a9cd0f47a593c4952f13dc7ca7f8bdec5`

| Source | Public signature |
|---|---|
| [13](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-data-retention.php:13>) | `public static function init()` |
| [26](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-data-retention.php:26>) | `public static function clear()` |
| [30](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-data-retention.php:30>) | `public static function redact($value)` |
| [45](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-data-retention.php:45>) | `public static function summarize_audience_payload($payload)` |
| [67](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-data-retention.php:67>) | `public static function prune()` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|
| [14](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-data-retention.php:14>) | `add_action(self::HOOK, array(__CLASS__, 'prune'));` |
| [22](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-data-retention.php:22>) | `wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);` |
| [27](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-data-retention.php:27>) | `wp_clear_scheduled_hook(self::HOOK);` |

## includes/class-read-model-cache.php

SHA-256: `5867826a38208f55bf2a43e8bff7ae56a87d6c905b00a0f89d09ae1c8c224a5f`

| Source | Public signature |
|---|---|
| [25](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-read-model-cache.php:25>) | `public static function init()` |
| [47](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-read-model-cache.php:47>) | `public static function remember($namespace, $context, $builder, $ttl = self::DEFAULT_TTL, $stale_ttl = self::DEFAULT_STALE_TTL)` |
| [90](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-read-model-cache.php:90>) | `public static function invalidate($reason = '')` |
| [99](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-read-model-cache.php:99>) | `public static function invalidate_on_option_change($option)` |
| [113](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-read-model-cache.php:113>) | `public static function invalidate_after_retention()` |
| [117](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-read-model-cache.php:117>) | `public static function generation()` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|
| [31](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-read-model-cache.php:31>) | `add_action('added_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 2);` |
| [32](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-read-model-cache.php:32>) | `add_action('updated_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 3);` |
| [33](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-read-model-cache.php:33>) | `add_action('deleted_option', array(__CLASS__, 'invalidate_on_option_change'), 10, 1);` |
| [34](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-read-model-cache.php:34>) | `add_action(IDLM_Data_Retention::HOOK, array(__CLASS__, 'invalidate_after_retention'), 20);` |

## includes/class-cursor-pagination.php

SHA-256: `ea519320fe449ccb6a5fae7894f68139b0191310ae99afc0fa1b1450a3778a8b`

| Source | Public signature |
|---|---|
| [26](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-cursor-pagination.php:26>) | `public static function encode($context, array $position, array $scope, $ttl = self::DEFAULT_TTL)` |
| [49](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-cursor-pagination.php:49>) | `public static function decode($token, $context, array $scope)` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-dashboard.php

SHA-256: `5315546f9bd48ef33617a9580ff040802d03ac34216e06e72361be573553e779`

| Source | Public signature |
|---|---|
| [8](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-dashboard.php:8>) | `public static function access_context()` |
| [35](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-dashboard.php:35>) | `public static function filter_stats($stats)` |
| [66](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-dashboard.php:66>) | `public static function get_records($kind, $value, $period, $start = '', $end = '', $page = 1)` |
| [130](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-dashboard.php:130>) | `public static function get_stats($period = 'this_month', $custom_start = '', $custom_end = '', $filters = array())` |
| [139](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-dashboard.php:139>) | `public static function calculate_stats($period = 'this_month', $custom_start = '', $custom_end = '', $filters = array())` |
| [1524](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-dashboard.php:1524>) | `public static function get_date_ranges($period, $custom_start = '', $custom_end = '')` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-dashboard-read-model.php

SHA-256: `7e529529e808bce4cbffc0e2942efbf7cf54a7ae2e3b1727f361c994816b8168`

| Source | Public signature |
|---|---|
| [17](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-dashboard-read-model.php:17>) | `public static function get_stats($period, $custom_start = '', $custom_end = '', $filters = array())` |
| [33](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-dashboard-read-model.php:33>) | `public static function cache_context($period, $custom_start = '', $custom_end = '')` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-admin.php

SHA-256: `ac19ec32097df7c584c5dfdf26746127712dd9cfd2ec7ac954ef1d1ec46f63e4`

| Source | Public signature |
|---|---|
| [7](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:7>) | `public static function init()` |
| [71](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:71>) | `public static function get_allowed_rep_roles()` |
| [78](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:78>) | `public static function current_user_can_assign_leads()` |
| [85](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:85>) | `public static function current_user_is_pre_sales()` |
| [92](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:92>) | `public static function get_qualification_status_label($lead)` |
| [99](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:99>) | `public static function is_assignable_rep($user_id)` |
| [106](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:106>) | `public static function get_pre_sales_user_ids()` |
| [113](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:113>) | `public static function current_user_can_access_lead($lead_id)` |
| [121](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:121>) | `public static function current_user_can_view_lead($lead_id)` |
| [128](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:128>) | `public static function format_datetime($mysql_date, $format = 'M d, Y h:i A')` |
| [141](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:141>) | `public static function render_activity_details($details)` |
| [148](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:148>) | `public static function current_user_has_rep_access()` |
| [155](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:155>) | `public static function get_whatsapp_read_activity_ids()` |
| [162](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:162>) | `public static function mark_whatsapp_reply_read($lead_id, $activity_id)` |
| [169](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:169>) | `public static function get_unread_whatsapp_lead_ids()` |
| [178](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:178>) | `public static function get_unread_whatsapp_count()` |
| [185](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:185>) | `public static function get_whatsapp_received_lead_ids()` |
| [192](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:192>) | `public static function get_kanban_stage_total_mappings($stages)` |
| [200](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:200>) | `public static function get_reminder_rules()` |
| [207](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:207>) | `public static function check_lead_overdue_status($lead, $rules = null)` |
| [211](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:211>) | `public static function get_pending_calls_count()` |
| [218](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:218>) | `public static function register_core_assets()` |
| [225](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:225>) | `public static function enqueue_assets($hook)` |
| [232](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:232>) | `public static function enqueue_frontend_assets()` |
| [239](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:239>) | `public static function render_leads_list_shortcode()` |
| [246](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:246>) | `public static function render_dashboard_shortcode()` |
| [253](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:253>) | `public static function render_kanban_shortcode()` |
| [263](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:263>) | `public static function render_workspace_shortcode()` |
| [270](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:270>) | `public static function render_my_account_page()` |
| [277](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:277>) | `public static function handle_update_my_account()` |
| [282](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:282>) | `public static function handle_rotate_api_credential()` |
| [285](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:285>) | `public static function handle_create_user() { return IDLM_Admin_Users_Controller::handle_create(); }` |
| [286](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:286>) | `public static function handle_update_user() { return IDLM_Admin_Users_Controller::handle_update(); }` |
| [287](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:287>) | `public static function handle_update_role_scopes() { return IDLM_Admin_Users_Controller::handle_update_role_scopes(); }` |
| [290](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:290>) | `public static function register_admin_menus()` |
| [303](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:303>) | `public static function redirect_legacy_admin_page()` |
| [348](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:348>) | `public static function admin_utility_url()` |
| [353](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:353>) | `public static function render_admin_utility_page()` |
| [377](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:377>) | `public static function render_dashboard_page()` |
| [380](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:380>) | `public static function render_users_access_page() { return IDLM_Admin_Users_Controller::render(); }` |
| [385](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:385>) | `public static function render_leads_list_page()` |
| [392](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:392>) | `public static function render_common_overlays()` |
| [399](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:399>) | `public static function render_kanban_page()` |
| [406](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:406>) | `public static function render_capi_logs_page()` |
| [413](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:413>) | `public static function render_audience_sync_logs_page()` |
| [420](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:420>) | `public static function render_settings_page()` |
| [423](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:423>) | `public static function ajax_get_lead_details() { return IDLM_Admin_Ajax::ajax_get_lead_details(); }` |
| [424](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:424>) | `public static function ajax_get_lead_whatsapp() { return IDLM_Admin_Lead_Reads::ajax_get_lead_whatsapp(); }` |
| [425](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:425>) | `public static function ajax_mark_whatsapp_messages_read() { return IDLM_Admin_Lead_Reads::ajax_mark_whatsapp_messages_read(); }` |
| [426](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:426>) | `public static function ajax_get_lead_activity() { return IDLM_Admin_Ajax::ajax_get_lead_activity(); }` |
| [427](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:427>) | `public static function ajax_get_capi_log_payload() { return IDLM_Admin_Logs_Renderer::ajax_get_capi_log_payload(); }` |
| [428](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:428>) | `public static function ajax_get_audience_log_payload() { return IDLM_Admin_Logs_Renderer::ajax_get_audience_log_payload(); }` |
| [429](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:429>) | `public static function ajax_quick_edit_lead() { return IDLM_Admin_Ajax::ajax_quick_edit_lead(); }` |
| [430](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:430>) | `public static function ajax_log_presales_touch() { return IDLM_Admin_Ajax::ajax_log_presales_touch(); }` |
| [431](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:431>) | `public static function ajax_presales_decision() { return IDLM_Admin_Ajax::ajax_presales_decision(); }` |
| [432](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:432>) | `public static function ajax_save_activity_comment() { return IDLM_Admin_Ajax::ajax_save_activity_comment(); }` |
| [433](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:433>) | `public static function ajax_edit_activity_comment() { return IDLM_Admin_Ajax::ajax_edit_activity_comment(); }` |
| [434](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:434>) | `public static function ajax_delete_activity_comment() { return IDLM_Admin_Ajax::ajax_delete_activity_comment(); }` |
| [435](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:435>) | `public static function ajax_export_google_conversions() { return IDLM_Admin_Ajax::ajax_export_google_conversions(); }` |
| [436](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:436>) | `public static function ajax_export_leads() { return IDLM_Admin_Ajax::ajax_export_leads(); }` |
| [437](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:437>) | `public static function ajax_add_lead_manual() { return IDLM_Admin_Ajax::ajax_add_lead_manual(); }` |
| [438](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:438>) | `public static function ajax_trigger_manual_audience_sync() { return IDLM_Admin_Ajax::ajax_trigger_manual_audience_sync(); }` |
| [439](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:439>) | `public static function ajax_test_api_connection() { return IDLM_Admin_Ajax::ajax_test_api_connection(); }` |
| [440](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:440>) | `public static function ajax_retry_capi_events() { return IDLM_Admin_Ajax::ajax_retry_capi_events(); }` |
| [441](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:441>) | `public static function ajax_log_js_error() { return IDLM_Admin_Ajax::ajax_log_js_error(); }` |
| [442](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:442>) | `public static function ajax_clear_telemetry_logs() { return IDLM_Admin_Ajax::ajax_clear_telemetry_logs(); }` |
| [443](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:443>) | `public static function ajax_download_diagnostic_report() { return IDLM_Admin_Ajax::ajax_download_diagnostic_report(); }` |
| [444](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:444>) | `public static function ajax_save_routing_settings() { return IDLM_Admin_Ajax::ajax_save_routing_settings(); }` |
| [445](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:445>) | `public static function ajax_run_fallback_now() { return IDLM_Admin_Ajax::ajax_run_fallback_now(); }` |
| [446](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:446>) | `public static function ajax_get_mapped_past_leads() { return IDLM_Admin_Ajax::ajax_get_mapped_past_leads(); }` |
| [447](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:447>) | `public static function ajax_sync_mapped_past_batch() { return IDLM_Admin_Ajax::ajax_sync_mapped_past_batch(); }` |
| [448](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:448>) | `public static function ajax_bulk_action() { return IDLM_Admin_Ajax::ajax_bulk_action(); }` |
| [449](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:449>) | `public static function ajax_get_leads_table() { return IDLM_Admin_Ajax::ajax_get_leads_table(); }` |
| [450](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:450>) | `public static function ajax_get_kanban_leads() { return IDLM_Admin_Ajax::ajax_get_kanban_leads(); }` |
| [451](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:451>) | `public static function ajax_get_dashboard_stats() { return IDLM_Admin_Ajax::ajax_get_dashboard_stats(); }` |
| [452](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:452>) | `public static function ajax_save_lead_details() { return IDLM_Admin_Ajax::ajax_save_lead_details(); }` |
| [453](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:453>) | `public static function ajax_upload_lead_file() { return IDLM_Admin_Ajax::ajax_upload_lead_file(); }` |
| [454](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:454>) | `public static function ajax_get_total_leads_count() { return IDLM_Admin_Ajax::ajax_get_total_leads_count(); }` |
| [455](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:455>) | `public static function ajax_reset_crm_data() { return IDLM_Admin_Ajax::ajax_reset_crm_data(); }` |
| [456](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:456>) | `public static function ajax_delete_lead() { return IDLM_Admin_Ajax::ajax_delete_lead(); }` |
| [457](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:457>) | `public static function ajax_import_leads_batch() { return IDLM_Admin_Ajax::ajax_import_leads_batch(); }` |
| [458](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:458>) | `public static function ajax_get_lead_tasks() { return IDLM_Admin_Ajax::ajax_get_lead_tasks(); }` |
| [459](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:459>) | `public static function ajax_add_lead_task() { return IDLM_Admin_Ajax::ajax_add_lead_task(); }` |
| [460](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:460>) | `public static function ajax_toggle_task_status() { return IDLM_Admin_Ajax::ajax_toggle_task_status(); }` |
| [461](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:461>) | `public static function ajax_delete_lead_task() { return IDLM_Admin_Ajax::ajax_delete_lead_task(); }` |
| [462](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:462>) | `public static function ajax_get_rep_tasks() { return IDLM_Admin_Ajax::ajax_get_rep_tasks(); }` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|
| [8](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:8>) | `add_action('admin_menu', array(__CLASS__, 'register_admin_menus'));` |
| [9](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:9>) | `add_action('admin_init', array(__CLASS__, 'redirect_legacy_admin_page'));` |
| [10](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:10>) | `add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));` |
| [11](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:11>) | `add_action('admin_post_idlm_update_my_account', array(__CLASS__, 'handle_update_my_account'));` |
| [12](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:12>) | `add_action('admin_post_idlm_rotate_api_credential', array(__CLASS__, 'handle_rotate_api_credential'));` |
| [13](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:13>) | `add_action('admin_post_idlm_create_user', array(__CLASS__, 'handle_create_user'));` |
| [14](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:14>) | `add_action('admin_post_idlm_update_user', array(__CLASS__, 'handle_update_user'));` |
| [15](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:15>) | `add_action('admin_post_idlm_update_role_scopes', array(__CLASS__, 'handle_update_role_scopes'));` |
| [18](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:18>) | `add_shortcode('idlm_leads_list', array(__CLASS__, 'render_leads_list_shortcode'));` |
| [19](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:19>) | `add_shortcode('idlm_dashboard', array(__CLASS__, 'render_dashboard_shortcode'));` |
| [20](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:20>) | `add_shortcode('idlm_kanban', array(__CLASS__, 'render_kanban_shortcode'));` |
| [21](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:21>) | `add_shortcode('idlm_workspace', array(__CLASS__, 'render_workspace_shortcode'));` |
| [24](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:24>) | `add_action('wp_ajax_idlm_get_lead_details', array(__CLASS__, 'ajax_get_lead_details'));` |
| [25](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:25>) | `add_action('wp_ajax_idlm_get_lead_whatsapp', array(__CLASS__, 'ajax_get_lead_whatsapp'));` |
| [26](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:26>) | `add_action('wp_ajax_idlm_mark_whatsapp_messages_read', array(__CLASS__, 'ajax_mark_whatsapp_messages_read'));` |
| [27](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:27>) | `add_action('wp_ajax_idlm_get_lead_activity', array(__CLASS__, 'ajax_get_lead_activity'));` |
| [28](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:28>) | `add_action('wp_ajax_idlm_get_capi_log_payload', array(__CLASS__, 'ajax_get_capi_log_payload'));` |
| [29](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:29>) | `add_action('wp_ajax_idlm_get_audience_log_payload', array(__CLASS__, 'ajax_get_audience_log_payload'));` |
| [30](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:30>) | `add_action('wp_ajax_idlm_quick_edit_lead', array(__CLASS__, 'ajax_quick_edit_lead'));` |
| [31](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:31>) | `add_action('wp_ajax_idlm_save_activity_comment', array(__CLASS__, 'ajax_save_activity_comment'));` |
| [32](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:32>) | `add_action('wp_ajax_idlm_edit_activity_comment', array(__CLASS__, 'ajax_edit_activity_comment'));` |
| [33](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:33>) | `add_action('wp_ajax_idlm_delete_activity_comment', array(__CLASS__, 'ajax_delete_activity_comment'));` |
| [34](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:34>) | `add_action('wp_ajax_idlm_export_leads', array(__CLASS__, 'ajax_export_leads'));` |
| [35](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:35>) | `add_action('wp_ajax_idlm_export_google_conversions', array(__CLASS__, 'ajax_export_google_conversions'));` |
| [36](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:36>) | `add_action('wp_ajax_idlm_add_lead_manual', array(__CLASS__, 'ajax_add_lead_manual'));` |
| [37](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:37>) | `add_action('wp_ajax_idlm_trigger_manual_audience_sync', array(__CLASS__, 'ajax_trigger_manual_audience_sync'));` |
| [38](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:38>) | `add_action('wp_ajax_idlm_bulk_action', array(__CLASS__, 'ajax_bulk_action'));` |
| [39](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:39>) | `add_action('wp_ajax_idlm_get_leads_table', array(__CLASS__, 'ajax_get_leads_table'));` |
| [40](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:40>) | `add_action('wp_ajax_idlm_get_kanban_leads', array(__CLASS__, 'ajax_get_kanban_leads'));` |
| [41](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:41>) | `add_action('wp_ajax_idlm_get_dashboard_stats', array(__CLASS__, 'ajax_get_dashboard_stats'));` |
| [42](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:42>) | `add_action('wp_ajax_idlm_save_lead_details', array(__CLASS__, 'ajax_save_lead_details'));` |
| [43](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:43>) | `add_action('wp_ajax_idlm_delete_lead', array(__CLASS__, 'ajax_delete_lead'));` |
| [44](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:44>) | `add_action('wp_ajax_idlm_import_leads_batch', array(__CLASS__, 'ajax_import_leads_batch'));` |
| [45](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:45>) | `add_action('wp_ajax_idlm_upload_lead_file', array(__CLASS__, 'ajax_upload_lead_file'));` |
| [46](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:46>) | `add_action('wp_ajax_idlm_get_total_leads_count', array(__CLASS__, 'ajax_get_total_leads_count'));` |
| [47](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:47>) | `add_action('wp_ajax_idlm_reset_crm_data', array(__CLASS__, 'ajax_reset_crm_data'));` |
| [48](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:48>) | `add_action('wp_ajax_idlm_test_api_connection', array(__CLASS__, 'ajax_test_api_connection'));` |
| [49](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:49>) | `add_action('wp_ajax_idlm_retry_capi_events', array(__CLASS__, 'ajax_retry_capi_events'));` |
| [50](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:50>) | `add_action('wp_ajax_idlm_log_js_error', array(__CLASS__, 'ajax_log_js_error'));` |
| [51](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:51>) | `add_action('wp_ajax_idlm_clear_telemetry_logs', array(__CLASS__, 'ajax_clear_telemetry_logs'));` |
| [52](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:52>) | `add_action('wp_ajax_idlm_download_diagnostic_report', array(__CLASS__, 'ajax_download_diagnostic_report'));` |
| [53](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:53>) | `add_action('wp_ajax_idlm_save_routing_settings', array(__CLASS__, 'ajax_save_routing_settings'));` |
| [54](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:54>) | `add_action('wp_ajax_idlm_run_fallback_now', array(__CLASS__, 'ajax_run_fallback_now'));` |
| [55](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:55>) | `add_action('wp_ajax_idlm_log_presales_touch', array(__CLASS__, 'ajax_log_presales_touch'));` |
| [56](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:56>) | `add_action('wp_ajax_idlm_presales_decision', array(__CLASS__, 'ajax_presales_decision'));` |
| [57](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:57>) | `add_action('wp_ajax_idlm_get_mapped_past_leads', array(__CLASS__, 'ajax_get_mapped_past_leads'));` |
| [58](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:58>) | `add_action('wp_ajax_idlm_sync_mapped_past_batch', array(__CLASS__, 'ajax_sync_mapped_past_batch'));` |
| [61](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:61>) | `add_action('wp_ajax_idlm_get_lead_tasks', array(__CLASS__, 'ajax_get_lead_tasks'));` |
| [62](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:62>) | `add_action('wp_ajax_idlm_add_lead_task', array(__CLASS__, 'ajax_add_lead_task'));` |
| [63](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:63>) | `add_action('wp_ajax_idlm_toggle_task_status', array(__CLASS__, 'ajax_toggle_task_status'));` |
| [64](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:64>) | `add_action('wp_ajax_idlm_delete_lead_task', array(__CLASS__, 'ajax_delete_lead_task'));` |
| [65](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin.php:65>) | `add_action('wp_ajax_idlm_get_rep_tasks', array(__CLASS__, 'ajax_get_rep_tasks'));` |

## includes/class-admin-ajax.php

SHA-256: `59745d1edac09bfd15fcedd1f9ca4bc038f417315b46d3ceee4e15bb1e07c8a3`

| Source | Public signature |
|---|---|
| [13](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:13>) | `public static function ajax_get_lead_details()` |
| [20](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:20>) | `public static function ajax_get_lead_activity()` |
| [135](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:135>) | `public static function ajax_quick_edit_lead()` |
| [268](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:268>) | `public static function ajax_log_presales_touch()` |
| [434](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:434>) | `public static function ajax_presales_decision()` |
| [474](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:474>) | `public static function ajax_save_activity_comment()` |
| [521](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:521>) | `public static function ajax_edit_activity_comment()` |
| [564](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:564>) | `public static function ajax_delete_activity_comment()` |
| [605](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:605>) | `public static function ajax_export_google_conversions()` |
| [619](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:619>) | `public static function ajax_export_leads()` |
| [891](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:891>) | `public static function ajax_add_lead_manual()` |
| [969](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:969>) | `public static function ajax_trigger_manual_audience_sync()` |
| [984](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:984>) | `public static function ajax_test_api_connection()` |
| [1103](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:1103>) | `public static function ajax_retry_capi_events()` |
| [1140](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:1140>) | `public static function ajax_log_js_error()` |
| [1160](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:1160>) | `public static function ajax_clear_telemetry_logs()` |
| [1178](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:1178>) | `public static function ajax_download_diagnostic_report()` |
| [1279](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:1279>) | `public static function ajax_save_routing_settings()` |
| [1372](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:1372>) | `public static function ajax_run_fallback_now()` |
| [1397](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:1397>) | `public static function ajax_get_mapped_past_leads()` |
| [1426](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:1426>) | `public static function ajax_sync_mapped_past_batch()` |
| [1461](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:1461>) | `public static function ajax_bulk_action()` |
| [1596](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:1596>) | `public static function ajax_get_leads_table()` |
| [2239](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:2239>) | `public static function ajax_get_kanban_leads()` |
| [2486](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:2486>) | `public static function ajax_get_dashboard_stats()` |
| [2531](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:2531>) | `public static function ajax_save_lead_details()` |
| [2642](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:2642>) | `public static function ajax_upload_lead_file()` |
| [2701](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:2701>) | `public static function ajax_get_total_leads_count()` |
| [2720](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:2720>) | `public static function ajax_reset_crm_data()` |
| [2746](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:2746>) | `public static function ajax_delete_lead()` |
| [2768](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:2768>) | `public static function ajax_import_leads_batch()` |
| [2973](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:2973>) | `public static function ajax_get_lead_tasks()` |
| [3020](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:3020>) | `public static function ajax_add_lead_task()` |
| [3097](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:3097>) | `public static function ajax_toggle_task_status()` |
| [3155](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:3155>) | `public static function ajax_delete_lead_task()` |
| [3218](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:3218>) | `public static function ajax_get_rep_tasks()` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|
| [2915](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-ajax.php:2915>) | `do_action('idlm_lead_stage_converted', $lead_id, $capi_stage, (object) array('stage' => ''));` |

## includes/class-admin-lead-reads.php

SHA-256: `715ff52af3473832909e4cba96ef98f9959d4e318f9b5ee0bd6352c54bf739ca`

| Source | Public signature |
|---|---|
| [13](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-reads.php:13>) | `public static function ajax_mark_whatsapp_messages_read()` |
| [44](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-reads.php:44>) | `public static function ajax_get_lead_whatsapp()` |
| [100](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-reads.php:100>) | `public static function ajax_get_lead_details()` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-admin-lead-notifications.php

SHA-256: `34c6153071355bf0be03dd99887ef10c17a59ca5347276644f3b048f31f6315d`

| Source | Public signature |
|---|---|
| [27](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:27>) | `public static function get_whatsapp_read_activity_ids()` |
| [41](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:41>) | `public static function mark_whatsapp_reply_read($lead_id, $activity_id)` |
| [121](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:121>) | `public static function get_unread_whatsapp_lead_ids()` |
| [127](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:127>) | `public static function get_unread_whatsapp_count()` |
| [133](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:133>) | `public static function get_whatsapp_received_lead_ids()` |
| [146](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:146>) | `public static function get_reminder_rules()` |
| [160](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:160>) | `public static function check_lead_overdue_status($lead, $rules = null)` |
| [207](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:207>) | `public static function get_pending_calls_count()` |
| [242](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:242>) | `public static function get_stale_lead_count($sales_board_only = false)` |
| [267](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:267>) | `public static function get_no_recent_assigned_rep_action_sql($since, $lead_alias = '')` |
| [290](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-lead-notifications.php:290>) | `public static function get_stale_lead_cutoff()` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-admin-presenter.php

SHA-256: `8aa38656e3ae7ae7f8855b7cbbf22ea085cabdfb803a144e4aab856a48e7f108`

| Source | Public signature |
|---|---|
| [14](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-presenter.php:14>) | `public static function normalize_floor_plan_files($value, $legacy_url = '')` |
| [40](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-presenter.php:40>) | `public static function get_qualification_status_label($lead)` |
| [54](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-presenter.php:54>) | `public static function format_datetime($mysql_date, $format = 'M d, Y h:i A')` |
| [67](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-presenter.php:67>) | `public static function render_lead_table_datetime($mysql_date)` |
| [80](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-presenter.php:80>) | `public static function get_business_day_age($created_at)` |
| [103](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-presenter.php:103>) | `public static function render_activity_details($details)` |
| [119](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-presenter.php:119>) | `public static function get_kanban_stage_total_mappings($stages)` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-admin-leads-renderer.php

SHA-256: `5001eefabbef21eee1cef03ea0c4d620b7534bb73a372b5bad4cfcc000c89618`

| Source | Public signature |
|---|---|
| [12](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-leads-renderer.php:12>) | `public static function render()` |
| [26](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-leads-renderer.php:26>) | `public static function render_common_overlays()` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-admin-kanban-renderer.php

SHA-256: `ed4e828c80d04a96957ca9bfac49b848505c90a79a17cb56cf5a39263c8bc554`

| Source | Public signature |
|---|---|
| [12](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-kanban-renderer.php:12>) | `public static function render()` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## includes/class-admin-dashboard-renderer.php

SHA-256: `7e3543b564f959256b737ac8311eb1c03775d7ffbeac56f836c5a72104068515`

| Source | Public signature |
|---|---|
| [12](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-admin-dashboard-renderer.php:12>) | `public static function render()` |

Registrations and emitted hooks (source expressions; callbacks resolve through the admin facade):

| Source | Expression |
|---|---|

## Assignment APIs and internal routing functions in includes/class-integrations.php

Other integration APIs are inventoried in the integration review. Assignment callers use `route_lead_assignment`, `run_assignment_fallback`, `rep_has_capacity`, `get_rep_capacity_status`, and `get_elapsed_working_hours`; owner CAS and notifications are internal.

| Source | Signature |
|---|---|
| [987](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:987>) | `private static function normalize_rep_pool($rep_ids)` |
| [996](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:996>) | `private static function get_all_pre_sales_pool()` |
| [1008](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1008>) | `private static function get_pre_sales_pool()` |
| [1021](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1021>) | `private static function get_active_rep_pool($exclude_pre_sales = false)` |
| [1046](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1046>) | `private static function get_configured_sales_pool()` |
| [1062](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1062>) | `private static function filter_pool_by_capacity($rep_ids)` |
| [1088](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1088>) | `public static function rep_has_capacity($rep_id)` |
| [1093](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1093>) | `public static function get_rep_capacity_status($rep_id)` |
| [1114](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1114>) | `private static function get_studio_rep_pool($rep_ids, $preferred_studio)` |
| [1139](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1139>) | `private static function select_round_robin_rep($rep_ids, $rotation_key)` |
| [1152](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1152>) | `private static function assign_lead_atomically($lead_id, $expected_owner_id, $assigned_rep_id, $activity_message, $qualified_only = false, $assignment_context = '')` |
| [1239](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1239>) | `private static function send_handoff_notifications($lead_id, $old_owner_id, $new_owner_id, $activity_message)` |
| [1271](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1271>) | `private static function mark_handoff_exception($lead_id, $reason)` |
| [1295](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1295>) | `private static function record_routing_system_exception($message)` |
| [1318](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1318>) | `public static function route_lead_assignment($lead_id, $source)` |
| [1371](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1371>) | `private static function recover_unassigned_leads()` |
| [1439](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1439>) | `private static function handoff_qualified_leads()` |
| [1574](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1574>) | `public static function run_assignment_fallback()` |
| [1603](</Users/shashi_protecto/Local Sites/testing/app/public/wp-content/plugins/interior-design-lead-manager/includes/class-integrations.php:1603>) | `public static function get_elapsed_working_hours($start_time_str, $end_time_str, $work_start_str, $work_end_str, $work_days)` |

## Core AJAX authorization and response classes

All facade AJAX registrations are authenticated `wp_ajax_*`. Google conversion export is manage_options-only with no nonce in its handler/stream method; diagnostic download verifies GET nonce. Mutations use `idlm_admin_nonce` and recognized CRM access except manage_options-only administration. View and ownership checks are evaluated in the concrete implementation, not the facade.

| Operation group | Authorization / validation / operation / response |
|---|---|
| Details, activity, WhatsApp, read markers, per-lead tasks | nonce → CRM → positive lead ID → current view scope (includes history policy) → SELECT/bounded escaped HTML or per-user read marker → JSON. |
| Quick edit / drawer / bulk mutate | nonce → CRM → current lead mutation access for every affected ID → allowlisted fields/domain/owner capacity/role restrictions → IDLM_DB lifecycle write → JSON count/error. Bulk operations can partially succeed and summarize failures. |
| Pre-sales touch / decisions | nonce → CRM → pre-sales or administrator → current lead access → evidence/current decision/structured reason and minimum project gates → activity/lifecycle/task update → JSON. |
| Comment create/edit/delete | nonce → CRM → current lead mutation access; edit/delete requires comment type and actor or administrator → sanitized text and15s duplicate check/typed CRUD → JSON. |
| Task add/toggle/delete/reschedule | nonce → CRM → current lead access → manual type/priority/exact date/pending policy; qualification tasks reserved → task CRUD/activity → JSON. Reschedule is hidden dispatch under get_rep_tasks. |
| Global task read | nonce → CRM → role scope by stale task owner OR current lead owner → unbounded join/group/time classification → JSON tasks with lead contact data. See CORE-011. |
| Reset/delete/count/import/export/routing/log management | nonce/action-specific download verifier → manage_options → typed IDs/count confirmation/filter/row mapping → deletion service/upsert/config/log operation → JSON or streamed CSV/text. |
| Integrations actions | admin nonce → manage_options → configured credentials/request-specific validation → relevant integration service/mockable external request → JSON. Detailed contracts in integration review. |
| JS-error logging | nonce → CRM → sanitized bounded browser diagnostic → telemetry → JSON. |

## Menu/page and shortcodes

The admin facade registers one manage_options utility menu and redirects legacy CRM pages into workspace URLs; quotation menus belong to the quotation module. Render callbacks delegate lead/kanban/dashboard/log/settings controllers. Four compatibility shortcodes are `idlm_leads_list`, `idlm_dashboard`, `idlm_kanban`, `idlm_workspace`; source registrations above resolve through facade render methods and the shortcode module. Asset/account/user settings ownership belongs to corresponding peer reviews.
