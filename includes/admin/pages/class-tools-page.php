<?php

/**
 * Tools & Integrations admin page.
 *
 * Surfaces:
 *   1. Global tools settings (enable/disable tool execution per install).
 *   2. Webhook tools table — CRUD backed by wpaic_webhook_tools option.
 *   3. MCP servers table — CRUD backed by Mcp_Registry (wpaic_mcp_servers option).
 *
 * REST endpoints (registered via register_tools_rest_routes() from Plugin):
 *   POST   /wp-ai-chatbot/v1/tools/webhooks           — create webhook tool
 *   GET    /wp-ai-chatbot/v1/tools/webhooks            — list webhook tools
 *   DELETE /wp-ai-chatbot/v1/tools/webhooks/{id}       — delete webhook tool
 *   POST   /wp-ai-chatbot/v1/tools/mcp                 — add MCP server
 *   GET    /wp-ai-chatbot/v1/tools/mcp                 — list MCP servers
 *   DELETE /wp-ai-chatbot/v1/tools/mcp/{id}            — remove MCP server
 *   POST   /wp-ai-chatbot/v1/tools/mcp/{id}/test       — test + discover tools
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Admin\Pages;

use WPAIC\Admin\Admin_Page;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Tools_Page
 *
 * @since 1.0.0
 * @package WPAIC\Admin\Pages
 */
class Tools_Page extends Admin_Page
{
	/** WP option key for webhook tool definitions. */
	public const WEBHOOKS_OPTION = 'wpaic_webhook_tools';

	// =========================================================================
	// Admin_Page contract
	// =========================================================================

	public function get_slug(): string
	{
		return 'uwpaic-tools';
	}

	public function get_title(): string
	{
		return __('Tools & Integrations', 'wp-ai-chatbot');
	}

	/**
	 * Register settings for the Tools page.
	 *
	 * @return void
	 */
	public function register_settings(): void
	{
		register_setting(
			'wpaic_tools_settings',
			'wpaic_tools_enabled',
			[
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 0,
			]
		);

		register_setting(
			'wpaic_tools_settings',
			'wpaic_tools_max_loops',
			[
				'type' => 'integer',
				'sanitize_callback' => static function ($v): int {
					return max(1, min(10, (int) $v));
				},
				'default' => 5,
			]
		);

		add_settings_section(
			'wpaic_tools_general',
			__('Tool Execution', 'wp-ai-chatbot'),
			'__return_null',
			'wpaic_tools_settings'
		);

		add_settings_field(
			'wpaic_tools_enabled',
			__('Enable Tools', 'wp-ai-chatbot'),
			[$this, 'render_field_tools_enabled'],
			'wpaic_tools_settings',
			'wpaic_tools_general'
		);

		add_settings_field(
			'wpaic_tools_max_loops',
			__('Max Continuation Loops', 'wp-ai-chatbot'),
			[$this, 'render_field_max_loops'],
			'wpaic_tools_settings',
			'wpaic_tools_general'
		);
	}

	/**
	 * Render the Tools & Integrations admin page.
	 *
	 * @return void
	 */
	public function render(): void
	{
		$this->render_page_header();
		?>
		<div class="wpaic-admin-page">

			<?php $this->render_settings_form('wpaic_tools_settings', $this->get_slug()); ?>

			<hr />

			<h2><?php esc_html_e('Webhook Tools', 'wp-ai-chatbot'); ?></h2>
			<p class="description">
				<?php esc_html_e(
					'Register HTTP webhook endpoints as AI tools. Each webhook is exposed to the LLM as a callable function.',
					'wp-ai-chatbot'
				); ?>
			</p>

			<?php $this->render_webhook_table(); ?>

			<button type="button" class="button button-secondary wpaic-add-webhook">
				<?php esc_html_e('+ Add Webhook Tool', 'wp-ai-chatbot'); ?>
			</button>

			<hr style="margin-top:24px;" />

			<h2><?php esc_html_e('MCP Servers', 'wp-ai-chatbot'); ?></h2>
			<p class="description">
				<?php esc_html_e(
					'Connect Model Context Protocol servers. Tools discovered from each server are automatically made available to the AI.',
					'wp-ai-chatbot'
				); ?>
			</p>

			<?php $this->render_mcp_table(); ?>

			<button type="button" class="button button-secondary wpaic-add-mcp">
				<?php esc_html_e('+ Add MCP Server', 'wp-ai-chatbot'); ?>
			</button>

		</div>
		<?php
		$this->render_page_footer();
	}

	// =========================================================================
	// Settings field renderers
	// =========================================================================

	/** @return void */
	public function render_field_tools_enabled(): void
	{
		$val = (int) get_option('wpaic_tools_enabled', 0);
		echo '<label>';
		echo '<input type="checkbox" name="wpaic_tools_enabled" value="1" ' . checked(1, $val, false) . ' /> ';
		echo esc_html__('Allow the AI to call registered tools during conversations', 'wp-ai-chatbot');
		echo '</label>';
	}

	/** @return void */
	public function render_field_max_loops(): void
	{
		$val = (int) get_option('wpaic_tools_max_loops', 5);
		echo '<input type="number" name="wpaic_tools_max_loops" value="' . esc_attr($val) . '" min="1" max="10" class="small-text" />';
		echo '<p class="description">' . esc_html__('Maximum tool call → continuation cycles per request (1–10). Default: 5.', 'wp-ai-chatbot') . '</p>';
	}

	// =========================================================================
	// Table renderers
	// =========================================================================

	/** @return void */
	private function render_webhook_table(): void
	{
		$webhooks = $this->get_webhook_tools();
		?>
		<table class="wp-list-table widefat fixed striped" id="wpaic-webhooks-table">
			<thead>
				<tr>
					<th><?php esc_html_e('ID', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Name', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('URL', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Method', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Permission', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Actions', 'wp-ai-chatbot'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if (empty($webhooks)) : ?>
				<tr><td colspan="6"><?php esc_html_e('No webhook tools configured.', 'wp-ai-chatbot'); ?></td></tr>
			<?php else : ?>
				<?php foreach ($webhooks as $wh) : ?>
					<tr data-webhook-id="<?php echo esc_attr($wh['id']); ?>">
						<td><code><?php echo esc_html($wh['id']); ?></code></td>
						<td><?php echo esc_html($wh['name']); ?></td>
						<td><code><?php echo esc_html(substr($wh['webhook_url'] ?? '', 0, 60)); ?></code></td>
						<td><?php echo esc_html(strtoupper($wh['http_method'] ?? 'POST')); ?></td>
						<td><?php echo esc_html($wh['permission'] ?? 'none'); ?></td>
						<td>
							<a href="#" class="wpaic-delete-webhook" data-id="<?php echo esc_attr($wh['id']); ?>">
								<?php esc_html_e('Delete', 'wp-ai-chatbot'); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/** @return void */
	private function render_mcp_table(): void
	{
		$servers = [];

		if (class_exists('WPAIC\Mcp\Mcp_Registry')) {
			$servers = \WPAIC\Mcp\Mcp_Registry::instance()->get_servers();
		}
		?>
		<table class="wp-list-table widefat fixed striped" id="wpaic-mcp-table">
			<thead>
				<tr>
					<th><?php esc_html_e('ID', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Name', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('URL', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Status', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Tools', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Actions', 'wp-ai-chatbot'); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if (empty($servers)) : ?>
				<tr><td colspan="6"><?php esc_html_e('No MCP servers configured.', 'wp-ai-chatbot'); ?></td></tr>
			<?php else : ?>
				<?php foreach ($servers as $srv) :
					$sid = $srv['id'] ?? '';
					$tool_count = 0;
					if (class_exists('WPAIC\Mcp\Mcp_Registry') && '' !== $sid) {
						$tool_count = count(\WPAIC\Mcp\Mcp_Registry::instance()->get_cached_tools($sid));
					}
					?>
					<tr data-server-id="<?php echo esc_attr($sid); ?>">
						<td><code><?php echo esc_html($sid); ?></code></td>
						<td><?php echo esc_html($srv['name'] ?? $sid); ?></td>
						<td><code><?php echo esc_html(substr($srv['url'] ?? '', 0, 60)); ?></code></td>
						<td>
							<span class="wpaic-status-<?php echo esc_attr((bool) ($srv['enabled'] ?? true) ? 'active' : 'inactive'); ?>">
								<?php echo esc_html((bool) ($srv['enabled'] ?? true) ? __('Enabled', 'wp-ai-chatbot') : __('Disabled', 'wp-ai-chatbot')); ?>
							</span>
						</td>
						<td><?php echo esc_html($tool_count); ?></td>
						<td>
							<a href="#" class="wpaic-test-mcp" data-id="<?php echo esc_attr($sid); ?>">
								<?php esc_html_e('Test', 'wp-ai-chatbot'); ?>
							</a>
							&nbsp;|&nbsp;
							<a href="#" class="wpaic-delete-mcp" data-id="<?php echo esc_attr($sid); ?>">
								<?php esc_html_e('Delete', 'wp-ai-chatbot'); ?>
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	// =========================================================================
	// REST callbacks — Webhook tools
	// =========================================================================

	/**
	 * REST: list all webhook tools.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_list_webhooks(\WP_REST_Request $request): \WP_REST_Response
	{
		return new \WP_REST_Response($this->get_webhook_tools(), 200);
	}

	/**
	 * REST: create a webhook tool.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_create_webhook(\WP_REST_Request $request)
	{
		$id = sanitize_key((string) $request->get_param('id'));

		if ('' === $id || ! preg_match('/^[a-z0-9_]{2,64}$/', $id)) {
			return new \WP_Error('invalid_id', __('Invalid webhook ID. Use lowercase letters, digits, and underscores (2–64 chars).', 'wp-ai-chatbot'), ['status' => 400]);
		}

		$webhooks = $this->get_webhook_tools();

		foreach ($webhooks as $wh) {
			if (($wh['id'] ?? '') === $id) {
				return new \WP_Error('duplicate_id', __('A webhook tool with this ID already exists.', 'wp-ai-chatbot'), ['status' => 409]);
			}
		}

		$schema = $request->get_param('parameters');
		if (is_string($schema)) {
			$schema = json_decode($schema, true) ?: [];
		}

		$tool = [
			'id' => $id,
			'name' => sanitize_text_field((string) $request->get_param('name')),
			'description' => sanitize_textarea_field((string) $request->get_param('description')),
			'webhook_url' => esc_url_raw((string) $request->get_param('webhook_url')),
			'http_method' => in_array(strtoupper((string) $request->get_param('http_method')), ['GET', 'POST'], true) ? strtoupper((string) $request->get_param('http_method')) : 'POST',
			'parameters' => is_array($schema) ? $schema : [],
			'static_params' => $request->get_param('static_params') ?: [],
			'permission' => in_array((string) $request->get_param('permission'), ['none', 'user', 'admin'], true) ? (string) $request->get_param('permission') : 'none',
			'timeout' => max(5, min(30, (int) $request->get_param('timeout') ?: 15)),
			'success_message' => sanitize_text_field((string) $request->get_param('success_message')),
			'error_message' => sanitize_text_field((string) $request->get_param('error_message')),
		];

		$webhooks[] = $tool;
		$this->save_webhook_tools($webhooks);

		return new \WP_REST_Response($tool, 201);
	}

	/**
	 * REST: delete a webhook tool by ID.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_delete_webhook(\WP_REST_Request $request)
	{
		$id = sanitize_key((string) $request->get_param('id'));
		$webhooks = $this->get_webhook_tools();
		$found = false;

		$webhooks = array_values(array_filter(
			$webhooks,
			static function (array $wh) use ($id, &$found): bool {
				if (($wh['id'] ?? '') === $id) {
					$found = true;

					return false;
				}

				return true;
			}
		));

		if (! $found) {
			return new \WP_Error('not_found', __('Webhook tool not found.', 'wp-ai-chatbot'), ['status' => 404]);
		}

		$this->save_webhook_tools($webhooks);

		return new \WP_REST_Response(['deleted' => true], 200);
	}

	// =========================================================================
	// REST callbacks — MCP servers
	// =========================================================================

	/**
	 * REST: list all MCP servers.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_list_mcp(\WP_REST_Request $request): \WP_REST_Response
	{
		if (! class_exists('WPAIC\Mcp\Mcp_Registry')) {
			return new \WP_REST_Response([], 200);
		}

		return new \WP_REST_Response(\WPAIC\Mcp\Mcp_Registry::instance()->get_servers(), 200);
	}

	/**
	 * REST: add an MCP server.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_create_mcp(\WP_REST_Request $request)
	{
		if (! class_exists('WPAIC\Mcp\Mcp_Registry')) {
			return new \WP_Error('unavailable', __('MCP support is not available.', 'wp-ai-chatbot'), ['status' => 503]);
		}

		$id = sanitize_key((string) $request->get_param('id'));

		if ('' === $id) {
			return new \WP_Error('invalid_id', __('Server ID is required.', 'wp-ai-chatbot'), ['status' => 400]);
		}

		$server = [
			'id' => $id,
			'name' => sanitize_text_field((string) $request->get_param('name')),
			'url' => esc_url_raw((string) $request->get_param('url')),
			'headers' => $request->get_param('headers') ?: [],
			'timeout' => max(5, min(30, (int) ($request->get_param('timeout') ?: 15))),
			'enabled' => true,
		];

		$ok = \WPAIC\Mcp\Mcp_Registry::instance()->upsert_server($server);

		if (! $ok) {
			return new \WP_Error('save_failed', __('Failed to save server configuration.', 'wp-ai-chatbot'), ['status' => 500]);
		}

		return new \WP_REST_Response($server, 201);
	}

	/**
	 * REST: delete an MCP server by ID.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_delete_mcp(\WP_REST_Request $request)
	{
		if (! class_exists('WPAIC\Mcp\Mcp_Registry')) {
			return new \WP_Error('unavailable', __('MCP support is not available.', 'wp-ai-chatbot'), ['status' => 503]);
		}

		$id = sanitize_key((string) $request->get_param('id'));
		$ok = \WPAIC\Mcp\Mcp_Registry::instance()->delete_server($id);

		return new \WP_REST_Response(['deleted' => $ok], 200);
	}

	/**
	 * REST: test an MCP server connection and sync tool definitions.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_test_mcp(\WP_REST_Request $request)
	{
		if (! class_exists('WPAIC\Mcp\Mcp_Registry')) {
			return new \WP_Error('unavailable', __('MCP support is not available.', 'wp-ai-chatbot'), ['status' => 503]);
		}

		$id = sanitize_key((string) $request->get_param('id'));
		$started = microtime(true);
		$tools = \WPAIC\Mcp\Mcp_Registry::instance()->sync_server_tools($id);
		$elapsed = (int) round((microtime(true) - $started) * 1000);

		if (null === $tools) {
			$client = \WPAIC\Mcp\Mcp_Registry::instance()->get_client($id);
			$err = $client ? $client->get_last_error() : __('Server not found.', 'wp-ai-chatbot');

			return new \WP_REST_Response([
				'success' => false,
				'latency_ms' => $elapsed,
				'error' => $err,
			], 200);
		}

		return new \WP_REST_Response([
			'success' => true,
			'latency_ms' => $elapsed,
			'tools_count' => count($tools),
			'tools' => array_column($tools, 'name'),
		], 200);
	}

	// =========================================================================
	// Webhook storage helpers
	// =========================================================================

	/**
	 * Return all saved webhook tool configurations.
	 *
	 * @return array<int, array>
	 */
	private function get_webhook_tools(): array
	{
		$raw = get_option(self::WEBHOOKS_OPTION, []);

		return is_array($raw) ? $raw : [];
	}

	/**
	 * Persist the webhook tools list.
	 *
	 * @param  array $webhooks
	 * @return bool
	 */
	private function save_webhook_tools(array $webhooks): bool
	{
		return update_option(self::WEBHOOKS_OPTION, $webhooks);
	}
}
