<?php

/**
 * WooCommerce Add-to-Cart Tool.
 *
 * Implements the Tool_Interface so the AI can add a specific WooCommerce product
 * to the customer's shopping cart during a conversation. Requires WooCommerce
 * to be active and the customer's session to have a WC cart object.
 *
 * @package WP_AI_Chatbot
 * @since   1.1.0
 */

namespace WPAIC\WooCommerce;

use WPAIC\Tools\Tool_Interface;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class WC_Cart_Tool
 *
 * @since   1.1.0
 * @package WPAIC\WooCommerce
 */
class WC_Cart_Tool implements Tool_Interface
{
	/** {@inheritdoc} */
	public function get_id(): string
	{
		return 'wpaic_wc_add_to_cart';
	}

	/** {@inheritdoc} */
	public function get_name(): string
	{
		return __('Add Product to Cart', 'wp-ai-chatbot');
	}

	/** {@inheritdoc} */
	public function get_description(): string
	{
		return 'Add a WooCommerce product to the customer\'s shopping cart. Use when the customer explicitly asks to buy, purchase, or add a specific product. Requires the numeric product_id. Always confirm the product name with the customer before calling this tool.';
	}

	/** {@inheritdoc} */
	public function get_schema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'product_id' => [
					'type' => 'integer',
					'description' => 'The WooCommerce product ID to add (required).',
					'minimum' => 1,
				],
				'quantity' => [
					'type' => 'integer',
					'description' => 'Quantity to add (default: 1).',
					'minimum' => 1,
					'default' => 1,
				],
			],
			'required' => ['product_id'],
		];
	}

	/** {@inheritdoc} */
	public function get_permission(): string
	{
		// Cart actions are available to all visitors (WC handles cart sessions natively).
		return 'none';
	}

	/** {@inheritdoc} */
	public function execute(array $args, array $context): array
	{
		if (! function_exists('WC') || null === WC()->cart) {
			return [
				'ok' => false,
				'error' => 'wc_unavailable',
				'output' => __('WooCommerce is not available on this site.', 'wp-ai-chatbot'),
			];
		}

		$product_id = (int) ($args['product_id'] ?? 0);
		$quantity = max(1, (int) ($args['quantity'] ?? 1));

		if ($product_id < 1) {
			return [
				'ok' => false,
				'error' => 'invalid_product_id',
				'output' => __('A valid product ID is required.', 'wp-ai-chatbot'),
			];
		}

		$product = wc_get_product($product_id);

		if (! $product instanceof \WC_Product || 'publish' !== $product->get_status()) {
			return [
				'ok' => false,
				'error' => 'product_not_found',
				'output' => __('Product not found or not available for purchase.', 'wp-ai-chatbot'),
			];
		}

		if (! $product->is_in_stock()) {
			return [
				'ok' => false,
				'error' => 'out_of_stock',
				'output' => sprintf(
					/* translators: %s: product name */
					__('"%s" is currently out of stock.', 'wp-ai-chatbot'),
					$product->get_name()
				),
			];
		}

		$added = WC()->cart->add_to_cart($product_id, $quantity);

		if (false === $added) {
			return [
				'ok' => false,
				'error' => 'add_to_cart_failed',
				'output' => sprintf(
					/* translators: %s: product name */
					__('Could not add "%s" to the cart. Please try again or add it manually.', 'wp-ai-chatbot'),
					$product->get_name()
				),
			];
		}

		/**
		 * Fires after a product is successfully added to the cart via the chatbot tool.
		 *
		 * @since 1.1.0
		 * @param int   $product_id Product ID added.
		 * @param int   $quantity   Quantity added.
		 * @param array $context    Execution context (session_id, bot_slug, …).
		 */
		do_action('wpaic_wc_product_added_to_cart', $product_id, $quantity, $context);

		return [
			'ok' => true,
			'output' => sprintf(
				/* translators: 1: quantity, 2: product name */
				__('%1$d × "%2$s" added to your cart.', 'wp-ai-chatbot'),
				$quantity,
				$product->get_name()
			),
		];
	}

	/** {@inheritdoc} */
	public function to_provider_definition(): array
	{
		return [
			'name' => $this->get_id(),
			'description' => $this->get_description(),
			'parameters' => $this->get_schema(),
		];
	}
}
