<?php

/**
 * LiveChat Integration - Tool y filtros para el bot
 *
 * Registra el tool transfer_to_human y siempre añade livechat_mode a las respuestas.
 *
 * @package AIChat
 * @subpackage LiveChat
 * @since 2.5.0
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Registrar el tool transfer_to_human usando el sistema estándar
 */
add_action('init', 'aichat_livechat_register_tools', 25);

function aichat_livechat_register_tools()
{
	// Solo si la función de registro existe
	if (! function_exists('aichat_register_tool_safe')) {
		return;
	}

	// Solo si livechat está habilitado
	if (! get_option('aichat_addon_livechat_enabled', 0)) {
		return;
	}

	// Registrar el tool transfer_to_human
	aichat_register_tool_safe('transfer_to_human', [
		'type' => 'function',
		'name' => 'transfer_to_human',
		'description' => 'Transfer the conversation to a human agent when the user explicitly requests it, or when you cannot help with their request. Only use this when truly necessary.',
		'activity_label' => __('Connecting you with a human agent...', 'axiachat-ai'),
		'schema' => [
			'type' => 'object',
			'properties' => [
				'reason' => [
					'type' => 'string',
					'description' => 'Brief reason for the transfer (e.g., "User requested human agent", "Complex billing issue")',
				],
			],
			'required' => ['reason'],
		],
		'callback' => 'aichat_livechat_tool_callback',
		'timeout' => 5,
		'parallel' => false,
		'max_calls' => 1,
	]);

	// Registrar macro para fácil habilitación en bots
	if (function_exists('aichat_register_macro')) {
		aichat_register_macro([
			'name' => 'human_handoff',
			'label' => __('Human Handoff', 'axiachat-ai'),
			'description' => __('Enables the bot to transfer conversations to a human agent when requested by the user or when the bot cannot help.', 'axiachat-ai'),
			'tools' => ['transfer_to_human'],
			'source' => 'local',
			'source_ref' => 'axiachat_livechat',
		]);
	}
}

/**
 * Callback del tool transfer_to_human
 *
 * Simple: solicita el handoff y retorna el resultado.
 * El estado se guardará en BD y se leerá via livechat_mode en cada respuesta.
 */
function aichat_livechat_tool_callback($args, $context = [])
{
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in main AJAX handler; this is a tool callback.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- session_id from context preferred; POST fallback sanitized inline.
	$session_id = $context['session_id'] ?? (isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '');

	if (empty($session_id)) {
		return [
			'success' => false,
			'message' => 'No session ID available',
		];
	}

	// Verificar disponibilidad de agentes
	if (! AIChat_LiveChat_Manager::is_available()) {
		return [
			'success' => false,
			'message' => AIChat_LiveChat_Manager::get_offline_message(),
			'display_to_user' => AIChat_LiveChat_Manager::get_offline_message(),
		];
	}

	// Solicitar handoff
	$reason = $args['reason'] ?? 'User requested human agent';
	$handoff = AIChat_LiveChat_Manager::request_handoff($session_id, [
		'reason' => $reason,
	]);

	return [
		'success' => true,
		'status' => $handoff['status'],
		'message' => $handoff['message'],
		'display_to_user' => $handoff['message'],
	];
}

/**
 * SIEMPRE añadir livechat_mode a la respuesta
 *
 * Valores posibles:
 * - 'bot' (default): El bot responde normalmente
 * - 'waiting_agent': Esperando que un agente acepte
 * - 'agent': Un agente humano está conectado
 */
add_filter('aichat_response_data', 'aichat_livechat_add_mode_to_response');

function aichat_livechat_add_mode_to_response($resp)
{
	// Si livechat no está habilitado, siempre es 'bot'
	if (! get_option('aichat_addon_livechat_enabled', 0)) {
		$resp['livechat_mode'] = 'bot';

		return $resp;
	}

	// Obtener session_id del POST
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in main AJAX handler; this is a filter callback.
	$session_id = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';

	if (empty($session_id)) {
		$resp['livechat_mode'] = 'bot';

		return $resp;
	}

	// Consultar estado actual en BD
	$status_data = AIChat_LiveChat_Manager::get_session_status($session_id);

	// Mapear estado interno a livechat_mode simple
	switch ($status_data['status']) {
		case AIChat_LiveChat_Manager::STATUS_PENDING:
			$resp['livechat_mode'] = 'waiting_agent';
			break;
		case AIChat_LiveChat_Manager::STATUS_CONNECTED:
			$resp['livechat_mode'] = 'agent';
			break;
		default:
			$resp['livechat_mode'] = 'bot';
	}

	return $resp;
}

/**
 * Bloquear respuesta del bot cuando hay agente conectado
 *
 * Este filtro intercepta ANTES de llamar al proveedor de IA
 */
add_filter('aichat_pre_process_message', 'aichat_livechat_block_bot_response', 10, 2);

function aichat_livechat_block_bot_response($response, $data)
{
	if (! get_option('aichat_addon_livechat_enabled', 0)) {
		return $response;
	}

	$session_id = $data['session_id'] ?? '';
	if (empty($session_id)) {
		return $response;
	}

	$status_data = AIChat_LiveChat_Manager::get_session_status($session_id);
	$status = $status_data['status'] ?? AIChat_LiveChat_Manager::STATUS_BOT;

	// Si NO es bot, bloquear comunicación con IA
	// Estados que bloquean: pending_agent, agent_connected, waiting_agent, etc.
	if ($status !== AIChat_LiveChat_Manager::STATUS_BOT) {

		// Guardar mensaje del visitante para el agente
		AIChat_LiveChat_Manager::add_message(
			$session_id,
			'visitor',
			'Visitor',
			$data['message']
		);

		// Mensaje según el estado
		if ($status === AIChat_LiveChat_Manager::STATUS_CONNECTED) {
			// Notificar a Slack o Telegram del mensaje del visitante
			AIChat_LiveChat_Manager::notify_slack_visitor_message($session_id, $data['message']);
			AIChat_LiveChat_Manager::notify_telegram_visitor_message($session_id, $data['message']);

			// Agente conectado: mensaje silencioso (el agente responderá via polling)
			return [
				'success' => true,
				'message' => '', // Vacío, el agente responderá
				'livechat_mode' => 'agent',
			];
		} else {
			// Pendiente/esperando: mensaje de espera
			return [
				'success' => true,
				'message' => __('An agent will be with you shortly. Please wait...', 'axiachat-ai'),
				'livechat_mode' => 'waiting_agent',
			];
		}
	}

	// Estado BOT: dejar que el bot responda normalmente
	return $response;
}

/**
 * Añadir nonce y datos de LiveChat al frontend
 */
add_filter('aichat_frontend_localize_data', 'aichat_livechat_frontend_data');

function aichat_livechat_frontend_data($data)
{
	if (! get_option('aichat_addon_livechat_enabled', 0)) {
		return $data;
	}

	$data['livechat'] = [
		'enabled' => true,
		'nonce' => wp_create_nonce('aichat_livechat_nonce'),
		'available' => AIChat_LiveChat_Manager::is_available(),
		'endpoints' => [
			'poll' => 'aichat_livechat_poll',
			'message' => 'aichat_livechat_visitor_message',
		],
	];

	return $data;
}
