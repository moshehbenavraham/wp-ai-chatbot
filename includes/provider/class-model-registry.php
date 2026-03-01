<?php

/**
 * Model_Registry — centralised catalogue of all supported AI models.
 *
 * Provides per-provider model lists with pricing, capability flags,
 * deprecation metadata, and automatic successor resolution. The registry
 * is the single source of truth for:
 *   - Which model IDs are valid for each provider.
 *   - Canonical pricing for cost accounting.
 *   - Deprecation status and the recommended successor model.
 *   - Capability flags that gate UI features (streaming, tools, vision, etc.).
 *
 * Extension hooks:
 *   `wpaic_model_registry_models` — filter the full model catalogue (add/remove/modify entries).
 *   `wpaic_model_registry_defaults` — filter default model IDs per provider.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Provider;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Model_Registry
 *
 * Usage:
 *   $registry = Model_Registry::instance();
 *   $model    = $registry->get( 'claude-haiku-4-5-20251001' );
 *   $models   = $registry->get_models_for_provider( 'openai' );
 *   $default  = $registry->get_default_model( 'gemini' );
 *   $resolved = $registry->resolve_model( 'gpt-4', 'openai' );
 *
 * @since 1.0.0
 * @package WPAIC\Provider
 */
final class Model_Registry
{
	/** @var Model_Registry|null Singleton instance. */
	private static $instance = null;

	/** @var bool Whether the catalogue has been built and filtered. */
	private $initialized = false;

	/**
	 * Full model catalogue.
	 * Key: canonical model ID.
	 * Value: model descriptor array (see Model_Entry shape below).
	 *
	 * Model_Entry shape:
	 * @type string   $id               Canonical model ID (never changes once published).
	 * @type string   $provider         Canonical provider ID ('openai'|'claude'|'gemini'|'deepseek'|'grok').
	 * @type string   $display_name     Human-friendly label shown in admin UI.
	 * @type int      $context_window   Maximum context length in tokens.
	 * @type int      $max_output       Maximum output tokens this model supports.
	 * @type float    $input_price      USD per 1M input tokens (0 for free-tier models).
	 * @type float    $output_price     USD per 1M output tokens (0 for free-tier models).
	 * @type bool     $deprecated       True if this model should no longer be selected.
	 * @type string   $successor        Canonical ID of the recommended replacement (or '').
	 * @type string[] $capabilities     Subset of: 'chat', 'streaming', 'tools', 'vision', 'json_mode', 'thinking'.
	 * @type string   $release_date     YYYY-MM-DD string (informational only).
	 *
	 * @var array<string, array>
	 */
	private $models = [];

	/**
	 * Default model ID per provider.
	 *
	 * @var array<string, string>
	 */
	private $defaults = [
		'openai' => 'gpt-4o-mini',
		'claude' => 'claude-haiku-4-5-20251001',
		'gemini' => 'gemini-2.5-flash',
		'deepseek' => 'deepseek-chat',
		'grok' => 'grok-beta',
	];

	// =========================================================================
	// Singleton
	// =========================================================================

	/**
	 * Get the singleton instance.
	 *
	 * @return Model_Registry
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
	// Lookup API
	// =========================================================================

	/**
	 * Get a single model descriptor by ID.
	 *
	 * @param  string $model_id Canonical model ID (exact match).
	 * @return array|null       Model descriptor array, or null if not found.
	 */
	public function get(string $model_id): ?array
	{
		$this->ensure_initialized();

		return $this->models[$model_id] ?? null;
	}

	/**
	 * Get all models for a specific provider.
	 *
	 * @param  string $provider_id Canonical provider ID.
	 * @param  bool   $active_only Exclude deprecated models when true (default: false).
	 * @return array<string, array> Model descriptor map keyed by model ID.
	 */
	public function get_models_for_provider(string $provider_id, bool $active_only = false): array
	{
		$this->ensure_initialized();

		$result = [];

		foreach ($this->models as $id => $model) {
			if ($model['provider'] !== $provider_id) {
				continue;
			}

			if ($active_only && $model['deprecated']) {
				continue;
			}

			$result[$id] = $model;
		}

		return $result;
	}

	/**
	 * Get the default model ID for a provider.
	 *
	 * @param  string $provider_id Canonical provider ID.
	 * @return string              Default model ID, or empty string if provider unknown.
	 */
	public function get_default_model(string $provider_id): string
	{
		$this->ensure_initialized();

		return $this->defaults[$provider_id] ?? '';
	}

	/**
	 * Resolve a potentially-deprecated model ID to the current recommended ID.
	 *
	 * If the given model is deprecated and has a successor, returns the
	 * successor's ID. If the successor is also deprecated, follows the chain
	 * (up to 10 hops to prevent infinite loops). Returns the original ID if
	 * the model is unknown — callers should not assume resolution succeeds.
	 *
	 * @param  string $model_id    The model ID to resolve.
	 * @param  string $provider_id Optional provider context (for logging only).
	 * @return string              Resolved (current) model ID.
	 */
	public function resolve_model(string $model_id, string $provider_id = ''): string
	{
		$this->ensure_initialized();

		$visited = [];
		$current = $model_id;
		$hops = 0;

		while ($hops < 10) {
			$descriptor = $this->models[$current] ?? null;

			if (null === $descriptor) {
				// Unknown model — return as-is, let the provider API reject it.
				break;
			}

			if (! $descriptor['deprecated'] || '' === $descriptor['successor']) {
				break;
			}

			if (isset($visited[$current])) {
				// Circular successor chain — stop.
				break;
			}

			$visited[$current] = true;
			$current = $descriptor['successor'];
			$hops++;
		}

		if ($current !== $model_id && function_exists('wpaic_structured_log')) {
			wpaic_structured_log('info', 'Model auto-migrated', [
				'original' => $model_id,
				'resolved' => $current,
				'provider' => $provider_id,
				'hops' => $hops,
			], 'provider');
		}

		return $current;
	}

	/**
	 * Check if a model is deprecated.
	 *
	 * @param  string $model_id Canonical model ID.
	 * @return bool             True if deprecated; false if active or unknown.
	 */
	public function is_deprecated(string $model_id): bool
	{
		$this->ensure_initialized();

		return (bool) ($this->models[$model_id]['deprecated'] ?? false);
	}

	/**
	 * Check if a model has a specific capability.
	 *
	 * @param  string $model_id   Canonical model ID.
	 * @param  string $capability Capability slug (e.g. 'streaming', 'tools', 'vision').
	 * @return bool               True if the capability is listed for this model.
	 */
	public function has_capability(string $model_id, string $capability): bool
	{
		$this->ensure_initialized();

		$model = $this->models[$model_id] ?? null;
		if (null === $model) {
			return false;
		}

		return in_array($capability, $model['capabilities'], true);
	}

	/**
	 * Return provider IDs that have at least one non-deprecated model.
	 *
	 * @return string[] Canonical provider IDs.
	 */
	public function list_providers(): array
	{
		$this->ensure_initialized();

		$providers = [];
		foreach ($this->models as $model) {
			if (! $model['deprecated']) {
				$providers[$model['provider']] = true;
			}
		}

		return array_keys($providers);
	}

	// =========================================================================
	// Lazy initialisation
	// =========================================================================

	/**
	 * Build the model catalogue on first access.
	 *
	 * @return void
	 */
	private function ensure_initialized(): void
	{
		if ($this->initialized) {
			return;
		}

		$this->initialized = true;
		$catalogue = $this->default_catalogue();

		/**
		 * Filter the full model catalogue before it is used.
		 *
		 * Third-party code can add, remove, or modify model descriptors here.
		 * Keys are canonical model IDs; values are model descriptor arrays.
		 *
		 * @param array<string, array> $catalogue Model descriptor map.
		 */
		$this->models = (array) apply_filters('wpaic_model_registry_models', $catalogue);

		/**
		 * Filter the default model IDs per provider.
		 *
		 * @param array<string, string> $defaults Map of provider_id => model_id.
		 */
		$this->defaults = (array) apply_filters('wpaic_model_registry_defaults', $this->defaults);
	}

	// =========================================================================
	// Catalogue definition
	// =========================================================================

	/**
	 * Build the built-in model catalogue.
	 *
	 * This catalogue reflects the plugin's best knowledge of current models as
	 * of the release date. Extension code should add new models via the filter.
	 *
	 * @return array<string, array>
	 */
	private function default_catalogue(): array
	{
		// Capability flag aliases for readability.
		$std = ['chat', 'streaming'];
		$tool = ['chat', 'streaming', 'tools'];
		$adv = ['chat', 'streaming', 'tools', 'vision', 'json_mode'];

		return [

			// ===================================================================
			// OpenAI
			// ===================================================================

			'gpt-4o' => [
				'id' => 'gpt-4o',
				'provider' => 'openai',
				'display_name' => 'GPT-4o',
				'context_window' => 128000,
				'max_output' => 16384,
				'input_price' => 2.50,
				'output_price' => 10.00,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $adv,
				'release_date' => '2024-05-13',
			],
			'gpt-4o-mini' => [
				'id' => 'gpt-4o-mini',
				'provider' => 'openai',
				'display_name' => 'GPT-4o mini',
				'context_window' => 128000,
				'max_output' => 16384,
				'input_price' => 0.15,
				'output_price' => 0.60,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $adv,
				'release_date' => '2024-07-18',
			],
			'gpt-4-turbo' => [
				'id' => 'gpt-4-turbo',
				'provider' => 'openai',
				'display_name' => 'GPT-4 Turbo',
				'context_window' => 128000,
				'max_output' => 4096,
				'input_price' => 10.00,
				'output_price' => 30.00,
				'deprecated' => true,
				'successor' => 'gpt-4o',
				'capabilities' => $adv,
				'release_date' => '2024-04-09',
			],
			'gpt-4' => [
				'id' => 'gpt-4',
				'provider' => 'openai',
				'display_name' => 'GPT-4',
				'context_window' => 8192,
				'max_output' => 8192,
				'input_price' => 30.00,
				'output_price' => 60.00,
				'deprecated' => true,
				'successor' => 'gpt-4o',
				'capabilities' => $tool,
				'release_date' => '2023-03-14',
			],
			'gpt-3.5-turbo' => [
				'id' => 'gpt-3.5-turbo',
				'provider' => 'openai',
				'display_name' => 'GPT-3.5 Turbo',
				'context_window' => 16385,
				'max_output' => 4096,
				'input_price' => 0.50,
				'output_price' => 1.50,
				'deprecated' => true,
				'successor' => 'gpt-4o-mini',
				'capabilities' => $std,
				'release_date' => '2022-11-30',
			],
			'o1' => [
				'id' => 'o1',
				'provider' => 'openai',
				'display_name' => 'o1',
				'context_window' => 200000,
				'max_output' => 100000,
				'input_price' => 15.00,
				'output_price' => 60.00,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => ['chat', 'tools', 'thinking'],
				'release_date' => '2024-09-12',
			],
			'o1-mini' => [
				'id' => 'o1-mini',
				'provider' => 'openai',
				'display_name' => 'o1-mini',
				'context_window' => 128000,
				'max_output' => 65536,
				'input_price' => 3.00,
				'output_price' => 12.00,
				'deprecated' => true,
				'successor' => 'o3-mini',
				'capabilities' => ['chat', 'thinking'],
				'release_date' => '2024-09-12',
			],
			'o3-mini' => [
				'id' => 'o3-mini',
				'provider' => 'openai',
				'display_name' => 'o3-mini',
				'context_window' => 200000,
				'max_output' => 100000,
				'input_price' => 1.10,
				'output_price' => 4.40,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => ['chat', 'tools', 'thinking'],
				'release_date' => '2025-01-31',
			],

			// ===================================================================
			// Anthropic / Claude
			// ===================================================================

			'claude-opus-4-6' => [
				'id' => 'claude-opus-4-6',
				'provider' => 'claude',
				'display_name' => 'Claude Opus 4.6',
				'context_window' => 200000,
				'max_output' => 32768,
				'input_price' => 15.00,
				'output_price' => 75.00,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $adv,
				'release_date' => '2025-03-01',
			],
			'claude-sonnet-4-6' => [
				'id' => 'claude-sonnet-4-6',
				'provider' => 'claude',
				'display_name' => 'Claude Sonnet 4.6',
				'context_window' => 200000,
				'max_output' => 16384,
				'input_price' => 3.00,
				'output_price' => 15.00,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $adv,
				'release_date' => '2025-03-01',
			],
			'claude-haiku-4-5-20251001' => [
				'id' => 'claude-haiku-4-5-20251001',
				'provider' => 'claude',
				'display_name' => 'Claude Haiku 4.5',
				'context_window' => 200000,
				'max_output' => 8192,
				'input_price' => 0.80,
				'output_price' => 4.00,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $adv,
				'release_date' => '2025-10-01',
			],
			'claude-3-5-sonnet-20241022' => [
				'id' => 'claude-3-5-sonnet-20241022',
				'provider' => 'claude',
				'display_name' => 'Claude 3.5 Sonnet',
				'context_window' => 200000,
				'max_output' => 8192,
				'input_price' => 3.00,
				'output_price' => 15.00,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $adv,
				'release_date' => '2024-10-22',
			],
			'claude-3-5-haiku-20241022' => [
				'id' => 'claude-3-5-haiku-20241022',
				'provider' => 'claude',
				'display_name' => 'Claude 3.5 Haiku',
				'context_window' => 200000,
				'max_output' => 8192,
				'input_price' => 0.80,
				'output_price' => 4.00,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $adv,
				'release_date' => '2024-10-22',
			],
			'claude-3-opus-20240229' => [
				'id' => 'claude-3-opus-20240229',
				'provider' => 'claude',
				'display_name' => 'Claude 3 Opus',
				'context_window' => 200000,
				'max_output' => 4096,
				'input_price' => 15.00,
				'output_price' => 75.00,
				'deprecated' => true,
				'successor' => 'claude-opus-4-6',
				'capabilities' => $tool,
				'release_date' => '2024-02-29',
			],
			'claude-3-sonnet-20240229' => [
				'id' => 'claude-3-sonnet-20240229',
				'provider' => 'claude',
				'display_name' => 'Claude 3 Sonnet',
				'context_window' => 200000,
				'max_output' => 4096,
				'input_price' => 3.00,
				'output_price' => 15.00,
				'deprecated' => true,
				'successor' => 'claude-3-5-sonnet-20241022',
				'capabilities' => $tool,
				'release_date' => '2024-02-29',
			],
			'claude-3-haiku-20240307' => [
				'id' => 'claude-3-haiku-20240307',
				'provider' => 'claude',
				'display_name' => 'Claude 3 Haiku',
				'context_window' => 200000,
				'max_output' => 4096,
				'input_price' => 0.25,
				'output_price' => 1.25,
				'deprecated' => true,
				'successor' => 'claude-haiku-4-5-20251001',
				'capabilities' => $std,
				'release_date' => '2024-03-07',
			],

			// ===================================================================
			// Google / Gemini
			// ===================================================================

			'gemini-2.5-pro' => [
				'id' => 'gemini-2.5-pro',
				'provider' => 'gemini',
				'display_name' => 'Gemini 2.5 Pro',
				'context_window' => 1000000,
				'max_output' => 65536,
				'input_price' => 1.25,
				'output_price' => 10.00,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => array_merge($adv, ['thinking']),
				'release_date' => '2025-03-25',
			],
			'gemini-2.5-flash' => [
				'id' => 'gemini-2.5-flash',
				'provider' => 'gemini',
				'display_name' => 'Gemini 2.5 Flash',
				'context_window' => 1000000,
				'max_output' => 65536,
				'input_price' => 0.15,
				'output_price' => 0.60,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $adv,
				'release_date' => '2025-05-20',
			],
			'gemini-2.5-flash-lite' => [
				'id' => 'gemini-2.5-flash-lite',
				'provider' => 'gemini',
				'display_name' => 'Gemini 2.5 Flash Lite',
				'context_window' => 1000000,
				'max_output' => 65536,
				'input_price' => 0.10,
				'output_price' => 0.40,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $std,
				'release_date' => '2025-05-20',
			],
			'gemini-2.0-flash' => [
				'id' => 'gemini-2.0-flash',
				'provider' => 'gemini',
				'display_name' => 'Gemini 2.0 Flash',
				'context_window' => 1000000,
				'max_output' => 8192,
				'input_price' => 0.10,
				'output_price' => 0.40,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $adv,
				'release_date' => '2025-02-05',
			],
			'gemini-2.0-flash-lite' => [
				'id' => 'gemini-2.0-flash-lite',
				'provider' => 'gemini',
				'display_name' => 'Gemini 2.0 Flash Lite',
				'context_window' => 1000000,
				'max_output' => 8192,
				'input_price' => 0.075,
				'output_price' => 0.30,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $std,
				'release_date' => '2025-02-05',
			],
			'gemini-1.5-pro' => [
				'id' => 'gemini-1.5-pro',
				'provider' => 'gemini',
				'display_name' => 'Gemini 1.5 Pro',
				'context_window' => 2000000,
				'max_output' => 8192,
				'input_price' => 1.25,
				'output_price' => 5.00,
				'deprecated' => true,
				'successor' => 'gemini-2.5-pro',
				'capabilities' => $adv,
				'release_date' => '2024-05-24',
			],
			'gemini-1.5-flash' => [
				'id' => 'gemini-1.5-flash',
				'provider' => 'gemini',
				'display_name' => 'Gemini 1.5 Flash',
				'context_window' => 1000000,
				'max_output' => 8192,
				'input_price' => 0.075,
				'output_price' => 0.30,
				'deprecated' => true,
				'successor' => 'gemini-2.0-flash',
				'capabilities' => $std,
				'release_date' => '2024-05-24',
			],

			// ===================================================================
			// DeepSeek (provider ID: 'deepseek') — stub entries for Phase 04.
			// Full adapter is deferred; registry entries allow admin UI display.
			// ===================================================================

			'deepseek-chat' => [
				'id' => 'deepseek-chat',
				'provider' => 'deepseek',
				'display_name' => 'DeepSeek Chat (V3)',
				'context_window' => 64000,
				'max_output' => 8192,
				'input_price' => 0.27,
				'output_price' => 1.10,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $tool,
				'release_date' => '2024-12-26',
			],
			'deepseek-reasoner' => [
				'id' => 'deepseek-reasoner',
				'provider' => 'deepseek',
				'display_name' => 'DeepSeek Reasoner (R1)',
				'context_window' => 64000,
				'max_output' => 8192,
				'input_price' => 0.55,
				'output_price' => 2.19,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => ['chat', 'thinking'],
				'release_date' => '2025-01-20',
			],

			// ===================================================================
			// xAI / Grok (provider ID: 'grok') — stub entries for Phase 04.
			// ===================================================================

			'grok-beta' => [
				'id' => 'grok-beta',
				'provider' => 'grok',
				'display_name' => 'Grok Beta',
				'context_window' => 131072,
				'max_output' => 4096,
				'input_price' => 5.00,
				'output_price' => 15.00,
				'deprecated' => true,
				'successor' => 'grok-3',
				'capabilities' => $std,
				'release_date' => '2024-11-04',
			],
			'grok-3' => [
				'id' => 'grok-3',
				'provider' => 'grok',
				'display_name' => 'Grok 3',
				'context_window' => 131072,
				'max_output' => 8192,
				'input_price' => 3.00,
				'output_price' => 15.00,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => $adv,
				'release_date' => '2025-02-17',
			],
			'grok-3-mini' => [
				'id' => 'grok-3-mini',
				'provider' => 'grok',
				'display_name' => 'Grok 3 Mini',
				'context_window' => 131072,
				'max_output' => 8192,
				'input_price' => 0.30,
				'output_price' => 0.50,
				'deprecated' => false,
				'successor' => '',
				'capabilities' => ['chat', 'streaming', 'thinking'],
				'release_date' => '2025-02-17',
			],
		];
	}
}
