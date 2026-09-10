<?php
if (!defined('ABSPATH')) {
    exit;
}

$editor_id = isset($context['editor_id']) ? sanitize_html_class($context['editor_id']) : '';
$field_name = isset($context['field_name']) ? sanitize_key($context['field_name']) : '';
$label = isset($context['label']) ? $context['label'] : '';
$help = isset($context['help']) ? $context['help'] : '';
$values = isset($context['values']) && is_array($context['values']) ? array_values($context['values']) : array();
$required = !empty($context['required']);
$pipeline = !empty($context['pipeline']);
$stage_totals = isset($context['stage_totals']) && is_array($context['stage_totals']) ? $context['stage_totals'] : array();
$aliases = isset($context['aliases']) && is_array($context['aliases']) ? $context['aliases'] : array();

if ('' === $editor_id || '' === $field_name) {
    return;
}

$entry_id = $editor_id . '-entry';
$help_id = $editor_id . '-help';
$error_id = $editor_id . '-error';
$status_id = $editor_id . '-status';
?>
<div class="idlm-token-editor<?php echo $pipeline ? ' idlm-pipeline-stage-editor' : ''; ?>" data-idlm-token-editor data-idlm-sortable-editor data-idlm-required="<?php echo $required ? 'true' : 'false'; ?>"<?php echo $pipeline ? ' data-idlm-pipeline-editor' : ''; ?>>
    <label for="<?php echo esc_attr($entry_id); ?>"><?php echo esc_html($label); ?></label>
    <input type="hidden" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr(implode(',', $values)); ?>" data-idlm-token-source>
    <div class="idlm-token-editor__entry">
        <input type="text" id="<?php echo esc_attr($entry_id); ?>" class="idlm-input" autocomplete="off" aria-describedby="<?php echo esc_attr($help_id . ' ' . $error_id); ?>" data-idlm-token-entry>
        <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-token-add>Add option</button>
    </div>
    <p class="idlm-settings-help" id="<?php echo esc_attr($help_id); ?>"><?php echo esc_html($help); ?></p>
    <p class="idlm-field-error" id="<?php echo esc_attr($error_id); ?>" role="alert" hidden data-idlm-token-error></p>
    <ul class="idlm-token-editor__list idlm-token-editor__list--movable<?php echo $pipeline ? ' idlm-pipeline-stage-editor__list' : ''; ?>" aria-label="Current <?php echo esc_attr(strtolower($label)); ?>" data-idlm-token-list>
        <?php foreach ($values as $value): ?>
            <li class="idlm-token-editor__item idlm-token-editor__item--movable<?php echo $pipeline ? ' idlm-pipeline-stage-editor__item' : ''; ?>" draggable="true">
                <span class="idlm-token-editor__position<?php echo $pipeline ? ' idlm-pipeline-stage-editor__position' : ''; ?>" aria-hidden="true"></span>
                <span class="dashicons dashicons-menu idlm-token-editor__drag" aria-hidden="true"></span>
                <span class="idlm-token-editor__value"><?php echo esc_html($value); ?></span>
                <input type="hidden" name="<?php echo esc_attr($field_name); ?>_values[]" value="<?php echo esc_attr($value); ?>" data-idlm-token-value>
                <label class="screen-reader-text" for="<?php echo esc_attr($editor_id . '-alias-' . md5($value)); ?>">Display label for <?php echo esc_html($value); ?></label>
                <input type="text" class="idlm-input idlm-token-editor__alias" id="<?php echo esc_attr($editor_id . '-alias-' . md5($value)); ?>" name="value_labels[<?php echo esc_attr($field_name); ?>][<?php echo esc_attr(rawurlencode($value)); ?>]" value="<?php echo esc_attr(isset($aliases[$value]) ? $aliases[$value] : $value); ?>" aria-label="Display label for <?php echo esc_attr($value); ?>">
                <?php if ($pipeline): ?>
                    <?php $total_source = isset($stage_totals[$value]) ? $stage_totals[$value] : 'none'; ?>
                    <label class="screen-reader-text" for="<?php echo esc_attr($editor_id . '-total-' . md5($value)); ?>">Header total for <?php echo esc_html($value); ?></label>
                    <select id="<?php echo esc_attr($editor_id . '-total-' . md5($value)); ?>" name="kanban_stage_totals[<?php echo esc_attr(rawurlencode($value)); ?>]" class="idlm-select idlm-pipeline-stage-editor__total" aria-label="Header total for <?php echo esc_attr($value); ?>" data-idlm-stage-total>
                        <option value="none" <?php selected($total_source, 'none'); ?>>Hidden</option>
                        <option value="quote_value" <?php selected($total_source, 'quote_value'); ?>>Quote value</option>
                        <option value="deal_value" <?php selected($total_source, 'deal_value'); ?>>Deal value</option>
                    </select>
                <?php endif; ?>
                <?php if (!$pipeline): ?><button type="button" class="idlm-token-editor__move" aria-label="Move <?php echo esc_attr($value); ?> up" data-idlm-token-move="up">
                    <span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span>
                </button>
                <button type="button" class="idlm-token-editor__move" aria-label="Move <?php echo esc_attr($value); ?> down" data-idlm-token-move="down">
                    <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
                </button><?php endif; ?>
                <button type="button" class="idlm-token-editor__remove" aria-label="Remove <?php echo esc_attr($value); ?>" data-idlm-token-remove>
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="screen-reader-text" id="<?php echo esc_attr($status_id); ?>" aria-live="polite" data-idlm-token-status></p>
</div>
