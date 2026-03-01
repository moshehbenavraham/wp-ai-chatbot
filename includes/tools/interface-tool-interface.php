<?php

/**
 * Tool Interface — contract for all tool/action implementations.
 *
 * A Tool is any callable capability the AI can invoke during a conversation:
 * built-in functions, webhook integrations, or MCP-connected services.
 *
 * JSON Schema contract: schemas must declare type=object + properties.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Tools;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Interface Tool_Interface
 *
 * @since 1.0.0
 * @package WPAIC\Tools
 */
interface Tool_Interface
{
	/**
	 * Unique identifier (snake_case, 2-64 chars, [a-z0-9_]).
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable name (shown in admin UI and audit logs).
	 *
	 * @return string
	 */
	public function get_name(): string;

	/**
	 * Description passed to the LLM to explain what this tool does (max 600 chars).
	 *
	 * @return string
	 */
	public function get_description(): string;

	/**
	 * JSON Schema (type=object) describing input parameters.
	 *
	 * @return array Associative array that serialises to a valid JSON Schema object.
	 */
	public function get_schema(): array;

	/**
	 * Minimum permission required to execute this tool.
	 *
	 * Allowed values:
	 *   'none'  — any visitor (anonymous or logged-in)
	 *   'user'  — logged-in WordPress users only
	 *   'admin' — users with manage_options only
	 *   Any other string is passed to the `wpaic_tool_permission_check` filter.
	 *
	 * @return string
	 */
	public function get_permission(): string;

	/**
	 * Execute the tool.
	 *
	 * @param  array $args    Parsed arguments (validated against schema by caller).
	 * @param  array $context Execution context {session_id, bot_slug, user_id, page_url, ...}.
	 * @return array {
	 *     @type bool   $ok      Whether execution succeeded.
	 *     @type string $output  Text result returned to the LLM.
	 *     @type string $error   Short error code on failure (optional).
	 *     @type string $message Human-readable error message (optional).
	 * }
	 */
	public function execute(array $args, array $context): array;

	/**
	 * Return the normalised provider definition for inclusion in API calls.
	 *
	 * Format:
	 *   name        — sanitised identifier
	 *   description — max-600-char description
	 *   parameters  — JSON Schema object
	 *
	 * @return array
	 */
	public function to_provider_definition(): array;
}
