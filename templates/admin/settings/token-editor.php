<?php
if (!defined('ABSPATH')) {
    exit;
}

$idlm_editor_id = isset($context['editor_id']) ? sanitize_html_class($context['editor_id']) : '';
$idlm_field_name = isset($context['field_name']) ? sanitize_key($context['field_name']) : '';
$idlm_label = isset($context['label']) ? $context['label'] : '';
$idlm_help = isset($context['help']) ? $context['help'] : '';
$idlm_values = isset($context['values']) && is_array($context['values']) ? array_values($context['values']) : array();
$idlm_required = !empty($context['required']);
$idlm_pipeline = !empty($context['pipeline']);
$idlm_stage_totals = isset($context['stage_totals']) && is_array($context['stage_totals']) ? $context['stage_totals'] : array();
$idlm_aliases = isset($context['aliases']) && is_array($context['aliases']) ? $context['aliases'] : array();

if ('' === $idlm_editor_id || '' === $idlm_field_name) {
    return;
}

$idlm_entry_id = $idlm_editor_id . '-entry';
$idlm_help_id = $idlm_editor_id . '-help';
$idlm_error_id = $idlm_editor_id . '-error';
$idlm_status_id = $idlm_editor_id . '-status';
?>
<div class="idlm-token-editor<?php echo $idlm_pipeline ? ' idlm-pipeline-stage-editor' : ''; ?>" data-idlm-token-editor data-idlm-sortable-editor data-idlm-required="<?php echo $idlm_required ? 'true' : 'false'; ?>"<?php echo $idlm_pipeline ? ' data-idlm-pipeline-editor' : ''; ?>>
    <label for="<?php echo esc_attr($idlm_entry_id); ?>"><?php echo esc_html($idlm_label); ?></label>
    <input type="hidden" name="<?php echo esc_attr($idlm_field_name); ?>" value="<?php echo esc_attr(implode(',', $idlm_values)); ?>" data-idlm-token-source>
    <div class="idlm-token-editor__entry">
        <input type="text" id="<?php echo esc_attr($idlm_entry_id); ?>" class="idlm-input" autocomplete="off" aria-describedby="<?php echo esc_attr($idlm_help_id . ' ' . $idlm_error_id); ?>" data-idlm-token-entry>
        <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-token-add>Add option</button>
    </div>
    <p class="idlm-settings-help" id="<?php echo esc_attr($idlm_help_id); ?>"><?php echo esc_html($idlm_help); ?></p>
    <p class="idlm-field-error" id="<?php echo esc_attr($idlm_error_id); ?>" role="alert" hidden data-idlm-token-error></p>
    <ul class="idlm-token-editor__list idlm-token-editor__list--movable<?php echo $idlm_pipeline ? ' idlm-pipeline-stage-editor__list' : ''; ?>" aria-label="Current <?php echo esc_attr(strtolower($idlm_label)); ?>" data-idlm-token-list>
        <?php foreach ($idlm_values as $idlm_value): ?>
            <li class="idlm-token-editor__item idlm-token-editor__item--movable<?php echo $idlm_pipeline ? ' idlm-pipeline-stage-editor__item' : ''; ?>" draggable="true">
                <span class="idlm-token-editor__position<?php echo $idlm_pipeline ? ' idlm-pipeline-stage-editor__position' : ''; ?>" aria-hidden="true"></span>
                <span class="dashicons dashicons-menu idlm-token-editor__drag" aria-hidden="true"></span>
                <span class="idlm-token-editor__value"><?php echo esc_html($idlm_value); ?></span>
                <input type="hidden" name="<?php echo esc_attr($idlm_field_name); ?>_values[]" value="<?php echo esc_attr($idlm_value); ?>" data-idlm-token-value>
                <label class="screen-reader-text" for="<?php echo esc_attr($idlm_editor_id . '-alias-' . md5($idlm_value)); ?>">Display label for <?php echo esc_html($idlm_value); ?></label>
                <input type="text" class="idlm-input idlm-token-editor__alias" id="<?php echo esc_attr($idlm_editor_id . '-alias-' . md5($idlm_value)); ?>" name="value_labels[<?php echo esc_attr($idlm_field_name); ?>][<?php echo esc_attr(rawurlencode($idlm_value)); ?>]" value="<?php echo esc_attr(isset($idlm_aliases[$idlm_value]) ? $idlm_aliases[$idlm_value] : $idlm_value); ?>" aria-label="Display label for <?php echo esc_attr($idlm_value); ?>">
                <?php if ($idlm_pipeline): ?>
                    <?php $idlm_total_source = isset($idlm_stage_totals[$idlm_value]) ? $idlm_stage_totals[$idlm_value] : 'none'; ?>
                    <label class="screen-reader-text" for="<?php echo esc_attr($idlm_editor_id . '-total-' . md5($idlm_value)); ?>">Header total for <?php echo esc_html($idlm_value); ?></label>
                    <select id="<?php echo esc_attr($idlm_editor_id . '-total-' . md5($idlm_value)); ?>" name="kanban_stage_totals[<?php echo esc_attr(rawurlencode($idlm_value)); ?>]" class="idlm-select idlm-pipeline-stage-editor__total" aria-label="Header total for <?php echo esc_attr($idlm_value); ?>" data-idlm-stage-total>
                        <option value="none" <?php selected($idlm_total_source, 'none'); ?>>Hidden</option>
                        <option value="quote_value" <?php selected($idlm_total_source, 'quote_value'); ?>>Quote value</option>
                        <option value="deal_value" <?php selected($idlm_total_source, 'deal_value'); ?>>Deal value</option>
                    </select>
                <?php endif; ?>
                <?php if (!$idlm_pipeline): ?><button type="button" class="idlm-token-editor__move" aria-label="Move <?php echo esc_attr($idlm_value); ?> up" data-idlm-token-move="up">
                    <span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
                </button>
                <button type="button" class="idlm-token-editor__move" aria-label="Move <?php echo esc_attr($idlm_value); ?> down" data-idlm-token-move="down">
                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                </button><?php endif; ?>
                <button type="button" class="idlm-token-editor__remove" aria-label="Remove <?php echo esc_attr($idlm_value); ?>" data-idlm-token-remove>
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="screen-reader-text" id="<?php echo esc_attr($idlm_status_id); ?>" aria-live="polite" data-idlm-token-status></p>
</div>
