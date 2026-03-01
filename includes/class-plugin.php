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

		// Register custom cron interval before any event is scheduled.
		add_filter('cron_schedules', [$this, 'add_cron_schedules']);

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
		// Phase 05: Chatbot assets enqueued based on display configuration.
		if (! $this->should_display_chatbot()) {
			return;
		}

		wp_enqueue_style(
			'wpaic-chatbot',
			WPAIC_ASSETS_URL . 'css/chatbot.css',
			[],
			WPAIC_VERSION
		);

		wp_enqueue_script(
			'wpaic-chatbot',
			WPAIC_ASSETS_URL . 'js/chatbot.js',
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
	// Shortcode rendering
	// =========================================================================

	/**
	 * Render the [wpaic_chatbot] shortcode.
	 *
	 * @param  array  $atts    Shortcode attributes.
	 * @param  string $content Shortcode content (unused).
	 * @return string HTML output.
	 */
	public function render_chatbot_shortcode(array $atts = [], string $content = ''): string
	{
		// Phase 05: full shortcode rendering with attribute support.
		$template = WPAIC_PLUGIN_DIR . 'templates/chatbot-widget.php';
		if (! file_exists($template)) {
			return '';
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
	 * Returns true when: the chatbot is configured for global display, OR the
	 * current page is in the allowed pages list, OR a shortcode is present.
	 * Phase 05 replaces this stub with a full configuration-driven check.
	 *
	 * @return bool
	 */
	private function should_display_chatbot(): bool
	{
		// Stub: always display when assets exist (overridden in Phase 05).
		return file_exists(WPAIC_PLUGIN_DIR . 'assets/js/chatbot.js');
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
			'streamEnabled' => false, // Phase 05: driven by wpaic_runtime_stream_enabled option.
			'version' => WPAIC_VERSION,
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
	 * @return void
	 */
	public function register_provider_rest_routes(): void
	{
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
}
