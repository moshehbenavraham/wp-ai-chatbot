<?php

/**
 * Migration Runner — orchestrates detection, dry-run, live migration, and rollback.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Migration;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Migration_Runner
 *
 * Central service that ties together the three source-plugin migrators.
 * REST endpoint handlers in class-plugin.php delegate to these static methods.
 *
 * Backup strategy:
 *   Before every live migration the runner snapshots the current values of
 *   all target WPAIC options (and the source plugin options) into a single
 *   WP option:  wpaic_migration_backup_{source}_{timestamp}
 *   A separate index option  wpaic_migration_backup_index_{source}  tracks
 *   the timestamps of available backups so rollback can find the latest one.
 *
 * @since 1.0.0
 * @package WPAIC\Migration
 */
class Migration_Runner
{
	/** Recognised source identifiers. */
	public const SOURCES = ['axiachat', 'mxchat', 'baachal'];

	/** Option name prefix for per-source backup snapshots. */
	public const BACKUP_PREFIX = 'wpaic_migration_backup_';

	/** Option name prefix for per-source backup index arrays. */
	public const INDEX_PREFIX = 'wpaic_migration_backup_index_';

	/** Maximum backup snapshots retained per source (oldest purged). */
	public const MAX_BACKUPS = 5;

	// =========================================================================
	// Detection
	// =========================================================================

	/**
	 * Detect all source plugins and return their statuses.
	 *
	 * @return array<string, array{detected: bool, last_run: string|null, has_backup: bool}>
	 */
	public static function detect_all(): array
	{
		$result = [];

		foreach (self::SOURCES as $source) {
			$migrator = self::get_migrator($source);
			$detected = $migrator ? $migrator::detect() : false;
			$index = self::get_backup_index($source);

			$last_run = ! empty($index) ? max($index) : null;

			$result[ $source ] = [
				'detected' => $detected,
				'last_run' => $last_run ? gmdate('Y-m-d H:i:s', (int) $last_run) : null,
				'has_backup' => ! empty($index),
				'label' => self::source_label($source),
			];
		}

		return $result;
	}

	// =========================================================================
	// Mapping
	// =========================================================================

	/**
	 * Return the migration mapping table for a source (with runtime values).
	 *
	 * @param  string $source Source identifier: 'axiachat'|'mxchat'|'baachal'.
	 * @return array{success: bool, source: string, mapping: array<int, array<string, mixed>>}|array{success: bool, error: string}
	 */
	public static function get_mapping(string $source): array
	{
		$migrator = self::get_migrator($source);

		if (! $migrator) {
			return ['success' => false, 'error' => 'Unknown source: ' . $source];
		}

		if (! $migrator::detect()) {
			return ['success' => false, 'error' => 'Source plugin data not detected for: ' . $source];
		}

		return [
			'success' => true,
			'source' => $source,
			'label' => self::source_label($source),
			'mapping' => $migrator::get_mapping(),
		];
	}

	// =========================================================================
	// Migration (dry-run + live)
	// =========================================================================

	/**
	 * Run migration for a source plugin.
	 *
	 * On a live run (dry_run=false) this method:
	 *   1. Takes a backup snapshot of current WPAIC + source option values.
	 *   2. Delegates to the source migrator.
	 *   3. Records timestamp in the backup index.
	 *
	 * @param  string $source              Source identifier.
	 * @param  bool   $dry_run             When true, plan only; no writes.
	 * @param  bool   $overwrite_conflicts When true, overwrite existing WPAIC values.
	 * @return array
	 */
	public static function run(string $source, bool $dry_run = true, bool $overwrite_conflicts = false): array
	{
		$migrator = self::get_migrator($source);

		if (! $migrator) {
			return ['success' => false, 'error' => 'Unknown source: ' . $source];
		}

		if (! $migrator::detect()) {
			return ['success' => false, 'error' => 'Source plugin data not detected for: ' . $source];
		}

		// On live runs: snapshot current state before writing anything.
		$backup_key = null;

		if (! $dry_run) {
			$backup_key = self::create_backup($source, $migrator::get_mapping());
		}

		$result = $migrator::migrate($dry_run, $overwrite_conflicts);

		if (! $dry_run && $backup_key) {
			$result['backup_key'] = $backup_key;
		}

		$result['source'] = $source;
		$result['label'] = self::source_label($source);

		return $result;
	}

	// =========================================================================
	// Rollback
	// =========================================================================

	/**
	 * Roll back the most recent live migration for a source.
	 *
	 * Restores the WPAIC options to the values they held immediately before
	 * the last live migration ran. The source plugin data is left untouched.
	 *
	 * @param  string $source Source identifier.
	 * @return array{success: bool, restored: int, backup_key: string}|array{success: bool, error: string}
	 */
	public static function rollback(string $source): array
	{
		$index = self::get_backup_index($source);

		if (empty($index)) {
			return ['success' => false, 'error' => 'No backup found for source: ' . $source];
		}

		// Latest backup is the max timestamp.
		$latest_ts = (string) max($index);
		$backup_key = self::BACKUP_PREFIX . $source . '_' . $latest_ts;
		$snapshot = get_option($backup_key, null);

		if (! is_array($snapshot) || empty($snapshot['target_state'])) {
			return ['success' => false, 'error' => 'Backup snapshot is corrupt or missing: ' . $backup_key];
		}

		$restored = 0;

		foreach ($snapshot['target_state'] as $option => $value) {
			if (null === $value) {
				delete_option($option);
			} else {
				update_option($option, $value);
			}

			++$restored;
		}

		// Remove this backup from the index after successful restore.
		$index = array_values(array_filter($index, static function ($ts) use ($latest_ts) {
			return (string) $ts !== $latest_ts;
		}));
		update_option(self::INDEX_PREFIX . $source, $index);
		delete_option($backup_key);

		return [
			'success' => true,
			'source' => $source,
			'label' => self::source_label($source),
			'restored' => $restored,
			'backup_key' => $backup_key,
		];
	}

	// =========================================================================
	// Backup helpers
	// =========================================================================

	/**
	 * Create a backup snapshot of current WPAIC option values.
	 *
	 * @param  string $source  Source identifier.
	 * @param  array  $mapping Mapping table (used to know which WPAIC options to snapshot).
	 * @return string  Backup option key (includes timestamp).
	 */
	private static function create_backup(string $source, array $mapping): string
	{
		$ts = (string) time();
		$backup_key = self::BACKUP_PREFIX . $source . '_' . $ts;

		// Collect current values of every target WPAIC option in the mapping.
		$target_state = [];

		foreach ($mapping as $entry) {
			$opt = isset($entry['target_option']) ? $entry['target_option'] : '';

			if ($opt && ! array_key_exists($opt, $target_state)) {
				// Store NULL if option does not exist — distinguishes "not set" from empty string.
				$raw = get_option($opt, null);
				$target_state[ $opt ] = $raw;
			}
		}

		update_option(
			$backup_key,
			[
				'source' => $source,
				'timestamp' => $ts,
				'target_state' => $target_state,
			],
			false   // Do not autoload large backup blobs.
		);

		// Update the backup index.
		$index = self::get_backup_index($source);
		$index[] = $ts;

		// Keep only the most recent MAX_BACKUPS.
		if (count($index) > self::MAX_BACKUPS) {
			$oldest = array_shift($index); // Remove oldest.
			delete_option(self::BACKUP_PREFIX . $source . '_' . $oldest);
		}

		sort($index);
		update_option(self::INDEX_PREFIX . $source, $index, false);

		return $backup_key;
	}

	/**
	 * Return the backup timestamp index for a source.
	 *
	 * @param  string $source Source identifier.
	 * @return array<int, string>
	 */
	private static function get_backup_index(string $source): array
	{
		$raw = get_option(self::INDEX_PREFIX . $source, []);

		return is_array($raw) ? $raw : [];
	}

	// =========================================================================
	// Factory
	// =========================================================================

	/**
	 * Return the migrator class name for a source, or null if unknown.
	 *
	 * @param  string $source Source identifier.
	 * @return class-string|null
	 */
	private static function get_migrator(string $source): ?string
	{
		$map = [
			'axiachat' => 'WPAIC\Migration\Axiachat_Migrator',
			'mxchat' => 'WPAIC\Migration\Mxchat_Migrator',
			'baachal' => 'WPAIC\Migration\Baachal_Migrator',
		];

		return $map[ $source ] ?? null;
	}

	/**
	 * Human-readable label for a source identifier.
	 *
	 * @param  string $source Source identifier.
	 * @return string
	 */
	private static function source_label(string $source): string
	{
		$labels = [
			'axiachat' => 'AxiaChat AI',
			'mxchat' => 'MxChat Basic',
			'baachal' => 'Baachal AI Chatbot',
		];

		return $labels[ $source ] ?? $source;
	}
}
