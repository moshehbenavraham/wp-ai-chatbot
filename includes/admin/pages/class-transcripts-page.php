<?php

/**
 * Transcripts & Usage admin page.
 *
 * Surfaces:
 *   1. Sessions tab — paginated list of chat sessions with search/filter,
 *      per-session transcript viewer, context inspection modal.
 *   2. Usage & Cost tab — aggregated daily stats from wpaic_usage_daily,
 *      breakdown by provider/model, 7/30-day totals.
 *   3. Export tab — date-ranged CSV download via admin-post.php.
 *
 * REST endpoints (registered via register_transcripts_rest_routes() in Plugin):
 *   GET    /wp-ai-chatbot/v1/transcripts/sessions          — paginated sessions list
 *   GET    /wp-ai-chatbot/v1/transcripts/sessions/{id}     — session + full message thread
 *   DELETE /wp-ai-chatbot/v1/transcripts/sessions/{id}     — delete session + messages
 *   GET    /wp-ai-chatbot/v1/transcripts/usage             — aggregated usage stats
 *
 * CSV export is handled by admin-post action 'wpaic_export_transcripts' (Plugin.php).
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
 * Class Transcripts_Page
 *
 * @since 1.0.0
 * @package WPAIC\Admin\Pages
 */
class Transcripts_Page extends Admin_Page
{
	// =========================================================================
	// Admin_Page contract
	// =========================================================================

	public function get_slug(): string
	{
		return 'uwpaic-transcripts';
	}

	public function get_title(): string
	{
		return __('Transcripts & Usage', 'wp-ai-chatbot');
	}

	/** @return array<string,string> */
	public function get_tabs(): array
	{
		return [
			'sessions' => __('Chat Transcripts', 'wp-ai-chatbot'),
			'usage' => __('Usage & Cost', 'wp-ai-chatbot'),
			'export' => __('Export', 'wp-ai-chatbot'),
		];
	}

	/** No Settings API settings — data-driven page only. */
	public function register_settings(): void
	{
	}

	/**
	 * Render the full page.
	 *
	 * @return void
	 */
	public function render(): void
	{
		$tab = $this->get_active_tab();
		$this->render_page_header($tab);

		switch ($tab) {
			case 'usage':
				$this->render_usage_tab();
				break;
			case 'export':
				$this->render_export_tab();
				break;
			default:
				$this->render_sessions_tab();
		}

		$this->render_page_footer();
	}

	// =========================================================================
	// Tab renderers
	// =========================================================================

	/** @return void */
	private function render_sessions_tab(): void
	{
		$rest_url = esc_js(rest_url('wp-ai-chatbot/v1/transcripts'));
		$nonce = esc_js(wp_create_nonce('wp_rest'));
		$retention_days = (int) get_option('wpaic_data_retention_days', 90);
		?>
		<div class="wpaic-transcripts-sessions">

			<div class="wpaic-filter-bar" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin:16px 0;">
				<div>
					<label for="wpaic-filter-status"><?php esc_html_e('Status', 'wp-ai-chatbot'); ?></label><br>
					<select id="wpaic-filter-status" class="wpaic-filter">
						<option value=""><?php esc_html_e('All', 'wp-ai-chatbot'); ?></option>
						<option value="active"><?php esc_html_e('Active', 'wp-ai-chatbot'); ?></option>
						<option value="closed"><?php esc_html_e('Closed', 'wp-ai-chatbot'); ?></option>
						<option value="expired"><?php esc_html_e('Expired', 'wp-ai-chatbot'); ?></option>
					</select>
				</div>
				<div>
					<label for="wpaic-filter-from"><?php esc_html_e('From', 'wp-ai-chatbot'); ?></label><br>
					<input type="date" id="wpaic-filter-from" class="wpaic-filter" />
				</div>
				<div>
					<label for="wpaic-filter-to"><?php esc_html_e('To', 'wp-ai-chatbot'); ?></label><br>
					<input type="date" id="wpaic-filter-to" class="wpaic-filter" />
				</div>
				<button type="button" id="wpaic-filter-btn" class="button button-secondary">
					<?php esc_html_e('Filter', 'wp-ai-chatbot'); ?>
				</button>
			</div>

			<div id="wpaic-sessions-wrap">
				<table class="wp-list-table widefat fixed striped" id="wpaic-sessions-table">
					<thead>
						<tr>
							<th style="width:140px;"><?php esc_html_e('Token', 'wp-ai-chatbot'); ?></th>
							<th style="width:80px;"><?php esc_html_e('Status', 'wp-ai-chatbot'); ?></th>
							<th style="width:120px;"><?php esc_html_e('Provider / Model', 'wp-ai-chatbot'); ?></th>
							<th style="width:80px;"><?php esc_html_e('Messages', 'wp-ai-chatbot'); ?></th>
							<th style="width:90px;"><?php esc_html_e('Tokens', 'wp-ai-chatbot'); ?></th>
							<th style="width:80px;"><?php esc_html_e('Cost', 'wp-ai-chatbot'); ?></th>
							<th style="width:130px;"><?php esc_html_e('Started', 'wp-ai-chatbot'); ?></th>
							<?php if ($retention_days > 0) : ?>
							<th style="width:80px;"><?php esc_html_e('Expires In', 'wp-ai-chatbot'); ?></th>
							<?php endif; ?>
							<th style="width:110px;"><?php esc_html_e('Actions', 'wp-ai-chatbot'); ?></th>
						</tr>
					</thead>
					<tbody id="wpaic-sessions-body">
						<tr><td colspan="9" style="text-align:center;padding:20px;">
							<?php esc_html_e('Loading sessions…', 'wp-ai-chatbot'); ?>
						</td></tr>
					</tbody>
				</table>
				<div id="wpaic-sessions-pager" style="margin-top:8px;"></div>
			</div>

			<!-- Context Inspection Modal (opened per-message) -->
			<div id="wpaic-inspect-overlay" role="dialog" aria-modal="true"
				aria-labelledby="wpaic-inspect-title" tabindex="-1"
				style="display:none;position:fixed;inset:0;z-index:100050;background:rgba(0,0,0,.6);">
				<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
					background:#1f2937;border-radius:8px;width:min(760px,94vw);max-height:80vh;
					overflow:hidden;display:flex;flex-direction:column;">
					<div style="display:flex;align-items:center;justify-content:space-between;
						padding:14px 20px;border-bottom:1px solid #374151;">
						<h3 id="wpaic-inspect-title" style="margin:0;font-size:15px;color:#f9fafb;">
							<?php esc_html_e('Message Inspection', 'wp-ai-chatbot'); ?>
						</h3>
						<button type="button" id="wpaic-inspect-close"
							aria-label="<?php esc_attr_e('Close inspection modal', 'wp-ai-chatbot'); ?>"
							style="background:none;border:none;cursor:pointer;color:#9ca3af;font-size:20px;line-height:1;">
							&#x2715;
						</button>
					</div>
					<div style="display:flex;border-bottom:1px solid #374151;">
						<button type="button" class="wpaic-inspect-tab wpaic-inspect-tab--active" data-tab="sources"
							style="padding:8px 16px;border:none;background:none;cursor:pointer;
							color:#22d3ee;border-bottom:2px solid #22d3ee;font-weight:600;">
							<?php esc_html_e('Sources', 'wp-ai-chatbot'); ?>
						</button>
						<button type="button" class="wpaic-inspect-tab" data-tab="actions"
							style="padding:8px 16px;border:none;background:none;cursor:pointer;color:#9ca3af;">
							<?php esc_html_e('Actions', 'wp-ai-chatbot'); ?>
						</button>
					</div>
					<div id="wpaic-inspect-body" style="overflow-y:auto;padding:16px 20px;flex:1;
						color:#e5e7eb;font-size:13px;line-height:1.6;"></div>
				</div>
			</div>

		</div>

		<script>
		(function(){
			var BASE = '<?php echo $rest_url; ?>';
			var NONCE = '<?php echo $nonce; ?>';
			var RETENTION = <?php echo esc_js((string) $retention_days); ?>;
			var currentPage = 1, totalPages = 1;

			function apiFetch(path, opts) {
				opts = opts || {};
				opts.headers = Object.assign({'X-WP-Nonce': NONCE, 'Content-Type': 'application/json'}, opts.headers || {});
				return fetch(BASE + path, opts).then(function(r){ return r.json(); });
			}

			function formatCost(micros) {
				if (!micros || micros <= 0) return '—';
				var usd = micros / 1000000;
				return usd < 0.001 ? '< $0.001' : '$' + usd.toFixed(4);
			}

			function formatDate(dt) {
				if (!dt) return '—';
				return new Date(dt.replace(' ', 'T') + 'Z').toLocaleString();
			}

			function daysRemaining(created) {
				if (!RETENTION || !created) return '';
				var ms = new Date() - new Date(created.replace(' ', 'T') + 'Z');
				var elapsed = Math.floor(ms / 86400000);
				var remaining = RETENTION - elapsed;
				if (remaining <= 0) return '<span style="color:#ef4444">Expired</span>';
				if (remaining <= 7) return '<span style="color:#f59e0b">' + remaining + 'd</span>';
				return remaining + 'd';
			}

			function statusBadge(s) {
				var colors = {active:'#22d3ee',closed:'#9ca3af',expired:'#ef4444'};
				var c = colors[s] || '#9ca3af';
				return '<span style="background:' + c + '22;color:' + c + ';border-radius:3px;padding:1px 6px;font-size:11px;">' + s + '</span>';
			}

			function loadSessions(page) {
				var status = document.getElementById('wpaic-filter-status').value;
				var from   = document.getElementById('wpaic-filter-from').value;
				var to     = document.getElementById('wpaic-filter-to').value;
				page = page || 1;

				var q = '?per_page=25&page=' + page;
				if (status) q += '&status=' + encodeURIComponent(status);
				if (from)   q += '&date_from=' + encodeURIComponent(from);
				if (to)     q += '&date_to=' + encodeURIComponent(to);

				var tbody = document.getElementById('wpaic-sessions-body');
				tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px;">Loading…</td></tr>';

				apiFetch('/sessions' + q).then(function(data) {
					if (!data || !data.sessions) {
						tbody.innerHTML = '<tr><td colspan="9"><?php echo esc_js(__('Failed to load sessions.', 'wp-ai-chatbot')); ?></td></tr>';
						return;
					}
					totalPages = data.pages || 1;
					currentPage = data.page || 1;
					renderPager();

					if (!data.sessions.length) {
						tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:20px;"><?php echo esc_js(__('No sessions found.', 'wp-ai-chatbot')); ?></td></tr>';
						return;
					}
					var rows = data.sessions.map(function(s) {
						var tokens = (parseInt(s.total_prompt_tokens) || 0) + (parseInt(s.total_completion_tokens) || 0);
						var retHtml = RETENTION > 0 ? '<td>' + daysRemaining(s.created_at) + '</td>' : '';
						return '<tr>' +
							'<td><code style="font-size:11px;" title="' + s.session_token + '">' + s.session_token.substr(0,8) + '…</code></td>' +
							'<td>' + statusBadge(s.status) + '</td>' +
							'<td style="font-size:12px;">' + (s.provider || '—') + '<br>' + (s.model ? '<em>' + s.model.substr(0,18) + '</em>' : '') + '</td>' +
							'<td>' + (s.message_count || 0) + '</td>' +
							'<td>' + (tokens || '—') + '</td>' +
							'<td>' + formatCost(parseInt(s.total_cost_micros) || 0) + '</td>' +
							'<td style="font-size:11px;">' + formatDate(s.created_at) + '</td>' +
							retHtml +
							'<td>' +
								'<a href="#" class="wpaic-view-session" data-id="' + s.id + '" style="margin-right:8px;"><?php echo esc_js(__('View', 'wp-ai-chatbot')); ?></a>' +
								'<a href="#" class="wpaic-del-session" data-id="' + s.id + '" style="color:#ef4444;"><?php echo esc_js(__('Delete', 'wp-ai-chatbot')); ?></a>' +
							'</td>' +
						'</tr>';
					});
					tbody.innerHTML = rows.join('');
					attachRowHandlers();
				}).catch(function(){ tbody.innerHTML = '<tr><td colspan="9"><?php echo esc_js(__('Error loading sessions.', 'wp-ai-chatbot')); ?></td></tr>'; });
			}

			function renderPager() {
				var pager = document.getElementById('wpaic-sessions-pager');
				if (totalPages <= 1) { pager.innerHTML = ''; return; }
				var btns = '';
				for (var i = 1; i <= totalPages; i++) {
					btns += '<button type="button" class="button' + (i === currentPage ? ' button-primary' : '') + '" data-p="' + i + '" style="margin-right:4px;">' + i + '</button>';
				}
				pager.innerHTML = btns;
				pager.querySelectorAll('button').forEach(function(b){
					b.addEventListener('click', function(){ loadSessions(parseInt(this.dataset.p)); });
				});
			}

			function attachRowHandlers() {
				document.querySelectorAll('.wpaic-view-session').forEach(function(a){
					a.addEventListener('click', function(e){
						e.preventDefault();
						viewSession(this.dataset.id);
					});
				});
				document.querySelectorAll('.wpaic-del-session').forEach(function(a){
					a.addEventListener('click', function(e){
						e.preventDefault();
						var id = this.dataset.id;
						if (window.WPAICAdmin && WPAICAdmin.modal) {
							WPAICAdmin.modal.confirm({
								title: '<?php echo esc_js(__('Delete Session?', 'wp-ai-chatbot')); ?>',
								message: '<?php echo esc_js(__('This will permanently delete the session and all its messages. This cannot be undone.', 'wp-ai-chatbot')); ?>',
								confirmLabel: '<?php echo esc_js(__('Delete', 'wp-ai-chatbot')); ?>',
								destructive: true
							}).then(function(ok){ if (ok) deleteSession(id); });
						} else if (confirm('<?php echo esc_js(__('Delete this session and all messages?', 'wp-ai-chatbot')); ?>')) {
							deleteSession(id);
						}
					});
				});
			}

			function deleteSession(id) {
				apiFetch('/sessions/' + id, {method: 'DELETE'}).then(function(r){
					if (r.deleted) {
						if (window.WPAICAdmin && WPAICAdmin.toast) {
							WPAICAdmin.toast.show('<?php echo esc_js(__('Session deleted.', 'wp-ai-chatbot')); ?>', 'success');
						}
						loadSessions(currentPage);
					}
				});
			}

			function viewSession(id) {
				// Open WPAICAdmin drawer (or fallback inline modal) with message thread.
				apiFetch('/sessions/' + id).then(function(data) {
					if (!data || !data.session) return;
					var s = data.session;
					var msgs = data.messages || [];
					var html = '<div style="color:#e5e7eb;">';
					html += '<p style="margin:0 0 12px;font-size:12px;color:#9ca3af;">' +
						'Token: <code>' + s.session_token + '</code> &nbsp;|&nbsp; ' +
						'Status: ' + statusBadge(s.status) + ' &nbsp;|&nbsp; ' +
						'Started: ' + formatDate(s.created_at) + '</p>';

					if (!msgs.length) {
						html += '<p style="color:#6b7280;font-style:italic;"><?php echo esc_js(__('No messages recorded for this session.', 'wp-ai-chatbot')); ?></p>';
					} else {
						msgs.forEach(function(m, idx) {
							html += '<div style="margin-bottom:16px;">';
							html += '<div style="margin-bottom:4px;">';
							html += '<strong style="color:#22d3ee;">User</strong>';
							html += ' <span style="color:#6b7280;font-size:11px;">' + formatDate(m.created_at) + '</span>';
							html += ' <a href="#" class="wpaic-inspect-msg" data-idx="' + idx + '" style="font-size:11px;margin-left:8px;color:#9ca3af;">[Inspect]</a>';
							html += '</div>';
							html += '<div style="background:#111827;border-radius:4px;padding:8px 12px;font-size:13px;white-space:pre-wrap;word-break:break-word;">' + escHtml(m.message || '') + '</div>';
							if (m.response) {
								html += '<div style="margin-top:6px;margin-bottom:4px;"><strong style="color:#a78bfa;">Assistant</strong></div>';
								html += '<div style="background:#0f172a;border-radius:4px;padding:8px 12px;font-size:13px;white-space:pre-wrap;word-break:break-word;">' + escHtml(m.response) + '</div>';
							}
							var tokenInfo = [];
							if (m.prompt_tokens) tokenInfo.push('prompt: ' + m.prompt_tokens);
							if (m.completion_tokens) tokenInfo.push('completion: ' + m.completion_tokens);
							if (m.cost_micros) tokenInfo.push('cost: ' + formatCost(parseInt(m.cost_micros)));
							if (tokenInfo.length) {
								html += '<div style="font-size:11px;color:#4b5563;margin-top:4px;">' + tokenInfo.join(' · ') + '</div>';
							}
							html += '</div>';
						});
					}
					html += '</div>';

					if (window.WPAICAdmin && WPAICAdmin.drawer) {
						WPAICAdmin.drawer.open({
							title: '<?php echo esc_js(__('Session Transcript', 'wp-ai-chatbot')); ?>',
							content: html,
							onClose: function(){}
						});
					} else {
						alert(html.replace(/<[^>]+>/g, ''));
					}

					// Attach inspect handlers after DOM update.
					setTimeout(function(){
						document.querySelectorAll('.wpaic-inspect-msg').forEach(function(a){
							a.addEventListener('click', function(e){
								e.preventDefault();
								openInspect(msgs[parseInt(this.dataset.idx)]);
							});
						});
					}, 100);
				});
			}

			function escHtml(s) {
				return String(s)
					.replace(/&/g,'&amp;').replace(/</g,'&lt;')
					.replace(/>/g,'&gt;').replace(/"/g,'&quot;');
			}

			function openInspect(msg) {
				var overlay = document.getElementById('wpaic-inspect-overlay');
				var body    = document.getElementById('wpaic-inspect-body');
				overlay.style.display = 'block';
				overlay.focus();
				renderInspectTab('sources', msg);

				overlay.querySelectorAll('.wpaic-inspect-tab').forEach(function(btn){
					btn.addEventListener('click', function(){
						overlay.querySelectorAll('.wpaic-inspect-tab').forEach(function(b){
							b.style.color = '#9ca3af';
							b.style.borderBottom = 'none';
							b.style.fontWeight = 'normal';
							b.classList.remove('wpaic-inspect-tab--active');
						});
						this.style.color = '#22d3ee';
						this.style.borderBottom = '2px solid #22d3ee';
						this.style.fontWeight = '600';
						this.classList.add('wpaic-inspect-tab--active');
						renderInspectTab(this.dataset.tab, msg);
					});
				});
			}

			function renderInspectTab(tab, msg) {
				var body = document.getElementById('wpaic-inspect-body');
				if (tab === 'sources') {
					var ctx = msg.rag_context;
					if (!ctx) {
						body.innerHTML = '<p style="color:#6b7280;font-style:italic;"><?php echo esc_js(__('No RAG context was used for this message.', 'wp-ai-chatbot')); ?></p>';
						return;
					}
					// rag_context may be plain text or JSON array of chunk objects.
					var chunks = null;
					try { chunks = JSON.parse(ctx); } catch(e) { chunks = null; }
					if (Array.isArray(chunks) && chunks.length) {
						var rows = chunks.map(function(c, i){
							return '<div style="margin-bottom:12px;padding:10px;background:#111827;border-radius:4px;">' +
								'<div style="display:flex;justify-content:space-between;margin-bottom:6px;">' +
								'<span style="font-weight:600;">#' + (i+1) + ' ' + escHtml(c.title || c.type || 'Chunk') + '</span>' +
								(c.score ? '<span style="color:#22d3ee;font-size:12px;">Score: ' + parseFloat(c.score).toFixed(4) + '</span>' : '') +
								'</div>' +
								(c.source_url ? '<div style="font-size:11px;color:#6b7280;margin-bottom:4px;"><a href="' + escHtml(c.source_url) + '" target="_blank" style="color:#60a5fa;">' + escHtml(c.source_url) + '</a></div>' : '') +
								'<div style="font-size:12px;color:#d1d5db;white-space:pre-wrap;">' + escHtml((c.content || '').substr(0, 300)) + (c.content && c.content.length > 300 ? '…' : '') + '</div>' +
								'</div>';
						});
						body.innerHTML = rows.join('');
					} else {
						body.innerHTML = '<pre style="white-space:pre-wrap;word-break:break-word;font-size:12px;color:#d1d5db;">' + escHtml(ctx) + '</pre>';
					}
				} else {
					// Actions tab — render tool_calls history.
					var tc = msg.tool_calls;
					if (!tc) {
						body.innerHTML = '<p style="color:#6b7280;font-style:italic;"><?php echo esc_js(__('No tool calls were made for this message.', 'wp-ai-chatbot')); ?></p>';
						return;
					}
					var calls = null;
					try { calls = JSON.parse(tc); } catch(e) { calls = null; }
					if (Array.isArray(calls) && calls.length) {
						var rows = calls.map(function(c, i){
							var badge = c.ok
								? '<span style="background:#065f46;color:#6ee7b7;padding:2px 6px;border-radius:3px;font-size:11px;">OK</span>'
								: '<span style="background:#7f1d1d;color:#fca5a5;padding:2px 6px;border-radius:3px;font-size:11px;">ERROR</span>';
							return '<div style="margin-bottom:12px;padding:10px;background:#111827;border-radius:4px;">' +
								'<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">' +
								'<span style="font-weight:600;font-size:13px;">#' + (i+1) + ' ' + escHtml(c.tool_id || '') + '</span>' +
								'<span>' + badge + ' <span style="font-size:11px;color:#6b7280;margin-left:8px;">Loop ' + (c.loop||1) + ' &middot; ' + (c.duration_ms||0) + 'ms</span></span>' +
								'</div>' +
								(c.error ? '<div style="font-size:12px;color:#fca5a5;margin-bottom:4px;">' + escHtml(c.error) + '</div>' : '') +
								(c.output ? '<div style="font-size:12px;color:#d1d5db;white-space:pre-wrap;word-break:break-word;">' + escHtml(c.output.substr(0,500)) + (c.output.length > 500 ? '&hellip;' : '') + '</div>' : '') +
								'</div>';
						});
						body.innerHTML = rows.join('');
					} else {
						body.innerHTML = '<pre style="white-space:pre-wrap;word-break:break-word;font-size:12px;color:#d1d5db;">' + escHtml(tc) + '</pre>';
					}
				}
			}

			// Overlay close.
			document.getElementById('wpaic-inspect-close').addEventListener('click', function(){
				document.getElementById('wpaic-inspect-overlay').style.display = 'none';
			});
			document.getElementById('wpaic-inspect-overlay').addEventListener('click', function(e){
				if (e.target === this) this.style.display = 'none';
			});
			document.addEventListener('keydown', function(e){
				if (e.key === 'Escape') {
					var o = document.getElementById('wpaic-inspect-overlay');
					if (o.style.display !== 'none') { o.style.display = 'none'; e.stopPropagation(); }
				}
			});

			// Filter + load.
			document.getElementById('wpaic-filter-btn').addEventListener('click', function(){ loadSessions(1); });
			loadSessions(1);
		})();
		</script>
		<?php
	}

	/** @return void */
	private function render_usage_tab(): void
	{
		$rest_url = esc_js(rest_url('wp-ai-chatbot/v1/transcripts'));
		$nonce = esc_js(wp_create_nonce('wp_rest'));
		?>
		<div id="wpaic-usage-wrap">
			<div id="wpaic-usage-cards" style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0 24px;">
				<div class="wpaic-stat-card" style="background:#111827;border-radius:8px;padding:20px 24px;min-width:160px;">
					<div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:6px;"><?php esc_html_e('Sessions (30d)', 'wp-ai-chatbot'); ?></div>
					<div id="wstat-sessions" style="font-size:28px;font-weight:700;color:#f9fafb;">—</div>
				</div>
				<div class="wpaic-stat-card" style="background:#111827;border-radius:8px;padding:20px 24px;min-width:160px;">
					<div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:6px;"><?php esc_html_e('Messages (30d)', 'wp-ai-chatbot'); ?></div>
					<div id="wstat-messages" style="font-size:28px;font-weight:700;color:#f9fafb;">—</div>
				</div>
				<div class="wpaic-stat-card" style="background:#111827;border-radius:8px;padding:20px 24px;min-width:160px;">
					<div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:6px;"><?php esc_html_e('Tokens (30d)', 'wp-ai-chatbot'); ?></div>
					<div id="wstat-tokens" style="font-size:28px;font-weight:700;color:#22d3ee;">—</div>
				</div>
				<div class="wpaic-stat-card" style="background:#111827;border-radius:8px;padding:20px 24px;min-width:160px;">
					<div style="font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin-bottom:6px;"><?php esc_html_e('Cost (30d)', 'wp-ai-chatbot'); ?></div>
					<div id="wstat-cost" style="font-size:28px;font-weight:700;color:#a78bfa;">—</div>
				</div>
			</div>

			<h3><?php esc_html_e('Daily Breakdown by Provider / Model', 'wp-ai-chatbot'); ?></h3>
			<table class="wp-list-table widefat fixed striped" id="wpaic-usage-table">
				<thead>
					<tr>
						<th><?php esc_html_e('Date', 'wp-ai-chatbot'); ?></th>
						<th><?php esc_html_e('Provider', 'wp-ai-chatbot'); ?></th>
						<th><?php esc_html_e('Model', 'wp-ai-chatbot'); ?></th>
						<th><?php esc_html_e('Conversations', 'wp-ai-chatbot'); ?></th>
						<th><?php esc_html_e('Prompt Tokens', 'wp-ai-chatbot'); ?></th>
						<th><?php esc_html_e('Completion Tokens', 'wp-ai-chatbot'); ?></th>
						<th><?php esc_html_e('Total Tokens', 'wp-ai-chatbot'); ?></th>
						<th><?php esc_html_e('Cost (USD)', 'wp-ai-chatbot'); ?></th>
					</tr>
				</thead>
				<tbody id="wpaic-usage-body">
					<tr><td colspan="8" style="text-align:center;padding:20px;"><?php esc_html_e('Loading…', 'wp-ai-chatbot'); ?></td></tr>
				</tbody>
			</table>
		</div>

		<script>
		(function(){
			var BASE = '<?php echo $rest_url; ?>';
			var NONCE = '<?php echo $nonce; ?>';

			fetch(BASE + '/usage', {headers: {'X-WP-Nonce': NONCE}})
				.then(function(r){ return r.json(); })
				.then(function(data){
					if (!data) return;
					// Fill stat cards.
					document.getElementById('wstat-sessions').textContent  = (data.totals.sessions  || 0).toLocaleString();
					document.getElementById('wstat-messages').textContent  = (data.totals.messages  || 0).toLocaleString();
					document.getElementById('wstat-tokens').textContent    = (data.totals.tokens    || 0).toLocaleString();
					var cost = (data.totals.cost_micros || 0) / 1000000;
					document.getElementById('wstat-cost').textContent = cost < 0.01 ? '< $0.01' : '$' + cost.toFixed(2);

					// Fill breakdown table.
					var tbody = document.getElementById('wpaic-usage-body');
					if (!data.rows || !data.rows.length) {
						tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:20px;"><?php echo esc_js(__('No usage data recorded yet.', 'wp-ai-chatbot')); ?></td></tr>';
						return;
					}
					tbody.innerHTML = data.rows.map(function(r){
						var cost = parseInt(r.cost_micros) || 0;
						var costStr = cost <= 0 ? '—' : (cost < 1000 ? '< $0.001' : '$' + (cost/1000000).toFixed(4));
						return '<tr>' +
							'<td>' + r.date + '</td>' +
							'<td>' + (r.provider || '—') + '</td>' +
							'<td><code style="font-size:11px;">' + (r.model || '—') + '</code></td>' +
							'<td>' + (r.conversations || 0) + '</td>' +
							'<td>' + (r.prompt_tokens || 0).toLocaleString() + '</td>' +
							'<td>' + (r.completion_tokens || 0).toLocaleString() + '</td>' +
							'<td>' + (r.total_tokens || 0).toLocaleString() + '</td>' +
							'<td>' + costStr + '</td>' +
						'</tr>';
					}).join('');
				})
				.catch(function(){ document.getElementById('wpaic-usage-body').innerHTML = '<tr><td colspan="8"><?php echo esc_js(__('Failed to load usage data.', 'wp-ai-chatbot')); ?></td></tr>'; });
		})();
		</script>
		<?php
	}

	/** @return void */
	private function render_export_tab(): void
	{
		$action_url = esc_url(admin_url('admin-post.php'));
		?>
		<div style="max-width:480px;margin-top:16px;">
			<p class="description">
				<?php esc_html_e(
					'Export chat sessions and transcripts as a CSV file. Choose a date range to limit the export.',
					'wp-ai-chatbot'
				); ?>
			</p>

			<form method="get" action="<?php echo $action_url; ?>">
				<input type="hidden" name="action" value="wpaic_export_transcripts">
				<?php wp_nonce_field('wpaic_export_transcripts_nonce', 'wpaic_export_nonce'); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="exp-from"><?php esc_html_e('From Date', 'wp-ai-chatbot'); ?></label></th>
						<td><input type="date" id="exp-from" name="date_from" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="exp-to"><?php esc_html_e('To Date', 'wp-ai-chatbot'); ?></label></th>
						<td><input type="date" id="exp-to" name="date_to" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="exp-status"><?php esc_html_e('Status Filter', 'wp-ai-chatbot'); ?></label></th>
						<td>
							<select id="exp-status" name="status">
								<option value=""><?php esc_html_e('All Statuses', 'wp-ai-chatbot'); ?></option>
								<option value="active"><?php esc_html_e('Active', 'wp-ai-chatbot'); ?></option>
								<option value="closed"><?php esc_html_e('Closed', 'wp-ai-chatbot'); ?></option>
								<option value="expired"><?php esc_html_e('Expired', 'wp-ai-chatbot'); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button(__('Download CSV', 'wp-ai-chatbot'), 'primary', 'submit', false); ?>
			</form>
		</div>
		<?php
	}

	// =========================================================================
	// REST callbacks
	// =========================================================================

	/**
	 * REST: list sessions with optional filters.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_list_sessions(\WP_REST_Request $request): \WP_REST_Response
	{
		global $wpdb;

		$per_page = min(100, max(1, (int) ($request->get_param('per_page') ?: 25)));
		$page = max(1, (int) ($request->get_param('page') ?: 1));
		$status = sanitize_key((string) ($request->get_param('status') ?: ''));
		$date_from = sanitize_text_field((string) ($request->get_param('date_from') ?: ''));
		$date_to = sanitize_text_field((string) ($request->get_param('date_to') ?: ''));
		$offset = ($page - 1) * $per_page;

		$sessions_tbl = $wpdb->prefix . 'wpaic_sessions';
		$bots_tbl = $wpdb->prefix . 'wpaic_bots';

		$where = '1=1';
		$values = [];

		if (in_array($status, ['active', 'closed', 'expired'], true)) {
			$where .= ' AND s.status = %s';
			$values[] = $status;
		}
		if ($date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
			$where .= ' AND s.created_at >= %s';
			$values[] = $date_from . ' 00:00:00';
		}
		if ($date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
			$where .= ' AND s.created_at <= %s';
			$values[] = $date_to . ' 23:59:59';
		}

		// Count total rows (for pagination).
		$count_sql = "SELECT COUNT(*) FROM {$sessions_tbl} s WHERE {$where}";
		$total = $values
			? (int) $wpdb->get_var($wpdb->prepare($count_sql, $values)) // phpcs:ignore
			: (int) $wpdb->get_var($count_sql); // phpcs:ignore

		// Fetch rows.
		$data_sql = "SELECT s.id, s.session_token, s.user_id, s.status,
			s.provider, s.model, s.total_prompt_tokens, s.total_completion_tokens,
			s.total_cost_micros, s.message_count, s.created_at, s.expires_at,
			b.name AS bot_name, b.slug AS bot_slug
			FROM {$sessions_tbl} s
			LEFT JOIN {$bots_tbl} b ON s.bot_id = b.id
			WHERE {$where}
			ORDER BY s.created_at DESC
			LIMIT %d OFFSET %d";

		$fetch_values = array_merge($values, [$per_page, $offset]);
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results($wpdb->prepare($data_sql, $fetch_values), ARRAY_A);

		if (! is_array($rows)) {
			$rows = [];
		}

		return new \WP_REST_Response([
			'sessions' => $rows,
			'total' => $total,
			'page' => $page,
			'pages' => (int) ceil($total / $per_page),
		], 200);
	}

	/**
	 * REST: get a single session with its full message thread.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_get_session(\WP_REST_Request $request)
	{
		global $wpdb;

		$id = (int) $request->get_param('id');
		$sessions_tbl = $wpdb->prefix . 'wpaic_sessions';
		$messages_tbl = $wpdb->prefix . 'wpaic_messages';

		$session = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$sessions_tbl} WHERE id = %d LIMIT 1", $id),
			ARRAY_A
		);

		if (! $session) {
			return new \WP_Error('not_found', __('Session not found.', 'wp-ai-chatbot'), ['status' => 404]);
		}

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, session_id, role, message, response, provider, model,
				prompt_tokens, completion_tokens, cost_micros, rag_context, tool_calls, created_at
				FROM {$messages_tbl} WHERE session_id = %d ORDER BY created_at ASC LIMIT 200",
				$id
			),
			ARRAY_A
		);

		if (! is_array($messages)) {
			$messages = [];
		}

		return new \WP_REST_Response([
			'session' => $session,
			'messages' => $messages,
		], 200);
	}

	/**
	 * REST: delete a session and all its messages.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_delete_session(\WP_REST_Request $request)
	{
		global $wpdb;

		$id = (int) $request->get_param('id');
		$sessions_tbl = $wpdb->prefix . 'wpaic_sessions';
		$messages_tbl = $wpdb->prefix . 'wpaic_messages';

		$exists = (bool) $wpdb->get_var(
			$wpdb->prepare("SELECT id FROM {$sessions_tbl} WHERE id = %d LIMIT 1", $id)
		);

		if (! $exists) {
			return new \WP_Error('not_found', __('Session not found.', 'wp-ai-chatbot'), ['status' => 404]);
		}

		// Delete messages first (no FK constraint but maintains referential integrity).
		$wpdb->delete($messages_tbl, ['session_id' => $id], ['%d']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$deleted = (bool) $wpdb->delete($sessions_tbl, ['id' => $id], ['%d']); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		/**
		 * Fires after a session is manually deleted from admin.
		 *
		 * @since 1.0.0
		 * @param int $session_id Deleted session row ID.
		 */
		do_action('wpaic_session_admin_deleted', $id);

		return new \WP_REST_Response(['deleted' => $deleted], 200);
	}

	/**
	 * REST: aggregated usage statistics.
	 *
	 * Returns 30-day totals (stat cards) plus per-row daily breakdown.
	 *
	 * @param  \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function rest_usage_stats(\WP_REST_Request $request): \WP_REST_Response
	{
		global $wpdb;

		$usage_tbl = $wpdb->prefix . 'wpaic_usage_daily';
		$sessions_tbl = $wpdb->prefix . 'wpaic_sessions';

		$cutoff_30 = gmdate('Y-m-d', strtotime('-30 days'));

		// --- Totals over 30 days ---

		// Session count from sessions table.
		$session_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$sessions_tbl} WHERE created_at >= %s",
				$cutoff_30 . ' 00:00:00'
			)
		);

		$message_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(message_count),0) FROM {$sessions_tbl} WHERE created_at >= %s",
				$cutoff_30 . ' 00:00:00'
			)
		);

		// Token + cost totals from usage_daily table.
		$usage_totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(total_tokens),0) AS tokens,
				COALESCE(SUM(cost_micros),0) AS cost_micros
				FROM {$usage_tbl} WHERE date >= %s",
				$cutoff_30
			),
			ARRAY_A
		);

		// --- Daily rows (last 30 days) ---
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT date, provider, model, prompt_tokens, completion_tokens,
				total_tokens, cost_micros, conversations
				FROM {$usage_tbl} WHERE date >= %s
				ORDER BY date DESC, provider ASC, model ASC
				LIMIT 500",
				$cutoff_30
			),
			ARRAY_A
		);

		if (! is_array($rows)) {
			$rows = [];
		}

		return new \WP_REST_Response([
			'totals' => [
				'sessions' => $session_count,
				'messages' => $message_count,
				'tokens' => (int) ($usage_totals['tokens'] ?? 0),
				'cost_micros' => (int) ($usage_totals['cost_micros'] ?? 0),
			],
			'rows' => $rows,
		], 200);
	}

	// =========================================================================
	// CSV Export (admin-post handler)
	// =========================================================================

	/**
	 * Handle CSV export download via admin-post.php.
	 * Hooked to `admin_post_wpaic_export_transcripts` from Plugin::register_hooks().
	 *
	 * @return void
	 */
	public static function handle_export(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions.', 'wp-ai-chatbot'), 403);
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		check_admin_referer('wpaic_export_transcripts_nonce', 'wpaic_export_nonce');

		global $wpdb;

		$sessions_tbl = $wpdb->prefix . 'wpaic_sessions';

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_from = isset($_GET['date_from']) ? sanitize_text_field(wp_unslash($_GET['date_from'])) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$date_to = isset($_GET['date_to']) ? sanitize_text_field(wp_unslash($_GET['date_to'])) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';

		$where = '1=1';
		$values = [];

		if (in_array($status, ['active', 'closed', 'expired'], true)) {
			$where .= ' AND status = %s';
			$values[] = $status;
		}
		if ($date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
			$where .= ' AND created_at >= %s';
			$values[] = $date_from . ' 00:00:00';
		}
		if ($date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
			$where .= ' AND created_at <= %s';
			$values[] = $date_to . ' 23:59:59';
		}

		$sql = "SELECT id, session_token, user_id, status, provider, model,
			total_prompt_tokens, total_completion_tokens, total_cost_micros,
			message_count, created_at
			FROM {$sessions_tbl} WHERE {$where} ORDER BY created_at DESC LIMIT 50000";

		$rows = $values
			? $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A) // phpcs:ignore
			: $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore

		if (! is_array($rows)) {
			$rows = [];
		}

		$filename = 'wpaic-transcripts-' . gmdate('Y-m-d') . '.csv';

		nocache_headers();
		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Pragma: no-cache');
		header('Expires: 0');

		$out = fopen('php://output', 'w');
		if (false === $out) {
			exit;
		}

		// UTF-8 BOM for Excel compatibility.
		fwrite($out, "\xEF\xBB\xBF");

		fputcsv($out, [
			'Session Token', 'User ID', 'Status', 'Provider', 'Model',
			'Prompt Tokens', 'Completion Tokens', 'Total Cost (microcents)',
			'Message Count', 'Started At',
		]);

		foreach ($rows as $row) {
			fputcsv($out, [
				$row['session_token'],
				$row['user_id'],
				$row['status'],
				$row['provider'],
				$row['model'],
				$row['total_prompt_tokens'],
				$row['total_completion_tokens'],
				$row['total_cost_micros'],
				$row['message_count'],
				$row['created_at'],
			]);
		}

		fclose($out);
		exit;
	}
}
