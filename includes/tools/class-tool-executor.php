<?php

/**
 * Tool Executor — executes tools with permission checking and structured audit logging.
 *
 * Responsibilities:
 *   - Validate caller has permission to invoke the tool.
 *   - Call tool->execute() and capture result.
 *   - Truncate excessively large outputs (SSRF/exfiltration guard).
 *   - Write structured audit log entry for every execution (success or failure).
 *   - Fire wpaic_tool_executed action for cost accounting and side-effects.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Tools;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Tool_Executor
 *
 * @since 1.0.0
 * @package WPAIC\Tools
 */
final class Tool_Executor
{
	/** Maximum tool output length returned to the LLM (characters). */
	public const MAX_OUTPUT_LENGTH = 8000;

	/** @var Tool_Executor|null */
	private static $instance = null;

	/** Private constructor — use instance(). */
	private function __construct()
	{
	}

	/**
	 * Return the singleton instance.
	 *
	 * @return Tool_Executor
	 */
	public static function instance(): self
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	// =========================================================================
	// Execution
	// =========================================================================

	/**
	 * Execute a single tool call.
	 *
	 * @param  string $tool_id  Registered tool ID.
	 * @param  array  $args     Arguments (from LLM tool_call).
	 * @param  array  $context  Execution context {session_id, bot_slug, user_id, ...}.
	 * @return array  {ok, output, error?, message?, duration_ms}
	 */
	public function execute(string $tool_id, array $args, array $context): array
	{
		$registry = Tool_Registry::instance();
		$tool = $registry->get($tool_id);

		if (! $tool) {
			$result = [
				'ok' => false,
				'output' => '',
				'error' => 'tool_not_found',
				'message' => sprintf('Tool not found: %s', $tool_id),
			];
			$this->write_audit_log($tool_id, $args, $context, $result, 'not_found');

			return $result;
		}

		// Permission check before executing.
		$perm = $this->check_permission($tool, $context);
		if (! $perm['ok']) {
			$this->write_audit_log($tool_id, $args, $context, $perm, 'permission_denied');

			return array_merge($perm, ['duration_ms' => 0]);
		}

		/**
		 * Filter: allow extensions to modify or override tool arguments before execution.
		 *
		 * @since 1.0.0
		 * @param array  $args    Tool arguments.
		 * @param string $tool_id Tool identifier.
		 * @param array  $context Execution context.
		 */
		$args = (array) apply_filters('wpaic_tool_args', $args, $tool_id, $context);

		$started = microtime(true);

		try {
			$result = $tool->execute($args, $context);
		} catch (\Exception $e) {
			$result = [
				'ok' => false,
				'output' => '',
				'error' => 'exception',
				'message' => $e->getMessage(),
			];
		}

		$elapsed_ms = (int) round((microtime(true) - $started) * 1000);
		$result['duration_ms'] = $elapsed_ms;

		// Truncate oversized outputs to prevent exfiltration / context overload.
		if (isset($result['output']) && mb_strlen((string) $result['output']) > self::MAX_OUTPUT_LENGTH) {
			$result['output'] = mb_substr((string) $result['output'], 0, self::MAX_OUTPUT_LENGTH) . ' [truncated]';
		}

		// Ensure output is always a string.
		if (! isset($result['output'])) {
			$result['output'] = '';
		}

		$outcome = (bool) ($result['ok'] ?? false) ? 'success' : 'failure';
		$this->write_audit_log($tool_id, $args, $context, $result, $outcome);

		/**
		 * Fires after a tool is executed.
		 *
		 * @since 1.0.0
		 * @param string $tool_id  Tool ID.
		 * @param array  $args     Arguments passed to the tool.
		 * @param array  $result   Execution result.
		 * @param array  $context  Execution context.
		 */
		do_action('wpaic_tool_executed', $tool_id, $args, $result, $context);

		return $result;
	}

	/**
	 * Execute a batch of tool calls as returned by a provider.
	 *
	 * Accepts both OpenAI-style (function.name / function.arguments) and
	 * Claude-style (name / input) call objects.
	 *
	 * @param  array $tool_calls Array of provider tool_call objects.
	 * @param  array $context    Execution context.
	 * @return array  Array of {call_id, tool_id, result} for each call.
	 */
	public function execute_batch(array $tool_calls, array $context): array
	{
		$results = [];

		foreach ($tool_calls as $call) {
			// Normalise call format (OpenAI vs Claude vs generic).
			$call_id = (string) ($call['id'] ?? '');
			$name = (string) ($call['name'] ?? $call['function']['name'] ?? '');
			$raw = $call['arguments'] ?? $call['function']['arguments'] ?? $call['input'] ?? '{}';
			$args = is_string($raw) ? (json_decode($raw, true) ?: []) : (array) $raw;

			if ('' === $name) {
				continue;
			}

			$result = $this->execute($name, $args, $context);
			$results[] = [
				'call_id' => $call_id,
				'tool_id' => $name,
				'result' => $result,
			];
		}

		return $results;
	}

	// =========================================================================
	// Permission model
	// =========================================================================

	/**
	 * Check whether the current request context is allowed to run the tool.
	 *
	 * @param  Tool_Interface $tool
	 * @param  array          $context
	 * @return array {ok: bool, output: '', error?: string, message?: string}
	 */
	private function check_permission(Tool_Interface $tool, array $context): array
	{
		$permission = $tool->get_permission();

		switch ($permission) {
			case 'none':
				return ['ok' => true, 'output' => ''];

			case 'user':
				$uid = (int) ($context['user_id'] ?? 0);
				if (! $uid && ! is_user_logged_in()) {
					return [
						'ok' => false,
						'output' => '',
						'error' => 'permission_denied',
						'message' => __('This action requires you to be logged in.', 'wp-ai-chatbot'),
					];
				}

				return ['ok' => true, 'output' => ''];

			case 'admin':
				if (! current_user_can('manage_options')) {
					return [
						'ok' => false,
						'output' => '',
						'error' => 'permission_denied',
						'message' => __('This action requires administrator access.', 'wp-ai-chatbot'),
					];
				}

				return ['ok' => true, 'output' => ''];

			default:
				/**
				 * Filter: handle custom permission levels.
				 *
				 * @since 1.0.0
				 * @param bool   $allowed    Whether execution is allowed.
				 * @param string $tool_id    Tool ID.
				 * @param string $permission Required permission string.
				 * @param array  $context    Execution context.
				 */
				$allowed = (bool) apply_filters(
					'wpaic_tool_permission_check',
					false,
					$tool->get_id(),
					$permission,
					$context
				);

				if (! $allowed) {
					return [
						'ok' => false,
						'output' => '',
						'error' => 'permission_denied',
						'message' => __('You do not have permission to use this action.', 'wp-ai-chatbot'),
					];
				}

				return ['ok' => true, 'output' => ''];
		}
	}

	// =========================================================================
	// Audit logging
	// =========================================================================

	/**
	 * Write a structured audit log entry for this tool execution.
	 *
	 * Uses wpaic_structured_log (subsystem=audit) so entries appear in the
	 * audit log query alongside security events.
	 *
	 * Sensitive argument keys (password, key, secret, token, auth) are redacted.
	 *
	 * @param  string $tool_id  Tool ID.
	 * @param  array  $args     Arguments (will be key-redacted).
	 * @param  array  $context  Execution context.
	 * @param  array  $result   Execution result.
	 * @param  string $outcome  'success' | 'failure' | 'not_found' | 'permission_denied'.
	 * @return void
	 */
	private function write_audit_log(
		string $tool_id,
		array $args,
		array $context,
		array $result,
		string $outcome
	): void {
		if (! function_exists('wpaic_structured_log')) {
			return;
		}

		wpaic_structured_log('audit', 'info', 'tool_executed', [
			'tool_id' => $tool_id,
			'outcome' => $outcome,
			'args_keys' => array_keys($this->redact_args($args)),
			'session_id' => $context['session_id'] ?? '',
			'bot_slug' => $context['bot_slug'] ?? '',
			'duration_ms' => $result['duration_ms'] ?? 0,
			'error' => $result['error'] ?? '',
		]);
	}

	/**
	 * Redact sensitive keys from arguments before logging.
	 *
	 * @param  array $args
	 * @return array
	 */
	private function redact_args(array $args): array
	{
		$sensitive = ['password', 'key', 'secret', 'token', 'auth', 'api_key', 'apikey'];

		foreach ($args as $k => $v) {
			foreach ($sensitive as $s) {
				if (false !== stripos((string) $k, $s)) {
					$args[$k] = '[REDACTED]';
					break;
				}
			}
		}

		return $args;
	}
}
