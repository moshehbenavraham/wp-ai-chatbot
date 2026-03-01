<?php

/**
 * Message Pipeline — 4-stage chat request handler.
 *
 * Stage 1: Pre-Processing
 *   Nonce · sanitize · bot resolve · session resolve · ownership ·
 *   honeypot · rate limit · validate · duplicate detect · moderate · quota
 *
 * Stage 2: Context Assembly
 *   History · page context · RAG context · system prompt · message array
 *
 * Stage 3: Provider Dispatch
 *   Provider resolve · params · streaming decision · sync or SSE dispatch ·
 *   tool continuation two-phase flow
 *
 * Stage 4: Post-Processing
 *   Error handling · sanitize response · cost calculation ·
 *   log to DB · session aggregate update · response assembly
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Runtime;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Message_Pipeline
 *
 * @since 1.0.0
 * @package WPAIC\Runtime
 */
class Message_Pipeline
{
	/** Maximum message length (chars). Configurable per-bot. */
	public const MAX_MESSAGE_LENGTH = 4000;

	/** Default message length cap. */
	public const DEFAULT_MAX_LENGTH = 512;

	/** Duplicate-detection dedup window in seconds. */
	public const DEDUP_WINDOW = 10;

	/** Default history depth (messages). */
	public const DEFAULT_HISTORY_DEPTH = 10;

	/** Default system prompt. */
	public const DEFAULT_SYSTEM_PROMPT = 'You are a helpful AI assistant.';

	// =========================================================================
	// Entry point
	// =========================================================================

	/**
	 * Static entry point called from Plugin::on_ajax_chat().
	 *
	 * Handles both sync JSON responses and SSE streaming responses.
	 * Always terminates with wp_send_json_*() or direct SSE output + die().
	 *
	 * @return void
	 */
	public static function handle_ajax(): void
	{
		// Stage 1: Pre-Processing.
		$pre = self::stage_preprocess();

		if (is_wp_error($pre)) {
			$error_code = $pre->get_error_code();
			$error_data = $pre->get_error_data($error_code);
			$response_payload = [
				'message' => $pre->get_error_message(),
				'code' => $error_code,
			];

			// Merge extra data for rate limit errors (fallback contacts, etc.).
			if ('wpaic_rate_limit' === $error_code && is_array($error_data)) {
				$response_payload = array_merge($response_payload, $error_data);
			}

			wp_send_json_error($response_payload, self::error_http_status($error_code));

			return; // wp_send_json_error() calls wp_die()
		}

		// Stage 2: Context Assembly.
		$context = self::stage_context($pre);

		// Stage 2.5: Macro Router intercept (Phase 17).
		// Hook `wpaic_macro_route` to short-circuit the provider call.
		// Return a non-empty array with a `message` key to bypass Stage 3.
		$intercept = (array) apply_filters('wpaic_macro_route', [], $pre['message'], $pre, $context);

		if (! empty($intercept['message'])) {
			self::stage_postprocess($pre, $intercept);

			return;
		}

		// Stage 3: Provider Dispatch.
		$dispatch = self::stage_dispatch($pre, $context);

		// Stage 4: Post-Processing (handles error responses from dispatch too).
		self::stage_postprocess($pre, $dispatch);
	}

	// =========================================================================
	// Stage 1: Pre-Processing
	// =========================================================================

	/**
	 * Run all pre-processing checks.
	 *
	 * Returns a keyed data array on success, WP_Error on any failure.
	 *
	 * @return array|WP_Error
	 */
	private static function stage_preprocess()
	{
		// 1.1: Nonce verification.
		$nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
		if (! wp_verify_nonce($nonce, 'wpaic_chat_nonce')) {
			return new \WP_Error('wpaic_nonce', __('Security check failed. Please refresh the page.', 'wp-ai-chatbot'));
		}

		// 1.2: Input sanitization.
		$message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
		$bot_slug = isset($_POST['bot_slug']) ? sanitize_key(wp_unslash($_POST['bot_slug'])) : 'default';
		$session_tok = isset($_POST['session_token']) ? sanitize_text_field(wp_unslash($_POST['session_token'])) : '';
		$page_url = isset($_POST['page_url']) ? esc_url_raw(wp_unslash($_POST['page_url'])) : '';
		$page_title = isset($_POST['page_title']) ? sanitize_text_field(wp_unslash($_POST['page_title'])) : '';
		$stream_req = isset($_POST['stream']) && '1' === $_POST['stream'];
		$honeypot = isset($_POST['wpaic_hp']) ? $_POST['wpaic_hp'] : null;

		// 1.3: Bot resolution.
		$bot = self::resolve_bot($bot_slug);
		if (is_wp_error($bot)) {
			return $bot;
		}

		// 1.4: Session resolution / creation.
		$context = Session_Manager::build_ajax_context();
		$session = self::resolve_session($session_tok, $context, $bot);
		if (is_wp_error($session)) {
			return $session;
		}

		// 1.5: Ownership validation (already inside Session_Manager::resume(); secondary check).
		// — covered by resolve_session().

		// 1.6: Honeypot check — silent success on hit (bot filled the hidden field).
		if (null !== $honeypot && '' !== $honeypot) {
			// Return a plausible-looking success response to deceive bots.
			wp_send_json_success(['html' => '', 'suggestions' => []], 200);

			return new \WP_Error('wpaic_honeypot', 'honeypot');
		}

		// 1.7: Rate limiting via lib/rate-limiter.php.
		// wpaic_check_rate_limit(namespace, max_requests, window_seconds): array{allowed:bool,...}
		if (function_exists('wpaic_check_rate_limit')) {
			$limit = (int) get_option('wpaic_security_rate_limit', 60);
			$window = (int) get_option('wpaic_security_rate_window', 60);
			$ip = Session_Manager::get_client_ip();
			$rl = wpaic_check_rate_limit("wpaic_chat_{$bot_slug}", $limit, $window);
			$passed = isset($rl['allowed']) ? (bool) $rl['allowed'] : true;

			/**
			 * Filter rate-limit check result.
			 *
			 * @since 1.0.0
			 * @param bool   $passed   Whether request is within limit.
			 * @param string $bot_slug Bot slug.
			 * @param string $ip       Client IP address.
			 */
			$passed = (bool) apply_filters('wpaic_rate_limit_check', $passed, $bot_slug, $ip);

			if (! $passed) {
				// Build configurable rate limit message with [email] placeholder substitution.
				$rl_message = (string) get_option(
					'wpaic_rate_limit_message',
					__("I've reached my message limit. Please try again shortly.", 'wp-ai-chatbot')
				);
				$fallback_email = (string) get_option('wpaic_rate_limit_fallback_email', '');
				if ('' !== $fallback_email) {
					$rl_message = str_replace('[email]', $fallback_email, $rl_message);
				} else {
					$rl_message = str_replace(' or contact us at [email]', '', $rl_message);
					$rl_message = str_replace(' or contact us at [email].', '.', $rl_message);
				}

				// Build error with fallback contact data for the frontend.
				$error = new \WP_Error('wpaic_rate_limit', $rl_message);
				$error->add_data([
					'code' => 'wpaic_rate_limit',
					'message' => $rl_message,
					'fallback_email' => $fallback_email,
					'fallback_phone' => (string) get_option('wpaic_rate_limit_fallback_phone', ''),
					'fallback_url' => (string) get_option('wpaic_rate_limit_fallback_url', ''),
				]);

				return $error;
			}
		}

		// 1.8: Message validation.
		if ('' === $message) {
			return new \WP_Error('wpaic_empty_message', __('Message cannot be empty.', 'wp-ai-chatbot'));
		}

		$max_len = min(
			(int) ($bot['max_message_length'] ?? self::DEFAULT_MAX_LENGTH),
			self::MAX_MESSAGE_LENGTH
		);

		if (mb_strlen($message) > $max_len) {
			return new \WP_Error(
				'wpaic_message_too_long',
				sprintf(
					// translators: %d: character limit.
					__('Message exceeds the %d character limit.', 'wp-ai-chatbot'),
					$max_len
				)
			);
		}

		/**
		 * Filter: allow extensions to fail validation with a WP_Error.
		 *
		 * @since 1.0.0
		 * @param true   $valid   Pass true to allow, WP_Error to reject.
		 * @param string $message Sanitized message.
		 * @param array  $bot     Bot config.
		 */
		$validation = apply_filters('wpaic_validate_message', true, $message, $bot);
		if (is_wp_error($validation)) {
			return $validation;
		}

		// 1.9: Duplicate detection (10-second dedup window per session).
		$dedup_key = 'wpaic_dedup_' . $session['id'] . '_' . md5($message);
		$is_dup = get_transient($dedup_key);
		if (false !== $is_dup) {
			return new \WP_Error('wpaic_duplicate', __('Duplicate message detected. Please wait before resending.', 'wp-ai-chatbot'));
		}
		set_transient($dedup_key, '1', self::DEDUP_WINDOW);

		// 1.10: Moderation hook chain.
		$moderation = self::run_moderation($message, $bot, $session);
		if (is_wp_error($moderation)) {
			return $moderation;
		}

		// 1.11: Usage limit check.
		$quota = self::check_quota($bot, $session);
		if (is_wp_error($quota)) {
			return $quota;
		}

		return [
			'message' => $message,
			'bot' => $bot,
			'session' => $session,
			'context' => $context,
			'page_url' => $page_url,
			'page_title' => $page_title,
			'stream' => $stream_req && (bool) get_option('wpaic_runtime_stream_enabled', '1'),
		];
	}

	// =========================================================================
	// Stage 2: Context Assembly
	// =========================================================================

	/**
	 * Assemble the full message array for the provider.
	 *
	 * @param  array $pre Pre-processing result.
	 * @return array Keyed context data.
	 */
	private static function stage_context(array $pre): array
	{
		$session = $pre['session'];
		$bot = $pre['bot'];
		$message = $pre['message'];

		// 2.1: History retrieval.
		$depth = (int) ($bot['history_depth'] ?? self::DEFAULT_HISTORY_DEPTH);
		$raw_history = Session_Manager::get_history((int) $session['id'], $depth, $pre['context']);
		$history = is_wp_error($raw_history) ? [] : (array) $raw_history;

		// 2.2: Page context injection.
		$page_context = [
			'url' => $pre['page_url'],
			'title' => $pre['page_title'],
		];

		/**
		 * Filter: extensions can enrich page context (content extraction, schema, etc.).
		 *
		 * @since 1.0.0
		 * @param array $page_context {url, title, content?, ...}
		 * @param array $pre          Pre-processing data.
		 */
		$page_context = (array) apply_filters('wpaic_inject_page_context', $page_context, $pre);

		// 2.3: RAG context retrieval (Phase 06 hook point).
		$rag_context = '';

		/**
		 * Filter: Phase 06 RAG module injects retrieved context here.
		 *
		 * @since 1.0.0
		 * @param string $rag_context Empty string until Phase 06.
		 * @param string $message     User message.
		 * @param array  $bot         Bot configuration.
		 */
		$rag_context = (string) apply_filters('wpaic_rag_context', $rag_context, $message, $bot);

		// 2.4: System prompt assembly.
		$system_prompt = self::build_system_prompt($bot, $page_context, $rag_context);

		// 2.5: Message array construction.
		$messages = self::build_message_array($history, $message, $system_prompt);

		/**
		 * Filter: extensions can inspect or alter the final message array.
		 *
		 * @since 1.0.0
		 * @param array  $messages      Message array.
		 * @param array  $pre           Pre-processing data.
		 * @param string $system_prompt Assembled system prompt.
		 */
		$messages = (array) apply_filters('wpaic_messages_before_provider', $messages, $pre, $system_prompt);

		/**
		 * Action: intercept point for extensions that want to bypass normal dispatch.
		 * Fire wpaic_pipeline_intercepted to short-circuit the provider call.
		 *
		 * @since 1.0.0
		 * @param array $messages
		 * @param array $pre
		 */
		do_action('wpaic_before_provider_dispatch', $messages, $pre);

		return [
			'messages' => $messages,
			'system_prompt' => $system_prompt,
			'history' => $history,
			'page_context' => $page_context,
			'rag_context' => $rag_context,
		];
	}

	// =========================================================================
	// Stage 3: Provider Dispatch
	// =========================================================================

	/**
	 * Resolve provider, build params, and dispatch.
	 *
	 * @param  array $pre     Pre-processing data.
	 * @param  array $context Context assembly data.
	 * @return array Provider response array (may contain 'error' key).
	 */
	private static function stage_dispatch(array $pre, array $context): array
	{
		$bot = $pre['bot'];
		$messages = $context['messages'];

		// 3.1: Provider resolution.
		$provider_id = $bot['provider_id'] ?? get_option('wpaic_provider_default', 'openai');

		/**
		 * Filter: extensions can override the selected provider.
		 *
		 * @since 1.0.0
		 * @param string $provider_id
		 * @param array  $bot
		 * @param array  $pre
		 */
		$provider_id = (string) apply_filters('wpaic_resolve_provider', $provider_id, $bot, $pre);

		// Resolve the provider instance via the registry.
		$registry = \WPAIC\Provider\Provider_Registry::instance();
		$provider = $registry->get($provider_id);

		if (! $provider) {
			return [
				'error' => [
					'code' => 503,
					'type' => 'provider_unavailable',
					'message' => 'Provider not found: ' . $provider_id,
					'retryable' => false,
					'user_message' => __('The AI provider is temporarily unavailable. Please try again later.', 'wp-ai-chatbot'),
				],
			];
		}

		// 3.2: Parameter assembly.
		$model_id = $bot['model_id'] ?? get_option('wpaic_model_default', 'gpt-4o-mini');
		$params = [
			'model' => $model_id,
			'temperature' => (float) ($bot['temperature'] ?? 0.7),
			'max_tokens' => (int) get_option('wpaic_runtime_max_tokens', 2048),
			'system_prompt' => $context['system_prompt'],
			'request_id' => wp_generate_uuid4(),
			'session_id' => $pre['session']['session_token'],
		];

		// 3.2.1: Inject registered tools when tool execution is globally enabled
		// and the bot has at least one tool enabled.
		if (get_option('wpaic_tools_enabled', '0') && class_exists('WPAIC\Tools\Tool_Registry')) {
			$tool_defs = \WPAIC\Tools\Tool_Registry::instance()->get_definitions_for_bot(
				$bot['slug'] ?? 'default',
				$bot
			);

			if (! empty($tool_defs)) {
				$params['tools'] = $tool_defs;
			}
		}

		/**
		 * Filter: extensions can modify dispatch parameters (model, temperature, tools, etc.).
		 *
		 * @since 1.0.0
		 * @param array  $params
		 * @param array  $bot
		 * @param array  $pre
		 */
		$params = (array) apply_filters('wpaic_provider_params', $params, $bot, $pre);

		// 3.3: Streaming decision.
		// Note: tool continuation is not supported in streaming mode (SSE).
		// When tools are present, fall back to synchronous dispatch.
		$has_tools = ! empty($params['tools']);
		$do_stream = ! $has_tools
			&& $pre['stream']
			&& ($provider instanceof \WPAIC\Provider\Streamable_Provider_Interface)
			&& $provider->supports_streaming();

		// 3.4a: Streaming dispatch (no tools in params).
		if ($do_stream) {
			self::dispatch_stream($provider, $messages, $params, $pre);

			// dispatch_stream() terminates via die(); execution never returns.
			return []; // unreachable, satisfies static analysis.
		}

		// 3.4b: Synchronous dispatch.
		$response = $provider->chat($messages, $params);

		// 3.5: Tool continuation loop — fires when provider requests tool execution.
		if (
			! isset($response['error'])
			&& class_exists('WPAIC\Tools\Tool_Continuation')
			&& \WPAIC\Tools\Tool_Continuation::is_tool_call_response($response)
		) {
			$max_loops = (int) get_option('wpaic_tools_max_loops', 5);
			$response = \WPAIC\Tools\Tool_Continuation::run(
				$provider,
				$messages,
				$params,
				$pre,
				$response,
				$max_loops
			);
		}

		return $response;
	}

	// =========================================================================
	// Stage 4: Post-Processing
	// =========================================================================

	/**
	 * Handle provider response, format, log, and send JSON response.
	 *
	 * @param  array $pre      Pre-processing data.
	 * @param  array $dispatch Provider dispatch response.
	 * @return void
	 */
	private static function stage_postprocess(array $pre, array $dispatch): void
	{
		$session = $pre['session'];
		$bot = $pre['bot'];

		// 4.1: Error handling.
		if (isset($dispatch['error'])) {
			$err = $dispatch['error'];

			/**
			 * Filter: extensions can intercept and transform provider errors.
			 *
			 * @since 1.0.0
			 * @param array $err      Error payload.
			 * @param array $pre      Pre-processing data.
			 */
			$err = (array) apply_filters('wpaic_provider_error', $err, $pre);

			wp_send_json_error([
				'message' => $err['user_message'] ?? __('An error occurred. Please try again.', 'wp-ai-chatbot'),
				'code' => $err['type'] ?? 'provider_error',
			], (int) ($err['code'] ?? 500));

			return;
		}

		$raw_response = $dispatch['message'] ?? '';
		$usage = $dispatch['usage'] ?? [];
		$finish = $dispatch['finish_reason'] ?? 'stop';
		$actual_model = $dispatch['model_id'] ?? '';
		$provider_id = $dispatch['provider_id'] ?? '';

		// 4.2 & 4.3: Sanitize response and extract suggestions.
		$format_html = (bool) ($bot['markdown_enabled'] ?? true);
		$formatted = Output_Formatter::format($raw_response, $format_html);
		$html = $formatted['html'];
		$suggestions = $formatted['suggestions'];

		// 4.4: Cost calculation.
		$cost_micro = 0;
		if (! empty($provider_id) && ! empty($actual_model) && ! empty($usage)) {
			$registry = \WPAIC\Provider\Provider_Registry::instance();
			$provider = $registry->get($provider_id);
			if ($provider) {
				$cost_micro = (int) ($provider->calculate_cost($usage, $actual_model) ?? 0);
			}
		}

		// 4.5: Conversation logging.
		$log_data = [
			'session_id' => (int) $session['id'],
			'user_message' => $pre['message'],
			'bot_response' => $html,
			'usage' => $usage,
			'cost_micro' => $cost_micro,
			'provider_id' => $provider_id,
			'model_id' => $actual_model,
			'tool_history' => $dispatch['tool_history'] ?? [],
		];

		/**
		 * Action: fires before message is logged to the DB.
		 * Extensions can modify log data or perform side effects.
		 *
		 * @since 1.0.0
		 * @param array $log_data
		 */
		do_action('wpaic_before_log_message', $log_data);

		Session_Manager::log_message(
			$log_data['session_id'],
			$log_data['user_message'],
			$log_data['bot_response'],
			$log_data['usage'],
			$log_data['cost_micro'],
			$log_data['provider_id'],
			$log_data['model_id'],
			[],
			$log_data['tool_history']
		);

		/**
		 * Action: fires after usage is recorded; cost accounting hooks here.
		 *
		 * @since 1.0.0
		 * @param array $usage       Token usage.
		 * @param int   $cost_micro  Cost in microcents.
		 * @param array $log_data    Full log data.
		 */
		do_action('wpaic_usage_recorded', $usage, $cost_micro, $log_data);

		// 4.7: Response assembly.
		$response_data = [
			'html' => $html,
			'suggestions' => $suggestions,
			'finish_reason' => $finish,
			'session_token' => $session['session_token'],
		];

		/**
		 * Filter: extensions can add fields to the final response payload.
		 *
		 * @since 1.0.0
		 * @param array $response_data
		 * @param array $dispatch
		 * @param array $pre
		 */
		$response_data = (array) apply_filters('wpaic_response_data', $response_data, $dispatch, $pre);

		wp_send_json_success($response_data, 200);
	}

	// =========================================================================
	// SSE Streaming dispatch
	// =========================================================================

	/**
	 * Stream a response via Server-Sent Events and exit.
	 *
	 * Sends SSE headers, pushes normalized chunks from the provider, and
	 * logs the full assembled response after the stream ends.
	 *
	 * @param  \WPAIC\Provider\Streamable_Provider_Interface $provider
	 * @param  array $messages  Message array.
	 * @param  array $params    Provider params.
	 * @param  array $pre       Pre-processing data.
	 * @return void (terminates with die())
	 */
	private static function dispatch_stream(
		\WPAIC\Provider\Streamable_Provider_Interface $provider,
		array $messages,
		array $params,
		array $pre
	): void {
		// Set SSE headers.
		if (! headers_sent()) {
			header('Content-Type: text/event-stream');
			header('Cache-Control: no-cache');
			header('X-Accel-Buffering: no'); // Disable Nginx buffering.
		}

		// Disable PHP output buffering.
		if (function_exists('ob_end_clean') && ob_get_level() > 0) {
			ob_end_clean();
		}

		$full_text = '';
		$usage = [];
		$model_id = $params['model'] ?? '';
		$provider_id = $provider->get_id();
		$error = null;

		// Chunk callback: emit each delta as an SSE data event.
		$chunk_callback = static function (array $chunk) use (&$full_text): void {
			$text = $chunk['text'] ?? '';
			if ('' !== $text) {
				$full_text .= $text;
				$payload = wp_json_encode(['text' => $text, 'type' => 'delta']);
				echo 'data: ' . $payload . "\n\n";
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				flush();
			}

			// Capture usage from chunk metadata if provider includes it.
			// (Claude/Gemini may pass usage in final chunk.)
		};

		// Dispatch the stream.
		try {
			$result = $provider->stream_chat($messages, $params, $chunk_callback);
			if (isset($result['usage'])) {
				$usage = $result['usage'];
			}
			if (isset($result['model_id'])) {
				$model_id = $result['model_id'];
			}
		} catch (\Exception $e) {
			$error = $e->getMessage();
		}

		// If there was an error, emit an error event.
		if ($error) {
			$payload = wp_json_encode(['error' => __('Stream error. Please try again.', 'wp-ai-chatbot')]);
			echo 'data: ' . $payload . "\n\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			flush();
			echo "data: [DONE]\n\n";
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			flush();
			die();
		}

		// Format the full accumulated response.
		$bot = $pre['bot'];
		$format_html = (bool) ($bot['markdown_enabled'] ?? true);
		$formatted = Output_Formatter::format($full_text, $format_html);
		$html = $formatted['html'];
		$suggestions = $formatted['suggestions'];

		// Log the complete message.
		$cost_micro = 0;
		if (! empty($provider_id) && ! empty($model_id) && ! empty($usage)) {
			$reg = \WPAIC\Provider\Provider_Registry::instance();
			$prov = $reg->get($provider_id);
			if ($prov) {
				$cost_micro = (int) ($prov->calculate_cost($usage, $model_id) ?? 0);
			}
		}

		Session_Manager::log_message(
			(int) $pre['session']['id'],
			$pre['message'],
			$html,
			$usage,
			$cost_micro,
			$provider_id,
			$model_id
		);

		do_action('wpaic_usage_recorded', $usage, $cost_micro, [
			'session_id' => (int) $pre['session']['id'],
			'provider_id' => $provider_id,
			'model_id' => $model_id,
		]);

		// Emit finalization event with full HTML + suggestions.
		$final = wp_json_encode([
			'type' => 'done',
			'html' => $html,
			'suggestions' => $suggestions,
			'session_token' => $pre['session']['session_token'],
		]);

		echo 'data: ' . $final . "\n\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		flush();
		echo "data: [DONE]\n\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		flush();

		die();
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Resolve bot configuration by slug.
	 *
	 * Falls back to default bot if slug not found.
	 *
	 * @param  string $slug Bot slug (sanitized key).
	 * @return array|WP_Error Bot config array or WP_Error.
	 */
	private static function resolve_bot(string $slug)
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_bots';
		$bot = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$table} WHERE slug = %s AND status = 'active' LIMIT 1", $slug),
			ARRAY_A
		);

		// Fall back to first active bot.
		if (! $bot) {
			$bot = $wpdb->get_row(
				"SELECT * FROM {$table} WHERE status = 'active' LIMIT 1",
				ARRAY_A
			);
		}

		if (! $bot) {
			// No bot configured — return minimal defaults so pipeline can continue.
			$bot = [
				'id' => 0,
				'slug' => 'default',
				'name' => 'Assistant',
				'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
				'provider_id' => get_option('wpaic_provider_default', 'openai'),
				'model_id' => get_option('wpaic_model_default', 'gpt-4o-mini'),
				'temperature' => 0.7,
				'history_depth' => self::DEFAULT_HISTORY_DEPTH,
				'max_message_length' => self::DEFAULT_MAX_LENGTH,
				'markdown_enabled' => true,
				'gdpr_enabled' => false,
			];
		} else {
			// Decode JSON config fields stored in DB.
			if (! empty($bot['config'])) {
				$extra = json_decode($bot['config'], true);
				if (is_array($extra)) {
					$bot = array_merge($bot, $extra);
				}
			}
		}

		/**
		 * Filter: extensions can substitute or enrich the bot configuration.
		 *
		 * @since 1.0.0
		 * @param array  $bot  Bot config.
		 * @param string $slug Requested slug.
		 */
		return (array) apply_filters('wpaic_resolve_bot', $bot, $slug);
	}

	/**
	 * Resolve or create a session from a token string.
	 *
	 * @param  string $token   UUID v4 token from frontend (may be empty on first load).
	 * @param  array  $context Ownership context.
	 * @param  array  $bot     Resolved bot config.
	 * @return array|WP_Error  Session row or WP_Error.
	 */
	private static function resolve_session(string $token, array $context, array $bot)
	{
		// No token — create a fresh session.
		if ('' === $token || ! Session_Manager::is_valid_uuid($token)) {
			return Session_Manager::create([
				'bot_id' => (int) ($bot['id'] ?? 0),
				'user_id' => $context['user_id'],
				'ip_hash' => $context['ip_hash'],
				'ua_hash' => $context['ua_hash'],
			]);
		}

		// Try to resume existing session.
		$session = Session_Manager::resume($token, $context);

		/**
		 * Filter: extensions can substitute session resolution logic.
		 *
		 * @since 1.0.0
		 * @param array|WP_Error $session   Resolved session or error.
		 * @param string         $token     Raw token.
		 * @param array          $context   Ownership context.
		 * @param array          $bot       Bot config.
		 */
		$session = apply_filters('wpaic_resolve_session', $session, $token, $context, $bot);

		// If resume failed (e.g., session expired) create a new one silently.
		if (is_wp_error($session)) {
			$session = Session_Manager::create([
				'bot_id' => (int) ($bot['id'] ?? 0),
				'user_id' => $context['user_id'],
				'ip_hash' => $context['ip_hash'],
				'ua_hash' => $context['ua_hash'],
			]);
		}

		return $session;
	}

	/**
	 * Run the moderation hook chain.
	 *
	 * Extensions register `wpaic_moderate_message` filters returning WP_Error to block.
	 *
	 * @param  string $message Sanitized user message.
	 * @param  array  $bot     Bot config.
	 * @param  array  $session Session row.
	 * @return true|WP_Error
	 */
	private static function run_moderation(string $message, array $bot, array $session)
	{
		if (! get_option('wpaic_security_moderation_enabled', '0')) {
			return true;
		}

		/**
		 * Filter: moderation chain. Return WP_Error to block.
		 * Use priorities 5 (fast, local), 10 (default), 15 (external API).
		 *
		 * @since 1.0.0
		 * @param true   $pass    Pass-through true; blocked = WP_Error.
		 * @param string $message Sanitized message.
		 * @param array  $bot     Bot config.
		 * @param array  $session Session row.
		 */
		$result = apply_filters('wpaic_moderate_message', true, $message, $bot, $session);

		if (is_wp_error($result)) {
			return $result;
		}

		return true;
	}

	/**
	 * Check daily usage quota for the bot.
	 *
	 * @param  array $bot     Bot config.
	 * @param  array $session Session row.
	 * @return true|WP_Error
	 */
	private static function check_quota(array $bot, array $session): bool
	{
		// Quota enforcement is a Phase 07+ concern; hook provided for extensions.

		/**
		 * Filter: quota check. Return WP_Error to block when limit reached.
		 *
		 * @since 1.0.0
		 * @param true  $pass Pass-through; blocked = WP_Error.
		 * @param array $bot
		 * @param array $session
		 */
		$result = apply_filters('wpaic_check_quota', true, $bot, $session);

		if (is_wp_error($result)) {
			return $result;
		}

		return true;
	}

	/**
	 * Build the system prompt for the provider.
	 *
	 * @param  array  $bot          Bot config.
	 * @param  array  $page_context Page context (url, title, content).
	 * @param  string $rag_context  Retrieved RAG context text.
	 * @return string
	 */
	private static function build_system_prompt(array $bot, array $page_context, string $rag_context): string
	{
		$base = ! empty($bot['system_prompt']) ? $bot['system_prompt'] : self::DEFAULT_SYSTEM_PROMPT;

		// Append page context if available.
		if (! empty($page_context['title']) || ! empty($page_context['url'])) {
			$base .= "\n\n---\nPage: " . ($page_context['title'] ?? '');
			if (! empty($page_context['url'])) {
				$base .= ' (' . $page_context['url'] . ')';
			}
		}

		// Append RAG context block.
		if ('' !== $rag_context) {
			$base .= "\n\n---\n" . __('Relevant context from the knowledge base:', 'wp-ai-chatbot') . "\n" . $rag_context;
		}

		// Append current date/time for bots that need it.
		if (! empty($bot['inject_datetime'])) {
			$base .= "\n\n---\nCurrent date/time: " . current_time('Y-m-d H:i') . ' (' . wp_timezone_string() . ')';
		}

		/**
		 * Filter: extensions can augment or replace the assembled system prompt.
		 *
		 * @since 1.0.0
		 * @param string $base         Assembled system prompt.
		 * @param array  $bot          Bot config.
		 * @param array  $page_context Page context.
		 * @param string $rag_context  RAG context.
		 */
		return (string) apply_filters('wpaic_system_prompt', $base, $bot, $page_context, $rag_context);
	}

	/**
	 * Build the normalised message array from history + current message.
	 *
	 * Converts DB history rows into provider-normalised message format.
	 *
	 * @param  array  $history       Session message rows.
	 * @param  string $user_message  Current user message.
	 * @param  string $system_prompt System prompt.
	 * @return array  Normalised message array.
	 */
	private static function build_message_array(
		array $history,
		string $user_message,
		string $system_prompt
	): array {
		$messages = [];

		// System message first.
		if ('' !== $system_prompt) {
			$messages[] = [
				'role' => 'system',
				'content' => $system_prompt,
			];
		}

		// Conversation history (user/assistant pairs from DB).
		foreach ($history as $row) {
			if (! empty($row['role_user'])) {
				$messages[] = ['role' => 'user', 'content' => $row['role_user']];
			}
			if (! empty($row['role_assistant'])) {
				// Strip HTML from stored assistant messages — providers expect plain text.
				$plain = wp_strip_all_tags($row['role_assistant']);
				$messages[] = ['role' => 'assistant', 'content' => $plain];
			}
		}

		// Current user message.
		$messages[] = ['role' => 'user', 'content' => $user_message];

		return $messages;
	}

	/**
	 * Map WP_Error codes to HTTP status codes.
	 *
	 * @param  string $code WP_Error code.
	 * @return int HTTP status.
	 */
	private static function error_http_status(string $code): int
	{
		$map = [
			'wpaic_nonce' => 403,
			'wpaic_rate_limit' => 429,
			'wpaic_duplicate' => 429,
			'wpaic_empty_message' => 400,
			'wpaic_message_too_long' => 400,
			'wpaic_session_ownership' => 403,
			'wpaic_session_expired' => 401,
		];

		return $map[$code] ?? 400;
	}
}
