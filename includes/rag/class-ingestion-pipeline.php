<?php

/**
 * Ingestion Pipeline — queue-based RAG content ingestion worker.
 *
 * Processes pending items from wp_wpaic_queue:
 *  1. Claim next pending item (atomic UPDATE trick — no SELECT FOR UPDATE).
 *  2. Fetch content via the registered Content_Source adapter.
 *  3. Split into overlapping chunks via the Chunker.
 *  4. Embed chunks in one batch call via the Embedding_Provider adapter.
 *  5. Upsert chunk rows with embeddings into wp_wpaic_chunks.
 *  6. Mark item completed or failed (with retry up to max_attempts).
 *
 * Called from Plugin::on_process_queue() on the `wpaic_process_queue` cron event.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Ingestion_Pipeline
 *
 * @since 1.0.0
 * @package WPAIC\Rag
 */
class Ingestion_Pipeline
{
	/** Queue table name (without prefix). */
	private const QUEUE_TABLE = 'wpaic_queue';

	/** Knowledge table name (without prefix). */
	private const KNOWLEDGE_TABLE = 'wpaic_knowledge';

	/** Default batch size per cron invocation. */
	private const BATCH_SIZE = 5;

	/** Seconds before a 'processing' item is considered stalled. */
	private const STALL_SECONDS = 300;

	// =========================================================================
	// Public API
	// =========================================================================

	/**
	 * Enqueue a list of source items for ingestion into a knowledge context.
	 *
	 * Idempotent: existing queue rows for the same item are not duplicated
	 * when re-indexing — delete_post_chunks() handles old chunk removal at
	 * process time.
	 *
	 * @param  int    $knowledge_id Knowledge context ID.
	 * @param  string $source_id    Content source machine ID (e.g. 'posts').
	 * @param  int[]  $item_ids     Source item IDs to enqueue.
	 * @param  int    $priority     Queue priority (lower = processed first).
	 * @return int Number of items successfully inserted.
	 */
	public static function enqueue(
		int $knowledge_id,
		string $source_id,
		array $item_ids,
		int $priority = 0
	): int {
		global $wpdb;

		if (empty($item_ids) || $knowledge_id <= 0) {
			return 0;
		}

		$table = $wpdb->prefix . self::QUEUE_TABLE;
		$queue_id = wp_generate_uuid4();
		$count = 0;

		foreach ($item_ids as $item_id) {
			$item_data = wp_json_encode([
				'knowledge_id' => $knowledge_id,
				'source_id' => $source_id,
				'item_id' => (int) $item_id,
			]);

			if (false === $item_data) {
				continue;
			}

			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				[
					'queue_id' => $queue_id,
					'item_type' => 'ingest_item',
					'item_data' => $item_data,
					'status' => 'pending',
					'bot_id' => (string) $knowledge_id,
					'priority' => $priority,
					'attempts' => 0,
					'max_attempts' => 3,
				],
				['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d']
			);

			if ($inserted) {
				$count++;
			}
		}

		if ($count > 0) {
			self::update_knowledge_status($knowledge_id, 'pending', 0);
		}

		return $count;
	}

	/**
	 * Process the next batch of pending ingest_item queue entries.
	 *
	 * Invoked by Plugin::on_process_queue() via the `wpaic_process_queue` cron event.
	 * Recovers stalled items first, then processes up to BATCH_SIZE items.
	 *
	 * @return int Number of items processed in this batch.
	 */
	public static function process_next_batch(): int
	{
		self::recover_stalled();

		$limit = (int) apply_filters('wpaic_ingestion_batch_size', self::BATCH_SIZE);
		$processed = 0;

		for ($i = 0; $i < $limit; $i++) {
			$item = self::dequeue_next();

			if (null === $item) {
				break; // Queue empty.
			}

			$success = self::process_item($item);

			if ($success) {
				self::mark_completed((int) $item['id']);
			} else {
				self::mark_failed((int) $item['id'], $item);
			}

			$processed++;
		}

		return $processed;
	}

	// =========================================================================
	// Private: queue operations
	// =========================================================================

	/**
	 * Atomically claim and return the next pending ingest_item from the queue.
	 *
	 * Uses the error_message column as a claim token to avoid race conditions
	 * without requiring SELECT FOR UPDATE (not universally supported in WP hosting).
	 *
	 * @return array|null Queue row (ARRAY_A) or null when the queue is empty.
	 */
	private static function dequeue_next(): ?array
	{
		global $wpdb;

		$table = $wpdb->prefix . self::QUEUE_TABLE;
		$now = current_time('mysql');
		$claim_id = wp_generate_uuid4();

		// Claim via UPDATE then read.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"UPDATE `{$table}` SET status = 'processing', started_at = %s, error_message = %s
				 WHERE status = 'pending' AND item_type = 'ingest_item'
				 ORDER BY priority ASC, id ASC LIMIT 1",
				$now,
				$claim_id
			)
		);

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE status = 'processing' AND error_message = %s LIMIT 1",
				$claim_id
			),
			ARRAY_A
		);

		// Clear the claim token from the error_message column.
		if (is_array($row)) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$table,
				['error_message' => null],
				['id' => (int) $row['id']],
				['%s'],
				['%d']
			);
		}

		return is_array($row) ? $row : null;
	}

	/**
	 * Process a single queue item: fetch → chunk → embed → store.
	 *
	 * @param  array $item Queue row from wp_wpaic_queue.
	 * @return bool True on success (item should be marked completed).
	 */
	private static function process_item(array $item): bool
	{
		$data = json_decode((string) $item['item_data'], true);

		if (! is_array($data)) {
			return false;
		}

		$knowledge_id = (int) ($data['knowledge_id'] ?? 0);
		$source_id = (string) ($data['source_id'] ?? '');
		$item_id = (int) ($data['item_id'] ?? 0);

		if ($knowledge_id <= 0 || '' === $source_id || $item_id <= 0) {
			return false;
		}

		// 1. Resolve content source.
		$source = Content_Source_Registry::instance()->get($source_id);

		if (null === $source) {
			return false;
		}

		// 2. Fetch content.
		$content_data = $source->get_content($item_id);

		if (false === $content_data) {
			// Item not found or has no indexable content — mark completed to skip retries.
			return true;
		}

		// 3. Resolve embedding provider and model.
		[$embed_provider_id, $embed_model] = self::resolve_embed_config($knowledge_id);

		$embed_provider = Embedding_Registry::instance()->get($embed_provider_id);

		if (null === $embed_provider) {
			return false;
		}

		// 4. Chunk the content.
		$chunker = Chunker::from_options();
		$chunks = $chunker->chunk($content_data['content'], $content_data['title']);

		if (empty($chunks)) {
			return true; // No content chunks — nothing to index.
		}

		// 5. Remove old chunks for this item in this knowledge context.
		Knowledge_Index::delete_post_chunks($knowledge_id, $item_id);

		// 6. Embed all chunks in one batched API call.
		$texts = array_column($chunks, 'content');
		$vectors = $embed_provider->embed_batch($texts, $embed_model);

		if (is_wp_error($vectors)) {
			return false;
		}

		// 7. Upsert each chunk with its embedding vector.
		foreach ($chunks as $i => $chunk) {
			$vector = $vectors[$i] ?? null;

			if (! is_array($vector)) {
				continue;
			}

			Knowledge_Index::upsert_chunk([
				'knowledge_id' => $knowledge_id,
				'post_id' => $item_id,
				'chunk_index' => $chunk['index'],
				'content' => $chunk['content'],
				'embedding' => $vector,
				'tokens' => $chunk['tokens_estimate'],
				'title' => $content_data['title'],
				'type' => $content_data['type'],
			]);
		}

		return true;
	}

	/**
	 * Resolve the embedding provider ID and model for a knowledge context.
	 *
	 * Falls back to plugin-wide RAG defaults when the knowledge row has no
	 * provider/model configured.
	 *
	 * @param  int $knowledge_id Knowledge context ID.
	 * @return array{0: string, 1: string} [provider_id, model]
	 */
	private static function resolve_embed_config(int $knowledge_id): array
	{
		global $wpdb;

		$table = $wpdb->prefix . self::KNOWLEDGE_TABLE;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT embedding_provider, embedding_model FROM `' . $table . '` WHERE id = %d LIMIT 1',
				$knowledge_id
			),
			ARRAY_A
		);

		$provider = (is_array($row) && ! empty($row['embedding_provider']))
			? (string) $row['embedding_provider']
			: (string) get_option('wpaic_rag_embed_provider', 'openai');

		$model = (is_array($row) && ! empty($row['embedding_model']))
			? (string) $row['embedding_model']
			: (string) get_option('wpaic_rag_embed_model', 'text-embedding-3-small');

		return [$provider, $model];
	}

	/**
	 * Mark a queue item as completed.
	 *
	 * @param  int $id Queue row ID.
	 * @return void
	 */
	private static function mark_completed(int $id): void
	{
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::QUEUE_TABLE,
			[
				'status' => 'completed',
				'completed_at' => current_time('mysql'),
			],
			['id' => $id],
			['%s', '%s'],
			['%d']
		);
	}

	/**
	 * Increment attempts and set status to 'pending' (retry) or 'failed' (exhausted).
	 *
	 * @param  int   $id   Queue row ID.
	 * @param  array $item Original queue row.
	 * @return void
	 */
	private static function mark_failed(int $id, array $item): void
	{
		global $wpdb;

		$attempts = (int) $item['attempts'] + 1;
		$max_attempts = (int) $item['max_attempts'];
		$status = ($attempts >= $max_attempts) ? 'failed' : 'pending';

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::QUEUE_TABLE,
			[
				'status' => $status,
				'attempts' => $attempts,
				'error_message' => "Failed on attempt {$attempts}",
			],
			['id' => $id],
			['%s', '%d', '%s'],
			['%d']
		);
	}

	/**
	 * Recover processing items that exceeded the stall threshold.
	 *
	 * Resets them to 'pending' so they are retried on the next cron run.
	 *
	 * @return void
	 */
	private static function recover_stalled(): void
	{
		global $wpdb;

		$threshold = gmdate('Y-m-d H:i:s', time() - self::STALL_SECONDS);

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'UPDATE `' . $wpdb->prefix . self::QUEUE_TABLE . "` SET status = 'pending' WHERE status = 'processing' AND started_at < %s",
				$threshold
			)
		);
	}

	/**
	 * Update the processing_status and progress of a knowledge context row.
	 *
	 * @param  int    $knowledge_id Knowledge context ID.
	 * @param  string $status       New status string.
	 * @param  int    $progress     Progress 0-100.
	 * @return void
	 */
	private static function update_knowledge_status(int $knowledge_id, string $status, int $progress): void
	{
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prefix . self::KNOWLEDGE_TABLE,
			[
				'processing_status' => $status,
				'processing_progress' => $progress,
				'updated_at' => current_time('mysql'),
			],
			['id' => $knowledge_id],
			['%s', '%d', '%s'],
			['%d']
		);
	}
}
