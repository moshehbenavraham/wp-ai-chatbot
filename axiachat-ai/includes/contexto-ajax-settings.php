<?php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Incluir funciones necesarias
require_once plugin_dir_path(__FILE__) . 'contexto-functions.php';

// AJAX para cargar contextos
add_action('wp_ajax_aichat_load_contexts', 'aichat_load_contexts');
function aichat_load_contexts()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
	global $wpdb;
	$table_ctx = $wpdb->prefix . 'aichat_contexts';
	$table_chunks = $wpdb->prefix . 'aichat_chunks';
	// Traer todos los campos necesarios para reconstruir la tabla en JS
	$sql = "SELECT c.id, c.name, c.context_type, c.embedding_provider, c.processing_progress, c.processing_status, c.created_at, c.autosync, c.autosync_mode,
                   (SELECT COUNT(*) FROM $table_chunks ch WHERE ch.id_context = c.id) AS chunk_count,
                   (SELECT COUNT(DISTINCT post_id) FROM $table_chunks ch2 WHERE ch2.id_context = c.id) AS post_count
            FROM $table_ctx c ORDER BY c.id ASC";
	$contexts = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX listing; internal tables; no user input.
	if (! $contexts) {
		$contexts = [];
	}
	wp_send_json_success(['contexts' => $contexts]);
}

// AJAX para actualizar nombre del contexto
add_action('wp_ajax_aichat_update_context_name', 'aichat_update_context_name');
function aichat_update_context_name()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	global $wpdb;
	$id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
	$name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
	if ($id <= 0 || $name === '') {
		wp_send_json_error(['message' => 'Missing parameters.'], 400);
	}
	$data = ['name' => $name];
	$formats = ['%s'];

	// Opcionales: autosync settings si vienen en la petición
	if (isset($_POST['autosync'])) {
		$autosync = absint(wp_unslash($_POST['autosync'])) ? 1 : 0;
		$data['autosync'] = $autosync;
		$formats[] = '%d';
	}
	if (isset($_POST['autosync_mode'])) {
		$mode = sanitize_text_field(wp_unslash($_POST['autosync_mode']));
		if (! in_array($mode, ['updates', 'updates_and_new'], true)) {
			$mode = 'updates';
		}
		$data['autosync_mode'] = $mode;
		$formats[] = '%s';
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX update; internal table.
	$result = $wpdb->update(
		$wpdb->prefix . 'aichat_contexts',
		$data,
		['id' => $id],
		$formats,
		['%d']
	);
	if ($result !== false) {
		wp_send_json_success();
	} else {
		wp_send_json_error(['message' => 'Failed to update context name.']);
	}
}

// AJAX para eliminar contexto
add_action('wp_ajax_aichat_delete_context', 'aichat_delete_context');
function aichat_delete_context()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	global $wpdb;
	$id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
	if ($id <= 0) {
		wp_send_json_error(['message' => 'Missing id.'], 400);
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX delete; internal table.
	$result = $wpdb->delete($wpdb->prefix . 'aichat_contexts', ['id' => $id], ['%d']);
	if ($result !== false) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX update; internal table.
		$wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}aichat_chunks SET id_context = 0 WHERE id_context = %d", $id));
		wp_send_json_success();
	} else {
		wp_send_json_error(['message' => 'Failed to delete context.']);
	}
}

// AJAX para actualizar el progreso
add_action('wp_ajax_aichat_update_progress', 'aichat_update_progress');
function aichat_update_progress()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	$context_id = isset($_POST['context_id']) ? absint(wp_unslash($_POST['context_id'])) : 0;
	if ($context_id <= 0) {
		wp_send_json_error(['message' => 'Missing context_id'], 400);
	}
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX read; internal table.
	$context = $wpdb->get_row($wpdb->prepare("SELECT processing_progress FROM {$wpdb->prefix}aichat_contexts WHERE id = %d", $context_id), ARRAY_A);
	if ($context) {
		wp_send_json_success(['progress' => $context['processing_progress']]);
	} else {
		wp_send_json_error(['message' => 'Context not found']);
	}
}

// AJAX: búsqueda semántica de prueba dentro de un contexto
add_action('wp_ajax_aichat_search_context_chunks', 'aichat_search_context_chunks');
function aichat_search_context_chunks()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
	$context_id = isset($_POST['context_id']) ? absint(wp_unslash($_POST['context_id'])) : 0;
	$query = isset($_POST['q']) ? trim(sanitize_text_field(wp_unslash($_POST['q']))) : '';
	$limit = isset($_POST['limit']) ? max(1, min(20, absint(wp_unslash($_POST['limit'])))) : 10;
	if ($context_id <= 0) {
		wp_send_json_error(['message' => 'Missing context_id']);
	}
	if ($query === '') {
		wp_send_json_error(['message' => 'Empty query']);
	}
	// Generar embedding de la consulta
	$provider = aichat_get_context_embedding_provider($context_id);
	$q_embed = aichat_generate_embedding($query, 'query', $provider);
	if (! $q_embed) {
		wp_send_json_error(['message' => 'Embedding failed']);
	}
	global $wpdb;
	$table = $wpdb->prefix . 'aichat_chunks';
	$rows = $wpdb->get_results($wpdb->prepare("SELECT post_id, title, content, embedding, type FROM $table WHERE id_context=%d", $context_id), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX semantic test; internal table read.
	if (! $rows) {
		wp_send_json_success(['results' => []]);
	}
	$scored = [];
	foreach ($rows as $r) {
		$emb = json_decode($r['embedding'], true);
		if (!is_array($emb)) {
			continue;
		}
		$score = aichat_cosine_similarity($q_embed, $emb);
		$snippet = mb_substr(wp_strip_all_tags($r['content']), 0, 240);
		$scored[] = [
			'post_id' => (int) $r['post_id'],
			'title' => (string) $r['title'],
			'type' => isset($r['type']) ? (string) $r['type'] : '',
			'score' => round($score, 6),
			'excerpt' => $snippet . (strlen($r['content']) > 240 ? '…' : ''),
		];
	}
	usort($scored, function ($a, $b) {
		return ($b['score'] <=> $a['score']);
	});
	$scored = array_slice($scored, 0, $limit);
	wp_send_json_success(['results' => $scored, 'query' => $query]);
}

// AJAX: obtener metadatos del contexto (para panel de edición/test)
add_action('wp_ajax_aichat_get_context_meta', 'aichat_get_context_meta');
function aichat_get_context_meta()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
	$id = isset($_POST['id']) ? absint(wp_unslash($_POST['id'])) : 0;
	if ($id <= 0) {
		wp_send_json_error(['message' => 'Missing id']);
	}
	global $wpdb;
	$ctx_table = $wpdb->prefix . 'aichat_contexts';
	$chunks_table = $wpdb->prefix . 'aichat_chunks';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX read; internal table.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ctx_table is a trusted plugin table name.
			"SELECT id, name, context_type, remote_type, created_at, processing_status, processing_progress, autosync, autosync_mode, autosync_post_types FROM {$ctx_table} WHERE id=%d",
			$id
		),
		ARRAY_A
	);
	if (! $row) {
		wp_send_json_error(['message' => 'Not found']);
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX read; internal table.
	$chunk_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $chunks_table is a trusted plugin table name.
			"SELECT COUNT(*) FROM {$chunks_table} WHERE id_context=%d",
			$id
		)
	);
	// Conteo posts únicos
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX read; internal table.
	$post_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $chunks_table is a trusted plugin table name.
			"SELECT COUNT(DISTINCT post_id) FROM {$chunks_table} WHERE id_context=%d",
			$id
		)
	);
	$row['chunk_count'] = $chunk_count;
	$row['post_count'] = $post_count;
	wp_send_json_success(['context' => $row]);
}

// AJAX: Run AutoSync Now (manual trigger)
add_action('wp_ajax_aichat_autosync_run_now', 'aichat_autosync_run_now');
function aichat_autosync_run_now()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
	global $wpdb;
	$ctx_id = isset($_POST['context_id']) ? absint(wp_unslash($_POST['context_id'])) : 0;
	$mode_req = isset($_POST['mode']) ? sanitize_text_field(wp_unslash($_POST['mode'])) : 'modified';
	if ($ctx_id <= 0) {
		wp_send_json_error(['message' => 'Missing context_id']);
	}
	$table_ctx = $wpdb->prefix . 'aichat_contexts';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin AJAX read; internal table.
	$row = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table_ctx is a trusted plugin table name.
			"SELECT id, context_type, autosync, autosync_mode, autosync_post_types, items_to_process, processing_status FROM {$table_ctx} WHERE id=%d",
			$ctx_id
		),
		ARRAY_A
	);
	if (!$row) {
		wp_send_json_error(['message' => 'Context not found']);
	}
	if ($row['context_type'] !== 'local') {
		wp_send_json_error(['message' => 'Only local contexts supported']);
	}

	$types_csv = trim((string) $row['autosync_post_types']);
	$post_types = [];
	if ($types_csv !== '') {
		foreach (explode(',', $types_csv) as $t) {
			$t = trim($t);
			if ($t !== '') {
				$post_types[] = $t;
			}
		}
	}
	if (empty($post_types)) {
		$post_types = ['ALL_POSTS'];
	}
	$limited = ($types_csv === 'LIMITED');

	// Effective mode resolution
	$effective = 'modified';
	if ($mode_req === 'full') {
		$effective = 'full';
	} elseif ($mode_req === 'modified_and_new' && !$limited && $row['autosync_mode'] === 'updates_and_new') {
		$effective = 'modified_and_new';
	}

	$current_queue = maybe_unserialize($row['items_to_process']);
	if (!is_array($current_queue)) {
		$current_queue = [];
	}

	$modified = [];
	$new = [];
	$orphans = [];
	$full_ids = [];
	$added_ids = [];

	// Build actual WP post_types list for queries
	$wp_types = []; // map ALL_* tokens to real post_types
	foreach ($post_types as $tk) {
		switch ($tk) {
			case 'ALL_POSTS': $wp_types[] = 'post';
				break;
			case 'ALL_PAGES': $wp_types[] = 'page';
				break;
			case 'ALL_PRODUCTS': $wp_types[] = 'product';
				break;
			case 'ALL_UPLOADED': /* handled specially below for uploads converted to chunks already*/ break;
		}
	}
	if (empty($wp_types)) {
		$wp_types = ['post'];
	}
	// Construir lista de placeholders segura para post_type IN()
	$placeholders_types = implode(',', array_fill(0, count($wp_types), '%s'));

	// Queries similar to cron
	// Modified
	$modified_params = array_merge([$ctx_id], $wp_types);
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX read; internal tables.
	$modified = $wpdb->get_col($wpdb->prepare(
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders_types built from sanitized post types; executed via prepare.
		"SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->prefix}aichat_chunks c ON c.post_id=p.ID AND c.id_context=%d WHERE p.post_status='publish' AND p.post_type IN ($placeholders_types) GROUP BY p.ID HAVING TIMESTAMP(MAX(COALESCE(c.updated_at,c.created_at))) < TIMESTAMP(MAX(p.post_modified_gmt)) LIMIT 500",
		$modified_params
	));

	if ($effective === 'modified_and_new') {
		$new_params = array_merge([$ctx_id], $wp_types);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX read; internal tables.
		$new = $wpdb->get_col($wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders_types built from sanitized post types; executed via prepare.
			"SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->prefix}aichat_chunks c ON c.post_id=p.ID AND c.id_context=%d WHERE c.post_id IS NULL AND p.post_status='publish' AND p.post_type IN ($placeholders_types) ORDER BY p.ID DESC LIMIT 500",
			$new_params
		));
	}

	// Orphans
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX read; internal tables.
	$orphans = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT c.post_id
        FROM {$wpdb->prefix}aichat_chunks c
        LEFT JOIN {$wpdb->posts} p ON p.ID = c.post_id
        WHERE c.id_context=%d AND (p.ID IS NULL OR p.post_status <> 'publish')
        LIMIT 500", $ctx_id));

	if ($effective === 'full') {
		// FULL rebuild semantics:
		// - If context scope is LIMITED (no ALL_* tokens) we ONLY rebuild the existing indexed items (from chunks table).
		// - If scope includes ALL_* tokens, we re-scan full post lists for those types.
		$full_ids = [];
		if ($limited) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX read; internal table.
			$full_ids = $wpdb->get_col(
				$wpdb->prepare(
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal plugin table name.
					"SELECT DISTINCT post_id FROM {$wpdb->prefix}aichat_chunks WHERE id_context=%d",
					$ctx_id
				)
			);
		} else {
			foreach ($wp_types as $pt) {
				$ids = get_posts(['post_type' => $pt, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']);
				if ($ids) {
					$full_ids = array_merge($full_ids, $ids);
				}
			}
			// NOTE: ALL_UPLOADED omitted for now (uploaded chunks already expanded at creation time)
		}
		$full_ids = aichat_stable_unique_ids($full_ids);
	}

	// Build queue merge
	if ($effective === 'full') {
		$added_ids = $full_ids; // replace queue entirely
		$new_queue = $full_ids; // full rebuild
	} else {
		$merge_ids = array_merge($modified, $new);
		$new_queue = array_merge($current_queue, $merge_ids);
		$new_queue = aichat_stable_unique_ids($new_queue);
		$added_ids = array_diff($new_queue, $current_queue);
	}

	// Update context row
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX update; internal table.
	$wpdb->update($table_ctx, [
		'items_to_process' => maybe_serialize($new_queue),
		'processing_status' => 'pending',
		'processing_progress' => 0,
	], ['id' => $ctx_id]);

	// Reset cursor and total so aichat_process_context starts from the beginning
	if (function_exists('aichat_cursor_key')) {
		update_option(aichat_cursor_key($ctx_id), 0, false);
	}
	if (function_exists('aichat_total_items_key')) {
		update_option(aichat_total_items_key($ctx_id), count($new_queue), false);
	}

	// Delete orphan chunks
	$deleted_orphans = 0;
	if (!empty($orphans)) {
		$orph_placeholders = implode(',', array_fill(0, count($orphans), '%d'));
		$del_params = array_merge([$ctx_id], array_map('intval', $orphans));
		$delete_sql = "DELETE FROM {$wpdb->prefix}aichat_chunks WHERE id_context=%d AND post_id IN ($orph_placeholders)";
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orph_placeholders is generated from validated integers; internal table.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared immediately below via $wpdb->prepare with variadics; admin AJAX cleanup.
		$wpdb->query($wpdb->prepare($delete_sql, ...$del_params));
		$deleted_orphans = count($orphans);
	}

	wp_send_json_success([
		'context_id' => $ctx_id,
		'mode_requested' => $mode_req,
		'mode_effective' => $effective,
		'modified_count' => count($modified),
		'new_count' => count($new),
		'orphans_deleted' => $deleted_orphans,
		'queued_total' => count($new_queue),
		'added_to_queue' => count($added_ids),
	]);
}

// AJAX: Browse chunks (paginated) for local contexts only
add_action('wp_ajax_aichat_browse_context_chunks', 'aichat_browse_context_chunks');
function aichat_browse_context_chunks()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
	global $wpdb;
	$ctx_table = $wpdb->prefix . 'aichat_contexts';
	$chunks_table = $wpdb->prefix . 'aichat_chunks';
	$ctx_id = isset($_POST['context_id']) ? absint(wp_unslash($_POST['context_id'])) : 0;
	if ($ctx_id <= 0) {
		wp_send_json_error(['message' => 'Missing context_id']);
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled via $wpdb->prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX read; internal table.
	$context = $wpdb->get_row(
		$wpdb->prepare(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $ctx_table is a trusted plugin table name.
			"SELECT id, context_type FROM {$ctx_table} WHERE id=%d",
			$ctx_id
		),
		ARRAY_A
	);
	if (!$context) {
		wp_send_json_error(['message' => 'Context not found']);
	}
	if ($context['context_type'] !== 'local') {
		wp_send_json_error(['message' => 'Browse not available for remote contexts']);
	}

	$page = isset($_POST['page']) ? max(1, absint(wp_unslash($_POST['page']))) : 1;
	$per_page = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 25;
	if ($per_page <= 0) {
		$per_page = 25;
	} if ($per_page > 50) {
		$per_page = 50;
	}
	$offset = ($page - 1) * $per_page;
	$q = isset($_POST['q']) ? trim(sanitize_text_field(wp_unslash($_POST['q']))) : '';
	if (strlen($q) > 80) {
		$q = substr($q, 0, 80);
	}
	// Escape LIKE wildcards
	$q_like = $q !== '' ? '%' . $wpdb->esc_like($q) . '%' : '';
	$filter_type = isset($_POST['type']) ? sanitize_key(wp_unslash($_POST['type'])) : '';
	$allowed_types = ['post', 'page', 'product', 'upload'];
	if ($filter_type && !in_array($filter_type, $allowed_types, true)) {
		$filter_type = '';
	}

	$where_clauses = ['c.id_context=%d'];
	$where_params = [$ctx_id];
	if ($filter_type) {
		$where_clauses[] = 'c.type=%s';
		$where_params[] = $filter_type;
	}
	if ($q_like) {
		// Search in title or content (content truncated by LIKE may be heavy; add LIMIT already)
		$where_clauses[] = '(c.title LIKE %s OR c.content LIKE %s)';
		$where_params[] = $q_like;
		$where_params[] = $q_like;
	}
	$where_sql = implode(' AND ', $where_clauses);

	// Count total
	$count_sql = "SELECT COUNT(*) FROM $chunks_table c WHERE $where_sql";
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared immediately below; $where_sql uses only whitelisted fragments with placeholders; admin AJAX read.
	$total = (int) $wpdb->get_var($wpdb->prepare($count_sql, ...$where_params));
	if ($total === 0) {
		wp_send_json_success([
			'context_id' => $ctx_id,
			'rows' => [],
			'total' => 0,
			'total_pages' => 0,
			'page' => $page,
			'per_page' => $per_page,
		]);
	}

	// Fetch rows
	$sql = "SELECT c.post_id, c.type, c.title, c.updated_at, c.created_at, c.chunk_index, LENGTH(c.content) AS size, c.content
        FROM $chunks_table c
        WHERE $where_sql
        ORDER BY COALESCE(c.updated_at,c.created_at) DESC, c.id DESC
        LIMIT %d OFFSET %d";
	$rows_params = array_merge($where_params, [$per_page, $offset]);
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is plugin-controlled via $wpdb->prefix.
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately below; $where_sql uses only whitelisted fragments with placeholders.
	$prepared = $wpdb->prepare($sql, ...$rows_params);
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query is prepared above; admin AJAX read.
	$rows_raw = $wpdb->get_results($prepared, ARRAY_A);
	$rows = [];
	foreach ($rows_raw as $r) {
		$content_plain = wp_strip_all_tags($r['content']);
		$excerpt = mb_substr($content_plain, 0, 140);
		if (mb_strlen($content_plain) > 140) {
			$excerpt .= '…';
		}
		$rows[] = [
			'post_id' => (int) $r['post_id'],
			'type' => (string) $r['type'],
			'title' => (string) $r['title'],
			'updated_at' => $r['updated_at'] ?: $r['created_at'],
			'chunk_index' => (int) $r['chunk_index'],
			'size' => (int) $r['size'],
			'excerpt' => $excerpt,
		];
	}
	$total_pages = (int) ceil($total / $per_page);
	wp_send_json_success([
		'context_id' => $ctx_id,
		'rows' => $rows,
		'total' => $total,
		'total_pages' => $total_pages,
		'page' => $page,
		'per_page' => $per_page,
	]);
}

// =============================================================================
// AJAX: Get indexing options for a context
// =============================================================================
add_action('wp_ajax_aichat_get_indexing_options', 'aichat_ajax_get_indexing_options');
function aichat_ajax_get_indexing_options()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
	$context_id = isset($_POST['context_id']) ? absint(wp_unslash($_POST['context_id'])) : 0;
	if ($context_id <= 0) {
		wp_send_json_error(['message' => 'Missing context_id']);
	}
	$opts = function_exists('aichat_get_indexing_options')
		? aichat_get_indexing_options($context_id)
		: aichat_default_indexing_options();
	wp_send_json_success(['options' => $opts]);
}

// =============================================================================
// AJAX: Save indexing options for a context
// =============================================================================
add_action('wp_ajax_aichat_save_indexing_options', 'aichat_ajax_save_indexing_options');
function aichat_ajax_save_indexing_options()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
	$context_id = isset($_POST['context_id']) ? absint(wp_unslash($_POST['context_id'])) : 0;
	if ($context_id <= 0) {
		wp_send_json_error(['message' => 'Missing context_id']);
	}
	$raw = isset($_POST['indexing_options']) ? wp_unslash($_POST['indexing_options']) : '';
	$parsed = json_decode($raw, true);
	if (! is_array($parsed)) {
		wp_send_json_error(['message' => 'Invalid JSON']);
	}
	// Sanitize
	$clean = [
		'include_excerpt' => ! empty($parsed['include_excerpt']),
		'include_url' => ! empty($parsed['include_url']),
		'include_featured_image' => ! empty($parsed['include_featured_image']),
		'include_taxonomies' => isset($parsed['include_taxonomies']) && is_array($parsed['include_taxonomies'])
											? array_map('sanitize_key', $parsed['include_taxonomies']) : [],
		'include_wc_short_description' => ! empty($parsed['include_wc_short_description']),
		'include_wc_attributes' => ! empty($parsed['include_wc_attributes']),
		'include_meta_fields' => isset($parsed['include_meta_fields']) && is_array($parsed['include_meta_fields'])
											? array_map('sanitize_key', $parsed['include_meta_fields']) : [],
		'custom_meta_keys' => isset($parsed['custom_meta_keys']) && is_array($parsed['custom_meta_keys'])
											? array_map('sanitize_key', $parsed['custom_meta_keys']) : [],
	];
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin AJAX write; internal table.
	$ok = $wpdb->update(
		$wpdb->prefix . 'aichat_contexts',
		['indexing_options' => wp_json_encode($clean)],
		['id' => $context_id],
		['%s'],
		['%d']
	);
	if ($ok !== false) {
		wp_send_json_success(['options' => $clean]);
	} else {
		wp_send_json_error(['message' => 'DB update failed']);
	}
}
