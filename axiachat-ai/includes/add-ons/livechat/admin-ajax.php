<?php

/**
 * LiveChat AJAX Handlers
 *
 * Endpoints para comunicación frontend ↔ backend
 *
 * @package AIChat
 * @subpackage LiveChat
 * @since 2.5.0
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * =========================================================================
 * ENDPOINTS DEL FRONTEND (widget del visitante)
 * =========================================================================
 */

/**
 * Poll del frontend - obtiene estado y mensajes nuevos
 *
 * El widget llama esto cada 3 segundos cuando está en estado pending o connected
 */
add_action('wp_ajax_aichat_livechat_poll', 'aichat_livechat_poll');
add_action('wp_ajax_nopriv_aichat_livechat_poll', 'aichat_livechat_poll');

function aichat_livechat_poll()
{
	check_ajax_referer('aichat_livechat_nonce', 'nonce');

	$session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
	$since_id = absint($_POST['since_id'] ?? 0);

	if (empty($session_id)) {
		wp_send_json_error(['message' => 'Missing session_id']);
	}

	// Obtener estado actual
	$status_data = AIChat_LiveChat_Manager::get_session_status($session_id);

	// Obtener mensajes nuevos del agente/sistema para el visitante
	$messages = AIChat_LiveChat_Manager::get_messages_for_visitor($session_id, $since_id);

	// Calcular el último ID para el próximo poll
	$last_id = $since_id;
	if (! empty($messages)) {
		$last_msg = end($messages);
		$last_id = (int) $last_msg->id;
	}

	wp_send_json_success([
		'status' => $status_data['status'],
		'agent_name' => $status_data['agent_name'],
		'messages' => $messages,
		'last_id' => $last_id,
	]);
}

/**
 * Visitante envía mensaje (cuando está conectado con agente)
 */
add_action('wp_ajax_aichat_livechat_visitor_message', 'aichat_livechat_visitor_message');
add_action('wp_ajax_nopriv_aichat_livechat_visitor_message', 'aichat_livechat_visitor_message');

function aichat_livechat_visitor_message()
{
	check_ajax_referer('aichat_livechat_nonce', 'nonce');

	$session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
	$message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

	if (empty($session_id) || empty($message)) {
		wp_send_json_error(['message' => 'Missing data']);
	}

	// Verificar que la sesión está conectada
	$status_data = AIChat_LiveChat_Manager::get_session_status($session_id);
	if ($status_data['status'] !== AIChat_LiveChat_Manager::STATUS_CONNECTED) {
		wp_send_json_error(['message' => 'Not connected to agent']);
	}

	// Guardar mensaje
	$msg_id = AIChat_LiveChat_Manager::add_message(
		$session_id,
		'visitor',
		'Visitor',
		$message
	);

	// Notificar a Slack o Telegram
	AIChat_LiveChat_Manager::notify_slack_visitor_message($session_id, $message);
	AIChat_LiveChat_Manager::notify_telegram_visitor_message($session_id, $message);

	wp_send_json_success(['message_id' => $msg_id]);
}

/**
 * =========================================================================
 * ENDPOINTS DEL DASHBOARD (panel de agentes)
 * =========================================================================
 */

/**
 * Listar sesiones activas (usado por el JS como aichat_livechat_get_sessions)
 */
add_action('wp_ajax_aichat_livechat_get_sessions', 'aichat_livechat_get_sessions');

function aichat_livechat_get_sessions()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$sessions = AIChat_LiveChat_Manager::get_active_sessions();
	$pending_count = AIChat_LiveChat_Manager::count_pending();

	// Añadir campos adicionales para UI
	foreach ($sessions as &$session) {
		$session->visitor_name = $session->visitor_name ?: 'Visitor';
		$session->time_ago = human_time_diff(strtotime($session->updated_at), current_time('timestamp'));
	}

	wp_send_json_success([
		'sessions' => $sessions,
		'pending_count' => $pending_count,
	]);
}

/**
 * Agente toma una sesión
 */
add_action('wp_ajax_aichat_livechat_take_session', 'aichat_livechat_take_session');

function aichat_livechat_take_session()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));

	if (empty($session_id)) {
		wp_send_json_error(['message' => 'Missing session_id']);
	}

	$result = AIChat_LiveChat_Manager::agent_take_session($session_id, get_current_user_id());

	if ($result['success']) {
		// Cargar todos los mensajes para el agente
		$messages = AIChat_LiveChat_Manager::get_all_messages($session_id);
		wp_send_json_success([
			'agent_name' => $result['agent_name'],
			'messages' => $messages,
		]);
	} else {
		wp_send_json_error(['message' => 'Failed to take session']);
	}
}

/**
 * Agente envía mensaje (usado por el JS como aichat_livechat_send_message)
 */
add_action('wp_ajax_aichat_livechat_send_message', 'aichat_livechat_send_message');

function aichat_livechat_send_message()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
	$message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

	if (empty($session_id) || empty($message)) {
		wp_send_json_error(['message' => 'Missing data']);
	}

	$agent = wp_get_current_user();
	$agent_name = $agent->display_name;

	$msg_id = AIChat_LiveChat_Manager::add_message(
		$session_id,
		'agent',
		$agent_name,
		$message
	);

	wp_send_json_success([
		'message_id' => $msg_id,
		'sender_name' => $agent_name,
	]);
}

/**
 * Agente poll - obtiene mensajes nuevos del visitante (usado por el JS como aichat_livechat_get_messages)
 */
add_action('wp_ajax_aichat_livechat_get_messages', 'aichat_livechat_get_messages');

function aichat_livechat_get_messages()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));
	$since_id = absint($_POST['since_id'] ?? 0);

	if (empty($session_id)) {
		wp_send_json_error(['message' => 'Missing session_id']);
	}

	// Obtener mensajes nuevos del visitante
	$messages = AIChat_LiveChat_Manager::get_messages_for_agent($session_id, $since_id);

	// Añadir timestamp formateado
	foreach ($messages as &$msg) {
		$msg->time = wp_date('H:i', strtotime($msg->created_at));
	}

	// Calcular último ID
	$last_id = $since_id;
	if (! empty($messages)) {
		$last_msg = end($messages);
		$last_id = (int) $last_msg->id;
	}

	// Estado de la sesión
	$status_data = AIChat_LiveChat_Manager::get_session_status($session_id);

	wp_send_json_success([
		'status' => $status_data['status'],
		'messages' => $messages,
		'last_id' => $last_id,
	]);
}

/**
 * Cerrar sesión
 */
add_action('wp_ajax_aichat_livechat_close_session', 'aichat_livechat_close_session');

function aichat_livechat_close_session()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));

	if (empty($session_id)) {
		wp_send_json_error(['message' => 'Missing session_id']);
	}

	// Añadir mensaje de cierre
	AIChat_LiveChat_Manager::add_message(
		$session_id,
		'system',
		'System',
		__('The chat has been closed.', 'axiachat-ai')
	);

	AIChat_LiveChat_Manager::close_session($session_id);

	wp_send_json_success();
}

/**
 * Devolver al bot
 */
add_action('wp_ajax_aichat_livechat_return_to_bot', 'aichat_livechat_return_to_bot');

function aichat_livechat_return_to_bot()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$session_id = sanitize_text_field(wp_unslash($_POST['session_id'] ?? ''));

	if (empty($session_id)) {
		wp_send_json_error(['message' => 'Missing session_id']);
	}

	AIChat_LiveChat_Manager::return_to_bot($session_id);

	wp_send_json_success();
}

/**
 * Toggle disponibilidad
 */
add_action('wp_ajax_aichat_livechat_toggle_availability', 'aichat_livechat_toggle_availability');

function aichat_livechat_toggle_availability()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$available = isset($_POST['available']) && $_POST['available'] === '1';
	update_option('aichat_livechat_available', $available ? 1 : 0);

	wp_send_json_success(['available' => $available]);
}

/**
 * Crear sesión de prueba (para testing)
 */
add_action('wp_ajax_aichat_livechat_create_test', 'aichat_livechat_create_test');

function aichat_livechat_create_test()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
	}

	$session_id = 'test_' . wp_generate_uuid4();

	$result = AIChat_LiveChat_Manager::request_handoff($session_id, [
		'reason' => 'Test session created from dashboard',
		'visitor_name' => 'Test Visitor',
	]);

	if ($result['success']) {
		// Añadir mensaje de prueba
		AIChat_LiveChat_Manager::add_message(
			$session_id,
			'visitor',
			'Test Visitor',
			'Hello, this is a test message.'
		);

		wp_send_json_success(['session_id' => $session_id]);
	} else {
		wp_send_json_error(['message' => 'Failed to create test session']);
	}
}

/**
 * =========================================================================
 * SETTINGS AJAX
 * =========================================================================
 */

/**
 * Save LiveChat settings via AJAX
 */
add_action('wp_ajax_aichat_livechat_save_settings', 'aichat_livechat_ajax_save_settings');

function aichat_livechat_ajax_save_settings()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	// Destinations
	$external_destination = sanitize_key($_POST['aichat_livechat_external_destination'] ?? '');
	update_option('aichat_livechat_external_destination', $external_destination);

	// Set slack_enabled based on destination
	update_option('aichat_livechat_slack_enabled', $external_destination === 'slack' ? 1 : 0);

	// Availability mode
	$availability_mode = sanitize_key($_POST['aichat_livechat_availability_mode'] ?? 'manual');
	update_option('aichat_livechat_availability_mode', $availability_mode);

	// Manual availability toggle
	update_option('aichat_livechat_available', ! empty($_POST['aichat_livechat_available']) ? 1 : 0);

	// Working hours - wp_unslash and sanitize array input
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in the loop below
	$raw_working_hours = isset($_POST['working_hours']) ? wp_unslash($_POST['working_hours']) : [];
	if (is_array($raw_working_hours)) {
		$working_hours = [];
		foreach ($raw_working_hours as $day => $day_data) {
			$day = absint($day);
			$working_hours[ $day ] = [
				'enabled' => ! empty($day_data['enabled']),
				'slots' => [],
			];

			if (isset($day_data['slots']) && is_array($day_data['slots'])) {
				foreach ($day_data['slots'] as $slot) {
					$start = sanitize_text_field($slot['start'] ?? '09:00');
					$end = sanitize_text_field($slot['end'] ?? '18:00');
					if (preg_match('/^\d{2}:\d{2}$/', $start) && preg_match('/^\d{2}:\d{2}$/', $end)) {
						$working_hours[ $day ]['slots'][] = [
							'start' => $start,
							'end' => $end,
						];
					}
				}
			}

			// Ensure at least one slot
			if (empty($working_hours[ $day ]['slots'])) {
				$working_hours[ $day ]['slots'][] = ['start' => '09:00', 'end' => '18:00'];
			}
		}
		update_option('aichat_livechat_working_hours', $working_hours);
	}

	// Common availability settings
	update_option('aichat_livechat_estimated_wait', absint($_POST['aichat_livechat_estimated_wait'] ?? 2));
	update_option('aichat_livechat_agent_timeout', absint($_POST['aichat_livechat_agent_timeout'] ?? 5));

	// Messages
	update_option('aichat_livechat_queue_message', sanitize_textarea_field(wp_unslash($_POST['aichat_livechat_queue_message'] ?? '')));
	update_option('aichat_livechat_welcome_message', sanitize_textarea_field(wp_unslash($_POST['aichat_livechat_welcome_message'] ?? '')));
	update_option('aichat_livechat_offline_message', sanitize_textarea_field(wp_unslash($_POST['aichat_livechat_offline_message'] ?? '')));

	// Auto handoff
	update_option('aichat_livechat_auto_handoff', ! empty($_POST['aichat_livechat_auto_handoff']) ? 1 : 0);
	update_option('aichat_livechat_handoff_keywords', sanitize_textarea_field(wp_unslash($_POST['aichat_livechat_handoff_keywords'] ?? '')));

	// Slack settings
	update_option('aichat_livechat_slack_webhook_url', esc_url_raw(wp_unslash($_POST['aichat_livechat_slack_webhook_url'] ?? '')));
	update_option('aichat_livechat_slack_channel', sanitize_text_field(wp_unslash($_POST['aichat_livechat_slack_channel'] ?? '')));
	update_option('aichat_livechat_slack_send_history', ! empty($_POST['aichat_livechat_slack_send_history']) ? 1 : 0);

	// Slack Bot Token (for replies) - only update if provided (don't clear existing)
	$slack_bot_token = sanitize_text_field(wp_unslash($_POST['aichat_livechat_slack_bot_token'] ?? ''));
	if (! empty($slack_bot_token)) {
		update_option('aichat_livechat_slack_bot_token', $slack_bot_token);
	}

	// Telegram settings
	$telegram_bot_token = sanitize_text_field(wp_unslash($_POST['aichat_livechat_telegram_bot_token'] ?? ''));
	if (! empty($telegram_bot_token)) {
		update_option('aichat_livechat_telegram_bot_token', $telegram_bot_token);
	}
	update_option('aichat_livechat_telegram_chat_id', sanitize_text_field(wp_unslash($_POST['aichat_livechat_telegram_chat_id'] ?? '')));
	update_option('aichat_livechat_telegram_send_history', ! empty($_POST['aichat_livechat_telegram_send_history']) ? 1 : 0);

	// Custom webhook
	update_option('aichat_livechat_webhook_url', esc_url_raw(wp_unslash($_POST['aichat_livechat_webhook_url'] ?? '')));

	// Notifications
	update_option('aichat_livechat_notification_sound', ! empty($_POST['aichat_livechat_notification_sound']) ? 1 : 0);

	// Email notifications
	update_option('aichat_livechat_email_notify_enabled', ! empty($_POST['aichat_livechat_email_notify_enabled']) ? 1 : 0);
	update_option('aichat_livechat_email_notify_address', sanitize_email(wp_unslash($_POST['aichat_livechat_email_notify_address'] ?? '')));

	aichat_log_debug('[LiveChat] Settings saved via AJAX');

	wp_send_json_success(['message' => __('Settings saved successfully.', 'axiachat-ai')]);
}

/**
 * Save LiveChat email template via AJAX
 */
add_action('wp_ajax_aichat_livechat_save_email_template', 'aichat_livechat_ajax_save_email_template');

function aichat_livechat_ajax_save_email_template()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$subject = sanitize_text_field(wp_unslash($_POST['subject'] ?? ''));
	$body = sanitize_textarea_field(wp_unslash($_POST['body'] ?? ''));

	update_option('aichat_livechat_email_subject', $subject);
	update_option('aichat_livechat_email_body', $body);

	aichat_log_debug('[LiveChat] Email template saved');

	wp_send_json_success(['message' => __('Email template saved.', 'axiachat-ai')]);
}

/**
 * Set Telegram webhook via AJAX
 */
add_action('wp_ajax_aichat_livechat_telegram_set_webhook', 'aichat_livechat_ajax_telegram_set_webhook');

function aichat_livechat_ajax_telegram_set_webhook()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$bot_token = sanitize_text_field(wp_unslash($_POST['bot_token'] ?? ''));
	$webhook_url = esc_url_raw(wp_unslash($_POST['webhook_url'] ?? ''));

	if (empty($bot_token) || empty($webhook_url)) {
		wp_send_json_error(['message' => __('Missing token or webhook URL', 'axiachat-ai')]);
	}

	// Call Telegram setWebhook API
	$response = wp_remote_post("https://api.telegram.org/bot{$bot_token}/setWebhook", [
		'timeout' => 15,
		'body' => [
			'url' => $webhook_url,
		],
	]);

	if (is_wp_error($response)) {
		wp_send_json_error(['message' => $response->get_error_message()]);
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);

	if (! empty($body['ok'])) {
		// Save the token since it was valid
		update_option('aichat_livechat_telegram_bot_token', $bot_token);
		wp_send_json_success(['message' => __('Webhook set successfully!', 'axiachat-ai')]);
	} else {
		$error = $body['description'] ?? 'Unknown error';
		wp_send_json_error(['message' => $error]);
	}
}

/**
 * Test Telegram connection via AJAX
 */
add_action('wp_ajax_aichat_livechat_telegram_test', 'aichat_livechat_ajax_telegram_test');

function aichat_livechat_ajax_telegram_test()
{
	check_ajax_referer('aichat_livechat_admin_nonce', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$bot_token = sanitize_text_field(wp_unslash($_POST['bot_token'] ?? ''));
	$chat_id = sanitize_text_field(wp_unslash($_POST['chat_id'] ?? ''));

	if (empty($bot_token) || empty($chat_id)) {
		wp_send_json_error(['message' => __('Missing token or chat ID', 'axiachat-ai')]);
	}

	// Send test message
	$response = wp_remote_post("https://api.telegram.org/bot{$bot_token}/sendMessage", [
		'timeout' => 15,
		'body' => [
			'chat_id' => $chat_id,
			'text' => "✅ *AxiaChat Live Chat Test*\n\nConnection successful! You will receive notifications here.",
			'parse_mode' => 'Markdown',
		],
	]);

	if (is_wp_error($response)) {
		wp_send_json_error(['message' => $response->get_error_message()]);
	}

	$body = json_decode(wp_remote_retrieve_body($response), true);

	if (! empty($body['ok'])) {
		// Save the settings since they were valid
		update_option('aichat_livechat_telegram_bot_token', $bot_token);
		update_option('aichat_livechat_telegram_chat_id', $chat_id);
		wp_send_json_success(['message' => __('Test message sent successfully!', 'axiachat-ai')]);
	} else {
		$error = $body['description'] ?? 'Unknown error';
		wp_send_json_error(['message' => $error]);
	}
}
