<?php

/**
 * Content Source Registry — manages all registered RAG content sources.
 *
 * Singleton registry that collects Content_Source implementations.
 * Sources register themselves by hooking `wpaic_register_content_sources`.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Content_Source_Registry
 *
 * @since 1.0.0
 * @package WPAIC\Rag
 */
final class Content_Source_Registry
{
	/** @var Content_Source_Registry|null Singleton instance. */
	private static $instance = null;

	/** @var array<string, Content_Source> Registered sources keyed by ID. */
	private $sources = [];

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
	 * Register a content source adapter.
	 *
	 * @param  string         $source_id Source machine ID.
	 * @param  Content_Source $source    Adapter instance.
	 * @return void
	 */
	public function register(string $source_id, Content_Source $source): void
	{
		$this->sources[$source_id] = $source;
	}

	/**
	 * Resolve a source by ID. Triggers init on first access.
	 *
	 * @param  string $source_id Source ID.
	 * @return Content_Source|null
	 */
	public function get(string $source_id): ?Content_Source
	{
		$this->maybe_init();

		return $this->sources[$source_id] ?? null;
	}

	/**
	 * Return all registered sources.
	 *
	 * @return array<string, Content_Source>
	 */
	public function all(): array
	{
		$this->maybe_init();

		return $this->sources;
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
		 * Register RAG content source adapters.
		 *
		 * @since 1.0.0
		 * @param Content_Source_Registry $registry Registry instance.
		 */
		do_action('wpaic_register_content_sources', $this);
	}
}
