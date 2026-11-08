<?php
/** Lead connector settings, shared by administrator and workspace. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
$idlm_connector=$context['lead_connector'] ?? array();
$idlm_config=$idlm_connector['config'] ?? array();
$idlm_status=$idlm_connector['status'] ?? array();
$idlm_facebook=$idlm_connector['facebook'] ?? array();
$idlm_facebook_authorized=!empty($idlm_facebook['authorized']) || !empty($idlm_facebook['connected']);
?>
<?php
$idlm_summary=is_array($idlm_connector['summary'] ?? null) ? $idlm_connector['summary'] : array();
$idlm_paused_at=(int)($idlm_summary['paused_at'] ?? 0);
$idlm_enabled=!empty($idlm_config['enabled']) && '0'!==(string)$idlm_config['enabled'];
$idlm_state=$idlm_paused_at ? array('paused','Paused: token stopped working') : ($idlm_enabled ? array('on','Syncing') : (!empty($idlm_config['app_id']) ? array('off','Sync off') : array('none','Not set up')));
$idlm_last=(int)($idlm_summary['last_import'] ?? 0);
?>
<article class="idlm-card idlm-meta-connector">
    <div class="idlm-meta-connector__header">
        <div><h3>Direct Meta lead sync</h3>
        <p class="idlm-card-desc">Import leads directly from selected Facebook and Instagram Instant Forms.</p></div>
        <span class="idlm-meta-connector__status is-<?php echo esc_attr($idlm_state[0]); ?>" data-idlm-lead-sync-state><?php echo esc_html($idlm_state[1]); ?></span>
    </div>
    <?php if ($idlm_paused_at): ?>
    <div class="idlm-alert idlm-alert-warning" data-idlm-notice-inline role="alert">
        <p><strong>Lead imports are paused.</strong> <?php echo esc_html(sprintf('Meta stopped accepting the access token on %s. Waiting leads are kept for %d days.', wp_date('j M Y, H:i', $idlm_paused_at), IDLM_Lead_Connector::WINDOW_DAYS)); ?></p>
        <p>Generate a new system user token, replace it in step 1, save, then run the check in step 3. Imports resume as soon as the new token is saved.</p>
    </div>
    <?php endif; ?>
    <?php if ($idlm_enabled || $idlm_paused_at || !empty($idlm_summary['waiting']) || !empty($idlm_summary['failed'])): ?>
    <dl class="idlm-meta-connector__summary" data-idlm-lead-sync-summary>
        <div><dt>Last lead imported</dt><dd><?php echo esc_html($idlm_last ? sprintf('%s ago', human_time_diff($idlm_last, time())) : 'None yet'); ?></dd></div>
        <div><dt>Waiting</dt><dd data-idlm-lead-sync-waiting><?php echo esc_html(number_format_i18n((int)($idlm_summary['waiting'] ?? 0)).(!empty($idlm_summary['capped']) ? '+' : '')); ?></dd></div>
        <div class="<?php echo !empty($idlm_summary['failed']) ? 'is-failed' : ''; ?>"><dt>Failed</dt><dd data-idlm-lead-sync-failed><?php echo esc_html(number_format_i18n((int)($idlm_summary['failed'] ?? 0))); ?></dd></div>
        <div class="idlm-meta-connector__summary-action">
            <button type="button" class="idlm-btn idlm-btn-secondary idlm-btn-compact" data-idlm-meta-lead-retry <?php echo empty($idlm_summary['failed']) ? 'hidden' : ''; ?>>Retry failed imports</button>
        </div>
    </dl>
    <p class="description" role="status" aria-live="polite" data-idlm-meta-lead-retry-status></p>
    <?php endif; ?>
    <?php if (!empty($idlm_connector['error'])): ?>
    <div class="idlm-alert idlm-alert-warning" data-idlm-notice-inline role="alert" tabindex="-1">
        <p><strong>Connector setup was not saved.</strong> <?php echo esc_html($idlm_connector['error']); ?></p>
    </div>
    <?php endif; ?>
    <?php
    $idlm_credentials=$idlm_connector['credentials'] ?? array();
    $idlm_app_ready=!empty($idlm_config['app_id']) && !empty($idlm_credentials['app_secret']);
    $idlm_manual_step=!$idlm_app_ready ? 1 : (empty($idlm_credentials['verify_token']) ? 2 : (empty($idlm_config['enabled']) ? 3 : 4));
    $idlm_done=array(
        1=>$idlm_app_ready && (!empty($idlm_credentials['access_token']) || !empty($idlm_facebook['connected'])) && !empty($idlm_config['page_id']) && !empty($idlm_config['forms']),
        2=>!empty($idlm_credentials['verify_token']),
        3=>$idlm_enabled,
        4=>$idlm_enabled && !empty($idlm_config['forms']),
    );
    ?>
    <div class="idlm-meta-wizard" data-idlm-manual-setup data-idlm-wizard-initial="<?php echo (int)$idlm_manual_step; ?>" data-idlm-wizard-error="<?php echo !empty($idlm_connector['error']) ? '1' : '0'; ?>">
        <p>Connect your own Meta app with a system user token. It does not expire and needs no Meta App Review for your own Page.</p>
        <nav class="idlm-meta-wizard__steps" aria-label="Meta connection setup">
            <?php foreach (array(1=>'App and token',2=>'Webhook',3=>'Check and start',4=>'Field mapping') as $idlm_step=>$idlm_title): ?>
                <button type="button" class="idlm-btn idlm-btn-secondary<?php echo $idlm_done[$idlm_step] ? ' is-done' : ''; ?>" data-idlm-wizard-go="<?php echo (int)$idlm_step; ?>"><span class="idlm-meta-wizard__step-number" aria-hidden="true"><?php echo $idlm_done[$idlm_step] ? '✓' : (int)$idlm_step; ?></span> <?php echo esc_html($idlm_title); ?><?php if ($idlm_done[$idlm_step]): ?><span class="screen-reader-text"> (done)</span><?php endif; ?></button>
            <?php endforeach; ?>
        </nav>
        <p role="status" aria-live="polite" data-idlm-wizard-status></p>
        <input type="hidden" name="idlm_connector_settings" value="1">
        <details data-idlm-manual-step="1" <?php echo $idlm_manual_step===1 ? 'open' : ''; ?>>
            <summary><strong>1. Meta app and system user token</strong></summary>
            <ol class="idlm-meta-wizard__howto">
                <li>In Meta for Developers, create a <strong>Business</strong> app owned by the business that owns your Facebook Page, add a privacy policy URL, and switch it to <strong>Live</strong>. No App Review is needed.</li>
                <li>In Business Settings &gt; Users &gt; System users, add a system user. Assign it your app and your Page (full control).</li>
                <li>Generate a token for the app with <code>leads_retrieval</code>, <code>pages_manage_metadata</code>, <code>pages_read_engagement</code>, <code>pages_show_list</code> and <code>pages_manage_ads</code>. Set expiry to <strong>Never</strong>.</li>
                <li>In Business Settings &gt; Integrations &gt; Leads access, allow the app or system user to read leads.</li>
            </ol>
            <div class="idlm-form-grid two">
                <div class="idlm-form-group">
                    <label for="idlm-connector-app_id">Meta app ID</label>
                    <input type="text" inputmode="numeric" class="idlm-input" id="idlm-connector-app_id" name="idlm_connector_app_id" value="<?php echo esc_attr($idlm_config['app_id'] ?? ''); ?>">
                </div>
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The fixed credential subtemplate escapes labels/IDs and outputs only credential-presence booleans; saved secret values are never passed.
        echo IDLM_View::render('admin/settings/connector-secret.php', array('key'=>'app_secret','label'=>'Meta app secret','saved'=>$idlm_credentials['app_secret'] ?? false,'managed'=>$idlm_connector['managed_credentials']['app_secret'] ?? false)); ?>
            <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The fixed credential subtemplate escapes labels/IDs and outputs only credential-presence booleans; saved secret values are never passed.
        echo IDLM_View::render('admin/settings/connector-secret.php', array('key'=>'access_token','label'=>'System user access token','saved'=>$idlm_credentials['access_token'] ?? false,'managed'=>$idlm_connector['managed_credentials']['access_token'] ?? false)); ?>
                <div class="idlm-form-group"><label for="idlm-connector-page_id">Facebook Page ID</label><input class="idlm-input" inputmode="numeric" id="idlm-connector-page_id" name="idlm_connector_page_id" value="<?php echo esc_attr($idlm_config['page_id'] ?? ''); ?>"><p class="description">Page settings &gt; About &gt; Page transparency, or Business Settings &gt; Pages.</p></div>
                <div class="idlm-form-group"><label for="idlm-connector-forms">Instant Form IDs (comma-separated)</label><input class="idlm-input" id="idlm-connector-forms" name="idlm_connector_form_ids" value="<?php echo esc_attr(implode(', ', $idlm_config['forms'] ?? array())); ?>"><p class="description">One to twenty form IDs from Meta Business Suite &gt; Instant forms.</p></div>
            </div>
            <div class="idlm-table-actions"><button type="button" class="idlm-btn idlm-btn-primary" data-idlm-manual-next="2">Next: webhook</button></div>
        </details>
        <details data-idlm-manual-step="2" <?php echo $idlm_manual_step===2 ? 'open' : ''; ?>>
            <summary><strong>2. Configure the lead webhook</strong></summary>
            <p>In your Meta app, add the Webhooks product, choose <strong>Page</strong>, enter the callback URL and a verification token of your choice, then subscribe to the <code>leadgen</code> field. Use the same token here.</p>
            <div class="idlm-form-grid two">
                <div class="idlm-form-group"><label for="idlm-connector-url">Meta Lead Ads callback URL</label><div class="idlm-meta-connector__copy"><input class="idlm-input" id="idlm-connector-url" value="<?php echo esc_attr(get_rest_url(null,'idlm/v1/meta-leads')); ?>" readonly><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-copy-field="idlm-connector-url">Copy</button></div></div>
                <?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The fixed credential subtemplate escapes labels/IDs and outputs only credential-presence booleans; saved secret values are never passed.
        echo IDLM_View::render('admin/settings/connector-secret.php', array('key'=>'verify_token','label'=>'Webhook verification token','saved'=>$idlm_credentials['verify_token'] ?? false,'managed'=>$idlm_connector['managed_credentials']['verify_token'] ?? false)); ?>
            </div>
            <div class="idlm-table-actions"><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-generate-verify-token>Generate a verification token</button></div>
            <p class="description" role="status" aria-live="polite" data-idlm-generate-verify-status></p>
            <p class="description">Saved tokens stay hidden. If you no longer know yours, replace it here and use the replacement in Meta. Saving details also saves the other settings on this page.</p>
            <div class="idlm-table-actions"><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-manual-next="1">Back</button><button type="submit" class="idlm-btn idlm-btn-primary" data-idlm-wizard-save-next="3">Save details and continue</button></div>
        </details>
        <details data-idlm-manual-step="3" <?php echo $idlm_manual_step===3 ? 'open' : ''; ?>>
            <summary><strong>3. Check the connection and start syncing</strong></summary>
            <p>The check confirms the token and its permissions, subscribes your Page’s new leads to the app if needed, and confirms each form is readable. It reads one lead ID per form and imports nothing.</p>
            <div class="idlm-table-actions">
                <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-test-lead-connector">Check saved Meta lead connection</button>
            </div>
            <ul class="idlm-meta-wizard__check" data-idlm-lead-connector-lines hidden></ul>
            <p id="idlm-lead-connector-result" role="status" aria-live="polite"></p>
            <label><input type="checkbox" name="idlm_meta_lead_sync" value="1" <?php checked($idlm_config['enabled'] ?? '0','1'); ?>> Enable direct Instant Form sync</label>
            <p class="description">Leads submitted from now on are imported automatically, and missed ones from the last seven days are recovered in the background. Uncheck and save to stop imports; existing CRM records stay unchanged.</p>
            <div class="idlm-table-actions"><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-manual-next="2">Back</button><button type="submit" class="idlm-btn idlm-btn-primary">Save setup</button><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-manual-next="4">Next: field mapping</button></div>
            <details class="idlm-meta-wizard__advanced">
                <summary>Other way: connect with Facebook sign-in</summary>
                <p>Instead of a system user token, an administrator can sign in with Facebook to choose the Page and forms. This needs a Facebook Login for Business configuration, and the Page token comes from that administrator’s account.</p>
                <div class="idlm-form-grid two">
                    <div class="idlm-form-group">
                        <label for="idlm-connector-login_config_id">Facebook Login configuration ID</label>
                        <input type="text" inputmode="numeric" class="idlm-input" id="idlm-connector-login_config_id" name="idlm_connector_login_config_id" value="<?php echo esc_attr($idlm_config['login_config_id'] ?? ''); ?>">
                    </div>
                    <div class="idlm-form-group">
                        <label for="idlm-facebook-redirect-url">Facebook Login redirect URL</label>
                        <div class="idlm-meta-connector__copy"><input class="idlm-input" id="idlm-facebook-redirect-url" value="<?php echo esc_attr(IDLM_Facebook_Direct::callback_url()); ?>" readonly><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-copy-field="idlm-facebook-redirect-url">Copy</button></div>
                        <p class="description">Add this exact URL to your app’s valid OAuth redirect URIs. Live sign-in requires a public HTTPS address.</p>
                    </div>
                </div>
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
        <div class="idlm-alert idlm-alert-warning" data-idlm-notice-inline tabindex="-1" data-idlm-facebook-setup>
            <p><strong>Facebook sign-in is not available yet.</strong> Save your Meta app ID and app secret in step 1 and the Facebook Login configuration ID here. Use a public HTTPS CRM address.</p>
            
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
            </details>
        </details>
        <details data-idlm-manual-step="4" <?php echo $idlm_manual_step===4 ? 'open' : ''; ?>>
            <summary><strong>4. Field mapping</strong></summary>
            <dl class="idlm-meta-wizard__review">
                <dt>Saved Meta app</dt><dd><?php echo esc_html($idlm_config['app_id'] ?? ''); ?></dd>
                <dt>Saved Facebook Page</dt><dd><?php echo esc_html($idlm_config['page_name'] ?? ($idlm_config['page_id'] ?? 'Not selected')); ?></dd>
                <dt>Saved forms</dt><dd><?php echo esc_html(implode(', ', $idlm_config['form_names'] ?? ($idlm_config['forms'] ?? array())) ?: 'Not selected'); ?></dd>
            </dl>
    <details open>
        <summary>Instant Form field mappings</summary>
        <p class="description">Load a form’s Meta fields, choose a CRM destination for each, then save that form’s mapping. Previously imported leads are not remapped automatically. Unmapped answers remain in encrypted submission details.</p>
        <?php if (empty($idlm_config['forms'])): ?><p>Save your form IDs in step 1 first.</p><?php endif; ?>
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

            <p class="description">A failed import (for example a form field the mapping does not cover) waits in the status panel above. Fix the mapping, save, then use Retry failed imports.</p>
            <div class="idlm-table-actions"><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-manual-next="3">Back</button><button type="submit" class="idlm-btn idlm-btn-primary">Save setup</button></div>
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
