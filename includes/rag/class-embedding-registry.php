<?php

/**
 * Embedding Registry — manages all registered embedding provider adapters.
 *
 * Singleton registry that collects Embedding_Provider implementations.
 * Providers register themselves by hooking `wpaic_register_embedding_providers`.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Embedding_Registry
 *
 * @since 1.0.0
 * @package WPAIC\Rag
 */
final class Embedding_Registry
{
	/** @var Embedding_Registry|null Singleton instance. */
	private static $instance = null;

	/** @var array<string, Embedding_Provider> Registered providers keyed by ID. */
	private $providers = [];

	/** @var bool Whether the registration hook has fired. */
	private $initialised = false;

	/** Private constructor — use instance(). */
	private function __construct()
	{
	}

	/**
	 * Return (and lazily create) the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register an embedding provider.
	 *
	 * @param  string             $provider_id Machine ID (e.g. 'openai').
	 * @param  Embedding_Provider $provider    Provider instance.
	 * @return void
	 */
	public function register(string $provider_id, Embedding_Provider $provider): void
	{
		$this->providers[$provider_id] = $provider;
	}

	/**
	 * Retrieve a provider by ID. Triggers init on first access.
	 *
	 * @param  string $provider_id Provider machine ID.
	 * @return Embedding_Provider|null
	 */
	public function get(string $provider_id): ?Embedding_Provider
	{
		$this->maybe_init();

		return $this->providers[$provider_id] ?? null;
	}

	/**
	 * Return all registered providers.
	 *
	 * @return array<string, Embedding_Provider>
	 */
	public function all(): array
	{
		$this->maybe_init();

		return $this->providers;
	}

	// =========================================================================
	// Internal
	// =========================================================================

	/**
	 * Fire the registration hook on first access.
	 *
	 * @return void
	 */
	private function maybe_init(): void
	{
		if ($this->initialised) {
			return;
		}

		$this->initialised = true;

		/**
		 * Register embedding provider adapters.
		 *
		 * @since 1.0.0
		 * @param Embedding_Registry $registry Registry instance.
		 */
		do_action('wpaic_register_embedding_providers', $this);
	}
}
