<?php

/**
 * Plugin Name:       WP AI Chatbot
 * Plugin URI:        https://github.com/aiwithapex/wp-ai-chatbot
 * Description:       Ultimate AI chatbot plugin for WordPress — provider-agnostic, RAG-enabled, streaming-capable.
 * Version:           1.0.3
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            aiwithapex
 * License:           MIT
 * Text Domain:       wp-ai-chatbot
 * Domain Path:       /languages
 */

if (! defined('ABSPATH')) {
	exit;
}

// =============================================================================
// Phase A: Constants (defined before any WordPress hook fires).
// Version must match the Plugin Name header — enforced by release workflow.
// =============================================================================

define('WPAIC_VERSION', '1.0.3');
define('WPAIC_MIN_PHP', '7.4');
define('WPAIC_MIN_WP', '5.0');
define('WPAIC_TEXT_DOMAIN', 'wp-ai-chatbot');

define('WPAIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPAIC_INCLUDES_DIR', WPAIC_PLUGIN_DIR . 'includes/');

define('WPAIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPAIC_ASSETS_URL', WPAIC_PLUGIN_URL . 'assets/');
define('WPAIC_ADMIN_URL', WPAIC_PLUGIN_URL . 'admin/');

define('WPAIC_PREFIX', 'wpaic_');
define('WPAIC_REST_NAMESPACE', 'wp-ai-chatbot/v1');
define('WPAIC_BASENAME', plugin_basename(__FILE__));

define('WPAIC_DEBUG', defined('WP_DEBUG') && WP_DEBUG);
define('WPAIC_LOG_RETENTION_DAYS', 30);
define('WPAIC_DEFAULT_RATE_LIMIT', 60);
define('WPAIC_DEFAULT_RATE_WINDOW', 60);

// Autoloader must be available before any WPAIC class reference.
require_once WPAIC_INCLUDES_DIR . 'autoloader.php';

// Load Composer vendor autoloader when production dependencies are bundled
// (e.g. smalot/pdfparser for PDF text extraction). The plugin degrades
// gracefully when this file is absent — Pdf_Source falls back to its built-in
// pure-PHP extractor. Run `composer install --no-dev` and bundle vendor/ in
// the plugin ZIP to enable full PDF parsing.
$wpaic_vendor_autoload = WPAIC_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($wpaic_vendor_autoload)) {
	require_once $wpaic_vendor_autoload;
}
unset($wpaic_vendor_autoload);

// =============================================================================
// Phase B: Core infrastructure — loaded before any hook fires.
// Structured logger loads first so bootstrap errors are captured.
// Rate limiter loads here so it is available for both AJAX and REST contexts.
// =============================================================================

require_once WPAIC_PLUGIN_DIR . 'lib/structured-logger.php';
require_once WPAIC_PLUGIN_DIR . 'lib/rate-limiter.php';

// PHP version guard — show admin notice and halt initialisation if unsatisfied.
if (version_compare(PHP_VERSION, WPAIC_MIN_PHP, '<')) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: Required PHP version. 2: Current PHP version. */
						__('WP AI Chatbot requires PHP %1$s or higher. Your server is running PHP %2$s. Please upgrade PHP.', 'wp-ai-chatbot'),
						WPAIC_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);

	return;
}

// WP version guard — show admin notice and halt initialisation if unsatisfied.
global $wp_version;
if (isset($wp_version) && version_compare($wp_version, WPAIC_MIN_WP, '<')) {
	add_action(
		'admin_notices',
		static function () use ($wp_version): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: Required WP version. 2: Current WP version. */
						__('WP AI Chatbot requires WordPress %1$s or higher. You are running %2$s. Please upgrade WordPress.', 'wp-ai-chatbot'),
						WPAIC_MIN_WP,
						$wp_version
					)
				)
			);
		}
	);

	return;
}

// =============================================================================
// Lifecycle hooks — must be registered from the main plugin file because
// register_activation_hook() and register_deactivation_hook() resolve paths
// relative to the top-level plugin file's __FILE__.
// =============================================================================

register_activation_hook(
	__FILE__,
	['WPAIC\Migration\Lifecycle_Manager', 'activate']
);

register_deactivation_hook(
	__FILE__,
	['WPAIC\Migration\Lifecycle_Manager', 'deactivate']
);

// =============================================================================
// Bootstrap the plugin orchestrator and wire all WordPress action/filter hooks.
// =============================================================================

WPAIC\Plugin::get_instance()->register_hooks();
