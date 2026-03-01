<?php

/**
 * Privacy Manager — WP personal data export and erasure hooks (GDPR).
 *
 * Registers WordPress's built-in personal data exporters and erasers for:
 *  - Chat sessions (authenticated users only, matched by user_id)
 *  - Lead capture records (matched by email address)
 *
 * Also provides apply_retention_policy() for use in cron cleanup tasks.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Security;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Privacy_Manager
 *
 * Implements GDPR personal data export and erasure per WP core privacy API.
 *
 * @since 1.0.0
 * @package WPAIC\Security
 */
class Privacy_Manager
{
	/** Exporter slug for chat sessions. */
	public const EXPORTER_SESSIONS = 'wpaic-sessions';

	/** Exporter slug for lead data. */
	public const EXPORTER_LEADS = 'wpaic-leads';

	/** Eraser slug for chat sessions. */
	public const ERASER_SESSIONS = 'wpaic-sessions';

	/** Eraser slug for lead data. */
	public const ERASER_LEADS = 'wpaic-leads';

	/** @var int Number of records processed per paginated privacy request pass. */
	public const PAGE_SIZE = 50;

	// =========================================================================
	// Hook registration
	// =========================================================================

	/**
	 * Register WP personal data exporters and erasers.
	 * Call once from Plugin::register_hooks() on init.
	 *
	 * @return void
	 */
	public static function register_hooks(): void
	{
		add_filter('wp_privacy_personal_data_exporters', [static::class, 'register_exporters'], 10, 1);
		add_filter('wp_privacy_personal_data_erasers', [static::class, 'register_erasers'], 10, 1);
	}

	/**
	 * Register personal data exporters.
	 *
	 * @param  array $exporters Existing exporters.
	 * @return array Modified exporters.
	 */
	public static function register_exporters(array $exporters): array
	{
		$exporters[self::EXPORTER_SESSIONS] = [
			'exporter_friendly_name' => __('WP AI Chatbot — Chat Sessions', 'wp-ai-chatbot'),
			'callback' => [static::class, 'export_sessions'],
		];

		$exporters[self::EXPORTER_LEADS] = [
			'exporter_friendly_name' => __('WP AI Chatbot — Lead Data', 'wp-ai-chatbot'),
			'callback' => [static::class, 'export_leads'],
		];

		return $exporters;
	}

	/**
	 * Register personal data erasers.
	 *
	 * @param  array $erasers Existing erasers.
	 * @return array Modified erasers.
	 */
	public static function register_erasers(array $erasers): array
	{
		$erasers[self::ERASER_SESSIONS] = [
			'eraser_friendly_name' => __('WP AI Chatbot — Chat Sessions', 'wp-ai-chatbot'),
			'callback' => [static::class, 'erase_sessions'],
		];

		$erasers[self::ERASER_LEADS] = [
			'eraser_friendly_name' => __('WP AI Chatbot — Lead Data', 'wp-ai-chatbot'),
			'callback' => [static::class, 'erase_leads'],
		];

		return $erasers;
	}

	// =========================================================================
	// Data exporters (paginated)
	// =========================================================================

	/**
	 * Export chat sessions for a given email address.
	 *
	 * Only exports sessions that belong to an authenticated WP user whose
	 * registered email matches. Anonymous sessions (ip_hash only) are not
	 * personally linkable and are therefore not exported.
	 *
	 * @param  string $email Requester's email.
	 * @param  int    $page  Pagination page number (1-based).
	 * @return array{data: array, done: bool}
	 */
	public static function export_sessions(string $email, int $page = 1): array
	{
		global $wpdb;

		$empty = ['data' => [], 'done' => true];

		$user = get_user_by('email', $email);
		if (! $user instanceof \WP_User) {
			return $empty;
		}

		$table = $wpdb->prefix . 'wpaic_sessions';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
			return $empty;
		}

		$offset = (max(1, $page) - 1) * self::PAGE_SIZE;

		$sessions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_token, bot_slug, created_at, last_activity, message_count
				 FROM {$table}
				 WHERE user_id = %d
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d",
				$user->ID,
				self::PAGE_SIZE,
				$offset
			),
			ARRAY_A
		);

		if (! is_array($sessions)) {
			return $empty;
		}

		$data = [];
		foreach ($sessions as $session) {
			$data[] = [
				'group_id' => 'wpaic-sessions',
				'group_label' => __('Chat Sessions', 'wp-ai-chatbot'),
				'item_id' => 'session-' . sanitize_key($session['session_token']),
				'data' => [
					['name' => __('Bot', 'wp-ai-chatbot'), 'value' => esc_html((string) $session['bot_slug'])],
					['name' => __('Messages', 'wp-ai-chatbot'), 'value' => absint($session['message_count'])],
					['name' => __('Started', 'wp-ai-chatbot'), 'value' => esc_html((string) $session['created_at'])],
					['name' => __('Last Active', 'wp-ai-chatbot'), 'value' => esc_html((string) $session['last_activity'])],
				],
			];
		}

		return ['data' => $data, 'done' => count($sessions) < self::PAGE_SIZE];
	}

	/**
	 * Export lead capture records for a given email address.
	 *
	 * @param  string $email Requester's email.
	 * @param  int    $page  Pagination page number (1-based).
	 * @return array{data: array, done: bool}
	 */
	public static function export_leads(string $email, int $page = 1): array
	{
		global $wpdb;

		$empty = ['data' => [], 'done' => true];
		$table = $wpdb->prefix . 'wpaic_leads';

		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
			return $empty;
		}

		$offset = (max(1, $page) - 1) * self::PAGE_SIZE;

		$leads = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name, phone, company, interest, status, created_at
				 FROM {$table}
				 WHERE email = %s
				 ORDER BY created_at DESC
				 LIMIT %d OFFSET %d",
				$email,
				self::PAGE_SIZE,
				$offset
			),
			ARRAY_A
		);

		if (! is_array($leads)) {
			return $empty;
		}

		$data = [];
		foreach ($leads as $lead) {
			$data[] = [
				'group_id' => 'wpaic-leads',
				'group_label' => __('Lead Capture Data', 'wp-ai-chatbot'),
				'item_id' => 'lead-' . absint($lead['id']),
				'data' => [
					['name' => __('Name', 'wp-ai-chatbot'), 'value' => esc_html((string) $lead['name'])],
					['name' => __('Phone', 'wp-ai-chatbot'), 'value' => esc_html((string) $lead['phone'])],
					['name' => __('Company', 'wp-ai-chatbot'), 'value' => esc_html((string) $lead['company'])],
					['name' => __('Interest', 'wp-ai-chatbot'), 'value' => esc_html((string) $lead['interest'])],
					['name' => __('Status', 'wp-ai-chatbot'), 'value' => esc_html((string) $lead['status'])],
					['name' => __('Date', 'wp-ai-chatbot'), 'value' => esc_html((string) $lead['created_at'])],
				],
			];
		}

		return ['data' => $data, 'done' => count($leads) < self::PAGE_SIZE];
	}

	// =========================================================================
	// Data erasers (paginated)
	// =========================================================================

	/**
	 * Erase chat sessions (and associated messages) for a given email.
	 *
	 * Removes session rows and corresponding messages for the authenticated WP
	 * user matched by email. Processes PAGE_SIZE sessions per invocation.
	 *
	 * @param  string $email Requester's email.
	 * @param  int    $page  Pagination page number (1-based).
	 * @return array{items_removed: bool, items_retained: bool, messages: array, done: bool}
	 */
	public static function erase_sessions(string $email, int $page = 1): array
	{
		global $wpdb;

		$not_found = [
			'items_removed' => false,
			'items_retained' => false,
			'messages' => [],
			'done' => true,
		];

		$user = get_user_by('email', $email);
		if (! $user instanceof \WP_User) {
			return $not_found;
		}

		$sessions_table = $wpdb->prefix . 'wpaic_sessions';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sessions_table)) !== $sessions_table) {
			return $not_found;
		}

		// Fetch a page of session tokens to delete.
		$tokens = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT session_token FROM {$sessions_table}
				 WHERE user_id = %d LIMIT %d",
				$user->ID,
				self::PAGE_SIZE
			)
		);

		if (empty($tokens)) {
			return $not_found;
		}

		// Delete messages for these sessions first.
		$messages_table = $wpdb->prefix . 'wpaic_messages';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $messages_table)) === $messages_table) {
			$placeholders = implode(',', array_fill(0, count($tokens), '%s'));
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query($wpdb->prepare("DELETE FROM {$messages_table} WHERE session_token IN ({$placeholders})", $tokens));
		}

		// Delete the session rows.
		$placeholders = implode(',', array_fill(0, count($tokens), '%s'));
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query($wpdb->prepare("DELETE FROM {$sessions_table} WHERE session_token IN ({$placeholders})", $tokens));

		$done = count($tokens) < self::PAGE_SIZE;

		return [
			'items_removed' => true,
			'items_retained' => false,
			'messages' => [],
			'done' => $done,
		];
	}

	/**
	 * Erase lead capture records for a given email.
	 *
	 * @param  string $email Requester's email.
	 * @param  int    $page  Pagination page number (unused; all rows for email deleted at once up to PAGE_SIZE).
	 * @return array{items_removed: bool, items_retained: bool, messages: array, done: bool}
	 */
	public static function erase_leads(string $email, int $page = 1): array
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_leads';

		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
			return ['items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true];
		}

		$deleted = (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE email = %s LIMIT %d",
				$email,
				self::PAGE_SIZE
			)
		);

		return [
			'items_removed' => $deleted > 0,
			'items_retained' => false,
			'messages' => [],
			'done' => $deleted < self::PAGE_SIZE,
		];
	}

	// =========================================================================
	// Retention policy
	// =========================================================================

	/**
	 * Delete sessions (and their messages) older than the configured retention period.
	 *
	 * Reads `wpaic_data_retention_days` option (default 90). A value of 0 means
	 * retain indefinitely. Called from on_daily_cleanup() cron event.
	 *
	 * @return void
	 */
	public static function apply_retention_policy(): void
	{
		global $wpdb;

		$retention_days = (int) get_option('wpaic_data_retention_days', 90);
		if ($retention_days <= 0) {
			return;
		}

		$cutoff = gmdate('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS));

		$sessions_table = $wpdb->prefix . 'wpaic_sessions';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sessions_table)) !== $sessions_table) {
			return;
		}

		// Remove messages belonging to expired sessions.
		$messages_table = $wpdb->prefix . 'wpaic_messages';
		if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $messages_table)) === $messages_table) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE m FROM {$messages_table} m
					 INNER JOIN {$sessions_table} s ON m.session_token = s.session_token
					 WHERE s.created_at < %s",
					$cutoff
				)
			);
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sessions_table} WHERE created_at < %s",
				$cutoff
			)
		);
	}
}
