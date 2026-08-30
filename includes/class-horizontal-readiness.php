<?php
/**
 * Read-only deployment preflight for horizontally scaled installations.
 *
 * @package InteriorDesignLeadManager
 * @author Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Horizontal_Readiness {
    /**
     * Build an aggregate-only readiness report without exposing infrastructure values.
     *
     * @param array|null $context Optional detected-state override for tests.
     * @return array
     */
    public static function report($context = null) {
        $context = is_array($context) ? $context : self::detect_context();
        $context = array_merge(array(
            'multi_node' => false,
            'shared_database' => false,
            'shared_media' => false,
            'shared_configuration' => false,
            'persistent_cache' => false,
            'action_scheduler' => false,
            'managed_cron' => false,
            'streamed_exports' => true,
        ), $context);

        $requirements = array(
            'shared_database' => array(
                'label' => 'Shared transactional database',
                'guidance' => 'Declare IDLM_SHARED_DATABASE only after every node uses the same primary and tested recovery path.',
            ),
            'shared_media' => array(
                'label' => 'Shared media/object storage',
                'guidance' => 'Declare IDLM_SHARED_MEDIA_STORAGE only after WordPress media is available from every application node.',
            ),
            'shared_configuration' => array(
                'label' => 'Shared configuration and secrets',
                'guidance' => 'Declare IDLM_SHARED_CONFIGURATION only after salts, environment constants, and protected credentials are identical on every node.',
            ),
            'persistent_cache' => array(
                'label' => 'Persistent shared object cache',
                'guidance' => 'Configure a persistent WordPress object cache, declare IDLM_SHARED_OBJECT_CACHE after proving it is shared, and monitor errors and evictions.',
            ),
            'action_scheduler' => array(
                'label' => 'Action Scheduler queue',
                'guidance' => 'Install and initialize Action Scheduler before using dedicated multi-node queue runners.',
            ),
            'managed_cron' => array(
                'label' => 'Controlled cron runner',
                'guidance' => 'Set DISABLE_WP_CRON and declare IDLM_MANAGED_CRON_RUNNER only after a monitored system runner is active.',
            ),
            'streamed_exports' => array(
                'label' => 'Node-local export persistence',
                'guidance' => 'Current lead, catalogue, audience, conversion, and diagnostic downloads stream to the requesting client and do not persist plugin-local artifacts.',
            ),
        );

        $checks = array();
        $blocking = 0;
        foreach ($requirements as $key => $requirement) {
            $ready = !empty($context[$key]);
            $is_blocking = !empty($context['multi_node']) && !$ready;
            if ($is_blocking) {
                $blocking++;
            }
            $checks[] = array(
                'key' => $key,
                'label' => $requirement['label'],
                'guidance' => $requirement['guidance'],
                'ready' => $ready,
                'blocking' => $is_blocking,
                'status' => empty($context['multi_node']) ? 'not_enabled' : ($ready ? 'ready' : 'blocked'),
            );
        }

        return array(
            'mode' => !empty($context['multi_node']) ? 'multi_node' : 'single_node',
            'status' => empty($context['multi_node']) ? 'not_enabled' : ($blocking ? 'blocked' : 'ready'),
            'blocking_count' => $blocking,
            'checks' => $checks,
        );
    }

    /** Detect only boolean deployment state; never return hosts, paths, or credentials. */
    private static function detect_context() {
        $action_scheduler = class_exists('ActionScheduler')
            || (function_exists('as_next_scheduled_action') && function_exists('as_schedule_recurring_action'));
        $persistent_cache = function_exists('wp_using_ext_object_cache')
            && wp_using_ext_object_cache()
            && self::constant_enabled('IDLM_SHARED_OBJECT_CACHE');
        $managed_cron = self::constant_enabled('DISABLE_WP_CRON')
            && self::constant_enabled('IDLM_MANAGED_CRON_RUNNER');

        return array(
            'multi_node' => self::constant_enabled('IDLM_MULTI_NODE_MODE'),
            'shared_database' => self::constant_enabled('IDLM_SHARED_DATABASE'),
            'shared_media' => self::constant_enabled('IDLM_SHARED_MEDIA_STORAGE'),
            'shared_configuration' => self::constant_enabled('IDLM_SHARED_CONFIGURATION'),
            'persistent_cache' => $persistent_cache,
            'action_scheduler' => $action_scheduler,
            'managed_cron' => $managed_cron,
            'streamed_exports' => true,
        );
    }

    private static function constant_enabled($name) {
        return defined($name) && (bool) constant($name);
    }
}
