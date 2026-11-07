<?php
/**
 * Quotation workspace and document renderer.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Quotation_Renderer extends IDLM_Quotations {
    /** The list's selected period from the shared list ('' for all time). */
    private static $list_range = '';

    public static function render_settings_page() {
        if (!self::can('idlm_manage_quote_settings')) {
            wp_die('You do not have permission to manage quotation settings.', 403);
        }
        $settings = self::settings();
        $payment_structure = json_decode((string) $settings['payment_structure'], true);
        $payment_structure = is_array($payment_structure) ? $payment_structure : array();
        ?>
        <div class="wrap idlm-wrap idlm-quotes-wrap"><header class="idlm-header"><div><h1><?php echo esc_html('Quotation setup'); ?></h1><p class="idlm-header-subtitle">Manage the defaults used across every quotation, from numbering and pricing to client-facing document templates.</p></div></header><?php self::render_workspace_nav('settings'); self::render_notice(); ?>
        <form class="idlm-quotation-settings" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="idlm_save_quote_settings"><input type="hidden" name="idlm_return_url" value="<?php echo esc_attr(self::return_url()); ?>"><?php wp_nonce_field('idlm_save_quote_settings'); ?>
        <div class="idlm-settings-foundation">
        <section class="idlm-quote-panel"><h2>Quote numbering</h2><div class="idlm-settings-inline idlm-settings-inline-four"><label>Prefix<input name="number_prefix" maxlength="20" value="<?php echo esc_attr($settings['number_prefix']); ?>"></label><label>Separator<select name="number_separator"><option value="-" <?php selected($settings['number_separator'], '-'); ?>>Hyphen (-)</option><option value="/" <?php selected($settings['number_separator'], '/'); ?>>Slash (/)</option><option value="_" <?php selected($settings['number_separator'], '_'); ?>>Underscore (_)</option></select></label><label>Sequence padding<input type="number" min="3" max="10" name="number_padding" value="<?php echo esc_attr($settings['number_padding']); ?>"></label><div class="idlm-settings-preview"><strong>Preview</strong><p><?php echo esc_html($settings['number_prefix'] . $settings['number_separator'] . wp_date('Y') . $settings['number_separator'] . str_pad('125', absint($settings['number_padding']), '0', STR_PAD_LEFT)); ?></p></div></div></section>
        <div class="idlm-settings-inline idlm-settings-inline-two"><section class="idlm-quote-panel"><h2>Currency</h2><p class="description">This currency applies to new quotations and catalogue prices. Saved quotations and their revisions retain their original currency.</p><label>Currency code<select name="currency_code"><option value="INR" <?php selected($settings['currency_code'], 'INR'); ?>>INR (₹) Indian Rupee</option><option value="USD" <?php selected($settings['currency_code'], 'USD'); ?>>USD ($) US Dollar</option><option value="EUR" <?php selected($settings['currency_code'], 'EUR'); ?>>EUR (€) Euro</option><option value="GBP" <?php selected($settings['currency_code'], 'GBP'); ?>>GBP (£) British Pound</option><option value="AED" <?php selected($settings['currency_code'], 'AED'); ?>>AED (د.إ) UAE Dirham</option><option value="AUD" <?php selected($settings['currency_code'], 'AUD'); ?>>AUD (A$) Australian Dollar</option><option value="CAD" <?php selected($settings['currency_code'], 'CAD'); ?>>CAD (C$) Canadian Dollar</option><option value="SGD" <?php selected($settings['currency_code'], 'SGD'); ?>>SGD (S$) Singapore Dollar</option><option value="JPY" <?php selected($settings['currency_code'], 'JPY'); ?>>JPY (¥) Japanese Yen</option></select></label></section><section class="idlm-quote-panel"><h2><?php echo esc_html('Soft close addition'); ?></h2><p class="description">Set the percentage available to apply to individual quotations. It is calculated from the subtotal after wooden discount.</p><label>Global soft close percentage<input type="number" min="0" max="100" step="0.01" name="soft_close_percent" value="<?php echo esc_attr($settings['soft_close_percent']); ?>">%</label></section></div>
        </div>
        <section class="idlm-quote-panel idlm-settings-pdf"><h2>PDF appearance</h2><p class="description">Set the visual defaults for client-facing quotation documents.</p><div class="idlm-settings-inline idlm-settings-inline-three"><label>Logo URL<input type="url" name="company_logo" value="<?php echo esc_attr($settings['company_logo']); ?>"></label><label>Accent colour<input type="color" name="accent_color" value="<?php echo esc_attr($settings['accent_color']); ?>"></label><label>Paper size<select name="paper_size"><option value="A4" <?php selected($settings['paper_size'], 'A4'); ?>>A4</option><option value="Letter" <?php selected($settings['paper_size'], 'Letter'); ?>>Letter</option><option value="A3" <?php selected($settings['paper_size'], 'A3'); ?>>A3</option><option value="Legal" <?php selected($settings['paper_size'], 'Legal'); ?>>Legal</option></select></label></div></section>
        <section class="idlm-quote-panel idlm-settings-template"><div class="idlm-settings-panel-heading"><div><h2>Cover page builder</h2><p class="description">Include this HTML as a separate first page when saving a new quotation revision. Earlier revisions keep their saved content.</p></div><label class="idlm-settings-toggle"><input type="checkbox" name="cover_page_enabled" value="1" <?php checked(!empty($settings['cover_page_enabled'])); ?>><span class="idlm-settings-toggle__track" aria-hidden="true"></span> Enable cover page</label></div><details class="idlm-settings-disclosure"><summary>Edit cover page HTML</summary><label class="idlm-quote-textarea">Cover page HTML<textarea rows="14" name="cover_page_html" spellcheck="false" placeholder="&lt;section style=&quot;min-height: 900px; display: grid; place-items: center;&quot;&gt;...&lt;/section&gt;"><?php echo esc_textarea($settings['cover_page_html']); ?></textarea></label><p class="description">Enter safe HTML and inline CSS, enable the cover page, then save Quotation Setup. Empty content does not create a cover page.</p></details></section>
        <section class="idlm-quote-panel"><h2>Standard payment structure</h2><p class="description">Define reusable percentage-based milestones. Their amounts are calculated from each quotation's total including tax.</p><div class="idlm-payment-structure" data-payment-structure><?php foreach (array_merge($payment_structure, array(array('name' => '', 'percentage' => ''))) as $payment_index => $payment_row): ?><div class="idlm-payment-structure-row"><input name="payment_structure[<?php echo esc_attr($payment_index); ?>][name]" aria-label="Milestone name" placeholder="Milestone name" value="<?php echo esc_attr($payment_row['name'] ?? ''); ?>"><input type="number" min="0" max="100" step="0.01" name="payment_structure[<?php echo esc_attr($payment_index); ?>][percentage]" aria-label="Milestone percentage" placeholder="%" value="<?php echo esc_attr($payment_row['percentage'] ?? ''); ?>"><button type="button" class="button-link-delete" data-remove-payment-row>Remove</button></div><?php endforeach; ?></div><p class="description" data-payment-total aria-live="polite"></p><div class="idlm-payment-structure-actions"><button type="button" class="button" data-add-payment-row>Add milestone</button><p class="description">Milestones must total no more than 100%. Insert <code>[idlm_payment_structure]</code> in Content after the quote to render the table.</p></div></section>
        <section class="idlm-quote-panel idlm-settings-template"><div class="idlm-settings-panel-heading"><div><h2>Content after the quote</h2><p class="description">Include this HTML after the quotation when saving a new revision. Earlier revisions keep their saved content.</p></div><label class="idlm-settings-toggle"><input type="checkbox" name="after_quote_enabled" value="1" <?php checked(!empty($settings['after_quote_enabled'])); ?>><span class="idlm-settings-toggle__track" aria-hidden="true"></span> Enable closing content</label></div><details class="idlm-settings-disclosure"><summary>Edit after-quote HTML</summary><label class="idlm-quote-textarea">After-quote HTML<textarea rows="14" name="after_quote_html" spellcheck="false" placeholder="&lt;section&gt;&lt;h2&gt;Next steps&lt;/h2&gt;[idlm_payment_structure]&lt;/section&gt;"><?php echo esc_textarea($settings['after_quote_html']); ?></textarea></label><p class="description">Enter safe HTML and inline CSS, enable closing content, then save Quotation Setup. Empty content does not create a closing page. Available shortcode: <code>[idlm_payment_structure]</code>.</p></details></section>
        <div class="idlm-settings-actions"><span class="idlm-settings-actions__note" data-settings-dirty-note>New quotations use these defaults. Saved revisions keep their own.</span><button class="idlm-btn idlm-btn-primary"><?php echo esc_html('Save quotation setup'); ?></button></div></form></div><?php
    }

    public static function render_page() {
        if (!self::can('idlm_view_quotes')) {
            wp_die('You do not have permission to view quotations.', 403);
        }
        // Read-only routing parameters; mutations use nonce-protected admin-post handlers.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $workspace = sanitize_key($_GET['workspace'] ?? 'quotes');
        if ('catalogue' === $workspace && self::can('idlm_manage_quote_items')) {
            self::render_catalogue_page();
            return;
        }
        if ('settings' === $workspace && self::can('idlm_manage_quote_settings')) {
            self::render_settings_page();
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $action = sanitize_key($_GET['action'] ?? 'list');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $quote_id = absint($_GET['quote_id'] ?? 0);
        if ('edit' === $action || 'new' === $action) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            self::render_editor($quote_id, absint($_GET['lead_id'] ?? 0));
        } elseif ('view' === $action && $quote_id) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            self::render_detail($quote_id, absint($_GET['revision_id'] ?? 0));
        } else {
            self::render_list();
        }
    }

    private static function render_workspace_nav($active) {
        ?><nav class="idlm-quotation-workspace-nav"><a class="<?php echo 'quotes' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url(self::view_url(array('workspace' => 'quotes'))); ?>">Quotations</a><?php if (self::can('idlm_manage_quote_items')): ?><a class="<?php echo 'catalogue' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url(self::view_url(array('workspace' => 'catalogue'))); ?>"><?php echo esc_html('Item catalogue'); ?></a><?php endif; ?><?php if (self::can('idlm_manage_quote_settings')): ?><a class="<?php echo 'settings' === $active ? 'is-active' : ''; ?>" href="<?php echo esc_url(self::view_url(array('workspace' => 'settings'))); ?>"><?php echo esc_html('Quotation setup'); ?></a><?php endif; ?></nav><?php
    }

    private static function render_catalogue_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search = sanitize_text_field(wp_unslash($_GET['catalogue_search'] ?? ''));
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0097: render_catalogue_page(): protected catalogue/list renderers use capability checks and GET values only for bounded paging, fixed sort directions and read-only filters; dates delegate to clean_date sanitization; no GET performs mutations.
        $catalogue_page = max(1, absint($_GET['catalogue_page'] ?? 1));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0098: render_catalogue_page(): protected catalogue/list renderers use capability checks and GET values only for bounded paging, fixed sort directions and read-only filters; dates delegate to clean_date sanitization; no GET performs mutations.
        $catalogue_sort = sanitize_key(wp_unslash($_GET['catalogue_sort'] ?? 'status'));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $catalogue_sort_options = array('code' => 'Code', 'category' => 'Category', 'item' => 'Item', 'unit' => 'Unit', 'wood' => 'Wood', 'rate' => 'Rate', 'status' => 'Status');
        if (!isset($catalogue_sort_options[$catalogue_sort])) { $catalogue_sort = 'status'; }
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0099: render_catalogue_page(): protected catalogue/list renderers use capability checks and GET values only for bounded paging, fixed sort directions and read-only filters; dates delegate to clean_date sanitization; no GET performs mutations.
        $catalogue_order = 'desc' === strtolower(sanitize_key(wp_unslash($_GET['catalogue_order'] ?? 'asc'))) ? 'desc' : 'asc';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $per_page = 100;
        $total_items = IDLM_Quotation_Repository::count_catalogue_items($search);
        $items = IDLM_Quotation_Repository::list_catalogue_items($search, $catalogue_page, $per_page, $catalogue_sort, $catalogue_order);
        $category_rows = IDLM_Quotation_Repository::list_catalogue_categories();
        $categories = array_values(array_map(function ($category) { return $category->category_name; }, array_filter($category_rows, function ($category) { return 'active' === $category->status; })));
        $units = IDLM_Quotation_Repository::list_active_catalogue_units();
        wp_localize_script('idlm-quotations', 'idlmCatalogueData', array(
            'categories' => $categories,
            'manageUrl' => self::view_url(array('workspace' => 'catalogue', 'manage_categories' => 1, 'catalogue_search' => $search ?: false)),
        ));
        // Selecting an item only changes the editor state; saving remains nonce and capability protected.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $edit_item_id = absint($_GET['edit_item'] ?? 0);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $add_item = !empty($_GET['add_item']);
        $edit_item = $edit_item_id ? IDLM_Quotation_Repository::get_catalogue_item($edit_item_id) : null;
        $form_item = $edit_item ?: (object) array(
            'id' => 0, 'item_code' => '', 'category' => '', 'item_name' => '', 'description' => '',
            'unit' => 'Nos', 'is_wood' => 0, 'default_rate' => '',
        );
        self::render_catalogue_crm($search, $items, $total_items, $catalogue_page, $per_page, $catalogue_sort, $catalogue_order, $catalogue_sort_options, $categories, $units, $edit_item, $edit_item_id, $form_item);
        self::render_category_manager($category_rows);
        self::render_unit_manager($units);
    }

    /** Screen money: the same grouping the live editor totals use. Documents keep money(). */
    private static function display_money($value, $currency = '') {
        if ('' === $currency) { $currency = self::settings()['currency_code']; }
        $currency = strtoupper((string) $currency);
        if ('INR' !== $currency) { return self::money($value, $currency); }
        $value = (float) $value;
        $parts = explode('.', number_format(abs($value), 2, '.', ''));
        $whole = $parts[0];
        if (strlen($whole) > 3) {
            $whole = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3)) . ',' . substr($whole, -3);
        }
        return ($value < 0 ? '-' : '') . self::currency_symbol($currency) . $whole . '.' . $parts[1];
    }

    /** Screen date in the Leads format. Stored values stay untouched. */
    private static function display_date($mysql_date, $format = 'M j, Y') {
        if (empty($mysql_date) || 0 === strpos((string) $mysql_date, '0000-00-00')) { return '—'; }
        return IDLM_Admin_Presenter::format_datetime($mysql_date, $format);
    }

    /** The revision part of a stored revision name, such as R03. */
    private static function short_revision($revision_name) {
        return preg_match('/(R\d+)$/', (string) $revision_name, $match) ? $match[1] : ((string) $revision_name ?: '—');
    }

    /** Status is text first; the tone only supports it. */
    private static function status_badge($status) {
        $status = trim((string) $status) ?: 'Draft';
        $tones = array('shared' => 'is-info', 'accepted' => 'is-success', 'approved' => 'is-success', 'rejected' => 'is-danger', 'expired' => 'is-warning');
        $tone = $tones[strtolower($status)] ?? 'is-neutral';
        return '<span class="idlm-badge ' . esc_attr($tone) . '">' . esc_html(ucfirst(strtolower($status))) . '</span>';
    }

    /**
     * Name who created a record. Migrated quotations were created with no user,
     * so they say that plainly instead of "Unknown".
     */
    private static function creator_markup($user_id, $name) {
        if ($name) { return esc_html($name); }
        if (!absint($user_id)) {
            return '<span class="idlm-quote-creator-none" title="Created from the lead’s quote value during migration, so no user was recorded.">Migrated</span>';
        }
        return '<span class="idlm-quote-creator-none" title="This user account no longer exists.">Former user</span>';
    }

    /** Previous and next links plus the range shown. The live list replaces them after a filter. */
    private static function render_pagination_crm($type, $noun, $page_arg, $page, $per_page, $total, $base_url) {
        $pages = max(1, (int) ceil($total / $per_page));
        $first = $total ? (($page - 1) * $per_page) + 1 : 0;
        $last = min($total, $page * $per_page);
        ?><div class="idlm-pagination idlm-quote-pagination" data-live-list-pagination="<?php echo esc_attr($type); ?>"><?php if ($total): ?><span class="idlm-pagination-info"><?php echo esc_html(number_format_i18n($first) . '–' . number_format_i18n($last) . ' of ' . number_format_i18n($total) . ' ' . $noun); ?></span><?php endif; ?><?php if ($pages > 1): ?><?php if ($page > 1): ?><a class="idlm-btn idlm-btn-secondary" data-live-list-page="<?php echo esc_attr($page - 1); ?>" href="<?php echo esc_url(add_query_arg($page_arg, $page - 1, $base_url)); ?>">Previous</a><?php endif; ?><?php if ($page < $pages): ?><a class="idlm-btn idlm-btn-secondary" data-live-list-page="<?php echo esc_attr($page + 1); ?>" href="<?php echo esc_url(add_query_arg($page_arg, $page + 1, $base_url)); ?>">Next</a><?php endif; ?><?php endif; ?></div><?php
    }

    private static function render_list_crm($quotes, $total_quotes, $quote_page, $per_page, $search, $created_by, $date_from, $date_to, $lead_id) {
        $has_filters = '' !== $search || $created_by || $date_from || $date_to || '' !== self::$list_range;
        $can_create = self::can('idlm_create_quotes');
        $create_url = self::view_url(array('action' => 'new', 'lead_id' => $lead_id));
        $clear_url = self::view_url(array('quote_search' => false, 'created_by' => false, 'date_range' => false, 'date_from' => false, 'date_to' => false, 'quote_page' => false, 'lead_id' => $lead_id ?: false));
        ?><div class="wrap idlm-wrap idlm-quotes-wrap idlm-quote-list">
            <?php self::render_notice(); ?>
            <header class="idlm-header"><div><h1>Quotations</h1></div><?php if ($can_create): ?><a class="idlm-btn idlm-btn-primary" href="<?php echo esc_url($create_url); ?>">Create quotation</a><?php endif; ?></header>
            <?php self::render_workspace_nav('quotes'); ?>
            <form method="get" class="idlm-quote-filter" data-live-list="quotations" role="search" aria-label="Filter quotations"><?php if (is_admin()): ?><input type="hidden" name="page" value="idlm_quotations"><?php elseif (isset($_GET['idlm_view'])): // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><input type="hidden" name="idlm_view" value="<?php echo esc_attr(sanitize_key(wp_unslash($_GET['idlm_view']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"><?php endif; ?><label class="idlm-quote-filter__search"><span class="screen-reader-text">Search quotations</span><input type="search" name="quote_search" value="<?php echo esc_attr($search); ?>" placeholder="Quote, customer, phone or project" autocomplete="off"></label><?php if ($lead_id): ?><input type="hidden" name="lead_id" value="<?php echo esc_attr($lead_id); ?>"><?php endif; ?><label class="idlm-quote-filter__field"><span class="screen-reader-text">Created by</span><select name="created_by"><option value="">All creators</option><?php foreach (get_users(array('fields' => array('ID', 'display_name'))) as $user): ?><option value="<?php echo esc_attr($user->ID); ?>" <?php selected($created_by, $user->ID); ?>><?php echo esc_html($user->display_name); ?></option><?php endforeach; ?></select></label><label class="idlm-quote-filter__field"><span>Created</span><select name="date_range" id="idlm-quote-date-range" data-idlm-date-range><?php IDLM_Date_Ranges::print_options(self::$list_range, 'All time'); ?></select></label><label class="idlm-quote-filter__field idlm-quote-filter__date" data-idlm-date-custom-for="idlm-quote-date-range"<?php echo 'custom' === self::$list_range ? '' : ' hidden'; ?>><span>From</span><input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>"></label><label class="idlm-quote-filter__field idlm-quote-filter__date" data-idlm-date-custom-for="idlm-quote-date-range"<?php echo 'custom' === self::$list_range ? '' : ' hidden'; ?>><span>to</span><input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>"></label><button class="idlm-btn idlm-btn-secondary">Filter</button><a class="idlm-btn idlm-btn-quiet idlm-btn-secondary" data-quote-filter-clear href="<?php echo esc_url($clear_url); ?>" <?php echo $has_filters ? '' : 'hidden'; ?>>Clear filters</a><?php if ($lead_id): ?><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url(self::view_url()); ?>">Show all quotations</a><?php endif; ?></form>
            <div class="idlm-card"><div class="idlm-table-wrapper"><table class="idlm-table idlm-quote-list-table"><thead><tr><th scope="col">Quotation</th><th scope="col">Customer</th><th scope="col">Status</th><th scope="col" class="idlm-quote-num">Value</th><th scope="col">Created by</th><th scope="col">Updated</th><th scope="col"><span class="screen-reader-text">Action</span></th></tr></thead><tbody data-live-list-rows="quotations">
            <?php if (!$quotes): ?><tr class="idlm-quote-empty"><td colspan="7"><div class="idlm-state is-empty"><?php if ($has_filters): ?><strong>No quotations match these filters</strong><span>Try a different name, phone or date range.</span><div class="idlm-state__actions"><a class="idlm-btn idlm-btn-secondary" data-quote-filter-clear href="<?php echo esc_url($clear_url); ?>">Clear filters</a></div><?php else: ?><strong>No quotations yet</strong><span>A quotation is created for a lead, and revisions keep its history.</span><?php if ($can_create): ?><div class="idlm-state__actions"><a class="idlm-btn idlm-btn-primary" href="<?php echo esc_url($create_url); ?>">Create quotation</a></div><?php endif; ?><?php endif; ?></div></td></tr><?php endif; ?>
            <?php foreach ($quotes as $quote): $view_url = self::view_url(array('action' => 'view', 'quote_id' => $quote->id)); ?>
                <tr class="idlm-quote-row" data-href="<?php echo esc_url($view_url); ?>">
                    <td data-label="Quotation"><a class="idlm-quote-identity-link" href="<?php echo esc_url($view_url); ?>"><strong><?php echo esc_html($quote->quote_number); ?></strong></a><small><?php echo esc_html($quote->title); ?></small></td>
                    <td data-label="Customer"><strong><?php echo esc_html(trim($quote->first_name . ' ' . $quote->last_name) ?: '—'); ?></strong><small><?php echo esc_html(implode(' · ', array_filter(array($quote->project_name, $quote->phone)))); ?></small></td>
                    <td data-label="Status"><span class="idlm-quote-status-cell"><?php echo wp_kses_post(self::status_badge($quote->status ?? '')); ?><span class="idlm-quote-revision-label"><?php echo esc_html(self::short_revision($quote->revision_name)); ?></span></span></td>
                    <td data-label="Value" class="idlm-quote-num"><?php echo esc_html(self::display_money($quote->grand_total, $quote->currency_code ?? '')); ?></td>
                    <td data-label="Created by"><?php echo wp_kses_post(self::creator_markup($quote->created_by ?? 0, $quote->creator_name)); ?><small><?php echo esc_html(self::display_date($quote->created_at)); ?></small></td>
                    <td data-label="Updated"><span class="idlm-table-timestamp"><span class="idlm-table-timestamp-date"><?php echo esc_html(self::display_date($quote->updated_at)); ?></span><span class="idlm-table-timestamp-time"><?php echo esc_html(self::display_date($quote->updated_at, 'g:i A')); ?></span></span></td>
                    <td class="idlm-quote-row-action"><a class="idlm-btn idlm-btn-secondary idlm-btn-quiet" href="<?php echo esc_url($view_url); ?>" aria-label="<?php echo esc_attr('Open ' . $quote->quote_number); ?>">Open</a></td>
                </tr>
            <?php endforeach; ?></tbody></table></div></div><?php self::render_pagination_crm('quotations', 'quotations', 'quote_page', $quote_page, $per_page, $total_quotes, self::view_url(array('lead_id' => $lead_id ?: false))); ?><p class="screen-reader-text" aria-live="polite" data-live-list-status="quotations"></p>
        </div><?php
    }

    private static function render_detail_crm($bundle, $quote_id, $revision, $creator) {
        $currency = $revision->currency_code;
        $is_current = absint($revision->id) === absint($bundle->current_revision_id);
        $current_label = '';
        foreach ($bundle->revisions as $row) {
            if (absint($row->id) === absint($bundle->current_revision_id)) { $current_label = self::revision_label($row); }
        }
        $can_edit = IDLM_Quotation_Policy::can_mutate_lead($bundle->lead_id) && IDLM_Quotation_Policy::is_editable_draft($bundle, $revision) && self::can('idlm_edit_quote_drafts') && self::can('idlm_create_quote_revisions');
        $can_save_revision = self::can('idlm_edit_quote_drafts') && self::can('idlm_create_quote_revisions');
        $can_start_new = self::can('idlm_create_quotes') && IDLM_Quotation_Policy::can_mutate_lead($bundle->lead_id) && IDLM_Quotation_Policy::allows_new_quote($bundle);
        $has_items = (bool) array_filter($bundle->items, static function($item) { return 'header' !== ($item->row_type ?? 'item'); });
        $contact = array_filter(array($revision->customer_phone ?? '', $revision->customer_email ?? ''));
        $property = trim((string) ($revision->project_name ?? ''));
        $location = trim((string) ($revision->property_location ?? ''));
        ?><div class="wrap idlm-wrap idlm-quotes-wrap idlm-quote-detail"><?php self::render_notice(); ?>
        <header class="idlm-header"><div><h1><?php echo esc_html($bundle->quote_number); ?> · <?php echo esc_html($bundle->title); ?></h1></div><div class="idlm-header-actions"><?php if (self::can('idlm_download_quote_pdf') && !IDLM_Design_Revisions::may_share($revision->id)): ?><span class="idlm-badge is-warning">Waiting for design review</span><?php elseif (self::can('idlm_download_quote_pdf')): ?><a class="idlm-btn idlm-btn-secondary" target="_blank" rel="noopener" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=idlm_print_quote&quote_id=' . $quote_id . '&revision_id=' . absint($revision->id)), 'idlm_print_quote')); ?>">Preview and print</a><details class="idlm-quote-menu"><summary class="idlm-btn idlm-btn-secondary">Download</summary><div class="idlm-quote-menu__panel"><a href="<?php echo esc_url(IDLM_Quotation_Exporter::export_url($quote_id, $revision->id, 'pdf')); ?>">PDF</a><a href="<?php echo esc_url(IDLM_Quotation_Exporter::export_url($quote_id, $revision->id, 'csv')); ?>">CSV</a><a href="<?php echo esc_url(IDLM_Quotation_Exporter::export_url($quote_id, $revision->id, 'xlsx')); ?>">XLSX</a></div></details><?php endif; ?><?php if ($can_start_new): ?><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url(self::view_url(array('action' => 'new', 'lead_id' => absint($bundle->lead_id)))); ?>">New quotation</a><?php endif; ?><?php if ($can_edit): ?><a class="idlm-btn idlm-btn-primary" href="<?php echo esc_url(self::view_url(array('action' => 'edit', 'quote_id' => $quote_id))); ?>">Edit quotation</a><?php endif; ?></div></header><?php self::render_workspace_nav('quotes'); ?>
        <?php if (!$is_current): ?><div class="idlm-alert idlm-alert-warning" data-idlm-notice-inline role="status">You are viewing <?php echo esc_html($revision->revision_name); ?>, an earlier revision. It stays read-only. <a href="<?php echo esc_url(self::view_url(array('action' => 'view', 'quote_id' => $quote_id))); ?>">Open current revision<?php echo $current_label ? esc_html(' (' . $current_label . ')') : ''; ?></a></div><?php endif; ?>
        <div class="idlm-quote-summary idlm-quote-highlights">
            <div><span>Customer</span><strong><?php echo esc_html($revision->customer_name ?: '—'); ?></strong><?php if ($contact): ?><small><?php echo esc_html(implode(' · ', $contact)); ?></small><?php endif; ?></div>
            <div><span>Property</span><strong><?php echo esc_html($property ?: '—'); ?></strong><?php if ($location): ?><small><?php echo esc_html($location); ?></small><?php endif; ?></div>
            <div><span>Revision</span><strong class="idlm-quote-status-cell"><?php echo esc_html($revision->revision_name); ?> <?php echo wp_kses_post(self::status_badge($is_current ? ($bundle->status ?? '') : ($revision->status ?? ''))); ?></strong><small><?php echo esc_html($can_edit ? 'Editable. Saving creates the next revision.' : 'Read-only'); ?></small></div>
            <div><span>Created by</span><strong><?php echo wp_kses_post(self::creator_markup($revision->created_by, $creator ? $creator->display_name : '')); ?></strong><small><?php echo esc_html(self::display_date($revision->created_at, 'M j, Y, g:i A')); ?></small></div>
            <div class="idlm-quote-highlights__total"><span>Grand total</span><strong><?php echo esc_html(self::display_money($revision->grand_total, $currency)); ?></strong><small>Including GST</small></div>
        </div>
        <section class="idlm-quote-panel idlm-quote-detail-items"><h2>Sections and line items</h2><?php if ($has_items): ?><?php self::render_readonly_items($bundle, false); ?><?php self::render_detail_totals($revision); ?><?php else: ?><div class="idlm-state is-empty"><strong>No line items on this revision</strong><span><?php echo esc_html('Legacy quote migration' === ($revision->revision_reason ?? '') ? 'This quotation was migrated from the lead’s quote value, so only the grand total was recorded.' : 'This revision was saved without line items.'); ?><?php echo $can_edit && $can_save_revision ? esc_html(' Edit it to add items and save a new revision.') : ''; ?></span></div><?php endif; ?></section>
        <?php if (!empty($revision->internal_notes)): ?><section class="idlm-quote-panel idlm-additional-notes"><h2>Internal notes</h2><div class="idlm-quote-copy"><?php echo wp_kses_post(make_clickable(nl2br(esc_html($revision->internal_notes)))); ?></div></section><?php endif; ?>
        <section class="idlm-quote-panel idlm-quote-revisions"><h2>Revision history</h2><ol class="idlm-revision-list"><?php foreach ($bundle->revisions as $index => $row):
            $user = get_userdata($row->created_by);
            $older = $bundle->revisions[$index + 1] ?? null;
            $delta = $older ? round((float) $row->grand_total - (float) $older->grand_total, 2) : 0.0;
            $row_label = self::revision_label($row);
            $is_viewing = absint($row->id) === absint($revision->id);
            ?><li class="<?php echo $is_viewing ? 'is-viewing' : ''; ?>"<?php echo $is_viewing ? ' aria-current="true"' : ''; ?>><div class="idlm-quote-revision-id"><a href="<?php echo esc_url(self::view_url(array('action' => 'view', 'quote_id' => $quote_id, 'revision_id' => $row->id))); ?>"><?php echo esc_html($row_label); ?></a><?php if (absint($row->id) === absint($bundle->current_revision_id)): ?><span class="idlm-badge is-neutral">Current</span><?php endif; ?><?php if ($is_viewing): ?><span class="idlm-quote-revision-viewing">Viewing</span><?php endif; ?></div><div class="idlm-quote-revision-value"><span><?php echo esc_html(self::display_money($row->grand_total, $row->currency_code)); ?></span><?php if ($older): ?><small><?php echo esc_html(0.0 === $delta ? 'No change from ' . self::revision_label($older) : ($delta > 0 ? '+' : '') . self::display_money($delta, $row->currency_code) . ' from ' . self::revision_label($older)); ?></small><?php endif; ?></div><div class="idlm-quote-revision-remark"><?php echo esc_html($row->revision_reason ?: 'No remark recorded'); ?></div><small class="idlm-quote-revision-meta"><?php echo wp_kses_post(self::creator_markup($row->created_by, $user ? $user->display_name : '')); ?> · <?php echo esc_html(self::display_date($row->created_at, 'M j, Y, g:i A')); ?></small></li><?php endforeach; ?></ol></section></div><?php
    }

    /** Saved totals for the revision on screen. Values are read, never recalculated. */
    private static function render_detail_totals($revision) {
        $currency = $revision->currency_code;
        $tax_total = (float) $revision->cgst_total + (float) $revision->sgst_total + (float) $revision->igst_total;
        $additional_discount = (float) ($revision->additional_discount_amount ?? 0);
        $rows = array(array('Subtotal', $revision->subtotal, true));
        $rows[] = array('Wooden subtotal', $revision->wooden_subtotal ?? 0, (float) ($revision->wooden_subtotal ?? 0) > 0);
        $rows[] = array('Wooden discount (' . number_format_i18n((float) ($revision->wooden_discount_percent ?? 0), 2) . '%)', -1 * (float) ($revision->wooden_discount_amount ?? 0), (float) ($revision->wooden_discount_amount ?? 0) > 0);
        $rows[] = array('Soft close addition', $revision->soft_close_amount ?? 0, (float) ($revision->soft_close_amount ?? 0) > 0);
        $rows[] = array('Total', $revision->total_before_additional_discount ?? $revision->subtotal, true);
        $rows[] = array('Additional discount', -1 * $additional_discount, $additional_discount > 0);
        $rows[] = array('Total after additional discount', $revision->taxable_total ?? 0, $additional_discount > 0);
        $rows[] = array('GST (18%)', $tax_total, true);
        $rows[] = array('Round off', $revision->round_off_amount ?? 0, 0.0 !== (float) ($revision->round_off_amount ?? 0));
        ?><dl class="idlm-readonly-totals idlm-quote-detail-totals"><?php foreach ($rows as $row): if (!$row[2]) { continue; } ?><div><dt><?php echo esc_html($row[0]); ?></dt><dd><?php echo esc_html(self::display_money($row[1], $currency)); ?></dd></div><?php endforeach; ?><div class="idlm-grand-total"><dt>Grand total</dt><dd><?php echo esc_html(self::display_money($revision->grand_total, $currency)); ?></dd></div></dl><?php
    }

    /** Detail-screen measure. Print and PDF always pass $document = true and keep every figure. */
    private static function readonly_measure($value, $document) {
        return (!$document && 0.0 === (float) $value) ? '—' : number_format_i18n((float) $value, 2);
    }

    /** Detail-screen money. Print and PDF always pass $document = true and keep money(). */
    private static function readonly_money($value, $currency, $document) {
        return (!$document) ? self::display_money($value, $currency) : self::money($value, $currency);
    }

    private static function render_catalogue_crm($search, $items, $total_items, $catalogue_page, $per_page, $catalogue_sort, $catalogue_order, $catalogue_sort_options, $categories, $units, $edit_item, $edit_item_id, $form_item) {
        $has_search = '' !== $search;
        $close_url = self::view_url(array('workspace' => 'catalogue', 'edit_item' => false, 'add_item' => false, 'catalogue_search' => $search ?: false));
        $base_query = array('workspace' => 'catalogue', 'catalogue_search' => $search ?: false);
        ?><div class="wrap idlm-wrap idlm-quotes-wrap idlm-catalogue-view"><?php self::render_notice(); ?><header class="idlm-header"><div><h1>Item catalogue</h1></div><div class="idlm-header-actions"><details class="idlm-quote-menu"><summary class="idlm-btn idlm-btn-secondary">Manage</summary><div class="idlm-quote-menu__panel"><a href="<?php echo esc_url(self::view_url(array_merge($base_query, array('manage_categories' => 1)))); ?>">Item categories</a><a href="<?php echo esc_url(self::view_url(array_merge($base_query, array('manage_units' => 1)))); ?>">Units</a><a href="<?php echo esc_url(self::view_url(array_merge($base_query, array('import_catalogue' => 1)))); ?>">Import CSV</a><a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=idlm_export_catalogue_csv'), 'idlm_export_catalogue_csv')); ?>">Export CSV</a></div></details><a class="idlm-btn idlm-btn-primary" href="<?php echo esc_url(self::view_url(array_merge($base_query, array('add_item' => 1)))); ?>">Add item</a></div></header><?php self::render_workspace_nav('catalogue'); ?>
        <div class="idlm-quote-layout"><section class="idlm-quote-panel idlm-catalogue-panel"><h2 class="screen-reader-text">Catalogue items</h2><div class="idlm-quote-filter idlm-catalogue-toolbar"><form method="get" class="idlm-catalogue-search-form" data-live-list="catalogue" role="search" aria-label="Search catalogue"><?php if (is_admin()): ?><input type="hidden" name="page" value="idlm_quotations"><?php elseif (isset($_GET['idlm_view'])): // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><input type="hidden" name="idlm_view" value="<?php echo esc_attr(sanitize_key(wp_unslash($_GET['idlm_view']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"><?php endif; ?><input type="hidden" name="workspace" value="catalogue"><label><span class="screen-reader-text">Search catalogue</span><input type="search" name="catalogue_search" value="<?php echo esc_attr($search); ?>" placeholder="Search name, code or category" autocomplete="off"></label><button class="button">Search</button></form><form method="get" class="idlm-catalogue-sort-form" aria-label="Sort catalogue items"><?php if (is_admin()): ?><input type="hidden" name="page" value="idlm_quotations"><?php elseif (isset($_GET['idlm_view'])): // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?><input type="hidden" name="idlm_view" value="<?php echo esc_attr(sanitize_key(wp_unslash($_GET['idlm_view']))); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"><?php endif; ?><input type="hidden" name="workspace" value="catalogue"><input type="hidden" name="catalogue_search" value="<?php echo esc_attr($search); ?>"><label for="idlm-catalogue-sort">Sort by</label><select id="idlm-catalogue-sort" name="catalogue_sort"><?php foreach ($catalogue_sort_options as $value => $label): ?><option value="<?php echo esc_attr($value); ?>" <?php selected($catalogue_sort, $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select><label for="idlm-catalogue-order" class="screen-reader-text">Sort direction</label><select id="idlm-catalogue-order" name="catalogue_order"><option value="asc" <?php selected($catalogue_order, 'asc'); ?>>Ascending</option><option value="desc" <?php selected($catalogue_order, 'desc'); ?>>Descending</option></select><button class="button">Sort</button></form><div class="idlm-bulk-action-bar"><span data-selection-count>0 selected</span><label class="screen-reader-text" for="idlm-catalogue-bulk-action">Bulk action</label><select id="idlm-catalogue-bulk-action" name="bulk_action" form="idlm-catalogue-bulk-form" required><option value="">Bulk actions</option><option value="archive">Archive</option><option value="delete">Delete permanently</option></select><button class="button" form="idlm-catalogue-bulk-form">Apply</button></div></div><form id="idlm-catalogue-bulk-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idlm-bulk-action-form" data-bulk-form data-noun="catalogue item" data-delete-message="Permanently delete the selected catalogue items?" data-delete-note="Existing quotation revisions keep their copied values." data-archive-note="Archived items stop appearing in quotation search. Existing quotation revisions keep their copied values."><input type="hidden" name="action" value="idlm_bulk_catalogue_items"><?php wp_nonce_field('idlm_bulk_catalogue_items'); ?><div class="idlm-table-wrapper" tabindex="0" role="region" aria-label="Catalogue items"><table class="idlm-table idlm-catalogue-table"><thead><tr><th class="idlm-checkbox-column" scope="col"><input type="checkbox" data-select-all aria-label="Select all catalogue items"></th><th scope="col">Code</th><th scope="col">Category</th><th scope="col">Item</th><th scope="col">Unit</th><th scope="col">Wood</th><th scope="col" class="idlm-quote-num">Rate</th><th scope="col">Status</th><th scope="col"><span class="screen-reader-text">Actions</span></th></tr></thead><tbody data-live-list-rows="catalogue"><?php if (!$items): ?><tr class="idlm-quote-empty"><td colspan="9"><div class="idlm-state is-empty"><?php if ($has_search): ?><strong>No catalogue items match this search</strong><span>Try a different name, code or category.</span><?php else: ?><strong>No catalogue items yet</strong><span>Add items here so they can be searched and copied into quotations.</span><div class="idlm-state__actions"><a class="idlm-btn idlm-btn-primary" href="<?php echo esc_url(self::view_url(array_merge($base_query, array('add_item' => 1)))); ?>">Add item</a></div><?php endif; ?></div></td></tr><?php endif; ?><?php foreach ($items as $item): ?><tr class="<?php echo $edit_item_id === absint($item->id) ? 'is-editing' : ''; ?><?php echo 'active' === $item->status ? '' : ' is-archived'; ?>"><td class="idlm-checkbox-column"><input type="checkbox" name="item_ids[]" value="<?php echo esc_attr($item->id); ?>" data-row-select aria-label="Select <?php echo esc_attr($item->item_name); ?>"></td><td data-label="Code"><?php echo esc_html($item->item_code); ?></td><td data-label="Category"><?php echo esc_html($item->category); ?></td><td data-label="Item" class="idlm-catalogue-item-cell"><strong><?php echo esc_html($item->item_name); ?></strong><small title="<?php echo esc_attr($item->description); ?>"><?php echo esc_html($item->description); ?></small></td><td data-label="Unit"><?php echo esc_html($item->unit); ?></td><td data-label="Wood"><?php echo !empty($item->is_wood) ? 'Yes' : 'No'; ?></td><td data-label="Rate" class="idlm-quote-num"><?php echo esc_html(self::display_money($item->default_rate)); ?></td><td data-label="Status"><?php if ('active' === $item->status): ?>Active<?php else: ?><span class="idlm-badge is-neutral">Archived</span><?php endif; ?></td><td class="idlm-catalogue-row-actions"><div class="idlm-catalogue-actions"><a class="button-link" href="<?php echo esc_url(self::view_url(array_merge($base_query, array('edit_item' => $item->id)))); ?>" aria-label="<?php echo esc_attr('Edit ' . $item->item_name); ?>">Edit</a><?php if ('active' === $item->status): ?><button class="button-link-delete" name="bulk_action" value="archive" data-single-submit aria-label="<?php echo esc_attr('Archive ' . $item->item_name); ?>">Archive</button><?php endif; ?><button class="button-link-delete" name="bulk_action" value="delete" data-single-submit aria-label="<?php echo esc_attr('Delete ' . $item->item_name); ?>">Delete</button></div></td></tr><?php endforeach; ?></tbody></table></div></form></section>
        <aside><section class="idlm-quote-panel idlm-catalogue-editor" aria-labelledby="idlm-catalogue-editor-title"><div class="idlm-panel-heading"><h2 id="idlm-catalogue-editor-title"><?php echo $edit_item ? 'Edit catalogue item' : 'Add catalogue item'; ?></h2></div><?php if ($edit_item): ?><p class="description">Changes apply to future selections only. Existing quotation revisions stay as they are.</p><?php endif; ?><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="idlm_save_catalogue_item"><input type="hidden" name="item_id" value="<?php echo esc_attr($form_item->id); ?>"><input type="hidden" name="idlm_return_url" value="<?php echo esc_attr(self::view_url(array('workspace' => 'catalogue', 'edit_item' => false))); ?>"><?php wp_nonce_field('idlm_save_catalogue_item'); ?><div class="idlm-catalogue-form"><label>Item code<input <?php echo $edit_item ? 'required ' : ''; ?>name="item_code" value="<?php echo esc_attr($form_item->item_code); ?>" placeholder="<?php echo $edit_item ? '' : 'Generated automatically if blank'; ?>"></label><label>Category<select required name="category"><option value="">Select category</option><?php foreach ($categories as $category): ?><option value="<?php echo esc_attr($category); ?>" <?php selected($form_item->category, $category); ?>><?php echo esc_html($category); ?></option><?php endforeach; ?></select></label><label>Item name<input required name="item_name" value="<?php echo esc_attr($form_item->item_name); ?>"></label><label>Description<textarea name="description"><?php echo esc_textarea($form_item->description); ?></textarea></label><label>Unit<select required name="unit"><option value="">Select unit</option><?php foreach ($units as $unit): ?><option value="<?php echo esc_attr($unit); ?>" <?php selected($form_item->unit, $unit); ?>><?php echo esc_html($unit); ?></option><?php endforeach; ?></select></label><label>Wood<select name="is_wood"><option value="0" <?php selected((int) $form_item->is_wood, 0); ?>>No</option><option value="1" <?php selected((int) $form_item->is_wood, 1); ?>>Yes</option></select></label><label>Default rate<input type="number" min="0" step="0.01" name="default_rate" value="<?php echo esc_attr($form_item->default_rate); ?>"></label><div class="idlm-dialog__footer idlm-catalogue-form-footer"><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url($close_url); ?>">Cancel</a><button class="idlm-btn idlm-btn-primary"><?php echo $edit_item ? 'Save changes' : 'Add item'; ?></button></div></div></form></section><section class="idlm-quote-panel idlm-catalogue-import" data-catalogue-import><h2 id="idlm-catalogue-import-title">Import catalogue CSV</h2><p class="description">Choose a CSV to check every row before anything is imported. Maximum 2,000 rows or 5 MB. Rates may use thousands separators (1,790.00 or 1,00,000.00).</p><p class="description">Columns: <code>item_code</code>, <code>category</code>, <code>item_name</code>, <code>description</code>, <code>unit</code>, <code>is_wood</code>, <code>default_rate</code>, <code>status</code>. A blank item code creates a new item, so importing the same blank-code rows again creates more items.</p><form method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" data-catalogue-import-form><input type="hidden" name="action" value="idlm_import_catalogue_csv"><input type="hidden" name="idlm_return_url" value="<?php echo esc_attr(self::return_url()); ?>"><?php wp_nonce_field('idlm_import_catalogue_csv'); ?><label>CSV file<input required type="file" name="catalogue_csv" accept=".csv,text/csv"></label><div data-catalogue-import-status role="status" aria-live="polite"></div><div data-catalogue-import-preview></div><div class="idlm-catalogue-import-actions"><button type="submit" class="idlm-btn idlm-btn-secondary" data-catalogue-preview>Check file</button><button type="button" class="idlm-btn idlm-btn-primary" data-catalogue-confirm hidden disabled>Import valid items</button><button type="button" class="idlm-btn idlm-btn-primary" data-catalogue-import-done hidden>Done</button></div></form></section></aside></div><?php self::render_pagination_crm('catalogue', 'items', 'catalogue_page', $catalogue_page, $per_page, $total_items, self::view_url(array('workspace' => 'catalogue', 'catalogue_search' => $search ?: false, 'catalogue_sort' => $catalogue_sort, 'catalogue_order' => $catalogue_order))); ?><p class="screen-reader-text" aria-live="polite" data-live-list-status="catalogue"></p></div><?php
    }

    private static function render_unit_manager($units) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if (empty($_GET['manage_units'])) { return; }
        ?>
        <section class="idlm-wrap idlm-quotes-wrap idlm-quote-panel idlm-category-manager idlm-unit-manager is-open" role="dialog" aria-modal="true" aria-labelledby="idlm-unit-manager-title">
            <header><div><h2 id="idlm-unit-manager-title"><?php echo esc_html('Catalogue units'); ?></h2><p>Create reusable measurement units for consistent catalogue items.</p></div><a class="button idlm-catalogue-editor-close" aria-label="Close unit manager" href="<?php echo esc_url(self::view_url(array('workspace' => 'catalogue', 'manage_units' => false))); ?>">&times;</a></header>
            <?php self::render_notice(); ?>
            <div class="idlm-category-manager-body">
                <section><h3>Available units</h3><div class="idlm-category-manager-list idlm-unit-manager-list"><?php if (!$units): ?><p>No units created yet.</p><?php endif; ?><?php foreach ($units as $unit): ?><div><strong><?php echo esc_html($unit); ?></strong><span>Active</span></div><?php endforeach; ?></div></section>
                <section><h3>Create unit</h3><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="idlm_save_catalogue_unit"><input type="hidden" name="idlm_return_url" value="<?php echo esc_attr(self::view_url(array('workspace' => 'catalogue', 'manage_units' => 1))); ?>"><?php wp_nonce_field('idlm_save_catalogue_unit'); ?><label>Unit name<input required maxlength="30" name="unit_name" placeholder="e.g. Sq Ft"></label><button class="button button-primary"><?php echo esc_html('Create unit'); ?></button></form></section>
            </div>
        </section>
        <?php
    }

    private static function render_category_manager($categories) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $is_open = !empty($_GET['manage_categories']);
        if (!$is_open) { return; }
        ?>
        <section class="idlm-wrap idlm-quotes-wrap idlm-quote-panel idlm-category-manager is-open" role="dialog" aria-modal="true" aria-labelledby="idlm-category-manager-title">
            <header><div><h2 id="idlm-category-manager-title"><?php echo esc_html('Item categories'); ?></h2><p>Create reusable categories for consistent catalogue classification.</p></div><a class="button idlm-catalogue-editor-close" aria-label="Close category manager" href="<?php echo esc_url(self::view_url(array('workspace' => 'catalogue', 'manage_categories' => false))); ?>">&times;</a></header>
            <?php self::render_notice(); ?>
            <div class="idlm-category-manager-body">
                <section><h3>Available categories</h3><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="idlm-bulk-action-form" data-bulk-form data-noun="category" data-delete-note="Categories assigned to catalogue items are skipped." data-archive-note="Archived categories stop appearing when adding or editing catalogue items." data-delete-message="Permanently delete the selected categories? Categories assigned to catalogue items will be skipped."><input type="hidden" name="action" value="idlm_catalogue_category_action"><?php wp_nonce_field('idlm_catalogue_category_action'); ?><div class="idlm-bulk-action-bar"><label><input type="checkbox" data-select-all> Select all</label><select name="category_action" required><option value="">Bulk actions</option><option value="archive">Archive</option><option value="delete">Delete permanently</option></select><button class="button">Apply</button><span data-selection-count>0 selected</span></div><div class="idlm-category-manager-list"><?php if (!$categories): ?><p>No categories created yet.</p><?php endif; ?><?php foreach ($categories as $category): ?><div><label><input type="checkbox" name="category_ids[]" value="<?php echo esc_attr($category->id); ?>" data-row-select> <strong><?php echo esc_html($category->category_name); ?></strong></label><span><?php echo esc_html('active' === $category->status ? 'Active' : 'Archived'); ?></span><div class="idlm-catalogue-actions"><?php if ('active' === $category->status): ?><button class="button-link-delete" name="category_action" value="archive" data-single-submit>Archive</button><?php endif; ?><button class="button-link-delete" name="category_action" value="delete" data-single-submit>Delete</button></div></div><?php endforeach; ?></div></form></section>
                <section><h3>Create category</h3><form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden" name="action" value="idlm_save_catalogue_category"><input type="hidden" name="idlm_return_url" value="<?php echo esc_attr(self::view_url(array('workspace' => 'catalogue', 'manage_categories' => 1))); ?>"><?php wp_nonce_field('idlm_save_catalogue_category'); ?><label>Category name<input required maxlength="100" name="category_name" placeholder="e.g. Modular Kitchen"></label><button class="button button-primary"><?php echo esc_html('Create category'); ?></button></form></section>
            </div>
        </section>
        <?php
    }

    private static function render_notice() {
        // Values are generated by this module's redirect helper and displayed read-only.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (!empty($_GET['idlm_error'])) {
            echo '<div class="notice notice-error"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['idlm_error']))) . '</p></div>';
        }
        if (!empty($_GET['idlm_notice'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['idlm_notice']))) . '</p></div>';
        }
        // phpcs:enable
    }

    /**
     * Normalize an optional quotation-list date filter.
     *
     * Keep this renderer-side because list filters are read-only UI input, while
     * the controller validates dates used by quotation mutations separately.
     */
    private static function clean_date($value) {
        // The shared Y-m-d check; list filters keep null for an absent or invalid date.
        return IDLM_Date_Ranges::clean_date(sanitize_text_field($value)) ?: null;
    }

    private static function render_list() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $search = sanitize_text_field(wp_unslash($_GET['quote_search'] ?? ''));
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $created_by = absint($_GET['created_by'] ?? 0);
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0100: render_list(): protected catalogue/list renderers use capability checks and GET values only for bounded paging, fixed sort directions and read-only filters; dates delegate to clean_date sanitization; no GET performs mutations.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $date_from = self::clean_date(wp_unslash($_GET['date_from'] ?? ''));
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0101: render_list(): protected catalogue/list renderers use capability checks and GET values only for bounded paging, fixed sort directions and read-only filters; dates delegate to clean_date sanitization; no GET performs mutations.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $date_to = self::clean_date(wp_unslash($_GET['date_to'] ?? ''));
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $lead_id = absint($_GET['lead_id'] ?? 0);
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only list filter; normalized to the shared period list.
        list(self::$list_range, $date_from, $date_to) = IDLM_Date_Ranges::date_bounds(wp_unslash($_GET['date_range'] ?? ''), $date_from, $date_to);
        $filtered_lead_id = $lead_id && self::can_view_lead($lead_id) ? $lead_id : 0;
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- REL007-BOUNDARY-0102: render_list(): protected catalogue/list renderers use capability checks and GET values only for bounded paging, fixed sort directions and read-only filters; dates delegate to clean_date sanitization; no GET performs mutations.
        $quote_page = max(1, absint($_GET['quote_page'] ?? 1));
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        $per_page = 50;
        $filters = array(
            'search' => $search,
            'created_by' => $created_by,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'lead_id' => $filtered_lead_id,
        );
        $total_quotes = IDLM_Quotation_Repository::count_quotations($filters);
        $quotes = IDLM_Quotation_Repository::list_quotations($filters, $quote_page, $per_page);
        self::render_list_crm($quotes, $total_quotes, $quote_page, $per_page, $search, $created_by, $date_from, $date_to, $lead_id);
    }

    private static function render_editor($quote_id, $lead_id) {
        $quote_settings = self::settings();
        $bundle = $quote_id ? self::get_quote_bundle($quote_id) : null;
        $has_working_copy = false;
        $working_copy_saved_at = '';
        if ($bundle) {
            if (!IDLM_Quotation_Policy::can_mutate_lead($bundle->lead_id) || !self::can('idlm_edit_quote_drafts') || !self::can('idlm_create_quote_revisions') || !IDLM_Quotation_Policy::is_editable_draft($bundle, $bundle->revision)) {
                self::redirect(array('action' => 'view', 'quote_id' => $quote_id, 'idlm_error' => 'This revision is read-only.'));
            }
            $lead_id = absint($bundle->lead_id);
            $revision = $bundle->revision;
            $revision->revision_name = self::revision_label($revision);
            $items = $bundle->items;
            $sections = $bundle->sections;
            $working_copy = get_transient(self::autosave_key($revision->id));
            if (is_array($working_copy) && (int) ($working_copy['generation'] ?? 0) === IDLM_Recovery_Guard::generation() && !empty($working_copy['sections']) && !empty($working_copy['items'])) {
                $has_working_copy = true;
                $working_copy_saved_at = !empty($working_copy['saved_at']) ? wp_date('g:i A', absint($working_copy['saved_at'])) : '';
                foreach ((array) ($working_copy['revision'] ?? array()) as $field => $value) {
                    $revision->{$field} = $value;
                }
                $sections = array();
                $section_ids = array();
                foreach ((array) $working_copy['sections'] as $section_key => $section_data) {
                    $temporary_id = count($sections) + 1;
                    $section_ids[$section_key] = $temporary_id;
                    $sections[] = (object) array_merge($section_data, array('id' => $temporary_id));
                }
                $items = array();
                foreach ((array) $working_copy['items'] as $item_data) {
                    $section_key = $item_data['section_key'] ?? '';
                    unset($item_data['section_key']);
                    $item_data['section_id'] = $section_ids[$section_key] ?? reset($section_ids);
                    $items[] = (object) $item_data;
                }
            }
        } else {
            if (!self::can('idlm_create_quotes') || ($lead_id && !IDLM_Quotation_Policy::can_mutate_lead($lead_id))) {
                wp_die('You do not have permission to create quotations.', 403);
            }
            $revision = (object) $quote_settings;
            $items = array((object) array('category' => '', 'room_area' => '', 'item_code' => '', 'description' => '', 'specification' => '', 'length' => 0, 'height' => 0, 'quantity' => 1, 'unit' => 'Nos', 'unit_rate' => 0, 'is_optional' => 0));
            $sections = array((object) array('id' => 0, 'section_name' => 'General', 'section_description' => '', 'sort_order' => 0));
        }
        $selected_lead = null;
        if (!$selected_lead && $lead_id && self::can_view_lead($lead_id)) {
            $selected_lead = IDLM_Quotation_Lead_Read_Model::get_selected_lead($lead_id);
        }
        if (!$bundle && $lead_id) {
            if ($selected_lead) {
                $revision->customer_name = trim($selected_lead->first_name . ' ' . $selected_lead->last_name);
                $revision->customer_phone = $selected_lead->phone;
                $revision->customer_email = $selected_lead->email;
                $revision->project_name = $selected_lead->project_name;
                $revision->property_location = $selected_lead->property_location;
            }
        }
        $sales_rep_name = $selected_lead && $selected_lead->assigned_rep_name ? $selected_lead->assigned_rep_name : 'Unassigned';
        $sales_rep = $selected_lead && $selected_lead->assigned_rep_id ? get_userdata(absint($selected_lead->assigned_rep_id)) : false;
        $created_by_user = $bundle ? get_userdata(absint($bundle->created_by)) : wp_get_current_user();
        $white_label = IDLM_Settings::get_white_label();
        $company_name = trim((string) ($white_label['brand_name'] ?? '')) ?: get_bloginfo('name');
        $soft_close_percent = (float) $quote_settings['soft_close_percent'];
        $soft_close_enabled = !empty($revision->soft_close_enabled);
        $currency_code = strtoupper((string) ($revision->currency_code ?? $quote_settings['currency_code']));
        $currency_symbol = self::currency_symbol($currency_code);
        ?>
        <div class="wrap idlm-wrap idlm-quotes-wrap"><header class="idlm-header"><div><h1><?php echo $bundle ? esc_html('Edit ' . $bundle->quote_number . ' · ' . $revision->revision_name) : esc_html('Create quotation'); ?></h1></div></header><?php self::render_workspace_nav('quotes'); self::render_notice(); ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="idlm-quote-form">
            <input type="hidden" name="currency_code" value="<?php echo esc_attr($currency_code); ?>">
            <input type="hidden" name="action" value="idlm_save_quote"><input type="hidden" name="quote_id" value="<?php echo esc_attr($quote_id); ?>"><input type="hidden" name="revision_id" value="<?php echo esc_attr($revision->id ?? 0); ?>"><input type="hidden" name="save_request_key" value="<?php echo esc_attr(wp_generate_uuid4()); ?>"><input type="hidden" name="idlm_return_url" value="<?php echo esc_attr(self::return_url()); ?>"><?php if ($bundle): ?><input type="hidden" name="revision_reason" value=""><?php endif; ?><?php wp_nonce_field('idlm_save_quote'); ?>
            <input type="hidden" id="idlm-quote-created-by" value="<?php echo esc_attr($created_by_user ? $created_by_user->display_name : 'Migrated record'); ?>">
            <section class="idlm-quote-panel idlm-quote-identity"><div class="idlm-quote-identity-side"><h2>Customer</h2><label>Search lead<input type="search" id="idlm-lead-search" value="<?php echo esc_attr($selected_lead ? trim($selected_lead->first_name . ' ' . $selected_lead->last_name) . ($selected_lead->project_name ? ' — ' . $selected_lead->project_name : '') : ''); ?>" placeholder="Search name, phone, email or property" <?php disabled((bool) $bundle); ?>></label><input type="hidden" id="idlm-quote-lead" name="lead_id" value="<?php echo esc_attr($lead_id); ?>" required><label>Customer name<input name="customer_name" value="<?php echo esc_attr($revision->customer_name ?? ''); ?>"></label><div class="idlm-identity-inline"><label>Email<input type="email" name="customer_email" value="<?php echo esc_attr($revision->customer_email ?? ''); ?>"></label><label>Phone number<input name="customer_phone" value="<?php echo esc_attr($revision->customer_phone ?? ''); ?>"></label></div><div class="idlm-identity-inline"><label>Property name<input name="project_name" value="<?php echo esc_attr($revision->project_name ?? ''); ?>"></label><label>Location<input name="property_location" value="<?php echo esc_attr($revision->property_location ?? ''); ?>"></label></div></div>
            <div class="idlm-quote-identity-side idlm-quote-meta"><h2>Quotation</h2><dl><div><dt><?php echo esc_html('Quote number'); ?></dt><dd><?php echo esc_html($bundle ? $bundle->quote_number . ' · ' . $revision->revision_name : 'Assigned when saved'); ?></dd></div><div><dt><?php echo esc_html('Company'); ?></dt><dd><?php echo esc_html($company_name); ?></dd></div><div><dt><?php echo esc_html('Branch or studio'); ?></dt><dd id="idlm-quote-studio"><?php echo esc_html($selected_lead && $selected_lead->preferred_studio ? $selected_lead->preferred_studio : '—'); ?></dd></div><div><dt><?php echo esc_html('Sales rep'); ?></dt><dd id="idlm-quote-sales-rep"><?php echo esc_html($sales_rep ? $sales_rep->display_name : 'Unassigned'); ?></dd></div><div><dt>Created</dt><dd><?php echo esc_html($bundle ? self::display_date($bundle->created_at, 'M j, Y, g:i A') : 'When saved'); ?></dd></div><?php if ($bundle): ?><div><dt>Last modified</dt><dd><?php echo esc_html(self::display_date($bundle->updated_at, 'M j, Y, g:i A')); ?></dd></div><?php endif; ?></dl></div></section>
            <section class="idlm-quote-panel"><div class="idlm-panel-heading"><h2>Sections and line items</h2></div><p class="description">Search the item catalogue or enter a custom item. Width × height automatically calculates the billable area; use Area / Qty when no dimensions apply.</p><div id="idlm-quote-sections"><?php foreach ($sections as $section_index => $section) { self::render_section($section, $section_index, $items, $currency_code); } ?></div><div class="idlm-add-section-row"><button type="button" class="button idlm-add-section-item"><?php echo esc_html('Add line'); ?></button><button type="button" class="button" id="idlm-add-section"><?php echo esc_html('Add section'); ?></button><button type="button" class="button" id="idlm-open-bulk-items" aria-haspopup="dialog" aria-controls="idlm-bulk-items-modal"><?php echo esc_html('Add from catalogue'); ?></button></div><div class="idlm-subtotals-layout"><section class="idlm-section-summary"><h3>Section-wise summary</h3><div id="idlm-section-totals"></div></section><section class="idlm-wood-summary"><h3><?php echo esc_html('Wood and others summary'); ?></h3><div id="idlm-wood-totals"></div></section><?php self::render_totals_panel($revision, $soft_close_percent, $currency_symbol, $soft_close_enabled); ?></div></section>
            <section class="idlm-quote-panel idlm-additional-notes"><h2><?php echo esc_html('Internal notes'); ?></h2><p class="description">Visible only inside the CRM. Internal notes are never included in customer print or PDF output.</p><textarea name="internal_notes" rows="5" aria-label="Internal notes" placeholder="Add an internal note for this quotation..."><?php echo esc_textarea($revision->internal_notes ?? ''); ?></textarea></section>
            <div class="idlm-bulk-items-modal" id="idlm-bulk-items-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-bulk-items-title" hidden><div class="idlm-bulk-items-dialog"><header><div><h2 id="idlm-bulk-items-title">Add catalogue items</h2><p><?php echo esc_html('Select items to add to the last section.'); ?></p></div><button type="button" class="idlm-bulk-items-close" aria-label="Close bulk items">&times;</button></header><div class="idlm-bulk-items-columns"><section><label for="idlm-bulk-items-search">Search catalogue</label><input type="search" id="idlm-bulk-items-search" placeholder="Search item, code or category"><div class="idlm-bulk-items-list" id="idlm-bulk-items-available" aria-live="polite"><p class="idlm-bulk-items-empty">Loading catalogue…</p></div></section><section><h3>Selected <span id="idlm-bulk-items-count">0</span></h3><div class="idlm-bulk-items-list" id="idlm-bulk-items-selected" aria-live="polite"><p class="idlm-bulk-items-empty">No items selected.</p></div></section></div><footer><button type="button" class="button idlm-bulk-items-cancel">Cancel</button><button type="button" class="button button-primary" id="idlm-add-bulk-items" disabled><?php echo esc_html('Add selected'); ?></button></footer></div></div>
            <?php if ($bundle): ?><div class="idlm-modal idlm-dialog idlm-autosave-cancel-modal" data-autosave-cancel-modal data-idlm-dialog data-idlm-use-hidden="true" data-idlm-initial-focus="[data-autosave-save]" role="dialog" aria-modal="true" aria-labelledby="idlm-autosave-cancel-title" aria-describedby="idlm-autosave-cancel-description" aria-hidden="true" hidden><div class="idlm-modal-content idlm-dialog__panel"><header class="idlm-dialog__header"><div><h2 class="idlm-dialog__title" id="idlm-autosave-cancel-title">Save or discard your changes?</h2><p class="idlm-dialog__description" id="idlm-autosave-cancel-description">Your autosaved edits are a temporary working copy and have not changed the saved revision.</p></div><button type="button" class="idlm-dialog__close" data-autosave-continue data-idlm-dialog-close aria-label="Close unsaved quote changes dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header><div class="idlm-dialog__body"><p>Choose whether to continue editing, discard the working copy, or save it as a revision.</p></div><footer class="idlm-dialog__footer"><button type="button" class="button button-link-delete idlm-quote-dialog-discard" data-autosave-discard>Discard changes</button><button type="button" class="button" data-autosave-continue data-idlm-dialog-close>Continue editing</button><button type="button" class="button button-primary" data-autosave-save>Save as revision</button></footer></div></div><?php endif; ?>
            <div class="idlm-quote-actions"><?php if ($bundle && self::can('idlm_edit_quote_drafts') && self::can('idlm_create_quote_revisions')): ?><span class="idlm-autosave-status" data-autosave-status data-has-working-copy="<?php echo $has_working_copy ? '1' : '0'; ?>" role="status" aria-live="polite"><?php echo esc_html($has_working_copy ? 'Working copy restored' . ($working_copy_saved_at ? ' · ' . $working_copy_saved_at : '') : 'Autosave on'); ?></span><?php endif; ?><span class="idlm-quote-running-total"><span>Grand total</span><strong data-quote-total-mirror aria-live="off"><?php echo esc_html($currency_symbol); ?>0.00</strong></span><?php if ($bundle): ?><button type="button" class="idlm-btn idlm-btn-secondary" data-quote-cancel data-cancel-url="<?php echo esc_url(self::view_url()); ?>">Cancel</button><?php else: ?><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url(self::view_url()); ?>">Cancel</a><?php endif; ?><button type="submit" class="idlm-btn idlm-btn-primary"><?php echo esc_html($bundle ? 'Save revision' : 'Save quotation'); ?></button></div>
        </form></div><?php
    }

    /** Live totals. Design Reps see discounts read-only; the server also rejects changes. */
    private static function render_totals_panel($revision, $soft_close_percent, $currency_symbol, $soft_close_enabled, $lock_discounts = false) {
        ?><section class="idlm-quote-panel idlm-quote-totals"><h2>Totals</h2><dl id="idlm-live-totals" data-soft-close-percent="<?php echo esc_attr($soft_close_percent); ?>" data-currency-symbol="<?php echo esc_attr($currency_symbol); ?>"><div><dt>Subtotal</dt><dd data-total="subtotal"><?php echo esc_html($currency_symbol); ?>0.00</dd></div><div><dt><?php echo esc_html('Wooden subtotal'); ?></dt><dd data-total="wooden-subtotal"><?php echo esc_html($currency_symbol); ?>0.00</dd></div><div class="idlm-editable-total"><dt><?php echo esc_html('Wooden discount'); ?></dt><dd><span><input aria-label="Wooden discount percentage" type="number" min="0" max="100" step="0.01" name="wooden_discount_percent" value="<?php echo esc_attr($revision->wooden_discount_percent ?? 0); ?>"<?php echo $lock_discounts ? ' readonly' : ''; ?>>%</span><output data-total="wooden-discount"><?php echo esc_html($currency_symbol); ?>0.00</output></dd></div><div><dt>Subtotal after wooden discount</dt><dd data-total="after-wooden-discount"><?php echo esc_html($currency_symbol); ?>0.00</dd></div><div><dt><?php echo esc_html('Other subtotal'); ?></dt><dd data-total="other-subtotal"><?php echo esc_html($currency_symbol); ?>0.00</dd></div><div class="idlm-editable-total"><dt><?php echo esc_html('Soft close addition'); ?></dt><dd><label><input type="checkbox" name="soft_close_enabled" value="1" <?php checked($soft_close_enabled); ?>> Enable (<?php echo esc_html(number_format_i18n($soft_close_percent, 2)); ?>%)</label><output data-total="soft-close"><?php echo esc_html($currency_symbol); ?>0.00</output></dd></div><div><dt>Total</dt><dd data-total="total"><?php echo esc_html($currency_symbol); ?>0.00</dd></div><?php $has_additional_discount = (float) ($revision->additional_discount_amount ?? 0) > 0; ?><div class="idlm-editable-total" data-additional-discount-row <?php echo $has_additional_discount ? '' : 'hidden'; ?>><dt><?php echo esc_html('Additional discount on total'); ?></dt><dd><span><?php echo esc_html($currency_symbol); ?><input aria-label="Additional discount amount" type="number" min="0" step="0.01" name="additional_discount_amount" value="<?php echo esc_attr($revision->additional_discount_amount ?? 0); ?>"<?php echo $lock_discounts ? ' readonly' : ''; ?>></span></dd></div><div data-after-additional-discount-row <?php echo $has_additional_discount ? '' : 'hidden'; ?>><dt>Total after additional discount</dt><dd data-total="after-additional-discount"><?php echo esc_html($currency_symbol); ?>0.00</dd></div><div><dt>GST (18%)</dt><dd data-total="tax"><?php echo esc_html($currency_symbol); ?>0.00</dd></div><div class="idlm-grand-total"><dt><?php echo esc_html('Grand total'); ?></dt><dd data-total="grand"><?php echo esc_html($currency_symbol); ?>0.00</dd></div></dl><?php if (!$lock_discounts): ?><button type="button" class="button idlm-add-additional-discount" data-add-additional-discount <?php echo $has_additional_discount ? 'hidden' : ''; ?>>Add additional discount</button><?php endif; ?></section><?php
    }

    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    /** Design quotation editor: reuses the line-item table and totals; customer details are read-only. */
    public static function render_design_editor($row, $source, $bundle, $can_change_pricing) {
        $working_copy = get_transient(IDLM_Design_Revisions::autosave_key($source->id));
        $has_working_copy = is_array($working_copy) && !empty($working_copy['items']) && !empty($working_copy['sections']);
        $revision = $source; $items = $bundle->items; $sections = $bundle->sections;
        if ($has_working_copy) {
            foreach ((array) $working_copy['revision'] as $field => $value) { $revision->{$field} = $value; }
            $sections = array(); $section_ids = array();
            foreach ((array) $working_copy['sections'] as $section_key => $section_data) { $section_ids[$section_key] = count($sections) + 1; $sections[] = (object) array_merge($section_data, array('id' => $section_ids[$section_key])); }
            $items = array();
            foreach ((array) $working_copy['items'] as $item_data) { $section_key = $item_data['section_key'] ?? ''; unset($item_data['section_key']); $item_data['section_id'] = $section_ids[$section_key] ?? reset($section_ids); $items[] = (object) $item_data; }
        }
        $currency_code = strtoupper((string) $revision->currency_code); $currency_symbol = self::currency_symbol($currency_code);
        $detail_url = add_query_arg(array('idlm_view' => IDLM_Design_Handoff_Controller::VIEW, 'handoff' => $row->id), IDLM_Admin_Shortcodes::workspace_url());
        $next_number = IDLM_Quotation_Repository::next_revision_number($source->quote_id);
        ?>
        <div class="idlm-quotes-wrap idlm-design-quote"><header class="idlm-header"><div><h1><?php echo esc_html('Edit ' . $bundle->quote_number); ?></h1></div><div class="idlm-header-actions"><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url($detail_url); ?>">Back to handoff</a></div></header><?php self::render_notice(); ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="idlm-quote-form" data-autosave-action="idlm_design_quote_autosave" data-discard-action="idlm_design_quote_discard" data-autosave-nonce="<?php echo esc_attr(wp_create_nonce('idlm_design_quote_autosave')); ?>">
            <input type="hidden" name="currency_code" value="<?php echo esc_attr($currency_code); ?>"><input type="hidden" name="action" value="idlm_design_quote_save"><input type="hidden" name="handoff_id" value="<?php echo esc_attr($row->id); ?>"><input type="hidden" name="quote_id" value="<?php echo esc_attr($source->quote_id); ?>"><input type="hidden" name="revision_id" value="<?php echo esc_attr($source->id); ?>"><input type="hidden" name="save_request_key" value="<?php echo esc_attr(wp_generate_uuid4()); ?>"><input type="hidden" name="return_url" value="<?php echo esc_attr(remove_query_arg(array('design_quote', 'design_notice', 'design_error', 'handoff'), wp_unslash($_SERVER['REQUEST_URI'] ?? ''))); ?>"><input type="hidden" name="revision_reason" value=""><input type="hidden" id="idlm-lead-search" value=""><input type="hidden" id="idlm-quote-lead" value="<?php echo esc_attr($row->lead_id); ?>"><input type="hidden" id="idlm-quote-created-by" value=""><?php wp_nonce_field('idlm_design_quote_save'); ?>
            <section class="idlm-quote-panel idlm-quote-identity"><div class="idlm-quote-identity-side idlm-quote-meta"><h2>Customer</h2><dl><div><dt>Customer</dt><dd><?php echo esc_html($revision->customer_name ?: '—'); ?></dd></div><div><dt>Property</dt><dd><?php echo esc_html($revision->project_name ?: '—'); ?></dd></div><div><dt>Location</dt><dd><?php echo esc_html($revision->property_location ?: '—'); ?></dd></div></dl></div><div class="idlm-quote-identity-side idlm-quote-meta"><h2>Quotation</h2><dl><div><dt>Saving as</dt><dd><?php echo esc_html(IDLM_Design_Revisions::revision_name($bundle->quote_number, $next_number)); ?></dd></div><div><dt>Starting from</dt><dd><?php echo esc_html($source->revision_name); ?></dd></div><div><dt>Sales price</dt><dd><?php echo esc_html(self::money($row->baseline_total, $currency_code)); ?></dd></div><div><dt>Payments</dt><dd>Saving a revision does not change the payment schedule.</dd></div></dl></div></section>
            <section class="idlm-quote-panel"><div class="idlm-panel-heading"><h2>Sections and line items</h2></div><?php if (!$can_change_pricing): ?><p class="description">You can change line rates; each change is listed for your Design Manager, who reviews the revision before it goes to the customer. Discounts are locked. Ask your Design Manager to change them.</p><?php endif; ?><div id="idlm-quote-sections"><?php foreach ($sections as $section_index => $section) { self::render_section($section, $section_index, $items, $currency_code); } ?></div><div class="idlm-add-section-row"><button type="button" class="button idlm-add-section-item">Add line</button><button type="button" class="button" id="idlm-add-section">Add section</button><button type="button" class="button" id="idlm-open-bulk-items" aria-haspopup="dialog" aria-controls="idlm-bulk-items-modal">Add from catalogue</button></div><div class="idlm-subtotals-layout"><section class="idlm-section-summary"><h3>Section-wise summary</h3><div id="idlm-section-totals"></div></section><section class="idlm-wood-summary"><h3>Wood and others summary</h3><div id="idlm-wood-totals"></div></section><?php self::render_totals_panel($revision, (float) $source->soft_close_percent, $currency_symbol, !empty($revision->soft_close_enabled), !$can_change_pricing); ?></div></section>
            <?php if ($can_change_pricing): ?><section class="idlm-quote-panel idlm-additional-notes"><h2>Discount remark</h2><p class="description">Required only when a discount changes. It is saved in the audit history.</p><textarea name="discount_remark" rows="2" aria-label="Discount remark" maxlength="2000"></textarea></section><?php endif; ?>
            <section class="idlm-quote-panel idlm-additional-notes"><h2>Internal notes</h2><p class="description">Visible only inside the CRM. Internal notes are never included in customer print or PDF output.</p><textarea name="internal_notes" rows="4" aria-label="Internal notes"><?php echo esc_textarea($revision->internal_notes ?? ''); ?></textarea></section>
            <div class="idlm-bulk-items-modal" id="idlm-bulk-items-modal" role="dialog" aria-modal="true" aria-labelledby="idlm-bulk-items-title" hidden><div class="idlm-bulk-items-dialog"><header><div><h2 id="idlm-bulk-items-title">Add catalogue items</h2><p>Select items to add to the last section.</p></div><button type="button" class="idlm-bulk-items-close" aria-label="Close bulk items">&times;</button></header><div class="idlm-bulk-items-columns"><section><label for="idlm-bulk-items-search">Search catalogue</label><input type="search" id="idlm-bulk-items-search" placeholder="Search item, code or category"><div class="idlm-bulk-items-list" id="idlm-bulk-items-available" aria-live="polite"><p class="idlm-bulk-items-empty">Loading catalogue…</p></div></section><section><h3>Selected <span id="idlm-bulk-items-count">0</span></h3><div class="idlm-bulk-items-list" id="idlm-bulk-items-selected" aria-live="polite"><p class="idlm-bulk-items-empty">No items selected.</p></div></section></div><footer><button type="button" class="button idlm-bulk-items-cancel">Cancel</button><button type="button" class="button button-primary" id="idlm-add-bulk-items" disabled>Add selected</button></footer></div></div>
            <div class="idlm-modal idlm-dialog idlm-autosave-cancel-modal" data-autosave-cancel-modal data-idlm-dialog data-idlm-use-hidden="true" data-idlm-initial-focus="[data-autosave-save]" role="dialog" aria-modal="true" aria-labelledby="idlm-autosave-cancel-title" aria-describedby="idlm-autosave-cancel-description" aria-hidden="true" hidden><div class="idlm-modal-content idlm-dialog__panel"><header class="idlm-dialog__header"><div><h2 class="idlm-dialog__title" id="idlm-autosave-cancel-title">Save or discard your changes?</h2><p class="idlm-dialog__description" id="idlm-autosave-cancel-description">Your autosaved edits are a temporary working copy and have not changed the saved revision.</p></div><button type="button" class="idlm-dialog__close" data-autosave-continue data-idlm-dialog-close aria-label="Close unsaved quote changes dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header><div class="idlm-dialog__body"><p>Choose whether to continue editing, discard the working copy, or save it as a revision.</p></div><footer class="idlm-dialog__footer"><button type="button" class="button button-link-delete idlm-quote-dialog-discard" data-autosave-discard>Discard changes</button><button type="button" class="button" data-autosave-continue data-idlm-dialog-close>Continue editing</button><button type="button" class="button button-primary" data-autosave-save>Save as revision</button></footer></div></div>
            <div class="idlm-quote-actions"><span class="idlm-autosave-status" data-autosave-status data-has-working-copy="<?php echo $has_working_copy ? '1' : '0'; ?>" role="status" aria-live="polite"><?php echo esc_html($has_working_copy ? 'Working copy restored' : 'Autosave on'); ?></span><span class="idlm-quote-running-total"><span>Grand total</span><strong data-quote-total-mirror aria-live="off"><?php echo esc_html($currency_symbol); ?>0.00</strong></span><button type="button" class="idlm-btn idlm-btn-secondary" data-quote-cancel data-cancel-url="<?php echo esc_url($detail_url); ?>">Cancel</button><button type="submit" class="idlm-btn idlm-btn-primary">Save revision</button></div>
        </form></div><?php
    }
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

    private static function render_item_row($item, $index, $currency_code = 'INR') {
        $name = 'items[' . absint($index) . ']';
        if ('header' === ($item->row_type ?? 'item')) {
            ?><tr class="idlm-quote-item-row idlm-quote-header-row">
                <td colspan="7" data-label="Header"><input type="hidden" name="<?php echo esc_attr($name); ?>[section_key]" value=""><input type="hidden" name="<?php echo esc_attr($name); ?>[row_type]" value="header"><input class="idlm-inline-header-input" aria-label="Header title" name="<?php echo esc_attr($name); ?>[description]" value="<?php echo esc_attr($item->description ?? ''); ?>" placeholder="Header title" required></td>
                <td class="idlm-line-actions" data-label="Header actions"><button type="button" class="button-link idlm-line-action idlm-move-row-up" title="Move up" aria-label="Move header up">↑</button><button type="button" class="button-link idlm-line-action idlm-move-row-down" title="Move down" aria-label="Move header down">↓</button><button type="button" class="button-link-delete idlm-line-action idlm-remove-row" title="Remove" aria-label="Remove header">×</button></td>
            </tr><?php
            return;
        }
        $is_catalogue_item = !empty($item->catalogue_item_id);
        ?><tr class="idlm-quote-item-row idlm-quote-item-card <?php echo $is_catalogue_item ? 'is-catalogue-item' : ''; ?>">
            <td class="idlm-item-cell" data-label="Item"><input type="hidden" name="<?php echo esc_attr($name); ?>[section_key]" value=""><input type="hidden" name="<?php echo esc_attr($name); ?>[row_type]" value="item"><input type="hidden" name="<?php echo esc_attr($name); ?>[catalogue_item_id]" value="<?php echo esc_attr($item->catalogue_item_id ?? 0); ?>"><input type="hidden" name="<?php echo esc_attr($name); ?>[is_wood]" value="<?php echo esc_attr(!empty($item->is_wood) ? 1 : 0); ?>"><input type="hidden" name="<?php echo esc_attr($name); ?>[room_area]" value="<?php echo esc_attr($item->room_area ?? ''); ?>"><input type="hidden" name="<?php echo esc_attr($name); ?>[specification]" value="<?php echo esc_attr($item->specification ?? ''); ?>"><input type="hidden" name="<?php echo esc_attr($name); ?>[is_optional]" value="<?php echo esc_attr(!empty($item->is_optional) ? 1 : 0); ?>"><div class="idlm-catalogue-search-wrap"><input class="idlm-catalogue-search" aria-label="Item name or catalogue search" name="<?php echo esc_attr($name); ?>[item_name]" value="<?php echo esc_attr($item->item_name ?? ''); ?>" autocomplete="off" placeholder="Search catalogue or enter item"><div class="idlm-catalogue-results"></div></div><textarea class="idlm-item-description idlm-catalogue-locked" aria-label="Item description" required placeholder="Item description" name="<?php echo esc_attr($name); ?>[description]"<?php echo $is_catalogue_item ? ' readonly="readonly"' : ''; ?>><?php echo esc_textarea($item->description ?? ''); ?></textarea><div class="idlm-item-meta"><input class="idlm-catalogue-locked" placeholder="Item code" aria-label="Item code" name="<?php echo esc_attr($name); ?>[item_code]" value="<?php echo esc_attr($item->item_code ?? ''); ?>"<?php echo $is_catalogue_item ? ' readonly="readonly"' : ''; ?>><input class="idlm-catalogue-locked" placeholder="Category" aria-label="Category" name="<?php echo esc_attr($name); ?>[category]" value="<?php echo esc_attr($item->category ?? ''); ?>"<?php echo $is_catalogue_item ? ' readonly="readonly"' : ''; ?>></div></td>
            <td data-label="Width"><input class="idlm-calc idlm-measurement-input idlm-item-length" aria-label="Width" type="number" max="999999999.99" min="0" step="0.01" name="<?php echo esc_attr($name); ?>[length]" value="<?php echo esc_attr(number_format((float) ($item->length ?? 0), 2, '.', '')); ?>"></td>
            <td data-label="Height"><input class="idlm-calc idlm-measurement-input idlm-item-height" aria-label="Height" type="number" max="999999999.99" min="0" step="0.01" name="<?php echo esc_attr($name); ?>[height]" value="<?php echo esc_attr(number_format((float) ($item->height ?? 0), 2, '.', '')); ?>"></td>
            <td data-label="Quantity"><input class="idlm-calc idlm-measurement-input idlm-item-quantity" aria-label="Quantity" type="number" max="999999999.99" min="0.01" step="0.01" name="<?php echo esc_attr($name); ?>[quantity]" value="<?php echo esc_attr(number_format((float) ($item->quantity ?? 1), 2, '.', '')); ?>"></td>
            <td data-label="Unit"><input class="idlm-unit-value" aria-label="Unit" readonly name="<?php echo esc_attr($name); ?>[unit]" value="<?php echo esc_attr($item->unit ?? 'Nos'); ?>"></td>
            <td class="idlm-currency-input" data-label="Rate"><span aria-hidden="true"><?php echo esc_html(self::currency_symbol($currency_code)); ?></span><input class="idlm-calc idlm-spinnerless-input" aria-label="Unit rate" inputmode="decimal" type="number" max="9999999999.99" min="0" step="0.01" name="<?php echo esc_attr($name); ?>[unit_rate]" value="<?php echo esc_attr($item->unit_rate ?? 0); ?>"></td>
            <td class="idlm-line-total" data-label="Total">0.00</td><td class="idlm-line-actions" data-label="Item actions"><button type="button" class="button-link idlm-line-action idlm-move-row-up" title="Move up" aria-label="Move item up">↑</button><button type="button" class="button-link idlm-line-action idlm-move-row-down" title="Move down" aria-label="Move item down">↓</button><button type="button" class="button-link idlm-line-action idlm-duplicate-row" title="Duplicate" aria-label="Duplicate item">⧉</button><button type="button" class="button-link-delete idlm-line-action idlm-remove-row" title="Remove" aria-label="Remove item">×</button></td>
        </tr><?php
    }

    private static function render_section($section, $index, $items, $currency_code = 'INR') {
        $key = !empty($section->id) ? 'section-' . absint($section->id) : 'section-' . absint($index + 1);
        $section_items = array_values(array_filter($items, static function($item) use ($section) {
            return absint($item->section_id ?? 0) === absint($section->id ?? 0);
        }));
        if (!$section_items && 0 === absint($index)) { $section_items = $items; }
        if (!$section_items) { $section_items = array((object) array('description' => '', 'length' => 0, 'height' => 0, 'quantity' => 1, 'unit' => 'Nos', 'unit_rate' => 0)); }
        $has_line_item = (bool) array_filter($section_items, static function($item) {
            return 'header' !== ($item->row_type ?? 'item');
        });
        if (!$has_line_item) {
            $section_items[] = (object) array('description' => '', 'length' => 0, 'height' => 0, 'quantity' => 1, 'unit' => 'Nos', 'unit_rate' => 0);
        }
        ?><article class="idlm-quote-section" data-section-key="<?php echo esc_attr($key); ?>"><header><span class="dashicons dashicons-move idlm-section-handle" aria-hidden="true"></span><input type="hidden" class="idlm-section-key" name="sections[<?php echo esc_attr($index); ?>][key]" value="<?php echo esc_attr($key); ?>"><input class="idlm-section-name" aria-label="Section name" name="sections[<?php echo esc_attr($index); ?>][section_name]" value="<?php echo esc_attr($section->section_name ?? 'General'); ?>" placeholder="Room / section name"><button type="button" class="button-link idlm-move-section-up" title="Move section up" aria-label="Move section up">↑</button><button type="button" class="button-link idlm-move-section-down" title="Move section down" aria-label="Move section down">↓</button><button type="button" class="button idlm-add-section-header"><?php echo esc_html('Add header'); ?></button><button type="button" class="button-link-delete idlm-remove-section"><?php echo esc_html('Remove section'); ?></button></header><div class="idlm-quote-items-wrap"><table class="idlm-quote-items"><caption class="screen-reader-text">Editable quotation line items</caption><thead><tr><th><?php echo esc_html('Item'); ?></th><th>Width</th><th>Height</th><th>Area / Qty</th><th>Unit</th><th>Rate</th><th>Total</th><th><?php echo esc_html('Actions'); ?></th></tr></thead><tbody class="idlm-section-items"><?php foreach ($section_items as $item) { self::render_item_row($item, 0, $currency_code); } ?></tbody><tfoot><tr><th class="idlm-section-subtotal-label" colspan="6"><?php echo esc_html(($section->section_name ?? 'General') . ' subtotal'); ?></th><th class="idlm-section-subtotal">0.00</th><th></th></tr></tfoot></table></div></article><?php
    }

    private static function render_detail($quote_id, $revision_id = 0) {
        $bundle = self::get_quote_bundle($quote_id, $revision_id);
        if (!$bundle || !self::can_view_lead($bundle->lead_id)) {
            wp_die('Quotation not found.', 404);
        }
        cache_users(array_values(array_filter(array_map(static function($row) { return absint($row->created_by); }, $bundle->revisions))));
        $revision = $bundle->revision;
        $revision->revision_name = self::revision_label($revision);
        $creator = get_userdata($revision->created_by);
        self::render_detail_crm($bundle, $quote_id, $revision, $creator);
    }

    private static function render_readonly_items($bundle, $include_summary = true) {
        $categories = array();
        $sections = array();
        foreach ($bundle->items as $item) {
            if ($item->is_optional) { continue; }
            $category = $item->category ?: 'Uncategorised';
            if (!isset($categories[$category])) { $categories[$category] = array('subtotal' => 0.0); }
            $categories[$category]['subtotal'] += (float) $item->line_subtotal;
            $section_key = absint($item->section_id);
            $sections[$section_key] = ($sections[$section_key] ?? 0) + (float) $item->line_subtotal;
        }
        $tax_total = (float) $bundle->revision->cgst_total + (float) $bundle->revision->sgst_total + (float) $bundle->revision->igst_total;
        $additional_discount = (float) ($bundle->revision->additional_discount_amount ?? 0);
        if ($include_summary): ?>
            <section class="idlm-print-summary">
                <h2>Quotation Summary</h2>
                <div class="idlm-print-summary-grid"><div><h3>Section-wise subtotal before discount</h3><table><thead><tr><th>Sl No</th><th>Section</th><th>Subtotal before discount</th></tr></thead><tbody><?php foreach ($bundle->sections as $section_index => $section): ?><tr><td><?php echo esc_html($section_index + 1); ?></td><td><?php echo esc_html($section->section_name); ?></td><td><?php echo esc_html(self::money($sections[absint($section->id)] ?? 0, $bundle->revision->currency_code)); ?></td></tr><?php endforeach; ?></tbody></table></div><div><h3>Category-wise subtotal</h3><table><thead><tr><th>Category</th><th>Subtotal</th></tr></thead><tbody><?php foreach ($categories as $category => $amounts): ?><tr><td><?php echo esc_html($category); ?></td><td><?php echo esc_html(self::money($amounts['subtotal'], $bundle->revision->currency_code)); ?></td></tr><?php endforeach; ?></tbody></table></div></div>
                <dl class="idlm-readonly-totals idlm-print-summary-totals">
                    <div><dt>Subtotal</dt><dd><?php echo esc_html(self::money($bundle->revision->subtotal, $bundle->revision->currency_code)); ?></dd></div>
                    <div><dt>Wooden Subtotal</dt><dd><?php echo esc_html(self::money($bundle->revision->wooden_subtotal ?? 0, $bundle->revision->currency_code)); ?></dd></div>
                    <div><dt>Wooden Discount (<?php echo esc_html(number_format_i18n((float) ($bundle->revision->wooden_discount_percent ?? 0), 2)); ?>%)</dt><dd><?php echo esc_html(self::money($bundle->revision->wooden_discount_amount ?? 0, $bundle->revision->currency_code)); ?></dd></div>
                    <div><dt>Total</dt><dd><?php echo esc_html(self::money($bundle->revision->total_before_additional_discount ?? $bundle->revision->subtotal, $bundle->revision->currency_code)); ?></dd></div>
                    <?php if ($additional_discount > 0): ?><div><dt>Additional Discount on Total</dt><dd><?php echo esc_html(self::money($additional_discount, $bundle->revision->currency_code)); ?></dd></div><div><dt>Total after additional discount</dt><dd><?php echo esc_html(self::money($bundle->revision->taxable_total, $bundle->revision->currency_code)); ?></dd></div><?php endif; ?>
                    <div><dt>GST (18%)</dt><dd><?php echo esc_html(self::money($tax_total, $bundle->revision->currency_code)); ?></dd></div>
                    <div class="idlm-grand-total"><dt>Grand Total</dt><dd><?php echo esc_html(self::money($bundle->revision->grand_total, $bundle->revision->currency_code)); ?></dd></div>
                </dl>
            </section>
        <?php endif; ?>
        <section class="idlm-print-breakdown"><?php if ($include_summary): ?><h2>Section &amp; Item Breakdown</h2><?php endif; ?><?php
        foreach ($bundle->sections as $section): $section_total = 0; ?>
            <article class="idlm-quote-section idlm-readonly-section"><header><strong><?php echo esc_html($section->section_name); ?></strong></header>
            <div class="idlm-quote-items-wrap"<?php echo (!$include_summary) ? ' tabindex="0" role="region" aria-label="' . esc_attr($section->section_name . ' line items') . '"' : ''; ?>><table class="idlm-quote-items"><thead><tr><th>S.No</th><th>Item</th><th>Description</th><th>Width</th><th>Height</th><th>Area / Qty</th><th>Unit</th><th>Rate</th><th>Subtotal</th></tr></thead><tbody><?php $serial = 0; foreach ($bundle->items as $item): if (absint($item->section_id) !== absint($section->id)) { continue; } if ('header' === ($item->row_type ?? 'item')): ?><tr class="idlm-readonly-header-row"><th colspan="9"><?php echo esc_html($item->description); ?></th></tr><?php continue; endif; $serial++; if (!$item->is_optional) { $section_total += (float) $item->line_subtotal; } ?><tr class="<?php echo $item->is_optional ? 'is-optional' : ''; ?>"><td><?php echo esc_html($serial); ?></td><td><?php echo esc_html($item->item_name ?: $item->item_code); ?><small><?php echo esc_html(trim(($item->category ?: 'Uncategorised') . ($item->item_code ? ' · ' . $item->item_code : ''))); ?></small></td><td><?php echo esc_html($item->description); ?><small><?php echo esc_html($item->specification); ?></small></td><td><?php echo esc_html(self::readonly_measure($item->length ?? 0, $include_summary)); ?></td><td><?php echo esc_html(self::readonly_measure($item->height ?? 0, $include_summary)); ?></td><td><?php echo esc_html(number_format_i18n((float) ($item->quantity ?? 0), 2)); ?></td><td><?php echo esc_html($item->unit); ?></td><td><?php echo esc_html(self::readonly_money($item->unit_rate, $bundle->revision->currency_code, $include_summary)); ?></td><td><?php echo esc_html($item->is_optional ? 'Optional' : self::readonly_money($item->line_subtotal, $bundle->revision->currency_code, $include_summary)); ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th colspan="8"><?php echo esc_html($section->section_name); ?> <?php echo esc_html($include_summary ? 'Subtotal' : 'subtotal'); ?></th><th><?php echo esc_html(self::readonly_money($section_total, $bundle->revision->currency_code, $include_summary)); ?></th></tr></tfoot></table></div>
            </article><?php endforeach; ?></section><?php
    }

    private static function render_payment_structure_shortcode($html, $bundle, $terms) {
        if (false === strpos((string) $html, '[idlm_payment_structure]')) { return $html; }
        $rows = $terms['milestones'];
        $percentage_total = 0; $amount_total = 0;
        foreach ($rows as $row) {
            $percentage_total += IDLM_Quotation_Calculator::decimal_units($row['percentage'], 0, 10000);
            $amount_total += IDLM_Quotation_Calculator::decimal_units($row['amount'], 0, IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS);
        }
        ob_start();
        if ($rows): ?><section class="idlm-print-payment-structure"><h2>Payment Structure</h2><table><thead><tr><th>Milestone</th><th>Percentage</th><th>Amount</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?php echo esc_html($row['name']); ?></td><td><?php echo esc_html(number_format_i18n((float) $row['percentage'], 2)); ?>%</td><td><?php echo esc_html(self::money($row['amount'], $bundle->revision->currency_code)); ?></td></tr><?php endforeach; ?></tbody><tfoot><tr><th>Total</th><th><?php echo esc_html(number_format_i18n($percentage_total / 100, 2)); ?>%</th><th><?php echo esc_html(self::money($amount_total / 100, $bundle->revision->currency_code)); ?></th></tr></tfoot></table></section><?php endif;
        return str_replace('[idlm_payment_structure]', ob_get_clean(), $html);
    }

    private static function money($value, $currency = '') {
        if ($currency === '') { $currency = self::settings()['currency_code']; }
        return self::currency_symbol($currency) . number_format_i18n((float) $value, 2);
    }

    private static function currency_symbol($currency) {
        $currency = strtoupper((string) $currency);
        $symbol = array('INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'AED' => 'د.إ ', 'AUD' => 'A$', 'CAD' => 'C$', 'SGD' => 'S$', 'JPY' => '¥');
        return $symbol[$currency] ?? $currency . ' ';
    }

    private static function revision_label($revision) {
        return 'R' . str_pad((string) max(1, absint($revision->revision_number ?? 1)), 2, '0', STR_PAD_LEFT) . (IDLM_Design_Revisions::SUFFIX === substr((string) ($revision->revision_name ?? ''), -4) ? IDLM_Design_Revisions::SUFFIX : '');
    }

    public static function render_print_view() {
        if (!self::can('idlm_view_quotes') || !self::can('idlm_download_quote_pdf')) {
            wp_die('You do not have permission to print quotations.', 403);
        }
        check_admin_referer('idlm_print_quote');
        $quote_id = absint($_GET['quote_id'] ?? 0);
        $revision_id = absint($_GET['revision_id'] ?? 0);
        $bundle = self::get_quote_bundle($quote_id, $revision_id);
        if (!$bundle || !self::can_view_lead($bundle->lead_id)) {
            wp_die('Quotation not found.', 404);
        }
        if (!IDLM_Design_Revisions::may_share($bundle->revision->id)) {
            wp_die(esc_html(IDLM_Design_Revisions::WAITING_FOR_REVIEW), 403);
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Renderer escapes fields and applies the document HTML allowlist before building the complete page.
        echo self::render_print_document($bundle);
        exit;
    }

    /** Build final print HTML without requiring client-side DOM mutation. */
    public static function render_print_document($bundle) {
        $revision = $bundle->revision;
        $revision->revision_name = self::revision_label($revision);
        $settings = self::settings();
        $document_terms = IDLM_Quotation_Document::read($revision);
        $terms_known = is_array($document_terms);
        // Branding and paper layout are current; contractual content never falls back to settings.
        foreach (array('cover_page_enabled','cover_page_html','after_quote_enabled','after_quote_html','pdf_footer') as $field) {
            $settings[$field] = $terms_known ? $document_terms[$field] : '';
        }
        $after_quote_html = $terms_known ? self::render_payment_structure_shortcode($settings['after_quote_html'], $bundle, $document_terms) : '';
        $created_by = get_userdata(absint($revision->created_by));
        $updated_by = get_userdata(absint($revision->updated_by));
        $created_at_display = $revision->created_at ? mysql2date('d-m-Y h:i A', $revision->created_at, false) : '—';
        $updated_at_display = $revision->updated_at ? mysql2date('d-m-Y h:i A', $revision->updated_at, false) : '—';
        ob_start();
        $document_title = preg_replace('~[\\\\/:*?"<>|]+~', '-', $bundle->quote_number . '-' . $revision->revision_name . '-' . ($revision->customer_name ?: 'Quotation'));
        ?><!doctype html>
        <html>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <title><?php echo esc_html($document_title); ?></title>
            <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone admin-post print document does not run wp_head(). ?>
            <link rel="stylesheet" href="<?php echo esc_url(IDLM_URL . 'assets/css/quotations.css?ver=' . IDLM_VERSION); ?>">
            <style>@page{size:<?php echo esc_html($settings['paper_size']); ?>;margin:15mm}body{background:#fff;margin:0;color:#1f2937}.idlm-print,.idlm-print-custom{max-width:1000px;margin:0 auto;padding:40px}.idlm-print-cover{break-after:page;page-break-after:always}.idlm-print-after{break-before:page;page-break-before:always}.idlm-print-document-head{display:flex;justify-content:space-between;border-bottom:3px solid <?php echo esc_html($settings['accent_color']); ?>;padding-bottom:22px;margin-bottom:24px}.idlm-print-logo{max-width:180px;max-height:80px}.idlm-print h2{color:<?php echo esc_html($settings['accent_color']); ?>}.idlm-print table{width:100%;border-collapse:collapse}.idlm-print th,.idlm-print td{border-bottom:1px solid #ddd;padding:9px;text-align:left}.idlm-print-summary{break-after:page;page-break-after:always}.idlm-print-summary-grid,.idlm-print-party-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px}.idlm-print-document-meta div,.idlm-print-party-card dl div{display:grid;grid-template-columns:minmax(130px,auto) 1fr;gap:8px}.idlm-print-document-meta dt,.idlm-print-party-card dt{font-weight:600}.idlm-print-summary td:last-child,.idlm-print-summary th:not(:first-child),.idlm-print-payment-structure td:nth-child(n+3),.idlm-print-payment-structure th:nth-child(n+3){text-align:right}.idlm-print-footer{border-top:1px solid #ddd;margin-top:32px;padding-top:12px;text-align:center;color:#6b7280}@media print{.no-print{display:none}.idlm-print,.idlm-print-custom{padding:0}}</style>
            <style>
                /* Preview / Save PDF document summary. */
                section.idlm-print-summary {
                    border: 2px solid #334155 !important;
                    border-radius: 8px;
                    overflow: hidden;
                }
                section.idlm-print-summary > h2 {
                    margin: 0;
                    padding: 12px 16px;
                    border-bottom: 2px solid #334155 !important;
                    background: #334155 !important;
                    color: #ffffff !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                .idlm-print .idlm-readonly-section {
                    border: 1px solid #94a3b8;
                    border-radius: 0;
                }
                .idlm-print .idlm-readonly-section > header {
                    border-bottom: 1px solid #94a3b8;
                }
                section.idlm-print-customer-details {
                    width: 100%;
                    margin-bottom: 24px;
                    border: 1px solid #94a3b8;
                    border-radius: 8px;
                    overflow: hidden;
                    break-inside: avoid;
                }
                section.idlm-print-customer-details > h2 {
                    margin: 0;
                    padding: 10px 16px;
                    border-bottom: 1px solid #94a3b8;
                    background: #f8fafc;
                    color: #1f2937 !important;
                    font-size: 16px;
                }
                .idlm-print-customer-details table {
                    width: 100%;
                    table-layout: fixed;
                }
                .idlm-print-customer-details th,
                .idlm-print-customer-details td {
                    padding: 8px 10px;
                    overflow-wrap: anywhere;
                    vertical-align: top;
                }
                .idlm-print-customer-details th {
                    background: #f8fafc;
                    color: #475569;
                    font-size: 10px;
                    text-transform: uppercase;
                }
                </style>
        </head>
        <body>
            <button class="button no-print" onclick="window.print()">Print / Save PDF</button>
            <?php if (!$terms_known): ?><p class="idlm-document-terms-notice"><?php echo esc_html(is_wp_error($document_terms) ? 'Saved document terms are invalid. Review the original document before relying on these terms.' : 'Historical document terms were not captured. Current document settings have not been substituted.'); ?></p><?php endif; ?>
            <?php if (!empty($settings['cover_page_enabled']) && !empty($settings['cover_page_html'])): ?><div class="idlm-print-custom idlm-print-cover"><?php echo wp_kses_post($settings['cover_page_html']); ?></div><?php endif; ?>
            <main class="idlm-print">
                <header class="idlm-print-document-head">
                    <div class="idlm-print-logo-box"><?php if ($settings['company_logo']): ?><img class="idlm-print-logo" src="<?php echo esc_url($settings['company_logo']); ?>" alt=""><?php else: ?><h1 class="idlm-print-logo-fallback"><?php echo esc_html($settings['company_name']); ?></h1><?php endif; ?></div>
                    <dl class="idlm-print-document-meta">
                        <div><dt>Quotation #:</dt><dd><?php echo esc_html($bundle->quote_number); ?></dd></div>
                        <div><dt>Revision:</dt><dd><?php echo esc_html($revision->revision_name); ?></dd></div>
                        <?php $idlm_design_meta = IDLM_Design_Revisions::meta($revision->id); if ($idlm_design_meta): ?><div><dt>Sales Representative:</dt><dd><?php $idlm_sales_rep = !empty($bundle->lead->assigned_rep_id) ? get_userdata(absint($bundle->lead->assigned_rep_id)) : false; echo esc_html($idlm_sales_rep ? $idlm_sales_rep->display_name : '—'); ?></dd></div><div><dt>Design Consultant:</dt><dd><?php echo esc_html($idlm_design_meta->designer_name ?: '—'); ?></dd></div><?php endif; ?>
                        <div><dt>Created / Modified By:</dt><dd><?php echo esc_html(($created_by ? $created_by->display_name : 'Unknown') . ' / ' . ($updated_by ? $updated_by->display_name : 'Unknown')); ?></dd></div>
                        <div><dt>Created / Updated Date:</dt><dd><?php echo esc_html($created_at_display . ' / ' . $updated_at_display); ?></dd></div>
                    </dl>
                </header>
                <section class="idlm-print-customer-details"><h2>Customer Details</h2><table><thead><tr><th>Client Name</th><th>Phone Number</th><th>Email ID</th><th>Property Name</th><th>Location</th></tr></thead><tbody><tr><td><?php echo esc_html($revision->customer_name ?: '—'); ?></td><td><?php echo esc_html($revision->customer_phone ?: '—'); ?></td><td><?php echo esc_html($revision->customer_email ?: '—'); ?></td><td><?php echo esc_html($revision->project_name ?: '—'); ?></td><td><?php echo esc_html($revision->property_location ?: '—'); ?></td></tr></tbody></table></section>
                <?php self::render_readonly_items($bundle); ?>
                <?php if (!empty($settings['after_quote_enabled']) && !empty($after_quote_html)): ?><div class="idlm-print-custom idlm-print-after"><?php echo wp_kses_post($after_quote_html); ?></div><?php endif; ?>
                <?php if ($settings['pdf_footer']): ?><footer class="idlm-print-footer"><?php echo nl2br(esc_html($settings['pdf_footer'])); ?></footer><?php endif; ?>
            </main>
        </body>
        </html><?php
        return ob_get_clean();

    }

}
