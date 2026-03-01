<?php

/**
 * Livechat Manager — Live-agent handoff state machine.
 *
 * Manages the lifecycle of a visitor session transitioning from bot-handled
 * to human-agent-handled:
 *
 *   bot  →  pending_agent  →  agent_connected  →  closed
 *
 * State is stored in `{prefix}wpaic_livechat_sessions` (created via the
 * 1.1.0 migration). Provides request_handoff(), get_session_status(),
 * get_pending_sessions(), and update_status() operations.
 *
 * @package WP_AI_Chatbot
 * @since   1.1.0
 */

namespace WPAIC\Integrations;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Livechat_Manager
 *
 * @since   1.1.0
 * @package WPAIC\Integrations
 */
class Livechat_Manager
{
	// =========================================================================
	// Status constants
	// =========================================================================

	/** Conversation is handled by the bot (default). */
	public const STATUS_BOT = 'bot';
	/** Visitor has requested a human agent; waiting in queue. */
	public const STATUS_PENDING = 'pending_agent';
	/** A human agent has joined the conversation. */
	public const STATUS_CONNECTED = 'agent_connected';
	/** Session has been closed by an agent or automatically. */
	public const STATUS_CLOSED = 'closed';

	/** WP option key for livechat configuration. */
	public const SETTINGS_OPTION = 'wpaic_livechat_settings';

	// =========================================================================
	// Table helper
	// =========================================================================

	/**
	 * Return the fully-qualified livechat sessions table name.
	 *
	 * @return string
	 */
	public static function table(): string
	{
		global $wpdb;

		return $wpdb->prefix . 'wpaic_livechat_sessions';
	}

	// =========================================================================
	// Settings
	// =========================================================================

	/**
	 * Return livechat settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array
	{
		$defaults = [
			'enabled' => false,
			'agent_email' => '',
			'notify_on_request' => true,
			'queue_message' => '',
		];

		$settings = (array) wp_parse_args(
			(array) get_option(self::SETTINGS_OPTION, []),
			$defaults
		);

		// Provide translated default for queue_message if empty.
		if ('' === $settings['queue_message']) {
			$settings['queue_message'] = __('An agent will be with you shortly. Please hold on…', 'wp-ai-chatbot');
		}

		return $settings;
	}

	// =========================================================================
	// Handoff operations
	// =========================================================================

	/**
	 * Request a live-agent handoff for the given chat session.
	 *
	 * Creates a `pending_agent` record in the livechat sessions table and
	 * optionally sends an email notification to the configured agent address.
	 *
	 * If a record already exists for the session the current status is returned
	 * without creating a duplicate.
	 *
	 * @param  string $session_token Chat session token (UUID v4).
	 * @param  string $reason        Short reason for the handoff (free text).
	 * @param  array  $context       Execution context (bot_slug, page_url, user_id, …).
	 * @return array{ok: bool, status: string, message: string}
	 */
	public static function request_handoff(string $session_token, string $reason = '', array $context = []): array
	{
		global $wpdb;

		$settings = self::get_settings();

		if (empty($settings['enabled'])) {
			return [
				'ok' => false,
				'status' => self::STATUS_BOT,
				'message' => __('Live agent support is not currently available.', 'wp-ai-chatbot'),
			];
		}

		$table = self::table();

		// Check for an existing record to avoid duplicate insertions.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare("SELECT status FROM {$table} WHERE session_token = %s", $session_token),
			ARRAY_A
		);

		if (null !== $existing) {
			$current_status = (string) $existing['status'];

			return [
				'ok' => true,
				'status' => $current_status,
				'message' => self::get_status_message($current_status, $settings),
			];
		}

		$row = [
			'session_token' => $session_token,
			'status' => self::STATUS_PENDING,
			'handoff_reason' => sanitize_textarea_field($reason),
			'bot_slug' => sanitize_key($context['bot_slug'] ?? ''),
			'page_url' => esc_url_raw($context['page_url'] ?? ''),
			'requested_at' => current_time('mysql'),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert($table, $row);

		if (false === $inserted) {
			return [
				'ok' => false,
				'status' => self::STATUS_BOT,
				'message' => __('Could not queue your request. Please try again.', 'wp-ai-chatbot'),
			];
		}

		/**
		 * Fires when a live-agent handoff has been successfully requested.
		 *
		 * @since 1.1.0
		 * @param string $session_token Chat session token.
		 * @param string $reason        Reason provided by the visitor.
		 * @param array  $context       Execution context.
		 */
		do_action('wpaic_livechat_handoff_requested', $session_token, $reason, $context);

		// Notify the configured agent email.
		if (! empty($settings['notify_on_request'])) {
			$to = ! empty($settings['agent_email']) ? $settings['agent_email'] : get_option('admin_email');
			if ($to) {
				self::send_handoff_notification((string) $to, $session_token, $reason, $context);
			}
		}

		return [
			'ok' => true,
			'status' => self::STATUS_PENDING,
			'message' => (string) $settings['queue_message'],
		];
	}

	/**
	 * Return the current livechat status for a session.
	 *
	 * Returns STATUS_BOT when no livechat record exists (default state).
	 *
	 * @param  string $session_token Chat session token.
	 * @return string One of the STATUS_* constants.
	 */
	public static function get_session_status(string $session_token): string
	{
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare("SELECT status FROM {$table} WHERE session_token = %s", $session_token),
			ARRAY_A
		);

		return null !== $row ? (string) $row['status'] : self::STATUS_BOT;
	}

	/**
	 * Retrieve all sessions currently waiting for an agent (pending_agent status).
	 *
	 * Used by the admin queue view in the Chat Behavior page.
	 *
	 * @return array<int, array<string, string>> Rows from the livechat_sessions table.
	 */
	public static function get_pending_sessions(): array
	{
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY requested_at ASC",
				self::STATUS_PENDING
			),
			ARRAY_A
		);
	}

	/**
	 * Update the status of a livechat session.
	 *
	 * Called by agents (or admin UI) when connecting or closing a session.
	 *
	 * @param  string $session_token Chat session token.
	 * @param  string $status        New status (one of the STATUS_* constants).
	 * @param  int    $agent_id      WordPress user ID of the agent (for STATUS_CONNECTED).
	 * @return bool Whether the update succeeded.
	 */
	public static function update_status(string $session_token, string $status, int $agent_id = 0): bool
	{
		global $wpdb;

		$valid = [self::STATUS_BOT, self::STATUS_PENDING, self::STATUS_CONNECTED, self::STATUS_CLOSED];

		if (! in_array($status, $valid, true)) {
			return false;
		}

		$data = ['status' => $status];

		if (self::STATUS_CONNECTED === $status && $agent_id > 0) {
			$data['agent_id'] = $agent_id;
			$data['connected_at'] = current_time('mysql');
		}

		if (self::STATUS_CLOSED === $status) {
			$data['closed_at'] = current_time('mysql');
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			self::table(),
			$data,
			['session_token' => $session_token]
		);

		return false !== $result;
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Return a human-readable status message for a given status code.
	 *
	 * @param  string $status   One of the STATUS_* constants.
	 * @param  array  $settings Livechat settings array.
	 * @return string
	 */
	private static function get_status_message(string $status, array $settings): string
	{
		switch ($status) {
			case self::STATUS_PENDING:
				return (string) $settings['queue_message'];
			case self::STATUS_CONNECTED:
				return __('You are now connected with a live agent.', 'wp-ai-chatbot');
			case self::STATUS_CLOSED:
				return __('This live chat session has been closed.', 'wp-ai-chatbot');
			default:
				return '';
		}
	}

	/**
	 * Send an email notification to the agent when a handoff is requested.
	 *
	 * @param  string $to            Recipient email.
	 * @param  string $session_token Chat session token.
	 * @param  string $reason        Reason provided by the visitor.
	 * @param  array  $context       Execution context.
	 * @return void
	 */
	private static function send_handoff_notification(
		string $to,
		string $session_token,
		string $reason,
		array $context
	): void {
		$subject = sprintf(
			/* translators: %s: site name */
			__('[%s] Live Agent Requested', 'wp-ai-chatbot'),
			get_bloginfo('name')
		);

		$message = sprintf(
			/* translators: 1: session token, 2: reason, 3: bot slug, 4: page URL, 5: datetime */
			__(
				"A visitor has requested a live agent.\n\nSession: %1\$s\nReason: %2\$s\nBot: %3\$s\nPage: %4\$s\nTime: %5\$s",
				'wp-ai-chatbot'
			),
			$session_token,
			$reason ?: '—',
			$context['bot_slug'] ?? 'default',
			$context['page_url'] ?? '—',
			current_time('mysql')
		);

		wp_mail($to, $subject, $message);
	}
}
