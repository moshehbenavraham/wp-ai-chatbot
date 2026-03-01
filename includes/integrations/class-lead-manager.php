<?php

/**
 * Lead Manager — CRUD and analytics for captured leads.
 *
 * Stores leads in the `{prefix}wpaic_leads` table (created via the 1.1.0
 * migration). Provides save(), get_leads(), update_status(), delete(),
 * get_stats(), export_csv(), and cleanup_old_leads() operations.
 *
 * All public methods are static (service class pattern used throughout the
 * plugin for similar domain managers).
 *
 * @package WP_AI_Chatbot
 * @since   1.1.0
 */

namespace WPAIC\Integrations;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Lead_Manager
 *
 * @since   1.1.0
 * @package WPAIC\Integrations
 */
class Lead_Manager
{
	// =========================================================================
	// Constants
	// =========================================================================

	/** Lead status: newly captured, not yet followed up. */
	public const STATUS_NEW = 'new';
	/** Lead status: operator has contacted the lead. */
	public const STATUS_CONTACTED = 'contacted';
	/** Lead status: lead has converted (sale, booking, etc.). */
	public const STATUS_CONVERTED = 'converted';
	/** Lead status: lead was dismissed / not relevant. */
	public const STATUS_DISMISSED = 'dismissed';

	/** WP option key for lead capture configuration. */
	public const SETTINGS_OPTION = 'wpaic_lead_capture_settings';

	// =========================================================================
	// Table helper
	// =========================================================================

	/**
	 * Return the fully-qualified leads table name.
	 *
	 * @return string
	 */
	public static function table(): string
	{
		global $wpdb;

		return $wpdb->prefix . 'wpaic_leads';
	}

	// =========================================================================
	// Settings
	// =========================================================================

	/**
	 * Return lead capture settings, merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array
	{
		$defaults = [
			'enabled' => false,
			'notify_email' => '',
			'notify_enabled' => false,
			'retention_days' => 0,    // 0 = keep forever
			'store_ip_hash' => true, // SHA-256 hash, GDPR-friendly
		];

		return (array) wp_parse_args(
			(array) get_option(self::SETTINGS_OPTION, []),
			$defaults
		);
	}

	// =========================================================================
	// CRUD
	// =========================================================================

	/**
	 * Save a new lead captured from the chat.
	 *
	 * At least one of name, email, or phone must be non-empty.
	 *
	 * @param  array $data    Lead fields: name, email, phone, company, interest, notes.
	 * @param  array $context Execution context: session_id, bot_slug, user_id, page_url.
	 * @return array{ok: bool, lead_id: int|null, error?: string, message?: string}
	 */
	public static function save(array $data, array $context = []): array
	{
		global $wpdb;

		$settings = self::get_settings();

		if (empty($settings['enabled'])) {
			return [
				'ok' => false,
				'error' => 'disabled',
				'message' => __('Lead capture is not currently enabled.', 'wp-ai-chatbot'),
			];
		}

		$name = sanitize_text_field($data['name'] ?? '');
		$email = sanitize_email($data['email'] ?? '');
		$phone = sanitize_text_field($data['phone'] ?? '');
		$company = sanitize_text_field($data['company'] ?? '');
		$interest = sanitize_text_field($data['interest'] ?? '');
		$notes = sanitize_textarea_field($data['notes'] ?? '');

		if ('' === $name && '' === $email && '' === $phone) {
			return [
				'ok' => false,
				'error' => 'no_identifier',
				'message' => __('At least a name, email address, or phone number is required.', 'wp-ai-chatbot'),
			];
		}

		$row = [
			'bot_slug' => sanitize_key($context['bot_slug'] ?? ''),
			'session_id' => sanitize_text_field($context['session_id'] ?? ''),
			'name' => $name,
			'email' => $email,
			'phone' => $phone,
			'company' => $company,
			'interest' => $interest,
			'notes' => $notes,
			'status' => self::STATUS_NEW,
			'page_url' => esc_url_raw($context['page_url'] ?? ''),
			'ip_hash' => ! empty($settings['store_ip_hash']) ? self::hash_ip() : null,
			'created_at' => current_time('mysql'),
			'updated_at' => current_time('mysql'),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$inserted = $wpdb->insert(self::table(), $row);

		if (false === $inserted) {
			return [
				'ok' => false,
				'error' => 'db_error',
				'message' => __('Failed to save lead information. Please try again.', 'wp-ai-chatbot'),
			];
		}

		$lead_id = (int) $wpdb->insert_id;

		/**
		 * Fires after a lead is successfully saved to the database.
		 *
		 * @since 1.1.0
		 * @param int   $lead_id ID of the new lead row.
		 * @param array $row     Data saved to the database.
		 * @param array $context Execution context.
		 */
		do_action('wpaic_lead_saved', $lead_id, $row, $context);

		// Optional admin email notification.
		if (! empty($settings['notify_enabled'])) {
			$to = ! empty($settings['notify_email']) ? $settings['notify_email'] : get_option('admin_email');
			if ($to) {
				self::send_notification($row, (string) $to);
			}
		}

		return [
			'ok' => true,
			'lead_id' => $lead_id,
		];
	}

	/**
	 * Retrieve a paginated list of leads.
	 *
	 * @param  array $args {
	 *     @type int    $per_page  Rows per page (default 20).
	 *     @type int    $page      Page number (default 1).
	 *     @type string $orderby   Column (default 'created_at').
	 *     @type string $order     'ASC' or 'DESC' (default 'DESC').
	 *     @type string $status    Filter by status (empty = all).
	 *     @type string $bot_slug  Filter by bot slug.
	 *     @type string $search    Full-text search across name/email/phone/company.
	 *     @type string $date_from YYYY-MM-DD start date filter.
	 *     @type string $date_to   YYYY-MM-DD end date filter.
	 * }
	 * @return array{leads: array, total: int, page: int, per_page: int, total_pages: int}
	 */
	public static function get_leads(array $args = []): array
	{
		global $wpdb;

		$defaults = [
			'per_page' => 20,
			'page' => 1,
			'orderby' => 'created_at',
			'order' => 'DESC',
			'status' => '',
			'bot_slug' => '',
			'search' => '',
			'date_from' => '',
			'date_to' => '',
		];

		$args = wp_parse_args($args, $defaults);
		$table = self::table();
		$where = ['1=1'];
		$params = [];

		if ('' !== $args['status']) {
			$where[] = 'status = %s';
			$params[] = $args['status'];
		}

		if ('' !== $args['bot_slug']) {
			$where[] = 'bot_slug = %s';
			$params[] = $args['bot_slug'];
		}

		if ('' !== $args['search']) {
			$s = '%' . $wpdb->esc_like((string) $args['search']) . '%';
			$where[] = '(name LIKE %s OR email LIKE %s OR phone LIKE %s OR company LIKE %s)';
			$params[] = $s;
			$params[] = $s;
			$params[] = $s;
			$params[] = $s;
		}

		if ('' !== $args['date_from']) {
			$where[] = 'DATE(created_at) >= %s';
			$params[] = $args['date_from'];
		}

		if ('' !== $args['date_to']) {
			$where[] = 'DATE(created_at) <= %s';
			$params[] = $args['date_to'];
		}

		$where_sql = implode(' AND ', $where);

		// Total count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			empty($params) ? $count_sql : $wpdb->prepare($count_sql, $params)  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		// Paged results.
		$safe_cols = ['id', 'name', 'email', 'status', 'created_at'];
		$orderby = in_array($args['orderby'], $safe_cols, true) ? $args['orderby'] : 'created_at';
		$order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
		$per_page = max(1, (int) $args['per_page']);
		$offset = (max(1, (int) $args['page']) - 1) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$data_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_params = $params;
		$query_params[] = $per_page;
		$query_params[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$leads = (array) $wpdb->get_results($wpdb->prepare($data_sql, $query_params), ARRAY_A);  // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return [
			'leads' => $leads,
			'total' => $total,
			'page' => (int) $args['page'],
			'per_page' => $per_page,
			'total_pages' => (int) ceil($total / max(1, $per_page)),
		];
	}

	/**
	 * Update a lead's status.
	 *
	 * @param  int    $id     Lead row ID.
	 * @param  string $status One of the STATUS_* constants.
	 * @return bool Whether the update succeeded.
	 */
	public static function update_status(int $id, string $status): bool
	{
		global $wpdb;

		$valid = [self::STATUS_NEW, self::STATUS_CONTACTED, self::STATUS_CONVERTED, self::STATUS_DISMISSED];

		if (! in_array($status, $valid, true)) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			self::table(),
			['status' => $status, 'updated_at' => current_time('mysql')],
			['id' => $id],
			['%s', '%s'],
			['%d']
		);

		return false !== $result;
	}

	/**
	 * Delete a single lead by ID.
	 *
	 * @param  int $id Lead row ID.
	 * @return bool
	 */
	public static function delete(int $id): bool
	{
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (bool) $wpdb->delete(self::table(), ['id' => $id], ['%d']);
	}

	// =========================================================================
	// Analytics
	// =========================================================================

	/**
	 * Return aggregate statistics for the leads table.
	 *
	 * @return array{total: int, today: int, this_month: int, by_status: array<string, int>}
	 */
	public static function get_stats(): array
	{
		global $wpdb;
		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$today = (int) $wpdb->get_var(
			$wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE DATE(created_at) = %s", current_time('Y-m-d'))
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$this_month = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
				current_time('Y-m-01 00:00:00')
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = (array) $wpdb->get_results(
			"SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status",
			ARRAY_A
		);

		$by_status = [];
		foreach ($rows as $row) {
			$by_status[(string) $row['status']] = (int) $row['cnt'];
		}

		return [
			'total' => $total,
			'today' => $today,
			'this_month' => $this_month,
			'by_status' => $by_status,
		];
	}

	// =========================================================================
	// Export
	// =========================================================================

	/**
	 * Export all matching leads to a CSV string.
	 *
	 * Accepts the same filter args as get_leads(). Pagination is ignored
	 * (exports all matching rows up to 9,999).
	 *
	 * @param  array $args Same filter args as get_leads().
	 * @return string CSV content.
	 */
	public static function export_csv(array $args = []): string
	{
		$args['per_page'] = 9999;
		$args['page'] = 1;

		$result = self::get_leads($args);

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen('php://temp', 'r+');

		fputcsv($handle, [
			'ID', 'Name', 'Email', 'Phone', 'Company',
			'Interest', 'Notes', 'Bot', 'Status', 'Page', 'Date',
		]);

		foreach ($result['leads'] as $lead) {
			fputcsv($handle, [
				$lead['id'],
				$lead['name'],
				$lead['email'],
				$lead['phone'],
				$lead['company'],
				$lead['interest'],
				$lead['notes'],
				$lead['bot_slug'],
				$lead['status'],
				$lead['page_url'],
				$lead['created_at'],
			]);
		}

		rewind($handle);
		$csv = (string) stream_get_contents($handle);
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose($handle);

		return $csv;
	}

	// =========================================================================
	// Retention cleanup (hooked to wpaic_daily_cleanup cron)
	// =========================================================================

	/**
	 * Delete leads older than the configured retention period.
	 *
	 * Called by the `wpaic_daily_cleanup` cron event. Does nothing when
	 * retention_days is 0 (keep forever).
	 *
	 * @return int Number of rows deleted.
	 */
	public static function cleanup_old_leads(): int
	{
		global $wpdb;

		$settings = self::get_settings();

		if (empty($settings['retention_days']) || (int) $settings['retention_days'] <= 0) {
			return 0;
		}

		$cutoff = wp_date('Y-m-d H:i:s', (int) strtotime('-' . (int) $settings['retention_days'] . ' days'));

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query(
			$wpdb->prepare("DELETE FROM {$wpdb->prefix}wpaic_leads WHERE created_at < %s", $cutoff)
		);
	}

	// =========================================================================
	// Private helpers
	// =========================================================================

	/**
	 * Hash the requester's IP address for GDPR-compliant storage.
	 *
	 * @return string|null SHA-256 hex hash, or null if IP cannot be determined.
	 */
	private static function hash_ip(): ?string
	{
		$ip = '';

		if (! empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CLIENT_IP']));
		} elseif (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$parts = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
			$ip = trim($parts[0]);
		} elseif (! empty($_SERVER['REMOTE_ADDR'])) {
			$ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
		}

		return $ip ? hash('sha256', $ip . wp_salt()) : null;
	}

	/**
	 * Send an admin notification email when a new lead is captured.
	 *
	 * @param  array  $lead Row data as saved to the database.
	 * @param  string $to   Recipient email address.
	 * @return void
	 */
	private static function send_notification(array $lead, string $to): void
	{
		$subject = sprintf(
			/* translators: %s: site name */
			__('[%s] New Lead Captured', 'wp-ai-chatbot'),
			get_bloginfo('name')
		);

		$message = sprintf(
			/* translators: 1–7: lead fields */
			__(
				"A new lead has been captured.\n\nName: %1\$s\nEmail: %2\$s\nPhone: %3\$s\nCompany: %4\$s\nInterest: %5\$s\n\nBot: %6\$s\nDate: %7\$s",
				'wp-ai-chatbot'
			),
			$lead['name'] ?: '—',
			$lead['email'] ?: '—',
			$lead['phone'] ?: '—',
			$lead['company'] ?: '—',
			$lead['interest'] ?: '—',
			$lead['bot_slug'] ?: 'default',
			$lead['created_at']
		);

		wp_mail($to, $subject, $message);
	}
}
