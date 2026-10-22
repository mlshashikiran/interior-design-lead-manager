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
        check_admin_referer('idlm_save_quote');
        global $wpdb;
        $quote_id = absint($_POST['quote_id'] ?? 0);
        $creating_quote = !$quote_id;
        $revision_id = absint($_POST['revision_id'] ?? 0);
        $lead_id = absint($_POST['lead_id'] ?? 0);
        if (($creating_quote && !self::can('idlm_create_quotes')) || (!$creating_quote && (!self::can('idlm_edit_quote_drafts') || !self::can('idlm_create_quote_revisions')))) {
            wp_die('You do not have permission to save quotations.', 403);
        }
        if (!IDLM_Quotation_Policy::can_mutate_lead($lead_id)) {
            wp_die('You cannot create a quotation for this lead.', 403);
        }
        // A new aggregate never accepts a persisted revision identity.
        if ($creating_quote && $revision_id) {
            wp_die('Invalid quotation revision.', 403);
        }
        if (!$creating_quote) {
            $requested_quote = self::get_quote($quote_id);
            $requested_revision = self::get_revision($revision_id);
            if (!$requested_quote || !$requested_revision || absint($requested_quote->lead_id) !== $lead_id || absint($requested_revision->quote_id) !== $quote_id) {
                wp_die('Quotation not found.', 404);
            }
        }
        $revision_reason = sanitize_text_field(wp_unslash($_POST['revision_reason'] ?? ''));
        $save_request_key = sanitize_text_field(wp_unslash($_POST['save_request_key'] ?? ''));
        if (!preg_match('/^[a-f0-9-]{36}$/i', $save_request_key)) {
            $save_request_key = wp_generate_uuid4();
        }
        $existing_request_revision = IDLM_Quotation_Repository::get_revision_by_save_request_key($save_request_key);
        if ($existing_request_revision) {
            self::completed_save($existing_request_revision, $lead_id, $quote_id, $revision_id, $save_request_key);
        }
        if ($quote_id && '' === $revision_reason) {
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'idlm_error' => 'Enter a revision remark explaining why the quotation was changed.'));
        }
        if ($creating_quote) {
            if (!IDLM_Quotation_Repository::acquire_lead_quote_lock($lead_id)) {
                self::redirect(array('action' => 'edit', 'lead_id' => $lead_id, 'idlm_error' => 'Another quotation is being created for this lead. Please try again.'));
            }

        }
        $quote_settings = self::settings();
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0012: handle_save(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $items = self::parse_items(wp_unslash((array) ($_POST['items'] ?? array())), $quote_settings);
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (is_wp_error($items)) {
            if ($creating_quote) { IDLM_Quotation_Repository::release_lead_quote_lock($lead_id); }
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'lead_id' => $lead_id, 'idlm_error' => $items->get_error_message()));
        }
        $line_items = array_filter($items, static function($item) {
            return 'item' === ($item['row_type'] ?? 'item');
        });
        if (!$line_items) {
            if ($creating_quote) { IDLM_Quotation_Repository::release_lead_quote_lock($lead_id); }
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'lead_id' => $lead_id, 'idlm_error' => 'Add at least one line item.'));
        }
        $totals = self::calculate_totals($items, $_POST, $quote_settings);
        if (is_wp_error($totals)) {
            if ($creating_quote) { IDLM_Quotation_Repository::release_lead_quote_lock($lead_id); }
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'lead_id' => $lead_id, 'idlm_error' => $totals->get_error_message()));
        }
        $document_terms = IDLM_Quotation_Document::capture($quote_settings, $totals['grand_total']);
        if (is_wp_error($document_terms)) {
            if ($creating_quote) { IDLM_Quotation_Repository::release_lead_quote_lock($lead_id); }
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'lead_id' => $lead_id, 'idlm_error' => $document_terms->get_error_message()));
        }
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0013: handle_save(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $sections = self::parse_sections(wp_unslash($_POST['sections'] ?? array()));
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (is_wp_error($sections)) {
            if ($creating_quote) { IDLM_Quotation_Repository::release_lead_quote_lock($lead_id); }
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'lead_id' => $lead_id, 'idlm_error' => $sections->get_error_message()));
        }
        $now = current_time('mysql');
        $user_id = get_current_user_id();
        $number = null;
        $source_revision_id = 0;
        $transaction = IDLM_Quotation_Repository::begin_checked_transaction('save');
        if (is_wp_error($transaction)) {
            if ($creating_quote) { IDLM_Quotation_Repository::release_lead_quote_lock($lead_id); }
            self::redirect(array('action' => 'edit', 'quote_id' => $quote_id, 'lead_id' => $lead_id, 'idlm_error' => $transaction->get_error_message()));
        }

        if (!IDLM_Quotation_Policy::can_mutate_lead($lead_id, true) ||
            ($creating_quote ? !self::can('idlm_create_quotes') : (!self::can('idlm_edit_quote_drafts') || !self::can('idlm_create_quote_revisions')))) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0049: handle_save(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($creating_quote) { IDLM_Quotation_Repository::release_lead_quote_lock($lead_id); }
            wp_die('You cannot save a quotation for this lead.', 403);
        }
        $existing_request_revision = IDLM_Quotation_Repository::get_revision_by_save_request_key($save_request_key);
        if ($existing_request_revision) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0050: handle_save(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            if ($creating_quote) { IDLM_Quotation_Repository::release_lead_quote_lock($lead_id); }
            self::completed_save($existing_request_revision, $lead_id, $quote_id, $revision_id, $save_request_key);
        }
        if ($creating_quote) {
            $existing_quote = IDLM_Quotation_Repository::get_quote_for_lead($lead_id);
            if ($existing_quote) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0051: handle_save(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
                $wpdb->query('ROLLBACK');
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                IDLM_Quotation_Repository::release_lead_quote_lock($lead_id);
                self::redirect(array('action' => 'view', 'quote_id' => absint($existing_quote->id), 'idlm_notice' => 'A quotation already exists for this lead. Create a revision from that quotation.'));
            }
            // Reserve inside the authorized transaction; denied requests cannot
            // advance numbering, and this reservation must not commit the lock.
            $number = IDLM_Quotation_Repository::reserve_quote_number($quote_settings, false);
            if (is_wp_error($number)) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0052: handle_save(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
                $wpdb->query('ROLLBACK');
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                IDLM_Quotation_Repository::release_lead_quote_lock($lead_id);
                self::redirect(array('idlm_error' => $number->get_error_message()));
            }
        }

        if (!$quote_id) {
            $lead = IDLM_Quotation_Repository::get_lead_snapshot($lead_id);
            $quote_id = IDLM_Quotation_Repository::insert_quote(array(
                'lead_id' => $lead_id,
                'quote_number' => $number,
                'title' => trim((string) ($lead->project_name ?? '')) ?: 'Interior Design Proposal',
                'status' => 'Draft',
                'owner_user_id' => absint($lead->assigned_rep_id ?? $user_id),
                'created_by' => $user_id,
                'save_request_key' => $save_request_key,
                'created_at' => $now,
                'updated_at' => $now,
            ), array('%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s'));
            if (!$quote_id) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0053: handle_save(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
                $wpdb->query('ROLLBACK');
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                IDLM_Quotation_Repository::release_lead_quote_lock($lead_id);
                self::redirect(array('idlm_error' => 'Unable to create the quotation.'));
            }
            $revision_number = 1;
            $revision_name = $number . '-R01';
        } else {
            $quote = IDLM_Quotation_Repository::lock_quote($quote_id);
            if (!$quote || absint($quote->lead_id) !== $lead_id || !IDLM_Quotation_Policy::can_mutate_lead($lead_id)) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0054: handle_save(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
                $wpdb->query('ROLLBACK');
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                wp_die('Quotation not found.', 404);
            }
            $revision = self::get_revision($revision_id);
            if (!IDLM_Quotation_Policy::is_editable_draft($quote, $revision)) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0055: handle_save(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
                $wpdb->query('ROLLBACK');
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                self::redirect(array('action' => 'view', 'quote_id' => $quote_id, 'idlm_error' => 'Only the current draft quotation revision can be edited.'));
            }
            $source_revision_id = absint($revision->id);
            $revision_number = IDLM_Quotation_Repository::next_revision_number($quote_id);
            $revision_name = $quote->quote_number . '-R' . str_pad((string) $revision_number, 2, '0', STR_PAD_LEFT);
            $revision_id = 0;
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
            'currency_code' => $creating_quote ? self::currency_code($quote_settings['currency_code']) : (string) $revision->currency_code,
            'document_terms' => $document_terms,
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

        $revision_data['created_by'] = $user_id;
        $revision_data['created_at'] = $now;
        $revision_id = IDLM_Quotation_Repository::insert_revision($revision_data);
        $ok = (bool) $revision_id;
        $quote_updated = $ok ? IDLM_Quotation_Repository::set_current_revision($quote_id, $revision_id, 'Draft', $now) : false;
        if (false === $quote_updated) {
            self::rollback_save('Unable to set the current quotation revision.', array('action' => 'edit', 'quote_id' => $quote_id), 'Current revision pointer update failed');
        }
        if (false === $ok) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0056: handle_save(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0057: handle_save(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0058: handle_save(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
        if (false === $wpdb->query('COMMIT')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            self::rollback_save('Unable to commit the quotation changes.', array('action' => 'edit', 'quote_id' => $quote_id), 'Quotation transaction commit failed');
        }
        if ($creating_quote) {
            IDLM_Quotation_Repository::release_lead_quote_lock($lead_id);
        }
        IDLM_Read_Model_Cache::invalidate('quotation_value');
        delete_transient(self::autosave_key(!empty($source_revision_id) ? $source_revision_id : $revision_id));
        self::log_quote_activity($quote_id, $revision_id, 'Quotation ' . $revision_name . ' saved.');
        self::redirect(array('action' => 'view', 'quote_id' => $quote_id, 'idlm_notice' => $creating_quote ? 'Quotation saved.' : 'New revision saved.'));
    }

    public static function ajax_autosave_quote() {
        check_ajax_referer('idlm_quote_autosave', 'nonce');
        global $wpdb;
        if (!self::can('idlm_edit_quote_drafts') || !self::can('idlm_create_quote_revisions')) {
            wp_send_json_error(array('message' => 'You do not have permission to autosave quotation drafts.'), 403);
        }
        $quote_id = absint($_POST['quote_id'] ?? 0);
        $revision_id = absint($_POST['revision_id'] ?? 0);
        $lead_id = absint($_POST['lead_id'] ?? 0);
        $quote = self::get_quote($quote_id);
        $transaction = IDLM_Quotation_Repository::begin_checked_transaction('working_copy');
        if (is_wp_error($transaction)) {
            wp_send_json_error(array('message' => $transaction->get_error_message()), 503);
        }
        if (!$quote || !IDLM_Quotation_Policy::can_mutate_lead($quote->lead_id, true) || !self::can('idlm_edit_quote_drafts') || !self::can('idlm_create_quote_revisions')) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0059: ajax_autosave_quote(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            wp_send_json_error(array('message' => 'Only the current quotation revision can be autosaved.'), 409);
        }
        $quote = IDLM_Quotation_Repository::lock_quote($quote_id);
        $revision = self::get_revision($revision_id);
        if (!$quote || !$revision || absint($quote->lead_id) !== $lead_id || !IDLM_Quotation_Policy::is_editable_draft($quote, $revision) || !IDLM_Quotation_Policy::can_mutate_lead($lead_id)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0060: ajax_autosave_quote(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            wp_send_json_error(array('message' => 'Only the current quotation revision can be autosaved.'), 409);
        }
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0014: ajax_autosave_quote(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $items = self::parse_items(wp_unslash((array) ($_POST['items'] ?? array())), self::settings());
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (is_wp_error($items)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0061: ajax_autosave_quote(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            wp_send_json_error(array('message' => $items->get_error_message()), 422);
        }
        $line_items = array_filter($items, static function($item) {
            return 'item' === ($item['row_type'] ?? 'item');
        });
        if (!$line_items) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0062: ajax_autosave_quote(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            wp_send_json_error(array('message' => 'Autosave is waiting for at least one complete line item.'), 422);
        }
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0015: ajax_autosave_quote(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $sections = self::parse_sections(wp_unslash($_POST['sections'] ?? array()));
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (is_wp_error($sections)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0063: ajax_autosave_quote(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            wp_send_json_error(array('message' => $sections->get_error_message()), 422);
        }
        $totals = self::calculate_totals($items, $_POST, self::settings());
        if (is_wp_error($totals)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0064: ajax_autosave_quote(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            wp_send_json_error(array('message' => $totals->get_error_message()), 422);
        }
        $document_terms = IDLM_Quotation_Document::capture(self::settings(), $totals['grand_total']);
        if (is_wp_error($document_terms)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0065: ajax_autosave_quote(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            wp_send_json_error(array('message' => $document_terms->get_error_message()), 422);
        }
        $revision_data = array_merge(array(
            'currency_code' => (string) $revision->currency_code,
            'document_terms' => $document_terms,
            'customer_name' => sanitize_text_field(wp_unslash($_POST['customer_name'] ?? '')),
            'customer_phone' => sanitize_text_field(wp_unslash($_POST['customer_phone'] ?? '')),
            'customer_email' => sanitize_email(wp_unslash($_POST['customer_email'] ?? '')),
            'project_name' => sanitize_text_field(wp_unslash($_POST['project_name'] ?? '')),
            'property_location' => sanitize_textarea_field(wp_unslash($_POST['property_location'] ?? '')),
            'internal_notes' => sanitize_textarea_field(wp_unslash($_POST['internal_notes'] ?? '')),
        ), $totals);
        $saved_at = time();
        $working_copy = array(
            'generation' => IDLM_Recovery_Guard::generation(),
            'revision' => $revision_data,
            'sections' => $sections,
            'items' => $items,
            'saved_at' => $saved_at,
        );
        $autosave_key = self::autosave_key($revision_id);
        $stored = set_transient($autosave_key, $working_copy, 7 * DAY_IN_SECONDS);
        if (!$stored && false === get_transient($autosave_key)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0066: ajax_autosave_quote(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            wp_send_json_error(array('message' => 'Unable to store the quotation working copy.'), 500);
        }
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0067: ajax_autosave_quote(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
        if (false === $wpdb->query('COMMIT')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Unable to commit quotation working copy.'), 500);
        }
        wp_send_json_success(array('message' => 'Working copy autosaved.', 'savedAt' => wp_date('H:i:s', $saved_at), 'grandTotal' => $totals['grand_total']));
    }

    public static function ajax_discard_quote_autosave() {
        check_ajax_referer('idlm_quote_autosave', 'nonce');
        global $wpdb;
        if (!self::can('idlm_edit_quote_drafts') || !self::can('idlm_create_quote_revisions')) {
            wp_send_json_error(array('message' => 'You do not have permission to discard quotation working copies.'), 403);
        }
        $quote_id = absint($_POST['quote_id'] ?? 0);
        $revision_id = absint($_POST['revision_id'] ?? 0);
        $quote = self::get_quote($quote_id);
        $transaction = IDLM_Quotation_Repository::begin_checked_transaction('working_copy');
        if (is_wp_error($transaction)) {
            wp_send_json_error(array('message' => $transaction->get_error_message()), 503);
        }
        if (!$quote || !IDLM_Quotation_Policy::can_mutate_lead($quote->lead_id, true) || !self::can('idlm_edit_quote_drafts') || !self::can('idlm_create_quote_revisions')) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0068: ajax_discard_quote_autosave(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            wp_send_json_error(array('message' => 'Quotation working copy not found.'), 404);
        }
        $quote = IDLM_Quotation_Repository::lock_quote($quote_id);
        $revision = self::get_revision($revision_id);
        if (!$quote || !$revision || !IDLM_Quotation_Policy::is_editable_draft($quote, $revision) || !IDLM_Quotation_Policy::can_mutate_lead($quote->lead_id)) {
            // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0069: ajax_discard_quote_autosave(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
            $wpdb->query('ROLLBACK');
            // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            wp_send_json_error(array('message' => 'Quotation working copy not found.'), 404);
        }
        delete_transient(self::autosave_key($revision_id));
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0070: ajax_discard_quote_autosave(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
        if (false === $wpdb->query('COMMIT')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => 'Unable to commit quotation working copy.'), 500);
        }
        wp_send_json_success(array('message' => 'Working copy discarded.'));
    }

    /** A UUID retry belongs to the actor, lead, quote and source revision. */
    private static function completed_save($saved, $lead_id, $quote_id, $revision_id, $request_key) {
        $quote = self::get_quote($saved->quote_id);
        $matches = $quote && absint($quote->lead_id) === $lead_id &&
            absint($saved->created_by) === get_current_user_id() &&
            IDLM_Quotation_Policy::can_mutate_lead($lead_id);
        if ($quote_id) {
            $matches = $matches && absint($saved->quote_id) === $quote_id && absint($saved->source_revision_id) === $revision_id;
        } else {
            $matches = $matches && !$revision_id && !absint($saved->source_revision_id) && (string) $quote->save_request_key === $request_key;
        }
        if (!$matches) {
            wp_die('Invalid quotation save request.', 403);
        }
        self::redirect(array('action' => 'view', 'quote_id' => absint($quote->id), 'idlm_notice' => 'This quotation save was already completed.'));
    }

    private static function clean_date($value) {
        $value = sanitize_text_field($value);
        $date = DateTime::createFromFormat('Y-m-d', $value, wp_timezone());
        return $date && $date->format('Y-m-d') === $value ? $value : null;
    }

    /*
     * Legacy final-state revision entry points intentionally have no handler.
     * Revisions are created exclusively by handle_save() from the current draft.
     */
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
            'search' => sanitize_text_field(wp_unslash($_GET['quote_search'] ?? '')),
            'created_by' => absint(wp_unslash($_GET['created_by'] ?? 0)),
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0016: ajax_filter_quotation_list(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
            'date_from' => self::list_filter_date(wp_unslash($_GET['date_from'] ?? '')),
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0017: ajax_filter_quotation_list(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
            'date_to' => self::list_filter_date(wp_unslash($_GET['date_to'] ?? '')),
            // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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

    private static function currency_code($value) {
        $currency = strtoupper(sanitize_key($value));
        return in_array($currency, array('INR', 'USD', 'EUR', 'GBP', 'AED', 'AUD', 'CAD', 'SGD', 'JPY'), true) ? $currency : 'INR';
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
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0018: handle_save_catalogue_item(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $rate = IDLM_Quotation_Calculator::decimal_units(wp_unslash($_POST['default_rate'] ?? 0), 0, IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS, 'Catalogue rate');
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (is_wp_error($rate)) { self::redirect(array('workspace' => 'catalogue', 'idlm_error' => $rate->get_error_message())); }
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0019: handle_save_catalogue_item(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $is_wood = wp_unslash($_POST['is_wood'] ?? '0');
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (!is_string($is_wood) || !in_array($is_wood, array('0', '1'), true)) {
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Choose a valid wood classification.'));
        }
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
            'is_wood' => '1' === $is_wood ? 1 : 0,
            'default_rate' => (float) $rate / 100,
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
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0020: handle_import_catalogue_csv(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $upload = $_FILES['catalogue_csv'] ?? array();
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
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
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- REL007-BOUNDARY-0021: handle_import_catalogue_csv(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $handle = fopen($upload['tmp_name'], 'r');
        // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        if (!$handle) {
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Unable to read the CSV file.'));
        }
        $headers = array_map(static function($value) { return sanitize_key(str_replace(array(' ', '-'), '_', (string) $value)); }, (array) fgetcsv($handle));
        $required = array('item_code', 'item_name');
        if (array_diff($required, $headers) || count($headers) !== count(array_unique($headers)) || count($headers) > 20) {
            // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0022: handle_import_catalogue_csv(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
            fclose($handle);
            // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'CSV requires unique item_code and item_name headers and no more than 20 columns.'));
        }
        global $wpdb;
        $valid = 0;
        $errors = 0;
        $duplicates = 0;
        $row_count = 0;
        $transaction = IDLM_Quotation_Repository::begin_checked_transaction('catalogue');
        if (is_wp_error($transaction)) {
            // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0023: handle_import_catalogue_csv(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
            fclose($handle);
            // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
            self::redirect(array('workspace' => 'catalogue', 'idlm_error' => $transaction->get_error_message()));
        }
        while (($values = fgetcsv($handle)) !== false) {
            $row_count++;
            if ($row_count > 2000) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0071: handle_import_catalogue_csv(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
                $wpdb->query('ROLLBACK');
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0024: handle_import_catalogue_csv(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
                fclose($handle);
                // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
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
            $rate = IDLM_Quotation_Calculator::decimal_units($row['default_rate'] ?? 0, 0, IDLM_Quotation_Calculator::MAX_AMOUNT_UNITS, 'Catalogue rate');
            if (is_wp_error($rate)) { $errors++; continue; }
            $now = current_time('mysql');
            if (false === IDLM_Quotation_Repository::ensure_catalogue_term('category', $category, get_current_user_id(), $now) || false === IDLM_Quotation_Repository::ensure_catalogue_term('unit', $unit, get_current_user_id(), $now)) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0072: handle_import_catalogue_csv(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
                $wpdb->query('ROLLBACK');
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0025: handle_import_catalogue_csv(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
                fclose($handle);
                // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
                self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Catalogue import failed and was rolled back.'));
            }
            $ok = IDLM_Quotation_Repository::insert_catalogue_item(array(
                'item_code' => $code, 'category' => $category, 'item_name' => $name,
                'description' => sanitize_textarea_field($row['description'] ?? ''), 'unit' => $unit,
                'is_wood' => in_array(strtolower(trim((string) ($row['is_wood'] ?? 'no'))), array('yes', '1', 'true'), true) ? 1 : 0,
                'default_rate' => (float) $rate / 100,
                'status' => 'active', 'created_by' => get_current_user_id(), 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
            ));
            if (!$ok) {
                // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0073: handle_import_catalogue_csv(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
                $wpdb->query('ROLLBACK');
                // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0026: handle_import_catalogue_csv(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
                fclose($handle);
                // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
                self::redirect(array('workspace' => 'catalogue', 'idlm_error' => 'Catalogue import failed and was rolled back.'));
            }
            $valid++;
        }
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0027: handle_import_catalogue_csv(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        fclose($handle);
        // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- REL007-SQL-0074: handle_import_catalogue_csv(): literal transaction control in guarded quotation save/autosave/catalogue handlers; row writes are escaped by wpdb CRUD and checked under the owning transaction; no per-query cache is appropriate.
        if (false === $wpdb->query('COMMIT')) {
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- REL007-BOUNDARY-0028: handle_export_catalogue_csv(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        fclose($output);
        // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fclose
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
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0029: handle_save_settings(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $settings['paper_size'] = self::normalize_paper_size(wp_unslash($_POST['paper_size'] ?? 'A4'));
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $settings['cover_page_enabled'] = !empty($_POST['cover_page_enabled']) ? 1 : 0;
        $settings['cover_page_html'] = wp_kses_post(wp_unslash($_POST['cover_page_html'] ?? ''));
        $settings['after_quote_enabled'] = !empty($_POST['after_quote_enabled']) ? 1 : 0;
        $settings['after_quote_html'] = wp_kses_post(wp_unslash($_POST['after_quote_html'] ?? ''));
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0030: handle_save_settings(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $soft_close = IDLM_Quotation_Calculator::decimal_units(wp_unslash($_POST['soft_close_percent'] ?? 0), 0, 10000, 'Soft close percentage');
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (is_wp_error($soft_close)) { self::redirect(array('workspace' => 'settings', 'idlm_error' => $soft_close->get_error_message())); }
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0031: handle_save_settings(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $payment_rows = wp_unslash($_POST['payment_structure'] ?? array());
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if (!is_array($payment_rows) || count($payment_rows) > 50) { self::redirect(array('workspace' => 'settings', 'idlm_error' => 'Payment structure supports at most 50 milestones.')); }
        $payment_structure = array();
        $payment_total_units = 0;
        foreach ($payment_rows as $payment_row) {
            if (!is_array($payment_row) || !is_string($payment_row['name'] ?? '')) { self::redirect(array('workspace' => 'settings', 'idlm_error' => 'Invalid payment milestone.')); }
            $name = sanitize_text_field($payment_row['name'] ?? '');
            $percentage = IDLM_Quotation_Calculator::decimal_units($payment_row['percentage'] ?? 0, 0, 10000, 'Milestone percentage');
            if (is_wp_error($percentage)) { self::redirect(array('workspace' => 'settings', 'idlm_error' => $percentage->get_error_message())); }
            if ('' !== $name && $percentage > 0) {
                $payment_total_units += $percentage;
                $payment_structure[] = array('name' => $name, 'percentage' => (float) $percentage / 100);
            }
        }
        if ($payment_total_units > 10000) {
            self::redirect(array('workspace' => 'settings', 'idlm_error' => 'Payment milestones cannot total more than 100%.'));
        }
        $settings['payment_structure'] = wp_json_encode($payment_structure);
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0032: handle_save_settings(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $settings['currency_code'] = self::currency_code(wp_unslash($_POST['currency_code'] ?? $settings['currency_code']));
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $settings['soft_close_percent'] = (float) $soft_close / 100;
        $settings['number_prefix'] = preg_replace('/[^A-Za-z0-9_-]/', '', sanitize_text_field(wp_unslash($_POST['number_prefix'] ?? 'Q')));
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- REL007-BOUNDARY-0033: handle_save_settings(): each mutation checks its quotation capability and dedicated nonce first; item/section arrays delegate to bounded calculator sanitization, decimal/enum settings to strict validators, and uploads to size/extension/MIME/is_uploaded_file checks; native CSV handles require streaming IO.
        $number_separator = wp_unslash($_POST['number_separator'] ?? '');
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $settings['number_separator'] = in_array($number_separator, array('-', '/', '_'), true) ? $number_separator : '-';
        $settings['number_padding'] = min(10, max(3, absint($_POST['number_padding'] ?? 6)));
        unset($settings['category_discounts'], $settings['per_item_tax_enabled'], $settings['global_tax_enabled'], $settings['global_tax_type'], $settings['global_tax_rate']);
        update_option('idlm_quote_settings', $settings, false);
        self::redirect(array('workspace' => 'settings', 'idlm_notice' => 'Quotation settings saved.'));
    }


}
