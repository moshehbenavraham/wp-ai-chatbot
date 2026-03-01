<?php

/**
 * Tool Registry — central registry for all registered tool implementations.
 *
 * Singleton, hook-driven: fires `wpaic_register_tools` on first access, passing
 * this registry instance so modules can call register() to add tools.
 *
 * Tool IDs are validated as snake_case (a-z0-9_, 2-64 chars).
 * Hooks: wpaic_register_tools, wpaic_tools_default_enabled.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Tools;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Tool_Registry
 *
 * @since 1.0.0
 * @package WPAIC\Tools
 */
final class Tool_Registry
{
	/** @var Tool_Registry|null */
	private static $instance = null;

	/** @var array<string, Tool_Interface>  id => tool */
	private $tools = [];

	/** @var bool Whether wpaic_register_tools has fired. */
	private $initialized = false;

	/** Private constructor — use instance(). */
	private function __construct()
	{
	}

	/**
	 * Return the singleton instance.
	 *
	 * @return Tool_Registry
	 */
	public static function instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// =========================================================================
	// Registration
	// =========================================================================

	/**
	 * Register a tool.
	 *
	 * @param  Tool_Interface $tool
	 * @return bool True on success, false if ID is invalid or already registered.
	 */
	public function register(Tool_Interface $tool): bool
	{
		$id = $tool->get_id();

		if (! preg_match('/^[a-z0-9_]{2,64}$/', $id)) {
			return false;
		}

		$this->tools[$id] = $tool;

		return true;
	}

	/**
	 * Unregister a tool by ID.
	 *
	 * @param  string $id
	 * @return void
	 */
	public function unregister(string $id): void
	{
		unset($this->tools[$id]);
	}

	// =========================================================================
	// Retrieval
	// =========================================================================

	/**
	 * Retrieve a tool by ID.
	 *
	 * @param  string $id
	 * @return Tool_Interface|null
	 */
	public function get(string $id): ?Tool_Interface
	{
		$this->maybe_init();

		return $this->tools[$id] ?? null;
	}

	/**
	 * Return all registered tools.
	 *
	 * @return array<string, Tool_Interface>
	 */
	public function get_all(): array
	{
		$this->maybe_init();

		return $this->tools;
	}

	// =========================================================================
	// Bot-scoped helpers
	// =========================================================================

	/**
	 * Return provider-native definitions for all tools enabled for a given bot.
	 *
	 * @param  string $bot_slug   Bot slug.
	 * @param  array  $bot_config Bot configuration row.
	 * @return array  Array of provider definitions (name, description, parameters).
	 */
	public function get_definitions_for_bot(string $bot_slug, array $bot_config): array
	{
		$this->maybe_init();
		$enabled_ids = $this->get_enabled_for_bot($bot_slug, $bot_config);
		$defs = [];

		foreach ($enabled_ids as $id) {
			if (isset($this->tools[$id])) {
				$defs[] = $this->tools[$id]->to_provider_definition();
			}
		}

		return $defs;
	}

	/**
	 * Return the IDs of tools enabled for a given bot.
	 *
	 * Per-bot enablement is stored as a JSON array in `bot_config['tools_enabled']`.
	 * If the bot has no explicit tools_enabled config, the `wpaic_tools_default_enabled`
	 * filter controls defaults (empty by default — tools are opt-in per bot).
	 *
	 * @param  string $bot_slug   Bot slug.
	 * @param  array  $bot_config Bot configuration row.
	 * @return array  Validated list of enabled tool IDs.
	 */
	public function get_enabled_for_bot(string $bot_slug, array $bot_config): array
	{
		$this->maybe_init();
		$all_ids = array_keys($this->tools);

		// Per-bot config: JSON array of enabled tool IDs.
		if (! empty($bot_config['tools_enabled'])) {
			$configured = json_decode((string) $bot_config['tools_enabled'], true);
			if (is_array($configured)) {
				// Only return IDs that exist in the registry.
				return array_values(array_intersect(array_map('sanitize_key', $configured), $all_ids));
			}
		}

		/**
		 * Filter: control which tools are enabled for bots without explicit tool config.
		 * Defaults to an empty array (tools are opt-in).
		 *
		 * @since 1.0.0
		 * @param array  $enabled   Enabled tool IDs.
		 * @param string $bot_slug  Bot slug.
		 * @param array  $all_ids   All registered tool IDs.
		 */
		return (array) apply_filters('wpaic_tools_default_enabled', [], $bot_slug, $all_ids);
	}

	// =========================================================================
	// Diagnostics
	// =========================================================================

	/**
	 * Return registry statistics for the health/diagnostics endpoint.
	 *
	 * @return array{count: int, ids: array}
	 */
	public function get_stats(): array
	{
		$this->maybe_init();

		return [
			'count' => count($this->tools),
			'ids' => array_keys($this->tools),
		];
	}

	// =========================================================================
	// Lazy init
	// =========================================================================

	/**
	 * Fire wpaic_register_tools once to allow modules to register tools.
	 *
	 * @return void
	 */
	private function maybe_init(): void
	{
		if ($this->initialized) {
			return;
		}

		$this->initialized = true;

		/**
		 * Fires when the tool registry is accessed for the first time.
		 * Register tools by calling $registry->register($tool).
		 *
		 * @since 1.0.0
		 * @param Tool_Registry $registry The tool registry instance.
		 */
		do_action('wpaic_register_tools', $this);
	}
}
