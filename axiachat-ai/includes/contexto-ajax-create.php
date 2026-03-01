<?php

if (! defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// Incluir funciones necesarias
require_once plugin_dir_path(__FILE__) . 'contexto-functions.php';


// ===== Fallback helpers si no están cargados desde tus otros php =====
if (! function_exists('aichat_total_items_key')) {
	function aichat_total_items_key($id)
	{
		return 'aichat_total_items_' . (int) $id;
	}
}
if (! function_exists('aichat_cursor_key')) {
	function aichat_cursor_key($id)
	{
		return 'aichat_cursor_index_' . (int) $id;
	}
}
if (! function_exists('aichat_last_post_key')) {
	function aichat_last_post_key($id)
	{
		return 'aichat_last_post_id_' . (int) $id;
	}
}
if (! function_exists('aichat_lock_key')) {
	function aichat_lock_key($id)
	{
		return 'aichat_processing_' . (int) $id;
	}
}
if (! function_exists('aichat_lock_start_key')) {
	function aichat_lock_start_key($id)
	{
		return 'aichat_processing_start_' . (int) $id;
	}
}
if (! function_exists('aichat_stable_unique_ids')) {
	function aichat_stable_unique_ids($arr)
	{
		$out = [];
		$seen = [];
		if (!is_array($arr)) {
			return $out;
		}
		foreach ($arr as $v) {
			$id = (int) $v;
			if ($id <= 0) {
				continue;
			}
			$k = (string) $id;
			if (isset($seen[$k])) {
				continue;
			}
			$seen[$k] = true;
			$out[] = $id;
		}

		return $out;
	}
}

// =====================
// AJAX: crear/procesar
// =====================

/** Helper de logging con request-id corto */
function aichat_log($rid, $msg)
{
	aichat_log_debug("[AIChat AJAX $rid] $msg");
}

add_action('wp_ajax_aichat_process_context', 'aichat_process_context');

// Helpers para responder liberando lock
if (! function_exists('aichat_ajax_success')) {
	function aichat_ajax_success($rid, $data, $lock_key = null, $start_key = null)
	{
		if ($lock_key) {
			delete_option($lock_key);
			delete_option($start_key);
			aichat_log($rid, 'UNLOCK (success)');
		}
		wp_send_json_success($data);
	}
}
if (! function_exists('aichat_ajax_error')) {
	function aichat_ajax_error($rid, $data, $lock_key = null, $start_key = null)
	{
		if ($lock_key) {
			delete_option($lock_key);
			delete_option($start_key);
			aichat_log($rid, 'UNLOCK (error)');
		}
		wp_send_json_error($data);
	}
}

/**
 * Expande una lista de IDs seleccionados:
 * - Si el ID es de tipo aichat_upload (PADRE), lo convierte en la lista de IDs de sus aichat_upload_chunk (HIJOS, publish).
 * - Si no, deja el ID tal cual.
 */
function aichat_expand_selected_ids_to_chunks($ids)
{
	$out = [];
	foreach ((array) $ids as $sid) {
		$sid = (int) $sid;
		if ($sid <= 0) {
			continue;
		}
		$pt = get_post_type($sid);
		if ($pt === 'aichat_upload') {
			$chunks = get_posts([
				'post_type' => 'aichat_upload_chunk',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields' => 'ids',
				'meta_key' => '_aichat_upload_id',
				'meta_value' => $sid,
				'orderby' => 'meta_value_num',
				'order' => 'ASC',
			]);
			if (!empty($chunks)) {
				$out = array_merge($out, $chunks);
			}
		} else {
			$out[] = $sid;
		}
	}

	return $out;
}

/**
 * Devuelve todos los CHUNKS (ids) de todos los UPLOADS (padres).
 */
function aichat_all_uploaded_chunks_ids()
{
	$out = [];
	$uploads = get_posts([
		'post_type' => 'aichat_upload',
		'post_status' => 'private',
		'posts_per_page' => -1,
		'fields' => 'ids',
	]);
	foreach ($uploads as $up_id) {
		$chunks = get_posts([
			'post_type' => 'aichat_upload_chunk',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields' => 'ids',
			'meta_key' => '_aichat_upload_id',
			'meta_value' => $up_id,
			'orderby' => 'meta_value_num',
			'order' => 'ASC',
		]);
		if (!empty($chunks)) {
			$out = array_merge($out, $chunks);
		}
	}

	return $out;
}

function aichat_process_context()
{
	check_ajax_referer('aichat_nonce', 'nonce');
	global $wpdb;

	$t0 = microtime(true);
	$rid = function_exists('wp_generate_uuid4') ? substr(wp_generate_uuid4(), 0, 8) : substr(uniqid('', true), -8);

	$context_name = sanitize_text_field(wp_unslash($_POST['context_name'] ?? ''));
	$context_type = sanitize_text_field(wp_unslash($_POST['context_type'] ?? 'local'));
	$remote_type = sanitize_text_field(wp_unslash($_POST['remote_type'] ?? ''));
	$remote_api_key = sanitize_text_field(wp_unslash($_POST['remote_api_key'] ?? ''));
	$remote_endpoint = sanitize_text_field(wp_unslash($_POST['remote_endpoint'] ?? ''));
	$embedding_provider = sanitize_key(wp_unslash($_POST['embedding_provider'] ?? ''));
	$selected_items = isset($_POST['selected']) ? array_map('absint', (array) wp_unslash($_POST['selected'])) : [];
	$all_selected = isset($_POST['all_selected']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['all_selected'])) : [];
	$batch = isset($_POST['batch']) ? absint(wp_unslash($_POST['batch'])) : 0;
	// New autosync related fields
	$autosync_raw = sanitize_text_field(wp_unslash($_POST['autosync'] ?? ''));
	$autosync = in_array(strtolower($autosync_raw), ['1', 'true', 'on', 'yes'], true) ? 1 : 0;
	$autosync_mode = isset($_POST['autosync_mode']) ? sanitize_text_field(wp_unslash($_POST['autosync_mode'])) : 'updates';
	if (!in_array($autosync_mode, ['updates', 'updates_and_new'], true)) {
		$autosync_mode = 'updates';
	}

	// Advanced indexing options (JSON string from frontend)
	$indexing_options_raw = isset($_POST['indexing_options']) ? wp_unslash($_POST['indexing_options']) : '';
	$indexing_options_json = null;
	if ($indexing_options_raw !== '') {
		$parsed = json_decode($indexing_options_raw, true);
		if (is_array($parsed)) {
			// Sanitize each field
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
			$indexing_options_json = wp_json_encode($clean);
		}
	}

	// Normalize embedding provider
	if ($embedding_provider === 'anthropic') {
		$embedding_provider = 'claude';
	}
	if ($embedding_provider && ! in_array($embedding_provider, ['openai', 'gemini', 'claude'], true)) {
		$embedding_provider = '';
	}

	$AJAX_BATCH_SIZE = 10;

	$endpoint_key = rtrim(esc_url_raw($remote_endpoint), '/');
	$api_masked = $remote_api_key ? (substr($remote_api_key, 0, 4) . '***' . substr($remote_api_key, -4)) : '(empty)';

	aichat_log($rid, "START handler | name='$context_name' type=$context_type remote_type=$remote_type endpoint='$endpoint_key' api=$api_masked batch=$batch sel=" . count($selected_items) . ' all=' . count($all_selected));

	// 1) Buscar contexto – prefer explicit context_id when provided (e.g. from Training UI)
	$explicit_ctx_id = isset($_POST['context_id']) ? absint(wp_unslash($_POST['context_id'])) : 0;
	$existing = null;
	if ($explicit_ctx_id > 0) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$wpdb->prefix}aichat_contexts WHERE id = %d", $explicit_ctx_id),
			ARRAY_A
		);
	}
	if (! $existing) {
		// Fallback: lookup by endpoint + name (legacy / create pages)
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$wpdb->prefix}aichat_contexts WHERE remote_endpoint=%s AND name=%s", $endpoint_key, $context_name),
			ARRAY_A
		);
	}
	$context_id = $existing ? (int) $existing['id'] : 0;

	// 2) Crear / resetear lista (DEDUP) si toca
	if (! $existing || ($batch === 0 && ($selected_items || $all_selected))) {
		$items = [];

		// 2.a) Expandir selección "custom": padres aichat_upload -> hijos chunks
		if (!empty($selected_items)) {
			$expanded = aichat_expand_selected_ids_to_chunks($selected_items);
			$items = array_merge($items, $expanded);
		}

		// 2.b) Gestionar "ALL" por cada grupo
		foreach ($all_selected as $all_type) {

			if ($all_type === 'all_uploaded') {
				// Todos los chunks de todos los uploads (padres privados)
				$chunk_ids = aichat_all_uploaded_chunks_ids();
				if (!empty($chunk_ids)) {
					$items = array_merge($items, $chunk_ids);
				}
				continue;
			}

			$pt = ($all_type === 'all_posts') ? 'post'
				: (($all_type === 'all_pages') ? 'page'
				: (($all_type === 'all_products') ? 'product' : ''));

			if ($pt) {
				$ids = get_posts([
					'post_type' => $pt,
					'post_status' => 'publish',
					'posts_per_page' => -1,
					'fields' => 'ids',
				]);
				$items = array_merge($items, $ids);
			}
		}

		$before = count($items);
		$items_unique = aichat_stable_unique_ids($items);
		$after = count($items_unique);
		aichat_log($rid, "LIST build: before=$before after_dedup=$after");

		// Derive autosync_post_types string (store ALL_* even if autosync disabled to know origin scope)
		$all_keys = [];
		foreach ($all_selected as $all_type) {
			if (in_array($all_type, ['all_posts', 'all_pages', 'all_products', 'all_uploaded'], true)) {
				$all_keys[] = strtoupper($all_type); // e.g. ALL_PAGES
			}
		}
		if (!empty($all_keys)) {
			$autosync_post_types = implode(',', $all_keys);
		} else {
			$autosync_post_types = 'LIMITED';
			if ($autosync) {
				$autosync_mode = 'updates';
			}
		}
		aichat_log($rid, "AUTOSYNC scope: autosync=$autosync mode=$autosync_mode post_types='$autosync_post_types'");

		if (! $existing) {
			// Build insert data
			$insert_data = [
				'name' => $context_name,
				'context_type' => $context_type,
				'remote_type' => $remote_type,
				'remote_api_key' => $remote_api_key,
				'remote_endpoint' => $endpoint_key,
				'processing_status' => 'in_progress',
				'processing_progress' => 0,
				'items_to_process' => maybe_serialize($items_unique),
				'autosync' => $autosync,
				'autosync_mode' => $autosync_mode,
				'autosync_post_types' => $autosync_post_types,
			];
			// Add indexing_options if set
			if ($indexing_options_json !== null) {
				$insert_data['indexing_options'] = $indexing_options_json;
			}
			// Add embedding_provider if set
			if (! empty($embedding_provider)) {
				$insert_data['embedding_provider'] = $embedding_provider;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX handler writing internal contexts table.
			$result = $wpdb->insert($wpdb->prefix . 'aichat_contexts', $insert_data);
			if ($result === false) {
				aichat_log($rid, 'ERR INSERT failed: ' . $wpdb->last_error);
				wp_send_json_error(['message' => __('Database error creating context.', 'axiachat-ai')]);

				return;
			}
			$context_id = (int) $wpdb->insert_id;
			aichat_log($rid, "CONTEXT created id=$context_id embedding_provider=$embedding_provider");
		} else {
			$update_data = [
				'context_type' => $context_type,
				'remote_type' => $remote_type,
				'remote_api_key' => $remote_api_key,
				'remote_endpoint' => $endpoint_key,
				'processing_status' => 'in_progress',
				'processing_progress' => 0,
				'items_to_process' => maybe_serialize($items_unique),
			];
			// Only override autosync config on reset if explicitly passed in first batch (batch===0)
			if ($batch === 0) {
				$update_data['autosync'] = $autosync;
				$update_data['autosync_mode'] = $autosync_mode;
				$update_data['autosync_post_types'] = $autosync_post_types;
				// Update embedding provider on first batch
				if (! empty($embedding_provider)) {
					$update_data['embedding_provider'] = $embedding_provider;
				}
				// Update indexing options on first batch
				if ($indexing_options_json !== null) {
					$update_data['indexing_options'] = $indexing_options_json;
				}
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX handler writing internal contexts table.
			$wpdb->update($wpdb->prefix . 'aichat_contexts', $update_data, ['id' => $context_id]);
			aichat_log($rid, "CONTEXT reset id=$context_id embedding_provider=$embedding_provider");
		}
		update_option(aichat_total_items_key($context_id), $after, false);
		update_option(aichat_cursor_key($context_id), 0, false);
	}

	// 3) Lock atómico (evitar solapes)
	$lock_key = aichat_lock_key($context_id);
	$start_key = aichat_lock_start_key($context_id);
	$now = time();
	$got = add_option($lock_key, $now, '', 'no');
	if (!$got) {
		$started = (int) get_option($lock_key, 0);
		$elapsed = $now - $started;
		if ($started && $elapsed <= 300) {
			// NO tenemos el lock: devolvemos estado SIN liberar (no nos pertenece)
			$total = (int) get_option(aichat_total_items_key($context_id), 0);
			$cursor = (int) get_option(aichat_cursor_key($context_id), 0);
			$progress = $total > 0 ? (int) floor($cursor * 100 / $total) : 100;
			aichat_log($rid, "LOCK busy ($elapsed s) | ctx=$context_id cursor=$cursor total=$total progress=$progress%");
			wp_send_json_success([
				'context_id' => $context_id,
				'total_processed' => 0,
				'total_tokens' => 0,
				'progress' => $progress,
				'batch' => $batch,
				'continue' => ($cursor < $total),
				'total' => $total,
				'message' => 'Otro proceso en curso…',
			]);
		}
		update_option($lock_key, $now, false);
		aichat_log($rid, 'LOCK stale renewed');
	}
	update_option($start_key, $now, false);
	aichat_log($rid, "LOCK acquired | ctx=$context_id");

	// 4) Trabajo protegido por lock
	try {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- AJAX handler reading internal contexts table.
		$context = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM {$wpdb->prefix}aichat_contexts WHERE id=%d", $context_id),
			ARRAY_A
		);
		if (!$context) {
			aichat_log($rid, "ERR context not found id=$context_id");
			aichat_ajax_error($rid, ['message' => 'Contexto no encontrado'], $lock_key, $start_key);
		}

		$items = maybe_unserialize($context['items_to_process']);
		$items = is_array($items) ? aichat_stable_unique_ids($items) : [];
		$total = (int) get_option(aichat_total_items_key($context_id), 0);
		if ($total !== count($items)) {
			$total = count($items);
			update_option(aichat_total_items_key($context_id), $total, false);
			aichat_log($rid, "TOTAL adjusted to $total");
		}
		$cursor_key = aichat_cursor_key($context_id);
		$cursor = (int) get_option($cursor_key, 0);
		aichat_log($rid, "STATE | total=$total cursor=$cursor status={$context['processing_status']}");

		if ($total === 0) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update internal contexts table.
			$wpdb->update($wpdb->prefix . 'aichat_contexts', ['processing_status' => 'completed', 'processing_progress' => 100], ['id' => $context_id]);
			aichat_log($rid, 'EMPTY list → completed');
			aichat_ajax_success($rid, [
				'context_id' => $context_id, 'total_processed' => 0, 'total_tokens' => 0,
				'progress' => 100, 'batch' => $batch, 'continue' => false, 'total' => 0, 'message' => 'Sin elementos',
			], $lock_key, $start_key);
		}
		if ($cursor >= $total) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update internal contexts table.
			$wpdb->update($wpdb->prefix . 'aichat_contexts', ['processing_status' => 'completed', 'processing_progress' => 100], ['id' => $context_id]);
			aichat_log($rid, 'ALREADY completed (cursor>=total)');
			aichat_ajax_success($rid, [
				'context_id' => $context_id, 'total_processed' => 0, 'total_tokens' => 0,
				'progress' => 100, 'batch' => $batch, 'continue' => false, 'total' => $total, 'message' => 'Completado',
			], $lock_key, $start_key);
		}

		// 5) Procesar batch
		$batch_items = array_slice($items, $cursor, $AJAX_BATCH_SIZE);
		$attempted = count($batch_items);
		aichat_log($rid, "BATCH begin | offset=$cursor size=$attempted ids=" . implode(',', array_slice($batch_items, 0, 10)));

		$processed = 0;
		$tokens_sum = 0;
		$last_post_id = null;

		foreach ($batch_items as $post_id) {
			$per0 = microtime(true);
			$last_post_id = $post_id;
			$post = get_post($post_id);
			if (!$post || $post->post_status !== 'publish') {
				aichat_log($rid, "POST $post_id skipped (invalid/unpublished)");
				continue;
			}

			if ($context['context_type'] === 'remoto' && $context['remote_type'] === 'pinecone') {
				// Remoto (Pinecone)
				$text = function_exists('aichat_build_index_text') ? aichat_build_index_text($post, $context_id) : wp_strip_all_tags($post->post_title . "\n" . $post->post_content);
				$title = $post->post_title;
				$emb0 = microtime(true);
				$provider = aichat_get_context_embedding_provider($context_id);
				$embedding = aichat_generate_embedding($text, 'document', $provider);
				$emb1 = microtime(true);
				if (!is_array($embedding) || empty($embedding)) {
					aichat_log($rid, "POST $post_id embedding FAILED (dt=" . number_format(($emb1 - $emb0), 3) . 's)');
					continue;
				}
				$dim = count($embedding);
				$payload = [
					'vectors' => [[
						'id' => (string) $post_id,
						'values' => array_values($embedding),
						'metadata' => [
							'post_id' => (int) $post_id,
							'title' => $title,
							'content' => mb_substr($text, 0, 30000),
							'type' => $post->post_type,
							'context_id' => (int) $context_id,
						],
					]],
					'namespace' => 'aichat_context_' . $context_id,
				];
				$http0 = microtime(true);
				$resp = wp_remote_post(
					rtrim($context['remote_endpoint'], '/') . '/vectors/upsert',
					['headers' => ['Api-Key' => $context['remote_api_key'], 'Content-Type' => 'application/json'],
					 'body' => wp_json_encode($payload), 'timeout' => 30]
				);
				$http1 = microtime(true);
				if (is_wp_error($resp)) {
					aichat_log($rid, "POST $post_id pinecone HTTP ERROR: " . $resp->get_error_message() . " (emb_dim=$dim, dt=" . number_format(($http1 - $http0), 3) . 's)');
				} else {
					$code = (int) wp_remote_retrieve_response_code($resp);
					$body = json_decode(wp_remote_retrieve_body($resp), true);
					$upc = isset($body['upsertedCount']) ? (int) $body['upsertedCount'] : -1;
					if ($code >= 200 && $code < 300 && $upc >= 1) {
						$processed++;
						$tk = str_word_count($text);
						$tokens_sum += $tk;
						aichat_log($rid, "POST $post_id OK (emb_dim=$dim, upserted=$upc, http=$code, toks~$tk, dt_total=" . number_format((microtime(true) - $per0), 3) . 's)');
					} else {
						$snippet = substr(wp_remote_retrieve_body($resp), 0, 180);
						aichat_log($rid, "POST $post_id pinecone FAIL (code=$code, upserted=$upc, body~'$snippet', dt=" . number_format(($http1 - $http0), 3) . 's)');
					}
				}
			} else {
				// Local
				$provider = aichat_get_context_embedding_provider($context_id);
				$ok = function_exists('aichat_index_post') ? aichat_index_post($post_id, $context_id, $provider) : false;
				if ($ok) {
					$processed++;
					$tk = str_word_count($post->post_content);
					$tokens_sum += $tk;
					aichat_log($rid, "POST $post_id local OK (toks~$tk, dt=" . number_format((microtime(true) - $per0), 3) . 's)');
				} else {
					aichat_log($rid, "POST $post_id local FAIL (func missing or error)");
				}
			}
		}

		// 6) Avanzar cursor y progreso
		$new_cursor = min($cursor + $attempted, $total);
		update_option($cursor_key, $new_cursor, false);
		if ($last_post_id) {
			update_option(aichat_last_post_key($context_id), (int) $last_post_id, false);
		}

		$progress = (int) floor($new_cursor * 100 / max(1, $total));
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update internal contexts table.
		$wpdb->update(
			$wpdb->prefix . 'aichat_contexts',
			['processing_progress' => $progress, 'processing_status' => ($progress >= 100 ? 'completed' : 'in_progress')],
			['id' => $context_id]
		);

		$continue = ($new_cursor < $total);
		$next_batch = $batch + 1;

		aichat_log(
			$rid,
			'BATCH end | processed=' . $processed .
			' tokens=' . $tokens_sum .
			' new_cursor=' . $new_cursor .
			' progress=' . $progress . '%' .
			' continue=' . ($continue ? 'yes' : 'no') .
			' dt=' . number_format((microtime(true) - $t0), 3) . 's'
		);

		// 7) RESPUESTA
		aichat_ajax_success($rid, [
			'context_id' => $context_id,
			'total_processed' => $processed,
			'total_tokens' => $tokens_sum,
			'progress' => $progress,
			'batch' => $next_batch,
			'continue' => $continue,
			'total' => $total,
			'message' => $continue ? 'Procesando…' : 'Completado.',
		], $lock_key, $start_key);

	} catch (Throwable $e) {
		aichat_log($rid, 'ERROR exception: ' . $e->getMessage());
		aichat_ajax_error($rid, ['message' => 'Error AJAX: ' . $e->getMessage()], $lock_key, $start_key);
	}

	// No finally: ya liberamos lock en success/error
}


// =====================
// AJAX: cargar items
// =====================
add_action('wp_ajax_aichat_load_items', 'aichat_load_items');
function aichat_load_items()
{
	check_ajax_referer('aichat_nonce', 'nonce');

	$pt = sanitize_text_field(wp_unslash($_POST['post_type'] ?? ''));
	$tab = sanitize_text_field(wp_unslash($_POST['tab'] ?? 'recent'));
	$search = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));
	$paged = isset($_POST['paged']) ? absint(wp_unslash($_POST['paged'])) : 1;

	// aichat_upload (PADRES) son privados; el resto publish
	$status = ($pt === 'aichat_upload') ? 'private' : 'publish';

	$args = [
		'post_type' => $pt,
		'post_status' => $status,
		'posts_per_page' => 5,
		'orderby' => 'date',
		'order' => 'DESC',
		'fields' => 'ids',
	];

	if ($tab === 'recent') {
		$args['posts_per_page'] = 5;
		$args['orderby'] = 'date';
		$args['order'] = 'DESC';
	} elseif ($tab === 'all' || $tab === 'search') {
		$args['posts_per_page'] = 20;
		$args['paged'] = $paged;
	}

	if ($tab === 'search' && strlen($search)) {
		// Búsqueda básica por título/contenido
		$args['s'] = $search;
	}

	$ids = get_posts($args);

	// Total para paginación
	$counts = wp_count_posts($pt);
	if ($pt === 'aichat_upload') {
		$total_posts = isset($counts->private) ? (int) $counts->private : 0;
	} else {
		$total_posts = isset($counts->publish) ? (int) $counts->publish : 0;
	}
	$max_pages = ($tab === 'all' || $tab === 'search') ? max(1, (int) ceil($total_posts / 20)) : 1;

	// Render
	$html = '';
	if (!empty($ids)) {
		foreach ($ids as $post_id) {
			$label = get_the_title($post_id);
			if ($pt === 'aichat_upload') {
				// Mostrar nombre de fichero si existe
				$fn = get_post_meta($post_id, '_aichat_filename', true);
				if ($fn) {
					$label = $fn;
				}
			}
			$html .= '<label><input type="checkbox" value="' . esc_attr($post_id) . '" /> ' . esc_html($label) . '</label><br>';
		}
	}
	if (empty($html)) {
		$html = '<p>No items found.</p>';
	}

	wp_send_json_success([
		'html' => $html,
		'max_pages' => $max_pages,
		'current_page' => $paged,
	]);
}
