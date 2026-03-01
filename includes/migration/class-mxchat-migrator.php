<?php

/**
 * MxChat migration source adapter.
 *
 * Reads mxchat_options (single serialised array) and maps fields to wpaic_* options.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Migration;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Mxchat_Migrator
 *
 * Detects and migrates settings from MxChat Basic (mxchat_options array).
 *
 * MxChat stores almost all configuration in a single `mxchat_options` serialised
 * array. Each mapping entry carries a `source_key` (array key within mxchat_options)
 * in addition to the standard `source_option` (which is always 'mxchat_options').
 *
 * @since 1.0.0
 * @package WPAIC\Migration
 */
class Mxchat_Migrator
{
	/** Source plugin identifier. */
	public const SOURCE_ID = 'mxchat';

	/** Root option key used by MxChat for all settings. */
	public const ROOT_OPTION = 'mxchat_options';

	// =========================================================================
	// Detection
	// =========================================================================

	/**
	 * Whether MxChat data appears to be present in the database.
	 *
	 * @return bool
	 */
	public static function detect(): bool
	{
		$opts = get_option(self::ROOT_OPTION, null);

		return is_array($opts) && ! empty($opts);
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
		$mx_opts = (array) get_option(self::ROOT_OPTION, []);
		$result = [];

		foreach (self::raw_mappings() as $m) {
			// Source value lives inside the mxchat_options array at source_key.
			$source_val = isset($mx_opts[ $m['source_key'] ]) ? $mx_opts[ $m['source_key'] ] : null;
			$target_val = get_option($m['target_option'], null);

			$has_source = (null !== $source_val && '' !== $source_val);
			$has_target = (null !== $target_val && '' !== $target_val);
			$conflict = $has_source && $has_target && ((string) $source_val !== (string) $target_val);

			$result[] = array_merge($m, [
				'source_option' => self::ROOT_OPTION . '[' . $m['source_key'] . ']',
				'source_value' => $m['sensitive'] ? self::mask((string) $source_val) : $source_val,
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
	 * Migrate MxChat settings to WPAIC options.
	 *
	 * @param  bool $dry_run             Build plan only, no writes.
	 * @param  bool $overwrite_conflicts Overwrite existing WPAIC values on conflict.
	 * @return array{success: bool, dry_run: bool, applied: int, skipped: int, conflicts: int, items: array<int, array<string, mixed>>}
	 */
	public static function migrate(bool $dry_run = true, bool $overwrite_conflicts = false): array
	{
		$mx_opts = (array) get_option(self::ROOT_OPTION, []);
		$applied = 0;
		$skipped = 0;
		$conflicts = 0;
		$items = [];

		foreach (self::raw_mappings() as $m) {
			$source_val = isset($mx_opts[ $m['source_key'] ]) ? $mx_opts[ $m['source_key'] ] : null;

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

			$write_val = self::cast_value($source_val, $m['type']);

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

		// Determine provider_default from which API key is populated.
		if (! $dry_run && $applied > 0) {
			self::infer_and_set_provider_default($mx_opts);
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
	 * Raw mapping definitions. source_key is the key inside mxchat_options[].
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function raw_mappings(): array
	{
		return [
			// API keys.
			[
				'source_key' => 'api_key',
				'target_option' => 'wpaic_provider_openai_key',
				'label' => 'OpenAI API Key',
				'type' => 'api_key',
				'sensitive' => true,
			],
			[
				'source_key' => 'claude_api_key',
				'target_option' => 'wpaic_provider_claude_key',
				'label' => 'Claude / Anthropic API Key',
				'type' => 'api_key',
				'sensitive' => true,
			],
			[
				'source_key' => 'gemini_api_key',
				'target_option' => 'wpaic_provider_gemini_key',
				'label' => 'Gemini API Key',
				'type' => 'api_key',
				'sensitive' => true,
			],
			[
				'source_key' => 'xai_api_key',
				'target_option' => 'wpaic_provider_xai_key',
				'label' => 'xAI / Grok API Key',
				'type' => 'api_key',
				'sensitive' => true,
			],
			[
				'source_key' => 'deepseek_api_key',
				'target_option' => 'wpaic_provider_deepseek_key',
				'label' => 'DeepSeek API Key',
				'type' => 'api_key',
				'sensitive' => true,
			],
			// Chat settings.
			[
				'source_key' => 'model',
				'target_option' => 'wpaic_default_model',
				'label' => 'Default Model',
				'type' => 'string',
				'sensitive' => false,
			],
			[
				'source_key' => 'system_prompt_instructions',
				'target_option' => 'wpaic_system_prompt',
				'label' => 'System Prompt',
				'type' => 'string',
				'sensitive' => false,
			],
			// Widget settings.
			[
				'source_key' => 'top_bar_title',
				'target_option' => 'wpaic_widget_title',
				'label' => 'Widget Title',
				'type' => 'string',
				'sensitive' => false,
			],
			[
				'source_key' => 'intro_message',
				'target_option' => 'wpaic_widget_welcome_message',
				'label' => 'Welcome Message',
				'type' => 'string',
				'sensitive' => false,
			],
			// Security / rate limit.
			[
				'source_key' => 'rate_limit_logged_out',
				'target_option' => 'wpaic_security_rate_limit_max',
				'label' => 'Rate Limit (logged-out)',
				'type' => 'int',
				'sensitive' => false,
			],
		];
	}

	/**
	 * Infer and set wpaic_provider_default based on which API key was migrated.
	 *
	 * @param  array $mx_opts Full mxchat_options array.
	 * @return void
	 */
	private static function infer_and_set_provider_default(array $mx_opts): void
	{
		// Skip if already set.
		$current = get_option('wpaic_provider_default', '');

		if (! empty($current)) {
			return;
		}

		// Priority order: openai > claude > gemini > deepseek > xai.
		$priority = [
			'api_key' => 'openai',
			'claude_api_key' => 'claude',
			'gemini_api_key' => 'gemini',
			'deepseek_api_key' => 'deepseek',
			'xai_api_key' => 'xai',
		];

		foreach ($priority as $key => $provider) {
			if (! empty($mx_opts[ $key ])) {
				update_option('wpaic_provider_default', $provider);
				break;
			}
		}
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
	 * MxChat stores booleans as 'on'/'off' strings for many toggles.
	 *
	 * @param  mixed  $value Raw value.
	 * @param  string $type  Target type.
	 * @return mixed
	 */
	private static function cast_value($value, string $type)
	{
		switch ($type) {
			case 'int':
				// Handle MxChat 'on'/'off' convention.
				if ('on' === $value) {
					return 1;
				}

				if ('off' === $value) {
					return 0;
				}

				return (int) $value;

			case 'bool':
				return ('on' === $value || (bool) $value) ? 1 : 0;

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
