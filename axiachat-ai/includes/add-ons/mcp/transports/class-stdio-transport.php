<?php
/**
 * STDIO transport was removed in favour of the hardened HTTP-only architecture.
 * This placeholder remains to avoid autoload errors if legacy code attempts to include it.
 *
 * @package AIChat
 * @subpackage MCP
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! class_exists( 'AIChat_MCP_STDIO_Transport', false ) ) {
    class AIChat_MCP_STDIO_Transport extends AIChat_MCP_Transport {
        public function __construct( array $config = [] ) {
            parent::__construct( $config );
            $this->last_error = 'STDIO transport support has been removed.';
        }

        public function connect() {
            return false;
        }

        public function send_request( $method, $params = [], $id = null ) {
            return null;
        }

        public function close() {
            return true;
        }

        public function get_type() {
            return 'stdio';
        }
    }
}
