<?php
/** Write-only connector credential control. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
$idlm_key = $context['key'];
$idlm_option = 'idlm_meta_lead_'.$idlm_key;
$idlm_saved = !empty($context['saved']);
$idlm_managed = !empty($context['managed']);
?>
<div class="idlm-form-group idlm-connector-secret" data-idlm-connector-secret>
    <label for="<?php echo esc_attr($idlm_option); ?>"><?php echo esc_html($context['label']); ?></label>
    <div class="idlm-connector-secret__saved" data-idlm-secret-summary <?php echo ($idlm_saved || $idlm_managed) ? '' : 'hidden'; ?>>
        <strong data-idlm-secret-summary-text><?php echo $idlm_saved ? 'Saved securely' : 'Not configured'; ?></strong>
        <span><?php echo $idlm_managed ? 'Managed in server configuration.' : 'Your saved value is hidden for security. No need to enter it again.'; ?></span>
        <?php if ($idlm_saved && !$idlm_managed): ?><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-secret-replace>Replace</button><?php endif; ?>
    </div>
    <div data-idlm-secret-editor <?php echo ($idlm_saved || $idlm_managed) ? 'hidden' : ''; ?>>
        <input type="password" class="idlm-input" id="<?php echo esc_attr($idlm_option); ?>" name="<?php echo esc_attr($idlm_option); ?>" value="" autocomplete="new-password" placeholder="<?php echo $idlm_saved ? 'Paste a replacement value' : 'Enter this credential'; ?>" data-idlm-secret-configured="<?php echo $idlm_saved ? '1' : '0'; ?>" <?php disabled($idlm_managed); ?>>
        <p class="description" data-idlm-secret-edit-status><?php echo $idlm_saved ? 'The existing value stays saved until you save a replacement.' : 'Not configured. Enter a value, then save details.'; ?></p>
        <?php if ($idlm_saved && !$idlm_managed): ?><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-secret-cancel>Keep saved value</button><?php endif; ?>
    </div>
    <?php if ($idlm_saved && !$idlm_managed): ?><label><input type="checkbox" name="<?php echo esc_attr($idlm_option); ?>_clear" value="1" data-idlm-secret-clear> Remove saved credential when settings are saved</label><?php endif; ?>
    <p class="description" role="status" aria-live="polite" data-idlm-secret-status></p>
</div>
