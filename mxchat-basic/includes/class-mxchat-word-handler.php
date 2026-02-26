<?php
/**
 * Word document handler and processor for MXChat
 * Can be directly bundled in WordPress plugins
 */
class MXChat_Word_Handler {
    private $temp_dir;
    private $options;

    public function __construct($options) {
        $this->options = $options;
        $this->temp_dir = wp_upload_dir()['path'];
    }

    /**
     * Handle Word document upload and processing
     */
    public function mxchat_handle_word_upload() {
        check_ajax_referer('mxchat_chat_nonce', 'nonce');
        
        if (!isset($_FILES['word_file']) || !isset($_POST['session_id'])) {
            wp_send_json_error(esc_html__('Missing required parameters.', 'mxchat'));
            return;
        }
    
        // SECURITY FIX: Check if Word uploads are enabled in settings
        $options = get_option('mxchat_options', array());
        $show_word_button = isset($options['show_word_upload_button']) ? $options['show_word_upload_button'] : 'on';
        
        if ($show_word_button !== 'on') {
            wp_send_json_error(esc_html__('Word document uploads are currently disabled.', 'mxchat'));
            return;
        }
    
        $file = $_FILES['word_file'];
        $session_id = sanitize_text_field($_POST['session_id']);
        $original_filename = sanitize_text_field($file['name']);
        
        // SECURITY FIX: Verify session ownership before allowing upload
        $current_user_identifier = MxChat_User::mxchat_get_user_identifier();
        $session_owner = get_option("mxchat_session_owner_{$session_id}");
        
        if ($session_owner && $session_owner !== $current_user_identifier) {
            wp_send_json_error(esc_html__('Unauthorized access.', 'mxchat'));
            return;
        }
        
        // Check file type
        $allowed_types = array(
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        );
        $file_type = wp_check_filetype($file['name'], $allowed_types);
        
        if (!$file_type['type']) {
            wp_send_json_error(esc_html__('Invalid file type. Only .docx files are allowed.', 'mxchat'));
            return;
        }
        
        // SECURITY FIX: Generate random filename without exposing session_id
        $random_string = wp_generate_password(20, false, false); // 20 char alphanumeric string
        $word_filename = 'mxchat_word_' . $random_string . '_' . time() . '.docx';
        $word_path = $this->temp_dir . '/' . $word_filename;
        
        if (!move_uploaded_file($file['tmp_name'], $word_path)) {
            wp_send_json_error(esc_html__('Failed to upload file.', 'mxchat'));
            return;
        }
        
        $this->mxchat_clear_word_transients($session_id);
        
        // Process the document
        $embeddings = $this->mxchat_process_word_document($word_path);
        
        if ($embeddings === false || empty($embeddings)) {
            unlink($word_path);
            $error_message = $this->options['word_intent_error_text'] ?? 
                esc_html__('The uploaded document appears to be empty or contains unsupported content.', 'mxchat');
            wp_send_json_error($error_message);
            return;
        }
        
        // Store the mapping between session and the random filename
        set_transient('mxchat_word_url_' . $session_id, $word_path, HOUR_IN_SECONDS);
        set_transient('mxchat_word_filename_' . $session_id, $original_filename, HOUR_IN_SECONDS);
        set_transient('mxchat_word_embeddings_' . $session_id, $embeddings, HOUR_IN_SECONDS);
        set_transient('mxchat_include_word_in_context_' . $session_id, true, HOUR_IN_SECONDS);
        
        $success_message = $this->options['pdf_intent_success_text'] ?? 
            __("I've processed the document. What questions do you have about it?", 'mxchat');
        
        wp_send_json_success([
            'message' => $success_message,
            'filename' => $original_filename
        ]);
    }

    /**
     * Process Word document and generate embeddings
     */
private function mxchat_process_word_document($file_path) {
    // Get the maximum number of pages allowed from admin settings
    $max_pages = isset($this->options['pdf_max_pages']) ? intval($this->options['pdf_max_pages']) : 69; // Use same setting as PDF

    try {
        $zip = new ZipArchive();
        if ($zip->open($file_path) !== true) {
            return false;
        }

        // Extract main document content
        $content = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($content === false) {
            return false;
        }

        // Clean up the content
        $text = $this->mxchat_clean_word_content($content);
        
        // Count pages (roughly estimate based on paragraphs)
        $paragraphs = explode("\n\n", $text);
        $estimated_pages = ceil(count($paragraphs) / 3); // Assume ~3 paragraphs per page

        if ($estimated_pages > $max_pages) {
            return esc_html__('too_many_pages', 'mxchat');
        }

        // Split into chunks and continue processing...
        $chunks = $this->mxchat_split_word_into_chunks($text, 1000);

        $embeddings = [];
        foreach ($chunks as $chunk_number => $chunk) {
            if (empty(trim($chunk))) {
                continue;
            }

            $embedding = $this->mxchat_generate_embedding_word(
                esc_html__('Chunk ', 'mxchat') . ($chunk_number + 1) . ': ' . $chunk,
                $this->options['api_key']
            );

            if ($embedding) {
                $embeddings[] = [
                    'chunk_number' => $chunk_number + 1,
                    'embedding' => $embedding,
                    'text' => $chunk,
                ];
            }
        }

        return $embeddings;

    } catch (Exception $e) {
        return false;
    }
}
    /**
     * Clean Word XML content
     */
    private function mxchat_clean_word_content($content) {
        // Remove XML namespaces
        $content = preg_replace('/xmlns[^=]*="[^"]*"/i', '', $content);
        
        // Convert Word XML elements to text
        $content = str_replace('</w:p>', "\n", $content);
        $content = str_replace('</w:tr>', "\n", $content);
        
        // Strip remaining XML tags
        $content = strip_tags($content);
        
        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        $content = preg_replace('/\n\s*\n/', "\n\n", $content);
        
        return trim($content);
    }

    /**
     * Split text into manageable chunks
     */
    private function mxchat_split_word_into_chunks($text, $chunk_size) {
        $chunks = [];
        $paragraphs = explode("\n\n", $text);
        
        $current_chunk = '';
        foreach ($paragraphs as $paragraph) {
            if (strlen($current_chunk) + strlen($paragraph) > $chunk_size) {
                if (!empty($current_chunk)) {
                    $chunks[] = trim($current_chunk);
                }
                $current_chunk = $paragraph;
            } else {
                $current_chunk .= (!empty($current_chunk) ? "\n\n" : '') . $paragraph;
            }
        }
        
        if (!empty($current_chunk)) {
            $chunks[] = trim($current_chunk);
        }
        
        return $chunks;
    }

    /**
     * Remove Word document and clean up transients
     */
public function mxchat_handle_word_remove() {
        check_ajax_referer('mxchat_chat_nonce', 'nonce');

        if (empty($_POST['session_id'])) {
            wp_send_json_error(esc_html__('Session ID missing.', 'mxchat'));
            return;
        }

        $session_id = sanitize_text_field($_POST['session_id']);
        $word_path = get_transient('mxchat_word_url_' . $session_id);

        if ($word_path && file_exists($word_path)) {
            unlink($word_path);
        }

        $this->mxchat_clear_word_transients($session_id);

        wp_send_json_success([
            'message' => esc_html__('Document removed successfully.', 'mxchat')
        ]);
    }

    /**
     * Clear all Word-related transients
     */
    private function mxchat_clear_word_transients($session_id) {
        delete_transient('mxchat_word_url_' . $session_id);
        delete_transient('mxchat_word_filename_' . $session_id);
        delete_transient('mxchat_word_embeddings_' . $session_id);
        delete_transient('mxchat_include_word_in_context_' . $session_id);
    }

    /**
     * Find relevant chunks from the Word document
     */
    public function mxchat_find_relevant_word_chunks($query_embedding, $embeddings) {
        $most_relevant = null;
        $highest_similarity = -INF;

        foreach ($embeddings as $chunk_data) {
            $similarity = $this->mxchat_calculate_cosine_similarity_word($query_embedding, $chunk_data['embedding']);

            if ($similarity > $highest_similarity) {
                $highest_similarity = $similarity;
                $most_relevant = $chunk_data['chunk_number'];
            }
        }

        if (!is_null($most_relevant)) {
            $chunk_numbers = range(
                max(1, $most_relevant - 1),
                min(count($embeddings), $most_relevant + 1)
            );
            return array_filter($embeddings, function ($chunk) use ($chunk_numbers) {
                return in_array($chunk['chunk_number'], $chunk_numbers);
            });
        }

        return [];
    }

    /**
     * Handle Word document discussion similar to PDF discussion
     */
public function mxchat_handle_word_discussion($message, $user_id, $session_id) {
        // Get stored embeddings for the session
        $embeddings = get_transient('mxchat_word_embeddings_' . $session_id);
        $word_path = get_transient('mxchat_word_url_' . $session_id);

        if (!$embeddings || !$word_path) {
            $trigger_text = $this->options['word_intent_trigger_text'] ?? 
                __("Please upload a Word document (.docx) that you'd like to discuss.", 'mxchat');
            set_transient('mxchat_waiting_for_word_' . $session_id, true, HOUR_IN_SECONDS);
            $this->fallbackResponse['text'] = $trigger_text;
            return;
        }

        // Set context flag for including Word content in conversation
        set_transient('mxchat_include_word_in_context_' . $session_id, true, HOUR_IN_SECONDS);
        $this->fallbackResponse['text'] = ''; // Proceed without additional message
    }
    
    
    private function mxchat_generate_embedding_word($text, $api_key) {
        $endpoint = 'https://api.openai.com/v1/embeddings';

        $body = wp_json_encode([
            'input' => $text,
            'model' => 'text-embedding-ada-002'
        ]);

        $args = [
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'timeout' => 60,
            'redirection' => 5,
            'blocking' => true,
            'httpversion' => '1.0',
            'sslverify' => true,
        ];

        $response = wp_remote_post($endpoint, $args);

        if (is_wp_error($response)) {
            return null;
        }

        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['data'][0]['embedding']) && is_array($response_body['data'][0]['embedding'])) {
            return $response_body['data'][0]['embedding'];
        } else {
            return null;
        }
    }
    
    
    private function mxchat_calculate_cosine_similarity_word($vectorA, $vectorB) {
        if (!is_array($vectorA) || !is_array($vectorB) || empty($vectorA) || empty($vectorB)) {
            return 0;
        }

        $dotProduct = array_sum(array_map(function ($a, $b) {
            return $a * $b;
        }, $vectorA, $vectorB));
        $normA = sqrt(array_sum(array_map(function ($a) {
            return $a * $a;
        }, $vectorA)));
        $normB = sqrt(array_sum(array_map(function ($b) {
            return $b * $b;
        }, $vectorB)));

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dotProduct / ($normA * $normB);
    }
    
    /**
 * Check the status of a Word document for the current session
 */
public function mxchat_check_word_status() {
    check_ajax_referer('mxchat_chat_nonce', 'nonce');

    if (empty($_POST['session_id'])) {
        wp_send_json_error(esc_html__('Session ID missing.', 'mxchat'));
        return;
    }

    $session_id = sanitize_text_field($_POST['session_id']);
    $word_path = get_transient('mxchat_word_url_' . $session_id);
    $filename = get_transient('mxchat_word_filename_' . $session_id);

    if ($word_path && file_exists($word_path) && $filename) {
        wp_send_json_success([
            'has_word' => true,
            'filename' => $filename
        ]);
    } else {
        wp_send_json_success([
            'has_word' => false
        ]);
    }
}


}