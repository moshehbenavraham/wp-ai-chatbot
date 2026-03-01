<?php

/**
 * MCP Registry — manages configured MCP server entries and their tool adapters.
 *
 * MCP server configurations are stored in the `wpaic_mcp_servers` WP option as a
 * JSON-encoded array of server objects. On first access the registry loads all
 * enabled servers and creates Mcp_Tool adapter tools that are registered with the
 * Tool_Registry via the `wpaic_register_tools` action.
 *
 * Server config shape:
 *   id      — unique snake_case identifier (required)
 *   name    — human-readable name (required)
 *   url     — MCP server endpoint URL (required)
 *   headers — JSON object of extra HTTP headers (optional; use for Authorization)
 *   timeout — request timeout in seconds (default: 15)
 *   enabled — bool (default: true)
 *
 * Filter points:
 *   wpaic_mcp_server_config — modify a single server config before use
 *   wpaic_mcp_servers       — modify the full servers list after loading
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Mcp;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Mcp_Registry
 *
 * @since 1.0.0
 * @package WPAIC\Mcp
 */
final class Mcp_Registry
{
	/** WP option key for server configurations. */
	public const OPTION_KEY = 'wpaic_mcp_servers';

	/** WP option key for cached tool definitions (per server). */
	public const TOOLS_OPTION_PREFIX = 'wpaic_mcp_tools_';

	/** @var Mcp_Registry|null */
	private static $instance = null;

	/** @var array<string, Mcp_Client> server_id => client */
	private $clients = [];

	/** Private constructor — use instance(). */
	private function __construct()
	{
	}

	/**
	 * Return the singleton instance.
	 *
	 * @return Mcp_Registry
	 */
	public static function instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// =========================================================================
	// Server management
	// =========================================================================

	/**
	 * Return all configured server entries (enabled + disabled).
	 *
	 * @return array<int, array>
	 */
	public function get_servers(): array
	{
		$raw = get_option(self::OPTION_KEY, []);
		$servers = is_string($raw) ? (json_decode($raw, true) ?: []) : (array) $raw;

		/**
		 * Filter: modify the full MCP server list.
		 *
		 * @since 1.0.0
		 * @param array $servers Loaded server configurations.
		 */
		return (array) apply_filters('wpaic_mcp_servers', $servers);
	}

	/**
	 * Return all enabled server entries.
	 *
	 * @return array<int, array>
	 */
	public function get_enabled_servers(): array
	{
		return array_values(array_filter(
			$this->get_servers(),
			static function (array $s): bool {
				return (bool) ($s['enabled'] ?? true);
			}
		));
	}

	/**
	 * Save the servers list to the WP option.
	 *
	 * @param  array $servers
	 * @return bool
	 */
	public function save_servers(array $servers): bool
	{
		return update_option(self::OPTION_KEY, $servers);
	}

	/**
	 * Add or update a server by ID.
	 *
	 * @param  array $server Server config array (must include 'id').
	 * @return bool
	 */
	public function upsert_server(array $server): bool
	{
		$id = sanitize_key($server['id'] ?? '');
		$servers = $this->get_servers();

		if ('' === $id) {
			return false;
		}

		// Sanitise required fields.
		$server['id'] = $id;
		$server['name'] = sanitize_text_field($server['name'] ?? $id);
		$server['url'] = esc_url_raw($server['url'] ?? '');
		$server['enabled'] = (bool) ($server['enabled'] ?? true);
		$server['timeout'] = max(5, min(30, (int) ($server['timeout'] ?? 15)));

		// Merge into existing list.
		$found = false;
		foreach ($servers as $i => $existing) {
			if (sanitize_key($existing['id'] ?? '') === $id) {
				$servers[$i] = array_merge($existing, $server);
				$found = true;
				break;
			}
		}

		if (! $found) {
			$servers[] = $server;
		}

		return $this->save_servers($servers);
	}

	/**
	 * Remove a server by ID. Also deletes its cached tool definitions.
	 *
	 * @param  string $server_id
	 * @return bool
	 */
	public function delete_server(string $server_id): bool
	{
		$id = sanitize_key($server_id);
		$servers = $this->get_servers();

		$servers = array_values(array_filter(
			$servers,
			static function (array $s) use ($id): bool {
				return sanitize_key($s['id'] ?? '') !== $id;
			}
		));

		// Clear cached tool list and auth credentials for this server.
		delete_option(self::TOOLS_OPTION_PREFIX . $id);
		$this->delete_auth($id);

		return $this->save_servers($servers);
	}

	// =========================================================================
	// Client management
	// =========================================================================

	/**
	 * Return (or lazily create) an Mcp_Client for a given server ID.
	 *
	 * Auth headers (encrypted) are resolved from Settings_Manager and merged
	 * into the client config so no plaintext credentials are stored in options.
	 *
	 * @param  string $server_id
	 * @return Mcp_Client|null  Null if the server is not found or disabled.
	 */
	public function get_client(string $server_id): ?Mcp_Client
	{
		$id = sanitize_key($server_id);

		if (isset($this->clients[$id])) {
			return $this->clients[$id];
		}

		// Find server config.
		$config = null;
		foreach ($this->get_enabled_servers() as $s) {
			if (sanitize_key($s['id'] ?? '') === $id) {
				$config = $s;
				break;
			}
		}

		if (null === $config) {
			return null;
		}

		/**
		 * Filter: modify server config before creating a client.
		 *
		 * @since 1.0.0
		 * @param array  $config    Server configuration.
		 * @param string $server_id Server identifier.
		 */
		$config = (array) apply_filters('wpaic_mcp_server_config', $config, $id);

		// Merge decrypted auth headers (Phase 17). These override any header
		// values stored in plain text in the server config array.
		$auth_headers = $this->get_auth_headers($id);
		if (! empty($auth_headers)) {
			$existing_headers = $config['headers'] ?? [];
			if (is_string($existing_headers)) {
				$existing_headers = json_decode($existing_headers, true) ?: [];
			}
			$config['headers'] = array_merge((array) $existing_headers, $auth_headers);
		}

		$this->clients[$id] = new Mcp_Client($config);

		return $this->clients[$id];
	}

	// =========================================================================
	// Auth header management (Phase 17)
	// =========================================================================

	/**
	 * Resolve decrypted auth headers for a server.
	 *
	 * Auth configuration is stored as two separate pieces:
	 *   1. Non-sensitive: auth_type + auth_header_name in the server config array.
	 *   2. Sensitive: the header VALUE is encrypted via Settings_Manager under
	 *      `wpaic_mcp_auth_value_{server_id}`.
	 *
	 * Supported auth_type values:
	 *   'none'     — no auth headers injected
	 *   'api_key'  — injects `{auth_header_name}: {decrypted_value}` (e.g. X-API-Key)
	 *   'bearer'   — injects `Authorization: Bearer {decrypted_value}`
	 *   'custom'   — injects `{auth_header_name}: {decrypted_value}`
	 *
	 * @param  string $server_id
	 * @return array  Header key → value map (empty if auth not configured or no value).
	 */
	public function get_auth_headers(string $server_id): array
	{
		$id = sanitize_key($server_id);
		$servers = $this->get_servers();

		$auth_type = 'none';
		$auth_header_name = '';

		foreach ($servers as $s) {
			if (sanitize_key($s['id'] ?? '') === $id) {
				$auth_type = (string) ($s['auth_type'] ?? 'none');
				$auth_header_name = (string) ($s['auth_header_name'] ?? '');
				break;
			}
		}

		if ('none' === $auth_type) {
			return [];
		}

		// Decrypt the stored auth value.
		$option_key = "wpaic_mcp_auth_value_{$id}";

		if (class_exists('WPAIC\Admin\Settings_Manager')) {
			$value = (string) \WPAIC\Admin\Settings_Manager::get($option_key, '');
		} else {
			$value = (string) get_option($option_key, '');
		}

		if ('' === $value) {
			return [];
		}

		if ('bearer' === $auth_type) {
			return ['Authorization' => 'Bearer ' . $value];
		}

		// api_key or custom: use the configured header name.
		$header_name = '' !== $auth_header_name ? $auth_header_name : 'Authorization';

		return [$header_name => $value];
	}

	/**
	 * Save auth configuration for a server.
	 *
	 * The header NAME and auth_type are stored in the server config (non-sensitive).
	 * The header VALUE is stored encrypted via Settings_Manager.
	 *
	 * @param  string $server_id   Server ID.
	 * @param  string $auth_type   'none' | 'api_key' | 'bearer' | 'custom'
	 * @param  string $header_name Header name (for api_key/custom types; ignored for bearer).
	 * @param  string $value       Secret value (will be encrypted).
	 * @return bool
	 */
	public function save_auth(string $server_id, string $auth_type, string $header_name, string $value): bool
	{
		$id = sanitize_key($server_id);

		if ('' === $id) {
			return false;
		}

		$valid_types = ['none', 'api_key', 'bearer', 'custom'];
		$auth_type = in_array($auth_type, $valid_types, true) ? $auth_type : 'none';

		// Persist non-sensitive config into the server record.
		$servers = $this->get_servers();
		$found = false;

		foreach ($servers as $i => $existing) {
			if (sanitize_key($existing['id'] ?? '') === $id) {
				$servers[$i]['auth_type'] = $auth_type;
				$servers[$i]['auth_header_name'] = sanitize_text_field($header_name);
				$found = true;
				break;
			}
		}

		if (! $found) {
			return false; // Server must exist before saving auth.
		}

		// Invalidate cached client so it will be rebuilt with new headers.
		unset($this->clients[$id]);

		$option_key = "wpaic_mcp_auth_value_{$id}";

		// If 'none', clear the stored value.
		if ('none' === $auth_type || '' === $value) {
			// Clear the encrypted secret.
			delete_option($option_key);

			// Clear auth config from server record too.
			foreach ($servers as $i => $existing) {
				if (sanitize_key($existing['id'] ?? '') === $id) {
					unset($servers[$i]['auth_type'], $servers[$i]['auth_header_name']);
					break;
				}
			}

			return $this->save_servers($servers);
		}

		// Persist the server config updates (auth_type + header_name).
		$saved_config = $this->save_servers($servers);

		// Encrypt and save the secret value via Settings_Manager.
		if (class_exists('WPAIC\Admin\Settings_Manager')) {
			\WPAIC\Admin\Settings_Manager::get_instance()->set($option_key, $value);
		} else {
			update_option($option_key, $value, false);
		}

		return $saved_config;
	}

	/**
	 * Remove all auth configuration for a server (called on server delete).
	 *
	 * @param  string $server_id
	 * @return void
	 */
	public function delete_auth(string $server_id): void
	{
		$id = sanitize_key($server_id);
		$option_key = "wpaic_mcp_auth_value_{$id}";

		delete_option($option_key);
		unset($this->clients[$id]);
	}

	// =========================================================================
	// Tool discovery and caching
	// =========================================================================

	/**
	 * Discover tools from a server and cache them in a WP option.
	 *
	 * Called when:
	 *   - Admin adds/saves a server (explicit sync).
	 *   - Admin clicks "Test Connection".
	 *
	 * @param  string $server_id
	 * @return array|null  Array of MCP tool definitions, or null on failure.
	 */
	public function sync_server_tools(string $server_id): ?array
	{
		$client = $this->get_client($server_id);

		if (null === $client) {
			return null;
		}

		$tools = $client->list_tools();

		if (null === $tools) {
			return null;
		}

		// Cache tool definitions.
		update_option(self::TOOLS_OPTION_PREFIX . sanitize_key($server_id), $tools, false);

		return $tools;
	}

	/**
	 * Return cached tool definitions for a server (DB read, no live connection).
	 *
	 * @param  string $server_id
	 * @return array  Array of MCP tool definitions (may be empty if not synced yet).
	 */
	public function get_cached_tools(string $server_id): array
	{
		$raw = get_option(self::TOOLS_OPTION_PREFIX . sanitize_key($server_id), []);

		return is_array($raw) ? $raw : [];
	}

	/**
	 * Register all cached MCP tools with the Tool_Registry.
	 *
	 * Called from the `wpaic_register_tools` action. Uses cached tool definitions
	 * so no live MCP connection is needed on every page load.
	 *
	 * @param  \WPAIC\Tools\Tool_Registry $registry
	 * @return void
	 */
	public function register_tools_with_registry(\WPAIC\Tools\Tool_Registry $registry): void
	{
		foreach ($this->get_enabled_servers() as $server) {
			$server_id = sanitize_key($server['id'] ?? '');

			if ('' === $server_id) {
				continue;
			}

			$tools = $this->get_cached_tools($server_id);

			foreach ($tools as $tool_def) {
				if (! isset($tool_def['name'])) {
					continue;
				}

				$adapter = new Mcp_Tool_Adapter($tool_def, $server_id, $this);
				$registry->register($adapter);
			}
		}
	}
}
