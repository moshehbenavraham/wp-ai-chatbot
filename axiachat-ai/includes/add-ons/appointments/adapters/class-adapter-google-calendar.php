<?php
/**
 * Google Calendar Appointments Adapter
 * 
 * Hybrid adapter that uses internal working hours for availability
 * and syncs appointments with Google Calendar.
 * 
 * Strategy:
 * - Availability: Based on internal working hours configuration
 * - Conflicts: Checks Google Calendar for existing events
 * - Booking: Saves to internal DB + creates Google Calendar event
 * - Cancellation: Updates internal DB + deletes Google Calendar event
 * 
 * @package AxiaChat_AI
 * @subpackage Appointments
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Appointments_Adapter_Google_Calendar implements AIChat_Appointments_Adapter_Interface {
    
    /** @var array Cached settings */
    private $settings;
    
    /** @var string Table name */
    private $table_name;
    
    /** @var AIChat_GCal_OAuth OAuth handler */
    private $oauth;
    
    /** @var AIChat_GCal_Client API client */
    private $client;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aichat_appointments';
        $this->oauth = new AIChat_GCal_OAuth();
        $this->client = new AIChat_GCal_Client( $this->oauth );
    }
    
    /**
     * Check if adapter is available
     * Always available (doesn't depend on external plugin)
     */
    public function is_available() {
        return true;
    }
    
    /**
     * Check if connected to Google Calendar
     */
    public function is_connected() {
        return $this->oauth->is_connected();
    }
    
    /**
     * Get OAuth handler
     */
    public function get_oauth() {
        return $this->oauth;
    }
    
    /**
     * Get API client
     */
    public function get_client() {
        return $this->client;
    }
    
    /**
     * Get adapter info
     */
    public function get_info() {
        return [
            'id'          => 'google_calendar',
            'name'        => 'Google Calendar',
            'description' => __( 'Sync appointments with Google Calendar. Uses internal availability settings and creates events in your Google Calendar.', 'axiachat-ai' ),
            'icon'        => 'bi-google',
        ];
    }
    
    /**
     * Get services (uses internal service list)
     */
    public function get_services() {
        $settings = $this->get_settings();
        $services = $settings['services'] ?? [];
        
        if ( empty( $services ) ) {
            return [
                [
                    'id'       => 0,
                    'name'     => __( 'Appointment', 'axiachat-ai' ),
                    'duration' => $settings['slot_duration'] ?? 30,
                    'price'    => 0,
                ],
            ];
        }
        
        return $services;
    }
    
    /**
     * Get staff (not supported for Google Calendar)
     */
    public function get_staff( $service_id = 0 ) {
        return [];
    }
    
    /**
     * Get available slots for a date
     * Uses internal working hours, excludes slots that conflict with Google Calendar events
     */
    public function get_available_slots( $date, $params = [] ) {
        $settings = $this->get_settings();
        
        aichat_appointments_log( '[GCal Adapter] get_available_slots() called', [
            'date'        => $date,
            'destination' => $settings['destination'] ?? 'unknown',
        ] );
        
        // Parse date
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $date_obj ) {
            aichat_appointments_log( '[GCal Adapter] get_available_slots() invalid date format' );
            return [];
        }
        
        $day_of_week = (int) $date_obj->format( 'w' ); // 0 = Sunday
        $working = $settings['working_hours'][ $day_of_week ] ?? null;
        
        aichat_appointments_log( '[GCal Adapter] get_available_slots() working hours', [
            'day_of_week'    => $day_of_week,
            'working_config' => $working,
        ] );
        
        // Check if this day is enabled
        if ( ! $working || ! $working['enabled'] ) {
            aichat_appointments_log( '[GCal Adapter] get_available_slots() day not enabled' );
            return [];
        }
        
        // Check if date is blocked
        if ( in_array( $date, $settings['blocked_dates'] ?? [], true ) ) {
            aichat_appointments_log( '[GCal Adapter] get_available_slots() date is blocked' );
            return [];
        }
        
        // Check minimum advance time
        $timezone = new DateTimeZone( $settings['timezone'] ?? 'UTC' );
        $now = new DateTime( 'now', $timezone );
        // Reset microseconds to avoid comparison issues
        $now->setTime( (int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'), 0 );
        $min_time = clone $now;
        $min_time->modify( '+' . ( $settings['min_advance'] ?? 60 ) . ' minutes' );
        
        aichat_appointments_log( '[GCal Adapter] get_available_slots() time checks', [
            'timezone'  => $settings['timezone'] ?? 'UTC',
            'now'       => $now->format( 'Y-m-d H:i:s' ),
            'min_time'  => $min_time->format( 'Y-m-d H:i:s' ),
        ] );
        
        // Check maximum advance days
        $max_date = clone $now;
        $max_date->modify( '+' . ( $settings['max_advance_days'] ?? 30 ) . ' days' );
        
        if ( $date_obj > $max_date ) {
            aichat_appointments_log( '[GCal Adapter] get_available_slots() date exceeds max advance days' );
            return [];
        }
        
        // Get time slots for this day
        $time_slots = [];
        if ( ! empty( $working['slots'] ) && is_array( $working['slots'] ) ) {
            $time_slots = $working['slots'];
        } else {
            $time_slots = [
                [
                    'start' => $working['start'] ?? '09:00',
                    'end'   => $working['end'] ?? '18:00',
                ]
            ];
        }
        
        aichat_appointments_log( '[GCal Adapter] get_available_slots() time_slots config', [
            'time_slots' => $time_slots,
        ] );
        
        // Generate all possible slots
        $slots = [];
        $duration = (int) ( $settings['slot_duration'] ?? 30 );
        $buffer = (int) ( $settings['buffer_before'] ?? 0 ) + (int) ( $settings['buffer_after'] ?? 0 );
        
        // Get booked slots from internal database
        $booked = $this->get_booked_slots( $date );
        
        // Get busy times from Google Calendar
        $gcal_busy = [];
        if ( $this->oauth->is_connected() ) {
            $gcal_busy = $this->client->get_busy_times( $date, $settings['timezone'] ?? 'UTC' );
        }
        
        foreach ( $time_slots as $time_range ) {
            $start = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time_range['start'], $timezone );
            $end = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . $time_range['end'], $timezone );
            
            if ( ! $start || ! $end ) {
                continue;
            }
            
            // Reset microseconds to avoid comparison issues (PHP 7.1+ sets current microseconds on createFromFormat)
            $start->setTime( (int) $start->format('H'), (int) $start->format('i'), 0, 0 );
            $end->setTime( (int) $end->format('H'), (int) $end->format('i'), 0, 0 );
            
            while ( $start < $end ) {
                $slot_end = clone $start;
                $slot_end->modify( '+' . $duration . ' minutes' );
                
                // Check if slot is in the past
                if ( $start < $min_time ) {
                    $start->modify( '+' . ( $duration + $buffer ) . ' minutes' );
                    continue;
                }
                
                // Check if slot exceeds end time
                if ( $slot_end > $end ) {
                    break;
                }
                
                $slot_time = $start->format( 'H:i' );
                $slot_end_time = $slot_end->format( 'H:i' );
                
                // Check if slot is already booked in internal DB
                if ( in_array( $slot_time, $booked, true ) ) {
                    $start->modify( '+' . ( $duration + $buffer ) . ' minutes' );
                    continue;
                }
                
                // Check if slot conflicts with Google Calendar events
                $has_gcal_conflict = $this->check_gcal_conflict( $slot_time, $slot_end_time, $gcal_busy );
                
                if ( ! $has_gcal_conflict ) {
                    $slots[] = [
                        'time'     => $slot_time,
                        'end_time' => $slot_end_time,
                    ];
                }
                
                $start->modify( '+' . ( $duration + $buffer ) . ' minutes' );
            }
        }
        
        // Sort slots by time
        usort( $slots, function( $a, $b ) {
            return strcmp( $a['time'], $b['time'] );
        } );
        
        aichat_appointments_log( '[GCal Adapter] get_available_slots() returning', [
            'total_slots'    => count( $slots ),
            'slot_times'     => array_column( $slots, 'time' ),
            'booked_slots'   => $booked,
            'gcal_busy'      => $gcal_busy,
        ] );
        
        return $slots;
    }
    
    /**
     * Check if a slot conflicts with Google Calendar busy times
     */
    private function check_gcal_conflict( $slot_start, $slot_end, $busy_times ) {
        $start = strtotime( $slot_start );
        $end = strtotime( $slot_end );
        
        foreach ( $busy_times as $busy ) {
            $busy_start = strtotime( $busy['start'] );
            $busy_end = strtotime( $busy['end'] );
            
            // Check for overlap
            if ( $start < $busy_end && $end > $busy_start ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get booked slot times from internal database
     */
    private function get_booked_slots( $date ) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table_name is a trusted plugin table name.
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_col( $wpdb->prepare( 
            "SELECT start_time FROM {$this->table_name}
             WHERE appointment_date = %s 
             AND source = 'google_calendar'
             AND status NOT IN ('cancelled', 'no_show')",
            $date
        ) );
        
        return $results ?: [];
    }
    
    /**
     * Book an appointment
     */
    public function book( $data ) {
        global $wpdb;
        $settings = $this->get_settings();
        
        aichat_appointments_log( '[GCal Adapter] book() called', [
            'customer_name'    => $data['customer_name'] ?? '',
            'customer_email'   => $data['customer_email'] ?? '',
            'appointment_date' => $data['appointment_date'] ?? '',
            'start_time'       => $data['start_time'] ?? '',
            'service'          => $data['service'] ?? '',
        ] );
        
        // Validate required fields
        $required = [ 'customer_name', 'customer_email', 'appointment_date', 'start_time' ];
        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                aichat_appointments_log( '[GCal Adapter] book() missing field: ' . $field );
                return new WP_Error( 
                    'missing_field', 
                    sprintf(
                        /* translators: %s: Field name that is required but missing */
                        __( 'Missing required field: %s', 'axiachat-ai' ),
                        $field
                    )
                );
            }
        }
        
        // Validate email
        if ( ! is_email( $data['customer_email'] ) ) {
            aichat_appointments_log( '[GCal Adapter] book() invalid email: ' . $data['customer_email'] );
            return new WP_Error( 'invalid_email', __( 'Invalid email address', 'axiachat-ai' ) );
        }
        
        // Check if slot is available
        $available_slots = $this->get_available_slots( $data['appointment_date'] );
        // Normalize time format (9:00 -> 09:00) without timezone conversion
        // Use DateTime to parse and format, avoiding strtotime/wp_date timezone issues
        $time_obj = DateTime::createFromFormat( 'H:i', $data['start_time'] );
        if ( ! $time_obj ) {
            // Try with seconds
            $time_obj = DateTime::createFromFormat( 'H:i:s', $data['start_time'] );
        }
        $requested_time = $time_obj ? $time_obj->format( 'H:i' ) : $data['start_time'];
        
        aichat_appointments_log( '[GCal Adapter] book() checking availability', [
            'requested_date'    => $data['appointment_date'],
            'requested_time'    => $requested_time,
            'original_time'     => $data['start_time'],
            'available_slots'   => array_map( function( $s ) { return $s['time']; }, $available_slots ),
            'total_slots_found' => count( $available_slots ),
        ] );
        
        $slot_available = false;
        $slot_end_time = '';
        foreach ( $available_slots as $slot ) {
            if ( $slot['time'] === $requested_time ) {
                $slot_available = true;
                $slot_end_time = $slot['end_time'];
                aichat_appointments_log( '[GCal Adapter] book() slot matched', [
                    'slot_time'     => $slot['time'],
                    'slot_end_time' => $slot_end_time,
                ] );
                break;
            }
        }
        
        if ( ! $slot_available ) {
            aichat_appointments_log( '[GCal Adapter] book() slot NOT available', [
                'requested_time' => $requested_time,
                'available_times' => array_column( $available_slots, 'time' ),
            ] );
            return new WP_Error( 
                'slot_unavailable', 
                __( 'The requested time slot is no longer available. Please choose another time.', 'axiachat-ai' )
            );
        }
        
        // Generate booking code
        $booking_code = $this->generate_booking_code();
        $status = ( $settings['auto_confirm'] ?? true ) ? 'confirmed' : 'pending';
        
        // Prepare appointment data
        $appointment_data = [
            'booking_code'     => $booking_code,
            'customer_name'    => sanitize_text_field( $data['customer_name'] ),
            'customer_email'   => sanitize_email( $data['customer_email'] ),
            'customer_phone'   => sanitize_text_field( $data['customer_phone'] ?? '' ),
            'service'          => sanitize_text_field( $data['service'] ?? '' ),
            'staff'            => '',
            'appointment_date' => sanitize_text_field( $data['appointment_date'] ),
            'start_time'       => sanitize_text_field( $requested_time ),
            'end_time'         => sanitize_text_field( $slot_end_time ),
            'timezone'         => $settings['timezone'] ?? 'UTC',
            'status'           => $status,
            'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
            'source'           => 'google_calendar',
            'bot_slug'         => sanitize_text_field( $data['bot_slug'] ?? '' ),
            'session_id'       => sanitize_text_field( $data['session_id'] ?? '' ),
            'external_source'  => 'google_calendar',
            'external_id'      => '', // Will be updated after GCal creation
        ];
        
        // Insert into internal database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert( $this->table_name, $appointment_data );
        
        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to save appointment', 'axiachat-ai' ) );
        }
        
        $appointment_id = $wpdb->insert_id;
        $appointment_data['id'] = $appointment_id;
        
        // Create Google Calendar event if connected
        $gcal_link = '';
        if ( $this->oauth->is_connected() ) {
            $gcal_result = $this->client->create_event( $appointment_data );
            
            if ( ! is_wp_error( $gcal_result ) && ! empty( $gcal_result['event_id'] ) ) {
                // Update with Google Calendar event ID
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $wpdb->update(
                    $this->table_name,
                    [ 'external_id' => $gcal_result['event_id'] ],
                    [ 'id' => $appointment_id ]
                );
                
                $gcal_link = $gcal_result['html_link'] ?? '';
                
                aichat_appointments_log( 'Appointment synced to Google Calendar', [
                    'appointment_id' => $appointment_id,
                    'gcal_event_id'  => $gcal_result['event_id'],
                ] );
            } else {
                aichat_appointments_log( 'Failed to sync to Google Calendar', [
                    'appointment_id' => $appointment_id,
                    'error'          => is_wp_error( $gcal_result ) ? $gcal_result->get_error_message() : 'Unknown',
                ] );
            }
        }
        
        // Trigger action for integrations
        do_action( 'aichat_appointment_booked', $appointment_id, $appointment_data );
        
        return [
            'success'      => true,
            'id'           => $appointment_id,
            'booking_code' => $booking_code,
            'status'       => $status,
            'date'         => $appointment_data['appointment_date'],
            'time'         => $appointment_data['start_time'],
            'end_time'     => $appointment_data['end_time'],
            'gcal_link'    => $gcal_link,
        ];
    }
    
    /**
     * Generate unique booking code
     */
    private function generate_booking_code() {
        $prefix = 'APT';
        $unique = strtoupper( substr( md5( uniqid( wp_rand(), true ) ), 0, 8 ) );
        return $prefix . '-' . $unique;
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
        
        // Delete from Google Calendar if connected and has external_id
        if ( $this->oauth->is_connected() && ! empty( $appointment->external_id ) ) {
            $delete_result = $this->client->delete_event( $appointment->external_id );
            
            if ( is_wp_error( $delete_result ) ) {
                aichat_appointments_log( 'Failed to delete Google Calendar event', [
                    'booking_code'  => $booking_code,
                    'gcal_event_id' => $appointment->external_id,
                    'error'         => $delete_result->get_error_message(),
                ] );
            }
        }
        
        // Update status in internal database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table_name,
            [ 
                'status'     => 'cancelled',
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'booking_code' => $booking_code ]
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', __( 'Failed to cancel appointment', 'axiachat-ai' ) );
        }
        
        return true;
    }
    
    /**
     * Get appointment by booking code
     */
    public function get_by_code( $booking_code ) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table_name is a trusted plugin table name.
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE booking_code = %s AND source = 'google_calendar'",
            $booking_code
        ) );
    }
    
    /**
     * Get appointment by ID
     */
    public function get( $id ) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table_name is a trusted plugin table name.
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ) );
    }
    
    /**
     * Get appointments list
     */
    public function get_list( $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'status'     => '',
            'date_from'  => '',
            'date_to'    => '',
            'limit'      => 50,
            'offset'     => 0,
            'order'      => 'DESC',
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $where = [ "source = 'google_calendar'" ];
        $values = [];
        
        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'appointment_date >= %s';
            $values[] = $args['date_from'];
        }
        
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'appointment_date <= %s';
            $values[] = $args['date_to'];
        }
        
        $where_sql = implode( ' AND ', $where );
        $order = $args['order'] === 'ASC' ? 'ASC' : 'DESC';
        
        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY appointment_date {$order}, start_time {$order} LIMIT %d OFFSET %d";
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        if ( ! empty( $values ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql uses plugin-controlled table name; prepared here.
            $sql = $wpdb->prepare( $sql, $values );
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql prepared above or contains only safe table/column names.
        return $wpdb->get_results( $sql );
    }
    
    /**
     * Update appointment status
     */
    public function update_status( $id, $status ) {
        global $wpdb;
        
        $valid_statuses = [ 'pending', 'confirmed', 'cancelled', 'completed', 'no_show' ];
        if ( ! in_array( $status, $valid_statuses ) ) {
            return new WP_Error( 'invalid_status', __( 'Invalid status', 'axiachat-ai' ) );
        }
        
        // If cancelling, also delete from Google Calendar
        if ( $status === 'cancelled' ) {
            $appointment = $this->get( $id );
            if ( $appointment && $this->oauth->is_connected() && ! empty( $appointment->external_id ) ) {
                $this->client->delete_event( $appointment->external_id );
            }
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table_name,
            [ 
                'status'     => $status,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id ]
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', __( 'Failed to update status', 'axiachat-ai' ) );
        }
        
        return true;
    }
    
    /**
     * Update appointment data
     */
    public function update( $id, $data ) {
        global $wpdb;
        
        $allowed_fields = [
            'customer_name',
            'customer_email',
            'customer_phone',
            'service',
            'appointment_date',
            'start_time',
            'end_time',
            'notes',
            'status',
        ];
        
        $update_data = [];
        foreach ( $allowed_fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $update_data[ $field ] = $data[ $field ];
            }
        }
        
        if ( empty( $update_data ) ) {
            return new WP_Error( 'no_data', __( 'No data to update', 'axiachat-ai' ) );
        }
        
        $update_data['updated_at'] = current_time( 'mysql' );
        
        // Update internal database
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            [ 'id' => $id ]
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', __( 'Failed to update appointment', 'axiachat-ai' ) );
        }
        
        // Update Google Calendar event if connected
        $appointment = $this->get( $id );
        if ( $appointment && $this->oauth->is_connected() && ! empty( $appointment->external_id ) ) {
            $this->client->update_event( $appointment->external_id, $update_data );
        }
        
        return true;
    }
    
    /**
     * Get settings fields for admin
     */
    public function get_settings_fields() {
        return [
            'gcal_connection' => [
                'type'    => 'gcal_oauth',
                'label'   => __( 'Google Calendar Connection', 'axiachat-ai' ),
            ],
            'working_hours' => [
                'type'    => 'working_hours',
                'label'   => __( 'Working Hours', 'axiachat-ai' ),
                'default' => $this->get_default_working_hours(),
            ],
            'slot_duration' => [
                'type'    => 'number',
                'label'   => __( 'Slot Duration (minutes)', 'axiachat-ai' ),
                'default' => 30,
                'min'     => 5,
                'max'     => 480,
            ],
            'min_advance' => [
                'type'    => 'number',
                'label'   => __( 'Minimum Advance Time (minutes)', 'axiachat-ai' ),
                'default' => 60,
            ],
            'max_advance_days' => [
                'type'    => 'number',
                'label'   => __( 'Maximum Days in Advance', 'axiachat-ai' ),
                'default' => 30,
            ],
        ];
    }
    
    /**
     * Get default working hours
     */
    private function get_default_working_hours() {
        return [
            1 => [ 'enabled' => true,  'start' => '09:00', 'end' => '18:00' ],
            2 => [ 'enabled' => true,  'start' => '09:00', 'end' => '18:00' ],
            3 => [ 'enabled' => true,  'start' => '09:00', 'end' => '18:00' ],
            4 => [ 'enabled' => true,  'start' => '09:00', 'end' => '18:00' ],
            5 => [ 'enabled' => true,  'start' => '09:00', 'end' => '18:00' ],
            6 => [ 'enabled' => false, 'start' => '10:00', 'end' => '14:00' ],
            0 => [ 'enabled' => false, 'start' => '10:00', 'end' => '14:00' ],
        ];
    }
    
    /**
     * Validate settings
     */
    public function validate_settings( $settings ) {
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
