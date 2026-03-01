<?php

/**
 * Appointments Add-on Integration with AI Tools
 *
 * Registers appointment tools using aichat_register_tool_safe() for proper
 * integration with the AI Tools system and bot configuration.
 *
 * Schema generation is dynamic based on destination (internal/bookly/amelia)
 * and configuration (service_mode, staff_mode).
 *
 * @package AxiaChat_AI
 * @subpackage Appointments
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Register appointment tools with the AI Tools system.
 */
add_action('init', 'aichat_appointments_register_tools_safe', 25);

function aichat_appointments_register_tools_safe()
{
	// Ensure the AI Tools API is available
	if (! function_exists('aichat_register_tool_safe')) {
		return;
	}

	// Ensure the manager class exists
	if (! class_exists('AIChat_Appointments_Manager')) {
		return;
	}

	// Get current settings for dynamic schema generation
	$settings = AIChat_Appointments_Manager::get_settings();
	$destination = $settings['destination'] ?? 'internal';

	// Tool 1: Get Available Slots
	$slots_schema = aichat_appointments_build_get_slots_schema($settings);
	aichat_register_tool_safe('get_available_slots', [
		'type' => 'function',
		'name' => 'get_available_slots',
		'description' => aichat_appointments_get_slots_description($settings),
		'activity_label' => __('Checking availability...', 'axiachat-ai'),
		'schema' => $slots_schema,
		'callback' => 'aichat_appointments_tool_get_slots_callback',
		'timeout' => 10,
		'parallel' => false,
	]);

	// Tool 2: Book Appointment - with dynamic schema based on destination
	$book_schema = aichat_appointments_build_book_schema($settings);
	aichat_register_tool_safe('book_appointment', [
		'type' => 'function',
		'name' => 'book_appointment',
		'description' => aichat_appointments_get_book_description($settings),
		'activity_label' => __('Booking appointment...', 'axiachat-ai'),
		'schema' => $book_schema,
		'callback' => 'aichat_appointments_tool_book_callback',
		'timeout' => 15,
		'parallel' => false,
		'max_calls' => 1,
	]);

	// Tool 3: Cancel Appointment
	aichat_register_tool_safe('cancel_appointment', [
		'type' => 'function',
		'name' => 'cancel_appointment',
		'description' => __('Cancel an existing appointment using its confirmation code. The code starts with APT- and was provided when the appointment was booked.', 'axiachat-ai'),
		'activity_label' => __('Cancelling appointment...', 'axiachat-ai'),
		'schema' => [
			'type' => 'object',
			'properties' => [
				'booking_code' => [
					'type' => 'string',
					'description' => __('The appointment confirmation code (starts with APT-)', 'axiachat-ai'),
				],
			],
			'required' => ['booking_code'],
		],
		'callback' => 'aichat_appointments_tool_cancel_callback',
		'timeout' => 10,
		'parallel' => false,
		'max_calls' => 1,
	]);

	// Tool 4: Get Available Services (for external destinations like Bookly/Amelia)
	if ($destination !== 'internal') {
		aichat_register_tool_safe('get_appointment_services', [
			'type' => 'function',
			'name' => 'get_appointment_services',
			'description' => __('Get the list of available services that can be booked. Call this to show customers what services are available before checking availability or booking.', 'axiachat-ai'),
			'activity_label' => __('Getting services...', 'axiachat-ai'),
			'schema' => [
				'type' => 'object',
				'properties' => new stdClass(),  // Must be {} not [] for OpenAI
				'required' => [],
			],
			'callback' => 'aichat_appointments_tool_get_services_callback',
			'timeout' => 10,
			'parallel' => true,
		]);

		// Tool 5: Get Available Staff
		aichat_register_tool_safe('get_appointment_staff', [
			'type' => 'function',
			'name' => 'get_appointment_staff',
			'description' => __('Get the list of available staff members. Optionally filter by service to see which staff can provide a specific service.', 'axiachat-ai'),
			'activity_label' => __('Getting staff...', 'axiachat-ai'),
			'schema' => [
				'type' => 'object',
				'properties' => [
					'service' => [
						'type' => 'string',
						'description' => __('Optional: Filter staff by service name to see who can provide that service', 'axiachat-ai'),
					],
				],
				'required' => [],
			],
			'callback' => 'aichat_appointments_tool_get_staff_callback',
			'timeout' => 10,
			'parallel' => true,
		]);
	}

	// Register macro for bot configuration
	$macro_tools = ['get_available_slots', 'book_appointment', 'cancel_appointment'];
	if ($destination !== 'internal') {
		$macro_tools = array_merge(['get_appointment_services', 'get_appointment_staff'], $macro_tools);
	}

	if (function_exists('aichat_register_macro')) {
		aichat_register_macro([
			'name' => 'appointment_booking',
			'label' => __('Appointment Booking', 'axiachat-ai'),
			'description' => __('Enables the bot to check availability, book appointments, and handle cancellations during conversations.', 'axiachat-ai'),
			'tools' => $macro_tools,
			'source' => 'local',
			'source_ref' => 'axiachat_appointments',
		]);
	}
}

/**
 * Build dynamic schema for get_available_slots based on settings
 *
 * @param array $settings Current appointment settings
 * @return array Schema definition
 */
function aichat_appointments_build_get_slots_schema($settings)
{
	$destination = $settings['destination'] ?? 'internal';

	$properties = [
		'range_start' => [
			'type' => 'string',
			'description' => __('Start date/time for availability check. Format: Y-m-d H:i:s or Y-m-d (site local time). Default: now', 'axiachat-ai'),
		],
		'range_end' => [
			'type' => 'string',
			'description' => __('End date/time for availability check. Format: Y-m-d H:i:s or Y-m-d (site local time). Default: now + 7 days', 'axiachat-ai'),
		],
	];

	$required = [];

	// Add service/staff parameters for external destinations when mode is 'ask'
	if ($destination === 'bookly') {
		$service_mode = $settings['bookly_service_mode'] ?? 'default';
		$staff_mode = $settings['bookly_staff_mode'] ?? 'any';

		// Add service parameter if mode is 'ask'
		if ($service_mode === 'ask') {
			$services = AIChat_Appointments_Manager::get_services();
			if (! empty($services)) {
				$service_enum = array_column($services, 'name');
				$properties['service'] = [
					'type' => 'string',
					'description' => __('Name of the service to book. Ask the customer which service they want.', 'axiachat-ai'),
					'enum' => $service_enum,
				];
			}
		}

		// Add staff parameter if mode is 'ask'
		if ($staff_mode === 'ask') {
			$staff_members = AIChat_Appointments_Manager::get_staff();
			if (! empty($staff_members)) {
				$staff_enum = array_column($staff_members, 'name');
				$properties['staff'] = [
					'type' => 'string',
					'description' => __('Name of the preferred staff member. Ask the customer if they have a preference.', 'axiachat-ai'),
					'enum' => $staff_enum,
				];
			}
		}
	}
	// Amelia support
	elseif ($destination === 'amelia') {
		$service_mode = $settings['amelia_service_mode'] ?? 'default';
		$staff_mode = $settings['amelia_provider_mode'] ?? 'any';

		// Add service parameter if mode is 'ask'
		if ($service_mode === 'ask') {
			$services = AIChat_Appointments_Manager::get_services();
			if (! empty($services)) {
				$service_enum = array_column($services, 'name');
				$properties['service'] = [
					'type' => 'string',
					'description' => __('Name of the service to book. Ask the customer which service they want.', 'axiachat-ai'),
					'enum' => $service_enum,
				];
			}
		}

		// Add staff parameter if mode is 'ask'
		if ($staff_mode === 'ask') {
			$staff_members = AIChat_Appointments_Manager::get_staff();
			if (! empty($staff_members)) {
				$staff_enum = array_column($staff_members, 'name');
				$properties['staff'] = [
					'type' => 'string',
					'description' => __('Name of the preferred employee. Ask the customer if they have a preference.', 'axiachat-ai'),
					'enum' => $staff_enum,
				];
			}
		}
	}
	// SSA (Simply Schedule Appointments) support
	elseif ($destination === 'ssa') {
		$service_mode = $settings['ssa_service_mode'] ?? 'default';

		// Add service parameter if mode is 'ask'
		if ($service_mode === 'ask') {
			$services = AIChat_Appointments_Manager::get_services();
			if (! empty($services)) {
				$service_enum = array_column($services, 'name');
				$properties['service'] = [
					'type' => 'string',
					'description' => __('Name of the appointment type to book. Ask the customer which type they want.', 'axiachat-ai'),
					'enum' => $service_enum,
				];
			}
		}
		// SSA doesn't have staff/provider selection
	}

	return [
		'type' => 'object',
		'properties' => $properties,
		'required' => $required,
	];
}

/**
 * Build dynamic schema for book_appointment based on settings
 *
 * @param array $settings Current appointment settings
 * @return array Schema definition
 */
function aichat_appointments_build_book_schema($settings)
{
	$destination = $settings['destination'] ?? 'internal';

	// Base properties always needed
	$properties = [
		'customer_name' => [
			'type' => 'string',
			'description' => __('Full name of the customer', 'axiachat-ai'),
		],
		'customer_email' => [
			'type' => 'string',
			'description' => __('Email address of the customer', 'axiachat-ai'),
		],
		'customer_phone' => [
			'type' => 'string',
			'description' => __('Phone number of the customer (optional)', 'axiachat-ai'),
		],
		'appointment_date' => [
			'type' => 'string',
			'description' => __('The appointment date (YYYY-MM-DD format)', 'axiachat-ai'),
		],
		'start_time' => [
			'type' => 'string',
			'description' => __('Start time in 24h format (HH:MM)', 'axiachat-ai'),
		],
		'notes' => [
			'type' => 'string',
			'description' => __('Additional notes or reason for appointment (optional)', 'axiachat-ai'),
		],
	];

	$required = ['customer_name', 'customer_email', 'appointment_date', 'start_time'];

	// Add service/staff parameters for external destinations when mode is 'ask'
	if ($destination === 'bookly') {
		$service_mode = $settings['bookly_service_mode'] ?? 'default';
		$staff_mode = $settings['bookly_staff_mode'] ?? 'any';

		// Add service parameter if mode is 'ask'
		if ($service_mode === 'ask') {
			$services = AIChat_Appointments_Manager::get_services();
			if (! empty($services)) {
				$service_enum = array_column($services, 'name');
				$properties['service'] = [
					'type' => 'string',
					'description' => __('Name of the service to book. Must be one of the available services.', 'axiachat-ai'),
					'enum' => $service_enum,
				];
				$required[] = 'service';
			}
		}

		// Add staff parameter if mode is 'ask'
		if ($staff_mode === 'ask') {
			$staff_members = AIChat_Appointments_Manager::get_staff();
			if (! empty($staff_members)) {
				$staff_enum = array_column($staff_members, 'name');
				$properties['staff'] = [
					'type' => 'string',
					'description' => __('Name of the preferred staff member. Use if customer has a preference.', 'axiachat-ai'),
					'enum' => $staff_enum,
				];
				// Staff is optional even in 'ask' mode - customer may not have preference
			}
		}
	}
	// Amelia support
	elseif ($destination === 'amelia') {
		$service_mode = $settings['amelia_service_mode'] ?? 'default';
		$staff_mode = $settings['amelia_provider_mode'] ?? 'any';

		// Add service parameter if mode is 'ask'
		if ($service_mode === 'ask') {
			$services = AIChat_Appointments_Manager::get_services();
			if (! empty($services)) {
				$service_enum = array_column($services, 'name');
				$properties['service'] = [
					'type' => 'string',
					'description' => __('Name of the service to book. Must be one of the available services.', 'axiachat-ai'),
					'enum' => $service_enum,
				];
				$required[] = 'service';
			}
		}

		// Add staff parameter if mode is 'ask'
		if ($staff_mode === 'ask') {
			$staff_members = AIChat_Appointments_Manager::get_staff();
			if (! empty($staff_members)) {
				$staff_enum = array_column($staff_members, 'name');
				$properties['staff'] = [
					'type' => 'string',
					'description' => __('Name of the preferred employee. Use if customer has a preference.', 'axiachat-ai'),
					'enum' => $staff_enum,
				];
				// Staff is optional even in 'ask' mode - customer may not have preference
			}
		}
	}
	// SSA (Simply Schedule Appointments) support
	elseif ($destination === 'ssa') {
		$service_mode = $settings['ssa_service_mode'] ?? 'default';

		// Add service parameter if mode is 'ask'
		if ($service_mode === 'ask') {
			$services = AIChat_Appointments_Manager::get_services();
			if (! empty($services)) {
				$service_enum = array_column($services, 'name');
				$properties['service'] = [
					'type' => 'string',
					'description' => __('Name of the appointment type to book. Must be one of the available types.', 'axiachat-ai'),
					'enum' => $service_enum,
				];
				$required[] = 'service';
			}
		}
		// SSA doesn't have staff/provider selection
	}

	return [
		'type' => 'object',
		'properties' => $properties,
		'required' => $required,
	];
}

/**
 * Get dynamic description for get_available_slots based on settings
 *
 * @param array $settings Current appointment settings
 * @return string Tool description
 */
function aichat_appointments_get_slots_description($settings)
{
	$destination = $settings['destination'] ?? 'internal';
	$base = __('Check available appointment slots for a date range. Returns list of available times. If no parameters provided, returns availability for the next 7 days.', 'axiachat-ai');

	if ($destination === 'bookly') {
		$service_mode = $settings['bookly_service_mode'] ?? 'default';
		$staff_mode = $settings['bookly_staff_mode'] ?? 'any';

		$extras = [];
		if ($service_mode === 'ask') {
			$extras[] = __('Ask the customer which service they need before checking availability.', 'axiachat-ai');
		}
		if ($staff_mode === 'ask') {
			$extras[] = __('Optionally ask if they have a preferred staff member.', 'axiachat-ai');
		}

		if (! empty($extras)) {
			$base .= ' ' . implode(' ', $extras);
		}
	} elseif ($destination === 'amelia') {
		$service_mode = $settings['amelia_service_mode'] ?? 'default';
		$staff_mode = $settings['amelia_provider_mode'] ?? 'any';

		$extras = [];
		if ($service_mode === 'ask') {
			$extras[] = __('Ask the customer which service they need before checking availability.', 'axiachat-ai');
		}
		if ($staff_mode === 'ask') {
			$extras[] = __('Optionally ask if they have a preferred employee.', 'axiachat-ai');
		}

		if (! empty($extras)) {
			$base .= ' ' . implode(' ', $extras);
		}
	} elseif ($destination === 'ssa') {
		$service_mode = $settings['ssa_service_mode'] ?? 'default';

		if ($service_mode === 'ask') {
			$base .= ' ' . __('Ask the customer which appointment type they need before checking availability.', 'axiachat-ai');
		}
	}

	return $base;
}

/**
 * Get dynamic description for book_appointment based on settings
 *
 * @param array $settings Current appointment settings
 * @return string Tool description
 */
function aichat_appointments_get_book_description($settings)
{
	$destination = $settings['destination'] ?? 'internal';
	$base = __('Book a new appointment. Requires customer details and preferred date/time. IMPORTANT: Always confirm availability first with get_available_slots, and ask for user confirmation before booking.', 'axiachat-ai');

	if ($destination === 'bookly') {
		$service_mode = $settings['bookly_service_mode'] ?? 'default';
		$staff_mode = $settings['bookly_staff_mode'] ?? 'any';

		$extras = [];
		if ($service_mode === 'ask') {
			$extras[] = __('You must include the service name in the booking.', 'axiachat-ai');
		}
		if ($staff_mode === 'ask') {
			$extras[] = __('Include staff name if customer specified a preference.', 'axiachat-ai');
		}

		if (! empty($extras)) {
			$base .= ' ' . implode(' ', $extras);
		}
	} elseif ($destination === 'amelia') {
		$service_mode = $settings['amelia_service_mode'] ?? 'default';
		$staff_mode = $settings['amelia_provider_mode'] ?? 'any';

		$extras = [];
		if ($service_mode === 'ask') {
			$extras[] = __('You must include the service name in the booking.', 'axiachat-ai');
		}
		if ($staff_mode === 'ask') {
			$extras[] = __('Include employee name if customer specified a preference.', 'axiachat-ai');
		}

		if (! empty($extras)) {
			$base .= ' ' . implode(' ', $extras);
		}
	} elseif ($destination === 'ssa') {
		$service_mode = $settings['ssa_service_mode'] ?? 'default';

		if ($service_mode === 'ask') {
			$base .= ' ' . __('You must include the appointment type name in the booking.', 'axiachat-ai');
		}
	}

	return $base;
}

/**
 * Callback: Get Available Slots
 */
function aichat_appointments_tool_get_slots_callback($args)
{
	$timezone = wp_timezone();
	$now = new DateTime('now', $timezone);

	// Parse range_start (default: now)
	$range_start_input = isset($args['range_start']) ? sanitize_text_field($args['range_start']) : '';
	if (empty($range_start_input)) {
		$range_start = clone $now;
	} else {
		// Try full datetime format first, then date only
		$range_start = DateTime::createFromFormat('Y-m-d H:i:s', $range_start_input, $timezone);
		if (! $range_start) {
			$range_start = DateTime::createFromFormat('Y-m-d', $range_start_input, $timezone);
			if ($range_start) {
				$range_start->setTime(0, 0, 0);
			}
		}
		if (! $range_start) {
			return ['error' => __('Invalid range_start format. Use Y-m-d H:i:s or Y-m-d', 'axiachat-ai')];
		}
	}

	// Parse range_end (default: now + 7 days)
	$range_end_input = isset($args['range_end']) ? sanitize_text_field($args['range_end']) : '';
	if (empty($range_end_input)) {
		$range_end = clone $now;
		$range_end->modify('+7 days');
		$range_end->setTime(23, 59, 59);
	} else {
		$range_end = DateTime::createFromFormat('Y-m-d H:i:s', $range_end_input, $timezone);
		if (! $range_end) {
			$range_end = DateTime::createFromFormat('Y-m-d', $range_end_input, $timezone);
			if ($range_end) {
				$range_end->setTime(23, 59, 59);
			}
		}
		if (! $range_end) {
			return ['error' => __('Invalid range_end format. Use Y-m-d H:i:s or Y-m-d', 'axiachat-ai')];
		}
	}

	// Ensure range_start is not after range_end
	if ($range_start > $range_end) {
		return ['error' => __('range_start cannot be after range_end', 'axiachat-ai')];
	}

	// Build options for slots query (service/staff for external destinations)
	$options = [];
	if (isset($args['service']) && $args['service'] !== '') {
		$options['service'] = sanitize_text_field($args['service']);
	}
	if (isset($args['staff']) && $args['staff'] !== '') {
		$options['staff'] = sanitize_text_field($args['staff']);
	}

	// Get settings for logging
	$settings = AIChat_Appointments_Manager::get_settings();
	aichat_log_debug('[Appointments Tool] get_slots_callback START', [
		'range_start' => $range_start->format('Y-m-d'),
		'range_end' => $range_end->format('Y-m-d'),
		'destination' => $settings['destination'] ?? 'unknown',
		'options' => $options,
	]);

	// Collect slots for all days in range
	$all_slots = [];
	$current_date = clone $range_start;

	while ($current_date <= $range_end) {
		$date_str = $current_date->format('Y-m-d');
		$day_slots = AIChat_Appointments_Manager::get_available_slots($date_str, $options);

		aichat_log_debug('[Appointments Tool] get_slots for date', [
			'date' => $date_str,
			'is_error' => is_wp_error($day_slots),
			'slots_count' => is_array($day_slots) ? count($day_slots) : 'N/A',
			'result' => is_wp_error($day_slots) ? $day_slots->get_error_message() : (is_array($day_slots) ? array_slice($day_slots, 0, 3) : $day_slots),
		]);

		if (! is_wp_error($day_slots) && ! empty($day_slots)) {
			$all_slots[ $date_str ] = $day_slots;
		}

		$current_date->modify('+1 day');
	}

	aichat_log_debug('[Appointments Tool] get_slots_callback END', [
		'total_days_with_slots' => count($all_slots),
	]);

	if (empty($all_slots)) {
		return [
			'success' => true,
			'message' => __('No available slots in the specified date range', 'axiachat-ai'),
			'range_start' => $range_start->format('Y-m-d'),
			'range_end' => $range_end->format('Y-m-d'),
			'slots' => [],
		];
	}

	// Count total slots
	$total_count = 0;
	foreach ($all_slots as $day_slots) {
		$total_count += count($day_slots);
	}

	return [
		'success' => true,
		'range_start' => $range_start->format('Y-m-d'),
		'range_end' => $range_end->format('Y-m-d'),
		'slots' => $all_slots,
		'total_slots' => $total_count,
		'days_with_availability' => count($all_slots),
	];
}

/**
 * Callback: Book Appointment
 */
function aichat_appointments_tool_book_callback($args, $context = [])
{
	aichat_log_debug('[Appointments Tool] book_callback START', $args);

	// Get settings to check for dynamic required fields
	$settings = AIChat_Appointments_Manager::get_settings();
	$destination = $settings['destination'] ?? 'internal';

	aichat_log_debug('[Appointments Tool] book_callback settings', [
		'destination' => $destination,
		'settings_keys' => array_keys($settings),
	]);

	// Base required fields
	$required = ['customer_name', 'customer_email', 'appointment_date', 'start_time'];

	// Add service to required if mode is 'ask' for Bookly
	if ($destination === 'bookly' && ($settings['bookly_service_mode'] ?? 'default') === 'ask') {
		$required[] = 'service';
	}

	$missing = [];
	foreach ($required as $field) {
		if (! isset($args[ $field ]) || $args[ $field ] === '' || $args[ $field ] === null) {
			$missing[] = $field;
		}
	}

	if (! empty($missing)) {
		/* translators: %s: comma-separated list of missing field names */
		return ['error' => sprintf(__('Missing required field: %s', 'axiachat-ai'), implode(', ', $missing))];
	}

	// Validate email
	if (! is_email($args['customer_email'])) {
		return ['error' => __('Invalid email address', 'axiachat-ai')];
	}

	// Prepare booking data
	$data = [
		'customer_name' => sanitize_text_field($args['customer_name']),
		'customer_email' => sanitize_email($args['customer_email']),
		'customer_phone' => isset($args['customer_phone']) ? sanitize_text_field($args['customer_phone']) : '',
		'appointment_date' => sanitize_text_field($args['appointment_date']),
		'start_time' => sanitize_text_field($args['start_time']),
		'notes' => isset($args['notes']) ? sanitize_textarea_field($args['notes']) : '',
	];

	// Add service/staff if provided (for external destinations)
	if (isset($args['service']) && $args['service'] !== '') {
		$data['service'] = sanitize_text_field($args['service']);
	}
	if (isset($args['staff']) && $args['staff'] !== '') {
		$data['staff'] = sanitize_text_field($args['staff']);
	}

	// Check usage limits for free plan
	$limit_check = AIChat_Appointments_Manager::check_usage_limit();
	if (is_wp_error($limit_check)) {
		aichat_log_debug('[Appointments Tool] book_callback limit check FAILED', $limit_check->get_error_message());

		return ['error' => $limit_check->get_error_message()];
	}

	aichat_log_debug('[Appointments Tool] book_callback calling Manager::book', $data);

	// Attempt to book
	$result = AIChat_Appointments_Manager::book($data);

	aichat_log_debug('[Appointments Tool] book_callback Manager::book result', [
		'is_wp_error' => is_wp_error($result),
		'result' => is_wp_error($result) ? $result->get_error_message() : $result,
	]);

	if (is_wp_error($result)) {
		return ['error' => $result->get_error_message()];
	}

	// Build response with optional service/staff info
	$response = [
		'success' => true,
		'message' => __('Appointment booked successfully', 'axiachat-ai'),
		'booking_code' => $result['booking_code'],
		'appointment_date' => $data['appointment_date'],
		'start_time' => $data['start_time'],
		'end_time' => $result['end_time'] ?? '',
		'customer_name' => $data['customer_name'],
	];

	// Add service/staff to response if present
	if (! empty($result['service_name'])) {
		$response['service'] = $result['service_name'];
	}
	if (! empty($result['staff_name'])) {
		$response['staff'] = $result['staff_name'];
	}

	return $response;
}

/**
 * Callback: Cancel Appointment
 */
function aichat_appointments_tool_cancel_callback($args)
{
	$code = isset($args['booking_code']) ? sanitize_text_field($args['booking_code']) : '';

	if (empty($code)) {
		return ['error' => __('Booking code is required', 'axiachat-ai')];
	}

	$result = AIChat_Appointments_Manager::cancel($code);

	if (is_wp_error($result)) {
		return ['error' => $result->get_error_message()];
	}

	return [
		'success' => true,
		'message' => __('Appointment cancelled successfully', 'axiachat-ai'),
	];
}

/**
 * Callback: Get Available Services
 */
function aichat_appointments_tool_get_services_callback($args = [])
{
	$services = AIChat_Appointments_Manager::get_services();

	if (empty($services)) {
		return [
			'success' => true,
			'message' => __('No services available', 'axiachat-ai'),
			'services' => [],
		];
	}

	return [
		'success' => true,
		'services' => $services,
		'count' => count($services),
	];
}

/**
 * Callback: Get Available Staff
 */
function aichat_appointments_tool_get_staff_callback($args = [])
{
	// Optionally filter by service
	$service_id = 0;
	if (! empty($args['service'])) {
		// Resolve service name to ID
		$services = AIChat_Appointments_Manager::get_services();
		foreach ($services as $service) {
			if (strcasecmp($service['name'], $args['service']) === 0) {
				$service_id = $service['id'];
				break;
			}
		}
	}

	$staff = AIChat_Appointments_Manager::get_staff($service_id);

	if (empty($staff)) {
		return [
			'success' => true,
			'message' => __('No staff members available', 'axiachat-ai'),
			'staff' => [],
		];
	}

	return [
		'success' => true,
		'staff' => $staff,
		'count' => count($staff),
	];
}
