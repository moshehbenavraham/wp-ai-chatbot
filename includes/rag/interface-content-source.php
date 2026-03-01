<?php

/**
 * Content Source Interface — adapter contract for RAG knowledge sources.
 *
 * Implemented by every adapter that feeds content into the knowledge base.
 * Content sources enumerate items (posts, pages, products, URLs) and
 * extract their plain-text content for chunking and embedding.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Interface Content_Source
 *
 * @since 1.0.0
 * @package WPAIC\Rag
 */
interface Content_Source
{
	/**
	 * Unique machine-readable ID for this source (e.g. 'posts', 'products', 'web').
	 *
	 * @return string
	 */
	public function get_id(): string;

	/**
	 * Human-readable label shown in the admin Knowledge Base page.
	 *
	 * @return string
	 */
	public function get_label(): string;

	/**
	 * Return a flat list of items available for indexing.
	 *
	 * Each item MUST contain at least: id (int), title (string).
	 * May also include: modified (string), type (string).
	 *
	 * @param  array $options Source-specific configuration (post_types, statuses, etc.)
	 * @return array<int, array{id: int, title: string}>
	 */
	public function get_items(array $options = []): array;

	/**
	 * Extract full indexable content for a single item.
	 *
	 * Returns false when the item is inaccessible or has no indexable content.
	 *
	 * @param  int $item_id Source item identifier.
	 * @return array{title: string, content: string, url: string, type: string, meta: array}|false
	 */
	public function get_content(int $item_id);
}
