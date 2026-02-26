<?php
/**
 * Internal Appointments Adapter
 * 
 * Handles appointments using the plugin's internal database tables.
 * This is the default adapter when no external booking system is configured.
 * 
 * @package AxiaChat_AI
 * @subpackage Appointments
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Appointments_Adapter_Internal implements AIChat_Appointments_Adapter_Interface {
    
    /** @var array Cached settings */
    private $settings;
    
    /** @var string Table name */
    private $table_name;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'aichat_appointments';
    }
    
    /**
     * Check if adapter is available
     */
    public function is_available() {
        return true; // Always available
    }
    
    /**
     * Get adapter info
     */
    public function get_info() {
        return [
            'id'          => 'internal',
            'name'        => __( 'Internal Database', 'axiachat-ai' ),
            'description' => __( 'Use the plugin\'s built-in appointment system with working hours configuration.', 'axiachat-ai' ),
            'icon'        => 'bi-database',
        ];
    }
    
    /**
     * Get services (internal has no multi-service support by default)
     */
    public function get_services() {
        // Internal adapter can have a simple services list from settings
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
     * Get staff (internal has no staff support)
     */
    public function get_staff( $service_id = 0 ) {
        // Internal adapter doesn't have staff
        return [];
    }
    
    /**
     * Get available slots for a date
     */
    public function get_available_slots( $date, $params = [] ) {
        $settings = $this->get_settings();
        
        // Parse date
        $date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
        if ( ! $date_obj ) {
            return [];
        }
        
        $day_of_week = (int) $date_obj->format( 'w' ); // 0 = Sunday
        $working = $settings['working_hours'][ $day_of_week ] ?? null;
        
        // Check if this day is enabled
        if ( ! $working || ! $working['enabled'] ) {
            return [];
        }
        
        // Check if date is blocked
        if ( in_array( $date, $settings['blocked_dates'] ?? [], true ) ) {
            return [];
        }
        
        // Check minimum advance time
        $timezone = new DateTimeZone( $settings['timezone'] ?? 'UTC' );
        $now = new DateTime( 'now', $timezone );
        // Reset microseconds to avoid comparison issues
        $now->setTime( (int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'), 0 );
        $min_time = clone $now;
        $min_time->modify( '+' . ( $settings['min_advance'] ?? 60 ) . ' minutes' );
        
        // Check maximum advance days
        $max_date = clone $now;
        $max_date->modify( '+' . ( $settings['max_advance_days'] ?? 30 ) . ' days' );
        
        if ( $date_obj > $max_date ) {
            return [];
        }
        
        // Get time slots for this day (support multiple slots)
        $time_slots = [];
        if ( ! empty( $working['slots'] ) && is_array( $working['slots'] ) ) {
            $time_slots = $working['slots'];
        } else {
            // Legacy format: single start/end
            $time_slots = [
                [
                    'start' => $working['start'] ?? '09:00',
                    'end'   => $working['end'] ?? '18:00',
                ]
            ];
        }
        
        // Generate all possible slots from all time ranges
        $slots = [];
        $duration = (int) ( $settings['slot_duration'] ?? 30 );
        $buffer = (int) ( $settings['buffer_before'] ?? 0 ) + (int) ( $settings['buffer_after'] ?? 0 );
        
        // Get existing appointments for this date
        $booked = $this->get_booked_slots( $date );
        
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
                
                // Check if slot is already booked
                if ( ! in_array( $slot_time, $booked, true ) ) {
                    $slots[] = [
                        'time'     => $slot_time,
                        'end_time' => $slot_end->format( 'H:i' ),
                    ];
                }
                
                $start->modify( '+' . ( $duration + $buffer ) . ' minutes' );
            }
        }
        
        // Sort slots by time
        usort( $slots, function( $a, $b ) {
            return strcmp( $a['time'], $b['time'] );
        } );
        
        return $slots;
    }
    
    /**
     * Get booked slot times for a date
     */
    private function get_booked_slots( $date ) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table_name is a trusted plugin table name.
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
        $results = $wpdb->get_col( $wpdb->prepare( 
            "SELECT start_time FROM {$this->table_name}
             WHERE appointment_date = %s 
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
        
        // Validate required fields
        $required = [ 'customer_name', 'customer_email', 'appointment_date', 'start_time' ];
        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                return new WP_Error( 
                    'missing_field', 
                    /* translators: %s: required field name */
                    sprintf( __( 'Missing required field: %s', 'axiachat-ai' ), $field )
                );
            }
        }
        
        // Validate email
        if ( ! is_email( $data['customer_email'] ) ) {
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
        
        // Collect slot times for debugging
        $slot_times = array_map( function( $s ) { return $s['time']; }, $available_slots );
        aichat_log_debug( '[Adapter Internal] book slot check', [
            'requested_time' => $requested_time,
            'original_time' => $data['start_time'],
            'available_slot_times' => $slot_times,
            'date' => $data['appointment_date'],
        ]);
        
        $slot_available = false;
        $slot_end_time = '';
        foreach ( $available_slots as $slot ) {
            if ( $slot['time'] === $requested_time ) {
                $slot_available = true;
                $slot_end_time = $slot['end_time'];
                break;
            }
        }
        
        aichat_log_debug( '[Adapter Internal] book slot result', [
            'slot_available' => $slot_available,
            'slot_end_time' => $slot_end_time,
        ]);
        
        if ( ! $slot_available ) {
            return new WP_Error( 
                'slot_unavailable', 
                __( 'The requested time slot is no longer available. Please choose another time.', 'axiachat-ai' )
            );
        }
        
        $booking_code = $this->generate_booking_code();
        $status = ( $settings['auto_confirm'] ?? true ) ? 'confirmed' : 'pending';
        
        $insert_data = [
            'booking_code'     => $booking_code,
            'customer_name'    => sanitize_text_field( $data['customer_name'] ),
            'customer_email'   => sanitize_email( $data['customer_email'] ),
            'customer_phone'   => sanitize_text_field( $data['customer_phone'] ?? '' ),
            'service'          => sanitize_text_field( $data['service'] ?? '' ),
            'staff'            => sanitize_text_field( $data['staff'] ?? '' ),
            'appointment_date' => sanitize_text_field( $data['appointment_date'] ),
            'start_time'       => sanitize_text_field( $requested_time ),
            'end_time'         => sanitize_text_field( $slot_end_time ),
            'timezone'         => $settings['timezone'] ?? 'UTC',
            'status'           => $status,
            'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
            'source'           => 'internal',
            'bot_slug'         => sanitize_text_field( $data['bot_slug'] ?? '' ),
            'session_id'       => sanitize_text_field( $data['session_id'] ?? '' ),
            'external_source'  => 'internal',
        ];
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert( $this->table_name, $insert_data );
        
        if ( ! $result ) {
            return new WP_Error( 'db_error', __( 'Failed to save appointment', 'axiachat-ai' ) );
        }
        
        $appointment_id = $wpdb->insert_id;
        
        // Trigger action for integrations
        do_action( 'aichat_appointment_booked', $appointment_id, $insert_data );
        
        return [
            'success'      => true,
            'id'           => $appointment_id,
            'booking_code' => $booking_code,
            'status'       => $status,
            'date'         => $insert_data['appointment_date'],
            'time'         => $insert_data['start_time'],
            'end_time'     => $insert_data['end_time'],
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
            return new WP_Error( 'not_found', __( 'Appointment not found with that code', 'axiachat-ai' ) );
        }
        
        if ( $appointment->status === 'cancelled' ) {
            return new WP_Error( 'already_cancelled', __( 'This appointment is already cancelled', 'axiachat-ai' ) );
        }
        
        // Check if appointment is in the past
        $apt_datetime = $appointment->appointment_date . ' ' . $appointment->start_time;
        if ( strtotime( $apt_datetime ) < time() ) {
            return new WP_Error( 'past_appointment', __( 'Cannot cancel past appointments', 'axiachat-ai' ) );
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $updated = $wpdb->update(
            $this->table_name,
            [ 'status' => 'cancelled' ],
            [ 'id' => $appointment->id ]
        );
        
        if ( $updated === false ) {
            return new WP_Error( 'db_error', __( 'Failed to cancel appointment', 'axiachat-ai' ) );
        }
        
        do_action( 'aichat_appointment_cancelled', $appointment->id );
        
        return true;
    }
    
    /**
     * Get appointment by booking code
     */
    public function get_by_code( $booking_code ) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $this->table_name is a trusted plugin table name.
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE booking_code = %s",
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
     * Get appointments list with filters
     */
    public function get_list( $args = [] ) {
        global $wpdb;
        
        $defaults = [
            'status'     => '',
            'date_from'  => '',
            'date_to'    => '',
            'search'     => '',
            'orderby'    => 'appointment_date',
            'order'      => 'DESC',
            'limit'      => 50,
            'offset'     => 0,
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $where = [ '1=1' ];
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
        
        if ( ! empty( $args['search'] ) ) {
            $where[] = '(customer_name LIKE %s OR customer_email LIKE %s OR booking_code LIKE %s)';
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $values[] = $search;
            $values[] = $search;
            $values[] = $search;
        }
        
        $where_sql = implode( ' AND ', $where );
        $orderby = sanitize_sql_orderby( $args['orderby'] . ' ' . $args['order'] ) ?: 'appointment_date DESC';
        
        $sql = "SELECT * FROM {$this->table_name} WHERE {$where_sql} ORDER BY {$orderby} LIMIT %d OFFSET %d";
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
        
        $valid_statuses = [ 'pending', 'confirmed', 'completed', 'cancelled', 'no_show' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return new WP_Error( 'invalid_status', __( 'Invalid status', 'axiachat-ai' ) );
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table_name,
            [ 'status' => $status ],
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
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table_name,
            $update_data,
            [ 'id' => $id ]
        );
        
        if ( $result === false ) {
            return new WP_Error( 'db_error', __( 'Failed to update appointment', 'axiachat-ai' ) );
        }
        
        return true;
    }
    
    /**
     * Get settings fields for admin
     */
    public function get_settings_fields() {
        return [
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
