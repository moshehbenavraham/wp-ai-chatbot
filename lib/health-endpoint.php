<?php

/**
 * Health Endpoint — WP REST API health check for the WP AI Chatbot plugin.
 *
 * Registers GET /wp-json/wp-ai-chatbot/v1/health
 * Returns JSON with system status, database connectivity, and environment checks.
 *
 * Usage (in main plugin bootstrap):
 *   require_once __DIR__ . '/../lib/health-endpoint.php';
 *   add_action( 'rest_api_init', 'wpaic_register_health_endpoint' );
 */

if (! defined('ABSPATH')) {
	define('WPAIC_HEALTH_STANDALONE', true);
}

/**
 * Register the health REST route.
 */
function wpaic_register_health_endpoint(): void
{
	register_rest_route('wp-ai-chatbot/v1', '/health', [
		'methods' => 'GET',
		'callback' => 'wpaic_health_callback',
		'permission_callback' => '__return_true',
	]);
}

/**
 * Health endpoint callback.
 *
 * @param WP_REST_Request $request The incoming request.
 * @return WP_REST_Response
 */
function wpaic_health_callback($request)
{
	$checks = [];
	$overall = 'healthy';

	// 1. PHP version check.
	$php_ok = version_compare(PHP_VERSION, '7.4.0', '>=');
	$checks['php'] = [
		'status' => $php_ok ? 'pass' : 'fail',
		'version' => PHP_VERSION,
		'minimum' => '7.4.0',
	];

	// 2. WordPress version check.
	$wp_version = get_bloginfo('version');
	$wp_ok = version_compare($wp_version, '5.8', '>=');
	$checks['wordpress'] = [
		'status' => $wp_ok ? 'pass' : 'fail',
		'version' => $wp_version,
		'minimum' => '5.8',
	];

	// 3. Database connectivity.
	$checks['database'] = wpaic_check_database();

	// 4. Required PHP extensions.
	$checks['extensions'] = wpaic_check_extensions();

	// 5. Memory usage.
	$memory_limit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
	$memory_used = memory_get_usage(true);
	$checks['memory'] = [
		'status' => 'pass',
		'used_mb' => round($memory_used / 1048576, 1),
		'limit_mb' => $memory_limit > 0 ? round($memory_limit / 1048576, 1) : 'unlimited',
	];

	// Determine overall status.
	foreach ($checks as $check) {
		if (isset($check['status']) && $check['status'] === 'fail') {
			$overall = 'unhealthy';
			break;
		}
	}

	$response_data = [
		'status' => $overall,
		'checks' => $checks,
		'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
	];

	$status_code = $overall === 'healthy' ? 200 : 503;

	return new WP_REST_Response($response_data, $status_code);
}

/**
 * Check database connectivity via $wpdb.
 *
 * @return array{status: string, latency_ms?: float, error?: string}
 */
function wpaic_check_database(): array
{
	global $wpdb;

	$start = microtime(true);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$result = $wpdb->get_var('SELECT 1');
	$elapsed_ms = round((microtime(true) - $start) * 1000, 2);

	if ($result === '1' || $result === 1) {
		return [
			'status' => 'pass',
			'latency_ms' => $elapsed_ms,
		];
	}

	return [
		'status' => 'fail',
		'error' => $wpdb->last_error ?: 'Query returned unexpected result',
	];
}

/**
 * Check required PHP extensions.
 *
 * @return array{status: string, loaded: string[], missing: string[]}
 */
function wpaic_check_extensions(): array
{
	$required = ['curl', 'json', 'mbstring', 'openssl'];
	$loaded = [];
	$missing = [];

	foreach ($required as $ext) {
		if (extension_loaded($ext)) {
			$loaded[] = $ext;
		} else {
			$missing[] = $ext;
		}
	}

	return [
		'status' => empty($missing) ? 'pass' : 'fail',
		'loaded' => $loaded,
		'missing' => $missing,
	];
}
