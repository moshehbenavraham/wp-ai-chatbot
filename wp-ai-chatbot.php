<?php

/**
 * Plugin Name: WP AI Chatbot
 * Description: Ultimate AI chatbot plugin for WordPress.
 * Version:     0.1.0
 * Requires PHP: 7.4
 * Author:      aiwithapex
 * License:     GPL-2.0-or-later
 * Text Domain: wp-ai-chatbot
 */

if (! defined('ABSPATH')) {
	exit;
}

define('WPAIC_VERSION', '0.1.0');
define('WPAIC_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Load library modules.
require_once WPAIC_PLUGIN_DIR . 'lib/health-endpoint.php';
require_once WPAIC_PLUGIN_DIR . 'lib/rate-limiter.php';
require_once WPAIC_PLUGIN_DIR . 'lib/backup-manager.php';

// Register REST API endpoints.
add_action('rest_api_init', 'wpaic_register_health_endpoint');
add_action('rest_api_init', 'wpaic_register_backup_endpoints');
