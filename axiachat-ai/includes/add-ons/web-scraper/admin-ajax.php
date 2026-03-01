<?php

/**
 * Web Scraper – AJAX handlers
 *
 * Handles:
 *  - aichat_webscraper_fetch_urls      → Scrape a list of URLs, create aichat_upload + chunks
 *  - aichat_webscraper_crawl_site      → Discover URLs from a root page (spider)
 *  - aichat_webscraper_import_scraped  → Convert already-scraped URLs into upload posts
 *
 * @package AxiaChat
 * @subpackage WebScraper
 * @since 3.2.0
 */

if (! defined('ABSPATH')) {
	exit;
}

/* ──────────────────────────────
 * Register AJAX actions
 * ────────────────────────────── */
add_action('wp_ajax_aichat_webscraper_fetch_urls', 'aichat_webscraper_fetch_urls_handler');
add_action('wp_ajax_aichat_webscraper_crawl_site', 'aichat_webscraper_crawl_site_handler');

/**
 * Scrape a list of URLs and create aichat_upload + aichat_upload_chunk posts.
 *
 * POST params:
 *   - nonce       (string)  aichat_training nonce
 *   - urls        (string)  JSON-encoded array of URLs
 *   - include_url (int)     1 = prepend source URL to content
 */
function aichat_webscraper_fetch_urls_handler()
{
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
	check_ajax_referer('aichat_training', 'nonce');

	$raw_urls = isset($_POST['urls']) ? sanitize_text_field(wp_unslash($_POST['urls'])) : '[]';
	$include_url = isset($_POST['include_url']) ? (int) $_POST['include_url'] : 0;

	$urls = json_decode($raw_urls, true);
	if (! is_array($urls) || empty($urls)) {
		wp_send_json_error(['message' => __('No URLs provided.', 'axiachat-ai')]);
	}

	// Limit batch size
	$max_batch = apply_filters('aichat_webscraper_max_batch', 20);
	$urls = array_slice($urls, 0, $max_batch);

	$results = [];
	$errors = [];
	$upload_ids = [];

	foreach ($urls as $url) {
		$url = trim($url);
		if (empty($url)) {
			continue;
		}

		// Validate URL
		if (! filter_var($url, FILTER_VALIDATE_URL)) {
			$errors[] = [
				'url' => $url,
				'message' => __('Invalid URL format.', 'axiachat-ai'),
			];
			continue;
		}

		// Extract content
		$extracted = aichat_webscraper_extract_content($url, (bool) $include_url);

		if (is_wp_error($extracted)) {
			$errors[] = [
				'url' => $url,
				'message' => $extracted->get_error_message(),
			];
			continue;
		}

		// Skip if no content
		if (empty($extracted['content'])) {
			$errors[] = [
				'url' => $url,
				'message' => __('No content could be extracted.', 'axiachat-ai'),
			];
			continue;
		}

		// Create aichat_upload post (parent) — mimics the PDF/TXT flow
		$safe_title = ! empty($extracted['title']) ? $extracted['title'] : $url;
		$upload_id = wp_insert_post([
			'post_type' => 'aichat_upload',
			'post_status' => 'private',
			'post_title' => sanitize_text_field($safe_title),
		], true);

		if (is_wp_error($upload_id)) {
			$errors[] = [
				'url' => $url,
				'message' => $upload_id->get_error_message(),
			];
			continue;
		}

		// Store metadata (same keys as PDF/TXT uploads for compatibility)
		add_post_meta($upload_id, '_aichat_filename', sanitize_text_field($url), true);
		add_post_meta($upload_id, '_aichat_mime', 'text/html', true);
		add_post_meta($upload_id, '_aichat_size', strlen($extracted['content']), true);
		add_post_meta($upload_id, '_aichat_source_type', 'web_scraper', true);
		add_post_meta($upload_id, '_aichat_source_url', esc_url_raw($url), true);

		// Create chunk posts using existing chunking pipeline
		if (function_exists('aichat_create_chunks_posts')) {
			$chunk_ids = aichat_create_chunks_posts($upload_id, $safe_title, $extracted['content']);
		} else {
			// Fallback: create a single chunk
			$chunk_ids = aichat_webscraper_create_single_chunk($upload_id, $safe_title, $extracted['content']);
		}

		update_post_meta($upload_id, '_aichat_status', 'chunked');
		update_post_meta($upload_id, '_aichat_chunk_count', count($chunk_ids));

		$upload_ids[] = $upload_id;

		$results[] = [
			'url' => $url,
			'title' => $extracted['title'],
			'excerpt' => $extracted['excerpt'],
			'content_length' => strlen($extracted['content']),
			'chunks_created' => count($chunk_ids),
			'upload_id' => $upload_id,
			'content_warning' => $extracted['content_warning'],
		];
	}

	wp_send_json_success([
		'results' => $results,
		'errors' => $errors,
		'upload_ids' => $upload_ids,
		'total_ok' => count($results),
		'total_err' => count($errors),
	]);
}

/**
 * Discover URLs from a root page (spider/crawler).
 *
 * POST params:
 *   - nonce      (string) aichat_training nonce
 *   - root_url   (string) The starting URL
 *   - max_depth  (int)    How many link levels to follow (1–5)
 *   - max_pages  (int)    Maximum pages to discover (1–200)
 */
function aichat_webscraper_crawl_site_handler()
{
	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Forbidden'], 403);
	}
	check_ajax_referer('aichat_training', 'nonce');

	$root_url = isset($_POST['root_url']) ? esc_url_raw(wp_unslash($_POST['root_url'])) : '';
	$max_depth = isset($_POST['max_depth']) ? absint($_POST['max_depth']) : 2;
	$max_pages = isset($_POST['max_pages']) ? absint($_POST['max_pages']) : 50;

	if (empty($root_url)) {
		wp_send_json_error(['message' => __('Please enter a valid URL.', 'axiachat-ai')]);
	}

	if (! filter_var($root_url, FILTER_VALIDATE_URL)) {
		wp_send_json_error(['message' => __('Invalid URL format.', 'axiachat-ai')]);
	}

	// Increase time limit for crawling
	if (function_exists('set_time_limit')) {
		set_time_limit(120); // phpcs:ignore
	}

	$result = aichat_webscraper_crawl($root_url, $max_depth, $max_pages);

	wp_send_json_success([
		'urls' => $result['urls'],
		'total' => $result['total'],
		'domain' => $result['domain'],
		'errors' => $result['errors'],
	]);
}

/**
 * Fallback: create a single chunk post when the main chunking function is unavailable.
 *
 * @param int    $upload_id Parent upload post ID.
 * @param string $title     Document title.
 * @param string $content   Full text content.
 * @return array Array of chunk post IDs.
 */
function aichat_webscraper_create_single_chunk($upload_id, $title, $content)
{
	$chunk_title = sanitize_text_field($title . ' (chunk 1/1)');
	$post_id = wp_insert_post([
		'post_type' => 'aichat_upload_chunk',
		'post_status' => 'publish',
		'post_title' => $chunk_title,
		'post_content' => wp_kses_post($content),
	], true);

	if (is_wp_error($post_id) || ! $post_id) {
		return [];
	}

	add_post_meta($post_id, '_aichat_upload_id', (int) $upload_id, true);
	add_post_meta($post_id, '_aichat_chunk_index', 0, true);
	add_post_meta($post_id, '_aichat_tokens', str_word_count($content), true);

	return [(int) $post_id];
}
