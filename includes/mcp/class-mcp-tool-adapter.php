<?php

/**
 * MCP Tool Adapter — bridges a single MCP tool into the WPAIC Tool_Interface.
 *
 * Created by Mcp_Registry::register_tools_with_registry(). Each adapter wraps one
 * MCP tool definition and delegates execution to the Mcp_Client for that server.
 * IDs are prefixed with the server_id to prevent naming conflicts.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Mcp;

use WPAIC\Tools\Tool_Interface;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Mcp_Tool_Adapter
 *
 * @since 1.0.0
 * @package WPAIC\Mcp
 */
class Mcp_Tool_Adapter implements Tool_Interface
{
	/** @var array MCP tool definition {name, description, inputSchema}. */
	private $tool_def;

	/** @var string Server ID prefix. */
	private $server_id;

	/** @var Mcp_Registry Parent registry (used to obtain the client). */
	private $mcp_registry;

	/** @var string Sanitised composite tool ID (server_id + tool name). */
	private $id;

	/**
	 * Constructor.
	 *
	 * @param array        $tool_def     MCP tool definition.
	 * @param string       $server_id    MCP server identifier.
	 * @param Mcp_Registry $mcp_registry Parent MCP registry.
	 */
	public function __construct(array $tool_def, string $server_id, Mcp_Registry $mcp_registry)
	{
		$this->tool_def = $tool_def;
		$this->server_id = sanitize_key($server_id);
		$this->mcp_registry = $mcp_registry;

		// Build composite ID: server_id + '_' + sanitised_tool_name (max 64 chars).
		$raw_name = preg_replace('/[^a-z0-9_]/', '_', strtolower((string) ($tool_def['name'] ?? 'tool')));
		$raw_id = $this->server_id . '_' . $raw_name;
		$this->id = substr($raw_id, 0, 64);
	}

	// =========================================================================
	// Tool_Interface contract
	// =========================================================================

	public function get_id(): string
	{
		return $this->id;
	}

	public function get_name(): string
	{
		$server_name = '';
		foreach ($this->mcp_registry->get_servers() as $s) {
			if (sanitize_key($s['id'] ?? '') === $this->server_id) {
				$server_name = $s['name'] ?? $this->server_id;
				break;
			}
		}

		$tool_name = $this->tool_def['name'] ?? $this->id;

		return $server_name ? "{$server_name}: {$tool_name}" : $tool_name;
	}

	public function get_description(): string
	{
		return mb_substr((string) ($this->tool_def['description'] ?? ''), 0, 600);
	}

	public function get_schema(): array
	{
		// MCP uses 'inputSchema'; normalise to 'parameters'.
		$schema = $this->tool_def['inputSchema'] ?? $this->tool_def['parameters'] ?? [];

		if (! is_array($schema) || empty($schema)) {
			return ['type' => 'object', 'properties' => (object) []];
		}

		if (empty($schema['type'])) {
			$schema['type'] = 'object';
		}

		if (! isset($schema['properties']) || (is_array($schema['properties']) && empty($schema['properties']))) {
			$schema['properties'] = (object) [];
		}

		return $schema;
	}

	public function get_permission(): string
	{
		// MCP tools are available to all authenticated chat users by default.
		// Override via wpaic_tool_permission_check filter if needed.
		return 'none';
	}

	/**
	 * Execute the MCP tool via a live connection to the server.
	 *
	 * @param  array $args    Tool arguments.
	 * @param  array $context Execution context.
	 * @return array {ok, output, error?, message?}
	 */
	public function execute(array $args, array $context): array
	{
		$client = $this->mcp_registry->get_client($this->server_id);

		if (null === $client) {
			return [
				'ok' => false,
				'output' => '',
				'error' => 'server_not_found',
				'message' => sprintf(
					// translators: %s: server identifier.
					__('MCP server "%s" is not configured or is disabled.', 'wp-ai-chatbot'),
					$this->server_id
				),
			];
		}

		$local_name = (string) ($this->tool_def['name'] ?? '');

		return $client->execute_tool($local_name, $args);
	}

	public function to_provider_definition(): array
	{
		return [
			'name' => $this->get_id(),
			'description' => $this->get_description(),
			'parameters' => $this->get_schema(),
		];
	}
}
