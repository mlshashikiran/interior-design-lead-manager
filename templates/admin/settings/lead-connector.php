<?php
/** Lead connector settings, shared by administrator and workspace. Builder: Shashi. */
if (!defined('ABSPATH')) { exit; }
$idlm_connector=$context['lead_connector'] ?? array();
$idlm_config=$idlm_connector['config'] ?? array();
$idlm_status=$idlm_connector['status'] ?? array();
$idlm_facebook=$idlm_connector['facebook'] ?? array();
?>
<article class="idlm-card idlm-meta-connector">
    <div class="idlm-meta-connector__header">
        <div><h3>Direct Meta lead sync</h3>
        <p class="idlm-card-desc">Import leads directly from selected Facebook and Instagram Instant Forms.</p></div>
        <span class="idlm-meta-connector__status"><?php echo !empty($idlm_facebook['connected']) ? 'Connected' : (!empty($idlm_config['enabled']) ? 'Manual sync enabled' : 'Not connected'); ?></span>
    </div>
    <?php if (!empty($idlm_connector['error'])): ?>
    <div class="idlm-alert idlm-alert-warning" role="alert" tabindex="-1">
        <p><strong>Connector setup was not saved.</strong> <?php echo esc_html($idlm_connector['error']); ?></p>
    </div>
    <?php endif; ?>
    <div class="idlm-meta-connector__connection" data-idlm-facebook-connect data-idlm-facebook-ready="<?php echo !empty($idlm_facebook['ready']) ? '1' : '0'; ?>">
        <div class="idlm-meta-connector__connection-row">
        <div class="idlm-meta-connector__connection-heading"><strong>Connect with Facebook</strong><span>Approve access, then choose a Page and its forms.</span></div>
        <div class="idlm-table-actions">
            <button type="button" class="idlm-btn idlm-btn-primary" data-idlm-facebook-start><?php echo !empty($idlm_facebook['connected']) ? 'Change Page or forms' : 'Connect Facebook'; ?></button>
            <?php if (!empty($idlm_facebook['connected'])): ?><button type="button" class="idlm-btn idlm-btn-secondary" data-idlm-facebook-disconnect>Disconnect Facebook</button><?php endif; ?>
        </div>
        </div>
        <?php if (!empty($idlm_facebook['connected'])): ?>
            <p><strong>Connected to <?php echo esc_html($idlm_facebook['page_name']); ?></strong></p>
            <ul><?php foreach (($idlm_facebook['form_names'] ?? array()) as $idlm_name): ?><li><?php echo esc_html($idlm_name); ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if (empty($idlm_facebook['ready'])): ?>
        <div class="idlm-alert idlm-alert-warning" tabindex="-1" data-idlm-facebook-setup>
            <p><strong>Facebook sign-in is not available yet.</strong> The shared connection service must be configured for this CRM, and the CRM must be reachable over public HTTPS.</p>
            <p>You can use manual setup below with your own Meta app.</p>
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
                <button type="button" class="idlm-btn idlm-btn-primary" data-idlm-facebook-save disabled>Start syncing selected forms</button>
            </fieldset>
        </div>
    </div>
    <?php
    $idlm_credentials=$idlm_connector['credentials'] ?? array();
    $idlm_source_ready=!empty($idlm_config['page_id']) && !empty($idlm_config['app_id']) && !empty($idlm_config['forms']);
    $idlm_credentials_ready=!empty($idlm_credentials['access_token']) && !empty($idlm_credentials['app_secret']);
    $idlm_manual_step=!$idlm_source_ready ? 1 : (!$idlm_credentials_ready ? 2 : (empty($idlm_credentials['verify_token']) ? 3 : 4));
    ?>
    <details class="idlm-meta-manual" <?php echo empty($idlm_facebook['ready']) || !empty($idlm_connector['error']) ? 'open' : ''; ?> data-idlm-manual-setup>
        <summary><span><strong>Manual setup</strong><small>Connect using your own Meta app</small></span></summary>
        <p class="description">Use your own Meta app without the shared service. Complete the steps in order. Save progress at any time; sync starts only when you enable it in step 4. Saving also saves the other settings on this page.</p>
        <input type="hidden" name="idlm_connector_settings" value="1">
        <details data-idlm-manual-step="1" <?php echo $idlm_manual_step===1 ? 'open' : ''; ?>>
            <summary><span class="idlm-meta-step-number">1</span><span class="idlm-meta-step-label"><strong>Page and forms</strong><small>Choose the source of your leads</small></span><?php if ($idlm_source_ready): ?><span class="idlm-meta-step-state">Saved</span><?php endif; ?></summary>
            <p>Enter the Page that owns your lead forms, your Meta app ID, and the individual form IDs you want to import.</p>
            <div class="idlm-form-grid two">
            <?php foreach (array('page_id'=>'Facebook Page ID','app_id'=>'Meta app ID') as $idlm_key=>$idlm_label): ?>
                <div class="idlm-form-group">
                    <label for="idlm-connector-<?php echo esc_attr($idlm_key); ?>"><?php echo esc_html($idlm_label); ?></label>
                    <input type="text" inputmode="numeric" class="idlm-input" id="idlm-connector-<?php echo esc_attr($idlm_key); ?>" name="idlm_connector_<?php echo esc_attr($idlm_key); ?>" value="<?php echo esc_attr($idlm_config[$idlm_key] ?? ''); ?>">
                </div>
            <?php endforeach; ?>
                <div class="idlm-form-group">
                    <label for="idlm-connector-forms">Instant Form IDs (comma-separated)</label>
                    <input class="idlm-input" id="idlm-connector-forms" name="idlm_connector_form_ids" value="<?php echo esc_attr(implode(', ',$idlm_config['forms'] ?? array())); ?>">
                    <p class="description">Choose between one and twenty forms belonging to this Page.</p>
                </div>
            </div>
            <button type="submit" class="idlm-btn idlm-btn-secondary">Save progress</button>
            <button type="button" class="idlm-btn idlm-btn-primary" data-idlm-manual-next="2">Continue to credentials</button>
        </details>
        <details data-idlm-manual-step="2" <?php echo $idlm_manual_step===2 ? 'open' : ''; ?>>
            <summary><span class="idlm-meta-step-number">2</span><span class="idlm-meta-step-label"><strong>Credentials</strong><small>Authorize lead retrieval</small></span><?php if ($idlm_credentials_ready): ?><span class="idlm-meta-step-state">Saved</span><?php endif; ?></summary>
            <p>Use a lead-retrieval token with access to this Page and its leads, plus the secret from the same Meta app. These are separate from your CAPI credentials.</p>
            <p class="description">Saved secrets stay hidden. A blank field does not mean the saved value was lost; look for “Saved securely” below it. Leave it blank to keep the existing value.</p>
            <div class="idlm-form-grid two">
            <?php foreach (array('access_token'=>'Lead retrieval access token','app_secret'=>'Lead app secret') as $idlm_key=>$idlm_label): $idlm_option='idlm_meta_lead_'.$idlm_key; $idlm_managed=!empty($idlm_connector['managed_credentials'][$idlm_key]); ?>
                <div class="idlm-form-group">
                    <label for="<?php echo esc_attr($idlm_option); ?>"><?php echo esc_html($idlm_label); ?></label>
                    <input type="password" class="idlm-input" id="<?php echo esc_attr($idlm_option); ?>" name="<?php echo esc_attr($idlm_option); ?>" value="" autocomplete="new-password" data-idlm-secret-configured="<?php echo !empty($idlm_credentials[$idlm_key]) ? '1' : '0'; ?>" <?php disabled($idlm_managed); ?>>
                    <p class="description"><?php echo !empty($idlm_credentials[$idlm_key]) ? 'Saved securely. Leave blank to retain it.' : 'Not configured. Paste the value and save progress.'; ?><?php echo $idlm_managed ? ' Managed in server configuration.' : ''; ?></p>
                    <label><input type="checkbox" name="<?php echo esc_attr($idlm_option); ?>_clear" value="1" <?php disabled($idlm_managed); ?>> Clear saved credential</label>
                </div>
            <?php endforeach; ?>
            </div>
            <button type="submit" class="idlm-btn idlm-btn-secondary">Save progress</button>
            <button type="button" class="idlm-btn idlm-btn-primary" data-idlm-manual-next="3">Continue to webhook</button>
        </details>
        <details data-idlm-manual-step="3" <?php echo $idlm_manual_step===3 ? 'open' : ''; ?>>
            <summary><span class="idlm-meta-step-number">3</span><span class="idlm-meta-step-label"><strong>Webhook</strong><small>Connect your Meta app to the CRM</small></span><?php if (!empty($idlm_credentials['verify_token'])): ?><span class="idlm-meta-step-state">Token saved</span><?php endif; ?></summary>
            <p>Choose a verification token below and save progress. In your Meta app, add this callback URL with the same token, configure the Page webhook’s <code>leadgen</code> field, and subscribe the app to your Page. Grant lead access to the token’s account.</p>
            <div class="idlm-form-grid two">
                <div class="idlm-form-group">
                    <label for="idlm-connector-url">Meta Lead Ads callback URL</label>
                    <input class="idlm-input" id="idlm-connector-url" value="<?php echo esc_attr(get_rest_url(null,'idlm/v1/meta-leads')); ?>" readonly>
                </div>
                <?php $idlm_option='idlm_meta_lead_verify_token'; $idlm_managed=!empty($idlm_connector['managed_credentials']['verify_token']); ?>
                <div class="idlm-form-group">
                    <label for="<?php echo esc_attr($idlm_option); ?>">Lead webhook verify token</label>
                    <input type="password" class="idlm-input" id="<?php echo esc_attr($idlm_option); ?>" name="<?php echo esc_attr($idlm_option); ?>" value="" autocomplete="new-password" data-idlm-secret-configured="<?php echo !empty($idlm_credentials['verify_token']) ? '1' : '0'; ?>" <?php disabled($idlm_managed); ?>>
                    <p class="description"><?php echo !empty($idlm_credentials['verify_token']) ? 'Saved securely. Leave blank to retain it.' : 'Not configured. Choose a random token and use the same value in Meta.'; ?><?php echo $idlm_managed ? ' Managed in server configuration.' : ''; ?></p>
                    <label><input type="checkbox" name="<?php echo esc_attr($idlm_option); ?>_clear" value="1" <?php disabled($idlm_managed); ?>> Clear saved credential</label>
                </div>
            </div>
            <p class="description">The callback must be publicly reachable over HTTPS. You can save this setup locally, but Meta cannot deliver leads to a local HTTP address. Webhook verification is available after the Page ID and verify token are saved, even before sync is enabled.</p>
            <button type="submit" class="idlm-btn idlm-btn-secondary">Save progress</button>
            <button type="button" class="idlm-btn idlm-btn-primary" data-idlm-manual-next="4">Continue to check and enable</button>
        </details>
        <details data-idlm-manual-step="4" <?php echo $idlm_manual_step===4 ? 'open' : ''; ?>>
            <summary><span class="idlm-meta-step-number">4</span><span class="idlm-meta-step-label"><strong>Check and enable</strong><small>Verify the connection before syncing</small></span></summary>
            <p>Save your setup first, then check the saved connection. This checks Page subscription and access to the first form; send a test lead for each selected form to confirm delivery and field mapping.</p>
    <details>
        <summary>Instant Form field mappings</summary>
        <p class="description">Use the exact field names returned by Meta, including custom questions. These mappings are independent of Pabbly. If first name is absent, <code>full_name</code> is split into first and last name.</p>
        <div class="idlm-form-grid two">
        <?php foreach (($idlm_connector['mapping_fields'] ?? array()) as $idlm_key=>$idlm_default): ?>
            <div class="idlm-form-group">
                <label for="idlm-connector-map-<?php echo esc_attr($idlm_key); ?>"><?php echo esc_html(ucwords(str_replace('_',' ',$idlm_key))); ?></label>
                <input class="idlm-input" id="idlm-connector-map-<?php echo esc_attr($idlm_key); ?>" name="idlm_connector_map_<?php echo esc_attr($idlm_key); ?>" value="<?php echo esc_attr($idlm_config['mappings'][$idlm_key] ?? $idlm_default); ?>">
            </div>
        <?php endforeach; ?>
        </div>
    </details>

            <div class="idlm-table-actions">
                <button type="button" class="idlm-btn idlm-btn-secondary" id="idlm-test-lead-connector">Check saved Meta lead connection</button>
            </div>
            <p id="idlm-lead-connector-result" role="status" aria-live="polite"></p>
            <label><input type="checkbox" name="idlm_meta_lead_sync" value="1" <?php checked($idlm_config['enabled'] ?? '0','1'); ?>> Enable direct Instant Form sync</label>
            <p class="description">After the check succeeds, enable sync and save. Keep a reliable Action Scheduler or WP-Cron runner active.</p>
            <button type="submit" class="idlm-btn idlm-btn-primary">Save setup</button>
            <label><input type="checkbox" name="idlm_connector_retry" value="1"> Retry failed imports on Save settings (up to 100 queued records inspected)</label>
            <p class="description">New notifications are saved before acknowledgment. Retrieval retries up to five times; unprocessed contact/message data expires after seven days. Catch-up starts when this Page is first saved and does not import older history.</p>
            <?php if (is_array($idlm_status) && !empty($idlm_status['at'])): ?>
                <p class="description">Last connector result: <?php echo esc_html(($idlm_status['provider'] ?? '').' — '.($idlm_status['state'] ?? '').' — '.wp_date('Y-m-d H:i:s',(int)$idlm_status['at'])); ?></p>
            <?php endif; ?>
        </details>
    </details>
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
