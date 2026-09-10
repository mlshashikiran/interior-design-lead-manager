<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
?>
            <!-- Tab: Help & FAQs -->
            <section id="settings-help" class="idlm-settings-tab-content <?php echo $active_tab === 'help' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-help" <?php echo $active_tab === 'help' ? '' : 'hidden'; ?>>
                <div class="idlm-grid-full">
                    <article class="idlm-card">
                        <h3>Cloudflare "Under Attack" Mode & Security Blocks Guidance</h3>
                        <p class="idlm-card-desc">If you have enabled Cloudflare's "Under Attack" Mode or high security levels to mitigate bot attacks, incoming third-party ad webhooks (Meta, Google, LinkedIn) will be blocked with a "Just a moment..." (503 challenge) page because API clients cannot execute the required JavaScript challenges.</p>
                        <ol class="idlm-help-list">
                            <li>Log in to your Cloudflare Dashboard and select your website domain.</li>
                            <li>Navigate to Security &gt; WAF &gt; Custom Rules in the sidebar.</li>
                            <li>Create a rule named Bypass Lead Webhook Verification.</li>
                            <li>Match URI Path contains /wp-json/interior-design-lead-manager/v1/webhook.</li>
                            <li>Choose action Skip and skip WAF Managed Rules, Super Bot Fight Mode, and Security Level.</li>
                        </ol>
                    </article>

                    <article class="idlm-card" id="idlm-help-google-ads-creds">
                        <h3>Obtaining Google Ads Data Manager Credentials</h3>
                        <p class="idlm-card-desc">Instead of complex API applications, Google Ads Data Manager pulls audience segments and conversion events from secure HTTPS CSV feeds on your server. Follow these steps to link your feeds to Google Ads:</p>
                        <div class="idlm-help-steps" data-idlm-ui="mt-4">
                            <h4 class="idlm-help-step-title">1. View HTTPS source credentials</h4>
                            <ul class="idlm-help-list" data-idlm-ui="help-list">
                                <li>Navigate to the <strong>Sync &amp; Reminders</strong> tab.</li>
                                <li>Under the <strong>Google Ads Data Manager (HTTPS Pull Feeds)</strong> section, configure and save your custom <strong>HTTPS Source Username</strong> and <strong>HTTPS Source Password</strong>. Keep these credentials safe as they secure your endpoints.</li>
                            </ul>

                            <h4 class="idlm-help-step-title">2. Link Offline Conversion Import Feed</h4>
                            <ul class="idlm-help-list" data-idlm-ui="help-list">
                                <li>In Google Ads, open <strong>Goals &gt; Conversions &gt; Summary &gt; + New conversion action</strong>.</li>
                                <li>Select <strong>Import &gt; CRMs, files, or other data sources &gt; Connect a new data source</strong>.</li>
                                <li>Search and select <strong>HTTPS</strong> as the data source and click <strong>Continue</strong>.</li>
                                <li>Enter the <strong>Offline Conversions Feed URL</strong> (from the settings page), along with your configured HTTPS Source Username and Password, and click <strong>Next</strong>.</li>
                                <li>Map the CSV column headers (e.g. <code>Google Click ID</code>, <code>Conversion Name</code>, <code>Conversion Time</code>, <code>Conversion Value</code>) to the corresponding destination fields in Google Ads.</li>
                            </ul>

                            <h4 class="idlm-help-step-title">3. Link Customer Match Audience Feeds</h4>
                            <ul class="idlm-help-list" data-idlm-ui="help-list mb-2">
                                <li>In Google Ads, open <strong>Tools &gt; Shared library &gt; Audience manager &gt; + Create remarketing list &gt; + Customer list</strong>.</li>
                                <li>Under Data source, choose <strong>Connect a new data source &gt; HTTPS</strong> and click <strong>Continue</strong>.</li>
                                <li>Enter the specific rule's <strong>Feed URL</strong> (displayed directly inside your Sync Rules table below the Google Ads segment ID), along with your HTTPS Source credentials, and click <strong>Next</strong>.</li>
                                <li>Select <strong>Customer Match</strong> data type, map the fields (<code>Email</code>, <code>Phone</code>, <code>First Name</code>, <code>Last Name</code>, <code>Hashed Email</code>, <code>Hashed Phone</code>), and click <strong>Finish</strong>.</li>
                            </ul>
                        </div>
                    </article>

                    <article class="idlm-card">
                        <h3>Frequently Asked Questions (FAQs)</h3>
                        <div class="idlm-grid-full" data-idlm-ui="mt-4">
                            <div class="idlm-faq-item">
                                <h4>Q: How does the smart duplicate detection work?</h4>
                                <p>It normalizes all incoming phone numbers to digits only and performs a suffix match on the last 10 digits. This matches identical contacts regardless of international country prefixes or local punctuation styles.</p>
                            </div>
                            <div class="idlm-faq-item">
                                <h4>Q: Can I map custom stages to multiple platforms differently?</h4>
                                <p>Yes. Each platform CAPI setting has a unique stage-to-event configuration table under the Conversion APIs (CAPI) tab. Leaving any stage mapping value blank excludes it from synchronizing to that specific platform.</p>
                            </div>
                            <div class="idlm-faq-item">
                                <h4>Q: What happens if a lead is mapped to a stage with no click ID?</h4>
                                <p>For Google Ads, the lead must contain a Google Click ID (gclid, gbraid, or wbraid) to upload successfully. If no click ID is captured for the lead, the sync engine skips transmitting the conversion event to Google to avoid reporting errors.</p>
                            </div>
                        </div>
                    </article>
                </div>
            </section> <!-- End Tab: Help & FAQs -->
