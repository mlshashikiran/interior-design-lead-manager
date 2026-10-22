<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_logs = isset($context['logs']) && is_array($context['logs']) ? $context['logs'] : array();
$idlm_filters = isset($context['filters']) && is_array($context['filters']) ? $context['filters'] : array();
$idlm_filter_keys = array('log_search', 'log_status', 'log_date_from', 'log_date_to');
$idlm_clear_url = remove_query_arg($idlm_filter_keys);
$idlm_has_filters = !empty($idlm_filters['search']) || !empty($idlm_filters['status']) || !empty($idlm_filters['date_from']) || !empty($idlm_filters['date_to']);
?>
<div class="wrap idlm-wrap idlm-logs-page" data-idlm-log-page="audience" data-idlm-log-nonce="<?php echo esc_attr(wp_create_nonce('idlm_admin_nonce')); ?>">
    <header class="idlm-header">
        <div class="idlm-header-meta">
            <h1>Audience sync log</h1>
        </div>
        <div class="idlm-header-actions">
            <button type="button" class="idlm-btn idlm-btn-secondary idlm-header-filter-button" data-idlm-filter-open aria-controls="idlm-audience-log-filters" aria-expanded="false">
                <span class="dashicons dashicons-filter" aria-hidden="true"></span><span>Filters</span><span class="idlm-filter-count" data-idlm-filter-count aria-label="0 active filters" hidden>0</span>
            </button>
        </div>
    </header>

    <div class="idlm-filters-backdrop" data-idlm-filter-backdrop hidden></div>
    <form method="get" class="idlm-filters-panel idlm-log-filters" id="idlm-audience-log-filters" data-idlm-filter-drawer aria-label="Filter audience sync logs" role="dialog" aria-modal="true" aria-labelledby="idlm-audience-log-filters-title" aria-hidden="true" tabindex="-1">
        <div class="idlm-filters-panel-header"><div><h2 id="idlm-audience-log-filters-title">Filter audience logs</h2><p>Narrow sync events by status or date.</p></div><button type="button" class="idlm-filters-close" data-idlm-filter-close aria-label="Close filters"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
        <div class="idlm-filters-panel-body">
        <?php if (is_admin()): ?>
            <input type="hidden" name="page" value="idlm_audience_sync_logs">
        <?php else: ?>
            <input type="hidden" name="idlm_view" value="audience-logs">
        <?php endif; ?>
        <div class="idlm-form-group idlm-log-search-field">
            <label for="idlm-audience-log-search">Search logs</label>
            <input class="idlm-input" id="idlm-audience-log-search" name="log_search" type="search" data-idlm-filter-field value="<?php echo esc_attr($idlm_filters['search'] ?? ''); ?>" placeholder="Audience ID or platform">
        </div>
        <div class="idlm-form-group">
            <label for="idlm-audience-log-status">Delivery status</label>
            <select class="idlm-input" id="idlm-audience-log-status" name="log_status" data-idlm-filter-field>
                <option value="">All statuses</option>
                <option value="success" <?php selected($idlm_filters['status'] ?? '', 'success'); ?>>Success</option>
                <option value="failed" <?php selected($idlm_filters['status'] ?? '', 'failed'); ?>>Failed</option>
            </select>
        </div>
        <div class="idlm-form-group">
            <label for="idlm-audience-log-date-from">From date</label>
            <input class="idlm-input" id="idlm-audience-log-date-from" name="log_date_from" type="date" data-idlm-filter-field value="<?php echo esc_attr($idlm_filters['date_from'] ?? ''); ?>">
        </div>
        <div class="idlm-form-group">
            <label for="idlm-audience-log-date-to">To date</label>
            <input class="idlm-input" id="idlm-audience-log-date-to" name="log_date_to" type="date" data-idlm-filter-field value="<?php echo esc_attr($idlm_filters['date_to'] ?? ''); ?>">
        </div>
        <div class="idlm-log-filter-actions">
            <?php if ($idlm_has_filters): ?><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url($idlm_clear_url); ?>">Clear</a><?php endif; ?>
            <button class="idlm-btn idlm-btn-primary" type="submit">Apply filters</button>
        </div>
        </div>
    </form>

    <p class="idlm-log-page-status" role="status" aria-live="polite" data-idlm-log-page-status></p>
    <div class="idlm-table-wrapper idlm-log-table-region" role="region" aria-label="Audience sync log entries" tabindex="0">
        <table class="idlm-table idlm-log-table">
            <caption class="screen-reader-text">The latest 50 audience sync events matching the selected filters.</caption>
            <thead>
                <tr>
                    <th scope="col">Time</th>
                    <th scope="col">Platform</th>
                    <th scope="col">Audience</th>
                    <th scope="col">Leads synced</th>
                    <th scope="col">Status</th>
                    <th scope="col">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$idlm_logs): ?>
                    <tr><td colspan="6" class="idlm-table-empty"><?php echo esc_html($idlm_has_filters ? 'No audience logs match these filters.' : 'No audience sync logs have been recorded yet.'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($idlm_logs as $idlm_log):
                        $idlm_entry_label = sprintf('%s audience %s at %s', ucfirst($idlm_log->platform), $idlm_log->audience_id, IDLM_Admin::format_datetime($idlm_log->created_at));
                    ?>
                        <tr>
                            <td data-label="Time" class="idlm-table-cell-nowrap"><?php echo esc_html(IDLM_Admin::format_datetime($idlm_log->created_at)); ?></td>
                            <td data-label="Platform"><span class="idlm-badge idlm-badge-<?php echo esc_attr($idlm_log->platform); ?>"><?php echo esc_html(ucfirst($idlm_log->platform)); ?></span></td>
                            <td data-label="Audience"><code class="idlm-log-token"><?php echo esc_html($idlm_log->audience_id); ?></code></td>
                            <td data-label="Leads synced"><span class="idlm-log-primary"><?php echo esc_html(number_format_i18n((int) $idlm_log->lead_count)); ?></span></td>
                            <td data-label="Status"><span class="idlm-badge idlm-badge-<?php echo 'success' === $idlm_log->status ? 'success' : 'failed'; ?>"><?php echo esc_html(ucfirst((string) $idlm_log->status)); ?></span></td>
                            <td data-label="Details"><button class="idlm-btn idlm-btn-secondary idlm-btn-compact" type="button" data-idlm-log-inspect data-log-type="audience" data-log-id="<?php echo esc_attr($idlm_log->id); ?>" data-log-label="<?php echo esc_attr($idlm_entry_label); ?>" aria-haspopup="dialog">Inspect payload</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php IDLM_View::display('admin/logs/payload-dialog.php', array('dialog_id' => 'idlm-audience-payload-dialog')); ?>
</div>
