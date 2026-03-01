<?php

/**
 * Migration runner — versioned database schema management.
 *
 * Responsibilities:
 *  - Maintain a registry of ordered, idempotent migration callbacks.
 *  - Check stored version against WPAIC_VERSION on every `plugins_loaded`.
 *  - Execute pending migrations in semver + domain-dependency order.
 *  - Log each execution to the `wp_wpaic_migrations` table.
 *  - Prevent concurrent execution via a transient-based lock.
 *
 * Migration entries are collected via the `wpaic_register_migrations` action.
 * Domain modules add callbacks using Migration_Registry::get_instance()->register().
 *
 * Source anchors:
 *  - MxChat: stored version + version_compare() gating.
 *  - AxiaChat: dbDelta() + SHOW COLUMNS idempotency guards.
 *  - Baachal: three-hook lifecycle coverage.
 *
 * @package WP_AI_Chatbot
 */

namespace WPAIC\Migration;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Migration_Registry
 *
 * Manages versioned database migrations across plugin upgrades.
 */
final class Migration_Registry
{
	// -------------------------------------------------------------------------
	// Domain dependency levels — lower number = runs first within same version.
	// -------------------------------------------------------------------------

	/** @var array<string, int> Domain execution order (leaf → dependent). */
	private const DOMAIN_ORDER = [
		'migration' => 0,
		'security' => 0,
		'provider' => 1,
		'rag' => 2,
		'runtime' => 2,
		'admin' => 3,
		'tools' => 4,
		'integrations' => 4,
		'woocommerce' => 4,
	];

	/** @var string[] All valid canonical domain IDs. */
	private const VALID_DOMAINS = [
		'migration', 'security', 'provider', 'rag', 'runtime',
		'admin', 'tools', 'integrations', 'woocommerce',
	];

	/** @var string Transient key for the migration lock. */
	private const LOCK_KEY = 'wpaic_temp_migration_lock';

	/** @var int Lock TTL in seconds. */
	private const LOCK_TTL = 300;

	/** @var Migration_Registry|null Singleton instance. */
	private static $instance = null;

	/**
	 * Registered migration entries.
	 *
	 * Each entry: [version, domain, description, callback, validate?]
	 *
	 * @var array<int, array{version: string, domain: string, description: string, callback: callable, validate?: array}>
	 */
	private $migrations = [];

	/** @var bool Whether the bootstrap migration has been seeded. */
	private $bootstrapped = false;

	/** Private constructor enforces singleton. */
	private function __construct()
	{
		$this->seed_bootstrap_migration();
	}

	/**
	 * Return (and lazily create) the singleton instance.
	 *
	 * @return Migration_Registry
	 */
	public static function get_instance(): Migration_Registry
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Register a migration entry.
	 *
	 * @param  array{version: string, domain: string, description: string, callback: callable, validate?: array} $entry
	 * @return void
	 * @throws \InvalidArgumentException On validation failure.
	 */
	public function register(array $entry): void
	{
		$this->validate_entry($entry);

		// Guard against duplicate (version, domain) pairs.
		foreach ($this->migrations as $existing) {
			if ($existing['version'] === $entry['version'] && $existing['domain'] === $entry['domain']) {
				throw new \InvalidArgumentException(
					sprintf(
						'Duplicate migration entry: version %s, domain %s.',
						$entry['version'],
						$entry['domain']
					)
				);
			}
		}

		$this->migrations[] = $entry;
	}

	/**
	 * Check whether migrations are needed and run any that are pending.
	 *
	 * Called on every `plugins_loaded`. Returns immediately if the stored
	 * version matches WPAIC_VERSION (the common case — no overhead).
	 *
	 * @return void
	 */
	public function check_and_run(): void
	{
		$stored_version = (string) get_option('wpaic_migration_version', '0.0.0');

		if (version_compare($stored_version, WPAIC_VERSION, '>=')) {
			return; // Already up to date.
		}

		$this->run_pending($stored_version);
	}

	/**
	 * Run ALL registered migrations, ignoring the stored version.
	 *
	 * Called from Lifecycle_Manager::activate() on fresh installs and
	 * forced re-activations. Idempotent: each migration callback uses
	 * CREATE TABLE IF NOT EXISTS / SHOW COLUMNS guards.
	 *
	 * @return void
	 */
	public function run_all_pending(): void
	{
		$this->run_pending('0.0.0');
	}

	// =========================================================================
	// Internals
	// =========================================================================

	/**
	 * Seed the self-bootstrapping migration that creates the migrations log table.
	 *
	 * This migration (version 0.0.1, domain migration) is always registered first
	 * and cannot be overridden by external registrations.
	 *
	 * @return void
	 */
	private function seed_bootstrap_migration(): void
	{
		if ($this->bootstrapped) {
			return;
		}
		$this->bootstrapped = true;

		$this->migrations[] = [
			'version' => '0.0.1',
			'domain' => 'migration',
			'description' => 'Create migration log table (self-bootstrapping)',
			'callback' => [$this, 'create_migrations_table'],
			'validate' => [
				[
					'table' => 'wpaic_migrations',
					'columns' => ['id', 'version', 'domain', 'status'],
				],
			],
		];
	}

	/**
	 * Collect pending migrations via hook, sort them, and execute in order.
	 *
	 * @param  string $since_version Only run migrations with version > this.
	 * @return void
	 */
	private function run_pending(string $since_version): void
	{
		if (! $this->acquire_lock()) {
			// Another process is already migrating (e.g. concurrent admin loads).
			return;
		}

		try {
			/**
			 * Fires to collect migration entries from all domain modules.
			 *
			 * @param Migration_Registry $registry The registry instance.
			 * @since 1.0.0
			 */
			do_action('wpaic_register_migrations', $this);

			// Filter to migrations not yet applied.
			$pending = array_filter(
				$this->migrations,
				static function (array $entry) use ($since_version): bool {
					return version_compare($entry['version'], $since_version, '>');
				}
			);

			if (empty($pending)) {
				$this->update_stored_version();

				return;
			}

			// Sort: version ASC, then domain dependency level ASC.
			usort(
				$pending,
				static function (array $a, array $b): int {
					$ver_cmp = version_compare($a['version'], $b['version']);
					if (0 !== $ver_cmp) {
						return $ver_cmp;
					}
					$level_a = self::DOMAIN_ORDER[ $a['domain'] ] ?? 99;
					$level_b = self::DOMAIN_ORDER[ $b['domain'] ] ?? 99;

					return $level_a - $level_b;
				}
			);

			/**
			 * Fires immediately before the first migration callback runs.
			 * Backup plugins can hook here to snapshot the database.
			 *
			 * @since 1.0.0
			 */
			do_action('wpaic_before_migration');

			foreach ($pending as $entry) {
				$success = $this->execute_migration($entry);
				if (! $success) {
					// Stop the sequence on failure; stored version is NOT updated.
					// The next `plugins_loaded` will retry from the same version.
					return;
				}
			}

			$this->update_stored_version();

			/**
			 * Fires after all pending migrations have completed successfully.
			 *
			 * @since 1.0.0
			 */
			do_action('wpaic_after_migration');

		} finally {
			$this->release_lock();
		}
	}

	/**
	 * Execute a single migration callback, log the result.
	 *
	 * @param  array{version: string, domain: string, description: string, callback: callable, validate?: array} $entry
	 * @return bool True on success, false on failure.
	 */
	private function execute_migration(array $entry): bool
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_migrations';
		$table_exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '{$table}'"); // phpcs:ignore

		$started_at = current_time('mysql');
		$start_ms = (int) round(microtime(true) * 1000);
		$log_id = null;

		// Insert log row only if the migrations table already exists.
		if ($table_exists) {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				[
					'version' => $entry['version'],
					'domain' => $entry['domain'],
					'description' => $entry['description'],
					'status' => 'running',
					'started_at' => $started_at,
					'checksum' => substr(md5(serialize($entry['callback'])), 0, 32),
				],
				['%s', '%s', '%s', '%s', '%s', '%s']
			);
			$log_id = $wpdb->insert_id;
		}

		try {
			call_user_func($entry['callback']);

			$duration_ms = (int) round(microtime(true) * 1000) - $start_ms;

			if ($table_exists && $log_id) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					[
						'status' => 'completed',
						'completed_at' => current_time('mysql'),
						'duration_ms' => $duration_ms,
					],
					['id' => $log_id],
					['%s', '%s', '%d'],
					['%d']
				);
			}

			// Optional post-migration schema validation.
			if (! empty($entry['validate'])) {
				$this->validate_schema($entry['validate'], $entry['version'], $entry['domain']);
			}

			return true;

		} catch (\Throwable $e) {
			$duration_ms = (int) round(microtime(true) * 1000) - $start_ms;

			if ($table_exists && $log_id) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$table,
					[
						'status' => 'failed',
						'completed_at' => current_time('mysql'),
						'duration_ms' => $duration_ms,
						'error_message' => substr($e->getMessage(), 0, 65535),
					],
					['id' => $log_id],
					['%s', '%s', '%d', '%s'],
					['%d']
				);
			}

			// Surface the failure in the PHP error log; DB log already captured it above.
			error_log(
				sprintf(
					'[WP AI Chatbot] Migration failed — version: %s, domain: %s, error: %s',
					$entry['version'],
					$entry['domain'],
					$e->getMessage()
				)
			);

			return false;
		}
	}

	/**
	 * Validate that expected columns exist after a migration.
	 *
	 * Logs a warning on mismatch but does NOT fail the migration.
	 *
	 * @param  array  $expectations Array of ['table' => string, 'columns' => string[]].
	 * @param  string $version      Migration version (for log context).
	 * @param  string $domain       Migration domain (for log context).
	 * @return void
	 */
	private function validate_schema(array $expectations, string $version, string $domain): void
	{
		global $wpdb;

		foreach ($expectations as $expectation) {
			$table = $wpdb->prefix . $expectation['table'];
			$columns = $expectation['columns'];

			$existing = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`"); // phpcs:ignore

			$missing = array_diff($columns, $existing);
			if (! empty($missing)) {
				error_log(
					sprintf(
						'[WP AI Chatbot] Schema validation warning — version: %s, domain: %s, table: %s, missing: %s',
						$version,
						$domain,
						$table,
						implode(', ', $missing)
					)
				);
			}
		}
	}

	/**
	 * Update the stored migration version option.
	 *
	 * @return void
	 */
	private function update_stored_version(): void
	{
		update_option('wpaic_migration_version', WPAIC_VERSION, false);
	}

	// =========================================================================
	// Lock management
	// =========================================================================

	/**
	 * Attempt to acquire the migration lock.
	 *
	 * Uses a double-check pattern: set then re-read the transient to confirm
	 * ownership. Prevents two concurrent processes from both running migrations.
	 *
	 * @return bool True if the lock was acquired, false otherwise.
	 */
	private function acquire_lock(): bool
	{
		if (false !== get_transient(self::LOCK_KEY)) {
			return false; // Another process holds the lock.
		}

		$lock_value = gethostname() . ':' . getmypid() . ':' . time();
		set_transient(self::LOCK_KEY, $lock_value, self::LOCK_TTL);

		// Double-check: confirm we own the lock.
		return (get_transient(self::LOCK_KEY) === $lock_value);
	}

	/**
	 * Release the migration lock.
	 *
	 * @return void
	 */
	private function release_lock(): void
	{
		delete_transient(self::LOCK_KEY);
	}

	// =========================================================================
	// Entry validation
	// =========================================================================

	/**
	 * Validate a migration entry array.
	 *
	 * @param  array $entry Entry to validate.
	 * @return void
	 * @throws \InvalidArgumentException On any validation failure.
	 */
	private function validate_entry(array $entry): void
	{
		if (empty($entry['version']) || ! preg_match('/^\d+\.\d+\.\d+/', $entry['version'])) {
			throw new \InvalidArgumentException(
				'Migration entry must have a valid semver `version` string.'
			);
		}

		if (empty($entry['domain']) || ! in_array($entry['domain'], self::VALID_DOMAINS, true)) {
			throw new \InvalidArgumentException(
				sprintf(
					'Migration entry domain "%s" is not a valid canonical domain ID.',
					$entry['domain'] ?? ''
				)
			);
		}

		if (empty($entry['description'])) {
			throw new \InvalidArgumentException(
				'Migration entry must have a non-empty `description`.'
			);
		}

		if (! isset($entry['callback']) || ! is_callable($entry['callback'])) {
			throw new \InvalidArgumentException(
				'Migration entry must have a callable `callback`.'
			);
		}
	}

	// =========================================================================
	// Bootstrap migration callback
	// =========================================================================

	/**
	 * Create the migration log table (self-bootstrapping migration 0.0.1).
	 *
	 * Uses dbDelta() for idempotent creation. This table must exist before
	 * any other migration is logged, so it is created here without logging.
	 *
	 * @return void
	 */
	public function create_migrations_table(): void
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_migrations';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  version varchar(20) NOT NULL DEFAULT '',
  domain varchar(30) NOT NULL DEFAULT '',
  description varchar(255) NOT NULL DEFAULT '',
  status ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
  started_at datetime DEFAULT NULL,
  completed_at datetime DEFAULT NULL,
  duration_ms int(10) UNSIGNED DEFAULT NULL,
  error_message text DEFAULT NULL,
  checksum varchar(64) DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY version_domain (version,domain),
  KEY status (status),
  KEY completed_at (completed_at)
) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}
}
