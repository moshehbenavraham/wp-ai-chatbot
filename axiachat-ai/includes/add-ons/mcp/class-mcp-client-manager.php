<?php

/**
 * MCP Client Manager
 *
 * Manages connections to multiple MCP servers, handles tool discovery,
 * execution and capability negotiation.
 *
 * @package AIChat
 * @subpackage MCP
 */

if (! defined('ABSPATH')) {
	exit;
}

// MCP client manager may use direct DB reads/writes for internal plugin state.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

class AIChat_MCP_Client_Manager
{
	/**
	 * Singleton instance
	 * @var AIChat_MCP_Client_Manager|null
	 */
	private static $instance = null;

	/**
	 * Active server sessions
	 * @var array [ server_id => [ 'transport' => AIChat_MCP_Transport, 'capabilities' => array, 'tools' => array ] ]
	 */
	private $sessions = [];

	/**
	 * Cached tools from all servers
	 * @var array [ tool_name => [ 'server_id' => string, 'definition' => array ] ]
	 */
	private $tools_cache = [];

	/**
	 * Cache expiration time in seconds
	 * @var int
	 */
	private $cache_ttl = 300; // 5 minutes

	/**
	 * Get singleton instance
	 *
	 * @return AIChat_MCP_Client_Manager
	 */
	public static function instance()
	{
		if (self::$instance === null) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor
	 */
	private function __construct()
	{
		// NO auto-initialize servers on every page load - connect only when needed (lazy loading)
		// Connections happen on-demand when:
		// 1. A tool needs to be executed (during chat)
		// 2. Admin tests connection
		// 3. Admin adds/saves server

		// Clean up on shutdown
		add_action('shutdown', [$this, 'cleanup_sessions']);
	}

	/**
	 * Initialize connections to configured MCP servers (LAZY - only when explicitly called)
	 *
	 * This is NOT called automatically on every page load.
	 * Call this manually when you need to ensure all enabled servers are connected.
	 *
	 * @deprecated Use connect_server() on-demand instead
	 */
	public function initialize_servers()
	{
		// DEPRECATED: Do not auto-connect all servers on every page load
		// This was causing performance issues (2-5 seconds delay on every admin page)
		//
		// Connections are now lazy-loaded:
		// - connect_server() is called only when needed
		// - Tools are read from wp_aichat_tools table (fast)
		// - Actual server connection only happens when executing a tool

		aichat_log_debug('[MCP] initialize_servers() called (deprecated - connections are now lazy)');

		return;

		/* DISABLED AUTO-INITIALIZATION CODE:
		try {
			$servers_config = $this->get_servers_config();

			if ( empty( $servers_config ) ) {
				return;
			}

			foreach ( $servers_config as $server_id => $config ) {
				try {
					// Skip disabled servers
					if ( isset( $config['enabled'] ) && ! $config['enabled'] ) {
						continue;
					}

					// Try to connect (wrapped in try-catch to prevent one bad server from breaking all)
					$this->connect_server( $server_id, $config );

				} catch ( Exception $e ) {
					// Log error but continue with other servers
					aichat_log_debug( '[MCP] Failed to initialize server ' . $server_id . ': ' . $e->getMessage() );
				}
			}
		} catch ( Exception $e ) {
			// Log error and fail gracefully
			aichat_log_debug( '[MCP] Failed to initialize servers: ' . $e->getMessage() );
		}
		*/
	}

	/**
	 * Connect to an MCP server
	 *
	 * @param string $server_id Unique server identifier
	 * @param array  $config Server configuration
	 * @return bool True on success
	 */
	public function connect_server($server_id, array $config)
	{
		try {
			// OPTIMIZATION: Reuse existing session if already connected
			if (isset($this->sessions[ $server_id ])) {
				$this->log_debug('Reusing existing MCP session', [
					'server_id' => $server_id,
				]);

				return true;
			}

			// Create transport based on type
			$transport_type = isset($config['transport']) ? strtolower((string) $config['transport']) : 'http';

			if ($transport_type !== 'http') {
				$this->log_debug('Unsupported transport type', [
					'server_id' => $server_id,
					'type' => $transport_type,
				]);

				return false;
			}

			$transport = new AIChat_MCP_HTTP_Transport($config);

			// Attempt connection
			if (! $transport->connect()) {
				$this->log_debug('Failed to connect to MCP server', [
					'server_id' => $server_id,
					'error' => $transport->get_last_error(),
				]);

				return false;
			}

			// Perform MCP initialization handshake
			$init_result = $this->perform_initialization($transport, $server_id);

			if (! $init_result) {
				$transport->close();

				return false;
			}

			$this->log_debug('MCP initialization result received', [
				'server_id' => $server_id,
				'protocolVersion' => $init_result['protocolVersion'] ?? 'unknown',
				'capabilities' => wp_json_encode($init_result['capabilities'] ?? []),
				'serverInfo' => wp_json_encode($init_result['serverInfo'] ?? $init_result['server_info'] ?? []),
			]);

			// Store session (handle both camelCase and snake_case keys)
			$this->sessions[ $server_id ] = [
				'transport' => $transport,
				'capabilities' => $init_result['capabilities'] ?? [],
				'server_info' => $init_result['serverInfo'] ?? $init_result['server_info'] ?? [],
				'protocol_version' => $init_result['protocolVersion'] ?? '2025-06-18',
				'config' => $config,
				'connected_at' => time(),
			];

			$this->log_debug('MCP server connected successfully', [
				'server_id' => $server_id,
				'capabilities' => array_keys($init_result['capabilities'] ?? []),
			]);

			// NOTE: We do NOT auto-discover tools here anymore
			// Tools are only discovered when:
			// 1. Admin clicks "Test Connection" (explicit discovery)
			// 2. Server is added/saved (sync to DB)
			// For normal tool execution, we already have tools in DB
			// This avoids unnecessary tools/list calls on every connection

			// Fire action hook for integrations
			do_action('aichat_mcp_server_connected', $server_id);

			return true;

		} catch (Exception $e) {
			$this->log_debug('Exception during MCP server connection', [
				'server_id' => $server_id,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);

			return false;
		}
	}

	/**
	 * Perform MCP initialization handshake
	 *
	 * @param AIChat_MCP_Transport $transport
	 * @param string $server_id
	 * @return array|false Initialization result or false on failure
	 */
	private function perform_initialization($transport, $server_id)
	{
		$init_params = [
			'protocolVersion' => '2025-06-18',
			'capabilities' => [
				// Client capabilities we support
				'elicitation' => new stdClass(), // Can handle user input requests
				'sampling' => new stdClass(), // Can provide LLM sampling
			],
			'clientInfo' => [
				'name' => 'axiachat-ai-mcp',
				'version' => defined('AICHAT_MCP_VERSION') ? AICHAT_MCP_VERSION : '1.0.0',
			],
		];

		$response = $transport->send_request('initialize', $init_params);

		if ($response === null) {
			$this->log_debug('Initialization failed', [
				'server_id' => $server_id,
				'error' => $transport->get_last_error(),
			]);

			return false;
		}

		// Extract result
		$result = isset($response['result']) ? $response['result'] : [];

		// Send initialized notification
		$transport->send_request('notifications/initialized', [], null);

		return $result;
	}

	/**
	 * Discover tools from a specific server
	 *
	 * @param string $server_id
	 * @return array|null Array of tools or null on failure
	 */
	public function discover_tools($server_id)
	{
		try {
			if (! isset($this->sessions[ $server_id ])) {
				return null;
			}

			$transport = $this->sessions[ $server_id ]['transport'];

			$response = $transport->send_request('tools/list', []);

			if ($response === null) {
				$this->log_debug('Tools discovery failed', [
					'server_id' => $server_id,
					'error' => $transport->get_last_error(),
				]);

				return null;
			}

			$this->log_debug('Tools/list response received', [
				'server_id' => $server_id,
				'response' => $response,
			]);

			$tools = isset($response['result']['tools']) ? $response['result']['tools'] : [];

			// Cache tools
			foreach ($tools as $tool) {
				if (! isset($tool['name'])) {
					continue;
				}

				$tool_name = $tool['name'];

				// Prefix with server_id to avoid conflicts
				$global_tool_name = $server_id . '_' . $tool_name;

				$this->tools_cache[ $global_tool_name ] = [
					'server_id' => $server_id,
					'local_name' => $tool_name,
					'definition' => $tool,
					'cached_at' => time(),
				];
			}

			// Store in session
			$this->sessions[ $server_id ]['tools'] = $tools;

			// NOTE: We do NOT sync to database here (discover_tools is read-only)
			// Sync only happens when:
			// - Adding new MCP server (admin-ajax.php:aichat_mcp_ajax_add_server)
			// - Testing connection (admin-ajax.php:aichat_mcp_ajax_test_connection)

			$this->log_debug('Tools discovered', [
				'server_id' => $server_id,
				'tool_count' => count($tools),
			]);

			return $tools;

		} catch (Exception $e) {
			$this->log_debug('Exception during tool discovery', [
				'server_id' => $server_id,
				'error' => $e->getMessage(),
			]);

			return null;
		}
	}

	/**
	 * Execute a tool on the appropriate MCP server
	 *
	 * @param string $tool_name Global tool name (server_id_toolname)
	 * @param array  $arguments Tool arguments
	 * @param array  $context Execution context
	 * @return array Tool result
	 */
	public function execute_tool($tool_name, array $arguments, array $context = [])
	{
		try {
			// OPTIMIZED: Parse tool name instead of requiring cache
			// Tool name format: mcp_servername_suffix_toolname (sanitized with _ instead of -)
			// We need to extract server_id and local_name from it

			// First, try to get from cache (if available from previous discover_tools)
			if (isset($this->tools_cache[ $tool_name ])) {
				$tool_info = $this->tools_cache[ $tool_name ];
				$server_id = $tool_info['server_id'];
				$local_name = $tool_info['local_name'];
			} else {
				// Parse tool name to extract server_id and local tool name
				// Read from database to get the original server_id and tool name
				global $wpdb;
				$table = $wpdb->prefix . 'aichat_tools';

				// Query by sanitized safe name
				// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name and parameters are safe.
				$tool_row = $wpdb->get_row($wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
					"SELECT source_id, name FROM {$table} WHERE type = 'mcp' AND REPLACE(LOWER(CONCAT(source_id, '_', name)), '-', '_') = %s LIMIT 1",
					strtolower($tool_name)
				), ARRAY_A);

				if (! $tool_row) {
					return [
						'ok' => false,
						'error' => 'unknown_tool',
						'message' => 'Tool not found in database',
					];
				}

				$server_id = $tool_row['source_id'];
				$local_name = $tool_row['name'];
			}

			// Get session
			if (! isset($this->sessions[ $server_id ])) {
				return [
					'ok' => false,
					'error' => 'server_not_connected',
					'message' => 'MCP server not connected',
				];
			}

			$transport = $this->sessions[ $server_id ]['transport'];

			$start_time = microtime(true);

			// Call tool via JSON-RPC
			$response = $transport->send_request('tools/call', [
				'name' => $local_name,
				'arguments' => $arguments,
			]);

			$elapsed_ms = round((microtime(true) - $start_time) * 1000);

			if ($response === null) {
				$this->log_debug('Tool execution failed', [
					'tool' => $tool_name,
					'server_id' => $server_id,
					'error' => $transport->get_last_error(),
					'ms' => $elapsed_ms,
				]);

				return [
					'ok' => false,
					'error' => 'execution_failed',
					'message' => $transport->get_last_error(),
				];
			}

			$this->log_debug('Tool executed successfully', [
				'tool' => $tool_name,
				'server_id' => $server_id,
				'ms' => $elapsed_ms,
			]);

			// Convert MCP content format to AxiaChat format
			$mcp_content = isset($response['result']['content']) ? $response['result']['content'] : [];
			$result_text = $this->convert_mcp_content($mcp_content);

			return [
				'ok' => true,
				'result' => $result_text,
				'content' => $mcp_content, // Original MCP content for advanced usage
			];

		} catch (Exception $e) {
			$this->log_debug('Exception during tool execution', [
				'tool' => $tool_name,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);

			return [
				'ok' => false,
				'error' => 'exception',
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Convert MCP content array to internal format
	 *
	 * @param array $mcp_content MCP content array
	 * @return array Converted result
	 */
	private function convert_mcp_content(array $mcp_content)
	{
		if (empty($mcp_content)) {
			return ['ok' => true, 'output' => ''];
		}

		$text_parts = [];
		$data_parts = [];

		foreach ($mcp_content as $item) {
			$type = isset($item['type']) ? $item['type'] : 'text';

			switch ($type) {
				case 'text':
					if (isset($item['text'])) {
						$text_parts[] = $item['text'];
					}
					break;

				case 'resource':
					// Store resource references
					if (isset($item['resource'])) {
						$data_parts[] = $item['resource'];
					}
					break;

					// Add more content types as needed
			}
		}

		// Combine text parts
		$output = implode("\n", $text_parts);

		$result = [
			'ok' => true,
			'output' => $output,
		];

		if (! empty($data_parts)) {
			$result['data'] = $data_parts;
		}

		return $result;
	}

	/**
	 * Get all discovered tools from all connected servers
	 *
	 * @return array Array of tools
	 */
	public function get_all_tools()
	{
		return $this->tools_cache;
	}

	/**
	 * Get server info for a specific server
	 *
	 * @param string $server_id
	 * @return array|null Server info or null if not connected
	 */
	/**
	 * Get server information
	 *
	 * Returns the full initialization result including capabilities,
	 * protocol version, and server info
	 *
	 * @param string $server_id
	 * @return array|null Full init result or null if not connected
	 */
	public function get_server_info($server_id)
	{
		if (! isset($this->sessions[ $server_id ])) {
			return null;
		}

		$session = $this->sessions[ $server_id ];

		// Return complete info including capabilities
		return [
			'capabilities' => $session['capabilities'] ?? [],
			'serverInfo' => $session['server_info'] ?? [],
			'protocolVersion' => $session['protocol_version'] ?? '2025-06-18',
			'connected_at' => $session['connected_at'] ?? null,
		];
	}

	/**
	 * Get tools for a specific bot
	 *
	 * @param string $bot_slug
	 * @return array
	 */
	public function get_tools_for_bot($bot_slug)
	{
		$all_tools = $this->get_all_tools();

		// Filter by bot configuration
		// TODO: Implement per-bot MCP server filtering

		return $all_tools;
	}

	/**
	 * Get MCP servers configuration
	 *
	 * @return array
	 */
	private function get_servers_config()
	{
		$config = get_option('aichat_mcp_servers', []);

		// Handle both array (correct) and JSON string (legacy/edge case)
		if (is_string($config) && ! empty($config)) {
			$decoded = json_decode($config, true);
			$config = is_array($decoded) ? $decoded : [];
		}

		return is_array($config) ? $config : [];
	}

	/**
	 * Cleanup all sessions on shutdown
	 */
	public function cleanup_sessions()
	{
		foreach ($this->sessions as $server_id => $session) {
			if (isset($session['transport']) && is_object($session['transport'])) {
				$session['transport']->close();
			}
		}

		$this->sessions = [];
	}

	/**
	 * Get transport for a specific server
	 *
	 * @param string $server_id Server identifier
	 * @return AIChat_MCP_Transport|null Transport instance or null if not found
	 */
	public function get_transport($server_id)
	{
		if (isset($this->sessions[ $server_id ]['transport'])) {
			return $this->sessions[ $server_id ]['transport'];
		}

		return null;
	}

	/**
	 * Log debug message
	 *
	 * @param string $message
	 * @param array  $context
	 */
	private function log_debug($message, $context = [])
	{
		if (function_exists('aichat_log_debug') && defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
			aichat_log_debug('[MCP Manager] ' . $message, $context);
		}
	}
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
