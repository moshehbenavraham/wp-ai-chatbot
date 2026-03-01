<?php

/**
 * MCP (Model Context Protocol) Add-on Loader
 *
 * Provides integration with external MCP servers following the official specification.
 * Allows connecting to remote tools, resources and prompts via JSON-RPC 2.0.
 *
 * @package AIChat
 * @subpackage MCP
 * @see https://modelcontextprotocol.io/
 */

if (! defined('ABSPATH')) {
	exit;
}

// Add-on metadata
if (! function_exists('aichat_mcp_addon_info')) {
	function aichat_mcp_addon_info()
	{
		return [
			'id' => 'mcp',
			'name' => __('MCP Server Connectors', 'axiachat-ai'),
			'description' => __('Connect to external MCP (Model Context Protocol) servers to access remote tools, resources and prompts via HTTP transport.', 'axiachat-ai'),
			'version' => '1.0.0',
			'author' => 'AxiaChat AI',
			'requires' => '1.2.3', // Versión mínima del plugin
			'default' => false,   // Desactivado por defecto
		];
	}
}

/**
 * Check if user has PRO license for MCP (unlimited servers)
 */
function aichat_mcp_has_pro()
{
	// Use centralized feature check (Feature ID 26988: Unlimited MCP)
	if (function_exists('aichat_has_feature') && defined('AICHAT_FEATURE_UNLIMITED_MCP')) {
		return aichat_has_feature(AICHAT_FEATURE_UNLIMITED_MCP);
	}

	// Fallback to legacy check
	return function_exists('aichat_is_pro') && aichat_is_pro();
}

/**
 * Get max MCP servers allowed
 * Free: 1, Paid (Starter+): unlimited
 */
function aichat_mcp_max_servers_allowed()
{
	// Use centralized limit function if available
	if (function_exists('aichat_max_mcp_servers_allowed')) {
		$limit = aichat_max_mcp_servers_allowed();

		// Convert PHP_INT_MAX to a reasonable display value
		return $limit >= PHP_INT_MAX ? 999 : $limit;
	}

	// Fallback to legacy check
	return aichat_mcp_has_pro() ? 999 : 1;
}

/**
 * Get current MCP server count
 */
function aichat_mcp_servers_count()
{
	$servers = get_option('aichat_mcp_servers', []);

	return is_array($servers) ? count($servers) : 0;
}

/**
 * Can create more MCP servers?
 */
function aichat_mcp_can_create_server()
{
	return aichat_mcp_servers_count() < aichat_mcp_max_servers_allowed();
}

// Check if add-on is enabled
if (! get_option('aichat_addon_mcp_enabled', false)) {
	if (function_exists('aichat_log_debug')) {
		aichat_log_debug('[MCP Add-on] Disabled - skipping load');
	}

	return; // No cargar nada si está desactivado
}

if (function_exists('aichat_log_debug')) {
	aichat_log_debug('[MCP Add-on] Loading...');
}

// Define constants
if (! defined('AICHAT_MCP_VERSION')) {
	define('AICHAT_MCP_VERSION', '1.0.0');
}
if (! defined('AICHAT_MCP_DIR')) {
	define('AICHAT_MCP_DIR', __DIR__ . '/');
}

// Load core components in order
require_once AICHAT_MCP_DIR . 'class-mcp-transport.php';
require_once AICHAT_MCP_DIR . 'transports/class-http-transport.php';
require_once AICHAT_MCP_DIR . 'class-mcp-client-manager.php';
require_once AICHAT_MCP_DIR . 'integration.php';

// Admin UI (solo en admin)
if (is_admin()) {
	require_once AICHAT_MCP_DIR . 'admin-settings.php';
	require_once AICHAT_MCP_DIR . 'admin-ajax.php';

	// Register MCP Servers submenu (only if add-on is enabled)
	add_action('admin_menu', 'aichat_mcp_register_menu', 15);

	// Enqueue admin assets for MCP Servers page
	add_action('admin_enqueue_scripts', 'aichat_mcp_admin_enqueue_scripts');
}

/**
 * Register MCP Servers admin menu
 */
function aichat_mcp_register_menu()
{
	if (! get_option('aichat_addon_mcp_enabled', false)) {
		return;
	}

	add_submenu_page(
		'aichat-settings',
		__('MCP Servers', 'axiachat-ai'),
		__('MCP Servers', 'axiachat-ai'),
		'manage_options',
		'aichat-mcp-servers',
		'aichat_mcp_render_servers_page'
	);
}

/**
 * Enqueue MCP admin scripts only on the MCP Servers page.
 */
function aichat_mcp_admin_enqueue_scripts($hook_suffix)
{
	// Only load on our MCP page under the AxiaChat menu
	if ($hook_suffix !== 'axiachat-ai_page_aichat-mcp-servers') {
		return;
	}

	// Base handle reused from main plugin admin styles if needed
	// Calculate base URL from plugin root (same approach as core)

	$js_url = AICHAT_PLUGIN_URL . 'assets/js/mcp-admin.js';

	// Add wp-i18n as dependency for translation support
	wp_enqueue_script(
		'aichat-mcp-admin',
		$js_url,
		['jquery', 'wp-i18n'],
		defined('AICHAT_VERSION') ? AICHAT_VERSION : false,
		true
	);
	wp_set_script_translations('aichat-mcp-admin', 'axiachat-ai', AICHAT_PLUGIN_DIR . 'languages');

	$ajax_url = admin_url('admin-ajax.php');

	wp_localize_script(
		'aichat-mcp-admin',
		'aichatMcpData',
		[
			'ajaxUrl' => $ajax_url,
			'nonce' => wp_create_nonce('aichat_mcp_nonce'),
			'ajaxNonce' => wp_create_nonce('aichat_mcp_ajax'),
			'i18n' => [
				'select_server_placeholder' => __('— Select a server —', 'axiachat-ai'),
				'select_server_first' => __('— Select a server first —', 'axiachat-ai'),
				'loading_tools' => __('Loading tools...', 'axiachat-ai'),
				'no_tools_available' => __('No tools available', 'axiachat-ai'),
				'no_parameters' => __('This tool takes no parameters.', 'axiachat-ai'),
				'parameters_label' => __('Parameters:', 'axiachat-ai'),
				'executing' => __('Executing...', 'axiachat-ai'),
				'success' => __('Success', 'axiachat-ai'),
				'error' => __('Error', 'axiachat-ai'),
				'request_failed' => __('Request failed', 'axiachat-ai'),
				'network_error' => __('Network error', 'axiachat-ai'),
				'connecting' => __('Connecting...', 'axiachat-ai'),
				'disabled' => __('Disabled', 'axiachat-ai'),
				'error_updating_server' => __('Error updating server.', 'axiachat-ai'),
				'add_server' => __('Add MCP Server', 'axiachat-ai'),
				'edit_server' => __('Edit MCP Server', 'axiachat-ai'),
				'error_saving_server' => __('Error saving server.', 'axiachat-ai'),
				'delete_confirm' => __('Are you sure you want to delete this server?', 'axiachat-ai'),
				'testing' => __('Testing...', 'axiachat-ai'),
				'connection_success' => __('Connection successful!', 'axiachat-ai'),
				'server_info' => __('Server Information', 'axiachat-ai'),
				'protocol_version' => __('Protocol Version', 'axiachat-ai'),
				'server_name' => __('Server Name', 'axiachat-ai'),
				'server_version' => __('Server Version', 'axiachat-ai'),
				'capabilities' => __('Capabilities', 'axiachat-ai'),
				'available_tools' => __('Available Tools', 'axiachat-ai'),
				'connection_failed' => __('Connection failed', 'axiachat-ai'),
				'unknown_error' => __('Unknown error', 'axiachat-ai'),
				'failed_load_tools' => __('Failed to load tools.', 'axiachat-ai'),
				'no_tools_server' => __('No tools found for this server.', 'axiachat-ai'),
				'please_select_server_first' => __('Please select a server first.', 'axiachat-ai'),
				'error_saving' => __('Error saving.', 'axiachat-ai'),
			],
		]
	);
}

// Initialize MCP Manager EARLY to ensure initialize_servers() hook fires
add_action('plugins_loaded', function () {
	if (class_exists('AIChat_MCP_Client_Manager')) {
		AIChat_MCP_Client_Manager::instance();
		if (function_exists('aichat_log_debug')) {
			aichat_log_debug('[MCP Add-on] Client Manager initialized');
		}
	}
}, 10); // EARLY - before manager's internal initialize_servers() at priority 15

if (function_exists('aichat_log_debug')) {
	aichat_log_debug('[MCP Add-on] Loaded successfully');
}
