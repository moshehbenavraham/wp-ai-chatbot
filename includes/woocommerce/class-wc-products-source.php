<?php

/**
 * WooCommerce Products Content Source Adapter.
 *
 * Indexes published WooCommerce products for the RAG knowledge base so the
 * chatbot can retrieve product information during retrieval-augmented generation.
 * Requires WooCommerce to be active; returns empty results silently if not available.
 *
 * @package WP_AI_Chatbot
 * @since   1.1.0
 */

namespace WPAIC\WooCommerce;

use WPAIC\Rag\Content_Source;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class WC_Products_Source
 *
 * @since   1.1.0
 * @package WPAIC\WooCommerce
 */
class WC_Products_Source implements Content_Source
{
	/** Source machine ID. */
	private const SOURCE_ID = 'wc_products';

	// =========================================================================
	// Content_Source contract
	// =========================================================================

	/** {@inheritdoc} */
	public function get_id(): string
	{
		return self::SOURCE_ID;
	}

	/** {@inheritdoc} */
	public function get_label(): string
	{
		return __('WooCommerce Products', 'wp-ai-chatbot');
	}

	/**
	 * Return a list of all published products as indexable items.
	 *
	 * Each item has keys: id, title, modified, type.
	 * Options: statuses (array), limit (int, -1 = all).
	 *
	 * {@inheritdoc}
	 */
	public function get_items(array $options = []): array
	{
		if (! function_exists('wc_get_products')) {
			return [];
		}

		$statuses = ! empty($options['statuses']) ? (array) $options['statuses'] : ['publish'];
		$limit = isset($options['limit']) ? (int) $options['limit'] : -1;

		$product_ids = wc_get_products([
			'status' => $statuses,
			'limit' => $limit,
			'return' => 'ids',
		]);

		if (empty($product_ids)) {
			return [];
		}

		$items = [];

		foreach ($product_ids as $product_id) {
			$product = wc_get_product($product_id);

			if (! $product instanceof \WC_Product) {
				continue;
			}

			$modified = $product->get_date_modified();

			$items[] = [
				'id' => (int) $product->get_id(),
				'title' => (string) $product->get_name(),
				'modified' => $modified ? $modified->date('Y-m-d H:i:s') : current_time('mysql'),
				'type' => 'product',
			];
		}

		return $items;
	}

	/**
	 * Return cleaned text content for a single product, ready for chunking.
	 *
	 * Returns an array with keys: title, content, url, type, meta.
	 * Returns false if the product does not exist or is not published.
	 *
	 * {@inheritdoc}
	 */
	public function get_content(int $item_id)
	{
		if (! function_exists('wc_get_product')) {
			return false;
		}

		$product = wc_get_product($item_id);

		if (! $product instanceof \WC_Product || 'publish' !== $product->get_status()) {
			return false;
		}

		$parts = [];

		// Core identity.
		$parts[] = 'Product: ' . $product->get_name();

		$sku = $product->get_sku();
		if ('' !== $sku) {
			$parts[] = 'SKU: ' . $sku;
		}

		// Pricing.
		$price_html = $product->get_price_html();
		if ('' !== $price_html) {
			$parts[] = 'Price: ' . wp_strip_all_tags($price_html);
		}

		// Categories.
		$cat_ids = $product->get_category_ids();
		if (! empty($cat_ids)) {
			$names = [];
			foreach ($cat_ids as $cat_id) {
				$term = get_term((int) $cat_id, 'product_cat');
				if ($term instanceof \WP_Term) {
					$names[] = $term->name;
				}
			}
			if (! empty($names)) {
				$parts[] = 'Categories: ' . implode(', ', $names);
			}
		}

		// Stock status.
		$stock_status = $product->get_stock_status();
		$parts[] = 'Availability: ' . ('instock' === $stock_status ? 'In Stock' : 'Out of Stock');

		// Short description.
		$short_desc = wp_strip_all_tags(apply_filters('the_content', $product->get_short_description()));
		$short_desc = trim(html_entity_decode($short_desc, ENT_QUOTES, 'UTF-8'));
		if ('' !== $short_desc) {
			$parts[] = 'Summary: ' . $short_desc;
		}

		// Full description.
		$description = wp_strip_all_tags(apply_filters('the_content', $product->get_description()));
		$description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
		$description = (string) preg_replace('/\s{3,}/', "\n\n", $description);
		$description = trim($description);
		if ('' !== $description) {
			$parts[] = "Description:\n" . $description;
		}

		$content = implode("\n", $parts);
		$content = trim($content);

		if ('' === $content) {
			return false;
		}

		$modified = $product->get_date_modified();

		return [
			'title' => (string) $product->get_name(),
			'content' => $content,
			'url' => (string) $product->get_permalink(),
			'type' => 'product',
			'meta' => [
				'post_id' => $item_id,
				'modified' => $modified ? $modified->date('Y-m-d H:i:s') : '',
				'price' => (string) $product->get_price(),
				'sku' => $sku,
				'stock_status' => $stock_status,
			],
		];
	}
}
