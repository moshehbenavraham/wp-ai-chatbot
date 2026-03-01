<?php

/**
 * Appointments Manager Class
 *
 * Handles all appointment operations: CRUD, availability checking,
 * scheduling logic, and integration with external booking plugins.
 * Uses the Adapter pattern to delegate to the appropriate booking system.
 *
 * @package AIChat
 * @subpackage Appointments
 * @since 2.3.0
 */

if (! defined('ABSPATH')) {
	exit;
}

class AIChat_Appointments_Manager
{
	/** @var string Table name */
	private static $table_name;

	/** @var array Cached settings */
	private static $settings_cache = null;

	/** @var array Registered adapters */
	private static $adapters = [];

	/**
	 * Register an adapter
	 *
	 * @param string $id Adapter ID (internal, bookly, amelia)
	 * @param AIChat_Appointments_Adapter_Interface $adapter Adapter instance
	 */
	public static function register_adapter($id, $adapter)
	{
		self::$adapters[ $id ] = $adapter;
	}

	/**
	 * Get registered adapters
	 */
	public static function get_adapters()
	{
		return self::$adapters;
	}

	/**
	 * Get available destinations (adapters that are installed/available)
	 */
	public static function get_available_destinations()
	{
		$destinations = [];

		foreach (self::$adapters as $id => $adapter) {
			$info = $adapter->get_info();
			$destinations[ $id ] = [
				'id' => $id,
				'name' => $info['name'],
				'description' => $info['description'],
				'icon' => $info['icon'],
				'available' => $adapter->is_available(),
			];
		}

		return $destinations;
	}

	/**
	 * Get the current adapter based on settings
	 *
	 * @return AIChat_Appointments_Adapter_Interface|null
	 */
	public static function get_adapter()
	{
		$settings = self::get_settings();
		$destination = $settings['destination'] ?? 'internal';

		if (isset(self::$adapters[ $destination ]) && self::$adapters[ $destination ]->is_available()) {
			return self::$adapters[ $destination ];
		}

		// Fallback to internal
		return self::$adapters['internal'] ?? null;
	}

	/**
	 * Get a specific adapter by ID
	 */
	public static function get_adapter_by_id($id)
	{
		return self::$adapters[ $id ] ?? null;
	}

	/**
	 * Get the appointments table name
	 */
	public static function get_table_name()
	{
		global $wpdb;
		if (! self::$table_name) {
			self::$table_name = $wpdb->prefix . 'aichat_appointments';
		}

		return self::$table_name;
	}

	/**
	 * Create the appointments table
	 */
	public static function create_table()
	{
		global $wpdb;
		$table = self::get_table_name();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            booking_code VARCHAR(20) NOT NULL,
            customer_name VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_phone VARCHAR(50) DEFAULT '',
            service VARCHAR(255) DEFAULT '',
            staff VARCHAR(255) DEFAULT '',
            appointment_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            timezone VARCHAR(50) DEFAULT 'Europe/Madrid',
            status VARCHAR(20) DEFAULT 'pending',
            notes TEXT,
            source VARCHAR(50) DEFAULT 'chat',
            bot_slug VARCHAR(100) DEFAULT '',
            session_id VARCHAR(100) DEFAULT '',
            external_id VARCHAR(100) DEFAULT '',
            external_source VARCHAR(50) DEFAULT '',
            reminder_sent TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY booking_code (booking_code),
            KEY appointment_date (appointment_date),
            KEY status (status),
            KEY customer_email (customer_email),
            KEY external_id (external_id)
        ) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		// Migration: add staff column if missing (for existing installations)
		self::maybe_add_staff_column();

		// Also create slots/availability table
		self::create_availability_table();

		aichat_appointments_log('Table created/updated', ['table' => $table]);
	}

	/**
	 * Add staff column if missing (migration for existing installations)
	 */
	private static function maybe_add_staff_column()
	{
		global $wpdb;
		$table = self::get_table_name();

		// Check if column exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$column_exists = $wpdb->get_results($wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
			"SHOW COLUMNS FROM `$table` LIKE %s",
			'staff'
		));

		if (empty($column_exists)) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
			$wpdb->query("ALTER TABLE `$table` ADD COLUMN `staff` VARCHAR(255) DEFAULT '' AFTER `service`");
			aichat_appointments_log('Migration: added staff column to appointments table');
		}
	}

	/**
	 * Create availability configuration table
	 */
	private static function create_availability_table()
	{
		global $wpdb;
		$table = $wpdb->prefix . 'aichat_appointment_slots';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            day_of_week TINYINT NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            slot_duration INT DEFAULT 30,
            max_bookings INT DEFAULT 1,
            is_active TINYINT(1) DEFAULT 1,
            service VARCHAR(255) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY day_of_week (day_of_week),
            KEY is_active (is_active)
        ) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
	}

	/**
	 * Get settings with defaults
	 */
	public static function get_settings()
	{
		if (self::$settings_cache !== null) {
			return self::$settings_cache;
		}

		$defaults = [
			'destination' => 'internal',  // internal, bookly, amelia
			'timezone' => wp_timezone_string(),
			'slot_duration' => 30,          // minutes
			'buffer_before' => 0,           // minutes before appointment
			'buffer_after' => 0,           // minutes after appointment
			'min_advance' => 60,          // minimum minutes in advance to book
			'max_advance_days' => 30,          // maximum days in advance
			'auto_confirm' => true,        // auto-confirm or require manual
			'send_confirmation' => true,        // send email on booking
			'send_reminder' => true,        // send reminder
			'reminder_time' => 24,          // time before to send reminder
			'reminder_unit' => 'hours',     // 'hours' or 'days'
			'confirmation_email_subject' => '', // custom subject (empty = default)
			'confirmation_email_body' => '', // custom body (empty = default)
			'reminder_email_subject' => '', // custom subject (empty = default)
			'reminder_email_body' => '', // custom body (empty = default)
			'services' => [],          // available services (for internal)
			'working_hours' => self::get_default_working_hours(),
			'blocked_dates' => [],          // specific dates blocked

			// Bookly-specific settings
			'bookly_service_mode' => 'default', // 'default' (use configured) or 'ask' (bot asks customer)
			'bookly_service_id' => 0,         // default service ID when mode='default'
			'bookly_staff_mode' => 'any',     // 'any' (first available), 'default' (configured), 'ask' (bot asks)
			'bookly_staff_id' => 0,         // default staff ID when mode='default'

			// Amelia-specific settings (future)
			'amelia_service_mode' => 'default',
			'amelia_service_id' => 0,
			'amelia_staff_mode' => 'any',
			'amelia_staff_id' => 0,
		];

		$saved = get_option('aichat_appointments_settings', []);
		self::$settings_cache = wp_parse_args($saved, $defaults);

		return self::$settings_cache;
	}

	/**
	 * Get default working hours
	 */
	private static function get_default_working_hours()
	{
		return [
			1 => ['enabled' => true,  'start' => '09:00', 'end' => '18:00'], // Monday
			2 => ['enabled' => true,  'start' => '09:00', 'end' => '18:00'], // Tuesday
			3 => ['enabled' => true,  'start' => '09:00', 'end' => '18:00'], // Wednesday
			4 => ['enabled' => true,  'start' => '09:00', 'end' => '18:00'], // Thursday
			5 => ['enabled' => true,  'start' => '09:00', 'end' => '18:00'], // Friday
			6 => ['enabled' => false, 'start' => '10:00', 'end' => '14:00'], // Saturday
			0 => ['enabled' => false, 'start' => '10:00', 'end' => '14:00'], // Sunday
		];
	}

	/**
	 * Save settings
	 */
	public static function save_settings($settings)
	{
		self::$settings_cache = null;

		return update_option('aichat_appointments_settings', $settings);
	}

	/**
	 * Generate unique booking code
	 */
	public static function generate_booking_code()
	{
		$prefix = 'APT';
		$unique = strtoupper(substr(md5(uniqid(wp_rand(), true)), 0, 8));

		return $prefix . '-' . $unique;
	}

	/**
	 * Get available slots for a given date
	 * Delegates to the current adapter
	 *
	 * @param string $date Date in Y-m-d format
	 * @param array  $params Optional params (service, staff, etc.)
	 * @return array Available time slots
	 */
	public static function get_available_slots($date, $params = [])
	{
		$adapter = self::get_adapter();

		aichat_log_debug('[Appointments Manager] get_available_slots called', [
			'date' => $date,
			'params' => $params,
			'adapter' => $adapter ? get_class($adapter) : 'none',
			'adapter_available' => $adapter ? $adapter->is_available() : false,
		]);

		if (! $adapter) {
			aichat_log_debug('[Appointments Manager] No adapter available!');

			return [];
		}

		// Support legacy string $service param
		if (is_string($params)) {
			$params = ['service' => $params];
		}

		$result = $adapter->get_available_slots($date, $params);

		aichat_log_debug('[Appointments Manager] get_available_slots result', [
			'date' => $date,
			'is_error' => is_wp_error($result),
			'slots_count' => is_array($result) ? count($result) : 'N/A',
		]);

		return $result;
	}

	/**
	 * Get services from current adapter
	 */
	public static function get_services()
	{
		$adapter = self::get_adapter();

		return $adapter ? $adapter->get_services() : [];
	}

	/**
	 * Get staff from current adapter
	 */
	public static function get_staff($service_id = 0)
	{
		$adapter = self::get_adapter();

		return $adapter ? $adapter->get_staff($service_id) : [];
	}

	/**
	 * Get booked slot times for a date (internal helper)
	 */
	private static function get_booked_slots($date)
	{
		global $wpdb;
		$table = self::get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$results = $wpdb->get_col($wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
			"SELECT TIME_FORMAT(start_time, '%%H:%%i') FROM $table 
             WHERE appointment_date = %s 
             AND status NOT IN ('cancelled', 'no_show')",
			$date
		));

		return $results ?: [];
	}

	/**
	 * Book an appointment
	 * Delegates to the current adapter
	 *
	 * @param array $data Appointment data
	 * @return array|WP_Error Result with booking code or error
	 */
	public static function book($data)
	{
		aichat_log_debug('[Appointments Manager] book START', $data);

		// Check usage limits (freemium)
		$limit_check = self::check_usage_limit();
		if (is_wp_error($limit_check)) {
			aichat_log_debug('[Appointments Manager] book limit check FAILED');

			return $limit_check;
		}

		$settings = self::get_settings();
		$destination = $settings['destination'] ?? 'internal';

		// PRO integrations check - Standard+ plan required for external integrations
		$pro_destinations = ['bookly', 'amelia', 'ssa', 'google_calendar'];
		if (in_array($destination, $pro_destinations, true) && ! self::has_integrations_license()) {
			aichat_log_debug('[Appointments Manager] Integrations destination blocked (Standard+ required), forcing internal', [
				'requested' => $destination,
			]);
			// Override to use internal adapter
			$adapter = self::$adapters['internal'] ?? null;
		} else {
			$adapter = self::get_adapter();
		}

		aichat_log_debug('[Appointments Manager] book adapter info', [
			'adapter_class' => $adapter ? get_class($adapter) : 'null',
			'destination' => $settings['destination'] ?? 'unknown',
		]);

		if (! $adapter) {
			return new WP_Error('no_adapter', __('No booking system configured', 'axiachat-ai'));
		}

		aichat_log_debug('[Appointments Manager] book calling adapter->book');
		$result = $adapter->book($data);

		aichat_log_debug('[Appointments Manager] book adapter result', [
			'is_wp_error' => is_wp_error($result),
			'result_type' => gettype($result),
			'result' => is_wp_error($result) ? $result->get_error_message() : (is_array($result) ? $result : 'other'),
		]);

		// Send confirmation email if successful
		if (! is_wp_error($result) && ! empty($result['success'])) {
			$settings = self::get_settings();
			if ($settings['send_confirmation'] && ! empty($result['id'])) {
				self::send_confirmation_email($result['id']);
			}

			// Send webhook if configured (independent of destination)
			self::send_webhook($data, $result);

			// Track for external adapters
			if ($settings['destination'] !== 'internal') {
				self::increment_external_count();
			}
		}

		return $result;
	}

	/**
	 * Cancel an appointment
	 * Delegates to the current adapter
	 *
	 * @param string $booking_code The booking code
	 * @param string $email Customer email for verification (optional)
	 * @return array|WP_Error
	 */
	public static function cancel($booking_code, $email = '')
	{
		$adapter = self::get_adapter();
		if (! $adapter) {
			return new WP_Error('no_adapter', __('No booking system configured', 'axiachat-ai'));
		}

		// Get appointment first for email verification if needed
		if ($email) {
			$appointment = $adapter->get_by_code($booking_code);
			if ($appointment && strtolower($appointment->customer_email) !== strtolower($email)) {
				return new WP_Error('email_mismatch', __('Email does not match the booking', 'axiachat-ai'));
			}
		}

		$result = $adapter->cancel($booking_code);

		// Send cancellation email if successful
		if ($result === true || (is_array($result) && ! empty($result['success']))) {
			$appointment = $adapter->get_by_code($booking_code);
			if ($appointment) {
				self::send_cancellation_email($appointment->id);
			}
		}

		return $result;
	}

	/**
	 * Get appointment by ID or booking code
	 * Delegates to the current adapter
	 */
	public static function get($id_or_code)
	{
		$adapter = self::get_adapter();
		if (! $adapter) {
			return null;
		}

		if (is_numeric($id_or_code)) {
			return $adapter->get($id_or_code);
		}

		return $adapter->get_by_code($id_or_code);
	}

	/**
	 * Update appointment status
	 * Delegates to the current adapter
	 */
	public static function update_status($id, $status)
	{
		$adapter = self::get_adapter();
		if (! $adapter) {
			return new WP_Error('no_adapter', __('No booking system configured', 'axiachat-ai'));
		}

		return $adapter->update_status($id, $status);
	}

	/**
	 * Update appointment data
	 * Delegates to the current adapter
	 */
	public static function update($id, $data)
	{
		$adapter = self::get_adapter();
		if (! $adapter) {
			return new WP_Error('no_adapter', __('No booking system configured', 'axiachat-ai'));
		}

		return $adapter->update($id, $data);
	}

	/**
	 * Get appointments list with filters
	 * Delegates to the current adapter (but also works directly for admin views)
	 */
	public static function get_list($args = [])
	{
		// For admin views, always query our tracking table directly
		global $wpdb;
		$table = self::get_table_name();

		$defaults = [
			'status' => '',
			'date_from' => '',
			'date_to' => '',
			'search' => '',
			'orderby' => 'appointment_date',
			'order' => 'ASC',
			'limit' => 20,
			'offset' => 0,
		];

		$args = wp_parse_args($args, $defaults);

		$where = ['1=1'];
		$values = [];

		if ($args['status']) {
			$where[] = 'status = %s';
			$values[] = $args['status'];
		}

		if ($args['date_from']) {
			$where[] = 'appointment_date >= %s';
			$values[] = $args['date_from'];
		}

		if ($args['date_to']) {
			$where[] = 'appointment_date <= %s';
			$values[] = $args['date_to'];
		}

		if ($args['search']) {
			$like = '%' . $wpdb->esc_like($args['search']) . '%';
			$where[] = '(customer_name LIKE %s OR customer_email LIKE %s OR booking_code LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = implode(' AND ', $where);
		$orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']) ?: 'appointment_date ASC';

		$sql = "SELECT * FROM $table WHERE $where_sql ORDER BY $orderby, start_time ASC LIMIT %d OFFSET %d";
		$values[] = $args['limit'];
		$values[] = $args['offset'];

		if (! empty($values)) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql uses plugin-controlled table name; prepared here.
			$sql = $wpdb->prepare($sql, $values);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- $sql prepared above or contains only safe table/column names.
		return $wpdb->get_results($sql);
	}

	/**
	 * Get stats
	 */
	public static function get_stats()
	{
		global $wpdb;
		$table = self::get_table_name();

		$today = current_time('Y-m-d');

		return [
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
			'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table"),
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			'today' => (int) $wpdb->get_var($wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
				"SELECT COUNT(*) FROM $table WHERE appointment_date = %s",
				$today
			)),
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
			'upcoming' => (int) $wpdb->get_var($wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
				"SELECT COUNT(*) FROM $table WHERE appointment_date >= %s AND status NOT IN ('cancelled', 'completed', 'no_show')",
				$today
			)),
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
			'pending' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'pending'"),
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
			'confirmed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'confirmed'"),
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
			'cancelled' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'cancelled'"),
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
			'completed' => (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'completed'"),
		];
	}

	/**
	 * Send confirmation email
	 */
	public static function send_confirmation_email($appointment_id)
	{
		$apt = self::get($appointment_id);
		if (! $apt) {
			return false;
		}

		$settings = self::get_settings();
		$site_name = get_bloginfo('name');

		$date_formatted = date_i18n(get_option('date_format'), strtotime($apt->appointment_date));
		$time_formatted = date_i18n(get_option('time_format'), strtotime($apt->start_time));

		// Placeholders for template
		$placeholders = [
			'{customer_name}' => $apt->customer_name,
			'{booking_code}' => $apt->booking_code,
			'{date}' => $date_formatted,
			'{time}' => $time_formatted,
			'{service}' => $apt->service ?? '',
			'{site_name}' => $site_name,
		];

		// Subject - use custom or default
		if (! empty($settings['confirmation_email_subject'])) {
			$subject = strtr($settings['confirmation_email_subject'], $placeholders);
		} else {
			$subject = sprintf(
				/* translators: 1: Site name, 2: Booking code */
				__('[%1$s] Appointment Confirmation - %2$s', 'axiachat-ai'),
				$site_name,
				$apt->booking_code
			);
		}

		// Body - use custom or default
		if (! empty($settings['confirmation_email_body'])) {
			$message = strtr($settings['confirmation_email_body'], $placeholders);
		} else {
			$message = sprintf(
				/* translators: 1: Customer name, 2: Appointment date, 3: Appointment time, 4: Booking code, 5: Site name */
				__("Hello %1\$s,\n\nYour appointment has been confirmed.\n\nDetails:\n- Date: %2\$s\n- Time: %3\$s\n- Confirmation Code: %4\$s\n\nTo cancel or reschedule, please contact us with your confirmation code.\n\nThank you!\n%5\$s", 'axiachat-ai'),
				$apt->customer_name,
				$date_formatted,
				$time_formatted,
				$apt->booking_code,
				$site_name
			);
		}

		return wp_mail($apt->customer_email, $subject, $message);
	}

	/**
	 * Send reminder email
	 */
	public static function send_reminder_email($appointment_id)
	{
		$apt = self::get($appointment_id);
		if (! $apt) {
			return false;
		}

		$settings = self::get_settings();
		$site_name = get_bloginfo('name');

		$date_formatted = date_i18n(get_option('date_format'), strtotime($apt->appointment_date));
		$time_formatted = date_i18n(get_option('time_format'), strtotime($apt->start_time));

		// Calculate hours until appointment
		$apt_datetime = strtotime($apt->appointment_date . ' ' . $apt->start_time);
		$hours_until = max(0, round(($apt_datetime - time()) / 3600));

		// Placeholders for template
		$placeholders = [
			'{customer_name}' => $apt->customer_name,
			'{booking_code}' => $apt->booking_code,
			'{date}' => $date_formatted,
			'{time}' => $time_formatted,
			'{service}' => $apt->service ?? '',
			'{site_name}' => $site_name,
			'{hours_until}' => $hours_until,
		];

		// Subject - use custom or default
		if (! empty($settings['reminder_email_subject'])) {
			$subject = strtr($settings['reminder_email_subject'], $placeholders);
		} else {
			$subject = sprintf(
				/* translators: 1: Site name, 2: Appointment date */
				__('[%1$s] Appointment Reminder - %2$s', 'axiachat-ai'),
				$site_name,
				$date_formatted
			);
		}

		// Body - use custom or default
		if (! empty($settings['reminder_email_body'])) {
			$message = strtr($settings['reminder_email_body'], $placeholders);
		} else {
			$message = sprintf(
				/* translators: 1: Customer name, 2: Appointment date, 3: Appointment time, 4: Booking code, 5: Site name */
				__("Hello %1\$s,\n\nThis is a friendly reminder about your upcoming appointment.\n\nDetails:\n- Date: %2\$s\n- Time: %3\$s\n- Confirmation Code: %4\$s\n\nIf you need to cancel or reschedule, please contact us as soon as possible.\n\nWe look forward to seeing you!\n%5\$s", 'axiachat-ai'),
				$apt->customer_name,
				$date_formatted,
				$time_formatted,
				$apt->booking_code,
				$site_name
			);
		}

		// Mark reminder as sent
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			self::get_table_name(),
			['reminder_sent' => 1],
			['id' => $appointment_id],
			['%d'],
			['%d']
		);

		return wp_mail($apt->customer_email, $subject, $message);
	}

	/**
	 * Process pending reminders (called by cron)
	 * Only processes internal destination appointments
	 */
	public static function process_pending_reminders()
	{
		$settings = self::get_settings();

		// Only process if destination is internal and reminders are enabled
		if ($settings['destination'] !== 'internal' || empty($settings['send_reminder'])) {
			return 0;
		}

		$reminder_time = intval($settings['reminder_time'] ?? 24);
		$reminder_unit = $settings['reminder_unit'] ?? 'hours';

		// Convert to hours
		$reminder_hours = ($reminder_unit === 'days') ? $reminder_time * 24 : $reminder_time;

		global $wpdb;
		$table = self::get_table_name();

		// Calculate the time window for reminders
		// Appointments that are between now and (now + reminder_hours)
		$now = current_time('mysql');
		$reminder_threshold = wp_date('Y-m-d H:i:s', strtotime('+' . $reminder_hours . ' hours'));

		// Get appointments that need reminders:
		// - source = 'internal' (only internal appointments)
		// - status = 'confirmed' (only confirmed appointments)
		// - reminder_sent = 0 (not already sent)
		// - appointment is within the reminder window
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$appointments = $wpdb->get_results($wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
			"SELECT * FROM $table 
             WHERE source = 'internal'
             AND status = 'confirmed'
             AND reminder_sent = 0
             AND CONCAT(appointment_date, ' ', start_time) > %s
             AND CONCAT(appointment_date, ' ', start_time) <= %s",
			$now,
			$reminder_threshold
		));

		$sent_count = 0;

		foreach ($appointments as $apt) {
			$result = self::send_reminder_email($apt->id);
			if ($result) {
				$sent_count++;
				aichat_appointments_log('Reminder sent', [
					'appointment_id' => $apt->id,
					'customer_email' => $apt->customer_email,
					'booking_code' => $apt->booking_code,
				]);
			}
		}

		return $sent_count;
	}

	/**
	 * Send cancellation email
	 */
	public static function send_cancellation_email($appointment_id)
	{
		$apt = self::get($appointment_id);
		if (! $apt) {
			return false;
		}

		$site_name = get_bloginfo('name');

		$subject = sprintf(
			/* translators: 1: Site name, 2: Booking code */
			__('[%1$s] Appointment Cancelled - %2$s', 'axiachat-ai'),
			$site_name,
			$apt->booking_code
		);

		$message = sprintf(
			/* translators: 1: Customer name, 2: Booking code, 3: Site name */
			__("Hello %1\$s,\n\nYour appointment (Code: %2\$s) has been cancelled.\n\nIf you would like to book a new appointment, please visit our website.\n\nThank you!\n%3\$s", 'axiachat-ai'),
			$apt->customer_name,
			$apt->booking_code,
			$site_name
		);

		return wp_mail($apt->customer_email, $subject, $message);
	}

	// =========================================================================
	// FREEMIUM LIMIT METHODS (same pattern as Leads)
	// =========================================================================

	/**
	 * Get total appointments count (for freemium limit)
	 */
	public static function get_total_appointments_count()
	{
		global $wpdb;
		$table = self::get_table_name();

		// Count internal appointments
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
		$internal_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE external_source = ''");

		// Count external appointments tracked
		$external_count = (int) get_option('aichat_appointments_external_count', 0);

		return $internal_count + $external_count;
	}

	/**
	 * Increment external appointments counter
	 */
	public static function increment_external_count()
	{
		$current = (int) get_option('aichat_appointments_external_count', 0);
		update_option('aichat_appointments_external_count', $current + 1);
	}

	/**
	 * Get free plan limit
	 * Free: 50, Paid (Starter+): unlimited
	 */
	public static function get_free_limit()
	{
		// Use centralized limit function if available
		if (function_exists('aichat_max_appointments_allowed')) {
			$limit = aichat_max_appointments_allowed();

			// Convert PHP_INT_MAX to a large number for display
			return $limit >= PHP_INT_MAX ? 999999 : $limit;
		}

		return apply_filters('aichat_appointments_free_limit', 50);
	}

	/**
	 * Check if user has Pro license (unlimited appointments)
	 * Uses Feature ID 26991: Unlimited Appointments
	 */
	public static function has_pro_license()
	{
		// Use centralized feature check (Feature ID 26991: Unlimited Appointments)
		if (function_exists('aichat_has_feature') && defined('AICHAT_FEATURE_UNLIMITED_APPOINTMENTS')) {
			return aichat_has_feature(AICHAT_FEATURE_UNLIMITED_APPOINTMENTS);
		}
		// Fallback to legacy check
		if (function_exists('aichat_is_pro') && aichat_is_pro()) {
			return true;
		}

		return apply_filters('aichat_appointments_has_pro', false);
	}

	/**
	 * Check if user has Integrations license (Bookly, Amelia, SSA, Google Calendar)
	 * Uses Feature ID 26994: Appointments Integrations (Standard+ plan)
	 *
	 * @return bool True if Integrations license is active
	 */
	public static function has_integrations_license()
	{
		// Use centralized feature check (Feature ID 26994: Appointments Integrations)
		if (function_exists('aichat_can_use_appointments_integrations')) {
			return aichat_can_use_appointments_integrations();
		}

		// Fallback: Use central Freemius check for plan level
		if (function_exists('aichat_is_plan')) {
			return aichat_is_plan('standard'); // Standard+ required
		}

		// Fallback: check if has_pro_license (for legacy behavior)
		return self::has_pro_license();
	}

	/**
	 * Check usage limit before booking
	 */
	public static function check_usage_limit()
	{
		if (self::has_pro_license()) {
			return true;
		}

		$count = self::get_total_appointments_count();
		$limit = self::get_free_limit();

		if ($count >= $limit) {
			return new WP_Error(
				'limit_reached',
				sprintf(
					/* translators: %d: Maximum number of appointments in free plan */
					__('Free plan limit reached (%d appointments). Upgrade to Pro for unlimited appointments.', 'axiachat-ai'),
					$limit
				)
			);
		}

		return true;
	}

	/**
	 * Get usage info for UI
	 */
	public static function get_usage_info()
	{
		$count = self::get_total_appointments_count();
		$limit = self::get_free_limit();
		$is_pro = self::has_pro_license();

		return [
			'count' => $count,
			'limit' => $limit,
			'remaining' => max(0, $limit - $count),
			'is_pro' => $is_pro,
			'percent' => $limit > 0 ? min(100, round(($count / $limit) * 100)) : 0,
		];
	}

	/**
	 * Send webhook notification
	 *
	 * @param array $data Original booking data
	 * @param array $result Booking result (contains id, booking_code, etc.)
	 */
	private static function send_webhook($data, $result)
	{
		// Webhooks are PRO-only feature
		if (! self::has_pro_license()) {
			return;
		}

		$webhook_url = get_option('aichat_appointments_webhook_url', '');

		if (empty($webhook_url)) {
			return;
		}

		// Build webhook payload
		$payload = [
			'name' => $data['customer_name'] ?? '',
			'email' => $data['customer_email'] ?? '',
			'phone' => $data['customer_phone'] ?? '',
			'date' => $data['date'] ?? '',
			'time' => $data['time'] ?? '',
			'service' => $data['service'] ?? '',
			'notes' => $data['notes'] ?? '',
			'confirmation_code' => $result['booking_code'] ?? '',
			'appointment_id' => $result['id'] ?? null,
			'source' => home_url(),
			'created_at' => current_time('c'), // ISO 8601 format
		];

		// Allow filtering the payload
		$payload = apply_filters('aichat_appointments_webhook_payload', $payload, $data, $result);

		// Send POST request
		$response = wp_remote_post($webhook_url, [
			'timeout' => 15,
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body' => wp_json_encode($payload),
			'sslverify' => true,
		]);

		// Log result
		if (function_exists('aichat_log_debug')) {
			if (is_wp_error($response)) {
				aichat_log_debug('[Appointments] Webhook error', [
					'url' => $webhook_url,
					'error' => $response->get_error_message(),
				]);
			} else {
				aichat_log_debug('[Appointments] Webhook sent', [
					'url' => $webhook_url,
					'status' => wp_remote_retrieve_response_code($response),
				]);
			}
		}
	}
}
