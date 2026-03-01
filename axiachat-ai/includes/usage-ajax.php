<?php

if (!defined('ABSPATH')) {
	exit;
}

add_action('wp_ajax_aichat_get_usage_summary', 'aichat_get_usage_summary');
add_action('wp_ajax_aichat_get_usage_timeseries', 'aichat_get_usage_timeseries');
add_action('wp_ajax_aichat_get_last_conversations', 'aichat_get_last_conversations');

function aichat_usage_cap_check()
{
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
}

function aichat_get_usage_summary()
{
	// CSRF protection: usage dashboard requests must include nonce generated in includes/usage.php
	check_ajax_referer('aichat_usage', 'nonce');
	aichat_usage_cap_check();
	global $wpdb;
	$conv = $wpdb->prefix . 'aichat_conversations';
	// Use wp_date + current_time('timestamp') to avoid mixing PHP date() (server TZ) with WP timezone.
	$ts_today = current_time('timestamp'); // Local blog timestamp
	$d_today = wp_date('Y-m-d', $ts_today);
	$d_7 = wp_date('Y-m-d', $ts_today - 7 * DAY_IN_SECONDS);
	$d_30 = wp_date('Y-m-d', $ts_today - 30 * DAY_IN_SECONDS);
	// Build a prepared query for the last 30 days (local time) starting from local midnight 30 days ago.
	$start_30_midnight = $d_30 . ' 00:00:00';
	$sql_summary = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $conv is a trusted plugin table name.
		"SELECT DATE(created_at) d, SUM(total_tokens) tt, SUM(cost_micros) cm, COUNT(*) c FROM {$conv} WHERE created_at >= %s GROUP BY DATE(created_at)",
		$start_30_midnight
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above; admin dashboard read.
	$rows = $wpdb->get_results($sql_summary, ARRAY_A) ?: [];
	$today = ['tokens' => 0, 'cost' => 0, 'conversations' => 0];
	$last7 = ['tokens' => 0, 'cost' => 0, 'conversations' => 0];
	$last30 = ['tokens' => 0, 'cost' => 0, 'conversations' => 0];
	foreach ($rows as $r) {
		$d = $r['d'];
		$tt = (int) $r['tt'];
		$cm = (int) $r['cm'];
		$c = (int) $r['c'];
		if ($d === $d_today) {
			$today['tokens'] += $tt;
			$today['cost'] += $cm;
			$today['conversations'] += $c;
		}
		if ($d >= $d_7) {
			$last7['tokens'] += $tt;
			$last7['cost'] += $cm;
			$last7['conversations'] += $c;
		}
		if ($d >= $d_30) {
			$last30['tokens'] += $tt;
			$last30['cost'] += $cm;
			$last30['conversations'] += $c;
		}
	}
	// Top modelos últimos 30 días (mismo rango calculado) usando consulta preparada.
	$sql_top = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $conv is a trusted plugin table name.
		"SELECT model, provider, SUM(cost_micros) cm FROM {$conv} WHERE created_at >= %s AND cost_micros IS NOT NULL GROUP BY model, provider ORDER BY cm DESC LIMIT 10",
		$start_30_midnight
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above; admin dashboard read.
	$top_models = $wpdb->get_results($sql_top, ARRAY_A) ?: [];
	wp_send_json_success([
	'today' => $today,
	'last7' => $last7,
	'last30' => $last30,
	'top_models' => $top_models,
	]);
}

function aichat_get_usage_timeseries()
{
	// CSRF protection
	check_ajax_referer('aichat_usage', 'nonce');
	aichat_usage_cap_check();
	global $wpdb;
	$conv = $wpdb->prefix . 'aichat_conversations';
	$date_from = isset($_POST['date_from']) ? sanitize_text_field(wp_unslash($_POST['date_from'])) : '';
	$date_to = isset($_POST['date_to']) ? sanitize_text_field(wp_unslash($_POST['date_to'])) : '';
	if (!$date_from || !$date_to) {
		$ts_today = current_time('timestamp');
		$date_to = wp_date('Y-m-d', $ts_today);
		$date_from = wp_date('Y-m-d', $ts_today - 30 * DAY_IN_SECONDS);
	}
	// Validar formato simple YYYY-MM-DD
	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
		wp_send_json_error(['message' => 'Invalid date format'], 400);
	}
	// BETWEEN inclusivo: inicio 00:00:00 fin 23:59:59
	$start_ts = $date_from . ' 00:00:00';
	$end_ts = $date_to . ' 23:59:59';
	// Build fully prepared query (table name is trusted via $wpdb->prefix). Using prepare for date bounds appeases phpcs.
	$sql = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $conv is a trusted plugin table name.
		"SELECT DATE(created_at) d, SUM(prompt_tokens) p, SUM(completion_tokens) c, SUM(total_tokens) t, SUM(cost_micros) m FROM {$conv} WHERE created_at BETWEEN %s AND %s GROUP BY DATE(created_at) ORDER BY d ASC",
		$start_ts,
		$end_ts
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above; admin dashboard read.
	$rows = $wpdb->get_results($sql, ARRAY_A) ?: [];
	wp_send_json_success(['series' => $rows, 'date_from' => $date_from, 'date_to' => $date_to]);
}

function aichat_get_last_conversations()
{
	check_ajax_referer('aichat_usage', 'nonce');
	aichat_usage_cap_check();

	$limit_raw = isset($_POST['limit']) ? absint(wp_unslash($_POST['limit'])) : 50;
	$offset_raw = isset($_POST['offset']) ? absint(wp_unslash($_POST['offset'])) : 0;
	$limit = max(1, min(200, (int) $limit_raw));
	$offset = max(0, (int) $offset_raw);

	global $wpdb;
	$conv = $wpdb->prefix . 'aichat_conversations';

	$sql = $wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $conv is a trusted plugin table name.
		"SELECT id, created_at, bot_slug, provider, model, prompt_tokens, completion_tokens, total_tokens, cost_micros, message, response
     FROM {$conv}
     ORDER BY id DESC
     LIMIT %d OFFSET %d",
		$limit,
		$offset
	);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin dashboard read.
	$rows = $wpdb->get_results($sql, ARRAY_A) ?: [];

	$items = array_map(function ($r) {
		$resp_txt = isset($r['response']) ? wp_strip_all_tags((string) $r['response']) : '';
		$resp_txt = preg_replace('/\s+/', ' ', trim($resp_txt));
		$q_txt = isset($r['message']) ? wp_strip_all_tags((string) $r['message']) : '';
		$q_txt = preg_replace('/\s+/', ' ', trim($q_txt));

		// Previews para tabla
		$resp_preview = function_exists('mb_substr') ? mb_substr($resp_txt, 0, 260) : substr($resp_txt, 0, 260);
		if (function_exists('mb_strlen') ? (mb_strlen($resp_txt) > 260) : (strlen($resp_txt) > 260)) {
			$resp_preview .= '…';
		}
		$q_preview = function_exists('mb_substr') ? mb_substr($q_txt, 0, 140) : substr($q_txt, 0, 140);
		if (function_exists('mb_strlen') ? (mb_strlen($q_txt) > 140) : (strlen($q_txt) > 140)) {
			$q_preview .= '…';
		}

		return [
		  'id' => (int) ($r['id'] ?? 0),
		  'created_at' => (string) ($r['created_at'] ?? ''),
		  'bot_slug' => (string) ($r['bot_slug'] ?? ''),
		  'provider' => (string) ($r['provider'] ?? ''),
		  'model' => (string) ($r['model'] ?? ''),
		  'prompt_tokens' => isset($r['prompt_tokens']) ? (int) $r['prompt_tokens'] : null,
		  'completion_tokens' => isset($r['completion_tokens']) ? (int) $r['completion_tokens'] : null,
		  'total_tokens' => isset($r['total_tokens']) ? (int) $r['total_tokens'] : null,
		  'cost_micros' => isset($r['cost_micros']) ? (int) $r['cost_micros'] : null,
		  'question_preview' => $q_preview,
		  'response_preview' => $resp_preview,
		  'response_full' => $resp_txt,
		];
	}, $rows);

	wp_send_json_success([
	'items' => $items,
	'limit' => $limit,
	'offset' => $offset,
	]);
}
