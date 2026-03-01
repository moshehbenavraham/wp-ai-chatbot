<?php

/**
 * Baachal migration source adapter.
 *
 * Reads baachal_* options and maps them to wpaic_* equivalents.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Migration;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Baachal_Migrator
 *
 * Detects and migrates settings from the Baachal AI Chatbot (baachal_* options).
 *
 * @since 1.0.0
 * @package WPAIC\Migration
 */
class Baachal_Migrator
{
	/** Source plugin identifier. */
	public const SOURCE_ID = 'baachal';

	/** Canary option that confirms Baachal data is present. */
	public const CANARY_OPTION = 'baachal_ai_provider';

	// =========================================================================
	// Detection
	// =========================================================================

	/**
	 * Whether Baachal plugin data appears to be present in the database.
	 *
	 * @return bool
	 */
	public static function detect(): bool
	{
		return get_option(self::CANARY_OPTION, null) !== null
			|| get_option('baachal_enabled', null) !== null
			|| get_option('baachal_ai_model', null) !== null;
	}

	// =========================================================================
	// Mapping
	// =========================================================================

	/**
	 * Return the full migration mapping table with runtime values.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_mapping(): array
	{
		$result = [];

		foreach (self::raw_mappings() as $m) {
			$source_val = get_option($m['source_option'], null);

			// Apply value transformation for display.
			$display_source = isset($m['transform'])
				? call_user_func([__CLASS__, $m['transform']], $source_val)
				: $source_val;

			$target_val = get_option($m['target_option'], null);

			$has_source = (null !== $source_val && '' !== $source_val);
			$has_target = (null !== $target_val && '' !== $target_val);
			$conflict = $has_source && $has_target && ((string) $source_val !== (string) $target_val);

			$result[] = array_merge($m, [
				'source_value' => $m['sensitive'] ? self::mask((string) $source_val) : $display_source,
				'target_value' => $m['sensitive'] ? self::mask((string) $target_val) : $target_val,
				'has_source' => $has_source,
				'has_target' => $has_target,
				'conflict' => $conflict,
				'would_migrate' => $has_source,
			]);
		}

		return $result;
	}

	// =========================================================================
	// Migration
	// =========================================================================

	/**
	 * Migrate Baachal settings to WPAIC options.
	 *
	 * @param  bool $dry_run             Build plan only, no writes.
	 * @param  bool $overwrite_conflicts Overwrite existing WPAIC values on conflict.
	 * @return array{success: bool, dry_run: bool, applied: int, skipped: int, conflicts: int, items: array<int, array<string, mixed>>}
	 */
	public static function migrate(bool $dry_run = true, bool $overwrite_conflicts = false): array
	{
		$applied = 0;
		$skipped = 0;
		$conflicts = 0;
		$items = [];

		foreach (self::raw_mappings() as $m) {
			$source_val = get_option($m['source_option'], null);

			if (null === $source_val || '' === $source_val) {
				$items[] = ['option' => $m['target_option'], 'action' => 'skip', 'reason' => 'no_source_value', 'label' => $m['label']];
				++$skipped;
				continue;
			}

			$target_val = get_option($m['target_option'], null);
			$has_target = (null !== $target_val && '' !== $target_val);

			if ($has_target && ! $overwrite_conflicts) {
				$items[] = ['option' => $m['target_option'], 'action' => 'skip', 'reason' => 'conflict', 'label' => $m['label']];
				++$conflicts;
				continue;
			}

			// Apply value transform before writing.
			$write_val = isset($m['transform'])
				? call_user_func([__CLASS__, $m['transform']], $source_val)
				: $source_val;
			$write_val = self::cast_value($write_val, $m['type']);

			if (! $dry_run) {
				self::write_option($m['target_option'], $write_val, $m['type']);
			}

			$items[] = [
				'option' => $m['target_option'],
				'action' => 'apply',
				'label' => $m['label'],
				'value' => $m['sensitive'] ? self::mask((string) $write_val) : $write_val,
			];
			++$applied;
		}

		return [
			'success' => true,
			'dry_run' => $dry_run,
			'applied' => $applied,
			'skipped' => $skipped,
			'conflicts' => $conflicts,
			'items' => $items,
		];
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Raw mapping definitions.
	 *
	 * An optional 'transform' key names a static method on this class that
	 * will be called with the raw source value before casting and writing.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function raw_mappings(): array
	{
		return [
			// Provider.
			[
				'source_option' => 'baachal_ai_provider',
				'target_option' => 'wpaic_provider_default',
				'label' => 'Default AI Provider',
				'type' => 'string',
				'sensitive' => false,
				'transform' => 'normalize_provider',
			],
			// API keys.
			[
				'source_option' => 'baachal_openai_api_key',
				'target_option' => 'wpaic_provider_openai_key',
				'label' => 'OpenAI API Key',
				'type' => 'api_key',
				'sensitive' => true,
			],
			[
				'source_option' => 'baachal_gemini_api_key',
				'target_option' => 'wpaic_provider_gemini_key',
				'label' => 'Gemini API Key',
				'type' => 'api_key',
				'sensitive' => true,
			],
			[
				'source_option' => 'baachal_claude_api_key',
				'target_option' => 'wpaic_provider_claude_key',
				'label' => 'Claude / Anthropic API Key',
				'type' => 'api_key',
				'sensitive' => true,
			],
			[
				'source_option' => 'baachal_grok_api_key',
				'target_option' => 'wpaic_provider_xai_key',
				'label' => 'xAI / Grok API Key',
				'type' => 'api_key',
				'sensitive' => true,
			],
			// Model.
			[
				'source_option' => 'baachal_ai_model',
				'target_option' => 'wpaic_default_model',
				'label' => 'Default Model',
				'type' => 'string',
				'sensitive' => false,
			],
			// Widget appearance.
			[
				'source_option' => 'baachal_title',
				'target_option' => 'wpaic_widget_title',
				'label' => 'Widget Title',
				'type' => 'string',
				'sensitive' => false,
			],
			[
				'source_option' => 'baachal_welcome_message',
				'target_option' => 'wpaic_widget_welcome_message',
				'label' => 'Welcome Message',
				'type' => 'string',
				'sensitive' => false,
			],
			[
				'source_option' => 'baachal_primary_color',
				'target_option' => 'wpaic_widget_accent_color',
				'label' => 'Widget Accent Color',
				'type' => 'string',
				'sensitive' => false,
			],
			[
				'source_option' => 'baachal_position',
				'target_option' => 'wpaic_widget_position',
				'label' => 'Widget Position',
				'type' => 'string',
				'sensitive' => false,
			],
			// Visibility.
			[
				'source_option' => 'baachal_enabled',
				'target_option' => 'wpaic_widget_display_global',
				'label' => 'Global Widget Enabled',
				'type' => 'int',
				'sensitive' => false,
			],
			// WooCommerce.
			[
				'source_option' => 'baachal_woocommerce_integration',
				'target_option' => 'wpaic_wc_enabled',
				'label' => 'WooCommerce Integration',
				'type' => 'int',
				'sensitive' => false,
			],
			// RAG / content.
			[
				'source_option' => 'baachal_content_max_results',
				'target_option' => 'wpaic_rag_chunk_limit',
				'label' => 'Max RAG Results',
				'type' => 'int',
				'sensitive' => false,
			],
		];
	}

	/**
	 * Normalise Baachal provider identifier to WPAIC convention.
	 *
	 * Baachal uses: 'gemini', 'openai', 'claude', 'grok'
	 * WPAIC uses:   'gemini', 'openai', 'claude', 'xai'
	 *
	 * @param  mixed $value Raw provider string.
	 * @return string
	 */
	private static function normalize_provider($value): string
	{
		$map = [
			'grok' => 'xai',
			'anthropic' => 'claude',
			'google' => 'gemini',
		];

		$v = strtolower((string) $value);

		return $map[ $v ] ?? $v;
	}

	/**
	 * Write a single WPAIC option, using Settings_Manager for API keys.
	 *
	 * @param  string $option Option name.
	 * @param  mixed  $value  Value to store.
	 * @param  string $type   Field type.
	 * @return void
	 */
	private static function write_option(string $option, $value, string $type): void
	{
		if ('api_key' === $type && class_exists('WPAIC\Admin\Settings_Manager')) {
			\WPAIC\Admin\Settings_Manager::get_instance()->set($option, (string) $value);

			return;
		}

		update_option($option, $value);
	}

	/**
	 * Cast a source value to the correct PHP type.
	 *
	 * @param  mixed  $value Raw value.
	 * @param  string $type  Target type.
	 * @return mixed
	 */
	private static function cast_value($value, string $type)
	{
		switch ($type) {
			case 'int':
				// Baachal stores booleans as '1'/'0' strings.
				return (int) $value;

			case 'bool':
				return (bool) $value ? 1 : 0;

			case 'api_key':
			case 'string':
			default:
				return (string) $value;
		}
	}

	/**
	 * Mask a sensitive string for safe display.
	 *
	 * @param  string $value Raw value.
	 * @return string
	 */
	private static function mask(string $value): string
	{
		if (empty($value)) {
			return '';
		}

		$len = strlen($value);

		if ($len <= 8) {
			return str_repeat('*', $len);
		}

		return substr($value, 0, 4) . str_repeat('*', $len - 8) . substr($value, -4);
	}
}
