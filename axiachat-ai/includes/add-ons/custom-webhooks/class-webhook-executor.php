<?php

/**
 * Custom Webhook Executor
 *
 * Handles HTTP execution of custom webhooks with parameter merging,
 * conversation history inclusion, and response handling.
 *
 * @package AIChat
 * @subpackage CustomWebhooks
 */

if (! defined('ABSPATH')) {
	exit;
}

class AIChat_Webhook_Executor
{
	/**
	 * Execute a custom webhook
	 *
	 * @param array $webhook Webhook definition from database
	 * @param array $ai_params Parameters provided by the AI model
	 * @param array $context Execution context (session_id, bot_slug, history, etc.)
	 * @return array Result with 'ok', 'message' or 'error'
	 */
	public static function execute($webhook, $ai_params = [], $context = [])
	{
		$url = $webhook['webhook_url'] ?? '';
		$method = strtoupper($webhook['http_method'] ?? 'POST');

		// Validate URL
		if (empty($url) || ! filter_var($url, FILTER_VALIDATE_URL)) {
			return [
				'ok' => false,
				'error' => 'invalid_url',
				'message' => $webhook['error_message'] ?? __('Could not complete the action.', 'axiachat-ai'),
			];
		}

		// Security: Block internal URLs
		$parsed = wp_parse_url($url);
		$host = $parsed['host'] ?? '';
		$blocked_hosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1'];
		if (in_array($host, $blocked_hosts, true) || preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.)/', $host)) {
			return [
				'ok' => false,
				'error' => 'blocked_host',
				'message' => $webhook['error_message'] ?? __('Could not complete the action.', 'axiachat-ai'),
			];
		}

		// Merge parameters: AI params + static params
		$static = $webhook['static_params'] ?? [];
		if (is_string($static)) {
			$static = json_decode($static, true) ?: [];
		}

		// Replace placeholders in static params
		$static = self::replace_placeholders($static, $context);

		$payload = array_merge($ai_params, $static);

		// Add conversation history if enabled
		if (! empty($webhook['include_history']) && ! empty($context['history'])) {
			$limit = (int) ($webhook['history_limit'] ?? 10);
			$history = array_slice($context['history'], -$limit);
			$payload['conversation_history'] = $history;
		}

		// Add metadata
		$payload['_meta'] = [
			'source' => 'axiachat',
			'bot_slug' => $context['bot_slug'] ?? '',
			'session_id' => $context['session_id'] ?? '',
			'timestamp' => current_time('c'),
			'webhook_slug' => $webhook['slug'] ?? '',
		];

		// Build headers
		$headers = [
			'Content-Type' => 'application/json',
			'User-Agent' => 'AxiaChat-Webhook/1.0',
		];

		$custom_headers = $webhook['headers'] ?? [];
		if (is_string($custom_headers)) {
			$custom_headers = json_decode($custom_headers, true) ?: [];
		}

		foreach ($custom_headers as $key => $value) {
			$headers[ sanitize_text_field($key) ] = sanitize_text_field($value);
		}

		// Build request args
		$args = [
			'method' => $method,
			'timeout' => 15,
			'headers' => $headers,
			'sslverify' => true,
		];

		if ($method === 'GET') {
			$url = add_query_arg($payload, $url);
		} else {
			$args['body'] = wp_json_encode($payload);
		}

		// Execute request
		$start = microtime(true);
		$response = wp_remote_request($url, $args);
		$duration = round((microtime(true) - $start) * 1000);

		// Log execution
		self::log_execution($webhook, $payload, $response, $duration, $context);

		// Handle response
		if (is_wp_error($response)) {
			aichat_log_debug('[Custom Webhook] Error: ' . $response->get_error_message(), [
				'webhook' => $webhook['slug'],
				'url' => $url,
			]);

			return [
				'ok' => false,
				'error' => $response->get_error_code(),
				'message' => $webhook['error_message'] ?? __('Could not complete the action. Please try again.', 'axiachat-ai'),
			];
		}

		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);

		if ($code >= 200 && $code < 300) {
			// Success
			$decoded = json_decode($body, true);

			return [
				'ok' => true,
				'message' => $webhook['success_message'] ?? __('Action completed successfully.', 'axiachat-ai'),
				'response' => $decoded ?: $body,
				'duration_ms' => $duration,
			];
		} else {
			// HTTP error
			aichat_log_debug('[Custom Webhook] HTTP Error', [
				'webhook' => $webhook['slug'],
				'code' => $code,
				'body' => mb_substr($body, 0, 200),
			]);

			return [
				'ok' => false,
				'error' => 'http_' . $code,
				'message' => $webhook['error_message'] ?? __('Could not complete the action.', 'axiachat-ai'),
			];
		}
	}

	/**
	 * Replace placeholders in static params
	 */
	private static function replace_placeholders($params, $context)
	{
		if (! is_array($params)) {
			return $params;
		}

		$replacements = [
			'{{NOW}}' => current_time('c'),
			'{{DATE}}' => current_time('Y-m-d'),
			'{{TIME}}' => current_time('H:i:s'),
			'{{TIMESTAMP}}' => time(),
			'{{SESSION_ID}}' => $context['session_id'] ?? '',
			'{{BOT_SLUG}}' => $context['bot_slug'] ?? '',
			'{{PAGE_URL}}' => $context['page_url'] ?? '',
			'{{USER_IP}}' => self::get_client_ip(),
		];

		foreach ($params as $key => $value) {
			if (is_string($value)) {
				foreach ($replacements as $placeholder => $replacement) {
					$value = str_replace($placeholder, $replacement, $value);
				}
				$params[ $key ] = $value;
			}
		}

		return $params;
	}

	/**
	 * Get client IP (privacy-aware)
	 */
	private static function get_client_ip()
	{
		// Only return if usage limits are enabled (which requires IP tracking)
		if (! get_option('aichat_usage_limits_enabled', 0)) {
			return 'hidden';
		}

		$ip = '';
		if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])))[0];
		} elseif (! empty($_SERVER['REMOTE_ADDR'])) {
			$ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
		}

		return $ip;
	}

	/**
	 * Log webhook execution
	 */
	private static function log_execution($webhook, $payload, $response, $duration, $context)
	{
		global $wpdb;
		$table = $wpdb->prefix . 'aichat_webhook_logs';

		// Check if log table exists (optional feature)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var($wpdb->prepare(
			'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s',
			$table
		));

		if (! $exists) {
			return; // Logging table not created yet
		}

		$code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
		$body = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert($table, [
			'webhook_id' => $webhook['id'] ?? 0,
			'webhook_slug' => $webhook['slug'] ?? '',
			'session_id' => $context['session_id'] ?? '',
			'bot_slug' => $context['bot_slug'] ?? '',
			'payload_excerpt' => mb_substr(wp_json_encode($payload), 0, 1000),
			'response_code' => $code,
			'response_excerpt' => mb_substr($body, 0, 500),
			'duration_ms' => $duration,
			'created_at' => current_time('mysql'),
		]);
	}
}
