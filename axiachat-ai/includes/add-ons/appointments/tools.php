<?php
/**
 * Appointments Tools Registration
 * 
 * Registers AI tools for checking availability and booking appointments.
 * 
 * @package AIChat
 * @subpackage Appointments
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Legacy tool registration via filter - DISABLED
 * Tools are now registered via integration.php using aichat_register_tool_safe()
 * This file is kept for the executor functions only.
 */
// add_filter( 'aichat_register_tools', 'aichat_appointments_register_tools' );

function aichat_appointments_register_tools_legacy( $tools ) {
    $settings = AIChat_Appointments_Manager::get_settings();
    
    // Build services enum if available
    $services_enum = ! empty( $settings['services'] ) 
        ? array_column( $settings['services'], 'name' ) 
        : [];
    
    // Tool 1: Get Available Slots
    $tools['get_available_slots'] = [
        'enabled'     => true,
        'name'        => 'get_available_slots',
        'description' => __( 'Check available appointment times for a specific date. Use this before booking to show the user available options.', 'axiachat-ai' ),
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'date' => [
                    'type'        => 'string',
                    'description' => __( 'The date to check availability for, in YYYY-MM-DD format (e.g., 2026-01-25)', 'axiachat-ai' ),
                ],
                'service' => [
                    'type'        => 'string',
                    'description' => __( 'Optional: The specific service to check availability for', 'axiachat-ai' ),
                ],
            ],
            'required' => [ 'date' ],
        ],
        'executor' => 'aichat_tool_get_available_slots',
    ];
    
    // Tool 2: Book Appointment
    $tools['book_appointment'] = [
        'enabled'     => true,
        'name'        => 'book_appointment',
        'description' => __( 'Book an appointment for the user. Requires name, email, date and time. Always check availability first with get_available_slots.', 'axiachat-ai' ),
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'customer_name' => [
                    'type'        => 'string',
                    'description' => __( 'Full name of the customer', 'axiachat-ai' ),
                ],
                'customer_email' => [
                    'type'        => 'string',
                    'description' => __( 'Email address of the customer', 'axiachat-ai' ),
                ],
                'customer_phone' => [
                    'type'        => 'string',
                    'description' => __( 'Phone number of the customer (optional)', 'axiachat-ai' ),
                ],
                'appointment_date' => [
                    'type'        => 'string',
                    'description' => __( 'Date for the appointment in YYYY-MM-DD format', 'axiachat-ai' ),
                ],
                'start_time' => [
                    'type'        => 'string',
                    'description' => __( 'Start time for the appointment in HH:MM format (24-hour, e.g., 14:30)', 'axiachat-ai' ),
                ],
                'service' => [
                    'type'        => 'string',
                    'description' => __( 'The service being booked (optional)', 'axiachat-ai' ),
                ],
                'notes' => [
                    'type'        => 'string',
                    'description' => __( 'Additional notes or requests from the customer (optional)', 'axiachat-ai' ),
                ],
            ],
            'required' => [ 'customer_name', 'customer_email', 'appointment_date', 'start_time' ],
        ],
        'executor' => 'aichat_tool_book_appointment',
    ];
    
    // Tool 3: Cancel Appointment
    $tools['cancel_appointment'] = [
        'enabled'     => true,
        'name'        => 'cancel_appointment',
        'description' => __( 'Cancel an existing appointment using the booking confirmation code.', 'axiachat-ai' ),
        'parameters'  => [
            'type'       => 'object',
            'properties' => [
                'booking_code' => [
                    'type'        => 'string',
                    'description' => __( 'The booking confirmation code (e.g., APT-ABC12345)', 'axiachat-ai' ),
                ],
                'customer_email' => [
                    'type'        => 'string',
                    'description' => __( 'Email address used for the booking (for verification)', 'axiachat-ai' ),
                ],
            ],
            'required' => [ 'booking_code' ],
        ],
        'executor' => 'aichat_tool_cancel_appointment',
    ];
    
    return $tools;
}

/**
 * Tool Executor: Get Available Slots
 */
function aichat_tool_get_available_slots( $params, $context = [] ) {
    $date = $params['date'] ?? '';
    $service = $params['service'] ?? '';
    
    // Validate date format
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return [
            'success' => false,
            'error'   => __( 'Invalid date format. Please use YYYY-MM-DD format.', 'axiachat-ai' ),
        ];
    }
    
    // Check if date is in the past
    $today = current_time( 'Y-m-d' );
    if ( $date < $today ) {
        return [
            'success' => false,
            'error'   => __( 'Cannot check availability for past dates.', 'axiachat-ai' ),
        ];
    }
    
    $slots = AIChat_Appointments_Manager::get_available_slots( $date, $service );
    
    if ( empty( $slots ) ) {
        // Get settings to provide helpful info
        $settings = AIChat_Appointments_Manager::get_settings();
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
        $day_name = $date_obj ? $date_obj->format( 'l' ) : '';
        
        return [
            'success'   => true,
            'available' => false,
            'slots'     => [],
            'message'   => sprintf(
                /* translators: 1: Formatted date, 2: Day name (e.g., Monday) */
                __( 'No available times on %1$s (%2$s). This day might be closed or fully booked.', 'axiachat-ai' ),
                date_i18n( get_option( 'date_format' ), strtotime( $date ) ),
                $day_name
            ),
        ];
    }
    
    // Format slots for nice display
    $slot_times = array_map( function( $s ) {
        return $s['display'];
    }, $slots );
    
    return [
        'success'     => true,
        'available'   => true,
        'date'        => $date,
        'date_display'=> date_i18n( get_option( 'date_format' ), strtotime( $date ) ),
        'slots'       => $slots,
        'slots_count' => count( $slots ),
        'message'     => sprintf(
            /* translators: 1: Formatted date, 2: Comma-separated list of available times */
            __( 'Available times on %1$s: %2$s', 'axiachat-ai' ),
            date_i18n( get_option( 'date_format' ), strtotime( $date ) ),
            implode( ', ', $slot_times )
        ),
    ];
}

/**
 * Tool Executor: Book Appointment
 */
function aichat_tool_book_appointment( $params, $context = [] ) {
    // Add context info
    $params['bot_slug'] = $context['bot_slug'] ?? '';
    $params['session_id'] = $context['session_id'] ?? '';
    
    $result = AIChat_Appointments_Manager::book( $params );
    
    if ( is_wp_error( $result ) ) {
        return [
            'success' => false,
            'error'   => $result->get_error_message(),
        ];
    }
    
    return $result;
}

/**
 * Tool Executor: Cancel Appointment
 */
function aichat_tool_cancel_appointment( $params, $context = [] ) {
    $booking_code = $params['booking_code'] ?? '';
    $email = $params['customer_email'] ?? '';
    
    if ( empty( $booking_code ) ) {
        return [
            'success' => false,
            'error'   => __( 'Booking code is required to cancel an appointment.', 'axiachat-ai' ),
        ];
    }
    
    $result = AIChat_Appointments_Manager::cancel( $booking_code, $email );
    
    if ( is_wp_error( $result ) ) {
        return [
            'success' => false,
            'error'   => $result->get_error_message(),
        ];
    }
    
    return $result;
}
