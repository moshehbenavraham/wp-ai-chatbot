<?php
/**
 * Google Calendar OAuth Handler
 * 
 * Handles OAuth 2.0 authentication flow with Google Calendar API.
 * Uses the plugin's shared Google credentials as a proxy.
 * 
 * @package AxiaChat_AI
 * @subpackage Appointments
 * @since 2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_GCal_OAuth {
    
    /** @var string Google OAuth endpoints */
    const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';
    
    /** @var string API Scopes */
    const SCOPES = 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly';
    
    /** @var string Option keys for storing tokens */
    const OPTION_ACCESS_TOKEN  = 'aichat_gcal_access_token';
    const OPTION_REFRESH_TOKEN = 'aichat_gcal_refresh_token';
    const OPTION_TOKEN_EXPIRY  = 'aichat_gcal_token_expiry';
    const OPTION_USER_EMAIL    = 'aichat_gcal_user_email';
    const OPTION_CALENDAR_ID   = 'aichat_gcal_calendar_id';
    
    /** @var string Client credentials */
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    
    /** @var string Central OAuth callback URL */
    const CENTRAL_CALLBACK_URL = 'https://axiachat.org/site/wp-admin/admin.php?page=aichat-appointments&tab=settings&gcal_callback=1';
    
    /**
     * Constructor
     */
    public function __construct() {
        // These are the shared plugin credentials
        $this->client_id     = '353820719147-fq0u9scoqkgakoq42iu2d0mdn27pltj7.apps.googleusercontent.com';
        $this->client_secret = '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';
        // Use central callback URL for Google Cloud App verification
        $this->redirect_uri  = self::CENTRAL_CALLBACK_URL;
    }
    
    /**
     * Get the local callback URL (where central server should redirect back)
     */
    public function get_local_callback_url() {
        return admin_url( 'admin.php?page=aichat-appointments&tab=settings&gcal_callback=1' );
    }
    
    /**
     * Get the authorization URL for OAuth flow
     * 
     * @return string Authorization URL to redirect user to
     */
    public function get_auth_url() {
        $nonce = wp_create_nonce( 'gcal_oauth_state' );
        set_transient( 'aichat_gcal_oauth_state', $nonce, 600 ); // 10 minutes
        
        // Encode state with nonce and return URL for central server redirect
        $state_data = [
            'nonce'      => $nonce,
            'return_url' => $this->get_local_callback_url(),
        ];
        $state = base64_encode( wp_json_encode( $state_data ) );
        
        $params = [
            'client_id'     => $this->client_id,
            'redirect_uri'  => $this->redirect_uri,
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            'access_type'   => 'offline',  // Get refresh token
            'prompt'        => 'consent',  // Always show consent to get refresh token
            'state'         => $state,
        ];
        
        return self::AUTH_URL . '?' . http_build_query( $params );
    }
    
    /**
     * Exchange authorization code for tokens
     * 
     * @param string $code Authorization code from Google
     * @param string $state State parameter for CSRF protection (may be base64 encoded JSON)
     * @return array|WP_Error Token data or error
     */
    public function exchange_code( $code, $state ) {
        // Verify state - handle both old format (nonce only) and new format (JSON with nonce + return_url)
        $saved_state = get_transient( 'aichat_gcal_oauth_state' );
        
        // Try to decode state as JSON (new format from central redirect)
        $state_data = json_decode( base64_decode( $state ), true );
        $state_nonce = is_array( $state_data ) && isset( $state_data['nonce'] ) ? $state_data['nonce'] : $state;
        
        if ( ! $saved_state || $state_nonce !== $saved_state ) {
            return new WP_Error( 'invalid_state', __( 'Invalid OAuth state. Please try again.', 'axiachat-ai' ) );
        }
        delete_transient( 'aichat_gcal_oauth_state' );
        
        $response = wp_remote_post( self::TOKEN_URL, [
            'body' => [
                'code'          => $code,
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'redirect_uri'  => $this->redirect_uri,
                'grant_type'    => 'authorization_code',
            ],
            'timeout' => 30,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( isset( $body['error'] ) ) {
            return new WP_Error( 
                'oauth_error', 
                $body['error_description'] ?? $body['error'] 
            );
        }
        
        if ( empty( $body['access_token'] ) ) {
            return new WP_Error( 'no_token', __( 'No access token received from Google.', 'axiachat-ai' ) );
        }
        
        // Store tokens securely
        $this->store_tokens( $body );
        
        // Get user info
        $user_info = $this->get_user_info( $body['access_token'] );
        if ( ! is_wp_error( $user_info ) && isset( $user_info['email'] ) ) {
            update_option( self::OPTION_USER_EMAIL, $user_info['email'] );
        }
        
        // Set default calendar to primary
        update_option( self::OPTION_CALENDAR_ID, 'primary' );
        
        return [
            'success' => true,
            'email'   => $user_info['email'] ?? '',
        ];
    }
    
    /**
     * Store tokens in options (encrypted)
     * 
     * @param array $token_data Token response from Google
     */
    private function store_tokens( $token_data ) {
        $access_token = $this->encrypt_token( $token_data['access_token'] );
        update_option( self::OPTION_ACCESS_TOKEN, $access_token );
        
        if ( ! empty( $token_data['refresh_token'] ) ) {
            $refresh_token = $this->encrypt_token( $token_data['refresh_token'] );
            update_option( self::OPTION_REFRESH_TOKEN, $refresh_token );
        }
        
        $expiry = time() + ( $token_data['expires_in'] ?? 3600 );
        update_option( self::OPTION_TOKEN_EXPIRY, $expiry );
        
        aichat_appointments_log( 'Google Calendar tokens stored', [
            'expires_in' => $token_data['expires_in'] ?? 3600,
        ] );
    }
    
    /**
     * Get valid access token (refreshes if expired)
     * 
     * @return string|WP_Error Access token or error
     */
    public function get_access_token() {
        $access_token = get_option( self::OPTION_ACCESS_TOKEN );
        $expiry = (int) get_option( self::OPTION_TOKEN_EXPIRY, 0 );
        
        if ( empty( $access_token ) ) {
            return new WP_Error( 'not_connected', __( 'Google Calendar is not connected.', 'axiachat-ai' ) );
        }
        
        // Check if token is expired (with 5 minute buffer)
        if ( $expiry > 0 && time() >= ( $expiry - 300 ) ) {
            $refreshed = $this->refresh_access_token();
            if ( is_wp_error( $refreshed ) ) {
                return $refreshed;
            }
            $access_token = get_option( self::OPTION_ACCESS_TOKEN );
        }
        
        return $this->decrypt_token( $access_token );
    }
    
    /**
     * Refresh the access token using refresh token
     * 
     * @return bool|WP_Error True on success or error
     */
    private function refresh_access_token() {
        $refresh_token = get_option( self::OPTION_REFRESH_TOKEN );
        
        if ( empty( $refresh_token ) ) {
            $this->disconnect();
            return new WP_Error( 'no_refresh_token', __( 'No refresh token. Please reconnect Google Calendar.', 'axiachat-ai' ) );
        }
        
        $refresh_token = $this->decrypt_token( $refresh_token );
        
        $response = wp_remote_post( self::TOKEN_URL, [
            'body' => [
                'refresh_token' => $refresh_token,
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type'    => 'refresh_token',
            ],
            'timeout' => 30,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( isset( $body['error'] ) ) {
            // If refresh fails, disconnect
            $this->disconnect();
            return new WP_Error( 
                'refresh_failed', 
                __( 'Failed to refresh token. Please reconnect Google Calendar.', 'axiachat-ai' ) 
            );
        }
        
        $this->store_tokens( $body );
        
        aichat_appointments_log( 'Google Calendar token refreshed' );
        
        return true;
    }
    
    /**
     * Get user info from Google
     * 
     * @param string $access_token
     * @return array|WP_Error
     */
    private function get_user_info( $access_token ) {
        $response = wp_remote_get( 'https://www.googleapis.com/oauth2/v2/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 15,
        ] );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        return json_decode( wp_remote_retrieve_body( $response ), true );
    }
    
    /**
     * Check if connected to Google Calendar
     * 
     * @return bool
     */
    public function is_connected() {
        $access_token = get_option( self::OPTION_ACCESS_TOKEN );
        $refresh_token = get_option( self::OPTION_REFRESH_TOKEN );
        
        return ! empty( $access_token ) && ! empty( $refresh_token );
    }
    
    /**
     * Get connection status info
     * 
     * @return array
     */
    public function get_connection_status() {
        return [
            'connected'   => $this->is_connected(),
            'email'       => get_option( self::OPTION_USER_EMAIL, '' ),
            'calendar_id' => get_option( self::OPTION_CALENDAR_ID, 'primary' ),
        ];
    }
    
    /**
     * Disconnect from Google Calendar
     * 
     * @return bool
     */
    public function disconnect() {
        // Try to revoke token at Google
        $access_token = get_option( self::OPTION_ACCESS_TOKEN );
        if ( $access_token ) {
            $token = $this->decrypt_token( $access_token );
            wp_remote_post( self::REVOKE_URL . '?token=' . $token, [
                'timeout' => 10,
            ] );
        }
        
        // Clear all stored data
        delete_option( self::OPTION_ACCESS_TOKEN );
        delete_option( self::OPTION_REFRESH_TOKEN );
        delete_option( self::OPTION_TOKEN_EXPIRY );
        delete_option( self::OPTION_USER_EMAIL );
        delete_option( self::OPTION_CALENDAR_ID );
        
        aichat_appointments_log( 'Google Calendar disconnected' );
        
        return true;
    }
    
    /**
     * Set the calendar ID to use
     * 
     * @param string $calendar_id
     */
    public function set_calendar_id( $calendar_id ) {
        update_option( self::OPTION_CALENDAR_ID, sanitize_text_field( $calendar_id ) );
    }
    
    /**
     * Get the current calendar ID
     * 
     * @return string
     */
    public function get_calendar_id() {
        return get_option( self::OPTION_CALENDAR_ID, 'primary' );
    }
    
    /**
     * Encrypt a token for storage
     * 
     * @param string $token
     * @return string
     */
    private function encrypt_token( $token ) {
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            return base64_encode( $token ); // Fallback
        }
        
        $key = $this->get_encryption_key();
        $iv = openssl_random_pseudo_bytes( 16 );
        $encrypted = openssl_encrypt( $token, 'AES-256-CBC', $key, 0, $iv );
        
        return base64_encode( $iv . '::' . $encrypted );
    }
    
    /**
     * Decrypt a stored token
     * 
     * @param string $encrypted
     * @return string
     */
    private function decrypt_token( $encrypted ) {
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            return base64_decode( $encrypted ); // Fallback
        }
        
        $data = base64_decode( $encrypted );
        if ( strpos( $data, '::' ) === false ) {
            return base64_decode( $encrypted ); // Legacy unencrypted
        }
        
        list( $iv, $encrypted_data ) = explode( '::', $data, 2 );
        $key = $this->get_encryption_key();
        
        return openssl_decrypt( $encrypted_data, 'AES-256-CBC', $key, 0, $iv );
    }
    
    /**
     * Get encryption key from WordPress
     * 
     * @return string
     */
    private function get_encryption_key() {
        if ( defined( 'SECURE_AUTH_KEY' ) && SECURE_AUTH_KEY ) {
            return hash( 'sha256', SECURE_AUTH_KEY . 'aichat_gcal' );
        }
        return hash( 'sha256', 'aichat_gcal_default_key' );
    }
}
