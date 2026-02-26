<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class MxChat_Utils {

/**
 * UPDATED: Submit or update content (and its embedding) in the database.
 * Stores in Pinecone if enabled, otherwise stores in WordPress DB.
 *
 * @param string $content      The content to be embedded.
 * @param string $source_url   The source URL of the content.
 * @param string $api_key      The API key used for generating embeddings.
 * @param string $vector_id    Optional vector ID for Pinecone (if not provided, will use md5 of URL)
 * @param string $bot_id       The bot ID for multi-bot support
 * @param string $content_type The type of content (post, page, pdf, url, manual, product, etc.)
 * @return bool|WP_Error True on success, WP_Error on failure
 */
public static function submit_content_to_db($content, $source_url, $api_key, $vector_id = null, $bot_id = 'default', $content_type = 'content') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

    //error_log('[MXCHAT-DB] Starting database submission for URL: ' . $source_url . ' (Bot: ' . $bot_id . ', Type: ' . $content_type . ')');
    //error_log('[MXCHAT-DB] Content length: ' . strlen($content) . ' bytes');

    // Sanitize the source URL
    $source_url = esc_url_raw($source_url);

    // Sanitize content_type
    $content_type = sanitize_key($content_type);
    if (empty($content_type)) {
        $content_type = 'content'; // Fallback for backwards compatibility
    }

    // Just ensure UTF-8 validity without aggressive escaping
    $safe_content = wp_check_invalid_utf8($content);
    // Remove only null bytes and other control characters, but preserve newlines (\n = \x0A) and carriage returns (\r = \x0D)
    $safe_content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $safe_content);

    // Check if chunking should be applied
    $chunker = MxChat_Chunker::from_settings();
    if ($chunker->should_chunk($safe_content)) {
        //error_log('[MXCHAT-DB] Content exceeds chunk threshold, using chunked submission');
        return self::submit_chunked_content($safe_content, $source_url, $api_key, $bot_id, $content_type, $chunker);
    }

    // UPDATED: Generate the embedding using bot-specific configuration
    $embedding_vector = self::generate_embedding($content, $api_key, $bot_id);

    if (!is_array($embedding_vector)) {
        //error_log('[MXCHAT-DB] Error: Embedding generation failed');
        return new WP_Error('embedding_failed', 'Failed to generate embedding for content');
    }

    //error_log('[MXCHAT-DB] Embedding generated successfully');

    // UPDATED: Check if Pinecone is enabled for this specific bot
    if (self::is_pinecone_enabled_for_bot($bot_id)) {
        //error_log('[MXCHAT-DB] Pinecone is enabled for bot ' . $bot_id . ' - using Pinecone storage');
        // Store in Pinecone only
        return self::store_in_pinecone_only($embedding_vector, $content, $source_url, $vector_id, $bot_id, $content_type);
    } else {
        //error_log('[MXCHAT-DB] Pinecone not enabled for bot ' . $bot_id . ' - using WordPress storage');
        // Store in WordPress database only
        $embedding_vector_serialized = maybe_serialize($embedding_vector);
        return self::store_in_wordpress_db($safe_content, $source_url, $embedding_vector_serialized, $table_name, $content_type);
    }
}

/**
 * UPDATED: Check if Pinecone is enabled and properly configured for a specific bot
 */
private static function is_pinecone_enabled_for_bot($bot_id = 'default') {
    // For default bot or when multi-bot is not active, use original method
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        return self::is_pinecone_enabled();
    }
    
    // Get bot-specific Pinecone configuration
    $bot_pinecone_config = apply_filters('mxchat_get_bot_pinecone_config', array(), $bot_id);
    
    if (empty($bot_pinecone_config)) {
        // Fallback to default configuration
        return self::is_pinecone_enabled();
    }
    
    $enabled_check = !empty($bot_pinecone_config['use_pinecone']) && $bot_pinecone_config['use_pinecone'];
    $api_key_check = !empty($bot_pinecone_config['api_key']);
    $host_check = !empty($bot_pinecone_config['host']);
    
    return $enabled_check && $api_key_check && $host_check;
}

/**
 * Check if Pinecone is enabled and properly configured (original method for default bot)
 */
private static function is_pinecone_enabled() {
    $pinecone_options = get_option('mxchat_pinecone_addon_options');
    
    if (empty($pinecone_options)) {
        return false;
    }
    
    $enabled_check = !empty($pinecone_options['mxchat_use_pinecone']) && $pinecone_options['mxchat_use_pinecone'] !== '0';
    $api_key_check = !empty($pinecone_options['mxchat_pinecone_api_key']);
    $host_check = !empty($pinecone_options['mxchat_pinecone_host']);
    
    return $enabled_check && $api_key_check && $host_check;
}

/**
 * UPDATED: Store content in Pinecone only with bot support
 */
private static function store_in_pinecone_only($embedding_vector, $content, $source_url, $vector_id = null, $bot_id = 'default', $content_type = 'content') {
    //error_log('[MXCHAT-PINECONE] ===== Using Pinecone-only storage for bot ' . $bot_id . ' =====');

    // Get bot-specific Pinecone configuration
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        $pinecone_options = get_option('mxchat_pinecone_addon_options');
        $api_key = $pinecone_options['mxchat_pinecone_api_key'];
        $environment = $pinecone_options['mxchat_pinecone_environment'] ?? '';
        $index_name = $pinecone_options['mxchat_pinecone_index'] ?? '';
        $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';
    } else {
        $bot_pinecone_config = apply_filters('mxchat_get_bot_pinecone_config', array(), $bot_id);
        if (empty($bot_pinecone_config)) {
            // Fallback to default configuration
            $pinecone_options = get_option('mxchat_pinecone_addon_options');
            $api_key = $pinecone_options['mxchat_pinecone_api_key'];
            $environment = $pinecone_options['mxchat_pinecone_environment'] ?? '';
            $index_name = $pinecone_options['mxchat_pinecone_index'] ?? '';
            $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';
        } else {
            $api_key = $bot_pinecone_config['api_key'];
            $environment = ''; // Not used in new Pinecone API
            $index_name = ''; // Not used in new Pinecone API
            $namespace = $bot_pinecone_config['namespace'] ?? '';
        }
    }

    $result = self::store_in_pinecone_main(
        $embedding_vector,
        $content,
        $source_url,
        $api_key,
        $environment,
        $index_name,
        $vector_id,
        $bot_id,
        $namespace,
        $content_type
    );
    
    if (is_wp_error($result)) {
        //error_log('[MXCHAT-PINECONE] Pinecone storage failed for bot ' . $bot_id . ': ' . $result->get_error_message());
        return $result;
    }
    
    //error_log('[MXCHAT-PINECONE] Pinecone storage completed successfully for bot ' . $bot_id);
    return true;
}

/**
 * Store content in WordPress database with progressive fallback
 * UPDATED 2.5.6: Now includes content_type parameter
 */
private static function store_in_wordpress_db($safe_content, $source_url, $embedding_vector_serialized, $table_name, $content_type = 'content') {
    global $wpdb;

    //error_log('[MXCHAT-DB] ===== Using WordPress-only storage =====');

    // Sanitize content_type
    $content_type = sanitize_key($content_type);
    if (empty($content_type)) {
        $content_type = 'content'; // Fallback for backwards compatibility
    }

    // ===== FIXED: Generate unique identifier for manual content =====
    $original_source_url = $source_url;
    $is_manual_content = empty($source_url) || $source_url === '' || !filter_var($source_url, FILTER_VALIDATE_URL);

    if ($is_manual_content) {
        // Generate unique identifier for manual content to prevent overwrites
        $source_url = 'mxchat://manual-content/' . time() . '-' . wp_generate_password(8, false);
        //error_log('[MXCHAT-DB] Generated unique ID for manual content: ' . $source_url);
    }

    // Only check for duplicates if we have a valid source URL (not manual content)
    $existing_id = null;
    if (!$is_manual_content) {
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE source_url = %s LIMIT 1",
                $source_url
            )
        );
        //error_log('[MXCHAT-DB] Checked for existing URL, found ID: ' . ($existing_id ?: 'none'));
    } else {
        //error_log('[MXCHAT-DB] Manual content - will create new entry (no duplicate check)');
    }
    // ===== END FIX =====

    // Progressive fallback mechanism for problematic content
    $attempt = 1;
    $max_attempts = 3;
    $current_content = $safe_content;
    $result = false;

    while ($attempt <= $max_attempts && $result === false) {
        try {
            if ($existing_id) {
                //error_log('[MXCHAT-DB] Found existing entry (ID: ' . $existing_id . '). Updating... (Attempt ' . $attempt . ')');

                // Update the existing row - UPDATED 2.5.6: Added content_type
                $result = $wpdb->update(
                    $table_name,
                    array(
                        'url'              => $source_url,
                        'article_content'  => $current_content,
                        'embedding_vector' => $embedding_vector_serialized,
                        'source_url'       => $source_url,
                        'content_type'     => $content_type,
                        'timestamp'        => current_time('mysql'),
                    ),
                    array('id' => $existing_id),
                    array('%s','%s','%s','%s','%s','%s'),
                    array('%d')
                );
            } else {
                //error_log('[MXCHAT-DB] No existing entry found. Inserting new row... (Attempt ' . $attempt . ')');
                //error_log('[MXCHAT-DB] Content sample: ' . substr($current_content, 0, 1000));

                // Insert a new row - UPDATED 2.5.6: Added content_type
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'url'              => $source_url, // Now unique for manual content
                        'article_content'  => $current_content,
                        'embedding_vector' => $embedding_vector_serialized,
                        'source_url'       => $source_url, // Now unique for manual content
                        'content_type'     => $content_type,
                        'timestamp'        => current_time('mysql'),
                    ),
                    array('%s','%s','%s','%s','%s','%s')
                );
            }
            
        if ($result === false) {
            error_log('[MXCHAT-DB] Database operation failed (Attempt ' . $attempt . ')');
            error_log('[MXCHAT-DB] MySQL Error: ' . $wpdb->last_error);
            error_log('[MXCHAT-DB] MySQL Error Number: ' . $wpdb->last_errno);
            error_log('[MXCHAT-DB] Last Query: ' . substr($wpdb->last_query, 0, 500));
            error_log('[MXCHAT-DB] Content length: ' . strlen($current_content) . ' bytes');
            error_log('[MXCHAT-DB] Embedding vector length: ' . strlen($embedding_vector_serialized) . ' bytes');
                
                // Progressively apply more aggressive sanitization on failure
                if ($attempt === 1) {
                    // First fallback: Use a more aggressive character filter and shorten
                    $current_content = preg_replace('/[^\p{L}\p{N}\s.,;:!?()-]/u', '', $current_content);
                    $current_content = substr($current_content, 0, 50000);
                } else if ($attempt === 2) {
                    // Second fallback: Keep only alphanumeric and basic punctuation, shorten further
                    $current_content = preg_replace('/[^a-zA-Z0-9\s.,;:!?()-]/u', '', $current_content);
                    $current_content = substr($current_content, 0, 30000);
                }
                
                $attempt++;
            }
        } catch (Exception $e) {
            //error_log('[MXCHAT-DB] Exception during database operation: ' . $e->getMessage());
            $attempt++;
        }
    }
    
if ($result === false) {
    error_log('[MXCHAT-DB] All database operation attempts failed');
    error_log('[MXCHAT-DB] Final MySQL Error: ' . $wpdb->last_error);
    
    $detailed_error = sprintf(
        'Failed to store content in WordPress database after %d attempts. MySQL Error: %s (Error #%d). Content size: %d bytes, Embedding size: %d bytes',
        $max_attempts,
        $wpdb->last_error,
        $wpdb->last_errno,
        strlen($current_content),
        strlen($embedding_vector_serialized)
    );
    
    return new WP_Error('database_failed', $detailed_error);
}
    
    //error_log('[MXCHAT-DB] WordPress database operation completed successfully (Attempt ' . ($attempt - 1) . ')');
    return true;
}

/**
 * UPDATED: Store content in Pinecone database with bot support
 * UPDATED 2.5.6: Now accepts content_type parameter
 */
private static function store_in_pinecone_main($embedding_vector, $content, $url, $api_key, $environment, $index_name, $vector_id = null, $bot_id = 'default', $namespace = '', $content_type = 'content') {
    //error_log('[MXCHAT-PINECONE-MAIN] ===== Starting Pinecone storage for bot ' . $bot_id . ' =====');

    // ===== UPDATED: Handle manual content with unique vector IDs =====
    if ($vector_id) {
        // Use provided vector ID
        //error_log('[MXCHAT-PINECONE-MAIN] Using provided vector ID: ' . $vector_id);
    } elseif (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
        // For valid URLs, use URL-based ID (existing behavior)
        $vector_id = md5($url);
        //error_log('[MXCHAT-PINECONE-MAIN] Generated vector ID from URL: ' . $vector_id);
    } else {
        // For manual content (empty/invalid URL), generate unique ID
        $vector_id = 'manual_' . time() . '_' . substr(md5($content . microtime(true)), 0, 8);
        //error_log('[MXCHAT-PINECONE-MAIN] Generated unique vector ID for manual content: ' . $vector_id);
    }
    // ===== END UPDATE =====

    // Get host from bot-specific config or fallback to default
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        $options = get_option('mxchat_pinecone_addon_options');
        $host = $options['mxchat_pinecone_host'] ?? '';
    } else {
        $bot_pinecone_config = apply_filters('mxchat_get_bot_pinecone_config', array(), $bot_id);
        if (!empty($bot_pinecone_config)) {
            $host = $bot_pinecone_config['host'] ?? '';
        } else {
            $options = get_option('mxchat_pinecone_addon_options');
            $host = $options['mxchat_pinecone_host'] ?? '';
        }
    }

    //error_log('[MXCHAT-PINECONE-MAIN] Host: ' . $host);
    //error_log('[MXCHAT-PINECONE-MAIN] API key length: ' . strlen($api_key));
    //error_log('[MXCHAT-PINECONE-MAIN] Bot ID: ' . $bot_id);
    //error_log('[MXCHAT-PINECONE-MAIN] Namespace: ' . $namespace);

    if (empty($host)) {
        //error_log('[MXCHAT-PINECONE-MAIN] ERROR: Host is empty');
        return new WP_Error('pinecone_config', 'Pinecone host is not configured. Please set the host in your bot settings.');
    }

    // ===== UPDATED 2.5.6: Use passed content_type or determine from URL if not provided =====
    // Sanitize content_type
    $content_type = sanitize_key($content_type);
    if (empty($content_type)) {
        // Fallback to old detection logic for backwards compatibility
        $is_product = false;
        $content_type = 'manual'; // Default for manual content

        if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            $is_product = (strpos($url, '/product/') !== false || strpos($url, '/shop/') !== false);
            $content_type = $is_product ? 'product' : 'content';
        }
    }

    //error_log('[MXCHAT-PINECONE-MAIN] Content type: ' . $content_type);
    // ===== END UPDATE =====

    $api_endpoint = "https://{$host}/vectors/upsert";
    //error_log('[MXCHAT-PINECONE-MAIN] API endpoint: ' . $api_endpoint);

    // UPDATED 2.5.6: Use provided content_type in metadata
    $metadata = array(
        'text' => $content,
        'source_url' => $url, // Can be empty for manual content
        'type' => $content_type, // Now supports: post, page, pdf, url, manual, product, etc.
        'last_updated' => time(),
        'created_at' => time(), // Add creation timestamp
        'bot_id' => $bot_id // Add bot identification
    );
    
    $vector_data = array(
        'id' => $vector_id,
        'values' => $embedding_vector,
        'metadata' => $metadata
    );
    
    $request_body = array(
        'vectors' => array($vector_data)
    );
    
    // Add namespace if specified for multi-bot separation
    if (!empty($namespace)) {
        $request_body['namespace'] = $namespace;
        //error_log('[MXCHAT-PINECONE-MAIN] Using namespace: ' . $namespace);
    }
    
    //error_log('[MXCHAT-PINECONE-MAIN] Request body prepared (embedding dimensions: ' . count($embedding_vector) . ')');

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

    if (is_wp_error($response)) {
        //error_log('[MXCHAT-PINECONE-MAIN] WordPress request error: ' . $response->get_error_message());
        return new WP_Error('pinecone_request', $response->get_error_message());
    }

    $response_code = wp_remote_retrieve_response_code($response);
    //error_log('[MXCHAT-PINECONE-MAIN] Response code: ' . $response_code);
    
    if ($response_code !== 200) {
        $body = wp_remote_retrieve_body($response);
        //error_log('[MXCHAT-PINECONE-MAIN] API error - Response body: ' . $body);
        return new WP_Error('pinecone_api', sprintf(
            'Pinecone API error (HTTP %d): %s',
            $response_code,
            $body
        ));
    }

    $response_body = wp_remote_retrieve_body($response);
    //error_log('[MXCHAT-PINECONE-MAIN] Success response: ' . $response_body);
    //error_log('[MXCHAT-PINECONE-MAIN] Successfully stored in Pinecone for bot ' . $bot_id);
    //error_log('[MXCHAT-PINECONE-MAIN] ===== Pinecone storage complete =====');
    
    return true;
}

/**
 * UPDATED: Generate an embedding for the given text using bot-specific configuration.
 *
 * @param string $text    The text to be embedded.
 * @param string $api_key The API key used for generating embeddings.
 * @param string $bot_id  The bot ID for multi-bot support
 * @return array|null     The embedding vector or null on failure.
 */
private static function generate_embedding($text, $api_key, $bot_id = 'default') {
    // Get bot-specific options
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        $options = get_option('mxchat_options');
    } else {
        $bot_options = apply_filters('mxchat_get_bot_options', array(), $bot_id);
        $options = !empty($bot_options) ? $bot_options : get_option('mxchat_options');
    }
    
    $selected_model = $options['embedding_model'] ?? 'text-embedding-ada-002';
    
    // Determine endpoint and API key based on model
    if (strpos($selected_model, 'voyage') === 0) {
        $endpoint = 'https://api.voyageai.com/v1/embeddings';
        $api_key = $options['voyage_api_key'] ?? '';
    } elseif (strpos($selected_model, 'gemini-embedding') === 0) {
        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/' . $selected_model . ':embedContent';
        $api_key = $options['gemini_api_key'] ?? '';
    } else {
        $endpoint = 'https://api.openai.com/v1/embeddings';
        // Use the bot-specific API key or fallback to passed API key
        $api_key = $options['api_key'] ?? $api_key;
    }
    
    // Prepare request body based on provider
    if (strpos($selected_model, 'gemini-embedding') === 0) {
        // Gemini API format
        $request_body = [
            'model' => 'models/' . $selected_model,
            'content' => [
                'parts' => [
                    ['text' => $text]
                ]
            ],
            'outputDimensionality' => 1536
        ];
        
        // Prepare headers for Gemini (API key as query parameter)
        $endpoint .= '?key=' . $api_key;
        $headers = [
            'Content-Type' => 'application/json'
        ];
    } else {
        // OpenAI/Voyage API format
        $request_body = [
            'input' => $text,
            'model' => $selected_model
        ];
        
        // Add output_dimension for voyage-3-large
        if ($selected_model === 'voyage-3-large') {
            $request_body['output_dimension'] = 2048;
        }
        
        // Prepare headers for OpenAI/Voyage
        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ];
    }
    
    $args = [
        'body'        => wp_json_encode($request_body),
        'headers'     => $headers,
        'timeout'     => 60,
        'redirection' => 5,
        'blocking'    => true,
        'httpversion' => '1.0',
        'sslverify'   => true,
    ];
    
    $response = wp_remote_post($endpoint, $args);
    
    if (is_wp_error($response)) {
        //error_log('Error generating embedding for bot ' . $bot_id . ': ' . $response->get_error_message());
        return null;
    }
    
    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
    // Handle different response formats based on provider
    if (strpos($selected_model, 'gemini-embedding') === 0) {
        // Gemini API response format
        if (isset($response_body['embedding']['values']) && is_array($response_body['embedding']['values'])) {
            return $response_body['embedding']['values'];
        } else {
            //error_log('Invalid response received from Gemini embedding API for bot ' . $bot_id . ': ' . wp_json_encode($response_body));
            return null;
        }
    } else {
        // OpenAI/Voyage API response format
        if (isset($response_body['data'][0]['embedding']) && is_array($response_body['data'][0]['embedding'])) {
            return $response_body['data'][0]['embedding'];
        } else {
            //error_log('Invalid response received from embedding API for bot ' . $bot_id . ': ' . wp_json_encode($response_body));
            return null;
        }
    }
}

/**
 * Submit content as multiple chunks
 *
 * Splits large content into chunks, generates embeddings for each,
 * and stores them with chunk metadata for later reassembly.
 *
 * @param string $content The content to chunk and store
 * @param string $source_url The source URL
 * @param string $api_key The API key for embeddings
 * @param string $bot_id The bot ID
 * @param string $content_type The content type
 * @param MxChat_Chunker $chunker The chunker instance
 * @return bool|WP_Error True on success, WP_Error on failure
 */
private static function submit_chunked_content($content, $source_url, $api_key, $bot_id, $content_type, $chunker) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

    error_log('[MXCHAT-CHUNK-DEBUG] Starting chunked submission for: ' . $source_url);
    error_log('[MXCHAT-CHUNK-DEBUG] Content length: ' . strlen($content) . ' chars');

    // First, delete any existing chunks for this URL (clean slate)
    $delete_result = self::delete_chunks_for_url($source_url, $bot_id);
    if (is_wp_error($delete_result)) {
        error_log('[MXCHAT-CHUNK-DEBUG] Warning: Failed to delete existing chunks: ' . $delete_result->get_error_message());
        // Continue anyway - we'll overwrite with upsert
    }

    // Split content into chunks
    $chunks = $chunker->chunk_text($content);
    $total_chunks = count($chunks);

    error_log('[MXCHAT-CHUNK-DEBUG] Created ' . $total_chunks . ' chunks');
    foreach ($chunks as $i => $chunk) {
        error_log('[MXCHAT-CHUNK-DEBUG] Chunk ' . $i . ' length: ' . strlen($chunk) . ' chars, preview: ' . substr($chunk, 0, 100));
    }

    //error_log('[MXCHAT-CHUNK] Split content into ' . $total_chunks . ' chunks');

    if ($total_chunks === 0) {
        return new WP_Error('chunking_failed', 'Content could not be split into chunks');
    }

    $errors = array();
    $is_pinecone = self::is_pinecone_enabled_for_bot($bot_id);

    foreach ($chunks as $index => $chunk_text) {
        // Generate chunk metadata
        $chunk_metadata = MxChat_Chunker::create_chunk_metadata($index, $total_chunks, $source_url);
        $chunk_vector_id = MxChat_Chunker::generate_chunk_vector_id($source_url, $index);

        //error_log('[MXCHAT-CHUNK] Processing chunk ' . ($index + 1) . '/' . $total_chunks . ' (ID: ' . $chunk_vector_id . ')');

        // Generate embedding for this chunk
        $embedding_vector = self::generate_embedding($chunk_text, $api_key, $bot_id);

        if (!is_array($embedding_vector)) {
            $errors[] = new WP_Error('embedding_failed', 'Failed to generate embedding for chunk ' . $index);
            //error_log('[MXCHAT-CHUNK] Failed to generate embedding for chunk ' . $index);
            continue;
        }

        if ($is_pinecone) {
            // Store in Pinecone with chunk metadata
            $result = self::store_chunk_in_pinecone(
                $embedding_vector,
                $chunk_text,
                $source_url,
                $chunk_vector_id,
                $bot_id,
                $content_type,
                $chunk_metadata
            );
        } else {
            // Store in WordPress DB with chunk metadata
            $content_with_metadata = MxChat_Chunker::format_chunk_for_storage($chunk_text, $chunk_metadata);
            $embedding_vector_serialized = maybe_serialize($embedding_vector);

            $result = self::store_chunk_in_wordpress_db(
                $content_with_metadata,
                $source_url,
                $embedding_vector_serialized,
                $table_name,
                $content_type,
                $chunk_metadata
            );
        }

        if (is_wp_error($result)) {
            $errors[] = $result;
            //error_log('[MXCHAT-CHUNK] Failed to store chunk ' . $index . ': ' . $result->get_error_message());
        }
    }

    if (count($errors) === $total_chunks) {
        return new WP_Error('chunking_failed', 'Failed to store any chunks');
    }

    if (!empty($errors)) {
        //error_log('[MXCHAT-CHUNK] Completed with ' . count($errors) . ' errors out of ' . $total_chunks . ' chunks');
        return new WP_Error('chunking_partial_failure',
            sprintf('Failed to store %d of %d chunks', count($errors), $total_chunks));
    }

    //error_log('[MXCHAT-CHUNK] Successfully stored all ' . $total_chunks . ' chunks');
    return true;
}

/**
 * Store a single chunk in Pinecone with chunk-specific metadata
 */
private static function store_chunk_in_pinecone($embedding_vector, $chunk_text, $source_url, $vector_id, $bot_id, $content_type, $chunk_metadata) {
    // Get Pinecone configuration
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        $pinecone_options = get_option('mxchat_pinecone_addon_options');
        $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
        $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
        $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';
    } else {
        $bot_pinecone_config = apply_filters('mxchat_get_bot_pinecone_config', array(), $bot_id);
        if (empty($bot_pinecone_config)) {
            $pinecone_options = get_option('mxchat_pinecone_addon_options');
            $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
            $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
            $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';
        } else {
            $api_key = $bot_pinecone_config['api_key'] ?? '';
            $host = $bot_pinecone_config['host'] ?? '';
            $namespace = $bot_pinecone_config['namespace'] ?? '';
        }
    }

    if (empty($host) || empty($api_key)) {
        return new WP_Error('pinecone_config', 'Pinecone is not properly configured');
    }

    $api_endpoint = "https://{$host}/vectors/upsert";

    // Build metadata with chunk information
    $metadata = array(
        'text' => $chunk_text,
        'source_url' => $source_url,
        'type' => $content_type,
        'is_chunked' => true,
        'chunk_index' => $chunk_metadata['chunk_index'],
        'total_chunks' => $chunk_metadata['total_chunks'],
        'parent_url_hash' => $chunk_metadata['parent_url_hash'],
        'last_updated' => time(),
        'created_at' => time(),
        'bot_id' => $bot_id
    );

    $vector_data = array(
        'id' => $vector_id,
        'values' => $embedding_vector,
        'metadata' => $metadata
    );

    $request_body = array(
        'vectors' => array($vector_data)
    );

    if (!empty($namespace)) {
        $request_body['namespace'] = $namespace;
    }

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
        return $response;
    }

    $response_code = wp_remote_retrieve_response_code($response);
    if ($response_code !== 200) {
        return new WP_Error('pinecone_api', 'Pinecone API error: HTTP ' . $response_code);
    }

    return true;
}

/**
 * Store a single chunk in WordPress database
 */
private static function store_chunk_in_wordpress_db($content_with_metadata, $source_url, $embedding_vector_serialized, $table_name, $content_type, $chunk_metadata) {
    global $wpdb;

    // For chunks, we always insert new rows (no duplicate checking)
    // The URL includes chunk info in the metadata, but source_url stays the same for grouping
    $result = $wpdb->insert(
        $table_name,
        array(
            'url' => $source_url,
            'article_content' => $content_with_metadata,
            'embedding_vector' => $embedding_vector_serialized,
            'source_url' => $source_url,
            'content_type' => $content_type,
            'timestamp' => current_time('mysql')
        ),
        array('%s', '%s', '%s', '%s', '%s', '%s')
    );

    if ($result === false) {
        return new WP_Error('database_failed', 'Failed to insert chunk: ' . $wpdb->last_error);
    }

    return true;
}

/**
 * Delete all chunks for a given URL
 *
 * @param string $source_url The source URL
 * @param string $bot_id The bot ID
 * @return bool|WP_Error True on success, WP_Error on failure
 */
public static function delete_chunks_for_url($source_url, $bot_id = 'default') {
    //error_log('[MXCHAT-CHUNK-DELETE] Deleting chunks for URL: ' . $source_url);

    if (self::is_pinecone_enabled_for_bot($bot_id)) {
        return self::delete_pinecone_chunks_by_url($source_url, $bot_id);
    } else {
        return self::delete_wordpress_chunks_by_url($source_url);
    }
}

/**
 * Delete all chunks for a URL from Pinecone
 */
private static function delete_pinecone_chunks_by_url($source_url, $bot_id) {
    // Get Pinecone configuration
    if ($bot_id === 'default' || !class_exists('MxChat_Multi_Bot_Manager')) {
        $pinecone_options = get_option('mxchat_pinecone_addon_options');
        $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
        $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
        $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';
    } else {
        $bot_pinecone_config = apply_filters('mxchat_get_bot_pinecone_config', array(), $bot_id);
        if (empty($bot_pinecone_config)) {
            $pinecone_options = get_option('mxchat_pinecone_addon_options');
            $api_key = $pinecone_options['mxchat_pinecone_api_key'] ?? '';
            $host = $pinecone_options['mxchat_pinecone_host'] ?? '';
            $namespace = $pinecone_options['mxchat_pinecone_namespace'] ?? '';
        } else {
            $api_key = $bot_pinecone_config['api_key'] ?? '';
            $host = $bot_pinecone_config['host'] ?? '';
            $namespace = $bot_pinecone_config['namespace'] ?? '';
        }
    }

    if (empty($host) || empty($api_key)) {
        return new WP_Error('pinecone_config', 'Pinecone is not properly configured');
    }

    $base_vector_id = md5($source_url);
    $vectors_to_delete = array();

    // Add the original single-vector ID (for non-chunked content)
    $vectors_to_delete[] = $base_vector_id;

    // Use Pinecone list API to find all chunk vectors with this prefix
    $list_url = "https://{$host}/vectors/list";

    $list_body = array(
        'prefix' => $base_vector_id . '_chunk_',
        'limit' => 100
    );

    if (!empty($namespace)) {
        $list_body['namespace'] = $namespace;
    }

    $list_response = wp_remote_post($list_url, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'accept' => 'application/json',
            'content-type' => 'application/json'
        ),
        'body' => wp_json_encode($list_body),
        'timeout' => 30
    ));

    if (!is_wp_error($list_response)) {
        $list_data = json_decode(wp_remote_retrieve_body($list_response), true);
        if (!empty($list_data['vectors'])) {
            foreach ($list_data['vectors'] as $vector) {
                if (isset($vector['id'])) {
                    $vectors_to_delete[] = $vector['id'];
                }
            }
        }
    }

    if (empty($vectors_to_delete)) {
        //error_log('[MXCHAT-CHUNK-DELETE] No vectors found to delete');
        return true;
    }

    //error_log('[MXCHAT-CHUNK-DELETE] Deleting ' . count($vectors_to_delete) . ' vectors from Pinecone');

    // Delete vectors
    $delete_url = "https://{$host}/vectors/delete";

    $delete_body = array(
        'ids' => $vectors_to_delete
    );

    if (!empty($namespace)) {
        $delete_body['namespace'] = $namespace;
    }

    $delete_response = wp_remote_post($delete_url, array(
        'headers' => array(
            'Api-Key' => $api_key,
            'accept' => 'application/json',
            'content-type' => 'application/json'
        ),
        'body' => wp_json_encode($delete_body),
        'timeout' => 30
    ));

    if (is_wp_error($delete_response)) {
        return $delete_response;
    }

    $response_code = wp_remote_retrieve_response_code($delete_response);
    if ($response_code !== 200) {
        return new WP_Error('pinecone_delete', 'Failed to delete vectors: HTTP ' . $response_code);
    }

    return true;
}

/**
 * Delete all chunks for a URL from WordPress database
 */
private static function delete_wordpress_chunks_by_url($source_url) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mxchat_system_prompt_content';

    // Delete all rows with this source_url (handles both chunked and non-chunked)
    $result = $wpdb->delete(
        $table_name,
        array('source_url' => $source_url),
        array('%s')
    );

    if ($result === false) {
        return new WP_Error('database_delete', 'Failed to delete chunks: ' . $wpdb->last_error);
    }

    //error_log('[MXCHAT-CHUNK-DELETE] Deleted ' . $result . ' rows from WordPress DB');
    return true;
}
}