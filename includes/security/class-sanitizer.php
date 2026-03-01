<?php

/**
 * Unified sanitization library — domain-specific sanitizers as a central registry.
 *
 * Every domain module routes input through Sanitizer::sanitize() or a typed alias.
 * No inline ad-hoc sanitization in domain code (SCR-03).
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Security;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Sanitizer
 *
 * Central, extensible sanitization registry.
 * All TYPE_* constants map to a registered sanitizer callable.
 * Extensions register custom types via the `wpaic_sanitizer_types` filter (SCR-04).
 */
final class Sanitizer
{
	// =========================================================================
	// Type constants (one per domain input category)
	// =========================================================================

	public const TYPE_MESSAGE = 'message';
	public const TYPE_SYSTEM_PROMPT = 'system_prompt';
	public const TYPE_API_KEY = 'api_key';
	public const TYPE_SLUG = 'slug';
	public const TYPE_INT = 'int';
	public const TYPE_FLOAT = 'float';
	public const TYPE_BOOL = 'bool';
	public const TYPE_URL = 'url';
	public const TYPE_HTML_RESTRICTED = 'html_restricted';
	public const TYPE_FILENAME = 'filename';
	public const TYPE_IP = 'ip';
	public const TYPE_JSON = 'json';

	/**
	 * HTML elements/attributes allowed by TYPE_HTML_RESTRICTED.
	 * SCR-06: Not configurable at runtime — allowlist is defined here as a constant.
	 */
	public const HTML_ALLOWLIST = [
		'a' => ['href' => [], 'title' => [], 'target' => [], 'rel' => []],
		'b' => [],
		'em' => [],
		'i' => [],
		'strong' => [],
		'ul' => [],
		'ol' => [],
		'li' => [],
		'p' => [],
		'br' => [],
		'code' => [],
		'pre' => [],
		'blockquote' => [],
	];

	/** @var array<string, callable>|null Lazily built registry. */
	private static $registry = null;

	// =========================================================================
	// Primary interface
	// =========================================================================

	/**
	 * Sanitize a value using a registered type.
	 *
	 * @param  mixed  $value  Raw input.
	 * @param  string $type   One of the TYPE_* constants.
	 * @param  array  $params Type-specific options (e.g., ['min' => 0, 'max' => 100]).
	 * @return mixed          Sanitized value; PHP type guaranteed per type constant.
	 */
	public static function sanitize($value, string $type, array $params = [])
	{
		$registry = self::get_registry();

		if (! isset($registry[$type])) {
			// Unknown type — return a safely-cast string as a conservative default.
			_doing_it_wrong(
				__METHOD__,
				esc_html(sprintf('Unknown sanitizer type: %s', $type)),
				'1.0.0'
			);

			return sanitize_text_field((string) $value);
		}

		return ($registry[$type])($value, $params);
	}

	/**
	 * Validate and sanitize; return WP_Error on validation failure.
	 *
	 * @param  mixed  $value
	 * @param  string $type
	 * @param  array  $params
	 * @return mixed|\WP_Error Sanitized value, or WP_Error with code 'wpaic_invalid_input'.
	 */
	public static function validate($value, string $type, array $params = [])
	{
		$sanitized = self::sanitize($value, $type, $params);

		// Detect validation failures per type.
		switch ($type) {
			case self::TYPE_URL:
				if ('' !== (string) $value && '' === $sanitized) {
					return new \WP_Error('wpaic_invalid_input', __('Invalid or non-HTTPS URL.', 'wp-ai-chatbot'));
				}
				break;

			case self::TYPE_IP:
				if ('' !== (string) $value && null === $sanitized) {
					return new \WP_Error('wpaic_invalid_input', __('Invalid IP address.', 'wp-ai-chatbot'));
				}
				break;

			case self::TYPE_JSON:
				if ('' !== (string) $value && '' === $sanitized) {
					return new \WP_Error('wpaic_invalid_input', __('Invalid or oversized JSON.', 'wp-ai-chatbot'));
				}
				break;
		}

		return $sanitized;
	}

	/**
	 * Sanitize an associative array of values using a type map.
	 * Extras not present in $type_map are stripped (whitelist approach).
	 *
	 * @param  array<string, mixed>  $data     Raw associative array.
	 * @param  array<string, array>  $type_map ['field' => ['type' => TYPE_*, 'params' => [...]]]
	 * @return array<string, mixed>            Sanitized values for mapped fields only.
	 */
	public static function sanitize_map(array $data, array $type_map): array
	{
		$result = [];

		foreach ($type_map as $field => $config) {
			$type = $config['type'] ?? self::TYPE_MESSAGE;
			$params = $config['params'] ?? [];
			$raw = $data[$field] ?? '';
			$result[$field] = self::sanitize($raw, $type, $params);
		}

		return $result;
	}

	/**
	 * Register a custom sanitizer type (called via wpaic_sanitizer_types filter).
	 *
	 * @param  string   $type     Type constant string.
	 * @param  callable $callback function($value, array $params): mixed
	 * @return void
	 */
	public static function register_type(string $type, callable $callback): void
	{
		// Ensure registry is bootstrapped before registering.
		self::get_registry();
		self::$registry[$type] = $callback;
	}

	// =========================================================================
	// Typed method aliases (preferred for readability in domain code)
	// =========================================================================

	/** Sanitize a user chat message (max 10 000 chars, no PHP tags, no <script>). */
	public static function sanitize_message(string $value): string
	{
		return (string) self::sanitize($value, self::TYPE_MESSAGE);
	}

	/** Sanitize a bot system prompt (max 50 000 chars, template tokens allowed). */
	public static function sanitize_system_prompt(string $value): string
	{
		return (string) self::sanitize($value, self::TYPE_SYSTEM_PROMPT);
	}

	/** Sanitize an API key string (max 200 chars, alphanumeric + -_.:). */
	public static function sanitize_api_key(string $value): string
	{
		return (string) self::sanitize($value, self::TYPE_API_KEY);
	}

	/** Sanitize a model/provider slug (max 64 chars, [a-z0-9_-] only). */
	public static function sanitize_slug(string $value): string
	{
		return (string) self::sanitize($value, self::TYPE_SLUG);
	}

	/** Sanitize an integer within optional bounds. */
	public static function sanitize_int($value, int $min = 0, int $max = PHP_INT_MAX): int
	{
		return (int) self::sanitize($value, self::TYPE_INT, ['min' => $min, 'max' => $max]);
	}

	/** Sanitize a float within optional bounds; returns null on invalid input. */
	public static function sanitize_float($value, float $min = 0.0, float $max = PHP_FLOAT_MAX): ?float
	{
		return self::sanitize($value, self::TYPE_FLOAT, ['min' => $min, 'max' => $max]);
	}

	/** Sanitize a boolean toggle value. */
	public static function sanitize_bool($value): bool
	{
		return (bool) self::sanitize($value, self::TYPE_BOOL);
	}

	/** Sanitize a URL (HTTPS-only, esc_url_raw). */
	public static function sanitize_url(string $value): string
	{
		return (string) self::sanitize($value, self::TYPE_URL);
	}

	/** Sanitize restricted HTML (wp_kses with HTML_ALLOWLIST, max 2 000 chars). */
	public static function sanitize_html(string $value): string
	{
		return (string) self::sanitize($value, self::TYPE_HTML_RESTRICTED);
	}

	/** Sanitize an IP address (IPv4/IPv6 validation); returns null on invalid. */
	public static function sanitize_ip(string $value): ?string
	{
		return self::sanitize($value, self::TYPE_IP);
	}

	/** Sanitize a JSON string (decode + re-encode, max 64 KB). */
	public static function sanitize_json(string $value): string
	{
		return (string) self::sanitize($value, self::TYPE_JSON);
	}

	// =========================================================================
	// Registry bootstrap
	// =========================================================================

	/**
	 * Build (or return cached) the sanitizer registry.
	 *
	 * @return array<string, callable>
	 */
	private static function get_registry(): array
	{
		if (null !== self::$registry) {
			return self::$registry;
		}

		self::$registry = self::build_default_registry();

		// Allow extensions to add/override types (SCR-04).
		self::$registry = (array) apply_filters('wpaic_sanitizer_types', self::$registry);

		return self::$registry;
	}

	/**
	 * Build the built-in sanitizer registry.
	 *
	 * @return array<string, callable>
	 */
	private static function build_default_registry(): array
	{
		return [

			// ------------------------------------------------------------------
			// TYPE_MESSAGE — user chat message
			// ------------------------------------------------------------------
			self::TYPE_MESSAGE => static function ($value, array $params): string {
				$value = (string) $value;

				// Strip null bytes.
				$value = str_replace("\0", '', $value);

				// Remove PHP open/close tags.
				$value = (string) preg_replace('/<\?(?:php)?.*?\?>/is', '', $value);

				// Strip <script> and its content.
				$value = (string) wp_strip_all_tags(
					(string) preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $value)
				);

				// Enforce character limit.
				return mb_substr($value, 0, 10000);
			},

			// ------------------------------------------------------------------
			// TYPE_SYSTEM_PROMPT — bot instruction text (allows template tokens)
			// ------------------------------------------------------------------
			self::TYPE_SYSTEM_PROMPT => static function ($value, array $params): string {
				$value = (string) $value;
				$value = str_replace("\0", '', $value);
				$value = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $value);
				// Strip HTML but allow template tokens like {date}, {url}.
				$value = (string) wp_strip_all_tags($value);

				return mb_substr($value, 0, 50000);
			},

			// ------------------------------------------------------------------
			// TYPE_API_KEY — provider/Pinecone API key
			// ------------------------------------------------------------------
			self::TYPE_API_KEY => static function ($value, array $params): string {
				$value = trim((string) $value);
				// Keep only alphanumeric + safe key chars.
				$value = (string) preg_replace('/[^a-zA-Z0-9\-_.:]/', '', $value);

				return mb_substr($value, 0, 200);
			},

			// ------------------------------------------------------------------
			// TYPE_SLUG — model slug, provider slug, source type
			// ------------------------------------------------------------------
			self::TYPE_SLUG => static function ($value, array $params): string {
				$value = mb_strtolower(trim((string) $value));
				$value = (string) preg_replace('/[^a-z0-9_\-]/', '', $value);

				return mb_substr($value, 0, 64);
			},

			// ------------------------------------------------------------------
			// TYPE_INT — integer settings (limits, counts, timeouts)
			// ------------------------------------------------------------------
			self::TYPE_INT => static function ($value, array $params): int {
				$int = absint($value);
				$min = isset($params['min']) ? (int) $params['min'] : 0;
				$max = isset($params['max']) ? (int) $params['max'] : PHP_INT_MAX;

				return max($min, min($max, $int));
			},

			// ------------------------------------------------------------------
			// TYPE_FLOAT — temperature, similarity threshold
			// ------------------------------------------------------------------
			self::TYPE_FLOAT => static function ($value, array $params): ?float {
				if (! is_numeric($value)) {
					return null;
				}
				$float = (float) $value;
				$min = isset($params['min']) ? (float) $params['min'] : 0.0;
				$max = isset($params['max']) ? (float) $params['max'] : PHP_FLOAT_MAX;
				if ($float < $min || $float > $max) {
					return null;
				}

				return $float;
			},

			// ------------------------------------------------------------------
			// TYPE_BOOL — toggle settings
			// ------------------------------------------------------------------
			self::TYPE_BOOL => static function ($value, array $params): bool {
				if (is_bool($value)) {
					return $value;
				}
				if (is_int($value)) {
					return 1 === $value;
				}
				$str = strtolower(trim((string) $value));

				return in_array($str, ['1', 'true', 'yes', 'on'], true);
			},

			// ------------------------------------------------------------------
			// TYPE_URL — HTTPS-only URLs (Pinecone endpoint, webhook URL)
			// ------------------------------------------------------------------
			self::TYPE_URL => static function ($value, array $params): string {
				$url = esc_url_raw(trim((string) $value));

				// HTTPS-only enforcement (SCR-05).
				if ('' !== $url && strpos($url, 'https://') !== 0) {
					return '';
				}

				// Optional domain allowlist via filter.
				$allowed = (array) apply_filters('wpaic_allowed_domains', []);
				if (! empty($allowed) && '' !== $url) {
					$host = wp_parse_url($url, PHP_URL_HOST);
					if (! in_array($host, $allowed, true)) {
						return '';
					}
				}

				return $url;
			},

			// ------------------------------------------------------------------
			// TYPE_HTML_RESTRICTED — bot description, tooltip text
			// ------------------------------------------------------------------
			self::TYPE_HTML_RESTRICTED => static function ($value, array $params): string {
				$value = wp_kses((string) $value, self::HTML_ALLOWLIST);

				return mb_substr($value, 0, 2000);
			},

			// ------------------------------------------------------------------
			// TYPE_FILENAME — log file names, export file names
			// ------------------------------------------------------------------
			self::TYPE_FILENAME => static function ($value, array $params): string {
				$value = sanitize_file_name((string) $value);
				// Strip path traversal attempts.
				$value = (string) str_replace(['../', '..\\', '../', '..\\'], '', $value);

				return mb_substr($value, 0, 128);
			},

			// ------------------------------------------------------------------
			// TYPE_IP — client IP (IPv4 / IPv6)
			// ------------------------------------------------------------------
			self::TYPE_IP => static function ($value, array $params): ?string {
				$value = trim((string) $value);
				if (filter_var($value, FILTER_VALIDATE_IP) === false) {
					return null;
				}

				return $value;
			},

			// ------------------------------------------------------------------
			// TYPE_JSON — stored serialized config (defensive deserialization)
			// ------------------------------------------------------------------
			self::TYPE_JSON => static function ($value, array $params): string {
				$value = (string) $value;

				// Reject oversized payloads (64 KB).
				if (strlen($value) > 65536) {
					return '';
				}

				$decoded = json_decode($value, true);
				if (! is_array($decoded) && ! is_object($decoded)) {
					return '';
				}

				$re_encoded = wp_json_encode($decoded);

				return false === $re_encoded ? '' : $re_encoded;
			},
		];
	}
}
