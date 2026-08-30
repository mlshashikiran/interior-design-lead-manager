<?php
/** WP-CLI integrity audit/repair command. @author Shashi */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Integrity_Command {
    /** Audit application-enforced lead relationships; pass --repair to remove orphans. */
    public function audit($args, $assoc_args) {
        $repair = !empty($assoc_args['repair']);
        $counts = IDLM_Lead_Deletion_Service::audit_orphans($repair);
        foreach ($counts as $table => $count) {
            WP_CLI::log(sprintf('%s: %d orphan row(s)', $table, $count));
        }
        WP_CLI::success($repair ? 'Integrity repair completed.' : 'Integrity audit completed.');
    }
}

if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
    WP_CLI::add_command('idlm integrity', 'IDLM_Integrity_Command');
}
