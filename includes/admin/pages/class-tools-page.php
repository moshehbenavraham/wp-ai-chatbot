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

		// Routing settings (Phase 17).
		register_setting(
			'wpaic_tools_settings',
			'wpaic_routing_enabled',
			[
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 0,
			]
		);

		add_settings_section(
			'wpaic_routing_general',
			__('Message Routing', 'wp-ai-chatbot'),
			'__return_null',
			'wpaic_tools_settings'
		);

		add_settings_field(
			'wpaic_routing_enabled',
			__('Enable Macro Router', 'wp-ai-chatbot'),
			[$this, 'render_field_routing_enabled'],
			'wpaic_tools_settings',
			'wpaic_routing_general'
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

			<hr style="margin-top:28px;" />

			<?php $this->render_catalog(); ?>

			<hr style="margin-top:28px;" />

			<?php $this->render_routing_section(); ?>

			<hr style="margin-top:28px;" />

			<?php $this->render_bot_assignments(); ?>

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
							<a href="#" class="wpaic-mcp-auth" data-id="<?php echo esc_attr($sid); ?>"
								data-name="<?php echo esc_attr($srv['name'] ?? $sid); ?>"
								data-auth-type="<?php echo esc_attr($srv['auth_type'] ?? 'none'); ?>"
								data-auth-header="<?php echo esc_attr($srv['auth_header_name'] ?? ''); ?>">
								<?php esc_html_e('Auth', 'wp-ai-chatbot'); ?>
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

	// =========================================================================
	// Action Catalog section (Phase 16)
	// =========================================================================

	/**
	 * Render the Action Catalog inline section.
	 *
	 * Lists all registered tools with their definitions, permission levels,
	 * and a per-tool test runner.
	 *
	 * @return void
	 */
	private function render_catalog(): void
	{
		$rest_url = esc_js(rest_url('wp-ai-chatbot/v1/tools'));
		$nonce = esc_js(wp_create_nonce('wp_rest'));
		?>
		<h2><?php esc_html_e('Action Catalog', 'wp-ai-chatbot'); ?></h2>
		<p class="description">
			<?php esc_html_e(
				'All registered tools (native, webhook, and MCP) available to the AI. Use the Test button to execute a tool with sample inputs.',
				'wp-ai-chatbot'
			); ?>
		</p>

		<div id="wpaic-catalog-wrap">
			<p style="color:#9ca3af;"><?php esc_html_e('Loading catalog…', 'wp-ai-chatbot'); ?></p>
		</div>

		<!-- Tool test drawer (WPAICAdmin.drawer content populated dynamically) -->

		<script>
		(function(){
			var BASE  = '<?php echo $rest_url; ?>';
			var NONCE = '<?php echo $nonce; ?>';

			function apiFetch(path, opts) {
				opts = opts || {};
				opts.headers = Object.assign({'X-WP-Nonce': NONCE, 'Content-Type': 'application/json'}, opts.headers || {});
				return fetch(BASE + path, opts).then(function(r){ return r.json(); });
			}

			function escHtml(s) {
				return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
			}

			function permBadge(p) {
				var colors = {none:'#6b7280', user:'#22d3ee', admin:'#a78bfa'};
				var c = colors[p] || '#6b7280';
				return '<span style="background:' + c + '22;color:' + c + ';border-radius:3px;padding:1px 6px;font-size:11px;">' + escHtml(p || 'none') + '</span>';
			}

			function typeBadge(t) {
				var colors = {native:'#34d399', webhook:'#f59e0b', mcp:'#60a5fa'};
				var c = colors[t] || '#9ca3af';
				return '<span style="background:' + c + '22;color:' + c + ';border-radius:3px;padding:1px 6px;font-size:11px;">' + escHtml(t || '?') + '</span>';
			}

			function openTestDrawer(tool) {
				var props = {};
				try {
					if (tool.schema && tool.schema.properties) {
						props = tool.schema.properties;
					}
				} catch(e) {}

				var fields = '';
				var propNames = Object.keys(props);
				var required = (tool.schema && Array.isArray(tool.schema.required)) ? tool.schema.required : [];

				if (propNames.length === 0) {
					fields = '<p style="color:#6b7280;font-style:italic;"><?php echo esc_js(__('This tool has no input parameters.', 'wp-ai-chatbot')); ?></p>';
				} else {
					fields = propNames.map(function(name){
						var def = props[name];
						var req = required.indexOf(name) >= 0;
						return '<div style="margin-bottom:12px;">' +
							'<label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;">' +
							escHtml(name) + (req ? ' <span style="color:#ef4444">*</span>' : '') +
							(def.description ? ' <em style="color:#6b7280;font-weight:400;">(' + escHtml(def.description) + ')</em>' : '') +
							'</label>' +
							'<input type="text" data-param="' + escHtml(name) + '" ' +
							'placeholder="' + escHtml(def.type || 'string') + '" ' +
							'style="width:100%;background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;" />' +
							'</div>';
					}).join('');
				}

				var html = '<div id="wpaic-test-wrap" style="color:#e5e7eb;">' +
					'<p style="color:#6b7280;font-size:12px;margin:0 0 12px;">' +
					'ID: <code>' + escHtml(tool.id) + '</code> &nbsp;|&nbsp; ' +
					'Type: ' + typeBadge(tool.type) + ' &nbsp;|&nbsp; ' +
					'Permission: ' + permBadge(tool.permission) +
					'</p>' +
					'<div id="wpaic-test-fields">' + fields + '</div>' +
					'<button type="button" id="wpaic-run-test" class="button button-primary" style="margin-top:8px;">' +
					'<?php echo esc_js(__('Run Test', 'wp-ai-chatbot')); ?></button>' +
					'<div id="wpaic-test-result" style="margin-top:16px;"></div>' +
					'</div>';

				if (window.WPAICAdmin && WPAICAdmin.drawer) {
					WPAICAdmin.drawer.open({
						title: '<?php echo esc_js(__('Test Tool', 'wp-ai-chatbot')); ?>: ' + escHtml(tool.name),
						content: html,
						onClose: function(){}
					});
				} else {
					// Fallback: open inline panel.
					var wrap = document.getElementById('wpaic-catalog-wrap');
					var panel = document.createElement('div');
					panel.id = 'wpaic-test-panel';
					panel.style.cssText = 'background:#1f2937;border-radius:8px;padding:20px;margin-top:16px;';
					panel.innerHTML = '<h4 style="margin:0 0 12px;color:#f9fafb;"><?php echo esc_js(__('Test Tool', 'wp-ai-chatbot')); ?>: ' + escHtml(tool.name) + '</h4>' + html;
					var existing = document.getElementById('wpaic-test-panel');
					if (existing) existing.remove();
					wrap.appendChild(panel);
				}

				// Attach run handler (works for both drawer and fallback).
				setTimeout(function(){
					var btn = document.getElementById('wpaic-run-test');
					if (!btn) return;
					btn.addEventListener('click', function(){
						var inputs = {};
						document.querySelectorAll('#wpaic-test-fields [data-param]').forEach(function(el){
							if (el.value.trim()) inputs[el.dataset.param] = el.value.trim();
						});
						var resultEl = document.getElementById('wpaic-test-result');
						resultEl.innerHTML = '<span style="color:#9ca3af;"><?php echo esc_js(__('Running…', 'wp-ai-chatbot')); ?></span>';
						btn.disabled = true;

						apiFetch('/catalog/' + encodeURIComponent(tool.id) + '/test', {
							method: 'POST',
							body: JSON.stringify({inputs: inputs})
						}).then(function(r){
							btn.disabled = false;
							var ok = r.success !== false;
							var color = ok ? '#22d3ee' : '#ef4444';
							var label = ok ? '<?php echo esc_js(__('Result', 'wp-ai-chatbot')); ?>' : '<?php echo esc_js(__('Error', 'wp-ai-chatbot')); ?>';
							resultEl.innerHTML = '<div style="border-left:3px solid ' + color + ';padding:8px 12px;background:#111827;border-radius:0 4px 4px 0;">' +
								'<strong style="color:' + color + ';">' + label + '</strong>' +
								(r.elapsed_ms ? ' <span style="color:#6b7280;font-size:11px;">' + r.elapsed_ms + 'ms</span>' : '') +
								'<pre style="margin:6px 0 0;white-space:pre-wrap;word-break:break-word;font-size:12px;color:#d1d5db;">' +
								escHtml(JSON.stringify(r.output !== undefined ? r.output : r, null, 2)) + '</pre>' +
								'</div>';
						}).catch(function(err){
							btn.disabled = false;
							resultEl.innerHTML = '<span style="color:#ef4444;">Request failed: ' + escHtml(String(err)) + '</span>';
						});
					});
				}, 80);
			}

			apiFetch('/catalog').then(function(data) {
				var wrap = document.getElementById('wpaic-catalog-wrap');
				if (!data || !data.tools) {
					wrap.innerHTML = '<p style="color:#ef4444;"><?php echo esc_js(__('Failed to load catalog.', 'wp-ai-chatbot')); ?></p>';
					return;
				}
				var tools = data.tools;
				if (!tools.length) {
					wrap.innerHTML = '<p style="color:#9ca3af;"><?php echo esc_js(__('No tools are currently registered. Enable Tools above, then add Webhook or MCP tools, or register native tools via wpaic_register_tools.', 'wp-ai-chatbot')); ?></p>';
					return;
				}

				var rows = tools.map(function(t){
					var paramCount = (t.schema && t.schema.properties) ? Object.keys(t.schema.properties).length : 0;
					return '<tr>' +
						'<td><strong>' + escHtml(t.name) + '</strong><br><code style="font-size:11px;">' + escHtml(t.id) + '</code></td>' +
						'<td>' + typeBadge(t.type) + '</td>' +
						'<td style="font-size:12px;color:#d1d5db;">' + escHtml(t.description || '—') + '</td>' +
						'<td>' + permBadge(t.permission) + '</td>' +
						'<td>' + paramCount + '</td>' +
						'<td><a href="#" class="wpaic-test-tool button button-small" data-id="' + escHtml(t.id) + '"><?php echo esc_js(__('Test', 'wp-ai-chatbot')); ?></a></td>' +
					'</tr>';
				}).join('');

				wrap.innerHTML =
					'<table class="wp-list-table widefat fixed striped">' +
					'<thead><tr>' +
					'<th><?php echo esc_js(__('Name / ID', 'wp-ai-chatbot')); ?></th>' +
					'<th style="width:80px;"><?php echo esc_js(__('Type', 'wp-ai-chatbot')); ?></th>' +
					'<th><?php echo esc_js(__('Description', 'wp-ai-chatbot')); ?></th>' +
					'<th style="width:80px;"><?php echo esc_js(__('Permission', 'wp-ai-chatbot')); ?></th>' +
					'<th style="width:60px;"><?php echo esc_js(__('Params', 'wp-ai-chatbot')); ?></th>' +
					'<th style="width:70px;"><?php echo esc_js(__('Actions', 'wp-ai-chatbot')); ?></th>' +
					'</tr></thead>' +
					'<tbody>' + rows + '</tbody>' +
					'</table>';

				// Wire test buttons.
				document.querySelectorAll('.wpaic-test-tool').forEach(function(btn){
					btn.addEventListener('click', function(e){
						e.preventDefault();
						var tid = this.dataset.id;
						var t = tools.find(function(x){ return x.id === tid; });
						if (t) openTestDrawer(t);
					});
				});
			}).catch(function(){
				document.getElementById('wpaic-catalog-wrap').innerHTML =
					'<p style="color:#ef4444;"><?php echo esc_js(__('Error loading catalog.', 'wp-ai-chatbot')); ?></p>';
			});
		})();
		</script>
		<?php
	}

	// =========================================================================
	// REST callbacks — Action Catalog (Phase 16)
	// =========================================================================

	/**
	 * REST: list all registered tools for the Action Catalog.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_list_catalog(\WP_REST_Request $request): \WP_REST_Response
	{
		if (! class_exists('WPAIC\Tools\Tool_Registry')) {
			return new \WP_REST_Response(['tools' => []], 200);
		}

		$registry = \WPAIC\Tools\Tool_Registry::instance();
		$all_tools = $registry->get_all();

		$items = [];

		foreach ($all_tools as $id => $tool) {
			/** @var \WPAIC\Tools\Tool_Interface $tool */
			try {
				$schema = $tool->get_schema();
				$items[] = [
					'id' => $tool->get_id(),
					'name' => $tool->get_name(),
					'description' => $tool->get_description(),
					'permission' => $tool->get_permission(),
					'schema' => $schema,
					'type' => $this->classify_tool_type($tool),
				];
			} catch (\Throwable $e) {
				// Skip tools that throw on metadata access.
				continue;
			}
		}

		return new \WP_REST_Response(['tools' => $items], 200);
	}

	/**
	 * REST: execute a tool with test inputs.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_test_tool(\WP_REST_Request $request)
	{
		if (! class_exists('WPAIC\Tools\Tool_Registry')) {
			return new \WP_Error('unavailable', __('Tool system is not available.', 'wp-ai-chatbot'), ['status' => 503]);
		}

		$tool_id = sanitize_text_field((string) $request->get_param('id'));
		$inputs = $request->get_param('inputs');

		if (! is_array($inputs)) {
			$inputs = [];
		}

		$registry = \WPAIC\Tools\Tool_Registry::instance();
		$tool = $registry->get($tool_id);

		if (! $tool) {
			return new \WP_Error('not_found', __('Tool not found.', 'wp-ai-chatbot'), ['status' => 404]);
		}

		$started = microtime(true);

		try {
			$result = $tool->execute($inputs);
			$elapsed = (int) round((microtime(true) - $started) * 1000);

			return new \WP_REST_Response([
				'success' => true,
				'output' => $result,
				'elapsed_ms' => $elapsed,
			], 200);
		} catch (\Throwable $e) {
			$elapsed = (int) round((microtime(true) - $started) * 1000);

			return new \WP_REST_Response([
				'success' => false,
				'output' => $e->getMessage(),
				'elapsed_ms' => $elapsed,
			], 200);
		}
	}

	/**
	 * Classify a tool instance by type string.
	 *
	 * @param  object $tool Tool instance.
	 * @return string 'native' | 'webhook' | 'mcp'
	 */
	private function classify_tool_type(object $tool): string
	{
		if ($tool instanceof \WPAIC\Tools\Adapters\Webhook_Tool) {
			return 'webhook';
		}
		if ($tool instanceof \WPAIC\Mcp\Mcp_Tool_Adapter) {
			return 'mcp';
		}

		return 'native';
	}

	// =========================================================================
	// Settings field renderers (Phase 17)
	// =========================================================================

	/** @return void */
	public function render_field_routing_enabled(): void
	{
		$val = (int) get_option('wpaic_routing_enabled', 0);
		echo '<label>';
		echo '<input type="checkbox" name="wpaic_routing_enabled" value="1" ' . checked(1, $val, false) . ' /> ';
		echo esc_html__('Match incoming messages against routing rules before calling the AI provider', 'wp-ai-chatbot');
		echo '</label>';
		echo '<p class="description">' . esc_html__('When a rule matches, the configured response is returned directly without consuming API tokens.', 'wp-ai-chatbot') . '</p>';
	}

	// =========================================================================
	// Routing rules section (Phase 17)
	// =========================================================================

	/**
	 * Render the Macro Router rules management section.
	 *
	 * @return void
	 */
	private function render_routing_section(): void
	{
		$rest_url = esc_js(rest_url('wp-ai-chatbot/v1/tools'));
		$nonce = esc_js(wp_create_nonce('wp_rest'));
		?>
		<h2><?php esc_html_e('Routing Rules', 'wp-ai-chatbot'); ?></h2>
		<p class="description">
			<?php esc_html_e(
				'Define pattern-based rules to route messages before they reach the AI provider. Rules are evaluated in priority order (lowest number = first).',
				'wp-ai-chatbot'
			); ?>
		</p>

		<div id="wpaic-routing-wrap">
			<p style="color:#9ca3af;"><?php esc_html_e('Loading rules…', 'wp-ai-chatbot'); ?></p>
		</div>

		<button type="button" class="button button-secondary" id="wpaic-add-rule" style="margin-top:8px;">
			<?php esc_html_e('+ Add Rule', 'wp-ai-chatbot'); ?>
		</button>

		<script>
		(function(){
			var BASE  = '<?php echo $rest_url; ?>';
			var NONCE = '<?php echo $nonce; ?>';

			function apiFetch(path, opts) {
				opts = opts || {};
				opts.headers = Object.assign({'X-WP-Nonce': NONCE, 'Content-Type': 'application/json'}, opts.headers || {});
				return fetch(BASE + path, opts).then(function(r){ return r.json(); });
			}

			function escHtml(s) {
				return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
			}

			function loadRules() {
				apiFetch('/routing').then(function(data) {
					var wrap = document.getElementById('wpaic-routing-wrap');
					var rules = data.rules || [];
					if (!rules.length) {
						wrap.innerHTML = '<p style="color:#9ca3af;"><?php echo esc_js(__('No routing rules configured. Click "+ Add Rule" to create one.', 'wp-ai-chatbot')); ?></p>';
						return;
					}
					var rows = rules.map(function(r){
						var typeBg = r.pattern_type === 'regex' ? '#a78bfa22' : '#22d3ee22';
						var typeColor = r.pattern_type === 'regex' ? '#a78bfa' : '#22d3ee';
						var actBg = r.action_type === 'call_tool' ? '#f59e0b22' : '#34d39922';
						var actColor = r.action_type === 'call_tool' ? '#f59e0b' : '#34d399';
						var enabledBg = r.enabled ? '#34d39922' : '#6b728022';
						var enabledColor = r.enabled ? '#34d399' : '#6b7280';
						return '<tr data-rule-id="'+escHtml(r.id)+'">' +
							'<td style="font-size:12px;">'+escHtml(r.priority)+'</td>' +
							'<td><strong>'+escHtml(r.name)+'</strong></td>' +
							'<td style="font-size:12px;"><span style="background:'+typeBg+';color:'+typeColor+';border-radius:3px;padding:1px 5px;">'+escHtml(r.pattern_type)+'</span> <code>'+escHtml(r.pattern)+'</code></td>' +
							'<td style="font-size:12px;"><span style="background:'+actBg+';color:'+actColor+';border-radius:3px;padding:1px 5px;">'+escHtml(r.action_type)+'</span></td>' +
							'<td style="font-size:12px;overflow:hidden;max-width:200px;white-space:nowrap;text-overflow:ellipsis;">'+escHtml(r.action_data)+'</td>' +
							'<td><span style="background:'+enabledBg+';color:'+enabledColor+';border-radius:3px;padding:1px 5px;font-size:11px;">'+(r.enabled ? '<?php echo esc_js(__('On', 'wp-ai-chatbot')); ?>' : '<?php echo esc_js(__('Off', 'wp-ai-chatbot')); ?>')+'</span></td>' +
							'<td><a href="#" class="wpaic-edit-rule" data-rule="'+escHtml(JSON.stringify(r))+'">Edit</a>&nbsp;|&nbsp;<a href="#" class="wpaic-delete-rule" data-id="'+escHtml(r.id)+'" style="color:#ef4444;">Delete</a></td>' +
							'</tr>';
					}).join('');
					wrap.innerHTML = '<table class="wp-list-table widefat fixed striped">' +
						'<thead><tr><th style="width:50px;"><?php echo esc_js(__('Priority', 'wp-ai-chatbot')); ?></th><th><?php echo esc_js(__('Name', 'wp-ai-chatbot')); ?></th><th><?php echo esc_js(__('Pattern', 'wp-ai-chatbot')); ?></th><th style="width:100px;"><?php echo esc_js(__('Action', 'wp-ai-chatbot')); ?></th><th><?php echo esc_js(__('Response/Tool', 'wp-ai-chatbot')); ?></th><th style="width:50px;"><?php echo esc_js(__('Status', 'wp-ai-chatbot')); ?></th><th style="width:100px;"><?php echo esc_js(__('Actions', 'wp-ai-chatbot')); ?></th></tr></thead>' +
						'<tbody>' + rows + '</tbody></table>';
					wireRuleButtons();
				}).catch(function(){
					document.getElementById('wpaic-routing-wrap').innerHTML = '<p style="color:#ef4444;"><?php echo esc_js(__('Failed to load rules.', 'wp-ai-chatbot')); ?></p>';
				});
			}

			function openRuleDrawer(rule) {
				rule = rule || {};
				var html = '<div style="color:#e5e7eb;">' +
					'<div style="margin-bottom:12px;"><label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;"><?php echo esc_js(__('Rule Name', 'wp-ai-chatbot')); ?> <span style="color:#ef4444">*</span></label>' +
					'<input type="text" id="wr-name" value="'+escHtml(rule.name||'')+'" style="width:100%;background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;" /></div>' +
					'<div style="margin-bottom:12px;"><label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;"><?php echo esc_js(__('Pattern Type', 'wp-ai-chatbot')); ?></label>' +
					'<select id="wr-ptype" style="width:100%;background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;">' +
					'<option value="keyword"'+(rule.pattern_type==='keyword'?' selected':'')+'>Keyword (case-insensitive)</option>' +
					'<option value="regex"'+(rule.pattern_type==='regex'?' selected':'')+'>Regex</option>' +
					'</select></div>' +
					'<div style="margin-bottom:12px;"><label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;"><?php echo esc_js(__('Pattern', 'wp-ai-chatbot')); ?> <span style="color:#ef4444">*</span></label>' +
					'<input type="text" id="wr-pattern" value="'+escHtml(rule.pattern||'')+'" placeholder="e.g. book appointment" style="width:100%;background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;" /></div>' +
					'<div style="margin-bottom:12px;"><label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;"><?php echo esc_js(__('Action Type', 'wp-ai-chatbot')); ?></label>' +
					'<select id="wr-atype" style="width:100%;background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;">' +
					'<option value="direct_response"'+(rule.action_type!=='call_tool'?' selected':'')+'>Direct Response</option>' +
					'<option value="call_tool"'+(rule.action_type==='call_tool'?' selected':'')+'>Call Tool</option>' +
					'</select></div>' +
					'<div style="margin-bottom:12px;"><label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;" id="wr-adata-label"><?php echo esc_js(__('Response Text / Tool ID', 'wp-ai-chatbot')); ?> <span style="color:#ef4444">*</span></label>' +
					'<textarea id="wr-adata" rows="4" style="width:100%;background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;resize:vertical;">'+escHtml(rule.action_data||'')+'</textarea></div>' +
					'<div style="display:flex;gap:16px;margin-bottom:16px;">' +
					'<div style="flex:1;"><label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;"><?php echo esc_js(__('Priority', 'wp-ai-chatbot')); ?></label>' +
					'<input type="number" id="wr-priority" value="'+(rule.priority||10)+'" min="1" max="999" style="width:80px;background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;" /></div>' +
					'<div><label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;"><?php echo esc_js(__('Enabled', 'wp-ai-chatbot')); ?></label>' +
					'<input type="checkbox" id="wr-enabled"'+(rule.enabled!==false?' checked':'')+' style="width:18px;height:18px;margin-top:8px;" /></div>' +
					'</div>' +
					'<button type="button" id="wr-save" class="button button-primary"><?php echo esc_js(__('Save Rule', 'wp-ai-chatbot')); ?></button>' +
					'<span id="wr-status" style="margin-left:12px;font-size:12px;"></span>' +
					'</div>';

				if (window.WPAICAdmin && WPAICAdmin.drawer) {
					WPAICAdmin.drawer.open({
						title: rule.id ? '<?php echo esc_js(__('Edit Rule', 'wp-ai-chatbot')); ?>' : '<?php echo esc_js(__('New Routing Rule', 'wp-ai-chatbot')); ?>',
						content: html,
						onClose: function(){}
					});
				}

				setTimeout(function(){
					var btn = document.getElementById('wr-save');
					if (!btn) return;
					btn.addEventListener('click', function(){
						var payload = {
							id: rule.id || '',
							name: document.getElementById('wr-name').value.trim(),
							pattern: document.getElementById('wr-pattern').value.trim(),
							pattern_type: document.getElementById('wr-ptype').value,
							action_type: document.getElementById('wr-atype').value,
							action_data: document.getElementById('wr-adata').value.trim(),
							priority: parseInt(document.getElementById('wr-priority').value) || 10,
							enabled: document.getElementById('wr-enabled').checked
						};
						var statusEl = document.getElementById('wr-status');
						btn.disabled = true;
						statusEl.textContent = '<?php echo esc_js(__('Saving…', 'wp-ai-chatbot')); ?>';
						apiFetch('/routing', {method:'POST', body:JSON.stringify(payload)}).then(function(r){
							btn.disabled = false;
							if (r.id) {
								statusEl.style.color = '#34d399';
								statusEl.textContent = '<?php echo esc_js(__('Saved!', 'wp-ai-chatbot')); ?>';
								if (window.WPAICAdmin && WPAICAdmin.drawer) WPAICAdmin.drawer.close();
								loadRules();
							} else {
								statusEl.style.color = '#ef4444';
								statusEl.textContent = r.message || '<?php echo esc_js(__('Error saving.', 'wp-ai-chatbot')); ?>';
							}
						}).catch(function(){
							btn.disabled = false;
							statusEl.style.color = '#ef4444';
							statusEl.textContent = '<?php echo esc_js(__('Request failed.', 'wp-ai-chatbot')); ?>';
						});
					});
				}, 80);
			}

			function wireRuleButtons() {
				document.querySelectorAll('.wpaic-edit-rule').forEach(function(a){
					a.addEventListener('click', function(e){
						e.preventDefault();
						var rule = {};
						try { rule = JSON.parse(this.dataset.rule); } catch(ex){}
						openRuleDrawer(rule);
					});
				});
				document.querySelectorAll('.wpaic-delete-rule').forEach(function(a){
					a.addEventListener('click', function(e){
						e.preventDefault();
						var id = this.dataset.id;
						var confirm_fn = (window.WPAICAdmin && WPAICAdmin.modal)
							? WPAICAdmin.modal.confirm({title:'<?php echo esc_js(__('Delete Rule', 'wp-ai-chatbot')); ?>',message:'<?php echo esc_js(__('Delete this routing rule? This cannot be undone.', 'wp-ai-chatbot')); ?>',destructive:true})
							: Promise.resolve(window.confirm('<?php echo esc_js(__('Delete this routing rule?', 'wp-ai-chatbot')); ?>'));
						confirm_fn.then(function(ok){
							if (!ok) return;
							apiFetch('/routing/'+encodeURIComponent(id), {method:'DELETE'}).then(loadRules);
						});
					});
				});
			}

			document.getElementById('wpaic-add-rule').addEventListener('click', function(){
				openRuleDrawer(null);
			});

			loadRules();
		})();
		</script>
		<?php
	}

	// =========================================================================
	// Bot tool assignment section (Phase 17)
	// =========================================================================

	/**
	 * Render the Bot Tool Assignments section.
	 *
	 * Allows admins to configure which tools are enabled per bot slug.
	 * Tools not explicitly assigned here fall back to the default empty set
	 * (tools are opt-in per bot).
	 *
	 * @return void
	 */
	private function render_bot_assignments(): void
	{
		$rest_url = esc_js(rest_url('wp-ai-chatbot/v1/tools'));
		$nonce = esc_js(wp_create_nonce('wp_rest'));
		?>
		<h2><?php esc_html_e('Bot Tool Assignments', 'wp-ai-chatbot'); ?></h2>
		<p class="description">
			<?php esc_html_e(
				'Control which tools are available per bot. Enter a bot slug and select enabled tools. Tools not assigned here default to none (opt-in per bot).',
				'wp-ai-chatbot'
			); ?>
		</p>

		<div style="display:flex;gap:12px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap;">
			<div>
				<label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;">
					<?php esc_html_e('Bot Slug', 'wp-ai-chatbot'); ?>
				</label>
				<input type="text" id="wpaic-bot-slug-input" placeholder="default"
					style="background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;width:180px;" />
			</div>
			<button type="button" class="button button-secondary" id="wpaic-load-bot-tools">
				<?php esc_html_e('Load Assignments', 'wp-ai-chatbot'); ?>
			</button>
		</div>

		<div id="wpaic-bot-tools-wrap" style="display:none;">
			<div id="wpaic-bot-tools-list" style="margin-bottom:12px;"></div>
			<button type="button" class="button button-primary" id="wpaic-save-bot-tools">
				<?php esc_html_e('Save Assignments', 'wp-ai-chatbot'); ?>
			</button>
			<span id="wpaic-bot-tools-status" style="margin-left:12px;font-size:12px;"></span>
		</div>

		<script>
		(function(){
			var BASE  = '<?php echo $rest_url; ?>';
			var NONCE = '<?php echo $nonce; ?>';
			var allTools = [];

			function apiFetch(path, opts) {
				opts = opts || {};
				opts.headers = Object.assign({'X-WP-Nonce': NONCE, 'Content-Type': 'application/json'}, opts.headers || {});
				return fetch(BASE + path, opts).then(function(r){ return r.json(); });
			}

			function escHtml(s) {
				return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
			}

			document.getElementById('wpaic-load-bot-tools').addEventListener('click', function(){
				var slug = document.getElementById('wpaic-bot-slug-input').value.trim().replace(/[^a-z0-9_-]/gi,'').toLowerCase() || 'default';
				var wrap = document.getElementById('wpaic-bot-tools-wrap');
				var listEl = document.getElementById('wpaic-bot-tools-list');

				// Load catalog + current bot assignments in parallel.
				Promise.all([
					apiFetch('/catalog'),
					apiFetch('/bots/'+encodeURIComponent(slug)+'/tools')
				]).then(function(results){
					allTools = (results[0].tools || []);
					var enabled = results[1].enabled || [];

					if (!allTools.length) {
						listEl.innerHTML = '<p style="color:#9ca3af;"><?php echo esc_js(__('No tools are registered yet.', 'wp-ai-chatbot')); ?></p>';
						wrap.style.display = 'block';
						return;
					}

					var checkboxes = allTools.map(function(t){
						var checked = enabled.indexOf(t.id) >= 0 ? ' checked' : '';
						return '<label style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #1f2937;cursor:pointer;">' +
							'<input type="checkbox" value="'+escHtml(t.id)+'"'+checked+' style="width:16px;height:16px;" />' +
							'<span><strong style="font-size:13px;">'+escHtml(t.name)+'</strong>' +
							'<span style="font-size:11px;color:#6b7280;margin-left:8px;">'+escHtml(t.id)+'</span></span>' +
							'</label>';
					}).join('');

					listEl.innerHTML = '<div style="max-height:300px;overflow-y:auto;border:1px solid #374151;border-radius:6px;padding:8px 12px;background:#111827;">'+checkboxes+'</div>';
					wrap.style.display = 'block';
					document.getElementById('wpaic-bot-tools-status').textContent = '';
				}).catch(function(){
					listEl.innerHTML = '<p style="color:#ef4444;"><?php echo esc_js(__('Failed to load.', 'wp-ai-chatbot')); ?></p>';
					wrap.style.display = 'block';
				});
			});

			document.getElementById('wpaic-save-bot-tools').addEventListener('click', function(){
				var slug = document.getElementById('wpaic-bot-slug-input').value.trim().replace(/[^a-z0-9_-]/gi,'').toLowerCase() || 'default';
				var enabled = [];
				document.querySelectorAll('#wpaic-bot-tools-list input[type="checkbox"]:checked').forEach(function(cb){
					enabled.push(cb.value);
				});
				var btn = this;
				var statusEl = document.getElementById('wpaic-bot-tools-status');
				btn.disabled = true;
				statusEl.textContent = '<?php echo esc_js(__('Saving…', 'wp-ai-chatbot')); ?>';

				apiFetch('/bots/'+encodeURIComponent(slug)+'/tools', {method:'PUT', body:JSON.stringify({enabled:enabled})}).then(function(r){
					btn.disabled = false;
					if (r.saved) {
						statusEl.style.color = '#34d399';
						statusEl.textContent = '<?php echo esc_js(__('Saved!', 'wp-ai-chatbot')); ?>';
					} else {
						statusEl.style.color = '#ef4444';
						statusEl.textContent = r.message || '<?php echo esc_js(__('Error.', 'wp-ai-chatbot')); ?>';
					}
				}).catch(function(){
					btn.disabled = false;
					statusEl.style.color = '#ef4444';
					statusEl.textContent = '<?php echo esc_js(__('Request failed.', 'wp-ai-chatbot')); ?>';
				});
			});

			// MCP auth drawer wiring (hoisted to share apiFetch).
			document.addEventListener('click', function(e){
				var authLink = e.target.closest('.wpaic-mcp-auth');
				if (!authLink) return;
				e.preventDefault();
				var sid = authLink.dataset.id;
				var sname = authLink.dataset.name || sid;
				var authType = authLink.dataset.authType || 'none';
				var headerName = authLink.dataset.authHeader || '';

				var html = '<div style="color:#e5e7eb;">' +
					'<div style="margin-bottom:12px;"><label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;"><?php echo esc_js(__('Auth Type', 'wp-ai-chatbot')); ?></label>' +
					'<select id="mcp-auth-type" style="width:100%;background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;">' +
					'<option value="none"'+(authType==='none'?' selected':'')+'>None</option>' +
					'<option value="bearer"'+(authType==='bearer'?' selected':'')+'>Bearer Token</option>' +
					'<option value="api_key"'+(authType==='api_key'?' selected':'')+'>API Key (custom header)</option>' +
					'<option value="custom"'+(authType==='custom'?' selected':'')+'>Custom Header</option>' +
					'</select></div>' +
					'<div id="mcp-header-row" style="margin-bottom:12px;'+(authType==='bearer'||authType==='none'?'display:none;':'')+'">' +
					'<label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;"><?php echo esc_js(__('Header Name', 'wp-ai-chatbot')); ?></label>' +
					'<input type="text" id="mcp-header-name" value="'+escHtml(headerName)+'" placeholder="X-API-Key" style="width:100%;background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;" /></div>' +
					'<div id="mcp-value-row" style="margin-bottom:16px;'+(authType==='none'?'display:none;':'')+'">' +
					'<label style="display:block;font-size:12px;color:#d1d5db;margin-bottom:4px;"><?php echo esc_js(__('Secret Value', 'wp-ai-chatbot')); ?> <em style="color:#6b7280;font-weight:400;">(<?php echo esc_js(__('leave blank to keep existing', 'wp-ai-chatbot')); ?>)</em></label>' +
					'<input type="password" id="mcp-auth-value" autocomplete="new-password" style="width:100%;background:#111827;border:1px solid #374151;border-radius:4px;padding:6px 10px;color:#f9fafb;font-size:13px;" /></div>' +
					'<button type="button" id="mcp-auth-save" class="button button-primary"><?php echo esc_js(__('Save Auth', 'wp-ai-chatbot')); ?></button>' +
					'<span id="mcp-auth-status" style="margin-left:12px;font-size:12px;"></span>' +
					'</div>';

				if (window.WPAICAdmin && WPAICAdmin.drawer) {
					WPAICAdmin.drawer.open({title:'<?php echo esc_js(__('Configure Auth', 'wp-ai-chatbot')); ?>: '+escHtml(sname), content:html, onClose:function(){}});
				}

				setTimeout(function(){
					var typeEl = document.getElementById('mcp-auth-type');
					var headerRow = document.getElementById('mcp-header-row');
					var valueRow = document.getElementById('mcp-value-row');

					function updateVisibility() {
						var t = typeEl ? typeEl.value : 'none';
						if (headerRow) headerRow.style.display = (t==='bearer'||t==='none') ? 'none' : '';
						if (valueRow) valueRow.style.display = t==='none' ? 'none' : '';
					}
					if (typeEl) typeEl.addEventListener('change', updateVisibility);

					var saveBtn = document.getElementById('mcp-auth-save');
					if (!saveBtn) return;
					saveBtn.addEventListener('click', function(){
						var payload = {
							auth_type: document.getElementById('mcp-auth-type').value,
							auth_header_name: (document.getElementById('mcp-header-name') || {}).value || '',
							value: document.getElementById('mcp-auth-value').value
						};
						var statusEl = document.getElementById('mcp-auth-status');
						saveBtn.disabled = true;
						statusEl.textContent = '<?php echo esc_js(__('Saving…', 'wp-ai-chatbot')); ?>';
						apiFetch('/mcp/'+encodeURIComponent(sid)+'/auth', {method:'POST', body:JSON.stringify(payload)}).then(function(r){
							saveBtn.disabled = false;
							if (r.saved) {
								statusEl.style.color = '#34d399';
								statusEl.textContent = '<?php echo esc_js(__('Saved!', 'wp-ai-chatbot')); ?>';
								// Update the link's data attributes for next open.
								authLink.dataset.authType = payload.auth_type;
								authLink.dataset.authHeader = payload.auth_header_name;
								if (window.WPAICAdmin && WPAICAdmin.drawer) WPAICAdmin.drawer.close();
							} else {
								statusEl.style.color = '#ef4444';
								statusEl.textContent = r.message || '<?php echo esc_js(__('Error saving.', 'wp-ai-chatbot')); ?>';
							}
						}).catch(function(){
							saveBtn.disabled = false;
							statusEl.style.color = '#ef4444';
							statusEl.textContent = '<?php echo esc_js(__('Request failed.', 'wp-ai-chatbot')); ?>';
						});
					});
				}, 80);
			});
		})();
		</script>
		<?php
	}

	// =========================================================================
	// REST callbacks — Routing rules (Phase 17)
	// =========================================================================

	/**
	 * REST: list all routing rules.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_list_rules(\WP_REST_Request $request): \WP_REST_Response
	{
		if (! class_exists('WPAIC\Runtime\Macro_Router')) {
			return new \WP_REST_Response(['rules' => []], 200);
		}

		return new \WP_REST_Response(['rules' => \WPAIC\Runtime\Macro_Router::get_rules()], 200);
	}

	/**
	 * REST: create or update a routing rule.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_upsert_rule(\WP_REST_Request $request)
	{
		if (! class_exists('WPAIC\Runtime\Macro_Router')) {
			return new \WP_Error('unavailable', __('Macro Router is not available.', 'wp-ai-chatbot'), ['status' => 503]);
		}

		$rule = [
			'id' => (string) $request->get_param('id'),
			'name' => (string) $request->get_param('name'),
			'pattern' => (string) $request->get_param('pattern'),
			'pattern_type' => (string) $request->get_param('pattern_type'),
			'action_type' => (string) $request->get_param('action_type'),
			'action_data' => (string) $request->get_param('action_data'),
			'priority' => (int) $request->get_param('priority'),
			'enabled' => (bool) $request->get_param('enabled'),
		];

		$id = \WPAIC\Runtime\Macro_Router::upsert_rule($rule);

		if ('' === $id) {
			return new \WP_Error('invalid', __('Rule must have a non-empty pattern and action data.', 'wp-ai-chatbot'), ['status' => 400]);
		}

		return new \WP_REST_Response(['id' => $id], 200);
	}

	/**
	 * REST: delete a routing rule by ID.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_delete_rule(\WP_REST_Request $request)
	{
		if (! class_exists('WPAIC\Runtime\Macro_Router')) {
			return new \WP_Error('unavailable', __('Macro Router is not available.', 'wp-ai-chatbot'), ['status' => 503]);
		}

		$id = sanitize_key((string) $request->get_param('id'));
		$ok = \WPAIC\Runtime\Macro_Router::delete_rule($id);

		if (! $ok) {
			return new \WP_Error('not_found', __('Rule not found.', 'wp-ai-chatbot'), ['status' => 404]);
		}

		return new \WP_REST_Response(['deleted' => true], 200);
	}

	// =========================================================================
	// REST callbacks — MCP auth (Phase 17)
	// =========================================================================

	/**
	 * REST: save MCP server auth configuration.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_save_mcp_auth(\WP_REST_Request $request)
	{
		if (! class_exists('WPAIC\Mcp\Mcp_Registry')) {
			return new \WP_Error('unavailable', __('MCP support is not available.', 'wp-ai-chatbot'), ['status' => 503]);
		}

		$id = sanitize_key((string) $request->get_param('id'));
		$auth_type = sanitize_key((string) $request->get_param('auth_type'));
		$header_name = sanitize_text_field((string) $request->get_param('auth_header_name'));
		$value = (string) $request->get_param('value'); // raw — will be encrypted

		$registry = \WPAIC\Mcp\Mcp_Registry::instance();
		$ok = $registry->save_auth($id, $auth_type, $header_name, $value);

		if (! $ok) {
			return new \WP_Error('save_failed', __('Failed to save auth config. Ensure the server exists.', 'wp-ai-chatbot'), ['status' => 400]);
		}

		return new \WP_REST_Response(['saved' => true], 200);
	}

	// =========================================================================
	// REST callbacks — Bot tool assignments (Phase 17)
	// =========================================================================

	/**
	 * REST: get tool assignments for a bot slug.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_get_bot_tools(\WP_REST_Request $request): \WP_REST_Response
	{
		$slug = sanitize_key((string) $request->get_param('slug'));
		$enabled = get_option("wpaic_bot_tools_{$slug}", null);

		if (null === $enabled) {
			// No explicit assignment — return empty (falls back to filter default).
			return new \WP_REST_Response(['slug' => $slug, 'enabled' => []], 200);
		}

		if (is_string($enabled)) {
			$enabled = json_decode($enabled, true) ?: [];
		}

		return new \WP_REST_Response(['slug' => $slug, 'enabled' => (array) $enabled], 200);
	}

	/**
	 * REST: save tool assignments for a bot slug.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_save_bot_tools(\WP_REST_Request $request)
	{
		$slug = sanitize_key((string) $request->get_param('slug'));
		$enabled = $request->get_param('enabled');

		if (! is_array($enabled)) {
			$enabled = [];
		}

		// Validate tool IDs against the registry.
		if (class_exists('WPAIC\Tools\Tool_Registry')) {
			$registry = \WPAIC\Tools\Tool_Registry::instance();
			$all_ids = array_keys($registry->get_all());
			$enabled = array_values(array_intersect(
				array_map('sanitize_key', $enabled),
				$all_ids
			));
		} else {
			$enabled = array_values(array_map('sanitize_key', $enabled));
		}

		update_option("wpaic_bot_tools_{$slug}", $enabled, false);

		return new \WP_REST_Response(['saved' => true, 'slug' => $slug, 'enabled' => $enabled], 200);
	}
}
