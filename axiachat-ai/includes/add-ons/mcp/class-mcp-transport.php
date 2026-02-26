<?php
/**
 * Abstract MCP Transport Base Class
 * 
 * Base class for all MCP transport implementations (HTTP).
 * Defines the common interface for JSON-RPC 2.0 communication.
 * 
 * @package AIChat
 * @subpackage MCP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class AIChat_MCP_Transport {
    
    /**
     * Transport configuration
     * @var array
     */
    protected $config = [];
    
    /**
     * Is connection active?
     * @var bool
     */
    protected $connected = false;
    
    /**
     * Last error message
     * @var string|null
     */
    protected $last_error = null;
    
    /**
     * Constructor
     * 
     * @param array $config Transport-specific configuration
     */
    public function __construct( array $config = [] ) {
        $this->config = $config;
    }
    
    /**
     * Establish connection to MCP server
     * 
     * @return bool True on success, false on failure
     */
    abstract public function connect();
    
    /**
     * Send JSON-RPC request
     * 
     * @param string $method JSON-RPC method name
     * @param array  $params Method parameters
     * @param string|null $id Request ID (null for notifications)
     * @return array|null Response array or null on error
     */
    abstract public function send_request( $method, $params = [], $id = null );
    
    /**
     * Close connection
     * 
     * @return bool True on success
     */
    abstract public function close();
    
    /**
     * Check if transport is connected
     * 
     * @return bool
     */
    public function is_connected() {
        return $this->connected;
    }
    
    /**
     * Get last error message
     * 
     * @return string|null
     */
    public function get_last_error() {
        return $this->last_error;
    }
    
    /**
     * Build JSON-RPC 2.0 request payload
     * 
     * @param string $method Method name
     * @param array  $params Parameters
     * @param string|null $id Request ID
     * @return array
     */
    protected function build_jsonrpc_request( $method, $params = [], $id = null ) {
        $request = [
            'jsonrpc' => '2.0',
            'method'  => $method,
        ];
        
        if ( ! empty( $params ) ) {
            $request['params'] = $params;
        }
        
        if ( $id !== null ) {
            $request['id'] = $id;
        }
        
        return $request;
    }
    
    /**
     * Parse JSON-RPC 2.0 response
     * 
     * @param string $raw_response Raw JSON response
     * @return array|null Parsed response or null on error
     */
    protected function parse_jsonrpc_response( $raw_response ) {
        if ( empty( $raw_response ) ) {
            $this->last_error = 'Empty response';
            return null;
        }
        
        $data = json_decode( $raw_response, true );
        
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $this->last_error = 'JSON parse error: ' . json_last_error_msg();
            return null;
        }
        
        // Check JSON-RPC version
        if ( ! isset( $data['jsonrpc'] ) || $data['jsonrpc'] !== '2.0' ) {
            $this->last_error = 'Invalid JSON-RPC version';
            return null;
        }
        
        // Check for error response
        if ( isset( $data['error'] ) ) {
            $error_msg = isset( $data['error']['message'] ) 
                ? $data['error']['message'] 
                : 'Unknown error';
            $error_code = isset( $data['error']['code'] ) 
                ? $data['error']['code'] 
                : -1;
            
            $this->last_error = sprintf( 
                'JSON-RPC error [%d]: %s', 
                $error_code, 
                $error_msg 
            );
            
            return null;
        }
        
        return $data;
    }
    
    /**
     * Generate unique request ID
     * 
     * @return string
     */
    protected function generate_request_id() {
        return 'req_' . wp_generate_uuid4();
    }
    
    /**
     * Log debug message
     * 
     * @param string $message
     * @param array  $context
     */
    protected function log_debug( $message, $context = [] ) {
        if ( function_exists('aichat_log_debug') && defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug( '[MCP Transport] ' . $message, $context );
        }
    }
}
