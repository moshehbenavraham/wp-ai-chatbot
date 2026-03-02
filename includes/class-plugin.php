<?php

/**
 * Main plugin orchestrator — wires every WordPress hook to domain modules.
 *
 * Design decisions:
 *  - Singleton: one instance per request; instantiated from wp-ai-chatbot.php.
 *  - Hook array is filterable via `wpaic_register_hooks` (Baachal pattern).
 *  - Domain module callbacks are guarded by class_exists() so the skeleton
 *    runs cleanly before later phases implement runtime, admin, RAG classes.
 *  - register_activation_hook / register_deactivation_hook are NOT registered
 *    here — they must be called with __FILE__ from the top-level plugin file.
 *
 * @package WP_AI_Chatbot
 */

namespace WPAIC;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Plugin
 *
 * Central orchestrator that bootstraps all plugin domains.
 */
final class Plugin
{
	/** @var Plugin|null Singleton instance. */
	private static $instance = null;

	/** Private constructor enforces singleton. */
	private function __construct()
	{
	}

	/**
	 * Return (and lazily create) the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function get_instance(): Plugin
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// =========================================================================
	// Hook registration
	// =========================================================================

	/**
	 * Register all WordPress actions and filters.
	 *
	 * External code may extend or override hooks via the `wpaic_register_hooks`
	 * filter. Each entry must be an array with at least a `callback` key.
	 * Optional keys: `priority` (default 10), `accepted_args` (default 1).
	 *
	 * @return void
	 */
	public function register_hooks(): void
	{
		// Register core migration discovery hook before plugins_loaded fires.
		add_action(
			'wpaic_register_migrations',
			['WPAIC\Migration\Lifecycle_Manager', 'register_core_migrations']
		);

		// Register v1.0.1 migrations (priority 15 — after core 1.0.0, before integrations).
		add_action(
			'wpaic_register_migrations',
			['WPAIC\Migration\Lifecycle_Manager', 'register_v101_migrations'],
			15
		);

		// Register Phase 08 integration migrations (priority 20 — after core 1.0.0 tables).
		add_action(
			'wpaic_register_migrations',
			['WPAIC\Migration\Lifecycle_Manager', 'register_integrations_migrations'],
			20
		);

		// Register custom cron interval before any event is scheduled.
		add_filter('cron_schedules', [$this, 'add_cron_schedules']);

		// CSV export handler — admin-post.php action (Phase 16).
		if (class_exists('WPAIC\Admin\Pages\Transcripts_Page')) {
			add_action('admin_post_wpaic_export_transcripts', ['WPAIC\Admin\Pages\Transcripts_Page', 'handle_export']);
		}

		// Build the filterable hook array (Baachal pattern).
		$hooks = apply_filters('wpaic_register_hooks', $this->default_hooks());

		foreach ($hooks as $hook => $args) {
			if (! is_array($args) || ! isset($args['callback'])) {
				continue;
			}
			add_action(
				$hook,
				$args['callback'],
				$args['priority'] ?? 10,
				$args['accepted_args'] ?? 1
			);
		}
	}

	/**
	 * Default hook registration map.
	 *
	 * @return array<string, array{callback: callable, priority: int, accepted_args: int}>
	 */
	private function default_hooks(): array
	{
		return [
			'plugins_loaded' => [
				'callback' => [$this, 'on_plugins_loaded'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'init' => [
				'callback' => [$this, 'on_init'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'admin_init' => [
				'callback' => [$this, 'on_admin_init'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'admin_menu' => [
				'callback' => [$this, 'on_admin_menu'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'admin_enqueue_scripts' => [
				'callback' => [$this, 'on_admin_enqueue_scripts'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'rest_api_init' => [
				'callback' => [$this, 'on_rest_api_init'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'wp_enqueue_scripts' => [
				'callback' => [$this, 'on_frontend_enqueue'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'wp_footer' => [
				'callback' => [$this, 'on_frontend_footer'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'wp_ajax_wpaic_chat' => [
				'callback' => [$this, 'on_ajax_chat'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'wp_ajax_nopriv_wpaic_chat' => [
				'callback' => [$this, 'on_ajax_chat'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'wpaic_cleanup_sessions' => [
				'callback' => [$this, 'on_cleanup_sessions'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			'wpaic_process_queue' => [
				'callback' => [$this, 'on_process_queue'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			// Register core provider adapters when the provider registry initialises.
			'wpaic_register_providers' => [
				'callback' => [$this, 'register_core_providers'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			// Register provider-related REST endpoints alongside infrastructure routes.
			'wpaic_register_rest_routes' => [
				'callback' => [$this, 'register_provider_rest_routes'],
				'priority' => 10,
				'accepted_args' => 0,
			],
			// Register core content sources when the source registry initialises.
			'wpaic_register_content_sources' => [
				'callback' => [$this, 'register_core_content_sources'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			// Register core embedding providers when the embedding registry initialises.
			'wpaic_register_embedding_providers' => [
				'callback' => [$this, 'register_core_embedding_providers'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			// Register core RAG hooks (retrieval engine context filter) after init.
			'wpaic_init' => [
				'callback' => [$this, 'register_core_rag'],
				'priority' => 20,
				'accepted_args' => 0,
			],
			// Register MCP server tools with the Tool_Registry (Phase 07).
			'wpaic_register_tools' => [
				'callback' => [$this, 'register_core_tools'],
				'priority' => 10,
				'accepted_args' => 1,
			],
			// Register WooCommerce domain modules when WC is active (Phase 08).
			'wpaic_wc_init' => [
				'callback' => [$this, 'on_wc_init'],
				'priority' => 10,
				'accepted_args' => 0,
			],
			// Schedule lead retention cleanup (Phase 08).
			'wpaic_daily_cleanup' => [
				'callback' => [$this, 'on_daily_cleanup'],
				'priority' => 10,
				'accepted_args' => 0,
			],
		];
	}

	// =========================================================================
	// Cron schedules
	// =========================================================================

	/**
	 * Register custom WP-Cron recurrence intervals.
	 *
	 * @param  array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_schedules(array $schedules): array
	{
		if (! isset($schedules['wpaic_every_five_minutes'])) {
			$schedules['wpaic_every_five_minutes'] = [
				'interval' => 300,
				'display' => __('Every 5 Minutes (WP AI Chatbot)', 'wp-ai-chatbot'),
			];
		}

		return $schedules;
	}

	// =========================================================================
	// WordPress action callbacks
	// =========================================================================

	/**
	 * `plugins_loaded` (priority 10) — i18n, migration check, signal ready.
	 *
	 * @return void
	 */
	public function on_plugins_loaded(): void
	{
		load_plugin_textdomain(
			WPAIC_TEXT_DOMAIN,
			false,
			dirname(WPAIC_BASENAME) . '/languages'
		);

		// Run pending database migrations (version-gated, idempotent).
		Migration\Migration_Registry::get_instance()->check_and_run();

		/**
		 * Fires once the plugin is fully bootstrapped and migrations are done.
		 *
		 * @since 1.0.0
		 */
		do_action('wpaic_loaded');
	}

	/**
	 * `init` (priority 10) — shortcodes, WooCommerce detection, cache exclusions.
	 *
	 * @return void
	 */
	public function on_init(): void
	{
		// Phase 09: Register security and privacy hooks early in init.
		$this->register_core_security();

		// Phase 17: Register Macro Router filter (wpaic_macro_route).
		if (class_exists('WPAIC\Runtime\Macro_Router')) {
			Runtime\Macro_Router::register_hooks();
		}

		// Phase 17: Inject per-bot tool assignments into resolved bot config.
		// Reads `wpaic_bot_tools_{slug}` option and merges into bot['tools_enabled'].
		add_filter('wpaic_resolve_bot', static function (array $bot, string $slug): array {
			$option = get_option("wpaic_bot_tools_{$slug}", null);

			// Only override if an explicit assignment has been saved.
			if (null !== $option) {
				if (is_string($option)) {
					$option = json_decode($option, true) ?: [];
				}
				// Store as JSON string so Tool_Registry::get_enabled_for_bot() can decode it.
				$bot['tools_enabled'] = wp_json_encode(array_values((array) $option));
			}

			return $bot;
		}, 10, 2);

		// Register the primary shortcode.
		add_shortcode('wpaic_chatbot', [$this, 'render_chatbot_shortcode']);

		// Detect WooCommerce and declare HPOS compatibility.
		if (class_exists('WooCommerce')) {
			add_action(
				'before_woocommerce_init',
				static function (): void {
					if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
						\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
							'custom_order_tables',
							WPAIC_BASENAME,
							true
						);
					}
				}
			);

			/**
			 * Fires when WooCommerce is active, after HPOS compatibility is declared.
			 * Deferred WooCommerce domain modules hook here to register their features.
			 *
			 * @since 1.0.0
			 */
			do_action('wpaic_wc_init');
		}

		$this->register_cache_exclusions();

		/**
		 * Fires during `init`. Extension modules register providers, sources,
		 * and other runtime components here.
		 *
		 * @since 1.0.0
		 */
		do_action('wpaic_init');
	}

	/**
	 * `admin_init` (priority 10) — settings registration, per-page controllers.
	 *
	 * @return void
	 */
	public function on_admin_init(): void
	{
		if (! is_admin()) {
			return;
		}

		// Phase 05: Settings_Manager::get_instance()->register_settings().
		if (class_exists('WPAIC\Admin\Settings_Manager')) {
			Admin\Settings_Manager::get_instance()->register_settings();
		}

		/**
		 * Fires after admin settings are registered and page controllers are ready.
		 *
		 * @since 1.0.0
		 */
		do_action('wpaic_admin_ready');
	}

	/**
	 * `admin_menu` (priority 10) — register admin menu pages.
	 *
	 * @return void
	 */
	public function on_admin_menu(): void
	{
		// Phase 05: Admin_Router registers menu pages.
		if (class_exists('WPAIC\Admin\Admin_Router')) {
			Admin\Admin_Router::get_instance()->register_menus();
		}
	}

	/**
	 * `admin_enqueue_scripts` (priority 10) — enqueue admin assets.
	 *
	 * @param  string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function on_admin_enqueue_scripts(string $hook_suffix): void
	{
		// Phase 05: Admin_Router enqueues assets for the current admin page.
		if (class_exists('WPAIC\Admin\Admin_Router')) {
			Admin\Admin_Router::get_instance()->enqueue_assets($hook_suffix);
		}
	}

	/**
	 * `rest_api_init` (priority 10) — register all REST endpoints.
	 *
	 * Infrastructure endpoints (health, backup) are registered here.
	 * Domain endpoints are registered via the `wpaic_register_rest_routes` action.
	 *
	 * @return void
	 */
	public function on_rest_api_init(): void
	{
		// Load and register infrastructure REST endpoints.
		require_once WPAIC_PLUGIN_DIR . 'lib/health-endpoint.php';
		require_once WPAIC_PLUGIN_DIR . 'lib/backup-manager.php';

		wpaic_register_health_endpoint();
		wpaic_register_backup_endpoints();

		/**
		 * Fires during `rest_api_init`. Domain modules register their REST routes here.
		 *
		 * @since 1.0.0
		 */
		do_action('wpaic_register_rest_routes');
	}

	/**
	 * `wp_enqueue_scripts` (priority 10) — enqueue frontend chatbot assets.
	 *
	 * @return void
	 */
	public function on_frontend_enqueue(): void
	{
		if (! $this->should_display_chatbot()) {
			return;
		}

		$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'wpaic-chatbot',
			WPAIC_ASSETS_URL . 'css/chatbot' . $suffix . '.css',
			[],
			WPAIC_VERSION
		);

		// Phase 12 — Appearance: optionally load Google Fonts (Space Grotesk, Inter, JetBrains Mono).
		if (get_option('wpaic_appearance_load_fonts', 0)) {
			wp_enqueue_style(
				'wpaic-fonts',
				'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600&family=Inter:wght@400;500&family=JetBrains+Mono:wght@400&display=swap',
				[],
				null
			);
		}

		wp_enqueue_script(
			'wpaic-chatbot',
			WPAIC_ASSETS_URL . 'js/chatbot' . $suffix . '.js',
			[],
			WPAIC_VERSION,
			true
		);

		wp_localize_script(
			'wpaic-chatbot',
			'wpaicChatData',
			$this->get_frontend_script_data()
		);
	}

	/**
	 * `wp_footer` (priority 10) — output the floating chatbot widget.
	 *
	 * @return void
	 */
	public function on_frontend_footer(): void
	{
		// Phase 05: Render chatbot widget template when assets are enqueued.
		if (! $this->should_display_chatbot()) {
			return;
		}

		$template = WPAIC_PLUGIN_DIR . 'templates/chatbot-widget.php';
		if (file_exists($template)) {
			include $template;
		}
	}

	/**
	 * `wp_ajax_wpaic_chat` / `wp_ajax_nopriv_wpaic_chat` — handle chat AJAX.
	 *
	 * @return void
	 */
	public function on_ajax_chat(): void
	{
		// Phase 05: Message_Pipeline processes the request.
		if (class_exists('WPAIC\Runtime\Message_Pipeline')) {
			Runtime\Message_Pipeline::handle_ajax();

			return;
		}

		wp_send_json_error(
			['message' => __('Chat runtime is not yet initialised.', 'wp-ai-chatbot')],
			503
		);
	}

	/**
	 * `wpaic_cleanup_sessions` cron event — remove expired and stale sessions.
	 *
	 * @return void
	 */
	public function on_cleanup_sessions(): void
	{
		// Phase 05: Session_Manager::cleanup_expired().
		if (class_exists('WPAIC\Runtime\Session_Manager')) {
			Runtime\Session_Manager::cleanup_expired();
		}
	}

	/**
	 * `wpaic_process_queue` cron event — process pending ingestion queue items.
	 *
	 * @return void
	 */
	public function on_process_queue(): void
	{
		// Phase 06: Ingestion_Pipeline::process_next_batch().
		if (class_exists('WPAIC\Rag\Ingestion_Pipeline')) {
			Rag\Ingestion_Pipeline::process_next_batch();
		}
	}

	// =========================================================================
	// Phase 08 — WooCommerce, leads, and livechat hooks
	// =========================================================================

	/**
	 * `wpaic_wc_init` — initialise WooCommerce domain modules.
	 *
	 * Fires from on_init() when WooCommerce is active. Registers the WC products
	 * content source, WC context provider (cart/orders injection), and WC tools.
	 *
	 * @return void
	 */
	public function on_wc_init(): void
	{
		// Register WC products as a RAG content source.
		if (
			class_exists('WPAIC\Rag\Content_Source_Registry')
			&& class_exists('WPAIC\WooCommerce\WC_Products_Source')
		) {
			add_action(
				'wpaic_register_content_sources',
				function (Rag\Content_Source_Registry $registry): void {
					$registry->register('wc_products', new WooCommerce\WC_Products_Source());
				},
				20
			);
		}

		// Wire the WC context provider into the RAG context filter.
		if (class_exists('WPAIC\WooCommerce\WC_Context_Provider')) {
			WooCommerce\WC_Context_Provider::register_hooks();
		}
	}

	/**
	 * `wpaic_daily_cleanup` cron event — run retention-based cleanup tasks.
	 *
	 * @return void
	 */
	public function on_daily_cleanup(): void
	{
		// Phase 08: purge leads past their retention policy.
		if (class_exists('WPAIC\Integrations\Lead_Manager')) {
			Integrations\Lead_Manager::cleanup_old_leads();
		}

		// Phase 09: purge chat sessions past the data retention period.
		if (class_exists('WPAIC\Security\Privacy_Manager')) {
			Security\Privacy_Manager::apply_retention_policy();
		}

		// Phase 11: rotate structured log files — delete daily log files older than 30 days.
		$this->prune_log_files(30);
	}

	/**
	 * Delete daily structured log files older than $days.
	 *
	 * Log files follow the naming pattern: logs/app-YYYY-MM-DD.log
	 * Files outside the retention window are deleted to prevent unbounded disk growth.
	 *
	 * @param  int $days Number of days to retain log files.
	 * @return void
	 */
	private function prune_log_files(int $days): void
	{
		if ($days <= 0) {
			return;
		}

		$logs_dir = defined('WPAIC_PLUGIN_DIR') ? WPAIC_PLUGIN_DIR . 'logs/' : '';
		if ('' === $logs_dir || ! is_dir($logs_dir)) {
			return;
		}

		$cutoff = time() - ($days * DAY_IN_SECONDS);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.glob_glob
		$files = glob($logs_dir . 'app-*.log');
		if (! is_array($files)) {
			return;
		}

		foreach ($files as $file) {
			// Extract date from filename: app-YYYY-MM-DD.log
			$basename = basename($file, '.log');
			$date_str = substr($basename, 4); // strip 'app-'
			$file_time = strtotime($date_str);

			if (false !== $file_time && $file_time < $cutoff) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
				@unlink($file);
			}
		}
	}

	// =========================================================================
	// Phase 09 — Security, privacy, and moderation
	// =========================================================================

	/**
	 * Register core security and privacy hooks.
	 *
	 * Called early in on_init() (before WooCommerce detection and shortcodes).
	 * Wires Privacy_Manager WP personal data hooks and Content_Moderator
	 * blocklist filter — both are no-ops when their classes are absent.
	 *
	 * @return void
	 */
	public function register_core_security(): void
	{
		// GDPR personal data export / erase.
		if (class_exists('WPAIC\Security\Privacy_Manager')) {
			Security\Privacy_Manager::register_hooks();
		}

		// Keyword-based message moderation.
		if (class_exists('WPAIC\Security\Content_Moderator')) {
			Security\Content_Moderator::register_hooks();
		}
	}

	// =========================================================================
	// Shortcode rendering
	// =========================================================================

	/**
	 * Render the [wpaic_chatbot] shortcode.
	 *
	 * Supported attributes:
	 *   bot_slug    — Bot slug (default 'default').
	 *   mode        — 'inline' or 'floating' (default 'inline' for shortcode).
	 *   title       — Chat panel title override.
	 *   placeholder — Input placeholder text override.
	 *   gdpr        — '1' to enable GDPR consent banner.
	 *
	 * @param  array  $atts    Shortcode attributes (raw from WP parser).
	 * @param  string $content Shortcode content (unused).
	 * @return string HTML output.
	 */
	public function render_chatbot_shortcode(array $atts = [], string $content = ''): string
	{
		$template = WPAIC_PLUGIN_DIR . 'templates/chatbot-widget.php';
		if (! file_exists($template)) {
			return '';
		}

		// Merge caller atts with shortcode defaults.
		$atts = shortcode_atts(
			[
				'bot_slug' => 'default',
				'mode' => 'inline',
				'title' => '',
				'placeholder' => '',
				'gdpr' => '',
			],
			$atts,
			'wpaic_chatbot'
		);

		// Enqueue assets if not already done (shortcode used inside content).
		if (! wp_script_is('wpaic-chatbot', 'enqueued')) {
			$suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
			wp_enqueue_style('wpaic-chatbot', WPAIC_ASSETS_URL . 'css/chatbot' . $suffix . '.css', [], WPAIC_VERSION);
			wp_enqueue_script('wpaic-chatbot', WPAIC_ASSETS_URL . 'js/chatbot' . $suffix . '.js', [], WPAIC_VERSION, true);
			wp_localize_script('wpaic-chatbot', 'wpaicChatData', $this->get_frontend_script_data());
		}

		ob_start();
		include $template;

		return (string) ob_get_clean();
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Determine whether the chatbot widget should render on the current page.
	 *
	 * Global display is enabled by default. Operators may restrict to specific
	 * page IDs via the `wpaic_widget_page_ids` option (comma-separated list).
	 * Returns true unconditionally when no restriction is configured.
	 *
	 * @return bool
	 */
	private function should_display_chatbot(): bool
	{
		if (! file_exists(WPAIC_PLUGIN_DIR . 'assets/js/chatbot.js')) {
			return false;
		}

		// Check global display toggle (default: enabled).
		$enabled = get_option('wpaic_widget_display_global', '1');
		if (! $enabled || '0' === $enabled) {
			return false;
		}

		// Optional page-ID restriction (empty = all pages).
		$page_ids_raw = get_option('wpaic_widget_page_ids', '');
		if ('' !== $page_ids_raw) {
			$page_ids = array_filter(array_map('intval', explode(',', $page_ids_raw)));
			if (! empty($page_ids) && ! in_array((int) get_the_ID(), $page_ids, true)) {
				return false;
			}
		}

		/**
		 * Filter: extensions can override display decision (e.g. logged-in only).
		 *
		 * @since 1.0.0
		 * @param bool $display Whether to display the chatbot.
		 */
		return (bool) apply_filters('wpaic_should_display_chatbot', true);
	}

	/**
	 * Build the localised data object passed to chatbot.js via wp_localize_script.
	 *
	 * @return array<string, mixed>
	 */
	private function get_frontend_script_data(): array
	{
		return [
			'ajaxUrl' => admin_url('admin-ajax.php'),
			'restUrl' => rest_url(WPAIC_REST_NAMESPACE),
			'nonce' => wp_create_nonce('wpaic_chat_nonce'),
			'streamEnabled' => (bool) get_option('wpaic_runtime_stream_enabled', '1'),
			'version' => WPAIC_VERSION,
			'i18n' => [
				'open_chat' => __('Open chat', 'wp-ai-chatbot'),
				'close_chat' => __('Close chat', 'wp-ai-chatbot'),
				'error_generic' => __('An error occurred. Please try again.', 'wp-ai-chatbot'),
				'error_network' => __('Network error. Please check your connection.', 'wp-ai-chatbot'),
			],
		];
	}

	/**
	 * Register cache-exclusion hints for popular caching plugins.
	 *
	 * Prevents caching plugins from breaking AJAX and SSE streaming by marking
	 * the chatbot's dynamic requests as uncacheable.
	 *
	 * @return void
	 */
	private function register_cache_exclusions(): void
	{
		// WP Rocket: exclude from RUCSS and JS/CSS optimisation.
		add_filter('rocket_exclude_js', [$this, 'exclude_chatbot_from_cache']);
		add_filter('rocket_exclude_css', [$this, 'exclude_chatbot_from_cache']);

		// Autoptimize: exclude chatbot script from aggregation.
		add_filter('autoptimize_filter_js_exclude', [$this, 'exclude_chatbot_from_autoptimize']);
		add_filter('autoptimize_filter_css_exclude', [$this, 'exclude_chatbot_from_autoptimize']);
	}

	/**
	 * Add chatbot assets to WP Rocket's exclusion list.
	 *
	 * @param  array $excluded Existing exclusion array.
	 * @return array Modified exclusion array.
	 */
	public function exclude_chatbot_from_cache(array $excluded): array
	{
		$excluded[] = WPAIC_ASSETS_URL . 'js/chatbot.js';
		$excluded[] = WPAIC_ASSETS_URL . 'css/chatbot.css';

		return $excluded;
	}

	/**
	 * Add chatbot assets to Autoptimize's exclusion string.
	 *
	 * @param  string $excluded Existing comma-separated exclusion string.
	 * @return string Modified exclusion string.
	 */
	public function exclude_chatbot_from_autoptimize(string $excluded): string
	{
		return $excluded . ',chatbot.js,chatbot.css';
	}

	// =========================================================================
	// Provider registration
	// =========================================================================

	/**
	 * Register core provider adapters with the provider registry.
	 *
	 * Fires on `wpaic_register_providers` (triggered lazily on first registry access).
	 * Registers OpenAI, Claude, and Gemini adapters. Additional providers can be
	 * registered by third-party code hooking `wpaic_register_providers` at priority > 10.
	 *
	 * @param  Provider\Provider_Registry $registry The provider registry instance.
	 * @return void
	 */
	public function register_core_providers(Provider\Provider_Registry $registry): void
	{
		$registry->register('openai', 'WPAIC\Provider\Adapters\OpenAI_Adapter');
		$registry->register('claude', 'WPAIC\Provider\Adapters\Claude_Adapter');
		$registry->register('gemini', 'WPAIC\Provider\Adapters\Gemini_Adapter');
		$registry->register('deepseek', 'WPAIC\Provider\Adapters\DeepSeek_Adapter');
		$registry->register('grok', 'WPAIC\Provider\Adapters\Grok_Adapter');
	}

	// =========================================================================
	// Provider REST routes
	// =========================================================================

	/**
	 * Register provider-related REST endpoints.
	 *
	 * Fires on `wpaic_register_rest_routes` (triggered from `rest_api_init`).
	 *
	 * Endpoint: POST wp-ai-chatbot/v1/providers/validate
	 *   Validates an API key for the given provider by making a lightweight
	 *   HTTP probe to the provider's models/info endpoint. Requires
	 *   `manage_options` capability (admin-only).
	 *
	 * Session endpoints: POST/GET/DELETE /sessions, GET /sessions/{token}/history
	 *
	 * @return void
	 */
	public function register_provider_rest_routes(): void
	{
		// Register session CRUD REST routes.
		if (class_exists('WPAIC\Runtime\Session_Manager')) {
			Runtime\Session_Manager::register_rest_routes();
		}

		// Register knowledge base REST routes (Phase 06).
		if (class_exists('WPAIC\Admin\Pages\Knowledge_Page')) {
			$this->register_knowledge_rest_routes();
		}

		// Register tools REST routes (Phase 07).
		if (class_exists('WPAIC\Admin\Pages\Tools_Page')) {
			$this->register_tools_rest_routes();
		}

		// Register chat/leads/livechat REST routes (Phase 08).
		if (class_exists('WPAIC\Admin\Pages\Chat_Page')) {
			$this->register_chat_rest_routes();
		}

		// Register migration REST routes (Phase 10).
		if (class_exists('WPAIC\Migration\Migration_Runner')) {
			$this->register_migration_rest_routes();
		}

		// Register transcripts REST routes (Phase 16).
		if (class_exists('WPAIC\Admin\Pages\Transcripts_Page')) {
			$this->register_transcripts_rest_routes();
		}

		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/providers/validate',
			[
				'methods' => 'POST',
				'callback' => [$this, 'rest_validate_provider_key'],
				'permission_callback' => static function (): bool {
					return current_user_can('manage_options');
				},
				'args' => [
					'provider_id' => [
						'required' => true,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'api_key' => [
						'required' => false,
						'type' => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default' => '',
					],
				],
			]
		);
	}

	/**
	 * REST callback: validate a provider API key.
	 *
	 * If `api_key` is empty in the request, the key is resolved from the saved
	 * settings (Settings_Manager). The probe is a read-only HTTP GET to the
	 * provider's model listing endpoint, which requires no token budget.
	 *
	 * @param  \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_validate_provider_key(\WP_REST_Request $request)
	{
		$provider_id = (string) $request->get_param('provider_id');
		$api_key = (string) $request->get_param('api_key');

		// Fall back to saved key when none is provided in the request.
		if ('' === $api_key && class_exists('WPAIC\Admin\Settings_Manager')) {
			$api_key = (string) Admin\Settings_Manager::get("wpaic_provider_{$provider_id}_key", '');
		}

		if ('' === $api_key) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'provider_id' => $provider_id,
					'error' => __('API key is empty. Please enter a key to validate.', 'wp-ai-chatbot'),
				],
				400
			);
		}

		$started = microtime(true);
		$result = $this->probe_provider($provider_id, $api_key);
		$latency = (int) round((microtime(true) - $started) * 1000);

		if (isset($result['error'])) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'provider_id' => $provider_id,
					'latency_ms' => $latency,
					'error' => $result['error'],
				],
				200
			);
		}

		return new \WP_REST_Response(
			[
				'success' => true,
				'provider_id' => $provider_id,
				'latency_ms' => $latency,
				'message' => __('API key validated successfully.', 'wp-ai-chatbot'),
			],
			200
		);
	}

	// =========================================================================
	// RAG module wiring (Phase 06)
	// =========================================================================

	/**
	 * Register core content source adapters with the content source registry.
	 *
	 * Fires on `wpaic_register_content_sources` (triggered lazily on first registry access).
	 * Registers the built-in Posts_Source. Additional sources can be registered by
	 * third-party code hooking `wpaic_register_content_sources` at priority > 10.
	 *
	 * @param  Rag\Content_Source_Registry $registry The content source registry instance.
	 * @return void
	 */
	public function register_core_content_sources(Rag\Content_Source_Registry $registry): void
	{
		if (class_exists('WPAIC\Rag\Adapters\Posts_Source')) {
			$registry->register('posts', new Rag\Adapters\Posts_Source());
		}

		if (class_exists('WPAIC\Rag\Adapters\Pdf_Source')) {
			$registry->register('pdf', new Rag\Adapters\Pdf_Source());
		}

		if (class_exists('WPAIC\Rag\Adapters\Web_Source')) {
			$registry->register('web', new Rag\Adapters\Web_Source());
		}

		if (class_exists('WooCommerce') && class_exists('WPAIC\WooCommerce\WC_Products_Source')) {
			$registry->register('wc_products', new WooCommerce\WC_Products_Source());
		}
	}

	/**
	 * Register core embedding provider adapters with the embedding registry.
	 *
	 * Fires on `wpaic_register_embedding_providers` (triggered lazily on first registry access).
	 * Registers the OpenAI embedding adapter using the saved API key.
	 *
	 * @param  Rag\Embedding_Registry $registry The embedding registry instance.
	 * @return void
	 */
	public function register_core_embedding_providers(Rag\Embedding_Registry $registry): void
	{
		if (! class_exists('WPAIC\Rag\Adapters\OpenAI_Embedding')) {
			return;
		}

		$api_key = '';

		if (class_exists('WPAIC\Admin\Settings_Manager')) {
			$api_key = (string) Admin\Settings_Manager::get('wpaic_provider_openai_key', '');
		}

		$registry->register('openai', new Rag\Adapters\OpenAI_Embedding($api_key));
	}

	/**
	 * Register core RAG hooks during `wpaic_init` (priority 20).
	 *
	 * Wires the Retrieval_Engine into the `wpaic_rag_context` filter so that
	 * the Message_Pipeline receives knowledge base context in Stage 2.
	 *
	 * @return void
	 */
	public function register_core_rag(): void
	{
		if (class_exists('WPAIC\Rag\Retrieval_Engine')) {
			Rag\Retrieval_Engine::register_hooks();
		}
	}

	// =========================================================================
	// Tools module wiring (Phase 07)
	// =========================================================================

	/**
	 * Register core tools with the Tool_Registry.
	 *
	 * Fires on `wpaic_register_tools` (triggered lazily on first Tool_Registry access).
	 * Registers webhook tools loaded from the wpaic_webhook_tools option and all
	 * cached MCP tools via Mcp_Registry. Third-party code can hook the same action
	 * at priority > 10 to register additional tools.
	 *
	 * @param  Tools\Tool_Registry $registry The tool registry instance.
	 * @return void
	 */
	public function register_core_tools(Tools\Tool_Registry $registry): void
	{
		// Register webhook tools from stored option.
		if (class_exists('WPAIC\Tools\Adapters\Webhook_Tool')) {
			$raw = get_option(Admin\Pages\Tools_Page::WEBHOOKS_OPTION, []);
			$webhooks = is_array($raw) ? $raw : [];

			foreach ($webhooks as $config) {
				if (! empty($config['id'])) {
					$registry->register(new Tools\Adapters\Webhook_Tool($config));
				}
			}
		}

		// Register cached MCP tools via Mcp_Registry.
		if (class_exists('WPAIC\Mcp\Mcp_Registry')) {
			Mcp\Mcp_Registry::instance()->register_tools_with_registry($registry);
		}

		// Phase 08: Register WooCommerce tools when WC is active.
		if (class_exists('WooCommerce')) {
			if (class_exists('WPAIC\WooCommerce\WC_Cart_Tool')) {
				$registry->register(new WooCommerce\WC_Cart_Tool());
			}
			if (class_exists('WPAIC\WooCommerce\WC_Orders_Tool')) {
				$registry->register(new WooCommerce\WC_Orders_Tool());
			}
		}

		// Phase 08: Register Lead Capture tool.
		if (class_exists('WPAIC\Integrations\Lead_Capture_Tool')) {
			$registry->register(new Integrations\Lead_Capture_Tool());
		}

		// Phase 08: Register Live Agent Handoff tool.
		if (class_exists('WPAIC\Integrations\Livechat_Handoff_Tool')) {
			$registry->register(new Integrations\Livechat_Handoff_Tool());
		}
	}

	/**
	 * Register tools-related REST endpoints.
	 *
	 * Called from register_provider_rest_routes() when Tools_Page is available.
	 *
	 * @return void
	 */
	private function register_tools_rest_routes(): void
	{
		$page = new Admin\Pages\Tools_Page();

		$admin_perm = static function (): bool {
			return current_user_can('manage_options');
		};

		// Webhook tools.
		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/webhooks', [
			['methods' => 'GET',  'callback' => [$page, 'rest_list_webhooks'],   'permission_callback' => $admin_perm],
			['methods' => 'POST', 'callback' => [$page, 'rest_create_webhook'],  'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_key'],
					'name' => ['required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
					'description' => ['required' => true,  'type' => 'string'],
					'webhook_url' => ['required' => true,  'type' => 'string'],
					'http_method' => ['required' => false, 'type' => 'string', 'default' => 'POST'],
					'parameters' => ['required' => false, 'default' => []],
					'static_params' => ['required' => false, 'default' => []],
					'permission' => ['required' => false, 'type' => 'string', 'default' => 'none'],
					'timeout' => ['required' => false, 'type' => 'integer', 'default' => 15],
					'success_message' => ['required' => false, 'type' => 'string', 'default' => ''],
					'error_message' => ['required' => false, 'type' => 'string', 'default' => ''],
				],
			],
		]);

		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/webhooks/(?P<id>[a-z0-9_]+)', [
			['methods' => 'DELETE', 'callback' => [$page, 'rest_delete_webhook'], 'permission_callback' => $admin_perm,
				'args' => ['id' => ['required' => true, 'type' => 'string']],
			],
		]);

		// MCP servers.
		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/mcp', [
			['methods' => 'GET',  'callback' => [$page, 'rest_list_mcp'],   'permission_callback' => $admin_perm],
			['methods' => 'POST', 'callback' => [$page, 'rest_create_mcp'], 'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_key'],
					'name' => ['required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
					'url' => ['required' => true,  'type' => 'string'],
					'headers' => ['required' => false, 'default' => []],
					'timeout' => ['required' => false, 'type' => 'integer', 'default' => 15],
				],
			],
		]);

		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/mcp/(?P<id>[a-z0-9_]+)', [
			['methods' => 'DELETE', 'callback' => [$page, 'rest_delete_mcp'], 'permission_callback' => $admin_perm,
				'args' => ['id' => ['required' => true, 'type' => 'string']],
			],
		]);

		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/mcp/(?P<id>[a-z0-9_]+)/test', [
			['methods' => 'POST', 'callback' => [$page, 'rest_test_mcp'], 'permission_callback' => $admin_perm,
				'args' => ['id' => ['required' => true, 'type' => 'string']],
			],
		]);

		// Action Catalog (Phase 16).
		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/catalog', [
			['methods' => 'GET', 'callback' => [$page, 'rest_list_catalog'], 'permission_callback' => $admin_perm],
		]);

		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/catalog/(?P<id>[^/]+)/test', [
			['methods' => 'POST', 'callback' => [$page, 'rest_test_tool'], 'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => true, 'type' => 'string'],
					'inputs' => ['required' => false, 'default' => []],
				],
			],
		]);

		// Routing rules (Phase 17).
		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/routing', [
			['methods' => 'GET',  'callback' => [$page, 'rest_list_rules'],  'permission_callback' => $admin_perm],
			['methods' => 'POST', 'callback' => [$page, 'rest_upsert_rule'], 'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => false, 'type' => 'string', 'default' => ''],
					'name' => ['required' => true,  'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
					'pattern' => ['required' => true,  'type' => 'string'],
					'pattern_type' => ['required' => false, 'type' => 'string', 'default' => 'keyword', 'enum' => ['keyword', 'regex']],
					'action_type' => ['required' => false, 'type' => 'string', 'default' => 'direct_response', 'enum' => ['direct_response', 'call_tool']],
					'action_data' => ['required' => true,  'type' => 'string'],
					'priority' => ['required' => false, 'type' => 'integer', 'default' => 10],
					'enabled' => ['required' => false, 'type' => 'boolean', 'default' => true],
				],
			],
		]);

		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/routing/(?P<id>[a-z0-9_]+)', [
			['methods' => 'DELETE', 'callback' => [$page, 'rest_delete_rule'], 'permission_callback' => $admin_perm,
				'args' => ['id' => ['required' => true, 'type' => 'string']],
			],
		]);

		// MCP auth (Phase 17).
		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/mcp/(?P<id>[a-z0-9_]+)/auth', [
			['methods' => 'POST', 'callback' => [$page, 'rest_save_mcp_auth'], 'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => true,  'type' => 'string'],
					'auth_type' => ['required' => true,  'type' => 'string', 'enum' => ['none', 'api_key', 'bearer', 'custom']],
					'auth_header_name' => ['required' => false, 'type' => 'string', 'default' => ''],
					'value' => ['required' => false, 'type' => 'string', 'default' => ''],
				],
			],
		]);

		// Bot tool assignments (Phase 17).
		register_rest_route(WPAIC_REST_NAMESPACE, '/tools/bots/(?P<slug>[a-z0-9_-]+)/tools', [
			['methods' => 'GET', 'callback' => [$page, 'rest_get_bot_tools'], 'permission_callback' => $admin_perm,
				'args' => ['slug' => ['required' => true, 'type' => 'string']],
			],
			['methods' => 'PUT', 'callback' => [$page, 'rest_save_bot_tools'], 'permission_callback' => $admin_perm,
				'args' => [
					'slug' => ['required' => true,  'type' => 'string'],
					'enabled' => ['required' => false, 'default' => []],
				],
			],
		]);
	}

	/**
	 * Register knowledge base REST endpoints.
	 *
	 * Called from register_provider_rest_routes() when Knowledge_Page is available.
	 * Endpoints:
	 *   POST   /wp-ai-chatbot/v1/knowledge                     — create knowledge context
	 *   POST   /wp-ai-chatbot/v1/knowledge/{id}/ingest         — trigger ingestion
	 *   GET    /wp-ai-chatbot/v1/knowledge/{id}/status         — status and stats
	 *   GET    /wp-ai-chatbot/v1/knowledge/{id}/search         — search console (Phase 15)
	 *   GET    /wp-ai-chatbot/v1/knowledge/web-sources         — list web source entries
	 *   POST   /wp-ai-chatbot/v1/knowledge/web-sources         — create/update web source
	 *   DELETE /wp-ai-chatbot/v1/knowledge/web-sources/{id}   — delete web source
	 *
	 * @return void
	 */
	private function register_knowledge_rest_routes(): void
	{
		$page = new Admin\Pages\Knowledge_Page();

		$admin_perm = static function (): bool {
			return current_user_can('manage_options');
		};

		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/knowledge',
			[
				'methods' => 'POST',
				'callback' => [$page, 'rest_create_knowledge'],
				'permission_callback' => $admin_perm,
				'args' => [
					'name' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
					'embedding_provider' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'default' => 'openai'],
					'embedding_model' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => 'text-embedding-3-small'],
				],
			]
		);

		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/knowledge/(?P<id>\d+)/ingest',
			[
				'methods' => 'POST',
				'callback' => [$page, 'rest_trigger_ingest'],
				'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
					'source_id' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_key', 'default' => 'posts'],
					'post_types' => ['required' => false, 'type' => 'array', 'default' => ['post', 'page']],
				],
			]
		);

		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/knowledge/(?P<id>\d+)/status',
			[
				'methods' => 'GET',
				'callback' => [$page, 'rest_get_status'],
				'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
				],
			]
		);

		// Phase 15: search console endpoint.
		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/knowledge/(?P<id>\d+)/search',
			[
				'methods' => 'GET',
				'callback' => [$page, 'rest_search'],
				'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
					'query' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
					'limit' => ['required' => false, 'type' => 'integer', 'default' => 5, 'minimum' => 1, 'maximum' => 20],
				],
			]
		);

		// Phase 15: web source management endpoints.
		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/knowledge/web-sources',
			[
				[
					'methods' => 'GET',
					'callback' => [$page, 'rest_list_web_sources'],
					'permission_callback' => $admin_perm,
				],
				[
					'methods' => 'POST',
					'callback' => [$page, 'rest_upsert_web_source'],
					'permission_callback' => $admin_perm,
					'args' => [
						'url' => ['required' => true, 'type' => 'string'],
						'label' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field', 'default' => ''],
						'depth' => ['required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'maximum' => 3],
						'id' => ['required' => false, 'type' => 'integer', 'default' => 0],
					],
				],
			]
		);

		register_rest_route(
			WPAIC_REST_NAMESPACE,
			'/knowledge/web-sources/(?P<id>\d+)',
			[
				'methods' => 'DELETE',
				'callback' => [$page, 'rest_delete_web_source'],
				'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
				],
			]
		);
	}

	/**
	 * Register chat/leads/livechat REST endpoints (Phase 08).
	 *
	 * Endpoints:
	 *   GET    /wp-ai-chatbot/v1/chat/leads                     — list leads (paginated)
	 *   POST   /wp-ai-chatbot/v1/chat/leads/{id}/status          — update lead status
	 *   DELETE /wp-ai-chatbot/v1/chat/leads/{id}                 — delete lead
	 *   GET    /wp-ai-chatbot/v1/chat/livechat/queue             — pending handoff queue
	 *   POST   /wp-ai-chatbot/v1/chat/livechat/{token}/status    — update session status
	 *
	 * @return void
	 */
	private function register_chat_rest_routes(): void
	{
		$page = new Admin\Pages\Chat_Page();

		$admin_perm = static function (): bool {
			return current_user_can('manage_options');
		};

		// Leads list.
		register_rest_route(WPAIC_REST_NAMESPACE, '/chat/leads', [
			'methods' => 'GET',
			'callback' => [$page, 'rest_list_leads'],
			'permission_callback' => $admin_perm,
			'args' => [
				'per_page' => ['required' => false, 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100],
				'page' => ['required' => false, 'type' => 'integer', 'default' => 1,  'minimum' => 1],
				'status' => ['required' => false, 'type' => 'string',  'default' => ''],
				'search' => ['required' => false, 'type' => 'string',  'default' => ''],
			],
		]);

		// Update lead status.
		register_rest_route(WPAIC_REST_NAMESPACE, '/chat/leads/(?P<id>\d+)/status', [
			'methods' => 'POST',
			'callback' => [$page, 'rest_update_lead_status'],
			'permission_callback' => $admin_perm,
			'args' => [
				'id' => ['required' => true,  'type' => 'integer', 'minimum' => 1],
				'status' => ['required' => true,  'type' => 'string',  'sanitize_callback' => 'sanitize_key'],
			],
		]);

		// Delete lead.
		register_rest_route(WPAIC_REST_NAMESPACE, '/chat/leads/(?P<id>\d+)', [
			'methods' => 'DELETE',
			'callback' => [$page, 'rest_delete_lead'],
			'permission_callback' => $admin_perm,
			'args' => [
				'id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
			],
		]);

		// Livechat pending queue.
		register_rest_route(WPAIC_REST_NAMESPACE, '/chat/livechat/queue', [
			'methods' => 'GET',
			'callback' => [$page, 'rest_get_livechat_queue'],
			'permission_callback' => $admin_perm,
		]);

		// Update livechat session status.
		register_rest_route(WPAIC_REST_NAMESPACE, '/chat/livechat/(?P<token>[a-f0-9\-]{36})/status', [
			'methods' => 'POST',
			'callback' => [$page, 'rest_update_livechat_status'],
			'permission_callback' => $admin_perm,
			'args' => [
				'token' => ['required' => true, 'type' => 'string'],
				'status' => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_key'],
				'agent_id' => ['required' => false, 'type' => 'integer', 'default' => 0],
			],
		]);
	}

	/**
	 * Probe a provider's API to verify the given key is valid.
	 *
	 * Uses read-only model-listing endpoints that incur no token cost:
	 *   openai  → GET https://api.openai.com/v1/models
	 *   claude  → GET https://api.anthropic.com/v1/models
	 *   gemini  → GET https://generativelanguage.googleapis.com/v1beta/models?key={key}
	 *
	 * @param  string $provider_id Canonical provider ID.
	 * @param  string $api_key     Plaintext API key.
	 * @return array               ['success' => true] or ['error' => string].
	 */
	private function probe_provider(string $provider_id, string $api_key): array
	{
		switch ($provider_id) {
			case 'openai':
				$response = wp_remote_get('https://api.openai.com/v1/models', [
					'headers' => [
						'Authorization' => 'Bearer ' . $api_key,
					],
					'timeout' => 15,
					'sslverify' => true,
				]);
				break;

			case 'claude':
			case 'anthropic':
				$response = wp_remote_get('https://api.anthropic.com/v1/models', [
					'headers' => [
						'x-api-key' => $api_key,
						'anthropic-version' => '2023-06-01',
					],
					'timeout' => 15,
					'sslverify' => true,
				]);
				break;

			case 'gemini':
			case 'google':
				$response = wp_remote_get(
					'https://generativelanguage.googleapis.com/v1beta/models?key=' . rawurlencode($api_key),
					[
						'timeout' => 15,
						'sslverify' => true,
					]
				);
				break;

			case 'deepseek':
				$response = wp_remote_get('https://api.deepseek.com/v1/models', [
					'headers' => [
						'Authorization' => 'Bearer ' . $api_key,
					],
					'timeout' => 15,
					'sslverify' => true,
				]);
				break;

			case 'grok':
			case 'xai':
				$response = wp_remote_get('https://api.x.ai/v1/models', [
					'headers' => [
						'Authorization' => 'Bearer ' . $api_key,
					],
					'timeout' => 15,
					'sslverify' => true,
				]);
				break;

			default:
				return ['error' => sprintf(
					// translators: %s: provider ID.
					__('Provider "%s" does not support key validation.', 'wp-ai-chatbot'),
					esc_html($provider_id)
				)];
		}

		if (is_wp_error($response)) {
			return ['error' => $response->get_error_message()];
		}

		$code = (int) wp_remote_retrieve_response_code($response);

		if (401 === $code || 403 === $code) {
			return ['error' => __('Invalid API key — authentication rejected by the provider.', 'wp-ai-chatbot')];
		}

		if (429 === $code) {
			return ['error' => __('Rate limit reached. The key appears valid but the account is throttled.', 'wp-ai-chatbot')];
		}

		if ($code >= 400) {
			$raw = wp_remote_retrieve_body($response);
			$data = json_decode($raw, true);
			$msg = is_array($data) ? ($data['error']['message'] ?? ('HTTP ' . $code)) : ('HTTP ' . $code);

			return ['error' => $msg];
		}

		return ['success' => true];
	}

	// =========================================================================
	// Phase 10 — Migration REST routes
	// =========================================================================

	/**
	 * Register Migration Assistant REST endpoints.
	 *
	 * All endpoints require `manage_options` capability (admin-only).
	 *
	 * GET  /migration                               — detect all source plugins.
	 * GET  /migration/{source}/mapping              — field mapping table.
	 * POST /migration/{source}/run                  — dry-run or live migration.
	 * POST /migration/{source}/rollback             — restore from backup.
	 *
	 * @return void
	 */
	private function register_migration_rest_routes(): void
	{
		$admin_perm = static function (): bool {
			return current_user_can('manage_options');
		};

		// Detect all source plugins.
		register_rest_route(WPAIC_REST_NAMESPACE, '/migration', [
			'methods' => 'GET',
			'callback' => static function (): \WP_REST_Response {
				return new \WP_REST_Response(
					Migration\Migration_Runner::detect_all(),
					200
				);
			},
			'permission_callback' => $admin_perm,
		]);

		// Mapping table for a specific source.
		register_rest_route(WPAIC_REST_NAMESPACE, '/migration/(?P<source>[a-z]+)/mapping', [
			'methods' => 'GET',
			'callback' => static function (\WP_REST_Request $request): \WP_REST_Response {
				$result = Migration\Migration_Runner::get_mapping(
					sanitize_key($request->get_param('source'))
				);
				$status = $result['success'] ? 200 : 404;

				return new \WP_REST_Response($result, $status);
			},
			'permission_callback' => $admin_perm,
			'args' => [
				'source' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_key',
					'enum' => Migration\Migration_Runner::SOURCES,
				],
			],
		]);

		// Run (dry-run or live) for a specific source.
		register_rest_route(WPAIC_REST_NAMESPACE, '/migration/(?P<source>[a-z]+)/run', [
			'methods' => 'POST',
			'callback' => static function (\WP_REST_Request $request): \WP_REST_Response {
				$dry_run = (bool) $request->get_param('dry_run');
				$overwrite_conflicts = (bool) $request->get_param('overwrite_conflicts');
				$result = Migration\Migration_Runner::run(
					sanitize_key($request->get_param('source')),
					$dry_run,
					$overwrite_conflicts
				);
				$status = $result['success'] ? 200 : 422;

				return new \WP_REST_Response($result, $status);
			},
			'permission_callback' => $admin_perm,
			'args' => [
				'source' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_key',
					'enum' => Migration\Migration_Runner::SOURCES,
				],
				'dry_run' => [
					'required' => false,
					'type' => 'boolean',
					'default' => true,
				],
				'overwrite_conflicts' => [
					'required' => false,
					'type' => 'boolean',
					'default' => false,
				],
			],
		]);

		// Rollback for a specific source.
		register_rest_route(WPAIC_REST_NAMESPACE, '/migration/(?P<source>[a-z]+)/rollback', [
			'methods' => 'POST',
			'callback' => static function (\WP_REST_Request $request): \WP_REST_Response {
				$result = Migration\Migration_Runner::rollback(
					sanitize_key($request->get_param('source'))
				);
				$status = $result['success'] ? 200 : 422;

				return new \WP_REST_Response($result, $status);
			},
			'permission_callback' => $admin_perm,
			'args' => [
				'source' => [
					'required' => true,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_key',
					'enum' => Migration\Migration_Runner::SOURCES,
				],
			],
		]);
	}

	// =========================================================================
	// Transcripts & Usage REST endpoints (Phase 16)
	// =========================================================================

	/**
	 * Register Transcripts & Usage REST endpoints.
	 *
	 * Called from register_provider_rest_routes() when Transcripts_Page is available.
	 * All endpoints require `manage_options` capability.
	 *
	 * GET    /transcripts/sessions           — paginated sessions list with filters
	 * GET    /transcripts/sessions/{id}      — session + full message thread
	 * DELETE /transcripts/sessions/{id}      — delete session and all its messages
	 * GET    /transcripts/usage              — 30-day aggregated usage stats
	 *
	 * @return void
	 */
	private function register_transcripts_rest_routes(): void
	{
		$page = new Admin\Pages\Transcripts_Page();

		$admin_perm = static function (): bool {
			return current_user_can('manage_options');
		};

		// Session list with optional filters.
		register_rest_route(WPAIC_REST_NAMESPACE, '/transcripts/sessions', [
			[
				'methods' => 'GET',
				'callback' => [$page, 'rest_list_sessions'],
				'permission_callback' => $admin_perm,
				'args' => [
					'per_page' => ['required' => false, 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100],
					'page' => ['required' => false, 'type' => 'integer', 'default' => 1, 'minimum' => 1],
					'status' => ['required' => false, 'type' => 'string', 'default' => ''],
					'date_from' => ['required' => false, 'type' => 'string', 'default' => ''],
					'date_to' => ['required' => false, 'type' => 'string', 'default' => ''],
				],
			],
		]);

		// Single session with full message thread.
		register_rest_route(WPAIC_REST_NAMESPACE, '/transcripts/sessions/(?P<id>\d+)', [
			[
				'methods' => 'GET',
				'callback' => [$page, 'rest_get_session'],
				'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
				],
			],
			[
				'methods' => 'DELETE',
				'callback' => [$page, 'rest_delete_session'],
				'permission_callback' => $admin_perm,
				'args' => [
					'id' => ['required' => true, 'type' => 'integer', 'minimum' => 1],
				],
			],
		]);

		// Aggregated usage statistics.
		register_rest_route(WPAIC_REST_NAMESPACE, '/transcripts/usage', [
			[
				'methods' => 'GET',
				'callback' => [$page, 'rest_usage_stats'],
				'permission_callback' => $admin_perm,
			],
		]);
	}
}
