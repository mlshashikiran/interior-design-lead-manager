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
            <section id="settings-mcp" class="idlm-settings-tab-content <?php echo $active_tab === 'mcp' ? 'active' : ''; ?>" style="display: <?php echo $active_tab === 'mcp' ? 'block' : 'none'; ?>;">
                <div class="idlm-grid-full">
                    <article class="idlm-card">
                        <h3>Model Context Protocol (MCP) Server Configuration</h3>
                        <p class="idlm-card-desc">Expose secure, read-only analytical database access to AI models to perform complex lead and sales representative analysis.</p>
                        <div class="idlm-alert <?php echo $mcp_enabled ? 'idlm-alert-success' : 'idlm-alert-warning'; ?>" style="margin: 12px 0;">
                            <strong><?php echo $mcp_enabled ? 'MCP enabled' : 'MCP disabled'; ?></strong>
                            <span> for the <?php echo esc_html(IDLM_Production_Security::environment_type()); ?> environment.</span>
                            <?php if ($mcp_enabled): ?>
                                <span> Granted tools: <?php echo esc_html(implode(', ', $mcp_allowed_tools)); ?>.</span>
                            <?php else: ?>
                                <span> Production is fail-closed until <code>IDLM_MCP_ENABLED</code> is explicitly set.</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="idlm-tabs idlm-nested-tabs" style="margin-bottom: 20px; margin-top: 15px;">
                            <button type="button" class="idlm-tab-link active" onclick="switchMcpClientTab(this, 'mcp-claude')">Claude Desktop</button>
                            <button type="button" class="idlm-tab-link" onclick="switchMcpClientTab(this, 'mcp-antigravity')">Antigravity IDE</button>
                            <button type="button" class="idlm-tab-link" onclick="switchMcpClientTab(this, 'mcp-cursor')">Cursor</button>
                            <button type="button" class="idlm-tab-link" onclick="switchMcpClientTab(this, 'mcp-codex')">Codex CLI</button>
                        </div>

                        <div class="idlm-form-group full" style="margin: 15px 0 20px;">
                            <label>Scoped MCP API Key</label>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="text" class="idlm-input" value="<?php echo esc_attr($saved_api_key); ?>" readonly>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Rotate the MCP key now? Existing remote MCP clients must be updated immediately.');">
                                    <input type="hidden" name="action" value="idlm_rotate_api_credential">
                                    <input type="hidden" name="scope" value="mcp">
                                    <?php wp_nonce_field('idlm_rotate_api_credential_mcp'); ?>
                                    <button type="submit" class="idlm-btn idlm-btn-secondary">Rotate Key</button>
                                </form>
                            </div>
                            <p class="description">Remote clients must send this value in the <code>X-IDLM-API-Key</code> header.</p>
                        </div>

                        <!-- Subtab 1: Claude Desktop -->
                        <div id="mcp-claude" class="idlm-mcp-client-tab-content active">
                            <div class="idlm-form-group full">
                                <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700;">Claude Desktop Settings</h4>
                                <p class="idlm-card-desc">Add the snippet below into the <code>mcpServers</code> section of your Claude configuration file (<code>~/Library/Application Support/Claude/claude_desktop_config.json</code> on macOS).</p>
                                
                                <h5 style="margin: 15px 0 5px 0; font-size: 12px; font-weight: 600; color: var(--idlm-primary);">Option A: Remote HTTPS SSE (Recommended for Production)</h5>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="idlm-card-desc" style="margin: 0;">Exposes secure internet access to your remote site.</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" style="padding: 4px 8px; font-size: 11px;" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-claude-remote').textContent);">Copy Remote JSON</button>
                                </div>
                                <pre id="idlm-mcp-claude-remote" style="background: var(--idlm-surface-tint); border: 1px solid var(--idlm-border); padding: 15px; border-radius: 8px; font-family: monospace; overflow-x: auto; color: var(--idlm-text); margin: 0 0 15px 0; font-size: 12px; line-height: 1.4;"><?php echo esc_html($mcp_remote_json); ?></pre>

                                <h5 style="margin: 15px 0 5px 0; font-size: 12px; font-weight: 600; color: var(--idlm-muted);">Option B: Local Stdio Command (For Development)</h5>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="idlm-card-desc" style="margin: 0;">Runs a local command line script directly on your computer.</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" style="padding: 4px 8px; font-size: 11px;" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-claude-local').textContent);">Copy Local JSON</button>
                                </div>
                                <pre id="idlm-mcp-claude-local" style="background: var(--idlm-surface-tint); border: 1px solid var(--idlm-border); padding: 15px; border-radius: 8px; font-family: monospace; overflow-x: auto; color: var(--idlm-text); margin: 0; font-size: 12px; line-height: 1.4;"><?php echo esc_html($mcp_local_json); ?></pre>
                            </div>
                        </div>

                        <!-- Subtab 2: Antigravity IDE -->
                        <div id="mcp-antigravity" class="idlm-mcp-client-tab-content" style="display: none;">
                            <div class="idlm-form-group full">
                                <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700;">Antigravity / Gemini Code Assist Setup</h4>
                                <p class="idlm-card-desc">Configure the Google Antigravity developer agent environment (<code>~/.gemini/antigravity/mcp_config.json</code>) to use this database MCP server.</p>
                                
                                <h5 style="margin: 15px 0 5px 0; font-size: 12px; font-weight: 600; color: var(--idlm-primary);">Option A: Remote HTTPS SSE (Recommended for Production)</h5>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="idlm-card-desc" style="margin: 0;">Connects the agent directly to your live production data.</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" style="padding: 4px 8px; font-size: 11px;" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-antigravity-remote').textContent);">Copy JSON</button>
                                </div>
                                <pre id="idlm-mcp-antigravity-remote" style="background: var(--idlm-surface-tint); border: 1px solid var(--idlm-border); padding: 15px; border-radius: 8px; font-family: monospace; overflow-x: auto; color: var(--idlm-text); margin: 0 0 15px 0; font-size: 12px; line-height: 1.4;"><?php echo esc_html($mcp_remote_json); ?></pre>

                                <h5 style="margin: 15px 0 5px 0; font-size: 12px; font-weight: 600; color: var(--idlm-muted);">Option B: Local Stdio Command (For Development)</h5>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="idlm-card-desc" style="margin: 0;">Connects the agent to your local environment database.</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" style="padding: 4px 8px; font-size: 11px;" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-antigravity-local').textContent);">Copy JSON</button>
                                </div>
                                <pre id="idlm-mcp-antigravity-local" style="background: var(--idlm-surface-tint); border: 1px solid var(--idlm-border); padding: 15px; border-radius: 8px; font-family: monospace; overflow-x: auto; color: var(--idlm-text); margin: 0; font-size: 12px; line-height: 1.4;"><?php echo esc_html($mcp_local_json); ?></pre>
                            </div>
                        </div>

                        <!-- Subtab 3: Cursor -->
                        <div id="mcp-cursor" class="idlm-mcp-client-tab-content" style="display: none;">
                            <div class="idlm-form-group full">
                                <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700;">Cursor Editor Setup</h4>
                                <p class="idlm-card-desc">Navigate to **Cursor Settings > Features > MCP**, click **+ Add New MCP Server**, and configure using one of the options below:</p>
                                
                                <h5 style="margin: 15px 0 8px 0; font-size: 12px; font-weight: 600; color: var(--idlm-primary);">Option A: Remote HTTPS SSE (Recommended for Production)</h5>
                                <table class="idlm-table" style="margin-bottom: 15px; background: var(--idlm-surface-soft); border: 1px solid var(--idlm-border);">
                                    <tbody>
                                        <tr>
                                            <td style="width: 25%;"><strong>Name</strong></td>
                                            <td><code>interior-design-crm</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Type</strong></td>
                                            <td>Select <code>SSE</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>URL</strong></td>
                                            <td>
                                                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                                    <code id="cursor-sse-val" style="font-size: 11px; word-break: break-all;"><?php echo esc_url($mcp_rest_url); ?></code>
                                                    <button class="idlm-btn idlm-btn-secondary" type="button" style="padding: 4px 8px; font-size: 10px;" onclick="idlmCopyToClipboard(this, document.getElementById('cursor-sse-val').textContent);">Copy URL</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Header</strong></td>
                                            <td><code>X-IDLM-API-Key: <?php echo esc_html($saved_api_key); ?></code></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h5 style="margin: 15px 0 8px 0; font-size: 12px; font-weight: 600; color: var(--idlm-muted);">Option B: Local Stdio Command (For Development)</h5>
                                <table class="idlm-table" style="margin-bottom: 5px; background: var(--idlm-surface-soft); border: 1px solid var(--idlm-border);">
                                    <tbody>
                                        <tr>
                                            <td style="width: 25%;"><strong>Name</strong></td>
                                            <td><code>interior-design-crm</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Type</strong></td>
                                            <td>Select <code>command</code></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Command</strong></td>
                                            <td>
                                                <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                                    <code id="cursor-cmd-val" style="font-size: 11px;">php "<?php echo esc_html($mcp_script_path); ?>"</code>
                                                    <button class="idlm-btn idlm-btn-secondary" type="button" style="padding: 4px 8px; font-size: 10px;" onclick="idlmCopyToClipboard(this, document.getElementById('cursor-cmd-val').textContent);">Copy Command</button>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Subtab 4: Codex CLI -->
                        <div id="mcp-codex" class="idlm-mcp-client-tab-content" style="display: none;">
                            <div class="idlm-form-group full">
                                <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700;">Codex CLI Setup</h4>
                                <p class="idlm-card-desc">Configure the Codex CLI client to connect to your database over HTTPS (Remote) or Stdio command (Local):</p>
                                
                                <h5 style="margin: 15px 0 5px 0; font-size: 12px; font-weight: 600; color: var(--idlm-primary);">Option A: Remote HTTPS SSE (Recommended for Production)</h5>
                                <p class="idlm-card-desc" style="margin-bottom: 8px;">Run this command to register the remote website endpoint:</p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                    <code id="codex-cmd-remote" style="background: var(--idlm-surface-tint); border: 1px solid var(--idlm-border); padding: 8px; border-radius: 6px; font-family: monospace; overflow-x: auto; color: var(--idlm-text); width: 85%; font-size: 11px;">codex mcp add-url interior-design-crm "<?php echo esc_url($mcp_rest_url); ?>" --header "X-IDLM-API-Key: <?php echo esc_html($saved_api_key); ?>"</code>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" style="padding: 4px 8px; font-size: 11px;" onclick="idlmCopyToClipboard(this, document.getElementById('codex-cmd-remote').textContent);">Copy</button>
                                </div>
                                <p class="idlm-card-desc" style="margin-bottom: 8px;">Or append the following block directly to your configuration file (<code>~/.codex/config.toml</code>):</p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="idlm-card-desc" style="margin: 0; font-weight: 600;">TOML Configuration</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" style="padding: 4px 8px; font-size: 11px;" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-codex-toml-remote').textContent);">Copy TOML</button>
                                </div>
                                <pre id="idlm-mcp-codex-toml-remote" style="background: var(--idlm-surface-tint); border: 1px solid var(--idlm-border); padding: 15px; border-radius: 8px; font-family: monospace; overflow-x: auto; color: var(--idlm-text); margin: 0 0 15px 0; font-size: 12px; line-height: 1.4;">[mcp_servers.interior-design-crm]
url = "<?php echo esc_url($mcp_rest_url); ?>"
[mcp_servers.interior-design-crm.http_headers]
X-IDLM-API-Key = "<?php echo esc_html($saved_api_key); ?>"</pre>

                                <h5 style="margin: 20px 0 5px 0; font-size: 12px; font-weight: 600; color: var(--idlm-muted);">Option B: Local Stdio Command (For Development)</h5>
                                <p class="idlm-card-desc" style="margin-bottom: 8px;">Run this command to register your local server file:</p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                    <code id="codex-cmd-local" style="background: var(--idlm-surface-tint); border: 1px solid var(--idlm-border); padding: 8px; border-radius: 6px; font-family: monospace; overflow-x: auto; color: var(--idlm-text); width: 85%; font-size: 11px;">codex mcp add interior-design-crm -- php "<?php echo esc_html($mcp_script_path); ?>"</code>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" style="padding: 4px 8px; font-size: 11px;" onclick="idlmCopyToClipboard(this, document.getElementById('codex-cmd-local').textContent);">Copy</button>
                                </div>
                                <p class="idlm-card-desc" style="margin-bottom: 8px;">Or append the following block directly to your configuration file (<code>~/.codex/config.toml</code>):</p>
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                                    <span class="idlm-card-desc" style="margin: 0; font-weight: 600;">TOML Configuration</span>
                                    <button class="idlm-btn idlm-btn-secondary" type="button" style="padding: 4px 8px; font-size: 11px;" onclick="idlmCopyToClipboard(this, document.getElementById('idlm-mcp-codex-toml-local').textContent);">Copy TOML</button>
                                </div>
                                <pre id="idlm-mcp-codex-toml-local" style="background: var(--idlm-surface-tint); border: 1px solid var(--idlm-border); padding: 15px; border-radius: 8px; font-family: monospace; overflow-x: auto; color: var(--idlm-text); margin: 0; font-size: 12px; line-height: 1.4;">[mcp_servers.interior-design-crm]
command = "php"
args = ["<?php echo esc_html($mcp_script_path); ?>"]</pre>
                            </div>
                        </div>

                        <div class="idlm-form-group full" style="margin-top: 25px; border-top: 1px solid var(--idlm-border); padding-top: 20px;">
                            <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 700;">Security &amp; Access Sandbox</h4>
                            <ul style="margin-left: 20px; list-style-type: disc; line-height: 1.5; color: var(--idlm-text); font-size: 13px; margin-bottom: 0;">
                                <li><strong>Read-Only Restraints</strong>: The MCP server strictly executes only <code>SELECT</code> queries. Any writing, modifying, or structure altering statements are automatically rejected.</li>
                                <li><strong>Table Isolation</strong>: Access is strictly sandboxed to the CRM plugin custom tables (leads, activities, sync logs, cost mappings) and the <code>wp_users</code> table. Attempts to read other WordPress database tables (like <code>wp_options</code> or <code>wp_posts</code>) are blocked.</li>
                                <li><strong>Deployment Gate</strong>: Production HTTP access is disabled by default. Enable it explicitly, grant only approved tools, and use <code>IDLM_MCP_DISABLED</code> as the emergency shutdown switch.</li>
                            </ul>
                        </div>
                    </article>
                </div>
            </section> <!-- End Tab: AI Agent Access (MCP) -->
