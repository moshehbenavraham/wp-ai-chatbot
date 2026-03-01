<?php

/**
 * Abstract base class for all plugin admin page controllers.
 *
 * Concrete page controllers extend this class and implement the four abstract
 * methods. The Admin_Router instantiates and dispatches to registered controllers.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Admin;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Abstract class Admin_Page
 *
 * Base contract for per-page admin controllers (< 600 LOC cap per controller).
 * Follows the AxiaChat per-section decomposition pattern (CFI-ADM-001).
 *
 * @since 1.0.0
 * @package WPAIC\Admin
 */
abstract class Admin_Page
{
	/**
	 * Return the WP admin page slug this controller handles.
	 *
	 * @return string Page slug (e.g., 'uwpaic', 'uwpaic-providers').
	 */
	abstract public function get_slug(): string;

	/**
	 * Return the page title used in <title> and the <h1> heading.
	 *
	 * @return string Localised page title.
	 */
	abstract public function get_title(): string;

	/**
	 * Register WP Settings API settings, sections, and fields for this page.
	 * Called on the `admin_init` action (via Admin_Router).
	 *
	 * @return void
	 */
	abstract public function register_settings(): void;

	/**
	 * Render the full page HTML.
	 * Called by the WP menu page callback (via Admin_Router::dispatch()).
	 *
	 * @return void
	 */
	abstract public function render(): void;

	/**
	 * Return an ordered associative array of tabs for tabbed pages.
	 * Return an empty array for single-page (no-tab) controllers.
	 *
	 * @return array<string, string> ['tab_slug' => 'Tab Label']
	 */
	public function get_tabs(): array
	{
		return [];
	}

	/**
	 * Enqueue page-specific assets.
	 * Called by Admin_Router::enqueue_assets() when the current page matches.
	 *
	 * Override in concrete controllers to register page-specific CSS/JS.
	 *
	 * @return void
	 */
	public function enqueue_page_assets(): void
	{
	}

	// =========================================================================
	// Shared rendering helpers available to concrete controllers
	// =========================================================================

	/**
	 * Render the standard plugin page wrapper opening tag, title, and tab nav.
	 *
	 * @param  string $active_tab Currently active tab slug (empty for no tabs).
	 * @return void
	 */
	protected function render_page_header(string $active_tab = ''): void
	{
		$tabs = $this->get_tabs();
		?>
		<div class="wrap wpaic-admin-page">
			<h1><?php echo esc_html($this->get_title()); ?></h1>
			<?php if (! empty($tabs)) : ?>
				<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('Settings tabs', 'wp-ai-chatbot'); ?>">
					<?php foreach ($tabs as $slug => $label) : ?>
						<a
							href="<?php echo esc_url(add_query_arg('tab', $slug)); ?>"
							class="nav-tab<?php echo $active_tab === $slug ? ' nav-tab-active' : ''; ?>"
							<?php if ($active_tab === $slug) : ?>aria-current="page"<?php endif; ?>
						>
							<?php echo esc_html($label); ?>
						</a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
			<main id="wpaic-main-content" aria-label="<?php esc_attr_e('Settings content', 'wp-ai-chatbot'); ?>">
		<?php
	}

	/**
	 * Render the standard plugin page wrapper closing tag.
	 *
	 * @return void
	 */
	protected function render_page_footer(): void
	{
		echo '</main><!-- #wpaic-main-content -->' . "\n" . '</div><!-- .wpaic-admin-page -->';
	}

	/**
	 * Return the currently active tab slug for this page.
	 * Defaults to the first tab when the query arg is missing or invalid.
	 *
	 * @return string Active tab slug, or empty string when no tabs defined.
	 */
	protected function get_active_tab(): string
	{
		$tabs = $this->get_tabs();

		if (empty($tabs)) {
			return '';
		}

		$first_tab = (string) array_key_first($tabs);
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$requested = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : $first_tab;

		return array_key_exists($requested, $tabs) ? $requested : $first_tab;
	}

	/**
	 * Output a standard WP Settings form for the current page.
	 *
	 * @param  string $option_group WP Settings API option group.
	 * @param  string $section_id  Settings section to render fields for.
	 * @return void
	 */
	protected function render_settings_form(string $option_group, string $section_id = ''): void
	{
		?>
		<form method="post" action="options.php" novalidate>
			<?php
			settings_fields($option_group);
		do_settings_sections($this->get_slug());
		submit_button();
		?>
		</form>
		<?php
	}
}
