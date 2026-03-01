<?php

/**
 * Backup Manager — Plugin data export/import via WP REST API.
 *
 * Registers endpoints for exporting and importing plugin settings and data.
 * Export produces a versioned JSON payload; import validates and restores.
 *
 * Usage (in main plugin bootstrap):
 *   require_once __DIR__ . '/../lib/backup-manager.php';
 *   add_action( 'rest_api_init', 'wpaic_register_backup_endpoints' );
 */

if (! defined('ABSPATH')) {
	define('WPAIC_BACKUP_STANDALONE', true);
}

/** Current backup schema version. Bump when export format changes. */
define('WPAIC_BACKUP_VERSION', '1.0');

/** Plugin option prefix — all options matching this are exported. */
define('WPAIC_OPTION_PREFIX', 'wpaic_');

/**
 * Register backup/restore REST routes.
 */
function wpaic_register_backup_endpoints(): void
{
	register_rest_route('wp-ai-chatbot/v1', '/backup/export', [
		'methods' => 'GET',
		'callback' => 'wpaic_backup_export_callback',
		'permission_callback' => 'wpaic_backup_permission_check',
	]);

	register_rest_route('wp-ai-chatbot/v1', '/backup/import', [
		'methods' => 'POST',
		'callback' => 'wpaic_backup_import_callback',
		'permission_callback' => 'wpaic_backup_permission_check',
	]);
}

/**
 * Permission check — requires manage_options capability.
 *
 * @return bool|WP_Error
 */
function wpaic_backup_permission_check()
{
	if (! current_user_can('manage_options')) {
		return new WP_Error(
			'rest_forbidden',
			'You do not have permission to manage backups.',
			['status' => 403]
		);
	}

	return true;
}

/**
 * Export callback — returns all plugin options as a versioned JSON payload.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function wpaic_backup_export_callback($request)
{
	$data = wpaic_collect_export_data();

	return new WP_REST_Response($data, 200);
}

/**
 * Import callback — validates and restores plugin options from JSON payload.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response|WP_Error
 */
function wpaic_backup_import_callback($request)
{
	$body = $request->get_json_params();

	if (empty($body) || ! is_array($body)) {
		return new WP_Error(
			'invalid_backup',
			'Request body must be a valid JSON backup payload.',
			['status' => 400]
		);
	}

	$validation = wpaic_validate_backup_payload($body);
	if (is_wp_error($validation)) {
		return $validation;
	}

	$result = wpaic_apply_import_data($body);

	return new WP_REST_Response($result, 200);
}

/**
 * Collect all plugin options for export.
 *
 * Queries the wp_options table for rows whose option_name starts with the
 * plugin prefix and returns them in a versioned envelope.
 *
 * @return array{version: string, plugin: string, exported_at: string, options: array<string, mixed>}
 */
function wpaic_collect_export_data(): array
{
	global $wpdb;

	$options = [];

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like(WPAIC_OPTION_PREFIX) . '%'
		),
		ARRAY_A
	);

	if (is_array($rows)) {
		foreach ($rows as $row) {
			$value = maybe_unserialize($row['option_value']);
			$options[$row['option_name']] = $value;
		}
	}

	return [
		'version' => WPAIC_BACKUP_VERSION,
		'plugin' => 'wp-ai-chatbot',
		'exported_at' => gmdate('Y-m-d\TH:i:s\Z'),
		'options' => $options,
	];
}

/**
 * Validate that a backup payload has the required structure.
 *
 * @param array<string, mixed> $payload Parsed JSON body.
 * @return true|WP_Error
 */
function wpaic_validate_backup_payload(array $payload)
{
	if (! isset($payload['version']) || ! is_string($payload['version'])) {
		return new WP_Error(
			'invalid_backup',
			'Backup payload must include a "version" string.',
			['status' => 400]
		);
	}

	if (! isset($payload['plugin']) || $payload['plugin'] !== 'wp-ai-chatbot') {
		return new WP_Error(
			'invalid_backup',
			'Backup payload must have "plugin" set to "wp-ai-chatbot".',
			['status' => 400]
		);
	}

	if (! isset($payload['options']) || ! is_array($payload['options'])) {
		return new WP_Error(
			'invalid_backup',
			'Backup payload must include an "options" object.',
			['status' => 400]
		);
	}

	// Verify every key in options starts with the plugin prefix.
	foreach (array_keys($payload['options']) as $key) {
		if (strpos($key, WPAIC_OPTION_PREFIX) !== 0) {
			return new WP_Error(
				'invalid_backup',
				sprintf('Option key "%s" does not match the plugin prefix "%s".', $key, WPAIC_OPTION_PREFIX),
				['status' => 400]
			);
		}
	}

	return true;
}

/**
 * Apply imported options to the database.
 *
 * @param array<string, mixed> $payload Validated backup payload.
 * @return array{status: string, imported: int, skipped: int}
 */
function wpaic_apply_import_data(array $payload): array
{
	$imported = 0;
	$skipped = 0;

	foreach ($payload['options'] as $name => $value) {
		$result = update_option($name, $value);
		if ($result) {
			$imported++;
		} else {
			// update_option returns false when value is unchanged — not an error.
			$skipped++;
		}
	}

	return [
		'status' => 'complete',
		'imported' => $imported,
		'skipped' => $skipped,
		'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
	];
}
