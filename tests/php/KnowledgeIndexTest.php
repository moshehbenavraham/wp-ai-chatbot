<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use WPAIC\Rag\Knowledge_Index;

/**
 * Unit tests for Knowledge_Index.
 *
 * Two groups:
 *
 *   1. cosine_similarity() — pure math, zero dependencies.
 *   2. search()            — requires $wpdb mock (rows returned by get_results).
 *
 * Methods that write to the database (upsert_chunk, delete_*) are exercised
 * via a minimal $wpdb spy that captures the query call and verifies the
 * return-value contract.
 *
 * @covers \WPAIC\Rag\Knowledge_Index
 */
class KnowledgeIndexTest extends TestCase
{
	// =========================================================================
	// Test infrastructure
	// =========================================================================

	public static function setUpBeforeClass(): void
	{
		require_once __DIR__ . '/stubs/wp-core-stubs.php';
		require_once __DIR__ . '/stubs/class-wpdb.php';

		// current_time() is used by upsert_chunk / search.
		if (! function_exists('current_time')) {
			/**
			 * @param string $type   'mysql' or 'timestamp'.
			 * @param bool   $gmt    Use GMT?
			 * @return string|int
			 */
			function current_time(string $type, bool $gmt = false)
			{
				if ('mysql' === $type) {
					return '2026-01-01 00:00:00';
				}

				return time();
			}
		}
	}

	protected function setUp(): void
	{
		\WpTestHelpers::reset();
		$this->installWpdb();
	}

	// =========================================================================
	// Helper: create and install a fresh wpdb spy
	// =========================================================================

	/** @var object */
	private object $db;

	private function installWpdb(): void
	{
		$this->db = new class () {
			public string $prefix = 'wp_';
			public string $last_error = '';

			/** Rows returned by get_results() — set per-test. */
			public array $rows = [];

			/** Return value of query() and delete(). */
			public int $affected = 1;

			/** Capture: last SQL string passed to query(). */
			public string $last_query = '';

			/** Capture: last delete() where array. */
			public array $last_delete_where = [];

			/** Return value of get_var(). */
			public string $var = '0';

			/** phpcs:ignore */
			public function prepare(string $sql, ...$args): string
			{
				// Lightweight interpolation for test readability.
				$i = 0;

				return (string) preg_replace_callback('/%[sdfs]/', function () use ($args, &$i) {
					return "'" . ($args[$i++] ?? '') . "'";
				}, $sql);
			}

			/** phpcs:ignore */
			public function get_results(string $query, string $output = 'OBJECT'): array
			{
				return $this->rows;
			}

			/** phpcs:ignore */
			public function query(string $query): int
			{
				$this->last_query = $query;

				return $this->affected;
			}

			/** phpcs:ignore */
			public function delete(string $table, array $where, array $format = []): int
			{
				$this->last_delete_where = $where;

				return $this->affected;
			}

			/** phpcs:ignore */
			public function get_var(string $query): string
			{
				return $this->var;
			}
		};

		$GLOBALS['wpdb'] = $this->db;
	}

	// =========================================================================
	// cosine_similarity() — pure math tests
	// =========================================================================

	public function testIdenticalUnitVectorsHaveSimilarityOne(): void
	{
		$v = [1.0, 0.0, 0.0];
		$score = Knowledge_Index::cosine_similarity($v, $v);
		$this->assertEqualsWithDelta(1.0, $score, 1e-9);
	}

	public function testOrthogonalVectorsHaveZeroSimilarity(): void
	{
		$a = [1.0, 0.0, 0.0];
		$b = [0.0, 1.0, 0.0];
		$score = Knowledge_Index::cosine_similarity($a, $b);
		$this->assertEqualsWithDelta(0.0, $score, 1e-9);
	}

	public function testProportionalVectorsHaveSimilarityOne(): void
	{
		// Scaling a vector does not change direction.
		$a = [1.0, 2.0, 3.0];
		$b = [2.0, 4.0, 6.0];
		$score = Knowledge_Index::cosine_similarity($a, $b);
		$this->assertEqualsWithDelta(1.0, $score, 1e-9);
	}

	public function testZeroVectorAReturnsZero(): void
	{
		$a = [0.0, 0.0, 0.0];
		$b = [1.0, 2.0, 3.0];
		$this->assertSame(0.0, Knowledge_Index::cosine_similarity($a, $b));
	}

	public function testZeroVectorBReturnsZero(): void
	{
		$a = [1.0, 2.0, 3.0];
		$b = [0.0, 0.0, 0.0];
		$this->assertSame(0.0, Knowledge_Index::cosine_similarity($a, $b));
	}

	public function testKnownVectorPairProducesCorrectScore(): void
	{
		// cos([1,1], [1,0]) = 1/(√2 * 1) ≈ 0.7071
		$a = [1.0, 1.0];
		$b = [1.0, 0.0];
		$score = Knowledge_Index::cosine_similarity($a, $b);
		$this->assertEqualsWithDelta(0.7071, $score, 0.0001);
	}

	public function testMismatchedLengthsUseShorterVector(): void
	{
		// Extra elements in $b are read as 0.0 (out-of-bounds ?? 0.0).
		$a = [1.0, 0.0];
		$b = [1.0, 0.0, 0.0, 5.0]; // extra dimensions not read by $a's loop
		$score = Knowledge_Index::cosine_similarity($a, $b);
		// Loop iterates count($a) = 2 times, so b[2..] not used.
		$this->assertEqualsWithDelta(1.0, $score, 1e-9);
	}

	// =========================================================================
	// search() — with wpdb mock
	// =========================================================================

	public function testSearchReturnsEmptyWhenNoRows(): void
	{
		$this->db->rows = [];
		$results = Knowledge_Index::search(1, [1.0, 0.0], 5, 0.0);
		$this->assertSame([], $results);
	}

	public function testSearchRanksResultsByScoreDescending(): void
	{
		// Three rows with embeddings at known cosine distances from [1,0].
		$query = [1.0, 0.0];

		$this->db->rows = [
			[
				'post_id' => 1,
				'chunk_index' => 0,
				'type' => 'post',
				'title' => 'Low',
				'content' => 'Low relevance',
				'embedding' => wp_json_encode([0.0, 1.0]), // orthogonal → 0.0
			],
			[
				'post_id' => 2,
				'chunk_index' => 0,
				'type' => 'post',
				'title' => 'High',
				'content' => 'High relevance',
				'embedding' => wp_json_encode([1.0, 0.0]), // identical → 1.0
			],
			[
				'post_id' => 3,
				'chunk_index' => 0,
				'type' => 'post',
				'title' => 'Mid',
				'content' => 'Mid relevance',
				'embedding' => wp_json_encode([1.0, 1.0]), // ≈0.707
			],
		];

		$results = Knowledge_Index::search(1, $query, 5, 0.0);

		$this->assertCount(3, $results);
		// Highest score first.
		$this->assertSame('High', $results[0]['title']);
		$this->assertSame('Mid', $results[1]['title']);
		$this->assertSame('Low', $results[2]['title']);
	}

	public function testSearchRespectsMinScoreThreshold(): void
	{
		$query = [1.0, 0.0];

		$this->db->rows = [
			[
				'post_id' => 1, 'chunk_index' => 0, 'type' => 'post',
				'title' => 'Irrelevant', 'content' => 'x',
				'embedding' => wp_json_encode([0.0, 1.0]), // score ≈ 0.0
			],
			[
				'post_id' => 2, 'chunk_index' => 0, 'type' => 'post',
				'title' => 'Relevant', 'content' => 'y',
				'embedding' => wp_json_encode([1.0, 0.0]), // score = 1.0
			],
		];

		// Only results with score ≥ 0.5 should be returned.
		$results = Knowledge_Index::search(1, $query, 5, 0.5);

		$this->assertCount(1, $results);
		$this->assertSame('Relevant', $results[0]['title']);
	}

	public function testSearchRespectsLimit(): void
	{
		$query = [1.0, 0.0];

		// Seed three identical-score rows.
		$this->db->rows = array_map(static function (int $i): array {
			return [
				'post_id' => $i, 'chunk_index' => 0, 'type' => 'post',
				'title' => "T{$i}", 'content' => "c{$i}",
				'embedding' => wp_json_encode([1.0, 0.0]),
			];
		}, [1, 2, 3, 4, 5]);

		$results = Knowledge_Index::search(1, $query, 3, 0.0);
		$this->assertCount(3, $results);
	}

	public function testSearchResultShapeIncludesExpectedKeys(): void
	{
		$query = [1.0, 0.0];
		$this->db->rows = [
			[
				'post_id' => 7, 'chunk_index' => 2, 'type' => 'pdf',
				'title' => 'My Doc', 'content' => 'Lorem ipsum',
				'embedding' => wp_json_encode([1.0, 0.0]),
			],
		];

		$results = Knowledge_Index::search(1, $query, 5, 0.0);

		$this->assertCount(1, $results);
		$r = $results[0];
		$this->assertArrayHasKey('content', $r);
		$this->assertArrayHasKey('title', $r);
		$this->assertArrayHasKey('score', $r);
		$this->assertArrayHasKey('post_id', $r);
		$this->assertArrayHasKey('type', $r);
		$this->assertArrayHasKey('chunk_index', $r);
		$this->assertSame(7, $r['post_id']);
		$this->assertSame(2, $r['chunk_index']);
		$this->assertSame('pdf', $r['type']);
	}

	public function testSearchSkipsRowsWithBadEmbedding(): void
	{
		$query = [1.0, 0.0];
		$this->db->rows = [
			[
				'post_id' => 1, 'chunk_index' => 0, 'type' => 'post',
				'title' => 'Bad', 'content' => 'x',
				'embedding' => 'not-json', // malformed — should be skipped
			],
			[
				'post_id' => 2, 'chunk_index' => 0, 'type' => 'post',
				'title' => 'Good', 'content' => 'y',
				'embedding' => wp_json_encode([1.0, 0.0]),
			],
		];

		$results = Knowledge_Index::search(1, $query, 5, 0.0);
		$this->assertCount(1, $results);
		$this->assertSame('Good', $results[0]['title']);
	}

	public function testSearchFilterCanReplaceResults(): void
	{
		$query = [1.0, 0.0];
		$this->db->rows = [
			[
				'post_id' => 1, 'chunk_index' => 0, 'type' => 'post',
				'title' => 'Original', 'content' => 'x',
				'embedding' => wp_json_encode([1.0, 0.0]),
			],
		];

		$replacement = [['content' => 'Injected', 'title' => 'Injected', 'score' => 0.99, 'post_id' => 99, 'type' => 'post', 'chunk_index' => 0]];

		add_filter('wpaic_rag_search_results', static function () use ($replacement) {
			return $replacement;
		});

		$results = Knowledge_Index::search(1, $query, 5, 0.0);
		$this->assertCount(1, $results);
		$this->assertSame('Injected', $results[0]['title']);
	}

	// =========================================================================
	// delete_post_chunks() / delete_knowledge_chunks() — spy on delete calls
	// =========================================================================

	public function testDeletePostChunksCallsWpdbDeleteWithCorrectArgs(): void
	{
		$this->db->affected = 3;

		$deleted = Knowledge_Index::delete_post_chunks(42, 7);

		$this->assertSame(3, $deleted);
		$this->assertSame(['knowledge_id' => 42, 'post_id' => 7], $this->db->last_delete_where);
	}

	public function testDeleteKnowledgeChunksCallsWpdbDeleteWithCorrectArgs(): void
	{
		$this->db->affected = 10;

		$deleted = Knowledge_Index::delete_knowledge_chunks(5);

		$this->assertSame(10, $deleted);
		$this->assertSame(['knowledge_id' => 5], $this->db->last_delete_where);
	}

	public function testDeleteReturnsZeroWhenNothingDeleted(): void
	{
		$this->db->affected = 0;
		$this->assertSame(0, Knowledge_Index::delete_knowledge_chunks(99));
	}

	// =========================================================================
	// chunk_count() — spy on get_var
	// =========================================================================

	public function testChunkCountReturnsIntegerFromWpdbGetVar(): void
	{
		$this->db->var = '42';
		$count = Knowledge_Index::chunk_count(1);
		$this->assertSame(42, $count);
	}

	public function testChunkCountReturnsZeroOnEmptyResult(): void
	{
		$this->db->var = '0';
		$this->assertSame(0, Knowledge_Index::chunk_count(1));
	}
}
