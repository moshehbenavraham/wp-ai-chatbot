<?php

if (!defined('ABSPATH')) {
	exit;
}

/** Admin page: usage and cost */

function aichat_usage_admin_page()
{
	if (!current_user_can('manage_options')) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- View-only tab switch; no state changes.
	$active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'last';

	echo '<div class="wrap"><h1>' . esc_html__('AI Chat – Usage / Cost', 'axiachat-ai') . '</h1>';

	// Tabs navigation
	echo '<h2 class="nav-tab-wrapper" style="margin-bottom:20px;">';
	echo '<a href="?page=aichat-usage&tab=last" class="nav-tab' . ($active_tab === 'last' ? ' nav-tab-active' : '') . '">' . esc_html__('Last Conversations', 'axiachat-ai') . '</a>';
	echo '<a href="?page=aichat-usage&tab=usage" class="nav-tab' . ($active_tab === 'usage' ? ' nav-tab-active' : '') . '">' . esc_html__('Usage Metrics', 'axiachat-ai') . '</a>';
	echo '<a href="?page=aichat-usage&tab=pricing" class="nav-tab' . ($active_tab === 'pricing' ? ' nav-tab-active' : '') . '">' . esc_html__('Pricing Tables', 'axiachat-ai') . '</a>';
	echo '</h2>';

	if ($active_tab === 'pricing') {
		aichat_render_pricing_tab();
	} elseif ($active_tab === 'last') {
		aichat_render_last_conversations_tab();
	} else {
		aichat_render_usage_tab();
	}

	echo '</div>';
}

function aichat_render_last_conversations_tab()
{
	echo '<p class="description">' . esc_html__('Latest conversation turns (newest first). Response is shown in the right column.', 'axiachat-ai') . '</p>';

	echo '<div id="aichat-lastconv" style="margin-top:12px;">';
	echo '<div style="display:flex;align-items:center;gap:10px;margin:10px 0;">'
	. '<button type="button" class="button" id="aichat-lastconv-refresh">' . esc_html__('Refresh', 'axiachat-ai') . '</button>'
	. '<span id="aichat-lastconv-status" style="color:#666;"></span>'
	. '</div>';

	echo '<table class="widefat striped" id="aichat-lastconv-table" style="table-layout:fixed;">'
	. '<thead><tr>'
	  . '<th style="width:140px;">' . esc_html__('Date/Time', 'axiachat-ai') . '</th>'
	  . '<th style="width:120px;">' . esc_html__('Bot', 'axiachat-ai') . '</th>'
	  . '<th style="width:110px;">' . esc_html__('Provider', 'axiachat-ai') . '</th>'
	  . '<th style="width:180px;">' . esc_html__('Model', 'axiachat-ai') . '</th>'
	  . '<th style="width:140px;">' . esc_html__('Tokens (p/c/t)', 'axiachat-ai') . '</th>'
	  . '<th style="width:110px;">' . esc_html__('Cost (USD)', 'axiachat-ai') . '</th>'
	  . '<th style="width:260px;">' . esc_html__('Question', 'axiachat-ai') . '</th>'
	  . '<th>' . esc_html__('Response', 'axiachat-ai') . '</th>'
	. '</tr></thead>'
	. '<tbody><tr><td colspan="8">' . esc_html__('Loading...', 'axiachat-ai') . '</td></tr></tbody>'
	. '</table>';

	echo '<div style="margin-top:12px;">'
	. '<button type="button" class="button" id="aichat-lastconv-loadmore">' . esc_html__('Load more', 'axiachat-ai') . '</button>'
	. '</div>';

	echo '</div>';
}

function aichat_render_usage_tab()
{
	echo '<p class="description">' . esc_html__('Token & cost metrics (chat). Costs are approximate based on configured pricing.', 'axiachat-ai') . '</p>';
	echo '<div id="aichat-usage-kpis" class="aichat-usage-grid" style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:20px;">'
	. '<div class="usage-box" style="flex:1;min-width:180px;background:#fff;border:1px solid #ddd;padding:12px;border-radius:6px;"><strong>' . esc_html__('Today', 'axiachat-ai') . '</strong><br><span data-kpi="today-cost">-</span><br><small><span data-kpi="today-tokens">-</span> ' . esc_html__('tokens', 'axiachat-ai') . '</small></div>'
	. '<div class="usage-box" style="flex:1;min-width:180px;background:#fff;border:1px solid #ddd;padding:12px;border-radius:6px;"><strong>' . esc_html__('Last 7 days', 'axiachat-ai') . '</strong><br><span data-kpi="last7-cost">-</span><br><small><span data-kpi="last7-tokens">-</span> ' . esc_html__('tokens', 'axiachat-ai') . '</small></div>'
	. '<div class="usage-box" style="flex:1;min-width:180px;background:#fff;border:1px solid #ddd;padding:12px;border-radius:6px;"><strong>' . esc_html__('Last 30 days', 'axiachat-ai') . '</strong><br><span data-kpi="last30-cost">-</span><br><small><span data-kpi="last30-tokens">-</span> ' . esc_html__('tokens', 'axiachat-ai') . '</small></div>'
	  . '</div>';
	echo '<h2 style="margin-top:30px;">' . esc_html__('Timeseries (Last 30 days)', 'axiachat-ai') . '</h2>';
	echo '<div style="max-width:100%;max-height:400px;overflow:hidden;">'
	 . '<canvas id="aichat-usage-chart" style="max-height:400px;width:100%;"></canvas>'
	. '</div><div id="aichat-usage-nodata" style="margin-top:8px;color:#666;display:none;">' . esc_html__('No data', 'axiachat-ai') . '</div>';
	echo '<h2 style="margin-top:30px;">' . esc_html__('Top Models (30d)', 'axiachat-ai') . '</h2>';
	echo '<table class="widefat" id="aichat-usage-topmodels"><thead><tr><th>' . esc_html__('Model', 'axiachat-ai') . '</th><th>' . esc_html__('Provider', 'axiachat-ai') . '</th><th>' . esc_html__('Cost (USD)', 'axiachat-ai') . '</th></tr></thead><tbody><tr><td colspan="3">' . esc_html__('Loading...', 'axiachat-ai') . '</td></tr></tbody></table>';
}

function aichat_render_pricing_tab()
{
	$pricing = aichat_model_pricing();

	echo '<p class="description">' . esc_html__('Current pricing per 1K tokens (input / output). Standard rates only.', 'axiachat-ai') . ' <em>' . esc_html__('Updated: January 15, 2026', 'axiachat-ai') . '</em></p>';

	// OpenAI Section
	echo '<h2 style="margin-top:20px;">OpenAI Models <a href="https://openai.com/api/pricing/" target="_blank" class="button button-small" style="margin-left:10px;">' . esc_html__('Official Pricing', 'axiachat-ai') . ' ↗</a></h2>';
	echo '<table class="widefat" style="margin-top:10px;max-width:800px;">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__('Model', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Input (per 1K tokens)', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Output (per 1K tokens)', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Input (per 1M tokens)', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Output (per 1M tokens)', 'axiachat-ai') . '</th>';
	echo '</tr></thead><tbody>';

	if (isset($pricing['openai'])) {
		foreach ($pricing['openai'] as $model => $rates) {
			echo '<tr>';
			echo '<td><strong>' . esc_html($model) . '</strong></td>';
			echo '<td>$' . esc_html(number_format($rates['input_per_1k'], 5)) . '</td>';
			echo '<td>$' . esc_html(number_format($rates['output_per_1k'], 5)) . '</td>';
			echo '<td>$' . esc_html(number_format($rates['input_per_1k'] * 1000, 2)) . '</td>';
			echo '<td>$' . esc_html(number_format($rates['output_per_1k'] * 1000, 2)) . '</td>';
			echo '</tr>';
		}
	}

	echo '</tbody></table>';

	// Claude/Anthropic Section
	echo '<h2 style="margin-top:40px;">Anthropic (Claude) Models <a href="https://www.anthropic.com/pricing" target="_blank" class="button button-small" style="margin-left:10px;">' . esc_html__('Official Pricing', 'axiachat-ai') . ' ↗</a></h2>';
	echo '<table class="widefat" style="margin-top:10px;max-width:800px;">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__('Model', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Input (per 1K tokens)', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Output (per 1K tokens)', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Input (per 1M tokens)', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Output (per 1M tokens)', 'axiachat-ai') . '</th>';
	echo '</tr></thead><tbody>';

	if (isset($pricing['claude'])) {
		foreach ($pricing['claude'] as $model => $rates) {
			echo '<tr>';
			echo '<td><strong>' . esc_html($model) . '</strong></td>';
			echo '<td>$' . esc_html(number_format($rates['input_per_1k'], 5)) . '</td>';
			echo '<td>$' . esc_html(number_format($rates['output_per_1k'], 5)) . '</td>';
			echo '<td>$' . esc_html(number_format($rates['input_per_1k'] * 1000, 2)) . '</td>';
			echo '<td>$' . esc_html(number_format($rates['output_per_1k'] * 1000, 2)) . '</td>';
			echo '</tr>';
		}
	}

	echo '</tbody></table>';

	// Gemini Section
	echo '<h2 style="margin-top:40px;">Google Gemini Models <a href="https://ai.google.dev/pricing" target="_blank" class="button button-small" style="margin-left:10px;">' . esc_html__('Official Pricing', 'axiachat-ai') . ' ↗</a></h2>';
	echo '<table class="widefat" style="margin-top:10px;max-width:800px;">';
	echo '<thead><tr>';
	echo '<th>' . esc_html__('Model', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Input (per 1K tokens)', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Output (per 1K tokens)', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Input (per 1M tokens)', 'axiachat-ai') . '</th>';
	echo '<th>' . esc_html__('Output (per 1M tokens)', 'axiachat-ai') . '</th>';
	echo '</tr></thead><tbody>';

	if (isset($pricing['gemini'])) {
		foreach ($pricing['gemini'] as $model => $rates) {
			echo '<tr>';
			echo '<td><strong>' . esc_html($model) . '</strong></td>';
			echo '<td>$' . esc_html(number_format($rates['input_per_1k'], 5)) . '</td>';
			echo '<td>$' . esc_html(number_format($rates['output_per_1k'], 5)) . '</td>';
			echo '<td>$' . esc_html(number_format($rates['input_per_1k'] * 1000, 2)) . '</td>';
			echo '<td>$' . esc_html(number_format($rates['output_per_1k'] * 1000, 2)) . '</td>';
			echo '</tr>';
		}
	}

	echo '</tbody></table>';

	echo '<p style="margin-top:30px;color:#666;font-size:0.9em;">' . esc_html__('Note: Pricing shown is for standard API usage. Batch processing, cached prompts, and fine-tuned models may have different rates. Always verify with official provider documentation.', 'axiachat-ai') . '</p>';
}

add_action('admin_enqueue_scripts', function ($hook) {
	// Solo cargar scripts en la página de uso/coste exacta.
	if ($hook !== 'axiachat-ai_page_aichat-usage') {
		return;
	}

	// Patrón igual que en class-aichat-core.php: derivar base_path/base_url desde este include
	$base_path = dirname(plugin_dir_path(__FILE__)) . '/'; // raíz plugin
	$base_url = dirname(plugin_dir_url(__FILE__)) . '/';

	// Único archivo Chart.js soportado ahora: chart.umd.min.js
	$chart_rel = 'assets/js/vendor/chart.umd.min.js';
	$usage_rel = 'assets/js/usage.js';
	$chart_path = $base_path . $chart_rel;
	$usage_path = $base_path . $usage_rel;
	if (! file_exists($chart_path)) {
		if (function_exists('aichat_log_debug')) {
			aichat_log_debug('[AIChat Usage] Missing chart.umd.min.js at ' . $chart_path);
		}

		return; // evita errores si falta
	}
	if (function_exists('aichat_log_debug')) {
		aichat_log_debug('[AIChat Usage] Using Chart file: ' . $chart_rel);
	}
	$chart_url = $base_url . $chart_rel;
	$usage_url = $base_url . $usage_rel;
	$ver_chart = (string) filemtime($chart_path);
	$ver_usage = file_exists($usage_path) ? (string) filemtime($usage_path) : '1.0.0';

	if (! wp_script_is('aichat-chartjs', 'registered')) {
		wp_register_script('aichat-chartjs', $chart_url, [], $ver_chart, true);
	}
	wp_enqueue_script('aichat-chartjs');

	// Disparar evento incluso si DOMContentLoaded ya pasó (si la página se recarga via SPA futura)
	$inline = "(function(){function fire(){document.dispatchEvent(new CustomEvent('aichat_chart_ready'));} if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',fire);}else{fire();}})();";
	wp_add_inline_script('aichat-chartjs', $inline, 'after');

	// Script dashboard usage dependiente de Chart
	$usage_handle = 'aichat-usage';
	// Ensure wp-i18n is in deps for translation support
	$usage_deps = ['jquery', 'aichat-chartjs', 'wp-i18n'];
	wp_enqueue_script($usage_handle, $usage_url, $usage_deps, $ver_usage, true);
	wp_set_script_translations($usage_handle, 'axiachat-ai', dirname(plugin_dir_path(__FILE__)) . '/languages');
	wp_localize_script($usage_handle, 'AIChatUsageAjax', [
	'ajax_url' => admin_url('admin-ajax.php'),
	// Dedicated nonce for usage endpoints (see usage-ajax.php)
	'nonce' => wp_create_nonce('aichat_usage'),
	'strings' => [
	'totalTokens' => esc_html__('Total Tokens','axiachat-ai'),
	'costLabel' => esc_html__('Cost','axiachat-ai'),
	],
	]);
});
