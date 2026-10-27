<?php
if (!defined('ABSPATH')) { exit; }
$idlm_active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$idlm_can_manage_options = !empty($context['can_manage_options']);
// phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0137: (guarded template): the authorized settings template reads an allowlisted notice key to display fixed text; restore/export retain their own dedicated nonces and administrator guards.
$idlm_notice = isset($_GET['idlm_backup_notice']) ? sanitize_key(wp_unslash($_GET['idlm_backup_notice'])) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$idlm_messages = array(
    'restored' => array('success', 'Backup restored. Queued outbound jobs were cleared; review integrations before resuming any manual sync.'),
    'restore-confirmation' => array('error', 'Type RESTORE exactly to confirm the destructive restore.'),
    'restore-file' => array('error', 'Choose a valid JSON backup file smaller than 100 MB.'),
    'restore-invalid' => array('error', 'That file is not a compatible IDLM backup.'),
    'restore-failed' => array('error', 'Restore failed. Existing data was rolled back.'),
);
?>
<?php if (!empty($idlm_can_manage_options)): ?>
<div id="settings-backup" class="idlm-settings-tab-content <?php echo $idlm_active_tab === 'backup' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-backup" <?php echo $idlm_active_tab === 'backup' ? '' : 'hidden'; ?>>
    <?php if (isset($idlm_messages[$idlm_notice])): ?><div class="notice notice-<?php echo esc_attr($idlm_messages[$idlm_notice][0]); ?> is-dismissible idlm-settings-backup-notice" role="status" data-idlm-settings-toast data-idlm-toast-autoclose="3000"><p><?php echo esc_html($idlm_messages[$idlm_notice][1]); ?></p></div><?php endif; ?>
    <article class="idlm-card idlm-backup-export-card">
        <h3>Backup plugin data</h3>
        <p class="idlm-card-desc">Download a consistent JSON copy of CRM records and non-secret plugin settings.</p>
        <p class="description">Private lead files, WordPress attachments, users, API credentials and scheduled jobs are excluded. Use a full WordPress database backup to recover private files and their access records.</p>
        <button type="submit" form="idlm-export-backup-form" class="idlm-btn idlm-btn-secondary">Download backup</button>
    </article>
    <article class="idlm-card idlm-card-danger">
        <h3>Restore plugin data</h3>
        <p class="idlm-danger-copy">Restoring replaces CRM records and non-secret plugin settings. Use a full WordPress database restore when private lead files are present; this JSON restore is blocked to preserve those files. Current API credentials are preserved; queued outbound jobs are cleared. Restore requires a complete backup from this same site with matching schema and assigned WordPress users. Active CRM work must finish first. Quotation number counters never move backwards, and old working copies are discarded. Backup files must be smaller than 100 MB.</p>
        <div class="idlm-backup-restore-fields">
            <div class="idlm-form-group"><label for="idlm-backup-file">Backup file</label><input form="idlm-restore-backup-form" type="file" id="idlm-backup-file" name="idlm_backup_file" accept="application/json,.json" required></div>
            <div class="idlm-form-group"><label for="idlm-restore-confirmation">Type <strong>RESTORE</strong> to confirm</label><input form="idlm-restore-backup-form" type="text" id="idlm-restore-confirmation" name="idlm_restore_confirmation" autocomplete="off" required></div>
        </div>
        <div class="idlm-backup-restore-action"><button type="submit" form="idlm-restore-backup-form" class="idlm-btn idlm-btn-danger">Restore backup</button></div>
    </article>
</div>
<?php endif; ?>
