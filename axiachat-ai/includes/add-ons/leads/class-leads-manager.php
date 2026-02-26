<?php
/**
 * Leads Manager - Core class for lead handling
 * 
 * Manages saving leads to multiple destinations based on configuration.
 * 
 * @package AIChat
 * @subpackage Leads
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_Leads_Manager {
    
    /**
     * Registered adapters
     */
    private static $adapters = [];
    
    /**
     * Register an adapter
     */
    public static function register_adapter( $id, $adapter ) {
        self::$adapters[ $id ] = $adapter;
    }
    
    /**
     * Get registered adapters
     */
    public static function get_adapters() {
        return self::$adapters;
    }
    
    /**
     * Get available destinations based on active plugins
     */
    public static function get_available_destinations() {
        $destinations = [
            'internal' => [
                'id'          => 'internal',
                'name'        => __( 'Internal Database', 'axiachat-ai' ),
                'description' => __( 'Save leads to the plugin\'s internal table.', 'axiachat-ai' ),
                'available'   => true,
                'icon'        => 'dashicons-database',
            ],
            'google_sheets' => [
                'id'          => 'google_sheets',
                'name'        => 'Google Sheets',
                'description' => __( 'Save leads to a Google Spreadsheet.', 'axiachat-ai' ),
                'available'   => true,
                'icon'        => 'dashicons-media-spreadsheet',
            ],
            'cpt' => [
                'id'          => 'cpt',
                'name'        => __( 'WordPress (Custom Post Type)', 'axiachat-ai' ),
                'description' => __( 'Save leads as a custom post type for native WP integration.', 'axiachat-ai' ),
                'available'   => true,
                'icon'        => 'dashicons-wordpress',
            ],
            'cf7' => [
                'id'          => 'cf7',
                'name'        => 'Contact Form 7',
                'description' => __( 'Submit leads to a Contact Form 7 form.', 'axiachat-ai' ),
                'available'   => class_exists( 'WPCF7' ),
                'icon'        => 'dashicons-email-alt',
            ],
            'wpforms' => [
                'id'          => 'wpforms',
                'name'        => 'WPForms',
                'description' => __( 'Submit leads to a WPForms form.', 'axiachat-ai' ),
                'available'   => function_exists( 'wpforms' ),
                'icon'        => 'dashicons-feedback',
            ],
        ];
        
        return apply_filters( 'aichat_leads_destinations', $destinations );
    }
    
    /**
     * Get settings
     */
    public static function get_settings() {
        $defaults = [
            'enabled'             => true,
            'destination'         => 'internal', // Single destination
            'store_ip'            => false,
            'notify_email'        => '',
            'notify_enabled'      => false,
            'retention_days'      => 0, // 0 = forever
            'cf7_form_id'         => 0,
            'wpforms_form_id'     => 0,
        ];
        
        $settings = get_option( 'aichat_leads_settings', [] );
        return wp_parse_args( $settings, $defaults );
    }
    
    /**
     * Update settings
     */
    public static function update_settings( $settings ) {
        return update_option( 'aichat_leads_settings', $settings );
    }
    
    /**
     * Save a lead
     * 
     * @param array $data Lead data from the tool call (fields depend on destination)
     * @param array $context Execution context (session_id, bot_slug, etc.)
     * @return array Result with ok status and lead_id or error
     */
    public static function save( $data, $context = [] ) {
        $settings = self::get_settings();
        $destination = $settings['destination'] ?? 'internal';
        
        // PRO integrations check - Standard+ plan required for Google Sheets, CPT, CF7, WPForms
        $pro_destinations = [ 'google_sheets', 'cpt', 'cf7', 'wpforms' ];
        if ( in_array( $destination, $pro_destinations, true ) && ! self::has_integrations_license() ) {
            // Force fallback to internal for users without integrations license
            $destination = 'internal';
            if ( function_exists( 'aichat_log_debug' ) ) {
                aichat_log_debug( '[Leads] Integrations destination blocked (Standard+ required), falling back to internal', [
                    'requested' => $settings['destination'],
                ] );
            }
        }
        
        // Check usage limits (free plan)
        $limit_check = self::check_usage_limit();
        if ( is_wp_error( $limit_check ) ) {
            return [
                'ok'      => false,
                'error'   => 'limit_reached',
                'message' => $limit_check->get_error_message(),
            ];
        }
        
        // For internal/CPT, validate we have at least one identifier
        if ( in_array( $destination, [ 'internal', 'cpt' ], true ) ) {
            $has_identifier = ! empty( $data['nombre'] ) || ! empty( $data['email'] ) || ! empty( $data['telefono'] );
            if ( ! $has_identifier ) {
                return [
                    'ok'      => false,
                    'error'   => 'no_identifier',
                    'message' => __( 'At least one identifier (name, email, or phone) is required to save a lead.', 'axiachat-ai' ),
                ];
            }
        }
        
        // For CF7/WPForms, just need some data
        if ( in_array( $destination, [ 'cf7', 'wpforms' ], true ) && empty( $data ) ) {
            return [
                'ok'      => false,
                'error'   => 'no_data',
                'message' => __( 'No data provided to save.', 'axiachat-ai' ),
            ];
        }
        
        // Build lead data based on destination
        $lead = self::build_lead_data( $data, $destination, $settings, $context );
        
        // Debug: log built lead data
        if ( function_exists( 'aichat_log_debug' ) ) {
            aichat_log_debug( '[Leads] Built lead data', [
                'destination'  => $destination,
                'lead_keys'    => array_keys( $lead ),
                'has_fields'   => isset( $lead['fields'] ),
                'fields_count' => isset( $lead['fields'] ) ? count( $lead['fields'] ) : 0,
                'input_data'   => $data,
            ] );
        }
        
        // Save to the selected destination
        $result = [ 'ok' => false, 'error' => 'no_adapter' ];
        
        if ( isset( self::$adapters[ $destination ] ) ) {
            $adapter = self::$adapters[ $destination ];
            $result = $adapter->save( $lead, $settings );
            
            // Debug: log adapter result
            if ( function_exists( 'aichat_log_debug' ) ) {
                aichat_log_debug( '[Leads] Adapter result', [
                    'destination' => $destination,
                    'result'      => $result,
                ] );
            }
        } else {
            if ( function_exists( 'aichat_log_debug' ) ) {
                aichat_log_debug( '[Leads] No adapter found', [
                    'destination'       => $destination,
                    'available_adapters' => array_keys( self::$adapters ),
                ] );
            }
        }
        
        $success = ! empty( $result['ok'] );
        $lead_id = $result['lead_id'] ?? null;
        
        // Track external leads count for CF7/WPForms (for usage limits)
        if ( $success && in_array( $destination, [ 'cf7', 'wpforms' ], true ) ) {
            self::increment_external_count();
        }
        
        // Send notification email if enabled
        if ( $success && $settings['notify_enabled'] ) {
            $notify_email = ! empty( $settings['notify_email'] ) ? $settings['notify_email'] : get_option( 'admin_email' );
            if ( $notify_email ) {
                self::send_notification( $lead, $settings );
            }
        }
        
        // Send webhook if configured (independent of destination)
        if ( $success ) {
            self::send_webhook( $lead, $lead_id, $context );
        }
        
        // Log the capture
        if ( function_exists( 'aichat_log_debug' ) ) {
            aichat_log_debug( '[Leads] Lead saved', [
                'destination' => $destination,
                'success'     => $success,
                'data_keys'   => array_keys( $data ),
            ] );
        }
        
        if ( $success ) {
            return [
                'ok'      => true,
                'lead_id' => $lead_id,
                'message' => __( 'Contact information saved successfully. We will get in touch soon.', 'axiachat-ai' ),
            ];
        }
        
        return [
            'ok'      => false,
            'error'   => 'save_failed',
            'message' => __( 'There was a problem saving the information. Please try again.', 'axiachat-ai' ),
        ];
    }
    
    /**
     * Get leads from internal storage
     */
    public static function get_leads( $args = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_leads';
        
        $defaults = [
            'per_page'   => 20,
            'page'       => 1,
            'orderby'    => 'created_at',
            'order'      => 'DESC',
            'estado'     => '',
            'bot_slug'   => '',
            'search'     => '',
            'date_from'  => '',
            'date_to'    => '',
        ];
        
        $args = wp_parse_args( $args, $defaults );
        
        $where = [ '1=1' ];
        $params = [];
        
        if ( ! empty( $args['estado'] ) ) {
            $where[] = 'estado = %s';
            $params[] = $args['estado'];
        }
        
        if ( ! empty( $args['bot_slug'] ) ) {
            $where[] = 'bot_slug = %s';
            $params[] = $args['bot_slug'];
        }
        
        if ( ! empty( $args['search'] ) ) {
            $search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '(nombre LIKE %s OR email LIKE %s OR telefono LIKE %s OR empresa LIKE %s)';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        if ( ! empty( $args['date_from'] ) ) {
            $where[] = 'DATE(created_at) >= %s';
            $params[] = $args['date_from'];
        }
        
        if ( ! empty( $args['date_to'] ) ) {
            $where[] = 'DATE(created_at) <= %s';
            $params[] = $args['date_to'];
        }
        
        $where_sql = implode( ' AND ', $where );
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $count_sql = $wpdb->prepare( $count_sql, $params );
        }
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total = (int) $wpdb->get_var( $count_sql );
        
        // Get leads
        $orderby = in_array( $args['orderby'], [ 'id', 'nombre', 'email', 'estado', 'created_at' ], true ) 
            ? $args['orderby'] 
            : 'created_at';
        $order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
        $offset = ( $args['page'] - 1 ) * $args['per_page'];
        
        $sql = "SELECT * FROM $table WHERE $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d";
        $params[] = $args['per_page'];
        $params[] = $offset;
        
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $leads = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
        
        return [
            'leads'      => $leads,
            'total'      => $total,
            'page'       => $args['page'],
            'per_page'   => $args['per_page'],
            'total_pages' => ceil( $total / $args['per_page'] ),
        ];
    }
    
    /**
     * Get a single lead by ID
     */
    public static function get_lead( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_leads';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
            $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ),
            ARRAY_A
        );
    }
    
    /**
     * Update lead status
     */
    public static function update_status( $id, $status ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_leads';
        
        $valid_statuses = [ 'nuevo', 'contactado', 'convertido', 'descartado' ];
        if ( ! in_array( $status, $valid_statuses, true ) ) {
            return false;
        }
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->update(
            $table,
            [
                'estado'     => $status,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s' ],
            [ '%d' ]
        );
    }
    
    /**
     * Delete a lead
     */
    public static function delete( $id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_leads';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );
    }
    
    /**
     * Bulk delete leads
     */
    public static function bulk_delete( $ids ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_leads';
        
        $ids = array_map( 'intval', (array) $ids );
        $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->query(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $table and $placeholders are trusted plugin values.
            $wpdb->prepare( "DELETE FROM $table WHERE id IN ($placeholders)", $ids )
        );
    }
    
    /**
     * Export leads to CSV
     */
    public static function export_csv( $args = [] ) {
        $args['per_page'] = 9999;
        $result = self::get_leads( $args );
        
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Using php://temp memory stream for CSV generation, not actual filesystem.
        $output = fopen( 'php://temp', 'r+' );
        
        // Headers
        fputcsv( $output, [
            'ID',
            'Nombre',
            'Email',
            'Teléfono',
            'Empresa',
            'Interés',
            'Notas',
            'Bot',
            'Estado',
            'Fecha',
        ] );
        
        // Rows
        foreach ( $result['leads'] as $lead ) {
            fputcsv( $output, [
                $lead['id'],
                $lead['nombre'],
                $lead['email'],
                $lead['telefono'],
                $lead['empresa'],
                $lead['interes'],
                $lead['notas'],
                $lead['bot_slug'],
                $lead['estado'],
                $lead['created_at'],
            ] );
        }
        
        rewind( $output );
        $csv = stream_get_contents( $output );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing php://temp memory stream.
        fclose( $output );
        
        return $csv;
    }
    
    /**
     * Get statistics
     */
    public static function get_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_leads';
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $today = (int) $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
            $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE DATE(created_at) = %s", current_time( 'Y-m-d' ) )
        );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $this_month = (int) $wpdb->get_var(
            $wpdb->prepare( 
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
                "SELECT COUNT(*) FROM $table WHERE created_at >= %s", 
                current_time( 'Y-m-01 00:00:00' ) 
            )
        );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $by_status = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
            "SELECT estado, COUNT(*) as count FROM $table GROUP BY estado",
            ARRAY_A
        );
        
        $status_counts = [];
        foreach ( $by_status as $row ) {
            $status_counts[ $row['estado'] ] = (int) $row['count'];
        }
        
        return [
            'total'      => $total,
            'today'      => $today,
            'this_month' => $this_month,
            'by_status'  => $status_counts,
        ];
    }
    
    /**
     * Get total leads count across all destinations
     * Used for free plan limits
     * 
     * @return int Total number of leads saved
     */
    public static function get_total_leads_count() {
        $count = 0;
        
        // Count from internal table
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_leads';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $internal_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        $count += $internal_count;
        
        // Count from CPT
        $cpt_count = wp_count_posts( 'aichat_lead' );
        if ( $cpt_count && isset( $cpt_count->publish ) ) {
            $count += (int) $cpt_count->publish;
        }
        
        // For CF7/WPForms, we track via our own counter since we can't easily count their entries
        $external_count = (int) get_option( 'aichat_leads_external_count', 0 );
        $count += $external_count;
        
        return $count;
    }
    
    /**
     * Increment external leads counter (for CF7/WPForms)
     */
    public static function increment_external_count() {
        $current = (int) get_option( 'aichat_leads_external_count', 0 );
        update_option( 'aichat_leads_external_count', $current + 1 );
    }
    
    /**
     * Get the free plan limit
     * 
     * @return int Lead limit for free plan (50 for free, unlimited for paid)
     */
    public static function get_free_limit() {
        // Use centralized limit function if available
        if ( function_exists( 'aichat_max_leads_allowed' ) ) {
            $limit = aichat_max_leads_allowed();
            // Convert PHP_INT_MAX to a large number for display
            return $limit >= PHP_INT_MAX ? 999999 : $limit;
        }
        return apply_filters( 'aichat_leads_free_limit', 50 );
    }
    
    /**
     * Check if user has Pro license (unlimited leads)
     * Uses Feature ID 26990: Unlimited Leads
     * 
     * @return bool True if Pro license is active
     */
    public static function has_pro_license() {
        // Use centralized feature check (Feature ID 26990: Unlimited Leads)
        if ( function_exists( 'aichat_has_feature' ) && defined( 'AICHAT_FEATURE_UNLIMITED_LEADS' ) ) {
            return aichat_has_feature( AICHAT_FEATURE_UNLIMITED_LEADS );
        }
        
        // Fallback: Use central Freemius check
        if ( function_exists( 'aichat_is_pro' ) && aichat_is_pro() ) {
            return true;
        }
        
        // Fallback: check specific leads license option
        $license = get_option( 'aichat_leads_pro_license', '' );
        if ( ! empty( $license ) ) {
            return true;
        }
        
        return apply_filters( 'aichat_leads_has_pro', false );
    }
    
    /**
     * Check if user has Integrations license (Google Sheets, CF7, WPForms)
     * Uses Feature ID 26993: Leads Integrations (Standard+ plan)
     * 
     * @return bool True if Integrations license is active
     */
    public static function has_integrations_license() {
        // Use centralized feature check (Feature ID 26993: Leads Integrations)
        if ( function_exists( 'aichat_can_use_leads_integrations' ) ) {
            return aichat_can_use_leads_integrations();
        }
        
        // Fallback: Use central Freemius check for plan level
        if ( function_exists( 'aichat_is_plan' ) ) {
            return aichat_is_plan( 'standard' ); // Standard+ required
        }
        
        // Fallback: check if has_pro_license (for legacy behavior)
        return self::has_pro_license();
    }
    
    /**
     * Check usage limit before saving a lead
     * 
     * @return true|WP_Error True if can save, WP_Error if limit reached
     */
    public static function check_usage_limit() {
        // Pro users have unlimited leads
        if ( self::has_pro_license() ) {
            return true;
        }
        
        $count = self::get_total_leads_count();
        $limit = self::get_free_limit();
        
        if ( $count >= $limit ) {
            return new \WP_Error(
                'limit_reached',
                sprintf(
                    /* translators: %d: lead limit number */
                    __( 'You have reached the free plan limit of %d leads. Please upgrade to Pro for unlimited lead capture.', 'axiachat-ai' ),
                    $limit
                )
            );
        }
        
        return true;
    }
    
    /**
     * Get usage info for display
     * 
     * @return array Usage information
     */
    public static function get_usage_info() {
        $count = self::get_total_leads_count();
        $limit = self::get_free_limit();
        $is_pro = self::has_pro_license();
        
        return [
            'count'     => $count,
            'limit'     => $limit,
            'remaining' => max( 0, $limit - $count ),
            'is_pro'    => $is_pro,
            'percent'   => $limit > 0 ? min( 100, round( ( $count / $limit ) * 100 ) ) : 0,
        ];
    }
    
    /**
     * Build lead data based on destination
     * 
     * For internal/CPT: use default field structure
     * For CF7/WPForms: pass through dynamic form fields
     * 
     * @param array  $data        Raw data from tool call
     * @param string $destination Selected destination
     * @param array  $settings    Leads settings
     * @param array  $context     Execution context
     * @return array Formatted lead data
     */
    private static function build_lead_data( $data, $destination, $settings, $context ) {
        $lead = [
            'bot_slug'   => $context['bot_slug'] ?? '',
            'session_id' => $context['session_id'] ?? '',
            'ip_hash'    => ! empty( $settings['store_ip'] ) ? self::hash_ip() : null,
            'estado'     => 'nuevo',
            'created_at' => current_time( 'mysql' ),
        ];
        
        // For internal/CPT destinations: use standard fields
        if ( in_array( $destination, [ 'internal', 'cpt' ], true ) ) {
            $lead['nombre']   = sanitize_text_field( $data['nombre'] ?? '' );
            $lead['email']    = sanitize_email( $data['email'] ?? '' );
            $lead['telefono'] = sanitize_text_field( $data['telefono'] ?? '' );
            $lead['empresa']  = sanitize_text_field( $data['empresa'] ?? '' );
            $lead['interes']  = sanitize_text_field( $data['interes'] ?? '' );
            $lead['notas']    = sanitize_textarea_field( $data['notas'] ?? '' );
        }
        // For CF7/WPForms: pass through all fields with sanitization
        else {
            // Store all dynamic fields in a metadata array
            $fields = [];
            foreach ( $data as $key => $value ) {
                if ( is_string( $value ) ) {
                    $fields[ sanitize_key( $key ) ] = sanitize_text_field( $value );
                } elseif ( is_array( $value ) ) {
                    $fields[ sanitize_key( $key ) ] = array_map( 'sanitize_text_field', $value );
                }
            }
            $lead['fields'] = $fields;
            
            // Try to extract standard identifiers for notifications
            $lead['nombre']   = sanitize_text_field( $data['name'] ?? $data['nombre'] ?? $data['your-name'] ?? '' );
            $lead['email']    = sanitize_email( $data['email'] ?? $data['your-email'] ?? '' );
            $lead['telefono'] = sanitize_text_field( $data['phone'] ?? $data['telefono'] ?? $data['tel'] ?? '' );
            $lead['empresa']  = sanitize_text_field( $data['company'] ?? $data['empresa'] ?? '' );
            $lead['interes']  = '';
            $lead['notas']    = '';
        }
        
        return $lead;
    }
    
    /**
     * Hash IP address for GDPR compliance
     */
    private static function hash_ip() {
        $ip = '';
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }
        
        return $ip ? hash( 'sha256', $ip . wp_salt() ) : null;
    }
    
    /**
     * Send notification email
     */
    private static function send_notification( $lead, $settings ) {
        $to = $settings['notify_email'];
        $subject = sprintf(
            /* translators: %s: site name */
            __( '[%s] New Lead Captured', 'axiachat-ai' ),
            get_bloginfo( 'name' )
        );
        
        $message = sprintf(
            /* translators: 1: Name, 2: Email, 3: Phone, 4: Company, 5: Interest, 6: Notes, 7: Bot slug, 8: Date */
            __( "A new lead has been captured:\n\nName: %1\$s\nEmail: %2\$s\nPhone: %3\$s\nCompany: %4\$s\nInterest: %5\$s\nNotes: %6\$s\n\nBot: %7\$s\nDate: %8\$s", 'axiachat-ai' ),
            $lead['nombre'],
            $lead['email'] ?: '-',
            $lead['telefono'] ?: '-',
            $lead['empresa'] ?: '-',
            $lead['interes'] ?: '-',
            $lead['notas'] ?: '-',
            $lead['bot_slug'] ?: 'Unknown',
            current_time( 'mysql' )
        );
        
        wp_mail( $to, $subject, $message );
    }
    
    /**
     * Cleanup old leads based on retention policy
     */
    public static function cleanup_old_leads() {
        $settings = self::get_settings();
        
        if ( empty( $settings['retention_days'] ) || $settings['retention_days'] <= 0 ) {
            return 0;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_leads';
        $cutoff = wp_date( 'Y-m-d H:i:s', strtotime( "-{$settings['retention_days']} days" ) );
        
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
        return $wpdb->query(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
            $wpdb->prepare( "DELETE FROM $table WHERE created_at < %s", $cutoff )
        );
    }
    
    /**
     * Send webhook notification
     * 
     * @param array $lead Lead data
     * @param int|null $lead_id Lead ID (if saved to internal DB)
     * @param array $context Execution context
     */
    private static function send_webhook( $lead, $lead_id = null, $context = [] ) {
        // Webhooks are PRO-only feature
        if ( ! self::has_pro_license() ) {
            return;
        }
        
        $webhook_url = get_option( 'aichat_leads_webhook_url', '' );
        
        if ( empty( $webhook_url ) ) {
            return;
        }
        
        // Build webhook payload
        $payload = [
            'name'    => $lead['nombre'] ?? '',
            'email'   => $lead['email'] ?? '',
            'phone'   => $lead['telefono'] ?? '',
            'company' => $lead['empresa'] ?? '',
            'interest'=> $lead['interes'] ?? '',
            'notes'   => $lead['notas'] ?? '',
            'bot'     => $lead['bot_slug'] ?? '',
            'date'    => current_time( 'c' ), // ISO 8601 format
            'source'  => home_url(),
            'lead_id' => $lead_id,
        ];
        
        // Allow filtering the payload
        $payload = apply_filters( 'aichat_leads_webhook_payload', $payload, $lead, $context );
        
        // Send POST request
        $response = wp_remote_post( $webhook_url, [
            'timeout'   => 15,
            'headers'   => [
                'Content-Type' => 'application/json',
            ],
            'body'      => wp_json_encode( $payload ),
            'sslverify' => true,
        ] );
        
        // Log result
        if ( function_exists( 'aichat_log_debug' ) ) {
            if ( is_wp_error( $response ) ) {
                aichat_log_debug( '[Leads] Webhook error', [
                    'url'   => $webhook_url,
                    'error' => $response->get_error_message(),
                ] );
            } else {
                aichat_log_debug( '[Leads] Webhook sent', [
                    'url'    => $webhook_url,
                    'status' => wp_remote_retrieve_response_code( $response ),
                ] );
            }
        }
    }
}

// Register cleanup cron
add_action( 'aichat_daily_cleanup', [ 'AIChat_Leads_Manager', 'cleanup_old_leads' ] );
