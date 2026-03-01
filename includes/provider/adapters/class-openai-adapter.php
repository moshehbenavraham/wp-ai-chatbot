<?php

/**
 * OpenAI provider adapter — implements Provider_Interface and Streamable_Provider_Interface.
 *
 * Supports Chat Completions API. Translates the unified request/response format.
 * Implements exponential backoff for retryable errors (429, 500, 503).
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
 * Class OpenAI_Adapter
 *
 * Adapter for OpenAI's Chat Completions API.
 * Canonical provider ID: 'openai'
 *
 * @since 1.0.0
 * @package WPAIC\Provider\Adapters
 */
class OpenAI_Adapter implements Streamable_Provider_Interface
{
	/** Canonical provider ID (stored in DB, never change). */
	private const PROVIDER_ID = 'openai';

	/** Default Chat Completions endpoint. */
	private const DEFAULT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/** Connect timeout in seconds. */
	private const CONNECT_TIMEOUT = 5;

	/** Stream timeout in seconds (full SSE connection lifetime). */
	private const STREAM_TIMEOUT = 120;

	/** HTTP status codes that are safe to retry. */
	private const RETRYABLE_CODES = [429, 500, 502, 503, 504];

	/**
	 * Per-model pricing in USD per 1M tokens [input, output].
	 * Updated to approximate 2025-03 pricing.
	 *
	 * @var array<string, array{0: float, 1: float}>
	 */
	private const MODEL_PRICING = [
		'gpt-4o' => [2.50, 10.00],
		'gpt-4o-mini' => [0.15, 0.60],
		'gpt-4-turbo' => [10.00, 30.00],
		'gpt-4' => [30.00, 60.00],
		'gpt-3.5-turbo' => [0.50, 1.50],
		'o1' => [15.00, 60.00],
		'o1-mini' => [3.00, 12.00],
		'o3-mini' => [1.10, 4.40],
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
	 *     @type string $api_key     Decrypted OpenAI API key.
	 *     @type string $endpoint    Optional endpoint override.
	 *     @type int    $timeout     Request timeout in seconds.
	 *     @type int    $max_retries Max retry attempts.
	 * }
	 */
	public function __construct($config = [])
	{
		$this->api_key = (string) ($config['api_key'] ?? '');
		$this->endpoint = '' !== ($config['endpoint'] ?? '') ? (string) $config['endpoint'] : self::DEFAULT_ENDPOINT;
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
			return $this->error_response(401, 'auth_error', 'OpenAI API key is not configured.', false);
		}

		$body = $this->build_request_body($messages, $params, false);

		return $this->execute_with_retry($body, $params['request_id'] ?? '');
	}

	/**
	 * Calculate cost in microcents.
	 *
	 * 1 microcent = 1/1 000 000 USD.
	 * Formula: tokens / 1_000_000 * rate_per_1M * 1_000_000 = tokens * rate_per_1M.
	 *
	 * {@inheritdoc}
	 *
	 * @param  array  $usage Token counts.
	 * @param  string $model Model ID.
	 * @return int|null      Cost in microcents.
	 */
	public function calculate_cost($usage, $model)
	{
		// Normalise model name (strip version suffix like -2024-09-01).
		$base = strtolower(preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', (string) $model));

		$pricing = self::MODEL_PRICING[$base] ?? null;
		if (null === $pricing) {
			return null;
		}

		[$input_rate, $output_rate] = $pricing;

		$prompt_tokens = (int) ($usage['prompt_tokens'] ?? 0);
		$completion_tokens = (int) ($usage['completion_tokens'] ?? 0);

		// Microcents: rate is per 1M tokens; 1 USD = 100 cents = 1 000 000 microcents.
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
			$error = $this->error_response(401, 'auth_error', 'OpenAI API key is not configured.', false);
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

		do {
			if ($attempt > 0) {
				// Exponential back-off: 1s, 2s, 4s ...
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

			$code = wp_remote_retrieve_response_code($response);

			if (in_array((int) $code, self::RETRYABLE_CODES, true) && $attempt <= $this->max_retries) {
				$last_error = $code;
				continue;
			}

			return $this->parse_response($response, $request_id);

		} while ($attempt <= $this->max_retries);

		// All retries exhausted.
		if (isset($last_error) && is_wp_error($last_error)) {
			return $this->error_response(
				500,
				'connection_error',
				$last_error->get_error_message(),
				false
			);
		}

		return $this->error_response(
			(int) ($last_error ?? 500),
			'provider_error',
			'OpenAI request failed after ' . $this->max_retries . ' retries.',
			false
		);
	}

	/**
	 * Execute a streaming request via cURL.
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
		$stream_error = null;

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

		// Emit final 'done' chunk (SCR-04).
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
	 * Fallback: stream_chat → synchronous chat when cURL is unavailable.
	 *
	 * @param  array         $body
	 * @param  string        $request_id
	 * @param  callable|null $callback
	 * @return array
	 */
	private function stream_fallback(array $body, string $request_id, $callback): array
	{
		$body['stream'] = false;
		$sync_body = $body;

		$response = $this->execute_with_retry($sync_body, $request_id);

		// Emit the full text as a single content chunk + done.
		if (isset($response['message']) && is_callable($callback)) {
			$callback(['type' => 'content', 'content' => $response['message']]);
			$callback(['type' => 'done']);
		}

		return $response;
	}

	// =========================================================================
	// SSE parsing
	// =========================================================================

	/**
	 * Process a raw SSE data chunk, parsing deltas and calling $callback.
	 *
	 * @param  string        $raw_chunk        Raw bytes from cURL WRITEFUNCTION.
	 * @param  callable|null $callback
	 * @param  string        &$accumulated_text Accumulated response text.
	 * @param  array         &$tool_calls       Accumulated tool call data.
	 * @param  string        &$finish_reason    Final finish reason.
	 * @param  array         &$usage            Token usage.
	 * @param  string        &$model_used       Model actually used.
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

			if ('' === $line || 'data: [DONE]' === $line) {
				continue;
			}

			if (strpos($line, 'data: ') === 0) {
				$json = substr($line, 6);
				$data = json_decode($json, true);

				if (! is_array($data)) {
					continue;
				}

				// Capture model.
				if (isset($data['model'])) {
					$model_used = (string) $data['model'];
				}

				// Usage in final chunk (with stream_options.include_usage).
				if (isset($data['usage']) && is_array($data['usage'])) {
					$usage = [
						'prompt_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
						'completion_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
						'total_tokens' => (int) ($data['usage']['total_tokens'] ?? 0),
					];
				}

				$choice = $data['choices'][0] ?? null;
				if (! is_array($choice)) {
					continue;
				}

				// Finish reason.
				if (isset($choice['finish_reason']) && null !== $choice['finish_reason']) {
					$finish_reason = $this->normalize_finish_reason((string) $choice['finish_reason']);
				}

				$delta = $choice['delta'] ?? [];

				// Content delta.
				if (isset($delta['content']) && '' !== $delta['content']) {
					$text = (string) $delta['content'];
					$accumulated_text .= $text;
					if (is_callable($callback)) {
						$callback(['type' => 'content', 'content' => $text]);
					}
				}

				// Tool call deltas.
				if (isset($delta['tool_calls']) && is_array($delta['tool_calls'])) {
					foreach ($delta['tool_calls'] as $tc_delta) {
						if (is_callable($callback)) {
							$callback(['type' => 'tool_call', 'tool_call' => $tc_delta]);
						}
						$tool_calls[] = $tc_delta;
					}
				}
			}
		}
	}

	// =========================================================================
	// Request / response helpers
	// =========================================================================

	/**
	 * Build the OpenAI API request body.
	 *
	 * @param  array $messages Normalised messages.
	 * @param  array $params   Request parameters.
	 * @param  bool  $stream   Whether to enable streaming.
	 * @return array           OpenAI-format request body.
	 */
	private function build_request_body(array $messages, array $params, bool $stream): array
	{
		$model = (string) ($params['model'] ?? 'gpt-4o-mini');

		// Build OpenAI messages array; inject system prompt if provided.
		$openai_messages = [];

		if (! empty($params['system_prompt'])) {
			array_unshift($openai_messages, [
				'role' => 'system',
				'content' => (string) $params['system_prompt'],
			]);
		}

		foreach ($messages as $msg) {
			$openai_messages[] = [
				'role' => (string) ($msg['role'] ?? 'user'),
				'content' => $msg['content'] ?? '',
			];
		}

		$body = [
			'model' => $model,
			'messages' => $openai_messages,
			'temperature' => isset($params['temperature']) ? (float) $params['temperature'] : 0.7,
			'max_tokens' => isset($params['max_tokens']) ? (int) $params['max_tokens'] : 2048,
			'stream' => $stream,
		];

		if ($stream) {
			// Request usage in the final streaming chunk.
			$body['stream_options'] = ['include_usage' => true];
		}

		if (! empty($params['tools']) && is_array($params['tools'])) {
			$body['tools'] = $params['tools'];
		}

		return $body;
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
			return $this->error_response(
				500,
				'connection_error',
				$response->get_error_message(),
				true
			);
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$raw = wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);

		if ($code >= 400) {
			$api_msg = $data['error']['message'] ?? ('HTTP ' . $code);
			$retryable = in_array($code, self::RETRYABLE_CODES, true);

			return $this->error_response($code, $this->classify_error_code($code), $api_msg, $retryable);
		}

		if (! is_array($data)) {
			return $this->error_response(500, 'parse_error', 'Invalid JSON response from OpenAI.', false);
		}

		$choice = $data['choices'][0] ?? [];
		$msg = $choice['message'] ?? [];

		// Build usage.
		$api_usage = $data['usage'] ?? [];
		$usage = [
			'prompt_tokens' => (int) ($api_usage['prompt_tokens'] ?? 0),
			'completion_tokens' => (int) ($api_usage['completion_tokens'] ?? 0),
			'total_tokens' => (int) ($api_usage['total_tokens'] ?? 0),
		];

		$finish_reason = $this->normalize_finish_reason((string) ($choice['finish_reason'] ?? 'stop'));

		return [
			'message' => (string) ($msg['content'] ?? ''),
			'usage' => $usage,
			'finish_reason' => $finish_reason,
			'tool_calls' => $msg['tool_calls'] ?? [],
			'provider_id' => self::PROVIDER_ID,
			'model_id' => (string) ($data['model'] ?? $request_id),
			'request_id' => $request_id,
		];
	}

	/**
	 * Build an HTTP headers array for wp_remote_post.
	 *
	 * @return array<string, string>
	 */
	private function build_headers(): array
	{
		return [
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Build headers as a flat array of "Key: Value" strings for cURL.
	 *
	 * @return string[]
	 */
	private function build_curl_headers(): array
	{
		return [
			'Authorization: Bearer ' . $this->api_key,
			'Content-Type: application/json',
		];
	}

	/**
	 * Normalise OpenAI finish_reason to the plugin's canonical values.
	 *
	 * @param  string $reason OpenAI finish_reason.
	 * @return string         Canonical finish reason.
	 */
	private function normalize_finish_reason(string $reason): string
	{
		$map = [
			'stop' => 'stop',
			'length' => 'length',
			'tool_calls' => 'tool_calls',
			'content_filter' => 'content_filter',
		];

		return $map[$reason] ?? 'stop';
	}

	/**
	 * Build a standardised error response payload.
	 *
	 * @param  int    $code         HTTP / internal status code.
	 * @param  string $type         Error taxonomy type.
	 * @param  string $message      Technical message (for logging only).
	 * @param  bool   $retryable    Whether the error is retryable.
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
