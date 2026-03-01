<?php

/**
 * Custom Webhooks Add-on Loader
 *
 * Permite crear herramientas/acciones personalizadas basadas en webhooks
 * que el modelo de IA puede invocar durante las conversaciones.
 *
 * @package AIChat
 * @subpackage CustomWebhooks
 */

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

define('AICHAT_CUSTOM_WEBHOOKS_DIR', plugin_dir_path(__FILE__));
define('AICHAT_CUSTOM_WEBHOOKS_URL', plugin_dir_url(__FILE__));

/**
 * Check if user has PRO license for Webhooks (unlimited)
 */
function aichat_custom_webhooks_has_pro()
{
	// Use centralized feature check (Feature ID 26989: Unlimited Webhooks)
	if (function_exists('aichat_has_feature') && defined('AICHAT_FEATURE_UNLIMITED_WEBHOOKS')) {
		return aichat_has_feature(AICHAT_FEATURE_UNLIMITED_WEBHOOKS);
	}

	// Fallback to legacy check
	return function_exists('aichat_is_pro') && aichat_is_pro();
}

/**
 * Get max webhooks allowed
 * Free: 1, Paid (Starter+): unlimited
 */
function aichat_custom_webhooks_max_allowed()
{
	// Use centralized limit function if available
	if (function_exists('aichat_max_webhooks_allowed')) {
		$limit = aichat_max_webhooks_allowed();

		// Convert PHP_INT_MAX to a reasonable display value
		return $limit >= PHP_INT_MAX ? 999 : $limit;
	}

	// Fallback to legacy check
	return aichat_custom_webhooks_has_pro() ? 999 : 1;
}

/**
 * Get current webhook count
 */
function aichat_custom_webhooks_count()
{
	global $wpdb;
	$table = $wpdb->prefix . 'aichat_custom_webhooks';

	// Check if table exists
	$exists = $wpdb->get_var($wpdb->prepare(
		'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s',
		$table
	));

	if (! $exists) {
		return 0;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table");
}

/**
 * Can create more webhooks?
 */
function aichat_custom_webhooks_can_create()
{
	return aichat_custom_webhooks_count() < aichat_custom_webhooks_max_allowed();
}

/**
 * Create table on activation
 */
function aichat_custom_webhooks_create_table()
{
	global $wpdb;
	$table = $wpdb->prefix . 'aichat_custom_webhooks';
	$charset = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$sql = "CREATE TABLE $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        slug VARCHAR(50) NOT NULL,
        label VARCHAR(100) NOT NULL,
        description_ai TEXT NOT NULL,
        activity_label VARCHAR(150) NOT NULL DEFAULT '',
        webhook_url TEXT NOT NULL,
        http_method ENUM('GET','POST','PUT') NOT NULL DEFAULT 'POST',
        headers_json TEXT NULL,
        params_schema TEXT NULL,
        static_params TEXT NULL,
        include_history TINYINT(1) NOT NULL DEFAULT 0,
        history_limit SMALLINT UNSIGNED NOT NULL DEFAULT 10,
        success_message TEXT NULL,
        error_message TEXT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY is_active (is_active)
    ) $charset;";

	dbDelta($sql);
}

/**
 * Initialize on plugin load
 */
add_action('plugins_loaded', 'aichat_custom_webhooks_init', 15);
function aichat_custom_webhooks_init()
{
	// Ensure table exists (upgrade-safe)
	add_action('admin_init', 'aichat_custom_webhooks_maybe_create_table');

	// Load components
	require_once AICHAT_CUSTOM_WEBHOOKS_DIR . 'class-webhook-executor.php';
	require_once AICHAT_CUSTOM_WEBHOOKS_DIR . 'integration.php';

	if (is_admin()) {
		require_once AICHAT_CUSTOM_WEBHOOKS_DIR . 'admin-ajax.php';
		require_once AICHAT_CUSTOM_WEBHOOKS_DIR . 'admin-tab.php';
	}
}

/**
 * Maybe create table (upgrade safe)
 */
function aichat_custom_webhooks_maybe_create_table()
{
	global $wpdb;
	$table = $wpdb->prefix . 'aichat_custom_webhooks';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var($wpdb->prepare(
		'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s',
		$table
	));

	if (! $exists) {
		aichat_custom_webhooks_create_table();
	}
}

/**
 * Get all custom webhooks
 */
function aichat_get_custom_webhooks($active_only = false)
{
	global $wpdb;
	$table = $wpdb->prefix . 'aichat_custom_webhooks';

	// Check table exists
	$exists = $wpdb->get_var($wpdb->prepare(
		'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s',
		$table
	));

	if (! $exists) {
		return [];
	}

	$where = $active_only ? 'WHERE is_active = 1' : '';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$rows = $wpdb->get_results("SELECT * FROM $table $where ORDER BY label ASC", ARRAY_A);

	if (! $rows) {
		return [];
	}

	// Decode JSON fields
	foreach ($rows as &$row) {
		$row['headers'] = json_decode($row['headers_json'] ?? '{}', true) ?: [];
		$row['params_schema'] = json_decode($row['params_schema'] ?? '{}', true) ?: [];
		$row['static_params'] = json_decode($row['static_params'] ?? '{}', true) ?: [];
	}

	return $rows;
}

/**
 * Get single webhook by slug
 */
function aichat_get_custom_webhook($slug)
{
	global $wpdb;
	$table = $wpdb->prefix . 'aichat_custom_webhooks';

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$row = $wpdb->get_row($wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
		"SELECT * FROM $table WHERE slug = %s",
		$slug
	), ARRAY_A);

	if (! $row) {
		return null;
	}

	$row['headers'] = json_decode($row['headers_json'] ?? '{}', true) ?: [];
	$row['params_schema'] = json_decode($row['params_schema'] ?? '{}', true) ?: [];
	$row['static_params'] = json_decode($row['static_params'] ?? '{}', true) ?: [];

	return $row;
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
