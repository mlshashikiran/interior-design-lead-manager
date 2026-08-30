<?php
/**
 * Deterministic, PII-free performance dataset definitions.
 *
 * Builder: Shashi
 */

declare(strict_types=1);

if (!defined('ABSPATH') && 'cli' !== PHP_SAPI) {
    exit;
}

final class IDLM_Performance_Dataset_Generator {
    public const SOURCE = 'Performance Seed';
    public const EMAIL_DOMAIN = 'performance.invalid';

    /** @return array<string,array<string,int>> */
    public static function profiles(): array {
        return array(
            'small' => array(
                'leads' => 1000,
                'activities' => 8000,
                'tasks' => 2000,
                'touchpoints' => 2000,
                'logs' => 4000,
                'quotes' => 500,
            ),
            'expected' => array(
                'leads' => 50000,
                'activities' => 400000,
                'tasks' => 100000,
                'touchpoints' => 100000,
                'logs' => 200000,
                'quotes' => 20000,
            ),
            'maximum' => array(
                'leads' => 250000,
                'activities' => 2000000,
                'tasks' => 500000,
                'touchpoints' => 500000,
                'logs' => 1000000,
                'quotes' => 100000,
            ),
        );
    }

    /** @return array<string,int> */
    public static function profile(string $profile): array {
        $profiles = self::profiles();
        if (!isset($profiles[$profile])) {
            throw new InvalidArgumentException('Unknown dataset profile.');
        }
        return $profiles[$profile];
    }

    public static function normalize_seed(string $seed): string {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,20}$/', $seed)) {
            throw new InvalidArgumentException('Seed must contain 1-20 letters, numbers, underscores, or hyphens.');
        }
        return strtolower($seed);
    }

    public static function run_id(string $profile, string $seed): string {
        self::profile($profile);
        return $profile . '-' . self::normalize_seed($seed);
    }

    public static function marker(string $run_id): string {
        self::assert_run_id($run_id);
        return 'idlm-perf:' . $run_id;
    }

    public static function email(string $run_id, int $ordinal): string {
        self::assert_run_id($run_id);
        return sprintf('perf+%s-%06d@%s', $run_id, $ordinal, self::EMAIL_DOMAIN);
    }

    public static function timestamp(int $ordinal): string {
        $base = strtotime('2025-09-01 00:00:00 UTC');
        $seconds = ($ordinal * 137) % (365 * 86400);
        return gmdate('Y-m-d H:i:s', $base + $seconds);
    }

    /** @return array<string,mixed> */
    public static function lead(string $run_id, int $ordinal, int $owner_id = 0): array {
        self::assert_ordinal($ordinal);
        $marker = self::marker($run_id);
        $stages = array('New', 'Contacted', 'Qualified', 'Visited Studio', 'Quote Shared', 'Negotiation', 'Won', 'Lost');
        $call_statuses = array('Not Called', 'No Answer', 'Call Back Requested', 'Connected - Interested');
        $stage = $stages[($ordinal - 1) % count($stages)];
        $qualified = in_array($stage, array('Qualified', 'Visited Studio', 'Quote Shared', 'Negotiation', 'Won'), true);
        $lost = 'Lost' === $stage;
        $quote_shared = in_array($stage, array('Quote Shared', 'Negotiation', 'Won'), true);
        $created_at = self::timestamp($ordinal);
        $quote_value = $quote_shared ? 500000 + (($ordinal % 40) * 25000) : 0;

        return array(
            'first_name' => 'Synthetic',
            'last_name' => sprintf('Lead %06d', $ordinal),
            'email' => self::email($run_id, $ordinal),
            'phone' => '9' . str_pad((string) ($ordinal % 1000000000), 9, '0', STR_PAD_LEFT),
            'project_name' => sprintf('%s Project %06d', $marker, $ordinal),
            'budget' => (5 + ($ordinal % 46)) . ' lakh',
            'deal_value' => 'Won' === $stage ? $quote_value : 0,
            'discount_percentage' => $ordinal % 11,
            'project_timeline' => (1 + ($ordinal % 12)) . ' months',
            'property_location' => 'Synthetic Zone ' . (1 + ($ordinal % 25)),
            'source' => self::SOURCE,
            'original_source' => self::SOURCE,
            'latest_source' => self::SOURCE,
            'property_type' => 0 === $ordinal % 2 ? 'Apartment' : 'Villa',
            'num_rooms' => (1 + ($ordinal % 5)) . ' BHK',
            'quote_shared' => $quote_shared ? 1 : 0,
            'quote_value' => $quote_value,
            'stage' => $stage,
            'lost_reason' => $lost ? 'Unable to Contact' : '',
            'call_status' => $call_statuses[($ordinal - 1) % count($call_statuses)],
            'callback_datetime' => self::timestamp($ordinal + 86400),
            'lead_quality' => $qualified ? 'Qualified' : ($lost ? 'Unqualified' : ''),
            'disqualification_reason' => $lost ? 'unable_to_contact' : '',
            'disqualification_method' => $lost ? 'performance_seed' : '',
            'assigned_rep_id' => $owner_id,
            'assigned_at' => $created_at,
            'qualified_at' => $qualified ? $created_at : null,
            'handoff_status' => $qualified ? 'auto_assigned' : 'not_ready',
            'is_duplicate' => 0,
            'duplicate_of_id' => 0,
            'created_at' => $created_at,
            'updated_at' => $created_at,
        );
    }

    /** @return array<string,mixed> */
    public static function activity(string $run_id, int $ordinal, int $lead_id, int $user_id = 0): array {
        $types = array('creation', 'update', 'call_status_change', 'stage_change', 'assignment');
        return array(
            'lead_id' => $lead_id,
            'user_id' => $user_id,
            'activity_type' => $types[($ordinal - 1) % count($types)],
            'details' => self::marker($run_id) . ' synthetic activity ' . $ordinal,
            'created_at' => self::timestamp($ordinal),
        );
    }

    /** @return array<string,mixed> */
    public static function task(string $run_id, int $ordinal, int $lead_id, int $user_id = 0): array {
        $completed = 0 === $ordinal % 3;
        return array(
            'lead_id' => $lead_id,
            'assigned_rep_id' => $user_id,
            'title' => self::marker($run_id) . ' synthetic task ' . $ordinal,
            'task_type' => 0 === $ordinal % 2 ? 'Qualification' : 'Sales Follow-up',
            'priority' => array('Low', 'Medium', 'High')[($ordinal - 1) % 3],
            'status' => $completed ? 'completed' : 'pending',
            'due_date' => self::timestamp($ordinal + 43200),
            'created_by' => $user_id,
            'created_at' => self::timestamp($ordinal),
            'completed_at' => $completed ? self::timestamp($ordinal + 3600) : null,
            'updated_at' => self::timestamp($ordinal + 3600),
        );
    }

    /** @return array<string,mixed> */
    public static function touchpoint(string $run_id, int $ordinal, int $lead_id): array {
        $platforms = array('meta', 'google', 'linkedin', 'manual');
        $platform = $platforms[($ordinal - 1) % count($platforms)];
        return array(
            'lead_id' => $lead_id,
            'platform' => $platform,
            'source' => self::SOURCE,
            'external_id' => self::marker($run_id) . ':touchpoint:' . $ordinal,
            'utm_source' => $platform,
            'utm_medium' => 'performance-test',
            'utm_campaign' => 'synthetic-' . $run_id,
            'payload' => json_encode(array('synthetic' => true, 'run_id' => $run_id, 'ordinal' => $ordinal)),
            'submitted_at' => self::timestamp($ordinal),
            'received_at' => self::timestamp($ordinal + 5),
        );
    }

    /** @return array<string,mixed> */
    public static function capi_log(string $run_id, int $ordinal, int $lead_id): array {
        return array(
            'lead_id' => $lead_id,
            'platform' => array('meta', 'google', 'linkedin')[($ordinal - 1) % 3],
            'event_name' => 'SyntheticPerformanceEvent',
            'payload' => json_encode(array('synthetic' => true, 'run_id' => $run_id, 'ordinal' => $ordinal)),
            'response' => json_encode(array('synthetic' => true, 'accepted' => true)),
            'status' => 0 === $ordinal % 10 ? 'failed' : 'success',
            'created_at' => self::timestamp($ordinal),
        );
    }

    /** @return array<string,mixed> */
    public static function audience_log(string $run_id, int $ordinal): array {
        return array(
            'platform' => array('meta', 'google', 'linkedin')[($ordinal - 1) % 3],
            'audience_id' => self::marker($run_id) . ':audience:' . $ordinal,
            'lead_count' => 100 + ($ordinal % 1000),
            'payload' => json_encode(array('synthetic' => true, 'run_id' => $run_id)),
            'response' => json_encode(array('synthetic' => true, 'accepted' => true)),
            'status' => 0 === $ordinal % 10 ? 'failed' : 'success',
            'created_at' => self::timestamp($ordinal),
        );
    }

    /** @return array<string,mixed> */
    public static function telemetry_log(string $run_id, int $ordinal): array {
        return array(
            'log_type' => 'idlm_performance_seed',
            'severity' => 0 === $ordinal % 10 ? 'warning' : 'info',
            'message' => self::marker($run_id) . ' synthetic telemetry ' . $ordinal,
            'file' => '',
            'line' => 0,
            'stack_trace' => '',
            'created_at' => self::timestamp($ordinal),
        );
    }

    /** @return array<string,mixed> */
    public static function quote(string $run_id, int $ordinal, int $lead_id, int $user_id = 0): array {
        $now = self::timestamp($ordinal);
        return array(
            'lead_id' => $lead_id,
            'quote_number' => sprintf('PERF-%s-%06d', $run_id, $ordinal),
            'title' => self::marker($run_id) . ' Proposal ' . $ordinal,
            'status' => 0 === $ordinal % 4 ? 'Shared' : 'Draft',
            'current_revision_id' => 0,
            'owner_user_id' => $user_id,
            'created_by' => $user_id,
            'save_request_key' => hash('sha256', $run_id . ':quote:' . $ordinal),
            'created_at' => $now,
            'updated_at' => $now,
        );
    }

    /** @return array<string,mixed> */
    public static function revision(string $run_id, int $ordinal, int $quote_id, int $user_id = 0): array {
        $subtotal = 100000 + (($ordinal % 100) * 5000);
        $tax = round($subtotal * 0.09, 2);
        $now = self::timestamp($ordinal);
        return array(
            'quote_id' => $quote_id,
            'revision_number' => 1,
            'revision_name' => sprintf('PERF-%s-%06d-R01', $run_id, $ordinal),
            'status' => 0 === $ordinal % 4 ? 'Shared' : 'Draft',
            'currency_code' => 'INR',
            'customer_name' => 'Synthetic Customer ' . $ordinal,
            'customer_phone' => '',
            'customer_email' => self::email($run_id, $ordinal),
            'project_name' => self::marker($run_id) . ' Project ' . $ordinal,
            'property_location' => 'Synthetic Zone ' . (1 + ($ordinal % 25)),
            'subtotal' => $subtotal,
            'wooden_subtotal' => $subtotal,
            'subtotal_after_wooden_discount' => $subtotal,
            'total_before_additional_discount' => $subtotal,
            'taxable_total' => $subtotal,
            'cgst_total' => $tax,
            'sgst_total' => $tax,
            'grand_total' => $subtotal + (2 * $tax),
            'created_by' => $user_id,
            'created_at' => $now,
            'updated_by' => $user_id,
            'updated_at' => $now,
            'save_request_key' => hash('sha256', $run_id . ':revision:' . $ordinal),
        );
    }

    /** @return array<string,mixed> */
    public static function section(string $run_id, int $ordinal, int $revision_id): array {
        return array(
            'revision_id' => $revision_id,
            'section_name' => self::marker($run_id) . ' Section ' . $ordinal,
            'section_description' => 'Synthetic performance section',
            'sort_order' => 0,
        );
    }

    /** @return array<string,mixed> */
    public static function item(string $run_id, int $ordinal, int $revision_id, int $section_id): array {
        $subtotal = 100000 + (($ordinal % 100) * 5000);
        return array(
            'revision_id' => $revision_id,
            'section_id' => $section_id,
            'row_type' => 'item',
            'is_wood' => 1,
            'category' => 'Synthetic',
            'room_area' => 'Synthetic Room',
            'item_code' => sprintf('PERF-%s-%06d', $run_id, $ordinal),
            'item_name' => self::marker($run_id) . ' Item ' . $ordinal,
            'description' => 'Synthetic benchmark quotation item',
            'quantity' => 1,
            'unit' => 'Nos',
            'unit_rate' => $subtotal,
            'line_subtotal' => $subtotal,
            'line_total' => $subtotal,
            'sort_order' => 0,
        );
    }

    private static function assert_run_id(string $run_id): void {
        if (!preg_match('/^(small|expected|maximum)-[a-z0-9_-]{1,20}$/', $run_id)) {
            throw new InvalidArgumentException('Invalid performance dataset run ID.');
        }
    }

    private static function assert_ordinal(int $ordinal): void {
        if ($ordinal < 1) {
            throw new InvalidArgumentException('Dataset ordinals start at 1.');
        }
    }
}
