<?php

/**
 * Grok/xAI provider adapter — extends the OpenAI-compatible base class.
 *
 * xAI's Grok models use an OpenAI-compatible Chat Completions API.
 * No provider-specific overrides needed beyond constants.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Provider\Adapters;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Grok_Adapter
 *
 * Adapter for xAI's Grok Chat Completions API.
 * Canonical provider ID: 'grok'
 *
 * @since 1.0.0
 * @package WPAIC\Provider\Adapters
 */
class Grok_Adapter extends OpenAI_Compatible_Adapter
{
	/** {@inheritdoc} */
	protected function get_provider_id(): string
	{
		return 'grok';
	}

	/** {@inheritdoc} */
	protected function get_default_endpoint(): string
	{
		return 'https://api.x.ai/v1/chat/completions';
	}

	/** {@inheritdoc} */
	protected function get_default_model(): string
	{
		return 'grok-3-mini';
	}

	/** {@inheritdoc} */
	protected function get_model_pricing(): array
	{
		return [
			'grok-3' => [3.00, 15.00],
			'grok-3-mini' => [0.30, 0.50],
			'grok-beta' => [5.00, 15.00],
		];
	}
}
