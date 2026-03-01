<?php

/**
 * Training AJAX handlers (v3.0.1)
 *
 * Endpoints used by the Training hub, Instructions and Context sub-pages.
 *
 * @package AxiaChat
 * @since   3.0.1
 */

if (! defined('ABSPATH')) {
	exit;
}

// phpcs:disable WordPress.Security.NonceVerification.Missing -- All handlers verify via aichat_training_check().
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Trusted plugin tables via $wpdb->prefix.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only operations.

/* ──────────────────────────────
 * Register AJAX actions
 * ────────────────────────────── */
add_action('wp_ajax_aichat_training_save_instructions', 'aichat_training_save_instructions');
add_action('wp_ajax_aichat_training_save_context_sources', 'aichat_training_save_context_sources');
add_action('wp_ajax_aichat_training_set_bot_context', 'aichat_training_set_bot_context');
add_action('wp_ajax_aichat_training_save_advanced', 'aichat_training_save_advanced');

/* ──────────────────────────────
 * Helpers
 * ────────────────────────────── */
function aichat_training_check()
{
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
	check_ajax_referer('aichat_training', 'nonce');
}

function aichat_training_bots_table()
{
	global $wpdb;

	return $wpdb->prefix . 'aichat_bots';
}

function aichat_training_ctx_table()
{
	global $wpdb;

	return $wpdb->prefix . 'aichat_contexts';
}

/* ══════════════════════════════
 * 1. Save Instructions
 *    Saves the instructions (system prompt) field for a given bot.
 * ══════════════════════════════ */
function aichat_training_save_instructions()
{
	aichat_training_check();
	global $wpdb;

	$bot_id = isset($_POST['bot_id']) ? absint(wp_unslash($_POST['bot_id'])) : 0;
	$instructions = isset($_POST['instructions']) ? sanitize_textarea_field(wp_unslash($_POST['instructions'])) : '';

	if ($bot_id <= 0) {
		wp_send_json_error(['message' => 'Invalid bot_id.']);
	}

	$table = aichat_training_bots_table();

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
	$updated = $wpdb->update(
		$table,
		['instructions' => $instructions, 'updated_at' => current_time('mysql')],
		['id' => $bot_id],
		['%s', '%s'],
		['%d']
	);

	if (false === $updated) {
		wp_send_json_error(['message' => 'DB error: ' . $wpdb->last_error]);
	}

	wp_send_json_success(['message' => 'Instructions saved.', 'bot_id' => $bot_id]);
}

/* ══════════════════════════════
 * 2. Save Context Sources
 *    Creates a new context (or reuses existing) and triggers indexing for chosen content types.
 *    Expected POST params:
 *    - bot_id  (int)
 *    - name    (string) optional context name, defaults to "Bot – context"
 *    - sources (JSON string) e.g.: {"wordpress":{"mode":"all","ids":[]},"woocommerce":{"mode":"custom","ids":[1,2]},"files":{"mode":"none","ids":[]}}
 * ══════════════════════════════ */
function aichat_training_save_context_sources()
{
	aichat_training_check();
	global $wpdb;

	$bot_id = isset($_POST['bot_id']) ? absint(wp_unslash($_POST['bot_id'])) : 0;
	$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
	$src = isset($_POST['sources']) ? sanitize_text_field(wp_unslash($_POST['sources'])) : '{}';

	if ($bot_id <= 0) {
		wp_send_json_error(['message' => 'Invalid bot_id.']);
	}

	$sources = json_decode($src, true);
	if (! is_array($sources)) {
		$sources = [];
	}

	$bots_table = aichat_training_bots_table();
	$ctx_table = aichat_training_ctx_table();

	// Get current bot
	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
	$bot = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$bots_table} WHERE id = %d", $bot_id), ARRAY_A);
	if (! $bot) {
		wp_send_json_error(['message' => 'Bot not found.']);
	}

	// Determine context_id: reuse existing or create new
	$context_id = (int) ($bot['context_id'] ?? 0);
	if ($context_id <= 0) {
		// Create new context
		if (empty($name)) {
			$name = $bot['name'] . ' – context';
		}
		$wpdb->insert(
			$ctx_table,
			[
				'name' => $name,
				'context_type' => 'local',
				'processing_status' => 'pending',
			],
			['%s', '%s', '%s']
		);
		$context_id = (int) $wpdb->insert_id;
		if ($context_id <= 0) {
			wp_send_json_error(['message' => 'Could not create context row.']);
		}

		// Link bot → context
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->update($bots_table, ['context_id' => $context_id, 'context_mode' => 'embeddings'], ['id' => $bot_id], ['%d', '%s'], ['%d']);
	}

	// Build unified selected_ids from all active sources
	$all_ids = [];
	foreach (['wordpress', 'woocommerce', 'files'] as $key) {
		if (! isset($sources[ $key ])) {
			continue;
		}
		$s = $sources[ $key ];
		if (($s['mode'] ?? 'none') === 'none') {
			continue;
		}
		if (($s['mode'] ?? '') === 'all') {
			// "all" means auto-discover; handled by indexing engine
			// Store marker so indexing knows to fetch all for this type
			update_option("aichat_ctx_{$context_id}_src_{$key}", 'all', false);
		} elseif (($s['mode'] ?? '') === 'custom' && ! empty($s['ids'])) {
			update_option("aichat_ctx_{$context_id}_src_{$key}", wp_json_encode($s['ids']), false);
			$all_ids = array_merge($all_ids, array_map('absint', $s['ids']));
		}
	}

	// Store the combined sources config for later reference
	update_option("aichat_ctx_{$context_id}_sources", $src, false);

	wp_send_json_success([
		'message' => 'Context sources saved.',
		'context_id' => $context_id,
		'bot_id' => $bot_id,
		'ids_count' => count($all_ids),
	]);
}

/* ══════════════════════════════
 * 3. Set Bot Context
 *    Links a bot to an existing context_id and sets context_mode.
 * ══════════════════════════════ */
function aichat_training_set_bot_context()
{
	aichat_training_check();
	global $wpdb;

	$bot_id = isset($_POST['bot_id']) ? absint(wp_unslash($_POST['bot_id'])) : 0;
	$context_id = isset($_POST['context_id']) ? absint(wp_unslash($_POST['context_id'])) : 0;
	$context_mode = isset($_POST['context_mode']) ? sanitize_key(wp_unslash($_POST['context_mode'])) : 'embeddings';

	if ($bot_id <= 0) {
		wp_send_json_error(['message' => 'Invalid bot_id.']);
	}

	$allowed_modes = ['embeddings', 'page', 'none', 'auto'];
	if (! in_array($context_mode, $allowed_modes, true)) {
		$context_mode = 'embeddings';
	}

	$table = aichat_training_bots_table();

	// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
	$wpdb->update(
		$table,
		[
			'context_id' => $context_id,
			'context_mode' => $context_mode,
			'updated_at' => current_time('mysql'),
		],
		['id' => $bot_id],
		['%d', '%s', '%s'],
		['%d']
	);

	wp_send_json_success(['message' => 'Bot context updated.', 'bot_id' => $bot_id]);
}

/* ══════════════════════════════
 * 4. Save Advanced Settings
 *    Saves context name/type/autosync + bot thresholds (context_max_length, context_limit)
 *    + the "include page content" checkbox (context_mode with page fallback).
 * ══════════════════════════════ */
function aichat_training_save_advanced()
{
	aichat_training_check();
	global $wpdb;

	$bot_id = isset($_POST['bot_id']) ? absint(wp_unslash($_POST['bot_id'])) : 0;
	$context_id = isset($_POST['context_id']) ? absint(wp_unslash($_POST['context_id'])) : 0;

	if ($bot_id <= 0) {
		wp_send_json_error(['message' => 'Invalid bot_id.']);
	}

	$bots_table = aichat_training_bots_table();
	$ctx_table = aichat_training_ctx_table();

	// Bot-level thresholds
	$bot_updates = [];
	$bot_formats = [];

	if (isset($_POST['context_max_length'])) {
		$bot_updates['context_max_length'] = absint($_POST['context_max_length']);
		$bot_formats[] = '%d';
	}
	if (isset($_POST['context_limit'])) {
		$val = absint($_POST['context_limit']);
		$bot_updates['context_limit'] = max(3, min(10, $val));
		$bot_formats[] = '%d';
	}
	if (isset($_POST['include_page_content'])) {
		// If checked and bot has embeddings context, switch to "auto" which includes both embeddings + page
		$include_page = (int) $_POST['include_page_content'];
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
		$current_mode = $wpdb->get_var($wpdb->prepare("SELECT context_mode FROM {$bots_table} WHERE id = %d", $bot_id));
		if ($include_page && $current_mode === 'embeddings') {
			$bot_updates['context_mode'] = 'auto';
			$bot_formats[] = '%s';
		} elseif (! $include_page && $current_mode === 'auto') {
			$bot_updates['context_mode'] = 'embeddings';
			$bot_formats[] = '%s';
		}
	}

	if (! empty($bot_updates)) {
		$bot_updates['updated_at'] = current_time('mysql');
		$bot_formats[] = '%s';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
		$wpdb->update($bots_table, $bot_updates, ['id' => $bot_id], $bot_formats, ['%d']);
	}

	// Context-level settings
	if ($context_id > 0) {
		$ctx_updates = [];
		$ctx_formats = [];

		if (isset($_POST['ctx_name'])) {
			$ctx_updates['name'] = sanitize_text_field(wp_unslash($_POST['ctx_name']));
			$ctx_formats[] = '%s';
		}
		if (isset($_POST['ctx_type'])) {
			$ctx_updates['context_type'] = sanitize_key(wp_unslash($_POST['ctx_type']));
			$ctx_formats[] = '%s';
		}
		if (isset($_POST['remote_type'])) {
			$ctx_updates['remote_type'] = sanitize_text_field(wp_unslash($_POST['remote_type']));
			$ctx_formats[] = '%s';
		}
		if (isset($_POST['remote_api_key'])) {
			$ctx_updates['remote_api_key'] = sanitize_text_field(wp_unslash($_POST['remote_api_key']));
			$ctx_formats[] = '%s';
		}
		if (isset($_POST['remote_endpoint'])) {
			$ctx_updates['remote_endpoint'] = esc_url_raw(wp_unslash($_POST['remote_endpoint']));
			$ctx_formats[] = '%s';
		}
		if (isset($_POST['ctx_autosync'])) {
			$autosync = (int) $_POST['ctx_autosync'];
			$ctx_updates['autosync'] = $autosync ? 1 : 0;
			$ctx_formats[] = '%d';
		}
		if (isset($_POST['ctx_autosync_mode'])) {
			$mode = sanitize_key(wp_unslash($_POST['ctx_autosync_mode']));
			if (in_array($mode, ['updates', 'updates_and_new'], true)) {
				$ctx_updates['autosync_mode'] = $mode;
				$ctx_formats[] = '%s';
			}
		}

		// Indexing options (Advanced Indexing Options section)
		if (isset($_POST['indexing_options'])) {
			$raw_idx = sanitize_text_field(wp_unslash($_POST['indexing_options']));
			$idx_parsed = json_decode($raw_idx, true);
			if (is_array($idx_parsed)) {
				$ctx_updates['indexing_options'] = wp_json_encode($idx_parsed);
				$ctx_formats[] = '%s';
			}
		}

		if (! empty($ctx_updates)) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter
			$wpdb->update($ctx_table, $ctx_updates, ['id' => $context_id], $ctx_formats, ['%d']);
		}
	}

	wp_send_json_success(['message' => 'Advanced settings saved.', 'bot_id' => $bot_id, 'context_id' => $context_id]);
}
