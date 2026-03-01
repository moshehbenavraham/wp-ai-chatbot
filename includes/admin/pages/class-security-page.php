<?php

/**
 * Security & Privacy Admin Page — slug: uwpaic-security
 *
 * Tabs:
 *  - security    : Rate-limiting and moderation global toggles.
 *  - privacy     : Data retention, GDPR, and anonymisation controls.
 *  - moderation  : Keyword blocklist and response message configuration.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Admin\Pages;

use WPAIC\Admin\Admin_Page;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Security_Page
 *
 * Admin controller for Security & Privacy settings.
 *
 * @since 1.0.0
 * @package WPAIC\Admin\Pages
 */
class Security_Page extends Admin_Page
{
	// =========================================================================
	// Admin_Page contract
	// =========================================================================

	/** {@inheritdoc} */
	public function get_slug(): string
	{
		return 'uwpaic-security';
	}

	/** {@inheritdoc} */
	public function get_title(): string
	{
		return __('Security & Privacy', 'wp-ai-chatbot');
	}

	/** {@inheritdoc} */
	public function get_tabs(): array
	{
		return [
			'security' => __('Security', 'wp-ai-chatbot'),
			'privacy' => __('Privacy', 'wp-ai-chatbot'),
			'moderation' => __('Moderation', 'wp-ai-chatbot'),
		];
	}

	// =========================================================================
	// Settings registration
	// =========================================================================

	/** {@inheritdoc} */
	public function register_settings(): void
	{
		$this->register_security_settings();
		$this->register_privacy_settings();
		$this->register_moderation_settings();
	}

	/**
	 * Register security settings fields (rate limiting, auth).
	 *
	 * @return void
	 */
	private function register_security_settings(): void
	{
		register_setting(
			'wpaic_security_group',
			'wpaic_security_rate_limit_enabled',
			['sanitize_callback' => 'absint', 'default' => 1]
		);

		register_setting(
			'wpaic_security_group',
			'wpaic_security_rate_limit_max',
			[
				'sanitize_callback' => static function ($v): int {
					return max(1, min(500, (int) $v));
				},
				'default' => 20,
			]
		);

		register_setting(
			'wpaic_security_group',
			'wpaic_security_rate_limit_window',
			[
				'sanitize_callback' => static function ($v): int {
					return max(30, min(3600, (int) $v));
				},
				'default' => 60,
			]
		);

		register_setting(
			'wpaic_security_group',
			'wpaic_security_moderation_enabled',
			['sanitize_callback' => 'absint', 'default' => 0]
		);

		// Rate limit message — shown to users when they hit the rate limit.
		register_setting(
			'wpaic_security_group',
			'wpaic_rate_limit_message',
			[
				'sanitize_callback' => 'sanitize_text_field',
				'default' => "I've reached my message limit. Please try again shortly or contact us at [email].",
			]
		);

		// Rate limit fallback contact options.
		register_setting(
			'wpaic_security_group',
			'wpaic_rate_limit_fallback_email',
			['sanitize_callback' => 'sanitize_email', 'default' => '']
		);

		register_setting(
			'wpaic_security_group',
			'wpaic_rate_limit_fallback_phone',
			['sanitize_callback' => 'sanitize_text_field', 'default' => '']
		);

		register_setting(
			'wpaic_security_group',
			'wpaic_rate_limit_fallback_url',
			['sanitize_callback' => 'esc_url_raw', 'default' => '']
		);

		add_settings_section(
			'wpaic_security_main',
			__('Rate Limiting', 'wp-ai-chatbot'),
			'__return_false',
			$this->get_slug()
		);

		add_settings_field(
			'wpaic_security_rate_limit_enabled',
			__('Enable Rate Limiting', 'wp-ai-chatbot'),
			[$this, 'field_rate_limit_enabled'],
			$this->get_slug(),
			'wpaic_security_main'
		);

		add_settings_field(
			'wpaic_security_rate_limit_max',
			__('Max Requests', 'wp-ai-chatbot'),
			[$this, 'field_rate_limit_max'],
			$this->get_slug(),
			'wpaic_security_main'
		);

		add_settings_field(
			'wpaic_security_rate_limit_window',
			__('Window (seconds)', 'wp-ai-chatbot'),
			[$this, 'field_rate_limit_window'],
			$this->get_slug(),
			'wpaic_security_main'
		);

		add_settings_section(
			'wpaic_security_moderation_section',
			__('Moderation', 'wp-ai-chatbot'),
			'__return_false',
			$this->get_slug()
		);

		add_settings_field(
			'wpaic_security_moderation_enabled',
			__('Enable Moderation', 'wp-ai-chatbot'),
			[$this, 'field_moderation_enabled'],
			$this->get_slug(),
			'wpaic_security_moderation_section'
		);

		add_settings_section(
			'wpaic_rate_limit_messages_section',
			__('Rate Limit Messages & Fallback', 'wp-ai-chatbot'),
			static function (): void {
				echo '<p class="description">'
					. esc_html__('Configure the message shown when a user hits the rate limit, and optional fallback contact options.', 'wp-ai-chatbot')
					. '</p>';
			},
			$this->get_slug()
		);

		add_settings_field(
			'wpaic_rate_limit_message',
			__('Rate Limit Message', 'wp-ai-chatbot'),
			[$this, 'field_rate_limit_message'],
			$this->get_slug(),
			'wpaic_rate_limit_messages_section'
		);

		add_settings_field(
			'wpaic_rate_limit_fallback_email',
			__('Fallback Email', 'wp-ai-chatbot'),
			[$this, 'field_rate_limit_fallback_email'],
			$this->get_slug(),
			'wpaic_rate_limit_messages_section'
		);

		add_settings_field(
			'wpaic_rate_limit_fallback_phone',
			__('Fallback Phone', 'wp-ai-chatbot'),
			[$this, 'field_rate_limit_fallback_phone'],
			$this->get_slug(),
			'wpaic_rate_limit_messages_section'
		);

		add_settings_field(
			'wpaic_rate_limit_fallback_url',
			__('Fallback URL (live chat / contact page)', 'wp-ai-chatbot'),
			[$this, 'field_rate_limit_fallback_url'],
			$this->get_slug(),
			'wpaic_rate_limit_messages_section'
		);
	}

	/**
	 * Register privacy settings fields (retention, GDPR).
	 *
	 * @return void
	 */
	private function register_privacy_settings(): void
	{
		register_setting(
			'wpaic_privacy_group',
			'wpaic_data_retention_days',
			[
				'sanitize_callback' => static function ($v): int {
					return max(0, (int) $v);
				},
				'default' => 90,
			]
		);

		register_setting(
			'wpaic_privacy_group',
			'wpaic_privacy_anonymize_logs',
			['sanitize_callback' => 'absint', 'default' => 0]
		);

		add_settings_section(
			'wpaic_privacy_main',
			__('Data Retention', 'wp-ai-chatbot'),
			static function (): void {
				echo '<p class="description">'
					. esc_html__('Chat transcripts are retained for 90 days and automatically deleted. Anonymized usage statistics may be retained indefinitely.', 'wp-ai-chatbot')
					. '</p>';
			},
			$this->get_slug()
		);

		add_settings_field(
			'wpaic_data_retention_days',
			__('Retain Chat Sessions For (days)', 'wp-ai-chatbot'),
			[$this, 'field_retention_days'],
			$this->get_slug(),
			'wpaic_privacy_main'
		);

		add_settings_field(
			'wpaic_privacy_anonymize_logs',
			__('Anonymise IP in Audit Logs', 'wp-ai-chatbot'),
			[$this, 'field_anonymize_logs'],
			$this->get_slug(),
			'wpaic_privacy_main'
		);
	}

	/**
	 * Register moderation settings fields (blocklist).
	 *
	 * @return void
	 */
	private function register_moderation_settings(): void
	{
		register_setting(
			'wpaic_moderation_group',
			\WPAIC\Security\Content_Moderator::SETTINGS_OPTION,
			[
				'sanitize_callback' => class_exists('WPAIC\Security\Content_Moderator')
					? ['WPAIC\Security\Content_Moderator', 'sanitize_settings']
					: 'sanitize_textarea_field',
				'default' => ['enabled' => false, 'blocklist' => '', 'response_message' => ''],
			]
		);

		add_settings_section(
			'wpaic_moderation_main',
			__('Keyword Blocklist', 'wp-ai-chatbot'),
			static function (): void {
				echo '<p class="description">' . esc_html__('Messages matching any of these terms will be blocked before they reach the AI model. One term per line.', 'wp-ai-chatbot') . '</p>';
			},
			$this->get_slug()
		);

		add_settings_field(
			'wpaic_moderation_settings',
			__('Blocked Terms', 'wp-ai-chatbot'),
			[$this, 'field_moderation_settings'],
			$this->get_slug(),
			'wpaic_moderation_main'
		);
	}

	// =========================================================================
	// Field renderers
	// =========================================================================

	/** @internal */
	public function field_rate_limit_enabled(): void
	{
		$val = get_option('wpaic_security_rate_limit_enabled', '1');
		?>
		<input type="checkbox" name="wpaic_security_rate_limit_enabled" value="1" <?php checked('1', $val); ?>>
		<span class="description"><?php esc_html_e('Limit chat requests per IP address.', 'wp-ai-chatbot'); ?></span>
		<?php
	}

	/** @internal */
	public function field_rate_limit_max(): void
	{
		$val = (int) get_option('wpaic_security_rate_limit_max', 20);
		?>
		<input type="number" name="wpaic_security_rate_limit_max" value="<?php echo esc_attr($val); ?>" min="1" max="500" class="small-text">
		<span class="description"><?php esc_html_e('Maximum chat requests allowed within the window.', 'wp-ai-chatbot'); ?></span>
		<?php
	}

	/** @internal */
	public function field_rate_limit_window(): void
	{
		$val = (int) get_option('wpaic_security_rate_limit_window', 60);
		?>
		<input type="number" name="wpaic_security_rate_limit_window" value="<?php echo esc_attr($val); ?>" min="30" max="3600" class="small-text">
		<span class="description"><?php esc_html_e('Rolling window duration in seconds.', 'wp-ai-chatbot'); ?></span>
		<?php
	}

	/** @internal */
	public function field_moderation_enabled(): void
	{
		$val = get_option('wpaic_security_moderation_enabled', '0');
		?>
		<input type="checkbox" name="wpaic_security_moderation_enabled" value="1" <?php checked('1', $val); ?>>
		<span class="description"><?php esc_html_e('Enable keyword-based message moderation. Configure terms in the Moderation tab.', 'wp-ai-chatbot'); ?></span>
		<?php
	}

	/** @internal */
	public function field_retention_days(): void
	{
		$val = (int) get_option('wpaic_data_retention_days', 90);
		?>
		<input type="number" name="wpaic_data_retention_days" value="<?php echo esc_attr($val); ?>" min="0" max="3650" class="small-text">
		<span class="description">
			<?php esc_html_e('Sessions older than this are purged daily. Set to 0 to retain indefinitely.', 'wp-ai-chatbot'); ?>
		</span>
		<?php
	}

	/** @internal */
	public function field_anonymize_logs(): void
	{
		$val = get_option('wpaic_privacy_anonymize_logs', '0');
		?>
		<input type="checkbox" name="wpaic_privacy_anonymize_logs" value="1" <?php checked('1', $val); ?>>
		<span class="description"><?php esc_html_e('Truncate IP addresses in audit logs to the first three octets.', 'wp-ai-chatbot'); ?></span>
		<?php
	}

	/** @internal */
	public function field_rate_limit_message(): void
	{
		$default = "I've reached my message limit. Please try again shortly or contact us at [email].";
		$val = (string) get_option('wpaic_rate_limit_message', $default);
		?>
		<input type="text" name="wpaic_rate_limit_message" value="<?php echo esc_attr($val); ?>" class="large-text" placeholder="<?php echo esc_attr($default); ?>">
		<p class="description"><?php esc_html_e('Use [email] as a placeholder for the fallback email address.', 'wp-ai-chatbot'); ?></p>
		<?php
	}

	/** @internal */
	public function field_rate_limit_fallback_email(): void
	{
		$val = (string) get_option('wpaic_rate_limit_fallback_email', '');
		?>
		<input type="email" name="wpaic_rate_limit_fallback_email" value="<?php echo esc_attr($val); ?>" class="regular-text" placeholder="support@example.com">
		<p class="description"><?php esc_html_e('Replaces [email] in the rate limit message. Shown as a mailto: link in the widget.', 'wp-ai-chatbot'); ?></p>
		<?php
	}

	/** @internal */
	public function field_rate_limit_fallback_phone(): void
	{
		$val = (string) get_option('wpaic_rate_limit_fallback_phone', '');
		?>
		<input type="text" name="wpaic_rate_limit_fallback_phone" value="<?php echo esc_attr($val); ?>" class="regular-text" placeholder="+1-555-000-0000">
		<p class="description"><?php esc_html_e('Optional. Displayed as a tel: link alongside the rate limit message.', 'wp-ai-chatbot'); ?></p>
		<?php
	}

	/** @internal */
	public function field_rate_limit_fallback_url(): void
	{
		$val = (string) get_option('wpaic_rate_limit_fallback_url', '');
		?>
		<input type="url" name="wpaic_rate_limit_fallback_url" value="<?php echo esc_attr($val); ?>" class="regular-text" placeholder="https://example.com/contact">
		<p class="description"><?php esc_html_e('Optional. Shown as a "Contact us" link (live chat or contact page).', 'wp-ai-chatbot'); ?></p>
		<?php
	}

	/** @internal */
	public function field_moderation_settings(): void
	{
		$settings = class_exists('WPAIC\Security\Content_Moderator')
			? \WPAIC\Security\Content_Moderator::get_settings()
			: ['enabled' => false, 'blocklist' => '', 'response_message' => ''];

		$option_name = class_exists('WPAIC\Security\Content_Moderator')
			? \WPAIC\Security\Content_Moderator::SETTINGS_OPTION
			: 'wpaic_moderation_settings';
		?>
		<fieldset>
			<label style="display:block;margin-bottom:8px">
				<input type="checkbox" name="<?php echo esc_attr($option_name); ?>[enabled]" value="1" <?php checked(true, (bool) $settings['enabled']); ?>>
				<?php esc_html_e('Enable blocklist moderation', 'wp-ai-chatbot'); ?>
			</label>
			<label>
				<strong><?php esc_html_e('Blocked terms (one per line):', 'wp-ai-chatbot'); ?></strong><br>
				<textarea
					name="<?php echo esc_attr($option_name); ?>[blocklist]"
					rows="8"
					cols="50"
					class="large-text code"
				><?php echo esc_textarea($settings['blocklist']); ?></textarea>
			</label>
			<br>
			<label>
				<strong><?php esc_html_e('Response message when blocked (leave blank for default):', 'wp-ai-chatbot'); ?></strong><br>
				<input
					type="text"
					name="<?php echo esc_attr($option_name); ?>[response_message]"
					value="<?php echo esc_attr($settings['response_message']); ?>"
					class="regular-text"
					placeholder="<?php esc_attr_e("I'm sorry, I can't help with that topic.", 'wp-ai-chatbot'); ?>"
				>
			</label>
		</fieldset>
		<?php
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

		$tab = $this->get_active_tab();
		$this->render_page_header($tab);

		switch ($tab) {
			case 'privacy':
				$this->render_privacy_tab();
				break;
			case 'moderation':
				$this->render_moderation_tab();
				break;
			default:
				$this->render_security_tab();
				break;
		}

		$this->render_page_footer();
	}

	/**
	 * Render the Security tab.
	 *
	 * @return void
	 */
	private function render_security_tab(): void
	{
		?>
		<div class="wpaic-tab-content">
			<form method="post" action="options.php" novalidate>
				<?php
				settings_fields('wpaic_security_group');
		do_settings_sections($this->get_slug());
		submit_button();
		?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the Privacy tab.
	 *
	 * @return void
	 */
	private function render_privacy_tab(): void
	{
		?>
		<div class="wpaic-tab-content">
			<form method="post" action="options.php" novalidate>
				<?php
				settings_fields('wpaic_privacy_group');
		do_settings_sections($this->get_slug());
		submit_button();
		?>
			</form>

			<hr>
			<h2><?php esc_html_e('Personal Data Tools', 'wp-ai-chatbot'); ?></h2>
			<p class="description">
				<?php
		printf(
			/* translators: %s: link to WP privacy tools */
			esc_html__('Use the built-in %s to export or erase user chat sessions and lead capture records.', 'wp-ai-chatbot'),
			'<a href="' . esc_url(admin_url('tools.php?page=export-personal-data')) . '">' . esc_html__('Personal Data tools', 'wp-ai-chatbot') . '</a>'
		);
		?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the Moderation tab.
	 *
	 * @return void
	 */
	private function render_moderation_tab(): void
	{
		?>
		<div class="wpaic-tab-content">
			<form method="post" action="options.php" novalidate>
				<?php
				settings_fields('wpaic_moderation_group');
		do_settings_sections($this->get_slug());
		submit_button();
		?>
			</form>
		</div>
		<?php
	}
}
