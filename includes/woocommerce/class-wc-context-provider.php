<?php

/**
 * WooCommerce Context Provider.
 *
 * Hooks into the `wpaic_rag_context` filter (priority 15) to append
 * store-specific context — current product, cart contents, and recent
 * orders — to the system prompt assembled by the Message_Pipeline.
 *
 * Fires only when WooCommerce is active and the `wpaic_wc_enabled` option
 * is set to '1'. Individual context blocks can be toggled via sub-options.
 *
 * @package WP_AI_Chatbot
 * @since   1.1.0
 */

namespace WPAIC\WooCommerce;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class WC_Context_Provider
 *
 * @since   1.1.0
 * @package WPAIC\WooCommerce
 */
class WC_Context_Provider
{
	/** Maximum number of recent orders to include in context. */
	private const MAX_ORDERS = 3;

	// =========================================================================
	// Hook registration
	// =========================================================================

	/**
	 * Register the context-injection filter.
	 *
	 * Called from Plugin::on_wc_init() (fires inside `wpaic_wc_init` action).
	 *
	 * @return void
	 */
	public static function register_hooks(): void
	{
		// Priority 15: appends after RAG knowledge-base context (priority 10).
		add_filter('wpaic_rag_context', [static::class, 'inject_wc_context'], 15, 3);
	}

	// =========================================================================
	// Filter callback
	// =========================================================================

	/**
	 * Append WooCommerce context blocks to the pipeline context string.
	 *
	 * @param  string $context      Existing context assembled so far.
	 * @param  string $message      Incoming user message.
	 * @param  array  $session_data Session metadata (page_id, user_id, bot_slug, …).
	 * @return string Modified context string.
	 */
	public static function inject_wc_context(string $context, string $message, array $session_data): string
	{
		if (! class_exists('WooCommerce')) {
			return $context;
		}

		if (! get_option('wpaic_wc_enabled', '0')) {
			return $context;
		}

		$wc_parts = [];

		// --- 1. Current product page context ---
		$page_id = (int) ($session_data['page_id'] ?? 0);
		if ($page_id > 0 && 'product' === get_post_type($page_id)) {
			$product_ctx = self::build_product_context($page_id);
			if ('' !== $product_ctx) {
				$wc_parts[] = $product_ctx;
			}
		}

		// --- 2. Shopping cart ---
		if (get_option('wpaic_wc_cart_context_enabled', '1')) {
			$cart_ctx = self::build_cart_context();
			if ('' !== $cart_ctx) {
				$wc_parts[] = $cart_ctx;
			}
		}

		// --- 3. Recent orders for logged-in users ---
		$user_id = (int) ($session_data['user_id'] ?? get_current_user_id());
		if (
			$user_id > 0
			&& get_option('wpaic_wc_orders_context_enabled', '1')
			&& (bool) apply_filters('wpaic_wc_inject_orders_context', true, $user_id, $session_data)
		) {
			$orders_ctx = self::build_orders_context($user_id);
			if ('' !== $orders_ctx) {
				$wc_parts[] = $orders_ctx;
			}
		}

		if (empty($wc_parts)) {
			return $context;
		}

		$wc_block = "### WooCommerce Store Context\n" . implode("\n\n", $wc_parts);

		return $context . ($context ? "\n\n" : '') . $wc_block;
	}

	// =========================================================================
	// Private context builders
	// =========================================================================

	/**
	 * Build a context block for the product the customer is currently viewing.
	 *
	 * @param  int $product_id WC product post ID.
	 * @return string Context string, or empty string if unavailable.
	 */
	private static function build_product_context(int $product_id): string
	{
		if (! function_exists('wc_get_product')) {
			return '';
		}

		$product = wc_get_product($product_id);

		if (! $product instanceof \WC_Product) {
			return '';
		}

		$lines = [];
		$lines[] = 'Customer is viewing product: ' . $product->get_name();

		$price_html = $product->get_price_html();
		if ('' !== $price_html) {
			$lines[] = 'Price: ' . wp_strip_all_tags($price_html);
		}

		$stock_status = $product->get_stock_status();
		$lines[] = 'Stock: ' . ('instock' === $stock_status ? 'In Stock' : 'Out of Stock');

		$sku = $product->get_sku();
		if ('' !== $sku) {
			$lines[] = 'SKU: ' . $sku;
		}

		$short = wp_strip_all_tags($product->get_short_description());
		$short = trim($short);
		if ('' !== $short) {
			$lines[] = 'Summary: ' . $short;
		}

		return implode("\n", $lines);
	}

	/**
	 * Build a context block for the customer's current cart.
	 *
	 * @return string Context string, or empty string if cart is empty.
	 */
	private static function build_cart_context(): string
	{
		if (! function_exists('WC') || null === WC()->cart || WC()->cart->get_cart_contents_count() < 1) {
			return '';
		}

		$lines = [];
		$lines[] = sprintf(
			'Customer\'s cart (%d item(s)):',
			WC()->cart->get_cart_contents_count()
		);

		foreach (WC()->cart->get_cart() as $item) {
			$product = $item['data'] ?? null;
			if (! $product instanceof \WC_Product) {
				continue;
			}

			$lines[] = sprintf(
				'  - %s × %d @ %s',
				$product->get_name(),
				(int) $item['quantity'],
				wp_strip_all_tags(WC()->cart->get_product_price($product))
			);
		}

		$lines[] = 'Cart subtotal: ' . wp_strip_all_tags(WC()->cart->get_cart_subtotal());

		return implode("\n", $lines);
	}

	/**
	 * Build a context block for a logged-in customer's recent orders.
	 *
	 * @param  int $user_id WordPress user ID.
	 * @return string Context string, or empty string if no orders.
	 */
	private static function build_orders_context(int $user_id): string
	{
		if (! function_exists('wc_get_orders')) {
			return '';
		}

		$orders = wc_get_orders([
			'customer_id' => $user_id,
			'limit' => self::MAX_ORDERS,
			'orderby' => 'date',
			'order' => 'DESC',
		]);

		if (empty($orders)) {
			return '';
		}

		$lines = [];
		$lines[] = sprintf("Customer's %d most recent order(s):", count($orders));

		foreach ($orders as $order) {
			if (! $order instanceof \WC_Order) {
				continue;
			}

			$date = $order->get_date_created() ? $order->get_date_created()->date('Y-m-d') : 'N/A';

			$item_names = [];
			foreach ($order->get_items() as $item) {
				$item_names[] = $item->get_name();
			}

			$lines[] = sprintf(
				'  Order #%d (%s) — Status: %s — Total: %s',
				$order->get_id(),
				$date,
				wc_get_order_status_name($order->get_status()),
				$order->get_formatted_order_total()
			);

			if (! empty($item_names)) {
				$lines[] = '    Items: ' . implode(', ', $item_names);
			}
		}

		return implode("\n", $lines);
	}
}
