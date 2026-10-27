<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_dialog_id = isset($context['dialog_id']) ? sanitize_html_class($context['dialog_id']) : 'idlm-log-payload-dialog';
?>
<div class="idlm-modal idlm-dialog idlm-log-payload-dialog" id="<?php echo esc_attr($idlm_dialog_id); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($idlm_dialog_id); ?>-title" aria-describedby="<?php echo esc_attr($idlm_dialog_id); ?>-description" aria-hidden="true" data-idlm-dialog data-idlm-initial-focus="[data-idlm-log-copy='payload']" hidden>
    <div class="idlm-modal-content idlm-dialog__panel idlm-log-dialog-panel">
        <header class="idlm-modal-header idlm-dialog__header">
            <div>
                <h2 class="idlm-dialog__title" id="<?php echo esc_attr($idlm_dialog_id); ?>-title" data-idlm-log-dialog-title>Integration payload</h2>
                <p class="idlm-dialog__description" id="<?php echo esc_attr($idlm_dialog_id); ?>-description">Review the redacted request and platform response for this log entry.</p>
            </div>
            <button type="button" class="idlm-dialog__close" data-idlm-dialog-close aria-label="Close integration payload"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
        </header>
        <div class="idlm-dialog__body">
            <div class="idlm-log-dialog-status" role="status" aria-live="polite" data-idlm-log-dialog-status>Choose a log entry to load its details.</div>
            <section class="idlm-log-payload-section" aria-labelledby="<?php echo esc_attr($idlm_dialog_id); ?>-request-title">
                <div class="idlm-log-payload-heading">
                    <h3 id="<?php echo esc_attr($idlm_dialog_id); ?>-request-title">Request payload</h3>
                    <button type="button" class="idlm-btn idlm-btn-secondary idlm-btn-compact" data-idlm-log-copy="payload" disabled>Copy request</button>
                </div>
                <pre class="idlm-code-block" data-idlm-log-payload tabindex="0">No payload loaded.</pre>
            </section>
            <section class="idlm-log-payload-section" aria-labelledby="<?php echo esc_attr($idlm_dialog_id); ?>-response-title">
                <div class="idlm-log-payload-heading">
                    <h3 id="<?php echo esc_attr($idlm_dialog_id); ?>-response-title">Platform response</h3>
                    <button type="button" class="idlm-btn idlm-btn-secondary idlm-btn-compact" data-idlm-log-copy="response" disabled>Copy response</button>
                </div>
                <pre class="idlm-code-block" data-idlm-log-response tabindex="0">No response loaded.</pre>
            </section>
        </div>
        <footer class="idlm-dialog__footer">
            <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-dialog-close>Close</button>
        </footer>
    </div>
</div>
