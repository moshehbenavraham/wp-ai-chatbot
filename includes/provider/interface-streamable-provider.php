<?php

/**
 * Streamable_Provider_Interface — extended contract for SSE-capable adapters.
 *
 * Resolves CFI-PRO-001 (SSE streaming boundary) and CFI-RUN-003 (circular dependency).
 * The runtime checks `$adapter instanceof Streamable_Provider_Interface` to determine
 * streaming availability, falling back to synchronous chat() automatically if not.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Provider;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Interface Streamable_Provider_Interface
 *
 * Extended contract for provider adapters that support Server-Sent Events (SSE)
 * streaming. Implementing this interface is optional — adapters that only support
 * synchronous chat implement Provider_Interface alone.
 *
 * Streaming compliance rules (SCR-01 through SCR-05) are documented inline.
 *
 * @since 1.0.0
 * @package WPAIC\Provider
 */
interface Streamable_Provider_Interface extends Provider_Interface
{
	/**
	 * Send a streaming chat completion request via SSE.
	 *
	 * The adapter is responsible for:
	 *   1. Opening the HTTP connection to the provider API with streaming enabled.
	 *   2. Parsing provider-specific chunk formats into normalised SSE chunk arrays.
	 *   3. Emitting each chunk via the $callback callable.
	 *   4. Returning a final summary array after the stream completes.
	 *
	 * On error mid-stream, the adapter MUST call $callback with type='error'
	 * and then return an error response array. The adapter MUST NOT throw
	 * exceptions during streaming (SCR-05).
	 *
	 * @param  array    $messages Normalised message array (same shape as Provider_Interface::chat()).
	 * @param  array    $params   Request parameters (same as Provider_Interface::chat()).
	 * @param  callable|null $callback Chunk emission callback. Signature:
	 *     function( array $chunk ): void
	 *
	 *     $chunk structure:
	 *     @type string $type    One of 'content', 'tool_call', 'usage', 'error', 'done'.
	 *     @type string $content Delta text content (type='content' only).
	 *     @type array  $tool_call Tool call delta (type='tool_call' only).
	 *     @type array  $usage   Final usage data (type='usage' only).
	 *     @type array  $error   Error payload (type='error' only).
	 *
	 * @return array {
	 *     Stream completion summary.
	 *
	 *     @type string $message          Full concatenated response text.
	 *     @type array  $usage            Final token usage counts.
	 *     @type string $finish_reason    Final finish reason.
	 *     @type array  $tool_calls       Complete tool call array (if any).
	 *     @type string $provider_id      Canonical provider ID.
	 *     @type string $model_id         Actual model used.
	 *     @type string $request_id       Request UUID for correlation.
	 *     @type array  $error            Error payload (if stream failed).
	 * }
	 */
	public function stream_chat($messages, $params = [], $callback = null);

	/**
	 * Check whether streaming is currently available for this provider.
	 *
	 * Allows adapters to dynamically disable streaming based on configuration,
	 * API key status, or model capabilities.
	 *
	 * @return bool True if streaming is available and should be used.
	 */
	public function supports_streaming();
}
