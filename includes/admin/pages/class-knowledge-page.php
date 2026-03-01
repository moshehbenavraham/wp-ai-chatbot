<?php

/**
 * Knowledge Base admin page controller.
 *
 * Provides:
 *  - Knowledge context list with chunk stats and status
 *  - Ingest action button per context (REST-driven)
 *  - Global RAG settings (embedding provider, model, chunk size, overlap, min score)
 *  - REST endpoints: POST /knowledge, POST /knowledge/{id}/ingest, GET /knowledge/{id}/status
 *
 * REST routes are registered via the `wpaic_register_rest_routes` action in Plugin,
 * which delegates here via Plugin::register_knowledge_rest_routes().
 *
 * @package WP_AI_Chatbot
 * @since   1.0.0
 */

namespace WPAIC\Admin\Pages;

use WPAIC\Admin\Admin_Page;
use WPAIC\Rag\Adapters\Web_Source;
use WPAIC\Rag\Content_Source_Registry;
use WPAIC\Rag\Embedding_Registry;
use WPAIC\Rag\Ingestion_Pipeline;
use WPAIC\Rag\Knowledge_Index;

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Class Knowledge_Page
 *
 * @since 1.0.0
 * @package WPAIC\Admin\Pages
 */
class Knowledge_Page extends Admin_Page
{
	/** WP Settings option group. */
	private const OPTION_GROUP = 'wpaic_rag_settings';

	/** {@inheritdoc} */
	public function get_slug(): string
	{
		return 'uwpaic-knowledge';
	}

	/** {@inheritdoc} */
	public function get_title(): string
	{
		return __('WP AI Chatbot — Knowledge Base', 'wp-ai-chatbot');
	}

	// =========================================================================
	// Settings registration (admin_init via Admin_Router)
	// =========================================================================

	/** {@inheritdoc} */
	public function register_settings(): void
	{
		register_setting(
			self::OPTION_GROUP,
			'wpaic_rag_embed_provider',
			[
				'type' => 'string',
				'sanitize_callback' => 'sanitize_key',
				'default' => 'openai',
			]
		);

		register_setting(
			self::OPTION_GROUP,
			'wpaic_rag_embed_model',
			[
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'text-embedding-3-small',
			]
		);

		register_setting(
			self::OPTION_GROUP,
			'wpaic_rag_chunk_size',
			[
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 4000,
			]
		);

		register_setting(
			self::OPTION_GROUP,
			'wpaic_rag_chunk_overlap',
			[
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 200,
			]
		);

		register_setting(
			self::OPTION_GROUP,
			'wpaic_rag_min_score',
			[
				'type' => 'number',
				'sanitize_callback' => static function ($v) {
					return max(0.0, min(1.0, (float) $v));
				},
				'default' => 0.3,
			]
		);
	}

	// =========================================================================
	// REST callbacks (called from Plugin::register_knowledge_rest_routes())
	// =========================================================================

	/**
	 * REST POST /knowledge — create a new knowledge context.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_create_knowledge(\WP_REST_Request $request): \WP_REST_Response
	{
		global $wpdb;

		$name = sanitize_text_field((string) $request->get_param('name'));
		$provider = sanitize_key((string) $request->get_param('embedding_provider'));
		$model = sanitize_text_field((string) $request->get_param('embedding_model'));

		if ('' === $name) {
			return new \WP_REST_Response(
				['success' => false, 'message' => __('Name is required.', 'wp-ai-chatbot')],
				400
			);
		}

		$table = $wpdb->prefix . 'wpaic_knowledge';
		$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			[
				'name' => $name,
				'context_type' => 'local',
				'embedding_provider' => $provider ?: 'openai',
				'embedding_model' => $model ?: 'text-embedding-3-small',
				'processing_status' => 'idle',
			],
			['%s', '%s', '%s', '%s', '%s']
		);

		if (! $inserted) {
			return new \WP_REST_Response(
				['success' => false, 'message' => __('Failed to create knowledge context.', 'wp-ai-chatbot')],
				500
			);
		}

		return new \WP_REST_Response(
			['success' => true, 'id' => (int) $wpdb->insert_id, 'name' => $name],
			201
		);
	}

	/**
	 * REST POST /knowledge/{id}/ingest — trigger ingestion for a knowledge context.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_trigger_ingest(\WP_REST_Request $request): \WP_REST_Response
	{
		$knowledge_id = (int) $request->get_param('id');
		$source_id = sanitize_key((string) ($request->get_param('source_id') ?? 'posts'));
		$post_types = array_map('sanitize_key', (array) ($request->get_param('post_types') ?? ['post', 'page']));

		$source = Content_Source_Registry::instance()->get($source_id);

		if (null === $source) {
			return new \WP_REST_Response(
				['success' => false, 'message' => __('Unknown content source.', 'wp-ai-chatbot')],
				400
			);
		}

		$items = $source->get_items(['post_types' => $post_types]);
		$item_ids = array_column($items, 'id');

		if (empty($item_ids)) {
			return new \WP_REST_Response(
				['success' => true, 'queued' => 0, 'message' => __('No items found to index.', 'wp-ai-chatbot')],
				200
			);
		}

		$queued = Ingestion_Pipeline::enqueue($knowledge_id, $source_id, $item_ids);

		return new \WP_REST_Response(
			[
				'success' => true,
				'queued' => $queued,
				'message' => sprintf(
					/* translators: %d: items queued */
					__('%d items queued for indexing.', 'wp-ai-chatbot'),
					$queued
				),
			],
			200
		);
	}

	/**
	 * REST GET /knowledge/{id}/status — return status and stats for a knowledge context.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_get_status(\WP_REST_Request $request): \WP_REST_Response
	{
		global $wpdb;

		$knowledge_id = (int) $request->get_param('id');
		$table = $wpdb->prefix . 'wpaic_knowledge';

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare('SELECT * FROM `' . $table . '` WHERE id = %d LIMIT 1', $knowledge_id),
			ARRAY_A
		);

		if (! is_array($row)) {
			return new \WP_REST_Response(
				['success' => false, 'message' => __('Knowledge context not found.', 'wp-ai-chatbot')],
				404
			);
		}

		$chunk_count = Knowledge_Index::chunk_count($knowledge_id);

		$queue_table = $wpdb->prefix . 'wpaic_queue';
		$pending = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM `{$queue_table}` WHERE bot_id = %s AND status IN ('pending','processing')",
				(string) $knowledge_id
			)
		);

		return new \WP_REST_Response(
			[
				'success' => true,
				'id' => $knowledge_id,
				'name' => $row['name'],
				'status' => $row['processing_status'],
				'progress' => (int) $row['processing_progress'],
				'chunks' => $chunk_count,
				'queue_size' => $pending,
			],
			200
		);
	}

	// =========================================================================
	// Page rendering
	// =========================================================================

	/** {@inheritdoc} */
	public function render(): void
	{
		$this->render_page_header();
		$this->render_knowledge_list();
		$this->render_rag_settings_form();
		$this->render_web_sources();
		$this->render_search_console();
		$this->render_page_footer();
	}

	/**
	 * Render the knowledge context list table with stats and action buttons.
	 *
	 * @return void
	 */
	private function render_knowledge_list(): void
	{
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_knowledge';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results('SELECT * FROM `' . $table . '` ORDER BY id DESC', ARRAY_A);

		echo '<div class="wpaic-knowledge-list" style="margin: 1rem 0;">';
		echo '<h2>' . esc_html__('Knowledge Contexts', 'wp-ai-chatbot') . '</h2>';

		printf(
			'<button type="button" class="button button-primary" id="wpaic-create-knowledge" data-nonce="%s" data-rest="%s">%s</button>',
			esc_attr(wp_create_nonce('wp_rest')),
			esc_url(rest_url(WPAIC_REST_NAMESPACE . '/knowledge')),
			esc_html__('+ New Knowledge Context', 'wp-ai-chatbot')
		);

		if (empty($rows)) {
			echo '<p style="margin-top:1rem">' . esc_html__('No knowledge contexts yet. Create one to start indexing your content.', 'wp-ai-chatbot') . '</p>';
			echo '</div>';

			return;
		}

		echo '<table class="wp-list-table widefat fixed striped" style="margin-top:1rem">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Name', 'wp-ai-chatbot') . '</th>';
		echo '<th>' . esc_html__('Status', 'wp-ai-chatbot') . '</th>';
		echo '<th>' . esc_html__('Chunks', 'wp-ai-chatbot') . '</th>';
		echo '<th>' . esc_html__('Embedding', 'wp-ai-chatbot') . '</th>';
		echo '<th>' . esc_html__('Actions', 'wp-ai-chatbot') . '</th>';
		echo '</tr></thead><tbody>';

		foreach ($rows as $row) {
			$id = (int) $row['id'];
			$chunks = Knowledge_Index::chunk_count($id);
			$status = esc_html((string) $row['processing_status']);
			$provider = esc_html((string) $row['embedding_provider']);
			$model = esc_html((string) $row['embedding_model']);

			echo '<tr data-knowledge-id="' . esc_attr((string) $id) . '">';
			echo '<td><strong>' . esc_html((string) $row['name']) . '</strong></td>';
			echo '<td><code>' . $status . '</code></td>';
			echo '<td>' . esc_html((string) $chunks) . '</td>';
			echo '<td>' . $provider . ' / ' . $model . '</td>';
			echo '<td>';

			printf(
				'<button type="button" class="button button-small wpaic-ingest-btn" data-id="%s" data-nonce="%s" data-rest="%s">%s</button>',
				esc_attr((string) $id),
				esc_attr(wp_create_nonce('wp_rest')),
				esc_url(rest_url(WPAIC_REST_NAMESPACE . '/knowledge/' . $id . '/ingest')),
				esc_html__('Ingest', 'wp-ai-chatbot')
			);

			echo ' ';

			printf(
				'<button type="button" class="button button-small wpaic-status-btn" data-id="%s" data-rest="%s">%s</button>',
				esc_attr((string) $id),
				esc_url(rest_url(WPAIC_REST_NAMESPACE . '/knowledge/' . $id . '/status')),
				esc_html__('Refresh', 'wp-ai-chatbot')
			);

			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
	}

	// =========================================================================
	// REST callbacks — web sources
	// =========================================================================

	/**
	 * REST GET /knowledge/web-sources — list all configured web source entries.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_list_web_sources(\WP_REST_Request $request): \WP_REST_Response
	{
		$sources = Web_Source::get_stored_sources();

		return new \WP_REST_Response(['success' => true, 'sources' => $sources], 200);
	}

	/**
	 * REST POST /knowledge/web-sources — create or update a web source entry.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_upsert_web_source(\WP_REST_Request $request): \WP_REST_Response
	{
		$url = esc_url_raw((string) $request->get_param('url'));
		$label = sanitize_text_field((string) $request->get_param('label'));
		$depth = min(3, max(1, (int) ($request->get_param('depth') ?? 1)));
		$id = (int) ($request->get_param('id') ?? 0);

		if ('' === $url) {
			return new \WP_REST_Response(
				['success' => false, 'message' => __('URL is required.', 'wp-ai-chatbot')],
				400
			);
		}

		if ('' === $label) {
			$label = (string) wp_parse_url($url, PHP_URL_HOST);
		}

		$new_id = Web_Source::upsert_source(
			[
				'id' => $id,
				'url' => $url,
				'label' => $label,
				'depth' => $depth,
			]
		);

		return new \WP_REST_Response(
			['success' => true, 'id' => $new_id, 'url' => $url, 'label' => $label, 'depth' => $depth],
			$id > 0 ? 200 : 201
		);
	}

	/**
	 * REST DELETE /knowledge/web-sources/{id} — delete a web source entry.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_delete_web_source(\WP_REST_Request $request): \WP_REST_Response
	{
		$id = (int) $request->get_param('id');

		if ($id < 1) {
			return new \WP_REST_Response(
				['success' => false, 'message' => __('Invalid ID.', 'wp-ai-chatbot')],
				400
			);
		}

		Web_Source::delete_source($id);

		return new \WP_REST_Response(['success' => true], 200);
	}

	// =========================================================================
	// REST callbacks — search console
	// =========================================================================

	/**
	 * REST GET /knowledge/{id}/search — embed query and return scored chunks.
	 *
	 * @param  \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_search(\WP_REST_Request $request): \WP_REST_Response
	{
		global $wpdb;

		$knowledge_id = (int) $request->get_param('id');
		$query_text = sanitize_text_field((string) ($request->get_param('query') ?? ''));
		$limit = min(20, max(1, (int) ($request->get_param('limit') ?? 5)));

		if ('' === $query_text) {
			return new \WP_REST_Response(
				['success' => false, 'message' => __('Query parameter is required.', 'wp-ai-chatbot')],
				400
			);
		}

		// Load knowledge context row for embedding provider config.
		$kn_table = $wpdb->prefix . 'wpaic_knowledge';
		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare('SELECT * FROM `' . $kn_table . '` WHERE id = %d LIMIT 1', $knowledge_id),
			ARRAY_A
		);

		if (! is_array($row)) {
			return new \WP_REST_Response(
				['success' => false, 'message' => __('Knowledge context not found.', 'wp-ai-chatbot')],
				404
			);
		}

		// Resolve embedding provider.
		$embed_provider = Embedding_Registry::instance()->get((string) $row['embedding_provider']);

		if (null === $embed_provider) {
			return new \WP_REST_Response(
				['success' => false, 'message' => __('Configured embedding provider is not available. Check your API key.', 'wp-ai-chatbot')],
				503
			);
		}

		// Embed the query.
		$embedding = $embed_provider->embed($query_text, (string) $row['embedding_model']);

		if (is_wp_error($embedding)) {
			return new \WP_REST_Response(
				['success' => false, 'message' => $embedding->get_error_message()],
				502
			);
		}

		// Search (use 0.0 floor for the console — show everything ranked).
		$results = Knowledge_Index::search($knowledge_id, $embedding, $limit, 0.0);

		// Enrich each result with a human-readable source URL.
		foreach ($results as &$result) {
			$result['source_url'] = $this->get_chunk_source_url(
				(int) $result['post_id'],
				(string) $result['type']
			);
		}
		unset($result);

		return new \WP_REST_Response(
			[
				'success' => true,
				'count' => count($results),
				'results' => $results,
			],
			200
		);
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Look up the canonical URL for a chunk based on its type and post_id.
	 *
	 * For 'pdf' types post_id is a WP attachment ID.
	 * For 'web' types post_id is the entry ID in wpaic_web_sources.
	 * All other types are assumed to be WP post IDs.
	 *
	 * @param  int    $post_id Item ID (meaning depends on type).
	 * @param  string $type    Chunk source type (e.g. 'post', 'page', 'pdf', 'web').
	 * @return string Absolute URL, or empty string when not resolvable.
	 */
	private function get_chunk_source_url(int $post_id, string $type): string
	{
		if ('pdf' === $type) {
			$url = wp_get_attachment_url($post_id);

			return (false !== $url) ? (string) $url : '';
		}

		if ('web' === $type && class_exists('WPAIC\\Rag\\Adapters\\Web_Source')) {
			foreach (Web_Source::get_stored_sources() as $src) {
				if ((int) $src['id'] === $post_id) {
					return (string) $src['url'];
				}
			}

			return '';
		}

		$permalink = get_permalink($post_id);

		return (false !== $permalink) ? (string) $permalink : '';
	}

	/**
	 * Render the global RAG settings form using the WP Settings API.
	 *
	 * @return void
	 */
	private function render_rag_settings_form(): void
	{
		$all_providers = Embedding_Registry::instance()->all();
		$current_provider = (string) get_option('wpaic_rag_embed_provider', 'openai');
		$current_model = (string) get_option('wpaic_rag_embed_model', 'text-embedding-3-small');
		$chunk_size = (int) get_option('wpaic_rag_chunk_size', 4000);
		$chunk_overlap = (int) get_option('wpaic_rag_chunk_overlap', 200);
		$min_score = (float) get_option('wpaic_rag_min_score', 0.3);

		echo '<hr /><h2>' . esc_html__('Global RAG Settings', 'wp-ai-chatbot') . '</h2>';
		echo '<form method="post" action="options.php" novalidate>';
		settings_fields(self::OPTION_GROUP);
		echo '<table class="form-table" role="presentation"><tbody>';

		// Embedding provider.
		echo '<tr><th scope="row"><label for="wpaic_rag_embed_provider">' . esc_html__('Embedding Provider', 'wp-ai-chatbot') . '</label></th><td>';
		echo '<select name="wpaic_rag_embed_provider" id="wpaic_rag_embed_provider">';

		if (! empty($all_providers)) {
			foreach ($all_providers as $pid => $provider_obj) {
				printf(
					'<option value="%s"%s>%s</option>',
					esc_attr((string) $pid),
					selected($current_provider, $pid, false),
					esc_html($provider_obj->get_label())
				);
			}
		} else {
			echo '<option value="openai"' . selected($current_provider, 'openai', false) . '>OpenAI</option>';
		}

		echo '</select></td></tr>';

		// Embedding model.
		echo '<tr><th scope="row"><label for="wpaic_rag_embed_model">' . esc_html__('Embedding Model', 'wp-ai-chatbot') . '</label></th><td>';
		printf(
			'<input type="text" name="wpaic_rag_embed_model" id="wpaic_rag_embed_model" value="%s" class="regular-text" />',
			esc_attr($current_model)
		);
		echo '<p class="description">' . esc_html__('For OpenAI: text-embedding-3-small (recommended)', 'wp-ai-chatbot') . '</p></td></tr>';

		// Chunk size.
		echo '<tr><th scope="row"><label for="wpaic_rag_chunk_size">' . esc_html__('Chunk Size (chars)', 'wp-ai-chatbot') . '</label></th><td>';
		printf(
			'<input type="number" name="wpaic_rag_chunk_size" id="wpaic_rag_chunk_size" value="%s" min="500" max="16000" step="100" class="small-text" />',
			esc_attr((string) $chunk_size)
		);
		echo '<p class="description">' . esc_html__('~4 chars per token. 4000 chars ≈ 1000 tokens.', 'wp-ai-chatbot') . '</p></td></tr>';

		// Chunk overlap.
		echo '<tr><th scope="row"><label for="wpaic_rag_chunk_overlap">' . esc_html__('Chunk Overlap (chars)', 'wp-ai-chatbot') . '</label></th><td>';
		printf(
			'<input type="number" name="wpaic_rag_chunk_overlap" id="wpaic_rag_chunk_overlap" value="%s" min="0" max="2000" step="50" class="small-text" />',
			esc_attr((string) $chunk_overlap)
		);
		echo '<p class="description">' . esc_html__('Characters re-included at the start of each chunk for context continuity.', 'wp-ai-chatbot') . '</p></td></tr>';

		// Min similarity score.
		echo '<tr><th scope="row"><label for="wpaic_rag_min_score">' . esc_html__('Minimum Similarity Score', 'wp-ai-chatbot') . '</label></th><td>';
		printf(
			'<input type="number" name="wpaic_rag_min_score" id="wpaic_rag_min_score" value="%s" min="0" max="1" step="0.05" class="small-text" />',
			esc_attr((string) $min_score)
		);
		echo '<p class="description">' . esc_html__('0.0–1.0. Higher = stricter relevance. Default: 0.3', 'wp-ai-chatbot') . '</p></td></tr>';

		echo '</tbody></table>';
		submit_button(__('Save RAG Settings', 'wp-ai-chatbot'));
		echo '</form>';
	}

	/**
	 * Render the Web Sources management section.
	 *
	 * Lists configured URL entries with depth badges and provides REST-driven
	 * add/delete controls. Uploading and indexing is triggered via the standard
	 * knowledge context Ingest button (source_id = 'web').
	 *
	 * @return void
	 */
	private function render_web_sources(): void
	{
		$sources = Web_Source::get_stored_sources();
		$rest_base = rest_url(WPAIC_REST_NAMESPACE . '/knowledge/web-sources');
		$nonce = wp_create_nonce('wp_rest');

		echo '<hr />';
		echo '<h2>' . esc_html__('Web Page Sources', 'wp-ai-chatbot') . '</h2>';
		echo '<p class="description">' . esc_html__('Configure URLs to crawl and index as knowledge base content. After adding a URL, trigger indexing via the Ingest button on the knowledge context (source: Web Pages).', 'wp-ai-chatbot') . '</p>';

		// Add form.
		printf(
			'<div id="wpaic-add-web-source" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin:1rem 0;">'
			. '<div><label for="wpaic-ws-url"><strong>%s</strong></label><br>'
			. '<input type="url" id="wpaic-ws-url" placeholder="https://example.com" class="regular-text" /></div>'
			. '<div><label for="wpaic-ws-label"><strong>%s</strong></label><br>'
			. '<input type="text" id="wpaic-ws-label" placeholder="%s" class="regular-text" /></div>'
			. '<div><label for="wpaic-ws-depth"><strong>%s</strong></label><br>'
			. '<select id="wpaic-ws-depth"><option value="1">1</option><option value="2">2</option><option value="3">3</option></select></div>'
			. '<div style="padding-bottom:2px;"><button type="button" class="button button-primary" id="wpaic-ws-add-btn" data-nonce="%s" data-rest="%s">%s</button></div>'
			. '</div>',
			esc_html__('URL', 'wp-ai-chatbot'),
			esc_html__('Label', 'wp-ai-chatbot'),
			esc_attr__('My Site', 'wp-ai-chatbot'),
			esc_html__('Crawl Depth', 'wp-ai-chatbot'),
			esc_attr($nonce),
			esc_url($rest_base),
			esc_html__('Add URL', 'wp-ai-chatbot')
		);

		// Sources table.
		echo '<table class="wp-list-table widefat fixed striped" id="wpaic-web-sources-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__('Label', 'wp-ai-chatbot') . '</th>';
		echo '<th>' . esc_html__('URL', 'wp-ai-chatbot') . '</th>';
		echo '<th style="width:90px;">' . esc_html__('Depth', 'wp-ai-chatbot') . '</th>';
		echo '<th style="width:100px;">' . esc_html__('Actions', 'wp-ai-chatbot') . '</th>';
		echo '</tr></thead>';
		echo '<tbody id="wpaic-web-sources-tbody">';

		if (empty($sources)) {
			echo '<tr id="wpaic-ws-empty-row"><td colspan="4">' . esc_html__('No web sources configured yet.', 'wp-ai-chatbot') . '</td></tr>';
		} else {
			foreach ($sources as $src) {
				$sid = (int) $src['id'];
				echo '<tr data-ws-id="' . esc_attr((string) $sid) . '">';
				echo '<td><strong>' . esc_html((string) $src['label']) . '</strong></td>';
				echo '<td><a href="' . esc_url((string) $src['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html((string) $src['url']) . '</a></td>';
				echo '<td>' . esc_html((string) $src['depth']) . '</td>';
				echo '<td>';
				printf(
					'<button type="button" class="button button-small wpaic-ws-delete-btn" data-id="%s" data-nonce="%s" data-rest="%s">%s</button>',
					esc_attr((string) $sid),
					esc_attr($nonce),
					esc_url($rest_base . '/' . $sid),
					esc_html__('Delete', 'wp-ai-chatbot')
				);
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';

		// Inline JS for add/delete actions.
		?>
		<script>
		(function () {
			var nonce = <?php echo wp_json_encode($nonce); ?>;
			var restBase = <?php echo wp_json_encode($rest_base); ?>;

			function wpRestFetch(url, method, body) {
				return fetch(url, {
					method: method,
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
					body: body ? JSON.stringify(body) : undefined
				}).then(function (r) { return r.json(); });
			}

			// Add button handler.
			document.getElementById('wpaic-ws-add-btn').addEventListener('click', function () {
				var url = document.getElementById('wpaic-ws-url').value.trim();
				var label = document.getElementById('wpaic-ws-label').value.trim();
				var depth = parseInt(document.getElementById('wpaic-ws-depth').value, 10);

				if (! url) { alert(<?php echo wp_json_encode(__('Please enter a URL.', 'wp-ai-chatbot')); ?>); return; }

				this.disabled = true;

				wpRestFetch(restBase, 'POST', { url: url, label: label || url, depth: depth })
					.then(function (data) {
						if (! data.success) { alert(data.message || 'Error'); return; }

						var tbody = document.getElementById('wpaic-web-sources-tbody');
						var emptyRow = document.getElementById('wpaic-ws-empty-row');
						if (emptyRow) emptyRow.parentNode.removeChild(emptyRow);

						var row = document.createElement('tr');
						row.setAttribute('data-ws-id', data.id);
						row.innerHTML =
							'<td><strong>' + data.label.replace(/</g,'&lt;') + '</strong></td>' +
							'<td><a href="' + data.url.replace(/"/g,'&quot;') + '" target="_blank" rel="noopener noreferrer">' + data.url.replace(/</g,'&lt;') + '</a></td>' +
							'<td>' + data.depth + '</td>' +
							'<td><button type="button" class="button button-small wpaic-ws-delete-btn" data-id="' + data.id + '" data-nonce="' + nonce + '" data-rest="' + restBase + '/' + data.id + '"><?php echo esc_js(__('Delete', 'wp-ai-chatbot')); ?></button></td>';

						tbody.appendChild(row);
						bindDeleteBtn(row.querySelector('.wpaic-ws-delete-btn'));

						document.getElementById('wpaic-ws-url').value = '';
						document.getElementById('wpaic-ws-label').value = '';
					})
					.catch(function () { alert(<?php echo wp_json_encode(__('Request failed.', 'wp-ai-chatbot')); ?>); })
					.finally(function () {
						document.getElementById('wpaic-ws-add-btn').disabled = false;
					});
			});

			// Delete button handler (re-usable for dynamically added rows).
			function bindDeleteBtn(btn) {
				btn.addEventListener('click', function () {
					if (! confirm(<?php echo wp_json_encode(__('Delete this web source?', 'wp-ai-chatbot')); ?>)) return;

					var id = this.getAttribute('data-id');
					var deleteUrl = restBase + '/' + id;
					btn.disabled = true;

					wpRestFetch(deleteUrl, 'DELETE')
						.then(function (data) {
							if (! data.success) { alert(data.message || 'Error'); btn.disabled = false; return; }

							var row = document.querySelector('[data-ws-id="' + id + '"]');
							if (row) row.parentNode.removeChild(row);

							var tbody = document.getElementById('wpaic-web-sources-tbody');
							if (tbody && tbody.rows.length === 0) {
								var emptyRow = document.createElement('tr');
								emptyRow.id = 'wpaic-ws-empty-row';
								emptyRow.innerHTML = '<td colspan="4"><?php echo esc_js(__('No web sources configured yet.', 'wp-ai-chatbot')); ?></td>';
								tbody.appendChild(emptyRow);
							}
						})
						.catch(function () { alert(<?php echo wp_json_encode(__('Request failed.', 'wp-ai-chatbot')); ?>); btn.disabled = false; });
				});
			}

			// Bind existing delete buttons.
			document.querySelectorAll('.wpaic-ws-delete-btn').forEach(bindDeleteBtn);
		}());
		</script>
		<?php
	}

	/**
	 * Render the inline Search Console section.
	 *
	 * Allows admins to test retrieval quality by entering a natural-language
	 * query and seeing which chunks are returned, along with similarity scores
	 * and source metadata.
	 *
	 * @return void
	 */
	private function render_search_console(): void
	{
		global $wpdb;

		$kn_table = $wpdb->prefix . 'wpaic_knowledge';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
		$contexts = $wpdb->get_results('SELECT id, name FROM `' . $kn_table . '` ORDER BY id DESC', ARRAY_A);

		echo '<hr />';
		echo '<h2>' . esc_html__('Search Console', 'wp-ai-chatbot') . '</h2>';
		echo '<p class="description">' . esc_html__('Test retrieval quality by entering a query. Results show matched chunks, similarity scores, and source documents.', 'wp-ai-chatbot') . '</p>';

		if (empty($contexts)) {
			echo '<p>' . esc_html__('No knowledge contexts found. Create one and index content first.', 'wp-ai-chatbot') . '</p>';

			return;
		}

		$search_rest = rest_url(WPAIC_REST_NAMESPACE . '/knowledge/__ID__/search');
		$nonce = wp_create_nonce('wp_rest');

		echo '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin:1rem 0;">';

		// Context selector.
		echo '<div><label for="wpaic-sc-context"><strong>' . esc_html__('Knowledge Context', 'wp-ai-chatbot') . '</strong></label><br>';
		echo '<select id="wpaic-sc-context">';

		foreach ($contexts as $ctx) {
			printf(
				'<option value="%s">%s</option>',
				esc_attr((string) $ctx['id']),
				esc_html((string) $ctx['name'])
			);
		}

		echo '</select></div>';

		// Query input.
		echo '<div style="flex:1;min-width:260px;"><label for="wpaic-sc-query"><strong>' . esc_html__('Query', 'wp-ai-chatbot') . '</strong></label><br>';
		echo '<input type="text" id="wpaic-sc-query" class="regular-text" style="width:100%;" placeholder="' . esc_attr__('e.g. How do I reset my password?', 'wp-ai-chatbot') . '" /></div>';

		// Limit selector.
		echo '<div><label for="wpaic-sc-limit"><strong>' . esc_html__('Results', 'wp-ai-chatbot') . '</strong></label><br>';
		echo '<select id="wpaic-sc-limit"><option value="3">3</option><option value="5" selected>5</option><option value="10">10</option></select></div>';

		// Search button.
		echo '<div style="padding-bottom:2px;"><button type="button" class="button button-primary" id="wpaic-sc-search-btn">' . esc_html__('Test Search', 'wp-ai-chatbot') . '</button></div>';
		echo '</div>';

		// Results area.
		echo '<div id="wpaic-sc-results" style="margin-top:1rem;"></div>';

		?>
		<script>
		(function () {
			var nonce = <?php echo wp_json_encode($nonce); ?>;
			var restTemplate = <?php echo wp_json_encode($search_rest); ?>;

			document.getElementById('wpaic-sc-search-btn').addEventListener('click', function () {
				var contextId = document.getElementById('wpaic-sc-context').value;
				var query = document.getElementById('wpaic-sc-query').value.trim();
				var limit = document.getElementById('wpaic-sc-limit').value;
				var resultsEl = document.getElementById('wpaic-sc-results');
				var btn = this;

				if (! query) {
					alert(<?php echo wp_json_encode(__('Please enter a query.', 'wp-ai-chatbot')); ?>);
					return;
				}

				btn.disabled = true;
				resultsEl.innerHTML = '<p><em><?php echo esc_js(__('Searching…', 'wp-ai-chatbot')); ?></em></p>';

				var url = restTemplate.replace('__ID__', contextId)
					+ '?query=' + encodeURIComponent(query)
					+ '&limit=' + limit;

				fetch(url, { headers: { 'X-WP-Nonce': nonce } })
					.then(function (r) { return r.json(); })
					.then(function (data) {
						if (! data.success) {
							resultsEl.innerHTML = '<p style="color:#d63638;"><strong><?php echo esc_js(__('Error:', 'wp-ai-chatbot')); ?></strong> ' + (data.message || 'Unknown error') + '</p>';
							return;
						}

						if (data.count === 0) {
							resultsEl.innerHTML = '<p><?php echo esc_js(__('No results found. Try a different query or lower the minimum similarity score.', 'wp-ai-chatbot')); ?></p>';
							return;
						}

						var html = '<p><strong><?php echo esc_js(__('Results:', 'wp-ai-chatbot')); ?></strong> ' + data.count + '</p>';
						html += '<table class="wp-list-table widefat fixed striped">';
						html += '<thead><tr>'
							+ '<th style="width:50px;">#</th>'
							+ '<th style="width:80px;"><?php echo esc_js(__('Score', 'wp-ai-chatbot')); ?></th>'
							+ '<th style="width:120px;"><?php echo esc_js(__('Type', 'wp-ai-chatbot')); ?></th>'
							+ '<th><?php echo esc_js(__('Title', 'wp-ai-chatbot')); ?></th>'
							+ '<th><?php echo esc_js(__('Content Preview', 'wp-ai-chatbot')); ?></th>'
							+ '<th><?php echo esc_js(__('Source', 'wp-ai-chatbot')); ?></th>'
							+ '</tr></thead><tbody>';

						data.results.forEach(function (r, i) {
							var preview = r.content.length > 200 ? r.content.substring(0, 200) + '…' : r.content;
							var sourceLink = r.source_url
								? '<a href="' + r.source_url.replace(/"/g, '&quot;') + '" target="_blank" rel="noopener noreferrer">' + r.type + ' #' + r.post_id + '</a>'
								: r.type + ' #' + r.post_id;

							html += '<tr>'
								+ '<td>' + (i + 1) + '</td>'
								+ '<td><code>' + (Math.round(r.score * 1000) / 1000).toFixed(3) + '</code></td>'
								+ '<td><code>' + r.type.replace(/</g,'&lt;') + '</code></td>'
								+ '<td>' + r.title.replace(/</g,'&lt;') + '</td>'
								+ '<td><small>' + preview.replace(/</g,'&lt;') + '</small></td>'
								+ '<td><small>' + sourceLink + '</small></td>'
								+ '</tr>';
						});

						html += '</tbody></table>';
						resultsEl.innerHTML = html;
					})
					.catch(function () {
						resultsEl.innerHTML = '<p style="color:#d63638;"><?php echo esc_js(__('Request failed. Check browser console for details.', 'wp-ai-chatbot')); ?></p>';
					})
					.finally(function () { btn.disabled = false; });
			});

			// Allow Enter key to trigger search.
			document.getElementById('wpaic-sc-query').addEventListener('keydown', function (e) {
				if (e.key === 'Enter') document.getElementById('wpaic-sc-search-btn').click();
			});
		}());
		</script>
		<?php
	}
}
