<?php

/**
 * DeepSeek provider adapter — extends the OpenAI-compatible base class.
 *
 * DeepSeek uses an OpenAI-compatible Chat Completions API. The deepseek-reasoner
 * model returns an additional `reasoning_content` field which is stripped from
 * the user-facing output and logged separately as `reasoning_tokens`.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Provider\Adapters;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class DeepSeek_Adapter
 *
 * Adapter for DeepSeek's Chat Completions API.
 * Canonical provider ID: 'deepseek'
 *
 * @since 1.0.0
 * @package WPAIC\Provider\Adapters
 */
class DeepSeek_Adapter extends OpenAI_Compatible_Adapter
{
	/** {@inheritdoc} */
	protected function get_provider_id(): string
	{
		return 'deepseek';
	}

	/** {@inheritdoc} */
	protected function get_default_endpoint(): string
	{
		return 'https://api.deepseek.com/v1/chat/completions';
	}

	/** {@inheritdoc} */
	protected function get_default_model(): string
	{
		return 'deepseek-chat';
	}

	/** {@inheritdoc} */
	protected function get_model_pricing(): array
	{
		return [
			'deepseek-chat' => [0.27, 1.10],
			'deepseek-reasoner' => [0.55, 2.19],
		];
	}

	/**
	 * {@inheritdoc}
	 *
	 * Extracts reasoning_content from deepseek-reasoner responses and stores
	 * reasoning_tokens in the usage array for cost tracking.
	 */
	protected function post_process_response(array $result, array $raw_data): array
	{
		$choice = $raw_data['choices'][0] ?? [];
		$msg = $choice['message'] ?? [];

		// DeepSeek reasoner returns reasoning_content alongside content.
		if (isset($msg['reasoning_content']) && '' !== $msg['reasoning_content']) {
			$result['reasoning_content'] = (string) $msg['reasoning_content'];
		}

		// DeepSeek may report reasoning_tokens in usage.
		$api_usage = $raw_data['usage'] ?? [];
		if (isset($api_usage['reasoning_tokens'])) {
			$result['usage']['reasoning_tokens'] = (int) $api_usage['reasoning_tokens'];
		}

		return $result;
	}

	/**
	 * {@inheritdoc}
	 *
	 * Accumulates reasoning_content from streaming deltas (deepseek-reasoner).
	 */
	protected function post_process_sse_event(array $data, $callback, array &$extra): void
	{
		$choice = $data['choices'][0] ?? null;
		if (! is_array($choice)) {
			return;
		}

		$delta = $choice['delta'] ?? [];

		if (isset($delta['reasoning_content']) && '' !== $delta['reasoning_content']) {
			if (! isset($extra['reasoning_content'])) {
				$extra['reasoning_content'] = '';
			}
			$extra['reasoning_content'] .= (string) $delta['reasoning_content'];
		}

		// Capture reasoning_tokens from usage in final chunk.
		if (isset($data['usage']['reasoning_tokens'])) {
			$extra['reasoning_tokens'] = (int) $data['usage']['reasoning_tokens'];
		}
	}

	/**
	 * {@inheritdoc}
	 *
	 * Adds reasoning_content and reasoning_tokens to the stream summary.
	 */
	protected function build_stream_summary(array $summary, array $extra): array
	{
		if (isset($extra['reasoning_content']) && '' !== $extra['reasoning_content']) {
			$summary['reasoning_content'] = $extra['reasoning_content'];
		}

		if (isset($extra['reasoning_tokens'])) {
			$summary['usage']['reasoning_tokens'] = (int) $extra['reasoning_tokens'];
		}

		return $summary;
	}
}
