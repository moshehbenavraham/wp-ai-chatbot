<?php

/**
 * Webhook Tool — implements Tool_Interface backed by an HTTP webhook.
 *
 * Stores configuration as a plain PHP array (loaded from wpaic_webhook_tools option).
 * Executes webhooks via wp_remote_request with:
 *   - URL validation and SSRF protection (no private/loopback IPs)
 *   - Placeholder substitution ({{NOW}}, {{SESSION_ID}}, {{BOT_SLUG}}, etc.)
 *   - Static parameter merging
 *   - Configurable HTTP method (GET/POST, default POST)
 *   - Configurable timeout (default 15s, max 30s)
 *
 * Config shape:
 *   id           — unique snake_case ID (required)
 *   name         — human-readable name (required)
 *   description  — description for the LLM (required)
 *   webhook_url  — target URL (required)
 *   http_method  — GET or POST (default: POST)
 *   parameters   — JSON Schema parameters block (default: empty object)
 *   static_params — JSON object of extra params always merged into request body
 *   permission   — 'none'|'user'|'admin' (default: 'none')
 *   timeout      — request timeout in seconds (default: 15)
 *   success_message — text returned to LLM on success (default: auto-extract body)
 *   error_message   — text returned to LLM on failure
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Tools\Adapters;

use WPAIC\Tools\Tool_Interface;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Webhook_Tool
 *
 * @since 1.0.0
 * @package WPAIC\Tools\Adapters
 */
class Webhook_Tool implements Tool_Interface
{
	/** Blocked private/loopback host patterns (SSRF guard). */
	private const BLOCKED_HOSTS = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];

	/** @var array Tool configuration. */
	private $config;

	/**
	 * Constructor.
	 *
	 * @param array $config Webhook configuration array (see class docblock).
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
	}

	// =========================================================================
	// Tool_Interface contract
	// =========================================================================

	public function get_id(): string
	{
		return (string) ($this->config['id'] ?? '');
	}

	public function get_name(): string
	{
		return (string) ($this->config['name'] ?? '');
	}

	public function get_description(): string
	{
		return mb_substr((string) ($this->config['description'] ?? ''), 0, 600);
	}

	public function get_schema(): array
	{
		$schema = $this->config['parameters'] ?? [];

		if (! is_array($schema) || empty($schema)) {
			return [
				'type' => 'object',
				'properties' => (object) [],
			];
		}

		// Ensure type=object.
		if (empty($schema['type'])) {
			$schema['type'] = 'object';
		}

		// Ensure properties is a non-empty-indexed array (OpenAI requires an object).
		if (! isset($schema['properties']) || (is_array($schema['properties']) && empty($schema['properties']))) {
			$schema['properties'] = (object) [];
		}

		return $schema;
	}

	public function get_permission(): string
	{
		$perm = $this->config['permission'] ?? 'none';

		return in_array($perm, ['none', 'user', 'admin'], true) ? $perm : 'none';
	}

	/**
	 * Execute the webhook HTTP call.
	 *
	 * @param  array $args    Tool arguments from LLM.
	 * @param  array $context Execution context.
	 * @return array {ok, output, error?, message?, duration_ms}
	 */
	public function execute(array $args, array $context): array
	{
		$url = (string) ($this->config['webhook_url'] ?? '');
		$method = strtoupper((string) ($this->config['http_method'] ?? 'POST'));

		// Validate URL.
		if ('' === $url || ! filter_var($url, FILTER_VALIDATE_URL)) {
			return $this->error('invalid_url', __('Webhook URL is not valid.', 'wp-ai-chatbot'));
		}

		// SSRF guard: block private / loopback / RFC-1918 addresses.
		if ($this->is_blocked_host($url)) {
			return $this->error('blocked_host', __('Webhook target host is not allowed.', 'wp-ai-chatbot'));
		}

		// Build payload: merge AI args + static params + placeholders.
		$static = $this->resolve_static_params($context);
		$payload = array_merge($args, $static);
		$payload = $this->replace_placeholders($payload, $context);

		// Add request metadata.
		$payload['_meta'] = [
			'source' => 'wp-ai-chatbot',
			'bot_slug' => $context['bot_slug'] ?? '',
			'session_id' => $context['session_id'] ?? '',
			'timestamp' => current_time('c'),
			'webhook_id' => $this->get_id(),
		];

		// Build headers.
		$headers = ['Content-Type' => 'application/json', 'User-Agent' => 'WP-AI-Chatbot-Webhook/1.0'];
		$custom = $this->config['headers'] ?? [];
		if (is_string($custom)) {
			$custom = json_decode($custom, true) ?: [];
		}

		foreach ((array) $custom as $k => $v) {
			$headers[ sanitize_text_field((string) $k) ] = sanitize_text_field((string) $v);
		}

		$timeout = min((int) ($this->config['timeout'] ?? 15), 30);

		$args_wp = [
			'method' => $method,
			'timeout' => $timeout,
			'headers' => $headers,
			'sslverify' => true,
		];

		if ('GET' === $method) {
			$url = add_query_arg(
				array_map('urlencode', array_map('strval', $payload)),
				$url
			);
		} else {
			$args_wp['body'] = wp_json_encode($payload);
		}

		$started = microtime(true);
		$response = wp_remote_request($url, $args_wp);
		$elapsed = (int) round((microtime(true) - $started) * 1000);

		if (is_wp_error($response)) {
			return array_merge(
				$this->error('request_failed', $response->get_error_message()),
				['duration_ms' => $elapsed]
			);
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = (string) wp_remote_retrieve_body($response);

		if ($code >= 200 && $code < 300) {
			// Use configured success_message, or extract from response body.
			$success_msg = (string) ($this->config['success_message'] ?? '');
			if ('' === $success_msg) {
				$decoded = json_decode($body, true);
				if (is_array($decoded)) {
					// Try common output keys.
					$success_msg = (string) ($decoded['message'] ?? $decoded['output'] ?? $decoded['result'] ?? '');
				}
				if ('' === $success_msg) {
					$success_msg = mb_substr($body, 0, 1000);
				}
			}

			return [
				'ok' => true,
				'output' => $success_msg,
				'duration_ms' => $elapsed,
			];
		}

		// HTTP error.
		$err_msg = (string) ($this->config['error_message'] ?? '');
		if ('' === $err_msg) {
			$err_msg = sprintf(
				// translators: %d: HTTP status code.
				__('Webhook request failed (HTTP %d).', 'wp-ai-chatbot'),
				$code
			);
		}

		return array_merge(
			$this->error('http_' . $code, $err_msg),
			['duration_ms' => $elapsed]
		);
	}

	public function to_provider_definition(): array
	{
		return [
			'name' => $this->get_id(),
			'description' => $this->get_description(),
			'parameters' => $this->get_schema(),
		];
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Check whether the target URL resolves to a blocked host (SSRF protection).
	 *
	 * @param  string $url
	 * @return bool
	 */
	private function is_blocked_host(string $url): bool
	{
		$parsed = wp_parse_url($url);
		$host = strtolower($parsed['host'] ?? '');

		if (in_array($host, self::BLOCKED_HOSTS, true)) {
			return true;
		}

		// Block RFC-1918 private ranges and link-local.
		if (preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|169\.254\.)/', $host)) {
			return true;
		}

		return false;
	}

	/**
	 * Decode and return the static_params from config.
	 *
	 * @param  array $context Execution context for placeholder resolution.
	 * @return array
	 */
	private function resolve_static_params(array $context): array
	{
		$raw = $this->config['static_params'] ?? [];

		if (is_string($raw)) {
			$raw = json_decode($raw, true) ?: [];
		}

		return is_array($raw) ? $raw : [];
	}

	/**
	 * Replace {{PLACEHOLDER}} tokens in string values of the payload.
	 *
	 * @param  array $params  Parameter array.
	 * @param  array $context Execution context.
	 * @return array
	 */
	private function replace_placeholders(array $params, array $context): array
	{
		$map = [
			'{{NOW}}' => current_time('c'),
			'{{DATE}}' => current_time('Y-m-d'),
			'{{TIME}}' => current_time('H:i:s'),
			'{{TIMESTAMP}}' => (string) time(),
			'{{SESSION_ID}}' => $context['session_id'] ?? '',
			'{{BOT_SLUG}}' => $context['bot_slug'] ?? '',
			'{{PAGE_URL}}' => $context['page_url'] ?? '',
		];

		foreach ($params as $key => $value) {
			if (is_string($value)) {
				$params[$key] = strtr($value, $map);
			}
		}

		return $params;
	}

	/**
	 * Build a standard error result array.
	 *
	 * @param  string $code    Error code.
	 * @param  string $message Human-readable message.
	 * @return array
	 */
	private function error(string $code, string $message): array
	{
		return [
			'ok' => false,
			'output' => '',
			'error' => $code,
			'message' => $message,
		];
	}
}
