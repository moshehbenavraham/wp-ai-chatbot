<?php

/**
 * Live Chat Add-on Loader
 *
 * Enables human agent takeover for AI chat conversations.
 * Supports WP Dashboard and Slack integration for agent responses.
 *
 * @package AIChat
 * @subpackage LiveChat
 * @since 2.5.0
 */

if (! defined('ABSPATH')) {
	exit;
}

// Add-on metadata
if (! function_exists('aichat_livechat_addon_info')) {
	function aichat_livechat_addon_info()
	{
		return [
			'id' => 'livechat',
			'name' => __('Live Chat', 'axiachat-ai'),
			'description' => __('Enable human agent takeover for chat conversations. Agents can respond via WP Dashboard or Slack.', 'axiachat-ai'),
			'version' => '1.0.0',
			'author' => 'AxiaChat AI',
			'requires' => '2.5.0',
			'default' => false,
		];
	}
}

// Check if add-on is enabled (default: false)
$aichat_livechat_enabled = (int) get_option('aichat_addon_livechat_enabled', 0);
if (! $aichat_livechat_enabled) {
	return;
}

// Check if user has LiveChat license (Professional plan required)
if (! aichat_livechat_has_license()) {
	return;
}

/**
 * Check if user has LiveChat license (Professional plan only)
 * Uses Feature ID 26995: LiveChat
 *
 * @return bool True if LiveChat license is active
 */
function aichat_livechat_has_license()
{
	// Use centralized feature check (Feature ID 26995: LiveChat)
	if (function_exists('aichat_can_use_livechat')) {
		return aichat_can_use_livechat();
	}

	// Fallback: Use central Freemius check for plan level
	if (function_exists('aichat_is_plan')) {
		return aichat_is_plan('professional'); // Professional only
	}

	// Fallback: check legacy pro function
	if (function_exists('aichat_is_pro')) {
		return aichat_is_pro();
	}

	return false;
}

// Define constants
if (! defined('AICHAT_LIVECHAT_VERSION')) {
	define('AICHAT_LIVECHAT_VERSION', '1.0.4');
}
if (! defined('AICHAT_LIVECHAT_DIR')) {
	define('AICHAT_LIVECHAT_DIR', plugin_dir_path(__FILE__));
}
if (! defined('AICHAT_LIVECHAT_URL')) {
	define('AICHAT_LIVECHAT_URL', plugin_dir_url(__FILE__));
}

// Load core components
require_once AICHAT_LIVECHAT_DIR . 'class-livechat-manager.php';
require_once AICHAT_LIVECHAT_DIR . 'integration.php';
require_once AICHAT_LIVECHAT_DIR . 'rest-api.php';

// Load admin components
if (is_admin()) {
	require_once AICHAT_LIVECHAT_DIR . 'admin-dashboard.php';
	require_once AICHAT_LIVECHAT_DIR . 'admin-ajax.php';
	require_once AICHAT_LIVECHAT_DIR . 'admin-settings.php';
}

/**
 * Enqueue frontend assets
 */
add_action('wp_enqueue_scripts', 'aichat_livechat_enqueue_frontend', 999);

function aichat_livechat_enqueue_frontend()
{
	// Always load if LiveChat is enabled - the script handles checking for session
	if (! get_option('aichat_addon_livechat_enabled', 0)) {
		return;
	}

	wp_enqueue_style(
		'aichat-livechat-frontend',
		AICHAT_LIVECHAT_URL . 'assets/livechat-frontend.css',
		[],
		AICHAT_LIVECHAT_VERSION
	);

	wp_enqueue_script(
		'aichat-livechat-frontend',
		AICHAT_LIVECHAT_URL . 'assets/livechat-frontend.js',
		['jquery'],
		AICHAT_LIVECHAT_VERSION,
		true
	);

	$settings = AIChat_LiveChat_Manager::get_settings();

	wp_localize_script('aichat-livechat-frontend', 'AIChatLiveChat', [
		'enabled' => true,
		'ajax_url' => admin_url('admin-ajax.php'),
		'nonce' => wp_create_nonce('aichat_livechat_nonce'),
		'available' => $settings['available'],
		'agent_timeout' => (int) get_option('aichat_livechat_agent_timeout', 5) * 60 * 1000, // Convert minutes to ms
		'strings' => [
			'waiting' => 'Connecting to agent...',
			'connected' => 'Connected with agent',
			'returned_to_bot' => 'You are now chatting with our AI assistant.',
			'timeout' => 'No response from agent. Returning to AI assistant.',
		],
	]);
}

/**
 * Create livechat tables on activation
 */
function aichat_livechat_activate()
{
	AIChat_LiveChat_Manager::create_tables();
}
register_activation_hook(AICHAT_PLUGIN_DIR . 'axiachat-ai.php', 'aichat_livechat_activate');

/**
 * Ensure tables exist when add-on is enabled
 */
add_action('admin_init', function () {
	if (get_option('aichat_addon_livechat_enabled', 0)) {
		AIChat_LiveChat_Manager::create_tables();
	}
}, 5);
