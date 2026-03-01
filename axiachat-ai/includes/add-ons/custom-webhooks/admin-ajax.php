<?php

/**
 * Custom Webhooks Admin AJAX Handlers
 *
 * CRUD operations for custom webhooks.
 *
 * @package AIChat
 * @subpackage CustomWebhooks
 */

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * AJAX: Get all webhooks
 */
add_action('wp_ajax_aichat_custom_webhooks_list', 'aichat_ajax_custom_webhooks_list');
function aichat_ajax_custom_webhooks_list()
{
	check_ajax_referer('aichat_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}

	$webhooks = aichat_get_custom_webhooks();
	$max = aichat_custom_webhooks_max_allowed();
	$count = count($webhooks);
	$can_create = $count < $max;
	$is_pro = aichat_custom_webhooks_has_pro();

	wp_send_json_success([
		'webhooks' => $webhooks,
		'count' => $count,
		'max' => $max,
		'can_create' => $can_create,
		'is_pro' => $is_pro,
	]);
}

/**
 * AJAX: Get single webhook
 */
add_action('wp_ajax_aichat_custom_webhooks_get', 'aichat_ajax_custom_webhooks_get');
function aichat_ajax_custom_webhooks_get()
{
	check_ajax_referer('aichat_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}

	$slug = sanitize_key($_POST['slug'] ?? '');
	if (empty($slug)) {
		wp_send_json_error(['message' => 'Missing slug']);
	}

	$webhook = aichat_get_custom_webhook($slug);
	if (! $webhook) {
		wp_send_json_error(['message' => 'Webhook not found']);
	}

	wp_send_json_success(['webhook' => $webhook]);
}

/**
 * AJAX: Create/Update webhook
 */
add_action('wp_ajax_aichat_custom_webhooks_save', 'aichat_ajax_custom_webhooks_save');
function aichat_ajax_custom_webhooks_save()
{
	check_ajax_referer('aichat_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}

	global $wpdb;
	$table = $wpdb->prefix . 'aichat_custom_webhooks';

	// Get data
	$id = absint($_POST['id'] ?? 0);
	$slug = sanitize_key($_POST['slug'] ?? '');
	$label = sanitize_text_field(wp_unslash($_POST['label'] ?? ''));
	$description_ai = sanitize_textarea_field(wp_unslash($_POST['description_ai'] ?? ''));
	$activity_label = sanitize_text_field(wp_unslash($_POST['activity_label'] ?? ''));
	$webhook_url = esc_url_raw(wp_unslash($_POST['webhook_url'] ?? ''));
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated with in_array
	$http_method = in_array(wp_unslash($_POST['http_method'] ?? 'POST'), ['GET', 'POST', 'PUT'], true)
		? sanitize_key(wp_unslash($_POST['http_method'])) : 'POST';
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON sanitized via json_decode/wp_json_encode roundtrip.
	$headers_json = wp_json_encode(json_decode(wp_unslash($_POST['headers_json'] ?? '{}'), true) ?: []);
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON sanitized via json_decode/wp_json_encode roundtrip.
	$params_schema = wp_json_encode(json_decode(wp_unslash($_POST['params_schema'] ?? '[]'), true) ?: []);
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON sanitized via json_decode/wp_json_encode roundtrip.
	$static_params = wp_json_encode(json_decode(wp_unslash($_POST['static_params'] ?? '{}'), true) ?: []);
	$include_history = absint($_POST['include_history'] ?? 0);
	$history_limit = min(50, max(1, absint($_POST['history_limit'] ?? 10)));
	$success_message = sanitize_textarea_field(wp_unslash($_POST['success_message'] ?? ''));
	$error_message = sanitize_textarea_field(wp_unslash($_POST['error_message'] ?? ''));
	$is_active = absint($_POST['is_active'] ?? 1);

	// Validate required fields
	if (empty($slug) || empty($label) || empty($description_ai) || empty($webhook_url)) {
		wp_send_json_error(['message' => __('Please fill in all required fields.', 'axiachat-ai')]);
	}

	// Check slug format
	if (! preg_match('/^[a-z0-9_]+$/', $slug)) {
		wp_send_json_error(['message' => __('Slug can only contain lowercase letters, numbers, and underscores.', 'axiachat-ai')]);
	}

	// Check limits for new webhooks
	if (! $id) {
		if (! aichat_custom_webhooks_can_create()) {
			$max = aichat_custom_webhooks_max_allowed();
			wp_send_json_error([
				'message' => sprintf(
					/* translators: %d: Maximum number of webhooks allowed in free plan */
					__('You have reached the limit of %d custom webhooks. Upgrade to PRO for unlimited webhooks.', 'axiachat-ai'),
					$max
				),
				'upgrade_required' => true,
			]);
		}

		// Check slug uniqueness
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
		$exists = $wpdb->get_var($wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
			"SELECT id FROM $table WHERE slug = %s",
			$slug
		));

		if ($exists) {
			wp_send_json_error(['message' => __('A webhook with this slug already exists.', 'axiachat-ai')]);
		}
	}

	$data = [
		'slug' => $slug,
		'label' => $label,
		'description_ai' => $description_ai,
		'activity_label' => $activity_label,
		'webhook_url' => $webhook_url,
		'http_method' => $http_method,
		'headers_json' => $headers_json,
		'params_schema' => $params_schema,
		'static_params' => $static_params,
		'include_history' => $include_history,
		'history_limit' => $history_limit,
		'success_message' => $success_message,
		'error_message' => $error_message,
		'is_active' => $is_active,
		'updated_at' => current_time('mysql'),
	];

	if ($id) {
		// Update existing
		$wpdb->update($table, $data, ['id' => $id]);
		$result_id = $id;
	} else {
		// Create new
		$data['created_at'] = current_time('mysql');
		$wpdb->insert($table, $data);
		$result_id = $wpdb->insert_id;
	}

	if (! $result_id) {
		wp_send_json_error(['message' => __('Database error saving webhook.', 'axiachat-ai')]);
	}

	// Re-register tools (will happen on next page load via init hook)
	// For immediate effect, we'd need to clear and re-register, but that's complex.

	wp_send_json_success([
		'message' => __('Webhook saved successfully.', 'axiachat-ai'),
		'id' => $result_id,
		'slug' => $slug,
	]);
}

/**
 * AJAX: Delete webhook
 */
add_action('wp_ajax_aichat_custom_webhooks_delete', 'aichat_ajax_custom_webhooks_delete');
function aichat_ajax_custom_webhooks_delete()
{
	check_ajax_referer('aichat_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}

	global $wpdb;
	$table = $wpdb->prefix . 'aichat_custom_webhooks';

	$slug = sanitize_key($_POST['slug'] ?? '');
	if (empty($slug)) {
		wp_send_json_error(['message' => 'Missing slug']);
	}

	// Delete from database
	$deleted = $wpdb->delete($table, ['slug' => $slug]);

	if (! $deleted) {
		wp_send_json_error(['message' => __('Webhook not found or already deleted.', 'axiachat-ai')]);
	}

	// Cleanup macro
	aichat_custom_webhooks_cleanup_macro($slug);

	wp_send_json_success(['message' => __('Webhook deleted.', 'axiachat-ai')]);
}

/**
 * AJAX: Test webhook
 */
add_action('wp_ajax_aichat_custom_webhooks_test', 'aichat_ajax_custom_webhooks_test');
function aichat_ajax_custom_webhooks_test()
{
	check_ajax_referer('aichat_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}

	$slug = sanitize_key($_POST['slug'] ?? '');
	if (empty($slug)) {
		wp_send_json_error(['message' => 'Missing slug']);
	}

	$webhook = aichat_get_custom_webhook($slug);
	if (! $webhook) {
		wp_send_json_error(['message' => 'Webhook not found']);
	}

	// Build test params based on schema
	$test_params = [];
	$params_schema = $webhook['params_schema'] ?? [];

	foreach ($params_schema as $param) {
		$name = $param['name'] ?? '';
		if (empty($name)) {
			continue;
		}

		$type = $param['type'] ?? 'string';

		// Generate test value
		switch ($type) {
			case 'integer':
			case 'number':
				$test_params[ $name ] = 123;
				break;
			case 'boolean':
				$test_params[ $name ] = true;
				break;
			case 'enum':
				$values = array_map('trim', explode(',', $param['values'] ?? ''));
				$test_params[ $name ] = $values[0] ?? 'test';
				break;
			default:
				$test_params[ $name ] = 'test_' . $name;
		}
	}

	// Test context
	$context = [
		'session_id' => 'test_session_' . time(),
		'bot_slug' => 'test_bot',
		'page_url' => home_url(),
		'history' => [
			['role' => 'user', 'content' => 'This is a test message'],
			['role' => 'assistant', 'content' => 'This is a test response'],
		],
	];

	// Execute
	$result = AIChat_Webhook_Executor::execute($webhook, $test_params, $context);

	if ($result['ok']) {
		wp_send_json_success([
			'message' => __('Webhook test successful!', 'axiachat-ai'),
			'result' => $result,
		]);
	} else {
		wp_send_json_error([
			'message' => __('Webhook test failed.', 'axiachat-ai'),
			'result' => $result,
		]);
	}
}

/**
 * AJAX: Toggle webhook active status
 */
add_action('wp_ajax_aichat_custom_webhooks_toggle', 'aichat_ajax_custom_webhooks_toggle');
function aichat_ajax_custom_webhooks_toggle()
{
	check_ajax_referer('aichat_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}

	global $wpdb;
	$table = $wpdb->prefix . 'aichat_custom_webhooks';

	$slug = sanitize_key($_POST['slug'] ?? '');
	$is_active = absint($_POST['is_active'] ?? 0);

	if (empty($slug)) {
		wp_send_json_error(['message' => 'Missing slug']);
	}

	$updated = $wpdb->update(
		$table,
		['is_active' => $is_active, 'updated_at' => current_time('mysql')],
		['slug' => $slug]
	);

	if ($updated === false) {
		wp_send_json_error(['message' => __('Could not update webhook status.', 'axiachat-ai')]);
	}

	wp_send_json_success([
		'message' => $is_active
			? __('Webhook activated.', 'axiachat-ai')
			: __('Webhook paused.', 'axiachat-ai'),
	]);
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
