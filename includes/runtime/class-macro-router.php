<?php

/**
 * Macro Router — pattern-based message routing before AI provider dispatch.
 *
 * Rules are evaluated in priority order (lowest integer = highest priority).
 * When an enabled rule matches, it either returns a canned response
 * (direct_response) or executes a registered tool (call_tool) and returns
 * its output — bypassing the AI provider entirely for that message.
 *
 * Rule structure (stored in `wpaic_routing_rules` WP option):
 *   id           — unique snake_case identifier (auto-generated on first upsert)
 *   name         — human-readable rule name
 *   pattern      — substring keyword or PHP regex
 *   pattern_type — 'keyword' (case-insensitive substr) | 'regex' (full PHP regex)
 *   action_type  — 'direct_response' | 'call_tool'
 *   action_data  — response text (direct_response) or tool ID (call_tool)
 *   priority     — integer 1–999 (1 = highest)
 *   enabled      — bool
 *
 * Filter points:
 *   wpaic_macro_route(array $intercept, string $message, array $pre, array $context)
 *     — Return array with non-empty 'message' key to short-circuit provider dispatch.
 *       Passes through if $intercept['message'] is already set (earlier filter wins).
 *
 * Global on/off: `wpaic_routing_enabled` option (int 0|1, default 0 — opt-in).
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Runtime;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Macro_Router
 *
 * @since 1.0.0
 * @package WPAIC\Runtime
 */
class Macro_Router
{
	/** WP option key for routing rules. */
	public const OPTION_KEY = 'wpaic_routing_rules';

	// =========================================================================
	// Rule storage
	// =========================================================================

	/**
	 * Return all routing rules ordered by priority ASC.
	 *
	 * @return array<int, array>
	 */
	public static function get_rules(): array
	{
		$raw = get_option(self::OPTION_KEY, []);
		$rules = is_array($raw) ? $raw : [];

		usort($rules, static function (array $a, array $b): int {
			return (int) ($a['priority'] ?? 10) <=> (int) ($b['priority'] ?? 10);
		});

		return $rules;
	}

	/**
	 * Persist the rules list.
	 *
	 * @param  array $rules
	 * @return bool
	 */
	public static function save_rules(array $rules): bool
	{
		return (bool) update_option(self::OPTION_KEY, array_values($rules));
	}

	/**
	 * Add or update a rule by ID. Generates a unique ID when $rule['id'] is empty.
	 *
	 * @param  array $rule
	 * @return string Rule ID on success, empty string on failure.
	 */
	public static function upsert_rule(array $rule): string
	{
		$id = sanitize_key($rule['id'] ?? '');

		if ('' === $id) {
			$id = 'rule_' . substr(wp_generate_uuid4(), 0, 8);
		}

		$rule = [
			'id' => $id,
			'name' => sanitize_text_field($rule['name'] ?? $id),
			'pattern' => (string) ($rule['pattern'] ?? ''),
			'pattern_type' => in_array($rule['pattern_type'] ?? '', ['keyword', 'regex'], true)
				? $rule['pattern_type'] : 'keyword',
			'action_type' => in_array($rule['action_type'] ?? '', ['direct_response', 'call_tool'], true)
				? $rule['action_type'] : 'direct_response',
			'action_data' => sanitize_textarea_field($rule['action_data'] ?? ''),
			'priority' => max(1, min(999, (int) ($rule['priority'] ?? 10))),
			'enabled' => (bool) ($rule['enabled'] ?? true),
		];

		if ('' === $rule['pattern'] || '' === $rule['action_data']) {
			return ''; // Reject incomplete rules.
		}

		$rules = self::get_rules();
		$found = false;

		foreach ($rules as $i => $existing) {
			if (($existing['id'] ?? '') === $id) {
				$rules[$i] = array_merge($existing, $rule);
				$found = true;
				break;
			}
		}

		if (! $found) {
			$rules[] = $rule;
		}

		return self::save_rules($rules) ? $id : '';
	}

	/**
	 * Delete a rule by ID.
	 *
	 * @param  string $id
	 * @return bool True if the rule was found and removed.
	 */
	public static function delete_rule(string $id): bool
	{
		$id = sanitize_key($id);
		$rules = self::get_rules();

		$filtered = array_values(array_filter(
			$rules,
			static function (array $r) use ($id): bool {
				return ($r['id'] ?? '') !== $id;
			}
		));

		if (count($filtered) === count($rules)) {
			return false; // Not found.
		}

		return self::save_rules($filtered);
	}

	// =========================================================================
	// Matching
	// =========================================================================

	/**
	 * Find the first enabled rule matching $message (evaluated in priority order).
	 *
	 * @param  string $message Incoming user message.
	 * @return array|null Matching rule array or null if no rule matches.
	 */
	public static function match(string $message): ?array
	{
		foreach (self::get_rules() as $rule) {
			if (empty($rule['enabled'])) {
				continue;
			}

			if ('' === ($rule['pattern'] ?? '')) {
				continue;
			}

			if (self::matches_pattern($message, $rule['pattern'], $rule['pattern_type'] ?? 'keyword')) {
				return $rule;
			}
		}

		return null;
	}

	/**
	 * Test whether $message matches a given pattern.
	 *
	 * @param  string $message      User message.
	 * @param  string $pattern      Pattern string.
	 * @param  string $pattern_type 'keyword' | 'regex'
	 * @return bool
	 */
	private static function matches_pattern(string $message, string $pattern, string $pattern_type): bool
	{
		if ('regex' === $pattern_type) {
			// If the pattern has no delimiters, wrap it.
			if ('' !== $pattern && ! preg_match('/^[^a-zA-Z0-9\s\\\\]/', $pattern)) {
				$pattern = '/' . str_replace('/', '\\/', $pattern) . '/i';
			}

			// Suppress warnings for malformed patterns; treat as no-match.
			$result = @preg_match($pattern, $message);

			return 1 === $result;
		}

		// keyword: case-insensitive substring match.
		return false !== stripos($message, $pattern);
	}

	// =========================================================================
	// WordPress integration
	// =========================================================================

	/**
	 * Register WordPress hooks.
	 *
	 * Called from Plugin::register_core_tools() during the `init` action.
	 *
	 * @return void
	 */
	public static function register_hooks(): void
	{
		add_filter('wpaic_macro_route', [self::class, 'on_macro_route'], 10, 4);
	}

	/**
	 * Filter callback: `wpaic_macro_route`.
	 *
	 * Returns a non-empty intercept array (with 'message' key) to short-circuit
	 * provider dispatch, or passes through $intercept unchanged.
	 *
	 * @param  array  $intercept Existing intercept (earlier filter may have set it).
	 * @param  string $message   User message.
	 * @param  array  $pre       Pipeline pre-processing data.
	 * @param  array  $context   Pipeline context assembly data.
	 * @return array
	 */
	public static function on_macro_route(array $intercept, string $message, array $pre, array $context): array
	{
		// Already intercepted by an earlier filter — pass through.
		if (! empty($intercept['message'])) {
			return $intercept;
		}

		// Global routing must be enabled via admin option (opt-in).
		if (! (int) get_option('wpaic_routing_enabled', 0)) {
			return $intercept;
		}

		$rule = self::match($message);

		if (null === $rule) {
			return $intercept;
		}

		$response_text = '';

		if ('call_tool' === ($rule['action_type'] ?? '')) {
			$tool_id = sanitize_key($rule['action_data'] ?? '');

			if ('' !== $tool_id && class_exists('WPAIC\Tools\Tool_Registry')) {
				$tool = \WPAIC\Tools\Tool_Registry::instance()->get($tool_id);

				if ($tool) {
					try {
						$result = $tool->execute([]);

						if (is_string($result)) {
							$response_text = $result;
						} elseif (is_array($result)) {
							$response_text = (string) wp_json_encode($result);
						} else {
							$response_text = (string) $result;
						}
					} catch (\Throwable $e) {
						// Tool execution failed — fall through to provider.
						return $intercept;
					}
				}
			}

			// Tool not found or returned empty — fall through to provider.
			if ('' === $response_text) {
				return $intercept;
			}
		} else {
			// direct_response.
			$response_text = (string) ($rule['action_data'] ?? '');
		}

		if ('' === $response_text) {
			return $intercept;
		}

		return [
			'message' => $response_text,
			'usage' => [],
			'finish_reason' => 'macro_match',
			'model_id' => 'macro_router',
			'provider_id' => 'macro_router',
		];
	}
}
