<?php

/**
 * OpenAI-Compatible base adapter — shared logic for providers using
 * OpenAI-compatible Chat Completions APIs.
 *
 * Providers like OpenAI, DeepSeek, and Grok/xAI share the same request/response
 * format. This abstract class extracts HTTP dispatch, SSE parsing, retry logic,
 * error normalization, and streaming so concrete adapters only define constants.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Provider\Adapters;

use WPAIC\Provider\Streamable_Provider_Interface;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class OpenAI_Compatible_Adapter
 *
 * Abstract base for OpenAI-compatible provider adapters.
 *
 * @since 1.0.0
 * @package WPAIC\Provider\Adapters
 */
abstract class OpenAI_Compatible_Adapter implements Streamable_Provider_Interface
{
	/** Connect timeout in seconds. */
	protected const CONNECT_TIMEOUT = 5;

	/** Stream timeout in seconds (full SSE connection lifetime). */
	protected const STREAM_TIMEOUT = 120;

	/** HTTP status codes that are safe to retry. */
	protected const RETRYABLE_CODES = [429, 500, 502, 503, 504];

	/** @var string Decrypted API key. */
	protected $api_key;

	/** @var string Resolved API endpoint URL. */
	protected $endpoint;

	/** @var int Request timeout in seconds. */
	protected $timeout;

	/** @var int Maximum retry attempts. */
	protected $max_retries;

	// =========================================================================
	// Abstract methods — must be implemented by each provider adapter
	// =========================================================================

	/**
	 * Return the canonical provider identifier.
	 *
	 * @return string e.g. 'openai', 'deepseek', 'grok'
	 */
	abstract protected function get_provider_id(): string;

	/**
	 * Return the default Chat Completions endpoint URL.
	 *
	 * @return string
	 */
	abstract protected function get_default_endpoint(): string;

	/**
	 * Return the model pricing table.
	 *
	 * Each entry maps a base model name to [input_rate, output_rate]
	 * in USD per 1M tokens.
	 *
	 * @return array<string, array{0: float, 1: float}>
	 */
	abstract protected function get_model_pricing(): array;

	// =========================================================================
	// Overridable methods
	// =========================================================================

	/**
	 * Return the default model ID when none is specified.
	 *
	 * @return string
	 */
	protected function get_default_model(): string
	{
		return 'gpt-4o-mini';
	}

	/**
	 * Build HTTP headers for wp_remote_post.
	 *
	 * @return array<string, string>
	 */
	protected function build_headers(): array
	{
		return [
			'Authorization' => 'Bearer ' . $this->api_key,
			'Content-Type' => 'application/json',
		];
	}

	/**
	 * Normalize a model name for pricing lookup.
	 *
	 * Default: strip date suffixes like -2024-09-01.
	 *
	 * @param  string $model Raw model name.
	 * @return string        Normalized model name.
	 */
	protected function normalize_model_name(string $model): string
	{
		return strtolower(preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $model));
	}

	/**
	 * Provider display name for error messages.
	 *
	 * @return string
	 */
	protected function get_provider_label(): string
	{
		return ucfirst($this->get_provider_id());
	}

	/**
	 * Post-process a parsed synchronous response.
	 *
	 * Subclasses can override to extract provider-specific fields
	 * (e.g. reasoning_content from DeepSeek).
	 *
	 * @param  array $result    Normalised response from parse_response().
	 * @param  array $raw_data  Raw decoded JSON from the API.
	 * @return array            Modified response.
	 */
	protected function post_process_response(array $result, array $raw_data): array
	{
		return $result;
	}

	/**
	 * Post-process a streaming SSE data event.
	 *
	 * Subclasses can override to handle provider-specific delta fields
	 * (e.g. reasoning_content from DeepSeek).
	 *
	 * @param  array         $data       Decoded JSON SSE data event.
	 * @param  callable|null $callback   Chunk callback.
	 * @param  array         &$extra     Provider-specific accumulator (passed by reference).
	 * @return void
	 */
	protected function post_process_sse_event(array $data, $callback, array &$extra): void
	{
		// Default: no-op.
	}

	/**
	 * Build the final stream summary with any provider-specific fields.
	 *
	 * @param  array $summary Base stream summary.
	 * @param  array $extra   Provider-specific accumulator from SSE processing.
	 * @return array          Modified summary.
	 */
	protected function build_stream_summary(array $summary, array $extra): array
	{
		return $summary;
	}

	// =========================================================================
	// Constructor
	// =========================================================================

	/**
	 * Construct the adapter with registry-injected configuration.
	 *
	 * @param array $config {
	 *     @type string $api_key     Decrypted API key.
	 *     @type string $endpoint    Optional endpoint override.
	 *     @type int    $timeout     Request timeout in seconds.
	 *     @type int    $max_retries Max retry attempts.
	 * }
	 */
	public function __construct($config = [])
	{
		$this->api_key = (string) ($config['api_key'] ?? '');
		$this->endpoint = '' !== ($config['endpoint'] ?? '') ? (string) $config['endpoint'] : $this->get_default_endpoint();
		$this->timeout = isset($config['timeout']) ? max(5, (int) $config['timeout']) : 30;
		$this->max_retries = isset($config['max_retries']) ? max(0, (int) $config['max_retries']) : 3;
	}

	// =========================================================================
	// Provider_Interface
	// =========================================================================

	/** {@inheritdoc} */
	public function get_id()
	{
		return $this->get_provider_id();
	}

	/** {@inheritdoc} */
	public function chat($messages, $params = [])
	{
		if ('' === $this->api_key) {
			return $this->error_response(401, 'auth_error', $this->get_provider_label() . ' API key is not configured.', false);
		}

		$body = $this->build_request_body($messages, $params, false);

		return $this->execute_with_retry($body, $params['request_id'] ?? '');
	}

	/** {@inheritdoc} */
	public function calculate_cost($usage, $model)
	{
		$base = $this->normalize_model_name((string) $model);
		$pricing = $this->get_model_pricing()[$base] ?? null;

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

	/** {@inheritdoc} */
	public function stream_chat($messages, $params = [], $callback = null)
	{
		if ('' === $this->api_key) {
			$error = $this->error_response(401, 'auth_error', $this->get_provider_label() . ' API key is not configured.', false);
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
	protected function execute_with_retry(array $body, string $request_id): array
	{
		$attempt = 0;

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

			$code = wp_remote_retrieve_response_code($response);

			if (in_array((int) $code, static::RETRYABLE_CODES, true) && $attempt <= $this->max_retries) {
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
			(int) ($last_error ?? 500),
			'provider_error',
			$this->get_provider_label() . ' request failed after ' . $this->max_retries . ' retries.',
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
	protected function execute_stream(array $body, string $request_id, $callback): array
	{
		if (! function_exists('curl_init')) {
			return $this->stream_fallback($body, $request_id, $callback);
		}

		$accumulated_text = '';
		$accumulated_calls = [];
		$finish_reason = 'stop';
		$usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
		$model_used = $body['model'] ?? '';
		$extra = [];

		$ch = curl_init($this->endpoint);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => (string) wp_json_encode($body),
			CURLOPT_HTTPHEADER => $this->build_curl_headers(),
			CURLOPT_RETURNTRANSFER => false,
			CURLOPT_TIMEOUT => static::STREAM_TIMEOUT,
			CURLOPT_CONNECTTIMEOUT => static::CONNECT_TIMEOUT,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_WRITEFUNCTION => function ($ch, $data) use (
				$callback,
				&$accumulated_text,
				&$accumulated_calls,
				&$finish_reason,
				&$usage,
				&$model_used,
				&$extra
			) {
				$this->process_sse_chunk(
					$data,
					$callback,
					$accumulated_text,
					$accumulated_calls,
					$finish_reason,
					$usage,
					$model_used,
					$extra
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

		$summary = [
			'message' => $accumulated_text,
			'usage' => $usage,
			'finish_reason' => $finish_reason,
			'tool_calls' => $accumulated_calls,
			'provider_id' => $this->get_provider_id(),
			'model_id' => $model_used,
			'request_id' => $request_id,
		];

		return $this->build_stream_summary($summary, $extra);
	}

	/**
	 * Fallback: stream_chat -> synchronous chat when cURL is unavailable.
	 *
	 * @param  array         $body
	 * @param  string        $request_id
	 * @param  callable|null $callback
	 * @return array
	 */
	protected function stream_fallback(array $body, string $request_id, $callback): array
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
	// SSE parsing
	// =========================================================================

	/**
	 * Process a raw SSE data chunk.
	 *
	 * @param  string        $raw_chunk
	 * @param  callable|null $callback
	 * @param  string        &$accumulated_text
	 * @param  array         &$tool_calls
	 * @param  string        &$finish_reason
	 * @param  array         &$usage
	 * @param  string        &$model_used
	 * @param  array         &$extra          Provider-specific accumulator.
	 * @return void
	 */
	protected function process_sse_chunk(
		string $raw_chunk,
		$callback,
		string &$accumulated_text,
		array &$tool_calls,
		string &$finish_reason,
		array &$usage,
		string &$model_used,
		array &$extra = []
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

				if (isset($data['model'])) {
					$model_used = (string) $data['model'];
				}

				if (isset($data['usage']) && is_array($data['usage'])) {
					$usage = [
						'prompt_tokens' => (int) ($data['usage']['prompt_tokens'] ?? 0),
						'completion_tokens' => (int) ($data['usage']['completion_tokens'] ?? 0),
						'total_tokens' => (int) ($data['usage']['total_tokens'] ?? 0),
					];
				}

				// Provider-specific SSE processing.
				$this->post_process_sse_event($data, $callback, $extra);

				$choice = $data['choices'][0] ?? null;
				if (! is_array($choice)) {
					continue;
				}

				if (isset($choice['finish_reason']) && null !== $choice['finish_reason']) {
					$finish_reason = $this->normalize_finish_reason((string) $choice['finish_reason']);
				}

				$delta = $choice['delta'] ?? [];

				if (isset($delta['content']) && '' !== $delta['content']) {
					$text = (string) $delta['content'];
					$accumulated_text .= $text;
					if (is_callable($callback)) {
						$callback(['type' => 'content', 'content' => $text]);
					}
				}

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
	 * Build the request body.
	 *
	 * @param  array $messages Normalised messages.
	 * @param  array $params   Request parameters.
	 * @param  bool  $stream   Whether to enable streaming.
	 * @return array
	 */
	protected function build_request_body(array $messages, array $params, bool $stream): array
	{
		$model = (string) ($params['model'] ?? $this->get_default_model());

		$api_messages = [];

		if (! empty($params['system_prompt'])) {
			array_unshift($api_messages, [
				'role' => 'system',
				'content' => (string) $params['system_prompt'],
			]);
		}

		foreach ($messages as $msg) {
			$api_messages[] = [
				'role' => (string) ($msg['role'] ?? 'user'),
				'content' => $msg['content'] ?? '',
			];
		}

		$body = [
			'model' => $model,
			'messages' => $api_messages,
			'temperature' => isset($params['temperature']) ? (float) $params['temperature'] : 0.7,
			'max_tokens' => isset($params['max_tokens']) ? (int) $params['max_tokens'] : 2048,
			'stream' => $stream,
		];

		if ($stream) {
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
	protected function parse_response($response, string $request_id): array
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
			$retryable = in_array($code, static::RETRYABLE_CODES, true);

			return $this->error_response($code, $this->classify_error_code($code), $api_msg, $retryable);
		}

		if (! is_array($data)) {
			return $this->error_response(500, 'parse_error', 'Invalid JSON response from ' . $this->get_provider_label() . '.', false);
		}

		$choice = $data['choices'][0] ?? [];
		$msg = $choice['message'] ?? [];

		$api_usage = $data['usage'] ?? [];
		$usage = [
			'prompt_tokens' => (int) ($api_usage['prompt_tokens'] ?? 0),
			'completion_tokens' => (int) ($api_usage['completion_tokens'] ?? 0),
			'total_tokens' => (int) ($api_usage['total_tokens'] ?? 0),
		];

		$finish_reason = $this->normalize_finish_reason((string) ($choice['finish_reason'] ?? 'stop'));

		$result = [
			'message' => (string) ($msg['content'] ?? ''),
			'usage' => $usage,
			'finish_reason' => $finish_reason,
			'tool_calls' => $msg['tool_calls'] ?? [],
			'provider_id' => $this->get_provider_id(),
			'model_id' => (string) ($data['model'] ?? $request_id),
			'request_id' => $request_id,
		];

		return $this->post_process_response($result, $data);
	}

	/**
	 * Build headers as flat "Key: Value" strings for cURL.
	 *
	 * @return string[]
	 */
	protected function build_curl_headers(): array
	{
		$headers = $this->build_headers();
		$flat = [];
		foreach ($headers as $key => $value) {
			$flat[] = $key . ': ' . $value;
		}

		return $flat;
	}

	/**
	 * Normalise finish_reason to canonical values.
	 *
	 * @param  string $reason
	 * @return string
	 */
	protected function normalize_finish_reason(string $reason): string
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
	 * @param  int    $code
	 * @param  string $type
	 * @param  string $message
	 * @param  bool   $retryable
	 * @return array
	 */
	protected function error_response(int $code, string $type, string $message, bool $retryable): array
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
	 * @param  int    $code
	 * @return string
	 */
	protected function classify_error_code(int $code): string
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
