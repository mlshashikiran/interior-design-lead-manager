<?php
if (!defined('ABSPATH')) { exit; }
$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$can_manage_options = !empty($context['can_manage_options']);
$notice = isset($_GET['idlm_backup_notice']) ? sanitize_key(wp_unslash($_GET['idlm_backup_notice'])) : '';
$messages = array(
    'restored' => array('success', 'Backup restored. Queued outbound jobs were cleared; review integrations before resuming any manual sync.'),
    'restore-confirmation' => array('error', 'Type RESTORE exactly to confirm the destructive restore.'),
    'restore-file' => array('error', 'Choose a valid JSON backup file smaller than 100 MB.'),
    'restore-invalid' => array('error', 'That file is not a compatible IDLM backup.'),
    'restore-failed' => array('error', 'Restore failed. Existing data was rolled back.'),
);
?>
<?php if (!empty($can_manage_options)): ?>
<div id="settings-backup" class="idlm-settings-tab-content <?php echo $active_tab === 'backup' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-backup" <?php echo $active_tab === 'backup' ? '' : 'hidden'; ?>>
    <?php if (isset($messages[$notice])): ?><div class="notice notice-<?php echo esc_attr($messages[$notice][0]); ?> is-dismissible idlm-settings-backup-notice" role="status" data-idlm-settings-toast data-idlm-toast-autoclose="3000"><p><?php echo esc_html($messages[$notice][1]); ?></p></div><?php endif; ?>
    <article class="idlm-card idlm-backup-export-card">
        <h3>Backup plugin data</h3>
        <p class="idlm-card-desc">Download a JSON copy of all Interior Design Lead Manager custom-table data and non-secret plugin settings.</p>
        <p class="description">Backups do not include WordPress users, Media Library files, API credentials, or scheduled jobs.</p>
        <button type="submit" form="idlm-export-backup-form" class="idlm-btn idlm-btn-secondary">Download backup</button>
    </article>
    <article class="idlm-card idlm-card-danger">
        <h3>Restore plugin data</h3>
        <p class="idlm-danger-copy">Restoring replaces all current IDLM custom-table data and non-secret plugin settings with the selected backup. Current API credentials are preserved; queued outbound jobs are cleared. Backup files must be smaller than 100 MB.</p>
        <div class="idlm-backup-restore-fields">
            <div class="idlm-form-group"><label for="idlm-backup-file">Backup file</label><input form="idlm-restore-backup-form" type="file" id="idlm-backup-file" name="idlm_backup_file" accept="application/json,.json" required></div>
            <div class="idlm-form-group"><label for="idlm-restore-confirmation">Type <strong>RESTORE</strong> to confirm</label><input form="idlm-restore-backup-form" type="text" id="idlm-restore-confirmation" name="idlm_restore_confirmation" autocomplete="off" required></div>
        </div>
        <div class="idlm-backup-restore-action"><button type="submit" form="idlm-restore-backup-form" class="idlm-btn idlm-btn-danger">Restore backup</button></div>
    </article>
</div>
<?php endif; ?>
