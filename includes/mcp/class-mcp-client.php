<?php

/**
 * MCP Client — HTTP transport client for Model Context Protocol servers.
 *
 * Implements the MCP 2025-06-18 protocol via JSON-RPC 2.0 over HTTP(S).
 * Connections are lazy (no auto-connect on instantiation); call connect() or
 * ensure_connected() before calling list_tools() / execute_tool().
 *
 * Protocol flow:
 *   1. POST /  with method=initialize, params={protocolVersion, capabilities, clientInfo}
 *   2. POST /  with method=notifications/initialized (no response needed)
 *   3. POST /  with method=tools/list (returns tool definitions)
 *   4. POST /  with method=tools/call, params={name, arguments}
 *
 * Reference: https://modelcontextprotocol.io/specification
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Mcp;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Mcp_Client
 *
 * @since 1.0.0
 * @package WPAIC\Mcp
 */
class Mcp_Client
{
	/** MCP protocol version we advertise. */
	public const PROTOCOL_VERSION = '2025-06-18';

	/** Default request timeout in seconds. */
	public const DEFAULT_TIMEOUT = 15;

	/** @var array Server configuration {url, headers, timeout}. */
	private $config;

	/** @var bool Whether the MCP handshake has been completed. */
	private $connected = false;

	/** @var array Server capabilities from the initialize response. */
	private $server_caps = [];

	/** @var array|null Server info from the initialize response. */
	private $server_info = null;

	/** @var string|null Last error message. */
	private $last_error = null;

	/** @var int JSON-RPC request ID counter. */
	private $rpc_id = 0;

	/**
	 * Constructor.
	 *
	 * @param array $config {
	 *     @type string $url     Server endpoint URL (required).
	 *     @type array  $headers Additional HTTP headers (e.g. Authorization).
	 *     @type int    $timeout Request timeout in seconds (default: 15).
	 * }
	 */
	public function __construct(array $config)
	{
		$this->config = $config;
	}

	// =========================================================================
	// Connection lifecycle
	// =========================================================================

	/**
	 * Perform the MCP initialization handshake.
	 *
	 * @return bool True on success.
	 */
	public function connect(): bool
	{
		if ($this->connected) {
			return true;
		}

		$init_params = [
			'protocolVersion' => self::PROTOCOL_VERSION,
			'capabilities' => new \stdClass(),
			'clientInfo' => [
				'name' => 'wp-ai-chatbot',
				'version' => defined('WPAIC_VERSION') ? WPAIC_VERSION : '1.0.0',
			],
		];

		$response = $this->send_rpc('initialize', $init_params);

		if (null === $response) {
			return false;
		}

		$result = $response['result'] ?? [];
		$this->server_caps = $result['capabilities'] ?? [];
		$this->server_info = $result['serverInfo'] ?? $result['server_info'] ?? null;

		// Send initialized notification (fire-and-forget).
		$this->send_rpc('notifications/initialized', new \stdClass(), true);

		$this->connected = true;

		return true;
	}

	/**
	 * Connect if not already connected.
	 *
	 * @return bool
	 */
	public function ensure_connected(): bool
	{
		return $this->connected ? true : $this->connect();
	}

	/**
	 * Whether the client is connected.
	 *
	 * @return bool
	 */
	public function is_connected(): bool
	{
		return $this->connected;
	}

	// =========================================================================
	// Tool operations
	// =========================================================================

	/**
	 * Fetch the list of tools from the MCP server.
	 *
	 * @return array|null Array of tool definitions, or null on error.
	 */
	public function list_tools(): ?array
	{
		if (! $this->ensure_connected()) {
			return null;
		}

		$response = $this->send_rpc('tools/list', new \stdClass());

		if (null === $response) {
			return null;
		}

		return $response['result']['tools'] ?? [];
	}

	/**
	 * Execute a tool on the MCP server.
	 *
	 * @param  string $tool_name  Tool name (as returned by list_tools).
	 * @param  array  $arguments  Tool arguments.
	 * @return array  {ok, output, content?, error?, message?}
	 */
	public function execute_tool(string $tool_name, array $arguments): array
	{
		if (! $this->ensure_connected()) {
			return [
				'ok' => false,
				'output' => '',
				'error' => 'not_connected',
				'message' => $this->last_error ?? __('MCP server is not connected.', 'wp-ai-chatbot'),
			];
		}

		$response = $this->send_rpc('tools/call', [
			'name' => $tool_name,
			'arguments' => $arguments,
		]);

		if (null === $response) {
			return [
				'ok' => false,
				'output' => '',
				'error' => 'rpc_failed',
				'message' => $this->last_error ?? __('MCP tool call failed.', 'wp-ai-chatbot'),
			];
		}

		if (isset($response['error'])) {
			return [
				'ok' => false,
				'output' => '',
				'error' => 'server_error',
				'message' => (string) ($response['error']['message'] ?? 'Unknown MCP error'),
			];
		}

		$mcp_content = $response['result']['content'] ?? [];
		$text_output = $this->extract_text_from_content($mcp_content);

		return [
			'ok' => true,
			'output' => $text_output,
			'content' => $mcp_content, // full MCP content array for advanced usage
		];
	}

	// =========================================================================
	// Diagnostics
	// =========================================================================

	/**
	 * Return the server info from the initialize response.
	 *
	 * @return array|null
	 */
	public function get_server_info(): ?array
	{
		return $this->server_info;
	}

	/**
	 * Return the server capabilities from the initialize response.
	 *
	 * @return array
	 */
	public function get_server_caps(): array
	{
		return $this->server_caps;
	}

	/**
	 * Return the last transport error message.
	 *
	 * @return string|null
	 */
	public function get_last_error(): ?string
	{
		return $this->last_error;
	}

	// =========================================================================
	// JSON-RPC transport
	// =========================================================================

	/**
	 * Send a JSON-RPC 2.0 request to the MCP server.
	 *
	 * @param  string       $method         RPC method name.
	 * @param  array|object $params         RPC params.
	 * @param  bool         $notification   If true, send as a notification (no id, no response expected).
	 * @return array|null   Decoded response, or null on transport/parse error.
	 */
	private function send_rpc(string $method, $params, bool $notification = false): ?array
	{
		$this->last_error = null;
		$url = rtrim((string) ($this->config['url'] ?? ''), '/');

		if ('' === $url) {
			$this->last_error = 'MCP server URL is not configured.';

			return null;
		}

		$payload = ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params];

		if (! $notification) {
			$payload['id'] = ++$this->rpc_id;
		}

		$timeout = min((int) ($this->config['timeout'] ?? self::DEFAULT_TIMEOUT), 30);

		$base_headers = [
			'Content-Type' => 'application/json',
			'Accept' => 'application/json',
			'User-Agent' => 'WP-AI-Chatbot-MCP/' . (defined('WPAIC_VERSION') ? WPAIC_VERSION : '1.0.0'),
		];

		$custom_headers = $this->config['headers'] ?? [];
		if (is_string($custom_headers)) {
			$custom_headers = json_decode($custom_headers, true) ?: [];
		}

		$headers = array_merge($base_headers, (array) $custom_headers);

		$response = wp_remote_post($url, [
			'body' => wp_json_encode($payload),
			'headers' => $headers,
			'timeout' => $timeout,
			'sslverify' => true,
		]);

		if (is_wp_error($response)) {
			$this->last_error = $response->get_error_message();

			return null;
		}

		if ($notification) {
			return []; // Notifications have no meaningful response.
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = (string) wp_remote_retrieve_body($response);

		if ($code < 200 || $code >= 300) {
			$this->last_error = sprintf('HTTP %d from MCP server.', $code);

			return null;
		}

		$decoded = json_decode($body, true);

		if (! is_array($decoded)) {
			$this->last_error = 'Invalid JSON response from MCP server.';

			return null;
		}

		return $decoded;
	}

	/**
	 * Extract concatenated text from an MCP content array.
	 *
	 * @param  array $content MCP content items [{type:'text', text:'...'}, ...].
	 * @return string
	 */
	private function extract_text_from_content(array $content): string
	{
		$parts = [];

		foreach ($content as $item) {
			if (! is_array($item)) {
				continue;
			}

			$type = $item['type'] ?? 'text';

			if ('text' === $type && isset($item['text'])) {
				$parts[] = (string) $item['text'];
			} elseif ('resource' === $type && isset($item['resource']['text'])) {
				$parts[] = (string) $item['resource']['text'];
			}
		}

		return implode("\n", $parts);
	}
}
