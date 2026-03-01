<?php
/**
 * MCP Add-on: Admin settings page
 * Renders UI for managing MCP servers (add, edit, test, delete).
 *
 * @package AIChat
 * @subpackage MCP
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Render MCP Servers admin page
 */
function aichat_mcp_render_servers_page()
{
	if (! current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have sufficient permissions.', 'axiachat-ai'));
	}

	// Get saved servers (array of server configs)
	$servers = get_option('aichat_mcp_servers', []);
	if (! is_array($servers)) {
		$servers = [];
	}

	$is_pro = function_exists('aichat_mcp_has_pro') && aichat_mcp_has_pro();
	$max_servers = function_exists('aichat_mcp_max_servers_allowed') ? aichat_mcp_max_servers_allowed() : 1;
	$server_count = count($servers);
	$can_create = $server_count < $max_servers;

	?>
    <div class="wrap aichat-mcp-wrap">
        <h1 class="wp-heading-inline">
            <span class="dashicons dashicons-admin-plugins" style="color:#6f42c1"></span>
            <?php echo esc_html__('MCP Servers', 'axiachat-ai'); ?>
        </h1>
        <a href="#" id="aichat-mcp-add-server" class="page-title-action" <?php echo ! $can_create ? 'style="pointer-events:none;opacity:0.5;" title="' . esc_attr__('Upgrade to Pro for more servers', 'axiachat-ai') . '"' : ''; ?>><?php echo esc_html__('Add Server', 'axiachat-ai'); ?></a>
        <p class="description">
            <?php echo esc_html__('Configure external MCP servers to expose their tools to your AI bots.', 'axiachat-ai'); ?>
        </p>
        
        <?php if (! $is_pro) : ?>
        <!-- Free Plan Banner -->
        <div class="notice notice-info inline" style="margin-top:15px;padding:12px 15px;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:10px;">
                <span class="dashicons dashicons-admin-plugins" style="color:#2271b1;"></span>
                <div>
                    <strong><?php echo esc_html__('Free Plan', 'axiachat-ai'); ?>:</strong>
                    <?php echo esc_html($server_count); ?>/<?php echo esc_html($max_servers); ?> <?php echo esc_html__('MCP server used', 'axiachat-ai'); ?>
                    <?php if ($server_count >= $max_servers) : ?>
                        <span style="background:#dc3545;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;margin-left:8px;"><?php echo esc_html__('Limit reached', 'axiachat-ai'); ?></span>
                    <?php endif; ?>
                    <span style="color:#666;margin-left:8px;">—</span>
                    <span style="color:#666;margin-left:5px;"><?php echo esc_html__('Pro has unlimited servers', 'axiachat-ai'); ?></span>
                </div>
            </div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=aichat-settings-pricing')); ?>" class="button button-primary" style="display:flex;align-items:center;gap:5px;">
                <span class="dashicons dashicons-superhero" style="font-size:16px;width:16px;height:16px;"></span>
                <?php echo esc_html__('Upgrade to Pro', 'axiachat-ai'); ?>
            </a>
        </div>
        <?php endif; ?>

        <!-- Navigation Tabs -->
        <nav class="nav-tab-wrapper wp-clearfix" style="margin-top:20px;">
            <a href="#tab-servers" class="nav-tab nav-tab-active" id="tab-servers-link">
                <span class="dashicons dashicons-admin-plugins"></span>
                <?php echo esc_html__('Servers', 'axiachat-ai'); ?>
            </a>
            <a href="#tab-test-tools" class="nav-tab" id="tab-test-tools-link">
                <span class="dashicons dashicons-admin-tools"></span>
                <?php echo esc_html__('Test Tools', 'axiachat-ai'); ?>
            </a>
            <a href="#tab-manage-tools" class="nav-tab" id="tab-manage-tools-link">
                <span class="dashicons dashicons-admin-settings"></span>
                <?php echo esc_html__('Enable/Disable Tools', 'axiachat-ai'); ?>
            </a>
        </nav>

        <!-- Tab: Servers List -->
        <div id="tab-servers" class="aichat-mcp-tab-content">
        <?php if (empty($servers)): ?>
            <div class="notice notice-info inline" style="margin-top:20px;">
                <p><strong><?php echo esc_html__('No MCP servers configured yet.', 'axiachat-ai'); ?></strong></p>
                <p><?php echo esc_html__('Click "Add Server" to connect to an external MCP server (Sentry, Notion, GitHub, local tools, etc.).', 'axiachat-ai'); ?></p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top:20px;">
                <thead>
                    <tr>
                        <th style="width:20%;"><?php echo esc_html__('Name', 'axiachat-ai'); ?></th>
                        <th style="width:10%;"><?php echo esc_html__('Transport', 'axiachat-ai'); ?></th>
                        <th style="width:30%;"><?php echo esc_html__('Endpoint', 'axiachat-ai'); ?></th>
                        <th style="width:10%;"><?php echo esc_html__('Enabled', 'axiachat-ai'); ?></th>
                        <th style="width:15%;"><?php echo esc_html__('Status', 'axiachat-ai'); ?></th>
                        <th style="width:15%;"><?php echo esc_html__('Actions', 'axiachat-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($servers as $server_id => $server): ?>
                        <tr data-server-id="<?php echo esc_attr($server_id); ?>">
                            <td><strong><?php echo esc_html($server['name'] ?? $server_id); ?></strong></td>
                            <td>
                                <?php
								$transport_type = strtolower($server['transport'] ?? 'http');
                    	if ($transport_type === 'http') {
                    		echo esc_html__('HTTP', 'axiachat-ai');
                    	} else {
                    		echo esc_html__('STDIO (deprecated)', 'axiachat-ai');
                    	}
                    	?>
                            </td>
                            <td>
                                <?php
                    	if ($transport_type === 'http') {
                    		echo esc_html($server['url'] ?? '');
                    	} else {
                    		echo esc_html__('Update required: provide an HTTP endpoint.', 'axiachat-ai');
                    	}
                    	?>
                            </td>
                            <td>
                                <?php $is_enabled = isset($server['enabled']) ? (bool) $server['enabled'] : true; ?>
                                <label class="aichat-mcp-toggle">
                                    <input type="checkbox" class="aichat-mcp-toggle-enabled" 
                                           data-server-id="<?php echo esc_attr($server_id); ?>"
                                           <?php checked($is_enabled); ?> />
                                    <span class="aichat-mcp-toggle-slider"></span>
                                </label>
                            </td>
                            <td>
                                <span class="aichat-mcp-status" data-server-id="<?php echo esc_attr($server_id); ?>">
                                    <span class="dashicons dashicons-minus" style="color:#999;"></span>
                                    <?php echo esc_html__('Unknown', 'axiachat-ai'); ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" class="button button-small aichat-mcp-test" data-server-id="<?php echo esc_attr($server_id); ?>">
                                    <?php echo esc_html__('Test', 'axiachat-ai'); ?>
                                </button>
                                <button type="button" class="button button-small aichat-mcp-edit" data-server-id="<?php echo esc_attr($server_id); ?>">
                                    <?php echo esc_html__('Edit', 'axiachat-ai'); ?>
                                </button>
                                <button type="button" class="button button-small button-link-delete aichat-mcp-delete" data-server-id="<?php echo esc_attr($server_id); ?>">
                                    <?php echo esc_html__('Delete', 'axiachat-ai'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>

        <!-- Tab: Test Tools -->
        <div id="tab-test-tools" class="aichat-mcp-tab-content" style="display:none;">
            <div style="max-width:900px;margin:20px 0;">
                <div class="card">
                    <div class="card-header" style="background:#f6f7f7;padding:15px;border-bottom:1px solid #dcdcde;">
                        <h2 style="margin:0;font-size:14px;">
                            <span class="dashicons dashicons-admin-tools" style="color:#2271b1;"></span>
                            <?php echo esc_html__('Test MCP Tools', 'axiachat-ai'); ?>
                        </h2>
                    </div>
                    <div style="padding:20px;">
                        <!-- Server Selection -->
                        <div style="margin-bottom:20px;">
                            <label for="aichat-mcp-test-server-select" style="display:block;margin-bottom:8px;font-weight:600;">
                                <?php echo esc_html__('Select MCP Server', 'axiachat-ai'); ?>
                            </label>
                            <select id="aichat-mcp-test-server-select" class="regular-text" style="width:100%;max-width:400px;">
                                <option value=""><?php echo esc_html__('— Select a server —', 'axiachat-ai'); ?></option>
                            </select>
                        </div>

                        <!-- Tool Selection -->
                        <div style="margin-bottom:20px;">
                            <label for="aichat-mcp-test-tool-select" style="display:block;margin-bottom:8px;font-weight:600;">
                                <?php echo esc_html__('Select Tool', 'axiachat-ai'); ?>
                            </label>
                            <select id="aichat-mcp-test-tool-select" class="regular-text" style="width:100%;max-width:400px;" disabled>
                                <option value=""><?php echo esc_html__('— Select a server first —', 'axiachat-ai'); ?></option>
                            </select>
                        </div>

                        <!-- Tool Description -->
                        <div id="aichat-mcp-test-tool-description" style="margin-bottom:20px;padding:10px;background:#f0f6fc;border-left:3px solid #2271b1;display:none;">
                            <strong><?php echo esc_html__('Description:', 'axiachat-ai'); ?></strong>
                            <p id="aichat-mcp-test-tool-desc-text" style="margin:5px 0 0 0;"></p>
                        </div>

                        <!-- Dynamic Form -->
                        <div id="aichat-mcp-test-tool-form" style="margin-bottom:20px;"></div>

                        <!-- Run Button -->
                        <div style="margin-bottom:20px;">
                            <button type="button" class="button button-primary" id="aichat-mcp-test-tool-run" disabled>
                                <span class="dashicons dashicons-controls-play" style="margin-top:3px;"></span>
                                <?php echo esc_html__('Execute Tool', 'axiachat-ai'); ?>
                            </button>
                            <span id="aichat-mcp-test-tool-status" style="margin-left:10px;color:#666;"></span>
                        </div>

                        <!-- Result Display -->
                        <div>
                            <label style="display:block;margin-bottom:8px;font-weight:600;">
                                <?php echo esc_html__('Result', 'axiachat-ai'); ?>
                            </label>
                            <pre id="aichat-mcp-test-tool-result" style="background:#0d1117;color:#c9d1d9;padding:15px;border-radius:6px;overflow:auto;max-height:400px;white-space:pre-wrap;word-wrap:break-word;font-family:Consolas,Monaco,monospace;font-size:13px;line-height:1.5;"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab: Enable/Disable Tools -->
    <div id="tab-manage-tools" class="aichat-mcp-tab-content" style="display:none;">
        <div class="card" style="margin-top:20px;max-width:none;">
            <h2><?php echo esc_html__('Enable/Disable MCP Tools', 'axiachat-ai'); ?></h2>
            <p class="description">
                <?php echo esc_html__('Control which tools from each MCP server are available to your AI bots. Disabled tools will not be sent to the AI model.', 'axiachat-ai'); ?>
            </p>

            <div style="margin-top:20px;">
                <!-- Server Selection -->
                <div style="margin-bottom:20px;">
                    <label for="aichat-mcp-manage-server-select" style="display:block;margin-bottom:8px;font-weight:600;">
                        <?php echo esc_html__('Select MCP Server', 'axiachat-ai'); ?>
                    </label>
                    <select id="aichat-mcp-manage-server-select" class="regular-text" style="min-width:350px;">
                        <option value=""><?php echo esc_html__('-- Select a server --', 'axiachat-ai'); ?></option>
                        <?php foreach ($servers as $sid => $srv): ?>
                            <?php if (isset($srv['enabled']) && $srv['enabled']): ?>
                                <option value="<?php echo esc_attr($sid); ?>">
                                    <?php echo esc_html($srv['name'] ?? $sid); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Loading Indicator -->
                <div id="aichat-mcp-manage-loading" style="display:none;padding:20px;text-align:center;">
                    <span class="spinner is-active" style="float:none;"></span>
                    <p><?php echo esc_html__('Loading tools...', 'axiachat-ai'); ?></p>
                </div>

                <!-- Tools List -->
                <div id="aichat-mcp-manage-tools-container" style="display:none;">
                    <div style="margin-bottom:15px;">
                        <button type="button" class="button" id="aichat-mcp-enable-all">
                            <span class="dashicons dashicons-yes"></span>
                            <?php echo esc_html__('Enable All', 'axiachat-ai'); ?>
                        </button>
                        <button type="button" class="button" id="aichat-mcp-disable-all">
                            <span class="dashicons dashicons-dismiss"></span>
                            <?php echo esc_html__('Disable All', 'axiachat-ai'); ?>
                        </button>
                    </div>

                    <div id="aichat-mcp-manage-tools-list" style="max-height:500px;overflow-y:auto;border:1px solid #ddd;padding:15px;background:#fff;">
                        <!-- Tools will be loaded here via AJAX -->
                    </div>

                    <div style="margin-top:20px;">
                        <button type="button" class="button button-primary button-large" id="aichat-mcp-save-tools-status">
                            <span class="dashicons dashicons-saved"></span>
                            <?php echo esc_html__('Save Changes', 'axiachat-ai'); ?>
                        </button>
                        <span id="aichat-mcp-save-status" style="margin-left:15px;"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Server Modal -->
    <div id="aichat-mcp-modal" style="display:none;">
        <div class="aichat-mcp-modal-backdrop"></div>
        <div class="aichat-mcp-modal-content">
            <div class="aichat-mcp-modal-header">
                <h2 id="aichat-mcp-modal-title"><?php echo esc_html__('Add MCP Server', 'axiachat-ai'); ?></h2>
                <button type="button" class="aichat-mcp-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="aichat-mcp-modal-body">
                <form id="aichat-mcp-server-form">
                    <input type="hidden" id="mcp-server-id" name="server_id" value="" />
                    <input type="hidden" name="transport" value="http" />

                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="mcp-name"><?php echo esc_html__('Server Name', 'axiachat-ai'); ?></label></th>
                            <td>
                                <input type="text" id="mcp-name" name="name" class="regular-text" required />
                                <p class="description"><?php echo esc_html__('Descriptive name (e.g., "Sentry Tools", "Notion Database").', 'axiachat-ai'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div id="mcp-http-config">
                        <h3><?php echo esc_html__('HTTP Configuration', 'axiachat-ai'); ?></h3>
                        <p class="description"><?php echo esc_html__('Only HTTP/HTTPS MCP servers are supported.', 'axiachat-ai'); ?></p>
                        <div id="mcp-unsupported-transport" class="notice notice-warning" style="display:none;margin:0 0 15px;">
                            <p><strong><?php echo esc_html__('STDIO connections have been removed.', 'axiachat-ai'); ?></strong> <?php echo esc_html__('Update this server with an HTTP/HTTPS endpoint to continue using it.', 'axiachat-ai'); ?></p>
                        </div>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="mcp-url"><?php echo esc_html__('Server URL', 'axiachat-ai'); ?></label></th>
                                <td>
                                    <input type="url" id="mcp-url" name="url" class="regular-text" placeholder="https://api.example.com/mcp" />
                                    <p class="description"><?php echo esc_html__('Full URL to the MCP endpoint.', 'axiachat-ai'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="mcp-auth-type"><?php echo esc_html__('Authentication', 'axiachat-ai'); ?></label></th>
                                <td>
                                    <select id="mcp-auth-type" name="auth_type" class="regular-text">
                                        <option value="none"><?php echo esc_html__('None', 'axiachat-ai'); ?></option>
                                        <option value="bearer"><?php echo esc_html__('Bearer Token', 'axiachat-ai'); ?></option>
                                        <option value="api_key"><?php echo esc_html__('API Key (custom header)', 'axiachat-ai'); ?></option>
                                        <option value="custom"><?php echo esc_html__('Custom Headers', 'axiachat-ai'); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr id="mcp-auth-token-row" style="display:none;">
                                <th scope="row"><label for="mcp-auth-token"><?php echo esc_html__('Token / API Key', 'axiachat-ai'); ?></label></th>
                                <td>
                                    <input type="password" id="mcp-auth-token" name="auth_token" class="regular-text" autocomplete="off" />
                                </td>
                            </tr>
                            <tr id="mcp-auth-header-row" style="display:none;">
                                <th scope="row"><label for="mcp-auth-header"><?php echo esc_html__('Header Name', 'axiachat-ai'); ?></label></th>
                                <td>
                                    <input type="text" id="mcp-auth-header" name="auth_header" class="regular-text" placeholder="X-API-Key" />
                                </td>
                            </tr>
                            <tr id="mcp-custom-headers-row" style="display:none;">
                                <th scope="row"><label for="mcp-custom-headers"><?php echo esc_html__('Custom Headers', 'axiachat-ai'); ?></label></th>
                                <td>
                                    <textarea id="mcp-custom-headers" name="custom_headers" class="large-text" rows="4" placeholder='{"X-Custom-Header": "value"}'></textarea>
                                    <p class="description"><?php echo esc_html__('JSON object with custom headers.', 'axiachat-ai'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="aichat-mcp-modal-footer">
                        <button type="button" class="button button-secondary aichat-mcp-modal-close"><?php echo esc_html__('Cancel', 'axiachat-ai'); ?></button>
                        <button type="submit" class="button button-primary"><?php echo esc_html__('Save Server', 'axiachat-ai'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Test Results Modal -->
    <div id="aichat-mcp-test-modal" style="display:none;">
        <div class="aichat-mcp-modal-backdrop"></div>
        <div class="aichat-mcp-modal-content">
            <div class="aichat-mcp-modal-header">
                <h2><?php echo esc_html__('Test Connection', 'axiachat-ai'); ?></h2>
                <button type="button" class="aichat-mcp-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="aichat-mcp-modal-body">
                <div id="aichat-mcp-test-results"></div>
            </div>
            <div class="aichat-mcp-modal-footer">
                <button type="button" class="button button-secondary aichat-mcp-modal-close"><?php echo esc_html__('Close', 'axiachat-ai'); ?></button>
            </div>
        </div>
    </div>

    <style>
        .aichat-mcp-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 100000;
        }
        .aichat-mcp-modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.3);
            z-index: 100001;
            max-width: 700px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        .aichat-mcp-modal-header {
            padding: 20px 25px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .aichat-mcp-modal-header h2 {
            margin: 0;
            font-size: 1.3em;
        }
        .aichat-mcp-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            color: #666;
        }
        .aichat-mcp-modal-body {
            padding: 25px;
        }
        .aichat-mcp-modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #ddd;
            text-align: right;
        }
        .aichat-mcp-status .dashicons {
            vertical-align: middle;
            margin-right: 4px;
        }
        /* Toggle switch */
        .aichat-mcp-toggle {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }
        .aichat-mcp-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .aichat-mcp-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }
        .aichat-mcp-toggle-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        .aichat-mcp-toggle input:checked + .aichat-mcp-toggle-slider {
            background-color: #46b450;
        }
        .aichat-mcp-toggle input:checked + .aichat-mcp-toggle-slider:before {
            transform: translateX(20px);
        }
    </style>
    <?php
}
