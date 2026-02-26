<?php
/**
 * File: admin/class-pinecone-manager.php
 *
 * Handles all Pinecone vector database operations for MxChat
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MxChat_Pinecone_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        // Hook into WordPress actions if needed
        add_action('mxchat_delete_content', array($this, 'mxchat_delete_from_pinecone_by_url'), 10, 1);
    }

    // ========================================
    // PINECONE FETCH OPERATIONS
    // ========================================
    
    
/**
 * Fetches records from Pinecone with bot-specific filtering
 * UPDATED 2.6.1: Optimized for large datasets - uses server-side pagination
 */
public function mxchat_fetch_pinecone_records($pinecone_options, $search_query = '', $page = 1, $per_page = 20, $bot_id = 'default', $content_type = '') {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
    $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

    if (empty($api_key) || empty($host)) {
        return array('data' => array(), 'total' => 0, 'total_in_database' => 0, 'showing_recent_only' => false);
    }

    try {
        // Get total count for the banner message (bot-specific) - lightweight call
        $total_in_database = $this->mxchat_get_pinecone_total_count($pinecone_options, $bot_id);

        // For large databases, use optimized paginated fetching
        // Only fetch what we need for the current page, not all 1K records
        if ($total_in_database > 500) {
            $result = $this->mxchat_fetch_pinecone_page_optimized($pinecone_options, $search_query, $page, $per_page, $bot_id, $content_type);
            $result['total_in_database'] = $total_in_database;
            $result['showing_recent_only'] = true; // Always show banner when we're limiting results
            return $result;
        }

        // For smaller databases, use the existing approach but with safety limits
        $all_records = $this->mxchat_get_recent_entries_safe($pinecone_options, $bot_id, 500);

        // Filter by content type if provided
        if (!empty($content_type)) {
            $all_records = array_filter($all_records, function($record) use ($content_type) {
                $record_type = $record->type ?? 'content';
                return $record_type === $content_type;
            });
            $all_records = array_values($all_records); // Re-index array
        }

        // Filter by search query if provided
        if (!empty($search_query)) {
            $all_records = array_filter($all_records, function($record) use ($search_query) {
                $content = $record->article_content ?? '';
                $source_url = $record->source_url ?? '';
                return stripos($content, $search_query) !== false || stripos($source_url, $search_query) !== false;
            });
            $all_records = array_values($all_records); // Re-index array
        }

        // UPDATED 2.6.3: Group records by source_url for chunk-aware pagination
        // This ensures pagination shows X entries per page, not X chunks
        $grouped_by_url = array();
        $empty_url_records = array();

        foreach ($all_records as $record) {
            $source_url = $record->source_url ?? '';
            if (empty($source_url)) {
                $empty_url_records[] = $record;
            } else {
                if (!isset($grouped_by_url[$source_url])) {
                    $grouped_by_url[$source_url] = array();
                }
                $grouped_by_url[$source_url][] = $record;
            }
        }

        // Count unique entries (unique URLs + individual empty-URL records)
        $total_unique_entries = count($grouped_by_url) + count($empty_url_records);

        // Paginate by unique entries
        $offset = ($page - 1) * $per_page;

        // Build ordered list of URL groups (newest first based on first record)
        $url_groups_ordered = array_keys($grouped_by_url);

        // Get the URLs for this page
        $page_urls = array_slice($url_groups_ordered, $offset, $per_page);

        // Collect all records for this page's URLs
        $paged_records = array();
        foreach ($page_urls as $url) {
            foreach ($grouped_by_url[$url] as $record) {
                $paged_records[] = $record;
            }
        }

        // Add empty-URL records if they fall within this page's range
        $remaining_slots = $per_page - count($page_urls);
        $empty_offset = max(0, $offset - count($grouped_by_url));
        if ($remaining_slots > 0 && $empty_offset < count($empty_url_records)) {
            $empty_page_records = array_slice($empty_url_records, $empty_offset, $remaining_slots);
            $paged_records = array_merge($paged_records, $empty_page_records);
        }

        return array(
            'data' => $paged_records,
            'total' => $total_unique_entries,
            'total_in_database' => $total_in_database,
            'showing_recent_only' => ($total_in_database > 500)
        );

    } catch (Exception $e) {
        error_log('MxChat Pinecone fetch error: ' . $e->getMessage());
        MxChat_Admin::mxchat_log_debug('pinecone_error', 'Pinecone fetch error: ' . $e->getMessage());
        return array('data' => array(), 'total' => 0, 'total_in_database' => 0, 'showing_recent_only' => false);
    }
}

/**
 * Optimized fetch for large Pinecone databases
 * ADDED 2.6.1: Prevents crashes with large datasets
 * UPDATED 2.6.1: When searching, uses semantic search with embedded query for accurate results across all 13K+ records
 */
private function mxchat_fetch_pinecone_page_optimized($pinecone_options, $search_query = '', $page = 1, $per_page = 20, $bot_id = 'default', $content_type = '') {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
    $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

    try {
        // If user is searching, use semantic search with embedded query
        // This searches ALL records in Pinecone, not just fetched ones
        if (!empty($search_query)) {
            return $this->mxchat_semantic_search_pinecone($pinecone_options, $search_query, $page, $per_page, $bot_id, $content_type);
        }

        // For browsing (no search), use Pinecone's list endpoint for true pagination
        return $this->mxchat_list_pinecone_records($pinecone_options, $page, $per_page, $bot_id, $content_type);

    } catch (Exception $e) {
        error_log('MxChat optimized fetch exception: ' . $e->getMessage());
        MxChat_Admin::mxchat_log_debug('pinecone_error', 'Pinecone optimized fetch error: ' . $e->getMessage());
        return array('data' => array(), 'total' => 0);
    }
}

/**
 * Semantic search across ALL Pinecone records using embedded search query
 * This allows users to find any of their 13K+ products by searching
 * ADDED 2.6.1
 */
private function mxchat_semantic_search_pinecone($pinecone_options, $search_query, $page = 1, $per_page = 20, $bot_id = 'default', $content_type = '') {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
    $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

    // Get embedding for the search query
    $query_embedding = $this->mxchat_get_search_embedding($search_query);

    if (empty($query_embedding)) {
        // Fallback to text-based search if embedding fails
        return $this->mxchat_text_search_fallback($pinecone_options, $search_query, $page, $per_page, $bot_id, $content_type);
    }

    $query_url = "https://{$host}/query";

    // Fetch more results to allow for filtering and pagination
    $fetch_limit = min(($page * $per_page) + 100, 500);

    $query_data = array(
        'includeMetadata' => true,
        'includeValues' => false,
        'topK' => $fetch_limit,
        'vector' => $query_embedding
    );

    if (!empty($namespace)) {
        $query_data['namespace'] = $namespace;
    }

    // Add metadata filter for content type if specified
    if (!empty($content_type)) {
        $query_data['filter'] = array(
            'type' => array('$eq' => $content_type)
        );
    }

    $response = wp_remote_post($query_url, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($query_data),
        'timeout' => 20
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return $this->mxchat_text_search_fallback($pinecone_options, $search_query, $page, $per_page, $bot_id, $content_type);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $records = array();
    if (isset($data['matches'])) {
        foreach ($data['matches'] as $match) {
            $metadata = $match['metadata'] ?? array();
            $created_at = $metadata['created_at'] ?? $metadata['last_updated'] ?? $metadata['timestamp'] ?? time();
            if (!is_numeric($created_at)) {
                $created_at = strtotime($created_at) ?: time();
            }

            $records[] = (object) array(
                'id' => $match['id'] ?? '',
                'article_content' => $metadata['text'] ?? '',
                'source_url' => $metadata['source_url'] ?? '',
                'role_restriction' => $metadata['role_restriction'] ?? 'public',
                'type' => $metadata['type'] ?? 'content',
                'bot_id' => $bot_id,
                'created_at' => $created_at,
                'data_source' => 'pinecone',
                'relevance_score' => $match['score'] ?? 0,
                'chunk_index' => isset($metadata['chunk_index']) ? intval($metadata['chunk_index']) : null,
                'total_chunks' => isset($metadata['total_chunks']) ? intval($metadata['total_chunks']) : null,
                'is_chunked' => isset($metadata['is_chunked']) ? (bool) $metadata['is_chunked'] : false
            );
        }
    }

    // For semantic search, results are already sorted by relevance (score)
    // No need to re-sort by date

    $total = count($records);
    $offset = ($page - 1) * $per_page;
    $paged_records = array_slice($records, $offset, $per_page);

    $this->mxchat_batch_fetch_role_restrictions($paged_records, $bot_id);

    return array(
        'data' => $paged_records,
        'total' => $total
    );
}

/**
 * Get embedding vector for a search query
 * Uses the same embedding model configured for the knowledge base
 * ADDED 2.6.1
 */
private function mxchat_get_search_embedding($search_query) {
    $options = get_option('mxchat_options', array());
    $embedding_model = $options['embedding_model'] ?? 'text-embedding-ada-002';

    // Determine which API to use based on model
    if (strpos($embedding_model, 'voyage-') === 0) {
        return $this->mxchat_get_voyage_embedding($search_query, $options, $embedding_model);
    } elseif (strpos($embedding_model, 'gemini-') === 0) {
        return $this->mxchat_get_gemini_embedding($search_query, $options, $embedding_model);
    } else {
        return $this->mxchat_get_openai_embedding($search_query, $options, $embedding_model);
    }
}

/**
 * Get OpenAI embedding for search query
 */
private function mxchat_get_openai_embedding($text, $options, $model) {
    $api_key = $options['api_key'] ?? '';
    if (empty($api_key)) {
        return null;
    }

    $response = wp_remote_post('https://api.openai.com/v1/embeddings', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode(array(
            'model' => $model,
            'input' => $text
        )),
        'timeout' => 15
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['data'][0]['embedding'] ?? null;
}

/**
 * Get Voyage AI embedding for search query
 */
private function mxchat_get_voyage_embedding($text, $options, $model) {
    $api_key = $options['voyage_api_key'] ?? '';
    if (empty($api_key)) {
        return null;
    }

    $request_body = array(
        'model' => $model,
        'input' => $text,
        'input_type' => 'query'
    );

    // Add output dimensions for voyage-3-large if configured
    if (strpos($model, 'voyage-3-large') === 0 && !empty($options['voyage_output_dimension'])) {
        $request_body['output_dimension'] = intval($options['voyage_output_dimension']);
    }

    $response = wp_remote_post('https://api.voyageai.com/v1/embeddings', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($request_body),
        'timeout' => 15
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['data'][0]['embedding'] ?? null;
}

/**
 * Get Google Gemini embedding for search query
 */
private function mxchat_get_gemini_embedding($text, $options, $model) {
    $api_key = $options['gemini_api_key'] ?? '';
    if (empty($api_key)) {
        return null;
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:embedContent?key={$api_key}";

    $request_body = array(
        'model' => "models/{$model}",
        'content' => array(
            'parts' => array(
                array('text' => $text)
            )
        ),
        'taskType' => 'RETRIEVAL_QUERY'
    );

    // Add output dimensions if configured
    if (!empty($options['gemini_output_dimension'])) {
        $request_body['outputDimensionality'] = intval($options['gemini_output_dimension']);
    }

    $response = wp_remote_post($url, array(
        'headers' => array(
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($request_body),
        'timeout' => 15
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['embedding']['values'] ?? null;
}

/**
 * Fallback text search when embedding fails
 * Fetches more records and filters by text match
 * ADDED 2.6.1
 */
private function mxchat_text_search_fallback($pinecone_options, $search_query, $page, $per_page, $bot_id, $content_type) {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
    $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

    $query_url = "https://{$host}/query";
    $query_vector = $this->mxchat_generate_optimized_query_vector();

    // Fetch more records to search through
    $query_data = array(
        'includeMetadata' => true,
        'includeValues' => false,
        'topK' => 1000, // Fetch more for text search
        'vector' => $query_vector
    );

    if (!empty($namespace)) {
        $query_data['namespace'] = $namespace;
    }

    $response = wp_remote_post($query_url, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($query_data),
        'timeout' => 20
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return array('data' => array(), 'total' => 0);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $records = array();
    $search_lower = strtolower($search_query);

    if (isset($data['matches'])) {
        foreach ($data['matches'] as $match) {
            $metadata = $match['metadata'] ?? array();

            // Filter by content type
            if (!empty($content_type)) {
                $record_type = $metadata['type'] ?? 'content';
                if ($record_type !== $content_type) {
                    continue;
                }
            }

            // Text search filter
            $content = strtolower($metadata['text'] ?? '');
            $source_url = strtolower($metadata['source_url'] ?? '');
            if (strpos($content, $search_lower) === false && strpos($source_url, $search_lower) === false) {
                continue;
            }

            $created_at = $metadata['created_at'] ?? $metadata['last_updated'] ?? $metadata['timestamp'] ?? time();
            if (!is_numeric($created_at)) {
                $created_at = strtotime($created_at) ?: time();
            }

            $records[] = (object) array(
                'id' => $match['id'] ?? '',
                'article_content' => $metadata['text'] ?? '',
                'source_url' => $metadata['source_url'] ?? '',
                'role_restriction' => $metadata['role_restriction'] ?? 'public',
                'type' => $metadata['type'] ?? 'content',
                'bot_id' => $bot_id,
                'created_at' => $created_at,
                'data_source' => 'pinecone',
                'chunk_index' => isset($metadata['chunk_index']) ? intval($metadata['chunk_index']) : null,
                'total_chunks' => isset($metadata['total_chunks']) ? intval($metadata['total_chunks']) : null,
                'is_chunked' => isset($metadata['is_chunked']) ? (bool) $metadata['is_chunked'] : false
            );
        }
    }

    // Sort by date for text search results
    usort($records, function($a, $b) {
        return $b->created_at - $a->created_at;
    });

    $total = count($records);
    $offset = ($page - 1) * $per_page;
    $paged_records = array_slice($records, $offset, $per_page);

    $this->mxchat_batch_fetch_role_restrictions($paged_records, $bot_id);

    return array(
        'data' => $paged_records,
        'total' => $total
    );
}

/**
 * List Pinecone records using the list endpoint for true pagination (no search)
 * This allows browsing through all 13K+ records page by page
 * ADDED 2.6.1
 */
private function mxchat_list_pinecone_records($pinecone_options, $page = 1, $per_page = 20, $bot_id = 'default', $content_type = '') {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
    $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

    // Pinecone's list endpoint returns vector IDs with pagination
    // We then fetch the metadata for those specific IDs
    $list_url = "https://{$host}/vectors/list";

    // Calculate pagination token from page number
    // Pinecone uses cursor-based pagination, so we need to handle this differently
    $limit = $per_page * 2; // Fetch extra to account for filtering

    $list_params = array(
        'limit' => $limit
    );

    if (!empty($namespace)) {
        $list_params['namespace'] = $namespace;
    }

    // For pages beyond first, we need to use pagination_token
    // Store/retrieve pagination tokens in transients
    $pagination_key = 'mxchat_pinecone_page_' . md5($host . $namespace . $content_type);

    if ($page > 1) {
        $stored_tokens = get_transient($pagination_key);
        if ($stored_tokens && isset($stored_tokens[$page])) {
            $list_params['paginationToken'] = $stored_tokens[$page];
        }
    }

    $response = wp_remote_post($list_url, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($list_params),
        'timeout' => 15
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        // Fallback to query-based approach
        return $this->mxchat_query_based_list($pinecone_options, $page, $per_page, $bot_id, $content_type);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Store pagination token for next page
    if (!empty($data['pagination']['next'])) {
        $stored_tokens = get_transient($pagination_key) ?: array();
        $stored_tokens[$page + 1] = $data['pagination']['next'];
        set_transient($pagination_key, $stored_tokens, 300); // 5 minute cache
    }

    $vector_ids = array();
    if (isset($data['vectors'])) {
        foreach ($data['vectors'] as $vector) {
            $vector_ids[] = $vector['id'];
        }
    }

    // If list endpoint returned empty, fall back to query-based approach
    if (empty($vector_ids)) {
        return $this->mxchat_query_based_list($pinecone_options, $page, $per_page, $bot_id, $content_type);
    }

    // Fetch metadata for these vector IDs
    return $this->mxchat_fetch_vectors_by_ids_for_list($pinecone_options, $vector_ids, $page, $per_page, $bot_id, $content_type);
}

/**
 * Query-based listing fallback when list endpoint fails
 * ADDED 2.6.1
 */
private function mxchat_query_based_list($pinecone_options, $page, $per_page, $bot_id, $content_type) {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
    $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

    $query_url = "https://{$host}/query";
    $query_vector = $this->mxchat_generate_optimized_query_vector();

    // Fetch records - use higher limit to cover large databases
    // Pinecone query API supports up to 10,000 topK
    // We fetch more than needed to get accurate total count and enable pagination
    $fetch_limit = 5000;

    $query_data = array(
        'includeMetadata' => true,
        'includeValues' => false,
        'topK' => $fetch_limit,
        'vector' => $query_vector
    );

    if (!empty($namespace)) {
        $query_data['namespace'] = $namespace;
    }

    // Add content type filter if specified
    if (!empty($content_type)) {
        $query_data['filter'] = array(
            'type' => array('$eq' => $content_type)
        );
    }

    $response = wp_remote_post($query_url, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($query_data),
        'timeout' => 30
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return array('data' => array(), 'total' => 0);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $records = array();
    if (isset($data['matches'])) {
        foreach ($data['matches'] as $match) {
            $metadata = $match['metadata'] ?? array();

            $created_at = $metadata['created_at'] ?? $metadata['last_updated'] ?? $metadata['timestamp'] ?? time();
            if (!is_numeric($created_at)) {
                $created_at = strtotime($created_at) ?: time();
            }

            $records[] = (object) array(
                'id' => $match['id'] ?? '',
                'article_content' => $metadata['text'] ?? '',
                'source_url' => $metadata['source_url'] ?? '',
                'role_restriction' => $metadata['role_restriction'] ?? 'public',
                'type' => $metadata['type'] ?? 'content',
                'bot_id' => $bot_id,
                'created_at' => $created_at,
                'data_source' => 'pinecone',
                'chunk_index' => isset($metadata['chunk_index']) ? intval($metadata['chunk_index']) : null,
                'total_chunks' => isset($metadata['total_chunks']) ? intval($metadata['total_chunks']) : null,
                'is_chunked' => isset($metadata['is_chunked']) ? (bool) $metadata['is_chunked'] : false
            );
        }
    }

    // Sort by created_at (newest first)
    usort($records, function($a, $b) {
        return $b->created_at - $a->created_at;
    });

    $total = count($records);
    $offset = ($page - 1) * $per_page;
    $paged_records = array_slice($records, $offset, $per_page);

    $this->mxchat_batch_fetch_role_restrictions($paged_records, $bot_id);

    return array(
        'data' => $paged_records,
        'total' => $total
    );
}

/**
 * Fetch specific vectors by their IDs and format for display
 * ADDED 2.6.1
 */
private function mxchat_fetch_vectors_by_ids_for_list($pinecone_options, $vector_ids, $page, $per_page, $bot_id, $content_type) {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
    $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

    $fetch_url = "https://{$host}/vectors/fetch";

    $fetch_data = array(
        'ids' => $vector_ids
    );

    if (!empty($namespace)) {
        $fetch_data['namespace'] = $namespace;
    }

    $response = wp_remote_post($fetch_url, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($fetch_data),
        'timeout' => 15
    ));

    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
        return array('data' => array(), 'total' => 0);
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    $records = array();
    if (isset($data['vectors'])) {
        foreach ($data['vectors'] as $vector_id => $vector_data) {
            $metadata = $vector_data['metadata'] ?? array();

            // Filter by content type if specified
            if (!empty($content_type)) {
                $record_type = $metadata['type'] ?? 'content';
                if ($record_type !== $content_type) {
                    continue;
                }
            }

            $created_at = $metadata['created_at'] ?? $metadata['last_updated'] ?? $metadata['timestamp'] ?? time();
            if (!is_numeric($created_at)) {
                $created_at = strtotime($created_at) ?: time();
            }

            $records[] = (object) array(
                'id' => $vector_id,
                'article_content' => $metadata['text'] ?? '',
                'source_url' => $metadata['source_url'] ?? '',
                'role_restriction' => $metadata['role_restriction'] ?? 'public',
                'type' => $metadata['type'] ?? 'content',
                'bot_id' => $bot_id,
                'created_at' => $created_at,
                'data_source' => 'pinecone',
                'chunk_index' => isset($metadata['chunk_index']) ? intval($metadata['chunk_index']) : null,
                'total_chunks' => isset($metadata['total_chunks']) ? intval($metadata['total_chunks']) : null,
                'is_chunked' => isset($metadata['is_chunked']) ? (bool) $metadata['is_chunked'] : false
            );
        }
    }

    // Sort by date
    usort($records, function($a, $b) {
        return $b->created_at - $a->created_at;
    });

    $total = count($records);
    $paged_records = array_slice($records, 0, $per_page);

    $this->mxchat_batch_fetch_role_restrictions($paged_records, $bot_id);

    return array(
        'data' => $paged_records,
        'total' => $total
    );
}

/**
 * Generate a single optimized query vector for fetching records
 * Uses a center-weighted approach for best coverage
 */
private function mxchat_generate_optimized_query_vector() {
    $dimensions = $this->mxchat_get_embedding_dimensions();
    $vector = array();

    // Create a normalized center-weighted vector
    $center = $dimensions / 2;
    for ($i = 0; $i < $dimensions; $i++) {
        $distance = abs($i - $center) / $center;
        $vector[] = (1 - $distance) * 0.5;
    }

    // Normalize to unit length
    $magnitude = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vector)));
    if ($magnitude > 0) {
        $vector = array_map(function($x) use ($magnitude) { return $x / $magnitude; }, $vector);
    }

    return $vector;
}

/**
 * Batch fetch role restrictions for a set of records
 * Uses a single query instead of N queries
 * ADDED 2.6.1: Prevents N+1 query problem
 */
private function mxchat_batch_fetch_role_restrictions(&$records, $bot_id = 'default') {
    if (empty($records)) {
        return;
    }

    global $wpdb;
    $roles_table = $wpdb->prefix . 'mxchat_pinecone_roles';

    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$roles_table}'");
    if (!$table_exists) {
        return;
    }

    // Get all vector IDs that need role lookup
    $vector_ids = array();
    foreach ($records as $record) {
        if (empty($record->role_restriction) || $record->role_restriction === 'public') {
            $vector_ids[] = $record->id;
        }
    }

    if (empty($vector_ids)) {
        return;
    }

    // Check if bot_id column exists
    $columns = $wpdb->get_col("SHOW COLUMNS FROM {$roles_table}");
    $has_bot_id = in_array('bot_id', $columns);

    // Build single query with IN clause
    $placeholders = implode(',', array_fill(0, count($vector_ids), '%s'));

    if ($has_bot_id) {
        $query = $wpdb->prepare(
            "SELECT vector_id, role_restriction FROM {$roles_table} WHERE vector_id IN ({$placeholders}) AND bot_id = %s",
            array_merge($vector_ids, array($bot_id))
        );
    } else {
        $query = $wpdb->prepare(
            "SELECT vector_id, role_restriction FROM {$roles_table} WHERE vector_id IN ({$placeholders})",
            $vector_ids
        );
    }

    $results = $wpdb->get_results($query, OBJECT_K);

    // Apply role restrictions to records
    foreach ($records as &$record) {
        if (isset($results[$record->id])) {
            $record->role_restriction = $results[$record->id]->role_restriction;
        }
    }
}

/**
 * Safe version of get_recent_entries with memory limits
 * ADDED 2.6.1: Prevents memory exhaustion
 */
private function mxchat_get_recent_entries_safe($pinecone_options, $bot_id = 'default', $limit = 500) {
    global $wpdb;

    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
    $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

    if (empty($api_key) || empty($host)) {
        return array();
    }

    try {
        $all_records = array();
        $seen_ids = array();
        $query_url = "https://{$host}/query";

        // Use only 2 query vectors instead of 5 for better performance
        $fixed_vectors = array_slice($this->mxchat_generate_fixed_query_vectors(), 0, 2);

        foreach ($fixed_vectors as $query_vector) {
            // Limit topK to prevent memory issues
            $topK = min(500, $limit);

            $query_data = array(
                'includeMetadata' => true,
                'includeValues' => false,
                'topK' => $topK,
                'vector' => $query_vector
            );

            if (!empty($namespace)) {
                $query_data['namespace'] = $namespace;
            }

            $response = wp_remote_post($query_url, array(
                'headers' => array(
                    'Api-Key' => $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($query_data),
                'timeout' => 15
            ));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if (isset($data['matches'])) {
                    foreach ($data['matches'] as $match) {
                        $match_id = $match['id'] ?? '';
                        if (!empty($match_id) && !isset($seen_ids[$match_id])) {
                            $metadata = $match['metadata'] ?? array();

                            $created_at = $metadata['created_at'] ?? $metadata['last_updated'] ?? $metadata['timestamp'] ?? time();
                            if (!is_numeric($created_at)) {
                                $created_at = strtotime($created_at) ?: time();
                            }

                            $all_records[] = (object) array(
                                'id' => $match_id,
                                'article_content' => $metadata['text'] ?? '',
                                'source_url' => $metadata['source_url'] ?? '',
                                'role_restriction' => $metadata['role_restriction'] ?? 'public',
                                'type' => $metadata['type'] ?? 'content',
                                'bot_id' => $bot_id,
                                'created_at' => $created_at,
                                'data_source' => 'pinecone',
                                'chunk_index' => isset($metadata['chunk_index']) ? intval($metadata['chunk_index']) : null,
                                'total_chunks' => isset($metadata['total_chunks']) ? intval($metadata['total_chunks']) : null,
                                'is_chunked' => isset($metadata['is_chunked']) ? (bool) $metadata['is_chunked'] : false
                            );

                            $seen_ids[$match_id] = true;

                            // Stop if we've reached our limit
                            if (count($all_records) >= $limit) {
                                break 2;
                            }
                        }
                    }
                }
            }

            // Minimal delay between requests
            usleep(50000); // 0.05 second delay
        }

        // Sort by created_at (newest first) and apply limit
        usort($all_records, function($a, $b) {
            return $b->created_at - $a->created_at;
        });

        $limited_records = array_slice($all_records, 0, $limit);

        // Batch fetch role restrictions
        $this->mxchat_batch_fetch_role_restrictions($limited_records, $bot_id);

        return $limited_records;

    } catch (Exception $e) {
        error_log('MxChat safe fetch exception: ' . $e->getMessage());
        MxChat_Admin::mxchat_log_debug('pinecone_error', 'Pinecone safe fetch error: ' . $e->getMessage());
        return array();
    }
}
/**
     * Get embedding dimensions based on the selected model
     * ADD THIS NEW FUNCTION
     */
    private function mxchat_get_embedding_dimensions() {
        $options = get_option('mxchat_options', array());
        $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';
        
        // Define dimensions for different models
        $model_dimensions = array(
            'text-embedding-ada-002' => 1536,
            'text-embedding-3-small' => 1536,
            'text-embedding-3-large' => 3072,
            'voyage-2' => 1024,
            'voyage-large-2' => 1536,
            'voyage-3-large' => 2048,
            'gemini-embedding-001' => 1536,
        );
        
        // Check if it's a voyage model with custom dimensions
        if (strpos($selected_model, 'voyage-3-large') === 0) {
            $custom_dimensions = $options['voyage_output_dimension'] ?? 2048;
            return intval($custom_dimensions);
        }
        
        // Check if it's a gemini model with custom dimensions
        if (strpos($selected_model, 'gemini-embedding') === 0) {
            $custom_dimensions = $options['gemini_output_dimension'] ?? 1536;
            return intval($custom_dimensions);
        }
        
        // Return known dimensions or default to 1536
        return $model_dimensions[$selected_model] ?? 1536;
    }

    /**
     * Generate random unit vector with correct dimensions
     * ADD THIS NEW FUNCTION
     */
    private function mxchat_generate_random_vector() {
        $dimensions = $this->mxchat_get_embedding_dimensions();
        
        $random_vector = array();
        for ($i = 0; $i < $dimensions; $i++) {
            $random_vector[] = (rand(-1000, 1000) / 1000.0);
        }
        
        // Normalize the vector to unit length
        $magnitude = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $random_vector)));
        if ($magnitude > 0) {
            $random_vector = array_map(function($x) use ($magnitude) { return $x / $magnitude; }, $random_vector);
        }
        
        return $random_vector;
    }


/**
 * Get recent entries from Pinecone
 * UPDATED 2.6.1: Now uses safe version with memory limits to prevent crashes
 * @deprecated Use mxchat_get_recent_entries_safe() instead for new code
 */
private function mxchat_get_recent_1k_entries($pinecone_options, $bot_id = 'default') {
    // Delegate to the safe version with a reasonable limit
    // This prevents crashes with large datasets (13K+ products)
    return $this->mxchat_get_recent_entries_safe($pinecone_options, $bot_id, 500);
}
/**
 * Generate fixed query vectors for consistent results
 */
private function mxchat_generate_fixed_query_vectors() {
    $dimensions = $this->mxchat_get_embedding_dimensions();
    $vectors = array();
    
    // Create 5 fixed vectors with different patterns for better coverage
    $patterns = array(
        'zeros_with_ones' => 0.1,      // Mostly zeros with some 1s
        'ascending' => 0.2,            // Ascending pattern
        'descending' => 0.3,           // Descending pattern  
        'alternating' => 0.4,          // Alternating positive/negative
        'center_weighted' => 0.5       // Higher values in center
    );
    
    foreach ($patterns as $pattern_name => $seed) {
        $vector = array();
        
        for ($i = 0; $i < $dimensions; $i++) {
            switch ($pattern_name) {
                case 'zeros_with_ones':
                    $vector[] = ($i % 10 === 0) ? 1.0 : 0.0;
                    break;
                case 'ascending':
                    $vector[] = ($i / $dimensions) * 2 - 1; // Range -1 to 1
                    break;
                case 'descending':
                    $vector[] = (($dimensions - $i) / $dimensions) * 2 - 1;
                    break;
                case 'alternating':
                    $vector[] = ($i % 2 === 0) ? $seed : -$seed;
                    break;
                case 'center_weighted':
                    $center = $dimensions / 2;
                    $distance = abs($i - $center) / $center;
                    $vector[] = (1 - $distance) * $seed;
                    break;
            }
        }
        
        // Normalize the vector to unit length
        $magnitude = sqrt(array_sum(array_map(function($x) { return $x * $x; }, $vector)));
        if ($magnitude > 0) {
            $vector = array_map(function($x) use ($magnitude) { return $x / $magnitude; }, $vector);
        }
        
        $vectors[] = $vector;
    }
    
    return $vectors;
}


/**
 * Scan Pinecone for processed content
 * UPDATED 2.6.2: Uses direct ID lookup via fetch API instead of random vector scanning
 * This removes the 10K record limit and scales to any database size
 *
 * @param array $pinecone_options Pinecone configuration options
 * @param array $post_ids Optional array of specific post IDs to check (if empty, checks all published posts)
 * @return array Processed data keyed by post ID
 */
public function mxchat_scan_pinecone_for_processed_content($pinecone_options, $post_ids = array()) {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';

    if (empty($api_key) || empty($host)) {
        return array();
    }

    try {
        // If no specific post IDs provided, get all published posts
        if (empty($post_ids)) {
            $posts = get_posts(array(
                'post_type' => 'any',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'no_found_rows' => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ));
            $post_ids = $posts;
        }

        if (empty($post_ids)) {
            return array();
        }

        // Build a map of vector_id => post data for lookup
        $vector_id_map = array();
        foreach ($post_ids as $post_id) {
            $permalink = get_permalink($post_id);
            if ($permalink) {
                $vector_id = md5($permalink);
                $vector_id_map[$vector_id] = array(
                    'post_id' => $post_id,
                    'url' => $permalink
                );
            }
        }

        if (empty($vector_id_map)) {
            return array();
        }

        // Batch check Pinecone using fetch API (max 1000 IDs per request)
        $all_vector_ids = array_keys($vector_id_map);
        $chunks = array_chunk($all_vector_ids, 1000);
        $processed_data = array();

        foreach ($chunks as $chunk) {
            $fetch_url = "https://{$host}/vectors/fetch";

            $response = wp_remote_post($fetch_url, array(
                'headers' => array(
                    'Api-Key' => $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array('ids' => $chunk)),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            if ($response_code !== 200) {
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Process returned vectors
            if (isset($data['vectors']) && is_array($data['vectors'])) {
                foreach ($data['vectors'] as $vector_id => $vector_data) {
                    if (isset($vector_id_map[$vector_id])) {
                        $post_info = $vector_id_map[$vector_id];
                        $post_id = $post_info['post_id'];
                        $metadata = $vector_data['metadata'] ?? array();

                        $created_at = $metadata['created_at'] ?? '';
                        $processed_date = 'Recently';
                        $timestamp = current_time('timestamp');

                        if (!empty($created_at)) {
                            $ts = is_numeric($created_at) ? $created_at : strtotime($created_at);
                            if ($ts) {
                                $timestamp = $ts;
                                $processed_date = human_time_diff($ts, current_time('timestamp')) . ' ago';
                            }
                        }

                        $processed_data[$post_id] = array(
                            'db_id' => $vector_id,
                            'processed_date' => $processed_date,
                            'url' => $post_info['url'],
                            'source' => 'pinecone',
                            'timestamp' => $timestamp
                        );
                    }
                }
            }
        }

        return $processed_data;

    } catch (Exception $e) {
        return array();
    }
}

/**
 * Get total count from Pinecone stats API
 * UPDATED: Removed cache fallback reference
 */
private function mxchat_get_pinecone_total_count($pinecone_options, $bot_id = 'default') {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
    $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';

    if (empty($api_key) || empty($host)) {
        return 0;
    }

    try {
        $stats_url = "https://{$host}/describe_index_stats";

        // describe_index_stats doesn't need a body, just the POST request
        $response = wp_remote_post($stats_url, array(
            'headers' => array(
                'Api-Key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => '{}',
            'timeout' => 15
        ));

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $body = wp_remote_retrieve_body($response);
            $stats_data = json_decode($body, true);

            // If namespace is specified, get count from that specific namespace
            // Pinecone stats response format: { namespaces: { "ns": { vectorCount: N } }, totalVectorCount: N }
            if (!empty($namespace) && isset($stats_data['namespaces'][$namespace]['vectorCount'])) {
                $namespace_count = intval($stats_data['namespaces'][$namespace]['vectorCount']);
                //error_log('DEBUG: Got namespace-specific count: ' . $namespace_count . ' for namespace: ' . $namespace);
                return $namespace_count;
            }

            // If no namespace specified or namespace not found in response, use total
            $total_count = $stats_data['totalVectorCount'] ?? 0;
            if ($total_count > 0) {
                //error_log('DEBUG: Got total count from stats API: ' . $total_count);
                return intval($total_count);
            }
        }

        // If stats API fails, return 0 instead of using cache
        return 0;

    } catch (Exception $e) {
        //error_log('DEBUG: Exception getting total count: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Get bot-specific Pinecone configuration for database operations
 */
public function mxchat_get_bot_pinecone_options($bot_id = 'default') {
    //error_log('DEBUG: Getting Pinecone options for bot: ' . $bot_id);
    
    // If default bot or multi-bot add-on not active, use default Pinecone config
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        $addon_options = get_option('mxchat_pinecone_addon_options', array());
        //error_log('DEBUG: Using default Pinecone options');
        return $addon_options;
    }
    
    // Get bot-specific configuration using the filter
    $bot_config = apply_filters('mxchat_get_bot_pinecone_config', array(), $bot_id);
    
    //error_log('DEBUG: Bot config from filter: ' . print_r($bot_config, true));
    
    // Check if we got valid bot-specific config
    if (!empty($bot_config) && isset($bot_config['use_pinecone']) && $bot_config['use_pinecone']) {
        // Convert bot config to the format expected by fetch functions
        $pinecone_options = array(
            'mxchat_use_pinecone' => '1',
            'mxchat_pinecone_api_key' => $bot_config['api_key'] ?? '',
            'mxchat_pinecone_host' => $bot_config['host'] ?? '',
            'mxchat_pinecone_namespace' => $bot_config['namespace'] ?? '',
            'mxchat_pinecone_environment' => '',
            'mxchat_pinecone_index' => ''
        );
        
        //error_log('DEBUG: Returning bot-specific Pinecone options for bot: ' . $bot_id);
        return $pinecone_options;
    }
    
    // Fallback to default options if bot-specific config is invalid
    //error_log('DEBUG: Bot-specific config invalid, falling back to default');
    return get_option('mxchat_pinecone_addon_options', array());
}

/**
 * Get bot-specific Pinecone configuration
 * Used in the knowledge retrieval functions  
 */
private function get_bot_pinecone_config($bot_id = 'default') {
    //error_log("MXCHAT DEBUG: get_bot_pinecone_config called for bot: " . $bot_id);
    
    // If default bot or multi-bot add-on not active, use default Pinecone config
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        //error_log("MXCHAT DEBUG: Using default Pinecone config (no multi-bot or bot is 'default')");
        $addon_options = get_option('mxchat_pinecone_addon_options', array());
        $config = array(
            'use_pinecone' => (isset($addon_options['mxchat_use_pinecone']) && $addon_options['mxchat_use_pinecone'] === '1'),
            'api_key' => $addon_options['mxchat_pinecone_api_key'] ?? '',
            'host' => $addon_options['mxchat_pinecone_host'] ?? '',
            'namespace' => $addon_options['mxchat_pinecone_namespace'] ?? ''
        );
        //error_log("MXCHAT DEBUG: Default config - use_pinecone: " . ($config['use_pinecone'] ? 'true' : 'false'));
        return $config;
    }
    
    //error_log("MXCHAT DEBUG: Calling filter 'mxchat_get_bot_pinecone_config' for bot: " . $bot_id);
    
    // Hook for multi-bot add-on to provide bot-specific Pinecone config
    $bot_pinecone_config = apply_filters('mxchat_get_bot_pinecone_config', array(), $bot_id);
    
    if (!empty($bot_pinecone_config)) {
        //error_log("MXCHAT DEBUG: Got bot-specific config from filter");
        //error_log("  - use_pinecone: " . (isset($bot_pinecone_config['use_pinecone']) ? ($bot_pinecone_config['use_pinecone'] ? 'true' : 'false') : 'not set'));
        //error_log("  - host: " . ($bot_pinecone_config['host'] ?? 'not set'));
        //error_log("  - namespace: " . ($bot_pinecone_config['namespace'] ?? 'not set'));
    } else {
        //error_log("MXCHAT DEBUG: Filter returned empty config!");
    }
    
    return is_array($bot_pinecone_config) ? $bot_pinecone_config : array();
}    


/**
 * Fetches vectors from Pinecone using provided IDs (for content selection feature)
 */
public function fetch_pinecone_vectors_by_ids($pinecone_options, $vector_ids) {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';

    if (empty($api_key) || empty($host) || empty($vector_ids)) {
        return array();
    }

    try {
        $fetch_url = "https://{$host}/vectors/fetch";

        // Pinecone fetch API allows fetching specific vectors by ID
        $fetch_data = array(
            'ids' => array_values($vector_ids)
        );

        $response = wp_remote_post($fetch_url, array(
            'headers' => array(
                'Api-Key' => $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($fetch_data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            return array();
        }

        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['vectors'])) {
            return array();
        }

        $processed_data = array();

        foreach ($data['vectors'] as $vector_id => $vector_data) {
            $metadata = $vector_data['metadata'] ?? array();
            $source_url = $metadata['source_url'] ?? '';

            if (!empty($source_url)) {
                $post_id = url_to_postid($source_url);
                if ($post_id) {
                    $created_at = $metadata['created_at'] ?? '';
                    $processed_date = 'Recently'; // Default

                    if (!empty($created_at)) {
                        $timestamp = is_numeric($created_at) ? $created_at : strtotime($created_at);
                        if ($timestamp) {
                            $processed_date = human_time_diff($timestamp, current_time('timestamp')) . ' ago';
                        }
                    }

                    $processed_data[$post_id] = array(
                        'db_id' => $vector_id,
                        'processed_date' => $processed_date,
                        'url' => $source_url,
                        'source' => 'pinecone',
                        'timestamp' => $timestamp ?? current_time('timestamp')
                    );
                }
            }
        }

        return $processed_data;

    } catch (Exception $e) {
        return array();
    }
}
    // ========================================
    // PINECONE DELETE OPERATIONS
    // ========================================

/**
 * Delete all vectors from Pinecone
 * Loops until all vectors are deleted (handles large databases)
 */
public function mxchat_delete_all_from_pinecone($pinecone_options) {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';

    if (empty($api_key) || empty($host)) {
        return array(
            'success' => false,
            'message' => 'Missing Pinecone API credentials'
        );
    }

    try {
        $total_deleted = 0;
        $failed_batches = 0;
        $max_iterations = 100; // Safety limit to prevent infinite loops
        $iteration = 0;

        // Loop until no more vectors are found
        do {
            $iteration++;

            // Get a batch of vector IDs from Pinecone
            $records = $this->mxchat_get_recent_1k_entries($pinecone_options);
            $vector_ids = array();

            foreach ($records as $record) {
                if (!empty($record->id)) {
                    $vector_ids[] = $record->id;
                }
            }

            // If no vectors found, we're done
            if (empty($vector_ids)) {
                break;
            }

            // Delete vectors in batches (Pinecone has limits on batch operations)
            $batch_size = 100;
            $batches = array_chunk($vector_ids, $batch_size);

            foreach ($batches as $batch) {
                $result = $this->mxchat_delete_pinecone_batch($batch, $api_key, $host);
                if ($result['success']) {
                    $total_deleted += count($batch);
                } else {
                    $failed_batches++;
                }
            }

            // Small delay to avoid rate limiting
            usleep(100000); // 100ms

        } while ($iteration < $max_iterations);

        if ($total_deleted === 0) {
            return array(
                'success' => true,
                'message' => 'No vectors found to delete'
            );
        }

        if ($failed_batches > 0) {
            return array(
                'success' => false,
                'message' => sprintf('Deleted %d vectors, but %d batches failed', $total_deleted, $failed_batches)
            );
        }

        return array(
            'success' => true,
            'message' => "Successfully deleted {$total_deleted} vectors from Pinecone"
        );

    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => $e->getMessage()
        );
    }
}

    /**
     * Deletes batch of vectors from Pinecone database
     */
     private function mxchat_delete_pinecone_batch($vector_ids, $api_key, $host) {
         // Build the API endpoint
         $api_endpoint = "https://{$host}/vectors/delete";

         // Prepare the request body with the IDs
         $request_body = array(
             'ids' => $vector_ids
         );

         // Make the deletion request
         $response = wp_remote_post($api_endpoint, array(
             'headers' => array(
                 'Api-Key' => $api_key,
                 'accept' => 'application/json',
                 'content-type' => 'application/json'
             ),
             'body' => wp_json_encode($request_body),
             'timeout' => 60, // Increased timeout for batch operations
             'method' => 'POST'
         ));

         // Handle WordPress HTTP API errors
         if (is_wp_error($response)) {
             MxChat_Admin::mxchat_log_debug('pinecone_error', 'Pinecone batch deletion failed: ' . $response->get_error_message());
             return array(
                 'success' => false,
                 'message' => $response->get_error_message()
             );
         }

         // Check response status
         $response_code = wp_remote_retrieve_response_code($response);
         $response_body = wp_remote_retrieve_body($response);

         // Pinecone returns 200 for successful deletion
         if ($response_code !== 200) {
             MxChat_Admin::mxchat_log_debug('pinecone_error', 'Pinecone batch deletion failed (HTTP ' . $response_code . ')', array('response' => substr($response_body, 0, 200)));
             return array(
                 'success' => false,
                 'message' => sprintf(
                     'Pinecone API error (HTTP %d): %s',
                     $response_code,
                     $response_body
                 )
             );
         }

         return array(
             'success' => true,
             'message' => 'Batch deleted successfully from Pinecone'
         );
     }


/**
 * Deletes vector from Pinecone using API request
 */
public function mxchat_delete_from_pinecone_by_vector_id($vector_id, $api_key, $host, $namespace = '') {
    //error_log('=== PINECONE DELETE OPERATION ===');
    //error_log('Vector ID: ' . $vector_id);
    //error_log('Host: ' . $host);
    //error_log('API Key: ' . (empty($api_key) ? 'EMPTY' : 'SET'));
    
    // First, let's verify the vector exists before trying to delete
    $fetch_url = "https://{$host}/vectors/fetch";
    
    $fetch_params = array(
        'ids' => array($vector_id)
    );
    
    // Add namespace if provided (though you said you're not using namespaces)
    if (!empty($namespace)) {
        $fetch_params['namespace'] = $namespace;
    }
    
    // Construct URL with query parameters for GET request
    $fetch_url_with_params = $fetch_url . '?' . http_build_query($fetch_params);
    
    $fetch_response = wp_remote_get($fetch_url_with_params, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'accept' => 'application/json'
        ),
        'timeout' => 15
    ));
    
    if (!is_wp_error($fetch_response) && wp_remote_retrieve_response_code($fetch_response) === 200) {
        $fetch_body = wp_remote_retrieve_body($fetch_response);
        $fetch_data = json_decode($fetch_body, true);
        
        //error_log('DEBUG: Fetch response: ' . print_r($fetch_data, true));
        
        if (isset($fetch_data['vectors']) && isset($fetch_data['vectors'][$vector_id])) {
            //error_log('DEBUG: Vector EXISTS in this index before deletion');
        } else {
            //error_log('WARNING: Vector NOT FOUND in this index! It may be in a different bot\'s index');
            // You might want to return an error here
        }
    } else {
        //error_log('DEBUG: Could not fetch vector to verify existence');
    }
    
    // Now proceed with deletion
    $api_endpoint = "https://{$host}/vectors/delete";
    
    // Prepare the request body with the ID
    $request_body = array(
        'ids' => array($vector_id)
    );
    
    // Add namespace if provided
    if (!empty($namespace)) {
        $request_body['namespace'] = $namespace;
    }
    
    //error_log('DEBUG: Delete request body: ' . json_encode($request_body));
    //error_log('DEBUG: Delete endpoint: ' . $api_endpoint);

    // Make the deletion request
    $response = wp_remote_post($api_endpoint, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'accept' => 'application/json',
            'content-type' => 'application/json'
        ),
        'body' => wp_json_encode($request_body),
        'timeout' => 30
    ));

    // Handle WordPress HTTP API errors
    if (is_wp_error($response)) {
        //error_log('DEBUG: WP Error: ' . $response->get_error_message());
        return array(
            'success' => false,
            'message' => $response->get_error_message()
        );
    }

    // Check response status
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    
    //error_log('DEBUG: Delete response code: ' . $response_code);
    //error_log('DEBUG: Delete response body: ' . $response_body);

    // Pinecone returns 200 for successful deletion (even if vector didn't exist)
    if ($response_code !== 200) {
        //error_log('DEBUG: Non-200 response from Pinecone');
        return array(
            'success' => false,
            'message' => sprintf(
                'Pinecone API error (HTTP %d): %s',
                $response_code,
                $response_body
            )
        );
    }
    
    // After deletion, verify it's actually gone
    sleep(1); // Give Pinecone a moment to process
    
    $verify_response = wp_remote_get($fetch_url_with_params, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'accept' => 'application/json'
        ),
        'timeout' => 15
    ));
    
    if (!is_wp_error($verify_response) && wp_remote_retrieve_response_code($verify_response) === 200) {
        $verify_body = wp_remote_retrieve_body($verify_response);
        $verify_data = json_decode($verify_body, true);
        
        if (isset($verify_data['vectors']) && isset($verify_data['vectors'][$vector_id])) {
            //error_log('ERROR: Vector STILL EXISTS after deletion attempt!');
            return array(
                'success' => false,
                'message' => 'Vector still exists after deletion attempt'
            );
        } else {
            //error_log('SUCCESS: Vector confirmed deleted (or never existed)');
        }
    }
    
    //error_log('=== END PINECONE DELETE OPERATION ===');

    return array(
        'success' => true,
        'message' => 'Vector deleted successfully from Pinecone'
    );
}

    /**
     * Deletes data from Pinecone using a source URL
     */
     public function mxchat_delete_from_pinecone_by_url($source_url, $pinecone_options) {
         $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
         $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';

         if (empty($host) || empty($api_key)) {
             //error_log('MXChat: Pinecone deletion failed - missing configuration');
             return false;
         }

         $api_endpoint = "https://{$host}/vectors/delete";
         $vector_id = md5($source_url);

         $request_body = array(
             'ids' => array($vector_id)
         );

         $response = wp_remote_post($api_endpoint, array(
             'headers' => array(
                 'Api-Key' => $api_key,
                 'accept' => 'application/json',
                 'content-type' => 'application/json'
             ),
             'body' => wp_json_encode($request_body),
             'timeout' => 30
         ));

         if (is_wp_error($response)) {
             //error_log('MXChat: Pinecone deletion error - ' . $response->get_error_message());
             return false;
         }

         $response_code = wp_remote_retrieve_response_code($response);
         if ($response_code !== 200) {
             //error_log('MXChat: Pinecone deletion failed with status ' . $response_code);
             return false;
         }

         return true;
     }


    /**
     * Deletes data from Pinecone index using API key
     */
     private function mxchat_delete_from_pinecone($urls, $api_key, $environment, $index_name) {
         // Get the Pinecone host from options (matching your store_in_pinecone_main pattern)
         $options = get_option('mxchat_pinecone_addon_options');
         $host = $options['mxchat_pinecone_host'] ?? '';

         if (empty($host)) {
             return array(
                 'success' => false,
                 'message' => 'Pinecone host is not configured. Please set the host in your settings.'
             );
         }

         // Build API endpoint using the configured host
         $api_endpoint = "https://{$host}/vectors/delete";

         // Create vector IDs from URLs (matching your store method's ID generation)
         $vector_ids = array_map('md5', $urls);

         // Prepare the delete request body
         $request_body = array(
             'ids' => $vector_ids,
             'filter' => array(
                 'source_url' => array(
                     '$in' => $urls
                 )
             )
         );

         // Make the deletion request
         $response = wp_remote_post($api_endpoint, array(
             'headers' => array(
                 'Api-Key' => $api_key,
                 'accept' => 'application/json',
                 'content-type' => 'application/json'
             ),
             'body' => wp_json_encode($request_body),
             'timeout' => 30,
             'data_format' => 'body'
         ));

         // Handle WordPress HTTP API errors
         if (is_wp_error($response)) {
             return array(
                 'success' => false,
                 'message' => $response->get_error_message()
             );
         }

         // Check response status
         $response_code = wp_remote_retrieve_response_code($response);
         if ($response_code !== 200) {
             $body = wp_remote_retrieve_body($response);
             return array(
                 'success' => false,
                 'message' => sprintf(
                     'Pinecone API error (HTTP %d): %s',
                     $response_code,
                     $body
                 )
             );
         }

         // Parse response body
         $body = wp_remote_retrieve_body($response);
         $response_data = json_decode($body, true);

         // Final validation of the response
         if (json_last_error() !== JSON_ERROR_NONE) {
             return array(
                 'success' => false,
                 'message' => 'Failed to parse Pinecone response: ' . json_last_error_msg()
             );
         }

         return array(
             'success' => true,
             'message' => sprintf('Successfully deleted %d vectors from Pinecone', count($vector_ids))
         );
     }



/**
 * Retrieves processed content from Pinecone API
 *
 * @param array $pinecone_options Pinecone configuration options
 * @param array $post_ids Optional array of specific post IDs to check (if empty, checks all)
 * @return array Processed data keyed by post ID
 */
public function mxchat_get_pinecone_processed_content($pinecone_options, $post_ids = array()) {
    $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
    $host = $pinecone_options['mxchat_pinecone_host'] ?? '';

    if (empty($api_key) || empty($host)) {
        return array();
    }

    $pinecone_data = array();

    try {
        // Always get fresh data from Pinecone
        $pinecone_data = $this->mxchat_scan_pinecone_for_processed_content($pinecone_options, $post_ids);

        // Method 2: Final fallback - try stats endpoint (if available)
        if (empty($pinecone_data)) {
            $stats_url = "https://{$host}/describe_index_stats";

            $response = wp_remote_post($stats_url, array(
                'headers' => array(
                    'Api-Key' => $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode(array()),
                'timeout' => 30
            ));

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $stats_data = json_decode($body, true);
            }
        }

    } catch (Exception $e) {
        // Log error but don't return cached data
    }

    return $pinecone_data;
}
    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Validates Pinecone API credentials
     */
    private function mxchat_validate_pinecone_credentials($api_key, $host) {
        if (empty($api_key) || empty($host)) {
            return false;
        }
        return true;
    }

    /**
     * Get Pinecone API credentials from options
     */
    private function mxchat_get_pinecone_credentials() {
        $options = get_option('mxchat_options', array());
        return array(
            'api_key' => isset($options['pinecone_api_key']) ? $options['pinecone_api_key'] : '',
            'host' => isset($options['pinecone_host']) ? $options['pinecone_host'] : ''
        );
    }

    /**
     * Log Pinecone operation errors
     */
    private function log_pinecone_error($operation, $error_message) {
        //error_log("MxChat Pinecone {$operation} Error: " . $error_message);
    }

    // ========================================
    // STATIC ACCESS METHODS (for backward compatibility)
    // ========================================

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        static $instance = null;
        if ($instance === null) {
            $instance = new self();
        }
        return $instance;
    }
}

// Initialize the Pinecone manager
$mxchat_pinecone_manager = MxChat_Pinecone_Manager::get_instance();
