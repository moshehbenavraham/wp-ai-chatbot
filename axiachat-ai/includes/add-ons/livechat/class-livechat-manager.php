<?php

/**
 * LiveChat Manager - Lógica de negocio central
 *
 * Gestiona estados de sesión y mensajes entre visitante y agente.
 *
 * @package AIChat
 * @subpackage LiveChat
 * @since 2.5.0
 */

if (! defined('ABSPATH')) {
	exit;
}

class AIChat_LiveChat_Manager
{
	// Estados posibles
	public const STATUS_BOT = 'bot';              // Hablando con bot
	public const STATUS_PENDING = 'pending_agent';    // Esperando agente
	public const STATUS_CONNECTED = 'agent_connected';  // Conectado con agente
	public const STATUS_CLOSED = 'closed';           // Sesión cerrada

	// =========================================================================
	// TABLAS
	// =========================================================================

	public static function table_sessions()
	{
		global $wpdb;

		return $wpdb->prefix . 'aichat_livechat_sessions';
	}

	public static function table_messages()
	{
		global $wpdb;

		return $wpdb->prefix . 'aichat_livechat_messages';
	}

	/**
	 * Crear tablas en la BD
	 */
	public static function create_tables()
	{
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$sql_sessions = 'CREATE TABLE IF NOT EXISTS ' . self::table_sessions() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL UNIQUE,
            status VARCHAR(32) NOT NULL DEFAULT 'bot',
            agent_id BIGINT UNSIGNED DEFAULT NULL,
            agent_name VARCHAR(255) DEFAULT NULL,
            visitor_name VARCHAR(255) DEFAULT NULL,
            handoff_reason TEXT DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            closed_at DATETIME DEFAULT NULL,
            INDEX idx_status (status),
            INDEX idx_updated (updated_at)
        ) {$charset};";

		$sql_messages = 'CREATE TABLE IF NOT EXISTS ' . self::table_messages() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            sender_type ENUM('visitor', 'agent', 'system', 'bot') NOT NULL,
            sender_name VARCHAR(255) DEFAULT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session (session_id),
            INDEX idx_session_id (session_id, id)
        ) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql_sessions);
		dbDelta($sql_messages);

		// Actualizar ENUM si falta 'bot' (para instalaciones existentes)
		self::maybe_update_enum();
	}

	/**
	 * Actualizar ENUM para añadir 'bot' si no existe
	 */
	private static function maybe_update_enum()
	{
		global $wpdb;

		$table = self::table_messages();

		// Verificar si 'bot' ya existe en el ENUM
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
		$column = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'sender_type'");

		if ($column && strpos($column->Type, 'bot') === false) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
			$wpdb->query("ALTER TABLE {$table} MODIFY COLUMN sender_type ENUM('visitor', 'agent', 'system', 'bot') NOT NULL");
		}
	}

	// =========================================================================
	// ESTADO DE SESIÓN
	// =========================================================================

	/**
	 * Obtener estado actual de una sesión
	 *
	 * @param string $session_id
	 * @return array ['status', 'agent_id', 'agent_name']
	 */
	public static function get_session_status($session_id)
	{
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from self::table_sessions() is plugin-controlled via $wpdb->prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$row = $wpdb->get_row($wpdb->prepare(
			'SELECT status, agent_id, agent_name FROM ' . self::table_sessions() . ' WHERE session_id = %s',
			$session_id
		));
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if (! $row) {
			return [
				'status' => self::STATUS_BOT,
				'agent_id' => null,
				'agent_name' => null,
			];
		}

		return [
			'status' => $row->status,
			'agent_id' => $row->agent_id,
			'agent_name' => $row->agent_name,
		];
	}

	/**
	 * Solicitar handoff a agente humano
	 *
	 * @param string $session_id
	 * @param array $args [reason, visitor_name]
	 * @return array
	 */
	public static function request_handoff($session_id, $args = [])
	{
		global $wpdb;

		$table = self::table_sessions();
		$current = self::get_session_status($session_id);

		// Si ya está conectado o pendiente, no hacer nada
		if ($current['status'] === self::STATUS_CONNECTED) {
			return [
				'success' => true,
				'status' => self::STATUS_CONNECTED,
				/* translators: %s: Agent name who is connected to chat */
				'message' => sprintf(__('Already connected with %s.', 'axiachat-ai'), $current['agent_name']),
			];
		}

		if ($current['status'] === self::STATUS_PENDING) {
			return [
				'success' => true,
				'status' => self::STATUS_PENDING,
				'message' => __('Already waiting for an agent.', 'axiachat-ai'),
			];
		}

		// Crear o actualizar sesión a estado PENDING
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$exists = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$table} WHERE session_id = %s",
			$session_id
		));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$data = [
			'session_id' => $session_id,
			'status' => self::STATUS_PENDING,
			'agent_id' => null,
			'agent_name' => null,
			'visitor_name' => $args['visitor_name'] ?? null,
			'handoff_reason' => $args['reason'] ?? null,
			'closed_at' => null,
		];

		if ($exists) {
			// Limpiar mensajes de livechat anteriores si se reabre
			self::clear_messages($session_id);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update($table, $data, ['session_id' => $session_id]);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert($table, $data);
		}

		// Enviar webhook si está configurado
		self::send_handoff_webhook($session_id, $args);

		// Enviar notificación a Slack si está configurado
		self::send_slack_notification($session_id, $args);

		// Enviar notificación a Telegram si está configurado
		self::send_telegram_notification($session_id, $args);

		// Enviar notificación por email si está configurado
		self::send_email_notification($session_id, $args);

		aichat_log_debug("[LiveChat] request_handoff completed | session={$session_id} status=pending");

		return [
			'success' => true,
			'status' => self::STATUS_PENDING,
			'message' => __('Connecting you with an agent...', 'axiachat-ai'),
		];
	}

	/**
	 * Enviar webhook cuando se solicita handoff
	 */
	private static function send_handoff_webhook($session_id, $args = [])
	{
		$webhook_url = get_option('aichat_livechat_webhook_url', '');

		aichat_log_debug('[LiveChat] send_handoff_webhook | url_configured=' . (! empty($webhook_url) ? 'yes' : 'no'));

		if (empty($webhook_url)) {
			return;
		}

		$payload = [
			'event' => 'handoff_requested',
			'session_id' => $session_id,
			'reason' => $args['reason'] ?? 'User requested human agent',
			'visitor_name' => $args['visitor_name'] ?? 'Visitor',
			'timestamp' => gmdate('c'),
			'page_url' => wp_get_referer() ?: '',
			'source' => 'axiachat_livechat',
		];

		aichat_log_debug("[LiveChat] send_handoff_webhook sending | url={$webhook_url} payload=" . wp_json_encode($payload));

		$response = wp_remote_post($webhook_url, [
			'timeout' => 10,
			'blocking' => true, // Esperar respuesta para debug
			'headers' => ['Content-Type' => 'application/json'],
			'body' => wp_json_encode($payload),
		]);

		if (is_wp_error($response)) {
			aichat_log_debug('[LiveChat] send_handoff_webhook ERROR | ' . $response->get_error_message());
		} else {
			$code = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);
			aichat_log_debug("[LiveChat] send_handoff_webhook response | code={$code} body=" . substr($body, 0, 200));
		}
	}

	/**
	 * Enviar notificación a Slack
	 */
	private static function send_slack_notification($session_id, $args = [])
	{
		$slack_enabled = get_option('aichat_livechat_slack_enabled', 0);
		$webhook_url = get_option('aichat_livechat_slack_webhook_url', '');

		aichat_log_debug("[LiveChat] send_slack_notification | enabled={$slack_enabled} url_configured=" . (! empty($webhook_url) ? 'yes' : 'no'));

		if (! $slack_enabled) {
			aichat_log_debug('[LiveChat] send_slack_notification SKIP - not enabled');

			return;
		}

		if (empty($webhook_url)) {
			aichat_log_debug('[LiveChat] send_slack_notification SKIP - no webhook URL');

			return;
		}

		$reason = $args['reason'] ?? 'User requested human agent';
		$visitor = $args['visitor_name'] ?? 'Visitor';
		$dashboard_url = admin_url('admin.php?page=aichat-livechat&session=' . urlencode($session_id));

		$payload = [
			'text' => '🆘 *New Live Chat Request*',
			'blocks' => [
				[
					'type' => 'section',
					'text' => [
						'type' => 'mrkdwn',
						'text' => "🆘 *New Live Chat Request*\n\n*Visitor:* {$visitor}\n*Reason:* {$reason}",
					],
				],
				[
					'type' => 'actions',
					'block_id' => 'livechat_actions',
					'elements' => [
						[
							'type' => 'button',
							'action_id' => 'take_session',
							'text' => ['type' => 'plain_text', 'text' => '✅ Take Chat', 'emoji' => true],
							'value' => $session_id,
							'style' => 'primary',
						],
						[
							'type' => 'button',
							'action_id' => 'quick_reply',
							'text' => ['type' => 'plain_text', 'text' => '💬 Quick Reply', 'emoji' => true],
							'value' => $session_id,
						],
						[
							'type' => 'button',
							'action_id' => 'open_dashboard',
							'text' => ['type' => 'plain_text', 'text' => '🔗 Open Dashboard', 'emoji' => true],
							'url' => $dashboard_url,
						],
					],
				],
				[
					'type' => 'context',
					'elements' => [
						[
							'type' => 'mrkdwn',
							'text' => "Session: `{$session_id}`",
						],
					],
				],
			],
		];

		// Include conversation history if enabled
		$send_history = get_option('aichat_livechat_slack_send_history', 0);
		if ($send_history) {
			$history = self::get_conversation_history_for_slack($session_id);
			if (! empty($history)) {
				// Insert divider and history block after buttons
				$payload['blocks'][] = [
					'type' => 'divider',
				];
				$payload['blocks'][] = [
					'type' => 'section',
					'text' => [
						'type' => 'mrkdwn',
						'text' => "📜 *Conversation History*\n\n{$history}",
					],
				];
			}
		}

		$channel = get_option('aichat_livechat_slack_channel', '');
		if (! empty($channel)) {
			$payload['channel'] = $channel;
		}

		aichat_log_debug("[LiveChat] send_slack_notification sending | url={$webhook_url}");

		$response = wp_remote_post($webhook_url, [
			'timeout' => 10,
			'blocking' => true,
			'headers' => ['Content-Type' => 'application/json'],
			'body' => wp_json_encode($payload),
		]);

		if (is_wp_error($response)) {
			aichat_log_debug('[LiveChat] send_slack_notification ERROR | ' . $response->get_error_message());
		} else {
			$code = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);
			aichat_log_debug("[LiveChat] send_slack_notification response | code={$code} body=" . substr($body, 0, 200));
		}
	}

	/**
	 * Notificar mensaje del visitante a Slack (durante sesión activa)
	 */
	public static function notify_slack_visitor_message($session_id, $message)
	{
		$slack_enabled = get_option('aichat_livechat_slack_enabled', 0);
		$bot_token = get_option('aichat_livechat_slack_bot_token', '');
		$channel = get_option('aichat_livechat_slack_channel', '');

		if (! $slack_enabled || empty($bot_token)) {
			return;
		}

		// Si no hay canal configurado, no podemos enviar
		if (empty($channel)) {
			aichat_log_debug('[LiveChat] notify_slack_visitor_message SKIP - no channel configured');

			return;
		}

		// Truncar mensaje si es muy largo
		$display_msg = mb_strlen($message) > 500 ? mb_substr($message, 0, 497) . '...' : $message;

		$payload = [
			'channel' => $channel,
			'text' => "💬 *Visitor* (`{$session_id}`):\n>{$display_msg}",
			'mrkdwn' => true,
		];

		$response = wp_remote_post('https://slack.com/api/chat.postMessage', [
			'timeout' => 10,
			'blocking' => false, // No bloquear
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $bot_token,
			],
			'body' => wp_json_encode($payload),
		]);

		aichat_log_debug("[LiveChat] notify_slack_visitor_message sent | session={$session_id}");
	}

	/**
	 * Enviar notificación a Telegram
	 */
	private static function send_telegram_notification($session_id, $args = [])
	{
		$external_dest = get_option('aichat_livechat_external_destination', '');
		$bot_token = get_option('aichat_livechat_telegram_bot_token', '');
		$chat_id = get_option('aichat_livechat_telegram_chat_id', '');

		aichat_log_debug("[LiveChat] send_telegram_notification | dest={$external_dest} token_configured=" . (! empty($bot_token) ? 'yes' : 'no') . ' chat_configured=' . (! empty($chat_id) ? 'yes' : 'no'));

		if ($external_dest !== 'telegram') {
			aichat_log_debug('[LiveChat] send_telegram_notification SKIP - not enabled');

			return;
		}

		if (empty($bot_token) || empty($chat_id)) {
			aichat_log_debug('[LiveChat] send_telegram_notification SKIP - missing token or chat_id');

			return;
		}

		$reason = $args['reason'] ?? 'User requested human agent';
		$visitor = $args['visitor_name'] ?? 'Visitor';
		$dashboard_url = admin_url('admin.php?page=aichat-livechat&session=' . urlencode($session_id));

		// Build message with inline keyboard
		$text = "🆘 *New Live Chat Request*\n\n";
		$text .= "*Visitor:* {$visitor}\n";
		$text .= "*Reason:* {$reason}\n";
		$text .= "*Session:* `{$session_id}`\n\n";
		$text .= '_Click "Take Chat" to respond._';

		// Include history if enabled
		$send_history = get_option('aichat_livechat_telegram_send_history', 0);
		if ($send_history) {
			$history = self::get_conversation_history_for_telegram($session_id);
			if (! empty($history)) {
				$text .= "\n\n📜 *Conversation History*\n{$history}";
			}
		}

		$keyboard = [
			'inline_keyboard' => [
				[
					['text' => '✅ Take Chat', 'callback_data' => 'take:' . $session_id],
					['text' => '🔗 Dashboard', 'url' => $dashboard_url],
				],
			],
		];

		$response = wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
			'timeout' => 10,
			'blocking' => true,
			'body' => [
				'chat_id' => $chat_id,
				'text' => $text,
				'parse_mode' => 'Markdown',
				'reply_markup' => wp_json_encode($keyboard),
			],
		]);

		if (is_wp_error($response)) {
			aichat_log_debug('[LiveChat] send_telegram_notification ERROR | ' . $response->get_error_message());
		} else {
			$code = wp_remote_retrieve_response_code($response);
			$body = wp_remote_retrieve_body($response);
			aichat_log_debug("[LiveChat] send_telegram_notification response | code={$code} body=" . substr($body, 0, 200));

			// Store message_id for reply tracking
			$result = json_decode($body, true);
			if (! empty($result['ok']) && ! empty($result['result']['message_id'])) {
				$msg_id = $result['result']['message_id'];
				update_option('aichat_telegram_session_' . $msg_id, $session_id);
				update_option('aichat_telegram_chat_' . $session_id, $chat_id);
				update_option('aichat_telegram_msg_' . $session_id, $msg_id);
			}
		}
	}

	/**
	 * Notificar mensaje del visitante a Telegram (durante sesión activa)
	 */
	public static function notify_telegram_visitor_message($session_id, $message)
	{
		$external_dest = get_option('aichat_livechat_external_destination', '');
		$bot_token = get_option('aichat_livechat_telegram_bot_token', '');

		if ($external_dest !== 'telegram' || empty($bot_token)) {
			return;
		}

		// Get chat_id from session mapping
		$chat_id = get_option('aichat_telegram_chat_' . $session_id, '');
		$original_msg_id = get_option('aichat_telegram_msg_' . $session_id, '');

		if (empty($chat_id)) {
			return;
		}

		// Truncar mensaje si es muy largo
		$display_msg = mb_strlen($message) > 500 ? mb_substr($message, 0, 497) . '...' : $message;

		$text = "💬 *Visitor*:\n{$display_msg}";

		$data = [
			'chat_id' => $chat_id,
			'text' => $text,
			'parse_mode' => 'Markdown',
		];

		// Reply to the original notification message
		if ($original_msg_id) {
			$data['reply_to_message_id'] = $original_msg_id;
		}

		wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
			'timeout' => 10,
			'blocking' => false,
			'body' => $data,
		]);

		aichat_log_debug("[LiveChat] notify_telegram_visitor_message sent | session={$session_id}");
	}

	/**
	 * Get conversation history formatted for Telegram
	 */
	private static function get_conversation_history_for_telegram($session_id)
	{
		global $wpdb;

		// Get the last N messages from wp_aichat_conversations for this uuid
		$table = $wpdb->prefix . 'aichat_conversations';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT role, message FROM {$table} WHERE session_id = %s ORDER BY id DESC LIMIT 10",
			$session_id
		), ARRAY_A);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if (empty($rows)) {
			return '';
		}

		$rows = array_reverse($rows);
		$lines = [];

		foreach ($rows as $row) {
			$role = $row['role'] === 'user' ? '👤' : '🤖';
			$msg = wp_strip_all_tags($row['message']);
			$msg = mb_strlen($msg) > 150 ? mb_substr($msg, 0, 147) . '...' : $msg;
			$lines[] = "{$role} {$msg}";
		}

		return implode("\n", $lines);
	}

	/**
	 * Enviar notificación por email
	 */
	private static function send_email_notification($session_id, $args = [])
	{
		$enabled = get_option('aichat_livechat_email_notify_enabled', false);

		if (! $enabled) {
			aichat_log_debug('[LiveChat] send_email_notification SKIP - not enabled');

			return;
		}

		$to = get_option('aichat_livechat_email_notify_address', '');
		if (empty($to)) {
			$to = get_option('admin_email');
		}

		if (empty($to)) {
			aichat_log_debug('[LiveChat] send_email_notification SKIP - no email address');

			return;
		}

		$visitor = $args['visitor_name'] ?? 'Visitor';
		$visitor_email = $args['visitor_email'] ?? '';
		$reason = $args['reason'] ?? 'User requested human agent';
		$page_url = wp_get_referer() ?: '';
		$dashboard_url = admin_url('admin.php?page=aichat-livechat&session=' . urlencode($session_id));

		// Get last message
		global $wpdb;
		$conv_table = $wpdb->prefix . 'aichat_conversations';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $conv_table is a trusted plugin table name.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$last_msg = $wpdb->get_var($wpdb->prepare(
			"SELECT message FROM {$conv_table} WHERE session_id = %s ORDER BY id DESC LIMIT 1",
			$session_id
		));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$last_msg = $last_msg ?: '';

		// Get template
		$subject = get_option('aichat_livechat_email_subject', __('New Live Chat Request: {visitor_name}', 'axiachat-ai'));
		$body = get_option(
			'aichat_livechat_email_body',
			"A new Live Chat request needs attention:\n\nVisitor: {visitor_name}\nPage: {page_url}\n\nLast message:\n{last_message}\n\nHandoff reason: {handoff_reason}\n\nDate: {date}\n\n---\nRespond in the dashboard: {dashboard_url}"
		);

		// Get full conversation history for email
		$conversation_history = self::get_conversation_history_for_email($session_id);

		// Replace placeholders
		$replacements = [
			'{visitor_name}' => $visitor,
			'{visitor_email}' => $visitor_email,
			'{page_url}' => $page_url,
			'{last_message}' => $last_msg,
			'{handoff_reason}' => $reason,
			'{date}' => current_time('Y-m-d H:i:s'),
			'{dashboard_url}' => $dashboard_url,
			'{conversation_history}' => $conversation_history,
		];

		$subject = strtr($subject, $replacements);
		$body = strtr($body, $replacements);

		$sent = wp_mail($to, $subject, $body);

		aichat_log_debug("[LiveChat] send_email_notification | to={$to} sent=" . ($sent ? 'yes' : 'no'));
	}

	/**
	 * Get conversation history formatted for email
	 */
	private static function get_conversation_history_for_email($session_id)
	{
		global $wpdb;

		$table = $wpdb->prefix . 'aichat_conversations';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT role, message FROM {$table} WHERE session_id = %s ORDER BY id ASC LIMIT 20",
			$session_id
		), ARRAY_A);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if (empty($rows)) {
			return __('(No conversation history)', 'axiachat-ai');
		}

		$lines = [];
		foreach ($rows as $row) {
			$role = $row['role'] === 'user' ? __('Visitor', 'axiachat-ai') : __('Bot', 'axiachat-ai');
			$msg = wp_strip_all_tags($row['message']);
			// Truncate very long messages
			if (mb_strlen($msg) > 300) {
				$msg = mb_substr($msg, 0, 297) . '...';
			}
			$lines[] = "{$role}: {$msg}";
		}

		return implode("\n\n", $lines);
	}

	/**
	 * Agente toma control de una sesión
	 */
	public static function agent_take_session($session_id, $agent_id)
	{
		global $wpdb;

		$agent = get_userdata($agent_id);
		$agent_name = $agent ? $agent->display_name : 'Agent';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			self::table_sessions(),
			[
				'status' => self::STATUS_CONNECTED,
				'agent_id' => $agent_id,
				'agent_name' => $agent_name,
			],
			['session_id' => $session_id]
		);

		if ($updated) {
			// Mensaje de sistema
			self::add_message(
				$session_id,
				'system',
				'System',
				/* translators: %s: Agent name who joined the chat */
				sprintf(__('%s has joined the chat.', 'axiachat-ai'), $agent_name)
			);
		}

		return $updated ? ['success' => true, 'agent_name' => $agent_name] : ['success' => false];
	}

	/**
	 * Cerrar sesión
	 */
	public static function close_session($session_id)
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->update(
			self::table_sessions(),
			[
				'status' => self::STATUS_CLOSED,
				'closed_at' => current_time('mysql'),
			],
			['session_id' => $session_id]
		);
	}

	/**
	 * Devolver sesión al bot
	 */
	public static function return_to_bot($session_id)
	{
		global $wpdb;

		self::add_message(
			$session_id,
			'system',
			'System',
			__('You are now chatting with the AI assistant.', 'axiachat-ai')
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->update(
			self::table_sessions(),
			[
				'status' => self::STATUS_BOT,
				'agent_id' => null,
				'agent_name' => null,
			],
			['session_id' => $session_id]
		);
	}

	// =========================================================================
	// MENSAJES
	// =========================================================================

	/**
	 * Añadir mensaje
	 *
	 * @param string $session_id
	 * @param string $sender_type 'visitor', 'agent', 'system'
	 * @param string $sender_name
	 * @param string $message
	 * @return int|false Insert ID or false
	 */
	public static function add_message($session_id, $sender_type, $sender_name, $message)
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$result = $wpdb->insert(
			self::table_messages(),
			[
				'session_id' => $session_id,
				'sender_type' => $sender_type,
				'sender_name' => $sender_name,
				'message' => $message,
			]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Obtener el último ID de mensaje de livechat para una sesión
	 *
	 * @param string $session_id
	 * @return int
	 */
	public static function get_last_message_id($session_id)
	{
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from self::table_messages() is plugin-controlled via $wpdb->prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$id = $wpdb->get_var($wpdb->prepare(
			'SELECT MAX(id) FROM ' . self::table_messages() . ' WHERE session_id = %s',
			$session_id
		));
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return (int) $id;
	}

	/**
	 * Obtener mensajes nuevos para el frontend (del agente hacia el visitante)
	 *
	 * @param string $session_id
	 * @param int $since_id Solo mensajes con id > $since_id
	 * @return array
	 */
	public static function get_messages_for_visitor($session_id, $since_id = 0)
	{
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from self::table_messages() is plugin-controlled via $wpdb->prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $wpdb->get_results($wpdb->prepare(
			'SELECT id, sender_type, sender_name, message, created_at 
             FROM ' . self::table_messages() . " 
             WHERE session_id = %s AND id > %d AND sender_type IN ('agent', 'system')
             ORDER BY id ASC",
			$session_id,
			$since_id
		));
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Obtener mensajes nuevos para el dashboard (todos los tipos)
	 * Solo de la tabla livechat_messages (no del historial del bot)
	 *
	 * @param string $session_id
	 * @param int $since_id
	 * @return array
	 */
	public static function get_messages_for_agent($session_id, $since_id = 0)
	{
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Table name from self::table_messages() is plugin-controlled via $wpdb->prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results($wpdb->prepare(
			'SELECT id, sender_type, sender_name, message, created_at 
             FROM ' . self::table_messages() . ' 
             WHERE session_id = %s AND id > %d
             ORDER BY id ASC',
			$session_id,
			$since_id
		));
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Obtener todos los mensajes de una sesión (para cargar historial en dashboard)
	 * Hace UNION con la tabla de conversaciones del bot para mostrar historial completo
	 * Ordena por fecha/hora para mezclar correctamente bot + agente
	 */
	public static function get_all_messages($session_id)
	{
		global $wpdb;

		$livechat_table = self::table_messages();
		$conversations_table = $wpdb->prefix . 'aichat_conversations';

		// UNION de:
		// 1. Mensajes del visitante desde wp_aichat_conversations (historial con bot)
		// 2. Respuestas del bot desde wp_aichat_conversations
		// 3. Mensajes de livechat (agente, system, visitor durante livechat)
		// Usamos una clave única (source + created_at + message hash) para evitar duplicados
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $conversations_table and $livechat_table are trusted plugin table names.
		$sql = $wpdb->prepare(
			"SELECT id, sender_type, sender_name, message, created_at FROM (
                -- Mensajes del visitante al bot
                SELECT 
                    0 as id,
                    'visitor' as sender_type,
                    NULL as sender_name,
                    message as message,
                    created_at,
                    CONCAT('conv_v_', id) as unique_key
                FROM {$conversations_table}
                WHERE session_id = %s AND message != ''
                
                UNION ALL
                
                -- Respuestas del bot
                SELECT 
                    0 as id,
                    'bot' as sender_type,
                    'Bot' as sender_name,
                    response as message,
                    DATE_ADD(created_at, INTERVAL 1 SECOND) as created_at,
                    CONCAT('conv_b_', id) as unique_key
                FROM {$conversations_table}
                WHERE session_id = %s AND response != ''
                
                UNION ALL
                
                -- Mensajes de livechat (agente, system, visitor durante livechat)
                SELECT 
                    id,
                    sender_type,
                    sender_name,
                    message,
                    created_at,
                    CONCAT('lc_', id) as unique_key
                FROM {$livechat_table}
                WHERE session_id = %s
                
            ) AS combined
            GROUP BY unique_key
            ORDER BY created_at ASC, id ASC",
			$session_id,
			$session_id,
			$session_id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql already prepared above with $wpdb->prepare().
		return $wpdb->get_results($sql);
	}

	/**
	 * Obtener historial de conversación formateado para Slack
	 *
	 * @param string $session_id
	 * @return string Texto formateado para Slack mrkdwn
	 */
	public static function get_conversation_history_for_slack($session_id)
	{
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'aichat_conversations';

		// Solo mensajes del bot (visitante + respuestas bot) - no livechat aún
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $conversations_table is a trusted plugin table name.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$messages = $wpdb->get_results($wpdb->prepare(
			"SELECT message, response, created_at 
             FROM {$conversations_table}
             WHERE session_id = %s 
             ORDER BY created_at ASC
             LIMIT 20",
			$session_id
		));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if (empty($messages)) {
			return '';
		}

		$lines = [];
		foreach ($messages as $msg) {
			// Mensaje del visitante
			if (! empty($msg->message)) {
				$text = mb_strlen($msg->message) > 150 ? mb_substr($msg->message, 0, 147) . '...' : $msg->message;
				$lines[] = '*Visitor:* ' . $text;
			}
			// Respuesta del bot
			if (! empty($msg->response)) {
				$text = mb_strlen($msg->response) > 150 ? mb_substr($msg->response, 0, 147) . '...' : $msg->response;
				$lines[] = '*Bot:* ' . $text;
			}
		}

		// Limitar a ~2000 caracteres para no sobrepasar límites de Slack
		$history = implode("\n", $lines);
		if (mb_strlen($history) > 2000) {
			$history = mb_substr($history, 0, 1997) . '...';
		}

		return $history;
	}

	/**
	 * Limpiar mensajes de una sesión
	 */
	public static function clear_messages($session_id)
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $wpdb->delete(self::table_messages(), ['session_id' => $session_id]);
	}

	// =========================================================================
	// LISTADOS PARA DASHBOARD
	// =========================================================================

	/**
	 * Obtener sesiones activas (pending o connected)
	 */
	public static function get_active_sessions()
	{
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Table name from self::table_sessions() is plugin-controlled via $wpdb->prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results(
			'SELECT * FROM ' . self::table_sessions() . " 
             WHERE status IN ('pending_agent', 'agent_connected')
             ORDER BY 
                CASE status 
                    WHEN 'pending_agent' THEN 0 
                    WHEN 'agent_connected' THEN 1 
                END,
                updated_at DESC"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Contar sesiones pendientes
	 */
	public static function count_pending()
	{
		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- Table name from self::table_sessions() is plugin-controlled via $wpdb->prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . self::table_sessions() . " WHERE status = 'pending_agent'"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
	}

	// =========================================================================
	// CONFIGURACIÓN
	// =========================================================================

	/**
	 * Check if agents are available based on mode (manual or schedule)
	 */
	public static function is_available()
	{
		$mode = get_option('aichat_livechat_availability_mode', 'manual');

		if ($mode === 'manual') {
			return (bool) get_option('aichat_livechat_available', 1);
		}

		// Schedule mode - check working hours
		return self::is_within_working_hours();
	}

	/**
	 * Check if current time is within configured working hours
	 */
	public static function is_within_working_hours()
	{
		$working_hours = get_option('aichat_livechat_working_hours', []);

		if (empty($working_hours)) {
			return false;
		}

		// Get current day of week (0 = Sunday, 1 = Monday, etc.)
		$current_day = (int) current_time('w');
		$current_time = current_time('H:i');

		// Check if day is enabled
		if (! isset($working_hours[ $current_day ]) || empty($working_hours[ $current_day ]['enabled'])) {
			return false;
		}

		// Check if current time is within any slot
		$slots = $working_hours[ $current_day ]['slots'] ?? [];
		foreach ($slots as $slot) {
			$start = $slot['start'] ?? '00:00';
			$end = $slot['end'] ?? '23:59';

			if ($current_time >= $start && $current_time <= $end) {
				return true;
			}
		}

		return false;
	}

	public static function get_offline_message()
	{
		return get_option(
			'aichat_livechat_offline_message',
			__('Sorry, no agents are available right now. Please try again later.', 'axiachat-ai')
		);
	}

	public static function get_settings()
	{
		return [
			'enabled' => (bool) get_option('aichat_addon_livechat_enabled', 0),
			'available' => self::is_available(),
			'availability_mode' => get_option('aichat_livechat_availability_mode', 'manual'),
			'working_hours' => get_option('aichat_livechat_working_hours', []),
			'external_destination' => get_option('aichat_livechat_external_destination', ''),
			'estimated_wait' => (int) get_option('aichat_livechat_estimated_wait', 2),
			'agent_timeout' => (int) get_option('aichat_livechat_agent_timeout', 5),
			'queue_message' => get_option('aichat_livechat_queue_message', ''),
			'welcome_message' => get_option('aichat_livechat_welcome_message', ''),
			'offline_message' => self::get_offline_message(),
			'auto_handoff' => (bool) get_option('aichat_livechat_auto_handoff', 0),
			'handoff_keywords' => get_option('aichat_livechat_handoff_keywords', ''),
			'slack_enabled' => (bool) get_option('aichat_livechat_slack_enabled', 0),
			'slack_webhook_url' => get_option('aichat_livechat_slack_webhook_url', ''),
			'slack_channel' => get_option('aichat_livechat_slack_channel', ''),
			'slack_bot_token' => get_option('aichat_livechat_slack_bot_token', ''),
			'slack_send_history' => (bool) get_option('aichat_livechat_slack_send_history', 0),
			'webhook_url' => get_option('aichat_livechat_webhook_url', ''),
			'notification_sound' => (bool) get_option('aichat_livechat_notification_sound', 1),
		];
	}
}
