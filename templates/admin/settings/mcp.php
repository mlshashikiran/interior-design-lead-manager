<?php
if (!defined('ABSPATH')) {
    exit;
}

$active_tab = isset($context['active_tab']) ? $context['active_tab'] : 'general';
$saved_api_key = IDLM_Admin_Settings_Renderer::credential_value('mcp_api_key');
$mcp_script_path = isset($context['mcp_script_path']) ? $context['mcp_script_path'] : '';
$mcp_rest_url = isset($context['mcp_rest_url']) ? $context['mcp_rest_url'] : '';
$mcp_local_json = isset($context['mcp_local_json']) ? $context['mcp_local_json'] : '';
$mcp_remote_json = wp_json_encode(array(
    'mcpServers' => array(
        'interior-design-crm' => array(
            'url'     => $mcp_rest_url,
            'headers' => array('X-IDLM-API-Key' => $saved_api_key),
        ),
    ),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$mcp_enabled = IDLM_Production_Security::is_mcp_enabled();
$mcp_allowed_tools = IDLM_Production_Security::mcp_allowed_tools();
?>
            <!-- Tab: AI Agent Access (MCP) -->
                <section id="settings-mcp" class="idlm-settings-tab-content <?php echo $active_tab === 'mcp' ? 'active' : ''; ?>" role="tabpanel" aria-labelledby="idlm-settings-tab-mcp" <?php echo $active_tab === 'mcp' ? '' : 'hidden'; ?>>
                <div class="idlm-grid-full">
                    <article class="idlm-card">
                        <h3>Model Context Protocol (MCP) Server Configuration</h3>
                        <p class="idlm-card-desc">Expose secure, read-only analytical database access to AI models to perform complex lead and sales representative analysis.</p>
                        <div class="idlm-alert idlm-mcp-status <?php echo $mcp_enabled ? 'idlm-alert-success' : 'idlm-alert-warning'; ?>">
                            <strong><?php echo $mcp_enabled ? 'MCP enabled' : 'MCP disabled'; ?></strong>
                            <span> for the <?php echo esc_html(IDLM_Production_Security::environment_type()); ?> environment.</span>
                            <?php if ($mcp_enabled): ?>
                                <span> Granted tools: <?php echo esc_html(implode(', ', $mcp_allowed_tools)); ?>.</span>
                            <?php else: ?>
                                <span> Production is fail-closed until <code>IDLM_MCP_ENABLED</code> is explicitly set.</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="idlm-tabs idlm-nested-tabs idlm-mcp-tabs" role="tablist" aria-label="MCP client setup">
                            <button type="button" id="idlm-mcp-tab-claude" class="idlm-tab-link active" role="tab" aria-selected="true" aria-controls="mcp-claude" onclick="switchMcpClientTab(this, 'mcp-claude')">Claude Desktop</button>
                            <button type="button" id="idlm-mcp-tab-antigravity" class="idlm-tab-link" role="tab" aria-selected="false" aria-controls="mcp-antigravity" tabindex="-1" onclick="switchMcpClientTab(this, 'mcp-antigravity')">Antigravity IDE</button>
                            <button type="button" id="idlm-mcp-tab-cursor" class="idlm-tab-link" role="tab" aria-selected="false" aria-controls="mcp-cursor" tabindex="-1" onclick="switchMcpClientTab(this, 'mcp-cursor')">Cursor</button>
                            <button type="button" id="idlm-mcp-tab-codex" class="idlm-tab-link" role="tab" aria-selected="false" aria-controls="mcp-codex" tabindex="-1" onclick="switchMcpClientTab(this, 'mcp-codex')">Codex CLI</button>
                        </div>

                        <div class="idlm-form-group full idlm-mcp-form-block">
                            <label for="idlm-mcp-api-key">Scoped MCP API key</label>
                            <div class="idlm-mcp-key-control" data-idlm-ui="flex">
                                <input type="password" id="idlm-mcp-api-key" class="idlm-input" autocomplete="new-password" value="<?php echo esc_attr($saved_api_key); ?>" readonly>
                                <button type="submit" form="idlm-rotate-mcp-form" class="idlm-action-link idlm-action-link-danger" data-idlm-confirm-submit data-idlm-confirm-title="Rotate MCP key?" data-idlm-confirm-message="Rotate the MCP key now? Existing remote MCP clients must be updated immediately." data-idlm-confirm-label="Rotate key" data-idlm-confirm-destructive="true">Rotate Key</button>
                            </div>
                            <p class="description">Remote clients must send this value in the <code>X-IDLM-API-Key</code> header.</p>
                        </div>

                        <!-- Subtab 1: Claude Desktop -->
                        <div id="mcp-claude" class="idlm-mcp-client-tab-content active" role="tabpanel" aria-labelledby="idlm-mcp-tab-claude">
                            <div class="idlm-form-group full">
                                <h4 data-idlm-ui="heading">Claude Desktop Settings</h4>
                                <p class="idlm-card-desc">Add the snippet below into the <code>mcpServers</code> section of your Claude configuration file (<code>~/Library/Application Support/Claude/claude_desktop_config.json</code> on macOS).</p>
                                
                                <h5 data-idlm-ui="option-heading">Option A: Remote HTTPS SSE (Recommended for Production)</h5>
                                <div data-idlm-ui="flex-between mb-2">
                                    <span class="idlm-card-desc idlm-heading-flush">Exposes secure internet access to your remote site.</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" data-idlm-ui="compact-button" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-claude-remote').textContent);">Copy Remote JSON</button>
                                </div>
                                <pre id="idlm-mcp-claude-remote" class="idlm-code-block idlm-mb-4"><?php echo esc_html($mcp_remote_json); ?></pre>

                                <h5 data-idlm-ui="option-heading-muted">Option B: Local Stdio Command (For Development)</h5>
                                <div data-idlm-ui="flex-between mb-2">
                                    <span class="idlm-card-desc idlm-heading-flush">Runs a local command line script directly on your computer.</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" data-idlm-ui="compact-button" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-claude-local').textContent);">Copy Local JSON</button>
                                </div>
                                <pre id="idlm-mcp-claude-local" class="idlm-code-block"><?php echo esc_html($mcp_local_json); ?></pre>
                            </div>
                        </div>

                        <!-- Subtab 2: Antigravity IDE -->
                        <div id="mcp-antigravity" class="idlm-mcp-client-tab-content" role="tabpanel" aria-labelledby="idlm-mcp-tab-antigravity" hidden>
                            <div class="idlm-form-group full">
                                <h4 data-idlm-ui="heading">Antigravity / Gemini Code Assist Setup</h4>
                                <p class="idlm-card-desc">Configure the Google Antigravity developer agent environment (<code>~/.gemini/antigravity/mcp_config.json</code>) to use this database MCP server.</p>
                                
                                <h5 data-idlm-ui="option-heading">Option A: Remote HTTPS SSE (Recommended for Production)</h5>
                                <div data-idlm-ui="flex-between mb-2">
                                    <span class="idlm-card-desc idlm-heading-flush">Connects the agent directly to your live production data.</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" data-idlm-ui="compact-button" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-antigravity-remote').textContent);">Copy JSON</button>
                                </div>
                                <pre id="idlm-mcp-antigravity-remote" class="idlm-code-block idlm-mb-4"><?php echo esc_html($mcp_remote_json); ?></pre>

                                <h5 data-idlm-ui="option-heading-muted">Option B: Local Stdio Command (For Development)</h5>
                                <div data-idlm-ui="flex-between mb-2">
                                    <span class="idlm-card-desc idlm-heading-flush">Connects the agent to your local environment database.</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" data-idlm-ui="compact-button" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-antigravity-local').textContent);">Copy JSON</button>
                                </div>
                                <pre id="idlm-mcp-antigravity-local" class="idlm-code-block"><?php echo esc_html($mcp_local_json); ?></pre>
                            </div>
                        </div>

                        <!-- Subtab 3: Cursor -->
                        <div id="mcp-cursor" class="idlm-mcp-client-tab-content" role="tabpanel" aria-labelledby="idlm-mcp-tab-cursor" hidden>
                            <div class="idlm-form-group full">
                                <h4 data-idlm-ui="heading">Cursor Editor Setup</h4>
                                <p class="idlm-card-desc">Navigate to **Cursor Settings > Features > MCP**, click **+ Add New MCP Server**, and configure using one of the options below:</p>
                                
                                <h5 data-idlm-ui="option-heading">Option A: Remote HTTPS SSE (Recommended for Production)</h5>
                                <table class="idlm-table idlm-table-soft">
                                    <tbody>
                                        <tr>
                                            <td data-idlm-ui="col-25"><strong>Name</strong></td>
                                            <td><code>interior-design-crm</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Type</strong></td>
                                            <td>Select <code>SSE</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>URL</strong></td>
                                            <td>
                                                <div class="idlm-mcp-copy-row">
                                                    <code id="cursor-sse-val" data-idlm-ui="code-wrap"><?php echo esc_url($mcp_rest_url); ?></code>
                                                    <button class="idlm-btn idlm-btn-secondary" type="button" data-idlm-ui="compact-button" onclick="idlmCopyToClipboard(this, document.getElementById('cursor-sse-val').textContent);">Copy URL</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Header</strong></td>
                                            <td><code>X-IDLM-API-Key: <?php echo esc_html($saved_api_key); ?></code></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h5 data-idlm-ui="option-heading-muted">Option B: Local Stdio Command (For Development)</h5>
                                <table class="idlm-table idlm-table-soft">
                                    <tbody>
                                        <tr>
                                            <td data-idlm-ui="col-25"><strong>Name</strong></td>
                                            <td><code>interior-design-crm</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Type</strong></td>
                                            <td>Select <code>command</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Command</strong></td>
                                            <td>
                                                <div class="idlm-mcp-copy-row">
                                                    <code id="cursor-cmd-val" data-idlm-ui="code-wrap">php "<?php echo esc_html($mcp_script_path); ?>"</code>
                                                    <button class="idlm-btn idlm-btn-secondary" type="button" data-idlm-ui="compact-button" onclick="idlmCopyToClipboard(this, document.getElementById('cursor-cmd-val').textContent);">Copy Command</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Subtab 4: Codex CLI -->
                        <div id="mcp-codex" class="idlm-mcp-client-tab-content" role="tabpanel" aria-labelledby="idlm-mcp-tab-codex" hidden>
                            <div class="idlm-form-group full">
                                <h4 data-idlm-ui="heading">Codex CLI Setup</h4>
                                <p class="idlm-card-desc">Configure the Codex CLI client to connect to your database over HTTPS (Remote) or Stdio command (Local):</p>
                                
                                <h5 data-idlm-ui="option-heading">Option A: Remote HTTPS SSE (Recommended for Production)</h5>
                                <p class="idlm-card-desc" data-idlm-ui="mb-2">Run this command to register the remote website endpoint:</p>
                                <div data-idlm-ui="flex-between mb-3">
                                    <code id="codex-cmd-remote" class="idlm-code-command">codex mcp add-url interior-design-crm "<?php echo esc_url($mcp_rest_url); ?>" --header "X-IDLM-API-Key: <?php echo esc_html($saved_api_key); ?>"</code>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" data-idlm-ui="compact-button" onclick="idlmCopyToClipboard(this, document.getElementById('codex-cmd-remote').textContent);">Copy</button>
                                </div>
                                <p class="idlm-card-desc" data-idlm-ui="mb-2">Or append the following block directly to your configuration file (<code>~/.codex/config.toml</code>):</p>
                                <div data-idlm-ui="flex-between mb-2">
                                    <span class="idlm-card-desc idlm-heading-flush" data-idlm-ui="strong">TOML Configuration</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" data-idlm-ui="compact-button" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-codex-toml-remote').textContent);">Copy TOML</button>
                                </div>
                                <pre id="idlm-mcp-codex-toml-remote" class="idlm-code-block idlm-mb-4">[mcp_servers.interior-design-crm]
url = "<?php echo esc_url($mcp_rest_url); ?>"
[mcp_servers.interior-design-crm.http_headers]
X-IDLM-API-Key = "<?php echo esc_html($saved_api_key); ?>"</pre>

                                <h5 data-idlm-ui="option-heading-muted mt-5">Option B: Local Stdio Command (For Development)</h5>
                                <p class="idlm-card-desc" data-idlm-ui="mb-2">Run this command to register your local server file:</p>
                                <div data-idlm-ui="flex-between mb-3">
                                    <code id="codex-cmd-local" class="idlm-code-command">codex mcp add interior-design-crm -- php "<?php echo esc_html($mcp_script_path); ?>"</code>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" data-idlm-ui="compact-button" onclick="idlmCopyToClipboard(this, document.getElementById('codex-cmd-local').textContent);">Copy</button>
                                </div>
                                <p class="idlm-card-desc" data-idlm-ui="mb-2">Or append the following block directly to your configuration file (<code>~/.codex/config.toml</code>):</p>
                                <div data-idlm-ui="flex-between mb-2">
                                    <span class="idlm-card-desc idlm-heading-flush" data-idlm-ui="strong">TOML Configuration</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" data-idlm-ui="compact-button" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-codex-toml-local').textContent);">Copy TOML</button>
                                </div>
                                <pre id="idlm-mcp-codex-toml-local" class="idlm-code-block">[mcp_servers.interior-design-crm]
command = "php"
args = ["<?php echo esc_html($mcp_script_path); ?>"]</pre>
                            </div>
                        </div>

                        <div class="idlm-form-group full idlm-settings-divider">
                            <h4 data-idlm-ui="heading">Security &amp; Access Sandbox</h4>
                            <ul class="idlm-security-list">
                                <li><strong>Read-Only Restraints</strong>: The MCP server strictly executes only <code>SELECT</code> queries. Any writing, modifying, or structure altering statements are automatically rejected.</li>
                                <li><strong>Table Isolation</strong>: Access is strictly sandboxed to the CRM plugin custom tables (leads, activities, sync logs, cost mappings) and the <code>wp_users</code> table. Attempts to read other WordPress database tables (like <code>wp_options</code> or <code>wp_posts</code>) are blocked.</li>
                                <li><strong>Deployment Gate</strong>: Production HTTP access is disabled by default. Enable it explicitly, grant only approved tools, and use <code>IDLM_MCP_DISABLED</code> as the emergency shutdown switch.</li>
                            </ul>
                        </div>
                    </article>
                </div>
            </section> <!-- End Tab: AI Agent Access (MCP) -->
