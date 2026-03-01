<?php

/**
 * AI Chat — AJAX (con trazas)
 *
 * @package AIChat
 */

if (! defined('ABSPATH')) {
	exit;
}

// This file performs real-time chat processing and internal logging.
// Caching DB access here is not appropriate; we intentionally use direct DB calls.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

// Definir bandera de depuración si no existe para evitar notices
if (! defined('AICHAT_DEBUG')) {
	define('AICHAT_DEBUG', false);
}

if (! class_exists('AIChat_Ajax')) {

	class AIChat_Ajax
	{
		private static $instance = null;

		public static function instance()
		{
			if (self::$instance === null) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		private function __construct()
		{
			add_action('wp_ajax_aichat_process_message', [$this, 'process_message']);
			add_action('wp_ajax_nopriv_aichat_process_message', [$this, 'process_message']);
			add_action('wp_ajax_aichat_get_history', [$this, 'get_history']);
			add_action('wp_ajax_nopriv_aichat_get_history', [$this, 'get_history']);
		}

		public function process_message()
		{
			$t0 = microtime(true);
			$uid = wp_generate_uuid4();

			// Flag de depuración ahora sólo depende de la constante global AICHAT_DEBUG
			$debug = (defined('AICHAT_DEBUG') && AICHAT_DEBUG);
			aichat_log_debug("[AIChat AJAX][$uid] start");
			// Nonce
			$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
			if (empty($nonce) || ! wp_verify_nonce($nonce, 'aichat_ajax')) {
				aichat_log_debug("[AIChat AJAX][$uid] nonce invalid");
				wp_send_json_error(['message' => __('Invalid nonce.', 'axiachat-ai')], 403);
			}

			// Flujo de continuación de tools (segunda fase)
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via wp_verify_nonce.
			$is_continue = false;
			if (isset($_POST['continue_tool'])) {
				$is_continue = (bool) filter_var(wp_unslash($_POST['continue_tool']), FILTER_VALIDATE_BOOLEAN);
			}
			$continue_response_id = '';
			$continue_tool_calls = [];

			$message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
			$bot_slug = isset($_POST['bot_slug']) ? sanitize_title(wp_unslash($_POST['bot_slug'])) : '';
			// Session id sanitize (regex + length bound)
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via aichat_sanitize_session_id().
			$session = isset($_POST['session_id']) ? aichat_sanitize_session_id(wp_unslash($_POST['session_id'])) : '';
			if ($session === '') {
				$session = wp_generate_uuid4();
			}
			if ($is_continue) {
				// Segunda fase: no exigimos message, sí parámetros response_id + tool_calls
				$continue_response_id = isset($_POST['response_id']) ? sanitize_text_field(wp_unslash($_POST['response_id'])) : '';
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via wp_verify_nonce.
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload decoded and normalized below.
				$tool_calls_raw = isset($_POST['tool_calls']) ? wp_unslash($_POST['tool_calls']) : '';
				if (is_string($tool_calls_raw) && $tool_calls_raw !== '') {
					$decoded = json_decode($tool_calls_raw, true);
					if (is_array($decoded)) {
						$continue_tool_calls = $decoded;
					}
				} elseif (is_array($_POST['tool_calls'] ?? null)) {
					// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified above via wp_verify_nonce.
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Array payload normalized below.
					$continue_tool_calls = wp_unslash($_POST['tool_calls']);
				}
				// Normalizar estructura para evitar entradas inesperadas
				if (! empty($continue_tool_calls)) {
					$continue_tool_calls = array_values(array_filter(array_map(static function ($tc) {
						if (! is_array($tc)) {
							return null;
						}
						$call_id = isset($tc['call_id']) ? sanitize_text_field((string) $tc['call_id']) : '';
						$name = isset($tc['name']) ? sanitize_text_field((string) $tc['name']) : '';
						$args = $tc['args'] ?? ($tc['arguments'] ?? '{}');
						if (is_array($args)) {
							$args = wp_json_encode($args);
						}
						if (! is_string($args)) {
							$args = '{}';
						}

						return [
							'call_id' => $call_id,
							'id' => isset($tc['id']) ? sanitize_text_field((string) $tc['id']) : $call_id,
							'name' => $name,
							'args' => $args,
							'arguments' => $args,
						];
					}, $continue_tool_calls)));
				}
				if ($continue_response_id === '' || empty($continue_tool_calls)) {
					wp_send_json_error(['message' => 'Missing continuation parameters.'], 400);
				}
				aichat_log_debug("[AIChat AJAX][$uid] continuation init response_id={$continue_response_id} tool_calls=" . count($continue_tool_calls));
			} else {
				if ($message === '') {
					aichat_log_debug("[AIChat AJAX][$uid] empty message");
					wp_send_json_error(['message' => __('Message is empty.', 'axiachat-ai')], 400);
				}
			}
			aichat_log_debug("[AIChat AJAX][$uid] payload slug={$bot_slug} msg_len=" . strlen($message) . ($is_continue ? ' (continuation)' : ''));

			// ---- CAPTCHA (filtro opcional) ----
			$captcha_payload = [
				'bot_slug' => $bot_slug,
				'session_id' => $session,
				'message_len' => strlen($message),
			];
			$captcha_ok = apply_filters('aichat_validate_captcha', true, $captcha_payload);
			if (! $captcha_ok) {
				aichat_log_debug("[AIChat AJAX][$uid] captcha failed");
				wp_send_json_error(['message' => __('Captcha validation failed.', 'axiachat-ai')], 403);
			}

			// ---- Honeypot anti bots ----
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Honeypot value is used only for a strict empty-check.
			$hp = isset($_POST['aichat_hp']) ? (string) wp_unslash($_POST['aichat_hp']) : '';
			if ($hp !== '') { // Honeypot present -> block
				aichat_log_debug("[AIChat AJAX][$uid] honeypot filled");
				wp_send_json_error(['message' => __('Request blocked.', 'axiachat-ai')], 403);
			}

			// ---- Rate limiting & burst control ----
			if (function_exists('aichat_rate_limit_check')) {
				$rl = aichat_rate_limit_check($session, $bot_slug);
				if (is_wp_error($rl)) {
					aichat_log_debug("[AIChat AJAX][$uid] rate limit: " . $rl->get_error_code());
					wp_send_json_error(['message' => $rl->get_error_message()], 429);
				}
			}

			// ---- Longitud máxima dura ----
			$len = mb_strlen($message);
			if ($len > 4000) {
				aichat_log_debug("[AIChat AJAX][$uid] message too long len=$len");
				wp_send_json_error(['message' => __('Message too long.', 'axiachat-ai')], 400);
			}

			// ---- Firma / Patrón de spam básico ----
			if (function_exists('aichat_spam_signature_check')) {
				$sig = aichat_spam_signature_check($message);
				if (is_wp_error($sig) && $sig->get_error_code() !== 'aichat_empty') {
					$sig_code = $sig->get_error_code();
					aichat_log_debug("[AIChat AJAX][$uid] spam signature: " . $sig_code);

					// Si es un duplicado, no lo tratamos como un error duro.
					// Esto evita que un doble-submit (o un retry del navegador) rompa la UX con un "Blocked".
					if ($sig_code === 'aichat_dup') {
						wp_send_json_success([
							'message' => '',
							'duplicate' => true,
						]);
					}

					wp_send_json_error(['message' => __('Blocked.', 'axiachat-ai')], 400);
				}
			}

			// ---- Moderación temprana ----
			if (function_exists('aichat_run_moderation_checks')) {
				$mod_check = aichat_run_moderation_checks($message);
				if (is_wp_error($mod_check)) {
					$rej_msg = $mod_check->get_error_message();
					aichat_log_debug("[AIChat AJAX][$uid] moderation blocked: " . $rej_msg);
					if (function_exists('aichat_record_moderation_block')) {
						aichat_record_moderation_block($mod_check->get_error_code());
					}
					wp_send_json_success([
						'message' => $rej_msg,
						'moderated' => true,
					]);
				}
			}
			// ---- Fin moderación ----

			// 1) Resolver BOT
			$bot = $this->resolve_bot($bot_slug);
			if (! $bot) {
				aichat_log_debug("[AIChat AJAX][$uid] resolve_bot: NOT FOUND");
				wp_send_json_error(['message' => __('No bot found to process the request.', 'axiachat-ai')], 404);
			}

			// ---- Live Chat Pre-Process Check ----
			// If session is in livechat mode (waiting_agent or agent), block AI processing
			if (! $is_continue) {
				$pre_response = apply_filters('aichat_pre_process_message', null, [
					'session_id' => $session,
					'message' => $message,
					'bot_slug' => $bot_slug,
				]);

				// If filter returns a response array, send it and skip AI processing
				if (is_array($pre_response) && isset($pre_response['success'])) {
					aichat_log_debug("[AIChat AJAX][$uid] blocked by pre_process filter (livechat), session=$session");
					wp_send_json_success($pre_response);
				}
			}
			// ---- End Live Chat Pre-Process Check ----

			// Sanitiza/normaliza campos del bot
			$bot_id = isset($bot['id']) ? intval($bot['id']) : 0;
			$bot_name = isset($bot['name']) ? sanitize_text_field($bot['name']) : '';
			$bot_slug_r = isset($bot['slug']) ? sanitize_title($bot['slug']) : $bot_slug;

			$provider = ! empty($bot['provider']) ? sanitize_key($bot['provider']) : 'openai'; // 'openai' | 'claude'
			$model = ! empty($bot['model']) ? sanitize_text_field($bot['model']) : aichat_get_default_model($provider);

			/**
			 * Permite que un add-on (ej: Agency Connector) override el provider y modelo.
			 *
			 * @since 1.3.0
			 * @param array $override {
			 *     @type string $provider Provider a usar (ej: 'agency').
			 *     @type string $model    Modelo a usar.
			 *     @type array  $config   Configuración adicional para el provider.
			 * }
			 * @param array  $bot      Datos del bot.
			 * @param string $provider Provider original.
			 * @param string $model    Modelo original.
			 */
			$provider_override = apply_filters('aichat_provider_override', null, $bot, $provider, $model);
			if (is_array($provider_override)) {
				if (! empty($provider_override['provider'])) {
					$provider = sanitize_key($provider_override['provider']);
				}
				if (! empty($provider_override['model'])) {
					$model = sanitize_text_field($provider_override['model']);
				}
				aichat_log_debug("[AIChat AJAX][$uid] provider overridden to: {$provider}, model: {$model}");
			}
			$instructions = isset($bot['instructions']) ? wp_kses_post($bot['instructions']) : '';
			$temperature = isset($bot['temperature']) ? floatval($bot['temperature']) : 0.7;
			if ($temperature < 0) {
				$temperature = 0;
			}
			if ($temperature > 2) {
				$temperature = 2;
			}
			$max_tokens = isset($bot['max_tokens']) ? intval($bot['max_tokens']) : 512;
			if ($max_tokens <= 0) {
				$max_tokens = 512;
			}

			$context_mode = isset($bot['context_mode']) ? sanitize_key($bot['context_mode']) : 'auto'; // 'embeddings'|'none'|'page' (legacy)
			$context_id = isset($bot['context_id']) ? intval($bot['context_id']) : 0;

			aichat_log_debug("[AIChat AJAX][$uid] bot id={$bot_id} slug={$bot_slug_r} name={$bot_name} provider={$provider} model={$model} temp={$temperature} max_tokens={$max_tokens}");
			aichat_log_debug("[AIChat AJAX][$uid] context raw mode={$context_mode} context_id={$context_id}");

			// ---- Tool continuation: skip context/embedding entirely ----
			// Continuations carry no new user message; the context was already
			// resolved in the original request and persisted in tool state.
			// Running embeddings with empty text causes a 400 from providers.
			if ($is_continue) {
				aichat_log_debug("[AIChat AJAX][$uid] tool continuation — skipping context, jumping to process_tool_continuation");

				return $this->process_tool_continuation($bot_slug, $session, $continue_response_id, $continue_tool_calls, $uid);
			}

			// 2) Determinar modo de contexto efectivo
			$mode_arg = 'auto';
			if ($context_mode === 'none') {
				$mode_arg = 'none';
			}
			if ($context_mode === 'page') {
				$mode_arg = 'page';
			}

			// page_id llega del frontend para soportar contexto “contenido de la página” en admin-ajax
			$page_id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;

			$t_ctx0 = microtime(true);
			$context_limit = isset($bot['context_limit']) ? max(3, min(10, intval($bot['context_limit']))) : 5;
			$contexts = aichat_get_context_for_question($message, [
				'context_id' => $context_id,
				'mode' => $mode_arg, // auto|none|page (auto decide local|pinecone según fila)
				'page_id' => $page_id,
				'limit' => $context_limit,
				'provider' => $provider,
			]);
			$t_ctx1 = microtime(true);

			$ctx_count = is_array($contexts) ? count($contexts) : 0;
			$ctx_peek = [];
			if ($ctx_count) {
				$i = 0;
				foreach ($contexts as $c) {
					$ctx_peek[] = [
						'title' => isset($c['title']) ? mb_substr($c['title'], 0, 120) : '',
						'score' => isset($c['score']) ? round(floatval($c['score']), 5) : null,
						'type' => isset($c['type']) ? $c['type'] : '',
						'post' => isset($c['post_id']) ? (string) $c['post_id'] : '',
					];
					if (++$i >= 3) {
						break;
					}
				}
			}
			aichat_log_debug("[AIChat AJAX][$uid] context mode_eff={$mode_arg} count={$ctx_count} time_ms=" . round(($t_ctx1 - $t_ctx0) * 1000) . ' top=' . wp_json_encode($ctx_peek));

			// 3) Construir mensajes (system + historial + user actual) para primera fase
			// Base (system + user actual con CONTEXTO)
			$base = aichat_build_messages($message, $contexts, $instructions, null, [
				'bot_name' => $bot_name,
				'bot_slug' => $bot_slug_r,
				'suggestions_enabled' => ! empty($bot['ui_suggestions_enabled']),
				'suggestions_count' => isset($bot['ui_suggestions_count']) ? (int) $bot['ui_suggestions_count'] : 3,
			]);
			$system_msg = isset($base[0]) ? $base[0] : ['role' => 'system', 'content' => ''];
			$current_user_msg = isset($base[1]) ? $base[1] : ['role' => 'user',   'content' => (string) $message];

			// Historial previo por sesión+bot, limitado por configuración
			$max_messages_hist = isset($bot['max_messages']) ? max(1, (int) $bot['max_messages']) : 20;
			$context_max_length = isset($bot['context_max_length']) ? max(128, (int) $bot['context_max_length']) : 4096;
			$history_msgs = $this->build_history_messages($session, $bot_slug_r, $max_messages_hist, $context_max_length);

			// Mensajes finales: system + historial + user actual
			$messages = array_merge([$system_msg], $history_msgs, [$current_user_msg]);

			// HOOK: modificar mensajes antes de provider en flujo interno
			if (function_exists('apply_filters')) {
				$messages = apply_filters('aichat_messages_before_provider', $messages, [
					'bot' => $bot,
					'bot_slug' => $bot['slug'],
					'session_id' => $session,
					'question' => $message,
					'contexts' => $contexts,
					'provider' => $provider,
					'model' => $model,
					'internal' => true,
				]);
			}

			// HOOK: interceptar petición interna
			$intercept = ['abort' => false, 'immediate_response' => null, 'meta' => []];
			if (function_exists('apply_filters')) {
				$intercept = apply_filters('aichat_maybe_intercept_request', $intercept, [
					'bot' => $bot,
					'bot_slug' => $bot['slug'],
					'session_id' => $session,
					'question' => $message,
					'messages' => $messages,
					'contexts' => $contexts,
					'provider' => $provider,
					'model' => $model,
					'page_id' => $page_id,
					'internal' => true,
				]);
			}
			if (! empty($intercept['abort'])) {
				$answer = isset($intercept['immediate_response']) ? (string) $intercept['immediate_response'] : __('No response available.', 'axiachat-ai');
				$suggestions = $this->extract_suggestions_from_answer($answer, $bot);
				// Renderizar Markdown (si procede) antes de reemplazos HTML
				$answer = $this->maybe_render_markdown_answer($answer);
				// Reemplazo [LINK] y embellecer enlaces conocidos antes de sanear
				$answer = aichat_replace_link_placeholder($answer);
				if (function_exists('aichat_pretty_known_links')) {
					$answer = aichat_pretty_known_links($answer);
				}
				$answer = $this->sanitize_answer_html($answer);
				if (get_option('aichat_logging_enabled', 1)) {
					$this->maybe_log_conversation(get_current_user_id(), $session, $bot['slug'], $page_id, $message, $answer, $model, $provider, null, null, null, null);
				}
				do_action('aichat_after_response', [
					'bot_slug' => $bot['slug'],
					'session_id' => $session,
					'question' => $message,
					'answer' => $answer,
					'provider' => $provider,
					'model' => $model,
					'internal' => true,
					'intercepted' => true,
				]);

				return [
					'message' => $answer,
					'bot_slug' => $bot['slug'],
					'session_id' => $session,
					'provider' => $provider,
					'model' => $model,
					'intercepted' => true,
					'suggestions' => $suggestions,
				];
			}

			// Métricas de depuración
			$sys_len = 0;
			$usr_len = 0;
			foreach ($messages as $m) {
				if ($m['role'] === 'system') {
					$sys_len += mb_strlen((string) $m['content']);
				}
				if ($m['role'] === 'user') {
					$usr_len += mb_strlen((string) $m['content']);
				}
			}
			aichat_log_debug("[AIChat AJAX][$uid] messages built sys_len={$sys_len} user_len={$usr_len}");

			// 4) Claves API (use aichat_get_setting which transparently decrypts if stored encrypted)
			$openai_key = aichat_get_setting('aichat_openai_api_key');
			$claude_key = aichat_get_setting('aichat_claude_api_key');

			// Normalizar alias de proveedor
			if ($provider === 'anthropic') {
				$provider = 'claude';
			}

			// Normalizar modelo Claude (alias → versión fechada conocida)
			if ($provider === 'claude') {
				$model = $this->normalize_claude_model($model);
			}

			$agency_enabled = (function_exists('aichat_agency_is_configured') && aichat_agency_is_configured())
				|| (bool) get_option('aichat_agency_enabled', false);
			if ($provider === 'openai' && empty($openai_key) && ! $agency_enabled) {
				aichat_log_debug("[AIChat AJAX][$uid] ERROR: OpenAI key missing");
				wp_send_json_error(['message' => __('Missing OpenAI API Key in settings.', 'axiachat-ai')], 400);
			}
			if ($provider === 'claude' && empty($claude_key) && ! $agency_enabled) {
				aichat_log_debug("[AIChat AJAX][$uid] ERROR: Claude key missing");
				wp_send_json_error(['message' => __('Missing Claude API Key in settings.', 'axiachat-ai')], 400);
			}

			// === HOOK: Permitir modificación de mensajes antes de llamar al proveedor ===
			if (function_exists('apply_filters')) {
				$state_before = [
					'bot' => $bot,
					'bot_slug' => $bot_slug_r,
					'session_id' => $session,
					'question' => $message,
					'contexts' => $contexts,
					'page_id' => $page_id,
					'provider' => $provider,
					'model' => $model,
				];
				$messages = apply_filters('aichat_messages_before_provider', $messages, $state_before);
			}

			// === HOOK: Posible interceptación (evitar llamada a modelo) ===
			$intercept = ['abort' => false, 'immediate_response' => null, 'meta' => []];
			if (function_exists('apply_filters')) {
				$intercept = apply_filters('aichat_maybe_intercept_request', $intercept, [
					'bot' => $bot,
					'bot_slug' => $bot_slug_r,
					'session_id' => $session,
					'question' => $message,
					'messages' => $messages,
					'contexts' => $contexts,
					'provider' => $provider,
					'model' => $model,
					'page_id' => $page_id,
				]);
			}
			if (! empty($intercept['abort'])) {
				$answer = isset($intercept['immediate_response']) ? (string) $intercept['immediate_response'] : '';
				if ($answer === '') {
					$answer = __('No response available.', 'axiachat-ai');
				}
				$suggestions = $this->extract_suggestions_from_answer($answer, $bot);
				// Sanitizar y log opcional
				$answer = $this->maybe_render_markdown_answer($answer);
				$answer = aichat_replace_link_placeholder($answer);
				if (function_exists('aichat_pretty_known_links')) {
					$answer = aichat_pretty_known_links($answer);
				}
				$answer = $this->sanitize_answer_html($answer);
				if (get_option('aichat_logging_enabled', 1)) {
					$this->maybe_log_conversation(get_current_user_id(), $session, $bot_slug, $page_id, $message, $answer, $model, $provider, null, null, null, null);
				}
				// Hook post-respuesta para intercept también
				do_action('aichat_after_response', [
					'bot_slug' => $bot_slug,
					'session_id' => $session,
					'question' => $message,
					'answer' => $answer,
					'provider' => $provider,
					'model' => $model,
					'intercepted' => true,
				]);
				wp_send_json_success(['message' => $answer, 'intercepted' => true, 'suggestions' => $suggestions]);
			}

			// 5) Llamar al proveedor
			// Pretty log: request summary (system, prompt, tools) before calling provider
			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				$pretty_user_prompt = $this->extract_question_from_user_message($current_user_msg['content'] ?? '', $message);
				$tools_list = $this->extract_tool_names_for_log(isset($active_tools) ? $active_tools : []);
				$hist_pairs = (int) floor(count($history_msgs) / 2);
				$req_pretty = $this->format_pretty_request_log([
					'uid' => $uid,
					'provider' => $provider,
					'model' => $model,
					'temperature' => $temperature,
					'max_tokens' => $max_tokens,
					'mode' => $mode_arg,
					'context_count' => $ctx_count,
					'history_pairs' => $hist_pairs,
					'messages_total' => count($messages),
					'system' => (string) ($system_msg['content'] ?? ''),
					'prompt' => $pretty_user_prompt,
					'tools' => $tools_list,
				]);
				aichat_log_debug($req_pretty, [], true);
				// Optional full SYSTEM dump without truncation for diagnostics
				if (defined('AICHAT_DEBUG_LOG_SYSTEM_FULL') && (bool) constant('AICHAT_DEBUG_LOG_SYSTEM_FULL')) {
					$sys_full = (string) ($system_msg['content'] ?? '');
					aichat_log_debug("[AIChat SYSTEM FULL][$uid]\n" . $sys_full, [], true);
				}
			}
			// === USAGE LIMITS (antes de llamar al proveedor) ===
			if (get_option('aichat_usage_limits_enabled', 1)) {
				global $wpdb;
				$conv_table = $wpdb->prefix . 'aichat_conversations';
				$today_start = gmdate('Y-m-d 00:00:00');
				$today_end = gmdate('Y-m-d 23:59:59');

				$max_total = (int) get_option('aichat_usage_max_daily_total', 0); // 0 = sin límite
				$max_per_user = (int) get_option('aichat_usage_max_daily_per_user', 0);
				$msg_user = trim((string) get_option('aichat_usage_per_user_message', ''));
				$beh_total = get_option('aichat_usage_daily_total_behavior', 'disabled'); // disabled|hidden
				$msg_total = trim((string) get_option('aichat_usage_daily_total_message', ''));

				// Identificador de usuario: preferimos user_id; si 0, usamos hash de IP (no almacenamos hash extra, sólo para comparación en query)
				$user_id_real = get_current_user_id();
				$ip_for_filter = null;
				$packed_ip = null;
				if ($user_id_real === 0) {
					// Intentar obtener ip ya calculable igual que en maybe_log_conversation (placeholder: lógica previa eliminada)
					// Mantener variables $ip_for_filter y $packed_ip en null para conteos por usuario.
				}

				// Comprobar límite global (total)
				if ($max_total > 0) {
					// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $conv_table is a trusted plugin table name via $wpdb->prefix.
					$count_total = (int) $wpdb->get_var(
						$wpdb->prepare(
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $conv_table is a trusted plugin table name.
							"SELECT COUNT(*) FROM {$conv_table} WHERE created_at BETWEEN %s AND %s",
							$today_start,
							$today_end
						)
					);
					if ($count_total >= $max_total) {
						// Según comportamiento: devolvemos mensaje (disabled) o lo tratamos como oculto
						aichat_log_debug("[AIChat AJAX][$uid] global limit reached count=$count_total max=$max_total behavior=$beh_total");
						if ($beh_total === 'hidden') {
							// Simulamos recurso no disponible
							wp_send_json_error(['message' => __('Chat temporarily unavailable.', 'axiachat-ai'), 'limit_type' => 'daily_total_hidden'], 403);
						} else {
							$limit_msg_total = $msg_total !== '' ? $msg_total : __('Daily total message limit reached.', 'axiachat-ai');
							wp_send_json_success(['message' => $limit_msg_total, 'limited' => true, 'limit_type' => 'daily_total']);
						}
					}
				}
			}
			// === FIN USAGE LIMITS ===

			$t_call0 = microtime(true);
			// === Function Calling (Tools) Phase 1 ===
			// FIXED: Support tools for ALL providers (OpenAI + Claude)
			$active_tools = [];
			if (! empty($bot['tools_json'])) {
				$raw_selected = json_decode((string) $bot['tools_json'], true);
				if (is_array($raw_selected)) {
					// DEBUG: Log raw selected
					if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
						aichat_log_debug('[AIChat Tools] Raw selected from bot', [
							'bot_slug' => $bot_slug_r,
							'provider' => $provider,
							'raw_selected' => $raw_selected,
							'count' => count($raw_selected),
						], true);
					}

					// Expand macros → atomic tool names
					if (function_exists('aichat_expand_macros_to_atomic')) {
						$expanded = aichat_expand_macros_to_atomic($raw_selected);
					} else {
						$expanded = $raw_selected; // fallback
					}

					// DEBUG: Log expanded tools
					if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
						aichat_log_debug('[AIChat Tools] Expanded atomic tools', [
							'expanded' => $expanded,
							'count' => count($expanded),
						], true);
					}

					$registered = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];

					// DEBUG: Log registered tools count
					if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
						aichat_log_debug('[AIChat Tools] Registered tools available', [
							'count' => count($registered),
							'tool_names' => array_keys($registered),
						], true);
					}

					foreach ($expanded as $tid) {
						if (isset($registered[$tid])) {
							$def = $registered[$tid];
							if (($def['type'] ?? '') === 'function') {
								$active_tools[] = [
									'type' => 'function',
									'function' => [
										'name' => $def['name'] ?? $tid,
										'description' => $def['description'] ?? '',
										'strict' => ! empty($def['strict']),
										'parameters' => $def['schema'] ?? ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false],
									],
								];
							}
						} else {
							// DEBUG: Tool not found in registry
							if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
								aichat_log_debug('[AIChat Tools] Tool NOT found in registry', [
									'tool_id' => $tid,
								], true);
							}
						}
					}
				}
			}

			// DEBUG: Log final active tools
			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				aichat_log_debug('[AIChat Tools] Final active_tools for API', [
					'count' => count($active_tools),
					'tool_names' => array_map(function ($t) {
						return $t['function']['name'] ?? '?';
					}, $active_tools),
				], true);
			}

			// Log incondicional de tools activas
			$tool_names_for_log = array_map(function ($t) {
				return $t['function']['name'] ?? '?';
			}, $active_tools);
			aichat_log_debug("[AIChat AJAX][$uid] tools_sent=" . count($active_tools) . ' names=[' . implode(',', $tool_names_for_log) . ']', [], true);

			// Generar request_uuid para trazar tool calls (se reutiliza al vincular conversation_id)
			$request_uuid = wp_generate_uuid4();

			// ===== PROVIDER ARCHITECTURE: Registry + Adapters =====
			aichat_log_debug("[AIChat AJAX][$uid] using provider registry architecture", [], true);

			// Mapear provider legacy 'anthropic' a 'claude' para registry
			$registry_provider = ($provider === 'anthropic') ? 'claude' : $provider;

			// Obtener provider instance del registry
			$registry = AIChat_Provider_Registry::instance();

			// Preparar configuración según provider
			// Si hay override con config personalizada, usarla
			if (isset($provider_override['config']) && is_array($provider_override['config'])) {
				$provider_config = $provider_override['config'];
				aichat_log_debug("[AIChat AJAX][$uid] using provider config from override");
			} elseif ($provider === 'openai') {
				$provider_config = ['api_key' => $openai_key];
				$org = aichat_get_setting('aichat_openai_organization');
				if (! empty($org)) {
					$provider_config['organization'] = $org;
				}
			} elseif ($provider === 'claude') {
				$provider_config = ['api_key' => $claude_key];
			} elseif ($provider === 'gemini') {
				$gemini_key = aichat_get_setting('aichat_gemini_api_key');
				$provider_config = ['api_key' => $gemini_key];
			} else {
				$provider_config = [];
			}

			try {
				$provider_instance = $registry->get($registry_provider, $provider_config);
			} catch (Exception $e) {
				aichat_log_debug("[AIChat AJAX][$uid] Registry ERROR: " . $e->getMessage(), [], true);
				wp_send_json_error(['message' => __('Provider initialization failed.', 'axiachat-ai')], 500);
			}

			// Preparar parámetros de llamada
			$call_params = [
				'model' => $model,
				'temperature' => $temperature,
				'max_tokens' => $max_tokens,
				'bot_id' => $bot_id,
				'conversation_id' => 0,  // TODO: Obtener de session storage
				'request_uuid' => $request_uuid,
				'session_id' => $session,
				'bot_slug' => $bot_slug_r,
			];

			// Pasar tools a cualquier provider que los soporte
			if (! empty($active_tools)) {
				$call_params['tools'] = $active_tools;
			}

			// Llamar al provider via adapter
			$result = $provider_instance->chat($messages, $call_params);

			// Si OpenAI Responses devolvió tool_pending, reenviar al frontend
			if (is_array($result) && isset($result['status']) && $result['status'] === 'tool_pending') {
				// Log incondicional: qué tool eligió el modelo
				$pending_names = array_map(function ($tc) {
					return ($tc['name'] ?? '?');
				}, $result['tool_calls'] ?? []);
				aichat_log_debug("[AIChat AJAX][$uid] tool_pending from model: [" . implode(',', $pending_names) . '] response_id=' . ($result['response_id'] ?? '?'), [], true);
				// Extraer y registrar usage antes de devolver respuesta pendiente
				$prompt_tokens_pending = null;
				$completion_tokens_pending = null;
				$total_tokens_pending = null;
				$cost_micros_pending = null;

				if (isset($result['usage']) && is_array($result['usage'])) {
					$prompt_tokens_pending = isset($result['usage']['prompt_tokens']) ? (int) $result['usage']['prompt_tokens'] : null;
					$completion_tokens_pending = isset($result['usage']['completion_tokens']) ? (int) $result['usage']['completion_tokens'] : null;
					$total_tokens_pending = isset($result['usage']['total_tokens']) ? (int) $result['usage']['total_tokens'] : (($prompt_tokens_pending !== null && $completion_tokens_pending !== null) ? $prompt_tokens_pending + $completion_tokens_pending : null);

					if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
						aichat_log_debug('[AIChat AJAX][' . $uid . '] tool_pending usage tokens', [
							'prompt' => $prompt_tokens_pending,
							'completion' => $completion_tokens_pending,
							'total' => $total_tokens_pending,
							'model' => $model,
							'provider' => $provider,
						], true);
					}

					if (function_exists('aichat_calc_cost_micros') && $prompt_tokens_pending !== null) {
						$cost_micros_pending = aichat_calc_cost_micros($provider, $model, $prompt_tokens_pending, $completion_tokens_pending ?: 0);
					}

					// Registrar uso en tabla diaria
					if (function_exists('aichat_update_daily_usage_row') && $model && $provider && $total_tokens_pending !== null) {
						$pt = ($prompt_tokens_pending !== null) ? (int) $prompt_tokens_pending : 0;
						$ct = ($completion_tokens_pending !== null) ? (int) $completion_tokens_pending : 0;
						if ($pt === 0 && $ct === 0) {
							$pt = (int) $total_tokens_pending;
						}
						aichat_update_daily_usage_row($provider, $model, $pt, $ct, (int) $total_tokens_pending, (int) $cost_micros_pending);
					}
				}

				$out = [
					'status' => 'tool_pending',
					'response_id' => $result['response_id'] ?? '',
					'tool_calls' => $result['tool_calls'] ?? [],
					'request_uuid' => $uid,
					'session_id' => $session,
					'bot_slug' => $bot_slug_r,
					'model' => $model,
				];

				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug("[AIChat AJAX][$uid] tool_pending handshake", [
						'response_id' => $result['response_id'] ?? '-',
						'tool_count' => is_array($result['tool_calls'] ?? null) ? count($result['tool_calls']) : 0,
						'usage_registered' => $total_tokens_pending !== null,
					], true);
				}

				// Guardar conversación con marcador de pendiente
				if (get_option('aichat_logging_enabled', 1)) {
					$placeholder_resp = '(executing tools…)';
					$this->maybe_log_conversation(get_current_user_id(), $session, $bot_slug_r, $page_id, $message, $placeholder_resp, $model, $provider, $prompt_tokens_pending, $completion_tokens_pending, $total_tokens_pending, $cost_micros_pending);
				}

				wp_send_json_success($out);
			}

			// Manejar tool calls si es OpenAI Chat Completions (multi-ronda simple)
			if ($provider === 'openai' && ! empty($active_tools) && is_array($result) && !empty($result['tool_calls'])) {
				// TODO: Implementar multi-ronda con tools en PASO 4
				// Por ahora, simplemente tomamos la primera respuesta
				aichat_log_debug("[AIChat AJAX][$uid] tool_calls detected but multi-round not yet implemented", [], true);
			}

			// Validar resultado
			if (isset($result['error'])) {
				aichat_log_debug("[AIChat AJAX][$uid] provider error: " . $result['error'], [], true);
				wp_send_json_error(['message' => $result['error']], 500);
			}

			$answer = $result['message'] ?? '';

			if ($answer === '') {
				aichat_log_debug("[AIChat AJAX][$uid] ERROR: empty answer", [], true);
				wp_send_json_error(['message' => __('Model returned an empty response.', 'axiachat-ai')], 500);
			}

			// Pretty log de respuesta
			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				$pretty_resp = $this->format_pretty_response_log([
					'uid' => $uid,
					'provider' => $provider,
					'model' => $result['model'] ?? $model,
					'answer' => $answer,
					'usage' => $result['usage'] ?? [],
					'timings_ms' => [],
				]);
				aichat_log_debug($pretty_resp, [], true);
			}

			$t_call1 = microtime(true);

			if (is_wp_error($result)) {
				if (is_object($result) && method_exists($result, 'get_error_message')) {
					$error_message = $result->get_error_message();
				} elseif (is_array($result) && isset($result['error'])) {
					$error_message = (string) $result['error'];
				} else {
					$error_message = __('Unknown error occurred.', 'axiachat-ai');
				}
				aichat_log_debug("[AIChat AJAX][$uid] provider WP_Error: " . $error_message, [], true);
				wp_send_json_error(['message' => $error_message], 500);
			}
			if (is_array($result) && isset($result['error'])) {
				aichat_log_debug("[AIChat AJAX][$uid] provider error: " . $result['error'], [], true);
				wp_send_json_error(['message' => (string) $result['error']], 500);
			}

			$answer = is_array($result) && isset($result['message']) ? (string) $result['message'] : '';
			$ans_flat = str_replace(["\n", "\r"], ' ', $answer);
			aichat_log_debug("[AIChat AJAX][$uid] raw answer len=" . mb_strlen($answer) . ' time_ms=' . round(($t_call1 - $t_call0) * 1000) . ' full=' . $ans_flat, [], true);

			if ($answer === '') {
				aichat_log_debug("[AIChat AJAX][$uid] ERROR: empty answer", [], true);
				wp_send_json_error(['message' => __('Model returned an empty response.', 'axiachat-ai')], 500);
			}

			// 6) Extraer sugerencias (si vienen) antes de renderizar Markdown/HTML
			$suggestions = $this->extract_suggestions_from_answer($answer, $bot);

			// 6.1) Markdown -> HTML (si procede), luego reemplazo [LINK] y embellecer enlaces conocidos
			$answer = $this->maybe_render_markdown_answer($answer);
			$answer = aichat_replace_link_placeholder($answer);
			if (function_exists('aichat_pretty_known_links')) {
				$answer = aichat_pretty_known_links($answer);
			}

			// 6.2) Sanitizar HTML permitido (permitimos <a>, <strong>, <em>, listas, etc.)
			$answer = $this->sanitize_answer_html($answer);

			// Extraer usage si viene (token counts) y log debug
			$prompt_tokens = null;
			$completion_tokens = null;
			$total_tokens = null;
			$cost_micros = null;
			if (is_array($result) && isset($result['usage']) && is_array($result['usage'])) {
				$prompt_tokens = isset($result['usage']['prompt_tokens']) ? (int) $result['usage']['prompt_tokens'] : null;
				$completion_tokens = isset($result['usage']['completion_tokens']) ? (int) $result['usage']['completion_tokens'] : null;
				$total_tokens = isset($result['usage']['total_tokens']) ? (int) $result['usage']['total_tokens'] : (($prompt_tokens !== null && $completion_tokens !== null) ? $prompt_tokens + $completion_tokens : null);
				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug('[AIChat AJAX][' . $uid . '] usage tokens', [
						'prompt' => $prompt_tokens,
						'completion' => $completion_tokens,
						'total' => $total_tokens,
						'model' => $model,
						'provider' => $provider,
					], true);
				}
				if (function_exists('aichat_calc_cost_micros') && $prompt_tokens !== null) {
					$cost_micros = aichat_calc_cost_micros($provider, $model, $prompt_tokens, $completion_tokens ?: 0);
				}
			} else {
				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug('[AIChat AJAX][' . $uid . '] usage tokens not present', ['model' => $model, 'provider' => $provider], true);
				}
			}

			// === HOOK: Permitir modificar la respuesta antes de persistir ===
			if (function_exists('apply_filters')) {
				$answer = apply_filters('aichat_response_pre_persist', $answer, [
					'bot' => $bot,
					'bot_slug' => $bot_slug_r,
					'session_id' => $session,
					'question' => $message,
					'contexts' => $contexts,
					'provider' => $provider,
					'model' => $model,
					'messages' => $messages,
					'usage' => [
						'prompt_tokens' => $prompt_tokens,
						'completion_tokens' => $completion_tokens,
						'total_tokens' => $total_tokens,
						'cost_micros' => $cost_micros,
					],
				]);
			}

			// 7) Guardar conversación con tokens/coste si procede
			// Pasar request_uuid (si existe) para vincular con tool_calls registrados en esta request
			if (!isset($_REQUEST['aichat_request_uuid']) && isset($request_uuid) && is_string($request_uuid) && preg_match('/^[a-f0-9-]{36}$/i', $request_uuid)) {
				$_REQUEST['aichat_request_uuid'] = $request_uuid;
			}
			if (get_option('aichat_logging_enabled', 1)) {
				$this->maybe_log_conversation(get_current_user_id(), $session, $bot_slug, $page_id, $message, $answer, $model, $provider, $prompt_tokens, $completion_tokens, $total_tokens, $cost_micros);
			}

			// Pretty log: response summary (preview, usage, timings)
			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				$tool_calls_count = 0;
				if (is_array($result) && isset($result['tool_calls']) && is_array($result['tool_calls'])) {
					$tool_calls_count = count($result['tool_calls']);
				}
				$pretty_resp = $this->format_pretty_response_log([
					'uid' => $uid,
					'provider' => $provider,
					'model' => $model,
					'answer' => $answer,
					'tool_calls' => $tool_calls_count,
					'usage' => is_array($result['usage'] ?? null) ? $result['usage'] : [],
					'timings_ms' => [
						'context' => round(($t_ctx1 - $t_ctx0) * 1000),
						'provider' => round(($t_call1 - $t_call0) * 1000),
						'total' => round((microtime(true) - $t0) * 1000),
					],
				]);
				aichat_log_debug($pretty_resp, [], true);
			}

			// 8) Respuesta (con debug opcional)
			// Construir respuesta
			$resp = ['message' => $answer, 'suggestions' => $suggestions];

			// Aplicar filtro para permitir que add-ons inyecten datos (ej: livechat_status)
			$resp = apply_filters('aichat_response_data', $resp);

			if ($debug) {
				$resp['debug'] = [
					'uid' => $uid,
					'bot' => ['id' => $bot_id, 'slug' => $bot_slug_r, 'name' => $bot_name],
					'provider' => $provider,
					'model' => $model,
					'temperature' => $temperature,
					'max_tokens' => $max_tokens,
					'context_mode' => $context_mode,
					'context_id' => $context_id,
					'mode_effect' => $mode_arg,
					'ctx_count' => $ctx_count,
					'ctx_top3' => $ctx_peek,
					'sys_len' => $sys_len,
					'user_len' => $usr_len,
					'timings_ms' => [
						'context' => round(($t_ctx1 - $t_ctx0) * 1000),
						'provider' => round(($t_call1 - $t_call0) * 1000),
						'total' => round((microtime(true) - $t0) * 1000),
					],
				];
				@header('X-AIChat-Bot: ' . $bot_slug_r);
				@header('X-AIChat-Provider: ' . $provider);
				@header('X-AIChat-Model: ' . $model);
				@header('X-AIChat-Context-Count: ' . $ctx_count);
				@header('X-AIChat-Mode: ' . $mode_arg);
			}

			// Hook after response
			do_action('aichat_after_response', [
			'bot_slug' => $bot_slug,
			'session_id' => $session,
			'question' => $message,
			'answer' => $answer,
			'provider' => $provider,
			'model' => $model,
			]);

			wp_send_json_success($resp);
		}

		/**
		 * Segunda fase: ejecutar tools solicitadas por Responses (handshake tool_pending) y obtener respuesta final.
		 */
		protected function process_tool_continuation($bot_slug, $session, $response_id, $tool_calls, $uid)
		{
			aichat_log_debug("[AIChat AJAX][$uid] process_tool_continuation entry | provider_pending response_id={$response_id} tool_count=" . count($tool_calls));

			$bot = $this->resolve_bot($bot_slug);
			if (! $bot) {
				aichat_log_debug("[AIChat AJAX][$uid] process_tool_continuation: bot not found slug={$bot_slug}");
				wp_send_json_error(['message' => 'Bot not found.'], 404);
			}

			$provider = ! empty($bot['provider']) ? sanitize_key($bot['provider']) : 'openai';
			$model = ! empty($bot['model']) ? sanitize_text_field($bot['model']) : 'gpt-4o-mini';

			// Obtener API keys (igual que en process_message)
			$openai_key = aichat_get_setting('aichat_openai_api_key');
			$claude_key = aichat_get_setting('aichat_claude_api_key');

			// NUEVA ARQUITECTURA: Detectar provider y llamar al método correspondiente
			// Gemini y Claude SIEMPRE requieren la nueva arquitectura (no tienen legacy path).
			$use_new_architecture = (bool) get_option('aichat_use_provider_architecture', 0);
			if (in_array($provider, ['gemini', 'claude', 'anthropic'], true)) {
				$use_new_architecture = true;
			}
			aichat_log_debug("[AIChat AJAX][$uid] process_tool_continuation: provider={$provider} model={$model} new_arch=" . ($use_new_architecture ? '1' : '0'));

			if ($use_new_architecture) {
				// Mapear provider legacy 'anthropic' a 'claude' para registry
				$registry_provider = ($provider === 'anthropic') ? 'claude' : $provider;

				// Preparar configuración según provider
				if ($provider === 'openai') {
					$provider_config = ['api_key' => $openai_key];
					$org = aichat_get_setting('aichat_openai_organization');
					if (! empty($org)) {
						$provider_config['organization'] = $org;
					}
				} elseif ($provider === 'anthropic' || $provider === 'claude') {
					$provider_config = ['api_key' => $claude_key];
				} elseif ($provider === 'gemini') {
					$gemini_key = aichat_get_setting('aichat_gemini_api_key');
					$provider_config = ['api_key' => $gemini_key];
				} else {
					$provider_config = [];
				}

				// Obtener provider instance
				$registry = AIChat_Provider_Registry::instance();
				$provider_instance = $registry->get($registry_provider, $provider_config);

				if (! $provider_instance) {
					aichat_log_debug("[AIChat AJAX][$uid] process_tool_continuation: provider instance NULL for {$registry_provider}");
					wp_send_json_error(['message' => "Provider {$provider} not available."], 500);
				}

				aichat_log_debug("[AIChat AJAX][$uid] tool continuation via registry", [
					'provider' => $provider,
					'registry_provider' => $registry_provider,
					'response_id' => $response_id,
					'tool_count' => count($tool_calls),
				], true);

				// Claude/Anthropic usa continue_from_tool_pending
				if ($provider === 'claude' || $provider === 'anthropic') {
					if (! method_exists($provider_instance, 'continue_from_tool_pending')) {
						wp_send_json_error(['message' => 'Claude provider does not support tool continuation.'], 500);
					}

					$result = $provider_instance->continue_from_tool_pending($response_id, $tool_calls);

					if (isset($result['error'])) {
						aichat_log_debug("[AIChat AJAX][$uid] Claude continuation error: " . $result['error'], [], true);
						wp_send_json_error(['message' => $result['error']], 500);
					}

					$answer = $result['message'] ?? '';

					// Fallback if provider returns empty after tool execution
					if ($answer === '') {
						aichat_log_debug("[AIChat AJAX][$uid] Claude continuation: empty answer after tool execution, using fallback");
						$answer = __('Done! The action has been completed successfully.', 'axiachat-ai');
					}

					$usage = $result['usage'] ?? null;
					$prompt_tokens_final = null;
					$completion_tokens_final = null;
					$total_tokens_final = null;
					$cost_micros_final = null;

					if (is_array($usage)) {
						$prompt_tokens_final = isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null;
						$completion_tokens_final = isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null;
						$total_tokens_final = isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : (($prompt_tokens_final !== null && $completion_tokens_final !== null) ? $prompt_tokens_final + $completion_tokens_final : null);

						if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
							aichat_log_debug('[AIChat AJAX][' . $uid . '] Claude final response usage', [
								'prompt' => $prompt_tokens_final,
								'completion' => $completion_tokens_final,
								'total' => $total_tokens_final,
								'model' => $model,
								'provider' => $provider,
							], true);
						}

						if (function_exists('aichat_calc_cost_micros') && $prompt_tokens_final !== null) {
							$cost_micros_final = aichat_calc_cost_micros($provider, $model, $prompt_tokens_final, $completion_tokens_final ?: 0);
						}

						// Registrar uso en tabla diaria
						if (function_exists('aichat_update_daily_usage_row') && $model && $provider && $total_tokens_final !== null) {
							$pt = ($prompt_tokens_final !== null) ? (int) $prompt_tokens_final : 0;
							$ct = ($completion_tokens_final !== null) ? (int) $completion_tokens_final : 0;
							if ($pt === 0 && $ct === 0) {
								$pt = (int) $total_tokens_final;
							}
							aichat_update_daily_usage_row($provider, $model, $pt, $ct, (int) $total_tokens_final, (int) $cost_micros_final);
						}
					}

					// Extraer sugerencias ANTES de renderizar Markdown/HTML
					$suggestions = $this->extract_suggestions_from_answer($answer, $bot);

					// Post-procesar respuesta (igual que flujo principal)
					$answer = $this->maybe_render_markdown_answer($answer);
					$answer = aichat_replace_link_placeholder($answer);
					if (function_exists('aichat_pretty_known_links')) {
						$answer = aichat_pretty_known_links($answer);
					}
					$answer = $this->sanitize_answer_html($answer);

					// Actualizar conversación con respuesta final (igual que OpenAI Responses)
					if (get_option('aichat_logging_enabled', 1)) {
						// Obtener request_uuid del response_id (Claude usa mismo formato)
						$request_uuid = $response_id;
						$this->update_last_conversation_response($session, $bot_slug, $answer, $request_uuid);
					}

					// Hook after response
					do_action('aichat_after_response', [
						'bot_slug' => $bot_slug,
						'session_id' => $session,
						'question' => null,
						'answer' => $answer,
						'provider' => 'claude',
						'model' => $model,
					]);

					// Devolver respuesta final
					$resp = [
						'status' => 'final',
						'message' => $answer,
						'suggestions' => $suggestions,
						'model' => $result['model'] ?? $model,
						'provider' => 'claude',
						'usage' => $usage,
						'session_id' => $session,
						'bot_slug' => $bot_slug,
					];
					// Aplicar filtro para add-ons (ej: livechat_status)
					$resp = apply_filters('aichat_response_data', $resp);
					wp_send_json_success($resp);
				}

				// Gemini usa el mismo patrón continue_from_tool_pending
				if ($provider === 'gemini') {
					if (! method_exists($provider_instance, 'continue_from_tool_pending')) {
						wp_send_json_error(['message' => 'Gemini provider does not support tool continuation.'], 500);
					}

					$result = $provider_instance->continue_from_tool_pending($response_id, $tool_calls);

					if (isset($result['error'])) {
						aichat_log_debug("[AIChat AJAX][$uid] Gemini continuation error: " . $result['error'], [], true);
						wp_send_json_error(['message' => $result['error']], 500);
					}

					$answer = $result['message'] ?? '';

					// Gemini sometimes returns STOP with empty text after tool execution.
					// Provide a graceful fallback so the user still gets a confirmation.
					if ($answer === '') {
						aichat_log_debug("[AIChat AJAX][$uid] Gemini continuation: empty answer after tool execution, using fallback");
						$answer = __('Done! The action has been completed successfully.', 'axiachat-ai');
					}
					$usage = $result['usage'] ?? null;

					// Extraer y registrar tokens de la respuesta final
					$prompt_tokens_final = null;
					$completion_tokens_final = null;
					$total_tokens_final = null;
					$cost_micros_final = null;

					if (is_array($usage)) {
						$prompt_tokens_final = isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : null;
						$completion_tokens_final = isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : null;
						$total_tokens_final = isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : (($prompt_tokens_final !== null && $completion_tokens_final !== null) ? $prompt_tokens_final + $completion_tokens_final : null);

						if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
							aichat_log_debug('[AIChat AJAX][' . $uid . '] Gemini final response usage', [
								'prompt' => $prompt_tokens_final,
								'completion' => $completion_tokens_final,
								'total' => $total_tokens_final,
								'model' => $model,
								'provider' => $provider,
							], true);
						}

						if (function_exists('aichat_calc_cost_micros') && $prompt_tokens_final !== null) {
							$cost_micros_final = aichat_calc_cost_micros($provider, $model, $prompt_tokens_final, $completion_tokens_final ?: 0);
						}

						// Registrar uso en tabla diaria
						if (function_exists('aichat_update_daily_usage_row') && $model && $provider && $total_tokens_final !== null) {
							$pt = ($prompt_tokens_final !== null) ? (int) $prompt_tokens_final : 0;
							$ct = ($completion_tokens_final !== null) ? (int) $completion_tokens_final : 0;
							if ($pt === 0 && $ct === 0) {
								$pt = (int) $total_tokens_final;
							}
							aichat_update_daily_usage_row($provider, $model, $pt, $ct, (int) $total_tokens_final, (int) $cost_micros_final);
						}
					}

					// Extraer sugerencias ANTES de renderizar Markdown/HTML
					$suggestions = $this->extract_suggestions_from_answer($answer, $bot);

					// Post-procesar respuesta
					$answer = $this->maybe_render_markdown_answer($answer);
					$answer = aichat_replace_link_placeholder($answer);
					if (function_exists('aichat_pretty_known_links')) {
						$answer = aichat_pretty_known_links($answer);
					}
					$answer = $this->sanitize_answer_html($answer);

					// Actualizar conversación con respuesta final
					if (get_option('aichat_logging_enabled', 1)) {
						$request_uuid = $response_id;
						$this->update_last_conversation_response($session, $bot_slug, $answer, $request_uuid);
					}

					// Hook after response
					do_action('aichat_after_response', [
						'bot_slug' => $bot_slug,
						'session_id' => $session,
						'question' => null,
						'answer' => $answer,
						'provider' => 'gemini',
						'model' => $model,
					]);

					// Devolver respuesta final
					$resp = [
						'status' => 'final',
						'message' => $answer,
						'suggestions' => $suggestions,
						'model' => $result['model'] ?? $model,
						'provider' => 'gemini',
						'usage' => $usage,
						'session_id' => $session,
						'bot_slug' => $bot_slug,
					];
					// Aplicar filtro para add-ons (ej: livechat_status)
					$resp = apply_filters('aichat_response_data', $resp);
					wp_send_json_success($resp);
				}

				// OpenAI Responses (legacy path, mantener código original)
				// TODO: Migrar OpenAI Responses también a provider adapter
			}

			// === LEGACY CODE: OpenAI Responses ===
			if ($provider !== 'openai') {
				aichat_log_debug("[AIChat AJAX][$uid] process_tool_continuation: legacy path rejected provider={$provider} (only OpenAI supported in legacy)");
				wp_send_json_error(['message' => 'Continuation only supported for OpenAI Responses.'], 400);
			}
			$model = ! empty($bot['model']) ? sanitize_text_field($bot['model']) : 'gpt-4o-mini';
			if (! $this->is_openai_responses_model($model)) {
				wp_send_json_error(['message' => 'Model is not a Responses (gpt-5*) model.'], 400);
			}
			$openai_key = aichat_get_setting('aichat_openai_api_key');
			if (empty($openai_key)) {
				wp_send_json_error(['message' => 'Missing OpenAI key.'], 400);
			}
			$registered = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];
			$pending_tool_outputs = [];
			// request_uuid opcional para enlazar tool_calls con la conversación al persistir
			$request_uuid = '';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Optional request correlation ID; no state change.
			if (! empty($_REQUEST['aichat_request_uuid'])) {
				$uuid = sanitize_text_field(wp_unslash((string) $_REQUEST['aichat_request_uuid']));
				if (preg_match('/^[a-f0-9-]{36}$/i', $uuid)) {
					$request_uuid = $uuid;
				}
			}
			// Asegurar NOT NULL en la columna (fallback si el cliente no envió UUID)
			if ($request_uuid === '' || $request_uuid === null) {
				$request_uuid = wp_generate_uuid4();
				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug('[AIChat Continue][' . $uid . '] missing aichat_request_uuid; generated fallback', ['request_uuid' => $request_uuid], true);
				}
			}
			// Bucle de servidor: ejecuta tools -> envía previous_response_id -> repite hasta no haber más tool_calls o alcanzar max_rounds
			$endpoint = 'https://api.openai.com/v1/responses';
			$max_rounds = (int) apply_filters('aichat_tools_max_rounds', 5, $bot, $session);
			if ($max_rounds < 1) {
				$max_rounds = 1;
			}
			$round = 2; // la primera continuación tras el handshake
			$final_text = '';

			// Acumular usage de las rondas de Responses API (si viene)
			$prompt_tokens_acc = 0;
			$completion_tokens_acc = 0;
			$total_tokens_acc = 0;
			$usage_seen = false;

			// Helper inline: ejecuta una lista de tool_calls y devuelve pending_tool_outputs
			$exec_tools = function (array $calls, int $round_num) use ($registered, $session, $bot_slug, $request_uuid, $uid) {
				$outputs = [];
				global $wpdb;
				$tool_tbl = $wpdb->prefix . 'aichat_tool_calls';
				foreach ($calls as $tc) {
					$call_id = isset($tc['call_id']) ? sanitize_text_field($tc['call_id']) : ('tc_' . wp_generate_uuid4());
					$name = isset($tc['name']) ? sanitize_text_field($tc['name']) : '';
					$args_json = isset($tc['args']) ? (string) $tc['args'] : (isset($tc['arguments']) ? (string) $tc['arguments'] : '{}');
					$args_arr = json_decode($args_json, true);
					if (!is_array($args_arr)) {
						$args_arr = [];
					}
					$out_str = '';
					$start_exec = microtime(true);
					if (isset($registered[$name]['callback']) && is_callable($registered[$name]['callback'])) {
						try {
							$res_cb = call_user_func($registered[$name]['callback'], $args_arr, ['session_id' => $session, 'bot_slug' => $bot_slug]);
							if (is_array($res_cb)) {
								$out_str = wp_json_encode($res_cb);
							} elseif (is_string($res_cb)) {
								$out_str = $res_cb;
							} else {
								$out_str = '"ok"';
							}
						} catch (\Throwable $e) {
							$out_str = wp_json_encode(['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
						}
					} else {
						$out_str = wp_json_encode(['ok' => false, 'error' => 'unknown_tool']);
					}
					$elapsed_tool = round((microtime(true) - $start_exec) * 1000);
					if (mb_strlen($out_str) > 4000) {
						$out_str = mb_substr($out_str, 0, 4000) . '…';
					}
					aichat_log_debug('[AIChat Continue][' . $uid . '] tool_exec name=' . $name . ' round=' . $round_num . ' ms=' . $elapsed_tool . ' args_len=' . strlen($args_json) . ' out_len=' . strlen($out_str), [], true);
					$wpdb->insert($tool_tbl, [
						'request_uuid' => $request_uuid,
						'conversation_id' => null,
						'session_id' => $session,
						'bot_slug' => $bot_slug,
						'round' => $round_num,
						'call_id' => $call_id,
						'tool_name' => $name,
						'arguments_json' => $args_json,
						'output_excerpt' => $out_str,
						'duration_ms' => $elapsed_tool,
						'error_code' => (strpos($out_str, '"error"') !== false ? 'error' : null),
						'created_at' => current_time('mysql'),
					], ['%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);
					if (! empty($wpdb->last_error)) {
						aichat_log_debug('[AIChat Continue][' . $uid . '] tool_calls insert error', [
							'error' => $wpdb->last_error,
							'table' => $tool_tbl,
							'call_id' => $call_id,
							'tool' => $name,
						], true);
					}
					$outputs[] = ['tool_call_id' => $call_id, 'output' => $out_str];
				}

				return $outputs;
			};

			// 1) Ejecutar la primera tanda recibida desde el cliente
			aichat_log_debug("[AIChat Continue][$uid] legacy OpenAI path: executing " . count($tool_calls) . " tool(s), round={$round}, prev_response_id={$response_id}", [], true);
			if (is_array($tool_calls) && !empty($tool_calls)) {
				$pending_tool_outputs = $exec_tools($tool_calls, $round);
			}
			aichat_log_debug("[AIChat Continue][$uid] tool outputs ready: " . count($pending_tool_outputs), [], true);

			// 2) Bucle de continuación hasta agotar tool calls o superar max_rounds
			while ($round <= $max_rounds) {
				// Preparar payload de continuación
				$payload = [
					'model' => $model,
					'previous_response_id' => $response_id,
					'input' => [],
					'max_output_tokens' => (int) ($bot['max_tokens'] ?? 512),
				];
				if (empty($pending_tool_outputs)) {
					// Continuación sin outputs (p.ej., por max_output_tokens en la ronda anterior)
					$payload['input'][] = ['type' => 'input_text', 'text' => '(no tool outputs)'];
				} else {
					foreach ($pending_tool_outputs as $po) {
						$payload['input'][] = [
							'type' => 'function_call_output',
							'call_id' => $po['tool_call_id'],
							'output' => $po['output'],
						];
					}
				}

				$json_payload = wp_json_encode($payload);
				aichat_log_debug("[AIChat Continue][$uid][round={$round}] POST to Responses API | payload_len=" . strlen($json_payload) . " prev_response_id={$response_id}", [], true);
				$t_r0 = microtime(true);
				$res = wp_remote_post($endpoint, [
					'headers' => ['Authorization' => 'Bearer ' . $openai_key, 'Content-Type' => 'application/json'],
					'body' => $json_payload,
					'timeout' => 60,
				]);
				$t_r1 = microtime(true);
				$elapsed_ms = round(($t_r1 - $t_r0) * 1000);
				if (is_wp_error($res)) {
					aichat_log_debug("[AIChat Continue][$uid][round={$round}] HTTP error: " . $res->get_error_message() . " | {$elapsed_ms}ms", [], true);
					wp_send_json_error(['message' => $res->get_error_message()], 500);
				}
				$code = wp_remote_retrieve_response_code($res);
				$raw = wp_remote_retrieve_body($res);
				aichat_log_debug("[AIChat Continue][$uid][round={$round}] Response code={$code} body_len=" . strlen($raw) . " | {$elapsed_ms}ms", [], true);
				if ($code >= 400) {
					$data_err = json_decode($raw, true);
					$err = isset($data_err['error']['message']) ? $data_err['error']['message'] : ('HTTP ' . $code);
					aichat_log_debug("[AIChat Continue][$uid][round={$round}] API error: {$err}", [], true);
					wp_send_json_error(['message' => $err], 500);
				}
				$data = json_decode($raw, true);
				$response_id = $data['id'] ?? $response_id;

				// Acumular usage (input/output tokens) si está disponible
				if (isset($data['usage']) && is_array($data['usage'])) {
					$u = $data['usage'];
					$pt = null;
					$ct = null;
					$tt = null;
					if (isset($u['prompt_tokens']) || isset($u['completion_tokens']) || isset($u['total_tokens'])) {
						$pt = isset($u['prompt_tokens']) ? (int) $u['prompt_tokens'] : null;
						$ct = isset($u['completion_tokens']) ? (int) $u['completion_tokens'] : null;
						$tt = isset($u['total_tokens']) ? (int) $u['total_tokens'] : null;
					} else {
						// Formato Responses API habitual
						$pt = isset($u['input_tokens']) ? (int) $u['input_tokens'] : null;
						$ct = isset($u['output_tokens']) ? (int) $u['output_tokens'] : null;
						$tt = isset($u['total_tokens']) ? (int) $u['total_tokens'] : null;
					}
					if ($tt === null && $pt !== null && $ct !== null) {
						$tt = $pt + $ct;
					}
					if ($pt !== null || $ct !== null || $tt !== null) {
						$usage_seen = true;
						$prompt_tokens_acc += (int) ($pt ?? 0);
						$completion_tokens_acc += (int) ($ct ?? 0);
						$total_tokens_acc += (int) ($tt ?? (($pt !== null && $ct !== null) ? ($pt + $ct) : 0));
					}
				}

				// Parsear texto y nuevas tool calls
				$text_frag = '';
				$new_tool_calls = [];
				if (isset($data['output']) && is_array($data['output'])) {
					foreach ($data['output'] as $blk) {
						$type = $blk['type'] ?? '';
						if ($type === 'message') {
							if (isset($blk['content']) && is_array($blk['content'])) {
								foreach ($blk['content'] as $c) {
									if (($c['type'] ?? '') === 'output_text' && isset($c['text'])) {
										$text_frag .= ($text_frag ? "\n" : '') . (string) $c['text'];
									}
								}
							}
						} elseif ($type === 'tool_call' || $type === 'function_call') {
							$new_tool_calls[] = [
								'call_id' => $blk['call_id'] ?? $blk['id'] ?? ('tc_' . wp_generate_uuid4()),
								'name' => $blk['name'] ?? $blk['tool_name'] ?? '',
								'arguments' => $blk['arguments'] ?? '{}',
								// para compatibilidad con exec_tools
								'args' => $blk['arguments'] ?? '{}',
							];
						}
					}
				}

				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					$raw_dbg = (strlen($raw) > 3000) ? substr($raw, 0, 3000) . '…' : $raw;
					aichat_log_debug('[AIChat Continue][' . $uid . '][round=' . $round . '] raw_body', ['len' => strlen($raw), 'body' => $raw_dbg], true);
				}
				aichat_log_debug('[AIChat Continue][' . $uid . '][round=' . $round . '] parsed text_len=' . strlen($text_frag) . ' new_tool_calls=' . count($new_tool_calls), [], true);

				// GPT-5.x: detectar function calls filtrados como texto ("to=functions.XXX json {args}")
				// y convertirlos en tool_calls reales para ejecutar
				if (empty($new_tool_calls) && $text_frag !== '' && strpos($text_frag, 'to=functions.') !== false) {
					// Extraer llamadas filtradas: "to=functions.TOOL_NAME" seguido de JSON {...}
					if (preg_match_all('/to=functions\.(\w+).*?\{([^}]+)\}/su', $text_frag, $leaked_matches, PREG_SET_ORDER)) {
						foreach ($leaked_matches as $lm) {
							$leaked_name = $lm[1];
							$leaked_args_raw = '{' . $lm[2] . '}';
							// Verificar que el JSON es válido
							$leaked_args_test = json_decode($leaked_args_raw, true);
							if (is_array($leaked_args_test) && !empty($leaked_args_test)) {
								$new_tool_calls[] = [
									'call_id' => 'leaked_' . wp_generate_uuid4(),
									'name' => $leaked_name,
									'arguments' => $leaked_args_raw,
									'args' => $leaked_args_raw,
								];
								aichat_log_debug("[AIChat Continue][$uid][round=$round] Recovered leaked function_call from text: $leaked_name args_len=" . strlen($leaked_args_raw), [], true);
							}
						}
						if (!empty($new_tool_calls)) {
							// Limpiar el texto garbled ya que lo vamos a ejecutar como tool
							$text_frag = '';
							$final_text = ''; // reset — la respuesta vendrá tras ejecutar el tool
							aichat_log_debug("[AIChat Continue][$uid][round=$round] Converted " . count($new_tool_calls) . ' leaked function_call(s) to real tool_calls', [], true);
						}
					}
				}

				if ($text_frag !== '') {
					$final_text .= ($final_text ? "\n\n" : '') . $text_frag;
				}

				// ¿Hay nuevas tool calls? -> ejecutarlas y continuar
				if (!empty($new_tool_calls)) {
					$pending_tool_outputs = $exec_tools($new_tool_calls, $round);
					$round++;
					continue;
				}

				// Si no hay tool calls: comprobar si quedó incompleto por max_output_tokens para forzar otra continuación
				$status = isset($data['status']) ? (string) $data['status'] : '';
				$incomp_reason = isset($data['incomplete_details']['reason']) ? (string) $data['incomplete_details']['reason'] : '';
				if ($status === 'incomplete' && $incomp_reason === 'max_output_tokens' && $round < $max_rounds) {
					if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
						aichat_log_debug('[AIChat Continue][' . $uid . '][round=' . $round . '] incomplete due to max_output_tokens — continuing', [], true);
					}
					$pending_tool_outputs = [];
					$round++;
					continue;
				}
				// Salida final
				break;
			}

			if ($final_text === '') {
				$final_text = '(empty response)';
			}

			// Sanitizar tokens internos de function calling que se filtran como texto (GPT-5.x)
			if (strpos($final_text, 'to=functions.') !== false) {
				aichat_log_debug("[AIChat Continue][$uid] Detected function_call token leak, sanitizing", [
					'text_len' => strlen($final_text),
					'preview' => mb_substr($final_text, 0, 300),
				], true);
				// Eliminar bloques to=functions.XXX ... {json}
				$cleaned = preg_replace('/to=functions\\.\\w+[^{}]*\\{[^}]*\\}/su', '', $final_text);
				// Eliminar fragmentos CJK/Thai residuales
				$cleaned = preg_replace('/[\\x{4E00}-\\x{9FFF}\\x{3000}-\\x{303F}\\x{FF00}-\\x{FFEF}\\x{0E00}-\\x{0E7F}\\x{3010}\\x{3011}]+/u', '', $cleaned);
				$cleaned = preg_replace('/\\s{2,}/', ' ', trim($cleaned));
				if (mb_strlen($cleaned) < 10) {
					// Intentar extraer mensaje del resultado de la tool
					$fallback_msg = '';
					if (!empty($pending_tool_outputs)) {
						foreach (array_reverse($pending_tool_outputs) as $po) {
							$po_data = json_decode($po['output'] ?? '', true);
							if (is_array($po_data) && !empty($po_data['message'])) {
								$fallback_msg = (string) $po_data['message'];
								break;
							}
						}
					}
					// Si no hay fallback del tool, extraer del texto garbled
					if (empty($fallback_msg) && preg_match('/\"message\"\\s*:\\s*\"([^\"]+)\"/s', $final_text, $m_fb)) {
						$fallback_msg = $m_fb[1];
					}
					$final_text = $fallback_msg ?: __('The operation was completed successfully.', 'axiachat-ai');
					aichat_log_debug("[AIChat Continue][$uid] Using fallback after garbled text cleanup: " . mb_substr($final_text, 0, 200), [], true);
				} else {
					$final_text = $cleaned;
				}
			}

			aichat_log_debug("[AIChat Continue][$uid] final text_len=" . strlen($final_text) . " rounds_used={$round}", [], true);
			$suggestions = $this->extract_suggestions_from_answer($final_text, $bot);
			$final_text = $this->maybe_render_markdown_answer($final_text);
			$final_text = aichat_replace_link_placeholder($final_text);
			if (function_exists('aichat_pretty_known_links')) {
				$final_text = aichat_pretty_known_links($final_text);
			}
			$final_text = $this->sanitize_answer_html($final_text);

			// Calcular coste si tenemos usage
			$usage_final = null;
			if ($usage_seen) {
				// Si por cualquier motivo total quedó a 0 pero hay prompt+completion, reconstruir
				if ($total_tokens_acc <= 0 && ($prompt_tokens_acc > 0 || $completion_tokens_acc > 0)) {
					$total_tokens_acc = $prompt_tokens_acc + $completion_tokens_acc;
				}
				$cost_micros_final = null;
				if (function_exists('aichat_calc_cost_micros')) {
					$cost_micros_final = aichat_calc_cost_micros('openai', $model, $prompt_tokens_acc, $completion_tokens_acc);
				}
				$usage_final = [
					'prompt_tokens' => (int) $prompt_tokens_acc,
					'completion_tokens' => (int) $completion_tokens_acc,
					'total_tokens' => (int) $total_tokens_acc,
					'cost_micros' => $cost_micros_final,
				];
			}

			// Actualizar la última conversación con la respuesta final y vincular tool_calls por request_uuid
			if (get_option('aichat_logging_enabled', 1)) {
				$this->update_last_conversation_response($session, $bot_slug, $final_text, $request_uuid, $usage_final, 'openai', $model);
			}

			// Hook after response, alineado con flujo principal
			do_action('aichat_after_response', [
				'bot_slug' => $bot_slug,
				'session_id' => $session,
				'question' => null,
				'answer' => $final_text,
				'provider' => 'openai',
				'model' => $model,
			]);
			$resp = [
				'status' => 'final',
				'message' => $final_text,
				'suggestions' => $suggestions,
				'usage' => $usage_final,
				'session_id' => $session,
				'bot_slug' => $bot_slug,
			];
			// Aplicar filtro para add-ons (ej: livechat_status)
			$resp = apply_filters('aichat_response_data', $resp);
			wp_send_json_success($resp);
		}

		/**
		 * Actualiza la última fila de conversación para session+bot con la respuesta final y vincula tool_calls mediante request_uuid.
		 */
		protected function update_last_conversation_response($session_id, $bot_slug, $final_answer, $request_uuid = '', $usage = null, $provider = null, $model = null)
		{
			global $wpdb;
			$table = $wpdb->prefix . 'aichat_conversations';
			// Busca la última conversación para este par
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name via $wpdb->prefix.
			$row = $wpdb->get_row(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
					"SELECT id, prompt_tokens, completion_tokens, total_tokens, cost_micros, provider, model FROM {$table} WHERE session_id=%s AND bot_slug=%s ORDER BY id DESC LIMIT 1",
					$session_id,
					sanitize_title($bot_slug)
				),
				ARRAY_A
			);
			if ($row && isset($row['id'])) {
				$conv_id = (int) $row['id'];

				$update = ['response' => wp_kses_post($final_answer)];
				$upd_formats = ['%s'];
				$has_usage_cols = (isset($row['prompt_tokens']) || isset($row['completion_tokens']) || isset($row['total_tokens']) || isset($row['cost_micros']));
				$provider_eff = $provider ?: (isset($row['provider']) ? (string) $row['provider'] : '');
				$model_eff = $model ?: (isset($row['model']) ? (string) $row['model'] : '');

				if ($usage && is_array($usage) && $has_usage_cols) {
					if (array_key_exists('prompt_tokens', $usage)) {
						$update['prompt_tokens'] = (int) $usage['prompt_tokens'];
						$upd_formats[] = '%d';
					}
					if (array_key_exists('completion_tokens', $usage)) {
						$update['completion_tokens'] = (int) $usage['completion_tokens'];
						$upd_formats[] = '%d';
					}
					if (array_key_exists('total_tokens', $usage)) {
						$update['total_tokens'] = (int) $usage['total_tokens'];
						$upd_formats[] = '%d';
					}
					if (array_key_exists('cost_micros', $usage)) {
						$cm = $usage['cost_micros'];
						if ($cm !== null) {
							$update['cost_micros'] = (int) $cm;
							$upd_formats[] = '%d';
						}
					}
				}

				// Actualizar fila de conversación
				$wpdb->update($table, $update, ['id' => $conv_id], $upd_formats, ['%d']);

				// Si antes NO había tokens y ahora sí, registrar aggregate diario una sola vez
				if (function_exists('aichat_update_daily_usage_row') && $usage && is_array($usage)) {
					$old_total = isset($row['total_tokens']) ? (int) $row['total_tokens'] : 0;
					$new_total = isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : 0;
					if ($old_total <= 0 && $new_total > 0 && $provider_eff && $model_eff) {
						$pt = isset($usage['prompt_tokens']) ? (int) $usage['prompt_tokens'] : 0;
						$ct = isset($usage['completion_tokens']) ? (int) $usage['completion_tokens'] : 0;
						if ($pt === 0 && $ct === 0) {
							$pt = $new_total;
						}
						$cm = array_key_exists('cost_micros', $usage) ? $usage['cost_micros'] : null;
						aichat_update_daily_usage_row($provider_eff, $model_eff, $pt, $ct, $new_total, (int) $cm);
					}
				}

				// Vincular tool_calls si disponemos de request_uuid
				if ($request_uuid && preg_match('/^[a-f0-9-]{36}$/i', $request_uuid)) {
					$tool_tbl = $wpdb->prefix . 'aichat_tool_calls';
					// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $tool_tbl is a trusted plugin table name via $wpdb->prefix.
					$wpdb->query(
						$wpdb->prepare(
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $tool_tbl is a trusted plugin table name.
							"UPDATE {$tool_tbl} SET conversation_id=%d WHERE request_uuid=%s AND conversation_id IS NULL",
							$conv_id,
							$request_uuid
						)
					);
				}
			}
		}

		/**
		 * Resuelve el bot por slug.
		 */
		protected function resolve_bot($bot_slug)
		{
			global $wpdb;
			$table = $wpdb->prefix . 'aichat_bots';

			if (! empty($bot_slug)) {
				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name via $wpdb->prefix.
				$bot = $wpdb->get_row(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
						"SELECT * FROM {$table} WHERE slug = %s LIMIT 1",
						$bot_slug
					),
					ARRAY_A
				);
				if ($bot) {
					return $bot;
				}
			}

			$global_on = (bool) get_option('aichat_global_bot_enabled', false);
			$global_slug = get_option('aichat_global_bot_slug', '');

			if ($global_on && ! empty($global_slug)) {
				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name via $wpdb->prefix.
				$bot = $wpdb->get_row(
					$wpdb->prepare(
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
						"SELECT * FROM {$table} WHERE slug = %s LIMIT 1",
						sanitize_title($global_slug)
					),
					ARRAY_A
				);
				if ($bot) {
					return $bot;
				}
			}

			// Fallback al primero
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
			$bot = $wpdb->get_row("SELECT * FROM {$table} ORDER BY id ASC LIMIT 1", ARRAY_A);

			return $bot ?: null;
		}

		/**
		 * OpenAI Chat Completions.
		 * $messages en formato [{role, content}, ...]
		 */
		protected function call_openai_chat($api_key, $model, $messages, $temperature, $max_tokens)
		{
			$endpoint = 'https://api.openai.com/v1/chat/completions';

			$payload = [
				'model' => $model,
				'messages' => array_values($messages),
				'temperature' => $temperature,
				'max_tokens' => $max_tokens,
			];

			$res = wp_remote_post($endpoint, [
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type' => 'application/json',
				],
				'timeout' => 45,
				'body' => wp_json_encode($payload),
			]);

			if (is_wp_error($res)) {
				return ['error' => $res->get_error_message()];
			}

			$code = wp_remote_retrieve_response_code($res);
			$body = json_decode(wp_remote_retrieve_body($res), true);

			if ($code >= 400) {
				$msg = isset($body['error']['message']) ? $body['error']['message'] : __('OpenAI error.', 'axiachat-ai');

				return ['error' => $msg];
			}

			$text = $body['choices'][0]['message']['content'] ?? '';
			if ($text === '') {
				return ['error' => __('Empty response from OpenAI.', 'axiachat-ai')];
			}

			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				if (isset($body['usage'])) {
					aichat_log_debug('OpenAI chat usage', ['usage' => $body['usage'], 'model' => $model], true);
				} else {
					aichat_log_debug('OpenAI chat no usage field', ['model' => $model], true);
				}
			}

			$usage = [];
			if (isset($body['usage'])) {
				$u = $body['usage'];
				$prompt_tokens = isset($u['prompt_tokens']) ? (int) $u['prompt_tokens'] : (isset($u['input_tokens']) ? (int) $u['input_tokens'] : null);
				$completion_tokens = isset($u['completion_tokens']) ? (int) $u['completion_tokens'] : (isset($u['output_tokens']) ? (int) $u['output_tokens'] : null);
				$total_tokens = isset($u['total_tokens']) ? (int) $u['total_tokens'] : null;
				if ($total_tokens === null && $prompt_tokens !== null && $completion_tokens !== null) {
					$total_tokens = $prompt_tokens + $completion_tokens;
				}
				$usage['prompt_tokens'] = $prompt_tokens;
				$usage['completion_tokens'] = $completion_tokens;
				$usage['total_tokens'] = $total_tokens;
			}

			return ['message' => $text, 'usage' => $usage];
		}

		/**
		 * Normaliza nombres de modelo Claude a identificadores oficiales versionados.
		 * Acepta alias sin fecha y devuelve siempre uno datado.
		 */
		protected function normalize_claude_model($model)
		{
			// Delegates to centralised model registry (includes/model-registry.php).
			return aichat_resolve_model($model, 'anthropic');
		}

		/**
		 * Anthropic Claude (Messages).
		 */
		protected function call_claude_messages($api_key, $model, $messages, $temperature, $max_tokens)
		{
			if (empty($api_key)) {
				return ['error' => __('Missing Claude API Key in settings.', 'axiachat-ai')];
			}
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
				aichat_log_debug('[AIChat OpenAI][responses] payload', [
					'model' => $model,
					'max_tokens' => $max_tokens,
					'has_reasoning' => isset($payload['reasoning']) ? 1 : 0,
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

				return ['message' => $text, 'usage' => $usage];
			}

			return ['error' => $last_error ?: 'Error desconocido Claude'];
		}

		/**
		 * Genera una respuesta de un bot de forma programática (sin AJAX/nonce).
		 * Uso previsto por integraciones externas (addon WhatsApp, CRON, etc.).
		 * NO aplica captcha, honeypot ni rate limit. Añadir fuera si se requiere.
		 *
		 * @param string $bot_slug   Slug del bot (o vacío para fallback normal).
		 * @param string $message    Mensaje del usuario.
		 * @param string $session_id ID de sesión (UUID). Si vacío genera uno nuevo.
		 * @param array  $args       Opcionales: page_id(int), debug(bool), context_override(array)
		 * @return array|WP_Error    ['message','bot_slug','session_id','provider','model', 'debug'?]
		 */
		public function process_message_internal($bot_slug, $message, $session_id, $args = [])
		{
			$message = (string) $message;
			if ($message === '') {
				return new WP_Error('aichat_empty', 'Empty message');
			}
			$session_id = preg_replace('/[^a-z0-9\-]/i', '', (string) $session_id);
			if ($session_id === '') {
				$session_id = wp_generate_uuid4();
			}
			$bot_slug = sanitize_title($bot_slug);

			$bot = $this->resolve_bot($bot_slug);
			if (! $bot) {
				return new WP_Error('aichat_bot_not_found', 'Bot not found');
			}

			// Normalización básica (mantener sincronizada con process_message)
			$provider = ! empty($bot['provider']) ? sanitize_key($bot['provider']) : 'openai';
			if ($provider === 'anthropic') {
				$provider = 'claude';
			}
			$model = ! empty($bot['model']) ? sanitize_text_field($bot['model']) : ($provider === 'claude' ? 'claude-3-haiku-20240307' : 'gpt-4o-mini');
			if ($provider === 'claude') {
				$model = $this->normalize_claude_model($model);
			}
			$instructions = isset($bot['instructions']) ? wp_kses_post($bot['instructions']) : '';
			$temperature = isset($bot['temperature']) ? floatval($bot['temperature']) : 0.7;
			if ($temperature < 0) {
				$temperature = 0;
			} if ($temperature > 2) {
				$temperature = 2;
			}
			$max_tokens = isset($bot['max_tokens']) ? (int) $bot['max_tokens'] : 512;
			if ($max_tokens <= 0) {
				$max_tokens = 512;
			}
			$context_mode = isset($bot['context_mode']) ? sanitize_key($bot['context_mode']) : 'auto';
			$context_id = isset($bot['context_id']) ? (int) $bot['context_id'] : 0;
			$page_id = isset($args['page_id']) ? (int) $args['page_id'] : 0;
			$debug = ! empty($args['debug']);

			$mode_arg = 'auto';
			if ($context_mode === 'none') {
				$mode_arg = 'none';
			}
			if ($context_mode === 'page') {
				$mode_arg = 'page';
			}

			if (isset($args['context_override']) && is_array($args['context_override'])) {
				$contexts = $args['context_override'];
			} else {
				$contexts = aichat_get_context_for_question($message, [
					'context_id' => $context_id,
					'mode' => $mode_arg,
					'page_id' => $page_id,
					'limit' => 5,
					'provider' => $provider,
				]);
			}

			$base = aichat_build_messages($message, $contexts, $instructions, null, [
				'bot_name' => ($bot['name'] ?? ''),
				'bot_slug' => ($bot['slug'] ?? ''),
				'suggestions_enabled' => ! empty($bot['ui_suggestions_enabled']),
				'suggestions_count' => isset($bot['ui_suggestions_count']) ? (int) $bot['ui_suggestions_count'] : 3,
			]);
			$system_msg = $base[0] ?? ['role' => 'system', 'content' => ''];
			$current_user_msg = $base[1] ?? ['role' => 'user', 'content' => $message];

			$max_messages_hist = isset($bot['max_messages']) ? max(1, (int) $bot['max_messages']) : 20;
			$context_max_length = isset($bot['context_max_length']) ? max(128, (int) $bot['context_max_length']) : 4096;
			$history_msgs = $this->build_history_messages($session_id, $bot['slug'], $max_messages_hist, $context_max_length);
			$messages = array_merge([$system_msg], $history_msgs, [$current_user_msg]);

			$openai_key = aichat_get_setting('aichat_openai_api_key');
			$claude_key = aichat_get_setting('aichat_claude_api_key');
			if ($provider === 'openai' && ! $openai_key) {
				return new WP_Error('aichat_no_key', 'Missing OpenAI key');
			}
			if ($provider === 'claude' && ! $claude_key) {
				return new WP_Error('aichat_no_key', 'Missing Claude key');
			}

			// === USAR PROVIDER REGISTRY ===
			$registry_provider = ($provider === 'anthropic') ? 'claude' : $provider;
			$registry = AIChat_Provider_Registry::instance();

			if ($provider === 'openai') {
				$provider_config = ['api_key' => $openai_key];
				$org = aichat_get_setting('aichat_openai_organization');
				if (! empty($org)) {
					$provider_config['organization'] = $org;
				}
			} elseif ($provider === 'claude') {
				$provider_config = ['api_key' => $claude_key];
			} elseif ($provider === 'gemini') {
				$gemini_key = aichat_get_setting('aichat_gemini_api_key');
				$provider_config = ['api_key' => $gemini_key];
			} else {
				return new WP_Error('aichat_provider', 'Provider not supported');
			}

			try {
				$provider_instance = $registry->get($registry_provider, $provider_config);
			} catch (Exception $e) {
				return new WP_Error('aichat_registry_error', $e->getMessage());
			}

			$call_params = [
				'model' => $model,
				'temperature' => $temperature,
				'max_tokens' => $max_tokens,
				'bot_id' => $bot['id'] ?? 0,
				'conversation_id' => 0,
				'request_uuid' => wp_generate_uuid4(),
				'session_id' => $session_id,
				'bot_slug' => $bot['slug'],
			];

			$result = $provider_instance->chat($messages, $call_params);
			if (is_wp_error($result)) {
				return $result;
			}
			if (isset($result['error'])) {
				return new WP_Error('aichat_provider_error', (string) $result['error']);
			}

			$answer = (string) ($result['message'] ?? '');
			if ($answer === '') {
				return new WP_Error('aichat_empty_answer', 'Empty answer');
			}
			$answer = $this->maybe_render_markdown_answer($answer);
			$answer = aichat_replace_link_placeholder($answer);
			// Convert known plain URLs (e.g., Google Calendar) into compact, safe anchors
			if (function_exists('aichat_pretty_known_links')) {
				$answer = aichat_pretty_known_links($answer);
			}
			$answer = $this->sanitize_answer_html($answer);

			// HOOK: respuesta antes de persistir (flujo interno)
			if (function_exists('apply_filters')) {
				$answer = apply_filters('aichat_response_pre_persist', $answer, [
					'bot' => $bot,
					'bot_slug' => $bot['slug'],
					'session_id' => $session_id,
					'question' => $message,
					'contexts' => $contexts,
					'provider' => $provider,
					'model' => $model,
					'messages' => $messages,
					'internal' => true,
				]);
			}
			// Extraer usage tokens si están presentes en $result
			$prompt_tokens = isset($result['usage']['prompt_tokens']) ? (int) $result['usage']['prompt_tokens'] : null;
			$completion_tokens = isset($result['usage']['completion_tokens']) ? (int) $result['usage']['completion_tokens'] : null;
			$total_tokens = isset($result['usage']['total_tokens']) ? (int) $result['usage']['total_tokens'] : (($prompt_tokens !== null && $completion_tokens !== null) ? ($prompt_tokens + $completion_tokens) : null);
			$cost_micros = null;
			if (function_exists('aichat_calc_cost_micros') && $prompt_tokens !== null) {
				$cost_micros = aichat_calc_cost_micros($provider, $model, $prompt_tokens, $completion_tokens ?: 0);
			}
			if (get_option('aichat_logging_enabled', 1)) {
				$this->maybe_log_conversation(get_current_user_id(), $session_id, $bot['slug'], $page_id, $message, $answer, $model, $provider, $prompt_tokens, $completion_tokens, $total_tokens, $cost_micros);
			}

			do_action('aichat_after_response', [
				'bot_slug' => $bot['slug'],
				'session_id' => $session_id,
				'question' => $message,
				'answer' => $answer,
				'provider' => $provider,
				'model' => $model,
				'internal' => true,
			]);

			$out = [
				'message' => $answer,
				'bot_slug' => $bot['slug'],
				'session_id' => $session_id,
				'provider' => $provider,
				'model' => $model,
			];
			if ($debug) {
				$out['debug'] = ['context_count' => is_array($contexts) ? count($contexts) : 0];
			}

			return $out;
		}

		/**
		 * Guarda conversación si la tabla existe.
		 */
		protected function maybe_log_conversation($user_id, $session_id, $bot_slug, $page_id, $q, $a, $model = null, $provider = null, $prompt_tokens = null, $completion_tokens = null, $total_tokens = null, $cost_micros = null)
		{
			if (! get_option('aichat_logging_enabled', 1)) {
				return;
			}
			global $wpdb;
			$table = $wpdb->prefix . 'aichat_conversations';

			$exists = $wpdb->get_var($wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s',
				$table
			));
			if (intval($exists) !== 1) {
				return;
			}

			// Obtener IP del usuario (preferimos REMOTE_ADDR; opcionalmente cabeceras proxy confiables)
			$ip_raw = '';
			$candidates = [];
			if (! empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
				$candidates[] = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
			}
			if (! empty($_SERVER['HTTP_X_REAL_IP'])) {
				$candidates[] = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_REAL_IP']));
			}
			if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$xff = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
				$candidates[] = explode(',', $xff)[0];
			}
			if (! empty($_SERVER['REMOTE_ADDR'])) {
				$candidates[] = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
			}
			foreach ($candidates as $cand) {
				$cand = trim((string) $cand);
				if (filter_var($cand, FILTER_VALIDATE_IP)) {
					$ip_raw = $cand;
					break;
				}
			}
			$ip_binary = null;
			if ($ip_raw) {
				// inet_pton soporta IPv4 e IPv6; devuelve binario o false
				$packed = @inet_pton($ip_raw);
				if ($packed !== false) {
					$ip_binary = $packed;
				}
			}

			$data = [
				'user_id' => intval($user_id),
				'session_id' => $session_id,
				'bot_slug' => sanitize_title($bot_slug),
				'model' => $model ? substr(sanitize_text_field($model), 0, 100) : null,
				'provider' => $provider ? substr(sanitize_text_field($provider), 0, 40) : null,
				'page_id' => absint($page_id),
				'message' => wp_kses_post($q),
				'response' => wp_kses_post($a),
				'prompt_tokens' => $prompt_tokens,
				'completion_tokens' => $completion_tokens,
				'total_tokens' => $total_tokens,
				'cost_micros' => $cost_micros,
				'created_at' => current_time('mysql'),
			];
			$formats = ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%d', '%d', '%s'];
			// Sólo añadimos ip_address si la columna existe
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name via $wpdb->prefix.
			$has_ip = $wpdb->get_var(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
				$wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", 'ip_address')
			);
			if ($has_ip) {
				$data['ip_address'] = $ip_binary; // puede ser null
				// ip_address ya considerado en order actual: reordenar arrays si hiciera falta
			}
			// Ajustar formatos a columnas realmente presentes
			$insert_cols = array_keys($data);
			$adj_formats = [];
			foreach ($insert_cols as $col) {
				switch ($col) {
					case 'user_id': case 'page_id': case 'prompt_tokens': case 'completion_tokens': case 'total_tokens': case 'cost_micros': $adj_formats[] = '%d';
						break;
					default: $adj_formats[] = '%s';
				}
			}
			$wpdb->insert($table, $data, $adj_formats);

			// Hook after insert
			if (! empty($wpdb->insert_id)) {
				// Vincular tool calls si request_uuid vino en la petición
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Optional request correlation ID; no state change.
				if (! empty($_REQUEST['aichat_request_uuid'])) {
					$uuid = sanitize_text_field(wp_unslash((string) $_REQUEST['aichat_request_uuid']));
					if (preg_match('/^[a-f0-9-]{36}$/i', $uuid)) {
						$tool_tbl = $wpdb->prefix . 'aichat_tool_calls';
						// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $tool_tbl is a trusted plugin table name via $wpdb->prefix.
						$wpdb->query(
							// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $tool_tbl is a trusted plugin table name.
							$wpdb->prepare("UPDATE $tool_tbl SET conversation_id=%d WHERE request_uuid=%s AND conversation_id IS NULL", $wpdb->insert_id, $uuid)
						);
					}
				}
				do_action('aichat_conversation_saved', [
					'id' => $wpdb->insert_id,
					'bot_slug' => $bot_slug,
					'session_id' => $session_id,
					'user_id' => $user_id,
					'page_id' => $page_id,
				]);
			}

			// Update daily usage aggregate (permitir sin desglose prompt/completion)
			if (function_exists('aichat_update_daily_usage_row') && $model && $provider && $total_tokens !== null) {
				$pt = ($prompt_tokens !== null) ? (int) $prompt_tokens : 0;
				$ct = ($completion_tokens !== null) ? (int) $completion_tokens : 0;
				if ($pt === 0 && $ct === 0) {
					// sin desglose => asignar todo a prompt para no perder contabilización
					$pt = (int) $total_tokens;
				}
				aichat_update_daily_usage_row($provider, $model, $pt, $ct, (int) $total_tokens, (int) $cost_micros);
			}

		}

		/**
		 * Devuelve historial de conversación por session_id + bot_slug.
		 */
		public function get_history()
		{
			$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
			if (empty($nonce) || ! wp_verify_nonce($nonce, 'aichat_ajax')) {
				wp_send_json_error(['message' => __('Nonce inválido.', 'axiachat-ai')], 403);
			}
			$session_raw = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';
			$session = aichat_sanitize_session_id($session_raw);
			$bot_slug = isset($_POST['bot_slug']) ? sanitize_title(wp_unslash($_POST['bot_slug'])) : '';
			$limit_raw = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 50;
			$limit = aichat_bounded_int($limit_raw, 1, 200, 50);
			if ($session === '' || $bot_slug === '') {
				wp_send_json_success(['items' => []]);
			}

			global $wpdb;
			$t = $wpdb->prefix . 'aichat_conversations';
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via $wpdb->prefix.
			$rows = $wpdb->get_results(
				$wpdb->prepare("SELECT message, response, created_at FROM $t WHERE session_id=%s AND bot_slug=%s ORDER BY id ASC LIMIT %d", $session, $bot_slug, $limit), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $t is a trusted plugin table name.
				ARRAY_A
			);
			// Ya guardamos sanitizado; evitamos doble-escape en cliente.
			wp_send_json_success(['items' => array_map(function ($r) {
				return [
					'q' => (string) $r['message'],
					'a' => (string) $r['response'],
					't' => (string) $r['created_at'],
				];
			}, $rows ?: [])]);
		}

		/**
		 * Permite un HTML básico en la respuesta del bot (enlaces seguros, formateo simple).
		 */
		protected function maybe_render_markdown_answer($text)
		{
			$text = (string) $text;
			if ($text === '') {
				return $text;
			}

			// Si ya trae HTML "real", no intentamos parsear Markdown.
			if (preg_match('/<\s*\/?\s*(a|p|br|ul|ol|li|pre|code|strong|em|span)\b/i', $text)) {
				return $text;
			}

			$enabled = true;
			if (function_exists('apply_filters')) {
				$enabled = (bool) apply_filters('aichat_enable_markdown_output', $enabled, $text);
			}
			if (! $enabled) {
				return $text;
			}

			// Heurística simple: sólo si parece Markdown.
			if (! $this->text_looks_like_markdown($text)) {
				return $text;
			}

			// Si Parsedown está disponible, usarlo (mejor soporte Markdown).
			if (class_exists('Parsedown')) {
				try {
					$pd = new Parsedown();
					if (method_exists($pd, 'setSafeMode')) {
						$pd->setSafeMode(true);
					}
					if (method_exists($pd, 'setBreaksEnabled')) {
						$pd->setBreaksEnabled(true);
					}

					return (string) $pd->text($text);
				} catch (Exception $e) {
					// fallback below
				}
			}

			return $this->markdown_to_html_basic($text);
		}

		/**
		 * Extrae sugerencias (chips) si vienen dentro de un bloque delimitado:
		 * [[AICHAT_SUGGESTIONS]]{...json...}[[/AICHAT_SUGGESTIONS]]
		 *
		 * - Modifica $text eliminando el bloque.
		 * - Devuelve array de strings ya saneados.
		 */
		protected function extract_suggestions_from_answer(&$text, $bot)
		{
			$text = (string) $text;

			$enabled = ! empty($bot['ui_suggestions_enabled']);
			if (! $enabled) {
				return [];
			}

			$count = isset($bot['ui_suggestions_count']) ? (int) $bot['ui_suggestions_count'] : 3;
			if (function_exists('aichat_bounded_int')) {
				$count = aichat_bounded_int($count, 1, 6, 3);
			} else {
				$count = max(1, min(6, $count ?: 3));
			}

			if ($text === '') {
				return [];
			}

			// Accept both [[/AICHAT_SUGGESTIONS]] (double bracket) and [/AICHAT_SUGGESTIONS] (single bracket)
			$pattern = '/\[\[AICHAT_SUGGESTIONS\]\]([\s\S]*?)\[{1,2}\/AICHAT_SUGGESTIONS\]{1,2}/i';
			if (! preg_match($pattern, $text, $m)) {
				return [];
			}

			$json_raw = isset($m[1]) ? trim((string) $m[1]) : '';
			// Eliminar el bloque del texto (solo primera coincidencia)
			$text = trim(preg_replace($pattern, '', $text, 1));

			if ($json_raw === '') {
				return [];
			}

			$data = json_decode($json_raw, true);
			if (! is_array($data)) {
				return [];
			}

			$items = $data['suggestions'] ?? [];
			if (! is_array($items)) {
				return [];
			}

			$out = [];
			$seen = [];
			foreach ($items as $it) {
				$s = '';
				if (is_string($it)) {
					$s = $it;
				} elseif (is_array($it) && isset($it['label']) && is_string($it['label'])) {
					$s = $it['label'];
				}
				$s = html_entity_decode((string) $s, ENT_QUOTES);
				$s = wp_strip_all_tags($s);
				$s = preg_replace('/\s+/', ' ', $s);
				$s = trim((string) $s);

				if ($s === '') {
					continue;
				}
				// Evitar URLs o cosas raras (contrato lo prohíbe)
				if (preg_match('/\bhttps?:\/\//i', $s) || preg_match('/\bwww\./i', $s)) {
					continue;
				}
				// Recorte razonable
				if (function_exists('mb_substr')) {
					$s = mb_substr($s, 0, 80);
				} else {
					$s = substr($s, 0, 80);
				}
				$key = strtolower($s);
				if (isset($seen[ $key ])) {
					continue;
				}
				$seen[ $key ] = true;
				$out[] = $s;
				if (count($out) >= $count) {
					break;
				}
			}

			return $out;
		}

		protected function text_looks_like_markdown($text)
		{
			$s = (string) $text;

			return (bool) preg_match('/(^|\n)\s*#{1,4}\s+|```|\*\*[^\n]+\*\*|`[^`]+`|\[[^\]]+\]\((https?:\/\/|\/)[^\)]+\)|(^|\n)\s*(?:-|\*)\s+|(^|\n)\s*\d+\.\s+/m', $s);
		}

		/**
		 * Conversor Markdown básico (sin dependencias) pensado para salida de chat.
		 * - Escapa HTML del modelo.
		 * - Soporta: headings (#..####), negrita, cursiva, inline code, fenced code blocks, listas simples y enlaces [txt](url).
		 * - El HTML resultante se vuelve a pasar por sanitize_answer_html().
		 */
		protected function markdown_to_html_basic($markdown)
		{
			$src = str_replace(["\r\n", "\r"], "\n", (string) $markdown);

			// 1) Extraer code fences primero (para evitar que regex de énfasis toque el código)
			$blocks = [];
			$src = preg_replace_callback('/```([a-z0-9_-]+)?\n([\s\S]*?)```/i', function ($m) use (&$blocks) {
				$lang = isset($m[1]) ? sanitize_html_class((string) $m[1]) : '';
				$code = isset($m[2]) ? (string) $m[2] : '';
				$idx = count($blocks);
				$blocks[] = ['lang' => $lang, 'code' => $code];

				return "\n\n[[AICHAT_CODEBLOCK_{$idx}]]\n\n";
			}, $src);

			// 2) Escapar el resto
			$html = esc_html($src);

			// 3) Headings (#..####)
			$html = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $html);
			$html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);
			$html = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $html);
			$html = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $html);

			// 4) Images ![alt](url) — must come before links to avoid conflict
			$html = preg_replace_callback('/!\[([^\]\n]*)\]\(((?:https?:\/\/)[^\)\s]+)\)/', function ($m) {
				$alt = isset($m[1]) ? (string) $m[1] : '';
				$url = isset($m[2]) ? (string) $m[2] : '';
				$src = esc_url(html_entity_decode($url, ENT_QUOTES));
				if ($src === '') {
					return $alt;
				}

				return '<img src="' . $src . '" alt="' . esc_attr(html_entity_decode($alt, ENT_QUOTES)) . '" loading="lazy" />';
			}, $html);

			// 5) Links [text](url) (solo http(s) o rutas /...)
			$html = preg_replace_callback('/\[([^\]\n]+)\]\(((?:https?:\/\/|\/)[^\)\s]+)\)/', function ($m) {
				$label = isset($m[1]) ? (string) $m[1] : '';
				$url = isset($m[2]) ? (string) $m[2] : '';
				$href = esc_url(html_entity_decode($url, ENT_QUOTES));
				if ($href === '') {
					return $label;
				}

				return '<a href="' . $href . '" target="_blank" rel="noopener nofollow">' . esc_html(html_entity_decode($label, ENT_QUOTES)) . '</a>';
			}, $html);

			// 5) Inline code
			$html = preg_replace('/`([^`\n]+)`/', '<code>$1</code>', $html);

			// 6) Negrita y cursiva (básico)
			$html = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $html);
			$html = preg_replace('/__([^_\n]+)__/', '<strong>$1</strong>', $html);
			// Cursiva: evitar **bold**
			$html = preg_replace('/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $html);
			$html = preg_replace('/(?<!_)_([^_\n]+)_(?!_)/', '<em>$1</em>', $html);

			// 7) Listas simples (no anidadas)
			$lines = explode("\n", $html);
			$out = [];
			$in_ul = false;
			$in_ol = false;
			foreach ($lines as $line) {
				if (preg_match('/^\s*(?:-|\*)\s+(.+)$/', $line, $mm)) {
					if ($in_ol) {
						$out[] = '</ol>';
						$in_ol = false;
					}
					if (! $in_ul) {
						$out[] = '<ul>';
						$in_ul = true;
					}
					$out[] = '<li>' . $mm[1] . '</li>';
					continue;
				}
				if (preg_match('/^\s*\d+\.\s+(.+)$/', $line, $mm)) {
					if ($in_ul) {
						$out[] = '</ul>';
						$in_ul = false;
					}
					if (! $in_ol) {
						$out[] = '<ol>';
						$in_ol = true;
					}
					$out[] = '<li>' . $mm[1] . '</li>';
					continue;
				}
				if ($in_ul) {
					$out[] = '</ul>';
					$in_ul = false;
				}
				if ($in_ol) {
					$out[] = '</ol>';
					$in_ol = false;
				}
				$out[] = $line;
			}
			if ($in_ul) {
				$out[] = '</ul>';
			}
			if ($in_ol) {
				$out[] = '</ol>';
			}
			$html = implode("\n", $out);

			// 8) Reinsertar code blocks
			$html = preg_replace_callback('/\[\[AICHAT_CODEBLOCK_(\d+)\]\]/', function ($m) use ($blocks) {
				$idx = isset($m[1]) ? (int) $m[1] : -1;
				if ($idx < 0 || ! isset($blocks[ $idx ])) {
					return '';
				}
				$lang = $blocks[ $idx ]['lang'];
				$code = $blocks[ $idx ]['code'];
				$class = $lang ? ' class="language-' . esc_attr($lang) . '"' : '';

				return '<pre><code' . $class . '>' . esc_html($code) . '</code></pre>';
			}, $html);

			// 9) Párrafos / saltos de línea
			if (function_exists('wpautop')) {
				$html = wpautop($html);
			} else {
				$html = nl2br($html);
			}

			return $html;
		}

		protected function sanitize_answer_html($html)
		{
			$allowed = [
				'a' => ['href' => true, 'target' => true, 'rel' => true, 'title' => true, 'class' => true],
				'strong' => [], 'em' => [], 'b' => [], 'i' => [],
				'br' => [], 'p' => [], 'ul' => [], 'ol' => [], 'li' => [],
				'code' => [], 'pre' => [],
				'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [],
				'blockquote' => [],
				'span' => ['class' => true],
				'img' => ['src' => true, 'alt' => true, 'class' => true, 'loading' => true, 'width' => true, 'height' => true],
			];
			// Forzar rel noopener en target=_blank
			$html = preg_replace('/<a([^>]+)target="_blank"/i', '<a$1target="_blank" rel="noopener"', $html);

			return wp_kses($html, $allowed);
		}

		/**
		 * Construye el historial en formato [{role:'user'},{role:'assistant'},...]
		 * Limita nº de pares por max_pairs y recorta a un presupuesto de caracteres ($char_limit).
		 */
		protected function build_history_messages($session_id, $bot_slug, $max_pairs, $char_limit)
		{
			global $wpdb;
			$t = $wpdb->prefix . 'aichat_conversations';
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via $wpdb->prefix.
			$rows = $wpdb->get_results(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $t is a trusted plugin table name.
				$wpdb->prepare(
					"SELECT message, response FROM $t WHERE session_id=%s AND bot_slug=%s ORDER BY id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $t is a trusted plugin table name.
					$session_id,
					$bot_slug,
					max(1, (int) $max_pairs)
				),
				ARRAY_A
			);
			if (! $rows) {
				return [];
			}

			$rows = array_reverse($rows); // cronológico
			$out = [];
			$acc = 0;
			foreach ($rows as $r) {
				$q = trim(wp_strip_all_tags((string) $r['message']));
				$a = trim(wp_strip_all_tags((string) $r['response']));
				if ($q !== '') {
					$q = $this->truncate_to_budget($q, $char_limit, $acc);
					if ($q === '') {
						break;
					}
					$out[] = ['role' => 'user', 'content' => $q];
					$acc += strlen($q);
				}
				if ($a !== '') {
					$a = $this->truncate_to_budget($a, $char_limit, $acc);
					if ($a === '') {
						break;
					}
					$out[] = ['role' => 'assistant', 'content' => $a];
					$acc += strlen($a);
				}
				if ($acc >= $char_limit) {
					break;
				}
			}

			return $out;
		}

		protected function truncate_to_budget($text, $limit, $used)
		{
			if ($limit <= 0) {
				return (string) $text;
			}
			$remain = $limit - $used;
			if ($remain <= 0) {
				return '';
			}
			$s = (string) $text;

			return (strlen($s) > $remain) ? substr($s, 0, $remain) : $s;
		}

		/**
		 * Nuevo: Router OpenAI. Modelos gpt-5* → Responses API; resto → Chat Completions.
		 */
		protected function call_openai_auto($api_key, $model, $messages, $temperature, $max_tokens, $extra = [])
		{
			// $extra can be either:
			//  - an associative array with extra flags (reasoning, etc.)
			//  - a numeric array of tool definitions (legacy Phase 1 pass-through)
			//  - an associative array containing key 'tools' => [ ...tool defs... ]
			// Normalize so chat path always receives ['tools'=>[...]] if tools provided.
			$tools = [];
			if (is_array($extra)) {
				if (isset($extra['tools']) && is_array($extra['tools'])) {
					$tools = $extra['tools'];
				} elseif ($extra && array_keys($extra) === range(0, count($extra) - 1)) { // sequential numeric keys ⇒ treat as tool list
					$is_tool_list = true;
					foreach ($extra as $maybe_tool) {
						if (! is_array($maybe_tool) || empty($maybe_tool['type'])) {
							$is_tool_list = false;
							break;
						}
					}
					if ($is_tool_list) {
						$tools = $extra;
						$extra = ['tools' => $tools];
					}
				}
			}

			if ($this->is_openai_responses_model($model)) {
				// Responses API (gpt-5*) currently: we ignore tools until multi-turn tool flow implemented for Responses.
				return $this->call_openai_responses($api_key, $model, $messages, $max_tokens, $extra, $temperature);
			}

			// Chat Completions path (GPT-4* / o / mini etc.)
			return $this->call_openai_chat_cc($api_key, $model, $messages, $temperature, $max_tokens, $extra);
		}

		protected function is_openai_responses_model($model)
		{
			$m = strtolower((string) $model);

			return strpos($m, 'gpt-5') === 0; // gpt‑5* → Responses API
		}

		protected function is_gpt5_model($model)
		{
			return (bool) preg_match('/^gpt-5(\b|[-_])/i', (string) $model);
		}

		protected function map_reasoning_effort($val)
		{
			$v = strtolower((string) $val);
			if ($v === 'fast') {
				return 'low';
			}
			if ($v === 'accurate') {
				return 'high';
			}

			return 'medium'; // por si se amplía en el futuro
		}

		/**
		 * OpenAI Responses API con auto-fallback de parámetros.
		 * Nota: el orden de los args mantiene compatibilidad con call_openai_auto().
		 */
		protected function call_openai_responses($api_key, $model, $messages, $max_tokens, $extra = [], $temperature = null)
		{
			$endpoint = 'https://api.openai.com/v1/responses';
			$is_gpt5 = $this->is_gpt5_model($model);
			$raw_messages = (array) $messages;

			// --- Nueva estrategia solicitada ---
			// In 'instructions': policy + bot instructions + contexto
			// In 'input': historial (user/assistant) + mensaje actual DEL usuario (sin el bloque de contexto)
			// Si extracción falla, fallback a versión aplanada previa.

			// Construir campos instructions + input para Responses
			$instructions_field = '';
			$input_field = '';
			if ($raw_messages) {
				$conv_parts = [];
				foreach ($raw_messages as $m) {
					if (!is_array($m)) {
						continue;
					}
					$role = $m['role'] ?? '';
					$content = is_string($m['content'] ?? '') ? (string) $m['content'] : '';
					if ($content === '') {
						continue;
					}
					if ($role === 'system') {
						$instructions_field .= ($instructions_field ? "\n\n" : '') . $content;
					} else {
						$conv_parts[] = strtoupper($role) . ': ' . $content;
					}
				}
				if ($instructions_field === '') {
					$instructions_field = 'You are a helpful assistant.';
				} // fallback mínimo
				$input_field = trim(implode("\n\n", $conv_parts));
				if ($input_field === '') {
					$input_field = 'Hello';
				}
			} else {
				$instructions_field = 'You are a helpful assistant.';
				$input_field = 'Hello';
			}
			// --- NUEVO: Multi-ronda tools para Responses ---
			$tools = (isset($extra['tools']) && is_array($extra['tools'])) ? $extra['tools'] : [];
			// Normalizar formato tools (Chat → Responses).
			// Responses espera:
			//  - Function tools: { type: 'function', function: { name, description?, parameters } }
			//  - Native tools (e.g. web_search): { type: 'web_search' }
			if ($tools) {
				$mapped = [];
				foreach ($tools as $t) {
					if (!is_array($t)) {
						continue;
					}
					$t_type = isset($t['type']) ? (string) $t['type'] : '';
					if ($t_type === 'function') {
						// Flatten to Responses function tool schema: top-level name/description/parameters
						$fn = isset($t['function']) && is_array($t['function']) ? $t['function'] : [];
						$fn_name = isset($fn['name']) ? (string) $fn['name'] : '';
						if ($fn_name === '') {
							$fn_name = isset($t['name']) ? (string) $t['name'] : '';
						}
						if ($fn_name === '') {
							$fn_name = 'unnamed_func';
						}
						$fn_desc = isset($fn['description']) ? (string) $fn['description'] : (isset($t['description']) ? (string) $t['description'] : '');
						$fn_params = isset($fn['parameters']) ? $fn['parameters'] : (isset($t['parameters']) ? $t['parameters'] : (object) []);
						$entry = [
							'type' => 'function',
							'name' => $fn_name,
							'description' => $fn_desc,
							'parameters' => $fn_params,
						];
						if (isset($t['strict']) && !empty($t['strict'])) {
							$entry['strict'] = true;
						}
						$mapped[] = $entry;
					} elseif ($t_type === 'web_search') {
						// Native tool: allow filters.allowed_domains if present
						$entry = ['type' => 'web_search'];
						if (isset($t['filters']) && is_array($t['filters'])) {
							$filters = $t['filters'];
							$out_filters = [];
							if (isset($filters['allowed_domains']) && is_array($filters['allowed_domains'])) {
								// sanitize to host-like strings
								$out_filters['allowed_domains'] = array_values(array_filter(array_map(function ($d) {
									$d = trim((string) $d);
									$d = preg_replace('/^https?:\/\//i', '', $d); // strip scheme
									$d = preg_replace('/\/$/', '', $d); // strip trailing slash

									return $d;
								}, $filters['allowed_domains'])));
							}
							if ($out_filters) {
								$entry['filters'] = $out_filters;
							}
						}
						$mapped[] = $entry;
					} else {
						// Unknown tool types are ignored for Responses to avoid API errors
						if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
							aichat_log_debug('[AIChat Responses][tools] Ignoring unsupported tool type', ['type' => $t_type], true);
						}
					}
				}
				// Always replace with normalized list to avoid leaking invalid shapes
				$tools = $mapped;
			}
			// Permitir inyección de tools nativas (web_search) incluso si venimos sin tools
			$tools = apply_filters('aichat_openai_responses_tools', $tools, ['model' => $model, 'bot' => $extra['bot_slug'] ?? null]);
			$has_tools = !empty($tools);
			$max_rounds = (int) apply_filters('aichat_tools_max_rounds', 5, null, null);
			if ($max_rounds < 1) {
				$max_rounds = 1;
			}

			$round = 1;
			$response_id = null;
			$final_text = '';
			$usage_acc = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
			$pending_tool_outputs = [];
			$request_uuid = wp_generate_uuid4();

			while ($round <= $max_rounds) {
				$payload = [];
				if ($response_id === null) {
					// Primera ronda
					$payload = [
						'model' => $model,
						'instructions' => $instructions_field,
						'input' => [
							['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $input_field]]],
						],
						'max_output_tokens' => (int) $max_tokens,
					];
					if (!$is_gpt5 && $temperature !== null && $temperature !== '') {
						$payload['temperature'] = (float) $temperature;
					}
					if ($has_tools) {
						$payload['tools'] = $tools;
						$payload['tool_choice'] = 'auto';
						// If web_search is present, ask API to include sources in output for transparency
						$has_ws = false;
						foreach ($tools as $tt) {
							if (($tt['type'] ?? '') === 'web_search') {
								$has_ws = true;
								break;
							}
						}
						if ($has_ws) {
							$payload['include'] = ['web_search_call.action.sources'];
						}
					}
					if (!empty($extra['reasoning']) && strtolower($extra['reasoning']) !== 'off') {
						$payload['reasoning'] = ['effort' => $this->map_reasoning_effort($extra['reasoning'])];
					}
				} else {
					// Rondas siguientes: patrón Python → nuevo POST /responses con previous_response_id + input array de function_call_output
					$fco_items = [];
					foreach ($pending_tool_outputs as $to) {
						$fco_items[] = [
							'type' => 'function_call_output',
							'call_id' => $to['tool_call_id'],
							'output' => $to['output'],
						];
					}
					if (empty($fco_items)) {
						// Seguridad: si no hay outputs, enviamos un input_text mínimo para evitar error
						$fco_items[] = ['type' => 'input_text', 'text' => '(no tool outputs)'];
					}
					$payload = [
						'model' => $model,
						'previous_response_id' => $response_id,
						'input' => $fco_items,
						'max_output_tokens' => (int) $max_tokens,
					];
				}

				$json_payload = wp_json_encode($payload);
				$t_r0 = microtime(true);
				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					$tool_meta = ['has_tools' => $has_tools ? 1 : 0, 'payload_len' => strlen($json_payload)];
					if ($has_tools) {
						$tool_meta['tool_types'] = array_values(array_unique(array_map(function ($x) {
							return isset($x['type']) ? $x['type'] : '?';
						}, $tools)));
					}
					aichat_log_debug('[AIChat Responses][round=' . $round . '] request', $tool_meta, true);
					// Pretty request snapshot
					$req_pretty = $this->format_pretty_request_log([
						'uid' => $request_uuid,
						'provider' => 'openai',
						'model' => $model,
						'temperature' => $temperature,
						'max_tokens' => $max_tokens,
						'mode' => 'responses',
						'context_count' => 0,
						'history_pairs' => 0,
						'messages_total' => is_array($messages) ? count($messages) : 0,
						'system' => $instructions_field,
						'prompt' => is_string($input_field) ? mb_substr($input_field, 0, 1200) : '',
						'tools' => $this->extract_tool_names_for_log(isset($extra['tools']) ? $extra['tools'] : []),
					]);
					aichat_log_debug($req_pretty, [], true);
					// Optional full SYSTEM dump without truncation for diagnostics (Responses path)
					if (defined('AICHAT_DEBUG_LOG_SYSTEM_FULL') && (bool) constant('AICHAT_DEBUG_LOG_SYSTEM_FULL')) {
						aichat_log_debug("[AIChat SYSTEM FULL][$request_uuid]\n" . $instructions_field, [], true);
					}
				}
				// Siempre usamos el endpoint base /responses ahora (previous_response_id maneja el hilo)
				$post_endpoint = $endpoint;
				$res = wp_remote_post($post_endpoint, [
					'headers' => ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json'],
					'body' => $json_payload,
					'timeout' => 60,
				]);
				// (El fallback antiguo de tool_outputs ya no aplica)
				$t_r1 = microtime(true);
				if (is_wp_error($res)) {
					return ['error' => $res->get_error_message()];
				}
				$code = wp_remote_retrieve_response_code($res);
				$raw = wp_remote_retrieve_body($res);
				$data = json_decode($raw, true);
				if ($code >= 400) {
					$err = isset($data['error']['message']) ? $data['error']['message'] : ('HTTP ' . $code);

					return ['error' => $err];
				}
				$response_id = $data['id'] ?? $response_id;

				// Parse output blocks: output[] OR top-level message/content variants
				$tool_calls = [];
				$text_frag = '';
				if (isset($data['output']) && is_array($data['output'])) {
					foreach ($data['output'] as $blk) {
						$type = $blk['type'] ?? '';
						if ($type === 'message') {
							// message: role + content[]
							if (isset($blk['content']) && is_array($blk['content'])) {
								foreach ($blk['content'] as $c) {
									if (($c['type'] ?? '') === 'output_text' && isset($c['text'])) {
										$text_frag .= ($text_frag ? "\n" : '') . (string) $c['text'];
									}
								}
							}
						} elseif ($type === 'tool_call' || $type === 'function_call') {
							// Responses API está devolviendo 'function_call' (no 'tool_call') para llamadas de función.
							$tool_calls[] = [
								'id' => $blk['call_id'] ?? $blk['id'] ?? ('tc_' . wp_generate_uuid4()),
								'name' => $blk['name'] ?? $blk['tool_name'] ?? '',
								'arguments' => $blk['arguments'] ?? '{}',
							];
						}
					}
				} else {
					// Fallback legacy shaping
					if (isset($data['message']['content']) && is_string($data['message']['content'])) {
						$text_frag = $data['message']['content'];
					}
				}

				// Usage mapping
				if (isset($data['usage'])) {
					$u = $data['usage'];
					$pt = $u['input_tokens'] ?? $u['prompt_tokens'] ?? null;
					$ct = $u['output_tokens'] ?? $u['completion_tokens'] ?? null;
					$tt = $u['total_tokens'] ?? (($pt !== null && $ct !== null) ? $pt + $ct : null);
					if ($pt !== null) {
						$usage_acc['prompt_tokens'] += (int) $pt;
					}
					if ($ct !== null) {
						$usage_acc['completion_tokens'] += (int) $ct;
					}
					if ($tt !== null) {
						$usage_acc['total_tokens'] += (int) $tt;
					}
				}

				if ($text_frag !== '') {
					$final_text .= ($final_text ? "\n\n" : '') . $text_frag;
				}
				if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
					aichat_log_debug('[AIChat Responses][round=' . $round . '] result', ['text_len' => strlen($text_frag), 'tool_calls' => count($tool_calls), 'ms' => round(($t_r1 - $t_r0) * 1000)], true);
					// Pretty response snapshot for the round
					$pretty_round = $this->format_pretty_response_log([
						'uid' => $request_uuid,
						'provider' => 'openai',
						'model' => $model,
						'answer' => $text_frag,
						'tool_calls' => count($tool_calls),
						'usage' => isset($data['usage']) && is_array($data['usage']) ? $data['usage'] : [],
						'timings_ms' => ['round' => round(($t_r1 - $t_r0) * 1000)],
					]);
					aichat_log_debug($pretty_round, [], true);
					// Log raw (truncado) siempre para diagnóstico avanzado
					$raw_dbg = (strlen($raw) > 3000) ? substr($raw, 0, 3000) . '…' : $raw;
					aichat_log_debug('[AIChat Responses][round=' . $round . '] raw_body', ['len' => strlen($raw), 'body' => $raw_dbg], true);
				}

				if ($text_frag === '' && empty($tool_calls)) {
					// Log específico de vacío
					if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
						aichat_log_debug('[AIChat Responses][round=' . $round . '] empty_output_no_tools', [], true);
					}
				}

				// If no tool calls, decide whether to end or continue when response is incomplete due to token cap
				if (empty($tool_calls)) {
					$status = isset($data['status']) ? (string) $data['status'] : '';
					$incomp_reason = isset($data['incomplete_details']['reason']) ? (string) $data['incomplete_details']['reason'] : '';
					if ($status === 'incomplete' && $incomp_reason === 'max_output_tokens' && $round < $max_rounds) {
						if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
							aichat_log_debug('[AIChat Responses][round=' . $round . '] incomplete due to max_output_tokens — continuing', [], true);
						}
						// Prepare to continue in the next loop iteration using previous_response_id
						$pending_tool_outputs = []; // will produce a minimal input_text fallback
						$round++;
						continue;
					}
					break; // final answer reached
				}
				// NUEVO HANDSHAKE: si es la primera ronda y hay tool_calls, devolvemos estado tool_pending sin ejecutar herramientas todavía
				if ($response_id !== null && $round === 1) {
					// (seguridad: en este punto response_id ya asignado tras primera solicitud)
				}
				if ($round === 1 && ! empty($tool_calls)) {
					// Construir salida especial
					$pending = [];
					// Mapear tool_calls -> incluir activity_label si existe en registro
					$registered_map = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];
					foreach ($tool_calls as $tc) {
						$fname = $tc['name'];
						$activity = '';
						if (isset($registered_map[$fname]['activity_label'])) {
							$activity = (string) $registered_map[$fname]['activity_label'];
						} else {
							$activity = 'Running tool: ' . $fname . '...';
						}
						$pending[] = [
							'call_id' => $tc['id'],
							'name' => $fname,
							'args' => $tc['arguments'],
							'activity_label' => $activity,
						];
					}

					return [
						'status' => 'tool_pending',
						'response_id' => $response_id,
						'tool_calls' => $pending,
						'request_uuid' => $request_uuid,
						'usage' => $usage_acc,
					];
				}
				if ($round === $max_rounds) {
					break;
				}

				// Ejecutar tool calls y preparar tool_outputs
				$pending_tool_outputs = [];
				$registered = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];
				// Extra metadata for logging (optional)
				$log_bot_slug = isset($extra['bot_slug']) ? sanitize_title((string) $extra['bot_slug']) : '';
				$log_session = isset($extra['session_id']) ? aichat_sanitize_session_id((string) $extra['session_id']) : null;
				foreach ($tool_calls as $tc) {
					$fname = $tc['name'];
					$args_json = $tc['arguments'];
					$args_arr = json_decode($args_json, true);
					if (!is_array($args_arr)) {
						$args_arr = [];
					}
					$start_exec = microtime(true);
					$out_str = '';
					if (isset($registered[$fname]) && is_callable($registered[$fname]['callback'])) {
						try {
							$res_cb = call_user_func($registered[$fname]['callback'], $args_arr, ['model' => $model, 'round' => $round]);
							if (is_array($res_cb)) {
								$out_str = wp_json_encode($res_cb);
							} elseif (is_string($res_cb)) {
								$out_str = $res_cb;
							} else {
								$out_str = '"ok"';
							}
						} catch (\Throwable $e) {
							$out_str = wp_json_encode(['ok' => false, 'error' => 'exception', 'message' => $e->getMessage()]);
						}
					} else {
						$out_str = wp_json_encode(['ok' => false, 'error' => 'unknown_tool']);
					}
					$elapsed_tool = round((microtime(true) - $start_exec) * 1000);
					if (mb_strlen($out_str) > 4000) {
						$out_str = mb_substr($out_str, 0, 4000) . '…';
					}
					if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
						aichat_log_debug('[AIChat Responses][tool_exec] name=' . $fname . ' ms=' . $elapsed_tool . ' args_len=' . strlen($args_json), [], true);
					}
					// Log en tabla tool_calls
					global $wpdb;
					$tool_tbl = $wpdb->prefix . 'aichat_tool_calls';
					$wpdb->insert($tool_tbl, [
						'request_uuid' => $request_uuid,
						'conversation_id' => null,
						'session_id' => $log_session,
						'bot_slug' => $log_bot_slug,
						'round' => $round,
						'call_id' => $tc['id'],
						'tool_name' => $fname,
						'arguments_json' => $args_json,
						'output_excerpt' => $out_str,
						'duration_ms' => $elapsed_tool,
							'error_code' => (strpos($out_str, '"error"') !== false ? 'error' : null),
						'created_at' => current_time('mysql'),
					], ['%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);
					if (! empty($wpdb->last_error)) {
						aichat_log_debug('[AIChat Responses][tool_exec] insert error', [
							'error' => $wpdb->last_error,
							'table' => $tool_tbl,
							'call_id' => $tc['id'],
							'tool' => $fname,
						], true);
					}

					$pending_tool_outputs[] = [
						'tool_call_id' => $tc['id'],
						'output' => $out_str,
					];
				}
				$round++;
			}

			$usage = [];
			if ($usage_acc['prompt_tokens'] || $usage_acc['completion_tokens'] || $usage_acc['total_tokens']) {
				$usage = [
					'prompt_tokens' => $usage_acc['prompt_tokens'] ?: null,
					'completion_tokens' => $usage_acc['completion_tokens'] ?: null,
					'total_tokens' => $usage_acc['total_tokens'] ?: null,
				];
			}

			return ['message' => $final_text, 'usage' => $usage];
		}

		/** Chat Completions clásico para GPT‑4* (versión router) */
		protected function call_openai_chat_cc($api_key, $model, $messages, $temperature, $max_tokens, $extra = [])
		{
			$endpoint = 'https://api.openai.com/v1/chat/completions';
			$payload = [
				'model' => $model,
				'messages' => array_values($messages),
				'temperature' => (float) $temperature,
				'max_tokens' => (int) $max_tokens,
			];
			// Tool (function calling) support
			$tools = [];
			if (is_array($extra)) {
				if (isset($extra['tools']) && is_array($extra['tools'])) {
					$tools = $extra['tools'];
				} elseif ($extra && array_keys($extra) === range(0, count($extra) - 1)) {
					// numeric array passed directly
					$maybe_tools = $extra;
					$valid = true;
					foreach ($maybe_tools as $t) {
						if (!is_array($t) || empty($t['type'])) {
							$valid = false;
							break;
						}
					}
					if ($valid) {
						$tools = $maybe_tools;
					}
				}
			}
			if ($tools) {
				$payload['tools'] = $tools;
				$payload['tool_choice'] = 'auto';
			}
			if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
				// Copia defensiva para truncar contenido antes de loggear
				$dbg = $payload;
				// Truncar mensajes largos
				if (isset($dbg['messages']) && is_array($dbg['messages'])) {
					foreach ($dbg['messages'] as &$m) {
						if (isset($m['content']) && is_string($m['content']) && strlen($m['content']) > 1000) {
							$m['content'] = substr($m['content'], 0, 1000) . '…';
						}
					}
				}
				aichat_log_debug('[AIChat OpenAI][chat] payload', [
					'model' => $model,
					'temperature' => $temperature,
					'max_tokens' => $max_tokens,
					'tool_count' => isset($payload['tools']) ? count($payload['tools']) : 0,
					'messages_count' => isset($payload['messages']) ? count($payload['messages']) : 0,
					'size_chars' => strlen(wp_json_encode($payload)),
					'preview' => $dbg,
				], true);
			}
			$res = wp_remote_post($endpoint, [
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type' => 'application/json',
				],
				'timeout' => 60,
				'body' => wp_json_encode($payload),
			]);
			if (is_wp_error($res)) {
				return ['error' => $res->get_error_message()];
			}
			$code = wp_remote_retrieve_response_code($res);
			$body_raw = wp_remote_retrieve_body($res);
			$body = json_decode($body_raw, true);
			if ($code >= 400) {
				$msg = isset($body['error']['message']) ? $body['error']['message'] : __('OpenAI API error.', 'axiachat-ai');

				return ['error' => $msg];
			}
			$choice = $body['choices'][0] ?? [];
			$finish_reason = $choice['finish_reason'] ?? '';
			$message_obj = $choice['message'] ?? [];
			$text = isset($message_obj['content']) ? (string) $message_obj['content'] : '';

			$tool_calls_out = [];
			if (! empty($message_obj['tool_calls']) && is_array($message_obj['tool_calls'])) {
				foreach ($message_obj['tool_calls'] as $tc) {
					$id = $tc['id'] ?? ('call_' . wp_generate_uuid4());
					$func = $tc['function'] ?? [];
					$tool_calls_out[] = [
						'id' => $id,
						'name' => $func['name'] ?? '',
						'arguments' => $func['arguments'] ?? '{}',
					];
				}
			}
			// If model only requested tools (finish_reason tool_calls) it's fine that $text is empty
			if ($text === '' && ! $tool_calls_out) {
				return ['error' => __('Empty response from OpenAI.', 'axiachat-ai')];
			}

			$usage = [];
			if (isset($body['usage'])) {
				$u = $body['usage'];
				$prompt_tokens = isset($u['prompt_tokens']) ? (int) $u['prompt_tokens'] : (isset($u['input_tokens']) ? (int) $u['input_tokens'] : null);
				$completion_tokens = isset($u['completion_tokens']) ? (int) $u['completion_tokens'] : (isset($u['output_tokens']) ? (int) $u['output_tokens'] : null);
				$total_tokens = isset($u['total_tokens']) ? (int) $u['total_tokens'] : null;
				if ($total_tokens === null && $prompt_tokens !== null && $completion_tokens !== null) {
					$total_tokens = $prompt_tokens + $completion_tokens;
				}
				$usage['prompt_tokens'] = $prompt_tokens;
				$usage['completion_tokens'] = $completion_tokens;
				$usage['total_tokens'] = $total_tokens;
			}
			$out = ['message' => (string) $text, 'usage' => $usage];
			if ($tool_calls_out) {
				$out['tool_calls'] = $tool_calls_out;
			}

			return $out;
		}

		/**
		 * Convierte [{role,content}] a prompt plano para Responses.
		 */
		protected function messages_to_prompt($messages)
		{
			$sys = [];
			$lines = [];
			foreach ($messages as $m) {
				$role = $m['role'] ?? 'user';
				$txt = trim((string) ($m['content'] ?? ''));
				if ($txt === '') {
					continue;
				}
				if ($role === 'system') {
					$sys[] = $txt;
					continue;
				}
				$prefix = ($role === 'assistant') ? 'Assistant' : 'User';
				$lines[] = $prefix . ': ' . $txt;
			}
			$sysBlock = !empty($sys) ? ('System: ' . implode("\n\n", $sys) . "\n\n") : '';

			return $sysBlock . implode("\n\n", $lines);
		}

		/**
		 * Registra respuesta de OpenAI (intentos, errores, etc.).
		 */
		protected function aichat_log_openai_response($kind, $endpoint, $model, $status, $res, $body_raw, $payload_meta = [], $input_len = null)
		{
			try {
				$req_id = wp_remote_retrieve_header($res, 'x-request-id');
				if (!$req_id) {
					$req_id = wp_remote_retrieve_header($res, 'x-openai-request-id');
				}
				$body_trim = is_string($body_raw) ? mb_substr($body_raw, 0, 4000) : '';
				$usage = '';
				$decoded = json_decode($body_raw, true);
				if (is_array($decoded)) {
					if (isset($decoded['usage'])) {
						$usage = ' usage=' . wp_json_encode($decoded['usage']);
					} elseif (isset($decoded['output_tokens'])) {
						$usage = ' output_tokens=' . (int) $decoded['output_tokens'];
					}
				}
				$meta = $payload_meta;
				if ($input_len !== null) {
					$meta['input_len'] = (int) $input_len;
				}
				aichat_log_debug(
					'[AIChat AJAX][OpenAI ' . $kind . ']' .
					' model=' . $model .
					' status=' . $status .
					' req_id=' . ($req_id ?: '-') .
					$usage .
					' endpoint=' . $endpoint .
					' meta=' . wp_json_encode($meta) .
					' body_raw=' . $body_trim,
					[],
					true
				);
			} catch (\Throwable $e) {
				aichat_log_debug('[AIChat AJAX][OpenAI ' . $kind . '] log error: ' . $e->getMessage(), [], true);
			}
		}

		protected function extract_openai_responses_text(array $body)
		{
			// 1) Campo directo si existe
			if (!empty($body['output_text']) && is_string($body['output_text'])) {
				return trim((string) $body['output_text']);
			}

			// 2) Recorrer output[] buscando type=message y sus content[]
			if (!empty($body['output']) && is_array($body['output'])) {
				$buf = '';
				foreach ($body['output'] as $item) {
					if (!is_array($item)) {
						continue;
					}
					// Algunos items pueden ser de tipo "reasoning" sin texto; saltar
					if (($item['type'] ?? '') !== 'message') {
						continue;
					}

					$content = $item['content'] ?? null;
					if (is_string($content) && $content !== '') {
						$buf .= ($buf ? "\n\n" : '') . trim($content);
						continue;
					}
					if (is_array($content)) {
						foreach ($content as $c) {
							if (!is_array($c)) {
								continue;
							}
							$ctype = strtolower((string) ($c['type'] ?? ''));
							// output_text y text son los más comunes
							if (($ctype === 'output_text' || $ctype === 'text') && !empty($c['text'])) {
								$buf .= ($buf ? "\n\n" : '') . trim((string) $c['text']);
							}
						}
					}
				}
				if ($buf !== '') {
					return $buf;
				}
			}

			// 3) Algunos backends devuelven message.content plano
			if (!empty($body['message']['content']) && is_string($body['message']['content'])) {
				return trim((string) $body['message']['content']);
			}

			// 4) Sin texto localizado
			return '';
		}

		/**
		 * Extracts the user's question text from the composed user message content.
		 * Falls back to original message if parsing fails. Removes CONTEXT and helper hints.
		 */
		protected function extract_question_from_user_message($user_content, $original_message)
		{
			$uc = is_string($user_content) ? (string) $user_content : '';
			if ($uc === '') {
				return (string) $original_message;
			}
			// Try to locate QUESTION: marker used by aichat_build_messages
			$pos = stripos($uc, 'QUESTION:');
			if ($pos !== false) {
				$q = trim(substr($uc, $pos + strlen('QUESTION:')));
			} else {
				// Fallback: remove CONTEXT block if present
				$q = preg_replace('/^CONTEXT:.*?\n\n/s', '', $uc);
				$q = trim((string) $q);
			}
			// Remove LINK instruction hint line (any language) if present
			if (strpos($q, '[LINK]') !== false) {
				$lines = preg_split('/\r\n|\r|\n/', $q);
				$lines = array_values(array_filter($lines, function ($ln) {
					return strpos($ln, '[LINK]') === false;
				}));
				$q = trim(implode("\n", $lines));
			}
			$q = trim((string) $q);
			if ($q === '') {
				$q = (string) $original_message;
			}
			// Cap length for logs
			if (mb_strlen($q) > 1200) {
				$q = mb_substr($q, 0, 1200) . '…';
			}

			return $q;
		}

		/**
		 * Returns a simple array of tool names/types for logging.
		 */
		protected function extract_tool_names_for_log($tools)
		{
			$out = [];
			if (is_array($tools)) {
				foreach ($tools as $t) {
					if (!is_array($t)) {
						continue;
					}
					$type = isset($t['type']) ? (string) $t['type'] : '';
					if ($type === 'function') {
						$fn = isset($t['function']) && is_array($t['function']) ? $t['function'] : [];
						$name = isset($fn['name']) ? (string) $fn['name'] : (isset($t['name']) ? (string) $t['name'] : '');
						$out[] = $name !== '' ? ('function:' . $name) : 'function';
					} elseif ($type !== '') {
						$out[] = $type;
					}
				}
			}

			return $out;
		}

		/**
		 * Builds a multi-line pretty request summary for debug.log.
		 */
		protected function format_pretty_request_log($data)
		{
			$uid = $data['uid'] ?? '-';
			$prov = $data['provider'] ?? '-';
			$model = $data['model'] ?? '-';
			$temp = isset($data['temperature']) ? (float) $data['temperature'] : null;
			$mx = isset($data['max_tokens']) ? (int) $data['max_tokens'] : null;
			$mode = $data['mode'] ?? 'auto';
			$ctx = isset($data['context_count']) ? (int) $data['context_count'] : 0;
			$hist = isset($data['history_pairs']) ? (int) $data['history_pairs'] : 0;
			$msgs = isset($data['messages_total']) ? (int) $data['messages_total'] : 0;
			$sys = is_string($data['system'] ?? null) ? (string) $data['system'] : '';
			$prm = is_string($data['prompt'] ?? null) ? (string) $data['prompt'] : '';
			$tools = is_array($data['tools'] ?? null) ? $data['tools'] : [];
			// Truncate long system text (configurable)
			// You can override via constant AICHAT_DEBUG_SYS_MAXLEN (set to 0 or negative to disable truncation)
			$maxSys = defined('AICHAT_DEBUG_SYS_MAXLEN') ? (int) constant('AICHAT_DEBUG_SYS_MAXLEN') : 1500;
			if ($maxSys > 0 && mb_strlen($sys) > $maxSys) {
				$sys = mb_substr($sys, 0, $maxSys) . '…';
			}
			// Compose
			$lines = [];
			$lines[] = "[AIChat Request][$uid]";
			$lines[] = "provider=$prov model=$model temp=" . ($temp === null ? '' : $temp) . ' max_tokens=' . ($mx === null ? '' : $mx);
			$lines[] = "mode=$mode ctx=$ctx history_pairs=$hist messages_total=$msgs";
			if (!empty($tools)) {
				$lines[] = 'tools(' . count($tools) . '): ' . implode(', ', array_slice($tools, 0, 20));
			} else {
				$lines[] = 'tools(0)';
			}
			$lines[] = "SYSTEM:\n" . $sys;
			$lines[] = "PROMPT:\n" . $prm;

			return implode("\n", $lines);
		}

		/**
		 * Builds a multi-line pretty response summary for debug.log.
		 */
		protected function format_pretty_response_log($data)
		{
			$uid = $data['uid'] ?? '-';
			$prov = $data['provider'] ?? '-';
			$model = $data['model'] ?? '-';
			$ans = is_string($data['answer'] ?? null) ? (string) $data['answer'] : '';
			$tcc = isset($data['tool_calls']) ? (int) $data['tool_calls'] : 0;
			$usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
			$tim = is_array($data['timings_ms'] ?? null) ? $data['timings_ms'] : [];
			$ans_prev = $ans;
			if (mb_strlen($ans_prev) > 800) {
				$ans_prev = mb_substr($ans_prev, 0, 800) . '…';
			}
			$lines = [];
			$lines[] = "[AIChat Response][$uid] provider=$prov model=$model tools=$tcc";
			if ($usage) {
				$lines[] = 'USAGE: ' . wp_json_encode($usage);
			}
			if ($tim) {
				$lines[] = 'TIMINGS(ms): ' . wp_json_encode($tim);
			}
			$lines[] = "ANSWER:\n" . $ans_prev;

			return implode("\n", $lines);
		}

		/**
		 * El modelo a veces imprime literalmente la salida de una tool (JSON o bloque ```json```)
		 * antes de la respuesta redactada. Este helper elimina cualquier bloque de código
		 * al inicio y también un objeto/array JSON “sueltos” al principio.
		 */
		protected function strip_leading_tool_payloads($text)
		{
			if (!is_string($text) || $text === '') {
				return $text;
			}
			$orig = $text;
			$maxIters = 2; // por si hay más de un bloque encadenado
			while ($maxIters-- > 0) {
				$text = ltrim($text);
				if ($text === '') {
					break;
				}
				// 1) Bloque con triple comillas ```...```
				if (preg_match('/^```[a-zA-Z0-9_-]*\s*\n(.*?)\n```/s', $text, $m)) {
					$before = $text;
					$candidate = trim((string) $m[1]);
					// Si el contenido parece JSON (empieza por { o [), lo recortamos
					if ($candidate !== '' && ($candidate[0] === '{' || $candidate[0] === '[')) {
						$text = (string) substr($text, strlen($m[0]));
						continue;
					} else {
						// No parece un payload JSON, no tocar
						$text = $before;
					}
				}
				// 2) Objeto/array JSON al inicio sin fence
				$first = substr($text, 0, 1);
				if ($first === '{' || $first === '[') {
					// Intentar localizar el cierre del bloque inicial y validar JSON
					$endPos = false;
					// Buscamos un doble salto de línea como posible separación entre JSON y respuesta
					$doubleNl = strpos($text, "\n\n");
					if ($doubleNl !== false) {
						$jsonChunk = substr($text, 0, $doubleNl);
						$decoded = json_decode($jsonChunk, true);
						if (is_array($decoded)) {
							// Heurística: si contiene claves típicas de tool (ok|error|slots|data)
							$keys = array_keys($decoded);
							$keysStr = implode(',', $keys);
							if (preg_match('/\bok\b|\berror\b|\bslots\b|\bdata\b/i', $keysStr)) {
								$text = substr($text, $doubleNl);
								continue;
							}
						}
					}
				}
				break; // nada que limpiar
			}
			// Si limpiamos todo y quedó vacío, retorna original para no dejar al usuario sin respuesta
			$trimmed = trim($text);
			if ($trimmed === '') {
				return $orig;
			}

			return $text;
		}
	}

	if (! class_exists('AICHAT_AJAX', false)) {
		class_alias('AIChat_Ajax', 'AICHAT_AJAX');
	}
}


// Función externa para generar respuesta del bot (para uso en themes, plugins, etc.)
function aichat_generate_bot_response($bot_slug, $message, $args = [])
{
	// $args: session_id (opcional), page_id (opcional), debug (opcional)
	if (! class_exists('AIChat_Ajax')) {
		return new WP_Error('aichat_missing', 'AI Chat not loaded');
	}

	$session_id = isset($args['session_id']) ? preg_replace('/[^a-z0-9\\-]/i', '', $args['session_id']) : '';
	if ($session_id === '') {
		$session_id = wp_generate_uuid4();
	}

	// Reutiliza internamente la lógica: sugerido refactor (extract a private core method)
	// Versión rápida: llama a un nuevo método público reducido
	return AIChat_Ajax::instance()->process_message_internal($bot_slug, $message, $session_id, $args);
}

/**
 * Genera una respuesta usando un número de teléfono externo (WhatsApp u otro canal) como base de sesión.
 * No crea tablas nuevas: simplemente usa session_id determinista "wha<hash|digits>" para agrupar historial.
 * @param string $bot_slug
 * @param string $phone  Número en formato internacional sugerido (solo dígitos y '+').
 * @param string $message Texto del usuario.
 * @param array  $args    Acepta page_id, debug. session_id se ignora (se fuerza).
 * @return array|WP_Error
 */
function aichat_generate_bot_response_for_phone($bot_slug, $phone, $message, $args = [])
{
	if (! class_exists('AIChat_Ajax')) {
		return new WP_Error('aichat_missing', 'AI Chat not loaded');
	}
	// Normalizar teléfono: dejamos dígitos y '+' inicial si existe
	$raw = trim((string) $phone);
	$normalized = preg_replace('/[^0-9+]/', '', $raw);
	if ($normalized === '') {
		return new WP_Error('aichat_phone_empty', 'Empty phone');
	}
	// Acotar longitud para prevenir abusos (guardamos máximo 24 chars visibles)
	if (strlen($normalized) > 24) {
		$normalized = substr($normalized, 0, 24);
	}
	// session determinista: wha<md5> o wha<digits>. (Antes se usaba 'wha_' con guion bajo; soportado en vistas).
	$digits_only = preg_replace('/[^0-9]/', '', $normalized);
	if ($digits_only === '') {
		$digits_only = substr(md5($normalized), 0, 10);
	}
	$session_id = 'wha' . $digits_only;
	// Forzar session_id
	$args['session_id'] = $session_id;

	return aichat_generate_bot_response($bot_slug, $message, $args);
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
