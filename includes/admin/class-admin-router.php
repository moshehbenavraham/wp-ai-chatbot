<?php

/**
 * Admin Router — WP admin menu registration and page controller dispatcher.
 *
 * Registers the top-level menu and all submenu pages under 'uwpaic'.
 * Dispatches page renders and settings registration to the correct Admin_Page controller.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Admin;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Admin_Router
 *
 * Singleton router that maps WP admin page slugs to Admin_Page controllers.
 *
 * @since 1.0.0
 * @package WPAIC\Admin
 */
final class Admin_Router
{
	/** @var Admin_Router|null Singleton instance. */
	private static $instance = null;

	/**
	 * Registered page controllers, keyed by slug.
	 *
	 * @var array<string, Admin_Page>
	 */
	private $pages = [];

	/**
	 * WP hook suffixes returned by add_menu_page / add_submenu_page.
	 * Used to match admin_enqueue_scripts $hook.
	 *
	 * @var array<string, string>  slug => hook_suffix
	 */
	private $hook_suffixes = [];

	/** Private constructor — use get_instance(). */
	private function __construct()
	{
	}

	/**
	 * Return (or lazily create) the singleton instance.
	 *
	 * @return Admin_Router
	 */
	public static function get_instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// =========================================================================
	// Admin_Router public contract
	// =========================================================================

	/**
	 * Register the admin menu tree. Called on `admin_menu` action.
	 *
	 * Builds default controllers for all known pages, registers them,
	 * and adds the WP menu entries. Extensions may call register_page()
	 * before this method to inject custom controllers.
	 *
	 * @return void
	 */
	public function register_menus(): void
	{
		$this->bootstrap_default_pages();

		$capability = $this->admin_capability();

		// Top-level menu: General Settings (default page).
		$hook = add_menu_page(
			__('WP AI Chatbot', 'wp-ai-chatbot'),
			__('WP AI Chatbot', 'wp-ai-chatbot'),
			$capability,
			'uwpaic',
			[$this, 'dispatch_current_page'],
			'dashicons-format-chat',
			80
		);

		$this->hook_suffixes['uwpaic'] = (string) $hook;

		// Submenus.
		$submenus = [
			'uwpaic' => __('General Settings', 'wp-ai-chatbot'),
			'uwpaic-providers' => __('Providers & Models', 'wp-ai-chatbot'),
			'uwpaic-chat' => __('Chat Behavior', 'wp-ai-chatbot'),
			'uwpaic-appearance' => __('Appearance', 'wp-ai-chatbot'),
			'uwpaic-knowledge' => __('Knowledge Base', 'wp-ai-chatbot'),
			'uwpaic-security' => __('Security & Privacy', 'wp-ai-chatbot'),
			'uwpaic-tools' => __('Tools & Integrations', 'wp-ai-chatbot'),
			'uwpaic-transcripts' => __('Transcripts & Usage', 'wp-ai-chatbot'),
			'uwpaic-migration' => __('Migration', 'wp-ai-chatbot'),
			'uwpaic-diagnostics' => __('Diagnostics', 'wp-ai-chatbot'),
		];

		foreach ($submenus as $slug => $label) {
			$hook = add_submenu_page(
				'uwpaic',
				$label . ' — ' . __('WP AI Chatbot', 'wp-ai-chatbot'),
				$label,
				$capability,
				$slug,
				[$this, 'dispatch_current_page']
			);

			$this->hook_suffixes[$slug] = (string) $hook;
		}
	}

	/**
	 * Enqueue admin assets (CSS + JS) for the current plugin page.
	 * Called on `admin_enqueue_scripts` with the current hook suffix.
	 *
	 * @param  string $hook Current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets(string $hook): void
	{
		$slug = array_search($hook, $this->hook_suffixes, true);

		if (false === $slug) {
			return; // Not a plugin page.
		}

		// Enqueue shared admin stylesheet.
		wp_enqueue_style(
			'wpaic-admin',
			defined('WPAIC_ADMIN_URL') ? WPAIC_ADMIN_URL . 'css/admin.css' : '',
			[],
			defined('WPAIC_VERSION') ? WPAIC_VERSION : '1.0.0'
		);

		// Enqueue shared admin JS.
		wp_enqueue_script(
			'wpaic-admin',
			defined('WPAIC_ADMIN_URL') ? WPAIC_ADMIN_URL . 'js/admin.js' : '',
			[],
			defined('WPAIC_VERSION') ? WPAIC_VERSION : '1.0.0',
			true
		);

		// Delegate to page-specific assets.
		if (isset($this->pages[$slug])) {
			$this->pages[$slug]->enqueue_page_assets();
		}
	}

	/**
	 * Route the current page request to the appropriate page controller.
	 * Used as the WP menu page callback (all pages share this dispatcher).
	 *
	 * @return void
	 */
	public function dispatch_current_page(): void
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : 'uwpaic';

		$this->dispatch($slug);
	}

	/**
	 * Dispatch to a specific page slug's controller.
	 *
	 * @param  string $page_slug Page slug (e.g., 'uwpaic-providers').
	 * @return void
	 */
	public function dispatch(string $page_slug): void
	{
		if (! isset($this->pages[$page_slug])) {
			$this->render_unknown_page($page_slug);

			return;
		}

		$this->pages[$page_slug]->render();
	}

	/**
	 * Register a page controller for a slug.
	 * Must be called before register_menus() fires (i.e., before admin_menu).
	 *
	 * @param  string     $page_slug   Menu page slug.
	 * @param  Admin_Page $controller  Page controller instance.
	 * @return void
	 */
	public function register_page(string $page_slug, Admin_Page $controller): void
	{
		$this->pages[$page_slug] = $controller;
	}

	/**
	 * Call register_settings() on all registered page controllers.
	 * Invoked by Settings_Manager::register_settings() on admin_init.
	 *
	 * @return void
	 */
	public function register_all_page_settings(): void
	{
		$this->bootstrap_default_pages();

		foreach ($this->pages as $controller) {
			$controller->register_settings();
		}
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Bootstrap the default page controllers if not already registered.
	 * Allows extensions to override before this runs.
	 *
	 * @return void
	 */
	private function bootstrap_default_pages(): void
	{
		$defaults = [
			'uwpaic' => 'WPAIC\Admin\Pages\General_Page',
			'uwpaic-knowledge' => 'WPAIC\Admin\Pages\Knowledge_Page',     // Phase 06
			'uwpaic-tools' => 'WPAIC\Admin\Pages\Tools_Page',             // Phase 07
			'uwpaic-chat' => 'WPAIC\Admin\Pages\Chat_Page',               // Phase 08
			'uwpaic-security' => 'WPAIC\Admin\Pages\Security_Page',       // Phase 09
			'uwpaic-diagnostics' => 'WPAIC\Admin\Pages\Diagnostics_Page', // Phase 09
			'uwpaic-migration' => 'WPAIC\Admin\Pages\Migration_Page',     // Phase 10
			'uwpaic-appearance' => 'WPAIC\Admin\Pages\Appearance_Page',   // Phase 12
			'uwpaic-transcripts' => 'WPAIC\Admin\Pages\Transcripts_Page', // Phase 16
		];

		foreach ($defaults as $slug => $class_name) {
			if (! isset($this->pages[$slug]) && class_exists($class_name)) {
				$this->pages[$slug] = new $class_name();
			}
		}
	}

	/**
	 * Return the admin capability string (filterable).
	 *
	 * @return string
	 */
	private function admin_capability(): string
	{
		if (class_exists('WPAIC\Security\Auth_Enforcer')) {
			return \WPAIC\Security\Auth_Enforcer::admin_capability();
		}

		return (string) apply_filters('wpaic_admin_capability', 'manage_options');
	}

	/**
	 * Render a minimal error page for an unrecognised slug.
	 *
	 * @param  string $slug The unknown page slug.
	 * @return void
	 */
	private function render_unknown_page(string $slug): void
	{
		echo '<div class="wrap"><h1>';
		echo esc_html__('WP AI Chatbot', 'wp-ai-chatbot');
		echo '</h1><p>';
		printf(
			/* translators: %s: page slug */
			esc_html__('Unknown admin page: %s', 'wp-ai-chatbot'),
			esc_html($slug)
		);
		echo '</p></div>';
	}
}
