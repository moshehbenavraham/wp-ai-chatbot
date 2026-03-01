<?php

/**
 * Posts Content Source Adapter — WordPress posts and pages as a RAG knowledge source.
 *
 * Indexes published WordPress posts, pages, and custom post types.
 * Content is extracted as clean plain text (HTML stripped).
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Rag\Adapters;

use WPAIC\Rag\Content_Source;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Posts_Source
 *
 * @since 1.0.0
 * @package WPAIC\Rag\Adapters
 */
class Posts_Source implements Content_Source
{
	/** Source machine ID. */
	private const SOURCE_ID = 'posts';

	/** Post statuses to include by default. */
	private const DEFAULT_STATUSES = ['publish'];

	/** Post types to index by default. */
	private const DEFAULT_POST_TYPES = ['post', 'page'];

	/** {@inheritdoc} */
	public function get_id(): string
	{
		return self::SOURCE_ID;
	}

	/** {@inheritdoc} */
	public function get_label(): string
	{
		return __('Posts & Pages', 'wp-ai-chatbot');
	}

	/** {@inheritdoc} */
	public function get_items(array $options = []): array
	{
		$post_types = ! empty($options['post_types']) ? (array) $options['post_types'] : self::DEFAULT_POST_TYPES;
		$statuses = ! empty($options['statuses']) ? (array) $options['statuses'] : self::DEFAULT_STATUSES;

		// Sanitize to prevent SQL injection via WP_Query.
		$post_types = array_map('sanitize_key', $post_types);
		$statuses = array_map('sanitize_key', $statuses);

		$query = new \WP_Query([
			'post_type' => $post_types,
			'post_status' => $statuses,
			'posts_per_page' => -1,
			'no_found_rows' => true,
			'fields' => 'ids',
		]);

		if (empty($query->posts)) {
			return [];
		}

		$items = [];

		foreach ($query->posts as $post_id) {
			$post = get_post((int) $post_id);

			if (! $post instanceof \WP_Post) {
				continue;
			}

			$items[] = [
				'id' => (int) $post->ID,
				'title' => $post->post_title,
				'modified' => $post->post_modified,
				'type' => $post->post_type,
			];
		}

		return $items;
	}

	/** {@inheritdoc} */
	public function get_content(int $item_id)
	{
		$post = get_post($item_id);

		if (! $post instanceof \WP_Post || 'publish' !== $post->post_status) {
			return false;
		}

		// Apply WP content filters to resolve shortcodes, blocks, etc.
		$content = apply_filters('the_content', $post->post_content);

		// Strip HTML to clean plain text.
		$content = wp_strip_all_tags($content);
		$content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

		// Normalise excessive whitespace.
		$content = (string) preg_replace('/\s{3,}/', "\n\n", $content);
		$content = trim($content);

		if ('' === $content) {
			return false;
		}

		return [
			'title' => (string) $post->post_title,
			'content' => $content,
			'url' => (string) get_permalink($post),
			'type' => (string) $post->post_type,
			'meta' => [
				'post_id' => (int) $post->ID,
				'modified' => (string) $post->post_modified,
				'author' => (int) $post->post_author,
			],
		];
	}
}
