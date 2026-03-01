<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use WPAIC\Runtime\Macro_Router;

/**
 * Tests for WPAIC\Runtime\Macro_Router
 *
 * Covers rule CRUD, keyword/regex pattern matching, priority ordering,
 * disabled-rule skipping, and the on_macro_route filter callback.
 */
final class MacroRouterTest extends TestCase
{
	private static bool $loaded = false;

	public static function setUpBeforeClass(): void
	{
		if (self::$loaded) {
			return;
		}

		require_once __DIR__ . '/stubs/class-wp-error.php';
		require_once __DIR__ . '/stubs/wp-core-stubs.php';

		self::$loaded = true;
	}

	protected function setUp(): void
	{
		\WpTestHelpers::reset();
	}

	// ── get_rules ─────────────────────────────────────────────────────────────

	public function testGetRulesReturnsEmptyArrayByDefault(): void
	{
		$this->assertSame([], Macro_Router::get_rules());
	}

	public function testGetRulesOrdersByPriorityAscending(): void
	{
		\WpTestHelpers::seed_option(Macro_Router::OPTION_KEY, [
			['id' => 'b', 'name' => 'B', 'pattern' => 'b', 'pattern_type' => 'keyword',
				'action_type' => 'direct_response', 'action_data' => 'B reply', 'priority' => 20, 'enabled' => true],
			['id' => 'a', 'name' => 'A', 'pattern' => 'a', 'pattern_type' => 'keyword',
				'action_type' => 'direct_response', 'action_data' => 'A reply', 'priority' => 5, 'enabled' => true],
		]);

		$rules = Macro_Router::get_rules();

		$this->assertSame('a', $rules[0]['id']);
		$this->assertSame('b', $rules[1]['id']);
	}

	// ── upsert_rule ───────────────────────────────────────────────────────────

	public function testUpsertRuleRejectsIncompleteRuleMissingPattern(): void
	{
		$id = Macro_Router::upsert_rule([
			'pattern' => '',
			'action_data' => 'response text',
		]);

		$this->assertSame('', $id, 'Missing pattern should return empty string.');
	}

	public function testUpsertRuleRejectsIncompleteRuleMissingActionData(): void
	{
		$id = Macro_Router::upsert_rule([
			'pattern' => 'hello',
			'action_data' => '',
		]);

		$this->assertSame('', $id, 'Missing action_data should return empty string.');
	}

	public function testUpsertRuleGeneratesIdWhenEmpty(): void
	{
		$id = Macro_Router::upsert_rule([
			'id' => '',
			'pattern' => 'hello',
			'action_data' => 'Hi there!',
		]);

		$this->assertNotSame('', $id);
		$this->assertStringStartsWith('rule_', $id);
	}

	public function testUpsertRuleAddsRuleToList(): void
	{
		$id = Macro_Router::upsert_rule([
			'pattern' => 'hello',
			'action_data' => 'Hi there!',
		]);

		$rules = Macro_Router::get_rules();

		$this->assertCount(1, $rules);
		$this->assertSame($id, $rules[0]['id']);
	}

	public function testUpsertRuleUpdatesExistingRuleByIdNoDuplicate(): void
	{
		$id = Macro_Router::upsert_rule([
			'pattern' => 'hello',
			'action_data' => 'First',
		]);

		Macro_Router::upsert_rule([
			'id' => $id,
			'pattern' => 'hello',
			'action_data' => 'Updated',
		]);

		$rules = Macro_Router::get_rules();

		$this->assertCount(1, $rules, 'Updating by ID must not create a duplicate.');
		$this->assertSame('Updated', $rules[0]['action_data']);
	}

	public function testUpsertRuleClampsPriorityBounds(): void
	{
		$id = Macro_Router::upsert_rule([
			'pattern' => 'test',
			'action_data' => 'reply',
			'priority' => 9999,
		]);

		$rules = Macro_Router::get_rules();
		$rule = $rules[0];

		$this->assertSame(999, $rule['priority'], 'Priority 9999 must be clamped to 999.');
	}

	// ── delete_rule ───────────────────────────────────────────────────────────

	public function testDeleteRuleRemovesRuleById(): void
	{
		$id = Macro_Router::upsert_rule([
			'pattern' => 'bye',
			'action_data' => 'Goodbye!',
		]);

		$deleted = Macro_Router::delete_rule($id);

		$this->assertTrue($deleted);
		$this->assertSame([], Macro_Router::get_rules());
	}

	public function testDeleteRuleReturnsFalseForUnknownId(): void
	{
		$this->assertFalse(Macro_Router::delete_rule('nonexistent-id'));
	}

	// ── match — keyword ───────────────────────────────────────────────────────

	public function testMatchKeywordCaseInsensitive(): void
	{
		\WpTestHelpers::seed_option(Macro_Router::OPTION_KEY, [
			['id' => 'r1', 'name' => 'R1', 'pattern' => 'HELLO', 'pattern_type' => 'keyword',
				'action_type' => 'direct_response', 'action_data' => 'Hi!', 'priority' => 10, 'enabled' => true],
		]);

		$rule = Macro_Router::match('hello world');
		$this->assertNotNull($rule);
		$this->assertSame('r1', $rule['id']);
	}

	public function testMatchKeywordNoMatch(): void
	{
		\WpTestHelpers::seed_option(Macro_Router::OPTION_KEY, [
			['id' => 'r1', 'name' => 'R1', 'pattern' => 'hello', 'pattern_type' => 'keyword',
				'action_type' => 'direct_response', 'action_data' => 'Hi!', 'priority' => 10, 'enabled' => true],
		]);

		$this->assertNull(Macro_Router::match('goodbye world'));
	}

	// ── match — regex ─────────────────────────────────────────────────────────

	public function testMatchRegexWithDelimiters(): void
	{
		\WpTestHelpers::seed_option(Macro_Router::OPTION_KEY, [
			['id' => 'rx1', 'name' => 'RX1', 'pattern' => '/^hello/i', 'pattern_type' => 'regex',
				'action_type' => 'direct_response', 'action_data' => 'Hey!', 'priority' => 10, 'enabled' => true],
		]);

		$this->assertNotNull(Macro_Router::match('Hello there!'));
		$this->assertNull(Macro_Router::match('Say hello'));
	}

	public function testMatchRegexAutoDelimited(): void
	{
		\WpTestHelpers::seed_option(Macro_Router::OPTION_KEY, [
			['id' => 'rx2', 'name' => 'RX2', 'pattern' => 'order\\s+#?\\d+', 'pattern_type' => 'regex',
				'action_type' => 'direct_response', 'action_data' => 'Order found.', 'priority' => 10, 'enabled' => true],
		]);

		$this->assertNotNull(Macro_Router::match('where is order #12345'));
	}

	public function testMatchRegexMalformedPatternDoesNotCrash(): void
	{
		\WpTestHelpers::seed_option(Macro_Router::OPTION_KEY, [
			['id' => 'rx3', 'name' => 'RX3', 'pattern' => '/[unclosed/', 'pattern_type' => 'regex',
				'action_type' => 'direct_response', 'action_data' => 'reply', 'priority' => 10, 'enabled' => true],
		]);

		// A malformed regex must silently return no match, not throw.
		$this->assertNull(Macro_Router::match('any message'));
	}

	// ── match — disabled rules ────────────────────────────────────────────────

	public function testMatchSkipsDisabledRules(): void
	{
		\WpTestHelpers::seed_option(Macro_Router::OPTION_KEY, [
			['id' => 'dis', 'name' => 'Disabled', 'pattern' => 'hello', 'pattern_type' => 'keyword',
				'action_type' => 'direct_response', 'action_data' => 'Reply', 'priority' => 1, 'enabled' => false],
		]);

		$this->assertNull(Macro_Router::match('hello world'));
	}

	// ── match — priority ordering ─────────────────────────────────────────────

	public function testMatchReturnsFirstByPriority(): void
	{
		\WpTestHelpers::seed_option(Macro_Router::OPTION_KEY, [
			['id' => 'low', 'name' => 'Low', 'pattern' => 'hello', 'pattern_type' => 'keyword',
				'action_type' => 'direct_response', 'action_data' => 'Low', 'priority' => 100, 'enabled' => true],
			['id' => 'high', 'name' => 'High', 'pattern' => 'hello', 'pattern_type' => 'keyword',
				'action_type' => 'direct_response', 'action_data' => 'High', 'priority' => 1, 'enabled' => true],
		]);

		$rule = Macro_Router::match('hello world');

		$this->assertNotNull($rule);
		$this->assertSame('high', $rule['id'], 'Lowest priority number (1) must win.');
	}

	// ── on_macro_route ────────────────────────────────────────────────────────

	public function testOnMacroRoutePassesThroughWhenRoutingDisabled(): void
	{
		// wpaic_routing_enabled = 0 (default) → pass through.
		\WpTestHelpers::seed_option(Macro_Router::OPTION_KEY, [
			['id' => 'r1', 'name' => 'R1', 'pattern' => 'hello', 'pattern_type' => 'keyword',
				'action_type' => 'direct_response', 'action_data' => 'Hi!', 'priority' => 10, 'enabled' => true],
		]);
		\WpTestHelpers::seed_option('wpaic_routing_enabled', 0);

		$result = Macro_Router::on_macro_route([], 'hello world', [], []);

		$this->assertArrayNotHasKey('message', $result);
	}

	public function testOnMacroRouteReturnsInterceptWhenEnabled(): void
	{
		\WpTestHelpers::seed_option(Macro_Router::OPTION_KEY, [
			['id' => 'r1', 'name' => 'R1', 'pattern' => 'hello', 'pattern_type' => 'keyword',
				'action_type' => 'direct_response', 'action_data' => 'Hi there!', 'priority' => 10, 'enabled' => true],
		]);
		\WpTestHelpers::seed_option('wpaic_routing_enabled', 1);

		$result = Macro_Router::on_macro_route([], 'hello world', [], []);

		$this->assertArrayHasKey('message', $result);
		$this->assertSame('Hi there!', $result['message']);
		$this->assertSame('macro_match', $result['finish_reason']);
	}

	public function testOnMacroRoutePassesThroughWhenAlreadyIntercepted(): void
	{
		\WpTestHelpers::seed_option('wpaic_routing_enabled', 1);
		$existingIntercept = ['message' => 'Already handled', 'finish_reason' => 'earlier_filter'];

		$result = Macro_Router::on_macro_route($existingIntercept, 'hello', [], []);

		// Must not overwrite an existing intercept.
		$this->assertSame('Already handled', $result['message']);
	}
}
