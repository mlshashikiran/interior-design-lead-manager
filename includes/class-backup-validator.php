<?php
/** Strict same-site CRM backup preflight, without persistent writes. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Backup_Validator {
    /** Current versioned column inventory; an empty table is still mandatory. */
    public static function columns() {
        return array(
            'idlm_leads' => explode(' ', 'id first_name last_name email phone project_name budget deal_value discount_percentage project_timeline possession_date property_location source original_source latest_source referrer_details property_type num_rooms floor_plan_received floor_plan_file floor_plan_files quote_shared quote_value quote_file preferred_studio stage lost_reason call_status callback_datetime lead_quality disqualification_reason disqualification_method disqualified_at disqualified_by_user_id assigned_rep_id assigned_at utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content gclid gbraid wbraid fbclid li_fat_id fb_lead_id landing_page conversion_time qualified_at qualified_by_user_id intake_owner_id handoff_status handoff_at handoff_method handoff_exception_reason is_duplicate duplicate_of_id created_at updated_at time_to_connected time_to_won'),
            'idlm_lead_activity_log' => explode(' ', 'id lead_id user_id activity_type details created_at'),
            'idlm_lead_state_events' => explode(' ', 'id lead_id event_type event_origin from_value to_value reason amount actor_user_id occurred_at'),
            'idlm_tasks' => explode(' ', 'id lead_id assigned_rep_id title task_type qualification_cycle priority status due_date created_by created_at completed_at updated_at'),
            'idlm_lead_touchpoints' => explode(' ', 'id lead_id platform source external_id utm_source utm_medium utm_campaign utm_adgroup utm_term utm_content gclid gbraid wbraid fbclid li_fat_id landing_page payload submitted_at received_at'),
            'idlm_inbound_submission_reviews' => explode(' ', 'id platform source email phone candidate_lead_ids payload submitted_at received_at status submission_data external_id resolved_lead_id resolved_by resolved_at resolution_note'),
            'idlm_call_logs' => explode(' ', 'id lead_id user_id provider provider_event_id direction outcome started_at duration_seconds recording_url recording_hash metadata created_at'),
            'idlm_capi_logs' => explode(' ', 'id lead_id platform event_name payload response status created_at'),
            'idlm_audience_sync_logs' => explode(' ', 'id platform audience_id lead_count payload response status created_at'),
            'idlm_telemetry_logs' => explode(' ', 'id log_type severity message file line stack_trace created_at'),
            'idlm_routing_state' => explode(' ', 'state_key state_value updated_at'),
            'idlm_quote_catalogue_items' => explode(' ', 'id item_code category item_name description unit is_wood default_rate status created_by created_at updated_at'),
            'idlm_quote_item_categories' => explode(' ', 'id category_name status created_by created_at updated_at'),
            'idlm_quote_item_units' => explode(' ', 'id unit_name status created_by created_at updated_at'),
            'idlm_quotes' => explode(' ', 'id lead_id quote_number title status current_revision_id owner_user_id created_by accepted_revision_id shared_at accepted_at rejected_at save_request_key created_at updated_at'),
            'idlm_quote_revisions' => explode(' ', 'id quote_id revision_number revision_name status source_revision_id revision_reason currency_code document_terms valid_until customer_intro customer_name customer_phone customer_email project_name property_location internal_notes subtotal wooden_subtotal wooden_discount_percent wooden_discount_amount subtotal_after_wooden_discount other_subtotal soft_close_enabled soft_close_percent soft_close_amount design_fee_percent design_fee_amount total_before_additional_discount additional_discount_amount taxable_total cgst_total sgst_total igst_total round_off_amount grand_total created_by created_at updated_by updated_at finalized_at save_request_key'),
            'idlm_quote_revision_sections' => explode(' ', 'id revision_id section_name section_description sort_order'),
            'idlm_quote_revision_items' => explode(' ', 'id revision_id section_id row_type catalogue_item_id is_wood classification_source category room_area item_code item_name description specification length height quantity unit unit_rate discount_type discount_value discount_amount line_subtotal line_total is_optional sort_order'),
            'idlm_quote_payment_milestones' => explode(' ', 'id revision_id milestone_name amount_type amount_value calculated_amount due_trigger sort_order'),
            'idlm_quote_status_history' => explode(' ', 'id quote_id revision_id from_status to_status note changed_by changed_at'),
            'idlm_number_sequences' => explode(' ', 'id sequence_type sequence_year last_number updated_at'),
        );
    }
    private static function error() { return new WP_Error('idlm_backup_invalid', 'Backup data or schema is incomplete or incompatible. No CRM data was changed.'); }
    private static function list_array($value) { return is_array($value) && (!$value || array_keys($value) === range(0, count($value) - 1)); }
    private static function integer($value, $minimum, $maximum) {
        if (!is_int($value) && !is_string($value)) { return false; }
        $value = (string) $value;
        if (!preg_match('/^-?(?:0|[1-9][0-9]*)$/D', $value)) { return false; }
        // Compare canonical decimal strings without overflow or float conversion.
        foreach (array(array($minimum, true), array($maximum, false)) as $bound) {
            $negative = $value[0] === '-'; $limit = (string) $bound[0]; $limit_negative = $limit[0] === '-';
            if ($negative !== $limit_negative) { $cmp = $negative ? -1 : 1; }
            else { $a = ltrim($value, '-'); $b = ltrim($limit, '-'); $cmp = strlen($a) <=> strlen($b); if (!$cmp) { $cmp = strcmp($a, $b); } if ($negative) { $cmp *= -1; } }
            if (($bound[1] && $cmp < 0) || (!$bound[1] && $cmp > 0)) { return false; }
        }
        return true;
    }
    public static function value_valid($value, $column) {
        if (null === $value) { return $column['Null'] === 'YES'; }
        $type = strtolower($column['Type']);
        if (preg_match('/^(tinyint|smallint|mediumint|int|bigint)(?:\(\d+\))?( unsigned)?$/D', $type, $m)) {
            $ranges = array('tinyint'=>array('-128','127','255'),'smallint'=>array('-32768','32767','65535'),'mediumint'=>array('-8388608','8388607','16777215'),'int'=>array('-2147483648','2147483647','4294967295'),'bigint'=>array((string) PHP_INT_MIN,(string) PHP_INT_MAX,(string) PHP_INT_MAX));
            $range = $ranges[$m[1]]; return self::integer($value, !empty($m[2]) ? '0' : $range[0], !empty($m[2]) ? $range[2] : $range[1]);
        }
        if (preg_match('/^decimal\((\d+),(\d+)\)( unsigned)?$/D', $type, $m)) {
            if (!is_int($value) && !is_string($value)) { return false; }
            if (!preg_match('/^(-?)(0|[1-9][0-9]*)(?:\.([0-9]+))?$/D', (string) $value, $parts)) { return false; }
            return (empty($m[3]) || !$parts[1]) && strlen(ltrim($parts[2], '0')) <= (int) $m[1] - (int) $m[2] && strlen($parts[3] ?? '') <= (int) $m[2];
        }
        if (!is_string($value) || !preg_match('//u', $value)) { return false; }
        if (preg_match('/^(?:varchar|char)\((\d+)\)$/D', $type, $m)) { return preg_match_all('/./us', $value) <= (int) $m[1]; }
        if (in_array($type, array('text','mediumtext','longtext','tinytext'), true)) { return strlen($value) <= array('tinytext'=>255,'text'=>65535,'mediumtext'=>16777215,'longtext'=>4294967295)[$type]; }
        if (in_array($type, array('date','datetime','timestamp'), true)) {
            $format = $type === 'date' ? 'Y-m-d' : 'Y-m-d H:i:s'; $date = DateTimeImmutable::createFromFormat('!' . $format, $value, new DateTimeZone('UTC')); $errors = DateTimeImmutable::getLastErrors();
            return $date && (!$errors || (!$errors['warning_count'] && !$errors['error_count'])) && $date->format($format) === $value && substr($value,0,4) >= '1000';
        }
        return false;
    }
    private static function plain_serialized($value) {
        if (!is_serialized($value)) { return true; }
        $decoded = @unserialize($value, array('allowed_classes' => false));
        if (false === $decoded && $value !== 'b:0;') { return false; }
        $walk = static function($item, $depth = 0) use (&$walk) {
            if (is_object($item) || $depth > 64) { return false; }
            if (is_array($item)) { foreach ($item as $child) { if (!$walk($child, $depth + 1)) { return false; } } }
            return true;
        };
        return $walk($decoded);
    }
    /** Add unknown terms only to complete pre-snapshot quotation inventories. */
    public static function normalize_document_terms($payload, $schema) {
        $table = 'idlm_quote_revisions';
        if (!is_array($payload) || !is_array($payload['tables'][$table] ?? null)) { return $payload; }
        $legacy = $schema; unset($legacy[$table]['document_terms']);
        if (isset($payload['schema'])) {
            $quote_version = $payload['schema']['quotation'] ?? '';
            if (!in_array($quote_version, array('2.5.0','2.5.1','2.5.2','2.5.3'), true)) { return $payload; }
            if (in_array($quote_version, array('2.5.0','2.5.1'), true)) { unset($legacy['idlm_quote_revision_items']['classification_source']); }
            $core = $payload['schema']['core'] ?? '';
            if (in_array($core, array('1.3.5','1.3.6','1.3.7','1.3.8'), true)) {
                foreach (array('submission_data','external_id','resolved_lead_id','resolved_by','resolved_at','resolution_note') as $field) { unset($legacy['idlm_inbound_submission_reviews'][$field]); }
                if ($core !== '1.3.8') { unset($legacy['idlm_tasks']['qualification_cycle']); }
            }
            if (($payload['schema']['columns'] ?? null) !== $legacy) { return $payload; }
        }
        foreach ($payload['tables'][$table] as $row) {
            if (!is_array($row) || array_diff(array_keys($legacy[$table]),array_keys($row)) || array_diff(array_keys($row),array_keys($legacy[$table]))) { return $payload; }
        }
        foreach ($payload['tables'][$table] as &$row) { $row['document_terms'] = null; } unset($row);
        if (isset($payload['schema'])) { $payload['schema']['columns'][$table] = $schema[$table]; }
        return $payload;
    }

    /** Preserve old bits; never use today's catalogue to repair missing provenance. */
    public static function normalize_item_classifications($payload, $schema) {
        $table = 'idlm_quote_revision_items';
        if (!is_array($payload) || !is_array($payload['tables'][$table] ?? null)) { return $payload; }
        $legacy = $schema;
        unset($legacy[$table]['classification_source']);
        if (isset($payload['schema'])) {
            if (!in_array($payload['schema']['quotation'] ?? '', array('2.5.0', '2.5.1'), true)) { return $payload; }
            // Compose with the already supported complete older core inventories.
            $core = $payload['schema']['core'] ?? '';
            if (in_array($core, array('1.3.5', '1.3.6', '1.3.7', '1.3.8'), true)) {
                foreach (array('submission_data','external_id','resolved_lead_id','resolved_by','resolved_at','resolution_note') as $field) { unset($legacy['idlm_inbound_submission_reviews'][$field]); }
                if ('1.3.8' !== $core) { unset($legacy['idlm_tasks']['qualification_cycle']); }
            }
            if (($payload['schema']['columns'] ?? null) !== $legacy) { return $payload; }
        }
        foreach ($payload['tables'][$table] as $row) {
            if (!is_array($row) || array_diff(array_keys($legacy[$table]), array_keys($row)) || array_diff(array_keys($row), array_keys($legacy[$table]))) { return $payload; }
        }
        foreach ($payload['tables'][$table] as &$row) { $row['classification_source'] = 'legacy_unverified'; }
        unset($row);
        if (isset($payload['schema'])) { $payload['schema']['columns'][$table] = $schema[$table]; }
        return $payload;
    }

    /** Add nullable review lifecycle fields only to exact complete prior inventories. */
    public static function normalize_reviews($payload, $schema) {
        if (!is_array($payload) || !is_array($payload['tables']['idlm_inbound_submission_reviews'] ?? null)) { return $payload; }
        if (isset($payload['schema']) && !in_array($payload['schema']['core'] ?? '', array('1.3.5','1.3.6','1.3.7','1.3.8'), true)) { return $payload; }
        $legacy = $schema;
        $fields = array('submission_data','external_id','resolved_lead_id','resolved_by','resolved_at','resolution_note');
        foreach ($fields as $field) { unset($legacy['idlm_inbound_submission_reviews'][$field]); }
        if (isset($payload['schema']) && !in_array($payload['schema']['core'], array('1.3.8'), true)) { unset($legacy['idlm_tasks']['qualification_cycle']); }
        if (isset($payload['schema']) && ($payload['schema']['columns'] ?? null) !== $legacy) { return $payload; }
        foreach ($payload['tables']['idlm_inbound_submission_reviews'] as $row) {
            if (!is_array($row) || array_diff(array_keys($legacy['idlm_inbound_submission_reviews']), array_keys($row)) || array_diff(array_keys($row), array_keys($legacy['idlm_inbound_submission_reviews']))) { return $payload; }
        }
        foreach ($payload['tables']['idlm_inbound_submission_reviews'] as &$row) { foreach ($fields as $field) { $row[$field] = null; } }
        unset($row);
        if (isset($payload['schema'])) { $payload['schema']['columns']['idlm_inbound_submission_reviews'] = $schema['idlm_inbound_submission_reviews']; }
        return $payload;
    }

    /** Convert only complete old task inventories; current malformed manifests stay invalid. */
    public static function normalize_task_cycles($payload, $schema) {
        if (!is_array($payload) || !is_array($payload['tables']['idlm_tasks'] ?? null)) { return $payload; }
        if (isset($payload['schema']) && !in_array($payload['schema']['core'] ?? '', array('1.3.5', '1.3.6', '1.3.7'), true)) { return $payload; }
        $legacy = $schema;
        unset($legacy['idlm_tasks']['qualification_cycle']);
        if (isset($payload['schema']) && ($payload['schema']['columns'] ?? null) !== $legacy) { return $payload; }
        foreach ($payload['tables']['idlm_tasks'] as $row) {
            if (!is_array($row) || array_diff(array_keys($legacy['idlm_tasks']), array_keys($row)) || array_diff(array_keys($row), array_keys($legacy['idlm_tasks']))) { return $payload; }
        }
        foreach ($payload['tables']['idlm_tasks'] as &$row) { $row['qualification_cycle'] = null; }
        unset($row);
        if (isset($payload['schema'])) { $payload['schema']['columns'] = $schema; }
        return $payload;
    }

    public static function validate($payload, $schema, $site_url, $identities, $option_allowed) {
        if (!is_array($payload) || ($payload['format'] ?? '') !== 'idlm-backup' || !in_array($payload['version'] ?? null, array(1,'1'), true) || ($payload['site_url'] ?? '') !== $site_url || !is_array($payload['tables'] ?? null) || !self::list_array($payload['options'] ?? null)) { return self::error(); }
        if (array_diff(array_keys($schema), array_keys($payload['tables'])) || array_diff(array_keys($payload['tables']), array_keys($schema))) { return self::error(); }
        if (isset($payload['schema'])) {
            if (!is_array($payload['schema']) || !in_array($payload['schema']['core'] ?? '', array(IDLM_DB_VERSION, '1.3.8', '1.3.7', '1.3.6', '1.3.5'), true) || !in_array($payload['schema']['quotation'] ?? '', array(IDLM_Quotation_Schema::VERSION, '2.5.3', '2.5.2', '2.5.1', '2.5.0'), true) || ($payload['schema']['columns'] ?? null) !== $schema || !is_array($payload['user_identities'] ?? null)) { return self::error(); }
            foreach ($payload['user_identities'] as $id => $login) { if (!is_string($login) || !isset($identities[$id]) || $identities[$id] !== $login) { return self::error(); } }
        }
        $maps = array();
        foreach ($schema as $table => $columns) {
            if (!self::list_array($payload['tables'][$table])) { return self::error(); }
            $maps[$table] = array();
            foreach ($payload['tables'][$table] as $index => $row) {
                if (!is_array($row) || array_diff(array_keys($columns), array_keys($row)) || array_diff(array_keys($row), array_keys($columns))) { return self::error(); }
                foreach ($columns as $field => $column) {
                    if (!self::value_valid($row[$field], $column)) { return self::error(); }
                    if ('document_terms' === $field && is_wp_error(IDLM_Quotation_Document::read((object) array('document_terms'=>$row[$field])))) { return self::error(); }
                    if ('classification_source' === $field && !in_array($row[$field], array('legacy_unverified', 'submitted', 'default_nonwood'), true)) { return self::error(); }
                }
                $key = (string) ($row['id'] ?? $row['state_key'] ?? '');
                if (!$key || (isset($row['id']) && !self::integer($row['id'], '1', (string) PHP_INT_MAX)) || isset($maps[$table][$key])) { return self::error(); }
                $maps[$table][$key] = $index;
            }
        }
        $exists = static function($table, $id, $optional = false) use ($maps) { return ($optional && (null === $id || (string) $id === '0')) || isset($maps[$table][(string) $id]); };
        $get = static function($table, $id) use ($maps, $payload) { return isset($maps[$table][(string) $id]) ? $payload['tables'][$table][$maps[$table][(string) $id]] : null; };
        foreach (array('idlm_lead_activity_log','idlm_lead_state_events','idlm_tasks','idlm_lead_touchpoints','idlm_call_logs','idlm_capi_logs') as $table) {
            foreach ($payload['tables'][$table] as $row) { if (!$exists('idlm_leads', $row['lead_id'])) { return self::error(); } }
        }
        foreach ($payload['tables']['idlm_leads'] as $row) {
            if (!$exists('idlm_leads', $row['duplicate_of_id'], true) || ((string) $row['duplicate_of_id'] === (string) $row['id'])) { return self::error(); }
            if (!empty($row['assigned_rep_id']) && (!isset($identities[$row['assigned_rep_id']]) || (isset($payload['schema']) && !isset($payload['user_identities'][$row['assigned_rep_id']])))) { return self::error(); }
        }
        $cycles = array();
        foreach ($payload['tables']['idlm_tasks'] as $row) {
            if (null === $row['qualification_cycle']) { continue; }
            if ('Qualification' !== $row['task_type'] || !self::integer($row['qualification_cycle'], '1', '6')) { return self::error(); }
            $key = $row['lead_id'] . ':' . $row['qualification_cycle'];
            if (isset($cycles[$key])) { return self::error(); }
            $cycles[$key] = true;
        }
        $complete = array();
        foreach ($payload['tables']['idlm_leads'] as $row) {
            $path = array(); $cursor = (string) $row['id'];
            while ($cursor !== '0' && $cursor !== '' && !isset($complete[$cursor])) {
                if (isset($path[$cursor])) { return self::error(); }
                $path[$cursor] = true; $parent = $get('idlm_leads', $cursor);
                $cursor = (string) ($parent['duplicate_of_id'] ?? 0);
            }
            foreach ($path as $id=>$unused) { $complete[$id] = true; }
        }
        foreach ($payload['tables']['idlm_quotes'] as $row) {
            if (!$exists('idlm_leads', $row['lead_id'])) { return self::error(); }
            foreach (array('current_revision_id','accepted_revision_id') as $field) {
                if (!$exists('idlm_quote_revisions', $row[$field], true)) { return self::error(); }
                if (!empty($row[$field]) && (string) $get('idlm_quote_revisions', $row[$field])['quote_id'] !== (string) $row['id']) { return self::error(); }
            }
        }
        foreach ($payload['tables']['idlm_quote_revisions'] as $row) {
            if (!$exists('idlm_quotes', $row['quote_id']) || !$exists('idlm_quote_revisions', $row['source_revision_id'], true)) { return self::error(); }
            if (!empty($row['source_revision_id']) && ((string) $row['source_revision_id'] === (string) $row['id'] || (string) $get('idlm_quote_revisions',$row['source_revision_id'])['quote_id'] !== (string) $row['quote_id'] || (int) $get('idlm_quote_revisions',$row['source_revision_id'])['revision_number'] >= (int) $row['revision_number'])) { return self::error(); }
        }
        foreach (array('idlm_quote_revision_sections','idlm_quote_revision_items','idlm_quote_payment_milestones') as $table) {
            foreach ($payload['tables'][$table] as $row) {
                if (!$exists('idlm_quote_revisions', $row['revision_id'])) { return self::error(); }
                if ($table === 'idlm_quote_revision_items' && (!$exists('idlm_quote_revision_sections', $row['section_id'], true) || (!empty($row['section_id']) && (string) $get('idlm_quote_revision_sections',$row['section_id'])['revision_id'] !== (string) $row['revision_id']))) { return self::error(); }
            }
        }
        foreach ($payload['tables']['idlm_quote_status_history'] as $row) {
            if (!$exists('idlm_quotes', $row['quote_id']) || !$exists('idlm_quote_revisions', $row['revision_id'], true) || (!empty($row['revision_id']) && (string) $get('idlm_quote_revisions',$row['revision_id'])['quote_id'] !== (string) $row['quote_id'])) { return self::error(); }
        }
        // Legacy candidate IDs may reference already-erased leads; they remain hints.
        foreach ($payload['tables']['idlm_inbound_submission_reviews'] as $row) {
            if (!in_array($row['status'], array('pending','matched','separate','rejected'), true)) { return self::error(); }
            $ids = json_decode($row['candidate_lead_ids'] ?? '', true);
            if (!self::list_array($ids)) { return self::error(); }
            foreach ($ids as $id) { if (!self::integer($id, '1', (string) PHP_INT_MAX)) { return self::error(); } }
            $terminal = $row['status'] !== 'pending';
            if ($terminal && (empty($row['resolved_by']) || empty($row['resolved_at']) || empty($row['resolution_note']))) { return self::error(); }
            if (in_array($row['status'],array('matched','separate'),true)) {
                if (!$exists('idlm_leads', $row['resolved_lead_id'])) { return self::error(); }
            } elseif (!empty($row['resolved_lead_id'])) { return self::error(); }
            if (!$terminal && (!empty($row['resolved_by']) || !empty($row['resolved_at']) || !empty($row['resolution_note']))) { return self::error(); }
            if (null !== $row['submission_data'] && !is_array(json_decode($row['submission_data'], true))) { return self::error(); }
        }
        $names = array();
        foreach ($payload['options'] as $row) {
            if (!is_array($row) || count($row) !== 3 || !isset($row['option_name'],$row['option_value'],$row['autoload']) || !is_string($row['option_name']) || !preg_match('/^idlm_[a-zA-Z0-9_]+$/D', $row['option_name']) || strlen($row['option_name']) > 191 || isset($names[$row['option_name']]) || !is_string($row['option_value']) || !preg_match('//u', $row['option_value']) || !self::plain_serialized($row['option_value']) || !in_array($row['autoload'],array('yes','no','on','off','auto','auto-on','auto-off'),true)) { return self::error(); }
            $names[$row['option_name']] = true;
            if (isset($payload['schema']) && !call_user_func($option_allowed,$row['option_name'])) { return self::error(); }
        }
        return true;
    }
}
