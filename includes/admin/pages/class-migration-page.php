<?php

/**
 * Migration Assistant admin page.
 *
 * Detects legacy source plugins, presents a mapping table with conflict badges,
 * and lets the operator run a dry-run preview before committing a live migration.
 * Every live run is backed up so rollback is available without data loss.
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Admin\Pages;

use WPAIC\Admin\Admin_Page;
use WPAIC\Migration\Migration_Runner;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Migration_Page
 *
 * Admin page controller for `uwpaic-migration`.
 *
 * @since 1.0.0
 * @package WPAIC\Admin\Pages
 */
class Migration_Page extends Admin_Page
{
	// =========================================================================
	// Admin_Page contract
	// =========================================================================

	/**
	 * @inheritDoc
	 */
	public function get_slug(): string
	{
		return 'uwpaic-migration';
	}

	/**
	 * @inheritDoc
	 */
	public function get_title(): string
	{
		return __('Migration Assistant', 'wp-ai-chatbot');
	}

	/**
	 * @inheritDoc
	 */
	public function get_tabs(): array
	{
		return [
			'overview' => __('Overview', 'wp-ai-chatbot'),
			'axiachat' => __('AxiaChat AI', 'wp-ai-chatbot'),
			'mxchat' => __('MxChat Basic', 'wp-ai-chatbot'),
			'baachal' => __('Baachal', 'wp-ai-chatbot'),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function register_settings(): void
	{
		// No WP Settings API registration — this page is action-driven via REST.
	}

	/**
	 * @inheritDoc
	 */
	public function render(): void
	{
		$active_tab = $this->get_active_tab();

		$this->render_page_header($active_tab);

		echo '<div class="wpaic-migration-wrap" style="margin-top:20px;">';

		switch ($active_tab) {
			case 'axiachat':
				$this->render_source_tab('axiachat');
				break;

			case 'mxchat':
				$this->render_source_tab('mxchat');
				break;

			case 'baachal':
				$this->render_source_tab('baachal');
				break;

			case 'overview':
			default:
				$this->render_overview_tab();
				break;
		}

		echo '</div>';

		$this->render_inline_js();

		$this->render_page_footer();
	}

	// =========================================================================
	// Tab renderers
	// =========================================================================

	/**
	 * Render the Overview tab: detection cards for all three source plugins.
	 *
	 * @return void
	 */
	private function render_overview_tab(): void
	{
		$statuses = Migration_Runner::detect_all();
		$sources = Migration_Runner::SOURCES;
		?>
		<p><?php esc_html_e('The Migration Assistant scans your database for settings from legacy chatbot plugins and imports them into WP AI Chatbot. Run a dry-run first to preview every change before committing.', 'wp-ai-chatbot'); ?></p>

		<div class="wpaic-migration-cards" style="display:flex;gap:20px;flex-wrap:wrap;margin-top:16px;">
			<?php foreach ($sources as $source) :
				$s = $statuses[ $source ];
				$detected = $s['detected'];
				$badge_c = $detected ? '#22c55e' : '#94a3b8';
				$badge_l = $detected ? __('Detected', 'wp-ai-chatbot') : __('Not found', 'wp-ai-chatbot');
				$tab_url = esc_url(add_query_arg(['page' => 'uwpaic-migration', 'tab' => $source], admin_url('admin.php')));
				?>
			<div style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px 24px;min-width:220px;flex:1;">
				<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
					<strong style="font-size:15px;"><?php echo esc_html($s['label']); ?></strong>
					<span style="background:<?php echo esc_attr($badge_c); ?>;color:#fff;border-radius:4px;padding:2px 8px;font-size:11px;font-weight:600;"><?php echo esc_html($badge_l); ?></span>
				</div>
				<?php if ($s['last_run']) : ?>
					<p style="color:#64748b;font-size:12px;margin:0 0 8px;">
						<?php
							echo esc_html(
								/* translators: %s: date-time string */
								sprintf(__('Last migrated: %s', 'wp-ai-chatbot'), $s['last_run'])
							);
					?>
					</p>
				<?php endif; ?>
				<?php if ($s['has_backup']) : ?>
					<p style="color:#f59e0b;font-size:12px;margin:0 0 8px;"><?php esc_html_e('Rollback available', 'wp-ai-chatbot'); ?></p>
				<?php endif; ?>
				<?php if ($detected) : ?>
					<a href="<?php echo esc_url($tab_url); ?>" class="button button-secondary" style="margin-top:8px;"><?php esc_html_e('View Mapping', 'wp-ai-chatbot'); ?></a>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>

		<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:16px 20px;margin-top:24px;">
			<strong><?php esc_html_e('How migration works', 'wp-ai-chatbot'); ?></strong>
			<ol style="margin:8px 0 0 16px;padding:0;line-height:1.8;">
				<li><?php esc_html_e('Click a source tab and review the mapping table.', 'wp-ai-chatbot'); ?></li>
				<li><?php esc_html_e('Run Dry-Run to preview what will change without writing anything.', 'wp-ai-chatbot'); ?></li>
				<li><?php esc_html_e('Click Run Live Migration to apply. A backup is saved automatically.', 'wp-ai-chatbot'); ?></li>
				<li><?php esc_html_e('If anything looks wrong, click Rollback to restore the previous state.', 'wp-ai-chatbot'); ?></li>
			</ol>
		</div>
		<?php
	}

	/**
	 * Render a source-specific migration tab.
	 *
	 * @param  string $source Source identifier: 'axiachat'|'mxchat'|'baachal'.
	 * @return void
	 */
	private function render_source_tab(string $source): void
	{
		$statuses = Migration_Runner::detect_all();
		$s = $statuses[ $source ];

		if (! $s['detected']) {
			echo '<div class="notice notice-warning inline"><p>';
			echo esc_html(
				/* translators: %s: plugin name */
				sprintf(__('No data found for %s. The plugin may not be installed or may have been removed.', 'wp-ai-chatbot'), $s['label'])
			);
			echo '</p></div>';

			return;
		}

		$nonce = wp_create_nonce('wp_rest');
		$api_base = esc_js(rest_url('wp-ai-chatbot/v1/migration/' . $source));
		?>
		<div class="wpaic-source-tab" data-source="<?php echo esc_attr($source); ?>">

			<div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
				<h2 style="margin:0;"><?php echo esc_html($s['label']); ?></h2>
				<?php if ($s['has_backup']) : ?>
					<span style="background:#fef3c7;border:1px solid #fcd34d;color:#92400e;border-radius:4px;padding:2px 10px;font-size:12px;"><?php esc_html_e('Rollback available', 'wp-ai-chatbot'); ?></span>
				<?php endif; ?>
			</div>

			<?php $this->render_mapping_table($source); ?>

			<div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
				<button
					type="button"
					class="button button-secondary wpaic-migration-action"
					data-source="<?php echo esc_attr($source); ?>"
					data-action="dry-run"
					data-nonce="<?php echo esc_attr($nonce); ?>"
					data-api="<?php echo esc_attr(rest_url('wp-ai-chatbot/v1/migration/' . $source . '/run')); ?>"
				>
					<?php esc_html_e('Dry-Run Preview', 'wp-ai-chatbot'); ?>
				</button>

				<button
					type="button"
					class="button button-primary wpaic-migration-action"
					data-source="<?php echo esc_attr($source); ?>"
					data-action="run"
					data-nonce="<?php echo esc_attr($nonce); ?>"
					data-api="<?php echo esc_attr(rest_url('wp-ai-chatbot/v1/migration/' . $source . '/run')); ?>"
					<?php echo $s['detected'] ? '' : 'disabled'; ?>
				>
					<?php esc_html_e('Run Live Migration', 'wp-ai-chatbot'); ?>
				</button>

				<?php if ($s['has_backup']) : ?>
					<button
						type="button"
						class="button button-secondary wpaic-migration-action"
						data-source="<?php echo esc_attr($source); ?>"
						data-action="rollback"
						data-nonce="<?php echo esc_attr($nonce); ?>"
						data-api="<?php echo esc_attr(rest_url('wp-ai-chatbot/v1/migration/' . $source . '/rollback')); ?>"
						style="border-color:#ef4444;color:#ef4444;"
					>
						<?php esc_html_e('Rollback', 'wp-ai-chatbot'); ?>
					</button>
				<?php endif; ?>
			</div>

			<div id="wpaic-migration-result-<?php echo esc_attr($source); ?>" style="margin-top:20px;display:none;"></div>

		</div>
		<?php
	}

	/**
	 * Render the static mapping table for a source (read-only preview).
	 *
	 * @param  string $source Source identifier.
	 * @return void
	 */
	private function render_mapping_table(string $source): void
	{
		$result = Migration_Runner::get_mapping($source);

		if (! $result['success']) {
			echo '<p class="description">' . esc_html($result['error']) . '</p>';

			return;
		}

		$mapping = $result['mapping'];

		if (empty($mapping)) {
			echo '<p class="description">' . esc_html__('No migration mappings defined for this source.', 'wp-ai-chatbot') . '</p>';

			return;
		}
		?>
		<table class="wp-list-table widefat fixed striped" style="margin-top:0;">
			<thead>
				<tr>
					<th style="width:28%;"><?php esc_html_e('Field', 'wp-ai-chatbot'); ?></th>
					<th style="width:24%;"><?php esc_html_e('Source Value', 'wp-ai-chatbot'); ?></th>
					<th style="width:24%;"><?php esc_html_e('Current WPAIC Value', 'wp-ai-chatbot'); ?></th>
					<th style="width:12%;"><?php esc_html_e('Status', 'wp-ai-chatbot'); ?></th>
					<th style="width:12%;"><?php esc_html_e('WPAIC Option', 'wp-ai-chatbot'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($mapping as $row) : ?>
					<?php
					$status_html = '';

					if (! $row['has_source']) {
						$status_html = '<span style="color:#94a3b8;">' . esc_html__('No source', 'wp-ai-chatbot') . '</span>';
					} elseif ($row['conflict']) {
						$status_html = '<span style="background:#fef3c7;border:1px solid #fcd34d;border-radius:3px;color:#92400e;padding:1px 6px;font-size:11px;">' . esc_html__('Conflict', 'wp-ai-chatbot') . '</span>';
					} elseif ($row['has_target']) {
						$status_html = '<span style="color:#22c55e;">' . esc_html__('OK', 'wp-ai-chatbot') . '</span>';
					} else {
						$status_html = '<span style="color:#3b82f6;">' . esc_html__('Ready', 'wp-ai-chatbot') . '</span>';
					}
					?>
					<tr>
						<td><strong><?php echo esc_html($row['label']); ?></strong></td>
						<td style="font-family:monospace;font-size:12px;word-break:break-all;">
							<?php echo esc_html($row['has_source'] ? (string) $row['source_value'] : '—'); ?>
						</td>
						<td style="font-family:monospace;font-size:12px;word-break:break-all;">
							<?php echo esc_html($row['has_target'] ? (string) $row['target_value'] : '—'); ?>
						</td>
						<td><?php echo wp_kses($status_html, ['span' => ['style' => [], 'class' => []]]); ?></td>
						<td style="font-family:monospace;font-size:11px;color:#64748b;"><?php echo esc_html($row['target_option']); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$conflict_count = count(array_filter($mapping, static function ($r) {
			return $r['conflict'];
		}));

		if ($conflict_count > 0) :
			?>
		<div class="notice notice-warning inline" style="margin-top:12px;">
			<p>
				<?php
					echo esc_html(
						/* translators: %d: number of conflicts */
						sprintf(_n('%d conflict detected.', '%d conflicts detected.', $conflict_count, 'wp-ai-chatbot'), $conflict_count)
					);
			?>
				<?php esc_html_e('Conflicting fields will be skipped unless you enable "Overwrite conflicts".', 'wp-ai-chatbot'); ?>
			</p>
		</div>
		<?php endif; ?>
		<?php
	}

	// =========================================================================
	// Inline JavaScript
	// =========================================================================

	/**
	 * Render the inline JavaScript that drives migration actions.
	 *
	 * Uses vanilla fetch() to call the WPAIC REST API endpoints.
	 *
	 * @return void
	 */
	private function render_inline_js(): void
	{
		?>
		<script>
		(function () {
			'use strict';

			document.addEventListener('DOMContentLoaded', function () {
				document.querySelectorAll('.wpaic-migration-action').forEach(function (btn) {
					btn.addEventListener('click', function () {
						var source      = btn.dataset.source;
						var action      = btn.dataset.action;
						var nonce       = btn.dataset.nonce;
						var apiUrl      = btn.dataset.api;
						var resultEl    = document.getElementById('wpaic-migration-result-' + source);

						if ( 'run' === action ) {
							if ( ! confirm( '<?php echo esc_js(__('Run live migration? This will overwrite matching WPAIC settings. A backup will be saved.', 'wp-ai-chatbot')); ?>' ) ) {
								return;
							}
						}

						if ( 'rollback' === action ) {
							if ( ! confirm( '<?php echo esc_js(__('Roll back to the previous state? This cannot be undone.', 'wp-ai-chatbot')); ?>' ) ) {
								return;
							}
						}

						btn.disabled = true;
						btn.textContent = '<?php echo esc_js(__('Working…', 'wp-ai-chatbot')); ?>';

						var body = null;
						var method = 'POST';

						if ( 'dry-run' === action ) {
							body = JSON.stringify({ dry_run: true, overwrite_conflicts: false });
						} else if ( 'run' === action ) {
							var overwrite = confirm( '<?php echo esc_js(__('Overwrite existing WPAIC values on conflict? (Cancel = skip conflicts)', 'wp-ai-chatbot')); ?>' );
							body = JSON.stringify({ dry_run: false, overwrite_conflicts: overwrite });
						}
						// rollback uses no body.

						fetch( apiUrl, {
							method: method,
							credentials: 'same-origin',
							headers: {
								'Content-Type': 'application/json',
								'X-WP-Nonce': nonce
							},
							body: body
						} )
						.then( function (r) { return r.json(); } )
						.then( function (data) {
							if ( resultEl ) {
								resultEl.style.display = 'block';
								resultEl.innerHTML = wpAicRenderResult( action, data );
							}

							btn.disabled = false;
							btn.textContent = btn.dataset.originalLabel || btn.textContent;
						} )
						.catch( function (err) {
							if ( resultEl ) {
								resultEl.style.display = 'block';
								resultEl.innerHTML = '<div class="notice notice-error inline"><p>' +
									'<?php echo esc_js(__('Request failed: ', 'wp-ai-chatbot')); ?>' +
									String( err ) + '</p></div>';
							}

							btn.disabled = false;
						} );
					} );

					// Preserve original button label.
					btn.dataset.originalLabel = btn.textContent;
				} );
			} );

			function wpAicRenderResult( action, data ) {
				if ( ! data.success && data.error ) {
					return '<div class="notice notice-error inline"><p>' + esc( data.error ) + '</p></div>';
				}

				var dryLabel = data.dry_run ? ' (<?php echo esc_js(__('dry-run', 'wp-ai-chatbot')); ?>)' : '';

				if ( 'rollback' === action ) {
					return '<div class="notice notice-success inline"><p>' +
						'<?php echo esc_js(__('Rollback complete. Restored ', 'wp-ai-chatbot')); ?>' +
						( data.restored || 0 ) + ' <?php echo esc_js(__('options.', 'wp-ai-chatbot')); ?>' +
						'</p></div>';
				}

				var html = '<div class="notice notice-' + ( data.applied > 0 ? 'success' : 'warning' ) + ' inline">';
				html += '<p><strong>' + ( data.dry_run
					? '<?php echo esc_js(__('Dry-run complete', 'wp-ai-chatbot')); ?>'
					: '<?php echo esc_js(__('Migration complete', 'wp-ai-chatbot')); ?>'
				) + dryLabel + '</strong></p>';
				html += '<ul style="margin:4px 0 4px 16px;">';
				html += '<li>' + ( data.applied   || 0 ) + ' <?php echo esc_js(__('settings would be / were applied', 'wp-ai-chatbot')); ?></li>';
				html += '<li>' + ( data.skipped   || 0 ) + ' <?php echo esc_js(__('skipped (no source value)', 'wp-ai-chatbot')); ?></li>';
				html += '<li>' + ( data.conflicts || 0 ) + ' <?php echo esc_js(__('skipped due to conflicts', 'wp-ai-chatbot')); ?></li>';
				html += '</ul>';

				if ( data.items && data.items.length ) {
					html += '<details style="margin-top:8px;"><summary style="cursor:pointer;"><?php echo esc_js(__('Details', 'wp-ai-chatbot')); ?></summary>';
					html += '<ul style="margin:4px 0 4px 16px;font-size:12px;font-family:monospace;">';
					data.items.forEach( function (item) {
						var colour = item.action === 'apply' ? '#22c55e' : '#94a3b8';
						html += '<li style="color:' + colour + ';">' +
							esc( item.action.toUpperCase() ) + ' ' + esc( item.label || item.option ) +
							( item.reason ? ' (' + esc( item.reason ) + ')' : '' ) +
							'</li>';
					} );
					html += '</ul></details>';
				}

				html += '</div>';
				return html;
			}

			function esc( str ) {
				var div = document.createElement( 'div' );
				div.appendChild( document.createTextNode( String( str || '' ) ) );
				return div.innerHTML;
			}
		}());
		</script>
		<?php
	}
}
