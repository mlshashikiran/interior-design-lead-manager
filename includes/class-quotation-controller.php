<?php
/**
 * Quotation request, transaction, CSV, and transport controller.
 *
 * Builder: Shashi
 */

if (!defined('ABSPATH')) {
    exit;
}

final class IDLM_Quotation_Controller extends IDLM_Quotations {
    public static function handle_save() {
        if (!self::can('idlm_create_quotes')) {
            wp_die('You do not have permission to save quotations.', 403);
        }
        check_admin_referer('idlm_save_quote');
        global $wpdb;
        $quote_id = absint($_POST['quote_id'] ?? 0);
        $creating_quote = !$quote_id;
        $revision_id = absint($_POST['revision_id'] ?? 0);
        $lead_id = absint($_POST['lead_id'] ?? 0);
        if (!self::can_view_lead($lead_id)) {
            wp_die('You cannot create a quotation for this lead.', 403);
        }
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        if ('' === $title) {
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'lead_id' => $lead_id, 'idlm_error' => 'A quotation title is required.'));
        }
        $revision_reason = sanitize_text_field(wp_unslash($_POST['revision_reason'] ?? ''));
        $save_request_key = sanitize_text_field(wp_unslash($_POST['save_request_key'] ?? ''));
        if (!preg_match('/^[a-f0-9-]{36}$/i', $save_request_key)) {
            $save_request_key = wp_generate_uuid4();
        }
        $existing_request_revision = IDLM_Quotation_Repository::get_revision_by_save_request_key($save_request_key);
        if ($existing_request_revision) {
            $existing_request_quote = self::get_quote($existing_request_revision->quote_id);
            if ($existing_request_quote && self::can_view_lead($existing_request_quote->lead_id)) {
                self::redirect(array('action' => 'view', 'quote_id' => absint($existing_request_quote->id), 'idlm_notice' => 'This quotation save was already completed.'));
            }
        }
        if ($quote_id && '' === $revision_reason) {
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'idlm_error' => 'Enter an edit remark explaining why the quotation was changed.'));
        }
        $quote_settings = self::settings();
        $items = self::parse_items(wp_unslash((array) ($_POST['items'] ?? array())), $quote_settings);
        $line_items = array_filter($items, static function($item) {
            return 'item' === ($item['row_type'] ?? 'item');
        });
        if (!$line_items) {
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'lead_id' => $lead_id, 'idlm_error' => 'Add at least one line item.'));
        }
        $totals = self::calculate_totals($items, $_POST, $quote_settings);
        $sections = self::parse_sections($_POST['sections'] ?? array());
        $now = current_time('mysql');
        $user_id = get_current_user_id();
        $number = null;
        if (!$quote_id) {
            $number = self::next_quote_number();
            if (is_wp_error($number)) {
                self::redirect(array('idlm_error' => $number->get_error_message()));
            }
        }
        if (false === $wpdb->query('START TRANSACTION')) {
            self::rollback_save('Unable to start the quotation save.', array('action' => 'edit', 'quote_id' => $quote_id, 'lead_id' => $lead_id), 'Quotation transaction start failed');
        }

        if (!$quote_id) {
            $lead = IDLM_Quotation_Repository::get_lead_snapshot($lead_id);
            $quote_id = IDLM_Quotation_Repository::insert_quote(array(
                'lead_id' => $lead_id,
                'quote_number' => $number,
                'title' => $title,
                'status' => 'Draft',
                'owner_user_id' => absint($lead->assigned_rep_id ?? $user_id),
                'created_by' => $user_id,
                'save_request_key' => $save_request_key,
                'created_at' => $now,
                'updated_at' => $now,
            ), array('%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s'));
            if (!$quote_id) {
                $wpdb->query('ROLLBACK');
                self::redirect(array('idlm_error' => 'Unable to create the quotation.'));
            }
            $revision_number = 1;
            $revision_name = $number . '-R01';
        } else {
            $quote = self::get_quote($quote_id);
            if (!$quote || absint($quote->lead_id) !== $lead_id || !self::can_view_lead($lead_id)) {
                $wpdb->query('ROLLBACK');
                wp_die('Quotation not found.', 404);
            }
            $revision = self::get_revision($revision_id);
            if (!$revision || absint($revision->quote_id) !== $quote_id || absint($quote->current_revision_id) !== $revision_id || !self::can('idlm_edit_quote_drafts')) {
                $wpdb->query('ROLLBACK');
                self::redirect(array('action' => 'view', 'quote_id' => $quote_id, 'idlm_error' => 'Only the current quotation revision can be edited.'));
            }
            $source_revision_id = absint($revision->id);
            $revision_number = IDLM_Quotation_Repository::next_revision_number($quote_id);
            $revision_name = $quote->quote_number . '-R' . str_pad((string) $revision_number, 2, '0', STR_PAD_LEFT);
            $revision_id = 0;
            $quote_updated = IDLM_Quotation_Repository::update_quote($quote_id, array('title' => $title, 'updated_at' => $now), array('%s', '%s'));
            if (false === $quote_updated) {
                self::rollback_save('Unable to update the quotation.', array('action' => 'edit', 'quote_id' => $quote_id), 'Quote title update failed');
            }
        }

        if (empty($lead)) {
            $lead = IDLM_Quotation_Repository::get_lead_snapshot($lead_id);
        }

        $revision_data = array_merge(array(
            'quote_id' => $quote_id,
            'revision_number' => $revision_number,
            'revision_name' => $revision_name,
            'status' => 'Draft',
            'source_revision_id' => absint($source_revision_id ?? 0),
            'revision_reason' => $revision_reason ?: 'Initial proposal',
            'currency_code' => sanitize_text_field(wp_unslash($_POST['currency_code'] ?? get_option('idlm_currency_code', 'INR'))),
            'valid_until' => self::clean_date(wp_unslash($_POST['valid_until'] ?? '')),
            'customer_intro' => sanitize_textarea_field(wp_unslash($_POST['customer_intro'] ?? (isset($revision->customer_intro) ? $revision->customer_intro : ''))),
            'customer_name' => sanitize_text_field(wp_unslash($_POST['customer_name'] ?? trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? '')))),
            'customer_phone' => sanitize_text_field(wp_unslash($_POST['customer_phone'] ?? ($lead->phone ?? ''))),
            'customer_email' => sanitize_email(wp_unslash($_POST['customer_email'] ?? ($lead->email ?? ''))),
            'project_name' => sanitize_text_field(wp_unslash($_POST['project_name'] ?? ($lead->project_name ?? ''))),
            'property_location' => sanitize_textarea_field(wp_unslash($_POST['property_location'] ?? ($lead->property_location ?? ''))),
            'internal_notes' => sanitize_textarea_field(wp_unslash($_POST['internal_notes'] ?? (isset($revision->internal_notes) ? $revision->internal_notes : ''))),
            'updated_by' => $user_id,
            'updated_at' => $now,
            'save_request_key' => $save_request_key,
        ), $totals);

        if (!$revision_id) {
            $revision_data['created_by'] = $user_id;
            $revision_data['created_at'] = $now;
            $revision_id = IDLM_Quotation_Repository::insert_revision($revision_data);
            $ok = (bool) $revision_id;
            $quote_updated = IDLM_Quotation_Repository::set_current_revision($quote_id, $revision_id, 'Draft', $now);
            if (false === $quote_updated) {
                self::rollback_save('Unable to set the current quotation revision.', array('action' => 'edit', 'quote_id' => $quote_id), 'Current revision pointer update failed');
            }
        } else {
            $ok = IDLM_Quotation_Repository::update_revision($revision_id, $revision_data);
        }
        if (false === $ok) {
            $wpdb->query('ROLLBACK');
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'idlm_error' => 'Unable to save the quotation revision.'));
        }
        if (!empty($source_revision_id)) {
            $superseded = IDLM_Quotation_Repository::supersede_revision($source_revision_id, $now);
            if (false === $superseded) {
                self::rollback_save('Unable to finalize the previous quotation revision.', array('action' => 'edit', 'quote_id' => $quote_id), 'Previous revision supersede failed');
            }
        }

        $contents_saved = IDLM_Quotation_Repository::replace_revision_contents($revision_id, $sections, $items);
        if (is_wp_error($contents_saved)) {
            self::rollback_save($contents_saved->get_error_message(), array('action' => 'edit', 'quote_id' => $quote_id), $contents_saved->get_error_data());
        }
        $lead_updated = IDLM_Quotation_Repository::update_lead_quote_value($lead_id, $totals['grand_total'], $now);
        if (false === $lead_updated) {
            $wpdb->query('ROLLBACK');
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'idlm_error' => 'Unable to update the lead quote value.'));
        }
        if ($creating_quote) {
            $history_saved = self::insert_history($quote_id, $revision_id, '', 'Draft', 'Initial quotation created.');
        } elseif (!empty($source_revision_id)) {
            $history_saved = self::insert_history($quote_id, $revision_id, 'Draft', 'Draft', $revision_reason);
        } else {
            $history_saved = true;
        }
        if (false === $history_saved) {
            self::rollback_save('Unable to record the quotation history.', array('action' => 'edit', 'quote_id' => $quote_id), 'Quotation history insert failed');
        }
        if (false === $wpdb->query('COMMIT')) {
            self::rollback_save('Unable to commit the quotation changes.', array('action' => 'edit', 'quote_id' => $quote_id), 'Quotation transaction commit failed');
        }
        IDLM_Read_Model_Cache::invalidate('quotation_value');
        if (!empty($source_revision_id)) {
            delete_transient(self::autosave_key($source_revision_id));
        }
        self::log_quote_activity($quote_id, $revision_id, 'Quotation ' . $revision_name . ' saved.');
        self::redirect(array('action' => 'view', 'quote_id' => $quote_id, 'idlm_notice' => $creating_quote ? 'Quotation saved.' : 'New revision saved.'));
    }

    public static function ajax_autosave_quote() {
        check_ajax_referer('idlm_quote_autosave', 'nonce');
        if (!self::can('idlm_edit_quote_drafts')) {
            wp_send_json_error(array('message' => 'You do not have permission to autosave quotation drafts.'), 403);
        }
        $quote_id = absint($_POST['quote_id'] ?? 0);
        $revision_id = absint($_POST['revision_id'] ?? 0);
        $lead_id = absint($_POST['lead_id'] ?? 0);
        $quote = self::get_quote($quote_id);
        $revision = self::get_revision($revision_id);
        if (!$quote || !$revision || absint($quote->lead_id) !== $lead_id || absint($quote->current_revision_id) !== $revision_id || absint($revision->quote_id) !== $quote_id || !self::can_view_lead($lead_id)) {
            wp_send_json_error(array('message' => 'Only the current quotation revision can be autosaved.'), 409);
        }
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $items = self::parse_items(wp_unslash((array) ($_POST['items'] ?? array())), self::settings());
        $line_items = array_filter($items, static function($item) {
            return 'item' === ($item['row_type'] ?? 'item');
        });
        if ('' === $title || !$line_items) {
            wp_send_json_error(array('message' => 'Autosave is waiting for a title and at least one complete line item.'), 422);
        }
        $sections = self::parse_sections($_POST['sections'] ?? array());
        $totals = self::calculate_totals($items, $_POST, self::settings());
        $revision_data = array_merge(array(
            'currency_code' => sanitize_text_field(wp_unslash($_POST['currency_code'] ?? $revision->currency_code)),
            'valid_until' => self::clean_date(wp_unslash($_POST['valid_until'] ?? '')),
            'customer_intro' => array_key_exists('customer_intro', $_POST)
                ? sanitize_textarea_field(wp_unslash($_POST['customer_intro']))
                : sanitize_textarea_field($revision->customer_intro ?? ''),
            'customer_name' => sanitize_text_field(wp_unslash($_POST['customer_name'] ?? '')),
            'customer_phone' => sanitize_text_field(wp_unslash($_POST['customer_phone'] ?? '')),
            'customer_email' => sanitize_email(wp_unslash($_POST['customer_email'] ?? '')),
            'project_name' => sanitize_text_field(wp_unslash($_POST['project_name'] ?? '')),
            'property_location' => sanitize_textarea_field(wp_unslash($_POST['property_location'] ?? '')),
            'internal_notes' => sanitize_textarea_field(wp_unslash($_POST['internal_notes'] ?? '')),
        ), $totals);
        $saved_at = time();
        $working_copy = array(
            'title' => $title,
            'revision' => $revision_data,
            'sections' => $sections,
            'items' => $items,
            'saved_at' => $saved_at,
        );
        $autosave_key = self::autosave_key($revision_id);
        $stored = set_transient($autosave_key, $working_copy, 7 * DAY_IN_SECONDS);
        if (!$stored && false === get_transient($autosave_key)) {
            wp_send_json_error(array('message' => 'Unable to store the quotation working copy.'), 500);
        }
        wp_send_json_success(array('message' => 'Working copy autosaved.', 'savedAt' => wp_date('H:i:s', $saved_at), 'grandTotal' => $totals['grand_total']));
    }

    public static function ajax_discard_quote_autosave() {
        check_ajax_referer('idlm_quote_autosave', 'nonce');
        if (!self::can('idlm_edit_quote_drafts')) {
            wp_send_json_error(array('message' => 'You do not have permission to discard quotation working copies.'), 403);
        }
        $quote_id = absint($_POST['quote_id'] ?? 0);
        $revision_id = absint($_POST['revision_id'] ?? 0);
        $quote = self::get_quote($quote_id);
        $revision = self::get_revision($revision_id);
        if (!$quote || !$revision || absint($quote->current_revision_id) !== $revision_id || absint($revision->quote_id) !== $quote_id || !self::can_view_lead($quote->lead_id)) {
            wp_send_json_error(array('message' => 'Quotation working copy not found.'), 404);
        }
        delete_transient(self::autosave_key($revision_id));
        wp_send_json_success(array('message' => 'Working copy discarded.'));
    }

    private static function clean_date($value) {
        $value = sanitize_text_field($value);
        $date = DateTime::createFromFormat('Y-m-d', $value, wp_timezone());
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public static function handle_create_revision() {
        if (!self::can('idlm_create_quote_revisions')) {
            wp_die('You do not have permission to create quotation revisions.', 403);
        }
        check_admin_referer('idlm_create_quote_revision');
        global $wpdb;
        $quote_id = absint($_POST['quote_id'] ?? 0);
        $quote = self::get_quote($quote_id);
        if (!$quote || !self::can_view_lead($quote->lead_id)) {
            wp_die('Quotation not found.', 404);
        }
        $source = self::get_revision($quote->current_revision_id);
        if (!$source) {
            self::redirect(array('quote_id' => $quote_id, 'idlm_error' => 'Current revision not found.'));
        }
        $revisable_statuses = array('Approved', 'Shared', 'Under Negotiation', 'Rejected', 'Expired');
        if (!in_array($quote->status, $revisable_statuses, true) || $source->status !== $quote->status) {
            self::redirect(array('action' => 'view', 'quote_id' => $quote_id, 'idlm_error' => 'A new revision cannot be created from the current quotation state.'));
        }
        $reason = sanitize_text_field(wp_unslash($_POST['revision_reason'] ?? ''));
        $save_request_key = sanitize_text_field(wp_unslash($_POST['save_request_key'] ?? ''));
        if (!preg_match('/^[a-f0-9-]{36}$/i', $save_request_key)) {
            $save_request_key = wp_generate_uuid4();
        }
        $existing_request_revision = IDLM_Quotation_Repository::get_revision_by_save_request_key($save_request_key);
        if ($existing_request_revision && absint($existing_request_revision->quote_id) === $quote_id) {
            self::redirect(array('action' => 'view', 'quote_id' => $quote_id, 'idlm_notice' => 'This revision request was already completed.'));
        }
        if ('' === $reason) {
            self::redirect(array('action' => 'view', 'quote_id' => $quote_id, 'idlm_error' => 'A revision reason is required.'));
        }
        $now = current_time('mysql');
        $user_id = get_current_user_id();
        if (false === $wpdb->query('START TRANSACTION')) {
            self::rollback_save('Unable to start creating the quotation revision.', array('action' => 'view', 'quote_id' => $quote_id), 'Revision transaction start failed');
        }
        $locked_quote = IDLM_Quotation_Repository::lock_quote($quote_id);
        $locked_source = $locked_quote ? self::get_revision($locked_quote->current_revision_id) : null;
        if (!$locked_quote || !$locked_source || absint($locked_source->id) !== absint($source->id) || !in_array($locked_quote->status, $revisable_statuses, true) || $locked_source->status !== $locked_quote->status) {
            $wpdb->query('ROLLBACK');
            self::redirect(array('action' => 'view', 'quote_id' => $quote_id, 'idlm_error' => 'The quotation changed before the revision could be created. Please try again.'));
        }
        $quote = $locked_quote;
        $source = $locked_source;
        $next = IDLM_Quotation_Repository::next_revision_number($quote_id);
        $data = (array) $source;
        unset($data['id']);
        $data['revision_number'] = $next;
        $data['revision_name'] = $quote->quote_number . '-R' . str_pad((string) $next, 2, '0', STR_PAD_LEFT);
        $data['status'] = 'Draft';
        $data['source_revision_id'] = absint($source->id);
        $data['revision_reason'] = $reason;
        $data['created_by'] = $user_id;
        $data['created_at'] = $now;
        $data['updated_by'] = $user_id;
        $data['updated_at'] = $now;
        $data['finalized_at'] = null;
        $data['save_request_key'] = $save_request_key;
        $new_id = IDLM_Quotation_Repository::insert_revision($data);
        if (!$new_id) {
            $wpdb->query('ROLLBACK');
            self::redirect(array('action' => 'view', 'quote_id' => $quote_id, 'idlm_error' => 'Unable to create the revision.'));
        }
        $contents_copied = IDLM_Quotation_Repository::copy_revision_contents($source->id, $new_id);
        if (is_wp_error($contents_copied)) {
            self::rollback_save($contents_copied->get_error_message(), array('action' => 'view', 'quote_id' => $quote_id), $contents_copied->get_error_data());
        }
        $source_updated = IDLM_Quotation_Repository::supersede_revision($source->id, $now);
        $quote_updated = IDLM_Quotation_Repository::set_current_revision($quote_id, $new_id, 'Draft', $now);
        $history_saved = self::insert_history($quote_id, $new_id, $quote->status, 'Draft', $reason);
        if (false === $source_updated || false === $quote_updated || false === $history_saved || false === $wpdb->query('COMMIT')) {
            self::rollback_save('Unable to finish creating the quotation revision.', array('action' => 'view', 'quote_id' => $quote_id), 'Revision finalization failed');
        }
        self::log_quote_activity($quote_id, $new_id, 'Created revision ' . $data['revision_name'] . '. Reason: ' . $reason);
        self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'idlm_notice' => 'New revision created.'));
    }

    private static function insert_history($quote_id, $revision_id, $from, $to, $note) {
        return IDLM_Quotation_Repository::insert_history($quote_id, $revision_id, $from, $to, $note);
    }

    private static function log_quote_activity($quote_id, $revision_id, $message) {
        $quote = self::get_quote($quote_id);
        if ($quote && class_exists('IDLM_DB')) {
            IDLM_DB::log_activity(absint($quote->lead_id), 'quotation', $message, get_current_user_id());
        }
    }

    protected static function get_quote($quote_id) {
        return IDLM_Quotation_Repository::get_quote($quote_id);
    }

    protected static function get_revision($revision_id) {
        return IDLM_Quotation_Repository::get_revision($revision_id);
    }

    protected static function get_quote_bundle($quote_id, $revision_id = 0) {
        return IDLM_Quotation_Repository::get_quote_bundle($quote_id, $revision_id);
    }

    public static function ajax_search_catalogue() {
        check_ajax_referer('idlm_quote_catalogue_search', 'nonce');
        if (!self::can('idlm_create_quotes')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }
        $term = sanitize_text_field(wp_unslash($_GET['term'] ?? ''));
        $bulk = 1 === absint(wp_unslash($_GET['bulk'] ?? 0));
        $page = max(1, absint(wp_unslash($_GET['catalogue_page'] ?? 1)));
        $per_page = $bulk ? 100 : 20;
        $rows = IDLM_Quotation_Repository::search_catalogue($term, $page, $per_page);
        wp_send_json_success($rows);
    }

    public static function ajax_search_leads() {
        check_ajax_referer('idlm_quote_catalogue_search', 'nonce');
        if (!self::can('idlm_create_quotes')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }
        $term = sanitize_text_field(wp_unslash($_GET['term'] ?? ''));
        if (strlen($term) < 2) {
            wp_send_json_success(array());
        }
        wp_send_json_success(IDLM_Quotation_Lead_Read_Model::search($term, 20));
    }

    /**
     * Return one scoped quotation-list page for the live toolbar search.
     */
    public static function ajax_filter_quotation_list() {
        check_ajax_referer('idlm_quote_catalogue_search', 'nonce');
        if (!self::can('idlm_view_quotes')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }
        $lead_id = absint(wp_unslash($_GET['lead_id'] ?? 0));
        $filters = array(
            'owner_user_id' => IDLM_Quotation_Policy::can_manage_all_leads() ? 0 : get_current_user_id(),
            'search' => sanitize_text_field(wp_unslash($_GET['quote_search'] ?? '')),
            'created_by' => absint(wp_unslash($_GET['created_by'] ?? 0)),
            'date_from' => self::list_filter_date(wp_unslash($_GET['date_from'] ?? '')),
            'date_to' => self::list_filter_date(wp_unslash($_GET['date_to'] ?? '')),
            'lead_id' => $lead_id && self::can_view_lead($lead_id) ? $lead_id : 0,
        );
        $page = max(1, absint(wp_unslash($_GET['quote_page'] ?? 1)));
        $per_page = 50;
        wp_send_json_success(array(
            'rows' => IDLM_Quotation_Repository::list_quotations($filters, $page, $per_page),
            'total' => IDLM_Quotation_Repository::count_quotations($filters),
            'page' => $page,
            'perPage' => $per_page,
        ));
    }

    /**
     * Return one catalogue page for the live toolbar search.
     */
    public static function ajax_filter_catalogue_list() {
        check_ajax_referer('idlm_quote_catalogue_search', 'nonce');
        if (!self::can('idlm_manage_quote_items')) {
            wp_send_json_error(array('message' => 'Permission denied.'), 403);
        }
        $sort = sanitize_key(wp_unslash($_GET['catalogue_sort'] ?? 'status'));
        if (!in_array($sort, array('code', 'category', 'item', 'unit', 'wood', 'rate', 'status'), true)) {
            $sort = 'status';
        }
        $order = 'desc' === strtolower(sanitize_key(wp_unslash($_GET['catalogue_order'] ?? 'asc'))) ? 'desc' : 'asc';
        $search = sanitize_text_field(wp_unslash($_GET['catalogue_search'] ?? ''));
        $page = max(1, absint(wp_unslash($_GET['catalogue_page'] ?? 1)));
        $per_page = 100;
        wp_send_json_success(array(
            'rows' => IDLM_Quotation_Repository::list_catalogue_items($search, $page, $per_page, $sort, $order),
            'total' => IDLM_Quotation_Repository::count_catalogue_items($search),
            'page' => $page,
            'perPage' => $per_page,
        ));
    }

    private static function list_filter_date($value) {
        $value = sanitize_text_field($value);
        $date = DateTime::createFromFormat('Y-m-d', $value, wp_timezone());
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    public static function handle_save_catalogue_category() {
        if (!self::can('idlm_manage_quote_items')) {
            wp_die('You do not have permission to manage quotation categories.', 403);
        }
        check_admin_referer('idlm_save_catalogue_category');
        $category_name = sanitize_text_field(wp_unslash($_POST['category_name'] ?? ''));
        if ('' === $category_name) {
            self::redirect(array('workspace' => 'catalogue', 'manage_categories' => 1, 'idlm_error' => 'Enter a category name.'));
        }
        $now = current_time('mysql');
        $ok = IDLM_Quotation_Repository::insert_catalogue_term('category', $category_name, get_current_user_id(), $now);
        if (!$ok) {
            self::redirect(array('workspace' => 'catalogue', 'manage_categories' => 1, 'idlm_error' => 'That category already exists.'));
        }
        self::redirect(array('workspace' => 'catalogue', 'manage_categories' => 1, 'idlm_notice' => 'Item category created.'));
    }

    public static function handle_save_catalogue_unit() {
        if (!self::can('idlm_manage_quote_items')) {
            wp_die('You do not have permission to manage quotation units.', 403);
        }
        check_admin_referer('idlm_save_catalogue_unit');
        $unit_name = sanitize_text_field(wp_unslash($_POST['unit_name'] ?? ''));
        if ('' === $unit_name) {
            self::redirect(array('workspace' => 'catalogue', 'manage_units' => 1, 'idlm_error' => 'Enter a unit name.'));
        }
        $now = current_time('mysql');
        $ok = IDLM_Quotation_Repository::insert_catalogue_term('unit', $unit_name, get_current_user_id(), $now);
        if (!$ok) {
            self::redirect(array('workspace' => 'catalogue', 'manage_units' => 1, 'idlm_error' => 'That unit already exists.'));
        }
        self::redirect(array('workspace' => 'catalogue', 'manage_units' => 1, 'idlm_notice' => 'Catalogue unit created.'));
    }

    public static function handle_save_catalogue_item() {
        if (!self::can('idlm_manage_quote_items')) {
            wp_die('You do not have permission to manage quotation items.', 403);
        }
        check_admin_referer('idlm_save_catalogue_item');
        $id = absint($_POST['item_id'] ?? 0);
        $code = sanitize_text_field(wp_unslash($_POST['item_code'] ?? ''));
        $name = sanitize_text_field(wp_unslash($_POST['item_name'] ?? ''));
        $category = sanitize_text_field(wp_unslash($_POST['category'] ?? ''));
        $unit = sanitize_text_field(wp_unslash($_POST['unit'] ?? ''));
        $category_exists = IDLM_Quotation_Repository::catalogue_term_exists('category', $category);
        $unit_exists = IDLM_Quotation_Repository::catalogue_term_exists('unit', $unit);
        if (($id && !$code) || !$name || !$category_exists || !$unit_exists) {
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => $id ? 'Item code, item name, registered category, and registered unit are required.' : 'Item name, registered category, and registered unit are required.'));
        }
        $data = array(
            'item_code' => $code,
            'category' => $category,
            'item_name' => $name,
            'description' => sanitize_textarea_field(wp_unslash($_POST['description'] ?? '')),
            'unit' => $unit,
            'is_wood' => '1' === (string) ($_POST['is_wood'] ?? '0') ? 1 : 0,
            'default_rate' => self::clean_decimal($_POST['default_rate'] ?? 0),
            'updated_at' => current_time('mysql'),
        );
        if ($id) {
            $existing_status = IDLM_Quotation_Repository::get_catalogue_item_status($id);
            if (null === $existing_status) {
                self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Catalogue item not found.'));
            }
            $data['status'] = in_array($existing_status, array('active', 'inactive'), true) ? $existing_status : 'active';
            $ok = IDLM_Quotation_Repository::update_catalogue_item($id, $data);
        } else {
            $data['status'] = 'active';
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = current_time('mysql');
            $ok = IDLM_Quotation_Repository::insert_catalogue_item($data);
        }
        self::redirect(array('workspace' => 'catalogue', false === $ok ? 'idlm_error' : 'idlm_notice' => false === $ok ? 'Unable to save item. Check for a duplicate item code.' : 'Catalogue item saved.'));
    }

    public static function handle_archive_catalogue_item() {
        if (!self::can('idlm_manage_quote_items')) {
            wp_die('You do not have permission to manage quotation items.', 403);
        }
        $id = absint($_POST['item_id'] ?? 0);
        check_admin_referer('idlm_archive_catalogue_item_' . $id);
        IDLM_Quotation_Repository::archive_catalogue_item($id, current_time('mysql'));
        self::redirect(array('workspace' => 'catalogue', 'idlm_notice' => 'Catalogue item archived.'));
    }

    public static function handle_bulk_catalogue_items() {
        if (!self::can('idlm_manage_quote_items')) {
            wp_die('You do not have permission to manage quotation items.', 403);
        }
        check_admin_referer('idlm_bulk_catalogue_items');
        $ids = array_values(array_filter(array_map('absint', (array) ($_POST['item_ids'] ?? array()))));
        $bulk_action = sanitize_key(wp_unslash($_POST['bulk_action'] ?? ''));
        if (!$ids || !in_array($bulk_action, array('archive', 'delete'), true)) {
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Select catalogue items and a valid bulk action.'));
        }
        $affected = IDLM_Quotation_Repository::bulk_catalogue_items($ids, $bulk_action, current_time('mysql'));
        $message = false === $affected ? 'Unable to update the selected catalogue items.' : sprintf('%d catalogue item(s) %s.', (int) $affected, 'archive' === $bulk_action ? 'archived' : 'deleted');
        self::redirect(array('workspace' => 'catalogue', false === $affected ? 'idlm_error' : 'idlm_notice' => $message));
    }

    public static function handle_catalogue_category_action() {
        if (!self::can('idlm_manage_quote_items')) {
            wp_die('You do not have permission to manage quotation categories.', 403);
        }
        check_admin_referer('idlm_catalogue_category_action');
        $ids = array_values(array_filter(array_map('absint', (array) ($_POST['category_ids'] ?? array()))));
        $category_action = sanitize_key(wp_unslash($_POST['category_action'] ?? ''));
        if (!$ids || !in_array($category_action, array('archive', 'delete'), true)) {
            self::redirect(array('workspace' => 'catalogue', 'manage_categories' => 1, 'idlm_error' => 'Select categories and a valid action.'));
        }
        $result = IDLM_Quotation_Repository::apply_catalogue_category_action($ids, $category_action, current_time('mysql'));
        $affected = $result['affected'];
        $blocked = $result['blocked'];
        $message = sprintf('%d categor%s %s.', $affected, 1 === $affected ? 'y' : 'ies', 'archive' === $category_action ? 'archived' : 'deleted');
        if ($blocked) { $message .= sprintf(' %d in-use categor%s could not be deleted; archive them instead.', $blocked, 1 === $blocked ? 'y' : 'ies'); }
        self::redirect(array('workspace' => 'catalogue', 'manage_categories' => 1, 'idlm_notice' => $message));
    }

    public static function handle_import_catalogue_csv() {
        if (!self::can('idlm_manage_quote_items')) {
            wp_die('You do not have permission to import quotation items.', 403);
        }
        check_admin_referer('idlm_import_catalogue_csv');
        $upload = $_FILES['catalogue_csv'] ?? array();
        $upload_error = absint($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        $upload_size = absint($upload['size'] ?? 0);
        $upload_name = sanitize_file_name(wp_unslash($upload['name'] ?? ''));
        if (UPLOAD_ERR_OK !== $upload_error || empty($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Choose a CSV file to import.'));
        }
        if ($upload_size <= 0 || $upload_size > 5 * MB_IN_BYTES || 'csv' !== strtolower((string) pathinfo($upload_name, PATHINFO_EXTENSION))) {
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'CSV files must use the .csv extension and be no larger than 5 MB.'));
        }
        if (function_exists('finfo_open')) {
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = $file_info ? (string) finfo_file($file_info, $upload['tmp_name']) : '';
            if ($file_info) { finfo_close($file_info); }
            if ($mime_type && !in_array($mime_type, array('text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'), true)) {
                self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'The uploaded file is not a recognized CSV document.'));
            }
        }
        $handle = fopen($upload['tmp_name'], 'r');
        if (!$handle) {
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Unable to read the CSV file.'));
        }
        $headers = array_map(static function($value) { return sanitize_key(str_replace(array(' ', '-'), '_', (string) $value)); }, (array) fgetcsv($handle));
        $required = array('item_code', 'item_name');
        if (array_diff($required, $headers) || count($headers) !== count(array_unique($headers)) || count($headers) > 20) {
            fclose($handle);
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'CSV requires unique item_code and item_name headers and no more than 20 columns.'));
        }
        global $wpdb;
        $valid = 0;
        $errors = 0;
        $duplicates = 0;
        $row_count = 0;
        if (false === $wpdb->query('START TRANSACTION')) {
            fclose($handle);
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Unable to start the catalogue import.'));
        }
        while (($values = fgetcsv($handle)) !== false) {
            $row_count++;
            if ($row_count > 2000) {
                $wpdb->query('ROLLBACK');
                fclose($handle);
                self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Catalogue imports are limited to 2,000 rows per file.'));
            }
            if (count($values) > count($headers) || array_filter($values, static function($value) { return strlen((string) $value) > 5000; })) {
                $errors++;
                continue;
            }
            $row = array_combine($headers, array_pad($values, count($headers), ''));
            $code = sanitize_text_field($row['item_code'] ?? '');
            $name = sanitize_text_field($row['item_name'] ?? '');
            $category = sanitize_text_field($row['category'] ?? '');
            $unit = sanitize_text_field($row['unit'] ?? 'Nos');
            if (!$name || !$category || !$unit) {
                $errors++;
                continue;
            }
            if (IDLM_Quotation_Repository::catalogue_item_code_exists($code)) {
                $duplicates++;
                continue;
            }
            $now = current_time('mysql');
            if (false === IDLM_Quotation_Repository::ensure_catalogue_term('category', $category, get_current_user_id(), $now) || false === IDLM_Quotation_Repository::ensure_catalogue_term('unit', $unit, get_current_user_id(), $now)) {
                $wpdb->query('ROLLBACK');
                fclose($handle);
                self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Catalogue import failed and was rolled back.'));
            }
            $ok = IDLM_Quotation_Repository::insert_catalogue_item(array(
                'item_code' => $code, 'category' => $category, 'item_name' => $name,
                'description' => sanitize_textarea_field($row['description'] ?? ''), 'unit' => $unit,
                'is_wood' => in_array(strtolower(trim((string) ($row['is_wood'] ?? 'no'))), array('yes', '1', 'true'), true) ? 1 : 0,
                'default_rate' => self::clean_decimal($row['default_rate'] ?? 0),
                'status' => 'active', 'created_by' => get_current_user_id(), 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
            ));
            if (!$ok) {
                $wpdb->query('ROLLBACK');
                fclose($handle);
                self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Catalogue import failed and was rolled back.'));
            }
            $valid++;
        }
        fclose($handle);
        if (false === $wpdb->query('COMMIT')) {
            $wpdb->query('ROLLBACK');
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Unable to commit the catalogue import.'));
        }
        self::redirect(array('workspace' => 'catalogue', 'idlm_notice' => sprintf('%d items imported; %d invalid rows; %d duplicate codes skipped.', $valid, $errors, $duplicates)));
    }

    private static function csv_safe_cell($value) {
        $value = (string) $value;
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $value) ? "'" . $value : $value;
    }

    public static function handle_export_catalogue_csv() {
        if (!self::can('idlm_manage_quote_items')) {
            wp_die('You do not have permission to export quotation items.', 403);
        }
        check_admin_referer('idlm_export_catalogue_csv');
        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=quotation-catalogue-' . wp_date('Y-m-d') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, array('item_code', 'category', 'item_name', 'description', 'unit', 'is_wood', 'default_rate', 'status'));
        $last_id = 0;
        do {
            $rows = IDLM_Quotation_Repository::get_catalogue_export_chunk($last_id, 500);
            foreach ($rows as $row) {
                $last_id = absint($row['id']);
                unset($row['id']);
                fputcsv($output, array_map(array(__CLASS__, 'csv_safe_cell'), $row));
            }
            if (function_exists('flush')) { flush(); }
        } while (count($rows) === 500);
        fclose($output);
        exit;
    }

    public static function handle_save_settings() {
        if (!self::can('idlm_manage_quote_settings')) {
            wp_die('You do not have permission to manage quotation settings.', 403);
        }
        check_admin_referer('idlm_save_quote_settings');
        $settings = self::settings();
        if (isset($_POST['company_name'])) {
            $settings['company_name'] = sanitize_text_field(wp_unslash($_POST['company_name']));
        }
        $settings['company_logo'] = esc_url_raw(wp_unslash($_POST['company_logo'] ?? ''));
        $settings['accent_color'] = sanitize_hex_color(wp_unslash($_POST['accent_color'] ?? '')) ?: '#111827';
        if (isset($_POST['pdf_footer'])) {
            $settings['pdf_footer'] = sanitize_textarea_field(wp_unslash($_POST['pdf_footer']));
        }
        $settings['paper_size'] = in_array(($_POST['paper_size'] ?? ''), array('A4', 'Letter'), true) ? $_POST['paper_size'] : 'A4';
        $settings['cover_page_enabled'] = !empty($_POST['cover_page_enabled']) ? 1 : 0;
        $settings['cover_page_html'] = wp_kses_post(wp_unslash($_POST['cover_page_html'] ?? ''));
        $settings['after_quote_enabled'] = !empty($_POST['after_quote_enabled']) ? 1 : 0;
        $settings['after_quote_html'] = wp_kses_post(wp_unslash($_POST['after_quote_html'] ?? ''));
        $payment_structure = array();
        foreach ((array) ($_POST['payment_structure'] ?? array()) as $payment_row) {
            $name = sanitize_text_field(wp_unslash($payment_row['name'] ?? ''));
            $percentage = min(100, self::clean_decimal($payment_row['percentage'] ?? 0));
            if ('' !== $name && $percentage > 0) {
                $payment_structure[] = array('name' => $name, 'percentage' => $percentage);
            }
        }
        $settings['payment_structure'] = wp_json_encode($payment_structure);
        $settings['soft_close_percent'] = min(100, self::clean_decimal($_POST['soft_close_percent'] ?? 0));
        $settings['number_prefix'] = preg_replace('/[^A-Za-z0-9_-]/', '', sanitize_text_field(wp_unslash($_POST['number_prefix'] ?? 'Q')));
        $settings['number_separator'] = in_array(($_POST['number_separator'] ?? ''), array('-', '/', '_'), true) ? $_POST['number_separator'] : '-';
        $settings['number_padding'] = min(10, max(3, absint($_POST['number_padding'] ?? 6)));
        unset($settings['category_discounts'], $settings['per_item_tax_enabled'], $settings['global_tax_enabled'], $settings['global_tax_type'], $settings['global_tax_rate']);
        update_option('idlm_quote_settings', $settings, false);
        self::redirect(array('workspace' => 'settings', 'idlm_notice' => 'Quotation settings saved.'));
    }


}
