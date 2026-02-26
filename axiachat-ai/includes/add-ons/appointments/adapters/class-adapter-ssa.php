<?php
/**
 * Simply Schedule Appointments (SSA) Adapter
 * 
 * Integrates with the SSA plugin for appointment booking.
 * Uses SSA's native API directly without wrapper classes.
 * 
 * @package AxiaChat_AI
 * @subpackage Appointments
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Appointments_Adapter_SSA implements AIChat_Appointments_Adapter_Interface {
    
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
     * Check if SSA is available
     */
    public function is_available() {
        return function_exists( 'ssa' ) 
            || class_exists( 'Simply_Schedule_Appointments' ) 
            || class_exists( 'SSA_Appointment_Model' )
            || class_exists( 'SSA_Appointment' );
    }
    
    /**
     * Get adapter info
     */
    public function get_info() {
        return [
            'id'          => 'ssa',
            'name'        => 'Simply Schedule Appointments',
            'description' => __( 'Integrate with Simply Schedule Appointments plugin for flexible scheduling.', 'axiachat-ai' ),
            'icon'        => 'bi-calendar-event',
        ];
    }
    
    /**
     * Get services (appointment types) from SSA
     */
    public function get_services() {
        if ( ! $this->is_available() ) {
            return [];
        }
        
        try {
            $rows = [];
            if ( function_exists( 'ssa' ) ) {
                $ssa = call_user_func( 'ssa' );
                if ( isset( $ssa->appointment_type_model ) ) {
                    if ( method_exists( $ssa->appointment_type_model, 'get_all_appointment_types' ) ) {
                        $rows = $ssa->appointment_type_model->get_all_appointment_types();
                    } elseif ( method_exists( $ssa->appointment_type_model, 'query' ) ) {
                        $rows = $ssa->appointment_type_model->query( [ 'status' => 'publish' ] );
                    }
                }
            }
            
            $result = [];
            foreach ( (array) $rows as $r ) {
                $id = isset( $r['id'] ) ? (int) $r['id'] : 0;
                if ( $id <= 0 ) {
                    continue;
                }
                
                $title = isset( $r['title'] ) ? (string) $r['title'] : ( isset( $r['name'] ) ? (string) $r['name'] : '' );
                $duration = isset( $r['duration'] ) ? (int) $r['duration'] : 30; // SSA stores in minutes
                
                $result[] = [
                    'id'       => $id,
                    'name'     => $title,
                    'duration' => $duration,
                    'price'    => 0, // SSA doesn't have built-in pricing
                ];
            }
            
            return $result;
        } catch ( \Exception $e ) {
            aichat_log_debug( '[SSA Adapter] get_services error: ' . $e->getMessage() );
            return [];
        }
    }
    
    /**
     * Get staff from SSA
     * Note: SSA doesn't have a built-in staff system like Bookly/Amelia
     */
    public function get_staff( $service_id = 0 ) {
        // SSA uses "Team Members" in Pro version only
        // For now, return empty - appointments work without staff selection
        return [];
    }
    
    /**
     * Get available slots from SSA
     * 
     * @param string $date   Date in Y-m-d format
     * @param array  $params Additional parameters
     * @return array Available slots
     */
    public function get_available_slots( $date, $params = [] ) {
        if ( ! $this->is_available() ) {
            aichat_log_debug( '[SSA Adapter] get_available_slots: SSA not available' );
            return [];
        }
        
        if ( ! function_exists( 'ssa' ) ) {
            aichat_log_debug( '[SSA Adapter] get_available_slots: ssa() function not found' );
            return [];
        }
        
        $ssa = call_user_func( 'ssa' );
        
        // Get service (appointment type) ID
        $service_id = 0;
        if ( ! empty( $params['service_id'] ) ) {
            $service_id = (int) $params['service_id'];
        } elseif ( ! empty( $params['service'] ) ) {
            // Find by name
            $services = $this->get_services();
            foreach ( $services as $svc ) {
                if ( strcasecmp( $svc['name'], $params['service'] ) === 0 ) {
                    $service_id = (int) $svc['id'];
                    break;
                }
            }
        }
        
        // Fallback to settings default or first available
        if ( $service_id <= 0 ) {
            $settings = AIChat_Appointments_Manager::get_settings();
            $service_id = (int) ( $settings['ssa_service_id'] ?? 0 );
            
            if ( $service_id <= 0 ) {
                // Get first available appointment type
                $services = $this->get_services();
                if ( ! empty( $services ) ) {
                    $service_id = (int) $services[0]['id'];
                }
            }
        }
        
        if ( $service_id <= 0 ) {
            aichat_log_debug( '[SSA Adapter] get_available_slots: No appointment type found' );
            return [];
        }
        
        aichat_log_debug( '[SSA Adapter] get_available_slots for date: ' . $date . ', appointment_type_id: ' . $service_id );
        
        try {
            // Get local timezone for this appointment type
            $local_tz = wp_timezone();
            if ( isset( $ssa->utils ) && method_exists( $ssa->utils, 'get_datetimezone' ) ) {
                try {
                    $local_tz = $ssa->utils->get_datetimezone( $service_id );
                } catch ( \Throwable $e ) {
                    // Use WP timezone as fallback
                }
            }
            
            // Build date range for the specific day
            $from_dt = new DateTimeImmutable( $date . ' 00:00:00', $local_tz );
            $to_dt   = new DateTimeImmutable( $date . ' 23:59:59', $local_tz );
            
            // Convert to UTC for SSA query
            $utc_tz = new DateTimeZone( 'UTC' );
            $from_utc = $from_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
            $to_utc   = $to_dt->setTimezone( $utc_tz )->format( 'Y-m-d H:i:s' );
            
            // Use SSA's availability query
            if ( ! class_exists( 'SSA_Appointment_Type_Object' ) || ! class_exists( 'SSA_Availability_Query' ) ) {
                aichat_log_debug( '[SSA Adapter] Required SSA classes not found' );
                return [];
            }
            
            $appointment_type = \SSA_Appointment_Type_Object::instance( $service_id );
            
            // Check if ssa_datetime function exists
            if ( ! function_exists( 'ssa_datetime' ) ) {
                aichat_log_debug( '[SSA Adapter] ssa_datetime function not found' );
                return [];
            }
            
            $start_dt = ssa_datetime( $from_utc );
            $end_dt   = ssa_datetime( $to_utc );
            
            // Check if Period class exists
            if ( ! class_exists( 'League\Period\Period' ) ) {
                aichat_log_debug( '[SSA Adapter] League\Period\Period class not found' );
                return [];
            }
            
            $period = new \League\Period\Period( $start_dt, $end_dt );
            
            $availability_query = new \SSA_Availability_Query(
                $appointment_type,
                $period,
                [
                    'cache_level_read'  => 1,
                    'cache_level_write' => 1,
                ]
            );
            
            $bookable = $availability_query->get_bookable_appointment_start_datetime_strings();
            
            if ( ! is_array( $bookable ) || empty( $bookable ) ) {
                aichat_log_debug( '[SSA Adapter] No bookable slots found for date: ' . $date );
                return [];
            }
            
            // Get duration for calculating end times
            $duration_minutes = 30;
            foreach ( $this->get_services() as $svc ) {
                if ( (int) $svc['id'] === $service_id ) {
                    $duration_minutes = (int) $svc['duration'];
                    break;
                }
            }
            
            // Convert to local time and build slots
            $slots = [];
            foreach ( $bookable as $item ) {
                $start_string = is_array( $item ) && isset( $item['start_date'] ) 
                    ? $item['start_date'] 
                    : ( is_string( $item ) ? $item : null );
                    
                if ( ! $start_string ) {
                    continue;
                }
                
                try {
                    // Convert to local datetime
                    if ( isset( $ssa->utils ) && method_exists( $ssa->utils, 'get_datetime_as_local_datetime' ) ) {
                        $local_dt = $ssa->utils->get_datetime_as_local_datetime( $start_string, $service_id );
                    } else {
                        $local_dt = new DateTimeImmutable( $start_string, $utc_tz );
                        $local_dt = $local_dt->setTimezone( $local_tz );
                    }
                    
                    // Only include slots for the requested date
                    if ( $local_dt->format( 'Y-m-d' ) !== $date ) {
                        continue;
                    }
                    
                    $end_local = $local_dt->add( new DateInterval( 'PT' . $duration_minutes . 'M' ) );
                    
                    $slots[] = [
                        'start_time' => $local_dt->format( 'H:i' ),
                        'end_time'   => $end_local->format( 'H:i' ),
                    ];
                } catch ( \Throwable $e ) {
                    aichat_log_debug( '[SSA Adapter] Error parsing slot: ' . $e->getMessage() );
                }
            }
            
            aichat_log_debug( '[SSA Adapter] Found ' . count( $slots ) . ' slots for date ' . $date );
            return $slots;
            
        } catch ( \Throwable $e ) {
            aichat_log_debug( '[SSA Adapter] get_available_slots error: ' . $e->getMessage() );
            return [];
        }
    }
    
    /**
     * Book an appointment in SSA
     */
    public function book( $data ) {
        if ( ! $this->is_available() || ! function_exists( 'ssa' ) ) {
            return new \WP_Error( 'ssa_not_available', __( 'Simply Schedule Appointments is not available', 'axiachat-ai' ) );
        }
        
        $ssa = call_user_func( 'ssa' );
        
        // Get service (appointment type) ID
        $service_id = 0;
        if ( ! empty( $data['service_id'] ) ) {
            $service_id = (int) $data['service_id'];
        } elseif ( ! empty( $data['service'] ) ) {
            $services = $this->get_services();
            foreach ( $services as $svc ) {
                if ( strcasecmp( $svc['name'], $data['service'] ) === 0 ) {
                    $service_id = (int) $svc['id'];
                    break;
                }
            }
        }
        
        // Fallback to settings default
        if ( $service_id <= 0 ) {
            $settings = AIChat_Appointments_Manager::get_settings();
            $service_id = (int) ( $settings['ssa_service_id'] ?? 0 );
        }
        
        // Fallback to first appointment type
        if ( $service_id <= 0 ) {
            $services = $this->get_services();
            if ( ! empty( $services ) ) {
                $service_id = (int) $services[0]['id'];
            }
        }
        
        if ( $service_id <= 0 ) {
            return new \WP_Error( 'no_appointment_type', __( 'No appointment type configured', 'axiachat-ai' ) );
        }
        
        // Validate required fields
        if ( empty( $data['customer_email'] ) || ! is_email( $data['customer_email'] ) ) {
            return new \WP_Error( 'invalid_email', __( 'Valid email address is required', 'axiachat-ai' ) );
        }
        
        if ( empty( $data['appointment_date'] ) || empty( $data['start_time'] ) ) {
            return new \WP_Error( 'missing_datetime', __( 'Appointment date and time are required', 'axiachat-ai' ) );
        }
        
        try {
            // Get local timezone for this appointment type
            $local_tz = wp_timezone();
            if ( isset( $ssa->utils ) && method_exists( $ssa->utils, 'get_datetimezone' ) ) {
                try {
                    $local_tz = $ssa->utils->get_datetimezone( $service_id );
                } catch ( \Throwable $e ) {
                    // Use WP timezone as fallback
                }
            }
            
            // Parse the datetime
            $datetime_string = $data['appointment_date'] . ' ' . $data['start_time'] . ':00';
            $local_dt = new DateTimeImmutable( $datetime_string, $local_tz );
            
            // Convert to UTC for SSA
            $utc_dt = $local_dt->setTimezone( new DateTimeZone( 'UTC' ) );
            $start_date_utc = $utc_dt->format( 'Y-m-d H:i:s' );
            
            // Check availability
            if ( class_exists( 'SSA_Appointment_Type_Object' ) && isset( $ssa->appointment_model ) ) {
                $appointment_type = \SSA_Appointment_Type_Object::instance( $service_id );
                
                if ( method_exists( $ssa->appointment_model, 'is_prospective_appointment_available' ) ) {
                    $is_available = $ssa->appointment_model->is_prospective_appointment_available( $appointment_type, $utc_dt );
                    if ( ! $is_available ) {
                        return new \WP_Error( 'slot_unavailable', __( 'The requested time slot is not available', 'axiachat-ai' ) );
                    }
                }
            }
            
            // Prepare customer information for SSA format
            $customer_information = [
                'Name'  => sanitize_text_field( $data['customer_name'] ?? '' ),
                'Email' => sanitize_email( $data['customer_email'] ),
            ];
            if ( ! empty( $data['customer_phone'] ) ) {
                $customer_information['Phone'] = sanitize_text_field( $data['customer_phone'] );
            }
            
            // Prepare appointment data
            $appointment_data = [
                'appointment_type_id'  => $service_id,
                'start_date'           => $start_date_utc,
                'status'               => 'booked',
                'customer_information' => $customer_information,
                'customer_timezone'    => $local_tz->getName(),
            ];
            
            // Create the appointment
            if ( ! isset( $ssa->appointment_model ) || ! method_exists( $ssa->appointment_model, 'insert' ) ) {
                return new \WP_Error( 'ssa_api_error', __( 'SSA appointment model not available', 'axiachat-ai' ) );
            }
            
            $result = $ssa->appointment_model->insert( $appointment_data );
            
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            
            if ( ! is_numeric( $result ) || $result <= 0 ) {
                $error_msg = is_string( $result ) ? $result : 'unknown_error';
                /* translators: %s: Error message from appointment creation failure */
                return new \WP_Error( 'booking_failed', sprintf( __( 'Failed to create appointment: %s', 'axiachat-ai' ), $error_msg ) );
            }
            
            $ssa_appointment_id = (int) $result;
            
            // Generate our tracking code
            $booking_code = 'APT-' . strtoupper( wp_generate_password( 8, false ) );
            
            // Get duration for end time
            $duration_minutes = 30;
            foreach ( $this->get_services() as $svc ) {
                if ( (int) $svc['id'] === $service_id ) {
                    $duration_minutes = (int) $svc['duration'];
                    break;
                }
            }
            $end_dt = $local_dt->add( new DateInterval( 'PT' . $duration_minutes . 'M' ) );
            
            // Store in our tracking table
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->insert(
                $this->tracking_table,
                [
                    'booking_code'     => $booking_code,
                    'customer_name'    => sanitize_text_field( $data['customer_name'] ?? '' ),
                    'customer_email'   => sanitize_email( $data['customer_email'] ),
                    'customer_phone'   => sanitize_text_field( $data['customer_phone'] ?? '' ),
                    'appointment_date' => $data['appointment_date'],
                    'start_time'       => $data['start_time'] . ':00',
                    'end_time'         => $end_dt->format( 'H:i:s' ),
                    'service'          => $this->get_service_name( $service_id ),
                    'staff'            => '',
                    'status'           => 'confirmed',
                    'notes'            => sanitize_textarea_field( $data['notes'] ?? '' ),
                    'source'           => 'ssa',
                    'external_id'      => $ssa_appointment_id,
                    'bot_slug'         => sanitize_key( $data['bot_slug'] ?? '' ),
                    'session_id'       => sanitize_text_field( $data['session_id'] ?? '' ),
                    'created_at'       => current_time( 'mysql' ),
                    'updated_at'       => current_time( 'mysql' ),
                ],
                [ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
            );
            
            aichat_log_debug( '[SSA Adapter] Appointment booked successfully', [
                'ssa_id'       => $ssa_appointment_id,
                'booking_code' => $booking_code,
                'date'         => $data['appointment_date'],
                'time'         => $data['start_time'],
            ] );
            
            return [
                'success'      => true,
                'booking_code' => $booking_code,
                'external_id'  => $ssa_appointment_id,
                'message'      => sprintf(
                    /* translators: %s: Booking confirmation code */
                    __( 'Appointment booked successfully. Confirmation code: %s', 'axiachat-ai' ),
                    $booking_code
                ),
            ];
            
        } catch ( \Throwable $e ) {
            aichat_log_debug( '[SSA Adapter] book error: ' . $e->getMessage() );
            return new \WP_Error( 'booking_error', $e->getMessage() );
        }
    }
    
    /**
     * Cancel an appointment
     */
    public function cancel( $booking_code ) {
        global $wpdb;
        
        // Get our tracking record
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $this->tracking_table is a trusted plugin table name.
        $appointment = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tracking_table} WHERE booking_code = %s",
            $booking_code
        ) );
        
        if ( ! $appointment ) {
            return new \WP_Error( 'not_found', __( 'Appointment not found', 'axiachat-ai' ) );
        }
        
        if ( $appointment->source !== 'ssa' ) {
            return new \WP_Error( 'wrong_source', __( 'This appointment was not booked through SSA', 'axiachat-ai' ) );
        }
        
        // Cancel in SSA if external_id exists
        if ( ! empty( $appointment->external_id ) && function_exists( 'ssa' ) ) {
            $ssa = call_user_func( 'ssa' );
            
            if ( isset( $ssa->appointment_model ) && method_exists( $ssa->appointment_model, 'update' ) ) {
                try {
                    $ssa->appointment_model->update( (int) $appointment->external_id, [
                        'status' => 'canceled',
                    ] );
                } catch ( \Throwable $e ) {
                    aichat_log_debug( '[SSA Adapter] Error canceling in SSA: ' . $e->getMessage() );
                    // Continue to update our tracking record anyway
                }
            }
        }
        
        // Update our tracking record
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update(
            $this->tracking_table,
            [
                'status'     => 'cancelled',
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $appointment->id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        aichat_log_debug( '[SSA Adapter] Appointment cancelled: ' . $booking_code );
        
        return true;
    }
    
    /**
     * Get appointment by booking code
     */
    public function get_by_code( $booking_code ) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $this->tracking_table is a trusted plugin table name.
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tracking_table} WHERE booking_code = %s AND source = 'ssa'",
            $booking_code
        ) );
    }
    
    /**
     * Get settings fields for admin
     */
    public function get_settings_fields() {
        return [
            'ssa_service_mode' => [
                'type'        => 'select',
                'label'       => __( 'Appointment Type Mode', 'axiachat-ai' ),
                'description' => __( 'Choose whether to use a fixed appointment type or let the AI ask customers.', 'axiachat-ai' ),
                'options'     => [
                    'default' => __( 'Use default appointment type', 'axiachat-ai' ),
                    'ask'     => __( 'Let the bot ask the customer', 'axiachat-ai' ),
                ],
                'default' => 'default',
            ],
            'ssa_service_id' => [
                'type'        => 'select',
                'label'       => __( 'Default Appointment Type', 'axiachat-ai' ),
                'description' => __( 'Select the default appointment type for bookings.', 'axiachat-ai' ),
                'options'     => $this->get_services_for_select(),
                'default'     => 0,
                'depends_on'  => [ 'ssa_service_mode' => 'default' ],
            ],
        ];
    }
    
    /**
     * Get services formatted for select dropdown
     */
    private function get_services_for_select() {
        $options = [ 0 => __( '— Select an appointment type —', 'axiachat-ai' ) ];
        
        foreach ( $this->get_services() as $service ) {
            $label = $service['name'];
            if ( $service['duration'] > 0 ) {
                $label .= sprintf( ' (%d min)', $service['duration'] );
            }
            $options[ $service['id'] ] = $label;
        }
        
        return $options;
    }
    
    /**
     * Get service name by ID
     */
    private function get_service_name( $service_id ) {
        foreach ( $this->get_services() as $service ) {
            if ( (int) $service['id'] === (int) $service_id ) {
                return $service['name'];
            }
        }
        return '';
    }
    
    /**
     * Get appointment by ID
     */
    public function get( $id ) {
        global $wpdb;
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $this->tracking_table is a trusted plugin table name.
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->tracking_table} WHERE id = %d AND source = 'ssa'",
            $id
        ) );
    }
    
    /**
     * Get appointments list with filters
     */
    public function get_list( $args = [] ) {
        global $wpdb;
        
        $where = [ "source = 'ssa'" ];
        $params = [];
        
        if ( ! empty( $args['status'] ) ) {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }
        
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'appointment_date >= %s';
            $params[] = $args['date_from'];
        }
        
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'appointment_date <= %s';
            $params[] = $args['date_to'];
        }
        
        $where_sql = implode( ' AND ', $where );
        $limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
        
        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Table/where are plugin-controlled; placeholder count is correct (%d for limit/offset + dynamic params).
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->tracking_table} WHERE {$where_sql} ORDER BY appointment_date DESC, start_time DESC LIMIT %d OFFSET %d",
                array_merge( $params, [ $limit, $offset ] )
            );
        } else {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Table/where are plugin-controlled; 2 placeholders (%d LIMIT, %d OFFSET) match 2 params.
            $sql = $wpdb->prepare(
                "SELECT * FROM {$this->tracking_table} WHERE {$where_sql} ORDER BY appointment_date DESC, start_time DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            );
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql already prepared above with $wpdb->prepare().
        return $wpdb->get_results( $sql );
    }
    
    /**
     * Update appointment status
     */
    public function update_status( $id, $status ) {
        global $wpdb;
        
        $appointment = $this->get( $id );
        if ( ! $appointment ) {
            return new \WP_Error( 'not_found', __( 'Appointment not found', 'axiachat-ai' ) );
        }
        
        // Update in SSA if external_id exists
        if ( ! empty( $appointment->external_id ) && function_exists( 'ssa' ) ) {
            $ssa = call_user_func( 'ssa' );
            
            if ( isset( $ssa->appointment_model ) && method_exists( $ssa->appointment_model, 'update' ) ) {
                try {
                    // Map our status to SSA status
                    $ssa_status = $status;
                    if ( $status === 'cancelled' ) {
                        $ssa_status = 'canceled'; // SSA uses American spelling
                    }
                    $ssa->appointment_model->update( (int) $appointment->external_id, [
                        'status' => $ssa_status,
                    ] );
                } catch ( \Throwable $e ) {
                    aichat_log_debug( '[SSA Adapter] Error updating status in SSA: ' . $e->getMessage() );
                }
            }
        }
        
        // Update our tracking record
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->tracking_table,
            [
                'status'     => $status,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
        
        return $result !== false;
    }
    
    /**
     * Update appointment data
     */
    public function update( $id, $data ) {
        global $wpdb;
        
        $appointment = $this->get( $id );
        if ( ! $appointment ) {
            return new \WP_Error( 'not_found', __( 'Appointment not found', 'axiachat-ai' ) );
        }
        
        $update_data = [];
        $format = [];
        
        $allowed = [ 'customer_name', 'customer_email', 'customer_phone', 'appointment_date', 'start_time', 'end_time', 'notes', 'status' ];
        
        foreach ( $allowed as $field ) {
            if ( isset( $data[ $field ] ) ) {
                $update_data[ $field ] = $data[ $field ];
                $format[] = '%s';
            }
        }
        
        if ( empty( $update_data ) ) {
            return true; // Nothing to update
        }
        
        $update_data['updated_at'] = current_time( 'mysql' );
        $format[] = '%s';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->tracking_table,
            $update_data,
            [ 'id' => $id ],
            $format,
            [ '%d' ]
        );
        
        return $result !== false;
    }
    
    /**
     * Validate adapter-specific settings
     */
    public function validate_settings( $settings ) {
        // SSA settings are optional, no strict validation needed
        return true;
    }
}
