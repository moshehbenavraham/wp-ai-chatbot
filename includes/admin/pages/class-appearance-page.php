<?php

/**
 * Appearance Settings page controller.
 *
 * Manages the visual design system settings: preset color palettes,
 * custom token overrides, widget position, font loading, and branding.
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
 * Class Appearance_Page
 *
 * Appearance / Design System: palette picker, custom hex tokens, widget display.
 *
 * @since 1.0.0
 * @package WPAIC\Admin\Pages
 */
class Appearance_Page extends Admin_Page
{
	private const OPTION_GROUP_PALETTE = 'wpaic_appearance_palette_group';
	private const OPTION_GROUP_DISPLAY = 'wpaic_appearance_display_group';

	// =========================================================================
	// Palette registry — 8 preset palettes
	// =========================================================================

	/**
	 * Return all preset color palettes.
	 *
	 * Each palette defines the five tokens that drive the widget's color scheme.
	 * Keyed by machine-readable slug for easy storage and retrieval.
	 *
	 * @return array<string, array{label:string, bg_deep:string, bg_card:string, bg_input:string, accent:string, accent_hover:string}>
	 */
	public static function get_palettes(): array
	{
		return [
			'dark-teal' => [
				'label' => 'Dark Teal (Default)',
				'bg_deep' => '#0F172A',
				'bg_card' => '#1F2937',
				'bg_input' => '#111827',
				'accent' => '#22D3EE',
				'accent_hover' => '#06B6D4',
			],
			'dark-violet' => [
				'label' => 'Dark Violet',
				'bg_deep' => '#0D0B1E',
				'bg_card' => '#1C1833',
				'bg_input' => '#120F26',
				'accent' => '#A855F7',
				'accent_hover' => '#9333EA',
			],
			'dark-emerald' => [
				'label' => 'Dark Emerald',
				'bg_deep' => '#071512',
				'bg_card' => '#0F2922',
				'bg_input' => '#0A1B17',
				'accent' => '#10B981',
				'accent_hover' => '#059669',
			],
			'dark-rose' => [
				'label' => 'Dark Rose',
				'bg_deep' => '#16080D',
				'bg_card' => '#2D1521',
				'bg_input' => '#1A0A12',
				'accent' => '#F43F5E',
				'accent_hover' => '#E11D48',
			],
			'dark-amber' => [
				'label' => 'Dark Amber',
				'bg_deep' => '#15100A',
				'bg_card' => '#291E0E',
				'bg_input' => '#1A140C',
				'accent' => '#F59E0B',
				'accent_hover' => '#D97706',
			],
			'dark-indigo' => [
				'label' => 'Dark Indigo',
				'bg_deep' => '#0C0C1F',
				'bg_card' => '#1A1A35',
				'bg_input' => '#0F0F27',
				'accent' => '#6366F1',
				'accent_hover' => '#4F46E5',
			],
			'midnight-blue' => [
				'label' => 'Midnight Blue',
				'bg_deep' => '#0A1628',
				'bg_card' => '#162440',
				'bg_input' => '#0D1C33',
				'accent' => '#3B82F6',
				'accent_hover' => '#2563EB',
			],
			'carbon-orange' => [
				'label' => 'Carbon Orange',
				'bg_deep' => '#141210',
				'bg_card' => '#252220',
				'bg_input' => '#1A1816',
				'accent' => '#EA580C',
				'accent_hover' => '#C2410C',
			],
		];
	}

	/**
	 * Resolve the active palette tokens for frontend output.
	 *
	 * Returns a preset palette's tokens, or the site-specific custom overrides
	 * when the palette option is set to 'custom'.
	 *
	 * @return array{bg_deep:string, bg_card:string, bg_input:string, accent:string, accent_hover:string}
	 */
	public static function get_active_tokens(): array
	{
		$slug = (string) get_option('wpaic_appearance_palette', 'dark-teal');
		$palettes = self::get_palettes();

		if (array_key_exists($slug, $palettes)) {
			return $palettes[$slug];
		}

		// 'custom' palette: read individual token options with dark-teal fallbacks.
		$defaults = $palettes['dark-teal'];

		return [
			'bg_deep' => (string) get_option('wpaic_appearance_custom_bg_deep', $defaults['bg_deep']),
			'bg_card' => (string) get_option('wpaic_appearance_custom_bg_card', $defaults['bg_card']),
			'bg_input' => (string) get_option('wpaic_appearance_custom_bg_input', $defaults['bg_input']),
			'accent' => (string) get_option('wpaic_appearance_custom_accent', $defaults['accent']),
			'accent_hover' => (string) get_option('wpaic_appearance_custom_accent_hover', $defaults['accent_hover']),
		];
	}

	// =========================================================================
	// Admin_Page abstract contract
	// =========================================================================

	/** @return string */
	public function get_slug(): string
	{
		return 'uwpaic-appearance';
	}

	/** @return string */
	public function get_title(): string
	{
		return __('WP AI Chatbot — Appearance', 'wp-ai-chatbot');
	}

	/** @return array<string,string> */
	public function get_tabs(): array
	{
		return [
			'palette' => __('Color Palette', 'wp-ai-chatbot'),
			'display' => __('Display', 'wp-ai-chatbot'),
		];
	}

	// =========================================================================
	// Settings registration
	// =========================================================================

	/** {@inheritdoc} */
	public function register_settings(): void
	{
		$this->register_palette_settings();
		$this->register_display_settings();
	}

	/** Register palette tab settings. */
	private function register_palette_settings(): void
	{
		$valid_slugs = array_keys(self::get_palettes());
		$valid_slugs[] = 'custom';

		register_setting(self::OPTION_GROUP_PALETTE, 'wpaic_appearance_palette', [
			'type' => 'string',
			'sanitize_callback' => static function (string $value) use ($valid_slugs): string {
				return in_array($value, $valid_slugs, true) ? $value : 'dark-teal';
			},
			'default' => 'dark-teal',
		]);

		// Custom token overrides (each validated as a hex colour).
		foreach (['bg_deep', 'bg_card', 'bg_input', 'accent', 'accent_hover'] as $token) {
			register_setting(self::OPTION_GROUP_PALETTE, "wpaic_appearance_custom_{$token}", [
				'type' => 'string',
				'sanitize_callback' => 'sanitize_hex_color',
				'default' => '',
			]);
		}

		add_settings_section(
			'wpaic_appearance_palette_section',
			__('Preset Palettes', 'wp-ai-chatbot'),
			[$this, 'render_palette_section_description'],
			'uwpaic-appearance-palette'
		);

		add_settings_field(
			'wpaic_appearance_palette',
			__('Color Palette', 'wp-ai-chatbot'),
			[$this, 'render_palette_picker_field'],
			'uwpaic-appearance-palette',
			'wpaic_appearance_palette_section'
		);
	}

	/** Register display tab settings. */
	private function register_display_settings(): void
	{
		$valid_positions = ['bottom-right', 'bottom-left', 'top-right', 'top-left'];

		register_setting(self::OPTION_GROUP_DISPLAY, 'wpaic_appearance_widget_position', [
			'type' => 'string',
			'sanitize_callback' => static function (string $value) use ($valid_positions): string {
				return in_array($value, $valid_positions, true) ? $value : 'bottom-right';
			},
			'default' => 'bottom-right',
		]);

		register_setting(self::OPTION_GROUP_DISPLAY, 'wpaic_appearance_show_powered_by', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 1,
		]);

		register_setting(self::OPTION_GROUP_DISPLAY, 'wpaic_appearance_load_fonts', [
			'type' => 'integer',
			'sanitize_callback' => 'absint',
			'default' => 0,
		]);

		add_settings_section(
			'wpaic_appearance_display_section',
			__('Widget Display', 'wp-ai-chatbot'),
			[$this, 'render_display_section_description'],
			'uwpaic-appearance-display'
		);

		add_settings_field(
			'wpaic_appearance_widget_position',
			__('Widget Position', 'wp-ai-chatbot'),
			[$this, 'render_position_field'],
			'uwpaic-appearance-display',
			'wpaic_appearance_display_section'
		);

		add_settings_field(
			'wpaic_appearance_show_powered_by',
			__('Branding', 'wp-ai-chatbot'),
			[$this, 'render_powered_by_field'],
			'uwpaic-appearance-display',
			'wpaic_appearance_display_section'
		);

		add_settings_field(
			'wpaic_appearance_load_fonts',
			__('Web Fonts', 'wp-ai-chatbot'),
			[$this, 'render_load_fonts_field'],
			'uwpaic-appearance-display',
			'wpaic_appearance_display_section'
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

		$active_tab = $this->get_active_tab();
		$this->render_page_header($active_tab);
		?>
		<form method="post" action="options.php" novalidate>
			<?php
			if ('palette' === $active_tab) {
				settings_fields(self::OPTION_GROUP_PALETTE);
				do_settings_sections('uwpaic-appearance-palette');
			} else {
				settings_fields(self::OPTION_GROUP_DISPLAY);
				do_settings_sections('uwpaic-appearance-display');
			}
		submit_button(__('Save Settings', 'wp-ai-chatbot'));
		?>
		</form>
		<?php
		$this->render_page_footer();
	}

	// =========================================================================
	// Section descriptions
	// =========================================================================

	/** Render the palette section description. */
	public function render_palette_section_description(): void
	{
		echo '<p>';
		esc_html_e(
			'Choose a preset color palette for your chat widget. Select "Custom" to fine-tune individual colors.',
			'wp-ai-chatbot'
		);
		echo '</p>';
	}

	/** Render the display section description. */
	public function render_display_section_description(): void
	{
		echo '<p>';
		esc_html_e(
			'Control where the floating widget appears, whether to show the "Powered by" footer, and font loading.',
			'wp-ai-chatbot'
		);
		echo '</p>';
	}

	// =========================================================================
	// Field renderers
	// =========================================================================

	/** Render the visual palette picker with swatches + custom token inputs. */
	public function render_palette_picker_field(): void
	{
		$palettes = self::get_palettes();
		$current = (string) get_option('wpaic_appearance_palette', 'dark-teal');
		$defaults = $palettes['dark-teal'];

		// Custom token current values.
		$custom = [
			'bg_deep' => (string) get_option('wpaic_appearance_custom_bg_deep', $defaults['bg_deep']),
			'bg_card' => (string) get_option('wpaic_appearance_custom_bg_card', $defaults['bg_card']),
			'bg_input' => (string) get_option('wpaic_appearance_custom_bg_input', $defaults['bg_input']),
			'accent' => (string) get_option('wpaic_appearance_custom_accent', $defaults['accent']),
			'accent_hover' => (string) get_option('wpaic_appearance_custom_accent_hover', $defaults['accent_hover']),
		];

		$custom_labels = [
			'bg_deep' => __('Background (deep)', 'wp-ai-chatbot'),
			'bg_card' => __('Background (card)', 'wp-ai-chatbot'),
			'bg_input' => __('Background (input)', 'wp-ai-chatbot'),
			'accent' => __('Accent color', 'wp-ai-chatbot'),
			'accent_hover' => __('Accent hover', 'wp-ai-chatbot'),
		];
		?>

		<!-- Hidden radio input stores the selected palette slug -->
		<input
			type="hidden"
			id="wpaic_appearance_palette"
			name="wpaic_appearance_palette"
			value="<?php echo esc_attr($current); ?>"
		/>

		<!-- Preset palette swatches -->
		<div class="wpaic-palette-grid" style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;">
			<?php foreach ($palettes as $slug => $palette) : ?>
				<div
					class="wpaic-palette-swatch<?php echo $current === $slug ? ' wpaic-palette-swatch--active' : ''; ?>"
					data-palette-slug="<?php echo esc_attr($slug); ?>"
					role="radio"
					aria-checked="<?php echo $current === $slug ? 'true' : 'false'; ?>"
					tabindex="0"
					style="
						cursor:pointer;
						border-radius:10px;
						padding:12px;
						width:130px;
						border:2px solid <?php echo $current === $slug ? esc_attr($palette['accent']) : 'transparent'; ?>;
						background:<?php echo esc_attr($palette['bg_deep']); ?>;
						transition:border-color .15s;
					"
				>
					<!-- Accent preview bar -->
					<div style="
						height:6px;
						border-radius:3px;
						background:<?php echo esc_attr($palette['accent']); ?>;
						margin-bottom:8px;
					"></div>
					<!-- Mini "chat bubble" previews -->
					<div style="display:flex;flex-direction:column;gap:4px;margin-bottom:8px;">
						<div style="
							align-self:flex-end;
							height:8px;width:60%;
							border-radius:4px 4px 0 4px;
							background:<?php echo esc_attr($palette['accent']); ?>;
							opacity:.9;
						"></div>
						<div style="
							align-self:flex-start;
							height:8px;width:70%;
							border-radius:0 4px 4px 4px;
							background:<?php echo esc_attr($palette['bg_card']); ?>;
						"></div>
					</div>
					<div style="
						font-size:11px;
						color:<?php echo esc_attr($palette['accent']); ?>;
						font-weight:600;
						white-space:nowrap;
						overflow:hidden;
						text-overflow:ellipsis;
					"><?php echo esc_html($palette['label']); ?></div>
				</div>
			<?php endforeach; ?>

			<!-- Custom palette swatch -->
			<div
				class="wpaic-palette-swatch<?php echo 'custom' === $current ? ' wpaic-palette-swatch--active' : ''; ?>"
				data-palette-slug="custom"
				role="radio"
				aria-checked="<?php echo 'custom' === $current ? 'true' : 'false'; ?>"
				tabindex="0"
				style="
					cursor:pointer;
					border-radius:10px;
					padding:12px;
					width:130px;
					border:2px solid <?php echo 'custom' === $current ? '#9CA3AF' : 'transparent'; ?>;
					background:#111827;
					transition:border-color .15s;
				"
			>
				<div style="
					height:6px;border-radius:3px;
					background:linear-gradient(90deg,#22D3EE,#A855F7,#F59E0B);
					margin-bottom:8px;
				"></div>
				<div style="display:flex;flex-direction:column;gap:4px;margin-bottom:8px;">
					<div style="align-self:flex-end;height:8px;width:60%;border-radius:4px 4px 0 4px;background:#9CA3AF;opacity:.5;"></div>
					<div style="align-self:flex-start;height:8px;width:70%;border-radius:0 4px 4px 4px;background:#1F2937;"></div>
				</div>
				<div style="font-size:11px;color:#9CA3AF;font-weight:600;"><?php esc_html_e('Custom', 'wp-ai-chatbot'); ?></div>
			</div>
		</div>

		<!-- Custom token overrides (shown only when 'custom' is selected) -->
		<div
			id="wpaic-custom-tokens"
			style="<?php echo 'custom' !== $current ? 'display:none;' : ''; ?>margin-top:12px;padding:16px;background:#f9f9f9;border:1px solid #ddd;border-radius:6px;max-width:480px;"
		>
			<strong style="display:block;margin-bottom:12px;">
				<?php esc_html_e('Custom Color Tokens', 'wp-ai-chatbot'); ?>
			</strong>
			<table style="width:100%;border-spacing:0 8px;">
				<?php foreach ($custom as $token => $value) : ?>
					<tr>
						<td style="width:160px;padding-right:12px;">
							<label for="wpaic_appearance_custom_<?php echo esc_attr($token); ?>" style="font-size:13px;">
								<?php echo esc_html($custom_labels[$token]); ?>
							</label>
						</td>
						<td style="display:flex;align-items:center;gap:8px;">
							<input
								type="color"
								id="wpaic_appearance_custom_<?php echo esc_attr($token); ?>_picker"
								value="<?php echo esc_attr($value); ?>"
								style="width:44px;height:32px;padding:2px;border-radius:4px;border:1px solid #ddd;cursor:pointer;"
							/>
							<input
								type="text"
								id="wpaic_appearance_custom_<?php echo esc_attr($token); ?>"
								name="wpaic_appearance_custom_<?php echo esc_attr($token); ?>"
								value="<?php echo esc_attr($value); ?>"
								maxlength="7"
								placeholder="#000000"
								style="width:90px;font-family:monospace;"
								class="small-text"
							/>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		</div>

		<script>
		(function () {
			var swatches = document.querySelectorAll('.wpaic-palette-swatch');
			var hidden   = document.getElementById('wpaic_appearance_palette');
			var customBox = document.getElementById('wpaic-custom-tokens');

			function selectSwatch(el) {
				swatches.forEach(function (s) {
					s.style.borderColor = 'transparent';
					s.setAttribute('aria-checked', 'false');
				});
				var slug = el.getAttribute('data-palette-slug');
				el.style.borderColor = slug === 'custom' ? '#9CA3AF' : '#22D3EE';
				el.setAttribute('aria-checked', 'true');
				hidden.value = slug;
				customBox.style.display = slug === 'custom' ? '' : 'none';
			}

			swatches.forEach(function (swatch) {
				swatch.addEventListener('click', function () { selectSwatch(this); });
				swatch.addEventListener('keydown', function (e) {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						selectSwatch(this);
					}
				});
			});

			// Sync color picker → text input.
			document.querySelectorAll('[id$="_picker"]').forEach(function (picker) {
				var textId = picker.id.replace('_picker', '');
				var text   = document.getElementById(textId);
				if (! text) return;
				picker.addEventListener('input', function () { text.value = picker.value; });
				text.addEventListener('input', function () {
					if (/^#[0-9a-fA-F]{6}$/.test(text.value)) {
						picker.value = text.value;
					}
				});
			});
		}());
		</script>
		<?php
	}

	/** Render the widget position select. */
	public function render_position_field(): void
	{
		$current = (string) get_option('wpaic_appearance_widget_position', 'bottom-right');
		$options = [
			'bottom-right' => __('Bottom right (default)', 'wp-ai-chatbot'),
			'bottom-left' => __('Bottom left', 'wp-ai-chatbot'),
			'top-right' => __('Top right', 'wp-ai-chatbot'),
			'top-left' => __('Top left', 'wp-ai-chatbot'),
		];
		?>
		<select id="wpaic_appearance_widget_position" name="wpaic_appearance_widget_position">
			<?php foreach ($options as $value => $label) : ?>
				<option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>>
					<?php echo esc_html($label); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<p class="description">
			<?php esc_html_e('Corner of the viewport where the floating launcher appears.', 'wp-ai-chatbot'); ?>
		</p>
		<?php
	}

	/** Render the "Powered by" branding toggle. */
	public function render_powered_by_field(): void
	{
		$show = (bool) get_option('wpaic_appearance_show_powered_by', 1);
		?>
		<label>
			<input
				type="checkbox"
				name="wpaic_appearance_show_powered_by"
				id="wpaic_appearance_show_powered_by"
				value="1"
				<?php checked($show); ?>
			/>
			<?php esc_html_e('Show "Powered by WP AI Chatbot" footer link', 'wp-ai-chatbot'); ?>
		</label>
		<p class="description">
			<?php esc_html_e('Uncheck to remove the branding footer from the chat panel.', 'wp-ai-chatbot'); ?>
		</p>
		<?php
	}

	/** Render the web fonts loading toggle. */
	public function render_load_fonts_field(): void
	{
		$enabled = (bool) get_option('wpaic_appearance_load_fonts', 0);
		?>
		<label>
			<input
				type="checkbox"
				name="wpaic_appearance_load_fonts"
				id="wpaic_appearance_load_fonts"
				value="1"
				<?php checked($enabled); ?>
			/>
			<?php esc_html_e('Load Space Grotesk, Inter, and JetBrains Mono from Google Fonts', 'wp-ai-chatbot'); ?>
		</label>
		<p class="description">
			<?php esc_html_e(
				'Adds a Google Fonts CDN request. Disable if you self-host fonts or prefer system fallbacks.',
				'wp-ai-chatbot'
			); ?>
		</p>
		<?php
	}
}
