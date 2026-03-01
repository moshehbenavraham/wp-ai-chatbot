<?php

/**
 * AxiaChat migration source adapter.
 *
 * Reads aichat_* options and maps them to wpaic_* equivalents.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Migration;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Axiachat_Migrator
 *
 * Detects and migrates settings from the AxiaChat AI plugin (aichat_* options).
 *
 * @since 1.0.0
 * @package WPAIC\Migration
 */
class Axiachat_Migrator
{
	/** Source plugin identifier used in backup keys and status tracking. */
	public const SOURCE_ID = 'axiachat';

	/** Canary option key that confirms AxiaChat data is present. */
	public const CANARY_OPTION = 'aichat_openai_api_key';

	// =========================================================================
	// Detection
	// =========================================================================

	/**
	 * Whether AxiaChat plugin data appears to be present in the database.
	 *
	 * @return bool
	 */
	public static function detect(): bool
	{
		// Check multiple indicators so detection survives partial uninstalls.
		return get_option(self::CANARY_OPTION, null) !== null
			|| get_option('aichat_branding_enabled', null) !== null
			|| get_option('aichat_global_bot_enabled', null) !== null;
	}

	// =========================================================================
	// Mapping
	// =========================================================================

	/**
	 * Return the full migration mapping table with runtime values.
	 *
	 * Each entry:
	 *   source_option   string  Source WP option name.
	 *   target_option   string  WPAIC target option name.
	 *   label           string  Human-readable field label.
	 *   type            string  'api_key'|'string'|'int'|'bool'.
	 *   sensitive       bool    Whether the value should be masked for display.
	 *   source_value    mixed   Value in source (masked if sensitive); null = not set.
	 *   target_value    mixed   Current WPAIC value (masked if sensitive).
	 *   has_source      bool    Source has a non-empty value.
	 *   has_target      bool    Target already has a non-empty value.
	 *   conflict        bool    Both non-empty and source differs from target.
	 *   would_migrate   bool    Whether a live run would write this entry.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_mapping(): array
	{
		$result = [];

		foreach (self::raw_mappings() as $m) {
			$source_val = get_option($m['source_option'], null);
			$target_val = get_option($m['target_option'], null);

			$has_source = (null !== $source_val && '' !== $source_val);
			$has_target = (null !== $target_val && '' !== $target_val);
			$conflict = $has_source && $has_target && ((string) $source_val !== (string) $target_val);

			$result[] = array_merge($m, [
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
	 * Migrate AxiaChat settings to WPAIC target options.
	 *
	 * @param  bool $dry_run              When true, build the plan without writing.
	 * @param  bool $overwrite_conflicts  When true, overwrite existing WPAIC values.
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
	 * Raw mapping definitions (no runtime option lookups).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function raw_mappings(): array
	{
		return [
			[
				'source_option' => 'aichat_openai_api_key',
				'target_option' => 'wpaic_provider_openai_key',
				'label' => 'OpenAI API Key',
				'type' => 'api_key',
				'sensitive' => true,
			],
			[
				'source_option' => 'aichat_global_bot_enabled',
				'target_option' => 'wpaic_widget_display_global',
				'label' => 'Global Widget Enabled',
				'type' => 'int',
				'sensitive' => false,
			],
			[
				'source_option' => 'aichat_position',
				'target_option' => 'wpaic_widget_position',
				'label' => 'Widget Position',
				'type' => 'string',
				'sensitive' => false,
			],
			[
				'source_option' => 'aichat_branding_enabled',
				'target_option' => 'wpaic_widget_branding_enabled',
				'label' => 'Branding Enabled',
				'type' => 'int',
				'sensitive' => false,
			],
			[
				'source_option' => 'aichat_branding_text',
				'target_option' => 'wpaic_widget_branding_text',
				'label' => 'Branding Text',
				'type' => 'string',
				'sensitive' => false,
			],
			[
				'source_option' => 'aichat_chat_color',
				'target_option' => 'wpaic_widget_accent_color',
				'label' => 'Widget Accent Color',
				'type' => 'string',
				'sensitive' => false,
			],
		];
	}

	/**
	 * Write a single option, using Settings_Manager for API keys (encrypted).
	 *
	 * @param  string $option Option name.
	 * @param  mixed  $value  Value to store.
	 * @param  string $type   'api_key'|'string'|'int'|'bool'.
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
	 * Mask a sensitive string for safe display (show first 4 + last 4 chars).
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
