<?php

/**
 * MCP Add-on: Admin AJAX handlers
 * Handles server CRUD operations and connection testing.
 *
 * @package AIChat
 * @subpackage MCP
 */

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// Admin-only MCP endpoints; direct DB reads/writes are expected here.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Get a single server configuration
 */
add_action('wp_ajax_aichat_mcp_get_server', 'aichat_mcp_ajax_get_server');
function aichat_mcp_ajax_get_server()
{
	check_ajax_referer('aichat_mcp_ajax');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(__('Insufficient permissions.', 'axiachat-ai'));
	}

	$server_id = sanitize_text_field(wp_unslash($_POST['server_id'] ?? ''));
	if (empty($server_id)) {
		wp_send_json_error(__('Server ID is required.', 'axiachat-ai'));
	}

	$servers = get_option('aichat_mcp_servers', []);
	if (! isset($servers[ $server_id ])) {
		wp_send_json_error(__('Server not found.', 'axiachat-ai'));
	}

	wp_send_json_success($servers[ $server_id ]);
}

/**
 * Save server configuration (add or update)
 */
add_action('wp_ajax_aichat_mcp_save_server', 'aichat_mcp_ajax_save_server');
function aichat_mcp_ajax_save_server()
{
	check_ajax_referer('aichat_mcp_ajax');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(__('Insufficient permissions.', 'axiachat-ai'));
	}

	// Get existing servers
	$servers = get_option('aichat_mcp_servers', []);
	if (! is_array($servers)) {
		$servers = [];
	}

	// Sanitize input
	$server_id = sanitize_text_field(wp_unslash($_POST['server_id'] ?? ''));
	$name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
	$transport = 'http';

	if (empty($name)) {
		wp_send_json_error(__('Server name is required.', 'axiachat-ai'));
	}

	// Generate server ID if adding new
	if (empty($server_id)) {
		// Check limits for new servers
		if (function_exists('aichat_mcp_can_create_server') && ! aichat_mcp_can_create_server()) {
			$max = function_exists('aichat_mcp_max_servers_allowed') ? aichat_mcp_max_servers_allowed() : 1;
			wp_send_json_error(
				sprintf(
					/* translators: %d: maximum number of servers */
					__('You have reached the limit of %d MCP server. Upgrade to Pro for unlimited servers.', 'axiachat-ai'),
					$max
				)
			);
		}

		// Sanitize name to use ONLY lowercase letters, numbers, and underscores
		// This avoids issues with hyphens in function names and simplifies tool naming
		$sanitized_name = strtolower($name);
		$sanitized_name = preg_replace('/[^a-z0-9_]/', '_', $sanitized_name); // Replace non-alphanumeric with _
		$sanitized_name = preg_replace('/_+/', '_', $sanitized_name);         // Collapse multiple _ to single
		$sanitized_name = trim($sanitized_name, '_');                         // Remove leading/trailing _

		$server_id = 'mcp_' . $sanitized_name . '_' . substr(md5(uniqid()), 0, 6);
	}

	// Build server config
	$server = [
		'name' => $name,
		'transport' => $transport,
		'enabled' => true, // Enable by default when creating
	];

	$server['url'] = esc_url_raw(wp_unslash($_POST['url'] ?? ''));
	$server['auth_type'] = sanitize_text_field(wp_unslash($_POST['auth_type'] ?? 'none'));
	$server['auth_token'] = sanitize_text_field(wp_unslash($_POST['auth_token'] ?? ''));
	$server['auth_header'] = sanitize_text_field(wp_unslash($_POST['auth_header'] ?? ''));
	$server['custom_headers'] = sanitize_textarea_field(wp_unslash($_POST['custom_headers'] ?? ''));

	if (empty($server['url'])) {
		wp_send_json_error(__('URL is required for HTTP transport.', 'axiachat-ai'));
	}

	// Save
	$servers[ $server_id ] = $server;
	update_option('aichat_mcp_servers', $servers);

	// Sync tools to database if this is a new server (first save)
	// This ensures tools are available immediately in wp_aichat_tools
	if (class_exists('AIChat_MCP_Client_Manager') && function_exists('aichat_mcp_sync_tools_to_db')) {
		try {
			$manager = AIChat_MCP_Client_Manager::instance();

			// Connect to server (initialize + handshake ONLY, no tools/list)
			$connected = $manager->connect_server($server_id, $server);

			if ($connected) {
				// NOW explicitly discover tools (this is when we call tools/list)
				$manager->discover_tools($server_id);

				// Get discovered tools
				$all_tools = $manager->get_all_tools();
				$tools = [];

				foreach ($all_tools as $tool_name => $tool_info) {
					if (isset($tool_info['server_id']) && $tool_info['server_id'] === $server_id) {
						$tools[] = $tool_info['definition'] ?? [];
					}
				}

				if (! empty($tools)) {
					aichat_mcp_sync_tools_to_db($server_id, $tools);

					// Register macro for this server (groups all its tools)
					if (function_exists('aichat_mcp_register_macros')) {
						aichat_mcp_register_macros($server_id);
					}
				}
			}
		} catch (Exception $e) {
			// Connection failed but server config saved - tools will sync on next successful connection
		}
	}

	wp_send_json_success(['server_id' => $server_id]);
}

/**
 * Delete server configuration
 */
add_action('wp_ajax_aichat_mcp_delete_server', 'aichat_mcp_ajax_delete_server');
function aichat_mcp_ajax_delete_server()
{
	check_ajax_referer('aichat_mcp_ajax');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(__('Insufficient permissions.', 'axiachat-ai'));
	}

	$server_id = sanitize_text_field(wp_unslash($_POST['server_id'] ?? ''));
	if (empty($server_id)) {
		wp_send_json_error(__('Server ID is required.', 'axiachat-ai'));
	}

	$servers = get_option('aichat_mcp_servers', []);
	if (isset($servers[ $server_id ])) {
		unset($servers[ $server_id ]);
		update_option('aichat_mcp_servers', $servers);

		// Clean up associated macros
		if (function_exists('aichat_delete_macros_by_source')) {
			aichat_delete_macros_by_source('mcp', $server_id);
		}

		// Cascade delete: remove all tools from this MCP server
		global $wpdb;
		$table = $wpdb->prefix . 'aichat_tools';
		$deleted = $wpdb->delete(
			$table,
			[
				'type' => 'mcp',
				'source_id' => $server_id,
			],
			['%s', '%s']
		);

		if (function_exists('aichat_log_debug') && $deleted > 0) {
			aichat_log_debug('[MCP] Tools deleted on server removal', [
				'server_id' => $server_id,
				'deleted' => $deleted,
			]);
		}
	}

	wp_send_json_success();
}

/**
 * Toggle server enabled/disabled state
 */
add_action('wp_ajax_aichat_mcp_toggle_server', 'aichat_mcp_ajax_toggle_server');
function aichat_mcp_ajax_toggle_server()
{
	check_ajax_referer('aichat_mcp_ajax');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(__('Insufficient permissions.', 'axiachat-ai'));
	}

	$server_id = sanitize_text_field(wp_unslash($_POST['server_id'] ?? ''));
	$enabled = isset($_POST['enabled']) ? (bool) intval(wp_unslash($_POST['enabled'])) : false;

	if (empty($server_id)) {
		wp_send_json_error(__('Server ID is required.', 'axiachat-ai'));
	}

	$servers = get_option('aichat_mcp_servers', []);
	if (! isset($servers[ $server_id ])) {
		wp_send_json_error(__('Server not found.', 'axiachat-ai'));
	}

	// Update enabled state
	$servers[ $server_id ]['enabled'] = $enabled;
	update_option('aichat_mcp_servers', $servers);

	// If enabled, try to connect and register tools
	if ($enabled) {
		$manager = AIChat_MCP_Client_Manager::instance();
		$connected = $manager->connect_server($server_id, $servers[ $server_id ]);

		if ($connected) {
			// Re-register tools
			do_action('aichat_mcp_server_connected', $server_id);
		}
	}

	wp_send_json_success(['enabled' => $enabled]);
}

/**
 * Test server connection
 * Attempts to connect, perform handshake, and list tools.
 */
add_action('wp_ajax_aichat_mcp_test_server', 'aichat_mcp_ajax_test_server');
function aichat_mcp_ajax_test_server()
{
	check_ajax_referer('aichat_mcp_ajax');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(__('Insufficient permissions.', 'axiachat-ai'));
	}

	$server_id = sanitize_text_field(wp_unslash($_POST['server_id'] ?? ''));
	if (empty($server_id)) {
		wp_send_json_error(__('Server ID is required.', 'axiachat-ai'));
	}

	$servers = get_option('aichat_mcp_servers', []);
	if (! isset($servers[ $server_id ])) {
		wp_send_json_error(__('Server not found.', 'axiachat-ai'));
	}

	$config = $servers[ $server_id ];

	try {
		$transport_type = isset($config['transport']) ? strtolower((string) $config['transport']) : 'http';

		if ($transport_type !== 'http') {
			throw new Exception(__('STDIO transport has been removed. Please update this server to use an HTTP/HTTPS endpoint.', 'axiachat-ai'));
		}

		// Use the MCP Client Manager for consistent behavior
		if (class_exists('AIChat_MCP_Client_Manager')) {
			$manager = AIChat_MCP_Client_Manager::instance();

			// Try to connect using the manager (initialize + handshake ONLY, no tools/list)
			$connected = $manager->connect_server($server_id, $config);

			if (! $connected) {
				throw new Exception(__('Failed to connect to server.', 'axiachat-ai'));
			}

			// NOW explicitly discover tools (this is when we call tools/list)
			$manager->discover_tools($server_id);

			// Check if server uses session ID (Streamable HTTP)
			$transport = $manager->get_transport($server_id);
			$session_info = '';
			if ($transport instanceof AIChat_MCP_HTTP_Transport && $transport->has_session()) {
				$session_id = $transport->get_session_id();
				$session_info = sprintf(
					' [Session: %s...]',
					substr($session_id, 0, 12)
				);
				aichat_log_debug('[AIChat MCP Test] Server uses Streamable HTTP with session ID: ' . substr($session_id, 0, 16) . '...');
			}

			// Get discovered tools for this server
			$all_tools = $manager->get_all_tools();
			$tools = [];
			$tool_definitions = [];

			foreach ($all_tools as $tool_name => $tool_info) {
				if (isset($tool_info['server_id']) && $tool_info['server_id'] === $server_id) {
					// Use local_name (original name without server prefix)
					$original_name = $tool_info['local_name'] ?? $tool_info['definition']['name'] ?? $tool_name;
					$description = $tool_info['definition']['description'] ?? $tool_info['description'] ?? '';

					$tools[] = [
						'name' => $original_name,
						'description' => $description,
					];

					// Store full definition for database sync
					$tool_definitions[] = $tool_info['definition'] ?? [];
				}
			}

			// Sync tools to database when testing connection
			if (function_exists('aichat_mcp_sync_tools_to_db') && ! empty($tool_definitions)) {
				aichat_mcp_sync_tools_to_db($server_id, $tool_definitions);

				// Register macro for this server (groups all its tools)
				if (function_exists('aichat_mcp_register_macros')) {
					aichat_mcp_register_macros($server_id);
				}
			}

			// Get server info from session
			$server_info = $manager->get_server_info($server_id);

			if ($server_info) {
				$protocol_version = $server_info['protocolVersion'] ?? '2025-06-18';
				$server_name = $server_info['serverInfo']['name'] ?? ($config['name'] ?? $server_id);
				$server_version = $server_info['serverInfo']['version'] ?? '—';

				$capabilities_arr = [];
				if (isset($server_info['capabilities']['tools'])) {
					$capabilities_arr[] = 'tools';
				}
				if (isset($server_info['capabilities']['resources'])) {
					$capabilities_arr[] = 'resources';
				}
				if (isset($server_info['capabilities']['prompts'])) {
					$capabilities_arr[] = 'prompts';
				}
				$capabilities = ! empty($capabilities_arr) ? implode(', ', $capabilities_arr) : '—';
			} else {
				// Fallback if server info not available
				$protocol_version = '2025-06-18';
				$server_name = $config['name'] ?? $server_id;
				$server_version = '—';
				$capabilities = count($tools) > 0 ? 'tools' : '—';
			}

			// Return success with server info
			wp_send_json_success([
				'protocol_version' => $protocol_version,
				'server_name' => $server_name,
				'server_version' => $server_version,
				'capabilities' => $capabilities,
				'tools' => $tools,
			]);

		} else {
			// Fallback to manual connection (old method, won't persist tools/macros)
			// Create transport
			$transport = new AIChat_MCP_HTTP_Transport([
				'url' => $config['url'] ?? '',
				'auth_type' => $config['auth_type'] ?? 'none',
				'auth_token' => $config['auth_token'] ?? '',
				'auth_header' => $config['auth_header'] ?? '',
				'custom_headers' => $config['custom_headers'] ?? '',
			]);

			// Connect
			if (! $transport->connect()) {
				throw new Exception($transport->get_last_error() ?: __('Failed to connect to server.', 'axiachat-ai'));
			}

			// Initialize MCP handshake
			$init_response = $transport->send_request('initialize', [
				'protocolVersion' => '2025-06-18',
				'capabilities' => [
					'tools' => (object) [],
				],
				'clientInfo' => [
					'name' => 'AIChat',
					'version' => '1.2.3',
				],
			]);

			if (isset($init_response['error'])) {
				throw new Exception($init_response['error']['message'] ?? __('Initialization failed.', 'axiachat-ai'));
			}

			// Send initialized notification
			$transport->send_request('notifications/initialized', [], true);

			// Get server info
			$server_info = $init_response['result'] ?? [];
			$protocol_version = $server_info['protocolVersion'] ?? '—';
			$server_name = $server_info['serverInfo']['name'] ?? '—';
			$server_version = $server_info['serverInfo']['version'] ?? '—';

			$capabilities_arr = [];
			if (isset($server_info['capabilities']['tools'])) {
				$capabilities_arr[] = 'tools';
			}
			if (isset($server_info['capabilities']['resources'])) {
				$capabilities_arr[] = 'resources';
			}
			if (isset($server_info['capabilities']['prompts'])) {
				$capabilities_arr[] = 'prompts';
			}
			$capabilities = ! empty($capabilities_arr) ? implode(', ', $capabilities_arr) : '—';

			// List tools
			$tools = [];
			if (in_array('tools', $capabilities_arr, true)) {
				$tools_response = $transport->send_request('tools/list', []);
				if (isset($tools_response['result']['tools']) && is_array($tools_response['result']['tools'])) {
					foreach ($tools_response['result']['tools'] as $tool) {
						$tools[] = [
							'name' => $tool['name'] ?? '',
							'description' => $tool['description'] ?? '',
						];
					}
				}
			}

			// Close connection
			$transport->close();

			// Return success with server info
			wp_send_json_success([
				'protocol_version' => $protocol_version,
				'server_name' => $server_name,
				'server_version' => $server_version,
				'capabilities' => $capabilities,
				'tools' => $tools,
			]);
		}

	} catch (Exception $e) {
		wp_send_json_error($e->getMessage());
	}
}

/**
 * List enabled MCP servers for Test Tools dropdown
 */
add_action('wp_ajax_aichat_mcp_list_servers_for_test', 'aichat_mcp_ajax_list_servers_for_test');
function aichat_mcp_ajax_list_servers_for_test()
{
	check_ajax_referer('aichat_mcp_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(__('Insufficient permissions.', 'axiachat-ai'));
	}

	$servers = get_option('aichat_mcp_servers', []);
	$enabled_servers = [];

	foreach ($servers as $server_id => $server) {
		// Only include enabled servers
		if (isset($server['enabled']) && ! $server['enabled']) {
			continue;
		}

		$enabled_servers[ $server_id ] = $server['name'] ?? $server_id;
	}

	wp_send_json_success(['servers' => $enabled_servers]);
}

/**
 * List tools from a specific MCP server
 * OPTIMIZED: Reads directly from wp_aichat_tools table (instant, no server connection)
 */
add_action('wp_ajax_aichat_mcp_list_tools', 'aichat_mcp_ajax_list_tools');
function aichat_mcp_ajax_list_tools()
{
	check_ajax_referer('aichat_mcp_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(__('Insufficient permissions.', 'axiachat-ai'));
	}

	$server_id = sanitize_text_field(wp_unslash($_POST['server_id'] ?? ''));
	if (empty($server_id)) {
		wp_send_json_error(__('Server ID is required.', 'axiachat-ai'));
	}

	// Get server config
	$servers = get_option('aichat_mcp_servers', []);
	if (! isset($servers[ $server_id ])) {
		wp_send_json_error(__('Server not found.', 'axiachat-ai'));
	}

	// OPTIMIZED: Read tools directly from database (instant, no server connection needed)
	global $wpdb;
	$table = $wpdb->prefix . 'aichat_tools';

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name and parameters are safe.
	$tools_rows = $wpdb->get_results(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
			"SELECT name, description, definition_json FROM {$table} WHERE type = 'mcp' AND source_id = %s ORDER BY name ASC",
			$server_id
		),
		ARRAY_A
	);

	if (empty($tools_rows)) {
		// No tools in database - might be a new server that hasn't been tested yet
		wp_send_json_success([
			'tools' => [],
			'message' => __('No tools found. Try testing the connection first.', 'axiachat-ai'),
		]);

		return;
	}

	$server_tools = [];
	foreach ($tools_rows as $row) {
		$definition = json_decode($row['definition_json'], true);
		if (! is_array($definition)) {
			$definition = [];
		}

		$server_tools[] = [
			'name' => $row['name'],
			'description' => $row['description'] ?? ($definition['description'] ?? ''),
			'inputSchema' => $definition['inputSchema'] ?? [],
		];
	}

	wp_send_json_success(['tools' => $server_tools]);
}

/**
 * Execute a tool from an MCP server
 */
add_action('wp_ajax_aichat_mcp_run_tool', 'aichat_mcp_ajax_run_tool');
function aichat_mcp_ajax_run_tool()
{
	check_ajax_referer('aichat_mcp_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(__('Insufficient permissions.', 'axiachat-ai'));
	}

	$server_id = sanitize_text_field(wp_unslash($_POST['server_id'] ?? ''));
	$tool_name = sanitize_text_field(wp_unslash($_POST['tool_name'] ?? ''));
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload validated via json_decode.
	$arguments_json = wp_unslash($_POST['arguments'] ?? '{}');

	if (empty($server_id) || empty($tool_name)) {
		wp_send_json_error(__('Server ID and Tool Name are required.', 'axiachat-ai'));
	}

	// Decode arguments
	$arguments = json_decode($arguments_json, true);
	if (! is_array($arguments)) {
		$arguments = [];
	}

	// Get MCP Manager
	if (! class_exists('AIChat_MCP_Client_Manager')) {
		wp_send_json_error(__('MCP Client Manager not available.', 'axiachat-ai'));
	}

	$manager = AIChat_MCP_Client_Manager::instance();

	// Get server config and ensure it's connected
	$servers = get_option('aichat_mcp_servers', []);
	if (! isset($servers[ $server_id ])) {
		wp_send_json_error(__('Server not found.', 'axiachat-ai'));
	}

	try {
		// LOG: Entrada al try block
		aichat_log_debug("[AIChat MCP Run Tool] Starting execution | server_id: $server_id | tool_name: $tool_name");

		// Ensure server is connected (will reuse existing session if available)
		$connected = $manager->connect_server($server_id, $servers[ $server_id ]);
		if (! $connected) {
			aichat_log_debug("[AIChat MCP Run Tool] Connection failed for server: $server_id");
			wp_send_json_error(__('Failed to connect to server.', 'axiachat-ai'));
		}

		aichat_log_debug('[AIChat MCP Run Tool] Server connected successfully');

		// Build global tool name using SAME logic as integration.php
		// Format: serverId_toolName, then sanitize (replace - with _)
		$global_name = $server_id . '_' . $tool_name;
		$safe_global_name = str_replace('-', '_', strtolower($global_name));

		aichat_log_debug("[AIChat MCP Run Tool] Tool names | global: $global_name | safe: $safe_global_name");

		// Verify tool exists in database
		global $wpdb;
		$table = $wpdb->prefix . 'aichat_tools';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name and parameters are safe.
		$tool_exists = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
			$wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE type = 'mcp' AND source_id = %s AND name = %s", $server_id, $tool_name)
		);

		aichat_log_debug("[AIChat MCP Run Tool] DB check | tool_exists: $tool_exists | table: $table");

		if (! $tool_exists) {
			aichat_log_debug('[AIChat MCP Run Tool] Tool not found in DB');
			wp_send_json_error([
				'error' => 'tool_not_found',
				/* translators: 1: tool name, 2: MCP server identifier */
				'message' => sprintf(__('Tool "%1$s" not found in server "%2$s". Try clicking "Test Connection" first.', 'axiachat-ai'), $tool_name, $server_id),
			]);
		}

		// Execute tool using safe global name
		aichat_log_debug("[AIChat MCP Run Tool] Calling execute_tool with safe_global_name: $safe_global_name");

		$result = $manager->execute_tool($safe_global_name, $arguments, [
			'test_mode' => true,
			'admin_user' => wp_get_current_user()->user_login,
		]);

		aichat_log_debug('[AIChat MCP Run Tool] Result received | ok: ' . ($result['ok'] ?? 'unknown'));

		wp_send_json_success($result);

	} catch (Exception $e) {
		wp_send_json_error([
			'error' => $e->getMessage(),
			'code' => $e->getCode(),
		]);
	}
}

/**
 * Get tools status (enabled/disabled) for a specific MCP server
 * OPTIMIZED: Reads directly from wp_aichat_tools table (instant)
 */
add_action('wp_ajax_aichat_mcp_get_server_tools_status', 'aichat_mcp_ajax_get_server_tools_status');
function aichat_mcp_ajax_get_server_tools_status()
{
	check_ajax_referer('aichat_mcp_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(__('Insufficient permissions.', 'axiachat-ai'));
	}

	$server_id = sanitize_text_field(wp_unslash($_POST['server_id'] ?? ''));
	if (empty($server_id)) {
		wp_send_json_error(__('Server ID is required.', 'axiachat-ai'));
	}

	// Get server config
	$servers = get_option('aichat_mcp_servers', []);
	if (! isset($servers[ $server_id ])) {
		wp_send_json_error(__('Server not found.', 'axiachat-ai'));
	}

	// OPTIMIZED: Read tools directly from database with enabled status (instant)
	global $wpdb;
	$table = $wpdb->prefix . 'aichat_tools';

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name and parameters are safe.
	$tools_rows = $wpdb->get_results(
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only read of internal table.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
		$wpdb->prepare("SELECT name, description, enabled FROM $table WHERE type = 'mcp' AND source_id = %s ORDER BY name ASC", $server_id),
		ARRAY_A
	);

	if (empty($tools_rows)) {
		// No tools in database - might be a new server that hasn't been tested yet
		wp_send_json_success([
			'tools' => [],
			'message' => __('No tools found. Try testing the connection first.', 'axiachat-ai'),
		]);

		return;
	}

	$server_tools = [];
	foreach ($tools_rows as $row) {
		$server_tools[] = [
			'local_name' => $row['name'],
			'description' => $row['description'] ?? '',
			'enabled' => (bool) $row['enabled'],
		];
	}

	wp_send_json_success(['tools' => $server_tools]);
}

/**
 * Save tools status (enabled/disabled) for a specific MCP server
 */
add_action('wp_ajax_aichat_mcp_save_tools_status', 'aichat_mcp_ajax_save_tools_status');
function aichat_mcp_ajax_save_tools_status()
{
	check_ajax_referer('aichat_mcp_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(__('Insufficient permissions.', 'axiachat-ai'));
	}

	$server_id = sanitize_text_field(wp_unslash($_POST['server_id'] ?? ''));
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload validated via json_decode.
	$tools_status_json = wp_unslash($_POST['tools_status'] ?? '{}');

	if (empty($server_id)) {
		wp_send_json_error(__('Server ID is required.', 'axiachat-ai'));
	}

	$tools_status = json_decode($tools_status_json, true);
	if (! is_array($tools_status)) {
		wp_send_json_error(__('Invalid tools status data.', 'axiachat-ai'));
	}

	global $wpdb;
	$table = $wpdb->prefix . 'aichat_tools';

	$updated = 0;
	foreach ($tools_status as $tool_name => $enabled) {
		$enabled = (int) $enabled;
		$tool_name = sanitize_text_field($tool_name);

		// Buscar el registro existente
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name and parameters are safe.
		$existing = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
			$wpdb->prepare("SELECT id FROM $table WHERE type = 'mcp' AND source_id = %s AND name = %s", $server_id, $tool_name)
		);

		if ($existing) {
			// Actualizar enabled
			$result = $wpdb->update(
				$table,
				[
					'enabled' => $enabled,
					'updated_at' => current_time('mysql'),
				],
				['id' => $existing->id],
				['%d', '%s'],
				['%d']
			);
		}

		if ($result !== false) {
			$updated++;
		}
	}

	wp_send_json_success([
		'message' => sprintf(
			/* translators: %d: number of tools updated */
			__('Successfully updated %d tool(s).', 'axiachat-ai'),
			$updated
		),
	]);
}
