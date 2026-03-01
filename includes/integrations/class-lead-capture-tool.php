<?php

/**
 * Lead Capture Tool.
 *
 * Implements Tool_Interface so the AI can collect and save a visitor's
 * contact information as a lead during a conversation.
 *
 * The tool is disabled (returns a polite refusal) when lead capture is turned
 * off in the Chat Behavior settings (`wpaic_lead_capture_settings.enabled`).
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
 * Class Lead_Capture_Tool
 *
 * @since   1.1.0
 * @package WPAIC\Integrations
 */
class Lead_Capture_Tool implements Tool_Interface
{
	/** {@inheritdoc} */
	public function get_id(): string
	{
		return 'wpaic_capture_lead';
	}

	/** {@inheritdoc} */
	public function get_name(): string
	{
		return __('Capture Lead', 'wp-ai-chatbot');
	}

	/** {@inheritdoc} */
	public function get_description(): string
	{
		return 'Save a visitor\'s contact information as a business lead. Call this when the user voluntarily shares their name, email, or phone and wants to be contacted, or after completing a lead capture flow. At least one of name, email, or phone must be provided. All other fields are optional.';
	}

	/** {@inheritdoc} */
	public function get_schema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string', 'description' => 'Full name of the contact.'],
				'email' => ['type' => 'string', 'description' => 'Email address.'],
				'phone' => ['type' => 'string', 'description' => 'Phone or mobile number.'],
				'company' => ['type' => 'string', 'description' => 'Company or organisation name.'],
				'interest' => ['type' => 'string', 'description' => 'Product or service the lead is interested in.'],
				'notes' => ['type' => 'string', 'description' => 'Additional notes or context from the conversation.'],
			],
			'required' => [],
		];
	}

	/** {@inheritdoc} */
	public function get_permission(): string
	{
		// Available to all visitors; Lead_Manager::save() performs its own auth check
		// against the enabled setting.
		return 'none';
	}

	/** {@inheritdoc} */
	public function execute(array $args, array $context): array
	{
		$result = Lead_Manager::save($args, $context);

		if ($result['ok']) {
			return [
				'ok' => true,
				'output' => __('Thank you! Your information has been saved. We will be in touch soon.', 'wp-ai-chatbot'),
			];
		}

		// Translate internal errors into user-friendly messages.
		switch ($result['error'] ?? '') {
			case 'disabled':
				return [
					'ok' => false,
					'error' => 'disabled',
					'output' => __('Lead capture is not currently available. Please use the contact form instead.', 'wp-ai-chatbot'),
				];

			case 'no_identifier':
				return [
					'ok' => false,
					'error' => 'no_identifier',
					'output' => __('Please provide at least your name, email address, or phone number.', 'wp-ai-chatbot'),
				];

			default:
				return [
					'ok' => false,
					'error' => $result['error'] ?? 'save_failed',
					'output' => $result['message'] ?? __('Could not save your information. Please try again.', 'wp-ai-chatbot'),
				];
		}
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
