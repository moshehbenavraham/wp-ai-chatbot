<?php

/**
 * Chatbot Widget Template
 *
 * Renders the floating launcher + chat panel, or inline embed when called
 * from the [wpaic_chatbot] shortcode with mode="inline".
 *
 * Variables available when called from Plugin::render_chatbot_shortcode():
 *   $atts  — shortcode attributes (merged with defaults).
 *
 * Variables available from Plugin::on_frontend_footer():
 *   (none — uses defaults from options).
 *
 * Security: all output is escaped. User-visible strings are translatable.
 * The honeypot field is intentionally invisible via CSS; aria-hidden hides it
 * from assistive technologies so screen readers aren't confused.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

if (! defined('ABSPATH')) {
	exit;
}

// Merge shortcode atts with option-driven defaults.
$defaults = [
	'bot_slug' => 'default',
	'mode' => 'floating',  // 'floating' | 'inline'
	'title' => get_option('wpaic_widget_title', __('AI Assistant', 'wp-ai-chatbot')),
	'placeholder' => get_option('wpaic_widget_placeholder', __('Type a message…', 'wp-ai-chatbot')),
	'gdpr' => get_option('wpaic_gdpr_enabled', '0'),
	'gdpr_message' => get_option(
		'wpaic_gdpr_message',
		__('By chatting you agree to our privacy policy.', 'wp-ai-chatbot')
	),
	'gdpr_policy_url' => get_option('wpaic_gdpr_policy_url', get_privacy_policy_url()),
];

$atts = isset($atts) && is_array($atts) ? array_merge($defaults, $atts) : $defaults;

// Sanitize all atts.
$bot_slug = sanitize_key($atts['bot_slug']);
$mode = in_array($atts['mode'], ['floating', 'inline'], true) ? $atts['mode'] : 'floating';
$title = esc_html($atts['title']);
$placeholder = esc_attr($atts['placeholder']);
$gdpr_enabled = ! empty($atts['gdpr']) && '0' !== $atts['gdpr'];
$gdpr_message = wp_kses(
	$atts['gdpr_message'],
	['a' => ['href' => true, 'target' => true, 'rel' => true]]
);
$gdpr_policy_url = esc_url($atts['gdpr_policy_url']);

// Generate unique instance ID for this widget (allows multiple per page).
static $instance_counter = 0;
$instance_counter++;
$instance_id = 'wpaic-' . $instance_counter;

// Inline SVG icons (no external requests).
$icon_chat = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>';
$icon_close = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true" focusable="false"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
$icon_bot = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2a2 2 0 1 1 0 4 2 2 0 0 1 0-4zm0 6c3.31 0 6 2.69 6 6v1H6v-1c0-3.31 2.69-6 6-6zM4 21h16v-2H4v2z"/></svg>';
$icon_send = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 21l21-9L2 3v7l15 2-15 2z"/></svg>';
?>
<div
	class="wpaic-widget"
	data-wpaic-instance="<?php echo esc_attr($instance_id); ?>"
	data-bot-slug="<?php echo esc_attr($bot_slug); ?>"
	data-mode="<?php echo esc_attr($mode); ?>"
	role="region"
	aria-label="<?php esc_attr_e('AI Chat', 'wp-ai-chatbot'); ?>"
>
	<?php if ('floating' === $mode) : ?>
	<button
		class="wpaic-launcher"
		type="button"
		aria-label="<?php esc_attr_e('Open chat', 'wp-ai-chatbot'); ?>"
		aria-expanded="false"
		aria-controls="<?php echo esc_attr($instance_id . '-panel'); ?>"
	>
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $icon_chat;
		?>
	</button>
	<?php endif; ?>

	<div
		class="wpaic-panel"
		id="<?php echo esc_attr($instance_id . '-panel'); ?>"
		role="dialog"
		aria-label="<?php echo esc_attr($title); ?>"
		aria-hidden="true"
		aria-live="polite"
	>
		<!-- Panel header -->
		<header class="wpaic-header">
			<div class="wpaic-header__avatar" aria-hidden="true">
				<?php
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $icon_bot;
?>
			</div>
			<div class="wpaic-header__info">
				<div class="wpaic-header__title"><?php echo esc_html($title); ?></div>
				<div class="wpaic-header__status">
					<?php esc_html_e('Online', 'wp-ai-chatbot'); ?>
				</div>
			</div>
			<?php if ('floating' === $mode) : ?>
			<button
				class="wpaic-close-btn"
				type="button"
				aria-label="<?php esc_attr_e('Close chat', 'wp-ai-chatbot'); ?>"
			>
				<?php
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
echo $icon_close;
				?>
			</button>
			<?php endif; ?>
		</header>

		<!-- Message transcript -->
		<div class="wpaic-transcript" role="log" aria-label="<?php esc_attr_e('Chat messages', 'wp-ai-chatbot'); ?>" aria-live="polite"></div>

		<!-- Suggestion chips container (populated by JS) -->
		<div class="wpaic-suggestions" aria-label="<?php esc_attr_e('Suggested questions', 'wp-ai-chatbot'); ?>"></div>

		<?php if ($gdpr_enabled) : ?>
		<!-- GDPR consent banner -->
		<div class="wpaic-consent" role="alert">
			<p class="wpaic-consent__text">
				<?php
				if ($gdpr_policy_url) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo str_replace(
						'%s',
						'<a href="' . esc_url($gdpr_policy_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('privacy policy', 'wp-ai-chatbot') . '</a>',
						// translators: %s is a link to the privacy policy.
						$gdpr_message ?: __('By chatting you agree to our %s.', 'wp-ai-chatbot')
					);
				} else {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo $gdpr_message;
				}
?>
			</p>
			<button class="wpaic-consent__accept" type="button">
				<?php esc_html_e('I Agree', 'wp-ai-chatbot'); ?>
			</button>
		</div>
		<?php endif; ?>

		<!-- Input composer -->
		<div class="wpaic-composer">
			<form class="wpaic-form" novalidate>
				<!-- Honeypot field — hidden from users and screen readers; bots fill it -->
				<div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">
					<label for="<?php echo esc_attr($instance_id . '-hp'); ?>">
						<?php esc_html_e('Leave this field empty', 'wp-ai-chatbot'); ?>
					</label>
					<input
						type="text"
						id="<?php echo esc_attr($instance_id . '-hp'); ?>"
						name="wpaic_hp"
						tabindex="-1"
						autocomplete="off"
					>
				</div>

				<div class="wpaic-input-wrapper">
					<textarea
						class="wpaic-input"
						name="message"
						rows="1"
						placeholder="<?php echo $placeholder; ?>"
						aria-label="<?php esc_attr_e('Your message', 'wp-ai-chatbot'); ?>"
						autocomplete="off"
						maxlength="4000"
					></textarea>
				</div>

				<button
					class="wpaic-send-btn"
					type="submit"
					aria-label="<?php esc_attr_e('Send message', 'wp-ai-chatbot'); ?>"
				>
					<?php
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo $icon_send;
?>
				</button>
			</form>

			<p class="wpaic-powered-by">
				<?php
				printf(
					// translators: 1: link open, 2: link close.
					esc_html__('Powered by %1$sWP AI Chatbot%2$s', 'wp-ai-chatbot'),
					'<a href="https://wordpress.org" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);
?>
			</p>
		</div>
	</div>
</div>
