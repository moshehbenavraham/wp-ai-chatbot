<?php

/**
 * Provider_Registry — singleton factory for all AI provider adapters.
 *
 * Manages registration, instantiation, alias resolution, instance caching,
 * and enable/disable toggling. All provider access flows through this registry.
 *
 * Design adopted from AxiaChat's AIChat_Provider_Registry with refinements.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Provider;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Provider_Registry
 *
 * Usage:
 *   $registry = Provider_Registry::instance();
 *   $adapter  = $registry->get( 'openai' );
 *   $response = $adapter->chat( $messages, $params );
 *
 * @since 1.0.0
 * @package WPAIC\Provider
 */
final class Provider_Registry
{
	/** @var Provider_Registry|null Singleton instance. */
	private static $instance = null;

	/**
	 * Registered provider definitions.
	 * Key: provider ID. Value: ['class_name' => string, 'enabled' => bool].
	 *
	 * @var array<string, array{class_name: string, enabled: bool}>
	 */
	private $providers = [];

	/**
	 * Cached adapter instances.
	 * Key: cache key (provider_id + config hash). Value: Provider_Interface instance.
	 *
	 * @var array<string, Provider_Interface>
	 */
	private $cache = [];

	/**
	 * Alias normalisation map (alias => canonical ID).
	 *
	 * @var array<string, string>
	 */
	private $aliases = [
		'anthropic' => 'claude',
		'google' => 'gemini',
		'xai' => 'grok',
	];

	/** @var bool Whether the registration hook has fired. */
	private $initialized = false;

	// =========================================================================
	// Singleton
	// =========================================================================

	/**
	 * Get the singleton instance.
	 *
	 * @return Provider_Registry
	 */
	public static function instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/** Private constructor — use instance(). */
	private function __construct()
	{
	}

	// =========================================================================
	// Registration API
	// =========================================================================

	/**
	 * Register a provider adapter class.
	 *
	 * Validates all compliance rules (CR-01 through CR-05) and fires the
	 * `wpaic_provider_registered` action on success.
	 *
	 * @param  string $id         Canonical provider ID (lowercase, [a-z0-9-]).
	 * @param  string $class_name Fully-qualified class implementing Provider_Interface.
	 * @param  bool   $enabled    Whether initially enabled (default: true).
	 * @param  bool   $force      Force re-registration if ID already exists.
	 * @return bool               True if registered; false if rejected.
	 */
	public function register($id, $class_name, $enabled = true, $force = false): bool
	{
		// CR-04: ID must match [a-z0-9-]+.
		if ('' === $id || ! preg_match('/^[a-z0-9\-]+$/', $id)) {
			_doing_it_wrong(
				__METHOD__,
				esc_html(sprintf(
					'Provider ID "%s" is invalid. Only lowercase letters, digits, and hyphens are allowed.',
					$id
				)),
				'1.0.0'
			);

			return false;
		}

		// CR-01: Class must exist and implement Provider_Interface.
		if (! class_exists($class_name)) {
			_doing_it_wrong(
				__METHOD__,
				esc_html(sprintf('Provider class "%s" does not exist.', $class_name)),
				'1.0.0'
			);

			return false;
		}

		if (! is_subclass_of($class_name, Provider_Interface::class)) {
			_doing_it_wrong(
				__METHOD__,
				esc_html(sprintf(
					'Provider class "%s" must implement %s.',
					$class_name,
					Provider_Interface::class
				)),
				'1.0.0'
			);

			return false;
		}

		// CR-05: No duplicate IDs unless $force = true.
		if (isset($this->providers[$id]) && ! $force) {
			_doing_it_wrong(
				__METHOD__,
				esc_html(sprintf('Provider ID "%s" is already registered. Use $force=true to override.', $id)),
				'1.0.0'
			);

			return false;
		}

		$this->providers[$id] = [
			'class_name' => $class_name,
			'enabled' => (bool) $enabled,
		];

		/**
		 * Fires after a provider is successfully registered.
		 *
		 * @param string $id         Canonical provider ID.
		 * @param string $class_name Fully-qualified class name.
		 */
		do_action('wpaic_provider_registered', $id, $class_name);

		return true;
	}

	// =========================================================================
	// Retrieval API
	// =========================================================================

	/**
	 * Get a provider adapter instance.
	 *
	 * Returns a cached instance when $cached = true and config matches.
	 * Config is resolved from wp_options via Settings_Manager if not provided.
	 *
	 * @param  string $id     Provider ID (aliases resolved automatically).
	 * @param  array  $config Optional config override.
	 * @param  bool   $cached Whether to use instance cache (default: true).
	 * @return Provider_Interface|null Adapter instance, or null if unavailable.
	 */
	public function get($id, $config = [], $cached = true): ?Provider_Interface
	{
		$this->ensure_initialized();

		$id = $this->resolve_alias($id);

		if (! isset($this->providers[$id]) || ! $this->providers[$id]['enabled']) {
			return null;
		}

		// Resolve config from Settings_Manager if not provided.
		if (empty($config)) {
			$config = $this->resolve_config($id);
		}

		// Apply provider-specific config filter.
		$config = (array) apply_filters("wpaic_provider_{$id}_config", $config);

		$cache_key = $id . '_' . md5((string) wp_json_encode($config));

		if ($cached && isset($this->cache[$cache_key])) {
			return $this->cache[$cache_key];
		}

		$class_name = $this->providers[$id]['class_name'];
		$adapter = new $class_name($config);

		// CR-03: Verify get_id() matches the registered ID.
		if ($adapter->get_id() !== $id) {
			if (function_exists('wpaic_structured_log')) {
				wpaic_structured_log('error', 'Provider ID mismatch', [
					'expected' => $id,
					'returned' => $adapter->get_id(),
					'class' => $class_name,
				], 'provider');
			}
			// Remove bad cache entry if it exists.
			unset($this->cache[$cache_key]);

			return null;
		}

		$this->cache[$cache_key] = $adapter;

		return $adapter;
	}

	/**
	 * Check if a provider is registered, enabled, and has a valid API key.
	 *
	 * @param  string $id Provider ID (aliases resolved).
	 * @return bool       True if the provider can be used.
	 */
	public function is_available($id): bool
	{
		$this->ensure_initialized();

		$id = $this->resolve_alias($id);

		if (! isset($this->providers[$id]) || ! $this->providers[$id]['enabled']) {
			return false;
		}

		// Check API key via Settings_Manager if available.
		if (class_exists('WPAIC\Admin\Settings_Manager')) {
			$key = \WPAIC\Admin\Settings_Manager::get("wpaic_provider_{$id}_key", '');

			return '' !== (string) $key;
		}

		// Fallback: check raw wp_options (during early bootstrap).
		$key = get_option("wpaic_provider_{$id}_key", '');

		return '' !== (string) $key;
	}

	/**
	 * List all registered providers with status information.
	 *
	 * @param  bool $enabled_only Only return enabled providers (default: false).
	 * @return array<string, array{id: string, class_name: string, enabled: bool, available: bool, streamable: bool}>
	 */
	public function list_providers($enabled_only = false): array
	{
		$this->ensure_initialized();

		$result = [];

		foreach ($this->providers as $id => $def) {
			if ($enabled_only && ! $def['enabled']) {
				continue;
			}

			$result[$id] = [
				'id' => $id,
				'class_name' => $def['class_name'],
				'enabled' => $def['enabled'],
				'available' => $this->is_available($id),
				'streamable' => is_a($def['class_name'], Streamable_Provider_Interface::class, true),
			];
		}

		return $result;
	}

	// =========================================================================
	// Cache management
	// =========================================================================

	/**
	 * Clear cached adapter instances.
	 *
	 * @param  string|null $id Provider ID to clear, or null to clear all.
	 * @return void
	 */
	public function clear_cache($id = null): void
	{
		if (null === $id) {
			$this->cache = [];

			return;
		}

		$id = $this->resolve_alias($id);
		$prefix = $id . '_';

		foreach (array_keys($this->cache) as $key) {
			if (strpos($key, $prefix) === 0) {
				unset($this->cache[$key]);
			}
		}
	}

	// =========================================================================
	// Enable / disable
	// =========================================================================

	/**
	 * Enable or disable a registered provider at runtime.
	 *
	 * State is runtime-only (not persisted to wp_options).
	 *
	 * @param  string $id      Provider ID.
	 * @param  bool   $enabled Whether to enable or disable.
	 * @return bool            True if state changed; false if provider not found.
	 */
	public function set_enabled($id, $enabled): bool
	{
		$id = $this->resolve_alias($id);

		if (! isset($this->providers[$id])) {
			return false;
		}

		$this->providers[$id]['enabled'] = (bool) $enabled;

		return true;
	}

	// =========================================================================
	// Statistics
	// =========================================================================

	/**
	 * Return registry statistics for health endpoint and diagnostics panel.
	 *
	 * @return array{total_registered: int, total_enabled: int, total_available: int, cache_size: int, providers: array}
	 */
	public function get_stats(): array
	{
		$this->ensure_initialized();

		$providers = $this->list_providers();
		$enabled = array_filter($providers, static fn ($p) => $p['enabled']);
		$available = array_filter($providers, static fn ($p) => $p['available']);

		return [
			'total_registered' => count($providers),
			'total_enabled' => count($enabled),
			'total_available' => count($available),
			'cache_size' => count($this->cache),
			'providers' => $providers,
		];
	}

	// =========================================================================
	// Lazy initialisation
	// =========================================================================

	/**
	 * Ensure the registration hook has been fired.
	 * Fires once on first registry access (lazy init pattern).
	 *
	 * @return void
	 */
	private function ensure_initialized(): void
	{
		if ($this->initialized) {
			return;
		}

		$this->initialized = true;

		// Allow alias map extensions before providers register.
		$this->aliases = (array) apply_filters('wpaic_provider_aliases', $this->aliases);

		/**
		 * Fires when the provider registry is first accessed.
		 * Provider adapters register themselves here.
		 *
		 * @param Provider_Registry $registry The registry instance.
		 */
		do_action('wpaic_register_providers', $this);
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	/**
	 * Resolve a provider alias to its canonical ID.
	 *
	 * @param  string $id Input ID (may be an alias).
	 * @return string     Canonical ID.
	 */
	private function resolve_alias(string $id): string
	{
		return $this->aliases[$id] ?? $id;
	}

	/**
	 * Resolve provider configuration from Settings_Manager or raw wp_options.
	 *
	 * @param  string $id Canonical provider ID.
	 * @return array      Config array ready for adapter constructor.
	 */
	private function resolve_config(string $id): array
	{
		if (class_exists('WPAIC\Admin\Settings_Manager')) {
			$sm = \WPAIC\Admin\Settings_Manager::get_instance();

			return [
				'api_key' => (string) $sm->get("wpaic_provider_{$id}_key", ''),
				'endpoint' => (string) $sm->get("wpaic_provider_{$id}_endpoint", ''),
				'timeout' => (int) $sm->get('wpaic_provider_timeout', 30),
				'max_retries' => (int) $sm->get('wpaic_provider_max_retries', 3),
			];
		}

		// Fallback during early bootstrap (Settings_Manager not yet loaded).
		return [
			'api_key' => (string) get_option("wpaic_provider_{$id}_key", ''),
			'endpoint' => (string) get_option("wpaic_provider_{$id}_endpoint", ''),
			'timeout' => (int) get_option('wpaic_provider_timeout', 30),
			'max_retries' => (int) get_option('wpaic_provider_max_retries', 3),
		];
	}
}
