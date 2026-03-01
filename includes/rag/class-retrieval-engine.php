<?php

/**
 * Retrieval Engine — RAG context assembly for the chat pipeline.
 *
 * Embeds the user's query, searches the knowledge index by cosine similarity,
 * and assembles a context block injected into the system prompt.
 *
 * Hooks into the `wpaic_rag_context` filter (wired in Message_Pipeline stage 2).
 * Filter signature: apply_filters('wpaic_rag_context', $context, $message, $bot)
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Retrieval_Engine
 *
 * @since 1.0.0
 * @package WPAIC\Rag
 */
class Retrieval_Engine
{
	/** Default number of chunks to retrieve per query. */
	public const DEFAULT_LIMIT = 5;

	/** Default minimum cosine similarity to include a chunk. */
	public const DEFAULT_MIN_SCORE = 0.3;

	/** Maximum characters of assembled context to inject. */
	public const MAX_CONTEXT_CHARS = 3000;

	// =========================================================================
	// Lifecycle
	// =========================================================================

	/**
	 * Register the `wpaic_rag_context` filter hook.
	 *
	 * Called from Plugin::register_core_rag() on `wpaic_init` action.
	 *
	 * @return void
	 */
	public static function register_hooks(): void
	{
		add_filter('wpaic_rag_context', [static::class, 'on_rag_context'], 10, 3);
	}

	// =========================================================================
	// Filter callbacks
	// =========================================================================

	/**
	 * `wpaic_rag_context` filter callback.
	 *
	 * Embeds the user message, retrieves matching chunks from the knowledge
	 * context linked to the bot, and returns formatted context text.
	 * Returns the original $context unchanged when:
	 *   - $context is already non-empty (higher-priority hook ran first)
	 *   - bot has no linked knowledge_id
	 *   - embedding or retrieval fails
	 *
	 * @param  string $context Existing context string (empty by default).
	 * @param  string $message User's message.
	 * @param  array  $bot     Bot configuration row.
	 * @return string Assembled RAG context string, or empty on skip/failure.
	 */
	public static function on_rag_context(string $context, string $message, array $bot): string
	{
		// Respect upstream context already set by a higher-priority hook.
		if ('' !== $context) {
			return $context;
		}

		$knowledge_id = (int) ($bot['knowledge_id'] ?? 0);

		if ($knowledge_id <= 0) {
			return '';
		}

		if ('' === trim($message)) {
			return '';
		}

		// Resolve embedding provider.
		[$provider_id, $model] = self::resolve_embed_config($knowledge_id);

		$embed_provider = Embedding_Registry::instance()->get($provider_id);

		if (null === $embed_provider) {
			return '';
		}

		// Embed the query.
		$query_vector = $embed_provider->embed($message, $model);

		if (is_wp_error($query_vector) || ! is_array($query_vector) || empty($query_vector)) {
			return '';
		}

		// Retrieve top-N matching chunks.
		$limit = (int) apply_filters('wpaic_rag_chunk_limit', self::DEFAULT_LIMIT, $bot);
		$min_score = (float) apply_filters('wpaic_rag_min_score', self::DEFAULT_MIN_SCORE, $bot);

		$chunks = Knowledge_Index::search($knowledge_id, $query_vector, $limit, $min_score);

		if (empty($chunks)) {
			return '';
		}

		return self::assemble_context($chunks);
	}

	// =========================================================================
	// Context assembly
	// =========================================================================

	/**
	 * Format retrieved chunks into a readable context block for the system prompt.
	 *
	 * Respects MAX_CONTEXT_CHARS to keep the overall prompt within token budgets.
	 *
	 * @param  array<int, array{content: string, title: string, score: float, post_id: int}> $chunks
	 * @return string Formatted context string, or empty string when all chunks were empty.
	 */
	public static function assemble_context(array $chunks): string
	{
		if (empty($chunks)) {
			return '';
		}

		$parts = [];
		$chars = 0;

		foreach ($chunks as $chunk) {
			$snippet = trim((string) $chunk['content']);

			if ('' === $snippet) {
				continue;
			}

			$remaining = self::MAX_CONTEXT_CHARS - $chars;

			if ($remaining <= 0) {
				break;
			}

			if (mb_strlen($snippet) > $remaining) {
				$snippet = mb_substr($snippet, 0, $remaining) . '…';
			}

			$source = ! empty($chunk['title']) ? '[' . $chunk['title'] . ']' : '';
			$parts[] = $source . "\n" . $snippet;
			$chars += mb_strlen($snippet);
		}

		if (empty($parts)) {
			return '';
		}

		return "Relevant information from the knowledge base:\n\n" . implode("\n\n---\n\n", $parts);
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Resolve embedding provider ID and model for a knowledge context.
	 *
	 * Falls back to plugin-wide defaults when the knowledge row is unconfigured.
	 *
	 * @param  int $knowledge_id Knowledge context ID.
	 * @return array{0: string, 1: string} [provider_id, model]
	 */
	private static function resolve_embed_config(int $knowledge_id): array
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_knowledge';

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
}
