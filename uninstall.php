<?php

/**
 * Plugin uninstall handler.
 *
 * WordPress executes this file when the plugin is deleted through the admin UI.
 * Data removal is controlled by the `wpaic_preserve_data` option:
 *   - true  → only the flag itself is removed; all tables and options are kept.
 *   - false → full cleanup: tables, options, transients, user meta, cron events.
 *
 * @see WPAIC\Migration\Lifecycle_Manager::uninstall()
 * @package WP_AI_Chatbot
 */

// Abort if not called from WordPress uninstall context.
if (! defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

// Define the minimal constants required by the autoloader and Lifecycle_Manager.
// The full plugin bootstrap (wp-ai-chatbot.php) is intentionally NOT loaded
// here to avoid double-registering hooks in the uninstall context.
if (! defined('WPAIC_PLUGIN_DIR')) {
	define('WPAIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (! defined('WPAIC_INCLUDES_DIR')) {
	define('WPAIC_INCLUDES_DIR', WPAIC_PLUGIN_DIR . 'includes/');
}
if (! defined('WPAIC_PREFIX')) {
	define('WPAIC_PREFIX', 'wpaic_');
}

require_once WPAIC_INCLUDES_DIR . 'autoloader.php';

WPAIC\Migration\Lifecycle_Manager::uninstall();
