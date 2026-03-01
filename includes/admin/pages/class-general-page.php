<?php

/**
 * General Settings page controller.
 *
 * Handles the primary plugin settings page (slug: 'uwpaic').
 * Covers provider selection, API key entry, default model, and system-level toggles.
 *
 * Spec: phase02-session06/.../admin_settings_architecture.md §5.1
 * LOC target: < 300 lines.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Admin\Pages;

use WPAIC\Admin\Admin_Page;
use WPAIC\Admin\Settings_Manager;
use WPAIC\Security\Sanitizer;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class General_Page
 *
 * General Settings: provider selection, default API key, default model.
 * Provider-specific credential fields reveal via JS on provider change (Baachal pattern).
 *
 * @since 1.0.0
 * @package WPAIC\Admin\Pages
 */
class General_Page extends Admin_Page
{
	/** WP Settings option group for this page. */
	private const OPTION_GROUP = 'wpaic_general_settings';

	/** @return string */
	public function get_slug(): string
	{
		return 'uwpaic';
	}

	/** @return string */
	public function get_title(): string
	{
		return __('WP AI Chatbot — General Settings', 'wp-ai-chatbot');
	}

	// =========================================================================
	// Settings registration
	// =========================================================================

	/** {@inheritdoc} */
	public function register_settings(): void
	{
		// ---- Provider settings ----

		register_setting(self::OPTION_GROUP, 'wpaic_provider_default', [
			'type' => 'string',
			'sanitize_callback' => Settings_Manager::sanitize_callback(Sanitizer::TYPE_SLUG),
			'default' => 'openai',
		]);

		register_setting(self::OPTION_GROUP, 'wpaic_provider_default_model', [
			'type' => 'string',
			'sanitize_callback' => Settings_Manager::sanitize_callback(Sanitizer::TYPE_SLUG),
			'default' => 'gpt-4o-mini',
		]);

		register_setting(self::OPTION_GROUP, 'wpaic_provider_timeout', [
			'type' => 'integer',
			'sanitize_callback' => Settings_Manager::sanitize_callback(Sanitizer::TYPE_INT, ['min' => 5, 'max' => 300]),
			'default' => 30,
		]);

		register_setting(self::OPTION_GROUP, 'wpaic_provider_max_retries', [
			'type' => 'integer',
			'sanitize_callback' => Settings_Manager::sanitize_callback(Sanitizer::TYPE_INT, ['min' => 0, 'max' => 10]),
			'default' => 3,
		]);

		// Sensitive fields (API keys) use the encrypt-on-save callback.
		foreach (['openai', 'claude', 'gemini', 'deepseek', 'grok'] as $provider) {
			register_setting(self::OPTION_GROUP, "wpaic_provider_{$provider}_key", [
				'type' => 'string',
				'sanitize_callback' => Settings_Manager::sensitive_sanitize_callback(),
				'default' => '',
			]);
		}

		// ---- Section ----

		add_settings_section(
			'wpaic_general_provider_section',
			__('Provider Configuration', 'wp-ai-chatbot'),
			[$this, 'render_section_description'],
			$this->get_slug()
		);

		// Fields.
		add_settings_field(
			'wpaic_provider_default',
			__('Default AI Provider', 'wp-ai-chatbot'),
			[$this, 'render_provider_select_field'],
			$this->get_slug(),
			'wpaic_general_provider_section'
		);

		add_settings_field(
			'wpaic_provider_api_keys',
			__('API Keys', 'wp-ai-chatbot'),
			[$this, 'render_api_key_fields'],
			$this->get_slug(),
			'wpaic_general_provider_section'
		);

		add_settings_field(
			'wpaic_provider_default_model',
			__('Default Model', 'wp-ai-chatbot'),
			[$this, 'render_default_model_field'],
			$this->get_slug(),
			'wpaic_general_provider_section'
		);

		add_settings_field(
			'wpaic_provider_timeout',
			__('Request Timeout (seconds)', 'wp-ai-chatbot'),
			[$this, 'render_timeout_field'],
			$this->get_slug(),
			'wpaic_general_provider_section'
		);
	}

	// =========================================================================
	// Page render
	// =========================================================================

	/** {@inheritdoc} */
	public function render(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'wp-ai-chatbot'));
		}

		$this->render_page_header();
		?>
		<form method="post" action="options.php" novalidate>
			<?php
			settings_fields(self::OPTION_GROUP);
		do_settings_sections($this->get_slug());
		submit_button(__('Save Settings', 'wp-ai-chatbot'));
		?>
		</form>
		<?php
		$this->render_page_footer();
	}

	// =========================================================================
	// Field renderers
	// =========================================================================

	/** Render section description text. */
	public function render_section_description(): void
	{
		echo '<p>';
		esc_html_e(
			'Configure the default AI provider and enter your API credentials. '
			. 'All API keys are encrypted before storage.',
			'wp-ai-chatbot'
		);
		echo '</p>';
	}

	/** Render the default provider <select>. */
	public function render_provider_select_field(): void
	{
		$current = (string) get_option('wpaic_provider_default', 'openai');

		$providers = [
			'openai' => 'OpenAI (GPT-4o, GPT-4o mini...)',
			'claude' => 'Anthropic Claude',
			'gemini' => 'Google Gemini',
			'deepseek' => 'DeepSeek',
			'grok' => 'xAI Grok',
		];
		?>
		<select id="wpaic_provider_default" name="wpaic_provider_default">
			<?php foreach ($providers as $value => $label) : ?>
				<option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>>
					<?php echo esc_html($label); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e('The AI provider used when no shortcode attribute overrides it.', 'wp-ai-chatbot'); ?>
		</p>
		<?php
	}

	/** Render API key inputs — one per provider, shown/hidden by JS. */
	public function render_api_key_fields(): void
	{
		$providers = [
			'openai' => __('OpenAI API Key', 'wp-ai-chatbot'),
			'claude' => __('Anthropic Claude API Key', 'wp-ai-chatbot'),
			'gemini' => __('Google Gemini API Key', 'wp-ai-chatbot'),
			'deepseek' => __('DeepSeek API Key', 'wp-ai-chatbot'),
			'grok' => __('xAI Grok API Key', 'wp-ai-chatbot'),
		];

		foreach ($providers as $provider => $label) :
			$key_option = "wpaic_provider_{$provider}_key";
			$masked = Settings_Manager::get_masked($key_option);
			?>
			<div class="wpaic-provider-api-key-row" data-provider="<?php echo esc_attr($provider); ?>">
				<label for="wpaic-key-<?php echo esc_attr($provider); ?>">
					<?php echo esc_html($label); ?>
				</label>
				<div class="wpaic-key-input-wrap">
					<?php if ('' !== $masked) : ?>
						<span class="wpaic-key-masked" aria-label="<?php esc_attr_e('API key stored', 'wp-ai-chatbot'); ?>">
							<?php echo esc_html($masked); ?>
						</span>
					<?php endif; ?>
					<input
						type="password"
						id="wpaic-key-<?php echo esc_attr($provider); ?>"
						name="<?php echo esc_attr($key_option); ?>"
						value=""
						placeholder="<?php echo '' !== $masked
							? esc_attr__('Enter new key to replace', 'wp-ai-chatbot')
							: esc_attr__('Enter API key', 'wp-ai-chatbot'); ?>"
						autocomplete="new-password"
						class="regular-text"
					/>
				</div>
			</div>
			<?php
		endforeach;

		echo '<p class="description">';
		esc_html_e('Enter only the key you wish to update. Leave blank to keep the current value.', 'wp-ai-chatbot');
		echo '</p>';
	}

	/** Render the default model text input. */
	public function render_default_model_field(): void
	{
		$value = (string) get_option('wpaic_provider_default_model', 'gpt-4o-mini');
		?>
		<input
			type="text"
			id="wpaic_provider_default_model"
			name="wpaic_provider_default_model"
			value="<?php echo esc_attr($value); ?>"
			class="regular-text"
			placeholder="gpt-4o-mini"
		/>
		<p class="description">
			<?php esc_html_e('Model slug used when no shortcode attribute specifies a model.', 'wp-ai-chatbot'); ?>
		</p>
		<?php
	}

	/** Render the request timeout field. */
	public function render_timeout_field(): void
	{
		$value = (int) get_option('wpaic_provider_timeout', 30);
		?>
		<input
			type="number"
			id="wpaic_provider_timeout"
			name="wpaic_provider_timeout"
			value="<?php echo esc_attr((string) $value); ?>"
			min="5"
			max="300"
			step="1"
			class="small-text"
		/>
		<p class="description">
			<?php esc_html_e('Seconds before an AI provider request times out (5–300).', 'wp-ai-chatbot'); ?>
		</p>
		<?php
	}
}
