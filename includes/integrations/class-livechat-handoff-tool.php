<?php

/**
 * Livechat Handoff Tool.
 *
 * Implements Tool_Interface so the AI can initiate a live-agent handoff during
 * a conversation. When called, the session is placed in the `pending_agent`
 * queue and an optional reason is recorded.
 *
 * @package WP_AI_Chatbot
 * @since   1.1.0
 */

namespace WPAIC\Integrations;

use WPAIC\Tools\Tool_Interface;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Livechat_Handoff_Tool
 *
 * @since   1.1.0
 * @package WPAIC\Integrations
 */
class Livechat_Handoff_Tool implements Tool_Interface
{
	/** {@inheritdoc} */
	public function get_id(): string
	{
		return 'wpaic_livechat_handoff';
	}

	/** {@inheritdoc} */
	public function get_name(): string
	{
		return __('Request Live Agent', 'wp-ai-chatbot');
	}

	/** {@inheritdoc} */
	public function get_description(): string
	{
		return 'Transfer the conversation to a live human agent. Use only when the visitor explicitly asks to speak with a person, or when you cannot adequately answer their question. Provide a concise reason to help the agent prepare for the conversation.';
	}

	/** {@inheritdoc} */
	public function get_schema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'reason' => [
					'type' => 'string',
					'description' => 'Short description of why the visitor needs a human agent (shown to the agent).',
				],
			],
			'required' => [],
		];
	}

	/** {@inheritdoc} */
	public function get_permission(): string
	{
		return 'none';
	}

	/** {@inheritdoc} */
	public function execute(array $args, array $context): array
	{
		$session_token = sanitize_text_field($context['session_id'] ?? '');
		$reason = sanitize_textarea_field($args['reason'] ?? '');

		if ('' === $session_token) {
			return [
				'ok' => false,
				'error' => 'no_session',
				'output' => __('Could not initiate handoff: no active session found.', 'wp-ai-chatbot'),
			];
		}

		$result = Livechat_Manager::request_handoff($session_token, $reason, $context);

		if ($result['ok']) {
			return [
				'ok' => true,
				'output' => $result['message'],
			];
		}

		return [
			'ok' => false,
			'error' => 'handoff_failed',
			'output' => $result['message'],
		];
	}

	/** {@inheritdoc} */
	public function to_provider_definition(): array
	{
		return [
			'name' => $this->get_id(),
			'description' => $this->get_description(),
			'parameters' => $this->get_schema(),
		];
	}
}
