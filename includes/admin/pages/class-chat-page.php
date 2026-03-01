<?php

/**
 * Chat Behavior Admin Page (uwpaic-chat).
 *
 * Provides three tabbed settings sections:
 *   1. WooCommerce Integration — enable product context and tools.
 *   2. Lead Capture — enable lead capture tool and configure notification.
 *   3. Live Agent Handoff — enable human-agent handoff and configure queue.
 *
 * Also renders a Leads Inbox table for reviewing and managing captured leads,
 * and a Live Queue panel showing sessions waiting for an agent.
 *
 * REST endpoints registered from Plugin::register_chat_rest_routes():
 *   GET    /wp-ai-chatbot/v1/chat/leads            — list leads (paginated)
 *   POST   /wp-ai-chatbot/v1/chat/leads/{id}/status — update lead status
 *   DELETE /wp-ai-chatbot/v1/chat/leads/{id}        — delete lead
 *   GET    /wp-ai-chatbot/v1/chat/livechat/queue    — pending handoff queue
 *   POST   /wp-ai-chatbot/v1/chat/livechat/{token}/status — update session status
 *
 * @package WP_AI_Chatbot
 * @since   1.1.0
 */

namespace WPAIC\Admin\Pages;

use WPAIC\Admin\Admin_Page;
use WPAIC\Integrations\Lead_Manager;
use WPAIC\Integrations\Livechat_Manager;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Chat_Page
 *
 * @since   1.1.0
 * @package WPAIC\Admin\Pages
 */
class Chat_Page extends Admin_Page
{
	/** {@inheritdoc} */
	public function get_slug(): string
	{
		return 'uwpaic-chat';
	}

	/** {@inheritdoc} */
	public function get_title(): string
	{
		return __('Chat Behavior', 'wp-ai-chatbot');
	}

	/** {@inheritdoc} */
	public function get_tabs(): array
	{
		return [
			'woocommerce' => __('WooCommerce', 'wp-ai-chatbot'),
			'leads' => __('Lead Capture', 'wp-ai-chatbot'),
			'livechat' => __('Live Agent Handoff', 'wp-ai-chatbot'),
			'inbox' => __('Leads Inbox', 'wp-ai-chatbot'),
		];
	}

	// =========================================================================
	// Settings registration
	// =========================================================================

	/** {@inheritdoc} */
	public function register_settings(): void
	{
		// --- WooCommerce tab ---
		register_setting('wpaic_wc_group', 'wpaic_wc_enabled', [
			'type' => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default' => false,
		]);

		register_setting('wpaic_wc_group', 'wpaic_wc_cart_context_enabled', [
			'type' => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default' => true,
		]);

		register_setting('wpaic_wc_group', 'wpaic_wc_orders_context_enabled', [
			'type' => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default' => true,
		]);

		// --- Lead capture tab ---
		register_setting('wpaic_leads_group', Lead_Manager::SETTINGS_OPTION, [
			'type' => 'object',
			'sanitize_callback' => [$this, 'sanitize_lead_settings'],
			'default' => [],
		]);

		// --- Livechat tab ---
		register_setting('wpaic_livechat_group', Livechat_Manager::SETTINGS_OPTION, [
			'type' => 'object',
			'sanitize_callback' => [$this, 'sanitize_livechat_settings'],
			'default' => [],
		]);
	}

	// =========================================================================
	// Render
	// =========================================================================

	/** {@inheritdoc} */
	public function render(): void
	{
		$tab = $this->get_active_tab('woocommerce');
		$this->render_page_header();
		?>
		<div class="wpaic-tab-content">
			<?php
			switch ($tab) {
				case 'woocommerce':
					$this->render_woocommerce_tab();
					break;
				case 'leads':
					$this->render_leads_tab();
					break;
				case 'livechat':
					$this->render_livechat_tab();
					break;
				case 'inbox':
					$this->render_inbox_tab();
					break;
				default:
					$this->render_woocommerce_tab();
			}
		?>
		</div>
		<?php
		$this->render_page_footer();
	}

	// =========================================================================
	// Tab renderers
	// =========================================================================

	/**
	 * Render the WooCommerce integration settings tab.
	 *
	 * @return void
	 */
	private function render_woocommerce_tab(): void
	{
		$wc_active = class_exists('WooCommerce');
		?>
		<?php if ($wc_active) : ?>
			<div class="notice notice-success inline" style="margin:0 0 16px;">
				<p>
					<?php
					printf(
						/* translators: %d: published product count */
						esc_html__('WooCommerce detected — %d published products found.', 'wp-ai-chatbot'),
						(int) wp_count_posts('product')->publish
					);
			?>
				</p>
			</div>
		<?php else : ?>
			<div class="notice notice-warning inline" style="margin:0 0 16px;">
				<p><?php esc_html_e('WooCommerce is not active. Install and activate WooCommerce to enable product integration.', 'wp-ai-chatbot'); ?></p>
			</div>
		<?php endif; ?>

		<form method="post" action="options.php">
			<?php settings_fields('wpaic_wc_group'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Enable WooCommerce Integration', 'wp-ai-chatbot'); ?></th>
					<td>
						<label>
							<input type="checkbox"
								name="wpaic_wc_enabled"
								value="1"
								<?php checked(get_option('wpaic_wc_enabled', '0')); ?>
								<?php disabled(! $wc_active); ?>
							/>
							<?php esc_html_e('Allow the chatbot to use product data, cart, and order context', 'wp-ai-chatbot'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Inject Cart Context', 'wp-ai-chatbot'); ?></th>
					<td>
						<label>
							<input type="checkbox"
								name="wpaic_wc_cart_context_enabled"
								value="1"
								<?php checked(get_option('wpaic_wc_cart_context_enabled', '1')); ?>
							/>
							<?php esc_html_e('Include the customer\'s current cart in the system prompt', 'wp-ai-chatbot'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Inject Order History Context', 'wp-ai-chatbot'); ?></th>
					<td>
						<label>
							<input type="checkbox"
								name="wpaic_wc_orders_context_enabled"
								value="1"
								<?php checked(get_option('wpaic_wc_orders_context_enabled', '1')); ?>
							/>
							<?php esc_html_e('Include recent orders for logged-in customers in the system prompt', 'wp-ai-chatbot'); ?>
						</label>
						<p class="description"><?php esc_html_e('Product catalog can be indexed via Knowledge Base → Add Source → WooCommerce Products.', 'wp-ai-chatbot'); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the lead capture settings tab.
	 *
	 * @return void
	 */
	private function render_leads_tab(): void
	{
		$settings = Lead_Manager::get_settings();
		?>
		<form method="post" action="options.php">
			<?php settings_fields('wpaic_leads_group'); ?>
			<input type="hidden" name="<?php echo esc_attr(Lead_Manager::SETTINGS_OPTION); ?>[_nonce]" value="<?php echo esc_attr(wp_create_nonce('wpaic_lead_settings')); ?>" />
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Enable Lead Capture', 'wp-ai-chatbot'); ?></th>
					<td>
						<label>
							<input type="checkbox"
								name="<?php echo esc_attr(Lead_Manager::SETTINGS_OPTION); ?>[enabled]"
								value="1"
								<?php checked(! empty($settings['enabled'])); ?>
							/>
							<?php esc_html_e('Allow the chatbot to collect and save visitor contact information', 'wp-ai-chatbot'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Email Notification', 'wp-ai-chatbot'); ?></th>
					<td>
						<label>
							<input type="checkbox"
								name="<?php echo esc_attr(Lead_Manager::SETTINGS_OPTION); ?>[notify_enabled]"
								value="1"
								<?php checked(! empty($settings['notify_enabled'])); ?>
							/>
							<?php esc_html_e('Send an email notification when a new lead is captured', 'wp-ai-chatbot'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpaic_lead_notify_email"><?php esc_html_e('Notification Email', 'wp-ai-chatbot'); ?></label>
					</th>
					<td>
						<input type="email"
							id="wpaic_lead_notify_email"
							name="<?php echo esc_attr(Lead_Manager::SETTINGS_OPTION); ?>[notify_email]"
							value="<?php echo esc_attr($settings['notify_email']); ?>"
							class="regular-text"
							placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
						/>
						<p class="description"><?php esc_html_e('Leave blank to use the admin email address.', 'wp-ai-chatbot'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpaic_lead_retention_days"><?php esc_html_e('Retention Period (days)', 'wp-ai-chatbot'); ?></label>
					</th>
					<td>
						<input type="number"
							id="wpaic_lead_retention_days"
							name="<?php echo esc_attr(Lead_Manager::SETTINGS_OPTION); ?>[retention_days]"
							value="<?php echo esc_attr((string) ($settings['retention_days'] ?? 0)); ?>"
							class="small-text"
							min="0"
							step="1"
						/>
						<p class="description"><?php esc_html_e('Automatically delete leads older than this many days. Set to 0 to keep forever.', 'wp-ai-chatbot'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Store IP Hash', 'wp-ai-chatbot'); ?></th>
					<td>
						<label>
							<input type="checkbox"
								name="<?php echo esc_attr(Lead_Manager::SETTINGS_OPTION); ?>[store_ip_hash]"
								value="1"
								<?php checked(! empty($settings['store_ip_hash'])); ?>
							/>
							<?php esc_html_e('Store a SHA-256 hash of the visitor\'s IP address (GDPR-friendly)', 'wp-ai-chatbot'); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<?php
	}

	/**
	 * Render the live agent handoff settings tab.
	 *
	 * @return void
	 */
	private function render_livechat_tab(): void
	{
		$settings = Livechat_Manager::get_settings();
		?>
		<form method="post" action="options.php">
			<?php settings_fields('wpaic_livechat_group'); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e('Enable Live Agent Handoff', 'wp-ai-chatbot'); ?></th>
					<td>
						<label>
							<input type="checkbox"
								name="<?php echo esc_attr(Livechat_Manager::SETTINGS_OPTION); ?>[enabled]"
								value="1"
								<?php checked(! empty($settings['enabled'])); ?>
							/>
							<?php esc_html_e('Allow the chatbot to transfer visitors to a human agent on request', 'wp-ai-chatbot'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpaic_agent_email"><?php esc_html_e('Agent Email', 'wp-ai-chatbot'); ?></label>
					</th>
					<td>
						<input type="email"
							id="wpaic_agent_email"
							name="<?php echo esc_attr(Livechat_Manager::SETTINGS_OPTION); ?>[agent_email]"
							value="<?php echo esc_attr($settings['agent_email']); ?>"
							class="regular-text"
							placeholder="<?php echo esc_attr(get_option('admin_email')); ?>"
						/>
						<p class="description"><?php esc_html_e('Email address to notify when a visitor requests a live agent.', 'wp-ai-chatbot'); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e('Notify on Request', 'wp-ai-chatbot'); ?></th>
					<td>
						<label>
							<input type="checkbox"
								name="<?php echo esc_attr(Livechat_Manager::SETTINGS_OPTION); ?>[notify_on_request]"
								value="1"
								<?php checked(! empty($settings['notify_on_request'])); ?>
							/>
							<?php esc_html_e('Send an email when a visitor requests a live agent', 'wp-ai-chatbot'); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="wpaic_queue_message"><?php esc_html_e('Queue Message', 'wp-ai-chatbot'); ?></label>
					</th>
					<td>
						<textarea
							id="wpaic_queue_message"
							name="<?php echo esc_attr(Livechat_Manager::SETTINGS_OPTION); ?>[queue_message]"
							class="large-text"
							rows="2"
						><?php echo esc_textarea($settings['queue_message']); ?></textarea>
						<p class="description"><?php esc_html_e('Message shown to the visitor while they wait for an agent to connect.', 'wp-ai-chatbot'); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<?php $this->render_livechat_queue(); ?>
		<?php
	}

	/**
	 * Render the leads inbox tab with a basic list table.
	 *
	 * @return void
	 */
	private function render_inbox_tab(): void
	{
		$stats = Lead_Manager::get_stats();
		$per_page = 20;
		$page = max(1, (int) ($GLOBALS['_GET']['leads_page'] ?? 1)); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$result = Lead_Manager::get_leads(['per_page' => $per_page, 'page' => $page]);

		// Handle CSV export.
		if (
			isset($_GET['wpaic_export_leads']) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'] ?? '')), 'wpaic_export_leads')
			&& current_user_can('manage_options')
		) {
			$csv = Lead_Manager::export_csv();
			header('Content-Type: text/csv');
			header('Content-Disposition: attachment; filename="wpaic-leads-' . gmdate('Y-m-d') . '.csv"');
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $csv;
			exit;
		}
		?>
		<div class="wpaic-stats-row" style="display:flex;gap:16px;margin-bottom:16px;">
			<?php foreach ([
				__('Total', 'wp-ai-chatbot') => $stats['total'],
				__('Today', 'wp-ai-chatbot') => $stats['today'],
				__('This Month', 'wp-ai-chatbot') => $stats['this_month'],
			] as $label => $value) : ?>
				<div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px;padding:12px 20px;min-width:100px;text-align:center;">
					<div style="font-size:22px;font-weight:600;"><?php echo (int) $value; ?></div>
					<div style="font-size:12px;color:#666;"><?php echo esc_html($label); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<p>
			<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('wpaic_export_leads', '1'), 'wpaic_export_leads')); ?>"
				class="button"><?php esc_html_e('Export CSV', 'wp-ai-chatbot'); ?></a>
		</p>

		<table class="wp-list-table widefat fixed striped" style="margin-top:8px;">
			<thead>
				<tr>
					<th><?php esc_html_e('Name', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Email', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Phone', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Interest', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Bot', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Status', 'wp-ai-chatbot'); ?></th>
					<th><?php esc_html_e('Date', 'wp-ai-chatbot'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($result['leads'])) : ?>
					<tr>
						<td colspan="7" style="text-align:center;padding:24px;">
							<?php esc_html_e('No leads captured yet. Enable Lead Capture in the Lead Capture tab to start collecting leads.', 'wp-ai-chatbot'); ?>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ($result['leads'] as $lead) : ?>
						<tr>
							<td><?php echo esc_html($lead['name'] ?: '—'); ?></td>
							<td><?php echo $lead['email'] ? '<a href="mailto:' . esc_attr($lead['email']) . '">' . esc_html($lead['email']) . '</a>' : '—'; ?></td>
							<td><?php echo esc_html($lead['phone'] ?: '—'); ?></td>
							<td><?php echo esc_html($lead['interest'] ?: '—'); ?></td>
							<td><code><?php echo esc_html($lead['bot_slug'] ?: 'default'); ?></code></td>
							<td>
								<span class="wpaic-badge wpaic-badge--<?php echo esc_attr($lead['status']); ?>">
									<?php echo esc_html(ucfirst($lead['status'])); ?>
								</span>
							</td>
							<td><?php echo esc_html(wp_date(get_option('date_format'), (int) strtotime($lead['created_at']))); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ($result['total_pages'] > 1) : ?>
			<div class="tablenav bottom" style="margin-top:8px;">
				<?php
				echo paginate_links([  // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'base' => add_query_arg('leads_page', '%#%'),
					'format' => '',
					'current' => $page,
					'total' => $result['total_pages'],
				]);
			?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render the live-agent queue sub-panel (inside the livechat tab).
	 *
	 * @return void
	 */
	private function render_livechat_queue(): void
	{
		$pending = Livechat_Manager::get_pending_sessions();

		if (empty($pending)) {
			return;
		}

		echo '<h3>' . esc_html__('Pending Queue', 'wp-ai-chatbot') . '</h3>';
		echo '<p>' . esc_html(sprintf(
			/* translators: %d: number of sessions waiting */
			_n('%d session is waiting for an agent.', '%d sessions are waiting for an agent.', count($pending), 'wp-ai-chatbot'),
			count($pending)
		)) . '</p>';

		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>';
		foreach ([
			__('Session', 'wp-ai-chatbot'),
			__('Bot', 'wp-ai-chatbot'),
			__('Reason', 'wp-ai-chatbot'),
			__('Requested', 'wp-ai-chatbot'),
		] as $col) {
			echo '<th>' . esc_html($col) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ($pending as $session) {
			echo '<tr>';
			printf('<td><code>%s</code></td>', esc_html(substr($session['session_token'], 0, 12) . '…'));
			printf('<td>%s</td>', esc_html($session['bot_slug'] ?: 'default'));
			printf('<td>%s</td>', esc_html($session['handoff_reason'] ?: '—'));
			printf('<td>%s</td>', esc_html(
				wp_date(get_option('date_format') . ' ' . get_option('time_format'), (int) strtotime($session['requested_at']))
			));
			echo '</tr>';
		}

		echo '</tbody></table>';
	}

	// =========================================================================
	// Sanitize callbacks
	// =========================================================================

	/**
	 * Sanitize the lead capture settings array.
	 *
	 * @param  mixed $raw Raw input from the settings form.
	 * @return array Sanitized settings.
	 */
	public function sanitize_lead_settings($raw): array
	{
		if (! is_array($raw)) {
			return Lead_Manager::get_settings();
		}

		return [
			'enabled' => ! empty($raw['enabled']),
			'notify_email' => sanitize_email($raw['notify_email'] ?? ''),
			'notify_enabled' => ! empty($raw['notify_enabled']),
			'retention_days' => max(0, (int) ($raw['retention_days'] ?? 0)),
			'store_ip_hash' => ! empty($raw['store_ip_hash']),
		];
	}

	/**
	 * Sanitize the livechat settings array.
	 *
	 * @param  mixed $raw Raw input from the settings form.
	 * @return array Sanitized settings.
	 */
	public function sanitize_livechat_settings($raw): array
	{
		if (! is_array($raw)) {
			return Livechat_Manager::get_settings();
		}

		return [
			'enabled' => ! empty($raw['enabled']),
			'agent_email' => sanitize_email($raw['agent_email'] ?? ''),
			'notify_on_request' => ! empty($raw['notify_on_request']),
			'queue_message' => sanitize_textarea_field($raw['queue_message'] ?? ''),
		];
	}

	// =========================================================================
	// REST callbacks
	// =========================================================================

	/**
	 * REST GET /chat/leads — list leads.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_list_leads(\WP_REST_Request $request): \WP_REST_Response
	{
		$result = Lead_Manager::get_leads([
			'per_page' => (int) $request->get_param('per_page'),
			'page' => (int) $request->get_param('page'),
			'status' => (string) ($request->get_param('status') ?? ''),
			'search' => (string) ($request->get_param('search') ?? ''),
		]);

		return new \WP_REST_Response($result, 200);
	}

	/**
	 * REST POST /chat/leads/{id}/status — update a lead's status.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_update_lead_status(\WP_REST_Request $request)
	{
		$id = (int) $request->get_param('id');
		$status = (string) $request->get_param('status');

		$ok = Lead_Manager::update_status($id, $status);

		if (! $ok) {
			return new \WP_Error('update_failed', __('Failed to update lead status.', 'wp-ai-chatbot'), ['status' => 400]);
		}

		return new \WP_REST_Response(['success' => true], 200);
	}

	/**
	 * REST DELETE /chat/leads/{id} — delete a lead.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_delete_lead(\WP_REST_Request $request)
	{
		$id = (int) $request->get_param('id');
		$ok = Lead_Manager::delete($id);

		if (! $ok) {
			return new \WP_Error('delete_failed', __('Failed to delete lead.', 'wp-ai-chatbot'), ['status' => 400]);
		}

		return new \WP_REST_Response(['success' => true], 200);
	}

	/**
	 * REST GET /chat/livechat/queue — get pending handoff sessions.
	 *
	 * @param  \WP_REST_Request $request Request object (unused).
	 * @return \WP_REST_Response
	 */
	public function rest_get_livechat_queue(\WP_REST_Request $request): \WP_REST_Response
	{
		return new \WP_REST_Response(Livechat_Manager::get_pending_sessions(), 200);
	}

	/**
	 * REST POST /chat/livechat/{token}/status — update a livechat session status.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_update_livechat_status(\WP_REST_Request $request)
	{
		$token = sanitize_text_field((string) $request->get_param('token'));
		$status = (string) $request->get_param('status');
		$agent_id = (int) ($request->get_param('agent_id') ?? get_current_user_id());

		$ok = Livechat_Manager::update_status($token, $status, $agent_id);

		if (! $ok) {
			return new \WP_Error('update_failed', __('Failed to update livechat status.', 'wp-ai-chatbot'), ['status' => 400]);
		}

		return new \WP_REST_Response(['success' => true], 200);
	}
}
