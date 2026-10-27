<?php
/** Lead connector settings, shared by administrator and workspace. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
$idlm_connector=$context['lead_connector'] ?? array();
$idlm_config=$idlm_connector['config'] ?? array();
$idlm_status=$idlm_connector['status'] ?? array();
$idlm_facebook=$idlm_connector['facebook'] ?? array();
$idlm_facebook_authorized=!empty($idlm_facebook['authorized']) || !empty($idlm_facebook['connected']);
?>
<article class="idlm-card idlm-meta-connector">
    <div class="idlm-meta-connector__header">
        <div><h3>Direct Meta lead sync</h3>
        <p class="idlm-card-desc">Import leads directly from selected Facebook and Instagram Instant Forms.</p></div>
        <span class="idlm-meta-connector__status"><?php echo !empty($idlm_config['enabled']) ? 'Sync enabled' : ($idlm_facebook_authorized ? 'Facebook connected' : 'Not connected'); ?></span>
    </div>
    <?php if (!empty($idlm_connector['error'])): ?>
    <div class="idlm-alert idlm-alert-warning" role="alert" tabindex="-1">
        <p><strong>Connector setup was not saved.</strong> <?php echo esc_html($idlm_connector['error']); ?></p>
    </div>
    <?php endif; ?>
    <?php
    $idlm_credentials=$idlm_connector['credentials'] ?? array();
    $idlm_app_ready=!empty($idlm_config['app_id']) && !empty($idlm_config['login_config_id']) && !empty($idlm_credentials['app_secret']);
    $idlm_manual_step=!$idlm_app_ready ? 1 : (empty($idlm_credentials['verify_token']) ? 2 : (empty($idlm_facebook['connected']) ? 3 : 4));
    ?>
    <div class="idlm-meta-wizard" data-idlm-manual-setup data-idlm-wizard-initial="<?php echo (int)$idlm_manual_step; ?>" data-idlm-wizard-error="<?php echo !empty($idlm_connector['error']) ? '1' : '0'; ?>">
        <p>Connect your own Meta app in four steps. Your progress stays in this form as you move between steps.</p>
        <nav class="idlm-meta-wizard__steps" aria-label="Meta connection setup">
            <?php foreach (array(1=>'Meta app',2=>'Webhook',3=>'Facebook and forms',4=>'Review') as $idlm_step=>$idlm_title): ?>
                <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-wizard-go="<?php echo (int)$idlm_step; ?>"><span><?php echo (int)$idlm_step; ?></span> <?php echo esc_html($idlm_title); ?></button>
            <?php endforeach; ?>
        </nav>
        <p role="status" aria-live="polite" data-idlm-wizard-status></p>
        <input type="hidden" name="idlm_connector_settings" value="1">
        <details data-idlm-manual-step="1" <?php echo $idlm_manual_step===1 ? 'open' : ''; ?>>
            <summary><strong>1. Set up your Meta app</strong></summary>
            <p>In Meta for Developers, open your app and its Facebook Login for Business configuration. Copy the two IDs below. Choose a user access token configuration with Page listing, lead retrieval and Page subscription permissions.</p>
            <div class="idlm-form-grid two">
            <?php foreach (array('app_id'=>'Meta app ID','login_config_id'=>'Facebook Login configuration ID') as $idlm_key=>$idlm_label): ?>
                <div class="idlm-form-group">
                    <label for="idlm-connector-<?php echo esc_attr($idlm_key); ?>"><?php echo esc_html($idlm_label); ?></label>
                    <input type="text" inputmode="numeric" class="idlm-input" id="idlm-connector-<?php echo esc_attr($idlm_key); ?>" name="idlm_connector_<?php echo esc_attr($idlm_key); ?>" value="<?php echo esc_attr($idlm_config[$idlm_key] ?? ''); ?>">
                </div>
            <?php endforeach; ?>
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The fixed credential subtemplate escapes labels/IDs and outputs only credential-presence booleans; saved secret values are never passed.
        echo IDLM_View::render('admin/settings/connector-secret.php', array('key'=>'app_secret','label'=>'Meta app secret','saved'=>$idlm_credentials['app_secret'] ?? false,'managed'=>$idlm_connector['managed_credentials']['app_secret'] ?? false)); ?>
            </div>
            <div class="idlm-form-group">
                <label for="idlm-facebook-redirect-url">Facebook Login redirect URL</label>
                <input class="idlm-input" id="idlm-facebook-redirect-url" value="<?php echo esc_attr(IDLM_Facebook_Direct::callback_url()); ?>" readonly>
                <p class="description">Add this exact URL to your app’s valid OAuth redirect URIs. Live sign-in requires a public HTTPS address.</p>
            </div>
            <div class="idlm-table-actions"><button type="button" class="idlm-btn idlm-btn-primary" data-idlm-manual-next="2">Next: webhook</button></div>
        </details>
        <details data-idlm-manual-step="2" <?php echo $idlm_manual_step===2 ? 'open' : ''; ?>>
            <summary><strong>2. Configure the lead webhook</strong></summary>
            <p>Choose a verification token and use that same value in your Meta app’s Page webhook settings. Add the callback below and select the <code>leadgen</code> field.</p>
            <div class="idlm-form-grid two">
                <div class="idlm-form-group"><label for="idlm-connector-url">Meta Lead Ads callback URL</label><input class="idlm-input" id="idlm-connector-url" value="<?php echo esc_attr(get_rest_url(null,'idlm/v1/meta-leads')); ?>" readonly></div>
                <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The fixed credential subtemplate escapes labels/IDs and outputs only credential-presence booleans; saved secret values are never passed.
        echo IDLM_View::render('admin/settings/connector-secret.php', array('key'=>'verify_token','label'=>'Webhook verification token','saved'=>$idlm_credentials['verify_token'] ?? false,'managed'=>$idlm_connector['managed_credentials']['verify_token'] ?? false)); ?>
                <div class="idlm-form-group"><label for="idlm-connector-page_id">Facebook Page ID</label><input class="idlm-input" inputmode="numeric" id="idlm-connector-page_id" name="idlm_connector_page_id" value="<?php echo esc_attr($idlm_config['page_id'] ?? ''); ?>"><p class="description">Needed to verify the webhook before connecting. You can also select your Page in the next step, then finish webhook verification in Meta.</p></div>
            </div>
            <p class="description">Saved verification tokens stay hidden. If you no longer know yours, replace it here and use the replacement in Meta. Saving details also saves the other settings on this page.</p>
            <div class="idlm-table-actions"><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-manual-next="1">Back</button><button type="submit" class="idlm-btn idlm-btn-primary" data-idlm-wizard-save-next="3">Save details and continue</button></div>
        </details>
        <details data-idlm-manual-step="3" <?php echo $idlm_manual_step===3 ? 'open' : ''; ?>>
            <summary><strong>3. Connect Facebook and choose forms</strong></summary>
            <p>Sign in to Facebook, approve access, then choose your Page and the individual forms to sync. The Page access token is saved automatically.</p>
    <div class="idlm-meta-connector__connection" data-idlm-facebook-connect data-idlm-facebook-ready="<?php echo !empty($idlm_facebook['ready']) ? '1' : '0'; ?>">
        <div class="idlm-meta-connector__connection-row">
        <div class="idlm-meta-connector__connection-heading"><strong>Connect with Facebook</strong><span>Approve access, then choose a Page and its forms. This site connects directly to Meta.</span></div>
        <div class="idlm-table-actions">
            <button type="button" class="idlm-btn idlm-btn-primary" data-idlm-facebook-start data-idlm-facebook-connect-button <?php disabled($idlm_facebook_authorized); ?>><?php echo $idlm_facebook_authorized ? 'Connected' : 'Connect Facebook'; ?></button>
            <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-facebook-start data-idlm-facebook-reconnect <?php echo $idlm_facebook_authorized ? '' : 'hidden'; ?>>Reconnect Facebook</button>
            <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-facebook-disconnect <?php echo $idlm_facebook_authorized ? '' : 'hidden'; ?>>Disconnect Facebook</button>
        </div>
        </div>
        <?php if (!empty($idlm_facebook['connected'])): ?>
            <p><strong>Connected to <?php echo esc_html($idlm_facebook['page_name']); ?></strong></p>
            <ul><?php foreach (($idlm_facebook['form_names'] ?? array()) as $idlm_name): ?><li><?php echo esc_html($idlm_name); ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if (empty($idlm_facebook['ready'])): ?>
        <div class="idlm-alert idlm-alert-warning" tabindex="-1" data-idlm-facebook-setup>
            <p><strong>Facebook sign-in is not available yet.</strong> Save your Meta app ID, Facebook Login configuration ID and app secret in step 1. Use a public HTTPS CRM address.</p>
            <p>Use Back to finish app setup, then save your details in step 2.</p>
        </div>
        <?php endif; ?>
        <p role="status" aria-live="polite" data-idlm-facebook-status></p>
        <div data-idlm-facebook-picker hidden>
            <div class="idlm-form-group">
                <label for="idlm-facebook-pages">Choose your Facebook Page</label>
                <select id="idlm-facebook-pages" class="idlm-input" data-idlm-facebook-pages><option value="">Select a Page</option></select>
            </div>
            <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-facebook-more-pages hidden>Load more Pages</button>
            <fieldset data-idlm-facebook-form-fieldset hidden>
                <legend>Select individual lead forms</legend>
                <div data-idlm-facebook-forms></div>
                <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-facebook-more-forms hidden>Load more forms</button>
                <p class="description">After selecting forms, click Start syncing selected forms. Leads submitted from that point onward will sync automatically. Earlier leads are not imported. Background catch-up recovers missed submissions after activation, within a seven-day window.</p>
                <button type="button" class="idlm-btn idlm-btn-primary" data-idlm-facebook-save disabled>Start syncing selected forms</button>
                <p role="status" aria-live="polite" tabindex="-1" data-idlm-facebook-sync-status></p>
            </fieldset>
        </div>
    </div>
            <div class="idlm-table-actions"><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-manual-next="2">Back</button><button type="button" class="idlm-btn idlm-btn-primary" data-idlm-manual-next="4">Next: review</button></div>
        </details>
        <details data-idlm-manual-step="4" <?php echo $idlm_manual_step===4 ? 'open' : ''; ?>>
            <summary><strong>4. Review and test</strong></summary>
            <dl class="idlm-meta-wizard__review">
                <dt>Saved Meta app</dt><dd><?php echo esc_html($idlm_config['app_id'] ?? ''); ?></dd>
                <dt>Saved Facebook Page</dt><dd><?php echo esc_html($idlm_config['page_name'] ?? ($idlm_config['page_id'] ?? 'Not selected')); ?></dd>
                <dt>Saved forms</dt><dd><?php echo esc_html(implode(', ', $idlm_config['form_names'] ?? ($idlm_config['forms'] ?? array())) ?: 'Not selected'); ?></dd>
            </dl>
            <p>Facebook sign-in enables sync after you confirm the selected forms. You can check the saved connection or turn off importing here. This checks Page subscription and access to the first form; send a test lead for each selected form to confirm delivery and field mapping.</p>
    <details open>
        <summary>Instant Form field mappings</summary>
        <p class="description">Load a form’s Meta fields, choose a CRM destination for each, then save that form’s mapping. Previously imported leads are not remapped automatically. Unmapped answers remain in encrypted submission details.</p>
        <?php if (empty($idlm_config['forms'])): ?><p>Select and save your forms in step 3 first.</p><?php endif; ?>
        <?php foreach (($idlm_config['forms'] ?? array()) as $idlm_index=>$idlm_form): ?>
        <fieldset class="idlm-meta-form-mapping" data-idlm-form-mapping data-idlm-form-id="<?php echo esc_attr($idlm_form); ?>">
            <legend data-idlm-form-name><?php echo esc_html($idlm_config['form_names'][$idlm_form] ?? ($idlm_config['form_names'][$idlm_index] ?? ('Form '.$idlm_form))); ?></legend>
            <p class="description">Form ID: <?php echo esc_html($idlm_form); ?></p>
            <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-form-fields-load>Load form fields</button>
            <div data-idlm-form-fields hidden></div>
            <button type="button" class="idlm-btn idlm-btn-primary" data-idlm-form-mapping-save hidden>Save mapping for this form</button>
            <p role="status" aria-live="polite" data-idlm-form-mapping-status></p>
        </fieldset>
        <?php endforeach; ?>
        <details class="idlm-meta-wizard__advanced">
            <summary>Advanced: default field names</summary>
            <p class="description">These fallback mappings apply only to forms without their own saved mapping. Full name defaults to splitting <code>full_name</code> into first and last name. Keep these for existing or manual connections when form questions cannot be loaded.</p>
        <div class="idlm-form-grid two">
        <?php foreach (($idlm_connector['mapping_fields'] ?? array()) as $idlm_key=>$idlm_default): ?>
            <div class="idlm-form-group">
                <label for="idlm-connector-map-<?php echo esc_attr($idlm_key); ?>"><?php echo esc_html(ucwords(str_replace('_',' ',$idlm_key))); ?></label>
                <input class="idlm-input" id="idlm-connector-map-<?php echo esc_attr($idlm_key); ?>" name="idlm_connector_map_<?php echo esc_attr($idlm_key); ?>" value="<?php echo esc_attr($idlm_config['mappings'][$idlm_key] ?? $idlm_default); ?>">
            </div>
        <?php endforeach; ?>
        </div>
        </details>
    </details>

            <div class="idlm-table-actions">
                <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-test-lead-connector">Check saved Meta lead connection</button>
            </div>
            <p id="idlm-lead-connector-result" role="status" aria-live="polite"></p>
            <label><input type="checkbox" name="idlm_meta_lead_sync" value="1" <?php checked($idlm_config['enabled'] ?? '0','1'); ?>> Enable direct Instant Form sync</label>
            <p class="description">This is the saved on/off switch for importing leads from the selected forms. Start syncing turns it on automatically. Uncheck and save to stop imports; existing CRM records stay unchanged. Queued imports encountered by the worker while disabled are cancelled. Imports need the site’s scheduled worker to run.</p>
            <button type="submit" class="idlm-btn idlm-btn-primary">Save setup</button>
            <details class="idlm-meta-wizard__advanced"><summary>Advanced: import recovery</summary>
            <label><input type="checkbox" name="idlm_connector_retry" value="1"> Retry failed queued imports when saving settings (up to 100 records checked)</label>
            <p class="description">Use this if a lead failed to import because of a temporary API or permission problem. It resets failed attempts among up to 100 queued records. Automatic retrieval retries up to five times, and queued data expires after seven days. This does not import historical leads.</p>
            <?php if (is_array($idlm_status) && !empty($idlm_status['at'])): ?>
                <p class="description">Last connector result: <?php echo esc_html(($idlm_status['provider'] ?? '').' — '.($idlm_status['state'] ?? '').' — '.wp_date('Y-m-d H:i:s',(int)$idlm_status['at'])); ?></p>
            <?php endif; ?>
            </details>
            <details class="idlm-meta-wizard__advanced"><summary>Advanced: connect using a Page access token</summary>
                <p>An alternative to Facebook sign-in: supply your own Page token and form IDs manually. Leave this closed when using Connect Facebook; that flow obtains the token and form IDs for you.</p>
                <div class="idlm-form-grid two">
                    <div class="idlm-form-group"><label for="idlm-connector-forms">Instant Form IDs (comma-separated)</label><input class="idlm-input" id="idlm-connector-forms" name="idlm_connector_form_ids" value="<?php echo esc_attr(implode(', ', $idlm_config['forms'] ?? array())); ?>"><p class="description">One to twenty form IDs belonging to the Page in step 2.</p></div>
                    <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The fixed credential subtemplate escapes labels/IDs and outputs only credential-presence booleans; saved secret values are never passed.
        echo IDLM_View::render('admin/settings/connector-secret.php', array('key'=>'access_token','label'=>'Page access token','saved'=>$idlm_credentials['access_token'] ?? false,'managed'=>$idlm_connector['managed_credentials']['access_token'] ?? false)); ?>
                </div>
            </details>
            <button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-manual-next="3">Back</button>
        </details>
    </div>
</article>
<article class="idlm-card idlm-interakt-capture">
    <div class="idlm-meta-connector__header">
        <div><h3>WhatsApp leads through Interakt</h3><p class="idlm-card-desc">Bring incoming WhatsApp conversations into your CRM using your existing Interakt webhook.</p></div>
        <span class="idlm-meta-connector__status"><?php echo !empty($idlm_connector['interakt_capture']) && '0' !== (string) $idlm_connector['interakt_capture'] ? 'Saved: capture on' : 'Saved: capture off'; ?></span>
    </div>
    <div class="idlm-interakt-capture__panel">
        <label class="idlm-interakt-capture__option"><input type="checkbox" name="idlm_interakt_lead_capture" value="1" <?php checked($idlm_connector['interakt_capture'] ?? '0','1'); ?>><span><strong>Capture incoming leads</strong><small>Create a lead for a new contact or match the message to an existing lead.</small></span></label>
        <p class="idlm-interakt-capture__note">New leads follow your normal assignment rules. Existing leads keep their owner and stage. Delivery and campaign status notifications do not create leads.</p>
    </div>
    <div class="idlm-interakt-capture__panel">
        <label class="idlm-interakt-capture__option"><input type="checkbox" name="idlm_whatsapp_capi_enabled" value="1" <?php checked($idlm_connector['whatsapp_capi'] ?? '0','1'); ?>><span><strong>Report WhatsApp campaign conversions</strong><small>Send eligible outcomes to Meta using your existing conversion API connection.</small></span></label>
    <div class="idlm-form-group">
        <label for="idlm-whatsapp-page-id">Facebook Page ID used by WhatsApp ads</label>
        <input class="idlm-input" type="text" inputmode="numeric" id="idlm-whatsapp-page-id" name="idlm_connector_whatsapp_page_id" value="<?php echo esc_attr($idlm_config['whatsapp_page_id'] ?? ''); ?>">
    </div>
    <p class="idlm-interakt-capture__note">Before enabling, verify that Interakt forwards the original ad click identifier. Disable Interakt reporting for the same outcomes to avoid duplicate reporting. Ordinary messages remain recorded as WhatsApp without campaign attribution.</p>
    <details class="idlm-interakt-capture__details">
        <summary>Advanced: conversion events and requirements</summary>
        <p class="description">The ad-message webhook must include <code>referral.ctwa_clid</code> or <code>meta_data.referral.ctwa_clid</code>. Confirm this in an actual webhook before enabling reporting.</p>
        <p class="description">Separate from Instant Form events. Won → Purchase is the default; leave other stages blank until their supported event names are configured for your Meta business-messaging dataset. Uses the existing Meta CAPI destination and access token.</p>
        <div class="idlm-form-grid two">
        <?php foreach (array('New','Qualified','Quote Shared','Won') as $idlm_stage): ?>
            <div class="idlm-form-group">
                <label for="idlm-wa-event-<?php echo esc_attr(sanitize_key($idlm_stage)); ?>"><?php echo esc_html($idlm_stage); ?></label>
                <input class="idlm-input" id="idlm-wa-event-<?php echo esc_attr(sanitize_key($idlm_stage)); ?>" name="idlm_whatsapp_event_<?php echo esc_attr(sanitize_key($idlm_stage)); ?>" value="<?php echo esc_attr($idlm_config['whatsapp_events'][$idlm_stage] ?? ($idlm_stage==='Won' ? 'Purchase' : '')); ?>">
            </div>
        <?php endforeach; ?>
        </div>
    </details>
    </div>
</article>
