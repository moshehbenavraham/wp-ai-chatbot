<?php

/**
 * Chunker — splits content text into overlapping semantic chunks for RAG indexing.
 *
 * Algorithm (paragraph → sentence → word fallback):
 *  1. Split text by paragraph boundaries (\n\n / blank lines).
 *  2. Accumulate paragraphs until chunk_size is exceeded, then flush.
 *  3. Single paragraphs longer than chunk_size are split by sentences.
 *  4. Last resort: split by words.
 *  5. Carry the last $overlap characters of the previous chunk as prefix
 *     to the next chunk (context continuity).
 *
 * Based on MxChat_Chunker pattern with overlap and token estimation added.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Chunker
 *
 * @since 1.0.0
 * @package WPAIC\Rag
 */
class Chunker
{
	/** Default chunk size in characters (~1000 tokens at 4 chars/token). */
	public const DEFAULT_CHUNK_SIZE = 4000;

	/** Default overlap in characters carried to the next chunk. */
	public const DEFAULT_OVERLAP = 200;

	/** @var int Max characters per chunk. */
	private $chunk_size;

	/** @var int Overlap characters re-included at the start of each chunk. */
	private $overlap;

	/**
	 * @param int $chunk_size Characters per chunk.
	 * @param int $overlap    Overlap character count.
	 */
	public function __construct(
		int $chunk_size = self::DEFAULT_CHUNK_SIZE,
		int $overlap = self::DEFAULT_OVERLAP
	) {
		$this->chunk_size = max(500, min(16000, $chunk_size));
		$this->overlap = max(0, min((int) ($this->chunk_size / 2), $overlap));
	}

	/**
	 * Create a Chunker from WordPress options.
	 *
	 * @return self
	 */
	public static function from_options(): self
	{
		$size = (int) get_option('wpaic_rag_chunk_size', self::DEFAULT_CHUNK_SIZE);
		$overlap = (int) get_option('wpaic_rag_chunk_overlap', self::DEFAULT_OVERLAP);

		return new self($size, $overlap);
	}

	/**
	 * Estimate token count for a string (~4 chars per token).
	 *
	 * @param  string $text Text to measure.
	 * @return int Estimated token count.
	 */
	public static function estimate_tokens(string $text): int
	{
		return (int) ceil(mb_strlen($text) / 4);
	}

	/**
	 * Split text into chunks with index and token estimate metadata.
	 *
	 * @param  string $text  Content to chunk.
	 * @param  string $title Source title prepended to the first chunk for context.
	 * @return array<int, array{index: int, content: string, tokens_estimate: int}>
	 */
	public function chunk(string $text, string $title = ''): array
	{
		$text = trim($text);

		if ('' === $text) {
			return [];
		}

		// Prepend title so retrieved chunks carry context.
		if ('' !== $title) {
			$text = $title . "\n\n" . $text;
		}

		if (mb_strlen($text) <= $this->chunk_size) {
			return [$this->make_chunk(0, $text)];
		}

		$raw = $this->split_paragraphs($text);

		$result = [];
		foreach ($raw as $i => $chunk_text) {
			$result[] = $this->make_chunk($i, $chunk_text);
		}

		return $result;
	}

	// =========================================================================
	// Private splitting helpers
	// =========================================================================

	/**
	 * Build a chunk metadata entry.
	 *
	 * @param  int    $index   0-based index.
	 * @param  string $content Chunk text.
	 * @return array{index: int, content: string, tokens_estimate: int}
	 */
	private function make_chunk(int $index, string $content): array
	{
		return [
			'index' => $index,
			'content' => $content,
			'tokens_estimate' => self::estimate_tokens($content),
		];
	}

	/**
	 * Split text into chunks by paragraph boundaries.
	 *
	 * @param  string $text Content text.
	 * @return string[]
	 */
	private function split_paragraphs(string $text): array
	{
		$paragraphs = preg_split('/\n\s*\n/', $text) ?: [$text];
		$chunks = [];
		$current = '';
		$tail = ''; // Overlap tail from the previous chunk.

		foreach ($paragraphs as $para) {
			$para = trim((string) $para);

			if ('' === $para) {
				continue;
			}

			$sep = '' === $current ? '' : "\n\n";
			$candidate = $current . $sep . $para;

			if (mb_strlen($candidate) > $this->chunk_size && '' !== $current) {
				$chunks[] = $tail . $current;
				$tail = $this->extract_tail($current);
				$current = $para;
			} else {
				$current = $candidate;
			}

			// Single paragraph exceeds chunk_size — split by sentences.
			if (mb_strlen($current) > $this->chunk_size) {
				$sub_chunks = $this->split_sentences($current);
				$last_idx = count($sub_chunks) - 1;

				foreach ($sub_chunks as $si => $sub) {
					if ($si < $last_idx) {
						$chunks[] = $tail . $sub;
						$tail = $this->extract_tail($sub);
					} else {
						$current = $sub;
					}
				}
			}
		}

		if ('' !== $current) {
			$chunks[] = $tail . $current;
		}

		return $chunks;
	}

	/**
	 * Split a long paragraph into sentence-level chunks.
	 *
	 * @param  string $text Long paragraph text.
	 * @return string[]
	 */
	private function split_sentences(string $text): array
	{
		$sentences = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];
		$chunks = [];
		$current = '';

		foreach ($sentences as $sentence) {
			$sentence = trim((string) $sentence);

			if ('' === $sentence) {
				continue;
			}

			// Sentence itself exceeds chunk_size → word-level split.
			if (mb_strlen($sentence) > $this->chunk_size) {
				if ('' !== $current) {
					$chunks[] = $current;
					$current = '';
				}

				$word_chunks = $this->split_words($sentence);
				$last_idx = count($word_chunks) - 1;

				foreach ($word_chunks as $wi => $wc) {
					if ($wi < $last_idx) {
						$chunks[] = $wc;
					} else {
						$current = $wc;
					}
				}

				continue;
			}

			$sep = '' === $current ? '' : ' ';
			$candidate = $current . $sep . $sentence;

			if (mb_strlen($candidate) > $this->chunk_size && '' !== $current) {
				$chunks[] = $current;
				$current = $sentence;
			} else {
				$current = $candidate;
			}
		}

		if ('' !== $current) {
			$chunks[] = $current;
		}

		return $chunks;
	}

	/**
	 * Last-resort word-level split for very long unbroken strings.
	 *
	 * @param  string $text Text to split.
	 * @return string[]
	 */
	private function split_words(string $text): array
	{
		$words = preg_split('/\s+/', $text) ?: [$text];
		$chunks = [];
		$current = '';

		foreach ($words as $word) {
			$sep = '' === $current ? '' : ' ';
			$candidate = $current . $sep . $word;

			if (mb_strlen($candidate) > $this->chunk_size && '' !== $current) {
				$chunks[] = $current;
				$current = $word;
			} else {
				$current = $candidate;
			}
		}

		if ('' !== $current) {
			$chunks[] = $current;
		}

		return $chunks;
	}

	/**
	 * Extract the last $overlap characters of a chunk as the next chunk's prefix.
	 *
	 * @param  string $chunk Chunk text.
	 * @return string Tail prefix (empty string when overlap is 0).
	 */
	private function extract_tail(string $chunk): string
	{
		if (0 === $this->overlap || '' === $chunk) {
			return '';
		}

		$len = mb_strlen($chunk);

		if ($len <= $this->overlap) {
			return $chunk . "\n\n";
		}

		return mb_substr($chunk, $len - $this->overlap) . "\n\n";
	}
}
