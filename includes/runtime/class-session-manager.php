<?php

/**
 * Session Manager — create, resume, close, expire, and cleanup chat sessions.
 *
 * Session lifecycle:
 *   active   → normal in-progress session (TTL 24 h, configurable)
 *   closed   → explicitly ended by user or operator
 *   expired  → TTL exceeded, soft-deleted by cleanup cron
 *
 * Ownership validation:
 *   authenticated   : (session_token, user_id)
 *   anonymous       : (session_token, ip_hash, ua_hash)
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Runtime;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Session_Manager
 *
 * Handles all session CRUD operations and cron cleanup.
 *
 * @since 1.0.0
 * @package WPAIC\Runtime
 */
class Session_Manager
{
	/** Session status constants. */
	public const STATUS_ACTIVE = 'active';
	public const STATUS_CLOSED = 'closed';
	public const STATUS_EXPIRED = 'expired';

	/** Default session TTL in seconds (24 hours). */
	public const DEFAULT_TTL = 86400;

	/** Maximum messages returned by get_history(). */
	public const MAX_HISTORY_DEPTH = 50;

	// =========================================================================
	// Session CRUD
	// =========================================================================

	/**
	 * Create a new active session.
	 *
	 * @param  array $args {
	 *     @type int    $bot_id       Required. Bot configuration ID.
	 *     @type int    $user_id      Optional. WordPress user ID (0 for anonymous).
	 *     @type string $ip_hash      Optional. SHA-256 hash of visitor IP.
	 *     @type string $ua_hash      Optional. SHA-256 hash of user agent.
	 *     @type string $locale       Optional. Locale string (e.g. 'en-US').
	 *     @type array  $context_data Optional. Arbitrary key-value context.
	 * }
	 * @return array|WP_Error Session row array or WP_Error on failure.
	 */
	public static function create(array $args)
	{
		global $wpdb;

		$bot_id = isset($args['bot_id']) ? absint($args['bot_id']) : 1;
		$user_id = isset($args['user_id']) ? absint($args['user_id']) : 0;
		$ip_hash = isset($args['ip_hash']) ? sanitize_text_field($args['ip_hash']) : '';
		$ua_hash = isset($args['ua_hash']) ? sanitize_text_field($args['ua_hash']) : '';
		$locale = isset($args['locale']) ? sanitize_text_field($args['locale']) : 'en';
		$context = isset($args['context_data']) && is_array($args['context_data'])
			? $args['context_data']
			: [];

		$token = wp_generate_uuid4();
		$ttl = (int) get_option('wpaic_runtime_session_ttl', self::DEFAULT_TTL);
		$now = current_time('mysql', true); // GMT
		$expires = gmdate('Y-m-d H:i:s', time() + $ttl);

		$table = $wpdb->prefix . 'wpaic_sessions';
		$result = $wpdb->insert(
			$table,
			[
				'session_token' => $token,
				'bot_id' => $bot_id,
				'user_id' => $user_id,
				'ip_hash' => $ip_hash,
				'ua_hash' => $ua_hash,
				'locale' => $locale,
				'context_data' => wp_json_encode($context),
				'status' => self::STATUS_ACTIVE,
				'created_at' => $now,
				'last_active_at' => $now,
				'expires_at' => $expires,
				'message_count' => 0,
				'total_tokens' => 0,
				'total_cost_micro' => 0,
			],
			[
				'%s', // session_token
				'%d', // bot_id
				'%d', // user_id
				'%s', // ip_hash
				'%s', // ua_hash
				'%s', // locale
				'%s', // context_data
				'%s', // status
				'%s', // created_at
				'%s', // last_active_at
				'%s', // expires_at
				'%d', // message_count
				'%d', // total_tokens
				'%d', // total_cost_micro
			]
		);

		if (false === $result) {
			return new \WP_Error(
				'wpaic_session_create_failed',
				__('Failed to create chat session.', 'wp-ai-chatbot')
			);
		}

		$session_id = (int) $wpdb->insert_id;
		$session = self::get_by_id($session_id);

		if (! $session) {
			return new \WP_Error(
				'wpaic_session_read_failed',
				__('Session created but could not be retrieved.', 'wp-ai-chatbot')
			);
		}

		/**
		 * Fires after a new session is successfully created.
		 *
		 * @since 1.0.0
		 * @param array $session Session row data.
		 */
		do_action('wpaic_session_created', $session);

		return $session;
	}

	/**
	 * Resume an existing active session after validating ownership.
	 *
	 * Extends the TTL on every successful resume (rolling window).
	 *
	 * @param  string $token   Session UUID token.
	 * @param  array  $context {
	 *     Ownership context.
	 *     @type int    $user_id  WordPress user ID (0 for anonymous).
	 *     @type string $ip_hash  SHA-256 hash of visitor IP.
	 *     @type string $ua_hash  SHA-256 hash of user agent.
	 * }
	 * @return array|WP_Error Session row or WP_Error if invalid/expired/unauthorized.
	 */
	public static function resume(string $token, array $context = [])
	{
		global $wpdb;

		$token = sanitize_text_field($token);

		if (! self::is_valid_uuid($token)) {
			return new \WP_Error('wpaic_invalid_token', __('Invalid session token format.', 'wp-ai-chatbot'));
		}

		$table = $wpdb->prefix . 'wpaic_sessions';
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_token = %s LIMIT 1",
				$token
			),
			ARRAY_A
		);

		if (! $session) {
			return new \WP_Error('wpaic_session_not_found', __('Session not found.', 'wp-ai-chatbot'));
		}

		// Reject non-active sessions.
		if (self::STATUS_ACTIVE !== $session['status']) {
			return new \WP_Error(
				'wpaic_session_inactive',
				__('Session has ended or expired.', 'wp-ai-chatbot')
			);
		}

		// Reject hard-expired sessions.
		if (! empty($session['expires_at']) && strtotime($session['expires_at']) < time()) {
			self::mark_expired($session['id']);

			return new \WP_Error('wpaic_session_expired', __('Session has expired.', 'wp-ai-chatbot'));
		}

		// Validate ownership.
		if (! self::validate_ownership($session, $context)) {
			return new \WP_Error('wpaic_session_ownership', __('Session ownership validation failed.', 'wp-ai-chatbot'));
		}

		// Extend TTL on resume (rolling window).
		$ttl = (int) get_option('wpaic_runtime_session_ttl', self::DEFAULT_TTL);
		$expires = gmdate('Y-m-d H:i:s', time() + $ttl);
		$now = current_time('mysql', true);

		$wpdb->update(
			$table,
			[
				'last_active_at' => $now,
				'expires_at' => $expires,
			],
			['id' => (int) $session['id']],
			['%s', '%s'],
			['%d']
		);

		$session['last_active_at'] = $now;
		$session['expires_at'] = $expires;

		/**
		 * Fires after a session is successfully resumed.
		 *
		 * @since 1.0.0
		 * @param array $session Session row data.
		 */
		do_action('wpaic_session_resumed', $session);

		return $session;
	}

	/**
	 * Close a session (explicit user/operator action).
	 *
	 * @param  int   $session_id DB row ID.
	 * @param  array $context    Ownership context for security check.
	 * @return bool
	 */
	public static function close(int $session_id, array $context = []): bool
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_sessions';
		$session = self::get_by_id($session_id);

		if (! $session) {
			return false;
		}

		if (! self::validate_ownership($session, $context)) {
			return false;
		}

		$result = (bool) $wpdb->update(
			$table,
			['status' => self::STATUS_CLOSED],
			['id' => $session_id],
			['%s'],
			['%d']
		);

		if ($result) {
			/**
			 * Fires after a session is explicitly closed.
			 *
			 * @since 1.0.0
			 * @param array $session Session row data.
			 */
			do_action('wpaic_session_closed', $session);
		}

		return $result;
	}

	/**
	 * Retrieve message history for a session.
	 *
	 * @param  int   $session_id DB row ID.
	 * @param  int   $limit      Max messages to return (default self::MAX_HISTORY_DEPTH).
	 * @param  array $context    Ownership context for security check.
	 * @return array[]|WP_Error  Array of message rows or WP_Error.
	 */
	public static function get_history(int $session_id, int $limit = self::MAX_HISTORY_DEPTH, array $context = [])
	{
		global $wpdb;

		$session = self::get_by_id($session_id);

		if (! $session) {
			return new \WP_Error('wpaic_session_not_found', __('Session not found.', 'wp-ai-chatbot'));
		}

		if (! self::validate_ownership($session, $context)) {
			return new \WP_Error('wpaic_session_ownership', __('Access denied.', 'wp-ai-chatbot'));
		}

		$limit = min(max(1, $limit), self::MAX_HISTORY_DEPTH);
		$table = $wpdb->prefix . 'wpaic_messages';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_id = %d ORDER BY created_at ASC LIMIT %d",
				$session_id,
				$limit
			),
			ARRAY_A
		);

		if (! is_array($rows)) {
			$rows = [];
		}

		/**
		 * Filter session history before returning to the pipeline.
		 *
		 * @since 1.0.0
		 * @param array[] $rows       Message rows.
		 * @param int     $session_id Session ID.
		 */
		return apply_filters('wpaic_history_messages', $rows, $session_id);
	}

	/**
	 * Log a single message pair (user + assistant) to the messages table.
	 *
	 * @param  int    $session_id      DB row ID.
	 * @param  string $user_message    Sanitized user message.
	 * @param  string $bot_response    Sanitized bot response HTML.
	 * @param  array  $usage           Token usage array from provider.
	 * @param  int    $cost_micro      Cost in microcents.
	 * @param  string $provider_id     Provider canonical ID.
	 * @param  string $model_id        Model used.
	 * @param  array  $rag_context_ids Optional array of knowledge chunk IDs used.
	 * @param  array  $tool_calls      Optional tool call history from Tool_Continuation::run().
	 * @return int|false               Inserted row ID or false on failure.
	 */
	public static function log_message(
		int $session_id,
		string $user_message,
		string $bot_response,
		array $usage = [],
		int $cost_micro = 0,
		string $provider_id = '',
		string $model_id = '',
		array $rag_context_ids = [],
		array $tool_calls = []
	) {
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_messages';

		$row = [
			'session_id' => $session_id,
			'role_user' => $user_message,
			'role_assistant' => $bot_response,
			'prompt_tokens' => isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : 0,
			'completion_tokens' => isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : 0,
			'total_tokens' => isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : 0,
			'cost_micro' => $cost_micro,
			'provider_id' => sanitize_key($provider_id),
			'model_id' => sanitize_text_field($model_id),
			'rag_context_ids' => wp_json_encode($rag_context_ids),
			'created_at' => current_time('mysql', true),
		];
		$formats = ['%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s'];

		if (! empty($tool_calls)) {
			$row['tool_calls'] = wp_json_encode($tool_calls);
			$formats[] = '%s';
		}

		$result = $wpdb->insert($table, $row, $formats);

		if (false === $result) {
			return false;
		}

		$msg_id = (int) $wpdb->insert_id;

		// Update session aggregates atomically.
		$sessions_table = $wpdb->prefix . 'wpaic_sessions';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$sessions_table}
				 SET message_count    = message_count + 1,
				     total_tokens     = total_tokens + %d,
				     total_cost_micro = total_cost_micro + %d,
				     last_active_at   = %s
				 WHERE id = %d",
				isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : 0,
				$cost_micro,
				current_time('mysql', true),
				$session_id
			)
		);

		return $msg_id;
	}

	// =========================================================================
	// Cron cleanup
	// =========================================================================

	/**
	 * Mark all expired sessions and optionally delete messages past retention.
	 *
	 * Called by `wpaic_cleanup_sessions` cron event (hourly).
	 *
	 * @return void
	 */
	public static function cleanup_expired(): void
	{
		global $wpdb;

		$now = current_time('mysql', true);
		$sessions_tbl = $wpdb->prefix . 'wpaic_sessions';

		// Mark active sessions whose TTL has elapsed as expired.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$sessions_tbl}
				 SET status = %s
				 WHERE status = %s AND expires_at < %s",
				self::STATUS_EXPIRED,
				self::STATUS_ACTIVE,
				$now
			)
		);

		// Hard-delete messages older than retention window.
		$retention_days = (int) get_option('wpaic_admin_data_retention_days', 90);
		if ($retention_days > 0) {
			$cutoff = gmdate('Y-m-d H:i:s', time() - ($retention_days * DAY_IN_SECONDS));
			$messages_tbl = $wpdb->prefix . 'wpaic_messages';

			// Delete messages linked to sessions older than retention.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE m FROM {$messages_tbl} m
					 INNER JOIN {$sessions_tbl} s ON m.session_id = s.id
					 WHERE s.created_at < %s",
					$cutoff
				)
			);

			// Delete sessions older than retention.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$sessions_tbl} WHERE created_at < %s",
					$cutoff
				)
			);
		}

		/**
		 * Fires after expired session cleanup completes.
		 *
		 * @since 1.0.0
		 */
		do_action('wpaic_sessions_cleaned');
	}

	// =========================================================================
	// REST endpoint helpers
	// =========================================================================

	/**
	 * Register REST routes for session CRUD.
	 *
	 * Called from `wpaic_register_rest_routes` action.
	 *
	 * @return void
	 */
	public static function register_rest_routes(): void
	{
		// POST /sessions — create.
		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/sessions',
			[
				'methods' => 'POST',
				'callback' => [self::class, 'rest_create'],
				'permission_callback' => '__return_true', // anonymous ok
			]
		);

		// GET /sessions/{token} — retrieve.
		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/sessions/(?P<token>[0-9a-f\-]{36})',
			[
				'methods' => 'GET',
				'callback' => [self::class, 'rest_get'],
				'permission_callback' => '__return_true',
				'args' => [
					'token' => [
						'required' => true,
						'validate_callback' => [self::class, 'is_valid_uuid'],
					],
				],
			]
		);

		// DELETE /sessions/{token} — close.
		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/sessions/(?P<token>[0-9a-f\-]{36})',
			[
				'methods' => 'DELETE',
				'callback' => [self::class, 'rest_close'],
				'permission_callback' => '__return_true',
				'args' => [
					'token' => [
						'required' => true,
						'validate_callback' => [self::class, 'is_valid_uuid'],
					],
				],
			]
		);

		// GET /sessions/{token}/history — message history.
		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/sessions/(?P<token>[0-9a-f\-]{36})/history',
			[
				'methods' => 'GET',
				'callback' => [self::class, 'rest_history'],
				'permission_callback' => '__return_true',
				'args' => [
					'token' => [
						'required' => true,
						'validate_callback' => [self::class, 'is_valid_uuid'],
					],
					'limit' => [
						'required' => false,
						'type' => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => self::MAX_HISTORY_DEPTH,
					],
				],
			]
		);
	}

	/**
	 * REST callback: create a session.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function rest_create(\WP_REST_Request $request): \WP_REST_Response
	{
		$context = self::build_request_context($request);
		$session = self::create([
			'bot_id' => (int) ($request->get_param('bot_id') ?: 1),
			'user_id' => $context['user_id'],
			'ip_hash' => $context['ip_hash'],
			'ua_hash' => $context['ua_hash'],
			'locale' => substr(sanitize_text_field((string) ($request->get_param('locale') ?: get_locale())), 0, 10),
		]);

		if (is_wp_error($session)) {
			return new \WP_REST_Response(['error' => $session->get_error_message()], 500);
		}

		return new \WP_REST_Response([
			'token' => $session['session_token'],
			'expires_at' => $session['expires_at'],
			'status' => $session['status'],
		], 201);
	}

	/**
	 * REST callback: get session info.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function rest_get(\WP_REST_Request $request): \WP_REST_Response
	{
		$context = self::build_request_context($request);
		$session = self::resume((string) $request->get_param('token'), $context);

		if (is_wp_error($session)) {
			$code = 'wpaic_session_not_found' === $session->get_error_code() ? 404 : 403;

			return new \WP_REST_Response(['error' => $session->get_error_message()], $code);
		}

		return new \WP_REST_Response([
			'token' => $session['session_token'],
			'status' => $session['status'],
			'expires_at' => $session['expires_at'],
			'message_count' => (int) $session['message_count'],
		], 200);
	}

	/**
	 * REST callback: close session.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function rest_close(\WP_REST_Request $request): \WP_REST_Response
	{
		$token = sanitize_text_field((string) $request->get_param('token'));
		$context = self::build_request_context($request);

		// Find session by token.
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_sessions';
		$session = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table} WHERE session_token = %s LIMIT 1", $token),
			ARRAY_A
		);

		if (! $session) {
			return new \WP_REST_Response(['error' => __('Session not found.', 'wp-ai-chatbot')], 404);
		}

		$closed = self::close((int) $session['id'], $context);

		if (! $closed) {
			return new \WP_REST_Response(['error' => __('Could not close session.', 'wp-ai-chatbot')], 403);
		}

		return new \WP_REST_Response(['success' => true], 200);
	}

	/**
	 * REST callback: session message history.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public static function rest_history(\WP_REST_Request $request): \WP_REST_Response
	{
		$token = sanitize_text_field((string) $request->get_param('token'));
		$limit = (int) ($request->get_param('limit') ?: 20);
		$context = self::build_request_context($request);

		// Find session by token.
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_sessions';
		$session = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table} WHERE session_token = %s LIMIT 1", $token),
			ARRAY_A
		);

		if (! $session) {
			return new \WP_REST_Response(['error' => __('Session not found.', 'wp-ai-chatbot')], 404);
		}

		$history = self::get_history((int) $session['id'], $limit, $context);

		if (is_wp_error($history)) {
			return new \WP_REST_Response(['error' => $history->get_error_message()], 403);
		}

		// Return safe subset of fields.
		$messages = array_map(static function (array $row): array {
			return [
				'user' => $row['role_user'],
				'assistant' => $row['role_assistant'],
				'created' => $row['created_at'],
			];
		}, $history);

		return new \WP_REST_Response(['messages' => $messages], 200);
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Retrieve a session row by primary key.
	 *
	 * @param  int $id Session row ID.
	 * @return array|null
	 */
	public static function get_by_id(int $id): ?array
	{
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_sessions';
		$result = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id),
			ARRAY_A
		);

		return is_array($result) ? $result : null;
	}

	/**
	 * Mark a session as expired.
	 *
	 * @param  int $session_id DB row ID.
	 * @return void
	 */
	private static function mark_expired(int $session_id): void
	{
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'wpaic_sessions',
			['status' => self::STATUS_EXPIRED],
			['id' => $session_id],
			['%s'],
			['%d']
		);

		/**
		 * Fires when a session expires.
		 *
		 * @since 1.0.0
		 * @param int $session_id Session row ID.
		 */
		do_action('wpaic_session_expired', $session_id);
	}

	/**
	 * Validate session ownership.
	 *
	 * Authenticated sessions require matching user_id.
	 * Anonymous sessions require matching ip_hash OR ua_hash (either is sufficient
	 * to handle mobile users behind rotating NAT, where one may change).
	 *
	 * @param  array $session Session row.
	 * @param  array $context {
	 *     @type int    $user_id
	 *     @type string $ip_hash
	 *     @type string $ua_hash
	 * }
	 * @return bool
	 */
	public static function validate_ownership(array $session, array $context): bool
	{
		$ctx_user = isset($context['user_id']) ? (int) $context['user_id'] : 0;
		$ctx_ip = isset($context['ip_hash']) ? $context['ip_hash'] : '';
		$ctx_ua = isset($context['ua_hash']) ? $context['ua_hash'] : '';

		// Authenticated session: must match user_id.
		if ((int) $session['user_id'] > 0) {
			return (int) $session['user_id'] === $ctx_user;
		}

		// Anonymous session: either IP hash or UA hash must match.
		$ip_ok = ! empty($session['ip_hash']) && ! empty($ctx_ip)
			&& hash_equals($session['ip_hash'], $ctx_ip);
		$ua_ok = ! empty($session['ua_hash']) && ! empty($ctx_ua)
			&& hash_equals($session['ua_hash'], $ctx_ua);

		/**
		 * Filter session ownership check result.
		 *
		 * @since 1.0.0
		 * @param bool  $valid   Whether ownership validated.
		 * @param array $session Session row.
		 * @param array $context Request context.
		 */
		return (bool) apply_filters('wpaic_session_ownership_valid', ($ip_ok || $ua_ok), $session, $context);
	}

	/**
	 * Build ownership context from a REST request.
	 *
	 * @param  \WP_REST_Request $request
	 * @return array {user_id, ip_hash, ua_hash}
	 */
	private static function build_request_context(\WP_REST_Request $request): array
	{
		$user_id = get_current_user_id();
		$ip = self::get_client_ip();
		$ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

		return [
			'user_id' => $user_id,
			'ip_hash' => hash('sha256', $ip),
			'ua_hash' => hash('sha256', $ua),
		];
	}

	/**
	 * Build ownership context from current AJAX request globals.
	 *
	 * @return array {user_id, ip_hash, ua_hash}
	 */
	public static function build_ajax_context(): array
	{
		$user_id = get_current_user_id();
		$ip = self::get_client_ip();
		$ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';

		return [
			'user_id' => $user_id,
			'ip_hash' => hash('sha256', $ip),
			'ua_hash' => hash('sha256', $ua),
		];
	}

	/**
	 * Extract client IP, honouring trusted reverse-proxy headers.
	 *
	 * @return string IP address string.
	 */
	public static function get_client_ip(): string
	{
		$candidates = [
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Load balancers
			'REMOTE_ADDR',
		];

		foreach ($candidates as $key) {
			if (! empty($_SERVER[$key])) {
				$raw = sanitize_text_field(wp_unslash($_SERVER[$key]));
				// X-Forwarded-For may be a comma list; take the leftmost.
				$ip = trim(explode(',', $raw)[0]);
				if (filter_var($ip, FILTER_VALIDATE_IP)) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Validate a UUID v4 string.
	 *
	 * @param  mixed $token Value to check.
	 * @return bool
	 */
	public static function is_valid_uuid($token): bool
	{
		return is_string($token) && (bool) preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
			$token
		);
	}
}
