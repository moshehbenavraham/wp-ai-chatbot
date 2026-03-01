<?php

/**
 * Adapter para Anthropic Claude API
 *
 * Refactorización del código existente en class-aichat-ajax.php::call_claude_messages()
 * Mantiene la lógica exacta incluyendo el fallback chain.
 *
 * @package AIChat
 * @since 2.0.0
 */

// Prevent direct access
if (! defined('ABSPATH')) {
	exit;
}

// Load trait for tool execution
require_once dirname(__FILE__) . '/../traits/trait-aichat-tool-execution.php';

class AIChat_Claude_Provider implements AIChat_Provider_Interface
{
	// Usar trait para ejecución de tools
	use AIChat_Tool_Execution;

	/**
	 * Configuración del proveedor
	 * @var array
	 */
	protected $config = [];

	/**
	 * Constructor
	 *
	 * @param array $config Configuración ['api_key' => string]
	 */
	public function __construct($config = [])
	{
		$this->config = $config;
	}

	/**
	 * Obtener ID del proveedor
	 *
	 * @return string
	 */
	public function get_id()
	{
		return 'claude';
	}

	/**
	 * Llamada principal al modelo (auto-router)
	 *
	 * Router que detecta si hay tools y selecciona el flujo adecuado:
	 * - Con tools → chat_with_tools() (multi-ronda)
	 * - Sin tools → chat_simple() (llamada directa)
	 *
	 * @param array $messages Array de mensajes en formato OpenAI
	 * @param array $params Parámetros de la llamada
	 * @return array Respuesta normalizada o error
	 */
	public function chat($messages, $params = [])
	{
		// Extraer configuración
		$api_key = $this->config['api_key'] ?? '';
		if (empty($api_key)) {
			$agency_enabled = (function_exists('aichat_agency_is_configured') && aichat_agency_is_configured())
				|| (bool) get_option('aichat_agency_enabled', false);
			if (! $agency_enabled) {
				return ['error' => __('Missing Claude API Key in settings.', 'axiachat-ai')];
			}
			$api_key = 'proxy';
			$this->api_key = $api_key;
		}

		// Detectar si hay tools
		$tools = $params['tools'] ?? null;
		$has_tools = !empty($tools) && is_array($tools);

		if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
			aichat_log_debug('[Claude Provider] Routing', ['has_tools' => $has_tools, 'model' => $params['model'] ?? 'default'], true);
		}

		// ALWAYS route to chat_with_tools even if tools is empty
		// This allows filters (like aichat_claude_messages_tools) to inject server-side tools like web_search
		// The method will apply filters and then decide whether to use tools or fallback to simple chat
		return $this->chat_with_tools($messages, $params);
	}

	/**
	 * Continuar desde tool_pending
	 *
	 * Recibe el response_id del handshake inicial, ejecuta las tools,
	 * y continúa la conversación con Claude hasta obtener respuesta final.
	 *
	 * @param string $response_id ID del handshake previo
	 * @param array $tool_calls Tools a ejecutar (del frontend)
	 * @return array Respuesta final o error
	 */
	public function continue_from_tool_pending($response_id, $tool_calls)
	{
		global $wpdb;

		if (empty($response_id)) {
			return ['error' => __('Missing response_id', 'axiachat-ai')];
		}

		// Recuperar estado desde base de datos (más robusto que transients)
		$table = $wpdb->prefix . 'aichat_tool_states';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal tool state lookup.
		$row = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
			$wpdb->prepare("SELECT state_data FROM $table WHERE response_id = %s", $response_id)
		);

		if (! $row || empty($row->state_data)) {
			// Estado no encontrado - puede ser expirado o error de guardado
			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				aichat_log_debug('[Claude Provider] Tool state not found in DB', [
					'response_id' => $response_id,
					'table' => $table,
				], true);
			}

			return ['error' => __('Tool state expired or not found', 'axiachat-ai')];
		}

		// Deserializar estado
		$state = maybe_unserialize($row->state_data);

		if (! is_array($state)) {
			return ['error' => __('Invalid tool state data', 'axiachat-ai')];
		}

		// Eliminar estado consumido (one-time use)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal tool state cleanup.
		$wpdb->delete($table, ['response_id' => $response_id], ['%s']);

		// Extraer estado
		$working_messages = $state['working_messages'] ?? [];
		$full_content = $state['full_content'] ?? [];
		$tool_use_blocks = $state['tool_use_blocks'] ?? [];
		$model = $state['model'] ?? aichat_get_default_model('anthropic');
		$temperature = $state['temperature'] ?? 0.7;
		$max_tokens = $state['max_tokens'] ?? 2048;
		$anthropic_tools = $state['anthropic_tools'] ?? [];
		$context = $state['context'] ?? [];
		$total_usage = $state['total_usage'] ?? [
			'prompt_tokens' => 0,
			'completion_tokens' => 0,
			'total_tokens' => 0,
		];

		if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
			aichat_log_debug('[Claude Provider] Continuing from tool_pending', [
				'response_id' => $response_id,
				'tool_count' => count($tool_calls),
			], true);
		}

		// Convertir tool_calls a formato normalizado
		// Frontend normalizer may use 'args' key; also check state for authoritative args
		$state_function_calls = $state['function_calls'] ?? [];
		$normalized_tool_calls = [];
		foreach ($tool_calls as $idx => $tc) {
			$fe_args = $tc['arguments'] ?? ($tc['args'] ?? '{}');
			if (is_array($fe_args)) {
				$fe_args = wp_json_encode($fe_args);
			}
			// State has authoritative args from the original API response
			$state_args = '';
			if (isset($state_function_calls[ $idx ])) {
				$sa = $state_function_calls[ $idx ]['args'] ?? ($state_function_calls[ $idx ]['input'] ?? []);
				$state_args = is_array($sa) ? wp_json_encode($sa) : (string) $sa;
			}
			$final_args = ($state_args !== '' && $state_args !== '{}' && $state_args !== '[]') ? $state_args : $fe_args;

			$normalized_tool_calls[] = [
				'id' => $tc['id'] ?? ($tc['call_id'] ?? ''),
				'name' => $tc['name'] ?? '',
				'arguments' => $final_args,
			];
		}

		// Ejecutar tools
		$context['round'] = 1; // Marcar como round 1 para logs
		$tool_outputs = $this->execute_registered_tools($normalized_tool_calls, $context);

		// Log en BD
		$this->log_tool_executions($normalized_tool_calls, $tool_outputs, 1, $context);

		// Añadir assistant message + user message con tool_result
		$working_messages = $this->append_tool_conversation(
			$working_messages,
			$full_content,
			$tool_outputs
		);

		// Continuar loop desde round 2
		$max_rounds = apply_filters('aichat_max_tool_rounds', 5);

		for ($round = 2; $round <= $max_rounds; $round++) {
			$context['round'] = $round;

			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				aichat_log_debug("[Claude Provider] Continuation round {$round}/{$max_rounds}", [
					'messages_count' => count($working_messages),
				], true);
			}

			// Llamada a Claude
			$result = $this->call_claude_with_tools($working_messages, [
				'model' => $model,
				'temperature' => $temperature,
				'max_tokens' => $max_tokens,
				'tools' => $anthropic_tools,
			]);

			if (isset($result['error'])) {
				return $result;
			}

			// Acumular usage
			if (isset($result['usage'])) {
				$total_usage['prompt_tokens'] += $result['usage']['prompt_tokens'] ?? 0;
				$total_usage['completion_tokens'] += $result['usage']['completion_tokens'] ?? 0;
				$total_usage['total_tokens'] += $result['usage']['total_tokens'] ?? 0;
			}

			// Verificar si hay más tool_use
			$tool_use_blocks = $result['tool_use_blocks'] ?? [];
			$has_tool_calls = !empty($tool_use_blocks);

			// Si no hay más tools, devolver respuesta final
			if (! $has_tool_calls) {
				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug('[Claude Provider] Continuation final response', [
						'round' => $round,
						'answer_len' => strlen($result['message'] ?? ''),
					], true);
				}

				return [
					'message' => $result['message'] ?? '',
					'usage' => $total_usage,
					'finish_reason' => $result['finish_reason'] ?? 'end_turn',
					'model' => $model,
				];
			}

			// Hay más tools - ejecutar directamente (no handshake)
			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				aichat_log_debug('[Claude Provider] Additional tools in continuation', [
					'round' => $round,
					'count' => count($tool_use_blocks),
				], true);
			}

			// Convertir y ejecutar
			$normalized_tool_calls = [];
			foreach ($tool_use_blocks as $block) {
				$normalized_tool_calls[] = [
					'id' => $block['id'],
					'name' => $block['name'],
					'arguments' => wp_json_encode($block['input'] ?? []),
				];
			}

			$tool_outputs = $this->execute_registered_tools($normalized_tool_calls, $context);
			$this->log_tool_executions($normalized_tool_calls, $tool_outputs, $round, $context);

			$working_messages = $this->append_tool_conversation(
				$working_messages,
				$result['full_content'] ?? [],
				$tool_outputs
			);

			// Si alcanzamos límite
			if ($round === $max_rounds) {
				return [
					'error' => sprintf(
						/* translators: %d: Maximum number of tool execution rounds allowed before aborting. */
						__('Maximum tool execution rounds (%d) reached', 'axiachat-ai'),
						$max_rounds
					),
					'usage' => $total_usage,
				];
			}
		}

		return [
			'error' => __('Unexpected exit from continuation loop', 'axiachat-ai'),
			'usage' => $total_usage,
		];
	}

	/**
	 * Chat simple sin tools (LEGACY BEHAVIOR)
	 *
	 * CÓDIGO REFACTORIZADO DE: class-aichat-ajax.php::call_claude_messages()
	 * Mantiene la lógica EXACTA del código original incluyendo fallback chain.
	 *
	 * @param array $messages Array de mensajes en formato OpenAI
	 * @param array $params Parámetros de la llamada
	 * @return array Respuesta normalizada o error
	 */
	protected function chat_simple($messages, $params = [])
	{
		// Extraer configuración
		$api_key = $this->config['api_key'] ?? '';
		if (empty($api_key)) {
			$agency_enabled = (function_exists('aichat_agency_is_configured') && aichat_agency_is_configured())
				|| (bool) get_option('aichat_agency_enabled', false);
			if (! $agency_enabled) {
				return ['error' => __('Missing Claude API Key in settings.', 'axiachat-ai')];
			}
			$api_key = 'proxy';
			$this->config['api_key'] = $api_key;
		}

		// Extraer parámetros
		$model = $params['model'] ?? aichat_get_default_model('anthropic');
		$temperature = $params['temperature'] ?? 0.7;
		$max_tokens = $params['max_tokens'] ?? 2048;

		// === INICIO CÓDIGO ORIGINAL ===
		$endpoint = 'https://api.anthropic.com/v1/messages';

		// 1. Separar system y construir bloques Anthropic
		$system_parts = [];
		$claude_msgs = [];
		foreach ((array) $messages as $m) {
			$role = $m['role'] ?? '';
			$content = $m['content'] ?? '';
			if ($role === 'system') {
				if (is_array($content)) {
					$flat = [];
					foreach ($content as $c) {
						if (is_string($c)) {
							$flat[] = $c;
						} elseif (is_array($c) && isset($c['text'])) {
							$flat[] = $c['text'];
						}
					}
					$system_parts[] = implode("\n\n", $flat);
				} else {
					$system_parts[] = (string) $content;
				}
				continue;
			}
			if ($role !== 'user' && $role !== 'assistant') {
				continue;
			}

			if (is_array($content)) {
				$flat = [];
				foreach ($content as $c) {
					if (is_string($c)) {
						$flat[] = $c;
					} elseif (is_array($c) && isset($c['text'])) {
						$flat[] = $c['text'];
					}
				}
				$content = implode("\n\n", $flat);
			}
			$claude_msgs[] = [
				'role' => $role,
				'content' => [['type' => 'text', 'text' => (string) $content]],
			];
		}
		$system_text = trim(implode("\n\n", array_filter($system_parts)));

		$payload = [
			'model' => $model,
			'max_tokens' => (int) $max_tokens,
			'messages' => $claude_msgs,
		];
		if ($system_text !== '') {
			$payload['system'] = $system_text;
		}
		if ($temperature !== null && $temperature !== '') {
			$payload['temperature'] = (float) $temperature;
		}

		$json_payload = wp_json_encode($payload);

		// Debug log (no api key) – muestra instrucciones, input resumido y flags
		if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
			$dbg = $payload;
			// Truncar posibles campos largos para evitar saturar logs
			if (isset($dbg['instructions']) && is_string($dbg['instructions']) && strlen($dbg['instructions']) > 1200) {
				$dbg['instructions'] = substr($dbg['instructions'], 0, 1200) . '…';
			}
			if (isset($dbg['input']) && is_array($dbg['input'])) {
				// Limitar cada bloque de contenido textual
				foreach ($dbg['input'] as &$blk) {
					if (isset($blk['content']) && is_array($blk['content'])) {
						foreach ($blk['content'] as &$cnt) {
							if (isset($cnt['text']) && is_string($cnt['text']) && strlen($cnt['text']) > 500) {
								$cnt['text'] = substr($cnt['text'], 0, 500) . '…';
							}
						}
					}
				}
			}
			aichat_log_debug('[AIChat Claude] payload', [
				'model' => $model,
				'max_tokens' => $max_tokens,
				'temperature' => $temperature,
				'size_chars' => strlen($json_payload),
				'preview' => $dbg,
			], true);
		}

		// Fallback chain from centralised model registry
		$registry_chain = aichat_get_fallback_chain('anthropic');
		$fallback_chain = array_values(array_filter($registry_chain, function ($fb) use ($model) {
			return $fb !== $model;
		}));
		$primary = $model;

		$attempts = [$primary, ...$fallback_chain];
		$last_error = null;

		foreach ($attempts as $idx => $mdl_try) {
			if ($mdl_try !== $payload['model']) {
				$payload['model'] = $mdl_try;
				$json_payload = wp_json_encode($payload);
			}
			$res = wp_remote_post($endpoint, [
				'headers' => [
					'x-api-key' => $api_key,
					'anthropic-version' => '2023-06-01',
					'content-type' => 'application/json',
				],
				'body' => $json_payload,
				'timeout' => 45,
			]);
			if (is_wp_error($res)) {
				$last_error = $res->get_error_message();
				aichat_log_debug('[AIChat Claude][HTTP_ERR] ' . $last_error, [], true);
				continue;
			}
			$code = wp_remote_retrieve_response_code($res);
			$raw = wp_remote_retrieve_body($res);
			$req_id = wp_remote_retrieve_header($res, 'x-request-id');
			aichat_log_debug('[AIChat Claude][RAW] ' . wp_json_encode([
				'status' => $code, 'model' => $mdl_try, 'req_id' => $req_id ?: '-',
				'attempt' => ($idx + 1) . '/' . count($attempts),
				'payload_len' => strlen($json_payload),
				'resp_len' => strlen($raw),
				'resp_preview' => mb_substr($raw, 0, 500),
			]), [], true);
			// Si 404 y hay más intentos → probar siguiente
			if ($code === 404 && $idx < count($attempts) - 1) {
				$last_error = '404 model not found: ' . $mdl_try;
				continue;
			}
			// Procesar respuesta normal
			if ($code >= 400) {
				$data = json_decode($raw, true);
				$err = '';
				if (isset($data['error']['message'])) {
					$err = $data['error']['message'];
				} elseif (isset($data['error'])) {
					$err = is_string($data['error']) ? $data['error'] : wp_json_encode($data['error']);
				} else {
					$err = 'HTTP ' . $code;
				}
				$last_error = $err;
				// No retry salvo 404 (ya tratado)
				break;
			}
			$data = json_decode($raw, true);
			$text = '';
			if (isset($data['content']) && is_array($data['content'])) {
				foreach ($data['content'] as $blk) {
					if (is_array($blk) && ($blk['type'] ?? '') === 'text' && isset($blk['text'])) {
						$text .= ($text ? "\n\n" : '') . trim((string) $blk['text']);
					}
				}
			}
			if ($text === '' && isset($data['message']['content']) && is_string($data['message']['content'])) {
				$text = trim($data['message']['content']);
			}
			if ($text === '') {
				$last_error = 'Respuesta vacía de Claude (sin bloques).';
				break;
			}
			// Si hubo fallback exitoso, log y devolver
			if ($mdl_try !== $primary) {
				aichat_log_debug('[AIChat Claude] Fallback model used: ' . $mdl_try . ' (original=' . $primary . ')', [], true);
			}
			// Claude usage structure: usage: { input_tokens:X, output_tokens:Y }
			$usage = [];
			if (isset($data['usage'])) {
				$usage['prompt_tokens'] = isset($data['usage']['input_tokens']) ? (int) $data['usage']['input_tokens'] : null;
				$usage['completion_tokens'] = isset($data['usage']['output_tokens']) ? (int) $data['usage']['output_tokens'] : null;
				$usage['total_tokens'] = ($usage['prompt_tokens'] !== null && $usage['completion_tokens'] !== null) ? ($usage['prompt_tokens'] + $usage['completion_tokens']) : null;
			}
			// === FIN CÓDIGO ORIGINAL ===

			// Información adicional
			$finish_reason = $data['stop_reason'] ?? 'end_turn';

			return [
				'message' => $text,
				'usage' => $usage,
				'finish_reason' => $finish_reason,
				'model_used' => $mdl_try, // Registrar qué modelo del fallback se usó
			];
		}

		// Todos los intentos fallaron
		return ['error' => $last_error ?? __('Claude request failed', 'axiachat-ai')];
	}

	/**
	 * Chat con tools (multi-ronda)
	 *
	 * Implementa tool calling usando la API de Anthropic:
	 * 1. Envía mensaje inicial con herramientas disponibles
	 * 2. Si responde con tool_use, ejecutar y continuar
	 * 3. Repetir hasta que devuelva respuesta final
	 *
	 * Anthropic tool calling docs:
	 * https://docs.anthropic.com/en/docs/build-with-claude/tool-use
	 *
	 * @param array $messages Historial de mensajes
	 * @param array $params Parámetros (model, tools, temperature, max_tokens, etc.)
	 * @return array Respuesta final o error
	 */
	protected function chat_with_tools($messages, $params = [])
	{
		$api_key = $this->config['api_key'] ?? '';
		$model = $params['model'] ?? aichat_get_default_model('anthropic');
		$temperature = $params['temperature'] ?? 0.7;
		$max_tokens = $params['max_tokens'] ?? 2048;
		$tools = $params['tools'] ?? [];

		// Contexto para logging y tool execution
		$context = [
			'request_uuid' => $params['request_uuid'] ?? '',
			'session_id' => $params['session_id'] ?? '',
			'bot_slug' => $params['bot_slug'] ?? '',
			'message' => '',
			'round' => 1,
		];

		// Límite de rondas (configurable vía filter)
		$max_rounds = apply_filters('aichat_max_tool_rounds', 5);

		// Apply Claude-specific tools filter (includes web_search_20250305 injection)
		$filter_ctx = [
			'model' => $model,
			'bot' => $context['bot_slug'],
		];
		$tools = apply_filters('aichat_claude_messages_tools', $tools, $filter_ctx);

		// If still no tools after filter, fallback to simple chat
		if (empty($tools)) {
			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				aichat_log_debug('[Claude Provider] No tools after filter, using simple chat', [], true);
			}

			return $this->chat_simple($messages, $params);
		}

		// Construir herramientas en formato Anthropic
		$anthropic_tools = $this->build_anthropic_tools($tools);

		// Working messages: copia que se modifica en cada ronda
		$working_messages = $messages;

		// Acumuladores
		$total_usage = [
			'prompt_tokens' => 0,
			'completion_tokens' => 0,
			'total_tokens' => 0,
		];

		for ($round = 1; $round <= $max_rounds; $round++) {
			$context['round'] = $round;

			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				aichat_log_debug("[Claude Provider] Starting round {$round}/{$max_rounds}", [
					'messages_count' => count($working_messages),
				], true);
			}

			// Llamada a Claude con tools
			$result = $this->call_claude_with_tools($working_messages, [
				'model' => $model,
				'temperature' => $temperature,
				'max_tokens' => $max_tokens,
				'tools' => $anthropic_tools,
			]);

			if (isset($result['error'])) {
				return $result; // Error, abortar
			}

			// Acumular usage
			if (isset($result['usage'])) {
				$total_usage['prompt_tokens'] += $result['usage']['prompt_tokens'] ?? 0;
				$total_usage['completion_tokens'] += $result['usage']['completion_tokens'] ?? 0;
				$total_usage['total_tokens'] += $result['usage']['total_tokens'] ?? 0;
			}

			// Verificar si hay tool_use blocks
			$tool_use_blocks = $result['tool_use_blocks'] ?? [];
			$has_tool_calls = !empty($tool_use_blocks);

			// Si no hay tool calls, devolver respuesta final
			if (! $has_tool_calls) {
				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug('[Claude Provider] Final response (no more tools)', [
						'round' => $round,
						'answer_len' => strlen($result['message'] ?? ''),
					], true);
				}

				return [
					'message' => $result['message'] ?? '',
					'usage' => $total_usage,
					'finish_reason' => $result['finish_reason'] ?? 'end_turn',
					'model' => $model,
				];
			}

			// Hay tool calls
			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				aichat_log_debug('[Claude Provider] Tool calls detected', [
					'round' => $round,
					'count' => count($tool_use_blocks),
					'tools' => array_column($tool_use_blocks, 'name'),
				], true);
			}

			// PRIMERA RONDA: Devolver tool_pending para que frontend muestre "Ejecutando X"
			if ($round === 1) {
				// Generar response_id único para continuar
				$response_id = wp_generate_uuid4();

				// Convertir tool_use_blocks a formato para frontend
				$pending_tool_calls = [];

				// Obtener registro de tools para activity_labels amigables
				$registered_map = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];

				foreach ($tool_use_blocks as $block) {
					$tool_name = $block['name'] ?? '';

					// Generar activity_label amigable
					if (isset($registered_map[$tool_name]['activity_label'])) {
						// Usar activity_label del registro (ej: "Running Get Current Weather...")
						$activity_label = (string) $registered_map[$tool_name]['activity_label'];
					} else {
						// Fallback: extraer nombre local y formatearlo
						// "mcp_tiempo_860905_get_current_weather" → "Get Current Weather"
						$local_name = $tool_name;
						// Intentar extraer último segmento si tiene formato MCP
						if (preg_match('/^mcp_[^_]+_[^_]+_(.+)$/', $tool_name, $matches)) {
							$local_name = $matches[1];
						}
						$friendly_name = ucwords(str_replace('_', ' ', $local_name));
						$activity_label = sprintf(
							/* translators: %s: Tool name being executed (e.g., 'Get Weather') */
							__('Running %s...', 'axiachat-ai'),
							$friendly_name
						);
					}

					$pending_tool_calls[] = [
						'id' => $block['id'],
						'name' => $tool_name,
						'arguments' => wp_json_encode($block['input'] ?? []),
						'activity_label' => $activity_label,
					];
				}

				// Guardar estado para continuation en base de datos
				// Más robusto que transients (evita race conditions con object cache)
				global $wpdb;
				$table = $wpdb->prefix . 'aichat_tool_states';

				$state_data = [
					'working_messages' => $working_messages,
					'full_content' => $result['full_content'] ?? [],
					'tool_use_blocks' => $tool_use_blocks,
					'model' => $model,
					'temperature' => $temperature,
					'max_tokens' => $max_tokens,
					'anthropic_tools' => $anthropic_tools,
					'context' => $context,
					'total_usage' => $total_usage,
				];

				// Insertar estado en DB
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal tool state persistence.
				$inserted = $wpdb->insert(
					$table,
					[
						'response_id' => $response_id,
						'state_data' => maybe_serialize($state_data),
						'created_at' => current_time('mysql'),
					],
					['%s', '%s', '%s']
				);

				if (! $inserted) {
					// Error crítico - no se pudo guardar estado
					if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
						aichat_log_debug('[Claude Provider] Failed to save tool state', [
							'response_id' => $response_id,
							'error' => $wpdb->last_error,
						], true);
					}

					return ['error' => __('Failed to save tool state', 'axiachat-ai')];
				}

				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug('[Claude Provider] Returning tool_pending handshake', [
						'response_id' => $response_id,
						'tool_count' => count($pending_tool_calls),
						'table' => $table,
					], true);
				}

				return [
					'status' => 'tool_pending',
					'response_id' => $response_id,
					'tool_calls' => $pending_tool_calls,
					'request_uuid' => $context['request_uuid'],
					'usage' => $total_usage,
				];
			}

			// RONDAS POSTERIORES (2+): Ejecutar tools directamente

			// Convertir tool_use_blocks a formato unificado para execute_registered_tools
			$normalized_tool_calls = [];
			foreach ($tool_use_blocks as $block) {
				$normalized_tool_calls[] = [
					'id' => $block['id'],
					'name' => $block['name'],
					'arguments' => wp_json_encode($block['input'] ?? []),
				];
			}

			// Ejecutar tools usando trait
			$tool_outputs = $this->execute_registered_tools($normalized_tool_calls, $context);

			// Log en BD
			$this->log_tool_executions($normalized_tool_calls, $tool_outputs, $round, $context);

			// Añadir assistant message con tool_use + user message con tool_result
			$working_messages = $this->append_tool_conversation(
				$working_messages,
				$result['full_content'] ?? [], // Content blocks completos de Claude
				$tool_outputs
			);

			// Si alcanzamos el límite de rondas, abortar
			if ($round === $max_rounds) {
				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug('[Claude Provider] Max rounds reached', ['max' => $max_rounds], true);
				}

				return [
					'error' => sprintf(
						/* translators: %d: Maximum number of tool execution rounds allowed before aborting. */
						__('Maximum tool execution rounds (%d) reached', 'axiachat-ai'),
						$max_rounds
					),
					'usage' => $total_usage,
				];
			}
		}

		// No debería llegar aquí
		return [
			'error' => __('Unexpected exit from tool loop', 'axiachat-ai'),
			'usage' => $total_usage,
		];
	}

	/**
	 * Construir herramientas en formato Anthropic
	 *
	 * Convierte tools de formato OpenAI a formato Anthropic.
	 * Soporta:
	 * - Client-side tools (function): Convertidos a input_schema
	 * - Server-side tools (web_search_20250305): Pasados directamente
	 *
	 * OpenAI function format:
	 * {
	 *   "type": "function",
	 *   "function": {
	 *     "name": "get_weather",
	 *     "description": "Get weather...",
	 *     "parameters": { "type": "object", "properties": {...} }
	 *   }
	 * }
	 *
	 * Anthropic client tool format:
	 * {
	 *   "name": "get_weather",
	 *   "description": "Get weather...",
	 *   "input_schema": { "type": "object", "properties": {...} }
	 * }
	 *
	 * Anthropic server tool format (web_search_20250305):
	 * {
	 *   "type": "web_search_20250305",
	 *   "name": "web_search",
	 *   "max_uses": 5,
	 *   "allowed_domains": ["example.com"],
	 *   "blocked_domains": ["spam.com"]
	 * }
	 *
	 * @param array $openai_tools Tools en formato OpenAI (o mixto)
	 * @return array Tools en formato Anthropic
	 */
	protected function build_anthropic_tools($openai_tools)
	{
		$anthropic_tools = [];

		foreach ($openai_tools as $tool) {
			$type = $tool['type'] ?? '';

			if ($type === 'function') {
				// Client-side tool: convertir de OpenAI a Anthropic
				$func = $tool['function'] ?? [];
				$anthropic_tools[] = [
					'name' => $func['name'] ?? '',
					'description' => $func['description'] ?? '',
					'input_schema' => $func['parameters'] ?? ['type' => 'object', 'properties' => []],
				];
			} elseif ($type === 'web_search_20250305') {
				// Server-side tool: pasar directamente (Anthropic lo ejecuta)
				// El filtro aichat_claude_messages_tools ya lo construyó con el formato correcto
				$anthropic_tools[] = $tool;
			}
			// Otros tipos de server-side tools en el futuro (web_fetch, etc.)
		}

		return $anthropic_tools;
	}

	/**
	 * Llamada a Claude con tools
	 *
	 * Realiza una llamada única a la API de Claude con tools.
	 *
	 * @param array $messages Mensajes en formato interno
	 * @param array $params Parámetros (model, temperature, max_tokens, tools)
	 * @return array Respuesta parseada o error
	 */
	protected function call_claude_with_tools($messages, $params)
	{
		$api_key = $this->config['api_key'] ?? '';
		$model = $params['model'] ?? aichat_get_default_model('anthropic');
		$temperature = $params['temperature'] ?? 0.7;
		$max_tokens = $params['max_tokens'] ?? 2048;
		$tools = $params['tools'] ?? [];

		$endpoint = 'https://api.anthropic.com/v1/messages';

		// Separar system de user/assistant messages
		$system_parts = [];
		$claude_msgs = [];

		foreach ($messages as $m) {
			$role = $m['role'] ?? '';
			$content = $m['content'] ?? '';

			if ($role === 'system') {
				// Extraer texto del system message
				if (is_array($content)) {
					$flat = [];
					foreach ($content as $c) {
						if (is_string($c)) {
							$flat[] = $c;
						} elseif (is_array($c) && isset($c['text'])) {
							$flat[] = $c['text'];
						}
					}
					$system_parts[] = implode("\n\n", $flat);
				} else {
					$system_parts[] = (string) $content;
				}
				continue;
			}

			// Construir message en formato Anthropic
			// El content puede ser string o array de blocks
			if (is_string($content)) {
				$claude_msgs[] = [
					'role' => $role,
					'content' => [['type' => 'text', 'text' => $content]],
				];
			} elseif (is_array($content)) {
				// Puede venir de rondas previas con tool_use/tool_result blocks
				// O puede ser content normal que necesita normalización
				$claude_msgs[] = [
					'role' => $role,
					'content' => $content, // Pasamos directamente
				];
			}
		}

		$system_text = trim(implode("\n\n", array_filter($system_parts)));

		// Construir payload
		$payload = [
			'model' => $model,
			'max_tokens' => (int) $max_tokens,
			'messages' => $claude_msgs,
		];

		if ($system_text !== '') {
			$payload['system'] = $system_text;
		}

		if ($temperature !== null && $temperature !== '') {
			$payload['temperature'] = (float) $temperature;
		}

		if (!empty($tools)) {
			$payload['tools'] = $tools;
		}

		$json_payload = wp_json_encode($payload);

		if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
			aichat_log_debug('[Claude Provider] API call', [
				'model' => $model,
				'tools_count' => count($tools),
				'messages_count' => count($claude_msgs),
				'payload_size' => strlen($json_payload),
			], true);
		}

		// Retry logic para errores temporales (overloaded, rate limit, etc.)
		$max_retries = apply_filters('aichat_claude_max_retries', 3);
		$base_delay = 1; // segundos
		$last_error = null;
		$last_code = 0;

		for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
			// Realizar request
			$res = wp_remote_post($endpoint, [
				'headers' => [
					'x-api-key' => $api_key,
					'anthropic-version' => '2023-06-01',
					'content-type' => 'application/json',
				],
				'body' => $json_payload,
				'timeout' => 60, // Tools pueden tardar más
			]);

			if (is_wp_error($res)) {
				$last_error = $res->get_error_message();
				// Network errors también pueden necesitar retry
				if ($attempt < $max_retries) {
					$delay = $base_delay * pow(2, $attempt - 1);
					if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
						aichat_log_debug('[Claude Provider] Network error, retrying', [
							'attempt' => $attempt,
							'max_retries' => $max_retries,
							'delay_seconds' => $delay,
							'error' => $last_error,
						], true);
					}
					sleep($delay);
					continue;
				}

				return ['error' => $last_error];
			}

			$code = wp_remote_retrieve_response_code($res);
			$raw = wp_remote_retrieve_body($res);
			$last_code = $code;

			// Errores temporales que necesitan retry
			// 429: Rate limit
			// 529: Overloaded
			// 503: Service unavailable
			$retryable_codes = [429, 503, 529];

			if (in_array($code, $retryable_codes, true)) {
				if ($attempt < $max_retries) {
					$delay = $base_delay * pow(2, $attempt - 1); // Exponential: 1s, 2s, 4s

					$data = json_decode($raw, true);
					$err_msg = $data['error']['message'] ?? 'Service temporarily unavailable';
					$last_error = $err_msg;

					if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
						aichat_log_debug("[Claude Provider] Retryable error (HTTP {$code}), waiting {$delay}s", [
							'attempt' => $attempt,
							'max_retries' => $max_retries,
							'delay_seconds' => $delay,
							'error' => $err_msg,
						], true);
					}

					sleep($delay);
					continue; // Retry
				}

				// Último intento falló
				$data = json_decode($raw, true);
				$err_msg = $data['error']['message'] ?? 'Service temporarily unavailable';

				return ['error' => "{$err_msg} (after {$max_retries} retries)"];
			}

			// Otros errores 4xx/5xx (no retryables)
			if ($code >= 400) {
				$data = json_decode($raw, true);
				$err = '';
				if (isset($data['error']['message'])) {
					$err = $data['error']['message'];
				} elseif (isset($data['error'])) {
					$err = is_string($data['error']) ? $data['error'] : wp_json_encode($data['error']);
				} else {
					$err = 'HTTP ' . $code;
				}

				return ['error' => $err];
			}

			// Success - salir del loop
			break;
		}

		// Parse respuesta
		$data = json_decode($raw, true);

		if (!isset($data['content']) || !is_array($data['content'])) {
			return ['error' => __('Invalid response format from Claude', 'axiachat-ai')];
		}

		// Extraer content blocks
		$content_blocks = $data['content'];
		$text_parts = [];
		$tool_use_blocks = [];
		$web_search_results = [];
		$citations = [];

		foreach ($content_blocks as $block) {
			$type = $block['type'] ?? '';

			if ($type === 'text') {
				$text_parts[] = $block['text'] ?? '';

				// Extract citations from web search (if present)
				if (isset($block['citations']) && is_array($block['citations'])) {
					foreach ($block['citations'] as $citation) {
						$citations[] = [
							'url' => $citation['url'] ?? '',
							'title' => $citation['title'] ?? '',
							'cited_text' => $citation['cited_text'] ?? '',
						];
					}
				}
			} elseif ($type === 'tool_use') {
				// Client-side tool call
				$tool_use_blocks[] = [
					'id' => $block['id'] ?? '',
					'name' => $block['name'] ?? '',
					'input' => $block['input'] ?? [],
				];
			} elseif ($type === 'server_tool_use') {
				// Server-side tool call (web_search executed by Anthropic)
				$tool_name = $block['name'] ?? '';
				$query = isset($block['input']['query']) ? $block['input']['query'] : '';

				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug("[Claude] Server tool used: {$tool_name}", [
						'query' => $query,
						'tool_use_id' => $block['id'] ?? '',
					], true);
				}
			} elseif ($type === 'web_search_tool_result') {
				// Web search results from Anthropic
				$results = $block['content'] ?? [];

				foreach ($results as $result) {
					if (($result['type'] ?? '') === 'web_search_result') {
						$web_search_results[] = [
							'url' => $result['url'] ?? '',
							'title' => $result['title'] ?? '',
							'page_age' => $result['page_age'] ?? '',
						];
					}
				}

				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug('[Claude] Web search results received', [
						'count' => count($web_search_results),
						'tool_use_id' => $block['tool_use_id'] ?? '',
					], true);
				}
			}
		}

		$text = implode("\n\n", array_filter($text_parts));

		// Append citations as footnotes (if any)
		if (!empty($citations)) {
			$text .= "\n\n---\n**Sources:**\n";
			$citation_num = 1;
			foreach ($citations as $citation) {
				if ($citation['url'] && $citation['title']) {
					$text .= sprintf(
						"[%d] [%s](%s)\n",
						$citation_num,
						esc_html($citation['title']),
						esc_url($citation['url'])
					);
					$citation_num++;
				}
			}
		}

		// Usage (includes web_search_requests if present)
		$usage = [];
		if (isset($data['usage'])) {
			$usage['prompt_tokens'] = isset($data['usage']['input_tokens']) ? (int) $data['usage']['input_tokens'] : 0;
			$usage['completion_tokens'] = isset($data['usage']['output_tokens']) ? (int) $data['usage']['output_tokens'] : 0;
			$usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];

			// Track web search usage for billing transparency
			if (isset($data['usage']['server_tool_use']['web_search_requests'])) {
				$usage['web_search_requests'] = (int) $data['usage']['server_tool_use']['web_search_requests'];

				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug('[Claude] Web search requests billed', [
						'count' => $usage['web_search_requests'],
						'pricing' => '$10 per 1,000 searches',
					], true);
				}
			}
		}

		return [
			'message' => $text,
			'full_content' => $content_blocks, // Guardar blocks completos para construir siguiente ronda
			'tool_use_blocks' => $tool_use_blocks,
			'web_search_results' => $web_search_results,
			'citations' => $citations,
			'usage' => $usage,
			'finish_reason' => $data['stop_reason'] ?? 'end_turn',
		];
	}

	/**
	 * Añadir conversación de tools al historial
	 *
	 * Anthropic requiere que después de un assistant message con tool_use,
	 * el siguiente mensaje sea user con tool_result blocks.
	 *
	 * @param array $messages Mensajes actuales
	 * @param array $assistant_content Content blocks del assistant (con tool_use)
	 * @param array $tool_outputs Outputs ejecutados
	 * @return array Mensajes actualizados
	 */
	protected function append_tool_conversation($messages, $assistant_content, $tool_outputs)
	{
		// 1. Añadir assistant message con content blocks originales
		// IMPORTANTE: Normalizar tool_use.input para que siempre sea objeto JSON (no array)
		$normalized_content = [];
		foreach ($assistant_content as $block) {
			if (isset($block['type']) && $block['type'] === 'tool_use') {
				// Forzar input a ser objeto (stdClass) para wp_json_encode
				// Esto evita que arrays vacíos [] se conviertan en [] en lugar de {}
				$input = $block['input'] ?? [];
				if (is_array($input) && empty($input)) {
					// Array vacío → objeto vacío
					$input = new \stdClass();
				} elseif (is_array($input) && array_keys($input) === range(0, count($input) - 1)) {
					// Array indexado (serialización lo convirtió) → mantener como array
					// Pero asegurar que si es vacío sea objeto
					if (empty($input)) {
						$input = new \stdClass();
					}
				}

				$normalized_content[] = [
					'type' => 'tool_use',
					'id' => $block['id'] ?? '',
					'name' => $block['name'] ?? '',
					'input' => $input,
				];
			} else {
				// Otros blocks (text, etc.) pasan sin modificar
				$normalized_content[] = $block;
			}
		}

		$messages[] = [
			'role' => 'assistant',
			'content' => $normalized_content,
		];

		// 2. Construir user message con tool_result blocks
		$tool_result_blocks = [];
		foreach ($tool_outputs as $output) {
			$tool_result_blocks[] = [
				'type' => 'tool_result',
				'tool_use_id' => $output['tool_call_id'],
				'content' => $output['output'], // String JSON del output
			];
		}

		$messages[] = [
			'role' => 'user',
			'content' => $tool_result_blocks,
		];

		return $messages;
	}

	/**
	 * Calcular coste de la llamada en microcents
	 *
	 * Precios basados en documentación oficial de Anthropic (Nov 2025)
	 * https://www.anthropic.com/pricing
	 *
	 * Nota: Anthropic cobra por millón de tokens (MTok), no por 1K como OpenAI
	 *
	 * @param array $usage Array con tokens: ['prompt_tokens', 'completion_tokens', 'total_tokens']
	 * @param string $model ID del modelo usado
	 * @return int|null Coste en microcents (1 cent = 10,000 micros), null si modelo desconocido
	 */
	public function calculate_cost($usage, $model)
	{
		// Derive pricing from centralised model registry (per 1M tokens)
		$pricing = [];
		if (function_exists('aichat_get_models_for_provider')) {
			foreach (aichat_get_models_for_provider('anthropic') as $id => $m) {
				$pricing[ $id ] = [
					'prompt' => $m['pricing']['input'],
					'completion' => $m['pricing']['output'],
				];
				// Also add aliases for prefix matching
				if (! empty($m['aliases'])) {
					foreach ((array) $m['aliases'] as $a) {
						$pricing[ $a ] = $pricing[ $id ];
					}
				}
			}
		}

		$rates = null;
		if (isset($pricing[ $model ])) {
			$rates = $pricing[ $model ];
		} else {
			// Fallback por prefijo para snapshots (e.g. claude-sonnet-4-5-YYYYMMDD)
			foreach ($pricing as $k => $v) {
				if (stripos($model, $k) === 0) {
					$rates = $v;
					break;
				}
			}
		}

		// Modelo desconocido, no podemos calcular coste
		if (! $rates) {
			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				aichat_log_debug("[Claude Provider] Unknown model for cost calculation: {$model}", [], true);
			}

			return null;
		}
		$prompt_tokens = $usage['prompt_tokens'] ?? 0;
		$completion_tokens = $usage['completion_tokens'] ?? 0;

		// Calcular coste en USD (precio es per 1M tokens)
		$cost_usd = ($prompt_tokens / 1000000 * $rates['prompt']) +
					($completion_tokens / 1000000 * $rates['completion']);

		// Convertir a microcents
		// 1 USD = 100 cents = 1,000,000 microcents
		// 1 cent = 10,000 microcents
		$cost_microcents = (int) round($cost_usd * 100 * 10000);

		if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
			aichat_log_debug('[Claude Provider] Cost calculated', [
				'model' => $model,
				'prompt_tokens' => $prompt_tokens,
				'completion_tokens' => $completion_tokens,
				'cost_usd' => $cost_usd,
				'cost_microcents' => $cost_microcents,
			], true);
		}

		return $cost_microcents;
	}
}
