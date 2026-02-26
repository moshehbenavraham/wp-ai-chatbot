<?php
/**
 * Google Calendar API Client
 * 
 * Wrapper for Google Calendar API operations.
 * Handles events CRUD and calendar listing.
 * 
 * @package AxiaChat_AI
 * @subpackage Appointments
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_GCal_Client {
    
    /** @var string API Base URL */
    const API_BASE = 'https://www.googleapis.com/calendar/v3';
    
    /** @var AIChat_GCal_OAuth OAuth handler */
    private $oauth;
    
    /** @var string Calendar ID */
    private $calendar_id;
    
    /**
     * Constructor
     * 
     * @param AIChat_GCal_OAuth $oauth OAuth handler instance
     */
    public function __construct( AIChat_GCal_OAuth $oauth ) {
        $this->oauth = $oauth;
        $this->calendar_id = $oauth->get_calendar_id();
    }
    
    /**
     * Make an authenticated API request
     * 
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array $body Request body
     * @return array|WP_Error Response data or error
     */
    private function request( $endpoint, $method = 'GET', $body = null ) {
        $access_token = $this->oauth->get_access_token();
        
        if ( is_wp_error( $access_token ) ) {
            return $access_token;
        }
        
        $url = self::API_BASE . $endpoint;
        
        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ];
        
        if ( $body !== null && in_array( $method, [ 'POST', 'PUT', 'PATCH' ] ) ) {
            $args['body'] = wp_json_encode( $body );
        }
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            aichat_appointments_log( 'Google Calendar API error', [
                'endpoint' => $endpoint,
                'error'    => $response->get_error_message(),
            ] );
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $code >= 400 ) {
            $error_message = $data['error']['message'] ?? __( 'Unknown API error', 'axiachat-ai' );
            aichat_appointments_log( 'Google Calendar API error response', [
                'endpoint' => $endpoint,
                'code'     => $code,
                'error'    => $error_message,
            ] );
            return new WP_Error( 'gcal_api_error', $error_message );
        }
        
        return $data;
    }
    
    /**
     * List user's calendars
     * 
     * @return array|WP_Error List of calendars
     */
    public function list_calendars() {
        $result = $this->request( '/users/me/calendarList' );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $calendars = [];
        foreach ( $result['items'] ?? [] as $calendar ) {
            $calendars[] = [
                'id'      => $calendar['id'],
                'summary' => $calendar['summary'],
                'primary' => $calendar['primary'] ?? false,
            ];
        }
        
        return $calendars;
    }
    
    /**
     * Get events for a specific date range
     * 
     * @param string $date Date in Y-m-d format
     * @param string $timezone Timezone string
     * @return array|WP_Error Events for the date
     */
    public function get_events_for_date( $date, $timezone = 'UTC' ) {
        $tz = new DateTimeZone( $timezone );
        
        // Start of day
        $start = new DateTime( $date . ' 00:00:00', $tz );
        $time_min = $start->format( 'c' );
        
        // End of day
        $end = new DateTime( $date . ' 23:59:59', $tz );
        $time_max = $end->format( 'c' );
        
        $endpoint = '/calendars/' . urlencode( $this->calendar_id ) . '/events?' . http_build_query( [
            'timeMin'      => $time_min,
            'timeMax'      => $time_max,
            'singleEvents' => 'true',
            'orderBy'      => 'startTime',
        ] );
        
        $result = $this->request( $endpoint );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $events = [];
        foreach ( $result['items'] ?? [] as $event ) {
            // Skip all-day events (they have 'date' instead of 'dateTime')
            if ( isset( $event['start']['date'] ) ) {
                continue;
            }
            
            $event_start = new DateTime( $event['start']['dateTime'] );
            $event_end = new DateTime( $event['end']['dateTime'] );
            
            $events[] = [
                'id'         => $event['id'],
                'summary'    => $event['summary'] ?? __( '(No title)', 'axiachat-ai' ),
                'start_time' => $event_start->format( 'H:i' ),
                'end_time'   => $event_end->format( 'H:i' ),
                'status'     => $event['status'] ?? 'confirmed',
            ];
        }
        
        return $events;
    }
    
    /**
     * Create a calendar event for an appointment
     * 
     * @param array $appointment Appointment data
     * @return array|WP_Error Created event data or error
     */
    public function create_event( $appointment ) {
        $settings = AIChat_Appointments_Manager::get_settings();
        $timezone = $settings['timezone'] ?? 'UTC';
        
        // Build datetime strings
        $start_datetime = $appointment['appointment_date'] . 'T' . $appointment['start_time'] . ':00';
        $end_datetime = $appointment['appointment_date'] . 'T' . $appointment['end_time'] . ':00';
        
        // Build event description
        $description = sprintf(
            /* translators: 1: Customer name, 2: Email, 3: Phone, 4: Booking code, 5: Notes */
            __( "Appointment booked via AI Chat\n\nCustomer: %1\$s\nEmail: %2\$s\nPhone: %3\$s\nBooking Code: %4\$s\n\nNotes: %5\$s", 'axiachat-ai' ),
            $appointment['customer_name'],
            $appointment['customer_email'],
            $appointment['customer_phone'] ?? '-',
            $appointment['booking_code'],
            $appointment['notes'] ?? '-'
        );
        
        // Build event title
        $summary = sprintf(
            /* translators: 1: Customer name, 2: Service name or 'Appointment' */
            __( '📅 %1$s - %2$s', 'axiachat-ai' ),
            $appointment['customer_name'],
            $appointment['service'] ?? __( 'Appointment', 'axiachat-ai' )
        );
        
        $event_data = [
            'summary'     => $summary,
            'description' => $description,
            'start' => [
                'dateTime' => $start_datetime,
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $end_datetime,
                'timeZone' => $timezone,
            ],
            'attendees' => [
                [
                    'email'        => $appointment['customer_email'],
                    'displayName'  => $appointment['customer_name'],
                    'responseStatus' => 'accepted',
                ],
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    [ 'method' => 'email', 'minutes' => 60 ],
                    [ 'method' => 'popup', 'minutes' => 30 ],
                ],
            ],
            // Store booking code in extended properties for easy lookup
            'extendedProperties' => [
                'private' => [
                    'aichat_booking_code' => $appointment['booking_code'],
                ],
            ],
        ];
        
        $endpoint = '/calendars/' . urlencode( $this->calendar_id ) . '/events?sendUpdates=all';
        
        $result = $this->request( $endpoint, 'POST', $event_data );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        aichat_appointments_log( 'Google Calendar event created', [
            'event_id'     => $result['id'],
            'booking_code' => $appointment['booking_code'],
        ] );
        
        return [
            'event_id'   => $result['id'],
            'html_link'  => $result['htmlLink'] ?? '',
        ];
    }
    
    /**
     * Update an existing calendar event
     * 
     * @param string $event_id Google event ID
     * @param array $updates Data to update
     * @return array|WP_Error Updated event or error
     */
    public function update_event( $event_id, $updates ) {
        $settings = AIChat_Appointments_Manager::get_settings();
        $timezone = $settings['timezone'] ?? 'UTC';
        
        $event_data = [];
        
        if ( isset( $updates['appointment_date'] ) && isset( $updates['start_time'] ) ) {
            $event_data['start'] = [
                'dateTime' => $updates['appointment_date'] . 'T' . $updates['start_time'] . ':00',
                'timeZone' => $timezone,
            ];
        }
        
        if ( isset( $updates['appointment_date'] ) && isset( $updates['end_time'] ) ) {
            $event_data['end'] = [
                'dateTime' => $updates['appointment_date'] . 'T' . $updates['end_time'] . ':00',
                'timeZone' => $timezone,
            ];
        }
        
        if ( isset( $updates['customer_name'] ) || isset( $updates['service'] ) ) {
            $event_data['summary'] = sprintf(
                /* translators: 1: Customer name, 2: Service name or 'Appointment' */
                __( '📅 %1$s - %2$s', 'axiachat-ai' ),
                $updates['customer_name'] ?? 'Customer',
                $updates['service'] ?? __( 'Appointment', 'axiachat-ai' )
            );
        }
        
        if ( empty( $event_data ) ) {
            return new WP_Error( 'no_updates', __( 'No data to update', 'axiachat-ai' ) );
        }
        
        $endpoint = '/calendars/' . urlencode( $this->calendar_id ) . '/events/' . urlencode( $event_id ) . '?sendUpdates=all';
        
        return $this->request( $endpoint, 'PATCH', $event_data );
    }
    
    /**
     * Delete/cancel a calendar event
     * 
     * @param string $event_id Google event ID
     * @return bool|WP_Error True on success or error
     */
    public function delete_event( $event_id ) {
        $endpoint = '/calendars/' . urlencode( $this->calendar_id ) . '/events/' . urlencode( $event_id ) . '?sendUpdates=all';
        
        $result = $this->request( $endpoint, 'DELETE' );
        
        // DELETE returns empty on success
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        aichat_appointments_log( 'Google Calendar event deleted', [
            'event_id' => $event_id,
        ] );
        
        return true;
    }
    
    /**
     * Find event by booking code
     * 
     * @param string $booking_code
     * @return string|null Event ID or null
     */
    public function find_event_by_booking_code( $booking_code ) {
        // Search using private extended properties
        $endpoint = '/calendars/' . urlencode( $this->calendar_id ) . '/events?' . http_build_query( [
            'privateExtendedProperty' => 'aichat_booking_code=' . $booking_code,
            'maxResults' => 1,
        ] );
        
        $result = $this->request( $endpoint );
        
        if ( is_wp_error( $result ) || empty( $result['items'] ) ) {
            return null;
        }
        
        return $result['items'][0]['id'];
    }
    
    /**
     * Check if a time slot conflicts with existing events
     * 
     * @param string $date Date in Y-m-d format
     * @param string $start_time Start time in H:i format
     * @param string $end_time End time in H:i format
     * @param string $timezone Timezone
     * @return bool True if there's a conflict
     */
    public function has_conflict( $date, $start_time, $end_time, $timezone = 'UTC' ) {
        $events = $this->get_events_for_date( $date, $timezone );
        
        if ( is_wp_error( $events ) || empty( $events ) ) {
            return false;
        }
        
        $slot_start = strtotime( $start_time );
        $slot_end = strtotime( $end_time );
        
        foreach ( $events as $event ) {
            $event_start = strtotime( $event['start_time'] );
            $event_end = strtotime( $event['end_time'] );
            
            // Check for overlap
            if ( $slot_start < $event_end && $slot_end > $event_start ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get busy times for a date (times that are blocked by events)
     * 
     * @param string $date Date in Y-m-d format
     * @param string $timezone Timezone
     * @return array Array of busy time ranges
     */
    public function get_busy_times( $date, $timezone = 'UTC' ) {
        $events = $this->get_events_for_date( $date, $timezone );
        
        if ( is_wp_error( $events ) ) {
            return [];
        }
        
        $busy = [];
        foreach ( $events as $event ) {
            if ( $event['status'] !== 'cancelled' ) {
                $busy[] = [
                    'start' => $event['start_time'],
                    'end'   => $event['end_time'],
                ];
            }
        }
        
        return $busy;
    }
}
