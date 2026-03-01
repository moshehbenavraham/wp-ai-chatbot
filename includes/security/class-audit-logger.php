<?php

/**
 * Audit logger — structured security event logging with 15 EVENT_* constants.
 *
 * Routes to lib/structured-logger.php with subsystem tag 'audit'.
 * Privacy rule: no raw IPs, no message content, no API key values, no nonces.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Security;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Audit_Logger
 *
 * All security-relevant events flow through Audit_Logger::log().
 * The query() method supports the admin Diagnostics > Logs panel.
 */
final class Audit_Logger
{
	// =========================================================================
	// Event type constants
	// =========================================================================

	public const EVENT_AUTH_FAIL = 'auth_fail';
	public const EVENT_RATE_LIMIT = 'rate_limit';
	public const EVENT_MODERATION_BLOCK = 'moderation_block';
	public const EVENT_ENCRYPTION_DEGRADED = 'encryption_degraded';
	public const EVENT_KEY_ROTATION = 'key_rotation';
	public const EVENT_KEY_CHANGE_DETECTED = 'key_change_detected';
	public const EVENT_DATA_EXPORT = 'data_export';
	public const EVENT_DATA_ERASURE = 'data_erasure';
	public const EVENT_SETTINGS_CHANGE = 'settings_change';
	public const EVENT_PROVIDER_KEY_SET = 'provider_key_set';
	public const EVENT_MIGRATION_EXECUTED = 'migration_executed';
	public const EVENT_PLAINTEXT_KEY_MIGRATED = 'plaintext_key_migrated';
	public const EVENT_SPAM_BLOCKED = 'spam_blocked';
	public const EVENT_AI_MODERATION_BLOCKED = 'ai_moderation_blocked';
	public const EVENT_QUEUE_STALL = 'queue_stall';

	/** Subsystem tag written to every structured log entry. */
	private const SUBSYSTEM = 'audit';

	/** Valid severity levels (maps to structured-logger levels). */
	private const VALID_SEVERITIES = ['info', 'warn', 'error', 'critical'];

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Log a security audit event.
	 *
	 * @param  string $event_type One of the EVENT_* constants.
	 * @param  array  $context    Event-specific structured data (no raw IPs, no keys, no nonces).
	 * @param  string $severity   'info'|'warn'|'error'|'critical'
	 * @return void
	 */
	public static function log(
		string $event_type,
		array $context = [],
		string $severity = 'info'
	): void {
		if (! in_array($severity, self::VALID_SEVERITIES, true)) {
			$severity = 'info';
		}

		// Merge standard fields into context.
		$entry = array_merge(
			[
				'event_type' => $event_type,
				'plugin_version' => defined('WPAIC_VERSION') ? WPAIC_VERSION : 'unknown',
				'wp_version' => get_bloginfo('version'),
			],
			$context
		);

		// Route to structured logger when available.
		if (function_exists('wpaic_structured_log')) {
			wpaic_structured_log($severity, $event_type, $entry, self::SUBSYSTEM);

			return;
		}

		// Fallback: error_log (never surfaces secrets — context is already scrubbed by caller).
		if (defined('WP_DEBUG') && WP_DEBUG) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(sprintf(
				'[WPAIC Audit][%s][%s] %s',
				strtoupper($severity),
				$event_type,
				(string) wp_json_encode($entry)
			));
		}
	}

	/**
	 * Query recent audit log entries (from structured log files).
	 *
	 * Reads the current day's log file and the previous day's if more entries
	 * are needed to satisfy $filters['limit'].
	 *
	 * @param  array $filters {
	 *     Optional filters.
	 *     @type string $event_type Filter to a single event type.
	 *     @type string $severity   Filter to a single severity level.
	 *     @type int    $limit      Max entries to return (default 100).
	 * }
	 * @return array Array of log entry arrays, newest first.
	 */
	public static function query(array $filters = []): array
	{
		$limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 100;
		$event_type = $filters['event_type'] ?? '';
		$severity = $filters['severity'] ?? '';

		$log_dir = defined('WPAIC_PLUGIN_DIR')
			? WPAIC_PLUGIN_DIR . 'logs/'
			: '';

		if ('' === $log_dir || ! is_dir($log_dir)) {
			return [];
		}

		// Collect candidate log files: today + yesterday.
		$files = [];
		for ($days_back = 0; $days_back <= 7; $days_back++) {
			$date = gmdate('Y-m-d', strtotime("-{$days_back} days"));
			$file = $log_dir . "app-{$date}.log";
			if (file_exists($file)) {
				$files[] = $file;
			}
		}

		$entries = [];

		foreach ($files as $file) {
			$lines = self::read_lines_reversed($file);

			foreach ($lines as $line) {
				$entry = json_decode(trim($line), true);
				if (! is_array($entry)) {
					continue;
				}

				// Filter by subsystem — only audit entries.
				if (! isset($entry['subsystem']) || self::SUBSYSTEM !== $entry['subsystem']) {
					continue;
				}

				// Optional filters.
				if ('' !== $event_type && ($entry['event_type'] ?? '') !== $event_type) {
					continue;
				}

				if ('' !== $severity) {
					$entry_level = strtolower($entry['level'] ?? '');
					if ($entry_level !== strtolower($severity)) {
						continue;
					}
				}

				$entries[] = $entry;

				if (count($entries) >= $limit) {
					break 2;
				}
			}
		}

		return $entries;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Read a file's lines in reverse order (newest entries first for log files).
	 *
	 * @param  string $filepath
	 * @return string[]
	 */
	private static function read_lines_reversed(string $filepath): array
	{
		$content = file_get_contents($filepath); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if (false === $content) {
			return [];
		}

		$lines = array_filter(explode("\n", $content));

		return array_reverse(array_values($lines));
	}
}
