<?php

/**
 * Gemini (Google) provider adapter.
 *
 * Implements Provider_Interface and Streamable_Provider_Interface for the
 * Google Generative Language API. Translates the unified request/response format.
 *
 * API reference: https://ai.google.dev/api/generate-content
 *
 * Key differences from OpenAI / Claude:
 *  - Auth: API key passed as a URL query parameter (`?key=`), no auth header.
 *  - Messages: `contents[]` with `role` ('user'|'model') and `parts[]` array.
 *  - System prompt: top-level `systemInstruction` field (not a message role).
 *  - Assistant role maps to 'model' (not 'assistant') in Gemini's terminology.
 *  - Generation params: nested inside a `generationConfig` object.
 *  - Usage: `usageMetadata.promptTokenCount` / `candidatesTokenCount`.
 *  - Streaming: separate endpoint suffix `:streamGenerateContent?alt=sse`.
 *  - 2.5/3.x pro models require `thinkingConfig` (cannot disable thinking).
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Provider\Adapters;

use WPAIC\Provider\Provider_Interface;
use WPAIC\Provider\Streamable_Provider_Interface;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Gemini_Adapter
 *
 * Adapter for Google's Generative Language API (Gemini models).
 * Canonical provider ID: 'gemini'
 *
 * @since 1.0.0
 * @package WPAIC\Provider\Adapters
 */
class Gemini_Adapter implements Streamable_Provider_Interface
{
	/** Canonical provider ID (stored in DB, never change). */
	private const PROVIDER_ID = 'gemini';

	/** Google Generative Language API base URL. */
	private const API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

	/** Connect timeout in seconds. */
	private const CONNECT_TIMEOUT = 5;

	/** Stream timeout in seconds (full SSE connection lifetime). */
	private const STREAM_TIMEOUT = 120;

	/** HTTP status codes that are safe to retry. */
	private const RETRYABLE_CODES = [429, 500, 502, 503, 504];

	/**
	 * Per-model pricing in USD per 1M tokens [input, output].
	 * Gemini models have tiered pricing (free vs paid, context window).
	 * Updated to approximate 2025-03 pricing (paid tier).
	 *
	 * @var array<string, array{0: float, 1: float}>
	 */
	private const MODEL_PRICING = [
		'gemini-2.5-pro' => [1.25,  10.00],
		'gemini-2.5-flash' => [0.15,   0.60],
		'gemini-2.5-flash-lite' => [0.10,   0.40],
		'gemini-2.0-flash' => [0.10,   0.40],
		'gemini-2.0-flash-lite' => [0.075,  0.30],
		'gemini-1.5-pro' => [1.25,   5.00],
		'gemini-1.5-flash' => [0.075,  0.30],
		'gemini-1.5-flash-8b' => [0.0375, 0.15],
	];

	/** @var string Decrypted API key. */
	private $api_key;

	/** @var string API base URL (allows override for testing). */
	private $api_base;

	/** @var int Request timeout in seconds. */
	private $timeout;

	/** @var int Maximum retry attempts. */
	private $max_retries;

	// =========================================================================
	// Constructor
	// =========================================================================

	/**
	 * Construct the adapter with registry-injected configuration.
	 *
	 * @param array $config {
	 *     @type string $api_key     Decrypted Google API key.
	 *     @type string $endpoint    Optional API base URL override.
	 *     @type int    $timeout     Request timeout in seconds.
	 *     @type int    $max_retries Max retry attempts.
	 * }
	 */
	public function __construct($config = [])
	{
		$this->api_key = (string) ($config['api_key'] ?? '');
		$this->api_base = '' !== ($config['endpoint'] ?? '')
			? rtrim((string) $config['endpoint'], '/')
			: self::API_BASE;
		$this->timeout = isset($config['timeout']) ? max(5, (int) $config['timeout']) : 60;
		$this->max_retries = isset($config['max_retries']) ? max(0, (int) $config['max_retries']) : 3;
	}

	// =========================================================================
	// Provider_Interface
	// =========================================================================

	/** {@inheritdoc} */
	public function get_id()
	{
		return self::PROVIDER_ID;
	}

	/**
	 * Send a synchronous chat completion request.
	 *
	 * {@inheritdoc}
	 *
	 * @param  array $messages Normalised messages.
	 * @param  array $params   Request parameters.
	 * @return array           Normalised response.
	 */
	public function chat($messages, $params = [])
	{
		if ('' === $this->api_key) {
			return $this->error_response(401, 'auth_error', 'Gemini API key is not configured.', false);
		}

		$model = (string) ($params['model'] ?? 'gemini-2.5-flash');
		$body = $this->build_request_body($messages, $params);
		$endpoint = $this->build_endpoint($model, false);

		return $this->execute_with_retry($endpoint, $body, $model, $params['request_id'] ?? '');
	}

	/**
	 * Calculate cost in microcents.
	 *
	 * {@inheritdoc}
	 *
	 * @param  array  $usage Token counts.
	 * @param  string $model Model ID.
	 * @return int|null      Cost in microcents.
	 */
	public function calculate_cost($usage, $model)
	{
		$base = $this->normalize_model_name((string) $model);

		$pricing = self::MODEL_PRICING[$base] ?? null;
		if (null === $pricing) {
			return null;
		}

		[$input_rate, $output_rate] = $pricing;

		$prompt_tokens = (int) ($usage['prompt_tokens'] ?? 0);
		$completion_tokens = (int) ($usage['completion_tokens'] ?? 0);

		$input_cost = (int) round(($prompt_tokens / 1_000_000) * $input_rate * 1_000_000);
		$output_cost = (int) round(($completion_tokens / 1_000_000) * $output_rate * 1_000_000);

		return $input_cost + $output_cost;
	}

	// =========================================================================
	// Streamable_Provider_Interface
	// =========================================================================

	/**
	 * Send a streaming chat completion request via SSE.
	 *
	 * {@inheritdoc}
	 *
	 * @param  array         $messages Normalised messages.
	 * @param  array         $params   Request parameters.
	 * @param  callable|null $callback Chunk emission callback.
	 * @return array                   Stream completion summary.
	 */
	public function stream_chat($messages, $params = [], $callback = null)
	{
		if ('' === $this->api_key) {
			$error = $this->error_response(401, 'auth_error', 'Gemini API key is not configured.', false);
			if (is_callable($callback)) {
				$callback(['type' => 'error', 'error' => $error['error']]);
			}

			return $error;
		}

		$model = (string) ($params['model'] ?? 'gemini-2.5-flash');
		$body = $this->build_request_body($messages, $params);
		$endpoint = $this->build_endpoint($model, true);

		return $this->execute_stream($endpoint, $body, $model, $params['request_id'] ?? '', $callback);
	}

	/** {@inheritdoc} */
	public function supports_streaming()
	{
		return '' !== $this->api_key && function_exists('curl_init');
	}

	// =========================================================================
	// HTTP execution
	// =========================================================================

	/**
	 * Execute a synchronous POST with exponential back-off retry.
	 *
	 * @param  string $endpoint   Full URL including API key.
	 * @param  array  $body       JSON-encodable request body.
	 * @param  string $model      Model ID (for response normalisation).
	 * @param  string $request_id Correlation ID.
	 * @return array              Normalised response.
	 */
	private function execute_with_retry(string $endpoint, array $body, string $model, string $request_id): array
	{
		$attempt = 0;
		$last_error = null;

		do {
			if ($attempt > 0) {
				usleep((int) (1_000_000 * 2 ** ($attempt - 1)));
			}

			$response = wp_remote_post($endpoint, [
				'headers' => ['Content-Type' => 'application/json'],
				'body' => (string) wp_json_encode($body),
				'timeout' => $this->timeout,
				'sslverify' => true,
			]);

			$attempt++;

			if (is_wp_error($response)) {
				$last_error = $response;
				continue;
			}

			$code = (int) wp_remote_retrieve_response_code($response);

			if (in_array($code, self::RETRYABLE_CODES, true) && $attempt <= $this->max_retries) {
				$last_error = $code;
				continue;
			}

			return $this->parse_response($response, $model, $request_id);

		} while ($attempt <= $this->max_retries);

		if (isset($last_error) && is_wp_error($last_error)) {
			return $this->error_response(500, 'connection_error', $last_error->get_error_message(), false);
		}

		return $this->error_response(
			is_int($last_error) ? $last_error : 500,
			'provider_error',
			'Gemini request failed after ' . $this->max_retries . ' retries.',
			false
		);
	}

	/**
	 * Execute a streaming request via cURL SSE.
	 *
	 * @param  string        $endpoint   Full URL (streamGenerateContent + ?alt=sse).
	 * @param  array         $body       JSON-encodable request body.
	 * @param  string        $model      Model ID used in response.
	 * @param  string        $request_id Correlation ID.
	 * @param  callable|null $callback   Chunk callback.
	 * @return array                     Stream summary.
	 */
	private function execute_stream(string $endpoint, array $body, string $model, string $request_id, $callback): array
	{
		if (! function_exists('curl_init')) {
			return $this->stream_fallback($endpoint, $body, $model, $request_id, $callback);
		}

		$accumulated_text = '';
		$finish_reason = 'stop';
		$usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
		$stream_buffer = '';

		$ch = curl_init($endpoint);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => (string) wp_json_encode($body),
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_TIMEOUT => self::STREAM_TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_WRITEFUNCTION => function ($ch, $data) use (
				$callback,
				&$accumulated_text,
				&$finish_reason,
				&$usage,
				&$stream_buffer
			) {
				$stream_buffer .= $data;
				$this->process_sse_buffer(
					$stream_buffer,
					$callback,
					$accumulated_text,
					$finish_reason,
					$usage
				);

				return strlen($data);
			},
		]);

		$result = curl_exec($ch);
		$error = curl_error($ch);
		$errno = curl_errno($ch);
		curl_close($ch);

		if ($result === false || 0 !== $errno) {
			$err = $this->error_response(500, 'stream_error', $error ?: 'cURL stream error', false);
			if (is_callable($callback)) {
				$callback(['type' => 'error', 'error' => $err['error']]);
			}

			return $err;
		}

		// Process any remaining buffer content.
		if ('' !== $stream_buffer) {
			$this->process_sse_buffer($stream_buffer, $callback, $accumulated_text, $finish_reason, $usage);
		}

		if (is_callable($callback)) {
			$callback(['type' => 'done']);
		}

		return [
			'message' => $accumulated_text,
			'usage' => $usage,
			'finish_reason' => $finish_reason,
			'tool_calls' => [],
			'provider_id' => self::PROVIDER_ID,
			'model_id' => $model,
			'request_id' => $request_id,
		];
	}

	/**
	 * Fallback: stream_chat → synchronous when cURL unavailable.
	 *
	 * @param  string        $endpoint   Sync endpoint (not stream).
	 * @param  array         $body
	 * @param  string        $model
	 * @param  string        $request_id
	 * @param  callable|null $callback
	 * @return array
	 */
	private function stream_fallback(string $endpoint, array $body, string $model, string $request_id, $callback): array
	{
		// Use the non-streaming endpoint (replace :streamGenerateContent with :generateContent).
		$sync_endpoint = str_replace(':streamGenerateContent', ':generateContent', $endpoint);
		// Remove alt=sse query param if present.
		$sync_endpoint = preg_replace('/([?&])alt=sse(&|$)/', '$1', $sync_endpoint);
		$sync_endpoint = rtrim($sync_endpoint, '?&');

		$response = $this->execute_with_retry($sync_endpoint, $body, $model, $request_id);

		if (isset($response['message']) && is_callable($callback)) {
			$callback(['type' => 'content', 'content' => $response['message']]);
			$callback(['type' => 'done']);
		}

		return $response;
	}

	// =========================================================================
	// SSE parsing — Gemini event format
	// =========================================================================

	/**
	 * Process accumulated SSE buffer, extracting complete data lines.
	 *
	 * Gemini streaming format with ?alt=sse: standard SSE where each
	 * `data: {...}` line is a complete partial GenerateContentResponse JSON.
	 * Text is accumulated from candidates[0].content.parts[].text.
	 * Usage is in usageMetadata of the last chunk.
	 *
	 * @param  string        &$buffer           Accumulated raw bytes (consumed in place).
	 * @param  callable|null $callback
	 * @param  string        &$accumulated_text Accumulated response text.
	 * @param  string        &$finish_reason    Final finish reason.
	 * @param  array         &$usage            Token usage.
	 * @return void
	 */
	private function process_sse_buffer(
		string &$buffer,
		$callback,
		string &$accumulated_text,
		string &$finish_reason,
		array &$usage
	): void {
		// Split on SSE line boundaries (double newline terminates an event).
		while (false !== ($pos = strpos($buffer, "\n"))) {
			$line = substr($buffer, 0, $pos);
			$buffer = substr($buffer, $pos + 1);
			$line = trim($line);

			if ('' === $line || strpos($line, 'event: ') === 0) {
				continue;
			}

			if (strpos($line, 'data: ') !== 0) {
				continue;
			}

			$json = substr($line, 6);
			if ('[DONE]' === $json) {
				continue;
			}

			$data = json_decode($json, true);
			if (! is_array($data)) {
				continue;
			}

			// Extract text from candidates[0].content.parts[].
			$candidates = $data['candidates'] ?? [];
			$candidate = is_array($candidates) && isset($candidates[0]) ? $candidates[0] : null;

			if (is_array($candidate)) {
				$parts = $candidate['content']['parts'] ?? [];
				foreach ($parts as $part) {
					if (isset($part['text']) && '' !== $part['text']) {
						$text = (string) $part['text'];
						$accumulated_text .= $text;
						if (is_callable($callback)) {
							$callback(['type' => 'content', 'content' => $text]);
						}
					}
				}

				if (isset($candidate['finishReason']) && '' !== $candidate['finishReason']) {
					$finish_reason = $this->normalize_finish_reason((string) $candidate['finishReason']);
				}
			}

			// Usage is typically in the last chunk.
			if (isset($data['usageMetadata']) && is_array($data['usageMetadata'])) {
				$meta = $data['usageMetadata'];
				$p = (int) ($meta['promptTokenCount'] ?? 0);
				$c = (int) ($meta['candidatesTokenCount'] ?? 0);
				$t = (int) ($meta['totalTokenCount'] ?? ($p + $c));
				$usage = [
					'prompt_tokens' => $p,
					'completion_tokens' => $c,
					'total_tokens' => $t,
				];
			}
		}
	}

	// =========================================================================
	// Request / response helpers
	// =========================================================================

	/**
	 * Build the Gemini generateContent request body.
	 *
	 * @param  array $messages Normalised messages.
	 * @param  array $params   Request parameters.
	 * @return array           Gemini-format request body.
	 */
	private function build_request_body(array $messages, array $params): array
	{
		$model = (string) ($params['model'] ?? 'gemini-2.5-flash');
		$system_parts = [];
		$contents = [];

		// Inject system_prompt param as system instruction if present.
		if (! empty($params['system_prompt'])) {
			$system_parts[] = (string) $params['system_prompt'];
		}

		foreach ($messages as $msg) {
			$role = (string) ($msg['role'] ?? 'user');
			$content = $msg['content'] ?? '';

			if ('system' === $role) {
				// Collect all system messages into systemInstruction.
				if (is_array($content)) {
					foreach ($content as $c) {
						$system_parts[] = is_array($c) ? ($c['text'] ?? '') : (string) $c;
					}
				} else {
					$system_parts[] = (string) $content;
				}
				continue;
			}

			// Map assistant → model (Gemini terminology).
			$gemini_role = ('assistant' === $role) ? 'model' : 'user';

			// Flatten content to a text string.
			if (is_array($content)) {
				$text_parts = [];
				foreach ($content as $c) {
					$text_parts[] = is_array($c) ? ($c['text'] ?? '') : (string) $c;
				}
				$content = implode("\n\n", array_filter($text_parts));
			}

			$contents[] = [
				'role' => $gemini_role,
				'parts' => [['text' => (string) $content]],
			];
		}

		$body = ['contents' => $contents];

		// System instruction.
		$system_text = trim(implode("\n\n", array_filter($system_parts)));
		if ('' !== $system_text) {
			$body['systemInstruction'] = [
				'parts' => [['text' => $system_text]],
			];
		}

		// Generation config.
		$gen_config = [];

		if (isset($params['temperature'])) {
			$gen_config['temperature'] = (float) $params['temperature'];
		}

		if (isset($params['max_tokens'])) {
			$gen_config['maxOutputTokens'] = (int) $params['max_tokens'];
		}

		// Thinking config for 2.5/3.x models.
		$needs_thinking = strpos($model, '2.5') !== false || strpos($model, 'gemini-3') !== false;
		$is_pro_model = strpos($model, '-pro') !== false;

		if ($needs_thinking) {
			if ($is_pro_model) {
				// Pro models require thinking mode (cannot be disabled).
				$gen_config['thinkingConfig'] = ['thinkingBudget' => 8192];
			} else {
				// Flash/lite models: disable thinking by default to save tokens.
				$gen_config['thinkingConfig'] = ['thinkingBudget' => 0];
			}
		}

		if (! empty($gen_config)) {
			$body['generationConfig'] = $gen_config;
		}

		// Tool definitions.
		if (! empty($params['tools']) && is_array($params['tools'])) {
			$body['tools'] = $this->convert_tools_to_gemini($params['tools']);
		}

		return $body;
	}

	/**
	 * Convert OpenAI-format tool definitions to Gemini function declarations.
	 *
	 * OpenAI: [{type:'function', function:{name, description, parameters}}]
	 * Gemini: [{functionDeclarations: [{name, description, parameters}]}]
	 *
	 * @param  array $tools OpenAI-format tool definitions.
	 * @return array        Gemini-format tools array.
	 */
	private function convert_tools_to_gemini(array $tools): array
	{
		$declarations = [];

		foreach ($tools as $tool) {
			if (! is_array($tool)) {
				continue;
			}

			$fn = $tool['function'] ?? $tool;

			$declarations[] = [
				'name' => (string) ($fn['name'] ?? ''),
				'description' => (string) ($fn['description'] ?? ''),
				'parameters' => $fn['parameters'] ?? ['type' => 'object', 'properties' => []],
			];
		}

		if (empty($declarations)) {
			return [];
		}

		return [['functionDeclarations' => $declarations]];
	}

	/**
	 * Build the full endpoint URL with API key.
	 *
	 * @param  string $model  Model ID (e.g. 'gemini-2.5-flash').
	 * @param  bool   $stream Whether to use the streaming endpoint.
	 * @return string         Full URL.
	 */
	private function build_endpoint(string $model, bool $stream): string
	{
		$action = $stream ? 'streamGenerateContent' : 'generateContent';
		$url = $this->api_base . '/models/' . rawurlencode($model) . ':' . $action
			. '?key=' . rawurlencode($this->api_key);

		if ($stream) {
			$url .= '&alt=sse';
		}

		return $url;
	}

	/**
	 * Parse a wp_remote_post() response into a normalised response array.
	 *
	 * @param  array|\WP_Error $response
	 * @param  string          $model      Model used (for model_id field).
	 * @param  string          $request_id Correlation ID.
	 * @return array
	 */
	private function parse_response($response, string $model, string $request_id): array
	{
		if (is_wp_error($response)) {
			return $this->error_response(500, 'connection_error', $response->get_error_message(), true);
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$raw = wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);

		if ($code >= 400) {
			$api_msg = is_array($data) ? ($data['error']['message'] ?? ('HTTP ' . $code)) : ('HTTP ' . $code);
			$retryable = in_array($code, self::RETRYABLE_CODES, true);

			return $this->error_response($code, $this->classify_error_code($code), $api_msg, $retryable);
		}

		if (! is_array($data)) {
			return $this->error_response(500, 'parse_error', 'Invalid JSON response from Gemini.', false);
		}

		// Check for prompt feedback blocks.
		if (empty($data['candidates']) && isset($data['promptFeedback']['blockReason'])) {
			$reason = (string) $data['promptFeedback']['blockReason'];

			return $this->error_response(400, 'content_filter', 'Prompt blocked by safety filters: ' . $reason, false);
		}

		$candidate = $data['candidates'][0] ?? [];

		if (empty($candidate)) {
			return $this->error_response(500, 'provider_error', 'No candidates in Gemini response.', false);
		}

		// Check for SAFETY finish reason.
		$finish_reason_raw = (string) ($candidate['finishReason'] ?? 'STOP');
		if ('SAFETY' === $finish_reason_raw) {
			return $this->error_response(400, 'content_filter', 'Response blocked by safety filters.', false);
		}

		// Extract text from content parts.
		$text = '';
		$parts = $candidate['content']['parts'] ?? [];
		foreach ($parts as $part) {
			if (isset($part['text'])) {
				$text .= (string) $part['text'];
			}
		}

		// Extract usage metadata.
		$meta = $data['usageMetadata'] ?? [];
		$prompt_tokens = (int) ($meta['promptTokenCount'] ?? 0);
		$completion_tokens = (int) ($meta['candidatesTokenCount'] ?? 0);
		$usage = [
			'prompt_tokens' => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'total_tokens' => (int) ($meta['totalTokenCount'] ?? ($prompt_tokens + $completion_tokens)),
		];

		$finish_reason = $this->normalize_finish_reason($finish_reason_raw);

		return [
			'message' => $text,
			'usage' => $usage,
			'finish_reason' => $finish_reason,
			'tool_calls' => [],
			'provider_id' => self::PROVIDER_ID,
			'model_id' => $model,
			'request_id' => $request_id,
		];
	}

	/**
	 * Normalise Gemini finishReason to the plugin's canonical finish_reason values.
	 *
	 * Gemini → canonical:
	 *  STOP              → stop
	 *  MAX_TOKENS        → length
	 *  SAFETY            → content_filter
	 *  RECITATION        → content_filter
	 *  OTHER             → stop
	 *
	 * @param  string $reason Gemini finishReason.
	 * @return string         Canonical finish reason.
	 */
	private function normalize_finish_reason(string $reason): string
	{
		$map = [
			'STOP' => 'stop',
			'MAX_TOKENS' => 'length',
			'SAFETY' => 'content_filter',
			'RECITATION' => 'content_filter',
			'OTHER' => 'stop',
		];

		return $map[$reason] ?? 'stop';
	}

	/**
	 * Normalise a model ID by stripping date suffixes and lowercasing.
	 * e.g. 'gemini-2.5-flash-preview-05-20' → 'gemini-2.5-flash'
	 *
	 * @param  string $model Raw model ID.
	 * @return string        Normalised base model ID.
	 */
	private function normalize_model_name(string $model): string
	{
		// Strip common Gemini preview/date suffixes.
		$base = preg_replace('/-(?:preview|exp|latest)(?:-\d{2}-\d{2})?$/', '', strtolower($model));

		return (string) $base;
	}

	/**
	 * Build a standardised error response payload.
	 *
	 * @param  int    $code      HTTP / internal status code.
	 * @param  string $type      Error taxonomy type.
	 * @param  string $message   Technical message (for logging only).
	 * @param  bool   $retryable Whether the error is retryable.
	 * @return array
	 */
	private function error_response(int $code, string $type, string $message, bool $retryable): array
	{
		$user_messages = [
			'auth_error' => __('The AI service is not configured. Please contact the site administrator.', 'wp-ai-chatbot'),
			'rate_limit' => __('The AI service is temporarily busy. Please try again in a moment.', 'wp-ai-chatbot'),
			'connection_error' => __('Could not reach the AI service. Please check your connection and try again.', 'wp-ai-chatbot'),
			'provider_error' => __('The AI service returned an error. Please try again.', 'wp-ai-chatbot'),
			'parse_error' => __('The AI service returned an unexpected response. Please try again.', 'wp-ai-chatbot'),
			'stream_error' => __('The streaming connection was interrupted. Please try again.', 'wp-ai-chatbot'),
			'content_filter' => __('Your message was blocked by the content safety filter. Please rephrase and try again.', 'wp-ai-chatbot'),
		];

		return [
			'error' => [
				'code' => $code,
				'type' => $type,
				'message' => $message,
				'retryable' => $retryable,
				'user_message' => $user_messages[$type] ?? __('An error occurred. Please try again.', 'wp-ai-chatbot'),
			],
		];
	}

	/**
	 * Classify an HTTP status code into a plugin error type.
	 *
	 * @param  int    $code HTTP status code.
	 * @return string       Error taxonomy type.
	 */
	private function classify_error_code(int $code): string
	{
		if (401 === $code || 403 === $code) {
			return 'auth_error';
		}

		if (429 === $code) {
			return 'rate_limit';
		}

		return 'provider_error';
	}
}
