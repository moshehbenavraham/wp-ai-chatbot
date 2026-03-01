<?php

/**
 * Diagnostics Admin Page — slug: uwpaic-diagnostics
 *
 * Tabs:
 *  - logs    : Recent audit log entries from Audit_Logger.
 *  - health  : System health check (PHP / WP / DB / extensions).
 *  - system  : Plugin version, PHP/WP environment, and active modules summary.
 *
 * This page is intentionally read-only — no WP Settings API forms. All data
 * is rendered from live sources (audit logs, system state, option values).
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
 * Class Diagnostics_Page
 *
 * Admin controller for Diagnostics, logs, and system health.
 *
 * @since 1.0.0
 * @package WPAIC\Admin\Pages
 */
class Diagnostics_Page extends Admin_Page
{
	/** Max audit log rows displayed per page. */
	public const LOGS_PER_PAGE = 100;

	// =========================================================================
	// Admin_Page contract
	// =========================================================================

	/** {@inheritdoc} */
	public function get_slug(): string
	{
		return 'uwpaic-diagnostics';
	}

	/** {@inheritdoc} */
	public function get_title(): string
	{
		return __('Diagnostics', 'wp-ai-chatbot');
	}

	/** {@inheritdoc} */
	public function get_tabs(): array
	{
		return [
			'logs' => __('Audit Logs', 'wp-ai-chatbot'),
			'health' => __('Health Check', 'wp-ai-chatbot'),
			'system' => __('System Info', 'wp-ai-chatbot'),
		];
	}

	/** {@inheritdoc} */
	public function register_settings(): void
	{
		// Read-only page — no settings to register.
	}

	// =========================================================================
	// Page render
	// =========================================================================

	/** {@inheritdoc} */
	public function render(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to access this page.', 'wp-ai-chatbot'));
		}

		$tab = $this->get_active_tab();
		$this->render_page_header($tab);

		switch ($tab) {
			case 'health':
				$this->render_health_tab();
				break;
			case 'system':
				$this->render_system_tab();
				break;
			default:
				$this->render_logs_tab();
				break;
		}

		$this->render_page_footer();
	}

	// =========================================================================
	// Tab: Audit Logs
	// =========================================================================

	/**
	 * Render the Audit Logs tab.
	 *
	 * Displays the most recent LOGS_PER_PAGE events from Audit_Logger::query().
	 * Falls back to a notice when Audit_Logger is unavailable or logs are empty.
	 *
	 * @return void
	 */
	private function render_logs_tab(): void
	{
		$entries = [];

		if (class_exists('WPAIC\Security\Audit_Logger')) {
			$entries = \WPAIC\Security\Audit_Logger::query(['limit' => self::LOGS_PER_PAGE]);
		}

		?>
		<div class="wpaic-tab-content">
			<h2><?php esc_html_e('Recent Audit Events', 'wp-ai-chatbot'); ?></h2>
			<?php if (empty($entries)) : ?>
				<p><?php esc_html_e('No audit log entries found for the past 7 days.', 'wp-ai-chatbot'); ?></p>
			<?php else : ?>
				<style>.wpaic-log-table td{vertical-align:top;padding:4px 8px;}.wpaic-log-table tr:nth-child(even){background:#f9f9f9;}</style>
				<table class="widefat fixed wpaic-log-table" role="grid">
					<thead>
						<tr>
							<th scope="col" style="width:15%"><?php esc_html_e('Time', 'wp-ai-chatbot'); ?></th>
							<th scope="col" style="width:10%"><?php esc_html_e('Level', 'wp-ai-chatbot'); ?></th>
							<th scope="col" style="width:15%"><?php esc_html_e('Event', 'wp-ai-chatbot'); ?></th>
							<th scope="col"><?php esc_html_e('Context', 'wp-ai-chatbot'); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($entries as $entry) : ?>
							<tr>
								<td><?php echo esc_html($entry['timestamp'] ?? ''); ?></td>
								<td>
									<span class="<?php echo esc_attr('wpaic-level-' . ($entry['level'] ?? 'info')); ?>">
										<?php echo esc_html(strtoupper((string) ($entry['level'] ?? 'info'))); ?>
									</span>
								</td>
								<td><?php echo esc_html($entry['event'] ?? $entry['message'] ?? ''); ?></td>
								<td>
									<code style="font-size:11px;word-break:break-all;">
										<?php
										$ctx = $entry;
							unset($ctx['timestamp'], $ctx['level'], $ctx['event'], $ctx['message']);
							echo esc_html(wp_json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
							?>
									</code>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of entries */
						esc_html__('Showing up to %d most recent audit events.', 'wp-ai-chatbot'),
						self::LOGS_PER_PAGE
					);
				?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// =========================================================================
	// Tab: Health Check
	// =========================================================================

	/**
	 * Render the Health Check tab.
	 *
	 * Performs lightweight system checks and renders a pass/warn/fail table.
	 *
	 * @return void
	 */
	private function render_health_tab(): void
	{
		$checks = $this->run_health_checks();
		?>
		<div class="wpaic-tab-content">
			<h2><?php esc_html_e('System Health', 'wp-ai-chatbot'); ?></h2>
			<table class="widefat fixed">
				<thead>
					<tr>
						<th><?php esc_html_e('Check', 'wp-ai-chatbot'); ?></th>
						<th style="width:80px"><?php esc_html_e('Status', 'wp-ai-chatbot'); ?></th>
						<th><?php esc_html_e('Details', 'wp-ai-chatbot'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($checks as $check) : ?>
						<tr>
							<td><?php echo esc_html($check['label']); ?></td>
							<td>
								<?php
								$status = $check['status'];
						$icon = 'pass' === $status ? '✓' : ('warn' === $status ? '⚠' : '✗');
						$color = 'pass' === $status ? '#22c55e' : ('warn' === $status ? '#f59e0b' : '#ef4444');
						printf(
							'<strong style="color:%s">%s %s</strong>',
							esc_attr($color),
							esc_html($icon),
							esc_html(ucfirst($status))
						);
						?>
							</td>
							<td><?php echo esc_html($check['detail']); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Run a set of lightweight health checks.
	 *
	 * @return array<int, array{label: string, status: string, detail: string}>
	 */
	private function run_health_checks(): array
	{
		global $wpdb;

		$checks = [];

		// PHP version.
		$php_version = PHP_VERSION;
		$checks[] = [
			'label' => __('PHP Version', 'wp-ai-chatbot'),
			'status' => version_compare($php_version, '7.4', '>=') ? 'pass' : 'fail',
			'detail' => $php_version,
		];

		// WordPress version.
		$checks[] = [
			'label' => __('WordPress Version', 'wp-ai-chatbot'),
			'status' => version_compare(get_bloginfo('version'), '5.8', '>=') ? 'pass' : 'warn',
			'detail' => get_bloginfo('version'),
		];

		// OpenSSL extension (required for AES-256-GCM encryption).
		$checks[] = [
			'label' => __('OpenSSL Extension', 'wp-ai-chatbot'),
			'status' => extension_loaded('openssl') ? 'pass' : 'fail',
			'detail' => extension_loaded('openssl')
				? __('Available', 'wp-ai-chatbot')
				: __('Missing — API key encryption disabled.', 'wp-ai-chatbot'),
		];

		// cURL extension (required for provider HTTP calls).
		$checks[] = [
			'label' => __('cURL Extension', 'wp-ai-chatbot'),
			'status' => extension_loaded('curl') ? 'pass' : 'fail',
			'detail' => extension_loaded('curl') ? __('Available', 'wp-ai-chatbot') : __('Missing — provider calls will fail.', 'wp-ai-chatbot'),
		];

		// mbstring (required for multi-byte string handling in moderator).
		$checks[] = [
			'label' => __('mbstring Extension', 'wp-ai-chatbot'),
			'status' => extension_loaded('mbstring') ? 'pass' : 'warn',
			'detail' => extension_loaded('mbstring') ? __('Available', 'wp-ai-chatbot') : __('Missing — some text processing functions degraded.', 'wp-ai-chatbot'),
		];

		// Memory limit.
		$memory_limit = ini_get('memory_limit');
		$memory_bytes = wp_convert_hr_to_bytes($memory_limit);
		$checks[] = [
			'label' => __('PHP Memory Limit', 'wp-ai-chatbot'),
			'status' => $memory_bytes >= 64 * MB_IN_BYTES ? 'pass' : 'warn',
			'detail' => $memory_limit,
		];

		// WP-Cron enabled.
		$cron_disabled = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
		$checks[] = [
			'label' => __('WP-Cron', 'wp-ai-chatbot'),
			'status' => $cron_disabled ? 'warn' : 'pass',
			'detail' => $cron_disabled
				? __('DISABLE_WP_CRON is set — session cleanup and ingestion crons will not fire automatically.', 'wp-ai-chatbot')
				: __('Enabled', 'wp-ai-chatbot'),
		];

		// DB connectivity.
		$db_ok = (bool) $wpdb->get_var('SELECT 1');
		$checks[] = [
			'label' => __('Database Connection', 'wp-ai-chatbot'),
			'status' => $db_ok ? 'pass' : 'fail',
			'detail' => $db_ok ? __('Connected', 'wp-ai-chatbot') : __('Connection failed.', 'wp-ai-chatbot'),
		];

		// Sessions table presence.
		$sessions_table = $wpdb->prefix . 'wpaic_sessions';
		$table_exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $sessions_table)) === $sessions_table;
		$checks[] = [
			'label' => __('Plugin DB Tables', 'wp-ai-chatbot'),
			'status' => $table_exists ? 'pass' : 'warn',
			'detail' => $table_exists
				? __('All core tables present.', 'wp-ai-chatbot')
				: __('Sessions table missing — run plugin activation or visit Settings.', 'wp-ai-chatbot'),
		];

		// API key configuration (at least one provider).
		$has_openai = (bool) get_option('wpaic_provider_openai_key', '');
		$has_claude = (bool) get_option('wpaic_provider_claude_key', '');
		$has_gemini = (bool) get_option('wpaic_provider_gemini_key', '');
		$has_key = $has_openai || $has_claude || $has_gemini;
		$checks[] = [
			'label' => __('AI Provider Key', 'wp-ai-chatbot'),
			'status' => $has_key ? 'pass' : 'warn',
			'detail' => $has_key
				? __('At least one provider API key is configured.', 'wp-ai-chatbot')
				: __('No provider API key found — chatbot will not respond.', 'wp-ai-chatbot'),
		];

		return $checks;
	}

	// =========================================================================
	// Tab: System Info
	// =========================================================================

	/**
	 * Render the System Info tab.
	 *
	 * Shows plugin version, WP/PHP environment, and active module status.
	 *
	 * @return void
	 */
	private function render_system_tab(): void
	{
		$info = $this->collect_system_info();
		?>
		<div class="wpaic-tab-content">
			<h2><?php esc_html_e('System Information', 'wp-ai-chatbot'); ?></h2>
			<p>
				<button
					type="button"
					class="button"
					onclick="document.getElementById('wpaic-sysinfo-raw').style.display='block'"
				>
					<?php esc_html_e('Copy as Text', 'wp-ai-chatbot'); ?>
				</button>
			</p>

			<?php foreach ($info as $section_title => $fields) : ?>
				<h3><?php echo esc_html($section_title); ?></h3>
				<table class="widefat fixed" style="margin-bottom:16px">
					<tbody>
						<?php foreach ($fields as $label => $value) : ?>
							<tr>
								<td style="width:40%;font-weight:500"><?php echo esc_html($label); ?></td>
								<td><code><?php echo esc_html($value); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<div id="wpaic-sysinfo-raw" style="display:none;margin-top:16px">
				<textarea class="large-text code" rows="20" readonly>
<?php
			foreach ($info as $section_title => $fields) {
				echo esc_textarea("### {$section_title}\n");
				foreach ($fields as $label => $value) {
					echo esc_textarea("{$label}: {$value}\n");
				}
				echo "\n";
			}
		?>
				</textarea>
			</div>
		</div>
		<?php
	}

	/**
	 * Collect system info for display and copy-paste debugging.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function collect_system_info(): array
	{
		global $wpdb;

		$modules = [
			'Provider Registry' => class_exists('WPAIC\Provider\Provider_Registry') ? 'loaded' : 'not loaded',
			'Session Manager' => class_exists('WPAIC\Runtime\Session_Manager') ? 'loaded' : 'not loaded',
			'Message Pipeline' => class_exists('WPAIC\Runtime\Message_Pipeline') ? 'loaded' : 'not loaded',
			'RAG Retrieval Engine' => class_exists('WPAIC\Rag\Retrieval_Engine') ? 'loaded' : 'not loaded',
			'Tool Registry' => class_exists('WPAIC\Tools\Tool_Registry') ? 'loaded' : 'not loaded',
			'MCP Registry' => class_exists('WPAIC\Mcp\Mcp_Registry') ? 'loaded' : 'not loaded',
			'WC Context Provider' => class_exists('WPAIC\WooCommerce\WC_Context_Provider') ? 'loaded' : 'not loaded',
			'Lead Manager' => class_exists('WPAIC\Integrations\Lead_Manager') ? 'loaded' : 'not loaded',
			'Livechat Manager' => class_exists('WPAIC\Integrations\Livechat_Manager') ? 'loaded' : 'not loaded',
			'Privacy Manager' => class_exists('WPAIC\Security\Privacy_Manager') ? 'loaded' : 'not loaded',
			'Content Moderator' => class_exists('WPAIC\Security\Content_Moderator') ? 'loaded' : 'not loaded',
		];

		return [
			__('Plugin', 'wp-ai-chatbot') => [
				__('Version', 'wp-ai-chatbot') => defined('WPAIC_VERSION') ? WPAIC_VERSION : 'unknown',
				__('Plugin File', 'wp-ai-chatbot') => defined('WPAIC_BASENAME') ? WPAIC_BASENAME : 'unknown',
				__('Debug Mode', 'wp-ai-chatbot') => (defined('WP_DEBUG') && WP_DEBUG) ? 'enabled' : 'disabled',
			],
			__('WordPress', 'wp-ai-chatbot') => [
				__('WP Version', 'wp-ai-chatbot') => get_bloginfo('version'),
				__('Site URL', 'wp-ai-chatbot') => get_bloginfo('url'),
				__('Multisite', 'wp-ai-chatbot') => is_multisite() ? 'yes' : 'no',
				__('Active Theme', 'wp-ai-chatbot') => (string) wp_get_theme()->get('Name'),
				__('WooCommerce', 'wp-ai-chatbot') => class_exists('WooCommerce') ? 'active' : 'not active',
			],
			__('PHP Environment', 'wp-ai-chatbot') => [
				__('PHP Version', 'wp-ai-chatbot') => PHP_VERSION,
				__('Max Execution Time', 'wp-ai-chatbot') => ini_get('max_execution_time') . 's',
				__('Memory Limit', 'wp-ai-chatbot') => ini_get('memory_limit'),
				__('Upload Max Size', 'wp-ai-chatbot') => ini_get('upload_max_filesize'),
				__('OpenSSL', 'wp-ai-chatbot') => extension_loaded('openssl') ? 'yes (' . OPENSSL_VERSION_TEXT . ')' : 'no',
				__('cURL', 'wp-ai-chatbot') => extension_loaded('curl') ? 'yes' : 'no',
				__('mbstring', 'wp-ai-chatbot') => extension_loaded('mbstring') ? 'yes' : 'no',
			],
			__('Database', 'wp-ai-chatbot') => [
				__('MySQL Version', 'wp-ai-chatbot') => (string) $wpdb->db_version(),
				__('Table Prefix', 'wp-ai-chatbot') => $wpdb->prefix,
			],
			__('Loaded Modules', 'wp-ai-chatbot') => $modules,
		];
	}
}
