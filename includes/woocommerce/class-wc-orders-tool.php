<?php

/**
 * WooCommerce Get Orders Tool.
 *
 * Implements Tool_Interface so the AI can retrieve the current logged-in
 * customer's recent order history during a conversation.
 * Requires a logged-in WordPress user with WooCommerce orders.
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
 * Class WC_Orders_Tool
 *
 * @since   1.1.0
 * @package WPAIC\WooCommerce
 */
class WC_Orders_Tool implements Tool_Interface
{
	/** Maximum orders the tool may return (hard cap). */
	private const MAX_ORDERS = 5;

	/** {@inheritdoc} */
	public function get_id(): string
	{
		return 'wpaic_wc_get_orders';
	}

	/** {@inheritdoc} */
	public function get_name(): string
	{
		return __('Get Customer Orders', 'wp-ai-chatbot');
	}

	/** {@inheritdoc} */
	public function get_description(): string
	{
		return 'Retrieve the order history for the currently logged-in customer. Returns a list of recent orders with their status, items, and total. Use when the customer asks about their orders, purchases, shipping, or delivery status.';
	}

	/** {@inheritdoc} */
	public function get_schema(): array
	{
		return [
			'type' => 'object',
			'properties' => [
				'limit' => [
					'type' => 'integer',
					'description' => 'Number of recent orders to retrieve. Default: 3, maximum: 5.',
					'minimum' => 1,
					'maximum' => self::MAX_ORDERS,
					'default' => 3,
				],
			],
			'required' => [],
		];
	}

	/** {@inheritdoc} */
	public function get_permission(): string
	{
		return 'user'; // Requires a logged-in WordPress user.
	}

	/** {@inheritdoc} */
	public function execute(array $args, array $context): array
	{
		if (! function_exists('wc_get_orders')) {
			return [
				'ok' => false,
				'error' => 'wc_unavailable',
				'output' => __('WooCommerce is not available on this site.', 'wp-ai-chatbot'),
			];
		}

		// Prefer context user_id (set by Message_Pipeline for authenticated sessions).
		$user_id = (int) ($context['user_id'] ?? get_current_user_id());

		if ($user_id < 1) {
			return [
				'ok' => false,
				'error' => 'not_logged_in',
				'output' => __('You need to be logged in to view your orders.', 'wp-ai-chatbot'),
			];
		}

		$limit = min(self::MAX_ORDERS, max(1, (int) ($args['limit'] ?? 3)));

		$orders = wc_get_orders([
			'customer_id' => $user_id,
			'limit' => $limit,
			'orderby' => 'date',
			'order' => 'DESC',
		]);

		if (empty($orders)) {
			return [
				'ok' => true,
				'output' => __('You have no orders yet.', 'wp-ai-chatbot'),
			];
		}

		$lines = [];
		$lines[] = sprintf(
			/* translators: %d: number of orders */
			_n('Here is your most recent order:', 'Here are your %d most recent orders:', count($orders), 'wp-ai-chatbot'),
			count($orders)
		);

		foreach ($orders as $order) {
			if (! $order instanceof \WC_Order) {
				continue;
			}

			$date = $order->get_date_created()
				? $order->get_date_created()->date('F j, Y')
				: 'N/A';

			$lines[] = sprintf(
				'Order #%d — %s — Status: %s — Total: %s',
				$order->get_id(),
				$date,
				wc_get_order_status_name($order->get_status()),
				$order->get_formatted_order_total()
			);

			$item_names = [];
			foreach ($order->get_items() as $item) {
				$qty = $item->get_quantity();
				$item_names[] = ($qty > 1 ? "{$qty}× " : '') . $item->get_name();
			}

			if (! empty($item_names)) {
				$lines[] = '  Items: ' . implode(', ', $item_names);
			}
		}

		return [
			'ok' => true,
			'output' => implode("\n", $lines),
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
