<?php

/**
 * OpenAI provider adapter — extends the OpenAI-compatible base class.
 *
 * Supports Chat Completions API. All shared HTTP, SSE, retry, and error logic
 * lives in OpenAI_Compatible_Adapter; this class defines OpenAI-specific constants.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Provider\Adapters;

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
class OpenAI_Adapter extends OpenAI_Compatible_Adapter
{
	/** {@inheritdoc} */
	protected function get_provider_id(): string
	{
		return 'openai';
	}

	/** {@inheritdoc} */
	protected function get_default_endpoint(): string
	{
		return 'https://api.openai.com/v1/chat/completions';
	}

	/** {@inheritdoc} */
	protected function get_default_model(): string
	{
		return 'gpt-4o-mini';
	}

	/**
	 * {@inheritdoc}
	 *
	 * Updated to approximate 2025-03 pricing.
	 */
	protected function get_model_pricing(): array
	{
		return [
			'gpt-4o' => [2.50, 10.00],
			'gpt-4o-mini' => [0.15, 0.60],
			'gpt-4-turbo' => [10.00, 30.00],
			'gpt-4' => [30.00, 60.00],
			'gpt-3.5-turbo' => [0.50, 1.50],
			'o1' => [15.00, 60.00],
			'o1-mini' => [3.00, 12.00],
			'o3-mini' => [1.10, 4.40],
		];
	}
}
