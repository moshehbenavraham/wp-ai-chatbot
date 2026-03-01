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
}
