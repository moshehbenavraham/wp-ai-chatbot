<?php
/**
 * Amelia Appointments Adapter
 * 
 * Integrates with the Amelia plugin for appointment booking.
 * Supports services, employees, and Amelia's slot calculation.
 * 
 * Amelia Database Tables:
 * - wp_amelia_services: id, name, duration (seconds), price, status
 * - wp_amelia_users: id, firstName, lastName, email, type ('provider'/'customer'), status
 * - wp_amelia_appointments: id, bookingStart, bookingEnd, serviceId, providerId, status
 * - wp_amelia_customer_bookings: id, appointmentId, customerId, status, price
 * - wp_amelia_providers_to_services: userId, serviceId
 * - wp_amelia_providers_to_weekdays: userId, dayIndex, startTime, endTime
 * 
 * @package AxiaChat_AI
 * @subpackage Appointments
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Appointments_Adapter_Amelia implements AIChat_Appointments_Adapter_Interface {
    
    /** @var array Cached settings */
    private $settings;
    
    /** @var string Internal table for tracking */
    private $tracking_table;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->tracking_table = $wpdb->prefix . 'aichat_appointments';
    }
    
    /**
     * Check if Amelia is available
     */
    public function is_available() {
        return defined( 'AMELIA_VERSION' ) || 
               class_exists( 'AmeliaBooking\Plugin' ) ||
               is_plugin_active( 'ameliabooking/ameliabooking.php' );
    }
    
    /**
     * Get adapter info
     */
    public function get_info() {
        return [
            'id'          => 'amelia',
            'name'        => 'Amelia',
            'description' => __( 'Integrate with Amelia Booking plugin. Supports services, employees, locations and packages.', 'axiachat-ai' ),
            'icon'        => 'bi-calendar-heart',
        ];
    }
    
    /**
     * Get services from Amelia
     */
    public function get_services() {
        if ( ! $this->is_available() ) {
            return [];
        }
        
        global $wpdb;
        
        $table = $wpdb->prefix . 'amelia_services';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return [];
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
        $services = $wpdb->get_results(
            "SELECT id, name, duration, price, status 
             FROM {$table} 
             WHERE status = 'visible'
             ORDER BY position, name"
        );
        
        if ( ! $services ) {
            return [];
        }
        
        $result = [];
        foreach ( $services as $service ) {
            $result[] = [
                'id'       => (int) $service->id,
                'name'     => $service->name,
                'duration' => (int) $service->duration, // Amelia stores in seconds
                'price'    => (float) $service->price,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get employees (providers) from Amelia
     */
    public function get_staff( $service_id = 0 ) {
        if ( ! $this->is_available() ) {
            return [];
        }
        
        global $wpdb;
        
        $users_table = $wpdb->prefix . 'amelia_users';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $users_table is a trusted plugin table name.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$users_table}'" ) !== $users_table ) {
            return [];
        }
        
        if ( $service_id > 0 ) {
            $providers_services_table = $wpdb->prefix . 'amelia_providers_to_services';
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $users_table and $providers_services_table are trusted plugin table names.
            $staff = $wpdb->get_results( $wpdb->prepare(
                "SELECT u.id, u.firstName, u.lastName, u.email
                 FROM {$users_table} u
                 INNER JOIN {$providers_services_table} ps ON u.id = ps.userId
                 WHERE ps.serviceId = %d 
                 AND u.type = 'provider' 
                 AND u.status = 'visible'
                 ORDER BY u.firstName, u.lastName",
                $service_id
            ) );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $users_table is a trusted plugin table name.
            $staff = $wpdb->get_results(
                "SELECT id, firstName, lastName, email
                 FROM {$users_table} 
                 WHERE type = 'provider' AND status = 'visible'
                 ORDER BY firstName, lastName"
            );
        }
        
        if ( ! $staff ) {
            return [];
        }
        
        $result = [];
        foreach ( $staff as $member ) {
            $result[] = [
                'id'    => (int) $member->id,
                'name'  => trim( $member->firstName . ' ' . $member->lastName ),
                'email' => $member->email,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get available slots from Amelia
     */
    public function get_available_slots( $date, $params = [] ) {
        if ( ! $this->is_available() ) {
            aichat_log_debug( '[Amelia Adapter] get_available_slots: Amelia not available' );
            return [];
        }
        
        $settings = $this->get_settings();
        
        $service_id  = $this->resolve_service_id( $params['service'] ?? null, $settings );
        $provider_id = $this->resolve_staff_id( $params['staff'] ?? null, $settings );
        
        aichat_log_debug( '[Amelia Adapter] get_available_slots', [
            'date'        => $date,
            'service_id'  => $service_id,
            'provider_id' => $provider_id,
        ] );
        
        if ( ! $service_id ) {
            $services = $this->get_services();
            if ( empty( $services ) ) {
                aichat_log_debug( '[Amelia Adapter] get_available_slots: No services found in Amelia' );
                return [ 'error' => __( 'No services configured in Amelia', 'axiachat-ai' ) ];
            } elseif ( count( $services ) > 1 ) {
                aichat_log_debug( '[Amelia Adapter] get_available_slots: Multiple services, none selected' );
                return [ 
                    'error' => __( 'Please specify which service you want to book', 'axiachat-ai' ), 
                    'available_services' => array_column( $services, 'name' ),
                ];
            }
        }
        
        return $this->get_slots_via_database( $date, $service_id, $provider_id );
    }
    
    /**
     * Get slots by querying Amelia database directly
     */
    private function get_slots_via_database( $date, $service_id, $provider_id ) {
        global $wpdb;
        
        aichat_log_debug( '[Amelia Adapter] get_slots_via_database: Starting', [
            'date'        => $date,
            'service_id'  => $service_id,
            'provider_id' => $provider_id,
        ] );
        
        // Get service duration
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $service = $wpdb->get_row( $wpdb->prepare(
            "SELECT name, duration, timeBefore, timeAfter FROM {$wpdb->prefix}amelia_services WHERE id = %d",
            $service_id
        ) );
        
        if ( ! $service ) {
            aichat_log_debug( '[Amelia Adapter] get_slots_via_database: Service not found' );
            return [];
        }
        
        // Amelia stores duration in seconds
        $duration_seconds = (int) $service->duration;
        $duration = (int) ceil( $duration_seconds / 60 ); // Convert to minutes
        $buffer_before = (int) ( $service->timeBefore ?? 0 ) / 60;
        $buffer_after  = (int) ( $service->timeAfter ?? 0 ) / 60;
        $total_duration = $duration + $buffer_before + $buffer_after;
        
        $day_of_week = (int) gmdate( 'N', strtotime( $date ) ); // 1=Monday, 7=Sunday
        
        aichat_log_debug( '[Amelia Adapter] get_slots_via_database: Service details', [
            'name'             => $service->name,
            'duration_seconds' => $duration_seconds,
            'duration_minutes' => $duration,
            'buffer_before'    => $buffer_before,
            'buffer_after'     => $buffer_after,
            'day_of_week'      => $day_of_week,
        ] );
        
        // Get providers for this service
        $provider_ids = [];
        if ( $provider_id > 0 ) {
            $provider_ids = [ $provider_id ];
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $providers_for_service = $wpdb->get_col( $wpdb->prepare(
                "SELECT userId FROM {$wpdb->prefix}amelia_providers_to_services WHERE serviceId = %d",
                $service_id
            ) );
            $provider_ids = $providers_for_service ?: [];
        }
        
        aichat_log_debug( '[Amelia Adapter] get_slots_via_database: provider_ids=' . implode( ',', $provider_ids ) );
        
        if ( empty( $provider_ids ) ) {
            aichat_log_debug( '[Amelia Adapter] get_slots_via_database: No providers assigned to this service' );
            return [];
        }
        
        // Get provider working hours for this day
        // Amelia uses dayIndex: 1=Monday to 7=Sunday
        $placeholders = implode( ',', array_fill( 0, count( $provider_ids ), '%d' ) );
        $query_args = array_merge( $provider_ids, [ $day_of_week ] );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders contains safe %d placeholders.
        $schedules = $wpdb->get_results( $wpdb->prepare(
            "SELECT userId as provider_id, startTime, endTime 
             FROM {$wpdb->prefix}amelia_providers_to_weekdays 
             WHERE userId IN ({$placeholders}) AND dayIndex = %d",
            $query_args
        ) );
        
        aichat_log_debug( '[Amelia Adapter] get_slots_via_database: schedules found=' . count( $schedules ) );
        
        if ( empty( $schedules ) ) {
            // Try alternative table structure (amelia_providers_to_periods)
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders contains safe %d placeholders.
            $schedules = $wpdb->get_results( $wpdb->prepare(
                "SELECT w.userId as provider_id, p.startTime, p.endTime 
                 FROM {$wpdb->prefix}amelia_providers_to_weekdays w
                 INNER JOIN {$wpdb->prefix}amelia_providers_to_periods p ON w.id = p.weekDayId
                 WHERE w.userId IN ({$placeholders}) AND w.dayIndex = %d",
                $query_args
            ) );
            
            aichat_log_debug( '[Amelia Adapter] get_slots_via_database: schedules from periods=' . count( $schedules ) );
        }
        
        if ( empty( $schedules ) ) {
            aichat_log_debug( '[Amelia Adapter] get_slots_via_database: No schedules for day_index=' . $day_of_week );
            return [];
        }
        
        // Get already booked appointments for this date
        $booked_query_args = array_merge( [ $date ], $provider_ids );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders contains safe %d placeholders.
        $booked_slots = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.providerId as provider_id, a.bookingStart, a.bookingEnd
             FROM {$wpdb->prefix}amelia_appointments a
             WHERE DATE(a.bookingStart) = %s 
             AND a.providerId IN ({$placeholders})
             AND a.status NOT IN ('canceled', 'rejected', 'no-show')",
            $booked_query_args
        ) );
        
        aichat_log_debug( '[Amelia Adapter] get_slots_via_database: booked_slots=' . count( $booked_slots ) );
        
        // Calculate available slots
        $slots = [];
        $now = new DateTime( 'now', new DateTimeZone( wp_timezone_string() ) );
        // Reset microseconds to avoid comparison issues
        $now->setTime( (int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'), 0 );
        $min_advance = (int) ( $this->get_settings()['min_advance'] ?? 60 );
        $min_time = clone $now;
        $min_time->modify( '+' . $min_advance . ' minutes' );
        
        foreach ( $schedules as $schedule ) {
            if ( empty( $schedule->startTime ) || empty( $schedule->endTime ) ) {
                aichat_log_debug( '[Amelia Adapter] get_slots_via_database: Empty schedule times for provider=' . $schedule->provider_id );
                continue;
            }
            
            // Amelia stores times as HH:MM:SS
            $start_time_str = substr( $schedule->startTime, 0, 5 ); // HH:MM
            $end_time_str   = substr( $schedule->endTime, 0, 5 );
            
            aichat_log_debug( '[Amelia Adapter] get_slots_via_database: Processing schedule', [
                'provider_id' => $schedule->provider_id,
                'start'       => $start_time_str,
                'end'         => $end_time_str,
            ] );
            
            $start = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . $start_time_str );
            $end = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . $end_time_str );
            
            if ( ! $start || ! $end ) {
                aichat_log_debug( '[Amelia Adapter] get_slots_via_database: Failed to parse schedule times' );
                continue;
            }
            
            // Reset microseconds to avoid comparison issues (PHP 7.1+ sets current microseconds on createFromFormat)
            $start->setTime( (int) $start->format('H'), (int) $start->format('i'), 0, 0 );
            $end->setTime( (int) $end->format('H'), (int) $end->format('i'), 0, 0 );
            
            while ( $start < $end ) {
                $slot_end = clone $start;
                $slot_end->modify( '+' . $duration . ' minutes' );
                
                // Skip if before minimum advance time
                if ( $start < $min_time ) {
                    $start->modify( '+' . $duration . ' minutes' );
                    continue;
                }
                
                // Skip if slot would extend past working hours
                if ( $slot_end > $end ) {
                    break;
                }
                
                // Check if slot overlaps with any booked appointment
                $is_booked = false;
                foreach ( $booked_slots as $booked ) {
                    if ( (int) $booked->provider_id !== (int) $schedule->provider_id ) {
                        continue;
                    }
                    
                    $booked_start = new DateTime( $booked->bookingStart );
                    $booked_end   = new DateTime( $booked->bookingEnd );
                    
                    // Add buffer times
                    $slot_start_with_buffer = clone $start;
                    $slot_start_with_buffer->modify( '-' . $buffer_before . ' minutes' );
                    $slot_end_with_buffer = clone $slot_end;
                    $slot_end_with_buffer->modify( '+' . $buffer_after . ' minutes' );
                    
                    if ( $slot_start_with_buffer < $booked_end && $slot_end_with_buffer > $booked_start ) {
                        $is_booked = true;
                        break;
                    }
                }
                
                if ( ! $is_booked ) {
                    $slot_key = $start->format( 'H:i' );
                    if ( ! isset( $slots[ $slot_key ] ) ) {
                        $slots[ $slot_key ] = [
                            'time'        => $slot_key,
                            'end_time'    => $slot_end->format( 'H:i' ),
                            'provider_id' => (int) $schedule->provider_id,
                        ];
                    }
                }
                
                $start->modify( '+' . $duration . ' minutes' );
            }
        }
        
        aichat_log_debug( '[Amelia Adapter] get_slots_via_database: Total slots found=' . count( $slots ) );
        
        ksort( $slots );
        return array_values( $slots );
    }
    
    /**
     * Resolve service ID from name/id or use default
     */
    private function resolve_service_id( $service_input, $settings ) {
        if ( ! empty( $service_input ) ) {
            if ( is_numeric( $service_input ) ) {
                return (int) $service_input;
            }
            
            // Search by name
            $services = $this->get_services();
            foreach ( $services as $service ) {
                if ( strcasecmp( $service['name'], $service_input ) === 0 ) {
                    return $service['id'];
                }
                // Partial match
                if ( stripos( $service['name'], $service_input ) !== false ) {
                    return $service['id'];
                }
            }
        }
        
        // Use default from settings
        if ( ! empty( $settings['amelia_service_id'] ) ) {
            return (int) $settings['amelia_service_id'];
        }
        
        // If only one service, use it
        $services = $this->get_services();
        if ( count( $services ) === 1 ) {
            return $services[0]['id'];
        }
        
        return 0;
    }
    
    /**
     * Resolve provider ID from name/id or use default
     */
    private function resolve_staff_id( $staff_input, $settings ) {
        if ( ! empty( $staff_input ) ) {
            if ( is_numeric( $staff_input ) ) {
                return (int) $staff_input;
            }
            
            // Search by name
            $staff = $this->get_staff();
            foreach ( $staff as $member ) {
                if ( strcasecmp( $member['name'], $staff_input ) === 0 ) {
                    return $member['id'];
                }
                // Partial match
                if ( stripos( $member['name'], $staff_input ) !== false ) {
                    return $member['id'];
                }
            }
        }
        
        // Use default from settings
        if ( ! empty( $settings['amelia_provider_id'] ) ) {
            return (int) $settings['amelia_provider_id'];
        }
        
        // If only one provider, use it
        $staff = $this->get_staff();
        if ( count( $staff ) === 1 ) {
            return $staff[0]['id'];
        }
        
        return 0;
    }
    
    /**
     * Book an appointment in Amelia
     */
    public function book( $data ) {
        if ( ! $this->is_available() ) {
            return new WP_Error( 'amelia_unavailable', __( 'Amelia is not available', 'axiachat-ai' ) );
        }
        
        global $wpdb;
        $settings = $this->get_settings();
        
        // Validate required fields
        $required = [ 'customer_name', 'customer_email', 'appointment_date', 'start_time' ];
        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                /* translators: %s: Field name that is missing */
                return new WP_Error( 'missing_field', sprintf( __( 'Missing: %s', 'axiachat-ai' ), $field ) );
            }
        }
        
        if ( ! is_email( $data['customer_email'] ) ) {
            return new WP_Error( 'invalid_email', __( 'Invalid email', 'axiachat-ai' ) );
        }
        
        // Resolve service and provider
        $service_id  = $this->resolve_service_id( $data['service'] ?? null, $settings );
        $provider_id = $this->resolve_staff_id( $data['staff'] ?? null, $settings );
        
        if ( ! $service_id ) {
            return new WP_Error( 'no_service', __( 'No service specified', 'axiachat-ai' ) );
        }
        
        // Get service details
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $service = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, name, duration, price FROM {$wpdb->prefix}amelia_services WHERE id = %d",
            $service_id
        ) );
        
        if ( ! $service ) {
            return new WP_Error( 'invalid_service', __( 'Service not found', 'axiachat-ai' ) );
        }
        
        // If no provider specified, get first available for this service
        if ( $provider_id === 0 ) {
            $providers_for_service = $this->get_staff( $service_id );
            if ( ! empty( $providers_for_service ) ) {
                $provider_id = $providers_for_service[0]['id'];
            }
        }
        
        if ( ! $provider_id ) {
            return new WP_Error( 'no_provider', __( 'No provider available', 'axiachat-ai' ) );
        }
        
        // Calculate times
        $start_datetime = $data['appointment_date'] . ' ' . $data['start_time'] . ':00';
        // Amelia stores duration in seconds
        $duration_seconds = (int) $service->duration;
        $end_datetime = wp_date( 'Y-m-d H:i:s', strtotime( $start_datetime ) + $duration_seconds );
        
        aichat_log_debug( '[Amelia Book] Calculated times', [
            'start'            => $start_datetime,
            'end'              => $end_datetime,
            'duration_seconds' => $duration_seconds,
            'duration_minutes' => $duration_seconds / 60,
        ] );
        
        // Get or create customer
        $customer_id = $this->get_or_create_amelia_customer( $data );
        if ( is_wp_error( $customer_id ) ) {
            return $customer_id;
        }
        
        // Determine status
        $status = $settings['auto_confirm'] ? 'approved' : 'pending';
        
        // Create appointment in Amelia (Note: amelia_appointments doesn't have 'created' column)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $apt_result = $wpdb->insert(
            $wpdb->prefix . 'amelia_appointments',
            [
                'bookingStart'       => $start_datetime,
                'bookingEnd'         => $end_datetime,
                'notifyParticipants' => 1,
                'serviceId'          => $service_id,
                'providerId'         => $provider_id,
                'locationId'         => null,
                'internalNotes'      => sanitize_textarea_field( $data['notes'] ?? '' ),
                'status'             => $status,
            ]
        );
        
        if ( ! $apt_result ) {
            aichat_log_debug( '[Amelia Book] Failed to create appointment: ' . $wpdb->last_error );
            return new WP_Error( 'db_error', __( 'Failed to create appointment', 'axiachat-ai' ) );
        }
        
        $appointment_id = $wpdb->insert_id;
        
        // Create customer booking record
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $wpdb->prefix . 'amelia_customer_bookings',
            [
                'appointmentId' => $appointment_id,
                'customerId'    => $customer_id,
                'status'        => $status,
                'price'         => $service->price,
                'persons'       => 1,
                'created'       => current_time( 'mysql' ),
            ]
        );
        
        // Generate our tracking code
        $booking_code = 'APT-' . strtoupper( substr( md5( $appointment_id . uniqid() ), 0, 8 ) );
        
        // Get provider name for tracking
        $staff_name = '';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $provider_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT firstName, lastName FROM {$wpdb->prefix}amelia_users WHERE id = %d AND type = 'provider'",
            $provider_id
        ) );
        if ( $provider_row ) {
            $staff_name = trim( $provider_row->firstName . ' ' . $provider_row->lastName );
        }
        
        // Save to our tracking table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $this->tracking_table,
            [
                'booking_code'     => $booking_code,
                'customer_name'    => sanitize_text_field( $data['customer_name'] ),
                'customer_email'   => sanitize_email( $data['customer_email'] ),
                'customer_phone'   => sanitize_text_field( $data['customer_phone'] ?? '' ),
                'service'          => $service->name,
                'staff'            => $staff_name,
                'appointment_date' => $data['appointment_date'],
                'start_time'       => $data['start_time'],
                'end_time'         => wp_date( 'H:i', strtotime( $end_datetime ) ),
                'timezone'         => wp_timezone_string(),
                'status'           => $settings['auto_confirm'] ? 'confirmed' : 'pending',
                'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
                'source'           => 'amelia',
                'bot_slug'         => sanitize_text_field( $data['bot_slug'] ?? '' ),
                'session_id'       => sanitize_text_field( $data['session_id'] ?? '' ),
                'external_id'      => $appointment_id,
                'external_source'  => 'amelia',
            ]
        );
        
        do_action( 'aichat_appointment_booked', $wpdb->insert_id, $data );
        
        return [
            'success'      => true,
            'id'           => $wpdb->insert_id,
            'booking_code' => $booking_code,
            'external_id'  => $appointment_id,
            'status'       => $settings['auto_confirm'] ? 'confirmed' : 'pending',
            'date'         => $data['appointment_date'],
            'time'         => $data['start_time'],
            'end_time'     => wp_date( 'H:i', strtotime( $end_datetime ) ),
            'service'      => $service->name,
        ];
    }
    
    /**
     * Get or create customer in Amelia
     */
    private function get_or_create_amelia_customer( $data ) {
        global $wpdb;
        
        $email = sanitize_email( $data['customer_email'] );
        
        // Check if customer exists
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}amelia_users WHERE email = %s AND type = 'customer'",
            $email
        ) );
        
        if ( $customer ) {
            return (int) $customer->id;
        }
        
        // Parse name
        $full_name = sanitize_text_field( $data['customer_name'] );
        $name_parts = explode( ' ', $full_name, 2 );
        
        // Create new customer (Note: Amelia users table doesn't have 'created' column)
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $wpdb->prefix . 'amelia_users',
            [
                'type'            => 'customer',
                'status'          => 'visible',
                'firstName'       => $name_parts[0],
                'lastName'        => $name_parts[1] ?? '',
                'email'           => $email,
                'phone'           => sanitize_text_field( $data['customer_phone'] ?? '' ),
                'countryPhoneIso' => '',
                'externalId'      => null,
            ]
        );
        
        return $result ? $wpdb->insert_id : new WP_Error( 'customer_error', __( 'Failed to create customer', 'axiachat-ai' ) );
    }
    
    /**
     * Cancel an appointment
     */
    public function cancel( $booking_code ) {
        global $wpdb;
        
        $appointment = $this->get_by_code( $booking_code );
        
        if ( ! $appointment ) {
            return new WP_Error( 'not_found', __( 'Appointment not found', 'axiachat-ai' ) );
        }
        
        if ( $appointment->status === 'cancelled' ) {
            return new WP_Error( 'already_cancelled', __( 'Already cancelled', 'axiachat-ai' ) );
        }
        
        // Cancel in Amelia
        if ( ! empty( $appointment->external_id ) ) {
            // Update appointment status
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->prefix . 'amelia_appointments',
                [ 'status' => 'canceled' ],
                [ 'id' => $appointment->external_id ]
            );
            
            // Update customer booking status
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->update(
                $wpdb->prefix . 'amelia_customer_bookings',
                [ 'status' => 'canceled' ],
                [ 'appointmentId' => $appointment->external_id ]
            );
        }
        
        // Update our tracking table
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $wpdb->update( $this->tracking_table, [ 'status' => 'cancelled' ], [ 'id' => $appointment->id ] );
        
        do_action( 'aichat_appointment_cancelled', $appointment->id );
        
        return true;
    }
    
    /**
     * Get appointment by booking code
     */
    public function get_by_code( $booking_code ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->tracking_table is a trusted plugin table name.
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tracking_table} WHERE booking_code = %s",
            $booking_code
        ) );
    }
    
    /**
     * Get appointment by ID
     */
    public function get( $id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->tracking_table is a trusted plugin table name.
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tracking_table} WHERE id = %d",
            $id
        ) );
    }
    
    /**
     * Get appointments list
     */
    public function get_list( $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'status' => '', 'date_from' => '', 'date_to' => '', 'search' => '',
            'orderby' => 'appointment_date', 'order' => 'DESC', 'limit' => 50, 'offset' => 0,
        ];
        $args = wp_parse_args( $args, $defaults );
        
        $where = [ "external_source = 'amelia'" ];
        $values = [];
        
        if ( $args['status'] ) { $where[] = 'status = %s'; $values[] = $args['status']; }
        if ( $args['date_from'] ) { $where[] = 'appointment_date >= %s'; $values[] = $args['date_from']; }
        if ( $args['date_to'] ) { $where[] = 'appointment_date <= %s'; $values[] = $args['date_to']; }
        if ( $args['search'] ) {
            $where[] = '(customer_name LIKE %s OR customer_email LIKE %s OR booking_code LIKE %s)';
            $s = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values = array_merge( $values, [ $s, $s, $s ] );
        }
        
        $sql = "SELECT * FROM {$this->tracking_table} WHERE " . implode( ' AND ', $where );
        $sql .= " ORDER BY " . ( sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" ) ?: 'appointment_date DESC' );
        $sql .= " LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql uses plugin-controlled table name and is prepared here with placeholders.
        return $wpdb->get_results( $wpdb->prepare( $sql, $values ) );
    }
    
    /**
     * Update appointment status
     */
    public function update_status( $id, $status ) {
        global $wpdb;
        
        $appointment = $this->get( $id );
        if ( ! $appointment ) {
            return new WP_Error( 'not_found', __( 'Not found', 'axiachat-ai' ) );
        }
        
        // Map our status to Amelia status
        $amelia_map = [
            'pending'   => 'pending',
            'confirmed' => 'approved',
            'completed' => 'approved',
            'cancelled' => 'canceled',
            'no_show'   => 'no-show',
        ];
        
        if ( ! empty( $appointment->external_id ) && isset( $amelia_map[ $status ] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->prefix . 'amelia_appointments',
                [ 'status' => $amelia_map[ $status ] ],
                [ 'id' => $appointment->external_id ]
            );
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->prefix . 'amelia_customer_bookings',
                [ 'status' => $amelia_map[ $status ] ],
                [ 'appointmentId' => $appointment->external_id ]
            );
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->update( $this->tracking_table, [ 'status' => $status ], [ 'id' => $id ] ) !== false;
    }
    
    /**
     * Update appointment data
     */
    public function update( $id, $data ) {
        global $wpdb;
        
        $allowed = [ 'customer_name', 'customer_email', 'customer_phone', 'appointment_date', 'start_time', 'end_time', 'notes', 'status' ];
        $update = array_intersect_key( $data, array_flip( $allowed ) );
        
        if ( empty( $update ) ) {
            return new WP_Error( 'no_data', __( 'No data', 'axiachat-ai' ) );
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->update( $this->tracking_table, $update, [ 'id' => $id ] ) !== false;
    }
    
    /**
     * Get settings fields for admin UI
     */
    public function get_settings_fields() {
        return [
            'amelia_service_mode' => [
                'type'    => 'select',
                'label'   => __( 'Service Selection', 'axiachat-ai' ),
                'options' => [
                    'default' => __( 'Use default service (bot won\'t ask)', 'axiachat-ai' ),
                    'ask'     => __( 'Bot asks the customer which service', 'axiachat-ai' ),
                ],
                'default' => 'default',
                'help'    => __( 'If you have only one service or want the bot to use a fixed service, choose "default".', 'axiachat-ai' ),
            ],
            'amelia_service_id' => [
                'type'       => 'select_service',
                'label'      => __( 'Default Service', 'axiachat-ai' ),
                'depends_on' => [ 'amelia_service_mode' => 'default' ],
            ],
            'amelia_provider_mode' => [
                'type'    => 'select',
                'label'   => __( 'Employee/Provider Selection', 'axiachat-ai' ),
                'options' => [
                    'any'     => __( 'Any available employee (bot won\'t ask)', 'axiachat-ai' ),
                    'default' => __( 'Use default employee', 'axiachat-ai' ),
                    'ask'     => __( 'Bot asks the customer preferred employee', 'axiachat-ai' ),
                ],
                'default' => 'any',
            ],
            'amelia_provider_id' => [
                'type'       => 'select_staff',
                'label'      => __( 'Default Employee', 'axiachat-ai' ),
                'depends_on' => [ 'amelia_provider_mode' => 'default' ],
            ],
        ];
    }
    
    /**
     * Validate settings
     */
    public function validate_settings( $settings ) {
        if ( ( $settings['amelia_service_mode'] ?? 'default' ) === 'default' && empty( $settings['amelia_service_id'] ) ) {
            return new WP_Error( 'missing_service', __( 'Default service required', 'axiachat-ai' ) );
        }
        if ( ( $settings['amelia_provider_mode'] ?? 'any' ) === 'default' && empty( $settings['amelia_provider_id'] ) ) {
            return new WP_Error( 'missing_provider', __( 'Default employee required', 'axiachat-ai' ) );
        }
        return true;
    }
    
    /**
     * Get cached settings
     */
    private function get_settings() {
        if ( $this->settings === null ) {
            $this->settings = AIChat_Appointments_Manager::get_settings();
        }
        return $this->settings;
    }
}
