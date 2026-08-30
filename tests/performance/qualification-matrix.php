<?php
/**
 * Phase 8 load, resilience, integrity, and recovery qualification matrix.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH') && 'cli' !== PHP_SAPI) {
    exit;
}

return array(
    'datasets' => array('small', 'expected', 'maximum'),
    'traffic_profiles' => array('normal', 'peak', 'burst', 'soak'),
    'authorization_scopes' => array('administrator', 'assignment_manager', 'representative', 'pre_sales', 'unauthorized'),
    'concurrency_cases' => array(
        'webhook_deduplication',
        'lead_assignment',
        'task_update',
        'quotation_save',
        'number_reservation',
    ),
    'failure_cases' => array(
        'third_party_slow_or_unavailable',
        'cache_unavailable',
        'object_storage_unavailable',
        'database_latency_or_connection_exhaustion',
        'database_deadlock',
        'worker_termination',
        'queue_backlog',
        'expired_credentials',
    ),
    'recovery_cases' => array(
        'production_like_upgrade',
        'interrupted_backfill',
        'application_rollback',
        'database_and_media_restore',
    ),
    'integrity_invariants' => array(
        'no_cross_user_access',
        'no_duplicate_quote_numbers',
        'no_duplicate_integration_effects',
        'no_lost_accepted_webhooks',
        'no_orphaned_quotation_graphs',
    ),
    'execution_status' => 'not_executed',
);
