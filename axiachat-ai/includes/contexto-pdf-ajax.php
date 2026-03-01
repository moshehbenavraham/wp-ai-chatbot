<?php

/**
 * AJAX handlers for Import PDF/Data
 */
if (! defined('ABSPATH')) {
	exit;
}

// ==============================
// CPTs (ligeros, privados)
// ==============================
add_action('init', function () {
	// Padre: un post por fichero subido
	if (! post_type_exists('aichat_upload')) {
		register_post_type('aichat_upload', [
			'labels' => [
				'name' => 'AIChat Uploads',
				'singular_name' => 'AIChat Upload',
			],
			'public' => false,
			'show_ui' => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'supports' => ['title'],
		]);
	}
	// Hijo: un post por chunk de texto
	if (! post_type_exists('aichat_upload_chunk')) {
		register_post_type('aichat_upload_chunk', [
			'labels' => [
				'name' => 'AIChat Upload Chunks',
				'singular_name' => 'AIChat Upload Chunk',
			],
			'public' => false,
			'show_ui' => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'exclude_from_search' => true,
			'publicly_queryable' => false,
			'supports' => ['title', 'editor'],
		]);
	}
});

// ==============================
// Helpers
// ==============================
function aichat_pdf_log($m)
{
	aichat_log_debug('[AIChat PDF] ' . $m);
}

function aichat_upload_dir()
{
	$up = wp_upload_dir();
	$base = trailingslashit($up['basedir']) . 'aichat_uploads';
	if (! file_exists($base)) {
		wp_mkdir_p($base);
	}

	return $base;
}

function aichat_bytes($v)
{
	$n = is_numeric($v) ? (int) $v : 0;

	return max(0, $n);
}

function aichat_is_pdf($mime, $name)
{
	$mime = strtolower((string) $mime);
	$ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

	return ($mime === 'application/pdf') || ($ext === 'pdf');
}

function aichat_is_txt($mime, $name)
{
	$mime = strtolower((string) $mime);
	$ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));

	return ($mime === 'text/plain') || ($ext === 'txt');
}

/**
 * Sanitize text for chunking: ensure valid UTF-8 and remove problematic characters
 */
function aichat_sanitize_text_for_chunking($text)
{
	// Ensure we have a string
	if (! is_string($text)) {
		return '';
	}

	$original = $text;

	// Convert to UTF-8 if needed
	if (! mb_check_encoding($text, 'UTF-8')) {
		$text = mb_convert_encoding($text, 'UTF-8', 'auto');
	}

	// Remove invalid UTF-8 sequences
	$text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

	// Remove NULL bytes and other control characters (except newlines/tabs)
	$cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
	if ($cleaned !== null) {
		$text = $cleaned;
	} else {
		// preg_replace failed, manual cleanup
		$text = str_replace(chr(0), '', $original);
	}

	// Remove BOM if present
	$cleaned = preg_replace('/^\xEF\xBB\xBF/', '', $text);
	if ($cleaned !== null) {
		$text = $cleaned;
	}

	// Normalize various space characters to regular space
	$cleaned = preg_replace('/[\x{00A0}\x{2000}-\x{200B}\x{202F}\x{205F}\x{3000}]/u', ' ', $text);
	if ($cleaned !== null) {
		$text = $cleaned;
	} else {
		// Fallback if unicode regex fails
		$text = str_replace("\xC2\xA0", ' ', $text); // NBSP
	}

	return $text;
}

/**
 * Chunking básico por palabras (~900-1200 palabras, solape ~180)
 */
function aichat_chunk_text($text, $target_words = 1000, $overlap = 180)
{
	// Ensure text is a string
	if (! is_string($text)) {
		aichat_pdf_log('chunk_text: invalid input type: ' . gettype($text));

		return [];
	}

	// Sanitize text: ensure valid UTF-8 and remove problematic characters
	$text = aichat_sanitize_text_for_chunking($text);

	$cleaned = preg_replace("/\r\n|\r/", "\n", $text);
	$text = trim($cleaned !== null ? $cleaned : $text);

	// Handle empty text
	if (empty($text)) {
		aichat_pdf_log('chunk_text: empty text after trim');

		return [];
	}

	// Use simpler regex or fallback immediately
	$words = preg_split('/[\s\x{00A0}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

	// preg_split can return false on error
	if ($words === false) {
		aichat_pdf_log('chunk_text: preg_split failed, using fallback');
		// Fallback: normalize whitespace and use explode
		$normalized = preg_replace('/\s+/', ' ', $text);
		if ($normalized === null) {
			$normalized = str_replace(["\n", "\t", "\r"], ' ', $text);
		}
		$words = array_filter(explode(' ', $normalized), 'strlen');
	}

	$chunks = [];
	$n = count($words);
	if ($n === 0) {
		return [];
	}

	$i = 0;
	$idx = 0;
	while ($i < $n) {
		$end = min($n, $i + $target_words);
		$slice = array_slice($words, $i, $end - $i);
		$chunk = trim(implode(' ', $slice));
		if ($chunk !== '') {
			$chunks[] = ['index' => $idx++, 'text' => $chunk];
		}
		if ($end >= $n) {
			break;
		}
		$i = max($end - $overlap, $i + 1);
	}

	return $chunks;
}

/**
 * Extrae texto de TXT
 */
function aichat_extract_txt($path)
{
	$txt = @file_get_contents($path);
	if ($txt === false) {
		return new WP_Error('read_txt', 'Could not read TXT file');
	}
	// Normaliza UTF-8 (wp_is_valid_utf8 desde WP 6.9, fallback a mb_check_encoding)
	$is_utf8 = function_exists('wp_is_valid_utf8') ? wp_is_valid_utf8($txt) : mb_check_encoding($txt, 'UTF-8');
	if (! $is_utf8) {
		$txt = mb_convert_encoding($txt, 'UTF-8', 'auto');
	}
	// Limpia control chars
	$txt = preg_replace('/[^\P{C}\t\n]+/u', '', $txt);

	return trim($txt);
}

/**
 * Usa pdftotext si existe
 */
function aichat_pdftotext_available()
{
	if (! function_exists('shell_exec')) {
		return false;
	}
	$out = @shell_exec('command -v pdftotext 2>/dev/null');
	if (is_string($out) && strlen(trim($out)) > 0) {
		return true;
	}
	// Windows
	$out = @shell_exec('where pdftotext 2>NUL');

	return (is_string($out) && strlen(trim($out)) > 0);
}

function aichat_extract_pdf_with_pdftotext($path)
{
	if (! aichat_pdftotext_available()) {
		return new WP_Error('no_pdftotext', 'pdftotext not available');
	}
	$cmd = 'pdftotext -enc UTF-8 -q ' . escapeshellarg($path) . ' -';
	$out = @shell_exec($cmd);
	if (! is_string($out) || strlen($out) === 0) {
		return new WP_Error('pdftotext_empty', 'pdftotext returned no output');
	}

	return trim($out);
}

/**
 * Fallback PHP vía Smalot\PdfParser si está disponible
 * (recomendado: instalar con composer o incluir la lib)
 */
function aichat_extract_pdf_with_smalot($path)
{
	if (! class_exists('\Smalot\PdfParser\Parser')) {
		return new WP_Error('no_smalot', 'Smalot\\PdfParser not available');
	}
	try {
		$parser = new \Smalot\PdfParser\Parser();
		$pdf = $parser->parseFile($path);
		$text = $pdf->getText();
		if (! is_string($text) || strlen(trim($text)) === 0) {
			return new WP_Error('smalot_empty', 'Smalot returned empty text');
		}

		return trim($text);
	} catch (\Throwable $e) {
		return new WP_Error('smalot_exception', 'Smalot exception: ' . $e->getMessage());
	}
}

/**
 * Extractor unificado: TXT / PDF
 * - PDF: pdftotext -> Smalot -> AI Vision (fallback) -> error
 */
function aichat_extract_text($path, $mime, $name)
{
	if (aichat_is_txt($mime, $name)) {
		return aichat_extract_txt($path);
	}
	if (aichat_is_pdf($mime, $name)) {
		// Intenta pdftotext
		$t0 = microtime(true);
		$r = aichat_extract_pdf_with_pdftotext($path);
		if (! is_wp_error($r) && strlen(trim($r)) > 50) {
			aichat_pdf_log('pdftotext OK (' . number_format(microtime(true) - $t0, 3) . 's) - ' . strlen($r) . ' chars');

			return $r;
		}
		aichat_pdf_log('pdftotext failed or empty, trying Smalot...');

		// Fallback Smalot
		$t1 = microtime(true);
		$r2 = aichat_extract_pdf_with_smalot($path);
		if (! is_wp_error($r2) && strlen(trim($r2)) > 50) {
			aichat_pdf_log('Smalot OK (' . number_format(microtime(true) - $t1, 3) . 's) - ' . strlen($r2) . ' chars');

			return $r2;
		}
		aichat_pdf_log('Smalot failed or empty, trying AI Vision fallback...');

		// Fallback AI Vision (for scanned PDFs or when other parsers fail)
		if (function_exists('aichat_pdf_to_text_via_ai')) {
			// Determine which AI provider has a key
			$vision_provider = '';
			if (aichat_get_setting('aichat_openai_api_key')) {
				$vision_provider = 'openai';
			} elseif (aichat_get_setting('aichat_gemini_api_key')) {
				$vision_provider = 'gemini';
			} elseif (aichat_get_setting('aichat_claude_api_key')) {
				$vision_provider = 'claude';
			}

			if ($vision_provider) {
				$t2 = microtime(true);
				aichat_pdf_log("Trying AI Vision with provider: $vision_provider");
				$vision_result = aichat_pdf_to_text_via_ai($path, $vision_provider, ['max_pages' => 5]);
				if (! is_wp_error($vision_result) && ! empty($vision_result['text'])) {
					aichat_pdf_log('AI Vision OK (' . number_format(microtime(true) - $t2, 3) . 's) - ' . strlen($vision_result['text']) . ' chars');

					return $vision_result['text'];
				}
				if (is_wp_error($vision_result)) {
					aichat_pdf_log('AI Vision failed: ' . $vision_result->get_error_message());
				}
			} else {
				aichat_pdf_log('AI Vision: no API key configured for any provider');
			}
		}

		// Sin parser disponible
		return new WP_Error(
			'no_pdf_parser',
			'Could not extract text. Ensure pdftotext, Smalot\\PdfParser, or AI Vision is available.'
		);
	}

	return new WP_Error('unsupported', 'Unsupported file type. Only PDF or TXT are allowed.');
}

/**
 * Crea posts chunk a partir de texto; devuelve IDs
 */
function aichat_create_chunks_posts($upload_post_id, $filename, $text)
{
	aichat_pdf_log("Creating chunks for '$filename': text length = " . strlen($text) . ' chars');

	$chunks = aichat_chunk_text($text, apply_filters('aichat_chunk_words', 1000), apply_filters('aichat_chunk_overlap', 180));
	aichat_pdf_log('Chunking result: ' . count($chunks) . ' chunks generated');

	$ids = [];
	$total = count($chunks);
	foreach ($chunks as $c) {
		$title = sprintf('%s (chunk %d/%d)', $filename, $c['index'] + 1, $total);

		// Sanitize content for wp_insert_post
		$chunk_content = wp_kses_post($c['text']);
		if (empty(trim($chunk_content))) {
			// If kses removes everything, use sanitized plain text
			$chunk_content = sanitize_textarea_field($c['text']);
		}

		// Use wp_insert_post with true to get WP_Error on failure
		$post_id = wp_insert_post([
			'post_type' => 'aichat_upload_chunk',
			'post_status' => 'publish',
			'post_title' => sanitize_text_field($title),
			'post_content' => $chunk_content,
		], true);

		if ($post_id && ! is_wp_error($post_id)) {
			add_post_meta($post_id, '_aichat_upload_id', (int) $upload_post_id, true);
			add_post_meta($post_id, '_aichat_chunk_index', (int) $c['index'], true);
			add_post_meta($post_id, '_aichat_tokens', str_word_count($c['text']), true);
			$ids[] = (int) $post_id;
		} else {
			$error_msg = is_wp_error($post_id) ? $post_id->get_error_message() : 'wp_insert_post returned 0';
			aichat_pdf_log("Failed to create chunk post #{$c['index']}: $error_msg");
			aichat_pdf_log('Chunk content length: ' . strlen($chunk_content) . ", title: $title");
		}
	}

	return $ids;
}

/**
 * Realiza el parse de un upload: extrae texto y crea chunks
 * @param int $upload_id ID del post aichat_upload
 * @param bool $force Si es true, borra chunks existentes y re-parsea
 * @return array|WP_Error Array con chunks_created y chunk_ids, o WP_Error si falla
 */
function aichat_do_parse_upload($upload_id, $force = false)
{
	$p = get_post($upload_id);
	if (! $p || $p->post_type !== 'aichat_upload') {
		return new WP_Error('not_found', 'Upload not found');
	}

	$filename = get_post_meta($upload_id, '_aichat_filename', true);
	$mime = get_post_meta($upload_id, '_aichat_mime', true);
	$path = get_post_meta($upload_id, '_aichat_path', true);

	if (! file_exists($path)) {
		return new WP_Error('file_not_found', 'Stored file not found on disk');
	}

	// Si ya tiene chunks y no es force, devolver existentes
	$existing = get_posts([
		'post_type' => 'aichat_upload_chunk',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields' => 'ids',
		'meta_key' => '_aichat_upload_id',
		'meta_value' => $upload_id,
	]);

	if ($existing && ! $force) {
		return [
			'chunks_created' => count($existing),
			'chunk_ids' => array_map('intval', $existing),
			'message' => 'Already chunked',
		];
	}

	// Si force, borrar chunks anteriores
	if ($force && $existing) {
		foreach ($existing as $cid) {
			wp_delete_post($cid, true);
		}
		update_post_meta($upload_id, '_aichat_chunk_count', 0);
	}

	// Extraer texto
	$t0 = microtime(true);
	$res = aichat_extract_text($path, $mime, $filename);
	if (is_wp_error($res)) {
		aichat_pdf_log('Parse error: ' . $res->get_error_message());

		return $res;
	}
	$text = $res;

	// Crear chunks (posts)
	$chunk_ids = aichat_create_chunks_posts($upload_id, $filename, $text);
	update_post_meta($upload_id, '_aichat_status', 'chunked');
	update_post_meta($upload_id, '_aichat_chunk_count', count($chunk_ids));

	aichat_pdf_log("Parsed '$filename' -> " . count($chunk_ids) . ' chunks (' . number_format(microtime(true) - $t0, 3) . 's)');

	return [
		'chunks_created' => count($chunk_ids),
		'chunk_ids' => $chunk_ids,
	];
}

/**
 * Convierte un post "upload" a array serializable para el listado
 */
function aichat_upload_to_row($p)
{
	$id = (int) $p->ID;
	$filename = (string) get_post_meta($id, '_aichat_filename', true);
	$mime = (string) get_post_meta($id, '_aichat_mime', true);
	$size = aichat_bytes(get_post_meta($id, '_aichat_size', true));
	$status = (string) get_post_meta($id, '_aichat_status', true);
	$chunks = (int) get_post_meta($id, '_aichat_chunk_count', true);
	$updated = (string) get_post_modified_time('Y-m-d H:i:s', true, $id);

	return [
		'id' => $id,
		'filename' => $filename,
		'mime' => $mime,
		'size' => $size,        // BYTES → el JS ya lo convierte a KB/MB
		'status' => $status ?: 'uploaded',
		'chunk_count' => $chunks,
		'updated_at' => $updated,
	];
}

// ==============================
// AJAX: subir archivo
// ==============================
add_action('wp_ajax_aichat_upload_file', function () {
	check_ajax_referer('aichat_pdf_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES handled via server-side validation and sanitize_file_name().
	if (empty($_FILES['file'])) {
		wp_send_json_error(['message' => 'No file'], 400);
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_FILES handled via server-side validation and sanitize_file_name().
	$f = $_FILES['file'];
	if ($f['error'] !== UPLOAD_ERR_OK) {
		wp_send_json_error(['message' => 'Upload error code ' . $f['error']], 400);
	}

	$name = sanitize_file_name($f['name']);
	$tmp = $f['tmp_name'];
	// Prefer server-side detection
	$filetype = wp_check_filetype_and_ext($tmp, $name);
	$mime = $filetype['type'] ?: ($f['type'] ?: mime_content_type($tmp));
	$size = (int) $f['size'];

	// Validation: extension & MIME pair whitelist
	$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
	$allowed_ext = ['pdf', 'txt'];
	if (! in_array($ext, $allowed_ext, true)) {
		wp_send_json_error(['message' => 'Only .pdf or .txt allowed'], 400);
	}
	$allowed_mime = ['application/pdf', 'text/plain', 'text/markdown', 'application/octet-stream']; // octet-stream fallback for some txt uploads
	if ($ext === 'pdf' && $mime !== 'application/pdf') {
		wp_send_json_error(['message' => 'Invalid PDF MIME.'], 400);
	}
	if ($ext === 'txt' && ! in_array($mime, $allowed_mime, true)) {
		wp_send_json_error(['message' => 'Invalid TXT MIME.'], 400);
	}
	// Size cap (10MB default)
	$max_bytes = apply_filters('aichat_pdf_max_bytes', 10 * 1024 * 1024);
	if ($size > $max_bytes) {
		wp_send_json_error(['message' => 'File too large.'], 400);
	}

	// Move a carpeta propia
	$dir = aichat_upload_dir();
	$data = @file_get_contents($tmp);
	if ($data === false) {
		wp_send_json_error(['message' => 'Could not read uploaded file'], 400);
	}
	$sha = hash('sha256', $data);
	$safeBase = $sha . '.' . $ext;
	$dest = trailingslashit($dir) . $safeBase;
	if (! @file_put_contents($dest, $data)) {
		wp_send_json_error(['message' => 'Could not store uploaded file'], 500);
	}

	// Crea post "upload"
	$pid = wp_insert_post([
		'post_type' => 'aichat_upload',
		'post_status' => 'private',
		'post_title' => $name,
	]);
	if (! $pid || is_wp_error($pid)) {
		// Limpia el archivo físico usando API WP segura
		if (file_exists($dest)) {
			wp_delete_file($dest);
		}
		wp_send_json_error(['message' => 'Could not create upload post'], 500);
	}

	add_post_meta($pid, '_aichat_filename', $name, true);
	add_post_meta($pid, '_aichat_mime', $mime, true);
	add_post_meta($pid, '_aichat_size', $size, true);      // BYTES
	add_post_meta($pid, '_aichat_path', $dest, true);
	add_post_meta($pid, '_aichat_sha256', $sha, true);
	add_post_meta($pid, '_aichat_status', 'uploaded', true);
	add_post_meta($pid, '_aichat_chunk_count', 0, true);

	aichat_pdf_log("Uploaded '{$name}' ({$size} bytes) pid=$pid");

	// Auto-parse: extract text and create chunks immediately
	$parse_result = aichat_do_parse_upload($pid, false);
	if (is_wp_error($parse_result)) {
		// Upload succeeded but parse failed - return upload_id with warning
		aichat_pdf_log("Auto-parse failed for pid=$pid: " . $parse_result->get_error_message());
		wp_send_json_success([
			'upload_id' => (int) $pid,
			'parse_error' => $parse_result->get_error_message(),
			'chunks_created' => 0,
		]);
	}

	wp_send_json_success([
		'upload_id' => (int) $pid,
		'chunks_created' => $parse_result['chunks_created'],
		'chunk_ids' => $parse_result['chunk_ids'],
	]);
});

// ==============================
// AJAX: listar uploads
// ==============================
add_action('wp_ajax_aichat_list_uploads', function () {
	check_ajax_referer('aichat_pdf_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}

	$page = isset($_POST['page']) ? absint(wp_unslash($_POST['page'])) : 1;
	$page = max(1, $page);
	$per = isset($_POST['per_page']) ? absint(wp_unslash($_POST['per_page'])) : 10;
	$per = max(1, min(100, $per));
	$s = sanitize_text_field(wp_unslash($_POST['search'] ?? ''));

	$args = [
		'post_type' => 'aichat_upload',
		'post_status' => ['private'],
		'posts_per_page' => $per,
		'paged' => $page,
		'orderby' => 'modified',
		'order' => 'DESC',
		's' => $s,
		'no_found_rows' => false,
	];
	$q = new WP_Query($args);

	$items = array_map('aichat_upload_to_row', $q->posts);
	$total = (int) $q->found_posts;

	wp_send_json_success([
		'items' => $items,
		'total' => $total,
		'page' => $page,
		'per_page' => $per,
	]);
});

// ==============================
// AJAX: parse / chunk (Re-parse support)
// ==============================
add_action('wp_ajax_aichat_parse_upload', function () {
	check_ajax_referer('aichat_pdf_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}
	$upload_id = isset($_POST['upload_id']) ? absint(wp_unslash($_POST['upload_id'])) : 0;
	$force_raw = sanitize_text_field(wp_unslash($_POST['force'] ?? ''));
	$force = (bool) filter_var($force_raw, FILTER_VALIDATE_BOOLEAN);

	if ($upload_id <= 0) {
		wp_send_json_error(['message' => 'Invalid upload_id'], 400);
	}

	$result = aichat_do_parse_upload($upload_id, $force);
	if (is_wp_error($result)) {
		wp_send_json_error(['message' => $result->get_error_message()], 500);
	}

	wp_send_json_success($result);
});

// ==============================
// AJAX: devolver IDs de chunks (para Add to Context)
// ==============================
add_action('wp_ajax_aichat_get_chunks_for_upload', function () {
	check_ajax_referer('aichat_pdf_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}
	$upload_id = isset($_POST['upload_id']) ? absint(wp_unslash($_POST['upload_id'])) : 0;
	if ($upload_id <= 0) {
		wp_send_json_error(['message' => 'Invalid upload_id'], 400);
	}

	$ids = get_posts([
		'post_type' => 'aichat_upload_chunk',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields' => 'ids',
		'meta_key' => '_aichat_upload_id',
		'meta_value' => $upload_id,
		'orderby' => 'meta_value_num',
		'meta_key' => '_aichat_chunk_index',
		'order' => 'ASC',
	]);
	wp_send_json_success(['chunk_ids' => array_map('intval', $ids)]);
});

// ==============================
// AJAX: borrar upload + chunks
// ==============================
add_action('wp_ajax_aichat_delete_upload', function () {
	check_ajax_referer('aichat_pdf_nonce', 'nonce');
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized'], 403);
	}
	$upload_id = isset($_POST['upload_id']) ? absint(wp_unslash($_POST['upload_id'])) : 0;
	if ($upload_id <= 0) {
		wp_send_json_error(['message' => 'Invalid upload_id'], 400);
	}

	// Borra chunks primero
	$ids = get_posts([
		'post_type' => 'aichat_upload_chunk',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields' => 'ids',
		'meta_key' => '_aichat_upload_id',
		'meta_value' => $upload_id,
	]);
	foreach ($ids as $cid) {
		wp_delete_post($cid, true);
	}

	// Borra fichero físico
	$path = get_post_meta($upload_id, '_aichat_path', true);
	if ($path && file_exists($path)) {
		wp_delete_file($path);
	}

	// Borra upload
	wp_delete_post($upload_id, true);

	wp_send_json_success(['deleted' => true]);
});
