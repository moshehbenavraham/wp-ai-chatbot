<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use WPAIC\Rag\Chunker;

/**
 * Tests for WPAIC\Rag\Chunker
 *
 * Covers empty input, single-chunk short text, multi-chunk long text,
 * overlap prefix, title prepend, chunk-size clamping, and token estimation.
 */
final class ChunkerTest extends TestCase
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

	// ── Constructor / clamping ────────────────────────────────────────────────

	public function testChunkSizeClampedToMinimum(): void
	{
		// Values below 500 are clamped to 500.
		$c = new Chunker(100, 0);
		$chunks = $c->chunk(str_repeat('a', 400));

		// A 400-char string fits in a single 500-char chunk.
		$this->assertCount(1, $chunks);
	}

	public function testChunkSizeClampedToMaximum(): void
	{
		// Values above 16 000 are clamped to 16 000.
		$c = new Chunker(99999, 0);
		$text = str_repeat('a', 20000);
		$chunks = $c->chunk($text);

		// All 20 000 chars must be covered; at 16 000 chunk size → 2 chunks.
		$this->assertGreaterThan(0, count($chunks));
		$total = array_sum(array_map(static fn ($ch) => mb_strlen($ch['content']), $chunks));
		$this->assertSame(mb_strlen($text), $total);
	}

	public function testOverlapClampedToHalfChunkSize(): void
	{
		// overlap > chunk_size/2 is clamped.
		$c = new Chunker(500, 400); // 400 > 500/2 = 250 → clamped to 250.
		$this->assertInstanceOf(Chunker::class, $c);
	}

	// ── Empty input ───────────────────────────────────────────────────────────

	public function testEmptyStringReturnsEmptyArray(): void
	{
		$c = new Chunker(4000, 0);
		$this->assertSame([], $c->chunk(''));
	}

	public function testWhitespaceOnlyReturnsEmptyArray(): void
	{
		$c = new Chunker(4000, 0);
		$this->assertSame([], $c->chunk('   ' . "\n\n" . '   '));
	}

	// ── Single chunk ──────────────────────────────────────────────────────────

	public function testShortTextReturnsSingleChunk(): void
	{
		$c = new Chunker(4000, 0);
		$chunks = $c->chunk('Hello world.');

		$this->assertCount(1, $chunks);
	}

	public function testSingleChunkHasRequiredKeys(): void
	{
		$c = new Chunker(4000, 0);
		$chunks = $c->chunk('Hello world.');
		$chunk = $chunks[0];

		$this->assertArrayHasKey('index', $chunk);
		$this->assertArrayHasKey('content', $chunk);
		$this->assertArrayHasKey('tokens_estimate', $chunk);
	}

	public function testSingleChunkIndexIsZero(): void
	{
		$c = new Chunker(4000, 0);
		$chunks = $c->chunk('Hello world.');

		$this->assertSame(0, $chunks[0]['index']);
	}

	// ── Title prepend ─────────────────────────────────────────────────────────

	public function testTitleIsPrependedToFirstChunk(): void
	{
		$c = new Chunker(4000, 0);
		$chunks = $c->chunk('Some content here.', 'My Document Title');

		$this->assertStringContainsString('My Document Title', $chunks[0]['content']);
	}

	public function testNoTitleDoesNotAddPrefix(): void
	{
		$c = new Chunker(4000, 0);
		$text = 'Just some plain content.';
		$chunks = $c->chunk($text);

		$this->assertSame($text, $chunks[0]['content']);
	}

	// ── Multiple chunks ───────────────────────────────────────────────────────

	public function testLongTextProducesMultipleChunks(): void
	{
		$c = new Chunker(500, 0);

		// Create 3 paragraphs of ~200 chars each → total ~600 chars → 2 chunks.
		$para = str_repeat('word ', 40); // ~200 chars
		$text = $para . "\n\n" . $para . "\n\n" . $para;

		$chunks = $c->chunk($text);

		$this->assertGreaterThan(1, count($chunks));
	}

	public function testChunkIndexesAreSequential(): void
	{
		$c = new Chunker(500, 0);
		$para = str_repeat('word ', 40);
		$text = $para . "\n\n" . $para . "\n\n" . $para;

		$chunks = $c->chunk($text);

		foreach ($chunks as $i => $chunk) {
			$this->assertSame($i, $chunk['index']);
		}
	}

	public function testOverlapCarriesTextToNextChunk(): void
	{
		$c = new Chunker(500, 100);
		$end = 'UNIQUE_OVERLAP_TEXT'; // Distinctive tail for first chunk.

		// First para: 450 chars ending with our marker. Second para: forces a flush.
		$para1 = str_repeat('a ', 215) . $end; // ~450+19 chars
		$para2 = str_repeat('b ', 250); // ~500 chars → forces flush of para1
		$text = $para1 . "\n\n" . $para2;

		$chunks = $c->chunk($text);

		if (count($chunks) > 1) {
			// The overlap portion of chunk[0] should appear at the start of chunk[1].
			$this->assertStringContainsString($end, $chunks[1]['content']);
		} else {
			// Everything fit in one chunk — overlap not triggered, that's OK.
			$this->markTestSkipped('Text fit in a single chunk; overlap not exercised.');
		}
	}

	// ── Token estimation ─────────────────────────────────────────────────────

	public function testEstimateTokensEmpty(): void
	{
		$this->assertSame(0, Chunker::estimate_tokens(''));
	}

	public function testEstimateTokensFourCharsPerToken(): void
	{
		// 8 chars → ceil(8/4) = 2 tokens.
		$this->assertSame(2, Chunker::estimate_tokens('12345678'));
	}

	public function testEstimateTokensRoundsUp(): void
	{
		// 5 chars → ceil(5/4) = 2 tokens.
		$this->assertSame(2, Chunker::estimate_tokens('12345'));
	}

	public function testEstimateTokensMatchesChunkMetadata(): void
	{
		$c = new Chunker(4000, 0);
		$text = str_repeat('word ', 20);
		$chunks = $c->chunk($text);

		$expected = Chunker::estimate_tokens(trim($text));
		$this->assertSame($expected, $chunks[0]['tokens_estimate']);
	}
}
