<?php
/**
 * Appointments Admin AJAX Handlers
 * 
 * @package AIChat
 * @subpackage Appointments
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Save appointments settings
 */
add_action( 'wp_ajax_aichat_appointments_save_settings', function() {
    check_ajax_referer( 'aichat_appointments_settings', 'appointments_nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'axiachat-ai' ) );
    }
    
    $settings = [
        'destination'       => sanitize_key( $_POST['destination'] ?? 'internal' ),
        'timezone'          => sanitize_text_field( wp_unslash( $_POST['timezone'] ?? wp_timezone_string() ) ),
        'slot_duration'     => absint( $_POST['slot_duration'] ?? 30 ),
        'min_advance'       => absint( $_POST['min_advance'] ?? 60 ),
        'max_advance_days'  => absint( $_POST['max_advance_days'] ?? 30 ),
        'auto_confirm'      => ! empty( $_POST['auto_confirm'] ),
        'send_confirmation' => ! empty( $_POST['send_confirmation'] ),
        'send_reminder'     => ! empty( $_POST['send_reminder'] ),
        'reminder_time'     => absint( $_POST['reminder_time'] ?? 24 ),
        'reminder_unit'     => sanitize_key( $_POST['reminder_unit'] ?? 'hours' ),
        
        // Email templates
        'confirmation_email_subject' => sanitize_text_field( wp_unslash( $_POST['confirmation_email_subject'] ?? '' ) ),
        'confirmation_email_body'    => sanitize_textarea_field( wp_unslash( $_POST['confirmation_email_body'] ?? '' ) ),
        'reminder_email_subject'     => sanitize_text_field( wp_unslash( $_POST['reminder_email_subject'] ?? '' ) ),
        'reminder_email_body'        => sanitize_textarea_field( wp_unslash( $_POST['reminder_email_body'] ?? '' ) ),
        
        // Bookly-specific settings
        'bookly_service_mode' => sanitize_key( $_POST['bookly_service_mode'] ?? 'default' ),
        'bookly_service_id'   => absint( $_POST['bookly_service_id'] ?? 0 ),
        'bookly_staff_mode'   => sanitize_key( $_POST['bookly_staff_mode'] ?? 'any' ),
        'bookly_staff_id'     => absint( $_POST['bookly_staff_id'] ?? 0 ),
        
        // Amelia-specific settings
        'amelia_service_mode'  => sanitize_key( $_POST['amelia_service_mode'] ?? 'default' ),
        'amelia_service_id'    => absint( $_POST['amelia_service_id'] ?? 0 ),
        'amelia_provider_mode' => sanitize_key( $_POST['amelia_provider_mode'] ?? 'any' ),
        'amelia_provider_id'   => absint( $_POST['amelia_provider_id'] ?? 0 ),
        
        // SSA-specific settings
        'ssa_service_mode' => sanitize_key( $_POST['ssa_service_mode'] ?? 'default' ),
        'ssa_service_id'   => absint( $_POST['ssa_service_id'] ?? 0 ),
        
        // Google Calendar settings - calendar_id is handled separately by OAuth class
    ];
    
    // Update Google Calendar calendar_id if provided
    if ( isset( $_POST['gcal_calendar_id'] ) && ! empty( $_POST['gcal_calendar_id'] ) ) {
        $gcal_adapter = AIChat_Appointments_Manager::get_adapter_by_id( 'google_calendar' );
        if ( $gcal_adapter && $gcal_adapter->is_connected() ) {
            $gcal_adapter->get_oauth()->set_calendar_id( sanitize_text_field( wp_unslash( $_POST['gcal_calendar_id'] ) ) );
        }
    }
    
    // Save webhook URL separately (independent of destination)
    update_option( 'aichat_appointments_webhook_url', esc_url_raw( wp_unslash( $_POST['webhook_url'] ?? '' ) ) );
    
    // Process working hours with multiple slots support
    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in the loop below
    $raw_working_hours = isset( $_POST['working_hours'] ) ? wp_unslash( $_POST['working_hours'] ) : [];
    $working_hours = [];
    if ( ! empty( $raw_working_hours ) && is_array( $raw_working_hours ) ) {
        foreach ( $raw_working_hours as $day => $hours ) {
            $day = absint( $day );
            $working_hours[ $day ] = [
                'enabled' => ! empty( $hours['enabled'] ),
            ];
            
            // Process multiple time slots
            if ( ! empty( $hours['slots'] ) && is_array( $hours['slots'] ) ) {
                $slots = [];
                foreach ( $hours['slots'] as $slot ) {
                    if ( ! empty( $slot['start'] ) && ! empty( $slot['end'] ) ) {
                        $slots[] = [
                            'start' => sanitize_text_field( $slot['start'] ),
                            'end'   => sanitize_text_field( $slot['end'] ),
                        ];
                    }
                }
                $working_hours[ $day ]['slots'] = $slots;
                
                // Also set legacy start/end from first slot for backwards compatibility
                if ( ! empty( $slots ) ) {
                    $working_hours[ $day ]['start'] = $slots[0]['start'];
                    $working_hours[ $day ]['end'] = $slots[ count( $slots ) - 1 ]['end'];
                }
            } else {
                // Fallback to legacy single start/end format
                $working_hours[ $day ]['start'] = sanitize_text_field( $hours['start'] ?? '09:00' );
                $working_hours[ $day ]['end'] = sanitize_text_field( $hours['end'] ?? '18:00' );
                $working_hours[ $day ]['slots'] = [
                    [
                        'start' => $working_hours[ $day ]['start'],
                        'end'   => $working_hours[ $day ]['end'],
                    ]
                ];
            }
        }
    }
    $settings['working_hours'] = $working_hours;
    
    AIChat_Appointments_Manager::save_settings( $settings );
    
    wp_send_json_success( __( 'Settings saved successfully', 'axiachat-ai' ) );
} );

/**
 * Disconnect Google Calendar
 */
add_action( 'wp_ajax_aichat_appointments_gcal_disconnect', function() {
    check_ajax_referer( 'aichat_appointments_settings', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'axiachat-ai' ) );
    }
    
    $gcal_adapter = AIChat_Appointments_Manager::get_adapter_by_id( 'google_calendar' );
    if ( ! $gcal_adapter ) {
        wp_send_json_error( __( 'Google Calendar adapter not found', 'axiachat-ai' ) );
    }
    
    $gcal_adapter->get_oauth()->disconnect();
    
    wp_send_json_success( __( 'Google Calendar disconnected', 'axiachat-ai' ) );
} );

/**
 * Get appointment details
 */
add_action( 'wp_ajax_aichat_appointments_get', function() {
    check_ajax_referer( 'aichat_appointments_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'axiachat-ai' ) );
    }
    
    $id = absint( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( __( 'Invalid ID', 'axiachat-ai' ) );
    }
    
    $appointment = AIChat_Appointments_Manager::get( $id );
    if ( ! $appointment ) {
        wp_send_json_error( __( 'Appointment not found', 'axiachat-ai' ) );
    }
    
    wp_send_json_success( $appointment );
} );

/**
 * Update appointment status
 */
add_action( 'wp_ajax_aichat_appointments_update_status', function() {
    check_ajax_referer( 'aichat_appointments_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'axiachat-ai' ) );
    }
    
    $id = absint( $_POST['id'] ?? 0 );
    $status = sanitize_key( $_POST['status'] ?? '' );
    
    if ( ! $id || ! $status ) {
        wp_send_json_error( __( 'Invalid parameters', 'axiachat-ai' ) );
    }
    
    $result = AIChat_Appointments_Manager::update_status( $id, $status );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }
    
    wp_send_json_success( __( 'Status updated', 'axiachat-ai' ) );
} );

/**
 * Cancel appointment from admin
 */
add_action( 'wp_ajax_aichat_appointments_cancel', function() {
    check_ajax_referer( 'aichat_appointments_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'axiachat-ai' ) );
    }
    
    $id = absint( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( __( 'Invalid ID', 'axiachat-ai' ) );
    }
    
    $appointment = AIChat_Appointments_Manager::get( $id );
    if ( ! $appointment ) {
        wp_send_json_error( __( 'Appointment not found', 'axiachat-ai' ) );
    }
    
    $result = AIChat_Appointments_Manager::cancel( $appointment->booking_code );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }
    
    wp_send_json_success( __( 'Appointment cancelled', 'axiachat-ai' ) );
} );

/**
 * Update appointment details
 */
add_action( 'wp_ajax_aichat_appointments_update', function() {
    check_ajax_referer( 'aichat_appointments_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'axiachat-ai' ) );
    }
    
    $id = absint( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( __( 'Invalid ID', 'axiachat-ai' ) );
    }
    
    $data = [
        'customer_name'    => sanitize_text_field( wp_unslash( $_POST['customer_name'] ?? '' ) ),
        'customer_email'   => sanitize_email( wp_unslash( $_POST['customer_email'] ?? '' ) ),
        'customer_phone'   => sanitize_text_field( wp_unslash( $_POST['customer_phone'] ?? '' ) ),
        'service'          => sanitize_text_field( wp_unslash( $_POST['service'] ?? '' ) ),
        'appointment_date' => sanitize_text_field( wp_unslash( $_POST['appointment_date'] ?? '' ) ),
        'start_time'       => sanitize_text_field( wp_unslash( $_POST['start_time'] ?? '' ) ),
        'end_time'         => sanitize_text_field( wp_unslash( $_POST['end_time'] ?? '' ) ),
        'notes'            => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
        'status'           => sanitize_key( $_POST['status'] ?? '' ),
    ];
    
    // Remove empty status to not override if not sent
    if ( empty( $data['status'] ) ) {
        unset( $data['status'] );
    }
    
    // Validate required fields
    if ( empty( $data['customer_name'] ) || empty( $data['customer_email'] ) || 
         empty( $data['appointment_date'] ) || empty( $data['start_time'] ) ) {
        wp_send_json_error( __( 'Required fields are missing', 'axiachat-ai' ) );
    }
    
    // Validate email
    if ( ! is_email( $data['customer_email'] ) ) {
        wp_send_json_error( __( 'Invalid email address', 'axiachat-ai' ) );
    }
    
    $result = AIChat_Appointments_Manager::update( $id, $data );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }
    
    wp_send_json_success( __( 'Appointment updated successfully', 'axiachat-ai' ) );
} );

/**
 * Get appointments for calendar (AJAX)
 */
add_action( 'wp_ajax_aichat_appointments_calendar', function() {
    check_ajax_referer( 'aichat_appointments_nonce', 'nonce' );
    
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( __( 'Unauthorized', 'axiachat-ai' ) );
    }
    
    $start = sanitize_text_field( wp_unslash( $_GET['start'] ?? '' ) );
    $end = sanitize_text_field( wp_unslash( $_GET['end'] ?? '' ) );
    
    $appointments = AIChat_Appointments_Manager::get_list( [
        'date_from' => $start,
        'date_to'   => $end,
        'limit'     => 500,
    ] );
    
    $events = [];
    foreach ( $appointments as $apt ) {
        $color = [
            'pending'   => '#ffc107',
            'confirmed' => '#28a745',
            'completed' => '#6c757d',
            'cancelled' => '#dc3545',
            'no_show'   => '#343a40',
        ][ $apt->status ] ?? '#007bff';
        
        $events[] = [
            'id'    => $apt->id,
            'title' => $apt->customer_name . ' (' . $apt->booking_code . ')',
            'start' => $apt->appointment_date . 'T' . $apt->start_time,
            'end'   => $apt->appointment_date . 'T' . $apt->end_time,
            'color' => $color,
            'extendedProps' => [
                'status'  => $apt->status,
                'email'   => $apt->customer_email,
                'phone'   => $apt->customer_phone,
                'service' => $apt->service,
            ],
        ];
    }
    
    wp_send_json( $events );
} );

/**
 * Enqueue admin scripts for appointments page
 */
add_action( 'admin_enqueue_scripts', function( $hook ) {
    if ( strpos( $hook, 'aichat-appointments' ) === false ) {
        return;
    }
    
    // Enqueue main admin styles (Bootstrap is already loaded by main plugin)
    wp_enqueue_style( 'aichat-admin' );
    wp_enqueue_style( 'aichat-bootstrap' );
    wp_enqueue_style( 'aichat-bootstrap-icons' );
    
    // Enqueue appointments-specific styles
    wp_enqueue_style(
        'aichat-appointments-admin',
        AICHAT_PLUGIN_URL . 'assets/css/appointments-admin.css',
        [ 'aichat-bootstrap' ],
        AICHAT_VERSION
    );
    
    // Enqueue appointments-specific scripts - add wp-i18n for translation support
    wp_enqueue_script(
        'aichat-appointments-admin',
        AICHAT_PLUGIN_URL . 'assets/js/appointments-admin.js',
        [ 'jquery', 'aichat-bootstrap', 'wp-i18n' ],
        AICHAT_VERSION,
        true
    );
    wp_set_script_translations( 'aichat-appointments-admin', 'axiachat-ai', AICHAT_PLUGIN_DIR . 'languages' );
    
    // Get settings for calendar
    $settings = AIChat_Appointments_Manager::get_settings();
    
    // Localize script data
    wp_localize_script( 'aichat-appointments-admin', 'aichat_appointments', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'aichat_appointments_nonce' ),
        'settings' => [
            'working_hours' => $settings['working_hours'] ?? [],
            'slot_duration' => $settings['slot_duration'] ?? 30,
        ],
        'i18n'     => [
            'confirm_cancel'   => __( 'Are you sure you want to cancel this appointment?', 'axiachat-ai' ),
            'confirm_confirm'  => __( 'Confirm this appointment?', 'axiachat-ai' ),
            'confirm_complete' => __( 'Mark this appointment as completed?', 'axiachat-ai' ),
            'confirm_noshow'   => __( 'Mark customer as no-show?', 'axiachat-ai' ),
            'loading'          => __( 'Loading...', 'axiachat-ai' ),
            'error'            => __( 'Error', 'axiachat-ai' ),
            'success'          => __( 'Success', 'axiachat-ai' ),
        ],
    ] );
} );
