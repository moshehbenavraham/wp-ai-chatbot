<?php
/**
 * HTTP Transport for MCP
 * 
 * Implements MCP over HTTP using WordPress HTTP API.
 * Supports bearer tokens, API keys and custom headers.
 * 
 * @package AIChat
 * @subpackage MCP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AIChat_MCP_HTTP_Transport extends AIChat_MCP_Transport {
    
    /**
     * Base URL of MCP server
     * @var string
     */
    private $base_url = '';
    
    /**
     * Authentication headers
     * @var array
     */
    private $auth_headers = [];
    
    /**
     * Request timeout in seconds
     * @var int
     */
    private $timeout = 30;
    
    /**
     * MCP Session ID (for Streamable HTTP servers)
     * Server may send this in Mcp-Session-Id header during initialize.
     * Client must include it in all subsequent requests.
     * 
     * @var string|null
     */
    private $session_id = null;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array:
     *   - url: string Base URL of MCP server
     *   - auth: array Authentication config
     *     - type: 'bearer'|'api_key'|'custom'
     *     - token: string Bearer token
     *     - key: string API key value
     *     - header: string Header name for API key
     *     - headers: array Custom headers
     *   - timeout: int Request timeout (default 30)
     */
    public function __construct( array $config = [] ) {
        parent::__construct( $config );
        
        $this->base_url = isset( $config['url'] ) ? esc_url_raw( $config['url'] ) : '';
        $this->timeout  = isset( $config['timeout'] ) ? (int) $config['timeout'] : 30;
        
        // Setup authentication headers
        if ( isset( $config['auth'] ) && is_array( $config['auth'] ) ) {
            $this->setup_auth_headers( $config['auth'] );
        }
    }
    
    /**
     * Setup authentication headers based on config
     * 
     * @param array $auth_config
     */
    private function setup_auth_headers( array $auth_config ) {
        $type = isset( $auth_config['type'] ) ? $auth_config['type'] : '';
        
        switch ( $type ) {
            case 'bearer':
                if ( ! empty( $auth_config['token'] ) ) {
                    $this->auth_headers['Authorization'] = 'Bearer ' . sanitize_text_field( $auth_config['token'] );
                }
                break;
                
            case 'api_key':
                if ( ! empty( $auth_config['key'] ) && ! empty( $auth_config['header'] ) ) {
                    $header_name = sanitize_text_field( $auth_config['header'] );
                    $this->auth_headers[ $header_name ] = sanitize_text_field( $auth_config['key'] );
                }
                break;
                
            case 'custom':
                if ( ! empty( $auth_config['headers'] ) && is_array( $auth_config['headers'] ) ) {
                    foreach ( $auth_config['headers'] as $key => $value ) {
                        $this->auth_headers[ sanitize_text_field( $key ) ] = sanitize_text_field( $value );
                    }
                }
                break;
        }
    }
    
    /**
     * Test connection to MCP server
     * 
     * For HTTP transport, there's no persistent connection to establish.
     * We just validate the URL is configured - actual initialization happens
     * via perform_initialization() in the manager.
     * 
     * @return bool
     */
    public function connect() {
        if ( empty( $this->base_url ) ) {
            $this->last_error = 'No URL configured';
            return false;
        }
        
        // Mark as connected - HTTP is stateless, actual handshake happens in manager
        $this->connected = true;
        $this->log_debug( 'HTTP transport ready', [ 'url' => $this->base_url ] );
        
        return true;
    }
    
    /**
     * Send JSON-RPC request via HTTP POST
     * 
     * @param string      $method
     * @param array       $params
     * @param string|null $id
     * @return array|null
     */
    public function send_request( $method, $params = [], $id = null ) {
        if ( empty( $this->base_url ) ) {
            $this->last_error = 'No URL configured';
            return null;
        }
        
        // Auto-generate ID if not provided (for requests, not notifications)
        if ( $id === null && strpos( $method, 'notifications/' ) !== 0 ) {
            $id = $this->generate_request_id();
        }
        
        $request_payload = $this->build_jsonrpc_request( $method, $params, $id );
        $request_json    = wp_json_encode( $request_payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        
        $this->log_debug( 'Sending HTTP request', [
            'method'       => $method,
            'url'          => $this->base_url,
            'payload'      => strlen( $request_json ) . ' bytes',
            'request_json' => $request_json, // Log actual JSON being sent
        ] );
        
        $start_time = microtime( true );
        
        // Prepare headers - accept both JSON and SSE to support various MCP server implementations
        // Include MCP-Protocol-Version header for servers that require it (e.g., Smithery)
        $headers = array_merge( [
            'Content-Type'          => 'application/json',
            'Accept'                => 'application/json, text/event-stream',
            'MCP-Protocol-Version'  => '2025-06-18',
        ], $this->auth_headers );
        
        // Include Session ID if we have one (for Streamable HTTP servers per MCP spec 2025-06-18)
        if ( $this->session_id !== null ) {
            $headers['mcp-session-id'] = $this->session_id;
            $this->log_debug( 'Including MCP Session ID in request', [
                'session_id' => substr( $this->session_id, 0, 16 ) . '...', // Log first 16 chars
            ] );
        }
        
        // Send POST request
        $response = wp_remote_post( $this->base_url, [
            'headers' => $headers,
            'body'    => $request_json,
            'timeout' => $this->timeout,
        ] );
        
        $elapsed = round( ( microtime( true ) - $start_time ) * 1000 );
        
        // Check for WordPress HTTP errors
        if ( is_wp_error( $response ) ) {
            $this->last_error = $response->get_error_message();
            $this->log_debug( 'HTTP request failed', [
                'error' => $this->last_error,
                'ms'    => $elapsed,
            ] );
            return null;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body        = wp_remote_retrieve_body( $response );
        $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        
        // Capture MCP Session ID if server provides one (Streamable HTTP per MCP spec 2025-06-18)
        $new_session_id = wp_remote_retrieve_header( $response, 'mcp-session-id' );
        if ( ! empty( $new_session_id ) && $this->session_id !== $new_session_id ) {
            $this->session_id = $new_session_id;
            $this->log_debug( 'MCP Session ID received from server', [
                'session_id' => substr( $new_session_id, 0, 16 ) . '...', // Log first 16 chars
            ] );
        }
        
        $this->log_debug( 'HTTP response received', [
            'status'       => $status_code,
            'content_type' => $content_type,
            'size'         => strlen( $body ) . ' bytes',
            'ms'           => $elapsed,
        ] );
        
        // Check HTTP status
        if ( $status_code < 200 || $status_code >= 300 ) {
            $this->last_error = sprintf( 'HTTP %d error', $status_code );
            
            // Try to extract error from body
            $error_data = json_decode( $body, true );
            if ( isset( $error_data['error']['message'] ) ) {
                $this->last_error .= ': ' . $error_data['error']['message'];
            }
            
            return null;
        }
        
        // Handle SSE response format if server sends text/event-stream
        if ( strpos( $content_type, 'text/event-stream' ) !== false ) {
            $this->log_debug( 'SSE raw response body', [
                'body' => substr( $body, 0, 500 ), // First 500 chars for debugging
            ] );
            
            $body = $this->extract_json_from_sse( $body );
            
            $this->log_debug( 'SSE response converted to JSON', [
                'extracted_size' => strlen( $body ) . ' bytes',
                'extracted_json' => $body, // Log the actual extracted JSON
            ] );
        }
        
        // Parse JSON-RPC response
        return $this->parse_jsonrpc_response( $body );
    }
    
    /**
     * Extract JSON from SSE format
     * 
     * SSE sends data as lines prefixed with "data: "
     * We extract the last complete JSON-RPC response
     * 
     * @param string $sse_body Raw SSE response body
     * @return string JSON string
     */
    private function extract_json_from_sse( $sse_body ) {
        $lines = explode( "\n", $sse_body );
        $json_responses = [];
        
        foreach ( $lines as $line ) {
            $line = trim( $line );
            
            // SSE data lines start with "data: "
            if ( strpos( $line, 'data: ' ) === 0 ) {
                $json_str = trim( substr( $line, 6 ) ); // Remove "data: " prefix
                
                // Validate it's JSON
                $decoded = json_decode( $json_str, true );
                if ( $decoded !== null && isset( $decoded['jsonrpc'] ) ) {
                    $json_responses[] = $json_str;
                }
            }
        }
        
        // Return the last JSON-RPC response (usually the final result)
        // Or first if only one exists
        if ( ! empty( $json_responses ) ) {
            return end( $json_responses );
        }
        
        // Fallback: return original body and let parser handle it
        return $sse_body;
    }
    
    /**
     * Close connection (no-op for HTTP)
     * 
     * @return bool
     */
    public function close() {
        $this->connected = false;
        $this->session_id = null; // Clear session on close
        return true;
    }
    
    /**
     * Get transport type
     * 
     * @return string
     */
    public function get_type() {
        return 'http';
    }
    
    /**
     * Get current session ID (for debugging/testing)
     * 
     * @return string|null
     */
    public function get_session_id() {
        return $this->session_id;
    }
    
    /**
     * Check if this transport has an active session
     * 
     * @return bool
     */
    public function has_session() {
        return $this->session_id !== null;
    }
}
