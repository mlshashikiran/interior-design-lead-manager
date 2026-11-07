<?php
/** Design layer: read-only access change log. render() requires manage_options. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

$idlm_entries = isset($context['entries']) && is_array($context['entries']) ? $context['entries'] : array();
$idlm_events = IDLM_Access_Change_Log::events();
?>
<section class="idlm-users-activity" aria-labelledby="idlm-users-activity-title">
    <div class="idlm-users-section-head">
        <h2 id="idlm-users-activity-title">Access changes</h2>
        <p>Who changed people, roles and permissions. The latest <?php echo esc_html(IDLM_Access_Change_Log::MAX_ENTRIES); ?> changes are kept, newest first.</p>
    </div>
<?php if (!$idlm_entries): ?>
    <div class="idlm-state is-empty"><span class="dashicons dashicons-clock" aria-hidden="true"></span><strong>No access changes yet.</strong><p>Changes appear here after someone adds a person or saves a role, a suspension or a permission.</p></div>
<?php else: ?>
    <div class="idlm-activity-table-wrap" role="region" aria-labelledby="idlm-users-activity-title" tabindex="0">
        <table class="idlm-activity-table">
            <thead><tr><th scope="col">When</th><th scope="col">Changed by</th><th scope="col">Change</th><th scope="col">For</th><th scope="col">Details</th></tr></thead>
            <tbody>
            <?php foreach ($idlm_entries as $idlm_entry):
                $idlm_is_role = 'role' === ($idlm_entry['target_type'] ?? '');
                $idlm_target = $idlm_is_role ? IDLM_Admin_Users_Controller::role_label((string) ($idlm_entry['target_id'] ?? '')) : (string) ($idlm_entry['target_name'] ?? '');
                $idlm_detail = IDLM_Access_Change_Log::describe($idlm_entry);
            ?>
                <tr>
                    <td data-label="When"><?php echo esc_html(wp_date('M j, Y g:i a', (int) ($idlm_entry['time'] ?? 0))); ?></td>
                    <td data-label="Changed by"><?php echo esc_html((string) ($idlm_entry['actor_name'] ?? '')); ?></td>
                    <td data-label="Change"><?php echo esc_html($idlm_events[$idlm_entry['event'] ?? ''] ?? (string) ($idlm_entry['event'] ?? '')); ?></td>
                    <td data-label="For"><?php echo esc_html($idlm_target); ?></td>
                    <td data-label="Details"><?php echo esc_html('' !== $idlm_detail ? $idlm_detail : '-'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</section>
