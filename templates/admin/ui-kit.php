<?php
/**
 * Design system review page: every shared component on live classes. Builder: Shashi.
 * Shown only to administrators on local and development sites (IDLM_Admin_Shortcodes::ui_kit_available()).
 */
if (!defined('ABSPATH')) {
    exit;
}

$idlm_roles = array(
    'background' => 'Page background',
    'card' => 'Card surface',
    'muted' => 'Muted fill',
    'muted-foreground' => 'Muted text',
    'foreground' => 'Text',
    'border' => 'Border',
    'input' => 'Input border',
    'primary' => 'Primary (brand)',
    'success' => 'Success',
    'warning' => 'Needs attention',
    'destructive' => 'Destructive',
    'info' => 'Information',
);
$idlm_buttons = array(
    'idlm-btn-primary' => 'Default',
    'idlm-btn-secondary' => 'Outline',
    'idlm-btn-quiet' => 'Ghost',
    'idlm-btn-danger-outline' => 'Destructive',
    'idlm-btn-danger-quiet' => 'Destructive ghost',
    'idlm-btn-link' => 'Link',
);
$idlm_stages = array('New', 'Contacted', 'Qualified', 'Sales Connected', 'Studio Visit Scheduled', 'QS - Visit Pending', 'Visit Completed', 'Quote Shared', 'Negotiation', 'Won', 'RNR', 'On Hold', 'Lost');
$idlm_qualities = array('Pending' => 'Pending', 'Qualified' => 'Qualified, plan received', 'plan-pending' => 'Qualified, plan pending', 'Unqualified' => 'Unqualified');
?>
<div class="wrap idlm-wrap idlm-ui-kit">
    <header class="idlm-header">
        <div class="idlm-header-meta"><h1>Design system</h1><p>Shared components on live classes. Local and development sites only.</p></div>
        <div class="idlm-header-actions"><a class="idlm-btn idlm-btn-secondary" href="<?php echo esc_url(add_query_arg('idlm_view', 'leads')); ?>">Open Leads to compare</a></div>
    </header>

    <section class="idlm-card idlm-ui-kit-section" aria-labelledby="idlm-ui-kit-tokens">
        <h2 id="idlm-ui-kit-tokens">Role tokens</h2>
        <p class="idlm-card-desc">Use <code>--idlm-ui-*</code> in new rules. Each one points at an existing token, and Primary follows the white-label colour from Settings.</p>
        <ul class="idlm-ui-kit-swatches">
            <?php foreach ($idlm_roles as $idlm_role => $idlm_label): ?>
                <li><span class="idlm-ui-kit-swatch" data-role="<?php echo esc_attr($idlm_role); ?>" aria-hidden="true"></span><strong><?php echo esc_html($idlm_label); ?></strong><code>--idlm-ui-<?php echo esc_html($idlm_role); ?></code></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="idlm-card idlm-ui-kit-section" aria-labelledby="idlm-ui-kit-buttons">
        <h2 id="idlm-ui-kit-buttons">Buttons</h2>
        <p class="idlm-card-desc">One primary button per header. Destructive outline prepares an action; the filled destructive button appears only in the final confirmation dialog.</p>
        <div class="idlm-ui-kit-buttons" role="table" aria-label="Button variants" tabindex="0">
            <div class="idlm-ui-kit-button-row is-head" role="row"><span role="columnheader">Variant</span><span role="columnheader">Default</span><span role="columnheader">Small</span><span role="columnheader">With icon</span><span role="columnheader">Icon only</span><span role="columnheader">Disabled</span><span role="columnheader">Loading</span></div>
            <?php foreach ($idlm_buttons as $idlm_class => $idlm_label): ?>
                <div class="idlm-ui-kit-button-row" role="row">
                    <span role="rowheader"><strong><?php echo esc_html($idlm_label); ?></strong><code><?php echo esc_html($idlm_class); ?></code></span>
                    <span role="cell"><button type="button" class="idlm-btn <?php echo esc_attr($idlm_class); ?>">Save lead</button></span>
                    <span role="cell"><button type="button" class="idlm-btn idlm-btn-sm <?php echo esc_attr($idlm_class); ?>">Save lead</button></span>
                    <span role="cell"><button type="button" class="idlm-btn <?php echo esc_attr($idlm_class); ?>"><span class="dashicons dashicons-plus-alt2" data-icon="inline-start" aria-hidden="true"></span>Add lead</button></span>
                    <span role="cell"><button type="button" class="idlm-btn idlm-btn-icon <?php echo esc_attr($idlm_class); ?>" aria-label="Edit lead"><span class="dashicons dashicons-edit" data-icon aria-hidden="true"></span></button></span>
                    <span role="cell"><button type="button" class="idlm-btn <?php echo esc_attr($idlm_class); ?>" disabled>Save lead</button></span>
                    <span role="cell"><button type="button" class="idlm-btn <?php echo esc_attr($idlm_class); ?>" aria-busy="true">Save lead</button></span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="idlm-card idlm-ui-kit-section" aria-labelledby="idlm-ui-kit-badges">
        <h2 id="idlm-ui-kit-badges">Badges</h2>
        <p class="idlm-card-desc">A badge always carries a written label; colour only supports it (STD-CRM-4).</p>
        <h3>Status</h3>
        <div class="idlm-ui-kit-row">
            <span class="idlm-badge is-success">Sent</span>
            <span class="idlm-badge is-warning">Needs attention</span>
            <span class="idlm-badge is-danger">Failed</span>
            <span class="idlm-badge is-info">Scheduled</span>
            <span class="idlm-badge is-neutral">Draft</span>
            <span class="idlm-badge is-outline">Meta Ads</span>
            <span class="idlm-badge is-success is-strong">Paid in full</span>
        </div>
        <h3>Stage</h3>
        <div class="idlm-ui-kit-row">
            <?php foreach ($idlm_stages as $idlm_stage): ?><span class="idlm-badge" data-idlm-stage="<?php echo esc_attr($idlm_stage); ?>"><?php echo esc_html($idlm_stage); ?></span><?php endforeach; ?>
        </div>
        <h3>Quality</h3>
        <div class="idlm-ui-kit-row">
            <?php foreach ($idlm_qualities as $idlm_value => $idlm_label): ?><span class="idlm-badge" data-idlm-quality="<?php echo esc_attr($idlm_value); ?>"><?php echo esc_html($idlm_label); ?></span><?php endforeach; ?>
        </div>
    </section>

    <section class="idlm-card idlm-ui-kit-section" aria-labelledby="idlm-ui-kit-fields">
        <h2 id="idlm-ui-kit-fields">Fields</h2>
        <p class="idlm-card-desc">Field, label, control, help, then error. Validation sits on the field (<code>data-invalid</code>) and the control (<code>aria-invalid</code>).</p>
        <div class="idlm-ui-kit-form">
            <div class="idlm-field-group">
                <div class="idlm-field">
                    <label for="idlm-ui-kit-name">Lead name <span class="idlm-required">(required)</span></label>
                    <input class="idlm-input" id="idlm-ui-kit-name" value="Ananya Rao" aria-describedby="idlm-ui-kit-name-help">
                    <p class="idlm-field-help" id="idlm-ui-kit-name-help">As the customer writes it.</p>
                </div>
                <div class="idlm-field" data-invalid>
                    <label for="idlm-ui-kit-phone">Phone</label>
                    <input class="idlm-input" id="idlm-ui-kit-phone" value="98450" aria-invalid="true" aria-describedby="idlm-ui-kit-phone-error">
                    <p class="idlm-field-error" id="idlm-ui-kit-phone-error">Enter a 10-digit mobile number.</p>
                </div>
                <div class="idlm-field" data-disabled>
                    <label for="idlm-ui-kit-source">Source</label>
                    <input class="idlm-input" id="idlm-ui-kit-source" value="Meta Ads" disabled>
                    <p class="idlm-field-help">Set by the integration and cannot be changed.</p>
                </div>
                <div class="idlm-field">
                    <label for="idlm-ui-kit-stage">Stage</label>
                    <select class="idlm-select" id="idlm-ui-kit-stage"><?php foreach ($idlm_stages as $idlm_stage): ?><option><?php echo esc_html($idlm_stage); ?></option><?php endforeach; ?></select>
                </div>
                <fieldset class="idlm-fieldset">
                    <legend class="idlm-fieldset-legend">Notify on handoff</legend>
                    <label class="idlm-checkbox-row"><input type="checkbox" checked><span>Assigned representative</span></label>
                    <label class="idlm-checkbox-row"><input type="checkbox"><span>Sales manager</span></label>
                </fieldset>
                <fieldset class="idlm-fieldset">
                    <legend class="idlm-fieldset-legend">Budget</legend>
                    <label class="idlm-checkbox-row"><input type="radio" name="idlm_ui_kit_budget" checked><span>Under ₹10L</span></label>
                    <label class="idlm-checkbox-row"><input type="radio" name="idlm_ui_kit_budget"><span>₹10L to ₹25L</span></label>
                </fieldset>
            </div>
        </div>
    </section>

    <section class="idlm-card idlm-ui-kit-section" aria-labelledby="idlm-ui-kit-states">
        <h2 id="idlm-ui-kit-states">States</h2>
        <div class="idlm-ui-kit-states">
            <div class="idlm-state"><strong>No leads match these filters</strong><span>Clear a filter or widen the date range.</span><div class="idlm-state__actions"><button type="button" class="idlm-btn idlm-btn-secondary">Clear filters</button></div></div>
            <div class="idlm-state is-error"><strong>Leads could not be loaded</strong><span>Check your connection and try again.</span><div class="idlm-state__actions"><button type="button" class="idlm-btn idlm-btn-secondary">Retry</button></div></div>
            <div class="idlm-ui-kit-loading" aria-label="Loading example"><span class="idlm-spinner" aria-hidden="true"></span><span class="idlm-skeleton-text"></span><span class="idlm-skeleton-text"></span><span class="idlm-skeleton-row"></span></div>
        </div>
    </section>
</div>
