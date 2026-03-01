<?php

/**
 * Tool Continuation — two-phase tool execution loop for the Message Pipeline.
 *
 * When a provider returns finish_reason=tool_calls, this class:
 *   1. Extracts the tool_call requests from the provider response.
 *   2. Executes each tool via Tool_Executor.
 *   3. Appends normalised tool-result messages to the conversation.
 *   4. Re-dispatches to the same provider (up to MAX_LOOPS iterations).
 *   5. Returns when the provider returns finish_reason=stop (or on error/limit).
 *
 * Normalised tool-result messages use a special format that each provider
 * adapter should translate to its native format. For adapters that don't
 * handle the 'tool_result' role, the messages degrade gracefully to user-role
 * messages that include the function name and output inline.
 *
 * Filter points:
 *   wpaic_extract_tool_calls  — provider-specific extraction of tool_calls array
 *   wpaic_tool_result_message — build a normalised tool-result message entry
 *   wpaic_tool_continuation_context — enrich context before each continuation loop
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Tools;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Tool_Continuation
 *
 * @since 1.0.0
 * @package WPAIC\Tools
 */
final class Tool_Continuation
{
	/** Maximum number of tool continuation loops before aborting (anti-loop guard). */
	public const MAX_LOOPS = 5;

	// =========================================================================
	// Entry point
	// =========================================================================

	/**
	 * Run the tool continuation loop.
	 *
	 * Called from Message_Pipeline::stage_dispatch() when the first provider
	 * response indicates finish_reason=tool_calls.
	 *
	 * @param  \WPAIC\Provider\Provider_Interface $provider  Provider that returned tool_calls.
	 * @param  array                              $messages  Current normalised message array.
	 * @param  array                              $params    Provider dispatch params.
	 * @param  array                              $pre       Pre-processing data from Stage 1.
	 * @param  array                              $initial   Initial dispatch response (with tool_calls).
	 * @param  int                                $max_loops Max continuation loops (default: MAX_LOOPS).
	 * @return array Final provider response (finish_reason=stop) or error array.
	 */
	public static function run(
		\WPAIC\Provider\Provider_Interface $provider,
		array $messages,
		array $params,
		array $pre,
		array $initial,
		int $max_loops = self::MAX_LOOPS
	): array {
		$response = $initial;
		$loops = 0;
		$max_loops = max(1, min(self::MAX_LOOPS, $max_loops));

		while (self::is_tool_call_response($response) && $loops < $max_loops) {
			$loops++;

			// Extract tool calls from the provider response.
			$tool_calls = self::extract_tool_calls($response, $provider->get_id());

			if (empty($tool_calls)) {
				break; // No actionable tool calls found — stop looping.
			}

			// Build the execution context from pipeline pre-processing data.
			$context = self::build_execution_context($pre, $loops);

			// Execute all tool calls in this batch.
			$executor = Tool_Executor::instance();
			$batch = $executor->execute_batch($tool_calls, $context);

			// Append the assistant's tool-call request to the message array.
			$messages[] = self::build_assistant_tool_message($response, $provider->get_id());

			// Append each tool result message.
			foreach ($batch as $item) {
				$messages[] = self::build_tool_result_message(
					$item['call_id'],
					$item['tool_id'],
					$item['result'],
					$provider->get_id()
				);
			}

			/**
			 * Filter: extensions can inspect/modify the message array before each continuation.
			 *
			 * @since 1.0.0
			 * @param array  $messages Current message array.
			 * @param array  $context  Execution context.
			 * @param int    $loops    Current loop count.
			 */
			$messages = (array) apply_filters('wpaic_tool_continuation_messages', $messages, $context, $loops);

			// Re-dispatch to the provider with the updated message array.
			$response = $provider->chat($messages, $params);

			if (isset($response['error'])) {
				return $response; // Provider error — surface to Stage 4.
			}
		}

		// Add continuation metadata to the final response.
		$response['tool_loops'] = $loops;

		return $response;
	}

	// =========================================================================
	// Response detection
	// =========================================================================

	/**
	 * Determine whether a provider response is requesting tool execution.
	 *
	 * @param  array $response Provider response.
	 * @return bool
	 */
	public static function is_tool_call_response(array $response): bool
	{
		if (isset($response['error'])) {
			return false;
		}

		$finish = $response['finish_reason'] ?? 'stop';

		// Standard finish reasons that indicate tool use.
		if ('tool_calls' === $finish || 'tool_use' === $finish) {
			return true;
		}

		// Response contains tool_calls array even if finish reason is 'stop'
		// (some providers behave this way).
		if (! empty($response['tool_calls']) && is_array($response['tool_calls'])) {
			return true;
		}

		return false;
	}

	// =========================================================================
	// Message builders
	// =========================================================================

	/**
	 * Build the normalised "assistant tool-call request" message.
	 *
	 * Provider adapters translate this based on the 'role' key:
	 *   OpenAI  — role=assistant, tool_calls=[...]
	 *   Claude  — role=assistant, content=[{type:tool_use,...}]
	 *   Generic — passthrough as-is
	 *
	 * @param  array  $response    Provider response containing tool_calls.
	 * @param  string $provider_id Provider identifier.
	 * @return array  Normalised message entry.
	 */
	private static function build_assistant_tool_message(array $response, string $provider_id): array
	{
		$tool_calls = self::extract_tool_calls($response, $provider_id);

		/**
		 * Filter: build the assistant tool-call message in provider-native format.
		 *
		 * @since 1.0.0
		 * @param array  $message     Default normalised message.
		 * @param array  $response    Raw provider response.
		 * @param string $provider_id Provider identifier.
		 */
		return (array) apply_filters(
			'wpaic_build_assistant_tool_message',
			[
				'role' => 'assistant',
				'content' => $response['message'] ?? null,
				'tool_calls' => $tool_calls,
			],
			$response,
			$provider_id
		);
	}

	/**
	 * Build the normalised "tool result" message for a single tool execution.
	 *
	 * @param  string $call_id     Provider-issued call ID.
	 * @param  string $tool_id     Tool identifier.
	 * @param  array  $result      Tool_Executor result.
	 * @param  string $provider_id Provider identifier.
	 * @return array  Normalised message entry.
	 */
	private static function build_tool_result_message(
		string $call_id,
		string $tool_id,
		array $result,
		string $provider_id
	): array {
		$content = $result['ok']
			? (string) $result['output']
			: sprintf('[Tool error: %s]', $result['message'] ?? $result['error'] ?? 'unknown');

		/**
		 * Filter: build a tool-result message in provider-native format.
		 *
		 * Default format is provider-agnostic; specific adapters may override:
		 *   OpenAI: role=tool, tool_call_id=call_id, content=...
		 *   Claude: role=user, content=[{type:tool_result, tool_use_id:..., content:...}]
		 *
		 * @since 1.0.0
		 * @param array  $message     Default normalised tool-result message.
		 * @param string $call_id     Provider-issued call ID.
		 * @param string $tool_id     Tool identifier.
		 * @param array  $result      Tool execution result.
		 * @param string $provider_id Provider identifier.
		 */
		return (array) apply_filters(
			'wpaic_build_tool_result_message',
			[
				'role' => 'tool_result',
				'tool_call_id' => $call_id,
				'tool_id' => $tool_id,
				'content' => $content,
			],
			$call_id,
			$tool_id,
			$result,
			$provider_id
		);
	}

	// =========================================================================
	// Tool call extraction
	// =========================================================================

	/**
	 * Extract normalised tool_calls from a provider response.
	 *
	 * Handles both OpenAI ({id, function:{name, arguments}}) and
	 * Claude-style ({id, name, input}) formats. Delegates to a filter
	 * so provider adapters can supply format-specific extraction.
	 *
	 * @param  array  $response    Provider response.
	 * @param  string $provider_id Provider identifier.
	 * @return array  Array of normalised tool calls [{id, name, arguments}].
	 */
	private static function extract_tool_calls(array $response, string $provider_id): array
	{
		// Start with whatever the provider put in $response['tool_calls'].
		$raw = $response['tool_calls'] ?? [];

		if (! is_array($raw)) {
			$raw = [];
		}

		/**
		 * Filter: provider adapters implement provider-native extraction here.
		 *
		 * @since 1.0.0
		 * @param array  $tool_calls  Extracted tool calls (may be empty).
		 * @param array  $response    Raw provider response.
		 * @param string $provider_id Provider identifier.
		 */
		$raw = (array) apply_filters('wpaic_extract_tool_calls', $raw, $response, $provider_id);

		// Normalise each call to {id, name, arguments (string JSON)}.
		$normalised = [];

		foreach ($raw as $call) {
			if (! is_array($call)) {
				continue;
			}

			// OpenAI style: {id, type:function, function:{name, arguments}}
			if (isset($call['function']['name'])) {
				$normalised[] = [
					'id' => $call['id'] ?? '',
					'name' => $call['function']['name'],
					'arguments' => $call['function']['arguments'] ?? '{}',
				];
				continue;
			}

			// Claude / generic style: {id, name, input}
			if (isset($call['name'])) {
				$input = $call['input'] ?? $call['arguments'] ?? '{}';
				if (is_array($input)) {
					$input = wp_json_encode($input);
				}
				$normalised[] = [
					'id' => $call['id'] ?? '',
					'name' => $call['name'],
					'arguments' => (string) $input,
				];
			}
		}

		return $normalised;
	}

	// =========================================================================
	// Context builder
	// =========================================================================

	/**
	 * Build the execution context for Tool_Executor from pipeline pre-data.
	 *
	 * @param  array $pre   Message_Pipeline Stage 1 result.
	 * @param  int   $loop  Current continuation loop index (1-based).
	 * @return array
	 */
	private static function build_execution_context(array $pre, int $loop): array
	{
		$context = [
			'session_id' => $pre['session']['session_token'] ?? '',
			'session_db_id' => (int) ($pre['session']['id'] ?? 0),
			'bot_slug' => $pre['bot']['slug'] ?? 'default',
			'user_id' => (int) ($pre['context']['user_id'] ?? get_current_user_id()),
			'page_url' => $pre['page_url'] ?? '',
			'loop' => $loop,
		];

		/**
		 * Filter: enrich tool execution context before each continuation loop.
		 *
		 * @since 1.0.0
		 * @param array $context Execution context.
		 * @param array $pre     Pipeline Stage 1 data.
		 * @param int   $loop    Loop index.
		 */
		return (array) apply_filters('wpaic_tool_continuation_context', $context, $pre, $loop);
	}
}
