<?php
/**
 * Bookly Appointments Adapter
 * 
 * Integrates with the Bookly plugin for appointment booking.
 * Supports services, staff, and Bookly's slot calculation.
 * 
 * @package AxiaChat_AI
 * @subpackage Appointments
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Appointments_Adapter_Bookly implements AIChat_Appointments_Adapter_Interface {
    
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
     * Check if Bookly is available
     */
    public function is_available() {
        return class_exists( 'Bookly\Lib\Plugin' ) || 
               defined( 'BOOKLY_VERSION' ) || 
               class_exists( 'BooklyLite\Lib\Plugin' );
    }
    
    /**
     * Get adapter info
     */
    public function get_info() {
        return [
            'id'          => 'bookly',
            'name'        => 'Bookly',
            'description' => __( 'Integrate with Bookly plugin. Supports multiple services, staff members, and locations.', 'axiachat-ai' ),
            'icon'        => 'bi-calendar2-check',
        ];
    }
    
    /**
     * Get services from Bookly
     */
    public function get_services() {
        if ( ! $this->is_available() ) {
            return [];
        }
        
        global $wpdb;
        
        $table = $wpdb->prefix . 'bookly_services';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            return [];
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name.
        $services = $wpdb->get_results(
            "SELECT id, title, duration, price, visibility 
             FROM {$table} 
             WHERE visibility IN ('public', 'private')
             ORDER BY position, title"
        );
        
        if ( ! $services ) {
            return [];
        }
        
        $result = [];
        foreach ( $services as $service ) {
            $result[] = [
                'id'       => (int) $service->id,
                'name'     => $service->title,
                'duration' => (int) $service->duration,
                'price'    => (float) $service->price,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get staff from Bookly
     */
    public function get_staff( $service_id = 0 ) {
        if ( ! $this->is_available() ) {
            return [];
        }
        
        global $wpdb;
        
        $staff_table = $wpdb->prefix . 'bookly_staff';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $staff_table is a trusted plugin table name.
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$staff_table}'" ) !== $staff_table ) {
            return [];
        }
        
        if ( $service_id > 0 ) {
            $staff_services_table = $wpdb->prefix . 'bookly_staff_services';
            
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $staff_table and $staff_services_table are trusted plugin table names.
            $staff = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.id, s.full_name 
                 FROM {$staff_table} s
                 INNER JOIN {$staff_services_table} ss ON s.id = ss.staff_id
                 WHERE ss.service_id = %d AND s.visibility = 'public'
                 ORDER BY s.position, s.full_name",
                $service_id
            ) );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $staff_table is a trusted plugin table name.
            $staff = $wpdb->get_results(
                "SELECT id, full_name 
                 FROM {$staff_table} 
                 WHERE visibility = 'public'
                 ORDER BY position, full_name"
            );
        }
        
        if ( ! $staff ) {
            return [];
        }
        
        $result = [];
        foreach ( $staff as $member ) {
            $result[] = [
                'id'   => (int) $member->id,
                'name' => $member->full_name,
            ];
        }
        
        return $result;
    }
    
    /**
     * Get available slots from Bookly
     */
    public function get_available_slots( $date, $params = [] ) {
        if ( ! $this->is_available() ) {
            aichat_log_debug( '[Bookly Adapter] get_available_slots: Bookly not available' );
            return [];
        }
        
        $settings = $this->get_settings();
        
        $service_id = $this->resolve_service_id( $params['service'] ?? null, $settings );
        $staff_id   = $this->resolve_staff_id( $params['staff'] ?? null, $settings );
        
        aichat_log_debug( '[Bookly Adapter] get_available_slots: date=' . $date . ', service_id=' . $service_id . ', staff_id=' . $staff_id );
        
        if ( ! $service_id ) {
            // If no service could be resolved, check why
            $services = $this->get_services();
            if ( empty( $services ) ) {
                aichat_log_debug( '[Bookly Adapter] get_available_slots: No services found in Bookly' );
                return [ 'error' => __( 'No services configured in Bookly', 'axiachat-ai' ) ];
            } elseif ( count( $services ) > 1 ) {
                aichat_log_debug( '[Bookly Adapter] get_available_slots: Multiple services, none selected' );
                return [ 'error' => __( 'Please specify which service you want to book', 'axiachat-ai' ), 'available_services' => array_column( $services, 'name' ) ];
            }
            return [];
        }
        
        return $this->get_slots_via_database( $date, $service_id, $staff_id );
    }
    
    /**
     * Get slots by querying Bookly database directly
     */
    private function get_slots_via_database( $date, $service_id, $staff_id ) {
        global $wpdb;
        
        aichat_log_debug( '[Bookly Adapter] get_slots_via_database: Starting for date=' . $date . ', service=' . $service_id . ', staff=' . $staff_id );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $service = $wpdb->get_row( $wpdb->prepare(
            "SELECT duration FROM {$wpdb->prefix}bookly_services WHERE id = %d",
            $service_id
        ) );
        
        if ( ! $service ) {
            aichat_log_debug( '[Bookly Adapter] get_slots_via_database: Service not found' );
            return [];
        }
        
        // Bookly stores duration in SECONDS, convert to minutes
        $duration_seconds = (int) $service->duration;
        $duration = (int) ceil( $duration_seconds / 60 );  // Convert to minutes
        $day_of_week = (int) gmdate( 'N', strtotime( $date ) );
        
        aichat_log_debug( '[Bookly Adapter] get_slots_via_database: duration_seconds=' . $duration_seconds . ', duration_minutes=' . $duration . ', day_of_week=' . $day_of_week );
        
        $staff_ids = [];
        if ( $staff_id > 0 ) {
            $staff_ids = [ $staff_id ];
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $staff_for_service = $wpdb->get_col( $wpdb->prepare(
                "SELECT staff_id FROM {$wpdb->prefix}bookly_staff_services WHERE service_id = %d",
                $service_id
            ) );
            $staff_ids = $staff_for_service ?: [];
        }
        
        aichat_log_debug( '[Bookly Adapter] get_slots_via_database: staff_ids=' . implode( ',', $staff_ids ) );
        
        if ( empty( $staff_ids ) ) {
            aichat_log_debug( '[Bookly Adapter] get_slots_via_database: No staff assigned to this service' );
            return [];
        }
        
        $placeholders = implode( ',', array_fill( 0, count( $staff_ids ), '%d' ) );
        $query_args = array_merge( $staff_ids, [ $day_of_week ] );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders contains safe %d placeholders.
        $schedules = $wpdb->get_results( $wpdb->prepare(
            "SELECT staff_id, start_time, end_time 
             FROM {$wpdb->prefix}bookly_staff_schedule_items 
             WHERE staff_id IN ({$placeholders}) AND day_index = %d",
            $query_args
        ) );
        
        aichat_log_debug( '[Bookly Adapter] get_slots_via_database: schedules found=' . count( $schedules ) );
        
        if ( empty( $schedules ) ) {
            aichat_log_debug( '[Bookly Adapter] get_slots_via_database: No schedules for day_index=' . $day_of_week );
            return [];
        }
        
        // In Bookly, appointments table has the time slots, and status is in customer_appointments table
        // We need to get appointments that are not cancelled/rejected
        $booked_query_args = array_merge( [ $date ], $staff_ids );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders contains safe %d placeholders.
        $booked_slots = $wpdb->get_results( $wpdb->prepare(
            "SELECT a.staff_id, a.start_date, a.end_date
             FROM {$wpdb->prefix}bookly_appointments a
             LEFT JOIN {$wpdb->prefix}bookly_customer_appointments ca ON a.id = ca.appointment_id
             WHERE DATE(a.start_date) = %s 
             AND a.staff_id IN ({$placeholders})
             AND (ca.status IS NULL OR ca.status NOT IN ('cancelled', 'rejected'))",
            $booked_query_args
        ) );
        
        aichat_log_debug( '[Bookly Adapter] get_slots_via_database: booked_slots=' . count( $booked_slots ) );
        
        $slots = [];
        $now = new DateTime( 'now', new DateTimeZone( wp_timezone_string() ) );
        // Reset microseconds to avoid comparison issues
        $now->setTime( (int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'), 0 );
        $min_advance = (int) ( $this->get_settings()['min_advance'] ?? 60 );
        $min_time = clone $now;
        $min_time->modify( '+' . $min_advance . ' minutes' );
        
        foreach ( $schedules as $schedule ) {
            if ( empty( $schedule->start_time ) || empty( $schedule->end_time ) ) {
                aichat_log_debug( '[Bookly Adapter] get_slots_via_database: Empty schedule times for staff=' . $schedule->staff_id );
                continue;
            }
            
            aichat_log_debug( '[Bookly Adapter] get_slots_via_database: Processing schedule staff=' . $schedule->staff_id . ', start=' . $schedule->start_time . ', end=' . $schedule->end_time );
            
            $start = DateTime::createFromFormat( 'Y-m-d H:i:s', $date . ' ' . $schedule->start_time );
            $end = DateTime::createFromFormat( 'Y-m-d H:i:s', $date . ' ' . $schedule->end_time );
            
            if ( ! $start || ! $end ) {
                aichat_log_debug( '[Bookly Adapter] get_slots_via_database: Failed to parse schedule times. Trying H:i format...' );
                // Try without seconds
                $start = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . substr( $schedule->start_time, 0, 5 ) );
                $end = DateTime::createFromFormat( 'Y-m-d H:i', $date . ' ' . substr( $schedule->end_time, 0, 5 ) );
                
                if ( ! $start || ! $end ) {
                    aichat_log_debug( '[Bookly Adapter] get_slots_via_database: Still failed to parse times' );
                    continue;
                }
            }
            
            // Reset microseconds to avoid comparison issues (PHP 7.1+ sets current microseconds on createFromFormat)
            $start->setTime( (int) $start->format('H'), (int) $start->format('i'), 0, 0 );
            $end->setTime( (int) $end->format('H'), (int) $end->format('i'), 0, 0 );
            
            while ( $start < $end ) {
                $slot_end = clone $start;
                $slot_end->modify( '+' . $duration . ' minutes' );
                
                if ( $start < $min_time ) {
                    $start->modify( '+' . $duration . ' minutes' );
                    continue;
                }
                
                if ( $slot_end > $end ) {
                    break;
                }
                
                $is_booked = false;
                foreach ( $booked_slots as $booked ) {
                    if ( (int) $booked->staff_id !== (int) $schedule->staff_id ) {
                        continue;
                    }
                    
                    $booked_start = new DateTime( $booked->start_date );
                    $booked_end = new DateTime( $booked->end_date );
                    
                    if ( $start < $booked_end && $slot_end > $booked_start ) {
                        $is_booked = true;
                        break;
                    }
                }
                
                if ( ! $is_booked ) {
                    $slot_key = $start->format( 'H:i' );
                    if ( ! isset( $slots[ $slot_key ] ) ) {
                        $slots[ $slot_key ] = [
                            'time'     => $slot_key,
                            'end_time' => $slot_end->format( 'H:i' ),
                            'staff_id' => (int) $schedule->staff_id,
                        ];
                    }
                }
                
                $start->modify( '+' . $duration . ' minutes' );
            }
        }
        
        aichat_log_debug( '[Bookly Adapter] get_slots_via_database: Total slots found=' . count( $slots ) );
        
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
            
            $services = $this->get_services();
            foreach ( $services as $service ) {
                if ( strcasecmp( $service['name'], $service_input ) === 0 ) {
                    return $service['id'];
                }
            }
        }
        
        if ( ! empty( $settings['bookly_service_id'] ) ) {
            return (int) $settings['bookly_service_id'];
        }
        
        $services = $this->get_services();
        if ( count( $services ) === 1 ) {
            return $services[0]['id'];
        }
        
        return 0;
    }
    
    /**
     * Resolve staff ID from name/id or use default
     */
    private function resolve_staff_id( $staff_input, $settings ) {
        if ( ! empty( $staff_input ) ) {
            if ( is_numeric( $staff_input ) ) {
                return (int) $staff_input;
            }
            
            $staff = $this->get_staff();
            foreach ( $staff as $member ) {
                if ( strcasecmp( $member['name'], $staff_input ) === 0 ) {
                    return $member['id'];
                }
            }
        }
        
        if ( ! empty( $settings['bookly_staff_id'] ) ) {
            return (int) $settings['bookly_staff_id'];
        }
        
        $staff = $this->get_staff();
        if ( count( $staff ) === 1 ) {
            return $staff[0]['id'];
        }
        
        return 0;
    }
    
    /**
     * Book an appointment in Bookly
     */
    public function book( $data ) {
        if ( ! $this->is_available() ) {
            return new WP_Error( 'bookly_unavailable', __( 'Bookly is not available', 'axiachat-ai' ) );
        }
        
        global $wpdb;
        $settings = $this->get_settings();
        
        $required = [ 'customer_name', 'customer_email', 'appointment_date', 'start_time' ];
        foreach ( $required as $field ) {
            if ( empty( $data[ $field ] ) ) {
                /* translators: %s: required field name */
                return new WP_Error( 'missing_field', sprintf( __( 'Missing: %s', 'axiachat-ai' ), $field ) );
            }
        }
        
        if ( ! is_email( $data['customer_email'] ) ) {
            return new WP_Error( 'invalid_email', __( 'Invalid email', 'axiachat-ai' ) );
        }
        
        $service_id = $this->resolve_service_id( $data['service'] ?? null, $settings );
        $staff_id   = $this->resolve_staff_id( $data['staff'] ?? null, $settings );
        
        if ( ! $service_id ) {
            return new WP_Error( 'no_service', __( 'No service specified', 'axiachat-ai' ) );
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $service = $wpdb->get_row( $wpdb->prepare(
            "SELECT title, duration FROM {$wpdb->prefix}bookly_services WHERE id = %d",
            $service_id
        ) );
        
        if ( ! $service ) {
            return new WP_Error( 'invalid_service', __( 'Service not found', 'axiachat-ai' ) );
        }
        
        if ( $staff_id === 0 ) {
            $staff_for_service = $this->get_staff( $service_id );
            if ( ! empty( $staff_for_service ) ) {
                $staff_id = $staff_for_service[0]['id'];
            }
        }
        
        if ( ! $staff_id ) {
            return new WP_Error( 'no_staff', __( 'No staff available', 'axiachat-ai' ) );
        }
        
        $start_datetime = $data['appointment_date'] . ' ' . $data['start_time'] . ':00';
        // Bookly stores duration in seconds, so we use it directly (no multiplication)
        $duration_seconds = (int) $service->duration;
        $end_datetime = wp_date( 'Y-m-d H:i:s', strtotime( $start_datetime ) + $duration_seconds );
        
        aichat_log_debug( '[Bookly Book] Calculated times', [
            'start'            => $start_datetime,
            'end'              => $end_datetime,
            'duration_seconds' => $duration_seconds,
            'duration_minutes' => $duration_seconds / 60,
        ] );
        
        $customer_id = $this->get_or_create_bookly_customer( $data );
        if ( is_wp_error( $customer_id ) ) {
            return $customer_id;
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $apt_result = $wpdb->insert(
            $wpdb->prefix . 'bookly_appointments',
            [
                'staff_id'        => $staff_id,
                'service_id'      => $service_id,
                'start_date'      => $start_datetime,
                'end_date'        => $end_datetime,
                'extras_duration' => 0,
                'internal_note'   => sanitize_textarea_field( $data['notes'] ?? '' ),
                'created_from'    => 'frontend',
                'created_at'      => current_time( 'mysql' ),
            ]
        );
        
        if ( ! $apt_result ) {
            return new WP_Error( 'db_error', __( 'Failed to create appointment', 'axiachat-ai' ) );
        }
        
        $appointment_id = $wpdb->insert_id;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $wpdb->prefix . 'bookly_customer_appointments',
            [
                'customer_id'       => $customer_id,
                'appointment_id'    => $appointment_id,
                'number_of_persons' => 1,
                'status'            => $settings['auto_confirm'] ? 'approved' : 'pending',
                'created_from'      => 'frontend',
                'created_at'        => current_time( 'mysql' ),
            ]
        );
        
        $booking_code = 'APT-' . strtoupper( substr( md5( $appointment_id . uniqid() ), 0, 8 ) );
        
        // Get staff name for tracking
        $staff_name = '';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $staff_row = $wpdb->get_row( $wpdb->prepare(
            "SELECT full_name FROM {$wpdb->prefix}bookly_staff WHERE id = %d",
            $staff_id
        ) );
        if ( $staff_row ) {
            $staff_name = $staff_row->full_name;
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->insert(
            $this->tracking_table,
            [
                'booking_code'     => $booking_code,
                'customer_name'    => sanitize_text_field( $data['customer_name'] ),
                'customer_email'   => sanitize_email( $data['customer_email'] ),
                'customer_phone'   => sanitize_text_field( $data['customer_phone'] ?? '' ),
                'service'          => $service->title,
                'staff'            => $staff_name,
                'appointment_date' => $data['appointment_date'],
                'start_time'       => $data['start_time'],
                'end_time'         => wp_date( 'H:i', strtotime( $end_datetime ) ),
                'timezone'         => wp_timezone_string(),
                'status'           => $settings['auto_confirm'] ? 'confirmed' : 'pending',
                'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
                'source'           => 'bookly',
                'bot_slug'         => sanitize_text_field( $data['bot_slug'] ?? '' ),
                'session_id'       => sanitize_text_field( $data['session_id'] ?? '' ),
                'external_id'      => $appointment_id,
                'external_source'  => 'bookly',
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
            'service'      => $service->title,
        ];
    }
    
    /**
     * Get or create customer in Bookly
     */
    private function get_or_create_bookly_customer( $data ) {
        global $wpdb;
        
        $email = sanitize_email( $data['customer_email'] );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $customer = $wpdb->get_row( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bookly_customers WHERE email = %s",
            $email
        ) );
        
        if ( $customer ) {
            return (int) $customer->id;
        }
        
        $full_name = sanitize_text_field( $data['customer_name'] );
        $name_parts = explode( ' ', $full_name, 2 );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $wpdb->prefix . 'bookly_customers',
            [
                'full_name'  => $full_name,
                'first_name' => $name_parts[0],
                'last_name'  => $name_parts[1] ?? '',
                'email'      => $email,
                'phone'      => sanitize_text_field( $data['customer_phone'] ?? '' ),
                'created_at' => current_time( 'mysql' ),
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
        
        if ( ! empty( $appointment->external_id ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            $wpdb->update(
                $wpdb->prefix . 'bookly_customer_appointments',
                [ 'status' => 'cancelled' ],
                [ 'appointment_id' => $appointment->external_id ]
            );
        }
        
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
        
        $where = [ "external_source = 'bookly'" ];
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
        
        $bookly_map = [
            'pending' => 'pending', 'confirmed' => 'approved',
            'completed' => 'done', 'cancelled' => 'cancelled', 'no_show' => 'rejected',
        ];
        
        if ( ! empty( $appointment->external_id ) && isset( $bookly_map[ $status ] ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update(
                $wpdb->prefix . 'bookly_customer_appointments',
                [ 'status' => $bookly_map[ $status ] ],
                [ 'appointment_id' => $appointment->external_id ]
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
            'bookly_service_mode' => [
                'type'    => 'select',
                'label'   => __( 'Service Selection', 'axiachat-ai' ),
                'options' => [
                    'default' => __( 'Use default service (bot won\'t ask)', 'axiachat-ai' ),
                    'ask'     => __( 'Bot asks the customer which service', 'axiachat-ai' ),
                ],
                'default' => 'default',
                'help'    => __( 'If you have only one service or want the bot to use a fixed service, choose "default".', 'axiachat-ai' ),
            ],
            'bookly_service_id' => [
                'type'       => 'select_service',
                'label'      => __( 'Default Service', 'axiachat-ai' ),
                'depends_on' => [ 'bookly_service_mode' => 'default' ],
            ],
            'bookly_staff_mode' => [
                'type'    => 'select',
                'label'   => __( 'Staff Selection', 'axiachat-ai' ),
                'options' => [
                    'any'     => __( 'Any available staff (bot won\'t ask)', 'axiachat-ai' ),
                    'default' => __( 'Use default staff member', 'axiachat-ai' ),
                    'ask'     => __( 'Bot asks the customer preferred staff', 'axiachat-ai' ),
                ],
                'default' => 'any',
            ],
            'bookly_staff_id' => [
                'type'       => 'select_staff',
                'label'      => __( 'Default Staff', 'axiachat-ai' ),
                'depends_on' => [ 'bookly_staff_mode' => 'default' ],
            ],
        ];
    }
    
    /**
     * Validate settings
     */
    public function validate_settings( $settings ) {
        if ( ( $settings['bookly_service_mode'] ?? 'default' ) === 'default' && empty( $settings['bookly_service_id'] ) ) {
            return new WP_Error( 'missing_service', __( 'Default service required', 'axiachat-ai' ) );
        }
        if ( ( $settings['bookly_staff_mode'] ?? 'any' ) === 'default' && empty( $settings['bookly_staff_id'] ) ) {
            return new WP_Error( 'missing_staff', __( 'Default staff required', 'axiachat-ai' ) );
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
