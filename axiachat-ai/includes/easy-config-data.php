<?php

/**
 * Easy Config Wizard - Data definitions
 * Chatbot types, voice tones, response lengths, and default guidelines.
 *
 * @package AxiaChat
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Get available chatbot types with their default configurations.
 *
 * @return array
 */
function aichat_easycfg_get_chatbot_types()
{
	$types = [
		'customer_service' => [
			'icon' => '🎧',
			'name' => __('Customer Service Agent', 'axiachat-ai'),
			'description' => __('Helps users with questions, issues, and general support inquiries.', 'axiachat-ai'),
			'guidelines' => [
				__('Your main goal is to help users resolve their questions quickly and efficiently.', 'axiachat-ai'),
				__('Listen actively and provide clear, concise support.', 'axiachat-ai'),
				__('Respond accurately and avoid unnecessary complications.', 'axiachat-ai'),
				__('If you cannot help, politely suggest contacting human support.', 'axiachat-ai'),
			],
		],
		'secretary' => [
			'icon' => '📅',
			'name' => __('Scheduling Secretary', 'axiachat-ai'),
			'description' => __('Manages appointments, bookings, and schedule-related queries.', 'axiachat-ai'),
			'guidelines' => [
				__('Your primary objective is to help users schedule and manage appointments.', 'axiachat-ai'),
				__('Confirm dates, times, and availability clearly before finalizing.', 'axiachat-ai'),
				__('Use the tool to check availability (get_available_slots) and to book the appointment (book_appointment).', 'axiachat-ai'),
				__('Ask for the user\'s full name, email, and phone number before scheduling.', 'axiachat-ai'),
			],
		],
		'sales' => [
			'icon' => '💼',
			'name' => __('Sales Representative', 'axiachat-ai'),
			'description' => __('Recommends products, answers purchase questions, and guides buying decisions.', 'axiachat-ai'),
			'guidelines' => [
				__('Your goal is to help users find the best products or services for their needs.', 'axiachat-ai'),
				__('Be persuasive but not pushy; focus on value and benefits.', 'axiachat-ai'),
				__('Provide pricing information and purchase options when available.', 'axiachat-ai'),
				__('If they want a quote, use the save_lead tool to save their details (name and email, optionally phone) and let them know they will be contacted soon.', 'axiachat-ai'),
			],
		],
		'technical_support' => [
			'icon' => '🔧',
			'name' => __('Technical Support', 'axiachat-ai'),
			'description' => __('Resolves technical issues with step-by-step guidance.', 'axiachat-ai'),
			'guidelines' => [
				__('Your primary goal is to diagnose and resolve technical issues.', 'axiachat-ai'),
				__('Provide clear, numbered step-by-step instructions.', 'axiachat-ai'),
				__('Ask clarifying questions when the problem is not clear.', 'axiachat-ai'),
				__('Suggest escalation to human support for complex issues.', 'axiachat-ai'),
			],
		],
		'faq_assistant' => [
			'icon' => '❓',
			'name' => __('FAQ Assistant', 'axiachat-ai'),
			'description' => __('Answers frequently asked questions based on your knowledge base.', 'axiachat-ai'),
			'guidelines' => [
				__('Your main goal is to answer common questions quickly and accurately.', 'axiachat-ai'),
				__('Use the provided context to give precise answers.', 'axiachat-ai'),
				__('Keep responses brief and to the point.', 'axiachat-ai'),
				__('Suggest related questions the user might find helpful.', 'axiachat-ai'),
			],
		],
		'custom' => [
			'icon' => '✨',
			'name' => __('Custom Chatbot', 'axiachat-ai'),
			'description' => __('Create a fully customized chatbot with your own guidelines.', 'axiachat-ai'),
			'guidelines' => [
				__('Add your first guideline here...', 'axiachat-ai'),
			],
		],
	];

	return apply_filters('aichat_easycfg_chatbot_types', $types);
}

/**
 * Get available voice tones.
 *
 * @return array
 */
function aichat_easycfg_get_voice_tones()
{
	$tones = [
		'friendly' => [
			'icon' => '😊',
			'name' => __('Friendly', 'axiachat-ai'),
			'instruction' => __('Use a warm, approachable, and friendly tone. Be conversational and make the user feel welcome.', 'axiachat-ai'),
		],
		'casual' => [
			'icon' => '😎',
			'name' => __('Casual', 'axiachat-ai'),
			'instruction' => __('Use a relaxed, informal tone. Keep things light and easy-going while remaining helpful.', 'axiachat-ai'),
		],
		'professional' => [
			'icon' => '👔',
			'name' => __('Professional', 'axiachat-ai'),
			'instruction' => __('Use a formal, business-appropriate tone. Be courteous, precise, and maintain professionalism.', 'axiachat-ai'),
		],
		'empathetic' => [
			'icon' => '💗',
			'name' => __('Empathetic', 'axiachat-ai'),
			'instruction' => __('Show understanding and compassion. Acknowledge user feelings and provide supportive responses.', 'axiachat-ai'),
		],
		'concise' => [
			'icon' => '⚡',
			'name' => __('Concise', 'axiachat-ai'),
			'instruction' => __('Be direct and to the point. Provide clear, brief answers without unnecessary elaboration.', 'axiachat-ai'),
		],
	];

	return apply_filters('aichat_easycfg_voice_tones', $tones);
}

/**
 * Get response length options.
 *
 * @return array
 */
function aichat_easycfg_get_response_lengths()
{
	$lengths = [
		'minimal' => [
			'name' => __('Minimal', 'axiachat-ai'),
			'description' => __('1-2 sentences', 'axiachat-ai'),
			'instruction' => __('Keep responses extremely brief. Answer in 1-2 short sentences maximum.', 'axiachat-ai'),
			'value' => 1,
		],
		'short' => [
			'name' => __('Short', 'axiachat-ai'),
			'description' => __('2-3 sentences', 'axiachat-ai'),
			'instruction' => __('Keep responses concise. Answer in 2-3 sentences, elaborating only if asked.', 'axiachat-ai'),
			'value' => 2,
		],
		'medium' => [
			'name' => __('Medium', 'axiachat-ai'),
			'description' => __('Balanced', 'axiachat-ai'),
			'instruction' => __('Provide balanced responses. Include necessary context but avoid excessive detail.', 'axiachat-ai'),
			'value' => 3,
		],
		'long' => [
			'name' => __('Long', 'axiachat-ai'),
			'description' => __('Detailed', 'axiachat-ai'),
			'instruction' => __('Provide detailed, comprehensive responses. Include examples and thorough explanations.', 'axiachat-ai'),
			'value' => 4,
		],
	];

	return apply_filters('aichat_easycfg_response_lengths', $lengths);
}

/**
 * Get available AI providers.
 *
 * @return array
 */
function aichat_easycfg_get_providers()
{
	$providers = [
		'openai' => [
			'icon' => '🤖',
			'name' => __('OpenAI (GPT)', 'axiachat-ai'),
			'description' => __('GPT-5.2, GPT-5, GPT-4o. Best for general purpose chat.', 'axiachat-ai'),
			'option_key' => 'aichat_openai_api_key',
			'help_url' => 'https://platform.openai.com/api-keys',
		],
		'claude' => [
			'icon' => '🧠',
			'name' => __('Anthropic (Claude)', 'axiachat-ai'),
			'description' => __('Claude Opus 4.6, Sonnet 4.5, Haiku 4.5. Great for nuanced conversations.', 'axiachat-ai'),
			'option_key' => 'aichat_claude_api_key',
			'help_url' => 'https://console.anthropic.com/settings/keys',
		],
		'gemini' => [
			'icon' => '💎',
			'name' => __('Google (Gemini)', 'axiachat-ai'),
			'description' => __('Gemini 2.5 Flash, 2.5 Pro, 3 Preview. Google\'s latest AI technology.', 'axiachat-ai'),
			'option_key' => 'aichat_gemini_api_key',
			'help_url' => 'https://aistudio.google.com/app/apikey',
		],
	];

	return apply_filters('aichat_easycfg_providers', $providers);
}

/**
 * Build the system prompt from wizard configuration.
 *
 * @param array $config Wizard configuration (type, tone, length, guidelines).
 * @return string The generated system prompt.
 */
function aichat_easycfg_build_system_prompt($config)
{
	$types = aichat_easycfg_get_chatbot_types();
	$tones = aichat_easycfg_get_voice_tones();
	$lengths = aichat_easycfg_get_response_lengths();

	$type_key = isset($config['chatbot_type']) ? sanitize_key($config['chatbot_type']) : 'customer_service';
	$tone_key = isset($config['voice_tone']) ? sanitize_key($config['voice_tone']) : 'friendly';
	$length_key = isset($config['response_length']) ? sanitize_key($config['response_length']) : 'short';
	$guidelines = isset($config['guidelines']) && is_array($config['guidelines']) ? $config['guidelines'] : [];

	// Get type info
	$type_name = isset($types[ $type_key ]['name']) ? $types[ $type_key ]['name'] : __('AI Assistant', 'axiachat-ai');

	// Get tone instruction
	$tone_instruction = isset($tones[ $tone_key ]['instruction']) ? $tones[ $tone_key ]['instruction'] : '';

	// Get length instruction
	$length_instruction = isset($lengths[ $length_key ]['instruction']) ? $lengths[ $length_key ]['instruction'] : '';

	// Build the prompt
	$prompt_parts = [];

	// Role definition
	$prompt_parts[] = sprintf(
		/* translators: %s: chatbot type name */
		__('You are a %s.', 'axiachat-ai'),
		$type_name
	);

	// Add tone
	if ($tone_instruction) {
		$prompt_parts[] = $tone_instruction;
	}

	// Add length preference
	if ($length_instruction) {
		$prompt_parts[] = $length_instruction;
	}

	// Add guidelines
	if (! empty($guidelines)) {
		$prompt_parts[] = __('Follow these guidelines:', 'axiachat-ai');
		foreach ($guidelines as $index => $guideline) {
			$guideline = sanitize_text_field($guideline);
			if (! empty($guideline)) {
				$prompt_parts[] = sprintf('%d. %s', $index + 1, $guideline);
			}
		}
	}

	// Add context usage instruction
	$prompt_parts[] = __('Use the provided CONTEXT to answer questions accurately. If the information is not available in the context, clearly state that and suggest contacting human support.', 'axiachat-ai');

	return implode("\n\n", $prompt_parts);
}

/**
 * Get existing bots for the wizard selection.
 *
 * @return array
 */
function aichat_easycfg_get_existing_bots()
{
	global $wpdb;
	$table = aichat_bots_table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	$bots = $wpdb->get_results(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		"SELECT id, slug, name, created_at FROM {$table} ORDER BY created_at ASC",
		ARRAY_A
	);

	return $bots ? $bots : [];
}

/**
 * Get default model for a provider.
 * Delegates to the centralised model registry.
 *
 * @param string $provider Provider key (openai, claude, gemini).
 * @return string Default model for that provider.
 */
function aichat_easycfg_default_model($provider)
{
	return aichat_get_default_model($provider);
}
