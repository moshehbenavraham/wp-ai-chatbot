<?php

/**
 * Plugin lifecycle manager — activation, deactivation, and uninstall.
 *
 * Responsibilities:
 *  - activate():  version guards, run all pending migrations, set default
 *                 options, seed the default bot, schedule cron events.
 *  - deactivate(): unschedule cron events, fire extension hooks.
 *  - uninstall():  data preservation flag check, drop tables, delete
 *                  options / transients / user meta.
 *  - register_core_migrations(): hooked to `wpaic_register_migrations`,
 *                  registers all MVP table creation migrations.
 *
 * Source anchors:
 *  - Baachal: three-hook lifecycle, data preservation flag (baachal/uninstall.php).
 *  - AxiaChat: wp_die() version guard on activation.
 *  - MxChat: default option seeding, capabilities.
 *
 * @package WP_AI_Chatbot
 */

namespace WPAIC\Migration;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Lifecycle_Manager
 *
 * All methods are static: WordPress lifecycle hooks call them without
 * instantiating the class, and the uninstall.php context loads only the
 * autoloader + this class.
 */
final class Lifecycle_Manager
{
	// =========================================================================
	// Cron event slugs
	// =========================================================================

	private const CRON_CLEANUP_SESSIONS = 'wpaic_cleanup_sessions';
	private const CRON_PROCESS_QUEUE = 'wpaic_process_queue';

	// =========================================================================
	// Lifecycle callbacks
	// =========================================================================

	/**
	 * Plugin activation handler.
	 *
	 * WordPress calls this via register_activation_hook() from the main
	 * plugin file. Runs synchronously before the activation redirect.
	 *
	 * @return void
	 */
	public static function activate(): void
	{
		// Hard version checks — wp_die() prevents activation on incompatible sites.
		if (version_compare(PHP_VERSION, WPAIC_MIN_PHP, '<')) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: Required PHP version. 2: Current PHP version. */
						__('WP AI Chatbot requires PHP %1$s or higher. Your server is running PHP %2$s.', 'wp-ai-chatbot'),
						WPAIC_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}

		global $wp_version;
		if (isset($wp_version) && version_compare($wp_version, WPAIC_MIN_WP, '<')) {
			wp_die(
				esc_html(
					sprintf(
						/* translators: 1: Required WP version. 2: Current WP version. */
						__('WP AI Chatbot requires WordPress %1$s or higher. You are running %2$s.', 'wp-ai-chatbot'),
						WPAIC_MIN_WP,
						$wp_version
					)
				)
			);
		}

		// Register core migrations so they are available for the run below.
		// Normally collected via the `wpaic_register_migrations` action, but
		// on activation the action fires inside run_all_pending() via the hook.
		Migration_Registry::get_instance()->run_all_pending();

		// Seed default options (only if not already set — idempotent).
		static::seed_default_options();

		// Seed the default bot row if the bots table is empty.
		static::seed_default_bot();

		// Schedule WP-Cron events.
		static::schedule_cron_events();

		/**
		 * Fires after plugin activation is complete.
		 * Extension plugins create their own tables or options here.
		 *
		 * @since 1.0.0
		 */
		do_action('wpaic_activated');

		// Flush rewrite rules so shortcodes and REST routes resolve correctly.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation handler.
	 *
	 * Data and settings are preserved. Only scheduled tasks are cancelled.
	 *
	 * @return void
	 */
	public static function deactivate(): void
	{
		static::unschedule_cron_events();

		/**
		 * Fires after plugin deactivation.
		 * Extension plugins cancel their own scheduled tasks here.
		 *
		 * @since 1.0.0
		 */
		do_action('wpaic_deactivated');

		flush_rewrite_rules();
	}

	/**
	 * Plugin uninstall handler — called from uninstall.php.
	 *
	 * If `wpaic_preserve_data` is true only the flag is removed.
	 * Otherwise all plugin data is deleted in dependency order.
	 *
	 * @return void
	 */
	public static function uninstall(): void
	{
		// Data preservation flag — respects operator's explicit choice.
		if (get_option('wpaic_preserve_data')) {
			delete_option('wpaic_preserve_data');

			return;
		}

		/**
		 * Fires before the uninstall cleanup begins.
		 * Extension plugins register their own cleanup callbacks here.
		 *
		 * @since 1.0.0
		 */
		do_action('wpaic_uninstalling');

		// Allow extensions to add their own table names to the drop list.
		$tables = (array) apply_filters(
			'wpaic_uninstall_cleanup_tables',
			static::get_core_table_names()
		);

		static::drop_tables($tables);
		static::delete_options();
		static::delete_transients();
		static::delete_user_meta();
		static::unschedule_cron_events();
	}

	// =========================================================================
	// Migration registration
	// =========================================================================

	/**
	 * Register all core MVP table creation migrations.
	 *
	 * Hooked to `wpaic_register_migrations` from Plugin::register_hooks().
	 * All tables are created at version 1.0.0 using the proper domain labels.
	 *
	 * @param  Migration_Registry $registry The migration registry instance.
	 * @return void
	 */
	public static function register_core_migrations(Migration_Registry $registry): void
	{
		// Sessions and messages — runtime domain.
		$registry->register([
			'version' => '1.0.0',
			'domain' => 'runtime',
			'description' => 'Create sessions table',
			'callback' => [static::class, 'create_sessions_table'],
			'validate' => [
				[
					'table' => 'wpaic_sessions',
					'columns' => ['id', 'session_token', 'user_id', 'bot_id', 'status', 'created_at'],
				],
			],
		]);

		$registry->register([
			'version' => '1.0.0',
			'domain' => 'rag',
			'description' => 'Create knowledge, chunks, and queue tables',
			'callback' => [static::class, 'create_rag_tables'],
			'validate' => [
				[
					'table' => 'wpaic_knowledge',
					'columns' => ['id', 'name', 'context_type', 'processing_status'],
				],
				[
					'table' => 'wpaic_chunks',
					'columns' => ['id', 'knowledge_id', 'post_id', 'content'],
				],
				[
					'table' => 'wpaic_queue',
					'columns' => ['id', 'queue_id', 'status', 'priority'],
				],
			],
		]);

		$registry->register([
			'version' => '1.0.0',
			'domain' => 'admin',
			'description' => 'Create usage_daily and bots tables',
			'callback' => [static::class, 'create_admin_tables'],
			'validate' => [
				[
					'table' => 'wpaic_usage_daily',
					'columns' => ['date', 'provider', 'model', 'total_tokens'],
				],
				[
					'table' => 'wpaic_bots',
					'columns' => ['id', 'name', 'slug', 'provider', 'model', 'is_active'],
				],
			],
		]);

		// Runtime messages table (depends on sessions, so same domain/version but
		// within the same migration callback to ensure ordering).
		$registry->register([
			'version' => '1.0.0',
			'domain' => 'security',
			'description' => 'Create messages table',
			'callback' => [static::class, 'create_messages_table'],
			'validate' => [
				[
					'table' => 'wpaic_messages',
					'columns' => ['id', 'session_id', 'role', 'message', 'created_at'],
				],
			],
		]);
	}

	// =========================================================================
	// Table creation callbacks
	// =========================================================================

	/**
	 * Create the `wp_wpaic_sessions` table.
	 *
	 * @return void
	 */
	public static function create_sessions_table(): void
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_sessions';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  session_token varchar(64) NOT NULL DEFAULT '',
  user_id bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  bot_id bigint(20) UNSIGNED DEFAULT NULL,
  ip_address varbinary(16) DEFAULT NULL,
  user_agent_hash varchar(64) DEFAULT NULL,
  status ENUM('active','closed','expired') NOT NULL DEFAULT 'active',
  provider varchar(40) DEFAULT NULL,
  model varchar(100) DEFAULT NULL,
  page_id bigint(20) UNSIGNED DEFAULT NULL,
  total_prompt_tokens int(10) UNSIGNED NOT NULL DEFAULT '0',
  total_completion_tokens int(10) UNSIGNED NOT NULL DEFAULT '0',
  total_cost_micros bigint(20) NOT NULL DEFAULT '0',
  message_count int(10) UNSIGNED NOT NULL DEFAULT '0',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY session_token (session_token),
  KEY idx_user_status (user_id,status),
  KEY idx_bot_id (bot_id),
  KEY idx_status (status),
  KEY idx_page_id (page_id),
  KEY idx_created_at (created_at),
  KEY idx_expires_at (expires_at)
) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Create the `wp_wpaic_messages` table.
	 *
	 * Must be called after create_sessions_table() because it references
	 * session_id. No MySQL-level FK is used (WordPress convention).
	 *
	 * @return void
	 */
	public static function create_messages_table(): void
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_messages';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  session_id bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  role ENUM('user','assistant','system') NOT NULL DEFAULT 'user',
  message longtext NOT NULL,
  response longtext DEFAULT NULL,
  provider varchar(40) DEFAULT NULL,
  model varchar(100) DEFAULT NULL,
  prompt_tokens int(10) UNSIGNED DEFAULT NULL,
  completion_tokens int(10) UNSIGNED DEFAULT NULL,
  cost_micros bigint(20) DEFAULT NULL,
  rag_context longtext DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY idx_session_id (session_id,id),
  KEY idx_model (model),
  KEY idx_created_at (created_at)
) ENGINE=InnoDB {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Create the RAG tables: `wp_wpaic_knowledge`, `wp_wpaic_chunks`, `wp_wpaic_queue`.
	 *
	 * @return void
	 */
	public static function create_rag_tables(): void
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Knowledge contexts table.
		$knowledge = $wpdb->prefix . 'wpaic_knowledge';
		dbDelta("CREATE TABLE {$knowledge} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(255) NOT NULL DEFAULT '',
  context_type ENUM('local','pinecone') NOT NULL DEFAULT 'local',
  embedding_provider varchar(40) DEFAULT NULL,
  embedding_model varchar(100) DEFAULT NULL,
  processing_status varchar(20) DEFAULT 'pending',
  processing_progress int(11) DEFAULT '0',
  items_to_process longtext DEFAULT NULL,
  autosync_enabled tinyint(1) NOT NULL DEFAULT '0',
  autosync_mode ENUM('updates','updates_and_new') NOT NULL DEFAULT 'updates',
  autosync_post_types varchar(255) DEFAULT NULL,
  autosync_last_scan datetime DEFAULT NULL,
  indexing_options longtext DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY idx_processing_status (processing_status)
) ENGINE=InnoDB {$charset_collate};");

		// Content chunks table (with embeddings stored as JSON float arrays).
		$chunks = $wpdb->prefix . 'wpaic_chunks';
		dbDelta("CREATE TABLE {$chunks} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  knowledge_id bigint(20) UNSIGNED DEFAULT NULL,
  post_id bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  chunk_index int(11) NOT NULL DEFAULT '0',
  type varchar(20) DEFAULT NULL,
  title varchar(255) DEFAULT NULL,
  content mediumtext NOT NULL,
  embedding longtext DEFAULT NULL,
  tokens int(11) DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_post_knowledge_chunk (post_id,knowledge_id,chunk_index),
  KEY idx_knowledge_id (knowledge_id),
  KEY idx_post_knowledge (post_id,knowledge_id)
) ENGINE=InnoDB {$charset_collate};");

		// Ingestion processing queue.
		$queue = $wpdb->prefix . 'wpaic_queue';
		dbDelta("CREATE TABLE {$queue} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  queue_id varchar(64) NOT NULL DEFAULT '',
  item_type varchar(20) NOT NULL DEFAULT '',
  item_data longtext NOT NULL,
  status ENUM('pending','processing','completed','failed','stalled') NOT NULL DEFAULT 'pending',
  bot_id varchar(50) NOT NULL DEFAULT 'default',
  priority int(11) NOT NULL DEFAULT '0',
  attempts int(11) NOT NULL DEFAULT '0',
  max_attempts int(11) NOT NULL DEFAULT '3',
  error_message text DEFAULT NULL,
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at datetime DEFAULT NULL,
  completed_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY idx_queue_id (queue_id),
  KEY idx_status_priority (status,priority),
  KEY idx_item_type (item_type)
) ENGINE=InnoDB {$charset_collate};");
	}

	/**
	 * Create the admin tables: `wp_wpaic_usage_daily` and `wp_wpaic_bots`.
	 *
	 * @return void
	 */
	public static function create_admin_tables(): void
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Daily usage / cost aggregation table.
		$usage = $wpdb->prefix . 'wpaic_usage_daily';
		dbDelta("CREATE TABLE {$usage} (
  date date NOT NULL,
  provider varchar(40) NOT NULL DEFAULT 'openai',
  model varchar(100) NOT NULL DEFAULT '',
  prompt_tokens bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  completion_tokens bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  total_tokens bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  cost_micros bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  conversations bigint(20) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY  (date,provider,model)
) ENGINE=InnoDB {$charset_collate};");

		// Bot configuration table.
		$bots = $wpdb->prefix . 'wpaic_bots';
		dbDelta("CREATE TABLE {$bots} (
  id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  name varchar(100) NOT NULL DEFAULT '',
  slug varchar(100) NOT NULL DEFAULT '',
  instructions longtext DEFAULT NULL,
  provider varchar(40) NOT NULL DEFAULT 'openai',
  model varchar(100) NOT NULL DEFAULT 'gpt-4o',
  temperature decimal(3,2) NOT NULL DEFAULT '0.70',
  max_tokens int(11) NOT NULL DEFAULT '2048',
  context_mode ENUM('embeddings','page','none') NOT NULL DEFAULT 'embeddings',
  knowledge_id bigint(20) UNSIGNED DEFAULT NULL,
  input_max_length int(11) NOT NULL DEFAULT '512',
  max_messages int(11) NOT NULL DEFAULT '20',
  history_persistence tinyint(1) NOT NULL DEFAULT '1',
  ui_position ENUM('br','bl','tr','tl') NOT NULL DEFAULT 'br',
  ui_color varchar(7) NOT NULL DEFAULT '#1a73e8',
  ui_avatar varchar(255) DEFAULT NULL,
  ui_start_sentence varchar(255) DEFAULT NULL,
  ui_placeholder varchar(255) NOT NULL DEFAULT 'Write your question...',
  ui_suggestions_enabled tinyint(1) NOT NULL DEFAULT '0',
  ui_suggestions_count int(11) NOT NULL DEFAULT '3',
  is_active tinyint(1) NOT NULL DEFAULT '1',
  created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  UNIQUE KEY slug (slug),
  KEY idx_provider (provider),
  KEY idx_knowledge_id (knowledge_id),
  KEY idx_is_active (is_active)
) ENGINE=InnoDB {$charset_collate};");
	}

	// =========================================================================
	// Default seeding
	// =========================================================================

	/**
	 * Set default plugin options — only inserts if the option does not exist.
	 *
	 * Uses add_option() which is a no-op if the key already exists (idempotent).
	 *
	 * @return void
	 */
	private static function seed_default_options(): void
	{
		// General settings.
		add_option('wpaic_general_settings', [], '', false);

		// Provider settings.
		add_option('wpaic_provider_default', 'openai', '', false);
		add_option('wpaic_model_default', 'gpt-4o', '', false);

		// Runtime settings.
		add_option('wpaic_runtime_stream_enabled', '1', '', false);
		add_option('wpaic_runtime_max_tokens', '2048', '', false);

		// Security settings.
		add_option('wpaic_security_rate_limit', (string) WPAIC_DEFAULT_RATE_LIMIT, '', false);
		add_option('wpaic_security_rate_window', (string) WPAIC_DEFAULT_RATE_WINDOW, '', false);
		add_option('wpaic_security_moderation_enabled', '0', '', false);

		// Data lifecycle.
		add_option('wpaic_preserve_data', '0', '', false);

		// Version tracking — set to 0.0.0 initially so migrations always run.
		add_option('wpaic_migration_version', '0.0.0', '', false);
	}

	/**
	 * Seed the default bot row if the bots table is empty.
	 *
	 * Idempotent: only runs when the table has zero rows.
	 *
	 * @return void
	 */
	private static function seed_default_bot(): void
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_bots';

		// Check table exists before querying (tables created just above).
		$exists = (bool) $wpdb->get_var("SHOW TABLES LIKE '{$table}'"); // phpcs:ignore
		if (! $exists) {
			return;
		}

		$count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"); // phpcs:ignore
		if ($count > 0) {
			return;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			[
				'name' => 'AI Assistant',
				'slug' => 'default',
				'is_active' => 1,
			],
			['%s', '%s', '%d']
		);
	}

	// =========================================================================
	// WP-Cron scheduling
	// =========================================================================

	/**
	 * Schedule all plugin WP-Cron events.
	 *
	 * Checks for existing schedules before adding (idempotent).
	 *
	 * @return void
	 */
	private static function schedule_cron_events(): void
	{
		if (! wp_next_scheduled(self::CRON_CLEANUP_SESSIONS)) {
			wp_schedule_event(time(), 'hourly', self::CRON_CLEANUP_SESSIONS);
		}

		if (! wp_next_scheduled(self::CRON_PROCESS_QUEUE)) {
			wp_schedule_event(time(), 'wpaic_every_five_minutes', self::CRON_PROCESS_QUEUE);
		}
	}

	/**
	 * Unschedule all plugin WP-Cron events.
	 *
	 * @return void
	 */
	public static function unschedule_cron_events(): void
	{
		$timestamp = wp_next_scheduled(self::CRON_CLEANUP_SESSIONS);
		if ($timestamp) {
			wp_unschedule_event($timestamp, self::CRON_CLEANUP_SESSIONS);
		}

		$timestamp = wp_next_scheduled(self::CRON_PROCESS_QUEUE);
		if ($timestamp) {
			wp_unschedule_event($timestamp, self::CRON_PROCESS_QUEUE);
		}
	}

	// =========================================================================
	// Uninstall helpers
	// =========================================================================

	/**
	 * Return the ordered list of all core table names.
	 *
	 * Listed in safe drop order (dependents first, then parents).
	 *
	 * @return string[]
	 */
	private static function get_core_table_names(): array
	{
		global $wpdb;

		return [
			$wpdb->prefix . 'wpaic_messages',
			$wpdb->prefix . 'wpaic_sessions',
			$wpdb->prefix . 'wpaic_chunks',
			$wpdb->prefix . 'wpaic_queue',
			$wpdb->prefix . 'wpaic_knowledge',
			$wpdb->prefix . 'wpaic_usage_daily',
			$wpdb->prefix . 'wpaic_bots',
			$wpdb->prefix . 'wpaic_migrations',
		];
	}

	/**
	 * Drop the given tables.
	 *
	 * @param  string[] $tables Fully-qualified table names.
	 * @return void
	 */
	private static function drop_tables(array $tables): void
	{
		global $wpdb;

		foreach ($tables as $table) {
			$table = esc_sql($table);
			$wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		}
	}

	/**
	 * Delete all plugin options from wp_options.
	 *
	 * @return void
	 */
	private static function delete_options(): void
	{
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like('wpaic_') . '%'
			)
		);
	}

	/**
	 * Delete all plugin transients from wp_options.
	 *
	 * @return void
	 */
	private static function delete_transients(): void
	{
		global $wpdb;

		$prefix = $wpdb->esc_like('_transient_wpaic_');
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$prefix . '%',
				$wpdb->esc_like('_transient_timeout_wpaic_') . '%'
			)
		);
	}

	/**
	 * Delete all plugin user meta records.
	 *
	 * @return void
	 */
	private static function delete_user_meta(): void
	{
		global $wpdb;

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s",
				$wpdb->esc_like('wpaic_') . '%'
			)
		);
	}
}
