<?php

/**
 * Provider_Interface — base contract for all AI provider adapters.
 *
 * Every adapter registered with Provider_Registry must implement this interface.
 * Compliance rules CR-01 through CR-05 are enforced by the registry at registration time.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Provider;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Interface Provider_Interface
 *
 * Contract for synchronous AI provider adapters.
 * Adapters receive configuration via the constructor, expose a unique provider ID,
 * handle synchronous chat completion, and report cost in microcents for usage tracking.
 *
 * @since 1.0.0
 * @package WPAIC\Provider
 */
interface Provider_Interface
{
	/**
	 * Construct the provider with configuration injected by the registry.
	 *
	 * Adapters MUST NOT make HTTP requests or register hooks in the constructor.
	 * Use lazy initialisation for expensive resources.
	 *
	 * @param array $config {
	 *     Provider configuration.
	 *
	 *     @type string $api_key     Decrypted API key (from Settings_Manager).
	 *     @type string $endpoint    Optional endpoint override URL.
	 *     @type int    $timeout     Request timeout in seconds (default: 30).
	 *     @type int    $max_retries Maximum retry attempts (default: 3).
	 * }
	 */
	public function __construct($config = []);

	/**
	 * Return the unique canonical provider identifier.
	 *
	 * Must be a lowercase alphanumeric slug (hyphens allowed) matching the
	 * registration ID used with Provider_Registry::register(). This value is
	 * stored in DB records and MUST NOT change once published.
	 *
	 * Canonical IDs: 'openai', 'claude', 'gemini', 'deepseek', 'grok'
	 *
	 * @return string Provider ID (lowercase, [a-z0-9-]).
	 */
	public function get_id();

	/**
	 * Send a synchronous chat completion request.
	 *
	 * Accepts a normalised request payload and returns a normalised response array.
	 * The adapter is responsible for translating between the unified format and
	 * the provider's native API format.
	 *
	 * On success, the response MUST contain 'message' and 'usage' keys.
	 * On failure, the response MUST contain an 'error' key with the standardised
	 * error payload. Adapters MUST NOT catch and suppress errors silently.
	 *
	 * @param  array $messages Normalised message array. Each element: {
	 *     @type string $role    One of 'system', 'user', 'assistant', 'tool'.
	 *     @type string $content Message content string (or structured array for multimodal).
	 *     @type array  $tool_calls Optional tool call results (role='tool' only).
	 * }
	 * @param  array $params {
	 *     Request parameters.
	 *
	 *     @type string $model         Model ID (resolved by model registry before dispatch).
	 *     @type float  $temperature   Sampling temperature (0.0–2.0).
	 *     @type int    $max_tokens    Maximum response tokens.
	 *     @type array  $tools         Tool definitions array (optional).
	 *     @type string $system_prompt System prompt override (optional).
	 *     @type string $request_id    Unique request UUID for tracing.
	 *     @type string $session_id    Chat session ID for context.
	 *     @type array  $metadata      Arbitrary key-value metadata (optional).
	 * }
	 * @return array {
	 *     Normalised response payload.
	 *
	 *     Success:
	 *     @type string $message          Assistant response text.
	 *     @type array  $usage {
	 *         @type int $prompt_tokens
	 *         @type int $completion_tokens
	 *         @type int $total_tokens
	 *     }
	 *     @type string $finish_reason    'stop'|'length'|'tool_calls'|'content_filter'
	 *     @type array  $tool_calls       Tool call requests from model (optional).
	 *     @type string $provider_id      Canonical provider ID.
	 *     @type string $model_id         Actual model used (may differ if fallback).
	 *     @type string $request_id       Echo of input request_id for correlation.
	 *
	 *     Error:
	 *     @type array  $error {
	 *         @type int    $code         HTTP status code or internal error code.
	 *         @type string $type         Error taxonomy type.
	 *         @type string $message      Technical error message (for logging).
	 *         @type bool   $retryable    Whether the caller should retry.
	 *         @type string $user_message User-friendly error message (for display).
	 *     }
	 * }
	 */
	public function chat($messages, $params = []);

	/**
	 * Calculate request cost in microcents.
	 *
	 * 1 microcent = 1/10 000 of a cent = 1/1 000 000 of a dollar.
	 * Formula: (tokens / 1_000_000) * rate_per_1M * 100 * 10 000
	 *
	 * Returns null for free/local models or when pricing data is unavailable.
	 *
	 * @param  array  $usage {
	 *     Token usage from chat() response.
	 *     @type int $prompt_tokens
	 *     @type int $completion_tokens
	 *     @type int $total_tokens
	 * }
	 * @param  string $model The model ID used for the request.
	 * @return int|null Cost in microcents, or null if not calculable.
	 */
	public function calculate_cost($usage, $model);
}
