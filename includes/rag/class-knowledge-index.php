<?php

/**
 * Knowledge Index — local vector store for RAG chunk embeddings.
 *
 * Stores text chunks and their embedding vectors in wp_wpaic_chunks.
 * Retrieval uses cosine similarity computed in PHP, which scales to
 * tens of thousands of chunks without external infrastructure.
 *
 * For very large corpora, swap search() for a Pinecone/Weaviate adapter
 * by filtering via `wpaic_rag_search_results`.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Knowledge_Index
 *
 * @since 1.0.0
 * @package WPAIC\Rag
 */
class Knowledge_Index
{
	/** DB table name (without prefix). */
	private const TABLE = 'wpaic_chunks';

	// =========================================================================
	// Write operations
	// =========================================================================

	/**
	 * Upsert a chunk row.
	 *
	 * Replaces any existing chunk at the same (post_id, knowledge_id, chunk_index)
	 * combination using ON DUPLICATE KEY UPDATE (backed by the UNIQUE KEY).
	 *
	 * @param  array{
	 *   knowledge_id:  int,
	 *   post_id:       int,
	 *   chunk_index:   int,
	 *   content:       string,
	 *   embedding:     float[],
	 *   tokens:        int,
	 *   title:         string,
	 *   type:          string
	 * } $chunk Chunk data array.
	 * @return bool True on success.
	 */
	public static function upsert_chunk(array $chunk): bool
	{
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		$embedding_json = wp_json_encode($chunk['embedding']);

		if (false === $embedding_json) {
			return false;
		}

		$result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"INSERT INTO `{$table}`
				 (knowledge_id, post_id, chunk_index, type, title, content, embedding, tokens, updated_at)
				 VALUES (%d, %d, %d, %s, %s, %s, %s, %d, %s)
				 ON DUPLICATE KEY UPDATE
				   type       = VALUES(type),
				   title      = VALUES(title),
				   content    = VALUES(content),
				   embedding  = VALUES(embedding),
				   tokens     = VALUES(tokens),
				   updated_at = VALUES(updated_at)",
				(int) $chunk['knowledge_id'],
				(int) $chunk['post_id'],
				(int) $chunk['chunk_index'],
				(string) $chunk['type'],
				(string) $chunk['title'],
				(string) $chunk['content'],
				$embedding_json,
				(int) $chunk['tokens'],
				current_time('mysql')
			)
		);

		return false !== $result;
	}

	/**
	 * Delete all chunks for a specific post within a knowledge context.
	 *
	 * Called before re-indexing a modified post to avoid stale chunks.
	 *
	 * @param  int $knowledge_id Knowledge context ID.
	 * @param  int $post_id      WordPress post ID.
	 * @return int Number of rows deleted.
	 */
	public static function delete_post_chunks(int $knowledge_id, int $post_id): int
	{
		global $wpdb;

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::TABLE,
			[
				'knowledge_id' => $knowledge_id,
				'post_id' => $post_id,
			],
			['%d', '%d']
		);

		return (int) $deleted;
	}

	/**
	 * Delete all chunks for an entire knowledge context (full re-index or teardown).
	 *
	 * @param  int $knowledge_id Knowledge context ID.
	 * @return int Number of rows deleted.
	 */
	public static function delete_knowledge_chunks(int $knowledge_id): int
	{
		global $wpdb;

		$deleted = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::TABLE,
			['knowledge_id' => $knowledge_id],
			['%d']
		);

		return (int) $deleted;
	}

	// =========================================================================
	// Read / stats operations
	// =========================================================================

	/**
	 * Return the total number of indexed chunks for a knowledge context.
	 *
	 * @param  int $knowledge_id Knowledge context ID.
	 * @return int
	 */
	public static function chunk_count(int $knowledge_id): int
	{
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT COUNT(*) FROM `' . $wpdb->prefix . self::TABLE . '` WHERE knowledge_id = %d',
				$knowledge_id
			)
		);
	}

	/**
	 * Search for semantically similar chunks using cosine similarity.
	 *
	 * Loads all chunk embeddings for the knowledge context into PHP memory,
	 * computes cosine similarity against the query vector, then returns the
	 * top-$limit results above $min_score.
	 *
	 * Scales comfortably to ~50K chunks. For larger indexes consider an
	 * external vector store adapter via the `wpaic_rag_search_results` filter.
	 *
	 * @param  int     $knowledge_id    Knowledge context ID.
	 * @param  float[] $query_embedding Query embedding vector.
	 * @param  int     $limit           Maximum results to return.
	 * @param  float   $min_score       Minimum cosine similarity (0–1).
	 * @return array<int, array{content: string, title: string, score: float, post_id: int, type: string, chunk_index: int}>
	 */
	public static function search(
		int $knowledge_id,
		array $query_embedding,
		int $limit = 5,
		float $min_score = 0.0
	): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;

		// Load all chunks with embeddings for this context.
		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT post_id, chunk_index, type, title, content, embedding FROM `' . $table . '` WHERE knowledge_id = %d AND embedding IS NOT NULL',
				$knowledge_id
			),
			ARRAY_A
		);

		if (empty($rows)) {
			return [];
		}

		$scored = [];

		foreach ($rows as $row) {
			$embedding = json_decode((string) $row['embedding'], true);

			if (! is_array($embedding) || empty($embedding)) {
				continue;
			}

			$score = self::cosine_similarity($query_embedding, $embedding);

			if ($score < $min_score) {
				continue;
			}

			$scored[] = [
				'content' => (string) $row['content'],
				'title' => (string) $row['title'],
				'score' => $score,
				'post_id' => (int) $row['post_id'],
				'type' => (string) $row['type'],
				'chunk_index' => (int) $row['chunk_index'],
			];
		}

		// Sort by score descending.
		usort($scored, static function (array $a, array $b): int {
			return $b['score'] <=> $a['score'];
		});

		$results = array_slice($scored, 0, $limit);

		/**
		 * Filter: allow external vector stores to override/augment local results.
		 *
		 * @since 1.0.0
		 * @param array $results      Top-N scored chunks.
		 * @param int   $knowledge_id Knowledge context ID.
		 * @param float $min_score    Minimum score threshold.
		 */
		return (array) apply_filters('wpaic_rag_search_results', $results, $knowledge_id, $min_score);
	}

	// =========================================================================
	// Math helpers
	// =========================================================================

	/**
	 * Calculate cosine similarity between two float vectors.
	 *
	 * Returns 0.0 when either vector has zero magnitude (avoids division by zero).
	 *
	 * @param  float[] $a First vector.
	 * @param  float[] $b Second vector.
	 * @return float Similarity in [0, 1].
	 */
	public static function cosine_similarity(array $a, array $b): float
	{
		$dot = 0.0;
		$mag_a = 0.0;
		$mag_b = 0.0;
		$n = count($a);

		for ($i = 0; $i < $n; $i++) {
			$ai = (float) ($a[$i] ?? 0.0);
			$bi = (float) ($b[$i] ?? 0.0);
			$dot += $ai * $bi;
			$mag_a += $ai * $ai;
			$mag_b += $bi * $bi;
		}

		if (0.0 === $mag_a || 0.0 === $mag_b) {
			return 0.0;
		}

		return $dot / (sqrt($mag_a) * sqrt($mag_b));
	}
}
