<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_logs = isset($context['logs']) && is_array($context['logs']) ? $context['logs'] : array();
$idlm_filters = isset($context['filters']) && is_array($context['filters']) ? $context['filters'] : array();
$idlm_retry_nonce = isset($context['retry_nonce']) ? (string) $context['retry_nonce'] : '';
$idlm_filter_keys = array('log_search', 'log_status', 'log_date_from', 'log_date_to');
$idlm_clear_url = remove_query_arg($idlm_filter_keys);
$idlm_has_filters = !empty($idlm_filters['search']) || !empty($idlm_filters['status']) || !empty($idlm_filters['date_from']) || !empty($idlm_filters['date_to']);
?>
<div class="wrap idlm-wrap idlm-logs-page" data-idlm-log-page="capi" data-idlm-log-nonce="<?php echo esc_attr($idlm_retry_nonce); ?>">
    <header class="idlm-header">
        <div class="idlm-header-meta">
            <h1>Conversion delivery log</h1>
        </div>
        <div class="idlm-header-actions">
            <button type="button" class="idlm-btn idlm-btn-secondary idlm-header-filter-button" data-idlm-filter-open aria-controls="idlm-capi-log-filters" aria-expanded="false">
                <span class="dashicons dashicons-filter" aria-hidden="true"></span><span>Filters</span><span class="idlm-filter-count" data-idlm-filter-count aria-label="0 active filters" hidden>0</span>
            </button>
        </div>
    </header>

    <div class="idlm-filters-backdrop" data-idlm-filter-backdrop hidden></div>
    <form method="get" class="idlm-filters-panel idlm-log-filters" id="idlm-capi-log-filters" data-idlm-filter-drawer aria-label="Filter conversion delivery logs" role="dialog" aria-modal="true" aria-labelledby="idlm-capi-log-filters-title" aria-hidden="true" tabindex="-1">
        <div class="idlm-filters-panel-header"><div><h2 id="idlm-capi-log-filters-title">Filter conversion logs</h2><p>Narrow delivery events by status or date.</p></div><button type="button" class="idlm-filters-close" data-idlm-filter-close aria-label="Close filters"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></div>
        <div class="idlm-filters-panel-body">
        <?php if (is_admin()): ?>
            <input type="hidden" name="page" value="idlm_capi_logs">
        <?php else: ?>
            <input type="hidden" name="idlm_view" value="capi-logs">
        <?php endif; ?>
        <div class="idlm-form-group idlm-log-search-field">
            <label for="idlm-capi-log-search">Search logs</label>
            <input class="idlm-input" id="idlm-capi-log-search" name="log_search" type="search" data-idlm-filter-field value="<?php echo esc_attr($idlm_filters['search'] ?? ''); ?>" placeholder="Email, lead ID, event, or platform">
        </div>
        <div class="idlm-form-group">
            <label for="idlm-capi-log-status">Delivery status</label>
            <select class="idlm-input" id="idlm-capi-log-status" name="log_status" data-idlm-filter-field>
                <option value="">All statuses</option>
                <option value="success" <?php selected($idlm_filters['status'] ?? '', 'success'); ?>>Success</option>
                <option value="failed" <?php selected($idlm_filters['status'] ?? '', 'failed'); ?>>Failed</option>
            </select>
        </div>
        <div class="idlm-form-group">
            <label for="idlm-capi-log-date-from">From date</label>
            <input class="idlm-input" id="idlm-capi-log-date-from" name="log_date_from" type="date" data-idlm-filter-field value="<?php echo esc_attr($idlm_filters['date_from'] ?? ''); ?>">
        </div>
        <div class="idlm-form-group">
            <label for="idlm-capi-log-date-to">To date</label>
            <input class="idlm-input" id="idlm-capi-log-date-to" name="log_date_to" type="date" data-idlm-filter-field value="<?php echo esc_attr($idlm_filters['date_to'] ?? ''); ?>">
        </div>
        <div class="idlm-log-filter-actions">
            <?php if ($idlm_has_filters): ?><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url($idlm_clear_url); ?>">Clear</a><?php endif; ?>
            <button class="idlm-btn idlm-btn-primary" type="submit">Apply filters</button>
        </div>
        </div>
    </form>

    <div class="idlm-bulk-capi-bar" data-idlm-capi-bulk-bar hidden>
        <span class="idlm-selected-capi-count" data-idlm-selected-capi-count>0 logs selected</span>
        <button class="idlm-btn idlm-btn-primary" type="button" id="idlm-retry-capi-btn">Retry selected events</button>
    </div>
    <p class="idlm-log-page-status" role="status" aria-live="polite" data-idlm-log-page-status></p>

    <div class="idlm-table-wrapper idlm-log-table-region" role="region" aria-label="Conversion delivery log entries" tabindex="0">
        <table class="idlm-table idlm-log-table">
            <caption class="screen-reader-text">The latest 50 conversion delivery events matching the selected filters.</caption>
            <thead>
                <tr>
                    <th scope="col" class="idlm-selection-column"><label class="screen-reader-text" for="idlm-select-all-capi">Select all shown conversion logs</label><input class="idlm-checkbox-control" type="checkbox" id="idlm-select-all-capi"></th>
                    <th scope="col">Time</th>
                    <th scope="col">Lead</th>
                    <th scope="col">Platform</th>
                    <th scope="col">Event</th>
                    <th scope="col">Status</th>
                    <th scope="col">Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$idlm_logs): ?>
                    <tr><td colspan="7" class="idlm-table-empty"><?php echo esc_html($idlm_has_filters ? 'No conversion logs match these filters.' : 'No conversion logs have been recorded yet.'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($idlm_logs as $idlm_log):
                        $idlm_lead_label = $idlm_log->email ? $idlm_log->email : 'Lead #' . $idlm_log->lead_id;
                        $idlm_entry_label = sprintf('%s for %s at %s', $idlm_log->event_name, $idlm_lead_label, IDLM_Admin::format_datetime($idlm_log->created_at));
                    ?>
                        <tr>
                            <td data-label="Select" class="idlm-selection-column"><label class="screen-reader-text" for="idlm-capi-log-<?php echo esc_attr($idlm_log->id); ?>">Select <?php echo esc_html($idlm_entry_label); ?></label><input class="idlm-checkbox-control idlm-capi-log-selector" type="checkbox" id="idlm-capi-log-<?php echo esc_attr($idlm_log->id); ?>" value="<?php echo esc_attr($idlm_log->id); ?>"></td>
                            <td data-label="Time" class="idlm-table-cell-nowrap"><?php echo esc_html(IDLM_Admin::format_datetime($idlm_log->created_at)); ?></td>
                            <td data-label="Lead"><span class="idlm-log-primary"><?php echo esc_html($idlm_lead_label); ?></span><small>Lead #<?php echo esc_html((string) $idlm_log->lead_id); ?></small></td>
                            <td data-label="Platform"><span class="idlm-badge idlm-badge-<?php echo esc_attr($idlm_log->platform); ?>"><?php echo esc_html(ucfirst($idlm_log->platform)); ?></span></td>
                            <td data-label="Event"><code class="idlm-log-token"><?php echo esc_html($idlm_log->event_name); ?></code></td>
                            <td data-label="Status"><span class="idlm-badge idlm-badge-<?php echo 'success' === $idlm_log->status ? 'success' : 'failed'; ?>"><?php echo esc_html(ucfirst((string) $idlm_log->status)); ?></span></td>
                            <td data-label="Details"><button class="idlm-btn idlm-btn-secondary idlm-btn-compact" type="button" data-idlm-log-inspect data-log-type="capi" data-log-id="<?php echo esc_attr($idlm_log->id); ?>" data-log-label="<?php echo esc_attr($idlm_entry_label); ?>" aria-haspopup="dialog">Inspect payload</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php IDLM_View::display('admin/logs/payload-dialog.php', array('dialog_id' => 'idlm-capi-payload-dialog')); ?>
</div>
