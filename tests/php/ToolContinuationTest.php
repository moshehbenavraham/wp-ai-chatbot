<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use WPAIC\Tools\Tool_Continuation;

/**
 * Tests for WPAIC\Tools\Tool_Continuation
 *
 * Covers is_tool_call_response() detection, extract_tool_calls() normalization
 * for OpenAI and Claude/generic formats, and the MAX_LOOPS constant.
 */
final class ToolContinuationTest extends TestCase
{
	private static bool $loaded = false;

	public static function setUpBeforeClass(): void
	{
		if (self::$loaded) {
			return;
		}

		require_once __DIR__ . '/stubs/wp-core-stubs.php';

		self::$loaded = true;
	}

	protected function setUp(): void
	{
		\WpTestHelpers::reset();
	}

	// ── MAX_LOOPS constant ────────────────────────────────────────────────────

	public function testMaxLoopsConstantIsFive(): void
	{
		$this->assertSame(5, Tool_Continuation::MAX_LOOPS);
	}

	// ── is_tool_call_response ─────────────────────────────────────────────────

	public function testIsToolCallResponseWithToolCallsFinishReason(): void
	{
		$response = ['finish_reason' => 'tool_calls', 'message' => ''];
		$this->assertTrue(Tool_Continuation::is_tool_call_response($response));
	}

	public function testIsToolCallResponseWithToolUseFinishReason(): void
	{
		// Claude uses 'tool_use' as the finish reason.
		$response = ['finish_reason' => 'tool_use', 'message' => ''];
		$this->assertTrue(Tool_Continuation::is_tool_call_response($response));
	}

	public function testIsToolCallResponseWithStopFinishReasonReturnsFalse(): void
	{
		$response = ['finish_reason' => 'stop', 'message' => 'Done.'];
		$this->assertFalse(Tool_Continuation::is_tool_call_response($response));
	}

	public function testIsToolCallResponseWithErrorKeyReturnsFalse(): void
	{
		$response = ['error' => 'Provider timeout', 'finish_reason' => 'tool_calls'];
		$this->assertFalse(Tool_Continuation::is_tool_call_response($response));
	}

	public function testIsToolCallResponseWithToolCallsArrayReturnsTrueEvenIfStopReason(): void
	{
		// Some providers set finish_reason=stop but still include tool_calls.
		$response = [
			'finish_reason' => 'stop',
			'tool_calls' => [
				['id' => 'call_1', 'function' => ['name' => 'my_tool', 'arguments' => '{}']],
			],
		];
		$this->assertTrue(Tool_Continuation::is_tool_call_response($response));
	}

	public function testIsToolCallResponseEmptyToolCallsArrayReturnsFalse(): void
	{
		$response = ['finish_reason' => 'stop', 'tool_calls' => []];
		$this->assertFalse(Tool_Continuation::is_tool_call_response($response));
	}

	// ── extract_tool_calls (via reflection — method is private) ───────────────
	//
	// extract_tool_calls is private in Tool_Continuation, so we test it through
	// a public method that exercises the same logic: by passing a response with
	// tool_calls to run() via a mock provider, OR by using Reflection.

	/**
	 * Helper: call the private extract_tool_calls via reflection.
	 *
	 * @param  array  $response
	 * @param  string $provider_id
	 * @return array
	 */
	private function extract(array $response, string $provider_id = 'openai'): array
	{
		$method = new \ReflectionMethod(Tool_Continuation::class, 'extract_tool_calls');
		$method->setAccessible(true);

		return $method->invoke(null, $response, $provider_id);
	}

	public function testExtractToolCallsNormalizesOpenAIFormat(): void
	{
		$response = [
			'finish_reason' => 'tool_calls',
			'tool_calls' => [
				[
					'id' => 'call_abc123',
					'type' => 'function',
					'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}'],
				],
			],
		];

		$calls = $this->extract($response, 'openai');

		$this->assertCount(1, $calls);
		$this->assertSame('call_abc123', $calls[0]['id']);
		$this->assertSame('get_weather', $calls[0]['name']);
		$this->assertSame('{"city":"Paris"}', $calls[0]['arguments']);
	}

	public function testExtractToolCallsNormalizesClaudeFormat(): void
	{
		$response = [
			'finish_reason' => 'tool_use',
			'tool_calls' => [
				[
					'id' => 'toolu_xyz',
					'name' => 'search_knowledge',
					'input' => ['query' => 'Paris weather'],
				],
			],
		];

		$calls = $this->extract($response, 'claude');

		$this->assertCount(1, $calls);
		$this->assertSame('toolu_xyz', $calls[0]['id']);
		$this->assertSame('search_knowledge', $calls[0]['name']);

		// input array must be JSON-encoded.
		$decoded = json_decode($calls[0]['arguments'], true);
		$this->assertSame('Paris weather', $decoded['query']);
	}

	public function testExtractToolCallsNormalizesClaudeFormatWithStringArguments(): void
	{
		// Claude variant where 'arguments' is already a JSON string.
		$response = [
			'finish_reason' => 'tool_use',
			'tool_calls' => [
				[
					'id' => 'toolu_001',
					'name' => 'my_tool',
					'arguments' => '{"key":"value"}',
				],
			],
		];

		$calls = $this->extract($response, 'claude');

		$this->assertCount(1, $calls);
		$this->assertSame('{"key":"value"}', $calls[0]['arguments']);
	}

	public function testExtractToolCallsSkipsNonArrayEntries(): void
	{
		$response = [
			'finish_reason' => 'tool_calls',
			'tool_calls' => [
				'not-an-array',
				42,
				['id' => 'call_ok', 'function' => ['name' => 'valid_tool', 'arguments' => '{}']],
			],
		];

		$calls = $this->extract($response);

		$this->assertCount(1, $calls);
		$this->assertSame('valid_tool', $calls[0]['name']);
	}

	public function testExtractToolCallsReturnsEmptyWhenNoToolCalls(): void
	{
		$response = ['finish_reason' => 'stop', 'message' => 'Done.'];

		$calls = $this->extract($response);

		$this->assertSame([], $calls);
	}

	public function testExtractToolCallsDefaultsIdToEmptyString(): void
	{
		$response = [
			'finish_reason' => 'tool_calls',
			'tool_calls' => [
				['function' => ['name' => 'tool_no_id', 'arguments' => '{}']],
			],
		];

		$calls = $this->extract($response);

		$this->assertCount(1, $calls);
		$this->assertSame('', $calls[0]['id']);
	}

	public function testExtractToolCallsDefaultsArgumentsToEmptyObject(): void
	{
		$response = [
			'finish_reason' => 'tool_calls',
			'tool_calls' => [
				['function' => ['name' => 'no_args_tool']],
			],
		];

		$calls = $this->extract($response);

		$this->assertCount(1, $calls);
		$this->assertSame('{}', $calls[0]['arguments']);
	}
}
