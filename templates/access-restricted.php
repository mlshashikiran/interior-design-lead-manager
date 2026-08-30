<?php
if (!defined('ABSPATH')) {
    exit;
}

$wrapper_class = !empty($context['admin']) ? 'wrap idlm-wrap' : 'idlm-wrap';
?>
<div class="<?php echo esc_attr($wrapper_class); ?>">
    <div class="idlm-card idlm-access-restricted">
        <h3>Access Restricted</h3>
        <p>You do not have permission to access this resource. Please log in with an authorized account.</p>
    </div>
</div>
