<?php
/** Requests and panels for design stages and documents. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }

final class IDLM_Design_Stage_Controller {
    public static function init() {
        add_action('admin_post_idlm_design_stage_action', array(__CLASS__, 'handle_action'));
        add_action('admin_post_idlm_design_doc_upload', array(__CLASS__, 'handle_upload'));
        add_action('admin_post_idlm_design_doc_archive', array(__CLASS__, 'handle_archive'));
        add_action('admin_post_idlm_design_stage_settings', array(__CLASS__, 'handle_settings'));
    }

    // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    private static function text($key) { return isset($_POST[$key]) && is_string($_POST[$key]) ? wp_unslash($_POST[$key]) : ''; }
    // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    private static function base() {
        $fallback = add_query_arg('idlm_view', IDLM_Design_Handoff_Controller::VIEW, IDLM_Admin_Shortcodes::workspace_url());
        return wp_validate_redirect(sanitize_text_field(self::text('return_url')), $fallback);
    }
    private static function finish($result, $handoff_id, $notice) {
        if (is_wp_error($result) || false === $result) { wp_die(esc_html(is_wp_error($result) ? $result->get_error_message() : 'The CRM is busy. Try again shortly.'), 'Not saved', array('response' => 422, 'back_link' => true)); }
        wp_safe_redirect(add_query_arg(array('handoff' => $handoff_id, 'design_notice' => $notice), self::base())); exit;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Missing -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    public static function handle_action() {
        check_admin_referer('idlm_design_stage_action');
        $id = absint($_POST['handoff_id'] ?? 0); $action = sanitize_key(self::text('stage_action')); $reason = self::text('reason');
        if (!IDLM_Design_Handoffs::can_see(IDLM_Design_Handoffs::find($id))) { wp_die('You do not have permission to do this on this project.', '', array('response' => 403)); }
        $resume = sanitize_text_field(self::text('resume_date'));
        $notices = array('advance' => 'stage', 'back' => 'stage', 'hold' => 'hold', 'resume' => 'resume', 'complete' => 'completed', 'reopen' => 'reopened', 'clear' => 'cleared');
        if (!isset($notices[$action])) { wp_die('Invalid request.', '', array('response' => 400)); }
        // The hold dialog sends a picked reason and an optional note; older forms send the reason as text.
        if ('hold' === $action && isset($_POST['hold_choice'])) {
            $reason = IDLM_Design_Stages::hold_reason_text(self::text('hold_choice'), self::text('reason'));
            if (is_wp_error($reason)) { self::finish($reason, $id, 'hold'); }
        }
        $result = IDLM_Recovery_Guard::run(static function() use ($action, $id, $reason, $resume) {
            switch ($action) {
                case 'advance': return IDLM_Design_Stages::advance($id, sanitize_key(wp_unslash($_POST['from_stage'] ?? '')));
                case 'back': return IDLM_Design_Stages::move_back($id, $reason);
                case 'hold': return IDLM_Design_Stages::hold($id, $reason, $resume);
                case 'resume': return IDLM_Design_Stages::resume($id);
                case 'complete': return IDLM_Design_Stages::complete($id);
                case 'clear': return IDLM_Design_Stages::clear_flags($id, $reason);
                default: return IDLM_Design_Stages::reopen($id, $reason);
            }
        });
        self::finish($result, $id, $notices[$action]);
    }
    // phpcs:enable WordPress.Security.NonceVerification.Missing

    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    public static function handle_upload() {
        check_admin_referer('idlm_design_doc_upload');
        $id = absint($_POST['handoff_id'] ?? 0); $row = IDLM_Design_Handoffs::find($id);
        if (!$row || !IDLM_Design_Revisions::can_edit($row)) { wp_die('You do not have permission to do this on this project.', '', array('response' => 403)); }
        $slot = sanitize_key(self::text('slot_key')); $title = self::text('title');
        $file = $_FILES['design_file'] ?? null;
        $result = IDLM_Recovery_Guard::run(static function() use ($row, $id, $slot, $title, $file) {
            $allowed = IDLM_Design_Stages::allowed_extensions($slot);
            if (!$allowed) { return new WP_Error('idlm_design_stage_slot', 'Choose a valid document type.'); }
            // Check the type before storing anything, so a wrong file leaves nothing behind.
            $name = is_array($file) && is_string($file['name'] ?? null) ? $file['name'] : '';
            if (!in_array(strtolower((string) pathinfo($name, PATHINFO_EXTENSION)), $allowed, true)) { return new WP_Error('idlm_design_stage_type', 'This document must be one of: ' . strtoupper(implode(', ', $allowed)) . '.'); }
            $upload = IDLM_Private_Files::upload($file, (int) $row->lead_id, 'design');
            if (is_wp_error($upload)) { return $upload; }
            return IDLM_Design_Stages::add_document($id, $slot, $title, (int) $upload['attachment_id']);
        });
        self::finish($result, $id, 'document');
    }
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

    public static function handle_archive() {
        check_admin_referer('idlm_design_doc_archive');
        $id = absint($_POST['handoff_id'] ?? 0); $version = absint($_POST['version_id'] ?? 0);
        if (!IDLM_Design_Handoffs::can_decide(IDLM_Design_Handoffs::find($id))) { wp_die('You do not have permission to do this on this project.', '', array('response' => 403)); }
        $result = IDLM_Recovery_Guard::run(static function() use ($id, $version) { return IDLM_Design_Stages::archive_document($id, $version); });
        self::finish($result, $id, 'archived');
    }

    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    public static function handle_settings() {
        check_admin_referer('idlm_design_stage_settings');
        if (!current_user_can('manage_options')) { wp_die('Only an administrator can change design stages.', '', array('response' => 403)); }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field against fixed keys in save_settings().
        $result = IDLM_Design_Stages::save_settings(isset($_POST['stages']) && is_array($_POST['stages']) ? $_POST['stages'] : array(), isset($_POST['documents']) && is_array($_POST['documents']) ? $_POST['documents'] : array(), self::text('settings_fingerprint'), isset($_POST['hold_reasons']) ? self::text('hold_reasons') : null);
        if (is_wp_error($result)) { wp_die(esc_html($result->get_error_message()), 'Not saved', array('response' => 422, 'back_link' => true)); }
        wp_safe_redirect(add_query_arg(array('design_settings' => '1', 'design_notice' => 'settings'), self::base())); exit;
    }
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.MissingUnslash

    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    private static function fields($action, $row) {
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '"><input type="hidden" name="handoff_id" value="' . esc_attr($row->id) . '"><input type="hidden" name="return_url" value="' . esc_attr(remove_query_arg(array('design_notice', 'design_error', 'handoff'), wp_unslash($_SERVER['REQUEST_URI'] ?? ''))) . '">';
        wp_nonce_field($action);
    }
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    private static function open_form($action, $row, $class = '', $multipart = false) {
        echo '<form class="' . esc_attr($class) . '" method="post"' . ($multipart ? ' enctype="multipart/form-data"' : '') . ' action="' . esc_url(admin_url('admin-post.php')) . '">';
        self::fields($action, $row);
    }
    /** A button that opens a dialog holding a real form. The form posts as before. */
    public static function dialog_button($id, $label) {
        return '<button type="button" class="idlm-btn idlm-btn-secondary" data-design-dialog="' . esc_attr($id) . '" aria-haspopup="dialog">' . esc_html($label) . '</button>';
    }
    public static function dialog_open($id, $title, $description, $action, $row, $multipart = false) {
        wp_enqueue_script('idlm-design-detail', IDLM_URL . 'assets/js/design-detail.js', array('idlm-dialog'), (string) filemtime(IDLM_PATH . 'assets/js/design-detail.js'), true);
        echo '<div class="idlm-modal idlm-dialog idlm-design-dialog" id="' . esc_attr($id) . '" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr($id . '-title') . '"' . ('' !== $description ? ' aria-describedby="' . esc_attr($id . '-description') . '"' : '') . ' aria-hidden="true" hidden data-idlm-dialog data-idlm-initial-focus=".idlm-dialog__body :is(textarea, select, input:not([type=hidden]))"><div class="idlm-modal-content idlm-dialog__panel"><header class="idlm-modal-header idlm-dialog__header"><div><h2 class="idlm-dialog__title" id="' . esc_attr($id . '-title') . '">' . esc_html($title) . '</h2>' . ('' !== $description ? '<p class="idlm-dialog__description" id="' . esc_attr($id . '-description') . '">' . esc_html($description) . '</p>' : '') . '</div><button type="button" class="idlm-dialog__close" data-idlm-dialog-close aria-label="Close dialog"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button></header>';
        self::open_form($action, $row, 'idlm-design-dialog-form', $multipart);
        echo '<div class="idlm-dialog__body">';
    }
    public static function dialog_close($submit) {
        echo '</div><footer class="idlm-dialog__footer"><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-dialog-close>Cancel</button><button class="idlm-btn idlm-btn-primary">' . esc_html($submit) . '</button></footer></form></div></div>';
    }
    private static function reason_field($id, $label, $placeholder = '') {
        echo '<div class="idlm-form-group"><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label><textarea class="idlm-input" id="' . esc_attr($id) . '" name="reason" rows="3" maxlength="2000" required' . ('' !== $placeholder ? ' placeholder="' . esc_attr($placeholder) . '"' : '') . '></textarea></div>';
    }

    private static function accept_attribute($slot) {
        $types = array('pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'dwg' => '.dwg');
        return implode(',', array_unique(array_intersect_key($types, array_flip(IDLM_Design_Stages::allowed_extensions($slot)))));
    }
    private static function upload_form($row, $slot, $label) {
        self::open_form('idlm_design_doc_upload', $row, 'idlm-design-upload', true);
        $id = 'idlm-design-file-' . $slot;
        echo '<input type="hidden" name="slot_key" value="' . esc_attr($slot) . '"><label class="screen-reader-text" for="' . esc_attr($id) . '">' . esc_html('Choose file for ' . $label) . '</label><input class="idlm-input" type="file" id="' . esc_attr($id) . '" name="design_file" accept="' . esc_attr(self::accept_attribute($slot)) . '" required><button class="idlm-btn idlm-btn-secondary">Upload</button></form>';
    }

    private static function person($user_id) { $user = get_userdata((int) $user_id); return $user ? $user->display_name : 'Team member'; }
    private static function date($value) { return $value ? mysql2date('d M Y', $value) : ''; }

    /** Where the project is, what is needed to move on, and the move itself. */
    public static function render_stage_panel($row) {
        if (IDLM_Design_Handoffs::STATUS_ACCEPTED !== $row->status) { return; }
        $current = IDLM_Design_Stages::stage_of($row); $stages = IDLM_Design_Stages::shown(array($current)); $keys = array_keys($stages); $index = array_search($current, $keys, true);
        $can_work = IDLM_Design_Revisions::can_edit($row); $can_manage = IDLM_Design_Handoffs::can_decide($row);
        echo '<section class="idlm-card idlm-design-panel idlm-design-stage"><h2>Stage</h2><ol class="idlm-design-steps">';
        foreach ($keys as $position => $key) {
            $state = $position < $index || $row->completed_at ? 'Done' : ($key === $current ? 'Now' : 'Next');
            echo '<li class="is-' . esc_attr(strtolower($state)) . '"' . ($key === $current && !$row->completed_at ? ' aria-current="step"' : '') . '><span>' . esc_html($stages[$key]['label']) . '</span><small>' . esc_html($state) . '</small></li>';
        }
        echo '</ol>';
        if ($row->completed_at) { echo '<p class="idlm-design-note"><span class="idlm-badge is-success">Complete</span> Completed by ' . esc_html(self::person($row->completed_by)) . ' on ' . esc_html(self::date($row->completed_at)) . '.</p>'; }
        if ($row->hold_at) { echo '<p class="idlm-design-note"><span class="idlm-badge is-warning">On hold</span> ' . esc_html($row->hold_reason) . ($row->hold_resume_date ? ' Expected back ' . esc_html(self::date($row->hold_resume_date)) . '.' : '') . '</p>'; }
        $flags = IDLM_Design_Stages::open_flags($row);
        foreach ($flags as $flag) { echo '<p class="idlm-design-note"><span class="idlm-badge is-warning">Review</span> ' . esc_html($flag . ' no longer holds.') . '</p>'; }
        $clear = $can_manage && $flags;
        $items = IDLM_Design_Stages::checklist($row); $missing = IDLM_Design_Stages::unmet($items);
        if ($items && !$row->completed_at) {
            echo '<h3>Needed before leaving ' . esc_html($stages[$current]['label']) . '</h3><ul class="idlm-design-checklist">';
            foreach ($items as $item) {
                echo '<li><div class="idlm-design-check"><span class="idlm-badge ' . ($item['met'] ? 'is-success">Done' : 'is-neutral">Needed') . '</span> <strong>' . esc_html($item['label']) . '</strong>' . ($item['detail'] ? ' <small>' . esc_html($item['detail']) . '</small>' : '') . '</div>';
                if ('document' === $item['type']) {
                    if ($item['document']) { echo '<a href="' . esc_url(IDLM_Private_Files::url((int) $item['document']->attachment_id)) . '" target="_blank" rel="noopener">' . esc_html('Version ' . $item['document']->version_no) . '</a> '; }
                    if ($can_work) { self::upload_form($row, $item['key'], $item['label']); }
                }
                echo '</li>';
            }
            echo '</ul>';
        }
        $next = IDLM_Design_Stages::neighbour($current, 1);
        $hold = $can_work && !$row->hold_at; $back = $can_manage && !$row->completed_at && IDLM_Design_Stages::neighbour($current, -1); $reopen = $can_manage && $row->completed_at;
        echo '<div class="idlm-design-quote-actions">';
        if ($can_work && $next && !$row->hold_at && !$row->completed_at) {
            self::open_form('idlm_design_stage_action', $row);
            echo '<input type="hidden" name="stage_action" value="advance"><button class="idlm-btn idlm-btn-primary"' . disabled((bool) $missing, true, false) . '>' . esc_html('Move to ' . $stages[$next]['label']) . '</button></form>';
        }
        if ($can_manage && !$next && !$row->hold_at && !$row->completed_at) {
            self::open_form('idlm_design_stage_action', $row);
            echo '<input type="hidden" name="stage_action" value="complete"><button class="idlm-btn idlm-btn-primary"' . disabled((bool) $missing, true, false) . '>Mark project complete</button></form>';
        }
        if ($can_work && $row->hold_at) {
            self::open_form('idlm_design_stage_action', $row);
            echo '<input type="hidden" name="stage_action" value="resume"><button class="idlm-btn idlm-btn-primary">Resume project</button></form>';
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if ($hold) { echo self::dialog_button('idlm-design-hold-dialog', 'Put on hold'); }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if ($back) { echo self::dialog_button('idlm-design-back-dialog', 'Move back a stage'); }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if ($reopen) { echo self::dialog_button('idlm-design-reopen-dialog', 'Reopen project'); }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
        if ($clear) { echo self::dialog_button('idlm-design-clear-dialog', 'Clear review flag'); }
        echo '</div>';
        if ($hold) {
            self::dialog_open('idlm-design-hold-dialog', 'Put on hold', 'Stage moves stop until the project is resumed.', 'idlm_design_stage_action', $row);
            echo '<input type="hidden" name="stage_action" value="hold"><div class="idlm-form-group"><label for="idlm-design-hold-choice">Reason</label><select class="idlm-input" id="idlm-design-hold-choice" name="hold_choice" required><option value="">Choose a reason</option>';
            foreach (IDLM_Design_Stages::hold_reasons() as $choice) { echo '<option value="' . esc_attr($choice) . '">' . esc_html($choice) . '</option>'; }
            echo '<option value="' . esc_attr(IDLM_Design_Stages::HOLD_OTHER) . '">' . esc_html(IDLM_Design_Stages::HOLD_OTHER) . '</option></select></div>';
            echo '<div class="idlm-form-group"><label for="idlm-design-hold-reason">Details</label><textarea class="idlm-input" id="idlm-design-hold-reason" name="reason" rows="3" maxlength="2000" aria-describedby="idlm-design-hold-reason-help"></textarea><small id="idlm-design-hold-reason-help">Optional, unless you choose Other.</small></div>';
            echo '<div class="idlm-form-group"><label for="idlm-design-hold-date">Expected back (optional)</label><input class="idlm-input" type="date" id="idlm-design-hold-date" name="resume_date"></div>';
            self::dialog_close('Put on hold');
        }
        if ($back) {
            $previous = IDLM_Design_Stages::neighbour($current, -1);
            self::dialog_open('idlm-design-back-dialog', 'Move back a stage', 'The project returns to ' . $stages[$previous]['label'] . '. Uploaded documents stay.', 'idlm_design_stage_action', $row);
            echo '<input type="hidden" name="stage_action" value="back">'; self::reason_field('idlm-design-back-reason', 'Reason');
            self::dialog_close('Move back');
        }
        if ($clear) {
            self::dialog_open('idlm-design-clear-dialog', 'Clear review flag', 'The project stays where it is. The flag comes back if a payment or document changes again.', 'idlm_design_stage_action', $row);
            echo '<input type="hidden" name="stage_action" value="clear">'; self::reason_field('idlm-design-clear-reason', 'Note', 'For example, the customer pays the balance at Kick Off');
            self::dialog_close('Clear flag');
        }
        if ($reopen) {
            self::dialog_open('idlm-design-reopen-dialog', 'Reopen project', 'The project becomes active again at its last stage.', 'idlm_design_stage_action', $row);
            echo '<input type="hidden" name="stage_action" value="reopen">'; self::reason_field('idlm-design-reopen-reason', 'Reason');
            self::dialog_close('Reopen');
        }
        echo '</section>';
    }

    /** Every design file, kept apart from the lead's own documents. */
    public static function render_documents_panel($row) {
        if (IDLM_Design_Handoffs::STATUS_ACCEPTED !== $row->status) { return; }
        $all = IDLM_Design_Stages::documents($row->id, true); $can_work = IDLM_Design_Revisions::can_edit($row); $can_manage = IDLM_Design_Handoffs::can_decide($row);
        $categories = IDLM_Design_Stages::categories(); $slots = IDLM_Design_Stages::slots();
        echo '<section class="idlm-card idlm-design-panel"><h2>Design documents</h2>';
        if (!$all) { echo '<p class="idlm-design-muted">No design documents yet.</p>'; }
        else {
            echo '<ul class="idlm-design-revisions">';
            foreach ($all as $document) {
                if (IDLM_Design_Stages::STATE_CURRENT !== $document->state) { continue; }
                $group = $slots[$document->slot_key]['label'] ?? ($categories[$document->slot_key] ?? 'Other');
                echo '<li><a href="' . esc_url(IDLM_Private_Files::url((int) $document->attachment_id)) . '" target="_blank" rel="noopener">' . esc_html($document->title) . '</a> <small>' . esc_html($group . ' · Version ' . $document->version_no . ' · ' . self::person($document->uploaded_by) . ' · ' . self::date($document->uploaded_at)) . '</small>';
                $older = array_filter($all, static function($other) use ($document) { return $other->document_key === $document->document_key && (int) $other->id !== (int) $document->id; });
                if ($older) { echo '<details class="idlm-design-versions"><summary>Earlier versions (' . esc_html(count($older)) . ')</summary>'; foreach ($older as $old) { echo '<small><a href="' . esc_url(IDLM_Private_Files::url((int) $old->attachment_id)) . '" target="_blank" rel="noopener">' . esc_html('Version ' . $old->version_no) . '</a> ' . esc_html(self::person($old->uploaded_by) . ' · ' . self::date($old->uploaded_at) . (IDLM_Design_Stages::STATE_ARCHIVED === $old->state ? ' · Archived' : '')) . '</small>'; } echo '</details>'; }
                if ($can_manage) { self::open_form('idlm_design_doc_archive', $row, 'idlm-design-archive'); echo '<input type="hidden" name="version_id" value="' . esc_attr($document->id) . '"><button class="idlm-design-text-button" data-idlm-confirm-submit data-idlm-confirm-title="Archive document" data-idlm-confirm-message="' . esc_attr($document->title . ' leaves the current documents. Its versions stay in the history.') . '" data-idlm-confirm-label="Archive" data-idlm-confirm-destructive="true" aria-label="' . esc_attr('Archive ' . $document->title) . '">Archive</button></form>'; }
                echo '</li>';
            }
            echo '</ul>';
        }
        if ($can_work) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped when built: helpers and variables here concatenate only esc_html/esc_attr/esc_url values, nonce fields and fixed markup. Builder: Shashi.
            echo '<div class="idlm-design-quote-actions">' . self::dialog_button('idlm-design-doc-dialog', 'Add another file') . '</div>';
            self::dialog_open('idlm-design-doc-dialog', 'Add a design file', 'Using the same title as an existing file adds a new version of it.', 'idlm_design_doc_upload', $row, true);
            echo '<div class="idlm-form-group"><label for="idlm-design-doc-category">Type</label><select class="idlm-input" id="idlm-design-doc-category" name="slot_key">';
            foreach ($categories as $key => $label) { echo '<option value="' . esc_attr($key) . '">' . esc_html($label) . '</option>'; }
            echo '</select></div><div class="idlm-form-group"><label for="idlm-design-doc-title">Title</label><input class="idlm-input" id="idlm-design-doc-title" name="title" maxlength="150" required></div><div class="idlm-form-group"><label for="idlm-design-doc-file">File</label><input class="idlm-input" type="file" id="idlm-design-doc-file" name="design_file" accept="application/pdf,image/jpeg,image/png,image/webp,.dwg" required aria-describedby="idlm-design-doc-help"><small id="idlm-design-doc-help">PDF, JPG, PNG, WebP or DWG, up to 10 MB.</small></div>';
            self::dialog_close('Upload');
        }
        echo '</section>';
    }

    // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- POST handlers verify the nonce (check_admin_referer or check_ajax_referer) and capability before acting, reading the action name first only to choose that nonce; GET values are display-only routing, cast or sanitized before use; uploads are validated by IDLM_Private_Files::upload(). Builder: Shashi.
    /** Stages, their order and what each requires, for administrators. */
    public static function render_settings() {
        if (!current_user_can('manage_options')) { return false; }
        wp_enqueue_script('idlm-design-stage-settings', IDLM_URL . 'assets/js/design-stage-settings.js', array(), (string) filemtime(IDLM_PATH . 'assets/js/design-stage-settings.js'), true);
        $back = add_query_arg('idlm_view', IDLM_Design_Handoff_Controller::VIEW, IDLM_Admin_Shortcodes::workspace_url());
        $stages = IDLM_Design_Stages::stages(); $first = IDLM_Design_Stages::first(); $documents = IDLM_Design_Stages::custom_documents();
        echo '<header class="idlm-header"><div><h1>Design stages</h1></div><div class="idlm-header-actions"><a class="idlm-btn idlm-btn-secondary" href="' . esc_url($back) . '">Back to Design team</a></div></header>';
        echo '<p class="idlm-design-muted">Projects move through active stages in this order. Each stage lists what is needed before a project can leave it. Changes apply from now on: projects stay where they are, and past moves keep the names they had. Removing a stage keeps its history; a stage with unfinished projects cannot be removed.</p>';
        echo '<form class="idlm-design-stage-settings" method="post" action="' . esc_url(admin_url('admin-post.php')) . '" data-design-stage-settings><input type="hidden" name="action" value="idlm_design_stage_settings"><input type="hidden" name="settings_fingerprint" value="' . esc_attr(IDLM_Design_Stages::fingerprint()) . '"><input type="hidden" name="return_url" value="' . esc_attr(remove_query_arg(array('design_notice', 'design_settings'), wp_unslash($_SERVER['REQUEST_URI'] ?? ''))) . '">';
        wp_nonce_field('idlm_design_stage_settings');
        echo '<h2 class="idlm-design-settings-heading">Stages</h2><ol class="idlm-design-stage-list" data-stage-list>';
        $index = 0;
        foreach ($stages as $key => $stage) { self::stage_editor($index, $key, $stage, $key === $first, IDLM_Design_Stages::projects_in($key)); $index++; }
        self::stage_editor($index, '', array('label' => '', 'slots' => array(), 'requires' => array('designer' => false, 'payment' => '', 'tentative_quote' => false, 'final_quote' => false), 'archived' => false), false, 0);
        echo '</ol><template data-stage-template>'; self::stage_editor('__i__', '', array('label' => '', 'slots' => array(), 'requires' => array('designer' => false, 'payment' => '', 'tentative_quote' => false, 'final_quote' => false), 'archived' => false), false, 0); echo '</template>';
        echo '<div data-add-stage-slot></div>';
        echo '<h2 class="idlm-design-settings-heading">Documents you added</h2><p class="idlm-design-muted">Built-in documents are always available. Add others here, then save before requiring them on a stage. A document with uploaded files cannot be removed.</p><div class="idlm-design-document-list" data-document-list>';
        $index = 0;
        foreach ($documents as $key => $document) { self::document_editor($index, $key, $document); $index++; }
        self::document_editor($index, '', array('label' => '', 'type' => 'pdf'));
        echo '</div><template data-document-template>'; self::document_editor('__i__', '', array('label' => '', 'type' => 'pdf')); echo '</template>';
        echo '<div data-add-document-slot></div>';
        echo '<h2 class="idlm-design-settings-heading">Hold reasons</h2><p class="idlm-design-muted" id="idlm-hold-reasons-help">' . esc_html('Always offered: ' . implode(', ', IDLM_Design_Stages::HOLD_REASONS) . ', and Other. Add your own below, one per line. Removing one here never changes a project already on hold.') . '</p><div class="idlm-form-group idlm-design-hold-reasons-field"><label for="idlm-hold-reasons">Your hold reasons</label><textarea class="idlm-input" id="idlm-hold-reasons" name="hold_reasons" rows="4" aria-describedby="idlm-hold-reasons-help">' . esc_textarea(implode("\n", IDLM_Design_Stages::custom_hold_reasons())) . '</textarea></div>';
        echo '<div class="idlm-design-settings-actions"><button class="idlm-btn idlm-btn-primary">Save settings</button></div></form>';
        return true;
    }
    // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

    /** One stage card. A blank key is a new stage; a blank new name is ignored on save. */
    private static function stage_editor($index, $key, $stage, $is_first, $projects) {
        $name = 'stages[' . $index . ']'; $id = 'idlm-stage-' . $index; $new = '' === $key; $requires = $stage['requires'];
        echo '<li class="idlm-card idlm-design-stage-editor' . ($stage['archived'] ? ' is-archived' : '') . '" data-stage-row><input type="hidden" name="' . esc_attr($name . '[key]') . '" value="' . esc_attr($key) . '">';
        echo '<div class="idlm-design-stage-editor-head"><div class="idlm-form-group idlm-design-stage-position"><label for="' . esc_attr($id . '-position') . '">Order</label><input class="idlm-input" type="number" min="1" max="99" step="1" id="' . esc_attr($id . '-position') . '" name="' . esc_attr($name . '[position]') . '" value="' . esc_attr(is_int($index) ? $index + 1 : '') . '" data-stage-position></div>';
        echo '<div class="idlm-form-group idlm-design-stage-name"><label for="' . esc_attr($id . '-label') . '">' . ($new ? 'New stage name' : 'Stage name') . '</label><input class="idlm-input" id="' . esc_attr($id . '-label') . '" name="' . esc_attr($name . '[label]') . '" value="' . esc_attr($stage['label']) . '" maxlength="80"' . ($new ? ' placeholder="Leave blank to add no stage"' : ' required') . '></div>';
        echo '<div class="idlm-design-stage-move" data-stage-move></div></div>';
        $notes = array();
        if ($is_first && !$stage['archived']) { $notes[] = 'Start stage: accepted projects begin here.'; }
        if (!$new) { $notes[] = $projects ? $projects . ($projects === 1 ? ' unfinished project here.' : ' unfinished projects here.') : 'No unfinished projects here.'; }
        if ($notes) { echo '<p class="idlm-design-muted">' . esc_html(implode(' ', $notes)) . '</p>'; }
        echo '<fieldset class="idlm-design-stage-needs"><legend>Needed before leaving this stage</legend><div class="idlm-design-stage-checks">';
        foreach (array('designer' => 'Designer assigned', 'tentative_quote' => 'Tentative quote saved', 'final_quote' => 'Final quote marked Customer approved') as $field => $label) { echo '<label class="idlm-design-option"><input type="checkbox" name="' . esc_attr($name . '[' . $field . ']') . '" value="1"' . checked(!empty($requires[$field]), true, false) . '> ' . esc_html($label) . '</label>'; }
        echo '</div><div class="idlm-form-group"><label for="' . esc_attr($id . '-payment') . '">Payment</label><select class="idlm-input" id="' . esc_attr($id . '-payment') . '" name="' . esc_attr($name . '[payment]') . '">';
        foreach (IDLM_Design_Stages::PAYMENTS as $value => $label) { echo '<option value="' . esc_attr($value) . '"' . selected($requires['payment'], $value, false) . '>' . esc_html($label) . '</option>'; }
        echo '</select></div><p class="idlm-design-stage-docs-label">Documents</p><div class="idlm-design-stage-checks">';
        foreach (IDLM_Design_Stages::slots() as $slot => $definition) { echo '<label class="idlm-design-option"><input type="checkbox" name="' . esc_attr($name . '[slots][]') . '" value="' . esc_attr($slot) . '"' . checked(in_array($slot, $stage['slots'], true), true, false) . '> ' . esc_html($definition['label']) . '</label>'; }
        echo '</div></fieldset>';
        if (!$new) {
            $locked = !$stage['archived'] && $projects > 0;
            echo '<label class="idlm-design-option idlm-design-stage-remove"><input type="checkbox" name="' . esc_attr($name . '[archived]') . '" value="1"' . checked($stage['archived'], true, false) . disabled($locked, true, false) . '> ' . esc_html($stage['archived'] ? 'Removed. Untick to bring this stage back.' : ($locked ? 'Remove this stage (move its projects first)' : 'Remove this stage')) . '</label>';
        }
        echo '</li>';
    }

    private static function document_editor($index, $key, $document) {
        $name = 'documents[' . $index . ']'; $id = 'idlm-document-' . $index;
        echo '<div class="idlm-design-document-row" data-document-row><input type="hidden" name="' . esc_attr($name . '[key]') . '" value="' . esc_attr($key) . '"><div class="idlm-form-group"><label for="' . esc_attr($id . '-label') . '">' . ('' === $key ? 'New document name' : 'Document name') . '</label><input class="idlm-input" id="' . esc_attr($id . '-label') . '" name="' . esc_attr($name . '[label]') . '" value="' . esc_attr($document['label']) . '" maxlength="80"' . ('' === $key ? ' placeholder="For example, Site measurement drawing"' : ' required') . '></div><div class="idlm-form-group"><label for="' . esc_attr($id . '-type') . '">File type</label><select class="idlm-input" id="' . esc_attr($id . '-type') . '" name="' . esc_attr($name . '[type]') . '">';
        foreach (IDLM_Design_Stages::file_types() as $type => $definition) { echo '<option value="' . esc_attr($type) . '"' . selected($document['type'], $type, false) . '>' . esc_html($definition['label']) . '</option>'; }
        echo '</select></div>' . ('' !== $key ? '<label class="idlm-design-option"><input type="checkbox" name="' . esc_attr($name . '[remove]') . '" value="1"> Remove</label>' : '') . '</div>';
    }
}
