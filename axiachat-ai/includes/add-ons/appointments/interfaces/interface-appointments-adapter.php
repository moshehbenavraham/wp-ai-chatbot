<?php
/**
 * Appointments Adapter Interface
 * 
 * Defines the contract for appointment system adapters (internal, Bookly, Amelia, etc.)
 * Each adapter handles its own logic for slots, booking, and cancellation.
 * 
 * @package AxiaChat_AI
 * @subpackage Appointments
 * @since 2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

interface AIChat_Appointments_Adapter_Interface {
    
    /**
     * Check if this adapter is available (plugin installed, etc.)
     * 
     * @return bool
     */
    public function is_available();
    
    /**
     * Get adapter display info
     * 
     * @return array {
     *     @type string $id          Unique identifier
     *     @type string $name        Display name
     *     @type string $description Short description
     *     @type string $icon        Dashicon class
     * }
     */
    public function get_info();
    
    /**
     * Get available services from this booking system
     * 
     * @return array Array of services with id, name, duration, price
     */
    public function get_services();
    
    /**
     * Get available staff/employees from this booking system
     * 
     * @param int $service_id Optional - filter staff by service
     * @return array Array of staff with id, name
     */
    public function get_staff( $service_id = 0 );
    
    /**
     * Get available time slots for a date
     * 
     * @param string $date      Date in Y-m-d format
     * @param array  $params    Additional parameters {
     *     @type int    $service_id  Service ID
     *     @type int    $staff_id    Staff ID (0 = any)
     *     @type string $timezone    Timezone string
     * }
     * @return array Available slots with start_time, end_time
     */
    public function get_available_slots( $date, $params = [] );
    
    /**
     * Book an appointment
     * 
     * @param array $data Booking data {
     *     @type string $customer_name   Customer name
     *     @type string $customer_email  Customer email
     *     @type string $customer_phone  Customer phone (optional)
     *     @type string $appointment_date Date Y-m-d
     *     @type string $start_time      Start time H:i
     *     @type string $end_time        End time H:i (optional, calculated from duration)
     *     @type int    $service_id      Service ID
     *     @type int    $staff_id        Staff ID (0 = any available)
     *     @type string $notes           Additional notes
     *     @type string $bot_slug        Bot that made the booking
     *     @type string $session_id      Chat session ID
     * }
     * @return array|WP_Error Result with booking_code on success
     */
    public function book( $data );
    
    /**
     * Cancel an appointment
     * 
     * @param string $booking_code The booking confirmation code
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function cancel( $booking_code );
    
    /**
     * Get appointment details by booking code
     * 
     * @param string $booking_code The booking confirmation code
     * @return object|null Appointment object or null if not found
     */
    public function get_by_code( $booking_code );
    
    /**
     * Get appointment details by ID
     * 
     * @param int $id Appointment ID
     * @return object|null Appointment object or null if not found
     */
    public function get( $id );
    
    /**
     * Get appointments list with filters
     * 
     * @param array $args Filter arguments
     * @return array Appointments list
     */
    public function get_list( $args = [] );
    
    /**
     * Update appointment status
     * 
     * @param int    $id     Appointment ID
     * @param string $status New status
     * @return bool|WP_Error
     */
    public function update_status( $id, $status );
    
    /**
     * Update appointment data
     * 
     * @param int   $id   Appointment ID
     * @param array $data Data to update
     * @return bool|WP_Error
     */
    public function update( $id, $data );
    
    /**
     * Get configuration fields for admin settings
     * Returns the fields specific to this adapter
     * 
     * @return array Configuration fields definition
     */
    public function get_settings_fields();
    
    /**
     * Validate adapter-specific settings
     * 
     * @param array $settings Settings to validate
     * @return true|WP_Error
     */
    public function validate_settings( $settings );
}
