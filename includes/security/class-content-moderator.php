<?php

/**
 * Content Moderator — keyword-based pre-send message filtering.
 *
 * Hooks into the `wpaic_moderate_message` filter (priority 10) and checks
 * incoming user messages against a configurable term blocklist.
 *
 * The filter contract:
 *   apply_filters( 'wpaic_moderate_message', bool $blocked, string $message, array $context )
 *
 * When `enabled` is false the filter short-circuits immediately. Blocked
 * messages are logged as audit events; the pipeline should return a neutral
 * "cannot help with that" response without revealing the match reason.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Security;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Content_Moderator
 *
 * Enforces a configurable keyword blocklist on incoming chat messages.
 *
 * @since 1.0.0
 * @package WPAIC\Security
 */
class Content_Moderator
{
	/** WP option key for moderation settings (serialised array). */
	public const SETTINGS_OPTION = 'wpaic_moderation_settings';

	// =========================================================================
	// Hook registration
	// =========================================================================

	/**
	 * Register the moderation filter hook.
	 * Called once from Plugin::on_init() via register_core_security().
	 *
	 * @return void
	 */
	public static function register_hooks(): void
	{
		add_filter('wpaic_moderate_message', [static::class, 'check_blocklist'], 10, 3);
	}

	// =========================================================================
	// Filter callback
	// =========================================================================

	/**
	 * Check the message against the configured keyword blocklist.
	 *
	 * Returns true (blocked) when any configured term is found in the message,
	 * provided moderation is enabled. Passes through any upstream block (first
	 * arg already true).
	 *
	 * @param  bool   $blocked  Current blocked state from upstream filters.
	 * @param  string $message  Raw user message text.
	 * @param  array  $context  Pipeline context (session_id, bot_slug, ip, etc.).
	 * @return bool True when message should be blocked.
	 */
	public static function check_blocklist(bool $blocked, string $message, array $context = []): bool
	{
		// Already blocked by a higher-priority filter — respect it.
		if ($blocked) {
			return true;
		}

		$settings = self::get_settings();

		if (empty($settings['enabled'])) {
			return false;
		}

		$blocklist = (string) ($settings['blocklist'] ?? '');
		if ('' === $blocklist) {
			return false;
		}

		// Parse newline-delimited terms; skip blank lines.
		$terms = array_filter(
			array_map('trim', explode("\n", $blocklist)),
			static fn (string $t): bool => '' !== $t
		);

		if (empty($terms)) {
			return false;
		}

		$message_lower = mb_strtolower($message);

		foreach ($terms as $term) {
			if (mb_strpos($message_lower, mb_strtolower($term)) !== false) {
				self::log_blocked_event($context, $term);

				return true;
			}
		}

		return false;
	}

	// =========================================================================
	// Settings helpers
	// =========================================================================

	/**
	 * Retrieve current moderation settings with defaults applied.
	 *
	 * @return array{enabled: bool, blocklist: string, response_message: string}
	 */
	public static function get_settings(): array
	{
		$saved = get_option(self::SETTINGS_OPTION, []);

		return wp_parse_args(
			is_array($saved) ? $saved : [],
			[
				'enabled' => false,
				'blocklist' => '',
				'response_message' => '',
			]
		);
	}

	/**
	 * Sanitize and validate settings before saving via the Settings API.
	 *
	 * @param  mixed $input Raw form input.
	 * @return array Sanitized settings array.
	 */
	public static function sanitize_settings($input): array
	{
		if (! is_array($input)) {
			$input = [];
		}

		return [
			'enabled' => ! empty($input['enabled']),
			'blocklist' => sanitize_textarea_field($input['blocklist'] ?? ''),
			'response_message' => sanitize_text_field($input['response_message'] ?? ''),
		];
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Write a structured audit log entry for a blocked message.
	 *
	 * @param  array  $context Pipeline context for tracing.
	 * @param  string $term    The matched blocklist term (NOT logged in full to avoid leaking it).
	 * @return void
	 */
	private static function log_blocked_event(array $context, string $term): void
	{
		if (! function_exists('wpaic_structured_log')) {
			return;
		}

		wpaic_structured_log(
			'security',
			'message_blocked',
			[
				'reason' => 'blocklist_match',
				'term_length' => mb_strlen($term),
				'bot_slug' => sanitize_key($context['bot_slug'] ?? 'unknown'),
				'session_id' => sanitize_key($context['session_id'] ?? ''),
			],
			'warning'
		);
	}
}
