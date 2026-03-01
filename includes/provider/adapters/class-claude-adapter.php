<?php

/**
 * Claude (Anthropic) provider adapter.
 *
 * Implements Provider_Interface and Streamable_Provider_Interface for the
 * Anthropic Messages API. Translates the unified request/response format.
 * Implements exponential backoff for retryable errors (429, 500, 503).
 *
 * API reference: https://docs.anthropic.com/en/api/messages
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
 * Class Claude_Adapter
 *
 * Adapter for Anthropic's Claude Messages API.
 * Canonical provider ID: 'claude'
 *
 * Key API differences vs OpenAI:
 *  - Auth header: `x-api-key` (not `Authorization: Bearer`).
 *  - Required header: `anthropic-version: 2023-06-01`.
 *  - System prompt: top-level `system` field (not a message role).
 *  - Messages: content is an array of typed blocks [{type:'text', text:'...'}].
 *  - Usage: `input_tokens` / `output_tokens` (not prompt_/completion_tokens).
 *  - Stop reason: `stop_reason` field values differ from OpenAI finish_reason.
 *  - Streaming: SSE with typed events (content_block_delta, message_delta, etc.).
 *
 * @since 1.0.0
 * @package WPAIC\Provider\Adapters
 */
class Claude_Adapter implements Streamable_Provider_Interface
{
	/** Canonical provider ID (stored in DB, never change). */
	private const PROVIDER_ID = 'claude';

	/** Default Messages API endpoint. */
	private const DEFAULT_ENDPOINT = 'https://api.anthropic.com/v1/messages';

	/** Anthropic API version header value. */
	private const API_VERSION = '2023-06-01';

	/** Connect timeout in seconds. */
	private const CONNECT_TIMEOUT = 5;

	/** Stream timeout in seconds (full SSE connection lifetime). */
	private const STREAM_TIMEOUT = 120;

	/** HTTP status codes that are safe to retry. */
	private const RETRYABLE_CODES = [429, 500, 502, 503, 504];

	/**
	 * Per-model pricing in USD per 1M tokens [input, output].
	 * Models use canonical IDs without date suffixes.
	 * Updated to approximate 2025-03 pricing.
	 *
	 * @var array<string, array{0: float, 1: float}>
	 */
	private const MODEL_PRICING = [
		'claude-opus-4-6' => [15.00, 75.00],
		'claude-opus-4' => [15.00, 75.00],
		'claude-sonnet-4-6' => [3.00,  15.00],
		'claude-sonnet-4-5' => [3.00,  15.00],
		'claude-sonnet-4' => [3.00,  15.00],
		'claude-haiku-4-5' => [0.80,  4.00],
		'claude-3-5-sonnet' => [3.00,  15.00],
		'claude-3-5-haiku' => [0.80,  4.00],
		'claude-3-opus' => [15.00, 75.00],
		'claude-3-sonnet' => [3.00,  15.00],
		'claude-3-haiku' => [0.25,  1.25],
	];

	/** @var string Decrypted API key. */
	private $api_key;

	/** @var string Resolved API endpoint URL. */
	private $endpoint;

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
	 *     @type string $api_key     Decrypted Anthropic API key.
	 *     @type string $endpoint    Optional endpoint override.
	 *     @type int    $timeout     Request timeout in seconds.
	 *     @type int    $max_retries Max retry attempts.
	 * }
	 */
	public function __construct($config = [])
	{
		$this->api_key = (string) ($config['api_key'] ?? '');
		$this->endpoint = '' !== ($config['endpoint'] ?? '')
			? (string) $config['endpoint']
			: self::DEFAULT_ENDPOINT;
		$this->timeout = isset($config['timeout']) ? max(5, (int) $config['timeout']) : 30;
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
			return $this->error_response(401, 'auth_error', 'Claude API key is not configured.', false);
		}

		$body = $this->build_request_body($messages, $params, false);

		return $this->execute_with_retry($body, $params['request_id'] ?? '');
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
			$error = $this->error_response(401, 'auth_error', 'Claude API key is not configured.', false);
			if (is_callable($callback)) {
				$callback(['type' => 'error', 'error' => $error['error']]);
			}

			return $error;
		}

		$body = $this->build_request_body($messages, $params, true);

		return $this->execute_stream($body, $params['request_id'] ?? '', $callback);
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
	 * Execute a synchronous request with exponential back-off retry logic.
	 *
	 * @param  array  $body       JSON-encodable request body.
	 * @param  string $request_id Correlation ID.
	 * @return array              Normalised response.
	 */
	private function execute_with_retry(array $body, string $request_id): array
	{
		$attempt = 0;
		$last_error = null;

		do {
			if ($attempt > 0) {
				usleep((int) (1_000_000 * 2 ** ($attempt - 1)));
			}

			$response = wp_remote_post($this->endpoint, [
				'headers' => $this->build_headers(),
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

			return $this->parse_response($response, $request_id);

		} while ($attempt <= $this->max_retries);

		if (isset($last_error) && is_wp_error($last_error)) {
			return $this->error_response(
				500,
				'connection_error',
				$last_error->get_error_message(),
				false
			);
		}

		return $this->error_response(
			is_int($last_error) ? $last_error : 500,
			'provider_error',
			'Claude request failed after ' . $this->max_retries . ' retries.',
			false
		);
	}

	/**
	 * Execute a streaming request via cURL SSE.
	 *
	 * @param  array         $body       JSON-encodable request body.
	 * @param  string        $request_id Correlation ID.
	 * @param  callable|null $callback   Chunk callback.
	 * @return array                     Stream summary.
	 */
	private function execute_stream(array $body, string $request_id, $callback): array
	{
		if (! function_exists('curl_init')) {
			return $this->stream_fallback($body, $request_id, $callback);
		}

		$accumulated_text = '';
		$accumulated_calls = [];
		$finish_reason = 'stop';
		$usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
		$model_used = $body['model'] ?? '';

		$ch = curl_init($this->endpoint);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => (string) wp_json_encode($body),
			CURLOPT_HTTPHEADER => $this->build_curl_headers(),
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_TIMEOUT => self::STREAM_TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_WRITEFUNCTION => function ($ch, $data) use (
				$callback,
				&$accumulated_text,
				&$accumulated_calls,
				&$finish_reason,
				&$usage,
				&$model_used
			) {
				$this->process_sse_chunk(
					$data,
					$callback,
					$accumulated_text,
					$accumulated_calls,
					$finish_reason,
					$usage,
					$model_used
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

		if (is_callable($callback)) {
			$callback(['type' => 'done']);
		}

		return [
			'message' => $accumulated_text,
			'usage' => $usage,
			'finish_reason' => $finish_reason,
			'tool_calls' => $accumulated_calls,
			'provider_id' => self::PROVIDER_ID,
			'model_id' => $model_used,
			'request_id' => $request_id,
		];
	}

	/**
	 * Fallback: stream_chat → synchronous when cURL unavailable.
	 *
	 * @param  array         $body
	 * @param  string        $request_id
	 * @param  callable|null $callback
	 * @return array
	 */
	private function stream_fallback(array $body, string $request_id, $callback): array
	{
		$body['stream'] = false;

		$response = $this->execute_with_retry($body, $request_id);

		if (isset($response['message']) && is_callable($callback)) {
			$callback(['type' => 'content', 'content' => $response['message']]);
			$callback(['type' => 'done']);
		}

		return $response;
	}

	// =========================================================================
	// SSE parsing — Anthropic event format
	// =========================================================================

	/**
	 * Process a raw SSE data chunk from the Anthropic streaming API.
	 *
	 * Anthropic SSE events:
	 *  - message_start         → carries input_tokens in message.usage
	 *  - content_block_delta   → carries text_delta.text for text content
	 *  - message_delta         → carries stop_reason + output_tokens in usage
	 *  - tool_use blocks       → carries tool call deltas (input_json_delta)
	 *
	 * @param  string        $raw_chunk
	 * @param  callable|null $callback
	 * @param  string        &$accumulated_text
	 * @param  array         &$tool_calls
	 * @param  string        &$finish_reason
	 * @param  array         &$usage
	 * @param  string        &$model_used
	 * @return void
	 */
	private function process_sse_chunk(
		string $raw_chunk,
		$callback,
		string &$accumulated_text,
		array &$tool_calls,
		string &$finish_reason,
		array &$usage,
		string &$model_used
	): void {
		$lines = explode("\n", $raw_chunk);

		foreach ($lines as $line) {
			$line = trim($line);

			if ('' === $line) {
				continue;
			}

			// Skip SSE event: lines (just the event type label).
			if (strpos($line, 'event: ') === 0) {
				continue;
			}

			if (strpos($line, 'data: ') !== 0) {
				continue;
			}

			$json = substr($line, 6);
			$data = json_decode($json, true);

			if (! is_array($data)) {
				continue;
			}

			$event_type = (string) ($data['type'] ?? '');

			switch ($event_type) {
				case 'message_start':
					// Input token count is in the opening message_start event.
					$msg = $data['message'] ?? [];
					if (isset($msg['model'])) {
						$model_used = (string) $msg['model'];
					}
					if (isset($msg['usage']['input_tokens'])) {
						$usage['prompt_tokens'] = (int) $msg['usage']['input_tokens'];
					}
					break;

				case 'content_block_delta':
					$delta = $data['delta'] ?? [];
					$delta_type = (string) ($delta['type'] ?? '');

					if ('text_delta' === $delta_type && isset($delta['text'])) {
						$text = (string) $delta['text'];
						$accumulated_text .= $text;
						if (is_callable($callback)) {
							$callback(['type' => 'content', 'content' => $text]);
						}
					} elseif ('input_json_delta' === $delta_type) {
						// Tool call input accumulation.
						$idx = (int) ($data['index'] ?? 0);
						if (is_callable($callback)) {
							$callback(['type' => 'tool_call', 'tool_call' => [
								'index' => $idx,
								'input' => $delta['partial_json'] ?? '',
							]]);
						}
					}
					break;

				case 'content_block_start':
					// Signals start of a tool_use block — record tool name/id.
					$block = $data['content_block'] ?? [];
					if ('tool_use' === ($block['type'] ?? '')) {
						$tool_calls[] = [
							'id' => $block['id'] ?? '',
							'name' => $block['name'] ?? '',
							'input' => '',
						];
					}
					break;

				case 'message_delta':
					$delta = $data['delta'] ?? [];
					if (isset($delta['stop_reason'])) {
						$finish_reason = $this->normalize_stop_reason((string) $delta['stop_reason']);
					}
					// Output token count arrives here.
					if (isset($data['usage']['output_tokens'])) {
						$usage['completion_tokens'] = (int) $data['usage']['output_tokens'];
						$usage['total_tokens'] = $usage['prompt_tokens'] + $usage['completion_tokens'];
					}
					break;
			}
		}
	}

	// =========================================================================
	// Request / response helpers
	// =========================================================================

	/**
	 * Build the Anthropic Messages API request body.
	 *
	 * Key differences from OpenAI:
	 *  - `system` is a top-level string field, not a role in messages[].
	 *  - Each message content is a typed block array (not a plain string).
	 *
	 * @param  array $messages Normalised messages.
	 * @param  array $params   Request parameters.
	 * @param  bool  $stream   Whether to enable streaming.
	 * @return array           Anthropic-format request body.
	 */
	private function build_request_body(array $messages, array $params, bool $stream): array
	{
		$model = (string) ($params['model'] ?? 'claude-haiku-4-5-20251001');
		$max_tokens = isset($params['max_tokens']) ? (int) $params['max_tokens'] : 2048;
		$temperature = isset($params['temperature']) ? (float) $params['temperature'] : 0.7;

		$system_parts = [];
		$claude_msgs = [];

		// Inject system_prompt param as a system message if present.
		if (! empty($params['system_prompt'])) {
			$system_parts[] = (string) $params['system_prompt'];
		}

		foreach ($messages as $msg) {
			$role = (string) ($msg['role'] ?? 'user');
			$content = $msg['content'] ?? '';

			if ('system' === $role) {
				// Collect all system messages into the top-level system field.
				if (is_array($content)) {
					foreach ($content as $c) {
						$system_parts[] = is_array($c) ? ($c['text'] ?? '') : (string) $c;
					}
				} else {
					$system_parts[] = (string) $content;
				}
				continue;
			}

			if ('user' !== $role && 'assistant' !== $role) {
				continue;
			}

			// Flatten content to a plain string for the text block.
			if (is_array($content)) {
				$parts_text = [];
				foreach ($content as $c) {
					$parts_text[] = is_array($c) ? ($c['text'] ?? '') : (string) $c;
				}
				$content = implode("\n\n", array_filter($parts_text));
			}

			$claude_msgs[] = [
				'role' => $role,
				'content' => [['type' => 'text', 'text' => (string) $content]],
			];
		}

		$body = [
			'model' => $model,
			'max_tokens' => $max_tokens,
			'temperature' => $temperature,
			'messages' => $claude_msgs,
			'stream' => $stream,
		];

		$system_text = trim(implode("\n\n", array_filter($system_parts)));
		if ('' !== $system_text) {
			$body['system'] = $system_text;
		}

		if (! empty($params['tools']) && is_array($params['tools'])) {
			$body['tools'] = $this->convert_tools_to_anthropic($params['tools']);
		}

		return $body;
	}

	/**
	 * Convert OpenAI-format tool definitions to Anthropic format.
	 *
	 * OpenAI: [{type:'function', function:{name, description, parameters}}]
	 * Anthropic: [{name, description, input_schema}]
	 *
	 * @param  array $tools OpenAI-format tool definitions.
	 * @return array        Anthropic-format tool definitions.
	 */
	private function convert_tools_to_anthropic(array $tools): array
	{
		$converted = [];

		foreach ($tools as $tool) {
			if (! is_array($tool)) {
				continue;
			}

			$fn = $tool['function'] ?? $tool;

			$converted[] = [
				'name' => (string) ($fn['name'] ?? ''),
				'description' => (string) ($fn['description'] ?? ''),
				'input_schema' => $fn['parameters'] ?? ['type' => 'object', 'properties' => []],
			];
		}

		return $converted;
	}

	/**
	 * Parse a wp_remote_post() response into a normalised response array.
	 *
	 * @param  array|\WP_Error $response
	 * @param  string          $request_id
	 * @return array
	 */
	private function parse_response($response, string $request_id): array
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
			return $this->error_response(500, 'parse_error', 'Invalid JSON response from Claude.', false);
		}

		// Extract text from content blocks.
		$text = '';
		if (isset($data['content']) && is_array($data['content'])) {
			foreach ($data['content'] as $block) {
				if (is_array($block) && 'text' === ($block['type'] ?? '') && isset($block['text'])) {
					$text .= ('' !== $text ? "\n\n" : '') . trim((string) $block['text']);
				}
			}
		}

		// Claude usage: input_tokens / output_tokens.
		$api_usage = $data['usage'] ?? [];
		$prompt_tokens = (int) ($api_usage['input_tokens'] ?? 0);
		$completion_tokens = (int) ($api_usage['output_tokens'] ?? 0);
		$usage = [
			'prompt_tokens' => $prompt_tokens,
			'completion_tokens' => $completion_tokens,
			'total_tokens' => $prompt_tokens + $completion_tokens,
		];

		$finish_reason = $this->normalize_stop_reason((string) ($data['stop_reason'] ?? 'end_turn'));

		// Extract tool calls from content blocks if present.
		$tool_calls = [];
		if (isset($data['content']) && is_array($data['content'])) {
			foreach ($data['content'] as $block) {
				if (is_array($block) && 'tool_use' === ($block['type'] ?? '')) {
					$tool_calls[] = [
						'id' => (string) ($block['id'] ?? ''),
						'name' => (string) ($block['name'] ?? ''),
						'arguments' => wp_json_encode($block['input'] ?? []),
					];
				}
			}
		}

		return [
			'message' => $text,
			'usage' => $usage,
			'finish_reason' => $finish_reason,
			'tool_calls' => $tool_calls,
			'provider_id' => self::PROVIDER_ID,
			'model_id' => (string) ($data['model'] ?? ''),
			'request_id' => $request_id,
		];
	}

	/**
	 * Build HTTP headers array for wp_remote_post.
	 *
	 * @return array<string, string>
	 */
	private function build_headers(): array
	{
		return [
			'x-api-key' => $this->api_key,
			'anthropic-version' => self::API_VERSION,
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Build headers as flat "Key: Value" strings for cURL.
	 *
	 * @return string[]
	 */
	private function build_curl_headers(): array
	{
		return [
			'x-api-key: ' . $this->api_key,
			'anthropic-version: ' . self::API_VERSION,
			'Content-Type: application/json',
		];
	}

	/**
	 * Normalise Anthropic stop_reason to the plugin's canonical finish_reason values.
	 *
	 * Anthropic → canonical:
	 *  end_turn      → stop
	 *  max_tokens    → length
	 *  tool_use      → tool_calls
	 *  stop_sequence → stop
	 *
	 * @param  string $reason Anthropic stop_reason.
	 * @return string         Canonical finish reason.
	 */
	private function normalize_stop_reason(string $reason): string
	{
		$map = [
			'end_turn' => 'stop',
			'max_tokens' => 'length',
			'tool_use' => 'tool_calls',
			'stop_sequence' => 'stop',
		];

		return $map[$reason] ?? 'stop';
	}

	/**
	 * Normalise a model ID by stripping date suffixes and lowercasing.
	 * e.g. 'claude-haiku-4-5-20251001' → 'claude-haiku-4-5'
	 *
	 * @param  string $model Raw model ID.
	 * @return string        Normalised base model ID.
	 */
	private function normalize_model_name(string $model): string
	{
		return strtolower(preg_replace('/-\d{8}$/', '', $model));
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
